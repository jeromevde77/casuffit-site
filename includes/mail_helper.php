<?php
// includes/mail_helper.php — Fonctions d'envoi email (partagées)
// NE PAS inclure newsletter/subscribe.php directement — il a du code global

if (function_exists('sendViaBrevo')) return; // déjà chargé

function sendViaBrevo(string $to, string $to_name, string $html, string $subject, string $text, array $attachments = []): bool {
    $payload = [
        'sender'      => ['name' => SMTP_FROM_NAME, 'email' => SMTP_FROM],
        'to'          => [['email' => $to, 'name' => $to_name]],
        'subject'     => $subject,
        'htmlContent' => $html,
        'textContent' => $text,
    ];
    if (!empty($attachments)) {
        $payload['attachment'] = [];
        foreach ($attachments as $a) {
            $payload['attachment'][] = ['name' => $a['name'], 'content' => base64_encode($a['content'])];
        }
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

function sendViaSMTP(string $to, string $to_name, string $subject, string $html, string $text, array $attachments = []): bool {
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
        foreach ($attachments as $a) { $mail->addStringAttachment($a['content'], $a['name']); }
        $mail->Subject = $subject; $mail->isHTML(true);
        $mail->Body = $html; $mail->AltBody = $text;
        return $mail->send();
    } catch (\Exception $e) { error_log('Mail: '.$e->getMessage()); return false; }
}

function sendMail(string $to, string $to_name, string $subject, string $html, string $text, array $attachments = []): bool {
    if (!empty(BREVO_API_KEY)) return sendViaBrevo($to, $to_name, $html, $subject, $text, $attachments);
    return sendViaSMTP($to, $to_name, $subject, $html, $text, $attachments);
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

/**
 * Envoie un e-mail de remerciement (bilingue FR/NL) au membre dont le don
 * vient d'être confirmé. À appeler à chaque passage d'un don au statut
 * « confirme » (confirmation manuelle ou import CODA).
 *
 * Idempotent : grâce aux colonnes merci_envoye / merci_date sur member_dons,
 * un même don n'est jamais remercié deux fois. N'envoie que si le don est
 * confirmé ET que l'email du membre est connu (« si on le connaît »).
 *
 * @return bool true si un e-mail a effectivement été envoyé
 */
function sendDonMerci(PDO $db, int $donId): bool {
    if ($donId <= 0) return false;

    // Colonnes de suivi présentes ? (migration migrate_don_merci.sql)
    static $hasMerci = null;
    if ($hasMerci === null) {
        try { $hasMerci = (bool) $db->query("SHOW COLUMNS FROM member_dons LIKE 'merci_envoye'")->fetch(); }
        catch (Throwable $e) { $hasMerci = false; }
    }

    $sql = "SELECT d.id, d.montant, d.communication, d.ogm_don, d.date_don, d.statut"
         . ($hasMerci ? ", d.merci_envoye" : "")
         . ", m.prenom, m.nom, m.email
            FROM member_dons d JOIN members m ON m.id = d.member_id
            WHERE d.id = ? LIMIT 1";
    $st = $db->prepare($sql);
    $st->execute([$donId]);
    $d = $st->fetch(PDO::FETCH_ASSOC);

    if (!$d)                                       return false; // don/membre introuvable
    if ($d['statut'] !== 'confirme')               return false; // uniquement les dons confirmés
    if (empty($d['email']))                        return false; // « si on le connaît »
    if ($hasMerci && !empty($d['merci_envoye']))   return false; // déjà remercié

    $prenom  = trim($d['prenom'] ?? '');
    $helloFr = $prenom !== '' ? htmlspecialchars($prenom) : 'cher donateur';
    $helloNl = $prenom !== '' ? htmlspecialchars($prenom) : 'beste schenker';
    $montant = number_format((float) $d['montant'], 2, ',', ' ') . ' €';
    $dateDon = date('d/m/Y', strtotime($d['date_don']));
    $comm    = $d['ogm_don'] ?: ($d['communication'] ?: '—');
    $url     = defined('SITE_URL') ? SITE_URL : 'https://www.casuffit.be';

    $sujet = 'Merci pour votre don 🙏 — Bedankt voor uw gift — Ça suffit !';

    $recap_fr = "<div style='background:#f0f7ff;border-left:4px solid #1673B2;padding:14px 18px;border-radius:0 8px 8px 0;margin:18px 0;font-size:.92rem;line-height:1.8'>"
              . "<strong>Montant :</strong> ".htmlspecialchars($montant)."<br>"
              . "<strong>Date :</strong> ".htmlspecialchars($dateDon)."<br>"
              . "<strong>Communication :</strong> <code>".htmlspecialchars($comm)."</code></div>";
    $recap_nl = "<div style='background:#f0f7ff;border-left:4px solid #1673B2;padding:14px 18px;border-radius:0 8px 8px 0;margin:18px 0;font-size:.92rem;line-height:1.8'>"
              . "<strong>Bedrag:</strong> ".htmlspecialchars($montant)."<br>"
              . "<strong>Datum:</strong> ".htmlspecialchars($dateDon)."<br>"
              . "<strong>Mededeling:</strong> <code>".htmlspecialchars($comm)."</code></div>";

    $html = "
<p>Bonjour ".$helloFr.",</p>
<p>Un grand <strong>merci</strong> ! Nous confirmons la bonne réception de votre don de <strong>".htmlspecialchars($montant)."</strong> en faveur de l'ASBL <em>Ça suffit !</em>.</p>
<p>Grâce à vous, nous pouvons poursuivre le combat contre les nuisances aériennes de la piste 01 : expertise juridique, mesures de bruit et mobilisation citoyenne.</p>
".$recap_fr."
<p>Vous retrouvez l'historique de vos dons dans votre <a href='".$url."/membre/dashboard.php' style='color:#1673B2'>espace membre</a>.</p>
<p>Encore merci pour votre engagement à nos côtés.</p>
<p><em>L'équipe Ça suffit !<br><a href='".$url."' style='color:#1673B2'>casuffit.be</a></em></p>
<hr style='border:none;border-top:1px solid #e0e0e0;margin:26px 0'>
<p>Beste ".$helloNl.",</p>
<p>Hartelijk <strong>dank</strong>! We bevestigen de goede ontvangst van uw gift van <strong>".htmlspecialchars($montant)."</strong> aan de vzw <em>Ça suffit !</em>.</p>
<p>Dankzij u kunnen we de strijd tegen de geluidshinder van baan 01 voortzetten: juridische expertise, geluidsmetingen en burgermobilisatie.</p>
".$recap_nl."
<p>U vindt het overzicht van uw giften terug in uw <a href='".$url."/membre/dashboard.php' style='color:#1673B2'>ledenruimte</a>.</p>
<p>Nogmaals bedankt voor uw engagement aan onze zijde.</p>
<p><em>Het team van Ça suffit !<br><a href='".$url."' style='color:#1673B2'>casuffit.be</a></em></p>";

    $text = "Bonjour $prenom,\n\nUn grand merci ! Nous confirmons la bonne reception de votre don de $montant en faveur de l'ASBL Ca suffit !.\n"
          . "Montant : $montant | Date : $dateDon | Communication : $comm\n"
          . "Historique de vos dons : $url/membre/dashboard.php\n\nMerci pour votre engagement,\nL'equipe Ca suffit !\n\n"
          . "-----\n\nBeste $prenom,\n\nHartelijk dank! We bevestigen de goede ontvangst van uw gift van $montant aan de vzw Ca suffit !.\n"
          . "Bedrag: $montant | Datum: $dateDon | Mededeling: $comm\nOverzicht van uw giften: $url/membre/dashboard.php\n\nBedankt voor uw engagement,\nHet team van Ca suffit !";

    $ok = sendMail($d['email'], trim($prenom.' '.($d['nom'] ?? '')), $sujet, $html, $text);

    if ($ok && $hasMerci) {
        try { $db->prepare("UPDATE member_dons SET merci_envoye=1, merci_date=NOW() WHERE id=?")->execute([$donId]); }
        catch (Throwable $e) { error_log('sendDonMerci flag: '.$e->getMessage()); }
    }
    return $ok;
}

/**
 * Envoie un e-mail libre (objet + message) à un membre depuis l'admin, puis
 * journalise l'envoi dans member_emails (table optionnelle). Met en forme le
 * message (sauts de ligne conservés) + signature. Pas de copie BCC.
 *
 * @param array       $member  Ligne members (id, prenom, nom, email)
 * @param string|null $par     Identifiant de l'admin expéditeur (pour l'historique)
 * @return bool true si l'e-mail est parti
 */
function sendMemberEmail(PDO $db, array $member, string $sujet, string $message, ?string $par = null, array $attachments = []): bool {
    $email = trim($member['email'] ?? '');
    $sujet = trim($sujet); $message = trim($message);
    if ($email === '' || $sujet === '' || $message === '') return false;

    $html = "<div style='font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#333;line-height:1.6'>"
          . nl2br(htmlspecialchars($message))
          . "<hr style='border:none;border-top:1px solid #e0e0e0;margin:22px 0'>"
          . "<p style='font-size:13px;color:#777'>L'équipe Ça suffit !<br><a href='https://www.casuffit.be' style='color:#1673B2'>casuffit.be</a></p></div>";
    $text = $message . "\n\n-- \nL'equipe Ca suffit !\nhttps://www.casuffit.be";

    $ok = sendMail($email, trim(($member['prenom'] ?? '').' '.($member['nom'] ?? '')), $sujet, $html, $text, $attachments);

    // Journalisation (table optionnelle : migrate_member_emails.sql)
    $pj = $attachments ? implode(', ', array_map(fn($a) => $a['name'], $attachments)) : null;
    $st = $ok ? 'envoye' : 'echec';
    $mid = (int)($member['id'] ?? 0);
    try {
        $db->prepare("INSERT INTO member_emails (member_id, sujet, message, envoye_par, statut, pieces_jointes) VALUES (?,?,?,?,?,?)")
           ->execute([$mid, $sujet, $message, $par, $st, $pj]);
    } catch (Throwable $e) {
        // Colonne pieces_jointes peut-être absente — repli sans elle
        try {
            $db->prepare("INSERT INTO member_emails (member_id, sujet, message, envoye_par, statut) VALUES (?,?,?,?,?)")
               ->execute([$mid, $sujet, $message, $par, $st]);
        } catch (Throwable $e2) { error_log('sendMemberEmail log: '.$e2->getMessage()); }
    }

    return $ok;
}

/**
 * Collecte les fichiers uploadés d'un champ <input type="file" name="$field[]">
 * et les retourne sous forme [['name'=>..., 'content'=>bytes], ...] pour sendMail().
 * Filtre par extension autorisée, taille max par fichier et taille totale.
 */
function collectAttachments(string $field, int $maxEach = 5242880, int $maxTotal = 10485760): array {
    if (empty($_FILES[$field])) return [];
    $allowed = ['pdf','doc','docx','odt','xls','xlsx','ppt','pptx','png','jpg','jpeg','gif','webp','txt','csv','zip'];
    $f = $_FILES[$field];
    $names = is_array($f['name'])     ? $f['name']     : [$f['name']];
    $tmps  = is_array($f['tmp_name']) ? $f['tmp_name'] : [$f['tmp_name']];
    $errs  = is_array($f['error'])    ? $f['error']    : [$f['error']];
    $sizes = is_array($f['size'])     ? $f['size']     : [$f['size']];
    $atts = []; $total = 0;
    foreach ($names as $i => $nm) {
        if (($errs[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
        if (!is_uploaded_file($tmps[$i])) continue;
        $ext = strtolower(pathinfo($nm, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) continue;
        if (($sizes[$i] ?? 0) > $maxEach) continue;
        $total += (int)($sizes[$i] ?? 0);
        if ($total > $maxTotal) break;
        $content = @file_get_contents($tmps[$i]);
        if ($content === false) continue;
        $atts[] = ['name' => basename($nm), 'content' => $content];
    }
    return $atts;
}
