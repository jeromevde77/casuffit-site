<?php
require_once __DIR__ . '/config.php';
session_start();
if (empty($_SESSION['admin_logged_in'])) { header('Location: /admin/login.php'); exit; }
$db = getDB();
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Config</title>
<style>body{font-family:sans-serif;max-width:520px;margin:40px auto;padding:0 20px;line-height:1.7}
.ok{color:#27ae60;font-weight:700}.info{color:#1673B2}a.btn{display:inline-block;background:#FF9900;color:#fff;padding:10px 22px;border-radius:8px;text-decoration:none;font-weight:700;margin-top:12px}</style>
</head><body><h2>⚙ Configuration du site</h2>';

if (($_GET['apply'] ?? '') === '1') {
    // Fix site_email
    $db->prepare("INSERT INTO site_config (cle,valeur) VALUES ('site_email','info@casuffit.be') ON DUPLICATE KEY UPDATE valeur='info@casuffit.be'")->execute();
    echo '<p class=ok>✅ site_email → info@casuffit.be</p>';

    // Créer admin_bcc
    $bcc = $db->query("SELECT valeur FROM site_config WHERE cle='admin_bcc' LIMIT 1")->fetchColumn();
    if ($bcc === false) {
        $db->prepare("INSERT INTO site_config (cle,valeur) VALUES ('admin_bcc','')")->execute();
        echo '<p class=ok>✅ Clé <code>admin_bcc</code> créée — va la remplir dans Paramètres</p>';
    } else {
        echo '<p class=info>ℹ <code>admin_bcc</code> existe déjà : <strong>'.htmlspecialchars($bcc ?: '(vide)').'</strong></p>';
    }
    echo '<p style="margin-top:16px"><a href="/admin/site_config.php" style="background:#1673B2;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none;font-weight:700">→ Ouvrir Paramètres pour remplir admin_bcc</a></p>';
} else {
    echo '<p>Va créer/corriger :</p><ul>
    <li><code>site_email</code> → <strong>info@casuffit.be</strong></li>
    <li><code>admin_bcc</code> → clé vide à remplir avec ton email perso</li></ul>';
    echo '<a class=btn href="/outils-config.php?apply=1">⚙ Appliquer</a>';
}
echo '</body></html>';
