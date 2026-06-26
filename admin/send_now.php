<?php
// admin/send_now.php — Déclenche manuellement le traitement de la file d'envoi newsletter.
// Passe par /admin/ (non bloqué par .htaccess) et inclut le script cron en interne
// (l'include PHP n'est pas soumis au blocage HTTP de /cron/).
require_once __DIR__ . '/../config.php';
session_start();
requireAdmin();

// Déjà authentifié admin → on autorise le script cron via son garde ?secret=
$_GET['secret'] = defined('CRON_SECRET') ? CRON_SECRET : '';
header('Content-Type: text/plain; charset=utf-8');

require __DIR__ . '/../cron/send_queue.php';
