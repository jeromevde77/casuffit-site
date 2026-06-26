<?php
/**
 * cron/send_queue.php — Exécuté chaque jour par le Cron OVH
 * Envoie le prochain lot d'emails en attente dans la file d'envoi.
 *
 * Configuration Cron OVH :
 *   Espace client > Hébergements > votre hébergement > Plus > Tâches planifiées (Cron)
 *   Commande : /usr/local/php8.2/bin/php /home/VOTRE_LOGIN/www/cron/send_queue.php
 *   Fréquence : chaque jour à 09:00
 *   Email rapport : info@casuffit.be
 */
require_once __DIR__ . '/../config.php';

// Déclencheur autorisé : CLI (cron OVH), 127.0.0.1, ou URL avec ?secret=CRON_SECRET
// (permet un lancement manuel depuis l'admin ou via cron-job.org)
$is_web        = (PHP_SAPI !== 'cli');
$web_secret_ok = isset($_GET['secret']) && defined('CRON_SECRET')
               && hash_equals((string)CRON_SECRET, (string)$_GET['secret']);
if ($is_web && (($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1') && !$web_secret_ok) {
    http_response_code(403); die('Accès refusé');
}

$db         = getDB();
$batch_size = QUEUE_BATCH_SIZE;
// En lancement web (manuel), on borne le lot pour éviter les timeouts ; on peut relancer
if ($is_web) {
    $batch_size = isset($_GET['max']) ? max(1, min((int)$_GET['max'], QUEUE_BATCH_SIZE)) : min(120, QUEUE_BATCH_SIZE);
}
$start      = microtime(true);
$sent = $errors = 0;

log_msg("═══ Démarrage envoi — lot max: $batch_size ═══");

// Récupérer le prochain lot
$stmt = $db->prepare("
    SELECT q.id, q.newsletter_id, q.subscriber_id, q.tentatives,
           n.sujet, n.contenu_html,
           s.email, s.prenom, s.nom, s.token_unsub
    FROM send_queue q
    JOIN newsletters  n ON n.id = q.newsletter_id
    JOIN subscribers  s ON s.id = q.subscriber_id
    WHERE q.statut = 'en_attente' AND q.tentatives < 3
    ORDER BY q.id ASC LIMIT ?");
$stmt->execute([$batch_size]);
$queue = $stmt->fetchAll();

if (empty($queue)) { log_msg("✓ Aucun email en attente."); exit(0); }

log_msg("📨 " . count($queue) . " email(s) à envoyer");

foreach ($queue as $item) {
    $prenom_display  = $item['prenom'] ?: 'cher(e) sympathisant(e)';
    $unsubscribe_url = SITE_URL . '/newsletter/unsubscribe.php?token=' . $item['token_unsub'];
    $sujet           = $item['sujet'];
    $contenu_html    = $item['contenu_html'];

    // Générer le HTML de la newsletter
    ob_start();
    include __DIR__ . '/../templates/email_newsletter.php';
    $html_body = ob_get_clean();

    // Tracking ouverture : pixel + email_opens
    $track_campagne = 'newsletter_' . $item['newsletter_id'];
    $track_token    = bin2hex(random_bytes(16));
    try {
        $exists = $db->prepare("SELECT id FROM email_opens WHERE campagne=? AND email=?");
        $exists->execute([$track_campagne, $item['email']]);
        if ($exists->fetch()) {
            $db->prepare("UPDATE email_opens SET token=?, premiere_ouverture=NULL, derniere_ouverture=NULL, nb_ouvertures=0 WHERE campagne=? AND email=?")
               ->execute([$track_token, $track_campagne, $item['email']]);
        } else {
            $db->prepare("INSERT INTO email_opens (campagne, email, token) VALUES (?,?,?)")
               ->execute([$track_campagne, $item['email'], $track_token]);
        }
        $pixel   = '<img src="' . SITE_URL . '/api/open.php?t=' . $track_token . '" width="1" height="1" style="display:none" alt="">';
        $html_body = str_contains($html_body, '</body>')
            ? str_replace('</body>', $pixel . '</body>', $html_body)
            : $html_body . $pixel;
    } catch (Throwable $e) {
        log_msg("  ⚠ Tracking ignoré pour {$item['email']}: " . $e->getMessage());
    }

    // Envoi
    [$ok, $err] = !empty(BREVO_API_KEY)
        ? sendViaBrevo($item['email'], trim($item['prenom'].' '.$item['nom']), $sujet, $html_body)
        : sendViaSMTP($item['email'],  trim($item['prenom'].' '.$item['nom']), $sujet, $html_body, strip_tags($contenu_html));

    if ($ok) {
        $db->prepare("UPDATE send_queue SET statut='envoye', envoye_at=NOW() WHERE id=?")->execute([$item['id']]);
        $sent++;
        log_msg("  ✓ {$item['email']}");
    } else {
        $db->prepare("UPDATE send_queue SET tentatives=tentatives+1, erreur_msg=?,
            statut=IF(tentatives>=2,'erreur','en_attente') WHERE id=?")->execute([$err, $item['id']]);
        $errors++;
        log_msg("  ✗ {$item['email']} — $err");
    }
    usleep($is_web ? 50000 : 200000); // pause anti rate-limit (plus courte en web)
}

// Mettre à jour les compteurs newsletters
foreach (array_unique(array_column($queue, 'newsletter_id')) as $nid) {
    $db->prepare("UPDATE newsletters SET nb_envoyes=(SELECT COUNT(*) FROM send_queue WHERE newsletter_id=? AND statut='envoye') WHERE id=?")->execute([$nid, $nid]);
    $reste = $db->prepare("SELECT COUNT(*) FROM send_queue WHERE newsletter_id=? AND statut='en_attente'")->execute([$nid]) ? $db->query("SELECT COUNT(*) FROM send_queue WHERE newsletter_id=$nid AND statut='en_attente'")->fetchColumn() : 0;
    if ($reste == 0) {
        $db->prepare("UPDATE newsletters SET statut='envoye', sent_at=NOW() WHERE id=? AND statut!='envoye'")->execute([$nid]);
        log_msg("🎉 Newsletter #$nid complètement envoyée !");
    } else {
        log_msg("⏳ Newsletter #$nid : $reste restant(s)");
    }
}

$dur = round(microtime(true) - $start, 2);
log_msg("═══ Résumé : ✓ $sent envoyés · ✗ $errors erreurs · ⏱ {$dur}s ═══");

// Retour lisible si lancé manuellement depuis le navigateur
if ($is_web) {
    $reste = (int)$db->query("SELECT COUNT(*) FROM send_queue WHERE statut='en_attente'")->fetchColumn();
    echo "\nLot traité : $sent envoyé(s), $errors erreur(s). Restant en file d'attente : $reste.\n";
    if ($reste > 0) echo "→ Relance cette page pour envoyer le lot suivant.\n";
    else            echo "✅ File vide — newsletter(s) entièrement envoyée(s).\n";
}

// ── Fonctions ──────────────────────────────────────────────────────────
function sendViaBrevo(string $to, string $name, string $subject, string $html) {
    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS     => json_encode(['sender'=>['name'=>SMTP_FROM_NAME,'email'=>SMTP_FROM],
            'to'=>[['email'=>$to,'name'=>$name]],'subject'=>$subject,'htmlContent'=>$html]),
        CURLOPT_HTTPHEADER     => ['accept: application/json','api-key: '.BREVO_API_KEY,'content-type: application/json'],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($code >= 200 && $code < 300) return [true, ''];
    $d = json_decode($resp, true); return array(false, "HTTP $code: ".(isset($d['message']) ? $d['message'] : $resp));
}

function sendViaSMTP(string $to, string $name, string $subject, string $html, string $text) {
    $pm = __DIR__ . '/../vendor/PHPMailer/PHPMailer.php';
    if (!file_exists($pm)) return [false, 'PHPMailer non installé'];
    require_once $pm; require_once __DIR__.'/../vendor/PHPMailer/SMTP.php'; require_once __DIR__.'/../vendor/PHPMailer/Exception.php';
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP(); $mail->Host=SMTP_HOST; $mail->SMTPAuth=true;
        $mail->Username=SMTP_USER; $mail->Password=SMTP_PASS;
        $mail->SMTPSecure=(SMTP_PORT==465)?'ssl':'tls'; $mail->Port=SMTP_PORT; $mail->CharSet='UTF-8';
        $mail->setFrom(SMTP_FROM,SMTP_FROM_NAME); $mail->addAddress($to,$name);
        $mail->Subject=$subject; $mail->isHTML(true); $mail->Body=$html; $mail->AltBody=$text;
        $mail->send(); return [true, ''];
    } catch (\Exception $e) { return [false, $mail->ErrorInfo]; }
}

function log_msg(string $msg) {
    $line = '['.date('Y-m-d H:i:s').'] '.$msg;
    echo $line.PHP_EOL;
    $dir = __DIR__.'/../logs';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($dir.'/cron_'.date('Y-m').'.log', $line.PHP_EOL, FILE_APPEND);
}
