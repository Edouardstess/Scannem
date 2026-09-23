<?php

declare(strict_types=1);

use App\Core\Config;

return [
    // 'log' writes the message to storage/logs instead of sending it, which is
    // what a fresh install should do until SMTP credentials exist.
    'driver'       => Config::env('MAIL_DRIVER', 'log'),
    'host'         => Config::env('MAIL_HOST', ''),
    'port'         => Config::envInt('MAIL_PORT', 587),
    'username'     => Config::env('MAIL_USERNAME', ''),
    'password'     => Config::env('MAIL_PASSWORD', ''),
    'encryption'   => Config::env('MAIL_ENCRYPTION', 'tls'),
    'from_address' => Config::env('MAIL_FROM_ADDRESS', 'no-reply@example.com'),
    'from_name'    => Config::env('MAIL_FROM_NAME', 'L\'ENFANT VISUAL'),
    'timeout'      => Config::envInt('MAIL_TIMEOUT', 15),
];
