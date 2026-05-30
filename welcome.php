<?php
// welcome.php — Accueil contact Wix via lien unique
// Crée automatiquement un compte membre et connecte l'utilisateur
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/membre/functions.php';

session_start();
$db = getDB();

$token = trim(isset($_GET['token']) ? $_GET['token'] : '');
$email = filter_var(trim(isset($_GET['email']) ? $_GET['email'] : ''), FILTER_VALIDATE_EMAIL);
$error = '';
$membre_cree = null;

if (!$token || !$email) {
    $error = 'Lien invalide.';
} else {
    // Vérifier le token dans subscribers
    $stmt = $db->prepare("SELECT * FROM subscribers WHERE email=? AND token_confirm=? AND statut='actif'");
    $stmt->execute(array($email, $token));
    $sub = $stmt->fetch();

    if (!$sub) {
        $error = 'Ce lien est invalide ou a déjà été utilisé. <a href="membre/login.php">Accédez à votre espace ici</a>.';
    } else {
        // Vérifier si un compte membre existe déjà
        $stmt2 = $db->prepare("SELECT * FROM members WHERE email=?");
        $stmt2->execute(array($email));
        $membre = $stmt2->fetch();

        if (!$membre) {
            // Créer le compte membre automatiquement
            try {
                $token_unsub = bin2hex(random_bytes(32));
                $db->prepare("INSERT INTO members (email, prenom, nom, adresse, commune, telephone, token_unsub, statut, newsletter, code_membre, ogm, subscriber_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'actif', 1, 'TEMP', 'TEMP', ?)")
                   ->execute(array($email, $sub['prenom'], $sub['nom'], $sub['adresse'] ?? '', $sub['commune'], $sub['telephone'], $token_unsub, $sub['id']));
                $member_id   = $db->lastInsertId();
                $code_membre = genererCodeMembre($db);
                $ogm         = genererOGM($member_id);
                $db->prepare("UPDATE members SET code_membre=?, ogm=? WHERE id=?")
                   ->execute(array($code_membre, $ogm, $member_id));
                // Recharger
                $stmt3 = $db->prepare("SELECT * FROM members WHERE id=?");
                $stmt3->execute(array($member_id));
                $membre = $stmt3->fetch();
            } catch (Exception $e) {
                $error = 'Erreur lors de la création du compte : ' . $e->getMessage();
            }
        }

        if ($membre && !$error) {
            // Connecter le membre
            $_SESSION['membre_id']    = $membre['id'];
            $_SESSION['membre_email'] = $membre['email'];
            // Invalider le token (usage unique)
            $db->prepare("UPDATE subscribers SET token_confirm=NULL WHERE id=?")->execute(array($sub['id']));
            $db->prepare("UPDATE members SET derniere_connexion=NOW() WHERE id=?")->execute(array($membre['id']));
            $membre_cree = $membre;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Bienvenue — Piste01 Ça Suffit ASBL</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:"Helvetica Neue",Arial,sans-serif;background:linear-gradient(135deg,#0e3d6b,#1673B2);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
    .card{background:#fff;border-radius:16px;box-shadow:0 8px 40px rgba(0,0,0,.25);padding:40px;width:100%;max-width:480px;text-align:center}
    .logo{width:80px;height:80px;object-fit:contain;border-radius:50%;margin:0 auto 20px;display:block}
    .brand{font-size:1.3rem;font-weight:800;color:#1673B2;margin-bottom:4px}
    .brand span{color:#FF9900;font-style:italic}
    .welcome-icon{font-size:3rem;margin-bottom:16px}
    h1{font-size:1.2rem;color:#0e3d6b;margin-bottom:10px}
    p{font-size:.88rem;color:#555;line-height:1.7;margin-bottom:14px}
    .code-badge{display:inline-block;background:#e6f1fb;color:#1673B2;font-size:.78rem;font-weight:700;padding:4px 14px;border-radius:20px;border:1px solid #b5d4f4;margin:10px 0 20px;letter-spacing:.04em}
    .btn{display:block;width:100%;padding:14px;border-radius:8px;font-size:1rem;font-weight:700;cursor:pointer;border:none;font-family:inherit;text-decoration:none;margin-top:10px}
    .btn-orange{background:#FF9900;color:#fff}
    .btn-blue{background:#1673B2;color:#fff}
    .btn:hover{opacity:.9}
    .error-box{background:#fde8e8;color:#c53030;padding:16px;border-radius:8px;border-left:4px solid #fc8181;font-size:.88rem;text-align:left}
    .error-box a{color:#c53030}
    .avantages{background:#f0f7ff;border-radius:8px;padding:14px 16px;text-align:left;font-size:.8rem;color:#555;line-height:1.8;margin:14px 0}
  </style>
</head>
<body>
<div class="card">
  <img src="medias/logo.png" class="logo" alt="logo" onerror="this.style.display='none'">
  <div class="brand">Ça suffit ! <span>ASBL</span></div>

  <?php if ($error): ?>
    <div style="margin-top:20px" class="error-box"><?= $error ?></div>
    <a href="<?= SITE_URL ?>" class="btn btn-blue" style="margin-top:20px">← Retour au site</a>

  <?php elseif ($membre_cree): ?>
    <div class="welcome-icon">🎉</div>
    <h1>Bienvenue <?= htmlspecialchars($membre_cree['prenom'] ?: '') ?> !</h1>
    <p>Votre espace membre a été créé automatiquement. Vous êtes maintenant connecté(e).</p>
    <div class="code-badge">Membre <?= htmlspecialchars($membre_cree['code_membre']) ?></div>
    <div class="avantages">
      Depuis votre espace vous pouvez :<br>
      💳 Générer votre QR code de paiement personnel<br>
      📋 Suivre l'historique de vos dons<br>
      📧 Gérer votre abonnement newsletter
    </div>
    <a href="membre/dashboard.php" class="btn btn-orange">→ Accéder à mon espace membre</a>
    <a href="<?= SITE_URL ?>" class="btn btn-blue">← Retour au site</a>
  <?php endif; ?>
</div>

<?php if ($membre_cree): ?>
<script>
  // Redirection automatique après 4 secondes
  setTimeout(function() {
    window.location.href = 'membre/dashboard.php';
  }, 4000);
</script>
<?php endif; ?>
</body>
</html>
