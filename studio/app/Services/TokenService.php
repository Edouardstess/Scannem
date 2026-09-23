<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Encrypter;
use App\Models\TokenType;
use App\Repositories\GalleryTokenRepository;

/**
 * Generation and verification of gallery access tokens.
 *
 * Design rules, in order of importance:
 *
 * 1. A token is 32 bytes from random_bytes(). Never md5/sha1 of anything
 *    guessable, never a database id, never a timestamp. 256 bits of entropy
 *    makes enumeration pointless.
 * 2. Lookups use SHA-256 of the token. A leaked database backup therefore
 *    contains no usable links. SHA-256 (not bcrypt) is the right choice for
 *    this input: it is already high-entropy, so there is nothing to
 *    brute-force, and a lookup has to be one indexed query.
 * 3. A second copy, encrypted with APP_KEY, is kept so the photographer can
 *    re-open the share dialog weeks later and copy the same link. The key is
 *    in .env, so the database alone still yields nothing. Without APP_KEY the
 *    link simply cannot be shown again and must be regenerated — which is a
 *    recoverable inconvenience, not a security hole.
 * 3. Verification returns a reason, not just true/false, so the client sees
 *    "expired" rather than "not found" — and so the audit log records which.
 */
final class TokenService
{
    public const RESULT_VALID     = 'valid';
    public const RESULT_NOT_FOUND = 'not_found';
    public const RESULT_REVOKED   = 'revoked';
    public const RESULT_EXPIRED   = 'expired';
    public const RESULT_WRONG_TYPE = 'wrong_type';

    /** Raw token length in bytes before hex encoding. */
    private const TOKEN_BYTES = 32;

    public function __construct(
        private ?GalleryTokenRepository $tokens = null
    ) {
        $this->tokens = $tokens ?? new GalleryTokenRepository();
    }

    /**
     * Create a cryptographically random token.
     *
     * Returned to the caller once, in the clear. It is never retrievable
     * afterwards: the photographer copies the link, or regenerates it.
     */
    public function generateRawToken(): string
    {
        return bin2hex(random_bytes(self::TOKEN_BYTES));
    }

    public function hash(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    /**
     * Issue a token for a gallery.
     *
     * @return array{id: int, raw: string, type: string, expires_at: ?string}
     */
    public function issue(int $galleryId, string $type, ?string $expiresAt = null): array
    {
        if (!in_array($type, TokenType::ALL, true)) {
            throw new \InvalidArgumentException('Unknown token type: ' . $type);
        }

        $raw = $this->generateRawToken();

        $id = $this->tokens->insert([
            'gallery_id'   => $galleryId,
            'token_hash'   => $this->hash($raw),
            'token_cipher' => $this->encryptForRecall($raw),
            'token_type'   => $type,
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
            'use_count'  => 0,
        ]);

        return ['id' => $id, 'raw' => $raw, 'type' => $type, 'expires_at' => $expiresAt];
    }

    /**
     * Replace a gallery's token of a given type.
     *
     * The previous token is revoked in the same operation, so regenerating a
     * link always invalidates the one that was shared before.
     *
     * @return array{id: int, raw: string, type: string, expires_at: ?string}
     */
    public function regenerate(int $galleryId, string $type, ?string $expiresAt = null): array
    {
        $current = $this->tokens->activeFor($galleryId, $type);

        if ($current !== null) {
            $expiresAt ??= $current['expires_at'] === null ? null : (string) $current['expires_at'];
            $this->tokens->revoke((int) $current['id']);
        }

        return $this->issue($galleryId, $type, $expiresAt);
    }

    /**
     * Verify a raw token from a URL.
     *
     * @param string      $rawToken     Value taken from the URL.
     * @param string|null $expectedType Constrain to VIEW or DOWNLOAD.
     * @return array{status: string, token: array<string, mixed>|null}
     */
    public function verify(string $rawToken, ?string $expectedType = null): array
    {
        // Reject anything that is not shaped like one of our tokens before
        // touching the database.
        if (!$this->looksLikeToken($rawToken)) {
            return ['status' => self::RESULT_NOT_FOUND, 'token' => null];
        }

        $token = $this->tokens->findByHash($this->hash($rawToken));

        if ($token === null) {
            return ['status' => self::RESULT_NOT_FOUND, 'token' => null];
        }

        if (GalleryTokenRepository::isRevoked($token)) {
            return ['status' => self::RESULT_REVOKED, 'token' => $token];
        }

        if (GalleryTokenRepository::hasExpired($token)) {
            return ['status' => self::RESULT_EXPIRED, 'token' => $token];
        }

        if ($expectedType !== null && (string) $token['token_type'] !== $expectedType) {
            return ['status' => self::RESULT_WRONG_TYPE, 'token' => $token];
        }

        return ['status' => self::RESULT_VALID, 'token' => $token];
    }

    /**
     * Recover the raw token of a stored row, for the share dialog.
     *
     * Returns null when APP_KEY is missing or has changed since the token was
     * issued; callers must handle that by offering to regenerate the link
     * rather than by showing a broken one.
     *
     * @param array<string, mixed> $token
     */
    public function revealRawToken(array $token): ?string
    {
        $cipher = $token['token_cipher'] ?? null;

        if (!is_string($cipher) || $cipher === '') {
            return null;
        }

        $raw = Encrypter::decrypt($cipher);

        if ($raw === null) {
            return null;
        }

        // Defence against a swapped or corrupted ciphertext: the decrypted
        // value must still hash to the row's stored hash.
        return hash_equals((string) $token['token_hash'], $this->hash($raw)) ? $raw : null;
    }

    private function encryptForRecall(string $raw): ?string
    {
        if (!Encrypter::isAvailable()) {
            return null;
        }

        try {
            return Encrypter::encrypt($raw);
        } catch (\Throwable) {
            // Losing the recall copy must never block issuing a link.
            return null;
        }
    }

    public function looksLikeToken(string $candidate): bool
    {
        return strlen($candidate) === self::TOKEN_BYTES * 2 && ctype_xdigit($candidate);
    }

    public function revoke(int $tokenId): void
    {
        $this->tokens->revoke($tokenId);
    }

    public function revokeGallery(int $galleryId, ?string $type = null): int
    {
        return $this->tokens->revokeAllFor($galleryId, $type);
    }

    public function markUsed(int $tokenId): void
    {
        $this->tokens->touch($tokenId);
    }

    /** Public URL for a raw token. */
    public function urlFor(string $type, string $rawToken): string
    {
        $prefix = $type === TokenType::DOWNLOAD ? 'download' : 'gallery';

        return rtrim((string) Config::get('app.url'), '/') . '/' . $prefix . '/' . $rawToken;
    }

    /**
     * Translate an expiry choice from the admin form into a datetime.
     *
     * @param string      $option never|24h|7d|30d|custom
     * @param string|null $customDate Y-m-d or Y-m-d H:i when $option is custom
     */
    public function resolveExpiry(string $option, ?string $customDate = null): ?string
    {
        return match ($option) {
            '24h'    => date('Y-m-d H:i:s', strtotime('+24 hours')),
            '7d'     => date('Y-m-d H:i:s', strtotime('+7 days')),
            '30d'    => date('Y-m-d H:i:s', strtotime('+30 days')),
            'custom' => $this->parseCustomDate($customDate),
            default  => null,
        };
    }

    private function parseCustomDate(?string $customDate): ?string
    {
        if ($customDate === null || trim($customDate) === '') {
            return null;
        }

        $timestamp = strtotime($customDate);

        if ($timestamp === false) {
            return null;
        }

        // A date without a time means "end of that day", which is what a
        // photographer picking a date in a date field expects.
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($customDate)) === 1) {
            $timestamp = strtotime(trim($customDate) . ' 23:59:59');
        }

        return date('Y-m-d H:i:s', (int) $timestamp);
    }

    public static function statusMessage(string $status): string
    {
        return match ($status) {
            self::RESULT_EXPIRED    => "Cette galerie n'est plus disponible : le lien a expiré.",
            self::RESULT_REVOKED    => "Cette galerie n'est plus disponible : le lien a été désactivé.",
            self::RESULT_WRONG_TYPE => "Ce lien ne donne pas accès à cette page.",
            default                 => "Cette galerie n'est plus disponible.",
        };
    }
}
