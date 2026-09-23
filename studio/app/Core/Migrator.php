<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;

/**
 * Migration runner.
 *
 * The .sql files in database/migrations are canonical MySQL. Production runs
 * them verbatim. When the connection is SQLite — the test suite, and a laptop
 * demo without a database server — the DDL is translated on the fly, so there
 * is exactly one definition of the schema instead of two that drift apart.
 */
final class Migrator
{
    public function __construct(private string $directory)
    {
    }

    /** @return array<int, string> Names of the migrations that ran. */
    public function migrate(bool $verbose = false): array
    {
        $this->ensureMigrationsTable();
        $applied = $this->appliedMigrations();
        $ran = [];

        foreach ($this->pendingFiles($applied) as $file) {
            $name = basename($file);
            $sql = (string) file_get_contents($file);

            foreach ($this->statements($sql) as $statement) {
                try {
                    Database::connection()->exec($statement);
                } catch (\PDOException $e) {
                    throw new RuntimeException(
                        sprintf("Migration %s failed: %s\nStatement: %s", $name, $e->getMessage(), $statement),
                        0,
                        $e
                    );
                }
            }

            $this->markApplied($name);
            $ran[] = $name;

            if ($verbose) {
                fwrite(STDOUT, '  migrated  ' . $name . PHP_EOL);
            }
        }

        return $ran;
    }

    /** @return array<int, string> */
    public function pending(): array
    {
        $this->ensureMigrationsTable();

        return array_map('basename', $this->pendingFiles($this->appliedMigrations()));
    }

    /** @param array<int, string> $applied @return array<int, string> */
    private function pendingFiles(array $applied): array
    {
        $files = glob($this->directory . '/*.sql') ?: [];
        sort($files, SORT_STRING);

        return array_values(array_filter(
            $files,
            static fn (string $file): bool => !in_array(basename($file), $applied, true)
        ));
    }

    private function ensureMigrationsTable(): void
    {
        $sql = Database::driver() === 'sqlite'
            ? 'CREATE TABLE IF NOT EXISTS migrations (
                   id INTEGER PRIMARY KEY AUTOINCREMENT,
                   migration VARCHAR(190) NOT NULL UNIQUE,
                   applied_at DATETIME NOT NULL
               )'
            : 'CREATE TABLE IF NOT EXISTS migrations (
                   id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                   migration VARCHAR(190) NOT NULL,
                   applied_at DATETIME NOT NULL,
                   UNIQUE KEY uq_migrations_migration (migration)
               ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';

        Database::connection()->exec($sql);
    }

    /** @return array<int, string> */
    private function appliedMigrations(): array
    {
        $statement = Database::connection()->query('SELECT migration FROM migrations ORDER BY id');

        return $statement === false ? [] : array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    private function markApplied(string $name): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO migrations (migration, applied_at) VALUES (:migration, :applied_at)'
        );
        $statement->execute(['migration' => $name, 'applied_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Split a migration file into executable statements.
     *
     * @return array<int, string>
     */
    public function statements(string $sql): array
    {
        $sql = $this->stripComments($sql);
        $statements = [];

        foreach (explode(';', $sql) as $chunk) {
            $chunk = trim($chunk);

            if ($chunk === '') {
                continue;
            }

            $statements[] = Database::driver() === 'sqlite' ? self::toSqlite($chunk) : $chunk;
        }

        return $statements;
    }

    private function stripComments(string $sql): string
    {
        $lines = [];

        foreach (explode("\n", $sql) as $line) {
            if (str_starts_with(trim($line), '--')) {
                continue;
            }

            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    /**
     * Translate a MySQL DDL statement to its SQLite equivalent.
     *
     * The migrations deliberately stick to a conservative subset (no inline
     * KEY clauses, no ENUM, separate CREATE INDEX statements), which keeps
     * this translation small enough to trust.
     */
    public static function toSqlite(string $statement): string
    {
        $statement = str_replace('`', '', $statement);

        // The rowid alias SQLite needs for AUTOINCREMENT must be exactly INTEGER.
        $statement = (string) preg_replace(
            '/\bINT\s+UNSIGNED\s+NOT\s+NULL\s+AUTO_INCREMENT\s+PRIMARY\s+KEY\b/i',
            'INTEGER PRIMARY KEY AUTOINCREMENT',
            $statement
        );

        // Table options have no SQLite equivalent and are simply dropped.
        $statement = (string) preg_replace(
            '/\s*ENGINE\s*=\s*\w+(\s+DEFAULT\s+CHARSET\s*=\s*\w+)?(\s+COLLATE\s*=\s*\w+)?/i',
            '',
            $statement
        );

        $statement = (string) preg_replace('/\bTINYINT\s*\(\s*\d+\s*\)/i', 'INTEGER', $statement);
        $statement = (string) preg_replace('/\bBIGINT\s+UNSIGNED\b/i', 'INTEGER', $statement);
        $statement = (string) preg_replace('/\bINT\s+UNSIGNED\b/i', 'INTEGER', $statement);
        $statement = (string) preg_replace('/\bUNSIGNED\b/i', '', $statement);
        $statement = (string) preg_replace('/\bDECIMAL\s*\(\s*\d+\s*,\s*\d+\s*\)/i', 'NUMERIC', $statement);
        $statement = (string) preg_replace('/\bDATETIME\b/i', 'TEXT', $statement);
        $statement = (string) preg_replace('/\bDATE\b(?!TIME)/i', 'TEXT', $statement);

        return trim((string) preg_replace('/[ \t]+/', ' ', $statement));
    }
}
