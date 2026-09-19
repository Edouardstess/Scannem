<?php
/**
 * Tableau récapitulatif des réquisitions.
 * @var list<array<string,mixed>> $requisitions
 * @var Paginator $pagination
 */
?>
<section class="panel">
    <header class="panel-header">
        <div>
            <h2 class="panel-title">Réquisitions du service informatique</h2>
            <p class="panel-sous-titre">
                <?= Format::nombre($pagination->total) ?> demande(s) · cumul <?= Format::montant($cumul) ?>
            </p>
        </div>
        <?php if (Auth::peutSaisir()): ?>
            <a class="btn btn-primary" href="<?= URL_BASE ?>/requisitions/nouveau">
                <i class="bi bi-plus-lg" aria-hidden="true"></i> Nouvelle réquisition
            </a>
        <?php endif; ?>
    </header>

    <form class="barre-filtres" method="GET" action="<?= URL_BASE ?>/requisitions">
        <div class="filtre-recherche">
            <i class="bi bi-search" aria-hidden="true"></i>
            <label class="visually-hidden" for="filtre-q">Rechercher</label>
            <input type="search" id="filtre-q" name="q" value="<?= e($recherche) ?>" class="form-control"
                   placeholder="Numéro, objet, service ou demandeur…">
        </div>

        <div class="filtre-champ">
            <label class="visually-hidden" for="filtre-statut">Statut</label>
            <select id="filtre-statut" name="statut" class="form-control">
                <option value="">Tous les statuts</option>
                <?php foreach ($statuts as $valeur): ?>
                    <option value="<?= e($valeur) ?>"<?= $statut === $valeur ? ' selected' : '' ?>><?= e(Format::statutRequisition($valeur)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filtre-champ">
            <label class="visually-hidden" for="filtre-priorite">Priorité</label>
            <select id="filtre-priorite" name="priorite" class="form-control">
                <option value="">Toutes les priorités</option>
                <?php foreach ($priorites as $valeur): ?>
                    <option value="<?= e($valeur) ?>"<?= $priorite === $valeur ? ' selected' : '' ?>><?= e(Format::priorite($valeur)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn btn-outline"><i class="bi bi-funnel" aria-hidden="true"></i> Filtrer</button>
        <?php if ($recherche !== '' || $statut !== '' || $priorite !== ''): ?>
            <a class="btn btn-lien" href="<?= URL_BASE ?>/requisitions">Réinitialiser</a>
        <?php endif; ?>
    </form>

    <?php if ($requisitions === []): ?>
        <div class="panel-body">
            <?php
            $icone = 'bi-cart';
            $titre = 'Aucune réquisition';
            $message = $recherche !== '' || $statut !== '' || $priorite !== ''
                ? 'Aucune réquisition ne correspond à ces critères.'
                : 'Créez une première demande d\'équipement.';
            $action = Auth::peutSaisir() && $recherche === '' && $statut === '' && $priorite === ''
                ? '<a class="btn btn-primary" href="' . URL_BASE . '/requisitions/nouveau"><i class="bi bi-plus-lg"></i> Nouvelle réquisition</a>'
                : '';
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
                        <th scope="col">Demandeur</th>
                        <th scope="col">Priorité</th>
                        <th scope="col" class="cellule-nombre">Articles</th>
                        <th scope="col" class="cellule-nombre">Montant (HTG)</th>
                        <th scope="col">Statut</th>
                        <th scope="col" class="colonne-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requisitions as $requisition): ?>
                        <tr>
                            <td>
                                <a class="lien-fort" href="<?= URL_BASE ?>/requisitions/<?= (int)$requisition['id'] ?>">
                                    <?= e($requisition['numero_requisition']) ?>
                                </a>
                                <span class="cellule-secondaire"><?= Format::date($requisition['date_demande']) ?></span>
                            </td>
                            <td>
                                <?= e($requisition['objet']) ?>
                                <?php if (!empty($requisition['service_demandeur'])): ?>
                                    <span class="cellule-secondaire"><?= e($requisition['service_demandeur']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($requisition['demandeur']) ?></td>
                            <td><span class="badge-priorite badge-prio-<?= e($requisition['priorite']) ?>"><?= e(Format::priorite($requisition['priorite'])) ?></span></td>
                            <td class="cellule-nombre"><?= Format::nombre($requisition['nb_articles']) ?></td>
                            <td class="cellule-nombre"><?= Format::montant($requisition['montant_total'], false) ?></td>
                            <td><span class="badge-statut badge-req-<?= e($requisition['statut']) ?>"><?= e(Format::statutRequisition($requisition['statut'])) ?></span></td>
                            <td class="colonne-actions">
                                <a class="btn btn-outline btn-petit" href="<?= URL_BASE ?>/requisitions/<?= (int)$requisition['id'] ?>" title="Consulter">
                                    <i class="bi bi-eye" aria-hidden="true"></i><span class="visually-hidden">Consulter</span>
                                </a>
                                <?php if (Auth::peutSaisir() && (Auth::estAdmin() || $requisition['statut'] === 'en_attente')): ?>
                                    <a class="btn btn-outline btn-petit" href="<?= URL_BASE ?>/requisitions/<?= (int)$requisition['id'] ?>/modifier" title="Modifier">
                                        <i class="bi bi-pencil" aria-hidden="true"></i><span class="visually-hidden">Modifier</span>
                                    </a>
                                <?php endif; ?>
                                <?php if (Auth::estAdmin()): ?>
                                    <form method="POST" action="<?= URL_BASE ?>/requisitions/<?= (int)$requisition['id'] ?>/supprimer"
                                          data-confirmer="Supprimer définitivement la réquisition <?= e($requisition['numero_requisition']) ?> ?">
                                        <?= Csrf::champ() ?>
                                        <button type="submit" class="btn btn-danger btn-petit" title="Supprimer">
                                            <i class="bi bi-trash" aria-hidden="true"></i><span class="visually-hidden">Supprimer</span>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php $chemin = '/requisitions'; require RACINE_VIEWS . '/partials/pagination.php'; ?>
    <?php endif; ?>
</section>
