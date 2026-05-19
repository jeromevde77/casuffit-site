<?php
// newsletter/subscribe.php — Inscription newsletter (appelé par fetch() depuis index.html)
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Méthode non autorisée']);
    exit;
}

// Rate limiting basique
session_start();
$now = time();
$_SESSION['sub_attempts'] = array_filter((isset($_SESSION['sub_attempts']) ? $_SESSION['sub_attempts'] : array()), function($t) use ($now) { return $now - $t < 3600; });
if (count($_SESSION['sub_attempts']) >= 3) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'msg' => 'Trop de tentatives. Réessayez dans une heure.']);
    exit;
}

// Validation
// Honeypot anti-bot : si le champ website est rempli, c'est un bot
if (!empty($_POST['website'])) {
    echo json_encode(['ok' => true, 'msg' => '✓ Inscription enregistrée !']);
    exit;
}

$email    = filter_var(trim((isset($_POST['email']) ? $_POST['email'] : '')), FILTER_VALIDATE_EMAIL);
$prenom   = htmlspecialchars(trim((isset($_POST['prenom']) ? $_POST['prenom'] : '')), ENT_QUOTES, 'UTF-8');
$nom      = htmlspecialchars(trim((isset($_POST['nom']) ? $_POST['nom'] : '')), ENT_QUOTES, 'UTF-8');
$commune  = htmlspecialchars(trim((isset($_POST['commune']) ? $_POST['commune'] : '')), ENT_QUOTES, 'UTF-8');
$telephone = htmlspecialchars(trim((isset($_POST['telephone']) ? $_POST['telephone'] : '')), ENT_QUOTES, 'UTF-8');
$benevole = !empty($_POST['benevole']) ? 1 : 0;
$rgpd     = !empty($_POST['rgpd'])    ? 1 : 0;
$lang     = in_array($_POST['lang'] ?? '', ['fr','nl']) ? $_POST['lang'] : 'fr';

if (!$email) { echo json_encode(['ok' => false, 'msg' => 'Adresse email invalide.']); exit; }
if (!$rgpd)  { echo json_encode(['ok' => false, 'msg' => 'Vous devez accepter la politique RGPD.']); exit; }

try {
    $db = getDB();
    $check = $db->prepare('SELECT id, statut FROM subscribers WHERE email = ?');
    $check->execute([$email]);
    $existing = $check->fetch();

    $token_confirm = bin2hex(random_bytes(32));
    $token_unsub   = bin2hex(random_bytes(32));

    if ($existing) {
        if ($existing['statut'] === 'actif') {
            echo json_encode(['ok' => false, 'msg' => 'Cette adresse est déjà inscrite.']);
            exit;
        }
        $db->prepare("UPDATE subscribers SET prenom=?,nom=?,commune=?,telephone=?,benevole=?,lang=?,
            rgpd_accepte=1,statut='en_attente',token_confirm=?,date_inscription=NOW(),date_confirmation=NULL
            WHERE email=?")->execute([$prenom,$nom,$commune,$telephone,$benevole,$lang,$token_confirm,$email]);
    } else {
        $db->prepare("INSERT INTO subscribers
            (email,prenom,nom,commune,telephone,benevole,lang,rgpd_accepte,statut,token_confirm,token_unsub)
            VALUES (?,?,?,?,?,?,?,1,'en_attente',?,?)")
            ->execute([$email,$prenom,$nom,$commune,$telephone,$benevole,$lang,$token_confirm,$token_unsub]);
    }

    $_SESSION['sub_attempts'][] = $now;

} catch (PDOException $e) {
    error_log('Subscribe error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'msg' => 'Erreur serveur. Veuillez réessayer.']);
    exit;
}

// Envoi email de confirmation
$confirm_url    = SITE_URL . '/newsletter/confirm.php?token=' . $token_confirm;
$prenom_display = $prenom ?: 'cher(e) sympathisant(e)';

$sent = sendConfirmEmail($email, trim("$prenom $nom"), $prenom_display, $confirm_url);

if ($sent) {
    echo json_encode(['ok' => true, 'msg' => '✓ Merci ! Un email de confirmation vous a été envoyé. Vérifiez vos spams si nécessaire.']);
} else {
    // Activer directement si l'envoi échoue
    $db->prepare("UPDATE subscribers SET statut='actif', date_confirmation=NOW() WHERE email=?")->execute([$email]);
    echo json_encode(['ok' => true, 'msg' => '✓ Inscription enregistrée ! (email de confirmation non envoyé)']);
}

// ── Fonctions d'envoi ────────────────────────────────────────────────────
function sendConfirmEmail(string $to, string $to_name, string $prenom_display, string $confirm_url) {
    // Essayer Brevo API en priorité
    if (!empty(BREVO_API_KEY)) {
        return sendViaBrevo($to, $to_name, getConfirmHtml($prenom_display, $confirm_url),
            "Confirmez votre inscription — Piste 01 Ça suffit !",
            "Bonjour $prenom_display,\n\nConfirmez votre inscription : $confirm_url\n\nLien valable 48h.");
    }
    // Sinon PHPMailer SMTP
    return sendViaSMTP($to, $to_name, "Confirmez votre inscription — Piste 01 Ça suffit !",
        getConfirmHtml($prenom_display, $confirm_url),
        "Bonjour $prenom_display,\n\nConfirmez : $confirm_url");
}

function sendViaBrevo(string $to, string $to_name, string $html, string $subject, string $text) {
    $payload = json_encode([
        'sender'      => ['name' => SMTP_FROM_NAME, 'email' => SMTP_FROM],
        'to'          => [['email' => $to, 'name' => $to_name]],
        'subject'     => $subject,
        'htmlContent' => $html,
        'textContent' => $text,
    ]);
    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['accept: application/json','api-key: '.BREVO_API_KEY,'content-type: application/json'],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return $code >= 200 && $code < 300;
}

function sendViaSMTP(string $to, string $to_name, string $subject, string $html, string $text) {
    $pm = __DIR__ . '/../vendor/PHPMailer/PHPMailer.php';
    if (!file_exists($pm)) return false;
    require_once $pm;
    require_once __DIR__ . '/../vendor/PHPMailer/SMTP.php';
    require_once __DIR__ . '/../vendor/PHPMailer/Exception.php';
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP(); $mail->Host = SMTP_HOST; $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER; $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = (SMTP_PORT == 465) ? 'ssl' : 'tls';
        $mail->Port = SMTP_PORT; $mail->CharSet = 'UTF-8';
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($to, $to_name);
        $mail->Subject = $subject; $mail->isHTML(true);
        $mail->Body = $html; $mail->AltBody = $text;
        return $mail->send();
    } catch (\Exception $e) { error_log('Mail error: '.$e->getMessage()); return false; }
}

function getConfirmHtml(string $prenom, string $url) {
    ob_start();
    include __DIR__ . '/../templates/email_confirm.php';
    return ob_get_clean();
}
