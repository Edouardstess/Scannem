<?php

use App\Core\View;

View::extend('layouts.public');
View::startSection('content');
?>

<section class="ph">
    <p class="ph-kicker mono" data-reveal><span>Espace client</span><span class="ash">Galerie privée</span></p>
    <h1 class="ph-title ph-title--long split"><?= View::include('partials.split', ['text' => 'Retrouver mes photos']) ?></h1>
    <p class="ph-lead" data-reveal style="--i: 3">Collez le lien reçu par e-mail, ou seulement le code qu’il contient.</p>
</section>

<section class="sec sec--tight">
    <form class="narrow" method="post" action="<?= e(url('/espace-client')) ?>" data-reveal>
        <?= csrf_field() ?>

        <div class="code-form">
            <div class="field">
                <label for="code">Lien ou code de galerie</label>
                <input type="text" id="code" name="code" required autocomplete="off" spellcheck="false"
                       placeholder="https://… ou a8K29xPq…" value="<?= e(old('code')) ?>"<?= error_for('code') ? ' aria-invalid="true" aria-describedby="code-error"' : '' ?>>
            </div>
            <button class="btn btn--ink" type="submit">Ouvrir ma galerie <span class="arr" aria-hidden="true">→</span></button>
        </div>
        <?php if ($message = error_for('code')): ?>
            <p class="field-error" id="code-error"><?= e($message) ?></p>
        <?php endif; ?>

        <p class="form-note">
            Vous n’avez pas reçu votre lien&nbsp;?
            <a class="link-u" href="<?= e(url('/contact')) ?>">Contactez-moi</a>.
        </p>
    </form>
</section>

<?php View::endSection(); ?>
