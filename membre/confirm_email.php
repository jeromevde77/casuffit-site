<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/functions.php';
session_start();
require_once __DIR__ . '/lang.php';

$db    = getDB();
$token = trim($_GET['token'] ?? '');

if (!$token) { header('Location: ' . SITE_URL); exit; }

$stmt = $db->prepare("SELECT * FROM members WHERE token_email_change = ? AND email_nouveau IS NOT NULL AND statut='actif'");
$stmt->execute([$token]);
$membre = $stmt->fetch();

if (!$membre) {
?>
<!DOCTYPE html><html lang="<?= \$LANG ?>"><head><meta charset="UTF-8"><title><?= tm('confirm_invalide_h') ?></title></head>
<body style="font-family:Arial;text-align:center;padding:60px">
  <h2 style="color:#c53030"><?= tm('confirm_invalide_h') ?></h2>
  <p style="color:#666;margin-top:12px"><?= tm('confirm_invalide_p') ?></p>
  <a href="<?= SITE_URL ?>/membre/dashboard.php" style="color:#1673B2"><?= tm('retour_espace') ?></a>
</body></html>
<?php exit; }

// Valider le changement
$db->prepare("UPDATE members SET email = email_nouveau, email_nouveau = NULL, token_email_change = NULL WHERE id = ?")
   ->execute([$membre['id']]);

// Mettre à jour aussi le subscriber lié
if ($membre['subscriber_id']) {
    $db->prepare("UPDATE subscribers SET email = ? WHERE id = ?")
       ->execute([$membre['email_nouveau'], $membre['subscriber_id']]);
}
?>
<!DOCTYPE html><html lang="<?= \$LANG ?>"><head><meta charset="UTF-8"><title><?= tm('confirm_ok_h') ?></title></head>
<body style="font-family:Arial;text-align:center;padding:60px">
  <h2 style="color:#27ae60"><?= tm('confirm_ok_h') ?></h2>
  <p style="color:#555;margin-top:12px"><?= tm('confirm_ok_p', htmlspecialchars(\$membre['email_nouveau'])) ?></p>
  <a href="<?= SITE_URL ?>/membre/dashboard.php" style="display:inline-block;margin-top:20px;padding:10px 20px;background:#0e3d6b;color:#fff;border-radius:8px;text-decoration:none;font-weight:700"><?= tm('retour_espace_btn') ?></a>
</body></html>
