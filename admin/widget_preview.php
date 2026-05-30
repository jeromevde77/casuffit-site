<?php
// admin/widget_preview.php — Rendu réel d'un widget avec le CSS du site
require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();

$db = getDB();

$contenu = $_POST['contenu'] ?? '';
if (empty($contenu)) {
    echo '<div style="color:#aaa;text-align:center;padding:40px 20px;font-family:sans-serif;font-size:.85rem">Écrivez du HTML/PHP pour voir l\'aperçu...</div>';
    exit;
}

// Variables disponibles dans les widgets
$objectif     = floatval(cfg('montant_objectif', 15000));
$recolte      = floatval(cfg('montant_initial', 0)) + floatval(cfg('montant_recolte', 0));
$pct          = $objectif > 0 ? round($recolte / $objectif * 100) : 0;
$iban         = cfg('iban', 'BE41 0689 0149 6910');
$bic          = cfg('bic', 'GKCCBEBB');
$beneficiaire = cfg('beneficiaire', 'Piste01 Ça Suffit ASBL');
$don_texte    = cfg('don_texte', 'Frais judiciaires');

try {
    $news_list = $db->query("SELECT * FROM news WHERE statut='publie' ORDER BY date_creation DESC LIMIT 5")->fetchAll();
} catch (Exception $e) { $news_list = []; }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<?php include __DIR__ . '/../includes/admin_pwa_head.php'; ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
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
.nav-membre { border: 1px solid rgba(255,255,255,0.3) !important; border-radius: 5px; padding: 4px 10px !important; font-size: .78rem !important; }
.nav-admin { color: rgba(255,255,255,0.35) !important; font-size: .72rem !important; padding: 0 6px !important; }
.header-nav a.nav-cta:hover { background: #e68800; color: #fff; }
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
#pied {
  width: 100%;
  padding: 10px 20px;
  font-size: 70%;
  text-align: center;
  color: #999;
  border-top: 1px solid #ccc;
  background: #fff;
}
#pied a { color: var(--orange-hex); font-weight: 200; }
.pied-iban { font-size: 110%; font-weight: 700; color: var(--bleu-hex); margin-bottom: 4px; }
.pied-liens { margin: 8px 0; }
.pied-liens a { color: var(--orange-hex); margin: 0 8px; font-size: 100%; }

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

/* Reset aperçu */
body { background: #fff; padding: 12px; }
</style>
</head>
<body>
<?php
ob_start();
try {
    eval('?' . '>' . $contenu);
} catch (ParseError $e) {
    ob_end_clean();
    echo '<div style="background:#fee;color:#c33;padding:12px;border-radius:6px;font-size:.78rem"><strong>Erreur syntaxe :</strong><br>' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
} catch (Throwable $e) {
    ob_end_clean();
    echo '<div style="background:#fff3cd;color:#856404;padding:12px;border-radius:6px;font-size:.78rem"><strong>Erreur :</strong><br>' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}
echo ob_get_clean();
?>
</body>
</html>