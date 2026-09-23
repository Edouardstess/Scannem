<?php
/**
 * Public site layout.
 *
 * @var array<string, mixed> $settings
 * @var string|null          $title
 * @var string               $currentPath
 */

use App\Core\View;

// Settings are shared on every normal request; the fallback keeps the
// error pages renderable when a failure happens before that.
$settings = $settings ?? \App\Services\SettingsService::DEFAULTS;
$currentPath = $currentPath ?? '/';

$studioName = (string) ($settings['studio_name'] ?? 'Studio');
$pageTitle = isset($title) && $title !== null && $title !== ''
    ? $title . ' — ' . $studioName
    : $studioName . ' — ' . (string) ($settings['tagline'] ?? '');
$description = View::section('meta_description') !== ''
    ? View::section('meta_description')
    : (string) ($settings['meta_description'] ?? $settings['tagline'] ?? '');
$ogImage = ($settings['hero_image'] ?? '') !== '' ? url((string) $settings['hero_image']) : null;
$canonical = canonical_url(ltrim($currentPath, '/'));
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e(str_excerpt($description, 160)) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<meta name="robots" content="index, follow">

<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e($studioName) ?>">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e(str_excerpt($description, 160)) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<?php if ($ogImage !== null): ?>
<meta property="og:image" content="<?= e($ogImage) ?>">
<?php endif; ?>
<meta name="twitter:card" content="<?= $ogImage !== null ? 'summary_large_image' : 'summary' ?>">
<meta name="twitter:title" content="<?= e($pageTitle) ?>">
<meta name="twitter:description" content="<?= e(str_excerpt($description, 160)) ?>">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;500&family=Inter:wght@300;400;500;600&display=swap">
<link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
<link rel="icon" href="<?= e(asset('images/favicon.svg')) ?>" type="image/svg+xml">

<?= View::include('partials.theme', ['settings' => $settings]) ?>
<?= View::include('partials.schema', ['settings' => $settings, 'currentPath' => $currentPath]) ?>
</head>
<body class="site<?= $currentPath === '/' ? ' site--home' : '' ?>">

<a class="skip-link" href="#main">Aller au contenu</a>

<?= View::include('partials.header', ['settings' => $settings, 'currentPath' => $currentPath]) ?>

<main id="main">
<?= View::include('partials.flashes', ['flashes' => $flashes ?? []]) ?>
<?= View::section('content') ?>
</main>

<?= View::include('partials.footer', ['settings' => $settings]) ?>

<script src="<?= e(asset('js/site.js')) ?>" defer></script>
<?= View::section('scripts') ?>
</body>
</html>
