<?php

declare(strict_types=1);

/**
 * Web installer, for hosting without SSH.
 *
 * SECURITY
 *
 * An installer left reachable on a live site is a complete takeover: it can
 * rewrite the database credentials and create an administrator. Three things
 * stand in the way:
 *
 *   1. It refuses to run once a lock file exists in private storage.
 *   2. It refuses to run when the database already holds a user account,
 *      even if the lock file was deleted.
 *   3. It tells the operator, in the strongest terms it can, to delete this
 *      file — and the settings panel keeps warning while it is still there.
 *
 * Deleting it after installation is not optional.
 */

use App\Core\Autoloader;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Migrator;
use App\Core\Session;
use App\Models\Role;

$basePath = dirname(__DIR__);

require_once $basePath . '/app/Core/Autoloader.php';

$autoloader = new Autoloader();
$autoloader->addNamespace('App', $basePath . '/app');
$autoloader->register();

require_once $basePath . '/app/Helpers/helpers.php';

Config::loadEnv($basePath . '/.env');
Config::load($basePath . '/config');

// This script is reached directly, never through the front controller, so it
// is unaffected by the installation-required redirect in Application.

date_default_timezone_set((string) Config::get('app.timezone', 'UTC'));
Session::start();

$lockFile = $basePath . '/storage/private/.installed';
$envFile = $basePath . '/.env';

/** Has this installation already been completed? */
function alreadyInstalled(string $lockFile): bool
{
    if (is_file($lockFile)) {
        return true;
    }

    // The lock file can be deleted; an existing account cannot be wished away.
    try {
        $count = App\Core\Database::connection()
            ->query('SELECT COUNT(*) FROM users')
            ->fetchColumn();

        return (int) $count > 0;
    } catch (Throwable) {
        // No database, no tables, no users: a genuinely fresh installation.
        return false;
    }
}

/** @return array<int, array{label: string, ok: bool, detail: string, fatal: bool}> */
function requirements(string $basePath): array
{
    $imaging = new App\Services\ImageProcessingService();
    $storageRoot = (string) Config::get('storage.root', $basePath . '/storage/private');

    return [
        [
            'label'  => 'PHP 8.1 ou supérieur',
            'ok'     => version_compare(PHP_VERSION, '8.1.0', '>='),
            'detail' => 'Version détectée : ' . PHP_VERSION,
            'fatal'  => true,
        ],
        [
            'label'  => 'Extension PDO',
            'ok'     => extension_loaded('pdo'),
            'detail' => extension_loaded('pdo_mysql') ? 'pdo_mysql disponible' : 'pdo_mysql absent',
            'fatal'  => true,
        ],
        [
            'label'  => "Traitement d'image",
            'ok'     => $imaging->isAvailable(),
            'detail' => $imaging->driverLabel(),
            'fatal'  => true,
        ],
        [
            'label'  => 'Extension fileinfo',
            'ok'     => function_exists('finfo_open'),
            'detail' => "Contrôle du vrai type des fichiers importés",
            'fatal'  => true,
        ],
        [
            'label'  => 'Racine du projet accessible en écriture',
            'ok'     => is_writable($basePath),
            'detail' => 'Nécessaire pour écrire le fichier .env',
            'fatal'  => true,
        ],
        [
            'label'  => 'Stockage privé accessible en écriture',
            'ok'     => is_dir($storageRoot) ? is_writable($storageRoot) : is_writable(dirname($storageRoot)),
            'detail' => $storageRoot,
            'fatal'  => true,
        ],
        [
            'label'  => 'Extension ZIP',
            'ok'     => class_exists(ZipArchive::class),
            'detail' => 'Téléchargement de galerie complète',
            'fatal'  => false,
        ],
        [
            'label'  => 'Extension EXIF',
            'ok'     => function_exists('exif_read_data'),
            'detail' => 'Orientation et date de prise de vue',
            'fatal'  => false,
        ],
        [
            'label'  => 'Chiffrement (sodium ou openssl)',
            'ok'     => function_exists('sodium_crypto_secretbox') || function_exists('openssl_encrypt'),
            'detail' => "Réaffichage des liens de galerie après leur création",
            'fatal'  => false,
        ],
        [
            'label'  => 'Génération WebP',
            'ok'     => $imaging->supportsWebp(),
            'detail' => 'Aperçus environ un tiers plus légers',
            'fatal'  => false,
        ],
    ];
}

/** Build a .env from the collected answers. */
function renderEnv(array $database, string $appUrl, string $appKey): string
{
    $quote = static fn (string $value): string => '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';

    return implode("\n", [
        '# Généré par public/install.php le ' . date('Y-m-d H:i:s'),
        '',
        'APP_NAME=' . $quote("L'ENFANT VISUAL"),
        'APP_ENV=production',
        'APP_DEBUG=false',
        'APP_URL=' . $appUrl,
        'APP_KEY=' . $appKey,
        'APP_TIMEZONE=Europe/Paris',
        'APP_LOCALE=fr',
        '',
        'DB_CONNECTION=mysql',
        'DB_HOST=' . $database['host'],
        'DB_PORT=' . $database['port'],
        'DB_DATABASE=' . $database['database'],
        'DB_USERNAME=' . $database['username'],
        'DB_PASSWORD=' . $quote($database['password']),
        'DB_CHARSET=utf8mb4',
        '',
        '# Doit rester hors de la racine web. Vide = <projet>/storage/private',
        'STORAGE_PATH=',
        '',
        'UPLOAD_MAX_BYTES=104857600',
        'THUMBNAIL_WIDTH=400',
        'PREVIEW_WIDTH=1600',
        'WEBP_ENABLED=true',
        '',
        'SESSION_NAME=studio_session',
        'SESSION_LIFETIME=7200',
        '# Passez à true dès que le site est servi en HTTPS.',
        'SESSION_SECURE=' . (str_starts_with($appUrl, 'https://') ? 'true' : 'false'),
        'SESSION_SAMESITE=Lax',
        '',
        'LOGIN_MAX_ATTEMPTS=5',
        'LOGIN_DECAY_SECONDS=900',
        'GALLERY_MAX_ATTEMPTS=10',
        'CONTACT_MAX_PER_HOUR=5',
        '',
        '# À activer seulement quand HTTPS fonctionne sur le domaine ET ses sous-domaines.',
        'HSTS_ENABLED=false',
        '',
        'MAIL_DRIVER=log',
        'MAIL_HOST=',
        'MAIL_PORT=587',
        'MAIL_USERNAME=',
        'MAIL_PASSWORD=',
        'MAIL_ENCRYPTION=tls',
        'MAIL_FROM_ADDRESS=no-reply@example.com',
        'MAIL_FROM_NAME=' . $quote("L'ENFANT VISUAL"),
        '',
    ]);
}

// --- Request handling -------------------------------------------------------

$errors = [];
$notice = null;
$step = 'requirements';
$checks = requirements($basePath);
$blocked = false;

foreach ($checks as $check) {
    if ($check['fatal'] && !$check['ok']) {
        $blocked = true;
    }
}

if (alreadyInstalled($lockFile)) {
    $step = 'locked';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate((string) ($_POST['_token'] ?? ''))) {
        $errors[] = 'Session expirée. Rechargez la page et réessayez.';
        $step = 'database';
    } else {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'database') {
            $database = [
                'host'     => trim((string) ($_POST['db_host'] ?? 'localhost')),
                'port'     => (int) ($_POST['db_port'] ?? 3306),
                'database' => trim((string) ($_POST['db_database'] ?? '')),
                'username' => trim((string) ($_POST['db_username'] ?? '')),
                'password' => (string) ($_POST['db_password'] ?? ''),
            ];

            if ($database['database'] === '' || $database['username'] === '') {
                $errors[] = "Le nom de la base et l'utilisateur sont obligatoires.";
                $step = 'database';
            } else {
                try {
                    // The credentials are proved before anything is written:
                    // a .env with wrong credentials is a site that 500s.
                    $pdo = new PDO(
                        sprintf(
                            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                            $database['host'],
                            $database['port'],
                            $database['database']
                        ),
                        $database['username'],
                        $database['password'],
                        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                    );
                    $pdo->query('SELECT 1');

                    Session::put('_install_database', $database);
                    Session::put('_install_url', rtrim(trim((string) ($_POST['app_url'] ?? '')), '/'));
                    $step = 'account';
                } catch (PDOException $e) {
                    $errors[] = 'Connexion impossible : ' . $e->getMessage();
                    $step = 'database';
                }
            }
        } elseif ($action === 'account') {
            $database = Session::get('_install_database');
            $appUrl = (string) Session::get('_install_url', '');

            $name = trim((string) ($_POST['name'] ?? ''));
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $password = (string) ($_POST['password'] ?? '');

            if (!is_array($database)) {
                $errors[] = 'Étape base de données manquante. Reprenez depuis le début.';
                $step = 'database';
            } elseif ($name === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = 'Nom et adresse e-mail valides obligatoires.';
                $step = 'account';
            } elseif (strlen($password) < 10) {
                $errors[] = 'Le mot de passe doit contenir au moins 10 caractères.';
                $step = 'account';
            } elseif ($password !== (string) ($_POST['password_confirmation'] ?? '')) {
                $errors[] = 'La confirmation ne correspond pas au mot de passe.';
                $step = 'account';
            } else {
                try {
                    $appKey = App\Core\Encrypter::generateAppKey();
                    $contents = renderEnv($database, $appUrl === '' ? 'http://localhost' : $appUrl, $appKey);

                    if (@file_put_contents($envFile, $contents) === false) {
                        throw new RuntimeException(
                            "Impossible d'écrire le fichier .env. Créez-le à la main avec le contenu affiché plus bas."
                        );
                    }

                    @chmod($envFile, 0640);

                    // Re-read the configuration so the rest of this request
                    // works against the credentials just written.
                    Config::set('app.key', $appKey);
                    Config::set('app.url', $appUrl);
                    Config::set('database.driver', 'mysql');
                    Config::set('database.host', $database['host']);
                    Config::set('database.port', $database['port']);
                    Config::set('database.database', $database['database']);
                    Config::set('database.username', $database['username']);
                    Config::set('database.password', $database['password']);
                    App\Core\Database::swap(null);

                    (new App\Services\StorageService())->ensureReady();
                    (new Migrator($basePath . '/database/migrations'))->migrate();

                    require_once $basePath . '/database/seeders/SettingsSeeder.php';
                    (new Database\Seeders\SettingsSeeder())->run();

                    $users = new App\Repositories\UserRepository();

                    if ($users->emailExists($email)) {
                        throw new RuntimeException('Un compte existe déjà avec cette adresse.');
                    }

                    $users->create($name, $email, $password, Role::SUPER_ADMIN);

                    @file_put_contents(
                        $lockFile,
                        "Installé le " . date('Y-m-d H:i:s') . "\n"
                        . "Supprimez public/install.php s'il existe encore.\n"
                    );

                    Session::forget('_install_database');
                    Session::forget('_install_url');

                    $step = 'done';
                    $notice = $email;
                } catch (Throwable $e) {
                    $errors[] = $e->getMessage();
                    $step = 'account';
                }
            }
        }
    }
} elseif (!$blocked && ($_GET['step'] ?? '') === 'database') {
    $step = 'database';
}
// Otherwise the requirements screen is shown first, every time. Seeing what
// the server does and does not provide is the point of this step; skipping it
// because nothing is broken hides the optional gaps (ZIP, EXIF, WebP) that
// the operator still needs to know about.

$token = Csrf::token();
$suggestedUrl = (($_SERVER['HTTPS'] ?? 'off') !== 'off' ? 'https://' : 'http://')
    . (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Installation — L&#039;ENFANT VISUAL</title>
<link rel="stylesheet" href="assets/css/admin.css">
<style>
    body.admin { display: grid; place-items: start center; padding: 2.5rem 1rem; background: #f6f5f2; }
    .install { width: 100%; max-width: 640px; display: grid; gap: 1.25rem; }
    .install__head { text-align: center; }
    .install__title { font-size: 1.5rem; margin-bottom: .25rem; }
    .install__lede { color: #6e6960; margin: 0; }
    .install__steps { display: flex; gap: .5rem; justify-content: center; font-size: .78rem; color: #6e6960; }
    .install__step.is-active { color: #1c1a17; font-weight: 600; }
    .install__env { white-space: pre-wrap; word-break: break-all; font-size: .72rem; max-height: 220px; overflow: auto; }
</style>
</head>
<body class="admin">

<main class="install">
    <header class="install__head">
        <h1 class="install__title">Installation</h1>
        <p class="install__lede">Site vitrine et livraison privée de photographies</p>
    </header>

    <?php if ($step !== 'locked' && $step !== 'done'): ?>
        <nav class="install__steps" aria-label="Étapes">
            <span class="install__step<?= $step === 'requirements' ? ' is-active' : '' ?>">1. Vérifications</span>
            <span aria-hidden="true">›</span>
            <span class="install__step<?= $step === 'database' ? ' is-active' : '' ?>">2. Base de données</span>
            <span aria-hidden="true">›</span>
            <span class="install__step<?= $step === 'account' ? ' is-active' : '' ?>">3. Compte</span>
        </nav>
    <?php endif; ?>

    <?php foreach ($errors as $error): ?>
        <div class="callout callout--warn" role="alert"><?= e($error) ?></div>
    <?php endforeach; ?>

    <?php if ($step === 'locked'): ?>
        <section class="panel">
            <h2 class="panel__title">Cette installation est déjà effectuée</h2>
            <p class="panel__text">
                L'installateur refuse de s'exécuter : un compte administrateur existe déjà.
            </p>
            <div class="callout callout--warn">
                <strong>Supprimez maintenant le fichier <code>public/install.php</code>.</strong>
                Laissé en place, il reste une porte d'entrée sur votre site.
            </div>
            <p class="panel__footer">
                <a class="button" href="admin/login">Aller à la connexion</a>
            </p>
        </section>

    <?php elseif ($step === 'done'): ?>
        <section class="panel">
            <h2 class="panel__title">Installation terminée</h2>
            <p class="panel__text">
                Le compte <strong><?= e((string) $notice) ?></strong> a été créé, la base est
                initialisée et le fichier <code>.env</code> est écrit.
            </p>

            <div class="callout callout--warn">
                <strong>Première chose à faire : supprimez <code>public/install.php</code>.</strong>
                Tant qu'il existe, n'importe qui peut le rouvrir.
            </div>

            <ol class="panel__text">
                <li>Supprimez <code>public/install.php</code>.</li>
                <li>Connectez-vous et ouvrez <strong>Paramètres</strong> : aucun point ne doit être rouge.</li>
                <li>Activez HTTPS, puis passez <code>SESSION_SECURE=true</code> dans <code>.env</code>.</li>
                <li>Vérifiez que <code>https://votre-domaine/.env</code> renvoie une erreur 403 ou 404.</li>
            </ol>

            <p class="panel__footer"><a class="button" href="admin/login">Se connecter</a></p>
        </section>

    <?php elseif ($step === 'requirements' || $blocked): ?>
        <section class="panel">
            <h2 class="panel__title">Vérification du serveur</h2>

            <ul class="diagnostics">
                <?php foreach ($checks as $check): ?>
                    <li class="diagnostic<?= $check['ok'] ? ' is-ok' : ($check['fatal'] ? ' is-critical' : ' is-warn') ?>">
                        <span class="diagnostic__icon" aria-hidden="true"><?= $check['ok'] ? '✓' : '!' ?></span>
                        <span class="diagnostic__body">
                            <span class="diagnostic__label">
                                <?= e($check['label']) ?>
                                <?= $check['ok'] ? '' : ($check['fatal'] ? ' — bloquant' : ' — optionnel') ?>
                            </span>
                            <span class="diagnostic__detail"><?= e($check['detail']) ?></span>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($blocked): ?>
                <div class="callout callout--warn">
                    Corrigez les points bloquants auprès de votre hébergeur, puis rechargez cette page.
                </div>
            <?php else: ?>
                <p class="panel__footer">
                    <a class="button" href="install.php?step=database">Continuer</a>
                </p>
            <?php endif; ?>
        </section>

    <?php elseif ($step === 'database'): ?>
        <form class="form form--panel" method="post" action="install.php">
            <input type="hidden" name="_token" value="<?= e($token) ?>">
            <input type="hidden" name="action" value="database">

            <h2 class="panel__title">Base de données</h2>
            <p class="panel__text">
                Créez d'abord une base MySQL vide depuis le panneau de votre hébergeur,
                puis reportez ses informations ici. Elles sont testées avant d'être enregistrées.
            </p>

            <div class="field">
                <label for="app_url">Adresse publique du site</label>
                <input type="url" id="app_url" name="app_url" required
                       value="<?= e((string) ($_POST['app_url'] ?? $suggestedUrl)) ?>">
                <p class="field__hint">
                    Sans slash final. Les liens de galerie envoyés à vos clients sont construits
                    à partir de cette adresse.
                </p>
            </div>

            <div class="field-row">
                <div class="field">
                    <label for="db_host">Hôte</label>
                    <input type="text" id="db_host" name="db_host" required
                           value="<?= e((string) ($_POST['db_host'] ?? 'localhost')) ?>">
                </div>
                <div class="field">
                    <label for="db_port">Port</label>
                    <input type="number" id="db_port" name="db_port" required
                           value="<?= e((string) ($_POST['db_port'] ?? '3306')) ?>">
                </div>
            </div>

            <div class="field">
                <label for="db_database">Nom de la base</label>
                <input type="text" id="db_database" name="db_database" required
                       value="<?= e((string) ($_POST['db_database'] ?? '')) ?>">
            </div>

            <div class="field-row">
                <div class="field">
                    <label for="db_username">Utilisateur</label>
                    <input type="text" id="db_username" name="db_username" required
                           value="<?= e((string) ($_POST['db_username'] ?? '')) ?>">
                </div>
                <div class="field">
                    <label for="db_password">Mot de passe</label>
                    <input type="password" id="db_password" name="db_password" autocomplete="off">
                </div>
            </div>

            <div class="form__actions">
                <button class="button" type="submit">Tester et continuer</button>
            </div>
        </form>

    <?php else: ?>
        <form class="form form--panel" method="post" action="install.php">
            <input type="hidden" name="_token" value="<?= e($token) ?>">
            <input type="hidden" name="action" value="account">

            <h2 class="panel__title">Compte administrateur</h2>
            <p class="panel__text">
                Connexion à la base réussie. Ce compte sera le vôtre : il donne accès à tout.
            </p>

            <div class="field">
                <label for="name">Nom complet</label>
                <input type="text" id="name" name="name" required
                       value="<?= e((string) ($_POST['name'] ?? '')) ?>">
            </div>

            <div class="field">
                <label for="email">Adresse e-mail</label>
                <input type="email" id="email" name="email" required autocomplete="username"
                       value="<?= e((string) ($_POST['email'] ?? '')) ?>">
            </div>

            <div class="field-row">
                <div class="field">
                    <label for="password">Mot de passe</label>
                    <input type="password" id="password" name="password" required
                           minlength="10" autocomplete="new-password">
                    <p class="field__hint">10 caractères minimum. Une phrase de passe vaut mieux qu'un mot compliqué.</p>
                </div>
                <div class="field">
                    <label for="password_confirmation">Confirmation</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required
                           autocomplete="new-password">
                </div>
            </div>

            <div class="form__actions">
                <button class="button" type="submit">Installer</button>
            </div>
        </form>
    <?php endif; ?>
</main>

</body>
</html>
