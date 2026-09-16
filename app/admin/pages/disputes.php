<?php

declare(strict_types=1);

/**
 * Litiges : les doublons que le mode hors-ligne n'a pas pu empecher.
 *
 * @var Scannem\CardRepository $cards
 * @var string $flashBlock
 */

use Scannem\Http;
use Scannem\ScanResult;

$title = 'Litiges';
$disputes = $cards->disputes();

echo $flashBlock;
?>
<h1>Litiges</h1>
<p class="sub">
  Cartes admises par un appareil hors-ligne alors qu'elles avaient deja servi ailleurs.
</p>

<div class="panel" style="border-left:3px solid var(--amber)">
  <p style="margin:0;font-size:14px">
    <strong>Pourquoi ces cas existent.</strong>
    Deux portes deconnectees ne peuvent pas se consulter : si la meme carte est
    presentee aux deux pendant une coupure reseau, les deux laissent entrer. Aucun
    systeme ne peut l'empecher, c'est une limite du hors-ligne et non un defaut de
    configuration. Ce qui est possible, et ce que fait cette page, c'est de le
    constater des le retour du reseau, avec l'heure et la porte de chaque passage.
  </p>
  <p style="margin:12px 0 0;font-size:14px">
    <strong>Comment l'eviter.</strong>
    Garder les appareils connectes pendant l'entree. Un partage de connexion depuis
    un telephone suffit : la verification en ligne tient dans un aller-retour de
    quelques centaines d'octets.
  </p>
</div>

<div class="panel">
<?php if ($disputes === []): ?>
  <div class="empty">Aucun litige. Toutes les cartes scannees hors-ligne ont ete confirmees par le serveur.</div>
<?php else: ?>
  <p class="sub" style="margin-top:0"><?= count($disputes) ?> carte(s) concernee(s).</p>
  <?php foreach ($disputes as $d): ?>
    <div style="border:1px solid var(--line);border-radius:9px;padding:14px;margin-bottom:12px">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
        <strong class="mono"><?= Http::escape(implode(' ', str_split((string) $d['uid'], 4))) ?></strong>
        <span class="tag red"><?= (int) $d['tentatives'] ?> passages enregistres</span>
      </div>
      <table style="margin-top:10px">
        <thead><tr><th>Heure serveur</th><th>Heure appareil</th><th>Resultat</th><th>Porte</th><th>Mode</th></tr></thead>
        <tbody>
        <?php foreach ($d['scans'] as $s):
            $result = (string) $s['result'];
            if (str_starts_with($result, 'peek:')) {
                continue;
            }
        ?>
          <tr>
            <td class="mono" style="color:var(--muted)"><?= Http::escape(str_replace(['T', 'Z'], [' ', ''], (string) $s['server_at'])) ?></td>
            <td class="mono" style="color:var(--muted)"><?= Http::escape($s['client_at'] !== null ? str_replace(['T', 'Z'], [' ', ''], (string) $s['client_at']) : '—') ?></td>
            <td><span class="tag <?= ScanResult::color($result) ?>"><?= Http::escape(ScanResult::label($result)) ?></span></td>
            <td><?= Http::escape($s['device_label'] ?? '—') ?></td>
            <td><?= ((int) $s['was_offline']) === 1 ? '<span class="tag amber">hors-ligne</span>' : 'en ligne' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <p class="note" style="margin-bottom:0">
        L'heure appareil est celle du telephone au moment du scan ; l'heure serveur celle de la
        synchronisation. C'est l'heure appareil qui dit qui s'est presente en premier.
      </p>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
</div>
