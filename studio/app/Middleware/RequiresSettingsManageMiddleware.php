<?php

declare(strict_types=1);

namespace App\Middleware;

final class RequiresSettingsManageMiddleware extends PermissionMiddleware
{
    protected function permission(): string
    {
        return 'settings.manage';
    }
}
