<?php // templates/email_magic.php — Email lien magique connexion membre ?>
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f0f6fb;font-family:'Helvetica Neue',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f6fb;padding:30px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(22,115,178,0.12);max-width:600px;width:100%;">
      <!-- Header -->
      <tr>
        <td style="background:linear-gradient(135deg,#0e3d6b 0%,#1673B2 100%);padding:28px 40px;text-align:center;">
          <h1 style="color:#fff;font-size:20px;font-weight:800;margin:0;">
            Ça suffit ! <span style="color:#FF9900;font-style:italic;">ASBL</span>
          </h1>
          <p style="color:rgba(255,255,255,0.7);font-size:11px;margin:5px 0 0;letter-spacing:0.08em;text-transform:uppercase;">Piste 01 · UBCNA · Espace membre</p>
        </td>
      </tr>
      <!-- Badge code membre -->
      <tr>
        <td style="background:#FF9900;padding:10px 40px;text-align:center;">
          <span style="color:#fff;font-size:13px;font-weight:700;letter-spacing:0.06em;"><?= htmlspecialchars($code_membre) ?></span>
        </td>
      </tr>
      <!-- Body -->
      <tr>
        <td style="padding:36px 40px;">
          <p style="color:#333;font-size:15px;margin:0 0 16px;">Bonjour <?= htmlspecialchars($prenom) ?>,</p>
          <p style="color:#555;font-size:14px;line-height:1.7;margin:0 0 24px;">
            Voici votre <strong>lien de connexion sécurisé</strong> pour accéder à votre espace membre.<br>
            Ce lien est <strong>personnel, à usage unique</strong> et valable <strong>24 heures</strong>.
          </p>
          <div style="text-align:center;margin:28px 0;">
            <a href="<?= htmlspecialchars($magic_url) ?>"
               style="background:#1673B2;color:#fff;text-decoration:none;padding:14px 32px;border-radius:8px;font-size:15px;font-weight:700;display:inline-block;">
              ✨ Accéder à mon espace membre
            </a>
          </div>
          <div style="background:#f0f7ff;border-radius:8px;padding:14px 16px;margin-bottom:16px;border:1px solid #bee3f8;">
            <p style="color:#1673B2;font-size:13px;margin:0 0 8px;font-weight:700;">Votre QR code personnel vous permet de :</p>
            <p style="color:#555;font-size:12px;line-height:1.7;margin:0;">
              ✅ Effectuer un virement avec communication structurée (+++)<br>
              ✅ Être identifié automatiquement lors de la réception du don<br>
              ✅ Voir l'historique de vos contributions dans votre espace
            </p>
          </div>
          <p style="color:#aaa;font-size:12px;line-height:1.6;">
            Si vous n'avez pas demandé ce lien, ignorez simplement cet email.<br>
            Lien expirant le : <strong><?= date('d/m/Y à H\hi', strtotime('+24 hours')) ?></strong>
          </p>
        </td>
      </tr>
      <!-- Footer -->
      <tr>
        <td style="background:#f8f9fa;padding:18px 40px;border-top:1px solid #eee;text-align:center;">
          <p style="color:#aaa;font-size:11px;margin:0;line-height:1.8;">
            <strong style="color:#1673B2;">Ça suffit !</strong> · Piste 01 · UBCNA<br>
            <?= ADMIN_EMAIL ?> · <?= SITE_URL ?>
          </p>
        </td>
      </tr>
    </table>
  </td></tr>
</table>
</body>
</html>
