<?php
/* outils-diag-session.php — diagnostic session admin
 * ⚠ À SUPPRIMER après usage.
 */
$avant = array(
    'status_avant_config' => session_status(),
    'id_avant_config'     => session_id(),
);

require_once __DIR__ . '/config.php';

$apres_config = array(
    'status' => session_status(),
    'id'     => session_id(),
);

if (session_status() === PHP_SESSION_NONE) session_start();

$sess = $_SESSION;
$ini = array(
    'session.name'          => ini_get('session.name'),
    'session.save_path'     => ini_get('session.save_path'),
    'session.cookie_path'   => ini_get('session.cookie_path'),
    'session.cookie_domain' => ini_get('session.cookie_domain'),
    'session.cookie_secure' => ini_get('session.cookie_secure'),
    'session.use_strict_mode'=> ini_get('session.use_strict_mode'),
    'session.gc_maxlifetime'=> ini_get('session.gc_maxlifetime'),
);
$logged = !empty($_SESSION['admin_logged_in']);
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><title>Diagnostic session</title>
<style>
body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333;margin:0;padding:32px 18px}
.box{max-width:720px;margin:0 auto 18px;background:#fff;border-radius:14px;box-shadow:0 2px 14px rgba(0,0,0,.09);padding:24px 28px}
h1{font-size:1.15rem;color:#0e3d6b;margin:0 0 16px}
h2{font-size:.86rem;color:#0e3d6b;margin:0 0 10px;padding-bottom:7px;border-bottom:2px solid #f0f4f8}
table{width:100%;border-collapse:collapse;font-size:.82rem}
td{padding:7px 9px;border-bottom:1px solid #f4f7fb;vertical-align:top}
td:first-child{color:#888;width:44%;font-weight:600}
code{font-family:ui-monospace,Menlo,monospace;font-size:.8rem;background:#f4f7fb;padding:2px 6px;border-radius:4px;word-break:break-all}
.big{padding:16px 20px;border-radius:11px;font-size:.95rem;font-weight:700;margin-bottom:18px;text-align:center}
.ok{background:#e8f8f0;color:#1a6e3c;border:2px solid #a8e6c0}
.no{background:#fdecea;color:#922b21;border:2px solid #f5b7b1}
.hint{background:#eff6ff;border:1px solid #cfe2fb;color:#1673B2;padding:13px 16px;border-radius:9px;font-size:.82rem;line-height:1.65;margin-top:14px}
a{color:#1673B2;font-weight:700}
</style></head><body>

<div class="box">
  <h1>🔍 Diagnostic de session</h1>
  <div class="big <?= $logged ? 'ok' : 'no' ?>">
    <?= $logged
      ? '✅ Session admin DÉTECTÉE — les outils devraient fonctionner.'
      : '❌ Session admin NON détectée depuis la racine du site.' ?>
  </div>

  <h2>État de la session</h2>
  <table>
    <tr><td>Statut avant config.php</td><td><code><?= $avant['status_avant_config'] ?></code>
        <?= $avant['status_avant_config']==1?'(aucune)':($avant['status_avant_config']==2?'(active)':'(désactivée)') ?></td></tr>
    <tr><td>Statut après config.php</td><td><code><?= $apres_config['status'] ?></code></td></tr>
    <tr><td>ID de session actuel</td><td><code><?= session_id() ?: '(aucun)' ?></code></td></tr>
    <tr><td>Cookie reçu du navigateur</td><td><code><?= htmlspecialchars($_COOKIE[ini_get('session.name')] ?? '(absent)') ?></code></td></tr>
    <tr><td>Clés présentes dans $_SESSION</td><td><code><?= $sess ? htmlspecialchars(implode(', ', array_keys($sess))) : '(vide)' ?></code></td></tr>
    <tr><td>admin_logged_in</td><td><code><?= var_export($_SESSION['admin_logged_in'] ?? null, true) ?></code></td></tr>
    <tr><td>admin_user</td><td><code><?= htmlspecialchars($_SESSION['admin_user'] ?? '(absent)') ?></code></td></tr>
    <tr><td>admin_role</td><td><code><?= htmlspecialchars($_SESSION['admin_role'] ?? '(absent)') ?></code></td></tr>
  </table>
</div>

<div class="box">
  <h2>Configuration PHP des sessions</h2>
  <table>
    <?php foreach ($ini as $k => $v): ?>
    <tr><td><?= $k ?></td><td><code><?= htmlspecialchars($v !== '' ? $v : '(vide)') ?></code></td></tr>
    <?php endforeach; ?>
    <tr><td>Version PHP</td><td><code><?= PHP_VERSION ?></code></td></tr>
    <tr><td>Chemin du script</td><td><code><?= htmlspecialchars($_SERVER['SCRIPT_NAME'] ?? '') ?></code></td></tr>
    <tr><td>HTTPS</td><td><code><?= !empty($_SERVER['HTTPS']) ? 'oui' : 'non' ?></code></td></tr>
  </table>

  <div class="hint">
    <strong>Comment lire ce diagnostic</strong><br>
    • Si le <strong>cookie est absent</strong> alors que vous êtes connecté à l'admin, le cookie de session
      est limité au dossier <code>/admin/</code> — c'est la cause la plus probable.<br>
    • Si le cookie est présent mais <code>$_SESSION</code> est vide, les fichiers de session ne sont pas
      partagés (<code>save_path</code> différent entre la racine et <code>/admin/</code>).<br>
    • Si <code>session.cookie_secure</code> vaut <code>1</code> et que HTTPS indique « non », le cookie est bloqué.
  </div>
</div>

<div class="box">
  <h2>Fichiers de session sur le serveur</h2>
  <?php
    $sp = ini_get('session.save_path') ?: sys_get_temp_dir();
    $sid = session_id();
    $f_courant = $sp . '/sess_' . $sid;
    $cookie_sid = $_COOKIE[ini_get('session.name')] ?? '';
    $f_cookie = $cookie_sid ? $sp . '/sess_' . $cookie_sid : '';
  ?>
  <table>
    <tr><td>Dossier des sessions</td><td><code><?= htmlspecialchars($sp) ?></code>
        <?= is_dir($sp) ? (is_writable($sp)?' ✅ accessible':' ⚠ non inscriptible') : ' ❌ introuvable' ?></td></tr>
    <tr><td>Fichier de la session courante</td><td><code><?= htmlspecialchars($f_courant) ?></code><br>
        <?= file_exists($f_courant) ? '✅ existe ('.filesize($f_courant).' o)' : '❌ absent' ?></td></tr>
    <?php if ($f_cookie && $f_cookie !== $f_courant): ?>
    <tr><td>Fichier de la session du cookie</td><td><code><?= htmlspecialchars($f_cookie) ?></code><br>
        <?= file_exists($f_cookie) ? '✅ existe ('.filesize($f_cookie).' o)' : '❌ absent' ?></td></tr>
    <?php endif; ?>
    <?php
      $found = @glob($sp . '/sess_*');
      $n = is_array($found) ? count($found) : 0;
    ?>
    <tr><td>Sessions présentes dans le dossier</td><td><code><?= $n ?></code>
        <?= $n === 0 ? ' — le dossier est vide ou illisible' : '' ?></td></tr>
    <?php if (file_exists($f_courant)): ?>
    <tr><td>Contenu brut du fichier</td><td><code><?= htmlspecialchars(substr(@file_get_contents($f_courant), 0, 300)) ?: '(vide)' ?></code></td></tr>
    <?php endif; ?>
  </table>
  <div class="hint">
    Si le fichier de session <strong>existe mais que <code>$_SESSION</code> est vide</strong>, PHP n'arrive pas
    à le lire : soit une autre version de PHP écrit ailleurs, soit les permissions bloquent la lecture.<br>
    Si le fichier est <strong>absent</strong> alors que le cookie est présent, la session a expiré ou a été
    détruite — reconnectez-vous à l'admin puis rechargez cette page <em>sans</em> vous reconnecter entre-temps.
  </div>
</div>

<div class="box" style="font-size:.85rem">
  <a href="/admin/dashboard.php">← Retour à l'admin</a> ·
  <a href="/outils-irm-migration.php">Migration IRM</a> ·
  <a href="/outils-newsletter-action.php">Newsletter action</a>
  <p style="color:#922b21;margin:14px 0 0;font-size:.8rem"><strong>⚠</strong> Supprimez ce fichier après diagnostic.</p>
</div>

</body></html>
