<?php

declare(strict_types=1);

/**
 * Interface organisateur.
 *
 * Routage par ?p=... , rendu par gabarit. Volontairement sans framework :
 * le but est que ca tourne sur n'importe quel hebergement PHP sans surprise.
 */

use Scannem\App;
use Scannem\Config;
use Scannem\Http;
use Scannem\QrRenderer;
use Scannem\ScanResult;
use Scannem\Session;
use Scannem\Url;

// Version de PHP, dependances, autoloader. Le prefixe d'installation, lui, a
// deja ete fixe par le point d'entree (index.php ou le relais admin/index.php),
// qui sont les seuls a savoir a quelle profondeur ils se trouvent.
require dirname(__DIR__) . '/amorce.php';
scannem_amorcer(dirname(__DIR__, 2));

/**
 * Prefixe d'installation, pour les gabarits.
 *
 * Les vues l'intercalent devant chaque lien : href="<?= $base ?>/admin/?p=lots".
 * Sans lui, une installation dans htdocs/scannem/ renverrait sur /admin/, que le
 * serveur ne trouve pas.
 */
$base = Url::base();

// Pas encore installe : on oriente vers l'installateur plutot que de laisser
// remonter une exception, qui donnerait une page 500 vide et, sur certains
// hebergements, une trace d'execution revelant les chemins du serveur.
if (!Config::exists()) {
    if (is_file(Config::rootPath('install.php')) || is_file(Config::rootPath('public/install.php'))) {
        header('Location: ' . Url::to('/install.php'));
        exit;
    }

    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    exit(
        '<!DOCTYPE html><meta charset="utf-8"><title>Scannem</title>'
        . '<p style="font:16px system-ui;padding:40px;max-width:36em">'
        . 'Scannem n\'est pas installe, et le fichier <code>install.php</code> est absent.'
        . '<br><br>Renvoie <code>install.php</code> par FTP, ouvre-le dans le navigateur,'
        . ' puis supprime-le a nouveau.</p>'
    );
}

$app = App::boot();
$config = $app->config();

Session::start($config);

$page = isset($_GET['p']) && is_string($_GET['p']) ? $_GET['p'] : 'lots';
$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

$flash = null;

/** @param 'ok'|'err'|'warn' $kind */
function flash(string $kind, string $message): void
{
    $_SESSION['flash'] = ['kind' => $kind, 'message' => $message];
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

if (isset($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

// ------------------------------------------------------------------ Connexion

if ($page === 'login') {
    $error = null;

    if ($isPost) {
        Session::requireCsrf();

        $user = (string) ($_POST['username'] ?? '');
        $pass = (string) ($_POST['password'] ?? '');

        // Quota sur la connexion : sans lui, le mot de passe organisateur est
        // attaquable par force brute depuis n'importe ou.
        $ip = Http::clientIp() ?? 'inconnu';

        if (!$app->limiter()->allow('login:' . $ip, 12)) {
            $error = 'Trop de tentatives. Patiente une minute.';
        } else {
            $admin = $app->auth()->verifyAdmin($user, $pass);

            if ($admin !== null) {
                Session::login($admin);
                redirect(Url::to('/admin/?p=lots'));
            }

            // Message unique : ne pas dire si c'est l'identifiant ou le mot de passe.
            $error = 'Identifiants incorrects.';
        }
    }

    $csrf = Session::csrfField();
    $errBlock = $error !== null ? '<div class="flash err">' . Http::escape($error) . '</div>' : '';

    $content = <<<HTML
<div class="panel" style="max-width:380px;margin:60px auto;">
  <h1>Connexion</h1>
  <p class="sub">Espace organisateur</p>
  $errBlock
  <form method="post" action="$base/admin/?p=login">
    $csrf
    <div class="field">
      <label for="u">Nom d'utilisateur</label>
      <input type="text" id="u" name="username" autocomplete="username" autofocus required>
    </div>
    <div class="field">
      <label for="p">Mot de passe</label>
      <input type="password" id="p" name="password" autocomplete="current-password" required>
    </div>
    <button type="submit" class="primary" style="width:100%">Se connecter</button>
  </form>
</div>
HTML;

    $title = 'Connexion';
    $active = '';
    require __DIR__ . '/layout.php';
    exit;
}

if ($page === 'logout') {
    if ($isPost) {
        Session::requireCsrf();
    }
    Session::logout();
    redirect(Url::to('/admin/?p=login'));
}

Session::requireLogin();

$cards = $app->cards();
$auth = $app->auth();

// ------------------------------------------------------------------- Actions

if ($isPost) {
    Session::requireCsrf();

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create_batch') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $qty = (int) ($_POST['qty'] ?? 0);
        $date = trim((string) ($_POST['date'] ?? '')) ?: null;

        if ($name === '' || $qty < 1) {
            flash('err', 'Donne un nom de lot et une quantite valide.');
        } elseif ($qty > 20000) {
            flash('err', 'Au-dela de 20 000 cartes, passe par la ligne de commande (bin/generate-batch.php).');
        } else {
            try {
                $batch = $cards->createBatch($name, $qty, $date);
                flash('ok', "Lot #{$batch['batch_id']} cree : $qty cartes.");
                redirect(Url::to('/admin/?p=batch&id=' . $batch['batch_id']));
            } catch (Throwable $e) {
                flash('err', 'Creation impossible : ' . $e->getMessage());
            }
        }

        redirect(Url::to('/admin/?p=lots'));
    }

    if ($action === 'revoke') {
        $uid = trim((string) ($_POST['uid'] ?? ''));

        if ($cards->revoke($uid)) {
            flash('ok', "Carte $uid annulee. Elle sera refusee a l'entree.");
        } else {
            $card = $cards->findByUid($uid);
            flash(
                'warn',
                $card === null
                    ? "Carte $uid introuvable."
                    : "Carte $uid non annulee : elle est deja " . ($card['status'] === 'used' ? 'utilisee (la personne est entree)' : 'annulee') . '.'
            );
        }

        redirect((string) ($_POST['back'] ?? Url::to('/admin/?p=lots')));
    }

    if ($action === 'restore') {
        $uid = trim((string) ($_POST['uid'] ?? ''));
        flash(
            $cards->restore($uid) ? 'ok' : 'warn',
            $cards->findByUid($uid) === null ? "Carte $uid introuvable." : "Carte $uid remise en circulation."
        );

        redirect((string) ($_POST['back'] ?? Url::to('/admin/?p=lots')));
    }

    if ($action === 'enroll_code') {
        $label = trim((string) ($_POST['label'] ?? ''));
        $code = $auth->createEnrollCode($label);

        // Affiche une seule fois : seul le hachage est conserve.
        $_SESSION['fresh_code'] = ['code' => $code, 'label' => $label !== '' ? $label : 'Appareil'];
        redirect(Url::to('/admin/?p=devices'));
    }

    if ($action === 'device_toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        $on = ($_POST['to'] ?? '') === '1';

        $on ? $auth->reactivateDevice($id) : $auth->deactivateDevice($id);
        flash('ok', $on ? 'Appareil reactive.' : 'Appareil desactive : ses scans seront refuses.');

        redirect(Url::to('/admin/?p=devices'));
    }

    redirect(Url::to('/admin/?p=lots'));
}

// --------------------------------------------------------------- Exports bruts

if ($page === 'sheet' || $page === 'csv') {
    $id = (int) ($_GET['id'] ?? 0);
    $batch = $cards->batch($id);

    if ($batch === null) {
        http_response_code(404);
        exit('Lot introuvable.');
    }

    $rows = $cards->cardsOfBatch($id, true);
    $list = array_map(
        static fn (array $r): array => ['uid' => (string) $r['uid'], 'payload' => (string) $r['payload']],
        $rows
    );

    if ($page === 'sheet') {
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        echo (new QrRenderer())->printSheet($list, (string) $batch['name'], $batch['event_date'] ?? null);
        exit;
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="lot-' . $id . '-cartes.csv"');
    header('Cache-Control: no-store');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['uid', 'payload', 'statut', 'utilisee_le'], ',', '"', '\\');

    foreach ($rows as $r) {
        fputcsv($out, [$r['uid'], $r['payload'], $r['status'], $r['used_at'] ?? ''], ',', '"', '\\');
    }

    exit;
}

// ------------------------------------------------------------------- Rendu

$flashBlock = '';

if ($flash !== null) {
    $flashBlock = '<div class="flash ' . Http::escape($flash['kind']) . '">'
        . Http::escape($flash['message']) . '</div>';
}

$csrf = Session::csrfField();
$active = $page;

ob_start();

switch ($page) {
    case 'batch':
        require __DIR__ . '/pages/batch.php';
        $active = 'lots';
        break;

    case 'scans':
        require __DIR__ . '/pages/scans.php';
        break;

    case 'disputes':
        require __DIR__ . '/pages/disputes.php';
        break;

    case 'devices':
        require __DIR__ . '/pages/devices.php';
        break;

    case 'securite':
        require __DIR__ . '/pages/securite.php';
        break;

    case 'lots':
    default:
        require __DIR__ . '/pages/lots.php';
        $active = 'lots';
        break;
}

$content = (string) ob_get_clean();

require __DIR__ . '/layout.php';
