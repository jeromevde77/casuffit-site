<?php /* Widget vols en temps réel — Leaflet + OpenSky */ ?>

<div class="vbr" id="vbr">
  <div class="vbr-header">
    <div class="vbr-title">✈ Vols en cours — Zone Bruxelles</div>
    <div style="display:flex;gap:8px;align-items:center">
      <span class="vbr-badge" id="vbr-count">—</span>
      <button class="vbr-btn-refresh" onclick="vbrLoad()" title="Actualiser">↺</button>
    </div>
  </div>

  <!-- Panneau détails vol -->
  <div id="vbr-panel" style="display:none">
    <div class="vbr-panel-close" onclick="document.getElementById('vbr-panel').style.display='none'">✕</div>
    <div class="vbr-panel-cs" id="vp-cs">—</div>
    <div class="vbr-panel-grid">
      <div class="vbr-panel-item"><div class="vbr-panel-lbl">ALTITUDE</div><div class="vbr-panel-val" id="vp-alt">—</div></div>
      <div class="vbr-panel-item"><div class="vbr-panel-lbl">VITESSE</div><div class="vbr-panel-val" id="vp-spd">—</div></div>
      <div class="vbr-panel-item"><div class="vbr-panel-lbl">CAP</div><div class="vbr-panel-val" id="vp-hdg">—</div></div>
      <div class="vbr-panel-item"><div class="vbr-panel-lbl">V/S</div><div class="vbr-panel-val" id="vp-vs">—</div></div>
      <div class="vbr-panel-item"><div class="vbr-panel-lbl">SQUAWK</div><div class="vbr-panel-val" id="vp-sq">—</div></div>
      <div class="vbr-panel-item"><div class="vbr-panel-lbl">PAYS</div><div class="vbr-panel-val" id="vp-country">—</div></div>
      <div class="vbr-panel-item"><div class="vbr-panel-lbl">ICAO24</div><div class="vbr-panel-val" id="vp-icao">—</div></div>
      <div class="vbr-panel-item"><div class="vbr-panel-lbl">SOURCE</div><div class="vbr-panel-val" id="vp-src">—</div></div>
    </div>
  </div>

  <!-- Légende altitude -->
  <div class="vbr-legend">
    <span style="color:#e74c3c">■</span> 0-2k
    <span style="color:#e67e22">■</span> 2-5k
    <span style="color:#f1c40f">■</span> 5-10k
    <span style="color:#2ecc71">■</span> 10-20k
    <span style="color:#3498db">■</span> 20-35k
    <span style="color:#9b59b6">■</span> 35k+ ft
    <span style="float:right;font-size:.65rem" id="vbr-update">—</span>
  </div>

  <!-- Carte Leaflet -->
  <div id="vbr-mapbox"></div>

  <div class="vbr-loading" id="vbr-loading">
    <span class="vbr-spin">⟳</span> Chargement des données...
  </div>
  <div class="vbr-error" id="vbr-error" style="display:none"></div>

  <div class="vbr-footer">
    Source : <a href="https://opensky-network.org" target="_blank">OpenSky Network</a> ADS-B · Carte <a href="https://www.openstreetmap.org" target="_blank">OpenStreetMap</a>
  </div>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css"/>
<style>
.vbr { font-family:"Helvetica Neue",Arial,sans-serif; background:#fff; border-radius:10px; border:1.5px solid #dde6f0; overflow:hidden; font-size:13px; }
.vbr-header { background:#0e3d6b; color:#fff; padding:10px 14px; display:flex; justify-content:space-between; align-items:center; }
.vbr-title { font-weight:700; font-size:.88rem; }
.vbr-badge { background:rgba(255,255,255,.2); border-radius:10px; padding:2px 8px; font-size:.72rem; font-weight:700; }
.vbr-btn-refresh { background:none; border:1px solid rgba(255,255,255,.4); color:#fff; border-radius:5px; padding:2px 8px; cursor:pointer; font-size:1rem; line-height:1; }
.vbr-legend { padding:5px 12px; font-size:.68rem; color:#666; background:#f8fafb; border-bottom:1px solid #e8f0f8; display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
#vbr-mapbox { height:420px; }
.vbr-loading { padding:12px 14px; color:#888; font-size:.8rem; display:flex; align-items:center; gap:6px; }
.vbr-error { background:#fff0f0; padding:10px 14px; color:#c0392b; font-size:.8rem; border-top:1px solid #fca5a5; }
.vbr-footer { padding:6px 12px; font-size:.6rem; color:#aaa; border-top:1px solid #e8f0f8; }
.vbr-footer a { color:#1673B2; text-decoration:none; }
@keyframes vbr-rotate { to { transform: rotate(360deg); } }
.vbr-spin { display:inline-block; animation: vbr-rotate .8s linear infinite; }
/* Panneau détails */
.vbr-panel { position:relative; background:#f0f7ff; border-bottom:1px solid #c8dff0; padding:10px 14px 10px; }
.vbr-panel-close { position:absolute; top:8px; right:12px; cursor:pointer; color:#888; font-size:.9rem; font-weight:700; }
.vbr-panel-close:hover { color:#333; }
.vbr-panel-cs { font-size:1.3rem; font-weight:800; color:#0e3d6b; margin-bottom:8px; }
.vbr-panel-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:6px; }
.vbr-panel-item { background:#fff; border-radius:6px; padding:5px 8px; border:1px solid #dde6f0; }
.vbr-panel-lbl { font-size:.55rem; color:#999; font-weight:700; text-transform:uppercase; }
.vbr-panel-val { font-size:.82rem; font-weight:700; color:#0e3d6b; margin-top:1px; }
@media(max-width:500px){
  .vbr-panel-grid { grid-template-columns:repeat(2,1fr); }
  #vbr-mapbox { height:300px; }
}
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
(function(){
  var map = null;
  var markers = {};
  var selectedCS = null;

  var ALT_COLORS = [
    [0,     '#e74c3c'],
    [600,   '#e67e22'],
    [1500,  '#f1c40f'],
    [3000,  '#2ecc71'],
    [6000,  '#3498db'],
    [10500, '#9b59b6'],
  ];

  function altColor(m) {
    if(m === null || m === undefined) return '#aaa';
    var ft = m * 3.28084;
    var col = ALT_COLORS[0][1];
    for(var i=0; i<ALT_COLORS.length; i++){
      if(ft >= ALT_COLORS[i][0]) col = ALT_COLORS[i][1];
    }
    return col;
  }

  function makePlaneIcon(cap, color, selected) {
    cap = cap || 0;
    var size = selected ? 28 : 22;
    var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'+size+'" height="'+size+'" viewBox="-12 -12 24 24">'
      + '<g transform="rotate('+(cap - 0)+')">'
      + '<polygon points="0,-10 5,8 0,5 -5,8" fill="'+color+'" stroke="#fff" stroke-width="1.5"/>'
      + '</g></svg>';
    return L.divIcon({
      html: svg,
      className: '',
      iconSize: [size, size],
      iconAnchor: [size/2, size/2]
    });
  }

  function mToFt(m){ return m ? Math.round(m * 3.28084 / 100) * 100 : null; }
  function msToKt(ms){ return ms ? Math.round(ms * 1.94384) : null; }
  function srcLabel(n){ return ['ADS-B','ASTERIX','MLAT','FLARM'][n]||'?'; }

  function initMap() {
    if(map) return;
    window.vbrInvalidate = function(){
      if(map){
        map.invalidateSize();
        setTimeout(function(){ map.invalidateSize(); map.setView([50.9014,4.4844],9); },200);
      }
    };
    map = L.map('vbr-mapbox', { zoomControl: true }).setView([50.9014, 4.4844], 9);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© <a href="https://www.openstreetmap.org">OSM</a>',
      maxZoom: 18
    }).addTo(map);
    // Marker EBBR
    L.marker([50.9014, 4.4844], {
      icon: L.divIcon({
        html: '<div style="background:#0e3d6b;color:#fff;font-size:9px;font-weight:700;padding:2px 4px;border-radius:3px;white-space:nowrap;border:1px solid #F5A623">EBBR</div>',
        className: '', iconAnchor: [20, 10]
      })
    }).addTo(map);
  }

  function showPanel(s) {
    selectedCS = (s[1]||'?').trim();
    document.getElementById('vp-cs').textContent = selectedCS;
    var alt = mToFt(s[7]);
    var spd = msToKt(s[9]);
    var vs  = s[11] ? Math.round(s[11]*196.85) : null;
    document.getElementById('vp-alt').textContent = alt ? alt.toLocaleString()+' ft' : '—';
    document.getElementById('vp-spd').textContent = spd ? spd+' kt' : '—';
    document.getElementById('vp-hdg').textContent = s[10] ? Math.round(s[10])+'°' : '—';
    document.getElementById('vp-vs').textContent  = vs  ? (vs>0?'+':'')+vs+' ft/min' : '—';
    document.getElementById('vp-sq').textContent  = s[14] || '—';
    document.getElementById('vp-country').textContent = s[2] || '—';
    document.getElementById('vp-icao').textContent = (s[0]||'').toUpperCase();
    document.getElementById('vp-src').textContent = srcLabel(s[16]);
    document.getElementById('vbr-panel').style.display = 'block';
    // Refresh marker colors
    Object.keys(markers).forEach(function(cs){
      var m = markers[cs];
      if(m._vbrData) {
        m.setIcon(makePlaneIcon(m._vbrData[10], altColor(m._vbrData[7]), cs === selectedCS));
      }
    });
    // Pan to aircraft
    if(s[6] && s[5]) map.panTo([s[6], s[5]]);
  }

  window.vbrLoad = function() {
    var elLoad = document.getElementById('vbr-loading');
    var elErr  = document.getElementById('vbr-error');
    var btn    = document.querySelector('.vbr-btn-refresh');
    if(elLoad) elLoad.style.display='flex';
    if(elErr)  elErr.style.display='none';
    if(btn)    btn.style.opacity='.5';
    initMap();

    setTimeout(function(){ if(map) map.invalidateSize(); }, 100);
    fetch('/api/flights.php')
      .then(function(r){ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
      .then(function(d){
        if(elLoad) elLoad.style.display='none';
        if(btn)    btn.style.opacity='1';
        var states = (d.states||[]).filter(function(s){ return s[8]===false && s[5]&&s[6]; });
        document.getElementById('vbr-count').textContent = states.length+' vols';
        document.getElementById('vbr-update').textContent =
          'MàJ '+new Date().toLocaleTimeString('fr-BE',{hour:'2-digit',minute:'2-digit'});

        // Supprimer anciens marqueurs disparus
        var seen = {};
        states.forEach(function(s){ seen[s[1]||s[0]] = true; });
        Object.keys(markers).forEach(function(k){
          if(!seen[k]){ map.removeLayer(markers[k]); delete markers[k]; }
        });

        // Ajouter/mettre à jour marqueurs
        states.forEach(function(s) {
          var cs = (s[1]||s[0]||'?').trim();
          var color = altColor(s[7]);
          var icon = makePlaneIcon(s[10], color, cs===selectedCS);
          if(markers[cs]) {
            markers[cs].setLatLng([s[6], s[5]]);
            markers[cs].setIcon(icon);
            markers[cs]._vbrData = s;
          } else {
            var m = L.marker([s[6], s[5]], {icon: icon});
            m._vbrData = s;
            m.on('click', function(){ showPanel(s); });
            m.bindTooltip(cs, {permanent:false, direction:'top', offset:[0,-8], className:'vbr-tt'});
            m.addTo(map);
            markers[cs] = m;
          }
        });

        // Rafraîchir panneau si sélectionné
        if(selectedCS && states.some(function(s){return (s[1]||'').trim()===selectedCS;})){
          var sel = states.find(function(s){return (s[1]||'').trim()===selectedCS;});
          if(sel) showPanel(sel);
        }
      })
      .catch(function(e){
        if(elLoad) elLoad.style.display='none';
        if(btn)    btn.style.opacity='1';
        if(elErr){ elErr.textContent='Erreur : '+e.message; elErr.style.display='block'; }
      });
  };

  // Init au chargement
  if(document.readyState!=='loading') { vbrLoad(); }
  else { document.addEventListener('DOMContentLoaded', vbrLoad); }
  setInterval(vbrLoad, 60000);
})();
</script>
