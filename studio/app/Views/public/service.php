<?php
/**
 * One service: giant title, the figures that matter, a full-width photo,
 * what is included, and the other offers as an index.
 *
 * @var array<string, mixed>             $service
 * @var array<int, array<string, mixed>> $others
 * @var array<int, array<string, mixed>> $pictures  published portfolio, featured first
 */

use App\Core\View;

View::extend('layouts.public');

View::startSection('meta_description');
echo e(str_excerpt((string) ($service['summary'] ?? $service['description'] ?? ''), 160));
View::endSection();

View::startSection('content');

$deliverables = array_values(array_filter(
    array_map('trim', preg_split('/\R/', (string) ($service['deliverables'] ?? '')) ?: []),
    static fn (string $line): bool => $line !== ''
));
$paragraphs = array_values(array_filter(
    array_map('trim', preg_split('/\R{2,}/', (string) ($service['description'] ?? '')) ?: []),
    static fn (string $p): bool => $p !== ''
));
$image = first_filled($service['image_path'] ?? '', $pictures[0]['full'] ?? '');
$price = ($service['price_from'] ?? null) !== null ? format_price($service['price_from'], (string) $service['currency']) : '';
$booking = !empty($settings['booking_enabled']);
$bookUrl = url(($booking ? '/reservation' : '/contact') . '?service=' . (int) $service['id']);
?>

<section class="ph">
    <p class="ph-kicker mono" data-reveal>
        <span class="crumb"><a href="<?= e(url('/services')) ?>">Prestations</a></span>
        <span class="ash">/ <?= e((string) $service['title']) ?></span>
    </p>
    <h1 class="ph-title ph-title--long split"><?= View::include('partials.split', ['text' => (string) $service['title']]) ?></h1>
    <?php if (($service['summary'] ?? '') !== ''): ?>
        <p class="ph-lead" data-reveal style="--i: 3"><?= e((string) $service['summary']) ?></p>
    <?php endif; ?>
    <dl class="spec mono" data-reveal style="--i: 4">
        <?php if ($price !== ''): ?><div><dt>À partir de</dt><dd><?= e($price) ?></dd></div><?php endif; ?>
        <?php if (($service['duration'] ?? '') !== ''): ?><div><dt>Durée</dt><dd><?= e((string) $service['duration']) ?></dd></div><?php endif; ?>
        <?php if ($deliverables !== []): ?><div><dt>Inclus</dt><dd><?= e(sprintf('%02d éléments', count($deliverables))) ?></dd></div><?php endif; ?>
        <div><dt>Livraison</dt><dd>Galerie privée</dd></div>
    </dl>
</section>

<?php if ($image !== ''): ?>
    <div class="wide">
        <div class="clip" data-clip>
            <div class="par" data-speed="-0.08">
                <img src="<?= e(url($image)) ?>" alt="<?= e((string) $service['title']) ?>" decoding="async">
            </div>
        </div>
    </div>
<?php endif; ?>

<section class="sec">
    <div class="detail">
        <div>
            <?php if ($paragraphs !== []): ?>
                <div class="prose">
                    <?php foreach ($paragraphs as $i => $paragraph): ?>
                        <p class="<?= $i === 0 ? 'lead' : '' ?>" data-reveal style="--i: <?= (int) min($i, 3) ?>"><?= nl2br(e($paragraph)) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($deliverables !== []): ?>
                <div class="incl">
                    <h2 class="sec-title split"><?= View::include('partials.split', ['text' => 'Ce qui est inclus']) ?></h2>
                    <ol>
                        <?php foreach ($deliverables as $i => $line): ?>
                            <li data-reveal style="--i: <?= (int) min($i, 5) ?>"><?= e($line) ?></li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            <?php endif; ?>
        </div>

        <aside class="aside" data-reveal aria-label="Réserver cette prestation">
            <?php if ($price !== ''): ?>
                <div>
                    <p class="mono ash">À partir de</p>
                    <p class="aside-price"><?= e($price) ?></p>
                </div>
            <?php endif; ?>
            <?php if (($service['duration'] ?? '') !== ''): ?>
                <p class="aside-row mono"><span class="ash">Durée</span><span><?= e((string) $service['duration']) ?></span></p>
            <?php endif; ?>
            <p class="aside-row mono"><span class="ash">Devis</span><span>Gratuit</span></p>
            <a class="btn btn--block" href="<?= e($bookUrl) ?>"><?= $booking ? 'Réserver cette formule' : 'Demander un devis' ?> <span class="arr" aria-hidden="true">→</span></a>
            <a class="btn btn--line btn--block" href="<?= e(url('/contact')) ?>">Poser une question</a>
        </aside>
    </div>
</section>

<?php if ($others !== []): ?>
    <section class="sec">
        <p class="mono ash sec-kicker" data-reveal>Autres prestations</p>
        <ol class="offers">
            <?php foreach ($others as $index => $other): ?>
                <li data-reveal style="--i: <?= (int) min($index, 4) ?>">
                    <a class="offer" href="<?= e(url('/services/' . (string) $other['slug'])) ?>">
                        <span class="offer-num mono"><?= e(sprintf('(%02d)', $index + 1)) ?></span>
                        <span class="offer-main">
                            <span class="offer-title"><?= e((string) $other['title']) ?></span>
                            <?php if (($other['summary'] ?? '') !== ''): ?>
                                <span class="offer-sum"><?= e(str_excerpt((string) $other['summary'], 120)) ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="offer-meta">
                            <?php if (($other['price_from'] ?? null) !== null): ?>
                                <span class="mono ash">À partir de</span>
                                <span class="offer-price"><?= e(format_price($other['price_from'], (string) $other['currency'])) ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="offer-arrow" aria-hidden="true">→</span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>
<?php endif; ?>

<?= View::include('partials.cta_band', ['settings' => $settings, 'kicker' => mb_strtoupper((string) $service['title']), 'title' => 'Cette formule vous ressemble ?']) ?>

<?php View::endSection(); ?>
