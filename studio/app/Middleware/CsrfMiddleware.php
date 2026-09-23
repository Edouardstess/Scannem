<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Reject state-changing requests without a valid CSRF token.
 *
 * Applied globally rather than per route: a new POST route added later is
 * protected by default, which is the only way this stays true over time.
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function handle(Request $request, array $parameters): ?Response
    {
        if (in_array($request->realMethod(), self::SAFE_METHODS, true)) {
            return null;
        }

        if (Csrf::validate(Csrf::fromRequest($request))) {
            return null;
        }

        Logger::warning('CSRF token rejected', [
            'path'   => $request->path(),
            'method' => $request->method(),
            'ip'     => $request->ip(),
        ]);

        if ($request->isAjax()) {
            return Response::json([
                'error' => 'Session expirée. Rechargez la page et réessayez.',
            ], 419);
        }

        Session::flash('error', 'Session expirée. Rechargez la page et réessayez.');

        // Back to the page holding the form: the POST URL itself usually has
        // no GET route and would land the visitor on an error page.
        return Response::redirect($request->sameSiteReferer() ?? url('/'), 303);
    }
}
