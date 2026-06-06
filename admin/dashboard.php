<?php
// v7 — fix site_email hook
require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();
$db = getDB();

// ── Hook fix site_email + admin_bcc ───────────────────────────────────────
if (($_GET['action'] ?? '') === 'fix_email') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Fix config</title></head><body style="font-family:sans-serif;max-width:500px;margin:40px auto;padding:0 20px">';
    try {
        $current = $db->query("SELECT valeur FROM site_config WHERE cle='site_email' LIMIT 1")->fetchColumn();
        $db->prepare("INSERT INTO site_config (cle,valeur) VALUES ('site_email','info@casuffit.be') ON DUPLICATE KEY UPDATE valeur='info@casuffit.be'")->execute();
        echo '<p style="color:#27ae60;font-weight:700">✅ site_email : '.$current.' → info@casuffit.be</p>';
        // Créer admin_bcc si absent
        $bcc = $db->query("SELECT valeur FROM site_config WHERE cle='admin_bcc' LIMIT 1")->fetchColumn();
        if ($bcc === false) {
            $db->prepare("INSERT INTO site_config (cle,valeur) VALUES ('admin_bcc','')")->execute();
            echo '<p style="color:#27ae60;font-weight:700">✅ Clé admin_bcc créée (vide) — remplis-la dans <a href="/admin/site_config.php">Paramètres</a></p>';
        } else {
            echo '<p style="color:#1673B2">ℹ admin_bcc déjà présente : '.htmlspecialchars($bcc ?: '(vide)').'</p>';
        }
    } catch(Exception $e) {
        echo '<p style="color:#c0392b">❌ '.htmlspecialchars($e->getMessage()).'</p>';
    }
    echo '<p><a href="/admin/site_config.php" style="color:#1673B2;font-weight:700">→ Aller dans Paramètres pour remplir admin_bcc</a></p>';
    echo '</body></html>';
    exit;
}


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
    <h1>📊 Tableau de bord <span style="font-size:.62rem;font-weight:600;color:#aaa;vertical-align:middle;background:#eef2f7;padding:2px 8px;border-radius:10px;margin-left:6px">v<?= date('y.m.d-Hi', filemtime(__FILE__)) ?></span></h1>
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
