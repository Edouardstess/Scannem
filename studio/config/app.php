<?php

declare(strict_types=1);

use App\Core\Config;

return [
    'name'      => Config::env('APP_NAME', 'Studio'),
    'env'       => Config::env('APP_ENV', 'production'),
    'debug'     => Config::envBool('APP_DEBUG', false),
    'url'       => rtrim((string) Config::env('APP_URL', 'http://localhost:8000'), '/'),
    'key'       => (string) Config::env('APP_KEY', ''),
    'locale'    => Config::env('APP_LOCALE', 'fr'),
    'timezone'  => Config::env('APP_TIMEZONE', 'Europe/Paris'),

    // Trusted proxy handling for client IP resolution behind a load balancer.
    'trust_proxy' => Config::envBool('APP_TRUST_PROXY', false),
];
