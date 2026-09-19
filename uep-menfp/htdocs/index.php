<?php
/**
 * ============================================================================
 * index.php — Point d'entrée unique de l'application UEP / MENFP.
 *
 * Toutes les requêtes dynamiques passent ici (voir .htaccess). Séquence :
 *   1. configuration      2. autoloader       3. en-têtes de sécurité
 *   4. session            5. routes           6. dispatch
 * ============================================================================
 */
declare(strict_types=1);

require __DIR__ . '/config/config.php';

// ---------------------------------------------------------------------------
// Autoloader : core/, controllers/, middlewares/, models/
// ---------------------------------------------------------------------------
spl_autoload_register(static function (string $classe): void {
    // Aucun nom de classe ne doit pouvoir sortir des dossiers de l'application.
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $classe)) {
        return;
    }

    foreach (['core', 'models', 'controllers', 'middlewares'] as $dossier) {
        $fichier = RACINE_APP . '/' . $dossier . '/' . $classe . '.php';
        if (is_file($fichier)) {
            require_once $fichier;
            return;
        }
    }
});

// ---------------------------------------------------------------------------
// Application pas encore configurée : on oriente vers l'installateur.
// ---------------------------------------------------------------------------
if (!uep_est_installee()) {
    if (is_file(__DIR__ . '/install.php')) {
        header('Location: ' . URL_BASE . '/install.php', true, 302);
        exit;
    }

    http_response_code(503);
    exit('Application non configurée : créez config/config.local.php à partir de config/config.local.example.php.');
}

// ---------------------------------------------------------------------------
// En-têtes de sécurité. Le nonce permet d'interdire les scripts injectés tout
// en autorisant les quelques scripts inline légitimes de l'application.
// ---------------------------------------------------------------------------
define('CSP_NONCE', base64_encode(random_bytes(16)));

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=(), interest-cohort=()');
header('Cross-Origin-Opener-Policy: same-origin');
header(
    "Content-Security-Policy: default-src 'self'; "
    . "script-src 'self' 'nonce-" . CSP_NONCE . "'; "
    . "style-src 'self' 'unsafe-inline'; "
    . "img-src 'self' data:; font-src 'self'; connect-src 'self'; "
    . "form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'"
);

if (uep_requete_https()) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// ---------------------------------------------------------------------------
// Session, routes, dispatch
// ---------------------------------------------------------------------------
Session::demarrer();

set_exception_handler(static function (Throwable $e): void {
    error_log('Exception non interceptée : ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());

    if (APP_ENV !== 'production') {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        echo '<h1>Erreur</h1><pre>' . htmlspecialchars(
            $e->getMessage() . "\n\n" . $e->getTraceAsString(),
            ENT_QUOTES,
            'UTF-8'
        ) . '</pre>';
        exit;
    }

    ErreurHttp::afficher(500);
});

$router = new Router(URL_BASE);
require RACINE_APP . '/routes/web.php';
$router->dispatch();
