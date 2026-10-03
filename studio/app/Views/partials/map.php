<?php
/**
 * "Find us" block: Google map, address and route buttons.
 *
 * With map_click_to_load the frame (and Google's cookies) only arrives after
 * the visitor asks for it; pages.js swaps the placeholder for the map.
 *
 * @var array<string, mixed> $settings
 * @var string               $headingLevel  'h1'…'h3', for the page outline
 */

use App\Core\View;
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
<section class="sec location" id="localisation" aria-labelledby="localisation-title">
    <div class="where">
        <div class="where-text">
            <p class="mono ash sec-kicker" data-reveal>Nous trouver</p>
            <<?= e($heading) ?> class="sec-title split" id="localisation-title"><?= View::include('partials.split', ['text' => 'Venir au studio']) ?></<?= e($heading) ?>>

            <?php if ($address !== ''): ?>
                <address class="where-addr" data-reveal style="--i: 1"><?= nl2br(e($address)) ?></address>
            <?php endif; ?>

            <?php if ($phone !== ''): ?>
                <p class="mono" data-reveal style="--i: 2; margin-top: 14px;">
                    <a class="link-u" href="tel:<?= e(preg_replace('/[^0-9+]/', '', $phone)) ?>"><?= e($phone) ?></a>
                </p>
            <?php endif; ?>

            <div class="actions" data-reveal style="--i: 3">
                <a class="btn" href="<?= e((string) $map->directionsUrl()) ?>"
                   target="_blank" rel="noopener noreferrer">Itinéraire <span class="arr" aria-hidden="true">↗</span></a>
                <a class="btn btn--line" href="<?= e((string) $map->searchUrl()) ?>"
                   target="_blank" rel="noopener noreferrer">Ouvrir dans Google Maps</a>
            </div>
        </div>

        <div class="where-map clip" data-clip>
            <?php if ($map->clickToLoad()): ?>
                <div class="map-facade" data-map-facade data-src="<?= e((string) $map->embedUrl()) ?>"
                     data-title="<?= e($title) ?>">
                    <p>La carte est fournie par Google Maps, qui dépose des cookies à son affichage.</p>
                    <button class="btn btn--line" type="button" data-map-load>Afficher la carte</button>
                </div>
            <?php else: ?>
                <iframe src="<?= e((string) $map->embedUrl()) ?>"
                        title="<?= e($title) ?>" loading="lazy" allowfullscreen
                        referrerpolicy="no-referrer-when-downgrade"></iframe>
            <?php endif; ?>
        </div>
    </div>
</section>
