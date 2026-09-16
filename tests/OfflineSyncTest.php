<?php

declare(strict_types=1);

namespace Scannem\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use Scannem\CardRepository;
use Scannem\OfflinePack;
use Scannem\ScanResult;
use Scannem\Tests\Support\TestDb;
use Scannem\Token;

final class OfflineSyncTest extends TestCase
{
    private PDO $pdo;
    private CardRepository $repo;
    private OfflinePack $pack;

    protected function setUp(): void
    {
        $this->pdo = TestDb::fresh(TestDb::tempFile());
        $this->repo = TestDb::repository($this->pdo);
        $this->pack = new OfflinePack($this->pdo);
    }

    private function porte(string $label): int
    {
        $this->pdo->prepare('INSERT INTO devices (label, token_hash, active, created_at) VALUES (?, ?, 1, ?)')
            ->execute([$label, hash('sha256', $label), '2026-01-01T00:00:00Z']);

        return (int) $this->pdo->lastInsertId();
    }

    // ------------------------------------------------------------------ Pack

    public function testLePackNeContientQueDesEmpreintes(): void
    {
        $batch = $this->repo->createBatch('Soiree', 5);
        $pack = $this->pack->build($batch['batch_id']);

        self::assertSame(5, $pack['count']);
        self::assertCount(5, $pack['fingerprints']);

        $serialise = json_encode($pack);

        foreach ($batch['cards'] as $carte) {
            // Ni l'uid ni le payload ne doivent apparaitre : un telephone vole ne
            // doit pas livrer de quoi fabriquer une carte.
            self::assertStringNotContainsString($carte['uid'], (string) $serialise);
            self::assertStringNotContainsString($carte['payload'], (string) $serialise);
        }
    }

    public function testLePackEstTriePourLaRechercheDichotomique(): void
    {
        $this->repo->createBatch('Soiree', 50);
        $empreintes = $this->pack->build()['fingerprints'];

        $trie = $empreintes;
        sort($trie);

        self::assertSame($trie, $empreintes);
    }

    public function testLePackIgnoreLesCartesAnnuleesEtUtilisees(): void
    {
        $batch = $this->repo->createBatch('Soiree', 6);

        $this->repo->revoke($batch['cards'][0]['uid']);
        $this->repo->redeem($batch['cards'][1]['payload']);

        self::assertSame(4, $this->pack->build($batch['batch_id'])['count']);
    }

    public function testLaVersionDuPackChangeAvecSonContenu(): void
    {
        $batch = $this->repo->createBatch('Soiree', 4);
        $avant = $this->pack->build($batch['batch_id'])['version'];

        $this->repo->revoke($batch['cards'][0]['uid']);
        $apres = $this->pack->build($batch['batch_id'])['version'];

        self::assertNotSame($avant, $apres, 'Le telephone doit pouvoir detecter un pack perime');
    }

    // ------------------------------------------------------- Synchronisation

    public function testUneFileHorsLigneSansConflitEstEntierementAppliquee(): void
    {
        $batch = $this->repo->createBatch('Soiree', 3);

        $file = array_map(
            static fn (array $c): array => ['payload' => $c['payload'], 'client_at' => '2026-10-12T21:15:00Z'],
            $batch['cards']
        );

        $bilan = $this->pack->sync($this->repo, $file, $this->porte('Porte A'));

        self::assertSame(3, $bilan['applied']);
        self::assertSame(0, $bilan['disputed']);
        self::assertSame(0, $bilan['rejected']);
    }

    public function testUnDoublonEntreDeuxPortesHorsLigneDevientUnLitige(): void
    {
        $batch = $this->repo->createBatch('Soiree', 2);
        $carteCopiee = $batch['cards'][0];

        $porteA = $this->porte('Porte A');
        $porteB = $this->porte('Porte B');

        // Les deux portes ont admis la meme carte pendant la coupure : l'une a vu
        // l'original, l'autre la photo. Aucune ne pouvait le savoir sur le moment.
        $this->pack->sync($this->repo, [
            ['payload' => $carteCopiee['payload'], 'client_at' => '2026-10-12T21:10:00Z'],
        ], $porteA);

        $bilanB = $this->pack->sync($this->repo, [
            ['payload' => $carteCopiee['payload'], 'client_at' => '2026-10-12T21:12:00Z'],
        ], $porteB);

        self::assertSame(0, $bilanB['applied']);
        self::assertSame(1, $bilanB['disputed'], 'Le second passage doit etre signale');

        $litiges = $this->repo->disputes();

        self::assertCount(1, $litiges);
        self::assertSame($carteCopiee['uid'], $litiges[0]['uid']);
    }

    public function testLeLitigeConserveLHeureEtLaPorteDeChaquePassage(): void
    {
        $batch = $this->repo->createBatch('Soiree', 1);
        $carte = $batch['cards'][0];

        $this->pack->sync($this->repo, [['payload' => $carte['payload'], 'client_at' => '2026-10-12T21:10:00Z']], $this->porte('Porte A'));
        $this->pack->sync($this->repo, [['payload' => $carte['payload'], 'client_at' => '2026-10-12T21:12:00Z']], $this->porte('Porte B'));

        $scans = $this->repo->scansForUid($carte['uid']);
        $portes = array_column($scans, 'device_label');

        self::assertContains('Porte A', $portes);
        self::assertContains('Porte B', $portes);

        // L'heure du telephone est ce qui permet de savoir qui s'est presente en premier.
        $heures = array_filter(array_column($scans, 'client_at'));
        self::assertContains('2026-10-12T21:10:00Z', $heures);
        self::assertContains('2026-10-12T21:12:00Z', $heures);
    }

    public function testUneCarteFabriqueeDansLaFileEstRefuseeSansCasserLeReste(): void
    {
        $batch = $this->repo->createBatch('Soiree', 2);

        $bilan = $this->pack->sync($this->repo, [
            ['payload' => $batch['cards'][0]['payload'], 'client_at' => '2026-10-12T21:10:00Z'],
            ['payload' => 'SCN1A.ABCDEFGH12345678.ZZZZZZZZZZZZZZZZ'],
            ['payload' => $batch['cards'][1]['payload'], 'client_at' => '2026-10-12T21:11:00Z'],
        ], $this->porte('Porte A'));

        self::assertSame(2, $bilan['applied'], 'Les cartes valides doivent passer malgre l intrus');
        self::assertSame(1, $bilan['rejected']);
    }

    /**
     * Non-regression : le meme telephone qui renvoie deux fois sa file.
     *
     * Le scanner pose un verrou pour eviter ce cas (deux evenements 'online' et
     * 'visibilitychange' peuvent partir ensemble), mais le serveur doit rester
     * coherent si un rejeu passe malgre tout : la carte reste utilisee une seule
     * fois, et le doublon est trace comme litige plutot que compte comme entree.
     */
    public function testUnRejeuDeLaMemeFileNeCompteQuUneEntree(): void
    {
        $batch = $this->repo->createBatch('Soiree', 1);
        $porte = $this->porte('Porte A');

        $file = [['payload' => $batch['cards'][0]['payload'], 'client_at' => '2026-10-12T21:10:00Z']];

        $premier = $this->pack->sync($this->repo, $file, $porte);
        $rejeu = $this->pack->sync($this->repo, $file, $porte);

        self::assertSame(1, $premier['applied']);
        self::assertSame(0, $rejeu['applied'], 'Un rejeu ne doit jamais compter une seconde entree');

        self::assertSame(
            1,
            (int) $this->pdo->query("SELECT COUNT(*) FROM cards WHERE status = 'used'")->fetchColumn()
        );
    }

    public function testLeLitigeNeDivulguePasLIpNiLIdentifiantInterne(): void
    {
        $batch = $this->repo->createBatch('Soiree', 1);
        $carte = $batch['cards'][0];

        $this->pack->sync($this->repo, [['payload' => $carte['payload']]], $this->porte('Porte A'), '10.0.0.5');
        $bilan = $this->pack->sync($this->repo, [['payload' => $carte['payload']]], $this->porte('Porte B'), '10.0.0.6');

        $json = (string) json_encode($bilan['entries']);

        // Le telephone du vigile n'a besoin que de l'heure et du nom de la porte.
        self::assertStringNotContainsString('10.0.0.5', $json);
        self::assertStringNotContainsString('device_id', $json);
        self::assertSame('Porte A', $bilan['entries'][0]['first_scan']['device']);
    }

    public function testUneFileVideNeCassePas(): void
    {
        $bilan = $this->pack->sync($this->repo, [], $this->porte('Porte A'));

        self::assertSame(0, $bilan['applied']);
        self::assertSame([], $bilan['entries']);
    }

    public function testLesEntreesMalFormeesSontIgnorees(): void
    {
        $bilan = $this->pack->sync($this->repo, [
            ['payload' => ''],
            ['client_at' => '2026-10-12T21:10:00Z'],
        ], $this->porte('Porte A'));

        self::assertSame(0, $bilan['applied']);
        self::assertSame(2, $bilan['rejected']);
    }

    public function testLesScansHorsLigneSontMarquesCommeTels(): void
    {
        $batch = $this->repo->createBatch('Soiree', 1);

        $this->pack->sync($this->repo, [
            ['payload' => $batch['cards'][0]['payload'], 'client_at' => '2026-10-12T21:10:00Z'],
        ], $this->porte('Porte A'));

        $scans = $this->repo->scansForUid($batch['cards'][0]['uid']);

        self::assertSame(1, (int) $scans[0]['was_offline']);
    }
}
