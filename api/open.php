<?php
// api/open.php — Pixel de tracking d'ouverture email (1x1 GIF transparent)
// Appelé via <img src="https://www.casuffit.be/api/open.php?t=TOKEN">
require_once __DIR__ . '/../config.php';

// Le pixel doit TOUJOURS être renvoyé, même en cas d'erreur DB.
function envoyerPixel() {
    // GIF transparent 1x1
    header('Content-Type: image/gif');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Content-Length: 43');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
}

$token = preg_replace('/[^a-f0-9]/', '', $_GET['t'] ?? '');

if ($token && strlen($token) >= 16) {
    try {
        $db = getDB();
        // Incrémenter le compteur et dater l'ouverture (sans dévoiler d'erreur)
        $db->prepare("UPDATE email_opens
                      SET nb_ouvertures = nb_ouvertures + 1,
                          derniere_ouverture = NOW(),
                          premiere_ouverture = COALESCE(premiere_ouverture, NOW())
                      WHERE token = ?")
           ->execute([$token]);
    } catch (Throwable $e) {
        // Silencieux : ne jamais casser le rendu de l'image
        error_log('open.php: ' . $e->getMessage());
    }
}

envoyerPixel();
exit;
