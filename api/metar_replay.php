<?php
// api/metar_replay.php — Rejoue metar.php avec données historiques
// ?id=123  ou  ?date=2026-05-11T10:30:00
header('Content-Type: application/json; charset=utf-8');
define('ROOT', dirname(__DIR__));
require_once ROOT . '/config.php';

$id   = isset($_GET['id'])   ? (int)$_GET['id']   : 0;
$date = isset($_GET['date'])  ? $_GET['date']       : '';

$pdo = getDB();

if ($id > 0) {
    $row = $pdo->prepare("SELECT * FROM metar_history WHERE id=?");
    $row->execute([$id]);
} elseif ($date) {
    $row = $pdo->prepare("SELECT * FROM metar_history WHERE obs_time=? LIMIT 1");
    $row->execute([$date]);
} else {
    echo json_encode(['error' => 'Paramètre id ou date requis']); exit;
}

$r = $row->fetch(PDO::FETCH_ASSOC);
if (!$r) { echo json_encode(['error' => 'Enregistrement introuvable']); exit; }

// ── Constantes pistes EBBR ────────────────────────────────────────────────
const PISTES = [
    '25L' => ['qfu' => 251, 'type' => 'principale'],
    '25R' => ['qfu' => 246, 'type' => 'principale'],
    '07R' => ['qfu' => 71,  'type' => 'sec'],
    '07L' => ['qfu' => 66,  'type' => 'sec'],
    '01'  => ['qfu' => 14,  'type' => 'prs'],
    '19'  => ['qfu' => 194, 'type' => 'sec'],
];

$wd  = $r['wind_dir']   !== null ? (float)$r['wind_dir']   : null;
$ws  = (float)($r['wind_speed'] ?? 0);
$wg  = $r['irm_gust']  !== null ? (float)$r['irm_gust']  : ($r['wind_gust'] !== null ? (float)$r['wind_gust'] : null);
$wgm = $r['wind_gust'] !== null ? (float)$r['wind_gust']  : null;
$wgi = $r['irm_gust']  !== null ? (float)$r['irm_gust']   : null;
$ws_eff = max($ws, $wg ?? 0);

// ── Composantes pour chaque piste ─────────────────────────────────────────
$components = [];
foreach (PISTES as $rwy => $cfg) {
    $diff   = deg2rad(($wd ?? 0) - $cfg['qfu']);
    $tw_moy = $wd !== null ? round($ws  * cos($diff), 1) : null;
    $tw_gst = $wd !== null && $wg ? round($wg * cos($diff), 1) : $tw_moy;
    $xw     = $wd !== null ? round(abs($ws * sin($diff)), 1) : null;
    $xw_g   = $wd !== null && $wg ? round(abs($wg * sin($diff)), 1) : $xw;
    $components[$rwy] = [
        'qfu'    => $cfg['qfu'],
        'tw_moy' => $tw_moy,
        'tw_gst' => $tw_gst,
        'xw_moy' => $xw,
        'xw_gst' => $xw_g,
    ];
}

// ── JSON compatible metar.php ─────────────────────────────────────────────
echo json_encode([
    'replay'     => true,
    'obs_time'   => $r['obs_time'],
    'metar'      => $r['metar_raw'] ?: ('Données IRM — ' . $r['obs_time']),
    'wdir'       => $wd !== null ? (int)$wd : null,
    'wspd'       => (int)$ws,
    'wspd_eff'   => (int)$ws_eff,
    'wgst'       => $wg !== null ? (int)$wg : null,
    'wgst_metar' => $wgm !== null ? (int)$wgm : null,
    'wgst_irm'   => $wgi !== null ? (int)$wgi : null,
    'variable'   => (bool)$r['wind_variable'],
    'temp'       => $r['temp'],
    'qnh'        => $r['qnh'],
    'prs_active' => (bool)$r['prs_active'],
    'aip2013'    => ['prs_active' => (bool)$r['prs_2013']],
    'tw_25_max'  => $r['tw_25'],
    'xw_25_max'  => $r['xw_25'],
    'runways'    => $r['runways'] ? explode(',', $r['runways']) : [],
    'components' => $components,
    'source'     => 'metar_history',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
