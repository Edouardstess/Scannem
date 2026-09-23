<?php
/**
 * Lightbox shell.
 *
 * Markup lives here rather than being generated in JavaScript so that the
 * dialog's ARIA wiring is visible and reviewable in one place.
 */
?>
<div class="lightbox" data-lightbox-root hidden role="dialog" aria-modal="true" aria-label="Photo en grand">
    <div class="lightbox__bar">
        <span class="lightbox__counter" data-lightbox-counter>1 / 1</span>

        <div class="lightbox__tools">
            <button type="button" class="lightbox__tool" data-lightbox-zoom aria-pressed="false">
                <span class="sr-only">Zoom</span><span aria-hidden="true">&#9906;</span>
            </button>
            <button type="button" class="lightbox__tool" data-lightbox-fullscreen>
                <span class="sr-only">Plein écran</span><span aria-hidden="true">&#9974;</span>
            </button>
            <a class="lightbox__tool lightbox__tool--download" data-lightbox-download hidden download>
                <span class="sr-only">Télécharger</span><span aria-hidden="true">&#8595;</span>
            </a>
            <button type="button" class="lightbox__tool" data-lightbox-close>
                <span class="sr-only">Fermer</span><span aria-hidden="true">&times;</span>
            </button>
        </div>
    </div>

    <button type="button" class="lightbox__nav lightbox__nav--prev" data-lightbox-prev>
        <span class="sr-only">Photo précédente</span><span aria-hidden="true">&#8249;</span>
    </button>

    <div class="lightbox__stage" data-lightbox-stage>
        <img class="lightbox__image" data-lightbox-image alt="" decoding="async">
        <div class="lightbox__spinner" data-lightbox-spinner hidden><span class="sr-only">Chargement…</span></div>
    </div>

    <button type="button" class="lightbox__nav lightbox__nav--next" data-lightbox-next>
        <span class="sr-only">Photo suivante</span><span aria-hidden="true">&#8250;</span>
    </button>

    <p class="lightbox__caption" data-lightbox-caption></p>
</div>
