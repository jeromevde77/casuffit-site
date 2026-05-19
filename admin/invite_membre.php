<?php
// admin/invite_membre.php — Envoi d'invitations "devenir membre" aux abonnés
require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/mail_helper.php';

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok'=>false,'error'=>'POST requis']); exit;
}

// Vérification CSRF
$submitted = $_POST['_csrf'] ?? '';
$expected  = $_SESSION['_csrf_token'] ?? '';
if (!$expected || !$submitted || !hash_equals($expected, $submitted)) {
    echo json_encode(['ok'=>false,'error'=>'Token CSRF invalide']); exit;
}

$db = getDB();

// Vérifier que les colonnes existent
try {
    $db->query("SELECT invite_membre_token FROM subscribers LIMIT 1");
} catch (Exception $e) {
    echo json_encode(['ok'=>false,'error'=>'Migration SQL non exécutée. Ajoutez les colonnes invite_membre_* dans la table subscribers.']); exit;
}

$ids  = array_filter(array_map('intval', (array)($_POST['ids'] ?? [])));
if (empty($ids)) { echo json_encode(['ok'=>false,'error'=>'Aucun abonné sélectionné']); exit; }

$in      = implode(',', $ids);
$abonnes = $db->query("SELECT id,email,prenom,nom FROM subscribers WHERE id IN ($in) AND statut='actif'")->fetchAll();

if (empty($abonnes)) {
    echo json_encode(['ok'=>false,'error'=>'Aucun abonné actif trouvé dans la sélection']); exit;
}

$site_url = defined('SITE_URL') ? SITE_URL : 'https://www.casuffit.be';
$sent = 0; $skipped = 0; $errors = [];

foreach ($abonnes as $ab) {
    $token = bin2hex(random_bytes(24));

    try {
        $db->prepare("UPDATE subscribers SET invite_membre_token=?, invite_membre_sent_at=NOW() WHERE id=?")
           ->execute([$token, $ab['id']]);
    } catch (Exception $e) {
        $errors[] = $ab['email'] . ' (db)';
        continue;
    }

    $url    = $site_url . '/membre/inscription.php?invite=' . $token;
    $prenom = $ab['prenom'] ?: '';
    $nom    = trim($ab['prenom'].' '.$ab['nom']);
    $html   = buildInviteHtml($prenom, $url, $ab['email'], $site_url);
    $text   = buildInviteText($prenom, $url, $ab['email']);
    $subj   = 'Votre espace membre vous attend — Ça suffit ! ASBL';

    $ok = sendMail($ab['email'], $nom ?: $ab['email'], $subj, $html, $text);

    if ($ok) {
        $sent++;
    } else {
        $errors[] = $ab['email'];
        $db->prepare("UPDATE subscribers SET invite_membre_token=NULL, invite_membre_sent_at=NULL WHERE id=?")->execute([$ab['id']]);
    }
}

$msg = "$sent invitation(s) envoyée(s)";
if ($skipped) $msg .= ", $skipped déjà invités";
if (count($errors)) $msg .= ", ".count($errors)." erreur(s) : ".implode(', ', array_slice($errors,0,3));

echo json_encode(['ok'=>true, 'sent'=>$sent, 'errors'=>count($errors), 'msg'=>$msg]);

// ── Templates email ──────────────────────────────────────────────────────────
function buildInviteHtml(string $prenom, string $url, string $email, string $site_url): string {
    $salut = $prenom ? "Bonjour $prenom," : "Bonjour,";
    return <<<HTML
<!DOCTYPE html><html lang="fr">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f0f4f8;font-family:'Helvetica Neue',Arial,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4f8;padding:30px 0">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:10px;overflow:hidden;max-width:560px">
  <tr><td style="background:#1673B2;padding:28px 32px;text-align:center">
    <div style="font-size:24px;font-weight:800;color:#FF9900">Ça suffit !</div>
    <div style="font-size:12px;color:rgba(255,255,255,.7);margin-top:4px">ASBL — Stop aux nuisances aériennes de Brussels Airport</div>
  </td></tr>
  <tr><td style="background:#FF9900;height:3px"></td></tr>
  <tr><td style="padding:32px">
    <p style="font-size:16px;font-weight:700;color:#0e3d6b;margin:0 0 16px">$salut</p>
    <p style="font-size:14px;color:#555;line-height:1.6;margin:0 0 16px">En tant qu'abonné(e) à notre newsletter, vous faites déjà partie de notre communauté de riverains mobilisés contre les nuisances aériennes de Brussels Airport.</p>
    <p style="font-size:14px;color:#555;line-height:1.6;margin:0 0 20px">Nous vous invitons à créer votre <strong style="color:#1673B2">espace membre gratuit</strong> :</p>
    <table cellpadding="0" cellspacing="0" style="margin-bottom:24px">
      <tr><td style="padding:5px 0;font-size:13px;color:#333"><span style="color:#FF9900;font-weight:700">✓</span>&nbsp; <strong>QR code de paiement personnel</strong> avec communication structurée unique</td></tr>
      <tr><td style="padding:5px 0;font-size:13px;color:#333"><span style="color:#FF9900;font-weight:700">✓</span>&nbsp; <strong>Historique de vos dons</strong> dans votre espace privé sécurisé</td></tr>
      <tr><td style="padding:5px 0;font-size:13px;color:#333"><span style="color:#FF9900;font-weight:700">✓</span>&nbsp; <strong>Accès sans mot de passe</strong> par lien magique</td></tr>
    </table>
    <table cellpadding="0" cellspacing="0" width="100%">
      <tr><td align="center" style="padding:8px 0 20px">
        <a href="$url" style="display:inline-block;background:#FF9900;color:#fff;text-decoration:none;padding:14px 36px;border-radius:8px;font-weight:700;font-size:15px">✨ Créer mon espace membre</a>
      </td></tr>
    </table>
    <p style="font-size:11px;color:#bbb;text-align:center;margin:0 0 6px">Lien valable 30 jours · Vous pouvez ignorer cet email et rester simplement abonné à la newsletter.</p>
  </td></tr>
  <tr><td style="background:#f5f7fa;padding:16px 32px;text-align:center;border-top:1px solid #e0e8f0">
    <p style="font-size:11px;color:#aaa;margin:0">Ça suffit ! ASBL · <a href="$site_url" style="color:#1673B2">casuffit.be</a><br>Vous recevez cet email car vous êtes abonné(e) avec <strong>$email</strong></p>
  </td></tr>
</table>
</td></tr>
</table>
</body></html>
HTML;
}

function buildInviteText(string $prenom, string $url, string $email): string {
    $salut = $prenom ? "Bonjour $prenom," : "Bonjour,";
    return "$salut\n\nNous vous invitons à créer votre espace membre gratuit sur casuffit.be.\n\nAvantages :\n- QR code de paiement personnel\n- Historique de vos dons\n- Accès sans mot de passe\n\nCréer mon espace membre (30 jours) :\n$url\n\nVous pouvez ignorer cet email et rester abonné à la newsletter.\n\nL'équipe Ça suffit ! ASBL";
}
