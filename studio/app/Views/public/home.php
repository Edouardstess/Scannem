<?php
/**
 * Homepage.
 *
 * @var array<string, mixed>             $settings
 * @var array<int, array<string, mixed>> $featured
 * @var array<int, array<string, mixed>> $services
 * @var array<int, array<string, mixed>> $categories
 */

use App\Core\View;

View::extend('layouts.public');
View::startSection('content');

$hero = (string) ($settings['hero_image'] ?? '');
$heroTitle = first_filled($settings['hero_title'] ?? '', $settings['tagline'] ?? '', $settings['studio_name'] ?? '');
$heroSubtitle = (string) ($settings['hero_subtitle'] ?? '');
$photographer = first_filled($settings['photographer_name'] ?? '', $settings['studio_name'] ?? '');
$speciality = (string) ($settings['speciality'] ?? '');

$testimonials = is_array($settings['testimonials'] ?? null) ? $settings['testimonials'] : [];
$process = is_array($settings['process_steps'] ?? null) ? $settings['process_steps'] : [];
?>

<section class="hero<?= $hero === '' ? ' hero--plain' : '' ?>">
    <?php if ($hero !== ''): ?>
        <img class="hero__image" src="<?= e(url($hero)) ?>" alt="" fetchpriority="high" decoding="async">
        <div class="hero__scrim" aria-hidden="true"></div>
    <?php endif; ?>

    <div class="hero__content">
        <?php if ($photographer !== ''): ?>
            <p class="hero__eyebrow"><?= e($photographer) ?><?= $speciality !== '' ? ' · ' . e($speciality) : '' ?></p>
        <?php endif; ?>

        <h1 class="hero__title"><?= e($heroTitle) ?></h1>

        <?php if ($heroSubtitle !== ''): ?>
            <p class="hero__subtitle"><?= e($heroSubtitle) ?></p>
        <?php endif; ?>

        <div class="hero__actions">
            <a class="button" href="<?= e(url('/portfolio')) ?>">Voir le portfolio</a>
            <?php if (!empty($settings['booking_enabled'])): ?>
                <a class="button button--ghost" href="<?= e(url('/reservation')) ?>">Réserver une séance</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if (trim((string) ($settings['about_text'] ?? '')) !== ''): ?>
    <section class="section section--intro">
        <div class="wrap wrap--narrow">
            <p class="section__eyebrow">Présentation</p>
            <div class="prose prose--lead">
                <?= nl2br(e(str_excerpt((string) $settings['about_text'], 700))) ?>
            </div>
            <p><a class="link-arrow" href="<?= e(url('/a-propos')) ?>">En savoir plus</a></p>
        </div>
    </section>
<?php endif; ?>

<?php if ($featured !== []): ?>
    <section class="section">
        <div class="wrap">
            <header class="section__header">
                <p class="section__eyebrow">Sélection</p>
                <h2 class="section__title">Travaux récents</h2>
            </header>

            <?php /* A uniform grid, not masonry: the homepage selection is a
                     composed block, and a ragged last row reads as broken. The
                     full portfolio page keeps masonry, where variety helps. */ ?>
            <div class="selection" data-lightbox-group="home">
                <?php foreach ($featured as $index => $item): ?>
                    <?php $thumb = (string) ($item['thumbnail_path'] ?? $item['image_path']); ?>
                    <figure class="selection__item">
                        <a class="selection__link"
                           href="<?= e(url((string) $item['image_path'])) ?>"
                           data-lightbox
                           data-caption="<?= e((string) $item['title']) ?>">
                            <img src="<?= e(url($thumb)) ?>"
                                 alt="<?= e((string) $item['title']) ?>"
                                 loading="<?= $index < 3 ? 'eager' : 'lazy' ?>"
                                 decoding="async">
                            <?php if (($item['category_name'] ?? '') !== ''): ?>
                                <span class="selection__tag"><?= e((string) $item['category_name']) ?></span>
                            <?php endif; ?>
                        </a>
                    </figure>
                <?php endforeach; ?>
            </div>

            <p class="section__more"><a class="link-arrow" href="<?= e(url('/portfolio')) ?>">Tout le portfolio</a></p>
        </div>
    </section>
<?php endif; ?>

<?php if ($categories !== []): ?>
    <section class="section section--muted">
        <div class="wrap">
            <header class="section__header">
                <p class="section__eyebrow">Spécialités</p>
                <h2 class="section__title">Ce que je photographie</h2>
            </header>

            <ul class="pill-list">
                <?php foreach ($categories as $category): ?>
                    <li>
                        <a class="pill" href="<?= e(url('/portfolio/' . (string) $category['slug'])) ?>">
                            <?= e((string) $category['name']) ?>
                            <span class="pill__count"><?= (int) $category['items_count'] ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
<?php endif; ?>

<?php if ($process !== []): ?>
    <section class="section">
        <div class="wrap">
            <header class="section__header">
                <p class="section__eyebrow">Déroulement</p>
                <h2 class="section__title">Comment nous travaillons</h2>
            </header>

            <ol class="steps">
                <?php foreach ($process as $index => $step): ?>
                    <li class="steps__item">
                        <span class="steps__number"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                        <h3 class="steps__title"><?= e((string) ($step['title'] ?? '')) ?></h3>
                        <p class="steps__text"><?= e((string) ($step['text'] ?? '')) ?></p>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </section>
<?php endif; ?>

<?php if ($services !== []): ?>
    <section class="section section--muted">
        <div class="wrap">
            <header class="section__header">
                <p class="section__eyebrow">Prestations</p>
                <h2 class="section__title">Formules</h2>
            </header>

            <div class="cards">
                <?php foreach (array_slice($services, 0, 3) as $service): ?>
                    <article class="card">
                        <h3 class="card__title"><?= e((string) $service['title']) ?></h3>
                        <?php if (($service['summary'] ?? '') !== ''): ?>
                            <p class="card__text"><?= e((string) $service['summary']) ?></p>
                        <?php endif; ?>
                        <?php if (($service['price_from'] ?? null) !== null): ?>
                            <p class="card__price">
                                À partir de <?= e(format_price($service['price_from'], (string) $service['currency'])) ?>
                            </p>
                        <?php endif; ?>
                        <a class="link-arrow" href="<?= e(url('/services/' . (string) $service['slug'])) ?>">
                            Détails
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($testimonials !== []): ?>
    <section class="section">
        <div class="wrap wrap--narrow">
            <header class="section__header">
                <p class="section__eyebrow">Témoignages</p>
            </header>

            <div class="quotes">
                <?php foreach ($testimonials as $testimonial): ?>
                    <blockquote class="quote">
                        <p class="quote__text"><?= e((string) ($testimonial['text'] ?? '')) ?></p>
                        <cite class="quote__author"><?= e((string) ($testimonial['author'] ?? '')) ?></cite>
                    </blockquote>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ((new \App\Services\MapService($settings))->showOnHome()): ?>
    <?= View::include('partials.map', ['settings' => $settings, 'headingLevel' => 'h2']) ?>
<?php endif; ?>

<section class="cta">
    <div class="wrap wrap--narrow">
        <h2 class="cta__title">Parlons de votre projet</h2>
        <p class="cta__text">Un mariage, un portrait, un événement : dites-moi ce que vous avez en tête.</p>
        <div class="cta__actions">
            <?php if (!empty($settings['booking_enabled'])): ?>
                <a class="button" href="<?= e(url('/reservation')) ?>">Réserver une séance</a>
            <?php endif; ?>
            <a class="button button--ghost" href="<?= e(url('/contact')) ?>">Me contacter</a>
        </div>
    </div>
</section>

<?php View::endSection(); ?>
