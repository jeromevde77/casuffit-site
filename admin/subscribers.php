<?php
// admin/subscribers.php
require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();
require_once __DIR__ . '/../includes/csrf.php';
$db = getDB();

// ── Actions groupées ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_verify();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ids']) && isset($_POST['bulk_action'])) {
    $ids = array_filter(array_map('intval', (array)$_POST['ids']));
    if (!empty($ids)) {
        $in = implode(',', $ids);
        switch ($_POST['bulk_action']) {
            case 'activer':
                $db->exec("UPDATE subscribers SET statut='actif' WHERE id IN ($in)");
                $msg = count($ids) . ' abonné(s) activé(s).'; break;
            case 'desactiver':
                $db->exec("UPDATE subscribers SET statut='en_attente' WHERE id IN ($in)");
                $msg = count($ids) . ' abonné(s) passé(s) en attente.'; break;
            case 'supprimer':
                $db->exec("DELETE FROM subscribers WHERE id IN ($in)");
                $msg = count($ids) . ' abonné(s) supprimé(s) (RGPD).'; break;
        }
    }
    $qs = http_build_query(array_filter(array_intersect_key($_POST, array_flip(['commune','benevole','statut','q','source']))));
    header('Location: subscribers.php' . ($qs ? '?'.$qs.'&' : '?') . 'msg='.urlencode($msg ?? '')); exit;
}

// ── Ajout manuel ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_subscriber'])) {
    $email = trim($_POST['email'] ?? '');
    if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $exists = $db->prepare("SELECT id FROM subscribers WHERE email=?");
        $exists->execute([$email]);
        if ($exists->fetch()) {
            $add_error = 'Cet email est déjà enregistré.';
        } else {
            $db->prepare("INSERT INTO subscribers (email,prenom,nom,commune,adresse,telephone,statut,benevole,date_inscription) VALUES (?,?,?,?,?,?,?,?,NOW())")
               ->execute([
                   $email, trim($_POST['prenom']??''), trim($_POST['nom']??''),
                   trim($_POST['commune']??''), trim($_POST['adresse']??''), trim($_POST['telephone']??''),
                   in_array($_POST['statut']??'', ['actif','en_attente','desabonne']) ? $_POST['statut'] : 'actif',
                   isset($_POST['benevole']) ? 1 : 0,
               ]);
            header('Location: subscribers.php?msg='.urlencode('Abonné ajouté.')); exit;
        }
    } else {
        $add_error = 'Email invalide ou manquant.';
    }
}

// ── Édition ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id']) && is_numeric($_POST['edit_id'])) {
    $db->prepare("UPDATE subscribers SET prenom=?,nom=?,email=?,commune=?,adresse=?,telephone=?,statut=?,benevole=? WHERE id=?")
       ->execute([
           trim($_POST['prenom'] ?? ''), trim($_POST['nom'] ?? ''), trim($_POST['email'] ?? ''),
           trim($_POST['commune'] ?? ''), trim($_POST['adresse'] ?? ''), trim($_POST['telephone'] ?? ''),
           in_array($_POST['statut'] ?? '', ['actif','en_attente','desabonne']) ? $_POST['statut'] : 'en_attente',
           isset($_POST['benevole']) ? 1 : 0, intval($_POST['edit_id']),
       ]);
    $qs = http_build_query(array_filter(array_intersect_key($_POST, array_flip(['commune','benevole','statut','q','source']))));
    header('Location: subscribers.php' . ($qs ? '?'.$qs.'&' : '?') . 'msg='.urlencode('Abonné modifié.')); exit;
}

// ── Filtres ───────────────────────────────────────────────────────────────
$where = ['1=1']; $params = [];
if (!empty($_GET['commune'])) { $where[] = 'commune LIKE ?'; $params[] = '%'.$_GET['commune'].'%'; }
if (isset($_GET['benevole']) && $_GET['benevole'] !== '') { $where[] = 'benevole = ?'; $params[] = (int)$_GET['benevole']; }
if (!empty($_GET['statut']))  { $where[] = 'statut = ?'; $params[] = $_GET['statut']; }
if (!empty($_GET['source']))  {
    if ($_GET['source'] === 'wix')    { $where[] = "source_import = 'wix'"; }
    elseif ($_GET['source'] === 'site') { $where[] = "(source_import IS NULL OR source_import = '')"; }
}
if (!empty($_GET['q']))       { $where[] = '(email LIKE ? OR prenom LIKE ? OR nom LIKE ? OR commune LIKE ?)';
                                $q = '%'.$_GET['q'].'%'; array_push($params, $q,$q,$q,$q); }
if (!empty($_GET['membre'])) {
    if ($_GET['membre'] === 'non')  $where[] = "NOT EXISTS (SELECT 1 FROM members m WHERE m.email=s.email)";
    elseif ($_GET['membre'] === 'oui') $where[] = "EXISTS (SELECT 1 FROM members m WHERE m.email=s.email)";
}
$where_sql = implode(' AND ', $where);

// ── Tri serveur ──────────────────────────────────────────────────────────
$sub_sort_map = [
    'email'    => 's.email',
    'nom'      => 's.nom, s.prenom',
    'commune'  => 's.commune',
    'benevole' => 's.benevole',
    'statut'   => 's.statut',
    'date'     => 's.date_inscription',
    'membre'   => 'is_membre',
];
$sub_sort = array_key_exists($_GET['sort'] ?? '', $sub_sort_map) ? $_GET['sort'] : 'date';
$sub_dir  = ($_GET['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

// ── Pagination ───────────────────────────────────────────────────────────
$sub_per   = 50;
$sub_page  = max(1,(int)($_GET['page'] ?? 1));
$stmt_cnt2 = $db->prepare("SELECT COUNT(*) FROM subscribers s WHERE $where_sql");
$stmt_cnt2->execute($params);
$sub_total  = (int)$stmt_cnt2->fetchColumn();
$sub_pages  = max(1,(int)ceil($sub_total/$sub_per));
$sub_page   = min($sub_page,$sub_pages);
$sub_offset = ($sub_page-1)*$sub_per;

// ── Export CSV ────────────────────────────────────────────────────────────
if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="abonnes_piste01_'.date('Ymd').'.csv"');
    $out = fopen('php://output', 'w'); fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Email','Prénom','Nom','Commune','Téléphone','Bénévole','Statut','Date inscription'], ';');
    $rows = $db->prepare("SELECT email,prenom,nom,commune,telephone,benevole,statut,date_inscription FROM subscribers WHERE $where_sql ORDER BY date_inscription DESC");
    $rows->execute($params);
    while ($r = $rows->fetch()) fputcsv($out, [$r['email'],$r['prenom'],$r['nom'],$r['commune'],$r['telephone'],$r['benevole']?'Oui':'Non',$r['statut'],date('d/m/Y H:i',strtotime($r['date_inscription']))], ';');
    fclose($out); exit;
}

// ── Copie emails ──────────────────────────────────────────────────────────
if (isset($_GET['emails_only'])) {
    header('Content-Type: text/plain; charset=utf-8');
    $e = $db->prepare("SELECT email FROM subscribers WHERE statut='actif' AND $where_sql ORDER BY email");
    $e->execute($params); echo implode("\n", $e->fetchAll(PDO::FETCH_COLUMN)); exit;
}

// ── Liste ─────────────────────────────────────────────────────────────────
$stmt = $db->prepare("SELECT s.*, (SELECT COUNT(*) FROM members m WHERE m.email=s.email LIMIT 1) AS is_membre FROM subscribers s WHERE $where_sql ORDER BY {$sub_sort_map[$sub_sort]} {$sub_dir} LIMIT $sub_per OFFSET $sub_offset");
$stmt->execute($params);
$subscribers  = $stmt->fetchAll();
$total_actifs = $db->query("SELECT COUNT(*) FROM subscribers WHERE statut='actif'")->fetchColumn();

function su_sub($ov=[]){
    global $sub_sort,$sub_dir;
    $keep=['q','commune','benevole','statut','source','membre','sort','dir','page'];
    $p=[];foreach($keep as $k){if(isset($_GET[$k])&&$_GET[$k]!=='')$p[$k]=$_GET[$k];}
    $p['sort']=$sub_sort;$p['dir']=$sub_dir;
    foreach($ov as $k=>$v)$p[$k]=$v;
    foreach($p as $k=>$v){if($v===''||$v===null||$v===false)unset($p[$k]);}
    return 'subscribers.php?'.http_build_query($p);
}
function sub_th($label,$col){
    global $sub_sort,$sub_dir;
    $active=($sub_sort===$col);
    $next=($active&&$sub_dir==='asc')?'desc':'asc';
    $url=su_sub(['sort'=>$col,'dir'=>$next,'page'=>1]);
    $icon=$active?($sub_dir==='asc'?'▲':'▼'):'<span style="opacity:.2">↕</span>';
    $style='color:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:4px;white-space:nowrap;'.($active?'color:#1673B2;font-weight:700;':'');
    return "<th><a href=\"$url\" style=\"$style\">".htmlspecialchars($label)." $icon</a></th>";
}
$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<?php include __DIR__ . '/../includes/admin_pwa_head.php'; ?>
  <title>Abonnés — Admin Piste 01</title>
  <style>
    <?php include __DIR__ . '/../includes/admin_sidebar_css.php'; ?>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333}
    .main{margin-left:240px;padding:28px 32px}
    .page-title{font-size:1.3rem;font-weight:800;color:#0e3d6b;margin-bottom:4px}
    .subtitle{color:#888;font-size:.82rem;margin-bottom:20px}
    /* Toolbar */
    .toolbar{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;align-items:center}
    .toolbar input[type=text],.toolbar select{padding:7px 11px;border:1.5px solid #dde4ed;border-radius:7px;font-size:.82rem;background:#fff;height:34px}
    /* Bulk bar */
    .bulk-bar{display:none;align-items:center;gap:10px;flex-wrap:wrap;background:#e8f3fb;border:1.5px solid #b5d4f4;border-radius:8px;padding:9px 14px;margin-bottom:12px;font-size:.82rem;color:#0e3d6b}
    .bulk-bar.visible{display:flex}
    .bulk-count{font-weight:700}
    .bulk-sep{width:1px;height:20px;background:#b5d4f4}
    /* Boutons */
    .btn{padding:6px 13px;border-radius:6px;font-size:.78rem;font-weight:600;cursor:pointer;border:1.5px solid transparent;font-family:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:5px;transition:all .15s;line-height:1;height:34px}
    .btn-p{background:#1673B2;color:#fff;border-color:#1673B2}.btn-p:hover{background:#125a90;color:#fff;text-decoration:none}
    .btn-g{background:#f0f4f8;color:#555;border-color:#dde4ed}.btn-g:hover{background:#e0e8f0;color:#333;text-decoration:none}
    .btn-r{background:#fff5f5;color:#e53e3e;border-color:#fed7d7}.btn-r:hover{background:#fee2e2}
    .btn-o{background:#fff8ee;color:#c97200;border-color:#ffd080}.btn-o:hover{background:#fff3d0}
    .btn-v{background:#f0fff4;color:#27ae60;border-color:#c6f6d5}.btn-v:hover{background:#dcfce7}
    .page-btn{padding:5px 11px;border-radius:6px;font-size:.78rem;font-weight:600;text-decoration:none;background:#f0f4f8;color:#555;border:1.5px solid #dde4ed;line-height:1}
    .page-btn:hover{background:#e0e8f0;text-decoration:none;color:#333}
    .page-btn-active{background:#1673B2!important;color:#fff!important;border-color:#1673B2!important}
    /* Act-btn inline */
    .act-btn{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:7px;border:1.5px solid;cursor:pointer;text-decoration:none;transition:all .15s;background:none;font-family:inherit;flex-shrink:0}
    .act-btn.edit{color:#4a5568;border-color:#e2e8f0;background:#f7f8fa}.act-btn.edit:hover{background:#edf2f7;border-color:#cbd5e0}
    .act-btn.del{color:#e53e3e;border-color:#fed7d7;background:#fff5f5}.act-btn.del:hover{background:#fee2e2;border-color:#fc8181}
    .act-btn.view{color:#27ae60;border-color:#c6f6d5;background:#f0fff4}.act-btn.view:hover{background:#dcfce7}
    /* Table */
    .card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.06);overflow-x:auto}
    table{width:100%;border-collapse:collapse;font-size:.82rem;white-space:nowrap}
    thead th{text-align:left;padding:10px;color:#888;font-weight:600;font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;border-bottom:2px solid #eee;background:#fafbfc}
    thead th.sortable{cursor:pointer;user-select:none;white-space:nowrap}
    thead th.sortable:hover{color:#1673B2;background:#f0f6fc}
    thead th.sort-asc::after{content:' ↑';color:#1673B2;font-weight:800}
    thead th.sort-desc::after{content:' ↓';color:#1673B2;font-weight:800}
    td{padding:9px 10px;border-bottom:1px solid #f5f5f5;color:#444;vertical-align:middle}
    tr:last-child td{border-bottom:none}
    tr:hover td{background:#f8fbff}
    tr.selected td{background:#eef6ff!important}
    .cb-col{width:36px;text-align:center}
    input[type=checkbox]{accent-color:#1673B2;width:15px;height:15px;cursor:pointer}
    /* Badges */
    .badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:.68rem;font-weight:600}
    .badge-green{background:#e8f8f0;color:#27ae60}.badge-orange{background:#fff3e0;color:#c97200}
    .badge-grey{background:#f0f0f0;color:#888}.badge-red{background:#fde8e8;color:#c0392b}
    /* Messages */
    .msg-ok{background:#e8f8f0;color:#27ae60;padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:.85rem;border-left:3px solid #48bb78}
    /* Modal */
    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center}
    .modal-overlay.open{display:flex}
    .modal{background:#fff;border-radius:12px;padding:28px;width:520px;max-width:95vw;box-shadow:0 8px 40px rgba(0,0,0,.18);max-height:90vh;overflow-y:auto}
    .modal h3{font-size:.95rem;font-weight:700;color:#0e3d6b;margin-bottom:18px}
    .frow{margin-bottom:12px}
    .frow label{display:block;font-size:.7rem;font-weight:700;color:#555;margin-bottom:3px;text-transform:uppercase;letter-spacing:.04em}
    .frow input,.frow select{width:100%;padding:8px 10px;border:1.5px solid #dde4ed;border-radius:6px;font-size:.82rem;font-family:inherit;color:#333;outline:none;transition:border .15s}
    .frow input:focus,.frow select:focus{border-color:#1673B2}
    .row2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .modal-foot{display:flex;gap:8px;margin-top:18px;justify-content:flex-end}
    .chk-label{display:flex;align-items:center;gap:6px;font-size:.82rem;cursor:pointer;margin-top:8px}
    @media(max-width:768px){.main{margin-left:0!important;padding:16px!important;padding-top:68px!important}table{font-size:.75rem}table th,table td{padding:6px 8px!important}}
  </style>
</head>
<body>
<?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>
<div class="main">
  <div class="page-title">Abonnés newsletter</div>
  <div class="subtitle"><?= $total_actifs ?> abonné(s) actif(s) · <?= $sub_total ?> affiché(s) — page <?= $sub_page ?>/<?= $sub_pages ?></div>

  <?php if ($msg): ?><div class="msg-ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

  <!-- Filtres -->
  <form method="GET" class="toolbar">
    <input type="text" name="q" placeholder="Rechercher…" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
    <input type="text" name="commune" placeholder="Commune" value="<?= htmlspecialchars($_GET['commune'] ?? '') ?>">
    <select name="benevole">
      <option value="">Tous (bénévole)</option>
      <option value="1" <?= ($_GET['benevole']??'')==='1'?'selected':'' ?>>Bénévoles</option>
      <option value="0" <?= ($_GET['benevole']??'')==='0'?'selected':'' ?>>Non bénévoles</option>
    </select>
    <select name="statut">
      <option value="">Tous les statuts</option>
      <option value="actif"      <?= ($_GET['statut']??'')==='actif'     ?'selected':'' ?>>Actifs</option>
      <option value="en_attente" <?= ($_GET['statut']??'')==='en_attente'?'selected':'' ?>>En attente</option>
      <option value="desabonne"  <?= ($_GET['statut']??'')==='desabonne' ?'selected':'' ?>>Désabonnés</option>
    </select>
    <select name="source">
      <option value="">Toutes les sources</option>
      <option value="wix"  <?= ($_GET['source']??'')==='wix'  ?'selected':'' ?>>Importés de Wix</option>
      <option value="site" <?= ($_GET['source']??'')==='site' ?'selected':'' ?>>Inscrits sur le site</option>
    </select>
    <select name="membre">
      <option value="">Tous (membre ou non)</option>
      <option value="non" <?= ($_GET['membre']??'')==='non'?'selected':'' ?>>🔴 Pas encore membres</option>
      <option value="oui" <?= ($_GET['membre']??'')==='oui'?'selected':'' ?>>✅ Déjà membres</option>
    </select>
    <button type="submit" class="btn btn-g">Filtrer</button>
    <a href="subscribers.php" class="btn btn-g">Réinitialiser</a>
    <a href="?<?= http_build_query(array_merge($_GET,['export'=>'1'])) ?>" class="btn btn-g">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      Export CSV
    </a>
    <button type="button" class="btn btn-g" onclick="copyEmails()">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
      Copier les emails
    </button>
    <button type="button" class="btn btn-p" onclick="openAdd()">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Ajouter un abonné
    </button>
  </form>

  <!-- Form bulk + table -->
  <form method="POST" id="bulk-form"><?= csrf_field() ?>
    <?php foreach ($_GET as $k=>$v): ?><input type="hidden" name="<?=htmlspecialchars($k)?>" value="<?=htmlspecialchars($v)?>"><?php endforeach; ?>
    <input type="hidden" name="bulk_action" id="bulk-action-input" value="">

    <!-- Barre actions groupées -->
    <div class="bulk-bar" id="bulk-bar">
      <span class="bulk-count"><span id="sel-count">0</span> sélectionné(s)</span>
      <div class="bulk-sep"></div>
      <button type="button" class="btn btn-v" onclick="doBulk('activer')">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        Activer
      </button>
      <button type="button" class="btn btn-o" onclick="doBulk('desactiver')">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        Mettre en attente
      </button>
      <button type="button" class="btn btn-r" onclick="doBulk('supprimer')">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
        Supprimer
      </button>
      <div class="bulk-sep"></div>
      <button type="button" class="btn btn-m" onclick="inviterSelection()" style="background:#8b5cf6;color:#fff">
        ✉ Inviter → Membre
      </button>
      <button type="button" class="btn btn-m" onclick="inviterSelectionWix()" style="background:#FF9900;color:#fff">
        ✊ Inviter anciens membres (relance Ça Suffit)
      </button>
      <div class="bulk-sep"></div>
      <button type="button" class="btn btn-g" onclick="clearSel()">Désélectionner</button>
    </div>

    <div class="card">
      <table>
        <thead>
          <tr>
            <th class="cb-col"><input type="checkbox" id="check-all" title="Tout sélectionner"></th>
            <?= sub_th('Email',    'email') ?>
            <?= sub_th('Prénom / Nom','nom') ?>
            <?= sub_th('Commune',  'commune') ?>
            <th>Téléphone</th>
            <?= sub_th('Bénévole', 'benevole') ?>
            <?= sub_th('Statut',   'statut') ?>
            <?= sub_th('Membre',   'membre') ?>
            <?= sub_th('Inscrit',  'date') ?>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($subscribers as $s): ?>
        <tr id="row-<?=$s['id']?>">
          <td class="cb-col"><input type="checkbox" name="ids[]" value="<?=$s['id']?>" class="row-cb" onchange="updBar()"></td>
          <td><?=htmlspecialchars($s['email'])?></td>
          <td><?=htmlspecialchars(trim($s['prenom'].' '.$s['nom'])) ?: '—'?></td>
          <td><?=htmlspecialchars($s['commune']?:'—')?></td>
          <td><?=htmlspecialchars($s['telephone']?:'—')?></td>
          <td><?=$s['benevole']?'<span class="badge badge-orange">Oui</span>':'<span class="badge badge-grey">Non</span>'?></td>
          <td>
            <?php if($s['statut']==='actif'):?><span class="badge badge-green">Actif</span>
            <?php elseif($s['statut']==='en_attente'):?><span class="badge badge-orange">En attente</span>
            <?php else:?><span class="badge badge-red">Désabonné</span><?php endif;?>
          </td>
          <td><?=date('d/m/Y',strtotime($s['date_inscription']))?></td>
          <td>
            <?php if (!empty($s['is_membre'])): ?>
              <span class="badge badge-green">✅ Membre</span>
            <?php elseif (!empty($s['invite_membre_accepted'] ?? null)): ?>
              <span class="badge badge-green">✓ A rejoint</span>
            <?php elseif (!empty($s['invite_membre_sent_at'] ?? null)): ?>
              <span class="badge" style="background:#faf5ff;color:#7c3aed;border:1px solid #c4b5fd"
                    title="Envoyée le <?= date('d/m/Y', strtotime($s['invite_membre_sent_at'])) ?>">
                ⏳ Invitation envoyée
              </span>
            <?php else: ?>
              <span class="badge badge-grey">Non membre</span>
            <?php endif; ?>
          </td>
          <td>
            <div style="display:flex;gap:5px">
              <button type="button" class="act-btn edit" title="Modifier" onclick="openEdit(<?=htmlspecialchars(json_encode($s))?>)">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              </button>
              <?php if ($s['statut'] !== 'actif'): ?>
              <button type="button" class="act-btn view" title="Activer" onclick="quickAct(<?=$s['id']?>,'activer')">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
              </button>
              <?php endif; ?>
              <?php if (empty($s['is_membre']) && empty($s['invite_membre_sent_at'] ?? null) && empty($s['invite_membre_accepted'] ?? null)): ?>
              <button type="button" class="act-btn" title="Inviter à devenir membre"
                      onclick="inviterMembre(<?=$s['id']?>)"
                      style="color:#8b5cf6;border-color:#e9d5ff;background:#faf5ff;font-size:13px">✉</button>
              <?php endif; ?>
              <button type="button" class="act-btn del" title="Supprimer (RGPD)" onclick="quickAct(<?=$s['id']?>,'supprimer')">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach;?>
        </tbody>
      </table>
      <?php if ($sub_pages > 1): ?>
      <div style="display:flex;gap:4px;align-items:center;padding:14px 16px;border-top:1px solid #f0f4f8;flex-wrap:wrap">
        <?php if($sub_page>1):?><a href="<?=su_sub(['page'=>1])?>" class="page-btn">«</a><a href="<?=su_sub(['page'=>$sub_page-1])?>" class="page-btn">‹</a><?php endif;?>
        <?php $ps=max(1,$sub_page-3);$pe=min($sub_pages,$sub_page+3);
          if($ps>1)echo '<span style="color:#aaa;font-size:.8rem">…</span>';
          for($i=$ps;$i<=$pe;$i++):?><a href="<?=su_sub(['page'=>$i])?>" class="page-btn <?=$i===$sub_page?'page-btn-active':''?>"><?=$i?></a><?php endfor;
          if($pe<$sub_pages)echo '<span style="color:#aaa;font-size:.8rem">…</span>';?>
        <?php if($sub_page<$sub_pages):?><a href="<?=su_sub(['page'=>$sub_page+1])?>" class="page-btn">›</a><a href="<?=su_sub(['page'=>$sub_pages])?>" class="page-btn">»</a><?php endif;?>
        <span style="font-size:.75rem;color:#888;margin-left:8px"><?=$sub_total?> abonné(s) — <?=$sub_per?>/page</span>
      </div>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- Modal édition -->
<div class="modal-overlay" id="edit-modal" onclick="if(event.target===this)closeEdit()">
  <div class="modal">
    <h3>Modifier l'abonné</h3>
    <form method="POST" id="edit-form"><?= csrf_field() ?>
      <input type="hidden" name="edit_id" id="edit-id">
      <?php foreach($_GET as $k=>$v):?><input type="hidden" name="<?=htmlspecialchars($k)?>" value="<?=htmlspecialchars($v)?>"><?php endforeach;?>
      <div class="row2">
        <div class="frow"><label>Prénom</label><input type="text" name="prenom" id="edit-prenom"></div>
        <div class="frow"><label>Nom</label><input type="text" name="nom" id="edit-nom"></div>
      </div>
      <div class="frow"><label>Email</label><input type="email" name="email" id="edit-email"></div>
      <div class="row2">
        <div class="frow"><label>Commune</label><input type="text" name="commune" id="edit-commune"></div>
        <div class="frow"><label>Téléphone</label><input type="text" name="telephone" id="edit-telephone"></div>
      </div>
      <div class="frow"><label>Adresse</label><input type="text" name="adresse" id="edit-adresse"></div>
      <div class="row2">
        <div class="frow">
          <label>Statut</label>
          <select name="statut" id="edit-statut">
            <option value="actif">Actif</option>
            <option value="en_attente">En attente</option>
            <option value="desabonne">Désabonné</option>
          </select>
        </div>
        <div class="frow">
          <label>Bénévole</label>
          <label class="chk-label"><input type="checkbox" name="benevole" id="edit-benevole"> Oui</label>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-g" onclick="closeEdit()">Annuler</button>
        <button type="submit" class="btn btn-p">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
          Sauvegarder
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Modal ajout -->
<div class="modal-overlay" id="add-modal" onclick="if(event.target===this)closeAdd()">
  <div class="modal">
    <h3>Ajouter un abonné</h3>
    <?php if (!empty($add_error)): ?>
      <div style="background:#fde8e8;color:#c53030;padding:8px 12px;border-radius:6px;margin-bottom:14px;font-size:.82rem;border-left:3px solid #fc8181"><?= htmlspecialchars($add_error) ?></div>
    <?php endif; ?>
    <form method="POST"><?= csrf_field() ?>
      <input type="hidden" name="add_subscriber" value="1">
      <div class="frow"><label>Email *</label><input type="email" name="email" required autofocus placeholder="prenom.nom@exemple.be"></div>
      <div class="row2">
        <div class="frow"><label>Prénom</label><input type="text" name="prenom"></div>
        <div class="frow"><label>Nom</label><input type="text" name="nom"></div>
      </div>
      <div class="row2">
        <div class="frow"><label>Commune</label><input type="text" name="commune"></div>
        <div class="frow"><label>Téléphone</label><input type="text" name="telephone"></div>
      </div>
      <div class="frow"><label>Adresse</label><input type="text" name="adresse"></div>
      <div class="row2">
        <div class="frow">
          <label>Statut</label>
          <select name="statut">
            <option value="actif">Actif</option>
            <option value="en_attente">En attente</option>
          </select>
        </div>
        <div class="frow">
          <label>Bénévole</label>
          <label class="chk-label"><input type="checkbox" name="benevole"> Oui</label>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-g" onclick="closeAdd()">Annuler</button>
        <button type="submit" class="btn btn-p">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Ajouter
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openAdd() {
  document.getElementById('add-modal').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeAdd() {
  document.getElementById('add-modal').classList.remove('open');
  document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key==='Escape') { closeEdit(); closeAdd(); } });
<?php if (!empty($add_error)): ?>
// Rouvrir la modale si erreur de validation
window.addEventListener('DOMContentLoaded', () => openAdd());
<?php endif; ?>

document.getElementById('check-all').addEventListener('change', function() {
  document.querySelectorAll('.row-cb').forEach(cb => {
    cb.checked = this.checked;
    cb.closest('tr').classList.toggle('selected', this.checked);
  });
  updBar();
});
function updBar() {
  var n = document.querySelectorAll('.row-cb:checked').length;
  document.getElementById('sel-count').textContent = n;
  document.getElementById('bulk-bar').classList.toggle('visible', n > 0);
  document.querySelectorAll('.row-cb').forEach(cb => cb.closest('tr').classList.toggle('selected', cb.checked));
}
function clearSel() {
  document.querySelectorAll('.row-cb,#check-all').forEach(cb => cb.checked = false);
  document.querySelectorAll('tr.selected').forEach(tr => tr.classList.remove('selected'));
  updBar();
}
function doBulk(action) {
  var n = document.querySelectorAll('.row-cb:checked').length;
  if (!n) return;
  var labels = {activer:'activer',desactiver:'mettre en attente',supprimer:'supprimer définitivement (RGPD)'};
  if (!confirm('Voulez-vous ' + labels[action] + ' ' + n + ' abonné(s) ?')) return;
  document.getElementById('bulk-action-input').value = action;
  document.getElementById('bulk-form').submit();
}

// Actions rapides individuelles
function quickAct(id, action) {
  var labels = {activer:'Activer cet abonné ?', supprimer:'Supprimer définitivement ? (RGPD)'};
  if (!confirm(labels[action])) return;
  var f = document.createElement('form'); f.method='POST'; f.action='';
  var add = (n,v) => { var i=document.createElement('input');i.type='hidden';i.name=n;i.value=v;f.appendChild(i); };
  add('_csrf', '<?= htmlspecialchars(csrf_token()) ?>');
  add('bulk_action', action); add('ids[]', id);
  <?php foreach($_GET as $k=>$v): ?>add(<?=json_encode($k)?>,<?=json_encode($v)?>);<?php endforeach;?>
  document.body.appendChild(f); f.submit();
}

// Modal édition
function openEdit(s) {
  ['id','prenom','nom','email','commune','telephone','adresse'].forEach(k => {
    document.getElementById('edit-'+k).value = s[k] || '';
  });
  document.getElementById('edit-statut').value = s.statut || 'en_attente';
  document.getElementById('edit-benevole').checked = s.benevole == 1;
  document.getElementById('edit-modal').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeEdit() {
  document.getElementById('edit-modal').classList.remove('open');
  document.body.style.overflow = '';
}

// ── Tri par colonne ───────────────────────────────────────────────────
var sortCol = -1, sortAsc = true;
document.querySelectorAll('thead th.sortable').forEach(th => {
  th.addEventListener('click', function() {
    var col = parseInt(this.dataset.col);
    if (sortCol === col) { sortAsc = !sortAsc; }
    else { sortCol = col; sortAsc = true; }
    // Màj indicateurs visuels
    document.querySelectorAll('thead th.sortable').forEach(t => t.classList.remove('sort-asc','sort-desc'));
    this.classList.add(sortAsc ? 'sort-asc' : 'sort-desc');
    // Trier les lignes
    var tbody = document.querySelector('tbody');
    var rows = Array.from(tbody.querySelectorAll('tr'));
    // Index réel dans la tr : col 0=email, +1 pour la cb
    rows.sort(function(a, b) {
      var ai = getCellVal(a, col);
      var bi = getCellVal(b, col);
      // Comparaison date (col 6 = "dd/mm/yyyy")
      if (col === 6) {
        ai = ai.split('/').reverse().join('');
        bi = bi.split('/').reverse().join('');
      }
      var cmp = ai.localeCompare(bi, 'fr', {numeric:true, sensitivity:'base'});
      return sortAsc ? cmp : -cmp;
    });
    rows.forEach(r => tbody.appendChild(r));
    // Réinitialiser la sélection
    clearSel();
  });
});
function getCellVal(row, col) {
  // col 0..6 → td index 1..7 (td[0] est la checkbox)
  var td = row.querySelectorAll('td')[col + 1];
  if (!td) return '';
  // Pour les badges, prendre le texte du badge
  var badge = td.querySelector('.badge');
  return (badge ? badge.textContent : td.textContent).trim();
}

function copyEmails() {
  fetch('subscribers.php?emails_only=1&<?=http_build_query(array_filter($_GET))?>')
    .then(r => r.text())
    .then(txt => {
      var n = txt.trim().split('\n').filter(Boolean).length;
      if (navigator.clipboard) navigator.clipboard.writeText(txt);
      alert(n + ' email(s) copié(s) dans le presse-papier !');
    });
}

function inviterMembre(id) {
  if (!confirm('Envoyer une invitation "Créer un espace membre" à cet abonné ?')) return;
  _envoyerInvitations([id], 'newsletter');
}

function inviterSelection() {
  var ids = Array.from(document.querySelectorAll('.row-cb:checked')).map(cb => cb.value);
  if (!ids.length) return;
  if (!confirm('Envoyer une invitation "Créer un espace membre" à ' + ids.length + ' abonné(s) sélectionné(s) ?')) return;
  _envoyerInvitations(ids, 'newsletter');
}

function inviterSelectionWix() {
  var ids = Array.from(document.querySelectorAll('.row-cb:checked')).map(cb => cb.value);
  if (!ids.length) { alert('Sélectionnez d\'abord des contacts (filtrez sur « Importés de Wix »).'); return; }
  if (!confirm('Envoyer l\'invitation de RELANCE du mouvement « Ça Suffit » à ' + ids.length + ' ancien(s) membre(s) ?\n\nCe message annonce le nouveau site et invite à redevenir membre.')) return;
  _envoyerInvitations(ids, 'wix');
}

function _envoyerInvitations(ids, type) {
  var btn = event?.currentTarget;
  var originalText = btn ? btn.textContent : '';
  if (btn) { btn.disabled = true; btn.textContent = '⏳ Envoi…'; }

  var fd = new FormData();
  fd.append('_csrf', '<?= htmlspecialchars(csrf_token()) ?>');
  fd.append('mode', 'selected');
  fd.append('type', type || 'newsletter');
  ids.forEach(id => fd.append('ids[]', id));

  fetch('invite_membre.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
      alert(d.msg || (d.ok ? 'Invitations envoyées !' : 'Erreur : ' + d.error));
      if (d.ok) location.reload();
    })
    .catch(e => alert('Erreur réseau : ' + e.message))
    .finally(() => { if (btn) { btn.disabled = false; btn.textContent = originalText; } });
}
</script>
</body>
</html>
