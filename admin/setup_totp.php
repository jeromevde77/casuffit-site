<?php
// admin/setup_totp.php — Configuration 2FA TOTP
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/totp.php';
session_start(); requireAdmin();
$db    = getDB();
$first = !empty($_GET['first']); // premier setup post-connexion
$admin_id = $_SESSION['admin_id'];

$stmt = $db->prepare("SELECT * FROM admin_users WHERE id=?");
$stmt->execute([$admin_id]); $admin = $stmt->fetch();

$step  = $_SESSION['totp_setup_step'] ?? 'generate';
$error = '';

// ── Générer un nouveau secret ──────────────────────────────────────────
if ($step === 'generate' || isset($_POST['regenerate'])) {
    $secret = TOTP::generateSecret();
    $_SESSION['totp_pending_secret'] = $secret;
    $_SESSION['totp_setup_step']     = 'verify';
    $step = 'verify';
}

$secret = $_SESSION['totp_pending_secret'] ?? TOTP::generateSecret();
$uri    = TOTP::getUri($secret, $admin['username']);

// ── Vérifier le premier code ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_code'])) {
    $code = preg_replace('/\D/', '', $_POST['code'] ?? '');
    if (!TOTP::verify($secret, $code)) {
        $error = 'Code incorrect. Vérifie que l\'heure de ton téléphone est synchronisée et réessaye.';
    } else {
        // Générer les codes de secours
        $plain_codes  = TOTP::generateBackupCodes(8);
        $hashed_codes = array_map([TOTP::class, 'hashBackupCode'], $plain_codes);
        $db->prepare("UPDATE admin_users SET totp_secret=?, totp_enabled=1, totp_backup_codes=?, totp_setup_at=NOW() WHERE id=?")
           ->execute([$secret, json_encode($hashed_codes), $admin_id]);
        unset($_SESSION['admin_2fa_required']);
        $_SESSION['totp_plain_codes']  = $plain_codes;
        $_SESSION['totp_setup_step']   = 'backup';
        unset($_SESSION['totp_pending_secret']);
        header('Location: setup_totp.php'); exit;
    }
}

// ── Confirmer que les codes ont été sauvegardés ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_saved'])) {
    unset($_SESSION['totp_setup_step'], $_SESSION['totp_plain_codes']);
    header('Location: dashboard.php?msg=totp_ok'); exit;
}

$step = $_SESSION['totp_setup_step'] ?? 'verify';
$plain_codes = $_SESSION['totp_plain_codes'] ?? [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <?php include __DIR__ . '/../includes/admin_pwa_head.php'; ?>
  <title>Configuration 2FA — Admin</title>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
    .card{background:#fff;border-radius:16px;box-shadow:0 8px 40px rgba(0,0,0,.12);padding:44px 40px;width:100%;max-width:500px}
    h2{font-size:1.25rem;font-weight:800;color:#0e3d6b;margin-bottom:6px}
    .sub{font-size:.82rem;color:#888;margin-bottom:28px;line-height:1.5}
    .steps{display:flex;gap:0;margin-bottom:28px}
    .step-item{flex:1;text-align:center;font-size:.72rem;font-weight:600;color:#bbb;padding-bottom:8px;border-bottom:3px solid #eee;transition:all .2s}
    .step-item.active{color:#1673B2;border-bottom-color:#1673B2}
    .step-item.done{color:#27ae60;border-bottom-color:#27ae60}
    label{display:block;font-size:.8rem;font-weight:600;color:#555;margin-bottom:5px}
    input{width:100%;padding:12px 13px;border:1.5px solid #dde4ed;border-radius:8px;font-size:1.5rem;letter-spacing:.35em;text-align:center;font-weight:700;outline:none;margin-bottom:16px;font-family:inherit}
    input:focus{border-color:#1673B2}
    button{width:100%;background:#1673B2;color:#fff;border:none;padding:13px;border-radius:8px;font-size:1rem;font-weight:700;cursor:pointer;font-family:inherit;margin-bottom:8px}
    button:hover{background:#125a90}
    button.btn-g{background:#f0f4f8;color:#555;border:1.5px solid #dde4ed}
    button.btn-g:hover{background:#e0e8f0;color:#333}
    .error{background:#fde8e8;color:#c0392b;padding:11px 13px;border-radius:8px;font-size:.83rem;margin-bottom:18px;border-left:3px solid #c0392b}
    .warn-box{background:#fff8ee;border:1.5px solid #FF9900;border-radius:10px;padding:16px;margin-bottom:20px;font-size:.82rem;line-height:1.7;color:#7a4400}
    /* QR Code */
    #qrcode{display:flex;justify-content:center;margin:18px 0}
    #qrcode canvas,#qrcode img{border:6px solid #fff;box-shadow:0 2px 12px rgba(0,0,0,.1);border-radius:8px}
    .secret-box{background:#f0f4f8;border-radius:8px;padding:10px 14px;font-family:monospace;font-size:.85rem;font-weight:700;color:#0e3d6b;text-align:center;letter-spacing:.15em;margin-bottom:16px;word-break:break-all}
    /* Backup codes */
    .backup-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin:16px 0}
    .backup-code{background:#f0f4f8;border-radius:8px;padding:11px 10px;font-family:monospace;font-size:1rem;font-weight:700;color:#0e3d6b;text-align:center;letter-spacing:.12em;border:1.5px solid #dde4ed}
    .print-section{border:2px dashed #1673B2;border-radius:10px;padding:20px;margin:16px 0}
    .print-title{font-size:.85rem;font-weight:700;color:#0e3d6b;margin-bottom:12px;text-align:center}
    @media print{
      body{background:#fff!important;display:block;padding:0}
      .card{box-shadow:none;padding:20px}
      .no-print{display:none!important}
      .print-section{border:2px solid #333}
      .backup-code{border:1px solid #999;break-inside:avoid}
    }
  </style>
</head>
<body>
<div class="card">
  <?php if ($first): ?>
  <div class="warn-box">
    🔐 <strong>Configuration obligatoire</strong><br>
    La double authentification est requise pour les comptes admin. Configure-la maintenant pour accéder au panneau d'administration.
  </div>
  <?php endif; ?>

  <h2>Configuration 2FA</h2>
  <p class="sub">Authentification par application (TOTP) — Google Authenticator, Authy, 1Password…</p>

  <div class="steps">
    <div class="step-item <?= $step==='verify'?'active':($step==='backup'?'done':'') ?>">1. Scanner</div>
    <div class="step-item <?= $step==='verify'?'active':($step==='backup'?'done':'') ?>">2. Vérifier</div>
    <div class="step-item <?= $step==='backup'?'active':'' ?>">3. Codes secours</div>
  </div>

  <?php if ($step === 'verify'): ?>
  <!-- ÉTAPE 1+2 : Scanner et vérifier -->
  <p style="font-size:.82rem;color:#555;margin-bottom:12px;line-height:1.6">
    <strong>1.</strong> Ouvre ton app d'authentification<br>
    <strong>2.</strong> Scanne le QR code ou entre la clé manuellement<br>
    <strong>3.</strong> Entre le code à 6 chiffres affiché dans l'app
  </p>

  <div id="qrcode"></div>
  <div class="secret-box"><?= wordwrap($secret, 4, ' ', true) ?></div>

  <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="POST">
    <label>Code de vérification (6 chiffres)</label>
    <input type="text" name="code" placeholder="000000" maxlength="6" autocomplete="one-time-code" inputmode="numeric" autofocus required>
    <button type="submit" name="verify_code">Vérifier et activer →</button>
    <button type="submit" name="regenerate" class="btn-g">↺ Générer un nouveau code QR</button>
  </form>

  <script>
    new QRCode(document.getElementById('qrcode'), {
      text: <?= json_encode($uri) ?>,
      width: 200, height: 200,
      colorDark: '#0e3d6b', colorLight: '#ffffff',
      correctLevel: QRCode.CorrectLevel.M
    });
  </script>

  <?php else: /* ÉTAPE 3 : Codes de secours */ ?>
  <div class="warn-box">
    ⚠ <strong>Imprime ou note ces 8 codes de secours maintenant.</strong><br>
    Chaque code ne peut être utilisé <strong>qu'une seule fois</strong>. Si tu perds ton téléphone, ils te permettront de te connecter sans l'app. Garde-les dans un endroit sûr (coffre, gestionnaire de mots de passe).
  </div>

  <div class="print-section">
    <div class="print-title">🔑 Codes de secours — Ça suffit ! Admin — <?= htmlspecialchars($admin['username']) ?></div>
    <div class="backup-grid">
      <?php foreach ($plain_codes as $code): ?>
      <div class="backup-code"><?= htmlspecialchars($code) ?></div>
      <?php endforeach; ?>
    </div>
    <p style="font-size:.7rem;color:#aaa;text-align:center;margin-top:8px">Générés le <?= date('d/m/Y à H:i') ?> · Usage unique · Garder secret</p>
  </div>

  <button onclick="window.print()" class="btn-g no-print" style="margin-bottom:12px">🖨 Imprimer cette page</button>

  <form method="POST">
    <button type="submit" name="confirm_saved">✅ J'ai sauvegardé mes codes — Accéder à l'admin</button>
  </form>
  <?php endif; ?>
</div>
</body>
</html>
