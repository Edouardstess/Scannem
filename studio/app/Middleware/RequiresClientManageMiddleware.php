<?php

declare(strict_types=1);

namespace App\Middleware;

final class RequiresClientManageMiddleware extends PermissionMiddleware
{
    protected function permission(): string
    {
        return 'client.manage';
    }
}
