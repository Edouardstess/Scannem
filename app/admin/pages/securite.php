<?php

declare(strict_types=1);

/**
 * Controle de securite de l'installation.
 *
 * Se lance sur l'hebergement reel, parce que c'est le seul endroit ou la
 * question se pose : la protection des fichiers sensibles depend de la
 * configuration du serveur, pas du code.
 *
 * @var string $flashBlock
 */

use Scannem\Http;
use Scannem\SecurityCheck;

$title = 'Securite';
$rapport = SecurityCheck::run();

$critiques = array_filter(
    $rapport['checks'],
    static fn (array $c): bool => !$c['ok'] && $c['critique']
);

$avertissements = array_filter(
    $rapport['checks'],
    static fn (array $c): bool => !$c['ok'] && !$c['critique']
);

echo $flashBlock;
?>
<h1>Securite de l'installation</h1>
<p class="sub">
  Ces controles interrogent le site depuis lui-meme, exactement comme le ferait
  un curieux.
</p>

<?php if ($critiques !== []): ?>
  <div class="flash err">
    <strong><?= count($critiques) ?> probleme(s) a corriger.</strong>
    Tant qu'ils sont la, le systeme ne tient pas ses promesses.
  </div>
<?php elseif ($avertissements !== []): ?>
  <div class="flash warn">
    Rien de critique, mais <?= count($avertissements) ?> point(s) ameliorable(s).
  </div>
<?php else: ?>
  <div class="flash ok">
    Tous les controles passent.
  </div>
<?php endif; ?>

<div class="panel">
  <table>
    <thead><tr><th>Controle</th><th>Etat</th><th>Detail</th></tr></thead>
    <tbody>
    <?php foreach ($rapport['checks'] as $c): ?>
      <tr>
        <td><strong><?= Http::escape($c['nom']) ?></strong></td>
        <td>
          <?php if ($c['ok']): ?>
            <span class="tag green">OK</span>
          <?php elseif ($c['critique']): ?>
            <span class="tag red">CRITIQUE</span>
          <?php else: ?>
            <span class="tag amber">a revoir</span>
          <?php endif; ?>
        </td>
        <td style="color:var(--muted);font-size:13px"><?= Http::escape($c['detail']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <?php if ($rapport['base_url'] !== null): ?>
    <p class="note">Site interroge : <code><?= Http::escape($rapport['base_url']) ?></code></p>
  <?php endif; ?>
</div>

<div class="panel">
  <h2 style="margin-top:0">Ce qui compte vraiment</h2>
  <p style="font-size:14px;margin:0 0 12px">
    <strong>Le secret de signature ne doit jamais etre telechargeable.</strong>
    Il vit dans <code>storage/config.php</code>. Qui l'obtient peut fabriquer des
    cartes valides a l'infini, et plus rien n'a de valeur : ni la signature, ni
    l'usage unique.
  </p>
  <p style="font-size:14px;margin:0 0 12px">
    <strong>Le HTTPS n'est pas un confort.</strong> Les navigateurs refusent
    l'acces a la camera sur une connexion non chiffree. Sans HTTPS, le scanner ne
    demarre tout simplement pas.
  </p>
  <p style="font-size:14px;margin:0">
    <strong>Sauvegarde <code><?= Http::escape(\Scannem\Config::configFile()) ?></code>
    hors du serveur.</strong> Sans ce fichier, aucune carte deja imprimee ne peut
    plus etre verifiee. C'est le seul element vraiment irremplacable.
  </p>
</div>
