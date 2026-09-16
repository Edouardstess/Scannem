<?php

declare(strict_types=1);

/**
 * Installation de Scannem.
 *
 *     php bin/install.php
 *     php bin/install.php --driver=mysql --db-name=scannem --db-user=root --db-pass=secret
 *
 * Cree le secret de signature, le schema de base et le premier compte
 * organisateur. Relancable sans danger : le secret existant n'est jamais ecrase.
 */

use Scannem\Auth;
use Scannem\Config;
use Scannem\Db;

require dirname(__DIR__) . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script s'execute en ligne de commande uniquement.\n");
}

/** @return array<string,string> */
function parseArgs(array $argv): array
{
    $out = [];

    foreach (array_slice($argv, 1) as $arg) {
        if (preg_match('/^--([a-z0-9-]+)(?:=(.*))?$/i', $arg, $m) === 1) {
            $out[$m[1]] = $m[2] ?? '1';
        }
    }

    return $out;
}

function prompt(string $question, bool $hidden = false): string
{
    echo $question;

    if ($hidden && DIRECTORY_SEPARATOR !== '\\') {
        shell_exec('stty -echo 2>/dev/null');
        $value = trim((string) fgets(STDIN));
        shell_exec('stty echo 2>/dev/null');
        echo "\n";

        return $value;
    }

    return trim((string) fgets(STDIN));
}

$args = parseArgs($argv);
$interactive = !isset($args['no-interaction']);

echo "\n=== Installation de Scannem ===\n\n";

// ---------------------------------------------------------------- Configuration

if (Config::exists()) {
    echo "Une configuration existe deja (" . Config::configFile() . ").\n";
    echo "Le secret de signature est CONSERVE : l'ecraser invaliderait toutes les\n";
    echo "cartes deja imprimees.\n\n";

    /** @var array<string,mixed> $values */
    $values = require Config::configFile();
} else {
    $values = [];

    // Le secret qui signe les QR. 32 octets tires du generateur cryptographique.
    // S'il fuite, n'importe qui peut fabriquer des cartes valides ; s'il est perdu,
    // toutes les cartes imprimees deviennent inutilisables.
    $values['keys'] = ['A' => bin2hex(random_bytes(32))];
    $values['active_key_id'] = 'A';

    echo "Secret de signature genere (32 octets).\n";
    echo "  -> SAUVEGARDE storage/config.php hors du serveur.\n";
    echo "  -> Sans lui, les cartes deja imprimees ne sont plus verifiables.\n\n";
}

$driver = $args['driver'] ?? ($interactive
    ? (prompt('Base de donnees [sqlite]/mysql : ') ?: 'sqlite')
    : 'sqlite');

$values['db_driver'] = $driver === 'mysql' ? 'mysql' : 'sqlite';

if ($values['db_driver'] === 'mysql') {
    $values['db_host'] = $args['db-host'] ?? ($interactive ? (prompt('Hote [127.0.0.1] : ') ?: '127.0.0.1') : '127.0.0.1');
    $values['db_port'] = (int) ($args['db-port'] ?? 3306);
    $values['db_name'] = $args['db-name'] ?? ($interactive ? (prompt('Base [scannem] : ') ?: 'scannem') : 'scannem');
    $values['db_user'] = $args['db-user'] ?? ($interactive ? (prompt('Utilisateur [root] : ') ?: 'root') : 'root');
    $values['db_pass'] = $args['db-pass'] ?? ($interactive ? prompt('Mot de passe : ', true) : '');
} else {
    $values['db_path'] = $args['db-path'] ?? Config::storagePath('scannem.sqlite');
    echo "Base SQLite : {$values['db_path']}\n";
}

if (isset($args['cookie-secure'])) {
    $values['cookie_secure'] = $args['cookie-secure'] !== '0';
}

Config::write($values);
echo "\nConfiguration ecrite dans " . Config::configFile() . " (chmod 600).\n";

// ------------------------------------------------------------------ Repertoires

foreach (['exports', 'qr'] as $dir) {
    $path = Config::storagePath($dir);
    if (!is_dir($path)) {
        @mkdir($path, 0700, true);
    }
}

// --------------------------------------------------------------------- Schema

$config = Config::load();
$pdo = Db::connect($config);
Db::migrate($pdo);

echo "Schema de base cree ou mis a jour.\n";

// ------------------------------------------------------------------ Protections

/**
 * Repli pour les hebergements mutualises ou l'on ne peut pas pointer la racine
 * web sur public/. Un .htaccess a la racine vaut mieux qu'une base de cartes
 * telechargeable, mais ca ne remplace pas une vraie configuration de vhost.
 */
$htaccess = Config::rootPath('.htaccess');

if (!is_file($htaccess)) {
    file_put_contents($htaccess, <<<'HTACCESS'
# Repli de securite : ideallement, la racine web pointe sur public/ et ce fichier
# est inutile. Tant que ce n'est pas le cas, il empeche au moins de telecharger
# la base de donnees et le secret de signature.

<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(storage|src|bin|tests|vendor|app)/ - [F,L]
    RewriteCond %{REQUEST_URI} !^/public/
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>

<FilesMatch "\.(sqlite|sqlite-wal|sqlite-shm|log|md|json|lock|dist)$">
    Require all denied
</FilesMatch>
HTACCESS);

    echo "Fichier .htaccess de repli ecrit a la racine.\n";
}

$storageHtaccess = Config::storagePath('.htaccess');

if (!is_file($storageHtaccess)) {
    file_put_contents($storageHtaccess, "Require all denied\nDeny from all\n");
}

// --------------------------------------------------------------- Premier compte

$auth = new Auth($pdo, $config);

if ($auth->adminCount() === 0) {
    $username = $args['admin-user'] ?? ($interactive ? (prompt("\nNom d'utilisateur organisateur [admin] : ") ?: 'admin') : 'admin');

    if (isset($args['admin-pass'])) {
        $password = $args['admin-pass'];
    } elseif ($interactive) {
        $password = prompt('Mot de passe (10 caracteres minimum) : ', true);
    } else {
        // Mot de passe genere plutot que valeur par defaut : un "admin/admin"
        // oublie en production annule tout le reste.
        $password = bin2hex(random_bytes(9));
        echo "\nMot de passe genere : $password\n";
    }

    try {
        $auth->createAdmin($username, $password);
        echo "Compte organisateur '$username' cree.\n";
    } catch (RuntimeException $e) {
        echo "Compte non cree : {$e->getMessage()}\n";
        echo "Relance l'installation pour reessayer.\n";
    }
} else {
    echo "Un compte organisateur existe deja.\n";
}

echo <<<TXT

=== Installation terminee ===

Etapes suivantes :

  1. Pointer la racine web du domaine sur le dossier public/
     (pas sur la racine du projet : storage/ contient le secret de signature)

  2. Generer un lot de cartes :
       php bin/generate-batch.php --name="Soiree du 12" --qty=200

  3. Lancer un serveur de test :
       php -S localhost:8000 -t public

  4. Enroler chaque telephone de controle depuis l'admin, puis ouvrir /scan/
     sur ce telephone (HTTPS obligatoire pour l'acces camera, sauf localhost)

A SAUVEGARDER : storage/config.php
Sans ce fichier, aucune carte deja imprimee ne peut plus etre verifiee.


TXT;
