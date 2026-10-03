<?php
/**
 * @var int    $status
 * @var string $message
 */

use App\Core\View;

View::extend('layouts.public');
$title = 'Erreur ' . (int) $status;
View::startSection('content');

$headline = match ((int) $status) {
    400 => 'Requête invalide',
    401 => 'Authentification requise',
    403 => 'Accès refusé',
    404 => 'Page introuvable',
    405 => 'Méthode non autorisée',
    410 => "Ce contenu n'est plus disponible",
    419 => 'Session expirée',
    429 => 'Trop de requêtes',
    default => 'Une erreur est survenue',
};
?>

<section class="err">
    <p class="err-code" aria-hidden="true" data-reveal><?= (int) $status ?></p>
    <h1 class="err-title split"><?= View::include('partials.split', ['text' => $headline]) ?></h1>
    <?php if (($message ?? '') !== ''): ?>
        <p class="err-text" data-reveal style="--i: 2"><?= e((string) $message) ?></p>
    <?php endif; ?>
    <p class="actions" data-reveal style="--i: 3">
        <a class="btn btn--ink" href="<?= e(url('/')) ?>">Retour à l'accueil <span class="arr" aria-hidden="true">→</span></a>
        <a class="btn btn--line" href="<?= e(url('/portfolio')) ?>">Voir le portfolio</a>
    </p>
</section>

<?php View::endSection(); ?>
