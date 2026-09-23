<?php

declare(strict_types=1);

/**
 * Shared bootstrap for the web front controller, the CLI tools and the tests.
 *
 * Returns the booted Application. Composer is optional: the PSR-4 autoloader
 * in app/Core/Autoloader.php is what actually loads the application classes,
 * so a plain FTP deployment works.
 */

use App\Core\Application;
use App\Core\Autoloader;

$basePath = dirname(__DIR__);

require_once $basePath . '/app/Core/Autoloader.php';

$autoloader = new Autoloader();
$autoloader->addNamespace('App', $basePath . '/app');
$autoloader->register();

require_once $basePath . '/app/Helpers/helpers.php';

if (is_file($basePath . '/vendor/autoload.php')) {
    require_once $basePath . '/vendor/autoload.php';
}

$app = Application::boot($basePath);
$app->loadRoutes($basePath . '/routes/web.php');

return $app;
