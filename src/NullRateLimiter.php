<?php

declare(strict_types=1);

namespace Scannem;

/**
 * Quota desactive (rate_limit_driver = none).
 *
 * A n'utiliser que derriere une protection equivalente en amont (pare-feu,
 * passerelle, reverse proxy qui limite deja). Sans quota, une boucle de
 * synchronisation emballee peut marteler le serveur sans frein.
 *
 * La route d'enrolement reste la plus exposee : c'est la seule accessible sans
 * jeton, et elle protege un code a 48 bits.
 */
final class NullRateLimiter implements RateLimiterInterface
{
    public function allow(string $key, int $max): bool
    {
        return true;
    }

    public function counter(string $key): int
    {
        return 0;
    }

    public function retryAfter(): int
    {
        return 0;
    }

    public function purge(): void
    {
    }
}
