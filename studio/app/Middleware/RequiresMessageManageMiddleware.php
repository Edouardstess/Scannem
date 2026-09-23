<?php

declare(strict_types=1);

namespace App\Middleware;

final class RequiresMessageManageMiddleware extends PermissionMiddleware
{
    protected function permission(): string
    {
        return 'message.manage';
    }
}
