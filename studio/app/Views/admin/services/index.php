<?php
/** @var array<int, array<string, mixed>> $services */

use App\Core\View;

View::extend('layouts.admin');
View::startSection('content');
?>

<div class="panel-actions">
    <a class="button" href="<?= e(url('/admin/services/create')) ?>">Nouvelle prestation</a>
</div>

<section class="panel">
    <?php if ($services === []): ?>
        <p class="empty">Aucune prestation. La page « Prestations » du site restera vide.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Prestation</th>
                        <th scope="col">Tarif</th>
                        <th scope="col">Durée</th>
                        <th scope="col" class="numeric">Ordre</th>
                        <th scope="col">Statut</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $service): ?>
                        <tr>
                            <th scope="row">
                                <a href="<?= e(url('/admin/services/' . (int) $service['id'] . '/edit')) ?>">
                                    <?= e((string) $service['title']) ?>
                                </a>
                                <span class="table__sub">/services/<?= e((string) $service['slug']) ?></span>
                            </th>
                            <td>
                                <?= ($service['price_from'] ?? null) !== null
                                    ? e(number_format((float) $service['price_from'], 0, ',', ' ') . ' ' . (string) $service['currency'])
                                    : '—' ?>
                            </td>
                            <td><?= e((string) ($service['duration'] ?? '')) ?: '—' ?></td>
                            <td class="numeric"><?= (int) $service['sort_order'] ?></td>
                            <td>
                                <span class="badge badge--<?= (string) $service['status'] === 'published' ? 'active' : 'draft' ?>">
                                    <?= (string) $service['status'] === 'published' ? 'Publiée' : 'Brouillon' ?>
                                </span>
                            </td>
                            <td class="row-actions">
                                <a href="<?= e(url('/admin/services/' . (int) $service['id'] . '/edit')) ?>">Modifier</a>
                                <a href="<?= e(url('/services/' . (string) $service['slug'])) ?>"
                                   target="_blank" rel="noopener">Voir</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php View::endSection(); ?>
