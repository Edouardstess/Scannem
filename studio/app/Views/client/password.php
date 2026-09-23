<?php
/**
 * Gallery password gate.
 *
 * Shows the gallery's title only. The number of photos, the client's name and
 * the event details stay hidden until the password is right.
 *
 * @var array<string, mixed>|null $gallery
 * @var string                    $rawToken
 * @var string|null               $error
 * @var string                    $actionPath
 */

use App\Core\View;

View::extend('layouts.client');
$title = 'Galerie protégée';
View::startSection('content');
?>

<section class="gate">
    <div class="gate__panel">
        <p class="gate__eyebrow">Galerie privée</p>
        <h1 class="gate__title"><?= e((string) ($gallery['title'] ?? 'Galerie')) ?></h1>
        <p class="gate__text">Cette galerie est protégée. Saisissez le mot de passe qui vous a été communiqué.</p>

        <form class="gate__form" method="post" action="<?= e(url($actionPath)) ?>">
            <?= csrf_field() ?>

            <div class="field">
                <label for="password" class="sr-only">Mot de passe</label>
                <input type="password" id="password" name="password" required autofocus
                       autocomplete="current-password" placeholder="Mot de passe"
                       <?= $error !== null ? 'aria-invalid="true" aria-describedby="gate-error"' : '' ?>>
            </div>

            <?php if ($error !== null): ?>
                <p class="gate__error" id="gate-error" role="alert"><?= e($error) ?></p>
            <?php endif; ?>

            <button class="button button--block" type="submit">Voir les photos</button>
        </form>
    </div>
</section>

<?php View::endSection(); ?>
