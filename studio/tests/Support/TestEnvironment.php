<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Config;
use App\Core\Database;
use App\Core\Migrator;
use App\Core\Session;
use App\Services\SettingsService;

/**
 * Isolated environment for a test run.
 *
 * Each run gets a throwaway SQLite database and a throwaway storage tree, so
 * the suite never touches a real installation and leaves nothing behind.
 */
final class TestEnvironment
{
    private static ?string $storageRoot = null;

    public static function boot(): void
    {
        $basePath = dirname(__DIR__, 2);

        require_once $basePath . '/app/Core/Autoloader.php';

        $autoloader = new \App\Core\Autoloader();
        $autoloader->addNamespace('App', $basePath . '/app');
        $autoloader->addNamespace('Tests', $basePath . '/tests');
        $autoloader->register();

        require_once $basePath . '/app/Helpers/helpers.php';

        Config::load($basePath . '/config');

        self::$storageRoot = sys_get_temp_dir() . '/studio-tests-' . bin2hex(random_bytes(6));

        Config::set('app.env', 'testing');
        Config::set('app.debug', false);
        Config::set('app.url', 'https://tests.example');
        Config::set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        Config::set('app.timezone', 'UTC');

        Config::set('database.driver', 'sqlite');
        Config::set('database.sqlite_path', self::$storageRoot . '/database.sqlite');

        Config::set('storage.root', self::$storageRoot);
        Config::set('storage.originals', self::$storageRoot . '/originals');
        Config::set('storage.previews', self::$storageRoot . '/previews');
        Config::set('storage.thumbnails', self::$storageRoot . '/thumbnails');
        Config::set('storage.temporary', self::$storageRoot . '/temporary');
        Config::set('storage.logs', self::$storageRoot . '/logs');

        date_default_timezone_set('UTC');
        mkdir(self::$storageRoot, 0775, true);

        \App\Core\View::setBasePath($basePath . '/app/Views');

        Database::swap(null);
        (new Migrator($basePath . '/database/migrations'))->migrate();

        Session::start();
    }

    /** Wipe every table between test classes, so order never matters. */
    public static function resetDatabase(): void
    {
        $pdo = Database::connection();
        $tables = $pdo->query(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' AND name <> 'migrations'"
        )->fetchAll(\PDO::FETCH_COLUMN);

        $pdo->exec('PRAGMA foreign_keys = OFF');

        foreach ($tables as $table) {
            $pdo->exec('DELETE FROM ' . $table);
        }

        $pdo->exec("DELETE FROM sqlite_sequence");
        $pdo->exec('PRAGMA foreign_keys = ON');

        $_SESSION = [];
        SettingsService::flush();
        \App\Core\Auth::forgetCache();
    }

    public static function storageRoot(): string
    {
        return (string) self::$storageRoot;
    }

    public static function tearDown(): void
    {
        Database::swap(null);

        if (self::$storageRoot !== null && is_dir(self::$storageRoot)) {
            self::removeDirectory(self::$storageRoot);
        }
    }

    private static function removeDirectory(string $directory): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            /** @var \SplFileInfo $item */
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($directory);
    }
}
