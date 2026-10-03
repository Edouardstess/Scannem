<?php
/**
 * Services as an index: one large line per offer, its photo following the
 * cursor, then the three steps of the homepage.
 *
 * @var array<int, array<string, mixed>> $services
 * @var array<int, array<string, mixed>> $pictures  published portfolio, featured first
 */

use App\Core\View;
use App\Services\ImmersiveHomeService;

View::extend('layouts.public');
View::startSection('content');

$texts = (new ImmersiveHomeService($settings))->texts();
$prices = array_filter(array_map(static fn (array $s): ?float => $s['price_from'] !== null ? (float) $s['price_from'] : null, $services));
$currency = (string) ($services[0]['currency'] ?? 'EUR');

/** The service's own image, else a portfolio photo so every row has one. */
$imageFor = static function (array $service, int $index) use ($pictures): string {
    if (($service['image_path'] ?? '') !== '') {
        return (string) $service['image_path'];
    }

    return $pictures === [] ? '' : (string) $pictures[$index % count($pictures)]['thumb'];
};
?>

<section class="ph">
    <p class="ph-kicker mono" data-reveal><span>Prestations</span></p>
    <h1 class="ph-title split"><?= View::include('partials.split', ['text' => 'Formules et tarifs']) ?></h1>
    <p class="ph-lead" data-reveal style="--i: 3">Chaque projet est différent. Ces formules sont un point de départ : on les ajuste ensemble.</p>
    <dl class="ph-meta mono" data-reveal style="--i: 4">
        <div><dt>Formules</dt><dd><?= e(sprintf('%02d', count($services))) ?></dd></div>
        <?php if ($prices !== []): ?>
            <div><dt>À partir de</dt><dd><?= e(format_price(min($prices), $currency)) ?></dd></div>
        <?php endif; ?>
        <div><dt>Livraison</dt><dd>Galerie privée en ligne</dd></div>
        <div><dt>Devis</dt><dd>Gratuit, sans engagement</dd></div>
    </dl>
</section>

<section class="sec sec--tight">
    <?php if ($services === []): ?>
        <p class="empty">Les prestations seront publiées prochainement.</p>
    <?php else: ?>
        <ol class="offers">
            <?php foreach ($services as $index => $service): ?>
                <?php $image = $imageFor($service, $index); ?>
                <li data-reveal style="--i: <?= (int) min($index, 4) ?>">
                    <a class="offer" href="<?= e(url('/services/' . (string) $service['slug'])) ?>"<?= $image !== '' ? ' data-follow-src="' . e(url($image)) . '"' : '' ?>>
                        <span class="offer-num mono"><?= e(sprintf('(%02d)', $index + 1)) ?></span>
                        <span class="offer-main">
                            <span class="offer-title"><?= e((string) $service['title']) ?></span>
                            <?php if (($service['summary'] ?? '') !== ''): ?>
                                <span class="offer-sum"><?= e((string) $service['summary']) ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="offer-meta">
                            <?php if (($service['price_from'] ?? null) !== null): ?>
                                <span class="mono ash">À partir de</span>
                                <span class="offer-price"><?= e(format_price($service['price_from'], (string) $service['currency'])) ?></span>
                            <?php endif; ?>
                            <?php if (($service['duration'] ?? '') !== ''): ?>
                                <span class="mono ash offer-dur"><?= e((string) $service['duration']) ?></span>
                            <?php endif; ?>
                        </span>
                        <?php if ($image !== ''): ?>
                            <span class="offer-thumb"><img src="<?= e(url($image)) ?>" alt="" loading="lazy" decoding="async"></span>
                        <?php endif; ?>
                        <span class="offer-arrow" aria-hidden="true">→</span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ol>
        <div class="follow" data-follow aria-hidden="true"><img alt=""></div>
    <?php endif; ?>
</section>

<section class="sec">
    <div class="sec-head">
        <div>
            <p class="mono ash sec-kicker" data-reveal>Comment ça se passe</p>
            <h2 class="sec-title split"><?= View::include('partials.split', ['text' => 'Trois étapes, aucune surprise.']) ?></h2>
        </div>
    </div>
    <ol class="steps">
        <?php foreach ([1, 2, 3] as $i): ?>
            <li class="step" data-reveal style="--i: <?= (int) $i ?>">
                <span class="mono ash"><?= e(sprintf('Étape — %02d', $i)) ?></span>
                <span class="step-num" aria-hidden="true"><?= e(sprintf('%02d', $i)) ?></span>
                <h3><?= e($texts["step_{$i}_name"]) ?></h3>
                <p><?= e($texts["step_{$i}_desc"]) ?></p>
            </li>
        <?php endforeach; ?>
    </ol>
</section>

<?= View::include('partials.cta_band', ['settings' => $settings, 'kicker' => 'UNE QUESTION SUR UNE FORMULE ?', 'title' => 'Parlons de votre projet.']) ?>

<?php View::endSection(); ?>
