<?php

declare(strict_types=1);

use App\Core\Config;

return [
    'session_name'     => Config::env('SESSION_NAME', 'studio_session'),
    'session_lifetime' => Config::envInt('SESSION_LIFETIME', 7200),
    'session_secure'   => Config::envBool('SESSION_SECURE', false),
    'session_samesite' => Config::env('SESSION_SAMESITE', 'Lax'),

    // Signed, short-lived media URLs. Never a raw filesystem path.
    'media_token_ttl'  => Config::envInt('MEDIA_TOKEN_TTL', 3600),

    'login_max_attempts'   => Config::envInt('LOGIN_MAX_ATTEMPTS', 5),
    'login_decay_seconds'  => Config::envInt('LOGIN_DECAY_SECONDS', 900),
    'gallery_max_attempts' => Config::envInt('GALLERY_MAX_ATTEMPTS', 10),
    'contact_max_per_hour' => Config::envInt('CONTACT_MAX_PER_HOUR', 5),

    'hsts_enabled' => Config::envBool('HSTS_ENABLED', false),
    'csp_report_only' => Config::envBool('CSP_REPORT_ONLY', false),
];
