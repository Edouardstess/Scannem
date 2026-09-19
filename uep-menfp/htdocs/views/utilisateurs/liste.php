<?php
/**
 * Liste des comptes utilisateurs.
 * @var list<array<string,mixed>> $users
 * @var Paginator $pagination
 */
?>
<section class="panel">
    <header class="panel-header">
        <div>
            <h2 class="panel-title">Comptes utilisateurs</h2>
            <p class="panel-sous-titre"><?= Format::nombre($pagination->total) ?> compte(s)</p>
        </div>
        <a class="btn btn-primary" href="<?= URL_BASE ?>/utilisateurs/nouveau">
            <i class="bi bi-person-plus" aria-hidden="true"></i> Nouvel utilisateur
        </a>
    </header>

    <form class="barre-filtres" method="GET" action="<?= URL_BASE ?>/utilisateurs">
        <div class="filtre-recherche">
            <i class="bi bi-search" aria-hidden="true"></i>
            <label class="visually-hidden" for="filtre-q">Rechercher</label>
            <input type="search" id="filtre-q" name="q" value="<?= e($recherche) ?>" class="form-control"
                   placeholder="Nom, adresse électronique ou téléphone…">
        </div>
        <div class="filtre-champ">
            <label class="visually-hidden" for="filtre-role">Rôle</label>
            <select id="filtre-role" name="role" class="form-control">
                <option value="">Tous les rôles</option>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= e($r['nom_role']) ?>"<?= $role === $r['nom_role'] ? ' selected' : '' ?>><?= e($r['libelle']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-outline"><i class="bi bi-funnel" aria-hidden="true"></i> Filtrer</button>
        <?php if ($recherche !== '' || $role !== ''): ?>
            <a class="btn btn-lien" href="<?= URL_BASE ?>/utilisateurs">Réinitialiser</a>
        <?php endif; ?>
    </form>

    <?php if ($users === []): ?>
        <div class="panel-body">
            <?php
            $icone = 'bi-people';
            $titre = 'Aucun compte';
            $message = 'Aucun compte ne correspond à ces critères.';
            $action = '';
            require RACINE_VIEWS . '/partials/vide.php';
            ?>
        </div>
    <?php else: ?>
        <div class="table-defilante">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">Utilisateur</th>
                        <th scope="col">Rôle</th>
                        <th scope="col">Département</th>
                        <th scope="col">Dernière connexion</th>
                        <th scope="col">État</th>
                        <th scope="col" class="colonne-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr<?= (int)$user['actif'] === 1 ? '' : ' class="ligne-inactive"' ?>>
                            <td>
                                <strong><?= e($user['nom_complet']) ?></strong>
                                <span class="cellule-secondaire"><?= e($user['email']) ?></span>
                                <?php if (!empty($user['telephone'])): ?>
                                    <span class="cellule-secondaire"><?= e($user['telephone']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge-role badge-role-<?= e($user['nom_role']) ?>"><?= e($user['role_libelle']) ?></span></td>
                            <td><?= e(Departements::affichage($user['departement_rattachement'])) ?></td>
                            <td><?= Format::dateHeure($user['derniere_connexion']) ?></td>
                            <td>
                                <?php if ((int)$user['actif'] === 1): ?>
                                    <span class="badge-statut badge-valide">Actif</span>
                                <?php else: ?>
                                    <span class="badge-statut badge-rejete">Désactivé</span>
                                <?php endif; ?>
                                <?php if ((int)$user['doit_changer_mdp'] === 1): ?>
                                    <span class="badge-statut badge-soumis" title="Mot de passe à renouveler">MDP à changer</span>
                                <?php endif; ?>
                            </td>
                            <td class="colonne-actions">
                                <a class="btn btn-outline btn-petit" href="<?= URL_BASE ?>/utilisateurs/<?= (int)$user['id'] ?>/modifier" title="Modifier">
                                    <i class="bi bi-pencil" aria-hidden="true"></i><span class="visually-hidden">Modifier</span>
                                </a>
                                <?php if ((int)$user['id'] !== Auth::id()): ?>
                                    <form method="POST" action="<?= URL_BASE ?>/utilisateurs/<?= (int)$user['id'] ?>/supprimer"
                                          data-confirmer="Supprimer définitivement le compte de <?= e($user['nom_complet']) ?> ?">
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

        <?php $chemin = '/utilisateurs'; require RACINE_VIEWS . '/partials/pagination.php'; ?>
    <?php endif; ?>
</section>
