<?php
// config.php LOCAL (Docker) — monté à la place de config.php dans le conteneur.
// Aucun secret : pas de clé Brevo ni de mot de passe SMTP → aucun e-mail ne part.

define('DB_HOST',    'db');
define('DB_NAME',    'casuffit');
define('DB_USER',    'casuffit');
define('DB_PASS',    'casuffit');
define('DB_CHARSET', 'utf8mb4');

define('ANTHROPIC_API_KEY', '');
define('BREVO_API_KEY',     '');
define('SMTP_HOST',         'localhost.invalid');
define('SMTP_PASS',         '');

define('ADMIN_USER', 'admin');
define('ADMIN_PASS', password_hash('admin-local', PASSWORD_DEFAULT));

define('SITE_URL', 'http://localhost:8080');

// Reprend le reste de config.exemple.php (constantes restantes + fonctions).
// Les define() déjà faits ci-dessus gardent leur valeur ; on masque les avertissements
// « Constant already defined » que PHP émet pour les doublons.
$lvl = error_reporting(E_ALL & ~E_WARNING);
require __DIR__ . '/config.exemple.php';
error_reporting($lvl);
