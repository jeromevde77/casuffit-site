<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/functions.php';
session_start();
require_once __DIR__ . '/lang.php';
$db = getDB();
echo "Avant requireMembre\n";
$membre = getMembre($db);
if (!$membre) { echo "Pas connecté — OK (pas d'erreur)\n"; exit; }
echo "Membre: " . $membre['email'] . "\n";

// Tester les actions POST-like
echo "Test dons query...\n";
$dons = $db->prepare("SELECT * FROM member_dons WHERE member_id=? ORDER BY date_don DESC");
$dons->execute([$membre['id']]);
$historique = $dons->fetchAll();
echo "Dons: " . count($historique) . "\n";

$stmt_total = $db->prepare("SELECT COALESCE(SUM(montant),0) FROM member_dons WHERE member_id=? AND statut='confirme'");
$stmt_total->execute([$membre['id']]);
$total = $stmt_total->fetchColumn();
echo "Total: $total\n";

echo "✅ Tout OK\n";
