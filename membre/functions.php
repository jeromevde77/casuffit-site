<?php
// membre/functions.php — Fonctions utilitaires pour le système membre

// ── Générer un code membre unique ────────────────────────────────────────
function genererCodeMembre($db) {
    $annee = date('Y');
    // Trouver le dernier numéro de l'année
    $stmt = $db->prepare("SELECT code_membre FROM members WHERE code_membre LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute(array("MBR-$annee-%"));
    $last = $stmt->fetchColumn();
    if ($last) {
        $num = intval(substr($last, -5)) + 1;
    } else {
        $num = 1;
    }
    return sprintf("MBR-%s-%05d", $annee, $num);
}

// ── Générer un OGM (communication structurée belge +++) ──────────────────
// Format: +++XXX/XXXX/XXXXX+++ avec chiffre de contrôle modulo 97
function genererOGM($member_id) {
    // Base : 00 + id membre sur 10 chiffres
    $base = str_pad($member_id, 10, '0', STR_PAD_LEFT);
    $part1 = substr($base, 0, 3);
    $part2 = substr($base, 3, 4);
    $part3 = substr($base, 7, 3);

    // Chiffre de contrôle : modulo 97 (0 = 97)
    $number = $part1 . $part2 . $part3;
    $mod = intval($number) % 97;
    $ctrl = $mod == 0 ? 97 : $mod;
    $part3_full = $part3 . str_pad($ctrl, 2, '0', STR_PAD_LEFT);

    return "+++" . $part1 . "/" . $part2 . "/" . $part3_full . "+++";
}


// ── Générer un OGM unique PAR DON (traçabilité) ───────────────────────────
// Format: +++XXX/XXXX/XXXXX+++ basé sur member_id + don_id
function genererOGMDon($member_id, $don_id) {
    // Encode: 3 premiers chiffres = member_id, 4 suivants = don_id, reste = contrôle
    $base = str_pad($member_id, 4, '0', STR_PAD_LEFT) 
          . str_pad($don_id, 6, '0', STR_PAD_LEFT);
    $part1 = substr($base, 0, 3);
    $part2 = substr($base, 3, 4);
    $part3 = substr($base, 7, 3);
    $number = $part1 . $part2 . $part3;
    $mod    = intval($number) % 97;
    $ctrl   = $mod == 0 ? 97 : $mod;
    $part3_full = $part3 . str_pad($ctrl, 2, '0', STR_PAD_LEFT);
    return '+++' . $part1 . '/' . $part2 . '/' . $part3_full . '+++';
}

// ── Générer un token magique sécurisé ────────────────────────────────────
function genererTokenMagic() {
    return bin2hex(random_bytes(32));
}

// ── Log des envois email ─────────────────────────────────────────────────
function logMail($to, $subject, $result, $error = '') {
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);
    $line = '[' . date('Y-m-d H:i:s') . '] '
          . ($result ? 'OK' : 'ERREUR') . ' '
          . "to=$to subject=\"$subject\""
          . ($error ? " erreur=$error" : '')
          . PHP_EOL;
    file_put_contents($log_dir . '/mail.log', $line, FILE_APPEND);
}

// getMembre() et requireMembre() sont définies dans config.php

// ── Envoyer le lien magique ──────────────────────────────────────────────
function envoyerLienMagique($db, $membre) {
    $token   = genererTokenMagic();
    $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
    $db->prepare("UPDATE members SET token_magic=?, token_magic_exp=? WHERE id=?")
       ->execute(array($token, $expires, $membre['id']));

    $magic_url     = SITE_URL . '/membre/magic.php?token=' . $token;
    $prenom        = $membre['prenom'] ?: 'membre';
    $code_membre   = $membre['code_membre'];

    // Template email
    ob_start();
    include __DIR__ . '/../templates/email_magic.php';
    $html = ob_get_clean();

    $text = "Bonjour $prenom,\n\nVoici votre lien de connexion (valable 24h) :\n$magic_url\n\nVotre code membre : $code_membre\n\nL'équipe ça suffit ! ASBL";

    // Envoi
    if (!empty(BREVO_API_KEY)) {
        return envoyerViaBrevo($membre['email'], trim($membre['prenom'].' '.$membre['nom']), "Votre accès espace membre — ça suffit ! ASBL", $html, $text);
    }
    return envoyerViaSMTP($membre['email'], trim($membre['prenom'].' '.$membre['nom']), "Votre accès espace membre — ça suffit ! ASBL", $html, $text);
}

// ── Envoi Brevo ──────────────────────────────────────────────────────────
function envoyerViaBrevo($to, $name, $subject, $html, $text) {
    $payload = json_encode(array(
        'sender'      => array('name' => SMTP_FROM_NAME, 'email' => SMTP_FROM),
        'to'          => array(array('email' => $to, 'name' => $name)),
        'subject'     => $subject,
        'htmlContent' => $html,
        'textContent' => $text,
    ));
    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => array('accept: application/json', 'api-key: '.BREVO_API_KEY, 'content-type: application/json'),
        CURLOPT_TIMEOUT        => 15,
    ));
    $resp     = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err = curl_error($ch);
    // curl_close() supprimé — déprécié depuis PHP 8.0
    $ok  = ($code >= 200 && $code < 300);
    $err = $curl_err ? $curl_err : ($ok ? '' : "HTTP $code: $resp");
    logMail($to, $subject, $ok, $err);
    return $ok;
}

// ── Envoi SMTP ───────────────────────────────────────────────────────────
function envoyerViaSMTP($to, $name, $subject, $html, $text) {
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
        $mail->addAddress($to, $name);
        $mail->Subject = $subject; $mail->isHTML(true);
        $mail->Body = $html; $mail->AltBody = $text;
        $mail->send();
        logMail($to, $subject, true);
        return true;
    } catch (\Exception $e) {
        error_log('Mail error: ' . $e->getMessage());
        logMail($to, $subject, false, $e->getMessage());
        return false;
    }
}
