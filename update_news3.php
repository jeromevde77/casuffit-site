<?php
// Script one-shot — corriger les liens des ac-titre dans l'actualité ID=3
require_once __DIR__ . '/config.php';
$db = getDB();

$row = $db->query("SELECT contenu FROM news WHERE id=3")->fetch();
$contenu = $row['contenu'];

$nouveau = preg_replace_callback(
    '/<div class="ac-titre">(.*?)<\/div>/s',
    function($m) {
        $inner = strip_tags($m[1]);
        $inner = trim(preg_replace('/\s*→\s*$/', '', $inner));
        $map = [
            'Conditions de vent'          => ['https://www.casuffit.be/wind.php',                 'Conditions de vent'],
            'Historique du vent'          => ['https://www.casuffit.be/?page=historique-du-vent', 'Historique du vent'],
            'Rose des vents'              => ['https://www.casuffit.be/#rose',                    'Rose des vents'],
            'Radar de vols en temps réel' => ['https://www.casuffit.be/vols',                     'Radar de vols en temps réel'],
        ];
        foreach ($map as $key => [$url, $label]) {
            if (stripos($inner, $key) !== false)
                return '<div class="ac-titre"><a href="'.$url.'">'.$label.' →</a></div>';
        }
        return $m[0];
    },
    $contenu
);

echo "=== AVANT ===\n";
preg_match_all('/<div class="ac-titre">.*?<\/div>/s', $contenu, $m1);
foreach ($m1[0] as $x) echo strip_tags($x)."\n";
echo "\n=== APRÈS ===\n";
preg_match_all('/<div class="ac-titre">.*?<\/div>/s', $nouveau, $m2);
foreach ($m2[0] as $x) echo $x."\n";
$db->prepare("UPDATE news SET contenu=? WHERE id=3")->execute([$nouveau]);
echo "\n✅ MIS À JOUR.\n";
