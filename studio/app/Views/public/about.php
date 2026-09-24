<?php
/**
 * @var array<string, mixed>             $settings
 * @var array<int, array<string, mixed>> $services
 */

use App\Core\View;

View::extend('layouts.public');
View::startSection('content');

$image = (string) ($settings['about_image'] ?? '');
$text = (string) ($settings['about_text'] ?? '');
?>

<section class="page-head">
    <div class="wrap">
        <p class="section__eyebrow">À propos</p>
        <h1 class="page-head__title">
            <?= e(first_filled($settings['about_title'] ?? '', 'À propos')) ?>
        </h1>
    </div>
</section>

<section class="section">
    <div class="wrap">
        <div class="about">
            <?php if ($image !== ''): ?>
                <div class="about__media">
                    <img src="<?= e(url($image)) ?>"
                         alt="<?= e((string) ($settings['photographer_name'] ?? '')) ?>" decoding="async">
                </div>
            <?php endif; ?>

            <div class="about__body prose">
                <?php if ($text !== ''): ?>
                    <?= nl2br(e($text)) ?>
                <?php else: ?>
                    <?php /* No "coming soon" placeholder in public: the tagline stands in until the text is written. */ ?>
                    <p class="prose--lead"><?= e(first_filled($settings['tagline'] ?? '', $settings['hero_subtitle'] ?? '')) ?></p>
                <?php endif; ?>

                <p class="about__actions">
                    <a class="button" href="<?= e(url('/portfolio')) ?>">Voir le portfolio</a>
                    <a class="button button--ghost" href="<?= e(url('/contact')) ?>">Me contacter</a>
                </p>

                <?php if (($settings['speciality'] ?? '') !== ''): ?>
                    <p class="about__speciality"><?= e((string) $settings['speciality']) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php if ($services !== []): ?>
    <section class="section section--muted">
        <div class="wrap">
            <header class="section__header"><h2 class="section__title">Travailler ensemble</h2></header>
            <div class="cards">
                <?php foreach (array_slice($services, 0, 3) as $service): ?>
                    <article class="card">
                        <h3 class="card__title"><?= e((string) $service['title']) ?></h3>
                        <?php if (($service['summary'] ?? '') !== ''): ?>
                            <p class="card__text"><?= e(str_excerpt((string) $service['summary'], 110)) ?></p>
                        <?php endif; ?>
                        <a class="link-arrow" href="<?= e(url('/services/' . (string) $service['slug'])) ?>">Détails</a>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php View::endSection(); ?>
