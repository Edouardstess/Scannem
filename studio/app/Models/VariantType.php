<?php

declare(strict_types=1);

namespace App\Models;

final class VariantType
{
    public const THUMBNAIL = 'THUMBNAIL';
    public const PREVIEW   = 'PREVIEW';

    public const ALL = [self::THUMBNAIL, self::PREVIEW];
}
