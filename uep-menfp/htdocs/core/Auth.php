<?php
declare(strict_types=1);

/**
 * Authentification, contrôle de session, anti-bruteforce et journal d'audit.
 */
final class Auth
{
    public const ROLES = ['administrateur', 'superviseur', 'saisisseur', 'lecteur'];

    public static function connecter(string $email, string $motDePasse): bool
    {
        $pdo = Database::pdo();
        $email = self::normaliserEmail($email);

        if (self::estBloque($email)) {
            return false;
        }

        $stmt = $pdo->prepare(
            'SELECT u.id, u.nom_complet, u.email, u.mot_de_passe_hash, u.actif,
                    u.doit_changer_mdp, r.nom_role
             FROM utilisateurs u
             JOIN roles r ON r.id = u.role_id
             WHERE u.email = :email
             LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        // Comparaison systématique, même sans compte : le temps de réponse ne
        // doit pas révéler l'existence d'une adresse.
        $hash = is_array($user) ? (string)$user['mot_de_passe_hash'] : '$2y$12$' . str_repeat('x', 53);
        $mdpValide = password_verify($motDePasse, $hash);

        if (!is_array($user) || !$mdpValide || (int)$user['actif'] !== 1) {
            self::enregistrerTentative($email, false);
            return false;
        }

        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $stmtHash = $pdo->prepare('UPDATE utilisateurs SET mot_de_passe_hash = :hash WHERE id = :id');
            $stmtHash->execute([':hash' => password_hash($motDePasse, PASSWORD_DEFAULT), ':id' => (int)$user['id']]);
        }

        self::enregistrerTentative($email, true);
        // Sans cette purge, les échecs précédents restent comptés pendant toute
        // la fenêtre de blocage : un utilisateur qui s'est trompé plusieurs fois
        // puis a réussi se retrouverait bloqué à la faute suivante.
        self::purgerTentatives($email);

        Session::regenerer();
        Session::set('utilisateur_id', (int)$user['id']);
        Session::set('utilisateur_nom', (string)$user['nom_complet']);
        Session::set('utilisateur_email', (string)$user['email']);
        Session::set('utilisateur_role', (string)$user['nom_role']);
        Session::set('doit_changer_mdp', (int)$user['doit_changer_mdp'] === 1);
        Csrf::regenerer();

        $pdo->prepare('UPDATE utilisateurs SET derniere_connexion = NOW() WHERE id = :id')
            ->execute([':id' => (int)$user['id']]);

        self::journaliser((int)$user['id'], 'connexion', 'utilisateurs', (int)$user['id']);

        return true;
    }

    /**
     * Resynchronise le contexte d'autorisation avec la base à chaque requête :
     * un compte désactivé ou rétrogradé perd ses droits immédiatement, sans
     * attendre l'expiration de sa session.
     */
    public static function actualiserSession(): bool
    {
        $id = self::id();
        if ($id === null) {
            return false;
        }

        $stmt = Database::pdo()->prepare(
            'SELECT u.nom_complet, u.email, u.actif, u.doit_changer_mdp, r.nom_role
             FROM utilisateurs u
             JOIN roles r ON r.id = u.role_id
             WHERE u.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();

        if (!is_array($user) || (int)$user['actif'] !== 1) {
            self::deconnecter(false);
            return false;
        }

        Session::set('utilisateur_nom', (string)$user['nom_complet']);
        Session::set('utilisateur_email', (string)$user['email']);
        Session::set('utilisateur_role', (string)$user['nom_role']);
        Session::set('doit_changer_mdp', (int)$user['doit_changer_mdp'] === 1);

        return true;
    }

    public static function deconnecter(bool $journaliser = true): void
    {
        if ($journaliser && Session::estConnecte()) {
            self::journaliser(self::id(), 'deconnexion');
        }

        Session::detruire();
    }

    public static function estBloque(string $email): bool
    {
        $stmt = Database::pdo()->prepare(
            'SELECT
                 SUM(email_saisi = :email) AS echecs_email,
                 SUM(adresse_ip = :ip)     AS echecs_ip
             FROM tentatives_connexion
             WHERE reussie = 0
               AND created_at >= DATE_SUB(NOW(), INTERVAL :fenetre SECOND)'
        );
        $stmt->bindValue(':email', self::normaliserEmail($email));
        $stmt->bindValue(':ip', self::adresseIp());
        $stmt->bindValue(':fenetre', FENETRE_BLOCAGE, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch() ?: [];

        return (int)($row['echecs_email'] ?? 0) >= MAX_TENTATIVES
            || (int)($row['echecs_ip'] ?? 0) >= MAX_TENTATIVES_IP;
    }

    private static function purgerTentatives(string $email): void
    {
        try {
            Database::pdo()
                ->prepare('DELETE FROM tentatives_connexion WHERE reussie = 0 AND (email_saisi = :email OR adresse_ip = :ip)')
                ->execute([':email' => self::normaliserEmail($email), ':ip' => self::adresseIp()]);
        } catch (Throwable $e) {
            error_log('Purge des tentatives impossible : ' . $e->getMessage());
        }
    }

    private static function enregistrerTentative(string $email, bool $reussie): void
    {
        try {
            Database::pdo()
                ->prepare('INSERT INTO tentatives_connexion (email_saisi, adresse_ip, reussie) VALUES (:email, :ip, :reussie)')
                ->execute([
                    ':email'   => mb_substr(self::normaliserEmail($email), 0, 150),
                    ':ip'      => self::adresseIp(),
                    ':reussie' => $reussie ? 1 : 0,
                ]);
        } catch (Throwable $e) {
            error_log('Enregistrement de tentative impossible : ' . $e->getMessage());
        }
    }

    /** Trace une action dans journal_activites. Un échec d'audit ne casse jamais l'action métier. */
    public static function journaliser(
        ?int $utilisateurId,
        string $action,
        ?string $table = null,
        ?int $enregistrementId = null,
        ?string $details = null
    ): void {
        try {
            Database::pdo()
                ->prepare(
                    'INSERT INTO journal_activites
                        (utilisateur_id, action, table_concernee, enregistrement_id, details, adresse_ip)
                     VALUES (:uid, :action, :tbl, :rid, :details, :ip)'
                )
                ->execute([
                    ':uid'     => $utilisateurId,
                    ':action'  => mb_substr($action, 0, 100),
                    ':tbl'     => $table !== null ? mb_substr($table, 0, 100) : null,
                    ':rid'     => $enregistrementId,
                    ':details' => $details !== null ? mb_substr($details, 0, 4000) : null,
                    ':ip'      => self::adresseIp(),
                ]);
        } catch (Throwable $e) {
            error_log('Journalisation impossible : ' . $e->getMessage());
        }
    }

    /**
     * Adresse IP du client. X-Forwarded-For n'est pris en compte que si la
     * requête provient d'un proxy explicitement déclaré : sinon n'importe qui
     * pourrait contourner l'anti-bruteforce en forgeant l'en-tête.
     */
    public static function adresseIp(): string
    {
        $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        if (!filter_var($remote, FILTER_VALIDATE_IP)) {
            return '0.0.0.0';
        }

        if (in_array($remote, PROXIES_APPROUVES, true)) {
            foreach ([$_SERVER['HTTP_X_FORWARDED_FOR'] ?? '', $_SERVER['HTTP_X_REAL_IP'] ?? ''] as $entete) {
                foreach (array_map('trim', explode(',', (string)$entete)) as $candidat) {
                    if (filter_var($candidat, FILTER_VALIDATE_IP)) {
                        return $candidat;
                    }
                }
            }
        }

        return $remote;
    }

    public static function id(): ?int
    {
        $id = Session::get('utilisateur_id');
        return $id === null ? null : (int)$id;
    }

    public static function nom(): string
    {
        return (string)Session::get('utilisateur_nom', '');
    }

    public static function role(): ?string
    {
        $role = Session::get('utilisateur_role');
        return is_string($role) ? $role : null;
    }

    /** L'utilisateur possède-t-il l'un des rôles donnés ? */
    public static function aRole(string ...$roles): bool
    {
        return in_array(self::role(), $roles, true);
    }

    public static function estAdmin(): bool
    {
        return self::role() === 'administrateur';
    }

    /** Peut créer et modifier des données métier. */
    public static function peutSaisir(): bool
    {
        return self::aRole('administrateur', 'saisisseur');
    }

    /** Peut valider ou rejeter un dossier. */
    public static function peutValider(): bool
    {
        return self::aRole('administrateur', 'superviseur');
    }

    public static function doitChangerMdp(): bool
    {
        return Session::get('doit_changer_mdp') === true;
    }

    public static function normaliserEmail(string $email): string
    {
        return mb_strtolower(trim($email), 'UTF-8');
    }
}
