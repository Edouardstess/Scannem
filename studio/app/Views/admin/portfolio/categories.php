<?php
/** @var array<int, array<string, mixed>> $categories */

use App\Core\View;

View::extend('layouts.admin');
View::startSection('content');
?>

<div class="panel-actions">
    <a class="link-arrow" href="<?= e(url('/admin/portfolio')) ?>">← Portfolio</a>
</div>

<section class="panel">
    <header class="panel__head"><h2 class="panel__title">Nouvelle catégorie</h2></header>

    <form class="form form--inline-grid" method="post" action="<?= e(url('/admin/portfolio/categories')) ?>">
        <?= csrf_field() ?>

        <div class="field">
            <label for="name">Nom <span aria-hidden="true">*</span></label>
            <input type="text" id="name" name="name" required maxlength="120" value="<?= e(old('name')) ?>"
                   placeholder="Mariage">
            <?php if ($message = error_for('name')): ?>
                <p class="field__error"><?= e($message) ?></p>
            <?php endif; ?>
        </div>

        <div class="field">
            <label for="description">Description</label>
            <input type="text" id="description" name="description" maxlength="1000"
                   value="<?= e(old('description')) ?>">
        </div>

        <div class="field">
            <label for="status">Statut</label>
            <select id="status" name="status">
                <option value="published">Publiée</option>
                <option value="draft">Brouillon</option>
            </select>
        </div>

        <div class="field field--action">
            <button class="button" type="submit">Ajouter</button>
        </div>
    </form>
</section>

<section class="panel">
    <header class="panel__head"><h2 class="panel__title">Catégories existantes</h2></header>

    <?php if ($categories === []): ?>
        <p class="empty">Aucune catégorie. Le portfolio fonctionne aussi sans.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Nom</th>
                        <th scope="col">URL publique</th>
                        <th scope="col" class="numeric">Photos</th>
                        <th scope="col">Statut</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $category): ?>
                        <tr>
                            <td colspan="5" class="cell-form">
                                <form class="row-form" method="post"
                                      action="<?= e(url('/admin/portfolio/categories/' . (int) $category['id'])) ?>">
                                    <?= csrf_field() ?>
                                    <?= method_field('PUT') ?>

                                    <label class="sr-only" for="name-<?= (int) $category['id'] ?>">Nom</label>
                                    <input type="text" id="name-<?= (int) $category['id'] ?>" name="name" required
                                           maxlength="120" value="<?= e((string) $category['name']) ?>">

                                    <label class="sr-only" for="desc-<?= (int) $category['id'] ?>">Description</label>
                                    <input type="text" id="desc-<?= (int) $category['id'] ?>" name="description"
                                           maxlength="1000" value="<?= e((string) ($category['description'] ?? '')) ?>"
                                           placeholder="Description">

                                    <label class="sr-only" for="order-<?= (int) $category['id'] ?>">Ordre</label>
                                    <input type="number" id="order-<?= (int) $category['id'] ?>" name="sort_order"
                                           min="0" value="<?= (int) $category['sort_order'] ?>" class="input--narrow">

                                    <label class="sr-only" for="status-<?= (int) $category['id'] ?>">Statut</label>
                                    <select id="status-<?= (int) $category['id'] ?>" name="status">
                                        <option value="published" <?= (string) $category['status'] === 'published' ? 'selected' : '' ?>>Publiée</option>
                                        <option value="draft" <?= (string) $category['status'] === 'draft' ? 'selected' : '' ?>>Brouillon</option>
                                    </select>

                                    <span class="row-form__meta">
                                        /portfolio/<?= e((string) $category['slug']) ?>
                                        · <?= (int) $category['items_count'] ?> photo(s)
                                    </span>

                                    <button class="button button--small" type="submit">Enregistrer</button>
                                </form>

                                <form method="post"
                                      action="<?= e(url('/admin/portfolio/categories/' . (int) $category['id'])) ?>"
                                      data-confirm="Supprimer cette catégorie ? Les photos associées seront conservées.">
                                    <?= csrf_field() ?>
                                    <?= method_field('DELETE') ?>
                                    <button class="button button--small button--danger-ghost" type="submit">
                                        Supprimer
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php View::endSection(); ?>
