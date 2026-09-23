<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

interface MiddlewareInterface
{
    /**
     * Inspect the request.
     *
     * Return a Response to short-circuit the pipeline (redirect, 403, …), or
     * null to let the request continue to the next middleware or controller.
     *
     * @param array<string, string> $parameters Route parameters.
     */
    public function handle(Request $request, array $parameters): ?Response;
}
