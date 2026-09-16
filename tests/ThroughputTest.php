<?php

declare(strict_types=1);

namespace Scannem\Tests;

use PDO;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Scannem\Db;
use Scannem\ScanResult;
use Scannem\Tests\Support\TestDb;

/**
 * Debit sous charge : plusieurs vigiles scannant en meme temps.
 *
 * ConcurrencyTest prouve la CORRECTION (une seule personne entre avec une carte
 * copiee). Ce fichier-ci mesure le DEBIT : N appareils scannant des cartes
 * DIFFERENTES simultanement, ce qui est le vrai regime de fonctionnement d'une
 * entree a plusieurs portes.
 *
 * Les deux moteurs sont couverts, parce qu'ils se comportent de facon opposee :
 * SQLite serialise tous les ecrivains sur un verrou global, InnoDB verrouille
 * ligne par ligne. MySQL est saute si SCANNEM_TEST_MYSQL n'est pas defini.
 *
 * @requires extension pcntl
 */
final class ThroughputTest extends TestCase
{
    /** Nombre de processus simultanes, au-dela de la demande (plus de 10 appareils). */
    private const PROCESSUS = 20;

    protected function setUp(): void
    {
        if (!function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl requis pour tester la concurrence reelle.');
        }
    }

    private function preparer(string $engine): string
    {
        if ($engine === 'mysql' && !TestDb::hasMysql()) {
            self::markTestSkipped('SCANNEM_TEST_MYSQL non defini : MySQL non teste.');
        }

        return TestDb::handle($engine, 'scannem-debit');
    }

    /**
     * Lance un processus par entree de $travaux et collecte les verdicts.
     *
     * Chaque enfant ouvre sa propre connexion : une connexion PDO heritee par
     * fork serait partagee et fausserait completement la mesure.
     *
     * @param list<string> $travaux un payload par processus
     * @return array{verdicts:list<string>, duree:float}
     */
    private function ruee(string $handle, array $travaux): array
    {
        $tubes = [];
        $pids = [];

        // Depart synchronise sur une horloge commune : sans ca les processus
        // frappent la base a la queue leu leu et il n'y a aucune contention.
        $topDepart = microtime(true) + 0.5;

        foreach ($travaux as $payload) {
            $paire = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            self::assertNotFalse($paire, 'stream_socket_pair a echoue');

            $pid = pcntl_fork();
            self::assertNotSame(-1, $pid, 'fork a echoue');

            if ($pid === 0) {
                fclose($paire[0]);

                $verdict = 'error';

                try {
                    $pdo = TestDb::connectTo($handle);
                    $repo = TestDb::repository($pdo);

                    $attente = $topDepart - microtime(true);
                    if ($attente > 0) {
                        usleep((int) ($attente * 1_000_000));
                    }

                    $verdict = $repo->redeem($payload, null)['result'];
                } catch (\Throwable $e) {
                    $verdict = 'exception:' . $e->getMessage();
                }

                fwrite($paire[1], $verdict);
                fclose($paire[1]);

                // Sortie sans declencher les fonctions d'arret heritees de
                // PHPUnit : elles ecriraient un rapport parasite et
                // supprimeraient la base de test partagee.
                if (function_exists('posix_kill')) {
                    posix_kill((int) getmypid(), SIGKILL);
                }

                exit(0);
            }

            fclose($paire[1]);
            $tubes[] = $paire[0];
            $pids[] = $pid;
        }

        $verdicts = [];
        foreach ($tubes as $tube) {
            $verdicts[] = trim((string) stream_get_contents($tube));
            fclose($tube);
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        // La duree est mesuree depuis le top de depart : le temps de fork des
        // 20 processus n'a rien a voir avec le debit de la base.
        return ['verdicts' => $verdicts, 'duree' => microtime(true) - $topDepart];
    }

    // ------------------------------------------------------------- Tests

    /**
     * Le test qui repond litteralement a la demande : plusieurs vigiles scannent
     * des cartes differentes au meme instant, tout le monde passe.
     */
    #[DataProviderExternal(TestDb::class, 'engines')]
    public function testVingtAppareilsScannentDesCartesDifferentesSansConflit(string $engine): void
    {
        $handle = $this->preparer($engine);
        $pdo = TestDb::freshAt($handle);
        $repo = TestDb::repository($pdo);

        $lot = $repo->createBatch('Grosse soiree', self::PROCESSUS);
        $payloads = array_column($lot['cards'], 'payload');

        unset($repo, $pdo);
        Db::reset();

        $bilan = $this->ruee($handle, $payloads);

        $admis = 0;
        $erreurs = [];

        foreach ($bilan['verdicts'] as $verdict) {
            if ($verdict === ScanResult::ADMITTED) {
                $admis++;
            } else {
                $erreurs[] = $verdict;
            }
        }

        self::assertSame([], $erreurs, 'Aucun scan ne doit echouer sous charge');
        self::assertSame(
            self::PROCESSUS,
            $admis,
            'Des cartes differentes ne se bloquent jamais entre elles'
        );

        $parSeconde = $bilan['duree'] > 0 ? self::PROCESSUS / $bilan['duree'] : INF;

        // La mesure est affichee pour pouvoir dimensionner l'installation avec un
        // chiffre plutot qu'avec une impression. Elle n'est pas un critere de
        // reussite : la machine de test n'est pas le serveur de production.
        fwrite(STDERR, sprintf(
            "\n  [debit %s] %d scans en %.3f s -> %.0f scans/s\n",
            $engine,
            self::PROCESSUS,
            $bilan['duree'],
            $parSeconde
        ));

        self::assertSame(
            self::PROCESSUS,
            (int) TestDb::connectTo($handle)
                ->query("SELECT COUNT(*) FROM scans WHERE result = 'admitted'")
                ->fetchColumn()
        );
    }

    /**
     * Regime melange : du trafic normal ET une carte copiee presentee a
     * plusieurs portes au meme instant. Le trafic normal ne doit pas souffrir
     * de la bagarre sur la carte dupliquee.
     */
    #[DataProviderExternal(TestDb::class, 'engines')]
    public function testUneCarteCopieeNeBloquePasLeResteDuTrafic(string $engine): void
    {
        $handle = $this->preparer($engine);
        $pdo = TestDb::freshAt($handle);
        $repo = TestDb::repository($pdo);

        $lot = $repo->createBatch('Melange', 15);
        $cartes = array_column($lot['cards'], 'payload');

        // 14 cartes distinctes + 6 presentations de la 15e (l'originale et ses copies).
        $travaux = array_slice($cartes, 0, 14);
        for ($i = 0; $i < 6; $i++) {
            $travaux[] = $cartes[14];
        }

        unset($repo, $pdo);
        Db::reset();

        $bilan = $this->ruee($handle, $travaux);

        $admis = 0;
        $refuses = 0;
        $erreurs = [];

        foreach ($bilan['verdicts'] as $verdict) {
            match ($verdict) {
                ScanResult::ADMITTED => $admis++,
                ScanResult::ALREADY_USED => $refuses++,
                default => $erreurs[] = $verdict,
            };
        }

        self::assertSame([], $erreurs, 'Aucune erreur, meme en regime melange');
        self::assertSame(15, $admis, 'Les 14 cartes distinctes + une seule de la copiee');
        self::assertSame(5, $refuses, 'Les 5 autres presentations de la carte copiee');
    }

    /**
     * Non-regression : plusieurs appareils derriere la MEME adresse IP.
     *
     * A un evenement, toutes les portes passent par le Wi-Fi du lieu ou un
     * partage de connexion : elles sortent donc sur une seule IP publique. Un
     * quota applique a cette IP les briderait collectivement et provoquerait des
     * refus « trop de scans » en pleine entree.
     */
    #[Group('quota')]
    public function testDesAppareilsDerriereUneMemeIpNeSeBridentPas(): void
    {
        $handle = TestDb::handle('sqlite', 'scannem-nat');
        $pdo = TestDb::freshAt($handle);
        $repo = TestDb::repository($pdo);

        $lot = $repo->createBatch('Derriere le NAT', 60);
        $payloads = array_column($lot['cards'], 'payload');

        // 12 appareils enroles, tous vus depuis la meme IP publique.
        $appareils = [];
        for ($i = 1; $i <= 12; $i++) {
            $pdo->prepare('INSERT INTO devices (label, token_hash, active, created_at) VALUES (?, ?, 1, ?)')
                ->execute(["Porte $i", hash('sha256', "porte-$i"), Db::now()]);
            $appareils[] = (int) $pdo->lastInsertId();
        }

        $limiter = \Scannem\RateLimiter::fromConfig($pdo, new \Scannem\Config());

        $refusesParQuota = 0;

        // Chaque appareil enchaine 5 scans : 60 requetes depuis une seule IP.
        foreach ($payloads as $index => $payload) {
            $appareil = $appareils[$index % 12];

            if (!$limiter->allow('device:' . $appareil, 120)) {
                $refusesParQuota++;
                continue;
            }

            $repo->redeem($payload, $appareil);
        }

        self::assertSame(
            0,
            $refusesParQuota,
            'Douze portes sur une seule IP ne doivent jamais se brider mutuellement'
        );
        self::assertSame(60, (int) $pdo->query("SELECT COUNT(*) FROM cards WHERE status = 'used'")->fetchColumn());
    }
}
