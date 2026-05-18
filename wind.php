<?php
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
// Page standalone PWA — widget météo EBBR — page publique
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>EBBR Wind — ça suffit !</title>
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="EBBR Wind">
  <meta name="theme-color" content="#0e3d6b">
  <link rel="apple-touch-icon" sizes="180x180" href="/assets/img/wind-icon-180.png">
  <link rel="manifest" href="/wind-manifest.json">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --safe-top: env(safe-area-inset-top, 0px);
      --safe-bot: env(safe-area-inset-bottom, 0px);
      --blue: #0e3d6b;
      --orange: #F5A623;
    }
    html, body {
      height: 100%; width: 100%;
      font-family: -apple-system, 'Helvetica Neue', Arial, sans-serif;
      background: var(--blue);
      overflow: hidden;
      display: flex;
      flex-direction: column;
    }

    /* ── Header ─────────────────────────────── */
    .app-statusbar {
      background: var(--blue);
      height: var(--safe-top);
      flex-shrink: 0;
    }
    .app-header {
      height: 48px;
      background: var(--blue);
      display: flex; align-items: center; justify-content: space-between;
      padding: 0 16px;
      border-bottom: 1px solid rgba(255,255,255,.12);
      flex-shrink: 0;
    }
    .app-title {
      font-size: .95rem; font-weight: 700; color: #fff;
      display: flex; align-items: center; gap: 8px;
    }
    .app-title img { width: 28px; height: 28px; border-radius: 6px; }
    .app-title span { color: var(--orange); }
    .app-refresh {
      background: rgba(255,255,255,.15); border: none;
      border-radius: 20px; color: #fff; font-size: .78rem;
      padding: 5px 12px; cursor: pointer; font-family: inherit; font-weight: 600;
    }
    .app-refresh:active { background: rgba(255,255,255,.3); }

    /* ── Corps scrollable ────────────────────── */
    .app-body {
      flex: 1;
      overflow-y: auto;
      -webkit-overflow-scrolling: touch;
      background: #f0f4f8;
      min-height: 0;
    }

    /* ── Mode d'emploi installation ─────────── */
    .install-guide {
      display: none;
      background: #fff;
      border-radius: 14px;
      margin: 16px;
      padding: 20px;
      box-shadow: 0 4px 20px rgba(0,0,0,.10);
    }
    .install-guide-title {
      font-size: 1rem; font-weight: 800; color: var(--blue);
      margin-bottom: 4px; display: flex; align-items: center; gap: 8px;
    }
    .install-guide-sub {
      font-size: .8rem; color: #888; margin-bottom: 18px;
    }
    .install-steps { list-style: none; }
    .install-steps li {
      display: flex; align-items: flex-start; gap: 12px;
      padding: 10px 0; border-bottom: 1px solid #f0f0f0; font-size: .88rem; color: #333;
    }
    .install-steps li:last-child { border-bottom: none; }
    .install-step-num {
      flex-shrink: 0;
      width: 28px; height: 28px; border-radius: 50%;
      background: var(--blue); color: #fff;
      font-size: .78rem; font-weight: 800;
      display: flex; align-items: center; justify-content: center;
    }
    .install-step-txt { line-height: 1.5; }
    .install-step-txt strong { display: block; font-weight: 700; color: var(--blue); }
    .install-icon-share {
      display: inline-block;
      background: #007AFF; color: #fff;
      border-radius: 5px; padding: 2px 7px; font-size: .75rem; font-weight: 700;
      vertical-align: middle; margin: 0 2px;
    }
    .install-icon-add {
      display: inline-block;
      background: #f0f0f0; color: #333;
      border-radius: 5px; padding: 2px 7px; font-size: .75rem;
      vertical-align: middle; margin: 0 2px;
    }
    .install-note {
      margin-top: 14px; padding: 10px 12px;
      background: #fff8ee; border-radius: 8px; border-left: 3px solid var(--orange);
      font-size: .78rem; color: #7a5200; line-height: 1.5;
    }
    .install-dismiss {
      display: block; width: 100%; margin-top: 14px;
      padding: 11px; background: var(--blue); color: #fff;
      border: none; border-radius: 10px; font-size: .9rem; font-weight: 700;
      cursor: pointer; font-family: inherit;
    }
    .install-dismiss:active { background: #0a2d54; }

    .app-footer {
      padding: 14px 16px 8px;
    }
    .app-nav-btns {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
      margin-bottom: 12px;
    }
    .app-nav-btn {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      padding: 12px 8px;
      background: #fff;
      border: 2px solid #1673B2;
      border-radius: 10px;
      color: #1673B2;
      font-size: .82rem;
      font-weight: 700;
      text-decoration: none;
      text-align: center;
      transition: all .15s;
    }
    .app-nav-btn:active { background: #e8f0fa; }
    .app-nav-btn-rose {
      border-color: #F5A623;
      color: #c97200;
    }
    .app-nav-btn-rose:active { background: #fff8ee; }
    .app-footer-links {
      text-align: center;
      font-size: .72rem;
      color: #aaa;
    }
    .app-footer-links a { color: #1673B2; text-decoration: none; }

    /* ── Navigation bas d'app ── */
    .app-nav-bar {
      background: #0e3d6b;
      border-top: 1px solid rgba(255,255,255,.15);
      display: flex;
      flex-shrink: 0;
      padding-bottom: 0;
    }
    .app-nav-bar-btn {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 2px;
      padding: 6px 4px;
      background: none;
      border: none;
      cursor: pointer;
      font-family: inherit;
      font-size: .62rem;
      color: rgba(255,255,255,.5);
      font-weight: 600;
      transition: color .15s;
    }
    .app-nav-bar-btn .nav-icon { font-size: 1.15rem; }
    .app-nav-bar-btn.active { color: #fff; }
    .app-nav-bar-btn.active-rose { color: #F5A623; }

    /* ── Vues ── */
    .app-view { display: none; }
    .app-view.active { display: block; min-height: 100%; padding: 12px; }
    #view-historique.active { padding: 0; }


    /* Footer liens discrets */
    .app-footer-links {
      text-align: center;
      font-size: .68rem;
      color: #aaa;
      padding: 8px 0 4px;
    }
    .app-footer-links a { color: #1673B2; text-decoration: none; }
  /* ── CSS widget Historique de vent (injecté pour Safari iPhone) ── */

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

/* ── Mobile iPhone ────────────────────────────────────────────── */
@media (max-width: 500px) {
  .pmh { font-size: 12px; }
  .pmh-table-wrap { -webkit-overflow-scrolling: touch; }
  .pmh-table { font-size: .72rem; min-width: 600px; }
  .pmh-table th, .pmh-table td { padding: 5px 5px; }
  /* Cacher colonnes secondaires sur iPhone */
  .pmh-table th:nth-child(9), .pmh-table td:nth-child(9),
  .pmh-table th:nth-child(3), .pmh-table td:nth-child(3) { display: none; }
  /* Modale plein écran sur mobile */
  .pmh-wmodal-bg { align-items: flex-end; }
  .pmh-wmodal { width: 100%; max-width: 100%; border-radius: 16px 16px 0 0;
    max-height: 85vh; }
  .pmh-widget-btn { padding: 4px 8px; font-size: .7rem; }
  .pmh-row-btns { flex-wrap: wrap; gap: 2px; }
  .pmh-row-btn { font-size: .62rem; padding: 2px 4px; }
  /* Barre de scroll visible */
  .pmh-scroll-hint { display: block; }
}
.pmh-scroll-hint { display: none; text-align: center; font-size: .7rem; color: #aaa;
  padding: 4px; }

.pmh-wmodal-head { padding: 12px 18px; border-bottom: 1px solid #eee;
  display: flex; align-items: center; justify-content: space-between; background: #0e3d6b; border-radius: 12px 12px 0 0; }
.pmh-wmodal-head h3 { margin: 0; color: #fff; font-size: .95rem; }
.pmh-wmodal-close { background: none; border: none; font-size: 1.4rem; cursor: pointer; color: #fff; line-height: 1; }
.pmh-wmodal-body { padding: 18px; }
.pmh-stats-note { font-size: .72rem; color: #888; margin-top: 8px; text-align: right; }

  </style>
</head>
<body>

<div class="app-statusbar"></div>

<header class="app-header">
  <div class="app-title">
    <img src="/assets/img/wind-icon-180.png" alt="">
    <span>ça suffit !</span>&nbsp;Wind
  </div>
  <button class="app-refresh" onclick="location.reload()">↺ Actualiser</button>
</header>

<main class="app-body">

  <!-- Mode d'emploi — visible uniquement hors standalone -->
  <div class="install-guide" id="install-guide">
    <div class="install-guide-title">📲 Installer l'application</div>
    <div class="install-guide-sub">Accédez au widget météo en un tap depuis votre écran d'accueil</div>

    <ul class="install-steps">
      <li>
        <div class="install-step-num">1</div>
        <div class="install-step-txt">
          <strong>Ouvrir dans Safari</strong>
          Cette page doit être ouverte dans <b>Safari</b> (pas Chrome ni Firefox) pour pouvoir être installée sur iPhone.
        </div>
      </li>
      <li>
        <div class="install-step-num">2</div>
        <div class="install-step-txt">
          <strong>Appuyer sur Partager</strong>
          En bas de l'écran, appuie sur le bouton <span class="install-icon-share">⬆ Partager</span>
        </div>
      </li>
      <li>
        <div class="install-step-num">3</div>
        <div class="install-step-txt">
          <strong>Sur l'écran d'accueil</strong>
          Dans le menu qui s'ouvre, fais défiler et appuie sur <span class="install-icon-add">＋ Sur l'écran d'accueil</span>
        </div>
      </li>
      <li>
        <div class="install-step-num">4</div>
        <div class="install-step-txt">
          <strong>Confirmer</strong>
          Le nom "EBBR Wind" est déjà rempli. Appuie sur <b>Ajouter</b> en haut à droite.
        </div>
      </li>
      <li>
        <div class="install-step-num">5</div>
        <div class="install-step-txt">
          <strong>C'est installé !</strong>
          L'icône <b>ça suffit ! Wind</b> apparaît sur votre écran d'accueil. Elle s'ouvre en plein écran comme une vraie app.
        </div>
      </li>
    </ul>

    <div class="install-note">
      ⚡ Une fois installée, l'app se rafraîchit automatiquement toutes les 5 minutes. Les données proviennent des METARs ICAO et de l'IRM station 6451.
    </div>

    <button class="install-dismiss" onclick="dismissGuide()">Continuer sans installer →</button>
  </div>

  <!-- Vue : Météo -->
  <div class="app-view active" id="view-meteo">
    <?php include __DIR__ . '/includes/widgets/piste_meteo.php'; ?>
    <div class="app-footer-links">
      <a href="/">← ça suffit ! ASBL</a> &nbsp;·&nbsp; METAR + IRM station 6451
    </div>
  </div>

  <!-- Vue : Historique -->
  <div class="app-view" id="view-historique">
    <?php include __DIR__ . '/includes/widgets/historique_vent.php'; ?>
  </div>

  <!-- Vue : Rose des vents -->
  <div class="app-view" id="view-rose">
    <?php include __DIR__ . '/includes/widgets/rose_vents.php'; ?>
  </div>

  <!-- Vue : Vols en temps réel -->
  <div class="app-view" id="view-vols">
    <?php include __DIR__ . '/includes/widgets/vols_brussels.php'; ?>
  </div>

  <!-- Navigation fixe en bas -->
</main>

<nav class="app-nav-bar">
  <button class="app-nav-bar-btn active" id="nav-meteo" onclick="switchView('meteo')">
    <span class="nav-icon">🌤</span>Météo
  </button>
  <button class="app-nav-bar-btn" id="nav-historique" onclick="switchView('historique')">
    <span class="nav-icon">📊</span>Historique
  </button>
  <button class="app-nav-bar-btn" id="nav-rose" onclick="switchView('rose')">
    <span class="nav-icon">🌬</span>Rose des vents
  </button>
  <button class="app-nav-bar-btn" id="nav-vols" onclick="switchView('vols')">
    <span class="nav-icon">✈</span>Vols
  </button>
</nav>

<script>
function switchView(view) {
  ['meteo','historique','rose','vols'].forEach(function(v) {
    var el = document.getElementById('view-' + v);
    if (v === view) {
      el.style.display = 'block';
      el.classList.add('active');
    } else {
      el.style.display = 'none';
      el.classList.remove('active');
    }
    var btn = document.getElementById('nav-' + v);
    btn.classList.remove('active','active-rose');
  });
  var btn = document.getElementById('nav-' + view);
  btn.classList.add(view === 'rose' ? 'active-rose' : 'active');
  // Init rose des vents au premier affichage
  if (view === 'rose' && typeof window.rvwInitYear === 'function') {
    window.rvwInitYear();
  }
  // Init/refresh carte Leaflet vols
  if (view === 'vols') {
    setTimeout(function(){
      if(typeof window.vbrInvalidate === 'function') window.vbrInvalidate();
      else if(typeof window.vbrLoad === 'function') window.vbrLoad();
    }, 150);
  }
  // Forcer le repaint du widget historique
  if (view === 'historique') {
    var pmhEl = document.getElementById('pmh');
    if (pmhEl) {
      pmhEl.style.display = 'none';
      setTimeout(function(){ pmhEl.style.display = ''; }, 20);
    }
  }
  // Scroll en haut
  var body = document.querySelector('.app-body');
  if (body) body.scrollTop = 0;
}

function dismissGuide() {
  document.getElementById('install-guide').style.display = 'none';
  localStorage.setItem('wind_guide_dismissed', '1');
}

(function() {
  var isStandalone = window.navigator.standalone === true
                  || window.matchMedia('(display-mode: standalone)').matches;

  if (!isStandalone) {
    // Hors webapp — afficher le guide si pas encore vu
    var dismissed = localStorage.getItem('wind_guide_dismissed');
    if (!dismissed) {
      document.getElementById('install-guide').style.display = 'block';
    }
  }

  // Auto-refresh toutes les 5 min
  setTimeout(function() { location.reload(); }, 300000);
})();
</script>

</body>
</html>
