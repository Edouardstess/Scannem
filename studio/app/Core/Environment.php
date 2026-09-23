<?php

declare(strict_types=1);

namespace App\Core;

/**
 * What the PHP runtime actually allows on this server.
 *
 * Shared and free hosts (ByetHost, InfinityFree, ...) disable functions, cap
 * uploads at a few megabytes and keep memory tight. Since PHP 8 a disabled
 * function is simply undefined: calling it is a fatal error that the `@`
 * operator does not suppress, so every optional call goes through here.
 */
final class Environment
{
    /** Megabytes kept free for PHP itself on top of an image's pixels. */
    private const MEMORY_HEADROOM = 24 * 1024 * 1024;

    public static function functionAvailable(string $name): bool
    {
        if (!function_exists($name)) {
            return false;
        }

        $disabled = array_map('trim', explode(',', strtolower((string) ini_get('disable_functions'))));

        return !in_array(strtolower($name), $disabled, true);
    }

    /** ini_set() when the host allows it; a refused setting is not an error. */
    public static function iniSet(string $key, string $value): void
    {
        if (self::functionAvailable('ini_set')) {
            @ini_set($key, $value);
        }
    }

    /** An ini size such as "10M" or "1G" in bytes; 0 means "no limit". */
    public static function iniBytes(string $key): int
    {
        return self::parseBytes((string) ini_get($key));
    }

    public static function parseBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '-1') {
            return 0;
        }

        $number = (float) $value;

        return (int) match (strtolower(substr($value, -1))) {
            'g'     => $number * 1024 * 1024 * 1024,
            'm'     => $number * 1024 * 1024,
            'k'     => $number * 1024,
            default => $number,
        };
    }

    /**
     * The largest file an upload can really carry: the application setting,
     * capped by upload_max_filesize and post_max_size (the form fields and
     * multipart framing travel in the same request, hence the margin).
     */
    public static function uploadLimitBytes(int $applicationLimit): int
    {
        $limits = array_filter([
            $applicationLimit,
            self::iniBytes('upload_max_filesize'),
            max(0, self::iniBytes('post_max_size') - 64 * 1024),
        ], static fn (int $bytes): bool => $bytes > 0);

        return $limits === [] ? $applicationLimit : min($limits);
    }

    /**
     * Whether a POST lost its whole body because it exceeded post_max_size.
     *
     * PHP then hands the script empty $_POST and $_FILES: no CSRF token, no
     * file. Recognising it turns a baffling "session expired" into "file too
     * large".
     */
    public static function postBodyWasDropped(array $server, array $body, array $files): bool
    {
        $length = (int) ($server['CONTENT_LENGTH'] ?? 0);
        $limit = self::iniBytes('post_max_size');

        return ($server['REQUEST_METHOD'] ?? '') === 'POST'
            && $limit > 0
            && $length > $limit
            && $body === []
            && $files === [];
    }

    /**
     * Make sure decoding an image of this size will not exhaust memory.
     *
     * GD holds every pixel uncompressed (about 5 bytes each once the resampled
     * copy is counted): a 24-megapixel photo needs roughly 120 MB. Raises
     * memory_limit when the host allows it; returns false when the image
     * still would not fit, so the caller can refuse it cleanly instead of
     * dying with a fatal error.
     */
    public static function ensureMemoryForImage(int $width, int $height): bool
    {
        $needed = (int) ($width * $height * 5) + memory_get_usage(true) + self::MEMORY_HEADROOM;
        $limit = self::iniBytes('memory_limit');

        if ($limit === 0 || $needed <= $limit) {
            return true;
        }

        self::iniSet('memory_limit', (string) (int) ceil($needed / 1024 / 1024) . 'M');

        $raised = self::iniBytes('memory_limit');

        return $raised === 0 || $needed <= $raised;
    }

    /** The largest image (in megapixels) the current memory_limit can decode. */
    public static function maxImageMegapixels(): float
    {
        $limit = self::iniBytes('memory_limit');

        if ($limit === 0) {
            return INF;
        }

        return max(0.0, ($limit - memory_get_usage(true) - self::MEMORY_HEADROOM) / 5 / 1_000_000);
    }
}
