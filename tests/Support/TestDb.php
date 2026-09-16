<?php

declare(strict_types=1);

namespace Scannem\Tests\Support;

use PDO;
use Scannem\CardRepository;
use Scannem\Db;
use Scannem\Token;

/**
 * Fabrique de bases de test, SQLite et MySQL.
 *
 * Les tests de concurrence ont besoin d'une base reellement partagee entre
 * processus : une base SQLite en memoire ne l'est pas, donc elle ne peut pas
 * mettre en evidence une course.
 *
 * Les deux moteurs doivent etre couverts, parce que leur comportement sous
 * concurrence n'a rien a voir : SQLite serialise tous les ecrivains sur un
 * verrou global, InnoDB verrouille ligne par ligne. Un test qui ne passerait
 * que sur SQLite ne prouverait rien de l'hebergement final.
 *
 * MySQL n'est utilise que si SCANNEM_TEST_MYSQL est defini ; sinon les cas
 * correspondants sont sautes, pour que la suite reste executable partout.
 */
final class TestDb
{
    private const KEY = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    /** Tables a vider entre deux tests MySQL, dans l'ordre inverse des dependances. */
    private const TABLES = [
        'scans', 'cards', 'batches', 'devices', 'enroll_codes', 'admins', 'rate_limits',
    ];

    public static function token(): Token
    {
        return new Token(['A' => self::KEY], 'A');
    }

    // ------------------------------------------------------------- Moteurs

    public static function mysqlDsn(): ?string
    {
        $dsn = getenv('SCANNEM_TEST_MYSQL');

        return is_string($dsn) && $dsn !== '' ? $dsn : null;
    }

    public static function hasMysql(): bool
    {
        return self::mysqlDsn() !== null;
    }

    /**
     * Moteurs a couvrir, pour les fournisseurs de donnees PHPUnit.
     *
     * @return array<string, array{string}>
     */
    public static function engines(): array
    {
        return ['sqlite' => ['sqlite'], 'mysql' => ['mysql']];
    }

    /**
     * Identifiant de base transmissible a un processus enfant.
     *
     * C'est une simple chaine : apres un fork, l'enfant ne peut pas heriter d'une
     * connexion PDO utilisable, il doit rouvrir la sienne a partir de cette
     * valeur (les identifiants MySQL viennent de l'environnement, herite lui).
     */
    public static function handle(string $engine, string $prefix = 'scannem-test'): string
    {
        return $engine === 'mysql' ? 'mysql' : 'sqlite:' . self::tempFile($prefix);
    }

    public static function tempFile(string $prefix = 'scannem-test'): string
    {
        $path = sys_get_temp_dir() . '/' . $prefix . '-' . bin2hex(random_bytes(6)) . '.sqlite';

        // Le PID est capture ici pour que seul le processus createur nettoie.
        // Les tests de concurrence forkent puis se terminent, ce qui declenche
        // les fonctions d'arret heritees : sans ce garde-fou, le premier enfant
        // qui se termine efface la base que ses freres sont en train d'utiliser.
        $owner = getmypid();

        register_shutdown_function(static function () use ($path, $owner): void {
            if (getmypid() !== $owner) {
                return;
            }

            foreach ([$path, $path . '-wal', $path . '-shm'] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        });

        return $path;
    }

    // ---------------------------------------------------------- Connexions

    /** Ouvre une connexion neuve a partir d'un identifiant de base. */
    public static function connectTo(string $handle): PDO
    {
        Db::reset();

        if (str_starts_with($handle, 'sqlite:')) {
            return Db::connectSqlite(substr($handle, 7));
        }

        $dsn = self::mysqlDsn();

        if ($dsn === null) {
            throw new \RuntimeException('SCANNEM_TEST_MYSQL non defini.');
        }

        $user = getenv('SCANNEM_TEST_MYSQL_USER');
        $pass = getenv('SCANNEM_TEST_MYSQL_PASS');

        return new PDO(
            $dsn,
            is_string($user) && $user !== '' ? $user : 'root',
            is_string($pass) ? $pass : '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                // Volontairement absent : PDO::MYSQL_ATTR_FOUND_ROWS.
                // Avec cette option, rowCount() renverrait les lignes
                // CORRESPONDANTES et non MODIFIEES, et l'invalidation atomique
                // de redeem() admettrait tout le monde au lieu d'une personne.
            ]
        );
    }

    /** Base vide et migree, prete a l'emploi. */
    public static function freshAt(string $handle): PDO
    {
        $pdo = self::connectTo($handle);

        if (Db::driverOf($pdo) === 'mysql') {
            // Une seule base MySQL sert a toute la suite : on la remet a zero
            // plutot que d'en creer une par test.
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

            foreach (self::TABLES as $table) {
                $pdo->exec("DROP TABLE IF EXISTS $table");
            }

            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }

        Db::migrate($pdo);

        return $pdo;
    }

    // --------------------------------------------- Compatibilite SQLite

    public static function connect(string $path): PDO
    {
        return self::connectTo('sqlite:' . $path);
    }

    public static function fresh(string $path): PDO
    {
        return self::freshAt('sqlite:' . $path);
    }

    public static function repository(PDO $pdo): CardRepository
    {
        return new CardRepository($pdo, self::token());
    }
}
