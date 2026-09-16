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

    // ------------------------------------------------- Transactions et verrous

    /**
     * Execute une operation dans une transaction, en tolerant l'imbrication.
     *
     * Si une transaction est deja ouverte (createBatch, ou une synchronisation
     * qui enveloppe plusieurs redeem), on se contente d'executer : ni PDO ni
     * SQLite ne gerent les transactions reellement imbriquees, et ouvrir une
     * seconde transaction lancerait une exception.
     *
     * @template T
     * @param callable():T $operation
     * @return T
     */
    public static function transaction(PDO $pdo, callable $operation): mixed
    {
        if ($pdo->inTransaction()) {
            return $operation();
        }

        $sqlite = self::driverOf($pdo) === 'sqlite';

        if ($sqlite) {
            // BEGIN IMMEDIATE, et surtout pas le BEGIN par defaut de
            // PDO::beginTransaction().
            //
            // Le BEGIN par defaut est « deferred » : la transaction demarre en
            // lecture et doit monter en ecriture au premier UPDATE. Quand un
            // autre processus tient deja le verrou, cette montee echoue sans
            // respecter busy_timeout et retombe sur le gestionnaire d'attente de
            // SQLite, dont les paliers de sommeil vont jusqu'a 25 ms. A vingt
            // portes qui scannent ensemble, le debit mesure s'effondrait d'un
            // facteur dix (environ 1300 scans/s -> 120).
            //
            // IMMEDIATE prend le verrou d'ecriture des le depart : plus de
            // montee a mi-parcours, et l'attente redevient celle, fine, de
            // busy_timeout.
            $pdo->exec('BEGIN IMMEDIATE');
        } else {
            $pdo->beginTransaction();
        }

        try {
            $resultat = $operation();

            $sqlite ? $pdo->exec('COMMIT') : $pdo->commit();

            return $resultat;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $sqlite ? $pdo->exec('ROLLBACK') : $pdo->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Rejoue une operation bloquee par la contention, avec recul exponentiel.
     *
     * A une entree, plusieurs portes ecrivent en meme temps. Sous SQLite tous
     * les ecrivains se serialisent sur un verrou global ; sous MySQL deux
     * transactions peuvent s'interbloquer. Dans les deux cas le moteur refuse
     * l'operation au lieu de la corrompre, et la bonne reponse est de reessayer.
     *
     * Ce n'est sur QUE parce que les operations concernees sont transactionnelles
     * : un echec ne valide rien, donc rejouer ne peut pas consommer deux fois la
     * meme carte. Ne jamais envelopper une operation qui ecrit hors transaction.
     *
     * @template T
     * @param callable():T $operation
     * @return T
     */
    public static function retryOnLock(callable $operation, int $tentatives = 4): mixed
    {
        $essai = 0;

        while (true) {
            try {
                return $operation();
            } catch (PDOException $e) {
                $essai++;

                if ($essai >= $tentatives || !self::isLockContention($e)) {
                    throw $e;
                }

                // Recul exponentiel avec gigue : sans la part aleatoire, deux
                // processus repousses en meme temps reessaieraient ensemble et
                // se bloqueraient a nouveau, indefiniment.
                $baseUs = 20_000 * (2 ** ($essai - 1));
                usleep((int) ($baseUs / 2 + random_int(0, (int) $baseUs)));
            }
        }
    }

    /** L'erreur traduit-elle une contention (et non une faute de programmation) ? */
    public static function isLockContention(PDOException $e): bool
    {
        $code = $e->errorInfo[1] ?? null;

        // MySQL : 1213 interblocage, 1205 attente de verrou expiree.
        if ($code === 1213 || $code === 1205) {
            return true;
        }

        // SQLite : 5 (SQLITE_BUSY) et 6 (SQLITE_LOCKED). Le pilote ne les
        // remonte pas toujours dans errorInfo, d'ou la lecture du message.
        if ($code === 5 || $code === 6) {
            return true;
        }

        $message = strtolower($e->getMessage());

        return str_contains($message, 'database is locked')
            || str_contains($message, 'database table is locked')
            || str_contains($message, 'deadlock')
            || str_contains($message, 'lock wait timeout');
    }
}
