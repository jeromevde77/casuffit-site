<?php
// newsletter/confirm.php — Confirmation de l'inscription (double opt-in)
require_once __DIR__ . '/../config.php';

$token = trim((isset($_GET['token']) ? $_GET['token'] : ''));
$error = '';
$success = false;

if (empty($token) || strlen($token) !== 64) {
    $error = 'Lien invalide ou expiré.';
} else {
    try {
        $db = getDB();
        $stmt = $db->prepare('SELECT id, prenom, statut FROM subscribers WHERE token_confirm = ? LIMIT 1');
        $stmt->execute([$token]);
        $sub = $stmt->fetch();

        if (!$sub) {
            $error = 'Ce lien de confirmation est invalide ou a déjà été utilisé.';
        } elseif ($sub['statut'] === 'actif') {
            $success = true; // déjà confirmé
        } else {
            $db->prepare("UPDATE subscribers SET statut='actif', date_confirmation=NOW(), token_confirm=NULL WHERE id=?")
               ->execute([$sub['id']]);
            $success = true;
        }
    } catch (PDOException $e) {
        $error = 'Erreur serveur. Veuillez contacter ' . ADMIN_EMAIL;
        error_log('Confirm error: ' . $e->getMessage());
    }
}
$prenom_display = (isset($sub['prenom']) ? $sub['prenom'] : '');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Confirmation — Piste 01 ça suffit !</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: "Helvetica Neue", Arial, sans-serif; background: #f5f8fc; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
    .card { background: #fff; border-radius: 16px; box-shadow: 0 4px 30px rgba(22,115,178,0.12); padding: 48px 40px; max-width: 480px; width: 100%; text-align: center; }
    .icon { font-size: 3rem; margin-bottom: 20px; }
    h1 { font-size: 1.5rem; color: #1673B2; margin-bottom: 12px; }
    p { color: #555; line-height: 1.6; margin-bottom: 20px; }
    .btn { display: inline-block; background: #1673B2; color: #fff; padding: 12px 28px; border-radius: 8px; text-decoration: none; font-weight: 600; }
    .btn:hover { background: #125a90; }
    .error { color: #c0392b; }
  </style>
</head>
<body>
<div class="card">
  <?php if ($success): ?>
    <div class="icon">✅</div>
    <h1>Inscription confirmée !</h1>
    <p>Merci <?= htmlspecialchars($prenom_display) ?>, vous êtes maintenant inscrit(e) à la newsletter de <strong>Piste 01 ça suffit !</strong><br>Vous serez informé(e) de toutes nos actions et de l'avancement de nos démarches.</p>
    <a href="<?= SITE_URL ?>" class="btn">Retour au site</a>
  <?php else: ?>
    <div class="icon">❌</div>
    <h1 class="error">Lien invalide</h1>
    <p class="error"><?= htmlspecialchars($error) ?></p>
    <a href="<?= SITE_URL ?>" class="btn">Retour au site</a>
  <?php endif; ?>
</div>
</body>
</html>
