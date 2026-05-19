<?php
// admin/dons_all.php — Vue consolidée de tous les dons
require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();
require_once __DIR__ . '/../includes/csrf.php';
$db = getDB();

// Filtres
$filtre_statut  = isset($_GET['statut'])  ? $_GET['statut']  : '';
$filtre_membre  = isset($_GET['membre'])  ? $_GET['membre']  : '';
$filtre_periode = isset($_GET['periode']) ? $_GET['periode'] : '';

// Confirmer un don
if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_verify();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmer'])) {
    $id = intval($_POST['don_id']);
    $db->prepare("UPDATE member_dons SET statut='confirme' WHERE id=?")->execute(array($id));
    header('Location: dons_all.php?msg=confirme'); exit;
}

// Annuler un don
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['annuler'])) {
    $id = intval($_POST['don_id']);
    $db->prepare("UPDATE member_dons SET statut='annule' WHERE id=?")->execute(array($id));
    header('Location: dons_all.php?msg=annule'); exit;
}

$msg = isset($_GET['msg']) ? $_GET['msg'] : '';

// Stats globales
$total_confirme  = $db->query("SELECT COALESCE(SUM(montant),0) FROM member_dons WHERE statut='confirme'")->fetchColumn();
$total_attente   = $db->query("SELECT COALESCE(SUM(montant),0) FROM member_dons WHERE statut='en_attente'")->fetchColumn();
$nb_donateurs    = $db->query("SELECT COUNT(DISTINCT member_id) FROM member_dons WHERE statut='confirme'")->fetchColumn();
$nb_dons         = $db->query("SELECT COUNT(*) FROM member_dons WHERE statut='confirme'")->fetchColumn();

// Construire la requête avec filtres
$where = array('1=1');
$params = array();

if ($filtre_statut) {
    $where[] = 'd.statut = ?';
    $params[] = $filtre_statut;
}
if ($filtre_membre) {
    $where[] = '(m.prenom LIKE ? OR m.nom LIKE ? OR m.email LIKE ? OR m.code_membre LIKE ?)';
    $params[] = "%$filtre_membre%";
    $params[] = "%$filtre_membre%";
    $params[] = "%$filtre_membre%";
    $params[] = "%$filtre_membre%";
}
if ($filtre_periode === 'mois') {
    $where[] = 'd.date_don >= DATE_SUB(NOW(), INTERVAL 1 MONTH)';
} elseif ($filtre_periode === 'annee') {
    $where[] = 'YEAR(d.date_don) = YEAR(NOW())';
}

$where_sql = implode(' AND ', $where);

$stmt = $db->prepare("
    SELECT d.*, m.prenom, m.nom, m.email, m.code_membre, m.commune
    FROM member_dons d
    JOIN members m ON m.id = d.member_id
    WHERE $where_sql
    ORDER BY d.date_don DESC
");
$stmt->execute($params);
$dons = $stmt->fetchAll();

// Top donateurs
$top_donateurs = $db->query("
    SELECT m.prenom, m.nom, m.code_membre, m.commune,
           COUNT(d.id) as nb_dons,
           SUM(d.montant) as total
    FROM member_dons d
    JOIN members m ON m.id = d.member_id
    WHERE d.statut = 'confirme'
    GROUP BY m.id
    ORDER BY total DESC
    LIMIT 10
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<?php include __DIR__ . '/../includes/admin_pwa_head.php'; ?>
  <title>Tous les dons — Admin</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333}
    <?php include __DIR__ . '/../includes/admin_sidebar_css.php'; ?>
    .main{margin-left:240px;padding:24px;max-width:1200px}
    .page-title{font-size:1.2rem;font-weight:800;color:#0e3d6b;margin-bottom:20px}
    .stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px}
    .stat-box{background:#fff;border-radius:10px;padding:16px;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,.06)}
    .stat-val{font-size:1.6rem;font-weight:800;color:#1673B2}
    .stat-val.green{color:#27ae60}
    .stat-val.orange{color:#FF9900}
    .stat-lab{font-size:.68rem;color:#888;text-transform:uppercase;letter-spacing:.05em;margin-top:3px}
    .card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,.06);margin-bottom:18px;overflow-x:auto}
    .card h3{font-size:.88rem;font-weight:700;color:#0e3d6b;margin-bottom:14px;padding-bottom:8px;border-bottom:1px solid #eee}
    .filters{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;align-items:flex-end}
    .filters input,.filters select{padding:7px 10px;border:1.5px solid #dde4ed;border-radius:6px;font-size:.8rem;font-family:inherit;outline:none}
    .filters input:focus,.filters select:focus{border-color:#1673B2}
    .btn{padding:7px 14px;border-radius:6px;font-size:.78rem;font-weight:600;cursor:pointer;border:1.5px solid transparent;font-family:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:all .15s;line-height:1}
.btn-p{background:#1673B2;color:#fff;border-color:#1673B2}
.btn-p:hover{background:#125a90;color:#fff;text-decoration:none}
.btn-g{background:#f0f4f8;color:#555;border-color:#dde4ed}
.btn-g:hover{background:#e0e8f0;color:#333;text-decoration:none}
.btn-r{background:#fff5f5;color:#e53e3e;border-color:#fed7d7}
.btn-r:hover{background:#fee2e2;text-decoration:none}
.btn-sm{padding:4px 10px;font-size:.72rem}
.btn-apercu-mobile{display:none}
.btn-retour-mobile{display:none;font-size:.78rem;color:rgba(255,255,255,.85);text-decoration:none;font-weight:600;margin-bottom:8px;align-items:center;gap:4px}
    table{width:100%;border-collapse:collapse;font-size:.8rem;min-width:600px}
    th{text-align:left;padding:8px 10px;color:#888;font-weight:600;font-size:.68rem;text-transform:uppercase;border-bottom:2px solid #eee;white-space:nowrap}
    td{padding:8px 10px;border-bottom:1px solid #f5f5f5;vertical-align:middle}
    tr:hover td{background:#f8fbff}
    .badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:.65rem;font-weight:700}
    .b-ok{background:#e8f8f0;color:#27ae60}
    .b-wait{background:#fff3e0;color:#ba7517}
    .b-off{background:#fde8e8;color:#c53030}
    .ogm{font-family:monospace;font-size:.72rem;color:#1673B2}
    .member-info{line-height:1.4}
    .member-name{font-weight:600;color:#0e3d6b;font-size:.82rem}
    .member-code{font-size:.68rem;color:#888;font-family:monospace}
    .flash-ok{background:#e8f8f0;color:#276749;padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:.82rem;border-left:3px solid #48bb78}
    .total-row td{font-weight:700;background:#f0f7ff;color:#0e3d6b}
    .grid2{display:grid;grid-template-columns:2fr 1fr;gap:16px}
  
  @media (max-width: 768px) {
    .main { margin-left: 0 !important; padding: 16px !important; padding-top: 68px !important; }
    .grid2, .cards-grid { grid-template-columns: 1fr !important; }
    table { font-size: .75rem; }
    table th, table td { padding: 6px 8px !important; }
    .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .form-row { grid-template-columns: 1fr !important; }
    .btn, button[type=submit], .btn-save { width: 100%; justify-content: center; }
  }
.act-btn{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;border:1.5px solid;cursor:pointer;text-decoration:none;transition:all .15s;background:none;font-family:inherit;flex-shrink:0}
.act-btn.edit{color:#4a5568;border-color:#e2e8f0;background:#f7f8fa}
.act-btn.edit:hover{background:#edf2f7;border-color:#cbd5e0;color:#2d3748;text-decoration:none}
.act-btn.del{color:#e53e3e;border-color:#fed7d7;background:#fff5f5}
.act-btn.del:hover{background:#fee2e2;border-color:#fc8181;text-decoration:none}
.act-btn.view{color:#38a169;border-color:#c6f6d5;background:#f0fff4}
.act-btn.view:hover{background:#dcfce7;text-decoration:none}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

<div class="main">
  <div class="page-title">Tous les dons</div>

  <?php if ($msg === 'confirme'): ?>
    <div class="flash-ok">Don confirmé.</div>
  <?php elseif ($msg === 'annule'): ?>
    <div class="flash-ok">Don annulé.</div>
  <?php endif; ?>

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-box">
      <div class="stat-val green"><?= number_format($total_confirme, 0, ',', ' ') ?> €</div>
      <div class="stat-lab">Total confirmé</div>
    </div>
    <div class="stat-box">
      <div class="stat-val orange"><?= number_format($total_attente, 0, ',', ' ') ?> €</div>
      <div class="stat-lab">En attente</div>
    </div>
    <div class="stat-box">
      <div class="stat-val"><?= $nb_donateurs ?></div>
      <div class="stat-lab">Donateurs</div>
    </div>
    <div class="stat-box">
      <div class="stat-val"><?= $nb_dons ?></div>
      <div class="stat-lab">Dons confirmés</div>
    </div>
  </div>

  <div class="grid2">

    <!-- Liste des dons -->
    <div class="card">
      <h3>Historique des dons (<?= count($dons) ?>)</h3>

      <!-- Filtres -->
      <form method="GET" class="filters">
        <input type="text" name="membre" placeholder="Nom, email, code..." value="<?= htmlspecialchars($filtre_membre) ?>">
        <select name="statut">
          <option value="">Tous les statuts</option>
          <option value="confirme"   <?= $filtre_statut==='confirme'   ?'selected':'' ?>>Confirmés</option>
          <option value="en_attente" <?= $filtre_statut==='en_attente' ?'selected':'' ?>>En attente</option>
          <option value="annule"     <?= $filtre_statut==='annule'     ?'selected':'' ?>>Annulés</option>
        </select>
        <select name="periode">
          <option value="">Toute la période</option>
          <option value="mois"  <?= $filtre_periode==='mois'  ?'selected':'' ?>>Ce mois</option>
          <option value="annee" <?= $filtre_periode==='annee' ?'selected':'' ?>>Cette année</option>
        </select>
        <button type="submit" class="btn btn-primary">Filtrer</button>
        <a href="dons_all.php" class="btn btn-grey">Réinitialiser</a>
      </form>

      <?php if (empty($dons)): ?>
        <p style="color:#aaa;text-align:center;padding:20px;font-size:.85rem">Aucun don trouvé.</p>
      <?php else: ?>
      <table>
        <tr>
          <th>Date</th>
          <th>Membre</th>
          <th>Montant</th>
          <th>OGM / Communication</th>
          <th>Statut</th>
          <th>Action</th>
        </tr>
        <?php
        $total_filtre = 0;
        foreach ($dons as $d):
            $total_filtre += ($d['statut'] === 'confirme') ? $d['montant'] : 0;
        ?>
        <tr>
          <td style="white-space:nowrap;color:#888;font-size:.75rem"><?= date('d/m/Y', strtotime($d['date_don'])) ?></td>
          <td>
            <div class="member-info">
              <div class="member-name"><?= htmlspecialchars($d['prenom'].' '.$d['nom']) ?></div>
              <div class="member-code"><?= htmlspecialchars($d['code_membre']) ?></div>
              <?php if ($d['commune']): ?>
              <div style="font-size:.68rem;color:#aaa"><?= htmlspecialchars($d['commune']) ?></div>
              <?php endif; ?>
            </div>
          </td>
          <td><strong style="color:#0e3d6b;font-size:.92rem"><?= number_format($d['montant'], 2, ',', ' ') ?> €</strong></td>
          <td><span class="ogm"><?= htmlspecialchars($d['ogm_don'] ?: ($d['communication'] ?: '—')) ?></span></td>
          <td>
            <?php if ($d['statut']==='confirme'): ?>
              <span class="badge b-ok">Confirmé</span>
            <?php elseif ($d['statut']==='en_attente'): ?>
              <span class="badge b-wait">En attente</span>
            <?php else: ?>
              <span class="badge b-off">Annulé</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($d['statut']==='en_attente'): ?>
            <form method="POST" style="display:inline"><?= csrf_field() ?>
              <input type="hidden" name="don_id" value="<?= $d['id'] ?>">
              <button name="confirmer" class="btn btn-green" style="padding:4px 8px;font-size:.7rem">Confirmer</button>
            </form>
            <?php elseif ($d['statut']==='confirme'): ?>
            <form method="POST" style="display:inline" onsubmit="return confirm('Annuler ce don ?')"><?= csrf_field() ?>
              <input type="hidden" name="don_id" value="<?= $d['id'] ?>">
              <button name="annuler" class="btn btn-red" style="padding:4px 8px;font-size:.7rem">Annuler</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if ($total_filtre > 0): ?>
        <tr class="total-row">
          <td colspan="2" style="text-align:right">Total confirmé (filtre) :</td>
          <td colspan="4"><?= number_format($total_filtre, 2, ',', ' ') ?> €</td>
        </tr>
        <?php endif; ?>
      </table>
      <?php endif; ?>
    </div>

    <!-- Top donateurs -->
    <div class="card">
      <h3>Top donateurs</h3>
      <?php if (empty($top_donateurs)): ?>
        <p style="color:#aaa;font-size:.82rem;text-align:center;padding:16px">Aucun don confirmé.</p>
      <?php else: ?>
      <table>
        <tr><th>#</th><th>Membre</th><th>Dons</th><th>Total</th></tr>
        <?php foreach ($top_donateurs as $i => $m): ?>
        <tr>
          <td style="color:#aaa;font-size:.75rem"><?= $i+1 ?></td>
          <td>
            <div class="member-name"><?= htmlspecialchars($m['prenom'].' '.$m['nom']) ?></div>
            <div style="font-size:.68rem;color:#aaa"><?= htmlspecialchars($m['commune'] ?: '') ?></div>
          </td>
          <td style="color:#888;font-size:.78rem"><?= $m['nb_dons'] ?>x</td>
          <td><strong style="color:#27ae60"><?= number_format($m['total'], 0, ',', ' ') ?> €</strong></td>
        </tr>
        <?php endforeach; ?>
      </table>
      <?php endif; ?>

      <!-- Export CSV -->
      <div style="margin-top:14px">
        <a href="dons_all.php?export=csv" class="btn btn-primary" style="width:100%;text-align:center;display:block">
          Exporter en CSV
        </a>
      </div>
    </div>

  </div>
</div>

<?php
// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $all = $db->query("
        SELECT d.date_don, m.code_membre, m.prenom, m.nom, m.email, m.commune,
               d.montant, d.ogm_don, d.communication, d.statut
        FROM member_dons d
        JOIN members m ON m.id = d.member_id
        ORDER BY d.date_don DESC
    ")->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="dons_' . date('Y-m-d') . '.csv"');
    echo "\xEF\xBB\xBF"; // BOM UTF-8
    echo "Date,Code membre,Prénom,Nom,Email,Commune,Montant,OGM,Communication,Statut\n";
    foreach ($all as $row) {
        echo implode(',', array_map(function($v) {
            return '"' . str_replace('"', '""', $v) . '"';
        }, array(
            date('d/m/Y', strtotime($row['date_don'])),
            $row['code_membre'], $row['prenom'], $row['nom'],
            $row['email'], $row['commune'],
            number_format($row['montant'], 2, '.', ''),
            $row['ogm_don'], $row['communication'], $row['statut']
        ))) . "\n";
    }
    exit;
}
?>
</body>
</html>
