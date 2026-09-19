<?php
declare(strict_types=1);

/** Connexion et déconnexion. */
final class AuthController extends Controller
{
    public function login(): void
    {
        if (Session::estConnecte() && Auth::actualiserSession()) {
            $this->redirect(Auth::doitChangerMdp() ? '/mot-de-passe/changer' : '/dashboard');
        }

        if (Session::consommer('session_expiree') === true) {
            Flash::avertissement('Votre session a expiré. Veuillez vous reconnecter.');
        }

        $this->afficherFormulaire();
    }

    public function authentifier(): void
    {
        if (!Csrf::verifier($_POST['csrf_token'] ?? null)) {
            // Un jeton neuf est émis pour que la tentative suivante aboutisse.
            Csrf::regenerer();
            http_response_code(419);
            $this->afficherFormulaire(
                ['general' => 'Votre session a expiré ou les cookies sont bloqués par votre navigateur. Veuillez réessayer.'],
                $this->champTexte('email', 150)
            );
            return;
        }

        $email = Auth::normaliserEmail($this->champTexte('email', 150));
        $motDePasse = is_string($_POST['mot_de_passe'] ?? null) ? $_POST['mot_de_passe'] : '';
        $erreurs = [];

        if ($email === '') {
            $erreurs['email'] = 'L\'adresse électronique est obligatoire.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erreurs['email'] = 'Format d\'adresse électronique invalide.';
        }

        if ($motDePasse === '') {
            $erreurs['mot_de_passe'] = 'Le mot de passe est obligatoire.';
        }

        if ($erreurs !== []) {
            $this->afficherFormulaire($erreurs, $email);
            return;
        }

        if (Auth::connecter($email, $motDePasse)) {
            if (Auth::doitChangerMdp()) {
                Flash::avertissement('Pour votre sécurité, définissez un nouveau mot de passe avant de continuer.');
                $this->redirect('/mot-de-passe/changer');
            }

            $destination = Session::consommer('url_apres_connexion');
            Flash::succes('Bienvenue, ' . Auth::nom() . '.');
            $this->redirect(is_string($destination) && $destination !== '' ? $destination : '/dashboard');
        }

        $message = Auth::estBloque($email)
            ? 'Trop de tentatives échouées. Réessayez dans ' . (int)ceil(FENETRE_BLOCAGE / 60) . ' minutes.'
            : 'Adresse électronique ou mot de passe incorrect.';

        http_response_code(401);
        $this->afficherFormulaire(['general' => $message], $email);
    }

    public function logout(): void
    {
        $this->exigerCsrf();

        Auth::deconnecter();
        Session::demarrer();
        Flash::succes('Vous avez été déconnecté.');

        $this->redirect('/login');
    }

    /** @param array<string, string> $erreurs */
    private function afficherFormulaire(array $erreurs = [], string $email = ''): void
    {
        $this->render('auth/login', [
            'titrePage' => 'Connexion',
            'erreurs'   => $erreurs,
            'email'     => $email,
        ], 'guest');
    }
}
