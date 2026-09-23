<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Csrf;
use App\Core\View;

if (!function_exists('e')) {
    /**
     * Escape a value for HTML output.
     *
     * Every dynamic value printed by a template goes through this. It is the
     * project's only defence against stored XSS, so it is deliberately short
     * enough that there is no excuse to skip it.
     */
    function e(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('eattr')) {
    /** Escape for use inside an HTML attribute. */
    function eattr(mixed $value): string
    {
        return e($value);
    }
}

if (!function_exists('ejs')) {
    /** Embed a PHP value in a <script> block safely. */
    function ejs(mixed $value): string
    {
        return (string) json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('url')) {
    /** Absolute URL for an application path. */
    function url(string $path = '/'): string
    {
        return rtrim((string) Config::get('app.url'), '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return url('assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('method_field')) {
    function method_field(string $method): string
    {
        return sprintf('<input type="hidden" name="_method" value="%s">', e(strtoupper($method)));
    }
}

if (!function_exists('view')) {
    function view(string $template, array $data = []): string
    {
        return View::render($template, $data);
    }
}

if (!function_exists('old')) {
    /** Redisplay a submitted value after a validation failure. */
    function old(string $key, mixed $default = ''): mixed
    {
        $old = View::shared()['old'] ?? [];

        return $old[$key] ?? $default;
    }
}

if (!function_exists('error_for')) {
    function error_for(string $key): ?string
    {
        $errors = View::shared()['errors'] ?? [];

        return $errors[$key] ?? null;
    }
}

if (!function_exists('format_bytes')) {
    function format_bytes(int|float $bytes, int $precision = 1): string
    {
        $units = ['o', 'Ko', 'Mo', 'Go', 'To'];
        $bytes = max((float) $bytes, 0);
        $power = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
        $power = min($power, count($units) - 1);

        return round($bytes / (1024 ** $power), $precision) . ' ' . $units[$power];
    }
}

if (!function_exists('format_date')) {
    function format_date(?string $date, string $format = 'd/m/Y'): string
    {
        if ($date === null || $date === '' || $date === '0000-00-00') {
            return '—';
        }

        $timestamp = strtotime($date);

        return $timestamp === false ? '—' : date($format, $timestamp);
    }
}

if (!function_exists('format_datetime')) {
    function format_datetime(?string $date): string
    {
        return format_date($date, 'd/m/Y H:i');
    }
}

if (!function_exists('format_date_long')) {
    /** "21 septembre 2026", without relying on the intl extension. */
    function format_date_long(?string $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        $timestamp = strtotime($date);

        if ($timestamp === false) {
            return '';
        }

        $months = [
            1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
            'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre',
        ];

        return sprintf(
            '%d %s %d',
            (int) date('j', $timestamp),
            $months[(int) date('n', $timestamp)],
            (int) date('Y', $timestamp)
        );
    }
}

if (!function_exists('parse_date')) {
    /**
     * Parse a date a French visitor might type.
     *
     * `<input type="date">` submits Y-m-d, which is what this normally
     * receives. But that input degrades to a plain text field on browsers
     * that do not support it, and a French user then types 15/06/2026 —
     * which strtotime reads as month 15 and rejects. Accepting the day-first
     * forms turns a silently dropped date into a stored one.
     *
     * @return int|false A timestamp, or false when nothing could be parsed.
     */
    function parse_date(string $value): int|false
    {
        $value = trim($value);

        if ($value === '') {
            return false;
        }

        // Day-first formats, tried before strtotime so that 06/07/2026 is
        // read as 6 July and not as 7 June.
        foreach (['d/m/Y', 'd-m-Y', 'd.m.Y', 'd/m/y'] as $format) {
            $parsed = DateTime::createFromFormat($format . '|', $value);

            if ($parsed !== false && DateTime::getLastErrors() === false) {
                return $parsed->getTimestamp();
            }
        }

        return strtotime($value);
    }
}

if (!function_exists('str_slug')) {
    function str_slug(string $value, string $separator = '-'): string
    {
        $value = trim($value);

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

            if ($converted !== false) {
                $value = $converted;
            }
        }

        $value = strtolower((string) preg_replace('/[^\p{L}\p{N}]+/u', $separator, $value));
        $value = (string) preg_replace('/' . preg_quote($separator, '/') . '+/', $separator, $value);

        return trim($value, $separator);
    }
}

if (!function_exists('str_excerpt')) {
    function str_excerpt(?string $value, int $length = 120): string
    {
        $value = trim((string) $value);

        if (mb_strlen($value) <= $length) {
            return $value;
        }

        return mb_substr($value, 0, $length - 1) . '…';
    }
}

if (!function_exists('array_pluck')) {
    function array_pluck(array $rows, string $column): array
    {
        return array_values(array_map(static fn (array $row) => $row[$column] ?? null, $rows));
    }
}

if (!function_exists('array_key_by')) {
    function array_key_by(array $rows, string $column): array
    {
        $result = [];

        foreach ($rows as $row) {
            if (isset($row[$column])) {
                $result[$row[$column]] = $row;
            }
        }

        return $result;
    }
}
