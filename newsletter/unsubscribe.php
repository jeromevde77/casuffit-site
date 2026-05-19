<?php
// newsletter/unsubscribe.php — Désabonnement (lien en bas de chaque newsletter)
require_once __DIR__ . '/../config.php';

$token = trim((isset($_GET['token']) ? $_GET['token'] : ''));
$done  = false;
$error = '';

if (strlen($token) !== 64) {
    $error = 'Lien invalide.';
} else {
    try {
        $db = getDB();
        $stmt = $db->prepare("UPDATE subscribers SET statut='desabonne' WHERE token_unsub = ?");
        $stmt->execute([$token]);
        $done = $stmt->rowCount() > 0;
        if (!$done) $error = 'Lien introuvable.';
    } catch (PDOException $e) {
        $error = 'Erreur serveur.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Désabonnement — Piste 01 Ça suffit !</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: "Helvetica Neue", Arial, sans-serif; background: #f5f8fc; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
    .card { background: #fff; border-radius: 16px; box-shadow: 0 4px 30px rgba(22,115,178,0.12); padding: 48px 40px; max-width: 480px; width: 100%; text-align: center; }
    .icon { font-size: 3rem; margin-bottom: 20px; }
    h1 { font-size: 1.4rem; color: #1673B2; margin-bottom: 12px; }
    p { color: #555; line-height: 1.6; margin-bottom: 20px; }
    .btn { display: inline-block; background: #1673B2; color: #fff; padding: 12px 28px; border-radius: 8px; text-decoration: none; font-weight: 600; }
    small { display: block; margin-top: 12px; color: #888; font-size: 0.75rem; }
  </style>
</head>
<body>
<div class="card">
  <?php if ($done): ?>
    <div class="icon">👋</div>
    <h1>Vous êtes désabonné(e)</h1>
    <p>Vous ne recevrez plus de newsletters de <strong>Piste 01 Ça suffit !</strong><br>Vos données personnelles restent dans notre base conformément au RGPD mais ne seront plus utilisées pour des envois.</p>
    <a href="<?= SITE_URL ?>" class="btn">Retour au site</a>
    <small>Pour faire supprimer définitivement vos données, contactez-nous : <?= ADMIN_EMAIL ?></small>
  <?php else: ?>
    <div class="icon">⚠️</div>
    <h1>Lien invalide</h1>
    <p><?= htmlspecialchars($error) ?><br>Contactez-nous directement : <a href="mailto:<?= ADMIN_EMAIL ?>"><?= ADMIN_EMAIL ?></a></p>
    <a href="<?= SITE_URL ?>" class="btn">Retour au site</a>
  <?php endif; ?>
</div>
</body>
</html>
