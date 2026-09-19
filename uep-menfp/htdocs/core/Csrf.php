<?php
declare(strict_types=1);

/**
 * Jeton anti-CSRF : une valeur aléatoire par session, insérée dans chaque
 * formulaire et comparée en temps constant à la réception.
 */
final class Csrf
{
    private const CLE = 'csrf_token';

    public static function jeton(): string
    {
        $jeton = Session::get(self::CLE);

        if (!is_string($jeton) || strlen($jeton) !== 64) {
            $jeton = bin2hex(random_bytes(32));
            Session::set(self::CLE, $jeton);
        }

        return $jeton;
    }

    public static function verifier(mixed $recu): bool
    {
        $attendu = Session::get(self::CLE);

        if (!is_string($attendu) || !is_string($recu) || $recu === '') {
            return false;
        }

        return hash_equals($attendu, $recu);
    }

    public static function regenerer(): string
    {
        $jeton = bin2hex(random_bytes(32));
        Session::set(self::CLE, $jeton);
        return $jeton;
    }

    /** Champ caché prêt à insérer dans un formulaire. */
    public static function champ(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . self::jeton() . '">';
    }
}
