<?php

declare(strict_types=1);

namespace Scannem;

use PDO;

/**
 * Pack de secours telecharge par les telephones avant l'evenement.
 *
 * Ce que le pack contient : les empreintes SHA-256 tronquees des cartes actives.
 * Ce qu'il ne contient PAS : le secret HMAC, ni les uid en clair.
 *
 * C'est un choix deliberant. Mettre le secret dans le telephone permettrait de
 * verifier la signature hors-ligne, mais le premier vigile qui ouvre la console
 * de son navigateur repart avec la clef maitresse et peut fabriquer des cartes
 * a l'infini. Une liste d'empreintes ne permet rien de tel : au pire, un
 * telephone vole apprend combien de cartes existent.
 *
 * Limite assumee : hors-ligne, un telephone ne detecte que les doublons qu'il a
 * lui-meme scannes. Deux portes deconnectees ne peuvent pas se synchroniser,
 * c'est une impossibilite physique et non un manque d'implementation. Les
 * doublons passes pendant une coupure remontent en litige a la reconnexion.
 */
final class OfflinePack
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Construit le pack pour un lot, ou pour toutes les cartes actives.
     *
     * @return array{version:string, generated_at:string, batch_id:?int, count:int, fingerprints:list<string>}
     */
    public function build(?int $batchId = null): array
    {
        if ($batchId === null) {
            $stmt = $this->pdo->query("SELECT uid FROM cards WHERE status = 'active' ORDER BY id ASC");
            $rows = $stmt === false ? [] : $stmt->fetchAll();
        } else {
            $stmt = $this->pdo->prepare(
                "SELECT uid FROM cards WHERE batch_id = ? AND status = 'active' ORDER BY id ASC"
            );
            $stmt->execute([$batchId]);
            $rows = $stmt->fetchAll();
        }

        $fingerprints = [];

        foreach ($rows as $row) {
            $fingerprints[] = Token::fingerprint((string) $row['uid']);
        }

        // Tri : le telephone fait une recherche dichotomique, et l'ordre trie
        // empeche de deduire l'ordre d'emission des cartes.
        sort($fingerprints);

        $generatedAt = Db::now();

        return [
            // La version change des que le contenu change : le telephone sait
            // ainsi s'il travaille avec un pack perime.
            'version' => substr(hash('sha256', implode('', $fingerprints) . $generatedAt), 0, 16),
            'generated_at' => $generatedAt,
            'batch_id' => $batchId,
            'count' => count($fingerprints),
            'fingerprints' => $fingerprints,
        ];
    }

    /**
     * Rejoue la file d'attente d'un telephone revenu en ligne.
     *
     * Chaque entree repasse par redeem() : le serveur reste seul juge. Une entree
     * que le telephone avait admise hors-ligne mais que le serveur refuse signifie
     * qu'une autre porte avait deja consomme la carte. On en fait un litige, avec
     * l'heure et la porte de chaque passage, pour que l'organisateur puisse
     * trancher apres coup.
     *
     * @param list<array{payload?:string, client_at?:string}> $queue
     * @return array{applied:int, disputed:int, rejected:int, entries:list<array<string,mixed>>}
     */
    public function sync(CardRepository $cards, array $queue, ?int $deviceId, ?string $ip = null): array
    {
        $applied = 0;
        $disputed = 0;
        $rejected = 0;
        $entries = [];

        foreach ($queue as $item) {
            $payload = is_array($item) && isset($item['payload']) ? (string) $item['payload'] : '';
            $clientAt = is_array($item) && isset($item['client_at']) ? (string) $item['client_at'] : null;

            if ($payload === '') {
                $rejected++;
                continue;
            }

            $outcome = $cards->redeem($payload, $deviceId, $clientAt, true, $ip);

            if ($outcome['result'] === ScanResult::ADMITTED) {
                $applied++;
                $entries[] = [
                    'payload' => $payload,
                    'result' => ScanResult::ADMITTED,
                    'client_at' => $clientAt,
                ];
                continue;
            }

            if ($outcome['result'] === ScanResult::ALREADY_USED) {
                // Le telephone avait laisse entrer. Une autre porte aussi.
                // On ne peut plus l'empecher : on le documente.
                $disputed++;

                $uid = $outcome['card']['uid'] ?? null;

                $cards->logScan(
                    is_string($uid) ? $uid : null,
                    $deviceId,
                    ScanResult::DISPUTED,
                    $clientAt,
                    $ip,
                    true,
                    'Admis hors-ligne alors que la carte etait deja consommee ailleurs'
                );

                // On ne renvoie que l'heure et la porte : la ligne de scan brute
                // contient l'IP et l'identifiant interne de l'autre appareil, qui
                // n'ont rien a faire dans un telephone de vigile.
                $entries[] = [
                    'payload' => $payload,
                    'result' => ScanResult::DISPUTED,
                    'client_at' => $clientAt,
                    'first_scan' => $outcome['first_scan'] === null ? null : [
                        'at' => $outcome['first_scan']['server_at'],
                        'device' => $outcome['first_scan']['device_label'] ?? 'appareil inconnu',
                    ],
                ];
                continue;
            }

            $rejected++;
            $entries[] = [
                'payload' => $payload,
                'result' => $outcome['result'],
                'client_at' => $clientAt,
            ];
        }

        return [
            'applied' => $applied,
            'disputed' => $disputed,
            'rejected' => $rejected,
            'entries' => $entries,
        ];
    }
}
