<?php
/**
 * Changement du mot de passe.
 * @var array<string, string> $erreurs
 * @var bool $impose
 */
?>
<div class="auth-ecran auth-ecran-simple">
    <section class="auth-panneau">
        <div class="auth-carte">
            <h2><?= $impose ? 'Définissez votre mot de passe' : 'Changer mon mot de passe' ?></h2>

            <?php if ($impose): ?>
                <div class="alerte alerte-avertissement" role="alert">
                    <i class="bi bi-shield-exclamation" aria-hidden="true"></i>
                    <span>Votre mot de passe a été défini par un administrateur. Choisissez-en un nouveau avant d'accéder à l'application.</span>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= URL_BASE ?>/mot-de-passe/changer" novalidate>
                <?= Csrf::champ() ?>

                <div class="champ">
                    <label for="ancien_mot_de_passe">Mot de passe actuel</label>
                    <div class="champ-icone">
                        <i class="bi bi-lock" aria-hidden="true"></i>
                        <input type="password" id="ancien_mot_de_passe" name="ancien_mot_de_passe"
                               class="form-control<?= isset($erreurs['ancien_mot_de_passe']) ? ' is-invalid' : '' ?>"
                               autocomplete="current-password" required autofocus>
                        <button type="button" class="champ-oeil" data-bascule-mdp="ancien_mot_de_passe" aria-label="Afficher le mot de passe">
                            <i class="bi bi-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                    <?php if (isset($erreurs['ancien_mot_de_passe'])): ?>
                        <p class="champ-erreur"><?= e($erreurs['ancien_mot_de_passe']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="champ">
                    <label for="nouveau_mot_de_passe">Nouveau mot de passe</label>
                    <div class="champ-icone">
                        <i class="bi bi-key" aria-hidden="true"></i>
                        <input type="password" id="nouveau_mot_de_passe" name="nouveau_mot_de_passe"
                               class="form-control<?= isset($erreurs['nouveau_mot_de_passe']) ? ' is-invalid' : '' ?>"
                               autocomplete="new-password" required minlength="<?= LONGUEUR_MDP_MIN ?>"
                               data-jauge-mdp>
                        <button type="button" class="champ-oeil" data-bascule-mdp="nouveau_mot_de_passe" aria-label="Afficher le mot de passe">
                            <i class="bi bi-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="jauge-mdp" data-jauge-cible hidden>
                        <div class="jauge-mdp-barre"><span></span></div>
                        <p class="jauge-mdp-texte"></p>
                    </div>
                    <p class="champ-aide">
                        Au moins <?= LONGUEUR_MDP_MIN ?> caractères, dont une minuscule, une majuscule et un chiffre.
                    </p>
                    <?php if (isset($erreurs['nouveau_mot_de_passe'])): ?>
                        <p class="champ-erreur"><?= e($erreurs['nouveau_mot_de_passe']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="champ">
                    <label for="confirmation">Confirmation</label>
                    <div class="champ-icone">
                        <i class="bi bi-key-fill" aria-hidden="true"></i>
                        <input type="password" id="confirmation" name="confirmation"
                               class="form-control<?= isset($erreurs['confirmation']) ? ' is-invalid' : '' ?>"
                               autocomplete="new-password" required>
                    </div>
                    <?php if (isset($erreurs['confirmation'])): ?>
                        <p class="champ-erreur"><?= e($erreurs['confirmation']) ?></p>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary btn-bloc">
                    <i class="bi bi-check2-circle" aria-hidden="true"></i> Enregistrer le nouveau mot de passe
                </button>
            </form>

            <?php if (!$impose): ?>
                <p class="auth-aide">
                    <a href="<?= URL_BASE ?>/dashboard"><i class="bi bi-arrow-left" aria-hidden="true"></i> Retour au tableau de bord</a>
                </p>
            <?php endif; ?>
        </div>
    </section>
</div>
