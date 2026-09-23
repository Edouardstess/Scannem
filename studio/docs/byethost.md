# Mettre L'ENFANT VISUAL en ligne sur ByetHost

Ce guide couvre l'offre **gratuite** de ByetHost (réseau iFastNet). Elle
annonce PHP 8.3, MySQL 8, un SSL gratuit, 5 Go d'espace et **10 Mo au
maximum par fichier envoyé**. Les menus de ByetHost changent parfois de
nom : si un intitulé diffère, cherchez l'équivalent dans le panneau.

L'application a été testée en reproduisant ces limites : envoi plafonné à
10 Mo, mémoire PHP à 64 Mo, et `mail`, `fsockopen`, `putenv`, `ini_set`,
`header_remove` et `disk_free_space` désactivées. Installation, site public,
administration, import de photos, galerie client et ZIP fonctionnent.

---

## 1. Créer la base de données

1. Connectez-vous au panneau de contrôle ByetHost (VistaPanel).
2. Ouvrez **MySQL Databases** et créez une base, par exemple `site`.
3. Notez les quatre informations affichées :

| Information | Exemple | Remarque |
|---|---|---|
| Hôte MySQL | `sql123.byethost7.com` | **pas** `localhost` ni `127.0.0.1` |
| Nom de la base | `b7_12345678_site` | préfixe ajouté par ByetHost |
| Utilisateur | `b7_12345678` | |
| Mot de passe | celui de votre compte d'hébergement | affiché dans l'espace client |

Si le panneau propose de choisir la version de PHP (*PHP Config* ou
*Select PHP version*), choisissez **8.3**.

---

## 2. Envoyer les fichiers

1. Installez [FileZilla](https://filezilla-project.org/) et connectez-vous
   avec les identifiants FTP indiqués dans votre panneau (hôte FTP,
   utilisateur, mot de passe, port 21).
2. Ouvrez le dossier **`htdocs`** de votre domaine et supprimez les fichiers
   d'exemple que ByetHost y a placés (`index2.html`, etc.).
3. Décompressez le ZIP sur votre ordinateur, puis envoyez **le contenu** du
   dossier `lenfant-visual/` dans `htdocs/`. Vous devez obtenir :

```
htdocs/
├── .htaccess        ← indispensable (fichier caché)
├── app/
├── bin/
├── config/
├── database/
├── public/
├── routes/
├── storage/
└── ...
```

Les fichiers commençant par un point (`.htaccess`) sont cachés sur certains
ordinateurs. Vérifiez qu'ils sont bien présents sur le serveur, dans `htdocs/`
**et** dans `htdocs/public/` : sans eux, toutes les pages répondent 404.

Les fichiers privés (photos originales, `.env`, code) restent dans `htdocs/`,
mais les `.htaccess` en interdisent l'accès depuis le web. L'application le
vérifie elle-même dans **Admin → Paramètres → Diagnostic**.

---

## 3. Lancer l'installateur

1. Activez le **SSL gratuit** pour votre domaine dans le panneau, si ce n'est
   pas déjà fait. L'activation peut prendre un moment.
2. Ouvrez `https://votre-domaine/`. Le site vous envoie vers l'installateur.
3. Étape « Base de données » : saisissez l'**hôte MySQL** noté plus haut (pas
   `localhost`), le port `3306`, le nom de la base, l'utilisateur et le mot de
   passe.
4. Créez votre compte administrateur.
5. **Supprimez `htdocs/public/install.php`** avec FileZilla.

À la première visite, ByetHost peut afficher brièvement une page de
vérification anti-robots, puis ajouter `?i=1` à l'adresse. C'est normal : cette
page vient de l'hébergeur, pas du site.

---

## 4. Réglages après l'installation

### Dans Admin → Paramètres

Remplacez les informations d'exemple : nom du photographe, e-mail, téléphone,
adresse, textes, logo et image d'accueil.

Section **Localisation (Google Maps)** : saisissez l'adresse de votre studio
(ou son nom sur Google Maps, ou ses coordonnées GPS). La carte apparaît sur la
page Contact et sur l'accueil, avec les boutons « Itinéraire » et « Ouvrir
dans Google Maps ». Pour placer l'épingle exactement, collez le code de
Google Maps : **Partager → Intégrer une carte → Copier le code HTML**.
Aucune clé d'API Google n'est nécessaire.

### Dans le fichier `.env` (à modifier avec FileZilla)

Une fois `https://` fonctionnel sur votre domaine :

```ini
APP_URL=https://votre-domaine
SESSION_SECURE=true
```

Laissez `HSTS_ENABLED=false`, sauf si le HTTPS fonctionne aussi sur tous vos
sous-domaines.

---

## 5. Les limites de l'offre gratuite

### Photos : 10 Mo par fichier

Un original d'appareil photo dépasse souvent 10 Mo. Exportez vos photos avant
l'import, par exemple depuis Lightroom :

- format JPEG, qualité 80 à 85 % ;
- grand côté entre 3 000 et 4 000 pixels ;
- résultat : environ 2 à 6 Mo par photo.

Une photo trop lourde est refusée **avant** l'envoi, avec un message qui
indique sa taille et la limite.

La mémoire PHP limite aussi le nombre de pixels. Le diagnostic, dans
**Admin → Paramètres**, indique le maximum en mégapixels. Au-delà, la photo
est refusée avec un message clair, sans rien casser.

### E-mails : souvent bloqués

L'offre gratuite restreint fortement l'envoi d'e-mails. Le bouton
« Notifier le client » peut échouer : un message vous le signale.

Dans ce cas, ouvrez **Galerie → Partager**, copiez les deux liens (galerie et
téléchargement) et envoyez-les vous-même par e-mail, SMS ou WhatsApp. Ce
parcours fonctionne toujours.

### Espace disque : 5 Go

Chaque photo occupe son fichier importé, plus une miniature et un aperçu
(environ 0,5 Mo de plus). Avec des photos de 4 Mo, comptez environ
1 000 photos. Supprimez les galeries livrées depuis longtemps pour libérer de
la place.

### En résumé

L'offre gratuite suffit pour démarrer et présenter votre travail. Pour livrer
régulièrement des mariages complets en haute définition, une offre payante
(ByetHost Premium ou un autre hébergeur) lève ces trois limites. L'application
fonctionne sans modification sur n'importe quel hébergement PHP 8.3 + MySQL.

---

## 6. En cas de problème

| Symptôme | Cause probable | Solution |
|---|---|---|
| Toutes les pages en **404** | `.htaccess` absent | renvoyer `htdocs/.htaccess` et `htdocs/public/.htaccess` |
| **Erreur 500** sur tout le site | directive refusée par l'hébergeur | dans les deux `.htaccess`, mettre `#` devant la ligne `Options -Indexes` |
| « Base de données indisponible » | mauvais hôte MySQL | utiliser `sqlXXX.byethost…` du panneau, pas `localhost` |
| Page sans mise en forme | fichiers de `public/assets/` incomplets | renvoyer le dossier `public/assets/` |
| Photo refusée « trop lourde » | limite de 10 Mo | exporter la photo plus légère (voir plus haut) |
| « Notifier le client » échoue | e-mails bloqués | copier les liens depuis « Partager » |

Le journal des erreurs de l'application se trouve dans `htdocs/storage/logs/`.
Téléchargez-le avec FileZilla pour le consulter.
