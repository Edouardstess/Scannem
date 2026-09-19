<?php
/**
 * ============================================================================
 * config/config.php — Configuration centrale de l'application.
 * ----------------------------------------------------------------------------
 * Aucun secret n'est écrit ici. Les valeurs proviennent, par ordre de priorité :
 *   1. des variables d'environnement (UEP_DB_HOST, ...) ;
 *   2. du fichier config/config.local.php, créé par install.php et jamais versionné ;
 *   3. des valeurs par défaut ci-dessous (développement uniquement).
 * ============================================================================
 */
declare(strict_types=1);

if (defined('UEP_CONFIG_CHARGEE')) {
    return;
}
define('UEP_CONFIG_CHARGEE', true);

define('APP_VERSION', '2.0.0');
define('RACINE_APP', dirname(__DIR__));
define('RACINE_VIEWS', RACINE_APP . '/views');
define('RACINE_STORAGE', RACINE_APP . '/storage');
define('RACINE_LOGS', RACINE_STORAGE . '/logs');

/** Chemin du fichier de configuration locale (identifiants de l'hébergeur). */
define('FICHIER_CONFIG_LOCALE', __DIR__ . '/config.local.php');

/**
 * Lit un paramètre : variable d'environnement, puis config locale, puis défaut.
 */
function uep_config(string $cle, mixed $defaut = null): mixed
{
    /** @var array<string, mixed> $local */
    static $local = null;
    if ($local === null) {
        $local = is_file(FICHIER_CONFIG_LOCALE) ? (array)require FICHIER_CONFIG_LOCALE : [];
    }

    $env = getenv($cle);
    if ($env !== false && $env !== '') {
        return $env;
    }

    return array_key_exists($cle, $local) ? $local[$cle] : $defaut;
}

/**
 * Échappement HTML des sorties (anti-XSS). Défini ici pour être disponible
 * partout, y compris sur les pages d'erreur rendues très tôt.
 */
function e(mixed $valeur): string
{
    if ($valeur === null || is_array($valeur) || is_object($valeur)) {
        return '';
    }

    return htmlspecialchars((string)$valeur, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Protocole réel de la requête courante.
 *
 * Ne JAMAIS déduire le HTTPS de APP_ENV : sur un site servi en http, un cookie
 * marqué « secure » est refusé par le navigateur, la session est perdue à chaque
 * requête et la connexion devient impossible (échec CSRF permanent).
 */
function uep_requete_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        return true;
    }
    foreach (['HTTP_X_FORWARDED_PROTO', 'HTTP_X_FORWARDED_SCHEME'] as $entete) {
        if (strtolower(trim(explode(',', (string)($_SERVER[$entete] ?? ''))[0])) === 'https') {
            return true;
        }
    }
    if (strtolower((string)($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on') {
        return true;
    }

    return false;
}

/**
 * URL de base déduite de la requête : schéma réel + hôte réel + sous-dossier.
 * Une URL figée casse les redirections dès qu'on ouvre le site depuis une autre
 * adresse (localhost, http au lieu de https, sous-répertoire).
 */
function uep_url_base(): string
{
    if (PHP_SAPI === 'cli') {
        return rtrim((string)uep_config('UEP_APP_URL', 'http://localhost'), '/');
    }

    $hote = (string)($_SERVER['HTTP_HOST'] ?? '');
    // L'en-tête Host vient du client : on n'accepte qu'un hôte bien formé.
    if (!preg_match('/^[A-Za-z0-9.\-]+(:\d{1,5})?$/', $hote)) {
        $hote = (string)($_SERVER['SERVER_NAME'] ?? 'localhost');
    }

    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $dossier = rtrim((string)preg_replace('#/[^/]*$#', '', $script), '/');

    return (uep_requete_https() ? 'https' : 'http') . '://' . $hote . $dossier;
}

// ---------------------------------------------------------------- Application
define('APP_NOM', (string)uep_config('UEP_APP_NAME', 'UEP - MENFP'));
define('APP_ENV', strtolower((string)uep_config('UEP_APP_ENV', 'production')));
define('URL_BASE', uep_url_base());

date_default_timezone_set((string)uep_config('UEP_TIMEZONE', 'America/Port-au-Prince'));

error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('display_errors', APP_ENV === 'production' ? '0' : '1');
if (is_dir(RACINE_LOGS) && is_writable(RACINE_LOGS)) {
    ini_set('error_log', RACINE_LOGS . '/php-' . date('Y-m') . '.log');
}

// ---------------------------------------------------------------- Base de données
define('DB_HOTE', (string)uep_config('UEP_DB_HOST', 'localhost'));
define('DB_PORT', (int)uep_config('UEP_DB_PORT', 3306));
define('DB_NOM', (string)uep_config('UEP_DB_NAME', ''));
define('DB_USER', (string)uep_config('UEP_DB_USER', ''));
define('DB_PASS', (string)uep_config('UEP_DB_PASS', ''));
define('DB_CHARSET', 'utf8mb4');
define('DB_DSN', sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOTE, DB_PORT, DB_NOM, DB_CHARSET));

// ---------------------------------------------------------------- Sécurité
define('CLE_SECRETE', (string)uep_config('UEP_APP_SECRET', ''));
define('SESSION_NAME', (string)uep_config('UEP_SESSION_NAME', 'UEP_MENFP_SESS'));
define('DUREE_SESSION', max(300, (int)uep_config('UEP_SESSION_TTL', 3600)));
define('MAX_TENTATIVES', max(3, (int)uep_config('UEP_MAX_LOGIN_ATTEMPTS', 5)));
define('MAX_TENTATIVES_IP', max(MAX_TENTATIVES, (int)uep_config('UEP_MAX_LOGIN_ATTEMPTS_IP', 20)));
define('FENETRE_BLOCAGE', max(60, (int)uep_config('UEP_LOGIN_WINDOW', 900)));
define('LONGUEUR_MDP_MIN', 12);

$proxies = (string)uep_config('UEP_TRUSTED_PROXIES', '');
define('PROXIES_APPROUVES', array_values(array_filter(array_map('trim', explode(',', $proxies)))));

// ---------------------------------------------------------------- Pagination
define('LIGNES_PAR_PAGE', 25);

// ---------------------------------------------------------------- Institution
define('CONTACT_TEL', (string)uep_config('UEP_CONTACT_TEL', '(509) 2813-0273'));
define('CONTACT_WEB', (string)uep_config('UEP_CONTACT_WEB', 'www.menfp.gouv.ht'));
define('CONTACT_ADRESSE', (string)uep_config('UEP_CONTACT_ADRESSE', '8, rue Antoine Simon, Delmas 83, Port-au-Prince, Haïti'));
define('SLOGAN_1', 'PLANIFIER. PROGRAMMER. TRANSFORMER.');
define('SLOGAN_2', 'TRANSFORMER LES DONNÉES EN DÉCISIONS, ET LES DÉCISIONS EN RÉSULTATS AU PROFIT DE LA SOCIÉTÉ ÉDUCATIVE HAÏTIENNE');

/** L'application est-elle configurée (identifiants de base de données saisis) ? */
function uep_est_installee(): bool
{
    return is_file(FICHIER_CONFIG_LOCALE) && DB_NOM !== '' && DB_USER !== '';
}
