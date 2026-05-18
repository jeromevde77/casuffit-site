<?php /* Widget vols en temps réel — Brussels Area (OpenSky Network) */ ?>

<div class="vbr" id="vbr">
  <div class="vbr-header">
    <div class="vbr-title">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M21 16v-2a4 4 0 0 0-4-4H5"/>
        <polyline points="1 12 5 8 9 12"/>
        <path d="M3 8v2a4 4 0 0 0 4 4h12"/>
        <polyline points="23 12 19 16 15 12"/>
      </svg>
      Vols en cours — Zone Bruxelles
    </div>
    <div style="display:flex;gap:8px;align-items:center">
      <span class="vbr-count" id="vbr-count">—</span>
      <button class="vbr-refresh" id="vbr-refresh-btn" onclick="vbrLoad()" title="Actualiser">↺</button>
    </div>
  </div>

  <!-- Carte SVG -->
  <div class="vbr-map-wrap">
    <svg class="vbr-map" id="vbr-map" viewBox="0 0 400 300" xmlns="http://www.w3.org/2000/svg">
      <!-- Fond -->
      <rect width="400" height="300" fill="#e8f0f8"/>
      <!-- Grille légère -->
      <g stroke="#c8d8e8" stroke-width="0.5">
        <line x1="100" y1="0" x2="100" y2="300"/>
        <line x1="200" y1="0" x2="200" y2="300"/>
        <line x1="300" y1="0" x2="300" y2="300"/>
        <line x1="0" y1="100" x2="400" y2="100"/>
        <line x1="0" y1="200" x2="400" y2="200"/>
      </g>
      <!-- EBBR marker -->
      <g id="vbr-ebbr">
        <!-- Piste 07/25 (cap ~280°) -->
        <line x1="168" y1="122" x2="220" y2="116" stroke="#0e3d6b" stroke-width="3" stroke-linecap="round"/>
        <!-- Piste 01/19 (cap ~012°) -->
        <line x1="193" y1="108" x2="195" y2="132" stroke="#0e3d6b" stroke-width="3" stroke-linecap="round"/>
        <circle cx="194" cy="120" r="5" fill="none" stroke="#F5A623" stroke-width="1.5"/>
        <text x="204" y="115" font-size="8" fill="#0e3d6b" font-weight="700" font-family="Arial,sans-serif">EBBR</text>
      </g>
      <!-- Avions injectés dynamiquement -->
      <g id="vbr-planes"></g>
    </svg>
    <div class="vbr-map-labels">
      <span>50.5°N 3.8°E</span>
      <span>51.3°N 5.2°E</span>
    </div>
  </div>

  <!-- Liste des vols -->
  <div class="vbr-loading" id="vbr-loading">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="vbr-spin"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
    Chargement des données...
  </div>
  <div class="vbr-error" id="vbr-error" style="display:none"></div>
  <div id="vbr-list" style="display:none">
    <div class="vbr-list-header">
      <span>VOL</span><span>ALTITUDE</span><span>VITESSE</span><span>CAP</span><span>PAYS</span>
    </div>
    <div id="vbr-list-body"></div>
  </div>
  <div class="vbr-footer">
    Source : <a href="https://opensky-network.org" target="_blank">OpenSky Network</a> · ADS-B temps réel ·
    <span id="vbr-last-update">—</span>
  </div>
</div>

<style>
.vbr { font-family:"Helvetica Neue",Arial,sans-serif; background:#fff; border-radius:10px; border:1.5px solid #dde6f0; overflow:hidden; font-size:13px; }
.vbr-header { background:#0e3d6b; color:#fff; padding:10px 14px; display:flex; justify-content:space-between; align-items:center; }
.vbr-title { display:flex; align-items:center; gap:7px; font-weight:700; font-size:.85rem; }
.vbr-count { background:rgba(255,255,255,.2); border-radius:10px; padding:2px 8px; font-size:.72rem; font-weight:700; }
.vbr-refresh { background:none; border:1px solid rgba(255,255,255,.4); color:#fff; border-radius:5px; padding:2px 8px; cursor:pointer; font-size:.85rem; }
.vbr-refresh:hover { background:rgba(255,255,255,.15); }
.vbr-map-wrap { position:relative; padding:8px; background:#f5f8fc; border-bottom:1px solid #e0e8f0; }
.vbr-map { width:100%; display:block; border-radius:6px; }
.vbr-map-labels { display:flex; justify-content:space-between; font-size:.6rem; color:#999; padding:0 4px; margin-top:2px; }
.vbr-loading { display:flex; align-items:center; gap:8px; padding:16px; color:#888; font-size:.8rem; }
.vbr-error { background:#fff0f0; border-top:1px solid #fca5a5; padding:10px 14px; color:#c0392b; font-size:.8rem; }
.vbr-list-header { display:grid; grid-template-columns:2fr 1.5fr 1.5fr 1fr 1.5fr; padding:6px 12px; font-size:.65rem; font-weight:700; color:#888; text-transform:uppercase; border-bottom:1px solid #e8f0f8; background:#f8fafb; }
.vbr-row { display:grid; grid-template-columns:2fr 1.5fr 1.5fr 1fr 1.5fr; padding:6px 12px; border-bottom:1px solid #f0f4f8; cursor:pointer; transition:background .15s; align-items:center; }
.vbr-row:hover { background:#f0f7ff; }
.vbr-callsign { font-weight:700; font-size:.82rem; color:#0e3d6b; }
.vbr-alt { font-size:.75rem; color:#555; }
.vbr-spd { font-size:.75rem; color:#555; }
.vbr-cap { font-size:.75rem; color:#555; }
.vbr-pays { font-size:.7rem; color:#888; }
.vbr-footer { padding:6px 12px; font-size:.62rem; color:#aaa; border-top:1px solid #e8f0f8; }
.vbr-footer a { color:#1673B2; text-decoration:none; }
@keyframes vbr-rotate { to { transform: rotate(360deg); } }
.vbr-spin { animation: vbr-rotate 1s linear infinite; transform-origin: center; }
/* Avion SVG */
.vbr-plane { cursor:pointer; transition:opacity .2s; }
.vbr-plane:hover { opacity:.7; }
</style>

<script>
(function(){
  // Coordonnées bounding box
  var LAT_MIN=50.5, LAT_MAX=51.3, LON_MIN=3.8, LON_MAX=5.2;
  var SVG_W=400, SVG_H=300;

  function latToY(lat){ return SVG_H - ((lat-LAT_MIN)/(LAT_MAX-LAT_MIN))*SVG_H; }
  function lonToX(lon){ return ((lon-LON_MIN)/(LON_MAX-LON_MIN))*SVG_W; }

  function mToFt(m){ return m ? Math.round(m*3.28084/100)*100 : null; }
  function msToKt(ms){ return ms ? Math.round(ms*1.94384) : null; }

  function capToArrow(deg){
    if(deg===null||deg===undefined) return '—';
    var dirs=['N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSO','SO','OSO','O','ONO','NO','NNO'];
    return dirs[Math.round(deg/22.5)%16];
  }

  function planeSVG(x, y, cap, callsign, selected){
    cap = cap || 0;
    var col = selected ? '#F5A623' : '#1673B2';
    var tip = callsign ? callsign.trim() : '?';
    return '<g class="vbr-plane" onclick="vbrSelect(\''+tip+'\')" transform="translate('+x+','+y+') rotate('+cap+')">'
      +'<title>'+tip+'</title>'
      +'<polygon points="0,-8 4,6 0,3 -4,6" fill="'+col+'" stroke="#fff" stroke-width="0.8"/>'
      +'</g>';
  }

  window.vbrLoad = function(){
    var loading = document.getElementById('vbr-loading');
    var err     = document.getElementById('vbr-error');
    var list    = document.getElementById('vbr-list');
    var btn     = document.getElementById('vbr-refresh-btn');
    if(loading) loading.style.display='flex';
    if(err)     err.style.display='none';
    if(list)    list.style.display='none';
    if(btn)     btn.style.opacity='.5';

    var url='/api/flights.php';

    fetch(url)
      .then(function(r){ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
      .then(function(d){
        if(loading) loading.style.display='none';
        if(btn) btn.style.opacity='1';
        var states = d.states || [];
        // Filtrer: en vol seulement, avec position
        states = states.filter(function(s){ return s[8]===false && s[5]!==null && s[6]!==null; });
        // Trier par altitude décroissante
        states.sort(function(a,b){ return (b[7]||0)-(a[7]||0); });

        document.getElementById('vbr-count').textContent = states.length + ' vols';
        document.getElementById('vbr-last-update').textContent =
          'MàJ ' + new Date().toLocaleTimeString('fr-BE',{hour:'2-digit',minute:'2-digit'});

        // Carte SVG
        var planesG = document.getElementById('vbr-planes');
        if(planesG){
          var html='';
          states.forEach(function(s){
            var x=lonToX(s[5]), y=latToY(s[6]);
            if(x>=0&&x<=SVG_W&&y>=0&&y<=SVG_H){
              html+=planeSVG(x,y,s[10],s[1],false);
            }
          });
          planesG.innerHTML=html;
        }

        // Liste
        var body = document.getElementById('vbr-list-body');
        if(body){
          body.innerHTML = states.slice(0,30).map(function(s){
            var cs=(s[1]||'???').trim();
            var alt=mToFt(s[7]);
            var spd=msToKt(s[9]);
            return '<div class="vbr-row" onclick="vbrSelect(\''+cs+'\')">'
              +'<span class="vbr-callsign">✈ '+cs+'</span>'
              +'<span class="vbr-alt">'+(alt?alt.toLocaleString()+' ft':'—')+'</span>'
              +'<span class="vbr-spd">'+(spd?spd+' kt':'—')+'</span>'
              +'<span class="vbr-cap">'+capToArrow(s[10])+'</span>'
              +'<span class="vbr-pays">'+( s[2]||'—')+'</span>'
              +'</div>';
          }).join('');
        }
        if(list) list.style.display='block';
      })
      .catch(function(e){
        if(loading) loading.style.display='none';
        if(btn) btn.style.opacity='1';
        if(err){ err.textContent='Erreur : '+e.message; err.style.display='block'; }
      });
  };

  window.vbrSelect = function(cs){
    // Highlight l'avion sélectionné sur la carte
    document.querySelectorAll('.vbr-plane polygon').forEach(function(p){
      p.setAttribute('fill', p.closest('.vbr-plane').querySelector('title').textContent===cs ? '#F5A623' : '#1673B2');
    });
    document.querySelectorAll('.vbr-row').forEach(function(r){
      r.style.background = r.querySelector('.vbr-callsign').textContent.includes(cs) ? '#fff8ee' : '';
    });
  };

  // Chargement initial
  document.addEventListener('DOMContentLoaded', function(){ vbrLoad(); });
  if(document.readyState!=='loading') vbrLoad();

  // Rafraîchissement automatique toutes les 60s
  setInterval(function(){ vbrLoad(); }, 60000);
})();
</script>
