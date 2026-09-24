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
    <a class="button button--ghost" href="<?= e(url('/admin/portfolio/create')) ?>">Ajouter une photo avec description</a>
</div>

<?php
// Public images are capped at 20 MB by PublicImageService, and by the host.
$uploadLimit = \App\Core\Environment::uploadLimitBytes(20 * 1024 * 1024);
?>
<section class="panel">
    <header class="panel__head">
        <h2 class="panel__title">Ajouter des photos au portfolio</h2>
        <span class="panel__hint">
            Visibles par tous les visiteurs du site · JPG, PNG, WEBP · <?= e(format_bytes($uploadLimit)) ?> max par fichier
        </span>
    </header>

    <div class="uploader" data-uploader data-reload-when-done
         data-max-bytes="<?= (int) $uploadLimit ?>"
         data-endpoint="<?= e(url('/admin/portfolio/bulk')) ?>"
         data-csrf="<?= e(csrf_token()) ?>">

        <div class="uploader__options">
            <div class="field">
                <label for="bulk_category">Catégorie</label>
                <select id="bulk_category" name="category_id" data-upload-field>
                    <option value="">Sans catégorie</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>"><?= e((string) $category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="bulk_status">Visibilité</label>
                <select id="bulk_status" name="status" data-upload-field>
                    <option value="published">Publiée tout de suite</option>
                    <option value="draft">Brouillon (invisible pour l'instant)</option>
                </select>
            </div>

            <label class="switch uploader__switch">
                <input type="checkbox" name="featured" value="1" data-upload-field>
                <span class="switch__label">
                    Mettre en vedette sur la page d'accueil
                    <span class="switch__hint">Les photos en vedette apparaissent dans la sélection de l'accueil.</span>
                </span>
            </label>
        </div>

        <div class="uploader__dropzone" data-dropzone tabindex="0" role="button"
             aria-label="Déposer des photos ou cliquer pour choisir des fichiers">
            <p class="uploader__title">Glissez vos photos ici</p>
            <p class="uploader__subtitle">ou cliquez pour en choisir plusieurs à la fois</p>
            <input class="uploader__input" type="file" data-file-input multiple
                   accept="image/jpeg,image/png,image/webp">
        </div>

        <div class="uploader__summary" data-upload-summary hidden>
            <div class="uploader__progress">
                <div class="uploader__bar" data-upload-bar></div>
            </div>
            <p class="uploader__status" data-upload-status role="status" aria-live="polite"></p>
        </div>

        <ul class="uploader__queue" data-upload-queue></ul>

        <p class="field__hint">
            Le titre de chaque photo vient du nom du fichier ; vous pouvez le changer ensuite avec « Modifier ».
            Pour voir le résultat : <a href="<?= e(url('/portfolio')) ?>" target="_blank" rel="noopener">page Portfolio du site</a>.
        </p>
    </div>
</section>

<section class="panel">
    <header class="panel__head">
        <h2 class="panel__title">Photos du portfolio <span class="panel__count"><?= count($items) ?></span></h2>
    </header>
    <?php if ($items === []): ?>
        <p class="empty">Le portfolio est vide. Glissez vos meilleures photos dans la zone ci-dessus.</p>
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
