<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Buffered HTTP response.
 *
 * Nothing is emitted until send() is called, which keeps headers changeable
 * for the whole request lifecycle and makes responses assertable in tests.
 */
final class Response
{
    private int $status = 200;

    /** @var array<string, string> */
    private array $headers = [];

    private string $body = '';

    /** @var null|callable():void Streamed body, used for file delivery. */
    private $streamer = null;

    public static function make(string $body = '', int $status = 200, array $headers = []): self
    {
        $response = new self();
        $response->body = $body;
        $response->status = $status;

        foreach ($headers as $name => $value) {
            $response->header((string) $name, (string) $value);
        }

        return $response;
    }

    public static function html(string $html, int $status = 200): self
    {
        return self::make($html, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return self::make($encoded === false ? '{}' : $encoded, $status, [
            'Content-Type' => 'application/json; charset=utf-8',
        ]);
    }

    public static function text(string $text, int $status = 200): self
    {
        return self::make($text, $status, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return self::make('', $status, ['Location' => $location]);
    }

    public static function noContent(): self
    {
        return self::make('', 204);
    }

    /** @param callable():void $streamer */
    public static function stream(callable $streamer, int $status = 200, array $headers = []): self
    {
        $response = self::make('', $status, $headers);
        $response->streamer = $streamer;

        return $response;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[strtolower($name)] = $value;

        return $this;
    }

    public function withHeaders(array $headers): self
    {
        foreach ($headers as $name => $value) {
            $this->header((string) $name, (string) $value);
        }

        return $this;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function body(): string
    {
        return $this->body;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);

            foreach ($this->headers as $name => $value) {
                // Header names are developer-controlled; values are sanitised
                // to prevent response splitting if one ever carries user input.
                header($this->normaliseName($name) . ': ' . str_replace(["\r", "\n"], '', $value), true);
            }
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
            return;
        }

        if ($this->streamer !== null) {
            ($this->streamer)();

            return;
        }

        echo $this->body;
    }

    private function normaliseName(string $name): string
    {
        return implode('-', array_map(
            static fn (string $part): string => ucfirst($part),
            explode('-', $name)
        ));
    }
}
