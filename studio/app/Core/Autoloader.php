<?php

declare(strict_types=1);

namespace App\Core;

/**
 * PSR-4 autoloader without Composer.
 *
 * Shared hosting frequently has no SSH and no Composer. The application must
 * therefore be able to boot from a plain FTP upload, so class loading is done
 * here instead of by vendor/autoload.php. Composer remains supported for the
 * dev tooling, but it is never required at runtime.
 */
final class Autoloader
{
    /** @var array<string, string> namespace prefix => base directory */
    private array $prefixes = [];

    public function addNamespace(string $prefix, string $baseDir): void
    {
        $prefix = trim($prefix, '\\') . '\\';
        $this->prefixes[$prefix] = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    public function register(): void
    {
        spl_autoload_register([$this, 'load']);
    }

    public function load(string $class): void
    {
        foreach ($this->prefixes as $prefix => $baseDir) {
            if (!str_starts_with($class, $prefix)) {
                continue;
            }

            $relative = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

            if (is_file($file)) {
                require $file;

                return;
            }
        }
    }
}
