<?php
// admin/dons.php
require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();
require_once __DIR__ . '/../includes/csrf.php';
$db = getDB();

// Mise à jour statut
if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_verify();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['don_id'], $_POST['statut'])) {
    $allowed = ['en_attente','recu','annule'];
    if (in_array($_POST['statut'], $allowed) && is_numeric($_POST['don_id'])) {
        $db->prepare("UPDATE dons SET statut=? WHERE id=?")->execute([$_POST['statut'], $_POST['don_id']]);
    }
    header('Location: dons.php'); exit;
}

$total_recu    = $db->query("SELECT COALESCE(SUM(montant),0) FROM dons WHERE statut='recu'")->fetchColumn();
$total_attente = $db->query("SELECT COALESCE(SUM(montant),0) FROM dons WHERE statut='en_attente'")->fetchColumn();
$dons = $db->query("SELECT * FROM dons ORDER BY date_don DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<?php include __DIR__ . '/../includes/admin_pwa_head.php'; ?>
  <title>Dons — Admin Piste 01</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: "Helvetica Neue", Arial, sans-serif; background: #f0f4f8; color: #333; }
    .sidebar { position: fixed; top: 0; left: 0; width: 220px; height: 100vh; background: linear-gradient(180deg, #0e3d6b, #1673B2); }
    .sidebar-brand { padding: 24px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); }
    .sidebar-brand h2 { color: #fff; font-size: 1rem; font-weight: 800; }
    .sidebar-brand h2 span { color: #FF9900; font-style: italic; }
    .sidebar-brand p { color: rgba(255,255,255,0.55); font-size: 0.68rem; margin-top: 3px; text-transform: uppercase; letter-spacing: 0.08em; }
    .sidebar nav a { display: flex; align-items: center; gap: 10px; padding: 12px 20px; color: rgba(255,255,255,0.75); text-decoration: none; font-size: 0.85rem; transition: background 0.15s; }
    .sidebar nav a:hover, .sidebar nav a.active { background: rgba(255,255,255,0.12); color: #fff; }
    .sidebar nav a.active { border-left: 3px solid #FF9900; }
    .sidebar-footer { position: absolute; bottom: 0; width: 100%; padding: 16px 20px; border-top: 1px solid rgba(255,255,255,0.1); }
    .sidebar-footer a { color: rgba(255,255,255,0.5); font-size: 0.75rem; text-decoration: none; }
    .main { margin-left:240px; padding: 32px; }
    .page-title { font-size: 1.4rem; font-weight: 800; color: #0e3d6b; margin-bottom: 24px; }
    .stats { display: flex; gap: 16px; margin-bottom: 24px; }
    .stat { background: #fff; border-radius: 12px; padding: 20px 28px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); }
    .stat .val { font-size: 1.8rem; font-weight: 800; color: #27ae60; }
    .stat .val.orange { color: #FF9900; }
    .stat .lbl { font-size: 0.72rem; color: #888; text-transform: uppercase; letter-spacing: 0.06em; margin-top: 4px; }
    .card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
    th { text-align: left; padding: 8px 10px; color: #888; font-weight: 600; font-size: 0.72rem; text-transform: uppercase; border-bottom: 2px solid #eee; }
    td { padding: 9px 10px; border-bottom: 1px solid #f5f5f5; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 0.68rem; font-weight: 600; }
    .badge-green { background: #e8f8f0; color: #27ae60; }
    .badge-orange { background: #fff3e0; color: #FF9900; }
    .badge-red { background: #fde8e8; color: #c0392b; }
    select { padding: 5px 8px; border: 1px solid #dde; border-radius: 6px; font-size: 0.75rem; }
    button.inline { padding: 5px 10px; background: #1673B2; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-size: 0.75rem; }
  
  @media (max-width: 768px) {
    .main { margin-left: 0 !important; padding: 16px !important; padding-top: 68px !important; }
    .grid2, .cards-grid { grid-template-columns: 1fr !important; }
    table { font-size: .75rem; }
    table th, table td { padding: 6px 8px !important; }
    .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .form-row { grid-template-columns: 1fr !important; }
    .btn, button[type=submit], .btn-save { width: 100%; justify-content: center; }
  }
</style>
</head>
<body>
<div class="sidebar">
  <div class="sidebar-brand"><h2>Piste 01 <span>ça suffit !</span></h2><p>Administration</p></div>
  <nav>
    <a href="dashboard.php">📊 Dashboard</a>
    <a href="subscribers.php">📋 Abonnés</a>
    <a href="membres.php">👥 Membres</a>
    <a href="coda.php">🏦 Import CODA</a>
    <a href="compose.php">✉ Nouvelle newsletter</a>
    <a href="newsletters.php">📨 Historique envois</a>
    <a href="dons.php" class="active">💶 Dons</a>
    <a href="config.php">⚙ Montants / Config</a>
  </nav>
  <div class="sidebar-footer"><a href="logout.php">⎋ Déconnexion</a></div>
</div>
<div class="main">
  <div class="page-title">💶 Suivi des dons</div>
  <div class="stats">
    <div class="stat"><div class="val"><?= number_format($total_recu, 2, ',', '.') ?> €</div><div class="lbl">Total reçu (confirmé)</div></div>
    <div class="stat"><div class="val orange"><?= number_format($total_attente, 2, ',', '.') ?> €</div><div class="lbl">En attente de confirmation</div></div>
  </div>
  <div class="card">
    <table>
      <tr><th>Montant</th><th>Nom</th><th>Email</th><th>Note</th><th>Statut</th><th>Date</th><th>Action</th></tr>
      <?php foreach ($dons as $d): ?>
      <tr>
        <td><strong><?= number_format($d['montant'], 2, ',', '.') ?> €</strong></td>
        <td><?= htmlspecialchars($d['nom'] ?: '—') ?></td>
        <td><?= htmlspecialchars($d['email'] ?: '—') ?></td>
        <td><?= htmlspecialchars(mb_strimwidth((isset($d['note']) ? $d['note'] : ''), 0, 40, '…')) ?></td>
        <td>
          <?php if ($d['statut']==='recu'): ?><span class="badge badge-green">✓ Reçu</span>
          <?php elseif ($d['statut']==='en_attente'): ?><span class="badge badge-orange">En attente</span>
          <?php else: ?><span class="badge badge-red">Annulé</span><?php endif; ?>
        </td>
        <td><?= date('d/m/Y', strtotime($d['date_don'])) ?></td>
        <td>
          <form method="POST" style="display:flex;gap:6px;align-items:center;"><?= csrf_field() ?>
            <input type="hidden" name="don_id" value="<?= $d['id'] ?>">
            <select name="statut">
              <option value="en_attente" <?= $d['statut']==='en_attente'?'selected':'' ?>>En attente</option>
              <option value="recu" <?= $d['statut']==='recu'?'selected':'' ?>>Reçu</option>
              <option value="annule" <?= $d['statut']==='annule'?'selected':'' ?>>Annulé</option>
            </select>
            <button type="submit" class="inline">OK</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
</body>
</html>
