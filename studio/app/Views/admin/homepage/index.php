<?php
/**
 * Admin → Page d'accueil (immersive homepage texts).
 *
 * @var string               $layout        immersive | classic
 * @var array<string, mixed> $texts         current values (defaults filled in)
 * @var bool                 $placeholders  no published portfolio photo yet
 */

use App\Core\View;

View::extend('layouts.admin');
View::startSection('content');

$val = static fn (string $key): string => (string) old($key, $texts[$key] ?? '');

/** One text field (see field.php), with its current value and error. */
$field = static fn (string $key, string $label, string $hint = '', int $max = 190, bool $long = false): array => [
    'key' => $key, 'label' => $label, 'hint' => $hint, 'max' => $max, 'long' => $long, 'value' => $val($key),
];
?>

<div class="panel-actions">
    <a class="link-arrow" href="<?= e(url('/')) ?>" target="_blank" rel="noopener">Voir la page d’accueil</a>
</div>

<?php if ($placeholders): ?>
    <p class="alert alert--info">
        Aucune photo publiée dans le portfolio : la page d’accueil affiche des visuels provisoires.
        Ajoutez vos photos dans <a href="<?= e(url('/admin/portfolio')) ?>">Portfolio</a> et cochez « En vedette »
        pour celles à montrer en premier.
    </p>
<?php endif; ?>

<form class="form form--panel" method="post" action="<?= e(url('/admin/homepage')) ?>">
    <?= csrf_field() ?>

    <fieldset class="fieldset">
        <legend class="fieldset__legend">Affichage</legend>

        <div class="field-row">
            <div class="field">
                <label for="hp_home_layout">Modèle de page d’accueil</label>
                <select id="hp_home_layout" name="home_layout">
                    <option value="immersive"<?= old('home_layout', $layout) === 'immersive' ? ' selected' : '' ?>>Immersive (animée au défilement)</option>
                    <option value="classic"<?= old('home_layout', $layout) === 'classic' ? ' selected' : '' ?>>Classique</option>
                </select>
            </div>
            <div class="field">
                <label for="hp_theme">Thème</label>
                <select id="hp_theme" name="theme">
                    <option value="light"<?= $val('theme') === 'light' ? ' selected' : '' ?>>Clair</option>
                    <option value="dark"<?= $val('theme') === 'dark' ? ' selected' : '' ?>>Sombre</option>
                </select>
            </div>
            <div class="field">
                <label for="hp_accent">Couleur d’accent</label>
                <input type="color" id="hp_accent" name="accent" value="<?= e($val('accent')) ?>">
                <?php if ($m = error_for('accent')): ?><p class="field__error"><?= e($m) ?></p><?php endif; ?>
            </div>
        </div>
        <p class="field__hint">
            Les photos viennent du <a href="<?= e(url('/admin/portfolio')) ?>">Portfolio</a> (photos « en vedette » d’abord) ;
            la grande image d’ouverture est l’« image d’accueil » des <a href="<?= e(url('/admin/settings')) ?>">Paramètres</a>.
        </p>
    </fieldset>

    <fieldset class="fieldset">
        <legend class="fieldset__legend">1 · Accroche</legend>
        <?= View::include('admin.homepage.field', $field('kicker', 'Petite ligne au-dessus du titre', 'Vide : « NOM — PHOTOGRAPHE ». En majuscules.', 190)) ?>
        <?= View::include('admin.homepage.field', $field('hook_line1', 'Titre, ligne 1', '', 40)) ?>
        <div class="field-row">
            <?= View::include('admin.homepage.field', $field('hook_line2a', 'Titre, ligne 2 — avant l’image', 'L’image naît entre ces deux morceaux.', 18)) ?>
            <?= View::include('admin.homepage.field', $field('hook_line2b', 'Titre, ligne 2 — après l’image', '', 18)) ?>
        </div>
        <?= View::include('admin.homepage.field', $field('positioning', 'Positionnement (sur l’image plein écran)', '42 caractères maximum : il tient sur une ligne.', 42)) ?>
    </fieldset>

    <fieldset class="fieldset">
        <legend class="fieldset__legend">2 · Démarche</legend>
        <?= View::include('admin.homepage.field', $field('manifesto', 'Texte', 'Deux ou trois phrases. Entourez 1 à 3 mots avec [[ et ]] : ils seront cerclés à la main.', 260, true)) ?>
    </fieldset>

    <fieldset class="fieldset">
        <legend class="fieldset__legend">3 · Travaux</legend>
        <div class="field-row">
            <?= View::include('admin.homepage.field', $field('proof_kicker', 'Surtitre', '', 60)) ?>
            <?= View::include('admin.homepage.field', $field('proof_title', 'Titre', '', 80)) ?>
        </div>
        <?= View::include('admin.homepage.field', $field('proof_sub', 'Sous-titre', '', 120)) ?>
        <?= View::include('admin.homepage.field', $field('proof_meta', 'Mention (ex. HUIT SÉANCES — 2024 → 2026)', 'Vide : calculée depuis vos photos.', 60)) ?>
    </fieldset>

    <fieldset class="fieldset">
        <legend class="fieldset__legend">4 · Devise — trois mots géants</legend>
        <?= View::include('admin.homepage.field', $field('motto_kicker', 'Surtitre', '', 60)) ?>
        <?php foreach ([1, 2, 3] as $i): ?>
            <div class="field-row">
                <?= View::include('admin.homepage.field', $field("motto_{$i}_word", "Mot {$i}", '', 12)) ?>
                <?= View::include('admin.homepage.field', $field("motto_{$i}_hint", "Légende du mot {$i}", '', 90)) ?>
            </div>
        <?php endforeach; ?>
    </fieldset>

    <fieldset class="fieldset">
        <legend class="fieldset__legend">5 · Étapes</legend>
        <div class="field-row">
            <?= View::include('admin.homepage.field', $field('steps_intro_a', 'Intro, mot 1', '', 14)) ?>
            <?= View::include('admin.homepage.field', $field('steps_intro_b', 'Intro, mot 2', '', 14)) ?>
            <?= View::include('admin.homepage.field', $field('steps_intro_c', 'Intro, après l’image', '', 14)) ?>
        </div>
        <?php foreach ([1, 2, 3] as $i): ?>
            <div class="field-row">
                <?= View::include('admin.homepage.field', $field("step_{$i}_name", "Étape {$i}", '', 28)) ?>
                <?= View::include('admin.homepage.field', $field("step_{$i}_desc", "Description de l’étape {$i}", '', 180)) ?>
            </div>
        <?php endforeach; ?>
        <?= View::include('admin.homepage.field', $field('steps_cta', 'Bouton final', 'Mène au formulaire de réservation.', 24)) ?>
    </fieldset>

    <fieldset class="fieldset">
        <legend class="fieldset__legend">6 · Témoignage</legend>
        <?= View::include('admin.homepage.field', $field('quote_text', 'Citation (sans guillemets)', 'Une ou deux phrases qu’un client a vraiment dites.', 240, true)) ?>
        <div class="field-row">
            <?= View::include('admin.homepage.field', $field('quote_author', 'Signature (en majuscules)', '', 80)) ?>
            <?= View::include('admin.homepage.field', $field('quote_kicker', 'Contexte (en majuscules)', '', 60)) ?>
        </div>
        <div class="field-row">
            <?= View::include('admin.homepage.field', $field('quote_figure', 'Chiffre', 'Monte de 0 à cette valeur. Vide : pas de chiffre.', 8)) ?>
            <?= View::include('admin.homepage.field', $field('quote_unit', 'Unité', '4 caractères maximum (sem., %, h…).', 4)) ?>
        </div>
    </fieldset>

    <fieldset class="fieldset">
        <legend class="fieldset__legend">7 · Objections</legend>
        <?php foreach ([1, 2, 3] as $i): ?>
            <?= View::include('admin.homepage.field', $field("objection_{$i}", "Phrase {$i}", $i === 1 ? 'Commencez par « Pas de … ».' : '', 60)) ?>
        <?php endforeach; ?>
        <div class="field-row">
            <?= View::include('admin.homepage.field', $field('finale', 'Chute', 'Ex. : « Juste vos »', 30)) ?>
            <?= View::include('admin.homepage.field', $field('pill', 'Mot entouré', '1 ou 2 mots.', 18)) ?>
        </div>
    </fieldset>

    <fieldset class="fieldset">
        <legend class="fieldset__legend">8 · Contact et navigation</legend>
        <div class="field-row">
            <?= View::include('admin.homepage.field', $field('contact_kicker', 'Surtitre du contact', '', 60)) ?>
            <?= View::include('admin.homepage.field', $field('reassurance', 'Réassurance', 'L’e-mail affiché est celui des Paramètres.', 90)) ?>
        </div>
        <?= View::include('admin.homepage.field', $field('signature', 'Signature de bas de page', '', 60)) ?>
        <?= View::include('admin.homepage.field', $field('nav_cta', 'Bouton du haut', 'À côté de Portfolio · Prestations · Contact.', 12)) ?>
    </fieldset>

    <div class="form__actions">
        <button class="button" type="submit">Enregistrer la page d’accueil</button>
    </div>
</form>

<?php View::endSection(); ?>
