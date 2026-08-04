<?php
/* outils-action-migration.php — v1
 * Ajoute les colonnes de participation à l'action judiciaire dans `members`.
 * ⚠ À SUPPRIMER après usage.
 */
require_once __DIR__ . '/config.php';
// Le cookie de session peut être limité à /admin/ selon la configuration du serveur :
// on force le chemin racine AVANT de démarrer la session pour retrouver la session admin.
if (session_status() === PHP_SESSION_NONE) {
    $p = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => $p['lifetime'], 'path' => '/',
        'domain'   => $p['domain'],
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();
}
if (!function_exists('isAdminLoggedIn') || !isAdminLoggedIn()) {
    http_response_code(403);
    echo '<!DOCTYPE html><meta charset="utf-8"><div style="font-family:Arial;max-width:520px;margin:60px auto;padding:26px 30px;background:#fff;border-radius:12px;box-shadow:0 2px 14px rgba(0,0,0,.1)">'
       . '<h2 style="color:#0e3d6b;margin:0 0 10px;font-size:1.1rem">🔒 Accès refusé</h2>'
       . '<p style="color:#555;font-size:.9rem;line-height:1.6">Vous devez être connecté à l\'administration pour utiliser cet outil.</p>'
       . '<p style="font-size:.8rem;color:#999">Session PHP : ' . session_status() . ' · ID : ' . (session_id() ?: 'aucun') . '</p>'
       . '<a href="/admin/login.php" style="display:inline-block;margin-top:8px;background:#FF9900;color:#fff;font-weight:700;padding:11px 24px;border-radius:8px;text-decoration:none;font-size:.9rem">Se connecter →</a>'
       . '</div>';
    exit;
}

$db  = getDB();
$log = array();
$done = false;

$COLS = array(
    'action_participe'     => "TINYINT(1) NOT NULL DEFAULT 0",
    'action_participe_at'  => "DATETIME NULL DEFAULT NULL",
    'action_participe_ip'  => "VARCHAR(45) NULL DEFAULT NULL",
);

// État actuel
$existantes = array();
try {
    foreach ($db->query("SHOW COLUMNS FROM members")->fetchAll() as $c) $existantes[] = $c['Field'];
} catch (Exception $e) { $log[] = '❌ Lecture impossible : '.$e->getMessage(); }

$manquantes = array_diff(array_keys($COLS), $existantes);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['go'])) {
    foreach ($manquantes as $col) {
        try {
            $db->exec("ALTER TABLE members ADD COLUMN `$col` ".$COLS[$col]);
            $log[] = "✅ Colonne « $col » ajoutée.";
        } catch (Exception $e) { $log[] = "❌ $col : ".$e->getMessage(); }
    }
    try { $db->exec("CREATE INDEX idx_action_participe ON members (action_participe)"); $log[] = "✅ Index créé."; }
    catch (Exception $e) { $log[] = "ℹ Index : déjà présent ou non nécessaire."; }
    if (!$manquantes) $log[] = "ℹ Toutes les colonnes existaient déjà — rien à faire.";
    $done = true;
}
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><title>Migration — Participation action</title>
<style>
body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333;margin:0;padding:36px 18px}
.box{max-width:640px;margin:0 auto;background:#fff;border-radius:14px;box-shadow:0 2px 14px rgba(0,0,0,.09);padding:28px 32px}
h1{font-size:1.2rem;color:#0e3d6b;margin:0 0 6px}.sub{font-size:.84rem;color:#888;margin-bottom:20px}
table{width:100%;border-collapse:collapse;font-size:.85rem;margin-bottom:18px}
th{text-align:left;padding:8px 10px;background:#f7fafd;color:#0e3d6b;font-size:.7rem;text-transform:uppercase;border-bottom:2px solid #e6eef7}
td{padding:8px 10px;border-bottom:1px solid #f0f4f8}
code{font-family:ui-monospace,Menlo,monospace;font-size:.82rem;background:#f4f7fb;padding:1px 6px;border-radius:4px}
.ok{color:#1a6e3c;font-weight:700}.miss{color:#cc7a00;font-weight:700}
.btn{padding:12px 26px;border:none;border-radius:9px;font-size:.9rem;font-weight:700;cursor:pointer;background:#FF9900;color:#fff;font-family:inherit}
.btn:hover{background:#e08800}
.log{background:#0e2438;color:#d6e4f0;padding:14px 18px;border-radius:9px;font-size:.82rem;line-height:1.8;margin-bottom:18px}
.warn{background:#fdecea;border:1px solid #f5b7b1;color:#922b21;padding:13px 16px;border-radius:9px;font-size:.82rem;margin-top:20px;line-height:1.6}
a{color:#1673B2;font-weight:700;text-decoration:none;font-size:.88rem;margin-right:14px}
</style></head><body>
<div class="box">
  <h1>🗄 Migration — Participation à l'action</h1>
  <div class="sub">Ajoute les colonnes nécessaires au recensement des volontaires.</div>

  <?php if ($log): ?><div class="log"><?php foreach($log as $l) echo htmlspecialchars($l).'<br>'; ?></div><?php endif; ?>

  <table>
    <tr><th>Colonne</th><th>Type</th><th>État</th></tr>
    <?php foreach ($COLS as $c => $t): $ok = in_array($c, $existantes); ?>
    <tr><td><code><?= $c ?></code></td><td style="color:#888;font-size:.78rem"><?= $t ?></td>
        <td class="<?= $ok?'ok':'miss' ?>"><?= $ok ? '✅ présente' : '⚠ à créer' ?></td></tr>
    <?php endforeach; ?>
  </table>

  <?php if (!$done && $manquantes): ?>
    <form method="POST"><button type="submit" name="go" value="1" class="btn">🗄 Créer les <?= count($manquantes) ?> colonne(s)</button></form>
  <?php elseif (!$manquantes): ?>
    <p style="color:#1a6e3c;font-weight:700;font-size:.9rem">✅ La base est prête.</p>
    <a href="/admin/action_participants.php" target="_blank">📋 Voir les participants</a>
    <a href="/membre/dashboard.php" target="_blank">👤 Espace membre</a>
  <?php endif; ?>

  <div class="warn"><strong>⚠</strong> Supprimez ce fichier du serveur une fois la migration effectuée.</div>
</div></body></html>
