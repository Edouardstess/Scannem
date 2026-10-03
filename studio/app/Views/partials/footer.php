<?php
/**
 * The homepage footer, on every page: the e-mail set large, the studio name
 * across the full width, then the practical links.
 *
 * @var array<string, mixed> $settings
 */

use App\Services\ImmersiveHomeService;
use App\Services\MapService;

// Settings are shared on every normal request; the fallback keeps the
// error pages renderable when a failure happens before that.
$settings = $settings ?? \App\Services\SettingsService::DEFAULTS;

$texts = (new ImmersiveHomeService($settings))->texts();
$studioName = (string) ($settings['studio_name'] ?? 'L\'ENFANT VISUAL');
$email = trim((string) ($settings['contact_email'] ?? ''));
$phone = trim((string) ($settings['contact_phone'] ?? ''));
$address = trim((string) ($settings['contact_address'] ?? ''));
$directions = (new MapService($settings))->directionsUrl();
$letters = mb_str_split(mb_strtoupper($studioName));

$socials = array_filter([
    'Instagram' => (string) ($settings['social_instagram'] ?? ''),
    'Facebook'  => (string) ($settings['social_facebook'] ?? ''),
    'LinkedIn'  => (string) ($settings['social_linkedin'] ?? ''),
    'Pinterest' => (string) ($settings['social_pinterest'] ?? ''),
], static fn (string $url): bool => str_starts_with($url, 'https://'));
?>
<footer class="footer" id="contact">
    <p class="footer-kicker mono ash" data-reveal><?= e($texts['contact_kicker']) ?></p>

    <?php if ($email !== ''): ?>
        <a class="footer-mail" href="mailto:<?= e($email) ?>" data-reveal style="--i: 1"><span><?= e($email) ?></span></a>
    <?php else: ?>
        <a class="footer-mail" href="<?= e(url('/contact')) ?>" data-reveal style="--i: 1"><span>Écrivez-moi</span></a>
    <?php endif; ?>

    <p class="footer-reassurance mono ash" data-reveal style="--i: 2"><?= e($texts['reassurance']) ?></p>

    <p class="footer-name" aria-label="<?= e($studioName) ?>">
        <?php foreach ($letters as $i => $letter): ?><span class="ch" aria-hidden="true"><span class="chi" style="--i: <?= (int) $i ?>"><?= $letter === ' ' ? '&nbsp;' : e($letter) ?></span></span><?php endforeach; ?>
    </p>

    <div class="footer-cols mono">
        <div>
            <p class="ash">© <?= e(date('Y')) ?> — <?= e(mb_strtoupper($studioName)) ?></p>
            <p class="ash"><?= e($texts['signature']) ?></p>
        </div>
        <div>
            <p class="ash">Pages</p>
            <p><a href="<?= e(url('/portfolio')) ?>">Portfolio</a></p>
            <p><a href="<?= e(url('/services')) ?>">Prestations</a></p>
            <p><a href="<?= e(url('/a-propos')) ?>">À propos</a></p>
            <p><a href="<?= e(url('/contact')) ?>">Contact</a></p>
            <?php if (!empty($settings['client_area_enabled'])): ?>
                <p><a href="<?= e(url('/espace-client')) ?>">Espace client</a></p>
            <?php endif; ?>
        </div>
        <div>
            <p class="ash">Studio</p>
            <?php if ($phone !== ''): ?>
                <p><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $phone)) ?>"><?= e($phone) ?></a></p>
            <?php endif; ?>
            <?php if ($address !== ''): ?>
                <p><?= e($address) ?></p>
            <?php endif; ?>
            <?php if ($directions !== null): ?>
                <p><a href="<?= e($directions) ?>" target="_blank" rel="noopener noreferrer">Itinéraire ↗</a></p>
            <?php endif; ?>
        </div>
        <div>
            <p class="ash">Suivre</p>
            <?php foreach ($socials as $label => $href): ?>
                <p><a href="<?= e($href) ?>" target="_blank" rel="noopener noreferrer"><?= e($label) ?> ↗</a></p>
            <?php endforeach; ?>
            <p><a href="<?= e(url('/admin')) ?>">Accès photographe</a></p>
        </div>
    </div>
</footer>
