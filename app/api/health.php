<?php

declare(strict_types=1);

/**
 * GET /api/health[?deep=1]
 *
 * Le « defibrillateur » : maintenir le compte en vie et deceler une panne AVANT
 * l'evenement, pas pendant.
 *
 * Deux usages :
 *   - un cron chez l'hebergeur, pour que le compte gratuit ne soit pas desactive
 *     pour inactivite prolongee ;
 *   - un pre-chauffage par le scanner a son ouverture, pour que le vigile sache
 *     que le serveur repond avant d'avoir quelqu'un en face.
 *
 * VOLONTAIREMENT MINUSCULE. Sur un hebergement gratuit, chaque appel consomme le
 * quota de requetes et de processeur, et un depassement declenche une suspension
 * de 24 heures. Un defibrillateur trop zele provoquerait donc exactement la panne
 * qu'il est cense empecher. Par defaut : aucune connexion a la base, aucune
 * session, aucun autoload lourd.
 *
 * ?deep=1 ajoute une verification de la base. A reserver a une surveillance
 * espacee, pas au cron de maintien en vie.
 *
 * Aucune authentification : la route ne revele rien d'exploitable, et un cron
 * d'hebergeur ne sait pas presenter un jeton d'appareil. Elle reste soumise au
 * quota par IP pour ne pas devenir un levier d'attaque par deni de service.
 */

use Scannem\App;
use Scannem\Db;
use Scannem\Http;

require __DIR__ . '/bootstrap.php';

Http::requireMethod('GET');

$profond = isset($_GET['deep']) && $_GET['deep'] !== '0';

$reponse = [
    'ok' => true,
    'service' => 'scannem',
    'time' => Db::now(),
];

if (!$profond) {
    // Chemin par defaut : on ne touche a rien. C'est tout l'interet.
    Http::json($reponse);
}

// --- Verification approfondie ---------------------------------------------

$app = App::boot();

// Quota par IP : la route est publique, elle ne doit pas servir de levier.
$ip = Http::clientIp();

if ($ip !== null && !$app->limiter()->allow('health:' . $ip, 30)) {
    header('Retry-After: ' . $app->limiter()->retryAfter());
    Http::error('Trop de verifications depuis cette connexion.', 429, 'rate_limited');
}

$debut = microtime(true);

$pdo = $app->pdo();
$pdo->query('SELECT 1')->fetchColumn();

$actives = (int) $pdo->query("SELECT COUNT(*) FROM cards WHERE status = 'active'")->fetchColumn();
$utilisees = (int) $pdo->query("SELECT COUNT(*) FROM cards WHERE status = 'used'")->fetchColumn();

$reponse['database'] = [
    'ok' => true,
    'driver' => Db::driverOf($pdo),
    'latency_ms' => (int) round((microtime(true) - $debut) * 1000),
    'cards_active' => $actives,
    'cards_used' => $utilisees,
];

$reponse['php'] = PHP_VERSION;

Http::json($reponse);
