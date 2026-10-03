# Faire tourner casuffit.be en local (Docker)

Environnement de **test sur ton ordinateur uniquement**. La production OVH reste
du PHP mutualisé classique : ni Docker ni ces fichiers n'y sont déployés
(`docker/` et `docker-compose.yml` sont exclus dans `.github/workflows/deploy.yml`).

Contenu : Apache + PHP 8.3 (le `.htaccess` est appliqué comme sur OVH),
MySQL 8 et phpMyAdmin.

## Installation (une seule fois)

1. Installer **Docker Desktop** (Mac ou Windows) : https://www.docker.com/products/docker-desktop/
   puis le lancer.
2. Récupérer le code :
   ```
   git clone https://github.com/jeromevde77/casuffit-site.git
   cd casuffit-site
   ```
3. Exporter la base de prod : Admin → **Backup & Export** (`admin/backup.php`), ou phpMyAdmin OVH → Exporter.
   Copier le fichier `.sql` dans **`docker/db-init/`** (le nom importe peu).
   Ce dossier est ignoré par git : la sauvegarde contient des données personnelles,
   elle ne doit **jamais** être commitée.

## Lancer

```
docker compose up -d --build
```

Le premier démarrage importe la sauvegarde : compter 1 à 2 minutes avant que
le site affiche les données.

| Adresse | Quoi |
|---|---|
| http://localhost:8080 | Le site |
| http://localhost:8080/admin/login.php | Admin (vrais comptes de la prod, avec la 2FA) |
| http://localhost:8081 | phpMyAdmin (base locale) |

Arrêter : `docker compose down`. Les données sont conservées pour la prochaine fois.

## Recharger une base plus récente

La sauvegarde n'est importée que lorsque la base locale est vide. Pour repartir
d'une nouvelle sauvegarde : remplacer le fichier dans `docker/db-init/`, puis

```
docker compose down -v
docker compose up -d
```

(`-v` efface la base locale, pas la prod.)

## Ce qui diffère de la prod

- **Aucun e-mail ne part** : pas de clé Brevo, SMTP neutralisé
  (`docker/config.docker.php`). On peut tester l'envoi de newsletters sans risque.
- **Pas de crons** : la file d'envoi, les traces EBBR et la météo ne tournent pas.
- **`medias/`** n'existe que sur le serveur OVH : les images uploadées
  manquent. On peut les récupérer par FTP et les copier dans `medias/`
  (ignoré par git).
- `config.php` local = `docker/config.docker.php` (sans secret). Ne **jamais**
  y copier le `config.php` de la prod.
- Les erreurs PHP sont affichées à l'écran (utile pour déboguer).

## Problèmes courants

- **Le site affiche une erreur de base de données juste après le lancement** :
  l'import est encore en cours, attendre une minute.
- **La sauvegarde n'est pas importée** : vérifier que le fichier est bien dans
  `docker/db-init/` et qu'il se termine par `.sql`, puis relancer avec
  `docker compose down -v && docker compose up -d`. Voir les messages avec
  `docker compose logs db`.
- **Port déjà utilisé** : changer `8080:80` ou `8081:80` dans `docker-compose.yml`.
