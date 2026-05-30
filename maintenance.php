<?php
// maintenance.php — Page "en construction" avec bypass par code secret
require_once __DIR__ . '/config.php';

// Si le site n'est PAS en maintenance, rediriger vers l'accueil
$pdo = getDB();
$mode = $pdo->query("SELECT valeur FROM site_config WHERE cle='maintenance_mode'")->fetchColumn();
if (!$mode || $mode === '0') {
    header('Location: /'); exit;
}

$code_ok = $pdo->query("SELECT valeur FROM site_config WHERE cle='maintenance_code'")->fetchColumn();

// Traitement du formulaire de bypass
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['code'])) {
    if (trim($_POST['code']) === $code_ok) {
        setcookie('maintenance_bypass', $code_ok, time() + 86400 * 30, '/', '', true, true);
        header('Location: /'); exit;
    }
    $error = 'Code incorrect.';
}

// Bypass via URL (?bypass=CODE)
if (isset($_GET['bypass']) && $_GET['bypass'] === $code_ok) {
    setcookie('maintenance_bypass', $code_ok, time() + 86400 * 30, '/', '', true, true);
    header('Location: /'); exit;
}

$titre = $pdo->query("SELECT valeur FROM site_config WHERE cle='maintenance_titre'")->fetchColumn()
      ?: 'Site en maintenance';
$msg   = $pdo->query("SELECT valeur FROM site_config WHERE cle='maintenance_message'")->fetchColumn()
      ?: 'Nous travaillons à l\'amélioration du site. Revenez bientôt !';
?><!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($titre) ?></title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;
         background:linear-gradient(135deg,#0e3d6b 0%,#1673B2 100%);font-family:"Helvetica Neue",Arial,sans-serif;
         color:#fff;padding:24px;text-align:center}
    .logo{margin-bottom:32px;opacity:.9}
    .logo img{height:60px}
    .icon{font-size:4rem;margin-bottom:16px;animation:pulse 2s ease-in-out infinite}
    @keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.08)}}
    h1{font-size:1.8rem;font-weight:800;margin-bottom:12px}
    p{font-size:1rem;opacity:.85;max-width:480px;line-height:1.6;margin-bottom:32px}
    .bypass-form{background:rgba(255,255,255,.1);border-radius:10px;padding:20px 24px;
                 max-width:320px;width:100%;backdrop-filter:blur(6px)}
    .bypass-form p{font-size:.8rem;opacity:.7;margin-bottom:12px}
    .bypass-form input{width:100%;padding:10px 14px;border-radius:7px;border:none;
                       background:rgba(255,255,255,.15);color:#fff;font-size:.9rem;
                       outline:none;text-align:center;letter-spacing:.1em;margin-bottom:10px}
    .bypass-form input::placeholder{color:rgba(255,255,255,.5)}
    .bypass-form button{width:100%;padding:10px;border:none;border-radius:7px;
                        background:#FF9900;color:#fff;font-weight:700;font-size:.9rem;cursor:pointer}
    .bypass-form button:hover{background:#e08800}
    .error{color:#ffcccc;font-size:.82rem;margin-top:8px}
    .footer{margin-top:48px;font-size:.75rem;opacity:.5}
  </style>
</head>
<body>
  <div class="icon">🚧</div>
  <h1><?= htmlspecialchars($titre) ?></h1>
  <p><?= nl2br(htmlspecialchars($msg)) ?></p>

  <div class="bypass-form">
    <p>Accès réservé</p>
    <form method="post">
      <input type="password" name="code" placeholder="Code d'accès" autocomplete="off" required>
      <button type="submit">Accéder →</button>
      <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
    </form>
  </div>

  <div class="footer">Ça suffit ! ASBL — Piste 01</div>
</body>
</html>
