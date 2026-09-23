<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

/**
 * Base class for per-permission route guards.
 *
 * Routes reference a concrete subclass because the router instantiates
 * middleware by class name; each subclass names one permission.
 */
abstract class PermissionMiddleware implements MiddlewareInterface
{
    abstract protected function permission(): string;

    public function handle(Request $request, array $parameters): ?Response
    {
        if (Auth::can($this->permission())) {
            return null;
        }

        if ($request->isAjax()) {
            return Response::json(['error' => 'Permission refusée.'], 403);
        }

        return Response::html(
            view('errors.403', ['message' => "Votre rôle ne permet pas cette action."]),
            403
        );
    }
}
