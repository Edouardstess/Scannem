<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Roles and the permissions each one grants.
 *
 * Permissions are checked, never roles: controllers ask for
 * `gallery.delete`, so adding a role later changes this file only.
 */
final class Role
{
    public const SUPER_ADMIN  = 'SUPER_ADMIN';
    public const PHOTOGRAPHER = 'PHOTOGRAPHER';
    public const EDITOR       = 'EDITOR';

    public const ALL = [self::SUPER_ADMIN, self::PHOTOGRAPHER, self::EDITOR];

    public const PERMISSIONS = [
        'gallery.create',
        'gallery.update',
        'gallery.delete',
        'gallery.view',
        'gallery.share',
        'photo.upload',
        'photo.delete',
        'portfolio.manage',
        'client.manage',
        'event.manage',
        'message.manage',
        'statistics.view',
        'settings.manage',
        'user.manage',
    ];

    private const MATRIX = [
        self::SUPER_ADMIN => ['*'],
        self::PHOTOGRAPHER => [
            'gallery.create', 'gallery.update', 'gallery.delete', 'gallery.view', 'gallery.share',
            'photo.upload', 'photo.delete',
            'portfolio.manage', 'client.manage', 'event.manage', 'message.manage',
            'statistics.view', 'settings.manage',
        ],
        self::EDITOR => [
            'gallery.view', 'gallery.update', 'gallery.create',
            'photo.upload',
            'portfolio.manage', 'client.manage', 'event.manage', 'message.manage',
            'statistics.view',
        ],
    ];

    /** @return array<int, string> */
    public static function permissionsFor(string $role): array
    {
        $permissions = self::MATRIX[$role] ?? [];

        return $permissions === ['*'] ? self::PERMISSIONS : $permissions;
    }

    public static function grants(string $role, string $permission): bool
    {
        $permissions = self::MATRIX[$role] ?? [];

        return $permissions === ['*'] || in_array($permission, $permissions, true);
    }

    public static function label(string $role): string
    {
        return match ($role) {
            self::SUPER_ADMIN  => 'Super administrateur',
            self::PHOTOGRAPHER => 'Photographe',
            self::EDITOR       => 'Éditeur',
            default            => $role,
        };
    }

    public static function isValid(string $role): bool
    {
        return in_array($role, self::ALL, true);
    }
}
