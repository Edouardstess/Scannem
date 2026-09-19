# UEP — MENFP · Plateforme de gestion des données institutionnelles

Application web de l'**Unité d'Études et de Programmation** du Ministère de
l'Éducation Nationale et de la Formation Professionnelle d'Haïti.

Trois modules :

| Module | Objet |
|---|---|
| **UPD** | Questionnaire des Universités Publiques Départementales — 625 questions, sections A à Q |
| **DDE** | Questionnaire des Directions Départementales d'Éducation — 89 questions, sections A à F |
| **Réquisitions** | Demandes d'équipement informatique, électrique et logiciel, avec bon imprimable |

PHP 8.1+ et MySQL 5.7+ / MariaDB 10.3+. **Aucun framework, aucune dépendance à
installer** : ni Composer, ni npm, ni accès SSH. Tout ce dont l'application a
besoin est dans le dossier `htdocs/`.

---

## Installation en cinq minutes (ByetHost, InfinityFree, tout hébergement mutualisé)

1. **Créer la base MySQL** dans le panneau de l'hébergeur (VistaPanel → *MySQL
   Databases*). Notez le serveur, le nom de la base, l'utilisateur et le mot de
   passe.
2. **Téléverser le contenu du dossier `htdocs/`** à la racine web du domaine —
   généralement `htdocs/`. Le fichier `index.php` doit se retrouver
   **directement** dans la racine web, pas dans un sous-dossier `htdocs/htdocs/`.
3. **Ouvrir `https://votre-domaine/install.php`** dans un navigateur.
   L'assistant vérifie le serveur, teste la connexion MySQL, écrit la
   configuration, crée les 17 tables et vues, charge les 714 questions des
   questionnaires, puis crée votre compte administrateur.
4. **Supprimer `install.php`** : l'assistant propose un bouton qui s'en charge.
5. Se connecter sur `https://votre-domaine/login`.

Le guide détaillé, écran par écran, est dans
[`docs/DEPLOIEMENT-BYETHOST.md`](docs/DEPLOIEMENT-BYETHOST.md).

### Installation manuelle (sans l'assistant)

```
1. phpMyAdmin → sélectionner la base → Importer → database/schema.sql
2. phpMyAdmin → SQL → coller database/creer-admin.sql → Exécuter
3. Copier config/config.local.example.php en config/config.local.php
   et y renseigner les identifiants MySQL et un UEP_APP_SECRET aléatoire.
4. Se connecter avec admin@menfp.gouv.ht / UepAdmin2026! puis CHANGER ce mot de passe.
```

### Mise à jour depuis la version 1.x

Ne réimportez pas `schema.sql` : il recrée les tables et efface les données.
Exécutez `database/migration-2.0.sql` depuis phpMyAdmin.

---

## Développement en local

```bash
cd htdocs
php -S localhost:8000 router-dev.php
```

`router-dev.php` reproduit le comportement du `.htaccess` (réécriture d'URL et
blocage des dossiers internes) pour le serveur intégré de PHP. Il ne s'exécute
que sous ce serveur : déposé sur un hébergement réel, il ne répond rien.

Avec XAMPP ou WAMP, placez le contenu de `htdocs/` dans le dossier web et
activez `mod_rewrite`.

---

## Rôles et droits

| Action | Administrateur | Superviseur | Saisisseur | Lecteur |
|---|:--:|:--:|:--:|:--:|
| Consulter les dossiers et les réquisitions | ✔ | ✔ | ✔ | ✔ |
| Créer et modifier un dossier UPD / DDE | ✔ | — | ✔ | — |
| Soumettre un dossier pour validation | ✔ | — | ✔ | — |
| Valider ou rejeter un dossier | ✔ | ✔ | — | — |
| Rouvrir un dossier validé | ✔ | — | — | — |
| Créer et modifier une réquisition | ✔ | — | ✔ | — |
| Approuver, rejeter, déclarer livrée | ✔ | ✔ | — | — |
| Supprimer un dossier ou une réquisition | ✔ | — | — | — |
| Gérer les comptes, consulter le journal | ✔ | — | — | — |

### Circuit de validation des dossiers

```
brouillon ──soumettre──► soumis ──valider──► validé
     ▲                     │
     │                     └──rejeter──► rejeté ──soumettre──► soumis
     └───────────── rouvrir (administrateur) ──────────────────┘
```

La saisie n'est possible qu'en **brouillon** ou **rejeté**. Dans les autres
états le formulaire est verrouillé côté affichage *et* le contrôleur refuse
l'écriture : poster directement sur l'URL d'enregistrement ne contourne rien.

### Circuit des réquisitions

```
en attente ──approuver──► approuvée ──livrer──► livrée (définitif)
     └──────rejeter──────► rejetée
```

Une réquisition ne peut être déclarée livrée qu'après approbation, et une
réquisition livrée ne change plus de statut.

---

## Architecture

MVC en PHP natif, point d'entrée unique, aucune bibliothèque serveur externe.

```
htdocs/
├── index.php          Contrôleur frontal : toutes les requêtes dynamiques y passent
├── install.php        Assistant d'installation (à supprimer après usage)
├── router-dev.php     Routeur du serveur PHP intégré (développement)
├── .htaccess          Réécriture d'URL, protections, compression, cache
│
├── config/            Configuration ; config.local.php n'est jamais versionné
├── core/              Micro-noyau : Router, Controller, Auth, Session, Csrf,
│                      Validator, Workflow, Paginator, Flash, Format, Database
├── middlewares/       Contrôle de connexion (AuthMiddleware) et de rôle (RoleMiddleware)
├── controllers/       Un contrôleur par module
├── views/             Gabarits : layouts, partials, une vue par écran
├── routes/web.php     Table de routage
├── database/          schema.sql, creer-admin.sql, migration-2.0.sql
├── storage/logs/      Journaux applicatifs (hors du web)
├── tools/             Script CLI de création d'administrateur (serveurs avec SSH)
└── assets/            CSS, JavaScript, images, polices et bibliothèques servies localement
```

`UpdController` et `DdeController` ne contiennent que leurs différences : toute
la mécanique des questionnaires vit dans `QuestionnaireController`.

### Aucune dépendance externe au chargement des pages

Bootstrap, Bootstrap Icons, Chart.js, Montserrat et Roboto sont **servis depuis
le serveur** (`assets/vendor/` et `assets/fonts/`). L'application ne contacte
aucun CDN : elle fonctionne derrière un filtrage réseau, sur une connexion lente
et le jour où un CDN tombe. Cela permet aussi une politique de sécurité de
contenu stricte (`default-src 'self'`).

---

## Sécurité

- Mots de passe hachés avec `password_hash()` (bcrypt), rehachés automatiquement
  si l'algorithme par défaut évolue.
- Jeton **CSRF** vérifié sur chaque requête POST.
- **Toutes** les requêtes SQL sont préparées, sans émulation. Aucune valeur
  issue de l'utilisateur n'est concaténée dans une requête.
- Échappement systématique des sorties HTML (`e()`), y compris dans les
  messages d'erreur.
- **Anti-bruteforce** : blocage après 5 échecs sur un compte ou 20 sur une même
  adresse IP, pendant 15 minutes ; le compteur est remis à zéro après une
  connexion réussie.
- Session régénérée à la connexion, expiration d'inactivité contrôlée côté
  serveur, cookie `HttpOnly` + `SameSite=Lax`, drapeau `secure` aligné sur le
  protocole **réel** de la requête.
- En-têtes : `Content-Security-Policy` (avec nonce, sans `unsafe-inline` pour
  les scripts), `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`,
  `Permissions-Policy`, et `Strict-Transport-Security` dès que le site est en
  HTTPS.
- Le rôle et l'état actif du compte sont **relus en base à chaque requête** : un
  compte désactivé ou rétrogradé perd ses droits immédiatement.
- Les dossiers internes (`config/`, `core/`, `database/`, `views/`, `storage/`…)
  et les fichiers sensibles sont refusés en HTTP par `.htaccess`.
- **Journal d'audit** : connexions, créations, modifications, suppressions,
  transitions de statut et accès refusés, avec horodatage et adresse IP.

### Ce qui reste à votre charge

- Changer le mot de passe administrateur dès la première connexion, et le mot de
  passe MySQL fourni par l'hébergeur.
- Activer HTTPS. L'application le détecte seule et réactive alors le cookie
  `secure` et HSTS.
- Sauvegarder la base régulièrement (phpMyAdmin → Exporter).
- Un hébergement mutualisé gratuit convient à une démonstration ou à un
  prototype. Pour des données administratives réelles, prévoyez un hébergement
  avec sauvegardes, isolation et restauration testée.

---

## Tests

```bash
# 1. Lancer l'application
cd htdocs && php -S 127.0.0.1:8090 router-dev.php &

# 2. Tests fonctionnels (90 vérifications : routes, CRUD, rôles, workflow,
#    CSRF, XSS, injection SQL, anti-bruteforce, pagination)
php tests/tests-fonctionnels.php

# 3. Tests d'interface (28 vérifications dans un navigateur réel : calculs en
#    direct, navigation par sections, menu mobile, graphiques)
node tests/tests-interface.js
```

Les deux harnais attendent une base de test et le compte
`admin@menfp.gouv.ht`. Adaptez les constantes en tête de fichier à votre
environnement.

---

## Documentation

- [`docs/DEPLOIEMENT-BYETHOST.md`](docs/DEPLOIEMENT-BYETHOST.md) — mise en ligne pas à pas et dépannage
- [`docs/CORRECTIONS.md`](docs/CORRECTIONS.md) — ce qui a été corrigé et vérifié dans cette version
