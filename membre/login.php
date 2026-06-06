<?php
// v4 — logo + retrait "ASBL" sur la page de connexion
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
    // ── Rate limiting : max 10 tentatives par IP par 15 minutes ──────────
    $ip_rl  = 'login_rl_' . md5($_SERVER['REMOTE_ADDR'] ?? '');
    $now_rl = time();
    $_SESSION[$ip_rl] = array_filter($_SESSION[$ip_rl] ?? [], fn($t) => $now_rl - $t < 900);
    if (count($_SESSION[$ip_rl]) >= 10) {
        $error = 'Trop de tentatives. Réessayez dans 15 minutes.'; goto end_login;
    }
    $_SESSION[$ip_rl][] = $now_rl;
    $mode  = (($_POST['mode'] ?? 'magic') === 'password') ? 'password' : 'magic';
    $email = filter_var(trim(isset($_POST['email']) ? $_POST['email'] : ''), FILTER_VALIDATE_EMAIL);

    if ($mode === 'password') {
        // ── Connexion par mot de passe ──────────────────────────────────
        $password   = (string)($_POST['password'] ?? '');
        $membre_pwd = null;
        if ($email && $password !== '') {
            $stmt = $db->prepare("SELECT * FROM members WHERE email = ? AND statut = 'actif'");
            $stmt->execute(array($email));
            $membre_pwd = $stmt->fetch();
        }
        if ($membre_pwd && !empty($membre_pwd['password_hash'])
            && password_verify($password, $membre_pwd['password_hash'])) {
            // Connexion réussie
            session_regenerate_id(true);
            $_SESSION['membre_id']    = $membre_pwd['id'];
            $_SESSION['membre_email'] = $membre_pwd['email'];
            $db->prepare("UPDATE members SET derniere_connexion=NOW() WHERE id=?")
               ->execute(array($membre_pwd['id']));
            header('Location: dashboard.php'); exit;
        }
        // Échec : message générique (ne révèle pas si l'email existe ou a un mdp)
        $error = tm('err_login_pass');

    } else {
        // ── Demande de lien magique (comportement existant) ─────────────
        if (!$email) {
            $error = 'Adresse email invalide.';
        } else {
            $stmt = $db->prepare("SELECT * FROM members WHERE email = ? AND statut = 'actif'");
            $stmt->execute(array($email));
            $membre = $stmt->fetch();

            if ($membre) {
                envoyerLienMagique($db, $membre);
                $success = true;
                $msg = tm('msg_lien_envoye', $email);
            } else {
                // Sécurité : ne pas révéler si l'email existe ou non
                $success = true;
                $msg = tm('msg_lien_generique');
            }
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
    .brand-logo{width:64px;height:64px;border-radius:50%;display:block;margin:0 auto 10px;background:#fff;object-fit:cover}
    .magic-icon{font-size:2.5rem;text-align:center;margin-bottom:12px}
    .explain{background:#f0f7ff;border-radius:8px;padding:12px 14px;font-size:0.8rem;color:#2c5282;line-height:1.6;margin-bottom:20px;border:1px solid #bee3f8}
    label{display:block;font-size:0.78rem;font-weight:600;color:#555;margin-bottom:6px}
    input[type=email],input[type=password]{width:100%;padding:11px 12px;border:1.5px solid #dde4ed;border-radius:7px;font-size:0.9rem;color:#333;outline:none;font-family:inherit;transition:border .2s}
    input:focus{border-color:#1673B2}
    .btn{width:100%;background:#1673B2;color:#fff;border:none;padding:13px;border-radius:8px;font-size:0.95rem;font-weight:700;cursor:pointer;margin-top:16px;font-family:inherit}
    .btn:hover{background:#125a90}
    .msg-ok{background:#e8f8f0;color:#276749;padding:14px;border-radius:8px;margin-bottom:16px;font-size:0.85rem;border-left:3px solid #48bb78;line-height:1.7}
    .msg-err{background:#fde8e8;color:#c53030;padding:14px;border-radius:8px;margin-bottom:16px;font-size:0.85rem;border-left:3px solid #fc8181}
    .links{text-align:center;margin-top:16px;font-size:0.78rem;color:#888}
    .links a{color:#1673B2;text-decoration:none}
    .auth-tabs{display:flex;gap:6px;margin-bottom:18px;background:#f0f4f9;border-radius:10px;padding:4px}
    .auth-tab{flex:1;padding:9px;border:none;background:none;border-radius:7px;font-size:0.82rem;font-weight:700;color:#888;cursor:pointer;font-family:inherit;transition:all .15s}
    .auth-tab.active{background:#fff;color:#1673B2;box-shadow:0 1px 3px rgba(0,0,0,.12)}
    .auth-panel{display:none}
    .auth-panel.active{display:block}
  </style>
</head>
<body>
<div class="card">
  <div class="brand">
    <img src="../assets/img/logo.png" alt="Ça suffit !" class="brand-logo">
    <h1>Ça suffit !</h1>
    <p><?= tm('login_titre') ?></p>
  </div>

  <?php if ($success): ?>
    <div style="text-align:center;font-size:2.5rem;margin-bottom:12px">📧</div>
    <div class="msg-ok"><?= $msg ?></div>
    <div class="links"><a href="<?= SITE_URL ?>"><?= tm('retour_site') ?></a></div>
  <?php else: ?>

  <?php if ($error): ?><div class="msg-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <?php $active_auth = (($mode ?? '') === 'magic') ? 'lien' : 'pass'; ?>

  <div class="auth-tabs">
    <button type="button" class="auth-tab <?= $active_auth==='pass'?'active':'' ?>" id="atab-pass" onclick="showAuth('pass')"><?= tm('login_tab_pass') ?></button>
    <button type="button" class="auth-tab <?= $active_auth==='lien'?'active':'' ?>" id="atab-lien" onclick="showAuth('lien')"><?= tm('login_tab_lien') ?></button>
  </div>

  <!-- Connexion par mot de passe -->
  <div class="auth-panel <?= $active_auth==='pass'?'active':'' ?>" id="apanel-pass">
    <div class="explain"><?= tm('login_pass_explain') ?></div>
    <form method="POST">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf_token) ?>">
      <input type="hidden" name="mode" value="password">
      <div style="display:none" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
      <label for="email_p"><?= tm('votre_email') ?></label>
      <input type="email" id="email_p" name="email" placeholder="votre@email.be" required autocomplete="username">
      <label for="password" style="margin-top:12px"><?= tm('label_password') ?></label>
      <input type="password" id="password" name="password" required autocomplete="current-password">
      <button type="submit" class="btn"><?= tm('btn_connexion_pass') ?></button>
    </form>
    <div class="links" style="margin-top:14px">
      <a href="javascript:void(0)" onclick="showAuth('lien')"><?= tm('mdp_oublie') ?></a>
    </div>
  </div>

  <!-- Lien magique -->
  <div class="auth-panel <?= $active_auth==='lien'?'active':'' ?>" id="apanel-lien">
    <div class="magic-icon">✨</div>
    <div class="explain"><?= tm('login_explain') ?></div>
    <form method="POST">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf_token) ?>">
      <input type="hidden" name="mode" value="magic">
      <div style="display:none" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
      <label for="email_m"><?= tm('votre_email') ?></label>
      <input type="email" id="email_m" name="email" placeholder="votre@email.be" required>
      <button type="submit" class="btn"><?= tm('btn_recevoir_lien') ?></button>
    </form>
  </div>

  <div class="links">
    <?= tm('pas_encore_membre') ?> <a href="inscription.php"><?= tm('creer_espace') ?></a><br>
    <a href="<?= SITE_URL ?>"><?= tm('retour_site') ?></a>
  </div>

  <?php endif; ?>
</div>
<script>
function showAuth(which){
  ['pass','lien'].forEach(function(w){
    document.getElementById('apanel-'+w).classList.toggle('active', w===which);
    document.getElementById('atab-'+w).classList.toggle('active', w===which);
  });
}
</script>
</body>
</html>
