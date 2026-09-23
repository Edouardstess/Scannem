<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Session wrapper with hardened cookie parameters and flash messages.
 */
final class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;

            return;
        }

        if (PHP_SAPI === 'cli') {
            // Tests exercise the session API without a real PHP session.
            self::$started = true;
            $_SESSION = $_SESSION ?? [];

            return;
        }

        session_name((string) Config::get('security.session_name', 'studio_session'));

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => (bool) Config::get('security.session_secure', false),
            'httponly' => true,
            'samesite' => (string) Config::get('security.session_samesite', 'Lax'),
        ]);

        Environment::iniSet('session.use_strict_mode', '1');
        Environment::iniSet('session.use_only_cookies', '1');
        Environment::iniSet('session.gc_maxlifetime', (string) Config::get('security.session_lifetime', 7200));

        session_start();
        self::$started = true;

        self::enforceIdleTimeout();
    }

    private static function enforceIdleTimeout(): void
    {
        $lifetime = (int) Config::get('security.session_lifetime', 7200);
        $last = (int) (self::get('_last_activity', 0));

        if ($last > 0 && (time() - $last) > $lifetime) {
            self::destroy();
            session_start();
            self::$started = true;
        }

        self::put('_last_activity', time());
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        return $_SESSION ?? [];
    }

    public static function regenerate(): void
    {
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        $_SESSION = [];

        if (PHP_SAPI === 'cli') {
            return;
        }

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        self::$started = false;
    }

    public static function flash(string $type, string $message): void
    {
        $flashes = self::get('_flash', []);
        $flashes[] = ['type' => $type, 'message' => $message];
        self::put('_flash', $flashes);
    }

    /** @return array<int, array{type: string, message: string}> */
    public static function pullFlashes(): array
    {
        $flashes = self::get('_flash', []);
        self::forget('_flash');

        return is_array($flashes) ? $flashes : [];
    }

    /** Remember submitted values so a failed form can be redisplayed. */
    public static function flashInput(array $input): void
    {
        unset($input['password'], $input['password_confirmation'], $input['_token'], $input['_method']);
        self::put('_old_input', $input);
    }

    /** @return array<string, mixed> */
    public static function pullOldInput(): array
    {
        $old = self::get('_old_input', []);
        self::forget('_old_input');

        return is_array($old) ? $old : [];
    }

    public static function flashErrors(array $errors): void
    {
        self::put('_errors', $errors);
    }

    /** @return array<string, string> */
    public static function pullErrors(): array
    {
        $errors = self::get('_errors', []);
        self::forget('_errors');

        return is_array($errors) ? $errors : [];
    }
}
