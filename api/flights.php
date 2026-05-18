<?php
// ═══════════════════════════════════════════════════════════════════════
//  api/flights.php — Proxy OpenSky avec auth OAuth2 + cache local
//
//  - Token Bearer mis en cache (~25 min, expire à 30 min OpenSky)
//  - Réponses /states/all mises en cache 25s par bounding box
//  - Fallback sur cache obsolète si OpenSky échoue (jusqu'à 5 min)
// ═══════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

// ── Protection : seuls les visiteurs du site peuvent appeler cette API ───
// Vérification du token de session passé par le widget JS
if (!session_id()) session_start();

$token_header = $_SERVER['HTTP_X_API_TOKEN'] ?? '';
$session_token = $_SESSION['api_token'] ?? '';

// Rate limiting : max 60 appels/minute par IP (le widget recharge toutes les 60s)
$ip      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rk      = 'flights_' . md5($ip);
$now     = time();
$_SESSION[$rk] = array_filter($_SESSION[$rk] ?? [], fn($t) => $now - $t < 60);
if (count($_SESSION[$rk]) >= 10) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many requests']);
    exit;
}
$_SESSION[$rk][] = $now;

// Vérifier le token si présent (non bloquant si absent pour compatibilité)
// mais bloquer si token invalide
if ($token_header && $session_token && !hash_equals($session_token, $token_header)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid token']);
    exit;
}

// Referer doit pointer vers le site (protection basique)
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$allowed = defined('SITE_URL') ? SITE_URL : 'https://www.casuffit.be';
if ($referer && !str_starts_with($referer, $allowed) && !str_starts_with($referer, 'http://localhost')) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// ── Paramètres bounding box ──────────────────────────────────────────────
$lamin = isset($_GET['lamin']) ? floatval($_GET['lamin']) : 50.1;
$lomin = isset($_GET['lomin']) ? floatval($_GET['lomin']) : 3.5;
$lamax = isset($_GET['lamax']) ? floatval($_GET['lamax']) : 51.7;
$lomax = isset($_GET['lomax']) ? floatval($_GET['lomax']) : 5.5;

$CACHE_DIR = __DIR__ . '/../cache';
if (!is_dir($CACHE_DIR)) @mkdir($CACHE_DIR, 0755, true);

// ── 1. Cache de la réponse (25s) ─────────────────────────────────────────
$bboxKey   = sprintf('%.2f_%.2f_%.2f_%.2f', $lamin, $lomin, $lamax, $lomax);
$cacheFile = $CACHE_DIR . '/flights_' . $bboxKey . '.json';
$cacheAge  = file_exists($cacheFile) ? (time() - filemtime($cacheFile)) : 999999;

if ($cacheAge < 25) {
    header('X-Cache: HIT');
    header('X-Cache-Age: ' . $cacheAge);
    readfile($cacheFile);
    exit;
}

// ── 2. Récupérer un token OAuth2 (avec cache ~25 min) ────────────────────
function getOpenSkyToken() {
    $tokenFile = __DIR__ . '/../cache/opensky_token.json';

    if (file_exists($tokenFile)) {
        $data = json_decode(@file_get_contents($tokenFile), true);
        if ($data && !empty($data['access_token']) && !empty($data['expires_at'])
            && $data['expires_at'] > time() + 30) {
            return $data['access_token'];
        }
    }

    if (!defined('OPENSKY_CLIENT_ID') || !defined('OPENSKY_CLIENT_SECRET')) {
        return null;
    }

    $ch = curl_init('https://auth.opensky-network.org/auth/realms/opensky-network/protocol/openid-connect/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type'    => 'client_credentials',
            'client_id'     => OPENSKY_CLIENT_ID,
            'client_secret' => OPENSKY_CLIENT_SECRET,
        ]),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_USERAGENT      => 'casuffit.be/1.0',
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || !$raw) return null;
    $data = json_decode($raw, true);
    if (empty($data['access_token'])) return null;

    $expiresIn = $data['expires_in'] ?? 1800;
    @file_put_contents($tokenFile, json_encode([
        'access_token' => $data['access_token'],
        'expires_at'   => time() + $expiresIn,
        'fetched_at'   => time(),
    ]), LOCK_EX);
    @chmod($tokenFile, 0600);

    return $data['access_token'];
}

// ── 3. Appeler OpenSky ───────────────────────────────────────────────────
$url = "https://opensky-network.org/api/states/all"
     . "?lamin={$lamin}&lomin={$lomin}&lamax={$lamax}&lomax={$lomax}";

$token = getOpenSkyToken();

$ch = curl_init($url);
$headers = ['Accept: application/json'];
if ($token) $headers[] = 'Authorization: Bearer ' . $token;

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_USERAGENT      => 'casuffit.be/1.0',
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_FOLLOWLOCATION => true,
]);
$raw      = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

// ── 4. Succès ─────────────────────────────────────────────────────────────
if ($httpCode === 200 && $raw) {
    @file_put_contents($cacheFile, $raw, LOCK_EX);
    header('X-Cache: MISS');
    header('X-OpenSky-Auth: ' . ($token ? 'oauth2' : 'anonymous'));
    echo $raw;
    exit;
}

// ── 5. Échec : fallback sur cache obsolète (jusqu'à 5 min) ───────────────
if (file_exists($cacheFile) && $cacheAge < 300) {
    header('X-Cache: STALE');
    header('X-Cache-Age: ' . $cacheAge);
    header('X-OpenSky-Status: ' . $httpCode);
    readfile($cacheFile);
    exit;
}

// ── 6. Vraie panne : 502 explicite ───────────────────────────────────────
http_response_code(502);
echo json_encode([
    'error'     => 'OpenSky indisponible',
    'http_code' => $httpCode,
    'detail'    => $curlErr ?: "HTTP $httpCode",
    'hint'      => $httpCode === 429 ? 'Rate limit'
                  : ($httpCode === 401 ? 'Token invalide/expiré'
                  : ($httpCode === 403 ? 'Auth requise' : null)),
]);
