<?php
/**
 * The download area (DOWNLOAD link).
 *
 * @var array<string, mixed>             $gallery
 * @var \App\DTO\GalleryAccess           $access
 * @var string                           $rawToken
 * @var array<int, array<string, mixed>> $photos
 * @var array<string, mixed>             $pagination
 * @var int                              $downloadableCount
 * @var int                              $totalBytes
 * @var bool                             $zipAvailable
 * @var string                           $photosEndpoint
 * @var string                           $archiveEndpoint
 */

use App\Core\View;

View::extend('layouts.client');
$title = 'Vos photos — ' . (string) $gallery['title'];

View::startSection('header_actions');
?>
<div class="client-header__meta">
    <span class="client-header__count"><?= (int) $downloadableCount ?> photos disponibles</span>
</div>
<?php
View::endSection();

View::startSection('content');

$clientName = trim((string) $gallery['first_name'] . ' ' . (string) $gallery['last_name']);
?>

<section class="gallery-head">
    <div class="wrap">
        <?php if ($clientName !== ''): ?>
            <p class="gallery-head__client"><?= e(mb_strtoupper($clientName)) ?></p>
        <?php endif; ?>

        <h1 class="gallery-head__title">Vos photos sont disponibles</h1>

        <p class="gallery-head__meta">
            <span><?= e((string) $gallery['title']) ?></span>
            <?php if (($gallery['event_date'] ?? null) !== null): ?>
                <span><?= e(format_date_long((string) $gallery['event_date'])) ?></span>
            <?php endif; ?>
            <span><?= (int) $downloadableCount ?> fichiers · <?= e(format_bytes($totalBytes)) ?></span>
        </p>

        <div class="download-actions" data-download-actions>
            <?php if ($zipAvailable): ?>
                <button class="button" type="button" data-download-all>
                    Télécharger toute la galerie
                </button>
                <button class="button button--ghost" type="button" data-selection-mode>
                    Sélectionner des photos
                </button>
            <?php else: ?>
                <p class="gallery-head__notice">
                    Le téléchargement groupé est indisponible sur ce serveur.
                    Les photos restent téléchargeables une par une.
                </p>
            <?php endif; ?>
        </div>

        <div class="download-progress" data-download-progress hidden role="status" aria-live="polite">
            <p class="download-progress__label" data-progress-label>Préparation de votre archive…</p>
            <div class="download-progress__track">
                <div class="download-progress__bar" data-progress-bar></div>
            </div>
        </div>
    </div>
</section>

<section class="gallery-body">
    <div class="wrap">
        <?php /* Inside the grid's section so position: sticky follows the photos. */ ?>
        <div class="download-selection" data-selection-bar hidden>
            <span><strong data-selected-count>0</strong> photo(s) sélectionnée(s)</span>
            <div class="download-selection__actions">
                <button class="button button--small" type="button" data-download-selection>
                    Télécharger la sélection
                </button>
                <button class="button button--small button--ghost" type="button" data-select-all>
                    Tout sélectionner
                </button>
                <button class="button button--small button--ghost" type="button" data-clear-selection>
                    Annuler
                </button>
            </div>
        </div>

        <?php if ($photos === []): ?>
            <p class="empty">Aucune photographie dans cette galerie pour le moment.</p>
        <?php else: ?>
            <?= View::include('partials.photo_grid', [
                'photos'      => $photos,
                'canDownload' => true,
                'canSelect'   => false,
                'selectable'  => $zipAvailable,
                'selectedIds' => [],
            ]) ?>

            <?php if (!empty($pagination['has_more'])): ?>
                <div class="gallery-more">
                    <button class="button button--ghost" type="button" data-load-more>
                        Charger plus de photos
                    </button>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?= View::include('partials.lightbox') ?>

<?php
View::endSection();

View::startSection('scripts');
?>
<?php /* A JSON data block is never executed, so the CSP (script-src 'self') allows it. */ ?>
<script type="application/json" id="gallery-config"><?= ejs([
    'photosEndpoint'  => $photosEndpoint,
    'archiveEndpoint' => $zipAvailable ? $archiveEndpoint : null,
    'selectEndpoint'  => null,
    'csrfToken'       => csrf_token(),
    'nextPage'        => ((int) $pagination['page']) + 1,
    'hasMore'         => (bool) ($pagination['has_more'] ?? false),
    'canDownload'     => true,
    'canSelect'       => false,
    'selectable'      => $zipAvailable,
    'total'           => (int) $pagination['total'],
    'downloadable'    => (int) $downloadableCount,
]) ?></script>
<script src="<?= e(asset('js/gallery.js')) ?>" defer></script>
<?php
View::endSection();
