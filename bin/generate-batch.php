<?php

declare(strict_types=1);

/**
 * Generation d'un lot de cartes.
 *
 *     php bin/generate-batch.php --name="Soiree du 12" --qty=200
 *     php bin/generate-batch.php --name="Soiree" --qty=50 --date=2026-10-12 --png
 *
 * Produit la planche d'impression HTML et l'export CSV dans storage/exports/.
 */

use Scannem\App;
use Scannem\QrRenderer;

require dirname(__DIR__) . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script s'execute en ligne de commande uniquement.\n");
}

$args = [];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([a-z0-9-]+)(?:=(.*))?$/i', $arg, $m) === 1) {
        $args[$m[1]] = $m[2] ?? '1';
    }
}

$name = $args['name'] ?? '';
$quantity = (int) ($args['qty'] ?? 0);
$date = $args['date'] ?? null;

if ($name === '' || $quantity < 1) {
    exit(<<<TXT
Usage : php bin/generate-batch.php --name="Nom du lot" --qty=200 [--date=2026-10-12] [--png]

  --name   nom du lot, imprime sur chaque carte
  --qty    nombre de cartes
  --date   date de l'evenement, imprimee sur la carte (optionnel)
  --png    ecrit aussi un PNG par carte dans storage/qr/<lot>/

TXT);
}

$app = App::boot();
$cards = $app->cards();

echo "Generation de $quantity cartes pour \"$name\"...\n";

$started = microtime(true);
$batch = $cards->createBatch($name, $quantity, $date);
$elapsed = microtime(true) - $started;

printf("Lot #%d cree en %.2f s.\n", $batch['batch_id'], $elapsed);

$renderer = new QrRenderer();
$slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($name)) ?? 'lot';
$slug = trim((string) $slug, '-') . '-' . $batch['batch_id'];

$exports = \Scannem\Config::storagePath('exports');
if (!is_dir($exports)) {
    @mkdir($exports, 0700, true);
}

// Planche d'impression
$sheetFile = $exports . '/' . $slug . '-planche.html';
file_put_contents($sheetFile, $renderer->printSheet($batch['cards'], $name, $date));
echo "Planche d'impression : $sheetFile\n";

// Export CSV. Il contient les payloads : c'est un document sensible, au meme
// titre que les cartes elles-memes.
$csvFile = $exports . '/' . $slug . '-cartes.csv';
$handle = fopen($csvFile, 'w');

if ($handle !== false) {
    fputcsv($handle, ['uid', 'payload', 'lot', 'date'], ',', '"', '\\');

    foreach ($batch['cards'] as $card) {
        fputcsv($handle, [$card['uid'], $card['payload'], $name, $date ?? ''], ',', '"', '\\');
    }

    fclose($handle);
    @chmod($csvFile, 0600);
    echo "Export CSV : $csvFile\n";
}

if (isset($args['png'])) {
    $dir = \Scannem\Config::storagePath('qr/' . $slug);
    echo "Ecriture des PNG...\n";
    $renderer->writeBatch($batch['cards'], $dir);
    echo "PNG : $dir\n";
}

echo <<<TXT

Exemple de contenu de carte : {$batch['cards'][0]['payload']}

ATTENTION : la planche et le CSV valent les cartes elles-memes. Quiconque les
obtient peut imprimer des cartes qui passeront le controle (une seule fois
chacune, mais elles passeront). Ne les laisse pas trainer et supprime-les du
serveur une fois l'impression faite.


TXT;
