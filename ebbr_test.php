<?php
// Test minimal — répond immédiatement sans rien faire de lourd
header('Content-Type: text/plain; charset=utf-8');
echo "OK - " . date('Y-m-d H:i:s') . "\n";
echo "PHP " . PHP_VERSION . "\n";

// Test 1 : config.php se charge ?
echo "1. Chargement config.php... ";
require_once __DIR__ . '/config.php';
echo "OK\n";

// Test 2 : constantes OpenSky définies ?
echo "2. OPENSKY_CLIENT_ID : " . (defined('OPENSKY_CLIENT_ID') && OPENSKY_CLIENT_ID ? 'défini' : 'ABSENT') . "\n";
echo "3. OPENSKY_CLIENT_SECRET : " . (defined('OPENSKY_CLIENT_SECRET') && OPENSKY_CLIENT_SECRET ? 'défini' : 'ABSENT') . "\n";
echo "4. CRON_SECRET : " . (defined('CRON_SECRET') ? 'défini' : 'ABSENT') . "\n";

// Test 3 : curl dispo ?
echo "5. curl : " . (function_exists('curl_init') ? 'OK' : 'ABSENT') . "\n";

// Test 4 : GD dispo ?
echo "6. GD (images) : " . (function_exists('imagecreatetruecolor') ? 'OK' : 'ABSENT') . "\n";

// Test 5 : table ebbr_runway_tracks existe ?
echo "7. Table ebbr_runway_tracks : ";
try {
    $db = getDB();
    $db->query("SELECT 1 FROM ebbr_runway_tracks LIMIT 1");
    echo "OK\n";
} catch (Exception $e) {
    echo "MANQUANTE (" . $e->getMessage() . ")\n";
}

// Test 6 : peut-on joindre OpenSky token ?
echo "8. Token OpenSky... ";
$ch = curl_init('https://auth.opensky-network.org/auth/realms/opensky-network/protocol/openid-connect/token');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 10,
    CURLOPT_POSTFIELDS => http_build_query([
        'grant_type'=>'client_credentials',
        'client_id'=> defined('OPENSKY_CLIENT_ID') ? OPENSKY_CLIENT_ID : '',
        'client_secret'=> defined('OPENSKY_CLIENT_SECRET') ? OPENSKY_CLIENT_SECRET : '',
    ]),
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
]);
$raw = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);
echo "HTTP $code";
if ($err) echo " | curl error: $err";
$data = json_decode($raw, true);
echo " | token: " . (!empty($data['access_token']) ? 'reçu ✓' : 'absent') . "\n";

echo "\n=== Tous les tests terminés ===\n";
