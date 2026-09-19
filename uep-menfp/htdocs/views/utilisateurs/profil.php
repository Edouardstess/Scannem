<?php
/**
 * Fiche du compte connecté.
 * @var array<string,mixed> $utilisateur
 * @var list<array<string,mixed>> $activites
 */
?>
<section class="panneaux-deux">
    <article class="panel">
        <header class="panel-header"><h2 class="panel-title">Mon compte</h2></header>
        <dl class="fiche-definitions">
            <dt>Nom complet</dt><dd><?= e($utilisateur['nom_complet']) ?></dd>
            <dt>Adresse électronique</dt><dd><?= e($utilisateur['email']) ?></dd>
            <dt>Téléphone</dt><dd><?= e($utilisateur['telephone'] ?: '—') ?></dd>
            <dt>Rôle</dt>
            <dd>
                <span class="badge-role badge-role-<?= e($utilisateur['nom_role']) ?>"><?= e($utilisateur['role_libelle']) ?></span>
                <br><small><?= e($utilisateur['role_description'] ?? '') ?></small>
            </dd>
            <dt>Département</dt><dd><?= e(Departements::affichage($utilisateur['departement_rattachement'])) ?></dd>
            <dt>Institution</dt><dd><?= e($utilisateur['institution_type'] ?: '—') ?></dd>
            <dt>Dernière connexion</dt><dd><?= Format::dateHeure($utilisateur['derniere_connexion']) ?></dd>
            <dt>Compte créé le</dt><dd><?= Format::dateHeure($utilisateur['created_at']) ?></dd>
        </dl>
        <div class="panel-body">
            <a class="btn btn-primary" href="<?= URL_BASE ?>/mot-de-passe/changer">
                <i class="bi bi-key" aria-hidden="true"></i> Changer mon mot de passe
            </a>
        </div>
    </article>

    <article class="panel">
        <header class="panel-header"><h2 class="panel-title">Mes dernières actions</h2></header>
        <?php if ($activites === []): ?>
            <div class="panel-body">
                <?php
                $icone = 'bi-clock-history';
                $titre = 'Aucune activité';
                $message = 'Vos actions apparaîtront ici.';
                $action = '';
                require RACINE_VIEWS . '/partials/vide.php';
                ?>
            </div>
        <?php else: ?>
            <ul class="liste-activites">
                <?php foreach ($activites as $activite): ?>
                    <li>
                        <span class="activite-action"><?= e(str_replace('_', ' ', (string)$activite['action'])) ?></span>
                        <span class="activite-detail"><?= e($activite['details'] ?: ($activite['table_concernee'] ?: '')) ?></span>
                        <time><?= Format::dateHeure($activite['created_at']) ?></time>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </article>
</section>
