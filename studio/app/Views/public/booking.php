<?php
/**
 * @var array<int, array<string, mixed>> $services
 * @var int                              $preselected
 */

use App\Core\View;

View::extend('layouts.public');
View::startSection('content');

$selected = (int) (old('service_id', $preselected));
?>

<section class="page-head">
    <div class="wrap wrap--narrow">
        <p class="section__eyebrow">Réservation</p>
        <h1 class="page-head__title">Réserver une séance</h1>
        <p class="page-head__text">
            Dites-moi ce que vous souhaitez : je reviens vers vous avec une proposition de date et un devis.
        </p>
    </div>
</section>

<section class="section">
    <div class="wrap wrap--narrow">
        <form class="form" method="post" action="<?= e(url('/reservation')) ?>" novalidate>
            <?= csrf_field() ?>

            <div class="honeypot" aria-hidden="true">
                <label for="website">Ne pas remplir</label>
                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <div class="field-row">
                <div class="field">
                    <label for="name">Nom <span aria-hidden="true">*</span></label>
                    <input type="text" id="name" name="name" required maxlength="150"
                           autocomplete="name" value="<?= e(old('name')) ?>">
                    <?php if ($message = error_for('name')): ?>
                        <p class="field__error"><?= e($message) ?></p>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label for="email">E-mail <span aria-hidden="true">*</span></label>
                    <input type="email" id="email" name="email" required maxlength="190"
                           autocomplete="email" value="<?= e(old('email')) ?>">
                    <?php if ($message = error_for('email')): ?>
                        <p class="field__error"><?= e($message) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="field-row">
                <div class="field">
                    <label for="phone">Téléphone</label>
                    <input type="tel" id="phone" name="phone" maxlength="40"
                           autocomplete="tel" value="<?= e(old('phone')) ?>">
                </div>

                <div class="field">
                    <label for="service_id">Prestation</label>
                    <select id="service_id" name="service_id">
                        <option value="">— Choisir —</option>
                        <?php foreach ($services as $service): ?>
                            <option value="<?= (int) $service['id'] ?>"
                                <?= $selected === (int) $service['id'] ? 'selected' : '' ?>>
                                <?= e((string) $service['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="field-row">
                <div class="field">
                    <label for="preferred_date">Date souhaitée</label>
                    <input type="date" id="preferred_date" name="preferred_date"
                           min="<?= e(date('Y-m-d')) ?>" value="<?= e(old('preferred_date')) ?>">
                    <?php if ($message = error_for('preferred_date')): ?>
                        <p class="field__error"><?= e($message) ?></p>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label for="location">Lieu</label>
                    <input type="text" id="location" name="location" maxlength="190"
                           value="<?= e(old('location')) ?>">
                </div>
            </div>

            <div class="field">
                <label for="message">Votre projet</label>
                <textarea id="message" name="message" rows="6" maxlength="5000"><?= e(old('message')) ?></textarea>
                <?php if ($message = error_for('message')): ?>
                    <p class="field__error"><?= e($message) ?></p>
                <?php endif; ?>
            </div>

            <button class="button" type="submit">Envoyer ma demande</button>
            <p class="form__note">
                Cette demande ne réserve pas encore la date : elle ouvre la discussion.
            </p>
        </form>
    </div>
</section>

<?php View::endSection(); ?>
