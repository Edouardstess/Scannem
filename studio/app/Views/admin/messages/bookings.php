<?php
/**
 * @var array<int, array<string, mixed>> $bookings
 * @var array<string, mixed>             $pagination
 * @var string                           $status
 * @var int                              $pending
 */

use App\Core\View;

View::extend('layouts.admin');
View::startSection('content');

$labels = [
    'pending'   => 'En attente',
    'confirmed' => 'Confirmée',
    'declined'  => 'Refusée',
    'archived'  => 'Archivée',
];
?>

<div class="panel-actions">
    <nav class="tabs" aria-label="Filtrer les demandes">
        <a class="tabs__link<?= $status === '' ? ' is-active' : '' ?>"
           href="<?= e(url('/admin/bookings')) ?>">Toutes</a>
        <?php foreach ($labels as $key => $label): ?>
            <a class="tabs__link<?= $status === $key ? ' is-active' : '' ?>"
               href="<?= e(url('/admin/bookings?status=' . $key)) ?>">
                <?= e($label) ?><?= $key === 'pending' && $pending > 0 ? ' (' . (int) $pending . ')' : '' ?>
            </a>
        <?php endforeach; ?>
    </nav>
</div>

<section class="panel">
    <?php if ($bookings === []): ?>
        <p class="empty">Aucune demande de réservation.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Demandeur</th>
                        <th scope="col">Prestation</th>
                        <th scope="col">Date souhaitée</th>
                        <th scope="col">Lieu</th>
                        <th scope="col">Reçue le</th>
                        <th scope="col">Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking): ?>
                        <tr<?= (string) $booking['status'] === 'pending' ? ' class="is-unread"' : '' ?>>
                            <th scope="row">
                                <?= e((string) $booking['name']) ?>
                                <span class="table__sub">
                                    <a href="mailto:<?= e((string) $booking['email']) ?>">
                                        <?= e((string) $booking['email']) ?>
                                    </a>
                                    <?= ($booking['phone'] ?? '') !== '' ? ' · ' . e((string) $booking['phone']) : '' ?>
                                </span>
                                <?php if (($booking['message'] ?? '') !== ''): ?>
                                    <span class="table__sub"><?= e(str_excerpt((string) $booking['message'], 90)) ?></span>
                                <?php endif; ?>
                            </th>
                            <td><?= e((string) ($booking['service_title'] ?? $booking['service_label'] ?? '')) ?: '—' ?></td>
                            <td><?= e(format_date($booking['preferred_date'] ?? null)) ?></td>
                            <td><?= e((string) ($booking['location'] ?? '')) ?: '—' ?></td>
                            <td><?= e(format_datetime((string) $booking['created_at'])) ?></td>
                            <td>
                                <form method="post"
                                      action="<?= e(url('/admin/bookings/' . (int) $booking['id'] . '/status')) ?>"
                                      class="inline-form">
                                    <?= csrf_field() ?>
                                    <label class="sr-only" for="status-<?= (int) $booking['id'] ?>">Statut</label>
                                    <select id="status-<?= (int) $booking['id'] ?>" name="status">
                                        <?php foreach ($labels as $key => $label): ?>
                                            <option value="<?= e($key) ?>"
                                                <?= (string) $booking['status'] === $key ? 'selected' : '' ?>>
                                                <?= e($label) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="button button--small" type="submit">OK</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?= View::include('partials.pagination', [
            'pagination' => $pagination,
            'basePath'   => '/admin/bookings',
            'query'      => ['status' => $status],
        ]) ?>
    <?php endif; ?>
</section>

<?php View::endSection(); ?>
