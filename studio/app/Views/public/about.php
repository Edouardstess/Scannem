<?php
/**
 * About: the portrait held in place while the text scrolls, the motto of
 * the homepage crossing the accent band, then the offers.
 *
 * @var array<string, mixed>             $settings
 * @var array<int, array<string, mixed>> $services
 * @var array<int, array<string, mixed>> $pictures  published portfolio, featured first
 */

use App\Core\View;
use App\Services\ImmersiveHomeService;

View::extend('layouts.public');
View::startSection('content');

$texts = (new ImmersiveHomeService($settings))->texts();
$image = first_filled($settings['about_image'] ?? '', $pictures[0]['full'] ?? '');
$name = first_filled($settings['photographer_name'] ?? '', $settings['studio_name'] ?? '');
$paragraphs = array_values(array_filter(
    array_map('trim', preg_split('/\R{2,}/', (string) ($settings['about_text'] ?? '')) ?: []),
    static fn (string $p): bool => $p !== ''
));

if ($paragraphs === []) {
    // No "coming soon" in public: the tagline stands in until the text is written.
    $paragraphs = [first_filled($settings['tagline'] ?? '', $settings['hero_subtitle'] ?? '', $texts['positioning'])];
}

$tags = array_values(array_filter(array_map('trim', preg_split('/[·,•|]/u', (string) ($settings['speciality'] ?? '')) ?: [])));
$words = [$texts['motto_1_word'], $texts['motto_2_word'], $texts['motto_3_word']];
?>

<section class="ph">
    <p class="ph-kicker mono" data-reveal><span>À propos</span><?php if ($name !== ''): ?><span class="ash"><?= e(mb_strtoupper($name)) ?></span><?php endif; ?></p>
    <h1 class="ph-title ph-title--long split"><?= View::include('partials.split', ['text' => first_filled($settings['about_title'] ?? '', 'Photographier ce qui ne se rejoue pas')]) ?></h1>
</section>

<section class="sec sec--tight">
    <div class="about">
        <?php if ($image !== ''): ?>
            <figure class="about-media">
                <div class="clip" data-clip>
                    <img src="<?= e(url($image)) ?>" alt="<?= e($name) ?>" decoding="async">
                </div>
                <figcaption class="mono ash"><?= e(mb_strtoupper($name)) ?> — <?= e(first_filled($settings['speciality'] ?? '', 'Photographe')) ?></figcaption>
            </figure>
        <?php endif; ?>

        <div class="about-body">
            <div class="prose">
                <?php foreach ($paragraphs as $i => $paragraph): ?>
                    <p class="<?= $i === 0 ? 'lead' : '' ?>" data-reveal style="--i: <?= (int) min($i, 3) ?>"><?= nl2br(e($paragraph)) ?></p>
                <?php endforeach; ?>
            </div>

            <?php if ($tags !== []): ?>
                <ul class="tags mono" data-reveal>
                    <?php foreach ($tags as $tag): ?><li><?= e($tag) ?></li><?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <p class="actions" data-reveal>
                <a class="btn btn--ink" href="<?= e(url('/portfolio')) ?>">Voir le portfolio <span class="arr" aria-hidden="true">→</span></a>
                <a class="btn btn--line" href="<?= e(url('/contact')) ?>">Me contacter</a>
            </p>

            <div class="manif" data-reveal>
                <p class="mono ash">Ma démarche</p>
                <?php
                // The homepage manifesto, with its circled words.
                $parts = preg_split('/\[\[(.+?)\]\]/u', $texts['manifesto'], 2, PREG_SPLIT_DELIM_CAPTURE) ?: [$texts['manifesto']];
                ?>
                <p class="lead"><?= e($parts[0]) ?><?php if (isset($parts[1])): ?><?= View::include('partials.oval', ['text' => $parts[1]]) ?><?= e($parts[2] ?? '') ?><?php endif; ?></p>
            </div>
        </div>
    </div>
</section>

<section class="band" aria-label="<?= e($texts['motto_kicker']) ?>">
    <div class="diag to-lime"></div>
    <div class="band-body">
        <p class="mono ash" data-reveal><?= e($texts['motto_kicker']) ?></p>
        <div class="marquee" data-marquee aria-hidden="true">
            <?php foreach (array_merge($words, $words) as $word): ?><span><?= e($word) ?></span><?php endforeach; ?>
        </div>
        <ol class="hints mono">
            <?php foreach ([1, 2, 3] as $i): ?>
                <li data-reveal style="--i: <?= (int) $i ?>"><strong><?= e($texts["motto_{$i}_word"]) ?></strong><?= e($texts["motto_{$i}_hint"]) ?></li>
            <?php endforeach; ?>
        </ol>
    </div>
    <div class="diag from-lime"></div>
</section>

<?php if ($services !== []): ?>
    <section class="sec">
        <div class="sec-head">
            <div>
                <p class="mono ash sec-kicker" data-reveal>Travailler ensemble</p>
                <h2 class="sec-title split"><?= View::include('partials.split', ['text' => 'Ce que je propose']) ?></h2>
            </div>
            <a class="mono link-u" href="<?= e(url('/services')) ?>" data-reveal>Toutes les prestations →</a>
        </div>
        <ol class="offers">
            <?php foreach (array_slice($services, 0, 4) as $index => $service): ?>
                <li data-reveal style="--i: <?= (int) $index ?>">
                    <a class="offer" href="<?= e(url('/services/' . (string) $service['slug'])) ?>">
                        <span class="offer-num mono"><?= e(sprintf('(%02d)', $index + 1)) ?></span>
                        <span class="offer-main">
                            <span class="offer-title"><?= e((string) $service['title']) ?></span>
                        </span>
                        <span class="offer-meta">
                            <?php if (($service['price_from'] ?? null) !== null): ?>
                                <span class="mono ash">À partir de</span>
                                <span class="offer-price"><?= e(format_price($service['price_from'], (string) $service['currency'])) ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="offer-arrow" aria-hidden="true">→</span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>
<?php endif; ?>

<?= View::include('partials.cta_band', ['settings' => $settings, 'kicker' => $texts['contact_kicker'], 'title' => 'Racontons votre histoire.']) ?>

<?php View::endSection(); ?>
