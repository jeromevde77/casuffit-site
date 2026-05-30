<?php
// admin/translate_widget.php — Traduit un widget PHP/HTML du FR vers le NL via Claude
// Reçoit : slug + contenu (PHP/HTML du widget FR)
// Produit : fichier includes/widgets/{slug}_nl.php

require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();

@set_time_limit(120);
ignore_user_abort(true);
header('Content-Type: application/json; charset=utf-8');

// ── Vérifications ────────────────────────────────────────────────────────
if (!defined('ANTHROPIC_API_KEY') || strpos(ANTHROPIC_API_KEY, 'VOTRE_CLE') !== false) {
    echo json_encode(['ok' => false, 'error' => 'ANTHROPIC_API_KEY non configurée dans config.php']);
    exit;
}

$slug    = preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($_POST['slug'] ?? '')));
$contenu = trim($_POST['contenu'] ?? '');

if (!$slug) {
    echo json_encode(['ok' => false, 'error' => 'slug manquant']);
    exit;
}
if (!$contenu) {
    echo json_encode(['ok' => false, 'error' => 'contenu manquant — sauvegardez le widget avant de traduire']);
    exit;
}

// ── Prompt ────────────────────────────────────────────────────────────────
$prompt = <<<TXT
Tu traduis un widget PHP/HTML d'une association militante belge (Piste01 Ça Suffit ASBL) du français vers le néerlandais belge.

CONTEXTE : Widget affiché sur le site casuffit.be. Public visé : habitants flamands et bruxellois.

CONSIGNES STRICTES :
1. Conserve EXACTEMENT tout le code PHP, HTML, CSS inline, JavaScript, les attributs, les classes CSS.
2. Ne traduis QUE les textes visibles par l'utilisateur (contenu des balises, placeholder, title, aria-label, textContent JS).
3. Ne traduis PAS : noms de variables PHP, fonctions, slugs, URLs, classes CSS, IDs, valeurs techniques.
4. Ne traduis PAS : "Ça suffit !", ASBL/VZW (garde les deux), IBAN, BIC, montants €, codes.
5. Garde les emoji tels quels.
6. Le fichier doit commencer par "<?php // Widget : " suivi du titre traduit + " — Version NL ?>"
7. Remplace "ASBL" par "VZW" dans les textes visibles uniquement.

Glossaire FR → NL à utiliser systématiquement :
- Prénom → Voornaam
- Nom → Naam  
- Adresse → Adres
- Commune → Gemeente
- Téléphone → Telefoon
- Email → E-mail
- S'abonner → Inschrijven
- Newsletter → Nieuwsbrief
- Don → Gift / Donatie
- Devenir membre → Lid worden
- Déjà membre → Al lid
- Objectif → Doelstelling
- Atteint → Bereikt
- De combat → Van strijd
- Soutenir → Steunen
- Copier → Kopiëren
- Communication → Mededeling
- Virement → Overschrijving
- Anonyme → Anoniem
- Libre → Vrij bedrag
- Scannez → Scan
- Envoi → Verzenden
- Erreur réseau → Netwerkfout
- J'accepte → Ik ga akkoord
- données → gegevens
- recevoir → ontvangen
- gratuit → gratis
- désinscription → uitschrijven

FICHIER PHP/HTML À TRADUIRE :
$contenu

RETOURNE UNIQUEMENT le fichier PHP/HTML traduit, sans explications, sans markdown, sans backticks.
Le fichier doit être directement exploitable tel quel (commence par <?php).
TXT;

// ── Appel API Claude ──────────────────────────────────────────────────────
$hasImages = (strpos($contenu, 'data:image') !== false);
$isLong    = strlen($contenu) > 4000;
$model     = ($hasImages || $isLong) ? 'claude-haiku-4-5-20251001' : 'claude-sonnet-4-6';

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
    CURLOPT_POSTFIELDS => json_encode([
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
    echo json_encode(['ok' => false, 'error' => "Claude API a échoué (HTTP $httpCode)", 'detail' => $curlErr]);
    exit;
}

$resp = json_decode($raw, true);
$text = trim($resp['content'][0]['text'] ?? '');

if (!$text) {
    echo json_encode(['ok' => false, 'error' => 'Réponse Claude vide']);
    exit;
}

// Nettoyer si Claude a quand même mis des backticks
$text = preg_replace('/^```(?:php|html)?\s*/i', '', $text);
$text = preg_replace('/\s*```\s*$/i', '', $text);
$text = trim($text);

// Vérifier que c'est bien du PHP
if (!str_starts_with($text, '<?php') && !str_starts_with($text, '<?')) {
    echo json_encode(['ok' => false, 'error' => 'La réponse ne semble pas être du PHP valide', 'raw' => substr($text, 0, 300)]);
    exit;
}

// ── Sauvegarder le fichier _nl.php ──────────────────────────────────────
$nl_file = __DIR__ . '/../includes/widgets/' . $slug . '_nl.php';
$written = file_put_contents($nl_file, $text);

if ($written === false) {
    echo json_encode(['ok' => false, 'error' => 'Impossible d\'écrire le fichier ' . $slug . '_nl.php (permissions ?)']);
    exit;
}

echo json_encode([
    'ok'     => true,
    'file'   => 'includes/widgets/' . $slug . '_nl.php',
    'size'   => $written,
    'model'  => $resp['model'] ?? $model,
    'tokens' => ($resp['usage']['input_tokens'] ?? 0) + ($resp['usage']['output_tokens'] ?? 0),
]);
