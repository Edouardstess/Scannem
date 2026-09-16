<?php

declare(strict_types=1);

/**
 * Filet de securite commun a toutes les routes API.
 *
 * Objectif unique : une route API ne doit JAMAIS repondre autre chose que du
 * JSON. Sans ce filet, une exception de base de donnees produit une page
 * d'erreur PHP : le vigile recoit un HTTP 500 illisible et ne sait pas si la
 * personne devant lui est entree ou non. C'est le pire resultat possible a une
 * porte, pire qu'un refus franc.
 *
 * Deuxieme role : ne rien divulguer. Une trace d'execution exposerait les
 * chemins du serveur, voire des fragments de configuration.
 */

use Scannem\Db;
use Scannem\Http;
use Scannem\ScanResult;

// Version de PHP, dependances, autoloader. Une panne a ce stade doit sortir en
// JSON comme le reste : scannem_amorcer s'en charge.
require dirname(__DIR__) . '/amorce.php';
scannem_amorcer(dirname(__DIR__, 2));

// Les erreurs partent dans le journal du serveur, jamais dans la reponse.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

/**
 * Journalise sans jamais interrompre la reponse.
 */
function scannem_log(string $message, ?Throwable $e = null): void
{
    $ligne = '[scannem] ' . $message;

    if ($e !== null) {
        $ligne .= ' | ' . $e::class . ': ' . $e->getMessage()
            . ' @ ' . $e->getFile() . ':' . $e->getLine();
    }

    error_log($ligne);
}

/**
 * Reponse unique en cas d'incident technique.
 *
 * On distingue deux cas, parce que le vigile doit reagir differemment :
 * - contention passagere  -> 503, il rescanne dans la seconde ;
 * - defaillance inconnue  -> 500 maitrise, il previent l'organisateur.
 *
 * Dans les deux cas la carte n'a pas ete consommee : rescanner est sans danger.
 */
function scannem_fail(Throwable $e): void
{
    if (headers_sent()) {
        return;
    }

    $contention = $e instanceof PDOException && Db::isLockContention($e);

    scannem_log($contention ? 'contention base de donnees' : 'erreur non rattrapee', $e);

    if ($contention) {
        header('Retry-After: 1');

        Http::json([
            'ok' => false,
            'result' => ScanResult::SERVER_BUSY,
            'label' => ScanResult::label(ScanResult::SERVER_BUSY),
            'color' => ScanResult::color(ScanResult::SERVER_BUSY),
            'admitted' => false,
            'consumed' => false,
            'retryable' => true,
            'message' => 'Serveur momentanement occupe. Rescanne la carte, elle n a pas ete utilisee.',
        ], 503);
    }

    Http::json([
        'ok' => false,
        'result' => 'server_error',
        'label' => 'ERREUR SERVEUR',
        'color' => 'red',
        'admitted' => false,
        'consumed' => false,
        'retryable' => false,
        'message' => 'Erreur interne. Previens l organisateur.',
    ], 500);
}

set_exception_handler(static function (Throwable $e): void {
    scannem_fail($e);
});

// Pas encore installe : on repond proprement en JSON. Le scanner sait alors que
// le serveur existe mais n'est pas pret, au lieu de recevoir une page d'erreur
// HTML qu'il interpreterait comme une panne reseau et qui le ferait basculer a
// tort en mode hors-ligne.
if (!\Scannem\Config::exists()) {
    Http::json([
        'ok' => false,
        'result' => 'not_installed',
        'label' => 'NON INSTALLE',
        'color' => 'red',
        'admitted' => false,
        'consumed' => false,
        'retryable' => false,
        'message' => 'Scannem n a pas encore ete installe sur ce serveur.',
    ], 503);
}

/**
 * Les erreurs PHP passent par le meme filet — mais pas n'importe lesquelles.
 *
 * Convertir TOUTES les erreurs en exceptions serait une faute : une simple
 * obsolescence signalee par une dependance (endroid/qr-code en declenche sur
 * PHP 8.4) suffirait alors a renvoyer un 500 au vigile en pleine entree. Une
 * remarque du moteur ne doit jamais coûter une entree.
 *
 * Les avis et obsolescences partent donc au journal et la requete continue.
 * Seules les vraies erreurs deviennent des exceptions.
 */
const SCANNEM_ERREURS_BENIGNES = E_DEPRECATED | E_USER_DEPRECATED | E_NOTICE | E_USER_NOTICE;

set_error_handler(static function (int $niveau, string $message, string $fichier, int $ligne): bool {
    if ((error_reporting() & $niveau) === 0) {
        return false;
    }

    if (($niveau & SCANNEM_ERREURS_BENIGNES) !== 0) {
        scannem_log(sprintf('avis PHP : %s @ %s:%d', $message, $fichier, $ligne));

        return true;
    }

    throw new ErrorException($message, 0, $niveau, $fichier, $ligne);
});

// Dernier rempart : erreur fatale que les gestionnaires ci-dessus ne voient pas
// (depassement de memoire, temps d'execution epuise). Sans ca, la reponse
// serait vide et le telephone basculerait en hors-ligne a tort.
register_shutdown_function(static function (): void {
    $erreur = error_get_last();

    if ($erreur === null || !in_array($erreur['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }

    if (headers_sent()) {
        return;
    }

    scannem_log('erreur fatale : ' . $erreur['message'] . ' @ ' . $erreur['file'] . ':' . $erreur['line']);

    Http::json([
        'ok' => false,
        'result' => 'server_error',
        'label' => 'ERREUR SERVEUR',
        'color' => 'red',
        'admitted' => false,
        'consumed' => false,
        'retryable' => false,
        'message' => 'Erreur interne. Previens l organisateur.',
    ], 500);
});
