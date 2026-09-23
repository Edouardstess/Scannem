<?php

declare(strict_types=1);

/**
 * Front controller.
 *
 * This is the only PHP file the web server is meant to execute. Everything
 * else — the application, the configuration, the private storage — sits above
 * this directory and is unreachable over HTTP.
 */

use App\Core\Application;

$app = require dirname(__DIR__) . '/app/bootstrap.php';

/** @var Application $app */
$app->run();
