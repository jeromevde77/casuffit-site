<?php
// api/rose_vents.php — Données IRM mensuelles pour la rose des vents EBBR
// Station 6451 ZAVENTEM/MELSBROEK — résolution horaire depuis 1952

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');

// Validation
if ($year < 2000 || $year > (int)date('Y') || $month < 1 || $month > 12) {
    echo json_encode(['error' => 'Paramètres invalides']); exit;
}

// Cache 24h (les données historiques ne changent pas)
$now       = time();
$cache_dir = sys_get_temp_dir();
$cache_key = "rose_vents_{$year}_{$month}";
$cache_file = $cache_dir . '/' . $cache_key . '.json';
$is_current_month = ($year == (int)date('Y') && $month == (int)date('m'));
$cache_ttl = $is_current_month ? 3600 : 86400 * 7; // 1h si mois courant, 7j sinon

if (file_exists($cache_file) && ($now - filemtime($cache_file)) < $cache_ttl) {
    echo file_get_contents($cache_file); exit;
}

// Calculer les bornes du mois
$ts_start = mktime(0, 0, 0, $month, 1, $year);
$ts_end   = mktime(0, 0, 0, $month == 12 ? 1 : $month + 1, 1, $month == 12 ? $year + 1 : $year);

$from = gmdate('Y-m-d\TH:i:s\Z', $ts_start);
$to   = gmdate('Y-m-d\TH:i:s\Z', $ts_end);

$ctx = stream_context_create([
    'http' => ['timeout' => 30, 'user_agent' => 'piste01casuffit.be/rose-vents'],
    'ssl'  => ['verify_peer' => false],
]);

// IRM WFS — récupérer toutes les observations du mois
// On pagine par blocs de 1000 car le WFS a une limite
$observations = [];
$offset = 0;
$page_size = 1000;
$max_pages = 40; // max ~720 heures/mois × 1 obs/h = ~750 → 1 page suffit

$filter = "code=6451 AND timestamp >= '$from' AND timestamp < '$to'";
$url = 'https://opendata.meteo.be/service/ows?service=WFS&version=2.0.0&request=GetFeature'
     . '&typeName=synop:synop_data&outputFormat=application/json'
     . '&sortBy=timestamp+A'
     . '&count=' . $page_size
     . '&startIndex=' . $offset
     . '&CQL_FILTER=' . urlencode($filter);

$raw = @file_get_contents($url, false, $ctx);
if ($raw === false) {
    echo json_encode(['error' => 'IRM non disponible. Réessayez plus tard.']); exit;
}

$json = json_decode($raw, true);
if (!$json || empty($json['features'])) {
    echo json_encode(['error' => 'Aucune donnée IRM pour cette période.']); exit;
}

foreach ($json['features'] as $feat) {
    $props = $feat['properties'];
    $wdir  = $props['wind_direction']  ?? null;
    $wspd  = $props['wind_speed']      ?? null;      // m/s
    $wgst  = $props['wind_peak_speed'] ?? null;      // m/s

    // Convertir m/s → noeuds
    $spd_kt  = $wspd  !== null ? round($wspd  * 1.94384, 1) : null;
    $gust_kt = $wgst  !== null ? round($wgst  * 1.94384, 1) : null;

    $observations[] = [
        'ts'   => $props['timestamp']     ?? null,
        'dir'  => $wdir,                             // degrés (0-360)
        'spd'  => $spd_kt,                           // noeuds
        'gust' => $gust_kt,                          // noeuds
    ];
}

// Statistiques rapides
$count      = count($observations);
$calm_count = count(array_filter($observations, function($o){ return $o['spd'] < 1; }));
$spd_vals   = array_filter(array_column($observations, 'spd'), function($v){ return $v !== null; });
$avg_spd    = $spd_vals ? round(array_sum($spd_vals) / count($spd_vals), 1) : null;
$max_spd    = $spd_vals ? max($spd_vals) : null;

$mois_noms = ['','Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];

$result = [
    'station'      => 'IRM 6451 — Zaventem/Melsbroek',
    'period'       => $mois_noms[$month] . ' ' . $year,
    'year'         => $year,
    'month'        => $month,
    'count'        => $count,
    'calm_count'   => $calm_count,
    'avg_spd_kt'   => $avg_spd,
    'max_spd_kt'   => $max_spd,
    'observations' => $observations,
];

$encoded = json_encode($result, JSON_UNESCAPED_UNICODE);
file_put_contents($cache_file, $encoded);
echo $encoded;
