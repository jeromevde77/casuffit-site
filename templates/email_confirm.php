<?php // templates/email_confirm.php — Email de confirmation double opt-in ?>
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f0f6fb;font-family:'Helvetica Neue',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f6fb;padding:30px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(22,115,178,0.12);max-width:600px;width:100%;">
      <!-- Header -->
      <tr>
        <td style="background:linear-gradient(135deg,#0e3d6b 0%,#1673B2 100%);padding:32px 40px;text-align:center;">
          <h1 style="color:#ffffff;font-size:22px;font-weight:800;margin:0;letter-spacing:-0.02em;">
            Piste 01 <span style="color:#FF9900;font-style:italic;">Ça suffit !</span>
          </h1>
          <p style="color:rgba(255,255,255,0.75);font-size:12px;margin:6px 0 0;letter-spacing:0.08em;text-transform:uppercase;">ASBL · Brabant wallon</p>
        </td>
      </tr>
      <!-- Body -->
      <tr>
        <td style="padding:40px;">
          <p style="color:#333;font-size:16px;margin:0 0 16px;">Bonjour <?= htmlspecialchars($prenom_display) ?>,</p>
          <p style="color:#555;font-size:15px;line-height:1.7;margin:0 0 24px;">
            Merci de votre soutien à notre combat contre les nuisances de la piste 01 de Brussels Airport.<br>
            Pour finaliser votre inscription à notre newsletter, cliquez sur le bouton ci-dessous :
          </p>
          <div style="text-align:center;margin:32px 0;">
            <a href="<?= htmlspecialchars($confirm_url) ?>"
               style="background:#1673B2;color:#ffffff;text-decoration:none;padding:16px 36px;border-radius:10px;font-size:15px;font-weight:700;display:inline-block;">
              ✅ Confirmer mon inscription
            </a>
          </div>
          <p style="color:#888;font-size:13px;line-height:1.6;margin:0 0 12px;">
            Ce lien est valable <strong>48 heures</strong>. Si vous n'avez pas demandé cette inscription, ignorez simplement cet email.
          </p>
          <p style="color:#555;font-size:14px;line-height:1.7;margin:24px 0 0;border-top:1px solid #eee;padding-top:20px;">
            En vous inscrivant, vous serez informé(e) de :<br>
            ✈ L'avancement de notre combat juridique contre l'État belge<br>
            ✈ Les actualités sur les nuisances de la piste 01<br>
            ✈ Les actions citoyennes et manifestations<br>
            ✈ Les victoires de notre mobilisation
          </p>
        </td>
      </tr>
      <!-- Footer -->
      <tr>
        <td style="background:#f8f9fa;padding:20px 40px;text-align:center;border-top:1px solid #eee;">
          <p style="color:#aaa;font-size:11px;margin:0;line-height:1.8;">
            <strong style="color:#1673B2;">Piste 01 Piste01 Ça Suffit ASBL</strong><br>
            <?= ADMIN_EMAIL ?> · <?= SITE_URL ?><br>
            IBAN : BE41 0689 0149 6910 (Belfius)
          </p>
        </td>
      </tr>
    </table>
  </td></tr>
</table>
</body>
</html>
