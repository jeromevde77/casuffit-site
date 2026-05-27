<?php
// admin/backup_codes.php — Affichage et impression des codes de secours
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/totp.php';
session_start(); requireAdmin();
$db       = getDB();
$me_id    = $_SESSION['admin_id'];
$admin    = $db->prepare("SELECT username,totp_backup_codes FROM admin_users WHERE id=?")->execute([$me_id]) ? null : null;
$s        = $db->prepare("SELECT username,totp_backup_codes FROM admin_users WHERE id=?"); $s->execute([$me_id]); $admin = $s->fetch();
$codes    = $_SESSION['new_backup_codes'] ?? null; // vient de regen
unset($_SESSION['new_backup_codes']);
$count    = count(json_decode($admin['totp_backup_codes'] ?? '[]', true) ?: []);
$warn     = !empty($_GET['warn']);
$regen    = !empty($_GET['regen']);
?>
<!DOCTYPE html>
<html lang="fr"><head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Codes de secours — Admin</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
    .card{background:#fff;border-radius:14px;box-shadow:0 4px 24px rgba(0,0,0,.12);padding:36px;max-width:480px;width:100%}
    h2{font-size:1.2rem;font-weight:800;color:#0e3d6b;margin-bottom:6px}
    .warn-box{background:#fff8ee;border:1.5px solid #FF9900;border-radius:8px;padding:13px;font-size:.82rem;color:#7a4400;margin-bottom:18px;line-height:1.7}
    .print-frame{border:2px dashed #1673B2;border-radius:10px;padding:20px;margin:16px 0}
    .pf-title{font-size:.85rem;font-weight:700;color:#0e3d6b;margin-bottom:12px;text-align:center}
    .codes-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px}
    .code-chip{background:#f0f4f8;border:1.5px solid #dde4ed;border-radius:7px;padding:10px 8px;font-family:monospace;font-size:.95rem;font-weight:700;color:#0e3d6b;text-align:center;letter-spacing:.08em}
    .pf-meta{font-size:.68rem;color:#aaa;text-align:center}
    button{padding:11px 20px;border-radius:8px;font-size:.9rem;font-weight:700;cursor:pointer;font-family:inherit;border:none;width:100%;margin-bottom:8px}
    .btn-p{background:#1673B2;color:#fff}.btn-g{background:#f0f4f8;color:#555;border:1.5px solid #dde4ed}
    .count-badge{display:inline-block;padding:3px 10px;border-radius:10px;font-size:.75rem;font-weight:700}
    .count-ok{background:#e8f8f0;color:#27ae60}.count-warn{background:#fff8ee;color:#b45309}.count-bad{background:#fde8e8;color:#c0392b}
    @media print{body{background:#fff;display:block;padding:0}.card{box-shadow:none;padding:10px}.no-print{display:none!important}.print-frame{border:2px solid #333}}
  </style>
</head>
<body>
<div class="card">
  <h2>🔑 Codes de secours</h2>

  <?php if ($warn): ?>
  <div class="warn-box">⚠ <strong>Attention !</strong> Tu as utilisé un code de secours pour te connecter.<br>Il te reste <strong><?= $count ?></strong> code(s). Génère-en de nouveaux si tu es en dessous de 3.</div>
  <?php elseif ($regen): ?>
  <div class="warn-box" style="border-color:#27ae60;background:#e8f8f0;color:#276749">✅ Nouveaux codes générés. Les anciens sont désormais invalides.</div>
  <?php endif; ?>

  <p style="font-size:.82rem;color:#555;margin-bottom:14px;line-height:1.6">
    Codes restants :
    <span class="count-badge <?= $count>4?'count-ok':($count>1?'count-warn':'count-bad') ?>"><?= $count ?> / 8</span>
  </p>

  <?php if ($codes): ?>
  <div class="print-frame">
    <div class="pf-title">🔐 Codes de secours — <?= htmlspecialchars($admin['username']) ?></div>
    <div class="codes-grid">
      <?php foreach ($codes as $c): ?>
      <div class="code-chip"><?= htmlspecialchars($c) ?></div>
      <?php endforeach; ?>
    </div>
    <p class="pf-meta">Générés le <?= date('d/m/Y H:i') ?> · Usage unique · Conserver en lieu sûr</p>
  </div>
  <button class="btn-g no-print" onclick="window.print()">🖨 Imprimer</button>
  <?php else: ?>
  <p style="font-size:.8rem;color:#aaa;margin-bottom:16px">Les codes de secours actuels ne sont pas affichés pour des raisons de sécurité. Tu peux en générer de nouveaux si nécessaire.</p>
  <?php endif; ?>

  <a href="admin_users.php" class="btn-g no-print" style="display:block;text-align:center;padding:11px;border-radius:8px;text-decoration:none;font-size:.9rem;font-weight:700;color:#555;background:#f0f4f8;border:1.5px solid #dde4ed">← Retour</a>
</div>
</body>
</html>
