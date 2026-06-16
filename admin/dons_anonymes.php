<?php
// admin/dons_anonymes.php — Dons anonymes : ajout, suivi et attribution à un membre
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../membre/functions.php';
session_start(); requireAdmin();
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/dons.php';
$db = getDB();
$anonId = getAnonymousMemberId($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_verify();

// Attribuer un don anonyme à un vrai membre
if (isset($_POST['attribuer'])) {
    $did = (int)($_POST['don_id'] ?? 0);
    $mid = (int)($_POST['member_id'] ?? 0);
    if ($did > 0 && $mid > 0 && $mid !== $anonId) {
        $upd = $db->prepare("UPDATE member_dons
                             SET member_id = ?, note = CONCAT(COALESCE(note,''), ' | Attribué depuis anonyme')
                             WHERE id = ? AND member_id = ?");
        $upd->execute([$mid, $did, $anonId]);
        if ($upd->rowCount() > 0) {
            require_once __DIR__ . '/../includes/mail_helper.php';
            sendDonMerci($db, $did); // le donateur est désormais connu → remerciement
        }
    }
    header('Location: dons_anonymes.php?msg=attribue'); exit;
}

// Ajouter un don anonyme
if (isset($_POST['ajouter_anon'])) {
    $montant = floatval(str_replace(',', '.', $_POST['montant'] ?? 0));
    $comm    = trim($_POST['communication'] ?? '');
    $date    = ($_POST['date'] ?? '') ?: date('Y-m-d');
    if ($montant > 0) {
        $db->prepare("INSERT INTO member_dons (member_id, montant, communication, statut, date_don, note)
                      VALUES (?, ?, ?, 'confirme', ?, 'Don anonyme')")
           ->execute([$anonId, $montant, $comm ?: null, $date . ' 12:00:00']);
    }
    header('Location: dons_anonymes.php?msg=ajoute'); exit;
}

$dons = $db->prepare("SELECT * FROM member_dons WHERE member_id = ? ORDER BY date_don DESC");
$dons->execute([$anonId]); $dons = $dons->fetchAll();
$total = array_sum(array_column(array_filter($dons, fn($d) => $d['statut'] === 'confirme'), 'montant'));

$membres = $db->query("SELECT id, prenom, nom, code_membre FROM members
                       WHERE statut='actif' AND id <> " . (int)$anonId . " ORDER BY nom, prenom")->fetchAll();
$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<?php include __DIR__ . '/../includes/admin_pwa_head.php'; ?>
  <title>Dons anonymes — Admin Ça suffit !</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tom-select/2.3.1/css/tom-select.default.min.css">
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333}
    <?php include __DIR__ . '/../includes/admin_sidebar_css.php'; ?>
    .main{margin-left:240px;padding:28px;max-width:1000px}
    .page-title{font-size:1.2rem;font-weight:800;color:#0e3d6b;margin-bottom:6px}
    .sub{color:#888;font-size:.82rem;margin-bottom:18px}
    .card{background:#fff;border-radius:12px;padding:18px 20px;box-shadow:0 2px 10px rgba(0,0,0,.06);margin-bottom:16px}
    .card h3{font-size:.88rem;font-weight:700;color:#0e3d6b;margin-bottom:14px;padding-bottom:8px;border-bottom:1px solid #eee}
    .tot{font-size:1.6rem;font-weight:900;color:#FF9900}
    table{width:100%;border-collapse:collapse;font-size:.8rem}
    th{text-align:left;padding:7px 9px;color:#888;font-weight:600;font-size:.65rem;text-transform:uppercase;border-bottom:2px solid #eee}
    td{padding:7px 9px;border-bottom:1px solid #f5f5f5;vertical-align:middle}
    .ogm{font-family:monospace;font-size:.72rem;color:#1673B2}
    .badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:.64rem;font-weight:700}
    .b-ok{background:#e8f8f0;color:#27ae60}.b-wait{background:#fff3e0;color:#ba7517}
    .btn{padding:6px 12px;border-radius:6px;font-size:.76rem;font-weight:700;cursor:pointer;border:1.5px solid transparent;font-family:inherit;display:inline-flex;align-items:center;gap:5px;line-height:1}
    .btn-p{background:#1673B2;color:#fff;border-color:#1673B2}.btn-p:hover{background:#125a90}
    .flash-ok{background:#e8f8f0;color:#276749;padding:11px 14px;border-radius:8px;margin-bottom:14px;font-size:.82rem;border-left:3px solid #48bb78}
    input,select{padding:6px 10px;border:1.5px solid #dde4ed;border-radius:6px;font-size:.8rem;font-family:inherit;outline:none}
    .msel{min-width:200px}
    .empty{text-align:center;padding:30px;color:#aaa}
    .ts-wrapper.single .ts-control{border:1.5px solid #dde4ed;border-radius:6px;font-size:.78rem;font-family:inherit;padding:5px 8px;min-height:unset;background:#fff;box-shadow:none}
    @media(max-width:768px){.main{margin-left:0!important;padding:14px!important;padding-top:68px!important}table{font-size:.72rem}}
  </style>
</head>
<body>
<?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>
<div class="main">
  <div class="page-title">🎭 Dons anonymes</div>
  <div class="sub">Dons reçus sans donateur identifié. Comptés dans les totaux, et attribuables à un membre quand son identité est connue.</div>

  <?php if ($msg==='attribue'): ?><div class="flash-ok">✅ Don attribué au membre (et remerciement envoyé).</div>
  <?php elseif ($msg==='ajoute'): ?><div class="flash-ok">✅ Don anonyme enregistré.</div><?php endif; ?>

  <div class="card">
    <h3>Total des dons anonymes confirmés</h3>
    <div class="tot"><?= number_format($total, 2, ',', ' ') ?> €</div>
    <div style="color:#888;font-size:.8rem;margin-top:4px"><?= count($dons) ?> don(s) anonyme(s) au total</div>
  </div>

  <div class="card">
    <h3>➕ Ajouter un don anonyme</h3>
    <form method="POST" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
      <?= csrf_field() ?>
      <div><label style="font-size:.68rem;color:#888;display:block;margin-bottom:3px">Montant (€)</label>
        <input type="number" name="montant" step="0.01" min="1" placeholder="50" style="width:100px" required></div>
      <div><label style="font-size:.68rem;color:#888;display:block;margin-bottom:3px">Date</label>
        <input type="date" name="date" value="<?= date('Y-m-d') ?>"></div>
      <div><label style="font-size:.68rem;color:#888;display:block;margin-bottom:3px">Communication (optionnel)</label>
        <input type="text" name="communication" placeholder="+++…+++ ou note" style="width:220px"></div>
      <button type="submit" name="ajouter_anon" class="btn btn-p">+ Ajouter</button>
    </form>
  </div>

  <div class="card">
    <h3>Dons anonymes — attribuer à un membre</h3>
    <?php if (empty($dons)): ?>
      <div class="empty">Aucun don anonyme pour le moment.</div>
    <?php else: ?>
    <table>
      <tr><th>Date</th><th>Montant</th><th>Communication</th><th>Statut</th><th>Attribuer à</th></tr>
      <?php foreach ($dons as $d): ?>
      <tr>
        <td><?= $d['date_don'] ? date('d/m/Y', strtotime($d['date_don'])) : '—' ?></td>
        <td><strong><?= number_format($d['montant'], 2, ',', ' ') ?> €</strong></td>
        <td><?php $o = $d['ogm_don'] ?: $d['communication']; ?>
            <?php if ($o): ?><span class="ogm"><?= htmlspecialchars($o) ?></span><?php else: ?><span style="color:#bbb">—</span><?php endif; ?></td>
        <td><?= $d['statut']==='confirme'?'<span class="badge b-ok">Confirmé</span>':'<span class="badge b-wait">En attente</span>' ?></td>
        <td>
          <form method="POST" style="display:flex;gap:6px;align-items:center;margin:0">
            <?= csrf_field() ?>
            <input type="hidden" name="don_id" value="<?= (int)$d['id'] ?>">
            <select class="msel anon-sel" name="member_id" required>
              <option value="">— choisir un membre —</option>
              <?php foreach ($membres as $m): ?>
                <option value="<?= (int)$m['id'] ?>"><?= htmlspecialchars(trim($m['prenom'].' '.$m['nom']).' ('.$m['code_membre'].')') ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" name="attribuer" class="btn btn-p">Attribuer</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/tom-select/2.3.1/js/tom-select.complete.min.js"></script>
<script>
document.querySelectorAll('select.anon-sel').forEach(function(el){
  new TomSelect(el, { placeholder:'— choisir un membre —', create:false, maxOptions:500 });
});
</script>
</body>
</html>
