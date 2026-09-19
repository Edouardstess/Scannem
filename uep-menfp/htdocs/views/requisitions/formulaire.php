<?php
/**
 * Création et modification d'une réquisition (formulaire unique).
 * @var string $mode  « creation » ou « modification »
 * @var array<string,mixed> $requisition
 * @var list<array<string,mixed>> $articles
 * @var list<array<string,mixed>> $categories
 * @var array<string,string> $erreurs
 */
$creation = $mode === 'creation';
$action = $creation
    ? URL_BASE . '/requisitions/creer'
    : URL_BASE . '/requisitions/' . (int)$requisition['id'] . '/mettre-a-jour';

// Lignes affichées : celles postées ou enregistrées, sinon une ligne vierge.
$lignes = $articles;
if ($lignes === []) {
    $lignes = [['categorie_id' => 0, 'designation' => '', 'unite_mesure' => 'Unité', 'quantite' => 1, 'prix_unitaire_estime' => '']];
}
?>
<form method="POST" action="<?= e($action) ?>" id="formulaireRequisition" novalidate>
    <?= Csrf::champ() ?>

    <div class="dossier-entete">
        <div>
            <p class="dossier-kicker"><?= $creation ? 'Nouvelle demande' : 'Modification' ?></p>
            <h2><?= e($numero) ?></h2>
            <p class="dossier-meta">
                <span><i class="bi bi-person" aria-hidden="true"></i> <?= e($creation ? Auth::nom() : (string)($requisition['demandeur'] ?? Auth::nom())) ?></span>
                <span><i class="bi bi-calendar3" aria-hidden="true"></i> <?= Format::date($requisition['date_demande'] ?? date('Y-m-d')) ?></span>
            </p>
        </div>
        <a class="btn btn-outline" href="<?= URL_BASE ?>/requisitions<?= $creation ? '' : '/' . (int)$requisition['id'] ?>">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> Annuler
        </a>
    </div>

    <?php if ($erreurs !== []): ?>
        <div class="alerte alerte-erreur" role="alert">
            <i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i>
            <span>
                Le formulaire contient <?= count($erreurs) ?> erreur(s) :
                <ul class="alerte-liste">
                    <?php foreach ($erreurs as $message): ?><li><?= e($message) ?></li><?php endforeach; ?>
                </ul>
            </span>
        </div>
    <?php endif; ?>

    <section class="panel">
        <header class="panel-header"><h3 class="panel-title">Informations générales</h3></header>
        <div class="panel-body grille-champs">
            <div class="champ champ-large">
                <label for="objet">Objet de la réquisition <span class="obligatoire">*</span></label>
                <input type="text" id="objet" name="objet" maxlength="255" required
                       class="form-control<?= isset($erreurs['objet']) ? ' is-invalid' : '' ?>"
                       value="<?= e($requisition['objet'] ?? '') ?>"
                       placeholder="Ex. : renouvellement du parc informatique de la direction">
                <?php if (isset($erreurs['objet'])): ?><p class="champ-erreur"><?= e($erreurs['objet']) ?></p><?php endif; ?>
            </div>

            <div class="champ">
                <label for="priorite">Priorité <span class="obligatoire">*</span></label>
                <select id="priorite" name="priorite" class="form-control<?= isset($erreurs['priorite']) ? ' is-invalid' : '' ?>" required>
                    <?php foreach ($priorites as $valeur): ?>
                        <option value="<?= e($valeur) ?>"<?= ($requisition['priorite'] ?? 'normale') === $valeur ? ' selected' : '' ?>>
                            <?= e(Format::priorite($valeur)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($erreurs['priorite'])): ?><p class="champ-erreur"><?= e($erreurs['priorite']) ?></p><?php endif; ?>
            </div>

            <div class="champ">
                <label for="service_demandeur">Service demandeur</label>
                <input type="text" id="service_demandeur" name="service_demandeur" maxlength="150"
                       class="form-control" value="<?= e($requisition['service_demandeur'] ?? '') ?>">
            </div>

            <div class="champ champ-large">
                <label for="adresse_livraison">Adresse de livraison</label>
                <input type="text" id="adresse_livraison" name="adresse_livraison" maxlength="255"
                       class="form-control" value="<?= e($requisition['adresse_livraison'] ?? '') ?>">
            </div>

            <div class="champ champ-large">
                <label for="justification">Justification de la demande</label>
                <textarea id="justification" name="justification" rows="3" class="form-control"
                          maxlength="5000"><?= e($requisition['justification'] ?? '') ?></textarea>
            </div>

            <div class="champ champ-large">
                <label for="observations">Observations</label>
                <textarea id="observations" name="observations" rows="2" class="form-control"
                          maxlength="5000"><?= e($requisition['observations'] ?? '') ?></textarea>
            </div>
        </div>
    </section>

    <section class="panel">
        <header class="panel-header">
            <div>
                <h3 class="panel-title">Articles demandés</h3>
                <p class="panel-sous-titre">Les montants sont calculés automatiquement.</p>
            </div>
            <button type="button" class="btn btn-outline btn-petit" data-ajouter-article>
                <i class="bi bi-plus-lg" aria-hidden="true"></i> Ajouter une ligne
            </button>
        </header>

        <?php if (isset($erreurs['articles'])): ?>
            <div class="panel-body"><p class="champ-erreur"><?= e($erreurs['articles']) ?></p></div>
        <?php endif; ?>

        <div class="table-defilante">
            <table class="data-table table-articles" id="tableArticles">
                <thead>
                    <tr>
                        <th scope="col" class="colonne-designation">Désignation <span class="obligatoire">*</span></th>
                        <th scope="col">Catégorie</th>
                        <th scope="col">Unité</th>
                        <th scope="col" class="cellule-nombre">Quantité</th>
                        <th scope="col" class="cellule-nombre">Prix unitaire</th>
                        <th scope="col" class="cellule-nombre">Montant</th>
                        <th scope="col"><span class="visually-hidden">Retirer</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lignes as $ligne): ?>
                        <tr class="ligne-article">
                            <td>
                                <input type="text" name="designation[]" maxlength="255" class="form-control"
                                       value="<?= e($ligne['designation'] ?? '') ?>" placeholder="Ex. : ordinateur portable 16 Go">
                            </td>
                            <td>
                                <select name="categorie_id[]" class="form-control">
                                    <?php foreach ($categories as $categorie): ?>
                                        <option value="<?= (int)$categorie['id'] ?>"<?= (int)($ligne['categorie_id'] ?? 0) === (int)$categorie['id'] ? ' selected' : '' ?>>
                                            <?= e($categorie['nom']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input type="text" name="unite_mesure[]" maxlength="30" class="form-control"
                                       value="<?= e($ligne['unite_mesure'] ?? 'Unité') ?>">
                            </td>
                            <td class="cellule-nombre">
                                <input type="number" name="quantite[]" min="1" step="1" class="form-control" data-quantite
                                       value="<?= e((string)($ligne['quantite'] ?? 1)) ?>">
                            </td>
                            <td class="cellule-nombre">
                                <input type="number" name="prix_unitaire[]" min="0" step="0.01" class="form-control" data-prix
                                       value="<?= e((string)($ligne['prix_unitaire_estime'] ?? $ligne['prix_unitaire'] ?? '')) ?>">
                            </td>
                            <td class="cellule-nombre"><output data-montant-ligne>0,00</output></td>
                            <td>
                                <button type="button" class="btn btn-danger btn-petit" data-retirer-article title="Retirer la ligne">
                                    <i class="bi bi-x-lg" aria-hidden="true"></i><span class="visually-hidden">Retirer</span>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="5" class="cellule-nombre">Total estimé</th>
                        <th class="cellule-nombre"><output data-montant-total>0,00</output> HTG</th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>

    <div class="actions-formulaire">
        <a class="btn btn-outline" href="<?= URL_BASE ?>/requisitions<?= $creation ? '' : '/' . (int)$requisition['id'] ?>">Annuler</a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check2-circle" aria-hidden="true"></i>
            <?= $creation ? 'Créer la réquisition' : 'Enregistrer les modifications' ?>
        </button>
    </div>
</form>

<template id="modeleLigneArticle">
    <tr class="ligne-article">
        <td><input type="text" name="designation[]" maxlength="255" class="form-control" placeholder="Désignation de l'article"></td>
        <td>
            <select name="categorie_id[]" class="form-control">
                <?php foreach ($categories as $categorie): ?>
                    <option value="<?= (int)$categorie['id'] ?>"><?= e($categorie['nom']) ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td><input type="text" name="unite_mesure[]" maxlength="30" class="form-control" value="Unité"></td>
        <td class="cellule-nombre"><input type="number" name="quantite[]" min="1" step="1" class="form-control" data-quantite value="1"></td>
        <td class="cellule-nombre"><input type="number" name="prix_unitaire[]" min="0" step="0.01" class="form-control" data-prix value=""></td>
        <td class="cellule-nombre"><output data-montant-ligne>0,00</output></td>
        <td>
            <button type="button" class="btn btn-danger btn-petit" data-retirer-article title="Retirer la ligne">
                <i class="bi bi-x-lg" aria-hidden="true"></i><span class="visually-hidden">Retirer</span>
            </button>
        </td>
    </tr>
</template>
