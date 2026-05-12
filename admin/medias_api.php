<?php
// admin/medias_api.php — API JSON pour la modale médias de l'éditeur de pages
require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();

header('Content-Type: application/json; charset=utf-8');
$db = getDB();
$action = $_GET['action'] ?? '';

// ── Lister les médias ─────────────────────────────────────────────────────
if ($action === 'list') {
    $medias = $db->query("SELECT filename, original_name FROM medias ORDER BY uploaded_at DESC")->fetchAll();
    $result = array_map(function($m) {
        return [
            'url'  => SITE_URL . '/medias/' . $m['filename'],
            'name' => $m['original_name'] ?: $m['filename'],
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

    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
    if (!in_array($file['type'], $allowed)) {
        echo json_encode(['ok' => false, 'error' => 'Type non autorisé']);
        exit;
    }

    $ext_map  = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp','image/svg+xml'=>'svg'];
    $ext      = $ext_map[$file['type']];
    $filename = uniqid('img_') . '.' . $ext;
    $dest     = __DIR__ . '/../medias/' . $filename;

    if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0755, true);

    if (move_uploaded_file($file['tmp_name'], $dest)) {
        $db->prepare("INSERT INTO medias (filename, original_name, mime_type, taille, alt_text, uploaded_by) VALUES (?,?,?,?,?,?)")
           ->execute([$filename, $file['name'], $file['type'], $file['size'], '', ADMIN_USER]);
        echo json_encode(['ok' => true, 'filename' => $filename, 'url' => SITE_URL . '/medias/' . $filename]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Impossible de sauvegarder le fichier']);
    }
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Action inconnue']);
