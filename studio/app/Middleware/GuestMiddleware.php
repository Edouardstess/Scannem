<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

/** Keep an already-authenticated admin off the login page. */
final class GuestMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, array $parameters): ?Response
    {
        return Auth::check() ? Response::redirect(url('admin')) : null;
    }
}
