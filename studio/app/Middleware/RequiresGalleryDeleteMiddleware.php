<?php

declare(strict_types=1);

namespace App\Middleware;

final class RequiresGalleryDeleteMiddleware extends PermissionMiddleware
{
    protected function permission(): string
    {
        return 'gallery.delete';
    }
}
