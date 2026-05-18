<?php
// Script d'installation unique — à supprimer après exécution
require_once __DIR__ . '/config.php';
$db = getDB();
$existing = $db->query("SELECT id FROM widgets WHERE slug='vols_brussels'")->fetch();
if($existing){
    echo "Widget déjà présent (id={$existing['id']})";
} else {
    $db->exec("INSERT INTO widgets (slug, nom, description, actif) VALUES 
               ('vols_brussels','✈ Vols en temps réel','Vols ADS-B en cours sur la zone de Bruxelles — OpenSky Network',1)");
    echo "Widget créé (id=" . $db->lastInsertId() . ")";
}
// Auto-supprime après usage
unlink(__FILE__);
echo " — script supprimé.";
