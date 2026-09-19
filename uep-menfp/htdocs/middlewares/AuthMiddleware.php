<?php
declare(strict_types=1);

/** Exige une session authentifiée valide. */
final class AuthMiddleware
{
    public static function exigerConnexion(bool $respecterChangementMdp = true): void
    {
        if (!Session::estConnecte() || !Auth::actualiserSession()) {
            Session::set('url_apres_connexion', self::cheminCourant());
            Flash::info('Veuillez vous connecter pour accéder à cette page.');
            header('Location: ' . URL_BASE . '/login', true, 303);
            exit;
        }

        if ($respecterChangementMdp && Auth::doitChangerMdp()) {
            header('Location: ' . URL_BASE . '/mot-de-passe/changer', true, 303);
            exit;
        }
    }

    /** Chemin demandé, pour y revenir après la connexion. */
    private static function cheminCourant(): string
    {
        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
            return '/dashboard';
        }

        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $prefixe = (string)parse_url(URL_BASE, PHP_URL_PATH);

        if ($prefixe !== '' && str_starts_with($uri, $prefixe)) {
            $uri = substr($uri, strlen($prefixe));
        }

        $uri = '/' . ltrim($uri, '/');

        // On ne renvoie jamais vers une URL absolue fournie par le client.
        return preg_match('#^/[A-Za-z0-9/_\-?=&.%]*$#', $uri) ? $uri : '/dashboard';
    }
}
