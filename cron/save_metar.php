<?php
// cron/save_metar.php — Enregistre les METARs EBBR en base
// OVH Cron : 0 * * * *   (toutes les heures)
// Récupère les 2 derniers METARs (résolution 30 min malgré cron horaire)

if (!defined('ROOT')) define('ROOT', dirname(__DIR__));
require_once ROOT . '/config.php';

function log_msg(string $msg): void {
    $logfile = ROOT . '/cron/save_metar.log';
    file_put_contents($logfile, date('Y-m-d H:i:s') . ' ' . $msg . PHP_EOL, FILE_APPEND | LOCK_EX);
    if (@filesize($logfile) > 200 * 1024) {
        $lines = file($logfile);
        file_put_contents($logfile, implode('', array_slice($lines, -300)));
    }
}


// ── Fetch rafale IRM (opendata.meteo.be) pour cette heure ────────────────
function fetch_irm_gust(int $obs_ts, $ctx): ?float {
    $irm_ts       = mktime(date('H', $obs_ts), 0, 0, date('n', $obs_ts), date('j', $obs_ts), date('Y', $obs_ts));
    $candidates   = [
        [$irm_ts, $irm_ts + 3600],
        [$irm_ts - 3600, $irm_ts],
    ];
    foreach ($candidates as [$from, $to]) {
        $f   = gmdate('Y-m-d\TH:i:s\Z', $from);
        $t   = gmdate('Y-m-d\TH:i:s\Z', $to);
        $url = 'https://opendata.meteo.be/service/ows?service=WFS&version=2.0.0&request=GetFeature'
             . '&typeName=synop:synop_data&outputFormat=application/json&count=1&sortBy=timestamp+D'
             . '&CQL_FILTER=' . urlencode("code=6451 AND timestamp >= '$f' AND timestamp <= '$t'");
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) continue;
        $feat = json_decode($raw, true)['features'][0]['properties'] ?? null;
        if ($feat && isset($feat['wind_peak_speed']) && $feat['wind_peak_speed'] !== null) {
            return round((float)$feat['wind_peak_speed'] * 1.94384 * 2) / 2; // m/s → kt
        }
    }
    return null;
}

// ── Connexion BDD ─────────────────────────────────────────────────────────
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    log_msg('ERREUR BDD: ' . $e->getMessage()); return;
}

// ── Récupérer les 2 derniers METARs depuis aviationweather.gov ────────────
$ctx = stream_context_create([
    'http' => ['timeout' => 15, 'user_agent' => 'casuffit.be/metar-cron'],
    'ssl'  => ['verify_peer' => false],
]);

$url  = 'https://aviationweather.gov/api/data/metar?ids=EBBR&format=json&taf=false&hoursBeforeNow=1';
$raw  = @file_get_contents($url, false, $ctx);

if ($raw === false) {
    log_msg('ERREUR: impossible de contacter aviationweather.gov'); return;
}

$metars = json_decode($raw, true);
if (!is_array($metars) || empty($metars)) {
    log_msg('ERREUR: réponse inattendue de l\'API'); return;
}

// ── QFU des pistes EBBR pour calcul tw/xw ────────────────────────────────
const QFU_25 = 248.5; // moyenne 25L=251° et 25R=246°

function calc_tw_xw(float $wdir, float $wspd): array {
    $diff = deg2rad($wdir - QFU_25);
    return [
        'tw' => round($wspd * cos($diff), 1),
        'xw' => round(abs($wspd * sin($diff)), 1),
    ];
}

function apply_prs_logic(float $wdir, float $wspd, float $wgst): array {
    // Vent arrière sur piste 25 = composante dans sens de la piste 25
    $diff_rad = deg2rad($wdir - QFU_25);
    $tw_moy   = $wspd  * cos($diff_rad);
    $tw_gust  = $wgst  * cos($diff_rad);

    // PRS AIP actuel (skeyes) : tailwind effectif > 7 kt (on prend max moy/rafale)
    $prs_now  = max($tw_moy, $tw_gust) > 7.0 ? 0 : 1;

    // PRS AIP 2013 légal : tailwind MOYEN > 7 kt ET vent dans plage 350°–100°
    $in_range = ($wdir >= 350 || $wdir <= 100);
    $prs_2013 = ($tw_moy <= 7.0 && $in_range) ? 1 : 0;

    return ['prs_now' => $prs_now, 'prs_2013' => $prs_2013, 'tw' => $tw_moy, 'xw' => abs($wspd * sin($diff_rad))];
}

// ── Insertion en BDD ──────────────────────────────────────────────────────
$sql = "INSERT IGNORE INTO metar_history
        (obs_time, metar_raw, wind_dir, wind_speed, wind_gust, irm_gust, wind_variable,
         temp, qnh, visib_m, ceiling_ft, runways, prs_active, prs_2013, tw_25, xw_25)
        VALUES
        (:obs_time, :metar_raw, :wind_dir, :wind_speed, :wind_gust, :irm_gust, :wind_variable,
         :temp, :qnh, :visib_m, :ceiling_ft, :runways, :prs_active, :prs_2013, :tw_25, :xw_25)";

$stmt   = $pdo->prepare($sql);
$saved  = 0;
$skipped = 0;

foreach ($metars as $m) {
    // Timestamp
    $obs_ts   = (int)($m['obsTime'] ?? 0);
    if ($obs_ts <= 0) continue;
    $obs_time = date('Y-m-d H:i:s', $obs_ts);

    // Vent
    $wind_dir  = isset($m['wdir'])  && $m['wdir']  !== '' ? (int)$m['wdir']  : null;
    $wind_spd  = (int)($m['wspd']   ?? 0);
    $wind_gust = isset($m['wgst'])  && (int)$m['wgst'] > 0 ? (int)$m['wgst'] : null;
    $wind_var  = isset($m['wdir'])  && $m['wdir'] === 'VRB' ? 1 : 0;

    // Logique PRS (simplifiée, direction vraie non dispo ici)
    $prs_active = 0; $prs_2013 = 0; $tw = null; $xw = null;
    if ($wind_dir !== null && $wind_spd > 0) {
        $gust  = max($irm_gust ?? 0, $wind_gust ?? 0, $wind_spd);
        if ($gust == 0) $gust = $wind_spd;
        $calc  = apply_prs_logic((float)$wind_dir, (float)$wind_spd, (float)$gust);
        $prs_active = $calc['prs_now'];
        $prs_2013   = $calc['prs_2013'];
        $tw         = round($calc['tw'], 1);
        $xw         = round($calc['xw'], 1);
    }

    // Pistes (non disponibles sans l'analyse complète — on met vide, metar.php remplit)
    $runways = '';

    // Température
    $temp = isset($m['temp']) && $m['temp'] !== '' ? (int)$m['temp'] : null;

    // QNH
    $qnh = isset($m['altim']) && $m['altim'] > 0 ? (int)round((float)$m['altim'] * 33.8639) : null;

    // Visibilité
    $visib_sm = $m['visib'] ?? null;
    $visib_m  = $visib_sm !== null ? (int)round((float)$visib_sm * 1852) : null;
    if ($visib_m > 9999) $visib_m = 9999;

    // Plafond (premier layer BKN ou OVC)
    $ceiling_ft = null;
    if (!empty($m['clouds'])) {
        foreach ($m['clouds'] as $layer) {
            if (in_array($layer['cover'] ?? '', ['BKN','OVC'], true)) {
                $ceiling_ft = (int)($layer['base'] ?? 0) * 100;
                break;
            }
        }
    }

    // Rafale IRM
    $irm_gust = fetch_irm_gust($obs_ts, $ctx);
    // Vent gust effectif = max(METAR, IRM)
    if ($irm_gust !== null && ($wind_gust === null || $irm_gust > $wind_gust)) {
        // on garde wind_gust METAR séparé, irm_gust séparé
    }

    // METAR brut
    $metar_raw = substr((string)($m['rawOb'] ?? ''), 0, 255);

    try {
        $stmt->execute([
            ':obs_time'      => $obs_time,
            ':metar_raw'     => $metar_raw,
            ':wind_dir'      => $wind_dir,
            ':wind_speed'    => $wind_spd,
            ':wind_gust'     => $wind_gust,
            ':irm_gust'      => $irm_gust,
            ':wind_variable' => $wind_var,
            ':temp'          => $temp,
            ':qnh'           => $qnh,
            ':visib_m'       => $visib_m,
            ':ceiling_ft'    => $ceiling_ft,
            ':runways'       => $runways,
            ':prs_active'    => $prs_active,
            ':prs_2013'      => $prs_2013,
            ':tw_25'         => $tw,
            ':xw_25'         => $xw,
        ]);
        $stmt->rowCount() > 0 ? $saved++ : $skipped++;
    } catch (PDOException $e) {
        log_msg('ERREUR INSERT: ' . $e->getMessage());
    }
}

log_msg("OK — $saved sauvé(s), $skipped ignoré(s) (déjà présents)");
