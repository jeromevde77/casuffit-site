<?php
// admin/admin_users.php — Gestion des comptes administrateurs
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/totp.php';
require_once __DIR__ . '/../includes/csrf.php';
session_start(); requireAdmin();
$db       = getDB();
$me_id    = $_SESSION['admin_id'];
$me_role  = $_SESSION['admin_role'] ?? 'admin';
$msg = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_verify();

// ── Actions ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);

    // Créer un compte
    if ($action === 'create') {
        $uname = preg_replace('/[^a-z0-9_]/', '', strtolower(trim($_POST['username'] ?? '')));
        $email = trim($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';
        $role  = ($me_role === 'superadmin' && ($_POST['role'] ?? '') === 'superadmin') ? 'superadmin' : 'admin';
        if (strlen($uname) < 3) $error = 'Nom d\'utilisateur trop court.';
        elseif (strlen($pass) < 10) $error = 'Mot de passe trop court (10 car. min).';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Email invalide.';
        else {
            try {
                $db->prepare("INSERT INTO admin_users (username,email,password_hash,role) VALUES (?,?,?,?)")
                   ->execute([$uname, $email, password_hash($pass, PASSWORD_DEFAULT), $role]);
                $msg = "Compte « $uname » créé.";
            } catch (Exception $e) { $error = 'Nom d\'utilisateur déjà pris.'; }
        }
    }
    // Désactiver / Réactiver
    elseif (in_array($action, ['deactivate','activate'])) {
        $target = $db->prepare("SELECT * FROM admin_users WHERE id=?")->execute([$user_id]) ? null : null;
        $s = $db->prepare("SELECT * FROM admin_users WHERE id=?"); $s->execute([$user_id]); $target = $s->fetch();
        if (!$target) $error = 'Compte introuvable.';
        elseif ($target['role'] === 'superadmin' && $action === 'deactivate') $error = 'Impossible de désactiver un superadmin.';
        elseif ($target['id'] === $me_id) $error = 'Tu ne peux pas désactiver ton propre compte.';
        else {
            $db->prepare("UPDATE admin_users SET is_active=? WHERE id=?")->execute([$action==='activate'?1:0, $user_id]);
            $msg = $action === 'activate' ? 'Compte réactivé.' : 'Compte désactivé.';
        }
    }
    // Reset 2FA
    elseif ($action === 'reset_totp') {
        $s = $db->prepare("SELECT * FROM admin_users WHERE id=?"); $s->execute([$user_id]); $target = $s->fetch();
        if (!$target) { $error = 'Compte introuvable.'; }
        elseif ($target['role'] === 'superadmin' && $target['id'] !== $me_id && $me_role !== 'superadmin') { $error = 'Non autorisé.'; }
        else {
            $db->prepare("UPDATE admin_users SET totp_secret=NULL, totp_enabled=0, totp_backup_codes=NULL, totp_setup_at=NULL WHERE id=?")->execute([$user_id]);
            $msg = "2FA réinitialisé pour « {$target['username']} ». Il devra le reconfigurer à la prochaine connexion.";
        }
    }
    // Nouveau mot de passe
    elseif ($action === 'change_password') {
        if ($user_id !== $me_id && $me_role !== 'superadmin') { $error = 'Non autorisé.'; }
        else {
            $pass = $_POST['new_password'] ?? '';
            if (strlen($pass) < 10) $error = 'Mot de passe trop court (10 car. min).';
            else {
                $db->prepare("UPDATE admin_users SET password_hash=? WHERE id=?")->execute([password_hash($pass, PASSWORD_DEFAULT), $user_id]);
                $msg = 'Mot de passe mis à jour.';
            }
        }
    }
    // Regénérer codes de secours (pour soi-même uniquement)
    elseif ($action === 'regen_backup' && $user_id === $me_id) {
        $plain  = TOTP::generateBackupCodes(8);
        $hashed = array_map([TOTP::class, 'hashBackupCode'], $plain);
        $db->prepare("UPDATE admin_users SET totp_backup_codes=? WHERE id=?")->execute([json_encode($hashed), $me_id]);
        $_SESSION['new_backup_codes'] = $plain;
        header('Location: backup_codes.php?regen=1'); exit;
    }
}

$users = $db->query("SELECT id,username,email,role,totp_enabled,totp_setup_at,last_login,last_login_ip,is_active,failed_attempts,locked_until,created_at FROM admin_users ORDER BY role DESC, username")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr"><head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <?php include __DIR__ . '/../includes/admin_pwa_head.php'; ?>
  <title>Comptes admin</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333}
    <?php include __DIR__ . '/../includes/admin_sidebar_css.php'; ?>
    .main{margin-left:240px;padding:28px;max-width:1100px}
    .page-title{font-size:1.2rem;font-weight:800;color:#0e3d6b;margin-bottom:20px}
    .card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,.06);margin-bottom:18px;overflow-x:auto}
    .card h3{font-size:.88rem;font-weight:700;color:#0e3d6b;margin-bottom:14px;padding-bottom:8px;border-bottom:1px solid #eee}
    .flash-ok{background:#e8f8f0;color:#276749;padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:.82rem;border-left:3px solid #48bb78}
    .flash-err{background:#fde8e8;color:#c0392b;padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:.82rem;border-left:3px solid #e53e3e}
    table{width:100%;border-collapse:collapse;font-size:.8rem;white-space:nowrap}
    th{text-align:left;padding:8px 10px;color:#888;font-weight:600;font-size:.68rem;text-transform:uppercase;border-bottom:2px solid #eee}
    td{padding:8px 10px;border-bottom:1px solid #f5f5f5;vertical-align:middle}
    .badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:.65rem;font-weight:700}
    .b-ok{background:#e8f8f0;color:#27ae60}.b-off{background:#fde8e8;color:#c0392b}
    .b-sa{background:#fef3c7;color:#92400e}.b-blue{background:#e6f1fb;color:#1673B2}
    .btn{padding:6px 12px;border-radius:6px;font-size:.75rem;font-weight:600;cursor:pointer;border:1.5px solid;font-family:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:4px}
    .btn-p{background:#1673B2;color:#fff;border-color:#1673B2}
    .btn-g{background:#f0f4f8;color:#555;border-color:#dde4ed}
    .btn-r{background:#fff5f5;color:#c53030;border-color:#fca5a5}
    .form-row{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin-top:10px}
    .form-row input,.form-row select{padding:8px 10px;border:1.5px solid #dde4ed;border-radius:6px;font-size:.8rem;font-family:inherit;outline:none}
    .form-row input:focus{border-color:#1673B2}
    .me{background:#f0f9ff}
    @media(max-width:768px){.main{margin-left:0!important;padding:14px!important;padding-top:68px!important}}
  </style>
</head>
<body>
<?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>
<div class="main">
  <div class="page-title">🔐 Comptes administrateurs</div>

  <?php if ($msg): ?><div class="flash-ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="flash-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <!-- Liste des comptes -->
  <div class="card">
    <h3>Comptes actifs</h3>
    <table>
      <tr><th>Utilisateur</th><th>Email</th><th>Rôle</th><th>2FA</th><th>Dernière connexion</th><th>Actions</th></tr>
      <?php foreach ($users as $u): $is_me = ($u['id'] === $me_id); ?>
      <tr <?= $is_me?'class="me"':'' ?>>
        <td>
          <strong><?= htmlspecialchars($u['username']) ?></strong>
          <?php if ($is_me): ?><span class="badge b-blue">moi</span><?php endif; ?>
          <?php if (!$u['is_active']): ?><span class="badge b-off">inactif</span><?php endif; ?>
        </td>
        <td style="font-size:.75rem"><?= htmlspecialchars($u['email']) ?></td>
        <td><span class="badge <?= $u['role']==='superadmin'?'b-sa':'b-ok' ?>"><?= $u['role'] ?></span></td>
        <td>
          <?php if ($u['totp_enabled']): ?>
            <span class="badge b-ok">✅ Actif</span>
            <?php if ($u['totp_setup_at']): ?><div style="font-size:.65rem;color:#aaa"><?= date('d/m/Y',strtotime($u['totp_setup_at'])) ?></div><?php endif; ?>
          <?php else: ?>
            <span class="badge b-off">⚠ Non configuré</span>
          <?php endif; ?>
        </td>
        <td style="font-size:.72rem;color:#888">
          <?= $u['last_login'] ? date('d/m/Y H:i',strtotime($u['last_login'])) : 'Jamais' ?>
          <?php if ($u['last_login_ip']): ?><div style="color:#bbb"><?= htmlspecialchars($u['last_login_ip']) ?></div><?php endif; ?>
        </td>
        <td>
          <div style="display:flex;gap:5px;flex-wrap:wrap">
            <?php if ($u['totp_enabled'] && ($is_me || $me_role === 'superadmin')): ?>
            <form method="POST" style="display:inline"><?= csrf_field() ?>
              <input type="hidden" name="action" value="reset_totp">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <button class="btn btn-g" onclick="return confirm('Réinitialiser le 2FA de <?= htmlspecialchars($u['username']) ?> ?')">🔄 Reset 2FA</button>
            </form>
            <?php endif; ?>
            <?php if ($is_me && $u['totp_enabled']): ?>
            <form method="POST" style="display:inline"><?= csrf_field() ?>
              <input type="hidden" name="action" value="regen_backup">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <button class="btn btn-g" onclick="return confirm('Générer de nouveaux codes de secours ? Les anciens ne fonctionneront plus.')">🔑 Nouveaux codes</button>
            </form>
            <?php endif; ?>
            <?php if (!$is_me && $me_role === 'superadmin' && $u['role'] !== 'superadmin'): ?>
            <form method="POST" style="display:inline"><?= csrf_field() ?>
              <input type="hidden" name="action" value="<?= $u['is_active']?'deactivate':'activate' ?>">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <button class="btn <?= $u['is_active']?'btn-r':'btn-p' ?>" onclick="return confirm('<?= $u['is_active']?'Désactiver':'Réactiver' ?> ce compte ?')">
                <?= $u['is_active']?'Désactiver':'Réactiver' ?>
              </button>
            </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <!-- Changer mon mot de passe -->
  <div class="card">
    <h3>Changer mon mot de passe</h3>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="change_password">
      <input type="hidden" name="user_id" value="<?= $me_id ?>">
      <div class="form-row">
        <div><label style="font-size:.7rem;color:#888;display:block;margin-bottom:3px">Nouveau mot de passe (10 car. min)</label>
          <input type="password" name="new_password" minlength="10" required style="min-width:260px"></div>
        <button type="submit" class="btn btn-p" style="padding:9px 16px">Mettre à jour</button>
      </div>
    </form>
  </div>

  <?php if ($me_role === 'superadmin'): ?>
  <!-- Créer un nouveau compte -->
  <div class="card">
    <h3>➕ Créer un compte administrateur</h3>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <div class="form-row">
        <div><label style="font-size:.7rem;color:#888;display:block;margin-bottom:3px">Nom d'utilisateur</label>
          <input type="text" name="username" required pattern="[a-z0-9_]{3,30}" placeholder="prenom" style="width:140px"></div>
        <div><label style="font-size:.7rem;color:#888;display:block;margin-bottom:3px">Email</label>
          <input type="email" name="email" required style="width:220px"></div>
        <div><label style="font-size:.7rem;color:#888;display:block;margin-bottom:3px">Mot de passe (10 car. min)</label>
          <input type="password" name="password" required minlength="10" style="width:180px"></div>
        <div><label style="font-size:.7rem;color:#888;display:block;margin-bottom:3px">Rôle</label>
          <select name="role"><option value="admin">admin</option><option value="superadmin">superadmin</option></select></div>
        <button type="submit" class="btn btn-p" style="padding:9px 16px">Créer</button>
      </div>
      <p style="font-size:.72rem;color:#aaa;margin-top:8px">Le nouveau compte devra configurer son 2FA à la première connexion.</p>
    </form>
  </div>
  <?php endif; ?>

  <!-- Lien urgence -->
  <div style="text-align:center;margin-top:8px">
    <a href="emergency.php" style="font-size:.75rem;color:#aaa;text-decoration:none">🆘 Procédure d'urgence (accès perdu)</a>
  </div>
</div>
</body>
</html>
