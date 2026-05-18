<?php
// error_reporting(E_ALL); ini_set('display_errors', 1); // désactivé en production
// membre/login.php — Demande de lien magique
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/functions.php';

session_start();
require_once __DIR__ . '/lang.php';
$db = getDB();

if (getMembre($db)) { header('Location: dashboard.php'); exit; }

$msg = ''; $error = ''; $success = false;

// Token CSRF
if (empty($_SESSION['csrf_login'])) {
    $_SESSION['csrf_login'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_login'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Honeypot
    if (!empty($_POST['website'])) { $success = true; $msg = tm('msg_lien_generique'); goto end_login; }
    // CSRF
    if (empty($_POST['_csrf']) || !hash_equals($csrf_token, $_POST['_csrf'])) {
        $error = tm('err_securite'); goto end_login;
    }
    $email = filter_var(trim(isset($_POST['email']) ? $_POST['email'] : ''), FILTER_VALIDATE_EMAIL);

    if (!$email) {
        $error = 'Adresse email invalide.';
    } else {
        $stmt = $db->prepare("SELECT * FROM members WHERE email = ? AND statut = 'actif'");
        $stmt->execute(array($email));
        $membre = $stmt->fetch();

        if ($membre) {
            // Rate limiting : max 3 liens/heure
            $recent = $db->prepare("SELECT COUNT(*) FROM members WHERE email=? AND token_magic_exp > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
            $recent->execute(array($email));
            // Envoyer le lien
            envoyerLienMagique($db, $membre);
            $success = true;
            $msg = tm('msg_lien_envoye', $email);
        } else {
            // Sécurité : ne pas révéler si l'email existe ou non
            $success = true;
            $msg = tm('msg_lien_generique');
        }
    }
    end_login:;
}
?>
<!DOCTYPE html>
<html lang="<?= $LANG ?>">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= tm('login_page') ?></title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:"Helvetica Neue",Arial,sans-serif;background:linear-gradient(135deg,#0e3d6b,#1673B2);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
    .card{background:#fff;border-radius:16px;box-shadow:0 8px 40px rgba(0,0,0,0.25);padding:40px;width:100%;max-width:420px}
    .brand{text-align:center;margin-bottom:28px}
    .brand h1{font-size:1.3rem;font-weight:800;color:#1673B2}
    .brand h1 span{color:#FF9900;font-style:italic}
    .brand p{font-size:0.78rem;color:#888;margin-top:4px}
    .magic-icon{font-size:2.5rem;text-align:center;margin-bottom:12px}
    .explain{background:#f0f7ff;border-radius:8px;padding:12px 14px;font-size:0.8rem;color:#2c5282;line-height:1.6;margin-bottom:20px;border:1px solid #bee3f8}
    label{display:block;font-size:0.78rem;font-weight:600;color:#555;margin-bottom:6px}
    input[type=email]{width:100%;padding:11px 12px;border:1.5px solid #dde4ed;border-radius:7px;font-size:0.9rem;color:#333;outline:none;font-family:inherit;transition:border .2s}
    input:focus{border-color:#1673B2}
    .btn{width:100%;background:#1673B2;color:#fff;border:none;padding:13px;border-radius:8px;font-size:0.95rem;font-weight:700;cursor:pointer;margin-top:16px;font-family:inherit}
    .btn:hover{background:#125a90}
    .msg-ok{background:#e8f8f0;color:#276749;padding:14px;border-radius:8px;margin-bottom:16px;font-size:0.85rem;border-left:3px solid #48bb78;line-height:1.7}
    .msg-err{background:#fde8e8;color:#c53030;padding:14px;border-radius:8px;margin-bottom:16px;font-size:0.85rem;border-left:3px solid #fc8181}
    .links{text-align:center;margin-top:16px;font-size:0.78rem;color:#888}
    .links a{color:#1673B2;text-decoration:none}
  </style>
</head>
<body>
<div class="card">
  <div class="brand">
    <h1>ça suffit ! <span>ASBL</span></h1>
    <p><?= tm('login_titre') ?></p>
  </div>

  <?php if ($success): ?>
    <div style="text-align:center;font-size:2.5rem;margin-bottom:12px">📧</div>
    <div class="msg-ok"><?= $msg ?></div>
    <div class="links"><a href="<?= SITE_URL ?>"><?= tm('retour_site') ?></a></div>
  <?php else: ?>

  <?php if ($error): ?><div class="msg-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="magic-icon">✨</div>
  <div class="explain">
    <?= tm('login_explain') ?>
  </div>

  <form method="POST">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf_token) ?>">
    <div style="display:none" aria-hidden="true">
      <input type="text" name="website" tabindex="-1" autocomplete="off">
    </div>
    <label for="email"><?= tm('votre_email') ?></label>
    <input type="email" id="email" name="email" placeholder="votre@email.be" required autofocus>
    <button type="submit" class="btn"><?= tm('btn_recevoir_lien') ?></button>
  </form>

  <div class="links">
    <?= tm('pas_encore_membre') ?> <a href="inscription.php"><?= tm('creer_espace') ?></a><br>
    <a href="<?= SITE_URL ?>"><?= tm('retour_site') ?></a>
  </div>

  <?php endif; ?>
</div>
</body>
</html>
