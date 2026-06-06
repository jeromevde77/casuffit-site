<?php
require_once __DIR__ . '/config.php';
session_start();
if (empty($_SESSION['admin_logged_in'])) { header('Location: /admin/login.php'); exit; }
$db = getDB();
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Setup Contact</title>';
echo '<style>body{font-family:sans-serif;max-width:640px;margin:30px auto;padding:0 20px;line-height:1.7}.ok{color:#27ae60;font-weight:700}.err{color:#c0392b}a.btn{display:inline-block;background:#1673B2;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none;margin-top:12px;font-weight:700}</style></head><body>';
echo '<h2>📬 Setup page Contact</h2>';
if (($_GET['apply'] ?? '') === '1') {
    // Table contacts
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS contacts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(200) NOT NULL, email VARCHAR(200) NOT NULL,
            sujet VARCHAR(200) DEFAULT '', message TEXT NOT NULL,
            created_at DATETIME DEFAULT NOW(),
            statut ENUM('nouveau','lu','repondu') DEFAULT 'nouveau',
            reponse TEXT NULL, repondu_at DATETIME NULL
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo '<p class=ok>✅ Table contacts créée/vérifiée</p>';
    } catch(Exception $e) { echo '<p class=err>❌ '.$e->getMessage().'</p>'; }

    // Widget contact actif dans la table widgets
    try {
        $chk = $db->prepare("SELECT id FROM widgets WHERE slug='contact' LIMIT 1");
        $chk->execute(); $ex = $chk->fetch();
        if ($ex) {
            $db->prepare("UPDATE widgets SET actif=1 WHERE slug='contact'")->execute();
            echo '<p class=ok>✅ Widget contact activé</p>';
        } else {
            $db->prepare("INSERT INTO widgets (slug, titre, actif) VALUES ('contact','Contact',1)")->execute();
            echo '<p class=ok>✅ Widget contact créé et activé</p>';
        }
    } catch(Exception $e) { echo '<p class=err>❌ '.$e->getMessage().'</p>'; }

    // Page contact dans pages (dans le menu)
    try {
        $chk = $db->prepare("SELECT id FROM pages WHERE slug='contact' LIMIT 1");
        $chk->execute(); $ex = $chk->fetch();
        if ($ex) {
            $db->prepare("UPDATE pages SET dans_menu=1,visible=1,titre='Contact',titre_nl='Contact',contenu='',lien_url='',ordre=98 WHERE id=?")->execute([$ex['id']]);
            echo '<p class=ok>✅ Page contact mise à jour</p>';
        } else {
            $db->prepare("INSERT INTO pages (slug,titre,titre_nl,contenu,contenu_nl,dans_menu,visible,ordre) VALUES ('contact','Contact','Contact','','',1,1,98)")->execute();
            echo '<p class=ok>✅ Page contact créée dans le menu</p>';
        }
    } catch(Exception $e) { echo '<p class=err>❌ '.$e->getMessage().'</p>'; }

    // Widget assigné à la page contact (colonne gauche)
    try {
        $db->prepare("DELETE FROM page_widgets WHERE page_slug='contact'")->execute();
        $db->prepare("INSERT INTO page_widgets (page_slug,widget_slug,ordre,position) VALUES ('contact','contact',1,'gauche')")->execute();
        echo '<p class=ok>✅ Widget contact assigné à l\'onglet contact (colonne gauche)</p>';
    } catch(Exception $e) { echo '<p class=err>❌ '.$e->getMessage().'</p>'; }

    echo '<p style="margin-top:16px"><a class=btn href="/">→ Voir le site</a> <a class=btn href="/admin/contacts.php">→ Messages reçus</a></p>';
} else {
    echo '<p>Va créer/configurer :</p><ul>';
    echo '<li>Table <code>contacts</code></li>';
    echo '<li>Widget <code>contact</code> dans la table widgets</li>';
    echo '<li>Page <code>Contact</code> dans le menu principal</li>';
    echo '<li>Widget assigné à l\'onglet Contact (colonne gauche)</li></ul>';
    echo '<p><a href="/outils-contact.php?apply=1" style="background:#FF9900;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:700">⚙ Configurer</a></p>';
}
echo '</body></html>';
