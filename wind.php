<?php
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
</nav>

<script>
function switchView(view) {
  ['meteo','historique','rose'].forEach(function(v) {
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
