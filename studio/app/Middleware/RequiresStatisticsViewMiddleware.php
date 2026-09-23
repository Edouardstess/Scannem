<?php

declare(strict_types=1);

namespace App\Middleware;

final class RequiresStatisticsViewMiddleware extends PermissionMiddleware
{
    protected function permission(): string
    {
        return 'statistics.view';
    }
}
