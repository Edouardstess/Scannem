<?php

declare(strict_types=1);

namespace Scannem;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Cartes et journal des scans.
 *
 * Le coeur du systeme tient dans redeem() : c'est la que se joue la difference
 * entre un controle qui laisse passer les copies et un controle qui tient.
 */
final class CardRepository
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly Token $token,
    ) {
    }

    /**
     * Cree un lot de cartes et renvoie les payloads a imprimer.
     *
     * @return array{batch_id:int, cards:list<array{uid:string, payload:string}>}
     */
    public function createBatch(string $name, int $quantity, ?string $eventDate = null): array
    {
        if ($quantity < 1) {
            throw new RuntimeException('La quantite doit etre au moins 1.');
        }

        if ($quantity > 200000) {
            throw new RuntimeException('Lot trop grand : 200000 cartes maximum.');
        }

        $now = Db::now();
        $keyId = $this->token->activeKeyId();

        // Ici la transaction paie franchement, a l'inverse du chemin de scan :
        // des milliers d'INSERT groupes en une seule transaction evitent autant
        // de validations disque, et la generation d'un lot se fait a froid, sans
        // personne qui attend a la porte.
        return Db::transaction($this->pdo, function () use ($name, $eventDate, $quantity, $now, $keyId): array {
            $this->pdo->prepare(
                'INSERT INTO batches (name, event_date, quantity, created_at) VALUES (?, ?, ?, ?)'
            )->execute([$name, $eventDate, $quantity, $now]);

            $batchId = (int) $this->pdo->lastInsertId();

            $insert = $this->pdo->prepare(
                'INSERT INTO cards (uid, batch_id, key_id, status, created_at)
                 VALUES (?, ?, ?, \'active\', ?)'
            );

            $cards = [];

            for ($i = 0; $i < $quantity; $i++) {
                // L'index unique sur cards.uid est le filet de securite : meme si le
                // generateur produisait deux fois la meme valeur, la base refuserait.
                $attempts = 0;
                while (true) {
                    $uid = Token::newUid();
                    try {
                        $insert->execute([$uid, $batchId, $keyId, $now]);
                        break;
                    } catch (PDOException $e) {
                        if (!$this->isUniqueViolation($e) || ++$attempts > 5) {
                            throw $e;
                        }
                    }
                }

                $cards[] = ['uid' => $uid, 'payload' => $this->token->build($uid, $keyId)];
            }

            return ['batch_id' => $batchId, 'cards' => $cards];
        });
    }

    /**
     * Consomme une carte. C'est l'operation qui decide qui entre.
     *
     * L'UPDATE conditionnel est atomique : le moteur garantit qu'une seule
     * transaction peut faire passer une ligne de 'active' a 'used'. Si deux portes
     * scannent la meme carte copiee au meme instant, exactement une obtient
     * rowCount() === 1 et l'autre repart avec 0.
     *
     * Un SELECT suivi d'un UPDATE laisserait passer les deux : entre la lecture et
     * l'ecriture, les deux processus voient la carte encore active. C'est l'erreur
     * classique, et elle annule tout l'interet du systeme.
     *
     * L'UPDATE et l'ecriture au journal tiennent dans UNE transaction, pour deux
     * raisons. D'integrite : si le processus mourait entre les deux, la carte
     * serait consommee sans aucune trace de qui est entre, et la personne
     * suivante se ferait refuser sans explication possible. De debit : sous
     * SQLite chaque ecriture prend le verrou global de la base, une transaction
     * ne le prend qu'une fois au lieu de deux.
     *
     * L'appel est relancable : un echec ne valide rien, donc Db::retryOnLock()
     * peut le rejouer sans risque de consommer deux fois la meme carte.
     *
     * @return array{result:string, card:?array<string,mixed>, first_scan:?array<string,mixed>}
     */
    public function redeem(
        string $payload,
        ?int $deviceId = null,
        ?string $clientAt = null,
        bool $wasOffline = false,
        ?string $ip = null,
    ): array {
        $check = $this->token->verify($payload);

        if (!$check['ok']) {
            // Signature invalide : on n'a meme pas besoin de toucher a la table cards.
            $this->logScan(null, $deviceId, ScanResult::FORGED, $clientAt, $ip, $wasOffline, $check['reason']);

            return ['result' => ScanResult::FORGED, 'card' => null, 'first_scan' => null];
        }

        $uid = (string) $check['uid'];

        // Volontairement en autocommit, sans transaction englobante.
        //
        // Envelopper cet UPDATE et l'ecriture au journal dans une transaction
        // paraissait plus propre, mais la mesure dit le contraire : sous SQLite
        // tous les ecrivains se serialisent sur un verrou global, et toute
        // transaction allonge la section critique. A vingt portes simultanees,
        // le debit tombait de ~1300 a ~120 scans/s (et a ~50 avec BEGIN
        // IMMEDIATE, qui prend le verrou encore plus tot). L'autocommit donne
        // les sections critiques les plus courtes possibles.
        //
        // Ce que la transaction devait proteger : un plantage entre l'UPDATE et
        // l'INSERT au journal. Le risque est en realite mineur, parce que cet
        // UPDATE ecrit lui-meme used_at ET used_by_device sur la carte : l'heure
        // et la porte survivent au plantage, seule la ligne de journal manque.
        // firstAdmission() sait retomber sur la carte dans ce cas.
        $update = $this->pdo->prepare(
            "UPDATE cards
                SET status = 'used', used_at = ?, used_by_device = ?
              WHERE uid = ? AND status = 'active'"
        );
        $update->execute([Db::now(), $deviceId, $uid]);

        if ($update->rowCount() === 1) {
            $this->logScan($uid, $deviceId, ScanResult::ADMITTED, $clientAt, $ip, $wasOffline);

            return [
                'result' => ScanResult::ADMITTED,
                'card' => $this->findByUid($uid),
                'first_scan' => null,
            ];
        }

        // Zero ligne modifiee : deja utilisee, revoquee, ou inexistante.
        $card = $this->findByUid($uid);

        if ($card === null) {
            // Signature valide mais carte absente : lot supprime, ou base restauree
            // depuis une sauvegarde anterieure a l'impression. Anormal, a tracer.
            $this->logScan($uid, $deviceId, ScanResult::UNKNOWN, $clientAt, $ip, $wasOffline);

            return ['result' => ScanResult::UNKNOWN, 'card' => null, 'first_scan' => null];
        }

        $result = $card['status'] === 'revoked' ? ScanResult::REVOKED : ScanResult::ALREADY_USED;

        $this->logScan($uid, $deviceId, $result, $clientAt, $ip, $wasOffline);

        return [
            'result' => $result,
            'card' => $card,
            'first_scan' => $result === ScanResult::ALREADY_USED ? $this->firstAdmission($uid) : null,
        ];
    }

    /**
     * Verifie une carte sans la consommer.
     *
     * Sert au controle prealable et aux tests de materiel. Ne doit jamais etre
     * utilise a la porte : une verification qui ne consomme pas laisse entrer
     * autant de copies qu'on veut.
     *
     * @return array{result:string, card:?array<string,mixed>}
     */
    public function peek(string $payload): array
    {
        $check = $this->token->verify($payload);

        if (!$check['ok']) {
            return ['result' => ScanResult::FORGED, 'card' => null];
        }

        $card = $this->findByUid((string) $check['uid']);

        if ($card === null) {
            return ['result' => ScanResult::UNKNOWN, 'card' => null];
        }

        $result = match ($card['status']) {
            'active' => ScanResult::ADMITTED,
            'revoked' => ScanResult::REVOKED,
            default => ScanResult::ALREADY_USED,
        };

        return ['result' => $result, 'card' => $card];
    }

    /** @return array<string,mixed>|null */
    public function findByUid(string $uid): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cards WHERE uid = ?');
        $stmt->execute([$uid]);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Premiere admission enregistree pour une carte : l'heure et la porte.
     *
     * C'est ce que le vigile lit quand il refuse quelqu'un — sans cette
     * information, « deja utilisee » est invérifiable face a la personne.
     *
     * D'ou le repli sur la carte elle-meme quand le journal ne dit rien : son
     * UPDATE d'invalidation a ecrit used_at et used_by_device dans le meme geste
     * atomique, donc l'heure et la porte sont toujours recuperables, meme si le
     * processus est mort avant d'ecrire au journal.
     */
    public function firstAdmission(string $uid): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT s.*, d.label AS device_label
               FROM scans s
          LEFT JOIN devices d ON d.id = s.device_id
              WHERE s.uid = ? AND s.result = ?
           ORDER BY s.server_at ASC, s.id ASC
              LIMIT 1"
        );
        $stmt->execute([$uid, ScanResult::ADMITTED]);

        $row = $stmt->fetch();

        if ($row !== false) {
            return $row;
        }

        return $this->admissionDepuisLaCarte($uid);
    }

    /**
     * Reconstruit l'admission a partir de la ligne de carte, journal absent.
     *
     * @return array<string,mixed>|null
     */
    private function admissionDepuisLaCarte(string $uid): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT c.used_at, c.used_by_device, d.label AS device_label
               FROM cards c
          LEFT JOIN devices d ON d.id = c.used_by_device
              WHERE c.uid = ? AND c.used_at IS NOT NULL"
        );
        $stmt->execute([$uid]);

        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return [
            'uid' => $uid,
            'device_id' => $row['used_by_device'],
            'device_label' => $row['device_label'],
            'result' => ScanResult::ADMITTED,
            'server_at' => $row['used_at'],
            'client_at' => null,
            'ip' => null,
            'was_offline' => 0,
            'note' => 'Reconstruit depuis la carte : ligne de journal absente.',
        ];
    }

    /**
     * Annule une carte. Une carte deja utilisee reste 'used' : on ne reecrit pas
     * l'historique, la personne est deja entree.
     */
    public function revoke(string $uid): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE cards SET status = 'revoked' WHERE uid = ? AND status = 'active'"
        );
        $stmt->execute([$uid]);

        return $stmt->rowCount() === 1;
    }

    /** Remet une carte annulee en circulation. */
    public function restore(string $uid): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE cards SET status = 'active' WHERE uid = ? AND status = 'revoked'"
        );
        $stmt->execute([$uid]);

        return $stmt->rowCount() === 1;
    }

    public function logScan(
        ?string $uid,
        ?int $deviceId,
        string $result,
        ?string $clientAt = null,
        ?string $ip = null,
        bool $wasOffline = false,
        ?string $note = null,
    ): void {
        $this->pdo->prepare(
            'INSERT INTO scans (uid, device_id, result, server_at, client_at, ip, was_offline, note)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $uid,
            $deviceId,
            $result,
            Db::now(),
            $clientAt,
            $ip,
            $wasOffline ? 1 : 0,
            $note,
        ]);
    }

    /**
     * Litiges : cartes admises hors-ligne par plusieurs appareils.
     *
     * Ce sont les fraudes que le mode hors-ligne n'a pas pu empecher sur le moment.
     * On ne peut que les constater apres coup, avec l'heure et la porte de chacune.
     *
     * @return list<array<string,mixed>>
     */
    public function disputes(int $limit = 200): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT uid, COUNT(*) AS tentatives, MIN(server_at) AS premier, MAX(server_at) AS dernier
               FROM scans
              WHERE result IN (?, ?) AND uid IS NOT NULL
           GROUP BY uid
             HAVING SUM(CASE WHEN result = ? THEN 1 ELSE 0 END) > 0
           ORDER BY dernier DESC
              LIMIT $limit"
        );
        $stmt->execute([ScanResult::ADMITTED, ScanResult::DISPUTED, ScanResult::DISPUTED]);

        $rows = $stmt->fetchAll();

        foreach ($rows as $i => $row) {
            $rows[$i]['scans'] = $this->scansForUid((string) $row['uid']);
        }

        return $rows;
    }

    /** @return list<array<string,mixed>> */
    public function scansForUid(string $uid): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT s.*, d.label AS device_label
               FROM scans s
          LEFT JOIN devices d ON d.id = s.device_id
              WHERE s.uid = ?
           ORDER BY s.server_at ASC, s.id ASC"
        );
        $stmt->execute([$uid]);

        return $stmt->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function recentScans(int $limit = 100): array
    {
        $limit = max(1, min(1000, $limit));

        $stmt = $this->pdo->query(
            "SELECT s.*, d.label AS device_label
               FROM scans s
          LEFT JOIN devices d ON d.id = s.device_id
           ORDER BY s.id DESC
              LIMIT $limit"
        );

        return $stmt === false ? [] : $stmt->fetchAll();
    }

    /** @return array<string,int> */
    public function batchStats(int $batchId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT status, COUNT(*) AS n FROM cards WHERE batch_id = ? GROUP BY status'
        );
        $stmt->execute([$batchId]);

        $stats = ['active' => 0, 'used' => 0, 'revoked' => 0];

        foreach ($stmt->fetchAll() as $row) {
            $stats[(string) $row['status']] = (int) $row['n'];
        }

        $stats['total'] = array_sum($stats);

        return $stats;
    }

    /** @return list<array<string,mixed>> */
    public function batches(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM batches ORDER BY id DESC');

        return $stmt === false ? [] : $stmt->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function batch(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM batches WHERE id = ?');
        $stmt->execute([$id]);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Cartes d'un lot, avec leur payload reconstruit pour l'impression.
     *
     * Les payloads ne sont jamais stockes : on les recalcule a partir de l'uid et
     * de la clef. Une base volee sans le fichier de configuration ne donne donc
     * aucun QR utilisable.
     *
     * @return list<array<string,mixed>>
     */
    public function cardsOfBatch(int $batchId, bool $withPayload = false): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cards WHERE batch_id = ? ORDER BY id ASC');
        $stmt->execute([$batchId]);

        $rows = $stmt->fetchAll();

        if ($withPayload) {
            foreach ($rows as $i => $row) {
                $rows[$i]['payload'] = $this->token->build((string) $row['uid'], (string) $row['key_id']);
            }
        }

        return $rows;
    }

    private function isUniqueViolation(PDOException $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'unique') || str_contains($message, 'duplicate');
    }
}
