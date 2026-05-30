<?php
// admin/resend_campaign.php — Renvoie un email aux non-ouvreurs d'une campagne
require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/mail_helper.php';
require_once __DIR__ . '/../includes/email_renderer.php';

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok'=>false,'error'=>'POST requis']); exit;
}

csrf_verify();

$db       = getDB();
$campagne = trim($_POST['campagne'] ?? '');
if (!$campagne) { echo json_encode(['ok'=>false,'error'=>'Campagne manquante']); exit; }

// ── Récupérer les non-ouvreurs ────────────────────────────────────────────
$non_ouvreurs = $db->prepare(
    "SELECT eo.email FROM email_opens eo
     WHERE eo.campagne = ? AND eo.premiere_ouverture IS NULL"
);
$non_ouvreurs->execute([$campagne]);
$emails_no = array_column($non_ouvreurs->fetchAll(), 'email');

if (empty($emails_no)) {
    echo json_encode(['ok'=>true,'sent'=>0,'msg'=>'Tous les destinataires ont déjà ouvert cet email.']); exit;
}

$in_pl = implode(',', array_fill(0, count($emails_no), '?'));
$site_url = defined('SITE_URL') ? SITE_URL : 'https://www.casuffit.be';
$sent = 0; $errors = [];

// ── Logique d'envoi selon la campagne ─────────────────────────────────────
if ($campagne === 'invite_membre' || $campagne === 'invite_wix') {

    $type = $campagne === 'invite_wix' ? 'wix' : 'newsletter';
    $abonnes = $db->prepare(
        "SELECT s.id, s.email, s.prenom, s.nom
         FROM subscribers s WHERE s.email IN ($in_pl) AND s.statut='actif'"
    );
    $abonnes->execute($emails_no);

    foreach ($abonnes->fetchAll() as $ab) {
        $token = bin2hex(random_bytes(24));
        try {
            $db->prepare("UPDATE subscribers SET invite_membre_token=?, invite_membre_sent_at=NOW() WHERE id=?")
               ->execute([$token, $ab['id']]);
        } catch (Exception $e) { $errors[] = $ab['email']; continue; }

        $url    = $site_url . '/membre/inscription.php?invite=' . $token;
        $prenom = $ab['prenom'] ?: '';
        $nom    = trim($ab['prenom'].' '.$ab['nom']);
        $vars   = ['{{prenom}}'=>$prenom,'{{url}}'=>$url,'{{email}}'=>$ab['email']];

        if ($type === 'wix') {
            $tpl  = renderEmailTemplate($db, 'invite_wix', $vars, 'fr');
            $subj = $tpl['sujet'] ?: 'Le mouvement Ça Suffit reprend vie — rejoignez-nous';
            $html = $tpl['html']; $text = $tpl['text'];
        } else {
            $tpl  = renderEmailTemplate($db, 'invite_membre', $vars, 'fr');
            $subj = $tpl['sujet'] ?: 'Votre espace membre vous attend — Ça suffit !';
            $html = $tpl['html']; $text = $tpl['text'];
        }

        // Réinitialiser le tracking pour ce renvoi
        $db->prepare("UPDATE email_opens SET token=?, premiere_ouverture=NULL, derniere_ouverture=NULL, nb_ouvertures=0 WHERE campagne=? AND email=?")
           ->execute([bin2hex(random_bytes(16)), $campagne, $ab['email']]);

        if (sendMailTracked($ab['email'], $nom ?: $ab['email'], $subj, $html, $text, $campagne)) {
            $sent++;
        } else {
            $errors[] = $ab['email'];
        }
    }

} elseif ($campagne === 'rappel_adresse') {

    $membres = $db->prepare(
        "SELECT m.id, m.email, m.prenom, m.nom, m.token_magic
         FROM members m WHERE m.email IN ($in_pl) AND m.statut='actif'
         AND (TRIM(COALESCE(m.adresse,''))='' OR TRIM(COALESCE(m.code_postal,''))='')"
    );
    $membres->execute($emails_no);

    foreach ($membres->fetchAll() as $m) {
        $token = bin2hex(random_bytes(32));
        $db->prepare("UPDATE members SET token_magic=?, token_magic_exp=DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE id=?")
           ->execute([$token, $m['id']]);

        $url    = $site_url . '/membre/completer.php?token=' . $token;
        $prenom = $m['prenom'] ?: '';
        $nom    = trim($m['prenom'].' '.$m['nom']);
        $vars   = ['{{prenom}}'=>$prenom,'{{url}}'=>$url,'{{email}}'=>$m['email']];
        $tpl    = renderEmailTemplate($db, 'rappel_adresse', $vars, 'fr');
        $subj   = $tpl['sujet'] ?: 'Votre adresse est manquante — Ça suffit !';
        $html   = $tpl['html']; $text = $tpl['text'];

        $db->prepare("UPDATE email_opens SET token=?, premiere_ouverture=NULL, derniere_ouverture=NULL, nb_ouvertures=0 WHERE campagne=? AND email=?")
           ->execute([bin2hex(random_bytes(16)), $campagne, $m['email']]);

        if (sendMailTracked($m['email'], $nom ?: $m['email'], $subj, $html, $text, $campagne)) {
            $sent++;
        } else {
            $errors[] = $m['email'];
        }
    }

} elseif (preg_match('/^newsletter_(\d+)$/', $campagne, $m_nl)) {

    $newsletter_id = (int)$m_nl[1];

    // Récupérer le contenu de la newsletter
    $nl = $db->prepare("SELECT * FROM newsletters WHERE id=?");
    $nl->execute([$newsletter_id]);
    $newsletter = $nl->fetch();
    if (!$newsletter) {
        echo json_encode(['ok'=>false,'error'=>"Newsletter #$newsletter_id introuvable."]); exit;
    }

    // Trouver les entrées send_queue de ces non-ouvreurs pour cette newsletter
    $sq = $db->prepare(
        "SELECT q.*, s.prenom, s.nom, s.token_unsub
         FROM send_queue q
         JOIN subscribers s ON s.id = q.subscriber_id
         WHERE q.newsletter_id = ? AND q.email IN ($in_pl)"
    );
    $sq->execute(array_merge([$newsletter_id], $emails_no));
    $to_resend = $sq->fetchAll();

    if (empty($to_resend)) {
        // Fallback : chercher directement dans subscribers
        $subs = $db->prepare(
            "SELECT s.* FROM subscribers s WHERE s.email IN ($in_pl) AND s.statut='actif'"
        );
        $subs->execute($emails_no);
        foreach ($subs->fetchAll() as $sub) {
            // Réinsérer dans send_queue
            $db->prepare(
                "INSERT IGNORE INTO send_queue (newsletter_id, subscriber_id, email, prenom, nom, token_unsub, statut, date_planifie)
                 VALUES (?,?,?,?,?,?,'en_attente',NOW())"
            )->execute([$newsletter_id, $sub['id'], $sub['email'], $sub['prenom'], $sub['nom'], $sub['token_unsub']]);
            $sent++;
        }
    } else {
        foreach ($to_resend as $item) {
            // Remettre en attente dans send_queue
            $db->prepare(
                "UPDATE send_queue SET statut='en_attente', tentatives=0, erreur_msg=NULL,
                 date_planifie=NOW() WHERE newsletter_id=? AND email=?"
            )->execute([$newsletter_id, $item['email']]);

            // Réinitialiser le tracking
            $new_token = bin2hex(random_bytes(16));
            $db->prepare(
                "UPDATE email_opens SET token=?, premiere_ouverture=NULL,
                 derniere_ouverture=NULL, nb_ouvertures=0 WHERE campagne=? AND email=?"
            )->execute([$new_token, $campagne, $item['email']]);

            $sent++;
        }
    }

    if ($sent > 0) {
        // Remettre la newsletter en statut 'envoi' si elle était 'envoye'
        $db->prepare("UPDATE newsletters SET statut='envoi' WHERE id=? AND statut='envoye'")
           ->execute([$newsletter_id]);
    }

} else {
    echo json_encode(['ok'=>false,'error'=>"Campagne '$campagne' non gérée pour le renvoi."]); exit;
}

$msg = "$sent email(s) renvoyé(s) aux non-ouvreurs";
if (count($errors)) $msg .= ' · ' . count($errors) . ' erreur(s) : ' . implode(', ', array_slice($errors, 0, 3));

echo json_encode(['ok'=>true, 'sent'=>$sent, 'errors'=>count($errors), 'msg'=>$msg]);
