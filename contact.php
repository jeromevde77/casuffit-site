<?php
// contact.php — Page de contact Ça suffit ! (standalone, sans header.php)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/mail_helper.php';
session_start();

$lang = ($_GET['lang'] ?? 'fr') === 'nl' ? 'nl' : 'fr';
$is_nl = ($lang === 'nl');
function tr(bool $nl, string $fr, string $nls): string { return $nl ? $nls : $fr; }

$success = false; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom     = trim($_POST['nom']     ?? '');
    $email   = trim($_POST['email']   ?? '');
    $sujet   = trim($_POST['sujet']   ?? '');
    $message = trim($_POST['message'] ?? '');

    if (!$nom || !$email || !$message) {
        $error = tr($is_nl, 'Merci de remplir tous les champs obligatoires.', 'Gelieve alle verplichte velden in te vullen.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = tr($is_nl, 'Adresse email invalide.', 'Ongeldig e-mailadres.');
    } else {
        $html = "<h2>Message reçu via casuffit.be/contact</h2>
        <p><strong>Nom :</strong> ".htmlspecialchars($nom)."</p>
        <p><strong>Email :</strong> ".htmlspecialchars($email)."</p>
        <p><strong>Sujet :</strong> ".htmlspecialchars($sujet)."</p>
        <hr><p>".nl2br(htmlspecialchars($message))."</p>";
        $text = "Nom: $nom\nEmail: $email\nSujet: $sujet\n\n$message";
        try {
            $db = getDB();
            $db->prepare("INSERT INTO contacts (nom,email,sujet,message,statut,created_at) VALUES (?,?,?,?,'nouveau',NOW())")
               ->execute([$nom,$email,$sujet,$message]);
        } catch (Exception $e) {}
        $sent = sendMail('info@casuffit.be', 'Ça suffit !', '✉ Contact casuffit.be : '.($sujet ?: 'Message'), $html, $text);
        if ($sent) {
            sendMail($email, $nom,
                tr($is_nl, 'Votre message a bien été reçu — Ça suffit !', 'Uw bericht is goed ontvangen — Ça suffit !'),
                '<p>'.tr($is_nl, 'Merci pour votre message. Nous reviendrons vers vous dès que possible.', 'Bedankt voor uw bericht. Wij nemen zo snel mogelijk contact met u op.').'</p><p><em>Ça suffit !</em></p>',
                tr($is_nl, 'Merci pour votre message. Nous reviendrons vers vous dès que possible.', 'Bedankt voor uw bericht.')
            );
            $success = true;
        } else {
            $error = tr($is_nl, 'Erreur lors de l\'envoi. Contactez-nous directement : info@casuffit.be', 'Fout bij verzenden. Contacteer ons: info@casuffit.be');
        }
    }
}

$logo_path = __DIR__.'/assets/img/logo.png';
$logo_b64  = file_exists($logo_path) ? base64_encode(file_get_contents($logo_path)) : null;
?>
<!DOCTYPE html>
<html lang="<?= $is_nl ? 'nl' : 'fr' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= tr($is_nl, 'Contact — Ça suffit !', 'Contact — Ça suffit !') ?></title>
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">
<link rel="alternate" hreflang="fr" href="https://www.casuffit.be/contact">
<link rel="alternate" hreflang="nl" href="https://www.casuffit.be/contact?lang=nl">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333;min-height:100vh}

/* Header */
.ct-header{background:linear-gradient(135deg,#0e3d6b,#1673B2);color:#fff;padding:20px;text-align:center}
.ct-logo{display:flex;align-items:center;justify-content:center;gap:12px;margin-bottom:8px}
.ct-logo img{width:52px;height:52px;border-radius:50%;border:2px solid #FF9900}
.ct-logo-name{font-size:1.25rem;font-weight:800}
.ct-logo-name span{color:#FF9900}
.ct-header-sub{font-size:.82rem;color:rgba(255,255,255,.75);line-height:1.5}

/* Conteneur */
.ct-wrap{max-width:660px;margin:0 auto;padding:22px 16px 48px}

/* Cartes */
.ct-card{background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.08);padding:22px 20px;margin-bottom:16px}
.ct-card-title{font-size:.95rem;font-weight:800;color:#0e3d6b;margin-bottom:14px;padding-bottom:8px;border-bottom:2px solid #f0f4f8}

/* Grille liens rapides */
.ct-links{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:18px}
@media(max-width:480px){.ct-links{grid-template-columns:1fr}}
.ct-link{display:flex;align-items:center;gap:12px;padding:14px 16px;border-radius:12px;text-decoration:none;color:#fff;font-weight:700;font-size:.9rem;transition:opacity .18s}
.ct-link:hover{opacity:.88}
.ct-link svg,.ct-link .ct-ic{flex-shrink:0}
.ct-link.ct-email{background:#0e3d6b}
.ct-link.ct-fb{background:#1877f2}
.ct-link.ct-plainte{background:#e67e22;grid-column:1/-1}
.ct-link .ct-sub{font-size:.75rem;font-weight:400;opacity:.85;margin-top:2px}

/* Formulaire */
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media(max-width:480px){.form-row{grid-template-columns:1fr}}
.form-group{margin-bottom:14px}
.form-group label{display:block;font-size:.82rem;font-weight:700;color:#0e3d6b;margin-bottom:4px}
.form-group input,.form-group select,.form-group textarea{
  width:100%;padding:10px 12px;border:1.5px solid #cdd8e5;border-radius:8px;
  font-size:.92rem;font-family:inherit;transition:border-color .2s}
.form-group input:focus,.form-group textarea:focus{outline:none;border-color:#1673B2}
.form-group textarea{min-height:130px;resize:vertical}
.btn-send{width:100%;background:#FF9900;color:#fff;border:none;padding:14px;border-radius:10px;
  font-weight:700;font-size:1rem;cursor:pointer;transition:background .2s}
.btn-send:hover{background:#e08800}
.alert{padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:.9rem}
.alert-ok{background:#e8f8f0;border:1px solid #27ae60;color:#1a6e3c}
.alert-err{background:#fdf0ed;border:1px solid #e74c3c;color:#922b21}

/* Footer */
.ct-footer{text-align:center;padding:20px 16px;font-size:.82rem;color:#999}
.ct-footer a{color:#1673B2;text-decoration:none;font-weight:600}
.ct-footer a:hover{text-decoration:underline}
</style>
</head>
<body>

<div class="ct-header">
  <div class="ct-logo">
    <?php if ($logo_b64): ?>
      <img src="data:image/png;base64,<?= $logo_b64 ?>" alt="Ça suffit !">
    <?php endif; ?>
    <div class="ct-logo-name"><span>Ça suffit !</span></div>
  </div>
  <div class="ct-header-sub"><?= tr($is_nl, 'Nuisances aériennes · Brussels Airport · Piste 01', 'Luchthinder · Brussels Airport · Baan 01') ?></div>
</div>

<div class="ct-wrap">

  <div class="ct-links">
    <a class="ct-link ct-email" href="mailto:info@casuffit.be">
      <span class="ct-ic">✉</span>
      <div><div>info@casuffit.be</div><div class="ct-sub"><?= tr($is_nl,'Par email','Per e-mail') ?></div></div>
    </a>
    <a class="ct-link ct-fb" href="http://www.facebook.com/piste01casuffit" target="_blank" rel="noopener">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="white"><path d="M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.69 4.53-4.69 1.31 0 2.68.24 2.68.24v2.97h-1.51c-1.49 0-1.96.93-1.96 1.89v2.25h3.33l-.53 3.49h-2.8V24C19.61 23.1 24 18.1 24 12.07z"/></svg>
      <div><div>piste01casuffit</div><div class="ct-sub">Facebook</div></div>
    </a>
    <a class="ct-link ct-plainte" href="/plainte.php<?= $is_nl ? '?lang=nl' : '' ?>">
      <span class="ct-ic">⚠️</span>
      <div><div><?= tr($is_nl,'Klacht indienen voor een overvlucht','Porter plainte pour un survol') ?></div><div class="ct-sub"><?= tr($is_nl,'Snel klachtinstrument →','Outil de plainte rapide →') ?></div></div>
    </a>
  </div>

  <div class="ct-card">
    <div class="ct-card-title">📝 <?= tr($is_nl, 'Stuur een bericht', 'Envoyer un message') ?></div>

    <?php if ($success): ?>
      <div class="alert alert-ok">✅ <?= tr($is_nl,
        'Uw bericht is goed verzonden. Wij antwoorden zo snel mogelijk. U heeft een ontvangstbevestiging ontvangen.',
        'Votre message a bien été envoyé. Nous vous répondrons dès que possible. Un accusé de réception vous a été envoyé.') ?></div>
    <?php elseif ($error): ?>
      <div class="alert alert-err">⚠ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST" action="/contact<?= $is_nl ? '?lang=nl' : '' ?>">
      <div class="form-row">
        <div class="form-group">
          <label><?= tr($is_nl,'Naam *','Nom *') ?></label>
          <input type="text" name="nom" required value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>" placeholder="<?= tr($is_nl,'Uw naam','Votre nom') ?>">
        </div>
        <div class="form-group">
          <label><?= tr($is_nl,'E-mail *','Email *') ?></label>
          <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="votre@email.be">
        </div>
      </div>
      <div class="form-group">
        <label><?= tr($is_nl,'Onderwerp','Sujet') ?></label>
        <select name="sujet">
          <?php
          $sujets = $is_nl ? [
            '' => '— Selecteer een onderwerp —',
            'Devenir membre' => 'Lid worden',
            'Signalement de nuisance' => 'Hinder melden',
            'Question juridique' => 'Juridische vraag',
            'Presse / média' => 'Pers / media',
            'Autre' => 'Andere',
          ] : [
            '' => '— Choisir un sujet —',
            'Devenir membre' => 'Devenir membre',
            'Signalement de nuisance' => 'Signalement de nuisance',
            'Question juridique' => 'Question juridique',
            'Presse / média' => 'Presse / média',
            'Autre' => 'Autre',
          ];
          foreach ($sujets as $v => $l) {
            $sel = (($_POST['sujet'] ?? '') === $v) ? ' selected' : '';
            echo '<option value="'.htmlspecialchars($v).'"'.$sel.'>'.htmlspecialchars($l).'</option>';
          }
          ?>
        </select>
      </div>
      <div class="form-group">
        <label><?= tr($is_nl,'Bericht *','Message *') ?></label>
        <textarea name="message" required placeholder="<?= tr($is_nl,'Uw bericht...','Votre message...') ?>"><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
      </div>
      <button type="submit" class="btn-send">📨 <?= tr($is_nl,'Bericht verzenden','Envoyer le message') ?></button>
    </form>
    <?php endif; ?>
  </div>
</div>

<div class="ct-footer">
  <a href="/">← <?= tr($is_nl,'Terug naar de site','Retour au site') ?></a>
  &nbsp;·&nbsp;
  <a href="/contact<?= $is_nl ? '' : '?lang=nl' ?>"><?= $is_nl ? 'FR' : 'NL' ?></a>
  &nbsp;·&nbsp;
  <a href="http://www.facebook.com/piste01casuffit" target="_blank">Facebook</a>
</div>

</body>
</html>
