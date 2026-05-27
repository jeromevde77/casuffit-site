<?php
// admin/emergency.php — Récupération d'urgence (désactivation 2FA sans connexion)
// USAGE : déposer un fichier "EMERGENCY_ACTIVE" dans ce dossier via FTP
// Le fichier doit contenir un token secret que tu choisis (ex: un mot de passe fort)

$tokenFile = __DIR__ . '/EMERGENCY_ACTIVE';

// Ce fichier n'est actif que si EMERGENCY_ACTIVE est présent
if (!file_exists($tokenFile)) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><title>404</title></head><body><h2>Page introuvable</h2>
    <p><small>Pour activer la procédure d\'urgence : dépose un fichier <code>EMERGENCY_ACTIVE</code> 
    (contenant un token secret) dans le dossier <code>admin/</code> via FTP.</small></p></body></html>';
    exit;
}

$token_expected = trim(file_get_contents($tokenFile));
$error = $success = '';

require_once __DIR__ . '/../config.php';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim($_POST['token'] ?? '');
    $uid   = (int)($_POST['user_id'] ?? 0);
    $act   = $_POST['action'] ?? '';

    if (!hash_equals($token_expected, $token)) {
        $error = 'Token incorrect.';
    } elseif ($act === 'disable_totp' && $uid > 0) {
        $s = $db->prepare("SELECT username FROM admin_users WHERE id=?"); $s->execute([$uid]); $u = $s->fetch();
        if (!$u) { $error = 'Compte introuvable.'; }
        else {
            $db->prepare("UPDATE admin_users SET totp_secret=NULL, totp_enabled=0, totp_backup_codes=NULL, totp_setup_at=NULL, failed_attempts=0, locked_until=NULL WHERE id=?")
               ->execute([$uid]);
            $success = "✅ 2FA désactivé pour « {$u['username']} ». Tu peux maintenant te connecter avec ton mot de passe uniquement, puis reconfigurer le 2FA.";
            // Logger l'action
            error_log("[EMERGENCY] 2FA disabled for user $uid ({$u['username']}) from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        }
    } elseif ($act === 'unlock' && $uid > 0) {
        $db->prepare("UPDATE admin_users SET failed_attempts=0, locked_until=NULL WHERE id=?")->execute([$uid]);
        $success = '✅ Compte débloqué.';
    }
}

try {
    $users = $db->query("SELECT id, username, role, totp_enabled, is_active, locked_until, failed_attempts FROM admin_users ORDER BY role DESC, username")->fetchAll();
} catch (Exception $e) { $users = []; }
?>
<!DOCTYPE html>
<html lang="fr"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>🆘 Récupération d'urgence</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:sans-serif;background:#fff3cd;min-height:100vh;padding:24px;color:#333}
.box{max-width:560px;margin:0 auto}
.warn{background:#ff9900;color:#000;padding:14px 18px;border-radius:8px;font-weight:700;margin-bottom:20px;font-size:.95rem}
.card{background:#fff;border-radius:10px;padding:22px;box-shadow:0 2px 12px rgba(0,0,0,.1);margin-bottom:16px}
h2{font-size:1.1rem;font-weight:800;color:#7a2a00;margin-bottom:12px}
label{display:block;font-size:.8rem;font-weight:600;color:#555;margin-bottom:4px}
input[type=text],input[type=password]{width:100%;padding:10px 12px;border:1.5px solid #ddd;border-radius:6px;font-size:.9rem;margin-bottom:12px;font-family:inherit}
button{padding:10px 18px;border-radius:6px;font-size:.85rem;font-weight:700;cursor:pointer;border:none;font-family:inherit}
.btn-r{background:#e53e3e;color:#fff}.btn-g{background:#38a169;color:#fff}.btn-grey{background:#eee;color:#555}
.error{background:#fde8e8;color:#c0392b;padding:10px;border-radius:6px;margin-bottom:14px;font-size:.83rem}
.success{background:#e8f8f0;color:#276749;padding:12px;border-radius:6px;margin-bottom:14px;font-size:.85rem;font-weight:600}
table{width:100%;border-collapse:collapse;font-size:.8rem}
th,td{padding:7px 10px;text-align:left;border-bottom:1px solid #f0f0f0}
th{color:#888;font-size:.68rem;text-transform:uppercase}
.badge{display:inline-block;padding:2px 7px;border-radius:8px;font-size:.65rem;font-weight:700}
.b-ok{background:#e8f8f0;color:#27ae60}.b-off{background:#fde8e8;color:#c0392b}.b-warn{background:#fff8ee;color:#b45309}
</style>
</head>
<body>
<div class="box">
  <div class="warn">⚠️ PROCÉDURE D'URGENCE — Accès non authentifié — Supprimer EMERGENCY_ACTIVE après usage</div>

  <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

  <div class="card">
    <h2>🔓 Désactiver le 2FA d'un compte</h2>
    <form method="POST">
      <label>Token d'urgence (contenu du fichier EMERGENCY_ACTIVE)</label>
      <input type="password" name="token" required placeholder="token secret">
      <label>Compte</label>
      <select name="user_id" style="width:100%;padding:10px;border:1.5px solid #ddd;border-radius:6px;margin-bottom:12px;font-family:inherit">
        <?php foreach ($users as $u): ?>
        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['username']) ?> (<?= $u['role'] ?>)<?= !$u['is_active']?' — INACTIF':'' ?><?= $u['totp_enabled']?' — 2FA actif':' — pas de 2FA' ?></option>
        <?php endforeach; ?>
      </select>
      <div style="display:flex;gap:8px">
        <button type="submit" name="action" value="disable_totp" class="btn-r">🔓 Désactiver 2FA</button>
        <button type="submit" name="action" value="unlock" class="btn-g">🔓 Débloquer compte</button>
      </div>
    </form>
  </div>

  <div class="card">
    <h2>📋 Comptes admin</h2>
    <table>
      <tr><th>Utilisateur</th><th>Rôle</th><th>2FA</th><th>Actif</th><th>Tentatives</th></tr>
      <?php foreach ($users as $u): ?>
      <tr>
        <td><?= htmlspecialchars($u['username']) ?></td>
        <td><?= htmlspecialchars($u['role']) ?></td>
        <td><span class="badge <?= $u['totp_enabled']?'b-ok':'b-off' ?>"><?= $u['totp_enabled']?'Oui':'Non' ?></span></td>
        <td><span class="badge <?= $u['is_active']?'b-ok':'b-off' ?>"><?= $u['is_active']?'Oui':'Non' ?></span></td>
        <td><?= $u['failed_attempts'] ?><?= $u['locked_until']&&strtotime($u['locked_until'])>time()?' 🔒':'' ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <div style="text-align:center;margin-top:16px">
    <a href="login.php" style="color:#555;font-size:.8rem">→ Aller à la connexion</a>
  </div>
  <p style="font-size:.7rem;color:#888;text-align:center;margin-top:12px">
    ⚠ Supprime le fichier <code>EMERGENCY_ACTIVE</code> du serveur FTP dès que tu as retrouvé l'accès.
  </p>
</div>
</body>
</html>
