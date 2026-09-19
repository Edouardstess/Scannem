<?php
declare(strict_types=1);

/**
 * Session PHP durcie : cookie HttpOnly, SameSite=Lax, « secure » aligné sur le
 * protocole réel, expiration contrôlée côté serveur.
 */
final class Session
{
    private static bool $demarree = false;

    public static function demarrer(): void
    {
        if (self::$demarree || session_status() === PHP_SESSION_ACTIVE) {
            self::$demarree = true;
            return;
        }

        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');

        $chemin = parse_url(URL_BASE, PHP_URL_PATH);
        $chemin = is_string($chemin) && $chemin !== '' ? rtrim($chemin, '/') . '/' : '/';

        session_name(SESSION_NAME);
        session_set_cookie_params([
            // lifetime = 0 : vrai cookie de session. Un cookie qui expire au bout
            // de DUREE_SESSION disparaît pendant qu'une page reste ouverte et le
            // formulaire n'est plus soumettable (échec CSRF). L'inactivité est
            // contrôlée côté serveur par verifierExpiration().
            'lifetime' => 0,
            'path'     => $chemin,
            'domain'   => '',
            'secure'   => uep_requete_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
        self::$demarree = true;
        self::verifierExpiration();
    }

    /**
     * Invalide l'identité après DUREE_SESSION d'inactivité, mais conserve le
     * jeton CSRF déjà transmis au navigateur : sans cela, une page de connexion
     * restée ouverte trop longtemps est rejetée en 403 à la première soumission.
     */
    private static function verifierExpiration(): void
    {
        $derniere = (int)self::get('derniere_activite', time());

        if (time() - $derniere > DUREE_SESSION) {
            $jeton = $_SESSION['csrf_token'] ?? null;
            $_SESSION = [];
            session_regenerate_id(true);
            if (is_string($jeton)) {
                $_SESSION['csrf_token'] = $jeton;
            }
            $_SESSION['session_expiree'] = true;
        }

        self::set('derniere_activite', time());
    }

    public static function regenerer(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function get(string $cle, mixed $defaut = null): mixed
    {
        return $_SESSION[$cle] ?? $defaut;
    }

    public static function set(string $cle, mixed $valeur): void
    {
        $_SESSION[$cle] = $valeur;
    }

    public static function supprimer(string $cle): void
    {
        unset($_SESSION[$cle]);
    }

    /** Lit une valeur puis la supprime (messages à usage unique). */
    public static function consommer(string $cle): mixed
    {
        $valeur = $_SESSION[$cle] ?? null;
        unset($_SESSION[$cle]);
        return $valeur;
    }

    public static function estConnecte(): bool
    {
        return self::get('utilisateur_id') !== null;
    }

    public static function detruire(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::$demarree = false;
            return;
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path'] ?? '/',
                'domain'   => $p['domain'] ?? '',
                'secure'   => (bool)($p['secure'] ?? false),
                'httponly' => true,
                'samesite' => $p['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
        self::$demarree = false;
    }
}
