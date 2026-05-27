<?php
// admin/plainte_stats.php — Stats des clics sur le bouton plainte
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../membre/functions.php';
session_start(); requireAdmin();
$db = getDB();

// Vérifier si la table existe
try {
    $db->query("SELECT 1 FROM plainte_clicks LIMIT 1");
    $table_ok = true;
} catch (Exception $e) {
    $table_ok = false;
}

if ($table_ok) {
    // Stats par jour sur 60 jours (membres vs visiteurs)
    $daily = $db->query("
        SELECT
            DATE(clicked_at) as jour,
            source,
            SUM(is_membre)           as nb_membres,
            SUM(1 - is_membre)       as nb_visiteurs,
            COUNT(*)                 as total,
            SUM(alert_level='hors_prs') as nb_hors_prs
        FROM plainte_clicks
        WHERE clicked_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
        GROUP BY DATE(clicked_at), source
        ORDER BY jour DESC, source
    ")->fetchAll();

    // Totaux globaux
    $totaux = $db->query("
        SELECT
            COUNT(*)                 as total,
            SUM(is_membre)           as membres,
            SUM(1-is_membre)         as visiteurs,
            SUM(alert_level='hors_prs') as hors_prs,
            SUM(source='piste_meteo')   as from_meteo,
            SUM(source='historique_vent') as from_histo
        FROM plainte_clicks
    ")->fetch();

    // Grouper par jour (toutes sources confondues pour le graphe)
    $chart_data = $db->query("
        SELECT
            DATE(clicked_at) as jour,
            SUM(is_membre)       as membres,
            SUM(1-is_membre)     as visiteurs
        FROM plainte_clicks
        WHERE clicked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(clicked_at)
        ORDER BY jour ASC
    ")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <?php include __DIR__ . '/../includes/admin_pwa_head.php'; ?>
  <title>Stats plaintes — Admin</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333}
    <?php include __DIR__ . '/../includes/admin_sidebar_css.php'; ?>
    .main{margin-left:240px;padding:28px;max-width:1100px}
    .page-title{font-size:1.2rem;font-weight:800;color:#0e3d6b;margin-bottom:20px}
    .card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,.06);margin-bottom:18px}
    .card h3{font-size:.88rem;font-weight:700;color:#0e3d6b;margin-bottom:14px;padding-bottom:8px;border-bottom:1px solid #eee}
    .kpi-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:18px}
    .kpi{background:#fff;border-radius:10px;padding:16px;box-shadow:0 2px 8px rgba(0,0,0,.06);text-align:center}
    .kpi .val{font-size:2rem;font-weight:900;color:#0e3d6b;line-height:1}
    .kpi .lbl{font-size:.68rem;color:#888;text-transform:uppercase;letter-spacing:.06em;margin-top:5px}
    .kpi.green .val{color:#27ae60}
    .kpi.orange .val{color:#FF9900}
    .kpi.red .val{color:#e53e3e}
    table{width:100%;border-collapse:collapse;font-size:.8rem}
    th{text-align:left;padding:8px 10px;color:#888;font-weight:600;font-size:.68rem;text-transform:uppercase;border-bottom:2px solid #eee}
    td{padding:7px 10px;border-bottom:1px solid #f5f5f5;vertical-align:middle}
    .badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:.65rem;font-weight:700}
    .b-ok{background:#e8f8f0;color:#27ae60}.b-warn{background:#fff8ee;color:#b45309}
    .b-red{background:#fde8e8;color:#c53030}.b-blue{background:#e6f1fb;color:#1673B2}
    /* Graphe barres */
    .chart-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}
    .bar-chart{display:flex;align-items:flex-end;gap:4px;height:160px;padding:0 4px 28px;border-bottom:2px solid #eee;min-width:600px}
    .bar-day{display:flex;flex-direction:column;align-items:center;gap:2px;flex:1;min-width:18px;cursor:default;position:relative}
    .bar-day:hover .bar-tooltip{display:block}
    .bar-stack{display:flex;flex-direction:column-reverse;align-items:center;width:100%;gap:1px}
    .bar-mb{background:#1673B2;border-radius:3px 3px 0 0;width:100%;min-height:2px}
    .bar-vi{background:#b5d4f4;width:100%;min-height:2px}
    .bar-label{font-size:.55rem;color:#bbb;position:absolute;bottom:-22px;white-space:nowrap;transform:rotate(-45deg);transform-origin:top left;left:4px}
    .bar-tooltip{display:none;position:absolute;bottom:105%;left:50%;transform:translateX(-50%);background:#0e3d6b;color:#fff;font-size:.68rem;padding:5px 8px;border-radius:6px;white-space:nowrap;z-index:10;pointer-events:none}
    .bar-tooltip::after{content:'';position:absolute;top:100%;left:50%;transform:translateX(-50%);border:5px solid transparent;border-top-color:#0e3d6b}
    .chart-legend{display:flex;gap:16px;margin-top:8px;font-size:.75rem;color:#555}
    .chart-legend span{display:inline-flex;align-items:center;gap:6px}
    .dot{width:10px;height:10px;border-radius:2px;display:inline-block}
    .dot-mb{background:#1673B2}.dot-vi{background:#b5d4f4}
    .no-data{text-align:center;padding:40px;color:#aaa}
    .install-box{background:#fff8ee;border:1.5px solid #FF9900;border-radius:8px;padding:16px;font-size:.82rem;line-height:1.6}
    .install-box code{background:#f0f4f8;padding:2px 6px;border-radius:4px;font-size:.78rem;color:#0e3d6b}
    @media(max-width:768px){.main{margin-left:0!important;padding:14px!important;padding-top:68px!important}.kpi-row{grid-template-columns:repeat(3,1fr)}}
  </style>
</head>
<body>
<?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>
<div class="main">
  <div class="page-title">📊 Statistiques — Clics sur le bouton Plainte</div>

  <?php if (!$table_ok): ?>
  <div class="install-box">
    <strong>⚠ Table <code>plainte_clicks</code> manquante.</strong><br>
    Exécute la migration SQL dans phpMyAdmin :<br><br>
    <code>source migrate_plainte_clicks.sql</code><br><br>
    ou copie le contenu du fichier <code>migrate_plainte_clicks.sql</code> dans l'onglet SQL de phpMyAdmin.
  </div>

  <?php else: ?>

  <!-- KPIs -->
  <div class="kpi-row">
    <div class="kpi">
      <div class="val"><?= $totaux['total'] ?></div>
      <div class="lbl">Clics total</div>
    </div>
    <div class="kpi green">
      <div class="val"><?= $totaux['membres'] ?></div>
      <div class="lbl">Membres</div>
    </div>
    <div class="kpi">
      <div class="val"><?= $totaux['visiteurs'] ?></div>
      <div class="lbl">Visiteurs</div>
    </div>
    <div class="kpi orange">
      <div class="val"><?= $totaux['hors_prs'] ?></div>
      <div class="lbl">Pendant Hors PRS</div>
    </div>
    <div class="kpi blue" style="--val-color:#1673B2">
      <div class="val" style="color:#1673B2"><?= $totaux['from_meteo'] ?></div>
      <div class="lbl">Via Piste Météo</div>
    </div>
    <div class="kpi">
      <div class="val" style="color:#7c3aed"><?= $totaux['from_histo'] ?></div>
      <div class="lbl">Via Historique</div>
    </div>
  </div>

  <!-- Graphe 30 jours -->
  <?php if (!empty($chart_data)): ?>
  <div class="card">
    <h3>📈 Clics par jour — 30 derniers jours</h3>
    <?php
      $max = max(1, max(array_map(fn($r) => $r['membres'] + $r['visiteurs'], $chart_data)));
    ?>
    <div class="chart-wrap">
      <div class="bar-chart" id="bar-chart">
        <?php foreach ($chart_data as $r):
          $total_day = $r['membres'] + $r['visiteurs'];
          $h_mb = $total_day > 0 ? max(2, round($r['membres'] / $max * 140)) : 0;
          $h_vi = $total_day > 0 ? max(2, round($r['visiteurs'] / $max * 140)) : 0;
          $label = date('d/m', strtotime($r['jour']));
        ?>
        <div class="bar-day" title="">
          <div class="bar-tooltip"><?= $label ?><br>👤 <?= $r['membres'] ?> membres<br>👁 <?= $r['visiteurs'] ?> visiteurs</div>
          <div class="bar-stack" style="height:<?= $h_mb+$h_vi ?>px">
            <?php if($h_mb):?><div class="bar-mb" style="height:<?= $h_mb ?>px"></div><?php endif;?>
            <?php if($h_vi):?><div class="bar-vi" style="height:<?= $h_vi ?>px"></div><?php endif;?>
          </div>
          <span class="bar-label"><?= $label ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="chart-legend">
      <span><span class="dot dot-mb"></span> Membres</span>
      <span><span class="dot dot-vi"></span> Visiteurs</span>
    </div>
  </div>
  <?php endif; ?>

  <!-- Tableau détaillé -->
  <div class="card">
    <h3>📋 Détail par jour (60 derniers jours)</h3>
    <?php if (empty($daily)): ?>
    <div class="no-data">Aucun clic enregistré pour le moment.<br>Les données apparaîtront après le premier clic sur « Générer une plainte ».</div>
    <?php else: ?>
    <table>
      <tr>
        <th>Date</th>
        <th>Source</th>
        <th>Total</th>
        <th>👤 Membres</th>
        <th>👁 Visiteurs</th>
        <th>🔴 Hors PRS</th>
      </tr>
      <?php
      $prev_jour = null;
      foreach ($daily as $r):
        $is_new_day = ($r['jour'] !== $prev_jour);
        $prev_jour = $r['jour'];
      ?>
      <tr<?= $is_new_day ? ' style="border-top:2px solid #eef2f7"' : '' ?>>
        <td style="white-space:nowrap;font-weight:<?= $is_new_day?'700':'400'?>;color:#0e3d6b">
          <?= $is_new_day ? date('D d/m/Y', strtotime($r['jour'])) : '' ?>
        </td>
        <td>
          <span class="badge <?= $r['source']==='piste_meteo'?'b-blue':'b-ok' ?>">
            <?= $r['source']==='piste_meteo' ? '📡 Piste Météo' : '📜 Historique' ?>
          </span>
        </td>
        <td><strong><?= $r['total'] ?></strong></td>
        <td><?= $r['nb_membres'] > 0 ? '<strong style="color:#1673B2">'.$r['nb_membres'].'</strong>' : '<span style="color:#ddd">0</span>' ?></td>
        <td><?= $r['nb_visiteurs'] > 0 ? $r['nb_visiteurs'] : '<span style="color:#ddd">0</span>' ?></td>
        <td><?= $r['nb_hors_prs'] > 0 ? '<span class="badge b-red">'.$r['nb_hors_prs'].'</span>' : '<span style="color:#ddd">—</span>' ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>

  <?php endif; ?>
</div>
</body>
</html>
