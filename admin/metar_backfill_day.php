<?php
// admin/metar_backfill_day.php — Backfill 1 jour via IRM synop (données illimitées)
// L'IRM publie des synops HORAIRES pour la station 6451 (EBBR)
// Champs : wind_direction, wind_speed (m/s), wind_peak_speed (m/s), temperature, pressure

require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();
header('Content-Type: application/json; charset=utf-8');

$date = $_GET['date'] ?? date('Y-m-d');
$dry  = isset($_GET['dry']);

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['error' => 'Date invalide']); exit;
}

$ctx = stream_context_create([
    'http' => ['timeout' => 25, 'user_agent' => 'casuffit.be/backfill'],
    'ssl'  => ['verify_peer' => false],
]);

// ── Fetch synop IRM pour toute la journée (24 obs horaires) ──────────────
$from = $date . 'T00:00:00Z';
$to   = $date . 'T23:59:59Z';

$url  = 'https://opendata.meteo.be/service/ows?service=WFS&version=2.0.0&request=GetFeature'
      . '&typeName=synop:synop_data&outputFormat=application/json&count=50&sortBy=timestamp+A'
      . '&CQL_FILTER=' . urlencode("code=6451 AND timestamp >= '$from' AND timestamp <= '$to'");

$raw = @file_get_contents($url, false, $ctx);

if ($raw === false) {
    echo json_encode(['error' => 'API IRM inaccessible', 'date' => $date]); exit;
}

$geojson  = json_decode($raw, true);
$features = $geojson['features'] ?? [];

if (empty($features)) {
    echo json_encode(['date' => $date, 'saved' => 0, 'skipped' => 0, 'records' => [],
                      'info' => 'Aucune donnée IRM pour ce jour (données trop récentes ou trop anciennes)']); exit;
}

const QFU_25_BF = 248.5;

function prs_calc(float $wd, float $ws_kt, float $wg_kt): array {
    $d  = deg2rad($wd - QFU_25_BF);
    $tw = $ws_kt * cos($d);
    $tg = $wg_kt  * cos($d);
    return [
        'prs' => max($tw, $tg) > 7.0 ? 0 : 1,
        'p13' => ($tw <= 7.0 && ($wd >= 350 || $wd <= 100)) ? 1 : 0,
        'tw'  => round($tw, 1),
        'xw'  => round(abs($ws_kt * sin($d)), 1),
    ];
}

$pdo  = getDB();
$stmt = $pdo->prepare("INSERT IGNORE INTO metar_history
    (obs_time, metar_raw, wind_dir, wind_speed, wind_gust, irm_gust, wind_variable,
     temp, qnh, visib_m, ceiling_ft, runways, prs_active, prs_2013, tw_25, xw_25)
    VALUES (:obs_time, :metar_raw, :wind_dir, :wind_speed, :wind_gust, :irm_gust, :wind_variable,
            :temp, :qnh, :visib_m, :ceiling_ft, :runways, :prs_active, :prs_2013, :tw_25, :xw_25)");

$saved = 0; $skipped = 0; $records = [];

foreach ($features as $feat) {
    $p  = $feat['properties'] ?? [];
    $ts = $p['timestamp'] ?? null;
    if (!$ts) continue;

    $obs_time = date('Y-m-d H:i:s', strtotime($ts));

    // Conversion m/s → knots (×1.94384)
    $ws_ms  = isset($p['wind_speed'])      && $p['wind_speed']      !== null ? (float)$p['wind_speed']      : null;
    $wg_ms  = isset($p['wind_peak_speed']) && $p['wind_peak_speed'] !== null ? (float)$p['wind_peak_speed'] : null;
    $wd     = isset($p['wind_direction'])  && $p['wind_direction']  !== null ? (int)$p['wind_direction']    : null;

    $ws_kt  = $ws_ms  !== null ? round($ws_ms  * 1.94384, 1) : 0;
    $wg_kt  = $wg_ms  !== null ? round($wg_ms  * 1.94384, 1) : null;
    $temp   = isset($p['temperature'])     && $p['temperature']     !== null ? (int)round((float)$p['temperature']) : null;
    $qnh    = isset($p['pressure_station'])&& $p['pressure_station']!== null ? (int)round((float)$p['pressure_station']) : null;

    $prs_active = 0; $prs_2013 = 0; $tw = null; $xw = null;
    if ($wd !== null && $ws_kt > 0) {
        $c = prs_calc((float)$wd, $ws_kt, $wg_kt ?? $ws_kt);
        $prs_active = $c['prs']; $prs_2013 = $c['p13'];
        $tw = $c['tw']; $xw = $c['xw'];
    }

    $records[] = [
        'time' => substr($obs_time, 11, 5),
        'wd'   => $wd,
        'ws'   => $ws_kt,
        'wg'   => $wg_kt,
        'irm'  => $wg_kt,
        'prs'  => $prs_active,
        'p13'  => $prs_2013,
    ];

    if (!$dry) {
        $stmt->execute([
            ':obs_time'      => $obs_time,
            ':metar_raw'     => 'IRM-' . substr($ts, 0, 16),
            ':wind_dir'      => $wd,
            ':wind_speed'    => (int)$ws_kt,
            ':wind_gust'     => $wg_kt !== null ? (int)$wg_kt : null,
            ':irm_gust'      => $wg_kt,
            ':wind_variable' => 0,
            ':temp'          => $temp,
            ':qnh'           => $qnh,
            ':visib_m'       => null,
            ':ceiling_ft'    => null,
            ':runways'       => '',
            ':prs_active'    => $prs_active,
            ':prs_2013'      => $prs_2013,
            ':tw_25'         => $tw,
            ':xw_25'         => $xw,
        ]);
        $stmt->rowCount() > 0 ? $saved++ : $skipped++;
    } else {
        $saved++;
    }
}

echo json_encode(['date' => $date, 'saved' => $saved, 'skipped' => $skipped,
                  'records' => $records, 'dry' => $dry, 'source' => 'IRM synop']);
