<?php

declare(strict_types=1);

namespace App\Models;

final class AuditAction
{
    public const LOGIN_SUCCESS     = 'auth.login.success';
    public const LOGIN_FAILED      = 'auth.login.failed';
    public const LOGOUT            = 'auth.logout';
    public const PASSWORD_CHANGED  = 'auth.password.changed';

    public const CLIENT_CREATED    = 'client.created';
    public const CLIENT_UPDATED    = 'client.updated';
    public const CLIENT_DELETED    = 'client.deleted';

    public const EVENT_CREATED     = 'event.created';
    public const EVENT_UPDATED     = 'event.updated';
    public const EVENT_DELETED     = 'event.deleted';

    public const GALLERY_CREATED   = 'gallery.created';
    public const GALLERY_UPDATED   = 'gallery.updated';
    public const GALLERY_DELETED   = 'gallery.deleted';
    public const GALLERY_ARCHIVED  = 'gallery.archived';
    public const GALLERY_VIEWED    = 'gallery.viewed';
    public const GALLERY_UNLOCKED  = 'gallery.unlocked';
    public const GALLERY_UNLOCK_FAILED = 'gallery.unlock.failed';

    public const TOKEN_GENERATED   = 'token.generated';
    public const TOKEN_REVOKED     = 'token.revoked';
    public const TOKEN_REGENERATED = 'token.regenerated';
    public const TOKEN_REJECTED    = 'token.rejected';

    public const PHOTO_UPLOADED    = 'photo.uploaded';
    public const PHOTO_DELETED     = 'photo.deleted';
    public const PHOTO_DOWNLOADED  = 'photo.downloaded';
    public const PHOTO_SELECTED    = 'photo.selected';

    public const ZIP_GENERATED     = 'zip.generated';

    public const PORTFOLIO_UPDATED = 'portfolio.updated';
    public const SETTINGS_UPDATED  = 'settings.updated';

    public static function label(string $action): string
    {
        return match ($action) {
            self::LOGIN_SUCCESS    => 'Connexion réussie',
            self::LOGIN_FAILED     => 'Échec de connexion',
            self::LOGOUT           => 'Déconnexion',
            self::PASSWORD_CHANGED => 'Mot de passe modifié',
            self::CLIENT_CREATED   => 'Client créé',
            self::CLIENT_UPDATED   => 'Client modifié',
            self::CLIENT_DELETED   => 'Client supprimé',
            self::EVENT_CREATED    => 'Événement créé',
            self::EVENT_UPDATED    => 'Événement modifié',
            self::EVENT_DELETED    => 'Événement supprimé',
            self::GALLERY_CREATED  => 'Galerie créée',
            self::GALLERY_UPDATED  => 'Galerie modifiée',
            self::GALLERY_DELETED  => 'Galerie supprimée',
            self::GALLERY_ARCHIVED => 'Galerie archivée',
            self::GALLERY_VIEWED   => 'Galerie consultée',
            self::GALLERY_UNLOCKED => 'Galerie déverrouillée',
            self::GALLERY_UNLOCK_FAILED => 'Mot de passe de galerie refusé',
            self::TOKEN_GENERATED  => 'Lien généré',
            self::TOKEN_REVOKED    => 'Lien révoqué',
            self::TOKEN_REGENERATED => 'Lien régénéré',
            self::TOKEN_REJECTED   => 'Lien refusé',
            self::PHOTO_UPLOADED   => 'Photo importée',
            self::PHOTO_DELETED    => 'Photo supprimée',
            self::PHOTO_DOWNLOADED => 'Photo téléchargée',
            self::PHOTO_SELECTED   => 'Photo sélectionnée',
            self::ZIP_GENERATED    => 'Archive ZIP générée',
            self::PORTFOLIO_UPDATED => 'Portfolio modifié',
            self::SETTINGS_UPDATED => 'Paramètres modifiés',
            default                => $action,
        };
    }
}
