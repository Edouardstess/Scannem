<?php
/**
 * Page de connexion.
 * @var array<string, string> $erreurs
 * @var string $email
 */
?>
<div class="auth-ecran">
    <section class="auth-presentation">
        <img class="auth-embleme" src="<?= URL_BASE ?>/assets/img/logo-uep.png" alt="">
        <p class="auth-kicker">République d'Haïti · MENFP</p>
        <h1>Unité d'Études<br>et de Programmation</h1>
        <p class="auth-baseline"><?= e(SLOGAN_1) ?></p>
        <ul class="auth-points">
            <li><i class="bi bi-building" aria-hidden="true"></i> Données consolidées des UPD</li>
            <li><i class="bi bi-diagram-3" aria-hidden="true"></i> Données consolidées des DDE</li>
            <li><i class="bi bi-cart-check" aria-hidden="true"></i> Réquisitions du service informatique</li>
        </ul>
    </section>

    <section class="auth-panneau">
        <div class="auth-carte">
            <h2>Connexion</h2>
            <p class="auth-sous-titre">Accès réservé aux agents habilités du Ministère.</p>

            <?php if (isset($erreurs['general'])): ?>
                <div class="alerte alerte-erreur" role="alert">
                    <i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i>
                    <span><?= e($erreurs['general']) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= URL_BASE ?>/login" novalidate>
                <?= Csrf::champ() ?>

                <div class="champ">
                    <label for="email">Adresse électronique</label>
                    <div class="champ-icone">
                        <i class="bi bi-envelope" aria-hidden="true"></i>
                        <input type="email" id="email" name="email" value="<?= e($email ?? '') ?>"
                               class="form-control<?= isset($erreurs['email']) ? ' is-invalid' : '' ?>"
                               autocomplete="username" required autofocus
                               placeholder="prenom.nom@menfp.gouv.ht">
                    </div>
                    <?php if (isset($erreurs['email'])): ?>
                        <p class="champ-erreur"><?= e($erreurs['email']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="champ">
                    <label for="mot_de_passe">Mot de passe</label>
                    <div class="champ-icone">
                        <i class="bi bi-lock" aria-hidden="true"></i>
                        <input type="password" id="mot_de_passe" name="mot_de_passe"
                               class="form-control<?= isset($erreurs['mot_de_passe']) ? ' is-invalid' : '' ?>"
                               autocomplete="current-password" required>
                        <button type="button" class="champ-oeil" data-bascule-mdp="mot_de_passe"
                                aria-label="Afficher le mot de passe">
                            <i class="bi bi-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                    <?php if (isset($erreurs['mot_de_passe'])): ?>
                        <p class="champ-erreur"><?= e($erreurs['mot_de_passe']) ?></p>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary btn-bloc">
                    <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Se connecter
                </button>
            </form>

            <p class="auth-aide">
                <i class="bi bi-info-circle" aria-hidden="true"></i>
                Mot de passe oublié ou compte bloqué : contactez l'administrateur de la plateforme
                au <?= e(CONTACT_TEL) ?>.
            </p>
        </div>
    </section>
</div>
