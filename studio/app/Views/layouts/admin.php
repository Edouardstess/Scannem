<?php
/**
 * Admin layout.
 *
 * @var array<string, mixed>      $settings
 * @var array<string, mixed>|null $auth
 * @var string                    $currentPath
 * @var string|null               $title
 */

use App\Core\Auth;
use App\Core\View;
use App\Models\Role;

// Settings are shared on every normal request; the fallback keeps the
// error pages renderable when a failure happens before that.
$settings = $settings ?? \App\Services\SettingsService::DEFAULTS;
$currentPath = $currentPath ?? '/';

$studioName = (string) ($settings['studio_name'] ?? 'L\'ENFANT VISUAL');

$sections = [
    ['path' => '/admin',            'label' => 'Tableau de bord', 'permission' => null,               'exact' => true],
    ['path' => '/admin/clients',    'label' => 'Clients',         'permission' => 'client.manage',    'exact' => false],
    ['path' => '/admin/events',     'label' => 'Événements',      'permission' => 'event.manage',     'exact' => false],
    ['path' => '/admin/galleries',  'label' => 'Galeries',        'permission' => 'gallery.view',     'exact' => false],
    ['path' => '/admin/portfolio',  'label' => 'Portfolio',       'permission' => 'portfolio.manage', 'exact' => false],
    ['path' => '/admin/services',   'label' => 'Prestations',     'permission' => 'portfolio.manage', 'exact' => false],
    ['path' => '/admin/messages',   'label' => 'Messages',        'permission' => 'message.manage',   'exact' => false],
    ['path' => '/admin/bookings',   'label' => 'Réservations',    'permission' => 'message.manage',   'exact' => false],
    ['path' => '/admin/statistics', 'label' => 'Statistiques',    'permission' => 'statistics.view',  'exact' => false],
    ['path' => '/admin/settings',   'label' => 'Paramètres',      'permission' => 'settings.manage',  'exact' => false],
];

$isActive = static function (array $section) use ($currentPath): bool {
    return $section['exact']
        ? $currentPath === $section['path']
        : str_starts_with($currentPath, $section['path']);
};
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($title ?? 'Administration') . ' — ' . $studioName) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
<link rel="icon" href="<?= e(asset('images/favicon.svg')) ?>" type="image/svg+xml">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
</head>
<body class="admin">

<a class="skip-link" href="#admin-main">Aller au contenu</a>

<aside class="admin-sidebar" data-sidebar>
    <div class="admin-sidebar__head">
        <a class="admin-sidebar__brand" href="<?= e(url('/admin')) ?>"><?= e($studioName) ?></a>
        <button class="admin-sidebar__close" type="button" data-sidebar-close aria-label="Fermer le menu">&times;</button>
    </div>

    <nav class="admin-nav" aria-label="Navigation de l'administration">
        <ul>
            <?php foreach ($sections as $section): ?>
                <?php if ($section['permission'] !== null && Auth::cannot($section['permission'])) {
                    continue;
                } ?>
                <li>
                    <a class="admin-nav__link<?= $isActive($section) ? ' is-active' : '' ?>"
                       href="<?= e(url($section['path'])) ?>"
                       <?= $isActive($section) ? 'aria-current="page"' : '' ?>>
                        <?= e($section['label']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <div class="admin-sidebar__foot">
        <a class="admin-sidebar__site" href="<?= e(url('/')) ?>" target="_blank" rel="noopener">
            Voir le site
        </a>
    </div>
</aside>

<div class="admin-shell">
    <header class="admin-topbar">
        <button class="admin-topbar__burger" type="button" data-sidebar-open aria-label="Ouvrir le menu">
            <span></span><span></span><span></span>
        </button>

        <h1 class="admin-topbar__title"><?= e((string) ($title ?? 'Administration')) ?></h1>

        <div class="admin-topbar__user">
            <?php if ($auth !== null): ?>
                <a class="admin-topbar__name" href="<?= e(url('/admin/profile')) ?>">
                    <?= e((string) $auth['name']) ?>
                    <span class="admin-topbar__role"><?= e(Role::label((string) $auth['role'])) ?></span>
                </a>
                <form method="post" action="<?= e(url('/admin/logout')) ?>" class="admin-topbar__logout">
                    <?= csrf_field() ?>
                    <button type="submit" class="button button--small button--ghost">Déconnexion</button>
                </form>
            <?php endif; ?>
        </div>
    </header>

    <main class="admin-main" id="admin-main">
        <?= View::include('partials.flashes', ['flashes' => $flashes ?? []]) ?>
        <?= View::section('content') ?>
    </main>
</div>

<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
<?= View::section('scripts') ?>
</body>
</html>
