<?php
/**
 * api/batc_stats.php — Proxy vers l'API BATC statistiques mouvements
 * Paramètres : date (timestamp Unix), aggregate (day/week/month)
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$date      = (int)($_GET['date']      ?? time());
$aggregate = in_array($_GET['aggregate'] ?? 'day', ['day','week','month']) ? $_GET['aggregate'] : 'day';

$url = 'https://www.batc.be/fr/api/visualisation/statistics_airport_movements'
     . '?time_of_day=day_night'
     . '&aggregate=' . $aggregate
     . '&date=' . $date
     . '&departures_arrivals=departures_arrivals';

$ctx = stream_context_create(['http' => [
    'timeout'       => 10,
    'user_agent'    => 'Mozilla/5.0 (compatible; casuffit-proxy/1.0)',
    'ignore_errors' => true,
]]);

$body = @file_get_contents($url, false, $ctx);
if ($body === false) {
    http_response_code(502);
    echo json_encode(['error' => 'Impossible de contacter BATC']);
    exit;
}

// Relayer tel quel
echo $body;
