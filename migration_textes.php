<?php
// Script one-shot : mettre à jour les textes BDD pour passer le référé au passé
require_once __DIR__ . '/config.php';
$db = getDB();

$updates = [
    // don_texte FR — affiché dans la carte de don
    'don_texte' => 'Combat juridique — Frais et procédures',
    // don_texte NL
    'don_texte_nl' => 'Juridische strijd — Kosten en procedures',
    // annonce_titre / annonce_texte si nécessaire (à vérifier)
];

foreach ($updates as $cle => $val) {
    // Vérifier que la clé existe
    $stmt = $db->prepare("SELECT cle FROM site_config WHERE cle = ?");
    $stmt->execute([$cle]);
    if ($stmt->fetch()) {
        // Existant — mise à jour (déterminer si c'est valeur ou valeur_nl)
        if (str_ends_with($cle, '_nl')) {
            $key_base = substr($cle, 0, -3);
            $db->prepare("UPDATE site_config SET valeur_nl = ? WHERE cle = ?")->execute([$val, $key_base]);
            echo "✓ {$key_base} (NL) → \"$val\"\n";
        } else {
            $db->prepare("UPDATE site_config SET valeur = ? WHERE cle = ?")->execute([$val, $cle]);
            echo "✓ {$cle} (FR) → \"$val\"\n";
        }
    } else {
        // Clé directe en NL — chercher la base
        if (str_ends_with($cle, '_nl')) {
            $key_base = substr($cle, 0, -3);
            $stmt = $db->prepare("SELECT cle FROM site_config WHERE cle = ?");
            $stmt->execute([$key_base]);
            if ($stmt->fetch()) {
                $db->prepare("UPDATE site_config SET valeur_nl = ? WHERE cle = ?")->execute([$val, $key_base]);
                echo "✓ {$key_base} (NL) → \"$val\"\n";
            } else {
                echo "✗ Clé manquante: {$key_base}\n";
            }
        }
    }
}

// Afficher le résultat final
echo "\n=== ÉTAT FINAL ===\n";
$rows = $db->query("SELECT cle, valeur, valeur_nl FROM site_config WHERE cle IN ('don_texte','annonce_titre','annonce_texte','urgence_texte')")->fetchAll();
foreach ($rows as $r) {
    echo $r['cle'] . " | FR: " . $r['valeur'] . " | NL: " . ($r['valeur_nl'] ?? '(vide)') . "\n";
}
