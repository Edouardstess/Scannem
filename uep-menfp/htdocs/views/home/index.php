<?php /** Page d'accueil publique. */ ?>
<section class="accueil-hero">
    <div class="accueil-hero-texte">
        <p class="accueil-kicker">République d'Haïti · Ministère de l'Éducation Nationale et de la Formation Professionnelle</p>
        <h1>Unité d'Études et de Programmation</h1>
        <p class="accueil-slogan"><?= e(SLOGAN_1) ?></p>
        <p class="accueil-intro">
            Plateforme institutionnelle de collecte, de consolidation et de suivi des données
            des Universités Publiques Départementales et des Directions Départementales
            d'Éducation, ainsi que des réquisitions du service informatique.
        </p>
        <div class="accueil-actions">
            <a class="btn btn-primary" href="<?= URL_BASE ?>/login">
                <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Accéder à la plateforme
            </a>
            <a class="btn btn-outline" href="#modules">
                <i class="bi bi-grid" aria-hidden="true"></i> Découvrir les modules
            </a>
        </div>
    </div>
    <div class="accueil-hero-visuel">
        <img src="<?= URL_BASE ?>/assets/img/illustration-uep.png" alt="">
    </div>
</section>

<section class="accueil-modules" id="modules">
    <h2>Les modules de la plateforme</h2>
    <div class="accueil-grille">
        <article class="accueil-carte">
            <i class="bi bi-building" aria-hidden="true"></i>
            <h3>Données UPD</h3>
            <p>
                Questionnaire complet des Universités Publiques Départementales, organisé en
                sections thématiques, avec suivi du taux de complétion et circuit de validation.
            </p>
        </article>
        <article class="accueil-carte">
            <i class="bi bi-diagram-3" aria-hidden="true"></i>
            <h3>Données DDE</h3>
            <p>
                Consolidation des informations des Directions Départementales d'Éducation
                pour les dix départements géographiques du pays.
            </p>
        </article>
        <article class="accueil-carte">
            <i class="bi bi-cart-check" aria-hidden="true"></i>
            <h3>Réquisitions</h3>
            <p>
                Demandes d'équipement informatique, électrique et de solutions logicielles :
                saisie, chiffrage, approbation et bon imprimable.
            </p>
        </article>
        <article class="accueil-carte">
            <i class="bi bi-shield-lock" aria-hidden="true"></i>
            <h3>Sécurité et traçabilité</h3>
            <p>
                Comptes nominatifs, rôles différenciés, protection contre les tentatives
                d'intrusion et journal d'activités consultable par l'administration.
            </p>
        </article>
    </div>
</section>

<section class="accueil-mission">
    <blockquote>
        <p><?= e(SLOGAN_2) ?></p>
    </blockquote>
</section>
