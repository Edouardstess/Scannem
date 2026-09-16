<?php

declare(strict_types=1);

namespace Scannem;

/**
 * Utilitaires HTTP partages par l'API et l'admin.
 */
final class Http
{
    /** @param array<string,mixed> $data */
    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function error(string $message, int $status = 400, string $code = 'error'): never
    {
        self::json(['ok' => false, 'error' => $code, 'message' => $message], $status);
    }

    /**
     * Corps de requete JSON.
     *
     * @return array<string,mixed>
     */
    public static function body(): array
    {
        $raw = file_get_contents('php://input');

        if ($raw === false || $raw === '') {
            return [];
        }

        // Garde-fou : une file de synchronisation geante ne doit pas faire exploser
        // la memoire du serveur mutualise.
        if (strlen($raw) > 4 * 1024 * 1024) {
            self::error('Requete trop volumineuse.', 413, 'payload_too_large');
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    public static function requireMethod(string $method): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $method) {
            header('Allow: ' . $method);
            self::error('Methode non autorisee.', 405, 'method_not_allowed');
        }
    }

    public static function clientIp(): ?string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        return is_string($ip) && $ip !== '' ? substr($ip, 0, 45) : null;
    }

    /** Horodatage client, valide sommairement avant stockage. */
    public static function clientAt(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        $value = substr($value, 0, 25);

        return preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $value) === 1 ? $value : null;
    }

    /** En-tetes de securite communs aux pages HTML. */
    public static function securityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: same-origin');
        header('Cache-Control: no-store');
    }

    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
