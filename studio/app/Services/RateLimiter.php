<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\RateLimitRepository;

/**
 * Attempt throttling for login, gallery passwords and public forms.
 *
 * Keys are hashed before storage so the table never holds a raw e-mail
 * address or IP alongside a failure count.
 */
final class RateLimiter
{
    public function __construct(private ?RateLimitRepository $repository = null)
    {
        $this->repository = $repository ?? new RateLimitRepository();
    }

    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return $this->repository->attempts($this->normalise($key)) >= $maxAttempts;
    }

    public function hit(string $key, int $decaySeconds): int
    {
        return $this->repository->hit($this->normalise($key), $decaySeconds);
    }

    public function attempts(string $key): int
    {
        return $this->repository->attempts($this->normalise($key));
    }

    public function clear(string $key): void
    {
        $this->repository->clear($this->normalise($key));
    }

    public function availableIn(string $key): int
    {
        return $this->repository->availableIn($this->normalise($key));
    }

    /** "Réessayez dans 3 minutes" for the blocked-form message. */
    public function retryMessage(string $key): string
    {
        $seconds = $this->availableIn($key);

        if ($seconds <= 0) {
            return 'Réessayez dans un instant.';
        }

        if ($seconds < 60) {
            return sprintf('Réessayez dans %d seconde%s.', $seconds, $seconds > 1 ? 's' : '');
        }

        $minutes = (int) ceil($seconds / 60);

        return sprintf('Réessayez dans %d minute%s.', $minutes, $minutes > 1 ? 's' : '');
    }

    private function normalise(string $key): string
    {
        return hash('sha256', $key);
    }

    public function purgeExpired(): int
    {
        return $this->repository->purgeExpired();
    }
}
