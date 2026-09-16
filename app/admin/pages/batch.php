<?php

declare(strict_types=1);

/**
 * Detail d'un lot : statistiques, impression, liste des cartes.
 *
 * @var Scannem\CardRepository $cards
 * @var string $csrf
 * @var string $flashBlock
 */

use Scannem\Http;

$id = (int) ($_GET['id'] ?? 0);
$batch = $cards->batch($id);

if ($batch === null) {
    $title = 'Lot introuvable';
    echo '<h1>Lot introuvable</h1><p class="sub"><a href="' . $base . '/admin/?p=lots">Retour aux lots</a></p>';

    return;
}

$title = (string) $batch['name'];
$stats = $cards->batchStats($id);

// Filtre de statut, pratique pour retrouver rapidement les cartes non utilisees.
$filter = isset($_GET['f']) && is_string($_GET['f']) ? $_GET['f'] : 'all';
$rows = $cards->cardsOfBatch($id);

if (in_array($filter, ['active', 'used', 'revoked'], true)) {
    $rows = array_values(array_filter($rows, static fn (array $r): bool => $r['status'] === $filter));
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 100;
$total = count($rows);
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$slice = array_slice($rows, ($page - 1) * $perPage, $perPage);

$back = $base . '/admin/?p=batch&id=' . $id;

echo $flashBlock;
?>
<h1><?= Http::escape($batch['name']) ?></h1>
<p class="sub">
  Lot #<?= $id ?><?= $batch['event_date'] !== null ? ' &middot; ' . Http::escape($batch['event_date']) : '' ?>
  &middot; cree le <?= Http::escape(substr((string) $batch['created_at'], 0, 10)) ?>
</p>

<div class="panel">
  <div class="stats">
    <div class="stat"><div class="n"><?= $stats['total'] ?></div><div class="k">cartes</div></div>
    <div class="stat"><div class="n" style="color:var(--green)"><?= $stats['used'] ?></div><div class="k">entrees</div></div>
    <div class="stat"><div class="n"><?= $stats['active'] ?></div><div class="k">non utilisees</div></div>
    <div class="stat"><div class="n" style="color:var(--red)"><?= $stats['revoked'] ?></div><div class="k">annulees</div></div>
  </div>
  <div style="margin-top:18px;display:flex;gap:10px;flex-wrap:wrap">
    <a class="btn" href="<?= $base ?>/admin/?p=sheet&amp;id=<?= $id ?>" target="_blank">Planche d'impression</a>
    <a class="btn" href="<?= $base ?>/admin/?p=csv&amp;id=<?= $id ?>">Exporter en CSV</a>
  </div>
  <p class="note">
    La planche et le CSV contiennent les codes complets : ils valent les cartes elles-memes.
    Quiconque y accede peut imprimer des cartes qui passeront le controle. Ne les laisse pas
    sur le serveur apres l'impression.
  </p>
</div>

<div class="panel">
  <h2 style="margin-top:0">Cartes</h2>
  <div style="margin-bottom:14px;display:flex;gap:6px;flex-wrap:wrap">
    <?php foreach (['all' => 'Toutes', 'active' => 'Non utilisees', 'used' => 'Entrees', 'revoked' => 'Annulees'] as $key => $label): ?>
      <a class="btn" style="<?= $filter === $key ? 'border-color:var(--accent);color:var(--accent)' : '' ?>"
         href="<?= $base ?>/admin/?p=batch&amp;id=<?= $id ?>&amp;f=<?= $key ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>

  <?php if ($slice === []): ?>
    <div class="empty">Aucune carte dans ce filtre.</div>
  <?php else: ?>
  <table>
    <thead><tr><th>Code</th><th>Statut</th><th>Utilisee le</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($slice as $r):
        $uid = (string) $r['uid'];
        [$tag, $cls] = match ($r['status']) {
            'used' => ['Entree', 'green'],
            'revoked' => ['Annulee', 'red'],
            default => ['Valable', 'grey'],
        };
    ?>
      <tr>
        <td class="mono"><?= Http::escape(implode(' ', str_split($uid, 4))) ?></td>
        <td><span class="tag <?= $cls ?>"><?= $tag ?></span></td>
        <td style="color:var(--muted)"><?= Http::escape($r['used_at'] !== null ? str_replace(['T', 'Z'], [' ', ''], (string) $r['used_at']) : '—') ?></td>
        <td style="text-align:right">
          <?php if ($r['status'] === 'active'): ?>
            <form method="post" action="<?= $base ?>/admin/?p=batch&amp;id=<?= $id ?>" style="display:inline"
                  onsubmit="return confirm('Annuler cette carte ? Elle sera refusee a l entree.')">
              <?= $csrf ?>
              <input type="hidden" name="action" value="revoke">
              <input type="hidden" name="uid" value="<?= Http::escape($uid) ?>">
              <input type="hidden" name="back" value="<?= Http::escape($back . '&f=' . $filter) ?>">
              <button type="submit" class="danger">Annuler</button>
            </form>
          <?php elseif ($r['status'] === 'revoked'): ?>
            <form method="post" action="<?= $base ?>/admin/?p=batch&amp;id=<?= $id ?>" style="display:inline">
              <?= $csrf ?>
              <input type="hidden" name="action" value="restore">
              <input type="hidden" name="uid" value="<?= Http::escape($uid) ?>">
              <input type="hidden" name="back" value="<?= Http::escape($back . '&f=' . $filter) ?>">
              <button type="submit">Remettre en circulation</button>
            </form>
          <?php else: ?>
            <span style="color:var(--muted);font-size:13px">la personne est entree</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <?php if ($pages > 1): ?>
    <div style="margin-top:16px;display:flex;gap:6px;align-items:center;flex-wrap:wrap">
      <?php for ($i = 1; $i <= min($pages, 30); $i++): ?>
        <a class="btn" style="<?= $i === $page ? 'border-color:var(--accent);color:var(--accent)' : '' ?>;padding:6px 11px"
           href="<?= $base ?>/admin/?p=batch&amp;id=<?= $id ?>&amp;f=<?= $filter ?>&amp;page=<?= $i ?>"><?= $i ?></a>
      <?php endfor; ?>
      <span style="color:var(--muted);font-size:13px"><?= $total ?> cartes</span>
    </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<p><a href="<?= $base ?>/admin/?p=lots">&larr; Tous les lots</a></p>
