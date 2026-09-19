<?php
/**
 * Fiche détaillée d'une réquisition.
 * @var array<string,mixed> $requisition
 * @var list<array<string,mixed>> $articles
 * @var float $montantTotal
 */
$id = (int)$requisition['id'];
$statut = (string)$requisition['statut'];
$peutDecider = Auth::peutValider() && $statut !== 'livree';
$peutModifier = Auth::peutSaisir() && (Auth::estAdmin() || $statut === 'en_attente');
?>
<div class="dossier-entete">
    <div>
        <p class="dossier-kicker">Réquisition · Service informatique</p>
        <h2><?= e($requisition['numero_requisition']) ?></h2>
        <p class="dossier-meta">
            <span class="badge-statut badge-req-<?= e($statut) ?>"><?= e(Format::statutRequisition($statut)) ?></span>
            <span class="badge-priorite badge-prio-<?= e($requisition['priorite']) ?>"><?= e(Format::priorite($requisition['priorite'])) ?></span>
            <span><i class="bi bi-calendar3" aria-hidden="true"></i> <?= Format::date($requisition['date_demande']) ?></span>
        </p>
    </div>
    <div class="entete-actions">
        <a class="btn btn-outline" href="<?= URL_BASE ?>/requisitions"><i class="bi bi-arrow-left" aria-hidden="true"></i> Retour</a>
        <a class="btn btn-outline" href="<?= URL_BASE ?>/requisitions/<?= $id ?>/imprimer" target="_blank" rel="noopener">
            <i class="bi bi-printer" aria-hidden="true"></i> Bon imprimable
        </a>
        <?php if ($peutModifier): ?>
            <a class="btn btn-primary" href="<?= URL_BASE ?>/requisitions/<?= $id ?>/modifier">
                <i class="bi bi-pencil" aria-hidden="true"></i> Modifier
            </a>
        <?php endif; ?>
    </div>
</div>

<section class="panneaux-deux">
    <article class="panel">
        <header class="panel-header"><h3 class="panel-title">Informations</h3></header>
        <dl class="fiche-definitions">
            <dt>Objet</dt><dd><?= e($requisition['objet']) ?></dd>
            <dt>Demandeur</dt><dd><?= e($requisition['demandeur']) ?><br><small><?= e($requisition['demandeur_email']) ?></small></dd>
            <dt>Service</dt><dd><?= e($requisition['service_demandeur'] ?: '—') ?></dd>
            <dt>Adresse de livraison</dt><dd><?= e($requisition['adresse_livraison'] ?: '—') ?></dd>
            <dt>Justification</dt><dd><?= nl2br(e($requisition['justification'] ?: '—')) ?></dd>
            <dt>Observations</dt><dd><?= nl2br(e($requisition['observations'] ?: '—')) ?></dd>
        </dl>
    </article>

    <article class="panel">
        <header class="panel-header"><h3 class="panel-title">Suivi de la décision</h3></header>
        <dl class="fiche-definitions">
            <dt>Statut</dt>
            <dd><span class="badge-statut badge-req-<?= e($statut) ?>"><?= e(Format::statutRequisition($statut)) ?></span></dd>
            <dt>Décidé par</dt><dd><?= e($requisition['decideur'] ?: '—') ?></dd>
            <dt>Date de décision</dt><dd><?= Format::dateHeure($requisition['date_decision']) ?></dd>
            <dt>Commentaire</dt><dd><?= nl2br(e($requisition['commentaire_decision'] ?: '—')) ?></dd>
        </dl>

        <?php if ($peutDecider): ?>
            <form class="panel-body decision-formulaire" method="POST"
                  action="<?= URL_BASE ?>/requisitions/<?= $id ?>/decision"
                  data-confirmer="Confirmer cette décision sur la réquisition <?= e($requisition['numero_requisition']) ?> ?">
                <?= Csrf::champ() ?>
                <div class="champ">
                    <label for="statut">Nouvelle décision</label>
                    <select id="statut" name="statut" class="form-control" required>
                        <option value="en_attente"<?= $statut === 'en_attente' ? ' selected' : '' ?>>Remettre en attente</option>
                        <option value="approuvee"<?= $statut === 'approuvee' ? ' selected' : '' ?>>Approuver</option>
                        <option value="rejetee"<?= $statut === 'rejetee' ? ' selected' : '' ?>>Rejeter</option>
                        <option value="livree"<?= $statut === 'approuvee' ? '' : ' disabled' ?>>Déclarer livrée</option>
                    </select>
                    <p class="champ-aide">Une réquisition doit être approuvée avant de pouvoir être déclarée livrée.</p>
                </div>
                <div class="champ">
                    <label for="commentaire_decision">Commentaire</label>
                    <textarea id="commentaire_decision" name="commentaire_decision" rows="2" maxlength="2000"
                              class="form-control"><?= e($requisition['commentaire_decision'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary btn-bloc">
                    <i class="bi bi-check2-circle" aria-hidden="true"></i> Enregistrer la décision
                </button>
            </form>
        <?php elseif ($statut === 'livree'): ?>
            <div class="panel-body">
                <p class="texte-discret"><i class="bi bi-lock-fill" aria-hidden="true"></i> Cette réquisition est livrée : son statut est définitif.</p>
            </div>
        <?php endif; ?>
    </article>
</section>

<section class="panel">
    <header class="panel-header">
        <h3 class="panel-title">Articles demandés</h3>
        <p class="panel-sous-titre"><?= Format::nombre(count($articles)) ?> ligne(s)</p>
    </header>

    <?php if ($articles === []): ?>
        <div class="panel-body">
            <?php
            $icone = 'bi-box-seam';
            $titre = 'Aucun article';
            $message = 'Cette réquisition ne contient aucune ligne d\'article.';
            $action = '';
            require RACINE_VIEWS . '/partials/vide.php';
            ?>
        </div>
    <?php else: ?>
        <div class="table-defilante">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">Désignation</th>
                        <th scope="col">Catégorie</th>
                        <th scope="col">Unité</th>
                        <th scope="col" class="cellule-nombre">Quantité</th>
                        <th scope="col" class="cellule-nombre">Prix unitaire</th>
                        <th scope="col" class="cellule-nombre">Montant</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($articles as $article): ?>
                        <tr>
                            <td><?= e($article['designation']) ?></td>
                            <td><?= e($article['nom_categorie']) ?></td>
                            <td><?= e($article['unite_mesure']) ?></td>
                            <td class="cellule-nombre"><?= Format::nombre($article['quantite']) ?></td>
                            <td class="cellule-nombre"><?= Format::montant($article['prix_unitaire_estime'], false) ?></td>
                            <td class="cellule-nombre"><?= Format::montant($article['montant_total'], false) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="5" class="cellule-nombre">Total estimé</th>
                        <th class="cellule-nombre"><?= Format::montant($montantTotal) ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</section>
