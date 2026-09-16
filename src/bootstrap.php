<?php

declare(strict_types=1);

/**
 * Amorce chargee automatiquement par Composer (autoload "files").
 *
 * Elle s'execute avant tout le reste, quel que soit le point d'entree : page
 * d'administration, route API, scanner ou ligne de commande. C'est le seul
 * endroit ou l'on peut fixer l'emplacement du dossier de donnees avant que
 * Config ne le resolve.
 *
 * Le fichier local qu'elle charge n'est PAS dans le depot : il appartient a
 * l'installation, pas au code.
 */

$local = dirname(__DIR__) . '/scannem-local.php';

if (is_file($local)) {
    /**
     * Doit renvoyer le chemin absolu du dossier de donnees, par exemple :
     *
     *     <?php return '/home/moncompte/scannem-donnees';
     *
     * Ce fichier ne contient qu'un chemin, jamais un secret : meme servi par le
     * serveur web il ne revele rien d'exploitable. Le secret de signature, lui,
     * vit dans le dossier designe — que l'on place hors de la racine web.
     */
    $chemin = require $local;

    if (is_string($chemin) && $chemin !== '' && !defined('SCANNEM_STORAGE_PATH')) {
        define('SCANNEM_STORAGE_PATH', rtrim($chemin, '/'));
    }
}
