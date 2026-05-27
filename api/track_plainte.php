<?php
// api/track_plainte.php — Enregistre un clic sur le bouton "Générer une plainte"
// Appelé en POST depuis les widgets piste_meteo et historique_vent
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST required']);
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../membre/functions.php';

session_start();

$db = getDB();

// Données du clic
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$source = in_array($body['source'] ?? '', ['piste_meteo','historique_vent'])
          ? $body['source'] : 'piste_meteo';
$alert  = !empty($body['alert']) ? 'hors_prs' : 'dans_prs';

// Détection membre via session
$member_id = isset($_SESSION['membre_id']) ? (int)$_SESSION['membre_id'] : null;
$is_membre = $member_id ? 1 : 0;

// IP hashée pour RGPD (pas de données personnelles stockées)
$ip_hash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');

try {
    $db->prepare("
        INSERT INTO plainte_clicks (member_id, is_membre, source, alert_level, ip_hash)
        VALUES (?, ?, ?, ?, ?)
    ")->execute([$member_id, $is_membre, $source, $alert, $ip_hash]);

    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    // Silencieux côté client, on log côté serveur
    error_log('track_plainte error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'db_error']);
}
