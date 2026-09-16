<?php

declare(strict_types=1);

namespace Scannem;

/**
 * Session de l'organisateur et protection CSRF.
 */
final class Session
{
    public static function start(Config $config): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,              // inaccessible au JavaScript
            'samesite' => 'Strict',          // le cookie ne part pas sur un lien externe
            'secure' => $config->bool('cookie_secure', false) || self::isHttps(),
        ]);

        session_name('scannem_admin');
        session_start();
    }

    private static function isHttps(): bool
    {
        return (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? '') === '443'
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    public static function login(array $admin): void
    {
        // Nouvel identifiant de session a la connexion : sans ca, un identifiant
        // fixe avant authentification reste valable apres (fixation de session).
        session_regenerate_id(true);

        $_SESSION['admin_id'] = (int) $admin['id'];
        $_SESSION['admin_user'] = (string) $admin['username'];
        $_SESSION['logged_at'] = time();
    }

    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name() ?: 'scannem_admin', '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'httponly' => true,
                'samesite' => 'Strict',
                'secure' => $params['secure'],
            ]);
        }

        session_destroy();
    }

    public static function isLogged(): bool
    {
        return isset($_SESSION['admin_id']);
    }

    public static function username(): string
    {
        return (string) ($_SESSION['admin_user'] ?? '');
    }

    public static function requireLogin(): void
    {
        if (!self::isLogged()) {
            header('Location: /admin/?p=login');
            exit;
        }
    }

    public static function csrfToken(): string
    {
        if (!isset($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['csrf'];
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="_csrf" value="' . Http::escape(self::csrfToken()) . '">';
    }

    /** Verifie le jeton CSRF d'un POST, et coupe court si absent ou faux. */
    public static function requireCsrf(): void
    {
        $sent = $_POST['_csrf'] ?? '';

        if (!is_string($sent) || !hash_equals(self::csrfToken(), $sent)) {
            http_response_code(419);
            exit('Jeton de securite invalide ou expire. Recharge la page et recommence.');
        }
    }
}
