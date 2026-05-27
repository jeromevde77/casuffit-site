<?php
// admin/login.php — Connexion 2 étapes : mot de passe + TOTP
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/totp.php';
session_start();

if (isAdminLoggedIn()) { header('Location: dashboard.php'); exit; }

$db    = getDB();
$step  = $_GET['step'] ?? 'password';
$error = '';

if ($step === 'password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $pass     = $_POST['password'] ?? '';
    try { $count = (int)$db->query("SELECT COUNT(*) FROM admin_users WHERE is_active=1")->fetchColumn(); }
    catch (Exception $e) { $count = 0; }

    if ($count === 0) {
        if (defined('ADMIN_USER') && defined('ADMIN_PASS') && $username === ADMIN_USER && password_verify($pass, ADMIN_PASS)) {
            $_SESSION['admin_logged_in'] = true; $_SESSION['admin_user'] = $username; $_SESSION['admin_last_activity'] = time();
            session_regenerate_id(true); header('Location: install_admin.php'); exit;
        }
        $error = 'Identifiants incorrects.';
    } else {
        $stmt = $db->prepare("SELECT * FROM admin_users WHERE username=? AND is_active=1");
        $stmt->execute([$username]); $admin = $stmt->fetch();
        if (!$admin) { sleep(1); $error = 'Identifiants incorrects.'; }
        elseif ($admin['locked_until'] && strtotime($admin['locked_until']) > time()) {
            $wait = ceil((strtotime($admin['locked_until']) - time()) / 60);
            $error = "Compte bloqué. Réessaye dans $wait minute(s).";
        } elseif (!password_verify($pass, $admin['password_hash'])) {
            $att  = $admin['failed_attempts'] + 1;
            $lock = $att >= 5 ? date('Y-m-d H:i:s', time() + 600) : null;
            $db->prepare("UPDATE admin_users SET failed_attempts=?, locked_until=? WHERE id=?")->execute([$att, $lock, $admin['id']]);
            $error = $lock ? 'Trop de tentatives. Compte bloqué 10 minutes.' : 'Mot de passe incorrect. ('.max(0,5-$att).' tentative(s) restante(s))';
        } else {
            $db->prepare("UPDATE admin_users SET failed_attempts=0, locked_until=NULL WHERE id=?")->execute([$admin['id']]);
            $_SESSION['admin_2fa_pending'] = $admin['id']; $_SESSION['admin_2fa_user'] = $admin['username'];
            session_regenerate_id(true);
            if (!$admin['totp_enabled']) {
                $_SESSION['admin_logged_in'] = true; $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_role'] = $admin['role']; $_SESSION['admin_last_activity'] = time();
                unset($_SESSION['admin_2fa_pending'], $_SESSION['admin_2fa_user']);
                $db->prepare("UPDATE admin_users SET last_login=NOW(), last_login_ip=? WHERE id=?")->execute([$_SERVER['REMOTE_ADDR']??'', $admin['id']]);
                session_write_close();
                header('Location: setup_totp.php?first=1'); exit;
            }
            header('Location: login.php?step=totp'); exit;
        }
    }
}

if ($step === 'totp' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pid = $_SESSION['admin_2fa_pending'] ?? null;
    if (!$pid) { header('Location: login.php'); exit; }
    $stmt = $db->prepare("SELECT * FROM admin_users WHERE id=? AND is_active=1");
    $stmt->execute([$pid]); $admin = $stmt->fetch();
    if (!$admin) { session_destroy(); header('Location: login.php'); exit; }
    $code = preg_replace('/\s/', '', $_POST['code'] ?? '');
    $verified = false; $used_backup = -1;
    if (strlen($code) > 6) {
        $hashes = json_decode($admin['totp_backup_codes'] ?? '[]', true) ?: [];
        $idx = TOTP::verifyBackupCode($code, $hashes);
        if ($idx >= 0) { array_splice($hashes, $idx, 1); $db->prepare("UPDATE admin_users SET totp_backup_codes=? WHERE id=?")->execute([json_encode($hashes), $admin['id']]); $verified = true; $used_backup = count($hashes); }
    } else { $verified = TOTP::verify($admin['totp_secret'], $code); }
    if (!$verified) { $error = 'Code incorrect. Réessaye ou utilise un code de secours.'; }
    else {
        $_SESSION['admin_logged_in'] = true; $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_role'] = $admin['role']; $_SESSION['admin_last_activity'] = time();
        unset($_SESSION['admin_2fa_pending'], $_SESSION['admin_2fa_user']);
        session_regenerate_id(true);
        $db->prepare("UPDATE admin_users SET last_login=NOW(), last_login_ip=? WHERE id=?")->execute([$_SERVER['REMOTE_ADDR']??'', $admin['id']]);
        try { require_once __DIR__ . '/../includes/mail_helper.php';
          $ip=$_SERVER['REMOTE_ADDR']??'?'; $now=date('d/m/Y H:i:s');
          $warn=$used_backup>=0?"<p style='color:#b45309'>⚠ Code de secours utilisé. Restants : $used_backup</p>":'';
          sendMail($admin['email'], '🔐 Connexion admin Ça suffit !', "<p>Connexion de <strong>{$admin['username']}</strong> le $now depuis IP $ip.</p>$warn<p>Si ce n'est pas toi, change ton mot de passe immédiatement.</p>");
        } catch (Exception $e) {}
        session_write_close(); // Force l'écriture session avant le redirect
        if ($used_backup >= 0 && $used_backup <= 2) { header('Location: backup_codes.php?warn=1'); exit; }
        header('Location: dashboard.php'); exit;
    }
}
if ($step === 'totp' && empty($_SESSION['admin_2fa_pending'])) { header('Location: login.php'); exit; }
?><!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<?php include __DIR__ . '/../includes/admin_pwa_head.php'; ?>
<title>Admin — Connexion</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Helvetica Neue",Arial,sans-serif;background:linear-gradient(135deg,#0e3d6b,#1673B2);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:#fff;border-radius:16px;box-shadow:0 8px 40px rgba(0,0,0,.25);padding:44px 40px;width:100%;max-width:400px}
.brand{text-align:center;margin-bottom:28px}
.brand h1{font-size:1.4rem;font-weight:800;color:#0e3d6b}.brand h1 span{color:#FF9900}
.brand p{font-size:.73rem;color:#aaa;margin-top:3px;text-transform:uppercase;letter-spacing:.07em}
.step-badge{display:inline-flex;align-items:center;gap:8px;background:#f0f4f8;border-radius:20px;padding:5px 14px;font-size:.75rem;color:#555;font-weight:600;margin-bottom:20px}
.step-dot{width:8px;height:8px;border-radius:50%;background:#ddd}.step-dot.active{background:#1673B2}
label{display:block;font-size:.8rem;font-weight:600;color:#555;margin-bottom:5px}
input{width:100%;padding:12px 13px;border:1.5px solid #dde4ed;border-radius:8px;font-size:.95rem;outline:none;margin-bottom:16px;font-family:inherit}
input:focus{border-color:#1673B2}
input.code-input{font-size:1.5rem;letter-spacing:.35em;text-align:center;font-weight:700}
button{width:100%;background:#1673B2;color:#fff;border:none;padding:13px;border-radius:8px;font-size:1rem;font-weight:700;cursor:pointer;font-family:inherit}
button:hover{background:#125a90}
.error{background:#fde8e8;color:#c0392b;padding:11px 13px;border-radius:8px;font-size:.83rem;margin-bottom:18px;border-left:3px solid #c0392b}
.back{text-align:center;margin-top:14px}.back a{color:#1673B2;font-size:.78rem;text-decoration:none}
.hint{background:#f0f9ff;border:1px solid #bdd5f5;border-radius:8px;padding:11px 13px;font-size:.78rem;color:#1673B2;margin-bottom:16px;line-height:1.6}
</style></head><body>
<div class="card">
  <div class="brand"><h1>Ça suffit <span>!</span></h1><p>Administration</p></div>
<?php if ($step === 'password'): ?>
  <div style="text-align:center;margin-bottom:20px">
    <div class="step-badge"><span class="step-dot active"></span> Étape 1/2 — Identifiants <span class="step-dot"></span></div>
  </div>
  <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="POST" autocomplete="off">
    <label>Nom d'utilisateur</label><input type="text" name="username" required autocomplete="username" autofocus>
    <label>Mot de passe</label><input type="password" name="password" required autocomplete="current-password">
    <button type="submit">Continuer →</button>
  </form>
<?php else: ?>
  <div style="text-align:center;margin-bottom:20px">
    <div class="step-badge"><span class="step-dot"></span> Étape 2/2 — Authentification <span class="step-dot active"></span></div>
  </div>
  <div class="hint">🔐 Code à 6 chiffres dans ton app pour <strong><?= htmlspecialchars($_SESSION['admin_2fa_user'] ?? '?') ?></strong>.<br>
  <em>Code de secours : entre le code complet (ex: A1B2-C3D4-E5F6)</em></div>
  <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="POST" autocomplete="off">
    <input type="text" name="code" class="code-input" placeholder="000000" maxlength="20" autocomplete="one-time-code" inputmode="numeric" autofocus required>
    <button type="submit">Valider</button>
  </form>
  <div class="back"><a href="login.php">← Changer de compte</a></div>
<?php endif; ?>
  <div class="back"><a href="<?= SITE_URL ?>">← Retour au site</a></div>
</div></body></html>
