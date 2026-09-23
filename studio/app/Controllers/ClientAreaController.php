<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\RateLimiter;
use App\Services\TokenService;

/**
 * "Espace client": a form where a client can paste the code from their link.
 *
 * Convenience only — it resolves a token to its gallery URL. It cannot list
 * galleries, and a wrong code reveals nothing, because the answer for an
 * unknown code and for a revoked one is identical.
 */
final class ClientAreaController extends Controller
{
    public function __construct(
        private TokenService $tokens = new TokenService(),
        private RateLimiter $limiter = new RateLimiter()
    ) {
    }

    /** GET /espace-client */
    public function show(Request $request): Response
    {
        return $this->view('public.client_area', ['title' => 'Espace client']);
    }

    /** POST /espace-client */
    public function open(Request $request): Response
    {
        $limiterKey = 'client-area|' . $request->ip();

        // Without this, the form is an oracle an attacker can hammer to
        // discover live tokens. 20 tries an hour makes 2^256 unreachable in
        // every sense that matters.
        if ($this->limiter->tooManyAttempts($limiterKey, 20)) {
            return $this->redirectWithErrors($request, [
                'code' => 'Trop de tentatives. ' . $this->limiter->retryMessage($limiterKey),
            ], 'espace-client');
        }

        $code = trim((string) $request->input('code', ''));

        // Visitors paste whole URLs as often as they paste codes, so the
        // last path segment is taken when one is present.
        if (str_contains($code, '/')) {
            $segments = explode('/', rtrim(strtok($code, '?') ?: $code, '/'));
            $code = (string) end($segments);
        }

        $this->limiter->hit($limiterKey, 3600);

        $verification = $this->tokens->verify($code);

        if ($verification['status'] !== TokenService::RESULT_VALID || $verification['token'] === null) {
            return $this->redirectWithErrors($request, [
                'code' => 'Ce code ne correspond à aucune galerie active.',
            ], 'espace-client');
        }

        $this->limiter->clear($limiterKey);

        $type = (string) $verification['token']['token_type'];
        $prefix = $type === \App\Models\TokenType::DOWNLOAD ? 'download' : 'gallery';

        return $this->redirect($prefix . '/' . $code);
    }
}
