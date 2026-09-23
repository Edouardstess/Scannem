<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * An exception that carries an HTTP status, so a controller or service can
 * abort a request without knowing how errors are rendered.
 */
class HttpException extends RuntimeException
{
    public function __construct(
        private int $statusCode,
        string $message = '',
        private array $headers = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }
}
