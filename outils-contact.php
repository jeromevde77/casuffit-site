<?php
require_once __DIR__ . '/config.php';
session_start();
if (empty($_SESSION['admin_logged_in'])) { header('Location: /admin/login.php'); exit; }
$db = getDB();
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Ajouter page Contact</title>';
echo '<style>body{font-family:sans-serif;max-width:600px;margin:30px auto;padding:0 20px}.ok{color:#27ae60;font-weight:700}a.btn{display:inline-block;background:#1673B2;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none;margin-top:12px}</style></head><body>';
echo '<h2>📬 Ajouter la page Contact dans la navigation</h2>';
if (($_GET['apply'] ?? '') === '1') {
    // Créer la table contacts
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS contacts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(200) NOT NULL,
            email VARCHAR(200) NOT NULL,
            sujet VARCHAR(200) DEFAULT '',
            message TEXT NOT NULL,
            created_at DATETIME DEFAULT NOW(),
            statut ENUM('nouveau','lu','repondu') DEFAULT 'nouveau',
            reponse TEXT NULL,
            repondu_at DATETIME NULL
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo '<p class=ok>✅ Table contacts créée</p>';
    } catch(Exception $e) { echo '<p class=err>❌ '.htmlspecialchars($e->getMessage()).'</p>'; }

    try {
        $chk = $db->prepare("SELECT id FROM pages WHERE slug='contact' LIMIT 1");
        $chk->execute(); $ex = $chk->fetch();
        if ($ex) {
            $db->prepare("UPDATE pages SET dans_menu=1,visible=1,titre='Contact',lien_url='/contact.php',ordre=99 WHERE id=?")->execute([$ex['id']]);
            echo '<p class=ok>✅ Page Contact mise à jour dans le menu (ID '.$ex['id'].')</p>';
        } else {
            $db->prepare("INSERT INTO pages (slug,titre,titre_nl,contenu,dans_menu,visible,lien_url,ordre,icone) VALUES ('contact','Contact','Contact','',1,1,'/contact.php',99,'📬')")->execute();
            echo '<p class=ok>✅ Page Contact ajoutée au menu (ID '.$db->lastInsertId().')</p>';
        }
        echo '<a class=btn href="/">→ Voir le site</a>';
    } catch (Exception $e) { echo '<p style="color:red">❌ '.htmlspecialchars($e->getMessage()).'</p>'; }
} else {
    echo '<p>Va ajouter <strong>Contact</strong> dans la navigation principale du site.</p>';
    echo '<p><a href="/outils-contact.php?apply=1" style="background:#FF9900;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:700">⚙ Ajouter au menu</a></p>';
}
echo '</body></html>';
