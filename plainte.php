<?php
// plainte.php — Page de plainte rapide, partageable par lien ou email
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/membre/functions.php';
session_start();

// Pré-remplir la commune si membre connecté
$commune_prefill = '';
try {
    if (!empty($_SESSION['membre_id'])) {
        $db = getDB();
        $s = $db->prepare("SELECT commune FROM members WHERE id=?");
        $s->execute([$_SESSION['membre_id']]);
        $row = $s->fetch();
        $commune_prefill = htmlspecialchars($row['commune'] ?? '');
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Porter plainte — Nuisances aériennes Brussels Airport</title>
  <meta name="description" content="Portez plainte facilement contre les nuisances aériennes de Brussels Airport">
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333;min-height:100vh}
    /* Header */
    .pl-header{background:linear-gradient(135deg,#0e3d6b,#1673B2);color:#fff;padding:20px;text-align:center}
    .pl-logo{display:flex;align-items:center;justify-content:center;gap:12px;margin-bottom:8px}
    .pl-logo img{width:48px;height:48px;border-radius:50%;border:2px solid #FF9900}
    .pl-logo-name{font-size:1.2rem;font-weight:800}
    .pl-logo-name span{color:#FF9900}
    .pl-header-sub{font-size:.82rem;color:rgba(255,255,255,.7);line-height:1.5}
    /* Container */
    .pl-wrap{max-width:620px;margin:0 auto;padding:20px 16px 40px}
    /* Cards */
    .pl-card{background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.08);padding:22px 20px;margin-bottom:16px}
    .pl-card-title{font-size:.95rem;font-weight:800;color:#0e3d6b;margin-bottom:12px;display:flex;align-items:center;gap:8px}
    .pl-step-badge{display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:50%;background:#1673B2;color:#fff;font-size:.72rem;font-weight:800;flex-shrink:0}
    /* Commune */
    .pl-commune-wrap{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap}
    .pl-commune-wrap input{flex:1;padding:12px 14px;border:2px solid #dde4ed;border-radius:8px;font-size:.95rem;font-family:inherit;outline:none;min-width:160px}
    .pl-commune-wrap input:focus{border-color:#1673B2}
    .pl-btn{padding:12px 20px;border-radius:8px;border:none;font-size:.88rem;font-weight:700;cursor:pointer;font-family:inherit;transition:all .18s}
    .pl-btn-orange{background:#FF9900;color:#fff;box-shadow:0 2px 8px rgba(255,153,0,.3)}
    .pl-btn-orange:hover{background:#e08800}
    .pl-btn-blue{background:#1673B2;color:#fff}
    .pl-btn-blue:hover{background:#125a90}
    .pl-btn-grey{background:#f0f4f8;color:#555;border:1.5px solid #dde4ed}
    .pl-btn-grey:hover{background:#e0e8f0}
    .pl-btn-full{width:100%;display:block;text-align:center;margin-top:10px}
    /* Piste selection */
    .pl-piste-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:4px}
    .pl-piste-btn{display:flex;flex-direction:column;align-items:center;gap:6px;padding:20px 16px;border:2px solid #dde4ed;border-radius:12px;background:#f8fafc;cursor:pointer;font-family:inherit;transition:all .15s}
    .pl-piste-btn:hover{border-color:#1673B2;background:#eef5fc}
    .pl-piste-btn.selected{border-color:#FF9900;background:#fff8ee}
    .pl-piste-icon{font-size:1.8rem;line-height:1}
    .pl-piste-label{font-size:1rem;font-weight:800;color:#0e3d6b}
    .pl-piste-sub{font-size:.72rem;color:#888}
    /* BATC details */
    details.pl-batc{margin-top:14px;border:1.5px solid #e2e8f0;border-radius:8px;overflow:hidden}
    details.pl-batc summary{font-size:.78rem;color:#1673B2;cursor:pointer;padding:9px 14px;background:#f8fafc;list-style:none;display:flex;align-items:center;gap:6px;user-select:none;font-weight:600}
    details.pl-batc summary::-webkit-details-marker{display:none}
    details.pl-batc summary::before{content:'▶';font-size:.6rem;transition:transform .2s}
    details.pl-batc[open] summary::before{transform:rotate(90deg)}
    .pl-batc-body{padding:12px 14px;font-size:.8rem;color:#555;line-height:1.7;background:#fff;border-top:1px solid #e2e8f0}
    .pl-batc-body a{color:#1673B2;font-weight:700;text-decoration:none}
    .pl-batc-body a:hover{text-decoration:underline}
    /* Météo table */
    .pl-meteo-loading{text-align:center;padding:20px;color:#aaa;font-size:.85rem}
    .pl-prs-badge{display:inline-block;padding:4px 14px;border-radius:20px;font-size:.75rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;margin-bottom:12px}
    .pl-prs-on{background:#e8f8f0;color:#27ae60;border:1.5px solid #b2f0d0}
    .pl-prs-off{background:#fff0f0;color:#c0392b;border:1.5px solid #fca5a5}
    .pl-meteo-table{width:100%;border-collapse:collapse;font-size:.82rem;margin-bottom:10px}
    .pl-meteo-table tr:nth-child(even){background:#f8fafc}
    .pl-meteo-table td{padding:8px 10px;border-bottom:1px solid #f0f4f8}
    .pl-meteo-table td:first-child{font-weight:600;color:#555;width:55%}
    .pl-comp-ok{color:#27ae60;font-weight:700}
    .pl-comp-warn{color:#b45309;font-weight:700}
    .pl-comp-bad{color:#c0392b;font-weight:700}
    .pl-metar-raw{font-family:monospace;font-size:.75rem;background:#f0f4f8;border-radius:6px;padding:8px 10px;color:#555;word-break:break-all;margin-top:8px}
    /* Complaint box */
    .pl-complaint-box{background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:8px;padding:14px;font-size:.8rem;line-height:1.7;color:#444;white-space:pre-wrap;max-height:280px;overflow-y:auto;font-family:"Helvetica Neue",Arial,sans-serif;margin-bottom:14px}
    /* Actions */
    .pl-actions{display:flex;gap:10px;flex-wrap:wrap}
    .pl-actions .pl-btn{flex:1;min-width:140px;justify-content:center;display:flex;align-items:center;gap:6px}
    /* Commune display */
    .pl-commune-tag{display:inline-flex;align-items:center;gap:6px;background:#e6f1fb;color:#1673B2;padding:4px 12px;border-radius:20px;font-size:.78rem;font-weight:600;margin-bottom:10px}
    .pl-commune-change{font-size:.7rem;color:#888;text-decoration:underline;cursor:pointer;background:none;border:none;font-family:inherit}
    /* Hidden */
    .pl-hidden{display:none}
    @media(max-width:480px){
      .pl-piste-grid{grid-template-columns:1fr 1fr}
      .pl-piste-btn{padding:16px 10px}
      .pl-actions{flex-direction:column}
      .pl-actions .pl-btn{min-width:0}
    }
  </style>
</head>
<body>

<div class="pl-header">
  <div class="pl-logo">
    <img src="/assets/img/logo.png" alt="Ça suffit !">
    <div class="pl-logo-name">Ça suffit <span>!</span></div>
  </div>
  <div class="pl-header-sub">Porter plainte — Nuisances aériennes Brussels Airport<br>Piste 01 / Piste 07</div>
</div>

<div class="pl-wrap">

  <!-- ÉTAPE 1 : Commune -->
  <div class="pl-card" id="step-commune">
    <div class="pl-card-title"><span class="pl-step-badge">1</span> Votre commune</div>
    <p style="font-size:.82rem;color:#666;margin-bottom:14px;line-height:1.6">
      Indiquez votre commune pour personnaliser la plainte. Ce champ restera dans le texte généré.
    </p>
    <div class="pl-commune-wrap">
      <input type="text" id="commune-input" placeholder="ex: Kraainem, Wezembeek-Oppem…"
             value="<?= $commune_prefill ?>" autocomplete="off" maxlength="80">
      <button class="pl-btn pl-btn-orange" onclick="confirmCommune()">Continuer →</button>
    </div>
    <p id="commune-error" style="color:#c0392b;font-size:.75rem;margin-top:6px;display:none">Veuillez indiquer votre commune.</p>
  </div>

  <!-- ÉTAPE 2 : Sélection piste -->
  <div class="pl-card pl-hidden" id="step-piste">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
      <div class="pl-card-title" style="margin-bottom:0"><span class="pl-step-badge">2</span> Quelle piste observez-vous ?</div>
      <span class="pl-commune-tag" id="commune-display">
        📍 <span id="commune-label"></span>
        <button class="pl-commune-change" onclick="resetCommune()">modifier</button>
      </span>
    </div>
    <div class="pl-piste-grid">
      <button class="pl-piste-btn" id="btn-piste-01" onclick="selectPiste('01')">
        <span class="pl-piste-icon">↑</span>
        <span class="pl-piste-label">Piste 01</span>
        <span class="pl-piste-sub">vers le nord</span>
      </button>
      <button class="pl-piste-btn" id="btn-piste-07" onclick="selectPiste('07')">
        <span class="pl-piste-icon">→</span>
        <span class="pl-piste-label">Piste 07</span>
        <span class="pl-piste-sub">07L / 07R — vers l'est</span>
      </button>
    </div>
    <details class="pl-batc">
      <summary>🔗 Je ne suis pas sûr(e) de la piste — vérifier sur BATC</summary>
      <div class="pl-batc-body">
        Le site BATC affiche en temps réel la configuration des pistes en service à Brussels Airport.<br><br>
        <a href="https://www.batc.be/fr/pistes-en-usage/actuel-prevision" target="_blank" rel="noopener">
          Ouvrir BATC — Pistes en service ↗
        </a><br><br>
        Si vous voyez un avion <strong>passer vers le nord</strong> au-dessus de votre commune → Piste 01.<br>
        Si vous voyez un avion <strong>passer vers l'est</strong> → Piste 07.
      </div>
    </details>
  </div>

  <!-- ÉTAPE 2.5 : Avertissement si piste adaptée aux conditions -->
  <div class="pl-card pl-hidden" id="step-confirm" style="border:2px solid #FF9900">
    <div class="pl-card-title" style="color:#b45309"><span class="pl-step-badge" style="background:#FF9900">⚠</span> Vérification</div>
    <div id="confirm-msg" style="font-size:.85rem;line-height:1.7;color:#555;margin-bottom:16px"></div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <button class="pl-btn pl-btn-grey" onclick="cancelConfirm()" style="flex:1">← Revenir au choix de piste</button>
      <button class="pl-btn" onclick="proceedAnyway()"
        style="flex:1;background:#e53e3e;color:#fff;border-color:#e53e3e">
        Je souhaite quand même signaler ce cas
      </button>
    </div>
  </div>

  <!-- ÉTAPE 3 : Météo + plainte -->
  <div class="pl-card pl-hidden" id="step-result">
    <div class="pl-card-title"><span class="pl-step-badge">3</span> Conditions météo &amp; plainte</div>

    <div id="meteo-loading" class="pl-meteo-loading">⏳ Récupération des données météo EBBR…</div>
    <div id="meteo-content" class="pl-hidden">
      <div id="prs-badge" class="pl-prs-badge"></div>
      <table class="pl-meteo-table" id="meteo-table"></table>
      <div id="metar-raw" class="pl-metar-raw"></div>
    </div>

    <div id="meteo-error" class="pl-hidden" style="color:#c0392b;font-size:.82rem;padding:12px;background:#fde8e8;border-radius:8px;margin-bottom:12px">
      ⚠ Impossible de récupérer les données météo. La plainte sera générée sans données en temps réel.
    </div>

    <!-- Capture visuelle du tableau météo -->
    <div id="pl-capture-wrap" class="pl-hidden" style="margin-bottom:14px">
      <div style="font-size:.8rem;font-weight:700;color:#0e3d6b;margin-bottom:6px">📸 Capture du tableau (jointe à la plainte) :</div>
      <div id="pl-capture-loading" style="font-size:.78rem;color:#aaa;padding:8px">⏳ Capture en cours…</div>
      <img id="pl-capture-img" style="display:none;max-width:100%;border:1px solid #dde4ed;border-radius:8px" alt="Capture conditions EBBR">
    </div>

    <div id="complaint-wrap" class="pl-hidden">
      <div style="font-size:.8rem;font-weight:700;color:#0e3d6b;margin:16px 0 8px">Texte de la plainte :</div>
      <div class="pl-complaint-box" id="complaint-text"></div>
      <div class="pl-actions">
        <button class="pl-btn pl-btn-grey" onclick="copyComplaint()">📋 Copier le texte</button>
        <button class="pl-btn pl-btn-orange" id="mailto-btn" onclick="openMail()">✉ Ouvrir email pré-adressé</button>
      </div>
      <p style="font-size:.7rem;color:#aaa;margin-top:10px;line-height:1.5">
        Destinataire : Médiateur aérien fédéral (<a href="https://airportmediation.be/fr" target="_blank" style="color:#aaa">airportmediation.be</a>)
      </p>
    </div>
  </div>

  <div style="text-align:center;margin-top:8px">
    <a href="/" style="font-size:.75rem;color:#aaa;text-decoration:none">← Retour au site casuffit.be</a>
  </div>

</div>

<script>
var _commune = '';
var _piste = '';
var _metar = null;
var _plainText = '';
var _captureDataUrl = null;
var _dest = 'airportmediation@mobilit.fgov.be';

function confirmCommune() {
  var val = document.getElementById('commune-input').value.trim();
  if (!val) {
    document.getElementById('commune-error').style.display = 'block';
    return;
  }
  document.getElementById('commune-error').style.display = 'none';
  _commune = val;
  document.getElementById('commune-label').textContent = val;
  document.getElementById('step-commune').classList.add('pl-hidden');
  document.getElementById('step-piste').classList.remove('pl-hidden');
  document.getElementById('commune-input').addEventListener('keypress', function(e){ if(e.key==='Enter') confirmCommune(); });
}

function resetCommune() {
  document.getElementById('step-commune').classList.remove('pl-hidden');
  document.getElementById('step-piste').classList.add('pl-hidden');
  document.getElementById('step-result').classList.add('pl-hidden');
  document.getElementById('commune-input').focus();
}

document.getElementById('commune-input').addEventListener('keypress', function(e){
  if(e.key==='Enter') confirmCommune();
});
<?php if ($commune_prefill): ?>
// Pré-remplir si membre connecté
window.addEventListener('DOMContentLoaded', function(){
  confirmCommune();
});
<?php endif; ?>

function selectPiste(piste) {
  _piste = piste;
  document.getElementById('btn-piste-01').classList.toggle('selected', piste==='01');
  document.getElementById('btn-piste-07').classList.toggle('selected', piste==='07');
  document.getElementById('step-result').classList.remove('pl-hidden');
  document.getElementById('step-result').scrollIntoView({behavior:'smooth',block:'start'});
  fetchMetar();
}

function fetchMetar() {
  document.getElementById('meteo-loading').style.display = 'block';
  document.getElementById('meteo-content').classList.add('pl-hidden');
  document.getElementById('meteo-error').classList.add('pl-hidden');
  document.getElementById('complaint-wrap').classList.add('pl-hidden');

  fetch('/api/metar.php?_='+Date.now())
    .then(function(r){ return r.json(); })
    .then(function(d){
      _metar = d;
      document.getElementById('meteo-loading').style.display = 'none';
      checkJustification(d);
    })
    .catch(function(){
      _metar = null;
      document.getElementById('meteo-loading').style.display = 'none';
      document.getElementById('meteo-error').classList.remove('pl-hidden');
      showResult(null); // pas de données, on laisse passer
    });
}

function compClass(val, warn, bad) {
  if (val >= bad) return 'pl-comp-bad';
  if (val >= warn) return 'pl-comp-warn';
  return 'pl-comp-ok';
}

function renderMeteo(d) {
  var comps = d.components || {};
  var c25R = comps['25R'] || {};
  var c25L = comps['25L'] || {};

  // Badge PRS
  var badge = document.getElementById('prs-badge');
  if (d.prs_active) {
    badge.className = 'pl-prs-badge pl-prs-on';
    badge.textContent = '✅ PRS actif — piste 25 requise';
  } else {
    badge.className = 'pl-prs-badge pl-prs-off';
    badge.textContent = '⛔ HORS PRS';
  }

  // Table météo
  var tw25R = (c25R.tw||0).toFixed(1);
  var tw25Rg = c25R.tw_g!==null&&c25R.tw_g!==undefined ? (c25R.tw_g||0).toFixed(1) : null;
  var xw25R  = (c25R.xw||0).toFixed(1);
  var tw25L  = (c25L.tw||0).toFixed(1);

  var rows = [
    ['METAR EBBR', '<span style="font-family:monospace;font-size:.75rem">'+(d.metar||'—')+'</span>'],
    ['Vent moyen', (d.wdir?d.wdir+'°':d.variable?'Variable':'—')+' / '+(d.wspd||'—')+' kt'],
    ['Rafales', d.wgst ? d.wgst+' kt'+(d.wgst_irm?' (IRM: '+d.wgst_irm+' kt)':'') : '—'],
    ['Vent arrière 25R', '<span class="'+compClass(parseFloat(tw25R),5,7)+'">'+tw25R+' kt</span> <small style="color:#aaa">(seuil légal : 7 kt)</small>'],
    ['Vent arrière 25L', '<span class="'+compClass(parseFloat(tw25L),5,7)+'">'+tw25L+' kt</span>'],
    ['Vent latéral 25R', '<span class="'+compClass(parseFloat(xw25R),10,15)+'">'+xw25R+' kt</span> <small style="color:#aaa">(seuil légal : 15 kt)</small>'],
  ];
  if (tw25Rg) rows.splice(4,0,['Rafale arrière 25R','<span class="'+compClass(parseFloat(tw25Rg),7,10)+'">'+tw25Rg+' kt</span> <small style="color:#aaa">(seuil légal : 10 kt)</small>']);

  var html = rows.map(function(r){
    return '<tr><td>'+r[0]+'</td><td>'+r[1]+'</td></tr>';
  }).join('');
  document.getElementById('meteo-table').innerHTML = html;
}

function buildComplaint(d) {
  var pisteLabel = _piste==='07' ? 'piste 07 (07L/07R)' : 'piste 01';
  var communeText = _commune ? ', habitant(e) de '+_commune+',' : ',';

  var now = new Date();
  var dateStr = now.toLocaleDateString('fr-BE',{day:'2-digit',month:'2-digit',year:'numeric'});
  var obsTimeStr = '—', obsDateStr = dateStr, metatLine = '—';
  var tw25Rv=0, tw25Lv=0, xw25Rv=0, tw25Rg=null;
  var tw25R='—', tw25L='—', xw25R='—';
  var prsActive = null;

  if (d) {
    var comps = d.components || {};
    var c25R = comps['25R']||{};
    var c25L = comps['25L']||{};
    var c01  = comps['01'] ||{};
    var c07R = comps['07R']||{};
    tw25Rv = c25R.tw||0; tw25Lv = c25L.tw||0; xw25Rv = c25R.xw||0;
    tw25R = tw25Rv.toFixed(1)+' kt';
    tw25L = tw25Lv.toFixed(1)+' kt';
    xw25R = xw25Rv.toFixed(1)+' kt';
    if (c25R.tw_g!==null&&c25R.tw_g!==undefined) tw25Rg = (c25R.tw_g||0).toFixed(1)+' kt';
    metatLine = d.metar||'—';
    prsActive = d.prs_active;
    if (d.obs_time) {
      var obs = new Date(d.obs_time);
      obsDateStr = obs.toLocaleDateString('fr-BE',{day:'2-digit',month:'2-digit',year:'numeric'});
      obsTimeStr = obs.toLocaleTimeString('fr-BE',{hour:'2-digit',minute:'2-digit',timeZone:'UTC'})+' UTC';
    }
    // Pour le cas Hors PRS, trouver la piste alternative avec le meilleur vent de face
    var hw01  = c01.hw  || 0;
    var hw07R = c07R.hw || 0;
    var _altPiste  = hw07R > hw01 ? 'piste 07' : 'piste 01';
    var _altHw     = Math.max(hw01, hw07R).toFixed(1);
    var _obsPiste  = _piste === '07' ? 'piste 07' : 'piste 01';
    var _obsIsBetter = (_obsPiste === _altPiste);
  }

  // ── Construction du contexte selon l'état PRS ───────────────────────
  var contextLines = '';
  var demandeLines = '';

  if (prsActive === true) {
    // CAS 1 : PRS actif — piste 25 requise, mais on observe 01/07
    contextLines =
      'Les conditions météorologiques au moment de mon observation indiquent que les pistes 25'+
      ' constituent la configuration préférentielle selon le Plan de Répartition du Survol :\n'+
      '  • Vent arrière sur 25R : '+tw25R+' (seuil légal AIP 2013 : 7 kt)\n'+
      (tw25Rg ? '  • Rafale arrière 25R : '+tw25Rg+' (seuil légal : 10 kt)\n' : '')+
      '  • Vent arrière sur 25L : '+tw25L+'\n'+
      '  • Analyse PRS (casuffit.be) : PRS actif — pistes 25 requises';
    demandeLines =
      'Selon l\'instruction ministérielle du 17/07/2013 (AIP EBBR AD 2.21), les pistes 25'+
      ' doivent être utilisées en priorité dans ces conditions.\n\n'+
      'Je souhaiterais dès lors obtenir les raisons opérationnelles ou météorologiques'+
      ' qui ont justifié l\'utilisation de la '+pisteLabel+' plutôt que des pistes 25.';

  } else if (prsActive === false) {
    // CAS 2 : Hors PRS — piste 25 impossible (vent arrière trop élevé)
    // La question est : pourquoi cette piste plutôt que l'autre alternative ?
    contextLines =
      'Les conditions météorologiques indiquent que les pistes 25 ne peuvent pas être utilisées'+
      ' (vent arrière de '+tw25R+' sur 25R, dépassant le seuil légal de 7 kt). Hors PRS.\n\n'+
      'Avec un vent de '+(d?d.wdir||'—':'—')+'° à '+(d?d.wspd||'—':'—')+' kt,'+
      (_obsIsBetter
        ? ' la '+pisteLabel+' (vent de face '+_altHw+' kt) est la configuration la plus adaptée aux conditions.'
        : ' la '+_altPiste+' présenterait un vent de face de '+_altHw+' kt, ce qui en ferait une configuration alternative plus adaptée que la '+pisteLabel+'.')+
      '\n  • Vent arrière sur 25R : '+tw25R+' (seuil légal : 7 kt) → piste 25 impossible'+
      '\n  • Analyse PRS (casuffit.be) : HORS PRS';
    demandeLines = _obsIsBetter
      ? 'Je souhaiterais obtenir une confirmation de la configuration en service et une information'+
        ' sur les mesures prises pour limiter les nuisances dans ce contexte météorologique.'
      : 'Je souhaiterais obtenir les raisons opérationnelles qui ont conduit à utiliser la '+
        pisteLabel+' plutôt que la '+_altPiste+' dans ces conditions.';

  } else {
    // CAS 3 : pas de données météo
    contextLines = 'Je n\'ai pas pu obtenir les données météo en temps réel au moment de mon observation.';
    demandeLines =
      'Je souhaiterais obtenir les raisons opérationnelles ou météorologiques'+
      ' qui ont justifié l\'utilisation de la '+pisteLabel+' à ce moment.';
  }

  _plainText =
    'Madame, Monsieur,\n\n'+
    'Je me permets de vous contacter afin de vous signaler qu\'en date du '+obsDateStr+
    ' vers '+obsTimeStr+', j\'ai observé'+communeText+' un usage de la '+pisteLabel+
    ' à l\'aéroport de Bruxelles-National (EBBR).\n\n'+
    '=== CONDITIONS MÉTÉO EBBR ===\n'+
    'METAR               : '+metatLine+'\n'+
    'Date / Heure (obs.) : '+obsDateStr+' à '+obsTimeStr+'\n'+
    'Vent arrière 25R    : '+tw25R+' (seuil légal AIP 2013 : 7 kt)\n'+
    'Vent arrière 25L    : '+tw25L+'\n'+
    'Vent latéral 25R    : '+xw25R+' (seuil légal : 15 kt)\n'+
    (tw25Rg ? 'Rafale arrière 25R  : '+tw25Rg+' (seuil légal : 10 kt)\n' : '')+
    '\n=== ANALYSE ===\n'+
    contextLines+'\n\n'+
    demandeLines+'\n\n'+
    'Je vous remercie de l\'attention portée à ce message et reste disponible pour tout'+
    ' complément d\'information.\n\n'+
    'Cordialement,\n\n'+
    '— Via Ça suffit ! ASBL — casuffit.be';

  document.getElementById('complaint-text').textContent = _plainText;
}

function checkJustification(d) {
  if (!d) { showResult(d); return; }
  if (d.prs_active) { showResult(d); return; } // PRS actif → plainte justifiée
  // Hors PRS : la piste 25 ne peut pas être utilisée
  // Vérifier si la piste observée est la meilleure alternative
  var comps = d.components || {};
  var hw01  = (comps['01']  || {}).hw || 0;
  var hw07R = (comps['07R'] || {}).hw || 0;
  var bestAlt = hw07R > hw01 ? '07' : '01'; // piste avec le meilleur vent de face
  if (_piste === bestAlt) {
    // La piste observée EST la plus adaptée → demander confirmation
    var pisteLabel  = _piste === '07' ? 'piste 07' : 'piste 01';
    var altLabel    = bestAlt === '07' ? 'piste 07 (07L/07R)' : 'piste 01';
    var altHw       = Math.max(hw01, hw07R).toFixed(1);
    var tw25R       = (comps['25R'] ? comps['25R'].tw || 0 : 0).toFixed(1);
    var windDesc    = (d.wdir ? d.wdir+'°' : '—') + ' / ' + (d.wspd || '—') + ' kt';
    document.getElementById('confirm-msg').innerHTML =
      '<strong>D\'après les conditions météo actuelles, la '+pisteLabel+' est la configuration la plus adaptée.</strong><br><br>'+
      '<ul style="margin:8px 0 8px 18px;line-height:2">'+
      '<li>Vent : '+windDesc+'</li>'+
      '<li>Vent arrière sur piste 25R : <strong>'+tw25R+' kt</strong> (seuil légal 7 kt → piste 25 <strong>non utilisable</strong>)</li>'+
      '<li>Vent de face sur '+altLabel+' : <strong>'+altHw+' kt</strong> → configuration appropriée</li>'+
      '</ul>'+
      '<em>Porter plainte dans ce cas pourrait nuire à la crédibilité de votre démarche. '+
      'Si vous avez d\'autres raisons de vous plaindre (bruit, fréquence, horaires…), vous pouvez quand même continuer.</em>';
    document.getElementById('step-confirm').classList.remove('pl-hidden');
    document.getElementById('step-confirm').scrollIntoView({behavior:'smooth',block:'start'});
    document.getElementById('step-result').classList.add('pl-hidden');
  } else {
    showResult(d); // piste observée n'est pas la meilleure → plainte justifiée
  }
}

function showResult(d) {
  document.getElementById('step-confirm').classList.add('pl-hidden');
  document.getElementById('step-result').classList.remove('pl-hidden');
  if (d) {
    renderMeteo(d);
    document.getElementById('meteo-content').classList.remove('pl-hidden');
  }
  buildComplaint(d);
  document.getElementById('complaint-wrap').classList.remove('pl-hidden');
  document.getElementById('step-result').scrollIntoView({behavior:'smooth',block:'start'});
  // Déclencher la capture html2canvas du tableau météo
  doCapture();
}

function doCapture() {
  var captureWrap = document.getElementById('pl-capture-wrap');
  var loadEl  = document.getElementById('pl-capture-loading');
  var imgEl   = document.getElementById('pl-capture-img');
  captureWrap.classList.remove('pl-hidden');
  _captureDataUrl = null;

  function runCapture() {
    var el = document.getElementById('meteo-content');
    if (!el || typeof html2canvas === 'undefined') {
      loadEl.textContent = '⚠ Capture non disponible — le tableau sera inclus sans image.';
      return;
    }
    html2canvas(el, {scale:2, useCORS:true, backgroundColor:'#ffffff', logging:false})
      .then(function(canvas) {
        _captureDataUrl = canvas.toDataURL('image/png');
        imgEl.src = _captureDataUrl;
        imgEl.style.display = 'block';
        loadEl.style.display = 'none';
      })
      .catch(function(err) {
        loadEl.textContent = '⚠ Capture non disponible (' + (err.message||'erreur') + ').';
      });
  }

  if (typeof html2canvas !== 'undefined') {
    runCapture();
  } else {
    var s = document.createElement('script');
    s.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
    s.onload = runCapture;
    s.onerror = function(){ loadEl.textContent = '⚠ Impossible de charger la librairie de capture.'; };
    document.head.appendChild(s);
  }
}

function cancelConfirm() {
  document.getElementById('step-confirm').classList.add('pl-hidden');
  document.getElementById('step-result').classList.add('pl-hidden');
  // Dé-sélectionner la piste
  document.getElementById('btn-piste-01').classList.remove('selected');
  document.getElementById('btn-piste-07').classList.remove('selected');
  document.getElementById('step-piste').scrollIntoView({behavior:'smooth',block:'start'});
}

function proceedAnyway() {
  document.getElementById('step-confirm').classList.add('pl-hidden');
  showResult(_metar);
}

function copyComplaint() {
  var btn = event.currentTarget;
  var orig = btn.textContent;
  var htmlBody = buildHtmlBody(); // inclut l'image si _captureDataUrl est défini

  function copyOk() {
    btn.textContent = '✓ Copié ! Collez dans votre email';
    setTimeout(function(){ btn.textContent = orig; }, 4000);
  }
  function copyFallback() {
    navigator.clipboard.writeText(_plainText).then(copyOk).catch(function(){
      // Dernier recours : sélection manuelle
      var ta = document.createElement('textarea');
      ta.value = _plainText; ta.style.position='fixed'; ta.style.opacity='0';
      document.body.appendChild(ta); ta.select(); document.execCommand('copy');
      document.body.removeChild(ta); copyOk();
    });
  }
  if (navigator.clipboard && window.ClipboardItem) {
    try {
      var item = new ClipboardItem({
        'text/html': new Blob([htmlBody], {type:'text/html'}),
        'text/plain': new Blob([_plainText], {type:'text/plain'})
      });
      navigator.clipboard.write([item]).then(copyOk).catch(copyFallback);
    } catch(e) { copyFallback(); }
  } else {
    copyFallback();
  }
}

function buildHtmlBody() {
  var d = _metar;
  var pisteLabel = _piste==='07' ? 'piste 07 (07L/07R)' : 'piste 01';
  var communeText = _commune ? ', habitant(e) de <strong>'+_commune+'</strong>,' : ',';
  var now = new Date();
  var obsTimeStr='—', obsDateStr=now.toLocaleDateString('fr-BE',{day:'2-digit',month:'2-digit',year:'numeric'});
  var tw25R='—',tw25L='—',xw25R='—',tw25Rg=null,metatLine='—',tw25Rv=0;
  var prsActive=null, altPiste='', altHw='', obsBetter=false, prsLine='';
  if (d) {
    var comps=d.components||{}; var c25R=comps['25R']||{}; var c25L=comps['25L']||{};
    var c01=comps['01']||{}; var c07R=comps['07R']||{};
    tw25Rv=c25R.tw||0;
    tw25R=tw25Rv.toFixed(1)+' kt'; tw25L=(c25L.tw||0).toFixed(1)+' kt';
    xw25R=(c25R.xw||0).toFixed(1)+' kt';
    if(c25R.tw_g!==null&&c25R.tw_g!==undefined) tw25Rg=(c25R.tw_g||0).toFixed(1)+' kt';
    metatLine=d.metar||'—'; prsActive=d.prs_active;
    altPiste=(c07R.hw||0)>(c01.hw||0)?'piste 07':'piste 01';
    altHw=Math.max(c07R.hw||0,c01.hw||0).toFixed(1);
    obsBetter=(_piste==='07'?'piste 07':'piste 01')===altPiste;
    if(d.obs_time){var obs=new Date(d.obs_time);
      obsDateStr=obs.toLocaleDateString('fr-BE',{day:'2-digit',month:'2-digit',year:'numeric'});
      obsTimeStr=obs.toLocaleTimeString('fr-BE',{hour:'2-digit',minute:'2-digit',timeZone:'UTC'})+' UTC';}
    prsLine='<tr><td style="padding:8px 12px;font-weight:bold">Analyse PRS</td><td style="padding:8px 12px">'+(prsActive?'<span style="color:#27ae60;font-weight:bold">✅ PRS actif — pistes 25 requises</span>':'<span style="color:#c0392b;font-weight:bold">⛔ HORS PRS — pistes 25 impossibles</span>')+'</td></tr>';
  }
  var analyseHtml = prsActive === true
    ? '<p>Les conditions météo indiquent que les pistes 25 constituent la configuration préférentielle'+
      ' (vent arrière 25R : <strong>'+tw25R+'</strong> — seuil légal AIP 2013 : 7 kt).</p>'+
      '<p>Selon l\'instruction ministérielle du 17/07/2013 (AIP EBBR AD 2.21), les pistes 25 doivent'+
      ' être utilisées prioritairement dans ces conditions.</p>'+
      '<p>Je souhaiterais obtenir les raisons opérationnelles qui ont justifié l\'utilisation de la <strong>'+pisteLabel+'</strong> plutôt que des pistes 25.</p>'
    : prsActive === false
      ? '<p>Les conditions actuelles ne permettent pas d\'utiliser les pistes 25 (vent arrière de <strong>'+tw25R+'</strong> sur 25R — seuil légal : 7 kt). La configuration HORS PRS s\'applique.</p>'+
        (obsBetter
          ? '<p>La <strong>'+pisteLabel+'</strong> est la configuration la plus adaptée aux conditions actuelles. Je souhaite être informé(e) des mesures prises pour limiter les nuisances.</p>'
          : '<p>Avec ce vent ('+( d?d.wdir+'°':'')+'), la <strong>'+altPiste+'</strong> (vent de face '+altHw+' kt) serait la configuration alternative la plus adaptée. Je souhaiterais obtenir les raisons du choix de la '+pisteLabel+' à la place.</p>')
      : '<p>Je souhaiterais obtenir les raisons opérationnelles ou météorologiques qui ont justifié l\'utilisation de la <strong>'+pisteLabel+'</strong>.</p>';

  return '<div style="font-family:Arial,sans-serif;color:#333;max-width:680px">'+
    '<p>Madame, Monsieur,</p>'+
    '<p>Je me permets de vous contacter afin de vous signaler qu\'en date du '+obsDateStr+' vers '+obsTimeStr+', j\'ai observé'+communeText+' un usage de la <strong>'+pisteLabel+'</strong> à l\'aéroport de Bruxelles-National (EBBR).</p>'+
    '<h3 style="color:#0e3d6b;border-bottom:2px solid #0e3d6b;padding-bottom:6px;margin-top:20px">Conditions météo EBBR</h3>'+
    '<table style="width:100%;border-collapse:collapse;font-size:.9em">'+
    '<tr style="background:#f0f4f8"><td style="padding:8px 12px;font-weight:bold">METAR</td><td style="padding:8px 12px;font-family:monospace;font-size:.85em">'+metatLine+'</td></tr>'+
    '<tr><td style="padding:8px 12px;font-weight:bold">Date / Heure obs.</td><td style="padding:8px 12px">'+obsDateStr+' à '+obsTimeStr+'</td></tr>'+
    '<tr style="background:#f0f4f8"><td style="padding:8px 12px;font-weight:bold">Vent arrière 25R</td><td style="padding:8px 12px">'+tw25R+' <small style="color:#888">(seuil légal : 7 kt)</small></td></tr>'+
    '<tr><td style="padding:8px 12px;font-weight:bold">Vent arrière 25L</td><td style="padding:8px 12px">'+tw25L+'</td></tr>'+
    '<tr style="background:#f0f4f8"><td style="padding:8px 12px;font-weight:bold">Vent latéral 25R</td><td style="padding:8px 12px">'+xw25R+' <small style="color:#888">(seuil légal : 15 kt)</small></td></tr>'+
    (tw25Rg ? '<tr><td style="padding:8px 12px;font-weight:bold">Rafale arrière 25R</td><td style="padding:8px 12px">'+tw25Rg+' <small style="color:#888">(seuil légal : 10 kt)</small></td></tr>' : '')+
    prsLine+
    '</table>'+
    '<h3 style="color:#0e3d6b;border-bottom:2px solid #0e3d6b;padding-bottom:6px;margin-top:20px">Analyse et demande</h3>'+
    analyseHtml+
    '<p>Je vous remercie de l\'attention portée à ce message et reste disponible pour tout complément d\'information.</p>'+
    '<p>Cordialement,</p>'+
    (_captureDataUrl ? '<p><img src="'+_captureDataUrl+'" style="max-width:100%;border:1px solid #ddd;border-radius:8px;margin-top:12px" alt="Capture conditions EBBR"></p>' : '')+
    '<p style="font-size:.8em;color:#888">— Via Ça suffit ! ASBL — casuffit.be</p>'+
    '</div>';
}

function openMail() {
  var pisteLabel = _piste==='07' ? 'piste 07' : 'piste 01';
  var now = new Date();
  var dateStr = now.toLocaleDateString('fr-BE',{day:'2-digit',month:'2-digit',year:'numeric'});
  var subj = 'Plainte nuisance aérienne EBBR — '+pisteLabel+' — '+dateStr;
  window.location.href = 'mailto:'+_dest+'?subject='+encodeURIComponent(subj)+'&body='+encodeURIComponent(_plainText);
}
</script>

</body>
</html>
