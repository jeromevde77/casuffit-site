<?php
// ═══════════════════════════════════════════════════════════════════════
//  config.exemple.php — MODÈLE de configuration
//  Copiez ce fichier en config.php et remplissez vos valeurs
//  ⚠ Ne modifiez PAS ce fichier directement
// ═══════════════════════════════════════════════════════════════════════

// ── BASE DE DONNÉES MySQL (OVH) ─────────────────────────────────────────
define('DB_HOST',    'votre-serveur.mysql.db');   // ex: sql123.mysql.db
define('DB_NAME',    'votre_base');
define('DB_USER',    'votre_utilisateur');
define('DB_PASS',    'votre_mot_de_passe');
define('DB_CHARSET', 'utf8mb4');

// ── ENVOI EMAIL ──────────────────────────────────────────────────────────
// Option A : Brevo API (recommandé)
define('ANTHROPIC_API_KEY', 'sk-ant-api03-VOTRE_CLE_ANTHROPIC'); // Pour la traduction automatique
define('BREVO_API_KEY', '');    // ex: xkeysib-abc123...

// ── CRON EXTERNE ─────────────────────────────────────────────────────────
// Token secret pour déclencher /api/trigger_metar.php depuis cron-job.org
// Générer avec : php -r "echo bin2hex(random_bytes(24));"
define('CRON_TOKEN', 'CHANGEZ_MOI_TOKEN_ALEATOIRE_HEX');

// ── OPENSKY NETWORK (vols ADS-B) ─────────────────────────────────────────
// Compte gratuit sur https://opensky-network.org → Account → API Clients
// Sans ces credentials, l'API tombe en mode anonyme (rate limit strict)
define('OPENSKY_CLIENT_ID',     'votre-pseudo-api-client');
define('OPENSKY_CLIENT_SECRET', 'CHANGEZ_MOI_SECRET_OPENSKY');
// Option B : SMTP OVH
define('SMTP_HOST',      'ssl0.ovh.net');
define('SMTP_PORT',      465);
define('SMTP_USER',      'info@casuffit.be');
define('SMTP_PASS',      'votre_mot_de_passe_email');
define('SMTP_FROM',      'info@casuffit.be');
define('SMTP_FROM_NAME', 'ça suffit ! ASBL');

// ── ADMIN ────────────────────────────────────────────────────────────────
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', password_hash('VotreMotDePasse!', PASSWORD_DEFAULT));

// ── SITE ─────────────────────────────────────────────────────────────────
define('SITE_URL',   'https://www.casuffit.be');
define('SITE_NAME',  'ça suffit ! ASBL');
define('ADMIN_EMAIL','info@casuffit.be');

// ── MEDIAS ───────────────────────────────────────────────────────────────
define('MEDIAS_DIR', __DIR__ . '/medias/');
define('MEDIAS_URL', SITE_URL . '/medias/');

// ── QUEUE ─────────────────────────────────────────────────────────────────
define('QUEUE_BATCH_SIZE', 280);

// ── TIMEZONE ──────────────────────────────────────────────────────────────
date_default_timezone_set('Europe/Brussels');

// ═══════════════════════════════════════════════════════════════════════
// NE PAS MODIFIER EN DESSOUS
// ═══════════════════════════════════════════════════════════════════════

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset='.DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, array(
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ));
    }
    return $pdo;
}

function cfg($cle, $default = '') {
    static $cache = null;
    if ($cache === null) {
        try {
            $rows = getDB()->query("SELECT cle, valeur FROM site_config")->fetchAll();
            $cache = array();
            foreach ($rows as $r) { $cache[$r['cle']] = $r['valeur']; }
        } catch (Exception $e) { $cache = array(); }
    }
    return isset($cache[$cle]) ? $cache[$cle] : $default;
}

function isAdminLoggedIn() { return !empty($_SESSION['admin_logged_in']); }

function requireAdmin() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isAdminLoggedIn()) { header('Location: '.SITE_URL.'/admin/login.php'); exit; }
}

function getMembre($db) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['membre_id'])) return null;
    $stmt = $db->prepare("SELECT * FROM members WHERE id=? AND statut='actif'");
    $stmt->execute(array($_SESSION['membre_id']));
    return $stmt->fetch();
}

function requireMembre($db) {
    $m = getMembre($db);
    if (!$m) { header('Location: '.SITE_URL.'/membre/login.php'); exit; }
    return $m;
}

function cleanHtml($html) {
    return htmlspecialchars_decode(strip_tags($html,
        '<h2><h3><h4><p><br><ul><ol><li><strong><em><b><i><a><span><div><blockquote><hr><img>'
    ));
}
