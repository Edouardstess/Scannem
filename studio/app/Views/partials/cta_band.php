<?php
/**
 * End-of-page call to action on the accent band, with the diagonal edges
 * of the homepage's motto band.
 *
 * @var array<string, mixed> $settings
 * @var string               $title   the large line
 * @var string               $kicker
 */

use App\Core\View;

$settings = $settings ?? \App\Services\SettingsService::DEFAULTS;
$booking = !empty($settings['booking_enabled']);
?>
<section class="band" aria-label="Prendre rendez-vous">
    <div class="diag to-lime"></div>
    <div class="band-body">
        <p class="mono ash" data-reveal><?= e($kicker ?? 'UN PROJET EN TÊTE ?') ?></p>
        <div class="cta-row">
            <h2 class="cta-title split"><?= View::include('partials.split', ['text' => (string) ($title ?? 'Parlons de vos images.')]) ?></h2>
            <p class="cta-link" data-reveal style="--i: 2">
                <a href="<?= e(url($booking ? '/reservation' : '/contact')) ?>"><?= View::include('partials.oval', ['text' => $booking ? 'Réserver une séance →' : 'Me contacter →']) ?></a>
            </p>
        </div>
    </div>
    <div class="diag from-lime"></div>
</section>
