<?php

declare(strict_types=1);

/**
 * POST /api/enroll
 *
 * Seule route accessible sans jeton d'appareil : elle echange un code
 * d'enrolement a usage unique contre un jeton permanent.
 *
 * Entree : { "code": "A1B2-C3D4-E5F6" }
 * Sortie : { "ok": true, "token": "...", "label": "Porte A" }
 */

use Scannem\App;
use Scannem\Http;
use Scannem\RateLimiter;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

Http::requireMethod('POST');

$app = App::boot();

// Quota serre : l'enrolement est la seule porte ouverte, on limite fortement
// les tentatives pour qu'un code a 48 bits ne puisse pas etre devine.
$ip = Http::clientIp();
$limiter = $app->limiter();

if ($ip !== null && !$limiter->allow('enroll:' . $ip, 10)) {
    header('Retry-After: ' . $limiter->retryAfter());
    Http::error('Trop de tentatives. Reessaie dans une minute.', 429, 'rate_limited');
}

$body = Http::body();
$code = isset($body['code']) && is_string($body['code']) ? $body['code'] : '';

if ($code === '' || strlen($code) > 64) {
    Http::error('Code absent.', 422, 'bad_code');
}

try {
    $device = $app->auth()->enrollDevice($code);
} catch (RuntimeException $e) {
    Http::error($e->getMessage(), 403, 'enroll_failed');
}

Http::json([
    'ok' => true,
    'token' => $device['token'],
    'device_id' => $device['device_id'],
    'label' => $device['label'],
]);
