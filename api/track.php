<?php
// Proxy trajectoire OpenSky — tracks/all
header('Content-Type: application/json');
header('Cache-Control: max-age=15');

require_once __DIR__ . '/../config.php';

// Rate limiting : max 30 appels/minute par IP
if (!session_id()) session_start();
$ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rk  = 'track_' . md5($ip);
$now = time();
$_SESSION[$rk] = array_filter($_SESSION[$rk] ?? [], fn($t) => $now - $t < 60);
if (count($_SESSION[$rk]) >= 30) {
    http_response_code(429); echo json_encode(['error'=>'Too many requests']); exit;
}
$_SESSION[$rk][] = $now;

// Referer check
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$allowed = defined('SITE_URL') ? SITE_URL : 'https://www.casuffit.be';
if ($referer && !str_starts_with($referer, $allowed) && !str_starts_with($referer, 'http://localhost')) {
    http_response_code(403); echo json_encode(['error'=>'Forbidden']); exit;
}

$icao24 = preg_replace('/[^a-f0-9]/i', '', $_GET['icao24'] ?? '');
if(!$icao24){ http_response_code(400); echo json_encode(['error'=>'icao24 requis']); exit; }

$url = "https://opensky-network.org/api/tracks/all?icao24={$icao24}&time=0";
$ctx = stream_context_create(['http'=>['timeout'=>8,'header'=>"User-Agent: casuffit.be/1.0\r\n"]]);
$raw = @file_get_contents($url, false, $ctx);
if($raw===false){ http_response_code(502); echo json_encode(['error'=>'OpenSky indisponible']); exit; }
echo $raw;
