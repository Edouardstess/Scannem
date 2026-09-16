<?php

declare(strict_types=1);

/**
 * Fabrique l'archive a envoyer sur un hebergement mutualise.
 *
 *     php bin/build-release.php
 *
 * Pourquoi ce script existe : les hebergements gratuits (ByetHost, InfinityFree)
 * n'offrent ni SSH ni Composer. Les dependances doivent donc partir deja
 * installees, et l'archive doit rester petite pour deux raisons :
 *
 *   - le gestionnaire de fichiers plafonne l'envoi a 10 Mo ;
 *   - le nombre de fichiers (inodes) est limite par compte, et les dependances
 *     de developpement en representent la quasi-totalite.
 *
 * Le script part donc d'un `composer install --no-dev`, puis elague ce qui ne
 * sert pas en production : tests, documentation, fixtures des bibliotheques.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script s'execute en ligne de commande uniquement.\n");
}

$racine = dirname(__DIR__);
$sortie = $racine . '/dist';
$travail = $sortie . '/scannem';

echo "\n=== Fabrication de l'archive Scannem ===\n\n";

// -------------------------------------------------------------- Nettoyage

function effacer(string $chemin): void
{
    if (is_link($chemin) || is_file($chemin)) {
        @unlink($chemin);

        return;
    }

    if (!is_dir($chemin)) {
        return;
    }

    foreach (scandir($chemin) ?: [] as $entree) {
        if ($entree !== '.' && $entree !== '..') {
            effacer($chemin . '/' . $entree);
        }
    }

    @rmdir($chemin);
}

effacer($sortie);

if (!mkdir($travail, 0755, true) && !is_dir($travail)) {
    exit("Impossible de creer $travail\n");
}

// ------------------------------------------------- Dependances de production

echo "Installation des dependances de production...\n";

$composer = trim((string) shell_exec('command -v composer 2>/dev/null'));

if ($composer === '') {
    exit("Composer est introuvable : impossible de construire l'archive.\n");
}

$cmd = sprintf(
    'cd %s && COMPOSER_ALLOW_SUPERUSER=1 %s install --no-dev --prefer-dist '
    . '--optimize-autoloader --no-interaction --no-progress 2>&1',
    escapeshellarg($racine),
    escapeshellarg($composer)
);

$log = (string) shell_exec($cmd);

if (!is_file($racine . '/vendor/autoload.php')) {
    exit("Echec de l'installation des dependances :\n" . $log . "\n");
}

echo "  fait.\n";

// ------------------------------------------------------------- Copie ciblee

/**
 * Dossiers et fichiers qui composent une installation fonctionnelle.
 *
 * `public` est absent volontairement : son CONTENU est copie a la racine de
 * l'archive (voir plus bas). Sur un mutualise gratuit, la racine web est imposee
 * (htdocs/) et ne peut pas pointer sur un sous-dossier. Poser le contenu de
 * public/ a plat evite de dependre d'une reecriture .htaccess, que certains
 * hebergeurs restreignent et dont l'absence rendrait le site muet.
 */
$aCopier = ['src', 'app', 'vendor', 'composer.json'];

/**
 * Ce qui n'a rien a faire en production. Chaque entree economise des inodes,
 * ressource reellement limitee sur les offres gratuites.
 */
$dossiersInutiles = [
    'test', 'tests', 'Tests', 'doc', 'docs', 'examples', '.github', 'bin',
    // Composer reutilise son cache de clones : les paquets gardent leur depot
    // git complet, soit une quinzaine de Mo d'historique parfaitement inutile
    // en production.
    '.git', '.svn', '.hg',
];

$fichiersInutiles = [
    '/\.md$/i', '/\.dist$/i', '/^\.gitignore$/', '/^\.gitattributes$/',
    '/^phpunit\.xml/', '/^\.editorconfig$/', '/^Makefile$/', '/\.neon$/',
    // endroid/qr-code embarque Noto Sans (15,7 Mo) pour les libelles sous le QR.
    // Scannem n'utilise aucun libelle : le texte lisible est mis en page par
    // QrRenderer::printSheet, en HTML. La police pese a elle seule plus que tout
    // le reste de l'archive.
    '/^noto_sans\.otf$/i',
];

$copies = 0;
$ignores = 0;

function copier(string $de, string $vers, array $dossiersInutiles, array $fichiersInutiles, bool $elaguer): void
{
    global $copies, $ignores;

    if (is_file($de)) {
        if (!is_dir(dirname($vers))) {
            mkdir(dirname($vers), 0755, true);
        }
        copy($de, $vers);
        $copies++;

        return;
    }

    if (!is_dir($de)) {
        return;
    }

    if (!is_dir($vers) && !mkdir($vers, 0755, true) && !is_dir($vers)) {
        return;
    }

    foreach (scandir($de) ?: [] as $entree) {
        if ($entree === '.' || $entree === '..') {
            continue;
        }

        $source = $de . '/' . $entree;

        // L'elagage ne s'applique qu'a vendor/ : le code de l'application doit
        // partir entier, y compris ce qui ressemble a de la documentation.
        if ($elaguer) {
            if (is_dir($source) && in_array($entree, $dossiersInutiles, true)) {
                $ignores++;
                continue;
            }

            if (is_file($source)) {
                foreach ($fichiersInutiles as $motif) {
                    if (preg_match($motif, $entree) === 1) {
                        $ignores++;
                        continue 2;
                    }
                }
            }
        }

        copier($source, $vers . '/' . $entree, $dossiersInutiles, $fichiersInutiles, $elaguer);
    }
}

echo "Copie des fichiers...\n";

foreach ($aCopier as $element) {
    copier(
        $racine . '/' . $element,
        $travail . '/' . $element,
        $dossiersInutiles,
        $fichiersInutiles,
        $element === 'vendor'
    );
}

// Le contenu de public/ va a la RACINE de l'archive : index.php, install.php,
// scan/ et admin/ se retrouvent directement dans htdocs/.
foreach (scandir($racine . '/public') ?: [] as $entree) {
    if ($entree === '.' || $entree === '..') {
        continue;
    }

    // public/.htaccess decrit la disposition « racine web sur public/ ». Ici la
    // disposition est l'autre : un .htaccess adapte est ecrit plus bas.
    if ($entree === '.htaccess') {
        continue;
    }

    copier(
        $racine . '/public/' . $entree,
        $travail . '/' . $entree,
        $dossiersInutiles,
        $fichiersInutiles,
        false
    );
}

/**
 * Points d'entree reels pour /api/... et /admin/.
 *
 * Apache ne devine pas les routes : sans fichier a l'emplacement demande, il
 * renvoie 404. Le serveur de test de PHP, lui, se replie sur index.php, ce qui
 * masque completement le probleme en developpement.
 *
 * On cree donc de vrais fichiers plutot que de dependre de mod_rewrite, dont
 * l'absence rendrait le scanner inutilisable sans message comprehensible.
 */
mkdir($travail . '/api', 0755, true);

/**
 * Le « 1 » passe a scannem_amorcer est la profondeur du relais sous la racine
 * servie. C'est ce qui permet a Scannem de savoir sous quel prefixe il est
 * installe : htdocs/ (prefixe vide) ou htdocs/scannem/ (prefixe /scannem). Sans
 * lui, toutes les URL produites repartiraient de la racine du site et le
 * scanner appellerait /api/redeem.php au lieu de /scannem/api/redeem.php.
 */
$relais = static fn (string $cible): string => "<?php\n\n"
    . "// Point d'entree reel : evite de dependre d'une reecriture .htaccess.\n"
    . "// Le 1 est la profondeur de ce fichier sous la racine servie ; il permet a\n"
    . "// Scannem de fonctionner aussi bien a la racine que dans un sous-dossier.\n"
    . "require dirname(__DIR__) . '/app/amorce.php';\n"
    . "scannem_amorcer(dirname(__DIR__), 1);\n"
    . "require dirname(__DIR__) . '/app/" . $cible . "';\n";

foreach (['redeem', 'verify', 'enroll', 'pack', 'sync', 'health'] as $route) {
    file_put_contents($travail . '/api/' . $route . '.php', $relais('api/' . $route . '.php'));
}

mkdir($travail . '/admin', 0755, true);
file_put_contents($travail . '/admin/index.php', $relais('admin/index.php'));

// Dossier de donnees, vide mais protege des le premier octet.
mkdir($travail . '/storage', 0700, true);
file_put_contents($travail . '/storage/.htaccess', "Require all denied\nDeny from all\n");
file_put_contents($travail . '/storage/index.html', '');

// Protection des dossiers de code : sur mutualise, la racine web est imposee et
// ces dossiers sont servis si on ne les refuse pas explicitement.
foreach (['src', 'app', 'vendor'] as $dir) {
    file_put_contents($travail . '/' . $dir . '/.htaccess', "Require all denied\nDeny from all\n");
}

// Protection de la racine. Aucune reecriture n'est necessaire : le contenu de
// public/ est deja a plat. Ce fichier ne fait que REFUSER l'acces aux dossiers
// de code et de donnees, ce qui ne demande pas mod_rewrite.
file_put_contents($travail . '/.htaccess', <<<'HTACCESS'
# Scannem — protection de l'arborescence.
#
# Le contenu de public/ est deja a la racine : aucune reecriture n'est requise,
# le site fonctionne meme si mod_rewrite est indisponible. Ce dossier peut etre
# htdocs/ ou un sous-dossier de htdocs/ : Scannem deduit son prefixe tout seul.
#
# Ces regles refusent l'acces au code et surtout aux donnees. Le fichier
# storage/config.php contient le secret qui signe les QR : il ne doit jamais
# etre telechargeable.

<FilesMatch "\.(sqlite|sqlite-wal|sqlite-shm|log|lock|dist)$">
    Require all denied
</FilesMatch>

# Empeche de lister le contenu des dossiers.
Options -Indexes

DirectoryIndex index.php index.html

<IfModule mod_rewrite.c>
    RewriteEngine On

    RewriteRule ^(storage|src|app|vendor|bin|tests)/ - [F,L]

    # Confort, jamais une obligation : /api/redeem sans extension et les liens
    # courts /s/CODE passent par le routeur. Tout ce dont le scanner a besoin
    # existe deja sous forme de fichier, donc l'absence de mod_rewrite ne casse
    # rien. Cible relative et pas de RewriteBase : la regle vaut telle quelle
    # dans un sous-dossier.
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ index.php [L]
</IfModule>
HTACCESS);

/**
 * Lanceur Windows.
 *
 * Il existe parce que la marche a franchir en local n'est pas technique, elle
 * est administrative : WampServer est souvent livre avec un PHP anterieur a
 * 8.1, et le menu qui permet d'en changer ne propose que les versions deja
 * installees. L'utilisateur se retrouve alors a chercher un chemin du genre
 * C:\wamp64\bin\php\php8.3.0\php.exe, a la main, dans une invite de commandes.
 *
 * Ce fichier fait cette recherche a sa place : il retient le premier PHP 8.1+
 * qu'il trouve — chez WampServer, chez XAMPP, ou dans le PATH — et demarre le
 * serveur integre. Un double-clic remplace trois pages d'explications.
 *
 * Pas d'accents : la console Windows n'est pas en UTF-8 et les afficherait mal.
 */
file_put_contents($travail . '/demarrer-en-local.bat', str_replace("\n", "\r\n", <<<'BAT'
@echo off
setlocal enabledelayedexpansion

rem Deuxieme role de ce fichier : ouvrir le navigateur une fois le serveur en
rem place. Le serveur integre bloque la fenetre qui le lance, donc l'ouverture
rem doit venir d'ailleurs. Sans ce detour, le navigateur arrive avant que le port
rem ne soit ouvert et affiche ERR_CONNECTION_REFUSED sur une installation
rem parfaitement saine.
if "%~1"=="--ouvrir" (
    ping -n 4 127.0.0.1 >nul 2>&1
    start "" http://localhost:8000/
    exit /b 0
)

cd /d "%~dp0"

echo.
echo   Scannem - demarrage en local
echo   ----------------------------
echo.

set "PHP="
set "LOG=%~dp0diagnostic-scannem.txt"
>"%LOG%" echo Scannem - recherche d'un PHP 8.1 ou plus recent

rem Ou chercher, dans l'ordre.
rem
rem Les deux premieres entrees sont relatives a CE fichier : depose dans
rem <wamp>\www\scannem, le dossier des PHP de WampServer est <wamp>\bin\php, et
rem depuis <xampp>\htdocs\scannem c'est <xampp>\php. Partir de la plutot que de
rem "C:\wamp64" en dur, c'est fonctionner aussi quand WampServer est installe sur
rem un autre disque ou dans un dossier renomme.
for %%R in (
    "%~dp0..\..\bin\php"
    "%~dp0..\..\php"
    "C:\wamp64\bin\php"
    "C:\wamp\bin\php"
    "C:\xampp\php"
) do (
    if not defined PHP call :essayer "%%~R\php.exe"

    rem Les versions installees par WampServer, du plus recent au plus ancien.
    if exist "%%~R" (
        for /f "delims=" %%D in ('dir /b /ad /o-n "%%~R" 2^>nul') do (
            if not defined PHP call :essayer "%%~R\%%D\php.exe"
        )
    )
)

rem En dernier ressort, un PHP declare dans le PATH.
if not defined PHP (
    for /f "delims=" %%P in ('where php 2^>nul') do (
        if not defined PHP call :essayer "%%P"
    )
)

if not defined PHP (
    echo   Aucun PHP 8.1 ou plus recent n'a ete trouve sur cette machine.
    echo.
    echo   Scannem et ses dependances de generation de QR en ont besoin, et
    echo   PHP 8.0 n'est plus suivi en securite depuis fin 2023.
    echo.
    echo   Pour en installer un, sans rien casser a ton WampServer actuel :
    echo.
    echo     1. va sur   wampserver.aviatechno.net
    echo     2. rubrique "PHP versions", telecharge PHP 8.2 ou 8.3
    echo     3. lance l'installateur telecharge
    echo     4. relance ce fichier
    echo.
    echo   Le detail de la recherche est dans   diagnostic-scannem.txt
    echo   a cote de ce fichier. En cas de doute, c'est ce fichier qu'il faut
    echo   montrer : il dit exactement ou j'ai regarde et ce que j'ai trouve.
    echo.
    pause
    exit /b 1
)

echo   PHP utilise : !PHP!
echo.
echo   ====================================
echo     http://localhost:8000/
echo   ====================================
echo.
echo   Le navigateur s'ouvre dans trois secondes. S'il ne s'ouvre pas, ouvre
echo   l'adresse ci-dessus a la main.
echo.
echo   Cette fenetre EST le serveur : la fermer arrete Scannem.
echo   Si le port 8000 est deja pris, le demarrage echoue juste en dessous ;
echo   remplace alors 8000 par 8001 aux deux endroits ou il apparait plus haut
echo   dans ce fichier.
echo.

start "" /min "%~f0" --ouvrir
"!PHP!" -S localhost:8000

echo.
echo   Serveur arrete.
pause
exit /b 0

rem ---------------------------------------------------------------------------
rem Retient le binaire passe en argument s'il existe et annonce au moins 8.1.
rem C'est PHP lui-meme qui repond : aucun numero de version a deviner d'apres un
rem nom de dossier, que WampServer choisit librement. version_compare et non une
rem comparaison numerique, parce que < et >= sont des operateurs de redirection
rem pour cmd.exe.
:essayer
if not exist "%~1" exit /b 0
>>"%LOG%" echo %~1

rem C'est PHP qui ecrit lui-meme sa version dans le journal. Passer par
rem "for /f" pour relire sa sortie obligerait a lancer un executable dont le
rem chemin est entre guillemets : cmd.exe s'y prend les pieds une fois sur deux.
"%~1" -r "file_put_contents(getenv('LOG'), '   PHP '.PHP_VERSION.(version_compare(PHP_VERSION,'8.1','ge')?' - convient':' - trop ancien').PHP_EOL, FILE_APPEND);" >nul 2>&1

"%~1" -r "exit(version_compare(PHP_VERSION,'8.1','ge')?0:1);" >nul 2>&1
if not errorlevel 1 if not defined PHP set "PHP=%~1"
exit /b 0
BAT));

file_put_contents($travail . '/LISEZ-MOI.txt', <<<'TXT'
SCANNEM — INSTALLATION
======================

SUR UN HEBERGEMENT
------------------

1. Cree une base MySQL dans le panneau de ton hebergeur, et note les
   identifiants qu'il affiche (serveur, nom, utilisateur, mot de passe).

2. Envoie TOUT le contenu de ce dossier dans htdocs/ par FTP.
   Un sous-dossier convient aussi : htdocs/scannem/ fonctionne sans rien
   changer, Scannem s'adapte au prefixe.

3. Ouvre dans ton navigateur :  https://TON-DOMAINE/install.php
   (ou https://TON-DOMAINE/scannem/install.php si tu as choisi un sous-dossier)
   Remplis le formulaire. C'est tout.

4. SUPPRIME install.php juste apres. Tant qu'il est la, c'est une porte ouverte.

5. Verifie que https://TON-DOMAINE/storage/config.php n'affiche RIEN.
   Si ce fichier affiche du texte, arrete tout : ton secret de signature fuite.

6. Le scanner est sur https://TON-DOMAINE/scan/
   HTTPS OBLIGATOIRE, sinon le navigateur refuse l'acces a la camera.

EN LOCAL, SOUS WINDOWS
----------------------

Le plus simple : double-clique sur   demarrer-en-local.bat

Il cherche tout seul un PHP 8.1 ou plus recent parmi ceux que WampServer et
XAMPP installent, demarre le serveur et ouvre le navigateur. Rien a
configurer, Apache n'est pas touche. Ouvre ensuite install.php depuis la
page qui s'affiche, et choisis SQLite : aucune base a creer.

Si tu preferes passer par ton Apache :

  - PHP 8.1 AU MINIMUM. WAMP et XAMPP sont souvent livres avec une version
    plus ancienne, et le menu PHP > Version ne propose que les versions
    DEJA INSTALLEES. Pour en ajouter une : wampserver.aviatechno.net,
    rubrique "PHP versions". Si PHP est trop ancien, Scannem te le dit en
    toutes lettres au lieu d'afficher une page blanche.

  - Depose le contenu de ce dossier dans www\scannem\ (WAMP) ou
    htdocs\scannem\ (XAMPP), puis ouvre :

        http://localhost/scannem/install.php

  - Choisis SQLite dans le formulaire : aucune base a creer, aucun
    identifiant a saisir. C'est preselectionne sur localhost.

La camera fonctionne sur http://localhost, que les navigateurs considerent
comme un contexte sur. Depuis un telephone qui pointe sur l'IP de ton PC,
en revanche, il faudra du HTTPS.

A SAUVEGARDER hors du serveur : storage/config.php
Sans ce fichier, aucune carte deja imprimee ne peut plus etre verifiee.
TXT);

echo "  $copies fichiers copies, $ignores ecartes (tests et documentation des dependances).\n";

// ----------------------------------------------------------------- Archive

$zipPath = $sortie . '/scannem-' . date('Ymd') . '.zip';

echo "Compression...\n";

$zip = new ZipArchive();

if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    exit("Impossible de creer $zipPath\n");
}

$iterateur = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($travail, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$fichiers = 0;

foreach ($iterateur as $item) {
    /** @var SplFileInfo $item */
    $relatif = substr($item->getPathname(), strlen($travail) + 1);

    if ($item->isDir()) {
        $zip->addEmptyDir($relatif);
    } else {
        $zip->addFile($item->getPathname(), $relatif);
        $fichiers++;
    }
}

$zip->close();

// --------------------------------------------------- Verification de l'archive

/**
 * L'elagage a retire des fichiers des dependances : il faut prouver que le
 * resultat fonctionne encore, pas seulement qu'il est plus leger. On genere un
 * QR depuis l'arborescence elaguee, hors du projet d'origine.
 */
echo "Verification de l'archive elaguee...\n";

$verif = sprintf(
    'cd %s && php -r %s 2>&1',
    escapeshellarg($travail),
    escapeshellarg(
        'require "vendor/autoload.php";'
        . '$r = new Scannem\QrRenderer();'
        . '$t = new Scannem\Token(["A" => str_repeat("a", 64)], "A");'
        . '$p = $t->build(Scannem\Token::newUid());'
        . '$svg = $r->svg($p);'
        . 'if (!str_contains($svg, "<svg")) { exit("SVG invalide\n"); }'
        . '$sheet = $r->printSheet([["uid" => "AAAABBBBCCCCDDDD", "payload" => $p]], "Essai");'
        . 'if (!str_contains($sheet, "<svg")) { exit("Planche invalide\n"); }'
        . 'echo "OK svg+planche";'
        . 'if (Scannem\QrRenderer::pngDisponible()) {'
        . '  $png = $r->png($p);'
        . '  echo strlen($png) > 100 ? "+png" : "+PNG VIDE";'
        . '}'
        . 'echo "\n";'
    )
);

$resultat = trim((string) shell_exec($verif));

if (!str_starts_with($resultat, 'OK')) {
    echo "\n  ECHEC : l'archive elaguee ne fonctionne pas.\n  $resultat\n\n";
    exit(1);
}

echo "  $resultat\n";

// ------------------------------------------------------------------ Bilan

$poids = filesize($zipPath);
$mo = $poids / 1024 / 1024;

printf("\n=== Archive prete ===\n\n");
printf("  %s\n", $zipPath);
printf("  %d fichiers (inodes), %.2f Mo compresses\n\n", $fichiers, $mo);

if ($mo > 10) {
    printf("  ATTENTION : au-dela des 10 Mo du gestionnaire de fichiers.\n");
    printf("  Passe par le FTP (FileZilla), qui n'a pas cette limite.\n\n");
} else {
    printf("  Tient sous les 10 Mo : envoi possible par le gestionnaire de fichiers\n");
    printf("  comme par FTP.\n\n");
}

// L'installation --no-dev a supprime PHPUnit du projet. Le laisser dans cet etat
// ferait echouer la prochaine execution des tests avec un message obscur, et
// c'est exactement le genre de piege qui coute une demi-heure.
echo "Restauration des dependances de developpement...\n";

$restaure = sprintf(
    'cd %s && COMPOSER_ALLOW_SUPERUSER=1 %s install --no-interaction --no-progress -q 2>&1',
    escapeshellarg($racine),
    escapeshellarg($composer)
);

shell_exec($restaure);

printf(
    "  %s\n\n",
    is_file($racine . '/vendor/bin/phpunit')
        ? 'fait, les tests peuvent tourner.'
        : 'ECHEC : relance `composer install` a la main avant les tests.'
);
