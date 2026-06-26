<?php
// admin/bilan.php — Bilan annuel (matière du rapport d'activité) + suivi des dépenses, par année
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/csrf.php';
session_start(); requireAdmin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_verify();
$db = getDB();

// ── Auto-provisionnement des tables ───────────────────────────────────────
$db->exec("CREATE TABLE IF NOT EXISTS bilan_annuel (
    annee INT UNSIGNED NOT NULL PRIMARY KEY,
    nb_membres INT DEFAULT NULL, nb_abonnes INT DEFAULT NULL, nb_followers INT DEFAULT NULL,
    ag_date VARCHAR(120) DEFAULT NULL,
    ca_composition TEXT, actions_juridiques TEXT, representation TEXT, communication TEXT,
    evenements TEXT, partenariats TEXT, faits_marquants TEXT, perspectives TEXT, notes TEXT,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$db->exec("CREATE TABLE IF NOT EXISTS depenses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    annee INT UNSIGNED NOT NULL,
    categorie VARCHAR(80) NOT NULL,
    libelle VARCHAR(255) DEFAULT NULL,
    montant DECIMAL(10,2) NOT NULL DEFAULT 0,
    date_depense DATE DEFAULT NULL,
    note TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_annee (annee), KEY idx_cat (categorie)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$CATEGORIES = ['Frais juridiques','Avocat','Comptabilité','Frais de fonctionnement','Frais divers'];

$annee = isset($_GET['annee']) ? (int)$_GET['annee'] : (int)date('Y');
if ($annee < 2015 || $annee > 2100) $annee = (int)date('Y');
$msg = $_GET['msg'] ?? '';

// ── Enregistrer le bilan de l'année ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_bilan'])) {
    $a = (int)($_POST['annee'] ?? $annee);
    $fields = [
        'nb_membres'         => ($_POST['nb_membres']   ?? '') === '' ? null : (int)$_POST['nb_membres'],
        'nb_abonnes'         => ($_POST['nb_abonnes']   ?? '') === '' ? null : (int)$_POST['nb_abonnes'],
        'nb_followers'       => ($_POST['nb_followers'] ?? '') === '' ? null : (int)$_POST['nb_followers'],
        'ag_date'            => trim($_POST['ag_date'] ?? ''),
        'ca_composition'     => trim($_POST['ca_composition'] ?? ''),
        'actions_juridiques' => trim($_POST['actions_juridiques'] ?? ''),
        'representation'     => trim($_POST['representation'] ?? ''),
        'communication'      => trim($_POST['communication'] ?? ''),
        'evenements'         => trim($_POST['evenements'] ?? ''),
        'partenariats'       => trim($_POST['partenariats'] ?? ''),
        'faits_marquants'    => trim($_POST['faits_marquants'] ?? ''),
        'perspectives'       => trim($_POST['perspectives'] ?? ''),
        'notes'              => trim($_POST['notes'] ?? ''),
    ];
    $cols    = array_keys($fields);
    $colList = 'annee,' . implode(',', $cols);
    $ph      = implode(',', array_fill(0, count($cols) + 1, '?'));
    $upd     = implode(',', array_map(fn($c) => "$c=VALUES($c)", $cols));
    $db->prepare("INSERT INTO bilan_annuel ($colList) VALUES ($ph) ON DUPLICATE KEY UPDATE $upd")
       ->execute(array_merge([$a], array_values($fields)));
    header('Location: bilan.php?annee=' . $a . '&msg=' . urlencode('Bilan ' . $a . ' enregistré.')); exit;
}

// ── Ajouter une dépense ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_depense'])) {
    $a       = (int)($_POST['annee'] ?? $annee);
    $cat     = trim($_POST['categorie'] ?? '');
    $lib     = trim($_POST['libelle'] ?? '');
    $montant = (float)str_replace([' ', ','], ['', '.'], $_POST['montant'] ?? '0');
    $date    = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['date_depense'] ?? '') ? $_POST['date_depense'] : null;
    if ($cat !== '' && $montant > 0) {
        $db->prepare("INSERT INTO depenses (annee, categorie, libelle, montant, date_depense) VALUES (?,?,?,?,?)")
           ->execute([$a, $cat, $lib, $montant, $date]);
        header('Location: bilan.php?annee=' . $a . '&msg=' . urlencode('Dépense ajoutée.')); exit;
    }
    $msg = '⚠ Catégorie et montant (> 0) requis.';
}

// ── Supprimer une dépense ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['del_depense'])) {
    $db->prepare("DELETE FROM depenses WHERE id=?")->execute([(int)$_POST['del_depense']]);
    header('Location: bilan.php?annee=' . (int)($_POST['annee'] ?? $annee) . '&msg=' . urlencode('Dépense supprimée.')); exit;
}

// ── Données ───────────────────────────────────────────────────────────────
$b = $db->prepare("SELECT * FROM bilan_annuel WHERE annee=?");
$b->execute([$annee]);
$b = $b->fetch(PDO::FETCH_ASSOC) ?: [];

$dep = $db->prepare("SELECT * FROM depenses WHERE annee=? ORDER BY categorie, date_depense, id");
$dep->execute([$annee]);
$depenses = $dep->fetchAll(PDO::FETCH_ASSOC);

$total_general = 0; $totaux_cat = [];
foreach ($depenses as $d) { $total_general += (float)$d['montant']; $totaux_cat[$d['categorie']] = ($totaux_cat[$d['categorie']] ?? 0) + (float)$d['montant']; }

// Années existantes (pour le sélecteur) + valeurs « live » indicatives
$annees = $db->query("SELECT DISTINCT annee FROM (SELECT annee FROM bilan_annuel UNION SELECT annee FROM depenses) t ORDER BY annee DESC")->fetchAll(PDO::FETCH_COLUMN);
$cur = (int)date('Y'); for ($y = $cur; $y >= $cur - 4; $y--) if (!in_array($y, $annees)) $annees[] = $y;
rsort($annees);
$live_membres = (int)$db->query("SELECT COUNT(*) FROM members WHERE statut='actif'")->fetchColumn();
$live_abonnes = (int)$db->query("SELECT COUNT(*) FROM subscribers WHERE statut='actif'")->fetchColumn();

function v($b,$k){ return htmlspecialchars($b[$k] ?? ''); }
function eur($n){ return number_format((float)$n, 2, ',', ' ') . ' €'; }
?>
<!DOCTYPE html><html lang="fr"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Bilan annuel — Admin</title>
<style>
<?php include __DIR__ . '/../includes/admin_sidebar_css.php'; ?>
body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333;margin:0}
.wrap{margin-left:240px;padding:28px;max-width:1040px}
@media(max-width:768px){.wrap{margin-left:0;padding:16px;padding-top:68px}}
h1{font-size:1.3rem;color:#0e3d6b;margin:0 0 4px}
.sub{font-size:.82rem;color:#888;margin-bottom:20px}
.card{background:#fff;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.06);padding:22px;margin-bottom:20px}
.card h2{font-size:1rem;color:#0e3d6b;margin:0 0 14px;padding-bottom:8px;border-bottom:1px solid #e0e8f0}
label{display:block;font-size:.72rem;font-weight:700;color:#555;text-transform:uppercase;letter-spacing:.03em;margin:12px 0 4px}
input[type=text],input[type=number],input[type=date],textarea,select{width:100%;padding:9px 11px;border:1.5px solid #dde4ed;border-radius:7px;font-size:.9rem;font-family:inherit;outline:none;background:#fff}
input:focus,textarea:focus,select:focus{border-color:#1673B2}
textarea{min-height:64px;resize:vertical;line-height:1.5}
.row3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
@media(max-width:680px){.row3,.row2{grid-template-columns:1fr}}
.hint{font-size:.7rem;color:#999;font-weight:400;text-transform:none;letter-spacing:0}
.btn{padding:10px 18px;border:none;border-radius:8px;font-size:.85rem;font-weight:700;cursor:pointer;font-family:inherit}
.btn-p{background:#1673B2;color:#fff}.btn-p:hover{background:#0e3d6b}
.btn-g{background:#f0f4f8;color:#555;border:1.5px solid #dde4ed}
.btn-r{background:#fff5f5;color:#e53e3e;border:1.5px solid #fed7d7;padding:5px 10px;font-size:.75rem;border-radius:6px}
.yearbar{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:18px}
.yearbar a{padding:7px 14px;border-radius:20px;text-decoration:none;font-weight:700;font-size:.85rem;background:#fff;color:#1673B2;border:1.5px solid #dde4ed}
.yearbar a.active{background:#1673B2;color:#fff;border-color:#1673B2}
table{width:100%;border-collapse:collapse;font-size:.86rem;margin-top:8px}
th{background:#0e3d6b;color:#fff;text-align:left;padding:8px 10px;font-size:.74rem}
td{padding:8px 10px;border-bottom:1px solid #eef2f6}
tr:nth-child(even) td{background:#f7fafd}
.flash{background:#e8f8f0;color:#1a5c35;padding:10px 14px;border-radius:8px;margin-bottom:16px;font-size:.85rem;border-left:3px solid #1a7a4a}
.totaux{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}
.tcat{background:#f0f6ff;border:1px solid #d6e6f5;border-radius:8px;padding:8px 12px;font-size:.8rem}
.tcat b{color:#0e3d6b}
.tgen{background:#FF9900;color:#fff;border-radius:8px;padding:10px 16px;font-weight:800;font-size:.95rem}
.dep-form{display:grid;grid-template-columns:1.2fr 2fr 1fr 1fr auto;gap:8px;align-items:end}
@media(max-width:760px){.dep-form{grid-template-columns:1fr 1fr}}
</style></head><body>
<?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>
<div class="wrap">
  <h1>📊 Bilan annuel</h1>
  <div class="sub">Matière du rapport d'activité (par année) + suivi des dépenses — pour les dossiers de subsides.</div>

  <?php if ($msg): ?><div class="flash"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

  <div class="yearbar">
    <span style="font-size:.8rem;color:#888;font-weight:700">Année :</span>
    <?php foreach ($annees as $y): ?>
      <a href="?annee=<?= $y ?>" class="<?= $y == $annee ? 'active' : '' ?>"><?= $y ?></a>
    <?php endforeach; ?>
    <form method="get" style="display:inline-flex;gap:6px;align-items:center">
      <input type="number" name="annee" value="<?= $annee ?>" min="2015" max="2100" style="width:90px;padding:6px 8px">
      <button class="btn btn-g" style="padding:7px 12px">Aller</button>
    </form>
  </div>

  <!-- ── BILAN ── -->
  <form method="post" class="card">
    <?= csrf_field() ?>
    <input type="hidden" name="annee" value="<?= $annee ?>">
    <h2>Rapport d'activité <?= $annee ?></h2>

    <div class="row3">
      <div><label>Membres au 31/12 <span class="hint">(actuel : <?= $live_membres ?>)</span></label>
        <input type="number" name="nb_membres" value="<?= v($b,'nb_membres') ?>"></div>
      <div><label>Abonnés newsletter <span class="hint">(actuel : <?= $live_abonnes ?>)</span></label>
        <input type="number" name="nb_abonnes" value="<?= v($b,'nb_abonnes') ?>"></div>
      <div><label>Followers réseaux sociaux</label>
        <input type="number" name="nb_followers" value="<?= v($b,'nb_followers') ?>"></div>
    </div>

    <label>Assemblée(s) générale(s) — date(s)</label>
    <input type="text" name="ag_date" value="<?= v($b,'ag_date') ?>" placeholder="ex. AG ordinaire le 15/03/<?= $annee ?>">

    <label>Composition du Conseil d'administration</label>
    <textarea name="ca_composition" placeholder="Nom — fonction (président, secrétaire, trésorier…)"><?= v($b,'ca_composition') ?></textarea>

    <label>Actions juridiques (référé, cessation, recours… : objet, juridiction, résultat)</label>
    <textarea name="actions_juridiques"><?= v($b,'actions_juridiques') ?></textarea>

    <label>Représentation & concertation (Skeyes, autorités, communes, associations)</label>
    <textarea name="representation"><?= v($b,'representation') ?></textarea>

    <label>Communication & sensibilisation (site, réseaux, newsletter, presse)</label>
    <textarea name="communication"><?= v($b,'communication') ?></textarea>

    <label>Événements & mobilisation</label>
    <textarea name="evenements"><?= v($b,'evenements') ?></textarea>

    <label>Partenariats</label>
    <textarea name="partenariats"><?= v($b,'partenariats') ?></textarea>

    <label>Faits marquants de l'année</label>
    <textarea name="faits_marquants"><?= v($b,'faits_marquants') ?></textarea>

    <label>Perspectives (année suivante)</label>
    <textarea name="perspectives"><?= v($b,'perspectives') ?></textarea>

    <label>Notes internes</label>
    <textarea name="notes"><?= v($b,'notes') ?></textarea>

    <div style="margin-top:16px"><button class="btn btn-p" name="save_bilan" value="1">💾 Enregistrer le bilan <?= $annee ?></button></div>
  </form>

  <!-- ── DÉPENSES ── -->
  <div class="card">
    <h2>Dépenses <?= $annee ?></h2>

    <form method="post" class="dep-form">
      <?= csrf_field() ?>
      <input type="hidden" name="annee" value="<?= $annee ?>">
      <div><label>Catégorie</label>
        <input type="text" name="categorie" list="cats" placeholder="Catégorie" required>
        <datalist id="cats"><?php foreach ($CATEGORIES as $c): ?><option value="<?= htmlspecialchars($c) ?>"><?php endforeach; ?></datalist>
      </div>
      <div><label>Libellé</label><input type="text" name="libelle" placeholder="ex. Honoraires Me X — référé"></div>
      <div><label>Montant (€)</label><input type="text" name="montant" placeholder="0,00" inputmode="decimal" required></div>
      <div><label>Date</label><input type="date" name="date_depense"></div>
      <div><button class="btn btn-p" name="add_depense" value="1" style="white-space:nowrap">+ Ajouter</button></div>
    </form>

    <?php if ($depenses): ?>
    <table>
      <thead><tr><th>Catégorie</th><th>Libellé</th><th>Date</th><th style="text-align:right">Montant</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($depenses as $d): ?>
        <tr>
          <td><strong><?= htmlspecialchars($d['categorie']) ?></strong></td>
          <td><?= htmlspecialchars($d['libelle'] ?? '') ?></td>
          <td><?= $d['date_depense'] ? date('d/m/Y', strtotime($d['date_depense'])) : '—' ?></td>
          <td style="text-align:right;font-weight:700"><?= eur($d['montant']) ?></td>
          <td style="text-align:right">
            <form method="post" onsubmit="return confirm('Supprimer cette dépense ?')" style="display:inline">
              <?= csrf_field() ?><input type="hidden" name="annee" value="<?= $annee ?>">
              <button class="btn-r" name="del_depense" value="<?= (int)$d['id'] ?>">✕</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="totaux">
      <?php foreach ($totaux_cat as $cat => $tot): ?>
        <div class="tcat"><b><?= htmlspecialchars($cat) ?></b> : <?= eur($tot) ?></div>
      <?php endforeach; ?>
      <div class="tgen">Total <?= $annee ?> : <?= eur($total_general) ?></div>
    </div>
    <?php else: ?>
      <p style="color:#999;font-size:.85rem;margin-top:8px">Aucune dépense enregistrée pour <?= $annee ?>.</p>
    <?php endif; ?>
  </div>

</div>
</body></html>
