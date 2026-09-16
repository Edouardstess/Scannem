<?php

declare(strict_types=1);

/**
 * POST /api/sync
 *
 * Rejoue la file d'attente d'un telephone qui a travaille hors-ligne.
 *
 * Le serveur reste seul juge : chaque entree repasse par le meme UPDATE atomique
 * que les scans en ligne. Une entree admise sur le telephone mais refusee ici
 * signifie qu'une autre porte avait deja consomme la carte. On ne peut plus rien
 * empecher a ce stade, alors on documente : le litige remonte dans l'admin avec
 * l'heure et la porte de chaque passage.
 *
 * Entree : { "queue": [ { "payload": "...", "client_at": "..." }, ... ] }
 */

use Scannem\App;
use Scannem\Http;

require __DIR__ . '/bootstrap.php';

Http::requireMethod('POST');

$app = App::boot();
$device = $app->requireDevice();

$body = Http::body();
$queue = isset($body['queue']) && is_array($body['queue']) ? $body['queue'] : [];

if ($queue === []) {
    Http::json(['ok' => true, 'applied' => 0, 'disputed' => 0, 'rejected' => 0, 'entries' => []]);
}

// La file d'un poste de controle sur une soiree ne depasse pas quelques milliers
// d'entrees. Au-dela, c'est une anomalie : on decoupe plutot que de tout avaler.
if (count($queue) > 2000) {
    Http::error('File trop longue, envoie-la par tranches de 2000.', 413, 'queue_too_long');
}

// Chaque entree est rejouable individuellement (UPDATE conditionnel unique), on
// enveloppe donc la file entiere : une contention passagere ne doit pas obliger
// le telephone a tout renvoyer, ce qui creerait de faux litiges.
$outcome = \Scannem\Db::retryOnLock(static fn (): array => $app->offlinePack()->sync(
    $app->cards(),
    $queue,
    (int) $device['id'],
    Http::clientIp()
));

Http::json(['ok' => true] + $outcome);
