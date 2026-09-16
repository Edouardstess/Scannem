# Scannem

Cartes physiques à QR code signé, vérifiées à l'entrée avec un téléphone.
Une carte = une entrée. PHP 8.1+, SQLite ou MySQL, sans framework.

---

## À lire avant de déployer : ce que ce système fait, et ce qu'il ne fait pas

Cette section n'est pas un avertissement de principe. Elle décrit le modèle de
sécurité réel, et vous en aurez besoin le soir de l'événement.

### Les fausses cartes sont bloquées. Totalement.

Chaque QR contient une signature HMAC-SHA256 calculée avec un secret qui ne
quitte jamais le serveur. Fabriquer un code valide sans ce secret demanderait de
deviner 80 bits : c'est hors de portée. Sur ce point, le problème est réglé.

### Les copies ne sont pas détectables. Jamais.

Un QR imprimé est un **jeton au porteur statique**. Si quelqu'un photographie sa
carte et envoie l'image à un ami, la copie est mathématiquement identique à
l'original. Aucune signature, aucun chiffrement, aucun algorithme ne peut les
distinguer — l'information présentée au scanner est la même.

**La seule défense contre la copie est l'usage unique côté serveur.** Scannem ne
bloque donc pas la copie : il la rend inutile. Le premier scan consomme la carte,
tous les suivants sont refusés. Une seule des deux personnes entre.

### La conséquence du choix « cartes anonymes »

Si le copieur se présente avant le titulaire légitime, **c'est le copieur qui
entre et le vrai titulaire qui est refusé**. La carte ne porte aucun nom : vous
n'aurez aucun moyen de trancher à la porte.

Si l'enjeu est sérieux (argent, capacité limitée, responsabilité), ajoutez un nom
sur les cartes. Le schéma contient déjà une colonne `cards.holder_label` prévue
pour ça, et l'API la renvoie au scanner quand elle est remplie : le vigile compare
avec la personne, et le litige se règle en trois secondes. C'est le meilleur
rapport effort/bénéfice de tout le système.

### Hors-ligne, un doublon peut passer

Deux portes déconnectées ne peuvent pas se consulter. Si la même carte est
présentée aux deux pendant une coupure réseau, les deux laissent entrer. **Ce
n'est pas un défaut de configuration, c'est une impossibilité** : aucun système
distribué ne peut garantir l'unicité sans communication.

Ce que Scannem fait à la place : il journalise chaque passage avec l'heure du
téléphone, et dès le retour du réseau il remonte les doublons dans un écran
**Litiges**. La fraude est constatée après coup, pas empêchée sur le moment.

**Pour l'éviter : gardez les appareils connectés.** Un partage de connexion depuis
un téléphone suffit — une vérification en ligne tient dans quelques centaines
d'octets.

### Ce qui vaut les cartes elles-mêmes

Trois choses donnent le pouvoir d'entrer, traitez-les comme des billets :

| Fichier | Risque |
|---|---|
| `storage/config.php` | Contient le secret. Le perdre = toutes les cartes imprimées deviennent invérifiables. Le divulguer = fabrication illimitée de cartes. |
| Planche d'impression, export CSV | Contiennent les codes complets. Quiconque y accède peut imprimer des cartes qui passeront (une fois chacune). |
| Base de données | Contient les `uid`, mais **pas** les signatures : les payloads sont recalculés à la volée. Une base volée sans `config.php` ne donne aucun QR utilisable. |

---

## Mise en ligne sur un hébergement gratuit (ByetHost, InfinityFree…)

Pas besoin de ligne de commande, ni de Composer, ni d'argent. Compter un
quart d'heure.

### 1. Créer la base MySQL

Dans le panneau de ton hébergeur (VistaPanel chez ByetHost) : **MySQL Databases**
→ créer une base. Note ce qu'il affiche : **serveur**, **nom de la base**,
**utilisateur**, **mot de passe**. Chez ByetHost, le serveur n'est presque jamais
`localhost` — c'est souvent quelque chose comme `sqlXXX.byethost.com`.

### 2. Envoyer les fichiers

Décompresse l'archive `scannem-*.zip` et envoie **tout son contenu** dans
`htdocs/` par FTP (FileZilla) ou par le gestionnaire de fichiers.

L'archive est faite pour ça : le contenu va directement dans `htdocs/`, sans
sous-dossier et **sans dépendre de mod_rewrite**. Les routes `/admin/` et
`/api/...` correspondent à de vrais fichiers, donc elles fonctionnent même si
l'hébergeur restreint la réécriture d'URL.

### 3. Installer

Ouvre `https://TON-DOMAINE/install.php`, remplis le formulaire, valide.
La page vérifie d'abord ton hébergement (PHP, MySQL, droits d'écriture) et te dit
ce qui manque avant de créer quoi que ce soit.

### 4. Supprimer l'installateur

L'installateur essaie de se supprimer tout seul. **Vérifie que c'est fait** :
`https://TON-DOMAINE/install.php` doit renvoyer une erreur 404. Sinon, supprime
le fichier par FTP.

> Tant que `install.php` existe et que le site n'est pas installé, quiconque
> trouve l'adresse peut installer Scannem et en prendre le contrôle. La fenêtre
> est courte si tu suis ces étapes dans l'ordre, mais elle est réelle.

### 5. Lancer le contrôle de sécurité

Connecte-toi à `/admin/` et ouvre l'onglet **Sécurité**. Il interroge ton site
depuis lui-même, exactement comme le ferait un curieux, et vérifie notamment que
`storage/config.php` ne livre rien.

**Si ce contrôle affiche « DU CONTENU EST SERVI » sur `storage/config.php`,
arrête tout.** Ton secret de signature est téléchargeable, et n'importe qui peut
fabriquer des cartes valides. Rien d'autre n'a d'importance tant que ce n'est pas
réglé.

### 6. Mettre le secret hors de la racine web (recommandé)

Si ton FTP te laisse créer un dossier **à côté** de `htdocs` (et non dedans),
c'est la meilleure protection possible :

1. crée `/scannem-donnees` au même niveau que `htdocs` ;
2. dépose dans `htdocs/` un fichier `scannem-local.php` contenant :

```php
<?php return '/home/TON_COMPTE/scannem-donnees';
```

Le secret vit alors hors de portée du serveur web, quoi qu'il arrive au
`.htaccess`. Ce fichier ne contient qu'un chemin : rien de sensible.

### 7. HTTPS — non négociable

Chez ByetHost, le HTTPS est automatique sur les sous-domaines gratuits.
**Vérifie que `https://` fonctionne avant l'événement** : les navigateurs
refusent l'accès à la caméra sur une connexion non chiffrée. Sans HTTPS, le
scanner ne démarre pas du tout, et seule la saisie manuelle reste possible.

### 8. Le « défibrillateur » : garder le compte en vie

Les hébergeurs gratuits se réservent le droit de désactiver les comptes restés
longtemps sans trafic. Un cron qui appelle l'adresse de santé l'évite :

```
URL   : https://TON-DOMAINE/api/health.php
Rythme: toutes les 6 heures
```

> **Attention, et c'est important.** Chaque appel consomme ton quota de requêtes
> et de processeur. Un cron à la minute déclencherait la **suspension de 24 h**
> pour abus de ressources — le défibrillateur provoquerait alors exactement la
> panne qu'il est censé empêcher. Six heures suffisent ; resserre à 15 minutes
> la veille de l'événement si tu veux être tranquille, puis remets-le comme
> avant.
>
> `api/health.php` ne touche pas à la base : c'est fait exprès, pour que le ping
> coûte le moins possible. Ajoute `?deep=1` seulement pour un contrôle manuel.

Le scanner fait aussi un pré-chauffage à son ouverture : le vigile sait que le
serveur répond **avant** d'avoir quelqu'un devant lui, au lieu de le découvrir au
premier scan.

### Les limites de l'hébergement gratuit, sans enrobage

- **Suspension CPU de 24 h** en cas de dépassement. Pour un contrôle d'entrée,
  c'est le vrai risque : si elle tombe le soir de l'événement, tu bascules en
  mode hors-ligne (le scanner sait le faire) mais tu perds la détection des
  doublons entre portes.
- **Limite d'inodes** : l'archive tient en ~200 fichiers, c'est fait pour.
- **Aucune garantie de disponibilité.** Si l'enjeu est réel, un hébergement à
  quelques euros par mois reste plus sûr — le code est exactement le même.

---

## Essayer en local (WAMP, XAMPP, MAMP, ou rien du tout)

### Le plus rapide : le serveur intégré de PHP

```bash
composer install
php -S localhost:8000 -t public
```

Depuis l'archive de déploiement — dont le contenu est déjà à plat — c'est encore
plus direct, et ça ne touche ni à Apache ni à WampServer :

```
cd C:\wamp64\www\scannem
C:\php\php.exe -S localhost:8000
```

Puis <http://localhost:8000/install.php>, en choisissant **SQLite** : aucune base
à créer, aucun identifiant à saisir. L'administration est sur `/admin/`, le
scanner sur `/scan/`.

### Avec Apache (WAMP, XAMPP, MAMP)

**PHP 8.1 minimum.** WAMP et XAMPP sont souvent livrés avec une version plus
ancienne, et les dépendances de génération de QR ne s'y chargent pas. Sous WAMP :
clic gauche sur l'icône de la barre des tâches, *PHP → Version*. Si PHP est trop
ancien, Scannem l'affiche en toutes lettres au lieu de rendre une page blanche.

Deux dispositions fonctionnent, au choix :

| Disposition | Où déposer | URL |
|---|---|---|
| Racine web sur `public/` | n'importe où | `http://localhost/` |
| Dépôt entier dans `www\scannem\` | `www/` ou `htdocs/` | `http://localhost/scannem/public/` |
| Archive de déploiement à plat | `www\scannem\` | `http://localhost/scannem/` |

**Un sous-dossier convient : Scannem déduit son préfixe d'installation tout
seul** (`Scannem\Url`), et toutes les URL qu'il produit en tiennent compte —
liens de l'administration, appels du scanner, service worker, manifeste PWA.

La caméra fonctionne sur `http://localhost`, que les navigateurs considèrent
comme un contexte sûr. Depuis un téléphone pointant sur l'IP du poste, en
revanche, il faudra du HTTPS.

### Si rien ne s'affiche

| Symptôme | Cause |
|---|---|
| Page blanche, ou HTTP 500 muet | PHP trop ancien. Depuis l'ajout de `app/amorce.php`, un message explicite le dit à la place. |
| « PHP 8.0.x est trop ancien » | Le menu *PHP → Version* de WampServer ne liste que les versions **déjà installées**, et il n'en livre souvent qu'une. Ajouter un module PHP récent depuis `wampserver.aviatechno.net` (rubrique *PHP versions*), puis rouvrir le menu. |
| « Les dépendances ne sont pas installées » | `composer install` n'a pas été lancé, ou `vendor/` a été oublié pendant l'envoi FTP. |
| 404 d'Apache sur `/scan/` | Version antérieure à la gestion du préfixe : les URL partaient de la racine du site. Mettre à jour. |
| 404 d'Apache sur `/admin/` avec la racine web sur `public/` | `mod_rewrite` désactivé. `public/.htaccess` en a besoin pour cette disposition ; l'archive de déploiement, elle, n'en dépend pas. |

---

## Installation en ligne de commande (VPS, local, hébergement avec SSH)

```bash
composer install
php bin/install.php
```

L'installateur génère le secret de signature, crée le schéma et le premier compte
organisateur. Il est relançable : **le secret existant n'est jamais écrasé.**

Mode non interactif (déploiement automatisé) :

```bash
php bin/install.php --no-interaction --admin-user=admin --admin-pass='...'
php bin/install.php --driver=mysql --db-name=scannem --db-user=... --db-pass=...
```

### Racine web : le point à ne pas rater

**Pointez la racine web du domaine sur `public/`, pas sur la racine du projet.**
Sinon `storage/config.php` et la base sont téléchargeables, et tout le modèle de
sécurité s'effondre.

Sur un hébergement mutualisé sans accès au vhost, `install.php` écrit un
`.htaccess` de repli à la racine qui bloque `storage/`, `src/`, `bin/` et
redirige vers `public/`. C'est un filet de sécurité, pas un substitut : vérifiez
après déploiement que `https://votredomaine/storage/config.php` renvoie bien une
erreur.

En revanche, **un sous-dossier ne pose aucun problème** : `/scannem/` comme
racine d'installation fonctionne sans configuration. Le préfixe est déduit de
`SCRIPT_NAME`, c'est-à-dire du fichier réellement exécuté, ce qui reste juste
avec ou sans réécriture.

---

## Utilisation

### 1. Générer un lot

Depuis l'admin (`/admin/`), ou en ligne de commande pour les gros volumes :

```bash
php bin/generate-batch.php --name="Soirée du 12" --qty=500 --date=2026-10-12
```

Produit dans `storage/exports/` une planche d'impression HTML (10 cartes par A4,
à imprimer sans mise à l'échelle) et un export CSV.

Imprimez **une carte d'essai et scannez-la** avant de lancer tout le tirage.

### 2. Enrôler les téléphones

Admin → **Appareils** → générer un code par porte. Sur le téléphone, ouvrir
`/scan/` et saisir le code. Il est valable 15 minutes, pour un seul appareil.

L'enrôlement n'est pas une formalité : sans lui, l'API serait ouverte et n'importe
qui pourrait **brûler les cartes à distance** en appelant la route de validation.

> **HTTPS obligatoire** pour l'accès caméra (sauf sur `localhost`). Sur un domaine
> en HTTP simple, le navigateur refusera la caméra et seule la saisie manuelle
> fonctionnera.

### 3. Avant l'événement

Sur chaque téléphone, menu → **Télécharger le pack hors-ligne**, tant que le
réseau est bon. Le pack ne contient que des empreintes SHA-256 : ni le secret, ni
les `uid` en clair. Un téléphone perdu ne permet pas de fabriquer des cartes.

### 4. À la porte

| Écran | Signification |
|---|---|
| 🟢 **ENTRÉE AUTORISÉE** | Première présentation. La personne entre. |
| 🔴 **DÉJÀ UTILISÉE** | Carte authentique, déjà consommée — avec l'heure et la porte du premier passage. C'est probablement une copie. |
| 🔴 **CARTE NON VALIDE** | Signature invalide : carte fabriquée, ou QR trop abîmé. |
| 🔴 **CARTE ANNULÉE** | Révoquée par l'organisateur. |
| 🟠 **ADMIS SOUS RÉSERVE** | Hors-ligne. Sera confirmé au retour du réseau. |

Chaque verdict s'accompagne d'une vibration distincte : le vigile apprend vite à
reconnaître un refus sans regarder l'écran.

---

## Plusieurs portes en simultané

Oui, plusieurs vigiles peuvent scanner en même temps. Ce n'est pas une intention,
c'est mesuré.

### Le conflit qui compte : la même carte à deux portes

Résolu par un `UPDATE` conditionnel atomique. Le test `ConcurrencyTest` lance
10 processus réels sur la même carte copiée : **exactement un est admis**.
`ThroughputTest` va plus loin avec 20 processus simultanés, sur SQLite comme sur
MySQL.

### Débit mesuré

20 processus concurrents, médiane sur 7 exécutions :

| Base | Débit |
|---|---|
| SQLite (WAL) | **~900 scans/s** |
| MySQL / InnoDB | **~1 560 scans/s** |

Pour donner l'échelle : une entrée dense, c'est 1 à 5 scans par seconde. Même
SQLite est environ **200 fois au-dessus** du besoin réel.

> Ces chiffres mesurent la base de données, pas ton hébergement. Le vrai facteur
> limitant sera PHP et le réseau, pas Scannem. Le serveur de test `php -S` est
> mono-processus : en production, php-fpm sert plusieurs requêtes en parallèle.

### SQLite ou MySQL ?

**SQLite suffit dans l'immense majorité des cas**, y compris à plus de 10 portes.

MySQL devient préférable si tu es dans un de ces cas :
- plusieurs milliers de personnes avec une entrée très dense ;
- plusieurs serveurs PHP partageant la même base (SQLite ne le permet pas) ;
- un hébergement dont le disque est lent ou distant (NFS), où SQLite souffre.

La bascule est une ligne dans `storage/config.php` (`db_driver`), sans rien
réécrire. Toute la suite de tests tourne sur les deux moteurs.

### Ce qui a été fait pour tenir la charge

- **Le quota par IP a été retiré des routes authentifiées.** À un événement,
  toutes les portes passent par le même Wi-Fi et sortent donc sur **une seule IP
  publique**. Un quota sur cette IP les aurait bridées collectivement : à douze
  portes, chacune n'aurait eu droit qu'à un douzième du plafond, et les vigiles
  auraient vu des refus « trop de scans » sans comprendre pourquoi. Le quota par
  appareil, lui, reste en place. (Le quota par IP demeure sur `/api/enroll`,
  seule route ouverte sans jeton.)
- **`last_seen_at` n'est rafraîchi qu'une fois par minute** et par appareil, au
  lieu d'une écriture à chaque requête.
- **Le chemin de scan reste en autocommit, délibérément.** Envelopper
  l'invalidation et le journal dans une transaction paraissait plus propre, mais
  la mesure dit l'inverse : sous SQLite tous les écrivains se sérialisent sur un
  verrou global, et le débit tombait de ~1 300 à ~120 scans/s (et à ~50 avec
  `BEGIN IMMEDIATE`). L'`UPDATE` d'invalidation écrit lui-même `used_at` et
  `used_by_device` sur la carte, donc l'heure et la porte survivent même si la
  ligne de journal manque — `firstAdmission()` sait retomber dessus.
- **Réessai automatique sur contention**, avec recul exponentiel et part
  aléatoire, sur `redeem` et `sync`.

### Quand le serveur sature

Une route API ne renvoie **jamais** de page d'erreur PHP. Deux cas, deux écrans :

| Situation | Réponse | Écran vigile |
|---|---|---|
| Contention passagère | `503` `server_busy` | **orange**, « rescanne, la carte n'a pas été utilisée » |
| Défaillance inattendue | `500` JSON maîtrisé | **rouge**, « préviens l'organisateur » |

L'orange est délibéré : un incident technique ne doit **pas** ressembler à un
refus de carte, sinon un vigile pressé refuse quelqu'un de légitime. Après un
« serveur occupé », rescanner la même carte repart immédiatement — l'anti-rebond
est levé pour ce cas.

Aucune trace d'exécution n'est jamais renvoyée au client.

### Quota : Redis est inutile ici

Le quota s'appuie sur la base par défaut (`rate_limit_driver = db`). Un backend
Redis existe (`redis`), mais **les mesures montrent qu'il n'apporte rien** à ces
volumes : ne l'active que si tu as déjà un Redis en service. Le mode `auto`
tenterait une connexion Redis à chaque requête, ce qui coûte plus cher que ça ne
rapporte sur un hébergement qui n'en a pas — d'où le défaut `db`. Si Redis est
demandé mais injoignable, le quota retombe sur la base : une entrée ne s'arrête
jamais parce qu'un cache est tombé.

## Choix techniques

### Format du QR

```
SCN1A.K7M2QP4XRT9VB3ZC.H4N8DQ2WKF6YJM5A
│   │ └─ uid, 10 octets ─┘ └─ HMAC tronqué, 80 bits ─┘
│   └─ identifiant de clé (rotation sans réimpression)
└─ préfixe de version
```

39 caractères, alphabet base32 **sans `I`, `L`, `O` ni `U`** — pas de confusion
`1`/`I`/`l` ni `0`/`O` quand un vigile recopie le code à la main. Aucune donnée
métier dans le payload : l'`uid` est une valeur aléatoire, tout le reste vit en base.

### Niveau de correction d'erreur : Quartile

Mesuré sur les QR réellement générés :

| Niveau | Modules | Tolérance aux dégâts |
|---|---|---|
| L (défaut habituel) | 25 × 25 | la plus faible |
| **Q (retenu)** | **29 × 29** | nettement meilleure que L |
| H | 33 × 33 | la meilleure, mais 14 % plus dense |

Une carte passe des semaines dans un portefeuille. Le niveau L par défaut cède
trop vite. H a été écarté : à 33 modules sur un QR imprimé à 34 mm, chaque module
tombe à 1,03 mm — trop fin pour une impression bon marché lue de nuit, en biais.

*Note mesurée : les dégâts sur les trois carrés de repérage rendent un QR
illisible quel que soit le niveau de correction. La redondance protège les
données, pas le repérage.*

### L'invalidation atomique

C'est la ligne qui décide si le système tient ou pas :

```php
UPDATE cards SET status = 'used', used_at = ?, used_by_device = ?
 WHERE uid = ? AND status = 'active'
```

On lit ensuite `rowCount()`. Le moteur garantit qu'**une seule** transaction peut
faire passer une ligne de `active` à `used`.

Un `SELECT` suivi d'un `UPDATE` laisserait passer les deux : entre la lecture et
l'écriture, les deux processus voient la carte encore active. C'est l'erreur
classique, et elle annule tout l'intérêt du système.

Le test `ConcurrencyTest` lance 10 vrais processus (`pcntl_fork`) sur la même
carte et vérifie qu'exactement un est admis.

### Pourquoi le secret ne descend pas dans le téléphone

Mettre le secret HMAC dans le navigateur permettrait de vérifier les signatures
hors-ligne. Mais le premier vigile qui ouvre la console repart avec la clé
maîtresse et peut fabriquer des cartes à l'infini.

Le téléphone reçoit donc une liste d'empreintes (`SHA-256` tronqué), jamais la
clé. Au pire, un téléphone volé apprend combien de cartes existent.

---

## Tests

```bash
vendor/bin/phpunit
```

59 tests. Les plus importants :

- **`ConcurrencyTest`** — 10 processus concurrents sur la même carte copiée :
  exactement un admis. C'est la preuve que l'anti-copie tient sous charge.
- **`ThroughputTest`** — 20 processus sur 20 cartes différentes : tous admis,
  zéro erreur, et le débit est affiché. Plus le régime mélangé (trafic normal +
  une carte copiée présentée à plusieurs portes) et la non-régression du quota
  derrière une même IP.
- `TokenTest` — signature altérée, tronquée, mauvaise clé, rotation de clé.
- `OfflineSyncTest` — litiges, non-régression sur le rejeu de file, absence de
  fuite d'IP vers les téléphones.
- `CardRepositoryTest` — dont la survie de l'heure et de la porte quand la ligne
  de journal a été perdue.
- `RateLimiterTest` — plafonds, fenêtres, choix du backend, repli quand Redis ne
  répond pas.

### Faire tourner la suite sur MySQL aussi

Par défaut seul SQLite est testé. Pour couvrir les deux moteurs :

```bash
SCANNEM_TEST_MYSQL='mysql:host=127.0.0.1;dbname=scannem_test;charset=utf8mb4' \
SCANNEM_TEST_MYSQL_USER=scannem \
SCANNEM_TEST_MYSQL_PASS='...' \
vendor/bin/phpunit
```

Sans ces variables, les cas MySQL sont **sautés** plutôt qu'en échec, pour que la
suite reste exécutable partout.

---

## Structure

```
public/          racine web (le seul dossier exposé)
  scan/          PWA vigile : caméra, hors-ligne, verdicts plein écran
  admin/         → app/admin
  .htaccess      réécriture vers index.php quand la racine web pointe ici
src/             Token, CardRepository, Auth, RateLimiter, OfflinePack, Url…
app/amorce.php   version de PHP, dépendances, préfixe d'installation
app/api/         redeem, verify, enroll, pack, sync
app/admin/       interface organisateur
bin/             install.php, generate-batch.php
storage/         HORS webroot : base, secret, exports
tests/
```

`app/amorce.php` s'exécute avant tout le reste, et **avant** de savoir si la
version de PHP permet de charger `src/`. Elle est donc écrite en PHP 7 : c'est
la seule façon de transformer deux pannes muettes — PHP trop ancien, `vendor/`
absent — en messages lisibles plutôt qu'en page blanche.

---

## Fabriquer l'archive de déploiement

```bash
php bin/build-release.php
```

Produit `dist/scannem-AAAAMMJJ.zip` : dépendances de production installées, tests
et documentation des bibliothèques élagués, points d'entrée réels pour `/admin/`
et `/api/...`, `.htaccess` de protection et dossier de données vide.

Le script **vérifie ensuite que l'archive élaguée fonctionne** (génération SVG,
planche d'impression, PNG) avant de la déclarer prête, puis restaure les
dépendances de développement pour que les tests puissent tourner.

Mesuré : **~200 fichiers, 0,4 Mo** — contre 2,8 Go et 5 600 fichiers pour
l'arborescence de développement. L'écart vient presque entièrement des
dépendances de test et d'une police de 15,7 Mo qu'endroid embarque pour les
libellés, dont Scannem ne se sert pas.

## Exploitation

- **Sauvegardez `storage/config.php`** hors du serveur. Sans lui, aucune carte
  imprimée n'est vérifiable.
- **Supprimez les exports** (`storage/exports/`) après impression.
- **Rotation de clé** : ajoutez une clé dans `keys`, passez `active_key_id`
  dessus. Les anciennes cartes restent valides tant que leur `key_id` figure au
  trousseau ; retirez-le pour les invalider d'un coup.
- **Téléphone perdu** : désactivez-le dans Appareils, ses scans sont refusés
  immédiatement. Attention, s'il est hors-ligne il conserve sa file locale.
- **Quotas** : 120 scans/min par appareil, 240/min par IP. Ajustables dans
  `storage/config.php`.

## Licence

MIT.
