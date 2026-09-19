<?php
/** Layout des pages publiques : accueil, connexion, changement de mot de passe. */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0B2A4A">
    <meta name="description" content="Plateforme de l'Unité d'Études et de Programmation du Ministère de l'Éducation Nationale et de la Formation Professionnelle d'Haïti.">
    <title><?= e($titrePage ?? APP_NOM) ?> — <?= e(APP_NOM) ?></title>
    <link rel="icon" type="image/png" href="<?= URL_BASE ?>/assets/img/favicon.png">
    <link rel="stylesheet" href="<?= URL_BASE ?>/assets/vendor/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="<?= URL_BASE ?>/assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= URL_BASE ?>/assets/css/style.css?v=<?= e(APP_VERSION) ?>">
</head>
<body class="guest-body">

<a class="lien-evitement" href="#contenu-principal">Aller au contenu principal</a>

<nav class="guest-nav" aria-label="Navigation">
    <a class="guest-nav-brand" href="<?= URL_BASE ?>/">
        <img src="<?= URL_BASE ?>/assets/img/logo-uep.png" alt="">
        <span><strong>UEP</strong><small>MENFP · Haïti</small></span>
    </a>
    <div class="guest-nav-links">
        <a href="<?= URL_BASE ?>/">Accueil</a>
        <?php if (Session::estConnecte()): ?>
            <a href="<?= URL_BASE ?>/dashboard">Tableau de bord</a>
            <form method="POST" action="<?= URL_BASE ?>/logout">
                <?= Csrf::champ() ?>
                <button type="submit" class="guest-nav-bouton">Déconnexion</button>
            </form>
        <?php else: ?>
            <a class="guest-nav-cta" href="<?= URL_BASE ?>/login">
                <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Connexion
            </a>
        <?php endif; ?>
    </div>
</nav>

<main id="contenu-principal">
    <div class="guest-flash"><?php require RACINE_VIEWS . '/partials/flash.php'; ?></div>
    <?= $contenu ?? '' ?>
</main>

<footer class="footer">
    <p class="footer-slogan"><?= e(SLOGAN_1) ?></p>
    <p class="footer-slogan-2"><?= e(SLOGAN_2) ?></p>
    <div class="footer-divider"></div>
    <p class="footer-contact">
        <?= e(CONTACT_TEL) ?> · <a href="https://<?= e(CONTACT_WEB) ?>" rel="noopener"><?= e(CONTACT_WEB) ?></a><br>
        <?= e(CONTACT_ADRESSE) ?>
    </p>
</footer>

<script src="<?= URL_BASE ?>/assets/js/app.js?v=<?= e(APP_VERSION) ?>" defer></script>
</body>
</html>
