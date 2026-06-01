<?php
// contact.php — Page de contact Ça suffit !
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/mail_helper.php';
require_once __DIR__ . '/membre/functions.php';
session_start();

$lang = ($_GET['lang'] ?? 'fr') === 'nl' ? 'nl' : 'fr';
$is_nl = ($lang === 'nl');
function tr(bool $nl, string $fr, string $nls): string { return $nl ? $nls : $fr; }

$success = false;
$error   = '';

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
        $html = "
        <h2>Message reçu via casuffit.be/contact</h2>
        <p><strong>Nom :</strong> ".htmlspecialchars($nom)."</p>
        <p><strong>Email :</strong> ".htmlspecialchars($email)."</p>
        <p><strong>Sujet :</strong> ".htmlspecialchars($sujet)."</p>
        <hr>
        <p>".nl2br(htmlspecialchars($message))."</p>";
        $text = "Nom: $nom\nEmail: $email\nSujet: $sujet\n\n$message";
        $sent = sendMail(ADMIN_EMAIL, 'Ça suffit !', '✉ Contact casuffit.be : '.($sujet ?: 'Message'), $html, $text);
        if ($sent) {
            // Accusé de réception
            sendMail($email, $nom,
                tr($is_nl, 'Votre message a bien été reçu — Ça suffit !', 'Uw bericht is goed ontvangen — Ça suffit !'),
                '<p>'.tr($is_nl, 'Merci pour votre message. Nous reviendrons vers vous dès que possible.', 'Bedankt voor uw bericht. Wij nemen zo snel mogelijk contact met u op.').'</p><p><em>Ça suffit !</em></p>',
                tr($is_nl, 'Merci pour votre message. Nous reviendrons vers vous dès que possible.', 'Bedankt voor uw bericht.')
            );
            $success = true;
        } else {
            $error = tr($is_nl, 'Erreur lors de l\'envoi. Contactez-nous directement par email.', 'Fout bij het verzenden. Contacteer ons rechtstreeks per e-mail.');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $is_nl ? 'nl' : 'fr' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= tr($is_nl, 'Contact — Ça suffit !', 'Contact — Ça suffit !') ?></title>
<link rel="stylesheet" href="/assets/css/content.css">
<style>
.contact-wrap { max-width: 680px; margin: 0 auto; padding: 24px 16px 48px; }
.contact-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 32px; }
@media(max-width:520px){ .contact-grid { grid-template-columns: 1fr; } }
.contact-card { background: var(--bleu-hex, #0e3d6b); color: #fff; border-radius: 12px; padding: 20px; text-align: center; text-decoration: none; display: flex; flex-direction: column; align-items: center; gap: 8px; transition: opacity .2s; }
.contact-card:hover { opacity: .85; }
.contact-card .cc-icon { font-size: 2rem; }
.contact-card .cc-label { font-size: .75rem; opacity: .8; }
.contact-card .cc-val { font-weight: 700; font-size: .95rem; word-break: break-all; }
.contact-card.fb { background: #1877f2; }
.contact-card.plainte { background: #e67e22; }
.contact-card.email { background: var(--bleu-hex, #0e3d6b); }
.form-group { margin-bottom: 14px; }
.form-group label { display: block; font-size: .85rem; font-weight: 700; color: var(--bleu-hex,#0e3d6b); margin-bottom: 4px; }
.form-group input, .form-group select, .form-group textarea {
  width: 100%; padding: 10px 12px; border: 1.5px solid #cdd8e5; border-radius: 8px;
  font-size: .92rem; font-family: inherit; box-sizing: border-box; transition: border-color .2s;
}
.form-group input:focus, .form-group textarea:focus { outline: none; border-color: var(--bleu-hex,#0e3d6b); }
.form-group textarea { min-height: 130px; resize: vertical; }
.btn-send { width: 100%; background: var(--orange-hex, #FF9900); color: #fff; border: none;
  padding: 14px; border-radius: 10px; font-weight: 700; font-size: 1rem; cursor: pointer; transition: background .2s; }
.btn-send:hover { background: #e88800; }
.alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; font-size: .9rem; }
.alert-ok  { background: #e8f8f0; border: 1px solid #27ae60; color: #1a6e3c; }
.alert-err { background: #fdf0ed; border: 1px solid #e74c3c; color: #922b21; }
h2.section-title { font-size: 1.05rem; color: var(--bleu-hex,#0e3d6b); border-bottom: 2px solid var(--orange-hex,#FF9900); padding-bottom: 6px; margin: 28px 0 16px; }
</style>
</head>
<body>
<?php include __DIR__.'/includes/header.php'; ?>

<div class="contact-wrap">
  <h1 style="color:var(--bleu-hex,#0e3d6b);font-size:1.6rem;margin-bottom:6px">
    <?= tr($is_nl, '📬 Nous contacter', '📬 Contacteer ons') ?>
  </h1>
  <p style="color:#666;margin-bottom:24px;font-size:.92rem">
    <?= tr($is_nl,
      'Une question, une remarque, vous voulez rejoindre l\'association ou signaler un survol ? Contactez-nous.',
      'Een vraag, opmerking, wil je lid worden of een vluchthinder melden? Neem contact met ons op.') ?>
  </p>

  <!-- Cartes de contact rapide -->
  <div class="contact-grid">
    <a class="contact-card email" href="mailto:<?= ADMIN_EMAIL ?>">
      <span class="cc-icon">✉</span>
      <span class="cc-label"><?= tr($is_nl, 'Par email', 'Per e-mail') ?></span>
      <span class="cc-val"><?= ADMIN_EMAIL ?></span>
    </a>
    <a class="contact-card fb" href="http://www.facebook.com/piste01casuffit" target="_blank" rel="noopener">
      <span class="cc-icon">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="white"><path d="M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.69 4.53-4.69 1.31 0 2.68.24 2.68.24v2.97h-1.51c-1.49 0-1.96.93-1.96 1.89v2.25h3.33l-.53 3.49h-2.8V24C19.61 23.1 24 18.1 24 12.07z"/></svg>
      </span>
      <span class="cc-label">Facebook</span>
      <span class="cc-val">piste01casuffit</span>
    </a>
    <a class="contact-card plainte" href="/plainte.php<?= $is_nl ? '?lang=nl' : '' ?>" style="grid-column:1/-1">
      <span class="cc-icon">⚠️</span>
      <span class="cc-val"><?= tr($is_nl, 'Porter plainte pour un survol', 'Klacht indienen voor een overvlucht') ?></span>
      <span class="cc-label"><?= tr($is_nl, 'Outil de plainte rapide →', 'Snel klachtinstrument →') ?></span>
    </a>
  </div>

  <!-- Formulaire -->
  <h2 class="section-title"><?= tr($is_nl, 'Envoyer un message', 'Stuur een bericht') ?></h2>

  <?php if ($success): ?>
    <div class="alert alert-ok">
      ✅ <?= tr($is_nl,
        'Votre message a bien été envoyé. Nous vous répondrons dès que possible. Un accusé de réception vous a été envoyé.',
        'Uw bericht is goed verzonden. Wij antwoorden zo snel mogelijk. U heeft een ontvangstbevestiging ontvangen.') ?>
    </div>
  <?php elseif ($error): ?>
    <div class="alert alert-err">⚠ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if (!$success): ?>
  <form method="POST" action="/contact.php<?= $is_nl ? '?lang=nl' : '' ?>">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
      <div class="form-group">
        <label><?= tr($is_nl, 'Nom *', 'Naam *') ?></label>
        <input type="text" name="nom" required value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>" placeholder="<?= tr($is_nl, 'Votre nom', 'Uw naam') ?>">
      </div>
      <div class="form-group">
        <label><?= tr($is_nl, 'Email *', 'E-mail *') ?></label>
        <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="votre@email.be">
      </div>
    </div>
    <div class="form-group">
      <label><?= tr($is_nl, 'Sujet', 'Onderwerp') ?></label>
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
          echo "<option value=\"".htmlspecialchars($v)."\"$sel>".htmlspecialchars($l)."</option>";
        }
        ?>
      </select>
    </div>
    <div class="form-group">
      <label><?= tr($is_nl, 'Message *', 'Bericht *') ?></label>
      <textarea name="message" required placeholder="<?= tr($is_nl, 'Votre message...', 'Uw bericht...') ?>"><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
    </div>
    <button type="submit" class="btn-send">
      <?= tr($is_nl, '📨 Envoyer le message', '📨 Bericht verzenden') ?>
    </button>
  </form>
  <?php endif; ?>
</div>

<?php include __DIR__.'/includes/footer.php'; ?>
</body>
</html>
