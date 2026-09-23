<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use App\Exceptions\DatabaseUnavailableException;
use PDOException;

/**
 * Single PDO connection, lazily opened.
 *
 * Every query in the application goes through prepared statements; this class
 * exposes no method that concatenates caller input into SQL.
 */
final class Database
{
    private static ?PDO $connection = null;

    private function __construct()
    {
    }

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $driver = (string) Config::get('database.driver', 'mysql');

        try {
            self::$connection = $driver === 'sqlite'
                ? self::connectSqlite()
                : self::connectMysql();
        } catch (PDOException $e) {
            // The message can carry credentials; never surface it to the browser.
            Logger::error('Database connection failed', ['driver' => $driver, 'code' => $e->getCode()]);

            throw new DatabaseUnavailableException('Database connection failed.', 0, $e);
        }

        return self::$connection;
    }

    private static function connectMysql(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            (string) Config::get('database.host'),
            (int) Config::get('database.port'),
            (string) Config::get('database.database'),
            (string) Config::get('database.charset')
        );

        $pdo = new PDO(
            $dsn,
            (string) Config::get('database.username'),
            (string) Config::get('database.password'),
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Real prepared statements, not client-side interpolation.
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]
        );

        $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");

        return $pdo;
    }

    private static function connectSqlite(): PDO
    {
        $path = (string) Config::get('database.sqlite_path');

        if ($path !== ':memory:') {
            $dir = dirname($path);

            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
        }

        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');

        return $pdo;
    }

    public static function driver(): string
    {
        return (string) self::connection()->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    /**
     * Give every repeated named placeholder its own name.
     *
     * With native prepares (emulation is off) MySQL rejects a named
     * placeholder used twice in one statement ("Invalid parameter number"),
     * while SQLite accepts it. Rewriting `:search ... :search` into
     * `:search ... :search__2` lets the same SQL run on both drivers.
     * Quoted literals and `::` casts are left untouched.
     *
     * @param  array<int|string, mixed> $bindings
     * @return array{0: string, 1: array<int|string, mixed>}
     */
    public static function expandPlaceholders(string $sql, array $bindings): array
    {
        if (!str_contains($sql, ':')) {
            return [$sql, $bindings];
        }

        $parts = preg_split(
            '/(\'(?:[^\'\\\\]|\\\\.|\'\')*\'|"(?:[^"\\\\]|\\\\.)*"|`[^`]*`)/s',
            $sql,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );

        if ($parts === false) {
            return [$sql, $bindings];
        }

        $seen = [];

        foreach ($parts as $index => $part) {
            // Odd indexes are the captured quoted literals.
            if ($index % 2 === 1) {
                continue;
            }

            $parts[$index] = (string) preg_replace_callback(
                '/(?<![:\w]):([A-Za-z_]\w*)/',
                static function (array $match) use (&$seen, &$bindings): string {
                    $name = $match[1];
                    $seen[$name] = ($seen[$name] ?? 0) + 1;

                    if ($seen[$name] === 1) {
                        return $match[0];
                    }

                    $alias = $name . '__' . $seen[$name];

                    foreach ([$name, ':' . $name] as $key) {
                        if (array_key_exists($key, $bindings)) {
                            $bindings[$alias] = $bindings[$key];
                            break;
                        }
                    }

                    return ':' . $alias;
                },
                $part
            );
        }

        return [implode('', $parts), $bindings];
    }

    /** Used by the test bootstrap to swap in an isolated connection. */
    public static function swap(?PDO $pdo): void
    {
        self::$connection = $pdo;
    }

    public static function beginTransaction(): void
    {
        self::connection()->beginTransaction();
    }

    public static function commit(): void
    {
        self::connection()->commit();
    }

    public static function rollBack(): void
    {
        if (self::connection()->inTransaction()) {
            self::connection()->rollBack();
        }
    }

    /**
     * Run a callback inside a transaction, rolling back on any exception.
     *
     * @template T
     * @param  callable():T $callback
     * @return T
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();
        $nested = $pdo->inTransaction();

        if (!$nested) {
            $pdo->beginTransaction();
        }

        try {
            $result = $callback();

            if (!$nested) {
                $pdo->commit();
            }

            return $result;
        } catch (\Throwable $e) {
            if (!$nested && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }
}
