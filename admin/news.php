<?php
require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();
$db = getDB();

$msg = ''; $error = '';
$edit = null;

// Sauvegarder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_news'])) {
    $id       = intval(isset($_POST['news_id']) ? $_POST['news_id'] : 0);
    $titre    = trim(isset($_POST['titre'])    ? $_POST['titre']    : '');
    $accroche = trim(isset($_POST['accroche']) ? $_POST['accroche'] : '');
    $contenu  = isset($_POST['contenu'])       ? $_POST['contenu']  : '';
    $image    = trim(isset($_POST['image_url'])? $_POST['image_url']: '');
    $statut   = in_array(isset($_POST['statut']) ? $_POST['statut'] : '', array('brouillon','publie','archive')) ? $_POST['statut'] : 'brouillon';
    $epingle  = isset($_POST['epingle']) ? 1 : 0;
    $date_pub = trim(isset($_POST['date_publication']) ? $_POST['date_publication'] : '');
    $date_pub = $date_pub ? date('Y-m-d H:i:s', strtotime($date_pub)) : date('Y-m-d H:i:s');

    if (empty($titre)) {
        $error = 'Le titre est obligatoire.';
    } else {
        if ($id > 0) {
            $db->prepare("UPDATE news SET titre=?,accroche=?,contenu=?,image_url=?,statut=?,epingle=?,date_publication=? WHERE id=?")
               ->execute(array($titre,$accroche,$contenu,$image,$statut,$epingle,$date_pub,$id));
            $msg = '✅ Actualité mise à jour.';
        } else {
            $db->prepare("INSERT INTO news (titre,accroche,contenu,image_url,statut,epingle,date_publication,created_by) VALUES (?,?,?,?,?,?,?,?)")
               ->execute(array($titre,$accroche,$contenu,$image,$statut,$epingle,$date_pub,ADMIN_USER));
            $msg = '✅ Actualité créée.';
        }
        header('Location: news.php?msg='.urlencode($msg)); exit;
    }
}

// Supprimer
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
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
<title>Actualités — Admin ça suffit !</title>
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
#wysiwyg-editor {
  min-height: 260px; padding: 14px; border: 1px solid #c8dff0; border-radius: 0 0 6px 6px;
  background: #fff; font-family: "Helvetica Neue",Arial,sans-serif; font-size: .88rem;
  line-height: 1.7; color: #333; outline: none; cursor: text;
}
#wysiwyg-editor:focus { border-color: #1673B2; }
#wysiwyg-toolbar { background: #f8fafc; border: 1px solid #c8dff0; border-bottom: none;
  border-radius: 6px 6px 0 0; padding: 6px 10px; display: flex; gap: 4px; flex-wrap: wrap; align-items: center; }
.wt-btn { background: #fff; border: 1px solid #dde; border-radius: 4px; padding: 3px 8px;
  cursor: pointer; font-size: .82rem; color: #333; min-width: 28px; text-align: center; }
.wt-btn:hover { background: #e8f3fb; border-color: #1673B2; }
.wt-btn.active { background: #1673B2; color: #fff; border-color: #1673B2; }
.wt-sep { width: 1px; background: #dde; margin: 0 4px; align-self: stretch; }
/* Styles du site DANS l'éditeur */
#wysiwyg-editor .cadre-bleu   { padding: 12px 16px; background: #e8f3fb; border-left: 4px solid #1673B2; color: #1673B2; margin: 10px 0; border-radius: 4px; display: block; }
#wysiwyg-editor .cadre-orange { padding: 12px 16px; background: #FF9900; color: #fff; margin: 10px 0; border-radius: 4px; display: block; }
#wysiwyg-editor .cadre-vert   { padding: 12px 16px; background: #e8f5e9; border-left: 4px solid #2e7d32; margin: 10px 0; border-radius: 4px; display: block; }
#wysiwyg-editor .alerte       { background: #fff8ee; border: 2px solid #FF9900; padding: 12px 16px; border-radius: 6px; margin: 10px 0; display: block; }
#wysiwyg-editor .al-titre     { font-weight: 700; color: #FF9900; margin-bottom: 6px; display: block; }
#wysiwyg-editor .orange, #wysiwyg-editor .section-title { color: #FF9900; font-weight: 600; }
#wysiwyg-editor h3            { color: #FF9900; font-weight: 600; font-size: 1rem; border-bottom: 1px solid #c8dff0; padding-bottom: 4px; margin: 16px 0 8px; }
#wysiwyg-editor .content-text { color: #1673B2; }
#wysiwyg-editor .ac-item      { background: #f0f6fb; border-left: 3px solid #1673B2; padding: 10px 14px; margin: 8px 0; }
#wysiwyg-editor ul, #wysiwyg-editor ol { padding-left: 20px; }
#wysiwyg-editor blockquote    { border-left: 4px solid #FF9900; padding: 8px 14px; background: #fff8ee; margin: 10px 0; }
.ql-toolbar.ql-snow { border: 1px solid #c8dff0; border-radius: 6px 6px 0 0; background: #f8fafc; }
.ql-container.ql-snow { border: 1px solid #c8dff0; border-radius: 0 0 6px 6px; }
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
<form method="POST" id="news-form">
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
        <div class="palette">
          <div class="palette-title">Insérer un bloc :</div>
          <div class="palette-grid">
            <button type="button" class="sb sb-titre"    onclick="ins('titre')">📌 Titre section</button>
            <button type="button" class="sb sb-texte"    onclick="ins('texte')">📝 Texte bleu</button>
            <button type="button" class="sb sb-cadreO"   onclick="ins('cadreO')">🟠 Cadre orange</button>
            <button type="button" class="sb sb-cadreB"   onclick="ins('cadreB')">🔵 Cadre bleu</button>
            <button type="button" class="sb sb-cadreV"   onclick="ins('cadreV')">🟢 Cadre vert</button>
            <button type="button" class="sb sb-alerte"   onclick="ins('alerte')">⚠️ Alerte</button>
            <button type="button" class="sb sb-chiffre"  onclick="ins('chiffre')">🔢 Chiffre clé</button>
            <button type="button" class="sb sb-liste"    onclick="ins('liste')">📋 Liste</button>
            <button type="button" class="sb sb-bq"       onclick="ins('bq')">💬 Citation</button>
            <button type="button" class="sb sb-arg"      onclick="ins('arg')">💡 Argument</button>
          </div>
        </div>
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
        </div>
        <!-- Éditeur contenteditable — préserve toutes les classes CSS -->
        <div id="wysiwyg-editor" contenteditable="true"
             oninput="syncEditor()"><?= $edit ? $edit['contenu'] : '' ?></div>
        <!-- Textarea caché pour soumission -->
        <textarea name="contenu" id="f-contenu" style="display:none"><?= $edit ? htmlspecialchars($edit['contenu']) : '' ?></textarea>
      </div>

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
          Épingler cette actualité en haut
        </label>
      </div>

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
        <a href="news.php?delete=<?= $n['id'] ?>" class="act-btn del" title="Supprimer" onclick="return confirm('Supprimer cette actualité ?')"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg></a>
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
</script>
</body>