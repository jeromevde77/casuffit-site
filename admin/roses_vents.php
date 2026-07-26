<?php
// admin/roses_vents.php — v2 — Génération en série des roses des vents mensuelles (IRM 6451)
require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();

$mois_noms = ['','Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
$cur_y = (int)date('Y');
$cur_m = (int)date('n');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Roses des vents — Admin Ça suffit !</title>
<style>
<?php include __DIR__.'/../includes/admin_sidebar_css.php'; ?>
body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333;margin:0}
*{box-sizing:border-box}
.page-header{padding:22px 28px 0}
.page-header h1{font-size:1.2rem;font-weight:800;color:#0e3d6b;margin:0 0 4px;display:flex;align-items:center;gap:8px}
.page-header p{font-size:.82rem;color:#888;margin:0}

.card{background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.08);margin:18px 28px;padding:20px 24px}
.ctrl{display:flex;gap:16px;flex-wrap:wrap;align-items:flex-end}
.fg{display:flex;flex-direction:column;gap:4px}
.fg label{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#888}
.fg select{padding:8px 12px;border:1.5px solid #cdd8e5;border-radius:8px;font-size:.88rem;font-family:inherit;background:#fff}
.fg select:focus{outline:none;border-color:#1673B2}
.chk{display:flex;align-items:center;gap:7px;font-size:.84rem;cursor:pointer;padding-bottom:9px}
.chk input{width:16px;height:16px;cursor:pointer}
.btn{padding:10px 22px;border:none;border-radius:8px;font-size:.88rem;font-weight:700;cursor:pointer;font-family:inherit;transition:background .15s}
.btn-go{background:#FF9900;color:#fff}.btn-go:hover{background:#e08800}
.btn-go:disabled{opacity:.55;cursor:default}
.btn-2{background:#0e3d6b;color:#fff}.btn-2:hover{background:#1673B2}
.btn-2:disabled{opacity:.4;cursor:default}
.btn-sm{padding:5px 12px;font-size:.76rem;border-radius:6px;background:#eef3f9;color:#0e3d6b;border:1px solid #d6e2ee;cursor:pointer;font-weight:700;font-family:inherit}
.btn-sm:hover{background:#dfe9f4}

.status{margin:0 28px;padding:12px 18px;border-radius:10px;font-size:.86rem;display:none}
.status.on{display:block}
.status.info{background:#eff6ff;color:#1673B2;border:1px solid #cfe2fb}
.status.ok{background:#e8f8f0;color:#1a6e3c;border:1px solid #a8e6c0}
.status.err{background:#fde8e8;color:#922b21;border:1px solid #f5b7b1}
.pbar{height:6px;background:#e3ecf6;border-radius:3px;margin-top:9px;overflow:hidden}
.pbar i{display:block;height:100%;background:#FF9900;width:0;transition:width .25s}

/* Légende */
.legend{display:flex;gap:14px;flex-wrap:wrap;margin-top:14px;padding-top:14px;border-top:1px solid #eef2f7}
.lg{display:flex;align-items:center;gap:6px;font-size:.76rem;color:#555}
.lg i{width:16px;height:12px;border-radius:3px;display:block;flex-shrink:0}

/* Grille des roses */
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(360px,1fr));gap:20px;margin:18px 28px 30px}
.rose{background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.08);padding:16px;text-align:center}
.rose h3{font-size:.95rem;font-weight:800;color:#0e3d6b;margin:0 0 2px}
.rose .sub{font-size:.7rem;color:#aaa;margin-bottom:10px}
.rose canvas{width:100%;height:auto;max-width:340px}
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:6px;margin-top:12px}
.st{background:#f7fafd;border-radius:7px;padding:7px 4px}
.st .v{font-size:.9rem;font-weight:800;color:#1673B2}
.st .l{font-size:.6rem;color:#999;text-transform:uppercase;letter-spacing:.04em;margin-top:1px}
.rose .acts{margin-top:10px}
.rose.err{border:2px solid #f5b7b1}
.rose.err .msg{font-size:.8rem;color:#922b21;padding:30px 10px}
</style>
</head>
<body>
<?php include __DIR__.'/../includes/admin_sidebar.php'; ?>
<div class="wrap">

<div class="page-header">
  <h1>🌹 Roses des vents mensuelles</h1>
  <p>Station IRM 6451 — Zaventem/Melsbroek · une rose par mois sur la période choisie</p>
</div>

<div class="card">
  <div class="ctrl">
    <div class="fg">
      <label>Du mois</label>
      <select id="m1"><?php for($m=1;$m<=12;$m++): ?><option value="<?= $m ?>" <?= $m==2?'selected':'' ?>><?= $mois_noms[$m] ?></option><?php endfor; ?></select>
    </div>
    <div class="fg">
      <label>Année</label>
      <select id="y1"><?php for($y=2015;$y<=$cur_y;$y++): ?><option value="<?= $y ?>" <?= $y==2025?'selected':'' ?>><?= $y ?></option><?php endfor; ?></select>
    </div>
    <div class="fg">
      <label>Au mois</label>
      <select id="m2"><?php for($m=1;$m<=12;$m++): ?><option value="<?= $m ?>" <?= $m==$cur_m?'selected':'' ?>><?= $mois_noms[$m] ?></option><?php endfor; ?></select>
    </div>
    <div class="fg">
      <label>Année</label>
      <select id="y2"><?php for($y=2015;$y<=$cur_y;$y++): ?><option value="<?= $y ?>" <?= $y==$cur_y?'selected':'' ?>><?= $y ?></option><?php endfor; ?></select>
    </div>
    <label class="chk"><input type="checkbox" id="axes" checked> Axes de pistes</label>
    <button class="btn btn-go" id="go" onclick="run()">🌹 Générer les roses</button>
    <button class="btn btn-2" id="dlall" onclick="downloadPlanche()" disabled>⬇ Planche complète (PNG)</button>
  </div>

  <div class="legend" id="legend"></div>
</div>

<div class="status" id="status"></div>

<div class="grid" id="grid"></div>

</div>

<script>
// ── Plages de vitesse (identiques au widget public) ────────────────────────
var RANGES = [
  {min:0,  max:1,   color:'#d4edda', label:'< 1 kt'},
  {min:1,  max:4,   color:'#52c46a', label:'1 – 4 kt'},
  {min:4,  max:7,   color:'#1a9e3f', label:'4 – 7 kt'},
  {min:7,  max:11,  color:'#f5d000', label:'7 – 11 kt'},
  {min:11, max:17,  color:'#f07800', label:'11 – 17 kt'},
  {min:17, max:21,  color:'#d42020', label:'17 – 21 kt'},
  {min:21, max:999, color:'#7b2fa0', label:'≥ 21 kt'}
];
// Axes de pistes EBBR (QFU) — uniquement 01 et 07L (les deux qui nous concernent)
var PISTES = [
  {qfu:14,  lbl:'01'},
  {qfu:66,  lbl:'07L'}
];
// Ligne repère supplémentaire (vent de Nord-Est : bascule 01 → 07)
var REPERE_DEG = 40;
var MOIS = ['','Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
var results = [];   // {y,m,label,canvas,stats}

// Légende
(function(){
  var h = '';
  RANGES.forEach(function(r){ h += '<div class="lg"><i style="background:'+r.color+'"></i>'+r.label+'</div>'; });
  h += '<div class="lg"><i style="background:repeating-linear-gradient(90deg,#c62828 0 4px,transparent 4px 7px);height:2px"></i>Axes 01 / 07L</div>';
  h += '<div class="lg"><i style="background:repeating-linear-gradient(90deg,#1e6edc 0 4px,transparent 4px 7px);height:2px"></i>Repère 40°</div>';
  document.getElementById('legend').innerHTML = h;
})();

function setStatus(cls, msg, pct) {
  var el = document.getElementById('status');
  el.className = 'status on ' + cls;
  el.innerHTML = msg + (pct !== undefined ? '<div class="pbar"><i style="width:'+pct+'%"></i></div>' : '');
}

// ── Liste des mois de la période ───────────────────────────────────────────
function monthList() {
  var m1 = +document.getElementById('m1').value, y1 = +document.getElementById('y1').value;
  var m2 = +document.getElementById('m2').value, y2 = +document.getElementById('y2').value;
  var out = [], y = y1, m = m1, guard = 0;
  if (y2 < y1 || (y2 === y1 && m2 < m1)) return null;
  while ((y < y2 || (y === y2 && m <= m2)) && guard++ < 300) {
    out.push({y:y, m:m});
    m++; if (m > 12) { m = 1; y++; }
  }
  return out;
}

// ── Dessin d'une rose ─────────────────────────────────────────────────────
function drawRose(obs, showAxes) {
  var S = 680;                       // haute résolution pour export
  var c = document.createElement('canvas');
  c.width = S; c.height = S;
  var ctx = c.getContext('2d');
  var cx = S/2, cy = S*0.46, R = S*0.35;

  ctx.fillStyle = '#fff'; ctx.fillRect(0,0,S,S);

  // Bins : 36 secteurs × 7 plages
  var bins = [], i, j;
  for (i=0;i<36;i++) { bins[i] = []; for (j=0;j<RANGES.length;j++) bins[i][j] = 0; }
  var total = 0, calm = 0;
  obs.forEach(function(o){
    if (o.spd === null || o.spd === undefined) return;
    total++;
    if (o.spd < 1) { calm++; return; }
    if (o.dir === null || o.dir === undefined) return;
    var s = Math.floor(((o.dir % 360) + 360) % 360 / 10) % 36;
    for (j=0;j<RANGES.length;j++) {
      if (o.spd >= RANGES[j].min && o.spd < RANGES[j].max) { bins[s][j]++; break; }
    }
  });
  if (total === 0) return null;

  // Max cumulé par secteur (en %)
  var maxPct = 0;
  for (i=0;i<36;i++) {
    var sum = bins[i].reduce(function(a,b){return a+b;},0);
    maxPct = Math.max(maxPct, sum/total*100);
  }
  var step = maxPct <= 4 ? 1 : (maxPct <= 8 ? 2 : (maxPct <= 20 ? 5 : 10));
  var maxScale = Math.ceil(maxPct/step)*step || step;

  // Cercles concentriques
  ctx.strokeStyle = '#dde8f0'; ctx.lineWidth = 1;
  ctx.font = '11px Arial'; ctx.fillStyle = '#aaa'; ctx.textAlign = 'left';
  for (var v = step; v <= maxScale; v += step) {
    var rr = R * v / maxScale;
    ctx.beginPath(); ctx.arc(cx, cy, rr, 0, Math.PI*2); ctx.stroke();
    ctx.fillText(v + '%', cx + 3, cy - rr - 2);
  }

  // Rayons + labels cardinaux
  for (i=0;i<36;i++) {
    var deg = i*10, isCard = deg % 90 === 0, isInter = deg % 45 === 0;
    var a = (deg - 90) * Math.PI/180;
    ctx.strokeStyle = isCard ? '#b0c8d8' : (isInter ? '#d0dde8' : '#eef3f8');
    ctx.beginPath(); ctx.moveTo(cx, cy);
    ctx.lineTo(cx + Math.cos(a)*R, cy + Math.sin(a)*R); ctx.stroke();
  }
  var cards = [{d:0,t:'N'},{d:90,t:'E'},{d:180,t:'S'},{d:270,t:'O'}];
  ctx.font = 'bold 17px Arial'; ctx.fillStyle = '#1673B2';
  ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
  cards.forEach(function(k){
    var a = (k.d - 90) * Math.PI/180;
    ctx.fillText(k.t, cx + Math.cos(a)*(R+22), cy + Math.sin(a)*(R+22));
  });

  // Pétales empilés
  var half = 10 * Math.PI/180 / 2 * 0.88;
  for (i=0;i<36;i++) {
    var acc = 0;
    var mid = (i*10 - 90) * Math.PI/180;
    for (j=0;j<RANGES.length;j++) {
      if (!bins[i][j]) continue;
      var pct = bins[i][j]/total*100;
      var r0 = R * acc / maxScale;
      var r1 = R * (acc + pct) / maxScale;
      ctx.beginPath();
      ctx.arc(cx, cy, r1, mid-half, mid+half, false);
      ctx.arc(cx, cy, r0, mid+half, mid-half, true);
      ctx.closePath();
      ctx.fillStyle = RANGES[j].color; ctx.fill();
      ctx.strokeStyle = 'rgba(255,255,255,.55)'; ctx.lineWidth = .6; ctx.stroke();
      acc += pct;
    }
  }

  // Axes de pistes
  if (showAxes) {
    ctx.setLineDash([6,5]); ctx.lineWidth = 1.6;
    ctx.font = 'bold 11px Arial'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    PISTES.forEach(function(p){
      var a = (p.qfu - 90) * Math.PI/180;
      ctx.strokeStyle = 'rgba(200,40,40,.55)';
      ctx.beginPath(); ctx.moveTo(cx, cy);
      ctx.lineTo(cx + Math.cos(a)*(R*0.97), cy + Math.sin(a)*(R*0.97)); ctx.stroke();
      ctx.setLineDash([]);
      var lx = cx + Math.cos(a)*(R+46), ly = cy + Math.sin(a)*(R+46);
      ctx.fillStyle = '#fff';
      ctx.fillRect(lx-16, ly-8, 32, 16);
      ctx.fillStyle = '#c62828';
      ctx.fillText(p.lbl, lx, ly);
      ctx.setLineDash([6,5]);
    });
    ctx.setLineDash([]);

    // Ligne repère à 40° (bleu pointillé) — seuil de bascule vent NE
    var ar = (REPERE_DEG - 90) * Math.PI/180;
    ctx.setLineDash([5,5]); ctx.lineWidth = 1.8;
    ctx.strokeStyle = 'rgba(30,110,220,.85)';
    ctx.beginPath(); ctx.moveTo(cx, cy);
    ctx.lineTo(cx + Math.cos(ar)*(R*0.97), cy + Math.sin(ar)*(R*0.97)); ctx.stroke();
    ctx.setLineDash([]);
    var rlx = cx + Math.cos(ar)*(R+40), rly = cy + Math.sin(ar)*(R+40);
    ctx.fillStyle = '#fff'; ctx.fillRect(rlx-20, rly-8, 40, 16);
    ctx.fillStyle = '#1e6edc'; ctx.font = 'bold 11px Arial';
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.fillText(REPERE_DEG + '°', rlx, rly);
  }

  // Centre
  ctx.beginPath(); ctx.arc(cx, cy, 3, 0, Math.PI*2);
  ctx.fillStyle = '#0e3d6b'; ctx.fill();

  // Légende des couleurs (bas de l'image, sur 2 lignes)
  var lx0 = 16, ly0 = S - 44, sw = 16, sh = 11, gapY = 18;
  var perLine = 4, colW = (S - 32) / perLine;
  ctx.textAlign = 'left'; ctx.textBaseline = 'middle'; ctx.font = '12px Arial';
  RANGES.forEach(function(r, i){
    var col = i % perLine, row = Math.floor(i / perLine);
    var x = lx0 + col*colW, y = ly0 + row*gapY;
    ctx.fillStyle = r.color;
    ctx.fillRect(x, y - sh/2, sw, sh);
    ctx.strokeStyle = 'rgba(0,0,0,.12)'; ctx.lineWidth = 1;
    ctx.strokeRect(x, y - sh/2, sw, sh);
    ctx.fillStyle = '#555';
    ctx.fillText(r.label, x + sw + 5, y);
  });

  return {canvas:c, total:total, calm:calm};
}

// ── Récupération + rendu ──────────────────────────────────────────────────
async function run() {
  var months = monthList();
  if (!months) { setStatus('err', '❌ La période de fin doit être postérieure à la période de début.'); return; }
  if (months.length > 60) { setStatus('err', '❌ Période trop longue (max 60 mois).'); return; }

  var go = document.getElementById('go'), dl = document.getElementById('dlall');
  go.disabled = true; dl.disabled = true;
  results = [];
  document.getElementById('grid').innerHTML = '';
  var showAxes = document.getElementById('axes').checked;
  var ok = 0, ko = 0;

  for (var k = 0; k < months.length; k++) {
    var mm = months[k];
    var label = MOIS[mm.m] + ' ' + mm.y;
    setStatus('info', '⏳ Chargement IRM — ' + label + ' (' + (k+1) + '/' + months.length + ')',
              Math.round(k/months.length*100));

    var card = document.createElement('div');
    card.className = 'rose';
    card.innerHTML = '<h3>' + label + '</h3><div class="sub">chargement…</div>';
    document.getElementById('grid').appendChild(card);

    try {
      var r = await fetch('/api/rose_vents.php?year=' + mm.y + '&month=' + mm.m);
      var d = await r.json();
      if (d.error) throw new Error(d.error);
      if (!d.observations || !d.observations.length) throw new Error('Aucune observation');

      var res = drawRose(d.observations, showAxes);
      if (!res) throw new Error('Données insuffisantes');

      var calmPct = res.total ? (res.calm / res.total * 100).toFixed(1) : '0';
      card.innerHTML =
        '<h3>' + label + '</h3>' +
        '<div class="sub">' + d.station + '</div>' +
        '<div class="stats">' +
          '<div class="st"><div class="v">' + d.count + '</div><div class="l">obs.</div></div>' +
          '<div class="st"><div class="v">' + (d.avg_spd_kt !== null ? d.avg_spd_kt : '—') + '</div><div class="l">moy. kt</div></div>' +
          '<div class="st"><div class="v">' + (d.max_spd_kt !== null ? d.max_spd_kt : '—') + '</div><div class="l">max kt</div></div>' +
          '<div class="st"><div class="v">' + calmPct + '%</div><div class="l">calme</div></div>' +
        '</div>' +
        '<div class="acts"><button class="btn-sm">⬇ PNG</button></div>';
      card.insertBefore(res.canvas, card.querySelector('.stats'));
      card.querySelector('.btn-sm').onclick = (function(cv, lb){
        return function(){ dlCanvas(cv, 'rose_vents_' + lb.replace(/ /g,'_') + '.png'); };
      })(res.canvas, label);

      results.push({label:label, canvas:res.canvas, count:d.count,
                    avg:d.avg_spd_kt, max:d.max_spd_kt, calm:calmPct});
      ok++;
    } catch (e) {
      card.className = 'rose err';
      card.innerHTML = '<h3>' + label + '</h3><div class="msg">⚠ ' + (e.message || 'Erreur') + '</div>';
      ko++;
    }
  }

  setStatus(ko ? 'info' : 'ok',
    (ko ? '⚠ ' : '✅ ') + ok + ' rose(s) générée(s)' + (ko ? ' · ' + ko + ' échec(s)' : '') + '.');
  go.disabled = false;
  dl.disabled = results.length === 0;
}

// ── Téléchargements ───────────────────────────────────────────────────────
function dlCanvas(cv, name) {
  var a = document.createElement('a');
  a.download = name; a.href = cv.toDataURL('image/png'); a.click();
}

function downloadPlanche() {
  if (!results.length) return;
  var n = results.length;
  var cols = n <= 2 ? n : (n <= 6 ? 3 : 4);
  var rows = Math.ceil(n / cols);
  var CW = 680, TH = 78, PAD = 26, HEAD = 96;
  var W = cols*CW + (cols+1)*PAD;
  var H = HEAD + rows*(CW+TH) + (rows+1)*PAD;

  var c = document.createElement('canvas');
  c.width = W; c.height = H;
  var x = c.getContext('2d');
  x.fillStyle = '#f0f4f8'; x.fillRect(0,0,W,H);

  // Titre
  x.fillStyle = '#0e3d6b'; x.textAlign = 'center'; x.textBaseline = 'middle';
  x.font = 'bold 46px Arial';
  x.fillText('Roses des vents — EBBR (IRM 6451 Zaventem/Melsbroek)', W/2, 40);
  x.font = '26px Arial'; x.fillStyle = '#7a8ba0';
  x.fillText(results[0].label + '  →  ' + results[n-1].label + '   ·   casuffit.be', W/2, 76);

  results.forEach(function(r, i){
    var col = i % cols, row = Math.floor(i / cols);
    var px = PAD + col*(CW+PAD);
    var py = HEAD + PAD + row*(CW+TH+PAD);
    x.fillStyle = '#fff';
    x.fillRect(px, py, CW, CW+TH);
    x.fillStyle = '#0e3d6b'; x.font = 'bold 32px Arial'; x.textAlign = 'center';
    x.fillText(r.label, px + CW/2, py + 28);
    x.fillStyle = '#8a9bb0'; x.font = '20px Arial';
    x.fillText(r.count + ' obs.  ·  moy. ' + r.avg + ' kt  ·  max ' + r.max + ' kt  ·  calme ' + r.calm + '%',
               px + CW/2, py + 58);
    x.drawImage(r.canvas, px, py + TH, CW, CW);
  });

  dlCanvas(c, 'roses_vents_planche.png');
}
</script>
</body>
</html>
