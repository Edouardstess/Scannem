<?php
declare(strict_types=1);

/** Changement du mot de passe par l'utilisateur connecté. */
final class PasswordController extends Controller
{
    public function changer(): void
    {
        AuthMiddleware::exigerConnexion(false);

        $this->render('auth/changer-mot-de-passe', [
            'titrePage' => 'Changer le mot de passe',
            'erreurs'   => [],
            'impose'    => Auth::doitChangerMdp(),
        ], 'guest');
    }

    public function mettreAJour(): void
    {
        AuthMiddleware::exigerConnexion(false);
        $this->exigerCsrf();

        $ancien       = is_string($_POST['ancien_mot_de_passe'] ?? null) ? $_POST['ancien_mot_de_passe'] : '';
        $nouveau      = is_string($_POST['nouveau_mot_de_passe'] ?? null) ? $_POST['nouveau_mot_de_passe'] : '';
        $confirmation = is_string($_POST['confirmation'] ?? null) ? $_POST['confirmation'] : '';

        $pdo = Database::pdo();
        $v = new Validator($_POST);

        $stmt = $pdo->prepare('SELECT mot_de_passe_hash FROM utilisateurs WHERE id = :id AND actif = 1');
        $stmt->execute([':id' => Auth::id()]);
        $hash = $stmt->fetchColumn();

        if (!is_string($hash) || !password_verify($ancien, $hash)) {
            $v->ajouterErreur('ancien_mot_de_passe', 'Le mot de passe actuel est incorrect.');
        }

        $v->motDePasse('nouveau_mot_de_passe', $nouveau);

        if ($nouveau !== '' && $nouveau === $ancien) {
            $v->ajouterErreur('nouveau_mot_de_passe', 'Le nouveau mot de passe doit être différent de l\'ancien.');
        }

        if ($nouveau !== $confirmation) {
            $v->ajouterErreur('confirmation', 'Les deux mots de passe ne correspondent pas.');
        }

        if ($v->echec()) {
            $this->render('auth/changer-mot-de-passe', [
                'titrePage' => 'Changer le mot de passe',
                'erreurs'   => $v->erreurs(),
                'impose'    => Auth::doitChangerMdp(),
            ], 'guest');
            return;
        }

        $pdo->prepare(
            'UPDATE utilisateurs
             SET mot_de_passe_hash = :hash, doit_changer_mdp = 0,
                 jeton_reinit_mdp = NULL, jeton_expire_le = NULL
             WHERE id = :id'
        )->execute([':hash' => password_hash($nouveau, PASSWORD_DEFAULT), ':id' => Auth::id()]);

        Session::set('doit_changer_mdp', false);
        Session::regenerer();
        Csrf::regenerer();

        Auth::journaliser(Auth::id(), 'changement_mot_de_passe', 'utilisateurs', Auth::id());
        Flash::succes('Votre mot de passe a été modifié.');

        $this->redirect('/dashboard');
    }
}
