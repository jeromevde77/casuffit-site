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
    require_once __DIR__ . '/../includes/mail_helper.php';
    sendDonMerci($db, $id);
    header('Location: dons_all.php?msg=confirme'); exit;
}

// Annuler un don
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['annuler'])) {
    $id = intval($_POST['don_id']);
    $db->prepare("UPDATE member_dons SET statut='annule' WHERE id=?")->execute(array($id));
    header('Location: dons_all.php?msg=annule'); exit;
}

// Modifier un don (montant, date, communication, statut, réaffectation membre)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier_don'])) {
    $id      = (int)($_POST['don_id'] ?? 0);
    $montant = floatval(str_replace(',', '.', $_POST['montant'] ?? 0));
    $date    = trim($_POST['date_don'] ?? '');
    $comm    = trim($_POST['communication'] ?? '');
    $statut  = in_array($_POST['statut'] ?? '', ['confirme','en_attente','annule'], true) ? $_POST['statut'] : 'en_attente';
    $mid     = (int)($_POST['member_id'] ?? 0);
    if ($id > 0 && $montant > 0 && $mid > 0) {
        $dt = $date ? date('Y-m-d H:i:s', strtotime($date)) : date('Y-m-d H:i:s');
        $db->prepare("UPDATE member_dons SET member_id=?, montant=?, communication=?, date_don=?, statut=? WHERE id=?")
           ->execute([$mid, $montant, ($comm !== '' ? $comm : null), $dt, $statut, $id]);
    }
    header('Location: dons_all.php?msg=modifie'); exit;
}

// Supprimer un don
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supprimer_don'])) {
    $id = (int)($_POST['don_id'] ?? 0);
    if ($id > 0) $db->prepare("DELETE FROM member_dons WHERE id=?")->execute([$id]);
    header('Location: dons_all.php?msg=supprime'); exit;
}

// ── Rappel promesses de don ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rappel_dons'])) {
    require_once __DIR__ . '/../includes/mail_helper.php';
    $ids     = array_map('intval', (array)($_POST['don_ids'] ?? []));
    $iban    = cfg('iban', 'BE41 0689 0149 6910');
    $bic     = cfg('bic', 'GKCCBEBB');
    $benef   = cfg('beneficiaire', 'Ça suffit !');
    $envoyes = 0; $erreurs = 0;
    foreach ($ids as $did) {
        if (!$did) continue;
        $stmt = $db->prepare("SELECT d.*, m.prenom, m.nom, m.email FROM member_dons d JOIN members m ON m.id=d.member_id WHERE d.id=? AND d.statut='en_attente' LIMIT 1");
        $stmt->execute([$did]); $d = $stmt->fetch();
        if (!$d || !$d['email']) continue;
        $montant  = number_format($d['montant'], 2, ',', ' ').' €';
        $date_don = date('d/m/Y', strtotime($d['date_don']));
        $comm     = $d['ogm_don'] ?: ($d['communication'] ?: '—');
        $prenom   = $d['prenom'];
        $html = "
<p>Bonjour ".htmlspecialchars($prenom).",</p>

<p>Vous avez promis un don de <strong>".htmlspecialchars($montant)."</strong> à notre association <em>Ça suffit !</em> le ".htmlspecialchars($date_don).".</p>

<p>Nous revenons vers vous pour savoir quelle suite vous souhaitez donner à cette promesse.</p>

<div style='background:#f0f7ff;border-left:4px solid #1673B2;padding:14px 18px;border-radius:0 8px 8px 0;margin:18px 0'>
  <p style='font-weight:700;color:#0e3d6b;margin-bottom:8px'>✅ Si vous souhaitez honorer votre promesse :</p>
  <p style='margin:0;font-size:.9rem;line-height:1.8'>
    Effectuez simplement un virement bancaire :<br>
    <strong>IBAN :</strong> ".htmlspecialchars($iban)."<br>
    <strong>BIC :</strong> ".htmlspecialchars($bic)."<br>
    <strong>Bénéficiaire :</strong> ".htmlspecialchars($benef)."<br>
    <strong>Communication :</strong> <code>".htmlspecialchars($comm)."</code><br>
    <strong>Montant :</strong> ".htmlspecialchars($montant)."
  </p>
</div>

<div style='background:#f9f9f9;border-left:4px solid #aaa;padding:14px 18px;border-radius:0 8px 8px 0;margin:18px 0'>
  <p style='font-weight:700;color:#555;margin-bottom:6px'>❌ Si vous ne pouvez pas honorer votre promesse :</p>
  <p style='margin:0;font-size:.9rem;color:#666;line-height:1.6'>Pas de problème ! Deux options :<br>
  • Connectez-vous à votre <a href='https://www.casuffit.be/membre/dashboard.php' style='color:#1673B2'>espace membre</a> et supprimez la promesse de don directement depuis votre tableau de bord.<br>
  • Ou contactez-nous simplement par email à <a href='mailto:info@casuffit.be' style='color:#1673B2'>info@casuffit.be</a>.</p>
</div>

<p>Merci pour votre engagement et votre soutien à notre cause !</p>
<p><em>L'équipe Ça suffit !<br><a href='https://www.casuffit.be' style='color:#1673B2'>casuffit.be</a></em></p>";
        $text = "Bonjour $prenom,\n\nVous avez promis un don de $montant le $date_don.\n\nSi vous souhaitez honorer votre promesse :\nIBAN : $iban | BIC : $bic | Bénéficiaire : $benef | Communication : $comm | Montant : $montant\n\nSi vous ne pouvez pas honorer votre promesse :\n- Connectez-vous à votre espace membre : https://www.casuffit.be/membre/dashboard.php et supprimez la promesse de don depuis votre tableau de bord.\n- Ou contactez-nous : info@casuffit.be\n\nMerci,\nL'équipe Ça suffit !";
        if (sendMail($d['email'], $prenom.' '.$d['nom'], 'Votre promesse de don — Ça suffit !', $html, $text))
            $envoyes++;
        else $erreurs++;
    }
    $msg_rappel = "rappel_ok:{$envoyes}:{$erreurs}";
    header("Location: dons_all.php?statut=en_attente&msg=".urlencode($msg_rappel)); exit;
}

$msg = isset($_GET['msg']) ? $_GET['msg'] : '';
// Parser le résultat de rappel
$msg_rappel_ok = '';
if (strpos($msg, 'rappel_ok:') === 0) {
    [$_x, $env, $err] = explode(':', $msg);
    $msg_rappel_ok = "✅ $env rappel(s) envoyé(s)".($err>0?" · ⚠ $err échec(s)":"").".";
    $msg = '';
}

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

// Membres actifs (pour la réaffectation d'un don dans le modal)
$membres_sel = $db->query("SELECT id, prenom, nom, code_membre FROM members WHERE statut='actif' ORDER BY nom, prenom")->fetchAll();

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
#edit-modal label{font-size:.7rem;color:#888;display:block;margin-bottom:3px}
#edit-modal input,#edit-modal select{padding:7px 10px;border:1.5px solid #dde4ed;border-radius:6px;font-size:.82rem;font-family:inherit;outline:none;width:100%}
.ts-wrapper.single .ts-control{border:1.5px solid #dde4ed;border-radius:6px;font-size:.82rem;min-height:unset;padding:5px 8px;box-shadow:none}
.ts-dropdown{font-size:.8rem}
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tom-select/2.3.1/css/tom-select.default.min.css">
</head>
<body>
<?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

<div class="main">
  <div class="page-title">Tous les dons</div>

  <?php if ($msg === 'confirme'): ?>
    <div class="flash-ok">Don confirmé.</div>
  <?php elseif ($msg === 'annule'): ?>
    <div class="flash-ok">Don annulé.</div>
  <?php elseif ($msg === 'modifie'): ?>
    <div class="flash-ok">✏️ Don modifié.</div>
  <?php elseif ($msg === 'supprime'): ?>
    <div class="flash-ok">🗑 Don supprimé.</div>
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

      <?php if ($msg_rappel_ok): ?>
        <div style="background:#e8f8f0;border:1px solid #27ae60;border-radius:8px;padding:12px 16px;margin-bottom:14px;font-size:.9rem;color:#1a6e3c"><?= htmlspecialchars($msg_rappel_ok) ?></div>
      <?php endif; ?>

      <?php if (empty($dons)): ?>
        <p style="color:#aaa;text-align:center;padding:20px;font-size:.85rem">Aucun don trouvé.</p>
      <?php else: ?>

      <?php if ($filtre_statut === 'en_attente' && !empty($dons)): ?>
      <form method="POST" id="form-rappel"><?= csrf_field() ?>
      <input type="hidden" name="rappel_dons" value="1">
      <div style="background:#fff8e6;border:1px solid #ffc107;border-radius:10px;padding:12px 16px;margin-bottom:14px;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
        <label style="display:flex;align-items:center;gap:6px;font-size:.85rem;cursor:pointer;font-weight:600">
          <input type="checkbox" id="cb-all" style="width:16px;height:16px" onclick="document.querySelectorAll('.cb-don').forEach(c=>c.checked=this.checked)">
          Tout sélectionner
        </label>
        <button type="submit" id="btn-rappel" onclick="return confirmRappel()" class="btn btn-primary" style="background:#FF9900;border-color:#FF9900">
          📧 Envoyer un rappel aux sélectionnés
        </button>
        <span id="rappel-count" style="font-size:.82rem;color:#888"></span>
      </div>
      <?php endif; ?>

      <table>
        <tr>
          <?php if ($filtre_statut === 'en_attente'): ?><th style="width:32px"></th><?php endif; ?>
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
          <?php if ($filtre_statut === 'en_attente'): ?>
          <td style="width:32px;text-align:center"><input type="checkbox" name="don_ids[]" value="<?= $d['id'] ?>" class="cb-don" style="width:16px;height:16px;cursor:pointer"></td>
          <?php endif; ?>
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
            <div style="display:flex;gap:5px;align-items:center;flex-wrap:wrap">
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
              <button type="button" class="act-btn edit" title="Modifier"
                onclick='openEditDon(<?= htmlspecialchars(json_encode([
                  "id"      => (int)$d["id"],
                  "montant" => number_format((float)$d["montant"], 2, ".", ""),
                  "date"    => date("Y-m-d", strtotime($d["date_don"])),
                  "comm"    => $d["communication"] ?? "",
                  "statut"  => $d["statut"],
                  "mid"     => (int)$d["member_id"],
                ]), ENT_QUOTES) ?>)'>✏️</button>
              <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer définitivement ce don ?')"><?= csrf_field() ?>
                <input type="hidden" name="don_id" value="<?= $d['id'] ?>">
                <button type="submit" name="supprimer_don" class="act-btn del" title="Supprimer">🗑</button>
              </form>
            </div>
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
      <?php if ($filtre_statut === 'en_attente' && !empty($dons)): ?></form><?php endif; ?>
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
<script>
// Rappel promesses : compteur sélectionnés
document.addEventListener('change', function(e) {
  if (!e.target.classList.contains('cb-don') && e.target.id !== 'cb-all') return;
  var n = document.querySelectorAll('.cb-don:checked').length;
  var span = document.getElementById('rappel-count');
  if (span) span.textContent = n > 0 ? n + ' sélectionné(s)' : '';
});
function confirmRappel() {
  var n = document.querySelectorAll('.cb-don:checked').length;
  if (n === 0) { alert('Sélectionnez au moins un don.'); return false; }
  return confirm('Envoyer un email de rappel à ' + n + ' personne(s) ?');
}
</script>

<!-- Modale édition d'un don -->
<div id="edit-modal" onclick="if(event.target===this)closeEditDon()"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9999;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:12px;width:480px;max-width:95vw;box-shadow:0 8px 40px rgba(0,0,0,.25)">
    <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-bottom:1px solid #eee">
      <div style="font-weight:700;color:#0e3d6b">✏️ Modifier le don</div>
      <button type="button" onclick="closeEditDon()" style="border:none;background:none;font-size:1.5rem;cursor:pointer;color:#bbb;line-height:1">×</button>
    </div>
    <form method="POST" style="padding:18px 20px;display:flex;flex-direction:column;gap:12px">
      <?= csrf_field() ?>
      <input type="hidden" name="don_id" id="ed-id">
      <div>
        <label>Membre (réaffectation)</label>
        <select name="member_id" id="ed-member" required>
          <?php foreach ($membres_sel as $m): ?>
            <option value="<?= (int)$m['id'] ?>"><?= htmlspecialchars(trim($m['prenom'].' '.$m['nom']).' ('.$m['code_membre'].')') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:flex;gap:10px">
        <div style="flex:1"><label>Montant (€)</label><input type="number" step="0.01" min="0.01" name="montant" id="ed-montant" required></div>
        <div style="flex:1"><label>Date</label><input type="date" name="date_don" id="ed-date"></div>
      </div>
      <div><label>Communication</label><input type="text" name="communication" id="ed-comm" placeholder="OGM ou note"></div>
      <div><label>Statut</label>
        <select name="statut" id="ed-statut">
          <option value="confirme">Confirmé</option>
          <option value="en_attente">En attente</option>
          <option value="annule">Annulé</option>
        </select>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:4px">
        <button type="button" class="btn btn-g" onclick="closeEditDon()">Annuler</button>
        <button type="submit" name="modifier_don" class="btn btn-p">💾 Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/tom-select/2.3.1/js/tom-select.complete.min.js"></script>
<script>
var edTom = null;
document.addEventListener('DOMContentLoaded', function(){
  if (window.TomSelect) edTom = new TomSelect('#ed-member', { create:false, maxOptions:2000 });
});
function openEditDon(d){
  document.getElementById('ed-id').value = d.id;
  document.getElementById('ed-montant').value = d.montant;
  document.getElementById('ed-date').value = d.date;
  document.getElementById('ed-comm').value = d.comm || '';
  document.getElementById('ed-statut').value = d.statut;
  if (edTom) edTom.setValue(String(d.mid)); else document.getElementById('ed-member').value = d.mid;
  document.getElementById('edit-modal').style.display = 'flex';
}
function closeEditDon(){ document.getElementById('edit-modal').style.display = 'none'; }
</script>
</body>
</html>
