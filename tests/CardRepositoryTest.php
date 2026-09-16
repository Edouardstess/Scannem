<?php

declare(strict_types=1);

namespace Scannem\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use Scannem\CardRepository;
use Scannem\ScanResult;
use Scannem\Tests\Support\TestDb;
use Scannem\Token;

final class CardRepositoryTest extends TestCase
{
    private PDO $pdo;
    private CardRepository $repo;
    private Token $token;

    protected function setUp(): void
    {
        $this->pdo = TestDb::fresh(TestDb::tempFile());
        $this->repo = TestDb::repository($this->pdo);
        $this->token = TestDb::token();
    }

    private function unePremierePayload(): string
    {
        $batch = $this->repo->createBatch('Test', 1);

        return $batch['cards'][0]['payload'];
    }

    public function testUnePremierePresentationEstAdmise(): void
    {
        $resultat = $this->repo->redeem($this->unePremierePayload());

        self::assertSame(ScanResult::ADMITTED, $resultat['result']);
        self::assertSame('used', $resultat['card']['status']);
        self::assertNotNull($resultat['card']['used_at']);
    }

    public function testLaDeuxiemePresentationEstRefusee(): void
    {
        $payload = $this->unePremierePayload();

        $this->repo->redeem($payload);
        $second = $this->repo->redeem($payload);

        self::assertSame(ScanResult::ALREADY_USED, $second['result']);
    }

    public function testUneCarteDejaUtiliseeIndiqueOuEtQuandElleAServi(): void
    {
        $payload = $this->unePremierePayload();

        $this->pdo->prepare('INSERT INTO devices (label, token_hash, active, created_at) VALUES (?, ?, 1, ?)')
            ->execute(['Porte A', str_repeat('a', 64), '2026-01-01T00:00:00Z']);
        $porteA = (int) $this->pdo->lastInsertId();

        $this->repo->redeem($payload, $porteA);
        $second = $this->repo->redeem($payload, null);

        self::assertSame(ScanResult::ALREADY_USED, $second['result']);
        self::assertNotNull($second['first_scan'], 'Le vigile doit voir le premier passage');
        self::assertSame('Porte A', $second['first_scan']['device_label']);
        self::assertNotEmpty($second['first_scan']['server_at']);
    }

    /**
     * Le chemin de scan ecrit sans transaction, pour ne pas etrangler le debit
     * quand plusieurs portes scannent ensemble. Consequence : un plantage entre
     * l'invalidation et l'ecriture au journal laisserait une carte consommee
     * sans ligne de journal.
     *
     * Ce n'est pas grave a condition que le vigile garde l'heure et la porte,
     * car c'est ce qu'il oppose a la personne qu'il refuse. L'UPDATE les ecrit
     * sur la carte dans le meme geste atomique : on verifie ici qu'ils sont bien
     * recuperes quand le journal est muet.
     */
    public function testLHeureEtLaPorteSurviventALaPerteDuJournal(): void
    {
        $batch = $this->repo->createBatch('Test', 1);
        $carte = $batch['cards'][0];

        $this->pdo->prepare('INSERT INTO devices (label, token_hash, active, created_at) VALUES (?, ?, 1, ?)')
            ->execute(['Porte A', str_repeat('a', 64), '2026-01-01T00:00:00Z']);
        $porteA = (int) $this->pdo->lastInsertId();

        $this->repo->redeem($carte['payload'], $porteA);

        // On simule le plantage : la carte reste consommee, le journal est efface.
        $this->pdo->exec('DELETE FROM scans');

        $second = $this->repo->redeem($carte['payload'], $porteA);

        self::assertSame(ScanResult::ALREADY_USED, $second['result']);
        self::assertNotNull($second['first_scan'], 'Le vigile doit garder de quoi justifier son refus');
        self::assertSame('Porte A', $second['first_scan']['device_label']);
        self::assertNotEmpty($second['first_scan']['server_at']);
    }

    public function testUneCarteFabriqueeEstRefusee(): void
    {
        $resultat = $this->repo->redeem('SCN1A.ABCDEFGH12345678.ZZZZZZZZZZZZZZZZ');

        self::assertSame(ScanResult::FORGED, $resultat['result']);
    }

    public function testUneSignatureValidePourUneCarteAbsenteEstSignalee(): void
    {
        // Signature authentique, mais la carte n'a jamais ete inscrite en base.
        $payload = $this->token->build(Token::newUid());

        $resultat = $this->repo->redeem($payload);

        self::assertSame(ScanResult::UNKNOWN, $resultat['result']);
    }

    public function testUneCarteAnnuleeEstRefusee(): void
    {
        $batch = $this->repo->createBatch('Test', 1);
        $carte = $batch['cards'][0];

        self::assertTrue($this->repo->revoke($carte['uid']));

        $resultat = $this->repo->redeem($carte['payload']);

        self::assertSame(ScanResult::REVOKED, $resultat['result']);
    }

    public function testOnNAnnulePasUneCarteDejaUtilisee(): void
    {
        $batch = $this->repo->createBatch('Test', 1);
        $carte = $batch['cards'][0];

        $this->repo->redeem($carte['payload']);

        // La personne est deja entree : annuler la carte ne la fait pas ressortir.
        self::assertFalse($this->repo->revoke($carte['uid']));
        self::assertSame('used', $this->repo->findByUid($carte['uid'])['status']);
    }

    public function testPeekNeConsommePasLaCarte(): void
    {
        $payload = $this->unePremierePayload();

        $premier = $this->repo->peek($payload);
        $second = $this->repo->peek($payload);

        self::assertSame(ScanResult::ADMITTED, $premier['result']);
        self::assertSame(ScanResult::ADMITTED, $second['result'], 'peek ne doit rien consommer');
        self::assertSame('active', $second['card']['status']);
    }

    public function testTousLesScansSontJournalisesRefusCompris(): void
    {
        $payload = $this->unePremierePayload();

        $this->repo->redeem($payload);
        $this->repo->redeem($payload);
        $this->repo->redeem('SCN1A.ABCDEFGH12345678.ZZZZZZZZZZZZZZZZ');

        $total = (int) $this->pdo->query('SELECT COUNT(*) FROM scans')->fetchColumn();

        self::assertSame(3, $total, 'Le journal doit contenir les refus, ils font la preuve');
    }

    public function testUnLotProduitDesUidTousDistincts(): void
    {
        $batch = $this->repo->createBatch('Soiree', 500);

        $uids = array_column($batch['cards'], 'uid');

        self::assertCount(500, $uids);
        self::assertCount(500, array_unique($uids));
        self::assertSame(500, $this->repo->batchStats($batch['batch_id'])['active']);
    }

    public function testLesPayloadsNeSontJamaisStockesEnBase(): void
    {
        $batch = $this->repo->createBatch('Test', 3);
        $payload = $batch['cards'][0]['payload'];

        $dump = (string) file_get_contents((string) $this->pdo->query('PRAGMA database_list')->fetch()['file']);

        self::assertStringNotContainsString(
            $payload,
            $dump,
            'Une base volee ne doit pas suffire a reconstituer des QR valides'
        );
    }

    public function testLesStatistiquesDeLotSuiventLesScans(): void
    {
        $batch = $this->repo->createBatch('Test', 10);

        $this->repo->redeem($batch['cards'][0]['payload']);
        $this->repo->redeem($batch['cards'][1]['payload']);
        $this->repo->revoke($batch['cards'][2]['uid']);

        $stats = $this->repo->batchStats($batch['batch_id']);

        self::assertSame(2, $stats['used']);
        self::assertSame(1, $stats['revoked']);
        self::assertSame(7, $stats['active']);
        self::assertSame(10, $stats['total']);
    }
}
