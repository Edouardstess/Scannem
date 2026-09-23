<?php
/**
 * @var array<int, array<string, mixed>> $events
 * @var array<string, mixed>             $pagination
 * @var string                           $search
 * @var string                           $status
 * @var array<int, string>               $statuses
 */

use App\Core\View;
use App\Models\EventStatus;
use App\Services\EventService;

View::extend('layouts.admin');
View::startSection('content');
?>

<div class="panel-actions">
    <form class="search" method="get" action="<?= e(url('/admin/events')) ?>" role="search">
        <label class="sr-only" for="q">Rechercher un événement</label>
        <input type="search" id="q" name="q" value="<?= e($search) ?>" placeholder="Titre, lieu…">

        <label class="sr-only" for="status">Statut</label>
        <select id="status" name="status">
            <option value="">Tous les statuts</option>
            <?php foreach ($statuses as $option): ?>
                <option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>>
                    <?= e(EventStatus::label($option)) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button class="button button--small" type="submit">Filtrer</button>
    </form>

    <a class="button" href="<?= e(url('/admin/events/create')) ?>">Nouvel événement</a>
</div>

<section class="panel">
    <?php if ($events === []): ?>
        <p class="empty">Aucun événement ne correspond.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Événement</th>
                        <th scope="col">Client</th>
                        <th scope="col">Type</th>
                        <th scope="col">Date</th>
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
                                <?php if (($event['location'] ?? '') !== ''): ?>
                                    <span class="table__sub"><?= e((string) $event['location']) ?></span>
                                <?php endif; ?>
                            </th>
                            <td>
                                <a href="<?= e(url('/admin/clients/' . (int) $event['client_id'])) ?>">
                                    <?= e(trim((string) $event['first_name'] . ' ' . (string) $event['last_name'])) ?>
                                </a>
                            </td>
                            <td><?= e(EventService::typeLabel($event['event_type'] ?? null)) ?></td>
                            <td><?= e(format_date($event['event_date'] ?? null)) ?></td>
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

        <?= View::include('partials.pagination', [
            'pagination' => $pagination,
            'basePath'   => '/admin/events',
            'query'      => ['q' => $search, 'status' => $status],
        ]) ?>
    <?php endif; ?>
</section>

<?php View::endSection(); ?>
