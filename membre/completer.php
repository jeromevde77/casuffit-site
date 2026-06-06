<?php
// membre/completer.php — Formulaire de complétion d'adresse (accès par token magique)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/functions.php';
session_start();
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/lang.php';
$db = getDB();

$error = ''; $success = false; $membre = null;

// 1) Accès par token magique (depuis l'email de rappel)
$token = trim($_GET['token'] ?? '');
if ($token && strlen($token) === 64) {
    $stmt = $db->prepare("SELECT * FROM members WHERE token_magic = ? AND token_magic_exp > NOW() AND statut = 'actif'");
    $stmt->execute([$token]);
    $m = $stmt->fetch();
    if ($m) {
        // Connecter le membre (sans invalider le token tout de suite : il finalise le formulaire)
        $_SESSION['membre_id']    = $m['id'];
        $_SESSION['membre_email'] = $m['email'];
    }
}

// 2) Récupérer le membre connecté
if (!empty($_SESSION['membre_id'])) {
    $stmt = $db->prepare("SELECT * FROM members WHERE id = ? AND statut = 'actif'");
    $stmt->execute([$_SESSION['membre_id']]);
    $membre = $stmt->fetch();
}

if (!$membre) {
    $error = 'Lien invalide ou expiré. Demandez un nouveau lien de connexion.';
}

// 3) Enregistrement
if ($membre && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['completer_adresse'])) {
    $rue         = trim($_POST['rue']         ?? '');
    $numero      = trim($_POST['numero']      ?? '');
    $boite       = trim($_POST['boite']       ?? '');
    $code_postal = trim($_POST['code_postal'] ?? '');
    $commune     = trim($_POST['commune']     ?? '');

    if (!$rue || !$code_postal || !$commune) {
        $error = 'Merci de compléter au minimum la rue, le code postal et la commune.';
    } else {
        $adresse = trim("$rue $numero" . ($boite ? " bte $boite" : ''));
        $db->prepare("UPDATE members SET rue=?, numero=?, boite=?, code_postal=?, commune=?, adresse=?, donnees_verifiees_at = NOW() WHERE id=?")
           ->execute([$rue, $numero, $boite, $code_postal, $commune, $adresse, $membre['id']]);
        if ($membre['subscriber_id']) {
            $db->prepare("UPDATE subscribers SET adresse=?, commune=? WHERE id=?")
               ->execute([$adresse, $commune, $membre['subscriber_id']]);
        }
        // Invalider le token magique (usage terminé)
        $db->prepare("UPDATE members SET token_magic=NULL, token_magic_exp=NULL WHERE id=?")->execute([$membre['id']]);
        $success = true;
        // Recharger les données à jour
        $stmt = $db->prepare("SELECT * FROM members WHERE id = ?");
        $stmt->execute([$membre['id']]);
        $membre = $stmt->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $LANG ?>">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Compléter mon adresse — Ça suffit !</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Helvetica Neue",Arial,sans-serif;background:linear-gradient(135deg,#0e3d6b,#1673B2);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:#fff;border-radius:16px;padding:36px;width:100%;max-width:460px;box-shadow:0 8px 40px rgba(0,0,0,0.25)}
.brand{text-align:center;margin-bottom:22px}
.brand img{width:64px;height:64px;border-radius:50%;background:#1673B2;margin-bottom:10px}
.brand .name{font-size:1.3rem;font-weight:800;color:#FF9900}
h1{font-size:1.1rem;color:#0e3d6b;margin-bottom:8px;text-align:center}
.intro{font-size:.85rem;color:#666;line-height:1.5;margin-bottom:22px;text-align:center}
label{display:block;font-size:.78rem;font-weight:700;color:#0e3d6b;margin:12px 0 4px}
input{width:100%;padding:10px 12px;border:1.5px solid #d0dce8;border-radius:8px;font-size:.9rem;font-family:inherit}
input:focus{outline:none;border-color:#1673B2}
.row{display:flex;gap:10px}
.row .col-sm{flex:0 0 90px}
.btn{width:100%;margin-top:20px;background:#FF9900;color:#fff;border:none;padding:13px;border-radius:8px;font-size:.95rem;font-weight:700;cursor:pointer}
.btn:hover{background:#e68a00}
.msg-err{background:#fdecea;color:#c0392b;padding:11px 14px;border-radius:8px;font-size:.83rem;margin-bottom:16px}
.success{text-align:center}
.success .icon{font-size:3rem;margin-bottom:12px}
.success h1{color:#1a7a4a}
.success p{font-size:.9rem;color:#555;line-height:1.6;margin-top:10px}
.success .btn{display:inline-block;width:auto;padding:11px 28px;text-decoration:none;margin-top:22px}
</style>
</head>
<body>
<div class="card">
  <div class="brand">
    <img src="https://www.casuffit.be/assets/img/logo.png" alt="Ça suffit !">
    <div class="name">Ça suffit !</div>
  </div>

  <?php if ($success): ?>
    <div class="success">
      <div class="icon">✅</div>
      <h1>Merci !</h1>
      <p>Votre adresse a bien été enregistrée. Cela renforce notre action : nous savons désormais précisément quelles communes sont survolées injustement.</p>
      <a href="dashboard.php" class="btn">Accéder à mon espace membre</a>
    </div>
  <?php elseif (!$membre): ?>
    <h1>Lien invalide</h1>
    <div class="msg-err"><?= htmlspecialchars($error) ?></div>
    <a href="login.php" class="btn" style="display:inline-block;width:auto;padding:11px 28px;text-decoration:none">Demander un nouveau lien</a>
  <?php else: ?>
    <h1>Complétez votre adresse</h1>
    <p class="intro">Bonjour <?= htmlspecialchars($membre['prenom'] ?: '') ?>, il nous manque votre adresse complète. Elle nous permet de savoir quelles communes sont survolées — un argument essentiel dans nos démarches.</p>
    <?php if ($error): ?><div class="msg-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="completer_adresse" value="1">
      <label>Rue *</label>
      <input type="text" name="rue" value="<?= htmlspecialchars($membre['rue'] ?? '') ?>" required>
      <div class="row">
        <div style="flex:1">
          <label>Numéro *</label>
          <input type="text" name="numero" value="<?= htmlspecialchars($membre['numero'] ?? '') ?>" required>
        </div>
        <div class="col-sm">
          <label>Boîte</label>
          <input type="text" name="boite" value="<?= htmlspecialchars($membre['boite'] ?? '') ?>">
        </div>
      </div>
      <div class="row">
        <div class="col-sm" style="flex:0 0 110px">
          <label>Code postal *</label>
          <input type="text" name="code_postal" value="<?= htmlspecialchars($membre['code_postal'] ?? '') ?>" required>
        </div>
        <div style="flex:1">
          <label>Commune *</label>
          <input type="text" name="commune" value="<?= htmlspecialchars($membre['commune'] ?? '') ?>" required>
        </div>
      </div>
      <button type="submit" class="btn">Enregistrer mon adresse</button>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
