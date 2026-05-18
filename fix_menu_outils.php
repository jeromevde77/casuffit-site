<?php
require_once __DIR__ . '/config.php';
$db = getDB();
// Page "Outils" (slug=vent, id=3) et "Conditions de vent" (slug=conditions-vent, id=22)
// Passer menu_position à 'tabs' au lieu de 'all'
$db->prepare("UPDATE pages SET menu_position='tabs' WHERE slug IN ('vent','conditions-vent')")->execute();
$rows = $db->query("SELECT id, titre, slug, menu_position FROM pages WHERE slug IN ('vent','conditions-vent')")->fetchAll();
foreach ($rows as $r) echo $r['id'].' | '.$r['titre'].' | '.$r['slug'].' | '.$r['menu_position']."\n";
echo "✅ OK\n";
