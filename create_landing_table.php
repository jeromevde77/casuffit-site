<?php
require_once __DIR__ . '/config.php';
$db = getDB();
$db->exec("CREATE TABLE IF NOT EXISTS landing_pages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(64) NOT NULL UNIQUE,
  titre VARCHAR(200) DEFAULT '',
  contenu MEDIUMTEXT DEFAULT '',
  contenu_nl MEDIUMTEXT DEFAULT '',
  css TEXT DEFAULT '',
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Migrer le contenu existant depuis site_config si présent
$css = '';
$contenu = '';
$contenu_nl = '';
try {
    $r = $db->query("SELECT cle, valeur FROM site_config WHERE cle IN ('agir_css','agir_contenu','agir_contenu_nl')")->fetchAll();
    foreach ($r as $row) {
        if ($row['cle'] === 'agir_css')        $css        = $row['valeur'];
        if ($row['cle'] === 'agir_contenu')    $contenu    = $row['valeur'];
        if ($row['cle'] === 'agir_contenu_nl') $contenu_nl = $row['valeur'];
    }
} catch (Exception $e) {}

// Si pas encore dans site_config, récupérer depuis pages
if (!$contenu) {
    try {
        $r = $db->query("SELECT contenu, contenu_nl FROM pages WHERE slug='agir' LIMIT 1")->fetch();
        if ($r) { $contenu = $r['contenu'] ?? ''; $contenu_nl = $r['contenu_nl'] ?? ''; }
    } catch (Exception $e) {}
}

$db->prepare("INSERT INTO landing_pages (slug, titre, contenu, contenu_nl, css) VALUES ('agir','Page Agir avec nous',?,?,?) ON DUPLICATE KEY UPDATE contenu=VALUES(contenu), contenu_nl=VALUES(contenu_nl), css=VALUES(css)")
   ->execute([$contenu, $contenu_nl, $css]);

echo "✅ Table landing_pages créée et initialisée.\n";
echo "slug=agir | contenu=" . strlen($contenu) . " chars | css=" . strlen($css) . " chars\n";
