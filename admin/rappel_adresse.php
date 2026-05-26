<?php
// admin/rappel_adresse.php — Envoi groupé du rappel "compléter l'adresse" aux membres
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

// CSRF
$submitted = $_POST['_csrf'] ?? '';
$expected  = $_SESSION['_csrf_token'] ?? '';
if (!$expected || !$submitted || !hash_equals($expected, $submitted)) {
    echo json_encode(['ok'=>false,'error'=>'Token CSRF invalide']); exit;
}

$db = getDB();

$ids = array_filter(array_map('intval', (array)($_POST['ids'] ?? [])));
if (empty($ids)) { echo json_encode(['ok'=>false,'error'=>'Aucun membre sélectionné']); exit; }

$in = implode(',', $ids);
$membres = $db->query("SELECT id, email, prenom, nom, adresse, code_postal FROM members
                       WHERE id IN ($in) AND statut='actif'")->fetchAll();

if (empty($membres)) { echo json_encode(['ok'=>false,'error'=>'Aucun membre actif dans la sélection']); exit; }

$site_url = defined('SITE_URL') ? SITE_URL : 'https://www.casuffit.be';
$sent = 0; $skipped = 0; $errors = [];
$expires = date('Y-m-d H:i:s', strtotime('+30 days'));

foreach ($membres as $m) {
    // Sécurité : ne relancer que ceux dont l'adresse OU le code postal est vide
    if (trim($m['adresse']) !== '' && trim($m['code_postal']) !== '') { $skipped++; continue; }

    $token = bin2hex(random_bytes(32));
    try {
        $db->prepare("UPDATE members SET token_magic=?, token_magic_exp=? WHERE id=?")
           ->execute([$token, $expires, $m['id']]);
    } catch (Exception $e) {
        $errors[] = $m['email'].' (db)'; continue;
    }

    $url    = $site_url . '/membre/completer.php?token=' . $token;
    $prenom = $m['prenom'] ?: '';
    $nom    = trim($m['prenom'].' '.$m['nom']);
    $vars   = ['{{prenom}}'=>$prenom, '{{url}}'=>$url, '{{email}}'=>$m['email']];

    $tpl  = renderEmailTemplate($db, 'rappel_adresse', $vars, 'fr');
    $subj = $tpl['sujet'] ?: 'Complétez votre profil membre — Ça suffit !';
    if (!empty($tpl['html'])) {
        $html = $tpl['html'];
        $text = $tpl['text'];
    } else {
        // Fallback présentable si le template n'est pas encore en base
        $html = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:0 auto;background:#fff;border-radius:10px;overflow:hidden">'
            . '<div style="background:#1673B2;padding:24px;text-align:center">'
            . '<img src="https://www.casuffit.be/assets/img/logo.png" alt="Ça suffit !" width="60" style="border-radius:50%;background:#fff;margin-bottom:8px">'
            . '<div style="font-size:22px;font-weight:800;color:#FF9900">Ça suffit !</div></div>'
            . '<div style="background:#FF9900;height:3px"></div>'
            . '<div style="padding:30px">'
            . '<p style="font-size:15px;font-weight:700;color:#0e3d6b">Bonjour ' . htmlspecialchars($prenom) . ',</p>'
            . '<p style="font-size:14px;color:#555;line-height:1.6">Merci de faire partie du mouvement <strong>Ça Suffit</strong>. Il manque actuellement <strong style="color:#1673B2">votre adresse</strong> dans nos données. Cette information nous permet de savoir précisément quelles communes sont survolées — un argument essentiel dans nos démarches auprès des autorités.</p>'
            . '<p style="font-size:14px;color:#555;line-height:1.6">Compléter votre adresse ne prend que quelques secondes :</p>'
            . '<p style="text-align:center;margin:24px 0"><a href="' . $url . '" style="background:#FF9900;color:#fff;text-decoration:none;padding:14px 36px;border-radius:8px;font-weight:700;font-size:15px;display:inline-block">Compléter mon adresse</a></p>'
            . '<p style="font-size:13px;color:#777">Merci pour votre engagement à nos côtés.<br>L\'équipe Ça suffit !</p>'
            . '</div>'
            . '<div style="background:#f5f7fa;padding:18px;text-align:center;border-top:1px solid #e0e8f0">'
            . '<a href="https://www.facebook.com/piste01casuffit" style="display:inline-block;background:#1877F2;color:#fff;text-decoration:none;padding:9px 20px;border-radius:6px;font-size:13px;font-weight:700">f&nbsp; Suivez-nous sur Facebook</a>'
            . '<p style="font-size:11px;color:#aaa;margin-top:12px">Ça suffit ! · <a href="https://www.casuffit.be" style="color:#1673B2">casuffit.be</a></p>'
            . '</div></div>';
        $text = "Bonjour $prenom,\n\nMerci de faire partie du mouvement Ça Suffit. Il manque actuellement votre adresse dans nos données — elle nous permet de savoir quelles communes sont survolées, un argument essentiel dans nos démarches.\n\nCompléter votre adresse (quelques secondes) :\n$url\n\nMerci pour votre engagement.\nL'équipe Ça suffit !";
    }

    $ok = sendMailTracked($m['email'], $nom ?: $m['email'], $subj, $html, $text, 'rappel_adresse');
    if ($ok) {
        $sent++;
    } else {
        $errors[] = $m['email'];
        $db->prepare("UPDATE members SET token_magic=NULL, token_magic_exp=NULL WHERE id=?")->execute([$m['id']]);
    }
}

$msg = "$sent rappel(s) envoyé(s)";
if ($skipped) $msg .= ", $skipped déjà complet(s) (ignoré)";
if (count($errors)) $msg .= ", ".count($errors)." erreur(s)";
echo json_encode(['ok'=>true, 'sent'=>$sent, 'skipped'=>$skipped, 'errors'=>count($errors), 'msg'=>$msg]);
