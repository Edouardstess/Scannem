<?php
/**
 * @var array<int, array<string, mixed>> $clients
 * @var array<string, mixed>             $pagination
 * @var string                           $search
 */

use App\Core\View;

View::extend('layouts.admin');
View::startSection('content');
?>

<div class="panel-actions">
    <form class="search" method="get" action="<?= e(url('/admin/clients')) ?>" role="search">
        <label class="sr-only" for="q">Rechercher un client</label>
        <input type="search" id="q" name="q" value="<?= e($search) ?>"
               placeholder="Nom, e-mail, société, téléphone…">
        <button class="button button--small" type="submit">Rechercher</button>
        <?php if ($search !== ''): ?>
            <a class="link-arrow" href="<?= e(url('/admin/clients')) ?>">Réinitialiser</a>
        <?php endif; ?>
    </form>

    <a class="button" href="<?= e(url('/admin/clients/create')) ?>">Nouveau client</a>
</div>

<section class="panel">
    <?php if ($clients === []): ?>
        <p class="empty">
            <?= $search === '' ? 'Aucun client enregistré.' : 'Aucun client ne correspond à cette recherche.' ?>
        </p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Nom</th>
                        <th scope="col">E-mail</th>
                        <th scope="col">Téléphone</th>
                        <th scope="col" class="numeric">Événements</th>
                        <th scope="col" class="numeric">Galeries</th>
                        <th scope="col">Dernière activité</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clients as $client): ?>
                        <tr>
                            <th scope="row">
                                <a href="<?= e(url('/admin/clients/' . (int) $client['id'])) ?>">
                                    <?= e(trim((string) $client['first_name'] . ' ' . (string) $client['last_name'])) ?>
                                </a>
                                <?php if (($client['company'] ?? '') !== ''): ?>
                                    <span class="table__sub"><?= e((string) $client['company']) ?></span>
                                <?php endif; ?>
                            </th>
                            <td>
                                <?php if (($client['email'] ?? '') !== ''): ?>
                                    <a href="mailto:<?= e((string) $client['email']) ?>"><?= e((string) $client['email']) ?></a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><?= e((string) ($client['phone'] ?? '')) ?: '—' ?></td>
                            <td class="numeric"><?= (int) $client['events_count'] ?></td>
                            <td class="numeric"><?= (int) $client['galleries_count'] ?></td>
                            <td><?= e(format_datetime($client['last_activity_at'] ?? null)) ?></td>
                            <td class="row-actions">
                                <a href="<?= e(url('/admin/clients/' . (int) $client['id'] . '/edit')) ?>">Modifier</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?= View::include('partials.pagination', [
            'pagination' => $pagination,
            'basePath'   => '/admin/clients',
            'query'      => ['q' => $search],
        ]) ?>
    <?php endif; ?>
</section>

<?php View::endSection(); ?>
