<?php
/**
 * @var array<string, mixed> $gallery
 * @var array<string, mixed> $links
 */

use App\Core\View;
use App\Models\GalleryStatus;
use App\Models\TokenType;
use App\Repositories\GalleryRepository;

View::extend('layouts.admin');
View::startSection('content');

$galleryId = (int) $gallery['id'];
$clientEmail = trim((string) ($gallery['client_email'] ?? ''));
$reachable = GalleryRepository::isReachable($gallery);

$shareTargets = static function (string $url, string $title): array {
    $text = rawurlencode($title . ' — vos photos : ' . $url);

    return [
        'WhatsApp' => 'https://wa.me/?text=' . $text,
        'E-mail'   => 'mailto:?subject=' . rawurlencode($title) . '&body=' . $text,
        'Facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($url),
    ];
};
?>

<div class="panel-actions">
    <a class="link-arrow" href="<?= e(url('/admin/galleries/' . $galleryId)) ?>">← Retour à la galerie</a>
</div>

<?php if (!$reachable): ?>
    <div class="callout callout--warn">
        Cette galerie n'est pas consultable actuellement
        (<?= e(mb_strtolower(GalleryStatus::label((string) $gallery['status']))) ?><?= GalleryRepository::hasExpired($gallery) ? ', expirée' : '' ?>).
        Les liens ci-dessous ne fonctionneront pas tant qu'elle n'est pas active.
    </div>
<?php endif; ?>

<?php foreach ([
    'view'     => ['label' => 'Lien de consultation', 'type' => TokenType::VIEW],
    'download' => ['label' => 'Lien de téléchargement', 'type' => TokenType::DOWNLOAD],
] as $key => $meta): ?>
    <?php $link = $links[$key]; ?>

    <section class="panel share-card">
        <header class="panel__head">
            <h2 class="panel__title"><?= e($meta['label']) ?></h2>
            <?php if ($link['token'] !== null): ?>
                <span class="panel__hint">
                    <?= (int) $link['use_count'] ?> ouverture(s)
                    <?= $link['last_used_at'] !== null
                        ? ' · dernière le ' . e(format_datetime((string) $link['last_used_at']))
                        : '' ?>
                </span>
            <?php endif; ?>
        </header>

        <?php if ($key === 'view'): ?>
            <p class="panel__text">
                Consultation seule. Le client voit les photos en aperçu et ne peut
                récupérer aucun fichier original avec ce lien.
            </p>
        <?php else: ?>
            <p class="panel__text">
                Téléchargement des fichiers originaux.
                <?php if ((int) $gallery['download_enabled'] !== 1): ?>
                    <strong>Le téléchargement est actuellement désactivé</strong> sur cette galerie :
                    ce lien ouvre la page mais ne délivre aucun fichier.
                    <a href="<?= e(url('/admin/galleries/' . $galleryId . '/edit')) ?>">Activer</a>.
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <?php if ($link['url'] !== null): ?>
            <div class="copy-row">
                <label class="sr-only" for="link-<?= e($key) ?>"><?= e($meta['label']) ?></label>
                <input class="copy-row__input" id="link-<?= e($key) ?>" type="text" readonly
                       value="<?= e((string) $link['url']) ?>">
                <button class="button button--small" type="button"
                        data-copy="#link-<?= e($key) ?>">Copier</button>
                <a class="button button--small button--ghost" target="_blank" rel="noopener"
                   href="<?= e((string) $link['url']) ?>">Ouvrir</a>
            </div>

            <p class="share-card__expiry">
                <?= $link['expires_at'] !== null
                    ? 'Expire le ' . e(format_datetime((string) $link['expires_at']))
                    : 'Sans expiration' ?>
                <a href="<?= e(url('/admin/galleries/' . $galleryId . '/edit')) ?>">Modifier</a>
            </p>

            <div class="share-card__targets">
                <span>Partager via</span>
                <?php foreach ($shareTargets((string) $link['url'], (string) $gallery['title']) as $name => $href): ?>
                    <a class="share-card__target" href="<?= e($href) ?>" target="_blank" rel="noopener noreferrer">
                        <?= e($name) ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if ($clientEmail !== ''): ?>
                <form method="post" action="<?= e(url('/admin/galleries/' . $galleryId . '/notify')) ?>"
                      class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="kind" value="<?= e($key) ?>">
                    <button class="button button--small button--ghost" type="submit">
                        Envoyer à <?= e($clientEmail) ?>
                    </button>
                </form>
            <?php endif; ?>

        <?php elseif ($link['token'] !== null): ?>
            <div class="callout callout--warn">
                Ce lien est actif mais ne peut pas être réaffiché : APP_KEY est absente ou a changé
                depuis sa création. Régénérez-le ci-dessous — le lien précédent cessera de fonctionner.
            </div>
        <?php else: ?>
            <div class="callout callout--warn">
                Aucun lien de <?= e(mb_strtolower(TokenType::label($meta['type']))) ?> actif.
                Générez-en un ci-dessous.
            </div>
        <?php endif; ?>

        <div class="share-card__actions">
            <form method="post"
                  action="<?= e(url('/admin/galleries/' . $galleryId . '/tokens/' . strtolower($meta['type']) . '/regenerate')) ?>"
                  data-confirm="Générer un nouveau lien ? L'ancien cessera immédiatement de fonctionner.">
                <?= csrf_field() ?>
                <button class="button button--small button--ghost" type="submit">
                    <?= $link['token'] === null ? 'Générer un lien' : 'Régénérer' ?>
                </button>
            </form>

            <?php if ($link['token'] !== null): ?>
                <form method="post"
                      action="<?= e(url('/admin/galleries/' . $galleryId . '/tokens/' . strtolower($meta['type']) . '/revoke')) ?>"
                      data-confirm="Désactiver ce lien ? Le client n'y aura plus accès.">
                    <?= csrf_field() ?>
                    <button class="button button--small button--danger-ghost" type="submit">Désactiver</button>
                </form>
            <?php endif; ?>
        </div>
    </section>
<?php endforeach; ?>

<section class="panel">
    <header class="panel__head"><h2 class="panel__title">Couper tous les accès</h2></header>
    <p class="panel__text">
        Désactive la galerie et révoque les deux liens en une fois. À utiliser si un lien
        a été transmis à la mauvaise personne.
    </p>
    <form method="post" action="<?= e(url('/admin/galleries/' . $galleryId . '/status')) ?>"
          data-confirm="Désactiver la galerie et révoquer les deux liens ?">
        <?= csrf_field() ?>
        <input type="hidden" name="status" value="<?= e(GalleryStatus::DISABLED) ?>">
        <button class="button button--danger" type="submit">Désactiver immédiatement</button>
    </form>
</section>

<?php View::endSection(); ?>
