<?php
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
            'Conditions de vent'          => ['/?page=conditions-vent',        'Conditions de vent'],
            'Historique du vent'          => ['/?page=historique-du-vent',      'Historique du vent'],
            'Rose des vents'              => ['/?page=rose-des-vents',          'Rose des vents'],
            'Radar de vols en temps réel' => ['/?page=vols-en-temps-reel',      'Radar de vols en temps réel'],
        ];
        foreach ($map as $key => [$url, $label]) {
            if (stripos($inner, $key) !== false)
                return '<div class="ac-titre"><a href="'.$url.'">'.$label.' →</a></div>';
        }
        return $m[0];
    },
    $contenu
);

echo "APRÈS:\n";
preg_match_all('/<div class="ac-titre">.*?<\/div>/s', $nouveau, $m2);
foreach ($m2[0] as $x) echo strip_tags($x)."\n";
$db->prepare("UPDATE news SET contenu=? WHERE id=3")->execute([$nouveau]);
echo "✅ MIS À JOUR.\n";
