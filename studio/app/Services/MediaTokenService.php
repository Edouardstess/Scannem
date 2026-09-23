<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

/**
 * Signed, short-lived URLs for individual image deliveries.
 *
 * A gallery page shows hundreds of images, each needing its own URL. Storing a
 * database row per image URL would be wasteful, so the media token is a signed
 * payload instead: photo id, the gallery token that authorised it, the variant
 * being requested, and an expiry, all covered by an HMAC keyed with APP_KEY.
 *
 * The signature only proves the URL was minted by this application. It is NOT
 * the authorisation: MediaController re-loads the gallery token on every
 * request and re-checks revocation, expiry, gallery status, password unlock
 * and download permission. A media token that outlives a revoked gallery link
 * is therefore worthless.
 */
final class MediaTokenService
{
    public const VARIANT_THUMBNAIL = 't';
    public const VARIANT_PREVIEW   = 'p';
    public const VARIANT_ORIGINAL  = 'o';

    public function mint(int $photoId, int $galleryTokenId, string $variant, ?int $ttl = null): string
    {
        $ttl ??= (int) Config::get('security.media_token_ttl', 3600);
        $expiresAt = time() + max(60, $ttl);

        $payload = implode('.', [$photoId, $galleryTokenId, $variant, $expiresAt]);
        $encoded = $this->base64UrlEncode($payload);

        return $encoded . '.' . $this->sign($encoded);
    }

    /**
     * Decode and authenticate a media token.
     *
     * @return array{photo_id: int, token_id: int, variant: string, expires_at: int}|null
     */
    public function parse(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 2) {
            return null;
        }

        [$encoded, $signature] = $parts;

        // Constant-time comparison: a fast reject on the first wrong byte
        // would leak the signature one character at a time.
        if (!hash_equals($this->sign($encoded), $signature)) {
            return null;
        }

        $payload = $this->base64UrlDecode($encoded);
        $fields = explode('.', $payload);

        if (count($fields) !== 4) {
            return null;
        }

        [$photoId, $tokenId, $variant, $expiresAt] = $fields;

        if (!ctype_digit($photoId) || !ctype_digit($tokenId) || !ctype_digit($expiresAt)) {
            return null;
        }

        if (!in_array($variant, [self::VARIANT_THUMBNAIL, self::VARIANT_PREVIEW, self::VARIANT_ORIGINAL], true)) {
            return null;
        }

        if ((int) $expiresAt < time()) {
            return null;
        }

        return [
            'photo_id'   => (int) $photoId,
            'token_id'   => (int) $tokenId,
            'variant'    => $variant,
            'expires_at' => (int) $expiresAt,
        ];
    }

    private function sign(string $payload): string
    {
        return $this->base64UrlEncode(hash_hmac('sha256', $payload, $this->key(), true));
    }

    /**
     * HMAC key.
     *
     * APP_KEY is required in production; the fallback exists so a
     * misconfigured install fails closed with an unusable-but-consistent key
     * rather than with an empty one.
     */
    private function key(): string
    {
        $key = (string) Config::get('app.key', '');

        if ($key === '') {
            $key = 'insecure-fallback-key:' . (string) Config::get('app.url');
        }

        return $key;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $padded = str_pad(strtr($value, '-_', '+/'), (int) (ceil(strlen($value) / 4) * 4), '=', STR_PAD_RIGHT);

        return (string) base64_decode($padded, true);
    }

    public static function variantFor(string $name): string
    {
        return match (strtolower($name)) {
            'thumbnail', 'thumb', 't' => self::VARIANT_THUMBNAIL,
            'original', 'download', 'o' => self::VARIANT_ORIGINAL,
            default => self::VARIANT_PREVIEW,
        };
    }
}
