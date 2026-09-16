<?php

declare(strict_types=1);

namespace Scannem;

use PDO;

/**
 * Quota par fenetre fixe, stocke en base.
 *
 * Avec 80 bits de signature, deviner un code valide est hors de portee : ce
 * limiteur ne sert pas a bloquer une attaque cryptographique. Il sert a couper
 * le scan de masse, les boucles de synchronisation emballees, et l'usure inutile
 * de la base quand un appareil part en vrille.
 *
 * Fenetre fixe plutot que glissante : c'est moins precis aux bordures, mais ca
 * tient en une ligne de table et ca fonctionne identiquement sur SQLite et MySQL.
 */
final class RateLimiter implements RateLimiterInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly int $window = 60,
    ) {
    }

    /**
     * Choisit le backend de quota selon la configuration.
     *
     * `rate_limit_driver` : auto (defaut) | db | redis | none.
     *
     * En mode auto, Redis n'est retenu que s'il repond : une installation sans
     * Redis n'a rien a configurer, et un Redis tombe ne doit pas empecher les
     * entrees. Le repli en base est donc toujours disponible.
     */
    public static function fromConfig(PDO $pdo, Config $config): RateLimiterInterface
    {
        $fenetre = $config->int('rate_limit_window', 60);
        $driver = $config->str('rate_limit_driver', 'auto');

        if ($driver === 'none') {
            return new NullRateLimiter();
        }

        if ($driver === 'redis' || $driver === 'auto') {
            $redis = RedisRateLimiter::tryConnect($config);

            if ($redis !== null) {
                return $redis;
            }

            // En mode 'redis' explicite, l'exploitant a demande Redis : on le
            // signale au journal plutot que de basculer en silence.
            if ($driver === 'redis') {
                error_log('[scannem] Redis injoignable, repli du quota sur la base de donnees.');
            }
        }

        return new self($pdo, $fenetre);
    }

    /**
     * Enregistre une tentative et indique si elle est autorisee.
     *
     * @return bool true si l'appel passe, false si le quota est depasse
     */
    public function allow(string $key, int $max): bool
    {
        if ($max <= 0) {
            return true;
        }

        $now = time();
        $windowStart = $now - ($now % $this->window);

        // On tente d'abord l'increment de la fenetre en cours. C'est le chemin
        // frequent, et il est atomique.
        $bump = $this->pdo->prepare(
            'UPDATE rate_limits SET counter = counter + 1 WHERE bucket_key = ? AND window_start = ?'
        );
        $bump->execute([$key, $windowStart]);

        if ($bump->rowCount() === 1) {
            return $this->counter($key) <= $max;
        }

        // Pas de ligne pour cette fenetre : soit c'est la premiere tentative,
        // soit la fenetre precedente est perimee. On ecrase dans les deux cas.
        $reset = $this->pdo->prepare(
            'UPDATE rate_limits SET window_start = ?, counter = 1 WHERE bucket_key = ?'
        );
        $reset->execute([$windowStart, $key]);

        if ($reset->rowCount() === 1) {
            return 1 <= $max;
        }

        try {
            $this->pdo->prepare(
                'INSERT INTO rate_limits (bucket_key, window_start, counter) VALUES (?, ?, 1)'
            )->execute([$key, $windowStart]);
        } catch (\PDOException $e) {
            // Course avec un autre processus sur la meme clef : il a insere en
            // premier, on se contente d'incrementer sa ligne.
            $bump->execute([$key, $windowStart]);

            return $this->counter($key) <= $max;
        }

        return 1 <= $max;
    }

    public function counter(string $key): int
    {
        $stmt = $this->pdo->prepare('SELECT counter FROM rate_limits WHERE bucket_key = ?');
        $stmt->execute([$key]);

        $value = $stmt->fetchColumn();

        return $value === false ? 0 : (int) $value;
    }

    /** Nombre de secondes avant la remise a zero du compteur. */
    public function retryAfter(): int
    {
        $now = time();

        return $this->window - ($now % $this->window);
    }

    /** Purge les fenetres perimees. A appeler de temps en temps, pas a chaque scan. */
    public function purge(): void
    {
        $this->pdo->prepare('DELETE FROM rate_limits WHERE window_start < ?')
            ->execute([time() - ($this->window * 10)]);
    }
}
