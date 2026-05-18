<?php
require_once __DIR__ . '/config.php';
$db = getDB();
$row = $db->query("SELECT contenu FROM news WHERE id=3")->fetch();
$contenu = $row['contenu'];

// Retirer tous les liens dans les ac-titre, garder juste le texte
$nouveau = preg_replace_callback(
    '/<div class="ac-titre">(.*?)<\/div>/s',
    function($m) {
        // Extraire le texte brut, retirer → final
        $texte = strip_tags($m[1]);
        $texte = trim(preg_replace('/\s*→\s*$/', '', $texte));
        return '<div class="ac-titre">' . htmlspecialchars($texte) . '</div>';
    },
    $contenu
);

echo "APRÈS revert:\n";
preg_match_all('/<div class="ac-titre">.*?<\/div>/s', $nouveau, $m2);
foreach ($m2[0] as $x) echo $x . "\n";
$db->prepare("UPDATE news SET contenu=? WHERE id=3")->execute([$nouveau]);
echo "✅ REVERT OK.\n";
