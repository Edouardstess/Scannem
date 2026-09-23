<?php
/** @var array<string, mixed>|null $service */

use App\Core\View;

View::extend('layouts.admin');
View::startSection('content');

$isEdit = $service !== null;
$action = $isEdit ? url('/admin/services/' . (int) $service['id']) : url('/admin/services');

$value = static function (string $field) use ($service): string {
    return (string) old($field, $service[$field] ?? '');
};
?>

<form class="form form--panel" method="post" action="<?= e($action) ?>"
      enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <?= $isEdit ? method_field('PUT') : '' ?>

    <div class="field">
        <label for="title">Titre <span aria-hidden="true">*</span></label>
        <input type="text" id="title" name="title" required maxlength="190" value="<?= e($value('title')) ?>"
               placeholder="Reportage de mariage">
        <?php if ($message = error_for('title')): ?>
            <p class="field__error"><?= e($message) ?></p>
        <?php endif; ?>
    </div>

    <div class="field">
        <label for="summary">Résumé</label>
        <input type="text" id="summary" name="summary" maxlength="255" value="<?= e($value('summary')) ?>">
        <p class="field__hint">Une phrase, affichée dans les listes et les aperçus de partage.</p>
    </div>

    <div class="field">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="8" maxlength="5000"><?= e($value('description')) ?></textarea>
    </div>

    <div class="field">
        <label for="deliverables">Ce qui est inclus</label>
        <textarea id="deliverables" name="deliverables" rows="6" maxlength="2000"><?= e($value('deliverables')) ?></textarea>
        <p class="field__hint">Une ligne par élément. Affiché sous forme de liste.</p>
    </div>

    <div class="field-row">
        <div class="field">
            <label for="price_from">Tarif à partir de</label>
            <input type="text" id="price_from" name="price_from" inputmode="decimal"
                   value="<?= e($value('price_from')) ?>" placeholder="1200">
            <?php if ($message = error_for('price_from')): ?>
                <p class="field__error"><?= e($message) ?></p>
            <?php endif; ?>
        </div>

        <div class="field">
            <label for="currency">Devise</label>
            <input type="text" id="currency" name="currency" maxlength="8"
                   value="<?= e(old('currency', $service['currency'] ?? 'EUR')) ?>">
        </div>

        <div class="field">
            <label for="duration">Durée</label>
            <input type="text" id="duration" name="duration" maxlength="80"
                   value="<?= e($value('duration')) ?>" placeholder="Journée complète">
        </div>
    </div>

    <div class="field-row">
        <div class="field">
            <label for="status">Statut <span aria-hidden="true">*</span></label>
            <select id="status" name="status" required>
                <option value="published" <?= (string) old('status', $service['status'] ?? 'published') === 'published' ? 'selected' : '' ?>>Publiée</option>
                <option value="draft" <?= (string) old('status', $service['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Brouillon</option>
            </select>
        </div>

        <?php if ($isEdit): ?>
            <div class="field">
                <label for="sort_order">Ordre</label>
                <input type="number" id="sort_order" name="sort_order" min="0"
                       value="<?= (int) old('sort_order', $service['sort_order'] ?? 0) ?>">
            </div>
        <?php endif; ?>
    </div>

    <div class="field">
        <label for="image">Illustration</label>
        <?php if ($isEdit && ($service['image_path'] ?? '') !== ''): ?>
            <img class="form__preview" src="<?= e(url((string) $service['image_path'])) ?>" alt="">
        <?php endif; ?>
        <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">
        <?php if ($message = error_for('image')): ?>
            <p class="field__error"><?= e($message) ?></p>
        <?php endif; ?>
    </div>

    <div class="form__actions">
        <button class="button" type="submit"><?= $isEdit ? 'Enregistrer' : 'Créer' ?></button>
        <a class="button button--ghost" href="<?= e(url('/admin/services')) ?>">Annuler</a>
    </div>
</form>

<?php if ($isEdit): ?>
    <section class="panel panel--danger">
        <h2 class="panel__title">Supprimer cette prestation</h2>
        <form method="post" action="<?= e(url('/admin/services/' . (int) $service['id'])) ?>"
              data-confirm="Supprimer cette prestation ?">
            <?= csrf_field() ?>
            <?= method_field('DELETE') ?>
            <button class="button button--danger" type="submit">Supprimer</button>
        </form>
    </section>
<?php endif; ?>

<?php View::endSection(); ?>
