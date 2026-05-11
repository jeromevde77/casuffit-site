<?php
// admin/members.php — Gestion des membres
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../membre/functions.php';
session_start(); requireAdmin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmer_don'])) {
    $db->prepare("UPDATE member_dons SET statut='confirme' WHERE id=?")->execute(array(intval($_POST['don_id'])));
    header('Location: members.php?msg=confirme'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_don'])) {
    $member_id = intval($_POST['member_id']);
    $montant   = floatval(str_replace(',', '.', isset($_POST['montant']) ? $_POST['montant'] : 0));
    $comm      = htmlspecialchars(trim(isset($_POST['communication']) ? $_POST['communication'] : ''), ENT_QUOTES, 'UTF-8');
    $statut    = (isset($_POST['statut']) && $_POST['statut'] === 'confirme') ? 'confirme' : 'en_attente';
    if ($montant > 0 && $member_id > 0) {
        $db->prepare("INSERT INTO member_dons (member_id, montant, communication, statut) VALUES (?,?,?,?)")
           ->execute(array($member_id, $montant, $comm, $statut));
    }
    header('Location: members.php?msg=don_ajoute'); exit;
}

$membres = $db->query("
    SELECT m.*,
           COUNT(d.id) as nb_dons,
           COALESCE(SUM(CASE WHEN d.statut='confirme' THEN d.montant ELSE 0 END), 0) as total_dons
    FROM members m
    LEFT JOIN member_dons d ON d.member_id = m.id
    GROUP BY m.id
    ORDER BY m.date_inscription DESC
")->fetchAll();

$dons_recents = $db->query("
    SELECT d.*, m.prenom, m.nom, m.email, m.code_membre, m.ogm
    FROM member_dons d
    JOIN members m ON m.id = d.member_id
    ORDER BY d.date_don DESC LIMIT 20
")->fetchAll();

$msg = isset($_GET['msg']) ? $_GET['msg'] : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<?php include __DIR__ . '/../includes/admin_pwa_head.php'; ?>
  <title>Membres — Admin ça suffit !</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333}
    <?php include __DIR__ . '/../includes/admin_sidebar_css.php'; ?>
    .main{margin-left:240px;padding:28px;max-width:1200px}
    .page-title{font-size:1.2rem;font-weight:800;color:#0e3d6b;margin-bottom:20px}
    .card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,.06);margin-bottom:18px;overflow-x:auto}
    .card h3{font-size:.88rem;font-weight:700;color:#0e3d6b;margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid #eee}
    table{width:100%;border-collapse:collapse;font-size:.8rem;white-space:nowrap}
    th{text-align:left;padding:8px 10px;color:#888;font-weight:600;font-size:.68rem;text-transform:uppercase;border-bottom:2px solid #eee}
    td{padding:8px 10px;border-bottom:1px solid #f5f5f5;vertical-align:middle}
    .badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:.65rem;font-weight:700}
    .b-ok{background:#e8f8f0;color:#27ae60}
    .b-wait{background:#fff3e0;color:#ba7517}
    .b-off{background:#fde8e8;color:#c53030}
    .flash-ok{background:#e8f8f0;color:#276749;padding:11px 14px;border-radius:8px;margin-bottom:16px;font-size:.82rem;border-left:3px solid #48bb78}
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
    input[type=number],input[type=text],select{padding:6px 10px;border:1.5px solid #dde4ed;border-radius:6px;font-size:.8rem;font-family:inherit;outline:none}
    .ogm{font-family:monospace;font-size:.75rem;color:#1673B2;font-weight:700}
    .form-inline{display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap}
    .form-inline label{font-size:.7rem;color:#888;display:block;margin-bottom:3px}
  
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
  <div class="page-title">Gestion des membres</div>

  <?php if ($msg === 'confirme'): ?>
    <div class="flash-ok">Don confirmé.</div>
  <?php elseif ($msg === 'don_ajoute'): ?>
    <div class="flash-ok">Don ajouté.</div>
  <?php endif; ?>

  <!-- Liste membres -->
  <div class="card">
    <h3>Membres inscrits (<?= count($membres) ?>)</h3>
    <table>
      <tr>
        <th>Code</th><th>Prénom Nom</th><th>Email</th><th>OGM</th>
        <th>Adresse</th><th>Commune</th><th>NL</th><th>Dons</th><th>Total</th><th>Inscrit le</th>
      </tr>
      <?php foreach ($membres as $m): ?>
      <tr>
        <td><span style="font-family:monospace;font-size:.72rem;font-weight:700;color:#1673B2"><?= htmlspecialchars($m['code_membre']) ?></span></td>
        <td><?= htmlspecialchars($m['prenom'].' '.$m['nom']) ?></td>
        <td style="font-size:.75rem"><?= htmlspecialchars($m['email']) ?></td>
        <td><span class="ogm"><?= htmlspecialchars($m['ogm']) ?></span></td>
        <td style="font-size:.75rem"><?= htmlspecialchars($m['adresse'] ?? '—') ?></td>
        <td><?= htmlspecialchars($m['commune'] ?: '—') ?></td>
        <td><?= $m['newsletter'] ? '<span class="badge b-ok">✓</span>' : '<span class="badge b-off">✗</span>' ?></td>
        <td><?= $m['nb_dons'] ?></td>
        <td><strong><?= number_format($m['total_dons'], 0, ',', ' ') ?> €</strong></td>
        <td style="font-size:.72rem;color:#aaa"><?= date('d/m/Y', strtotime($m['date_inscription'])) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <!-- Ajouter un don manuellement -->
  <div class="card">
    <h3>Enregistrer un don (virement reçu)</h3>
    <form method="POST" class="form-inline">
      <div>
        <label>Membre</label>
        <select name="member_id" required style="min-width:200px">
          <option value="">Sélectionner...</option>
          <?php foreach ($membres as $m): ?>
          <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['code_membre'].' — '.$m['prenom'].' '.$m['nom']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Montant (€)</label>
        <input type="number" name="montant" step="0.01" min="1" placeholder="50" style="width:80px" required>
      </div>
      <div>
        <label>Communication OGM</label>
        <input type="text" name="communication" placeholder="+++000/0000/00000+++" style="width:180px">
      </div>
      <div>
        <label>Statut</label>
        <select name="statut">
          <option value="confirme">Confirmé</option>
          <option value="en_attente">En attente</option>
        </select>
      </div>
      <button type="submit" name="ajouter_don" class="btn btn-primary">Enregistrer</button>
    </form>
  </div>

  <!-- Dons récents -->
  <div class="card">
    <h3>Dons récents (20 derniers)</h3>
    <table>
      <tr><th>Membre</th><th>OGM membre</th><th>Montant</th><th>Communication</th><th>Statut</th><th>Date</th><th>Action</th></tr>
      <?php foreach ($dons_recents as $d): ?>
      <tr>
        <td>
          <div style="font-size:.8rem;font-weight:600"><?= htmlspecialchars($d['prenom'].' '.$d['nom']) ?></div>
          <div style="font-size:.68rem;color:#888;font-family:monospace"><?= htmlspecialchars($d['code_membre']) ?></div>
        </td>
        <td><span class="ogm"><?= htmlspecialchars($d['ogm']) ?></span></td>
        <td><strong><?= number_format($d['montant'], 2, ',', ' ') ?> €</strong></td>
        <td style="font-family:monospace;font-size:.72rem"><?= htmlspecialchars($d['communication'] ?: '—') ?></td>
        <td>
          <?php if ($d['statut']==='confirme'): ?>
            <span class="badge b-ok">Confirmé</span>
          <?php else: ?>
            <span class="badge b-wait">En attente</span>
          <?php endif; ?>
        </td>
        <td style="font-size:.72rem;color:#aaa"><?= date('d/m/Y', strtotime($d['date_don'])) ?></td>
        <td>
          <?php if ($d['statut'] !== 'confirme'): ?>
          <form method="POST" style="display:inline">
            <input type="hidden" name="don_id" value="<?= $d['id'] ?>">
            <button type="submit" name="confirmer_don" class="btn btn-green" style="padding:4px 8px;font-size:.7rem">Confirmer</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
</body>
</html>
