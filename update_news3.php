<?php
// Script one-shot — à supprimer après exécution
require_once __DIR__ . '/config.php';
$db = getDB();

$row = $db->query("SELECT contenu FROM news WHERE id=3")->fetch();
$contenu = $row['contenu'];

// Remplacer les ac-titre sans lien par des ac-titre avec lien
$replacements = [
    '<div class="ac-titre">Conditions de vent</div>'
        => '<div class="ac-titre"><a href="https://www.casuffit.be/wind.php">Conditions de vent →</a></div>',
    '<div class="ac-titre">Historique du vent</div>'
        => '<div class="ac-titre"><a href="https://www.casuffit.be/wind.php">Historique du vent →</a></div>',
    '<div class="ac-titre">Rose des vents</div>'
        => '<div class="ac-titre"><a href="https://www.casuffit.be/wind.php">Rose des vents →</a></div>',
    '<div class="ac-titre">Radar de vols en temps réel</div>'
        => '<div class="ac-titre"><a href="https://www.casuffit.be/wind.php">Radar de vols en temps réel →</a></div>',
    // Aussi couvrir la version avec style="color:#0e3d6b;text-decoration:none"
    '<div class="ac-titre"><a href="https://www.casuffit.be/wind.php" style="color:#0e3d6b;text-decoration:none">Conditions de vent →</a></div>'
        => '<div class="ac-titre"><a href="https://www.casuffit.be/wind.php">Conditions de vent →</a></div>',
    '<div class="ac-titre"><a href="https://www.casuffit.be/wind.php#historique" style="color:#0e3d6b;text-decoration:none">Historique du vent →</a></div>'
        => '<div class="ac-titre"><a href="https://www.casuffit.be/wind.php">Historique du vent →</a></div>',
    '<div class="ac-titre"><a href="https://www.casuffit.be/wind.php#rose" style="color:#0e3d6b;text-decoration:none">Rose des vents →</a></div>'
        => '<div class="ac-titre"><a href="https://www.casuffit.be/wind.php">Rose des vents →</a></div>',
    '<div class="ac-titre"><a href="https://www.casuffit.be/wind.php#vols" style="color:#0e3d6b;text-decoration:none">Radar de vols en temps réel →</a></div>'
        => '<div class="ac-titre"><a href="https://www.casuffit.be/wind.php">Radar de vols en temps réel →</a></div>',
];

// Afficher le contenu actuel pour diagnostic
echo "=== CONTENU ACTUEL (ac-titre) ===\n";
preg_match_all('/<div class="ac-titre">[^<]*(?:<a[^>]*>[^<]*<\/a>)?[^<]*<\/div>/', $contenu, $matches);
foreach ($matches[0] as $m) echo $m . "\n";

$nouveau = strtr($contenu, $replacements);

if ($nouveau === $contenu) {
    echo "\n=== AUCUN REMPLACEMENT — recherche pattern générique ===\n";
    // Pattern générique pour tout ac-titre
    $nouveau = preg_replace_callback(
        '/<div class="ac-titre">(.*?)<\/div>/s',
        function($m) {
            $inner = $m[1];
            echo "TROUVÉ: " . $inner . "\n";
            // Si déjà un lien, retirer le style inline
            if (strpos($inner, '<a ') !== false) {
                $inner = preg_replace('/\s*style="[^"]*"/', '', $inner);
                return '<div class="ac-titre">' . $inner . '</div>';
            }
            return $m[0]; // pas de lien → laisser tel quel
        },
        $contenu
    );
} else {
    echo "\n=== REMPLACEMENTS EFFECTUÉS ===\n";
}

$db->prepare("UPDATE news SET contenu=? WHERE id=3")->execute([$nouveau]);
echo "\nMIS À JOUR en BDD.\n";
