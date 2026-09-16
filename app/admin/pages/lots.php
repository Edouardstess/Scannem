<?php

declare(strict_types=1);

/**
 * Liste des lots et creation.
 *
 * @var Scannem\CardRepository $cards
 * @var string $csrf
 * @var string $flashBlock
 */

use Scannem\Http;

$title = 'Lots de cartes';
$batches = $cards->batches();

echo $flashBlock;
?>
<h1>Lots de cartes</h1>
<p class="sub">Chaque lot produit des cartes a QR code uniques, valables une seule entree.</p>

<div class="panel">
  <h2 style="margin-top:0">Nouveau lot</h2>
  <form method="post" action="/admin/?p=lots">
    <?= $csrf ?>
    <input type="hidden" name="action" value="create_batch">
    <div class="row">
      <div class="field">
        <label for="name">Nom du lot</label>
        <input type="text" id="name" name="name" placeholder="Soiree du 12 octobre" required>
      </div>
      <div class="field">
        <label for="qty">Nombre de cartes</label>
        <input type="number" id="qty" name="qty" min="1" max="20000" value="100" required>
      </div>
      <div class="field">
        <label for="date">Date de l'evenement</label>
        <input type="date" id="date" name="date">
      </div>
    </div>
    <button type="submit" class="primary">Generer le lot</button>
  </form>
  <p class="note">
    Le nom et la date sont imprimes sur chaque carte. Au-dela de quelques milliers de
    cartes, la generation est plus rapide en ligne de commande :
    <code>php bin/generate-batch.php --name="..." --qty=5000</code>
  </p>
</div>

<div class="panel">
  <h2 style="margin-top:0">Lots existants</h2>
  <?php if ($batches === []): ?>
    <div class="empty">Aucun lot pour l'instant. Cree le premier ci-dessus.</div>
  <?php else: ?>
  <table>
    <thead>
      <tr>
        <th>Lot</th><th>Date</th><th>Cartes</th>
        <th>Entrees</th><th>Annulees</th><th></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($batches as $b):
        $stats = $cards->batchStats((int) $b['id']);
        $pct = $stats['total'] > 0 ? (int) round($stats['used'] / $stats['total'] * 100) : 0;
    ?>
      <tr>
        <td>
          <strong><?= Http::escape($b['name']) ?></strong><br>
          <span style="color:var(--muted);font-size:12px">#<?= (int) $b['id'] ?></span>
        </td>
        <td><?= Http::escape($b['event_date'] ?? '—') ?></td>
        <td><?= $stats['total'] ?></td>
        <td><?= $stats['used'] ?> <span style="color:var(--muted)">(<?= $pct ?>%)</span></td>
        <td><?= $stats['revoked'] > 0 ? '<span class="tag red">' . $stats['revoked'] . '</span>' : '—' ?></td>
        <td style="text-align:right;white-space:nowrap">
          <a class="btn" href="/admin/?p=batch&amp;id=<?= (int) $b['id'] ?>">Ouvrir</a>
          <a class="btn" href="/admin/?p=sheet&amp;id=<?= (int) $b['id'] ?>" target="_blank">Imprimer</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
