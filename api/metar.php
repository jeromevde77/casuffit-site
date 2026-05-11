<?php
// api/metar.php — METAR + TAF EBBR, composantes de vent pour toutes les pistes
// QFU magnétiques exacts (Jeppesen / AIP) :
//   07L = 066°  /  25R = 246°
//   07R = 071°  /  25L = 251°
//   01  = 014°  /  19  = 194°
//
// SEUILS PRS (vent arrière sur pistes 25, rafales incluses) :
//   AIP 2013 légal  : 7 kt (texte) mais seuil pratique observé : 6.5 kt
//   AIP actuel      : 7 kt (texte) mais seuil pratique observé : 6.5 kt
//   → En pratique skeyes bascule dès 6.5 kt (avant même les 7 kt légaux)

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// ── DEBUG : capturer TOUTE erreur PHP et la renvoyer dans le JSON ─────────
$DEBUG_TRAP = isset($_GET['debug']) || isset($_GET['nocache']);
register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
        }
        echo json_encode(['error'=>'Fatal PHP','type'=>$err['type'],'msg'=>$err['message'],'file'=>basename($err['file']??''),'line'=>$err['line']??0]);
    }
});

$CACHE_FILE = sys_get_temp_dir() . '/metar_ebbr_v6.json';
$CACHE_TTL  = 600;

// Ne PAS servir le cache si on demande explicitement nocache OU si le cache contient une erreur
if (!$DEBUG_TRAP && file_exists($CACHE_FILE) && (time() - filemtime($CACHE_FILE)) < $CACHE_TTL) {
    $cached = (string)@file_get_contents($CACHE_FILE);
    if ($cached !== '') {
        $cd = json_decode($cached, true);
        // Vérifier que c'est bien un array avant tout accès (json_decode retourne null sur JSON invalide)
        if (is_array($cd) && empty($cd['error'])) {
            header('Cache-Control: public, max-age=600');
            echo $cached; exit;
        }
    }
    // Cache vide, corrompu ou en erreur → supprimer et régénérer
    @unlink($CACHE_FILE);
}
header('Cache-Control: no-store');

$ctx = stream_context_create([
    'http' => ['timeout' => 10, 'user_agent' => 'piste01casuffit.be/meteo-widget'],
    'ssl'  => ['verify_peer' => false],
]);

$metar_raw = @file_get_contents('https://aviationweather.gov/api/data/metar?ids=EBBR&format=json&taf=false', false, $ctx);
$taf_raw   = @file_get_contents('https://aviationweather.gov/api/data/taf?ids=EBBR&format=json', false, $ctx);

if ($metar_raw === false) {
    if (file_exists($CACHE_FILE)) { echo file_get_contents($CACHE_FILE); }
    else { echo json_encode(['error' => 'Données météo temporairement indisponibles']); }
    exit;
}

$metar_data = json_decode($metar_raw, true);
$taf_data   = json_decode($taf_raw, true);

if (!$metar_data || empty($metar_data[0])) {
    echo json_encode(['error' => 'Format METAR inattendu']); exit;
}
$m = $metar_data[0];

// ── QFU des pistes EBBR (magnétique) ────────────────────────────────────
// On utilise le QFU de la piste dans le sens d'atterrissage/décollage
// La composante de vent se calcule par rapport à la direction d'utilisation
$RUNWAYS = [
    '07L' => ['qfu' => 66,  'pair' => '25R'],
    '25R' => ['qfu' => 246, 'pair' => '07L'],
    '07R' => ['qfu' => 71,  'pair' => '25L'],
    '25L' => ['qfu' => 251, 'pair' => '07R'],
    '01'  => ['qfu' => 14,  'pair' => '19'],
    '19'  => ['qfu' => 194, 'pair' => '01'],
];

// ── Fonctions de composantes ─────────────────────────────────────────────
// headwind : positif = vent de face, négatif = vent arrière
// On passe wdir et wspd, on reçoit la composante dans l'axe de la piste
function headwindComp($wdir, $wspd, $qfu) {
    if ($wdir === null || $wspd == 0) return 0;
    return round($wspd * cos(deg2rad($wdir - $qfu)), 1);
}
function crosswindComp($wdir, $wspd, $qfu) {
    if ($wdir === null || $wspd == 0) return 0;
    return round(abs($wspd * sin(deg2rad($wdir - $qfu))), 1);
}

// Calcule les composantes pour toutes les pistes
// Retourne vent moyen ET rafales séparément
function allRunwayComponents($wdir, $wspd, $RUNWAYS, $wgst = null) {
    $result = [];
    foreach ($RUNWAYS as $name => $info) {
        $hw = headwindComp($wdir, $wspd, $info['qfu']);
        $xw = crosswindComp($wdir, $wspd, $info['qfu']);
        $tw = $hw < 0 ? abs($hw) : 0;
        $hf = $hw > 0 ? $hw : 0;
        // Composantes rafales (si disponibles)
        $hw_g = $wgst ? headwindComp($wdir, $wgst, $info['qfu']) : null;
        $xw_g = $wgst ? crosswindComp($wdir, $wgst, $info['qfu']) : null;
        $tw_g = $hw_g !== null ? ($hw_g < 0 ? abs($hw_g) : 0) : null;
        $hf_g = $hw_g !== null ? ($hw_g > 0 ? $hw_g : 0) : null;
        $result[$name] = [
            'hw'   => $hw, 'xw'   => $xw, 'tw'   => $tw, 'hf'   => $hf,
            'hw_g' => $hw_g, 'xw_g' => $xw_g, 'tw_g' => $tw_g, 'hf_g' => $hf_g,
        ];
    }
    return $result;
}

// ── Seuils AIP selon la version ─────────────────────────────────────────
// AIP sept. 2013 (AMDT 10/2013) — base légale instruction ministérielle 17/07/2013
$SEUILS_2013 = [
    // AIP 2013 section 4.2.2 EXCEPTIONS — deux seuils distincts :
    // Vent MOYEN : seuil à partir duquel le PRS cesse d'être déterminant
    'tw_25_moy'   => 7,    // vent arrière moyen > 7 kt → hors PRS
    'xw_25_moy'   => 15,   // vent latéral moyen > 15 kt → hors PRS
    // Rafales : seuil max toléré (même avec vent moyen OK)
    'tw_25_gust'  => 10,   // rafale arrière > 10 kt → hors PRS
    'xw_25_gust'  => 20,   // rafale latérale > 20 kt → hors PRS
    // Pistes alternatives (01/07) — AIP 2013 tableau
    'tw_alt_moy'  => 0,    // vent arrière nominal 0 kt (VAR 0-3 kt)
    'tw_alt_gust' => 5,    // rafale arrière max 5 kt
    'xw_alt_moy'  => 15,
    'xw_alt_gust' => 20,
    // Rétro-compatibilité (pour code existant)
    'tw_25' => 7, 'xw_25' => 15, 'tw_25_max' => 10, 'xw_25_max' => 20,
];
// AIP actuel (skeyes 2025) — contesté juridiquement
// Pas de tableau de composantes par piste — seulement règle générale PRS
$SEUILS_NOW = [
    'tw_25'      => 7,   // identique à 2013
    'tw_25_max'  => 7,   // pas de max distinct
    'xw_25'      => 20,  // PLUS PERMISSIF : 20kt au lieu de 15kt
    'xw_25_max'  => 20,
    'tw_alt'     => 99,  // Aucune limite sur 01/07 — NON MENTIONNÉ
    'tw_alt_max' => 99,
    'xw_alt'     => 20,
    'xw_alt_max' => 20,
];
// Seuil pratique observé : skeyes bascule en pratique dès ~6.5 kt
// avant même les 7 kt légaux (vent instantané ou marge opérationnelle)
$SEUILS_PRATIQUE = [
    'tw_25'      => 6.5,
    'tw_25_max'  => 6.5,
    'xw_25'      => 20,
    'xw_25_max'  => 20,
    'tw_alt'     => 99,
    'tw_alt_max' => 99,
    'xw_alt'     => 20,
    'xw_alt_max' => 20,
];

// ── Logique PRS + configuration probable ─────────────────────────────────
// $seuils : tableau de seuils selon l'AIP à appliquer
// ── Sélection de piste alternative selon secteur et composantes ──────────
// Retourne ['piste'] et la raison, en appliquant les règles officielles
function selectAltRunway($wdir, $comps, $comps_g) {
    $d = ((int)$wdir) % 360;
    $XW_MAX = 20; // crosswind max sur piste alternative

    // Calcul vent arrière effectif (max moyen/rafale) pour une piste
    $tw_eff = function($rwy) use ($comps, $comps_g) {
        return max($comps[$rwy]['tw'] ?? 0, $comps_g ? ($comps_g[$rwy]['tw'] ?? 0) : 0);
    };
    // Calcul crosswind effectif pour une piste
    $xw_eff = function($rwy) use ($comps, $comps_g) {
        return max($comps[$rwy]['xw'] ?? 0, $comps_g ? ($comps_g[$rwy]['xw'] ?? 0) : 0);
    };

    if ($d >= 335 || $d < 40) {
        // Secteur nord → 01 en priorité
        $tw_01 = $tw_eff('01');
        $xw_01 = $xw_eff('01');
        if ($xw_01 > $XW_MAX)
            return [['19'], sprintf('Vent %d° — 01 impossible (crosswind %.1f kt > %d kt) → 19.', $d, $xw_01, $XW_MAX)];
        if ($tw_01 <= 3)
            return [['01'], sprintf('Vent %d° — config 01/01 (face %.1f kt, arrière %.1f kt).', $d, $comps['01']['hf'], $tw_01)];
        // tw_01 > 3 → essayer 07L
        $tw_07L = $tw_eff('07L');
        $xw_07L = $xw_eff('07L');
        if ($xw_07L > $XW_MAX)
            return [['01'], sprintf('Vent %d° — 07L impossible (crosswind %.1f kt) → retour 01 malgré arrière %.1f kt.', $d, $xw_07L, $tw_01)];
        return [['01','07R'], sprintf('Vent %d° — config 01/07R (arrière 01=%.1f kt > 3 → combiné avec 07R).', $d, $tw_01)];

    } elseif ($d >= 40 && $d < 130) {
        // Secteur NE→SE → 07L en priorité
        $tw_07L = $tw_eff('07L');
        $xw_07L = $xw_eff('07L');
        if ($xw_07L > $XW_MAX)
            return [['19'], sprintf('Vent %d° — 07L impossible (crosswind %.1f kt > %d kt) → 19.', $d, $xw_07L, $XW_MAX)];
        if ($tw_07L <= 3)
            return [['07L','07R'], sprintf('Vent %d° — config 07L/07R (face %.1f kt, arrière %.1f kt).', $d, $comps['07L']['hf'], $tw_07L)];
        // tw_07L > 3 → essayer 19
        $xw_19 = $xw_eff('19');
        if ($xw_19 > $XW_MAX)
            return [['07L','07R'], sprintf('Vent %d° — 19 impossible (crosswind %.1f kt) → maintien 07L/07R malgré arrière %.1f kt.', $d, $xw_19, $tw_07L)];
        return [['19'], sprintf('Vent %d° — config 19/19 (arrière 07L=%.1f kt > 3 → passage 19).', $d, $tw_07L)];

    } else {
        // Secteur S/SO/O/NO (130°–334°) → 19
        $xw_19 = $xw_eff('19');
        if ($xw_19 > $XW_MAX)
            return [['07L','07R'], sprintf('Vent %d° — 19 impossible (crosswind %.1f kt > %d kt) → 07L/07R.', $d, $xw_19, $XW_MAX)];
        return [['19'], sprintf('Vent %d° — config 19/19 (face %.1f kt).', $d, $comps['19']['hf'])];
    }
}

function analyseConfig($wdir, $wspd, $wgst, $visib_m, $ceiling_ft, $variable, $RUNWAYS, $seuils, $planning = null) {
    $wspd_eff = max($wspd, $wgst ?? 0); // pour la sélection de piste alternative

    $comps     = allRunwayComponents($wdir, $wspd, $RUNWAYS);      // composantes vent MOYEN
    $comps_g   = $wgst ? allRunwayComponents($wdir, $wgst, $RUNWAYS) : null; // composantes RAFALES

    $tw_25_moy  = max($comps['25R']['tw'],  $comps['25L']['tw']);
    $xw_25_moy  = max($comps['25R']['xw'],  $comps['25L']['xw']);
    $tw_25_gust = $comps_g ? max($comps_g['25R']['tw'], $comps_g['25L']['tw']) : null;
    $xw_25_gust = $comps_g ? max($comps_g['25R']['xw'], $comps_g['25L']['xw']) : null;

    // Seuils selon AIP : vent moyen ET rafales sont des seuils indépendants
    $tw_moy_seuil  = $seuils['tw_25_moy']  ?? $seuils['tw_25']     ?? 7;
    $xw_moy_seuil  = $seuils['xw_25_moy']  ?? $seuils['xw_25']     ?? 15;
    $tw_gust_seuil = $seuils['tw_25_gust'] ?? $seuils['tw_25_max'] ?? 10;
    $xw_gust_seuil = $seuils['xw_25_gust'] ?? $seuils['xw_25_max'] ?? 20;

    $exceptions = [];
    // Vent moyen arrière
    if ($tw_25_moy > $tw_moy_seuil)
        $exceptions[] = 'Vent arrière moyen '.$tw_25_moy.' kt > '.$tw_moy_seuil.' kt';
    // Rafales arrière (si vent moyen OK mais rafales > max)
    if ($tw_25_gust !== null && $tw_25_gust > $tw_gust_seuil)
        $exceptions[] = 'Rafale arrière '.$tw_25_gust.' kt > '.$tw_gust_seuil.' kt (max toléré)';
    // Vent moyen latéral
    if ($xw_25_moy > $xw_moy_seuil)
        $exceptions[] = 'Vent latéral moyen '.$xw_25_moy.' kt > '.$xw_moy_seuil.' kt';
    // Rafales latérales
    if ($xw_25_gust !== null && $xw_25_gust > $xw_gust_seuil)
        $exceptions[] = 'Rafale latérale '.$xw_25_gust.' kt > '.$xw_gust_seuil.' kt (max toléré)';
    if ($visib_m !== null && $visib_m < 1900)
        $exceptions[] = 'Visibilité '.$visib_m.' m < 1 900 m';
    if ($ceiling_ft !== null && $ceiling_ft < 500)
        $exceptions[] = 'Plafond '.$ceiling_ft.' ft < 500 ft';

    // Pour compatibilité avec le code existant
    $tw_25_max = $tw_25_gust ?? $tw_25_moy;
    $xw_25_max = $xw_25_gust ?? $xw_25_moy;

    $prs_active = empty($exceptions);
    $runways = []; $reason = ''; $alert = false;

    // ── Seuil de basculement hors PRS : composante arrière sur 25R > 6.5 kt ──
    // (vent moyen OU rafales — seuil pratique skeyes)
    $tw_25R_eff = max($comps['25R']['tw'], $comps_g ? $comps_g['25R']['tw'] : 0);

    // ── Crosswind 25R — axe de référence 335° ──
    // QFU 25R = 246° → axe perpendiculaire = 246+90 = 336° ≈ 335°
    $xw_25R_eff = max($comps['25R']['xw'], $comps_g ? $comps_g['25R']['xw'] : 0);


    if ($variable || $wspd_eff < 3) {
        // Vent calme/variable → PRS par défaut, config selon planning
        $runways = $planning ? array_unique(array_merge($planning['dep'], $planning['arr'])) : ['25R','25L'];
        $reason  = 'Vent calme ou variable — PRS standard, pistes 25 préférentielles.';
        if ($planning) $reason .= ' Config planning : DEP '.$planning['label_dep'].' / ARR '.$planning['label_arr'].'.';

    } elseif ($prs_active) {
        // PRS applicable
        if ($planning) {
            $plan_rwys = array_values(array_unique(array_merge($planning['dep'], $planning['arr'])));
            $rwys_ok = []; $rwys_ko = [];
            foreach ($plan_rwys as $rwy) {
                $tw_rwy = max(
                    $comps[$rwy]['tw'] ?? 0,
                    $comps_g ? ($comps_g[$rwy]['tw'] ?? 0) : 0
                );
                if ($tw_rwy > 5) $rwys_ko[] = $rwy.' (arrière '.round($tw_rwy,1).'kt)';
                else             $rwys_ok[] = $rwy;
            }
            if (!empty($rwys_ko) && empty($rwys_ok)) {
                $prs_active = false; $alert = true;
                $exceptions[] = 'Pistes du planning inutilisables ('.implode(', ', $rwys_ko).')';
                [$runways, $reason] = selectAltRunway($wdir, $comps, $comps_g);
                $reason = 'Hors PRS — planning impossible. '.$reason;
            } elseif (!empty($rwys_ko)) {
                $runways = $rwys_ok;
                $reason = sprintf('PRS actif (config réduite) — %s inutilisable(s). Pistes : %s.',
                    implode(', ', $rwys_ko), implode('/', $rwys_ok));
            } else {
                $runways = $plan_rwys;
                $reason = sprintf('PRS actif — planning : DEP %s / ARR %s.', $planning['label_dep'], $planning['label_arr']);
            }
        } else {
            $runways = ['25R','25L'];
            $reason = sprintf('PRS actif — vent de face %.1f kt sur 25, latéral %.1f kt.',
                max($comps['25R']['hf'], $comps['25L']['hf']),
                max($comps['25R']['xw'], $comps['25L']['xw']));
        }

    } else {
        // Hors PRS — sélection alternative selon règles officielles
        $alert = true;
        [$runways, $reason] = selectAltRunway($wdir, $comps, $comps_g);
        $reason = 'Hors PRS. '.$reason;

        // Vérifier crosswind 25R (angle 335°) ≤ 20 kt
        if ($xw_25R_eff > 20) {
            $reason .= sprintf(' ⚠ Crosswind 25R=%.1f kt > 20 kt.', $xw_25R_eff);
        }
    }

    return [
        'prs_active'     => $prs_active,
        'prs_exceptions' => $exceptions,
        'runways'        => $runways,
        'config_dep'     => $planning ? $planning['dep'] : ($prs_active ? ['25R'] : $runways),
        'config_arr'     => $planning ? $planning['arr'] : ($prs_active ? ['25L','25R'] : $runways),
        'config_type'    => $prs_active ? 'prs' : 'alt', // 'prs', 'alt', 'reduced'
        'reason'         => $reason,
        'alert'          => $alert,
        'components'     => $comps,
        'tw_25_moy'      => $tw_25_moy,
        'tw_25_gust'     => $tw_25_gust,
        'xw_25_moy'      => $xw_25_moy,
        'xw_25_gust'     => $xw_25_gust,
        'tw_25_max'      => $tw_25_max,
        'xw_25_max'      => $xw_25_max,
    ];
}

// ── Extraction METAR ─────────────────────────────────────────────────────
$wdir      = isset($m['wdir'])  ? (int)$m['wdir']  : null;
$wspd      = isset($m['wspd'])  ? (int)$m['wspd']  : 0;
$wgst      = isset($m['wgst'])  ? (int)$m['wgst']  : null;
$visib     = isset($m['visib']) ? (float)$m['visib'] : null;
$visib_m   = $visib !== null ? round($visib * 1852) : null;
$temp      = isset($m['temp'])  ? (float)$m['temp'] : null;
$raw_metar = $m['rawOb'] ?? $m['rawob'] ?? '';
$obs_time  = $m['reportTime'] ?? $m['obsTime'] ?? '';
// ── Enrichissement rafale IRM temps réel ────────────────────────────────
// Cherche wind_peak_speed IRM pour l'heure du METAR
// IRM publie à l'heure pile (synop horaire) — on cherche l'heure précédente
$wgst_irm   = null;
$wgst_metar = $wgst;

if ($obs_time) {
    $ts_obs = strtotime($obs_time);
    // Heure arrondie à l'heure inférieure (ex: 14:50 → 14:00)
    $irm_ts   = mktime(date('H', $ts_obs), 0, 0, date('n', $ts_obs), date('j', $ts_obs), date('Y', $ts_obs));

    // Essayer l'heure du METAR d'abord, puis l'heure précédente si vide
    $irm_candidates = array(
        array($irm_ts, $irm_ts + 3600),           // heure en cours (ex: 14:00–15:00)
        array($irm_ts - 3600, $irm_ts),           // heure précédente (ex: 13:00–14:00)
    );

    foreach ($irm_candidates as $range) {
        $irm_from = gmdate('Y-m-d\TH:i:s\Z', $range[0]);
        $irm_to   = gmdate('Y-m-d\TH:i:s\Z', $range[1]);

        $irm_url = 'https://opendata.meteo.be/service/ows?service=WFS&version=2.0.0&request=GetFeature'
                 . '&typeName=synop:synop_data&outputFormat=application/json&count=1&sortBy=timestamp+D'
                 . '&CQL_FILTER=' . urlencode("code=6451 AND timestamp >= '$irm_from' AND timestamp <= '$irm_to'");

        $irm_raw = @file_get_contents($irm_url, false, $ctx);
        if ($irm_raw !== false) {
            $irm_json = json_decode($irm_raw, true);
            $irm_feat = $irm_json['features'][0]['properties'] ?? null;
            if ($irm_feat && isset($irm_feat['wind_peak_speed']) && $irm_feat['wind_peak_speed'] !== null) {
                $wgst_irm = round($irm_feat['wind_peak_speed'] * 1.94384 * 2) / 2;
                break; // trouvé — on arrête
            }
        }
    }
}

// Rafale effective = max(METAR, IRM)
if ($wgst_irm !== null && ($wgst === null || $wgst_irm > $wgst)) {
    $wgst = $wgst_irm; // remplace ou complète la rafale METAR
}
$wspd_eff = max($wspd, $wgst ?? 0);
$variable  = ($wdir === null || $wdir === 0 || strtolower((string)($m['wdir'] ?? '')) === 'vrb');

$ceiling_ft = null;
if (!empty($m['clouds']) && is_array($m['clouds'])) {
    foreach ($m['clouds'] as $cloud) {
        $cover = strtoupper($cloud['cover'] ?? '');
        if (in_array($cover, ['BKN','OVC']) && isset($cloud['base'])) {
            $base = (int)$cloud['base'];
            if ($ceiling_ft === null || $base < $ceiling_ft) $ceiling_ft = $base;
        }
    }
}

// ── Planning AIP 2013 pour l'heure actuelle ──────────────────────────────
require_once __DIR__ . '/aip_planning.php';
// $ts_obs est défini plus haut UNIQUEMENT si $obs_time est non vide ; et
// quand il l'est, c'est déjà un timestamp (résultat de strtotime). Sur PHP 8.5
// le strtotime(int) levait une TypeError → HTTP 500. On utilise le timestamp tel quel.
$ts_obs_ts    = isset($ts_obs) && $ts_obs ? (int)$ts_obs : time();
$aip_planning = getAipConfig($ts_obs_ts);
$current_2013     = analyseConfig($wdir, $wspd, $wgst, $visib_m, $ceiling_ft, $variable, $RUNWAYS, $SEUILS_2013, $aip_planning);
$current_now      = analyseConfig($wdir, $wspd, $wgst, $visib_m, $ceiling_ft, $variable, $RUNWAYS, $SEUILS_NOW, $aip_planning);
$current_pratique = analyseConfig($wdir, $wspd, $wgst, $visib_m, $ceiling_ft, $variable, $RUNWAYS, $SEUILS_PRATIQUE, $aip_planning);
$comp_cur = allRunwayComponents($wdir, $wspd, $RUNWAYS, $wgst);

// ── Traitement TAF ───────────────────────────────────────────────────────
$forecast_periods = [];
$raw_taf = '';

if ($taf_data && !empty($taf_data[0])) {
    $t = $taf_data[0];
    $raw_taf = $t['rawTAF'] ?? '';
    $fcsts   = $t['fcsts'] ?? [];
    $seen    = [];
    $now     = time();

    foreach ($fcsts as $f) {
        $change = strtoupper($f['fcstChange'] ?? '');
        if ($change === 'TEMPO' || strpos($change, 'PROB') !== false) continue;

        $time_from = (int)($f['timeFrom'] ?? 0);
        $time_to   = (int)($f['timeTo']   ?? 0);
        if (!$time_from) continue;

        $slot = floor($time_from / 10800) * 10800;
        if (isset($seen[$slot])) continue;
        $seen[$slot] = true;

        // Garder si la période est en cours (timeTo futur) OU si elle commence dans les 30h
        if ($time_to > 0 && $time_to < $now) continue;       // terminée
        if ($time_from > $now + 30 * 3600) continue;          // trop loin dans le futur

        $f_wdir    = isset($f['wdir']) && $f['wdir'] !== null ? (int)$f['wdir'] : null;
        $f_wspd    = isset($f['wspd']) ? (int)$f['wspd'] : 0;
        $f_wgst    = isset($f['wgst']) ? (int)$f['wgst'] : null;
        $f_visib   = isset($f['visib']) ? (float)str_replace('+','',$f['visib']) : null;
        $f_visib_m = $f_visib !== null ? round($f_visib * 1852) : null;
        $f_wspd_eff = max($f_wspd, $f_wgst ?? 0);
        $f_variable = ($f_wdir === null || $f_wdir === 0);

        $f_ceil = null;
        if (!empty($f['clouds']) && is_array($f['clouds'])) {
            foreach ($f['clouds'] as $c) {
                $cov = strtoupper($c['cover'] ?? '');
                if (in_array($cov, ['BKN','OVC']) && isset($c['base'])) {
                    $base = (int)$c['base'];
                    if ($f_ceil === null || $base < $f_ceil) $f_ceil = $base;
                }
            }
        }

        $analysis = analyseConfig($f_wdir, $f_wspd, $f_wgst, $f_visib_m, $f_ceil, $f_variable, $RUNWAYS, $SEUILS_NOW);
        $f_comps  = allRunwayComponents($f_wdir, $f_wspd, $RUNWAYS, $f_wgst);

        // Préparer les composantes à afficher : toutes les pistes concernées
        $comp_display = [];
        foreach (['25R','25L','07L','07R','01','19'] as $rwy) {
            $comp_display[$rwy] = $f_comps[$rwy];
        }

        $forecast_periods[] = [
            'time_from'     => $time_from,
            'time_to'       => (int)($f['timeTo'] ?? 0),
            'change'        => $change ?: 'FM',
            'wdir'          => $f_wdir,
            'wspd'          => $f_wspd,
            'wgst'          => $f_wgst,
            'wspd_eff'      => $f_wspd_eff,
            'variable'      => $f_variable,
            'runways'       => $analysis['runways'],
            'alert'         => $analysis['alert'],
            'prs_active'    => $analysis['prs_active'],
            'prs_exceptions'=> $analysis['prs_exceptions'],
            'reason'        => $analysis['reason'],
            'components'    => $comp_display,
        ];
    }
}

// ── Réponse ──────────────────────────────────────────────────────────────
$result = [
    'metar'          => $raw_metar,
    'obs_time'       => $obs_time,
    'wdir'           => $wdir,
    'wspd'           => $wspd,
    'wgst'           => $wgst,         // rafale effective (max METAR/IRM)
    'wgst_metar'     => $wgst_metar,   // rafale publiée dans le METAR
    'wgst_irm'       => $wgst_irm,     // rafale IRM même heure
    'wspd_eff'       => $wspd_eff,
    'variable'       => $variable,
    'visib_m'        => $visib_m,
    'ceiling_ft'     => $ceiling_ft,
    'temp'           => $temp,
    // Analyse AIP actuel skeyes (contesté)
    'prs_active'     => $current_now['prs_active'],
    'prs_exceptions' => $current_now['prs_exceptions'],
    'runways'        => $current_now['runways'],
    'reason'         => $current_now['reason'],
    'alert'          => $current_now['alert'],
    'tw_25_max'      => $current_now['tw_25_max'],
    'xw_25_max'      => $current_now['xw_25_max'],
    // Analyse AIP 2013 (légal — IM 17/07/2013)
    'aip2013'        => [
        'prs_active'     => $current_2013['prs_active'],
        'prs_exceptions' => $current_2013['prs_exceptions'],
        'runways'        => $current_2013['runways'],
        'config_dep'     => $current_2013['config_dep'] ?? [],
        'config_arr'     => $current_2013['config_arr'] ?? [],
        'reason'         => $current_2013['reason'],
        'alert'          => $current_2013['alert'],
        'tw_25_moy'      => $current_2013['tw_25_moy']  ?? $current_2013['tw_25_max'],
        'tw_25_gust'     => $current_2013['tw_25_gust'] ?? null,
        'xw_25_moy'      => $current_2013['xw_25_moy']  ?? $current_2013['xw_25_max'],
        'xw_25_gust'     => $current_2013['xw_25_gust'] ?? null,
        'tw_25_max'      => $current_2013['tw_25_max'],
        'xw_25_max'      => $current_2013['xw_25_max'],
    ],
    'aip_now'        => [
        'prs_active'     => $current_now['prs_active'],
        'prs_exceptions' => $current_now['prs_exceptions'],
        'runways'        => $current_now['runways'],
        'config_dep'     => $current_now['config_dep'] ?? [],
        'config_arr'     => $current_now['config_arr'] ?? [],
        'reason'         => $current_now['reason'],
        'alert'          => $current_now['alert'],
        'tw_25_moy'      => $current_now['tw_25_moy']  ?? $current_now['tw_25_max'],
        'tw_25_gust'     => $current_now['tw_25_gust'] ?? null,
        'xw_25_moy'      => $current_now['xw_25_moy']  ?? $current_now['xw_25_max'],
        'xw_25_gust'     => $current_now['xw_25_gust'] ?? null,
        'tw_25_max'      => $current_now['tw_25_max'],
        'xw_25_max'      => $current_now['xw_25_max'],
    ],
    // Analyse pratique observée (seuil ~6.5 kt)
    'aip_pratique'   => [
        'prs_active'     => $current_pratique['prs_active'],
        'runways'        => $current_pratique['runways'],
        'alert'          => $current_pratique['alert'],
        'tw_seuil'       => 6.5,
        'note'           => 'Seuil observé en pratique — skeyes bascule vers 6.5 kt avant les 7 kt légaux',
    ],
    'aip_planning'   => $aip_planning,   // Config attendue selon planning AIP 2013
    'seuils_2013'    => $SEUILS_2013,
    'components'     => $comp_cur,
    'taf'            => $raw_taf,
    'forecast'       => $forecast_periods,
    'cached_at'      => date('Y-m-d H:i:s'),
    'source'         => 'NOAA Aviation Weather — AIP 2013 (IM 17/07/2013) vs AIP skeyes actuel',
];

$json = json_encode($result, JSON_UNESCAPED_UNICODE);
@file_put_contents($CACHE_FILE, $json);
echo $json;
