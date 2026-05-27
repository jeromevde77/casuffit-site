<?php
// admin/install_admin.php — Création du superadmin initial (usage unique)
// Accessible uniquement si la table admin_users est vide
require_once __DIR__ . '/../config.php';
session_start();

$db = getDB();

// Vérifier que la table existe
try {
    $count = (int)$db->query("SELECT COUNT(*) FROM admin_users")->fetchColumn();
} catch (Exception $e) {
    die('<div style="font-family:sans-serif;padding:40px;max-width:600px">
        <h2>⚠ Table manquante</h2>
        <p>Exécute d\'abord <code>migrate_admin_users.sql</code> dans phpMyAdmin, puis reviens ici.</p>
        <pre style="background:#f5f5f5;padding:12px;border-radius:6px;font-size:.8rem">'
        . htmlspecialchars($e->getMessage()) . '</pre></div>');
}

if ($count > 0) {
    die('<div style="font-family:sans-serif;padding:40px;max-width:600px">
        <h2>✅ Déjà configuré</h2>
        <p>Un compte administrateur existe déjà. Ce script ne peut être utilisé qu\'une seule fois.</p>
        <p><a href="login.php">→ Aller à la connexion</a></p></div>');
}

$error = ''; $success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $pass     = $_POST['password'] ?? '';
    $pass2    = $_POST['password2'] ?? '';

    if (!preg_match('/^[a-z0-9_]{3,30}$/', $username))
        $error = 'Nom d\'utilisateur invalide (lettres minuscules, chiffres, _, 3-30 caractères).';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $error = 'Email invalide.';
    elseif (strlen($pass) < 10)
        $error = 'Mot de passe trop court (10 caractères minimum).';
    elseif ($pass !== $pass2)
        $error = 'Les mots de passe ne correspondent pas.';
    else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $db->prepare("INSERT INTO admin_users (username, email, password_hash, role, is_active) VALUES (?,?,?,'superadmin',1)")
           ->execute([$username, $email, $hash]);
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Installation admin — Ça suffit !</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:"Helvetica Neue",Arial,sans-serif;background:linear-gradient(135deg,#0e3d6b,#1673B2);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
    .card{background:#fff;border-radius:16px;box-shadow:0 8px 40px rgba(0,0,0,.25);padding:44px 40px;width:100%;max-width:440px}
    h2{font-size:1.3rem;font-weight:800;color:#0e3d6b;margin-bottom:6px}
    .sub{font-size:.8rem;color:#888;margin-bottom:28px}
    label{display:block;font-size:.8rem;font-weight:600;color:#555;margin-bottom:5px}
    input{width:100%;padding:11px 13px;border:1.5px solid #dde4ed;border-radius:8px;font-size:.9rem;outline:none;margin-bottom:16px;font-family:inherit}
    input:focus{border-color:#1673B2}
    button{width:100%;background:#1673B2;color:#fff;border:none;padding:13px;border-radius:8px;font-size:1rem;font-weight:700;cursor:pointer}
    button:hover{background:#125a90}
    .error{background:#fde8e8;color:#c0392b;padding:11px 13px;border-radius:8px;font-size:.83rem;margin-bottom:18px;border-left:3px solid #c0392b}
    .success{background:#e8f8f0;color:#276749;padding:16px;border-radius:8px;margin-bottom:16px;font-size:.85rem;line-height:1.7}
    .info{background:#e6f1fb;border:1.5px solid #bdd5f5;border-radius:8px;padding:12px 14px;font-size:.78rem;color:#1673B2;margin-bottom:20px;line-height:1.6}
  </style>
</head>
<body>
<div class="card">
  <h2>🔧 Installation admin</h2>
  <p class="sub">Création du compte superadmin — usage unique</p>

  <?php if ($success): ?>
  <div class="success">
    ✅ Compte <strong><?= htmlspecialchars($_POST['username']) ?></strong> créé avec succès.<br><br>
    <strong>Prochaines étapes :</strong><br>
    1. <a href="login.php">Connecte-toi</a> avec tes identifiants<br>
    2. Configure le 2FA (tu seras guidé automatiquement)<br>
    3. Imprime tes codes de secours<br><br>
    <em>Ce script est maintenant inutilisable (table non vide).</em>
  </div>
  <a href="login.php" style="display:block;text-align:center;color:#1673B2;font-weight:700;text-decoration:none">→ Se connecter</a>

  <?php else: ?>
  <div class="info">
    ℹ Ce formulaire ne peut être soumis <strong>qu'une seule fois</strong>.<br>
    Il sera automatiquement désactivé après la création du premier compte.
  </div>

  <?php if ($error): ?>
  <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <label>Nom d'utilisateur</label>
    <input type="text" name="username" value="jeromevde" required pattern="[a-z0-9_]{3,30}" autocomplete="username">
    <label>Email</label>
    <input type="email" name="email" value="jeromevde@me.com" required autocomplete="email">
    <label>Mot de passe (10 car. min.)</label>
    <input type="password" name="password" required minlength="10" autocomplete="new-password">
    <label>Confirmer le mot de passe</label>
    <input type="password" name="password2" required minlength="10" autocomplete="new-password">
    <button type="submit">Créer le compte superadmin</button>
  </form>
  <?php endif; ?>
</div>
</body>
</html>
