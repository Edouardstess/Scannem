<?php
/**
 * Gallery workspace: uploader, photo grid, links, statistics.
 *
 * @var array<string, mixed>             $gallery
 * @var array<int, array<string, mixed>> $photos
 * @var array<string, mixed>             $pagination
 * @var array<string, mixed>             $links
 * @var array<string, mixed>             $stats
 * @var array<int, array<string, mixed>> $selections
 * @var array<int, array<string, mixed>> $auditTrail
 */

use App\Core\View;
use App\Models\AuditAction;
use App\Models\GalleryStatus;
use App\Repositories\GalleryRepository;

View::extend('layouts.admin');
View::startSection('content');

$galleryId = (int) $gallery['id'];
$expired = GalleryRepository::hasExpired($gallery);
$coverId = (int) ($gallery['cover_photo_id'] ?? 0);
?>

<div class="panel-actions">
    <a class="link-arrow" href="<?= e(url('/admin/galleries')) ?>">← Toutes les galeries</a>
    <div class="panel-actions__group">
        <a class="button button--ghost" href="<?= e(url('/admin/galleries/' . $galleryId . '/edit')) ?>">Modifier</a>
        <a class="button" href="<?= e(url('/admin/galleries/' . $galleryId . '/share')) ?>">Partager</a>
    </div>
</div>

<?php if ($expired): ?>
    <div class="callout callout--warn">
        Cette galerie a expiré le <?= e(format_datetime((string) $gallery['expires_at'])) ?>.
        Les clients voient « Cette galerie n'est plus disponible ».
    </div>
<?php elseif ((string) $gallery['status'] !== GalleryStatus::ACTIVE): ?>
    <div class="callout callout--warn">
        Cette galerie est <?= e(mb_strtolower(GalleryStatus::label((string) $gallery['status']))) ?> :
        les liens ne fonctionnent pas encore pour le client.
        <form method="post" action="<?= e(url('/admin/galleries/' . $galleryId . '/status')) ?>" class="callout__form">
            <?= csrf_field() ?>
            <input type="hidden" name="status" value="<?= e(GalleryStatus::ACTIVE) ?>">
            <button class="button button--small" type="submit">Activer la galerie</button>
        </form>
    </div>
<?php endif; ?>

<section class="tiles tiles--compact">
    <div class="tile"><span class="tile__value"><?= (int) $stats['photos'] ?></span><span class="tile__label">Photos</span></div>
    <div class="tile"><span class="tile__value"><?= (int) $stats['views'] ?></span><span class="tile__label">Consultations</span></div>
    <div class="tile"><span class="tile__value"><?= (int) $stats['downloads'] ?></span><span class="tile__label">Téléchargements</span></div>
    <div class="tile"><span class="tile__value"><?= e(format_bytes((int) $stats['total_bytes'])) ?></span><span class="tile__label">Poids total</span></div>
</section>

<?php
// The real ceiling: the host's upload_max_filesize / post_max_size can be far
// below the application setting (10 MB on free hosting).
$uploadLimit = \App\Core\Environment::uploadLimitBytes((int) config('storage.max_upload_bytes'));
?>
<section class="panel">
    <header class="panel__head">
        <h2 class="panel__title">Ajouter des photographies</h2>
        <span class="panel__hint">
            JPG, PNG, WEBP, TIFF, HEIC · <?= e(format_bytes($uploadLimit)) ?> max par fichier
        </span>
    </header>

    <div class="uploader" data-uploader
         data-max-bytes="<?= (int) $uploadLimit ?>"
         data-endpoint="<?= e(url('/admin/galleries/' . $galleryId . '/photos')) ?>"
         data-csrf="<?= e(csrf_token()) ?>">

        <div class="uploader__dropzone" data-dropzone tabindex="0" role="button"
             aria-label="Déposer des photos ou cliquer pour choisir des fichiers">
            <p class="uploader__title">Glissez vos photos ici</p>
            <p class="uploader__subtitle">ou cliquez pour choisir des fichiers</p>
            <input class="uploader__input" type="file" data-file-input multiple
                   accept="image/jpeg,image/png,image/webp,image/tiff,image/heic,image/heif">
        </div>

        <div class="uploader__summary" data-upload-summary hidden>
            <div class="uploader__progress">
                <div class="uploader__bar" data-upload-bar></div>
            </div>
            <p class="uploader__status" data-upload-status role="status" aria-live="polite"></p>
        </div>

        <ul class="uploader__queue" data-upload-queue></ul>
    </div>
</section>

<section class="panel">
    <header class="panel__head">
        <h2 class="panel__title">Photographies <span class="panel__count" data-photo-total><?= (int) $pagination['total'] ?></span></h2>
        <?php if ($photos !== []): ?>
            <span class="panel__hint">Glissez une vignette pour réorganiser.</span>
        <?php endif; ?>
    </header>

    <?php if ($photos === []): ?>
        <p class="empty">Aucune photo pour l'instant. Utilisez la zone ci-dessus.</p>
    <?php else: ?>
        <div class="admin-grid" data-sortable
             data-reorder-endpoint="<?= e(url('/admin/galleries/' . $galleryId . '/photos/reorder')) ?>"
             data-cover-endpoint="<?= e(url('/admin/galleries/' . $galleryId . '/cover')) ?>">
            <?php foreach ($photos as $photo): ?>
                <?php $photoId = (int) $photo['id']; ?>
                <figure class="admin-photo<?= $coverId === $photoId ? ' is-cover' : '' ?>"
                        data-photo-id="<?= (int) $photoId ?>" draggable="true">
                    <img class="admin-photo__image"
                         src="<?= e(url('/admin/photos/' . $photoId . '/thumb')) ?>"
                         alt="<?= e((string) $photo['original_filename']) ?>"
                         loading="lazy" decoding="async">

                    <figcaption class="admin-photo__meta">
                        <span class="admin-photo__name" title="<?= e((string) $photo['original_filename']) ?>">
                            <?= e(str_excerpt((string) $photo['original_filename'], 24)) ?>
                        </span>
                        <span class="admin-photo__size">
                            <?= (int) ($photo['width'] ?? 0) ?>×<?= (int) ($photo['height'] ?? 0) ?>
                            · <?= e(format_bytes((int) $photo['file_size'])) ?>
                        </span>
                    </figcaption>

                    <div class="admin-photo__actions">
                        <a class="admin-photo__action" target="_blank" rel="noopener"
                           href="<?= e(url('/admin/photos/' . $photoId . '/preview')) ?>"
                           title="Voir l'aperçu">Voir</a>

                        <form method="post" action="<?= e(url('/admin/galleries/' . $galleryId . '/cover')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="photo_id" value="<?= (int) $photoId ?>">
                            <button class="admin-photo__action" type="submit"
                                    title="Définir comme couverture">Couverture</button>
                        </form>

                        <form method="post" action="<?= e(url('/admin/photos/' . $photoId . '/downloadable')) ?>">
                            <?= csrf_field() ?>
                            <button class="admin-photo__action<?= (int) $photo['downloadable'] === 0 ? ' is-off' : '' ?>"
                                    type="submit"
                                    title="<?= (int) $photo['downloadable'] === 1
                                        ? 'Rendre non téléchargeable'
                                        : 'Rendre téléchargeable' ?>">
                                <?= (int) $photo['downloadable'] === 1 ? 'Téléch. ON' : 'Téléch. OFF' ?>
                            </button>
                        </form>

                        <form method="post" action="<?= e(url('/admin/photos/' . $photoId)) ?>"
                              data-confirm="Supprimer cette photo et ses fichiers ?">
                            <?= csrf_field() ?>
                            <?= method_field('DELETE') ?>
                            <button class="admin-photo__action admin-photo__action--danger" type="submit">
                                Supprimer
                            </button>
                        </form>
                    </div>
                </figure>
            <?php endforeach; ?>
        </div>

        <?= View::include('partials.pagination', [
            'pagination' => $pagination,
            'basePath'   => '/admin/galleries/' . $galleryId,
            'query'      => [],
        ]) ?>
    <?php endif; ?>
</section>

<div class="panel-grid">
    <section class="panel">
        <header class="panel__head"><h2 class="panel__title">Liens</h2></header>

        <?php foreach (['view' => 'Consultation', 'download' => 'Téléchargement'] as $key => $label): ?>
            <?php $link = $links[$key]; ?>
            <div class="link-row">
                <p class="link-row__label"><?= e($label) ?></p>
                <?php if ($link['url'] !== null): ?>
                    <code class="link-row__url"><?= e((string) $link['url']) ?></code>
                    <span class="link-row__meta">
                        <?= (int) $link['use_count'] ?> ouverture(s)
                        <?= $link['expires_at'] !== null
                            ? ' · expire le ' . e(format_datetime((string) $link['expires_at']))
                            : ' · sans expiration' ?>
                    </span>
                <?php elseif ($link['token'] !== null): ?>
                    <p class="link-row__warn">
                        Lien actif mais non réaffichable (APP_KEY absente ou modifiée).
                        Régénérez-le depuis l'écran de partage.
                    </p>
                <?php else: ?>
                    <p class="link-row__warn">Aucun lien actif. Régénérez-le depuis l'écran de partage.</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <p><a class="link-arrow" href="<?= e(url('/admin/galleries/' . $galleryId . '/share')) ?>">
            Copier et partager les liens
        </a></p>
    </section>

    <section class="panel">
        <header class="panel__head"><h2 class="panel__title">Informations</h2></header>
        <dl class="definition">
            <div><dt>Client</dt><dd>
                <a href="<?= e(url('/admin/clients/' . (int) $gallery['client_id'])) ?>">
                    <?= e(trim((string) $gallery['first_name'] . ' ' . (string) $gallery['last_name'])) ?>
                </a>
            </dd></div>
            <div><dt>Événement</dt><dd>
                <a href="<?= e(url('/admin/events/' . (int) $gallery['event_id'])) ?>">
                    <?= e((string) $gallery['event_title']) ?>
                </a>
            </dd></div>
            <div><dt>Mot de passe</dt><dd>
                <?= ($gallery['password_hash'] ?? null) !== null && $gallery['password_hash'] !== '' ? 'Oui' : 'Non' ?>
            </dd></div>
            <div><dt>Filigrane</dt><dd><?= (int) $gallery['watermark_enabled'] === 1 ? 'Activé' : 'Désactivé' ?></dd></div>
            <div><dt>Téléchargement</dt><dd><?= (int) $gallery['download_enabled'] === 1 ? 'Activé' : 'Désactivé' ?></dd></div>
            <div><dt>Dernière consultation</dt><dd><?= e(format_datetime($stats['last_viewed_at'] ?? null)) ?></dd></div>
            <div><dt>Dernier téléchargement</dt><dd><?= e(format_datetime($stats['last_downloaded_at'] ?? null)) ?></dd></div>
        </dl>

        <form method="post" action="<?= e(url('/admin/galleries/' . $galleryId . '/status')) ?>" class="inline-form">
            <?= csrf_field() ?>
            <label class="sr-only" for="status-change">Changer le statut</label>
            <select id="status-change" name="status">
                <?php foreach (GalleryStatus::ALL as $option): ?>
                    <option value="<?= e($option) ?>" <?= (string) $gallery['status'] === $option ? 'selected' : '' ?>>
                        <?= e(GalleryStatus::label($option)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="button button--small" type="submit">Appliquer</button>
        </form>
    </section>

    <?php if ((int) $gallery['selection_enabled'] === 1): ?>
        <section class="panel">
            <header class="panel__head">
                <h2 class="panel__title">Sélection du client <span class="panel__count"><?= count($selections) ?></span></h2>
            </header>

            <?php if ($selections === []): ?>
                <p class="empty">Le client n'a encore rien sélectionné.</p>
            <?php else: ?>
                <div class="admin-grid admin-grid--small">
                    <?php foreach ($selections as $photo): ?>
                        <figure class="admin-photo admin-photo--plain">
                            <img class="admin-photo__image"
                                 src="<?= e(url('/admin/photos/' . (int) $photo['id'] . '/thumb')) ?>"
                                 alt="<?= e((string) $photo['original_filename']) ?>" loading="lazy">
                            <figcaption class="admin-photo__meta">
                                <span class="admin-photo__name">
                                    <?= e(str_excerpt((string) $photo['original_filename'], 20)) ?>
                                </span>
                            </figcaption>
                        </figure>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="panel">
        <header class="panel__head"><h2 class="panel__title">Activité de la galerie</h2></header>

        <?php if ($auditTrail === []): ?>
            <p class="empty">Aucune activité enregistrée.</p>
        <?php else: ?>
            <ul class="list list--compact">
                <?php foreach ($auditTrail as $entry): ?>
                    <li class="list__item">
                        <span class="list__main">
                            <span class="list__title"><?= e(AuditAction::label((string) $entry['action'])) ?></span>
                            <span class="list__meta">
                                <?= e(format_datetime((string) $entry['created_at'])) ?>
                                <?= ($entry['user_name'] ?? null) !== null ? '· ' . e((string) $entry['user_name']) : '· client' ?>
                            </span>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<?php View::endSection(); ?>
