<?php // templates/email_newsletter.php
// Variables disponibles : $sujet, $contenu_html, $prenom_display, $unsubscribe_url ?>
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f0f6fb;font-family:'Helvetica Neue',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f6fb;padding:30px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(22,115,178,0.12);max-width:600px;width:100%;">
      <!-- Header -->
      <tr>
        <td style="background:linear-gradient(135deg,#0e3d6b 0%,#1673B2 100%);padding:28px 40px;text-align:center;">
          <h1 style="color:#ffffff;font-size:22px;font-weight:800;margin:0;letter-spacing:-0.02em;">
            Piste 01 <span style="color:#FF9900;font-style:italic;">ça suffit !</span>
          </h1>
          <p style="color:rgba(255,255,255,0.7);font-size:11px;margin:5px 0 0;letter-spacing:0.08em;text-transform:uppercase;">ASBL · Brabant wallon</p>
        </td>
      </tr>
      <!-- Sujet -->
      <tr>
        <td style="background:#FF9900;padding:14px 40px;text-align:center;">
          <p style="color:#fff;font-size:15px;font-weight:700;margin:0;letter-spacing:0.01em;">
            <?= htmlspecialchars($sujet) ?>
          </p>
        </td>
      </tr>
      <!-- Corps -->
      <tr>
        <td style="padding:36px 40px;">
          <p style="color:#333;font-size:15px;margin:0 0 20px;">Bonjour <?= htmlspecialchars($prenom_display) ?>,</p>
          <div style="color:#444;font-size:15px;line-height:1.75;">
            <?= $contenu_html ?>
          </div>
          <!-- Séparateur don -->
          <table width="100%" cellpadding="0" cellspacing="0" style="margin:32px 0;background:#f0f6fb;border-radius:10px;padding:24px;">
            <tr>
              <td style="text-align:center;">
                <p style="color:#1673B2;font-size:14px;font-weight:600;margin:0 0 12px;">Soutenez la suite de notre combat juridique</p>
                <a href="<?= SITE_URL ?>#don" style="background:#FF9900;color:#fff;text-decoration:none;padding:12px 28px;border-radius:8px;font-size:14px;font-weight:700;display:inline-block;">
                  ✈ Faire un don
                </a>
                <p style="color:#888;font-size:11px;margin:10px 0 0;">IBAN : BE41 0689 0149 6910 · Belfius</p>
              </td>
            </tr>
          </table>
        </td>
      </tr>
      <!-- Footer -->
      <tr>
        <td style="background:#f8f9fa;padding:20px 40px;border-top:1px solid #eee;text-align:center;">
          <p style="color:#aaa;font-size:11px;margin:0 0 8px;line-height:1.8;">
            <strong style="color:#1673B2;">Piste 01 ça suffit ! ASBL</strong><br>
            <a href="mailto:<?= ADMIN_EMAIL ?>" style="color:#1673B2;text-decoration:none;"><?= ADMIN_EMAIL ?></a>
            · <a href="<?= SITE_URL ?>" style="color:#1673B2;text-decoration:none;"><?= SITE_URL ?></a>
          </p>
          <p style="color:#ccc;font-size:10px;margin:0;">
            Vous recevez cet email car vous êtes inscrit(e) à notre newsletter.<br>
            <a href="<?= htmlspecialchars($unsubscribe_url) ?>" style="color:#aaa;text-decoration:underline;">Me désabonner</a>
          </p>
        </td>
      </tr>
    </table>
  </td></tr>
</table>
</body>
</html>
