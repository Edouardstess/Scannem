<?php
/** @var array<string, mixed> $settings */

// Settings are shared on every normal request; the fallback keeps the
// error pages renderable when a failure happens before that.
$settings = $settings ?? \App\Services\SettingsService::DEFAULTS;

$studioName = (string) ($settings['studio_name'] ?? 'L\'ENFANT VISUAL');
$email = (string) ($settings['contact_email'] ?? '');
$phone = (string) ($settings['contact_phone'] ?? '');
$address = (string) ($settings['contact_address'] ?? '');
$footerText = (string) ($settings['footer_text'] ?? '');

$socials = array_filter([
    'Instagram' => (string) ($settings['social_instagram'] ?? ''),
    'Facebook'  => (string) ($settings['social_facebook'] ?? ''),
    'LinkedIn'  => (string) ($settings['social_linkedin'] ?? ''),
    'Pinterest' => (string) ($settings['social_pinterest'] ?? ''),
], static fn (string $url): bool => $url !== '');
?>
<footer class="site-footer">
    <div class="site-footer__inner">
        <div class="site-footer__column">
            <p class="site-footer__brand"><?= e($studioName) ?></p>
            <?php if ($footerText !== ''): ?>
                <p class="site-footer__text"><?= e($footerText) ?></p>
            <?php elseif (($settings['tagline'] ?? '') !== ''): ?>
                <p class="site-footer__text"><?= e((string) $settings['tagline']) ?></p>
            <?php endif; ?>
        </div>

        <div class="site-footer__column">
            <p class="site-footer__heading">Contact</p>
            <?php if ($email !== ''): ?>
                <p><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></p>
            <?php endif; ?>
            <?php if ($phone !== ''): ?>
                <p><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $phone)) ?>"><?= e($phone) ?></a></p>
            <?php endif; ?>
            <?php if ($address !== ''): ?>
                <p><?= e($address) ?></p>
            <?php endif; ?>
            <?php if (($directions = (new \App\Services\MapService($settings))->directionsUrl()) !== null): ?>
                <p>
                    <a href="<?= e($directions) ?>" target="_blank" rel="noopener noreferrer">Itinéraire Google Maps</a>
                </p>
            <?php endif; ?>
        </div>

        <div class="site-footer__column">
            <p class="site-footer__heading">Navigation</p>
            <p><a href="<?= e(url('/portfolio')) ?>">Portfolio</a></p>
            <p><a href="<?= e(url('/services')) ?>">Prestations</a></p>
            <p><a href="<?= e(url('/contact')) ?>">Contact</a></p>
            <?php if (!empty($settings['client_area_enabled'])): ?>
                <p><a href="<?= e(url('/espace-client')) ?>">Espace client</a></p>
            <?php endif; ?>
        </div>

        <?php if ($socials !== []): ?>
            <div class="site-footer__column">
                <p class="site-footer__heading">Suivre</p>
                <?php foreach ($socials as $label => $href): ?>
                    <p><a href="<?= e($href) ?>" rel="noopener noreferrer" target="_blank"><?= e($label) ?></a></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="site-footer__legal">
        <p>© <?= e(date('Y')) ?> <?= e($studioName) ?>. Tous droits réservés.</p>
        <p><a href="<?= e(url('/admin')) ?>" class="site-footer__admin">Accès photographe</a></p>
    </div>
</footer>
