<?php
// ═══════════════════════════════════════════════════════════════════════
//  admin/translate_auto.php — Traduction FR → NL via Claude API
//  Reçoit page_id en POST, traduit titre + meta + contenu, renvoie JSON
// ═══════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();

header('Content-Type: application/json; charset=utf-8');

// ── Vérifier configuration ──────────────────────────────────────────────
if (!defined('ANTHROPIC_API_KEY') || ANTHROPIC_API_KEY === '' || strpos(ANTHROPIC_API_KEY, 'VOTRE_CLE') !== false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'ANTHROPIC_API_KEY non configurée dans config.php']);
    exit;
}

// ── Récupérer la page ────────────────────────────────────────────────────
$page_id = (int)($_POST['page_id'] ?? $_GET['page_id'] ?? 0);
if ($page_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'page_id manquant']);
    exit;
}

try {
    $db   = getDB();
    $stmt = $db->prepare("SELECT id, slug, titre, contenu, meta_description FROM pages WHERE id = ?");
    $stmt->execute([$page_id]);
    $page = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$page) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Page introuvable']);
        exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB: ' . $e->getMessage()]);
    exit;
}

// ── Construire le prompt ────────────────────────────────────────────────
$titre = $page['titre'] ?? '';
$meta  = $page['meta_description'] ?? '';
$html  = $page['contenu'] ?? '';

// Glossaire aéronautique pour cohérence
$glossaire = <<<TXT
Glossaire FR → NL (vocabulaire à utiliser systématiquement) :
- piste → baan
- vent arrière → rugwind (terme aviation)
- vent traversier / latéral → dwarswind
- vent de face → tegenwind
- plan de répartition (PRS) → spreidingsplan
- nuisances sonores → geluidshinder
- survol → overvliegen
- vol de nuit → nachtvlucht
- atterrissage → landing
- décollage → opstijgen
- ASBL → VZW
- Bruxelles → Brussel
- Bruxelles-National → Brussel-Nationaal
- Zaventem → Zaventem (inchangé)
- skeyes → skeyes (inchangé, nom propre)
- AIP → AIP (inchangé, terme technique)
- piste 01, piste 19, piste 25 → baan 01, baan 19, baan 25
TXT;

$prompt = <<<TXT
Tu traduis le contenu d'une association militante belge (ça suffit ! ASBL) du français vers le néerlandais belge.

CONTEXTE : Association citoyenne qui s'oppose à l'usage abusif de la piste 01 à l'aéroport de Bruxelles. Public visé : habitants flamands et bruxellois.

TON : Ton militant, clair, accessible. Pas de jargon administratif. Le NL doit être naturel (NL belge ≠ NL des Pays-Bas pour les expressions idiomatiques, mais reste compréhensible).

$glossaire

CONSIGNES STRICTES :
1. Conserve **exactement** la structure HTML (balises, attributs, classes). Ne traduis QUE le texte entre les balises.
2. Ne traduis PAS : noms propres, noms de pistes, sigles techniques (AIP, METAR, skeyes, BATC, UBCNA, IRM, NIMBY...), URLs, codes ICAO.
3. Conserve les emoji.
4. Si tu rencontres "ça suffit !" → garde-le tel quel (c'est le nom de l'asbl).

À TRADUIRE :

TITRE :
$titre

META DESCRIPTION :
$meta

CONTENU HTML :
$html

FORMAT DE RÉPONSE : strictement du JSON valide (et rien d'autre, pas de markdown, pas de ```json) :
{
  "titre_nl": "...",
  "meta_nl": "...",
  "contenu_nl": "..."
}
TXT;

// ── Appeler l'API Claude ────────────────────────────────────────────────
$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_TIMEOUT        => 90, // les longs contenus prennent du temps
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: ' . ANTHROPIC_API_KEY,
        'anthropic-version: 2023-06-01',
    ],
    CURLOPT_POSTFIELDS     => json_encode([
        'model'      => 'claude-sonnet-4-6',
        'max_tokens' => 16000,
        'messages'   => [
            ['role' => 'user', 'content' => $prompt],
        ],
    ]),
]);
$raw      = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($httpCode !== 200) {
    http_response_code(502);
    echo json_encode([
        'ok'        => false,
        'error'     => "Claude API a échoué (HTTP $httpCode)",
        'detail'    => $curlErr,
        'response'  => substr($raw ?? '', 0, 500),
    ]);
    exit;
}

$resp = json_decode($raw, true);
if (!$resp || empty($resp['content'][0]['text'])) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Réponse Claude invalide', 'raw' => substr($raw, 0, 500)]);
    exit;
}

// ── Extraire le JSON de la réponse ──────────────────────────────────────
$text = $resp['content'][0]['text'];
// Au cas où Claude entoure de ```json ... ```
$text = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($text));
$out  = json_decode($text, true);

if (!$out || !isset($out['titre_nl'])) {
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'error'   => 'Impossible de parser la réponse JSON de Claude',
        'raw'     => substr($text, 0, 800),
    ]);
    exit;
}

// ── Sauvegarder en BDD (auto, à relire) ─────────────────────────────────
try {
    // Vérifier que les colonnes _nl existent
    $cols = $db->query("SHOW COLUMNS FROM pages LIKE 'titre_nl'")->fetch();
    if ($cols) {
        $stmt = $db->prepare("UPDATE pages SET titre_nl=?, meta_description_nl=?, contenu_nl=?, nl_status='auto', nl_translated_at=NOW() WHERE id=?");
        $stmt->execute([
            $out['titre_nl']   ?? null,
            $out['meta_nl']    ?? null,
            $out['contenu_nl'] ?? null,
            $page_id,
        ]);
    }
} catch (Throwable $e) {
    // On renvoie quand même le résultat à l'admin pour qu'il puisse copier-coller
}

// ── Renvoyer le résultat ────────────────────────────────────────────────
echo json_encode([
    'ok'         => true,
    'titre_nl'   => $out['titre_nl']   ?? '',
    'meta_nl'    => $out['meta_nl']    ?? '',
    'contenu_nl' => $out['contenu_nl'] ?? '',
    'model'      => $resp['model'] ?? 'claude',
    'tokens'     => ($resp['usage']['input_tokens'] ?? 0) + ($resp['usage']['output_tokens'] ?? 0),
]);
