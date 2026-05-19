<?php
// admin/invite_membre.php — Envoi d'invitations "devenir membre" aux abonnés
require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();
require_once __DIR__ . '/../includes/csrf.php';
$db = getDB();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok'=>false,'error'=>'POST requis']); exit;
}
csrf_verify();

// Charger les fonctions mail depuis newsletter/subscribe.php
require_once __DIR__ . '/../newsletter/subscribe.php';

$ids  = array_filter(array_map('intval', (array)($_POST['ids'] ?? [])));
$mode = $_POST['mode'] ?? 'selected'; // 'selected' | 'all_wix' | 'all_uninvited'

// Construire la liste
if ($mode === 'all_wix') {
    $stmt = $db->query("SELECT id,email,prenom,nom FROM subscribers WHERE source_import LIKE '%wix%' AND statut='actif' AND invite_membre_sent_at IS NULL");
} elseif ($mode === 'all_uninvited') {
    $stmt = $db->query("SELECT id,email,prenom,nom FROM subscribers WHERE statut='actif' AND invite_membre_sent_at IS NULL");
} else {
    if (empty($ids)) { echo json_encode(['ok'=>false,'error'=>'Aucun abonné sélectionné']); exit; }
    $in   = implode(',', $ids);
    $stmt = $db->query("SELECT id,email,prenom,nom FROM subscribers WHERE id IN ($in)");
}
$abonnes = $stmt->fetchAll();

if (empty($abonnes)) {
    echo json_encode(['ok'=>true,'sent'=>0,'msg'=>'Aucun abonné à inviter.']); exit;
}

$sent = 0; $errors = [];

foreach ($abonnes as $ab) {
    // Générer un token unique
    $token = bin2hex(random_bytes(24));

    // Sauvegarder le token
    $db->prepare("UPDATE subscribers SET invite_membre_token=?, invite_membre_sent_at=NOW() WHERE id=?")
       ->execute([$token, $ab['id']]);

    // URL d'invitation
    $url = (defined('SITE_URL') ? SITE_URL : 'https://www.casuffit.be')
         . '/membre/inscription.php?invite=' . $token;

    $prenom = $ab['prenom'] ?: 'abonné(e)';
    $nom    = trim($ab['prenom'].' '.$ab['nom']);

    // Email HTML
    $html = getInviteMembreHtml($prenom, $url, $ab['email']);
    $text = "Bonjour $prenom,\n\nEn tant qu'abonné(e) à la newsletter de Ça suffit ! ASBL, nous vous invitons à créer votre espace membre gratuit.\n\nVotre espace membre vous donnera accès à :\n- Un QR code de paiement personnel avec communication structurée\n- L'historique de vos dons\n- Une communication directe sur l'avancement du combat juridique\n\nCréer mon espace membre (lien valable 30 jours) :\n$url\n\nVous pouvez continuer à recevoir la newsletter sans créer de compte.\n\nL'équipe Ça suffit ! ASBL\ncasuffit.be";

    $ok = false;
    if (!empty(BREVO_API_KEY)) {
        $ok = sendViaBrevo($ab['email'], $nom ?: $ab['email'], $html,
            "Votre espace membre vous attend — Ça suffit ! ASBL", $text);
    } else {
        $ok = sendViaSMTP($ab['email'], $nom ?: $ab['email'],
            "Votre espace membre vous attend — Ça suffit ! ASBL", $html, $text);
    }

    if ($ok) {
        $sent++;
    } else {
        $errors[] = $ab['email'];
        // Annuler le token si échec
        $db->prepare("UPDATE subscribers SET invite_membre_token=NULL, invite_membre_sent_at=NULL WHERE id=?")
           ->execute([$ab['id']]);
    }
}

echo json_encode([
    'ok'     => true,
    'sent'   => $sent,
    'errors' => count($errors),
    'msg'    => "$sent invitation(s) envoyée(s)" . (count($errors) ? ", ".count($errors)." erreur(s)" : ""),
]);

function getInviteMembreHtml(string $prenom, string $url, string $email): string {
    $site_url = defined('SITE_URL') ? SITE_URL : 'https://www.casuffit.be';
    return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f0f4f8;font-family:'Helvetica Neue',Arial,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4f8;padding:30px 0">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:10px;overflow:hidden;max-width:560px">

  <!-- Header -->
  <tr><td style="background:#1673B2;padding:28px 32px;text-align:center">
    <div style="font-size:24px;font-weight:800;color:#FF9900">Ça suffit !</div>
    <div style="font-size:12px;color:rgba(255,255,255,.7);margin-top:4px">ASBL — Piste 01 · UBCNA</div>
  </td></tr>
  <tr><td style="background:#FF9900;height:3px"></td></tr>

  <!-- Body -->
  <tr><td style="padding:32px">
    <p style="font-size:16px;font-weight:700;color:#0e3d6b;margin:0 0 16px">Bonjour {$prenom},</p>
    <p style="font-size:14px;color:#555;line-height:1.6;margin:0 0 20px">
      En tant qu'abonné(e) à notre newsletter, vous faites déjà partie de notre communauté de riverains mobilisés contre les nuisances aériennes de Brussels Airport.
    </p>
    <p style="font-size:14px;color:#555;line-height:1.6;margin:0 0 24px">
      Nous vous invitons à créer votre <strong style="color:#1673B2">espace membre gratuit</strong> qui vous donnera accès à :
    </p>

    <table cellpadding="0" cellspacing="0" style="margin-bottom:24px">
      <tr><td style="padding:6px 0;font-size:13px;color:#333">
        <span style="color:#FF9900;font-weight:700">✓</span>&nbsp; Un <strong>QR code de paiement personnel</strong> avec communication structurée unique
      </td></tr>
      <tr><td style="padding:6px 0;font-size:13px;color:#333">
        <span style="color:#FF9900;font-weight:700">✓</span>&nbsp; L'<strong>historique de vos dons</strong> dans votre espace privé
      </td></tr>
      <tr><td style="padding:6px 0;font-size:13px;color:#333">
        <span style="color:#FF9900;font-weight:700">✓</span>&nbsp; Un <strong>accès sécurisé</strong> par lien magique — sans mot de passe
      </td></tr>
    </table>

    <!-- CTA -->
    <table cellpadding="0" cellspacing="0" width="100%">
      <tr><td align="center" style="padding:8px 0 24px">
        <a href="{$url}" style="display:inline-block;background:#FF9900;color:#fff;text-decoration:none;padding:14px 36px;border-radius:8px;font-weight:700;font-size:15px">
          ✨ Créer mon espace membre
        </a>
      </td></tr>
    </table>

    <p style="font-size:12px;color:#aaa;text-align:center;margin:0 0 8px">Ce lien est valable 30 jours.</p>
    <p style="font-size:11px;color:#ccc;text-align:center;margin:0">
      Vous pouvez continuer à recevoir la newsletter sans créer de compte — c'est entièrement votre choix.
    </p>
  </td></tr>

  <!-- Footer -->
  <tr><td style="background:#f5f7fa;padding:20px 32px;text-align:center;border-top:1px solid #e0e8f0">
    <p style="font-size:11px;color:#aaa;margin:0">
      Ça suffit ! ASBL · <a href="{$site_url}" style="color:#1673B2">casuffit.be</a><br>
      Vous recevez cet email car vous êtes abonné(e) avec <strong>{$email}</strong>
    </p>
  </td></tr>

</table>
</td></tr>
</table>
</body>
</html>
HTML;
}
