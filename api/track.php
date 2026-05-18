<?php
// Proxy trajectoire OpenSky — tracks/all
header('Content-Type: application/json');
header('Cache-Control: max-age=15');

$icao24 = preg_replace('/[^a-f0-9]/i', '', $_GET['icao24'] ?? '');
if(!$icao24){ http_response_code(400); echo json_encode(['error'=>'icao24 requis']); exit; }

$url = "https://opensky-network.org/api/tracks/all?icao24={$icao24}&time=0";
$ctx = stream_context_create(['http'=>['timeout'=>8,'header'=>"User-Agent: casuffit.be/1.0\r\n"]]);
$raw = @file_get_contents($url, false, $ctx);
if($raw===false){ http_response_code(502); echo json_encode(['error'=>'OpenSky indisponible']); exit; }
echo $raw;
