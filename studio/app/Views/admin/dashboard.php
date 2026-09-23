<?php
/**
 * @var array<string, int>               $stats
 * @var array<string, mixed>             $activity
 * @var array<int, array<string, mixed>> $recent
 * @var array<int, array<string, mixed>> $popular
 * @var array<int, array<string, mixed>> $upcoming
 * @var array<int, array<string, mixed>> $auditTrail
 */

use App\Core\View;
use App\Models\AuditAction;
use App\Models\GalleryStatus;

View::extend('layouts.admin');
View::startSection('content');

$tiles = [
    ['label' => 'Clients',          'value' => $stats['clients'],   'href' => '/admin/clients'],
    ['label' => 'Événements',       'value' => $stats['events'],    'href' => '/admin/events'],
    ['label' => 'Galeries',         'value' => $stats['galleries'], 'href' => '/admin/galleries'],
    ['label' => 'Photos',           'value' => $stats['photos'],    'href' => '/admin/galleries'],
    ['label' => 'Consultations',    'value' => $stats['views'],     'href' => '/admin/statistics'],
    ['label' => 'Téléchargements',  'value' => $stats['downloads'], 'href' => '/admin/statistics'],
];
?>

<div class="panel-actions">
    <a class="button" href="<?= e(url('/admin/galleries/create')) ?>">Nouvelle galerie</a>
    <a class="button button--ghost" href="<?= e(url('/admin/clients/create')) ?>">Nouveau client</a>
</div>

<?php if ($stats['unread_messages'] > 0 || $stats['pending_bookings'] > 0): ?>
    <div class="callout">
        <?php if ($stats['unread_messages'] > 0): ?>
            <a href="<?= e(url('/admin/messages')) ?>">
                <?= (int) $stats['unread_messages'] ?> message(s) non lu(s)
            </a>
        <?php endif; ?>
        <?php if ($stats['pending_bookings'] > 0): ?>
            <a href="<?= e(url('/admin/bookings')) ?>">
                <?= (int) $stats['pending_bookings'] ?> demande(s) de réservation en attente
            </a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<section class="tiles">
    <?php foreach ($tiles as $tile): ?>
        <a class="tile" href="<?= e(url($tile['href'])) ?>">
            <span class="tile__value"><?= number_format((int) $tile['value'], 0, ',', ' ') ?></span>
            <span class="tile__label"><?= e($tile['label']) ?></span>
        </a>
    <?php endforeach; ?>
</section>

<section class="panel">
    <header class="panel__head">
        <h2 class="panel__title">Activité des 30 derniers jours</h2>
        <a class="link-arrow" href="<?= e(url('/admin/statistics')) ?>">Détail</a>
    </header>

    <?= View::include('partials.chart', [
        'id'     => 'dashboard-activity',
        'labels' => $activity['labels'],
        'series' => [
            ['label' => 'Consultations',   'values' => $activity['views'],     'variant' => 'primary'],
            ['label' => 'Téléchargements', 'values' => $activity['downloads'], 'variant' => 'accent'],
        ],
    ]) ?>
</section>

<div class="panel-grid">
    <section class="panel">
        <header class="panel__head"><h2 class="panel__title">Galeries récentes</h2></header>

        <?php if ($recent === []): ?>
            <p class="empty">Aucune galerie pour l'instant.</p>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($recent as $gallery): ?>
                    <li class="list__item">
                        <a class="list__main" href="<?= e(url('/admin/galleries/' . (int) $gallery['id'])) ?>">
                            <span class="list__title"><?= e((string) $gallery['title']) ?></span>
                            <span class="list__meta">
                                <?= e(trim((string) $gallery['first_name'] . ' ' . (string) $gallery['last_name'])) ?>
                                · <?= (int) $gallery['photos_count'] ?> photos
                            </span>
                        </a>
                        <span class="badge badge--<?= e((string) $gallery['status']) ?>">
                            <?= e(GalleryStatus::label((string) $gallery['status'])) ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="panel">
        <header class="panel__head"><h2 class="panel__title">Galeries les plus consultées</h2></header>

        <?php if ($popular === []): ?>
            <p class="empty">Pas encore de consultation.</p>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($popular as $gallery): ?>
                    <li class="list__item">
                        <a class="list__main" href="<?= e(url('/admin/galleries/' . (int) $gallery['id'])) ?>">
                            <span class="list__title"><?= e((string) $gallery['title']) ?></span>
                            <span class="list__meta">
                                <?= (int) $gallery['views_count'] ?> vues ·
                                <?= (int) $gallery['downloads_count'] ?> téléchargements
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="panel">
        <header class="panel__head"><h2 class="panel__title">Événements à venir</h2></header>

        <?php if ($upcoming === []): ?>
            <p class="empty">Aucun événement planifié.</p>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($upcoming as $event): ?>
                    <li class="list__item">
                        <a class="list__main" href="<?= e(url('/admin/events/' . (int) $event['id'])) ?>">
                            <span class="list__title"><?= e((string) $event['title']) ?></span>
                            <span class="list__meta">
                                <?= e(format_date((string) $event['event_date'])) ?>
                                · <?= e(trim((string) $event['first_name'] . ' ' . (string) $event['last_name'])) ?>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="panel">
        <header class="panel__head">
            <h2 class="panel__title">Dernières activités</h2>
            <a class="link-arrow" href="<?= e(url('/admin/statistics/audit')) ?>">Journal complet</a>
        </header>

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
                                <?= ($entry['user_name'] ?? null) !== null ? '· ' . e((string) $entry['user_name']) : '' ?>
                                <?= ($entry['gallery_title'] ?? null) !== null ? '· ' . e((string) $entry['gallery_title']) : '' ?>
                            </span>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<?php View::endSection(); ?>
