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

<section class="error-page">
    <div class="wrap wrap--narrow">
        <p class="error-page__code"><?= (int) $status ?></p>
        <h1 class="error-page__title"><?= e($headline) ?></h1>
        <?php if (($message ?? '') !== ''): ?>
            <p class="error-page__text"><?= e((string) $message) ?></p>
        <?php endif; ?>
        <a class="button" href="<?= e(url('/')) ?>">Retour à l'accueil</a>
    </div>
</section>

<?php View::endSection(); ?>
