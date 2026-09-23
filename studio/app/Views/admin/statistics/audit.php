<?php
/**
 * @var array<int, array<string, mixed>> $logs
 * @var array<string, mixed>             $pagination
 * @var array<int, string>               $actions
 * @var string                           $action
 */

use App\Core\View;
use App\Models\AuditAction;

View::extend('layouts.admin');
View::startSection('content');
?>

<div class="panel-actions">
    <a class="link-arrow" href="<?= e(url('/admin/statistics')) ?>">← Statistiques</a>

    <form class="search" method="get" action="<?= e(url('/admin/statistics/audit')) ?>">
        <label class="sr-only" for="action">Filtrer par action</label>
        <select id="action" name="action">
            <option value="">Toutes les actions</option>
            <?php foreach ($actions as $option): ?>
                <option value="<?= e($option) ?>" <?= $action === $option ? 'selected' : '' ?>>
                    <?= e(AuditAction::label($option)) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button class="button button--small" type="submit">Filtrer</button>
    </form>
</div>

<section class="panel">
    <p class="panel__text">
        Le journal enregistre les actions d'administration et les accès aux galeries.
        Il ne contient jamais de jeton en clair ni de mot de passe.
    </p>

    <?php if ($logs === []): ?>
        <p class="empty">Aucune entrée.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table table--compact">
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Action</th>
                        <th scope="col">Utilisateur</th>
                        <th scope="col">Galerie</th>
                        <th scope="col">Adresse IP</th>
                        <th scope="col">Détail</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= e(format_datetime((string) $log['created_at'])) ?></td>
                            <th scope="row"><?= e(AuditAction::label((string) $log['action'])) ?></th>
                            <td><?= e((string) ($log['user_name'] ?? '')) ?: '<span class="muted">client</span>' ?></td>
                            <td>
                                <?php if (($log['gallery_id'] ?? null) !== null && ($log['gallery_title'] ?? null) !== null): ?>
                                    <a href="<?= e(url('/admin/galleries/' . (int) $log['gallery_id'])) ?>">
                                        <?= e((string) $log['gallery_title']) ?>
                                    </a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td class="mono"><?= e((string) ($log['ip_address'] ?? '')) ?: '—' ?></td>
                            <td class="mono"><?= e(str_excerpt((string) ($log['context'] ?? ''), 60)) ?: '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?= View::include('partials.pagination', [
            'pagination' => $pagination,
            'basePath'   => '/admin/statistics/audit',
            'query'      => ['action' => $action],
        ]) ?>
    <?php endif; ?>
</section>

<?php View::endSection(); ?>
