<?php
/** @var string $message */

use App\Core\View;

View::extend('layouts.client');
$title = 'Galerie indisponible';
View::startSection('content');
?>

<section class="gate">
    <div class="gate__panel gate__panel--message">
        <h1 class="gate__title">Galerie indisponible</h1>
        <p class="gate__text"><?= e($message) ?></p>
        <p class="gate__text gate__text--muted">
            Si vous pensez qu'il s'agit d'une erreur, contactez votre photographe :
            il peut vous renvoyer un lien valide.
        </p>
        <a class="button button--ghost" href="<?= e(url('/contact')) ?>">Contacter le studio</a>
    </div>
</section>

<?php View::endSection(); ?>
