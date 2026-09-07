<?php
/* og-communes.php — v1
 * Genere le visuel 1200x630 "communes survolees en piste 01" avec les logos
 * officiels des communes, charges depuis /medias/ (upload via Admin > Medias).
 *
 * Nommage attendu des fichiers (png / jpg / gif / webp) — la detection est
 * souple : il suffit que le nom du fichier CONTIENNE le mot-cle.
 *   waterloo.png · kraainem.png · wezembeek.png · woluwe.png
 * Si un logo est absent, la carte affiche le nom de la commune en texte.
 *
 * Usage :  /og-communes.php          -> affiche l'image
 *          /og-communes.php?dl=1     -> telecharge le PNG
 */
require_once __DIR__ . '/config.php';

if (!function_exists('imagecreatetruecolor') || !function_exists('imagettftext')) {
    header('Content-Type: text/plain; charset=utf-8');
    exit("GD/FreeType indisponible sur ce serveur.");
}

$W = 1200; $H = 630;
$font_bold = __DIR__ . '/assets/fonts/LiberationSans-Bold.ttf';
$font_reg  = __DIR__ . '/assets/fonts/LiberationSans-Regular.ttf';

// ── Communes : libelle de repli + mots-cles de detection du fichier ───────
$communes = [
    ['nom' => 'Waterloo',              'keys' => ['waterloo']],
    ['nom' => 'Kraainem',              'keys' => ['kraainem']],
    ['nom' => 'Wezembeek-Oppem',       'keys' => ['wezembeek']],
    ['nom' => 'Woluwe-Saint-Pierre',   'keys' => ['woluwe', 'w5c', 'wsp']],
];

// ── Recherche du fichier logo dans /medias/ ──────────────────────────────
function find_logo(array $keys): ?string {
    $dir = __DIR__ . '/medias/';
    if (!is_dir($dir)) return null;
    $exts = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if (!in_array($ext, $exts, true)) continue;
        $base = strtolower($f);
        foreach ($keys as $k) {
            if (str_contains($base, $k)) return $dir . $f;
        }
    }
    return null;
}

function load_img(string $path) {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    switch ($ext) {
        case 'png':  return @imagecreatefrompng($path);
        case 'jpg': case 'jpeg': return @imagecreatefromjpeg($path);
        case 'gif':  return @imagecreatefromgif($path);
        case 'webp': return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false;
    }
    return false;
}

/** Dessine $src dans la boite ($bx,$by,$bw,$bh) en preservant le ratio (contain). */
function draw_contain($dst, $src, int $bx, int $by, int $bw, int $bh): void {
    $sw = imagesx($src); $sh = imagesy($src);
    if ($sw < 1 || $sh < 1) return;
    $ratio = min($bw / $sw, $bh / $sh);
    $nw = (int)round($sw * $ratio); $nh = (int)round($sh * $ratio);
    $nx = $bx + (int)(($bw - $nw) / 2);
    $ny = $by + (int)(($bh - $nh) / 2);
    imagecopyresampled($dst, $src, $nx, $ny, 0, 0, $nw, $nh, $sw, $sh);
}

/** Rectangle a coins arrondis. */
function rounded_rect($im, int $x1, int $y1, int $x2, int $y2, int $r, int $color): void {
    imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $color);
    imagefilledrectangle($im, $x1, $y1 + $r, $x2, $y2 - $r, $color);
    $d = $r * 2;
    imagefilledellipse($im, $x1 + $r, $y1 + $r, $d, $d, $color);
    imagefilledellipse($im, $x2 - $r, $y1 + $r, $d, $d, $color);
    imagefilledellipse($im, $x1 + $r, $y2 - $r, $d, $d, $color);
    imagefilledellipse($im, $x2 - $r, $y2 - $r, $d, $d, $color);
}

/** Largeur d'un texte TTF. */
function tw(string $font, float $size, string $text): int {
    $b = imagettfbbox($size, 0, $font, $text);
    return (int)abs($b[2] - $b[0]);
}

// ── Fond ─────────────────────────────────────────────────────────────────
$im = imagecreatetruecolor($W, $H);
$c1 = [14, 42, 71]; $c2 = [22, 115, 178];
for ($y = 0; $y < $H; $y++) {
    $t = $y / $H;
    $col = imagecolorallocate($im,
        (int)($c1[0] + ($c2[0]-$c1[0])*$t),
        (int)($c1[1] + ($c2[1]-$c1[1])*$t),
        (int)($c1[2] + ($c2[2]-$c1[2])*$t));
    imageline($im, 0, $y, $W, $y, $col);
}

$white   = imagecolorallocate($im, 255, 255, 255);
$orange  = imagecolorallocate($im, 255, 153, 0);
$amber   = imagecolorallocate($im, 255, 203, 112);
$light   = imagecolorallocate($im, 185, 208, 232);
$dark    = imagecolorallocate($im, 14, 42, 71);
$brown   = imagecolorallocate($im, 122, 68, 0);
$cardbg  = imagecolorallocate($im, 255, 255, 255);

imagefilledrectangle($im, 0, $H - 12, $W, $H, $orange);

// ── En-tete ──────────────────────────────────────────────────────────────
$logo_path = __DIR__ . '/assets/img/logo.png';
if (file_exists($logo_path) && ($logo = @imagecreatefrompng($logo_path))) {
    draw_contain($im, $logo, 60, 40, 86, 86);
    imagedestroy($logo);
}
imagettftext($im, 32, 0, 166, 82,  $orange, $font_bold, 'Ça suffit !');
imagettftext($im, 15, 0, 168, 112, $light,  $font_reg,  'Nuisances aériennes — Piste 01');

// Badge « communiqué de presse conjoint » + signataires (aligne a droite)
$badge = 'COMMUNIQUÉ DE PRESSE CONJOINT';
$bw    = tw($font_bold, 13, $badge) + 34;
$bx1   = 1140 - $bw;
rounded_rect($im, $bx1, 44, 1140, 80, 8, $orange);
imagettftext($im, 13, 0, $bx1 + 17, 68, $dark, $font_bold, $badge);
$sign = 'Piste 01, ça suffit ! · UBCNA/BUTV · AwaCCS — 4 septembre 2026';
imagettftext($im, 12, 0, 1140 - tw($font_reg, 12, $sign), 104, $light, $font_reg, $sign);

// ── Accroche + titre ─────────────────────────────────────────────────────
imagettftext($im, 14, 0, 60, 172, $orange, $font_bold, 'LISTE OFFICIELLE DU SERVICE FÉDÉRAL DE MÉDIATION');
imagettftext($im, 34, 0, 60, 226, $white,  $font_bold, 'Ces communes sont survolées en piste 01.');
imagettftext($im, 34, 0, 60, 274, $amber,  $font_bold, 'Depuis plus de vingt ans.');

// ── Cartes des communes ──────────────────────────────────────────────────
$card_y = 306; $card_h = 132; $gap = 16; $margin = 60;
$card_w = (int)(($W - 2*$margin - 3*$gap) / 4);
$missing = [];

foreach ($communes as $i => $com) {
    $x = $margin + $i * ($card_w + $gap);
    rounded_rect($im, $x, $card_y, $x + $card_w, $card_y + $card_h, 12, $cardbg);

    $path = find_logo($com['keys']);
    $img  = $path ? load_img($path) : false;
    if ($img) {
        draw_contain($im, $img, $x + 14, $card_y + 14, $card_w - 28, $card_h - 28);
        imagedestroy($img);
    } else {
        $missing[] = $com['nom'];
        // Repli : nom de la commune en texte, sur deux lignes si besoin
        $parts = explode('-', $com['nom']);
        if (count($parts) > 1) {
            $l1 = $parts[0] . '-'; $l2 = implode('-', array_slice($parts, 1));
            imagettftext($im, 17, 0, $x + (int)(($card_w - tw($font_bold,17,$l1))/2), $card_y + 60, $dark, $font_bold, $l1);
            imagettftext($im, 17, 0, $x + (int)(($card_w - tw($font_bold,17,$l2))/2), $card_y + 86, $dark, $font_bold, $l2);
        } else {
            imagettftext($im, 19, 0, $x + (int)(($card_w - tw($font_bold,19,$com['nom']))/2), $card_y + 76, $dark, $font_bold, $com['nom']);
        }
    }
}

// ── Pied : autres communes + chiffre cle ─────────────────────────────────
imagettftext($im, 14, 0, 60, 522, $light, $font_reg, 'Ainsi que Rhode-Saint-Genèse, Lasne et La Hulpe.');
imagettftext($im, 14, 0, 60, 550, $light, $font_reg, "Le couloir d'approche n'est pas vide — il n'a simplement jamais été compté.");

rounded_rect($im, 906, 486, 1140, 578, 12, $orange);
$s1 = '175 000+'; $s2 = 'HABITANTS · 3 RÉGIONS';
imagettftext($im, 28, 0, 906 + (int)((234 - tw($font_bold,28,$s1))/2), 534, $dark,  $font_bold, $s1);
imagettftext($im, 11, 0, 906 + (int)((234 - tw($font_bold,11,$s2))/2), 561, $brown, $font_bold, $s2);

// ── Sortie ───────────────────────────────────────────────────────────────
if (isset($_GET['dl'])) {
    header('Content-Disposition: attachment; filename="piste01-communes.png"');
}
header('Content-Type: image/png');
header('Cache-Control: no-cache, must-revalidate');
if ($missing) header('X-Logos-Manquants: ' . implode(', ', $missing));
imagepng($im);
imagedestroy($im);
