<?php

declare(strict_types=1);

namespace App\Middleware;

final class RequiresGalleryUpdateMiddleware extends PermissionMiddleware
{
    protected function permission(): string
    {
        return 'gallery.update';
    }
}
