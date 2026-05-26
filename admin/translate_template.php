<?php
// ═══════════════════════════════════════════════════════════════════════
//  admin/translate_template.php — Traduction FR → NL d'un template email
//  Reçoit slug en POST, traduit sujet_fr + contenu_fr → sujet_nl + contenu_nl
// ═══════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();
require_once __DIR__ . '/../includes/csrf.php';

@set_time_limit(120);
ignore_user_abort(true);
header('Content-Type: application/json; charset=utf-8');

// CSRF
$submitted = $_POST['_csrf'] ?? '';
$expected  = $_SESSION['_csrf_token'] ?? '';
if (!$expected || !$submitted || !hash_equals($expected, $submitted)) {
    echo json_encode(['ok'=>false,'error'=>'Token CSRF invalide']); exit;
}

// Vérifier la clé API
if (!defined('ANTHROPIC_API_KEY') || ANTHROPIC_API_KEY === '' || strpos(ANTHROPIC_API_KEY, 'VOTRE_CLE') !== false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'ANTHROPIC_API_KEY non configurée dans config.php']);
    exit;
}

$slug = preg_replace('/[^a-z_]/', '', $_POST['slug'] ?? '');
if (!$slug) { echo json_encode(['ok'=>false,'error'=>'slug manquant']); exit; }

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT slug, sujet_fr, contenu_fr FROM email_templates WHERE slug = ?");
    $stmt->execute([$slug]);
    $tpl = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tpl) { echo json_encode(['ok'=>false,'error'=>'Template introuvable']); exit; }
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'DB: ' . $e->getMessage()]); exit;
}

$sujet = $tpl['sujet_fr'] ?? '';
$html  = $tpl['contenu_fr'] ?? '';

$prompt = <<<TXT
Tu es un traducteur professionnel FR → NL pour une association citoyenne belge contre les nuisances aériennes.

RÈGLES :
1. Traduis en néerlandais (NL de Belgique) le sujet et le contenu HTML ci-dessous.
2. PRÉSERVE EXACTEMENT toute la structure HTML, les balises, les styles inline, les attributs, et les variables entre doubles accolades comme {{prenom}}, {{url}}, {{email}} — ne les traduis PAS, garde-les telles quelles.
3. Garde "Ça suffit !" tel quel (nom de l'association), ne le traduis pas.
4. Garde les liens (href) et adresses email inchangés.
5. Ton clair, respectueux et engageant.

À TRADUIRE :

SUJET :
$sujet

CONTENU HTML :
$html

FORMAT DE RÉPONSE : strictement du JSON valide (rien d'autre, pas de markdown) :
{
  "sujet_nl": "...",
  "contenu_nl": "..."
}
TXT;

$isLong = strlen($html) > 4000;
$model  = $isLong ? 'claude-haiku-4-5-20251001' : 'claude-sonnet-4-6';

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_TIMEOUT        => 55,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: ' . ANTHROPIC_API_KEY,
        'anthropic-version: 2023-06-01',
    ],
    CURLOPT_POSTFIELDS     => json_encode([
        'model'      => $model,
        'max_tokens' => 8192,
        'messages'   => [['role' => 'user', 'content' => $prompt]],
    ]),
]);
$raw      = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($httpCode !== 200) {
    http_response_code(502);
    echo json_encode(['ok'=>false,'error'=>"Claude API a échoué (HTTP $httpCode)",'detail'=>$curlErr,'response'=>substr($raw ?? '',0,500)]);
    exit;
}

$resp = json_decode($raw, true);
if (!$resp || empty($resp['content'][0]['text'])) {
    echo json_encode(['ok'=>false,'error'=>'Réponse Claude invalide']); exit;
}

$text = $resp['content'][0]['text'];
$text = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($text));
$out  = json_decode($text, true);

if (!$out || !isset($out['contenu_nl'])) {
    echo json_encode(['ok'=>false,'error'=>'Impossible de parser la réponse JSON','raw'=>substr($text,0,800)]); exit;
}

// Sauvegarder en base
try {
    $db->prepare("UPDATE email_templates SET sujet_nl=?, contenu_nl=? WHERE slug=?")
       ->execute([$out['sujet_nl'] ?? $sujet, $out['contenu_nl'], $slug]);
} catch (Throwable $e) {
    echo json_encode(['ok'=>false,'error'=>'Sauvegarde DB: '.$e->getMessage()]); exit;
}

echo json_encode(['ok'=>true, 'sujet_nl'=>$out['sujet_nl'] ?? '', 'contenu_nl'=>$out['contenu_nl']]);
