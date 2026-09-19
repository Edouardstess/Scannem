<?php
/**
 * Création et modification d'un compte.
 * @var string $mode
 * @var array<string,mixed> $user
 * @var list<array<string,mixed>> $roles
 * @var array<string,string> $departements
 * @var array<string,string> $erreurs
 */
$creation = $mode === 'creation';
$action = $creation
    ? URL_BASE . '/utilisateurs/creer'
    : URL_BASE . '/utilisateurs/' . (int)$user['id'] . '/mettre-a-jour';
?>
<div class="dossier-entete">
    <div>
        <p class="dossier-kicker">Administration des comptes</p>
        <h2><?= $creation ? 'Créer un utilisateur' : e($user['nom_complet']) ?></h2>
    </div>
    <a class="btn btn-outline" href="<?= URL_BASE ?>/utilisateurs">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> Retour à la liste
    </a>
</div>

<form method="POST" action="<?= e($action) ?>" novalidate>
    <?= Csrf::champ() ?>

    <section class="panel">
        <header class="panel-header"><h3 class="panel-title">Identité</h3></header>
        <div class="panel-body grille-champs">
            <div class="champ">
                <label for="nom_complet">Nom complet <span class="obligatoire">*</span></label>
                <input type="text" id="nom_complet" name="nom_complet" maxlength="150" required
                       class="form-control<?= isset($erreurs['nom_complet']) ? ' is-invalid' : '' ?>"
                       value="<?= e($user['nom_complet'] ?? '') ?>">
                <?php if (isset($erreurs['nom_complet'])): ?><p class="champ-erreur"><?= e($erreurs['nom_complet']) ?></p><?php endif; ?>
            </div>

            <div class="champ">
                <label for="email">Adresse électronique <span class="obligatoire">*</span></label>
                <input type="email" id="email" name="email" maxlength="150" required
                       class="form-control<?= isset($erreurs['email']) ? ' is-invalid' : '' ?>"
                       value="<?= e($user['email'] ?? '') ?>">
                <?php if (isset($erreurs['email'])): ?><p class="champ-erreur"><?= e($erreurs['email']) ?></p><?php endif; ?>
            </div>

            <div class="champ">
                <label for="telephone">Téléphone</label>
                <input type="tel" id="telephone" name="telephone" maxlength="30" class="form-control"
                       value="<?= e($user['telephone'] ?? '') ?>" placeholder="+509 0000 0000">
            </div>

            <div class="champ">
                <label for="departement_rattachement">Département de rattachement</label>
                <select id="departement_rattachement" name="departement_rattachement"
                        class="form-control<?= isset($erreurs['departement_rattachement']) ? ' is-invalid' : '' ?>">
                    <option value="">— Aucun —</option>
                    <?php foreach ($departements as $code => $libelle): ?>
                        <option value="<?= e($code) ?>"<?= ($user['departement_rattachement'] ?? '') === $code ? ' selected' : '' ?>><?= e($libelle) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($erreurs['departement_rattachement'])): ?><p class="champ-erreur"><?= e($erreurs['departement_rattachement']) ?></p><?php endif; ?>
            </div>
        </div>
    </section>

    <section class="panel">
        <header class="panel-header"><h3 class="panel-title">Rôle et accès</h3></header>
        <div class="panel-body">
            <?php if (isset($erreurs['role_id'])): ?>
                <p class="champ-erreur"><?= e($erreurs['role_id']) ?></p>
            <?php endif; ?>

            <div class="choix-roles">
                <?php foreach ($roles as $r): ?>
                    <label class="choix-role">
                        <input type="radio" name="role_id" value="<?= (int)$r['id'] ?>"
                               <?= (int)($user['role_id'] ?? 0) === (int)$r['id'] ? 'checked' : '' ?> required>
                        <span>
                            <strong><?= e($r['libelle']) ?></strong>
                            <small><?= e($r['description'] ?? '') ?></small>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="champs-cases">
                <label class="case">
                    <input type="checkbox" name="actif" value="1" <?= (int)($user['actif'] ?? 1) === 1 ? 'checked' : '' ?>>
                    <span>Compte actif <small>Un compte désactivé ne peut plus se connecter.</small></span>
                </label>
                <label class="case">
                    <input type="checkbox" name="doit_changer_mdp" value="1" <?= (int)($user['doit_changer_mdp'] ?? 1) === 1 ? 'checked' : '' ?>>
                    <span>Exiger un changement de mot de passe à la prochaine connexion</span>
                </label>
            </div>
        </div>
    </section>

    <section class="panel">
        <header class="panel-header">
            <h3 class="panel-title"><?= $creation ? 'Mot de passe initial' : 'Réinitialiser le mot de passe' ?></h3>
        </header>
        <div class="panel-body">
            <div class="champ champ-large">
                <label for="<?= $creation ? 'mot_de_passe' : 'nouveau_mot_de_passe' ?>">
                    <?= $creation ? 'Mot de passe' : 'Nouveau mot de passe' ?>
                    <?php if ($creation): ?><span class="obligatoire">*</span><?php endif; ?>
                </label>
                <div class="champ-icone">
                    <i class="bi bi-key" aria-hidden="true"></i>
                    <input type="password"
                           id="<?= $creation ? 'mot_de_passe' : 'nouveau_mot_de_passe' ?>"
                           name="<?= $creation ? 'mot_de_passe' : 'nouveau_mot_de_passe' ?>"
                           class="form-control<?= isset($erreurs['mot_de_passe']) || isset($erreurs['nouveau_mot_de_passe']) ? ' is-invalid' : '' ?>"
                           autocomplete="new-password" data-jauge-mdp
                           <?= $creation ? 'required minlength="' . LONGUEUR_MDP_MIN . '"' : '' ?>>
                    <button type="button" class="champ-oeil" data-bascule-mdp="<?= $creation ? 'mot_de_passe' : 'nouveau_mot_de_passe' ?>"
                            aria-label="Afficher le mot de passe">
                        <i class="bi bi-eye" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="jauge-mdp" data-jauge-cible hidden>
                    <div class="jauge-mdp-barre"><span></span></div>
                    <p class="jauge-mdp-texte"></p>
                </div>
                <p class="champ-aide">
                    Au moins <?= LONGUEUR_MDP_MIN ?> caractères, dont une minuscule, une majuscule et un chiffre.
                    <?= $creation ? '' : ' Laissez vide pour conserver le mot de passe actuel.' ?>
                </p>
                <?php foreach (['mot_de_passe', 'nouveau_mot_de_passe'] as $champ): ?>
                    <?php if (isset($erreurs[$champ])): ?><p class="champ-erreur"><?= e($erreurs[$champ]) ?></p><?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <div class="actions-formulaire">
        <a class="btn btn-outline" href="<?= URL_BASE ?>/utilisateurs">Annuler</a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check2-circle" aria-hidden="true"></i>
            <?= $creation ? 'Créer le compte' : 'Enregistrer les modifications' ?>
        </button>
    </div>
</form>
