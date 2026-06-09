# CLAUDE.md — Projet casuffit.be

Contexte et conventions pour travailler sur ce projet. À lire en premier.

## Vue d'ensemble

Site web de l'ASBL **« Ça suffit ! »** (Piste 01 / UBCNA), mobilisation citoyenne
contre les nuisances aériennes de la piste 01 de Bruxelles-National.

- **Stack** : PHP 8.3 (OVH mutualisé), MySQL, vanilla JS, pas de framework
- **Repo GitHub** : `jeromevde77/casuffit-site` (branche `main`)
- **Prod** : https://www.casuffit.be
- **Langues** : bilingue FR / NL

## Déploiement

**Automatique via GitHub Actions** : tout `git push origin main` déclenche
`.github/workflows/deploy.yml` qui synchronise via `lftp` (FTP) vers OVH.

- Le déploiement prend ~40-60 s après le push.
- Vérifier le statut : onglet Actions du repo, ou l'API GitHub.
- `config.php` n'est **jamais** déployé (exclu du mirror) — il vit uniquement
  sur le serveur OVH. `config.exemple.php` sert de modèle (fonctions utilitaires
  partagées y sont définies : `cfg()`, `requireAdmin()`, `getDB()`, `mailto_bcc()`, etc.).

### Pièges de déploiement (IMPORTANT)

1. **lftp saute les fichiers de taille identique** malgré `--ignore-time`.
   Pour forcer le retransfert d'un fichier modifié dont la taille ne change pas,
   ajouter/bumper un marqueur de version en tête (ex. `// v3` ou
   `<?php /* fichier — v4 */ ?>`).

2. **Service Worker admin** : le SW (`csa-admin-vN`) met en cache `admin/dashboard.php`.
   Les hooks/opérations DB ajoutés dans `dashboard.php` peuvent ne pas s'exécuter
   (version cachée servie). Pour les opérations ponctuelles en base, **créer un
   fichier à la racine** (hors scope `/admin/` du SW), du type `outils-xxx.php`,
   protégé par `$_SESSION['admin_logged_in']`. **Supprimer ces outils après usage**
   (risque de sécurité — voir plus bas).
   `reset-sw.php` à la racine force la réinitialisation du SW côté client.

## Architecture des pages

### Pages publiques (racine)
- `index.php` — page principale **autonome** (tout le CSS inline, son propre
  header/footer/nav). **N'inclut PAS** `includes/header.php` ni `includes/footer.php`.
  Système d'onglets (tabs) alimenté par la table `pages` + widgets via `page_widgets`.
- `plainte.php`, `don.php`, `agir.php`, `wind.php` — pages **standalone**, chacune
  avec son propre mini-header (dégradé bleu + logo) et son CSS. Elles n'utilisent
  pas `includes/header.php`.
- `soutenir.php` → `/soutenir` (FR), `/steunen` (NL) redirigent vers `don.php`.

### includes/
- `includes/header.php` / `footer.php` — n'étaient utilisés que par des pages
  standalone ; **éviter de les réintroduire dans index.php**.
- `includes/lang.php` — système bilingue. Définit la constante `LANG` (`fr`/`nl`)
  depuis `?lang=`, cookie, ou défaut `fr`. Contient `t()`, `cfgLang()`, `mailto_bcc()`.
- `includes/csrf.php` — `csrf_token()`, `csrf_field()`, `csrf_verify()`.
  Inclure **après** `session_start()`.
- `includes/mail_helper.php` — `sendMail($to,$name,$subject,$html,$text)`.
  Utilise Brevo (API) si `BREVO_API_KEY` défini, sinon SMTP (PHPMailer).
- `includes/totp.php` — 2FA (TOTP + codes de secours).

### Widgets bilingues
Pattern : **deux fichiers séparés** `xxx.php` (FR) + `xxx_nl.php` (NL).
Le moteur `widgetFile()` dans `index.php` choisit automatiquement `_nl.php`
quand `LANG === 'nl'`. **Ne jamais** mettre de détection `LANG` à l'intérieur
d'un widget — ça provoque des inversions. Exemples : `donation_card` / `donation_card_nl`,
`contact` / `contact_nl`, `progression` / `progression_nl`.

## Pages admin (`admin/`)

### Structure HTML obligatoire d'une page admin
```php
<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/csrf.php';   // si formulaires POST
session_start(); requireAdmin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_verify();
// ...
?>
<!DOCTYPE html><html lang="fr"><head>
<style>
<?php include __DIR__.'/../includes/admin_sidebar_css.php'; ?>   /* DANS le <style>, jamais avant */
body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333;margin:0}
/* ... styles page ... */
</style>
</head>
<body>
<?php include __DIR__.'/../includes/admin_sidebar.php'; ?>
<div class="wrap">    <!-- PAS de <div class="admin-layout"> ni <div class="main"> -->
  <!-- contenu -->
</div>
</body></html>
```

### Pièges admin récurrents
- `admin_sidebar_css.php` **doit** être inclus **à l'intérieur** d'un bloc `<style>`,
  jamais avant (sinon le CSS s'affiche en texte brut sur la page).
- Chaque page admin doit déclarer sa règle `body{font-family:...;background:#f0f4f8;...}`
  car `admin_sidebar_css.php` ne la contient pas.
- Structure : `<body>` > include sidebar > `<div class="wrap">`. Pas de wrapper
  `admin-layout` / `main`.

### Rôles admin
- `superadmin`, `admin` — accès complet.
- `support` — accès **uniquement** à `contacts.php` (messages). Redirection auto
  gérée dans `admin_sidebar.php`. Login redirige vers `contacts.php`.
- **2FA obligatoire** pour `admin`/`superadmin` (flag `$_SESSION['admin_2fa_required']`
  vérifié dans `admin_sidebar.php`). `support` exempté.

## Base de données (tables principales)

- `members` — membres (id, prenom, nom, email, code_membre, commune, statut, …)
- `member_dons` — dons (member_id, montant, statut `en_attente`/`confirme`/`annule`,
  ogm_don, communication, date_don)
- `contacts` — messages du formulaire de contact (nom, email, sujet, message,
  statut `nouveau`/`lu`/`repondu`, reponse, repondu_at)
- `pages` — onglets/pages du site (slug, titre, titre_nl, contenu, dans_menu, …)
- `widgets` + `page_widgets` — widgets et leur assignation aux pages (position `gauche`/`droite`)
- `site_config` — config clé/valeur (cle, valeur, valeur_nl). Lue via `cfg('cle', 'défaut')`.
- `admin_users` — comptes admin (role, totp_secret, totp_enabled, failed_attempts, locked_until)
- `subscribers` — abonnés newsletter

### Clés site_config utiles
- `site_email` = `info@casuffit.be` (groupe Google)
- `admin_bcc` = BCC sur les liens `mailto:` du site (plusieurs adresses séparées par virgule)
- `alerte_membre_email` / `alerte_don_email` / `alerte_contact_email` — destinataires
  des alertes (si vides → fallback `site_email` → distribution unique via groupe Google)
- `iban` (BE41 0689 0149 6910), `bic` (GKCCBEBB), `beneficiaire`
- `montant_objectif`, `date_lancement`, `montant_initial` — barre de progression
- `annonce_active` / `annonce_titre` / `annonce_texte` — bandeau d'annonce
- `urgence_texte` — bandeau d'urgence (ticker orange)

## Sécurité (déjà en place)

- PDO partout (pas d'injection SQL), `htmlspecialchars` partout (anti-XSS)
- CSRF sur formulaires admin + membres
- Rate limiting : login admin (`failed_attempts`/`locked_until`), login membre
  (10/15 min), formulaire contact (`api/contact_submit.php`, 5/h), inscription (3/h)
- Honeypot (`website`) sur formulaire contact + login membre
- `.htaccess` : HSTS, CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy,
  Permissions-Policy, cookies `Secure; HttpOnly; SameSite=Lax`, directory listing bloqué,
  `install_admin.php` bloqué
- 2FA obligatoire pour les rôles admin

### Règles de sécurité à respecter
- **Supprimer les fichiers `outils-*.php`** dès qu'ils ont servi (ils donnent un accès
  admin direct à des opérations DB).
- Ne jamais commiter de secrets (tokens, mots de passe, clés API) — `config.php` est hors repo.

## Conventions de travail

- Vérité absolue : ne jamais inventer ni extrapoler. Si une info n'est pas
  vérifiable, le dire (« Je ne sais pas »).
- Toujours `php -l` avant de commiter.
- Messages de commit concis et descriptifs.
- Après chaque push, attendre ~45 s et vérifier le succès du workflow GitHub Actions.
- Pour tester les modifications visuelles, consulter la prod après déploiement.

---
*Dernière mise à jour : juin 2026*
