<?php
// admin/import_csv.php — Import d'un historique bancaire CSV (Belfius) avec rapprochement
// Cascade : don en_attente (ogm_don) > OGM membre > IBAN connu > nom approchant > non attribué
// Workflow en 2 temps : upload+analyse (session) -> revue/confirmation -> écriture.
require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/dons.php';
$db = getDB();
getAnonymousMemberId($db); // garantit l'existence du membre « Donateur anonyme » (visible dans les listes)

$error = '';
$results  = null;   // étape 1 : résultat de l'analyse
$imported = null;   // étape 2 : résultat de l'import
$flash_msg = '';

// Onglet actif et compteur paiements en attente
$current_tab  = $_GET['tab'] ?? 'import';
$pending_count = 0;
try { $pending_count = (int)$db->query("SELECT COUNT(*) FROM import_csv_lignes WHERE statut='en_attente'")->fetchColumn(); } catch (Exception $e) {}

// ── Handlers onglet "En attente" ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    csrf_verify();
    $action = $_POST['action'];

    if ($action === 'reconcilier') {
        $lid = (int)($_POST['ligne_id'] ?? 0);
        $mid = (int)($_POST['member_id'] ?? 0);
        if ($lid > 0 && $mid > 0) {
            try {
                $l = $db->prepare("SELECT * FROM import_csv_lignes WHERE id=? AND statut='en_attente'");
                $l->execute([$lid]); $l = $l->fetch();
                if ($l) {
                    $chk = $db->prepare("SELECT id FROM member_dons WHERE ref_import=?");
                    $chk->execute([$l['ref_import']]);
                    if (!$chk->fetch()) {
                        $ogm_l = extract_ogm(($l['communication']??'').' ');
                        $comm = $ogm_l ?: ($l['communication'] ?: null);
                        $dupId = findDuplicateDon($db, $mid, (float)$l['montant'], $ogm_l ?: null, $l['communication'] ?: null, $l['date_virement'] ?: null);
                        if ($dupId) {
                            // Don déjà présent (confirmé manuellement) : on rattache l'empreinte au lieu de dupliquer.
                            $db->prepare("UPDATE member_dons SET ref_import = COALESCE(NULLIF(ref_import,''), ?), statut='confirme' WHERE id=?")
                               ->execute([$l['ref_import'], $dupId]);
                            require_once __DIR__ . '/../includes/mail_helper.php';
                            sendDonMerci($db, $dupId);
                        } else {
                            $note = 'Réconciliation manuelle — ' . ($l['contrepartie_nom'] ?? '');
                            $db->prepare("INSERT INTO member_dons (member_id,montant,communication,statut,note,date_don,ref_import) VALUES (?,?,?,'confirme',?,?,?)")
                               ->execute([$mid, $l['montant'], $comm, $note,
                                          ($l['date_virement'] ? $l['date_virement'].' 12:00:00' : date('Y-m-d').' 12:00:00'),
                                          $l['ref_import']]);
                            require_once __DIR__ . '/../includes/mail_helper.php';
                            sendDonMerci($db, (int)$db->lastInsertId());
                        }
                    }
                    $db->prepare("UPDATE import_csv_lignes SET statut='reconcilie',date_reconciliee=NOW() WHERE id=?")
                       ->execute([$lid]);
                    $flash_msg = '✓ Paiement réconcilié et don enregistré.';
                }
            } catch (Exception $e) { $flash_msg = 'Erreur : ' . $e->getMessage(); }
        }
        $current_tab = 'attente';

    } elseif ($action === 'ignorer') {
        $ids = array_filter(array_map('intval', (array)($_POST['ligne_ids'] ?? [])));
        if ($ids) {
            $in = implode(',', $ids);
            try { $db->exec("UPDATE import_csv_lignes SET statut='ignore' WHERE id IN ($in)"); } catch (Exception $e) {}
            $flash_msg = '✓ ' . count($ids) . ' paiement(s) ignoré(s) définitivement.';
        }
        $current_tab = 'attente';

    } elseif ($action === 'redetection') {
        try {
            $pending = $db->query("SELECT * FROM import_csv_lignes WHERE statut='en_attente'")->fetchAll();
            $mbrs = $db->query("SELECT id,prenom,nom,ogm,iban_membre FROM members WHERE statut='actif'")->fetchAll();
            $bo=[]; $bi=[];
            foreach ($mbrs as $m) {
                if (!empty($m['ogm']))         $bo[$m['ogm']] = $m;
                if (!empty($m['iban_membre'])) $bi[strtoupper(preg_replace('/\s+/','',$m['iban_membre']))] = $m;
            }
            $upd = 0;
            foreach ($pending as $row) {
                $nt=$row['tier']; $ns=(int)($row['suggested_member_id']??0);
                $ogm_r = extract_ogm(($row['communication']??'').' ');
                $iban_r = strtoupper(preg_replace('/\s+/','',$row['contrepartie_iban']??''));
                if ($ogm_r && isset($bo[$ogm_r]))     { $nt='ogm';  $ns=$bo[$ogm_r]['id']; }
                elseif ($iban_r && isset($bi[$iban_r])) { $nt='iban'; $ns=$bi[$iban_r]['id']; }
                else {
                    $n = nrm_name($row['contrepartie_nom']??'');
                    foreach ($mbrs as $m) {
                        $ln = nrm_name($m['nom']);
                        if ($ln!==''&&strlen($ln)>=3&&preg_match('/\b'.preg_quote($ln,'/').'\b/',$n)) { $nt='nom';$ns=$m['id'];break; }
                    }
                }
                if ($nt!==$row['tier']||(int)$ns!==(int)($row['suggested_member_id']??0)) {
                    $db->prepare("UPDATE import_csv_lignes SET tier=?,suggested_member_id=? WHERE id=?")->execute([$nt,$ns,$row['id']]);
                    $upd++;
                }
            }
            $flash_msg = '✓ Détection relancée — ' . $upd . ' suggestion(s) mise(s) à jour sur ' . count($pending) . ' paiement(s) en attente.';
        } catch (Exception $e) { $flash_msg = 'Erreur : ' . $e->getMessage(); }
        $current_tab = 'attente';
    }
}

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

// Cherche un don existant qui correspond déjà à ce virement (pour éviter les
// doublons quand un don a été confirmé/saisi manuellement, donc sans ref_import).
// Renvoie l'id du don existant, ou null.
function findDuplicateDon(PDO $db, int $mid, float $montant, ?string $ogm, ?string $comm, ?string $date): ?int {
    if ($mid <= 0) return null;
    if (isAnonymousMember($db, $mid)) return null; // dons anonymes : donateurs distincts, jamais des doublons entre eux
    // a) Même communication structurée (OGM) + même montant pour ce membre → quasi-certain
    if (!empty($ogm)) {
        try {
            $q = $db->prepare("SELECT id FROM member_dons
                               WHERE member_id=? AND ABS(montant - ?) < 0.01
                                 AND (ogm_don = ? OR communication LIKE ?) LIMIT 1");
            $q->execute([$mid, $montant, $ogm, '%' . $ogm . '%']);
            if ($id = $q->fetchColumn()) return (int)$id;
        } catch (Throwable $e) {}
    }
    // b) Don déjà saisi à la main (sans empreinte d'import) : même membre, même montant, date proche (±4 j)
    try {
        $d = $date ?: date('Y-m-d');
        $q = $db->prepare("SELECT id FROM member_dons
                           WHERE member_id=? AND ABS(montant - ?) < 0.01
                             AND (ref_import IS NULL OR ref_import = '')
                             AND ABS(DATEDIFF(date_don, ?)) <= 4 LIMIT 1");
        $q->execute([$mid, $montant, $d]);
        if ($id = $q->fetchColumn()) return (int)$id;
    } catch (Throwable $e) {}
    return null;
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
            $db->prepare("UPDATE member_dons
                          SET statut='confirme', date_don=?, ref_import=?,
                              note=CONCAT(COALESCE(note,''), ' | Confirmé par import CSV')
                          WHERE id=? AND statut='en_attente'")
               ->execute([($tx['date'] ?: date('Y-m-d')) . ' 12:00:00', $tx['ref'], (int)$tx['don_id']]);
            $upd++;
            require_once __DIR__ . '/../includes/mail_helper.php';
            sendDonMerci($db, (int)$tx['don_id']);
        } else {
            $mid = (int)($members[$k] ?? 0);
            if ($mid <= 0) continue;
            $comm = $tx['ogm'] ?: ($tx['comm'] ?: null);
            // Anti-doublon : un don correspondant existe-t-il déjà (confirmé/saisi manuellement) ?
            $dupId = findDuplicateDon($db, $mid, (float)$tx['montant'], $tx['ogm'] ?: null, $tx['comm'] ?: null, $tx['date'] ?: null);
            if ($dupId) {
                // On ne duplique pas : on appose l'empreinte d'import sur le don existant et on le confirme.
                $db->prepare("UPDATE member_dons SET ref_import = COALESCE(NULLIF(ref_import,''), ?), statut='confirme' WHERE id=?")
                   ->execute([$tx['ref'], $dupId]);
                require_once __DIR__ . '/../includes/mail_helper.php';
                sendDonMerci($db, $dupId);
                $skip++;
            } else {
                $note = 'Import CSV ' . $fname . ($tx['nom'] ? ' — ' . $tx['nom'] : '');
                $db->prepare("INSERT INTO member_dons
                              (member_id, montant, communication, statut, note, date_don, ref_import)
                              VALUES (?, ?, ?, 'confirme', ?, ?, ?)")
                   ->execute([$mid, $tx['montant'], $comm, $note, ($tx['date'] ?: date('Y-m-d')) . ' 12:00:00', $tx['ref']]);
                $ins++;
                require_once __DIR__ . '/../includes/mail_helper.php';
                sendDonMerci($db, (int)$db->lastInsertId());
            }
        }
        // Si ce don était en staging, le marquer réconcilié
        if (!empty($tx['is_staging'])) {
            try { $db->prepare("UPDATE import_csv_lignes SET statut='reconcilie',date_reconciliee=NOW() WHERE ref_import=?")->execute([$tx['ref']]); } catch (Exception $e) {}
        }
    }

    // Sauvegarder les non-confirmés (non cochés, hors déjà importés et ignorés) en staging
    $saved = 0;
    foreach ($rows as $k => $tx) {
        if (!empty($decisions[$k])) continue;                          // confirmé, déjà traité
        if (in_array($tx['tier'], ['deja','ignore_def'], true)) continue; // skip définitifs
        if (!empty($tx['is_staging'])) continue;                       // déjà en staging, laisser
        try {
            $db->prepare("INSERT IGNORE INTO import_csv_lignes
                          (ref_import,date_virement,montant,contrepartie_iban,contrepartie_nom,
                           communication,description,tier,suggested_member_id,nom_fichier)
                          VALUES (?,?,?,?,?,?,?,?,?,?)")
               ->execute([$tx['ref'], $tx['date']?:null, $tx['montant'], $tx['iban']?:null,
                          $tx['nom']?:null, $tx['comm']?:null, mb_substr($tx['desc'],0,500),
                          $tx['tier'], $tx['suggest']?:null, $fname]);
            $saved++;
        } catch (Exception $e) {}
    }
    unset($_SESSION['csv_import']);
    $imported = ['ins' => $ins, 'upd' => $upd, 'skip' => $skip, 'saved' => $saved];
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
                $dons = $db->query("SELECT d.id, d.member_id, d.ogm_don, d.montant, d.statut, m.prenom, m.nom
                                    FROM member_dons d JOIN members m ON m.id=d.member_id
                                    WHERE d.ogm_don IS NOT NULL AND d.ogm_don<>''
                                    ORDER BY (d.statut='en_attente') ASC")->fetchAll();
                foreach ($dons as $dn) { $by_ogm_don[$dn['ogm_don']] = $dn; }
            } catch (Exception $e) { /* colonne ogm_don absente : on ignore ce niveau */ }

            // Empreintes déjà importées (member_dons) + staging (import_csv_lignes)
            $refs_exist = []; $refs_staging = []; $refs_ignore = [];
            $allrefs = array_values(array_unique(array_column($txs, 'ref')));
            if ($allrefs) {
                $in = implode(',', array_fill(0, count($allrefs), '?'));
                try {
                    $q = $db->prepare("SELECT ref_import FROM member_dons WHERE ref_import IN ($in)");
                    $q->execute($allrefs);
                    foreach ($q->fetchAll() as $r) $refs_exist[$r['ref_import']] = true;
                } catch (Exception $e) {}
                try {
                    $q = $db->prepare("SELECT ref_import, statut, tier, suggested_member_id FROM import_csv_lignes WHERE ref_import IN ($in)");
                    $q->execute($allrefs);
                    foreach ($q->fetchAll() as $r) {
                        if ($r['statut'] === 'en_attente') $refs_staging[$r['ref_import']] = $r;
                        else $refs_ignore[$r['ref_import']] = true; // ignore ou reconcilie
                    }
                } catch (Exception $e) {}
            }

            // Classement
            $rows = []; $counts = ['don'=>0,'ogm'=>0,'iban'=>0,'nom'=>0,'aucun'=>0,'deja'=>0,'attente'=>0]; $total_import = 0;
            foreach ($txs as $tx) {
                $tx['tier'] = 'aucun'; $tx['suggest'] = 0; $tx['don_id'] = 0; $tx['membre_label'] = ''; $tx['is_staging'] = false;
                if (!empty($refs_exist[$tx['ref']])) {
                    $tx['tier'] = 'deja';
                } elseif (!empty($refs_ignore[$tx['ref']])) {
                    continue; // ignoré définitivement ou déjà réconcilié — ne pas afficher
                } elseif (!empty($refs_staging[$tx['ref']])) {
                    $st = $refs_staging[$tx['ref']];
                    $tx['tier'] = 'attente'; $tx['suggest'] = (int)($st['suggested_member_id']??0); $tx['is_staging'] = true;
                } elseif ($tx['ogm'] && isset($by_ogm_don[$tx['ogm']])) {
                    $dn = $by_ogm_don[$tx['ogm']];
                    if (($dn['statut'] ?? '') === 'en_attente') {
                        $tx['tier'] = 'don'; $tx['don_id'] = $dn['id']; $tx['suggest'] = $dn['member_id'];
                        $tx['membre_label'] = trim($dn['prenom'] . ' ' . $dn['nom']);
                    } else {
                        // Don déjà enregistré pour cet OGM (ex. confirmé manuellement) → éviter le doublon
                        $tx['tier'] = 'deja';
                    }
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
                if (isset($counts[$tx['tier']])) $counts[$tx['tier']]++;
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
    .upload-zone{display:block;border:2px dashed #bee3f8;border-radius:10px;padding:32px;text-align:center;background:#f0f7ff;cursor:pointer;transition:border .2s}
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
    .result-stats{display:grid;grid-template-columns:repeat(7,1fr);gap:10px;margin-bottom:20px}
    .stat-box{background:#f8f9fa;border-radius:8px;padding:12px;text-align:center}
    .stat-box .val{font-size:1.5rem;font-weight:800}
    .stat-box .lbl{font-size:0.64rem;color:#888;text-transform:uppercase;letter-spacing:0.04em;margin-top:2px}
    .val-ok{color:#27ae60}.val-warn{color:#FF9900}.val-blue{color:#1673B2}.val-grey{color:#aaa}.val-orange{color:#c97300}
    table{width:100%;border-collapse:collapse;font-size:0.8rem}
    th{text-align:left;padding:8px 10px;color:#888;font-weight:600;font-size:0.68rem;text-transform:uppercase;border-bottom:2px solid #eee;white-space:nowrap}
    td{padding:8px 10px;border-bottom:1px solid #f5f5f5;vertical-align:middle}
    tr.deja{opacity:.5}
    .badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:0.64rem;font-weight:700;white-space:nowrap}
    .b-ok{background:#e8f8f0;color:#27ae60}
    .b-info{background:#e6f1fb;color:#1673B2}
    .b-warn{background:#fff3e0;color:#ba7517}
    .b-grey{background:#f0f0f0;color:#888}
    .b-staging{background:#fff0e0;color:#c97300;border:1px solid #ffd080}
    .csv-tabs{display:flex;gap:4px;margin-bottom:24px;border-bottom:2px solid #e8eef5;padding-bottom:0}
    .csv-tab{padding:9px 18px;border:none;background:none;font-family:inherit;font-size:.85rem;font-weight:700;color:#888;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-2px}
    .csv-tab.active{color:#1673B2;border-bottom-color:#1673B2}
    .csv-tab .badge-cnt{display:inline-block;background:#FF9900;color:#fff;border-radius:10px;padding:1px 7px;font-size:.65rem;margin-left:5px;vertical-align:middle}
    .ogm{font-family:monospace;font-weight:700;color:#1673B2;font-size:0.76rem}
    .mt{font-weight:700;white-space:nowrap}
    select.msel{padding:5px 8px;border:1.5px solid #dde4ed;border-radius:6px;font-size:0.78rem;font-family:inherit;max-width:200px}
    .flash-ok{background:#e8f8f0;color:#276749;padding:14px 16px;border-radius:8px;margin-bottom:20px;font-size:0.88rem;border-left:3px solid #48bb78;line-height:1.6}
    .flash-err{background:#fde8e8;color:#c53030;padding:14px 16px;border-radius:8px;margin-bottom:20px;font-size:0.88rem;border-left:3px solid #fc8181}
    .help{background:#f0f7ff;border:1px solid #bee3f8;border-radius:8px;padding:14px 16px;font-size:0.82rem;color:#2c5282;line-height:1.7;margin-bottom:18px}
    .help strong{color:#0e3d6b}
    .desc-prev{color:#aaa;font-size:0.68rem;max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block}
    .action-bar{display:flex;gap:10px;align-items:center;margin-top:18px;flex-wrap:wrap}
    @media(max-width:768px){
      .main{margin-left:0!important;padding:14px!important;padding-top:68px!important}
      .page-title{font-size:1.1rem;margin-bottom:16px}
      .card{padding:14px}
      /* Stats grid : 7 cols → 3 cols */
      .result-stats{grid-template-columns:repeat(3,1fr)!important;gap:8px}
      .stat-box{padding:10px 8px}
      .stat-box .val{font-size:1.2rem}
      /* Tables : scroll horizontal */
      table{font-size:.72rem;white-space:nowrap}
      th,td{padding:6px 8px!important}
      /* Tabs de navigation : scroll horizontal */
      .csv-tabs{overflow-x:auto;-webkit-overflow-scrolling:touch;flex-wrap:nowrap;padding-bottom:2px}
      .csv-tab{white-space:nowrap;padding:8px 12px;font-size:.78rem}
      /* Zone upload : plus de padding pour le toucher */
      .upload-zone{padding:28px 16px}
      .upload-zone .icon{font-size:2rem}
      /* Boutons pleine largeur */
      .action-bar{flex-direction:column;align-items:stretch}
      .action-bar .btn{width:100%;justify-content:center;padding:12px 18px}
      /* Selects pleine largeur */
      select.msel{max-width:100%!important;width:100%!important;font-size:.82rem;padding:8px 10px}
      /* Tom Select pleine largeur */
      .ts-wrapper{max-width:100%!important}
      /* Cellules avec select : stack vertical */
      td > div[style*="inline-flex"]{flex-direction:column;align-items:stretch!important}
      /* Help box */
      .help{font-size:.78rem;padding:12px}
      /* Titre des étapes */
      .card h3{font-size:.82rem}
      /* Wrappers overflow existants */
      div[style*="overflow-x:auto"]{-webkit-overflow-scrolling:touch}
    }
  </style>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tom-select/2.3.1/css/tom-select.default.min.css">
  <style>
    /* Tom Select — intégration dans le thème admin */
    .ts-wrapper.single .ts-control{border:1.5px solid #dde4ed;border-radius:6px;font-size:.78rem;font-family:inherit;padding:5px 8px;min-height:unset;background:#fff;box-shadow:none}
    .ts-wrapper.single.focus .ts-control{border-color:#1673B2;box-shadow:none}
    .ts-dropdown{font-size:.78rem;font-family:inherit;border:1.5px solid #dde4ed;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,.1)}
    .ts-dropdown .option.selected,.ts-dropdown .option:hover{background:#e6f1fb;color:#0e3d6b}
    .ts-wrapper{max-width:200px}
  </style>
</head>
<body>
<?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

<div class="main">
  <div class="page-title">📥 Import CSV bancaire</div>

  <!-- Onglets -->
  <div class="csv-tabs">
    <button class="csv-tab <?= $current_tab==='import'?'active':'' ?>" onclick="window.location='import_csv.php'">📥 Importer</button>
    <button class="csv-tab <?= $current_tab==='attente'?'active':'' ?>" onclick="window.location='import_csv.php?tab=attente'">
      ⏳ En attente<?php if ($pending_count): ?><span class="badge-cnt"><?= $pending_count ?></span><?php endif; ?>
    </button>
  </div>

  <?php if ($error): ?>
    <div class="flash-err">⚠ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if ($imported): ?>
    <div class="flash-ok">
      ✓ Import terminé : <strong><?= $imported['ins'] ?></strong> nouveau(x) don(s),
      <strong><?= $imported['upd'] ?></strong> don(s) en attente confirmé(s),
      <strong><?= $imported['skip'] ?></strong> déjà présent(s).
      <?php if (!empty($imported['saved']) && $imported['saved'] > 0): ?>
        · <strong><?= $imported['saved'] ?></strong> paiement(s) non réconcilié(s) <a href="import_csv.php?tab=attente">sauvegardé(s) en attente →</a>
      <?php endif; ?>
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
      <div class="stat-box"><div class="val val-orange"><?= $results['counts']['attente'] ?></div><div class="lbl">Déjà en staging</div></div>
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
          <br>💡 Donateur inconnu ? Choisis <strong>« Donateur anonyme »</strong> dans la liste : le don est compté et tu pourras l'<a href="dons_anonymes.php">attribuer plus tard</a>.
        </div>
        <div style="overflow-x:auto">
        <table>
          <tr><th>✓</th><th>Date</th><th>Montant</th><th>Contrepartie</th><th>Communication</th><th>Statut</th><th>Membre</th></tr>
          <?php foreach ($results['rows'] as $k => $tx):
              $tier = $tx['tier'];
              $badge = ['don'=>['b-ok','Don en attente'],'ogm'=>['b-ok','OGM membre'],'iban'=>['b-info','IBAN connu'],
                        'nom'=>['b-warn','Nom approchant'],'aucun'=>['b-grey','Non attribué'],'deja'=>['b-grey','Déjà importé'],
                        'attente'=>['b-staging','⏳ Déjà en staging']][$tier] ?? ['b-grey','?'];
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

  <?php elseif ($current_tab === 'attente'): ?>
    <!-- ONGLET EN ATTENTE -->
    <?php if ($flash_msg): ?><div class="flash-ok"><?= htmlspecialchars($flash_msg) ?></div><?php endif; ?>
    <?php
      $filtre_reconcil = ($_GET['reconcil'] ?? '') === '1';
      try {
        $reconcil_where = $filtre_reconcil ? " AND l.tier IN ('ogm','iban','nom') AND l.suggested_member_id IS NOT NULL" : "";
        $pending = $db->query("SELECT l.*, m.prenom, m.nom as membre_nom
                               FROM import_csv_lignes l LEFT JOIN members m ON m.id=l.suggested_member_id
                               WHERE l.statut='en_attente'$reconcil_where ORDER BY l.date_virement DESC, l.montant DESC")->fetchAll();
        $count_reconcil = (int)$db->query("SELECT COUNT(*) FROM import_csv_lignes WHERE statut='en_attente' AND tier IN ('ogm','iban','nom') AND suggested_member_id IS NOT NULL")->fetchColumn();
        $membres_all = $db->query("SELECT id, prenom, nom FROM members WHERE statut='actif' ORDER BY nom,prenom")->fetchAll();
      } catch (Exception $e) { $pending=[]; $membres_all=[]; $count_reconcil=0; $filtre_reconcil=false; }
    ?>
    <div class="card">
      <h3>⏳ Paiements en attente de réconciliation (<?= count($pending) ?>)</h3>
      <div class="help">
        Ces paiements ont été détectés lors d'un import CSV mais <strong>non réconciliés</strong> avec un membre.
        <br>• <strong>Réconcilier</strong> → sélectionne un membre → le don est enregistré immédiatement.
        <br>• <strong>Ignorer définitivement</strong> → frais bancaires, erreur de virement… → n'apparaît plus jamais.
        <br>• <strong>Relancer la détection</strong> → remet à jour les suggestions (utile après l'inscription d'un nouveau membre).
      </div>
      <?php if (empty($pending)): ?>
        <div style="text-align:center;padding:40px 20px;color:#aaa">
          <div style="font-size:2.5rem;margin-bottom:10px">✅</div>
          <div>Aucun paiement en attente — tout est réconcilié !</div>
        </div>
      <?php else: ?>
        <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;align-items:center">
          <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="redetection">
            <button class="btn btn-g" type="submit">🔄 Relancer la détection automatique</button>
          </form>
          <?php if ($filtre_reconcil): ?>
            <a href="import_csv.php?tab=attente" class="btn btn-g" style="text-decoration:none">
              ✕ Afficher tout (<?= count($pending) + ($count_reconcil - count($pending)) ?> total)
            </a>
          <?php elseif ($count_reconcil > 0): ?>
            <a href="import_csv.php?tab=attente&reconcil=1" class="btn" style="background:#1673B2;color:#fff;text-decoration:none">
              🔗 Afficher seulement les réconciliables (<?= $count_reconcil ?>)
            </a>
          <?php endif; ?>
        </div>
      <style>
      /* ── Cartes paiements en attente ─────────────────────── */
      .pend-search{display:flex;gap:8px;align-items:center;margin-bottom:16px;flex-wrap:wrap}
      .pend-search input{flex:1;min-width:180px;padding:9px 12px;border:1.5px solid #dde4ed;border-radius:8px;font-size:.85rem;font-family:inherit;outline:none}
      .pend-search input:focus{border-color:#1673B2}
      .pend-search .count-lbl{font-size:.75rem;color:#aaa;white-space:nowrap}
      .pend-cards{display:flex;flex-direction:column;gap:12px}
      .pend-card{background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:10px;padding:14px 16px;transition:border .15s}
      .pend-card:hover{border-color:#b5d4f4}
      .pend-card.tier-ogm{border-left:4px solid #27ae60}
      .pend-card.tier-iban{border-left:4px solid #1673B2}
      .pend-card.tier-nom{border-left:4px solid #FF9900}
      .pend-card.tier-aucun{border-left:4px solid #ddd}
      .pend-card-top{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:10px}
      .pend-amount{font-size:1.4rem;font-weight:800;color:#0e3d6b;line-height:1}
      .pend-date{font-size:.72rem;color:#aaa;margin-top:3px}
      .pend-badge-wrap{display:flex;align-items:center;gap:6px;flex-shrink:0}
      .pend-card-info{display:grid;grid-template-columns:auto 1fr;gap:3px 10px;font-size:.78rem;margin-bottom:12px}
      .pend-lbl{color:#aaa;font-weight:600;text-transform:uppercase;font-size:.65rem;letter-spacing:.04em;padding-top:1px}
      .pend-val{color:#333;word-break:break-word}
      .pend-val .ogm{font-family:monospace;font-weight:700;color:#1673B2}
      .pend-suggest{font-size:.72rem;color:#888;margin-top:2px}
      .pend-card-actions{display:flex;gap:8px;align-items:center;padding-top:10px;border-top:1px solid #eee;flex-wrap:wrap}
      .pend-card-actions .ts-wrapper{flex:1;min-width:160px}
      .pend-card-actions .btn-reconcile{padding:10px 16px;font-size:.82rem;white-space:nowrap;flex-shrink:0}
      .pend-card-actions .btn-ignore{padding:8px 12px;font-size:.75rem;color:#c53030;background:#fff5f5;border:1.5px solid #fed7d7;border-radius:6px;cursor:pointer;font-family:inherit;font-weight:600;flex-shrink:0}
      .pend-card-actions .btn-ignore:hover{background:#fee2e2}
      .pend-chk-wrap{display:flex;align-items:center;gap:6px;font-size:.72rem;color:#aaa}
      .no-results-msg{text-align:center;padding:28px;color:#aaa;font-size:.85rem;display:none}
      @media(max-width:600px){
        .pend-card-top{flex-direction:column;gap:6px}
        .pend-card-actions{flex-direction:column;align-items:stretch}
        .pend-card-actions .ts-wrapper,.pend-card-actions .btn-reconcile,.pend-card-actions .btn-ignore{width:100%}
        .pend-card-actions .btn-reconcile{justify-content:center}
      }
      </style>

      <div class="pend-search">
        <input type="search" id="pend-q" placeholder="Rechercher nom, IBAN, OGM, montant…" oninput="filterPending()">
        <label class="pend-chk-wrap"><input type="checkbox" id="chk-all" onchange="document.querySelectorAll('.ligne-chk').forEach(c=>c.checked=this.checked)"> Tout cocher</label>
        <span class="count-lbl" id="pend-count"><?= count($pending) ?> paiement(s)</span>
      </div>
      <div class="no-results-msg" id="pend-noresult">Aucun résultat pour cette recherche.</div>

        <form method="POST" id="bulk-form">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="ignorer">
          <div class="pend-cards" id="pend-cards">
          <?php foreach ($pending as $l):
            $bt = ['ogm'=>['b-ok','OGM','tier-ogm'],'iban'=>['b-info','IBAN','tier-iban'],'nom'=>['b-warn','Nom approchant','tier-nom'],'aucun'=>['b-grey','Non identifié','tier-aucun']][$l['tier']] ?? ['b-grey','?','tier-aucun'];
            $ogm_l = extract_ogm(($l['communication']??'').' ');
            $search_data = strtolower(implode(' ', [
              $l['contrepartie_nom']??'', $l['contrepartie_iban']??'',
              $l['communication']??'', $ogm_l??'',
              number_format((float)$l['montant'],2,',','.'),
              $l['prenom']??'', $l['membre_nom']??''
            ]));
          ?>
          <div class="pend-card <?= $bt[2] ?>" data-search="<?= htmlspecialchars($search_data) ?>">
            <div class="pend-card-top">
              <div>
                <div class="pend-amount">💶 <?= number_format((float)$l['montant'],2,',','.') ?> €</div>
                <div class="pend-date"><?= $l['date_virement'] ? date('d/m/Y', strtotime($l['date_virement'])) : '—' ?> · <?= htmlspecialchars(mb_substr($l['nom_fichier']??'',0,22)) ?></div>
              </div>
              <div class="pend-badge-wrap">
                <span class="badge <?= $bt[0] ?>"><?= $bt[1] ?></span>
                <input type="checkbox" class="ligne-chk" name="ligne_ids[]" value="<?= $l['id'] ?>" title="Sélectionner pour ignorer">
              </div>
            </div>
            <div class="pend-card-info">
              <span class="pend-lbl">De</span>
              <span class="pend-val">
                <?= htmlspecialchars($l['contrepartie_nom']?:'—') ?>
                <?php if ($l['contrepartie_iban']): ?><br><span style="font-size:.68rem;color:#aaa"><?= htmlspecialchars($l['contrepartie_iban']) ?></span><?php endif; ?>
                <?php if ($l['description']): ?><br><span style="font-size:.68rem;color:#bbb"><?= htmlspecialchars(mb_substr($l['description'],0,60)) ?></span><?php endif; ?>
              </span>
              <span class="pend-lbl">Comm.</span>
              <span class="pend-val">
                <?php if ($ogm_l): ?><span class="ogm"><?= htmlspecialchars($ogm_l) ?></span>
                <?php else: ?><?= htmlspecialchars(mb_substr($l['communication']??'',0,50))?:'—' ?><?php endif; ?>
              </span>
              <?php if ($l['membre_nom']): ?>
              <span class="pend-lbl">Suggestion</span>
              <span class="pend-val pend-suggest">→ <?= htmlspecialchars(trim($l['prenom'].' '.$l['membre_nom'])) ?></span>
              <?php endif; ?>
            </div>
            <div class="pend-card-actions">
              <select class="msel" id="mbr-<?= $l['id'] ?>" style="flex:1"><?= member_options($membres_all, (int)$l['suggested_member_id']) ?></select>
              <button class="btn btn-p btn-reconcile" type="button" onclick="reconcilier(<?= $l['id'] ?>, this)">✓ Réconcilier</button>
              <button class="btn-ignore" type="button" onclick="ignorer1(<?= $l['id'] ?>)">✗</button>
            </div>
          </div>
          <?php endforeach; ?>
          </div>

          <div class="action-bar" style="margin-top:16px">
            <button class="btn btn-g" type="submit"
              onclick="return document.querySelectorAll('.ligne-chk:checked').length?confirm('Ignorer définitivement les lignes sélectionnées ?'):(alert('Aucune case cochée.'),false)">
              🚫 Ignorer la sélection
            </button>
          </div>
        </form>
      <?php endif; ?>
    </div>

    <!-- Formulaire caché pour la réconciliation individuelle (pas de forms imbriqués) -->
    <form method="POST" id="reconcile-form" style="display:none">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="reconcilier">
      <input type="hidden" name="ligne_id" id="rec-ligne-id">
      <input type="hidden" name="member_id" id="rec-member-id">
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
          <input type="file" name="csv_file" id="csv_file" accept=".csv,text/csv,text/plain,application/vnd.ms-excel" required>
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/tom-select/2.3.1/js/tom-select.complete.min.js"></script>
<script>
// Recherche dans les selects de membre (Tom Select)
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('select.msel').forEach(function(el) {
    new TomSelect(el, {
      placeholder: '— rechercher un membre —',
      create: false,
      maxOptions: 500,
      render: {
        no_results: function() { return '<div class="no-results">Aucun résultat</div>'; }
      }
    });
  });
});

// Réconciliation individuelle via formulaire caché
function reconcilier(ligneId, btn) {
  var sel = document.getElementById('mbr-' + ligneId);
  var mid = sel ? sel.value : '';
  if (!mid) { alert('Sélectionne un membre avant de valider.'); return; }
  document.getElementById('rec-ligne-id').value = ligneId;
  document.getElementById('rec-member-id').value = mid;
  document.getElementById('reconcile-form').submit();
}

function filterPending() {
  var q = document.getElementById('pend-q').value.toLowerCase().trim();
  var cards = document.querySelectorAll('.pend-card');
  var visible = 0;
  cards.forEach(function(c) {
    var match = !q || c.dataset.search.includes(q);
    c.style.display = match ? '' : 'none';
    if (match) visible++;
  });
  var lbl = document.getElementById('pend-count');
  if (lbl) lbl.textContent = visible + ' paiement(s)' + (q ? ' trouvé(s)' : '');
  var noRes = document.getElementById('pend-noresult');
  if (noRes) noRes.style.display = (visible === 0 && q) ? 'block' : 'none';
}

function ignorer1(ligneId) {
  if (!confirm('Ignorer définitivement ce paiement ?')) return;
  var fd = new FormData();
  fd.append('_csrf', document.querySelector('input[name=_csrf]').value);
  fd.append('action', 'ignorer');
  fd.append('ligne_ids[]', ligneId);
  fetch('import_csv.php?tab=attente', { method:'POST', body:fd })
    .then(function(r){ return r.text(); })
    .then(function(){ location.reload(); })
    .catch(function(e){ alert('Erreur: '+e.message); });
}
</script>
</body>
</html>
