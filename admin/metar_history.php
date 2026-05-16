<?php
require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();

$pdo    = getDB();
$page   = max(1, (int)($_GET['p'] ?? 1));
$limit  = 48;
$offset = ($page - 1) * $limit;

$total = $pdo->query("SELECT COUNT(*) FROM metar_history")->fetchColumn();
$rows  = $pdo->prepare("SELECT * FROM metar_history ORDER BY obs_time DESC LIMIT :lim OFFSET :off");
$rows->bindValue(':lim', $limit, PDO::PARAM_INT);
$rows->bindValue(':off', $offset, PDO::PARAM_INT);
$rows->execute();
$records = $rows->fetchAll(PDO::FETCH_ASSOC);
$pages = ceil($total / $limit);
?><!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8">
<title>Historique METAR — Admin</title>
<style>
*{box-sizing:border-box}
body{font-family:"Helvetica Neue",sans-serif;background:#f5f7fa;color:#333;margin:0;padding:20px}
h2{color:#0e3d6b;margin-bottom:4px}
.meta{color:#888;font-size:.8rem;margin-bottom:16px}
a{color:#1673B2;text-decoration:none}
table{width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;
      box-shadow:0 1px 4px rgba(0,0,0,.08);font-size:.82rem}
th{background:#0e3d6b;color:#fff;padding:8px 10px;text-align:left;font-weight:600;font-size:.75rem}
td{padding:7px 10px;border-bottom:1px solid #eee;vertical-align:middle}
tr:hover td{background:#f0f6fb}
.prs-1{background:#fff0f0}
.prs-1 td{color:#b00}
.badge{display:inline-block;padding:2px 6px;border-radius:3px;font-size:.7rem;font-weight:700}
.b-prs1{background:#ffe0e0;color:#c00}
.b-prs0{background:#e0ffe0;color:#080}
.b-ok{background:#e8f3fb;color:#1673B2}
.btn-replay{background:#1673B2;color:#fff;border:none;padding:4px 10px;border-radius:4px;
            cursor:pointer;font-size:.75rem;white-space:nowrap}
.btn-replay:hover{background:#0e5a96}
.pagination{margin-top:16px;display:flex;gap:6px;flex-wrap:wrap;align-items:center}
.pagination a,.pagination span{padding:5px 10px;border-radius:4px;border:1px solid #c8dff0;
  font-size:.8rem;background:#fff;color:#1673B2;text-decoration:none}
.pagination .cur{background:#1673B2;color:#fff;border-color:#1673B2}

/* Modal */
.modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:1000;
          align-items:center;justify-content:center}
.modal-bg.open{display:flex}
.modal{background:#fff;border-radius:12px;width:560px;max-width:96vw;max-height:90vh;
       overflow-y:auto;box-shadow:0 8px 40px rgba(0,0,0,.3)}
.modal-head{padding:14px 20px;border-bottom:1px solid #eee;display:flex;
            align-items:center;justify-content:space-between}
.modal-head h3{margin:0;color:#0e3d6b;font-size:1rem}
.modal-close{background:none;border:none;font-size:1.4rem;cursor:pointer;color:#888;line-height:1}
.modal-body{padding:20px}
.replay-meta{background:#f0f6fb;border-radius:6px;padding:10px 14px;margin-bottom:14px;font-size:.82rem;color:#555}
.replay-meta strong{color:#0e3d6b}
#replay-widget-wrap{background:#fff}
</style>
</head><body>

<p>← <a href="/admin/">Admin</a></p>
<h2>📊 Historique METAR enregistré</h2>
<p class="meta"><?= number_format($total) ?> enregistrements · Page <?= $page ?>/<?= max(1,$pages) ?></p>

<table>
<thead>
  <tr>
    <th>Date/Heure (UTC)</th>
    <th>Dir</th>
    <th>Moy (kt)</th>
    <th>METAR (kt)</th>
    <th>IRM (kt)</th>
    <th>PRS actuel</th>
    <th>PRS légal '13</th>
    <th>TW 25</th>
    <th>Temp</th>
    <th></th>
  </tr>
</thead>
<tbody>
<?php foreach ($records as $r): ?>
<?php $isPrs = !$r['prs_active']; // prs_active=0 = piste 01 active ?>
<tr class="<?= $isPrs ? 'prs-1' : '' ?>">
  <td><?= substr($r['obs_time'], 0, 16) ?></td>
  <td><?= $r['wind_variable'] ? 'VRB' : ($r['wind_dir'] !== null ? $r['wind_dir'].'°' : '—') ?></td>
  <td><?= $r['wind_speed'] ?> kt</td>
  <td><?= $r['wind_gust'] !== null ? $r['wind_gust'].' kt' : '—' ?></td>
  <td><?= $r['irm_gust']  !== null ? $r['irm_gust'].' kt'  : '—' ?></td>
  <td><span class="badge <?= $r['prs_active'] ? 'b-ok' : 'b-prs1' ?>"><?= $r['prs_active'] ? '✓ 25' : '⚠ 01' ?></span></td>
  <td><span class="badge <?= $r['prs_2013']   ? 'b-ok' : 'b-prs1' ?>"><?= $r['prs_2013']   ? '✓ 25' : '⚠ 01' ?></span></td>
  <td><?= $r['tw_25'] !== null ? $r['tw_25'].' kt' : '—' ?></td>
  <td><?= $r['temp'] !== null ? $r['temp'].'°C' : '—' ?></td>
  <td><button class="btn-replay" onclick="openReplay(<?= $r['id'] ?>, '<?= htmlspecialchars($r['obs_time']) ?>')">▶ Widget</button></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<!-- Pagination -->
<div class="pagination">
  <?php if ($page > 1): ?>
    <a href="?p=<?= $page-1 ?>">← Précédent</a>
  <?php endif; ?>
  <?php for ($i = max(1,$page-3); $i <= min($pages,$page+3); $i++): ?>
    <<?= $i==$page ? 'span class="cur"' : 'a href="?p='.$i.'"' ?>><?= $i ?></<?= $i==$page?'span':'a' ?>>
  <?php endfor; ?>
  <?php if ($page < $pages): ?>
    <a href="?p=<?= $page+1 ?>">Suivant →</a>
  <?php endif; ?>
</div>

<!-- Modal widget replay -->
<div class="modal-bg" id="modal-bg" onclick="if(event.target===this)closeReplay()">
  <div class="modal">
    <div class="modal-head">
      <h3 id="modal-title">Widget piste météo</h3>
      <button class="modal-close" onclick="closeReplay()">✕</button>
    </div>
    <div class="modal-body">
      <div class="replay-meta" id="replay-meta"></div>
      <div id="replay-widget-wrap">Chargement…</div>
    </div>
  </div>
</div>

<?php
// Extraire le CSS + HTML + JS du widget piste_meteo pour l'embarquer
$widget_src = file_get_contents(ROOT . '/includes/widgets/piste_meteo.php');
// On n'inclut que le widget brut (pas le <?php ?>)
// On récupère le CSS inline du widget
preg_match('/<style>(.*?)<\/style>/s', $widget_src, $css_m);
$widget_css = $css_m[1] ?? '';
?>

<style>
/* CSS piste_meteo injecté dans modal */
<?= $widget_css ?>
.pmw { max-width: 100%; font-size: 90%; }
</style>

<script>
var replayUrl = '/api/metar_replay.php';

function openReplay(id, obsTime) {
  document.getElementById('modal-title').textContent = 'Widget — ' + obsTime + ' UTC';
  document.getElementById('replay-meta').innerHTML =
    '<strong>Simulation</strong> du widget piste météo au <strong>' + obsTime + ' UTC</strong><br>' +
    '<span style="color:#888;font-size:.75rem">Les données viennent de la base historique (source IRM + METAR)</span>';

  var wrap = document.getElementById('replay-widget-wrap');
  wrap.innerHTML = '<div style="text-align:center;padding:20px;color:#888">Chargement…</div>';
  document.getElementById('modal-bg').classList.add('open');

  // Charger les données replay
  fetch(replayUrl + '?id=' + id, {credentials: 'include'})
    .then(function(r){ return r.json(); })
    .then(function(d) {
      if (d.error) { wrap.innerHTML = '<p style="color:red">Erreur: '+d.error+'</p>'; return; }
      renderReplayWidget(wrap, d);
    })
    .catch(function() { wrap.innerHTML = '<p style="color:red">Erreur réseau</p>'; });
}

function closeReplay() {
  document.getElementById('modal-bg').classList.remove('open');
}

// Fermer avec Escape
document.addEventListener('keydown', function(e) { if(e.key==='Escape') closeReplay(); });

function renderReplayWidget(wrap, d) {
  var wd  = d.wdir;
  var ws  = d.wspd;
  var wg  = d.wgst;
  var prs = d.prs_active;
  var p13 = d.aip2013?.prs_active;

  // Direction texte
  var dirs = ['N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSO','SO','OSO','O','ONO','NO','NNO'];
  var dirTxt = wd !== null ? dirs[Math.round(wd/22.5)%16] : '—';

  var gstTxt = '';
  if (d.wgst_metar) gstTxt += 'METAR: '+d.wgst_metar+' kt  ';
  if (d.wgst_irm)   gstTxt += 'IRM: '+d.wgst_irm+' kt';
  if (!gstTxt && wg) gstTxt = wg+' kt';

  // Composantes pour les pistes 25L/25R/01
  var c25L = d.components?.['25L'];
  var c25R = d.components?.['25R'];
  var c01  = d.components?.['01'];

  function twRow(rwy, comp) {
    if (!comp) return '';
    var tw = comp.tw_gst ?? comp.tw_moy;
    var xw = comp.xw_gst ?? comp.xw_moy;
    var cls = tw > 7 ? 'color:#c00;font-weight:700' : 'color:#080';
    return '<tr><td style="padding:4px 8px;font-weight:700">'+rwy+'</td>'
         + '<td style="padding:4px 8px;'+cls+'">TW: '+tw+' kt</td>'
         + '<td style="padding:4px 8px">XW: '+(xw||'—')+' kt</td></tr>';
  }

  wrap.innerHTML = 
    '<div style="background:#0e3d6b;color:#fff;border-radius:8px 8px 0 0;padding:12px 16px;font-size:.9rem">' +
      '<strong>Conditions au ' + d.obs_time?.slice(11,16) + ' UTC</strong>' +
    '</div>' +
    '<div style="padding:14px 16px">' +

    '<div style="display:flex;gap:20px;margin-bottom:12px;flex-wrap:wrap">' +
      '<div><div style="font-size:1.6rem;font-weight:800;color:#0e3d6b">' +
        (wd !== null ? wd+'° '+dirTxt : 'Variable') +
      '</div>' +
      '<div style="color:#1673B2;font-weight:600">' + ws + ' kt (moy)</div>' +
      (gstTxt ? '<div style="color:#e07000;font-size:.82rem">Rafales: '+gstTxt+'</div>' : '') +
      '</div>' +

      '<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-start">' +
        '<div style="background:'+(prs?'#e0ffe0':'#ffe0e0')+';border-radius:6px;padding:8px 12px;text-align:center">' +
          '<div style="font-size:.65rem;color:#555;margin-bottom:2px">PRS actuel (skeyes)</div>' +
          '<div style="font-weight:800;color:'+(prs?'#080':'#c00')+'">'+(prs?'✓ Piste 25':'⚠ Piste 01')+'</div>' +
        '</div>' +
        '<div style="background:'+(p13?'#e0ffe0':'#ffe0e0')+';border-radius:6px;padding:8px 12px;text-align:center">' +
          '<div style="font-size:.65rem;color:#555;margin-bottom:2px">PRS légal 2013</div>' +
          '<div style="font-weight:800;color:'+(p13?'#080':'#c00')+'">'+(p13?'✓ Piste 25':'⚠ Piste 01')+'</div>' +
        '</div>' +
      '</div>' +
    '</div>' +

    ((!prs && p13) ?
      '<div style="background:#fff8ee;border:2px solid #FF9900;border-radius:6px;padding:10px 14px;margin-bottom:12px;color:#7a4400">' +
      '⚖ <strong>Utilisation illégale de la piste 01</strong> — La norme de vent légale (AIP 2013) autorise la piste 25.' +
      '</div>' : '') +

    '<table style="width:100%;font-size:.8rem;background:#f5f7fa;border-radius:6px">' +
      '<thead><tr style="background:#e8f0f8">' +
        '<th style="padding:5px 8px;text-align:left">Piste</th>' +
        '<th style="padding:5px 8px;text-align:left">Vent arrière</th>' +
        '<th style="padding:5px 8px;text-align:left">Vent traversier</th>' +
      '</tr></thead><tbody>' +
      twRow('25L', c25L) + twRow('25R', c25R) + twRow('01', c01) +
      '</tbody></table>' +

    '<div style="font-size:.7rem;color:#aaa;margin-top:10px;text-align:right">' +
      'Source: ' + (d.metar || 'IRM synop') +
    '</div></div>';
}
</script>

</body></html>
