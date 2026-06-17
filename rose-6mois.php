<?php /* rose-6mois.php — Générateur de rose des vents multi-mois (image Facebook) — v1 */ ?>
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
  .canvas-box{background:#fff;border-radius:12px;padding:16px;box-shadow:0 1px 6px rgba(0,0,0,.08);display:inline-block}
  canvas{max-width:100%;height:auto;display:block;border-radius:8px}
  .note{font-size:.78rem;color:#999;margin-top:12px;line-height:1.5}
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
    var eastCount = 0, westCount = 0; // 045–135° = Est ; 225–315° = Ouest

    lastObs.forEach(function(obs){
      var wdir = obs.dir, wspd = obs.spd;
      if(wspd === null || wdir === null) return;
      if(wspd < speedMin) return;
      if(wdir === 0 && wspd < 1){ calmCount++; return; }

      totalObs++;
      if(wspd > maxSpd) maxSpd = wspd;
      sumSpd += wspd; spdCount++;

      if(wdir >= 45 && wdir <= 135) eastCount++;
      if(wdir >= 225 && wdir <= 315) westCount++;

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
    var cx = 400, cy = 600, maxR = 320;

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
    var pctEast = grandTotal>0 ? Math.round(eastCount/grandTotal*100)+'%' : '—';
    var pctWest = grandTotal>0 ? Math.round(westCount/grandTotal*100)+'%' : '—';
    var pctCalm = grandTotal>0 ? Math.round(calmCount/grandTotal*100)+'%' : '—';

    var stats = [
      [grandTotal.toString(), 'Observations'],
      [domDir, 'Direction dominante'],
      [avgSpd, 'Vent moyen'],
      [maxSpd.toFixed(0)+' kt', 'Vent max'],
      [pctEast, "Vent d'Est (045–135°)"],
      [pctWest, "Vent d'Ouest (225–315°)"]
    ];
    var sx0 = 60, sy = 985, bw = (W-120)/3, bh = 78;
    ctx.textAlign = 'left';
    stats.forEach(function(st, i){
      var col = i%3, row = Math.floor(i/3);
      var x = sx0 + col*bw, y = sy + row*(bh+10);
      ctx.fillStyle = '#f5f8fb';
      roundRect(ctx, x, y, bw-14, bh, 10); ctx.fill();
      ctx.fillStyle = '#1673B2'; ctx.font = 'bold 27px Arial'; ctx.textBaseline='alphabetic';
      ctx.fillText(st[0], x+18, y+38);
      ctx.fillStyle = '#8a98a5'; ctx.font = '16px Arial';
      ctx.fillText(st[1].toUpperCase(), x+18, y+62);
    });

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

  initControls();
})();
</script>
</body>
</html>
