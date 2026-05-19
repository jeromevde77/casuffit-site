<?php
// admin/landing_stats.php — Stats des visites depuis flyers / QR codes
require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();
require_once __DIR__ . '/../includes/csrf.php';

$db = getDB();

// Période — défaut 30 jours
$days = isset($_GET['days']) ? max(1, min(365, (int)$_GET['days'])) : 30;
$since = date('Y-m-d', strtotime("-$days days"));

// Vérifier que la table existe
$hasTable = false;
try {
    $db->query("SELECT 1 FROM landing_stats LIMIT 1");
    $hasTable = true;
} catch (Exception $e) {}

$total = $unique = 0; $bySource = $byCampaign = $byDay = $byLang = [];
if ($hasTable) {
    $total = (int)$db->prepare("SELECT COUNT(*) FROM landing_stats WHERE visited_at >= ?")
        ->execute([$since]) ?: 0;
    $stmt = $db->prepare("SELECT COUNT(*) FROM landing_stats WHERE visited_at >= ?");
    $stmt->execute([$since]);
    $total = (int)$stmt->fetchColumn();
    $stmt = $db->prepare("SELECT COUNT(DISTINCT ip_hash) FROM landing_stats WHERE visited_at >= ?");
    $stmt->execute([$since]);
    $unique = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT source, COUNT(*) AS n, COUNT(DISTINCT ip_hash) AS u FROM landing_stats WHERE visited_at >= ? GROUP BY source ORDER BY n DESC");
    $stmt->execute([$since]);
    $bySource = $stmt->fetchAll();

    $stmt = $db->prepare("SELECT campaign, COUNT(*) AS n, COUNT(DISTINCT ip_hash) AS u FROM landing_stats WHERE visited_at >= ? AND campaign IS NOT NULL AND campaign != '' GROUP BY campaign ORDER BY n DESC");
    $stmt->execute([$since]);
    $byCampaign = $stmt->fetchAll();

    $stmt = $db->prepare("SELECT DATE(visited_at) AS jour, COUNT(*) AS n FROM landing_stats WHERE visited_at >= ? GROUP BY DATE(visited_at) ORDER BY jour DESC LIMIT 30");
    $stmt->execute([$since]);
    $byDay = $stmt->fetchAll();

    $stmt = $db->prepare("SELECT lang, COUNT(*) AS n FROM landing_stats WHERE visited_at >= ? GROUP BY lang");
    $stmt->execute([$since]);
    $byLang = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Stats flyers — Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
<?php include __DIR__ . '/../includes/admin_sidebar_css.php'; ?>
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; margin:0; background:#f5f7fa; color:#222; }
.main { margin-left: 240px; padding: 24px 28px; }
@media (max-width: 768px) { .main { margin-left: 0; padding-top: 60px; } }
h1 { font-size: 1.4rem; color: #1673B2; font-weight: 700; margin-bottom: 14px; }
.period { display:flex; gap:6px; margin-bottom: 20px; flex-wrap: wrap; }
.period a { padding: 6px 14px; border-radius: 5px; text-decoration: none; font-size: .82rem; font-weight: 600; color: #1673B2; border: 1.5px solid #d0d8e0; background: #fff; }
.period a.active { background: #1673B2; color: #fff; border-color: #1673B2; }

.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 24px; }
.stat-card { background: #fff; border-radius: 8px; padding: 14px 18px; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
.stat-card .v { font-size: 1.8rem; font-weight: 800; color: #1673B2; }
.stat-card .l { font-size: .75rem; color: #888; text-transform: uppercase; letter-spacing: .04em; margin-top: 4px; }

.panels { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
@media (max-width: 900px) { .panels { grid-template-columns: 1fr; } }

.panel { background: #fff; border-radius: 8px; padding: 18px; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
.panel h2 { font-size: .95rem; color: #1673B2; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid #e8eef3; font-weight: 700; }

table { width: 100%; border-collapse: collapse; font-size: .85rem; }
table th { text-align: left; padding: 8px 10px; background: #f5f7fa; color: #555; font-weight: 600; font-size: .75rem; text-transform: uppercase; letter-spacing: .03em; }
table td { padding: 8px 10px; border-bottom: 1px solid #f0f3f7; }
table td.num { text-align: right; font-weight: 700; color: #1673B2; font-family: monospace; }
table tr:last-child td { border-bottom: none; }

.empty { text-align: center; padding: 30px; color: #aaa; font-size: .9rem; }
.alert { background: #fff8ee; border-left: 3px solid #FF9900; padding: 12px 16px; border-radius: 4px; margin-bottom: 20px; font-size: .88rem; color: #856404; }
.alert code { background: #fff; padding: 2px 6px; border-radius: 3px; font-size: .85rem; color: #1673B2; }
</style>
</head>
<body>

<?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

<div class="main">
  <h1>📊 Stats flyers & QR codes</h1>

  <?php if (!$hasTable): ?>
    <div class="alert">
      <strong>Table manquante.</strong> Crée d'abord la table <code>landing_stats</code> dans phpMyAdmin avec le SQL fourni dans <code>migrate_landing_stats.sql</code>.
    </div>
  <?php endif; ?>

  <div class="period">
    <a href="?days=7"  class="<?= $days == 7  ? 'active' : '' ?>">7 jours</a>
    <a href="?days=30" class="<?= $days == 30 ? 'active' : '' ?>">30 jours</a>
    <a href="?days=90" class="<?= $days == 90 ? 'active' : '' ?>">3 mois</a>
    <a href="?days=365" class="<?= $days == 365 ? 'active' : '' ?>">1 an</a>
  </div>

  <div class="stats-grid">
    <div class="stat-card">
      <div class="v"><?= number_format($total, 0, ',', ' ') ?></div>
      <div class="l">Visites totales</div>
    </div>
    <div class="stat-card">
      <div class="v"><?= number_format($unique, 0, ',', ' ') ?></div>
      <div class="l">Visiteurs uniques</div>
    </div>
    <div class="stat-card">
      <div class="v"><?= $total > 0 ? round($total / max(1, $unique), 1) : 0 ?></div>
      <div class="l">Visites / visiteur</div>
    </div>
    <div class="stat-card">
      <div class="v"><?= count($bySource) ?></div>
      <div class="l">Sources actives</div>
    </div>
  </div>

  <div class="panels">

    <div class="panel">
      <h2>🚩 Par source (utm_source)</h2>
      <?php if (empty($bySource)): ?>
        <div class="empty">Aucune visite trackée.<br>Imprime des QR codes et reviens ici !</div>
      <?php else: ?>
        <table>
          <tr><th>Source</th><th style="text-align:right">Visites</th><th style="text-align:right">Uniques</th></tr>
          <?php foreach ($bySource as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['source']) ?></td>
              <td class="num"><?= number_format($r['n'], 0, ',', ' ') ?></td>
              <td class="num"><?= number_format($r['u'], 0, ',', ' ') ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>
    </div>

    <div class="panel">
      <h2>🎯 Par campagne (utm_campaign)</h2>
      <?php if (empty($byCampaign)): ?>
        <div class="empty">Aucune campagne trackée.</div>
      <?php else: ?>
        <table>
          <tr><th>Campagne</th><th style="text-align:right">Visites</th><th style="text-align:right">Uniques</th></tr>
          <?php foreach ($byCampaign as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['campaign']) ?></td>
              <td class="num"><?= number_format($r['n'], 0, ',', ' ') ?></td>
              <td class="num"><?= number_format($r['u'], 0, ',', ' ') ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>
    </div>

    <div class="panel">
      <h2>📅 Par jour (30 derniers)</h2>
      <?php if (empty($byDay)): ?>
        <div class="empty">—</div>
      <?php else: ?>
        <table>
          <tr><th>Jour</th><th style="text-align:right">Visites</th></tr>
          <?php foreach ($byDay as $r): ?>
            <tr>
              <td><?= date('d/m/Y', strtotime($r['jour'])) ?></td>
              <td class="num"><?= number_format($r['n'], 0, ',', ' ') ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>
    </div>

    <div class="panel">
      <h2>🌐 Par langue</h2>
      <?php if (empty($byLang)): ?>
        <div class="empty">—</div>
      <?php else: ?>
        <table>
          <tr><th>Langue</th><th style="text-align:right">Visites</th></tr>
          <?php foreach ($byLang as $r): ?>
            <tr>
              <td><?= strtoupper(htmlspecialchars($r['lang'])) ?></td>
              <td class="num"><?= number_format($r['n'], 0, ',', ' ') ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>
    </div>

  </div>
</div>

</body>
</html>
