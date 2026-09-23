# Modèle de sécurité

Ce document décrit ce qui est protégé, comment, et ce qui ne l'est pas.

---

## 1. Le problème central

Un photographe livre des fichiers qui ont de la valeur. Il veut que son client
les regarde avant de les payer, ou avant qu'il n'ouvre le téléchargement. Il
faut donc montrer les images sans donner les fichiers.

La réponse est une séparation stricte entre **voir** et **obtenir** :

| | Consultation | Téléchargement |
|---|---|---|
| Fichier servi | aperçu 1600 px, JPEG ré-encodé | fichier original |
| Métadonnées | supprimées par le ré-encodage | intactes |
| Filigrane | optionnel | jamais |
| Route média acceptée | `thumb`, `preview` | `thumb`, `preview`, `download` |

Un lien de consultation ne peut pas être transformé en lien de téléchargement.
Ce n'est pas une différence d'interface : les deux jetons sont des lignes
distinctes en base, de types distincts, et `MediaController` refuse de servir
un original à un jeton de type `VIEW`.

---

## 2. Où vivent les fichiers

```
storage/private/
├── originals/{galerie}/{32 caractères aléatoires}.jpg   ← jamais servi directement
├── previews/{galerie}/…                                  ← servi par PHP, après contrôle
├── thumbnails/{galerie}/…                                ← servi par PHP, après contrôle
└── temporary/                                            ← archives ZIP, purgées par TTL
```

Ce répertoire est **en dehors de la racine web**. Aucune règle de serveur n'est
nécessaire pour le protéger : il n'y a pas d'URL qui y mène.

Trois protections s'ajoutent par précaution, pour le cas où une installation le
placerait malgré tout dans la racine web :

1. un `.htaccess` `Require all denied` écrit automatiquement à la racine du
   stockage, avec `php_flag engine off` ;
2. un `index.html` vide ;
3. un diagnostic dans `/admin/settings` qui affiche l'anomalie en rouge.

Les noms de fichiers sont 16 octets aléatoires en hexadécimal. Le nom envoyé
par le client est conservé en base — pour le `Content-Disposition` du
téléchargement — mais ne devient jamais un chemin. Un import appelé
`../../evil.php.jpg` est stocké sous `originals/4/3f2a…b1.jpg`.

`FileStorage` est le seul endroit qui transforme un chemin relatif en chemin
absolu. Il normalise `..`, vérifie que le résultat reste sous la racine de
stockage, et refuse tout chemin absolu.

---

## 3. Les jetons de galerie

### Génération

```php
$raw = bin2hex(random_bytes(32));   // 256 bits
```

Jamais `md5()`, jamais `sha1()` d'une valeur devinable, jamais un identifiant
de base. Énumérer un espace de 2²⁵⁶ n'a pas de sens.

### Stockage

Trois colonnes, trois rôles :

| Colonne | Contenu | Pourquoi |
|---|---|---|
| `token_hash` | SHA-256 du jeton, unique et indexé | la recherche se fait dessus ; une fuite de base ne donne aucun lien |
| `token_cipher` | le jeton chiffré avec `APP_KEY` | permet de réafficher le lien dans l'écran de partage |
| `token_type` | `VIEW` ou `DOWNLOAD` | la séparation d'accès |

SHA-256 plutôt que bcrypt : l'entrée est déjà à haute entropie, il n'y a rien à
ralentir, et la recherche doit être une requête indexée unique.

La copie chiffrée existe parce que le photographe doit pouvoir recopier son
lien des semaines plus tard sans invalider celui déjà envoyé au client.
`APP_KEY` vit dans `.env`, pas en base : un dump de base seul ne donne toujours
rien. Au déchiffrement, la valeur obtenue est re-hachée et comparée au
`token_hash` de la ligne — un chiffré déplacé d'une ligne à l'autre est
détecté.

Sans `APP_KEY`, le lien n'est simplement plus réaffichable et doit être
régénéré. C'est un désagrément récupérable, pas une faille.

### Vérification

`TokenService::verify()` retourne un motif, pas un booléen :
`valid`, `not_found`, `revoked`, `expired`, `wrong_type`. Le client voit « le
lien a expiré » plutôt que « introuvable », et le journal d'audit enregistre
lequel.

Un jeton mal formé est rejeté sur sa forme, avant toute requête.

---

## 4. Le portillon unique

Toute requête client passe par `GalleryAccessService::resolve()`. Il vérifie,
dans cet ordre :

1. le jeton existe, n'est pas révoqué, n'est pas expiré ;
2. il est du type attendu par la route ;
3. la galerie existe ;
4. la galerie n'a pas dépassé sa date d'expiration ;
5. la galerie est au statut `active` ;
6. si elle a un mot de passe, la session l'a déverrouillée.

Les contrôles 4 et 5 sont volontairement redondants avec l'expiration du
jeton : désactiver une galerie doit fermer tous ses liens d'un coup, sans avoir
à révoquer chaque jeton un par un.

L'expiration est évaluée à la lecture, jamais par une tâche planifiée. Une
tâche qui ne s'exécute pas laisserait des galeries ouvertes au-delà de leur
date — exactement la panne qu'il ne faut pas avoir.

---

## 5. Les URLs d'image

Une galerie affiche des centaines d'images. Créer une ligne en base par URL
serait absurde. L'URL média est donc une charge signée :

```
photoId . jetonId . variante . expiration      →  base64url
HMAC-SHA256(charge, APP_KEY)                   →  base64url
```

**La signature ne prouve qu'une chose : cette URL a été émise par cette
application.** Ce n'est pas l'autorisation. À chaque requête,
`MediaController` recharge le jeton de galerie nommé dans la charge et
revérifie tout : révocation, expiration, statut de la galerie, déverrouillage
du mot de passe, appartenance de la photo à cette galerie, et — pour un
original — que le jeton est bien de type `DOWNLOAD`, que la galerie autorise le
téléchargement, et que la photo est individuellement téléchargeable.

Conséquences vérifiées par les tests :

- modifier la charge invalide la signature ;
- passer la variante à « original » invalide la signature ;
- une URL émise pour un jeton `VIEW` et signée pour un original est quand même
  refusée, parce que le contrôle serveur est indépendant de la signature ;
- révoquer un lien rend immédiatement inutiles toutes les URLs déjà affichées
  dans le navigateur du client.

La comparaison de signature utilise `hash_equals()` : un rejet rapide à la
première mauvaise lettre laisserait deviner la signature octet par octet.

---

## 6. Import de fichiers

L'ordre des contrôles est délibéré :

1. erreur PHP (`UPLOAD_ERR_*`) ;
2. taille, contre la limite configurée ;
3. **type MIME réel**, lu dans les octets du fichier avec `finfo`, jamais
   l'en-tête `Content-Type` envoyé par le navigateur ni l'extension ;
4. le fichier se décode effectivement comme une image ;
5. seulement ensuite, déplacement dans le stockage privé.

Un fichier PHP renommé `.jpg` est rejeté à l'étape 3. Un fichier texte
également. Un polyglotte `GIF89a` suivi de code PHP est rejeté à l'étape 4.

Si quoi que ce soit échoue après l'écriture du fichier, la transaction est
annulée **et** le fichier est effacé : pas d'orphelin sur le disque.

Les images publiques (portfolio, prestations, bannière) vivent dans
`public/assets/uploads/` puisqu'elles sont destinées à être vues. Elles sont
systématiquement ré-encodées, ce qui normalise le format, supprime les
métadonnées — dont les coordonnées GPS — et garantit que les octets stockés
sont bien une image. Un `.htaccess` y désactive l'exécution PHP.

---

## 7. Métadonnées

L'orientation EXIF et la date de prise de vue sont lues et utilisées.

Les coordonnées GPS ne sont **jamais** stockées ni exposées. Une galerie de
mariage qui diffuserait la position du domicile des mariés serait un incident
de confidentialité, pas une fonctionnalité.

Les aperçus sont ré-encodés en JPEG avec suppression des métadonnées : ce qui
arrive au navigateur du client ne contient ni GPS, ni numéro de série
d'appareil, ni historique de retouche.

---

## 8. Authentification

- `password_hash()` / `password_verify()`, algorithme par défaut de PHP,
  re-hachage automatique après une évolution de cet algorithme ;
- `session_regenerate_id(true)` à la connexion et au déverrouillage d'une
  galerie ;
- cookie `HttpOnly`, `SameSite=Lax`, `Secure` dès que `SESSION_SECURE=true` ;
- une comparaison de mot de passe factice est effectuée quand le compte
  n'existe pas, pour que le temps de réponse ne révèle pas les adresses
  connues ;
- un compte suspendu perd sa session au prochain accès, sans attendre
  l'expiration ;
- l'empreinte du navigateur est liée à la session ; en cas de changement, la
  session est détruite plutôt que réutilisée.

### Limitation des tentatives

Les compteurs sont **en base**, pas en session : un attaquant contrôle ses
propres cookies, un compteur en session ne bloque personne.

| Clé | Limite | Fenêtre |
|---|---|---|
| connexion, par e-mail | 5 | 15 min |
| connexion, par IP | 20 | 15 min |
| mot de passe de galerie | 10 | 15 min |
| formulaire de contact | 5 | 1 h |
| espace client (recherche de jeton) | 20 | 1 h |
| création d'archive ZIP | 20 | 15 min |

Limiter par e-mail seul permettrait de bloquer volontairement le compte d'un
tiers ; limiter par IP seule permettrait d'essayer un mot de passe sur beaucoup
de comptes. Les deux sont appliquées.

Les clés sont hachées avant stockage : la table ne contient ni adresse e-mail
ni adresse IP en clair à côté d'un compteur d'échecs.

---

## 9. RBAC

Trois rôles, et des permissions vérifiées — jamais des rôles :

| | SUPER_ADMIN | PHOTOGRAPHER | EDITOR |
|---|---|---|---|
| Voir, créer, modifier une galerie | ✓ | ✓ | ✓ |
| Supprimer une galerie | ✓ | ✓ | — |
| Importer des photos | ✓ | ✓ | ✓ |
| Supprimer des photos | ✓ | ✓ | — |
| Portfolio, clients, événements, messages | ✓ | ✓ | ✓ |
| Statistiques | ✓ | ✓ | ✓ |
| Paramètres | ✓ | ✓ | — |
| Gestion des comptes | ✓ | — | — |

Les contrôleurs demandent `gallery.delete`, pas « est-ce un administrateur ».
Ajouter un rôle ne touche qu'à `app/Models/Role.php`.

Un test parcourt la table de routage et vérifie que chaque route `/admin`
porte `AuthMiddleware` — sauf la page de connexion — et qu'aucune route client
ne le porte, puisqu'un client n'a pas de compte.

---

## 10. CSRF

Jeton de synchronisation, un par session, comparé avec `hash_equals()`.

Le middleware est appliqué **globalement**, pas route par route : une nouvelle
route POST ajoutée plus tard est protégée par défaut. C'est la seule manière
que cela reste vrai dans le temps.

Le jeton n'est pas renouvelé à chaque requête : cela casserait l'usage en
plusieurs onglets et les imports parallèles, sans rien apporter face à un
attaquant qui, par construction, ne peut pas le lire.

Le remplacement de verbe (`_method`) n'est accepté que sur un POST. Autoriser
un GET à devenir un DELETE rendrait les actions destructrices atteignables
depuis un simple lien ou un préchargement.

---

## 11. Injection SQL

Tout passe par des requêtes préparées PDO, avec `ATTR_EMULATE_PREPARES` à
`false` pour que la préparation soit réellement faite par le serveur.

Les valeurs écrites sont doublement bornées : la classe `FormRequest` ne rend
que les champs qu'elle déclare, et le dépôt n'écrit que les colonnes de son
`$fillable`. Un champ supplémentaire ajouté à un formulaire est écarté deux
fois.

Les identifiants (table, colonne de tri) ne viennent jamais de l'utilisateur :
`ORDER BY` est comparé à une liste blanche déclarée par chaque dépôt, et une
valeur inconnue retombe sur `id`.

Les caractères génériques `LIKE` sont échappés : une recherche sur `%` cherche
le caractère, elle ne renvoie pas toute la table.

L'écriture est limitée aux colonnes `$fillable` de chaque dépôt : un champ
supplémentaire glissé dans un formulaire n'atteint pas la base.

Deux tests statiques verrouillent ces propriétés : l'un n'autorise qu'une liste
nommée de fragments à être concaténés dans une requête, l'autre refuse tout
`query()` ou `exec()` hors de l'infrastructure.

---

## 12. XSS

Le moteur de template n'échappe pas automatiquement — c'est du PHP. La garantie
repose donc sur `e()`, et un test la vérifie mécaniquement : il analyse chaque
`<?= … ?>` de chaque template et n'accepte que des expressions sûres — un
échappeur, un littéral, un calcul numérique, une concaténation de valeurs sûres,
ou un ternaire dont les deux branches le sont.

Pour les valeurs injectées dans un bloc `<script>`, `ejs()` encode avec
`JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT` : une légende
contenant `</script>` ne peut pas refermer le bloc.

---

## 13. En-têtes

Envoyés sur toutes les réponses, y compris les fichiers :

```
Content-Security-Policy: default-src 'self'; script-src 'self'; object-src 'none'; …
X-Frame-Options: SAMEORIGIN
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: camera=(), microphone=(), geolocation=()
Cross-Origin-Resource-Policy: same-origin
```

`script-src 'self'` : aucun script en ligne. C'est pour cela que les graphiques
sont rendus en SVG côté serveur et animés depuis un fichier `.js` externe.

`nosniff` est répété sur chaque fichier servi : sans lui, un navigateur pourrait
décider qu'une « image » est du HTML et l'exécuter dans l'origine du site.

Les fichiers sont servis avec `Cache-Control: private` — jamais `public` : un
cache partagé ne doit pas conserver les photographies d'un client là où la
requête d'un autre visiteur pourrait être servie depuis ce cache. Les originaux
ajoutent `no-store`.

HSTS est désactivé par défaut. L'envoyer sur un HTTPS à moitié configuré
enferme les visiteurs hors d'un site qui ne peut pas encore les servir.

---

## 14. Journalisation

Deux journaux distincts.

**`audit_logs`** — connexions, créations, modifications, suppressions,
consultations de galerie, téléchargements, générations de ZIP, révocations.
`user_id` est nul quand l'action vient d'un client, qui n'a pas de compte.

Le contexte est filtré avant écriture : aucun jeton en clair, aucun mot de
passe. Un journal d'audit contenant un lien de galerie fonctionnel annulerait
l'intérêt de ne stocker que des empreintes.

**`storage/logs/app-*.log`** — journal technique, avec rédaction automatique
des clés sensibles (mots de passe, jetons, secrets, cookies).

Les messages d'exception de base de données ne sont jamais renvoyés au
navigateur : ils contiennent parfois les identifiants de connexion.

---

## 15. L'installateur web

`public/install.php` est le fichier le plus dangereux du projet : il peut
réécrire les identifiants de base de données et créer un administrateur. Trois
garde-fous :

1. Il refuse de s'exécuter si un fichier verrou existe dans le stockage privé.
2. Il refuse de s'exécuter si la base contient déjà un compte — même si le
   verrou a été supprimé. C'est le contrôle qui compte réellement.
3. Le contrôle est évalué **avant** toute branche POST : aucune requête forgée
   ne peut sauter les étapes.

Il est protégé par CSRF, marqué `noindex`, ne réaffiche jamais le mot de passe
de base de données dans le formulaire, et écrit un `.env` sûr par défaut
(`APP_ENV=production`, `APP_DEBUG=false`, `HSTS_ENABLED=false`, `APP_KEY`
généré aléatoirement).

**Il doit malgré tout être supprimé après l'installation.** L'écran
`/admin/settings` affiche un point rouge critique tant qu'il est présent, et
`php bin/console.php check` sort avec le code 1.

---

## 16. Ce qui n'est pas protégé

**La capture d'écran.** Un client qui voit une image peut la photographier. Ce
qui est protégé, c'est le fichier source : sa résolution, ses métadonnées, sa
qualité d'impression.

**La désactivation du clic droit** n'est pas implémentée comme une protection
et ne doit pas être présentée comme telle à un client.

**Le partage d'un lien.** Un jeton est une capacité au porteur. Les défenses
sont le mot de passe, l'expiration et la révocation.

**Le compte d'un photographe compromis.** Quelqu'un qui obtient les
identifiants d'administration a accès à tout. L'authentification à deux
facteurs n'est pas implémentée dans cette version ; c'est la première chose à
ajouter pour un studio qui gère des clients sensibles.

**Le serveur lui-même.** Un accès au système de fichiers du serveur donne accès
aux originaux. Aucune application ne peut s'en prémunir.

---

## 17. En cas d'incident

**Un lien a été envoyé à la mauvaise personne**
`/admin/galleries/{id}/share` → « Désactiver ». Le lien cesse de fonctionner
immédiatement, y compris pour les pages déjà ouvertes. Puis « Régénérer » pour
en obtenir un nouveau à envoyer au bon destinataire.

**Une galerie entière doit être fermée**
Écran de partage → « Désactiver immédiatement » : la galerie passe en
`disabled` et les deux jetons sont révoqués en une opération.

**Un compte d'administration est compromis**
Passer le compte en `suspended` en base : la session en cours est invalidée au
prochain accès. Puis changer le mot de passe et inspecter `/admin/statistics/audit`.

**Une fuite de base de données est suspectée**
Les jetons sont stockés hachés, mais les copies chiffrées deviennent
déchiffrables si `APP_KEY` a fuité aussi. Dans ce cas : changer `APP_KEY`, puis
régénérer les liens des galeries encore actives. Changer `APP_KEY` rend
également inutilisables toutes les URLs média déjà émises, ce qui est l'effet
recherché.
