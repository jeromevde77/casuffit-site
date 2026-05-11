<?php
// admin/coda.php — Upload et traitement des fichiers CODA bancaires
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/coda_parser.php';
session_start(); requireAdmin();

$db = getDB();

// Créer la table coda_imports si elle n'existe pas
$db->exec("CREATE TABLE IF NOT EXISTS coda_imports (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    filename VARCHAR(255) NOT NULL,
    date_import DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    nb_transactions INT DEFAULT 0,
    nb_matches INT DEFAULT 0,
    nb_inconnus INT DEFAULT 0,
    montant_total DECIMAL(10,2) DEFAULT 0,
    importe_par VARCHAR(100) DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$results = null;
$error   = '';
$success = '';

// ── Traitement de l'upload ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['coda_file'])) {
    $file = $_FILES['coda_file'];

    // Validations
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Erreur lors de l\'upload du fichier.';
    } elseif ($file['size'] > 5 * 1024 * 1024) {
        $error = 'Fichier trop volumineux (max 5 MB).';
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, array('cod', 'coda', 'txt', 'dat', 'csv'))) {
            $error = 'Extension non autorisée. Accepté : .cod, .coda, .txt, .dat, .csv';
        } else {
            $content = file_get_contents($file['tmp_name']);
            if ($content === false) {
                $error = 'Impossible de lire le fichier.';
            } else {
                // Détecter le format et choisir le bon parser
                $format = 'coda';
                if ($ext === 'csv') {
                    $format = 'csv_belfius';
                } elseif (substr(trim($content), 0, 1) !== '0') {
                    // Pas un fichier CODA valide (ne commence pas par record type 0)
                    // Tenter CSV
                    $format = 'csv_belfius';
                }

                if ($format === 'csv_belfius') {
                    $parser = new BelfiusCsvParser();
                } else {
                    $parser = new CodaParser();
                }
                $transactions = $parser->parse($content);
                $credits = array_values($parser->getCredits());

                if (empty($credits)) {
                    $formatLabel = $format === 'csv_belfius' ? 'CSV Belfius' : 'CODA';
                    $error = "Aucune transaction crédit trouvée dans ce fichier $formatLabel. Vérifiez le format.";
                } else {
                    // Matcher avec les membres via OGM
                    $matches   = array();
                    $inconnus  = array();
                    $confirmes = 0;
                    $total_montant = 0;

                    // Charger les IBAN membres pour matching
                    $stmt_ibans = $db->query("SELECT id, iban_membre FROM members WHERE iban_membre IS NOT NULL AND statut='actif'");
                    $iban_map = array(); // IBAN => member_id
                    foreach ($stmt_ibans->fetchAll() as $row) {
                        $iban_clean = strtoupper(preg_replace('/\s+/', '', $row['iban_membre']));
                        if ($iban_clean) $iban_map[$iban_clean] = $row['id'];
                    }

                    foreach ($credits as &$tx) {
                        $total_montant += $tx['amount'];

                        if ($tx['ogm']) {
                            // Chercher le membre par OGM exact
                            $stmt = $db->prepare("SELECT * FROM members WHERE ogm = ? AND statut = 'actif'");
                            $stmt->execute(array($tx['ogm']));
                            $membre = $stmt->fetch();

                            if ($membre) {
                                $tx['matched_member'] = $membre;

                                // Vérifier si ce don n'est pas déjà enregistré (même date + montant + membre)
                                $check = $db->prepare("SELECT id FROM member_dons
                                    WHERE member_id=? AND montant=? AND DATE(date_don)=? AND communication=?");
                                $check->execute(array(
                                    $membre['id'],
                                    $tx['amount'],
                                    $tx['date'],
                                    $tx['ogm']
                                ));
                                $existing = $check->fetch();

                                if (!$existing) {
                                    // Enregistrer et confirmer le don
                                    $db->prepare("INSERT INTO member_dons
                                        (member_id, montant, communication, statut, note, date_don)
                                        VALUES (?, ?, ?, 'confirme', ?, ?)")
                                       ->execute(array(
                                           $membre['id'],
                                           $tx['amount'],
                                           $tx['ogm'],
                                           'Import CODA ' . $file['name'] . ($tx['name'] ? ' — ' . $tx['name'] : ''),
                                           $tx['date'] . ' 12:00:00'
                                       ));
                                    $tx['action'] = 'confirme';
                                    $confirmes++;
                                } else {
                                    $tx['action'] = 'deja_present';
                                }
                                $matches[] = $tx;
                            } else {
                                // OGM non reconnu
                                $tx['action'] = 'ogm_inconnu';
                                $inconnus[] = $tx;
                            }
                        } else {
                            // Pas d'OGM — tenter matching par IBAN contrepartie
                            $contre_iban = strtoupper(preg_replace('/\s+/', '', $tx['counterpart'] ?? ''));
                            if ($contre_iban && isset($iban_map[$contre_iban])) {
                                $tx['action']    = 'match_iban';
                                $tx['member_id'] = $iban_map[$contre_iban];
                                $stmt_m = $db->prepare("SELECT * FROM members WHERE id=?");
                                $stmt_m->execute([$iban_map[$contre_iban]]);
                                $tx['membre'] = $stmt_m->fetch();
                                $matches[] = $tx;
                            } else {
                                $tx['action'] = 'sans_ogm';
                                $inconnus[] = $tx;
                            }
                        }
                    }
                    unset($tx);

                    // Enregistrer l'import
                    $db->prepare("INSERT INTO coda_imports
                        (filename, nb_transactions, nb_matches, nb_inconnus, montant_total, importe_par)
                        VALUES (?,?,?,?,?,?)")
                       ->execute(array(
                           $file['name'],
                           count($credits),
                           count($matches),
                           count($inconnus),
                           $total_montant,
                           ADMIN_USER
                       ));

                    $results = array(
                        'filename'      => $file['name'],
                        'header'        => $parser->getHeader(),
                        'total'         => count($credits),
                        'matches'       => $matches,
                        'inconnus'      => $inconnus,
                        'confirmes'     => $confirmes,
                        'total_montant' => $total_montant,
                    );

                    $success = "✅ Fichier traité : <strong>{$results['confirmes']}</strong> don(s) confirmé(s), <strong>" . count($inconnus) . "</strong> transaction(s) non reconnue(s).";
                }
            }
        }
    }
}

// Historique des imports
$historique = $db->query("SELECT * FROM coda_imports ORDER BY date_import DESC LIMIT 20")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<?php include __DIR__ . '/../includes/admin_pwa_head.php'; ?>
  <title>Import CODA — Admin ça suffit !</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
        <?php include __DIR__ . '/../includes/admin_sidebar_css.php'; ?>
    body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333}
    .main{margin-left:240px;padding:32px;max-width:1100px}
    .page-title{font-size:1.4rem;font-weight:800;color:#0e3d6b;margin-bottom:24px}
    .card{background:#fff;border-radius:12px;padding:22px;box-shadow:0 2px 10px rgba(0,0,0,0.06);margin-bottom:20px}
    .card h3{font-size:0.9rem;font-weight:700;color:#0e3d6b;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid #eee}
    /* Upload zone */
    .upload-zone{border:2px dashed #bee3f8;border-radius:10px;padding:32px;text-align:center;background:#f0f7ff;cursor:pointer;transition:border .2s}
    .upload-zone:hover,.upload-zone.drag{border-color:#1673B2;background:#e6f1fb}
    .upload-zone input{display:none}
    .upload-zone .icon{font-size:2.5rem;margin-bottom:10px}
    .upload-zone p{color:#555;font-size:0.88rem;margin-bottom:6px}
    .upload-zone small{color:#aaa;font-size:0.75rem}
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
    /* Résultats */
    .result-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px}
    .stat-box{background:#f8f9fa;border-radius:8px;padding:14px;text-align:center}
    .stat-box .val{font-size:1.6rem;font-weight:800}
    .stat-box .lbl{font-size:0.7rem;color:#888;text-transform:uppercase;letter-spacing:0.05em;margin-top:2px}
    .val-ok{color:#27ae60}
    .val-warn{color:#FF9900}
    .val-blue{color:#1673B2}
    /* Table */
    table{width:100%;border-collapse:collapse;font-size:0.8rem}
    th{text-align:left;padding:8px 10px;color:#888;font-weight:600;font-size:0.7rem;text-transform:uppercase;border-bottom:2px solid #eee;white-space:nowrap}
    td{padding:8px 10px;border-bottom:1px solid #f5f5f5;vertical-align:middle}
    .badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:0.65rem;font-weight:700}
    .b-ok{background:#e8f8f0;color:#27ae60}
    .b-warn{background:#fff3e0;color:#ba7517}
    .b-info{background:#e6f1fb;color:#1673B2}
    .b-grey{background:#f0f0f0;color:#888}
    .ogm{font-family:monospace;font-weight:700;color:#1673B2;font-size:0.78rem}
    /* Flash msgs */
    .flash-ok{background:#e8f8f0;color:#276749;padding:14px 16px;border-radius:8px;margin-bottom:20px;font-size:0.85rem;border-left:3px solid #48bb78;line-height:1.6}
    .flash-err{background:#fde8e8;color:#c53030;padding:14px 16px;border-radius:8px;margin-bottom:20px;font-size:0.85rem;border-left:3px solid #fc8181}
    /* Explication CODA */
    .info-coda{background:#f0f7ff;border:1px solid #bee3f8;border-radius:8px;padding:14px 16px;margin-bottom:20px;font-size:0.8rem;color:#2c5282;line-height:1.7}
    .info-coda strong{color:#0e3d6b}
  
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
  <div class="page-title">🏦 Import CODA bancaire</div>

  <?php if ($error): ?>
    <div class="flash-err"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="flash-ok"><?= $success ?></div>
  <?php endif; ?>

  <div class="info-coda">
    <strong>Comment ça fonctionne :</strong> Téléchargez votre fichier CODA depuis votre banque en ligne (Belfius Direct Net → Relevés → CODA). Le système lit automatiquement chaque virement, détecte la communication structurée <strong>+++XXX/XXXX/XXXXX+++</strong>, la fait correspondre au code OGM de chaque membre, et confirme le don dans son historique. Les virements sans OGM reconnu sont listés séparément pour traitement manuel.
  </div>

  <!-- Zone upload -->
  <div class="card">
    <h3>📁 Charger un fichier CODA</h3>
    <form method="POST" enctype="multipart/form-data" id="coda-form">
      <div class="upload-zone" id="upload-zone" onclick="document.getElementById('coda-file').click()">
        <input type="file" id="coda-file" name="coda_file" accept=".cod,.coda,.txt,.dat" onchange="updateZone(this)">
        <div class="icon" id="upload-icon">🏦</div>
        <p id="upload-text">Cliquez ou glissez votre fichier CODA ici</p>
        <small>Formats acceptés : .cod, .coda, .txt, .dat — Belfius, BNP, ING, KBC, Argenta...</small>
      </div>
      <button type="submit" class="btn btn-primary" id="btn-submit" style="display:none">
        ▶ Analyser et importer
      </button>
    </form>
  </div>

  <?php if ($results): ?>
  <!-- Résultats de l'import -->
  <div class="card">
    <h3>📊 Résultats — <?= htmlspecialchars($results['filename']) ?></h3>

    <div class="result-stats">
      <div class="stat-box">
        <div class="val val-blue"><?= $results['total'] ?></div>
        <div class="lbl">Transactions analysées</div>
      </div>
      <div class="stat-box">
        <div class="val val-ok"><?= $results['confirmes'] ?></div>
        <div class="lbl">Dons confirmés</div>
      </div>
      <div class="stat-box">
        <div class="val val-warn"><?= count($results['inconnus']) ?></div>
        <div class="lbl">Non reconnus</div>
      </div>
      <div class="stat-box">
        <div class="val val-blue"><?= number_format($results['total_montant'], 2, ',', '.') ?> €</div>
        <div class="lbl">Montant total</div>
      </div>
    </div>

    <?php if (!empty($results['matches'])): ?>
    <h3 style="color:#27ae60;border-bottom-color:#c6f6d5;margin-bottom:12px">✅ Dons reconnus et confirmés (<?= count($results['matches']) ?>)</h3>
    <table style="margin-bottom:20px">
      <tr><th>Date</th><th>Montant</th><th>OGM</th><th>Membre</th><th>Nom (virement)</th><th>Statut</th></tr>
      <?php foreach ($results['matches'] as $tx): ?>
      <tr>
        <td><?= date('d/m/Y', strtotime($tx['date'])) ?></td>
        <td><strong><?= number_format($tx['amount'], 2, ',', '.') ?> €</strong></td>
        <td><span class="ogm"><?= htmlspecialchars($tx['ogm']) ?></span></td>
        <td>
          <?php if ($tx['matched_member']): ?>
          <div style="font-size:0.78rem;font-weight:600"><?= htmlspecialchars($tx['matched_member']['prenom'].' '.$tx['matched_member']['nom']) ?></div>
          <div style="font-size:0.7rem;color:#888"><?= htmlspecialchars($tx['matched_member']['code_membre']) ?></div>
          <?php endif; ?>
        </td>
        <td style="font-size:0.78rem;color:#666"><?= htmlspecialchars($tx['name'] ?: '—') ?></td>
        <td>
          <?php if ($tx['action'] === 'confirme'): ?>
            <span class="badge b-ok">✓ Nouveau</span>
          <?php else: ?>
            <span class="badge b-grey">Déjà présent</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>

    <?php if (!empty($results['inconnus'])): ?>
    <h3 style="color:#ba7517;border-bottom-color:#feebc8;margin-bottom:12px">⚠️ Transactions non reconnues (<?= count($results['inconnus']) ?>)</h3>
    <table>
      <tr><th>Date</th><th>Montant</th><th>Communication détectée</th><th>Nom (virement)</th><th>Raison</th><th>Action</th></tr>
      <?php foreach ($results['inconnus'] as $tx): ?>
      <tr>
        <td><?= date('d/m/Y', strtotime($tx['date'])) ?></td>
        <td><strong><?= number_format($tx['amount'], 2, ',', '.') ?> €</strong></td>
        <td style="font-family:monospace;font-size:0.75rem">
          <?= htmlspecialchars($tx['ogm'] ?: $tx['communication'] ?: '—') ?>
        </td>
        <td style="font-size:0.78rem"><?= htmlspecialchars($tx['name'] ?: '—') ?></td>
        <td>
          <?php if ($tx['action'] === 'sans_ogm'): ?>
            <span class="badge b-grey">Pas d'OGM</span>
          <?php else: ?>
            <span class="badge b-warn">OGM inconnu</span>
          <?php endif; ?>
        </td>
        <td>
          <a href="membres.php" class="btn btn-primary" style="padding:4px 8px;font-size:0.72rem">
            Attribuer manuellement
          </a>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Historique des imports -->
  <?php if (!empty($historique)): ?>
  <div class="card">
    <h3><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> Historique des imports CODA</h3>
    <table>
      <tr><th>Fichier</th><th>Date import</th><th>Transactions</th><th>Reconnus</th><th>Inconnus</th><th>Total</th></tr>
      <?php foreach ($historique as $h): ?>
      <tr>
        <td style="font-family:monospace;font-size:0.78rem"><?= htmlspecialchars($h['filename']) ?></td>
        <td><?= date('d/m/Y H:i', strtotime($h['date_import'])) ?></td>
        <td><?= $h['nb_transactions'] ?></td>
        <td><span class="badge b-ok"><?= $h['nb_matches'] ?></span></td>
        <td>
          <?php if ($h['nb_inconnus'] > 0): ?>
            <span class="badge b-warn"><?= $h['nb_inconnus'] ?></span>
          <?php else: ?>
            <span class="badge b-grey">0</span>
          <?php endif; ?>
        </td>
        <td><strong><?= number_format($h['montant_total'], 2, ',', '.') ?> €</strong></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>

</div>

<script>
// Drag & drop
var zone = document.getElementById('upload-zone');
zone.addEventListener('dragover', function(e) { e.preventDefault(); this.classList.add('drag'); });
zone.addEventListener('dragleave', function() { this.classList.remove('drag'); });
zone.addEventListener('drop', function(e) {
  e.preventDefault(); this.classList.remove('drag');
  var file = e.dataTransfer.files[0];
  if (file) {
    document.getElementById('coda-file').files = e.dataTransfer.files;
    updateZone({ files: [file] });
  }
});

function updateZone(input) {
  var file = input.files[0];
  if (file) {
    document.getElementById('upload-icon').textContent = '📄';
    document.getElementById('upload-text').textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
    document.getElementById('btn-submit').style.display = 'inline-block';
  }
}
</script>
</body>
</html>
