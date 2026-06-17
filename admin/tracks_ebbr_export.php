<?php
// admin/tracks_ebbr_export.php — Export CSV (Excel) des stats d'utilisation des pistes 01 / 07
// Source : table ebbr_runway_tracks (un atterrissage = une ligne). Agrégation par jour.
// Filtres optionnels : ?from=YYYY-MM-DD&to=YYYY-MM-DD
require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();
$db = getDB();

// Filtres de date optionnels (validés)
$from = $_GET['from'] ?? '';
$to   = $_GET['to']   ?? '';
$where = []; $params = [];
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $where[] = 'track_date >= ?'; $params[] = $from; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   { $where[] = 'track_date <= ?'; $params[] = $to; }
$wsql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$rows = [];
try {
    $st = $db->prepare("SELECT track_date,
            SUM(runway='01') AS n01,
            SUM(runway='07') AS n07,
            COUNT(*)         AS total
        FROM ebbr_runway_tracks $wsql
        GROUP BY track_date ORDER BY track_date ASC");
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    http_response_code(500);
    die('Table ebbr_runway_tracks indisponible.');
}

$fname = 'utilisation-pistes-01-07_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $fname . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 → accents corrects dans Excel

// Pourcentage avec virgule décimale (Excel belge/français)
$pct = function (int $n, int $d): string {
    return $d > 0 ? number_format($n / $d * 100, 1, ',', '') : '0';
};

fputcsv($out, ['Date', 'Vols RWY 01', 'Vols RWY 07', 'Total', '% RWY 01', '% RWY 07'], ';');

$t01 = $t07 = $ttot = 0;
foreach ($rows as $r) {
    $n01 = (int) $r['n01'];
    $n07 = (int) $r['n07'];
    $tot = (int) $r['total'];
    $t01 += $n01; $t07 += $n07; $ttot += $tot;
    fputcsv($out, [
        date('d/m/Y', strtotime($r['track_date'])),
        $n01, $n07, $tot,
        $pct($n01, $n01 + $n07),
        $pct($n07, $n01 + $n07),
    ], ';');
}

// Ligne de totaux
fputcsv($out, ['TOTAL', $t01, $t07, $ttot, $pct($t01, $t01 + $t07), $pct($t07, $t01 + $t07)], ';');
fclose($out);
