<?php
/** @var array<string, mixed>|null $client */

use App\Core\View;

View::extend('layouts.admin');
View::startSection('content');

$isEdit = $client !== null;
$action = $isEdit ? url('/admin/clients/' . (int) $client['id']) : url('/admin/clients');

$value = static function (string $field) use ($client): string {
    return (string) old($field, $client[$field] ?? '');
};
?>

<form class="form form--panel" method="post" action="<?= e($action) ?>" novalidate>
    <?= csrf_field() ?>
    <?= $isEdit ? method_field('PUT') : '' ?>

    <div class="field-row">
        <div class="field">
            <label for="first_name">Prénom <span aria-hidden="true">*</span></label>
            <input type="text" id="first_name" name="first_name" required maxlength="100"
                   value="<?= e($value('first_name')) ?>">
            <?php if ($message = error_for('first_name')): ?>
                <p class="field__error"><?= e($message) ?></p>
            <?php endif; ?>
        </div>

        <div class="field">
            <label for="last_name">Nom <span aria-hidden="true">*</span></label>
            <input type="text" id="last_name" name="last_name" required maxlength="100"
                   value="<?= e($value('last_name')) ?>">
            <?php if ($message = error_for('last_name')): ?>
                <p class="field__error"><?= e($message) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="field-row">
        <div class="field">
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" maxlength="190" value="<?= e($value('email')) ?>">
            <p class="field__hint">Sert à envoyer le lien de galerie depuis l'écran de partage.</p>
            <?php if ($message = error_for('email')): ?>
                <p class="field__error"><?= e($message) ?></p>
            <?php endif; ?>
        </div>

        <div class="field">
            <label for="phone">Téléphone</label>
            <input type="tel" id="phone" name="phone" maxlength="40" value="<?= e($value('phone')) ?>">
            <?php if ($message = error_for('phone')): ?>
                <p class="field__error"><?= e($message) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="field">
        <label for="company">Société</label>
        <input type="text" id="company" name="company" maxlength="150" value="<?= e($value('company')) ?>">
    </div>

    <div class="field">
        <label for="notes">Notes internes</label>
        <textarea id="notes" name="notes" rows="5" maxlength="5000"><?= e($value('notes')) ?></textarea>
        <p class="field__hint">Visibles uniquement par vous. Jamais affichées au client.</p>
    </div>

    <div class="form__actions">
        <button class="button" type="submit"><?= $isEdit ? 'Enregistrer' : 'Créer le client' ?></button>
        <a class="button button--ghost"
           href="<?= e($isEdit ? url('/admin/clients/' . (int) $client['id']) : url('/admin/clients')) ?>">
            Annuler
        </a>
    </div>
</form>

<?php if ($isEdit): ?>
    <section class="panel panel--danger">
        <h2 class="panel__title">Supprimer ce client</h2>
        <p class="panel__text">
            Supprime définitivement le client, ses événements, ses galeries, ses photographies
            et les fichiers correspondants. Cette action est irréversible.
        </p>
        <form method="post" action="<?= e(url('/admin/clients/' . (int) $client['id'])) ?>"
              data-confirm="Supprimer ce client et TOUTES ses galeries et photos ? Cette action est irréversible.">
            <?= csrf_field() ?>
            <?= method_field('DELETE') ?>
            <button class="button button--danger" type="submit">Supprimer définitivement</button>
        </form>
    </section>
<?php endif; ?>

<?php View::endSection(); ?>
