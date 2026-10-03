<?php
/**
 * Portfolio: the masonry of the homepage, three columns drifting at their
 * own speed, every photo from the database. Click opens the viewer.
 *
 * @var array<int, array<string, mixed>> $items
 * @var array<int, array<string, mixed>> $categories
 * @var string                           $active
 * @var array<string, mixed>|null        $category
 */

use App\Core\View;

View::extend('layouts.public');
View::startSection('content');

$total = array_sum(array_map(static fn (array $c): int => (int) $c['items_count'], $categories));
$years = array_filter(array_map(static fn (array $i): int => (int) substr((string) ($i['created_at'] ?? ''), 0, 4), $items));
$span = $years === [] ? '' : (min($years) === max($years) ? (string) max($years) : min($years) . ' → ' . max($years));

// Round-robin into three columns: reading order stays left to right, and
// data-index keeps the viewer in that order whatever the column.
$columns = [[], [], []];
foreach ($items as $index => $item) {
    $columns[$index % 3][] = ['index' => $index, 'item' => $item];
}
$speeds = ['-0.05', '0.07', '-0.025'];
?>

<section class="ph">
    <p class="ph-kicker mono" data-reveal>
        <span>Portfolio</span>
        <?php if ($category !== null): ?><span class="ash crumb"><a href="<?= e(url('/portfolio')) ?>">Tout voir</a></span><?php endif; ?>
    </p>
    <h1 class="ph-title split"><?= View::include('partials.split', ['text' => $category === null ? 'Travaux' : (string) $category['name']]) ?></h1>
    <p class="ph-lead" data-reveal style="--i: 3">
        <?= e(first_filled($category['description'] ?? '', 'Des images vraies, faites pour durer : mariages, portraits, familles et événements.')) ?>
    </p>
    <dl class="ph-meta mono" data-reveal style="--i: 4">
        <div><dt>Photographies</dt><dd><?= e(sprintf('%02d', count($items))) ?><?= $category !== null ? ' / ' . (int) $total : '' ?></dd></div>
        <div><dt>Catégories</dt><dd><?= e(sprintf('%02d', count($categories))) ?></dd></div>
        <?php if ($span !== ''): ?><div><dt>Période</dt><dd><?= e($span) ?></dd></div><?php endif; ?>
        <div><dt>Voir</dt><dd>Cliquez une image</dd></div>
    </dl>
</section>

<?php if ($categories !== []): ?>
    <nav class="cats mono" aria-label="Catégories" data-reveal>
        <a class="cat" href="<?= e(url('/portfolio')) ?>"<?= $active === '' ? ' aria-current="page"' : '' ?>>Tout <sup><?= (int) $total ?></sup></a>
        <?php foreach ($categories as $item): ?>
            <a class="cat" href="<?= e(url('/portfolio/' . (string) $item['slug'])) ?>"<?= $active === (string) $item['slug'] ? ' aria-current="page"' : '' ?>>
                <?= e((string) $item['name']) ?> <sup><?= (int) $item['items_count'] ?></sup>
            </a>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>

<section class="sec">
    <?php if ($items === []): ?>
        <p class="empty">Les premières images arrivent bientôt.</p>
    <?php else: ?>
        <div class="works">
            <?php foreach ($columns as $c => $column): ?>
                <div class="works-col" data-speed="<?= e($speeds[$c]) ?>">
                    <?php foreach ($column as $entry): ?>
                        <?php
                        $item = $entry['item'];
                        $thumb = first_filled($item['thumbnail_path'] ?? '', $item['image_path']);
                        $ratio = ((int) ($item['width'] ?? 0) > 0 && (int) ($item['height'] ?? 0) > 0)
                            ? (int) $item['width'] . ' / ' . (int) $item['height']
                            : '4 / 5';
                        $year = (int) substr((string) ($item['created_at'] ?? ''), 0, 4);
                        $meta = mb_strtoupper(first_filled($item['category_name'] ?? '', 'Photographie')) . ($year > 0 ? ' — ' . $year : '');
                        $caption = trim((string) $item['title'] . ' — ' . $meta);
                        ?>
                        <figure class="work" style="--ratio: <?= e($ratio) ?>">
                            <a href="<?= e(url((string) $item['image_path'])) ?>" data-lightbox data-index="<?= (int) $entry['index'] ?>" data-caption="<?= e($caption) ?>">
                                <div class="clip" data-clip>
                                    <img src="<?= e(url($thumb)) ?>" alt="<?= e((string) $item['title']) ?>"
                                         loading="<?= $entry['index'] < 6 ? 'eager' : 'lazy' ?>" decoding="async">
                                </div>
                            </a>
                            <figcaption>
                                <span><?= e((string) $item['title']) ?></span>
                                <span class="mono"><?= e($meta) ?></span>
                            </figcaption>
                        </figure>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?= View::include('partials.cta_band', ['settings' => $settings, 'kicker' => 'CE QUE VOUS VOYEZ ICI, VOUS POUVEZ L’AVOIR', 'title' => 'Vos images sont les prochaines.']) ?>

<?php View::endSection(); ?>
