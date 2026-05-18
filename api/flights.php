<?php
header('Content-Type: application/json');
header('Cache-Control: max-age=30');

$lamin = isset($_GET['lamin']) ? floatval($_GET['lamin']) : 50.1;
$lomin = isset($_GET['lomin']) ? floatval($_GET['lomin']) : 3.5;
$lamax = isset($_GET['lamax']) ? floatval($_GET['lamax']) : 51.7;
$lomax = isset($_GET['lomax']) ? floatval($_GET['lomax']) : 5.5;

$url = "https://opensky-network.org/api/states/all"
     . "?lamin={$lamin}&lomin={$lomin}&lamax={$lamax}&lomax={$lomax}";

$ctx = stream_context_create(['http'=>['timeout'=>10,'header'=>"User-Agent: casuffit.be/1.0\r\n"]]);
$raw = @file_get_contents($url, false, $ctx);
if($raw===false){ http_response_code(502); echo json_encode(['error'=>'OpenSky indisponible']); exit; }
echo $raw;
