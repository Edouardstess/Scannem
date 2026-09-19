# Mise en ligne sur ByetHost

Ce guide couvre ByetHost, mais s'applique tel quel à InfinityFree, AwardSpace,
000webhost et à tout hébergement mutualisé Apache + PHP + MySQL.

---

## Avant de commencer

Il vous faut :

- un compte d'hébergement avec **PHP 8.1 ou supérieur** et **MySQL** ;
- l'accès au panneau de contrôle (VistaPanel chez ByetHost) ;
- le dossier `htdocs/` de ce projet.

Vous n'avez besoin ni de SSH, ni de Composer, ni de npm.

---

## 1. Vérifier la version de PHP

VistaPanel → **Select PHP Version** → choisir **8.1** ou plus récent.

Une version antérieure fera échouer l'installation : l'application utilise des
fonctionnalités introduites en PHP 8.

---

## 2. Créer la base de données

VistaPanel → **MySQL Databases** → créer une base.

Notez soigneusement les quatre valeurs affichées ; l'assistant va les demander :

| Valeur | Exemple |
|---|---|
| Serveur MySQL (*MySQL Host Name*) | `sql113.byethost7.com` |
| Nom de la base (*Database Name*) | `b7_12345678_uep` |
| Utilisateur (*Username*) | `b7_12345678` |
| Mot de passe | celui de votre compte ByetHost |

> Le nom de la base est **imposé** par l'hébergeur. C'est pourquoi le script SQL
> ne contient ni `CREATE DATABASE` ni `USE` : il s'applique à la base que vous
> aurez sélectionnée.

---

## 3. Téléverser les fichiers

VistaPanel → **Online File Manager** (ou un client FTP comme FileZilla).

Ouvrez le dossier `htdocs` de votre domaine, puis envoyez **le contenu** du
dossier `htdocs/` de ce projet — pas le dossier lui-même.

Après le transfert, vous devez voir :

```
htdocs/
├── index.php          ← directement ici, pas dans un sous-dossier
├── install.php
├── .htaccess          ← vérifiez qu'il est bien présent
├── assets/
├── config/
├── core/
├── ...
```

**Si `index.php` se retrouve dans `htdocs/htdocs/index.php`, rien ne
fonctionnera.** Déplacez les fichiers d'un niveau vers le haut.

### Le fichier `.htaccess` est invisible ?

Les gestionnaires de fichiers masquent souvent les fichiers commençant par un
point. Dans le gestionnaire ByetHost, cochez **Show hidden files** ; dans
FileZilla, menu *Serveur* → *Forcer l'affichage des fichiers cachés*. Ce
fichier est indispensable : sans lui, seules la page d'accueil et
`install.php` répondront.

### Transfert plus rapide

Le gestionnaire de fichiers accepte une archive ZIP et sait l'extraire en ligne
(*Upload* puis *Extract*). C'est bien plus rapide que d'envoyer les 89 fichiers
un par un en FTP.

---

## 4. Lancer l'assistant d'installation

Ouvrez `http://votre-domaine.byethost7.com/install.php`.

| Étape | Ce qui se passe |
|---|---|
| 1. Vérification du serveur | Version de PHP, extensions, droits d'écriture, `mod_rewrite` |
| 2. Connexion à la base | Test des identifiants, puis écriture de `config/config.local.php` |
| 3. Création des tables | 14 tables, 3 vues, 625 questions UPD et 89 questions DDE |
| 4. Compte administrateur | Votre nom, votre adresse, votre mot de passe |
| 5. Terminé | Bouton de suppression de l'assistant |

Si l'étape 1 signale que le dossier `config/` n'est pas accessible en écriture :
dans le gestionnaire de fichiers, sélectionnez `config`, menu *Permissions*, et
mettez `755` (ou `777` si `755` ne suffit pas). Vous pouvez aussi créer
`config/config.local.php` à la main à partir de `config.local.example.php`.

---

## 5. Supprimer `install.php`

Le bouton de la dernière étape s'en charge. Si la suppression automatique
échoue, supprimez le fichier depuis le gestionnaire de fichiers.

Tant qu'il est en place, l'assistant refuse déjà de repartir — une installation
existante ne peut pas être écrasée — mais il n'a plus rien à faire sur le
serveur.

---

## 6. Vérifications après mise en ligne

| Adresse | Résultat attendu |
|---|---|
| `/` | Page d'accueil institutionnelle |
| `/login` | Formulaire de connexion |
| `/dashboard` (déconnecté) | Redirection vers `/login` |
| `/adresse-qui-nexiste-pas` | Page 404 de l'application |
| `/config/config.local.php` | **403 Interdit** |
| `/database/schema.sql` | **403 Interdit** |
| `/core/Auth.php` | **403 Interdit** |
| `/assets/css/style.css` | Feuille de style servie normalement |

Connectez-vous ensuite et parcourez : tableau de bord, UPD, DDE, réquisitions,
utilisateurs, journal d'activités.

---

## 7. Activer HTTPS

VistaPanel → **Free SSL Certificate**, puis suivez la procédure.

Aucune modification du code n'est nécessaire : l'application détecte le
protocole réel à chaque requête, remet le cookie de session en `secure` et
envoie l'en-tête HSTS dès que le site est servi en HTTPS.

> N'imposez jamais le drapeau `secure` sur un site servi en HTTP : le navigateur
> refuserait le cookie de session et la connexion deviendrait impossible.

---

## Dépannage

### « Trop de tentatives échouées. Réessayez dans 15 minutes. »

Protection anti-bruteforce. Attendez, ou videz la table depuis phpMyAdmin :

```sql
DELETE FROM tentatives_connexion;
```

### Toutes les pages renvoient 404, sauf `/` et `/install.php`

Le fichier `.htaccess` est absent ou `mod_rewrite` est désactivé. Vérifiez la
présence du fichier (voir § 3), puis l'option *Apache mod_rewrite* du panneau.

### Toutes les pages renvoient 403

`AllowOverride` est probablement restreint, ou le `.htaccess` a été altéré au
transfert. Renvoyez-le en mode **binaire** (et non ASCII) depuis votre client
FTP.

### « Connexion à la base de données impossible »

Les identifiants de `config/config.local.php` ne correspondent plus à ceux du
panneau. Corrigez le fichier, ou supprimez-le et relancez `install.php` (pensez
à le retéléverser si vous l'aviez supprimé).

### La page de connexion recharge sans message d'erreur

Les cookies sont bloqués par le navigateur, ou l'horloge du poste est très
décalée. Testez dans une fenêtre de navigation privée.

### Les styles ne s'affichent pas

Vérifiez que `/assets/css/style.css` répond bien. Si le dossier `assets/` renvoie
403, c'est que le transfert est incomplet : renvoyez-le en entier.

### Erreur MySQL « max_user_connections »

Limite des offres gratuites, sans rapport avec l'application : elle n'ouvre
qu'une seule connexion par requête. Réessayez quelques instants plus tard.

---

## Limites des offres gratuites

- Coupures et lenteurs aux heures de pointe.
- Pas de sauvegarde automatique : exportez la base régulièrement
  (phpMyAdmin → *Exporter* → *SQL*).
- Quotas d'entrées-sorties MySQL qui peuvent interrompre les gros imports.
- `mail()` généralement désactivé — c'est pourquoi la réinitialisation de mot de
  passe passe par un administrateur plutôt que par courriel.

Pour un usage institutionnel réel, prévoyez un hébergement payant ou un VPS avec
sauvegardes maîtrisées, supervision et restauration testée.
