<?php
/**
 * @var array<int, array<string, mixed>> $items
 * @var array<int, array<string, mixed>> $categories
 * @var string                           $active
 * @var array<string, mixed>|null        $category
 */

use App\Core\View;

View::extend('layouts.public');
View::startSection('content');
?>

<section class="page-head">
    <div class="wrap">
        <p class="section__eyebrow">Portfolio</p>
        <h1 class="page-head__title">
            <?= e($category === null ? 'Travaux' : (string) $category['name']) ?>
        </h1>
        <?php if ($category !== null && ($category['description'] ?? '') !== ''): ?>
            <p class="page-head__text"><?= e((string) $category['description']) ?></p>
        <?php endif; ?>
    </div>
</section>

<?php if ($categories !== []): ?>
    <nav class="filters" aria-label="Catégories">
        <div class="wrap">
            <ul class="filters__list">
                <li>
                    <a class="filters__link<?= $active === '' ? ' is-active' : '' ?>"
                       href="<?= e(url('/portfolio')) ?>">Tout</a>
                </li>
                <?php foreach ($categories as $item): ?>
                    <li>
                        <a class="filters__link<?= $active === (string) $item['slug'] ? ' is-active' : '' ?>"
                           href="<?= e(url('/portfolio/' . (string) $item['slug'])) ?>">
                            <?= e((string) $item['name']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </nav>
<?php endif; ?>

<section class="section section--tight">
    <div class="wrap">
        <?php if ($items === []): ?>
            <p class="empty">Aucune photographie publiée pour le moment.</p>
        <?php else: ?>
            <div class="masonry" data-lightbox-group="portfolio">
                <?php foreach ($items as $index => $item): ?>
                    <?php
                    $thumb = (string) ($item['thumbnail_path'] ?? $item['image_path']);
                    $ratio = ((int) ($item['width'] ?? 0) > 0 && (int) ($item['height'] ?? 0) > 0)
                        ? (int) $item['width'] . ' / ' . (int) $item['height']
                        : '4 / 5';
                    $caption = trim((string) $item['title']
                        . (($item['category_name'] ?? '') !== '' ? ' — ' . (string) $item['category_name'] : ''));
                    ?>
                    <figure class="masonry__item" style="--ratio: <?= e($ratio) ?>">
                        <a href="<?= e(url((string) $item['image_path'])) ?>"
                           data-lightbox
                           data-caption="<?= e($caption) ?>">
                            <img src="<?= e(url($thumb)) ?>"
                                 alt="<?= e((string) $item['title']) ?>"
                                 loading="<?= $index < 6 ? 'eager' : 'lazy' ?>"
                                 decoding="async">
                        </a>
                        <figcaption class="masonry__caption"><?= e($caption) ?></figcaption>
                    </figure>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php View::endSection(); ?>
