<?php

declare(strict_types=1);

use Scannem\Url;

/**
 * Point d'entree unique.
 *
 * Le serveur integre de PHP (php -S) sert les fichiers statiques tout seul et
 * n'appelle ce script que pour les chemins sans fichier correspondant. En
 * production, la racine web pointe sur ce dossier et tout passe par ici.
 */

/**
 * Trouve la racine du projet en remontant jusqu'a vendor/autoload.php.
 *
 * Deux dispositions doivent fonctionner sans rien changer au code :
 *
 *   - developpement et hebergement correct : la racine web pointe sur public/,
 *     et le projet est le dossier parent ;
 *   - mutualise gratuit : la racine web est imposee (htdocs/), on y depose le
 *     contenu de public/ et les dossiers du projet cote a cote.
 *
 * Chercher vendor/ plutot que supposer un niveau de profondeur evite de dependre
 * de mod_rewrite, que certains hebergeurs restreignent — et une reecriture
 * absente laisserait le site inaccessible sans message clair.
 */
$root = __DIR__;

while (!is_file($root . '/vendor/autoload.php') && dirname($root) !== $root) {
    $root = dirname($root);
}

// Version de PHP, dependances, prefixe d'installation. Le 0 dit que ce fichier
// est pose a la racine servie : le prefixe est le dossier de ce script.
require $root . '/app/amorce.php';
scannem_amorcer($root, 0);

/**
 * Chemin demande, ramene a un chemin interne.
 *
 * Url::strip retire le prefixe d'installation : dans htdocs/scannem/, une visite
 * sur /scannem/admin devient /admin. Sans ca, aucune route ne correspondrait et
 * l'application repondrait sa propre page « introuvable » pour sa page d'accueil
 * — exactement le symptome d'une installation en sous-dossier.
 */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = Url::strip(is_string($path) ? rtrim($path, '/') : '');

// Appel direct du routeur (/index.php), au lieu du dossier. Rien ne l'interdit
// et un navigateur y arrive vite : c'est la meme page d'accueil.
if ($path === '' || $path === '/index.php') {
    $path = '/';
}

// --- API ---------------------------------------------------------------------
if (str_starts_with($path, '/api/')) {
    // Les deux ecritures sont acceptees : /api/redeem et /api/redeem.php.
    // La seconde est celle qu'utilise le scanner, parce qu'elle correspond a un
    // vrai fichier chez l'hebergeur et ne demande donc aucune reecriture. Apache
    // ne devine pas les routes comme le fait le serveur de test de PHP.
    $route = basename($path);

    if (str_ends_with($route, '.php')) {
        $route = substr($route, 0, -4);
    }

    $file = $root . '/app/api/' . $route . '.php';

    // basename() empeche toute remontee de repertoire, et la liste blanche
    // garantit qu'on n'expose que les routes prevues.
    if (in_array($route, ['redeem', 'verify', 'enroll', 'pack', 'sync', 'health'], true) && is_file($file)) {
        require $file;
        exit;
    }

    http_response_code(404);
    header('Content-Type: application/json');
    exit('{"ok":false,"error":"not_found"}');
}

// --- Admin -------------------------------------------------------------------
if ($path === '/admin' || str_starts_with($path, '/admin')) {
    require $root . '/app/admin/index.php';
    exit;
}

// --- Installateur ------------------------------------------------------------
// Sert install.php meme quand la racine web pointe sur public/ et que le fichier
// est demande sans son dossier. Le relais garde le prefixe d'installation.
if ($path === '/install.php' && is_file(__DIR__ . '/install.php')) {
    require __DIR__ . '/install.php';
    exit;
}

// --- Scanner -----------------------------------------------------------------
if ($path === '/scan' || $path === '/') {
    header('Location: ' . Url::to('/scan/'));
    exit;
}

// --- Lien court imprime sur la carte ----------------------------------------
// Permet d'imprimer une URL plutot que le code brut si tu le souhaites un jour :
// le scanner reconnait les deux formes (voir Token::normalize).
if (str_starts_with($path, '/s/')) {
    header('Location: ' . Url::to('/scan/?code=' . rawurlencode(substr($path, 3))));
    exit;
}

http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><meta charset="utf-8"><title>Introuvable</title>'
    . '<p style="font:16px system-ui;padding:40px">Page introuvable. '
    . '<a href="' . Url::to('/scan/') . '">Scanner</a> &middot; '
    . '<a href="' . Url::to('/admin/') . '">Administration</a></p>';
