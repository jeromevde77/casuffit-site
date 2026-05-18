<?php /* Widget vols en temps réel — Leaflet + OpenSky + Planespotters */ ?>

<div class="vbr" id="vbr">

  <!-- Header -->
  <div class="vbr-header">
    <div class="vbr-title">✈ Vols en cours — Zone Bruxelles</div>
    <div style="display:flex;gap:6px;align-items:center">
      <span class="vbr-badge" id="vbr-count">—</span>
      <select id="vbr-airport" onchange="vbrSetAirport(this.value)" class="vbr-airport-sel">
        <option value="EBBR">✈ Bruxelles</option>
        <option value="EBCI">✈ Charleroi</option>
        <option value="EBLG">✈ Liège</option>
        <option value="EBOS">✈ Oostende</option>
        <option value="EBAW">✈ Anvers</option>
      </select>
      <button class="vbr-hbtn" onclick="vbrCenter()" title="Centrer sur l'aéroport">⌂</button>
      <button class="vbr-hbtn" onclick="vbrLoad()" title="Actualiser">↺</button>
    </div>
  </div>

  <!-- Panneau info avion sélectionné -->
  <div id="vbr-info" style="display:none" class="vbr-info">
    <button class="vbr-info-close" onclick="vbrDeselect()">✕</button>
    <div class="vbr-info-left">
      <div class="vbr-info-photo-wrap">
        <img id="vbr-photo" src="" alt="" class="vbr-photo"/>
        <div id="vbr-photo-credit" class="vbr-photo-credit"></div>
      </div>
    </div>
    <div class="vbr-info-right">
      <div class="vbr-info-cs" id="vi-cs">—</div>
      <div class="vbr-info-route" id="vi-route"></div>
      <div class="vbr-info-airline" id="vi-airline"></div>
      <div class="vbr-info-grid">
        <div class="vbr-stat"><div class="vbr-stat-lbl">ALTITUDE</div><div class="vbr-stat-val" id="vi-alt">—</div></div>
        <div class="vbr-stat"><div class="vbr-stat-lbl">VITESSE</div><div class="vbr-stat-val" id="vi-spd">—</div></div>
        <div class="vbr-stat"><div class="vbr-stat-lbl">CAP</div><div class="vbr-stat-val" id="vi-hdg">—</div></div>
        <div class="vbr-stat"><div class="vbr-stat-lbl">V/S</div><div class="vbr-stat-val" id="vi-vs">—</div></div>
        <div class="vbr-stat"><div class="vbr-stat-lbl">SQUAWK</div><div class="vbr-stat-val" id="vi-sq">—</div></div>
        <div class="vbr-stat"><div class="vbr-stat-lbl">ICAO24</div><div class="vbr-stat-val" id="vi-icao">—</div></div>
      </div>
    </div>
  </div>

  <!-- Légende -->
  <div class="vbr-legend">
    <span style="color:#e74c3c">▲</span>0-2k
    <span style="color:#e67e22">▲</span>2-5k
    <span style="color:#f1c40f">▲</span>5-10k
    <span style="color:#2ecc71">▲</span>10-20k
    <span style="color:#3498db">▲</span>20-35k
    <span style="color:#9b59b6">▲</span>35k+ ft
    <span style="margin-left:auto;font-size:.62rem" id="vbr-update"></span>
  </div>

  <!-- Carte -->
  <div id="vbr-mapbox"></div>

  <div id="vbr-loading" class="vbr-loading"><span class="vbr-spin">⟳</span> Chargement…</div>
  <div id="vbr-error" class="vbr-error" style="display:none"></div>

  <!-- Liste avions -->
  <div id="vbr-list-wrap" style="display:none">
    <div class="vbr-list-head">
      <span>VOL</span><span>ALT (ft)</span><span>VIT (kt)</span><span>CAP</span><span>PAYS</span>
    </div>
    <div id="vbr-list-body"></div>
  </div>

  <div class="vbr-footer">
    <a href="https://opensky-network.org" target="_blank">OpenSky Network</a> ·
    <a href="https://www.openstreetmap.org" target="_blank">OpenStreetMap</a> ·
    Photos <a href="https://www.planespotters.net" target="_blank">Planespotters.net</a>
  </div>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css"/>
<style>
.vbr{font-family:"Helvetica Neue",Arial,sans-serif;background:#fff;border-radius:10px;border:1.5px solid #dde6f0;overflow:hidden;font-size:13px}
.vbr-header{background:#0e3d6b;color:#fff;padding:10px 14px;display:flex;justify-content:space-between;align-items:center}
.vbr-title{font-weight:700;font-size:.88rem}
.vbr-badge{background:rgba(255,255,255,.2);border-radius:10px;padding:2px 8px;font-size:.72rem;font-weight:700}
.vbr-hbtn{background:none;border:1px solid rgba(255,255,255,.4);color:#fff;border-radius:5px;padding:2px 8px;cursor:pointer;font-size:1rem;line-height:1}
.vbr-hbtn:hover{background:rgba(255,255,255,.15)}
.vbr-airport-sel{background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.4);color:#fff;border-radius:5px;padding:2px 6px;font-size:.75rem;cursor:pointer;outline:none}
.vbr-airport-sel option{background:#0e3d6b;color:#fff}
/* Info panel */
.vbr-info{display:flex;gap:12px;padding:12px 14px;background:#f0f7ff;border-bottom:2px solid #1673B2;position:relative;align-items:flex-start}
.vbr-info-close{position:absolute;top:8px;right:10px;background:none;border:none;font-size:.85rem;cursor:pointer;color:#888;font-weight:700}
.vbr-info-left{flex-shrink:0}
.vbr-info-photo-wrap{position:relative;width:130px}
.vbr-photo{width:130px;height:82px;object-fit:cover;border-radius:6px;background:#e0e8f0;display:block}
.vbr-photo-credit{font-size:.5rem;color:#aaa;text-align:right;margin-top:2px}
.vbr-info-right{flex:1;min-width:0}
.vbr-info-cs{font-size:1.4rem;font-weight:800;color:#0e3d6b;line-height:1.1;margin-bottom:2px}
.vbr-info-route{font-size:.88rem;color:#0e3d6b;font-weight:800;margin-bottom:2px;letter-spacing:.5px}
.vbr-info-airline{font-size:.68rem;color:#888;margin-bottom:7px}
.vbr-info-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:5px}
.vbr-stat{background:#fff;border-radius:5px;padding:4px 7px;border:1px solid #dde6f0}
.vbr-stat-lbl{font-size:.52rem;color:#aaa;font-weight:700;text-transform:uppercase}
.vbr-stat-val{font-size:.8rem;font-weight:700;color:#0e3d6b}
/* Légende */
.vbr-legend{padding:5px 12px;font-size:.68rem;color:#666;background:#f8fafb;border-bottom:1px solid #e8f0f8;display:flex;gap:7px;align-items:center}
/* Carte */
#vbr-mapbox{height:400px}
.vbr-loading{padding:10px 14px;color:#888;font-size:.8rem;display:flex;align-items:center;gap:6px}
.vbr-error{background:#fff0f0;padding:10px 14px;color:#c0392b;font-size:.8rem}
/* Liste */
.vbr-list-head{display:grid;grid-template-columns:2fr 1.5fr 1.5fr .8fr 1.5fr;padding:5px 12px;font-size:.62rem;font-weight:700;color:#888;text-transform:uppercase;background:#f8fafb;border-top:1px solid #e0e8f0}
.vbr-list-row{display:grid;grid-template-columns:2fr 1.5fr 1.5fr .8fr 1.5fr;padding:5px 12px;border-bottom:1px solid #f0f4f8;cursor:pointer;transition:background .12s;align-items:center}
.vbr-list-row:hover,.vbr-list-row.sel{background:#e8f4ff}
.vbr-list-cs{font-weight:700;font-size:.78rem;color:#0e3d6b}
.vbr-list-val{font-size:.72rem;color:#555}
.vbr-list-cty{font-size:.65rem;color:#999}
/* Footer */
.vbr-footer{padding:5px 12px;font-size:.6rem;color:#bbb;border-top:1px solid #e8f0f8}
.vbr-footer a{color:#1673B2;text-decoration:none}
@keyframes vbr-r{to{transform:rotate(360deg)}}
.vbr-spin{display:inline-block;animation:vbr-r .8s linear infinite}
@media(max-width:500px){
  #vbr-mapbox{height:280px}
  .vbr-info{flex-direction:column}
  .vbr-info-photo-wrap{width:100%}
  .vbr-photo{width:100%;height:120px}
  .vbr-info-grid{grid-template-columns:repeat(2,1fr)}
}
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
(function(){
  var AIRPORTS = {
    EBBR:{name:'Bruxelles',icao:'EBBR',lat:50.9014,lng:4.4844,bb:[50.1,3.5,51.7,5.5]},
    EBCI:{name:'Charleroi',icao:'EBCI',lat:50.4564,lng:4.4538,bb:[49.7,3.6,51.2,5.3]},
    EBLG:{name:'Liège',   icao:'EBLG',lat:50.6374,lng:5.4432,bb:[49.9,4.7,51.4,6.2]},
    EBOS:{name:'Oostende',icao:'EBOS',lat:51.1988,lng:2.8622,bb:[50.5,1.9,51.9,3.9]},
    EBAW:{name:'Anvers',  icao:'EBAW',lat:51.1894,lng:4.4603,bb:[50.5,3.6,51.9,5.3]}
  };
  var currentAirport = 'EBBR';
  var HOME = {lat:50.9014,lng:4.4844,zoom:9};
  var map=null, markers={}, trackLine=null, selectedIcao=null, allStates=[];
  var DIRS=['N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSO','SO','OSO','O','ONO','NO','NNO'];

  var ALT_COLS=[[0,'#e74c3c'],[600,'#e67e22'],[1500,'#f1c40f'],[3000,'#2ecc71'],[6000,'#3498db'],[10500,'#9b59b6']];
  function altColor(m){
    if(!m&&m!==0)return'#aaa';
    var ft=m*3.28084, c=ALT_COLS[0][1];
    for(var i=0;i<ALT_COLS.length;i++){if(ft>=ALT_COLS[i][0])c=ALT_COLS[i][1];}
    return c;
  }

  // Icône avion SVG vue du dessus
  function planeIcon(cap, color, sel){
    cap=cap||0; var s=sel?32:24; var h=s/2;
    var svg='<svg xmlns="http://www.w3.org/2000/svg" width="'+s+'" height="'+s+'" viewBox="-16 -16 32 32">'
      +'<g transform="rotate('+(cap)+')">'
      +'<path d="M0,-14 L3,-4 L12,2 L10,5 L3,2 L2,8 L5,10 L5,13 L0,11 L-5,13 L-5,10 L-2,8 L-3,2 L-10,5 L-12,2 L-3,-4 Z" '
      +'fill="'+color+'" stroke="white" stroke-width="1.5" stroke-linejoin="round"/>'
      +'</g></svg>';
    return L.divIcon({html:svg,className:'',iconSize:[s,s],iconAnchor:[h,h]});
  }

  function mToFt(m){return m?Math.round(m*3.28084/100)*100:null;}
  function msToKt(ms){return ms?Math.round(ms*1.94384):null;}

  function initMap(){
    if(map)return;
    map=L.map('vbr-mapbox',{zoomControl:true}).setView([HOME.lat,HOME.lng],HOME.zoom);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{
      attribution:'© <a href="https://www.openstreetmap.org">OSM</a>',maxZoom:18
    }).addTo(map);
    window._apMarker = L.marker([HOME.lat,HOME.lng],{icon:L.divIcon({
      html:'<div id="vbr-ap-label" style="background:#0e3d6b;color:#fff;font-size:9px;font-weight:700;padding:2px 5px;border-radius:3px;border:1.5px solid #F5A623;white-space:nowrap">EBBR</div>',
      className:'',iconAnchor:[22,10]
    })}).addTo(map);
    window.vbrCenter=function(){if(map)map.setView([HOME.lat,HOME.lng],HOME.zoom);};
    window.vbrSetAirport=function(icao){
      var a=AIRPORTS[icao]; if(!a) return;
      currentAirport=icao;
      HOME={lat:a.lat,lng:a.lng,zoom:9};
      if(window._apMarker){
        window._apMarker.setLatLng([a.lat,a.lng]);
        var lbl=document.getElementById('vbr-ap-label');
        if(lbl) lbl.textContent=icao;
      }
      vbrCenter();
      vbrLoad();
    };
    window.vbrInvalidate=function(){
      if(map){map.invalidateSize();setTimeout(function(){map.invalidateSize();map.setView([HOME.lat,HOME.lng],HOME.zoom);},200);}
    };
  }

  function fetchRoute(callsign){
    var elRoute  = document.getElementById('vi-route');
    var elAirline = document.getElementById('vi-airline');
    elRoute.textContent = ''; elAirline.textContent = '';
    if(!callsign || callsign==='???') return;
    fetch('https://api.adsbdb.com/v0/callsign/' + encodeURIComponent(callsign.trim()))
      .then(function(r){ return r.ok ? r.json() : null; })
      .then(function(d){
        var fr = d && d.response && d.response.flightroute;
        if(!fr) return;
        var o = fr.origin, dst = fr.destination;
        if(o && dst){
          elRoute.textContent = o.iata_code + '  ✈  ' + dst.iata_code;
          elRoute.title = o.name + ' → ' + dst.name;
          var parts = [];
          if(o.municipality) parts.push(o.municipality);
          parts.push('→');
          if(dst.municipality) parts.push(dst.municipality);
          elAirline.textContent = (fr.airline ? fr.airline.name + ' · ' : '') + parts.join(' ');
        } else if(o){
          elRoute.textContent = o.iata_code + '  ✈  ?';
          elAirline.textContent = o.name;
        }
      }).catch(function(){});
  }

  function fetchPhoto(icao24){
    var img=document.getElementById('vbr-photo');
    var cred=document.getElementById('vbr-photo-credit');
    img.src=''; img.style.opacity='.4'; cred.textContent='';
    fetch('https://api.planespotters.net/pub/photos/hex/'+icao24)
      .then(function(r){return r.ok?r.json():null;})
      .then(function(d){
        if(!d||!d.photos||!d.photos.length){img.style.opacity='.2';return;}
        var p=d.photos[0];
        img.src=p.thumbnail_large?p.thumbnail_large.src:(p.thumbnail?p.thumbnail.src:'');
        img.style.opacity='1';
        img.onerror=function(){img.style.opacity='.2';};
        cred.textContent='© '+(p.photographer||'');
      }).catch(function(){img.style.opacity='.2';});
  }

  function fetchRoute(callsign){
    var el = document.getElementById('vi-route');
    el.textContent = '';
    var cs = callsign.replace(/\s/g,'').toUpperCase();
    if(!cs || cs==='???') return;
    fetch('https://api.adsbdb.com/v0/callsign/'+encodeURIComponent(cs))
      .then(function(r){ return r.ok?r.json():null; })
      .then(function(d){
        if(!d||!d.response||!d.response.flightroute) return;
        var fr = d.response.flightroute;
        var orig = fr.origin ? (fr.origin.iata_code||fr.origin.icao_code) : null;
        var dest = fr.destination ? (fr.destination.iata_code||fr.destination.icao_code) : null;
        var airline = fr.airline ? fr.airline.name : null;
        var parts = [];
        if(orig && dest) parts.push(orig+' ✈ '+dest);
        if(airline) parts.push('('+airline+')');
        if(parts.length) {
          el.textContent = parts.join(' ');
          el.title = (fr.origin?fr.origin.name+' ('+fr.origin.municipality+')':'') +
                     (orig&&dest?' → ':'') +
                     (fr.destination?fr.destination.name+' ('+fr.destination.municipality+')':'');
        }
      })
      .catch(function(){});
  }

  function fetchTrack(icao24){
    if(trackLine){map.removeLayer(trackLine);trackLine=null;}
    fetch('/api/track.php?icao24='+encodeURIComponent(icao24))
      .then(function(r){return r.ok?r.json():null;})
      .then(function(d){
        if(!d||!d.path||d.path.length<2)return;
        var pts=d.path.filter(function(p){return p[1]&&p[2];}).map(function(p){return[p[1],p[2]];});
        if(pts.length<2)return;
        trackLine=L.polyline(pts,{color:'#F5A623',weight:2.5,opacity:.9}).addTo(map);
        L.circleMarker(pts[0],{radius:4,color:'#F5A623',fillColor:'#fff',fillOpacity:1,weight:2}).addTo(map);
      }).catch(function(){});
  }

  function selectPlane(s){
    selectedIcao=(s[0]||'').toLowerCase();
    var cs=(s[1]||'???').trim();
    document.getElementById('vi-cs').textContent=cs;
    document.getElementById('vi-route').textContent='';
    fetchRoute(cs);
    var alt=mToFt(s[7]), spd=msToKt(s[9]), vs=s[11]?Math.round(s[11]*196.85):null;
    document.getElementById('vi-alt').textContent=alt?alt.toLocaleString()+' ft':'—';
    document.getElementById('vi-spd').textContent=spd?spd+' kt':'—';
    document.getElementById('vi-hdg').textContent=s[10]?Math.round(s[10])+'°':'—';
    document.getElementById('vi-vs').textContent=vs?(vs>0?'+':'')+vs+' ft/min':'—';
    document.getElementById('vi-sq').textContent=s[14]||'—';
    document.getElementById('vi-icao').textContent=(s[0]||'').toUpperCase();
    document.getElementById('vbr-info').style.display='flex';
    fetchPhoto(selectedIcao);
    fetchRoute(cs);
    fetchTrack(selectedIcao);
    // Highlight
    refreshMarkers();
    // Surligner ligne liste
    document.querySelectorAll('.vbr-list-row').forEach(function(r){
      r.classList.toggle('sel',r.dataset.icao===selectedIcao);
    });
    map.panTo([s[6],s[5]]);
  }

  window.vbrDeselect=function(){
    selectedIcao=null;
    if(trackLine){map.removeLayer(trackLine);trackLine=null;}
    document.getElementById('vbr-info').style.display='none';
    refreshMarkers();
    document.querySelectorAll('.vbr-list-row').forEach(function(r){r.classList.remove('sel');});
  };

  function refreshMarkers(){
    Object.keys(markers).forEach(function(icao){
      var m=markers[icao];
      if(m._s) m.setIcon(planeIcon(m._s[10],altColor(m._s[7]),icao===selectedIcao));
    });
  }

  function renderList(states){
    var wrap=document.getElementById('vbr-list-wrap');
    var body=document.getElementById('vbr-list-body');
    if(!states.length){wrap.style.display='none';return;}
    wrap.style.display='block';
    body.innerHTML=states.slice(0,50).map(function(s){
      var icao=(s[0]||'').toLowerCase();
      var cs=(s[1]||'???').trim();
      var alt=mToFt(s[7]), spd=msToKt(s[9]);
      var dir=s[10]!=null?DIRS[Math.round(s[10]/22.5)%16]:'—';
      return '<div class="vbr-list-row'+(icao===selectedIcao?' sel':'')+'" data-icao="'+icao+'" onclick="vbrSelectByIcao(\''+icao+'\')">'
        +'<span class="vbr-list-cs">✈ '+cs+'</span>'
        +'<span class="vbr-list-val">'+(alt?alt.toLocaleString():'—')+'</span>'
        +'<span class="vbr-list-val">'+(spd?spd:'—')+'</span>'
        +'<span class="vbr-list-val">'+dir+'</span>'
        +'<span class="vbr-list-cty">'+(s[2]||'—')+'</span>'
        +'</div>';
    }).join('');
  }

  window.vbrSelectByIcao=function(icao){
    var s=allStates.find(function(x){return(x[0]||'').toLowerCase()===icao;});
    if(s) selectPlane(s);
  };

  window.vbrLoad=function(){
    var elL=document.getElementById('vbr-loading');
    var elE=document.getElementById('vbr-error');
    if(elL)elL.style.display='flex';
    if(elE)elE.style.display='none';
    initMap();
    setTimeout(function(){if(map)map.invalidateSize();},100);

    var bb=AIRPORTS[currentAirport].bb;
    var apiToken = window._API_TOKEN || '';
    fetch('/api/flights.php?lamin='+bb[0]+'&lomin='+bb[1]+'&lamax='+bb[2]+'&lomax='+bb[3], {
      headers: {'X-Api-Token': apiToken}
    })
      .then(function(r){if(!r.ok)throw new Error('HTTP '+r.status);return r.json();})
      .then(function(d){
        if(elL)elL.style.display='none';
        allStates=(d.states||[]).filter(function(s){return s[8]===false&&s[5]&&s[6];});
        allStates.sort(function(a,b){return(b[7]||0)-(a[7]||0);});
        document.getElementById('vbr-count').textContent=allStates.length+' vols';
        document.getElementById('vbr-update').textContent='MàJ '+new Date().toLocaleTimeString('fr-BE',{hour:'2-digit',minute:'2-digit'});

        // Marqueurs
        var seen={};
        allStates.forEach(function(s){seen[(s[0]||'').toLowerCase()]=true;});
        Object.keys(markers).forEach(function(k){if(!seen[k]){map.removeLayer(markers[k]);delete markers[k];}});

        allStates.forEach(function(s){
          var icao=(s[0]||'').toLowerCase();
          var icon=planeIcon(s[10],altColor(s[7]),icao===selectedIcao);
          if(markers[icao]){
            markers[icao].setLatLng([s[6],s[5]]).setIcon(icon);
            markers[icao]._s=s;
          } else {
            var m=L.marker([s[6],s[5]],{icon:icon});
            m._s=s;
            m.on('click',function(){selectPlane(s);});
            m.bindTooltip((s[1]||'?').trim(),{permanent:false,direction:'top',offset:[0,-10],className:'vbr-tt'});
            m.addTo(map);
            markers[icao]=m;
          }
          markers[icao]._s=s;
        });

        renderList(allStates);
        if(selectedIcao){
          var sel=allStates.find(function(s){return(s[0]||'').toLowerCase()===selectedIcao;});
          if(sel)selectPlane(sel);
        }
      })
      .catch(function(e){
        if(elL)elL.style.display='none';
        if(elE){elE.textContent='Erreur : '+e.message;elE.style.display='block';}
      });
  };

  function startWidget(){
    var mapEl = document.getElementById('vbr-mapbox');
    if(!mapEl) return;
    var initialized = false;

    function tryInit(){
      // Le container est visible seulement si son parent n'est pas display:none
      var el = mapEl;
      while(el){ if(getComputedStyle(el).display==='none') return false; el=el.parentElement; }
      return mapEl.offsetWidth > 50;
    }

    function doInit(){
      if(initialized) return;
      initialized = true;
      vbrLoad();
      // Forcer Leaflet à recalculer la taille après rendu complet
      [100,300,600,1000].forEach(function(t){
        setTimeout(function(){
          if(map){ map.invalidateSize(); map.setView([HOME.lat,HOME.lng],HOME.zoom); }
        }, t);
      });
    }

    // ResizeObserver détecte quand le container devient réellement visible
    var ro = new ResizeObserver(function(){
      if(tryInit()) doInit();
    });
    ro.observe(mapEl);
    // Si déjà visible au chargement
    if(tryInit()) doInit();
  }

  if(document.readyState!=='loading'){ startWidget(); }
  else{ document.addEventListener('DOMContentLoaded', startWidget); }
  setInterval(function(){ if(map) vbrLoad(); }, 60000);
})();
</script>
