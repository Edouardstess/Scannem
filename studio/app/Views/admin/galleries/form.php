<?php
/**
 * @var array<string, mixed>|null        $gallery
 * @var array<int, array<string, mixed>> $events
 * @var array<int, string>               $statuses
 * @var int                              $eventId
 */

use App\Core\View;
use App\Models\GalleryStatus;

View::extend('layouts.admin');
View::startSection('content');

$isEdit = $gallery !== null;
$action = $isEdit ? url('/admin/galleries/' . (int) $gallery['id']) : url('/admin/galleries');
$selectedEvent = (int) old('event_id', $eventId);
$hasPassword = $isEdit && ($gallery['password_hash'] ?? null) !== null && $gallery['password_hash'] !== '';

$currentExpiry = $isEdit ? ($gallery['expires_at'] ?? null) : null;
$expiryOption = (string) old('expiry_option', $currentExpiry === null ? 'never' : 'custom');

$value = static function (string $field) use ($gallery): string {
    return (string) old($field, $gallery[$field] ?? '');
};

$checked = static function (string $field, bool $default = false) use ($gallery, $isEdit): bool {
    $old = old($field, null);

    if ($old !== null) {
        return in_array($old, ['1', 'on', 'true', 1, true], true);
    }

    return $isEdit ? (bool) ($gallery[$field] ?? false) : $default;
};
?>

<form class="form form--panel" method="post" action="<?= e($action) ?>" novalidate>
    <?= csrf_field() ?>
    <?= $isEdit ? method_field('PUT') : '' ?>

    <fieldset class="fieldset">
        <legend class="fieldset__legend">Identité</legend>

        <div class="field">
            <label for="event_id">Événement <span aria-hidden="true">*</span></label>
            <select id="event_id" name="event_id" required>
                <option value="">— Choisir un événement —</option>
                <?php foreach ($events as $event): ?>
                    <option value="<?= (int) $event['id'] ?>" <?= $selectedEvent === (int) $event['id'] ? 'selected' : '' ?>>
                        <?= e((string) $event['title']) ?>
                        — <?= e(trim((string) $event['first_name'] . ' ' . (string) $event['last_name'])) ?>
                        <?= ($event['event_date'] ?? null) !== null ? ' (' . e(format_date((string) $event['event_date'])) . ')' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($message = error_for('event_id')): ?>
                <p class="field__error"><?= e($message) ?></p>
            <?php endif; ?>
            <?php if ($events === []): ?>
                <p class="field__hint">
                    Aucun événement. <a href="<?= e(url('/admin/events/create')) ?>">Créez-en un d'abord</a>.
                </p>
            <?php endif; ?>
        </div>

        <div class="field">
            <label for="title">Titre de la galerie <span aria-hidden="true">*</span></label>
            <input type="text" id="title" name="title" required maxlength="190"
                   value="<?= e($value('title')) ?>" placeholder="Wedding Jean &amp; Marie">
            <?php if ($message = error_for('title')): ?>
                <p class="field__error"><?= e($message) ?></p>
            <?php endif; ?>
        </div>

        <div class="field">
            <label for="description">Message pour le client</label>
            <textarea id="description" name="description" rows="4" maxlength="5000"><?= e($value('description')) ?></textarea>
            <p class="field__hint">Affiché en tête de la galerie.</p>
        </div>

        <div class="field">
            <label for="status">Statut <span aria-hidden="true">*</span></label>
            <select id="status" name="status" required>
                <?php foreach ($statuses as $option): ?>
                    <option value="<?= e($option) ?>"
                        <?= (string) old('status', $gallery['status'] ?? GalleryStatus::DRAFT) === $option ? 'selected' : '' ?>>
                        <?= e(GalleryStatus::label($option)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="field__hint">
                Seule une galerie <strong>active</strong> est consultable par le client.
                Une galerie désactivée coupe immédiatement les deux liens.
            </p>
        </div>
    </fieldset>

    <fieldset class="fieldset">
        <legend class="fieldset__legend">Accès et protection</legend>

        <div class="switch-list">
            <label class="switch">
                <input type="checkbox" name="download_enabled" value="1" <?= $checked('download_enabled') ? 'checked' : '' ?>>
                <span class="switch__label">
                    Autoriser le téléchargement
                    <span class="switch__hint">
                        Le lien de téléchargement existe toujours, mais il ne délivre aucun fichier
                        tant que cette option est désactivée.
                    </span>
                </span>
            </label>

            <label class="switch">
                <input type="checkbox" name="watermark_enabled" value="1" <?= $checked('watermark_enabled') ? 'checked' : '' ?>>
                <span class="switch__label">
                    Filigrane sur les aperçus
                    <span class="switch__hint">
                        Appliqué aux aperçus uniquement. Les fichiers originaux ne sont jamais modifiés.
                        Changer cette option régénère les aperçus de la galerie.
                    </span>
                </span>
            </label>

            <label class="switch">
                <input type="checkbox" name="selection_enabled" value="1" <?= $checked('selection_enabled') ? 'checked' : '' ?>>
                <span class="switch__label">
                    Sélection client
                    <span class="switch__hint">Le client peut marquer ses photos préférées.</span>
                </span>
            </label>
        </div>

        <div class="field">
            <label for="password">
                Mot de passe de galerie
                <?= $hasPassword ? '<span class="field__badge">actif</span>' : '' ?>
            </label>
            <input type="password" id="password" name="password" minlength="6" maxlength="255"
                   autocomplete="new-password"
                   placeholder="<?= $hasPassword ? 'Laisser vide pour conserver le mot de passe actuel' : 'Aucun mot de passe' ?>">
            <?php if ($message = error_for('password')): ?>
                <p class="field__error"><?= e($message) ?></p>
            <?php endif; ?>

            <?php if ($hasPassword): ?>
                <label class="checkbox">
                    <input type="checkbox" name="remove_password" value="1">
                    <span>Supprimer le mot de passe</span>
                </label>
            <?php endif; ?>
        </div>
    </fieldset>

    <fieldset class="fieldset">
        <legend class="fieldset__legend">Expiration</legend>

        <div class="field">
            <label for="expiry_option">Les liens expirent</label>
            <select id="expiry_option" name="expiry_option" data-expiry-select>
                <option value="never" <?= $expiryOption === 'never' ? 'selected' : '' ?>>Jamais</option>
                <option value="24h"   <?= $expiryOption === '24h' ? 'selected' : '' ?>>Dans 24 heures</option>
                <option value="7d"    <?= $expiryOption === '7d' ? 'selected' : '' ?>>Dans 7 jours</option>
                <option value="30d"   <?= $expiryOption === '30d' ? 'selected' : '' ?>>Dans 30 jours</option>
                <option value="custom" <?= $expiryOption === 'custom' ? 'selected' : '' ?>>À une date précise</option>
            </select>
        </div>

        <div class="field" data-expiry-date <?= $expiryOption === 'custom' ? '' : 'hidden' ?>>
            <label for="expiry_date">Date d'expiration</label>
            <input type="date" id="expiry_date" name="expiry_date" min="<?= e(date('Y-m-d')) ?>"
                   value="<?= e(old('expiry_date', $currentExpiry === null ? '' : date('Y-m-d', (int) strtotime((string) $currentExpiry)))) ?>">
            <?php if ($message = error_for('expiry_date')): ?>
                <p class="field__error"><?= e($message) ?></p>
            <?php endif; ?>
            <p class="field__hint">Après cette date, les visiteurs voient « Cette galerie n'est plus disponible ».</p>
        </div>
    </fieldset>

    <div class="form__actions">
        <button class="button" type="submit"><?= $isEdit ? 'Enregistrer' : 'Créer la galerie' ?></button>
        <a class="button button--ghost"
           href="<?= e($isEdit ? url('/admin/galleries/' . (int) $gallery['id']) : url('/admin/galleries')) ?>">Annuler</a>
    </div>

    <?php if (!$isEdit): ?>
        <p class="form__note">
            Les deux liens (consultation et téléchargement) sont générés automatiquement à la création.
        </p>
    <?php endif; ?>
</form>

<?php if ($isEdit): ?>
    <section class="panel panel--danger">
        <h2 class="panel__title">Supprimer cette galerie</h2>
        <p class="panel__text">
            Supprime la galerie, ses <?= (int) ($gallery['photos_count'] ?? 0) ?> photographies,
            leurs aperçus et les fichiers originaux. Irréversible.
        </p>
        <form method="post" action="<?= e(url('/admin/galleries/' . (int) $gallery['id'])) ?>"
              data-confirm="Supprimer définitivement cette galerie et toutes ses photos ?">
            <?= csrf_field() ?>
            <?= method_field('DELETE') ?>
            <button class="button button--danger" type="submit">Supprimer définitivement</button>
        </form>
    </section>
<?php endif; ?>

<?php View::endSection(); ?>
