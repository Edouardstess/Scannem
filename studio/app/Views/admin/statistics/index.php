<?php
/**
 * @var array<string, int>               $stats
 * @var array<string, mixed>             $activity
 * @var array<string, mixed>             $monthly
 * @var array<int, array<string, mixed>> $popular
 * @var int                              $days
 * @var array<string, int>               $usage
 */

use App\Core\View;

View::extend('layouts.admin');
View::startSection('content');
?>

<div class="panel-actions">
    <nav class="tabs" aria-label="Période">
        <?php foreach ([7 => '7 jours', 30 => '30 jours', 90 => '90 jours', 365 => '1 an'] as $value => $label): ?>
            <a class="tabs__link<?= $days === $value ? ' is-active' : '' ?>"
               href="<?= e(url('/admin/statistics?days=' . $value)) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>

    <a class="button button--ghost" href="<?= e(url('/admin/statistics/audit')) ?>">Journal d'audit</a>
</div>

<section class="tiles">
    <div class="tile"><span class="tile__value"><?= number_format($stats['views'], 0, ',', ' ') ?></span><span class="tile__label">Consultations</span></div>
    <div class="tile"><span class="tile__value"><?= number_format($stats['downloads'], 0, ',', ' ') ?></span><span class="tile__label">Téléchargements</span></div>
    <div class="tile"><span class="tile__value"><?= number_format($stats['galleries'], 0, ',', ' ') ?></span><span class="tile__label">Galeries</span></div>
    <div class="tile"><span class="tile__value"><?= number_format($stats['photos'], 0, ',', ' ') ?></span><span class="tile__label">Photos</span></div>
    <div class="tile"><span class="tile__value"><?= e(format_bytes($stats['downloaded_bytes'])) ?></span><span class="tile__label">Volume téléchargé</span></div>
    <div class="tile"><span class="tile__value"><?= e(format_bytes($usage['total'])) ?></span><span class="tile__label">Stockage utilisé</span></div>
</section>

<section class="panel">
    <header class="panel__head">
        <h2 class="panel__title">Consultations et téléchargements</h2>
        <span class="panel__hint"><?= (int) $days ?> derniers jours</span>
    </header>

    <?= View::include('partials.chart', [
        'id'     => 'stats-activity',
        'labels' => $activity['labels'],
        'series' => [
            ['label' => 'Consultations',   'values' => $activity['views'],     'variant' => 'primary'],
            ['label' => 'Téléchargements', 'values' => $activity['downloads'], 'variant' => 'accent'],
        ],
    ]) ?>
</section>

<section class="panel">
    <header class="panel__head"><h2 class="panel__title">Galeries créées par mois</h2></header>

    <?= View::include('partials.chart', [
        'id'     => 'stats-monthly',
        'labels' => $monthly['labels'],
        'series' => [
            ['label' => 'Galeries créées', 'values' => $monthly['totals'], 'variant' => 'primary'],
        ],
    ]) ?>
</section>

<div class="panel-grid">
    <section class="panel">
        <header class="panel__head"><h2 class="panel__title">Galeries les plus consultées</h2></header>

        <?php if ($popular === []): ?>
            <p class="empty">Aucune consultation enregistrée.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table table--compact">
                    <thead>
                        <tr>
                            <th scope="col">Galerie</th>
                            <th scope="col" class="numeric">Vues</th>
                            <th scope="col" class="numeric">Téléchargements</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($popular as $gallery): ?>
                            <tr>
                                <th scope="row">
                                    <a href="<?= e(url('/admin/galleries/' . (int) $gallery['id'])) ?>">
                                        <?= e((string) $gallery['title']) ?>
                                    </a>
                                </th>
                                <td class="numeric"><?= (int) $gallery['views_count'] ?></td>
                                <td class="numeric"><?= (int) $gallery['downloads_count'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <header class="panel__head"><h2 class="panel__title">Stockage</h2></header>
        <dl class="definition">
            <div><dt>Originaux</dt><dd><?= e(format_bytes($usage['originals'])) ?></dd></div>
            <div><dt>Aperçus</dt><dd><?= e(format_bytes($usage['previews'])) ?></dd></div>
            <div><dt>Miniatures</dt><dd><?= e(format_bytes($usage['thumbnails'])) ?></dd></div>
            <div><dt>Archives temporaires</dt><dd><?= e(format_bytes($usage['temporary'])) ?></dd></div>
            <div><dt>Total</dt><dd><strong><?= e(format_bytes($usage['total'])) ?></strong></dd></div>
        </dl>
        <p class="panel__text">
            Les archives temporaires sont purgées automatiquement ; vous pouvez forcer
            la purge depuis <a href="<?= e(url('/admin/settings')) ?>">les paramètres</a>.
        </p>
    </section>
</div>

<?php View::endSection(); ?>
