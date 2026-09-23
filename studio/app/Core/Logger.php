<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Append-only application log.
 *
 * Context values are redacted before writing: passwords, raw tokens and
 * secrets must never reach the log file, because log files get emailed,
 * copied into tickets and read by third parties.
 */
final class Logger
{
    private const REDACTED_KEYS = [
        'password', 'password_confirmation', 'current_password', 'new_password',
        'token', 'raw_token', 'view_token', 'download_token', 'media_token',
        'secret', 'app_key', 'authorization', 'cookie', 'csrf_token',
        'db_password', 'mail_password',
    ];

    public static function debug(string $message, array $context = []): void
    {
        if (Config::get('app.debug')) {
            self::write('DEBUG', $message, $context);
        }
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    private static function write(string $level, string $message, array $context): void
    {
        $directory = (string) Config::get('storage.logs', dirname(__DIR__, 2) . '/storage/logs');

        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return;
        }

        $line = sprintf(
            "[%s] %s: %s %s\n",
            date('Y-m-d H:i:s'),
            $level,
            $message,
            $context === [] ? '' : json_encode(self::redact($context), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        @file_put_contents($directory . '/app-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    /** @return array<string, mixed> */
    private static function redact(array $context): array
    {
        $clean = [];

        foreach ($context as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::REDACTED_KEYS, true)) {
                $clean[$key] = '[redacted]';

                continue;
            }

            $clean[$key] = is_array($value) ? self::redact($value) : $value;
        }

        return $clean;
    }
}
