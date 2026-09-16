<?php

declare(strict_types=1);

/**
 * GET /api/pack[?batch_id=N]
 *
 * Pack de secours a telecharger AVANT l'evenement, tant que le reseau est bon.
 *
 * Il ne contient que des empreintes : ni le secret de signature, ni les uid en
 * clair. Un telephone perdu ne permet donc pas de fabriquer des cartes.
 */

use Scannem\App;
use Scannem\Http;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

Http::requireMethod('GET');

$app = App::boot();
$app->requireDevice();

$batchId = isset($_GET['batch_id']) && is_numeric($_GET['batch_id']) ? (int) $_GET['batch_id'] : null;

$pack = $app->offlinePack()->build($batchId);

Http::json(['ok' => true] + $pack);
