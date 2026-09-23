<?php
/**
 * @var array<int, array<string, mixed>> $items
 * @var array<int, array<string, mixed>> $categories
 */

use App\Core\View;

View::extend('layouts.admin');
View::startSection('content');
?>

<div class="panel-actions">
    <a class="link-arrow" href="<?= e(url('/admin/portfolio/categories')) ?>">Gérer les catégories</a>
    <a class="button" href="<?= e(url('/admin/portfolio/create')) ?>">Ajouter une photo</a>
</div>

<section class="panel">
    <?php if ($items === []): ?>
        <p class="empty">Le portfolio est vide. Ajoutez vos meilleures photographies.</p>
    <?php else: ?>
        <div class="admin-grid">
            <?php foreach ($items as $item): ?>
                <figure class="admin-photo<?= (int) $item['featured'] === 1 ? ' is-cover' : '' ?>">
                    <img class="admin-photo__image"
                         src="<?= e(url((string) ($item['thumbnail_path'] ?? $item['image_path']))) ?>"
                         alt="<?= e((string) $item['title']) ?>" loading="lazy" decoding="async">

                    <figcaption class="admin-photo__meta">
                        <span class="admin-photo__name"><?= e(str_excerpt((string) $item['title'], 24)) ?></span>
                        <span class="admin-photo__size">
                            <?= e((string) ($item['category_name'] ?? 'Sans catégorie')) ?>
                            · <?= (string) $item['status'] === 'published' ? 'publiée' : 'brouillon' ?>
                            <?= (int) $item['featured'] === 1 ? ' · en vedette' : '' ?>
                        </span>
                    </figcaption>

                    <div class="admin-photo__actions">
                        <a class="admin-photo__action"
                           href="<?= e(url('/admin/portfolio/' . (int) $item['id'] . '/edit')) ?>">Modifier</a>

                        <form method="post" action="<?= e(url('/admin/portfolio/' . (int) $item['id'])) ?>"
                              data-confirm="Retirer cette photo du portfolio ?">
                            <?= csrf_field() ?>
                            <?= method_field('DELETE') ?>
                            <button class="admin-photo__action admin-photo__action--danger" type="submit">
                                Supprimer
                            </button>
                        </form>
                    </div>
                </figure>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php View::endSection(); ?>
