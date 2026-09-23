<?php

declare(strict_types=1);

use App\Core\Config;

return [
    // 'mysql' in production. 'sqlite' exists so the test suite and a laptop
    // demo can run without a database server; the schema is identical and the
    // migration runner translates the canonical MySQL DDL.
    'driver'   => Config::env('DB_CONNECTION', 'mysql'),
    'host'     => Config::env('DB_HOST', '127.0.0.1'),
    'port'     => Config::envInt('DB_PORT', 3306),
    'database' => Config::env('DB_DATABASE', 'photographer'),
    'username' => Config::env('DB_USERNAME', ''),
    'password' => Config::env('DB_PASSWORD', ''),
    'charset'  => Config::env('DB_CHARSET', 'utf8mb4'),
    'collation'=> Config::env('DB_COLLATION', 'utf8mb4_unicode_ci'),

    // Absolute path used when driver = sqlite.
    'sqlite_path' => Config::env('DB_SQLITE_PATH', dirname(__DIR__) . '/storage/private/database.sqlite'),
];
