<?php
declare(strict_types=1);

/** Fiche du compte connecté : identité, rôle, dernière connexion, activité. */
final class ProfilController extends Controller
{
    public function index(): void
    {
        AuthMiddleware::exigerConnexion();

        $pdo = Database::pdo();

        $stmt = $pdo->prepare(
            'SELECT u.nom_complet, u.email, u.telephone, u.departement_rattachement,
                    u.institution_type, u.derniere_connexion, u.created_at,
                    r.nom_role, r.libelle AS role_libelle, r.description AS role_description
             FROM utilisateurs u
             JOIN roles r ON r.id = u.role_id
             WHERE u.id = :id'
        );
        $stmt->execute([':id' => Auth::id()]);
        $utilisateur = $stmt->fetch();

        if (!is_array($utilisateur)) {
            Auth::deconnecter(false);
            $this->redirect('/login');
        }

        $stmt = $pdo->prepare(
            'SELECT action, table_concernee, details, adresse_ip, created_at
             FROM journal_activites
             WHERE utilisateur_id = :id
             ORDER BY id DESC
             LIMIT 15'
        );
        $stmt->execute([':id' => Auth::id()]);

        $this->render('utilisateurs/profil', [
            'titrePage'   => 'Mon profil',
            'utilisateur' => $utilisateur,
            'activites'   => $stmt->fetchAll(),
        ]);
    }
}
