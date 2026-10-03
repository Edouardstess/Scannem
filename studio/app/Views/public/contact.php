<?php
/**
 * Contact: the e-mail set as large as the title, a quiet form on rules,
 * the studio's details beside it, then the map.
 *
 * @var array<int, array<string, mixed>> $services
 * @var array<string, mixed>             $settings
 * @var array<string, string>            $errors
 */

use App\Core\View;
use App\Services\ImmersiveHomeService;

View::extend('layouts.public');
View::startSection('content');

$texts = (new ImmersiveHomeService($settings))->texts();
$email = trim((string) ($settings['contact_email'] ?? ''));
$phone = trim((string) ($settings['contact_phone'] ?? ''));
$address = trim((string) ($settings['contact_address'] ?? ''));
?>

<section class="ph">
    <p class="ph-kicker mono" data-reveal><span>Contact</span><span class="ash"><?= e($texts['reassurance']) ?></span></p>
    <h1 class="ph-title split"><?= View::include('partials.split', ['text' => 'Écrivez-moi']) ?></h1>
    <?php if ($email !== ''): ?>
        <a class="mail-xl" href="mailto:<?= e($email) ?>" data-reveal style="--i: 3"><span><?= e($email) ?></span></a>
    <?php endif; ?>
    <p class="ph-lead" data-reveal style="--i: 4">Ou racontez-moi votre projet ici : je réponds sous 48&nbsp;heures.</p>
</section>

<section class="sec sec--tight">
    <div class="contact">
        <form class="form" method="post" action="<?= e(url('/contact')) ?>" novalidate data-reveal>
            <?= csrf_field() ?>

            <?php /* Honeypot: hidden from people, irresistible to bots. */ ?>
            <div class="honeypot" aria-hidden="true">
                <label for="website">Ne pas remplir</label>
                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <div class="form-row">
                <div class="field">
                    <label for="name">Nom <span aria-hidden="true">*</span></label>
                    <input type="text" id="name" name="name" required maxlength="150" autocomplete="name"
                           value="<?= e(old('name')) ?>"<?= error_for('name') ? ' aria-invalid="true" aria-describedby="name-error"' : '' ?>>
                    <?php if ($message = error_for('name')): ?><p class="field-error" id="name-error"><?= e($message) ?></p><?php endif; ?>
                </div>

                <div class="field">
                    <label for="email">E-mail <span aria-hidden="true">*</span></label>
                    <input type="email" id="email" name="email" required maxlength="190" autocomplete="email"
                           value="<?= e(old('email')) ?>"<?= error_for('email') ? ' aria-invalid="true" aria-describedby="email-error"' : '' ?>>
                    <?php if ($message = error_for('email')): ?><p class="field-error" id="email-error"><?= e($message) ?></p><?php endif; ?>
                </div>
            </div>

            <div class="form-row">
                <div class="field">
                    <label for="phone">Téléphone</label>
                    <input type="tel" id="phone" name="phone" maxlength="40" autocomplete="tel" value="<?= e(old('phone')) ?>">
                    <?php if ($message = error_for('phone')): ?><p class="field-error"><?= e($message) ?></p><?php endif; ?>
                </div>

                <div class="field">
                    <label for="subject">Type de séance</label>
                    <select id="subject" name="subject">
                        <option value="">— Choisir —</option>
                        <?php foreach ($services as $service): ?>
                            <option value="<?= e((string) $service['title']) ?>"<?= old('subject') === (string) $service['title'] ? ' selected' : '' ?>><?= e((string) $service['title']) ?></option>
                        <?php endforeach; ?>
                        <option value="Autre"<?= old('subject') === 'Autre' ? ' selected' : '' ?>>Autre</option>
                    </select>
                </div>
            </div>

            <div class="field">
                <label for="preferred_date">Date souhaitée</label>
                <input type="date" id="preferred_date" name="preferred_date" min="<?= e(date('Y-m-d')) ?>" value="<?= e(old('preferred_date')) ?>">
                <p class="field-hint">Si vous avez déjà une date en tête.</p>
                <?php if ($message = error_for('preferred_date')): ?><p class="field-error"><?= e($message) ?></p><?php endif; ?>
            </div>

            <div class="field">
                <label for="message">Message <span aria-hidden="true">*</span></label>
                <textarea id="message" name="message" rows="6" required minlength="10" maxlength="5000"
                          placeholder="Le lieu, la date, l’ambiance, ce qui compte pour vous…"<?= error_for('message') ? ' aria-invalid="true" aria-describedby="message-error"' : '' ?>><?= e(old('message')) ?></textarea>
                <?php if ($message = error_for('message')): ?><p class="field-error" id="message-error"><?= e($message) ?></p><?php endif; ?>
            </div>

            <div class="form-submit">
                <button class="btn btn--ink" type="submit">Envoyer <span class="arr" aria-hidden="true">→</span></button>
                <p class="form-note">Vos informations servent uniquement à vous répondre. Elles ne sont ni revendues ni partagées.</p>
            </div>
        </form>

        <dl class="coords mono" data-reveal style="--i: 2">
            <?php if ($email !== ''): ?>
                <div><dt class="ash">E-mail</dt><dd><a class="link-u" href="mailto:<?= e($email) ?>"><?= e($email) ?></a></dd></div>
            <?php endif; ?>
            <?php if ($phone !== ''): ?>
                <div><dt class="ash">Téléphone</dt><dd><a class="link-u" href="tel:<?= e(preg_replace('/[^0-9+]/', '', $phone)) ?>"><?= e($phone) ?></a></dd></div>
            <?php endif; ?>
            <?php if ($address !== ''): ?>
                <div><dt class="ash">Adresse</dt><dd><?= e($address) ?><br><a class="link-u" href="#localisation">Voir sur la carte ↓</a></dd></div>
            <?php endif; ?>
            <div><dt class="ash">Délai de réponse</dt><dd>48 heures</dd></div>
            <?php if (!empty($settings['booking_enabled'])): ?>
                <div><dt class="ash">Vous savez déjà ?</dt><dd><a class="link-u" href="<?= e(url('/reservation')) ?>">Réserver une séance →</a></dd></div>
            <?php endif; ?>
        </dl>
    </div>
</section>

<?= View::include('partials.map', ['settings' => $settings, 'headingLevel' => 'h2']) ?>

<?php View::endSection(); ?>
