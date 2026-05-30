<?php
require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();
$db = getDB();

// ── Hook insertion actualité norme vent ───────────────────────────────────
if (($_GET['action'] ?? '') === 'insert_actu_norme') {
    $titre    = "La norme de vent : nœud du problème aérien bruxellois";
    $accroche = "Comment la manipulation des seuils de vent arrière depuis 2004 a bouleversé l'organisation des pistes à Brussels Airport — et pourquoi le retour au PRS 25/25 est la seule solution viable.";
    $contenu  = '<p style="font-size:1.05rem;font-weight:500;color:#1673B2;border-left:4px solid #FF9900;padding-left:14px;margin-bottom:24px">La stabilisation des normes de vent est le nœud de tout le problème. Tant que cette question ne sera pas résolue, l\'organisation du trafic aérien autour de Bruxelles restera source de conflits, de nuisances injustes et d\'incertitude pour les riverains.</p>

<h3>Un aéroport qui fonctionnait bien — avant 2004</h3>
<p>Jusqu\'en 2003, Brussels Airport fonctionnait avec une norme stable et sécurisée : <strong>8 nœuds de composante de vent arrière, sans rafales</strong>, appliquée pendant 30 ans sans la moindre contestation.</p>
<p>En 2004, Bert Anciaux a compris qu\'en abaissant artificiellement cette valeur, il pourrait reporter une partie du trafic vers d\'autres pistes — dans le but de préserver le Noordrand, qu\'il estimait trop survolé par les décollages 25R effectuant le virage à droite.</p>

<h3>Les pistes 25R/25L : construites pour absorber tout le trafic</h3>
<p>Depuis 1958, les pistes parallèles et indépendantes 25R/25L ont été spécifiquement conçues pour absorber le maximum du trafic aérien :</p>
<ul>
<li>Les <strong>pistes les plus longues et les mieux équipées</strong> de l\'aéroport</li>
<li><strong>Parallèles sans croisement au sol</strong> — aucun conflit entre arrivées et départs</li>
<li>À l\'est, une <strong>zone non constructible</strong> (<em>non aedificandi</em>) réservée pour un corridor aérien ne survolant que champs et prairies</li>
</ul>

<h3>Le jeu des vases communicants</h3>
<p>Pour éviter d\'utiliser la 25R, on fait appel à d\'autres configurations — et chaque piste alternative à l\'atterrissage entraîne mécaniquement des décollages vers d\'autres directions. Pour ne pas survoler le Noordrand au décollage, on fait atterrir sur Bruxelles Ouest, Bruxelles Sud, la périphérie Est et le Brabant Wallon — et les décollages repartent vers Kampenhout, Tildonk ou Louvain.</p>
<p>Pourtant le Noordrand ne devrait pas se plaindre : les décollages 25R virant à droite sont répartis sur 4 trajectoires distinctes, et le week-end l\'une d\'elles est déplacée vers le Canal.</p>

<h3>Pourquoi la norme est déterminante</h3>
<p><strong>Plus la norme est basse, instable ou mal appliquée, plus on changera de pistes en permanence</strong> — réduisant la capacité opérationnelle et générant des conflits liés aux pistes qui se croisent au sol. À l\'inverse, une norme élevée et stable maintient le système préférentiel 25R/25L — le système en fonction duquel tout le monde est venu s\'installer autour de l\'aéroport.</p>

<h3>Notre position légale et technique</h3>
<p>Légalement, la composante de vent arrière peut être portée à <strong>10 nœuds</strong> (normes ICAO et FAA). La norme historique de 8 nœuds sans rafales ne prête à aucune contestation puisqu\'appliquée 30 ans sans incident.</p>
<p><strong>Nous ne réclamons pas un transfert aléatoire du trafic de la 01 vers la 07.</strong> Nous défendons le retour aux conditions historiques :</p>
<ul>
<li><strong>25R/25L en préférentiel</strong> — chaque fois que le vent le permet</li>
<li><strong>01</strong> par vent de Nord · <strong>07</strong> par vent d\'Est · <strong>19</strong> par vent de Sud</li>
</ul>
<p>L\'évolution climatique apporte de plus en plus de vent d\'Est et de moins en moins de vent de Nord — les roses des vents le confirment, sans qu\'aucun facteur humain en soit responsable.</p>

<h3>Conclusion : le retour au PRS 25/25</h3>
<p>Le retour au PRS 25/25 est la meilleure façon de ramener la sérénité — <strong>à condition que des mesures opérationnelles soient prises</strong> : mur antibruit, décollage depuis le seuil de piste, respect des procédures, poussée maximale sur la piste, élimination des cargos anciens et bruyants, nouvelles procédures de réduction des nuisances.</p>
<p>Le trafic de Bruxelles doit être remis au maximum sur les pistes 25 pour des motifs de <strong>sécurité, de capacité et de respect des décisions de justice</strong>. Si et seulement si les mesures de vent indiquent un dépassement réel sur les 25R/L, d\'autres pistes seront activées.</p>';

    try {
        $chk = $db->prepare("SELECT id FROM news WHERE titre=? LIMIT 1");
        $chk->execute([$titre]);
        if ($chk->fetch()) {
            $flash = '⚠ Actualité déjà présente — va dans Actualités pour la modifier.';
        } else {
            $db->prepare("INSERT INTO news (titre,accroche,contenu,statut,epingle,date_publication,date_creation) VALUES (?,?,?,'brouillon',0,NOW(),NOW())")
               ->execute([$titre,$accroche,$contenu]);
            header('Location: news.php?msg='.urlencode('✅ Actualité créée en brouillon — relisez et publiez !'));
            exit;
        }
    } catch(Exception $e) {
        $flash = 'Erreur : '.$e->getMessage();
    }
}
// ── Fin hook ──────────────────────────────────────────────────────────────

$nb_pages    = $db->query("SELECT COUNT(*) FROM pages")->fetchColumn();
$nb_news     = $db->query("SELECT COUNT(*) FROM news WHERE statut='publie'")->fetchColumn();
$nb_news_brf = $db->query("SELECT COUNT(*) FROM news WHERE statut='brouillon'")->fetchColumn();
$nb_sub      = $db->query("SELECT COUNT(*) FROM subscribers WHERE statut='actif'")->fetchColumn();
$nb_membres  = $db->query("SELECT COUNT(*) FROM members WHERE statut='actif'")->fetchColumn();
$total_dons     = $db->query("SELECT COALESCE(SUM(montant),0) FROM member_dons WHERE statut='confirme'")->fetchColumn();
$montant_initial = floatval(cfg('montant_initial', 0));
$date_lancement  = cfg('date_lancement', '2026-05-25');
$stmt_dash = $db->prepare("SELECT COALESCE(SUM(montant),0) FROM member_dons WHERE statut='confirme' AND date_don >= ?");
$stmt_dash->execute([$date_lancement]);
$recolte        = $montant_initial + floatval($stmt_dash->fetchColumn());
$objectif       = floatval(cfg('montant_objectif', 15000));
$pct            = $objectif > 0 ? min(100, round($recolte/$objectif*100)) : 0;

$news_recentes  = $db->query("SELECT titre, statut, date_creation FROM news ORDER BY date_creation DESC LIMIT 6")->fetchAll();
// Config maintenance
$maint_mode = $db->query("SELECT valeur FROM site_config WHERE cle='maintenance_mode'")->fetchColumn() ?? '0';
$maint_code = $db->query("SELECT valeur FROM site_config WHERE cle='maintenance_code'")->fetchColumn() ?? '';
$maint_titre = $db->query("SELECT valeur FROM site_config WHERE cle='maintenance_titre'")->fetchColumn() ?? '';
$maint_msg   = $db->query("SELECT valeur FROM site_config WHERE cle='maintenance_message'")->fetchColumn() ?? '';

// Traitement toggle maintenance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['maintenance_action'])) {
    if ($_POST['maintenance_action'] === 'reset_bypass') {
        setcookie('maintenance_bypass', '', time() - 3600, '/', '', true, true);
        header('Location: dashboard.php?msg=bypass_reset'); exit;
    } elseif ($_POST['maintenance_action'] === 'toggle') {
        $new = $maint_mode === '1' ? '0' : '1';
        $db->prepare("UPDATE site_config SET valeur=? WHERE cle='maintenance_mode'")->execute([$new]);
        $maint_mode = $new;
    } elseif ($_POST['maintenance_action'] === 'save') {
        foreach (['maintenance_code','maintenance_titre','maintenance_message'] as $k) {
            if (isset($_POST[$k])) {
                $db->prepare("UPDATE site_config SET valeur=? WHERE cle=?")->execute([$_POST[$k], $k]);
            }
        }
        $maint_code  = $_POST['maintenance_code']    ?? $maint_code;
        $maint_titre = $_POST['maintenance_titre']   ?? $maint_titre;
        $maint_msg   = $_POST['maintenance_message'] ?? $maint_msg;
    }
}

// Toggle annonce en haut de site
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['annonce_action']) && $_POST['annonce_action'] === 'toggle') {
    $cur = $db->query("SELECT valeur FROM site_config WHERE cle='annonce_active'")->fetchColumn();
    $new = ($cur === '1' || $cur === null) ? '0' : '1';
    $db->prepare("INSERT INTO site_config (cle,valeur) VALUES ('annonce_active',?) ON DUPLICATE KEY UPDATE valeur=?")
       ->execute([$new, $new]);
    header('Location: dashboard.php'); exit;
}
$annonce_active = ($db->query("SELECT valeur FROM site_config WHERE cle='annonce_active'")->fetchColumn() ?? '1') === '1';

// Toggle bandeau urgence
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['urgence_action']) && $_POST['urgence_action'] === 'toggle') {
    $cur = $db->query("SELECT valeur FROM site_config WHERE cle='urgence_active'")->fetchColumn();
    $new = ($cur === '1' || $cur === null) ? '0' : '1';
    $db->prepare("INSERT INTO site_config (cle,valeur) VALUES ('urgence_active',?) ON DUPLICATE KEY UPDATE valeur=?")
       ->execute([$new, $new]);
    header('Location: dashboard.php'); exit;
}
$urgence_active = ($db->query("SELECT valeur FROM site_config WHERE cle='urgence_active'")->fetchColumn() ?? '1') === '1';

$membres_recents = $db->query("SELECT prenom, nom, code_membre, date_inscription FROM members ORDER BY date_inscription DESC LIMIT 5")->fetchAll();
$nb_sub_new  = $db->query("SELECT COUNT(*) FROM subscribers WHERE date_inscription >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<?php include __DIR__ . '/../includes/admin_pwa_head.php'; ?>
<title>Dashboard — Admin Ça suffit !</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333}
<?php include __DIR__ . '/../includes/admin_sidebar_css.php'; ?>

/* ── LAYOUT ── */
.main { margin-left:240px; padding:24px; min-height:100vh; }

/* ── PAGE HEADER ── */
.dash-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; }
.dash-header h1 { font-size:1.3rem; font-weight:800; color:#0e3d6b; }
.dash-header .date { font-size:.78rem; color:#999; }

/* ── COLLECTE HERO ── */
.collecte-hero {
  background: linear-gradient(135deg, #0e3d6b 0%, #1673B2 100%);
  border-radius: 16px; padding: 24px 28px; margin-bottom: 24px;
  color: #fff; position: relative; overflow: hidden;
}
.collecte-hero::after {
  content:''; position:absolute; top:-40px; right:-40px;
  width:180px; height:180px; background:rgba(255,255,255,.05);
  border-radius:50%;
}
.collecte-hero .ch-title { font-size:.72rem; text-transform:uppercase; letter-spacing:.08em; opacity:.7; margin-bottom:6px; }
.collecte-hero .ch-montants { display:flex; align-items:baseline; gap:10px; margin-bottom:16px; }
.collecte-hero .ch-recolte { font-size:2rem; font-weight:800; color:#FF9900; }
.collecte-hero .ch-sep { opacity:.5; font-size:1.2rem; }
.collecte-hero .ch-objectif { font-size:1.1rem; font-weight:600; opacity:.8; }
.collecte-hero .ch-pct { font-size:.82rem; opacity:.7; margin-top:4px; }
.ch-bar-wrap { background:rgba(255,255,255,.2); border-radius:8px; height:10px; margin:12px 0 6px; overflow:hidden; }
.ch-bar-fill { background: linear-gradient(90deg, #FF9900, #ffcc44); height:100%; border-radius:8px; transition:width 1.2s ease; }
.ch-meta { display:flex; gap:20px; margin-top:8px; }
.ch-meta span { font-size:.75rem; opacity:.8; }
.ch-meta strong { color:#FF9900; }

/* ── STATS GRID ── */
.stats-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:24px; }
.stat-card {
  background:#fff; border-radius:12px; padding:16px 18px;
  box-shadow:0 2px 8px rgba(0,0,0,.06);
  display:flex; align-items:center; gap:14px;
  text-decoration:none; color:inherit;
  transition:all .2s; border:2px solid transparent;
}
.stat-card:hover { border-color:#1673B2; transform:translateY(-2px); box-shadow:0 6px 18px rgba(22,115,178,.12); }
.stat-icon { font-size:1.6rem; flex-shrink:0; }
.stat-info .val { font-size:1.5rem; font-weight:800; color:#0e3d6b; line-height:1; }
.stat-info .lbl { font-size:.68rem; color:#999; text-transform:uppercase; letter-spacing:.04em; margin-top:3px; }
.stat-info .sub { font-size:.7rem; color:#FF9900; font-weight:600; margin-top:2px; }

/* ── ACTIONS RAPIDES ── */
.actions-title { font-size:.7rem; font-weight:700; color:#999; text-transform:uppercase; letter-spacing:.08em; margin-bottom:10px; }
.actions-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin-bottom:24px; }
.action-btn {
  background:#fff; border-radius:12px; padding:16px 12px;
  box-shadow:0 2px 8px rgba(0,0,0,.06); text-align:center;
  text-decoration:none; color:#333; transition:all .2s;
  border:2px solid transparent; display:flex; flex-direction:column; align-items:center; gap:8px;
}
.action-btn:hover { border-color:#1673B2; transform:translateY(-2px); box-shadow:0 6px 18px rgba(22,115,178,.12); color:#1673B2; text-decoration:none; }
.action-btn .ab-icon { font-size:1.8rem; }
.action-btn .ab-label { font-size:.75rem; font-weight:600; line-height:1.2; }
.action-btn.primary { background:#1673B2; color:#fff; }
.action-btn.primary:hover { background:#0e5a8a; border-color:#0e5a8a; color:#fff; }

/* ── CARDS ── */
.cards-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.card { background:#fff; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,.06); overflow:hidden; }
.card-head { padding:14px 18px; border-bottom:1px solid #eef2f7; display:flex; align-items:center; justify-content:space-between; }
.card-head h3 { font-size:.88rem; font-weight:700; color:#0e3d6b; }
.card-head a { font-size:.72rem; color:#1673B2; text-decoration:none; font-weight:500; }
.card-body { padding:0; }
.item-row { display:flex; align-items:center; gap:10px; padding:10px 18px; border-bottom:1px solid #f5f5f7; font-size:.8rem; transition:background .1s; }
.item-row:last-child { border:none; }
.item-row:hover { background:#f9fbfd; }
.item-dot { width:7px; height:7px; border-radius:50%; flex-shrink:0; }
.dot-green { background:#27ae60; }
.dot-orange { background:#FF9900; }
.dot-grey { background:#bbb; }
.item-name { flex:1; font-weight:500; color:#1a1a2e; }
.item-meta { font-size:.7rem; color:#999; }
.badge { display:inline-block; padding:2px 7px; border-radius:10px; font-size:.65rem; font-weight:700; }
.b-ok { background:#e8f8f0; color:#1e8449; }
.b-warn { background:#fff3e0; color:#ba7517; }
.b-grey { background:#f0f0f0; color:#888; }
.empty-state { padding:30px; text-align:center; color:#bbb; font-size:.82rem; }

/* ── MOBILE ── */
@media (max-width:768px) {
  .main { margin-left:0; padding:16px; padding-top:68px; }
  .stats-grid { grid-template-columns:repeat(2,1fr); }
  .actions-grid { grid-template-columns:repeat(3,1fr); gap:8px; }
  .action-btn { padding:12px 8px; }
  .action-btn .ab-icon { font-size:1.4rem; }
  .action-btn .ab-label { font-size:.68rem; }
  .cards-grid { grid-template-columns:1fr; }
  .collecte-hero { padding:18px; }
  .collecte-hero .ch-recolte { font-size:1.6rem; }
}
@media (max-width:480px) {
  .stats-grid { grid-template-columns:repeat(2,1fr); gap:8px; }
  .stat-card { padding:12px; gap:10px; }
  .stat-icon { font-size:1.3rem; }
  .stat-info .val { font-size:1.2rem; }
}

/* Mode maintenance */
.maint-box { background: #f0f6fb; border-radius: 10px; padding: 16px 20px; margin-bottom: 24px; border: 2px solid #c8dff0; width: 100%; box-sizing: border-box; }
.maint-box.maint-active { background: #fff8ee; border-color: #FF9900; }
.maint-status { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; flex-wrap: wrap; }
.maint-dot { width: 10px; height: 10px; border-radius: 50%; background: #ccc; flex-shrink: 0; }
.maint-dot.on { background: #e53e3e; box-shadow: 0 0 6px #e53e3e; animation: blink 1.2s ease-in-out infinite; }
@keyframes blink { 0%,100%{opacity:1}50%{opacity:.4} }
.maint-toggle-btn { padding: 7px 14px; border: none; border-radius: 5px; font-size: .82rem; font-weight: 700; cursor: pointer; flex-shrink: 0; }
.maint-toggle-btn.on { background: #e53e3e; color: #fff; }
.maint-toggle-btn.off { background: #27ae60; color: #fff; }
.maint-form { width: 100%; }
.maint-fields { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; width: 100%; }
@media (max-width: 700px) { .maint-fields { grid-template-columns: 1fr; } }
.maint-field { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.maint-field label { font-size: .75rem; font-weight: 700; color: #0e3d6b; }
.maint-input { padding: 7px 10px; border: 1px solid #c8dff0; border-radius: 5px; font-size: .83rem; background: #fff; width: 100%; box-sizing: border-box; }
.maint-field small { font-size: .68rem; color: #888; margin-top: 2px; overflow-wrap: break-word; word-break: break-all; }
.maint-field small code { background: #f0f0f0; padding: 1px 4px; border-radius: 3px; }
/* Bloc historique METAR */
.metar-hist-box { background: #f0f6fb; border-radius: 10px; padding: 16px 20px; margin-bottom: 24px; display: flex; flex-wrap: wrap; align-items: center; gap: 16px; }
.mh-info  { display: flex; align-items: flex-start; gap: 12px; flex: 1; min-width: 220px; }
.mh-icon  { font-size: 1.8rem; line-height: 1; }
.mh-info strong { color: #0e3d6b; }
.mh-info small  { color: #666; font-size: .78rem; }
.mh-form  { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.mh-select { padding: 5px 8px; border: 1px solid #c8dff0; border-radius: 5px; background: #fff; color: #0e3d6b; font-size: .85rem; }
.mh-btn { padding: 7px 14px; border: none; border-radius: 5px; cursor: pointer; font-size: .82rem; background: #e0ecf8; color: #1673B2; font-weight: 600; }
.mh-btn.primary { background: #1673B2; color: #fff; }
.mh-btn:hover { opacity: .88; }
.mh-stat { width: 100%; font-size: .75rem; color: #888; border-top: 1px solid #dde; padding-top: 8px; margin-top: 4px; }
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

<div class="main">

  <!-- Header -->
  <div class="dash-header">
    <h1>📊 Tableau de bord</h1>
    <span class="date"><?= strftime('%A %d %B %Y') ?: date('d/m/Y') ?></span>
  </div>

  <!-- Collecte hero -->
  <div class="collecte-hero">
    <div class="ch-title">🎯 Objectif — Combat juridique — Suite de nos actions</div>
    <div class="ch-montants">
      <span class="ch-recolte"><?= number_format($recolte, 0, ',', ' ') ?> €</span>
      <span class="ch-sep">/</span>
      <span class="ch-objectif"><?= number_format($objectif, 0, ',', ' ') ?> €</span>
    </div>
    <div class="ch-bar-wrap">
      <div class="ch-bar-fill" style="width:<?= $pct ?>%"></div>
    </div>
    <div class="ch-meta">
      <span><strong><?= $pct ?>%</strong> atteint</span>
      <span><strong><?= number_format($objectif - $recolte, 0, ',', ' ') ?> €</strong> restants</span>
      <span><strong><?= $nb_membres ?></strong> membres actifs</span>
      <span style="opacity:.6;font-size:.85em">depuis le <?= date('d/m/Y', strtotime($date_lancement)) ?></span>
    </div>
  </div>

  <!-- Stats -->
  <div class="stats-grid">
    <a href="pages.php" class="stat-card">
      <span class="stat-icon">📄</span>
      <div class="stat-info">
        <div class="val"><?= $nb_pages ?></div>
        <div class="lbl">Pages du site</div>
      </div>
    </a>
    <a href="news.php" class="stat-card">
      <span class="stat-icon">📰</span>
      <div class="stat-info">
        <div class="val"><?= $nb_news ?></div>
        <div class="lbl">News publiées</div>
        <?php if ($nb_news_brf > 0): ?>
          <div class="sub"><?= $nb_news_brf ?> brouillon<?= $nb_news_brf > 1 ? 's' : '' ?></div>
        <?php endif; ?>
      </div>
    </a>
    <a href="subscribers.php" class="stat-card">
      <span class="stat-icon">✉️</span>
      <div class="stat-info">
        <div class="val"><?= number_format($nb_sub, 0, ',', ' ') ?></div>
        <div class="lbl">Abonnés newsletter</div>
        <?php if ($nb_sub_new > 0): ?>
          <div class="sub">+<?= $nb_sub_new ?> cette semaine</div>
        <?php endif; ?>
      </div>
    </a>
    <a href="dons_all.php" class="stat-card">
      <span class="stat-icon">💰</span>
      <div class="stat-info">
        <div class="val"><?= number_format($total_dons, 0, ',', ' ') ?> €</div>
        <div class="lbl">Dons confirmés</div>
      </div>
    </a>
  </div>

  <!-- Actions rapides -->
  <div class="actions-title">Actions rapides</div>
  <div class="actions-grid">
    <a href="news.php?new=1" class="action-btn primary">
      <span class="ab-icon">✍️</span>
      <span class="ab-label">Nouvelle actualité</span>
    </a>
    <a href="pages.php" class="action-btn">
      <span class="ab-icon">📄</span>
      <span class="ab-label">Modifier une page</span>
    </a>
    <a href="compose.php" class="action-btn">
      <span class="ab-icon">📤</span>
      <span class="ab-label">Envoyer newsletter</span>
    </a>
    <a href="subscribers.php" class="action-btn">
      <span class="ab-icon">👥</span>
      <span class="ab-label">Voir les abonnés</span>
    </a>
    <a href="coda.php" class="action-btn">
      <span class="ab-icon">🏦</span>
      <span class="ab-label">Import CODA</span>
    </a>
    <a href="site_config.php" class="action-btn">
      <span class="ab-icon">⚙️</span>
      <span class="ab-label">Paramètres</span>
    </a>
    <a href="metar_history.php" class="action-btn">
      <span class="ab-icon">📋</span>
      <span class="ab-label">Historique METAR</span>
    </a>
    <a href="run_cron.php" class="action-btn">
      <span class="ab-icon">🌬</span>
      <span class="ab-label">Forcer METAR</span>
    </a>
    <a href="backup.php" class="action-btn">
      <span class="ab-icon">💾</span>
      <span class="ab-label">Backup</span>
    </a>
  </div>

  <!-- Section METAR historique -->
  <div class="actions-title">Historique METAR</div>
  <div class="metar-hist-box">
    <div class="mh-info">
      <span class="mh-icon">📊</span>
      <div>
        <strong>Backfill historique</strong><br>
        <small>Remplit la base avec les données METAR + rafales IRM sur la période choisie.</small>
      </div>
    </div>
    <div class="mh-form">
      <label>Période :
        <select id="backfill-days" class="mh-select">
          <option value="7">7 jours</option>
          <option value="30" selected>30 jours</option>
          <option value="90">90 jours</option>
          <option value="180">6 mois</option>
          <option value="365">1 an</option>
        </select>
      </label>
      <button onclick="var d=document.getElementById('backfill-days').value;window.location='/admin/metar_backfill.php?days='+d" class="mh-btn primary">▶ Lancer</button>
      <button onclick="var d=document.getElementById('backfill-days').value;window.location='/admin/metar_backfill.php?days='+d+'&amp;dry=1'" class="mh-btn">🔍 Simuler</button>
    </div>
    <div id="mh-log-count" class="mh-stat"><?php
      try {
        $count = getDB()->query("SELECT COUNT(*) FROM metar_history")->fetchColumn();
        $last  = getDB()->query("SELECT MAX(obs_time) FROM metar_history")->fetchColumn();
        echo number_format($count) . ' enregistrements';
        if ($last) echo ' · dernier : ' . substr($last, 0, 16);
      } catch(Exception $e) { echo '(table manquante — exécuter migrate_metar_history.sql)'; }
    ?></div>
  </div>

  <!-- News + Membres -->
  <div class="cards-grid">

    <div class="card">
      <div class="card-head">
        <h3>📰 Actualités récentes</h3>
        <a href="news.php">Gérer →</a>
      </div>
      <div class="card-body">
        <?php if (empty($news_recentes)): ?>
          <div class="empty-state">Aucune actualité.</div>
        <?php else: ?>
          <?php foreach ($news_recentes as $n): ?>
          <div class="item-row">
            <span class="item-dot <?= $n['statut']==='publie' ? 'dot-green' : ($n['statut']==='brouillon' ? 'dot-orange' : 'dot-grey') ?>"></span>
            <span class="item-name"><?= htmlspecialchars(mb_strimwidth($n['titre'],0,40,'…')) ?></span>
            <span class="badge <?= $n['statut']==='publie' ? 'b-ok' : ($n['statut']==='brouillon' ? 'b-warn' : 'b-grey') ?>"><?= $n['statut'] ?></span>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-head">
        <h3>👤 Membres récents</h3>
        <a href="members.php">Gérer →</a>
      </div>
      <div class="card-body">
        <?php if (empty($membres_recents)): ?>
          <div class="empty-state">Aucun membre.</div>
        <?php else: ?>
          <?php foreach ($membres_recents as $m): ?>
          <div class="item-row">
            <span class="item-dot dot-green"></span>
            <span class="item-name"><?= htmlspecialchars($m['prenom'].' '.$m['nom']) ?></span>
            <span class="item-meta"><?= htmlspecialchars($m['code_membre']) ?></span>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

  </div>

  <!-- Section Maintenance -->
  <?php if (isset($_GET['msg']) && $_GET['msg'] === 'bypass_reset'): ?>
    <div style="background:#e8f5e9;border:1px solid #27ae60;border-radius:6px;padding:8px 14px;margin-bottom:12px;color:#166534;font-size:.82rem">
      ✅ Cookie de bypass réinitialisé — les visiteurs avec l'ancien cookie devront retaper le code.
    </div>
  <?php endif; ?>

  <div class="actions-title" style="margin-top:24px">📢 Annonce en haut de site</div>
  <div class="maint-box<?= $annonce_active ? ' maint-active' : '' ?>">
    <div class="maint-status" style="margin-bottom:0">
      <div class="maint-dot<?= $annonce_active ? ' on' : '' ?>" style="<?= $annonce_active ? 'background:#FF9900;box-shadow:0 0 6px #FF9900' : '' ?>"></div>
      <span><?= $annonce_active ? 'AFFICHÉE — Le bandeau d\'annonce est visible en haut du site' : 'Masquée — Aucun bandeau d\'annonce affiché' ?></span>
      <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
        <a href="site_config.php" class="maint-toggle-btn" style="background:#e8eef3;color:#555;text-decoration:none">Modifier le texte</a>
        <form method="post">
          <input type="hidden" name="annonce_action" value="toggle">
          <button type="submit" class="maint-toggle-btn<?= $annonce_active ? ' off' : ' on' ?>">
            <?= $annonce_active ? '⏹ Masquer l\'annonce' : '▶ Afficher l\'annonce' ?>
          </button>
        </form>
      </div>
    </div>
  </div>

  <div class="actions-title" style="margin-top:24px">⚠️ Bandeau urgence (orange)</div>
  <div class="maint-box<?= $urgence_active ? ' maint-active' : '' ?>">
    <div class="maint-status" style="margin-bottom:0">
      <div class="maint-dot<?= $urgence_active ? ' on' : '' ?>" style="<?= $urgence_active ? 'background:#FF9900;box-shadow:0 0 6px #FF9900' : '' ?>"></div>
      <span><?= $urgence_active ? 'AFFICHÉ — Le bandeau orange est visible en haut du site' : 'Masqué — Aucun bandeau urgence affiché' ?></span>
      <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
        <a href="site_config.php" class="maint-toggle-btn" style="background:#e8eef3;color:#555;text-decoration:none">Modifier le texte</a>
        <form method="post">
          <input type="hidden" name="urgence_action" value="toggle">
          <button type="submit" class="maint-toggle-btn<?= $urgence_active ? ' off' : ' on' ?>">
            <?= $urgence_active ? '⏹ Masquer le bandeau' : '▶ Afficher le bandeau' ?>
          </button>
        </form>
      </div>
    </div>
  </div>

  <div class="actions-title" style="margin-top:24px">🚧 Mode maintenance</div>
  <div class="maint-box<?= $maint_mode==='1' ? ' maint-active' : '' ?>" id="maint-box">

    <div class="maint-status">
      <div class="maint-dot<?= $maint_mode==='1' ? ' on' : '' ?>"></div>
      <span><?= $maint_mode==='1' ? 'ACTIF — Le site affiche la page de maintenance' : 'Inactif — Le site est accessible normalement' ?></span>
      <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
        <form method="post">
          <input type="hidden" name="maintenance_action" value="reset_bypass">
          <button type="submit" class="maint-toggle-btn" style="background:#6b7280;color:#fff"
            title="Expire le cookie de bypass sur ce navigateur">
            🍪 Réinitialiser bypass
          </button>
        </form>
        <form method="post">
          <input type="hidden" name="maintenance_action" value="toggle">
          <button type="submit" class="maint-toggle-btn<?= $maint_mode==='1' ? ' off' : ' on' ?>">
            <?= $maint_mode==='1' ? '⏹ Désactiver' : '▶ Activer le mode maintenance' ?>
          </button>
        </form>
      </div>
    </div>

    <form method="post" class="maint-form">
      <input type="hidden" name="maintenance_action" value="save">
      <div class="maint-fields">
        <div class="maint-field">
          <label>Titre</label>
          <input type="text" name="maintenance_titre" value="<?= htmlspecialchars($maint_titre) ?>" class="maint-input">
        </div>
        <div class="maint-field">
          <label>Code de bypass secret</label>
          <input type="text" name="maintenance_code" value="<?= htmlspecialchars($maint_code) ?>" class="maint-input" style="font-family:monospace">
          <small>URL de bypass : <code><?= htmlspecialchars((defined('SITE_URL')?SITE_URL:'https://www.casuffit.be')) ?>/?bypass=<?= htmlspecialchars($maint_code) ?></code></small>
        </div>
        <div class="maint-field" style="grid-column:1/-1">
          <label>Message</label>
          <textarea name="maintenance_message" class="maint-input" rows="3"><?= htmlspecialchars($maint_msg) ?></textarea>
        </div>
      </div>
      <button type="submit" class="mh-btn primary" style="margin-top:10px">💾 Enregistrer</button>
      <?php if ($maint_mode==='1'): ?>
        <a href="/maintenance.php" target="_blank" class="mh-btn" style="text-decoration:none;margin-left:8px">👁 Voir la page →</a>
      <?php endif; ?>
    </form>

  </div>


</div>
<script>
function launchBackfill(dry) {
  var days = document.getElementById('backfill-days').value;
  window.location.href = '/admin/metar_backfill.php?days=' + days + (dry ? '&dry=1' : '');
}
</script>

</body>
</html>
