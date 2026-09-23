<?php
/**
 * @var array<int, array<string, mixed>> $galleries
 * @var array<string, mixed>             $pagination
 * @var string                           $search
 * @var string                           $status
 * @var array<int, string>               $statuses
 */

use App\Core\View;
use App\Models\GalleryStatus;
use App\Repositories\GalleryRepository;

View::extend('layouts.admin');
View::startSection('content');
?>

<div class="panel-actions">
    <form class="search" method="get" action="<?= e(url('/admin/galleries')) ?>" role="search">
        <label class="sr-only" for="q">Rechercher une galerie</label>
        <input type="search" id="q" name="q" value="<?= e($search) ?>" placeholder="Galerie, événement, client…">

        <label class="sr-only" for="status">Statut</label>
        <select id="status" name="status">
            <option value="">Tous les statuts</option>
            <?php foreach ($statuses as $option): ?>
                <option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>>
                    <?= e(GalleryStatus::label($option)) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button class="button button--small" type="submit">Filtrer</button>
    </form>

    <a class="button" href="<?= e(url('/admin/galleries/create')) ?>">Nouvelle galerie</a>
</div>

<section class="panel">
    <?php if ($galleries === []): ?>
        <p class="empty">Aucune galerie ne correspond.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Galerie</th>
                        <th scope="col">Client</th>
                        <th scope="col" class="numeric">Photos</th>
                        <th scope="col" class="numeric">Vues</th>
                        <th scope="col" class="numeric">Téléch.</th>
                        <th scope="col">Expiration</th>
                        <th scope="col">Statut</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($galleries as $gallery): ?>
                        <?php $expired = GalleryRepository::hasExpired($gallery); ?>
                        <tr<?= $expired ? ' class="is-expired"' : '' ?>>
                            <th scope="row">
                                <a href="<?= e(url('/admin/galleries/' . (int) $gallery['id'])) ?>">
                                    <?= e((string) $gallery['title']) ?>
                                </a>
                                <span class="table__sub"><?= e((string) $gallery['event_title']) ?></span>
                            </th>
                            <td>
                                <a href="<?= e(url('/admin/clients/' . (int) $gallery['client_id'])) ?>">
                                    <?= e(trim((string) $gallery['first_name'] . ' ' . (string) $gallery['last_name'])) ?>
                                </a>
                            </td>
                            <td class="numeric"><?= (int) $gallery['photos_count'] ?></td>
                            <td class="numeric"><?= (int) $gallery['views_count'] ?></td>
                            <td class="numeric"><?= (int) $gallery['downloads_count'] ?></td>
                            <td>
                                <?= e(format_datetime($gallery['expires_at'] ?? null)) ?>
                                <?php if ($expired): ?>
                                    <span class="table__sub table__sub--warn">Expirée</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge--<?= e((string) $gallery['status']) ?>">
                                    <?= e(GalleryStatus::label((string) $gallery['status'])) ?>
                                </span>
                            </td>
                            <td class="row-actions">
                                <a href="<?= e(url('/admin/galleries/' . (int) $gallery['id'] . '/share')) ?>">Partager</a>
                                <a href="<?= e(url('/admin/galleries/' . (int) $gallery['id'] . '/edit')) ?>">Modifier</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?= View::include('partials.pagination', [
            'pagination' => $pagination,
            'basePath'   => '/admin/galleries',
            'query'      => ['q' => $search, 'status' => $status],
        ]) ?>
    <?php endif; ?>
</section>

<?php View::endSection(); ?>
