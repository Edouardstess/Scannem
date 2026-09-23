<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Immutable-ish view over the current HTTP request.
 *
 * Superglobals are read once here so that controllers, services and the test
 * suite all see the same request object.
 */
final class Request
{
    private static ?self $current = null;

    private string $basePath = '';

    /** @param array<string, mixed> $query
     *  @param array<string, mixed> $body
     *  @param array<string, mixed> $server
     *  @param array<string, mixed> $files
     *  @param array<string, string> $cookies */
    public function __construct(
        private string $method,
        private string $path,
        private array $query,
        private array $body,
        private array $server,
        private array $files,
        private array $cookies,
        private string $rawBody = ''
    ) {
    }

    public static function capture(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';

        $basePath = self::detectBasePath($path, (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));

        if ($basePath !== '') {
            $path = substr($path, strlen($basePath));
        }

        $path = '/' . trim((string) $path, '/');

        $raw = '';
        $body = $_POST;

        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');

            if (str_contains($contentType, 'application/json')) {
                $raw = (string) file_get_contents('php://input');
                $decoded = json_decode($raw, true);

                if (is_array($decoded)) {
                    $body = $decoded;
                }
            }
        }

        $request = new self($method, $path, $_GET, $body, $_SERVER, $_FILES, $_COOKIE, $raw);
        $request->basePath = $basePath;
        self::$current = $request;

        return $request;
    }

    /**
     * The prefix the application is mounted under, '' at the document root.
     *
     * Three layouts have to work:
     *
     *   1. Document root points at public/ — SCRIPT_NAME is /index.php and
     *      there is no prefix. This is the correct production setup.
     *   2. Document root points at the project, and the root .htaccess
     *      rewrites into public/ — SCRIPT_NAME is /studio/public/index.php
     *      while the browser asked for /studio/. The prefix is /studio.
     *   3. The visitor reaches public/ directly — SCRIPT_NAME is
     *      /studio/public/index.php and so is the URL. The prefix is
     *      /studio/public.
     *
     * Candidates are tried longest first, because /studio/public is a valid
     * prefix only when the URL really contains it.
     */
    public static function detectBasePath(string $path, string $scriptName): string
    {
        $directory = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

        if ($directory === '' || $directory === '.') {
            return '';
        }

        $candidates = [$directory];

        // Case 2: the rewrite added /public that the browser never sent.
        if (basename($directory) === 'public') {
            $parent = rtrim(dirname($directory), '/');
            $candidates[] = $parent === '.' ? '' : $parent;
        }

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                return '';
            }

            if ($path === $candidate || str_starts_with($path, $candidate . '/')) {
                return $candidate;
            }
        }

        return '';
    }

    /** The most recently captured request, for URL generation in templates. */
    public static function current(): ?self
    {
        return self::$current;
    }

    /** Only the test suite needs to clear this. */
    public static function forgetCurrent(): void
    {
        self::$current = null;
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function host(): string
    {
        $host = (string) ($this->server['HTTP_HOST'] ?? $this->server['SERVER_NAME'] ?? '');

        // Host headers are attacker-controlled; strip anything that is not a
        // hostname or port before it can end up inside a generated URL.
        $host = strtolower(trim($host));

        return preg_match('/^[a-z0-9._-]+(:\d+)?$/', $host) === 1 ? $host : 'localhost';
    }

    /**
     * Where this installation actually lives, as seen by the browser.
     *
     * Used to build links and asset URLs, so the site works wherever it is
     * dropped — including a subdirectory — before anyone has configured
     * APP_URL.
     */
    public function baseUrl(): string
    {
        return ($this->isSecure() ? 'https://' : 'http://') . $this->host() . $this->basePath;
    }

    /**
     * Effective HTTP verb.
     *
     * HTML forms can only emit GET and POST, so a POST carrying `_method`
     * stands in for PUT/PATCH/DELETE. Only POST may be overridden: allowing a
     * GET to be turned into a DELETE would make destructive actions reachable
     * from a link or a prefetch.
     */
    public function method(): string
    {
        if ($this->method === 'POST') {
            $override = strtoupper((string) ($this->body['_method'] ?? ''));

            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $override;
            }
        }

        return $this->method;
    }

    public function realMethod(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function queryAll(): array
    {
        return $this->query;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->input($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function bool(string $key): bool
    {
        $value = $this->input($key, false);

        return in_array(is_string($value) ? strtolower($value) : $value, [true, 1, '1', 'on', 'yes', 'true'], true);
    }

    /** @return array<int, mixed> */
    public function array(string $key): array
    {
        $value = $this->input($key, []);

        return is_array($value) ? array_values($value) : [];
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->body + $this->query;
    }

    /** @return array<string, mixed> */
    public function only(string ...$keys): array
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->input($key);
        }

        return $result;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->body) || array_key_exists($key, $this->query);
    }

    /** @return array<string, mixed> */
    public function files(): array
    {
        return $this->files;
    }

    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;

        return is_array($file) ? $file : null;
    }

    public function cookie(string $key, ?string $default = null): ?string
    {
        $value = $this->cookies[$key] ?? $default;

        return is_string($value) ? $value : $default;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        $value = $this->server[$key] ?? match (strtolower($name)) {
            'content-type'   => $this->server['CONTENT_TYPE'] ?? null,
            'content-length' => $this->server['CONTENT_LENGTH'] ?? null,
            default          => null,
        };

        return is_string($value) ? $value : null;
    }

    public function isAjax(): bool
    {
        return strtolower((string) $this->header('X-Requested-With')) === 'xmlhttprequest'
            || str_contains((string) $this->header('Accept'), 'application/json');
    }

    /** True when PHP discarded the body for exceeding post_max_size. */
    public function bodyWasDropped(): bool
    {
        return Environment::postBodyWasDropped(
            ['REQUEST_METHOD' => $this->method] + $this->server,
            $this->body,
            $this->files
        );
    }

    public function isSecure(): bool
    {
        if (($this->server['HTTPS'] ?? 'off') !== 'off' && ($this->server['HTTPS'] ?? '') !== '') {
            return true;
        }

        if ((int) ($this->server['SERVER_PORT'] ?? 80) === 443) {
            return true;
        }

        return Config::get('app.trust_proxy')
            && strtolower((string) ($this->server['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    /**
     * Best-effort client IP.
     *
     * X-Forwarded-For is honoured only when the deployment declares that it
     * sits behind a trusted proxy; otherwise any visitor could spoof the
     * address used by rate limiting and audit logs.
     */
    public function ip(): string
    {
        if (Config::get('app.trust_proxy')) {
            $forwarded = (string) ($this->server['HTTP_X_FORWARDED_FOR'] ?? '');

            if ($forwarded !== '') {
                $first = trim(explode(',', $forwarded)[0]);

                if (filter_var($first, FILTER_VALIDATE_IP) !== false) {
                    return $first;
                }
            }
        }

        $ip = (string) ($this->server['REMOTE_ADDR'] ?? '');

        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : '0.0.0.0';
    }

    public function userAgent(): string
    {
        return substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public function referer(): string
    {
        return (string) ($this->server['HTTP_REFERER'] ?? '');
    }

    /**
     * The referring URL when it points back at this site, null otherwise.
     *
     * Compared with the host actually serving the request (APP_URL may be
     * stale or absent), so redirecting to it can never become an open
     * redirect towards another domain.
     */
    public function sameSiteReferer(): ?string
    {
        $referer = $this->referer();

        if ($referer === '' || preg_match('#^https?://#i', $referer) !== 1) {
            return null;
        }

        $host = strtolower((string) parse_url($referer, PHP_URL_HOST));
        $port = parse_url($referer, PHP_URL_PORT);
        $origin = $host . ($port !== null ? ':' . $port : '');

        return $origin === strtolower($this->host()) ? $referer : null;
    }

    public function rawBody(): string
    {
        return $this->rawBody;
    }
}
