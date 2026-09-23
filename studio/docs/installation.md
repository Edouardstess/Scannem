# Installation

Trois situations sont couvertes : hébergement mutualisé (le cas courant),
serveur dédié ou VPS, et poste de développement.

---

## 1. Pré-requis

| Élément | Requis | Sans lui |
|---|---|---|
| PHP 8.3+ | oui | l'application ne démarre pas |
| PDO + `pdo_mysql` | oui | pas de base de données |
| `gd` ou `imagick` | oui | aucun import de photo n'est possible |
| `fileinfo` | oui | la validation des imports est dégradée |
| `zip` | recommandé | pas de téléchargement groupé |
| `exif` | recommandé | orientation et date de prise de vue perdues |
| `sodium` ou `openssl` | recommandé | un lien de galerie ne peut plus être réaffiché après sa création |

Vérifiez d'un coup :

```bash
php bin/console.php check
```

---

## 2. Base de données

```sql
CREATE DATABASE photographer CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'photographer'@'localhost' IDENTIFIED BY 'un-mot-de-passe-long';
GRANT ALL PRIVILEGES ON photographer.* TO 'photographer'@'localhost';
FLUSH PRIVILEGES;
```

Sur un hébergement mutualisé, créez la base depuis le panneau de contrôle et
notez les quatre valeurs : hôte, nom, utilisateur, mot de passe.

---

## 3. Configuration

```bash
cp .env.example .env
php bin/console.php key:generate
```

Copiez la ligne `APP_KEY=base64:…` affichée dans `.env`, puis renseignez :

```ini
APP_URL=https://photographe.com     # sans slash final ; les liens en dépendent
APP_ENV=production
APP_DEBUG=false

DB_HOST=localhost
DB_DATABASE=photographer
DB_USERNAME=photographer
DB_PASSWORD=…

SESSION_SECURE=true                 # dès que le site est en HTTPS
```

`APP_URL` est utilisée pour construire les liens de galerie. Une valeur
incorrecte produit des liens qui ne fonctionnent pas chez le client.

`.env` ne doit jamais être versionné ni accessible par le web. Le `.htaccess`
à la racine le bloque, mais la bonne configuration reste de pointer la racine
web sur `public/`.

---

## 4. Emplacement du stockage privé

C'est le point le plus important de l'installation.

Par défaut, les photographies sont écrites dans `storage/private/`, à côté de
`public/` et non dedans. Si votre hébergeur impose que le site soit servi
depuis un dossier qui contient tout le projet, **déplacez le stockage en
dehors** et pointez `.env` dessus :

```ini
STORAGE_PATH=/home/monuser/photos-privees
```

Le répertoire doit être accessible en écriture par PHP :

```bash
mkdir -p /home/monuser/photos-privees
chmod 750 /home/monuser/photos-privees
```

Après installation, ouvrez `/admin/settings` : le panneau de diagnostic
indique en rouge si le stockage se trouve dans la racine web. Ne mettez pas le
site en service tant que ce point n'est pas vert.

---

## 5. Installation

```bash
php bin/console.php install
```

La commande applique les migrations, insère les paramètres par défaut et crée
le compte administrateur (nom, e-mail, mot de passe de 10 caractères minimum).

Sans accès SSH, importez les fichiers de `database/migrations/` dans l'ordre
numérique via phpMyAdmin, puis créez le compte en visitant temporairement une
page d'installation ou en insérant la ligne à la main :

```sql
INSERT INTO users (name, email, password_hash, role, status, created_at)
VALUES ('Votre nom', 'vous@example.com', '<hash>', 'SUPER_ADMIN', 'active', NOW());
```

Le hash se génère avec :

```bash
php -r "echo password_hash('votre-mot-de-passe', PASSWORD_DEFAULT), PHP_EOL;"
```

---

## 6. Serveur web

### Racine web sur `public/` (recommandé)

Apache :

```apache
<VirtualHost *:443>
    ServerName photographe.com
    DocumentRoot /var/www/studio/public

    <Directory /var/www/studio/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Nginx :

```nginx
server {
    server_name photographe.com;
    root /var/www/studio/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Rien d'autre que public/ ne doit être servi.
    location ~ ^/(app|config|database|routes|storage|tests|vendor)/ {
        deny all;
    }
}
```

### Racine web imposée à la racine du projet

C'est le cas de la plupart des hébergements mutualisés. Le `.htaccess` fourni
à la racine s'en charge : il bloque `app/`, `config/`, `database/`, `routes/`,
`storage/`, `tests/` et `.env`, puis redirige tout le reste vers `public/`.

Vérifiez ensuite, depuis un navigateur :

```
https://photographe.com/.env                → doit renvoyer 403 ou 404
https://photographe.com/app/bootstrap.php   → doit renvoyer 403 ou 404
https://photographe.com/storage/private/    → doit renvoyer 403 ou 404
```

Si l'un de ces trois renvoie autre chose, arrêtez-vous et corrigez avant de
mettre des photographies de clients sur ce serveur.

---

## 7. Réglages PHP pour les gros fichiers

Un fichier d'appareil photo dépasse souvent les limites par défaut. Dans
`php.ini` ou via `.user.ini` sur un mutualisé :

```ini
upload_max_filesize = 100M
post_max_size = 110M
max_execution_time = 300
memory_limit = 256M
```

`post_max_size` doit rester supérieur à `upload_max_filesize`. Les valeurs
effectives sont affichées dans `/admin/settings`.

L'import se fait fichier par fichier : déposer 250 photographies n'envoie
jamais 250 fichiers dans une seule requête, ce qui évite de buter sur
`post_max_size` et laisse chaque envoi bien à l'intérieur de
`max_execution_time`.

---

## 8. HTTPS

Obligatoire dès qu'un client reçoit un lien : le jeton circule dans l'URL.

```bash
certbot --apache -d photographe.com -d www.photographe.com
```

Ensuite, dans `.env` :

```ini
SESSION_SECURE=true
HSTS_ENABLED=true
```

Activez `HSTS_ENABLED` seulement quand HTTPS fonctionne sur le domaine **et**
ses sous-domaines : l'en-tête est mémorisé par le navigateur pendant un an et
rendrait le site inaccessible en HTTP.

---

## 9. E-mails

Par défaut `MAIL_DRIVER=log` : les messages sont écrits dans `storage/logs/`
et rien n'est envoyé. C'est volontaire, pour qu'une installation neuve ne
tombe pas en échec sur l'envoi.

```ini
MAIL_DRIVER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=…
MAIL_PASSWORD=…
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=contact@photographe.com
MAIL_FROM_NAME="Atelier Lumière"
```

`MAIL_DRIVER=mail` utilise la fonction `mail()` de PHP, souvent suffisante sur
un mutualisé mais moins fiable pour la délivrabilité.

---

## 10. Vérification de bout en bout

Dans l'ordre, après installation :

1. `php bin/console.php check` — tout doit être `[OK]`.
2. Ouvrir `/` : la page d'accueil s'affiche.
3. Se connecter sur `/admin`.
4. Ouvrir `/admin/settings` : le diagnostic ne doit afficher aucun point rouge.
5. Créer un client, un événement, une galerie.
6. Importer trois photographies : les miniatures apparaissent.
7. Sur l'écran de partage, copier le lien de consultation, l'ouvrir dans une
   fenêtre de navigation privée : les photos s'affichent.
8. Dans cette même fenêtre, remplacer `/gallery/` par `/download/` dans l'URL :
   le résultat doit être « Cette galerie n'est plus disponible ».
9. Activer le téléchargement sur la galerie, ouvrir le lien de téléchargement :
   récupérer une photo, puis l'archive complète.
10. Révoquer le lien de consultation, le recharger : il doit être refusé.

Si le point 8 ne se comporte pas comme décrit, n'ouvrez pas le service.

---

## 11. Entretien

```bash
php bin/console.php maintenance
```

Purge les archives ZIP temporaires et les compteurs de limitation expirés. Les
archives sont aussi purgées automatiquement à chaque nouvelle demande, donc
cette tâche n'est pas indispensable — mais une tâche planifiée quotidienne
garde le disque propre sur un site peu fréquenté :

```cron
0 4 * * * cd /var/www/studio && php bin/console.php maintenance >/dev/null 2>&1
```

Les mêmes actions sont disponibles depuis `/admin/settings`.

### Sauvegardes

Deux choses à sauvegarder, ensemble :

- la base de données (`mysqldump`) ;
- le contenu de `storage/private/originals/`.

Les aperçus et miniatures sont régénérables à partir des originaux ; les
originaux ne sont régénérables à partir de rien.

---

## 12. Mise à jour

```bash
git pull
php bin/console.php migrate
php bin/console.php check
```

Les migrations sont incrémentales et ne rejouent jamais un fichier déjà
appliqué. Sauvegardez la base avant.
