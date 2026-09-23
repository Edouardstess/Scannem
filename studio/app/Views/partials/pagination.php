<?php
/**
 * @var array<string, mixed> $pagination
 * @var string               $basePath   Path without query string.
 * @var array<string, mixed> $query      Query parameters to preserve.
 */

$pages = (int) $pagination['pages'];

if ($pages <= 1) {
    return;
}

$current = (int) $pagination['page'];
$query = $query ?? [];

$link = static function (int $page) use ($basePath, $query): string {
    $parameters = $query;
    $parameters['page'] = $page;

    return url(ltrim($basePath, '/') . '?' . http_build_query(array_filter(
        $parameters,
        static fn ($value): bool => $value !== '' && $value !== null
    )));
};

// A window around the current page: a 400-page gallery list must not render
// 400 links.
$window = 2;
$from = max(1, $current - $window);
$to = min($pages, $current + $window);
?>
<nav class="pagination" aria-label="Pagination">
    <p class="pagination__summary">
        <?= (int) $pagination['from'] ?>–<?= (int) $pagination['to'] ?> sur <?= (int) $pagination['total'] ?>
    </p>

    <ul class="pagination__list">
        <?php if ($current > 1): ?>
            <li><a class="pagination__link" href="<?= e($link($current - 1)) ?>" rel="prev">Précédent</a></li>
        <?php endif; ?>

        <?php if ($from > 1): ?>
            <li><a class="pagination__link" href="<?= e($link(1)) ?>">1</a></li>
            <?php if ($from > 2): ?><li class="pagination__gap">…</li><?php endif; ?>
        <?php endif; ?>

        <?php for ($page = $from; $page <= $to; $page++): ?>
            <li>
                <a class="pagination__link<?= $page === $current ? ' is-active' : '' ?>"
                   href="<?= e($link($page)) ?>"
                   <?= $page === $current ? 'aria-current="page"' : '' ?>><?= (int) $page ?></a>
            </li>
        <?php endfor; ?>

        <?php if ($to < $pages): ?>
            <?php if ($to < $pages - 1): ?><li class="pagination__gap">…</li><?php endif; ?>
            <li><a class="pagination__link" href="<?= e($link($pages)) ?>"><?= (int) $pages ?></a></li>
        <?php endif; ?>

        <?php if ($current < $pages): ?>
            <li><a class="pagination__link" href="<?= e($link($current + 1)) ?>" rel="next">Suivant</a></li>
        <?php endif; ?>
    </ul>
</nav>
