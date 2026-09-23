<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;

/**
 * Emit security headers for every response.
 *
 * Headers are sent here rather than from Response::send() so that a route
 * that streams a file gets them too.
 */
final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, array $parameters): ?Response
    {
        if (headers_sent()) {
            return null;
        }

        foreach (self::headers($request) as $name => $value) {
            header($name . ': ' . $value, true);
        }

        // PHP advertises its version by default; that is free reconnaissance.
        if (\App\Core\Environment::functionAvailable('header_remove')) {
            header_remove('X-Powered-By');
        }

        return null;
    }

    /** @return array<string, string> */
    public static function headers(Request $request): array
    {
        $headers = [
            'X-Frame-Options'        => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy'        => 'strict-origin-when-cross-origin',
            'Permissions-Policy'     => 'camera=(), microphone=(), geolocation=(), interest-cohort=()',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ];

        $cspHeader = Config::get('security.csp_report_only')
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';

        $headers[$cspHeader] = self::contentSecurityPolicy();

        // HSTS is opt-in: sending it over a half-configured HTTPS setup locks
        // visitors out of a site that cannot yet serve them.
        if (Config::get('security.hsts_enabled') && $request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        return $headers;
    }

    private static function contentSecurityPolicy(): string
    {
        return implode('; ', [
            "default-src 'self'",
            // Google Fonts is the only third party the front end uses.
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com data:",
            "script-src 'self'",
            "img-src 'self' data: blob:",
            "connect-src 'self'",
            "frame-ancestors 'self'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
        ]);
    }
}
