<?php
// includes/mail_helper.php — Fonctions d'envoi email (partagées)
// NE PAS inclure newsletter/subscribe.php directement — il a du code global

if (function_exists('sendViaBrevo')) return; // déjà chargé

function sendViaBrevo(string $to, string $to_name, string $html, string $subject, string $text): bool {
    $payload = [
        'sender'      => ['name' => SMTP_FROM_NAME, 'email' => SMTP_FROM],
        'to'          => [['email' => $to, 'name' => $to_name]],
        'subject'     => $subject,
        'htmlContent' => $html,
        'textContent' => $text,
    ];
    // BCC admin si configuré (plusieurs adresses séparées par virgule)
    $bcc = function_exists('cfg') ? cfg('admin_bcc', '') : '';
    if ($bcc) {
        $bcc_list = array_filter(array_map('trim', explode(',', $bcc)), fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL));
        if ($bcc_list) $payload['bcc'] = array_map(fn($e) => ['email' => $e], array_values($bcc_list));
    }
    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['accept: application/json','api-key: '.BREVO_API_KEY,'content-type: application/json'],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code >= 200 && $code < 300;
}

function sendViaSMTP(string $to, string $to_name, string $subject, string $html, string $text): bool {
    $pm = __DIR__ . '/../vendor/PHPMailer/PHPMailer.php';
    if (!file_exists($pm)) { error_log('PHPMailer absent'); return false; }
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
        // BCC admin si configuré (plusieurs adresses séparées par virgule)
        $bcc = function_exists('cfg') ? cfg('admin_bcc', '') : '';
        if ($bcc) {
            foreach (array_filter(array_map('trim', explode(',', $bcc)), fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL)) as $bcc_email) {
                $mail->addBCC($bcc_email);
            }
        }
        $mail->Subject = $subject; $mail->isHTML(true);
        $mail->Body = $html; $mail->AltBody = $text;
        return $mail->send();
    } catch (\Exception $e) { error_log('Mail: '.$e->getMessage()); return false; }
}

function sendMail(string $to, string $to_name, string $subject, string $html, string $text): bool {
    if (!empty(BREVO_API_KEY)) return sendViaBrevo($to, $to_name, $html, $subject, $text);
    return sendViaSMTP($to, $to_name, $subject, $html, $text);
}

/**
 * Envoi avec tracking d'ouverture par campagne.
 * Crée une entrée dans email_opens, insère un pixel invisible dans le HTML,
 * puis envoie. Si le tracking échoue, l'email part quand même (sans pixel).
 *
 * @param string $campagne  slug de campagne (ex: 'invite_wix', 'rappel_adresse')
 */
function sendMailTracked(string $to, string $to_name, string $subject, string $html, string $text, string $campagne): bool {
    $site_url = defined('SITE_URL') ? SITE_URL : 'https://www.casuffit.be';
    try {
        $db = getDB();
        $token = bin2hex(random_bytes(16)); // 32 chars hex
        $db->prepare("INSERT INTO email_opens (campagne, email, token) VALUES (?,?,?)")
           ->execute([$campagne, $to, $token]);
        // Insérer le pixel juste avant </body> (ou à la fin si absent)
        $pixel = '<img src="' . $site_url . '/api/open.php?t=' . $token . '" width="1" height="1" alt="" style="display:none;border:0;width:1px;height:1px" />';
        if (stripos($html, '</body>') !== false) {
            $html = preg_replace('#</body>#i', $pixel . '</body>', $html, 1);
        } else {
            $html .= $pixel;
        }
    } catch (Throwable $e) {
        error_log('sendMailTracked (tracking ignoré): ' . $e->getMessage());
        // On continue : l'email part sans pixel
    }
    return sendMail($to, $to_name, $subject, $html, $text);
}
