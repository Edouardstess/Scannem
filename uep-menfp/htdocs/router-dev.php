<?php
/**
 * router-dev.php — Routeur pour le serveur de développement intégré à PHP.
 *
 *   php -S localhost:8000 router-dev.php
 *
 * Il reproduit ce que fait .htaccess : servir les fichiers statiques existants,
 * refuser les dossiers internes, et confier tout le reste à index.php.
 * Il ne s'exécute QUE sous le serveur intégré ; déposé sur un vrai serveur web,
 * il ne répond rien.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}

$chemin = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$chemin = '/' . ltrim(rawurldecode($chemin), '/');

// Dossiers internes : jamais servis, comme en production.
if (preg_match('#^/(config|core|controllers|middlewares|models|routes|views|database|storage|tools|docs)(/|$)#', $chemin)
    || preg_match('#(^|/)\.#', $chemin)
    || preg_match('#\.(sql|md|log|ini|bak|old|dist|lock)$#i', $chemin)
) {
    http_response_code(403);
    exit;
}

$fichier = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $chemin);

if ($chemin !== '/' && is_file($fichier)) {
    return false; // Le serveur intégré sert le fichier lui-même.
}

require __DIR__ . '/index.php';
