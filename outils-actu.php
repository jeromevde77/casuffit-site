<?php
// outils-actu.php — Diagnostic & fix widgets actualités (racine, hors SW admin)
require_once __DIR__ . '/config.php';
session_start();
require_once __DIR__ . '/membre/functions.php';

// Vérif admin simple
if (empty($_SESSION['admin_logged_in'])) {
    echo '<p style="font-family:sans-serif;max-width:600px;margin:40px auto">⚠ Tu dois être connecté à l\'admin. <a href="/admin/login.php">→ Se connecter</a> puis revenir sur cette page.</p>';
    exit;
}

header('Content-Type: text/html; charset=utf-8');
$db = getDB();
echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Diag widgets</title>';
echo '<style>body{font-family:sans-serif;max-width:800px;margin:30px auto;padding:0 20px;line-height:1.6}';
echo 'table{border-collapse:collapse;width:100%;margin:10px 0}td,th{border:1px solid #ccc;padding:6px 10px;font-size:.9rem;text-align:left}';
echo '.ok{color:#27ae60;font-weight:700}.err{color:#c0392b;font-weight:700}code{background:#eef2f7;padding:2px 6px;border-radius:4px}</style></head><body>';
echo '<h2>🔧 Diagnostic widgets — onglet Actualités</h2>';
echo '<p>Fichier servi : <code>outils-actu.php</code> · ' . date('Y-m-d H:i:s') . '</p>';

try {
    echo '<h3>1. page_widgets actuel</h3>';
    $all = $db->query("SELECT page_slug, widget_slug, ordre, position FROM page_widgets ORDER BY page_slug, ordre")->fetchAll(PDO::FETCH_ASSOC);
    echo '<table><tr><th>page_slug</th><th>widget</th><th>ordre</th><th>pos</th></tr>';
    $slugs_actu = [];
    foreach ($all as $r) {
        $isA = stripos($r['page_slug'],'actu') !== false;
        if ($isA) $slugs_actu[] = $r['page_slug'];
        $hl = $isA ? ' style="background:#fff3cd"' : '';
        echo "<tr$hl><td><code>{$r['page_slug']}</code></td><td>{$r['widget_slug']}</td><td>{$r['ordre']}</td><td>{$r['position']}</td></tr>";
    }
    echo '</table>';
    echo '<p>Slugs "actu" trouvés : <code>' . (count($slugs_actu)?implode(', ',array_unique($slugs_actu)):'AUCUN') . '</code></p>';

    echo '<h3>2. Widgets (actif ?)</h3>';
    $ws = $db->query("SELECT slug, actif FROM widgets ORDER BY slug")->fetchAll(PDO::FETCH_ASSOC);
    echo '<table><tr><th>slug</th><th>actif</th></tr>';
    foreach ($ws as $w) echo "<tr><td>{$w['slug']}</td><td>".($w['actif']?'<span class=ok>oui</span>':'<span class=err>NON</span>')."</td></tr>";
    echo '</table>';

    if (($_GET['apply'] ?? '') === '1') {
        echo '<h3>3. Application</h3>';
        $targets = array_unique(array_merge($slugs_actu, ['actualites']));
        foreach ($targets as $t) {
            $db->prepare("DELETE FROM page_widgets WHERE page_slug=?")->execute([$t]);
            $db->prepare("INSERT INTO page_widgets (page_slug,widget_slug,ordre,position) VALUES (?,'news',1,'droite')")->execute([$t]);
            $db->prepare("INSERT INTO page_widgets (page_slug,widget_slug,ordre,position) VALUES (?,'donation_card',2,'droite')")->execute([$t]);
            echo "<p class=ok>✅ <code>$t</code> → news + donation_card</p>";
        }
        $db->prepare("UPDATE widgets SET actif=1 WHERE slug='donation_card'")->execute();
        echo '<p class=ok>✅ donation_card actif=1</p>';
        echo '<p><a href="/?news=4" style="background:#1673B2;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none">→ Tester /?news=4</a></p>';
    } else {
        echo '<h3>3. Appliquer le changement</h3>';
        echo '<p><a href="/outils-actu.php?apply=1" style="background:#FF9900;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:700">⚙ Appliquer : news + donation_card</a></p>';
    }
} catch (Exception $e) {
    echo '<p class=err>❌ '.htmlspecialchars($e->getMessage()).'</p>';
}
echo '</body></html>';
