<?php

declare(strict_types=1);

/**
 * Test runner.
 *
 *   php tests/run.php              Run everything
 *   php tests/run.php TokenTest    Run one class
 *
 * No Composer, no PHPUnit: the application has no runtime dependencies and
 * neither does its test suite, so this runs anywhere the application runs.
 */

use Tests\Support\TestCase;
use Tests\Support\TestEnvironment;

if (PHP_SAPI !== 'cli') {
    exit("CLI only.\n");
}

require __DIR__ . '/Support/TestEnvironment.php';
require __DIR__ . '/Support/TestCase.php';

TestEnvironment::boot();

$filter = $argv[1] ?? null;
$files = [];

foreach (['Unit', 'Feature'] as $directory) {
    foreach (glob(__DIR__ . '/' . $directory . '/*Test.php') ?: [] as $file) {
        $files[] = $file;
    }
}

sort($files);

$totalTests = 0;
$totalAssertions = 0;
$failures = [];
$start = microtime(true);

foreach ($files as $file) {
    require_once $file;

    $className = 'Tests\\' . basename(dirname($file)) . '\\' . basename($file, '.php');

    if (!class_exists($className)) {
        fwrite(STDERR, "Class not found for " . $file . "\n");
        continue;
    }

    if ($filter !== null && !str_contains($className, $filter)) {
        continue;
    }

    $reflection = new ReflectionClass($className);
    $methods = array_filter(
        $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
        static fn (ReflectionMethod $method): bool => str_starts_with($method->getName(), 'test')
    );

    if ($methods === []) {
        continue;
    }

    echo "\n\033[1m" . $reflection->getShortName() . "\033[0m\n";

    foreach ($methods as $method) {
        // A fresh database per test: a test that depends on another test's
        // rows is a test that lies the day someone reorders the file.
        TestEnvironment::resetDatabase();

        /** @var TestCase $instance */
        $instance = new $className();
        $instance->resetResults();
        $totalTests++;

        $error = null;

        try {
            $instance->setUp();
            $instance->{$method->getName()}();
        } catch (Throwable $e) {
            $error = $e;
        } finally {
            try {
                $instance->tearDown();
            } catch (Throwable) {
                // A failing teardown must not mask the real failure.
            }
        }

        $totalAssertions += $instance->assertionCount();
        $methodFailures = $instance->failures();

        if ($error !== null) {
            $methodFailures[] = sprintf(
                '%s: %s (%s:%d)',
                $error::class,
                $error->getMessage(),
                basename($error->getFile()),
                $error->getLine()
            );
        }

        $label = preg_replace('/^test/', '', $method->getName()) ?? $method->getName();
        $label = trim((string) preg_replace('/(?<!^)[A-Z]/', ' $0', $label));

        if ($methodFailures === []) {
            echo "  \033[32m✓\033[0m " . $label . "\n";

            continue;
        }

        echo "  \033[31m✗\033[0m " . $label . "\n";

        foreach ($methodFailures as $failure) {
            echo "      \033[31m" . $failure . "\033[0m\n";
            $failures[] = $reflection->getShortName() . '::' . $method->getName() . ' — ' . $failure;
        }
    }
}

TestEnvironment::tearDown();

$duration = round(microtime(true) - $start, 2);

echo "\n";
echo str_repeat('─', 60) . "\n";

if ($failures === []) {
    printf(
        "\033[32mOK\033[0m  %d tests, %d assertions, %ss\n",
        $totalTests,
        $totalAssertions,
        $duration
    );

    exit(0);
}

printf(
    "\033[31mÉCHEC\033[0m  %d tests, %d assertions, %d échec(s), %ss\n",
    $totalTests,
    $totalAssertions,
    count($failures),
    $duration
);

exit(1);
