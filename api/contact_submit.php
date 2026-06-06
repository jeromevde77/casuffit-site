<?php
// api/contact_submit.php — Endpoint AJAX soumission formulaire de contact
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/mail_helper.php';
header('Content-Type: application/json; charset=utf-8');

// ── Rate limiting : max 5 soumissions par IP par heure ────────────────
$ip      = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ip_key  = 'contact_rl_' . md5($ip);
if (session_status() === PHP_SESSION_NONE) session_start();
$rl      = $_SESSION[$ip_key] ?? ['count' => 0, 'since' => time()];
if (time() - $rl['since'] > 3600) { $rl = ['count' => 0, 'since' => time()]; }
if ($rl['count'] >= 5) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'message' => 'Trop de messages envoyés. Réessayez dans une heure.']);
    exit;
}
$rl['count']++;
$_SESSION[$ip_key] = $rl;

$is_nl = (($_POST['lang'] ?? 'fr') === 'nl');
function tr(bool $nl, string $fr, string $nls): string { return $nl ? $nls : $fr; }

$nom     = trim($_POST['nom']     ?? '');
$email   = trim($_POST['email']   ?? '');
$sujet   = trim($_POST['sujet']   ?? '');
$message = trim($_POST['message'] ?? '');

if (!$nom || !$email || !$message) {
    echo json_encode(['ok'=>false,'message'=>tr($is_nl,'Gelieve alle verplichte velden in te vullen.','Merci de remplir tous les champs obligatoires.')]);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok'=>false,'message'=>tr($is_nl,'Ongeldig e-mailadres.','Adresse email invalide.')]);
    exit;
}

// Sauvegarde en base
try {
    $db = getDB();
    $db->prepare("INSERT INTO contacts (nom,email,sujet,message,statut,created_at) VALUES (?,?,?,?,'nouveau',NOW())")
       ->execute([$nom,$email,$sujet,$message]);
} catch (Exception $e) {}

// Email admin
$html = "<h2>Message via casuffit.be</h2>
<p><strong>Nom :</strong> ".htmlspecialchars($nom)."</p>
<p><strong>Email :</strong> ".htmlspecialchars($email)."</p>
<p><strong>Sujet :</strong> ".htmlspecialchars($sujet)."</p>
<hr><p>".nl2br(htmlspecialchars($message))."</p>";
$text = "Nom: $nom\nEmail: $email\nSujet: $sujet\n\n$message";

$admin_to = trim(cfg('alerte_contact_email', '')) ?: trim(cfg('site_email', 'info@casuffit.be'));
$sent = sendMail($admin_to, 'Ça suffit !',
    '✉ Contact : '.($sujet ?: 'Message de '.$nom), $html, $text);

if ($sent) {
    // Accusé de réception
    sendMail($email, $nom,
        tr($is_nl, 'Uw bericht is goed ontvangen — Ça suffit !', 'Votre message a bien été reçu — Ça suffit !'),
        '<p>'.tr($is_nl,'Bedankt voor uw bericht. Wij nemen zo snel mogelijk contact met u op.','Merci pour votre message. Nous reviendrons vers vous dès que possible.').'</p><p><em>Ça suffit !</em></p>',
        tr($is_nl,'Bedankt voor uw bericht.','Merci pour votre message.')
    );
    echo json_encode(['ok'=>true,'message'=>tr($is_nl,
        'Uw bericht is goed verzonden. U ontvangt een bevestiging per e-mail.',
        'Votre message a bien été envoyé. Vous recevrez un accusé de réception par email.')]);
} else {
    echo json_encode(['ok'=>false,'message'=>tr($is_nl,
        'Fout bij verzenden. Contacteer ons: info@casuffit.be',
        'Erreur d\'envoi. Contactez-nous directement : info@casuffit.be')]);
}
