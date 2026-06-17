<?php /* rose-6mois.php — Générateur de rose des vents multi-mois (image Facebook) — v4 */ ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Rose des vents EBBR — période — Ça suffit !</title>
<style>
  body{font-family:'Helvetica Neue',Arial,sans-serif;background:#eef3f8;color:#333;margin:0;padding:24px}
  .wrap{max-width:1120px;margin:0 auto}
  h1{font-size:1.25rem;color:#1673B2;margin:0 0 4px}
  .sub{font-size:.85rem;color:#888;margin-bottom:18px}
  .controls{display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;background:#fff;padding:14px 16px;border-radius:10px;margin-bottom:18px;box-shadow:0 1px 4px rgba(0,0,0,.06)}
  .cg{display:flex;flex-direction:column;gap:4px}
  .cg label{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#888}
  .cg select{padding:6px 10px;border:1.5px solid #ddd;border-radius:7px;font-size:.9rem;background:#fff;cursor:pointer}
  .btn{padding:8px 18px;border:none;border-radius:7px;font-size:.88rem;font-weight:700;cursor:pointer;font-family:inherit}
  .btn-blue{background:#1673B2;color:#fff}
  .btn-blue:hover{background:#0e3d6b}
  .btn-green{background:#1a9e3f;color:#fff}
  .btn-green:hover{background:#147a30}
  .btn:disabled{opacity:.5;cursor:default}
  #status{padding:12px 16px;border-radius:8px;font-size:.88rem;background:#f0f6ff;color:#1673B2;margin-bottom:16px}
  .canvas-box{background:#fff;border-radius:12px;padding:16px;box-shadow:0 1px 6px rgba(0,0,0,.08);display:inline-block;position:relative}
  canvas{max-width:100%;height:auto;display:block;border-radius:8px;cursor:crosshair}
  .note{font-size:.78rem;color:#999;margin-top:12px;line-height:1.5}
  #tooltip{position:absolute;background:rgba(20,20,20,.92);color:#fff;border-radius:10px;padding:10px 14px;font-size:.8rem;pointer-events:none;min-width:170px;z-index:10;box-shadow:0 4px 16px rgba(0,0,0,.3);display:none}
  #tooltip .tt-dir{font-size:1rem;font-weight:700;margin-bottom:6px}
  #tooltip .tt-row{display:flex;align-items:center;gap:7px;margin:2px 0}
  #tooltip .tt-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
  #tooltip .tt-lbl{color:#ccc;flex:1}
  #tooltip .tt-val{font-weight:700;color:#fff}
  #tooltip .tt-tot{border-top:1px solid rgba(255,255,255,.2);margin-top:6px;padding-top:5px;color:#aaa;font-size:.72rem}
</style>
</head>
<body>
<div class="wrap">
  <h1>🌬 Rose des vents EBBR — générateur d'image</h1>
  <div class="sub">Données IRM station 6451 (Zaventem/Melsbroek) · agrégation multi-mois · image prête pour les réseaux sociaux</div>

  <div class="controls">
    <div class="cg">
      <label>Année</label>
      <select id="year"></select>
    </div>
    <div class="cg">
      <label>De (mois)</label>
      <select id="from"></select>
    </div>
    <div class="cg">
      <label>À (mois)</label>
      <select id="to"></select>
    </div>
    <div class="cg">
      <label>Vitesse min.</label>
      <select id="speed">
        <option value="0">Tous vents</option>
        <option value="7">≥ 7 kt (seuil PRS)</option>
        <option value="10">≥ 10 kt</option>
        <option value="15">≥ 15 kt</option>
      </select>
    </div>
    <button class="btn btn-blue" id="btn-load" onclick="loadAll()">🔄 Générer</button>
    <button class="btn btn-green" id="btn-dl" onclick="downloadPng()" disabled>⬇ Télécharger PNG</button>
  </div>

  <div id="status">Choisissez la période puis cliquez sur « Générer ».</div>

  <div class="canvas-box">
    <canvas id="rose" width="1080" height="1080"></canvas>
    <div id="tooltip"></div>
  </div>

  <div class="note">
    L'image téléchargée (1080×1080, format carré idéal pour Facebook/Instagram) contient le titre, la légende
    et les statistiques. La direction indiquée est celle <strong>d'où vient le vent</strong> (convention météo).
    Données publiques IRM — résolution horaire.
  </div>
</div>

<script>
(function(){
  // ── Config (identique au widget rose_vents) ──────────────────────────────
  var DIRS_16 = ['N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSO','SO','OSO','O','ONO','NO','NNO'];
  var SPEED_BINS = [
    {min:0,  max:1,  color:'#d4edda', label:'< 1 kt'},
    {min:1,  max:4,  color:'#52c46a', label:'1 – 4 kt'},
    {min:4,  max:7,  color:'#1a9e3f', label:'4 – 7 kt'},
    {min:7,  max:11, color:'#f5d000', label:'7 – 11 kt'},
    {min:11, max:17, color:'#f07800', label:'11 – 17 kt'},
    {min:17, max:21, color:'#d42020', label:'17 – 21 kt'},
    {min:21, max:999,color:'#7b2fa0', label:'≥ 21 kt'}
  ];
  var MOIS = ['','Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];

  var lastObs = null;     // observations agrégées
  var lastPeriod = '';
  var lastFname = 'rose-vents.png';

  // Géométrie + agrégation du dernier rendu (pour le survol)
  var hov = {sectors:{}, grandTotal:0, cx:0, cy:0, maxR:0};

  // ── Init des sélecteurs ───────────────────────────────────────────────────
  function initControls(){
    var y = new Date().getFullYear();
    var ysel = document.getElementById('year');
    for(var i=y;i>=2000;i--){ var o=document.createElement('option');o.value=i;o.textContent=i;ysel.appendChild(o); }
    var fsel=document.getElementById('from'), tsel=document.getElementById('to');
    for(var m=1;m<=12;m++){
      var of=document.createElement('option');of.value=m;of.textContent=MOIS[m];fsel.appendChild(of);
      var ot=document.createElement('option');ot.value=m;ot.textContent=MOIS[m];tsel.appendChild(ot);
    }
    // Défaut : Janvier → Juin de l'année courante
    ysel.value = y; fsel.value = 1; tsel.value = 6;
  }

  function setStatus(msg){ document.getElementById('status').innerHTML = msg; }

  // ── Sélection de la piste à utiliser selon le vent (portage de api/metar.php) ──
  var SEL_QFU = {'07L':66,'25R':246,'07R':71,'25L':251,'01':14,'19':194};
  function selComps(wdir, spd){
    var c = {};
    for(var r in SEL_QFU){
      var delta = (wdir - SEL_QFU[r]) * Math.PI/180;
      var h = spd * Math.cos(delta);                 // + = vent de face, − = vent arrière
      c[r] = { tw: h < 0 ? -h : 0, xw: Math.abs(spd * Math.sin(delta)) };
    }
    return c;
  }
  function selectRunway(wdir, wspd, wgst){
    var wspd_eff = Math.max(wspd, wgst || 0);
    var comps   = selComps(wdir, wspd);
    var comps_g = wgst ? selComps(wdir, wgst) : null;
    function twEff(r){ return Math.max(comps[r].tw, comps_g ? comps_g[r].tw : 0); }
    function xwM(r){ return comps[r].xw; }           // crosswind = vent moyen uniquement (AIP)

    // Vent calme/faible → 25 préférentielle
    if(wspd_eff < 3) return 25;

    // PRS sur 25 (AIP 2013) : arrière moyen ≤ 7, latéral moyen ≤ 15, rafale arrière ≤ 10
    var tw25  = Math.max(comps['25R'].tw, comps['25L'].tw);
    var xw25  = Math.max(comps['25R'].xw, comps['25L'].xw);
    var tw25g = comps_g ? Math.max(comps_g['25R'].tw, comps_g['25L'].tw) : null;
    if(tw25 <= 7 && xw25 <= 15 && (tw25g === null || tw25g <= 10)) return 25;

    // Hors PRS → piste alternative selon le secteur (selectAltRunway)
    var d = ((Math.round(wdir) % 360) + 360) % 360;
    var XW_MAX = 20;
    if(d >= 335 || d < 40){              // Secteur Nord → 01
      if(xwM('01') > XW_MAX) return 19;
      return 1;
    } else if(d >= 40 && d < 130){       // Secteur NE→SE → 07
      if(xwM('07L') > XW_MAX) return 19;
      if(twEff('07L') <= 3) return 7;
      if(xwM('19') > XW_MAX) return 7;
      return 19;
    } else {                            // Secteur S/O/NO → 19
      if(xwM('19') > XW_MAX) return 7;
      return 19;
    }
  }

  // ── Chargement de tous les mois de la période ─────────────────────────────
  window.loadAll = function(){
    var year = parseInt(document.getElementById('year').value);
    var from = parseInt(document.getElementById('from').value);
    var to   = parseInt(document.getElementById('to').value);
    if(to < from){ var tmp=from; from=to; to=tmp; document.getElementById('from').value=from; document.getElementById('to').value=to; }

    document.getElementById('btn-load').disabled = true;
    document.getElementById('btn-dl').disabled = true;
    setStatus('⏳ Récupération des données IRM…');

    var months = [];
    for(var m=from;m<=to;m++) months.push(m);

    var calls = months.map(function(m){
      return fetch('/api/rose_vents.php?year='+year+'&month='+m)
        .then(function(r){ return r.json(); })
        .then(function(d){ return {month:m, data:d}; })
        .catch(function(){ return {month:m, data:{error:'réseau'}}; });
    });

    Promise.all(calls).then(function(results){
      var all = [];
      var okMonths = [], koMonths = [];
      results.forEach(function(res){
        if(res.data && res.data.observations && res.data.observations.length){
          all = all.concat(res.data.observations);
          okMonths.push(MOIS[res.month]);
        } else {
          koMonths.push(MOIS[res.month] + (res.data && res.data.error ? ' ('+res.data.error+')' : ''));
        }
      });

      document.getElementById('btn-load').disabled = false;

      if(!all.length){
        setStatus('❌ Aucune donnée IRM récupérée pour cette période. ' + (koMonths.length?('Échecs : '+koMonths.join(', ')):''));
        return;
      }

      lastObs = all;
      lastPeriod = (from===to ? MOIS[from] : MOIS[from]+' → '+MOIS[to]) + ' ' + year;
      lastFname = 'rose-vents-EBBR-'+year+'-'+('0'+from).slice(-2)+'_'+('0'+to).slice(-2)+'.png';

      var warn = koMonths.length ? '<br><span style="color:#c97a00">⚠ Mois sans données : '+koMonths.join(', ')+'</span>' : '';
      setStatus('✅ '+all.length+' observations sur '+okMonths.length+' mois ('+okMonths.join(', ')+').'+warn);

      render();
      document.getElementById('btn-dl').disabled = false;
    });
  };

  document.getElementById('speed').addEventListener('change', function(){ if(lastObs) render(); });

  // ── Rendu de la rose sur le grand canvas (image partageable) ──────────────
  function render(){
    var speedMin = parseInt(document.getElementById('speed').value) || 0;
    var canvas = document.getElementById('rose');
    var ctx = canvas.getContext('2d');
    var W = canvas.width, H = canvas.height;

    // Fond blanc
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0,0,W,H);

    // ── En-tête ──
    ctx.textAlign = 'left';
    ctx.fillStyle = '#1673B2';
    ctx.font = 'bold 40px "Helvetica Neue",Arial,sans-serif';
    ctx.fillText('Rose des vents — Aéroport de Bruxelles (EBBR)', 60, 70);
    ctx.fillStyle = '#555';
    ctx.font = '26px "Helvetica Neue",Arial,sans-serif';
    var subtitle = lastPeriod + '   ·   Source : IRM station 6451 (Zaventem)';
    if(speedMin>0) subtitle += '   ·   vents ≥ '+speedMin+' kt';
    ctx.fillText(subtitle, 62, 112);
    ctx.strokeStyle = '#e2eaf2'; ctx.lineWidth = 2;
    ctx.beginPath(); ctx.moveTo(60,135); ctx.lineTo(W-60,135); ctx.stroke();

    // ── Agrégation par secteur (36 × 10°) + plages de vitesse ──
    var sectors = {};
    var totalObs = 0, calmCount = 0, maxSpd = 0, sumSpd = 0, spdCount = 0;
    var dirCount = {};
    // « Piste qui aurait dû être utilisée » selon les règles PRS du site (api/metar.php) :
    //   25 préférentielle si dans les seuils, sinon piste alternative selon le secteur.
    //   Une seule piste comptée par observation → les 4 % totalisent 100 %.
    var fav = {1:0, 7:0, 25:0, 19:0};
    var selTotal = 0;

    lastObs.forEach(function(obs){
      var wdir = obs.dir, wspd = obs.spd, wgst = obs.gust;
      if(wspd === null || wdir === null) return;
      if(wspd < speedMin) return;

      // Piste attendue selon le vent (une seule par observation, calme inclus → 25)
      fav[selectRunway(wdir, wspd, wgst)]++;
      selTotal++;

      if(wdir === 0 && wspd < 1){ calmCount++; return; } // calme : pas de pétale directionnel

      totalObs++;
      if(wspd > maxSpd) maxSpd = wspd;
      sumSpd += wspd; spdCount++;

      var secIdx = Math.round(wdir/10) % 36;
      if(!sectors[secIdx]) sectors[secIdx] = {total:0, bins:SPEED_BINS.map(function(){return 0;})};
      sectors[secIdx].total++;
      dirCount[secIdx] = (dirCount[secIdx]||0)+1;
      for(var bi=0;bi<SPEED_BINS.length;bi++){
        if(wspd >= SPEED_BINS[bi].min && wspd < SPEED_BINS[bi].max){ sectors[secIdx].bins[bi]++; break; }
      }
    });

    var maxFreq = 0;
    for(var s=0;s<36;s++){ if(sectors[s]) maxFreq = Math.max(maxFreq, sectors[s].total); }
    var grandTotal = totalObs + calmCount;

    // ── Géométrie de la rose ──
    var cx = 400, cy = 540, maxR = 300;

    // Mémoriser pour le survol (détail des %)
    hov = {sectors:sectors, grandTotal:grandTotal, cx:cx, cy:cy, maxR:maxR};

    // Cercles de référence + labels %
    var rings = [0.25,0.5,0.75,1.0];
    ctx.strokeStyle = '#dde8f0'; ctx.lineWidth = 1.5;
    ctx.textAlign = 'center';
    rings.forEach(function(r){
      ctx.beginPath(); ctx.arc(cx,cy,maxR*r,0,Math.PI*2); ctx.stroke();
      var pct = grandTotal>0 ? Math.round((maxFreq*r/grandTotal)*100) : 0;
      ctx.fillStyle = '#9aa7b3'; ctx.font = '17px Arial';
      ctx.fillText(pct+'%', cx, cy - maxR*r - 6);
    });

    // Axes
    for(var a=0;a<36;a++){
      var ang = (a*10 - 90)*Math.PI/180;
      var isCard = (a%9===0), isInter = (a%3===0);
      ctx.strokeStyle = isCard ? '#b0c8d8' : (isInter ? '#d0dde8' : '#eef3f7');
      ctx.lineWidth = isCard ? 1.5 : 0.7;
      ctx.beginPath(); ctx.moveTo(cx,cy);
      ctx.lineTo(cx+Math.cos(ang)*(maxR+14), cy+Math.sin(ang)*(maxR+14)); ctx.stroke();
    }

    // Labels 16 directions
    ctx.textBaseline = 'middle';
    DIRS_16.forEach(function(name, di){
      var deg = di*22.5;
      var ang = (deg - 90)*Math.PI/180;
      var dist = maxR + 38;
      var isCard = (di%4===0); // N E S O
      ctx.font = isCard ? 'bold 26px Arial' : '19px Arial';
      ctx.fillStyle = isCard ? '#1673B2' : '#667';
      ctx.fillText(name, cx+Math.cos(ang)*dist, cy+Math.sin(ang)*dist);
    });

    // Pétales
    if(maxFreq > 0){
      for(var p=0;p<36;p++){
        if(!sectors[p]) continue;
        var ang2 = (p*10 - 90)*Math.PI/180;
        var halfW = (10/2)*Math.PI/180;
        var cum = 0;
        SPEED_BINS.forEach(function(bin, bi){
          var count = sectors[p].bins[bi];
          if(count === 0) return;
          var r = (count/maxFreq)*maxR;
          var r0 = cum; cum += r;
          ctx.beginPath(); ctx.moveTo(cx,cy);
          ctx.arc(cx,cy,r0+r, ang2-halfW, ang2+halfW); ctx.lineTo(cx,cy);
          ctx.fillStyle = bin.color; ctx.globalAlpha = 0.88; ctx.fill(); ctx.globalAlpha = 1;
          ctx.strokeStyle = 'rgba(255,255,255,.65)'; ctx.lineWidth = 1; ctx.stroke();
        });
      }
    }
    // Centre
    ctx.beginPath(); ctx.arc(cx,cy,7,0,Math.PI*2);
    ctx.fillStyle = '#fff'; ctx.fill();
    ctx.strokeStyle = '#1673B2'; ctx.lineWidth = 2; ctx.stroke();

    // ── Légende (plages de vitesse) ──
    var lx = 830, ly = 230;
    ctx.textAlign = 'left'; ctx.textBaseline = 'middle';
    ctx.fillStyle = '#444'; ctx.font = 'bold 22px Arial';
    ctx.fillText('Vitesse du vent', lx, ly - 34);
    SPEED_BINS.slice().reverse().forEach(function(b, i){
      var yy = ly + i*40;
      ctx.fillStyle = b.color;
      ctx.beginPath(); ctx.arc(lx+11, yy, 11, 0, Math.PI*2); ctx.fill();
      ctx.strokeStyle = 'rgba(0,0,0,.08)'; ctx.lineWidth=1; ctx.stroke();
      ctx.fillStyle = '#444'; ctx.font = '21px Arial';
      ctx.fillText(b.label, lx+32, yy);
    });

    // ── Statistiques (bas) ──
    var domDir = '—', domMax = 0, domIdx = -1;
    Object.keys(dirCount).forEach(function(d){ if(dirCount[d]>domMax){ domMax=dirCount[d]; domIdx=parseInt(d); } });
    if(domIdx >= 0){ var dd = domIdx*10; domDir = DIRS_16[Math.round(dd/22.5)%16] + ' ('+dd+'°)'; }
    var avgSpd = spdCount>0 ? (sumSpd/spdCount).toFixed(1)+' kt' : '—';
    function favPct(n){ return selTotal>0 ? Math.round(fav[n]/selTotal*100)+'%' : '—'; }

    // Rangée 1 : général · Rangée 2 : part du temps où chaque piste aurait dû être utilisée
    var stats = [
      [grandTotal.toString(),     'Observations',        '#1673B2'],
      [domDir,                    'Direction dominante', '#1673B2'],
      [avgSpd,                    'Vent moyen',          '#1673B2'],
      [maxSpd.toFixed(0)+' kt',   'Vent max',            '#1673B2'],
      [favPct(1),  'RWY 01 attendue · Nord',  '#1a9e3f'],
      [favPct(7),  'RWY 07 attendue · Est',   '#f07800'],
      [favPct(25), 'RWY 25 attendue · Ouest', '#1673B2'],
      [favPct(19), 'RWY 19 attendue · Sud',   '#1673B2']
    ];
    var sx0 = 60, sy = 905, bw = (W-120)/4, bh = 66, gap = 10;
    ctx.textAlign = 'left';
    stats.forEach(function(st, i){
      var col = i%4, row = Math.floor(i/4);
      var x = sx0 + col*bw, y = sy + row*(bh+gap);
      ctx.fillStyle = '#f5f8fb';
      roundRect(ctx, x, y, bw-12, bh, 10); ctx.fill();
      ctx.fillStyle = st[2]; ctx.font = 'bold 26px Arial'; ctx.textBaseline='alphabetic';
      ctx.fillText(st[0], x+16, y+34);
      ctx.fillStyle = '#8a98a5'; ctx.font = '14px Arial';
      ctx.fillText(st[1].toUpperCase(), x+16, y+55);
    });
    // Précision méthodo (sous les stats)
    ctx.fillStyle = '#aeb9c4'; ctx.font = 'italic 14px Arial'; ctx.textAlign = 'left';
    ctx.fillText('Piste qui aurait dû être utilisée selon le vent — règles PRS du site : 25 préférentielle si dans les seuils, sinon 01 / 07 / 19 selon le secteur · calme → 25.', 60, sy + 2*(bh+gap) + 14);

    // Pied de page
    ctx.textAlign = 'right'; ctx.fillStyle = '#b3c0cc'; ctx.font = '18px Arial';
    ctx.fillText('casuffit.be', W-60, 70);
  }

  function roundRect(ctx,x,y,w,h,r){
    ctx.beginPath();
    ctx.moveTo(x+r,y); ctx.arcTo(x+w,y,x+w,y+h,r); ctx.arcTo(x+w,y+h,x,y+h,r);
    ctx.arcTo(x,y+h,x,y,r); ctx.arcTo(x,y,x+w,y,r); ctx.closePath();
  }

  // ── Téléchargement PNG ────────────────────────────────────────────────────
  window.downloadPng = function(){
    var canvas = document.getElementById('rose');
    var a = document.createElement('a');
    a.download = lastFname;
    a.href = canvas.toDataURL('image/png');
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
  };

  // ── Survol : détail des % par secteur ─────────────────────────────────────
  (function(){
    var canvas = document.getElementById('rose');
    var tooltip = document.getElementById('tooltip');
    var box = canvas.parentElement;

    canvas.addEventListener('mousemove', function(e){
      if(!lastObs || hov.grandTotal === 0){ tooltip.style.display='none'; return; }
      var rect = canvas.getBoundingClientRect();
      var scaleX = canvas.width / rect.width;
      var scaleY = canvas.height / rect.height;
      var mx = (e.clientX - rect.left) * scaleX;
      var my = (e.clientY - rect.top)  * scaleY;
      var dx = mx - hov.cx, dy = my - hov.cy;
      var dist = Math.sqrt(dx*dx + dy*dy);

      if(dist < 8 || dist > hov.maxR + 16){ tooltip.style.display='none'; return; }

      var angleDeg = (Math.atan2(dy, dx) * 180/Math.PI + 90 + 360) % 360;
      var secIdx = Math.round(angleDeg/10) % 36;
      var sec = hov.sectors[secIdx];
      if(!sec){ tooltip.style.display='none'; return; }

      var deg = secIdx*10;
      var nameLabel = DIRS_16[Math.round(deg/22.5)%16] + ' (' + deg + '°)';

      var rows = SPEED_BINS.slice().reverse().map(function(b, ri){
        var bi = SPEED_BINS.length - 1 - ri;
        var cnt = sec.bins[bi] || 0;
        if(cnt === 0) return '';
        var pct = (cnt/hov.grandTotal*100).toFixed(2);
        return '<div class="tt-row"><div class="tt-dot" style="background:'+b.color+'"></div>'
          + '<span class="tt-lbl">'+b.label+'</span><span class="tt-val">'+pct+'%</span></div>';
      }).join('');

      var totalPct = (sec.total/hov.grandTotal*100).toFixed(1);
      tooltip.innerHTML = '<div class="tt-dir">'+nameLabel+'</div>' + rows
        + '<div class="tt-tot">Total : '+totalPct+'%  ·  '+sec.total+' obs</div>';

      // Position (coordonnées affichées, relatives à .canvas-box)
      var tipX = (e.clientX - box.getBoundingClientRect().left) + 14;
      var tipY = (e.clientY - box.getBoundingClientRect().top)  - 20;
      tooltip.style.display = 'block';
      var tw = tooltip.offsetWidth;
      if(tipX + tw + 20 > box.offsetWidth) tipX = tipX - tw - 28;
      tooltip.style.left = tipX + 'px';
      tooltip.style.top  = tipY + 'px';
    });

    canvas.addEventListener('mouseleave', function(){ tooltip.style.display='none'; });
  })();

  initControls();
})();
</script>
</body>
</html>
