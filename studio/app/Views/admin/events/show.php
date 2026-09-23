<?php
/**
 * @var array<string, mixed>             $event
 * @var array<int, array<string, mixed>> $galleries
 */

use App\Core\View;
use App\Models\EventStatus;
use App\Models\GalleryStatus;
use App\Services\EventService;

View::extend('layouts.admin');
View::startSection('content');
?>

<div class="panel-actions">
    <a class="link-arrow" href="<?= e(url('/admin/events')) ?>">← Tous les événements</a>
    <div class="panel-actions__group">
        <a class="button button--ghost" href="<?= e(url('/admin/events/' . (int) $event['id'] . '/edit')) ?>">
            Modifier
        </a>
        <a class="button" href="<?= e(url('/admin/galleries/create?event_id=' . (int) $event['id'])) ?>">
            Nouvelle galerie
        </a>
    </div>
</div>

<section class="panel">
    <dl class="definition">
        <div><dt>Client</dt><dd>
            <a href="<?= e(url('/admin/clients/' . (int) $event['client_id'])) ?>">
                <?= e(trim((string) $event['first_name'] . ' ' . (string) $event['last_name'])) ?>
            </a>
        </dd></div>
        <div><dt>Type</dt><dd><?= e(EventService::typeLabel($event['event_type'] ?? null)) ?></dd></div>
        <div><dt>Date</dt><dd><?= e(format_date($event['event_date'] ?? null)) ?></dd></div>
        <div><dt>Lieu</dt><dd><?= e((string) ($event['location'] ?? '')) ?: '—' ?></dd></div>
        <div><dt>Statut</dt><dd>
            <span class="badge badge--<?= e((string) $event['status']) ?>">
                <?= e(EventStatus::label((string) $event['status'])) ?>
            </span>
        </dd></div>
    </dl>

    <?php if (($event['description'] ?? '') !== ''): ?>
        <div class="note-block">
            <p><?= nl2br(e((string) $event['description'])) ?></p>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <header class="panel__head"><h2 class="panel__title">Galeries</h2></header>

    <?php if ($galleries === []): ?>
        <p class="empty">Aucune galerie pour cet événement.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Galerie</th>
                        <th scope="col" class="numeric">Photos</th>
                        <th scope="col">Téléchargement</th>
                        <th scope="col">Expiration</th>
                        <th scope="col">Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($galleries as $gallery): ?>
                        <tr>
                            <th scope="row">
                                <a href="<?= e(url('/admin/galleries/' . (int) $gallery['id'])) ?>">
                                    <?= e((string) $gallery['title']) ?>
                                </a>
                            </th>
                            <td class="numeric"><?= (int) $gallery['photos_count'] ?></td>
                            <td><?= (int) $gallery['download_enabled'] === 1 ? 'Activé' : 'Désactivé' ?></td>
                            <td><?= e(format_datetime($gallery['expires_at'] ?? null)) ?></td>
                            <td>
                                <span class="badge badge--<?= e((string) $gallery['status']) ?>">
                                    <?= e(GalleryStatus::label((string) $gallery['status'])) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php View::endSection(); ?>
