<?php

declare(strict_types=1);

namespace App\Models;

final class GalleryStatus
{
    public const DRAFT    = 'draft';
    public const ACTIVE   = 'active';
    public const DISABLED = 'disabled';
    public const ARCHIVED = 'archived';

    public const ALL = [self::DRAFT, self::ACTIVE, self::DISABLED, self::ARCHIVED];

    public static function label(string $status): string
    {
        return match ($status) {
            self::DRAFT    => 'Brouillon',
            self::ACTIVE   => 'Active',
            self::DISABLED => 'Désactivée',
            self::ARCHIVED => 'Archivée',
            default        => $status,
        };
    }

    /** Only an active gallery may be opened by a client. */
    public static function isReachable(string $status): bool
    {
        return $status === self::ACTIVE;
    }
}
