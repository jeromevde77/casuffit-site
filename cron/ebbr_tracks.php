<?php
/**
 * cron/ebbr_tracks.php — Collecte quotidienne des traces radar EBBR (RWY 01 + RWY 07)
 *
 * Configurer via cron-job.org : 1x/jour à 03:00 UTC
 * URL : https://www.casuffit.be/cron/ebbr_tracks.php?secret=CRON_SECRET&date=YYYY-MM-DD
 *
 * Ce script est INDÉPENDANT de l'outil vols en temps réel existant.
 */

require_once __DIR__ . '/../config.php';

// Protection
$secret = defined('CRON_SECRET') ? CRON_SECRET : null;
if ($secret && ($_GET['secret'] ?? '') !== $secret) {
    http_response_code(403); die('Forbidden');
}

ini_set('display_errors', 0);
ini_set('max_execution_time', 600);
ini_set('memory_limit', '256M');

$db   = getDB();
$log  = [];
$date = $_GET['date'] ?? date('Y-m-d', strtotime('yesterday'));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { die('Date invalide'); }

function logit($m) { global $log; $log[] = '['.date('H:i:s').'] '.$m; echo $log[array_key_last($log)]."\n"; flush(); }

logit("=== Collecte EBBR traces — $date ===");

// Vérifier déjà traité
$already = $db->prepare("SELECT COUNT(*) FROM ebbr_runway_tracks WHERE track_date=?");
$already->execute([$date]);
if ($already->fetchColumn() > 0 && !isset($_GET['force'])) {
    logit("Déjà traité — ajoutez &force=1 pour forcer"); exit;
}

$begin = strtotime($date.' 00:00:00');
$end   = strtotime($date.' 23:59:59');

$opensky_user = defined('OPENSKY_USER') ? OPENSKY_USER : '';
$opensky_pass = defined('OPENSKY_PASS') ? OPENSKY_PASS : '';
$auth_b64     = ($opensky_user && $opensky_pass) ? base64_encode("$opensky_user:$opensky_pass") : null;

// ── 1. Récupérer toutes les arrivées EBBR du jour ────────────────────────
$url     = "https://opensky-network.org/api/flights/arrival?airport=EBBR&begin=$begin&end=$end";
logit("Appel OpenSky flights/arrival...");
$flights = opensky_get($url, $auth_b64);

if (!is_array($flights)) {
    logit("ERREUR arrivals : " . print_r($flights, true)); exit;
}
logit(count($flights)." arrivées EBBR reçues");

$saved_01 = 0; $saved_07 = 0; $skipped = 0;

// ── 2. Traiter chaque vol ─────────────────────────────────────────────────
foreach ($flights as $i => $flight) {
    $icao24    = strtolower(trim($flight['icao24'] ?? ''));
    $callsign  = trim($flight['callsign'] ?? '');
    $last_seen = (int)($flight['lastSeen'] ?? 0);

    if (!$icao24 || !$last_seen) { $skipped++; continue; }

    // Rate limiting : max 1 req/s en anonymous, 2 req/s avec compte
    usleep($auth_b64 ? 600000 : 1300000);

    // Récupérer la trace — time = 1h avant atterrissage (couvre l'approche complète)
    $track_url  = "https://opensky-network.org/api/tracks/all?icao24=$icao24&time=".($last_seen - 3600);
    $track_data = opensky_get($track_url, $auth_b64);

    if (empty($track_data['path'])) { $skipped++; continue; }

    $waypoints = $track_data['path'];
    // Format : [[timestamp, lat, lon, baro_alt, geo_alt, on_ground], ...]

    // Détecter la piste
    $runway = detect_runway($waypoints);
    if (!$runway) { $skipped++; continue; }

    // Extraire l'approche (depuis altitude ~3000m / 10000ft jusqu'au touchdown)
    $approach = extract_approach($waypoints);
    if (count($approach) < 8) { $skipped++; continue; }

    // Sauvegarder
    try {
        $db->prepare("INSERT INTO ebbr_runway_tracks
            (track_date, callsign, icao24, runway, waypoints, arr_timestamp, created_at)
            VALUES (?,?,?,?,?,?,NOW())
            ON DUPLICATE KEY UPDATE waypoints=VALUES(waypoints)")
           ->execute([$date, $callsign ?: $icao24, $icao24, $runway, json_encode($approach), $last_seen]);

        if ($runway === '01') $saved_01++; else $saved_07++;
        logit("  ✓ ".str_pad($callsign ?: $icao24, 8)." → RWY $runway  ".count($approach)." pts");
    } catch (Exception $e) {
        logit("  DB: ".$e->getMessage());
    }
}

logit("Résultat : RWY 01 = $saved_01 | RWY 07 = $saved_07 | ignorés = $skipped");

// ── 3. Générer l'image PNG si vols trouvés ────────────────────────────────
if ($saved_01 + $saved_07 > 0) {
    $tracks_stmt = $db->prepare("SELECT * FROM ebbr_runway_tracks WHERE track_date=? ORDER BY runway, arr_timestamp");
    $tracks_stmt->execute([$date]);
    $all_tracks = $tracks_stmt->fetchAll();

    @mkdir(__DIR__.'/../medias/tracks', 0755, true);
    $img_path = __DIR__.'/../medias/tracks/'.$date.'.png';
    $ok = generate_track_image($all_tracks, $date, $saved_01, $saved_07, $img_path);
    logit($ok ? "Image PNG générée : $img_path" : "ERREUR génération image");
} else {
    logit("Aucun vol piste 01/07 ce jour — pas d'image");
}

logit("=== Terminé ===");

// ════════════════════════════════════════════════════════════════════════
// FONCTIONS
// ════════════════════════════════════════════════════════════════════════

function opensky_get(string $url, ?string $auth64): ?array {
    $headers = "User-Agent: casuffit.be/track-collector contact:info@casuffit.be\r\n";
    if ($auth64) $headers .= "Authorization: Basic $auth64\r\n";
    $ctx  = stream_context_create(['http'=>[
        'method'        => 'GET',
        'header'        => $headers,
        'timeout'       => 30,
        'ignore_errors' => true,
    ]]);
    $resp = @file_get_contents($url, false, $ctx);
    if (!$resp) return null;
    return json_decode($resp, true);
}

/**
 * Détecte la piste depuis les waypoints.
 *
 * RWY 01 : cap final 335° → 040° (atterrissage vers le nord)
 * RWY 07 : cap final 040° → 100° (atterrissage vers l'est / sud-est)
 */
function detect_runway(array $wps): ?string {
    $n = count($wps);
    if ($n < 5) return null;

    // Trouver le touchdown
    $td = $n - 1;
    for ($i = 1; $i < $n; $i++) {
        if (!empty($wps[$i][5]) && empty($wps[$i-1][5])) { $td = $i; break; }
    }

    // Prendre 20 points avant le touchdown pour le cap final
    $from = max(0, $td - 20);
    $to   = max(0, $td - 1);
    if ($to <= $from) return null;

    // Calculer cap moyen (moyenne circulaire pour éviter le problème 359°→001°)
    $sin_s = 0; $cos_s = 0; $cnt = 0;
    for ($i = $from + 1; $i <= $to; $i++) {
        $dlat = ($wps[$i][1] ?? 0) - ($wps[$i-1][1] ?? 0);
        $dlon = ($wps[$i][2] ?? 0) - ($wps[$i-1][2] ?? 0);
        if (abs($dlat) < 1e-6 && abs($dlon) < 1e-6) continue;
        $hdg = fmod(rad2deg(atan2($dlon, $dlat)) + 360, 360);
        $sin_s += sin(deg2rad($hdg));
        $cos_s += cos(deg2rad($hdg));
        $cnt++;
    }
    if (!$cnt) return null;

    $avg = fmod(rad2deg(atan2($sin_s/$cnt, $cos_s/$cnt)) + 360, 360);

    // Classification (chevauchement délibéré pour attraper les trajectoires en virage)
    // RWY 01 : 335°→040° (couvre le nord + léger est/ouest)
    if ($avg >= 335 || $avg < 40)  return '01';
    // RWY 07 : 040°→100° (est/sud-est)
    if ($avg >= 40  && $avg < 100) return '07';
    return null;
}

/**
 * Extrait la portion approche : depuis altitude ~3000m jusqu'au roulage.
 * On ignore les parties de croisière (trop haute altitude).
 * On garde jusqu'à 5 minutes après touchdown pour voir le roulage.
 */
function extract_approach(array $wps): array {
    $n = count($wps);

    // Trouver le touchdown
    $td = $n - 1;
    for ($i = 1; $i < $n; $i++) {
        if (!empty($wps[$i][5]) && empty($wps[$i-1][5])) { $td = $i; break; }
    }

    // Remonter depuis le touchdown jusqu'à ~3000m baro (~9800ft)
    $start = 0;
    for ($i = $td; $i >= 0; $i--) {
        $alt = $wps[$i][3] ?? 0; // baro_alt en mètres
        if ($alt !== null && $alt > 3000) { $start = $i; break; }
    }

    // Garder jusqu'à 3 points après touchdown (roulage court)
    $end = min($n - 1, $td + 3);

    // Filtrer : garder 1 point sur 2 pour alléger (approches = 200-400 pts)
    $result = [];
    for ($i = $start; $i <= $end; $i++) {
        if (($i - $start) % 2 === 0 || $i === $td || $i === $end) {
            $result[] = [
                'lat' => round($wps[$i][1] ?? 0, 6),
                'lon' => round($wps[$i][2] ?? 0, 6),
                'alt' => (int)($wps[$i][3] ?? 0),
                'gnd' => !empty($wps[$i][5]),
            ];
        }
    }
    return $result;
}

/**
 * Génère l'image PNG avec les traces sur fond OSM.
 * Utilise GD + tuiles OSM téléchargées.
 */
function generate_track_image(array $tracks, string $date, int $n01, int $n07, string $out_path): bool {
    if (!function_exists('imagecreatetruecolor')) { return false; }

    $ZOOM     = 10;
    $TILE_PX  = 256;
    // Bbox : couvre toutes les approches (30nm autour d'EBBR)
    $LAT_MIN  = 50.35; $LAT_MAX = 51.40;
    $LON_MIN  = 3.65;  $LON_MAX = 5.25;

    // Tiles limites
    $tx_min = lon2tile($LON_MIN, $ZOOM); $tx_max = lon2tile($LON_MAX, $ZOOM);
    $ty_min = lat2tile($LAT_MAX, $ZOOM); $ty_max = lat2tile($LAT_MIN, $ZOOM); // y inversé

    $W = ($tx_max - $tx_min + 1) * $TILE_PX;
    $H = ($ty_max - $ty_min + 1) * $TILE_PX;

    $im = imagecreatetruecolor($W, $H);
    $grey = imagecolorallocate($im, 200, 210, 220);
    imagefill($im, 0, 0, $grey);

    // Télécharger les tuiles OSM
    $ua = 'casuffit.be/track-image contact:info@casuffit.be';
    for ($tx = $tx_min; $tx <= $tx_max; $tx++) {
        for ($ty = $ty_min; $ty <= $ty_max; $ty++) {
            $tile_url  = "https://tile.openstreetmap.org/$ZOOM/$tx/$ty.png";
            $tile_data = @file_get_contents($tile_url, false, stream_context_create([
                'http'=>['header'=>"User-Agent: $ua\r\n", 'timeout'=>8]
            ]));
            if ($tile_data) {
                $tile = @imagecreatefromstring($tile_data);
                if ($tile) {
                    $px = ($tx - $tx_min) * $TILE_PX;
                    $py = ($ty - $ty_min) * $TILE_PX;
                    imagecopy($im, $tile, $px, $py, 0, 0, $TILE_PX, $TILE_PX);
                    imagedestroy($tile);
                }
            }
            usleep(120000); // 120ms entre les tuiles (politique OSM)
        }
    }

    // Couleurs
    $c_01     = imagecolorallocatealpha($im, 255, 153,   0, 20); // orange semi-transparent
    $c_01_s   = imagecolorallocate($im, 255, 120,   0);           // orange plein
    $c_07     = imagecolorallocatealpha($im, 22,  115, 178, 20); // bleu semi-transparent
    $c_07_s   = imagecolorallocate($im,  22,  115, 178);           // bleu plein
    $c_white  = imagecolorallocate($im, 255, 255, 255);
    $c_dark   = imagecolorallocate($im,  14,  61, 107);
    $c_grey   = imagecolorallocate($im, 136, 153, 170);
    $c_rwy    = imagecolorallocate($im, 255, 255, 255);
    $c_ebbr   = imagecolorallocate($im, 229,  62,  62);

    imagesetthickness($im, 1);

    // Dessiner les traces
    foreach ($tracks as $track) {
        $pts = json_decode($track['waypoints'], true);
        if (!$pts || count($pts) < 2) continue;
        $is01 = ($track['runway'] === '01');
        $col  = $is01 ? $c_01_s : $c_07_s;
        imagesetthickness($im, 2);
        for ($i = 1; $i < count($pts); $i++) {
            if (!isset($pts[$i]['lat'], $pts[$i-1]['lat'])) continue;
            [$x1, $y1] = ll2px($pts[$i-1]['lat'], $pts[$i-1]['lon'], $tx_min, $ty_min, $ZOOM, $TILE_PX);
            [$x2, $y2] = ll2px($pts[$i]['lat'],   $pts[$i]['lon'],   $tx_min, $ty_min, $ZOOM, $TILE_PX);
            imageline($im, $x1, $y1, $x2, $y2, $col);
        }
        // Point de touchdown (dernier point)
        $last = end($pts);
        [$lx, $ly] = ll2px($last['lat'], $last['lon'], $tx_min, $ty_min, $ZOOM, $TILE_PX);
        imagefilledellipse($im, $lx, $ly, 8, 8, $col);
    }

    // EBBR marker
    [$ex, $ey] = ll2px(50.9014, 4.4844, $tx_min, $ty_min, $ZOOM, $TILE_PX);
    imagefilledellipse($im, $ex, $ey, 14, 14, $c_ebbr);
    imageellipse($im, $ex, $ey, 14, 14, $c_white);

    // Dessiner les pistes schématiquement (ligne blanche)
    imagesetthickness($im, 4);
    // RWY 01/19 : nord-sud, légèrement incliné
    [$r01_nx, $r01_ny] = ll2px(50.912, 4.484, $tx_min, $ty_min, $ZOOM, $TILE_PX);
    [$r01_sx, $r01_sy] = ll2px(50.877, 4.476, $tx_min, $ty_min, $ZOOM, $TILE_PX);
    imageline($im, $r01_sx, $r01_sy, $r01_nx, $r01_ny, $c_rwy);
    // RWY 07/25 : est-ouest
    [$r07_wx, $r07_wy] = ll2px(50.894, 4.443, $tx_min, $ty_min, $ZOOM, $TILE_PX);
    [$r07_ex, $r07_ey] = ll2px(50.895, 4.530, $tx_min, $ty_min, $ZOOM, $TILE_PX);
    imageline($im, $r07_wx, $r07_wy, $r07_ex, $r07_ey, $c_rwy);

    imagesetthickness($im, 1);

    // ── Légende ──────────────────────────────────────────────────────────
    $lx = 16; $ly = $H - 130;
    imagefilledrectangle($im, $lx, $ly, $lx + 250, $ly + 112, imagecolorallocatealpha($im, 255,255,255, 30));
    imagerectangle($im, $lx, $ly, $lx + 250, $ly + 112, $c_grey);

    $font_sz = 3;
    imagesetthickness($im, 6);
    imageline($im, $lx+12, $ly+24, $lx+44, $ly+24, $c_01_s);
    imagesetthickness($im, 1);
    imagestring($im, $font_sz, $lx+52, $ly+16, "RWY 01 — $n01 vol".($n01>1?'s':''), $c_dark);

    imagesetthickness($im, 6);
    imageline($im, $lx+12, $ly+52, $lx+44, $ly+52, $c_07_s);
    imagesetthickness($im, 1);
    imagestring($im, $font_sz, $lx+52, $ly+44, "RWY 07 — $n07 vol".($n07>1?'s':''), $c_dark);

    $date_fmt = date('d/m/Y', strtotime($date));
    imagestring($im, 2, $lx+12, $ly+72, "Date : $date_fmt", $c_grey);
    imagestring($im, 2, $lx+12, $ly+88, "Source : OpenSky Network", $c_grey);
    imagestring($im, 2, $lx+12, $ly+100, "casuffit.be", $c_grey);

    // Titre
    $title = "EBBR — Atterrissages pistes 01 & 07 — $date_fmt";
    imagestring($im, 4, ($W - strlen($title)*8)/2, 12, $title, $c_dark);

    // Sauvegarde
    imagepng($im, $out_path, 6);
    imagedestroy($im);
    return file_exists($out_path);
}

// ── Helpers coordonnées ───────────────────────────────────────────────────
function lon2tile(float $lon, int $z): int { return (int)floor(($lon + 180) / 360 * 2**$z); }
function lat2tile(float $lat, int $z): int {
    return (int)floor((1 - log(tan(deg2rad($lat)) + 1/cos(deg2rad($lat))) / M_PI) / 2 * 2**$z);
}
function ll2px(float $lat, float $lon, int $tx_min, int $ty_min, int $z, int $tpx): array {
    $x_tile = ($lon + 180) / 360 * 2**$z;
    $y_tile = (1 - log(tan(deg2rad($lat)) + 1/cos(deg2rad($lat))) / M_PI) / 2 * 2**$z;
    return [(int)(($x_tile - $tx_min) * $tpx), (int)(($y_tile - $ty_min) * $tpx)];
}
