<?php
// outils-emails.php — Scanne & corrige "Ça suffit ! ASBL" dans email_templates (racine, hors SW)
require_once __DIR__ . '/config.php';
session_start();
if (empty($_SESSION['admin_logged_in'])) {
    echo '<p style="font-family:sans-serif;max-width:600px;margin:40px auto">⚠ Connecte-toi à l\'admin. <a href="/admin/login.php">→ Login</a> puis reviens ici.</p>';
    exit;
}
header('Content-Type: text/html; charset=utf-8');
$db = getDB();

// Variantes à remplacer → "Ça suffit !"
$variants = [
    'Ça suffit ! ASBL' => 'Ça suffit !',
    'Ça suffit! ASBL'  => 'Ça suffit !',
    'Ça Suffit ! ASBL' => 'Ça suffit !',
    'Ca suffit ! ASBL' => 'Ça suffit !',
    'ca suffit ! ASBL' => 'ca suffit !',
];

echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Fix emails</title>';
echo '<style>body{font-family:sans-serif;max-width:800px;margin:30px auto;padding:0 20px;line-height:1.6}';
echo '.ok{color:#27ae60;font-weight:700}.err{color:#c0392b}code{background:#eef2f7;padding:2px 6px;border-radius:4px}</style></head><body>';
echo '<h2>📧 Correction "Ça suffit ! ASBL" → "Ça suffit !" dans email_templates</h2>';
echo '<p>'.date('Y-m-d H:i:s').'</p>';

try {
    $rows = $db->query("SELECT slug, sujet_fr, sujet_nl, contenu_fr, contenu_nl FROM email_templates")->fetchAll(PDO::FETCH_ASSOC);
    echo '<h3>Scan ('.count($rows).' templates)</h3><ul>';
    $apply = (($_GET['apply'] ?? '') === '1');
    $total_fix = 0;
    foreach ($rows as $r) {
        $fields = ['sujet_fr','sujet_nl','contenu_fr','contenu_nl'];
        $new = []; $found = false;
        foreach ($fields as $f) {
            $val = $r[$f] ?? '';
            $orig = $val;
            foreach ($variants as $old => $rep) $val = str_replace($old, $rep, $val);
            $new[$f] = $val;
            if ($val !== $orig) $found = true;
        }
        if ($found) {
            $total_fix++;
            echo '<li><code>'.htmlspecialchars($r['slug']).'</code> — contient "ASBL" '.($apply?'<span class=ok>→ corrigé</span>':'<span class=err>(à corriger)</span>').'</li>';
            if ($apply) {
                $db->prepare("UPDATE email_templates SET sujet_fr=?, sujet_nl=?, contenu_fr=?, contenu_nl=? WHERE slug=?")
                   ->execute([$new['sujet_fr'], $new['sujet_nl'], $new['contenu_fr'], $new['contenu_nl'], $r['slug']]);
            }
        }
    }
    echo '</ul>';
    if ($total_fix === 0) {
        echo '<p class=ok>✅ Aucun template ne contient "Ça suffit ! ASBL". Tout est déjà propre.</p>';
    } elseif (!$apply) {
        echo '<p><strong>'.$total_fix.' template(s)</strong> à corriger.</p>';
        echo '<p><a href="/outils-emails.php?apply=1" style="background:#FF9900;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:700">⚙ Appliquer la correction</a></p>';
    } else {
        echo '<p class=ok>✅ '.$total_fix.' template(s) corrigé(s) !</p>';
    }
} catch (Exception $e) {
    echo '<p class=err>❌ '.htmlspecialchars($e->getMessage()).'</p>';
}
echo '</body></html>';
