<?php
/**
 * The photo grid, shared by the view gallery and the download page.
 *
 * Rendered server-side for the first page so the gallery is visible without
 * JavaScript and paints fast; further pages are appended by gallery.js.
 *
 * @var array<int, array<string, mixed>> $photos
 * @var bool                             $canDownload
 * @var bool                             $canSelect
 * @var bool                             $selectable   Checkboxes for ZIP selection.
 * @var array<int, int>                  $selectedIds
 */

$selectable = $selectable ?? false;
$selectedIds = $selectedIds ?? [];
?>
<div class="photo-grid" data-photo-grid>
    <?php foreach ($photos as $index => $photo): ?>
        <?php
        $width = (int) ($photo['width'] ?? 0);
        $height = (int) ($photo['height'] ?? 0);
        $ratio = ($width > 0 && $height > 0) ? $width . ' / ' . $height : '3 / 2';
        $isSelected = in_array((int) $photo['id'], $selectedIds, true);
        ?>
        <figure class="photo<?= $isSelected ? ' is-favourite' : '' ?>"
                style="--ratio: <?= e($ratio) ?>"
                data-photo-id="<?= (int) $photo['id'] ?>"
                data-index="<?= (int) $index ?>"
                data-preview="<?= e((string) $photo['preview_url']) ?>"
                data-caption="<?= e((string) $photo['name']) ?>"
                <?= $photo['download_url'] !== null ? 'data-download="' . e((string) $photo['download_url']) . '"' : '' ?>>

            <button type="button" class="photo__button" data-open-lightbox
                    aria-label="Agrandir <?= e((string) $photo['name']) ?>">
                <img class="photo__image"
                     src="<?= e((string) $photo['thumb_url']) ?>"
                     alt="<?= e((string) $photo['name']) ?>"
                     width="<?= (int) ($width > 0 ? $width : 1200) ?>"
                     height="<?= (int) ($height > 0 ? $height : 800) ?>"
                     loading="<?= $index < 8 ? 'eager' : 'lazy' ?>"
                     decoding="async">
            </button>

            <?php if ($selectable): ?>
                <label class="photo__check">
                    <input type="checkbox" class="photo__checkbox" data-select-photo
                           value="<?= (int) $photo['id'] ?>"
                           <?= $photo['download_url'] === null ? 'disabled' : '' ?>>
                    <span class="sr-only">Sélectionner <?= e((string) $photo['name']) ?></span>
                    <span class="photo__check-box" aria-hidden="true"></span>
                </label>
            <?php endif; ?>

            <?php if ($canSelect): ?>
                <button type="button" class="photo__favourite" data-favourite
                        aria-pressed="<?= $isSelected ? 'true' : 'false' ?>"
                        aria-label="Ajouter <?= e((string) $photo['name']) ?> à ma sélection">
                    <span aria-hidden="true">&#9825;</span>
                </button>
            <?php endif; ?>

            <?php if ($canDownload && $photo['download_url'] !== null): ?>
                <a class="photo__download" href="<?= e((string) $photo['download_url']) ?>"
                   download aria-label="Télécharger <?= e((string) $photo['name']) ?>">
                    <span aria-hidden="true">&#8595;</span>
                </a>
            <?php endif; ?>
        </figure>
    <?php endforeach; ?>
</div>
