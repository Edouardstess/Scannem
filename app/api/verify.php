<?php

declare(strict_types=1);

/**
 * POST /api/verify
 *
 * Verifie SANS consommer. Destine au controle prealable et aux tests de materiel.
 *
 * Attention : cette route ne doit pas servir a la porte. Une verification qui ne
 * consomme rien laisse entrer autant de copies qu'on veut, puisque la carte reste
 * active apres chaque passage.
 */

use Scannem\App;
use Scannem\Http;
use Scannem\ScanResult;

require __DIR__ . '/bootstrap.php';

Http::requireMethod('POST');

$app = App::boot();
$device = $app->requireDevice();

$body = Http::body();
$payload = isset($body['payload']) && is_string($body['payload']) ? $body['payload'] : '';

if ($payload === '' || strlen($payload) > 256) {
    Http::error('Payload absent ou invalide.', 422, 'bad_payload');
}

$outcome = $app->cards()->peek($payload);

// On trace aussi les verifications : un pic de "peek" sur des cartes inconnues
// est le signe d'un sondage malveillant.
$app->cards()->logScan(
    $outcome['card']['uid'] ?? null,
    (int) $device['id'],
    'peek:' . $outcome['result'],
    Http::clientAt($body['client_at'] ?? null),
    Http::clientIp()
);

Http::json([
    'ok' => true,
    'result' => $outcome['result'],
    'label' => ScanResult::label($outcome['result']),
    'color' => ScanResult::color($outcome['result']),
    'consumed' => false,
    'status' => $outcome['card']['status'] ?? null,
]);
