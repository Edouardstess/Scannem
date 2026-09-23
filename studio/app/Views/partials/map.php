<?php
/**
 * "Find us" block: Google map, address and route buttons.
 *
 * With map_click_to_load the frame (and Google's cookies) only arrives after
 * the visitor asks for it; site.js swaps the placeholder for the map.
 *
 * @var array<string, mixed> $settings
 * @var string               $headingLevel  'h1'…'h3', for the page outline
 */

use App\Services\MapService;

$settings = $settings ?? \App\Services\SettingsService::DEFAULTS;
$map = new MapService($settings);

if (!$map->isAvailable()) {
    return;
}

$studioName = (string) ($settings['studio_name'] ?? 'L\'ENFANT VISUAL');
$address = trim((string) ($settings['contact_address'] ?? ''));
$phone = trim((string) ($settings['contact_phone'] ?? ''));
$heading = in_array($headingLevel ?? 'h2', ['h1', 'h2', 'h3'], true) ? $headingLevel : 'h2';
$title = 'Carte : ' . $studioName . ($address !== '' ? ', ' . $address : '');
?>
<section class="location" id="localisation" aria-labelledby="localisation-title">
    <div class="wrap location__inner">
        <div class="location__text">
            <p class="section__eyebrow">Nous trouver</p>
            <<?= e($heading) ?> class="section__title" id="localisation-title">Venir au studio</<?= e($heading) ?>>

            <?php if ($address !== ''): ?>
                <address class="location__address"><?= nl2br(e($address)) ?></address>
            <?php endif; ?>

            <?php if ($phone !== ''): ?>
                <p class="location__phone">
                    <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $phone)) ?>"><?= e($phone) ?></a>
                </p>
            <?php endif; ?>

            <div class="location__actions">
                <a class="button" href="<?= e((string) $map->directionsUrl()) ?>"
                   target="_blank" rel="noopener noreferrer">Itinéraire</a>
                <a class="button button--ghost" href="<?= e((string) $map->searchUrl()) ?>"
                   target="_blank" rel="noopener noreferrer">Ouvrir dans Google Maps</a>
            </div>
        </div>

        <div class="location__map">
            <?php if ($map->clickToLoad()): ?>
                <div class="map-facade" data-map-facade data-src="<?= e((string) $map->embedUrl()) ?>"
                     data-title="<?= e($title) ?>">
                    <p class="map-facade__text">
                        La carte est fournie par Google Maps, qui dépose des cookies à son affichage.
                    </p>
                    <button class="button button--small" type="button" data-map-load>Afficher la carte</button>
                </div>
            <?php else: ?>
                <iframe class="location__frame" src="<?= e((string) $map->embedUrl()) ?>"
                        title="<?= e($title) ?>" loading="lazy" allowfullscreen
                        referrerpolicy="no-referrer-when-downgrade"></iframe>
            <?php endif; ?>
        </div>
    </div>
</section>
