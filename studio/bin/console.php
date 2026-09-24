<?php

declare(strict_types=1);

/**
 * Command-line tool.
 *
 *   php bin/console.php migrate            Apply pending migrations
 *   php bin/console.php migrate:status     List pending migrations
 *   php bin/console.php seed               Insert demonstration data
 *   php bin/console.php key:generate       Print a value for APP_KEY
 *   php bin/console.php user:create        Create an administrator account
 *   php bin/console.php user:password      Reset an account's password
 *   php bin/console.php install            migrate + settings + admin account
 *   php bin/console.php maintenance        Prune temporary files and counters
 *   php bin/console.php routes             List the route table
 *   php bin/console.php check              Run the installation diagnostics
 */

use App\Core\Application;
use App\Core\Config;
use App\Core\Database;
use App\Core\Encrypter;
use App\Core\Migrator;
use App\Models\Role;
use App\Repositories\UserRepository;
use App\Services\ImageProcessingService;
use App\Services\RateLimiter;
use App\Services\StorageService;
use App\Services\ZipService;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script is for the command line only.\n");
}

/** @var Application $app */
$app = require dirname(__DIR__) . '/app/bootstrap.php';

$command = $argv[1] ?? 'help';

/** Read a value from stdin, optionally without echoing it. */
function prompt(string $question, bool $hidden = false): string
{
    fwrite(STDOUT, $question);

    if ($hidden && DIRECTORY_SEPARATOR === '/') {
        // `stty -echo` is the only portable way to hide input in plain PHP CLI.
        @shell_exec('stty -echo 2>/dev/null');
        $value = trim((string) fgets(STDIN));
        @shell_exec('stty echo 2>/dev/null');
        fwrite(STDOUT, PHP_EOL);

        return $value;
    }

    return trim((string) fgets(STDIN));
}

function info(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

function fail(string $message): never
{
    fwrite(STDERR, 'Erreur : ' . $message . PHP_EOL);
    exit(1);
}

try {
    switch ($command) {
        case 'migrate':
            $migrator = new Migrator($app->basePath('database/migrations'));
            $ran = $migrator->migrate(true);

            info($ran === []
                ? 'Aucune migration en attente.'
                : sprintf('%d migration(s) appliquée(s).', count($ran)));
            break;

        case 'migrate:status':
            $pending = (new Migrator($app->basePath('database/migrations')))->pending();

            info($pending === []
                ? 'Base à jour.'
                : "Migrations en attente :\n  " . implode("\n  ", $pending));
            break;

        case 'seed':
            require $app->basePath('database/seeders/DemoSeeder.php');
            $summary = (new \Database\Seeders\DemoSeeder())->run();

            foreach ($summary as $line) {
                info('  ' . $line);
            }

            info('Données de démonstration insérées.');
            break;

        case 'key:generate':
            $key = Encrypter::generateAppKey();
            info('Ajoutez cette ligne à votre fichier .env :');
            info('');
            info('APP_KEY=' . $key);
            break;

        case 'user:password':
            $users = new UserRepository();
            $email = strtolower($argv[2] ?? prompt('E-mail du compte : '));
            $user = $users->findByEmail($email);

            if ($user === null) {
                fail('Aucun compte avec cet e-mail.');
            }

            $password = $argv[3] ?? prompt('Nouveau mot de passe (10 caractères minimum) : ', true);

            if (mb_strlen($password) < 10) {
                fail('Le mot de passe doit contenir au moins 10 caractères.');
            }

            $users->updatePassword((int) $user['id'], $password);
            (new RateLimiter())->clear('login|email|' . $email);
            info('Mot de passe modifié pour ' . $email . '. Le blocage de connexion est levé.');
            break;

        case 'user:create':
            $users = new UserRepository();

            $name = $argv[2] ?? prompt('Nom complet : ');
            $email = strtolower($argv[3] ?? prompt('E-mail : '));
            $role = $argv[5] ?? Role::PHOTOGRAPHER;

            if ($name === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                fail('Nom ou e-mail invalide.');
            }

            if ($users->emailExists($email)) {
                fail('Un compte existe déjà avec cet e-mail.');
            }

            if (!Role::isValid($role)) {
                fail('Rôle inconnu : ' . $role . '. Valeurs acceptées : ' . implode(', ', Role::ALL));
            }

            $password = $argv[4] ?? prompt('Mot de passe (10 caractères minimum) : ', true);

            if (strlen($password) < 10) {
                fail('Le mot de passe doit contenir au moins 10 caractères.');
            }

            $id = $users->create($name, $email, $password, $role);
            info(sprintf('Compte #%d créé pour %s (%s).', $id, $email, Role::label($role)));
            break;

        case 'install':
            info('1/4 — Vérification du stockage…');
            (new StorageService())->ensureReady();
            info('      OK : ' . (string) Config::get('storage.root'));

            info('2/4 — Migrations…');
            $ran = (new Migrator($app->basePath('database/migrations')))->migrate(true);
            info(sprintf('      %d migration(s) appliquée(s).', count($ran)));

            info('3/4 — Paramètres par défaut…');
            require $app->basePath('database/seeders/SettingsSeeder.php');
            (new \Database\Seeders\SettingsSeeder())->run();
            info('      OK.');

            info('4/4 — Compte administrateur…');

            if ((new UserRepository())->count() > 0) {
                info('      Un compte existe déjà, étape ignorée.');
            } else {
                $name = prompt('      Nom complet : ');
                $email = strtolower(prompt('      E-mail : '));
                $password = prompt('      Mot de passe (10 caractères minimum) : ', true);

                if (strlen($password) < 10) {
                    fail('Le mot de passe doit contenir au moins 10 caractères.');
                }

                (new UserRepository())->create($name, $email, $password, Role::SUPER_ADMIN);
                info('      Compte créé.');
            }

            info('');
            info('Installation terminée. Connectez-vous sur ' . Config::get('app.url') . '/admin');

            if (trim((string) Config::get('app.key', '')) === '') {
                info('');
                info('ATTENTION : APP_KEY est vide. Exécutez « php bin/console.php key:generate ».');
            }
            break;

        case 'maintenance':
            $storage = new StorageService();
            info(sprintf('%d archive(s) temporaire(s) supprimée(s).', $storage->pruneTemporary()));
            info(sprintf('%d compteur(s) de limitation purgé(s).', (new RateLimiter())->purgeExpired()));
            break;

        case 'routes':
            foreach ($app->router()->routes() as $method => $routes) {
                foreach ($routes as $route) {
                    $handler = is_array($route['handler'])
                        ? $route['handler'][0] . '::' . $route['handler'][1]
                        : 'closure';

                    info(sprintf('%-7s %-52s %s', $method, $route['pattern'], $handler));
                }
            }
            break;

        case 'check':
            $storage = new StorageService();
            $imaging = new ImageProcessingService();

            $checks = [
                'PHP ' . PHP_VERSION => version_compare(PHP_VERSION, '8.1.0', '>='),
                'PDO'                => extension_loaded('pdo'),
                'Base de données'    => (static function (): bool {
                    try {
                        Database::connection();

                        return true;
                    } catch (Throwable) {
                        return false;
                    }
                })(),
                'Traitement image (' . $imaging->driverLabel() . ')' => $imaging->isAvailable(),
                'Extension ZIP'      => (new ZipService())->isAvailable(),
                'Extension EXIF'     => function_exists('exif_read_data'),
                'APP_KEY définie'    => trim((string) Config::get('app.key', '')) !== '',
                'Installateur web supprimé' => !is_file($app->basePath('public/install.php')),
                // Only meaningful when a web server has told us where the
                // document root is; from the command line there is nothing to
                // compare against, so the web diagnostic panel is authoritative.
                'Stockage hors du dossier public' . ($storage->canCheckDocumentRoot()
                    ? ''
                    : ' (à vérifier depuis /admin/settings)') => $storage->canCheckDocumentRoot()
                        ? !$storage->isInsideDocumentRoot()
                        : true,
                'Stockage accessible en écriture' => is_writable((string) Config::get('storage.root')),
                'APP_DEBUG désactivé en production' => !(Config::get('app.debug')
                    && Config::get('app.env') === 'production'),
            ];

            $failures = 0;

            foreach ($checks as $label => $ok) {
                info(sprintf('  [%s] %s', $ok ? 'OK ' : 'KO ', $label));
                $failures += $ok ? 0 : 1;
            }

            info('');
            info($failures === 0
                ? 'Tout est en ordre.'
                : sprintf('%d point(s) à corriger.', $failures));

            exit($failures === 0 ? 0 : 1);

        default:
            info('Commandes disponibles :');
            info('  migrate            Appliquer les migrations en attente');
            info('  migrate:status     Lister les migrations en attente');
            info('  seed               Insérer les données de démonstration');
            info('  key:generate       Générer une valeur pour APP_KEY');
            info('  user:create        Créer un compte administrateur');
            info('  user:password      Réinitialiser le mot de passe d\'un compte');
            info('  install            Installation complète guidée');
            info('  maintenance        Purger fichiers temporaires et compteurs');
            info('  routes             Lister les routes');
            info('  check              Diagnostic de l\'installation');
    }
} catch (Throwable $e) {
    fail($e->getMessage());
}
