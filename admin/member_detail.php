<?php
// admin/member_detail.php — Fiche complète d'un membre
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../membre/functions.php';
session_start(); requireAdmin();
require_once __DIR__ . '/../includes/csrf.php';
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: members.php'); exit; }

$back_url = htmlspecialchars($_GET['back'] ?? 'members.php');

$m = $db->prepare("SELECT * FROM members WHERE id=?"); $m->execute([$id]); $m = $m->fetch();
if (!$m) { header('Location: members.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_verify();

// Ajouter un don
if (isset($_POST['ajouter_don'])) {
    $montant = floatval(str_replace(',','.', $_POST['montant'] ?? 0));
    $comm    = htmlspecialchars(trim($_POST['communication'] ?? ''), ENT_QUOTES,'UTF-8');
    $statut  = ($_POST['statut'] ?? '') === 'confirme' ? 'confirme' : 'en_attente';
    if ($montant > 0) {
        $db->prepare("INSERT INTO member_dons (member_id,montant,communication,statut) VALUES (?,?,?,?)")
           ->execute([$id,$montant,$comm,$statut]);
        if ($statut === 'confirme') {
            require_once __DIR__ . '/../includes/mail_helper.php';
            sendDonMerci($db, (int)$db->lastInsertId());
        }
    }
    header("Location: member_detail.php?id=$id&back=".urlencode($_GET['back']??'members.php')."&msg=don_ajoute"); exit;
}

// Confirmer un don
if (isset($_POST['confirmer_don'])) {
    $don_id = (int)$_POST['don_id'];
    $db->prepare("UPDATE member_dons SET statut='confirme' WHERE id=? AND member_id=?")->execute([$don_id,$id]);
    require_once __DIR__ . '/../includes/mail_helper.php';
    sendDonMerci($db, $don_id);
    header("Location: member_detail.php?id=$id&back=".urlencode($_GET['back']??'members.php')."&msg=confirme"); exit;
}

// Modifier le statut du membre
if (isset($_POST['sauver_membre'])) {
    $new_statut = in_array($_POST['statut']??'',['actif','inactif']) ? $_POST['statut'] : $m['statut'];
    $new_nl     = !empty($_POST['newsletter']) ? 1 : 0;
    $new_note   = trim($_POST['note'] ?? '');
    $db->prepare("UPDATE members SET statut=?,newsletter=?,note=? WHERE id=?")
       ->execute([$new_statut,$new_nl,$new_note,$id]);
    header("Location: member_detail.php?id=$id&back=".urlencode($_GET['back']??'members.php')."&msg=maj"); exit;
}

// Charger les dons
$dons = $db->prepare("SELECT * FROM member_dons WHERE member_id=? ORDER BY date_don DESC");
$dons->execute([$id]); $dons=$dons->fetchAll();
$total_confirme = array_sum(array_column(array_filter($dons,fn($d)=>$d['statut']==='confirme'),'montant'));

$msg = $_GET['msg'] ?? '';
$adresse_incomplete = (trim($m['adresse']??'')===''||trim($m['code_postal']??'')==='');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<?php include __DIR__ . '/../includes/admin_pwa_head.php'; ?>
  <title><?=htmlspecialchars($m['prenom'].' '.$m['nom'])?> — Fiche membre</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333}
    <?php include __DIR__ . '/../includes/admin_sidebar_css.php'; ?>
    .main{margin-left:240px;padding:28px;max-width:1100px}
    .back-link{display:inline-flex;align-items:center;gap:6px;color:#888;text-decoration:none;font-size:.8rem;margin-bottom:16px}
    .back-link:hover{color:#1673B2}
    .member-header{background:linear-gradient(135deg,#0e3d6b,#125a90);color:#fff;border-radius:12px;padding:22px 26px;margin-bottom:20px;display:flex;align-items:center;gap:18px}
    .mh-avatar{width:56px;height:56px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0}
    .mh-code{font-family:monospace;font-size:.75rem;color:rgba(255,255,255,.6);margin-bottom:3px}
    .mh-name{font-size:1.3rem;font-weight:800;line-height:1.1}
    .mh-email{font-size:.82rem;color:rgba(255,255,255,.75);margin-top:3px}
    .mh-badges{display:flex;gap:6px;margin-top:8px;flex-wrap:wrap}
    .mbadge{padding:3px 10px;border-radius:12px;font-size:.65rem;font-weight:700}
    .mb-ok{background:#22c55e;color:#fff}.mb-off{background:#ef4444;color:#fff}
    .mb-warn{background:#FF9900;color:#fff}.mb-nl{background:rgba(255,255,255,.2);color:#fff}
    .mh-stats{margin-left:auto;text-align:right;flex-shrink:0}
    .mh-stats .val{font-size:1.6rem;font-weight:900;color:#FF9900}
    .mh-stats .lbl{font-size:.68rem;color:rgba(255,255,255,.6);text-transform:uppercase;letter-spacing:.06em}
    .grid2{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:18px}
    .card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,.06)}
    .card h3{font-size:.88rem;font-weight:700;color:#0e3d6b;margin-bottom:14px;padding-bottom:8px;border-bottom:1px solid #eee}
    .field-row{display:flex;gap:8px;margin-bottom:9px;align-items:baseline}
    .field-lbl{font-size:.68rem;color:#999;text-transform:uppercase;letter-spacing:.05em;min-width:110px;flex-shrink:0}
    .field-val{font-size:.82rem;color:#333}
    .ogm{font-family:monospace;font-weight:700;color:#1673B2;font-size:.78rem}
    .iban-val{font-family:monospace;font-size:.8rem;color:#333}
    table{width:100%;border-collapse:collapse;font-size:.8rem}
    th{text-align:left;padding:7px 10px;color:#888;font-weight:600;font-size:.65rem;text-transform:uppercase;border-bottom:2px solid #eee}
    td{padding:7px 10px;border-bottom:1px solid #f5f5f5;vertical-align:middle}
    .badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:.65rem;font-weight:700}
    .b-ok{background:#e8f8f0;color:#27ae60}.b-wait{background:#fff3e0;color:#ba7517}.b-off{background:#fde8e8;color:#c53030}
    .flash-ok{background:#e8f8f0;color:#276749;padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:.82rem;border-left:3px solid #48bb78}
    .btn{padding:7px 14px;border-radius:6px;font-size:.78rem;font-weight:600;cursor:pointer;border:1.5px solid transparent;font-family:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:5px;line-height:1}
    .btn-p{background:#1673B2;color:#fff;border-color:#1673B2}.btn-p:hover{background:#125a90;color:#fff;text-decoration:none}
    .btn-g{background:#f0f4f8;color:#555;border-color:#dde4ed}.btn-g:hover{background:#e0e8f0;color:#333;text-decoration:none}
    .btn-sm{padding:4px 9px;font-size:.7rem}
    .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .form-group{display:flex;flex-direction:column;gap:4px}
    .form-group label{font-size:.7rem;color:#888}
    .form-group input,.form-group select,.form-group textarea{padding:7px 10px;border:1.5px solid #dde4ed;border-radius:6px;font-size:.8rem;font-family:inherit;outline:none;width:100%}
    .form-group textarea{resize:vertical;min-height:60px}
    .warn-banner{background:#fff8ee;border:1.5px solid #FF9900;border-radius:8px;padding:10px 14px;font-size:.8rem;color:#7a4400;margin-bottom:14px}
    @media(max-width:768px){.main{margin-left:0!important;padding:16px!important;padding-top:68px!important}.grid2{grid-template-columns:1fr!important}.form-grid{grid-template-columns:1fr!important}.mh-stats{display:none}}
  </style>
</head>
<body>
<?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>
<div class="main">
  <a href="<?= $back_url ?>" class="back-link">← Retour à la liste</a>

  <?php if ($msg==='don_ajoute'): ?><div class="flash-ok">Don ajouté.</div>
  <?php elseif ($msg==='confirme'): ?><div class="flash-ok">Don confirmé.</div>
  <?php elseif ($msg==='maj'): ?><div class="flash-ok">Fiche mise à jour.</div><?php endif; ?>

  <?php if ($adresse_incomplete): ?>
  <div class="warn-banner">⚠ <strong>Adresse incomplète</strong> — ce membre n'a pas encore complété son adresse postale.</div>
  <?php endif; ?>

  <!-- En-tête membre -->
  <div class="member-header">
    <div class="mh-avatar">👤</div>
    <div>
      <div class="mh-code"><?= htmlspecialchars($m['code_membre']) ?></div>
      <div class="mh-name"><?= htmlspecialchars($m['prenom'].' '.$m['nom']) ?></div>
      <div class="mh-email"><?= htmlspecialchars($m['email']) ?></div>
      <div class="mh-badges">
        <span class="mbadge <?= $m['statut']==='actif'?'mb-ok':'mb-off' ?>"><?= htmlspecialchars($m['statut']) ?></span>
        <?php if ($adresse_incomplete): ?><span class="mbadge mb-warn">📍 Adresse incomplète</span><?php endif; ?>
        <?php if ($m['newsletter']): ?><span class="mbadge mb-nl">📧 Newsletter</span><?php endif; ?>
        <?php if (!empty($m['password_hash'])): ?><span class="mbadge mb-nl">🔑 Mot de passe</span><?php endif; ?>
      </div>
    </div>
    <div class="mh-stats">
      <div class="val"><?= number_format($total_confirme,0,',',' ') ?> €</div>
      <div class="lbl">Total dons confirmés</div>
      <div style="font-size:.75rem;color:rgba(255,255,255,.6);margin-top:4px"><?= count($dons) ?> versement(s)</div>
    </div>
  </div>

  <div class="grid2">
    <!-- Infos personnelles -->
    <div class="card">
      <h3>📋 Informations personnelles</h3>
      <div class="field-row"><span class="field-lbl">Prénom Nom</span><span class="field-val"><?= htmlspecialchars($m['prenom'].' '.$m['nom']) ?></span></div>
      <div class="field-row"><span class="field-lbl">Email</span><span class="field-val"><?= htmlspecialchars($m['email']) ?></span></div>
      <?php if (!empty($m['telephone'])): ?>
      <div class="field-row"><span class="field-lbl">Téléphone</span><span class="field-val"><?= htmlspecialchars($m['telephone']) ?></span></div>
      <?php endif; ?>
      <div class="field-row"><span class="field-lbl">Adresse</span><span class="field-val"><?= htmlspecialchars($m['adresse'] ?: '—') ?></span></div>
      <div class="field-row"><span class="field-lbl">CP / Commune</span><span class="field-val"><?= htmlspecialchars(($m['code_postal']??'').' '.($m['commune']??'')) ?: '—' ?></span></div>
      <div class="field-row"><span class="field-lbl">Langue</span><span class="field-val"><?= strtoupper($m['lang']??'fr') ?></span></div>
      <div class="field-row"><span class="field-lbl">Inscrit le</span><span class="field-val"><?= date('d/m/Y à H:i', strtotime($m['date_inscription'])) ?></span></div>
      <?php if (!empty($m['derniere_connexion'])): ?>
      <div class="field-row"><span class="field-lbl">Dernière cnx</span><span class="field-val"><?= date('d/m/Y à H:i', strtotime($m['derniere_connexion'])) ?></span></div>
      <?php endif; ?>
    </div>

    <!-- Données financières -->
    <div class="card">
      <h3>💳 Données de paiement</h3>
      <div class="field-row"><span class="field-lbl">OGM membre</span><span class="field-val ogm"><?= htmlspecialchars($m['ogm'] ?: '—') ?></span></div>
      <div class="field-row"><span class="field-lbl">IBAN</span><span class="field-val iban-val"><?= htmlspecialchars($m['iban_membre'] ?: '—') ?></span></div>
      <div class="field-row"><span class="field-lbl">Newsletter</span><span class="field-val"><?= $m['newsletter']?'✅ Oui':'✗ Non' ?></span></div>
      <div class="field-row"><span class="field-lbl">Source</span><span class="field-val"><?= htmlspecialchars($m['source']??'direct') ?></span></div>
      <?php if (!empty($m['note'])): ?>
      <div class="field-row"><span class="field-lbl">Note</span><span class="field-val" style="white-space:pre-wrap"><?= htmlspecialchars($m['note']) ?></span></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Modifier la fiche -->
  <div class="card" style="margin-bottom:18px">
    <h3>✏️ Modifier</h3>
    <form method="POST">
      <?= csrf_field() ?>
      <div class="form-grid">
        <div class="form-group"><label>Statut</label>
          <select name="statut">
            <option value="actif"   <?= $m['statut']==='actif'  ?'selected':''?>>Actif</option>
            <option value="inactif" <?= $m['statut']==='inactif'?'selected':''?>>Inactif</option>
          </select>
        </div>
        <div class="form-group"><label>Newsletter</label>
          <select name="newsletter">
            <option value="1" <?= $m['newsletter']?'selected':''?>>Abonné</option>
            <option value="0" <?= !$m['newsletter']?'selected':''?>>Non abonné</option>
          </select>
        </div>
        <div class="form-group" style="grid-column:1/-1"><label>Note interne (admin uniquement)</label>
          <textarea name="note"><?= htmlspecialchars($m['note']??'') ?></textarea>
        </div>
      </div>
      <div style="margin-top:12px">
        <button type="submit" name="sauver_membre" class="btn btn-p">💾 Enregistrer</button>
      </div>
    </form>
  </div>

  <!-- Historique dons -->
  <div class="card">
    <h3 style="display:flex;justify-content:space-between;align-items:center">
      <span>💶 Historique des dons (<?= count($dons) ?>)</span>
      <span style="font-size:.9rem;color:#FF9900;font-weight:800"><?= number_format($total_confirme,2,',',' ') ?> € confirmés</span>
    </h3>
    <?php if (empty($dons)): ?>
      <div style="text-align:center;padding:24px;color:#aaa">Aucun don enregistré.</div>
    <?php else: ?>
    <table>
      <tr><th>Date</th><th>Montant</th><th>Communication</th><th>Statut</th><th>Source / Note</th><th>Action</th></tr>
      <?php foreach ($dons as $d): ?>
      <tr>
        <td><?= $d['date_don'] ? date('d/m/Y',strtotime($d['date_don'])) : '—' ?></td>
        <td><strong><?= number_format($d['montant'],2,',',' ') ?> €</strong></td>
        <td style="font-family:monospace;font-size:.72rem"><?= htmlspecialchars($d['communication']?:'—') ?></td>
        <td><?= $d['statut']==='confirme'?'<span class="badge b-ok">Confirmé</span>':'<span class="badge b-wait">En attente</span>' ?></td>
        <td style="font-size:.72rem;color:#888"><?= htmlspecialchars(mb_substr($d['note']??'',0,60)) ?></td>
        <td><?php if ($d['statut']!=='confirme'): ?>
          <form method="POST" style="display:inline"><?= csrf_field() ?>
            <input type="hidden" name="don_id" value="<?= $d['id'] ?>">
            <button type="submit" name="confirmer_don" class="btn btn-p btn-sm">Confirmer</button>
          </form>
        <?php endif; ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>

    <!-- Ajouter un don -->
    <div style="margin-top:18px;padding-top:14px;border-top:1px solid #eee">
      <div style="font-size:.8rem;font-weight:700;color:#0e3d6b;margin-bottom:10px">Enregistrer un don</div>
      <form method="POST" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
        <?= csrf_field() ?>
        <div><label style="font-size:.68rem;color:#888;display:block;margin-bottom:3px">Montant (€)</label>
          <input type="number" name="montant" step="0.01" min="1" placeholder="50" style="width:90px" required></div>
        <div><label style="font-size:.68rem;color:#888;display:block;margin-bottom:3px">Communication</label>
          <input type="text" name="communication" placeholder="+++000/0000/00000+++" style="width:200px"></div>
        <div><label style="font-size:.68rem;color:#888;display:block;margin-bottom:3px">Statut</label>
          <select name="statut" style="padding:6px 10px;border:1.5px solid #dde4ed;border-radius:6px;font-size:.8rem;font-family:inherit">
            <option value="confirme">Confirmé</option>
            <option value="en_attente">En attente</option>
          </select></div>
        <button type="submit" name="ajouter_don" class="btn btn-p">+ Ajouter</button>
      </form>
    </div>
  </div>
</div>
</body>
</html>
