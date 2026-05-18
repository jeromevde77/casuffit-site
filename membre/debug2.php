<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/functions.php';
session_start();
require_once __DIR__ . '/lang.php';
$db = getDB();
$membre = getMembre($db);
if (!$membre) { echo "Non connecté\n"; exit; }

// Tester toutes les colonnes utilisées dans dashboard.php
$cols = ['id','email','prenom','nom','code_membre','ogm','newsletter','rgpd_accepte','rgpd_date',
         'donnees_verifiees_at','subscriber_id','rue','numero','boite','code_postal','commune',
         'adresse','telephone','iban_membre','email_nouveau','token_email_change'];
foreach ($cols as $col) {
    echo "$col: " . (array_key_exists($col, $membre) ? "OK" : "MANQUANT") . "\n";
}

// Tester la requête complète
$stmt = $db->prepare("SELECT * FROM members WHERE id=?");
$stmt->execute([$membre['id']]);
$m2 = $stmt->fetch();
echo "\nColonnes disponibles:\n";
echo implode(', ', array_keys($m2)) . "\n";
echo "✅ OK\n";
