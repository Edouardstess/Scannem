<?php
/**
 * @var array<string, mixed>             $service
 * @var array<int, array<string, mixed>> $others
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
?>

<section class="page-head">
    <div class="wrap wrap--narrow">
        <p class="section__eyebrow">
            <a href="<?= e(url('/services')) ?>">Prestations</a>
        </p>
        <h1 class="page-head__title"><?= e((string) $service['title']) ?></h1>
        <?php if (($service['summary'] ?? '') !== ''): ?>
            <p class="page-head__text"><?= e((string) $service['summary']) ?></p>
        <?php endif; ?>
    </div>
</section>

<?php if (($service['image_path'] ?? '') !== ''): ?>
    <div class="feature-image">
        <img src="<?= e(url((string) $service['image_path'])) ?>"
             alt="<?= e((string) $service['title']) ?>" decoding="async">
    </div>
<?php endif; ?>

<section class="section">
    <div class="wrap wrap--narrow">
        <div class="detail-grid">
            <div class="prose">
                <?php if (($service['description'] ?? '') !== ''): ?>
                    <?= nl2br(e((string) $service['description'])) ?>
                <?php endif; ?>

                <?php if ($deliverables !== []): ?>
                    <h2>Ce qui est inclus</h2>
                    <ul class="checklist">
                        <?php foreach ($deliverables as $line): ?>
                            <li><?= e($line) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <aside class="detail-aside">
                <?php if (($service['price_from'] ?? null) !== null): ?>
                    <p class="detail-aside__price">
                        à partir de
                        <strong><?= e(format_price($service['price_from'], (string) $service['currency'])) ?></strong>
                    </p>
                <?php endif; ?>
                <?php if (($service['duration'] ?? '') !== ''): ?>
                    <p class="detail-aside__row"><span>Durée</span> <?= e((string) $service['duration']) ?></p>
                <?php endif; ?>
                <a class="button button--block"
                   href="<?= e(url('/reservation?service=' . (int) $service['id'])) ?>">Réserver</a>
                <a class="button button--ghost button--block" href="<?= e(url('/contact')) ?>">Poser une question</a>
            </aside>
        </div>
    </div>
</section>

<?php if ($others !== []): ?>
    <section class="section section--muted">
        <div class="wrap">
            <header class="section__header"><h2 class="section__title">Autres prestations</h2></header>
            <div class="cards">
                <?php foreach (array_slice($others, 0, 3) as $other): ?>
                    <article class="card">
                        <h3 class="card__title"><?= e((string) $other['title']) ?></h3>
                        <?php if (($other['summary'] ?? '') !== ''): ?>
                            <p class="card__text"><?= e(str_excerpt((string) $other['summary'], 110)) ?></p>
                        <?php endif; ?>
                        <a class="link-arrow" href="<?= e(url('/services/' . (string) $other['slug'])) ?>">Détails</a>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php View::endSection(); ?>
