<?php // includes/widgets/historique_vent.php — v2 avec saisie par ligne + export Excel ?>
<div class="pmh" id="pmh">

  <div class="pmh-header">
    <div class="pmh-title">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      Analyse historique — Conditions de vent EBBR
    </div>
  </div>

  <div class="pmh-body">

    <p class="pmh-intro">
      Saisissez une période, analysez les données IRM, indiquez la piste réellement utilisée sur chaque ligne, puis exportez en Excel pour vos courriers de plainte.
    </p>

    <!-- Formulaire -->
    <div class="pmh-form">
      <div class="pmh-form-row">
        <label class="pmh-lbl">Date de début (UTC)</label>
        <div class="pmh-dt-wrap">
          <input type="date" id="pmh-start-date" class="pmh-input pmh-dt-part">
          <input type="time" id="pmh-start-hour" class="pmh-input pmh-dt-part" step="1800">
        </div>
      </div>
      <div class="pmh-form-row">
        <label class="pmh-lbl">Date de fin (UTC)</label>
        <div class="pmh-dt-wrap">
          <input type="date" id="pmh-end-date" class="pmh-input pmh-dt-part">
          <input type="time" id="pmh-end-hour" class="pmh-input pmh-dt-part" step="1800">
        </div>
      </div>
      <div class="pmh-form-btns">
        <button class="pmh-btn" onclick="pmhLoad()">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          Analyser
        </button>
        <a href="https://www.batc.be/fr/pistes-en-usage/usage-anterieur" target="_blank" rel="noopener" class="pmh-batc-link" id="pmh-batc-link">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
          Usage antérieur BATC ↗
        </a>
      </div>
    </div>

    <!-- Résultats -->
    <!-- Graphique BATC mouvements par piste -->
  <div id="pmh-batc-chart" style="display:none;margin-top:20px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;flex-wrap:wrap;gap:8px">
      <div style="font-weight:800;font-size:.9rem;color:#0e3d6b" id="pmh-batc-chart-title">📊 Mouvements par piste</div>
      <div style="display:flex;gap:6px;align-items:center">
        <select id="pmh-batc-aggregate" onchange="pmhLoadBatcChart()"
          style="padding:5px 8px;border:1.5px solid #dde6f0;border-radius:7px;font-size:.78rem;background:#f7fafd;color:#333">
          <option value="day">Par jour</option>
          <option value="week">Par semaine</option>
          <option value="month">Par mois</option>
        </select>
        <select id="pmh-batc-filter" onchange="pmhDrawBatcChart()"
          style="padding:5px 8px;border:1.5px solid #dde6f0;border-radius:7px;font-size:.78rem;background:#f7fafd;color:#333">
          <option value="all">Toutes pistes</option>
          <option value="25">Pistes 25</option>
          <option value="07">Pistes 07</option>
          <option value="01">Piste 01</option>
          <option value="19">Piste 19</option>
        </select>
      </div>
    </div>
    <div id="pmh-batc-chart-status" style="font-size:.78rem;color:#888;margin-bottom:6px"></div>
    <div style="overflow-x:auto">
      <canvas id="pmh-batc-canvas" style="width:100%;min-height:280px"></canvas>
    </div>
    <div id="pmh-batc-legend" style="display:flex;flex-wrap:wrap;gap:10px;margin-top:10px;font-size:.72rem"></div>
  </div>

  <div id="pmh-results" style="display:none">

      <div class="pmh-summary" id="pmh-summary"></div>

      <!-- Barre d'actions -->
      <div class="pmh-actions">
        <span class="pmh-actions-lbl">Saisie rapide toutes les lignes :</span>
        <div class="pmh-quick-btns">
          <button class="pmh-q-btn pmh-q-25" onclick="pmhSetAll('25R/25L')">25R/25L</button>
          <button class="pmh-q-btn pmh-q-25" onclick="pmhSetAll('19/25R')">19/25R</button>
          <button class="pmh-q-btn pmh-q-07" onclick="pmhSetAll('07L/07R')">07L/07R</button>
          <button class="pmh-q-btn pmh-q-01" onclick="pmhSetAll('01/07R')">01/07R</button>
          <button class="pmh-q-btn pmh-q-01" onclick="pmhSetAll('01/01')">01/01</button><button class="pmh-q-btn pmh-q-19" onclick="pmhSetAll('19/19')">19/19</button>
          <button class="pmh-q-btn pmh-q-clear" onclick="pmhSetAll(null)">Effacer tout</button>
        </div>
        <button class="pmh-export-btn" onclick="pmhExport()">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
          Exporter PDF
        </button>
      </div>

      <!-- Tableau -->
      <div class="pmh-table-wrap">
        <table class="pmh-table" id="pmh-table">
          <thead>
            <tr>
              <th>Heure UTC<br><small>Heure BE</small></th>
              <th>Vent moy / rafales</th>
              <th>Planning AIP<br><small>Commun aux deux</small></th>
              <th>AIP 2013 <small>(légal)</small></th>
              <th>AIP actuel <small>(contesté)</small></th>
              <th>Piste réelle<br><small>Cliquer pour saisir</small></th>
              <th>Conforme<br><small>AIP 2013</small></th>
              <th>Note</th>
            </tr>
          </thead>
          <tbody id="pmh-tbody"></tbody>
        </table>
      </div>

      <div id="pmh-cards"></div>
    <div class="pmh-source-note" id="pmh-source-note"></div>
    </div>

    <div id="pmh-loading" style="display:none" class="pmh-loading">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="pmh-spin"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
      Chargement des données IRM…
    </div>
    <div id="pmh-error" style="display:none" class="pmh-error"></div>

  </div>
</div>

<!-- Modale widget piste météo -->
<div class="pmh-wmodal-bg" id="pmh-wmodal-bg" onclick="if(event.target===this)pmhCloseWidget()">
  <div class="pmh-wmodal">
    <div class="pmh-wmodal-head">
      <h3 id="pmh-wmodal-title">🌬 Conditions de vent</h3>
      <button class="pmh-wmodal-close" onclick="pmhCloseWidget()">✕</button>
    </div>
    <div class="pmh-wmodal-body" id="pmh-wmodal-body">Chargement…</div>
  </div>
</div>

<style>
.pmh{font-family:"Helvetica Neue",Arial,sans-serif;background:#fff;border-radius:12px;border:1.5px solid #dde6f0;overflow:hidden;max-width:1100px;margin:0 auto;font-size:13px}
.pmh-header{background:#0e3d6b;color:#fff;padding:12px 18px;display:flex;align-items:center;gap:8px}
.pmh-title{display:flex;align-items:center;gap:7px;font-weight:700;font-size:.88rem}
.pmh-body{padding:16px;display:flex;flex-direction:column;gap:14px}
.pmh-intro{font-size:.78rem;color:#666;line-height:1.6;margin:0}

/* Formulaire */
.pmh-form{background:#f8fafc;border-radius:8px;padding:14px;border:1.5px solid #e8eef5;display:flex;flex-direction:column;gap:10px}
.pmh-form-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.pmh-lbl{font-size:.72rem;font-weight:600;color:#555;min-width:160px}
.pmh-input{padding:6px 10px;border:1.5px solid #dde4ed;border-radius:6px;font-size:.8rem;font-family:inherit;color:#0e3d6b}
.pmh-form-btns{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.pmh-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 18px;background:#0e3d6b;color:#fff;border:none;border-radius:7px;font-size:.82rem;font-weight:700;cursor:pointer;font-family:inherit}
.pmh-btn:hover{background:#1673B2}
.pmh-batc-link{display:inline-flex;align-items:center;gap:5px;padding:7px 14px;background:#e8f0fb;border:1.5px solid #b0c8f0;border-radius:7px;font-size:.78rem;font-weight:600;color:#0e3d6b;text-decoration:none}
.pmh-batc-link:hover{background:#d0e4f8}

/* Actions */
.pmh-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap;padding:8px 0;border-bottom:1.5px solid #f0f4f8;margin-bottom:10px}
.pmh-actions-lbl{font-size:.68rem;color:#888;font-weight:600;white-space:nowrap}
.pmh-quick-btns{display:flex;gap:4px;flex-wrap:wrap}
.pmh-q-btn{padding:3px 9px;border-radius:5px;border:1.5px solid #dde4ed;background:#f8fafc;font-size:.72rem;font-weight:700;cursor:pointer;font-family:inherit;color:#0e3d6b}
.pmh-q-btn:hover{background:#e8f0fb}
.pmh-q-25{border-color:#b2f0d0;color:#1a7a4a;background:#f0fdf6}
.pmh-q-07{border-color:#ffd080;color:#c97200;background:#fff8ee}
.pmh-q-01,.pmh-q-19{border-color:#fca5a5;color:#c0392b;background:#fff5f5}
.pmh-rb-25{border-color:#b2f0d0;color:#1a7a4a}
.pmh-rb-25.active{background:#1a7a4a;color:#fff;border-color:#1a7a4a}
.pmh-rb-07{border-color:#ffd080;color:#c97200}
.pmh-rb-07.active{background:#c97200;color:#fff;border-color:#c97200}
.pmh-rb-01{border-color:#fca5a5;color:#c0392b}
.pmh-rb-01.active{background:#c0392b;color:#fff;border-color:#c0392b}
.pmh-rb-clr{color:#e53e3e;border-color:#fca5a5;background:#fff5f5;padding:2px 5px}
.pmh-q-clear{color:#e53e3e;border-color:#fca5a5;background:#fff5f5}
.pmh-export-btn{margin-left:auto;display:inline-flex;align-items:center;gap:6px;padding:6px 14px;background:#1a7a4a;color:#fff;border:none;border-radius:7px;font-size:.78rem;font-weight:700;cursor:pointer;font-family:inherit}
.pmh-export-btn:hover{background:#15603a}

/* Tableau */
.pmh-table-wrap{overflow-x:auto}
.pmh-table{width:100%;border-collapse:collapse;font-size:.75rem;min-width:750px}
.pmh-table th{text-align:left;padding:7px 8px;font-size:.63rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#888;border-bottom:2px solid #f0f4f8;background:#fafbfc;line-height:1.4;white-space:nowrap}
.pmh-table th small{font-weight:400;text-transform:none;letter-spacing:0;color:#bbb;display:block}
.pmh-table td{padding:7px 8px;border-bottom:1px solid #f5f5f5;vertical-align:middle}
.pmh-table tr.pmh-div td{background:#fff8ee}
.pmh-table tr.pmh-viol td{background:#fff0f0}
.pmh-table tr.pmh-warn td{background:#fffbf0}
.pmh-table tr:hover td{background:#f5f9ff}
.pmh-time{font-weight:700;color:#0e3d6b;white-space:nowrap;font-family:"Courier New",monospace;font-size:.8rem}
.pmh-wind{font-weight:600;color:#1673B2}
.pmh-gust{color:#e07000;font-size:.7rem}
.pmh-prs-on{color:#1a7a4a;font-weight:700;font-size:.78rem}
.pmh-prs-off{color:#c0392b;font-weight:700;font-size:.78rem}
.pmh-rwy-badge{display:inline-block;padding:2px 7px;border-radius:5px;font-weight:700;font-size:.75rem;margin:1px}
.pmh-r25{background:#e8f8f0;color:#1a7a4a;border:1px solid #b2f0d0}
.pmh-r01,.pmh-r19{background:#fde8e8;color:#c0392b;border:1px solid #fca5a5}
.pmh-r07{background:#fff8ee;color:#c97200;border:1px solid #ffd080}

/* Boutons piste par ligne */
.pmh-row-btns{display:flex;gap:3px;flex-wrap:wrap}
.pmh-row-btn{padding:2px 7px;border-radius:5px;border:1.5px solid #c8b8f0;background:#fff;font-size:.72rem;font-weight:700;cursor:pointer;font-family:inherit;color:#5b21b6;line-height:1.4}
.pmh-row-btn:hover{background:#ede9fe}
.pmh-row-btn.active{background:#7c3aed;color:#fff;border-color:#7c3aed}
.pmh-row-btn.clr{color:#e53e3e;border-color:#fca5a5;background:#fff5f5;padding:2px 5px}

/* Note saisie libre */
.pmh-note-input{width:100%;padding:3px 6px;border:1px solid #e0e8f0;border-radius:4px;font-size:.7rem;font-family:inherit;color:#333;background:#fafbfc}
.pmh-note-input:focus{outline:none;border-color:#1673B2}

/* Conformité */
.pmh-ok-cell{color:#1a7a4a;font-weight:700;font-size:.85rem}
.pmh-ko-cell{color:#c0392b;font-weight:700;font-size:.8rem;line-height:1.3}
.pmh-nd-cell{color:#bbb;font-size:.75rem}

/* Résumé */
.pmh-summary{padding:10px 14px;border-radius:8px;font-size:.8rem;line-height:1.6}
.pmh-sum-ok{background:#e8f8f0;border:1.5px solid #b2f0d0;color:#1a5c35}
.pmh-sum-warn{background:#fff8ee;border:1.5px solid #ffd080;color:#7a4400}
.pmh-sum-danger{background:#fff0f0;border:1.5px solid #fca5a5;color:#7a1a1a}

.pmh-loading{display:flex;align-items:center;gap:8px;color:#888;font-size:.8rem;padding:12px}
.pmh-spin{animation:pmh-rotate 1s linear infinite}
@keyframes pmh-rotate{to{transform:rotate(360deg)}}
.pmh-error{background:#fff0f0;border:1.5px solid #fca5a5;border-radius:7px;padding:10px 14px;font-size:.8rem;color:#c0392b}
.pmh-plan-box{font-size:.7rem;line-height:1.5;padding:3px 6px;border-radius:5px}
.pmh-plan-pref{background:#f0fdf6;border:1px solid #b2f0d0;color:#1a7a4a}
.pmh-plan-mixed{background:#fff8ee;border:1px solid #ffd080;color:#c97200}
.pmh-plan-dep{font-size:.72rem}.pmh-plan-arr{font-size:.72rem}
.pmh-plan-plage{font-size:.62rem;color:#aaa}
/* Stats auto BDD */
.pmh-stats-auto { background: #f0f6fb; border-radius: 8px; padding: 14px 16px; margin-bottom: 16px; }
.pmh-stats-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
.pmh-stats-title { font-weight: 700; color: #0e3d6b; font-size: .9rem; }
.pmh-stats-tabs { display: flex; gap: 6px; }
.pmh-stab { background: #fff; border: 1px solid #c8dff0; border-radius: 4px; padding: 4px 10px; font-size: .78rem; cursor: pointer; color: #1673B2; }
.pmh-stab.active { background: #1673B2; color: #fff; border-color: #1673B2; }
.pmh-stats-loading { text-align: center; color: #888; font-size: .85rem; padding: 12px; }
.pmh-stats-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; margin-bottom: 12px; }
.pmh-kpi { background: #fff; border-radius: 6px; padding: 10px 12px; text-align: center; border-top: 3px solid #1673B2; }
.pmh-kpi.bad { border-top-color: #e53e3e; }
.pmh-kpi-val { font-size: 1.5rem; font-weight: 800; color: #FF9900; }
.pmh-kpi.bad .pmh-kpi-val { color: #e53e3e; }
.pmh-kpi-lab { font-size: .7rem; color: #666; margin-top: 3px; }
.pmh-stats-chart { width: 100%; height: 120px; }
.pmh-chart-bar { fill: #1673B2; }
.pmh-chart-bar.prs { fill: #e53e3e; }
.pmh-sep { border: none; border-top: 1px solid #dde; margin: 16px 0; }
.pmh-widget-btn { background: #1673B2; color: #fff; border: none; border-radius: 5px;
  padding: 4px 9px; font-size: .72rem; font-weight: 700; cursor: pointer; white-space: nowrap; }
.pmh-widget-btn:hover { background: #0e5a96; }
/* Modale widget */
.pmh-wmodal-bg { display: none; position: fixed; top:0;right:0;bottom:0;left:0; background: rgba(0,0,0,.55);
  z-index: 9999; align-items: center; justify-content: center; }
.pmh-wmodal-bg.open { display: flex; }
.pmh-wmodal { background: #fff; border-radius: 12px; width: 520px; max-width: 96vw;
  max-height: 90vh; overflow-y: auto; box-shadow: 0 8px 40px rgba(0,0,0,.3); }
.pmh-wmodal-head { padding: 12px 18px; border-bottom: 1px solid #eee;
  display: flex; align-items: center; justify-content: space-between; background: #0e3d6b; border-radius: 12px 12px 0 0; }
.pmh-wmodal-head h3 { margin: 0; color: #fff; font-size: .95rem; }
.pmh-wmodal-close { background: none; border: none; font-size: 1.4rem; cursor: pointer; color: #fff; line-height: 1; }
.pmh-wmodal-body { padding: 18px; }
.pmh-stats-note { font-size: .72rem; color: #888; margin-top: 8px; text-align: right; }

/* Desktop : tableau visible, cartes cachées */
#pmh-cards { display: none; }

/* Mobile et tablette portrait (≤768px) : cartes visibles, tableau caché */
@media (max-width: 768px) {
  .pmh { font-size: 11px; }
  .pmh-table-wrap, .pmh-scroll-hint { display: none !important; }
  .pmh-actions { flex-wrap: wrap; }
  #pmh-cards { display: block; }
  /* Modal en bottom-sheet, sans dépasser le viewport, en gardant la safe-area iOS */
  .pmh-wmodal-bg { align-items: flex-end; }
  .pmh-wmodal {
    width: 100%; max-width: 100%;
    border-radius: 16px 16px 0 0;
    /* Hauteur max = 90% de viewport - safe-area bas (home indicator iOS) */
    max-height: calc(90vh - env(safe-area-inset-bottom, 0px));
  }
  /* Padding bas pour que la dernière ligne reste visible même avec safe-area */
  .pmh-wmodal-body {
    padding-bottom: calc(18px + env(safe-area-inset-bottom, 0px));
  }
}
</style>

<script>
(function(){

// ── Données globales ────────────────────────────────────────────────────
var pmhData      = [];   // résultats API
var pmhRealRwys  = {};   // {index: piste saisie}
var pmhNotes     = {};   // {index: note libre}
var pmhPeriod    = {};

// ── Logique PRS (miroir PHP) ────────────────────────────────────────────
function hw(wdir,wspd,qfu){if(!wdir||!wspd)return 0;return Math.round(wspd*Math.cos((wdir-qfu)*Math.PI/180)*10)/10;}
function xwc(wdir,wspd,qfu){if(!wdir||!wspd)return 0;return Math.round(Math.abs(wspd*Math.sin((wdir-qfu)*Math.PI/180))*10)/10;}
function prsStatus(wdir,wspd_eff,seuils){
  var variable=(!wdir||wdir===0);
  if(variable||wspd_eff<3)return{prs:true,runways:['25R','25L'],alert:false,exceptions:[],tw:0,xw:0};
  var tw25=Math.max(0,-hw(wdir,wspd_eff,246),-hw(wdir,wspd_eff,251));
  var xw25=Math.max(xwc(wdir,wspd_eff,246),xwc(wdir,wspd_eff,251));
  var exc=[];
  if(tw25>seuils.tw)exc.push('Arrière '+tw25+'kt > '+seuils.tw+'kt');
  if(xw25>seuils.xw)exc.push('Latéral '+xw25+'kt > '+seuils.xw+'kt');
  var prs=exc.length===0;
  var d=wdir%360;
  var rwys=prs?['25R','25L']:(d>=350||d<40)?['01']:(d>=40&&d<160)?['07L']:(d>=160&&d<220)?['19']:['25R','25L'];
  return{prs:prs,runways:rwys,alert:!prs,exceptions:exc,tw:tw25,xw:xw25};
}
function dirText(d){if(!d||d===0)return'Variable';return['N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSO','SO','OSO','O','ONO','NO','NNO'][Math.round(d/22.5)%16];}
function rwyBadge(r){var c=r.indexOf('25')>-1?'pmh-r25':r.indexOf('07')>-1?'pmh-r07':r.indexOf('19')>-1?'pmh-r19':'pmh-r01';return'<span class="pmh-rwy-badge '+c+'">'+r+'</span>';}

// ── Chargement ──────────────────────────────────────────────────────────
// Initialiser les selects d'heures dès le chargement (avant interaction)
(function initHourSelectsEarly() {
  function doInit() {
    ['pmh-start-hour','pmh-end-hour'].forEach(function(id) {
      var sel = document.getElementById(id);
      if (!sel || sel.options.length) return;
      for (var h = 0; h < 24; h++) {
        for (var m = 0; m < 60; m += 30) {
          var val = (h < 10 ? '0' : '') + h + ':' + (m === 0 ? '00' : '30');
          var opt = document.createElement('option');
          opt.value = opt.textContent = val;
          sel.appendChild(opt);
        }
      }
      // Valeur par défaut : 06:00 pour start, 12:00 pour end
      sel.value = (id === 'pmh-start-hour') ? '06:00' : '12:00';
    });
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', doInit);
  } else { doInit(); }
})();

window.pmhLoad = function(){
  var startDate=document.getElementById('pmh-start-date').value;
  var startHour=document.getElementById('pmh-start-hour').value;
  var endDate=document.getElementById('pmh-end-date').value;
  var endHour=document.getElementById('pmh-end-hour').value;
  if(!startDate||!endDate){alert('Veuillez saisir une date de début et de fin.');return;}
  var start=startDate+'T'+startHour;
  var end=endDate+'T'+endHour;
  var startISO=startDate+'T'+startHour+':00Z';
  var endISO=endDate+'T'+endHour+':00Z';
  pmhPeriod={start:start,end:end,startISO:startISO,endISO:endISO};
  pmhRealRwys={};pmhNotes={};pmhData=[];
  document.getElementById('pmh-loading').style.display='flex';
  document.getElementById('pmh-results').style.display='none';
  document.getElementById('pmh-error').style.display='none';
  // Charger les données
  fetch('/api/metar_history.php?start='+encodeURIComponent(startISO)+'&end='+encodeURIComponent(endISO))
    .then(function(r){return r.json();})
    .then(function(d){
      document.getElementById('pmh-loading').style.display='none';
      if(d.error){pmhShowError(d.error);return;}
      pmhData=d.results||[];
      pmhRender(d);
      window.pmhData = pmhData;
      if(typeof window.pmhRenderCards==="function") window.pmhRenderCards();
    })
    .catch(function(e){pmhShowError('Erreur: '+e.message);});
};

// ── Saisie par ligne ────────────────────────────────────────────────────
window.pmhSetRow = function(idx, rwy){
  pmhRealRwys[idx]=rwy;
  // Mettre à jour les boutons de la ligne
  var row=document.getElementById('pmh-row-'+idx);
  if(!row)return;
  row.querySelectorAll('.pmh-row-btn').forEach(function(b){
    b.classList.toggle('active', rwy && b.dataset.rwy===rwy);
  });
  // Mettre à jour la cellule conformité
  updateConformity(idx);
  updateSummary();
};

window.pmhSetAll = function(rwy){
  pmhData.forEach(function(m,i){ pmhSetRow(i,rwy); });
};

window.pmhNoteChange = function(idx, val){
  pmhNotes[idx]=val;
};

function updateConformity(idx){
  var m=pmhData[idx]; if(!m)return;
  var config=pmhRealRwys[idx]||null;
  var cell=document.getElementById('pmh-conf-'+idx);
  var noteCell=document.getElementById('pmh-verdict-'+idx);
  if(!cell)return;
  if(!config){
    cell.innerHTML='<span class="pmh-nd-cell">—</span>';
    if(noteCell)noteCell.textContent='';
    return;
  }
  var a2013=m.aip2013;
  // Config préférentielle = contient 25 (25R/25L ou 19/25R)
  var isPref=config.indexOf('25')>-1;
  var meteoSays25=a2013.prs;
  var txt,cls,verdict='';
  var row2=document.getElementById('pmh-row-'+idx);
  if(row2){row2.classList.remove('pmh-viol','pmh-warn');}
  if(isPref&&meteoSays25){
    cls='pmh-ok-cell';txt='✓';verdict='Conforme — configuration préférentielle ('+config+') autorisée par AIP 2013';
  }else if(!isPref&&!meteoSays25){
    cls='pmh-ok-cell';txt='✓';verdict='Conforme — configuration alternative ('+config+') justifiée (arr:'+a2013.tw+'kt lat:'+a2013.xw+'kt)';
  }else if(isPref&&!meteoSays25){
    if(row2)row2.classList.add('pmh-viol');
    cls='pmh-ko-cell';txt='⚠ VIOLATION';
    verdict='VIOLATION AIP 2013 — config '+config+' maintenue malgré vent arrière '+a2013.tw+'kt (seuil 7kt) et/ou latéral '+a2013.xw+'kt (seuil 15kt).';
  }else{
    if(row2)row2.classList.add('pmh-warn');
    cls='pmh-ko-cell';txt='⚡ Écart';
    verdict='Config '+config+' sans justification météo — PRS applicable (arr:'+a2013.tw+'kt<7kt, lat:'+a2013.xw+'kt<15kt).';
  }
  cell.innerHTML='<span class="'+cls+'">'+txt+'</span>';
  if(noteCell)noteCell.textContent=verdict;
}

function updateSummary(){
  var total=pmhData.length;
  var saisies=Object.keys(pmhRealRwys).filter(function(k){return pmhRealRwys[k];}).length;
  var violations=0;
  pmhData.forEach(function(m,i){
    var rwy=pmhRealRwys[i];
    if(!rwy)return;
    var isPref=config?config.indexOf('25')>-1:(rwy&&(rwy==='25R'||rwy==='25L'));
    if(isPref&&!m.aip2013.prs)violations++;
  });
  var divs=pmhData.filter(function(m){return m.divergence;}).length;
  var sumEl=document.getElementById('pmh-summary');
  if(violations>0){
    sumEl.className='pmh-summary pmh-sum-danger';
    sumEl.textContent='⚠ '+violations+' violation(s) AIP 2013 détectée(s) sur '+saisies+' ligne(s) saisies / '+total+' total.'+
      (divs>0?' '+divs+' divergence(s) entre les deux AIP.':'');
  }else if(divs>0){
    sumEl.className='pmh-summary pmh-sum-warn';
    sumEl.textContent='⚡ '+divs+' divergence(s) entre AIP 2013 et AIP actuel. '+saisies+'/'+total+' pistes saisies.';
  }else{
    sumEl.className='pmh-summary pmh-sum-ok';
    sumEl.textContent='✓ '+total+' entrée(s) analysée(s). '+saisies+' piste(s) saisie(s). Aucune violation détectée.';
  }
}

// ── Rendu ───────────────────────────────────────────────────────────────
function pmhRender(d){
  document.getElementById('pmh-results').style.display='';
  updateSummary();
  var tbody=document.getElementById('pmh-tbody');
  tbody.innerHTML='';
  pmhData.forEach(function(m,idx){
    var tr=document.createElement('tr');
    tr.id='pmh-row-'+idx;
    if(m.divergence)tr.classList.add('pmh-div');
    // Heure
    var t=new Date(m.time);
    var timeUTC=isNaN(t)?m.time:
      t.toLocaleTimeString('fr-BE',{hour:'2-digit',minute:'2-digit',timeZone:'UTC'})+' UTC';
    var timeBE=isNaN(t)?'':
      t.toLocaleTimeString('fr-BE',{hour:'2-digit',minute:'2-digit',timeZone:'Europe/Brussels'})+' (BE)';
    var dateStr=isNaN(t)?'':
      t.toLocaleDateString('fr-BE',{day:'2-digit',month:'2-digit',year:'numeric',timeZone:'UTC'});
    var timeStr='<b>'+timeUTC+'</b><br><span style="color:#e07000;font-size:.72rem">'+timeBE+'</span><br><small>'+dateStr+'</small>';
    // Vent
    var wStr=(m.wdir==='VRB'||!m.wdir)?'Variable':(m.wdir+'° '+dirText(m.wdir));
    var spdStr=(m.wspd_kt||m.wspd||0)+' kt';
    var gustKt=m.wgst_kt||null;
    var gustMetar=m.wgst_metar||null;
    var gustIrm=m.wgst_irm||null;
    var gustStr='';
    if(gustMetar!==null||gustIrm!==null){
      gustStr='<br><span class="pmh-gust">💨 eff: '+(gustKt||'—')+' kt</span>'
        +'<br><small style="color:#aaa;font-size:.63rem">METAR:'+(gustMetar!==null?gustMetar:'—')+'kt · IRM:'+(gustIrm!==null?gustIrm:'—')+'kt</small>';
    } else if(gustKt){
      gustStr='<br><span class="pmh-gust">💨 '+gustKt+' kt</span>';
    } else {
      gustStr='<br><span style="color:#ccc;font-size:.65rem">/ —</span>';
    }
    // AIP 2013
    var s2013=m.aip2013;
    var cell2013=(s2013.prs?'<span class="pmh-prs-on">PRS actif</span>':'<span class="pmh-prs-off">HORS PRS</span>')
      +'<br>'+s2013.runways.map(rwyBadge).join('')
      +'<br><small style="color:#aaa;font-size:.62rem">arr:'+s2013.tw+'kt lat:'+s2013.xw+'kt</small>';
    // AIP actuel
    var snow=m.aip_now;
    var cellNow=(snow.prs?'<span class="pmh-prs-on">PRS actif</span>':'<span class="pmh-prs-off">HORS PRS</span>')
      +'<br>'+snow.runways.map(rwyBadge).join('')
      +'<br><small style="color:#aaa;font-size:.62rem">arr:'+snow.tw+'kt lat:'+snow.xw+'kt</small>';
    if(m.divergence)cellNow+='<br><span style="font-size:.62rem;background:#fff8ee;color:#c97200;padding:1px 5px;border-radius:3px">⚡ divergence</span>';
    // Boutons piste réelle
    var CONFIGS = ['25R/25L','19/25R','07L/07R','01/07R','01/01','19/19'];
    var btns='<div class="pmh-row-btns">'
      +CONFIGS.map(function(r){
        var cls='pmh-row-btn '+(r.indexOf('25')>-1?'pmh-rb-25':r.indexOf('07')>-1?'pmh-rb-07':'pmh-rb-01');
        return'<button class="'+cls+'" data-rwy="'+r+'" onclick="pmhSetRow('+idx+',\''+r+'\')">'+r+'</button>';
      }).join('')
      +'<button class="pmh-row-btn pmh-rb-clr" data-rwy="" onclick="pmhSetRow('+idx+',null)">✕</button>'
      +'</div>';
    // Note libre
    var noteInput='<input type="text" class="pmh-note-input" placeholder="Note..." '
      +'onchange="pmhNoteChange('+idx+',this.value)" oninput="pmhNoteChange('+idx+',this.value)">';
    // Planning AIP 2013 pour cette heure
    var pl = m.aip_planning;
    var cellPlanning = '';
    if (pl) {
      var plCls = (pl.dep.indexOf('19')>-1||pl.arr.indexOf('19')>-1) ? 'pmh-plan-mixed' : 'pmh-plan-pref';
      cellPlanning = '<div class="pmh-plan-box '+plCls+'">'
        +'<div class="pmh-plan-dep">✈ <b>'+pl.label_dep+'</b></div>'
        +'<div class="pmh-plan-arr">↘ <b>'+pl.label_arr+'</b></div>'
        +'<div class="pmh-plan-plage">'+pl.plage+'</div>'
        +'</div>';
    } else {
      cellPlanning = '<span style="color:#ccc;font-size:.7rem">—</span>';
    }

    tr.innerHTML=
      '<td><span class="pmh-time">'+timeStr+'</span></td>'
      +'<td><span class="pmh-wind">'+wStr+'<br>'+spdStr+'</span>'+gustStr+'</td>'
      +'<td>'+cellPlanning+'</td>'
      +'<td>'+cell2013+'</td>'
      +'<td>'+cellNow+'</td>'
      +'<td>'+btns+'</td>'
      +'<td id="pmh-conf-'+idx+'"><span class="pmh-nd-cell">—</span></td>'
      +'<td style="text-align:center;white-space:nowrap"><button class="pmh-widget-btn" onclick="pmhOpenWidget('+idx+')">▶ Widget</button></td>'
      +'<td id="pmh-verdict-'+idx+'" style="font-size:.65rem;color:#555;line-height:1.4;max-width:180px">'+noteInput+'</td>';
    tbody.appendChild(tr);
  });
  document.getElementById('pmh-source-note').textContent=
    'Source : '+(d.source||'IRM')+' — '+d.count+' entrée(s). '+(d.note||'');
}

// ── Export PDF ────────────────────────────────────────────────────────────
window.pmhExport = function(){
  if(!pmhData.length){alert('Aucune donnée à exporter. Lancez d\'abord une analyse.');return;}

  var dateStr=(pmhPeriod.start||'').substring(0,10);
  var dateFin=(pmhPeriod.end||'').substring(0,10);

  var violations=0,saisies=0;
  pmhData.forEach(function(m,i){
    var rwy=pmhRealRwys[i];if(!rwy)return;saisies++;
    var isPref=config?config.indexOf('25')>-1:(rwy&&(rwy==='25R'||rwy==='25L'));
    if(isPref&&!m.aip2013.prs)violations++;
  });

  var rows='';
  pmhData.forEach(function(m,idx){
    var rwy=pmhRealRwys[idx]||'—';
    var note=pmhNotes[idx]||'';
    var a2013=m.aip2013,anow=m.aip_now;
    var t=new Date(m.time);
    var heureUTC=isNaN(t)?m.time:t.toLocaleTimeString('fr-BE',{hour:'2-digit',minute:'2-digit',timeZone:'UTC'})+' UTC';
    var heureBE=isNaN(t)?'':t.toLocaleTimeString('fr-BE',{hour:'2-digit',minute:'2-digit',timeZone:'Europe/Brussels'})+' (BE)';
    var dateRow=isNaN(t)?'':t.toLocaleDateString('fr-BE',{day:'2-digit',month:'2-digit',year:'numeric',timeZone:'UTC'});
    var wStr=(m.wdir==='VRB'||!m.wdir)?'Variable':(m.wdir+'° '+dirText(m.wdir));
    var spdStr=(m.wspd_kt||m.wspd||0)+' kt';
    var gustKt=m.wgst_kt||null;
    var gustMetar2=m.wgst_metar||null;
    var gustIrm2=m.wgst_irm||null;
    var gustStr=gustKt?'💨 eff:'+gustKt+'kt (METAR:'+(gustMetar2!==null?gustMetar2:'—')+'kt / IRM:'+(gustIrm2!==null?gustIrm2:'—')+'kt)':'—';
    var isPref=config?config.indexOf('25')>-1:(rwy&&(rwy==='25R'||rwy==='25L'));
    var conforme='—',confCls='',analyse='';
    if(rwy!=='—'){
      if(isPref&&a2013.prs){conforme='✓ OUI';confCls='ok';analyse='Conforme';}
      else if(!isPref&&!a2013.prs){conforme='✓ OUI';confCls='ok';analyse='Alternative justifiée';}
      else if(isPref&&!a2013.prs){conforme='⚠ NON';confCls='viol';analyse='VIOLATION — arr:'+a2013.tw+'kt&gt;7, lat:'+a2013.xw+'kt&gt;15';}
      else{conforme='⚡ Écart';confCls='warn';analyse='Non justifié météo';}
    }
    var rowCls=confCls==='viol'?'viol-row':m.divergence?'div-row':'';
    var pl2=m.aip_planning;
    var plCell=pl2?('DEP:'+pl2.label_dep+' ARR:'+pl2.label_arr):'—';
    rows+='<tr class="'+rowCls+'">'
      +'<td><b>'+heureUTC+'</b><br><span class="be">'+heureBE+'</span><br><span class="dt">'+dateRow+'</span></td>'
      +'<td>'+wStr+'<br><b>'+spdStr+'</b><br>'+gustStr+'</td>'
      +'<td style="font-size:7pt">'+plCell+'</td>'
      +'<td class="'+(a2013.prs?'prs-on':'prs-off')+'">'+(a2013.prs?'PRS ✓':'HORS PRS')+'<br><b>'+a2013.runways.join('/')+'</b><br><span class="comp">arr:'+a2013.tw+'kt lat:'+a2013.xw+'kt</span></td>'
      +'<td class="'+(anow.prs?'prs-on':'prs-off')+'">'+(anow.prs?'PRS ✓':'HORS PRS')+'<br><b>'+anow.runways.join('/')+'</b>'+(m.divergence?'<br><span class="div-lbl">⚡ divergence</span>':'')+'</td>'
      +'<td class="rwy-cell">'+rwy+'</td>'
      +'<td class="conf-cell '+confCls+'">'+conforme+'<br><span class="analyse">'+analyse+'</span></td>'
      +'<td class="note-cell">'+note+'</td>'
      +'</tr>';
  });

  var html='<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">'
    +'<title>Analyse pistes EBBR — '+dateStr+'</title>'
    +'<style>'
    +'@page{size:A4 landscape;margin:12mm 10mm}'
    +'*{font-family:Arial,Helvetica,sans-serif;box-sizing:border-box}'
    +'body{margin:0;padding:0;font-size:8.5pt;color:#222}'
    +'.header{background:#0e3d6b;color:white;padding:10px 14px;margin-bottom:8px;border-radius:4px;display:flex;justify-content:space-between;align-items:flex-start}'
    +'.header h1{margin:0;font-size:13pt;font-weight:bold}'
    +'.header p{margin:3px 0 0;font-size:8pt;opacity:.8}'
    +'.header .logo{font-size:11pt;font-weight:bold;color:rgba(255,255,255,.7);white-space:nowrap}'
    +'.meta{display:flex;gap:16px;margin-bottom:8px;padding:7px 10px;background:#f0f4f8;border-radius:4px;font-size:7.5pt;border:1px solid #dde6f0}'
    +'.meta-col{flex:1}'
    +'.meta b{color:#0e3d6b;display:block;margin-bottom:2px}'
    +'.legal{background:#fff8ee;border:1px solid #ffd080;border-radius:4px;padding:7px 10px;margin-bottom:8px;font-size:7.5pt;color:#7a4400;line-height:1.4}'
    +'.summary{padding:7px 10px;border-radius:4px;margin-bottom:8px;font-size:9pt;font-weight:bold}'
    +'.sum-viol{background:#fff0f0;border:1.5px solid #fca5a5;color:#7a1a1a}'
    +'.sum-ok{background:#e8f8f0;border:1.5px solid #b2f0d0;color:#1a5c35}'
    +'.sum-warn{background:#fff8ee;border:1.5px solid #ffd080;color:#7a4400}'
    +'table{width:100%;border-collapse:collapse;font-size:7.5pt}'
    +'th{background:#0e3d6b;color:white;padding:5px 6px;text-align:left;font-size:7pt;line-height:1.3;white-space:nowrap}'
    +'td{padding:4px 5px;border-bottom:1px solid #e8eef5;vertical-align:top;line-height:1.35}'
    +'tr:nth-child(even) td{background:#f8fafc}'
    +'tr.viol-row td{background:#fff0f0!important}'
    +'tr.div-row td{background:#fff8ee!important}'
    +'.prs-on{color:#1a7a4a;font-weight:bold}'
    +'.prs-off{color:#c0392b;font-weight:bold}'
    +'.ok{color:#1a7a4a;font-weight:bold}'
    +'.viol{color:#c0392b;font-weight:bold}'
    +'.warn{color:#c97200;font-weight:bold}'
    +'.rwy-cell{font-weight:bold;font-size:10pt;color:#0e3d6b;text-align:center}'
    +'.conf-cell{text-align:center;font-weight:bold}'
    +'.be{color:#e07000;font-size:7pt}'
    +'.dt{color:#aaa;font-size:7pt}'
    +'.comp{color:#888;font-size:6.5pt}'
    +'.div-lbl{color:#c97200;font-size:6.5pt}'
    +'.analyse{font-weight:normal;font-size:7pt;color:#555}'
    +'.note-cell{color:#555;font-style:italic;font-size:7pt;max-width:100px}'
    +'.footer{margin-top:8px;font-size:6.5pt;color:#999;border-top:1px solid #e0e8f0;padding-top:5px;display:flex;justify-content:space-between}'
    +'@media print{.no-print{display:none}}'
    +'</style></head><body>'
    +'<div class="header">'
    +'<div><h1>Analyse conditions de vent — Brussels Airport (EBBR)</h1>'
    +'<p>Période : <b>'+dateStr+'</b> → <b>'+dateFin+'</b> UTC · Source : IRM Station 6451 Zaventem/EBBR (mesures officielles)</p></div>'
    +'<div class="logo">ça suffit ! ASBL</div>'
    +'</div>'
    +'<div class="meta">'
    +'<div class="meta-col"><b>⚖ AIP sept. 2013 — Instruction ministérielle 17/07/2013 (base légale)</b>'
    +'Vent arrière seuil : <b>7 kt</b> · Vent latéral seuil : <b>15 kt</b> · Max rafale arrière : 10 kt · (rafales incluses)</div>'
    +'<div class="meta-col"><b>📋 AIP actuel skeyes (contesté juridiquement)</b>'
    +'Vent arrière seuil : <b>7 kt</b> · Vent latéral seuil : <b>20 kt</b> · Pas de limite sur pistes 01/07</div>'
    +'</div>'
    +'<div class="legal">⚖ <b>Rappel juridique :</b> Selon l\'AIP du 17/07/2013 (seule base légale valide), tout vent arrière &gt; 7 kt OU latéral &gt; 15 kt (rafales incluses) sur les pistes 25R/L impose l\'utilisation d\'une piste alternative (01, 07L/R ou 19). skeyes applique un seuil latéral de 20 kt au lieu de 15 kt, ce qui est jugé illégal. Cette différence est documentée dans le présent tableau pour chaque mesure horaire IRM.</div>'
    +'<div class="summary '+(violations>0?'sum-viol':saisies>0?'sum-ok':'sum-warn')+'">'
    +(violations>0?'⚠ '+violations+' VIOLATION(S) AIP 2013 détectée(s) sur '+saisies+' piste(s) saisies ('+pmhData.length+' mesures analysées)'
      :saisies>0?'✓ '+pmhData.length+' mesures analysées — '+saisies+' piste(s) saisies — Aucune violation détectée'
      :'ℹ '+pmhData.length+' mesures analysées — Pistes réelles non encore saisies')
    +'</div>'
    +'<table><thead><tr>'
    +'<th style="width:70px">Heure UTC<br>Heure BE<br>Date</th>'
    +'<th style="width:80px">Direction vent<br>Moy / Rafales</th>'
    +'<th style="width:90px">Planning AIP 2013<br>Config attendue</th>'
    +'<th style="width:110px">AIP 2013 (légal)<br>PRS · Piste suggérée</th>'
    +'<th style="width:110px">AIP actuel (contesté)<br>PRS · Piste suggérée</th>'
    +'<th style="width:55px">Piste réelle<br>(BATC)</th>'
    +'<th style="width:80px">Conforme<br>AIP 2013 ?</th>'
    +'<th>Note / Analyse</th>'
    +'</tr></thead><tbody>'+rows+'</tbody></table>'
    +'<div class="footer">'
    +'<span>Document généré le '+new Date().toLocaleString('fr-BE',{timeZone:'Europe/Brussels',day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'})+' · ça suffit ! ASBL — casuffit.be</span>'
    +'<span>Données : IRM Institut Royal Météorologique de Belgique — Station synoptique 6451 Zaventem/EBBR (mesures officielles)</span>'
    +'</div>'
    +'</body></html>';

  var win=window.open('','_blank','width=1200,height=850');
  if(!win){alert('Popup bloqué — autorisez les popups pour ce site.');return;}
  win.document.write(html);
  win.document.close();
  setTimeout(function(){win.focus();win.print();},500);
};

function pmhShowError(msg){
  document.getElementById('pmh-loading').style.display='none';
  var e=document.getElementById('pmh-error');e.textContent=msg;e.style.display='';
}

// Initialiser les dates par défaut : hier 06h-12h UTC
(function initDates() {
  var now=new Date();
  var d=new Date(now);d.setUTCDate(d.getUTCDate()-1);d.setUTCHours(6,0,0,0);
  var e=new Date(d);e.setUTCHours(12,0,0,0);
  function fmtDate(dt){return dt.toISOString().slice(0,10);}
  function fmtHour(dt){return dt.toISOString().slice(11,16);}
  function doInit() {
    var sd=document.getElementById('pmh-start-date');
    var ed=document.getElementById('pmh-end-date');
    var sh=document.getElementById('pmh-start-hour');
    var eh=document.getElementById('pmh-end-hour');
    if(sd && !sd.value) sd.value=fmtDate(d);
    if(ed && !ed.value) ed.value=fmtDate(e);
    if(sh && !sh.value) sh.value=fmtHour(d).slice(0,5);
    if(eh && !eh.value) eh.value=fmtHour(e).slice(0,5);
  }
  doInit(); // Immédiat (fonctionne si le script est après le HTML)
  document.addEventListener('DOMContentLoaded', doInit); // Fallback
})();

})();

// ── Stats auto depuis BDD ─────────────────────────────────────────────────
(function() {
  var currentPeriod = '30d';

  function loadStats(period) {
    currentPeriod = period;
    var el = document.getElementById('pmh-stats-content');
    if (!el) return;
    el.innerHTML = '<div class="pmh-stats-loading">Chargement…</div>';

    fetch('/api/metar_stats.php?period=' + period + '&view=daily')
      .then(function(r) { return r.json(); })
      .then(function(d) { renderStats(d); })
      .catch(function() {
        el.innerHTML = '<div class="pmh-stats-loading" style="color:#e53e3e">Données non disponibles — la base historique se remplit progressivement.</div>';
      });
  }

  function renderStats(d) {
    var el = document.getElementById('pmh-stats-content');
    if (!el) return;

    if (!d.data || d.data.length === 0) {
      el.innerHTML = '<div class="pmh-stats-loading">Pas encore de données. La base se remplit toutes les 30 min.</div>';
      return;
    }

    var totalDays  = d.total_days || 0;
    var prsDays    = d.prs_days   || 0;
    var prs13Days  = d.prs13_days || 0;
    var prsPct     = d.prs_days_pct  || 0;
    var prs13Pct   = d.prs13_days_pct || 0;
    var records    = d.data.reduce(function(s, r) { return s + (+r.records); }, 0);

    // KPIs
    var html = '<div class="pmh-stats-kpis">'
      + '<div class="pmh-kpi bad"><div class="pmh-kpi-val">' + prsPct + '%</div><div class="pmh-kpi-lab">Jours piste 01<br>(normes actuelles)</div></div>'
      + '<div class="pmh-kpi bad"><div class="pmh-kpi-val">' + prs13Pct + '%</div><div class="pmh-kpi-lab">Jours piste 01<br>(normes légales 2013)</div></div>'
      + '<div class="pmh-kpi"><div class="pmh-kpi-val">' + prsDays + '</div><div class="pmh-kpi-lab">Jours en 01<br>sur ' + totalDays + ' analysés</div></div>'
      + '<div class="pmh-kpi"><div class="pmh-kpi-val">' + (prsDays - prs13Days) + '</div><div class="pmh-kpi-lab">Jours illégaux<br>(01 sans justif.)</div></div>'
      + '</div>';

    // Mini graphique barres (jours PRS par semaine)
    html += renderChart(d.data);

    html += '<div class="pmh-stats-note">⚡ ' + records + ' METARs analysés — mise à jour toutes les 30 min</div>';

    el.innerHTML = html;
  }

  function renderChart(data) {
    if (data.length < 2) return '';

    // Agréger par semaine
    var weeks = {};
    data.forEach(function(r) {
      var d = new Date(r.day + 'T00:00:00Z');
      var mon = new Date(d);
      mon.setUTCDate(d.getUTCDate() - d.getUTCDay() + 1);
      var key = mon.toISOString().slice(0, 10);
      if (!weeks[key]) weeks[key] = { total: 0, prs: 0 };
      weeks[key].total++;
      if (+r.prs_count > 0) weeks[key].prs++;
    });

    var keys  = Object.keys(weeks).sort();
    var maxPct = 100;
    var w = 100 / keys.length;
    var bars = '';
    var labels = '';

    keys.forEach(function(k, i) {
      var wk    = weeks[k];
      var pct   = wk.total > 0 ? Math.round(wk.prs / wk.total * 100) : 0;
      var h     = (pct / maxPct * 80);
      var x     = i * w + w * 0.15;
      var bw    = w * 0.7;
      var cls   = pct >= 30 ? 'pmh-chart-bar prs' : 'pmh-chart-bar';
      bars += '<rect class="' + cls + '" x="' + x + '%" y="' + (90 - h) + '" width="' + bw + '%" height="' + h + '" rx="2">'
            + '<title>Semaine du ' + k + ' : ' + pct + '% jours piste 01 (' + wk.prs + '/' + wk.total + ')</title></rect>';
      // Label semaine (toutes les 4 semaines)
      if (i % 4 === 0) {
        var lbl = k.slice(5); // MM-DD
        labels += '<text x="' + (x + bw/2) + '%" y="100%" text-anchor="middle" style="font-size:8px;fill:#888">' + lbl + '</text>';
      }
    });

    return '<svg class="pmh-stats-chart" viewBox="0 0 100 110" preserveAspectRatio="none">'
         + '<text x="0" y="10" style="font-size:7px;fill:#888">100%</text>'
         + '<line x1="0" y1="10" x2="100%" y2="10" stroke="#eee" stroke-width="0.5"/>'
         + '<text x="0" y="50" style="font-size:7px;fill:#888">50%</text>'
         + '<line x1="0" y1="50" x2="100%" y2="50" stroke="#eee" stroke-width="0.5"/>'
         + bars + labels
         + '</svg>';
  }

  // Init
  document.addEventListener('DOMContentLoaded', function() {
    // Boutons de période
    document.querySelectorAll('.pmh-stab').forEach(function(btn) {
      btn.addEventListener('click', function() {
        document.querySelectorAll('.pmh-stab').forEach(function(b) { b.classList.remove('active'); });
        btn.classList.add('active');
        loadStats(btn.dataset.period);
      });
    });

    // Chargement initial
    loadStats('30d');
  });
})();

// ── Widget replay par heure ───────────────────────────────────────────────
window.pmhOpenWidget = function(idx) {
  var m = pmhData[idx];
  if (!m) return;

  var t = new Date(m.time);
  var timeUTC = isNaN(t) ? m.time :
    t.toLocaleTimeString('fr-BE', {hour:'2-digit', minute:'2-digit', timeZone:'UTC'}) + ' UTC';
  var dateStr = isNaN(t) ? '' :
    t.toLocaleDateString('fr-BE', {day:'2-digit', month:'2-digit', year:'numeric', timeZone:'UTC'});

  document.getElementById('pmh-wmodal-title').textContent = '🌬 ' + dateStr + ' — ' + timeUTC;
  document.getElementById('pmh-wmodal-bg').classList.add('open');

  // Construire le JSON compatible metar_replay
  var wd   = (m.wdir === 'VRB' || !m.wdir) ? null : (int = parseInt(m.wdir), isNaN(int) ? null : int);
  var ws   = m.wspd_kt || m.wspd || 0;
  var wg   = m.wgst_kt || null;
  var wgm  = m.wgst_metar || null;
  var wgi  = m.wgst_irm  || null;

  // Composantes pour chaque piste
  // Composantes vent pour une piste donnée
  //   qfu = cap magnétique de la piste à l'atterrissage (°)
  //   wd  = direction d'où vient le vent (°)
  //   tw > 0 : vent arrière ; tw < 0 : vent de face
  //   xw     : composante traversière (toujours positive)
  function calcComp(qfu) {
    if (wd === null) return {tw_moy:0, tw_gst:null, xw_moy:0, xw_gst:null};
    var d = (wd - qfu) * Math.PI / 180;
    var tw = Math.round(-ws * Math.cos(d) * 10) / 10;   // ← signe inversé : tw>0 = arrière
    var tg = (wg !== null && wg !== undefined)
              ? Math.round(-wg * Math.cos(d) * 10) / 10
              : null;
    var xw = Math.round(Math.abs(ws * Math.sin(d)) * 10) / 10;
    var xg = (wg !== null && wg !== undefined)
              ? Math.round(Math.abs(wg * Math.sin(d)) * 10) / 10
              : null;
    return {tw_moy: tw, tw_gst: tg, xw_moy: xw, xw_gst: xg};
  }

  var d = {
    replay: true,
    obs_time: m.time,
    metar: m.metar || ('IRM — ' + m.time),
    wdir: wd, wspd: ws, wspd_eff: Math.max(ws, wg||0),
    wgst: wg, wgst_metar: wgm, wgst_irm: wgi,
    variable: (m.wdir === 'VRB' || !m.wdir),
    temp: m.temp || null, qnh: m.qnh || null,
    prs_active: m.aip_now ? m.aip_now.prs : true,
    aip2013: { prs_active: m.aip2013 ? m.aip2013.prs : true },
    tw_25_max: m.aip_now ? m.aip_now.tw : null,
    xw_25_max: m.aip_now ? m.aip_now.xw : null,
    runways: m.aip_now ? m.aip_now.runways : [],
    // QFU réels EBBR (Jeppesen, Mag Var 1.5°W)
    components: {
      '25L': calcComp(251), '25R': calcComp(246),
      '07R': calcComp(71),  '07L': calcComp(66),
      '01':  calcComp(14),  '19':  calcComp(194)
    }
  };

  pmhRenderWidgetModal(document.getElementById('pmh-wmodal-body'), d);
};

window.pmhCloseWidget = function() {
  document.getElementById('pmh-wmodal-bg').classList.remove('open');
};

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') pmhCloseWidget();
});

function pmhRenderWidgetModal(wrap, d) {
  var wd = d.wdir, ws = d.wspd, wg = d.wgst, prs = d.prs_active, p13 = d.aip2013?.prs_active;
  var dirs = ['N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSO','SO','OSO','O','ONO','NO','NNO'];
  var dirTxt = wd !== null ? dirs[Math.round(wd/22.5)%16] : '—';

  var gstTxt = '';
  if (d.wgst_metar !== null && d.wgst_metar !== undefined) gstTxt += 'METAR: '+d.wgst_metar+' kt  ';
  if (d.wgst_irm   !== null && d.wgst_irm   !== undefined) gstTxt += 'IRM: '+d.wgst_irm+' kt';
  if (!gstTxt && wg) gstTxt = wg + ' kt';

  // Affichage d'une ligne piste — tw>0 = arrière (rouge si >7kt), tw<0 = face (vert)
  function twRow(rwy, comp) {
    if (!comp) return '';
    var twM = comp.tw_moy, twG = comp.tw_gst;
    var xwM = comp.xw_moy, xwG = comp.xw_gst;

    // Valeur max retenue pour le code couleur (rafale si dispo, sinon moyenne)
    var twMax = (twG !== null && twG !== undefined) ? Math.max(twM, twG) : twM;
    var xwMax = (xwG !== null && xwG !== undefined) ? Math.max(xwM, xwG) : xwM;

    // Texte vent arrière/face
    var twTxt, twCol;
    if (twMax > 0) {
      // Vent arrière
      twTxt = 'Arrière : ' + twM.toFixed(1) + ' kt';
      if (twG !== null && twG !== undefined && twG !== twM) twTxt += ' (raf. ' + twG.toFixed(1) + ')';
      twCol = twMax > 7 ? '#c00' : (twMax > 3 ? '#d97706' : '#080');
    } else {
      // Vent de face
      twTxt = 'Face : ' + (-twM).toFixed(1) + ' kt';
      if (twG !== null && twG !== undefined && twG !== twM) twTxt += ' (raf. ' + (-twG).toFixed(1) + ')';
      twCol = '#080';
    }

    // Texte traversier
    var xwTxt = 'Traversier : ' + xwM.toFixed(1) + ' kt';
    if (xwG !== null && xwG !== undefined && xwG !== xwM) xwTxt += ' (raf. ' + xwG.toFixed(1) + ')';
    var xwCol = xwMax > 15 ? '#c00' : (xwMax > 10 ? '#d97706' : '#555');

    return '<tr>'
      + '<td style="padding:5px 10px;font-weight:700;color:#0e3d6b">'+rwy+'</td>'
      + '<td style="padding:5px 10px;color:'+twCol+';font-weight:'+(twMax>7?'700':'400')+'">'+twTxt+'</td>'
      + '<td style="padding:5px 10px;color:'+xwCol+';font-weight:'+(xwMax>15?'700':'400')+'">'+xwTxt+'</td>'
      + '</tr>';
  }

  var illegal = !prs && p13;

  wrap.innerHTML =
    '<div style="background:#0e3d6b;color:#fff;margin:-18px -18px 14px;padding:12px 18px;font-size:.82rem">'
    + '📡 Source : ' + (d.metar||'IRM synop') + '</div>'

    + '<div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:14px">'
      + '<div>'
        + '<div style="font-size:1.8rem;font-weight:800;color:#0e3d6b">'+(wd!==null?wd+'° '+dirTxt:'Variable')+'</div>'
        + '<div style="color:#1673B2;font-weight:700;font-size:1.1rem">'+ws+' kt <span style="font-size:.75rem;font-weight:400">(moy)</span></div>'
        + (gstTxt?'<div style="color:#e07000;font-size:.82rem">💨 Rafales: '+gstTxt+'</div>':'')
      + '</div>'
      + '<div style="display:flex;gap:8px;flex-wrap:wrap">'
        + '<div style="background:'+(prs?'#e8f5e9':'#fde8e8')+';border-radius:7px;padding:10px 14px;text-align:center;border-top:3px solid '+(prs?'#27ae60':'#e53e3e')+'">'
          + '<div style="font-size:.65rem;color:#777;margin-bottom:4px">Normes skeyes</div>'
          + '<div style="font-weight:800;color:'+(prs?'#166534':'#b91c1c')+'">'+( prs?'✓ Piste 25':'⚠ Piste 01')+'</div>'
        + '</div>'
        + '<div style="background:'+(p13?'#e8f5e9':'#fde8e8')+';border-radius:7px;padding:10px 14px;text-align:center;border-top:3px solid '+(p13?'#27ae60':'#e53e3e')+'">'
          + '<div style="font-size:.65rem;color:#777;margin-bottom:4px">Normes légales 2013</div>'
          + '<div style="font-weight:800;color:'+(p13?'#166534':'#b91c1c')+'">'+(p13?'✓ Piste 25':'⚠ Piste 01')+'</div>'
        + '</div>'
      + '</div>'
    + '</div>'

    + (illegal
      ? '<div style="background:#fff8ee;border:2px solid #FF9900;border-radius:7px;padding:10px 14px;margin-bottom:14px;color:#7a4400;font-size:.83rem">'
        + '⚖ <strong>Utilisation potentiellement illégale de la piste 01</strong> — Les normes de vent légales (AIP 2013) permettraient d\'utiliser la piste 25.</div>'
      : '')

    + '<table style="width:100%;border-collapse:collapse;background:#f8fafc;border-radius:7px;overflow:hidden;font-size:.82rem;margin-bottom:10px">'
      + '<thead><tr style="background:#e8f0f8">'
        + '<th style="padding:6px 10px;text-align:left;color:#0e3d6b">Piste</th>'
        + '<th style="padding:6px 10px;text-align:left;color:#0e3d6b">Vent arrière</th>'
        + '<th style="padding:6px 10px;text-align:left;color:#0e3d6b">Vent traversier</th>'
      + '</tr></thead><tbody>'
      + twRow('25L', d.components?.['25L'])
      + twRow('25R', d.components?.['25R'])
      + twRow('07L', d.components?.['07L'])
      + twRow('07R', d.components?.['07R'])
      + twRow('01',  d.components?.['01'])
      + twRow('19',  d.components?.['19'])
      + '</tbody></table>'

    + (d.temp !== null && d.temp !== undefined
      ? '<div style="font-size:.75rem;color:#888">🌡 Température: '+d.temp+'°C'
        + (d.qnh ? ' · QNH: '+d.qnh+' hPa' : '') + '</div>'
      : '');
}


</script>
<script>
window.pmhRenderCards = function() {
  var wrap = document.getElementById('pmh-cards');
  if (!wrap) return;
  var data = window.pmhData || [];
  if (!data.length) { wrap.innerHTML = ''; return; }
  var dirs = ['N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSO','SO','OSO','O','ONO','NO','NNO'];
  wrap.innerHTML = data.map(function(m, idx) {
    var t = new Date(m.time);
    var timeUTC = isNaN(t) ? m.time : t.toLocaleTimeString('fr-BE',{hour:'2-digit',minute:'2-digit',timeZone:'UTC'})+' UTC';
    var timeBE  = isNaN(t) ? '' : t.toLocaleTimeString('fr-BE',{hour:'2-digit',minute:'2-digit',timeZone:'Europe/Brussels'})+' (BE)';
    var dateStr = isNaN(t) ? '' : t.toLocaleDateString('fr-BE',{day:'2-digit',month:'2-digit',year:'numeric',timeZone:'UTC'});
    var ws = m.wspd_kt||m.wspd||0;
    var wg = m.wgst_kt||m.wgst_metar||m.wgst_irm||null;
    var wd = (m.wdir==='VRB'||!m.wdir)?null:parseInt(m.wdir);
    var wdTxt = wd!==null ? wd+'° '+dirs[Math.round(wd/22.5)%16] : 'Variable';
    var windTxt = wdTxt+' — '+ws+' kt'+(wg?' 💨 '+wg+' kt':'');
    var s13=m.aip2013||{}; var snow=m.aip_now||{};
    return '<div class="pmh-card">'
      +'<div class="pmh-card-head"><div>'
        +'<div class="pmh-card-time">'+timeUTC+'</div>'
        +'<div class="pmh-card-date">'+timeBE+' · '+dateStr+'</div>'
      +'</div></div>'
      +'<div class="pmh-card-body">'
        +'<div class="pmh-card-wind">🌬 '+windTxt+'</div>'
        +'<div class="pmh-card-aip"><div class="pmh-card-aip-label">AIP 2013</div>'+(s13.prs?'<span style="color:#1a7a4a;font-weight:700">✓ PRS</span>':'<span style="color:#c0392b;font-weight:700">✕ Hors PRS</span>')+' · '+(s13.runways||[]).join('/')+'</div>'
        +'<div class="pmh-card-aip"><div class="pmh-card-aip-label">AIP actuel</div>'+(snow.prs?'<span style="color:#1a7a4a;font-weight:700">✓ PRS</span>':'<span style="color:#c0392b;font-weight:700">✕ Hors PRS</span>')+' · '+(snow.runways||[]).join('/')+'</div>'
        +(m.divergence?'<div class="pmh-card-verdict" style="background:#fff0f0;color:#c00;grid-column:1/-1;padding:6px;border-radius:6px;font-weight:600">⚡ Divergence</div>':'')
        +'<div style="grid-column:1/-1"><button type="button" class="pmh-card-widget-btn" onclick="pmhOpenWidget('+idx+')">▶ Voir widget</button></div>'
      +'</div></div>';
  }).join('');
};
</script>
