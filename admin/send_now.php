<?php
// admin/send_now.php — Déclenche le traitement de la file d'envoi newsletter (admin).
// Passe par /admin/ (non bloqué par .htaccess) et inclut le script cron en interne.
//   ?json=1  → renvoie {ok, sent, errors, reste} (pour la barre de progression AJAX)
//   ?max=N   → borne le lot (lu par le cron)
//   (sans json) → sortie texte lisible (onglet)
require_once __DIR__ . '/../config.php';
session_start();
requireAdmin();

// Déjà authentifié admin → on autorise le script cron via son garde ?secret=
$_GET['secret'] = defined('CRON_SECRET') ? CRON_SECRET : '';
$as_json = isset($_GET['json']);

if ($as_json) {
    ob_start();
    require __DIR__ . '/../cron/send_queue.php';   // définit $sent, $errors, $db (portée partagée)
    ob_end_clean();                                // on jette la sortie texte du cron
    $reste = (int)$db->query("SELECT COUNT(*) FROM send_queue WHERE statut='en_attente'")->fetchColumn();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>true, 'sent'=>(int)($sent ?? 0), 'errors'=>(int)($errors ?? 0), 'reste'=>$reste]);
} else {
    header('Content-Type: text/plain; charset=utf-8');
    require __DIR__ . '/../cron/send_queue.php';
}
