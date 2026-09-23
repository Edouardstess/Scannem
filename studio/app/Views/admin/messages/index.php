<?php
/**
 * @var array<int, array<string, mixed>> $messages
 * @var array<string, mixed>             $pagination
 * @var string                           $status
 * @var int                              $unread
 */

use App\Core\View;

View::extend('layouts.admin');
View::startSection('content');

$labels = ['new' => 'Nouveaux', 'read' => 'Lus', 'archived' => 'Archivés'];
?>

<div class="panel-actions">
    <nav class="tabs" aria-label="Filtrer les messages">
        <a class="tabs__link<?= $status === '' ? ' is-active' : '' ?>"
           href="<?= e(url('/admin/messages')) ?>">Tous</a>
        <?php foreach ($labels as $key => $label): ?>
            <a class="tabs__link<?= $status === $key ? ' is-active' : '' ?>"
               href="<?= e(url('/admin/messages?status=' . $key)) ?>">
                <?= e($label) ?><?= $key === 'new' && $unread > 0 ? ' (' . (int) $unread . ')' : '' ?>
            </a>
        <?php endforeach; ?>
    </nav>
</div>

<section class="panel">
    <?php if ($messages === []): ?>
        <p class="empty">Aucun message.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Expéditeur</th>
                        <th scope="col">Sujet</th>
                        <th scope="col">Date souhaitée</th>
                        <th scope="col">Reçu le</th>
                        <th scope="col">Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($messages as $message): ?>
                        <tr<?= (string) $message['status'] === 'new' ? ' class="is-unread"' : '' ?>>
                            <th scope="row">
                                <a href="<?= e(url('/admin/messages/' . (int) $message['id'])) ?>">
                                    <?= e((string) $message['name']) ?>
                                </a>
                                <span class="table__sub"><?= e((string) $message['email']) ?></span>
                            </th>
                            <td>
                                <?= e((string) ($message['subject'] ?? '')) ?: '—' ?>
                                <span class="table__sub"><?= e(str_excerpt((string) $message['message'], 70)) ?></span>
                            </td>
                            <td><?= e(format_date($message['preferred_date'] ?? null)) ?></td>
                            <td><?= e(format_datetime((string) $message['created_at'])) ?></td>
                            <td>
                                <span class="badge badge--<?= (string) $message['status'] === 'new' ? 'active' : 'draft' ?>">
                                    <?= e($labels[(string) $message['status']] ?? (string) $message['status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?= View::include('partials.pagination', [
            'pagination' => $pagination,
            'basePath'   => '/admin/messages',
            'query'      => ['status' => $status],
        ]) ?>
    <?php endif; ?>
</section>

<?php View::endSection(); ?>
