<?php
/**
 * @var array<string, mixed>|null        $item
 * @var array<int, array<string, mixed>> $categories
 */

use App\Core\View;

View::extend('layouts.admin');
View::startSection('content');

$isEdit = $item !== null;
$action = $isEdit ? url('/admin/portfolio/' . (int) $item['id']) : url('/admin/portfolio');

$value = static function (string $field) use ($item): string {
    return (string) old($field, $item[$field] ?? '');
};
?>

<form class="form form--panel" method="post" action="<?= e($action) ?>"
      enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <?= $isEdit ? method_field('PUT') : '' ?>

    <?php if ($isEdit && ($item['image_path'] ?? '') !== ''): ?>
        <div class="field">
            <span class="field__label-text">Image actuelle</span>
            <img class="form__preview"
                 src="<?= e(url((string) ($item['thumbnail_path'] ?? $item['image_path']))) ?>"
                 alt="<?= e((string) $item['title']) ?>">
        </div>
    <?php endif; ?>

    <div class="field">
        <label for="image">Image <?= $isEdit ? '' : '<span aria-hidden="true">*</span>' ?></label>
        <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp"
               <?= $isEdit ? '' : 'required' ?>>
        <p class="field__hint">
            JPG, PNG ou WEBP, 20 Mo maximum.
            L'image est ré-encodée et ses métadonnées (dont la position GPS) sont supprimées.
            <?= $isEdit ? ' Laissez vide pour conserver l\'image actuelle.' : '' ?>
        </p>
        <?php if ($message = error_for('image')): ?>
            <p class="field__error"><?= e($message) ?></p>
        <?php endif; ?>
    </div>

    <div class="field">
        <label for="title">Titre <span aria-hidden="true">*</span></label>
        <input type="text" id="title" name="title" required maxlength="190" value="<?= e($value('title')) ?>">
        <p class="field__hint">Sert aussi de texte alternatif : décrivez brièvement l'image.</p>
        <?php if ($message = error_for('title')): ?>
            <p class="field__error"><?= e($message) ?></p>
        <?php endif; ?>
    </div>

    <div class="field-row">
        <div class="field">
            <label for="category_id">Catégorie</label>
            <select id="category_id" name="category_id">
                <option value="">— Sans catégorie —</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['id'] ?>"
                        <?= (int) old('category_id', $item['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>>
                        <?= e((string) $category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="status">Statut <span aria-hidden="true">*</span></label>
            <select id="status" name="status" required>
                <option value="published" <?= (string) old('status', $item['status'] ?? 'published') === 'published' ? 'selected' : '' ?>>Publiée</option>
                <option value="draft" <?= (string) old('status', $item['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Brouillon</option>
            </select>
        </div>

        <?php if ($isEdit): ?>
            <div class="field">
                <label for="sort_order">Ordre</label>
                <input type="number" id="sort_order" name="sort_order" min="0"
                       value="<?= (int) old('sort_order', $item['sort_order'] ?? 0) ?>">
            </div>
        <?php endif; ?>
    </div>

    <div class="field">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="4" maxlength="2000"><?= e($value('description')) ?></textarea>
    </div>

    <label class="checkbox">
        <input type="checkbox" name="featured" value="1"
            <?= (bool) old('featured', $item['featured'] ?? false) ? 'checked' : '' ?>>
        <span>Mettre en vedette sur la page d'accueil</span>
    </label>

    <div class="form__actions">
        <button class="button" type="submit"><?= $isEdit ? 'Enregistrer' : 'Ajouter' ?></button>
        <a class="button button--ghost" href="<?= e(url('/admin/portfolio')) ?>">Annuler</a>
    </div>
</form>

<?php View::endSection(); ?>
