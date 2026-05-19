# Installation — Ça suffit ! ASBL

## Prérequis
- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+
- Serveur web (Apache recommandé — OVH hébergement mutualisé)

## Étapes

### 1. Base de données
1. Créer une base de données MySQL vide (utf8mb4_unicode_ci) dans phpMyAdmin
2. Importer `install.sql` → crée toutes les tables + données initiales
3. Importer `inject_content.sql` → contenu des pages (textes, historique)

### 2. Configuration
1. Copier `config.exemple.php` → `config.php`
2. Remplir les identifiants BDD :
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'votre_base');
define('DB_USER', 'votre_user');
define('DB_PASS', 'votre_mot_de_passe');
define('ADMIN_PASSWORD', 'votre_mot_de_passe_admin');
define('SITE_URL', 'https://votre-domaine.be');
define('MAIL_FROM', 'info@votre-domaine.be');
```

### 3. Fichiers
1. Uploader tous les fichiers via FTP à la racine du domaine
2. S'assurer que `.htaccess` est bien uploadé (fichier caché)
3. Créer le dossier `medias/` avec droits d'écriture (chmod 755)

### 4. Google Analytics
- Dans Admin → Configuration → Google Analytics
- Mettre à jour l'ID (G-XXXXXXXXXX) si le domaine change

### 5. Vérification
- Accéder à `https://votre-domaine.be/` → page d'accueil
- Accéder à `https://votre-domaine.be/admin/` → interface admin
- Accéder à `https://votre-domaine.be/wind.php` → web app météo

## Structure des fichiers
```
/
├── index.php              # Page principale
├── wind.php               # Web app météo (PWA)
├── politique-confidentialite.php
├── config.php             # ⚠ À créer depuis config.exemple.php
├── install.sql            # Structure + données initiales BDD
├── inject_content.sql     # Contenu des pages
├── .htaccess              # Réécriture URLs
├── admin/                 # Interface d'administration
├── api/                   # Endpoints API (METAR, IRM, AIP)
├── assets/img/            # Icônes PWA
├── includes/widgets/      # Widgets du site
├── membre/                # Espace membre
├── newsletter/            # Gestion newsletter
├── cron/                  # Script cron envoi newsletter
└── templates/             # Templates emails
```

## Cron job (optionnel — envoi newsletter)
```
*/5 * * * * php /chemin/vers/cron/send_queue.php
```
