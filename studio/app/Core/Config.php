<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Configuration registry fed by config/*.php files, themselves fed by .env.
 *
 * Secrets never appear in the PHP config files as literals: they are read from
 * the environment so that a leaked repository never leaks credentials.
 */
final class Config
{
    /** @var array<string, mixed> */
    private static array $items = [];

    private static bool $envLoaded = false;

    /**
     * Parse a .env file into the process environment.
     *
     * Deliberately minimal: KEY=VALUE, optional quotes, # comments, no
     * variable interpolation. Values already present in the real environment
     * win, so a hosting panel's environment variables override the file.
     */
    public static function loadEnv(string $path): void
    {
        self::$envLoaded = true;

        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = explode('=', $line, 2);

            if (count($parts) !== 2) {
                continue;
            }

            $key = trim($parts[0]);
            $value = trim($parts[1]);

            if ($key === '') {
                continue;
            }

            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = $value[strlen($value) - 1];

                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            if (getenv($key) !== false) {
                continue;
            }

            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }

    public static function env(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);

        if ($value === false) {
            $value = $_ENV[$key] ?? null;
        }

        if ($value === null || $value === false || $value === '') {
            return $default;
        }

        return match (strtolower((string) $value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            default => $value,
        };
    }

    public static function envBool(string $key, bool $default = false): bool
    {
        $value = self::env($key, $default);

        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    public static function envInt(string $key, int $default = 0): int
    {
        $value = self::env($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public static function load(string $directory): void
    {
        foreach (glob(rtrim($directory, '/') . '/*.php') ?: [] as $file) {
            $name = basename($file, '.php');
            self::$items[$name] = require $file;
        }
    }

    /** Dot-notation read: Config::get('app.url'). */
    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = self::$items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $ref = &self::$items;

        foreach ($segments as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }

            $ref = &$ref[$segment];
        }

        $ref = $value;
    }

    public static function envWasLoaded(): bool
    {
        return self::$envLoaded;
    }
}
