<?php
/**
 * Pagination d'une liste.
 * @var Paginator $pagination
 * @var string $chemin Chemin de la liste, par exemple « /requisitions »
 */
?>
<?php if ($pagination->total > 0): ?>
<nav class="pagination-bar" aria-label="Pagination">
    <p class="pagination-compte">
        <?= Format::nombre($pagination->premierIndex()) ?>–<?= Format::nombre($pagination->dernierIndex()) ?>
        sur <strong><?= Format::nombre($pagination->total) ?></strong>
    </p>

    <?php if ($pagination->pages > 1): ?>
        <ul class="pagination-pages">
            <li>
                <a class="pagination-lien<?= $pagination->page <= 1 ? ' is-disabled' : '' ?>"
                   href="<?= e($pagination->url($chemin, max(1, $pagination->page - 1))) ?>"
                   <?= $pagination->page <= 1 ? 'aria-disabled="true" tabindex="-1"' : 'rel="prev"' ?>>
                    <i class="bi bi-chevron-left" aria-hidden="true"></i><span class="visually-hidden">Page précédente</span>
                </a>
            </li>

            <?php if (!in_array(1, $pagination->fenetre(), true)): ?>
                <li><a class="pagination-lien" href="<?= e($pagination->url($chemin, 1)) ?>">1</a></li>
                <li><span class="pagination-ellipse">…</span></li>
            <?php endif; ?>

            <?php foreach ($pagination->fenetre() as $numero): ?>
                <li>
                    <a class="pagination-lien<?= $numero === $pagination->page ? ' is-active' : '' ?>"
                       href="<?= e($pagination->url($chemin, $numero)) ?>"
                       <?= $numero === $pagination->page ? 'aria-current="page"' : '' ?>><?= $numero ?></a>
                </li>
            <?php endforeach; ?>

            <?php if (!in_array($pagination->pages, $pagination->fenetre(), true)): ?>
                <li><span class="pagination-ellipse">…</span></li>
                <li><a class="pagination-lien" href="<?= e($pagination->url($chemin, $pagination->pages)) ?>"><?= $pagination->pages ?></a></li>
            <?php endif; ?>

            <li>
                <a class="pagination-lien<?= $pagination->page >= $pagination->pages ? ' is-disabled' : '' ?>"
                   href="<?= e($pagination->url($chemin, min($pagination->pages, $pagination->page + 1))) ?>"
                   <?= $pagination->page >= $pagination->pages ? 'aria-disabled="true" tabindex="-1"' : 'rel="next"' ?>>
                    <i class="bi bi-chevron-right" aria-hidden="true"></i><span class="visually-hidden">Page suivante</span>
                </a>
            </li>
        </ul>
    <?php endif; ?>
</nav>
<?php endif; ?>
