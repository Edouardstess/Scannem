<?php
/**
 * @var array<string, mixed>             $client
 * @var array<int, array<string, mixed>> $events
 */

use App\Core\View;
use App\Models\EventStatus;
use App\Services\EventService;

View::extend('layouts.admin');
View::startSection('content');
?>

<div class="panel-actions">
    <a class="link-arrow" href="<?= e(url('/admin/clients')) ?>">← Tous les clients</a>
    <div class="panel-actions__group">
        <a class="button button--ghost" href="<?= e(url('/admin/clients/' . (int) $client['id'] . '/edit')) ?>">
            Modifier
        </a>
        <a class="button" href="<?= e(url('/admin/events/create?client_id=' . (int) $client['id'])) ?>">
            Nouvel événement
        </a>
    </div>
</div>

<section class="panel">
    <dl class="definition">
        <div><dt>E-mail</dt><dd>
            <?php if (($client['email'] ?? '') !== ''): ?>
                <a href="mailto:<?= e((string) $client['email']) ?>"><?= e((string) $client['email']) ?></a>
            <?php else: ?>—<?php endif; ?>
        </dd></div>
        <div><dt>Téléphone</dt><dd><?= e((string) ($client['phone'] ?? '')) ?: '—' ?></dd></div>
        <div><dt>Société</dt><dd><?= e((string) ($client['company'] ?? '')) ?: '—' ?></dd></div>
        <div><dt>Événements</dt><dd><?= (int) $client['events_count'] ?></dd></div>
        <div><dt>Galeries</dt><dd><?= (int) $client['galleries_count'] ?></dd></div>
        <div><dt>Client depuis</dt><dd><?= e(format_date((string) $client['created_at'])) ?></dd></div>
    </dl>

    <?php if (($client['notes'] ?? '') !== ''): ?>
        <div class="note-block">
            <h3 class="note-block__title">Notes internes</h3>
            <p><?= nl2br(e((string) $client['notes'])) ?></p>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <header class="panel__head"><h2 class="panel__title">Événements</h2></header>

    <?php if ($events === []): ?>
        <p class="empty">Aucun événement pour ce client.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Événement</th>
                        <th scope="col">Type</th>
                        <th scope="col">Date</th>
                        <th scope="col">Lieu</th>
                        <th scope="col" class="numeric">Galeries</th>
                        <th scope="col">Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $event): ?>
                        <tr>
                            <th scope="row">
                                <a href="<?= e(url('/admin/events/' . (int) $event['id'])) ?>">
                                    <?= e((string) $event['title']) ?>
                                </a>
                            </th>
                            <td><?= e(EventService::typeLabel($event['event_type'] ?? null)) ?></td>
                            <td><?= e(format_date($event['event_date'] ?? null)) ?></td>
                            <td><?= e((string) ($event['location'] ?? '')) ?: '—' ?></td>
                            <td class="numeric"><?= (int) $event['galleries_count'] ?></td>
                            <td>
                                <span class="badge badge--<?= e((string) $event['status']) ?>">
                                    <?= e(EventStatus::label((string) $event['status'])) ?>
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
