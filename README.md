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

## Installation

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

49 tests. Les plus importants :

- **`ConcurrencyTest`** — 10 processus concurrents sur la même carte copiée :
  exactement un admis. C'est la preuve que l'anti-copie tient sous charge.
- `TokenTest` — signature altérée, tronquée, mauvaise clé, rotation de clé.
- `OfflineSyncTest` — litiges, non-régression sur le rejeu de file, absence de
  fuite d'IP vers les téléphones.
- `RateLimiterTest` — plafonds et remise à zéro de fenêtre.

---

## Structure

```
public/          racine web (le seul dossier exposé)
  scan/          PWA vigile : caméra, hors-ligne, verdicts plein écran
  admin/         → app/admin
src/             Token, CardRepository, Auth, RateLimiter, OfflinePack…
app/api/         redeem, verify, enroll, pack, sync
app/admin/       interface organisateur
bin/             install.php, generate-batch.php
storage/         HORS webroot : base, secret, exports
tests/
```

---

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
