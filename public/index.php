<?php

declare(strict_types=1);

/**
 * Point d'entree unique.
 *
 * Le serveur integre de PHP (php -S) sert les fichiers statiques tout seul et
 * n'appelle ce script que pour les chemins sans fichier correspondant. En
 * production, la racine web pointe sur ce dossier et tout passe par ici.
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = is_string($path) ? rtrim($path, '/') : '';

if ($path === '') {
    $path = '/';
}

$root = dirname(__DIR__);

// --- API ---------------------------------------------------------------------
if (str_starts_with($path, '/api/')) {
    $route = basename($path);
    $file = $root . '/app/api/' . $route . '.php';

    // basename() empeche toute remontee de repertoire, et la liste blanche
    // garantit qu'on n'expose que les routes prevues.
    if (in_array($route, ['redeem', 'verify', 'enroll', 'pack', 'sync'], true) && is_file($file)) {
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

// --- Scanner -----------------------------------------------------------------
if ($path === '/scan' || $path === '/') {
    header('Location: /scan/');
    exit;
}

// --- Lien court imprime sur la carte ----------------------------------------
// Permet d'imprimer une URL plutot que le code brut si tu le souhaites un jour :
// le scanner reconnait les deux formes (voir Token::normalize).
if (str_starts_with($path, '/s/')) {
    header('Location: /scan/?code=' . rawurlencode(substr($path, 3)));
    exit;
}

http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><meta charset="utf-8"><title>Introuvable</title>'
    . '<p style="font:16px system-ui;padding:40px">Page introuvable. '
    . '<a href="/scan/">Scanner</a> &middot; <a href="/admin/">Administration</a></p>';
