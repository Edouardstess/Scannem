<?php
/**
 * Layout des pages authentifiées : barre latérale, en-tête, contenu, pied.
 * @var string $contenu
 * @var string $titrePage
 */
$chemin = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$prefixe = (string)parse_url(URL_BASE, PHP_URL_PATH);
if ($prefixe !== '' && str_starts_with($chemin, $prefixe)) {
    $chemin = substr($chemin, strlen($prefixe));
}
$chemin = '/' . ltrim($chemin, '/');

/** Le lien de navigation correspond-il à la page courante ? */
$actif = static function (string $base) use ($chemin): string {
    return $chemin === $base || str_starts_with($chemin, $base . '/') ? ' is-active' : '';
};
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0B2A4A">
    <title><?= e($titrePage ?? 'Tableau de bord') ?> — <?= e(APP_NOM) ?></title>
    <link rel="icon" type="image/png" href="<?= URL_BASE ?>/assets/img/favicon.png">
    <link rel="stylesheet" href="<?= URL_BASE ?>/assets/vendor/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="<?= URL_BASE ?>/assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= URL_BASE ?>/assets/css/style.css?v=<?= e(APP_VERSION) ?>">
</head>
<body class="app-body">

<a class="lien-evitement" href="#contenu-principal">Aller au contenu principal</a>

<div class="app-overlay" data-fermer-menu hidden></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img class="sidebar-emblem" src="<?= URL_BASE ?>/assets/img/logo-uep.png" alt="">
        <div>
            <p class="sidebar-government">République d'Haïti · MENFP</p>
            <p class="sidebar-logo">UEP</p>
            <p class="sidebar-subtitle">Unité d'Études<br>et de Programmation</p>
        </div>
    </div>

    <nav class="sidebar-nav" aria-label="Navigation principale">
        <a href="<?= URL_BASE ?>/dashboard" class="sidebar-link<?= $actif('/dashboard') ?>">
            <i class="bi bi-speedometer2" aria-hidden="true"></i><span>Tableau de bord</span>
        </a>

        <p class="sidebar-section">Données institutionnelles</p>
        <a href="<?= URL_BASE ?>/upd" class="sidebar-link<?= $actif('/upd') ?>">
            <i class="bi bi-building" aria-hidden="true"></i><span>UPD</span>
        </a>
        <a href="<?= URL_BASE ?>/dde" class="sidebar-link<?= $actif('/dde') ?>">
            <i class="bi bi-diagram-3" aria-hidden="true"></i><span>DDE</span>
        </a>

        <p class="sidebar-section">Service informatique</p>
        <a href="<?= URL_BASE ?>/requisitions" class="sidebar-link<?= $actif('/requisitions') ?>">
            <i class="bi bi-cart-check" aria-hidden="true"></i><span>Réquisitions</span>
        </a>

        <?php if (Auth::estAdmin()): ?>
            <p class="sidebar-section">Administration</p>
            <a href="<?= URL_BASE ?>/utilisateurs" class="sidebar-link<?= $actif('/utilisateurs') ?>">
                <i class="bi bi-people" aria-hidden="true"></i><span>Utilisateurs</span>
            </a>
            <a href="<?= URL_BASE ?>/journal" class="sidebar-link<?= $actif('/journal') ?>">
                <i class="bi bi-journal-text" aria-hidden="true"></i><span>Journal d'activités</span>
            </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <a class="sidebar-user" href="<?= URL_BASE ?>/profil">
            <i class="bi bi-person-circle" aria-hidden="true"></i>
            <span>
                <span class="sidebar-user-name"><?= e(Auth::nom()) ?></span>
                <span class="sidebar-user-role"><?= e(Format::role(Auth::role())) ?></span>
            </span>
        </a>
        <form method="POST" action="<?= URL_BASE ?>/logout">
            <?= Csrf::champ() ?>
            <button type="submit" class="sidebar-logout" title="Se déconnecter">
                <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                <span class="visually-hidden">Se déconnecter</span>
            </button>
        </form>
    </div>
</aside>

<div class="main-content">
    <header class="topbar">
        <button type="button" class="topbar-toggle" data-basculer-menu aria-controls="sidebar" aria-expanded="false">
            <i class="bi bi-list" aria-hidden="true"></i>
            <span class="visually-hidden">Ouvrir le menu</span>
        </button>
        <h1 class="topbar-title"><?= e($titrePage ?? 'Tableau de bord') ?></h1>
        <div class="topbar-context">
            <span class="topbar-ministry">MENFP · UEP</span>
            <span class="topbar-date"><i class="bi bi-calendar3" aria-hidden="true"></i> <?= date('d/m/Y') ?></span>
        </div>
    </header>

    <main class="page-content" id="contenu-principal">
        <?php require RACINE_VIEWS . '/partials/flash.php'; ?>
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
        <p class="footer-version"><?= e(APP_NOM) ?> — version <?= e(APP_VERSION) ?></p>
    </footer>
</div>

<script src="<?= URL_BASE ?>/assets/vendor/bootstrap/bootstrap.bundle.min.js" defer></script>
<script src="<?= URL_BASE ?>/assets/js/app.js?v=<?= e(APP_VERSION) ?>" defer></script>
<?= $scriptsPage ?? '' ?>
</body>
</html>
