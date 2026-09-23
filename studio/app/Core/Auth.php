<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Role;
use App\Repositories\UserRepository;

/**
 * Administrator authentication and permission checks.
 *
 * Client galleries do NOT go through this class: a gallery visitor is not a
 * user, they hold a capability token. Keeping the two apart means a gallery
 * link can never be escalated into an admin session.
 */
final class Auth
{
    private const SESSION_USER_ID = '_auth_user_id';
    private const SESSION_FINGERPRINT = '_auth_fingerprint';

    /** @var array<string, mixed>|null */
    private static ?array $cachedUser = null;

    public static function attempt(string $email, string $password): ?array
    {
        $repository = new UserRepository();
        $user = $repository->findByEmail($email);

        if ($user === null) {
            // Spend comparable time on a missing account so that response
            // timing does not reveal which e-mail addresses exist.
            password_verify($password, '$2y$12$usesomesillystringforsalt0123456789abcdefghijklmnopqrstuv');

            return null;
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            return null;
        }

        if (($user['status'] ?? '') !== 'active') {
            return null;
        }

        $repository->rehashIfNeeded((int) $user['id'], (string) $user['password_hash'], $password);

        return $user;
    }

    /** @param array<string, mixed> $user */
    public static function login(array $user, Request $request): void
    {
        // Session fixation defence: a brand new session id for the new
        // privilege level.
        Session::regenerate();
        Session::put(self::SESSION_USER_ID, (int) $user['id']);
        Session::put(self::SESSION_FINGERPRINT, self::fingerprint($request));
        Csrf::rotate();

        self::$cachedUser = null;

        (new UserRepository())->touchLogin((int) $user['id']);
    }

    public static function logout(): void
    {
        Session::forget(self::SESSION_USER_ID);
        Session::forget(self::SESSION_FINGERPRINT);
        self::$cachedUser = null;
        Session::regenerate();
        Csrf::rotate();
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        if (self::$cachedUser !== null) {
            return self::$cachedUser;
        }

        $id = Session::get(self::SESSION_USER_ID);

        if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
            return null;
        }

        $user = (new UserRepository())->find((int) $id);

        // A disabled or deleted account loses its session immediately, so
        // revoking access does not wait for the session to expire.
        if ($user === null || ($user['status'] ?? '') !== 'active') {
            Session::forget(self::SESSION_USER_ID);

            return null;
        }

        self::$cachedUser = $user;

        return $user;
    }

    public static function id(): ?int
    {
        $user = self::user();

        return $user === null ? null : (int) $user['id'];
    }

    public static function role(): ?string
    {
        $user = self::user();

        return $user === null ? null : (string) $user['role'];
    }

    public static function can(string $permission): bool
    {
        $role = self::role();

        return $role !== null && Role::grants($role, $permission);
    }

    public static function cannot(string $permission): bool
    {
        return !self::can($permission);
    }

    /**
     * Bind the session to a coarse client fingerprint.
     *
     * Only the user agent is used: IP addresses change legitimately on mobile
     * networks, and logging a photographer out mid-upload because they moved
     * from wifi to 4G is a worse outcome than the marginal gain.
     */
    private static function fingerprint(Request $request): string
    {
        return hash('sha256', $request->userAgent() . '|' . Config::get('app.key'));
    }

    public static function fingerprintMatches(Request $request): bool
    {
        $stored = Session::get(self::SESSION_FINGERPRINT);

        if (!is_string($stored)) {
            return true;
        }

        return hash_equals($stored, self::fingerprint($request));
    }

    public static function forgetCache(): void
    {
        self::$cachedUser = null;
    }
}
