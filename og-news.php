<?php
// og-news.php — Génère une image OG 1200x630 pour une actualité (partage Facebook)
require_once __DIR__ . '/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$db = getDB();

$titre = 'Ça suffit !';
if ($id > 0) {
    try {
        $st = $db->prepare("SELECT titre, titre_nl FROM news WHERE id=? AND statut='publie' LIMIT 1");
        $st->execute([$id]);
        $row = $st->fetch();
        if ($row) {
            $lang = ($_GET['lang'] ?? 'fr') === 'nl' ? 'nl' : 'fr';
            $titre = ($lang === 'nl' && !empty($row['titre_nl'])) ? $row['titre_nl'] : $row['titre'];
        }
    } catch (Exception $e) {}
}

// Vérifier GD + FreeType
if (!function_exists('imagecreatetruecolor') || !function_exists('imagettftext')) {
    // Fallback : rediriger vers l'image statique
    header('Location: /assets/img/og-image.jpg');
    exit;
}

$W = 1200; $H = 630;
$font_bold = __DIR__ . '/assets/fonts/LiberationSans-Bold.ttf';
$font_reg  = __DIR__ . '/assets/fonts/LiberationSans-Regular.ttf';

$im = imagecreatetruecolor($W, $H);

// Dégradé bleu vertical (#0e3d6b -> #1673B2)
$c1 = [14, 61, 107];   // haut
$c2 = [22, 115, 178];  // bas
for ($y = 0; $y < $H; $y++) {
    $t = $y / $H;
    $r = (int)($c1[0] + ($c2[0]-$c1[0])*$t);
    $g = (int)($c1[1] + ($c2[1]-$c1[1])*$t);
    $b = (int)($c1[2] + ($c2[2]-$c1[2])*$t);
    $col = imagecolorallocate($im, $r, $g, $b);
    imageline($im, 0, $y, $W, $y, $col);
}

$white  = imagecolorallocate($im, 255, 255, 255);
$orange = imagecolorallocate($im, 255, 153, 0);
$light  = imagecolorallocate($im, 200, 220, 245);

// Bande orange en bas
imagefilledrectangle($im, 0, $H-12, $W, $H, $orange);

// Logo (si dispo)
$logo_path = __DIR__ . '/assets/img/logo.png';
if (file_exists($logo_path)) {
    $logo = @imagecreatefrompng($logo_path);
    if ($logo) {
        $lw = imagesx($logo); $lh = imagesy($logo);
        $target = 110;
        $ratio = $target / max($lw, $lh);
        $nw = (int)($lw*$ratio); $nh = (int)($lh*$ratio);
        imagecopyresampled($im, $logo, 70, 60, 0, 0, $nw, $nh, $lw, $lh);
        imagedestroy($logo);
    }
}

// "Ça suffit !" en orange à côté du logo
imagettftext($im, 40, 0, 200, 130, $orange, $font_bold, 'Ça suffit !');
imagettftext($im, 18, 0, 202, 165, $light, $font_reg, 'Nuisances aériennes — Brussels Airport');

// Titre de l'article (wrap multi-lignes)
function wrap_text($font, $size, $text, $maxW) {
    $words = explode(' ', $text);
    $lines = []; $cur = '';
    foreach ($words as $w) {
        $test = $cur === '' ? $w : $cur.' '.$w;
        $bbox = imagettfbbox($size, 0, $font, $test);
        $width = abs($bbox[2] - $bbox[0]);
        if ($width > $maxW && $cur !== '') { $lines[] = $cur; $cur = $w; }
        else { $cur = $test; }
    }
    if ($cur !== '') $lines[] = $cur;
    return $lines;
}

$title_size = 52;
$lines = wrap_text($font_bold, $title_size, $titre, $W - 140);
// Si trop de lignes, réduire la taille
while (count($lines) > 5 && $title_size > 32) {
    $title_size -= 4;
    $lines = wrap_text($font_bold, $title_size, $titre, $W - 140);
}

$line_h = $title_size + 18;
$total_h = count($lines) * $line_h;
$start_y = 300 + (($H - 300 - $total_h) / 2);
$y = $start_y;
foreach ($lines as $line) {
    imagettftext($im, $title_size, 0, 70, (int)$y, $white, $font_bold, $line);
    $y += $line_h;
}

// Sortie
header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');
imagepng($im);
imagedestroy($im);
