<?php

declare(strict_types=1);

namespace Scannem\Tests;

use PHPUnit\Framework\TestCase;
use Scannem\ScanResult;
use Scannem\Tests\Support\TestDb;

/**
 * Le test le plus important du projet.
 *
 * Scenario reel : quelqu'un photographie sa carte et l'envoie a un ami. Les deux
 * se presentent au meme moment, l'un porte A, l'autre porte B. Le systeme doit
 * en laisser entrer exactement un.
 *
 * Ces tests utilisent pcntl_fork pour obtenir de vrais processus concurrents.
 * Simuler la concurrence dans un seul processus ne prouverait rien : le verrou
 * du moteur de base ne serait jamais mis a l'epreuve.
 *
 * @requires extension pcntl
 */
final class ConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl requis pour tester la concurrence reelle.');
        }
    }

    /**
     * Lance $processus tentatives simultanees sur le meme payload.
     *
     * @return array{admis:int, refuses:int}
     */
    private function ruee(string $dbPath, string $payload, int $processus): array
    {
        // Tube pour remonter le verdict de chaque enfant au parent.
        $pipes = [];
        $pids = [];

        // On synchronise le depart sur une horloge commune pour que les processus
        // frappent la base au meme instant, plutot qu'a la queue leu leu.
        $topDepart = microtime(true) + 0.35;

        for ($i = 0; $i < $processus; $i++) {
            $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            self::assertNotFalse($pair, 'stream_socket_pair a echoue');

            $pid = pcntl_fork();
            self::assertNotSame(-1, $pid, 'fork a echoue');

            if ($pid === 0) {
                // --- Processus enfant ---
                fclose($pair[0]);

                $verdict = 'error';

                try {
                    // Chaque enfant ouvre SA propre connexion : une connexion PDO
                    // heritee par fork serait partagee et fausserait le test.
                    $pdo = TestDb::connect($dbPath);
                    $repo = TestDb::repository($pdo);

                    $attente = $topDepart - microtime(true);
                    if ($attente > 0) {
                        usleep((int) ($attente * 1_000_000));
                    }

                    $verdict = $repo->redeem($payload, null)['result'];
                } catch (\Throwable $e) {
                    $verdict = 'exception:' . $e->getMessage();
                }

                fwrite($pair[1], $verdict);
                fclose($pair[1]);

                // Sortie sans passer par les fonctions d'arret. L'enfant a herite
                // de celles de PHPUnit et de Composer ; les laisser tourner ferait
                // ecrire un rapport de test parasite et supprimerait la base de
                // test partagee. Le verdict est deja parti dans le tube.
                if (function_exists('posix_kill')) {
                    posix_kill((int) getmypid(), SIGKILL);
                }

                exit(0);
            }

            // --- Processus parent ---
            fclose($pair[1]);
            $pipes[] = $pair[0];
            $pids[] = $pid;
        }

        $verdicts = [];
        foreach ($pipes as $pipe) {
            $verdicts[] = trim((string) stream_get_contents($pipe));
            fclose($pipe);
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        $admis = 0;
        $refuses = 0;

        foreach ($verdicts as $verdict) {
            if ($verdict === ScanResult::ADMITTED) {
                $admis++;
            } elseif ($verdict === ScanResult::ALREADY_USED) {
                $refuses++;
            } else {
                self::fail("Verdict inattendu d'un processus concurrent : $verdict");
            }
        }

        return ['admis' => $admis, 'refuses' => $refuses];
    }

    public function testDixScansSimultanesDeLaMemeCarteNEnLaissentPasserQuUn(): void
    {
        $dbPath = TestDb::tempFile('scannem-race');
        $pdo = TestDb::fresh($dbPath);
        $repo = TestDb::repository($pdo);

        $batch = $repo->createBatch('Course', 1);
        $payload = $batch['cards'][0]['payload'];

        // On ferme la connexion du parent avant de forker : SQLite n'aime pas
        // qu'un descripteur de fichier soit partage entre processus.
        unset($repo, $pdo);
        \Scannem\Db::reset();

        $bilan = $this->ruee($dbPath, $payload, 10);

        self::assertSame(1, $bilan['admis'], 'Exactement une personne doit entrer');
        self::assertSame(9, $bilan['refuses']);

        // Et la trace doit rester coherente apres la bataille.
        $verif = TestDb::connect($dbPath);
        self::assertSame(
            1,
            (int) $verif->query("SELECT COUNT(*) FROM scans WHERE result = 'admitted'")->fetchColumn(),
            'Le journal ne doit contenir qu une seule admission'
        );
        self::assertSame(
            'used',
            (string) $verif->query('SELECT status FROM cards LIMIT 1')->fetchColumn()
        );
    }

    public function testLaCourseTientSurPlusieursCartesEnParallele(): void
    {
        $dbPath = TestDb::tempFile('scannem-race-multi');
        $pdo = TestDb::fresh($dbPath);
        $repo = TestDb::repository($pdo);

        $batch = $repo->createBatch('Course multiple', 5);
        $payloads = array_column($batch['cards'], 'payload');

        unset($repo, $pdo);
        \Scannem\Db::reset();

        foreach ($payloads as $index => $payload) {
            $bilan = $this->ruee($dbPath, $payload, 4);

            self::assertSame(1, $bilan['admis'], "Carte $index : une seule admission attendue");
            self::assertSame(3, $bilan['refuses'], "Carte $index");
        }

        $verif = TestDb::connect($dbPath);

        self::assertSame(
            5,
            (int) $verif->query("SELECT COUNT(*) FROM cards WHERE status = 'used'")->fetchColumn()
        );
        self::assertSame(
            5,
            (int) $verif->query("SELECT COUNT(*) FROM scans WHERE result = 'admitted'")->fetchColumn()
        );
    }
}
