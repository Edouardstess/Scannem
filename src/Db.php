<?php

declare(strict_types=1);

namespace Scannem;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Acces base de donnees. Le meme SQL doit fonctionner sur SQLite et MySQL,
 * parce qu'on ne sait pas encore ou le systeme sera heberge.
 */
final class Db
{
    private static ?PDO $instance = null;

    public static function connect(Config $config): PDO
    {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        $driver = $config->str('db_driver', 'sqlite');

        try {
            $pdo = match ($driver) {
                'sqlite' => self::connectSqlite($config->str('db_path')),
                'mysql' => self::connectMysql($config),
                default => throw new RuntimeException("Pilote de base inconnu : $driver"),
            };
        } catch (PDOException $e) {
            throw new RuntimeException('Connexion a la base impossible : ' . $e->getMessage(), 0, $e);
        }

        return self::$instance = $pdo;
    }

    public static function connectSqlite(string $path): PDO
    {
        $dir = dirname($path);

        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException("Impossible de creer $dir");
        }

        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        // WAL : lectures concurrentes pendant une ecriture. Sans ca, deux portes
        // qui scannent en meme temps se bloquent mutuellement.
        $pdo->exec('PRAGMA journal_mode = WAL');
        // Attendre au lieu d'echouer immediatement quand la base est verrouillee.
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA synchronous = NORMAL');

        @chmod($path, 0600);

        return $pdo;
    }

    private static function connectMysql(Config $config): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config->str('db_host'),
            $config->int('db_port', 3306),
            $config->str('db_name')
        );

        return new PDO($dsn, $config->str('db_user'), $config->str('db_pass'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    public static function driverOf(PDO $pdo): string
    {
        return (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    /** Remet a zero la connexion memorisee (utilise par les tests). */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * Cree le schema. Idempotent : peut etre relance sans casser une base existante.
     */
    public static function migrate(PDO $pdo): void
    {
        $mysql = self::driverOf($pdo) === 'mysql';

        // Types qui different entre les deux moteurs.
        $pk = $mysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $engine = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';

        $statements = [
            "CREATE TABLE IF NOT EXISTS batches (
                id $pk,
                name VARCHAR(120) NOT NULL,
                event_date VARCHAR(10) NULL,
                quantity INT NOT NULL DEFAULT 0,
                created_at VARCHAR(25) NOT NULL
            )$engine",

            "CREATE TABLE IF NOT EXISTS cards (
                id $pk,
                uid VARCHAR(32) NOT NULL,
                batch_id INT NOT NULL,
                key_id VARCHAR(4) NOT NULL,
                status VARCHAR(10) NOT NULL DEFAULT 'active',
                used_at VARCHAR(25) NULL,
                used_by_device INT NULL,
                holder_label VARCHAR(120) NULL,
                created_at VARCHAR(25) NOT NULL
            )$engine",

            "CREATE TABLE IF NOT EXISTS devices (
                id $pk,
                label VARCHAR(80) NOT NULL,
                token_hash VARCHAR(64) NOT NULL,
                active INT NOT NULL DEFAULT 1,
                last_seen_at VARCHAR(25) NULL,
                created_at VARCHAR(25) NOT NULL
            )$engine",

            "CREATE TABLE IF NOT EXISTS scans (
                id $pk,
                uid VARCHAR(32) NULL,
                device_id INT NULL,
                result VARCHAR(24) NOT NULL,
                server_at VARCHAR(25) NOT NULL,
                client_at VARCHAR(25) NULL,
                ip VARCHAR(45) NULL,
                was_offline INT NOT NULL DEFAULT 0,
                note VARCHAR(255) NULL
            )$engine",

            "CREATE TABLE IF NOT EXISTS enroll_codes (
                id $pk,
                code_hash VARCHAR(64) NOT NULL,
                label VARCHAR(80) NOT NULL,
                expires_at VARCHAR(25) NOT NULL,
                used_at VARCHAR(25) NULL,
                created_at VARCHAR(25) NOT NULL
            )$engine",

            "CREATE TABLE IF NOT EXISTS admins (
                id $pk,
                username VARCHAR(60) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                created_at VARCHAR(25) NOT NULL
            )$engine",

            "CREATE TABLE IF NOT EXISTS rate_limits (
                bucket_key VARCHAR(120) NOT NULL PRIMARY KEY,
                window_start INT NOT NULL,
                counter INT NOT NULL
            )$engine",
        ];

        foreach ($statements as $sql) {
            $pdo->exec($sql);
        }

        // Index. MySQL n'accepte pas "CREATE INDEX IF NOT EXISTS" avant 8.0.29,
        // donc on avale l'erreur "existe deja" plutot que de se fier a la syntaxe.
        $indexes = [
            'CREATE UNIQUE INDEX idx_cards_uid ON cards (uid)',
            'CREATE INDEX idx_cards_batch_status ON cards (batch_id, status)',
            'CREATE INDEX idx_scans_uid ON scans (uid, server_at)',
            'CREATE INDEX idx_scans_server_at ON scans (server_at)',
            'CREATE UNIQUE INDEX idx_admins_username ON admins (username)',
            'CREATE UNIQUE INDEX idx_devices_token ON devices (token_hash)',
            'CREATE UNIQUE INDEX idx_enroll_code ON enroll_codes (code_hash)',
        ];

        foreach ($indexes as $sql) {
            try {
                $pdo->exec($sql);
            } catch (PDOException $e) {
                if (!self::isDuplicateIndex($e)) {
                    throw $e;
                }
            }
        }
    }

    private static function isDuplicateIndex(PDOException $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'already exists')
            || str_contains($message, 'duplicate key name')
            || str_contains($message, 'exists');
    }

    /** Horodatage UTC uniforme dans toute l'application. */
    public static function now(): string
    {
        return gmdate('Y-m-d\TH:i:s\Z');
    }
}
