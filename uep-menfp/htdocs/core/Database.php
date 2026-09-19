<?php
declare(strict_types=1);

/**
 * Connexion PDO unique à MySQL.
 *
 * Toute l'application passe par Database::pdo() : une seule connexion par
 * requête HTTP, en mode exception, sans émulation des requêtes préparées.
 */
final class Database
{
    private static ?PDO $instance = null;

    public static function pdo(): PDO
    {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        try {
            self::$instance = new PDO(DB_DSN, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
        } catch (PDOException $e) {
            error_log('Connexion BDD échouée : ' . $e->getMessage());
            throw new RuntimeException('Connexion à la base de données impossible.', 0, $e);
        }

        return self::$instance;
    }

    /** Réinitialise la connexion (utilisé par l'installateur et les tests). */
    public static function reinitialiser(): void
    {
        self::$instance = null;
    }

    private function __construct() {}
    private function __clone() {}
}
