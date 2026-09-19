<?php
/**
 * Journal d'audit.
 * @var list<array<string,mixed>> $activites
 * @var Paginator $pagination
 */
?>
<section class="panel">
    <header class="panel-header">
        <div>
            <h2 class="panel-title">Journal d'activités</h2>
            <p class="panel-sous-titre"><?= Format::nombre($pagination->total) ?> entrée(s)</p>
        </div>
    </header>

    <form class="barre-filtres" method="GET" action="<?= URL_BASE ?>/journal">
        <div class="filtre-recherche">
            <i class="bi bi-search" aria-hidden="true"></i>
            <label class="visually-hidden" for="filtre-q">Rechercher</label>
            <input type="search" id="filtre-q" name="q" value="<?= e($recherche) ?>" class="form-control"
                   placeholder="Action, table ou détail…">
        </div>
        <div class="filtre-champ">
            <label class="visually-hidden" for="filtre-utilisateur">Utilisateur</label>
            <select id="filtre-utilisateur" name="utilisateur" class="form-control">
                <option value="">Tous les utilisateurs</option>
                <?php foreach ($utilisateurs as $u): ?>
                    <option value="<?= (int)$u['id'] ?>"<?= $utilisateurId === (int)$u['id'] ? ' selected' : '' ?>><?= e($u['nom_complet']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-outline"><i class="bi bi-funnel" aria-hidden="true"></i> Filtrer</button>
        <?php if ($recherche !== '' || $utilisateurId > 0): ?>
            <a class="btn btn-lien" href="<?= URL_BASE ?>/journal">Réinitialiser</a>
        <?php endif; ?>
    </form>

    <?php if ($activites === []): ?>
        <div class="panel-body">
            <?php
            $icone = 'bi-journal-text';
            $titre = 'Journal vide';
            $message = 'Aucune activité ne correspond à ces critères.';
            $action = '';
            require RACINE_VIEWS . '/partials/vide.php';
            ?>
        </div>
    <?php else: ?>
        <div class="table-defilante">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Utilisateur</th>
                        <th scope="col">Action</th>
                        <th scope="col">Objet</th>
                        <th scope="col">Détail</th>
                        <th scope="col">Adresse IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activites as $activite): ?>
                        <tr>
                            <td><?= Format::dateHeure($activite['created_at']) ?></td>
                            <td><?= e($activite['nom_complet'] ?: 'Compte supprimé') ?></td>
                            <td><code class="code-action"><?= e($activite['action']) ?></code></td>
                            <td>
                                <?= e($activite['table_concernee'] ?: '—') ?>
                                <?php if (!empty($activite['enregistrement_id'])): ?>
                                    <span class="cellule-secondaire">#<?= (int)$activite['enregistrement_id'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($activite['details'] ?: '—') ?></td>
                            <td><code><?= e($activite['adresse_ip']) ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php $chemin = '/journal'; require RACINE_VIEWS . '/partials/pagination.php'; ?>
    <?php endif; ?>
</section>
