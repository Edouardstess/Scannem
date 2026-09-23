# L'ENFANT VISUAL — site vitrine et livraison privée de photographies

Application web pour photographe professionnel : un site vitrine, un portfolio,
et surtout un système de livraison de photographies aux clients où **les
fichiers originaux ne sont jamais exposés**.

PHP 8.3+, MySQL 8+, aucun framework, aucune dépendance à l'exécution.

---

## Ce que fait ce système, et ce qu'il ne fait pas

Cette section décrit le modèle de sécurité réel. Lisez-la avant de déployer.

### Les originaux sont inaccessibles. Réellement.

Les fichiers sources ne vivent pas dans le dossier servi par Apache. Ils sont
stockés dans `storage/private/originals/`, en dehors de la racine web, et
délivrés uniquement par PHP après vérification. Aucune URL ne mène au fichier :
il n'y a pas de chemin à deviner, parce qu'il n'y a pas de chemin.

### Deux liens, deux niveaux d'accès

Chaque galerie possède deux liens distincts, générés à sa création :

```
Consultation   https://photographe.com/gallery/a8K29xPq…
Téléchargement https://photographe.com/download/X92Lm7Pk…
```

Le lien de consultation ne peut **pas** être transformé en lien de
téléchargement. Ce n'est pas une question d'interface : le serveur refuse de
délivrer un original à un jeton de consultation, quelle que soit l'URL
demandée. Les deux liens sont indépendants, révocables et régénérables
séparément.

Le lien de téléchargement existe dès le départ mais reste inerte tant que le
photographe n'a pas activé le téléchargement sur la galerie. C'est le
déroulement prévu : on envoie d'abord la consultation, on ouvre le
téléchargement ensuite, sans avoir à renvoyer un nouveau lien.

### Ce que ce système ne peut pas empêcher

**Une capture d'écran.** Un client qui voit une photographie peut la
photographier. C'est vrai de toute galerie en ligne, partout, et aucun
dispositif n'y change rien.

Ce qu'on protège, c'est le **fichier source** : sa résolution complète, ses
métadonnées, sa qualité d'impression. Un aperçu capturé à l'écran n'est pas un
fichier de 40 Mpx.

La désactivation du clic droit n'est pas implémentée comme une protection et ne
doit pas être considérée comme telle. La sécurité est entièrement côté serveur.

### Un lien partagé est un lien partagé

Un jeton est une capacité au porteur : quiconque l'obtient entre. Si un client
transfère son lien, le destinataire y accède. Les défenses disponibles sont le
mot de passe de galerie, l'expiration, et la révocation immédiate — toutes
présentes, toutes à activer selon le contexte.

---

## Démarrage rapide

### Avec un accès SSH

```bash
cp .env.example .env
php bin/console.php key:generate     # copiez la ligne APP_KEY dans .env
# renseignez DB_* dans .env
php bin/console.php install          # migrations + paramètres + compte admin
php -S localhost:8000 -t public
```

### En local avec XAMPP / WAMP / MAMP

Décompressez dans `htdocs/studio/` (ou tout autre nom), démarrez Apache et
MySQL, créez une base vide dans phpMyAdmin, puis ouvrez
`http://localhost/studio/`. Le site vous redirige vers l'installateur.

Aucune configuration d'URL n'est nécessaire : l'application détecte le
sous-dossier où elle est installée et construit ses liens en conséquence.
Pour les données de démonstration, lancez ensuite `php bin/console.php seed`
depuis le dossier `studio/`.

### Sans accès SSH (hébergement mutualisé)

Déposez les fichiers par FTP, créez une base MySQL vide depuis le panneau de
votre hébergeur, puis ouvrez `https://votre-domaine/install.php`. L'installateur
vérifie le serveur, teste la connexion, écrit le `.env` et crée votre compte.

**Supprimez ensuite `public/install.php`.** Il refuse de s'exécuter une fois un
compte créé, mais le laisser en place reste une mauvaise idée — l'application
vous le rappellera dans ses paramètres tant qu'il est présent.

Pour découvrir l'application avec des données factices :

```bash
php bin/console.php seed
```

Le seeder crée un photographe, deux clients, deux événements, une galerie de
12 photographies (générées, aucune personne réelle), un portfolio et trois
prestations. Il affiche les deux liens de la galerie et son mot de passe.

### Sur ByetHost (hébergement gratuit)

Guide pas à pas, limites de l'offre gratuite (10 Mo par photo, e-mails
restreints) et dépannage : [`docs/byethost.md`](docs/byethost.md).

Documentation complète : [`docs/installation.md`](docs/installation.md).

---

## Le parcours complet

**Photographe**

1. Se connecte sur `/admin`.
2. Crée un client, puis un événement, puis une galerie.
3. Dépose ses photographies (glisser-déposer, une requête par fichier,
   progression et reprise par fichier).
4. Le système génère miniature (400 px) et aperçu (1600 px) pour chacune, plus
   leur équivalent WebP quand le serveur sait le produire ; l'original n'est
   jamais modifié.
5. Récupère les deux liens sur l'écran de partage, copie celui de consultation
   et l'envoie au client.
6. Plus tard, active le téléchargement : le lien déjà transmis devient actif.

**Client**

1. Ouvre le lien reçu, saisit le mot de passe si la galerie en a un.
2. Parcourt les photographies : grille, lightbox, zoom, plein écran,
   navigation au clavier et au doigt. Son navigateur reçoit du WebP s'il
   l'accepte — environ deux tiers de poids en moins — et du JPEG sinon.
3. Marque ses préférées si la sélection est activée.
4. Avec le lien de téléchargement : récupère une photo, une sélection, ou toute
   la galerie en archive ZIP.

**Photographe, ensuite**

Consulte les statistiques : consultations, téléchargements, dernière ouverture,
sélection du client, et un journal d'audit de ce qui s'est passé.

---

## Architecture

Monolithe modulaire. MVC, couche de services, dépôts (repositories).

```
app/
  Core/          Router, Request, Response, Database, Session, Auth, View,
                 Validator, Csrf, Config, Logger, FileStorage, Encrypter, Migrator
  Controllers/   HTTP uniquement : déléguer et répondre
  Validators/    Règles de validation, une classe par formulaire
  Services/      Toute la logique métier
  Repositories/  Accès aux données, PDO, requêtes préparées
  Middleware/    Sécurité transverse (auth, CSRF, permissions, en-têtes)
  Models/        Énumérations du domaine et matrice RBAC
  DTO/           Objets de transfert (GalleryAccess)
  Views/         Templates PHP
config/          Configuration, alimentée par .env
database/        Migrations SQL et seeders
public/          Seul dossier exposé au web
routes/          Table de routage
storage/private/ Photographies. Jamais servi directement.
tests/           Suite de tests sans dépendance
```

Détail : [`docs/architecture.md`](docs/architecture.md).
Modèle de sécurité : [`docs/security.md`](docs/security.md).
Routes et endpoints : [`docs/api.md`](docs/api.md).

---

## Tests

```bash
php tests/run.php              # tout
php tests/run.php TokenTest    # une classe
```

197 tests, 488 assertions, aucune dépendance externe. Les tests critiques
vérifient notamment :

| Ce qui est vérifié | Où |
|---|---|
| Un jeton VIEW ne peut jamais obtenir un original | `MediaAccessTest` |
| Un jeton DOWNLOAD n'atteint que sa propre galerie | `MediaAccessTest`, `DownloadTest` |
| Un jeton expiré, révoqué, ou d'un autre type est refusé | `TokenTest`, `GalleryAccessTest` |
| Révoquer un lien invalide les URLs déjà émises | `MediaAccessTest` |
| Un visiteur non authentifié n'atteint pas `/admin` | `RoutingTest` |
| Un rôle insuffisant ne peut pas supprimer une galerie | `RbacTest`, `RoutingTest` |
| Un fichier privé n'est pas joignable par son chemin | `UploadTest` |
| Un fichier non-image est refusé à l'import | `UploadTest` |
| Les requêtes passent par des requêtes préparées | `SqlSafetyTest` |
| Les formulaires POST sont protégés contre le CSRF | `CsrfTest` |
| Toute sortie de template passe par un échappeur | `EscapingTest` |
| L'installateur refuse de s'exécuter deux fois | `InstallerTest` |
| Un formulaire ne livre que les champs qu'il déclare | `FormRequestTest` |
| Régénérer un lien tue immédiatement le précédent | `ShareTest` |
| Aucun script inline que la CSP bloquerait dans le navigateur | `CspTest` |
| Les recherches fonctionnent aussi sous MySQL (paramètres répétés) | `PlaceholderTest` |
| Un envoi trop lourd est expliqué, jamais pris pour une session expirée | `EnvironmentTest` |

Les tests automatisés tournent sur SQLite. L'application a en plus été
validée de bout en bout sur **Apache 2.4.58 (mod_rewrite, installation en
sous-dossier `/studio`), PHP 8.3 et MariaDB 10.11** : installateur web,
site public, formulaires, espace d'administration complet (CRUD, import de
photos, liens de partage), galerie client dans un vrai navigateur
(favoris, visionneuse, ZIP) et protection des fichiers internes.

---

## Exigences serveur

| Élément | Requis | Rôle |
|---|---|---|
| PHP | 8.3+ | — |
| PDO + pdo_mysql | oui | base de données |
| GD **ou** Imagick | oui | miniatures et aperçus |
| fileinfo | oui | détection du vrai type des fichiers importés |
| zip | recommandé | téléchargement d'une galerie entière |
| exif | recommandé | orientation et date de prise de vue |
| sodium ou openssl | recommandé | réaffichage des liens de galerie |

Composer n'est pas nécessaire : l'autoloader PSR-4 est intégré. Un dépôt par
FTP fonctionne.

---

## Licence

MIT.
