<?php
// api/metar_history.php — Analyse historique vent EBBR
// Sources (par priorité) :
//   1. IRM WFS synop:synop_data station 6451 (Zaventem/EBBR) — mesures réelles depuis 1952, horaire
//   2. NOAA METAR (15 derniers jours) — mesures réelles ICAO
//   3. Open-Meteo ERA5 (fallback) — réanalyse horaire depuis 1940

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$date_start = $_GET['start'] ?? '';
$date_end   = $_GET['end']   ?? '';

if (!$date_start || !$date_end) {
    echo json_encode(['error' => 'Paramètres start et end requis (format: 2024-11-15)']); exit;
}

// Nettoyer le format ISO — supprimer les millisecondes (.000) que JS ajoute
$date_start = preg_replace('/\.\d+Z$/', 'Z', $date_start);
$date_end   = preg_replace('/\.\d+Z$/', 'Z', $date_end);

$ts_start = strtotime($date_start);
$ts_end   = strtotime($date_end);
if (!$ts_start || !$ts_end || $ts_end < $ts_start) {
    echo json_encode(['error' => 'Dates invalides']); exit;
}
if (($ts_end - $ts_start) > 8 * 24 * 3600) {
    echo json_encode(['error' => 'Plage maximum 8 jours']); exit;
}

$ctx = stream_context_create([
    'http' => ['timeout' => 15, 'user_agent' => 'piste01casuffit.be/historique-vent'],
    'ssl'  => ['verify_peer' => false],
]);

// ── Seuils AIP ──────────────────────────────────────────────────────────
$SEUILS_2013 = ['tw'=>7,'xw'=>15,'tw_25_moy'=>7,'xw_25_moy'=>15,'tw_25_gust'=>10,'xw_25_gust'=>20,'tw_max'=>10,'xw_max'=>20];
$SEUILS_NOW  = ['tw'=>7,'xw'=>20,'tw_25_moy'=>7,'xw_25_moy'=>20,'tw_25_gust'=>7, 'xw_25_gust'=>20,'tw_max'=>7, 'xw_max'=>20];

function hwComp($wdir, $wspd, $qfu) {
    if (!$wdir || !$wspd) return 0;
    return round($wspd * cos(deg2rad($wdir - $qfu)), 1);
}
function xwComp($wdir, $wspd, $qfu) {
    if (!$wdir || !$wspd) return 0;
    return round(abs($wspd * sin(deg2rad($wdir - $qfu))), 1);
}
function prsStatus($wdir, $wspd, $seuils, $planning = null, $wgst = null) {
    $variable = (!$wdir || $wdir === 0);
    $wspd_eff = max($wspd, $wgst ?? 0);
    if ($variable || $wspd_eff < 3) {
        $rwys = $planning ? array_values(array_unique(array_merge($planning['dep'], $planning['arr']))) : ['25R','25L'];
        return ['prs'=>true,'runways'=>$rwys,'alert'=>false,'exceptions'=>[],'tw'=>0,'xw'=>0];
    }
    // Vent moyen
    $tw25_moy = max(0, -hwComp($wdir,$wspd,246), -hwComp($wdir,$wspd,251));
    $xw25_moy = max(xwComp($wdir,$wspd,246), xwComp($wdir,$wspd,251));
    // Rafales
    $tw25_gust = $wgst ? max(0, -hwComp($wdir,$wgst,246), -hwComp($wdir,$wgst,251)) : null;
    $xw25_gust = $wgst ? max(xwComp($wdir,$wgst,246), xwComp($wdir,$wgst,251)) : null;
    $tw25 = max($tw25_moy, $tw25_gust ?? 0); // pour affichage
    $xw25 = max($xw25_moy, $xw25_gust ?? 0);

    $tw_moy_s  = $seuils['tw_25_moy']  ?? $seuils['tw']  ?? 7;
    $xw_moy_s  = $seuils['xw_25_moy']  ?? $seuils['xw']  ?? 15;
    $tw_gust_s = $seuils['tw_25_gust'] ?? $seuils['tw_max'] ?? 10;
    $xw_gust_s = $seuils['xw_25_gust'] ?? $seuils['xw_max'] ?? 20;

    $exc = [];
    if ($tw25_moy > $tw_moy_s)   $exc[] = 'Vent arrière moyen '.$tw25_moy.' kt > '.$tw_moy_s.' kt';
    if ($tw25_gust !== null && $tw25_gust > $tw_gust_s)
        $exc[] = 'Rafale arrière '.$tw25_gust.' kt > '.$tw_gust_s.' kt';
    if ($xw25_moy > $xw_moy_s)   $exc[] = 'Vent latéral moyen '.$xw25_moy.' kt > '.$xw_moy_s.' kt';
    if ($xw25_gust !== null && $xw25_gust > $xw_gust_s)
        $exc[] = 'Rafale latérale '.$xw25_gust.' kt > '.$xw_gust_s.' kt';
    $prs = empty($exc);
    $d = $wdir % 360;
    if ($prs && $planning) {
        // Vérifier que les pistes du planning sont utilisables (pas de vent arrière > 5 kt)
        $plan_rwys = array_values(array_unique(array_merge($planning['dep'], $planning['arr'])));
        $rwys_ok = [];
        $rwys_ko = [];
        $RUNWAYS_QFU = ['25R'=>246,'25L'=>251,'07L'=>66,'07R'=>71,'01'=>14,'19'=>194];
        foreach ($plan_rwys as $rwy) {
            if (!isset($RUNWAYS_QFU[$rwy])) { $rwys_ok[] = $rwy; continue; }
            $qfu = $RUNWAYS_QFU[$rwy];
            $tw_rwy = max(0, -hwComp($wdir, $wspd, $qfu));
            $tw_g   = $wgst ? max(0, -hwComp($wdir, $wgst, $qfu)) : $tw_rwy;
            if (max($tw_rwy, $tw_g) > 5) {
                $rwys_ko[] = $rwy;
            } else {
                $rwys_ok[] = $rwy;
            }
        }
        if (!empty($rwys_ko) && empty($rwys_ok)) {
            // Toutes inutilisables → HORS PRS
            $prs = false;
            $exc[] = 'Config planning inutilisable (' . implode(', ', $rwys_ko) . ')';
            $rwys = (($d>=350||$d<40) ? ['01'] : ($d>=40&&$d<160 ? ['07L'] : ($d>=160&&$d<220 ? ['19'] : ['25R','25L'])));
        } else {
            $rwys = !empty($rwys_ok) ? $rwys_ok : $plan_rwys;
        }
    } elseif ($prs) {
        $rwys = ['25R','25L'];
    } else {
        $rwys = (($d>=350||$d<40) ? ['01'] :
                ($d>=40&&$d<160   ? ['07L'] :
                ($d>=160&&$d<220  ? ['19'] : ['25R','25L'])));
    }
    return ['prs'=>$prs,'runways'=>$rwys,'alert'=>!$prs,'exceptions'=>$exc,'tw'=>$tw25,'xw'=>$xw25];
}
// Planning AIP 2013
require_once __DIR__ . '/aip_planning.php';

function analyseEntry($wdir, $wspd, $wgst, $planning = null) {
    global $SEUILS_2013, $SEUILS_NOW;
    $wspd_eff = max($wspd, $wgst ?? 0);
    $a2013 = prsStatus($wdir, $wspd, $SEUILS_2013, $planning, $wgst);
    $anow  = prsStatus($wdir, $wspd, $SEUILS_NOW,  $planning, $wgst);
    return [
        'aip2013'    => $a2013,
        'aip_now'    => $anow,
        'divergence' => $a2013['prs'] !== $anow['prs'],
        'wspd_eff'   => $wspd_eff,
    ];
}

$results = [];
$source  = '';
$note    = '';

// ── 1. IRM WFS — station 6451 Zaventem/EBBR ─────────────────────────────
// wind_speed et wind_peak_speed sont en m/s (wind_speed_unit=1)
// Conversion en nœuds : × 1.94384
$irm_start = gmdate('Y-m-d\TH:i:s\Z', $ts_start);
$irm_end   = gmdate('Y-m-d\TH:i:s\Z', $ts_end);
$irm_filter = "code=6451 AND timestamp >= '$irm_start' AND timestamp <= '$irm_end'";
$irm_url = 'https://opendata.meteo.be/service/ows?service=WFS&version=2.0.0&request=GetFeature'
         . '&typeName=synop:synop_data&outputFormat=application/json&sortBy=timestamp+A'
         . '&CQL_FILTER=' . urlencode($irm_filter);

$raw = @file_get_contents($irm_url, false, $ctx);
if ($raw !== false) {
    $data = json_decode($raw, true);
    foreach ($data['features'] ?? [] as $f) {
        $p    = $f['properties'];
        $wspd_ms = $p['wind_speed']      ?? null;
        $wgst_ms = $p['wind_peak_speed'] ?? null;
        // Dans les données IRM, wind_direction=0 peut signifier nord (000°)
        // ou absence de mesure. On considère variable seulement si wspd=0
        $wdir = ($p['wind_direction'] === null || ($p['wind_direction'] === 0 && $wspd_ms == 0))
            ? null  // variable/calme
            : (int)$p['wind_direction'];

        // Conversion m/s → nœuds (arrondi à 0.5 kt)
        $wspd_kt = $wspd_ms !== null ? round($wspd_ms * 1.94384 * 2) / 2 : 0;
        $wgst_kt = $wgst_ms !== null ? round($wgst_ms * 1.94384 * 2) / 2 : null;

        // Si rafales <= vent moyen, pas de rafales
        if ($wgst_kt !== null && $wgst_kt <= $wspd_kt) $wgst_kt = null;

        $pl_irm   = getAipConfig($t);
        $analysis = analyseEntry($wdir, $wspd_kt, $wgst_kt, $pl_irm);

        $results[] = [
            'time'        => $p['timestamp'] ?? '',
            'source'      => 'IRM',
            'wdir'        => $wdir ?? 'VRB',
            'wspd_ms'     => $wspd_ms,
            'wgst_ms'     => $wgst_ms,
            'wspd_kt'     => $wspd_kt,
            'wgst_kt'     => $wgst_kt,
            'wspd_eff'    => $analysis['wspd_eff'],
            'aip2013'     => $analysis['aip2013'],
            'aip_now'     => $analysis['aip_now'],
            'divergence'  => $analysis['divergence'],
            'aip_planning' => getAipConfig($t),
        ];
    }
    if (!empty($results)) {
        $source = 'IRM — Station 6451 Zaventem/EBBR (mesures réelles, résolution horaire)';
        $note   = '✓ Données officielles IRM. Vent en m/s converti en nœuds. wind_peak_speed = rafale max sur la période.';
    }
}

// ── 2. NOAA METAR + enrichissement rafales IRM ────────────────────────────
// Toujours essayé si < 15 jours, en complément ou fallback de l'IRM
$now = time();
if (empty($results) && ($now - $ts_start) / 86400 <= 15) {
    $url = 'https://aviationweather.gov/api/data/metar?ids=EBBR&format=json'
         . '&startTime=' . urlencode($irm_start)
         . '&endTime='   . urlencode($irm_end);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw !== false) {
        $data = json_decode($raw, true);

        // Charger aussi les données IRM pour enrichir les rafales
        $irm_gusts = []; // index: heure arrondie → wgst_kt IRM
        $irm_raw2 = @file_get_contents(
            'https://opendata.meteo.be/service/ows?service=WFS&version=2.0.0&request=GetFeature'
            . '&typeName=synop:synop_data&outputFormat=application/json&sortBy=timestamp+A'
            . '&CQL_FILTER=' . urlencode("code=6451 AND timestamp >= '$irm_start' AND timestamp <= '$irm_end'"),
            false, $ctx
        );
        if ($irm_raw2) {
            $irm_data2 = json_decode($irm_raw2, true);
            foreach ($irm_data2['features'] ?? [] as $f) {
                $p = $f['properties'];
                $ts = strtotime($p['timestamp']);
                $hr = date('Y-m-d\TH', $ts); // clé = heure arrondie
                $wgst_ms = $p['wind_peak_speed'] ?? null;
                if ($wgst_ms !== null) {
                    $irm_gusts[$hr] = round($wgst_ms * 1.94384 * 2) / 2;
                }
            }
        }

        foreach ($data ?? [] as $m) {
            $wdir = isset($m['wdir']) ? (int)$m['wdir'] : null;
            $wspd = isset($m['wspd']) ? (int)$m['wspd'] : 0;
            $wgst_metar = isset($m['wgst']) ? (int)$m['wgst'] : null;

            // Chercher la rafale IRM pour cette heure
            $t_metar = strtotime($m['reportTime'] ?? $m['obsTime'] ?? '');
            $hr_key  = $t_metar ? date('Y-m-d\TH', $t_metar) : null;
            $wgst_irm = ($hr_key && isset($irm_gusts[$hr_key])) ? $irm_gusts[$hr_key] : null;

            // Prendre la rafale la plus haute : METAR ou IRM
            $wgst_eff = null;
            if ($wgst_metar !== null && $wgst_irm !== null)
                $wgst_eff = max($wgst_metar, $wgst_irm);
            elseif ($wgst_metar !== null) $wgst_eff = $wgst_metar;
            elseif ($wgst_irm  !== null) $wgst_eff = $wgst_irm;

            $pl_noaa  = $t_metar ? getAipConfig($t_metar) : null;
            $analysis = analyseEntry($wdir, $wspd, $wgst_eff, $pl_noaa);
            $results[] = [
                'time'        => $m['reportTime'] ?? $m['obsTime'] ?? '',
                'source'      => 'METAR+IRM',
                'metar'       => $m['rawOb'] ?? '',
                'wdir'        => $wdir ?? 'VRB',
                'wspd_kt'     => $wspd,
                'wgst_kt'     => $wgst_eff,       // meilleure des deux
                'wgst_metar'  => $wgst_metar,      // rafale publiée METAR
                'wgst_irm'    => $wgst_irm,        // rafale IRM (même heure)
                'wspd_eff'    => $analysis['wspd_eff'],
                'aip2013'     => $analysis['aip2013'],
                'aip_now'     => $analysis['aip_now'],
                'divergence'  => $analysis['divergence'],
                'aip_planning'=> $t_metar ? getAipConfig($t_metar) : null,
            ];
        }
        if (!empty($results)) {
            $source = 'NOAA METARs officiels ICAO + rafales IRM Station 6451';
            $note   = '✓ Vent officiel METAR NOAA. Rafales : max(METAR, IRM). '
                    . ($irm_gusts ? 'IRM enrichissement actif (' . count($irm_gusts) . ' mesures).' : 'IRM non disponible pour cette période.');
        }
    }
}

// ── 3. Open-Meteo ERA5 fallback ───────────────────────────────────────────
if (empty($results)) {
    $d_start = date('Y-m-d', $ts_start);
    $d_end   = date('Y-m-d', $ts_end);
    $url = 'https://archive-api.open-meteo.com/v1/archive'
         . '?latitude=50.896&longitude=4.526'
         . '&start_date='.$d_start.'&end_date='.$d_end
         . '&hourly=wind_speed_10m,wind_direction_10m,wind_gusts_10m'
         . '&wind_speed_unit=kn&timezone=UTC';
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw !== false) {
        $data = json_decode($raw, true);
        if ($data && isset($data['hourly'])) {
            $times  = $data['hourly']['time'];
            $speeds = $data['hourly']['wind_speed_10m'];
            $dirs   = $data['hourly']['wind_direction_10m'];
            $gusts  = $data['hourly']['wind_gusts_10m'];
            for ($i = 0; $i < count($times); $i++) {
                $t = strtotime($times[$i].'Z');
                if ($t < $ts_start || $t > $ts_end) continue;
                $wdir = (int)round($dirs[$i] ?? 0);
                $wspd = round($speeds[$i] ?? 0, 1);
                $wgst = round($gusts[$i]  ?? 0, 1);
                if ($wgst <= $wspd) $wgst = null;
                $pl_era   = getAipConfig($t);
                $analysis = analyseEntry($wdir, $wspd, $wgst, $pl_era);
                $results[] = [
                    'time'      => $times[$i].'Z',
                    'source'    => 'ERA5',
                    'wdir'      => $wdir,
                    'wspd_kt'   => $wspd,
                    'wgst_kt'   => $wgst,
                    'wspd_eff'  => $analysis['wspd_eff'],
                    'aip2013'   => $analysis['aip2013'],
                    'aip_now'   => $analysis['aip_now'],
                    'divergence'   => $analysis['divergence'],
                    'aip_planning' => getAipConfig($t),
                ];
            }
            if (!empty($results)) {
                $source = 'Open-Meteo ERA5 (réanalyse — données modélisées)';
                $note   = '⚠ Données de réanalyse ERA5 — pas des mesures directes. Pour validation juridique, préférer IRM ou METAR.';
            }
        }
    }
}

if (empty($results)) {
    echo json_encode(['error'=>'Aucune donnée disponible','results'=>[]]); exit;
}

usort($results, function($a,$b){ return strcmp($a['time'],$b['time']); });

$divs = array_filter($results, function($r){ return $r['divergence']; });

echo json_encode([
    'period_start' => date('Y-m-d', $ts_start),
    'period_end'   => date('Y-m-d', $ts_end),
    'count'        => count($results),
    'divergences'  => count($divs),
    'source'       => $source,
    'note'         => $note,
    'seuils_2013'  => $SEUILS_2013,
    'seuils_now'   => $SEUILS_NOW,
    'results'      => $results,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
