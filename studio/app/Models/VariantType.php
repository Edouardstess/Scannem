<?php

declare(strict_types=1);

namespace App\Models;

/**
 * The derivative renditions of a photograph.
 *
 * JPEG is the baseline every browser and every GD build handles. The WebP
 * companions are generated only when the server can produce them, and served
 * only to a browser that says it accepts the format — so a gallery is never
 * broken by their absence, and is about a third lighter when they exist.
 */
final class VariantType
{
    public const THUMBNAIL      = 'THUMBNAIL';
    public const PREVIEW        = 'PREVIEW';
    public const THUMBNAIL_WEBP = 'THUMBNAIL_WEBP';
    public const PREVIEW_WEBP   = 'PREVIEW_WEBP';

    public const ALL = [self::THUMBNAIL, self::PREVIEW, self::THUMBNAIL_WEBP, self::PREVIEW_WEBP];

    /** The renditions a gallery cannot do without. */
    public const REQUIRED = [self::THUMBNAIL, self::PREVIEW];

    /** The WebP companion of a baseline type, or null when there is none. */
    public static function webpOf(string $type): ?string
    {
        return match ($type) {
            self::THUMBNAIL => self::THUMBNAIL_WEBP,
            self::PREVIEW   => self::PREVIEW_WEBP,
            default         => null,
        };
    }

    public static function isWebp(string $type): bool
    {
        return in_array($type, [self::THUMBNAIL_WEBP, self::PREVIEW_WEBP], true);
    }
}
