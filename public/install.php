<?php

declare(strict_types=1);

/**
 * Installateur web.
 *
 * Raison d'etre : les hebergements mutualises gratuits (ByetHost, InfinityFree
 * et compagnie) ne donnent pas d'acces SSH. `php bin/install.php` y est donc
 * inexecutable, et sans ce formulaire il n'y aurait aucun moyen d'installer.
 *
 * DANGER, et il faut le regarder en face : tant que ce fichier existe et que
 * l'application n'est pas installee, quiconque trouve son URL peut installer
 * Scannem et en devenir proprietaire. La fenetre va de l'envoi des fichiers a la
 * premiere visite — quelques minutes si l'on suit le guide. WordPress fonctionne
 * exactement pareil. Trois garde-fous :
 *
 *   1. refus immediat des que la configuration existe ;
 *   2. tentative d'auto-suppression apres succes ;
 *   3. avertissement tant que le fichier traine.
 */

use Scannem\Auth;
use Scannem\Config;
use Scannem\Db;
use Scannem\Http;
use Scannem\QrRenderer;
use Scannem\Url;

// Racine trouvee en remontant : l'installateur doit fonctionner aussi bien
// depuis public/ que depose directement dans le htdocs/ d'un mutualise.
$racineProjet = __DIR__;

while (!is_file($racineProjet . '/vendor/autoload.php') && dirname($racineProjet) !== $racineProjet) {
    $racineProjet = dirname($racineProjet);
}

// Le 0 dit que ce fichier est pose a la racine servie : le prefixe
// d'installation est le dossier de ce script, '' a la racine du site,
// '/scannem' dans un sous-dossier.
require $racineProjet . '/app/amorce.php';
scannem_amorcer($racineProjet, 0);

$base = Url::base();

Http::securityHeaders();

$erreurs = [];
$succes = false;
$identifiants = null;

// --------------------------------------------------------------- Diagnostic

/** @return list<array{bool, string}> */
function diagnostic(): array
{
    $checks = [];

    $checks[] = [
        PHP_VERSION_ID >= 80100,
        'PHP ' . PHP_VERSION . (PHP_VERSION_ID >= 80100 ? '' : ' — il faut au moins 8.1'),
    ];

    foreach (['json' => 'JSON', 'hash' => 'Hachage', 'mbstring' => 'mbstring'] as $ext => $nom) {
        $checks[] = [extension_loaded($ext), $nom . ' (' . $ext . ')'];
    }

    // Une seule des deux suffit. Les afficher comme deux obligations ferait
    // croire a une installation impossible alors qu'il ne manque rien.
    $mysql = extension_loaded('pdo_mysql');
    $sqlite = extension_loaded('pdo_sqlite');

    $checks[] = [
        $mysql || $sqlite,
        'Base de donnees : ' . trim(($mysql ? 'MySQL ' : '') . ($sqlite ? 'SQLite' : ''))
            . ($mysql || $sqlite ? '' : 'aucun pilote disponible (pdo_mysql ou pdo_sqlite)'),
    ];

    $checks[] = [
        QrRenderer::pngDisponible(),
        'GD — export PNG' . (QrRenderer::pngDisponible() ? '' : ' absent, sans gravite : la planche d impression est en SVG'),
    ];

    $storage = Config::storageBase();
    $parent = is_dir($storage) ? $storage : dirname($storage);
    $checks[] = [is_writable($parent), 'Dossier de donnees accessible en ecriture (' . $storage . ')'];

    $checks[] = [
        !Config::storageIsInsideProject(),
        Config::storageIsInsideProject()
            ? 'Le dossier de donnees est DANS la racine web — voir l avertissement ci-dessous'
            : 'Le dossier de donnees est hors de la racine web',
    ];

    return $checks;
}

/**
 * Sert-on une installation locale ?
 *
 * Sur un poste de developpement, SQLite evite d'avoir a creer une base et des
 * identifiants avant meme de voir l'application tourner. On ne fait que
 * preselectionner : le choix reste entier.
 */
$hote = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$hote = explode(':', $hote)[0];
$enLocal = in_array($hote, ['localhost', '127.0.0.1', '::1', '[::1]'], true)
    || str_ends_with($hote, '.local')
    || str_ends_with($hote, '.test');

// L'installation est-elle deja faite ? Si oui, on ne touche a rien.
$dejaInstalle = Config::exists();

// ------------------------------------------------------------- Traitement

if (!$dejaInstalle && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $champ = static fn (string $k): string => trim((string) ($_POST[$k] ?? ''));

    // SQLite ou MySQL. SQLite ne demande aucun serveur ni identifiant : c'est ce
    // qui permet d'essayer Scannem sur un WAMP local en une seule etape. Les
    // hebergements mutualises, eux, n'offrent souvent que MySQL.
    $pilote = $champ('db_driver') === 'sqlite' ? 'sqlite' : 'mysql';

    $valeurs = [
        'db_driver' => $pilote,
        'db_host' => $champ('db_host') ?: 'localhost',
        'db_port' => (int) ($champ('db_port') ?: 3306),
        'db_name' => $champ('db_name'),
        'db_user' => $champ('db_user'),
        'db_pass' => (string) ($_POST['db_pass'] ?? ''),
    ];

    if ($pilote === 'sqlite') {
        // Le fichier atterrit dans le dossier de donnees, celui-la meme qui
        // contient deja le secret de signature et que le .htaccess protege.
        $valeurs['db_path'] = Config::storagePath('scannem.sqlite');
    }

    $adminUser = $champ('admin_user');
    $adminPass = (string) ($_POST['admin_pass'] ?? '');

    if ($pilote === 'mysql' && ($valeurs['db_name'] === '' || $valeurs['db_user'] === '')) {
        $erreurs[] = 'Le nom de la base et l utilisateur sont obligatoires.';
    }

    if ($adminUser === '') {
        $erreurs[] = "Choisis un nom d'utilisateur organisateur.";
    }

    if (strlen($adminPass) < 10) {
        $erreurs[] = 'Le mot de passe organisateur doit faire au moins 10 caracteres.';
    }

    if ($erreurs === []) {
        try {
            // Le secret de signature. Genere ici et jamais ailleurs : le perdre
            // rend toutes les cartes deja imprimees invérifiables.
            $valeurs['keys'] = ['A' => bin2hex(random_bytes(32))];
            $valeurs['active_key_id'] = 'A';
            // Cookies « secure » : indispensable en HTTPS, mais en local on est
            // en http://localhost et un cookie secure ne serait jamais renvoye —
            // la connexion a l'administration tournerait en rond sans message.
            $valeurs['cookie_secure'] = ($_SERVER['HTTPS'] ?? '') !== ''
                || ($_SERVER['SERVER_PORT'] ?? '') === '443';

            // On teste la connexion AVANT d'ecrire quoi que ce soit : mieux vaut
            // une erreur lisible qu'une installation a moitie faite.
            $config = new Config($valeurs);
            $pdo = Db::connect($config);
            Db::migrate($pdo);

            $auth = new Auth($pdo, $config);

            if ($auth->adminCount() === 0) {
                $auth->createAdmin($adminUser, $adminPass);
            }

            Config::write($valeurs);
            proteger();

            $succes = true;
            $identifiants = ['user' => $adminUser];

            // Le fichier a fait son office : il ne doit plus etre atteignable.
            @unlink(__FILE__);
        } catch (Throwable $e) {
            $erreurs[] = 'Installation impossible : ' . $e->getMessage();
        }
    }
}

/** Ecrit les .htaccess de protection, comme le fait bin/install.php. */
function proteger(): void
{
    foreach (['exports', 'qr'] as $dir) {
        $chemin = Config::storagePath($dir);
        if (!is_dir($chemin)) {
            @mkdir($chemin, 0700, true);
        }
    }

    $refus = "Require all denied\nDeny from all\n";

    @file_put_contents(Config::storagePath('.htaccess'), $refus);

    // Sur mutualise, la racine web est imposee : ces dossiers sont servis si on
    // ne les refuse pas explicitement.
    foreach (['src', 'app', 'bin', 'vendor', 'tests'] as $dir) {
        $chemin = Config::rootPath($dir);
        if (is_dir($chemin)) {
            @file_put_contents($chemin . '/.htaccess', $refus);
        }
    }
}

$installateurPresent = is_file(__FILE__);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Installation de Scannem</title>
<style>
  :root { --bg:#f6f7f9; --panel:#fff; --ink:#16181d; --muted:#6b7280; --line:#e3e6ea;
          --accent:#2b5cff; --green:#0f7b45; --green-bg:#e6f5ec; --red:#b4231c;
          --red-bg:#fdeceb; --amber:#92600a; --amber-bg:#fdf3e0; }
  @media (prefers-color-scheme: dark) {
    :root { --bg:#101216; --panel:#181b21; --ink:#e9ebef; --muted:#9aa3b0; --line:#272b33;
            --green:#4ade80; --green-bg:#12301f; --red:#f87171; --red-bg:#331715;
            --amber:#fbbf24; --amber-bg:#332508; }
  }
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--ink);
       font:15px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}
  main{max-width:640px;margin:36px auto 60px;padding:0 18px}
  h1{font-size:23px;margin:0 0 4px;letter-spacing:-.3px}
  h2{font-size:15px;margin:26px 0 12px}
  .sub{color:var(--muted);margin:0 0 22px}
  .panel{background:var(--panel);border:1px solid var(--line);border-radius:11px;padding:20px;margin-bottom:16px}
  label{display:block;font-size:13px;color:var(--muted);margin-bottom:5px}
  input{width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:8px;
        background:var(--bg);color:var(--ink);font-size:15px}
  .field{margin-bottom:14px}
  .row{display:flex;gap:14px;flex-wrap:wrap}
  .row>*{flex:1;min-width:150px}
  button{width:100%;padding:14px;border:none;border-radius:9px;background:var(--accent);
         color:#fff;font-size:16px;font-weight:600;cursor:pointer}
  .flash{padding:13px 16px;border-radius:9px;margin-bottom:16px;font-size:14px}
  .err{background:var(--red-bg);color:var(--red)}
  .ok{background:var(--green-bg);color:var(--green)}
  .warn{background:var(--amber-bg);color:var(--amber)}
  ul.checks{list-style:none;padding:0;margin:0;font-size:14px}
  ul.checks li{padding:6px 0;border-bottom:1px solid var(--line);display:flex;gap:10px}
  ul.checks li:last-child{border-bottom:none}
  .note{font-size:13px;color:var(--muted);margin:12px 0 0}
  code{font-family:"SFMono-Regular",Consolas,monospace;font-size:13px;
       background:var(--bg);padding:2px 5px;border-radius:4px}
  a{color:var(--accent)}
  .choix{display:flex;flex-direction:column;gap:10px;margin-bottom:18px}
  .opt{display:flex;gap:10px;align-items:flex-start;font-size:14px;color:var(--ink);
       border:1px solid var(--line);border-radius:9px;padding:12px;cursor:pointer;margin:0}
  .opt input{width:auto;margin-top:2px;flex:none}
</style>
</head>
<body>
<main>

<?php if ($succes): ?>

  <h1>Installation terminee</h1>
  <p class="sub">Scannem est pret a servir.</p>

  <div class="flash ok">
    Base creee, secret de signature genere, compte
    <strong><?= Http::escape($identifiants['user']) ?></strong> actif.
  </div>

  <?php if (is_file(__FILE__)): ?>
  <div class="flash err">
    <strong>A faire tout de suite :</strong> supprime le fichier
    <code>install.php</code> par FTP. Je n'ai pas pu le supprimer moi-meme
    (droits insuffisants), et tant qu'il est la, il reste une porte d'entree.
  </div>
  <?php else: ?>
  <div class="flash ok">L'installateur s'est supprime tout seul.</div>
  <?php endif; ?>

  <div class="panel">
    <h2 style="margin-top:0">La suite</h2>
    <ol style="padding-left:20px;margin:0">
      <li>Ouvre <a href="<?= Http::escape($base) ?>/admin/">l'administration</a> et connecte-toi.</li>
      <li>Cree un lot de cartes, puis imprime la planche.</li>
      <li>Enrole chaque telephone depuis <strong>Appareils</strong>.</li>
      <li>Ouvre <code><?= Http::escape($base) ?>/scan/</code> sur les telephones. <strong>En HTTPS
          obligatoirement</strong>, sinon le navigateur refuse la camera.
          Seule exception : <code>localhost</code>, que les navigateurs
          considerent comme sur — c'est ce qui permet d'essayer en local.</li>
    </ol>
  </div>

  <div class="flash warn">
    <strong>Sauvegarde le fichier de configuration</strong>
    (<code><?= Http::escape(Config::configFile()) ?></code>) hors du serveur.
    Il contient le secret de signature : sans lui, aucune carte deja imprimee ne
    peut plus etre verifiee.
  </div>

<?php elseif ($dejaInstalle): ?>

  <h1>Deja installe</h1>
  <p class="sub">Scannem est configure sur cet hebergement.</p>

  <div class="flash warn">
    L'installateur refuse de repartir de zero : relancer l'installation
    ecraserait le secret de signature et <strong>rendrait toutes les cartes deja
    imprimees invalides</strong>.
  </div>

  <div class="flash err">
    <strong>Supprime <code>install.php</code> par FTP.</strong>
    Il n'a plus aucune utilite.
  </div>

  <p><a href="<?= Http::escape($base) ?>/admin/">Aller a l'administration</a></p>

<?php else: ?>

  <h1>Installation de Scannem</h1>
  <p class="sub">Une seule etape. Les informations demandees viennent du panneau
     de ton hebergeur.</p>

  <?php foreach ($erreurs as $e): ?>
    <div class="flash err"><?= Http::escape($e) ?></div>
  <?php endforeach; ?>

  <div class="panel">
    <h2 style="margin-top:0">Verifications</h2>
    <ul class="checks">
      <?php foreach (diagnostic() as [$ok, $libelle]): ?>
        <li><span style="color:var(--<?= $ok ? 'green' : 'amber' ?>)"><?= $ok ? '&#10003;' : '&#9888;' ?></span>
            <span><?= Http::escape($libelle) ?></span></li>
      <?php endforeach; ?>
    </ul>

    <?php if (Config::storageIsInsideProject()): ?>
    <div class="flash warn" style="margin:16px 0 0">
      <strong>Le secret sera stocke dans la racine web.</strong>
      Ca fonctionne, mais la seule protection sera un <code>.htaccess</code>.
      Si ton FTP te laisse creer un dossier <em>a cote</em> de <code>htdocs</code>,
      depose a la racine du projet un fichier <code>scannem-local.php</code>
      contenant :
      <br><br>
      <code>&lt;?php return '/home/TON_COMPTE/scannem-donnees';</code>
    </div>
    <?php endif; ?>
  </div>

  <form method="post" class="panel">
    <h2 style="margin-top:0">Base de donnees</h2>

    <div class="choix">
      <label class="opt">
        <input type="radio" name="db_driver" value="sqlite"<?= $enLocal ? ' checked' : '' ?>>
        <span><strong>SQLite</strong> — un simple fichier, rien a creer.
          Parfait pour essayer en local.</span>
      </label>
      <label class="opt">
        <input type="radio" name="db_driver" value="mysql"<?= $enLocal ? '' : ' checked' ?>>
        <span><strong>MySQL</strong> — a choisir sur un hebergement mutualise,
          qui n'offre generalement que celui-la.</span>
      </label>
    </div>

    <div id="mysql">
      <p class="note" style="margin:0 0 14px">
        Cree d'abord une base dans le panneau de ton hebergeur, puis recopie ici
        les identifiants qu'il t'affiche.
      </p>

      <div class="row">
        <div class="field">
          <label for="db_host">Serveur</label>
          <input type="text" id="db_host" name="db_host" value="localhost">
        </div>
        <div class="field">
          <label for="db_port">Port</label>
          <input type="text" id="db_port" name="db_port" value="3306">
        </div>
      </div>

      <div class="field">
        <label for="db_name">Nom de la base</label>
        <input type="text" id="db_name" name="db_name" autocapitalize="off" spellcheck="false">
      </div>

      <div class="row">
        <div class="field">
          <label for="db_user">Utilisateur</label>
          <input type="text" id="db_user" name="db_user" autocapitalize="off" spellcheck="false">
        </div>
        <div class="field">
          <label for="db_pass">Mot de passe</label>
          <input type="password" id="db_pass" name="db_pass" autocomplete="off">
        </div>
      </div>
    </div>

    <h2>Compte organisateur</h2>
    <div class="row">
      <div class="field">
        <label for="admin_user">Nom d'utilisateur</label>
        <input type="text" id="admin_user" name="admin_user" value="admin" required
               autocapitalize="off" spellcheck="false">
      </div>
      <div class="field">
        <label for="admin_pass">Mot de passe (10 caracteres minimum)</label>
        <input type="password" id="admin_pass" name="admin_pass" required autocomplete="new-password">
      </div>
    </div>

    <button type="submit">Installer</button>

    <p class="note">
      Choisis un vrai mot de passe : ce compte permet de generer des cartes
      valides et d'annuler celles des autres.
    </p>
  </form>

  <script>
    /*
     * Simple confort : masquer les champs MySQL quand SQLite est choisi. Aucun
     * champ n'est marque « required », donc le formulaire reste entierement
     * utilisable sans JavaScript — la validation qui compte est cote serveur.
     */
    (function () {
      var bloc = document.getElementById('mysql');
      var choix = document.querySelectorAll('input[name=db_driver]');

      function refletter() {
        var sqlite = document.querySelector('input[name=db_driver]:checked').value === 'sqlite';
        bloc.style.display = sqlite ? 'none' : '';
      }

      for (var i = 0; i < choix.length; i++) {
        choix[i].addEventListener('change', refletter);
      }

      refletter();
    })();
  </script>

<?php endif; ?>

</main>
</body>
</html>
