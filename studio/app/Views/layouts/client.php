<?php
/**
 * Layout for client-facing gallery pages.
 *
 * Deliberately separate from the public site layout: no site navigation, no
 * footer links back into the marketing pages, and noindex. A client opening
 * their gallery should see their photographs, and a search engine that
 * somehow receives the URL should not index it.
 *
 * @var array<string, mixed> $settings
 * @var string|null          $title
 */

use App\Core\View;

// Settings are shared on every normal request; the fallback keeps the
// error pages renderable when a failure happens before that.
$settings = $settings ?? \App\Services\SettingsService::DEFAULTS;

$studioName = (string) ($settings['studio_name'] ?? 'Studio');
$logo = (string) ($settings['logo_path'] ?? '');
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($title ?? 'Galerie') . ' — ' . $studioName) ?></title>

<?php /* Client galleries must never appear in a search index. */ ?>
<meta name="robots" content="noindex, nofollow, noarchive, noimageindex">
<meta name="referrer" content="same-origin">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400&family=Inter:wght@300;400;500;600&display=swap">
<link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/gallery.css')) ?>">
<link rel="icon" href="<?= e(asset('images/favicon.svg')) ?>" type="image/svg+xml">

<?= View::include('partials.theme', ['settings' => $settings]) ?>
</head>
<body class="client">

<a class="skip-link" href="#gallery-main">Aller aux photos</a>

<header class="client-header">
    <div class="client-header__inner">
        <a class="client-header__brand" href="<?= e(url('/')) ?>">
            <?php if ($logo !== ''): ?>
                <img src="<?= e(url($logo)) ?>" alt="<?= e($studioName) ?>" class="client-header__logo">
            <?php else: ?>
                <span class="client-header__wordmark"><?= e($studioName) ?></span>
            <?php endif; ?>
        </a>
        <?= View::section('header_actions') ?>
    </div>
</header>

<main id="gallery-main" class="client-main">
<?= View::include('partials.flashes', ['flashes' => $flashes ?? []]) ?>
<?= View::section('content') ?>
</main>

<footer class="client-footer">
    <p>© <?= e(date('Y')) ?> <?= e($studioName) ?></p>
</footer>

<?= View::section('scripts') ?>
</body>
</html>
