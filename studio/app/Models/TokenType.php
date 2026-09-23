<?php

declare(strict_types=1);

namespace App\Models;

/**
 * The two access levels a gallery link can carry.
 *
 * This distinction is the core of the product: a VIEW token can never reach
 * an original file, whatever the client does in the browser.
 */
final class TokenType
{
    public const VIEW     = 'VIEW';
    public const DOWNLOAD = 'DOWNLOAD';

    public const ALL = [self::VIEW, self::DOWNLOAD];

    public static function label(string $type): string
    {
        return match ($type) {
            self::VIEW     => 'Consultation',
            self::DOWNLOAD => 'Téléchargement',
            default        => $type,
        };
    }

    public static function allowsDownload(string $type): bool
    {
        return $type === self::DOWNLOAD;
    }
}
