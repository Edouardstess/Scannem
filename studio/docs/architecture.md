# Architecture

Monolithe modulaire. MVC, couche de services, dépôts. Pas de framework, pas de
dépendance à l'exécution.

---

## 1. Pourquoi cette forme

Le cahier des charges impose PHP natif et un hébergement mutualisé. Deux
conséquences structurantes :

- **Composer n'est pas requis à l'exécution.** L'autoloader PSR-4 est dans
  `app/Core/Autoloader.php` et se charge de tout. Un dépôt par FTP fonctionne.
  Composer reste utilisable pour l'outillage, il n'est jamais indispensable.
- **Pas de couche de compilation.** Les templates sont du PHP. Un cache de
  templates sur un mutualisé est une chose de plus à mal configurer.

Le découpage suit une règle simple : **une raison de changer par couche**.

```
Requête HTTP
    ↓  public/index.php
Application  ──── configuration, gestion des erreurs, variables de vue
    ↓
Router       ──── correspondance de motif + pipeline de middlewares
    ↓
Middleware   ──── en-têtes, CSRF, authentification, permission
    ↓
Controller   ──── délègue et choisit la réponse
    ↓
FormRequest  ──── règles de validation, normalisation des valeurs
    ↓
Service      ──── la logique métier vit ici, et seulement ici
    ↓
Repository   ──── SQL, requêtes préparées
    ↓
PDO → MySQL
```

Un contrôleur qui contiendrait une règle métier serait une erreur : la même
règle serait alors à réimplémenter dans la ligne de commande et dans les tests.

### La couche de validation

Chaque formulaire a sa classe dans `app/Validators/`. Elle déclare ses règles,
ses libellés, les contrôles qu'une règle ne peut pas exprimer (`after()`) et la
forme des valeurs à enregistrer (`transform()`).

Trois conséquences :

- **Anti-affectation de masse.** `data()` ne rend que les clés déclarées. Un
  champ `role` glissé dans un formulaire de client n'atteint jamais le service.
- **Une seule normalisation.** Un e-mail passe en minuscules, une date devient
  `Y-m-d`, un champ optionnel vide devient `null` — au même endroit pour tous
  les appelants.
- **Un contrôleur qui ne nomme aucun FormRequest est un contrôleur qui ne
  valide pas**, et cela se voit à la lecture.

Les dates acceptent le format jour d'abord (`15/06/2026`). `<input type="date">`
soumet du `Y-m-d`, mais il se dégrade en champ texte sur les navigateurs qui ne
le gèrent pas, et un utilisateur français y tape le format français.

---

## 2. Le noyau

| Classe | Responsabilité | Décision notable |
|---|---|---|
| `Autoloader` | PSR-4 sans Composer | rend le déploiement FTP possible |
| `Config` | `.env` + `config/*.php` | l'environnement réel gagne sur le fichier |
| `Database` | connexion PDO unique | `ATTR_EMULATE_PREPARES = false` |
| `Migrator` | migrations SQL | traduit le DDL MySQL vers SQLite à la volée |
| `Router` | routage compilé en regex | 405 distinct du 404 |
| `Request` | lecture des superglobales | `_method` accepté sur POST uniquement |
| `Response` | réponse tamponnée | rien n'est émis avant `send()` |
| `Session` | session durcie | expiration d'inactivité, cookie `HttpOnly` |
| `Auth` | authentification admin | ne connaît pas les clients, par construction |
| `Csrf` | jeton de synchronisation | stable dans la session |
| `View` | templates PHP + layouts | pas d'échappement automatique, donc `e()` obligatoire |
| `Validator` | validation serveur | résultat mémoïsé, erreurs ajoutables |
| `FileStorage` | garde-fou du système de fichiers | seul endroit qui résout un chemin |
| `Encrypter` | chiffrement authentifié | sodium, repli OpenSSL |
| `Logger` | journal applicatif | rédaction automatique des secrets |

### Une seule connexion, une seule résolution de chemin

Deux endroits concentrent volontairement un risque pour qu'il n'y en ait qu'un
à relire :

- `Database` : toute requête passe par `Repository::run()`, qui lie les valeurs.
- `FileStorage::path()` : toute conversion chemin relatif → absolu, avec refus
  de la traversée et des chemins absolus.

---

## 3. Base de données

19 tables. Les clés étrangères portent `ON DELETE CASCADE` là où la suppression
doit se propager.

```
users                        (comptes d'administration — les clients n'en ont pas)
settings                     (contenu et apparence du site, éditables sans toucher au code)

clients ──< events ──< galleries ──< photos ──< photo_variants
                             │           └──< photo_selections
                             ├──< gallery_tokens      (VIEW / DOWNLOAD)
                             ├──< gallery_views
                             └──< download_logs

portfolio_categories ──< portfolio_items
services ──< bookings
messages
audit_logs
rate_limits
```

### Points de conception

**`galleries.cover_photo_id` n'a pas de clé étrangère.** `photos.gallery_id`
pointe déjà vers `galleries` ; une contrainte circulaire ne peut pas être créée
en une passe sur l'un ou l'autre moteur. La référence est nettoyée
explicitement par `GalleryRepository::clearCoverPhoto()` à la suppression d'une
photo.

**Les chemins de stockage sont relatifs.** `photos.storage_path` vaut
`originals/12/3f2a….jpg`. Déplacer le stockage est un changement de `.env`, pas
une migration.

**Les compteurs restent des requêtes, pas des colonnes.** Les vues et
téléchargements sont comptés par `COUNT(*)` sur des colonnes indexées. Une
colonne dénormalisée finirait par diverger, et le volume ici ne le justifie pas.

**Les jetons révoqués sont conservés.** Une ligne révoquée reste en base : le
journal d'audit doit pouvoir rattacher une consultation passée à son lien.

### Un seul schéma, deux moteurs

Les fichiers de `database/migrations/` sont du MySQL canonique. En production
ils sont exécutés tels quels. Quand la connexion est SQLite — la suite de tests,
et une démonstration sans serveur de base — `Migrator::toSqlite()` traduit le
DDL à la volée.

Le DDL reste volontairement dans un sous-ensemble conservateur (pas de `KEY`
en ligne, pas d'`ENUM`, `CREATE INDEX` séparés) pour que cette traduction tienne
en trente lignes et reste vérifiable. La contrepartie — une définition unique du
schéma plutôt que deux qui divergent — vaut cette contrainte.

---

## 4. Les services

La logique métier, un service par domaine.

| Service | Rôle |
|---|---|
| `GalleryService` | cycle de vie d'une galerie, émission des deux liens |
| `PublicImageService` | images publiques : portfolio, prestations, bannière |
| `GalleryAccessService` | **le portillon unique** de toute requête client |
| `TokenService` | génération, vérification, révocation, réaffichage |
| `MediaTokenService` | URLs d'image signées, sans ligne en base |
| `PhotoUploadService` | validation, stockage, génération des variantes |
| `ImageProcessingService` | Imagick ou GD, détecté à l'exécution |
| `WatermarkService` | filigrane, appliqué aux aperçus seulement |
| `StorageService` | vue métier du stockage privé |
| `DownloadService` | diffusion des fichiers en flux, journalisation |
| `ZipService` | archives temporaires |
| `AuditService` | journal d'audit |
| `StatisticsService` | agrégats du tableau de bord |
| `SettingsService` | paramètres du site, avec valeurs par défaut |
| `RateLimiter` | limitation persistante |
| `MailService` | log / mail() / SMTP |

### `GalleryAccessService`, le point central

Chaque requête client — page de galerie, page de téléchargement, chaque image,
chaque archive — passe par ce service. C'est ce qui rend la séparation
VIEW/DOWNLOAD vérifiable : il y a un endroit à lire, et un endroit à corriger.

Il retourne un `GalleryAccess` qui porte la décision **et son motif**, pour que
le contrôleur affiche la bonne page sans rien redériver, et que l'audit
enregistre pourquoi un accès a été refusé.

### Formats d'image

Chaque rendition est écrite en JPEG, et — lorsque le serveur sait le produire —
doublée d'un WebP de mêmes dimensions. `MediaController` choisit selon
l'en-tête `Accept` du navigateur et répond `Vary: Accept`, faute de quoi un
cache partagé servirait du WebP à un navigateur qui ne sait pas le lire.

JPEG reste la base : un hôte sans support WebP, ou une photo importée avant que
la fonction existe, sert simplement le JPEG. En pratique le gain mesuré est de
l'ordre de deux tiers sur les aperçus, ce qui change l'expérience d'une galerie
de 250 images ouverte en 4G.

### Diffusion en flux

`DownloadService::serve()` lit par tranches de 256 Ko et vide les tampons avant
d'émettre. Un original de 60 Mo ne doit pas devoir tenir dans `memory_limit`.
La boucle surveille `connection_aborted()` : un client mobile qui s'en va ne
doit pas continuer à consommer de la bande passante.

`ZipService` ajoute les fichiers par chemin (`ZipArchive::addFile`), jamais en
les lisant en mémoire : une galerie de mariage pèse plusieurs gigaoctets.

---

## 5. Les dépôts

Un dépôt par table ou par agrégat. Tous héritent de `Repository`, qui fournit
`run()`, `select()`, `selectOne()`, `scalar()`, `insert()`, `update()`,
`delete()` — toutes avec liaison de paramètres.

Deux garde-fous portés par la classe de base :

- **`$fillable`** : seules les colonnes déclarées peuvent être écrites. Un champ
  ajouté dans un formulaire n'atteint pas la base.
- **`safeOrderBy()`** : `ORDER BY` ne se paramètre pas, la colonne est donc
  comparée à une liste blanche et retombe sur `id` si elle est inconnue.

### N+1

Les écrans de liste chargent leurs compteurs en une seule requête, par
sous-requêtes corrélées. Les variantes d'une galerie de 250 photos sont
chargées par une seconde requête `IN (…)` et fusionnées en PHP — une jointure
multiplierait les lignes, une requête par photo serait un N+1.

---

## 6. Vues

Templates PHP, héritage par `View::extend()` / sections.

Trois dispositions, volontairement séparées :

- `layouts/public.php` — site vitrine, indexable, navigation complète ;
- `layouts/client.php` — galeries : `noindex`, pas de navigation vers le site ;
- `layouts/admin.php` — administration : `noindex`, barre latérale.

Une galerie client ne partage pas la disposition du site public : elle ne doit
pas être indexée, et n'a pas à ramener vers les pages marketing.

### Échappement

Le moteur n'échappe pas automatiquement. `e()` est donc obligatoire, et un test
(`EscapingTest`) analyse chaque `<?= … ?>` de chaque template pour l'imposer.
Il accepte un échappeur, un littéral, un calcul numérique, une concaténation de
valeurs sûres, ou un ternaire dont les deux branches le sont — et refuse tout
le reste.

### Graphiques

Rendus en SVG côté serveur. La politique de sécurité interdit les scripts en
ligne, donc aucune bibliothèque de graphiques ne peut recevoir ses données par
un bloc `<script>` inline. Les données partent dans un attribut `data-chart` et
`admin.js` ajoute la couche de survol.

Deux séries au maximum, un seul axe. Deux mesures d'échelles différentes
donnent deux graphiques, jamais deux axes verticaux. Les couleurs de séries ont
été validées pour les déficiences de vision des couleurs (ΔE 24,7 protanopie
sur la paire adjacente) et chaque graphique propose ses données en tableau.

---

## 7. Front-end

Trois fichiers, aucune dépendance, aucun CDN hors Google Fonts.

| Fichier | Portée |
|---|---|
| `site.js` | navigation mobile, lightbox du portfolio |
| `gallery.js` | galerie client : pagination, lightbox, favoris, ZIP |
| `admin.js` | barre latérale, confirmations, import, réordonnancement, survol des graphiques |

Tout est de l'amélioration progressive. Sans JavaScript, la première page de la
galerie s'affiche, les photos sont téléchargeables une par une, et tous les
formulaires fonctionnent.

Aucun contrôle de sécurité n'existe côté client. Masquer un bouton de
téléchargement ne protège rien : le serveur refuse l'original de toute façon.

### Import

Un fichier par requête, séquentiellement. Un mutualisé encaisse bien mieux une
grosse requête à la fois que huit en parallèle, chaque fichier a sa propre
progression, et un fichier en échec se réessaie sans renvoyer les 249 autres.

### Téléchargement groupé

L'archive est construite dans une requête, récupérée dans une autre. Construire
et diffuser plusieurs gigaoctets dans la même réponse ne laisse au navigateur
aucun moyen d'afficher une progression ni de réessayer. La poignée retournée
vit dans la session du visiteur : elle n'est pas rejouable par quelqu'un
d'autre.

---

## 8. Erreurs

`set_error_handler` transforme les avertissements en exceptions : un bogue
apparaît pendant le développement au lieu de corrompre des données en silence.

`Application::handle()` attrape tout. Une `HttpException` porte son statut et
rend la page correspondante ; le reste devient un 500, journalisé, avec le
détail affiché **uniquement** si `APP_DEBUG` est actif.

Les requêtes AJAX reçoivent du JSON, pas du HTML.

---

## 9. Évolution vers le SaaS

Le chemin est préparé, pas implémenté — le construire maintenant serait payer
une complexité pour un besoin hypothétique.

**Ce qui est déjà en place**

- La logique est dans les services : une portée par locataire s'ajoute là, pas
  dans les contrôleurs.
- Les dépôts concentrent l'accès aux données : un filtre global se pose dans
  `Repository`.
- Les chemins de stockage sont relatifs et résolus au même endroit : passer à
  S3 ou R2 est une implémentation de `FileStorage`, pas une migration.
- Les permissions sont déjà des permissions, pas des rôles codés en dur.

**Ce qu'il faudra ajouter**

1. Une table `tenants` et une colonne `tenant_id` sur `users`, `clients`,
   `events`, `galleries`, `portfolio_items`, `services`, `settings`.
2. Un filtre appliqué par `Repository`, résolu depuis la session ou le domaine.
3. Un préfixe de locataire dans les chemins de stockage.
4. Un domaine personnalisé résolu en locataire à l'entrée.

Ordre du modèle : `Tenant → Photographer → Clients → Events → Galleries →
Photos`. Les cinq derniers niveaux existent déjà tels quels.

**Ce qu'il ne faut pas faire** : passer aux microservices. Le système tient
dans la tête d'un développeur PHP expérimenté, et c'est une propriété à
conserver.
