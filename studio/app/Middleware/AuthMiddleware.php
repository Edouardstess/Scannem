<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/** Refuse anything under /admin to a request without a valid admin session. */
final class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, array $parameters): ?Response
    {
        if (Auth::check() && Auth::fingerprintMatches($request)) {
            return null;
        }

        if (Auth::check()) {
            // Fingerprint mismatch: treat the session as stolen, not stale.
            Auth::logout();
        }

        if ($request->isAjax()) {
            return Response::json(['error' => 'Authentification requise.'], 401);
        }

        Session::put('_intended_url', $request->path());
        Session::flash('error', 'Veuillez vous connecter pour accéder à cette page.');

        return Response::redirect(url('admin/login'));
    }
}
