<?php
/**
 * @var array<int, array<string, mixed>> $services
 * @var array<string, mixed>             $settings
 * @var array<string, string>            $errors
 */

use App\Core\View;

View::extend('layouts.public');
View::startSection('content');
?>

<section class="page-head">
    <div class="wrap wrap--medium">
        <p class="section__eyebrow">Contact</p>
        <h1 class="page-head__title">Écrivez-moi</h1>
        <p class="page-head__text">Je réponds sous 48&nbsp;heures.</p>
    </div>
</section>

<section class="section">
    <div class="wrap wrap--medium">
        <div class="form-layout">
            <form class="form" method="post" action="<?= e(url('/contact')) ?>" novalidate>
                <?= csrf_field() ?>

                <?php /* Honeypot: hidden from people, irresistible to bots. */ ?>
                <div class="honeypot" aria-hidden="true">
                    <label for="website">Ne pas remplir</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                </div>

                <div class="field">
                    <label for="name">Nom <span aria-hidden="true">*</span></label>
                    <input type="text" id="name" name="name" required maxlength="150"
                           autocomplete="name" value="<?= e(old('name')) ?>"
                           <?= error_for('name') ? 'aria-invalid="true" aria-describedby="name-error"' : '' ?>>
                    <?php if ($message = error_for('name')): ?>
                        <p class="field__error" id="name-error"><?= e($message) ?></p>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label for="email">E-mail <span aria-hidden="true">*</span></label>
                    <input type="email" id="email" name="email" required maxlength="190"
                           autocomplete="email" value="<?= e(old('email')) ?>"
                           <?= error_for('email') ? 'aria-invalid="true" aria-describedby="email-error"' : '' ?>>
                    <?php if ($message = error_for('email')): ?>
                        <p class="field__error" id="email-error"><?= e($message) ?></p>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label for="phone">Téléphone</label>
                    <input type="tel" id="phone" name="phone" maxlength="40"
                           autocomplete="tel" value="<?= e(old('phone')) ?>">
                    <?php if ($message = error_for('phone')): ?>
                        <p class="field__error"><?= e($message) ?></p>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label for="subject">Type de séance</label>
                    <select id="subject" name="subject">
                        <option value="">— Choisir —</option>
                        <?php foreach ($services as $service): ?>
                            <option value="<?= e((string) $service['title']) ?>"
                                <?= old('subject') === (string) $service['title'] ? 'selected' : '' ?>>
                                <?= e((string) $service['title']) ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="Autre" <?= old('subject') === 'Autre' ? 'selected' : '' ?>>Autre</option>
                    </select>
                </div>

                <div class="field">
                    <label for="preferred_date">Date souhaitée</label>
                    <input type="date" id="preferred_date" name="preferred_date"
                           min="<?= e(date('Y-m-d')) ?>" value="<?= e(old('preferred_date')) ?>">
                    <p class="field__hint">Si vous avez déjà une date en tête.</p>
                    <?php if ($message = error_for('preferred_date')): ?>
                        <p class="field__error"><?= e($message) ?></p>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label for="message">Message <span aria-hidden="true">*</span></label>
                    <textarea id="message" name="message" rows="7" required
                              minlength="10" maxlength="5000"
                              <?= error_for('message') ? 'aria-invalid="true" aria-describedby="message-error"' : '' ?>><?= e(old('message')) ?></textarea>
                    <?php if ($message = error_for('message')): ?>
                        <p class="field__error" id="message-error"><?= e($message) ?></p>
                    <?php endif; ?>
                </div>

                <button class="button" type="submit">Envoyer</button>
                <p class="form__note">
                    Vos informations servent uniquement à vous répondre. Elles ne sont ni revendues ni partagées.
                </p>
            </form>

            <aside class="contact-aside">
                <?php if (($settings['contact_email'] ?? '') !== ''): ?>
                    <p class="contact-aside__row">
                        <span>E-mail</span>
                        <a href="mailto:<?= e((string) $settings['contact_email']) ?>">
                            <?= e((string) $settings['contact_email']) ?>
                        </a>
                    </p>
                <?php endif; ?>
                <?php if (($settings['contact_phone'] ?? '') !== ''): ?>
                    <p class="contact-aside__row">
                        <span>Téléphone</span> <?= e((string) $settings['contact_phone']) ?>
                    </p>
                <?php endif; ?>
                <?php if (($settings['contact_address'] ?? '') !== ''): ?>
                    <p class="contact-aside__row">
                        <span>Adresse</span> <?= e((string) $settings['contact_address']) ?>
                        <a class="link-arrow" href="#localisation">Voir sur la carte</a>
                    </p>
                <?php endif; ?>
                <?php if (!empty($settings['booking_enabled'])): ?>
                    <p class="contact-aside__row">
                        <a class="link-arrow" href="<?= e(url('/reservation')) ?>">
                            Réserver une séance
                        </a>
                    </p>
                <?php endif; ?>
            </aside>
        </div>
    </div>
</section>

<?= View::include('partials.map', ['settings' => $settings, 'headingLevel' => 'h2']) ?>

<?php View::endSection(); ?>
