<?php
ini_set('display_errors', 0);
error_reporting(0);
// admin/medias_api.php — API JSON pour la modale médias de l'éditeur de pages
require_once __DIR__ . '/../config.php';
if (!defined('MEDIAS_DIR')) define('MEDIAS_DIR', __DIR__ . '/../medias/');
// session_start() déjà géré par config.php via requireAdmin/requireMembre
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    echo json_encode(['ok' => false, 'error' => "PHP $errno: $errstr line $errline"]);
    exit;
});

// Vérification auth compatible API (retourne JSON au lieu de rediriger)
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Non autorisé']);
    exit;
}
$db = getDB();
$action = $_GET['action'] ?? '';

// ── Lister les médias ─────────────────────────────────────────────────────
if ($action === 'list') {
    $medias = $db->query("SELECT fichier, nom FROM medias ORDER BY uploaded_at DESC")->fetchAll();
    $result = array_map(function($m) {
        return [
            'url'  => '/medias/' . $m['fichier'],
            'name' => $m['nom'] ?: $m['fichier'],
        ];
    }, $medias);
    echo json_encode($result);
    exit;
}

// ── Uploader un média ─────────────────────────────────────────────────────
if ($action === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $file = $_FILES['file'] ?? null;

    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['ok' => false, 'error' => 'Erreur upload']);
        exit;
    }

    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowed)) {
        echo json_encode(['ok' => false, 'error' => 'Type non autorisé']);
        exit;
    }

    $ext_map  = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
    $ext      = $ext_map[$file['type']];
    $filename = date('Ymd_His') . '_' . preg_replace('/[^a-z0-9]/', '', strtolower(pathinfo($file['name'], PATHINFO_FILENAME))) . '.' . $ext;
    $dest     = MEDIAS_DIR . $filename;

    if (!is_dir(MEDIAS_DIR)) mkdir(MEDIAS_DIR, 0755, true);

    if (move_uploaded_file($file['tmp_name'], $dest)) {
        $db->prepare("INSERT INTO medias (fichier, nom, type, taille) VALUES (?,?,?,?)")
           ->execute([$filename, $file['name'], $file['type'], $file['size']]);
        echo json_encode(['ok' => true, 'filename' => $filename, 'url' => SITE_URL . '/medias/' . $filename]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Impossible de sauvegarder le fichier']);
    }
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Action inconnue']);
