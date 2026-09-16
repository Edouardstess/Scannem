<?php

declare(strict_types=1);

/**
 * POST /api/redeem
 *
 * La route de la porte. Elle CONSOMME la carte : c'est ce qui rend les copies
 * inutiles. Ne jamais la remplacer par /api/verify a l'entree, sous peine de
 * laisser passer autant de copies qu'il s'en presente.
 *
 * Entree  : { "payload": "SCN1A....", "client_at": "2026-09-16T21:30:00Z" }
 * Sortie  : { "ok": true, "result": "admitted", "label": "...", "color": "green" }
 */

use Scannem\App;
use Scannem\Http;
use Scannem\ScanResult;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

Http::requireMethod('POST');

$app = App::boot();
$device = $app->requireDevice();

$body = Http::body();
$payload = isset($body['payload']) && is_string($body['payload']) ? $body['payload'] : '';

if ($payload === '' || strlen($payload) > 256) {
    Http::error('Payload absent ou invalide.', 422, 'bad_payload');
}

$outcome = $app->cards()->redeem(
    $payload,
    (int) $device['id'],
    Http::clientAt($body['client_at'] ?? null),
    false,
    Http::clientIp()
);

$response = [
    'ok' => true,
    'result' => $outcome['result'],
    'label' => ScanResult::label($outcome['result']),
    'color' => ScanResult::color($outcome['result']),
    'admitted' => $outcome['result'] === ScanResult::ADMITTED,
    'server_at' => \Scannem\Db::now(),
];

// Sur un refus pour carte deja utilisee, le vigile a besoin de savoir OU et QUAND
// elle a servi : c'est ce qui lui permet de trancher face a la personne.
if ($outcome['first_scan'] !== null) {
    $response['first_scan'] = [
        'at' => $outcome['first_scan']['server_at'],
        'device' => $outcome['first_scan']['device_label'] ?? 'appareil inconnu',
    ];
}

if ($outcome['card'] !== null && $outcome['card']['holder_label'] !== null) {
    $response['holder'] = $outcome['card']['holder_label'];
}

Http::json($response);
