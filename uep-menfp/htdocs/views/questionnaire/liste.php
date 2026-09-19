<?php
/**
 * Liste des dossiers d'un questionnaire (UPD ou DDE).
 * @var string $base  « upd » ou « dde »
 * @var list<array<string,mixed>> $dossiers
 * @var Paginator $pagination
 */
$peutCreer = Auth::peutSaisir();
?>
<section class="panel">
    <header class="panel-header">
        <div>
            <h2 class="panel-title"><?= e($intitule) ?></h2>
            <p class="panel-sous-titre"><?= Format::nombre($pagination->total) ?> dossier(s) enregistré(s)</p>
        </div>
        <?php if ($peutCreer): ?>
            <form method="POST" action="<?= URL_BASE ?>/<?= e($base) ?>/nouveau">
                <?= Csrf::champ() ?>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-plus-lg" aria-hidden="true"></i> Nouveau dossier <?= e($sigle) ?>
                </button>
            </form>
        <?php endif; ?>
    </header>

    <form class="barre-filtres" method="GET" action="<?= URL_BASE ?>/<?= e($base) ?>">
        <div class="filtre-recherche">
            <i class="bi bi-search" aria-hidden="true"></i>
            <label class="visually-hidden" for="filtre-q">Rechercher</label>
            <input type="search" id="filtre-q" name="q" value="<?= e($recherche) ?>"
                   class="form-control" placeholder="Nom du dossier ou département…">
        </div>

        <div class="filtre-champ">
            <label class="visually-hidden" for="filtre-statut">Statut</label>
            <select id="filtre-statut" name="statut" class="form-control">
                <option value="">Tous les statuts</option>
                <?php foreach (Workflow::STATUTS as $valeur): ?>
                    <option value="<?= e($valeur) ?>"<?= $statut === $valeur ? ' selected' : '' ?>>
                        <?= e(Workflow::libelleCourt($valeur)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filtre-champ">
            <label class="visually-hidden" for="filtre-departement">Département</label>
            <select id="filtre-departement" name="departement" class="form-control">
                <option value="">Tous les départements</option>
                <?php foreach (Departements::tous() as $code => $libelle): ?>
                    <option value="<?= e($code) ?>"<?= $departement === $code ? ' selected' : '' ?>><?= e($libelle) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn btn-outline"><i class="bi bi-funnel" aria-hidden="true"></i> Filtrer</button>
        <?php if ($recherche !== '' || $statut !== '' || $departement !== ''): ?>
            <a class="btn btn-lien" href="<?= URL_BASE ?>/<?= e($base) ?>">Réinitialiser</a>
        <?php endif; ?>
    </form>

    <?php if ($dossiers === []): ?>
        <div class="panel-body">
            <?php
            $icone = 'bi-folder2-open';
            $titre = 'Aucun dossier ' . $sigle;
            $message = $recherche !== '' || $statut !== '' || $departement !== ''
                ? 'Aucun dossier ne correspond à ces critères.'
                : 'Créez un premier dossier pour commencer la saisie.';
            $action = $peutCreer && $recherche === '' && $statut === '' && $departement === ''
                ? '<form method="POST" action="' . URL_BASE . '/' . e($base) . '/nouveau">' . Csrf::champ()
                  . '<button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Créer le premier dossier</button></form>'
                : '';
            require RACINE_VIEWS . '/partials/vide.php';
            ?>
        </div>
    <?php else: ?>
        <div class="table-defilante">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">Dossier</th>
                        <th scope="col">Département</th>
                        <th scope="col">Complétion</th>
                        <th scope="col">Statut</th>
                        <th scope="col">Dernière modification</th>
                        <th scope="col" class="colonne-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dossiers as $dossier): ?>
                        <?php
                        $pourcentage = Format::pourcentage((int)$dossier['questions_repondues'], (int)$dossier['total_questions']);
                        $classeBarre = $pourcentage >= 75 ? ' bg-vert' : ($pourcentage >= 40 ? '' : ' bg-rouge');
                        $nom = trim((string)($dossier['nom'] ?? ''));
                        ?>
                        <tr>
                            <td>
                                <a class="lien-fort" href="<?= URL_BASE ?>/<?= e($base) ?>/<?= (int)$dossier['id'] ?>">
                                    <?= e($nom !== '' ? $nom : $sigle . ' #' . (int)$dossier['id']) ?>
                                </a>
                                <span class="cellule-secondaire">Réf. <?= e($sigle) ?>-<?= str_pad((string)(int)$dossier['id'], 4, '0', STR_PAD_LEFT) ?></span>
                            </td>
                            <td><?= e(Departements::affichage($dossier['departement'])) ?></td>
                            <td class="cellule-completion">
                                <div class="progress-bar-container">
                                    <div class="progress-bar-fill<?= $classeBarre ?>" style="width: <?= $pourcentage ?>%"></div>
                                </div>
                                <span><?= $pourcentage ?> %</span>
                                <span class="cellule-secondaire">
                                    <?= Format::nombre($dossier['questions_repondues']) ?> / <?= Format::nombre($dossier['total_questions']) ?>
                                </span>
                            </td>
                            <td><span class="<?= e(Workflow::classeBadge($dossier['statut_validation'])) ?>"><?= e(Workflow::libelleCourt($dossier['statut_validation'])) ?></span></td>
                            <td><?= Format::dateHeure($dossier['updated_at']) ?></td>
                            <td class="colonne-actions">
                                <a class="btn btn-outline btn-petit" href="<?= URL_BASE ?>/<?= e($base) ?>/<?= (int)$dossier['id'] ?>">
                                    <i class="bi bi-<?= Workflow::peutModifier($dossier['statut_validation'], Auth::role()) ? 'pencil' : 'eye' ?>" aria-hidden="true"></i>
                                    <?= Workflow::peutModifier($dossier['statut_validation'], Auth::role()) ? 'Saisir' : 'Consulter' ?>
                                </a>
                                <?php if (Auth::estAdmin()): ?>
                                    <form method="POST" action="<?= URL_BASE ?>/<?= e($base) ?>/<?= (int)$dossier['id'] ?>/supprimer"
                                          data-confirmer="Supprimer définitivement ce dossier et toutes ses réponses ?">
                                        <?= Csrf::champ() ?>
                                        <button type="submit" class="btn btn-danger btn-petit" title="Supprimer">
                                            <i class="bi bi-trash" aria-hidden="true"></i>
                                            <span class="visually-hidden">Supprimer</span>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php $chemin = '/' . $base; require RACINE_VIEWS . '/partials/pagination.php'; ?>
    <?php endif; ?>
</section>
