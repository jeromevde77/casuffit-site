<?php
// admin/login.php
require_once __DIR__ . '/../config.php';
session_start();

if (isAdminLoggedIn()) {
    header('Location: dashboard.php'); exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim(isset($_POST['username']) ? $_POST['username'] : '');
    $pass = trim(isset($_POST['password']) ? $_POST['password'] : '');

    // Anti-brute force
    if (!isset($_SESSION['login_attempts'])) $_SESSION['login_attempts'] = 0;
    if (!isset($_SESSION['login_lockout'])) $_SESSION['login_lockout'] = 0;

    if (time() < $_SESSION['login_lockout']) {
        $wait = ceil(($_SESSION['login_lockout'] - time()) / 60);
        $error = "Trop de tentatives. Réessayez dans $wait minute(s).";
    } elseif ($user === ADMIN_USER && password_verify($pass, ADMIN_PASS)) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user']      = $user;
        $_SESSION['login_attempts']  = 0;
        session_regenerate_id(true);
        header('Location: dashboard.php'); exit;
    } else {
        $_SESSION['login_attempts']++;
        if ($_SESSION['login_attempts'] >= 5) {
            $_SESSION['login_lockout'] = time() + 900; // 15 min
            $error = 'Compte bloqué 15 minutes après trop de tentatives.';
        } else {
            $remaining = 5 - $_SESSION['login_attempts'];
            $error = "Identifiants incorrects. ($remaining tentative(s) restante(s))";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
<?php include __DIR__ . '/../includes/admin_pwa_head.php'; ?>
  <title>Admin — Piste 01 Ça suffit !</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: "Helvetica Neue", Arial, sans-serif; background: linear-gradient(135deg, #0e3d6b, #1673B2); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
    .card { background: #fff; border-radius: 16px; box-shadow: 0 8px 40px rgba(0,0,0,0.25); padding: 48px 40px; width: 100%; max-width: 400px; }
    .brand { text-align: center; margin-bottom: 32px; }
    .brand h1 { font-size: 1.4rem; font-weight: 800; color: #1673B2; }
    .brand h1 span { color: #FF9900; font-style: italic; }
    .brand p { font-size: 0.75rem; color: #888; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.08em; }
    label { display: block; font-size: 0.8rem; font-weight: 600; color: #555; margin-bottom: 6px; }
    input[type=text], input[type=password] { width: 100%; padding: 12px 14px; border: 1.5px solid #dde4ed; border-radius: 8px; font-size: 0.95rem; color: #333; transition: border 0.2s; outline: none; margin-bottom: 18px; }
    input:focus { border-color: #1673B2; }
    button { width: 100%; background: #1673B2; color: #fff; border: none; padding: 14px; border-radius: 8px; font-size: 1rem; font-weight: 700; cursor: pointer; transition: background 0.2s; }
    button:hover { background: #125a90; }
    .error { background: #fde8e8; color: #c0392b; padding: 12px 14px; border-radius: 8px; font-size: 0.85rem; margin-bottom: 20px; border-left: 3px solid #c0392b; }
    .back { text-align: center; margin-top: 20px; }
    .back a { color: #1673B2; font-size: 0.8rem; text-decoration: none; }
    .back a:hover { text-decoration: underline; }
  </style>
</head>
<body>
<div class="card">
  <div class="brand">
    <h1>Ça suffit ! <span>ASBL</span></h1>
    <p>Piste 01 · UBCNA — Administration</p>
  </div>
  <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="POST" autocomplete="off">
    <label for="username">Identifiant</label>
    <input type="text" id="username" name="username" required autocomplete="username">
    <label for="password">Mot de passe</label>
    <input type="password" id="password" name="password" required autocomplete="current-password">
    <button type="submit">Se connecter</button>
  </form>
  <div class="back"><a href="<?= SITE_URL ?>">← Retour au site</a></div>
</div>
</body>
</html>
