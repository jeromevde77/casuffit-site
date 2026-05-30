<?php
// outils-emails.php — Scanne & corrige "Ça suffit ! ASBL" dans TOUTES les tables de contenu
require_once __DIR__ . '/config.php';
session_start();
if (empty($_SESSION['admin_logged_in'])) {
    echo '<p style="font-family:sans-serif;max-width:600px;margin:40px auto">⚠ Connecte-toi à l\'admin. <a href="/admin/login.php">→ Login</a> puis reviens ici.</p>';
    exit;
}
header('Content-Type: text/html; charset=utf-8');
$db = getDB();

$variants = [
    'Ça suffit ! ASBL' => 'Ça suffit !',
    'Ça suffit! ASBL'  => 'Ça suffit !',
    'Ça Suffit ! ASBL' => 'Ça suffit !',
    'Ca suffit ! ASBL' => 'Ça suffit !',
    'ca suffit ! ASBL' => 'ca suffit !',
];
function fix_str($s, $variants) {
    if ($s === null) return [null, false];
    $o = $s;
    foreach ($variants as $old => $rep) $s = str_replace($old, $rep, $s);
    return [$s, $s !== $o];
}

// Tables à scanner : table => [clé primaire, [colonnes texte]]
$tables = [
    'email_templates' => ['id', ['sujet_fr','sujet_nl','contenu_fr','contenu_nl']],
    'pages'           => ['id', ['titre','titre_nl','contenu','contenu_nl','meta_description','meta_description_nl']],
    'news'            => ['id', ['titre','titre_nl','accroche','accroche_nl','contenu','contenu_nl']],
    'site_config'     => ['cle', ['valeur']],
];

echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Fix DB ASBL</title>';
echo '<style>body{font-family:sans-serif;max-width:850px;margin:30px auto;padding:0 20px;line-height:1.6}';
echo '.ok{color:#27ae60;font-weight:700}.err{color:#c0392b}code{background:#eef2f7;padding:2px 6px;border-radius:4px}';
echo 'h3{margin-top:24px;border-bottom:1px solid #ddd;padding-bottom:4px}</style></head><body>';
echo '<h2>🔍 Scan "Ça suffit ! ASBL" → "Ça suffit !" dans toute la base</h2><p>'.date('Y-m-d H:i:s').'</p>';

$apply = (($_GET['apply'] ?? '') === '1');
$total = 0;

foreach ($tables as $table => $def) {
    list($pk, $cols) = $def;
    echo "<h3>Table <code>$table</code></h3>";
    try {
        $collist = implode(',', array_merge([$pk], $cols));
        $rows = $db->query("SELECT $collist FROM $table")->fetchAll(PDO::FETCH_ASSOC);
        $found_here = 0;
        echo '<ul>';
        foreach ($rows as $r) {
            $new = []; $changed = false;
            foreach ($cols as $c) {
                list($v, $ch) = fix_str($r[$c] ?? null, $variants);
                $new[$c] = $v;
                if ($ch) $changed = true;
            }
            if ($changed) {
                $found_here++; $total++;
                $id = $r[$pk];
                echo "<li><code>".htmlspecialchars((string)$id)."</code> ".($apply?'<span class=ok>→ corrigé</span>':'<span class=err>(à corriger)</span>')."</li>";
                if ($apply) {
                    $set = implode(',', array_map(fn($c) => "$c=?", $cols));
                    $params = array_map(fn($c) => $new[$c], $cols);
                    $params[] = $id;
                    $db->prepare("UPDATE $table SET $set WHERE $pk=?")->execute($params);
                }
            }
        }
        echo '</ul>';
        if ($found_here === 0) echo '<p class=ok>✅ Rien à corriger.</p>';
    } catch (Exception $e) {
        echo '<p class=err>⚠ '.htmlspecialchars($e->getMessage()).'</p>';
    }
}

echo '<hr>';
if ($total === 0) {
    echo '<p class=ok style="font-size:1.1rem">✅ Toute la base est propre — aucun "Ça suffit ! ASBL" restant.</p>';
} elseif (!$apply) {
    echo '<p><strong>'.$total.' enregistrement(s)</strong> à corriger au total.</p>';
    echo '<p><a href="/outils-emails.php?apply=1" style="background:#FF9900;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:700">⚙ Tout corriger</a></p>';
} else {
    echo '<p class=ok style="font-size:1.1rem">✅ '.$total.' enregistrement(s) corrigé(s) dans toute la base !</p>';
}
echo '</body></html>';
