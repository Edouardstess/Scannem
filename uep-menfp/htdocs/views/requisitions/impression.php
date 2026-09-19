<?php
/** Bon de réquisition imprimable. */
$statut = (string)$requisition['statut'];
?>
<div class="feuille">
    <header class="feuille-entete">
        <img src="<?= URL_BASE ?>/assets/img/logo-uep.png" alt="" class="feuille-logo">
        <div class="feuille-institution">
            <p class="feuille-republique">République d'Haïti</p>
            <p class="feuille-ministere">Ministère de l'Éducation Nationale et de la Formation Professionnelle</p>
            <p class="feuille-unite">Unité d'Études et de Programmation — Service informatique</p>
        </div>
        <div class="feuille-reference">
            <p class="feuille-numero"><?= e($requisition['numero_requisition']) ?></p>
            <p><?= Format::date($requisition['date_demande']) ?></p>
        </div>
    </header>

    <h1 class="feuille-titre">Bon de réquisition</h1>

    <table class="feuille-infos">
        <tbody>
            <tr><th scope="row">Objet</th><td><?= e($requisition['objet']) ?></td></tr>
            <tr><th scope="row">Demandeur</th><td><?= e($requisition['demandeur']) ?></td></tr>
            <tr><th scope="row">Service demandeur</th><td><?= e($requisition['service_demandeur'] ?: '—') ?></td></tr>
            <tr><th scope="row">Priorité</th><td><?= e(Format::priorite($requisition['priorite'])) ?></td></tr>
            <tr><th scope="row">Adresse de livraison</th><td><?= e($requisition['adresse_livraison'] ?: '—') ?></td></tr>
            <tr><th scope="row">Statut</th><td><?= e(Format::statutRequisition($statut)) ?></td></tr>
        </tbody>
    </table>

    <?php if (!empty($requisition['justification'])): ?>
        <section class="feuille-bloc">
            <h2>Justification</h2>
            <p><?= nl2br(e($requisition['justification'])) ?></p>
        </section>
    <?php endif; ?>

    <table class="feuille-articles">
        <thead>
            <tr>
                <th scope="col">#</th>
                <th scope="col">Désignation</th>
                <th scope="col">Catégorie</th>
                <th scope="col">Unité</th>
                <th scope="col" class="cellule-nombre">Qté</th>
                <th scope="col" class="cellule-nombre">Prix unitaire</th>
                <th scope="col" class="cellule-nombre">Montant</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($articles as $index => $article): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
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
                <th colspan="6" class="cellule-nombre">Total estimé</th>
                <th class="cellule-nombre"><?= Format::montant($montantTotal) ?></th>
            </tr>
        </tfoot>
    </table>

    <?php if (!empty($requisition['observations'])): ?>
        <section class="feuille-bloc">
            <h2>Observations</h2>
            <p><?= nl2br(e($requisition['observations'])) ?></p>
        </section>
    <?php endif; ?>

    <section class="feuille-signatures">
        <div><p class="feuille-ligne-signature"></p><p>Le demandeur</p></div>
        <div><p class="feuille-ligne-signature"></p><p>Le responsable du service informatique</p></div>
        <div><p class="feuille-ligne-signature"></p><p>La coordination de l'UEP</p></div>
    </section>

    <footer class="feuille-pied">
        <p><?= e(CONTACT_ADRESSE) ?> · <?= e(CONTACT_TEL) ?> · <?= e(CONTACT_WEB) ?></p>
        <p>Document généré le <?= date('d/m/Y à H:i') ?> par <?= e(Auth::nom()) ?>.</p>
    </footer>

    <div class="feuille-actions no-print">
        <button type="button" class="btn btn-primary" data-imprimer>
            <i class="bi bi-printer" aria-hidden="true"></i> Imprimer
        </button>
        <a class="btn btn-outline" href="<?= URL_BASE ?>/requisitions/<?= (int)$requisition['id'] ?>">Retour à la fiche</a>
    </div>
</div>
