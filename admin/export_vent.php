<?php
// admin/export_vent.php — v1 — Extraction des données de vent entre deux dates (table metar_history)
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireAdmin();

$db = getDB();

// ── Pistes EBBR (QFU) — mêmes valeurs que api/metar_replay.php ────────────
const PISTES = [
    '25L' => 251, '25R' => 246,
    '07R' => 71,  '07L' => 66,
    '01'  => 14,  '19'  => 194,
];

/** Composantes de vent pour une piste : arrière (tailwind) et traversier (crosswind). */
function composantes(?int $dir, float $spd, ?float $gst, int $qfu): array {
    if ($dir === null) return ['tw'=>null,'tw_g'=>null,'xw'=>null,'xw_g'=>null];
    // Convention identique à api/metar_replay.php : cos > 0 = vent de FACE,
    // valeur négative = vent arrière (tailwind). On garde ce signe pour rester cohérent.
    $d = deg2rad($dir - $qfu);
    return [
        'tw'   => round($spd * cos($d), 1),
        'tw_g' => $gst !== null ? round($gst * cos($d), 1) : null,
        'xw'   => round(abs($spd * sin($d)), 1),
        'xw_g' => $gst !== null ? round(abs($gst * sin($d)), 1) : null,
    ];
}

/** Rose des vents : secteur cardinal depuis une direction. */
function cardinal(?int $d): string {
    if ($d === null) return 'VRB';
    $s = ['N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSO','SO','OSO','O','ONO','NO','NNO'];
    return $s[(int)round((($d % 360) + 360) % 360 / 22.5) % 16];
}

// ── Paramètres ───────────────────────────────────────────────────────────
$d1 = $_GET['d1'] ?? date('Y-m-d', strtotime('-7 days'));
$d2 = $_GET['d2'] ?? date('Y-m-d');
$pistes_sel = isset($_GET['pistes']) ? (array)$_GET['pistes'] : ['25R','25L','01','07L'];
$pistes_sel = array_values(array_intersect($pistes_sel, array_keys(PISTES)));
if (!$pistes_sel) $pistes_sel = ['25R'];
$horaire = ($_GET['horaire'] ?? '') === '1';   // 1 obs/heure au lieu de toutes
$source  = ($_GET['source'] ?? 'metar') === 'irm' ? 'irm' : 'metar';   // source des composantes

// ── Vérification table ───────────────────────────────────────────────────
$table_ok = false; $total_rows = 0; $periode_dispo = ['', '']; $has_irm = false;
try {
    $db->query("SELECT 1 FROM metar_history LIMIT 1");
    $table_ok = true;
    $total_rows = (int)$db->query("SELECT COUNT(*) FROM metar_history")->fetchColumn();
    $r = $db->query("SELECT MIN(obs_time) a, MAX(obs_time) b FROM metar_history")->fetch();
    $periode_dispo = [$r['a'] ?? '', $r['b'] ?? ''];
    foreach ($db->query("SHOW COLUMNS FROM metar_history")->fetchAll() as $col)
        if ($col['Field'] === 'irm_speed') { $has_irm = true; break; }
} catch (Exception $e) {}

// ── Extraction ───────────────────────────────────────────────────────────
$rows = [];
if ($table_ok && isset($_GET['go'])) {
    $irm_cols = $has_irm ? ", irm_dir, irm_speed, irm_gust" : "";
    $sql = "SELECT obs_time, metar_raw, wind_dir, wind_speed, wind_gust, wind_variable,
                   runways, prs_active, prs_2013, temp, qnh$irm_cols
            FROM metar_history
            WHERE obs_time >= ? AND obs_time < DATE_ADD(?, INTERVAL 1 DAY)";
    if ($horaire) $sql .= " AND MINUTE(obs_time) < 30";
    $sql .= " ORDER BY obs_time ASC";
    try {
        $st = $db->prepare($sql);
        $st->execute([$d1.' 00:00:00', $d2]);
        $rows = $st->fetchAll();
    } catch (Exception $e) { $err = $e->getMessage(); }
}

// ── Export CSV ───────────────────────────────────────────────────────────
if (isset($_GET['export']) && $rows) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="vent_ebbr_'.$d1.'_'.$d2.'.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');

    $head = ['Date/Heure (UTC)','Dir. METAR (°)','Secteur','Vit. METAR (kt)','Raf. METAR (kt)','Variable'];
    if ($has_irm) array_push($head, 'Dir. IRM (°)', 'Vit. IRM (kt)', 'Raf. IRM (kt)');
    foreach ($pistes_sel as $p) {
        $head[] = "Long. $p (kt)"; $head[] = "Long. raf. $p (kt)";
        $head[] = "Trav. $p (kt)"; $head[] = "Trav. raf. $p (kt)";
    }
    array_push($head, 'Pistes en service','PRS actuel','PRS 2013','Temp (°C)','QNH (hPa)','METAR brut');
    fputcsv($out, $head, ';');

    foreach ($rows as $r) {
        if ($source === 'irm' && $has_irm && $r['irm_speed'] !== null) {
            $dir = $r['irm_dir'] !== null ? (int)$r['irm_dir'] : null;
            $spd = (float)$r['irm_speed'];
            $gst = $r['irm_gust'] !== null ? (float)$r['irm_gust'] : null;
        } else {
            $dir = $r['wind_dir'] !== null ? (int)$r['wind_dir'] : null;
            $spd = (float)$r['wind_speed'];
            $gst = $r['wind_gust'] !== null ? (float)$r['wind_gust'] : null;
        }
        $line = [
            date('d/m/Y H:i', strtotime($r['obs_time'])),
            $dir !== null ? $dir : 'VRB',
            cardinal($dir),
            $spd,
            $gst !== null ? $gst : '',
            $r['wind_variable'] ? 'oui' : '',
        ];
        if ($has_irm) {
            $line[] = $r['irm_dir']   !== null ? (int)$r['irm_dir'] : '';
            $line[] = $r['irm_speed'] !== null ? str_replace('.', ',', (string)$r['irm_speed']) : '';
            $line[] = $r['irm_gust']  !== null ? str_replace('.', ',', (string)$r['irm_gust'])  : '';
        }
        foreach ($pistes_sel as $p) {
            $c = composantes($dir, $spd, $gst, PISTES[$p]);
            $line[] = $c['tw']   !== null ? str_replace('.', ',', (string)$c['tw'])   : '';
            $line[] = $c['tw_g'] !== null ? str_replace('.', ',', (string)$c['tw_g']) : '';
            $line[] = $c['xw']   !== null ? str_replace('.', ',', (string)$c['xw'])   : '';
            $line[] = $c['xw_g'] !== null ? str_replace('.', ',', (string)$c['xw_g']) : '';
        }
        array_push($line, $r['runways'], $r['prs_active'] ? 'oui':'non', $r['prs_2013'] ? 'oui':'non',
                   $r['temp'], $r['qnh'], $r['metar_raw']);
        fputcsv($out, $line, ';');
    }
    fclose($out); exit;
}

// ── Statistiques de la période ───────────────────────────────────────────
$stats = null;
if ($rows) {
    $spds = array_map(fn($r) => (float)$r['wind_speed'], $rows);
    $nb_prs = 0; $nb_hors = 0;
    foreach ($rows as $r) { $r['prs_active'] ? $nb_prs++ : $nb_hors++; }
    $stats = [
        'n'    => count($rows),
        'moy'  => round(array_sum($spds) / max(1, count($spds)), 1),
        'max'  => max($spds),
        'prs'  => $nb_prs,
        'hors' => $nb_hors,
        'pct'  => count($rows) ? round($nb_prs / count($rows) * 100, 1) : 0,
    ];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Extraction vent — Admin Ça suffit !</title>
<style>
<?php include __DIR__.'/../includes/admin_sidebar_css.php'; ?>
body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333;margin:0}
*{box-sizing:border-box}
.page-header{padding:22px 28px 0}
.page-header h1{font-size:1.2rem;font-weight:800;color:#0e3d6b;margin:0 0 4px}
.page-header p{font-size:.82rem;color:#888;margin:0}
.card{background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.08);margin:18px 28px;padding:20px 24px}
.ctrl{display:flex;gap:16px;flex-wrap:wrap;align-items:flex-end}
.fg{display:flex;flex-direction:column;gap:4px}
.fg label{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#888}
.fg input,.fg select{padding:8px 12px;border:1.5px solid #cdd8e5;border-radius:8px;font-size:.86rem;font-family:inherit}
.fg input:focus{outline:none;border-color:#1673B2}
.pistes{display:flex;gap:7px;flex-wrap:wrap;align-items:center}
.pistes label{display:flex;align-items:center;gap:5px;background:#f0f7fd;border:1.5px solid #cfe2fb;border-radius:8px;padding:7px 12px;cursor:pointer;font-size:.83rem;font-weight:700;color:#0e3d6b}
.pistes label:has(input:checked){background:#1673B2;color:#fff;border-color:#1673B2}
.pistes input{width:15px;height:15px;cursor:pointer}
.chk{display:flex;align-items:center;gap:8px;font-size:.84rem;cursor:pointer;padding-bottom:9px}
.chk input{width:16px;height:16px}
.btn{padding:10px 22px;border:none;border-radius:8px;font-size:.86rem;font-weight:700;cursor:pointer;font-family:inherit;text-decoration:none;display:inline-block}
.btn-go{background:#FF9900;color:#fff}.btn-go:hover{background:#e08800}
.btn-x{background:#1a7a45;color:#fff}.btn-x:hover{background:#146138}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px;margin:16px 28px}
.st{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.07);padding:14px 16px;border-top:3px solid #1673B2}
.st .v{font-size:1.5rem;font-weight:800;color:#1673B2;line-height:1}
.st .l{font-size:.68rem;color:#999;text-transform:uppercase;letter-spacing:.04em;margin-top:4px}
.tw{overflow-x:auto;max-height:65vh;overflow-y:auto}
table{width:100%;border-collapse:collapse;font-size:.78rem;white-space:nowrap}
th{text-align:left;padding:8px 9px;background:#0e3d6b;color:#fff;font-size:.67rem;text-transform:uppercase;letter-spacing:.03em;position:sticky;top:0;z-index:2}
th.grp{background:#1673B2;text-align:center}
td{padding:6px 9px;border-bottom:1px solid #f0f4f8}
tr:hover td{background:#f7fbff}
td.num{text-align:right;font-family:ui-monospace,Menlo,monospace}
.tw-bad{color:#c62828;font-weight:700}
.tw-warn{color:#e08800;font-weight:700}
.badge{display:inline-block;padding:2px 7px;border-radius:9px;font-size:.68rem;font-weight:700}
.b-y{background:#e8f5e9;color:#1b5e20}.b-n{background:#fdecea;color:#a5352a}
.note{font-size:.75rem;color:#999;margin-top:12px;line-height:1.65}
.warn{background:#fff8ee;border:1.5px solid #FF9900;color:#7a4500;padding:13px 17px;border-radius:10px;font-size:.84rem;margin:18px 28px;line-height:1.6}
.empty{text-align:center;padding:44px;color:#aaa}
</style>
</head>
<body>
<?php include __DIR__.'/../includes/admin_sidebar.php'; ?>
<div class="wrap">

<div class="page-header">
  <h1>💨 Extraction des données de vent</h1>
  <p>Historique METAR EBBR — direction, force, rafales et composantes par piste</p>
</div>

<?php if (!$table_ok): ?>
  <div class="warn">⚠ La table <code>metar_history</code> est introuvable. Exécutez d'abord <code>migrate_metar_history.sql</code> dans phpMyAdmin.</div>
<?php else: ?>

<div class="card">
  <form method="GET">
    <div class="ctrl">
      <div class="fg"><label>Du</label><input type="date" name="d1" value="<?= htmlspecialchars($d1) ?>" required></div>
      <div class="fg"><label>Au</label><input type="date" name="d2" value="<?= htmlspecialchars($d2) ?>" required></div>
      <label class="chk"><input type="checkbox" name="horaire" value="1" <?= $horaire?'checked':'' ?>> Une observation par heure</label>
      <?php if ($has_irm): ?>
      <div class="fg"><label>Source des composantes</label>
        <select name="source">
          <option value="metar" <?= $source==='metar'?'selected':'' ?>>METAR (aéroport)</option>
          <option value="irm"   <?= $source==='irm'?'selected':'' ?>>IRM (station 6451)</option>
        </select></div>
      <?php endif; ?>
      <button type="submit" name="go" value="1" class="btn btn-go">🔍 Extraire</button>
      <?php if ($rows): ?>
        <a class="btn btn-x" href="?<?= http_build_query(array_merge($_GET, ['export'=>1])) ?>">⬇ Exporter en CSV</a>
      <?php endif; ?>
    </div>

    <div style="margin-top:14px">
      <label style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#888;display:block;margin-bottom:7px">Composantes à calculer</label>
      <div class="pistes">
        <?php foreach (PISTES as $p => $qfu): ?>
          <label><input type="checkbox" name="pistes[]" value="<?= $p ?>" <?= in_array($p,$pistes_sel)?'checked':'' ?>>
            <?= $p ?> <span style="opacity:.6;font-weight:400"><?= $qfu ?>°</span></label>
        <?php endforeach; ?>
      </div>
    </div>
  </form>

  <div class="note">
    <strong>Long.</strong> = composante longitudinale : <strong>positif = vent de face</strong> (favorable),
    <strong>négatif = vent arrière</strong> (défavorable). <strong>Trav.</strong> = vent traversier, en valeur absolue.
    Seuils AIP 2013 : vent arrière <strong>7 kt</strong> (rafales comprises <strong>10 kt</strong>), traversier <strong>15 kt</strong>.
    <?php if ($has_irm): ?><br>Deux sources : <strong style="color:#0e5a96">METAR</strong> (aéroport, toutes les 30 min)
    et <strong style="color:#1a7a45">IRM</strong> (station officielle 6451, horaire). Le sélecteur choisit laquelle
    sert au calcul des composantes.<?php else: ?>
    <br><span style="color:#cc7a00">ℹ Direction et vitesse IRM non disponibles — lancez <code>outils-irm-migration.php</code> pour les ajouter.</span><?php endif; ?>
    <?php if ($periode_dispo[0]): ?><br>Données disponibles du <?= date('d/m/Y', strtotime($periode_dispo[0])) ?>
    au <?= date('d/m/Y', strtotime($periode_dispo[1])) ?> — <?= number_format($total_rows,0,',',' ') ?> observations.<?php endif; ?>
  </div>
</div>

<?php if ($stats): ?>
<div class="stats">
  <div class="st"><div class="v"><?= number_format($stats['n'],0,',',' ') ?></div><div class="l">Observations</div></div>
  <div class="st"><div class="v"><?= $stats['moy'] ?></div><div class="l">Vent moyen (kt)</div></div>
  <div class="st"><div class="v"><?= $stats['max'] ?></div><div class="l">Vent max (kt)</div></div>
  <div class="st"><div class="v"><?= $stats['pct'] ?>%</div><div class="l">Conforme PRS</div></div>
  <div class="st" style="border-top-color:#c62828"><div class="v" style="color:#c62828"><?= $stats['hors'] ?></div><div class="l">Hors PRS</div></div>
</div>
<?php endif; ?>

<?php if (isset($_GET['go'])): ?>
<div class="card">
  <?php if (!$rows): ?>
    <div class="empty">📭 Aucune observation sur cette période.</div>
  <?php else: ?>
    <div class="tw">
    <table>
      <tr>
        <th rowspan="2">Date / Heure (UTC)</th>
        <th class="grp" colspan="4" style="background:#0e5a96">METAR</th>
        <?php if ($has_irm): ?><th class="grp" colspan="3" style="background:#1a7a45">IRM</th><?php endif; ?>
        <?php foreach ($pistes_sel as $p): ?><th class="grp" colspan="2"><?= $p ?></th><?php endforeach; ?>
        <th rowspan="2">Pistes</th><th rowspan="2">PRS</th>
      </tr>
      <tr>
        <th style="background:#0e5a96;font-size:.63rem">Dir.</th>
        <th style="background:#0e5a96;font-size:.63rem">Sect.</th>
        <th style="background:#0e5a96;font-size:.63rem">Vit.</th>
        <th style="background:#0e5a96;font-size:.63rem">Raf.</th>
        <?php if ($has_irm): ?>
        <th style="background:#1a7a45;font-size:.63rem">Dir.</th>
        <th style="background:#1a7a45;font-size:.63rem">Vit.</th>
        <th style="background:#1a7a45;font-size:.63rem">Raf.</th>
        <?php endif; ?>
        <?php foreach ($pistes_sel as $p): ?>
          <th style="background:#1673B2;font-size:.63rem" title="Composante longitudinale : négatif = vent arrière">Long.</th>
          <th style="background:#1673B2;font-size:.63rem" title="Vent traversier">Trav.</th>
        <?php endforeach; ?>
      </tr>
      <?php foreach ($rows as $r):
        $use_irm = ($source === 'irm' && $has_irm && $r['irm_speed'] !== null);
        if ($use_irm) {
            $dir = $r['irm_dir'] !== null ? (int)$r['irm_dir'] : null;
            $spd = (float)$r['irm_speed'];
            $gst = $r['irm_gust'] !== null ? (float)$r['irm_gust'] : null;
        } else {
            $dir = $r['wind_dir'] !== null ? (int)$r['wind_dir'] : null;
            $spd = (float)$r['wind_speed'];
            $gst = $r['wind_gust'] !== null ? (float)$r['wind_gust'] : null;
        }
      ?>
      <tr>
        <td><?= date('d/m H:i', strtotime($r['obs_time'])) ?></td>
        <td class="num"><?= $r['wind_dir'] !== null ? (int)$r['wind_dir'].'°' : 'VRB' ?></td>
        <td style="color:#888"><?= cardinal($r['wind_dir'] !== null ? (int)$r['wind_dir'] : null) ?></td>
        <td class="num"><?= (float)$r['wind_speed'] ?></td>
        <td class="num" style="color:<?= $r['wind_gust']?'#e08800':'#ccc' ?>"><?= $r['wind_gust'] !== null ? (float)$r['wind_gust'] : '—' ?></td>
        <?php if ($has_irm): ?>
        <td class="num" style="background:#f4fbf6"><?= $r['irm_dir']   !== null ? (int)$r['irm_dir'].'°' : '—' ?></td>
        <td class="num" style="background:#f4fbf6"><?= $r['irm_speed'] !== null ? (float)$r['irm_speed'] : '—' ?></td>
        <td class="num" style="background:#f4fbf6;color:<?= $r['irm_gust']?'#e08800':'#ccc' ?>"><?= $r['irm_gust'] !== null ? (float)$r['irm_gust'] : '—' ?></td>
        <?php endif; ?>
        <?php foreach ($pistes_sel as $p):
          $c = composantes($dir, $spd, $gst, PISTES[$p]);
          $cls = ''; if ($c['tw'] !== null) { if ($c['tw'] < -7) $cls='tw-bad'; elseif ($c['tw'] < -5) $cls='tw-warn'; }
          $xcls = ($c['xw'] !== null && $c['xw'] > 15) ? 'tw-bad' : '';
        ?>
          <td class="num <?= $cls ?>"><?= $c['tw'] !== null ? number_format($c['tw'],1,',','') : '—' ?></td>
          <td class="num <?= $xcls ?>"><?= $c['xw'] !== null ? number_format($c['xw'],1,',','') : '—' ?></td>
        <?php endforeach; ?>
        <td style="font-size:.72rem;color:#666"><?= htmlspecialchars($r['runways'] ?: '—') ?></td>
        <td><span class="badge <?= $r['prs_active']?'b-y':'b-n' ?>"><?= $r['prs_active']?'oui':'non' ?></span></td>
      </tr>
      <?php endforeach; ?>
    </table>
    </div>
    <div class="note">
      Vent arrière signalé en <span class="tw-warn">orange</span> au-delà de 5 kt (valeur ≤ −5),
      en <span class="tw-bad">rouge</span> au-delà du seuil légal de 7 kt (valeur ≤ −7).
      Traversier en rouge au-delà de 15 kt. Heures en UTC (heure locale = UTC+1 en hiver, UTC+2 en été).
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php endif; ?>
</div>
</body>
</html>
