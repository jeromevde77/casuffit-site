<?php
// Proxy OpenSky Network — évite les restrictions CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: max-age=30'); // Cache 30s pour éviter le rate limit

$LAT_MIN = 50.5; $LAT_MAX = 51.3;
$LON_MIN = 3.8;  $LON_MAX = 5.2;

$url = "https://opensky-network.org/api/states/all"
     . "?lamin={$LAT_MIN}&lomin={$LON_MIN}&lamax={$LAT_MAX}&lomax={$LON_MAX}";

$ctx = stream_context_create([
    'http' => [
        'timeout' => 10,
        'header'  => "User-Agent: casuffit.be/1.0\r\n"
    ]
]);

$raw = @file_get_contents($url, false, $ctx);

if ($raw === false) {
    http_response_code(502);
    echo json_encode(['error' => 'Impossible de contacter OpenSky Network']);
    exit;
}

echo $raw;
