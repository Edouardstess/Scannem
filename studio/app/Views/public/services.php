<?php
/** @var array<int, array<string, mixed>> $services */

use App\Core\View;

View::extend('layouts.public');
View::startSection('content');
?>

<section class="page-head">
    <div class="wrap">
        <p class="section__eyebrow">Prestations</p>
        <h1 class="page-head__title">Formules et tarifs</h1>
        <p class="page-head__text">Chaque projet est différent. Ces formules sont un point de départ.</p>
    </div>
</section>

<section class="section">
    <div class="wrap">
        <?php if ($services === []): ?>
            <p class="empty">Les prestations seront publiées prochainement.</p>
        <?php else: ?>
            <div class="service-list">
                <?php foreach ($services as $service): ?>
                    <article class="service">
                        <?php if (($service['image_path'] ?? '') !== ''): ?>
                            <div class="service__media">
                                <img src="<?= e(url((string) $service['image_path'])) ?>"
                                     alt="<?= e((string) $service['title']) ?>" loading="lazy" decoding="async">
                            </div>
                        <?php endif; ?>

                        <div class="service__body">
                            <h2 class="service__title"><?= e((string) $service['title']) ?></h2>

                            <?php if (($service['summary'] ?? '') !== ''): ?>
                                <p class="service__summary"><?= e((string) $service['summary']) ?></p>
                            <?php endif; ?>

                            <dl class="service__meta">
                                <?php if (($service['price_from'] ?? null) !== null): ?>
                                    <div>
                                        <dt>Tarif</dt>
                                        <dd>à partir de
                                            <?= e(number_format((float) $service['price_from'], 0, ',', ' ')) ?>
                                            <?= e((string) $service['currency']) ?>
                                        </dd>
                                    </div>
                                <?php endif; ?>
                                <?php if (($service['duration'] ?? '') !== ''): ?>
                                    <div><dt>Durée</dt><dd><?= e((string) $service['duration']) ?></dd></div>
                                <?php endif; ?>
                            </dl>

                            <a class="link-arrow" href="<?= e(url('/services/' . (string) $service['slug'])) ?>">
                                Voir le détail
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="cta">
    <div class="wrap wrap--narrow">
        <h2 class="cta__title">Une question sur une formule&nbsp;?</h2>
        <div class="cta__actions">
            <a class="button" href="<?= e(url('/contact')) ?>">Me contacter</a>
        </div>
    </div>
</section>

<?php View::endSection(); ?>
