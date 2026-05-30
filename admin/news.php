<?php
require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();
require_once __DIR__ . '/../includes/csrf.php';
$db = getDB();

$msg = ''; $error = '';
$edit = null;

// Sauvegarder
if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_verify();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_news'])) {
    $id       = intval(isset($_POST['news_id']) ? $_POST['news_id'] : 0);
    $titre    = trim(isset($_POST['titre'])    ? $_POST['titre']    : '');
    $accroche = trim(isset($_POST['accroche']) ? $_POST['accroche'] : '');
    $contenu  = isset($_POST['contenu'])       ? $_POST['contenu']  : '';
    $image    = trim(isset($_POST['image_url'])? $_POST['image_url']: '');
    $statut   = in_array(isset($_POST['statut']) ? $_POST['statut'] : '', array('brouillon','publie','archive')) ? $_POST['statut'] : 'brouillon';
    $epingle  = isset($_POST['epingle']) ? 1 : 0;
    $deploye  = isset($_POST['deploye_defaut']) ? 1 : 0;
    $date_pub = trim(isset($_POST['date_publication']) ? $_POST['date_publication'] : '');

    // ── Champs NL ──
    $titre_nl    = trim($_POST['titre_nl']    ?? ''); $titre_nl    = $titre_nl    !== '' ? $titre_nl    : null;
    $accroche_nl = trim($_POST['accroche_nl'] ?? ''); $accroche_nl = $accroche_nl !== '' ? $accroche_nl : null;
    $contenu_nl  = trim($_POST['contenu_nl']  ?? ''); $contenu_nl  = $contenu_nl  !== '' ? $contenu_nl  : null;
    $nl_status   = in_array($_POST['nl_status'] ?? '', ['vide','auto','relu']) ? $_POST['nl_status'] : 'vide';

    // Vérifier si colonnes NL existent
    $hasNlNews = false;
    try { $c = $db->query("SHOW COLUMNS FROM news LIKE 'titre_nl'")->fetch(); $hasNlNews = !empty($c); }
    catch(Exception $e) { $hasNlNews = false; }
    // Vérifier si la colonne deploye_defaut existe
    $hasDeploye = false;
    try { $c = $db->query("SHOW COLUMNS FROM news LIKE 'deploye_defaut'")->fetch(); $hasDeploye = !empty($c); }
    catch(Exception $e) { $hasDeploye = false; }
    $date_pub = $date_pub ? date('Y-m-d H:i:s', strtotime($date_pub)) : date('Y-m-d H:i:s');

    if (empty($titre)) {
        $error = 'Le titre est obligatoire.';
    } else {
        if ($id > 0) {
            if ($hasNlNews) {
                $db->prepare("UPDATE news SET titre=?,accroche=?,contenu=?,image_url=?,statut=?,epingle=?,date_publication=?,titre_nl=?,accroche_nl=?,contenu_nl=?,nl_status=? WHERE id=?")
                   ->execute(array($titre,$accroche,$contenu,$image,$statut,$epingle,$date_pub,$titre_nl,$accroche_nl,$contenu_nl,$nl_status,$id));
            } else {
                $db->prepare("UPDATE news SET titre=?,accroche=?,contenu=?,image_url=?,statut=?,epingle=?,date_publication=? WHERE id=?")
                   ->execute(array($titre,$accroche,$contenu,$image,$statut,$epingle,$date_pub,$id));
            }
            $msg = '✅ Actualité mise à jour.';
        } else {
            if ($hasNlNews) {
                $db->prepare("INSERT INTO news (titre,accroche,contenu,image_url,statut,epingle,date_publication,titre_nl,accroche_nl,contenu_nl,nl_status,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
                   ->execute(array($titre,$accroche,$contenu,$image,$statut,$epingle,$date_pub,$titre_nl,$accroche_nl,$contenu_nl,$nl_status,ADMIN_USER));
            } else {
                $db->prepare("INSERT INTO news (titre,accroche,contenu,image_url,statut,epingle,date_publication,created_by) VALUES (?,?,?,?,?,?,?,?)")
                   ->execute(array($titre,$accroche,$contenu,$image,$statut,$epingle,$date_pub,ADMIN_USER));
            }
            $msg = '✅ Actualité créée.';
            if ($id == 0) { $id = (int)$db->lastInsertId(); }
        }
        // Mettre à jour deploye_defaut (colonne optionnelle)
        if ($hasDeploye && $id > 0) {
            $db->prepare("UPDATE news SET deploye_defaut=? WHERE id=?")->execute(array($deploye, $id));
        }
        header('Location: news.php?msg='.urlencode($msg)); exit;
    }
}

// Supprimer
if (isset($_GET['delete']) && is_numeric($_GET['delete']) && hash_equals(csrf_token(), $_GET['_csrf'] ?? '')) {
    $db->prepare("DELETE FROM news WHERE id=?")->execute(array(intval($_GET['delete'])));
    header('Location: news.php?msg='.urlencode('Actualité supprimée.')); exit;
}

// Toggle statut
if (isset($_GET['publish']) && is_numeric($_GET['publish'])) {
    $db->prepare("UPDATE news SET statut='publie', date_publication=COALESCE(date_publication,NOW()) WHERE id=?")->execute(array(intval($_GET['publish'])));
    header('Location: news.php?msg='.urlencode('Actualité publiée.')); exit;
}
if (isset($_GET['unpublish']) && is_numeric($_GET['unpublish'])) {
    $db->prepare("UPDATE news SET statut='brouillon' WHERE id=?")->execute(array(intval($_GET['unpublish'])));
    header('Location: news.php?msg='.urlencode('Actualité dépubliée.')); exit;
}
if (isset($_GET['pin']) && is_numeric($_GET['pin'])) {
    $db->prepare("UPDATE news SET epingle=1 WHERE id=?")->execute(array(intval($_GET['pin'])));
    header('Location: news.php?msg='.urlencode('Actualité épinglée.')); exit;
}

// Charger pour édition
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit = $db->prepare("SELECT * FROM news WHERE id=?");
    $edit->execute(array(intval($_GET['edit'])));
    $edit = $edit->fetch();
}

if (isset($_GET['msg'])) $msg = $_GET['msg'];

// Liste
$news_list = $db->query("SELECT * FROM news ORDER BY epingle DESC, date_creation DESC LIMIT 50")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<?php include __DIR__ . '/../includes/admin_pwa_head.php'; ?>
<title>Actualités — Admin Ça suffit !</title>
<style>
<?php include __DIR__ . '/../includes/admin_sidebar_css.php'; ?>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333}
  .wrap{margin-left:240px;height:100vh;display:flex;flex-direction:column;overflow:hidden}

  @media (max-width: 768px) {
    .wrap { margin-left: 0; padding-top: 52px; height: auto; }
    .news-editor-wrap { grid-template-columns: 1fr; height: auto; }
    .preview-col { display: none; }
    .meta-row { grid-template-columns: 1fr; }
    .ecol-head { position: sticky; top: 0; z-index: 10; }
    .save-bar { position: sticky; bottom: 0; z-index: 10; }
    .palette-grid { grid-template-columns: 1fr 1fr; }
    .view-toggle { display: none !important; }
    .ni-actions { flex-wrap: wrap; gap: 4px; }
  }

  /* Layout éditeur */
  .news-editor-wrap { display: grid; grid-template-columns: 1fr 1fr; gap: 0; height: calc(100vh - 60px); overflow: hidden; }
  .news-editor-wrap.view-edit    { grid-template-columns: 1fr; }
  .news-editor-wrap.view-edit    .preview-col { display: none !important; }
  .news-editor-wrap.view-preview { grid-template-columns: 1fr; }
  .news-editor-wrap.view-preview .editor-col  { display: none !important; }
  /* Toggle */
  .view-toggle{display:flex;gap:3px;background:rgba(255,255,255,.15);border-radius:6px;padding:3px;flex-shrink:0}
  .vt-btn{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border:none;border-radius:4px;font-size:.72rem;font-weight:600;cursor:pointer;color:rgba(255,255,255,.7);background:transparent;transition:all .15s;font-family:inherit}
  .vt-btn.active{background:rgba(255,255,255,.25);color:#fff}
  .vt-btn:hover{color:#fff}
  .ecol-head-vt{background:#1673B2 !important;}
  .ecol-head-vt h3{color:#fff !important;}
  .editor-col { display: flex; flex-direction: column; overflow: hidden; border-right: 1px solid #e0e8f0; }
  .preview-col { display: flex; flex-direction: column; overflow: hidden; background: #f5f8fc; }
  .ecol-head { padding: 10px 16px; background: #fff; border-bottom: 1px solid #e0e8f0; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
  .ecol-head h3 { font-size: .82rem; font-weight: 700; color: #0e3d6b; margin: 0; }
  .ecol-body { flex: 1; overflow-y: auto; padding: 16px; }

  /* Formulaire */
  .frow { margin-bottom: 12px; }
  .frow label { display: block; font-size: .72rem; font-weight: 700; color: #0e3d6b; margin-bottom: 4px; text-transform: uppercase; letter-spacing: .04em; }
  .frow input[type=text], .frow input[type=datetime-local], .frow textarea, .frow select {
    width: 100%; padding: 8px 10px; border: 1.5px solid #c8dff0; border-radius: 6px;
    font-size: .82rem; font-family: inherit; color: #1a1a2e; background: #fff;
    box-sizing: border-box; transition: border .15s;
  }
  .frow input:focus, .frow textarea:focus, .frow select:focus { outline: none; border-color: #1673B2; }
  .frow textarea { resize: vertical; min-height: 80px; font-family: 'Courier New', monospace; font-size: .78rem; }
  .frow textarea.contenu-area { min-height: 260px; }

  /* Palette */
  .palette { margin-top: 10px; padding-top: 10px; border-top: 1px solid #eee; }
  .palette-title { font-size: .65rem; font-weight: 700; color: #888; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 7px; }
  .palette-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 5px; }
  .sb { padding: 6px 8px; border-radius: 5px; font-size: .7rem; cursor: pointer; text-align: left; border: 1.5px solid transparent; font-family: inherit; font-weight: 500; transition: all .15s; }
  .sb:hover { transform: translateY(-1px); box-shadow: 0 2px 6px rgba(0,0,0,.12); }
  .sb-titre  { background: #fff8e1; border-color: #FF9900; color: #a05000; }
  .sb-texte  { background: #e6f1fb; border-color: #1673B2; color: #0e3d6b; }
  .sb-cadreO { background: #FF9900; border-color: #e68800; color: #fff; }
  .sb-cadreB { background: #e6f1fb; border-color: #b5d4f4; color: #1673B2; }
  .sb-cadreV { background: #e8f5e9; border-color: #a5d6a7; color: #1b5e20; }
  .sb-alerte { background: #fff8ee; border-color: #FF9900; color: #a05000; }
  .sb-chiffre{ background: #f3e5f5; border-color: #ce93d8; color: #6a1b9a; }
  .sb-arg    { background: #fff3e0; border-color: #ffcc80; color: #e65100; }
  .sb-liste  { background: #f5f5f5; border-color: #bdbdbd; color: #424242; }
  .sb-bq     { background: #fce4ec; border-color: #f48fb1; color: #880e4f; }

  /* Aperçu */
  .preview-inner { background: #fff; border-radius: 8px; padding: 24px; box-shadow: 0 2px 10px rgba(0,0,0,.06); max-width: 680px; margin: 0 auto; font-family: "Helvetica Neue", Arial, sans-serif; line-height: 1.6; color: #333; }
  .preview-inner h2 { font-size: 1.2rem; color: #0e3d6b; margin: 0 0 6px; }
  .preview-inner .accroche-preview { color: #555; font-style: italic; font-size: .9rem; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #eee; }
  .preview-inner p { margin-bottom: 12px; }
  .preview-inner .orange.section-title, .preview-inner .orange { color: #FF9900; font-weight: 400; font-size: 1.05rem; margin: 20px 0 8px; padding-bottom: 4px; border-bottom: 1px solid #c8dff0; }
  .preview-inner .cadre-bleu { padding: 12px 16px; background: #e8f3fb; border-left: 4px solid #1673B2; color: #1673B2; margin: 12px 0; }
  .preview-inner .cadre-orange { padding: 12px 16px; background: #FF9900; color: #fff; margin: 12px 0; }
  .preview-inner .cadre-vert { padding: 12px 16px; background: #e8f5e9; border-left: 4px solid #2e7d32; margin: 12px 0; }
  .preview-inner .alerte { padding: 12px 16px; border: 2px solid #FF9900; border-left: 5px solid #FF9900; background: #fff8ee; margin: 12px 0; }
  .preview-inner blockquote { border-left: 4px solid #FF9900; padding: 10px 16px; background: #fff8ee; margin: 14px 0; font-style: italic; color: #555; }
  .preview-inner ul, .preview-inner ol { margin: 8px 0 14px 24px; }

  /* Métadonnées */
  .meta-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
  .status-btns { display: flex; gap: 8px; margin-top: 4px; }
  .status-btn { padding: 6px 12px; border-radius: 5px; border: 1.5px solid; font-size: .75rem; font-weight: 600; cursor: pointer; font-family: inherit; transition: all .15s; }
  .status-btn.publie { background: #e8f5e9; border-color: #2e7d32; color: #2e7d32; }
  .status-btn.publie.active { background: #2e7d32; color: #fff; }
  .status-btn.brouillon { background: #f5f5f5; border-color: #999; color: #555; }
  .status-btn.brouillon.active { background: #757575; color: #fff; }
  .status-btn.archive { background: #fce4ec; border-color: #c62828; color: #c62828; }
  .status-btn.archive.active { background: #c62828; color: #fff; }
  .save-bar { padding: 10px 16px; background: #fff; border-top: 1px solid #e0e8f0; display: flex; gap: 8px; flex-shrink: 0; }
  .btn-save { padding: 8px 20px; background: #1673B2; color: #fff; border: none; border-radius: 6px; font-size: .82rem; font-weight: 700; cursor: pointer; }
  .btn-save:hover { background: #0e5a8a; }
  .btn-cancel { padding: 8px 14px; background: #f0f0f0; color: #555; border: none; border-radius: 6px; font-size: .82rem; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
  .epingle-check { display: flex; align-items: center; gap: 6px; font-size: .8rem; color: #555; cursor: pointer; }

  /* Liste news */
  .news-list-wrap { padding: 0; }
  .news-item-row { display: flex; align-items: center; gap: 10px; padding: 10px 16px; border-bottom: 1px solid #eef2f7; background: #fff; transition: background .1s; }
  .news-item-row:hover { background: #f5f8fc; }
  .ni-status { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
  .ni-status.publie { background: #2e7d32; }
  .ni-status.brouillon { background: #999; }
  .ni-status.archive { background: #c62828; }
  .ni-pin { font-size: .8rem; }
  .ni-titre { flex: 1; font-size: .82rem; font-weight: 600; color: #0e3d6b; }
  .ni-date { font-size: .72rem; color: #888; }
  .ni-actions { display: flex; gap: 4px; }
  
  
  
  
  

  /* Drawer aperçu mobile */
  .apercu-drawer { display:none }
  @media (max-width: 768px) {
    .btn-apercu-mobile { display: inline-flex !important; }
    .apercu-drawer {
      display: block !important;
      position: fixed; inset: 0; z-index: 500;
      pointer-events: none; opacity: 0;
      transition: opacity .2s;
    }
    .apercu-drawer.open { pointer-events: all; opacity: 1; }
    .apercu-overlay { position: absolute; inset: 0; background: rgba(0,0,0,.5); }
    .apercu-sheet {
      position: absolute; bottom: 0; left: 0; right: 0;
      height: 85vh; background: #fff;
      border-radius: 16px 16px 0 0;
      display: flex; flex-direction: column;
      transform: translateY(100%);
      transition: transform .3s ease;
    }
    .apercu-drawer.open .apercu-sheet { transform: translateY(0); }
    .apercu-sheet-head {
      display: flex; align-items: center; justify-content: space-between;
      padding: 14px 18px; border-bottom: 1px solid #eee; flex-shrink: 0;
    }
    .apercu-sheet-head h4 { font-size:.88rem; font-weight:700; color:#0e3d6b; }
    .apercu-close-btn {
      background:#f0f0f0; border:none; border-radius:50%;
      width:28px; height:28px; cursor:pointer; font-size:.9rem;
    }
    .apercu-sheet-body { flex:1; overflow-y:auto; padding:16px; }
  }
.act-btn{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;border:1.5px solid;cursor:pointer;text-decoration:none;transition:all .15s;background:none;font-family:inherit;flex-shrink:0}
.act-btn.edit{color:#4a5568;border-color:#e2e8f0;background:#f7f8fa}
.act-btn.edit:hover{background:#edf2f7;border-color:#cbd5e0;color:#2d3748;text-decoration:none}
.act-btn.del{color:#e53e3e;border-color:#fed7d7;background:#fff5f5}
.act-btn.del:hover{background:#fee2e2;border-color:#fc8181;text-decoration:none}
.act-btn.view{color:#38a169;border-color:#c6f6d5;background:#f0fff4}
.act-btn.view:hover{background:#dcfce7;text-decoration:none}
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
.eform-foot,.pedit-foot,.save-bar{display:flex;gap:8px;align-items:center;flex-wrap:wrap;padding:10px 16px;border-top:1px solid #eee;background:#fafbfc;flex-shrink:0}
/* Éditeur WYSIWYG contenteditable */
#wysiwyg-editor, #wysiwyg-editor-nl {
  min-height: 260px; padding: 14px; border: 1px solid #c8dff0; border-radius: 0 0 6px 6px;
  background: #fff; font-family: "Helvetica Neue",Arial,sans-serif; font-size: .88rem;
  line-height: 1.7; color: #333; outline: none; cursor: text;
}
#wysiwyg-editor:focus { border-color: #1673B2; }
#wysiwyg-editor-nl:focus { border-color: #FF9900; }
#wysiwyg-toolbar { background: #f8fafc; border: 1px solid #c8dff0; border-bottom: none;
  border-radius: 6px 6px 0 0; padding: 6px 10px; display: flex; gap: 4px; flex-wrap: wrap; align-items: center; }
.wt-btn { background: #fff; border: 1px solid #dde; border-radius: 4px; padding: 3px 8px;
  cursor: pointer; font-size: .82rem; color: #333; min-width: 28px; text-align: center; }
.wt-btn:hover { background: #e8f3fb; border-color: #1673B2; }
.wt-btn.active { background: #1673B2; color: #fff; border-color: #1673B2; }
.wt-sep { width: 1px; background: #dde; margin: 0 4px; align-self: stretch; }
/* Styles du site DANS l'éditeur — FR et NL */
#wysiwyg-editor .cadre-bleu,    #wysiwyg-editor-nl .cadre-bleu   { padding: 12px 16px; background: #e8f3fb; border-left: 4px solid #1673B2; color: #1673B2; margin: 10px 0; border-radius: 4px; display: block; }
#wysiwyg-editor .cadre-orange,  #wysiwyg-editor-nl .cadre-orange { padding: 12px 16px; background: #FF9900; color: #fff; margin: 10px 0; border-radius: 4px; display: block; }
#wysiwyg-editor .cadre-vert,    #wysiwyg-editor-nl .cadre-vert   { padding: 12px 16px; background: #e8f5e9; border-left: 4px solid #2e7d32; margin: 10px 0; border-radius: 4px; display: block; }
#wysiwyg-editor .alerte,        #wysiwyg-editor-nl .alerte       { background: #fff8ee; border: 2px solid #FF9900; padding: 12px 16px; border-radius: 6px; margin: 10px 0; display: block; }
#wysiwyg-editor .al-titre,      #wysiwyg-editor-nl .al-titre     { font-weight: 700; color: #FF9900; margin-bottom: 6px; display: block; }
#wysiwyg-editor .orange,        #wysiwyg-editor-nl .orange       { color: #FF9900; font-weight: 600; }
#wysiwyg-editor .section-title, #wysiwyg-editor-nl .section-title { color: #FF9900; font-weight: 400; font-size: 1.05rem; margin: 20px 0 8px; padding-bottom: 4px; border-bottom: 1px solid #c8dff0; }
#wysiwyg-editor .content-text,  #wysiwyg-editor-nl .content-text { color: #1673B2; margin-bottom: 10px; font-size: 95%; line-height: 1.65; }
#wysiwyg-editor .ac-item,       #wysiwyg-editor-nl .ac-item      { background: #f0f6fb; border-left: 3px solid #1673B2; padding: 10px 14px; margin: 8px 0; }
#wysiwyg-editor .lettre-intro,  #wysiwyg-editor-nl .lettre-intro  { background: #0e3d6b; color: #fff; padding: 16px 20px; margin-bottom: 16px; display: block; }
#wysiwyg-editor .lettre-intro p,#wysiwyg-editor-nl .lettre-intro p { color: #fff; margin: 0; line-height: 1.55; }
#wysiwyg-editor .citation-box,  #wysiwyg-editor-nl .citation-box  { background: #f5f5f5; border-left: 4px solid #1673B2; padding: 12px 16px; margin: 10px 0; display: block; }
#wysiwyg-editor .citation-box p,#wysiwyg-editor-nl .citation-box p { font-style: italic; color: #1673B2; margin: 0 0 4px; }
#wysiwyg-editor .actions-grid,  #wysiwyg-editor-nl .actions-grid  { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px,1fr)); gap: 10px; margin: 10px 0; }
#wysiwyg-editor .action-card,   #wysiwyg-editor-nl .action-card   { background: #e8f3fb; border-top: 3px solid #1673B2; padding: 12px 10px; }
#wysiwyg-editor .ac-num,        #wysiwyg-editor-nl .ac-num        { font-size: 1.3rem; font-weight: 700; color: #7ec8e3; }
#wysiwyg-editor .ac-titre,      #wysiwyg-editor-nl .ac-titre      { font-weight: 600; color: #0e3d6b; font-size: .88rem; }
#wysiwyg-editor .ac-text,       #wysiwyg-editor-nl .ac-text       { font-size: .78rem; color: #555; }
#wysiwyg-editor .signature,     #wysiwyg-editor-nl .signature     { background: #e8f3fb; border-left: 3px solid #1673B2; padding: 12px 16px; margin-top: 16px; font-size: .88rem; color: #1673B2; display: block; }
#wysiwyg-editor .cadre-vert .cv-titre, #wysiwyg-editor-nl .cadre-vert .cv-titre { font-weight: 600; color: #1b5e20; font-size: .78rem; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 6px; display: block; }
#wysiwyg-editor h2,             #wysiwyg-editor-nl h2             { color: #FF9900; font-weight: 600; font-size: 1.2rem; border-bottom: 1px solid #c8dff0; padding-bottom: 4px; margin: 16px 0 8px; }
#wysiwyg-editor h3,             #wysiwyg-editor-nl h3             { color: #FF9900; font-weight: 600; font-size: 1rem; border-bottom: 1px solid #c8dff0; padding-bottom: 4px; margin: 16px 0 8px; }
#wysiwyg-editor ul,             #wysiwyg-editor-nl ul             { padding-left: 20px; margin: 8px 0; }
#wysiwyg-editor ol,             #wysiwyg-editor-nl ol             { padding-left: 20px; margin: 8px 0; }
#wysiwyg-editor blockquote,     #wysiwyg-editor-nl blockquote     { border-left: 4px solid #FF9900; padding: 8px 14px; background: #fff8ee; margin: 10px 0; font-style: italic; }
#wysiwyg-editor p,              #wysiwyg-editor-nl p              { margin-bottom: 8px; }
#wysiwyg-editor strong,         #wysiwyg-editor-nl strong         { font-weight: 700; }
#wysiwyg-editor a,              #wysiwyg-editor-nl a              { color: #1673B2; }
#wysiwyg-editor img,            #wysiwyg-editor-nl img            { max-width: 100%; height: auto; display: block; margin: 10px 0; border-radius: 4px; }
#wysiwyg-editor .chiffre-val,   #wysiwyg-editor-nl .chiffre-val  { font-size: 1.5rem; font-weight: 700; color: #FF9900; }
#wysiwyg-editor .chiffre-label, #wysiwyg-editor-nl .chiffre-label { font-size: 70%; color: #555; display: block; margin-top: 2px; }
#wysiwyg-editor .timeline,      #wysiwyg-editor-nl .timeline     { position: relative; padding-left: 24px; margin: 14px 0; }
#wysiwyg-editor .tl-item,       #wysiwyg-editor-nl .tl-item      { position: relative; margin-bottom: 14px; }
#wysiwyg-editor .tl-date,       #wysiwyg-editor-nl .tl-date      { font-weight: 600; font-size: 85%; color: #1673B2; }
#wysiwyg-editor .tl-text,       #wysiwyg-editor-nl .tl-text      { font-size: 90%; color: #555; line-height: 1.5; }
#wysiwyg-editor .sep,           #wysiwyg-editor-nl .sep          { border: none; border-top: 1px solid #c8dff0; margin: 20px 0; display: block; }
#wysiwyg-editor .divider,       #wysiwyg-editor-nl .divider      { display: flex; align-items: center; gap: 10px; margin: 16px 0; color: #555; font-size: 72%; text-transform: uppercase; letter-spacing: .08em; }
.ql-toolbar.ql-snow { border: 1px solid #c8dff0; border-radius: 6px 6px 0 0; background: #f8fafc; }
.ql-container.ql-snow { border: 1px solid #c8dff0; border-radius: 0 0 6px 6px; }

/* ── Palette de styles flottante ──────────────────────────────────── */
#style-palette {
  display: none; position: fixed; z-index: 9999;
  background: #fff; border: 1px solid #c8dff0; border-radius: 10px;
  box-shadow: 0 8px 32px rgba(0,0,0,.18); padding: 14px;
  width: 560px; max-width: 96vw; max-height: 80vh; overflow-y: auto;
}
#style-palette.open { display: block; }
#style-palette h4 { font-size: .72rem; font-weight: 700; color: #888;
  text-transform: uppercase; letter-spacing: .06em; margin: 0 0 10px; }
.sp-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.sp-item { border: 1.5px solid #e0e8f0; border-radius: 7px; padding: 10px 12px;
  cursor: pointer; transition: border-color .15s, box-shadow .15s; background: #fff; }
.sp-item:hover { border-color: #1673B2; box-shadow: 0 2px 8px rgba(22,115,178,.15); }
.sp-item-label { font-size: .65rem; font-weight: 700; color: #888;
  text-transform: uppercase; letter-spacing: .05em; margin-bottom: 5px; }
/* Previews */
.sp-prev-cadreB { background: #e8f3fb; border-left: 4px solid #1673B2;
  color: #1673B2; padding: 6px 10px; border-radius: 3px; font-size: .78rem; }
.sp-prev-cadreO { background: #FF9900; color: #fff;
  padding: 6px 10px; border-radius: 3px; font-size: .78rem; }
.sp-prev-cadreV { background: #e8f5e9; border-left: 4px solid #2e7d32;
  padding: 6px 10px; border-radius: 3px; font-size: .78rem; }
.sp-prev-alerte { background: #fff8ee; border: 2px solid #FF9900;
  padding: 6px 10px; border-radius: 4px; font-size: .78rem; color: #7a4400; }
.sp-prev-lettre { background: #0e3d6b; color: #fff;
  padding: 7px 10px; border-radius: 3px; font-size: .78rem; }
.sp-prev-citation { background: #f5f5f5; border-left: 4px solid #1673B2;
  padding: 6px 10px; border-radius: 0 3px 3px 0; font-size: .78rem; font-style: italic; color: #1673B2; }
.sp-prev-bq { border-left: 4px solid #FF9900; background: #fff8ee;
  padding: 6px 10px; font-size: .78rem; color: #7a4400; }
.sp-prev-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 4px; }
.sp-prev-grid-item { background: #e8f3fb; border-top: 2px solid #1673B2;
  padding: 4px 6px; font-size: .65rem; color: #0e3d6b; }
.sp-prev-liste { font-size: .78rem; color: #333; padding: 4px 10px; }
.sp-prev-titre { color: #FF9900; font-weight: 700; font-size: .9rem;
  border-bottom: 1px solid #c8dff0; padding-bottom: 3px; }
.sp-prev-texteB { color: #1673B2; font-size: .78rem; }
.sp-prev-chiffre { display: flex; gap: 6px; align-items: center; }
.sp-prev-chiffre-val { font-size: 1.2rem; font-weight: 700; color: #1673B2; }
.sp-prev-chiffre-lbl { font-size: .65rem; color: #888; }
.sp-prev-sign { background: #e8f3fb; border-left: 3px solid #1673B2;
  padding: 5px 10px; font-size: .75rem; color: #1673B2; }
.sp-close { position: absolute; top: 10px; right: 12px; background: none;
  border: none; font-size: 1.1rem; cursor: pointer; color: #aaa; line-height: 1; }
.sp-close:hover { color: #333; }
.sp-section { margin-top: 12px; }

</style>

</head>
<body>
<?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>
<div class="wrap">

<?php if ($msg): ?>
  <div style="padding:10px 20px;background:#e8f5e9;color:#2e7d32;font-weight:600;font-size:.82rem;border-bottom:1px solid #c8e6c9">
    <?= htmlspecialchars($msg) ?>
  </div>
<?php endif; ?>
<?php if ($error): ?>
  <div style="padding:10px 20px;background:#fce4ec;color:#c62828;font-weight:600;font-size:.82rem;border-bottom:1px solid #f48fb1">
    <?= htmlspecialchars($error) ?>
  </div>
<?php endif; ?>

<?php if ($edit || isset($_GET['new'])): ?>
<!-- ═══ ÉDITEUR ═══ -->
<form method="POST" id="news-form"><?= csrf_field() ?>
<input type="hidden" name="news_id" value="<?= $edit ? intval($edit['id']) : 0 ?>">
<input type="hidden" name="save_news" value="1">
<input type="hidden" name="statut" id="f-statut" value="<?= $edit ? htmlspecialchars($edit['statut']) : 'brouillon' ?>">

<div class="news-editor-wrap view-edit">

  <!-- Colonne gauche : formulaire -->
  <div class="editor-col">
    <div class="ecol-head ecol-head-vt">
      <h3 style="color:#fff"><?= $edit ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Modifier l\'actualité' : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Nouvelle actualité' ?></h3>
      <div style="display:flex;align-items:center;gap:10px">
        <div class="view-toggle">
          <button type="button" class="vt-btn vt-edit active" onclick="setView('edit')" title="Éditeur">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Éditer
          </button>
          <button type="button" class="vt-btn vt-preview" onclick="setView('preview')" title="Aperçu">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            Aperçu
          </button>
        </div>
        <a href="news.php" style="font-size:.72rem;color:rgba(255,255,255,.8);text-decoration:none">← Liste</a>
      </div>
    </div>
    <div class="ecol-body">

      <div class="frow">
        <label>Titre *</label>
        <input type="text" name="titre" id="f-titre" value="<?= $edit ? htmlspecialchars($edit['titre']) : '' ?>" placeholder="Ex: Victoire ! L'audience est fixée au..." required>
      </div>

      <div class="frow">
        <label>Accroche <span style="font-weight:400;color:#aaa">(résumé affiché dans la liste)</span></label>
        <textarea name="accroche" id="f-accroche" rows="2" placeholder="2-3 phrases résumant l'actualité..."><?= $edit ? htmlspecialchars($edit['accroche']) : '' ?></textarea>
      </div>

      <div class="frow">
        <label>Contenu complet</label>
        <!-- Toolbar WYSIWYG -->
        <div id="wysiwyg-toolbar">
          <button type="button" class="wt-btn" onclick="fmt('bold')" title="Gras"><b>G</b></button>
          <button type="button" class="wt-btn" onclick="fmt('italic')" title="Italique"><i>I</i></button>
          <button type="button" class="wt-btn" onclick="fmt('underline')" title="Souligné"><u>S</u></button>
          <div class="wt-sep"></div>
          <button type="button" class="wt-btn" onclick="fmtBlock('h2')" title="Titre H2">H2</button>
          <button type="button" class="wt-btn" onclick="fmtBlock('h3')" title="Titre H3">H3</button>
          <button type="button" class="wt-btn" onclick="fmtBlock('p')" title="Paragraphe">¶</button>
          <div class="wt-sep"></div>
          <button type="button" class="wt-btn" onclick="fmt('insertUnorderedList')" title="Liste à puces">• —</button>
          <button type="button" class="wt-btn" onclick="fmt('insertOrderedList')" title="Liste numérotée">1.</button>
          <div class="wt-sep"></div>
          <button type="button" class="wt-btn" onclick="insertLink()" title="Lien">🔗</button>
          <button type="button" class="wt-btn" onclick="fmt('removeFormat')" title="Effacer style">Tx</button>
          <button type="button" class="wt-btn" onclick="removeBloc()" title="Supprimer le style du bloc" style="color:#c0392b;font-weight:700">✕ Bloc</button>
          <div class="wt-sep"></div>
          <button type="button" class="wt-btn" onclick="openPalette(this)" style="background:#1673B2;color:#fff;padding:3px 12px;font-weight:700;min-width:auto" title="Insérer un style">＋ Style</button>
        </div>
        <!-- Éditeur contenteditable — préserve toutes les classes CSS -->
        <div id="wysiwyg-editor" contenteditable="true"
             oninput="syncEditor()"><?= $edit ? $edit['contenu'] : '' ?></div>
        <!-- Textarea caché pour soumission -->
        <textarea name="contenu" id="f-contenu" style="display:none"><?= $edit ? htmlspecialchars($edit['contenu']) : '' ?></textarea>
      </div><!-- /frow -->

      <div class="meta-row">
        <div class="frow">
          <label>Image (URL)</label>
          <input type="text" name="image_url" value="<?= $edit ? htmlspecialchars($edit['image_url']) : '' ?>" placeholder="https://... ou /medias/image.jpg">
        </div>
        <div class="frow">
          <label>Date de publication</label>
          <input type="datetime-local" name="date_publication" value="<?= $edit && $edit['date_publication'] ? date('Y-m-d\TH:i', strtotime($edit['date_publication'])) : date('Y-m-d\TH:i') ?>">
        </div>
      </div>

      <div class="frow">
        <label>Statut</label>
        <div class="status-btns">
          <button type="button" class="status-btn brouillon <?= (!$edit || $edit['statut']==='brouillon') ? 'active' : '' ?>" onclick="setStatut('brouillon',this)">Brouillon</button>
          <button type="button" class="status-btn publie   <?= ($edit && $edit['statut']==='publie')   ? 'active' : '' ?>" onclick="setStatut('publie',this)">Publié</button>
          <button type="button" class="status-btn archive  <?= ($edit && $edit['statut']==='archive')  ? 'active' : '' ?>" onclick="setStatut('archive',this)">Archivé</button>
        </div>
      </div>

      <div class="frow">
        <label class="epingle-check">
          <input type="checkbox" name="epingle" value="1" <?= $edit && $edit['epingle'] ? 'checked' : '' ?>>
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><line x1="12" y1="17" x2="12" y2="22"/><path d="M5 17h14v-1.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V6h1a2 2 0 0 0 0-4H8a2 2 0 0 0 0 4h1v4.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24Z"/></svg>
          Épingler cette actualité en haut (à la une)
        </label>
        <div style="font-size:.72rem;color:#999;margin-left:24px;margin-top:2px">Place l'actualité tout en haut de la liste.</div>
      </div>

      <div class="frow">
        <label class="epingle-check">
          <input type="checkbox" name="deploye_defaut" value="1" <?= $edit && !empty($edit['deploye_defaut']) ? 'checked' : '' ?>>
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><polyline points="6 9 12 15 18 9"/></svg>
          Afficher dépliée par défaut
        </label>
        <div style="font-size:.72rem;color:#999;margin-left:24px;margin-top:2px">L'actualité s'affiche déjà ouverte (contenu visible) sans clic. Indépendant de l'épinglage.</div>
      </div>

      <!-- Champs NL soumis avec le form (alimentés par syncNl() depuis le bloc NL ci-dessous) -->
      <input type="hidden" name="titre_nl"    id="h-titre_nl"    value="<?= htmlspecialchars($edit['titre_nl']    ?? '') ?>">
      <input type="hidden" name="accroche_nl" id="h-accroche_nl" value="<?= htmlspecialchars($edit['accroche_nl'] ?? '') ?>">
      <input type="hidden" name="contenu_nl"  id="h-contenu_nl"  value="<?= htmlspecialchars($edit['contenu_nl']  ?? '') ?>">
      <input type="hidden" name="nl_status"   id="h-nl_status"   value="<?= htmlspecialchars($edit['nl_status']   ?? 'vide') ?>">

<?php if ($edit): ?>
<?php
$nl_status_n = $edit['nl_status'] ?? 'vide';
$has_nl_n    = !empty($edit['titre_nl']) || !empty($edit['contenu_nl']);
$badges_n = ['vide'=>'⚪ Vide','auto'=>'🤖 Traduction auto','relu'=>'✅ Relu humain'];
$colors_n = ['vide'=>'#999','auto'=>'#d97706','relu'=>'#27ae60'];
?>
      <details class="nl-block" <?= $has_nl_n ? 'open' : '' ?> style="margin-top:16px;background:#fff8ee;border:1.5px solid #FF9900;border-radius:8px;padding:10px 14px">
        <summary style="cursor:pointer;font-weight:700;color:#0e3d6b;display:flex;justify-content:space-between;align-items:center">
          <span>🇳🇱 Version néerlandaise (NL)</span>
          <span style="font-size:.7rem;color:<?= $colors_n[$nl_status_n] ?>;font-weight:600"><?= $badges_n[$nl_status_n] ?></span>
        </summary>
        <div style="margin-top:12px">
          <label>Titre (NL)</label>
          <input type="text" id="v-titre_nl" value="<?= htmlspecialchars($edit['titre_nl'] ?? '') ?>" placeholder="Laisser vide pour utiliser le titre FR" oninput="syncNl()" style="width:100%;padding:8px 10px;border:1px solid #f0c060;border-radius:5px;font-size:.88rem;box-sizing:border-box;margin-bottom:10px">
          <label>Accroche (NL)</label>
          <textarea id="v-accroche_nl" rows="2" placeholder="Résumé en néerlandais..." oninput="syncNl()" style="width:100%;padding:8px;border:1px solid #f0c060;border-radius:5px;font-size:.88rem;box-sizing:border-box;margin-bottom:10px"><?= htmlspecialchars($edit['accroche_nl'] ?? '') ?></textarea>
          <label>Contenu (NL)</label>
          <div style="background:#fff8ee;border:1px solid #f0c060;border-bottom:none;border-radius:6px 6px 0 0;padding:6px 10px;display:flex;gap:4px;flex-wrap:wrap;align-items:center">
            <button type="button" class="wt-btn" onclick="fmtNl('bold')"><b>G</b></button>
            <button type="button" class="wt-btn" onclick="fmtNl('italic')"><i>I</i></button>
            <button type="button" class="wt-btn" onclick="fmtNl('underline')"><u>S</u></button>
            <div class="wt-sep"></div>
            <button type="button" class="wt-btn" onclick="fmtBlockNl('h2')">H2</button>
            <button type="button" class="wt-btn" onclick="fmtBlockNl('h3')">H3</button>
            <button type="button" class="wt-btn" onclick="fmtBlockNl('p')">¶</button>
            <div class="wt-sep"></div>
            <button type="button" class="wt-btn" onclick="fmtNl('insertUnorderedList')">• —</button>
            <button type="button" class="wt-btn" onclick="fmtNl('insertOrderedList')">1.</button>
            <div class="wt-sep"></div>
            <button type="button" class="wt-btn" onclick="insertLinkNl()">🔗</button>
            <button type="button" class="wt-btn" onclick="fmtNl('removeFormat')">Tx</button>
            <button type="button" class="wt-btn" onclick="removeBlocNl()" style="color:#c0392b;font-weight:700">✕ Bloc</button>
            <div class="wt-sep"></div>
            <button type="button" class="wt-btn" onclick="openPaletteNl(this)" style="background:#FF9900;color:#fff;padding:3px 12px;font-weight:700">＋ Style</button>
          </div>
          <div id="wysiwyg-editor-nl" contenteditable="true" oninput="syncNl()"
               style="min-height:180px;max-height:40vh;overflow-y:auto;padding:14px;border:1px solid #f0c060;border-radius:0 0 6px 6px;background:#fffdf5;font-family:'Helvetica Neue',Arial,sans-serif;font-size:.88rem;line-height:1.7;color:#333;outline:none;cursor:text"></div>
          <div style="font-size:.63rem;color:#aaa;margin-top:2px">Laisser vide → fallback automatique sur le contenu FR</div>
          <div style="display:flex;gap:14px;margin-top:10px;align-items:center;flex-wrap:wrap">
            <div>
              <label style="display:block;margin-bottom:4px;font-size:.8rem">État</label>
              <select id="v-nl_status" onchange="syncNl()" style="min-width:200px;padding:6px;border:1px solid #ddd;border-radius:5px">
                <option value="vide"  <?= $nl_status_n==='vide' ?'selected':'' ?>>⚪ Vide / brouillon</option>
                <option value="auto"  <?= $nl_status_n==='auto' ?'selected':'' ?>>🤖 Auto (à relire)</option>
                <option value="relu"  <?= $nl_status_n==='relu' ?'selected':'' ?>>✅ Relu par humain</option>
              </select>
            </div>
            <button type="button" onclick="autoTranslateNews(<?= $edit['id'] ?>)" style="background:#1673B2;color:#fff;border:none;padding:8px 14px;border-radius:6px;cursor:pointer;font-weight:600;font-size:.82rem;margin-top:18px">🤖 Traduire automatiquement</button>
          </div>
        </div>
      </details>
<?php endif; ?>

    </div><!-- /ecol-body -->
    <div class="save-bar">
      <button type="submit" class="btn-save"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Sauvegarder</button>
      <a href="news.php" class="btn-cancel">Annuler</a>
    
      <button type="button" class="btn-apercu-mobile" onclick="openApercu()" style="display:none;padding:8px 14px;background:#f0f0f0;border:none;border-radius:6px;font-size:.82rem;font-weight:600;cursor:pointer"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg> Aperçu</button>
    </div>
  </div><!-- /editor-col -->

  <!-- Colonne droite : aperçu -->
  <div class="preview-col">
    <div class="ecol-head ecol-head-vt">
      <h3 style="color:#fff;display:inline-flex;align-items:center;gap:6px"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg> Aperçu</h3>
      <div class="view-toggle">
        <button type="button" class="vt-btn vt-edit" onclick="setView('edit')" title="Éditeur">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          Éditer
        </button>
        <button type="button" class="vt-btn vt-preview active" onclick="setView('preview')" title="Aperçu">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          Aperçu
        </button>
      </div>
    </div>
    <div class="ecol-body">
      <div class="preview-inner">
        <h2 id="preview-titre"><?= $edit ? htmlspecialchars($edit['titre']) : 'Titre de l\'actualité' ?></h2>
        <div class="accroche-preview" id="preview-accroche"><?= $edit ? htmlspecialchars($edit['accroche']) : 'Accroche...' ?></div>
        <div id="preview"><?= $edit ? $edit['contenu'] : '<p style="color:#aaa;text-align:center;padding:20px">Aperçu...</p>' ?></div>
      </div>
    </div>
  </div>

</div><!-- /news-editor-wrap -->
</form>

<?php if ($edit): ?>
<script>
function syncNl() {
  document.getElementById('h-titre_nl').value    = document.getElementById('v-titre_nl')?.value    || '';
  document.getElementById('h-accroche_nl').value = document.getElementById('v-accroche_nl')?.value || '';
  var edNl = document.getElementById('wysiwyg-editor-nl');
  document.getElementById('h-contenu_nl').value  = edNl ? edNl.innerHTML : '';
  document.getElementById('h-nl_status').value   = document.getElementById('v-nl_status')?.value   || 'vide';
}
(function() {
  var edNl = document.getElementById('wysiwyg-editor-nl');
  var h = document.getElementById('h-contenu_nl');
  if (edNl && h && h.value) edNl.innerHTML = h.value;
  syncNl();
})();
function fmtNl(cmd,val){ document.getElementById('wysiwyg-editor-nl')?.focus(); document.execCommand(cmd,false,val||null); syncNl(); }
function fmtBlockNl(tag){ document.getElementById('wysiwyg-editor-nl')?.focus(); document.execCommand('formatBlock',false,tag); syncNl(); }
function insertLinkNl(){ var u=prompt('URL :'); if(u) fmtNl('createLink',u); }
function removeBlocNl(){
  var ed=document.getElementById('wysiwyg-editor-nl'); if(!ed) return;
  ed.focus(); var sel=window.getSelection(); if(!sel.rangeCount) return;
  var n=sel.getRangeAt(0).commonAncestorContainer;
  while(n&&n!==ed){ if(n.nodeType===1&&n.className){n.className='';n.removeAttribute('style');break;} n=n.parentNode; }
  syncNl();
}
function openPaletteNl(btn){ window._paletteTargetNl=true; openPalette(btn); }
function autoTranslateNews(newsId) {
  if (!confirm('Traduire automatiquement en néerlandais ? Remplace le contenu NL actuel.')) return;
  var btn = event.target; btn.textContent='⏳ Traduction…'; btn.disabled=true;
  fetch('/admin/translate_auto.php?news_id='+newsId, {method:'POST'})
    .then(r=>r.json())
    .then(d=>{
      if (d.ok) {
        document.getElementById('v-titre_nl').value    = d.titre_nl    || '';
        document.getElementById('v-accroche_nl').value = d.accroche_nl || '';
        var edNl = document.getElementById('wysiwyg-editor-nl');
        if (edNl) edNl.innerHTML = d.contenu_nl || '';
        document.getElementById('v-nl_status').value = 'auto';
        syncNl();
        btn.textContent='✅ Traduit'; setTimeout(()=>{btn.textContent='🤖 Re-traduire';btn.disabled=false;},2500);
      } else { alert('Erreur : '+(d.error||'inconnue')); btn.textContent='🤖 Traduire automatiquement'; btn.disabled=false; }
    })
    .catch(e=>{ alert('Erreur réseau'); btn.textContent='🤖 Traduire automatiquement'; btn.disabled=false; });
}
</script>
<?php endif; ?>

<?php else: ?>
<!-- ═══ LISTE ═══ -->
<div style="padding:16px 20px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #e0e8f0;background:#fff">
  <h2 style="margin:0;font-size:1rem;color:#0e3d6b"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px;margin-right:5px"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/><path d="M18 14h-8M15 18h-5M10 6h8v4h-8V6Z"/></svg>Actualités</h2>
  <a href="news.php?new=1" style="padding:7px 16px;background:#1673B2;color:#fff;border-radius:6px;font-size:.78rem;font-weight:700;text-decoration:none"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Nouvelle actualité</a>
</div>
<div class="news-list-wrap">
  <?php if (empty($news_list)): ?>
    <div style="padding:40px;text-align:center;color:#aaa">Aucune actualité.</div>
  <?php else: ?>
    <?php foreach ($news_list as $n): ?>
    <div class="news-item-row">
      <div class="ni-status <?= htmlspecialchars($n['statut']) ?>"></div>
      <div class="ni-pin"><?= $n['epingle'] ? '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#FF9900" stroke-width="2"><line x1="12" y1="17" x2="12" y2="22"/><path d="M5 17h14v-1.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V6h1a2 2 0 0 0 0-4H8a2 2 0 0 0 0 4h1v4.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24Z"/></svg>' : '' ?></div>
      <div class="ni-titre"><?= htmlspecialchars($n['titre']) ?></div>
      <div class="ni-date"><?= date('d/m/Y', strtotime($n['date_creation'])) ?></div>
      <div style="font-size:.7rem;padding:2px 7px;border-radius:10px;font-weight:600;
        <?= $n['statut']==='publie' ? 'background:#e8f5e9;color:#2e7d32' : ($n['statut']==='archive' ? 'background:#fce4ec;color:#c62828' : 'background:#f5f5f5;color:#888') ?>">
        <?= $n['statut'] ?>
      </div>
      <div class="ni-actions">
        <a href="news.php?edit=<?= $n['id'] ?>" class="act-btn edit" title="Éditer"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></a>
        <?php if ($n['statut'] !== 'publie'): ?>
          <a href="news.php?publish=<?= $n['id'] ?>" class="act-btn view" title="Publier"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></a>
        <?php else: ?>
          <a href="news.php?unpublish=<?= $n['id'] ?>" class="act-btn" title="Dépublier" style="color:#999;border-color:#e2e8f0;background:#f7f8fa"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></a>
        <?php endif; ?>
        <a href="news.php?delete=<?= $n['id'] ?>&_csrf=<?= htmlspecialchars(csrf_token()) ?>" class="act-btn del" title="Supprimer" onclick="return confirm('Supprimer cette actualité ?')"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg></a>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php endif; ?>
</div><!-- /wrap -->

<script>
function setView(mode) {
  var w = document.querySelector('.news-editor-wrap');
  if (!w) return;
  w.classList.remove('view-edit','view-preview');
  w.classList.add('view-' + mode);
  document.querySelectorAll('.vt-edit').forEach(function(b){ b.classList.toggle('active', mode==='edit'); });
  document.querySelectorAll('.vt-preview').forEach(function(b){ b.classList.toggle('active', mode==='preview'); });
  try { localStorage.setItem('news_view', mode); } catch(e) {}
}
document.addEventListener('DOMContentLoaded', function() {
  if (window.innerWidth >= 768) {
    var hasEdit = window.location.search.includes('edit=') || window.location.search.includes('new=');
    var sv = 'edit';
    if (!hasEdit) {
      try { var s = localStorage.getItem('news_view'); if (s==='edit'||s==='preview') sv=s; } catch(e) {}
    }
    setView(sv);
  }
});
var T = {
  texte:   '<p class="content-text">Texte informatif...</p>\n',
  cadreO:  '<div class="cadre-orange"><strong>Message important</strong></div>\n',
  cadreB:  '<div class="cadre-bleu">Information en bleu.</div>\n',
  cadreV:  '<div class="cadre-vert"><div class="cv-titre">Points positifs</div><ul><li>Point 1</li><li>Point 2</li></ul></div>\n',
  alerte:  '<div class="alerte"><div class="al-titre">Titre alerte</div><p>Description...</p></div>\n',
  chiffre: '<div style="display:inline-block;text-align:center;margin:8px 16px 8px 0"><span class="chiffre-val">320</span><span class="chiffre-label">avions/jour</span></div>\n',
  arg:     '<div class="ac-item"><p class="ac-text">Votre argument clé.</p></div>\n',
  liste:   '<ul>\n  <li>Élément 1</li>\n  <li>Élément 2</li>\n</ul>\n',
  bq:      '<blockquote>Citation mise en valeur.</blockquote>\n',
};

function ins(k) {
  var ed = document.getElementById('wysiwyg-editor');
  if (!ed) return;
  ed.focus();
  document.execCommand('insertHTML', false, T[k]);
  syncEditor();
}

function maj() {
  var v = document.getElementById('f-contenu');
  if (!v) return;
  document.getElementById('preview').innerHTML = v.value || '<p style="color:#aaa;text-align:center;padding:20px">Aperçu...</p>';
  var t = document.getElementById('f-titre');
  if (t) document.getElementById('preview-titre').textContent = t.value || 'Titre de l\'actualité';
  var a = document.getElementById('f-accroche');
  if (a) document.getElementById('preview-accroche').textContent = a.value || 'Accroche...';
}

function setStatut(s, btn) {
  document.getElementById('f-statut').value = s;
  document.querySelectorAll('.status-btn').forEach(function(b){ b.classList.remove('active'); });
  btn.classList.add('active');
}

var titre = document.getElementById('f-titre');
if (titre) titre.addEventListener('input', maj);
var accroche = document.getElementById('f-accroche');
if (accroche) accroche.addEventListener('input', maj);

maj();
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  if (window.innerWidth < 768) {
    var btn = document.querySelector('.btn-retour-mobile');
    if (btn) btn.style.display = 'flex';
    var btnDesktop = document.querySelector('.btn-liste-desktop');
    if (btnDesktop) btnDesktop.style.display = 'none';
  }
});

function openApercu() {
  syncApercu();
  var d = document.getElementById('apercu-drawer');
  d.style.display = 'block';
  requestAnimationFrame(function(){ d.classList.add('open'); });
}
function closeApercu() {
  var d = document.getElementById('apercu-drawer');
  d.classList.remove('open');
  setTimeout(function(){ d.style.display='none'; }, 300);
}
function syncApercu() {
  var src = document.querySelector('.apanel-body, .apanel-inner, #preview');
  var dest = document.getElementById('apercu-sheet-body');
  if (src && dest) dest.innerHTML = src.innerHTML;
}
</script>

<script>
// ── Éditeur WYSIWYG contenteditable ──────────────────────────────────────
function syncEditor() {
  var ed = document.getElementById('wysiwyg-editor');
  document.getElementById('f-contenu').value = ed.innerHTML;
  if (typeof maj === 'function') maj();
}

function fmt(cmd, val) {
  document.getElementById('wysiwyg-editor').focus();
  document.execCommand(cmd, false, val || null);
  syncEditor();
}

function fmtBlock(tag) {
  document.getElementById('wysiwyg-editor').focus();
  document.execCommand('formatBlock', false, tag);
  syncEditor();
}

function insertLink() {
  var url = prompt('URL du lien :');
  if (url) fmt('createLink', url);
}

// Sync avant soumission du formulaire
document.getElementById('news-form').addEventListener('submit', function() {
  var ed = document.getElementById('wysiwyg-editor');
  document.getElementById('f-contenu').value = ed.innerHTML;
});


// ── Palette flottante ─────────────────────────────────────────────────
var BLOCS = {
  cadreB:    '<div class="cadre-bleu">Information en bleu.</div>',
  cadreO:    '<div class="cadre-orange"><strong>Message important</strong></div>',
  cadreV:    '<div class="cadre-vert"><div class="cv-titre">Points positifs</div><ul><li>Point 1</li><li>Point 2</li></ul></div>',
  alerte:    '<div class="alerte"><div class="al-titre">⚠ Attention</div><p>Description...</p></div>',
  lettre:    '<div class="lettre-intro"><p>Chers membres,<br>votre message ici.</p></div>',
  citation:  '<div class="citation-box"><p>« Votre citation »</p><a href="#">— Source</a></div>',
  bq:        '<blockquote>Citation mise en valeur.</blockquote>',
  signature: '<div class="signature">Cordialement,<strong>L\'équipe Ça suffit !</strong></div>',
  grid:      '<div class="actions-grid"><div class="action-card"><div class="ac-num">01</div><div class="ac-titre">Titre</div><div class="ac-text">Description courte.</div></div><div class="action-card"><div class="ac-num">02</div><div class="ac-titre">Titre</div><div class="ac-text">Description courte.</div></div><div class="action-card"><div class="ac-num">03</div><div class="ac-titre">Titre</div><div class="ac-text">Description courte.</div></div></div>',
  liste:     '<ul><li>Élément 1</li><li>Élément 2</li><li>Élément 3</li></ul>',
  chiffre:   '<div style="display:inline-block;text-align:center;margin:8px 16px 8px 0"><span class="chiffre-val">320</span><span class="chiffre-label">avions/jour</span></div>',
  titre:     '<h3 class="orange section-title">Votre titre de section</h3>',
  texte:     '<p class="content-text">Texte informatif...</p>',
};

function insBloc(k) {
  if (!k || !BLOCS[k]) return;
  var edId = window._paletteTargetNl ? 'wysiwyg-editor-nl' : 'wysiwyg-editor';
  var ed = document.getElementById(edId);
  if (!ed) return;
  ed.focus();

  var sel = window.getSelection();
  var SCLASSES = ['cadre-bleu','cadre-orange','cadre-vert','alerte','lettre-intro',
    'citation-box','signature','actions-grid','ac-item','content-text'];

  // ── Cas 1 : texte sélectionné → envelopper dans le style ─────────────
  if (sel && sel.rangeCount > 0 && !sel.isCollapsed) {
    var range = sel.getRangeAt(0);
    if (ed.contains(range.commonAncestorContainer)) {
      var frag = range.extractContents();
      var tmp = document.createElement('div');
      tmp.appendChild(frag);
      var selectedHTML = tmp.innerHTML;
      var wrapper = document.createElement('div');
      wrapper.innerHTML = BLOCS[k];
      var newEl = wrapper.firstElementChild;
      if (newEl) {
        newEl.innerHTML = selectedHTML;
        range.insertNode(newEl);
        sel.removeAllRanges();
        if (edId === 'wysiwyg-editor-nl') syncNl(); else syncEditor();
        if (typeof closePalette === 'function') closePalette();
        window._paletteTargetNl = false;
        return;
      }
    }
  }

  // ── Cas 2 : curseur dans un bloc stylé → remplacer sa classe ─────────
  if (sel && sel.rangeCount > 0) {
    var node = sel.getRangeAt(0).commonAncestorContainer;
    if (node.nodeType === 3) node = node.parentNode;
    while (node && node !== ed) {
      if (node.nodeType === 1) {
        for (var i = 0; i < SCLASSES.length; i++) {
          if (node.classList && node.classList.contains(SCLASSES[i])) {
            var t2 = document.createElement('div');
            t2.innerHTML = BLOCS[k];
            var n2 = t2.firstElementChild;
            if (n2) {
              node.className = n2.className;
              if (edId === 'wysiwyg-editor-nl') syncNl(); else syncEditor();
              if (typeof closePalette==='function') closePalette();
              window._paletteTargetNl = false;
              return;
            }
          }
        }
      }
      node = node.parentNode;
    }
  }

  // ── Cas 3 : curseur libre → insérer un bloc vide ─────────────────────
  document.execCommand('insertHTML', false, BLOCS[k]);
  if (edId === 'wysiwyg-editor-nl') syncNl(); else syncEditor();
  window._paletteTargetNl = false;
}

function openPalette(btn) {
  var p = document.getElementById('style-palette');
  if (!p) return;
  if (p.classList.contains('open')) { p.classList.remove('open'); return; }
  var r = btn.getBoundingClientRect();
  var maxH = Math.round(window.innerHeight * 0.75);
  p.style.maxHeight = maxH + 'px';
  p.style.overflowY = 'auto';
  var left = Math.min(Math.max(10, r.right - 560 + window.scrollX), window.innerWidth - 570);
  var top;
  // Ouvrir vers le bas si assez de place, sinon vers le haut
  if (r.bottom + 10 + maxH <= window.innerHeight) {
    top = r.bottom + 6 + window.scrollY;
  } else {
    top = Math.max(60, r.top - maxH - 6 + window.scrollY);
  }
  p.style.top  = top + 'px';
  p.style.left = left + 'px';
  p.classList.add('open');
}

function closePalette() {
  var p = document.getElementById('style-palette');
  if (p) p.classList.remove('open');
}

document.addEventListener('click', function(e) {
  var p = document.getElementById('style-palette');
  if (!p || !p.classList.contains('open')) return;
  if (!p.contains(e.target) && !e.target.closest('[onclick*="openPalette"]')) {
    p.classList.remove('open');
  }
});
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closePalette();
});


function removeBloc() {
  var ed = document.getElementById('wysiwyg-editor');
  if (!ed) return;
  var SCLASSES = ['cadre-bleu','cadre-orange','cadre-vert','alerte','lettre-intro',
    'citation-box','signature','actions-grid','ac-item','content-text','orange','section-title'];
  var sel = window.getSelection();
  if (!sel || sel.rangeCount === 0) return;
  var node = sel.getRangeAt(0).commonAncestorContainer;
  if (node.nodeType === 3) node = node.parentNode;
  while (node && node !== ed) {
    if (node.nodeType === 1) {
      for (var i = 0; i < SCLASSES.length; i++) {
        if (node.classList && node.classList.contains(SCLASSES[i])) {
          // Déballer : remplacer l'élément par ses enfants
          var frag = document.createDocumentFragment();
          while (node.firstChild) frag.appendChild(node.firstChild);
          node.parentNode.replaceChild(frag, node);
          syncEditor();
          if (typeof closePalette === 'function') closePalette();
          return;
        }
      }
    }
    node = node.parentNode;
  }
  // Pas de bloc stylé — effacer le formatage inline
  document.execCommand('removeFormat');
  syncEditor();
}

</script>

<div id="style-palette">
  <button class="sp-close" onclick="closePalette()">✕</button>
  <h4>Choisir un style</h4>

  <div class="sp-grid">
    <div class="sp-item" onclick="removeBloc()" style="grid-column:1/-1;border-color:#e74c3c;background:#fff5f5">
      <div class="sp-item-label" style="color:#c0392b">✕ Supprimer le style du bloc</div>
      <div style="font-size:.75rem;color:#888">Déballe le bloc et garde le contenu brut</div>
    </div>

    <div class="sp-item" onclick="insBloc('cadreB'); closePalette()">
      <div class="sp-item-label">Cadre bleu</div>
      <div class="sp-prev-cadreB">Information mise en avant</div>
    </div>

    <div class="sp-item" onclick="insBloc('cadreO'); closePalette()">
      <div class="sp-item-label">Cadre orange</div>
      <div class="sp-prev-cadreO">Message important</div>
    </div>

    <div class="sp-item" onclick="insBloc('cadreV'); closePalette()">
      <div class="sp-item-label">Cadre vert</div>
      <div class="sp-prev-cadreV">Points positifs</div>
    </div>

    <div class="sp-item" onclick="insBloc('alerte'); closePalette()">
      <div class="sp-item-label">⚠ Alerte</div>
      <div class="sp-prev-alerte"><strong>Attention</strong> — Description</div>
    </div>

    <div class="sp-item" onclick="insBloc('lettre'); closePalette()">
      <div class="sp-item-label">Lettre intro</div>
      <div class="sp-prev-lettre">Chers membres, ...</div>
    </div>

    <div class="sp-item" onclick="insBloc('citation'); closePalette()">
      <div class="sp-item-label">Citation</div>
      <div class="sp-prev-citation">« Citation mise en valeur »</div>
    </div>

    <div class="sp-item" onclick="insBloc('bq'); closePalette()">
      <div class="sp-item-label">Blockquote</div>
      <div class="sp-prev-bq">Citation courte</div>
    </div>

    <div class="sp-item" onclick="insBloc('signature'); closePalette()">
      <div class="sp-item-label">Signature</div>
      <div class="sp-prev-sign">L&apos;équipe Ça suffit !<br><small>Contact : ...</small></div>
    </div>

    <div class="sp-item" style="grid-column:1/-1" onclick="insBloc('grid'); closePalette()">
      <div class="sp-item-label">Grille d'actions (3 colonnes)</div>
      <div class="sp-prev-grid">
        <div class="sp-prev-grid-item"><strong>01</strong><br>Titre</div>
        <div class="sp-prev-grid-item"><strong>02</strong><br>Titre</div>
        <div class="sp-prev-grid-item"><strong>03</strong><br>Titre</div>
      </div>
    </div>

    <div class="sp-item" onclick="insBloc('liste'); closePalette()">
      <div class="sp-item-label">Liste à puces</div>
      <div class="sp-prev-liste">• Élément 1<br>• Élément 2</div>
    </div>

    <div class="sp-item" onclick="insBloc('chiffre'); closePalette()">
      <div class="sp-item-label">Chiffre clé</div>
      <div class="sp-prev-chiffre">
        <span class="sp-prev-chiffre-val">320</span>
        <span class="sp-prev-chiffre-lbl">avions/jour</span>
      </div>
    </div>

    <div class="sp-item" onclick="insBloc('titre'); closePalette()">
      <div class="sp-item-label">Titre de section</div>
      <div class="sp-prev-titre">Votre titre</div>
    </div>

    <div class="sp-item" onclick="insBloc('texte'); closePalette()">
      <div class="sp-item-label">Texte bleu</div>
      <div class="sp-prev-texteB">Texte informatif en bleu</div>
    </div>

  </div>
</div>

</body>