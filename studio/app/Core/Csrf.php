<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Synchroniser-token CSRF protection.
 *
 * One token per session, compared with hash_equals. Tokens are not rotated on
 * every request: rotation breaks multi-tab usage and the parallel uploads the
 * gallery admin relies on, while adding no protection against an attacker who
 * cannot read the token in the first place.
 */
final class Csrf
{
    public const FIELD = '_token';
    public const HEADER = 'X-CSRF-Token';

    private const SESSION_KEY = '_csrf_token';

    public static function token(): string
    {
        $token = Session::get(self::SESSION_KEY);

        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            Session::put(self::SESSION_KEY, $token);
        }

        return $token;
    }

    public static function generate(): string
    {
        return self::token();
    }

    public static function validate(?string $candidate): bool
    {
        $expected = Session::get(self::SESSION_KEY);

        if (!is_string($expected) || $expected === '' || !is_string($candidate) || $candidate === '') {
            return false;
        }

        return hash_equals($expected, $candidate);
    }

    /** Pull the token from the request body or the AJAX header. */
    public static function fromRequest(Request $request): ?string
    {
        $token = $request->input(self::FIELD);

        if (is_string($token) && $token !== '') {
            return $token;
        }

        $header = $request->header(self::HEADER);

        return is_string($header) && $header !== '' ? $header : null;
    }

    public static function field(): string
    {
        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            self::FIELD,
            htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8')
        );
    }

    public static function rotate(): void
    {
        Session::forget(self::SESSION_KEY);
    }
}
