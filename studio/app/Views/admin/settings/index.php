<?php
/**
 * @var array<string, mixed>                                                        $values
 * @var array<int, array{label: string, ok: bool, detail: string, critical: bool}>  $diagnostics
 */

use App\Core\View;

View::extend('layouts.admin');
View::startSection('content');

$value = static function (string $key) use ($values): string {
    return (string) old($key, $values[$key] ?? '');
};
?>

<section class="panel">
    <header class="panel__head"><h2 class="panel__title">Diagnostic de l'installation</h2></header>

    <ul class="diagnostics">
        <?php foreach ($diagnostics as $check): ?>
            <li class="diagnostic<?= $check['ok'] ? ' is-ok' : ($check['critical'] ? ' is-critical' : ' is-warn') ?>">
                <span class="diagnostic__icon" aria-hidden="true"><?= $check['ok'] ? '✓' : '!' ?></span>
                <span class="diagnostic__body">
                    <span class="diagnostic__label">
                        <?= e($check['label']) ?>
                        <span class="sr-only"><?= $check['ok'] ? ' : conforme' : ' : à corriger' ?></span>
                    </span>
                    <span class="diagnostic__detail"><?= e($check['detail']) ?></span>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<form class="form form--panel" method="post" action="<?= e(url('/admin/settings')) ?>"
      enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>

    <fieldset class="fieldset">
        <legend class="fieldset__legend">Identité</legend>

        <div class="field-row">
            <div class="field">
                <label for="studio_name">Nom du studio <span aria-hidden="true">*</span></label>
                <input type="text" id="studio_name" name="studio_name" required maxlength="120"
                       value="<?= e($value('studio_name')) ?>">
                <?php if ($message = error_for('studio_name')): ?>
                    <p class="field__error"><?= e($message) ?></p>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="photographer_name">Nom du photographe</label>
                <input type="text" id="photographer_name" name="photographer_name" maxlength="120"
                       value="<?= e($value('photographer_name')) ?>">
            </div>
        </div>

        <div class="field-row">
            <div class="field">
                <label for="tagline">Slogan</label>
                <input type="text" id="tagline" name="tagline" maxlength="190" value="<?= e($value('tagline')) ?>">
            </div>

            <div class="field">
                <label for="speciality">Spécialités</label>
                <input type="text" id="speciality" name="speciality" maxlength="190"
                       value="<?= e($value('speciality')) ?>" placeholder="Mariage · Portrait · Éditorial">
            </div>
        </div>

        <div class="field">
            <label for="logo_path">Logo</label>
            <?php if (($values['logo_path'] ?? '') !== ''): ?>
                <img class="form__preview form__preview--small" src="<?= e(url((string) $values['logo_path'])) ?>" alt="">
            <?php endif; ?>
            <input type="file" id="logo_path" name="logo_path" accept="image/jpeg,image/png,image/webp">
        </div>
    </fieldset>

    <fieldset class="fieldset">
        <legend class="fieldset__legend">Page d'accueil</legend>

        <div class="field">
            <label for="hero_title">Titre principal</label>
            <input type="text" id="hero_title" name="hero_title" maxlength="190" value="<?= e($value('hero_title')) ?>">
        </div>

        <div class="field">
            <label for="hero_subtitle">Sous-titre</label>
            <input type="text" id="hero_subtitle" name="hero_subtitle" maxlength="255"
                   value="<?= e($value('hero_subtitle')) ?>">
        </div>

        <div class="field">
            <label for="hero_image">Image d'accueil</label>
            <?php if (($values['hero_image'] ?? '') !== ''): ?>
                <img class="form__preview" src="<?= e(url((string) $values['hero_image'])) ?>" alt="">
            <?php endif; ?>
            <input type="file" id="hero_image" name="hero_image" accept="image/jpeg,image/png,image/webp">
            <p class="field__hint">Format paysage, idéalement 2000&nbsp;px de large.</p>
        </div>
    </fieldset>

    <fieldset class="fieldset">
        <legend class="fieldset__legend">À propos</legend>

        <div class="field">
            <label for="about_title">Titre</label>
            <input type="text" id="about_title" name="about_title" maxlength="190"
                   value="<?= e($value('about_title')) ?>">
        </div>

        <div class="field">
            <label for="about_text">Texte de présentation</label>
            <textarea id="about_text" name="about_text" rows="8" maxlength="5000"><?= e($value('about_text')) ?></textarea>
        </div>

        <div class="field">
            <label for="about_image">Portrait</label>
            <?php if (($values['about_image'] ?? '') !== ''): ?>
                <img class="form__preview" src="<?= e(url((string) $values['about_image'])) ?>" alt="">
            <?php endif; ?>
            <input type="file" id="about_image" name="about_image" accept="image/jpeg,image/png,image/webp">
        </div>
    </fieldset>

    <fieldset class="fieldset">
        <legend class="fieldset__legend">Contact</legend>

        <div class="field-row">
            <div class="field">
                <label for="contact_email">E-mail</label>
                <input type="email" id="contact_email" name="contact_email" maxlength="190"
                       value="<?= e($value('contact_email')) ?>">
                <p class="field__hint">Reçoit les messages et les demandes de réservation.</p>
                <?php if ($message = error_for('contact_email')): ?>
                    <p class="field__error"><?= e($message) ?></p>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="contact_phone">Téléphone</label>
                <input type="tel" id="contact_phone" name="contact_phone" maxlength="40"
                       value="<?= e($value('contact_phone')) ?>">
                <?php if ($message = error_for('contact_phone')): ?>
                    <p class="field__error"><?= e($message) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="field">
            <label for="contact_address">Adresse</label>
            <input type="text" id="contact_address" name="contact_address" maxlength="255"
                   value="<?= e($value('contact_address')) ?>">
        </div>
    </fieldset>

    <?php $map = new \App\Services\MapService($values); ?>
    <fieldset class="fieldset" id="localisation">
        <legend class="fieldset__legend">Localisation (Google Maps)</legend>

        <div class="field">
            <label for="map_query">Où vous trouver</label>
            <input type="text" id="map_query" name="map_query" maxlength="255"
                   value="<?= e($value('map_query')) ?>"
                   placeholder="<?= e((string) ($values['contact_address'] ?? '') ?: 'Ex. : 12 rue Capois, Port-au-Prince') ?>">
            <p class="field__hint">
                Une adresse complète, le nom du lieu tel qu'il apparaît sur Google Maps, ou des coordonnées
                (ex. <code>18.5392, -72.3364</code>). Vide : l'adresse de contact est utilisée.
            </p>
            <?php if ($message = error_for('map_query')): ?>
                <p class="field__error"><?= e($message) ?></p>
            <?php endif; ?>
        </div>

        <div class="field">
            <label for="map_embed_code">Épingle exacte (facultatif)</label>
            <textarea id="map_embed_code" name="map_embed_code" rows="3" maxlength="5000"
                      placeholder="&lt;iframe src=&quot;https://www.google.com/maps/embed?pb=…&quot;&gt;&lt;/iframe&gt;"><?= e((string) old('map_embed_code', $values['map_embed_url'] ?? '')) ?></textarea>
            <p class="field__hint">
                Pour placer l'épingle au mètre près : sur Google Maps, cherchez votre studio,
                puis <strong>Partager → Intégrer une carte → Copier le code HTML</strong> et collez-le ici.
            </p>
            <?php if ($message = error_for('map_embed_code')): ?>
                <p class="field__error"><?= e($message) ?></p>
            <?php endif; ?>
        </div>

        <div class="switch-list">
            <label class="switch">
                <input type="checkbox" name="map_show_home" value="1"
                    <?= (bool) old('map_show_home', $values['map_show_home'] ?? true) ? 'checked' : '' ?>>
                <span class="switch__label">
                    Afficher la carte sur la page d'accueil
                    <span class="switch__hint">Elle apparaît toujours sur la page Contact.</span>
                </span>
            </label>

            <label class="switch">
                <input type="checkbox" name="map_click_to_load" value="1"
                    <?= (bool) old('map_click_to_load', $values['map_click_to_load'] ?? false) ? 'checked' : '' ?>>
                <span class="switch__label">
                    Charger la carte seulement après un clic du visiteur
                    <span class="switch__hint">
                        Google dépose des cookies dès l'affichage de la carte. Recommandé si vous avez
                        des visiteurs en Europe (RGPD) ; la page s'affiche aussi plus vite.
                    </span>
                </span>
            </label>
        </div>

        <?php if ($map->isAvailable()): ?>
            <div class="map-preview">
                <p class="field__hint">Aperçu de la carte affichée sur le site :</p>
                <iframe class="map-preview__frame" src="<?= e((string) $map->embedUrl()) ?>"
                        title="Aperçu de la carte" loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
        <?php else: ?>
            <p class="field__hint">Renseignez une adresse ou une localisation pour afficher la carte sur le site.</p>
        <?php endif; ?>
    </fieldset>

    <fieldset class="fieldset">
        <legend class="fieldset__legend">Réseaux sociaux</legend>

        <div class="field-row">
            <?php foreach ([
                'social_instagram' => 'Instagram',
                'social_facebook'  => 'Facebook',
                'social_linkedin'  => 'LinkedIn',
                'social_pinterest' => 'Pinterest',
            ] as $key => $label): ?>
                <div class="field">
                    <label for="<?= e($key) ?>"><?= e($label) ?></label>
                    <input type="url" id="<?= e($key) ?>" name="<?= e($key) ?>" maxlength="255"
                           value="<?= e($value($key)) ?>" placeholder="https://">
                    <?php if ($message = error_for($key)): ?>
                        <p class="field__error"><?= e($message) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <fieldset class="fieldset">
        <legend class="fieldset__legend">Apparence</legend>

        <div class="field-row">
            <?php foreach ([
                'color_primary'    => ['Couleur principale', '#1a1a1a'],
                'color_accent'     => ["Couleur d'accent", '#b08d57'],
                'color_background' => ['Couleur de fond', '#fbfaf8'],
            ] as $key => [$label, $fallback]): ?>
                <div class="field">
                    <label for="<?= e($key) ?>"><?= e($label) ?></label>
                    <input type="color" id="<?= e($key) ?>" name="<?= e($key) ?>"
                           value="<?= e($value($key) !== '' ? $value($key) : $fallback) ?>">
                    <?php if ($message = error_for($key)): ?>
                        <p class="field__error"><?= e($message) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <fieldset class="fieldset">
        <legend class="fieldset__legend">Galeries et référencement</legend>

        <div class="field">
            <label for="watermark_text">Texte du filigrane</label>
            <input type="text" id="watermark_text" name="watermark_text" maxlength="120"
                   value="<?= e($value('watermark_text')) ?>"
                   placeholder="<?= e((string) ($values['studio_name'] ?? '')) ?>">
            <p class="field__hint">
                Utilisé sur les aperçus des galeries dont le filigrane est activé.
                Vide : le nom du studio est utilisé.
            </p>
        </div>

        <div class="field">
            <label for="meta_description">Description pour les moteurs de recherche</label>
            <textarea id="meta_description" name="meta_description" rows="3"
                      maxlength="255"><?= e($value('meta_description')) ?></textarea>
        </div>

        <div class="field">
            <label for="footer_text">Texte de pied de page</label>
            <input type="text" id="footer_text" name="footer_text" maxlength="500"
                   value="<?= e($value('footer_text')) ?>">
        </div>

        <div class="switch-list">
            <label class="switch">
                <input type="checkbox" name="booking_enabled" value="1"
                    <?= (bool) old('booking_enabled', $values['booking_enabled'] ?? true) ? 'checked' : '' ?>>
                <span class="switch__label">
                    Activer la page de réservation
                    <span class="switch__hint">Désactivée, /reservation renvoie une page 404.</span>
                </span>
            </label>

            <label class="switch">
                <input type="checkbox" name="client_area_enabled" value="1"
                    <?= (bool) old('client_area_enabled', $values['client_area_enabled'] ?? true) ? 'checked' : '' ?>>
                <span class="switch__label">
                    Afficher l'espace client
                    <span class="switch__hint">
                        Permet à un client de coller son lien ou son code pour ouvrir sa galerie.
                    </span>
                </span>
            </label>
        </div>
    </fieldset>

    <div class="form__actions">
        <button class="button" type="submit">Enregistrer les paramètres</button>
    </div>
</form>

<section class="panel">
    <header class="panel__head"><h2 class="panel__title">Maintenance</h2></header>
    <p class="panel__text">
        Ces opérations sont sans risque pour les photographies : elles ne touchent
        qu'aux fichiers temporaires et aux journaux.
    </p>

    <div class="panel__footer">
        <?php foreach ([
            'prune_temporary'   => 'Purger les archives temporaires',
            'purge_rate_limits' => 'Purger les compteurs de limitation expirés',
            'purge_audit'       => "Purger le journal d'audit de plus d'un an",
        ] as $action => $label): ?>
            <form method="post" action="<?= e(url('/admin/settings/maintenance')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= e($action) ?>">
                <button class="button button--ghost button--small" type="submit"><?= e($label) ?></button>
            </form>
        <?php endforeach; ?>
    </div>
</section>

<?php View::endSection(); ?>
