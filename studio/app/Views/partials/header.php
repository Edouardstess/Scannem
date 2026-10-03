<?php
/**
 * Site chrome: the transition curtain, the floating dock of the homepage
 * and, on narrow screens, the full-screen menu.
 *
 * @var array<string, mixed> $settings
 * @var string               $currentPath
 */

// Settings are shared on every normal request; the fallback keeps the
// error pages renderable when a failure happens before that.
$settings = $settings ?? \App\Services\SettingsService::DEFAULTS;

$currentPath = $currentPath ?? '/';
$studioName = (string) ($settings['studio_name'] ?? 'L\'ENFANT VISUAL');
$logo = (string) ($settings['logo_path'] ?? '');
$email = trim((string) ($settings['contact_email'] ?? ''));
$booking = !empty($settings['booking_enabled']);

$links = [
    ['path' => '/portfolio', 'label' => 'Portfolio', 'optional' => false],
    ['path' => '/services',  'label' => 'Prestations', 'optional' => false],
    ['path' => '/a-propos',  'label' => 'À propos', 'optional' => true],
    ['path' => '/contact',   'label' => 'Contact', 'optional' => false],
];

if (!empty($settings['client_area_enabled'])) {
    $links[] = ['path' => '/espace-client', 'label' => 'Espace client', 'optional' => true];
}

$isActive = static fn (string $path): bool => str_starts_with($currentPath, $path);
?>
<div class="curtain" aria-hidden="true">
    <?php foreach (range(0, 4) as $i): ?><span style="--i: <?= (int) $i ?>"></span><?php endforeach; ?>
</div>

<header class="dock mono" data-header>
    <a class="dock-wordmark" href="<?= e(url('/')) ?>">
        <?php if ($logo !== ''): ?>
            <img src="<?= e(url($logo)) ?>" alt="<?= e($studioName) ?>">
        <?php else: ?>
            <?= e($studioName) ?>
        <?php endif; ?>
    </a>

    <nav class="dock-nav" aria-label="Navigation principale">
        <?php foreach ($links as $link): ?>
            <a class="dock-link<?= $link['optional'] ? ' dock-link--opt' : '' ?>" href="<?= e(url($link['path'])) ?>"<?= $isActive($link['path']) ? ' aria-current="page"' : '' ?>><?= e($link['label']) ?></a>
        <?php endforeach; ?>
    </nav>

    <button class="dock-menu mono" type="button" data-menu-toggle aria-expanded="false" aria-controls="menu">
        <span data-menu-label>Menu</span><span class="dock-menu-icon" aria-hidden="true"></span>
    </button>

    <?php if ($booking): ?>
        <a class="dock-cta" href="<?= e(url('/reservation')) ?>"<?= $isActive('/reservation') ? ' aria-current="page"' : '' ?>>Réserver</a>
    <?php else: ?>
        <a class="dock-cta" href="<?= e(url('/contact')) ?>">Contact</a>
    <?php endif; ?>
</header>

<div class="menu" id="menu">
    <nav aria-label="Menu">
        <ul class="menu-list">
            <?php foreach (array_merge([['path' => '/', 'label' => 'Accueil']], $links, $booking ? [['path' => '/reservation', 'label' => 'Réserver']] : []) as $i => $link): ?>
                <li>
                    <a href="<?= e(url($link['path'])) ?>" style="--i: <?= (int) $i ?>"<?= ($link['path'] === '/' ? $currentPath === '/' : $isActive($link['path'])) ? ' aria-current="page"' : '' ?>>
                        <span class="mono"><?= e(sprintf('%02d', $i + 1)) ?></span><?= e($link['label']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
    <div class="menu-foot mono">
        <?php if ($email !== ''): ?>
            <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a>
        <?php endif; ?>
        <span class="ash"><?= e(mb_strtoupper($studioName)) ?></span>
    </div>
</div>
