<?php
declare(strict_types=1);

/**
 * Socle commun aux deux questionnaires dynamiques (UPD et DDE).
 *
 * Les deux modules partagent exactement la même mécanique : un catalogue de
 * questions, une table de réponses, un circuit de validation. Les différences
 * (noms de tables, libellés, colonnes dénormalisées) sont déclarées par les
 * classes filles ; aucune logique n'est dupliquée.
 */
abstract class QuestionnaireController extends Controller
{
    /** Préfixe des routes et des tables : « upd » ou « dde ». */
    abstract protected function base(): string;

    /** Libellé au singulier, par exemple « UPD ». */
    abstract protected function sigle(): string;

    /** Intitulé long affiché dans les titres de page. */
    abstract protected function intitule(): string;

    /**
     * Colonnes dénormalisées, alimentées depuis les réponses de la section A.
     *
     * @return array<string, string> colonne SQL => code de ligne du catalogue
     */
    abstract protected function colonnesDenormalisees(): array;

    final protected function tableInstitutions(): string
    {
        return 'institutions_' . $this->base();
    }

    final protected function tableCatalogue(): string
    {
        return $this->base() . '_questions_catalogue';
    }

    final protected function tableReponses(): string
    {
        return $this->base() . '_reponses';
    }

    final protected function colonneLien(): string
    {
        return $this->base() . '_id';
    }

    final protected function colonneNom(): string
    {
        return 'nom_' . $this->base();
    }

    // ------------------------------------------------------------------ Liste

    public function liste(): void
    {
        AuthMiddleware::exigerConnexion();

        $pdo = Database::pdo();
        $recherche = $this->filtre('q');
        $statut = $this->filtre('statut');
        $departement = $this->filtre('departement');

        $conditions = [];
        $parametres = [];

        if ($recherche !== '') {
            // Un placeholder nommé ne peut pas être réutilisé dans la même
            // requête lorsque l'émulation des requêtes préparées est désactivée.
            $conditions[] = '(i.' . $this->colonneNom() . ' LIKE :q1 OR i.departement LIKE :q2)';
            $motif = '%' . $recherche . '%';
            $parametres[':q1'] = $motif;
            $parametres[':q2'] = $motif;
        }
        if (in_array($statut, Workflow::STATUTS, true)) {
            $conditions[] = 'i.statut_validation = :statut';
            $parametres[':statut'] = $statut;
        }
        if ($departement !== '' && Departements::valide($departement)) {
            $conditions[] = 'i.departement = :dept';
            $parametres[':dept'] = $departement;
        }

        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);

        $stmtTotal = $pdo->prepare('SELECT COUNT(*) FROM ' . $this->tableInstitutions() . ' i' . $where);
        $stmtTotal->execute($parametres);
        $pagination = new Paginator((int)$stmtTotal->fetchColumn(), Paginator::pageDemandee());

        $sql = 'SELECT i.id, i.' . $this->colonneNom() . ' AS nom, i.departement, i.statut_validation,
                       i.updated_at,
                       (SELECT COUNT(*) FROM ' . $this->tableCatalogue() . ' WHERE actif = 1) AS total_questions,
                       (SELECT COUNT(*)
                          FROM ' . $this->tableReponses() . ' r
                          JOIN ' . $this->tableCatalogue() . ' q ON q.id = r.question_id
                         WHERE r.' . $this->colonneLien() . ' = i.id
                           AND q.actif = 1 AND r.valeur IS NOT NULL AND r.valeur <> \'\') AS questions_repondues
                FROM ' . $this->tableInstitutions() . ' i'
                . $where
                . ' ORDER BY (i.' . $this->colonneNom() . ' IS NULL), i.' . $this->colonneNom() . ', i.id
                    LIMIT :limite OFFSET :offset';

        $stmt = $pdo->prepare($sql);
        foreach ($parametres as $cle => $valeur) {
            $stmt->bindValue($cle, $valeur);
        }
        $stmt->bindValue(':limite', $pagination->parPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $pagination->offset, PDO::PARAM_INT);
        $stmt->execute();

        $this->render('questionnaire/liste', [
            'titrePage'    => $this->sigle() . ' — ' . $this->intitule(),
            'base'         => $this->base(),
            'sigle'        => $this->sigle(),
            'intitule'     => $this->intitule(),
            'dossiers'     => $stmt->fetchAll(),
            'pagination'   => $pagination,
            'recherche'    => $recherche,
            'statut'       => $statut,
            'departement'  => $departement,
        ]);
    }

    // ------------------------------------------------------------- Création

    public function nouveau(): void
    {
        RoleMiddleware::exigerRole(['administrateur', 'saisisseur']);
        $this->exigerCsrf();

        $pdo = Database::pdo();
        $pdo->prepare('INSERT INTO ' . $this->tableInstitutions() . ' (created_by) VALUES (:uid)')
            ->execute([':uid' => Auth::id()]);

        $id = (int)$pdo->lastInsertId();
        Auth::journaliser(Auth::id(), 'creation_' . $this->base(), $this->tableInstitutions(), $id);
        Flash::succes('Nouveau dossier ' . $this->sigle() . ' créé. Complétez la section A pour le nommer.');

        $this->redirect('/' . $this->base() . '/' . $id);
    }

    // ------------------------------------------------------------ Formulaire

    public function formulaire(int $id): void
    {
        AuthMiddleware::exigerConnexion();

        $dossier = $this->trouver($id);
        if ($dossier === null) {
            ErreurHttp::afficher(404);
        }

        $this->afficherFormulaire($dossier, $this->questions(), $this->reponses($id), []);
    }

    public function sauvegarder(int $id): void
    {
        RoleMiddleware::exigerRole(['administrateur', 'saisisseur']);
        $this->exigerCsrf();

        $dossier = $this->trouver($id);
        if ($dossier === null) {
            ErreurHttp::afficher(404);
        }

        // Un dossier soumis ou validé est figé : il doit d'abord être rouvert.
        if (!Workflow::peutModifier($dossier['statut_validation'] ?? null, Auth::role())) {
            Flash::erreur(
                'Ce dossier est « ' . Workflow::libelle($dossier['statut_validation'] ?? null)
                . ' » : la saisie est verrouillée.'
            );
            $this->redirect('/' . $this->base() . '/' . $id);
        }

        $questions = $this->questions();
        [$erreurs, $valeurs] = $this->validerReponsesCatalogue($questions, $_POST);

        if ($erreurs !== []) {
            $reponses = $this->reponses($id);
            // On réaffiche ce que l'utilisateur vient de saisir, pour ne rien perdre.
            foreach ($questions as $question) {
                $cle = 'q_' . (int)$question['id'];
                if (isset($_POST[$cle]) && is_scalar($_POST[$cle])) {
                    $reponses[(int)$question['id']] = (string)$_POST[$cle];
                }
            }

            http_response_code(422);
            Flash::erreur('Le formulaire contient ' . count($erreurs) . ' erreur(s). Les champs concernés sont signalés en rouge.');
            $this->afficherFormulaire($dossier, $questions, $reponses, $erreurs);
            return;
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $upsert = $pdo->prepare(
                'INSERT INTO ' . $this->tableReponses() . ' (' . $this->colonneLien() . ', question_id, valeur, saisi_par)
                 VALUES (:lien, :qid, :valeur, :uid)
                 ON DUPLICATE KEY UPDATE
                    valeur     = VALUES(valeur),
                    saisi_par  = VALUES(saisi_par),
                    updated_at = CURRENT_TIMESTAMP'
            );
            $suppression = $pdo->prepare(
                'DELETE FROM ' . $this->tableReponses() . ' WHERE ' . $this->colonneLien() . ' = :lien AND question_id = :qid'
            );

            foreach ($valeurs as $qid => $valeur) {
                if ($valeur === null) {
                    $suppression->execute([':lien' => $id, ':qid' => $qid]);
                } else {
                    $upsert->execute([':lien' => $id, ':qid' => $qid, ':valeur' => $valeur, ':uid' => Auth::id()]);
                }
            }

            $this->synchroniserDenormalisation($pdo, $id);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Sauvegarde ' . $this->sigle() . ' échouée : ' . $e->getMessage());
            Flash::erreur('L\'enregistrement a échoué. Aucune donnée n\'a été modifiée.');
            $this->redirect('/' . $this->base() . '/' . $id);
        }

        Auth::journaliser(Auth::id(), 'maj_' . $this->base(), $this->tableInstitutions(), $id, 'Enregistrement du questionnaire');
        Flash::succes('Les données ont été enregistrées.');

        $this->redirect('/' . $this->base() . '/' . $id);
    }

    // -------------------------------------------------------------- Workflow

    public function changerStatut(int $id): void
    {
        AuthMiddleware::exigerConnexion();
        $this->exigerCsrf();

        $dossier = $this->trouver($id);
        if ($dossier === null) {
            ErreurHttp::afficher(404);
        }

        $action = $this->champTexte('action', 20);
        $statutActuel = Workflow::statutValide($dossier['statut_validation'] ?? null);
        $cible = Workflow::cible($action, $statutActuel, Auth::role());

        if ($cible === null) {
            ErreurHttp::afficher(403, 'Cette action n\'est pas disponible pour ce dossier dans son état actuel.');
        }

        Workflow::appliquer($this->tableInstitutions(), $id, $cible);
        Auth::journaliser(
            Auth::id(),
            'statut_' . $this->base() . '_' . $action,
            $this->tableInstitutions(),
            $id,
            $statutActuel . ' -> ' . $cible
        );

        Flash::succes('Dossier ' . mb_strtolower(Workflow::libelleCourt($cible)) . '.');
        $this->redirect('/' . $this->base() . '/' . $id);
    }

    // ----------------------------------------------------------- Suppression

    public function supprimer(int $id): void
    {
        RoleMiddleware::exigerRole(['administrateur']);
        $this->exigerCsrf();

        $dossier = $this->trouver($id);
        if ($dossier === null) {
            ErreurHttp::afficher(404);
        }

        // Les réponses sont supprimées en cascade par la clé étrangère.
        Database::pdo()
            ->prepare('DELETE FROM ' . $this->tableInstitutions() . ' WHERE id = :id')
            ->execute([':id' => $id]);

        Auth::journaliser(
            Auth::id(),
            'suppression_' . $this->base(),
            $this->tableInstitutions(),
            $id,
            (string)($dossier[$this->colonneNom()] ?? '')
        );
        Flash::succes('Le dossier ' . $this->sigle() . ' a été supprimé.');

        $this->redirect('/' . $this->base());
    }

    // ------------------------------------------------------------- Internes

    /** @param array<int, string> $erreurs */
    private function afficherFormulaire(array $dossier, array $questions, array $reponses, array $erreurs): void
    {
        $nom = trim((string)($dossier[$this->colonneNom()] ?? ''));

        $this->render('questionnaire/formulaire', [
            'titrePage' => $this->sigle() . ' — ' . ($nom !== '' ? $nom : 'Dossier #' . (int)$dossier['id']),
            'base'      => $this->base(),
            'sigle'     => $this->sigle(),
            'intitule'  => $this->intitule(),
            'dossier'   => $dossier,
            'nomDossier'=> $nom !== '' ? $nom : 'Nouveau dossier ' . $this->sigle(),
            'sections'  => $this->organiserSections($questions),
            'reponses'  => $reponses,
            'erreurs'   => $erreurs,
        ]);
    }

    protected function trouver(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM ' . $this->tableInstitutions() . ' WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $ligne = $stmt->fetch();

        return is_array($ligne) ? $ligne : null;
    }

    /** @return list<array<string, mixed>> */
    protected function questions(): array
    {
        return Database::pdo()->query(
            'SELECT id, section_code, section_libelle, groupe_code, groupe_libelle,
                    ligne_code, ligne_libelle, colonne_libelle, type_reponse, obligatoire
             FROM ' . $this->tableCatalogue() . '
             WHERE actif = 1
             ORDER BY section_code, ordre_affichage, id'
        )->fetchAll();
    }

    /** @return array<int, string> question_id => valeur */
    protected function reponses(int $id): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT question_id, valeur FROM ' . $this->tableReponses() . ' WHERE ' . $this->colonneLien() . ' = :id'
        );
        $stmt->execute([':id' => $id]);

        $reponses = [];
        foreach ($stmt->fetchAll() as $ligne) {
            $reponses[(int)$ligne['question_id']] = (string)$ligne['valeur'];
        }

        return $reponses;
    }

    /** Regroupe les questions par section puis par sous-groupe, pour l'affichage. */
    protected function organiserSections(array $questions): array
    {
        $sections = [];

        foreach ($questions as $question) {
            $section = (string)$question['section_code'];
            $groupe = $question['groupe_code'] ?? '_simple';

            $sections[$section] ??= [
                'code'    => $section,
                'libelle' => (string)$question['section_libelle'],
                'groupes' => [],
            ];

            $sections[$section]['groupes'][$groupe] ??= [
                'code'    => $question['groupe_code'],
                'libelle' => (string)($question['groupe_libelle'] ?? ''),
                'lignes'  => [],
            ];

            $sections[$section]['groupes'][$groupe]['lignes'][] = $question;
        }

        return $sections;
    }

    /**
     * Recopie les réponses d'identification de la section A dans les colonnes
     * dénormalisées, qui servent aux listes et au tableau de bord.
     */
    protected function synchroniserDenormalisation(PDO $pdo, int $id): void
    {
        $correspondances = $this->colonnesDenormalisees();
        if ($correspondances === []) {
            return;
        }

        $codes = array_values($correspondances);
        $placeholders = implode(', ', array_fill(0, count($codes), '?'));

        $stmt = $pdo->prepare(
            'SELECT q.ligne_code, r.valeur
             FROM ' . $this->tableReponses() . ' r
             JOIN ' . $this->tableCatalogue() . ' q ON q.id = r.question_id
             WHERE r.' . $this->colonneLien() . ' = ? AND q.ligne_code IN (' . $placeholders . ')'
        );
        $stmt->execute(array_merge([$id], $codes));

        $valeurs = [];
        foreach ($stmt->fetchAll() as $ligne) {
            $valeurs[(string)$ligne['ligne_code']] = $ligne['valeur'];
        }

        $affectations = [];
        $parametres = [':id' => $id];
        foreach ($correspondances as $colonne => $code) {
            $affectations[] = $colonne . ' = :' . $colonne;
            $valeur = $valeurs[$code] ?? null;
            $parametres[':' . $colonne] = is_string($valeur) && trim($valeur) !== '' ? trim($valeur) : null;
        }

        $pdo->prepare(
            'UPDATE ' . $this->tableInstitutions() . ' SET ' . implode(', ', $affectations) . ' WHERE id = :id'
        )->execute($parametres);
    }
}
