<?php
// admin/tracks_ebbr.php — Visualisation des traces radar EBBR (pistes 01 & 07)
require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/admin_sidebar_css.php';
$db = getDB();

// Action : régénérer une image
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['regen_date'])) {
    csrf_verify();
    $d = preg_replace('/[^0-9-]/', '', $_POST['regen_date']);
    header("Location: /cron/ebbr_tracks.php?secret=".urlencode(defined('CRON_SECRET')?CRON_SECRET:'')."&date=$d&force=1");
    exit;
}

// Action : supprimer une journée
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['del_date'])) {
    csrf_verify();
    $d = preg_replace('/[^0-9-]/', '', $_POST['del_date']);
    $db->prepare("DELETE FROM ebbr_runway_tracks WHERE track_date=?")->execute([$d]);
    @unlink(__DIR__.'/../medias/tracks/'.$d.'.png');
}

// Charger les jours disponibles
$days = [];
try {
    $stmt = $db->query("SELECT track_date,
        SUM(runway='01') AS n01,
        SUM(runway='07') AS n07,
        COUNT(*) AS total
        FROM ebbr_runway_tracks
        GROUP BY track_date ORDER BY track_date DESC");
    $days = $stmt->fetchAll();
} catch (Exception $e) {}

$base_url = defined('SITE_URL') ? SITE_URL : 'https://www.casuffit.be';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Traces EBBR — Admin</title>
<style>
<?php include __DIR__ . '/../includes/admin_sidebar_css.php'; ?>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333;font-size:14px}
.main{margin-left:240px;padding:28px 32px}
@media(max-width:768px){.main{margin-left:0;padding-top:60px;padding-left:16px;padding-right:16px}}
h1{font-size:1.3rem;color:#1673B2;font-weight:700;margin-bottom:6px}
.subtitle{color:#888;font-size:.82rem;margin-bottom:22px}

.alert{background:#fff8ee;border-left:3px solid #FF9900;padding:12px 16px;border-radius:4px;font-size:.85rem;color:#856404;margin-bottom:20px}
.alert code{background:#fff;padding:2px 6px;border-radius:3px;font-family:monospace;font-size:.82rem}

.top-actions{display:flex;gap:10px;align-items:center;margin-bottom:20px;flex-wrap:wrap}
.btn{padding:8px 16px;border-radius:6px;border:none;font-size:.82rem;font-weight:600;cursor:pointer;font-family:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:5px}
.btn-blue{background:#1673B2;color:#fff}
.btn-gray{background:#e8eef3;color:#555}
.btn-red{background:#fee2e2;color:#c53030;border:1.5px solid #fca5a5}
.btn-orange{background:#FF9900;color:#fff}

.stats-row{display:flex;gap:14px;margin-bottom:22px;flex-wrap:wrap}
.stat-card{background:#fff;border-radius:8px;padding:14px 18px;box-shadow:0 1px 4px rgba(0,0,0,.05);min-width:140px}
.stat-card .v{font-size:1.8rem;font-weight:800;color:#1673B2}
.stat-card .l{font-size:.72rem;color:#888;text-transform:uppercase;letter-spacing:.04em;margin-top:3px}

.days-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:18px}
.day-card{background:#fff;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.06);overflow:hidden}
.day-card-head{padding:12px 16px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #f0f3f7}
.day-date{font-weight:700;color:#0e3d6b;font-size:.95rem}
.day-badges{display:flex;gap:6px}
.badge-01{background:#fff8ee;color:#c47700;border:1px solid #FF9900;padding:2px 9px;border-radius:10px;font-size:.72rem;font-weight:700}
.badge-07{background:#e8f3fb;color:#1673B2;border:1px solid #1673B2;padding:2px 9px;border-radius:10px;font-size:.72rem;font-weight:700}
.day-img{width:100%;display:block;cursor:pointer;transition:opacity .15s}
.day-img:hover{opacity:.92}
.day-no-img{padding:20px;text-align:center;color:#aaa;font-size:.82rem;font-style:italic}
.day-actions{padding:8px 12px;display:flex;gap:6px;border-top:1px solid #f0f3f7}

/* Lightbox */
#lightbox{display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:9999;align-items:center;justify-content:center}
#lightbox.open{display:flex}
#lightbox img{max-width:95vw;max-height:92vh;border-radius:6px;box-shadow:0 20px 60px rgba(0,0,0,.5)}
#lightbox-close{position:absolute;top:16px;right:20px;color:#fff;font-size:32px;cursor:pointer;opacity:.8;line-height:1}

.empty-state{padding:40px;text-align:center;color:#aaa;background:#fff;border-radius:10px}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>
<div class="main">
  <h1>Traces radar EBBR — Pistes 01 & 07</h1>
  <p class="subtitle">Images générées automatiquement chaque nuit depuis OpenSky Network · Indépendant de l'outil vols en temps réel</p>

  <?php
  // Vérifier si la table existe
  $table_ok = false;
  try { $db->query("SELECT 1 FROM ebbr_runway_tracks LIMIT 1"); $table_ok = true; }
  catch (Exception $e) {}
  if (!$table_ok): ?>
  <div class="alert">
    Table <code>ebbr_runway_tracks</code> manquante.
    Exécutez <code>migrate_ebbr_tracks.sql</code> dans phpMyAdmin, puis configurez le cron sur
    <a href="https://cron-job.org" target="_blank">cron-job.org</a> :
    <code>https://www.casuffit.be/cron/ebbr_tracks.php?secret=VOTRE_SECRET</code> — tous les jours à 03:00 UTC.
  </div>
  <?php endif; ?>

  <!-- Stats rapides -->
  <?php if (!empty($days)): ?>
  <div class="stats-row">
    <?php
      $total_vols = array_sum(array_column($days, 'total'));
      $total_01   = array_sum(array_column($days, 'n01'));
      $total_07   = array_sum(array_column($days, 'n07'));
    ?>
    <div class="stat-card"><div class="v"><?= count($days) ?></div><div class="l">Jours archivés</div></div>
    <div class="stat-card"><div class="v"><?= $total_vols ?></div><div class="l">Vols au total</div></div>
    <div class="stat-card"><div class="v" style="color:#FF9900"><?= $total_01 ?></div><div class="l">RWY 01</div></div>
    <div class="stat-card"><div class="v"><?= $total_07 ?></div><div class="l">RWY 07</div></div>
  </div>
  <?php endif; ?>

  <!-- Actions -->
  <div class="top-actions">
    <a href="/cron/ebbr_tracks.php?secret=<?= urlencode(defined('CRON_SECRET')?CRON_SECRET:'') ?>&date=<?= date('Y-m-d', strtotime('yesterday')) ?>"
       class="btn btn-blue" target="_blank">Lancer la collecte (hier)</a>
    <span style="font-size:.78rem;color:#aaa">Le cron tourne automatiquement chaque nuit à 03:00 UTC</span>
  </div>

  <!-- Grille des journées -->
  <?php if (empty($days) && $table_ok): ?>
  <div class="empty-state">
    Aucune trace enregistrée pour l'instant.<br>
    Lancez la collecte manuellement ou attendez le cron de cette nuit.
  </div>
  <?php else: ?>
  <div class="days-grid">
    <?php foreach ($days as $day):
      $d    = $day['track_date'];
      $img  = '/medias/tracks/'.$d.'.png';
      $full = __DIR__.'/../medias/tracks/'.$d.'.png';
    ?>
    <div class="day-card">
      <div class="day-card-head">
        <span class="day-date"><?= date('d/m/Y', strtotime($d)) ?></span>
        <div class="day-badges">
          <?php if ($day['n01'] > 0): ?>
            <span class="badge-01">RWY 01 · <?= $day['n01'] ?> vol<?= $day['n01']>1?'s':'' ?></span>
          <?php endif; ?>
          <?php if ($day['n07'] > 0): ?>
            <span class="badge-07">RWY 07 · <?= $day['n07'] ?> vol<?= $day['n07']>1?'s':'' ?></span>
          <?php endif; ?>
        </div>
      </div>

      <?php if (file_exists($full)): ?>
        <img src="<?= $base_url.$img ?>?v=<?= filemtime($full) ?>"
             class="day-img" alt="Traces <?= $d ?>"
             onclick="openLight('<?= $base_url.$img ?>?v=<?= filemtime($full) ?>')">
      <?php else: ?>
        <div class="day-no-img">Image non générée</div>
      <?php endif; ?>

      <div class="day-actions">
        <?php if (file_exists($full)): ?>
        <a href="<?= $base_url.$img ?>" download="casuffit-traces-<?= $d ?>.png"
           class="btn btn-gray">Télécharger</a>
        <?php endif; ?>
        <form method="POST" style="display:inline">
          <?= csrf_field() ?>
          <input type="hidden" name="regen_date" value="<?= $d ?>">
          <button type="submit" class="btn btn-orange">Régénérer</button>
        </form>
        <form method="POST" style="display:inline"
              onsubmit="return confirm('Supprimer toutes les traces du <?= $d ?> ?')">
          <?= csrf_field() ?>
          <input type="hidden" name="del_date" value="<?= $d ?>">
          <button type="submit" class="btn btn-red">Supprimer</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Lightbox -->
<div id="lightbox" onclick="closeLight()">
  <span id="lightbox-close" onclick="closeLight()">✕</span>
  <img id="lightbox-img" src="" alt="">
</div>

<script>
function openLight(src) {
  document.getElementById('lightbox-img').src = src;
  document.getElementById('lightbox').classList.add('open');
}
function closeLight() {
  document.getElementById('lightbox').classList.remove('open');
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLight(); });
</script>
</body>
</html>
