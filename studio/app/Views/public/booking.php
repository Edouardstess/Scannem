<?php
/**
 * Booking request: the three steps of the homepage as a reminder of what
 * happens next, then the form on rules.
 *
 * @var array<int, array<string, mixed>> $services
 * @var int                              $preselected
 */

use App\Core\View;
use App\Services\ImmersiveHomeService;

View::extend('layouts.public');
View::startSection('content');

$texts = (new ImmersiveHomeService($settings))->texts();
$selected = (int) (old('service_id', $preselected));
?>

<section class="ph">
    <p class="ph-kicker mono" data-reveal><span>Réservation</span><span class="ash">Devis gratuit, sans engagement</span></p>
    <h1 class="ph-title ph-title--long split"><?= View::include('partials.split', ['text' => 'Réserver une séance']) ?></h1>
    <p class="ph-lead" data-reveal style="--i: 3">
        Dites-moi ce que vous souhaitez : je reviens vers vous avec une proposition de date et un devis.
    </p>
    <ol class="ph-meta mono" data-reveal style="--i: 4">
        <?php foreach ([1, 2, 3] as $i): ?>
            <li><span class="ash"><?= e(sprintf('%02d', $i)) ?></span> <?= e($texts["step_{$i}_name"]) ?></li>
        <?php endforeach; ?>
    </ol>
</section>

<section class="sec sec--tight">
    <form class="form narrow" method="post" action="<?= e(url('/reservation')) ?>" novalidate data-reveal>
        <?= csrf_field() ?>

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
            </div>

            <div class="field">
                <label for="service_id">Prestation</label>
                <select id="service_id" name="service_id">
                    <option value="">— Choisir —</option>
                    <?php foreach ($services as $service): ?>
                        <option value="<?= (int) $service['id'] ?>"<?= $selected === (int) $service['id'] ? ' selected' : '' ?>>
                            <?= e((string) $service['title']) ?><?= ($service['price_from'] ?? null) !== null ? ' — dès ' . e(format_price($service['price_from'], (string) $service['currency'])) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="field">
                <label for="preferred_date">Date souhaitée</label>
                <input type="date" id="preferred_date" name="preferred_date" min="<?= e(date('Y-m-d')) ?>" value="<?= e(old('preferred_date')) ?>">
                <?php if ($message = error_for('preferred_date')): ?><p class="field-error"><?= e($message) ?></p><?php endif; ?>
            </div>

            <div class="field">
                <label for="location">Lieu</label>
                <input type="text" id="location" name="location" maxlength="190" placeholder="Ville, salle, extérieur…" value="<?= e(old('location')) ?>">
            </div>
        </div>

        <div class="field">
            <label for="message">Votre projet</label>
            <textarea id="message" name="message" rows="6" maxlength="5000" placeholder="Nombre d’invités, ambiance, moments à ne pas manquer…"><?= e(old('message')) ?></textarea>
            <?php if ($message = error_for('message')): ?><p class="field-error"><?= e($message) ?></p><?php endif; ?>
        </div>

        <div class="form-submit">
            <button class="btn" type="submit">Envoyer ma demande <span class="arr" aria-hidden="true">→</span></button>
            <p class="form-note">Cette demande ne réserve pas encore la date : elle ouvre la discussion.</p>
        </div>
    </form>
</section>

<?php View::endSection(); ?>
