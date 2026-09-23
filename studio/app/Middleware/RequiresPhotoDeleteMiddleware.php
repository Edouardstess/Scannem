<?php

declare(strict_types=1);

namespace App\Middleware;

final class RequiresPhotoDeleteMiddleware extends PermissionMiddleware
{
    protected function permission(): string
    {
        return 'photo.delete';
    }
}
