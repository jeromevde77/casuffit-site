<?php
// admin/members.php — Gestion des membres (recherche + pagination + fiche)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../membre/functions.php';
session_start(); requireAdmin();
require_once __DIR__ . '/../includes/csrf.php';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_verify();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmer_don'])) {
    $db->prepare("UPDATE member_dons SET statut='confirme' WHERE id=?")->execute([intval($_POST['don_id'])]);
    header('Location: members.php?msg=confirme'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_don'])) {
    $member_id = intval($_POST['member_id']);
    $montant   = floatval(str_replace(',','.', $_POST['montant'] ?? 0));
    $comm      = htmlspecialchars(trim($_POST['communication'] ?? ''), ENT_QUOTES,'UTF-8');
    $statut    = ($_POST['statut'] ?? '') === 'confirme' ? 'confirme' : 'en_attente';
    if ($montant > 0 && $member_id > 0) {
        $db->prepare("INSERT INTO member_dons (member_id,montant,communication,statut) VALUES (?,?,?,?)")
           ->execute([$member_id,$montant,$comm,$statut]);
    }
    header('Location: members.php?msg=don_ajoute'); exit;
}

// ── Recherche / filtres / pagination ─────────────────────────────────────
$page    = max(1,(int)($_GET['page'] ?? 1));
$q       = trim($_GET['q'] ?? '');
$filt_statut    = in_array($_GET['statut'] ?? 'actif',['actif','inactif','tous']) ? ($_GET['statut'] ?? 'actif') : 'actif';
$filt_incomplet = !empty($_GET['incomplet']);
$per_page = 25;

$sort_map = [
    'nom'        => 'm.nom, m.prenom',
    'email'      => 'm.email',
    'statut'     => 'm.statut',
    'commune'    => 'm.commune',
    'lang'       => 'm.lang',
    'total_dons' => 'total_dons',
    'nb_dons'    => 'nb_dons',
    'date'       => 'm.date_inscription',
];
$sort = array_key_exists($_GET['sort'] ?? '', $sort_map) ? $_GET['sort'] : 'date';
$dir  = ($_GET['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

$where=[]; $params=[];
if ($q !== '') {
    $like='%'.$q.'%';
    $where[]="(m.nom LIKE ? OR m.prenom LIKE ? OR m.email LIKE ? OR m.code_membre LIKE ? OR m.commune LIKE ? OR m.ogm LIKE ?)";
    $params=array_merge($params,[$like,$like,$like,$like,$like,$like]);
}
if ($filt_statut !== 'tous') { $where[]="m.statut=?"; $params[]=$filt_statut; }
if ($filt_incomplet) { $where[]="(TRIM(COALESCE(m.adresse,''))='' OR TRIM(COALESCE(m.code_postal,''))='')"; }
$sql_where=$where?'WHERE '.implode(' AND ',$where):'';

$stmt_cnt=$db->prepare("SELECT COUNT(*) FROM members m $sql_where");
$stmt_cnt->execute($params);
$count_total=(int)$stmt_cnt->fetchColumn();
$total_pages=max(1,(int)ceil($count_total/$per_page));
$page=min($page,$total_pages);
$offset=($page-1)*$per_page;

$stmt=$db->prepare("SELECT m.*,COUNT(d.id) as nb_dons,
    COALESCE(SUM(CASE WHEN d.statut='confirme' THEN d.montant ELSE 0 END),0) as total_dons
    FROM members m LEFT JOIN member_dons d ON d.member_id=m.id
    $sql_where GROUP BY m.id ORDER BY {$sort_map[$sort]} {$dir} LIMIT $per_page OFFSET $offset");
$stmt->execute($params); $membres=$stmt->fetchAll();

$membres_all=$db->query("SELECT id,prenom,nom,code_membre FROM members WHERE statut='actif' ORDER BY nom,prenom")->fetchAll();
$dons_recents=$db->query("SELECT d.*,m.prenom,m.nom,m.code_membre,m.ogm FROM member_dons d JOIN members m ON m.id=d.member_id ORDER BY d.date_don DESC LIMIT 20")->fetchAll();
$msg=$_GET['msg']??'';

// Compte global des adresses incomplètes (indépendant des filtres/pagination)
$nb_incomplets=(int)$db->query("SELECT COUNT(*) FROM members WHERE statut='actif' AND (TRIM(COALESCE(adresse,''))='' OR TRIM(COALESCE(code_postal,''))='')")->fetchColumn();

function su($ov=[]){
    global $q,$filt_statut,$filt_incomplet,$sort,$dir;
    $p=['q'=>$q,'statut'=>$filt_statut,'sort'=>$sort,'dir'=>$dir];
    if($filt_incomplet)$p['incomplet']='1';
    foreach($ov as $k=>$v)$p[$k]=$v;
    $out=[];foreach($p as $k=>$v){if($v!==''&&$v!==null&&$v!==false)$out[$k]=$v;}
    // Nettoyer les valeurs par défaut pour des URLs propres
    if(($out['sort']??'')==='date'&&($out['dir']??'')==='desc'){unset($out['sort']);unset($out['dir']);}
    return 'members.php?'.http_build_query($out);
}

function sort_th($label, $col, $extra_style=''){
    global $sort,$dir;
    $active = ($sort===$col);
    $next   = ($active && $dir==='asc') ? 'desc' : 'asc';
    $url    = su(['sort'=>$col,'dir'=>$next,'page'=>1]);
    $icon   = $active ? ($dir==='asc'?'▲':'▼') : '<span style="opacity:.2">↕</span>';
    $style  = 'color:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:4px;white-space:nowrap;'.($active?'color:#1673B2;font-weight:700;':'');
    return "<th $extra_style><a href=\"$url\" style=\"$style\">".htmlspecialchars($label)." $icon</a></th>";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<?php include __DIR__ . '/../includes/admin_pwa_head.php'; ?>
  <title>Membres — Admin Ça suffit !</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tom-select/2.3.1/css/tom-select.default.min.css">
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333}
    <?php include __DIR__ . '/../includes/admin_sidebar_css.php'; ?>
    .main{margin-left:240px;padding:28px;max-width:1300px}
    .page-title{font-size:1.2rem;font-weight:800;color:#0e3d6b;margin-bottom:16px}
    .card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,.06);margin-bottom:18px;overflow-x:auto}
    .card h3{font-size:.88rem;font-weight:700;color:#0e3d6b;margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid #eee}
    table{width:100%;border-collapse:collapse;font-size:.8rem;white-space:nowrap}
    th{text-align:left;padding:8px 10px;color:#888;font-weight:600;font-size:.68rem;text-transform:uppercase;border-bottom:2px solid #eee}
    td{padding:7px 10px;border-bottom:1px solid #f5f5f5;vertical-align:middle}
    .badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:.65rem;font-weight:700}
    .b-ok{background:#e8f8f0;color:#27ae60}.b-wait{background:#fff3e0;color:#ba7517}.b-off{background:#fde8e8;color:#c53030}
    .flash-ok{background:#e8f8f0;color:#276749;padding:11px 14px;border-radius:8px;margin-bottom:14px;font-size:.82rem;border-left:3px solid #48bb78}
    .btn{padding:6px 13px;border-radius:6px;font-size:.78rem;font-weight:600;cursor:pointer;border:1.5px solid transparent;font-family:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:5px;line-height:1;transition:all .15s}
    .btn-p{background:#1673B2;color:#fff;border-color:#1673B2}.btn-p:hover{background:#125a90;color:#fff;text-decoration:none}
    .btn-g{background:#f0f4f8;color:#555;border-color:#dde4ed}.btn-g:hover{background:#e0e8f0;color:#333;text-decoration:none}
    .btn-sm{padding:4px 9px;font-size:.7rem}
    .ogm{font-family:monospace;font-size:.72rem;color:#1673B2;font-weight:700}
    .form-inline{display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap}
    .form-inline label{font-size:.7rem;color:#888;display:block;margin-bottom:3px}
    input[type=number],input[type=text],input[type=search],select{padding:6px 10px;border:1.5px solid #dde4ed;border-radius:6px;font-size:.8rem;font-family:inherit;outline:none}
    /* Barre de recherche */
    .search-bar{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:16px}
    .search-bar input[type=search]{flex:1;min-width:200px;max-width:340px}
    /* Pagination */
    .pagination{display:flex;gap:4px;align-items:center;margin-top:14px;flex-wrap:wrap}
    .page-btn{padding:5px 11px;border-radius:6px;font-size:.78rem;font-weight:600;text-decoration:none;background:#f0f4f8;color:#555;border:1.5px solid #dde4ed;line-height:1}
    .page-btn:hover{background:#e0e8f0;text-decoration:none;color:#333}
    .page-btn.active{background:#1673B2;color:#fff;border-color:#1673B2}
    .page-info{font-size:.75rem;color:#888;margin-left:8px}
    /* Action buttons */
    .act{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:7px;border:1.5px solid;cursor:pointer;text-decoration:none;font-size:.82rem;transition:all .15s;flex-shrink:0;background:none}
    .act.view{color:#38a169;border-color:#c6f6d5;background:#f0fff4}.act.view:hover{background:#dcfce7;text-decoration:none}
    @media(max-width:768px){.main{margin-left:0!important;padding:16px!important;padding-top:68px!important}}
    /* Tom Select dans form */
    .ts-wrapper.single .ts-control{border:1.5px solid #dde4ed;border-radius:6px;font-size:.8rem;font-family:inherit;padding:6px 10px;min-height:unset;background:#fff;box-shadow:none}
    .ts-wrapper.single.focus .ts-control{border-color:#1673B2;box-shadow:none}
    .ts-dropdown{font-size:.78rem;font-family:inherit;border:1.5px solid #dde4ed;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,.1)}
    .ts-dropdown .option.selected,.ts-dropdown .option:hover{background:#e6f1fb;color:#0e3d6b}
  </style>
</head>
<body>
<?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

<div class="main">
  <div class="page-title">👥 Gestion des membres</div>

  <?php if ($msg==='confirme'): ?><div class="flash-ok">Don confirmé.</div>
  <?php elseif ($msg==='don_ajoute'): ?><div class="flash-ok">Don ajouté.</div><?php endif; ?>

  <!-- Barre de recherche + filtres -->
  <form method="GET" class="search-bar">
    <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Nom, prénom, email, code, commune, OGM…" style="min-width:260px">
    <select name="statut">
      <option value="actif"   <?= $filt_statut==='actif'  ?'selected':''?>>Actifs</option>
      <option value="inactif" <?= $filt_statut==='inactif'?'selected':''?>>Inactifs</option>
      <option value="tous"    <?= $filt_statut==='tous'   ?'selected':''?>>Tous les statuts</option>
    </select>
    <label style="display:flex;align-items:center;gap:5px;font-size:.8rem;color:#555;cursor:pointer">
      <input type="checkbox" name="incomplet" value="1" <?= $filt_incomplet?'checked':''?>> Adresse incomplète
    </label>
    <button type="submit" class="btn btn-p">Rechercher</button>
    <?php if ($q||$filt_statut!=='actif'||$filt_incomplet): ?>
      <a href="members.php" class="btn btn-g">✕ Effacer</a>
    <?php endif; ?>
  </form>

  <!-- Liste membres -->
  <div class="card">
    <h3 style="display:flex;justify-content:space-between;align-items:center">
      <span>
        <?php if ($q||$filt_statut!=='actif'||$filt_incomplet): ?>
          <?= $count_total ?> membre(s) trouvé(s) — page <?= $page ?>/<?= $total_pages ?>
        <?php else: ?>
          Membres inscrits — <?= $count_total ?> au total — page <?= $page ?>/<?= $total_pages ?>
        <?php endif; ?>
      </span>
    </h3>

    <?php if ($nb_incomplets > 0): ?>
    <div style="background:#fff8ee;border:1.5px solid #FF9900;border-radius:8px;padding:10px 14px;margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
      <div style="font-size:.82rem;color:#7a4400">
        📍 <strong><?= $nb_incomplets ?> membre(s)</strong> avec adresse incomplète —
        <a href="<?= su(['incomplet'=>'1','page'=>1]) ?>" style="color:#1673B2;font-weight:600">voir uniquement les incomplets</a>
      </div>
      <div style="display:flex;gap:8px">
        <button type="button" class="btn btn-g btn-sm" onclick="selectIncomplets()">Sélectionner les incomplets (page courante)</button>
        <button type="button" class="btn btn-sm" style="background:#FF9900;color:#fff;border-color:#FF9900" onclick="envoyerRappelAdresse()">✉ Envoyer rappel adresse</button>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($filt_incomplet): ?>
    <div style="background:#fff8ee;border:1.5px solid #FF9900;border-radius:8px;padding:8px 14px;margin-bottom:12px;font-size:.8rem;color:#555">
      Filtre actif : adresses incomplètes uniquement.
    </div>
    <?php endif; ?>

    <table>
      <tr>
        <th style="width:28px"></th>
        <?= sort_th('Membre',    'nom') ?>
        <?= sort_th('Email',     'email') ?>
        <?= sort_th('Statut',    'statut') ?>
        <?= sort_th('Commune',   'commune') ?>
        <?= sort_th('Langue',    'lang') ?>
        <?= sort_th('Dons / Total','total_dons') ?>
        <?= sort_th('Inscrit',   'date') ?>
        <th></th>
      </tr>
      <?php foreach ($membres as $m):
        $incomplet=(trim($m['adresse']??'')===''||trim($m['code_postal']??'')==='');
      ?>
      <tr<?= $incomplet?' style="background:#fff8ee"':''?>>
        <td style="width:28px"><?php if($incomplet):?><input type="checkbox" class="mbr-cb" value="<?=$m['id']?>" title="Adresse incomplète" <?= $filt_incomplet ? 'checked' : '' ?>><?php endif;?></td>
        <td>
          <span style="font-family:monospace;font-size:.7rem;font-weight:700;color:#1673B2;display:block"><?=htmlspecialchars($m['code_membre'])?></span>
          <span style="font-weight:600"><?=htmlspecialchars($m['prenom'].' '.$m['nom'])?></span>
        </td>
        <td style="font-size:.75rem"><?=htmlspecialchars($m['email'])?></td>
        <td>
          <span class="badge <?=$m['statut']==='actif'?'b-ok':'b-off'?>"><?=htmlspecialchars($m['statut'])?></span>
          <?php if($incomplet):?><span class="badge b-wait" style="margin-left:3px" title="Adresse incomplète">⚠️</span><?php endif;?>
          <?php if($m['newsletter']):?><span style="font-size:.75rem;margin-left:3px" title="Newsletter">📧</span><?php endif;?>
        </td>
        <td><?=htmlspecialchars($m['commune']??'—')?></td>
        <td><?php $lang=strtoupper($m['lang']??'fr');?>
          <span class="badge" style="background:<?=$lang==='NL'?'#e6f1fb':'#e8f0fb'?>;color:#1673B2"><?=$lang?></span>
        </td>
        <td><?php if($m['nb_dons']>0):?>
          <strong><?=number_format($m['total_dons'],0,',',' ')?> €</strong>
          <span style="font-size:.7rem;color:#aaa;margin-left:3px">(<?=$m['nb_dons']?>)</span>
        <?php else:?><span style="color:#ccc">—</span><?php endif;?></td>
        <td style="font-size:.72rem;color:#aaa;white-space:nowrap"><?=date('d/m/Y',strtotime($m['date_inscription']))?></td>
        <td><a href="member_detail.php?id=<?=$m['id']?>&back=<?=urlencode(su(['page'=>$page]))?>" class="act view" title="Voir la fiche">👁</a></td>
      </tr>
      <?php endforeach; ?>
    </table>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <?php if($page>1):?><a href="<?=su(['page'=>1])?>" class="page-btn" title="Première">«</a>
        <a href="<?=su(['page'=>$page-1])?>" class="page-btn">‹</a><?php endif;?>
      <?php
        $start=max(1,$page-3);$end=min($total_pages,$page+3);
        if($start>1)echo '<span class="page-info">…</span>';
        for($i=$start;$i<=$end;$i++):?><a href="<?=su(['page'=>$i])?>" class="page-btn <?=$i===$page?'active':''?>"><?=$i?></a><?php endfor;
        if($end<$total_pages)echo '<span class="page-info">…</span>';
      ?>
      <?php if($page<$total_pages):?><a href="<?=su(['page'=>$page+1])?>" class="page-btn">›</a>
        <a href="<?=su(['page'=>$total_pages])?>" class="page-btn" title="Dernière">»</a><?php endif;?>
      <span class="page-info"><?=$count_total?> membre(s) — <?=$per_page?>/page</span>
    </div>
    <?php endif; ?>
  </div>

  <!-- Ajouter un don manuellement -->
  <div class="card">
    <h3>Enregistrer un don (virement reçu)</h3>
    <form method="POST" class="form-inline"><?= csrf_field() ?>
      <div>
        <label>Membre</label>
        <select name="member_id" id="sel-membre-don" required style="min-width:220px">
          <option value="">— rechercher un membre —</option>
          <?php foreach($membres_all as $m):?>
          <option value="<?=$m['id']?>"><?=htmlspecialchars($m['code_membre'].' — '.$m['prenom'].' '.$m['nom'])?></option>
          <?php endforeach;?>
        </select>
      </div>
      <div><label>Montant (€)</label><input type="number" name="montant" step="0.01" min="1" placeholder="50" style="width:80px" required></div>
      <div><label>Communication OGM</label><input type="text" name="communication" placeholder="+++000/0000/00000+++" style="width:180px"></div>
      <div><label>Statut</label><select name="statut"><option value="confirme">Confirmé</option><option value="en_attente">En attente</option></select></div>
      <button type="submit" name="ajouter_don" class="btn btn-p">Enregistrer</button>
    </form>
  </div>

  <!-- Dons récents -->
  <div class="card">
    <h3>Dons récents (20 derniers)</h3>
    <table>
      <tr><th>Membre</th><th>Montant</th><th>Communication</th><th>Statut</th><th>Date</th><th>Action</th></tr>
      <?php foreach($dons_recents as $d):?>
      <tr>
        <td><a href="member_detail.php?id=<?=$d['member_id']?>" style="font-weight:600;color:#0e3d6b;text-decoration:none"><?=htmlspecialchars($d['prenom'].' '.$d['nom'])?></a>
            <div style="font-size:.68rem;color:#888;font-family:monospace"><?=htmlspecialchars($d['code_membre'])?></div></td>
        <td><strong><?=number_format($d['montant'],2,',',' ')?> €</strong></td>
        <td style="font-family:monospace;font-size:.72rem"><?=htmlspecialchars($d['communication']?:'—')?></td>
        <td><?=$d['statut']==='confirme'?'<span class="badge b-ok">Confirmé</span>':'<span class="badge b-wait">En attente</span>'?></td>
        <td style="font-size:.72rem;color:#aaa"><?=date('d/m/Y',strtotime($d['date_don']))?></td>
        <td><?php if($d['statut']!=='confirme'):?>
          <form method="POST" style="display:inline"><?=csrf_field()?>
            <input type="hidden" name="don_id" value="<?=$d['id']?>">
            <button type="submit" name="confirmer_don" class="btn btn-p btn-sm">Confirmer</button>
          </form><?php endif;?></td>
      </tr><?php endforeach;?>
    </table>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/tom-select/2.3.1/js/tom-select.complete.min.js"></script>
<script>
new TomSelect('#sel-membre-don',{placeholder:'— rechercher un membre —',create:false,maxOptions:500});

function selectIncomplets(){
  document.querySelectorAll('.mbr-cb').forEach(c=>c.checked=true);
}
function envoyerRappelAdresse(){
  var ids=Array.from(document.querySelectorAll('.mbr-cb:checked')).map(c=>c.value);
  if(!ids.length){alert('Sélectionnez au moins un membre.');return;}
  if(!confirm('Envoyer le rappel adresse à '+ids.length+' membre(s) ?'))return;
  var btn=event.currentTarget,orig=btn.textContent;
  btn.disabled=true;btn.textContent='⏳ Envoi…';
  var fd=new FormData();
  fd.append('_csrf','<?=htmlspecialchars(csrf_token())?>');
  ids.forEach(id=>fd.append('ids[]',id));
  fetch('rappel_adresse.php',{method:'POST',body:fd})
    .then(r=>r.json()).then(d=>{alert(d.msg||(d.ok?'Rappels envoyés !':'Erreur : '+d.error));if(d.ok)location.reload();})
    .catch(e=>alert('Erreur réseau : '+e.message))
    .finally(()=>{btn.disabled=false;btn.textContent=orig;});
}
</script>
</body>
</html>
