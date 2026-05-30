<?php
// admin/import_wix.php — Import contacts Wix avec nettoyage préalable
error_reporting(0);
ini_set('display_errors', 0);
@set_time_limit(300);
@ini_set('memory_limit', '256M');
require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();
require_once __DIR__ . '/../includes/csrf.php';
$db = getDB();

$msg = ''; $error = ''; $import_result = null;
$preview_data = null; // données nettoyées prêtes à importer

// ── Fonction de nettoyage d'une valeur ───────────────────────────────────
function cleanVal($val) {
    // Supprimer retours à la ligne
    $val = str_replace(array("\r\n", "\r", "\n"), ' ', $val);
    // Supprimer chars de contrôle sauf tab
    $val = preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', '', $val);
    return trim($val);
}

// ── Fonction de parsing CSV robuste ──────────────────────────────────────
function parseCSV($content) {
    // Supprimer BOM
    if (substr($content, 0, 3) === "\xEF\xBB\xBF") $content = substr($content, 3);
    // Normaliser fins de ligne
    $content = str_replace("\r\n", "\n", $content);
    $content = str_replace("\r", "\n", $content);
    // Écrire dans fichier temp
    $tmp = sys_get_temp_dir() . '/wix_' . uniqid() . '.csv';
    file_put_contents($tmp, $content);
    $handle = fopen($tmp, 'r');
    $rows = array();
    while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
        if (!empty(array_filter($row, 'strlen'))) $rows[] = $row;
    }
    fclose($handle);
    unlink($tmp);
    return $rows;
}

// ── Mapping colonnes ──────────────────────────────────────────────────────
function mapColumns($header) {
    $map = array(
        'email'          => array('e-mail','e-mail 1','email 1','email'),
        'prenom'         => array('prénom','prenom','first name'),
        'nom'            => array('nom de famille','nom','last name'),
        'telephone'      => array('téléphone','téléphone 1','telephone','phone'),
        'adresse'        => array('adresse (rue, n°, cp, commune)','adresse 1 - rue','adresse 1','address'),
        'commune'        => array('city','ville','commune'),
        'benevole'       => array("je deviens membre de l'asbl","bénévole"),
        'soutien_action' => array("je  demane à l'asbl de protger mes intérêts en allant devant la justice","soutien action"),
        'notes'          => array('renseignements supplémentaires','notes'),
        'date_naissance' => array('date de naissance','birth date'),
        'date_wix'       => array("date et heure de l'envoi",'created date'),
    );
    $col = array();
    foreach ($map as $field => $aliases) {
        foreach ($aliases as $alias) {
            $idx = array_search($alias, $header);
            if ($idx !== false) { $col[$field] = $idx; break; }
        }
    }
    return $col;
}

// ── Parser l'adresse ─────────────────────────────────────────────────────
function parseAdresse($raw) {
    $raw = cleanVal($raw);
    $adresse = $raw; $commune = '';
    if (preg_match('/^(.+?),?\s*(\d{4}),?\s+(.+)$/i', $raw, $m)) {
        $adresse = trim($m[1]);
        $commune = ucfirst(strtolower(trim($m[3])));
    } elseif (preg_match('/^(.+?)(\d{4})\s+(.+)$/i', $raw, $m)) {
        $adresse = trim($m[1]);
        $commune = ucfirst(strtolower(trim($m[3])));
    }
    return array($adresse, $commune);
}

// ── ÉTAPE 1 : Prévisualiser et nettoyer ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_verify();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'preview') {
    if (empty($_FILES['csv']['tmp_name'])) { $error = 'Aucun fichier.'; }
    else {
        $content = file_get_contents($_FILES['csv']['tmp_name']);
        $encoding = mb_detect_encoding($content, array('UTF-8','ISO-8859-1','Windows-1252'), true);
        if ($encoding && $encoding !== 'UTF-8') $content = mb_convert_encoding($content, 'UTF-8', $encoding);

        $rows = parseCSV($content);
        if (empty($rows)) { $error = 'Fichier vide.'; }
        else {
            $header_raw = array_shift($rows);
            $header = array_map('strtolower', array_map('trim', $header_raw));
            $col = mapColumns($header);

            if (!isset($col['email'])) { $error = 'Colonne email non trouvée. Colonnes: '.implode(', ', $header); }
            else {
                $contacts = array();
                $warnings = array();
                $emails_vus = array();

                foreach ($rows as $i => $row) {
                    $line = $i + 2;
                    $w = array();

                    // Email
                    $email_raw = isset($col['email']) && isset($row[$col['email']]) ? cleanVal($row[$col['email']]) : '';
                    $email = filter_var($email_raw, FILTER_VALIDATE_EMAIL);
                    if (!$email) { $warnings[] = array('ligne'=>$line,'type'=>'error','msg'=>"Email invalide: '$email_raw'"); continue; }
                    if (isset($emails_vus[$email])) { $warnings[] = array('ligne'=>$line,'type'=>'doublon_csv','msg'=>"Doublon dans le CSV: $email"); continue; }
                    $emails_vus[$email] = true;

                    // Téléphone — enlever le ' initial (format Excel)
                    $tel_raw = isset($col['telephone']) && isset($row[$col['telephone']]) ? cleanVal($row[$col['telephone']]) : '';
                    $tel = ltrim($tel_raw, "'");
                    // Convertir +32 en 0
                    if (preg_match('/^\+32\s*(\d.*)$/', $tel, $m)) $tel = '0' . str_replace(' ','',$m[1]);
                    if (preg_match('/^0032\s*(\d.*)$/', $tel, $m)) $tel = '0' . str_replace(' ','',$m[1]);

                    // Adresse
                    $adresse_raw = isset($col['adresse']) && isset($row[$col['adresse']]) ? cleanVal($row[$col['adresse']]) : '';
                    list($adresse, $commune) = parseAdresse($adresse_raw);
                    // Commune directe si dispo
                    if (empty($commune) && isset($col['commune']) && isset($row[$col['commune']])) {
                        $commune = ucfirst(strtolower(cleanVal($row[$col['commune']])));
                    }

                    // Bénévole / soutien
                    $benv_raw = isset($col['benevole']) && isset($row[$col['benevole']]) ? strtolower(cleanVal($row[$col['benevole']])) : '';
                    $benevole = in_array($benv_raw, array('oui','yes','true','1')) ? 1 : 0;
                    $sout_raw = isset($col['soutien_action']) && isset($row[$col['soutien_action']]) ? strtolower(cleanVal($row[$col['soutien_action']])) : '';
                    $soutien  = in_array($sout_raw, array('oui','yes','true','1')) ? 1 : 0;

                    // Notes — nettoyer retours à la ligne
                    $notes = isset($col['notes']) && isset($row[$col['notes']]) ? cleanVal($row[$col['notes']]) : '';

                    // Date naissance
                    $ddn_raw = isset($col['date_naissance']) && isset($row[$col['date_naissance']]) ? cleanVal($row[$col['date_naissance']]) : '';
                    $date_naissance = '';
                    if ($ddn_raw) { $ts = strtotime($ddn_raw); if ($ts) $date_naissance = date('Y-m-d',$ts); else $w[] = "Date invalide: $ddn_raw"; }

                    // Prenom / nom
                    $prenom = isset($col['prenom']) && isset($row[$col['prenom']]) ? cleanVal($row[$col['prenom']]) : '';
                    $nom    = isset($col['nom'])    && isset($row[$col['nom']])    ? cleanVal($row[$col['nom']])    : '';

                    if ($w) $warnings[] = array('ligne'=>$line,'type'=>'warning','msg'=>implode(', ',$w));

                    $contacts[] = array(
                        'email'=>$email,'prenom'=>$prenom,'nom'=>$nom,'tel'=>$tel,
                        'adresse'=>$adresse,'commune'=>$commune,'benevole'=>$benevole,
                        'soutien'=>$soutien,'notes'=>$notes,'ddn'=>$date_naissance,
                    );
                }

                // Stocker en session pour l'import
                $_SESSION['wix_contacts']  = $contacts;
                $_SESSION['wix_warnings']  = $warnings;
                $_SESSION['wix_filename']  = $_FILES['csv']['name'];

                $preview_data = array(
                    'contacts' => $contacts,
                    'warnings' => $warnings,
                    'filename' => $_FILES['csv']['name'],
                );
            }
        }
    }
}

// ── ÉTAPE 2 : Importer les données nettoyées ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import') {
    $contacts = $_SESSION['wix_contacts'] ?? array();
    $filename = $_SESSION['wix_filename'] ?? 'import.csv';

    if (empty($contacts)) { $error = 'Aucune donnée à importer. Recommencez l\'étape 1.'; }
    else {
        $nb_total = count($contacts);
        $nb_importes = $nb_doublons = $nb_erreurs = 0;
        $import_errors = array(); $import_doublons = array();

        // Colonnes disponibles en BDD
        $has_extra_cols = false;
        try {
            $db->query("SELECT soutien_action FROM subscribers LIMIT 1");
            $has_extra_cols = true;
        } catch (Exception $e) {}

        $db->beginTransaction();
        $batch = 0;
        foreach ($contacts as $c) {
            $check = $db->prepare("SELECT id FROM subscribers WHERE email=?");
            $check->execute(array($c['email']));
            $existing = $check->fetch();

            if ($existing) {
                $nb_doublons++;
                $import_doublons[] = $c['email'];
                $db->prepare("UPDATE subscribers SET source_import='wix',
                    prenom=CASE WHEN (prenom IS NULL OR prenom='') THEN ? ELSE prenom END,
                    nom=CASE WHEN (nom IS NULL OR nom='') THEN ? ELSE nom END,
                    telephone=CASE WHEN (telephone IS NULL OR telephone='') THEN ? ELSE telephone END,
                    adresse=CASE WHEN (adresse IS NULL OR adresse='') THEN ? ELSE adresse END,
                    commune=CASE WHEN (commune IS NULL OR commune='') THEN ? ELSE commune END
                    WHERE id=?")
                   ->execute(array($c['prenom'],$c['nom'],$c['tel'],$c['adresse'],$c['commune'],$existing['id']));
            } else {
                try {
                    $token = bin2hex(random_bytes(32));
                    if ($has_extra_cols) {
                        $db->prepare("INSERT INTO subscribers (email,prenom,nom,telephone,adresse,commune,benevole,soutien_action,notes,date_naissance,rgpd_accepte,statut,token_unsub,source,source_import,email_bienvenue_envoye) VALUES (?,?,?,?,?,?,?,?,?,?,1,'actif',?,'wix_import','wix',0)")
                           ->execute(array($c['email'],$c['prenom'],$c['nom'],$c['tel'],$c['adresse'],$c['commune'],$c['benevole'],$c['soutien'],$c['notes'],$c['ddn'],$token));
                    } else {
                        $db->prepare("INSERT INTO subscribers (email,prenom,nom,telephone,adresse,commune,benevole,rgpd_accepte,statut,token_unsub,source,source_import,email_bienvenue_envoye) VALUES (?,?,?,?,?,?,?,1,'actif',?,'wix_import','wix',0)")
                           ->execute(array($c['email'],$c['prenom'],$c['nom'],$c['tel'],$c['adresse'],$c['commune'],$c['benevole'],$token));
                    }
                    $nb_importes++;
                } catch (Exception $e) {
                    $nb_erreurs++;
                    $import_errors[] = array('email'=>$c['email'],'raison'=>$e->getMessage());
                }
            }
            $batch++;
            if ($batch % 50 === 0) { $db->commit(); $db->beginTransaction(); }
        }
        $db->commit();

        $db->prepare("INSERT INTO imports_wix (filename,nb_total,nb_importes,nb_doublons,nb_erreurs,importe_par) VALUES (?,?,?,?,?,?)")
           ->execute(array($filename,$nb_total,$nb_importes,$nb_doublons,$nb_erreurs,ADMIN_USER));

        unset($_SESSION['wix_contacts'], $_SESSION['wix_warnings'], $_SESSION['wix_filename']);
        $import_result = compact('nb_total','nb_importes','nb_doublons','nb_erreurs','import_errors','import_doublons','filename');
        $msg = "Import terminé !";
    }
}

// ── ENVOI EMAIL BIENVENUE ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'envoyer_bienvenue') {
    $ids = isset($_POST['ids']) ? array_filter(array_map('intval', explode(',', $_POST['ids']))) : array();
    if (empty($ids)) $contacts_env = $db->query("SELECT * FROM subscribers WHERE source_import='wix' AND email_bienvenue_envoye=0 AND statut='actif'")->fetchAll();
    else { $ph=implode(',',array_fill(0,count($ids),'?')); $stmt=$db->prepare("SELECT * FROM subscribers WHERE id IN ($ph)"); $stmt->execute($ids); $contacts_env=$stmt->fetchAll(); }
    $envoyes=$erreurs_env=0;
    foreach ($contacts_env as $contact) {
        if (envoyerEmailBienvenue($contact)) { $db->prepare("UPDATE subscribers SET email_bienvenue_envoye=1,email_bienvenue_date=NOW() WHERE id=?")->execute(array($contact['id'])); $envoyes++; }
        else $erreurs_env++;
        usleep(200000);
    }
    $msg = "$envoyes email(s) envoyé(s)".($erreurs_env?", $erreurs_env erreur(s)":"");
}

// Stats
$filtre=$_GET['filtre']??'tous'; $search=trim($_GET['q']??'');
$where="WHERE source_import='wix'";
if ($filtre==='attente') $where.=" AND email_bienvenue_envoye=0";
if ($filtre==='envoye')  $where.=" AND email_bienvenue_envoye=1";
if ($search) $where.=" AND (email LIKE ".$db->quote('%'.$search.'%')." OR prenom LIKE ".$db->quote('%'.$search.'%')." OR commune LIKE ".$db->quote('%'.$search.'%').")";
$contacts_wix  = $db->query("SELECT * FROM subscribers $where ORDER BY email_bienvenue_envoye ASC, date_inscription DESC")->fetchAll();
$nb_wix_total  = $db->query("SELECT COUNT(*) FROM subscribers WHERE source_import='wix'")->fetchColumn();
$nb_wix_env    = $db->query("SELECT COUNT(*) FROM subscribers WHERE source_import='wix' AND email_bienvenue_envoye=1")->fetchColumn();
$nb_wix_rest   = $nb_wix_total - $nb_wix_env;
$imports_hist  = $db->query("SELECT * FROM imports_wix ORDER BY date_import DESC LIMIT 10")->fetchAll();
$msg = $_GET['msg'] ?? $msg;

function envoyerEmailBienvenue($contact) {
    $token=bin2hex(random_bytes(32));
    try { getDB()->prepare("UPDATE subscribers SET token_confirm=? WHERE id=?")->execute(array($token,$contact['id'])); } catch(Exception $e){ return false; }
    $prenom=$contact['prenom']?:'cher(e) sympathisant(e)';
    $lien=SITE_URL.'/welcome.php?token='.$token.'&email='.urlencode($contact['email']);
    $html='<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial;background:#f0f4f8;padding:20px"><table width="600" style="background:#fff;border-radius:8px;overflow:hidden;margin:auto"><tr><td style="background:#0e3d6b;padding:20px;text-align:center"><h1 style="color:#fff;margin:0">Ça suffit !</h1></td></tr><tr><td style="padding:30px"><p>Bonjour '.htmlspecialchars($prenom).',</p><p>Notre site a été entièrement rénové. Accédez à votre espace :</p><div style="text-align:center;margin:20px 0"><a href="'.$lien.'" style="background:#FF9900;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:bold">→ Mon espace</a></div><p style="color:#aaa;font-size:12px">Lien valable 30 jours.</p></td></tr></table></body></html>';
    $text="Bonjour $prenom,\n\nAccédez à votre espace : $lien\n\nÇa suffit !";
    if (!empty(BREVO_API_KEY)) {
        $ch=curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt_array($ch,array(CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
            CURLOPT_POSTFIELDS=>json_encode(array('sender'=>array('name'=>SMTP_FROM_NAME,'email'=>SMTP_FROM),'to'=>array(array('email'=>$contact['email'],'name'=>trim($contact['prenom'].' '.$contact['nom']))),'subject'=>'Votre espace — Ça suffit !','htmlContent'=>$html,'textContent'=>$text)),
            CURLOPT_HTTPHEADER=>array('accept: application/json','api-key: '.BREVO_API_KEY,'content-type: application/json'),CURLOPT_TIMEOUT=>15));
        $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
        return $code>=200&&$code<300;
    }
    return false;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<?php include __DIR__ . '/../includes/admin_pwa_head.php'; ?>
  <title>Import Wix — Admin</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333}
    <?php include __DIR__ . '/../includes/admin_sidebar_css.php'; ?>
    .main{margin-left:240px;padding:24px;max-width:1200px}
    .page-title{font-size:1.2rem;font-weight:800;color:#0e3d6b;margin-bottom:20px}
    .card{background:#fff;border-radius:10px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,.06);margin-bottom:18px}
    .card h3{font-size:.88rem;font-weight:700;color:#0e3d6b;margin-bottom:14px;padding-bottom:8px;border-bottom:1px solid #eee}
    .stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:18px}
    .stat-box{background:#fff;border-radius:8px;padding:14px;text-align:center;box-shadow:0 2px 6px rgba(0,0,0,.06)}
    .stat-val{font-size:1.6rem;font-weight:800;color:#1673B2}
    .stat-val.green{color:#27ae60}.stat-val.orange{color:#FF9900}
    .stat-lab{font-size:.68rem;color:#888;text-transform:uppercase;letter-spacing:.04em;margin-top:2px}
    .upload-zone{border:2px dashed #bee3f8;border-radius:8px;padding:24px;text-align:center;background:#f0f7ff;cursor:pointer;transition:all .2s}
    .upload-zone:hover{border-color:#1673B2}
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
    .flash-ok{background:#e8f8f0;color:#276749;padding:10px 14px;border-radius:6px;margin-bottom:14px;font-size:.82rem;border-left:3px solid #48bb78}
    .flash-err{background:#fde8e8;color:#c53030;padding:10px 14px;border-radius:6px;margin-bottom:14px;font-size:.82rem;border-left:3px solid #fc8181}
    .result-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin:10px 0}
    .r-box{text-align:center;padding:10px;border-radius:6px}
    .r-box.blue{background:#e6f1fb;color:#1673B2}.r-box.green{background:#e8f8f0;color:#27ae60}
    .r-box.orange{background:#fff3e0;color:#ba7517}.r-box.red{background:#fde8e8;color:#c53030}
    .r-val{font-size:1.3rem;font-weight:800}.r-lab{font-size:.68rem}
    /* Validation */
    .validation-ok{background:#e8f8f0;border:1px solid #48bb78;border-radius:8px;padding:14px;margin-bottom:14px}
    .validation-ok h4{color:#276749;font-size:.85rem;margin-bottom:8px}
    .warn-item{padding:5px 8px;background:#fff8ee;border-radius:4px;margin-bottom:3px;font-size:.75rem;color:#856404}
    .err-item{padding:5px 8px;background:#fde8e8;border-radius:4px;margin-bottom:3px;font-size:.75rem;color:#c53030}
    .preview-table{width:100%;border-collapse:collapse;font-size:.75rem;margin-top:10px}
    .preview-table th{background:#f0f4f8;padding:6px 8px;text-align:left;font-size:.68rem;color:#555;text-transform:uppercase;border-bottom:2px solid #e0e8f0}
    .preview-table td{padding:6px 8px;border-bottom:1px solid #f5f5f5}
    .preview-table tr:hover td{background:#fafbfd}
    /* Table contacts */
    table{width:100%;border-collapse:collapse;font-size:.78rem}
    th{text-align:left;padding:7px 8px;color:#888;font-weight:600;font-size:.68rem;text-transform:uppercase;border-bottom:2px solid #eee;white-space:nowrap}
    td{padding:7px 8px;border-bottom:1px solid #f5f5f5;vertical-align:middle}
    .badge{display:inline-block;padding:2px 7px;border-radius:10px;font-size:.63rem;font-weight:700}
    .b-ok{background:#e8f8f0;color:#27ae60}.b-wait{background:#fff3e0;color:#ba7517}
    .b-err{background:#fde8e8;color:#c53030}.b-dup{background:#f3e5f5;color:#7b1fa2}
    .filtres{display:flex;gap:8px;align-items:center;margin-bottom:12px;flex-wrap:wrap}
    .filtre-btn{padding:5px 12px;border-radius:20px;font-size:.75rem;font-weight:600;cursor:pointer;border:1.5px solid #e0e8f0;background:#fff;color:#666;text-decoration:none;transition:all .15s}
    .filtre-btn.active{background:#1673B2;color:#fff;border-color:#1673B2}
    .search-box{padding:6px 10px;border:1.5px solid #dde4ed;border-radius:6px;font-size:.8rem;outline:none;font-family:inherit;min-width:180px}
    .toggle-link{font-size:.75rem;color:#1673B2;cursor:pointer;text-decoration:underline;background:none;border:none;padding:0;font-family:inherit}
    .hidden{display:none}
  
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
  <div class="page-title">📥 Import contacts Wix</div>

  <?php if ($msg): ?><div class="flash-ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="flash-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-box"><div class="stat-val"><?= $nb_wix_total ?></div><div class="stat-lab">Total importés</div></div>
    <div class="stat-box"><div class="stat-val green"><?= $nb_wix_env ?></div><div class="stat-lab">Emails envoyés</div></div>
    <div class="stat-box"><div class="stat-val orange"><?= $nb_wix_rest ?></div><div class="stat-lab">En attente</div></div>
  </div>

  <?php if ($import_result): ?>
  <!-- Résultat import -->
  <div class="card">
    <h3>✅ Résultat : <?= htmlspecialchars($import_result['filename']) ?></h3>
    <div class="result-grid">
      <div class="r-box blue"><div class="r-val"><?= $import_result['nb_total'] ?></div><div class="r-lab">Total</div></div>
      <div class="r-box green"><div class="r-val"><?= $import_result['nb_importes'] ?></div><div class="r-lab">Importés</div></div>
      <div class="r-box orange"><div class="r-val"><?= $import_result['nb_doublons'] ?></div><div class="r-lab">Doublons</div></div>
      <div class="r-box red"><div class="r-val"><?= $import_result['nb_erreurs'] ?></div><div class="r-lab">Erreurs</div></div>
    </div>
    <?php if (!empty($import_result['import_errors'])): ?>
    <button class="toggle-link" onclick="toggleEl('imp-errors')">▶ Voir les <?= count($import_result['import_errors']) ?> erreur(s)</button>
    <div id="imp-errors" class="hidden" style="margin-top:8px">
      <?php foreach ($import_result['import_errors'] as $e): ?>
      <div class="err-item"><strong><?= htmlspecialchars($e['email']) ?></strong> — <?= htmlspecialchars($e['raison']) ?></div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if ($preview_data): ?>
  <!-- Prévisualisation + confirmation import -->
  <div class="card">
    <h3>🔍 Validation — <?= htmlspecialchars($preview_data['filename']) ?></h3>

    <?php
    $nb_errors = count(array_filter($preview_data['warnings'], fn($w) => $w['type']==='error'));
    $nb_dups   = count(array_filter($preview_data['warnings'], fn($w) => $w['type']==='doublon_csv'));
    $nb_warns  = count(array_filter($preview_data['warnings'], fn($w) => $w['type']==='warning'));
    $nb_valid  = count($preview_data['contacts']);
    ?>

    <div class="result-grid">
      <div class="r-box blue"><div class="r-val"><?= $nb_valid + $nb_errors + $nb_dups ?></div><div class="r-lab">Total lignes</div></div>
      <div class="r-box green"><div class="r-val"><?= $nb_valid ?></div><div class="r-lab">Valides</div></div>
      <div class="r-box orange"><div class="r-val"><?= $nb_dups ?></div><div class="r-lab">Doublons CSV</div></div>
      <div class="r-box red"><div class="r-val"><?= $nb_errors ?></div><div class="r-lab">Erreurs</div></div>
    </div>

    <?php if ($preview_data['warnings']): ?>
    <button class="toggle-link" onclick="toggleEl('warn-list')" style="margin:10px 0;display:block">
      ▶ Voir les <?= count($preview_data['warnings']) ?> avertissement(s)
    </button>
    <div id="warn-list" class="hidden">
      <?php foreach ($preview_data['warnings'] as $w): ?>
      <div class="<?= $w['type']==='error'?'err-item':'warn-item' ?>">
        <strong>Ligne <?= $w['ligne'] ?></strong> — <?= htmlspecialchars($w['msg']) ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Aperçu des 5 premiers -->
    <button class="toggle-link" onclick="toggleEl('preview-rows')" style="margin:10px 0;display:block">
      ▶ Aperçu des <?= min(5,count($preview_data['contacts'])) ?> premiers contacts nettoyés
    </button>
    <div id="preview-rows" class="hidden">
      <div style="overflow-x:auto">
      <table class="preview-table">
        <tr><th>Email</th><th>Prénom</th><th>Nom</th><th>Téléphone</th><th>Adresse</th><th>Commune</th><th>Naissance</th><th>Bénévole</th><th>Soutien</th></tr>
        <?php foreach (array_slice($preview_data['contacts'],0,5) as $c): ?>
        <tr>
          <td><?= htmlspecialchars($c['email']) ?></td>
          <td><?= htmlspecialchars($c['prenom']) ?></td>
          <td><?= htmlspecialchars($c['nom']) ?></td>
          <td><?= htmlspecialchars($c['tel']) ?></td>
          <td><?= htmlspecialchars($c['adresse']) ?></td>
          <td><?= htmlspecialchars($c['commune']) ?></td>
          <td><?= htmlspecialchars($c['ddn']) ?></td>
          <td><?= $c['benevole']?'✓':'—' ?></td>
          <td><?= $c['soutien']?'✓':'—' ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
      </div>
    </div>

    <div style="margin-top:16px;display:flex;gap:10px;align-items:center">
      <?php if ($nb_valid > 0): ?>
      <form method="POST"><?= csrf_field() ?>
        <input type="hidden" name="action" value="import">
        <button type="submit" class="btn btn-green"
                onclick="return confirm('Importer <?= $nb_valid ?> contacts ?')">
          ✅ Confirmer l'import (<?= $nb_valid ?> contacts)
        </button>
      </form>
      <?php endif; ?>
      <a href="import_wix.php" class="btn" style="background:#f0f0f0;color:#666">✕ Annuler</a>
    </div>
  </div>
  <?php else: ?>

  <!-- Upload CSV -->
  <div class="card">
    <h3>📁 Étape 1 — Valider et nettoyer le fichier CSV</h3>
    <p style="font-size:.8rem;color:#555;margin-bottom:14px;line-height:1.6">
      Le fichier sera <strong>analysé et nettoyé</strong> avant import : suppression des retours à la ligne, 
      correction des téléphones, extraction des adresses. Un aperçu vous permettra de vérifier avant de confirmer.
    </p>
    <form method="POST" enctype="multipart/form-data"><?= csrf_field() ?>
      <input type="hidden" name="action" value="preview">
      <div class="upload-zone" onclick="document.getElementById('csv-file').click()">
        <input type="file" id="csv-file" name="csv" accept=".csv" onchange="updateZone(this)" style="display:none">
        <div style="font-size:2rem">📋</div>
        <p id="zone-text" style="color:#555;font-size:.85rem;margin:8px 0 4px">Cliquez pour sélectionner votre CSV Wix</p>
        <small style="color:#aaa;font-size:.72rem">Export Wix : Contacts → ⋮ Exporter → CSV</small>
      </div>
      <button type="submit" class="btn btn-primary" id="btn-validate" style="margin-top:10px;display:none">
        🔍 Valider et prévisualiser
      </button>
    </form>
  </div>

  <?php endif; ?>

  <!-- Envoi emails -->
  <?php if ($nb_wix_rest > 0 && !$preview_data): ?>
  <div class="card">
    <h3>✉ Envoyer les emails de bienvenue (<?= $nb_wix_rest ?> en attente)</h3>
    <form method="POST" onsubmit="return confirm('Envoyer <?= $nb_wix_rest ?><?= csrf_field() ?> email(s) ?')">
      <input type="hidden" name="action" value="envoyer_bienvenue">
      <input type="hidden" name="ids" value="">
      <button type="submit" class="btn btn-orange">✉ Envoyer les <?= $nb_wix_rest ?> email(s)</button>
    </form>
  </div>
  <?php endif; ?>

  <!-- Contacts -->
  <?php if (!$preview_data && $nb_wix_total > 0): ?>
  <div class="card">
    <h3>👥 Contacts (<?= $nb_wix_total ?> total)</h3>
    <div class="filtres">
      <a href="?filtre=tous" class="filtre-btn <?= $filtre==='tous'?'active':'' ?>">Tous (<?= $nb_wix_total ?>)</a>
      <a href="?filtre=attente" class="filtre-btn <?= $filtre==='attente'?'active':'' ?>">En attente (<?= $nb_wix_rest ?>)</a>
      <a href="?filtre=envoye" class="filtre-btn <?= $filtre==='envoye'?'active':'' ?>">Envoyés (<?= $nb_wix_env ?>)</a>
      <form method="GET" style="display:flex;gap:6px;margin-left:auto">
        <input type="hidden" name="filtre" value="<?= $filtre ?>">
        <input type="text" name="q" class="search-box" placeholder="Rechercher..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn btn-primary btn-sm">🔍</button>
        <?php if ($search): ?><a href="?filtre=<?= $filtre ?>" class="btn btn-sm" style="background:#f0f0f0;color:#666">✕</a><?php endif; ?>
      </form>
    </div>
    <div style="overflow-x:auto">
    <table>
      <tr>
        <th><input type="checkbox" id="cb-all" onchange="toggleAll(this)"></th>
        <th>Email</th><th>Prénom</th><th>Nom</th><th>Téléphone</th>
        <th>Adresse</th><th>Commune</th><th>Bénévole</th><th>Soutien</th>
        <th>Email bvn.</th><th>Action</th>
      </tr>
      <?php foreach ($contacts_wix as $c): ?>
      <tr>
        <td><input type="checkbox" class="cb-contact" value="<?= $c['id'] ?>"></td>
        <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($c['email']) ?></td>
        <td><?= htmlspecialchars($c['prenom']?:'—') ?></td>
        <td><?= htmlspecialchars($c['nom']?:'—') ?></td>
        <td><?= htmlspecialchars($c['telephone']?:'—') ?></td>
        <td style="max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($c['adresse']??'') ?>"><?= htmlspecialchars($c['adresse']?:'—') ?></td>
        <td><?= htmlspecialchars($c['commune']?:'—') ?></td>
        <td style="text-align:center"><?= ($c['benevole']??0)?'<span class="badge b-ok">✓</span>':'—' ?></td>
        <td style="text-align:center"><?= ($c['soutien_action']??0)?'<span class="badge b-ok">✓</span>':'—' ?></td>
        <td><?= $c['email_bienvenue_envoye']?'<span class="badge b-ok">✓</span>':'<span class="badge b-wait">⏳</span>' ?></td>
        <td>
          <form method="POST" style="display:inline"><?= csrf_field() ?>
            <input type="hidden" name="action" value="envoyer_bienvenue">
            <input type="hidden" name="ids" value="<?= $c['id'] ?>">
            <button type="submit" class="btn btn-sm <?= $c['email_bienvenue_envoye']?'':'btn-green' ?>"
                    style="<?= $c['email_bienvenue_envoye']?'background:#f0f0f0;color:#888':'' ?>"
                    onclick="return confirm('<?= $c['email_bienvenue_envoye']?'Renvoyer':'Envoyer' ?> l\'email ?')">
              <?= $c['email_bienvenue_envoye']?'↻':'✉' ?>
            </button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    </div>
    <div style="margin-top:10px;display:flex;align-items:center;gap:10px">
      <span id="nb-selec" style="font-size:.75rem;color:#888">0 sélectionné(s)</span>
      <form method="POST" id="form-groupe"><?= csrf_field() ?>
        <input type="hidden" name="action" value="envoyer_bienvenue">
        <input type="hidden" name="ids" id="ids-groupe" value="">
        <button type="submit" class="btn btn-orange btn-sm" id="btn-groupe" disabled onclick="return prepareGroupe()">✉ Envoyer aux sélectionnés</button>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <!-- Historique -->
  <?php if (!empty($imports_hist) && !$preview_data): ?>
  <div class="card">
    <h3><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> Historique des imports</h3>
    <table>
      <tr><th>Fichier</th><th>Date</th><th>Total</th><th>Importés</th><th>Doublons</th><th>Erreurs</th></tr>
      <?php foreach ($imports_hist as $h): ?>
      <tr>
        <td style="font-family:monospace;font-size:.72rem"><?= htmlspecialchars($h['filename']) ?></td>
        <td style="white-space:nowrap"><?= date('d/m/Y H:i',strtotime($h['date_import'])) ?></td>
        <td><?= $h['nb_total'] ?></td>
        <td><span class="badge b-ok"><?= $h['nb_importes'] ?></span></td>
        <td><span class="badge b-dup"><?= $h['nb_doublons'] ?></span></td>
        <td><span class="badge b-err"><?= $h['nb_erreurs'] ?></span></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>
</div>

<script>
function updateZone(input) {
    if (input.files[0]) {
        document.getElementById('zone-text').textContent = input.files[0].name + ' (' + (input.files[0].size/1024).toFixed(0) + ' KB)';
        document.getElementById('btn-validate').style.display = 'inline-block';
    }
}
function toggleEl(id) {
    var el = document.getElementById(id);
    el.classList.toggle('hidden');
}
function toggleAll(cb) {
    document.querySelectorAll('.cb-contact').forEach(function(c){c.checked=cb.checked});
    updateCount();
}
document.addEventListener('change', function(e){ if(e.target.classList.contains('cb-contact')) updateCount(); });
function updateCount() {
    var n = document.querySelectorAll('.cb-contact:checked').length;
    document.getElementById('nb-selec').textContent = n + ' sélectionné(s)';
    document.getElementById('btn-groupe').disabled = (n===0);
}
function prepareGroupe() {
    var ids = Array.from(document.querySelectorAll('.cb-contact:checked')).map(function(c){return c.value});
    if (!ids.length) return false;
    document.getElementById('ids-groupe').value = ids.join(',');
    return confirm('Envoyer l\'email aux ' + ids.length + ' contacts ?');
}
// Drag & drop
var zone = document.querySelector('.upload-zone');
if (zone) {
    zone.addEventListener('dragover', function(e){e.preventDefault();this.style.borderColor='#1673B2'});
    zone.addEventListener('dragleave', function(){this.style.borderColor=''});
    zone.addEventListener('drop', function(e){
        e.preventDefault(); this.style.borderColor='';
        var dt = new DataTransfer(); dt.items.add(e.dataTransfer.files[0]);
        document.getElementById('csv-file').files = dt.files;
        updateZone({files:[e.dataTransfer.files[0]]});
    });
}
</script>
</body>
</html>
