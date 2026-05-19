<?php
// includes/mail_helper.php — Fonctions d'envoi email (partagées)
// NE PAS inclure newsletter/subscribe.php directement — il a du code global

if (function_exists('sendViaBrevo')) return; // déjà chargé

function sendViaBrevo(string $to, string $to_name, string $html, string $subject, string $text): bool {
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
        $mail->Subject = $subject; $mail->isHTML(true);
        $mail->Body = $html; $mail->AltBody = $text;
        return $mail->send();
    } catch (\Exception $e) { error_log('Mail: '.$e->getMessage()); return false; }
}

function sendMail(string $to, string $to_name, string $subject, string $html, string $text): bool {
    if (!empty(BREVO_API_KEY)) return sendViaBrevo($to, $to_name, $html, $subject, $text);
    return sendViaSMTP($to, $to_name, $subject, $html, $text);
}
