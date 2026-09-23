<?php

use App\Core\View;

View::extend('layouts.public');
View::startSection('content');
?>

<section class="page-head">
    <div class="wrap wrap--narrow">
        <p class="section__eyebrow">Espace client</p>
        <h1 class="page-head__title">Accéder à ma galerie</h1>
        <p class="page-head__text">
            Collez le lien reçu par e-mail, ou seulement le code qu'il contient.
        </p>
    </div>
</section>

<section class="section">
    <div class="wrap wrap--narrow">
        <form class="form form--compact" method="post" action="<?= e(url('/espace-client')) ?>">
            <?= csrf_field() ?>

            <div class="field">
                <label for="code">Lien ou code de galerie</label>
                <input type="text" id="code" name="code" required autocomplete="off" spellcheck="false"
                       placeholder="https://… ou a8K29xPq…" value="<?= e(old('code')) ?>"
                       <?= error_for('code') ? 'aria-invalid="true" aria-describedby="code-error"' : '' ?>>
                <?php if ($message = error_for('code')): ?>
                    <p class="field__error" id="code-error"><?= e($message) ?></p>
                <?php endif; ?>
            </div>

            <button class="button" type="submit">Ouvrir ma galerie</button>
        </form>

        <p class="form__note form__note--centered">
            Vous n'avez pas reçu votre lien&nbsp;?
            <a href="<?= e(url('/contact')) ?>">Contactez-moi</a>.
        </p>
    </div>
</section>

<?php View::endSection(); ?>
