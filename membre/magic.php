<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // désactivé en production
// membre/magic.php — Valide le token magique et connecte le membre
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/functions.php';

session_start();
$db = getDB();

$token = trim(isset($_GET['token']) ? $_GET['token'] : '');
$error = '';

if (strlen($token) !== 64) {
    $error = 'Lien invalide ou malformé.';
} else {
    $stmt = $db->prepare("SELECT * FROM members WHERE token_magic = ? AND token_magic_exp > NOW() AND statut = 'actif'");
    $stmt->execute(array($token));
    $membre = $stmt->fetch();

    if (!$membre) {
        $error = 'Ce lien est invalide ou a expiré. <a href="login.php">Demandez un nouveau lien</a>.';
    } else {
        // Connecter le membre
        $_SESSION['membre_id']    = $membre['id'];
        $_SESSION['membre_email'] = $membre['email'];

        // Invalider le token (usage unique)
        $db->prepare("UPDATE members SET token_magic=NULL, token_magic_exp=NULL, derniere_connexion=NOW() WHERE id=?")
           ->execute(array($membre['id']));

        // Rediriger vers le dashboard
        header('Location: dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Connexion — ça suffit ! ASBL</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:"Helvetica Neue",Arial,sans-serif;background:linear-gradient(135deg,#0e3d6b,#1673B2);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
    .card{background:#fff;border-radius:16px;padding:40px;width:100%;max-width:420px;text-align:center;box-shadow:0 8px 40px rgba(0,0,0,0.25)}
    .icon{font-size:3rem;margin-bottom:16px}
    h1{font-size:1.2rem;color:#c53030;margin-bottom:10px}
    p{font-size:0.88rem;color:#555;line-height:1.6}
    a{color:#1673B2;text-decoration:none}
    .btn{display:inline-block;margin-top:20px;background:#1673B2;color:#fff;padding:10px 22px;border-radius:8px;font-weight:700;font-size:0.88rem;text-decoration:none}
  </style>
</head>
<body>
<div class="card">
  <div class="icon">⚠️</div>
  <h1>Lien invalide ou expiré</h1>
  <p><?= $error ?></p>
  <a href="login.php" class="btn">Demander un nouveau lien</a>
</div>
</body>
</html>
