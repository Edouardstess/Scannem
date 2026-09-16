<?php

declare(strict_types=1);

namespace Scannem\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use Scannem\RateLimiter;
use Scannem\Tests\Support\TestDb;

final class RateLimiterTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = TestDb::fresh(TestDb::tempFile());
    }

    public function testLesAppelsPassentJusquAuPlafond(): void
    {
        $limiter = new RateLimiter($this->pdo, 60);

        for ($i = 1; $i <= 5; $i++) {
            self::assertTrue($limiter->allow('device:1', 5), "Appel $i refuse a tort");
        }

        self::assertFalse($limiter->allow('device:1', 5), 'Le 6e appel doit etre bloque');
    }

    public function testLesClefsSontIndependantes(): void
    {
        $limiter = new RateLimiter($this->pdo, 60);

        for ($i = 0; $i < 5; $i++) {
            $limiter->allow('device:1', 5);
        }

        self::assertFalse($limiter->allow('device:1', 5));
        self::assertTrue($limiter->allow('device:2', 5), 'Un appareil ne doit pas brider les autres');
    }

    public function testLeCompteurRepartAZeroALaFenetreSuivante(): void
    {
        // Fenetre d'une seconde : on observe la remise a zero sans attendre.
        $limiter = new RateLimiter($this->pdo, 1);

        self::assertTrue($limiter->allow('ip:test', 2));
        self::assertTrue($limiter->allow('ip:test', 2));
        self::assertFalse($limiter->allow('ip:test', 2));

        // On attend le basculement de fenetre.
        $debut = time();
        while (time() === $debut) {
            usleep(50000);
        }

        self::assertTrue($limiter->allow('ip:test', 2), 'Le quota doit se liberer a la fenetre suivante');
    }

    public function testUnPlafondNulDesactiveLaLimite(): void
    {
        $limiter = new RateLimiter($this->pdo, 60);

        for ($i = 0; $i < 50; $i++) {
            self::assertTrue($limiter->allow('libre', 0));
        }
    }

    public function testLeDelaiDAttenteResteDansLaFenetre(): void
    {
        $limiter = new RateLimiter($this->pdo, 60);
        $delai = $limiter->retryAfter();

        self::assertGreaterThan(0, $delai);
        self::assertLessThanOrEqual(60, $delai);
    }

    // ------------------------------------------------------- Choix du backend

    public function testLeBackendParDefautEstLaBaseDeDonnees(): void
    {
        // Volontairement 'db' et non 'auto' : en mode auto, chaque requete
        // tenterait une connexion Redis meme sur un hebergement qui n'en a pas.
        $limiter = \Scannem\RateLimiter::fromConfig($this->pdo, new \Scannem\Config());

        self::assertInstanceOf(\Scannem\RateLimiter::class, $limiter);
    }

    public function testLeQuotaPeutEtreDesactive(): void
    {
        $limiter = \Scannem\RateLimiter::fromConfig(
            $this->pdo,
            new \Scannem\Config(['rate_limit_driver' => 'none'])
        );

        self::assertInstanceOf(\Scannem\NullRateLimiter::class, $limiter);

        for ($i = 0; $i < 50; $i++) {
            self::assertTrue($limiter->allow('device:1', 1));
        }
    }

    public function testUnRedisInjoignableRetombeSurLaBaseSansCasserLEntree(): void
    {
        // Port volontairement mort : une entree ne doit jamais s'arreter parce
        // qu'un Redis ne repond pas.
        $limiter = \Scannem\RateLimiter::fromConfig($this->pdo, new \Scannem\Config([
            'rate_limit_driver' => 'redis',
            'redis_port' => 6399,
        ]));

        self::assertInstanceOf(\Scannem\RateLimiter::class, $limiter);
        self::assertTrue($limiter->allow('device:1', 5));
    }

    public function testLeBackendRedisCompteCommeCeluiDeLaBase(): void
    {
        $limiter = \Scannem\RedisRateLimiter::tryConnect(
            new \Scannem\Config(['rate_limit_driver' => 'redis'])
        );

        if ($limiter === null) {
            self::markTestSkipped('Aucun serveur Redis joignable.');
        }

        // Clef unique : les tests ne doivent pas se marcher dessus entre eux.
        $cle = 'test:' . bin2hex(random_bytes(6));

        for ($i = 1; $i <= 5; $i++) {
            self::assertTrue($limiter->allow($cle, 5), "Appel $i refuse a tort");
        }

        self::assertFalse($limiter->allow($cle, 5), 'Le 6e appel doit etre bloque');
        self::assertSame(6, $limiter->counter($cle));

        // Autre clef, autre compteur : un appareil ne bride pas les autres.
        self::assertTrue($limiter->allow($cle . '-bis', 5));
    }

    public function testLaPurgeNettoieLesFenetresPerimees(): void
    {
        $limiter = new RateLimiter($this->pdo, 1);
        $limiter->allow('vieux', 10);

        // On vieillit artificiellement l'entree.
        $this->pdo->prepare('UPDATE rate_limits SET window_start = ? WHERE bucket_key = ?')
            ->execute([time() - 3600, 'vieux']);

        $limiter->purge();

        self::assertSame(
            0,
            (int) $this->pdo->query('SELECT COUNT(*) FROM rate_limits')->fetchColumn()
        );
    }
}
