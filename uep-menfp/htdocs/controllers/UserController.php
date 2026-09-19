<?php
declare(strict_types=1);

/** Gestion des comptes — réservée aux administrateurs. */
final class UserController extends Controller
{
    public function liste(): void
    {
        RoleMiddleware::exigerRole(['administrateur']);

        $pdo = Database::pdo();
        $recherche = $this->filtre('q');
        $role = $this->filtre('role');

        $conditions = [];
        $parametres = [];

        if ($recherche !== '') {
            // Un placeholder nommé ne peut pas être réutilisé dans la même
            // requête lorsque l'émulation des requêtes préparées est désactivée.
            $conditions[] = '(u.nom_complet LIKE :q1 OR u.email LIKE :q2 OR u.telephone LIKE :q3)';
            $motif = '%' . $recherche . '%';
            $parametres[':q1'] = $motif;
            $parametres[':q2'] = $motif;
            $parametres[':q3'] = $motif;
        }
        if (in_array($role, Auth::ROLES, true)) {
            $conditions[] = 'r.nom_role = :role';
            $parametres[':role'] = $role;
        }

        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);

        $stmtTotal = $pdo->prepare('SELECT COUNT(*) FROM utilisateurs u JOIN roles r ON r.id = u.role_id' . $where);
        $stmtTotal->execute($parametres);
        $pagination = new Paginator((int)$stmtTotal->fetchColumn(), Paginator::pageDemandee());

        $stmt = $pdo->prepare(
            'SELECT u.id, u.nom_complet, u.email, u.telephone, u.actif, u.doit_changer_mdp,
                    u.derniere_connexion, u.created_at, u.departement_rattachement,
                    r.nom_role, r.libelle AS role_libelle
             FROM utilisateurs u
             JOIN roles r ON r.id = u.role_id'
            . $where
            . ' ORDER BY u.actif DESC, u.nom_complet LIMIT :limite OFFSET :offset'
        );
        foreach ($parametres as $cle => $valeur) {
            $stmt->bindValue($cle, $valeur);
        }
        $stmt->bindValue(':limite', $pagination->parPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $pagination->offset, PDO::PARAM_INT);
        $stmt->execute();

        $this->render('utilisateurs/liste', [
            'titrePage'  => 'Utilisateurs',
            'users'      => $stmt->fetchAll(),
            'pagination' => $pagination,
            'recherche'  => $recherche,
            'role'       => $role,
            'roles'      => $this->roles(),
        ]);
    }

    public function nouveau(): void
    {
        RoleMiddleware::exigerRole(['administrateur']);

        $this->render('utilisateurs/formulaire', [
            'titrePage'    => 'Créer un utilisateur',
            'mode'         => 'creation',
            'user'         => ['id' => 0, 'nom_complet' => '', 'email' => '', 'telephone' => '',
                               'departement_rattachement' => '', 'role_id' => 0, 'actif' => 1,
                               'doit_changer_mdp' => 1],
            'roles'        => $this->roles(),
            'departements' => Departements::tous(),
            'erreurs'      => [],
        ]);
    }

    public function creer(): void
    {
        RoleMiddleware::exigerRole(['administrateur']);
        $this->exigerCsrf();

        $pdo = Database::pdo();
        $email = Auth::normaliserEmail($this->champTexte('email', 150));
        $motDePasse = is_string($_POST['mot_de_passe'] ?? null) ? $_POST['mot_de_passe'] : '';
        $roleId = filter_var($_POST['role_id'] ?? null, FILTER_VALIDATE_INT);
        $departement = $this->champTexte('departement_rattachement', 50);

        $v = new Validator($_POST);
        $v->obligatoire('nom_complet', 'Le nom complet est obligatoire.')
          ->longueurMax('nom_complet', 150, 'Le nom complet ne peut pas dépasser 150 caractères.')
          ->obligatoire('email', 'L\'adresse électronique est obligatoire.')
          ->email('email', 'Format d\'adresse électronique invalide.')
          ->longueurMax('telephone', 30, 'Le téléphone ne peut pas dépasser 30 caractères.')
          ->motDePasse('mot_de_passe', $motDePasse);

        if (!$this->roleExiste($roleId)) {
            $v->ajouterErreur('role_id', 'Sélectionnez un rôle valide.');
        }
        if ($departement !== '' && !Departements::valide($departement)) {
            $v->ajouterErreur('departement_rattachement', 'Département invalide.');
        }

        $stmt = $pdo->prepare('SELECT 1 FROM utilisateurs WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        if ($stmt->fetchColumn()) {
            $v->ajouterErreur('email', 'Cette adresse est déjà utilisée par un autre compte.');
        }

        if ($v->echec()) {
            http_response_code(422);
            $this->render('utilisateurs/formulaire', [
                'titrePage'    => 'Créer un utilisateur',
                'mode'         => 'creation',
                'user'         => $this->userDepuisPost(0),
                'roles'        => $this->roles(),
                'departements' => Departements::tous(),
                'erreurs'      => $v->erreurs(),
            ]);
            return;
        }

        $pdo->prepare(
            'INSERT INTO utilisateurs
                (nom_complet, email, mot_de_passe_hash, role_id, departement_rattachement,
                 telephone, actif, doit_changer_mdp)
             VALUES (:nom, :email, :hash, :role, :dept, :tel, :actif, :changer)'
        )->execute([
            ':nom'     => $this->champTexte('nom_complet', 150),
            ':email'   => $email,
            ':hash'    => password_hash($motDePasse, PASSWORD_DEFAULT),
            ':role'    => $roleId,
            ':dept'    => $departement !== '' ? $departement : null,
            ':tel'     => $this->champNullable('telephone', 30),
            ':actif'   => isset($_POST['actif']) ? 1 : 0,
            ':changer' => isset($_POST['doit_changer_mdp']) ? 1 : 0,
        ]);

        $id = (int)$pdo->lastInsertId();
        Auth::journaliser(Auth::id(), 'creation_utilisateur', 'utilisateurs', $id, $email);
        Flash::succes('Le compte de ' . $this->champTexte('nom_complet', 150) . ' a été créé.');

        $this->redirect('/utilisateurs');
    }

    public function modifier(int $id): void
    {
        RoleMiddleware::exigerRole(['administrateur']);

        $user = $this->trouver($id);
        if ($user === null) {
            ErreurHttp::afficher(404);
        }

        $this->render('utilisateurs/formulaire', [
            'titrePage'    => 'Modifier ' . $user['nom_complet'],
            'mode'         => 'modification',
            'user'         => $user,
            'roles'        => $this->roles(),
            'departements' => Departements::tous(),
            'erreurs'      => [],
        ]);
    }

    public function mettreAJour(int $id): void
    {
        RoleMiddleware::exigerRole(['administrateur']);
        $this->exigerCsrf();

        $pdo = Database::pdo();
        $user = $this->trouver($id);
        if ($user === null) {
            ErreurHttp::afficher(404);
        }

        $email = Auth::normaliserEmail($this->champTexte('email', 150));
        $roleId = filter_var($_POST['role_id'] ?? null, FILTER_VALIDATE_INT);
        $departement = $this->champTexte('departement_rattachement', 50);
        $nouveauMdp = is_string($_POST['nouveau_mot_de_passe'] ?? null) ? $_POST['nouveau_mot_de_passe'] : '';
        $actif = isset($_POST['actif']);

        $v = new Validator($_POST);
        $v->obligatoire('nom_complet', 'Le nom complet est obligatoire.')
          ->longueurMax('nom_complet', 150, 'Le nom complet ne peut pas dépasser 150 caractères.')
          ->obligatoire('email', 'L\'adresse électronique est obligatoire.')
          ->email('email', 'Format d\'adresse électronique invalide.')
          ->longueurMax('telephone', 30, 'Le téléphone ne peut pas dépasser 30 caractères.');

        if ($nouveauMdp !== '') {
            $v->motDePasse('nouveau_mot_de_passe', $nouveauMdp);
        }

        if (!$this->roleExiste($roleId)) {
            $v->ajouterErreur('role_id', 'Sélectionnez un rôle valide.');
        }
        if ($departement !== '' && !Departements::valide($departement)) {
            $v->ajouterErreur('departement_rattachement', 'Département invalide.');
        }

        $stmt = $pdo->prepare('SELECT 1 FROM utilisateurs WHERE email = :email AND id <> :id LIMIT 1');
        $stmt->execute([':email' => $email, ':id' => $id]);
        if ($stmt->fetchColumn()) {
            $v->ajouterErreur('email', 'Cette adresse est déjà utilisée par un autre compte.');
        }

        $seraAdminActif = $this->nomDuRole($roleId) === 'administrateur' && $actif;

        // Un administrateur ne peut pas se retirer ses propres droits : sinon
        // il se verrouille lui-même hors de l'application.
        if ($id === Auth::id() && !$seraAdminActif) {
            $v->ajouterErreur('role_id', 'Vous ne pouvez pas retirer votre propre accès administrateur ni désactiver votre compte.');
        }

        // Il doit toujours rester au moins un administrateur actif.
        if (!$seraAdminActif) {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM utilisateurs u
                 JOIN roles r ON r.id = u.role_id
                 WHERE r.nom_role = 'administrateur' AND u.actif = 1 AND u.id <> :id"
            );
            $stmt->execute([':id' => $id]);
            if ((int)$stmt->fetchColumn() === 0) {
                $v->ajouterErreur('role_id', 'L\'application doit conserver au moins un administrateur actif.');
            }
        }

        if ($v->echec()) {
            http_response_code(422);
            $this->render('utilisateurs/formulaire', [
                'titrePage'    => 'Modifier ' . $user['nom_complet'],
                'mode'         => 'modification',
                'user'         => array_merge($user, $this->userDepuisPost($id)),
                'roles'        => $this->roles(),
                'departements' => Departements::tous(),
                'erreurs'      => $v->erreurs(),
            ]);
            return;
        }

        $sql = 'UPDATE utilisateurs SET
                    nom_complet = :nom, email = :email, role_id = :role,
                    departement_rattachement = :dept, telephone = :tel,
                    actif = :actif, doit_changer_mdp = :changer';

        $parametres = [
            ':nom'     => $this->champTexte('nom_complet', 150),
            ':email'   => $email,
            ':role'    => $roleId,
            ':dept'    => $departement !== '' ? $departement : null,
            ':tel'     => $this->champNullable('telephone', 30),
            ':actif'   => $actif ? 1 : 0,
            ':changer' => isset($_POST['doit_changer_mdp']) ? 1 : 0,
            ':id'      => $id,
        ];

        if ($nouveauMdp !== '') {
            $sql .= ', mot_de_passe_hash = :hash';
            $parametres[':hash'] = password_hash($nouveauMdp, PASSWORD_DEFAULT);
        }

        $pdo->prepare($sql . ' WHERE id = :id')->execute($parametres);

        Auth::journaliser(Auth::id(), 'modification_utilisateur', 'utilisateurs', $id, $email);

        if ($id === Auth::id()) {
            Auth::actualiserSession();
        }

        Flash::succes('Le compte a été mis à jour.');
        $this->redirect('/utilisateurs');
    }

    public function supprimer(int $id): void
    {
        RoleMiddleware::exigerRole(['administrateur']);
        $this->exigerCsrf();

        if ($id === Auth::id()) {
            Flash::erreur('Vous ne pouvez pas supprimer votre propre compte.');
            $this->redirect('/utilisateurs');
        }

        $user = $this->trouver($id);
        if ($user === null) {
            ErreurHttp::afficher(404);
        }

        if ($user['nom_role'] === 'administrateur' && $this->nombreAdminsActifs($id) === 0) {
            Flash::erreur('L\'application doit conserver au moins un administrateur actif.');
            $this->redirect('/utilisateurs');
        }

        try {
            Database::pdo()->prepare('DELETE FROM utilisateurs WHERE id = :id')->execute([':id' => $id]);
        } catch (PDOException $e) {
            // Les clés étrangères protègent les données métier déjà saisies.
            error_log('Suppression d\'utilisateur refusée : ' . $e->getMessage());
            Flash::erreur(
                'Ce compte ne peut pas être supprimé : des réquisitions ou des saisies lui sont rattachées. '
                . 'Désactivez-le plutôt.'
            );
            $this->redirect('/utilisateurs');
        }

        Auth::journaliser(Auth::id(), 'suppression_utilisateur', 'utilisateurs', $id, (string)$user['email']);
        Flash::succes('Le compte a été supprimé.');

        $this->redirect('/utilisateurs');
    }

    // ------------------------------------------------------------- Internes

    private function trouver(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT u.*, r.nom_role, r.libelle AS role_libelle
             FROM utilisateurs u
             JOIN roles r ON r.id = u.role_id
             WHERE u.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $ligne = $stmt->fetch();

        return is_array($ligne) ? $ligne : null;
    }

    private function roles(): array
    {
        return Database::pdo()->query('SELECT id, nom_role, libelle, description FROM roles ORDER BY id')->fetchAll();
    }

    private function roleExiste(mixed $roleId): bool
    {
        if (!is_int($roleId) || $roleId <= 0) {
            return false;
        }

        $stmt = Database::pdo()->prepare('SELECT 1 FROM roles WHERE id = :id');
        $stmt->execute([':id' => $roleId]);

        return (bool)$stmt->fetchColumn();
    }

    private function nomDuRole(mixed $roleId): ?string
    {
        if (!is_int($roleId) || $roleId <= 0) {
            return null;
        }

        $stmt = Database::pdo()->prepare('SELECT nom_role FROM roles WHERE id = :id');
        $stmt->execute([':id' => $roleId]);
        $nom = $stmt->fetchColumn();

        return is_string($nom) ? $nom : null;
    }

    private function nombreAdminsActifs(int $sauf): int
    {
        $stmt = Database::pdo()->prepare(
            "SELECT COUNT(*) FROM utilisateurs u
             JOIN roles r ON r.id = u.role_id
             WHERE r.nom_role = 'administrateur' AND u.actif = 1 AND u.id <> :id"
        );
        $stmt->execute([':id' => $sauf]);

        return (int)$stmt->fetchColumn();
    }

    private function userDepuisPost(int $id): array
    {
        return [
            'id'                       => $id,
            'nom_complet'              => $this->champTexte('nom_complet', 150),
            'email'                    => $this->champTexte('email', 150),
            'telephone'                => $this->champTexte('telephone', 30),
            'departement_rattachement' => $this->champTexte('departement_rattachement', 50),
            'role_id'                  => (int)($_POST['role_id'] ?? 0),
            'actif'                    => isset($_POST['actif']) ? 1 : 0,
            'doit_changer_mdp'         => isset($_POST['doit_changer_mdp']) ? 1 : 0,
        ];
    }
}
