# Routes et API interne

Il n'y a pas d'API publique. Les endpoints JSON listés ici sont consommés par
le JavaScript de l'application et protégés par les mêmes contrôles que les
pages.

Toutes les réponses portent les en-têtes de sécurité décrits dans
[`security.md`](security.md). Toute requête POST, PUT ou DELETE exige un jeton
CSRF, dans le champ `_token` ou dans l'en-tête `X-CSRF-Token`.

---

## 1. Site public

| Méthode | Route | Description |
|---|---|---|
| GET | `/` | Accueil |
| GET | `/portfolio` | Portfolio complet |
| GET | `/portfolio/{slug}` | Portfolio filtré par catégorie |
| GET | `/services` | Liste des prestations |
| GET | `/services/{slug}` | Détail d'une prestation |
| GET | `/a-propos` | Présentation |
| GET | `/contact` | Formulaire de contact |
| POST | `/contact` | Envoi du message |
| GET | `/reservation` | Formulaire de réservation |
| POST | `/reservation` | Envoi de la demande |
| GET | `/espace-client` | Saisie d'un lien ou code de galerie |
| POST | `/espace-client` | Redirige vers la galerie correspondante |
| GET | `/robots.txt` | Interdit l'indexation des galeries |
| GET | `/sitemap.xml` | Pages publiques uniquement |

`/contact` et `/reservation` portent un champ appât (`website`) masqué. Rempli,
la requête reçoit la page de succès et n'est pas enregistrée.

---

## 2. Galeries client

Aucune authentification : l'autorisation vient du jeton dans l'URL.

| Méthode | Route | Jeton requis |
|---|---|---|
| GET | `/gallery/{token}` | VIEW |
| POST | `/gallery/{token}/unlock` | VIEW |
| GET | `/gallery/{token}/photos` | VIEW |
| POST | `/gallery/{token}/select` | VIEW, sélection activée |
| GET | `/download/{token}` | DOWNLOAD, téléchargement activé |
| POST | `/download/{token}/unlock` | DOWNLOAD |
| GET | `/download/{token}/photos` | DOWNLOAD |
| POST | `/download/{token}/archive` | DOWNLOAD |
| GET | `/download/{token}/archive/{handle}` | DOWNLOAD + session |

### Codes de réponse

| Code | Signification |
|---|---|
| 200 | Accès accordé |
| 401 | Session d'administration requise (routes `/admin` en AJAX) |
| 403 | Mot de passe de galerie non saisi, ou action non autorisée |
| 404 | Jeton inconnu ou malformé |
| 410 | Jeton révoqué, expiré, du mauvais type, ou galerie fermée |
| 419 | Jeton CSRF invalide ou session expirée |
| 429 | Trop de tentatives |

Un jeton inconnu renvoie 404, un jeton refusé 410 : la distinction est utile au
client et au support, et ne révèle rien qu'un attaquant ne puisse déjà déduire.

### `GET /gallery/{token}/photos`

Pagination de la grille, 60 photos par page.

```
GET /gallery/a8K29…/photos?page=2
X-Requested-With: XMLHttpRequest
```

```json
{
  "photos": [
    {
      "id": 142,
      "name": "IMG_0042.jpg",
      "thumb_url": "https://…/media/thumb/MS40…",
      "preview_url": "https://…/media/preview/MS40…",
      "download_url": null,
      "width": 1600,
      "height": 1066,
      "orientation": "landscape",
      "downloadable": true
    }
  ],
  "pagination": { "total": 250, "page": 2, "per_page": 60, "pages": 5, "has_more": true }
}
```

`download_url` vaut `null` sur un lien de consultation. Ce n'est pas la
protection — c'est de la commodité ; le contrôle est le refus serveur décrit
dans [`security.md`](security.md).

Les champs exposés sont volontairement limités : ni chemin de stockage, ni
empreinte, ni taille de l'original, ni EXIF au-delà de ce qui est nécessaire à
l'affichage.

### `POST /gallery/{token}/select`

```json
{ "photo_id": 142 }
```

```json
{ "selected": true, "total": 17 }
```

Bascule le favori. Les sélections sont attachées au jeton, de sorte que deux
personnes ayant reçu le même lien ne s'écrasent pas mutuellement.

### `POST /download/{token}/archive`

```json
{ "photo_ids": [142, 143, 144] }
```

Une liste vide signifie « toutes les photos téléchargeables de la galerie ».
Chaque identifiant est revérifié contre la galerie du jeton : un identifiant
appartenant à une autre galerie est simplement ignoré par la requête.

```json
{
  "handle": "e86e1c0e22cea9fc863bf474b7590043",
  "url": "https://…/download/X92Lm7…/archive/e86e1c0e…",
  "filename": "galerie-mariage-jean-marie.zip",
  "count": 12,
  "bytes": 1413904,
  "size": "1.3 Mo"
}
```

L'archive se récupère ensuite par `GET` sur `url`. La poignée vit dans la
session du visiteur : elle n'est pas rejouable ailleurs. L'archive est purgée
après `ZIP_TTL_SECONDS` (une heure par défaut).

Erreurs : `422` sélection vide ou trop volumineuse, `429` trop de demandes,
`501` extension ZIP absente.

---

## 3. Diffusion des images

| Méthode | Route | Sert |
|---|---|---|
| GET | `/media/thumb/{token}` | miniature ~400 px |
| GET | `/media/preview/{token}` | aperçu ~1600 px, filigrané si activé |
| GET | `/media/download/{token}` | fichier original |

`{token}` est une charge signée, pas un identifiant :

```
base64url(photoId . galleryTokenId . variante . expiration) . "." . base64url(HMAC-SHA256)
```

La signature prouve seulement que l'URL a été émise par l'application. À chaque
requête, le serveur recharge le jeton de galerie et revérifie révocation,
expiration, statut, mot de passe, appartenance de la photo, et — pour un
original — le type du jeton, l'activation du téléchargement et le drapeau de la
photo.

`/media/download` refuse systématiquement un jeton de galerie de type `VIEW`,
quelle que soit la signature présentée.

---

## 4. Administration

Toutes ces routes exigent une session d'administration, et la permission
indiquée.

### Authentification

| Méthode | Route | Permission |
|---|---|---|
| GET | `/admin/login` | — (visiteur) |
| POST | `/admin/login` | — (visiteur) |
| POST | `/admin/logout` | authentifié |
| GET | `/admin/profile` | authentifié |
| POST | `/admin/profile/password` | authentifié |

### Clients — `client.manage`

| Méthode | Route |
|---|---|
| GET | `/admin/clients` |
| GET | `/admin/clients/create` |
| POST | `/admin/clients` |
| GET | `/admin/clients/{id}` |
| GET | `/admin/clients/{id}/edit` |
| PUT | `/admin/clients/{id}` |
| DELETE | `/admin/clients/{id}` |

Supprimer un client supprime ses événements, ses galeries, ses photographies et
les fichiers correspondants.

### Événements — `event.manage`

Même forme : `/admin/events`, `create`, `{id}`, `{id}/edit`, `PUT`, `DELETE`.

### Galeries — `gallery.update`, `gallery.create`, `gallery.delete`

| Méthode | Route | Permission |
|---|---|---|
| GET | `/admin/galleries` | `gallery.update` |
| GET | `/admin/galleries/create` | `gallery.create` |
| POST | `/admin/galleries` | `gallery.create` |
| GET | `/admin/galleries/{id}` | `gallery.update` |
| GET | `/admin/galleries/{id}/edit` | `gallery.update` |
| PUT | `/admin/galleries/{id}` | `gallery.update` |
| DELETE | `/admin/galleries/{id}` | `gallery.delete` |
| GET | `/admin/galleries/{id}/share` | `gallery.update` |
| POST | `/admin/galleries/{id}/status` | `gallery.update` |
| POST | `/admin/galleries/{id}/notify` | `gallery.update` |
| POST | `/admin/galleries/{id}/tokens/{type}/regenerate` | `gallery.update` |
| POST | `/admin/galleries/{id}/tokens/{type}/revoke` | `gallery.update` |

`{type}` vaut `view` ou `download`. Régénérer révoque le lien précédent dans la
même opération.

`POST /admin/galleries/{id}/status` accepte `draft`, `active`, `disabled`,
`archived`. `disabled` et `archived` révoquent les deux jetons.

### Photographies

| Méthode | Route | Permission |
|---|---|---|
| POST | `/admin/galleries/{id}/photos` | `photo.upload` |
| POST | `/admin/galleries/{id}/photos/reorder` | `photo.upload` |
| POST | `/admin/galleries/{id}/cover` | `photo.upload` |
| GET | `/admin/photos/{id}/thumb` | authentifié |
| GET | `/admin/photos/{id}/preview` | authentifié |
| GET | `/admin/photos/{id}/original` | authentifié |
| POST | `/admin/photos/{id}/downloadable` | `photo.upload` |
| DELETE | `/admin/photos/{id}` | `photo.delete` |

#### `POST /admin/galleries/{id}/photos`

`multipart/form-data`, champ `photo`, un fichier par requête.

```json
{
  "uploaded": [
    { "id": 13, "name": "IMG_0042.jpg", "size": "119.5 Ko",
      "width": 1800, "height": 1200, "thumb_url": "https://…/admin/photos/13/thumb" }
  ],
  "failed": [],
  "total": 13
}
```

Un fichier refusé apparaît dans `failed` avec son motif, et la réponse porte
le code `422` si rien n'a pu être importé.

#### `POST /admin/galleries/{id}/photos/reorder`

```json
{ "order": [14, 12, 13, 15] }
```

Les mises à jour sont bornées par l'identifiant de galerie : un identifiant
étranger est ignoré.

### Portfolio et prestations — `portfolio.manage`

`/admin/portfolio`, `/admin/portfolio/create`, `/admin/portfolio/{id}/edit`,
`/admin/portfolio/categories`, et les routes `/admin/services` équivalentes.

### Messages et réservations — `message.manage`

`/admin/messages`, `/admin/messages/{id}`, `/admin/messages/{id}/status`,
`/admin/bookings`, `/admin/bookings/{id}/status`.

### Statistiques — `statistics.view`

`/admin/statistics`, `/admin/statistics/audit`.

### Paramètres — `settings.manage`

`/admin/settings`, `/admin/settings/maintenance`.

`maintenance` accepte `prune_temporary`, `purge_rate_limits`, `purge_audit`.

---

## 5. Ligne de commande

```bash
php bin/console.php migrate          # appliquer les migrations
php bin/console.php migrate:status   # lister les migrations en attente
php bin/console.php seed             # données de démonstration
php bin/console.php key:generate     # générer APP_KEY
php bin/console.php user:create      # créer un compte
php bin/console.php install          # installation guidée
php bin/console.php maintenance      # purges
php bin/console.php routes           # lister les 93 routes
php bin/console.php check            # diagnostic (code de sortie 1 si un point échoue)
```

`check` est utilisable dans un script de déploiement : il sort avec le code 1
si un contrôle échoue.
