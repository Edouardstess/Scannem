<?php

declare(strict_types=1);

/**
 * Journal des scans.
 *
 * Tous les scans y figurent, refus compris. C'est la piece a conviction en cas
 * de contestation a la porte : elle dit qui a scanne quoi, quand et ou.
 *
 * @var Scannem\CardRepository $cards
 * @var string $flashBlock
 */

use Scannem\Http;
use Scannem\ScanResult;

$title = 'Journal des scans';
$limit = isset($_GET['n']) && is_numeric($_GET['n']) ? max(20, min(1000, (int) $_GET['n'])) : 150;
$rows = $cards->recentScans($limit);

$compte = [];
foreach ($rows as $r) {
    $key = (string) $r['result'];
    $compte[$key] = ($compte[$key] ?? 0) + 1;
}

echo $flashBlock;
?>
<h1>Journal des scans</h1>
<p class="sub">Les <?= count($rows) ?> derniers scans, refus compris. Actualise la page pour voir les nouveaux.</p>

<?php if ($compte !== []): ?>
<div class="panel">
  <div class="stats">
    <?php foreach ($compte as $result => $n): ?>
      <div class="stat">
        <div class="n" style="color:var(--<?= ScanResult::color($result) === 'green' ? 'green' : (ScanResult::color($result) === 'amber' ? 'amber' : 'red') ?>)"><?= $n ?></div>
        <div class="k"><?= Http::escape(ScanResult::label($result)) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="panel">
<?php if ($rows === []): ?>
  <div class="empty">Aucun scan enregistre.</div>
<?php else: ?>
  <table>
    <thead>
      <tr><th>Heure (UTC)</th><th>Resultat</th><th>Carte</th><th>Appareil</th><th>Mode</th></tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r):
        $result = (string) $r['result'];
        $isPeek = str_starts_with($result, 'peek:');
        $base = $isPeek ? substr($result, 5) : $result;
    ?>
      <tr>
        <td class="mono" style="color:var(--muted);white-space:nowrap">
          <?= Http::escape(str_replace(['T', 'Z'], [' ', ''], (string) $r['server_at'])) ?>
        </td>
        <td>
          <span class="tag <?= ScanResult::color($base) ?>"><?= Http::escape(ScanResult::label($base)) ?></span>
          <?= $isPeek ? '<span class="tag grey">verification seule</span>' : '' ?>
        </td>
        <td class="mono"><?= $r['uid'] !== null ? Http::escape(implode(' ', str_split((string) $r['uid'], 4))) : '<span style="color:var(--muted)">—</span>' ?></td>
        <td><?= Http::escape($r['device_label'] ?? '—') ?></td>
        <td>
          <?= ((int) $r['was_offline']) === 1
              ? '<span class="tag amber">hors-ligne</span>'
              : '<span style="color:var(--muted);font-size:13px">en ligne</span>' ?>
        </td>
      </tr>
      <?php if ($r['note'] !== null && $r['note'] !== ''): ?>
      <tr>
        <td></td>
        <td colspan="4" style="color:var(--muted);font-size:13px;padding-top:0">
          <?= Http::escape((string) $r['note']) ?>
        </td>
      </tr>
      <?php endif; ?>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div style="margin-top:16px;display:flex;gap:8px">
    <?php foreach ([150, 400, 1000] as $n): ?>
      <a class="btn" style="<?= $limit === $n ? 'border-color:var(--accent);color:var(--accent)' : '' ?>"
         href="/admin/?p=scans&amp;n=<?= $n ?>">Voir <?= $n ?></a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
</div>
