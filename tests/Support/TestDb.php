<?php

declare(strict_types=1);

namespace Scannem\Tests\Support;

use PDO;
use Scannem\CardRepository;
use Scannem\Db;
use Scannem\Token;

/**
 * Fabrique de bases de test.
 *
 * Les tests de concurrence ont besoin d'un fichier SQLite reel : une base en
 * memoire n'est pas partagee entre processus, donc elle ne peut pas mettre en
 * evidence une course.
 */
final class TestDb
{
    private const KEY = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public static function token(): Token
    {
        return new Token(['A' => self::KEY], 'A');
    }

    public static function tempFile(string $prefix = 'scannem-test'): string
    {
        $path = sys_get_temp_dir() . '/' . $prefix . '-' . bin2hex(random_bytes(6)) . '.sqlite';

        // Le PID est capture ici pour que seul le processus createur nettoie.
        // Les tests de concurrence forkent puis appellent exit(), ce qui declenche
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

    public static function connect(string $path): PDO
    {
        Db::reset();

        return Db::connectSqlite($path);
    }

    /** Base fraiche, migree, prete a l'emploi. */
    public static function fresh(string $path): PDO
    {
        $pdo = self::connect($path);
        Db::migrate($pdo);

        return $pdo;
    }

    public static function repository(PDO $pdo): CardRepository
    {
        return new CardRepository($pdo, self::token());
    }
}
