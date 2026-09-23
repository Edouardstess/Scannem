<?php
/**
 * The client gallery (VIEW link).
 *
 * @var array<string, mixed>             $gallery
 * @var \App\DTO\GalleryAccess           $access
 * @var string                           $rawToken
 * @var array<int, array<string, mixed>> $photos
 * @var array<string, mixed>             $pagination
 * @var bool                             $canDownload
 * @var bool                             $canSelect
 * @var array<int, int>                  $selectedIds
 * @var string                           $photosEndpoint
 * @var string                           $selectEndpoint
 */

use App\Core\View;

View::extend('layouts.client');
$title = (string) $gallery['title'];

View::startSection('header_actions');
?>
<div class="client-header__meta">
    <span class="client-header__count"><?= (int) $pagination['total'] ?> photos</span>
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

        <h1 class="gallery-head__title"><?= e((string) $gallery['title']) ?></h1>

        <p class="gallery-head__meta">
            <?php if (($gallery['event_title'] ?? '') !== ''): ?>
                <span><?= e((string) $gallery['event_title']) ?></span>
            <?php endif; ?>
            <?php if (($gallery['event_date'] ?? null) !== null): ?>
                <span><?= e(format_date_long((string) $gallery['event_date'])) ?></span>
            <?php endif; ?>
            <span><?= (int) $pagination['total'] ?> photographies</span>
        </p>

        <?php if (($gallery['description'] ?? '') !== ''): ?>
            <p class="gallery-head__text"><?= nl2br(e((string) $gallery['description'])) ?></p>
        <?php endif; ?>

        <?php if ($canSelect): ?>
            <p class="gallery-head__hint">
                Marquez vos photos préférées avec le cœur : votre photographe verra votre sélection.
                <span class="gallery-head__selection" data-selection-count><?= count($selectedIds) ?></span>
                sélectionnée(s).
            </p>
        <?php endif; ?>

        <?php if (!$canDownload): ?>
            <p class="gallery-head__notice">
                Cette galerie est en consultation. Pour recevoir les fichiers originaux,
                demandez le lien de téléchargement à votre photographe.
            </p>
        <?php endif; ?>
    </div>
</section>

<section class="gallery-body">
    <div class="wrap">
        <?php if ($photos === []): ?>
            <p class="empty">Les photographies seront ajoutées très prochainement.</p>
        <?php else: ?>
            <?= View::include('partials.photo_grid', [
                'photos'      => $photos,
                'canDownload' => $canDownload,
                'canSelect'   => $canSelect,
                'selectable'  => false,
                'selectedIds' => $selectedIds,
            ]) ?>

            <?php if (!empty($pagination['has_more'])): ?>
                <div class="gallery-more">
                    <button class="button button--ghost" type="button" data-load-more>
                        Charger plus de photos
                    </button>
                    <noscript>
                        <p class="form__note">Activez JavaScript pour voir l'ensemble de la galerie.</p>
                    </noscript>
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
<script>
window.GALLERY_CONFIG = <?= ejs([
    'photosEndpoint' => $photosEndpoint,
    'selectEndpoint' => $canSelect ? $selectEndpoint : null,
    'csrfToken'      => csrf_token(),
    'nextPage'       => ((int) $pagination['page']) + 1,
    'hasMore'        => (bool) ($pagination['has_more'] ?? false),
    'canDownload'    => $canDownload,
    'canSelect'      => $canSelect,
    'total'          => (int) $pagination['total'],
]) ?>;
</script>
<script src="<?= e(asset('js/gallery.js')) ?>" defer></script>
<?php
View::endSection();
