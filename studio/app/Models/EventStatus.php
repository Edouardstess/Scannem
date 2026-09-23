<?php

declare(strict_types=1);

namespace App\Models;

final class EventStatus
{
    public const DRAFT     = 'draft';
    public const ACTIVE    = 'active';
    public const COMPLETED = 'completed';
    public const ARCHIVED  = 'archived';

    public const ALL = [self::DRAFT, self::ACTIVE, self::COMPLETED, self::ARCHIVED];

    public static function label(string $status): string
    {
        return match ($status) {
            self::DRAFT     => 'Brouillon',
            self::ACTIVE    => 'En cours',
            self::COMPLETED => 'Terminé',
            self::ARCHIVED  => 'Archivé',
            default         => $status,
        };
    }
}
