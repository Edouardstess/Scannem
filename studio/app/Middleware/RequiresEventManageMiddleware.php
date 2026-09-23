<?php

declare(strict_types=1);

namespace App\Middleware;

final class RequiresEventManageMiddleware extends PermissionMiddleware
{
    protected function permission(): string
    {
        return 'event.manage';
    }
}
