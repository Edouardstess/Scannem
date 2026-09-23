<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Authenticated symmetric encryption, keyed with APP_KEY.
 *
 * Used for one thing: keeping a recoverable copy of gallery link tokens.
 *
 * Tokens are stored hashed (SHA-256) so that lookups are indexed and a
 * database leak yields no working links. But the photographer must be able to
 * re-copy a link weeks later without invalidating the one already sent to the
 * client, so a second, encrypted copy is stored alongside the hash. The
 * ciphertext is worthless without APP_KEY, which lives in .env and not in the
 * database — so a dump of the database alone still exposes no links.
 *
 * libsodium is preferred (XChaCha20-Poly1305); OpenSSL AES-256-GCM is the
 * fallback. Both are authenticated, so tampering is detected rather than
 * silently decrypted into garbage.
 */
final class Encrypter
{
    private const PREFIX_SODIUM  = 'sd1:';
    private const PREFIX_OPENSSL = 'og1:';

    public static function isAvailable(): bool
    {
        return self::keyMaterial() !== null
            && (function_exists('sodium_crypto_secretbox') || function_exists('openssl_encrypt'));
    }

    public static function encrypt(string $plaintext): string
    {
        $key = self::keyMaterial();

        if ($key === null) {
            throw new RuntimeException('APP_KEY is not configured; cannot encrypt.');
        }

        if (function_exists('sodium_crypto_secretbox')) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $cipher = sodium_crypto_secretbox($plaintext, $nonce, $key);

            return self::PREFIX_SODIUM . base64_encode($nonce . $cipher);
        }

        if (function_exists('openssl_encrypt')) {
            $iv = random_bytes(12);
            $tag = '';
            $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

            if ($cipher === false) {
                throw new RuntimeException('Encryption failed.');
            }

            return self::PREFIX_OPENSSL . base64_encode($iv . $tag . $cipher);
        }

        throw new RuntimeException('No encryption extension available (sodium or openssl).');
    }

    /** @return string|null Null when the value cannot be decrypted or authenticated. */
    public static function decrypt(?string $payload): ?string
    {
        if ($payload === null || $payload === '') {
            return null;
        }

        $key = self::keyMaterial();

        if ($key === null) {
            return null;
        }

        try {
            if (str_starts_with($payload, self::PREFIX_SODIUM) && function_exists('sodium_crypto_secretbox_open')) {
                $raw = base64_decode(substr($payload, strlen(self::PREFIX_SODIUM)), true);

                if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
                    return null;
                }

                $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
                $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
                $plain = sodium_crypto_secretbox_open($cipher, $nonce, $key);

                return $plain === false ? null : $plain;
            }

            if (str_starts_with($payload, self::PREFIX_OPENSSL) && function_exists('openssl_decrypt')) {
                $raw = base64_decode(substr($payload, strlen(self::PREFIX_OPENSSL)), true);

                if ($raw === false || strlen($raw) <= 28) {
                    return null;
                }

                $iv = substr($raw, 0, 12);
                $tag = substr($raw, 12, 16);
                $cipher = substr($raw, 28);
                $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

                return $plain === false ? null : $plain;
            }
        } catch (\Throwable $e) {
            Logger::warning('Decryption failed', ['error' => $e->getMessage()]);

            return null;
        }

        return null;
    }

    /** A 32-byte key derived from APP_KEY, or null when APP_KEY is unset. */
    private static function keyMaterial(): ?string
    {
        $appKey = trim((string) Config::get('app.key', ''));

        if ($appKey === '') {
            return null;
        }

        // APP_KEY is a printable string of arbitrary length; both ciphers need
        // exactly 32 raw bytes, so it is run through SHA-256.
        return hash('sha256', 'gallery-token-v1|' . $appKey, true);
    }

    /** Generate a value suitable for APP_KEY in .env. */
    public static function generateAppKey(): string
    {
        return 'base64:' . base64_encode(random_bytes(32));
    }
}
