<?php
/**
 * @var array<string, mixed> $settings
 * @var string               $currentPath
 */

// Settings are shared on every normal request; the fallback keeps the
// error pages renderable when a failure happens before that.
$settings = $settings ?? \App\Services\SettingsService::DEFAULTS;

$currentPath = $currentPath ?? '/';
$studioName = (string) ($settings['studio_name'] ?? 'Studio');
$logo = (string) ($settings['logo_path'] ?? '');

$links = [
    ['path' => '/portfolio',     'label' => 'Portfolio'],
    ['path' => '/services',      'label' => 'Prestations'],
    ['path' => '/a-propos',      'label' => 'À propos'],
    ['path' => '/contact',       'label' => 'Contact'],
];

if (!empty($settings['client_area_enabled'])) {
    $links[] = ['path' => '/espace-client', 'label' => 'Espace client'];
}

$isActive = static function (string $path) use ($currentPath): bool {
    return $path === '/' ? $currentPath === '/' : str_starts_with($currentPath, $path);
};
?>
<header class="site-header" data-header>
    <div class="site-header__inner">
        <a class="site-header__brand" href="<?= e(url('/')) ?>">
            <?php if ($logo !== ''): ?>
                <img src="<?= e(url($logo)) ?>" alt="<?= e($studioName) ?>" class="site-header__logo">
            <?php else: ?>
                <span class="site-header__wordmark"><?= e($studioName) ?></span>
            <?php endif; ?>
        </a>

        <button class="site-header__toggle" type="button"
                aria-expanded="false" aria-controls="site-nav" data-nav-toggle>
            <span class="sr-only" data-nav-label>Ouvrir le menu</span>
            <span class="site-header__bars" aria-hidden="true"><span></span><span></span></span>
        </button>

        <nav class="site-nav" id="site-nav" data-nav aria-label="Navigation principale">
            <ul class="site-nav__list">
                <?php foreach ($links as $link): ?>
                    <li>
                        <a href="<?= e(url($link['path'])) ?>"
                           class="site-nav__link<?= $isActive($link['path']) ? ' is-active' : '' ?>"
                           <?= $isActive($link['path']) ? 'aria-current="page"' : '' ?>>
                            <?= e($link['label']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
                <?php if (!empty($settings['booking_enabled'])): ?>
                    <li class="site-nav__cta-item">
                        <a href="<?= e(url('/reservation')) ?>" class="button button--small">Réserver</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>
