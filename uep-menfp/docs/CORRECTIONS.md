# Version 2.0 — ce qui a été corrigé et vérifié

Reconstruction complète du projet à partir de l'archive `Uep-corrige_1.zip`.
Les modules, le modèle de données et les 714 questions des questionnaires sont
conservés. Chaque correction ci-dessous a été reproduite avant d'être corrigée,
puis vérifiée par un test automatisé.

---

## A. Bugs corrigés

### A.1 Erreur 500 sur toute URL comportant un identifiant non numérique

`/upd/abc`, `/requisitions/xyz`, `/utilisateurs/9%20/modifier` renvoyaient une
**erreur 500**. Le routeur passait le segment d'URL tel quel à une méthode typée
`int`, ce qui déclenchait une `TypeError` non interceptée.

Le paramètre `{id}` n'accepte désormais que des chiffres (1 à 9 chiffres, donc
aucun débordement d'entier). Toute autre valeur donne une **page 404** propre.

### A.2 Messages d'erreur perdus

Six vues affichaient `flash_success`, deux seulement affichaient `flash_error`,
et aucune des pages de formulaire n'en affichait. Conséquence : quand on tentait
d'enregistrer un dossier verrouillé, le contrôleur écrivait bien
« ce dossier n'est plus modifiable » puis redirigeait vers une page qui ne
l'affichait jamais. L'utilisateur voyait sa saisie disparaître sans explication.

Les messages sont désormais produits par une classe `Flash` et rendus **une
seule fois, dans les gabarits**. Aucune vue n'a plus à s'en occuper, et aucun
message ne peut plus être perdu.

### A.3 Le formulaire DDE renvoyait vers la liste des UPD

Le gabarit du formulaire était partagé entre UPD et DDE, mais deux liens
« Retour à la liste » pointaient en dur vers `/upd`. Depuis un dossier DDE, on
se retrouvait dans le mauvais module.

Toute la mécanique commune vit maintenant dans `QuestionnaireController` ;
`UpdController` et `DdeController` ne déclarent que leurs différences. Un lien
en dur ne peut plus diverger.

### A.4 Des numéros de téléphone stockés comme départements

Pour les DDE, la colonne `departement` était alimentée depuis la réponse **A.3**
du questionnaire — qui est le *téléphone de la direction départementale*. Les
listes, les filtres et le graphique « couverture départementale » affichaient
donc des numéros de téléphone.

Le questionnaire DDE ne contenait aucune question sur le département. Elle a été
ajoutée au catalogue (`A.0`, « Département géographique de la Direction
Départementale ») et c'est elle qui alimente la colonne.
`database/migration-2.0.sql` ajoute la question et efface les valeurs erronées
des bases existantes.

### A.5 Recherche impossible sur les listes

Les requêtes de filtrage réutilisaient un même paramètre nommé (`:q`) à
plusieurs endroits d'une même requête. Comme l'émulation des requêtes préparées
est désactivée — ce qui est le bon réglage —, MySQL rejetait la requête :
`SQLSTATE[HY093] Invalid parameter number`, donc **erreur 500** dès qu'on tapait
un mot dans un champ de recherche.

Chaque occurrence reçoit désormais son propre paramètre.

### A.6 Script SQL non réimportable

`database.sql` utilisait `CREATE TABLE` sans garde : un second import échouait
sur « table already exists » et laissait la base à moitié installée.

`schema.sql` supprime les objets dans l'ordre des dépendances avant de les
recréer, et fixe explicitement le `SQL_MODE`. L'import est reproductible.

### A.7 Identifiants réels publiés en clair

`config/config.local.php` contenait le mot de passe MySQL, le nom de la base et
le secret applicatif de l'installation ByetHost, et le fichier était présent
dans l'archive.

Il n'est plus livré du tout. `config/config.local.example.php` sert de modèle et
`install.php` génère le vrai fichier, avec un secret aléatoire de 48 caractères
hexadécimaux produit à l'installation.

> **À faire de votre côté :** le mot de passe MySQL et le mot de passe
> administrateur de l'ancienne installation ont circulé en clair. Changez-les.

### A.8 Aucun moyen de créer le premier compte sans ligne de commande

`tools/create_admin.php` exigeait la ligne de commande PHP, absente des
hébergements mutualisés. Sans compte en base, l'application était inutilisable.

`install.php` fait le travail depuis un navigateur : vérification du serveur,
test de connexion, écriture de la configuration, import du schéma, création du
compte, puis autodestruction. Il refuse de repartir dès qu'un administrateur
actif existe.

### A.9 Journaux d'erreurs impossibles à consulter

`log_errors` était activé sans `error_log` : les erreurs partaient dans le
journal global de l'hébergeur, inaccessible sur une offre mutualisée.

Elles sont écrites dans `storage/logs/php-AAAA-MM.log`, dossier refusé en HTTP.

### A.10 Avertissement PHP à chaque requête

`ini_set('session.sid_length', …)` est déprécié depuis PHP 8.4 et produisait un
`Deprecated` à chaque chargement de page. Réglage retiré ; le journal est vide.

---

## B. Fonctionnement et ergonomie

### B.1 Listes sans recherche, sans filtre, sans pagination

Les quatre listes affichaient toutes les lignes d'un coup. Avec les dix
départements, leurs dossiers et l'historique des réquisitions, la page devenait
inutilisable.

Recherche plein texte, filtres (statut, département, priorité, rôle) et
pagination de 25 lignes (50 pour le journal) sur toutes les listes.

### B.2 Erreurs de saisie invisibles dans les questionnaires

La validation serveur produisait un message par question, mais l'affichage se
contentait d'un « le formulaire contient N erreur(s) » : aucun champ n'était
signalé, et avec 625 questions réparties sur 17 sections, la question fautive
était introuvable.

Chaque champ en erreur est désormais encadré en rouge avec son message, et le
formulaire s'ouvre directement sur la première section concernée.

### B.3 Statut des réquisitions modifiable par n'importe quel saisisseur

Le statut voyageait dans le formulaire de modification. Un saisisseur ne pouvait
pas l'utiliser, mais rien ne distinguait « corriger une demande » de
« l'approuver ».

Les deux actions sont séparées : `/requisitions/{id}/mettre-a-jour` pour le
contenu, `/requisitions/{id}/decision` pour la décision, réservée aux
administrateurs et superviseurs. Une réquisition ne peut être déclarée livrée
qu'après approbation, et une réquisition livrée est définitive.

### B.4 Pas de trace consultable, pas de fiche de compte

`journal_activites` se remplissait mais n'était affichée nulle part.

Ajout de l'écran **Journal d'activités** (administrateurs, avec recherche et
filtre par utilisateur) et de la page **Mon profil** (identité, rôle, dernière
connexion, quinze dernières actions).

### B.5 Pas de document imprimable

Ajout du **bon de réquisition** imprimable : en-tête institutionnel, tableau des
articles, total et blocs de signature, mis en page pour le format A4.

### B.6 Détails d'ergonomie

- Total des réquisitions calculé en direct pendant la saisie ; lignes d'articles
  ajoutables et supprimables sans recharger la page.
- Indicateur de robustesse du mot de passe et bouton d'affichage.
- Confirmation avant chaque suppression.
- Boutons d'envoi désactivés après le premier clic (plus de double soumission).
- États vides explicites au lieu de tableaux vides.
- Montants en gourdes, dates en français, libellés de statut homogènes partout.

---

## C. Sécurité

### C.1 Dépendances externes supprimées

Bootstrap, Bootstrap Icons, Chart.js et les polices Google étaient chargés
depuis des CDN : le site était cassé si un CDN devenait inaccessible — cas
fréquent sur une connexion filtrée ou instable —, la politique de sécurité de
contenu devait les autoriser, et chaque visite était signalée à trois domaines
tiers.

Tout est désormais servi localement depuis `assets/`. La politique de sécurité
de contenu est passée à `default-src 'self'` et les scripts inline sont
autorisés par **nonce**, sans `unsafe-inline`.

### C.2 Images de 1,2 Mo sur chaque page

Le logo et l'illustration étaient des SVG produits par vectorisation d'images :
**1,2 Mo et 1,0 Mo**, rechargés sur toutes les pages.

Convertis en PNG optimisés : **34 Ko** et **150 Ko**, soit 2,2 Mo économisés par
visite. Compression gzip et cache long ajoutés dans le `.htaccess`.

### C.3 Réponses HTTP incohérentes

Les erreurs sortaient tantôt en texte brut (`exit('Token CSRF invalide')`),
tantôt en HTML, et un échec CSRF renvoyait un 403 sans explication. Trois
gabarits d'erreur se dupliquaient.

Une classe `ErreurHttp` et un gabarit unique traitent 400, 403, 404, 405, 419,
500 et 503. Un jeton CSRF expiré renvoie un **419** expliquant la marche à
suivre au lieu d'un 403 muet.

### C.4 Durcissements complémentaires

- `Auth::connecter()` vérifie systématiquement un hachage, même sans compte
  correspondant : le temps de réponse ne révèle plus l'existence d'une adresse.
- Jeton CSRF régénéré à la connexion et au changement de mot de passe.
- Redirection après connexion restreinte aux chemins internes.
- L'autoloader n'accepte que des noms de classe alphanumériques.
- Un administrateur ne peut ni se retirer ses propres droits, ni supprimer le
  dernier administrateur actif.
- En-têtes ajoutés : `Cross-Origin-Opener-Policy`, `Permissions-Policy` étendue.
- `.htaccess` : protections conditionnées aux requêtes clientes uniquement, pour
  qu'une réécriture interne ne puisse pas produire un 403 sur toutes les routes.

---

## D. Vérifications effectuées

Environnement : PHP 8.4.19, MariaDB 10.11, Apache 2.4, Chromium.

### Tests fonctionnels — `tests/tests-fonctionnels.php`

**90 vérifications, 0 échec.**

| Domaine | Vérifications |
|---|---|
| Pages publiques et routage | accueil, connexion, 404, identifiants non numériques |
| Connexion | mauvais mot de passe, CSRF invalide, changement imposé |
| Pages protégées | 7 routes en 200, aucune erreur PHP dans le HTML |
| UPD | création, 624 champs générés, enregistrement, dénormalisation |
| Circuit UPD | soumettre → rejeter → soumettre → valider → rouvrir |
| Verrouillage | écriture forcée sur un dossier soumis : refusée, base inchangée |
| DDE | création, enregistrement, dénormalisation du département |
| Réquisitions | création, montants calculés, modification, remplacement des lignes |
| Décision | livraison refusée avant approbation, réquisition livrée figée |
| Bon imprimable | page générée, total exact |
| Utilisateurs | création, modification, doublon d'adresse refusé, auto-rétrogradation refusée |
| Rôles | saisisseur sur `/utilisateurs` → 403 ; validation par un saisisseur → 403 |
| XSS | `<script>` injecté dans un objet : échappé à l'affichage |
| Injection SQL | `' OR '1'='1` dans un filtre : sans effet, base intacte |
| Anti-bruteforce | blocage au 5ᵉ échec, compteur remis à zéro après succès |
| Pagination | page hors limites et page non numérique gérées |

### Tests d'interface — `tests/tests-interface.js`

**28 vérifications, 0 échec**, dans Chromium.

Calcul des montants en direct, ajout et retrait de lignes, navigation entre les
17 sections, affichage et fermeture des messages, bascule des mots de passe,
confirmation avant suppression, rendu effectif des trois graphiques, menu
mobile, absence de défilement horizontal à 390 px, **aucune erreur JavaScript**.

### Serveur

- **Apache + `.htaccess`** : réécriture d'URL vérifiée sur `/dashboard`,
  `/upd/42`, `/requisitions`, `/login` ; 12 ressources statiques servies ;
  20 chemins internes (`config/`, `core/`, `database/`, `views/`, `storage/`,
  `tools/`, fichiers cachés, `.sql`) tous refusés en 403 ; compression gzip
  active (feuille de style : 40 Ko → 8 Ko).
- **Assistant d'installation** : parcours complet validé, identifiants erronés
  refusés avec le message de MySQL, mot de passe faible refusé, verrouillage
  après création de l'administrateur.
- **Import SQL** : 17 tables et vues, 624 questions UPD, 89 questions DDE,
  aucune erreur.
- **Lint PHP** : aucune erreur de syntaxe sur les 55 fichiers.
- **Journal applicatif** : vide après l'ensemble des campagnes de tests —
  aucun avertissement, aucune dépréciation.

### Ce qui n'a pas pu être vérifié ici

L'application n'a pas été testée sur ByetHost même : cela demande un compte et
un domaine. Elle l'a été sur la même pile logicielle (Apache 2.4 + PHP 8 +
MySQL, en `APP_ENV=production`, servie en HTTP), et `install.php` vérifie à
l'installation chacun des points qui peuvent différer d'un hébergeur à l'autre.
