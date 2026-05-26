<?php
// admin/import_csv.php — Import d'un historique bancaire CSV (Belfius) avec rapprochement
// Cascade : don en_attente (ogm_don) > OGM membre > IBAN connu > nom approchant > non attribué
// Workflow en 2 temps : upload+analyse (session) -> revue/confirmation -> écriture.
require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();
require_once __DIR__ . '/../includes/csrf.php';
$db = getDB();

$error = '';
$results  = null;   // étape 1 : résultat de l'analyse
$imported = null;   // étape 2 : résultat de l'import

// ── Helpers ───────────────────────────────────────────────────────────────
function nrm_name($s) {
    $s = (string)$s;
    $s = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
    return trim(preg_replace('/\s+/', ' ', $s));
}
function parse_montant($s) {
    $s = trim((string)$s);
    $neg = (strpos($s, '-') !== false);
    $s = preg_replace('/[^0-9,\.]/', '', $s);   // garde chiffres, virgule, point
    $s = str_replace('.', '', $s);              // séparateur de milliers
    $s = str_replace(',', '.', $s);             // virgule décimale -> point
    if ($s === '' || !is_numeric($s)) return 0.0;
    return ($neg ? -1 : 1) * (float)$s;
}
function extract_ogm($s) {
    if (preg_match('/\+{3}\d{3}\/\d{4}\/\d{5}\+{3}/', (string)$s, $m)) return $m[0];
    return '';
}

// Parse le CSV Belfius -> [transactions crédit | null, message d'erreur]
function parse_belfius_csv($path) {
    $raw = @file_get_contents($path);
    if ($raw === false) return [null, "Lecture du fichier impossible."];
    if (!mb_check_encoding($raw, 'UTF-8')) {
        $raw = mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-1');
    }
    $raw   = str_replace(["\r\n", "\r"], "\n", $raw);
    $lines = explode("\n", $raw);

    // Localiser l'en-tête du tableau de transactions
    $headerIdx = -1; $cols = [];
    foreach ($lines as $i => $l) {
        if (strpos($l, 'Date de comptabilisation') !== false
            && strpos($l, 'Montant') !== false
            && strpos($l, 'Communications') !== false) {
            $cols = str_getcsv($l, ';');
            $headerIdx = $i; break;
        }
    }
    if ($headerIdx < 0) return [null, "En-tête du tableau introuvable. Format Belfius (séparateur « ; ») attendu."];

    $idx = [];
    foreach ($cols as $j => $c) { $idx[trim($c)] = $j; }
    $need = ['Date de comptabilisation', 'Compte contrepartie', 'Nom contrepartie contient', 'Transaction', 'Montant', 'Communications'];
    foreach ($need as $n) {
        if (!isset($idx[$n])) return [null, "Colonne « $n » introuvable dans le fichier."];
    }

    $txs = [];
    $nbcols = count($cols);
    for ($i = $headerIdx + 1; $i < count($lines); $i++) {
        if (trim($lines[$i]) === '') continue;
        $f = str_getcsv($lines[$i], ';');
        if (count($f) < $nbcols) continue;          // ligne incomplète

        $montant = parse_montant($f[$idx['Montant']]);
        if ($montant <= 0) continue;                // crédits uniquement (dons entrants)

        $dateRaw = trim($f[$idx['Date de comptabilisation']]);
        $date = '';
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})#', $dateRaw, $d)) $date = "$d[3]-$d[2]-$d[1]";

        $comm = trim($f[$idx['Communications']]);
        $desc = trim($f[$idx['Transaction']]);
        $iban = strtoupper(preg_replace('/\s+/', '', $f[$idx['Compte contrepartie']]));
        $nom  = trim($f[$idx['Nom contrepartie contient']]);
        $ogm  = extract_ogm($comm . ' ' . $desc);
        $ref  = md5($date . '|' . number_format($montant, 2, '.', '') . '|' . $iban . '|' . $comm . '|' . $desc);

        $txs[] = compact('date', 'montant', 'comm', 'desc', 'iban', 'nom', 'ogm', 'ref');
    }
    return [$txs, ''];
}

// ── ÉTAPE 2 : confirmation / écriture ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmer_import'])) {
    csrf_verify();
    $rows      = $_SESSION['csv_import']['rows']     ?? [];
    $fname     = $_SESSION['csv_import']['filename']  ?? 'import.csv';
    $decisions = $_POST['import'] ?? [];
    $members   = $_POST['member'] ?? [];
    $ins = 0; $upd = 0; $skip = 0;

    foreach ($rows as $k => $tx) {
        if (empty($decisions[$k])) continue;

        // Sécurité : ne jamais ré-écrire une ligne déjà importée (empreinte)
        $chk = $db->prepare("SELECT id FROM member_dons WHERE ref_import = ?");
        $chk->execute([$tx['ref']]);
        if ($chk->fetch()) { $skip++; continue; }

        if ($tx['tier'] === 'don' && !empty($tx['don_id'])) {
            // Confirme un don en_attente existant (paiement d'un QR généré)
            $db->prepare("UPDATE member_dons
                          SET statut='confirme', date_don=?, ref_import=?,
                              note=CONCAT(COALESCE(note,''), ' | Confirmé par import CSV')
                          WHERE id=? AND statut='en_attente'")
               ->execute([($tx['date'] ?: date('Y-m-d')) . ' 12:00:00', $tx['ref'], (int)$tx['don_id']]);
            $upd++;
        } else {
            // Nouveau don rattaché au membre choisi
            $mid = (int)($members[$k] ?? 0);
            if ($mid <= 0) continue;
            $comm = $tx['ogm'] ?: ($tx['comm'] ?: null);
            $note = 'Import CSV ' . $fname . ($tx['nom'] ? ' — ' . $tx['nom'] : '');
            $db->prepare("INSERT INTO member_dons
                          (member_id, montant, communication, statut, note, date_don, ref_import)
                          VALUES (?, ?, ?, 'confirme', ?, ?, ?)")
               ->execute([$mid, $tx['montant'], $comm, $note, ($tx['date'] ?: date('Y-m-d')) . ' 12:00:00', $tx['ref']]);
            $ins++;
        }
    }
    unset($_SESSION['csv_import']);
    $imported = ['ins' => $ins, 'upd' => $upd, 'skip' => $skip];
}

// ── ÉTAPE 1 : upload + analyse ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    csrf_verify();
    $file = $_FILES['csv_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "Échec de l'upload (code " . (int)$file['error'] . ").";
    } elseif ($file['size'] > 5 * 1024 * 1024) {
        $error = "Fichier trop volumineux (max 5 Mo).";
    } else {
        list($txs, $perr) = parse_belfius_csv($file['tmp_name']);
        if ($perr !== '') {
            $error = $perr;
        } elseif (empty($txs)) {
            $error = "Aucun versement entrant (crédit) détecté dans ce fichier.";
        } else {
            // Membres actifs + dons en_attente avec OGM par don
            $membres = $db->query("SELECT id, prenom, nom, ogm, iban_membre FROM members WHERE statut='actif' ORDER BY nom, prenom")->fetchAll();
            $by_ogm = []; $by_iban = [];
            foreach ($membres as $m) {
                if (!empty($m['ogm']))         $by_ogm[$m['ogm']] = $m;
                if (!empty($m['iban_membre'])) $by_iban[strtoupper(preg_replace('/\s+/', '', $m['iban_membre']))] = $m;
            }
            $by_ogm_don = [];
            try {
                $dons = $db->query("SELECT d.id, d.member_id, d.ogm_don, d.montant, m.prenom, m.nom
                                    FROM member_dons d JOIN members m ON m.id=d.member_id
                                    WHERE d.statut='en_attente' AND d.ogm_don IS NOT NULL AND d.ogm_don<>''")->fetchAll();
                foreach ($dons as $dn) { $by_ogm_don[$dn['ogm_don']] = $dn; }
            } catch (Exception $e) { /* colonne ogm_don absente : on ignore ce niveau */ }

            // Empreintes déjà importées
            $refs_exist = [];
            $allrefs = array_values(array_unique(array_column($txs, 'ref')));
            if ($allrefs) {
                $in = implode(',', array_fill(0, count($allrefs), '?'));
                try {
                    $q = $db->prepare("SELECT ref_import FROM member_dons WHERE ref_import IN ($in)");
                    $q->execute($allrefs);
                    foreach ($q->fetchAll() as $r) $refs_exist[$r['ref_import']] = true;
                } catch (Exception $e) { /* colonne ref_import absente (migration non lancée) */ }
            }

            // Classement
            $rows = []; $counts = ['don'=>0,'ogm'=>0,'iban'=>0,'nom'=>0,'aucun'=>0,'deja'=>0]; $total_import = 0;
            foreach ($txs as $tx) {
                $tx['tier'] = 'aucun'; $tx['suggest'] = 0; $tx['don_id'] = 0; $tx['membre_label'] = '';
                if (!empty($refs_exist[$tx['ref']])) {
                    $tx['tier'] = 'deja';
                } elseif ($tx['ogm'] && isset($by_ogm_don[$tx['ogm']])) {
                    $dn = $by_ogm_don[$tx['ogm']];
                    $tx['tier'] = 'don'; $tx['don_id'] = $dn['id']; $tx['suggest'] = $dn['member_id'];
                    $tx['membre_label'] = trim($dn['prenom'] . ' ' . $dn['nom']);
                } elseif ($tx['ogm'] && isset($by_ogm[$tx['ogm']])) {
                    $m = $by_ogm[$tx['ogm']];
                    $tx['tier'] = 'ogm'; $tx['suggest'] = $m['id'];
                    $tx['membre_label'] = trim($m['prenom'] . ' ' . $m['nom']);
                } elseif ($tx['iban'] && isset($by_iban[$tx['iban']])) {
                    $m = $by_iban[$tx['iban']];
                    $tx['tier'] = 'iban'; $tx['suggest'] = $m['id'];
                    $tx['membre_label'] = trim($m['prenom'] . ' ' . $m['nom']);
                } else {
                    $n = nrm_name($tx['nom']);
                    if ($n !== '') {
                        foreach ($membres as $m) {
                            $ln = nrm_name($m['nom']);
                            if ($ln !== '' && strlen($ln) >= 3 && preg_match('/\b' . preg_quote($ln, '/') . '\b/', $n)) {
                                $tx['tier'] = 'nom'; $tx['suggest'] = $m['id'];
                                break;
                            }
                        }
                    }
                }
                $counts[$tx['tier']]++;
                if ($tx['tier'] !== 'deja') $total_import += $tx['montant'];
                $rows[] = $tx;
            }

            $_SESSION['csv_import'] = ['filename' => $file['name'], 'rows' => $rows];
            $results = ['rows' => $rows, 'membres' => $membres, 'counts' => $counts,
                        'total' => $total_import, 'filename' => $file['name']];
        }
    }
}

// Options du <select> membre
function member_options($membres, $selected) {
    $out = '<option value="">— choisir un membre —</option>';
    foreach ($membres as $m) {
        $sel = ((int)$selected === (int)$m['id']) ? ' selected' : '';
        $lbl = trim($m['prenom'] . ' ' . $m['nom']);
        $out .= '<option value="' . (int)$m['id'] . '"' . $sel . '>' . htmlspecialchars($lbl) . '</option>';
    }
    return $out;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<?php include __DIR__ . '/../includes/admin_pwa_head.php'; ?>
  <title>Import CSV bancaire — Admin Ça suffit !</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
        <?php include __DIR__ . '/../includes/admin_sidebar_css.php'; ?>
    body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333}
    .main{margin-left:240px;padding:32px;max-width:1150px}
    .page-title{font-size:1.4rem;font-weight:800;color:#0e3d6b;margin-bottom:24px}
    .card{background:#fff;border-radius:12px;padding:22px;box-shadow:0 2px 10px rgba(0,0,0,0.06);margin-bottom:20px}
    .card h3{font-size:0.9rem;font-weight:700;color:#0e3d6b;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid #eee}
    .upload-zone{border:2px dashed #bee3f8;border-radius:10px;padding:32px;text-align:center;background:#f0f7ff;cursor:pointer;transition:border .2s}
    .upload-zone:hover,.upload-zone.drag{border-color:#1673B2;background:#e6f1fb}
    .upload-zone input{display:none}
    .upload-zone .icon{font-size:2.5rem;margin-bottom:10px}
    .upload-zone p{color:#555;font-size:0.88rem;margin-bottom:6px}
    .upload-zone small{color:#aaa;font-size:0.75rem}
    .btn{padding:9px 18px;border-radius:7px;font-size:.82rem;font-weight:700;cursor:pointer;border:1.5px solid transparent;font-family:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:6px;line-height:1}
    .btn-p{background:#1673B2;color:#fff;border-color:#1673B2}
    .btn-p:hover{background:#125a90}
    .btn-g{background:#f0f4f8;color:#555;border-color:#dde4ed}
    .btn-g:hover{background:#e0e8f0}
    .result-stats{display:grid;grid-template-columns:repeat(6,1fr);gap:10px;margin-bottom:20px}
    .stat-box{background:#f8f9fa;border-radius:8px;padding:12px;text-align:center}
    .stat-box .val{font-size:1.5rem;font-weight:800}
    .stat-box .lbl{font-size:0.64rem;color:#888;text-transform:uppercase;letter-spacing:0.04em;margin-top:2px}
    .val-ok{color:#27ae60}.val-warn{color:#FF9900}.val-blue{color:#1673B2}.val-grey{color:#aaa}
    table{width:100%;border-collapse:collapse;font-size:0.8rem}
    th{text-align:left;padding:8px 10px;color:#888;font-weight:600;font-size:0.68rem;text-transform:uppercase;border-bottom:2px solid #eee;white-space:nowrap}
    td{padding:8px 10px;border-bottom:1px solid #f5f5f5;vertical-align:middle}
    tr.deja{opacity:.5}
    .badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:0.64rem;font-weight:700;white-space:nowrap}
    .b-ok{background:#e8f8f0;color:#27ae60}
    .b-info{background:#e6f1fb;color:#1673B2}
    .b-warn{background:#fff3e0;color:#ba7517}
    .b-grey{background:#f0f0f0;color:#888}
    .ogm{font-family:monospace;font-weight:700;color:#1673B2;font-size:0.76rem}
    .mt{font-weight:700;white-space:nowrap}
    select.msel{padding:5px 8px;border:1.5px solid #dde4ed;border-radius:6px;font-size:0.78rem;font-family:inherit;max-width:200px}
    .flash-ok{background:#e8f8f0;color:#276749;padding:14px 16px;border-radius:8px;margin-bottom:20px;font-size:0.88rem;border-left:3px solid #48bb78;line-height:1.6}
    .flash-err{background:#fde8e8;color:#c53030;padding:14px 16px;border-radius:8px;margin-bottom:20px;font-size:0.88rem;border-left:3px solid #fc8181}
    .help{background:#f0f7ff;border:1px solid #bee3f8;border-radius:8px;padding:14px 16px;font-size:0.82rem;color:#2c5282;line-height:1.7;margin-bottom:18px}
    .help strong{color:#0e3d6b}
    .desc-prev{color:#aaa;font-size:0.68rem;max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block}
    .action-bar{display:flex;gap:10px;align-items:center;margin-top:18px;flex-wrap:wrap}
  </style>
</head>
<body>
<?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

<div class="main">
  <div class="page-title">📥 Import CSV bancaire</div>

  <?php if ($error): ?>
    <div class="flash-err">⚠ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if ($imported): ?>
    <div class="flash-ok">
      ✓ Import terminé : <strong><?= $imported['ins'] ?></strong> nouveau(x) don(s),
      <strong><?= $imported['upd'] ?></strong> don(s) en attente confirmé(s),
      <strong><?= $imported['skip'] ?></strong> ignoré(s) (déjà présents).
    </div>
    <div class="card"><a href="import_csv.php" class="btn btn-p">↻ Nouvel import</a>
      <a href="dons_all.php" class="btn btn-g">Voir les dons</a></div>

  <?php elseif ($results): ?>
    <!-- ÉTAPE 2 : revue -->
    <div class="result-stats">
      <div class="stat-box"><div class="val val-ok"><?= $results['counts']['don'] ?></div><div class="lbl">Dons en attente</div></div>
      <div class="stat-box"><div class="val val-ok"><?= $results['counts']['ogm'] ?></div><div class="lbl">OGM membre</div></div>
      <div class="stat-box"><div class="val val-blue"><?= $results['counts']['iban'] ?></div><div class="lbl">IBAN connu</div></div>
      <div class="stat-box"><div class="val val-warn"><?= $results['counts']['nom'] ?></div><div class="lbl">Nom approchant</div></div>
      <div class="stat-box"><div class="val val-grey"><?= $results['counts']['aucun'] ?></div><div class="lbl">Non attribué</div></div>
      <div class="stat-box"><div class="val val-grey"><?= $results['counts']['deja'] ?></div><div class="lbl">Déjà importés</div></div>
    </div>

    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="confirmer_import" value="1">
      <div class="card">
        <h3>Revue — fichier « <?= htmlspecialchars($results['filename']) ?> »</h3>
        <div class="help">
          Coche les lignes à enregistrer. <strong>OGM</strong> et <strong>dons en attente</strong> sont fiables (pré-cochés).
          Pour <strong>IBAN</strong>, <strong>nom approchant</strong> et <strong>non attribué</strong>, vérifie/choisis le membre avant de cocher.
          Les lignes « déjà importées » sont grisées et ignorées.
        </div>
        <div style="overflow-x:auto">
        <table>
          <tr><th>✓</th><th>Date</th><th>Montant</th><th>Contrepartie</th><th>Communication</th><th>Statut</th><th>Membre</th></tr>
          <?php foreach ($results['rows'] as $k => $tx):
              $tier = $tx['tier'];
              $badge = ['don'=>['b-ok','Don en attente'],'ogm'=>['b-ok','OGM membre'],'iban'=>['b-info','IBAN connu'],
                        'nom'=>['b-warn','Nom approchant'],'aucun'=>['b-grey','Non attribué'],'deja'=>['b-grey','Déjà importé']][$tier];
              $precheck = in_array($tier, ['don','ogm','iban'], true);
              $fixed = in_array($tier, ['don','ogm'], true);   // membre certain
          ?>
          <tr class="<?= $tier==='deja'?'deja':'' ?>">
            <td>
              <?php if ($tier !== 'deja'): ?>
                <input type="checkbox" name="import[<?= $k ?>]" value="1" <?= $precheck?'checked':'' ?>>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($tx['date'] ? date('d/m/Y', strtotime($tx['date'])) : '—') ?></td>
            <td class="mt"><?= number_format($tx['montant'], 2, ',', '.') ?> €</td>
            <td><?= htmlspecialchars($tx['nom'] ?: '—') ?>
                <span class="desc-prev"><?= htmlspecialchars(mb_substr($tx['desc'], 0, 60)) ?></span></td>
            <td><?php if ($tx['ogm']): ?><span class="ogm"><?= htmlspecialchars($tx['ogm']) ?></span>
                <?php else: ?><span style="color:#aaa"><?= htmlspecialchars(mb_substr($tx['comm'], 0, 30)) ?: '—' ?></span><?php endif; ?></td>
            <td><span class="badge <?= $badge[0] ?>"><?= $badge[1] ?></span></td>
            <td>
              <?php if ($tier === 'deja'): ?>
                —
              <?php elseif ($fixed): ?>
                <?= htmlspecialchars($tx['membre_label']) ?>
                <input type="hidden" name="member[<?= $k ?>]" value="<?= (int)$tx['suggest'] ?>">
              <?php else: ?>
                <select class="msel" name="member[<?= $k ?>]"><?= member_options($results['membres'], $tx['suggest']) ?></select>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </table>
        </div>
        <div class="action-bar">
          <button type="submit" class="btn btn-p">✓ Enregistrer les dons cochés</button>
          <a href="import_csv.php" class="btn btn-g">Annuler</a>
        </div>
      </div>
    </form>

  <?php else: ?>
    <!-- ÉTAPE 1 : upload -->
    <div class="card">
      <h3>Importer un historique bancaire (CSV)</h3>
      <div class="help">
        <strong>Comment faire :</strong> dans Belfius Direct Net, exporte l'historique du compte au format <strong>CSV</strong>
        (gratuit). Dépose le fichier ici. Le système lit chaque versement entrant et tente de le rattacher à un membre dans cet ordre :
        <br>1. <strong>Don en attente</strong> (communication d'un QR généré) → confirme le don existant.
        <br>2. <strong>OGM membre</strong> (communication structurée du membre) → nouveau don.
        <br>3. <strong>IBAN connu</strong> (compte enregistré par un membre) → proposé, à confirmer.
        <br>4. <strong>Nom approchant</strong> → proposé, choix manuel à confirmer.
        <br>5. <strong>Non attribué</strong> → à imputer manuellement si tu reconnais le donateur.
        <br>Rien n'est enregistré sans ta confirmation à l'écran suivant. Ré-importer le même fichier ne crée pas de doublon.
      </div>
      <form method="POST" enctype="multipart/form-data" id="upform">
        <?= csrf_field() ?>
        <label class="upload-zone" id="dropzone">
          <div class="icon">📄</div>
          <p><strong>Clique ou dépose</strong> ton fichier CSV Belfius ici</p>
          <small>Format CSV (séparateur « ; »), max 5 Mo</small>
          <input type="file" name="csv_file" id="csv_file" accept=".csv,text/csv" required>
        </label>
        <div class="action-bar">
          <button type="submit" class="btn btn-p">Analyser le fichier</button>
        </div>
      </form>
    </div>
  <?php endif; ?>
</div>

<script>
(function(){
  var input = document.getElementById('csv_file');
  var zone  = document.getElementById('dropzone');
  if (input && zone) {
    input.addEventListener('change', function(){
      if (input.files.length) zone.querySelector('p').innerHTML = '<strong>' + input.files[0].name + '</strong> sélectionné';
    });
    ['dragover','dragenter'].forEach(function(e){ zone.addEventListener(e, function(ev){ ev.preventDefault(); zone.classList.add('drag'); }); });
    ['dragleave','drop'].forEach(function(e){ zone.addEventListener(e, function(ev){ ev.preventDefault(); zone.classList.remove('drag'); }); });
    zone.addEventListener('drop', function(ev){ if (ev.dataTransfer.files.length){ input.files = ev.dataTransfer.files; input.dispatchEvent(new Event('change')); } });
  }
})();
</script>
</body>
</html>
