<?php

declare(strict_types=1);

namespace Scannem;

/**
 * Quota anti-abus.
 *
 * A remettre a sa place : ce quota ne protege pas contre la fraude. Avec 80 bits
 * de signature, deviner un code valide est hors de portee. Il sert a couper le
 * scan de masse, les boucles de synchronisation emballees et l'usure inutile de
 * la base quand un appareil part en vrille.
 */
interface RateLimiterInterface
{
    /** Enregistre une tentative et indique si elle passe. */
    public function allow(string $key, int $max): bool;

    public function counter(string $key): int;

    /** Secondes restantes avant remise a zero du compteur. */
    public function retryAfter(): int;

    /** Nettoie les fenetres perimees. */
    public function purge(): void;
}
