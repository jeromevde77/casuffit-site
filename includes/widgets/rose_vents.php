<?php
/* ── Widget Rose des vents EBBR — données IRM station 6451 ─────────────── */
?>
<div class="rvw-wrap">
  <div class="rvw-header">
    <div class="rvw-title">🌬 Rose des vents — EBBR (ZAVENTEM)</div>
    <div class="rvw-subtitle">Source : IRM Station 6451 · Données synoptiques horaires</div>
  </div>

  <!-- Sélecteurs -->
  <div class="rvw-controls">
    <div class="rvw-ctrl-group">
      <label class="rvw-label">Année</label>
      <select id="rvw-year" onchange="rvwLoad()"></select>
    </div>
    <div class="rvw-ctrl-group">
      <label class="rvw-label">Mois</label>
      <select id="rvw-month" onchange="rvwLoad()">
        <option value="01">Janvier</option>
        <option value="02">Février</option>
        <option value="03">Mars</option>
        <option value="04">Avril</option>
        <option value="05">Mai</option>
        <option value="06">Juin</option>
        <option value="07">Juillet</option>
        <option value="08">Août</option>
        <option value="09">Septembre</option>
        <option value="10">Octobre</option>
        <option value="11">Novembre</option>
        <option value="12">Décembre</option>
      </select>
    </div>
    <div class="rvw-ctrl-group">
      <label class="rvw-label">Vitesse min.</label>
      <select id="rvw-speed" onchange="rvwRender()">
        <option value="0">Tous vents</option>
        <option value="7">≥ 7 kt (seuil PRS)</option>
        <option value="10">≥ 10 kt</option>
        <option value="15">≥ 15 kt</option>
      </select>
    </div>
    <button class="rvw-btn" onclick="rvwLoad()">🔄 Charger</button>
  </div>

  <!-- Statut -->
  <div id="rvw-status" class="rvw-status" style="display:none"></div>

  <!-- Canvas + légende -->
  <div class="rvw-canvas-wrap" style="display:none" id="rvw-chart-wrap">
    <div class="rvw-rose-container">
      <canvas id="rvw-canvas" width="420" height="420" style="cursor:crosshair"></canvas>
      <div id="rvw-tooltip" class="rvw-tooltip" style="display:none"></div>
    </div>
    <div class="rvw-legend" id="rvw-legend"></div>
  </div>

  <!-- Stats -->
  <div class="rvw-stats" id="rvw-stats" style="display:none"></div>

</div>

<style>
.rvw-wrap{font-family:'Helvetica Neue',Arial,sans-serif;background:#fff;border-radius:12px;padding:20px;max-width:780px}
.rvw-header{margin-bottom:14px}
.rvw-title{font-size:1rem;font-weight:700;color:#1673B2}
.rvw-subtitle{font-size:.72rem;color:#aaa;margin-top:2px}
.rvw-controls{display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;margin-bottom:16px;background:#f7fafd;padding:12px 14px;border-radius:8px}
.rvw-ctrl-group{display:flex;flex-direction:column;gap:4px}
.rvw-label{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#888}
.rvw-controls select{padding:6px 10px;border:1.5px solid #ddd;border-radius:7px;font-size:.85rem;font-family:inherit;cursor:pointer;background:#fff}
.rvw-btn{padding:7px 16px;background:#1673B2;color:#fff;border:none;border-radius:7px;font-size:.85rem;font-weight:700;cursor:pointer;font-family:inherit;align-self:flex-end}
.rvw-btn:hover{background:#0e3d6b}
.rvw-status{padding:10px 14px;border-radius:8px;font-size:.82rem;background:#f0f6ff;color:#1673B2;text-align:center}
.rvw-canvas-wrap{display:flex;gap:20px;flex-wrap:wrap;align-items:flex-start;justify-content:center}
.rvw-rose-container{position:relative}
.rvw-legend{display:flex;flex-direction:column;gap:6px;min-width:140px;padding-top:20px}
.rvw-legend-item{display:flex;align-items:center;gap:7px;font-size:.75rem}
.rvw-legend-color{width:16px;height:12px;border-radius:3px;flex-shrink:0}
.rvw-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:8px;margin-top:14px}
.rvw-stat-box{background:#f7fafd;border-radius:8px;padding:10px 12px;text-align:center}
.rvw-stat-val{font-size:1.1rem;font-weight:800;color:#1673B2}
.rvw-stat-lbl{font-size:.67rem;color:#888;text-transform:uppercase;letter-spacing:.04em;margin-top:2px}
.rvw-rose-container{position:relative}
.rvw-tooltip{position:absolute;background:rgba(20,20,20,.92);color:#fff;border-radius:10px;padding:10px 14px;font-size:.78rem;pointer-events:none;min-width:160px;z-index:10;box-shadow:0 4px 16px rgba(0,0,0,.3)}
.rvw-tooltip-dir{font-size:1rem;font-weight:700;margin-bottom:6px}
.rvw-tooltip-row{display:flex;align-items:center;gap:7px;margin:2px 0}
.rvw-tooltip-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
.rvw-tooltip-lbl{color:#ccc;flex:1}
.rvw-tooltip-val{font-weight:700;color:#fff}
</style>

<script>
(function(){

// -- Config ----------------------------------------------------------------
var STATION = '6451';
var IRM_URL = 'https://opendata.meteo.be/service/ows'
  + '?service=WFS'
  + '&version=2.0.0'
  + '&request=GetFeature'
  + '&typeName=synop:synop_data'
  + '&outputFormat=application/json';

// Directions - 36 secteurs de 10- (pr-cision BATC)
var DIRS_36 = [
  'N',   '',    'NNE', '',    'NE',  '',
  'ENE', '',    'E',   '',    'ESE', '',
  'SE',  '',    'SSE', '',    'S',   '',
  'S',   '',    'SSO', '',    'SO',  '',
  'OSO', '',    'O',   '',    'ONO', '',
  'NO',  '',    'NNO', '',    'NNO', ''
];
var DIRS_16 = ['N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSO','SO','OSO','O','ONO','NO','NNO']; // pour compat

// Palettes vitesse (kt) - 7 plages palette s-curit- contrast-e
var SPEED_BINS = [
  {min:0,  max:1,  color:'#d4edda', label:'< 1 kt'},
  {min:1,  max:4,  color:'#52c46a', label:'1 – 4 kts'},
  {min:4,  max:7,  color:'#1a9e3f', label:'4 – 7 kts'},
  {min:7,  max:11, color:'#f5d000', label:'7 – 11 kts'},
  {min:11, max:17, color:'#f07800', label:'11 – 17 kts'},
  {min:17, max:21, color:'#d42020', label:'17 – 21 kts'},
  {min:21, max:999,color:'#7b2fa0', label:'>= 21 kts'},
];

// -- Donn-es charg-es
var rvwData = null;
var rvwSectors = {};     // derni-re agr-gation rendue
var rvwGrandTotal = 0;   // total obs pour les %
var rvwMaxR = 0;         // rayon max du dernier rendu
var rvwCx = 0, rvwCy = 0;

// -- Init ann-es (apr-s rendu DOM) -----------------------------------------
function rvwInitYear(){
  var sel = document.getElementById('rvw-year');
  if(!sel) return;
  var cur = new Date();
  var y = cur.getFullYear();
  for(var i=y; i>=2000; i--){
    var o = document.createElement('option');
    o.value = i; o.textContent = i;
    sel.appendChild(o);
  }
  // Mois courant par d-faut
  var msel = document.getElementById('rvw-month');
  if(msel) msel.value = ('0'+(cur.getMonth()+1)).slice(-2);
}
// Exposer toutes les fonctions globales imm-diatement
window.rvwLoad     = function(){ return rvwLoad.apply(this, arguments); };
window.rvwRender   = function(){ return rvwRender.apply(this, arguments); };
// rvwInitYear est appel- par showTab() dans index.php quand le widget devient visible
window.rvwInitYear = rvwInitYear;

// -- Chargement donn-es IRM ------------------------------------------------
function rvwLoad(){
  var year  = document.getElementById('rvw-year').value;
  var month = document.getElementById('rvw-month').value;

  // Calculer start / end du mois
  var startDate = year + '-' + month + '-01T00:00:00Z';
  var d = new Date(Date.UTC(parseInt(year), parseInt(month), 1)); // 1er du mois suivant
  var endDate = d.toISOString().substring(0,19) + 'Z';

  setStatus('⏳ Chargement des données IRM pour ' + getMonthName(month) + ' ' + year + '…');
  document.getElementById('rvw-chart-wrap').style.display = 'none';
  document.getElementById('rvw-stats').style.display = 'none';

  // IRM WFS - on charge via proxy PHP pour -viter CORS
  var apiUrl = '/api/rose_vents.php?year=' + year + '&month=' + month;

  fetch(apiUrl)
    .then(function(r){ return r.json(); })
    .then(function(d){
      if(d.error){ setStatus('❌ ' + d.error); return; }
      rvwData = d;
      setStatus('');
      document.getElementById('rvw-chart-wrap').style.display = 'flex';
      document.getElementById('rvw-stats').style.display = 'grid';
      rvwRender();
    })
    .catch(function(e){ setStatus('❌ Erreur de connexion: ' + e.message); });
}

// -- Rendu de la rose ------------------------------------------------------
function rvwRender(){
  if(!rvwData) return;

  var speedMin = parseInt(document.getElementById('rvw-speed').value) || 0;
  var canvas   = document.getElementById('rvw-canvas');
  var ctx      = canvas.getContext('2d');
  var W = canvas.width, H = canvas.height;
  var cx = W/2, cy = H/2;
  var maxR = Math.min(cx, cy) - 40;

  ctx.clearRect(0,0,W,H);

  // -- Filtrer et agr-ger par secteur + vitesse --
  var sectors = {}; // secteur - {total, bins:[count par plage]}
  var totalObs = 0;
  var calmCount = 0;
  var maxSpd = 0, sumSpd = 0, spdCount = 0;
  var dirCount = {}; // pour la direction dominante

  (rvwData.observations || []).forEach(function(obs){
    var wdir = obs.dir;
    var wspd = obs.spd; // en kt

    if(wspd === null || wdir === null) return;
    if(wspd < speedMin) return;
    if(wdir === 0 && wspd < 1){ calmCount++; return; } // calme

    totalObs++;
    if(wspd > maxSpd) maxSpd = wspd;
    sumSpd += wspd; spdCount++;

    // Secteur 36 directions (10- chacun)
    var secIdx = Math.round(wdir / 10) % 36;
    var secName = secIdx; // index num-rique 0-35

    if(!sectors[secName]) sectors[secName] = {total:0, bins:SPEED_BINS.map(function(){return 0;})};
    sectors[secName].total++;
    dirCount[secName] = (dirCount[secName]||0) + 1;

    // Plage de vitesse
    for(var bi=0; bi<SPEED_BINS.length; bi++){
      if(wspd >= SPEED_BINS[bi].min && wspd < SPEED_BINS[bi].max){
        sectors[secName].bins[bi]++;
        break;
      }
    }
  });

  // Max fr-quence pour l'-chelle
  var maxFreq = 0;
  for(var _si=0;_si<36;_si++){ if(sectors[_si]) maxFreq = Math.max(maxFreq, sectors[_si].total); }
  if(maxFreq === 0){ setStatus('⚠ Aucune observation pour ce filtre.'); return; }

  // -- Cercles de r-f-rence --
  var grandTotal = totalObs + calmCount;
  // Stocker pour le tooltip
  rvwSectors = sectors; rvwGrandTotal = grandTotal; rvwMaxR = maxR; rvwCx = cx; rvwCy = cy;
  var rings = [0.25, 0.5, 0.75, 1.0];
  ctx.strokeStyle = '#dde8f0';
  ctx.lineWidth = 1;
  rings.forEach(function(r){
    ctx.beginPath();
    ctx.arc(cx, cy, maxR * r, 0, Math.PI*2);
    ctx.stroke();
    // Label % bas- sur le total r-el
    var pct = grandTotal > 0 ? Math.round((maxFreq * r / grandTotal) * 100) : 0;
    ctx.fillStyle = '#999';
    ctx.font = '10px Arial';
    ctx.textAlign = 'center';
    ctx.fillText(pct+'%', cx, cy - maxR*r - 3);
  });

  // -- Axes fins pour les 36 secteurs --
  for(var _ai=0; _ai<36; _ai++){
    var angle = (_ai * 10 - 90) * Math.PI / 180;
    var isCardinal = (_ai % 9 === 0); // 0,90,180,270-
    var isInter = (_ai % 3 === 0);    // multiples de 30-
    ctx.strokeStyle = isCardinal ? '#b0c8d8' : (isInter ? '#d0dde8' : '#e8eef4');
    ctx.lineWidth = isCardinal ? 1 : 0.5;
    ctx.beginPath();
    ctx.moveTo(cx, cy);
    ctx.lineTo(cx + Math.cos(angle)*(maxR+8), cy + Math.sin(angle)*(maxR+8));
    ctx.stroke();
  }

  // -- Labels sur le p-rim-tre (18 labels aux multiples de 20-) --
  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';
  DIRS_36.forEach(function(name, di){
    if(!name) return; // secteurs sans label
    var angle = (di * 10 - 90) * Math.PI / 180;
    var dist  = maxR + 22;
    var isCardinal = (di % 9 === 0); // N, E, S, O
    ctx.font = isCardinal ? 'bold 13px Arial' : '10px Arial';
    ctx.fillStyle = isCardinal ? '#1673B2' : '#555';
    ctx.fillText(name, cx + Math.cos(angle)*dist, cy + Math.sin(angle)*dist);
  });

  // -- P-tales de la rose (36 secteurs - 10-, empil-s par plage) --
  for(var _pi=0; _pi<36; _pi++){
    var dirName = _pi;
    var di = _pi;
    if(!sectors[dirName]) continue;
    var angle = (_pi * 10 - 90) * Math.PI / 180; // 0- = Nord = --/2
    var halfW  = (10 / 2) * Math.PI / 180;        // demi-largeur du p-tale

    var cumRadius = 0;
    SPEED_BINS.forEach(function(bin, bi){
      var count = sectors[dirName].bins[bi];
      if(count === 0) return;
      var r = (count / maxFreq) * maxR;
      var r0 = cumRadius;
      cumRadius += r;

      ctx.beginPath();
      ctx.moveTo(cx, cy);
      ctx.arc(cx, cy, r0 + r, angle - halfW, angle + halfW);
      ctx.lineTo(cx, cy);
      ctx.fillStyle = bin.color;
      ctx.globalAlpha = 0.85;
      ctx.fill();
      ctx.globalAlpha = 1;
      ctx.strokeStyle = 'rgba(255,255,255,.6)';
      ctx.lineWidth = 0.5;
      ctx.stroke();
    });  // fin SPEED_BINS.forEach
  }  // fin for _pi (36 secteurs)

  // -- Centre --
  ctx.beginPath();
  ctx.arc(cx, cy, 5, 0, Math.PI*2);
  ctx.fillStyle = '#fff';
  ctx.fill();
  ctx.strokeStyle = '#1673B2';
  ctx.lineWidth = 1.5;
  ctx.stroke();

  // -- L-gende --
  var legEl = document.getElementById('rvw-legend');
  legEl.innerHTML = SPEED_BINS.slice().reverse().map(function(b){
    return '<div class="rvw-legend-item">'
      + '<div class="rvw-legend-color" style="background:'+b.color+';border-radius:50%;width:12px;height:12px"></div>'
      + '<span>'+b.label+'</span></div>';
  }).join('');

  // -- Stats --
  var domDir = '—';
  var domMax = 0;
  var domIdx = -1;
  Object.keys(dirCount).forEach(function(d){
    if(dirCount[d]>domMax){ domMax=dirCount[d]; domIdx=parseInt(d); }
  });
  if(domIdx >= 0) {
    var domDeg = domIdx * 10;
    // Trouver le nom le plus proche dans DIRS_16
    var d16idx = Math.round(domDeg / 22.5) % 16;
    domDir = DIRS_16[d16idx] + ' (' + domDeg + '°)';
  }

  var avgSpd = spdCount > 0 ? (sumSpd/spdCount).toFixed(1) : '—';
  var pctCalm = totalObs > 0 ? ((calmCount/(totalObs+calmCount))*100).toFixed(0) : 0;
  var pctPRS  = totalObs > 0 ? ((rvwData.observations.filter(function(o){return o.spd>=7;}).length / (totalObs+calmCount))*100).toFixed(0) : 0;

  document.getElementById('rvw-stats').innerHTML =
    stat(totalObs + calmCount, 'Observations') +
    stat(avgSpd + ' kt', 'Vent moy.') +
    stat(maxSpd.toFixed(0) + ' kt', 'Vent max.') +
    stat(domDir, 'Direction dominante') +
    stat(pctCalm + '%', 'Calme (< 1 kt)') +
    stat(pctPRS + '%', '≥ 7 kt (seuil PRS)');
}

function stat(val, lbl){
  return '<div class="rvw-stat-box"><div class="rvw-stat-val">'+val+'</div><div class="rvw-stat-lbl">'+lbl+'</div></div>';
}

function getMonthName(m){
  var names = ['','Janvier','Fevrier','Mars','Avril','Mai','Juin','Juillet','Aout','Septembre','Octobre','Novembre','Decembre'];
  return names[parseInt(m)];
}

function setStatus(msg){
  var el = document.getElementById('rvw-status');
  el.style.display = msg ? 'block' : 'none';
  el.textContent = msg;
}

// -- Tooltip au survol --------------------------------------------------
(function(){
  function initTooltip(){
  var canvas  = document.getElementById('rvw-canvas');
  var tooltip = document.getElementById('rvw-tooltip');
  if(!canvas || !tooltip) return;
  var wrap    = canvas.parentElement;

  canvas.addEventListener('mousemove', function(e){
    var rect = canvas.getBoundingClientRect();
    var scaleX = canvas.width  / rect.width;
    var scaleY = canvas.height / rect.height;
    var mx = (e.clientX - rect.left)  * scaleX;
    var my = (e.clientY - rect.top)   * scaleY;
    var dx = mx - rvwCx, dy = my - rvwCy;
    var dist = Math.sqrt(dx*dx + dy*dy);

    if(dist < 6 || dist > rvwMaxR + 5 || rvwGrandTotal === 0){
      tooltip.style.display = 'none'; return;
    }

    // Angle - secteur (0-35)
    var angleDeg = (Math.atan2(dy, dx) * 180 / Math.PI + 90 + 360) % 360;
    var secIdx   = Math.round(angleDeg / 10) % 36;
    var sec = rvwSectors[secIdx];
    if(!sec){ tooltip.style.display = 'none'; return; }

    // Construire le tooltip
    var degLabel = secIdx * 10;
    // Trouver nom dans DIRS_16
    var d16 = ['N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSO','SO','OSO','O','ONO','NO','NNO'];
    var nameLabel = d16[Math.round(degLabel/22.5)%16] + ' (' + degLabel + '°)';

    var rows = SPEED_BINS.slice().reverse().map(function(b, ri){
      var bi = SPEED_BINS.length - 1 - ri;
      var cnt = sec.bins[bi] || 0;
      if(cnt === 0) return '';
      var pct = rvwGrandTotal > 0 ? (cnt/rvwGrandTotal*100).toFixed(2) : '0';
      return '<div class="rvw-tooltip-row">'
        + '<div class="rvw-tooltip-dot" style="background:'+b.color+'"></div>'
        + '<span class="rvw-tooltip-lbl">'+b.label+'</span>'
        + '<span class="rvw-tooltip-val">'+pct+'%</span>'
        + '</div>';
    }).join('');

    var totalPct = rvwGrandTotal > 0 ? (sec.total/rvwGrandTotal*100).toFixed(1) : '0';
    tooltip.innerHTML = '<div class="rvw-tooltip-dir">'+nameLabel+'</div>'
      + rows
      + '<div style="border-top:1px solid rgba(255,255,255,.2);margin-top:6px;padding-top:5px;color:#aaa;font-size:.72rem">Total : '+totalPct+'%</div>';

    // Positionner le tooltip
    var tipX = e.clientX - wrap.getBoundingClientRect().left + 14;
    var tipY = e.clientY - wrap.getBoundingClientRect().top  - 20;
    // -viter d-bordement droite
    tooltip.style.display = 'block';
    var tw = tooltip.offsetWidth;
    if(tipX + tw + 20 > wrap.offsetWidth) tipX = tipX - tw - 28;
    tooltip.style.left = tipX + 'px';
    tooltip.style.top  = tipY + 'px';
  });

  canvas.addEventListener('mouseleave', function(){
    tooltip.style.display = 'none';
  });
  } // fin initTooltip
  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', initTooltip);
  } else {
    initTooltip();
  }
})();

})();
</script>
