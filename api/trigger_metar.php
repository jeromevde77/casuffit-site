<?php
// ═══════════════════════════════════════════════════════════════════════
//  api/trigger_metar.php
//  Endpoint public sécurisé pour déclenchement externe (cron-job.org)
//  Usage : GET https://www.casuffit.be/api/trigger_metar.php?token=XXX
// ═══════════════════════════════════════════════════════════════════════

define('ROOT', dirname(__DIR__));
require_once ROOT . '/config.php';

header('Content-Type: text/plain; charset=utf-8');

// ── Vérification du token ────────────────────────────────────────────────
if (!defined('CRON_TOKEN') || CRON_TOKEN === '') {
    http_response_code(500);
    exit('error: CRON_TOKEN non configuré');
}

$token = $_GET['token'] ?? $_SERVER['HTTP_X_CRON_TOKEN'] ?? '';
if (!hash_equals(CRON_TOKEN, $token)) {
    http_response_code(403);
    // Logger les tentatives pour détecter les abus
    $ip = $_SERVER['REMOTE_ADDR'] ?? '?';
    @file_put_contents(
        ROOT . '/cron/save_metar.log',
        date('Y-m-d H:i:s') . " REFUS token invalide depuis $ip\n",
        FILE_APPEND | LOCK_EX
    );
    exit('error: token invalide');
}

// ── Exécuter le cron ─────────────────────────────────────────────────────
ob_start();
require ROOT . '/cron/save_metar.php';
ob_end_clean();

// ── Lire les 3 dernières lignes du log pour la réponse ───────────────────
$logfile = ROOT . '/cron/save_metar.log';
if (file_exists($logfile)) {
    $lines = array_slice(array_filter(file($logfile)), -3);
    echo "OK\n" . implode('', $lines);
} else {
    echo "OK (pas de log)\n";
}
