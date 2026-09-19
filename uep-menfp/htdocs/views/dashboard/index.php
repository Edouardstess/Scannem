<?php
/**
 * Tableau de bord.
 * Les données des graphiques transitent par un bloc JSON : aucun script inline
 * n'a besoin d'interpoler des valeurs, ce qui permet une CSP stricte.
 */
?>
<section class="stats-grille">
    <article class="stat-carte stat-bleu">
        <div class="stat-icone"><i class="bi bi-building" aria-hidden="true"></i></div>
        <div>
            <p class="stat-valeur"><?= Format::nombre($statsUpd['total']) ?></p>
            <p class="stat-libelle">Dossiers UPD</p>
            <p class="stat-detail"><?= Format::nombre($statsUpd['valide']) ?> validé(s) · <?= Format::nombre($statsUpd['soumis']) ?> en attente</p>
        </div>
    </article>

    <article class="stat-carte stat-indigo">
        <div class="stat-icone"><i class="bi bi-diagram-3" aria-hidden="true"></i></div>
        <div>
            <p class="stat-valeur"><?= Format::nombre($statsDde['total']) ?></p>
            <p class="stat-libelle">Dossiers DDE</p>
            <p class="stat-detail"><?= Format::nombre($statsDde['valide']) ?> validé(s) · <?= Format::nombre($statsDde['soumis']) ?> en attente</p>
        </div>
    </article>

    <article class="stat-carte stat-ambre">
        <div class="stat-icone"><i class="bi bi-cart-check" aria-hidden="true"></i></div>
        <div>
            <p class="stat-valeur"><?= Format::nombre($nbRequisitions) ?></p>
            <p class="stat-libelle">Réquisitions</p>
            <p class="stat-detail"><?= Format::nombre($requisitionsParStatut['en_attente']) ?> en attente de décision</p>
        </div>
    </article>

    <article class="stat-carte stat-vert">
        <div class="stat-icone"><i class="bi bi-cash-coin" aria-hidden="true"></i></div>
        <div>
            <p class="stat-valeur stat-valeur-montant"><?= Format::montant($montantEngage, false) ?></p>
            <p class="stat-libelle">Montant engagé (HTG)</p>
            <p class="stat-detail"><?= Format::montant($montantEnAttente) ?> en attente</p>
        </div>
    </article>
</section>

<section class="panneaux-deux">
    <article class="panel">
        <header class="panel-header">
            <h2 class="panel-title">Réquisitions par statut</h2>
        </header>
        <div class="panel-body graphique-conteneur">
            <?php if ($nbRequisitions > 0): ?>
                <canvas id="graphiqueRequisitions" height="240" role="img"
                        aria-label="Répartition des réquisitions par statut"></canvas>
            <?php else: ?>
                <?php
                $icone = 'bi-cart';
                $titre = 'Aucune réquisition';
                $message = 'Les demandes d\'équipement apparaîtront ici dès la première saisie.';
                require RACINE_VIEWS . '/partials/vide.php';
                ?>
            <?php endif; ?>
        </div>
    </article>

    <article class="panel">
        <header class="panel-header">
            <h2 class="panel-title">Avancement des dossiers</h2>
        </header>
        <div class="panel-body graphique-conteneur">
            <?php if ($statsUpd['total'] + $statsDde['total'] > 0): ?>
                <canvas id="graphiqueDossiers" height="240" role="img"
                        aria-label="Avancement des dossiers UPD et DDE par statut"></canvas>
            <?php else: ?>
                <?php
                $icone = 'bi-folder2-open';
                $titre = 'Aucun dossier';
                $message = 'Les dossiers UPD et DDE apparaîtront ici dès la première saisie.';
                require RACINE_VIEWS . '/partials/vide.php';
                ?>
            <?php endif; ?>
        </div>
    </article>
</section>

<section class="panel">
    <header class="panel-header">
        <h2 class="panel-title">Couverture départementale</h2>
        <p class="panel-sous-titre">Nombre de dossiers enregistrés par département</p>
    </header>
    <div class="panel-body graphique-conteneur graphique-haut">
        <canvas id="graphiqueDepartements" height="300" role="img"
                aria-label="Nombre de dossiers UPD et DDE par département"></canvas>
    </div>
</section>

<section class="panneaux-deux">
    <article class="panel">
        <header class="panel-header">
            <h2 class="panel-title">Dernières réquisitions</h2>
            <a class="panel-lien" href="<?= URL_BASE ?>/requisitions">Tout voir <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
        </header>
        <?php if ($dernieresRequisitions === []): ?>
            <div class="panel-body">
                <?php
                $icone = 'bi-cart';
                $titre = 'Aucune réquisition';
                $message = 'Les demandes d\'équipement apparaîtront ici.';
                require RACINE_VIEWS . '/partials/vide.php';
                ?>
            </div>
        <?php else: ?>
            <div class="table-defilante">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">Numéro</th>
                            <th scope="col">Objet</th>
                            <th scope="col">Montant</th>
                            <th scope="col">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dernieresRequisitions as $requisition): ?>
                            <tr>
                                <td>
                                    <a class="lien-fort" href="<?= URL_BASE ?>/requisitions/<?= (int)$requisition['id'] ?>">
                                        <?= e($requisition['numero_requisition']) ?>
                                    </a>
                                    <span class="cellule-secondaire"><?= Format::date($requisition['date_demande']) ?></span>
                                </td>
                                <td>
                                    <?= e($requisition['objet']) ?>
                                    <span class="cellule-secondaire"><?= e($requisition['demandeur']) ?></span>
                                </td>
                                <td class="cellule-nombre"><?= Format::montant($requisition['montant_total'], false) ?></td>
                                <td><span class="badge-statut badge-req-<?= e($requisition['statut']) ?>"><?= e(Format::statutRequisition($requisition['statut'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </article>

    <article class="panel">
        <header class="panel-header">
            <h2 class="panel-title">Dossiers en attente de validation</h2>
        </header>
        <?php if ($dossiersEnAttente === []): ?>
            <div class="panel-body">
                <?php
                $icone = 'bi-check2-all';
                $titre = 'Rien à valider';
                $message = 'Aucun dossier UPD ou DDE n\'attend de décision.';
                require RACINE_VIEWS . '/partials/vide.php';
                ?>
            </div>
        <?php else: ?>
            <ul class="liste-simple">
                <?php foreach ($dossiersEnAttente as $dossier): ?>
                    <li>
                        <a href="<?= URL_BASE ?>/<?= e($dossier['base']) ?>/<?= (int)$dossier['id'] ?>">
                            <span class="pastille pastille-<?= e($dossier['base']) ?>"><?= e(strtoupper((string)$dossier['base'])) ?></span>
                            <span>
                                <strong><?= e($dossier['nom'] ?: 'Dossier #' . (int)$dossier['id']) ?></strong>
                                <small><?= e(Departements::affichage($dossier['departement'])) ?> · modifié le <?= Format::dateHeure($dossier['updated_at']) ?></small>
                            </span>
                            <i class="bi bi-chevron-right" aria-hidden="true"></i>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </article>
</section>

<section class="panneaux-deux">
    <article class="panel panel-compact">
        <header class="panel-header"><h2 class="panel-title">Complétion moyenne des questionnaires</h2></header>
        <div class="panel-body">
            <div class="jauge-ligne">
                <span class="jauge-label">UPD</span>
                <div class="progress-bar-container"><div class="progress-bar-fill" style="width: <?= (int)$completionUpd ?>%"></div></div>
                <span class="jauge-valeur"><?= (int)$completionUpd ?> %</span>
            </div>
            <div class="jauge-ligne">
                <span class="jauge-label">DDE</span>
                <div class="progress-bar-container"><div class="progress-bar-fill" style="width: <?= (int)$completionDde ?>%"></div></div>
                <span class="jauge-valeur"><?= (int)$completionDde ?> %</span>
            </div>
        </div>
    </article>

    <article class="panel panel-compact">
        <header class="panel-header"><h2 class="panel-title">Accès rapides</h2></header>
        <div class="panel-body acces-rapides">
            <a class="acces-rapide" href="<?= URL_BASE ?>/upd"><i class="bi bi-building" aria-hidden="true"></i> Dossiers UPD</a>
            <a class="acces-rapide" href="<?= URL_BASE ?>/dde"><i class="bi bi-diagram-3" aria-hidden="true"></i> Dossiers DDE</a>
            <?php if (Auth::peutSaisir()): ?>
                <a class="acces-rapide" href="<?= URL_BASE ?>/requisitions/nouveau"><i class="bi bi-plus-circle" aria-hidden="true"></i> Nouvelle réquisition</a>
            <?php endif; ?>
            <?php if (Auth::estAdmin()): ?>
                <a class="acces-rapide" href="<?= URL_BASE ?>/utilisateurs/nouveau"><i class="bi bi-person-plus" aria-hidden="true"></i> Nouvel utilisateur</a>
            <?php endif; ?>
            <a class="acces-rapide" href="<?= URL_BASE ?>/profil"><i class="bi bi-person-circle" aria-hidden="true"></i> Mon profil</a>
        </div>
    </article>
</section>

<script type="application/json" id="donnees-graphiques"><?= json_encode($graphiques, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<?php
$scriptsPage = '<script src="' . URL_BASE . '/assets/vendor/chartjs/chart.umd.js" defer></script>'
    . '<script src="' . URL_BASE . '/assets/js/dashboard.js?v=' . e(APP_VERSION) . '" defer></script>';
?>
