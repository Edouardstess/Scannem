<?php

declare(strict_types=1);

/**
 * Appareils de controle et codes d'enrolement.
 *
 * @var Scannem\Auth $auth
 * @var string $csrf
 * @var string $flashBlock
 */

use Scannem\Http;

$title = 'Appareils';
$devices = $auth->devices();
$pending = $auth->pendingEnrollCodes();

// Code fraichement genere : affiche une seule fois, puis efface de la session.
$fresh = $_SESSION['fresh_code'] ?? null;
unset($_SESSION['fresh_code']);

echo $flashBlock;
?>
<h1>Appareils de controle</h1>
<p class="sub">Chaque telephone doit etre enrole avant de pouvoir scanner.</p>

<?php if ($fresh !== null): ?>
<div class="panel" style="border-color:var(--accent)">
  <h2 style="margin-top:0">Code d'enrolement pour &laquo; <?= Http::escape($fresh['label']) ?> &raquo;</h2>
  <div class="code-box"><?= Http::escape($fresh['code']) ?></div>
  <p class="note">
    Ce code ne sera plus affiche : seule son empreinte est conservee.
    Sur le telephone, ouvre <code><?= Http::escape($base) ?>/scan/</code> et
    saisis-le. Il est valable
    15 minutes et pour un seul appareil.
  </p>
</div>
<?php endif; ?>

<div class="panel">
  <h2 style="margin-top:0">Enroler un nouvel appareil</h2>
  <form method="post" action="<?= $base ?>/admin/?p=devices">
    <?= $csrf ?>
    <input type="hidden" name="action" value="enroll_code">
    <div class="row">
      <div class="field">
        <label for="label">Nom de l'appareil ou de la porte</label>
        <input type="text" id="label" name="label" placeholder="Porte A" required>
      </div>
      <div class="field" style="display:flex;align-items:flex-end">
        <button type="submit" class="primary">Generer un code</button>
      </div>
    </div>
  </form>
  <p class="note">
    Sans enrolement, l'API refuse tout. C'est ce qui empeche un inconnu d'appeler la
    route de validation depuis l'exterieur et de bruler les cartes a distance.
  </p>
</div>

<?php if ($pending !== []): ?>
<div class="panel">
  <h2 style="margin-top:0">Codes en attente</h2>
  <table>
    <thead><tr><th>Appareil</th><th>Expire a (UTC)</th></tr></thead>
    <tbody>
    <?php foreach ($pending as $c): ?>
      <tr>
        <td><?= Http::escape($c['label']) ?></td>
        <td class="mono" style="color:var(--muted)"><?= Http::escape(str_replace(['T', 'Z'], [' ', ''], (string) $c['expires_at'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="note">Le code lui-meme n'est pas recuperable : s'il est perdu, genere-en un autre.</p>
</div>
<?php endif; ?>

<div class="panel">
  <h2 style="margin-top:0">Appareils enroles</h2>
  <?php if ($devices === []): ?>
    <div class="empty">Aucun appareil enrole.</div>
  <?php else: ?>
  <table>
    <thead><tr><th>Appareil</th><th>Etat</th><th>Dernier scan (UTC)</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($devices as $d): $on = ((int) $d['active']) === 1; ?>
      <tr>
        <td><strong><?= Http::escape($d['label']) ?></strong></td>
        <td><span class="tag <?= $on ? 'green' : 'grey' ?>"><?= $on ? 'actif' : 'desactive' ?></span></td>
        <td class="mono" style="color:var(--muted)">
          <?= Http::escape($d['last_seen_at'] !== null ? str_replace(['T', 'Z'], [' ', ''], (string) $d['last_seen_at']) : 'jamais') ?>
        </td>
        <td style="text-align:right">
          <form method="post" action="<?= $base ?>/admin/?p=devices" style="display:inline">
            <?= $csrf ?>
            <input type="hidden" name="action" value="device_toggle">
            <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
            <input type="hidden" name="to" value="<?= $on ? '0' : '1' ?>">
            <button type="submit" class="<?= $on ? 'danger' : '' ?>">
              <?= $on ? 'Desactiver' : 'Reactiver' ?>
            </button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="note">
    Un telephone perdu se desactive ici : ses scans sont refuses immediatement.
    Attention, un appareil deja hors-ligne garde sa file locale et la
    synchronisera si on le reactive.
  </p>
  <?php endif; ?>
</div>
