<?php

declare(strict_types=1);

namespace Scannem;

use Redis;
use RedisException;

/**
 * Quota tenu en memoire par Redis.
 *
 * Interet : zero ecriture en base sur le chemin d'un scan. Sous SQLite, toute
 * ecriture prend le verrou global, donc en deporter une compte.
 *
 * A dire franchement : d'apres les mesures, ce n'est pas necessaire. Le quota
 * en base tient deja plusieurs centaines de scans par seconde, tres au-dela du
 * rythme d'une entree reelle. Ce backend est la pour les installations qui ont
 * deja un Redis sous la main, pas pour en justifier l'ajout.
 *
 * INCR est atomique cote Redis : deux portes qui incrementent le meme compteur
 * au meme instant ne peuvent pas se perdre mutuellement.
 */
final class RedisRateLimiter implements RateLimiterInterface
{
    private const PREFIXE = 'scannem:quota:';

    public function __construct(
        private readonly Redis $redis,
        private readonly int $window = 60,
    ) {
    }

    /**
     * Tente d'ouvrir une connexion Redis.
     *
     * Renvoie null si l'extension manque ou si le serveur ne repond pas : le
     * quota ne doit jamais empecher une entree de fonctionner, l'appelant
     * retombe alors sur la base.
     */
    public static function tryConnect(Config $config): ?self
    {
        if (!extension_loaded('redis') || !class_exists(Redis::class)) {
            return null;
        }

        try {
            $redis = new Redis();

            $connected = $redis->connect(
                $config->str('redis_host', '127.0.0.1'),
                $config->int('redis_port', 6379),
                // Delai court : a la porte, mieux vaut basculer vite sur la base
                // que de faire patienter quelqu'un devant un Redis muet.
                0.3
            );

            if ($connected === false) {
                return null;
            }

            $password = $config->str('redis_password', '');
            if ($password !== '') {
                $redis->auth($password);
            }

            $redis->ping();

            return new self($redis, $config->int('rate_limit_window', 60));
        } catch (RedisException | \Throwable $e) {
            return null;
        }
    }

    public function allow(string $key, int $max): bool
    {
        if ($max <= 0) {
            return true;
        }

        $bucket = self::PREFIXE . $key . ':' . $this->windowStart();

        try {
            $compte = $this->redis->incr($bucket);

            // Pose l'expiration au premier passage seulement : la renouveler a
            // chaque appel ferait glisser la fenetre indefiniment et le
            // compteur ne retomberait jamais a zero.
            if ($compte === 1) {
                $this->redis->expire($bucket, $this->window * 2);
            }

            return $compte <= $max;
        } catch (RedisException | \Throwable $e) {
            // Redis tombe en pleine soiree : on laisse passer plutot que de
            // bloquer l'entree. Un quota est un confort, pas une securite.
            return true;
        }
    }

    public function counter(string $key): int
    {
        try {
            $valeur = $this->redis->get(self::PREFIXE . $key . ':' . $this->windowStart());

            return $valeur === false ? 0 : (int) $valeur;
        } catch (RedisException | \Throwable $e) {
            return 0;
        }
    }

    public function retryAfter(): int
    {
        return $this->window - (time() % $this->window);
    }

    /** Redis expire les clefs tout seul : rien a purger. */
    public function purge(): void
    {
    }

    private function windowStart(): int
    {
        $now = time();

        return $now - ($now % $this->window);
    }
}
