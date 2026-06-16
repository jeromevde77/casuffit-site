<?php
// api/stats.php — Statistiques pour widget (membres, dons, messages, total).
// Sécurisé par un token stocké en base (site_config.widget_stats_token).
// Usage : https://www.casuffit.be/api/stats.php?token=XXXXXXXX
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/dons.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');

function out($arr, $code = 200) { http_response_code($code); echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

$expected = trim(cfg('widget_stats_token', ''));
$given    = trim($_GET['token'] ?? ($_SERVER['HTTP_X_WIDGET_TOKEN'] ?? ''));

if ($expected === '') out(['error' => 'Token non configuré (site_config.widget_stats_token).'], 503);
if ($given === '' || !hash_equals($expected, $given)) out(['error' => 'Token invalide.'], 403);

try {
    $db = getDB();

    $st = $db->prepare("SELECT COUNT(*) FROM members WHERE statut='actif' AND email <> ?");
    $st->execute([ANON_DON_EMAIL]);
    $membres = (int) $st->fetchColumn();

    $dons_enregistres = (int) $db->query("SELECT COUNT(*) FROM member_dons WHERE statut='confirme'")->fetchColumn();
    $messages_non_lus = (int) $db->query("SELECT COUNT(*) FROM contacts WHERE statut='nouveau'")->fetchColumn();
    $total_dons       = (float) $db->query("SELECT COALESCE(SUM(montant),0) FROM member_dons WHERE statut='confirme'")->fetchColumn();

    $montant_initial = (float) cfg('montant_initial', 0);
    $objectif        = (float) cfg('montant_objectif', 0);
    $recolte         = $montant_initial + $total_dons;

    out([
        'membres'          => $membres,
        'dons_enregistres' => $dons_enregistres,
        'messages_non_lus' => $messages_non_lus,
        'total_dons'       => round($total_dons, 2),
        'recolte_totale'   => round($recolte, 2),
        'objectif'         => round($objectif, 2),
        'maj'              => date('c'),
    ]);
} catch (Throwable $e) {
    out(['error' => 'Erreur serveur.'], 500);
}
