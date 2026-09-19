/**
 * UEP / MENFP — Comportements de l'interface.
 *
 * Aucun gestionnaire d'événement n'est écrit dans le HTML : tout est attaché
 * ici, ce qui permet une politique de sécurité de contenu stricte.
 */
(function () {
    'use strict';

    var racine = document;

    /* ------------------------------------------------------- Menu latéral */
    function initialiserMenu() {
        var menu = racine.getElementById('sidebar');
        var voile = racine.querySelector('.app-overlay');
        var bouton = racine.querySelector('[data-basculer-menu]');
        if (!menu || !bouton) { return; }

        function basculer(ouvrir) {
            menu.classList.toggle('is-open', ouvrir);
            bouton.setAttribute('aria-expanded', ouvrir ? 'true' : 'false');
            if (voile) { voile.hidden = !ouvrir; }
        }

        bouton.addEventListener('click', function () {
            basculer(!menu.classList.contains('is-open'));
        });

        if (voile) {
            voile.addEventListener('click', function () { basculer(false); });
        }

        racine.addEventListener('keydown', function (evenement) {
            if (evenement.key === 'Escape') { basculer(false); }
        });
    }

    /* ------------------------------------------------ Messages temporaires */
    function initialiserFlash() {
        racine.querySelectorAll('[data-fermer-flash]').forEach(function (bouton) {
            bouton.addEventListener('click', function () {
                var message = bouton.closest('.flash');
                if (message) { message.remove(); }
            });
        });
    }

    /* ------------------------------------------- Confirmation des actions */
    function initialiserConfirmations() {
        racine.querySelectorAll('[data-confirmer]').forEach(function (formulaire) {
            formulaire.addEventListener('submit', function (evenement) {
                if (!window.confirm(formulaire.dataset.confirmer)) {
                    evenement.preventDefault();
                }
            });
        });
    }

    /* ------------------------------------------- Affichage des mots de passe */
    function initialiserBasculeMotDePasse() {
        racine.querySelectorAll('[data-bascule-mdp]').forEach(function (bouton) {
            bouton.addEventListener('click', function () {
                var champ = racine.getElementById(bouton.dataset.basculeMdp);
                if (!champ) { return; }

                var visible = champ.type === 'text';
                champ.type = visible ? 'password' : 'text';
                bouton.setAttribute('aria-label', visible ? 'Afficher le mot de passe' : 'Masquer le mot de passe');

                var icone = bouton.querySelector('.bi');
                if (icone) {
                    icone.classList.toggle('bi-eye', visible);
                    icone.classList.toggle('bi-eye-slash', !visible);
                }
            });
        });
    }

    /* ------------------------------------------- Robustesse du mot de passe */
    function initialiserJaugeMotDePasse() {
        racine.querySelectorAll('[data-jauge-mdp]').forEach(function (champ) {
            var jauge = champ.closest('.champ') ? champ.closest('.champ').querySelector('[data-jauge-cible]') : null;
            if (!jauge) { return; }

            var barre = jauge.querySelector('.jauge-mdp-barre span');
            var texte = jauge.querySelector('.jauge-mdp-texte');
            var niveaux = [
                { seuil: 0, libelle: 'Trop faible', couleur: '#C8102E', largeur: '20%' },
                { seuil: 2, libelle: 'Faible', couleur: '#C8102E', largeur: '40%' },
                { seuil: 3, libelle: 'Correct', couleur: '#B45309', largeur: '65%' },
                { seuil: 4, libelle: 'Bon', couleur: '#15803D', largeur: '85%' },
                { seuil: 5, libelle: 'Excellent', couleur: '#15803D', largeur: '100%' }
            ];

            champ.addEventListener('input', function () {
                var valeur = champ.value;
                jauge.hidden = valeur.length === 0;
                if (valeur.length === 0) { return; }

                var points = 0;
                if (valeur.length >= 12) { points++; }
                if (valeur.length >= 16) { points++; }
                if (/[a-z]/.test(valeur)) { points++; }
                if (/[A-Z]/.test(valeur)) { points++; }
                if (/\d/.test(valeur)) { points++; }
                if (/[^A-Za-z0-9]/.test(valeur)) { points++; }

                var niveau = niveaux[0];
                niveaux.forEach(function (candidat) {
                    if (points >= candidat.seuil) { niveau = candidat; }
                });

                barre.style.width = niveau.largeur;
                barre.style.background = niveau.couleur;
                texte.textContent = 'Robustesse : ' + niveau.libelle;
                texte.style.color = niveau.couleur;
            });
        });
    }

    /* ------------------------------------------ Questionnaire en sections */
    function initialiserEtapes() {
        var formulaire = racine.getElementById('formulaireQuestionnaire');
        if (!formulaire) { return; }

        var etapes = Array.prototype.slice.call(formulaire.querySelectorAll('.etape'));
        var liens = Array.prototype.slice.call(formulaire.querySelectorAll('[data-aller-etape]'));
        var compteur = formulaire.querySelector('[data-etape-courante]');
        var progression = formulaire.querySelector('[data-etape-progression]');
        if (etapes.length === 0) { return; }

        var courante = parseInt(formulaire.dataset.etapeInitiale || '1', 10) || 1;

        function afficher(numero, defiler) {
            courante = Math.min(Math.max(1, numero), etapes.length);

            etapes.forEach(function (etape) {
                etape.classList.toggle('is-active', parseInt(etape.dataset.etape, 10) === courante);
            });
            liens.forEach(function (lien) {
                var index = parseInt(lien.dataset.allerEtape, 10);
                lien.classList.toggle('is-active', index === courante);
                lien.classList.toggle('is-complete', index < courante);
            });

            if (compteur) { compteur.textContent = String(courante); }
            if (progression) { progression.style.width = Math.round((courante / etapes.length) * 100) + '%'; }

            if (defiler) {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        }

        liens.forEach(function (lien) {
            lien.addEventListener('click', function () {
                afficher(parseInt(lien.dataset.allerEtape, 10), true);
            });
        });
        formulaire.querySelectorAll('[data-etape-suivante]').forEach(function (bouton) {
            bouton.addEventListener('click', function () { afficher(courante + 1, true); });
        });
        formulaire.querySelectorAll('[data-etape-precedente]').forEach(function (bouton) {
            bouton.addEventListener('click', function () { afficher(courante - 1, true); });
        });

        // Un champ obligatoire vide bloque l'envoi et ramène sur sa section.
        formulaire.addEventListener('submit', function (evenement) {
            var manquants = Array.prototype.filter.call(
                formulaire.querySelectorAll('[data-obligatoire="1"]'),
                function (champ) { return champ.value.trim() === ''; }
            );

            formulaire.querySelectorAll('[data-obligatoire="1"]').forEach(function (champ) {
                champ.classList.remove('is-invalid');
            });

            if (manquants.length === 0) { return; }

            evenement.preventDefault();
            manquants.forEach(function (champ) { champ.classList.add('is-invalid'); });

            var premier = manquants[0];
            var section = premier.closest('.etape');
            if (section) { afficher(parseInt(section.dataset.etape, 10), false); }
            premier.scrollIntoView({ block: 'center', behavior: 'smooth' });
            premier.focus({ preventScroll: true });

            window.alert(
                manquants.length === 1
                    ? 'Un champ obligatoire n’est pas rempli.'
                    : manquants.length + ' champs obligatoires ne sont pas remplis.'
            );
        });

        afficher(courante, false);
    }

    /* ------------------------------------------------ Lignes de réquisition */
    function initialiserArticles() {
        var table = racine.getElementById('tableArticles');
        var modele = racine.getElementById('modeleLigneArticle');
        if (!table) { return; }

        var corps = table.querySelector('tbody');
        var totalGeneral = racine.querySelector('[data-montant-total]');

        function formater(nombre) {
            return nombre.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function recalculer() {
            var total = 0;

            corps.querySelectorAll('.ligne-article').forEach(function (ligne) {
                var quantite = parseFloat((ligne.querySelector('[data-quantite]') || {}).value) || 0;
                var prix = parseFloat((ligne.querySelector('[data-prix]') || {}).value) || 0;
                var montant = quantite * prix;
                total += montant;

                var sortie = ligne.querySelector('[data-montant-ligne]');
                if (sortie) { sortie.textContent = formater(montant); }
            });

            if (totalGeneral) { totalGeneral.textContent = formater(total); }
        }

        corps.addEventListener('input', function (evenement) {
            if (evenement.target.matches('[data-quantite], [data-prix]')) { recalculer(); }
        });

        corps.addEventListener('click', function (evenement) {
            var bouton = evenement.target.closest('[data-retirer-article]');
            if (!bouton) { return; }

            var lignes = corps.querySelectorAll('.ligne-article');
            if (lignes.length <= 1) {
                // On garde toujours une ligne : on la vide au lieu de la retirer.
                lignes[0].querySelectorAll('input').forEach(function (champ) {
                    champ.value = champ.matches('[data-quantite]') ? '1' : '';
                });
            } else {
                bouton.closest('.ligne-article').remove();
            }

            recalculer();
        });

        var ajouter = racine.querySelector('[data-ajouter-article]');
        if (ajouter && modele) {
            ajouter.addEventListener('click', function () {
                corps.appendChild(modele.content.cloneNode(true));
                var lignes = corps.querySelectorAll('.ligne-article');
                var champ = lignes[lignes.length - 1].querySelector('input[name="designation[]"]');
                if (champ) { champ.focus(); }
                recalculer();
            });
        }

        recalculer();
    }

    /* ----------------------------------------------------------- Impression */
    function initialiserImpression() {
        racine.querySelectorAll('[data-imprimer]').forEach(function (bouton) {
            bouton.addEventListener('click', function () { window.print(); });
        });
    }

    /* ------------------------------------ Filtres : soumission automatique */
    function initialiserFiltres() {
        racine.querySelectorAll('.barre-filtres select').forEach(function (champ) {
            champ.addEventListener('change', function () {
                if (champ.form) { champ.form.submit(); }
            });
        });
    }

    /* ------------------------------------- Double envoi des formulaires POST */
    function initialiserAntiDoubleEnvoi() {
        racine.querySelectorAll('form[method="POST"], form[method="post"]').forEach(function (formulaire) {
            formulaire.addEventListener('submit', function () {
                window.setTimeout(function () {
                    formulaire.querySelectorAll('button[type="submit"]').forEach(function (bouton) {
                        bouton.disabled = true;
                    });
                }, 0);
            });
        });
    }

    function demarrer() {
        initialiserMenu();
        initialiserFlash();
        initialiserConfirmations();
        initialiserBasculeMotDePasse();
        initialiserJaugeMotDePasse();
        initialiserEtapes();
        initialiserArticles();
        initialiserImpression();
        initialiserFiltres();
        initialiserAntiDoubleEnvoi();
    }

    if (racine.readyState === 'loading') {
        racine.addEventListener('DOMContentLoaded', demarrer);
    } else {
        demarrer();
    }
}());
