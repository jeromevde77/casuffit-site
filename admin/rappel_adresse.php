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
    $html = $tpl['html'] ?: ('<p>Bonjour '.htmlspecialchars($prenom).',</p><p>Merci de compléter votre adresse : <a href="'.$url.'">'.$url.'</a></p>');
    $text = $tpl['text'] ?: ("Bonjour $prenom,\n\nMerci de compléter votre adresse :\n$url\n\nL'équipe Ça suffit !");

    $ok = sendMail($m['email'], $nom ?: $m['email'], $subj, $html, $text);
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
