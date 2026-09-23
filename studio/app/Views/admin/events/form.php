<?php
/**
 * @var array<string, mixed>|null        $event
 * @var array<int, array<string, mixed>> $clients
 * @var array<string, string>            $types
 * @var array<int, string>               $statuses
 * @var int                              $clientId
 */

use App\Core\View;
use App\Models\EventStatus;

View::extend('layouts.admin');
View::startSection('content');

$isEdit = $event !== null;
$action = $isEdit ? url('/admin/events/' . (int) $event['id']) : url('/admin/events');
$selectedClient = (int) old('client_id', $clientId);

$value = static function (string $field) use ($event): string {
    return (string) old($field, $event[$field] ?? '');
};
?>

<form class="form form--panel" method="post" action="<?= e($action) ?>" novalidate>
    <?= csrf_field() ?>
    <?= $isEdit ? method_field('PUT') : '' ?>

    <div class="field">
        <label for="client_id">Client <span aria-hidden="true">*</span></label>
        <select id="client_id" name="client_id" required>
            <option value="">— Choisir un client —</option>
            <?php foreach ($clients as $client): ?>
                <option value="<?= (int) $client['id'] ?>" <?= $selectedClient === (int) $client['id'] ? 'selected' : '' ?>>
                    <?= e(trim((string) $client['last_name'] . ' ' . (string) $client['first_name'])) ?>
                    <?= ($client['company'] ?? '') !== '' ? ' — ' . e((string) $client['company']) : '' ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ($message = error_for('client_id')): ?>
            <p class="field__error"><?= e($message) ?></p>
        <?php endif; ?>
        <?php if ($clients === []): ?>
            <p class="field__hint">
                Aucun client enregistré. <a href="<?= e(url('/admin/clients/create')) ?>">Créez-en un d'abord</a>.
            </p>
        <?php endif; ?>
    </div>

    <div class="field">
        <label for="title">Titre <span aria-hidden="true">*</span></label>
        <input type="text" id="title" name="title" required maxlength="190" value="<?= e($value('title')) ?>"
               placeholder="Mariage Jean &amp; Marie">
        <?php if ($message = error_for('title')): ?>
            <p class="field__error"><?= e($message) ?></p>
        <?php endif; ?>
    </div>

    <div class="field-row">
        <div class="field">
            <label for="event_type">Type</label>
            <select id="event_type" name="event_type">
                <option value="">— Choisir —</option>
                <?php foreach ($types as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $value('event_type') === $key ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="event_date">Date</label>
            <input type="date" id="event_date" name="event_date" value="<?= e($value('event_date')) ?>">
            <?php if ($message = error_for('event_date')): ?>
                <p class="field__error"><?= e($message) ?></p>
            <?php endif; ?>
        </div>

        <div class="field">
            <label for="status">Statut <span aria-hidden="true">*</span></label>
            <select id="status" name="status" required>
                <?php foreach ($statuses as $option): ?>
                    <option value="<?= e($option) ?>"
                        <?= (string) old('status', $event['status'] ?? EventStatus::DRAFT) === $option ? 'selected' : '' ?>>
                        <?= e(EventStatus::label($option)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="field">
        <label for="location">Lieu</label>
        <input type="text" id="location" name="location" maxlength="190" value="<?= e($value('location')) ?>">
    </div>

    <div class="field">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="5" maxlength="5000"><?= e($value('description')) ?></textarea>
    </div>

    <div class="form__actions">
        <button class="button" type="submit"><?= $isEdit ? 'Enregistrer' : "Créer l'événement" ?></button>
        <a class="button button--ghost"
           href="<?= e($isEdit ? url('/admin/events/' . (int) $event['id']) : url('/admin/events')) ?>">Annuler</a>
    </div>
</form>

<?php if ($isEdit): ?>
    <section class="panel panel--danger">
        <h2 class="panel__title">Supprimer cet événement</h2>
        <p class="panel__text">
            Supprime l'événement, ses galeries, ses photographies et les fichiers correspondants.
        </p>
        <form method="post" action="<?= e(url('/admin/events/' . (int) $event['id'])) ?>"
              data-confirm="Supprimer cet événement et toutes ses galeries ? Action irréversible.">
            <?= csrf_field() ?>
            <?= method_field('DELETE') ?>
            <button class="button button--danger" type="submit">Supprimer définitivement</button>
        </form>
    </section>
<?php endif; ?>

<?php View::endSection(); ?>
