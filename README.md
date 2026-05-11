# ça suffit ! ASBL — Site web officiel

Site de mobilisation citoyenne contre les nuisances aériennes de Brussels Airport.  
**Piste 01 ça suffit ! · UBCNA · Union citoyenne**

---

## 🚀 Installation rapide

### 1. Cloner le dépôt
```bash
git clone https://github.com/VOTRE-USERNAME/casuffit-site.git
cd casuffit-site
```

### 2. Configurer
Copier et remplir `config.php` :
```bash
cp config.exemple.php config.php
# Éditer config.php avec vos identifiants BDD + email
```

### 3. Base de données
Exécuter dans phpMyAdmin :
1. `install.sql` — crée les tables
2. `inject_content.sql` — injecte le contenu et le logo

### 4. Déployer sur OVH
```bash
# Configurer deploy.sh avec vos identifiants FTP
./deploy.sh "Premier déploiement"
```

---

## 📁 Structure du projet

```
├── index.php              # Site public (CMS dynamique)
├── config.php             # ⚠ NON versionné — à créer manuellement
├── config.exemple.php     # Modèle de configuration
├── install.sql            # Script BDD initial
├── inject_content.sql     # Contenu des pages + logo
├── migrate_wix.sql        # Migration contacts Wix
├── deploy.sh              # Script de déploiement automatique
│
├── admin/                 # Interface d'administration
│   ├── login.php          # Connexion admin
│   ├── dashboard.php      # Tableau de bord
│   ├── pages.php          # Éditeur de pages CMS
│   ├── news.php           # Gestion des actualités
│   ├── members.php        # Gestion des membres
│   ├── import_wix.php     # Import contacts Wix
│   ├── coda.php           # Import relevés CODA bancaires
│   ├── subscribers.php    # Abonnés newsletter
│   ├── compose.php        # Rédiger une newsletter
│   ├── site_config.php    # Paramètres du site
│   └── medias.php         # Médiathèque
│
├── membre/                # Espace membre
│   ├── inscription.php    # Inscription
│   ├── login.php          # Connexion (lien magique)
│   ├── magic.php          # Validation lien magique
│   ├── dashboard.php      # Espace personnel + QR codes
│   └── functions.php      # Fonctions utilitaires
│
├── newsletter/            # Système newsletter
│   ├── subscribe.php      # Inscription
│   ├── confirm.php        # Confirmation double opt-in
│   └── unsubscribe.php    # Désabonnement RGPD
│
├── cron/
│   └── send_queue.php     # Envoi newsletter (Cron OVH 09h00)
│
├── templates/             # Templates emails
├── includes/              # Includes PHP communs
├── lib/                   # Librairies (parser CODA)
├── api/                   # API endpoints
├── medias/                # Images (logo.png versionné)
└── welcome.php            # Accueil contacts Wix importés
```

---

## 🔧 Déploiement

```bash
# Déployer avec un message descriptif
./deploy.sh "Description de la modification"

# Exemples :
./deploy.sh "Ajout menu burger mobile"
./deploy.sh "Correction bug QR code membre"
./deploy.sh "Nouvelle actualité : audience fixée"
```

---

## 🔒 Sécurité

- `config.php` est dans `.gitignore` — **jamais commité**
- `config.exemple.php` fourni comme modèle sans données sensibles
- Tous les fichiers admin protégés par `requireAdmin()`
- Mots de passe hashés avec `password_hash()`

---

## 📬 Contact

info@piste01casuffit.be  
https://www.piste01casuffit.be
