<?php
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
// index.php — Site ça suffit ! ASBL (v2 - look ancien site)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/lang.php';

// ── Mode maintenance ──────────────────────────────────────────────────────
(function() {
    try {
        $pdo  = getDB();
        $mode = $pdo->query("SELECT valeur FROM site_config WHERE cle='maintenance_mode'")->fetchColumn();
        if (!$mode || $mode === '0') return;
        $code = $pdo->query("SELECT valeur FROM site_config WHERE cle='maintenance_code'")->fetchColumn();
        if (!empty($_COOKIE['maintenance_bypass']) && $_COOKIE['maintenance_bypass'] === $code) return;
        if (isset($_GET['bypass']) && $_GET['bypass'] === $code) {
            setcookie('maintenance_bypass', $code, time() + 86400 * 30, '/', '', true, true);
            return;
        }
        if (!session_id()) session_start();
        if (!empty($_SESSION['admin'])) return;
        header('Location: /maintenance.php'); exit;
    } catch (Exception $e) {}
})();


error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

try {
    $db = getDB();
} catch (Exception $e) {
    http_response_code(500);
    die('<html><head><meta charset="UTF-8">
<link rel="icon" type="image/x-icon" href="/favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">
<link rel="apple-touch-icon" sizes="192x192" href="/favicon-192.png"><title>Maintenance</title></head><body style="font-family:Arial;text-align:center;padding:60px"><h1 style="color:#1673B2">Site en maintenance</h1><p>Veuillez reessayer dans quelques instants.</p></body></html>');
}

// Pages du menu (pour tabs desktop + select mobile + burger)
$menu_pages = array();
try {
    $all_menu = $db->query("SELECT id, slug, titre, titre_nl, icone, css_class, btn_style, parent_id, menu_position, lien_url, affichage_menu FROM pages WHERE dans_menu=1 AND visible=1 ORDER BY COALESCE(parent_id,0) ASC, ordre ASC")->fetchAll();
    // Organiser en parents / enfants
    $menu_pages    = array(); // items racine
    $menu_children = array(); // parent_id => [enfants]
    $menu_by_id    = array(); // id => item
    foreach ($all_menu as $p) {
        $menu_by_id[$p['id']] = $p;
        if (!$p['parent_id']) $menu_pages[] = $p;
        else $menu_children[$p['parent_id']][] = $p;
    }
} catch (Exception $e) {
    // Fallback si colonnes manquantes
    $menu_children = array();
    $menu_by_id = array();
    try {
        $rows = $db->query("SELECT id, slug, titre, titre_nl, icone, css_class, menu_position, lien_url, affichage_menu FROM pages WHERE dans_menu=1 AND visible=1 ORDER BY ordre ASC")->fetchAll();
        foreach ($rows as $r) {
            $menu_pages[] = array_merge(array('btn_style'=>'','parent_id'=>null,'css_class'=>'','menu_position'=>'all','lien_url'=>''), $r);
        }
    } catch (Exception $e2) {}
}

// Charger les données dynamiques
// Montant récolté = montant initial + dons confirmés en BDD
$montant_initial = floatval(cfg('montant_initial', 0));
$dons_confirmes  = 0;
try {
    $dons_confirmes = floatval($db->query("SELECT COALESCE(SUM(montant),0) FROM member_dons WHERE statut='confirme'")->fetchColumn());
} catch (Exception $e) {}
$recolte = $montant_initial + $dons_confirmes;
// Mettre à jour site_config pour afficher le total dans l'admin
// (optionnel - on peut aussi juste le calculer à la volée)
$objectif  = floatval(cfg('montant_objectif', 15000));
$pct       = $objectif > 0 ? min(100, round($recolte / $objectif * 100)) : 0;
$don_texte = cfg('don_texte', 'Frais judiciaires — Action en référé');
$iban      = cfg('iban', 'BE41 0689 0149 6910');
$bic       = cfg('bic', 'GKCCBEBB');
$beneficiaire = cfg('beneficiaire', 'ca suffit ! ASBL');

// Charger le contenu des tabs depuis la BDD
// Charger pages + contenu
$tabs_content = array();
try {
    $pages = $db->query("SELECT * FROM pages WHERE visible=1 ORDER BY ordre ASC")->fetchAll();
    foreach ($pages as $p) { $tabs_content[$p['slug']] = $p; }
} catch (Exception $e) {}

// Charger les widgets par page
$page_widgets = array();        // slug => [slug, ...]  pour JS
$page_widgets_pos = array();    // slug => ['widget_slug'=>'droite'|'gauche', ...]
try {
    $rows = $db->query("SELECT pw.page_slug, pw.widget_slug, pw.ordre, COALESCE(pw.position,'droite') as position FROM page_widgets pw JOIN widgets w ON w.slug=pw.widget_slug WHERE w.actif=1 ORDER BY pw.page_slug, pw.ordre ASC")->fetchAll();
    foreach ($rows as $r) {
        $page_widgets[$r['page_slug']][] = $r['widget_slug'];
        $page_widgets_pos[$r['page_slug']][$r['widget_slug']] = $r['position'];
    }
} catch (Exception $e) {
    error_log('page_widgets error: ' . $e->getMessage());
}

// News publiées
$news_list = array();
try {
    $news_list = $db->query("SELECT * FROM news WHERE statut='publie' ORDER BY epingle DESC, date_creation DESC LIMIT 10")->fetchAll();
} catch (Exception $e) {}

// Calculer le premier tab visible (pour synchroniser PHP et JS)
$first_tab_slug = 'mobilisation'; // défaut
foreach ($menu_pages as $p) {
    if (($p['menu_position'] ?? 'all') === 'header') continue;
    if (!empty($p['lien_url'])) continue;
    $first_tab_slug = $p['slug'];
    break;
}
// Si ?page=slug est passé en URL et correspond à un tab valide, l'utiliser
if (!empty($_GET['page'])) {
    $req_slug = preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($_GET['page'])));
    $found_page = false;
    foreach ($menu_pages as $p) {
        if ($p['slug'] === $req_slug) { $found_page = true; break; }
    }
    if (!$found_page && isset($tabs_content[$req_slug])) { $found_page = true; }
    if ($found_page) $first_tab_slug = $req_slug;
}

// Annonce
$annonce_active = cfg('annonce_active', '1') === '1';
$annonce_titre  = cfg('annonce_titre', 'Piste 01 & UBCNA s unissent !');
$annonce_texte  = cfg('annonce_texte', '');

// Urgence texte
$urgence_texte = cfg('urgence_texte', 'Piste 01 & UBCNA unis — Ensemble pour faire cesser les nuisances !');

// Config don
$iban        = cfg('iban', 'BE41 0689 0149 6910');
$bic         = cfg('bic', 'GKCCBEBB');
$beneficiaire = cfg('beneficiaire', 'ça suffit ! ASBL');
$don_texte   = cfg('don_texte', 'Action en référé contre l Etat belge');

// Logo chargé depuis medias/logo.png
?>
<!DOCTYPE html>
<html lang="<?= LANG ?>">
<head>
  <meta charset="UTF-8">
  <?php
  // Balises hreflang pour SEO multilingue
  $_currentPath = strtok($_SERVER['REQUEST_URI'], '?');
  $_currentPath = preg_replace('#^/nl(/|$)#', '/', $_currentPath);
  $_currentPath = $_currentPath ?: '/';
  $_pageParam   = !empty($_GET['page']) ? '?page=' . urlencode($_GET['page']) : '';
  ?>
  <link rel="alternate" hreflang="fr"        href="https://www.casuffit.be<?= htmlspecialchars($_currentPath . $_pageParam) ?>">
  <link rel="alternate" hreflang="nl"        href="https://www.casuffit.be/nl<?= htmlspecialchars(rtrim($_currentPath,'/') . $_pageParam) ?>">
  <link rel="alternate" hreflang="x-default" href="https://www.casuffit.be/">

  <!-- ── RGPD : consentement cookies ── -->
  <script>
  (function(){
    var GA_ID = '<?= htmlspecialchars(cfg("ga_id","G-7LKP0KC1SD")) ?>';

    // Lire les préférences sauvegardées
    function getConsent() {
      try { return JSON.parse(localStorage.getItem('rgpd_consent') || 'null'); } catch(e) { return null; }
    }
    function saveConsent(analytics, social) {
      var c = {analytics: !!analytics, social: !!social, date: new Date().toISOString()};
      localStorage.setItem('rgpd_consent', JSON.stringify(c));
      return c;
    }

    // Charger GA4 si analytics accepté
    function loadGA() {
      if (document.getElementById('ga-script')) return;
      var s = document.createElement('script');
      s.id = 'ga-script';
      s.async = true;
      s.src = 'https://www.googletagmanager.com/gtag/js?id=' + GA_ID;
      document.head.appendChild(s);
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      window.gtag = gtag;
      gtag('js', new Date());
      gtag('config', GA_ID, {anonymize_ip: true});
    }

    // Signal pour Facebook (géré dans le widget)
    window.rgpdSocialAccepted = false;

    // Appliquer les préférences
    function applyConsent(c) {
      if (!c) return;
      if (c.analytics) loadGA();
      if (c.social) {
        window.rgpdSocialAccepted = true;
        document.dispatchEvent(new CustomEvent('rgpd-social-accepted'));
      }
    }

    // Au chargement : appliquer si déjà consenti
    var existing = getConsent();
    if (existing) {
      applyConsent(existing);
    }

    // Afficher la bannière si pas encore de choix
    window.rgpdInit = function() {
      if (getConsent()) return; // déjà choisi
      var banner = document.getElementById('rgpd-banner');
      if (banner) {
        banner.style.display = 'flex';
        // Éviter que la bannière couvre les boutons
        var h = banner.offsetHeight || 140;
        document.body.style.paddingBottom = (h + 10) + 'px';
      }
    };

    window.rgpdAcceptAll = function() {
      var c = saveConsent(true, true);
      applyConsent(c);
      hideBanner();
    };
    window.rgpdRefuseAll = function() {
      saveConsent(false, false);
      hideBanner();
    };
    window.rgpdSaveChoices = function() {
      var analytics = document.getElementById('rgpd-analytics').checked;
      var social    = document.getElementById('rgpd-social').checked;
      var c = saveConsent(analytics, social);
      applyConsent(c);
      hideBanner();
    };
    window.rgpdToggleDetail = function() {
      var d = document.getElementById('rgpd-detail');
      d.style.display = d.style.display === 'none' ? 'block' : 'none';
    };
    window.rgpdReset = function() {
      localStorage.removeItem('rgpd_consent');
      var banner = document.getElementById('rgpd-banner');
      if (banner) banner.style.display = 'flex';
    };
    function hideBanner() {
      var banner = document.getElementById('rgpd-banner');
      if (banner) banner.style.display = 'none';
      document.body.style.paddingBottom = '';
    }
  })();
  </script>
  <link rel="icon" type="image/x-icon" href="/favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">
  <link rel="apple-touch-icon" sizes="192x192" href="/favicon-192.png">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
  <meta name="app-version" content="2.2.0">
  <link rel="stylesheet" href="/assets/css/content.css">
  <meta name="first-tab" content="<?= htmlspecialchars($first_tab_slug) ?>">
  <title>ça suffit ! ASBL — Piste 01 · UBCNA</title>
  <meta name="description" content="Mobilisation citoyenne contre les nuisances aériennes de Brussels Airport">
  <style>

/* ══ CHARTE EXACTE casuffit.be ══════════════════════════════════════ */
:root {
  --bleu:        rgba(22, 115, 178, 1);   /* #1673B2 */
  --bleu-hex:    #1673B2;
  --bleu-fonce:  #0e5a96;
  --bleu-leger:  #e8f3fb;
  --bleu-ciel:   #c8dff0;
  --orange:      rgba(255, 153, 0, 1);    /* #FF9900 */
  --orange-hex:  #FF9900;
  --orange-sombre: #cc7a00;
  --blanc:       #ffffff;
  --gris-texte:  #555;
  --gris-bord:   #ccc;
  --gris-clair:  #f5f5f5;
  --vert-bg:     #e8f5e9;
  --vert:        #2e7d32;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }

body {
  margin: 0; padding: 0; height: 100%;
  font-weight: 200;
  font-family: "Helvetica Neue", Arial, sans-serif;
  font-size: 90%;
  background: #fff;
  color: var(--bleu-hex);
}

a {
  text-decoration: none;
  font-weight: 200;
  color: var(--orange-hex);
  line-height: 25px;
}
a:hover { text-decoration: underline; }

strong { font-size: 100%; font-weight: 700; }

/* ══ HEADER MODERNE ════════════════════════════════════════════════════ */
header.site-header {
  background: linear-gradient(135deg, #0e3d6b 0%, #1673B2 60%, #1a85cc 100%);
  position: sticky;
  top: 0;
  z-index: 100;
  box-shadow: 0 2px 20px rgba(0,0,0,0.25);
}
.header-inner{
  max-width:960px;
  margin:0 auto;
  padding:0 20px;
  display:flex;
  align-items:center;
  flex-direction:row;
  flex-wrap:nowrap;
  gap:0;
  min-height:64px;
}
.logo-wrap {
  width: 52px;
  height: 52px;
  flex-shrink: 0;
  margin-right: 12px;
  filter: drop-shadow(0 2px 4px rgba(0,0,0,0.25));
  transition: transform 0.2s;
}
.logo-wrap:hover { transform: scale(1.05); }
.logo-wrap img { width: 52px; height: 52px; object-fit: contain; display: block; border-radius: 0; }
.header-brand {
  display: flex;
  flex-direction: column;
  justify-content: center;
  margin-right: auto;
  flex-shrink: 0;
}
.header-brand h1 {
  font-family: "Helvetica Neue", Arial, sans-serif;
  font-size: 1.2rem;
  font-weight: 800;
  color: #ffffff;
  line-height: 1.1;
  letter-spacing: -0.02em;
  margin: 0;
}
.header-brand h1 .accent {
  color: #FF9900;
  font-style: italic;
}
.header-badge {
  display: inline-block;
  background: rgba(255,255,255,0.15);
  color: rgba(255,255,255,0.75);
  font-size: 0.58rem;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  padding: 2px 6px;
  border-radius: 20px;
  border: 1px solid rgba(255,255,255,0.2);
  margin-top: 3px;
  width: fit-content;
  white-space: nowrap;
}
/* NAV intégrée dans le header */
.header-nav {
  display: flex;
  align-items: stretch;
  height: 100%;
  gap: 2px;
  margin-left: 24px;
}
.header-nav a {
  display: flex;
  align-items: center;
  padding: 0 14px;
  color: rgba(255,255,255,0.80);
  font-size: 0.78rem;
  font-weight: 500;
  font-family: "Helvetica Neue", Arial, sans-serif;
  text-decoration: none;
  white-space: nowrap;
  transition: color 0.2s, background 0.2s;
  border-radius: 6px;
  letter-spacing: 0.02em;
}
.header-nav a:hover { color: #ffffff; background: rgba(255,255,255,0.12); }
.header-nav a.active { color: #FF9900; font-weight: 700; }
.header-nav a.nav-cta {
  background: #FF9900;
  color: #fff;
  font-weight: 700;
  border-radius: 8px;
  padding: 0 16px;
  margin-left: 8px;
}
.header-nav a.nav-cta:hover { background: #e68800; color: #fff; }
.header-nav a.nav-white {
  background: #fff; color: #0e3d6b; font-weight: 700;
  border-radius: 7px; margin-left: 6px; padding: 0 14px;
}
.header-nav a.nav-white:hover { background: #e6f1fb; color: #0e3d6b; }
.header-nav a.nav-outline {
  border: 1.5px solid rgba(255,255,255,0.7); color: #fff;
  border-radius: 7px; margin-left: 6px; padding: 0 13px;
}
.header-nav a.nav-outline:hover { background: rgba(255,255,255,0.15); }

/* ── Sélecteur de langue ─────────────────────────────────────────────── */
.lang-switch {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 34px; height: 28px;
  margin: 0 8px 0 6px;
  padding: 0 8px;
  border: 1.5px solid rgba(255,255,255,0.6);
  border-radius: 6px;
  color: #fff;
  font-size: 0.72rem; font-weight: 700;
  text-decoration: none;
  letter-spacing: .04em;
  transition: background .15s, border-color .15s;
}
.lang-switch:hover {
  background: rgba(255,255,255,0.15);
  border-color: #FF9900;
  color: #FF9900;
}
@media (max-width: 768px) {
  .lang-switch { margin: 0 6px; min-width: 30px; height: 26px; font-size: .68rem; }
}
/* ── Dropdown sous-menu ── */
.nav-dropdown { position: relative; display: flex; align-items: stretch; }
.nav-dropdown > .nav-parent {
  display: flex; align-items: center; gap: 4px;
  padding: 0 11px; color: rgba(255,255,255,0.80);
  font-size: 0.75rem; font-weight: 500; border-radius: 5px;
  cursor: pointer; white-space: nowrap; transition: all .2s; user-select: none;
}
.nav-dropdown > .nav-parent::after { content: '▾'; font-size: .6rem; opacity:.7; margin-left: 2px; }
.nav-dropdown:hover > .nav-parent { color: #fff; background: rgba(255,255,255,0.12); }
.nav-dropdown > .nav-parent.active { color: #FF9900; font-weight: 700; }
.nav-submenu {
  display: none; position: absolute; top: calc(100% + 2px); left: 0;
  background: #fff; border-radius: 8px;
  box-shadow: 0 8px 24px rgba(0,0,0,0.15); border: 1px solid #e0e8f0;
  min-width: 190px; padding: 6px; z-index: 200;
}
.nav-dropdown:hover .nav-submenu { display: block; }
.nav-submenu a {
  display: flex; align-items: center; gap: 8px;
  padding: 8px 12px; color: #0e3d6b; font-size: 0.78rem; font-weight: 500;
  border-radius: 5px; transition: background .15s; white-space: nowrap; text-decoration: none;
}
.nav-submenu a:hover { background: #e6f1fb; color: #1673B2; }
.nav-submenu a.active { color: #1673B2; font-weight: 700; background: #e6f1fb; }
@media (max-width: 700px) {
  .header-nav { display: none; }
  .header-inner { padding: 0 16px; min-height: 60px; }
  .logo-wrap { width: 46px; height: 46px; }
  .header-brand h1 { font-size: 1.1rem; }
}

/* ══ URGENCE STRIP ════════════════════════════════════════════════════ */
.urgence {
  width: 100%;
  background-color: var(--orange-hex);
  color: #fff;
  text-align: center;
  padding: 9px 20px;
  font-weight: 600;
  font-size: 95%;
}

/* ══ PROGRESS BAR ═════════════════════════════════════════════════════ */
.progress-section {
  background: #fff;
  border-bottom: 1px solid var(--bleu-ciel);
  padding: 28px 20px;
}
.progress-inner { max-width: 900px; margin: 0 auto; }
.prog-header { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 12px; flex-wrap: wrap; gap: 8px; }
.prog-label { font-weight: 400; font-size: 95%; color: var(--bleu-hex); text-transform: uppercase; letter-spacing: 0.04em; }
.prog-chiffres { font-size: 1.4rem; font-weight: 700; color: var(--bleu-hex); }
.prog-chiffres b { color: var(--orange-hex); }
.bar-wrap { background: var(--bleu-ciel); height: 12px; border-radius: 6px; overflow: hidden; margin-bottom: 14px; }
.bar-fill { height: 100%; background: linear-gradient(90deg, var(--bleu-hex), var(--orange-hex)); border-radius: 6px; width: 0%; transition: width 2s cubic-bezier(.22,1,.36,1); }
.prog-stats { display: flex; gap: 32px; flex-wrap: wrap; }
.stat { display: flex; flex-direction: column; }
.stat-val { font-size: 1.5rem; font-weight: 700; color: var(--orange-hex); }
.stat-lab { font-size: 75%; font-weight: 400; color: var(--gris-texte); text-transform: uppercase; letter-spacing: 0.05em; }

/* ══ TABS ═════════════════════════════════════════════════════════════ */
.tabs-wrap { max-width: 960px; margin: 0 auto; padding: 16px 20px 0; }
.tabs {
  display: flex; gap: 0;
  border-bottom: 2px solid var(--bleu-ciel);
  overflow-x: auto;
}
.tab-btn {
  background: none; border: none;
  border-bottom: 3px solid transparent;
  margin-bottom: -2px;
  padding: 8px 12px;
  font-family: "Helvetica Neue", Arial, sans-serif;
  font-size: 80%; font-weight: 500;
  color: var(--bleu-hex); cursor: pointer;
  transition: all 0.2s; white-space: nowrap;
  letter-spacing: -0.01em;
}
.tab-btn:hover { color: var(--orange-hex); }
.tab-btn.active { color: var(--orange-hex); border-bottom-color: var(--orange-hex); font-weight: 600; }
/* ── Sous-tabs ── */
.subtabs-wrap {
  display: none;
  background: #f0f6ff;
  border-bottom: 2px solid #c8ddf5;
  padding: 0 20px;
}
.subtabs-wrap.visible { display: block; }
.subtabs { max-width: 960px; margin: 0 auto; display: flex; gap: 2px; }
.subtab-btn {
  padding: 7px 14px;
  font-size: .75rem;
  font-weight: 500;
  color: #1673B2;
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  cursor: pointer;
  font-family: inherit;
  transition: all .2s;
  white-space: nowrap;
}
.subtab-btn:hover { color: #0e3d6b; background: rgba(22,115,178,.08); }
.subtab-btn.active { color: #0e3d6b; border-bottom-color: #1673B2; font-weight: 700; }

/* Sous-tab select mobile */
.subtabs-mobile-wrap { display:none; padding:6px 12px; background:#e4eef8; border-bottom:2px solid #c8ddf5; align-items:center; gap:8px; }
.subtabs-mobile-lbl { font-size:.68rem; font-weight:700; color:#1673B2; white-space:nowrap; text-transform:uppercase; letter-spacing:.03em; flex-shrink:0; }
.subtabs-sel { flex:1; padding:6px 10px; border:1.5px solid #a8c8e8; border-radius:7px; font-size:.82rem; font-family:inherit; color:var(--bleu-fonce); background:#fff; font-weight:600; outline:none; -webkit-appearance:auto; cursor:pointer; }

@media (max-width: 600px) {
  .subtabs-wrap { padding:0; }
  .subtabs { display:none !important; }
  .subtabs-mobile-wrap { display:flex; }
}

/* ══ MAIN LAYOUT ══════════════════════════════════════════════════════ */
.main-wrap {
  max-width: 960px;
  margin: 0 auto;
  padding: 20px 20px 40px;
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 28px;
  align-items: start;
}
@media (max-width: 430px) {
  /* ── iPhone : agrandir uniquement les zones de contenu texte ── */
  .tab-panel.active,
  .tab-panel.active p,
  .tab-panel.active li,
  .tab-panel.active div,
  .tab-panel.active span,
  .news-contenu,
  .news-contenu p,
  .news-contenu li,
  .apanel-inner,
  .apanel-inner p,
  .apanel-inner li {
    font-size: 112%;
    font-weight: 300;
    line-height: 1.65;
  }
  /* Titres et éléments spécifiques */
  .tab-panel.active h2,
  .tab-panel.active h3 { font-size: 115%; }
  .tab-panel.active .section-title { font-size: 19px; }
  .tab-panel.active { padding: 14px 14px; }
}

@media (max-width: 680px) {
  .main-wrap { grid-template-columns: 1fr; }
  .donation-card {
  order: 2;
  position: sticky;
  top: 80px;
}
  .tabs { white-space: nowrap; }
}

/* ══ PANNEAUX CONTENU ════════════════════════════════════════════════ */
.tab-panel { display: none; }
.tab-panel.active { display: block; padding: 20px 24px; }
/* Limiter la largeur quand pas de colonne droite */
#colonne-gauche { min-width: 0; }

.section-title {
  color: var(--orange-hex);
  font-weight: 400; font-size: 20px;
  margin: 24px 0 10px; padding-bottom: 6px;
  border-bottom: 1px solid var(--bleu-ciel);
}
.section-title:first-child { margin-top: 0; }

.content-text {
  color: var(--bleu-hex);
  margin-bottom: 12px;
  font-size: 95%; line-height: 1.65;
}

/* Encadrés */
.cadre-orange {
  padding: 12px 16px;
  background-color: var(--orange-hex);
  color: #fff;
  margin: 14px 0;
  font-weight: 200;
}
.cadre-bleu {
  padding: 12px 16px;
  background-color: var(--bleu-leger);
  border-left: 4px solid var(--bleu-hex);
  color: var(--bleu-hex);
  margin: 14px 0;
  font-size: 95%;
}
.cadre-vert {
  padding: 12px 16px;
  background-color: var(--vert-bg);
  border-left: 4px solid var(--vert);
  margin: 14px 0;
}
.cadre-vert ul { list-style: none; padding: 0; }
.cadre-vert ul li { color: var(--vert); font-size: 90%; padding: 3px 0; }
.cadre-vert ul li::before { content: "✓  "; font-weight: 700; }
.cadre-vert .cv-titre { font-weight: 600; color: #1b5e20; font-size: 80%; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 8px; }

.alerte {
  padding: 12px 16px;
  border: 2px solid var(--orange-hex);
  border-left: 5px solid var(--orange-hex);
  background: #fff8ee;
  margin: 14px 0;
  font-size: 95%;
}
.alerte .al-titre { font-weight: 700; color: var(--orange-sombre); font-size: 85%; text-transform: uppercase; margin-bottom: 6px; }
.alerte p { color: #7a4500; margin: 0; }

/* Frise chronologique */
.timeline { position: relative; padding-left: 24px; margin: 14px 0; }
.timeline::before { content: ""; position: absolute; left: 6px; top: 0; bottom: 0; width: 2px; background: var(--bleu-ciel); }
.tl-item { position: relative; margin-bottom: 14px; }
.tl-item::before { content: ""; position: absolute; left: -20px; top: 5px; width: 10px; height: 10px; border-radius: 50%; background: var(--bleu-hex); border: 2px solid #fff; box-shadow: 0 0 0 2px var(--bleu-hex); }
.tl-item.bad::before { background: var(--orange-hex); box-shadow: 0 0 0 2px var(--orange-hex); }
.tl-date { font-weight: 600; font-size: 85%; color: var(--bleu-hex); }
.tl-item.bad .tl-date { color: var(--orange-sombre); }
.tl-text { font-size: 90%; color: var(--gris-texte); line-height: 1.5; }

/* Chiffres */
.chiffres-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px,1fr)); gap: 10px; margin: 14px 0; }
.chiffre-card { background: var(--bleu-leger); border-top: 3px solid var(--bleu-hex); padding: 14px; text-align: center; }
.chiffre-val { font-size: 1.5rem; font-weight: 700; color: var(--orange-hex); }
.chiffre-lab { font-size: 70%; color: var(--gris-texte); margin-top: 4px; }
.chiffre-label { font-size: 70%; color: var(--gris-texte); display: block; margin-top: 2px; }

/* Styles palette éditeur */
.orange.section-title, div.orange.section-title {
  color: var(--orange-hex);
  font-weight: 400;
  font-size: 1.05rem;
  margin: 20px 0 8px;
  padding-bottom: 4px;
  border-bottom: 1px solid var(--bleu-ciel);
}
blockquote {
  border-left: 4px solid var(--orange-hex);
  padding: 10px 16px;
  background: #fff8ee;
  margin: 14px 0;
  font-style: italic;
  color: #555;
}
.ac-item { margin: 10px 0; }
.ac-item .ac-text { font-size: 88%; color: var(--gris-texte); line-height: 1.5; }

/* Demandes */
.demandes-list { list-style: none; padding: 0; margin: 12px 0; }
.demande-item { display: flex; gap: 14px; align-items: flex-start; padding: 12px 0; border-bottom: 1px solid var(--bleu-ciel); }
.demande-item:last-child { border-bottom: none; }
.demande-num { width: 32px; height: 32px; background: var(--bleu-hex); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 90%; flex-shrink: 0; margin-top: 2px; }
.dc-titre { font-weight: 600; color: var(--bleu-hex); font-size: 95%; margin-bottom: 4px; }
.dc-text { font-size: 85%; color: var(--gris-texte); margin: 0; }

/* Actions grille */
.actions-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr)); gap: 12px; margin: 14px 0; }
.action-card { background: var(--bleu-leger); border-top: 3px solid var(--bleu-hex); padding: 14px 12px; }
.ac-num { font-size: 1.4rem; font-weight: 700; color: var(--bleu-ciel); line-height: 1; margin-bottom: 5px; }
.ac-titre { font-weight: 600; color: var(--bleu-hex); font-size: 90%; margin-bottom: 5px; }
.ac-text { font-size: 80%; color: var(--gris-texte); margin: 0; line-height: 1.45; }

.citation-box { background: #f5f5f5; border-left: 4px solid var(--bleu-hex); padding: 12px 16px; margin: 14px 0; display: flex; gap: 12px; align-items: flex-start; }
.citation-box p { font-style: italic; color: var(--bleu-hex); font-size: 90%; margin: 0 0 6px; font-weight: 400; }
.citation-box a { font-size: 85%; color: var(--bleu-hex); font-weight: 400; }

.sep { border: none; border-top: 1px solid var(--bleu-ciel); margin: 20px 0; }

.lettre-intro { background: var(--bleu-hex); color: #fff; padding: 16px 20px; margin-bottom: 20px; }
.lettre-intro p { color: #fff; font-weight: 400; font-size: 95%; margin: 0; line-height: 1.55; }

.signature { background: var(--bleu-leger); border-left: 3px solid var(--bleu-hex); padding: 14px 18px; margin-top: 20px; font-size: 90%; color: var(--bleu-hex); }
.signature strong { display: block; margin-top: 6px; font-size: 88%; }

/* ══ CARTE DON ════════════════════════════════════════════════════════ */
.donation-card {
  background: #fff;
  border: 1px solid var(--bleu-ciel);
  border-top: 4px solid var(--orange-hex);
  padding: 24px 20px;
  box-shadow: 0 4px 16px rgba(22,115,178,0.1);
}
@media (min-width: 801px) { .donation-card { position: sticky; top: 20px; } }

.don-titre { font-size: 110%; font-weight: 600; color: var(--bleu-hex); margin-bottom: 3px; }
.don-sub { font-size: 75%; color: var(--gris-texte); text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 18px; font-weight: 400; }

.montant-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 7px; margin-bottom: 10px; }
.montant-btn {
  background: var(--bleu-leger); border: 2px solid var(--bleu-ciel);
  color: var(--bleu-hex); font-family: "Helvetica Neue", Arial;
  font-size: 100%; font-weight: 600;
  padding: 10px 4px; cursor: pointer; border-radius: 0;
  transition: all 0.15s; text-align: center; line-height: 1.2;
}
.montant-btn small { display: block; font-size: 60%; font-weight: 200; color: var(--gris-texte); margin-top: 2px; text-transform: uppercase; }
.montant-btn:hover { border-color: var(--bleu-hex); }
.montant-btn.active { background: var(--bleu-hex); border-color: var(--bleu-hex); color: #fff; }
.montant-btn.active small { color: #c8dff0; }

.custom-wrap { display: none; margin-bottom: 14px; }
.custom-label { font-size: 72%; font-weight: 400; color: var(--gris-texte); text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 5px; }
.custom-row { display: flex; }
.custom-input {
  flex: 1; border: 2px solid var(--bleu-ciel); border-right: none;
  padding: 10px 14px; font-family: "Helvetica Neue", Arial;
  font-size: 130%; font-weight: 600; color: var(--bleu-hex); outline: none;
  transition: border-color 0.2s;
}
.custom-input:focus { border-color: var(--bleu-hex); }
.euro-tag { background: var(--bleu-hex); color: white; padding: 10px 14px; font-size: 120%; font-weight: 600; }

.divider { display: flex; align-items: center; gap: 10px; margin: 16px 0; color: var(--gris-texte); font-size: 72%; font-weight: 400; text-transform: uppercase; letter-spacing: 0.08em; }
.divider::before, .divider::after { content: ""; flex: 1; height: 1px; background: var(--bleu-ciel); }

/* QR */
.qr-section { background: var(--bleu-leger); border: 1px solid var(--bleu-ciel); padding: 16px; margin-bottom: 12px; text-align: center; }
.qr-title { font-weight: 600; font-size: 90%; color: var(--bleu-hex); margin-bottom: 3px; }
.qr-sub { font-size: 72%; color: var(--gris-texte); margin-bottom: 12px; }
#qrcode { display: inline-block; background: white; padding: 8px; border: 2px solid var(--bleu-ciel); margin-bottom: 8px; }
#qrcode img, #qrcode canvas { display: block; }
.qr-instructions { font-size: 70%; color: var(--gris-texte); line-height: 1.5; }
.qr-instructions b { color: var(--bleu-hex); }

/* Bancontact */
.btn-bancontact {
  display: flex; align-items: center; justify-content: center; gap: 10px;
  width: 100%; background: var(--bleu-hex);
  color: white; border: none; padding: 13px 16px;
  font-family: "Helvetica Neue", Arial; font-size: 95%; font-weight: 400;
  cursor: pointer; text-decoration: none; transition: background 0.2s;
  margin-bottom: 8px;
}
.btn-bancontact:hover { background: var(--bleu-fonce); text-decoration: none; color: white; }
.bancontact-icon { width: 34px; height: 22px; background: white; padding: 2px 4px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.bancontact-icon svg { width: 26px; height: 16px; }

/* Virement */
.virement-box { background: #f9f9f9; border: 1px solid var(--bleu-ciel); padding: 14px 16px; margin-bottom: 14px; }
.vir-label { font-size: 68%; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--bleu-hex); margin-bottom: 8px; }
.iban-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
.iban-val { font-size: 100%; font-weight: 700; color: var(--bleu-hex); letter-spacing: 0.04em; }
.iban-bank { font-size: 70%; color: var(--gris-texte); font-weight: 200; margin-top: 2px; }
.copy-btn { background: var(--bleu-hex); color: white; border: none; padding: 5px 10px; font-family: "Helvetica Neue", Arial; font-size: 70%; font-weight: 400; cursor: pointer; transition: background 0.2s; flex-shrink: 0; }
.copy-btn:hover { background: var(--bleu-fonce); }
.copy-btn.ok { background: #2a8a2a; }
.comm-box { margin-top: 10px; background: rgba(255,153,0,0.08); border: 1px dashed var(--orange-hex); padding: 8px 12px; }
.comm-label { font-size: 65%; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--orange-sombre); margin-bottom: 2px; }
.comm-val { font-size: 82%; font-style: italic; color: var(--bleu-hex); font-weight: 400; }

/* Fiscal */
.fiscal-box { background: #fffdf0; border: 1px solid #fcd99a; padding: 10px 14px; margin-bottom: 14px; display: flex; gap: 10px; align-items: flex-start; }
.fiscal-text { font-size: 78%; color: #7a5010; line-height: 1.4; }
.fiscal-text strong { font-weight: 700; display: block; color: #b06800; font-size: 82%; margin-bottom: 2px; }

/* Membre */
.membre-area { padding-top: 14px; border-top: 1px solid var(--bleu-ciel); }
.membre-area p { font-size: 80%; color: var(--gris-texte); margin-bottom: 10px; line-height: 1.5; }
.btn-membre {
  display: block; width: 100%; background: transparent;
  border: 2px solid var(--bleu-hex); color: var(--bleu-hex);
  font-family: "Helvetica Neue", Arial; font-size: 88%; font-weight: 400;
  padding: 10px; cursor: pointer; text-align: center; text-decoration: none;
  transition: all 0.2s;
}
.btn-membre:hover { background: var(--bleu-hex); color: white; text-decoration: none; }

/* ══ FOOTER ═══════════════════════════════════════════════════════════ */
.margepied { clear: both; padding-top: 50px; }
/* ══ FOOTER ══════════════════════════════════════════════════════════ */
#pied {
  width: 100%;
  background: #fff;
  border-top: 1px solid var(--gris-bord);
  color: var(--gris-texte);
  font-size: .85rem;
}
.pied-grid {
  max-width: 1100px;
  margin: 0 auto;
  padding: 36px 24px 24px;
  display: grid;
  grid-template-columns: 1.2fr 1fr 1.3fr;
  gap: 36px;
  align-items: start;
}
.pied-col h4 {
  font-size: .8rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: var(--bleu-fonce);
  margin: 0 0 12px;
}
.pied-logo {
  max-width: 130px;
  height: auto;
  margin-bottom: 12px;
  display: block;
}
.pied-mission {
  font-size: .82rem;
  line-height: 1.55;
  color: var(--gris-texte);
}
.pied-mission strong { color: var(--bleu-fonce); font-weight: 700; }

.pied-col-nav ul {
  list-style: none;
  margin: 0; padding: 0;
}
.pied-col-nav li { margin-bottom: 7px; }
.pied-col-nav a {
  color: var(--gris-texte);
  text-decoration: none;
  font-size: .85rem;
  transition: color .15s;
}
.pied-col-nav a:hover { color: var(--orange-hex); }

.pied-iban {
  font-size: .9rem;
  font-weight: 700;
  color: var(--bleu-hex);
  margin-bottom: 4px;
}
.pied-comm {
  font-size: .78rem;
  color: #888;
  margin-bottom: 12px;
}
.pied-email {
  display: inline-block;
  color: var(--orange-hex);
  text-decoration: none;
  font-size: .85rem;
  margin-bottom: 14px;
}
.pied-email:hover { text-decoration: underline; }

.pied-rs { display: flex; gap: 10px; flex-wrap: wrap; }
.pied-rs-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 38px; height: 38px;
  border-radius: 8px;
  text-decoration: none;
  transition: transform .15s;
}
.pied-rs-icon:hover { transform: translateY(-2px); }
.pied-rs-fb { background: #1877F2; }
.pied-rs-ig { background: radial-gradient(circle at 30% 107%, #fdf497 0%, #fdf497 5%, #fd5949 45%, #d6249f 60%, #285AEB 90%); }
.pied-rs-wa { background: #25D366; }

.pied-bottom {
  border-top: 1px solid #eee;
  padding: 14px 24px;
  text-align: center;
  font-size: .72rem;
  color: #999;
}

@media (max-width: 768px) {
  .pied-grid {
    grid-template-columns: 1fr;
    gap: 28px;
    padding: 28px 20px 20px;
    text-align: center;
  }
  .pied-logo { margin-left: auto; margin-right: auto; }
  .pied-rs { justify-content: center; }
}

/* Animations */
.fade-in { opacity: 0; transform: translateY(14px); animation: fadeUp 0.5s ease forwards; }
@keyframes fadeUp { to { opacity: 1; transform: none; } }
.fade-in:nth-child(2) { animation-delay: 0.15s; }
.fade-in:nth-child(3) { animation-delay: 0.3s; }

/* ══ HERO IMAGE — section pourquoi ═══════════════════════════════════ */
.pourquoi-hero {
  width: 100%;
  border-radius: 12px;
  overflow: hidden;
  margin-bottom: 24px;
  position: relative;
}
.pourquoi-hero img {
  width: 100%;
  height: auto;
  display: block;
}
.pourquoi-hero-caption {
  position: absolute;
  bottom: 0; left: 0; right: 0;
  background: linear-gradient(transparent, rgba(14,61,107,0.85));
  color: #fff;
  font-size: 0.75rem;
  font-weight: 400;
  padding: 28px 16px 10px;
  letter-spacing: 0.02em;
}
.pourquoi-hero-caption strong {
  color: #FF9900;
  font-weight: 700;
  font-size: 0.85rem;
}

/* ══ NEWSLETTER FORM ═════════════════════════════════════════════════ */
.newsletter-intro {
  background: var(--bleu-leger);
  border-left: 4px solid var(--bleu-hex);
  padding: 14px 18px;
  border-radius: 0 8px 8px 0;
  margin-bottom: 22px;
  font-size: 0.9rem;
  color: var(--texte);
  line-height: 1.6;
}
.newsletter-form { max-width: 580px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
.form-group { display: flex; flex-direction: column; gap: 5px; margin-bottom: 12px; }
.form-group label { font-size: 0.78rem; font-weight: 600; color: var(--bleu-hex); text-transform: uppercase; letter-spacing: 0.05em; }
.form-group input[type=text],
.form-group input[type=email],
.form-group input[type=tel] {
  padding: 10px 12px;
  border: 1.5px solid #dde4ed;
  border-radius: 7px;
  font-size: 0.88rem;
  color: var(--texte);
  font-family: inherit;
  transition: border-color 0.2s;
  background: #fff;
}
.form-group input:focus { outline: none; border-color: var(--bleu-hex); }
.form-check { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 10px; }
.form-check input[type=checkbox] { margin-top: 3px; width: 16px; height: 16px; flex-shrink: 0; accent-color: var(--bleu-hex); }
.form-check label { font-size: 0.83rem; color: var(--texte); line-height: 1.5; cursor: pointer; }
.form-check.rgpd { background: #f0f7ff; border: 1px solid #bee3f8; border-radius: 7px; padding: 10px 12px; }
.form-check.rgpd label { color: #2c5282; font-size: 0.78rem; }
.btn-subscribe {
  background: var(--bleu-hex);
  color: #fff;
  border: none;
  padding: 12px 28px;
  border-radius: 8px;
  font-size: 0.9rem;
  font-weight: 700;
  font-family: inherit;
  cursor: pointer;
  transition: background 0.2s;
  margin-top: 8px;
}
.btn-subscribe:hover { background: #125a90; }
.btn-subscribe:disabled { background: #888; cursor: not-allowed; }
.form-msg { margin-top: 14px; padding: 12px 16px; border-radius: 8px; font-size: 0.85rem; display: none; }
.form-msg.ok  { background: #e8f8f0; color: #276749; border-left: 3px solid #48bb78; display: block; }
.form-msg.err { background: #fde8e8; color: #c53030; border-left: 3px solid #fc8181; display: block; }
@media (max-width: 600px) { .form-row { grid-template-columns: 1fr; } }

/* ══ ANNONCE UNION ═══════════════════════════════════════════════ */
.annonce-union {
  background: linear-gradient(90deg, #0e3d6b, #1673B2);
  color: #fff;
  padding: 7px 40px 7px 16px;
  text-align: center;
  position: relative;
  line-height: 1.3;
}
.annonce-union .annonce-date {
  display: inline-block;
  background: #FF9900;
  color: #fff;
  font-size: 0.62rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  padding: 1px 6px;
  border-radius: 10px;
  margin-right: 6px;
  vertical-align: middle;
}
.annonce-union h2 {
  display: inline;
  font-size: 0.8rem;
  font-weight: 700;
  color: #fff;
  margin: 0;
}
.annonce-union p {
  display: inline;
  font-size: 0.78rem;
  color: rgba(255,255,255,0.82);
  margin: 0 0 0 5px;
}
.annonce-union .annonce-logos { display: none; }
.annonce-close {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  right: 10px;
  background: none;
  border: none;
  color: rgba(255,255,255,0.55);
  font-size: 0.85rem;
  cursor: pointer;
  padding: 4px;
  line-height: 1;
}
.annonce-close:hover { color: #fff; }

  
/* ══ DONATION OPTIONS ═══════════════════════════════════════════════ */
.don-options { display: flex; flex-direction: column; gap: 12px; margin-top: 14px; }
.don-option {
  border: 2px solid var(--bleu-ciel);
  border-radius: 10px;
  padding: 14px;
  background: #fff;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.don-option-membre { border-color: var(--orange-hex); background: #fffdf7; }
.don-option-header { display: flex; align-items: center; gap: 10px; margin-bottom: 4px; }
.don-option-icon { font-size: 1.4rem; flex-shrink: 0; }
.don-option-titre { font-size: .88rem; font-weight: 700; color: var(--bleu-fonce); }
.don-option-sub { font-size: .72rem; color: var(--gris-texte); }
.iban-box { background: var(--bleu-leger); border-radius: 6px; padding: 10px 12px; font-size: .78rem; }
.iban-val { font-family: monospace; font-size: .9rem; font-weight: 700; color: var(--bleu-fonce); margin-bottom: 3px; }
.iban-bic { color: #666; margin-bottom: 3px; }
.iban-comm { color: #555; }
.btn-copy {
  display: block; width: 100%; margin-top: 8px;
  background: var(--bleu-hex); color: #fff; border: none;
  padding: 7px; border-radius: 5px; font-size: .75rem; font-weight: 700;
  cursor: pointer; font-family: inherit; transition: background .2s;
}
.btn-copy:hover { background: var(--bleu-fonce); }
.btn-copy.ok { background: #27ae60; }
.don-check { display: flex; align-items: center; gap: 6px; font-size: .75rem; color: #777; cursor: pointer; }
.don-check input { accent-color: var(--bleu-hex); }
.btn-devenir-membre {
  display: block; text-align: center; background: var(--orange-hex); color: #fff;
  padding: 11px; border-radius: 7px; font-size: .85rem; font-weight: 700;
  text-decoration: none; transition: background .2s;
}
.btn-devenir-membre:hover { background: var(--orange-sombre); text-decoration: none; }
.btn-deja-membre {
  display: block; text-align: center; font-size: .75rem; color: var(--bleu-hex);
  text-decoration: none; padding: 6px;
}
.btn-deja-membre:hover { text-decoration: underline; }


/* ── MENU BURGER MOBILE ─────────────────────────────────── */
.burger {
  display: none;
  flex-direction: column;
  gap: 5px;
  cursor: pointer;
  padding: 6px;
  margin-left: 8px;
  background: none;
  border: none;
  flex-shrink: 0;
}
.burger span {
  display: block;
  width: 24px;
  height: 2px;
  background: #fff;
  border-radius: 2px;
  transition: all .3s;
}
.burger.open span:nth-child(1) { transform: rotate(45deg) translate(5px,5px); }
.burger.open span:nth-child(2) { opacity: 0; }
.burger.open span:nth-child(3) { transform: rotate(-45deg) translate(5px,-5px); }

.mobile-menu {
  display: none;
  position: fixed;
  top: 64px;
  left: 0; right: 0;
  background: #0e3d6b;
  z-index: 999;
  flex-direction: column;
  border-top: 1px solid rgba(255,255,255,.15);
  box-shadow: 0 8px 20px rgba(0,0,0,.3);
  max-height: calc(100vh - 64px);
  overflow-y: auto;
}
.mobile-menu.open { display: flex; }
.mobile-menu a {
  padding: 13px 20px;
  color: rgba(255,255,255,.85);
  text-decoration: none;
  font-size: .88rem;
  border-bottom: 1px solid rgba(255,255,255,.08);
  display: flex;
  align-items: center;
  gap: 8px;
  transition: background .15s;
}
.mobile-menu a:hover { background: rgba(255,255,255,.1); color: #fff; }
.mobile-menu a.nav-cta-m {
  background: #FF9900;
  color: #fff;
  font-weight: 700;
}
.mobile-menu a.nav-cta-m:hover { background: #e68800; }
.mobile-menu a.nav-membre-m {
  background: rgba(255,255,255,.1);
  color: rgba(255,255,255,.9);
  font-weight: 600;
}

@media (max-width: 680px) {
  .main-wrap { grid-template-columns: 1fr; }
  .donation-card { position: static; order: -1; }
}

@media (min-width: 681px) {
  .main-wrap { grid-template-columns: 1fr 340px; }
}

@media (max-width: 768px) {
  /* Header */
  .header-nav { display: none !important; }
  .burger { display: flex; }
  .site-header { position: relative; }
  .header-inner { padding: 0 12px; min-height: 56px; }
  .header-brand h1 { font-size: .95rem; }
  .header-badge { display: none; }
  .logo-wrap { width: 40px; height: 40px; }
  .logo-wrap img { width: 40px; height: 40px; }

  /* Annonce — une seule ligne */
  .annonce-union { padding: 6px 32px 6px 10px; }
  .annonce-union h2 { font-size: .72rem; }
  .annonce-union p { display: none; }

  /* Urgence */
  .urgence { font-size: .72rem; }

  /* Progress */
  .progress-section { padding: 16px 12px; }
  .prog-stats { grid-template-columns: repeat(2,1fr); gap: 8px; }

  /* Tabs */
  .tabs-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
  .tabs { min-width: max-content; }
  .tab-btn { font-size: .72rem; padding: 8px 10px; }

  /* Main layout */
  .main-wrap { flex-direction: column; padding: 10px 12px; }
  .main-wrap > div { width: 100% !important; }

  /* Donation card */
  .donation-card { width: 100% !important; position: static !important; }
  .don-options { gap: 10px; }
  .don-option { padding: 12px; }
  .montant-grid { grid-template-columns: repeat(3,1fr); gap: 5px; }
  .montant-btn { font-size: .78rem; padding: 7px 4px; }

  /* Contenu pages */
  .tab-panel { padding: 0; }
  .tab-panel img { max-width: 100%; height: auto; }

  /* Global */
  body { overflow-x: hidden; }
  * { max-width: 100%; }
  img { max-width: 100%; height: auto; }
  table { font-size: .78rem; }
  pre, code { white-space: pre-wrap; word-break: break-word; }
}

@media (max-width: 480px) {
  .prog-stats { grid-template-columns: repeat(2,1fr); }
  .header-brand h1 { font-size: .88rem; }
}

/* ── TABS MOBILE ──────────────────────────────────────────────────── */
.tabs-mobile { display: none; padding: 10px 12px; }
.tabs-mobile select {
  width: 100%;
  padding: 10px 14px;
  border: 2px solid var(--bleu-ciel);
  border-radius: 8px;
  font-size: .92rem;
  font-family: inherit;
  color: var(--bleu-fonce);
  background: #fff;
  font-weight: 600;
  outline: none;
  -webkit-appearance: auto;
  cursor: pointer;
}

/* Bouton fixe "Soutenir" sur mobile */
.btn-soutenir-fixe {
  display: none;
  position: fixed;
  bottom: 0; left: 0; right: 0;
  background: #FF9900;
  color: #fff;
  text-align: center;
  padding: 14px;
  font-size: .92rem;
  font-weight: 700;
  z-index: 500;
  text-decoration: none;
  cursor: pointer;
  border: none;
  font-family: inherit;
}

@media (max-width: 900px) {
  .tabs-desktop { display: none !important; }
  .tabs-mobile { display: block; }
  .tabs-wrap { background: #fff; border-bottom: 1px solid var(--bleu-ciel); }
  .btn-soutenir-fixe { display: block; }
  /* Padding bas pour le bouton fixe */
  body { padding-bottom: 52px; }
}

/* ── BOUTONS MONTANT DONATION CARD ──────────────────────────────── */
.don-montant-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 5px;
  margin-bottom: 10px;
}
.don-mbtn {
  padding: 7px 4px;
  border: 1.5px solid var(--bleu-ciel);
  border-radius: 6px;
  background: var(--bleu-leger);
  color: var(--bleu-hex);
  font-size: .8rem;
  font-weight: 600;
  cursor: pointer;
  font-family: inherit;
  transition: all .15s;
  text-align: center;
}
.don-mbtn:hover, .don-mbtn.active {
  background: var(--bleu-hex);
  color: #fff;
  border-color: var(--bleu-hex);
}

/* ── COLONNES LAYOUT ─────────────────────────────────────────────────── */
#colonne-gauche {
  min-width: 0;
}
#colonne-droite {
  min-width: 0;
  align-self: start;
  position: sticky;
  top: 20px;
}
#colonne-droite > [data-widget] {
  margin-bottom: 16px;
}

/* ── ACTUALITÉS ─────────────────────────────────────────────────────── */
.news-section { margin: 20px 0; }
.news-item {
  background: #fff;
  border: 1px solid var(--bleu-ciel);
  border-left: 4px solid var(--bleu-hex);
  border-radius: 6px;
  padding: 14px 16px;
  margin-bottom: 12px;
}
.news-epingle {
  border-left-color: var(--orange-hex);
  background: #fffdf7;
}
.news-pin {
  font-size: .7rem;
  font-weight: 700;
  color: var(--orange-hex);
  text-transform: uppercase;
  letter-spacing: .05em;
  display: block;
  margin-bottom: 4px;
}
.news-titre {
  font-weight: 700;
  color: var(--bleu-fonce);
  font-size: .95rem;
  margin-bottom: 4px;
}
.news-date {
  font-size: .72rem;
  color: #aaa;
  margin-bottom: 8px;
}
.news-contenu {
  font-size: .85rem;
  color: #555;
  line-height: 1.6;
}
.news-contenu p { margin-bottom: 6px; }
</style>
</head>
<body>

<?php if ($annonce_active): ?>
<div class="annonce-union" id="annonce-union">
  <button class="annonce-close" onclick="document.getElementById('annonce-union').style.display='none'" title="Fermer">✕</button>
  <div class="annonce-date">Annonce — Mai 2026</div>
  <h2>🤝 <?= htmlspecialchars($annonce_titre) ?></h2>
  <p><?= htmlspecialchars($annonce_texte) ?></p>
  <div class="annonce-logos">
    <span>Piste 01 ça suffit !</span><span class="plus">+</span>
    <span>UBCNA</span><span class="plus">=</span>
    <span style="background:#FF9900;border-color:#FF9900;">ça suffit ! ASBL</span>
  </div>
</div>
<?php endif; ?>

<!-- HEADER MODERNE -->
<?php
// Fonctions helper pour le menu
function menuLabel($p) {
    $aff   = $p['affichage_menu'] ?? 'texte';
    $icone = htmlspecialchars($p['icone'] ?? '');
    $titre = htmlspecialchars(tdb($p, 'titre') ?? '');
    if ($aff === 'icone')       return $icone ? $icone : $titre;
    if ($aff === 'icone_texte') return $icone ? $icone . ' ' . $titre : $titre;
    return $titre;
}
function navBtnClass($p) {
    switch ($p['btn_style'] ?? '') {
        case 'cta':     return 'nav-cta';
        case 'white':   return 'nav-white';
        case 'outline': return 'nav-outline';
        default:        return $p['css_class'] ?? '';
    }
}
?>
<header class="site-header">
  <div class="header-inner">
    <div class="logo-wrap">
      <img src="medias/logo.png" alt="ça suffit ! ASBL" onerror="this.style.display='none'">
    </div>
    <div class="header-brand">
      <h1><span class="accent">ça suffit !</span> ASBL</h1>
      <span class="header-badge">Piste 01 · UBCNA · Union citoyenne</span>
    </div>
    <nav class="header-nav">
      <?php
      foreach ($menu_pages as $p):
        if (($p['menu_position'] ?? 'all') === 'tabs_only') continue;
        $id = $p['id'];
        $children = isset($menu_children[$id]) ? $menu_children[$id] : array();
        $btnCls = navBtnClass($p);

        if ($children):
          // Item avec sous-menu dropdown
      ?>
      <div class="nav-dropdown">
        <span class="nav-parent"><?= menuLabel($p) ?></span>
        <div class="nav-submenu">
          <?php foreach ($children as $c):
            $cHref = !empty($c['lien_url']) ? '/'.$c['lien_url'] : '#';
            $cOnclick = empty($c['lien_url']) ? " onclick=\"showTab('{$c['slug']}', this); return false;\"" : '';
          ?>
          <a href="<?= htmlspecialchars($cHref) ?>"<?= $cOnclick ?> class="<?= htmlspecialchars(navBtnClass($c)) ?>">
            <?= menuLabel($c) ?>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php elseif (!empty($p['lien_url'])): ?>
        <a href="/<?= htmlspecialchars($p['lien_url']) ?>" class="<?= htmlspecialchars($btnCls) ?>">
          <?= menuLabel($p) ?>
        </a>
      <?php else: ?>
        <a href="#" onclick="showTab('<?= $p['slug'] ?>', this); return false;"
           class="<?= htmlspecialchars($btnCls) ?>">
          <?= menuLabel($p) ?>
        </a>
      <?php endif; ?>
      <?php endforeach; ?>
    </nav>
      <a href="?setlang=<?= LANG === 'fr' ? 'nl' : 'fr' ?>"
         class="lang-switch"
         title="<?= LANG === 'fr' ? 'Schakel naar Nederlands' : 'Passer en français' ?>">
        <?= LANG === 'fr' ? 'NL' : 'FR' ?>
      </a>
      <button class="burger" id="burger" onclick="toggleBurger()" aria-label="Menu">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</header>
<nav class="mobile-menu" id="mobile-menu">
  <?php
  // Burger : afficher parents + enfants à plat
  $all_mobile = array();
  foreach ($menu_pages as $p) {
    if (($p['menu_position'] ?? 'all') === 'tabs_only') continue;
    $all_mobile[] = $p;
    // Ajouter les enfants en retrait
    $pid = $p['id'];
    if (isset($menu_children[$pid])) {
      foreach ($menu_children[$pid] as $c) {
        $c['_child'] = true;
        $all_mobile[] = $c;
      }
    }
  }
  foreach ($all_mobile as $p):
    $isChild = !empty($p['_child']);
    $indent  = $isChild ? 'padding-left:24px;font-size:.75rem;opacity:.85;' : '';
    $prefix  = $isChild ? '└ ' : '';
  ?>
  <?php if (!empty($p['lien_url'])): ?>
    <a href="/<?= htmlspecialchars($p['lien_url']) ?>" style="<?= $indent ?>" class="<?= navBtnClass($p) ?>">
      <?= $prefix ?><?= menuLabel($p) ?>
    </a>
  <?php else: ?>
    <a href="#" style="<?= $indent ?>"
       onclick="showTab('<?= $p['slug'] ?>', this); document.getElementById('burger').classList.remove('open'); document.getElementById('mobile-menu').classList.remove('open'); return false;"
       class="<?= navBtnClass($p) ?>">
      <?= $prefix ?><?= menuLabel($p) ?>
    </a>
  <?php endif; ?>
  <?php endforeach; ?>
</nav>

<!-- URGENCE -->
<div class="urgence">
  ✈ &nbsp; <?= htmlspecialchars($urgence_texte) ?> &nbsp; ✈
</div>

<!-- ZONE HEADER — widgets globaux (affichés sur toutes les pages) -->
<?php
$header_widgets = isset($page_widgets['__header__']) ? $page_widgets['__header__'] : array();
foreach ($header_widgets as $w_slug) {
    $w_file = __DIR__ . '/includes/widgets/' . $w_slug . '.php';
    if (!file_exists($w_file)) continue;
    $widget_no_scale = false;
    ob_start(); include $w_file; $w_html = ob_get_clean();
    if ($widget_no_scale) {
        echo '<div data-no-scale="1">' . $w_html . '</div>';
    } else {
        echo $w_html;
    }
}
?>
<!-- TABS -->
<div class="tabs-wrap">
  <!-- Desktop : tabs parents seulement -->
  <div class="tabs tabs-desktop">
    <?php $first_tab = true; foreach ($menu_pages as $p):
      if (($p['menu_position'] ?? 'all') === 'header') continue;
      $pid = $p['id'];
      $hasChildren = isset($menu_children[$pid]) && count($menu_children[$pid]) > 0;
    ?>
    <?php if (!empty($p['lien_url'])): ?>
      <a href="/<?= htmlspecialchars($p['lien_url']) ?>" class="tab-btn <?= navBtnClass($p) ?>" style="text-decoration:none">
        <?= menuLabel($p) ?>
      </a>
    <?php else: ?>
      <button class="tab-btn <?= $first_tab ? 'active' : '' ?> <?= navBtnClass($p) ?>"
              data-slug="<?= $p['slug'] ?>"
              data-has-children="<?= $hasChildren ? '1' : '0' ?>"
              onclick="showTab('<?= $p['slug'] ?>', this)">
        <?= menuLabel($p) ?>
      </button>
    <?php endif; ?>
    <?php $first_tab = false; endforeach; ?>
  </div>

  <!-- Mobile : select -->
  <div class="tabs-mobile">
    <select id="tab-select" onchange="showTabMobile(this.value)">
      <?php $first_sel = true; foreach ($menu_pages as $p):
        if (($p['menu_position'] ?? 'all') === 'header') continue;
      ?>
      <option value="<?= !empty($p['lien_url']) ? 'url:'.$p['lien_url'] : $p['slug'] ?>" <?= $first_sel?'selected':'' ?>>
        <?= menuLabel($p) ?>
      </option>
      <?php $first_sel = false; endforeach; ?>
    </select>
  </div>
</div>

<!-- Sous-tabs (rangée secondaire, affichée si le tab actif a des enfants) -->
<div class="subtabs-wrap" id="subtabs-wrap">
  <div class="subtabs-mobile-wrap">
    <span class="subtabs-mobile-lbl">Section&nbsp;▸</span>
    <select id="subtabs-sel" class="subtabs-sel" onchange="showSubTabMobile(this.value)"></select>
  </div>
  <div class="subtabs" id="subtabs-inner">
    <?php foreach ($menu_pages as $p):
      $pid = $p['id'];
      if (!isset($menu_children[$pid]) || count($menu_children[$pid]) === 0) continue;
    ?>
    <div class="subtabs-group" id="subtabs-<?= $p['slug'] ?>" style="display:none">
      <?php // Le parent lui-même est le premier sous-tab avec son propre slug ?>
      <button class="subtab-btn active"
              data-slug="<?= $p['slug'] ?>"
              onclick="showSubTab('<?= $p['slug'] ?>', this)">
        <?= menuLabel($p) ?>
      </button>
      <?php foreach ($menu_children[$pid] as $c): ?>
      <button class="subtab-btn"
              data-slug="<?= $c['slug'] ?>"
              onclick="showSubTab('<?= $c['slug'] ?>', this)">
        <?= menuLabel($c) ?>
      </button>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- MAIN -->
<div class="main-wrap" id="don">

  <!-- COLONNE GAUCHE -->
  <div id="colonne-gauche">

    <!-- ── MOBILISATION ── -->
        <div class="tab-panel <?= $first_tab_slug==='mobilisation' ? 'active' : '' ?>" id="tab-mobilisation">
      <?= tdb($tabs_content['mobilisation'] ?? [], 'contenu') ?? '' ?>
    </div>
<!-- ── POURQUOI LA 01 ── -->
        <div class="tab-panel <?= $first_tab_slug==='pourquoi' ? 'active' : '' ?>" id="tab-pourquoi">
      <?= tdb($tabs_content['pourquoi'] ?? [], 'contenu') ?? '' ?>
    </div>
<!-- ── INFORMATIONS ── -->
        <div class="tab-panel <?= $first_tab_slug==='informations' ? 'active' : '' ?>" id="tab-informations">
      <?= tdb($tabs_content['informations'] ?? [], 'contenu') ?? '' ?>
    </div>
<!-- ── NOS DEMANDES ── -->
    <div class="tab-panel <?= $first_tab_slug==='demandes' ? 'active' : '' ?>" id="tab-demandes">
      <?= tdb($tabs_content['demandes'] ?? [], 'contenu') ?? '' ?>
    </div>

<!-- ── NOS ALLIÉS ── -->
        <div class="tab-panel <?= $first_tab_slug==='allies' ? 'active' : '' ?>" id="tab-allies">
      <?= tdb($tabs_content['allies'] ?? [], 'contenu') ?? '' ?>
    </div>
<!-- ── NEWSLETTER ── -->
    <div class="tab-panel <?= $first_tab_slug==='newsletter' ? 'active' : '' ?>" id="tab-newsletter">
      <!-- Contenu via widget newsletter -->
    </div><!-- /tab-newsletter -->

    <!-- ── ACTUALITÉS ── -->
    <div class="tab-panel <?= $first_tab_slug==='actualites' ? 'active' : '' ?>" id="tab-actualites">
      <!-- Contenu via widget news -->
    </div><!-- /tab-actualites -->

    <?php
    // Tabs dynamiques — pages BDD qui n'ont pas de tab hardcodé ci-dessus
    $hardcoded_tabs = array('mobilisation','pourquoi','informations','demandes','allies','newsletter','actualites');

    // Construire la liste complète : parents + enfants
    $all_dynamic_pages = array();
    foreach ($menu_pages as $p) {
        $all_dynamic_pages[] = $p;
        // Ajouter les enfants
        $pid = $p['id'];
        if (isset($menu_children[$pid])) {
            foreach ($menu_children[$pid] as $c) {
                $all_dynamic_pages[] = $c;
            }
        }
    }

    foreach ($all_dynamic_pages as $p):
        if (empty($p['slug'])) continue;
        if (!empty($p['lien_url'])) continue;
        if (in_array($p['slug'], $hardcoded_tabs)) continue;
        if (($p['menu_position'] ?? 'all') === 'header') continue;
    ?>
    <div class="tab-panel <?= $first_tab_slug===$p['slug'] ? 'active' : '' ?>" id="tab-<?= htmlspecialchars($p['slug']) ?>">
      <?php
      // Contenu texte uniquement — les widgets sont gérés par updateColonneDroite() en JS
      $contenu = tdb($p, 'contenu');
      if (!empty($contenu)): ?>
        <?= $contenu ?>
      <?php endif; ?>
    </div><!-- /tab-<?= htmlspecialchars($p['slug']) ?> -->
    <?php endforeach; ?>

  </div><!-- /colonne gauche -->

<!-- COLONNE DROITE -->
  <div id="colonne-droite">
    <?php
    // Générer tous les widgets uniques une seule fois (hors __header__)
    $all_widget_slugs = array();
    foreach ($page_widgets as $page_slug => $slugs) {
        if ($page_slug === '__header__') continue;
        foreach ($slugs as $ws) {
            if (!in_array($ws, $all_widget_slugs)) $all_widget_slugs[] = $ws;
        }
    }
    foreach ($all_widget_slugs as $w_slug):
        $w_file = __DIR__ . '/includes/widgets/' . $w_slug . '.php';
        if (!file_exists($w_file)) continue;
    ?>
    <?php
    $widget_no_scale = (strpos(file_get_contents($w_file), '$widget_no_scale = true') !== false);
    ?>
    <div data-widget="<?= $w_slug ?>" <?= $widget_no_scale ? 'data-no-scale="1"' : '' ?> style="display:none">
      <?php include $w_file; ?>
    </div>
    <?php endforeach; ?>
  </div><!-- /colonne droite -->

</div><!-- /main-wrap -->

<div class="margepied"></div>

<?php
$fb_url = cfg('facebook_url','');
$ig_url = cfg('instagram_url','');
$wa_url = cfg('whatsapp_url','');
$site_email = cfg('site_email', 'info@casuffit.be');
?>
<footer id="pied">
  <div class="pied-grid">

    <!-- Colonne 1 : Logo + mission -->
    <div class="pied-col pied-col-mission">
      <img src="medias/logo.png" alt="Piste 01, ça suffit !" class="pied-logo">
      <p class="pied-mission">
        <strong>ça suffit ! ASBL</strong><br>
        Mobilisation citoyenne contre les nuisances de la piste 01 de Bruxelles-National.
      </p>
    </div>

    <!-- Colonne 2 : Navigation -->
    <nav class="pied-col pied-col-nav" aria-label="Navigation pied de page">
      <h4>Navigation</h4>
      <ul>
        <li><a href="#" onclick="showTab('mobilisation', this); return false;">Accueil</a></li>
        <li><a href="#" onclick="showTab('informations', this); return false;">Informations</a></li>
        <li><a href="#" onclick="showTab('demandes', this); return false;">Nos demandes</a></li>
        <li><a href="#" onclick="showTab('allies', this); return false;">Nos alliés</a></li>
        <li><a href="#don">Nous soutenir</a></li>
      </ul>
    </nav>

    <!-- Colonne 3 : Contact + RS -->
    <div class="pied-col pied-col-contact">
      <h4>Nous contacter</h4>
      <p class="pied-iban">BELFIUS — <?= htmlspecialchars($iban) ?></p>
      <p class="pied-comm">Communication : <em>Nom - prénom - Action en justice ASBL</em></p>
      <p><a href="mailto:<?= htmlspecialchars($site_email) ?>" class="pied-email">✉ <?= htmlspecialchars($site_email) ?></a></p>

      <?php if ($fb_url || $ig_url || $wa_url): ?>
      <div class="pied-rs">
        <?php if ($fb_url): ?>
        <a href="<?= htmlspecialchars($fb_url) ?>" target="_blank" rel="noopener" title="Facebook" class="pied-rs-icon pied-rs-fb">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="white" aria-hidden="true"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
        </a>
        <?php endif; ?>
        <?php if ($ig_url): ?>
        <a href="<?= htmlspecialchars($ig_url) ?>" target="_blank" rel="noopener" title="Instagram" class="pied-rs-icon pied-rs-ig">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="white" aria-hidden="true"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
        </a>
        <?php endif; ?>
        <?php if ($wa_url): ?>
        <a href="<?= htmlspecialchars($wa_url) ?>" target="_blank" rel="noopener" title="WhatsApp" class="pied-rs-icon pied-rs-wa">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="white" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
        </a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

  </div><!-- /pied-grid -->

  <div class="pied-bottom">
    <span id="copyright-trigger" onclick="handleAdminClick()" style="cursor:default;user-select:none">© <?= date('Y') ?> Piste 01, ça suffit ! ASBL &nbsp;·&nbsp; Tous droits réservés</span>
    <span style="margin-left:16px">
      <a href="/politique-confidentialite" style="color:#aaa;font-size:.75rem;text-decoration:none">Politique de confidentialité</a>
      &nbsp;·&nbsp;
      <button onclick="rgpdReset()" style="background:none;border:none;color:#aaa;font-size:.75rem;cursor:pointer;padding:0;text-decoration:underline">Paramètres cookies</button>
    </span>
  </div>
</footer>



<!-- PANNEAU ADMIN (caché) -->


<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
// ── MOT DE PASSE ADMIN ─────────────────────────────────
// Changez cette valeur pour personnaliser le mot de passe
const ADMIN_PASSWORD = 'piste01admin';

// ── MONTANTS — chargés depuis l'API admin ────────────────
let OBJECTIF = <?= $objectif ?>;
let RECOLT   = <?= $recolte ?>;

// Charger les montants depuis l'API
fetch('api/config.php')
  .then(function(r) { return r.json(); })
  .then(function(data) {
    if (data && data.ok) {
      OBJECTIF = data.objectif;
      RECOLT   = data.recolte;
      var pct  = Math.min(100, Math.round(RECOLT / OBJECTIF * 100));
      document.getElementById('bar-fill').style.width = pct + '%';
      document.getElementById('pct-val').textContent  = pct + '%';
      document.getElementById('stat-objectif').textContent = OBJECTIF.toLocaleString('fr-BE') + ' €';
      document.getElementById('b-objectif').textContent    = OBJECTIF.toLocaleString('fr-BE') + ' €';
      document.getElementById('prog-objectif-val').innerHTML =
        '<span id="montant-recolt">0</span> € / <b>' + OBJECTIF.toLocaleString('fr-BE') + ' €</b>';
      if (data.texte) {
        var el = document.querySelector('.prog-label');
        if (el) el.textContent = '🎯 Objectif — ' + data.texte;
      }
      animCounter('montant-recolt', 0, RECOLT, 1500);
    }
  })
  .catch(function() {
    // Valeurs par défaut si API indisponible
    var pct = Math.min(100, Math.round(RECOLT / OBJECTIF * 100));
    document.getElementById('bar-fill').style.width = pct + '%';
  });


function animCounter(id, from, to, ms) {
  const el = document.getElementById(id);
  const t0 = performance.now();
  function tick(now) {
    const p = Math.min((now - t0) / ms, 1);
    el.textContent = Math.round(from + (to - from) * (1 - Math.pow(1-p,3))).toLocaleString('fr-BE');
    if (p < 1) requestAnimationFrame(tick);
  }
  requestAnimationFrame(tick);
}

// Mapping page -> widgets (pour colonne droite dynamique)
var pageWidgets    = <?= json_encode($page_widgets) ?>;
var pageWidgetsPos = <?= json_encode($page_widgets_pos) ?>;
var firstTabSlug = <?= json_encode($first_tab_slug) ?>;

// Mettre à jour la colonne droite selon le tab
// Gérer max-width quand pas de colonne droite
function updateSansDroiteClass() {
  var droite = document.getElementById('colonne-droite');
  var don    = document.getElementById('don');
  if (!droite || !don) return;
  var visible = droite.style.display !== 'none' && droite.offsetWidth > 0;
  don.classList.toggle('sans-droite', !visible);
}

function updateColonneDroite(slug) {
    var col   = document.getElementById('colonne-droite');
    var wrap  = document.getElementById('don');
    var pos   = pageWidgetsPos[slug] || {};
    var allW  = pageWidgets[slug] || [];

    var droite = allW.filter(function(w){ return (pos[w]||'droite') === 'droite'; });
    var gauche = allW.filter(function(w){ return pos[w] === 'gauche'; });

    // Colonne droite
    var hasDroite = droite.length > 0;
    if (col) {
        col.querySelectorAll('[data-widget]').forEach(function(el) {
            el.style.display = droite.indexOf(el.getAttribute('data-widget')) >= 0 ? '' : 'none';
        });
        col.style.display = hasDroite ? '' : 'none';
    }
    if (wrap) wrap.style.gridTemplateColumns = hasDroite ? '' : '1fr';

    // Widgets gauche — déplacer dans colonne-gauche (pas cloner)
    var gaucheCol = document.getElementById('colonne-gauche');
    if (gaucheCol) {
        // Remettre les anciens widgets gauche dans colonne-droite
        gaucheCol.querySelectorAll('[data-widget-left]').forEach(function(el) {
            var wSlug = el.getAttribute('data-widget-left');
            el.removeAttribute('data-widget-left');
            el.setAttribute('data-widget', wSlug);
            el.style.display = 'none';
            if (col) col.appendChild(el);
        });
        // Déplacer les nouveaux widgets gauche
        gauche.forEach(function(wSlug) {
            // Chercher dans colonne-droite ET dans colonne-gauche (déjà déplacé)
            var src = col ? col.querySelector('[data-widget="'+wSlug+'"]') : null;
            if (!src) src = gaucheCol ? gaucheCol.querySelector('[data-widget-left="'+wSlug+'"]') : null;
            if (src) {
                // Déjà en place dans colonne-gauche
                if (src.hasAttribute('data-widget-left')) {
                    src.style.display = '';
                    return;
                }
                src.removeAttribute('data-widget');
                src.setAttribute('data-widget-left', wSlug);
                src.style.display = '';
                gaucheCol.insertBefore(src, gaucheCol.firstChild);
                if (window.FB && src.querySelector('.fb-page')) {
                    window.FB.XFBML.parse(src);
                }
            }
        });
    }
}

function showTab(id, el) {
  // Forcer Leaflet à recalculer la taille si le widget vols est visible
  setTimeout(function(){ if(typeof window.vbrInvalidate==='function') window.vbrInvalidate(); }, 150);
  // Si pas de tab-panel pour ce slug → scroll vers #don
  var panel = document.getElementById('tab-' + id);
  if (!panel) {
    var donEl = document.getElementById('don');
    if (donEl) donEl.scrollIntoView({behavior:'smooth', block:'start'});
    return;
  }
  document.querySelectorAll('.tab-panel').forEach(function(p) { p.classList.remove('active'); });
  document.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
  document.querySelectorAll('.menu-inner a, .header-nav a').forEach(function(a) { a.classList.remove('active'); });
  panel.classList.add('active');
  if (el) el.classList.add('active');
  updateColonneDroite(id);
  setTimeout(updateSansDroiteClass, 100);
  // Init rose des vents si elle devient visible
  if (typeof window.rvwInitYear === 'function') window.rvwInitYear();
  // Sync le select mobile
  var sel = document.getElementById('tab-select');
  if (sel) sel.value = id;
  // ── Gestion sous-tabs ──
  var subtabsWrap = document.getElementById('subtabs-wrap');
  var allGroups = document.querySelectorAll('.subtabs-group');
  allGroups.forEach(function(g) { g.style.display = 'none'; });
  var myGroup = document.getElementById('subtabs-' + id);
  if (myGroup && subtabsWrap) {
    myGroup.style.display = 'flex';
    subtabsWrap.classList.add('visible');
    // Activer le bouton parent (data-slug === id) dans les sous-tabs
    myGroup.querySelectorAll('.subtab-btn').forEach(function(b){
      b.classList.remove('active');
      // Cacher les panels enfants sauf le parent
      if (b.dataset.slug !== id) {
        var p2 = document.getElementById('tab-' + b.dataset.slug);
        if (p2) p2.classList.remove('active');
      }
    });
    var parentBtn = myGroup.querySelector('[data-slug="'+id+'"]');
    if (parentBtn) parentBtn.classList.add('active');
    // Sync select mobile sous-tabs
    var stSel = document.getElementById('subtabs-sel');
    if(stSel) {
      stSel.innerHTML = '';
      myGroup.querySelectorAll('.subtab-btn').forEach(function(b){
        var opt = document.createElement('option');
        opt.value = b.dataset.slug;
        opt.textContent = b.textContent.trim();
        if(b.classList.contains('active')) opt.selected = true;
        stSel.appendChild(opt);
      });
    }
  } else if (subtabsWrap) {
    subtabsWrap.classList.remove('visible');
  }
  // Cacher la donation card sur mobile sauf pour "soutenir" et "mobilisation"
  var card = document.querySelector('.donation-card');
  if (card) {
    var showCard = (id === 'mobilisation' || id === 'soutenir');
    card.style.display = window.innerWidth <= 900 ? (showCard ? '' : 'none') : '';
  }
  // Sur mobile : remonter juste sous les tabs
  if (window.innerWidth <= 900) {
    var tabsWrap = document.querySelector('.tabs-wrap');
    if (tabsWrap) window.scrollTo({top: tabsWrap.offsetTop - 60, behavior: 'smooth'});
  }
}

function showSubTab(id, el, noScroll) {
  var group = el ? el.closest('.subtabs-group') : null;
  // Mettre à jour l'actif dans les sous-tabs
  if (group) {
    group.querySelectorAll('.subtab-btn').forEach(function(b){ b.classList.remove('active'); });
  }
  if (el) el.classList.add('active');

  // Cacher tous les panels actifs du groupe
  if (group) {
    group.querySelectorAll('.subtab-btn').forEach(function(b){
      var p = document.getElementById('tab-' + b.dataset.slug);
      if (p) p.classList.remove('active');
    });
  }

  // Afficher le panel demandé
  var panel = document.getElementById('tab-' + id);
  if (panel) {
    panel.classList.add('active');
    updateColonneDroite(id);
    setTimeout(updateSansDroiteClass, 100);
  } else {
    // Page widget-only (pas de tab-panel) — init les widgets
    updateColonneDroite(id);
    setTimeout(updateSansDroiteClass, 100);
    if (typeof window.rvwInitYear === 'function') window.rvwInitYear();
  }

  // Sync select mobile
  var stSel = document.getElementById('subtabs-sel');
  if(stSel) stSel.value = id;

  if (!noScroll && window.innerWidth <= 900) {
    var w = document.querySelector('.subtabs-wrap');
    if (w) window.scrollTo({top: w.offsetTop - 60, behavior: 'smooth'});
  }
}

function showSubTabMobile(val) {
  showSubTab(val, null);
}

function showTabMobile(val) {
  if (val.startsWith('url:')) {
    window.location.href = val.substring(4);
  } else {
    showTab(val, null);
  }
}

let curMontant = 50;

function selectMontant(btn) {
  document.querySelectorAll('.don-mbtn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  const v = btn.dataset.v;
  if (v === '') {
    document.getElementById('libre-wrap').style.display = 'block';
    curMontant = null;
  } else {
    document.getElementById('libre-wrap').style.display = 'none';
    curMontant = parseInt(v);
    genQRAnon(curMontant);
  }
}

function updateMontantLibre(val) {
  const v = parseInt(val);
  if (v > 0) { curMontant = v; genQRAnon(v); }
}

// ── QR Code anonyme ──────────────────────────────────────────────────────
var qr_anon_obj = null;

function genQRAnon(montant) {
  montant = montant || curMontant;
  var el = document.getElementById('qrcode-anonyme');
  if (!el) return;
  el.innerHTML = '';
  var iban_raw = '<?= preg_replace('/\s+/', '', cfg('iban','BE41068901496910')) ?>';
  var epc = ['BCD','002','1','SCT',
    '<?= cfg('bic','GKCCBEBB') ?>',
    '<?= addslashes(cfg('beneficiaire','ca suffit ! ASBL')) ?>',
    iban_raw,
    montant ? 'EUR' + parseFloat(montant).toFixed(2) : '',
    '', 'DON CASUFFIT <?= date('Y') ?>', ''].join('\n');
  var src = 'https://quickchart.io/qr?text=' + encodeURIComponent(epc) + '&size=150&margin=1&ecLevel=M';
  var img = document.createElement('img');
  img.width = 150; img.height = 150;
  img.alt = 'QR code don';
  img.onerror = function() {
    this.src = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' + encodeURIComponent(epc);
  };
  img.src = src;
  el.appendChild(img);
}

function copyIBAN() {
  var iban = '<?= cfg('iban','BE41 0689 0149 6910') ?>';
  navigator.clipboard.writeText(iban).then(function() {
    var btn = document.getElementById('copy-btn');
    btn.textContent = '✓ Copié !'; btn.classList.add('ok');
    setTimeout(function(){ btn.textContent = '📋 Copier l\'IBAN'; btn.classList.remove('ok'); }, 2500);
  });
}

// Initialiser au chargement
document.addEventListener('DOMContentLoaded', function() {
  // Replacer colonne-droite dans main-wrap si le navigateur l'a sorti
  var col = document.getElementById('colonne-droite');
  var don = document.getElementById('don');
  if (col && don && col.parentElement !== don) {
    var gauche = document.getElementById('colonne-gauche');
    if (gauche) {
      don.insertBefore(col, gauche.nextSibling);
    } else {
      don.appendChild(col);
    }
  }
  genQRAnon(curMontant);

  // ── Gestion du hash dans l'URL (#slug) ────────────────────────────────
  var hash = window.location.hash.replace('#', '').trim();
  if (hash) {
    // 1. Chercher un sous-tab avec ce data-slug
    var subBtn = document.querySelector('.subtab-btn[data-slug="' + hash + '"]');
    if (subBtn) {
      // Trouver le tab parent (le groupe de sous-tabs)
      var group = subBtn.closest('.subtabs-group');
      var parentSlug = group ? group.id.replace('subtabs-', '') : firstTabSlug;
      // Activer le tab parent d'abord
      var parentBtn = document.querySelector('.tab-btn[onclick*="' + parentSlug + '"]');
      showTab(parentSlug, parentBtn);
      // Puis le sous-tab avec updateColonneDroite sur le slug exact
      setTimeout(function(){
        subBtn.click();
        updateColonneDroite(hash);
  setTimeout(updateSansDroiteClass, 100);
      }, 100);
    } else {
      // 2. Chercher un tab principal
      var tabBtn = document.querySelector('.tab-btn[onclick*="' + hash + '"]');
      if (tabBtn) {
        showTab(hash, tabBtn);
      } else {
        updateColonneDroite(firstTabSlug);
  setTimeout(updateSansDroiteClass, 100);
      }
    }
  } else {
    updateColonneDroite(firstTabSlug);
  setTimeout(updateSansDroiteClass, 100);
  // Init widgets sur pages sans tab panel (widget-only)
  if (!document.getElementById('tab-' + firstTabSlug)) {
    if (typeof window.rvwInitYear === 'function') window.rvwInitYear();
  }
  }
});

</script>

<script>
/* ── Newsletter form handler ── */
(function(){
  var form = document.getElementById('newsletter-form');
  if (!form) return;

  form.addEventListener('submit', async function(e) {
    e.preventDefault();

    var email   = document.getElementById('nl-email').value.trim();
    var rgpd    = document.getElementById('nl-rgpd').checked;
    var btn     = document.getElementById('btn-subscribe');
    var msgDiv  = document.getElementById('form-msg');

    msgDiv.className = 'form-msg';
    msgDiv.textContent = '';

    if (!email) { showMsg('Veuillez saisir votre adresse email.', 'err'); return; }
    if (!rgpd)  { showMsg('Vous devez accepter la politique RGPD pour vous inscrire.', 'err'); return; }

    btn.disabled = true;
    btn.textContent = '⏳ Envoi en cours…';

    var data = new FormData(form);
    /* Convertir la case bénévole en valeur envoyable */
    if (document.getElementById('nl-benevole').checked) data.set('benevole', '1');

    try {
      var resp = await fetch('newsletter/subscribe.php', { method: 'POST', body: data });
      var json = await resp.json();
      if (json.ok) {
        showMsg(json.msg || 'Merci ! Vérifiez vos emails pour confirmer votre inscription.', 'ok');
        form.reset();
      } else {
        showMsg(json.msg || 'Une erreur est survenue. Veuillez réessayer.', 'err');
      }
    } catch(err) {
      showMsg('Erreur de connexion. Vérifiez votre connexion internet et réessayez.', 'err');
    } finally {
      btn.disabled = false;
      btn.textContent = '✉ S\u2019inscrire à la newsletter';
    }
  });

  function showMsg(txt, type) {
    var d = document.getElementById('form-msg');
    d.textContent = txt;
    d.className = 'form-msg ' + type;
  }
})();
</script>



<script>
// ── Menu burger mobile ────────────────────────────────────────────────────
function toggleBurger() {
  var burger = document.getElementById('burger');
  var menu   = document.getElementById('mobile-menu');
  burger.classList.toggle('open');
  menu.classList.toggle('open');
}
document.addEventListener('DOMContentLoaded', function() {
  var mlinks = document.querySelectorAll('.mobile-menu a');
  mlinks.forEach(function(a) {
    a.addEventListener('click', function() {
      document.getElementById('burger').classList.remove('open');
      document.getElementById('mobile-menu').classList.remove('open');
    });
  });
});
</script>

<!-- ── Bannière RGPD ── -->
<div id="rgpd-banner" style="display:none;position:fixed;bottom:0;left:0;right:0;z-index:9999;
  background:#fff;border-top:3px solid #1673B2;box-shadow:0 -4px 24px rgba(0,0,0,.15);
  padding:18px 20px;flex-direction:column;gap:12px;max-width:100%;font-family:-apple-system,Arial,sans-serif">

  <div style="display:flex;align-items:flex-start;gap:12px;flex-wrap:wrap">
    <div style="font-size:1.1rem">🍪</div>
    <div style="flex:1;min-width:220px">
      <div style="font-weight:700;font-size:.95rem;color:#0e3d6b;margin-bottom:4px">Ce site utilise des cookies</div>
      <div style="font-size:.8rem;color:#555;line-height:1.5">
        Votre session de connexion membre est <strong>strictement nécessaire</strong> et ne requiert pas de consentement.
        Nous utilisons aussi Google Analytics (statistiques anonymes) et un widget Facebook (réseaux sociaux).
        <button onclick="rgpdToggleDetail()" style="background:none;border:none;color:#1673B2;cursor:pointer;font-size:.8rem;text-decoration:underline;padding:0">Personnaliser</button>
      </div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <button onclick="rgpdRefuseAll()" style="padding:8px 16px;border:1.5px solid #ccc;background:#fff;border-radius:8px;cursor:pointer;font-size:.82rem;color:#555;font-weight:600">Refuser</button>
      <button onclick="rgpdAcceptAll()" style="padding:8px 18px;background:#1673B2;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:.82rem;font-weight:700">Tout accepter</button>
    </div>
  </div>

  <div id="rgpd-detail" style="display:none;border-top:1px solid #eee;padding-top:12px">
    <div style="font-size:.82rem;color:#333;margin-bottom:10px;font-weight:600">Choisissez vos préférences :</div>
    <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:12px">
      <label style="display:flex;align-items:flex-start;gap:10px;cursor:default">
        <input type="checkbox" checked disabled style="margin-top:3px;accent-color:#1673B2">
        <span style="font-size:.8rem"><strong>Session membre</strong> (nécessaire) — Cookie de session PHP pour l'espace membre. Ne peut pas être refusé.</span>
      </label>
      <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer">
        <input type="checkbox" id="rgpd-analytics" style="margin-top:3px;accent-color:#1673B2">
        <span style="font-size:.8rem"><strong>Google Analytics</strong> (statistiques) — Mesure du trafic anonymisé. Données traitées par Google LLC aux États-Unis.</span>
      </label>
      <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer">
        <input type="checkbox" id="rgpd-social" style="margin-top:3px;accent-color:#1877F2">
        <span style="font-size:.8rem"><strong>Réseaux sociaux</strong> (Facebook) — Widget de la page Facebook. Des cookies tiers Meta peuvent être déposés.</span>
      </label>
    </div>
    <div style="display:flex;gap:8px;justify-content:flex-end">
      <button onclick="rgpdSaveChoices()" style="padding:8px 18px;background:#0e3d6b;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:.82rem;font-weight:700">Enregistrer mes choix</button>
    </div>
    <div style="font-size:.72rem;color:#aaa;margin-top:8px">
      Conformément au RGPD et à la loi belge du 13 juin 2005 relative aux communications électroniques.
      <a href="/politique-confidentialite" style="color:#1673B2">Politique de confidentialité</a>
    </div>
  </div>
</div>

<script>
// Afficher la bannière au chargement si pas encore de choix
document.addEventListener('DOMContentLoaded', function() {
  if (typeof rgpdInit === 'function') rgpdInit();
});
</script>

</body>
</html>