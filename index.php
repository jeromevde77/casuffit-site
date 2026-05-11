<?php
// index.php — Site ça suffit ! ASBL (v2 - look ancien site)
require_once __DIR__ . '/config.php';


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
    $all_menu = $db->query("SELECT id, slug, titre, icone, css_class, btn_style, parent_id, menu_position, lien_url, affichage_menu FROM pages WHERE dans_menu=1 AND visible=1 ORDER BY COALESCE(parent_id,0) ASC, ordre ASC")->fetchAll();
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
        $rows = $db->query("SELECT id, slug, titre, icone, css_class, menu_position, lien_url, affichage_menu FROM pages WHERE dans_menu=1 AND visible=1 ORDER BY ordre ASC")->fetchAll();
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
<html lang="fr">
<head>
  <meta charset="UTF-8">

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
      if (banner) banner.style.display = 'flex';
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
    }
  })();
  </script>
  <link rel="icon" type="image/x-icon" href="/favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">
  <link rel="apple-touch-icon" sizes="192x192" href="/favicon-192.png">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
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
.tab-panel.active { display: block; }

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
    $titre = htmlspecialchars($p['titre']);
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
    if (file_exists($w_file)) include $w_file;
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

      <div class="lettre-intro">
        <p>Chers amis, chers membres,<br>
        la Région de Bruxelles-Capitale et plusieurs communes se mobilisent désormais frontalement contre la RNP 07. Il est urgent de réagir et de <strong>ne pas laisser les riverains de la piste 01 payer la facture des erreurs passées.</strong></p>
      </div>



      <div class="orange section-title">Notre position, claire depuis 15 ans</div>
      <p class="content-text">Aucun citoyen ne souhaite être survolé de manière injuste et récurrente. C'est le combat des habitants de Waterloo, Lasne, Braine-l'Alleud, La Hulpe et des communes limitrophes depuis plus de vingt ans.</p>
      <p class="content-text">Seul le survol <strong>justifié par les conditions météo</strong> est acceptable : piste 01 par vent de Nord <em>fort</em>, piste 07 par vent d'Est <em>fort</em> — au-delà de <strong>8 nœuds sans rafales</strong>.</p>

      <div class="cadre-bleu">
        <strong>Notre message central — inchangé depuis 15 ans :</strong><br>
        Le véritable problème réside dans les normes de vent, telles qu'elles ont été dévoyées depuis 2003. Nous défendons une solution structurelle, juste et durable — sans déplacer les nuisances d'un territoire à un autre.
      </div>

      <div class="orange section-title">Ce que notre procédure en référé a obtenu</div>
      <div class="cadre-vert">
        <div class="cv-titre">✅ Résultats judiciaires</div>
        <ul>
          <li>Notre argumentaire est <strong>validé sur le fond</strong> : obligation de mise en place d'une <strong>RNP</strong></li>
          <li>Le juge a reconnu (une nouvelle fois) <strong>l'illégalité de l'instruction du 16 décembre 2013</strong> et ses effets persistants</li>
        </ul>
      </div>
      <p class="content-text">Sur la forme, le tribunal a estimé que l'urgence n'était plus réunie à ce stade. Nous l'acceptons — car la situation s'est <em>temporairement</em> améliorée. Mais pour combien de temps ?</p>

      <div class="alerte">
        <div class="al-titre">⚠ Risque imminent</div>
        <p>Si les communes bruxelloises persistent à contester la RNP 07 plutôt qu'à exiger une correction des normes de vent, un nouveau contentieux en cessation pourrait conduire à une <strong>réactivation massive et durable de la piste 01</strong>. Nous serions à nouveau en première ligne.</p>
      </div>

      <div class="orange section-title">Ce que vous pouvez faire</div>
      <div class="actions-grid">
        <div class="action-card">
          <div class="ac-num">01</div>
          <div class="ac-titre">Porter le bon message</div>
          <p class="ac-text">Le problème n'est pas une piste ou une autre, mais <strong>les normes de vent</strong> dévoyées depuis 2003.</p>
        </div>
        <div class="action-card">
          <div class="ac-num">02</div>
          <div class="ac-titre">Interpeller les élus</div>
          <p class="ac-text">Demandez à vos autorités locales d'exiger la seule solution : <strong>effacer les erreurs de 2003</strong> et revenir à 8 nœuds sans rafales.</p>
        </div>
        <div class="action-card">
          <div class="ac-num">03</div>
          <div class="ac-titre">Nous soutenir</div>
          <p class="ac-text">Face à l'État et ses moyens considérables, votre don nous permet de <strong>rester debout et crédibles</strong> dans cette bataille judiciaire.</p>
        </div>
      </div>

      <div class="cadre-orange">
        <strong>Notre position est simple :</strong> Revenons à la situation qui a prévalu durant plus de 30 ans — une limite de <strong>8 nœuds de vent arrière, sans notion de rafale</strong>. Cette solution fonctionne et est encore appliquée à Charleroi.
      </div>

      <div class="citation-box">
        <div>
          <p>Nous saluons la prise de position courageuse de <strong>Florence Reuter</strong>, qui a publiquement relayé ce message dans la presse et sur les réseaux sociaux.</p>
          <a href="https://www.facebook.com/share/r/18YCHeB2NK/" target="_blank" rel="noopener">👉 Voir sa prise de position sur Facebook →</a>
        </div>
      </div>

    </div>
<!-- ── POURQUOI LA 01 ── -->
    <div class="tab-panel <?= $first_tab_slug==='pourquoi' ? 'active' : '' ?>" id="tab-pourquoi">


      <div class="pourquoi-hero">
        <img src="data:image/jpeg;base64,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" alt="Vue aérienne de la région survolée — Brabant wallon">
        <div class="pourquoi-hero-caption"><strong>Vue aérienne de la région survolée</strong> — Waterloo, Braine-l'Alleud, Lasne et la périphérie bruxelloise sous les axes d'approche de la piste 01</div>
      </div>

      <div class="orange section-title">Les nuisances en piste 01, pourquoi ?</div>
      <p class="content-text">Un avion qui atterrit en piste 01 crée énormément de nuisances car la descente n'est pas constante — une bonne partie de l'approche se fait <strong>par paliers</strong>. Au point d'entrée de l'ILS, à environ 10 km de Zaventem, les avions ne sont qu'à <strong>650 m d'altitude</strong>.</p>
      <p class="content-text">À Waterloo (25 km de Brussels Airport), l'altitude est souvent comprise entre <strong>600 m et 1 000 m</strong>. Les avions arrivent vers la piste 01 soit via une boucle au-dessus de Bruxelles, soit par le sud avec un accès direct. La descente se poursuit ensuite vers les communes de l'est de Bruxelles à <strong>200–250 m d'altitude</strong>.</p>
      <p class="content-text">Un jour complet en configuration 01, on compte environ <strong>320 avions</strong> atterrissant à Zaventem. En heure de pointe : <strong>1 avion toutes les 1min52s</strong>. Le bruit dure en moyenne 1 minute — il n'y a donc pas de temps de répit. JOUR ET NUIT.</p>

      <div class="chiffres-grid">
        <div class="chiffre-card"><div class="chiffre-val">320</div><div class="chiffre-lab">avions/jour en config 01</div></div>
        <div class="chiffre-card"><div class="chiffre-val">1'52"</div><div class="chiffre-lab">entre deux avions en heure de pointe</div></div>
        <div class="chiffre-card"><div class="chiffre-val">650 m</div><div class="chiffre-lab">altitude au point ILS (10 km de BRU)</div></div>
        <div class="chiffre-card"><div class="chiffre-val">75.000</div><div class="chiffre-lab">habitants survolés (Waterloo + Braine)</div></div>
      </div>

      <div class="orange section-title">Quand la piste 01 est-elle mise en service ?</div>
      <p class="content-text">La piste 01 a toujours été une <strong>piste de secours</strong>. Elle n'était utilisée que par conditions extrêmes, uniquement quand le vent dépassait les <strong>8 nœuds de vent arrière en piste 25, sans notion de rafale</strong>.</p>
      <p class="content-text">Il a été démontré qu'en 2008, la piste 01 <strong>n'aurait pas dû être utilisée durant 74% du temps</strong> — la faute aux prévisions météo utilisées par Skeyes plutôt qu'aux mesures en temps réel.</p>

      <div class="orange section-title">Chronologie du dérèglement</div>
      <div class="timeline">
        <div class="tl-item"><div class="tl-date">Jusqu'en 2003</div><div class="tl-text">Pistes 25 préférentielles. Piste 01 = secours uniquement si vent arrière &gt; <strong>8 nœuds sans rafales</strong>. Utilisation en 2001 : <strong>4,2%</strong>.</div></div>
        <div class="tl-item bad"><div class="tl-date">2003–2004 — Plans Durant &amp; Anciaux</div><div class="tl-text">Dispersion totale des vols. Normes abaissées à <strong>5 nœuds</strong> (7 avec tampon 2). Utilisation de la 01 : doublement de jour, ×3,5 de nuit.</div></div>
        <div class="tl-item"><div class="tl-date">2008 — Accord gouvernemental</div><div class="tl-text">Norme relevée à <strong>7 nœuds</strong> — mais la notion de rafale est ajoutée, et Skeyes se base sur des <em>prévisions</em> et non des mesures en temps réel.</div></div>
        <div class="tl-item"><div class="tl-date">Sept. 2013 — MAXWIND 12 nœuds</div><div class="tl-text">Tolérance de 5 nœuds pour les rafales ajoutée (MAXWIND = 12 nœuds). Utilisation de la 01 immédiatement réduite.</div></div>
        <div class="tl-item bad"><div class="tl-date">Mars 2014 — Coup de force DGTA</div><div class="tl-text">La DGTA supprime la tolérance rafales. MAXWIND ramené à <strong>7 nœuds</strong>. Du jour au lendemain : <strong>33% des jours</strong> en piste 01 (9 à 12 jours/mois). Contradiction avec tous les jugements rendus.</div></div>
        <div class="tl-item bad"><div class="tl-date">2023</div><div class="tl-text">Quasi <strong>40 jours consécutifs</strong> en piste 01 sans grande pause.</div></div>
      </div>
    </div>
<!-- ── INFORMATIONS ── -->
    <div class="tab-panel <?= $first_tab_slug==='informations' ? 'active' : '' ?>" id="tab-informations">


      <div class="orange section-title">Notions fondamentales pour comprendre le survol</div>
      <p class="content-text">L'aéroport de Bruxelles National est constitué de 3 pistes, dont 2 pistes quasi parallèles. Les pistes sont dénommées en fonction de leur orientation en dizaines de degrés. Les 2 pistes 25 sont orientées à environ 250° quand on les utilise dans le sens EST-OUEST. Elles peuvent être utilisées dans l'autre sens (OUEST-EST), sont dénommées 07 et sont alors orientées à 70° (250°-180° = 70°).</p>
      <p class="content-text">La piste 01 (ou 19 dans l'autre sens) est orientée à environ 14° quand on la prend dans le sens SUD-NORD (190°-180°=10°). Ce mode de dénomination est mondial. Ainsi, dans tous les aéroports du monde, on peut déterminer l'orientation de la piste en fonction de son nom et vice-versa.</p>

      <div class="orange section-title">L'ILS et les trajectoires d'approche</div>
      <p class="content-text">Il faut savoir qu'actuellement il n'y a pas de trajectoire d'approche respectée contrairement à ce qui se fait, de manière très précise, pour les décollages. La seule notion importante est la notion d'ILS (système de guidage pour les atterrissages).</p>
      <p class="content-text">L'ILS de la piste 01 débute à environ 6 miles nautiques du pas de piste de la 01. À cette distance, les avions sont en configuration d'atterrissage : ligne droite, train d'atterrissage sorti, pente de 3° par rapport au sol.</p>
      <p class="content-text">Les atterrissages viennent un peu de partout avant de se concentrer au-dessus de Waterloo et Braine-l'Alleud. On voit clairement la boucle rendue obligatoire et survolant Bruxelles. Une fois cette concentration réalisée, juste avant la prise de l'ILS, un peu avant Woluwe-Saint-Pierre, <strong>TOUS les avions sont alignés sur un rail</strong>. La ligne est fine, les nuisances sont donc encore davantage concentrées !</p>
      <p class="content-text">En heure de pointe : <strong>1 avion toutes les 1min 52s</strong>. Il n'est pas normal que de si grosses agglomérations que sont Waterloo et Braine-l'Alleud (75.000 personnes) soient massacrées. Contrairement à Bruxelles, il existe en Brabant wallon des zones très peu peuplées vers lesquelles on peut déplacer les avions via des trajectoires plus précises.</p>

      <div class="orange section-title">Historique des routes aériennes</div>
      <div class="timeline">
        <div class="tl-item">
          <div class="tl-date">1958–1971 (13 ans)</div>
          <div class="tl-text">Concentration au-dessus de Bruxelles. Virage à 2000 et 3000 pieds en fonction du type d'avion. <strong>Pas d'utilisation de la piste 01</strong> (ou dans de très rares exceptions).</div>
        </div>
        <div class="tl-item">
          <div class="tl-date">1971–1974 (3 ans)</div>
          <div class="tl-text">Concentration des différentes routes sur deux axes principaux. Virage à 1700 pieds (gauche) et 700 pieds (droite). <strong>Pas d'utilisation de la piste 01</strong> (ou dans de très rares exceptions).</div>
        </div>
        <div class="tl-item">
          <div class="tl-date">1974–2002 (28 ans)</div>
          <div class="tl-text">Création par Jos Chabert de la route éponyme qui traverse tout Bruxelles. 1984 : On décolle de nuit via la 19 (anciennement 20). En 2000, le virage gauche se fait à 2000 pieds. <strong>Pas d'utilisation de la piste 01</strong> (ou dans de très rares exceptions).</div>
        </div>
        <div class="tl-item bad">
          <div class="tl-date">2002</div>
          <div class="tl-text">Suppression de la route Chabert.</div>
        </div>
        <div class="tl-item bad">
          <div class="tl-date">2003/2004 — Plans Durant &amp; Anciaux</div>
          <div class="tl-text">Dispersion totale des vols, concentration sur les communes de l'est de Bruxelles. La piste 01 (ex 02) devient une piste préférentielle certains jours de la semaine. Les normes de vent abaissées à 5 nœuds (7 nœuds avec un tampon de 2 nœuds) entraînent <strong>plus du double d'utilisation de la piste 01 de jour et plus du triple la nuit</strong> !</div>
        </div>
        <div class="tl-item">
          <div class="tl-date">2008 — Accord gouvernemental</div>
          <div class="tl-text">La norme de vent passe à 7 nœuds — mais la notion de rafale est ajoutée. Belgocontrol se base sur des prévisions et non pas sur des mesures en temps réel. Dans les faits : aucune amélioration réelle.</div>
        </div>
        <div class="tl-item">
          <div class="tl-date">Septembre 2013</div>
          <div class="tl-text">Les normes de vent sont appliquées. On ajoute une tolérance de 5 nœuds supplémentaires pour les rafales (MAXWIND 12 nœuds). Utilisation de la piste 01 immédiatement très réduite.</div>
        </div>
        <div class="tl-item bad">
          <div class="tl-date">Mars 2014 — Coup de force DGTA</div>
          <div class="tl-text">La DGTA supprime la tolérance de 5 nœuds et fait passer le MAXWIND à 7 nœuds (= TAILWIND). Du jour au lendemain : <strong>33% des jours en piste 01</strong> (environ 9 à 12 jours par mois). Elle devient de fait une des pistes préférentielles. Ceci est en contradiction avec tous les jugements rendus.</div>
        </div>
        <div class="tl-item bad">
          <div class="tl-date">2023</div>
          <div class="tl-text">Quasi <strong>40 jours consécutifs</strong> en piste 01 sans grandes pauses.</div>
        </div>
      </div>

      <div class="orange section-title">Les normes de vent — définitions</div>
      <ul class="content-text" style="padding-left:20px; list-style:disc">
        <li><strong>TAILWIND</strong> : vent moyen (normalement mesuré sur une période de 3 minutes)</li>
        <li><strong>MAXWIND</strong> : vent comprenant les petites rafales</li>
        <li>En météorologie aéronautique, on parle de <strong>rafales</strong> quand la vitesse maximale du vent dépasse d'au moins 10 nœuds sa vitesse moyenne (calculée sur 2 minutes)</li>
      </ul>
      <p class="content-text">Avant de parler de norme de vent, il faut rappeler un principe fondamental : on souhaite atterrir et décoller <strong>face au vent</strong>. Par vent de nord-est ou d'est, il faudra donc impérativement atterrir en piste 07.</p>

      <div class="cadre-bleu">
        <strong>Un point crucial souvent mal compris :</strong> Ce n'est pas parce qu'il y a 7 nœuds de vent que les pistes 25 ne peuvent plus être utilisées. Il faut que le <strong>vent arrière sur cette piste soit de 7 nœuds</strong> pour que le schéma PRS ne soit plus applicable.<br><br>
        Exemple : un vent de 10 nœuds plein nord — la résultante sur la piste 25 (considérée à 250°/70°) n'est que de <strong>3,4 nœuds</strong> (cos 70° × 10 nœuds = vent arrière). Il est donc tout à fait faux de dire : dès que le vent est orienté au nord ou au nord-est, il faut mettre la piste 01 en route.<br><br>
        Rappelons que la norme de vent pour la piste 01 est de <strong>0 nœud</strong> — son utilisation est interdite dès que l'orientation du vent est comprise entre 100° et 280°. La seule plage d'utilisation possible va de 350° à 100°.
      </div>

      <p class="content-text">En tout état de cause, il est très important de se rappeler que <strong>les pistes 25 sont les pistes préférentielles de Brussels Airport</strong>. Les couloirs d'approche à l'atterrissage sont non constructibles afin justement de pouvoir être survolés. C'est seulement en cas de grande nécessité que l'on utilisera d'autres pistes (07 ou 01).</p>

      <div class="orange section-title">La suppression des raccourcis (juillet 2013)</div>
      <p class="content-text"><strong>Avant juillet 2013 :</strong> Approches par l'EST et l'OUEST pour accéder au couloir de la piste 01.</p>
      <p class="content-text"><strong>Après juillet 2013 :</strong> Suppression du couloir d'approche par l'EST du Brabant wallon et report de la quasi-intégralité des vols sur l'OUEST via Bruxelles, Braine-l'Alleud et Waterloo. Les avions en approche effectuent une boucle autour de Bruxelles afin de s'aligner sur l'ILS.</p>
      <p class="content-text">La suppression du raccourci a soulagé immédiatement les communes de l'est du Brabant wallon mais les conséquences ont été directes pour les communes de l'ouest (Braine-l'Alleud, Waterloo…) et du sud-est de Bruxelles. Il faut adapter les routes afin de faire passer les avions sur les zones agricoles en priorité.</p>

      <div class="orange section-title">Quelques chiffres (Source : Service de Médiation)</div>
      <div class="chiffres-grid">
        <div class="chiffre-card"><div class="chiffre-val">4,2%</div><div class="chiffre-lab">Utilisation piste 01 en 2001 (avant Anciaux)</div></div>
        <div class="chiffre-card"><div class="chiffre-val">1,57%</div><div class="chiffre-lab">Atterrissages 01 de nuit en 2000 (avant Anciaux)</div></div>
        <div class="chiffre-card"><div class="chiffre-val">10,61%</div><div class="chiffre-lab">Atterrissages 01 de nuit en 2004 (après Anciaux)</div></div>
        <div class="chiffre-card"><div class="chiffre-val">84 jours</div><div class="chiffre-lab">Utilisation piste 01 en 2014</div></div>
        <div class="chiffre-card"><div class="chiffre-val">×3,5</div><div class="chiffre-lab">Utilisation de nuit après plan Anciaux</div></div>
        <div class="chiffre-card"><div class="chiffre-val">33%</div><div class="chiffre-lab">Des jours depuis mars 2014</div></div>
      </div>

      <div class="alerte" style="margin-top:16px">
        <div class="al-titre">⚠ Conclusion</div>
        <p>Contrairement à ce que l'on peut lire à certains endroits, le plan Anciaux n'a jamais été abrogé. Les nouvelles normes de vent (telles qu'utilisées actuellement) <strong>ne sont pas</strong> les normes qui prévalaient avant le plan (8 nœuds sans rafales avant, 7 nœuds avec rafales maintenant). La situation actuelle est CATASTROPHIQUE : 33% de jours d'utilisation de la 01 depuis la mise en place des nouvelles normes — alors que les responsables promettaient de diminuer l'usage de la piste 01.</p>
      </div>
    </div>
<!-- ── NOS DEMANDES ── -->
    <div class="tab-panel <?= $first_tab_slug==='demandes' ? 'active' : '' ?>" id="tab-demandes">


      <div class="cadre-bleu" style="margin-bottom:20px">
        Notre demande pourrait se résumer à ce que la <strong>piste 01 retrouve son caractère de piste de secours historique</strong>. Nous comprenons que par vent extrême du nord elle soit utilisée car le vent latéral sur les pistes préférentielles ne permettrait pas d'atterrir en toute sécurité.<br><br>
        Afin de retrouver son statut de piste de secours, il nous semble utile que les décisions suivantes soient prises :
        <ol style="padding-left:20px; margin-top:10px; line-height:1.8">
          <li>Modification des normes de vent (en conformité avec la décision de la Cour d'appel du 22 octobre 2020)</li>
          <li>Modification de la hauteur à l'approche (3000 pieds au lieu de 2000)</li>
          <li>Trajectoire d'approche modifiée pour le Brabant wallon afin d'éviter Waterloo</li>
          <li>La publication de RNP sur toutes les pistes pour réduire le bruit (CDO possible) pour TOUS !</li>
        </ol>
      </div>

      <div class="orange section-title">1. Normes de vent</div>
      <ul class="content-text" style="padding-left:20px; list-style:disc; line-height:1.8">
        <li>Retour aux normes historiques de <strong>8 nœuds sans notion de rafale</strong> ou retour à l'instruction de juillet 2013</li>
        <li>Si la notion de rafale doit être inscrite, il conviendrait alors d'ajouter une tolérance afin d'atteindre les <strong>10 nœuds</strong> (tous les avions sont certifiés pour voler avec 10 nœuds de vent arrière)</li>
        <li>Changement de piste <strong>QUAND</strong> le vent dépasse la norme, ce qui implique la <strong>FIN des ANTICIPATIONS</strong></li>
      </ul>

      <div class="orange section-title">2. Gradient de descente</div>
      <p class="content-text">Nous demandons l'utilisation en piste 01 d'un gradient de descente n'étant pas inférieur à 3° (même plus de 3° avant la prise de l'ILS) sur les 25 derniers kilomètres avec l'arrêt du vol par paliers et une interception à minimum <strong>3000 pieds</strong>.</p>
      <p class="content-text">En appliquant la procédure de <strong>descente continue (CDO)</strong>, on réduit les nuisances quand la piste 01 doit être utilisée. Évidemment, ceci ne résout absolument pas le problème du survol intensif et n'est à envisager que comme mesure supplémentaire (en plus du changement des normes de vent) afin de soulager les habitants les seuls jours où la piste 01 est en service.</p>
      <div class="cadre-bleu">
        À 20 kilomètres, on mesure une <strong>différence d'altitude de 300 m</strong> en appliquant la CDO par rapport à l'approche par paliers actuelle.
      </div>

      <div class="orange section-title">3. Trajectoires d'approche</div>
      <p class="content-text">Création de trajectoires d'approche propres à cette piste, <strong>évitant les agglomérations fortement peuplées</strong> en Brabant wallon.</p>
      <p class="content-text">Les pistes 25 doivent être utilisées le plus longtemps possible car en approche on survole des zones non constructibles. Les autres pistes ne doivent être utilisées que pour des mesures exceptionnelles, avec toutes les précautions nécessaires (ILS sur les pistes, trajectoires étudiées pour éviter les nuisances sur les populations…).</p>
      <p class="content-text">Il est possible, en Brabant wallon, d'éviter le survol des zones fortement peuplées que sont Waterloo et Braine-l'Alleud en modifiant les trajectoires afin de <strong>survoler les champs</strong>.</p>

      <div class="orange section-title">4. RNP sur toutes les pistes</div>
      <p class="content-text">Il nous semble opportun d'installer une <strong>RNP (Required Navigation Performance)</strong> sur toutes les pistes afin de permettre aux avions d'atterrir en toute sécurité. Il s'agit d'une demande des pilotes (BATA) depuis des dizaines d'années et d'une demande des organismes de sécurité (EU).</p>
      <p class="content-text">Le but étant de permettre l'utilisation de cette piste dans des conditions de vent exceptionnelles tout en utilisant les approches CDO (<strong>moindre bruit</strong>).</p>

      <div class="cadre-orange">
        <strong>Notre position est simple :</strong> Ces demandes ne sont pas nouvelles. La solution est connue, éprouvée et encore appliquée — notamment à <strong>Charleroi</strong>. Il ne manque qu'une <strong>volonté politique</strong> pour l'imposer à Brussels Airport.
      </div>
    </div>
<!-- ── NOS ALLIÉS ── -->
    <div class="tab-panel <?= $first_tab_slug==='allies' ? 'active' : '' ?>" id="tab-allies">


      <div class="cadre-bleu" style="margin-bottom:24px">
        <strong>Un combat commun, des associations complémentaires.</strong><br>
        Piste 01 ça suffit !, Wake Up Kraainem et l'UBCNA-BUTV partagent le même objectif fondamental : mettre fin aux nuisances aériennes abusives autour de Brussels Airport. Ensemble, nous couvrons toutes les zones survolées — du Brabant wallon à l'est de Bruxelles et la périphérie flamande.
      </div>

      <!-- PISTE 01 ÇA SUFFIT -->
      <div style="border-top:3px solid var(--orange-hex); background:#fff; padding:20px; margin-bottom:20px; border: 1px solid var(--bleu-ciel); border-top:3px solid var(--orange-hex);">
        <div style="display:flex; align-items:center; gap:16px; margin-bottom:14px;">
          <div style="width:52px; height:52px; background:var(--bleu-hex); border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-weight:900; color:white; font-size:85%; text-align:center; line-height:1.2;">01<br>✈</div>
          <div>
            <div style="font-size:1.05rem; font-weight:700; color:var(--bleu-hex);">Piste 01, ça suffit ! ASBL</div>
            <div style="font-size:78%; color:var(--gris-texte);">Brabant wallon — Waterloo, Braine-l'Alleud, Lasne, La Hulpe…</div>
          </div>
        </div>
        <p class="content-text">Fondée en 2014 à Waterloo et Braine-l'Alleud, l'ASBL regroupe toutes les personnes survolées par les atterrissages en piste 01, de Waterloo jusqu'à Kraainem, en passant par Hoeilaart ou La Hulpe. Elle mène des actions judiciaires contre l'utilisation abusive de la piste 01 et défend une correction des normes de vent comme solution structurelle.</p>
        <p class="content-text"><strong>Actions judiciaires en cours :</strong> recours en référé contre l'instruction illégale du 16 décembre 2013 et ses effets persistants. Le juge a reconnu l'illégalité — la bataille continue sur les normes de vent.</p>
        <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:12px;">
          <a href="https://www.casuffit.be" target="_blank" style="background:var(--bleu-hex);color:#fff;padding:7px 16px;font-size:80%;font-weight:600;text-decoration:none;">🌐 Site web</a>
          <a href="https://www.facebook.com/Piste01casuffit" target="_blank" style="background:#1877f2;color:#fff;padding:7px 16px;font-size:80%;font-weight:600;text-decoration:none;">Facebook</a>
          <a href="mailto:info@casuffit.be" style="background:var(--orange-hex);color:#fff;padding:7px 16px;font-size:80%;font-weight:600;text-decoration:none;">✉ Contact</a>
        </div>
      </div>

      <!-- WAKE UP KRAAINEM -->
      <div style="border: 1px solid var(--bleu-ciel); border-top:3px solid var(--orange-hex); background:#fff; padding:20px; margin-bottom:20px;">
        <div style="display:flex; align-items:center; gap:16px; margin-bottom:14px;">
          <div style="width:52px; height:52px; background:var(--bleu-hex); border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:1.4rem;">😴</div>
          <div>
            <div style="font-size:1.05rem; font-weight:700; color:var(--bleu-hex);">Wake Up Kraainem ASBL</div>
            <div style="font-size:78%; color:var(--gris-texte);">Est de Bruxelles — Kraainem, Wezembeek, Woluwe, Hoeilaart…</div>
          </div>
        </div>
        <p class="content-text">Fondée en <strong>2001</strong>, Wake Up Kraainem est une équipe bénévole de riverains survolés — ingénieurs, juristes, avocats et informaticiens — qui lutte contre les nuisances aériennes abusives dans l'est de Bruxelles et principalement à Kraainem.</p>
        <p class="content-text">L'association est membre de l'<strong>Overlegcommissie</strong> regroupant les communes flamandes et les acteurs aéroportuaires, et de la plateforme des <strong>États Généraux</strong> constituée à la demande du judiciaire. Elle est également membre du <strong>Bond Beter Leefmilieu</strong> pour une stratégie commune avec d'autres associations flamandes.</p>
        <div class="cadre-bleu" style="margin:10px 0; font-size:88%;">
          <strong>6 affaires judiciaires en cours.</strong> Wake Up Kraainem assiste les avocats dans toutes les procédures judiciaires en cours contre les nuisances de Brussels Airport.
        </div>
        <p class="content-text" style="font-size:85%; font-style:italic;">« Votre santé, votre sécurité et votre environnement sont en jeu. Luttons ensemble contre les nuisances sonores et la concentration des vols à Kraainem et à l'est de Bruxelles. »</p>
        <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:12px;">
          <a href="https://www.wakeupkraainem.be" target="_blank" style="background:var(--bleu-hex);color:#fff;padding:7px 16px;font-size:80%;font-weight:600;text-decoration:none;">🌐 Site web</a>
          <a href="https://www.wakeupkraainem.be/devenir-membre/" target="_blank" style="background:var(--orange-hex);color:#fff;padding:7px 16px;font-size:80%;font-weight:600;text-decoration:none;">✦ Devenir membre</a>
          <a href="https://www.wakeupkraainem.be/porter-plainte/" target="_blank" style="background:#555;color:#fff;padding:7px 16px;font-size:80%;font-weight:600;text-decoration:none;">Porter plainte</a>
        </div>
      </div>

      <!-- UBCNA-BUTV -->
      <div style="border: 1px solid var(--bleu-ciel); border-top:3px solid var(--orange-hex); background:#fff; padding:20px; margin-bottom:20px;">
        <div style="display:flex; align-items:center; gap:16px; margin-bottom:14px;">
          <div style="width:52px; height:52px; background:var(--bleu-hex); border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-weight:900; color:white; font-size:70%; text-align:center; line-height:1.3;">UB<br>CNA</div>
          <div>
            <div style="font-size:1.05rem; font-weight:700; color:var(--bleu-hex);">UBCNA-BUTV ASBL</div>
            <div style="font-size:78%; color:var(--gris-texte);">Union Belge Contre les Nuisances Aériennes — toute la Belgique</div>
          </div>
        </div>
        <p class="content-text"><strong>La plus ancienne et la plus importante</strong> association de riverains de l'aéroport de Bruxelles. L'UBCNA (Union Belge Contre les Nuisances Aériennes / Belgische Unie Tegen Vliegtuighinder) a été la <strong>toute première association</strong> spécifiquement créée pour résoudre la question des nuisances sonores de Brussels Airport, jouant un rôle de précurseur dans le développement d'une logique environnementale.</p>
        <p class="content-text">L'association conseille et aide tous les riverains et autorités communales ou régionales confrontés aux survols. Elle participe activement à l'étude et la recherche de toutes mesures tendant à limiter les nuisances — sonores, pollution de l'air, atteintes à la santé publique et au patrimoine.</p>

        <div class="alerte" style="margin:12px 0;">
          <div class="al-titre">⚠ Bilan accablant sous Gilkinet-Maron</div>
          <p style="font-style:italic;">« La législature Gilkinet-Maron est vraiment décevante, du temps perdu, aucune avancée, rien de rien, c'est incompréhensible que l'État belge ne se défende pas mieux, n'agisse pas et donc perd tous ses procès. »<br><span style="font-size:85%; margin-top:4px; display:block;">— Peggy Cortois, Administratrice déléguée de l'UBCNA</span></p>
        </div>

        <p class="content-text"><strong>42 millions €</strong> d'astreintes et d'indemnités payés par l'État belge sous le ministère Gilkinet. L'instruction illégale n'a toujours pas été retirée. Aucune nouvelle instruction sur les normes de vent n'a été prise.</p>
        <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:12px;">
          <a href="https://ubcna-butv.be" target="_blank" style="background:var(--bleu-hex);color:#fff;padding:7px 16px;font-size:80%;font-weight:600;text-decoration:none;">🌐 Site web</a>
          <a href="https://www.facebook.com/ubcna.belgium/" target="_blank" style="background:#1877f2;color:#fff;padding:7px 16px;font-size:80%;font-weight:600;text-decoration:none;">Facebook</a>
          <a href="https://ubcna-butv.be/contact/" target="_blank" style="background:var(--orange-hex);color:#fff;padding:7px 16px;font-size:80%;font-weight:600;text-decoration:none;">✉ Contact</a>
        </div>
      </div>

      <!-- FORCE DU NOMBRE -->
      <div class="cadre-orange" style="margin-top:8px;">
        <strong>💪 Ensemble, nous sommes plus forts.</strong><br>
        Ces trois associations couvrent l'ensemble des zones survolées autour de Brussels Airport. En rejoignant et en soutenant financièrement Piste 01 ça suffit !, vous renforcez l'ensemble de ce réseau de défense citoyenne.
      </div>


    <?php
    // Contenu additionnel depuis la BDD
    if (!empty($tabs_content['allies']['contenu'])) echo $tabs_content['allies']['contenu'];
    ?>
    </div><!-- /tab-allies -->

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
      if (!empty($p['contenu'])): ?>
        <?= $p['contenu'] ?>
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
    <div data-widget="<?= $w_slug ?>" style="display:none">
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
  }

  if (!noScroll && window.innerWidth <= 900) {
    var w = document.querySelector('.subtabs-wrap');
    if (w) window.scrollTo({top: w.offsetTop - 60, behavior: 'smooth'});
  }
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
      }, 100);
    } else {
      // 2. Chercher un tab principal
      var tabBtn = document.querySelector('.tab-btn[onclick*="' + hash + '"]');
      if (tabBtn) {
        showTab(hash, tabBtn);
      } else {
        updateColonneDroite(firstTabSlug);
      }
    }
  } else {
    updateColonneDroite(firstTabSlug);
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