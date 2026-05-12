<?php
// admin/pages.php — Éditeur de pages + gestion des widgets
require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();
$db = getDB();

$msg = ''; $error = '';
$edit_page = null;

// ── Sauvegarder une page ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_page'])) {
    $id             = intval(isset($_POST['page_id']) ? $_POST['page_id'] : 0);
    $slug           = preg_replace('/[^a-z0-9_-]/', '', strtolower(trim(isset($_POST['slug']) ? $_POST['slug'] : '')));
    $titre          = htmlspecialchars(trim(isset($_POST['titre']) ? $_POST['titre'] : ''), ENT_QUOTES, 'UTF-8');
    $contenu        = isset($_POST['contenu']) ? $_POST['contenu'] : '';
    $meta           = htmlspecialchars(trim(isset($_POST['meta_description']) ? $_POST['meta_description'] : ''), ENT_QUOTES, 'UTF-8');
    $ordre          = intval(isset($_POST['ordre']) ? $_POST['ordre'] : 0);
    $visible        = isset($_POST['visible']) ? 1 : 0;
    $menu           = isset($_POST['dans_menu']) ? 1 : 0;
    $icone          = htmlspecialchars(trim(isset($_POST['icone']) ? $_POST['icone'] : ''), ENT_QUOTES, 'UTF-8');
    $css_class      = htmlspecialchars(trim(isset($_POST['css_class']) ? $_POST['css_class'] : ''), ENT_QUOTES, 'UTF-8');
    $lien_url       = htmlspecialchars(trim(isset($_POST['lien_url']) ? $_POST['lien_url'] : ''), ENT_QUOTES, 'UTF-8');
    $menu_position  = htmlspecialchars(trim(isset($_POST['menu_position']) ? $_POST['menu_position'] : 'all'), ENT_QUOTES, 'UTF-8');
    $affichage_menu = htmlspecialchars(trim(isset($_POST['affichage_menu']) ? $_POST['affichage_menu'] : 'texte'), ENT_QUOTES, 'UTF-8');
    if (!in_array($affichage_menu, array('texte','icone','icone_texte'))) $affichage_menu = 'texte';
    if (!in_array($menu_position, array('all','tabs_only','header'))) $menu_position = 'all';
    $btn_style = htmlspecialchars(trim(isset($_POST['btn_style']) ? $_POST['btn_style'] : ''), ENT_QUOTES, 'UTF-8');
    if (!in_array($btn_style, array('','cta','white','outline'))) $btn_style = '';
    $parent_id = isset($_POST['parent_id']) && intval($_POST['parent_id']) > 0 ? intval($_POST['parent_id']) : null;

    if (empty($slug) || empty($titre)) {
        $error = 'Le slug et le titre sont obligatoires.';
    } else {
        if ($id > 0) {
            $db->prepare("UPDATE pages SET slug=?,titre=?,contenu=?,meta_description=?,ordre=?,visible=?,dans_menu=?,icone=?,css_class=?,menu_position=?,lien_url=?,affichage_menu=?,btn_style=?,parent_id=?,updated_by=? WHERE id=?")
               ->execute(array($slug,$titre,$contenu,$meta,$ordre,$visible,$menu,$icone,$css_class,$menu_position,$lien_url,$affichage_menu,$btn_style,$parent_id,ADMIN_USER,$id));
        } else {
            $db->prepare("INSERT INTO pages (slug,titre,contenu,meta_description,ordre,visible,dans_menu,icone,css_class,menu_position,lien_url,affichage_menu,btn_style,parent_id,updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
               ->execute(array($slug,$titre,$contenu,$meta,$ordre,$visible,$menu,$icone,$css_class,$menu_position,$lien_url,$affichage_menu,$btn_style,$parent_id,ADMIN_USER));
            $id = $db->lastInsertId();
            $slug_for_widgets = $slug;
        }

        // Sauvegarder les widgets cochés
        $db->prepare("DELETE FROM page_widgets WHERE page_slug=?")->execute(array($slug));
        $widgets_selected  = isset($_POST['widgets'])   ? $_POST['widgets']   : array();
        $widgets_positions = isset($_POST['wpos'])      ? $_POST['wpos']      : array();
        foreach ($widgets_selected as $ordre_w => $widget_slug) {
            $pos = isset($widgets_positions[$widget_slug]) ? $widgets_positions[$widget_slug] : 'droite';
            if (!in_array($pos, array('droite','gauche'))) $pos = 'droite';
            $db->prepare("INSERT INTO page_widgets (page_slug, widget_slug, ordre, position) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE ordre=?, position=?")
               ->execute(array($slug, $widget_slug, $ordre_w + 1, $pos, $ordre_w + 1, $pos));
        }

        header('Location: pages.php?edit='.$id.'&msg='.urlencode('Page sauvegardée.')); exit;
    }
}

// ── Supprimer une page ────────────────────────────────────────────────────
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $row = $db->query("SELECT slug FROM pages WHERE id=".intval($_GET['delete']))->fetch();
    if ($row) {
        $db->prepare("DELETE FROM pages WHERE id=?")->execute(array(intval($_GET['delete'])));
        $db->prepare("DELETE FROM page_widgets WHERE page_slug=?")->execute(array($row['slug']));
    }
    header('Location: pages.php?msg=Page+supprimee.'); exit;
}

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM pages WHERE id=?");
    $stmt->execute(array(intval($_GET['edit'])));
    $edit_page = $stmt->fetch();

}

$msg = isset($_GET['msg']) ? $_GET['msg'] : $msg;
$pages = $db->query("SELECT * FROM pages ORDER BY ordre ASC, id ASC")->fetchAll();

// Charger tous les widgets disponibles
$all_widgets = array();
try {
    $all_widgets = $db->query("SELECT * FROM widgets WHERE actif=1 ORDER BY id ASC")->fetchAll();
} catch (Exception $e) {}

// Charger les widgets de la page en cours d'édition
$page_widgets_actifs = array();
if ($edit_page) {
    try {
        $stmt = $db->prepare("SELECT widget_slug, ordre, COALESCE(position,'droite') as position FROM page_widgets WHERE page_slug=? ORDER BY ordre ASC");
        $stmt->execute(array($edit_page['slug']));
        foreach ($stmt->fetchAll() as $w) {
            $page_widgets_actifs[$w['widget_slug']] = array('ordre'=>$w['ordre'], 'position'=>$w['position']);
        }
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<?php include __DIR__ . '/../includes/admin_pwa_head.php'; ?>
  <title>Pages — Admin ça suffit !</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333}
    <?php include __DIR__ . '/../includes/admin_sidebar_css.php'; ?>
    .wrap{margin-left:240px;display:grid;grid-template-columns:240px 1fr 1fr;height:100vh;overflow:hidden}
    .wrap.view-edit    { grid-template-columns: 240px 1fr; }
    .wrap.view-edit    .apanel { display:none!important; }
    .wrap.view-preview { grid-template-columns: 240px 1fr; }
    .wrap.view-preview .eform  { display:none!important; }
    /* Toggle */
    .view-toggle{display:flex;gap:3px;background:rgba(255,255,255,.15);border-radius:6px;padding:3px;flex-shrink:0}
    .vt-btn{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border:none;border-radius:4px;font-size:.72rem;font-weight:600;cursor:pointer;color:rgba(255,255,255,.7);background:transparent;transition:all .15s;font-family:inherit}
    .vt-btn.active{background:rgba(255,255,255,.25);color:#fff}
    .vt-btn:hover{color:#fff}
    .apanel-title{display:inline-flex;align-items:center;gap:6px;flex-shrink:0}
    .apanel-actions{display:flex;align-items:center;gap:10px;flex-shrink:0}
    /* Toggle dans apanel-head (fond blanc) : couleurs sombres */
    .apanel-head .view-toggle{background:rgba(0,0,0,.06)}
    .apanel-head .vt-btn{color:rgba(0,0,0,.45)}
    .apanel-head .vt-btn.active{background:rgba(0,0,0,.12);color:#0e3d6b}
    .apanel-head .vt-btn:hover{color:#0e3d6b}

    .plist{background:#fff;border-right:1px solid #e0e8f0;display:flex;flex-direction:column;overflow:hidden}
    .plist-head{padding:12px 14px;background:#0e3d6b;color:#fff;flex-shrink:0}
    .plist-head h2{font-size:.85rem;font-weight:700}
    .plist-body{flex:1;overflow-y:auto}
    .btn-new{display:block;padding:10px 14px;background:#FF9900;color:#fff;font-size:.8rem;font-weight:700;text-align:center;text-decoration:none;border-bottom:1px solid #e68800}
    .btn-new:hover{background:#e68800;color:#fff;text-decoration:none}
    .pitem{display:flex;align-items:center;padding:9px 12px;border-bottom:1px solid #f5f5f5;gap:8px;font-size:.8rem;cursor:pointer}
    .pitem.active,.pitem:hover{background:#e6f1fb}
    .pitem-icon{font-size:.9rem;width:20px;text-align:center;flex-shrink:0}
    .pitem-name{flex:1;font-weight:500;color:#333;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .pitem-acts{display:flex;gap:4px;flex-shrink:0;min-width:70px}
    .badge{display:inline-block;padding:1px 5px;border-radius:8px;font-size:.6rem;font-weight:600}
    .b-on{background:#e8f8f0;color:#27ae60}
    .b-off{background:#f0f0f0;color:#888}

    .eform{background:#fff;border-right:1px solid #e0e8f0;display:flex;flex-direction:column;overflow:hidden}
    .eform-head{padding:12px 16px;background:#1673B2;color:#fff;flex-shrink:0}
    .eform-head h2{font-size:.88rem;font-weight:700}
    .eform-head p{font-size:.68rem;color:rgba(255,255,255,.65);margin-top:2px}
    .eform-body{flex:1;overflow-y:auto;padding:14px}
    .eform-foot{padding:10px 14px;border-top:1px solid #eee;background:#f8f9fa;flex-shrink:0;display:flex;gap:8px;flex-wrap:wrap}

    label{display:block;font-size:.7rem;font-weight:600;color:#555;margin-bottom:3px;margin-top:10px;text-transform:uppercase;letter-spacing:.04em}
    label:first-child{margin-top:0}
    input[type=text],input[type=number],textarea,select{width:100%;padding:7px 9px;border:1.5px solid #dde4ed;border-radius:6px;font-size:.82rem;color:#333;outline:none;font-family:inherit;transition:border .2s}
    input:focus,textarea:focus,select:focus{border-color:#1673B2}
    textarea.code{min-height:160px;font-family:monospace;font-size:.72rem;line-height:1.5;resize:vertical}
    .row2{display:grid;grid-template-columns:1fr 1fr;gap:8px}
    .row3{display:grid;grid-template-columns:2fr 1fr 1fr;gap:8px}
    .chk-row{display:flex;gap:12px;margin-top:6px}
    .chk-item{display:flex;align-items:center;gap:5px;font-size:.78rem;cursor:pointer}
    .chk-item input{accent-color:#1673B2}

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
/* Actions inline — juste icône + couleur, sans fond */
.act-btn{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;border:1.5px solid;cursor:pointer;text-decoration:none;transition:all .15s;background:none;font-family:inherit;flex-shrink:0}
.act-btn.edit{color:#4a5568;border-color:#e2e8f0;background:#f7f8fa}
.act-btn.edit:hover{background:#edf2f7;border-color:#cbd5e0;color:#2d3748;text-decoration:none}
.act-btn.del{color:#e53e3e;border-color:#fed7d7;background:#fff5f5}
.act-btn.del:hover{background:#fee2e2;border-color:#fc8181;text-decoration:none}
.act-btn.view{color:#38a169;border-color:#c6f6d5;background:#f0fff4}
.act-btn.view:hover{background:#dcfce7;text-decoration:none}
    .flash-ok{background:#e8f8f0;color:#276749;padding:9px 12px;border-radius:6px;margin-bottom:10px;font-size:.78rem;border-left:3px solid #48bb78}
    .flash-err{background:#fde8e8;color:#c53030;padding:9px 12px;border-radius:6px;margin-bottom:10px;font-size:.78rem;border-left:3px solid #fc8181}

    /* Widgets */
    .widgets-section{margin-top:12px;padding-top:10px;border-top:2px solid #e6f1fb}
    .widgets-title{font-size:.7rem;font-weight:700;color:#0e3d6b;text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px}
    .widget-item{display:flex;align-items:center;gap:10px;padding:8px 10px;border:1.5px solid #e0e8f0;border-radius:7px;margin-bottom:6px;background:#f8fbff;cursor:pointer;transition:border .15s}
    .widget-item:hover{border-color:#1673B2;background:#e6f1fb}
    .widget-item.active{border-color:#1673B2;background:#e6f1fb}
    .widget-item input[type=checkbox]{accent-color:#1673B2;flex-shrink:0}
    .widget-icone{font-size:1.1rem;flex-shrink:0}
    .widget-info{flex:1}
    .widget-titre{font-size:.8rem;font-weight:700;color:#0e3d6b}
    .widget-desc{font-size:.68rem;color:#888;margin-top:1px}
    .widget-hint{font-size:.65rem;color:#aaa;margin-top:6px}
    .wpos-toggle{display:flex;gap:3px;margin-left:auto;flex-shrink:0}
    .wpos-btn{padding:3px 7px;border-radius:4px;font-size:.65rem;cursor:pointer;background:#f0f0f0;color:#888;font-weight:600;transition:all .15s}
    .wpos-btn.active{background:#1673B2;color:#fff}

    /* Palette */
    .palette{margin-top:12px;padding-top:10px;border-top:1px solid #eee}
    .palette-title{font-size:.65rem;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.06em;margin-bottom:7px}
    .palette-grid{display:grid;grid-template-columns:1fr 1fr;gap:5px}
    .sb{padding:6px 8px;border-radius:5px;font-size:.7rem;cursor:pointer;text-align:left;border:1.5px solid transparent;font-family:inherit;font-weight:500;transition:all .15s}
    .sb:hover{transform:translateY(-1px);box-shadow:0 2px 6px rgba(0,0,0,.12)}
    .sb-titre{background:#fff8e1;border-color:#FF9900;color:#a05000}
    .sb-texte{background:#e6f1fb;border-color:#1673B2;color:#0e3d6b}
    .sb-cadreO{background:#FF9900;border-color:#e68800;color:#fff}
    .sb-cadreB{background:#e6f1fb;border-color:#b5d4f4;color:#1673B2}
    .sb-cadreV{background:#e8f5e9;border-color:#a5d6a7;color:#1b5e20}
    .sb-alerte{background:#fff8ee;border-color:#FF9900;color:#a05000}
    .sb-chiffre{background:#f3e5f5;border-color:#ce93d8;color:#6a1b9a}
    .sb-arg{background:#fff3e0;border-color:#ffcc80;color:#e65100}
    .sb-liste{background:#f5f5f5;border-color:#bdbdbd;color:#424242}
    .sb-bq{background:#fce4ec;border-color:#f48fb1;color:#880e4f}
    .sb-img{background:#e8f5e9;border-color:#66bb6a;color:#1b5e20}
    /* Modale médias */
    .media-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9999;align-items:center;justify-content:center}
    .media-overlay.open{display:flex}
    .media-modal{background:#fff;border-radius:14px;width:min(92vw,820px);max-height:90vh;display:flex;flex-direction:column;box-shadow:0 8px 40px rgba(0,0,0,.25)}
    .media-modal-head{padding:14px 20px;border-bottom:1px solid #eee;display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
    .media-modal-head h3{font-size:.9rem;font-weight:700;color:#0e3d6b}
    .media-modal-body{padding:16px;overflow-y:auto;flex:1}
    .media-upload-zone{border:2px dashed #bee3f8;border-radius:10px;padding:20px;text-align:center;background:#f0f7ff;cursor:pointer;margin-bottom:16px;transition:all .2s}
    .media-upload-zone:hover,.media-upload-zone.drag{border-color:#1673B2;background:#e6f1fb}
    .media-upload-zone input{display:none}
    .media-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px}
    .media-item{border:2px solid #eee;border-radius:8px;overflow:hidden;cursor:pointer;transition:all .2s;position:relative}
    .media-item:hover{border-color:#1673B2;transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,.1)}
    .media-item.selected{border-color:#1673B2;box-shadow:0 0 0 3px rgba(22,115,178,.3)}
    .media-item img{width:100%;height:90px;object-fit:cover;display:block}
    .media-item-name{font-size:.65rem;color:#666;padding:4px 6px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .media-insert-btn{display:none;width:100%;padding:10px;background:#1673B2;color:#fff;border:none;border-radius:0 0 8px 8px;font-size:.82rem;font-weight:700;cursor:pointer;font-family:inherit}
    .media-insert-btn.visible{display:block}
    .media-opts{padding:10px 16px;border-top:1px solid #eee;display:none;gap:10px;align-items:center;flex-shrink:0}
    .media-opts.visible{display:flex;flex-wrap:wrap}
    .media-opts label{font-size:.75rem;color:#555;font-weight:600}
    .media-opts input,.media-opts select{padding:5px 8px;border:1.5px solid #dde4ed;border-radius:6px;font-size:.78rem;font-family:inherit;outline:none}
    .media-opts input:focus{border-color:#1673B2}

    .apanel{background:#f5f8fc;display:flex;flex-direction:column;overflow:hidden}
    .apanel-head{padding:10px 16px;background:#fff;border-bottom:1px solid #e0e8f0;display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
    .apanel-head h3{font-size:.82rem;font-weight:700;color:#0e3d6b}
    .apanel-head a{font-size:.72rem;color:#1673B2;text-decoration:none}
    .apanel-body{flex:1;overflow-y:auto;padding:20px}
    .apanel-inner{background:#fff;border-radius:8px;padding:24px;box-shadow:0 2px 10px rgba(0,0,0,.06);max-width:680px;margin:0 auto}
    .apanel-inner{font-family:"Helvetica Neue",Arial,sans-serif;line-height:1.6;color:#333}
    .apanel-inner h2{font-size:1.3rem;color:#0e3d6b;margin:0 0 16px;padding-bottom:8px;border-bottom:2px solid #e8f3fb}
    .apanel-inner h3{font-size:1rem;color:#1673B2;margin:20px 0 8px}
    .apanel-inner p{margin-bottom:12px;color:#444;line-height:1.7}
    .apanel-inner ul,.apanel-inner ol{margin:8px 0 14px 24px;color:#444}
    .apanel-inner li{margin-bottom:6px;line-height:1.6}
    .apanel-inner a{color:#1673B2}
    .apanel-inner strong{color:#0e3d6b}
    .apanel-inner blockquote{border-left:4px solid #FF9900;padding:10px 16px;background:#fff8ee;margin:14px 0;font-style:italic;color:#555}
    .apanel-inner .section-title{color:#FF9900;font-weight:400;font-size:1.1rem;margin:24px 0 10px;padding-bottom:6px;border-bottom:1px solid #c8dff0}
    .apanel-inner .cadre-bleu{padding:12px 16px;background:#e8f3fb;border-left:4px solid #1673B2;color:#1673B2;margin:14px 0;font-size:95%}
    .apanel-inner .cadre-orange{padding:12px 16px;background:#FF9900;color:#fff;margin:14px 0}
    .apanel-inner .cadre-vert{padding:12px 16px;background:#e8f5e9;border-left:4px solid #2e7d32;margin:14px 0}
    .apanel-inner .alerte{padding:12px 16px;border:2px solid #FF9900;border-left:5px solid #FF9900;background:#fff8ee;margin:14px 0}
    /* Widget preview */
    .widget-preview{background:#e6f1fb;border:1px dashed #1673B2;border-radius:6px;padding:10px;margin:8px 0;text-align:center;font-size:.75rem;color:#1673B2;font-weight:600}
  
  @media (max-width: 768px) {
    .wrap  { margin-left: 0 !important; display: block !important;
             height: auto !important; overflow: visible !important; padding-top: 52px; }
    .plist { width: 100% !important; border-right: none !important; display: block; }
    .pedit { width: 100% !important; border-right: none !important;
             height: auto !important; overflow: visible !important; }
    .apanel { display: none !important; }
    .form-row { grid-template-columns: 1fr !important; }
    .palette-grid { grid-template-columns: 1fr 1fr !important; }
    .view-toggle { display: none !important; }
    .pedit-body { padding: 14px !important; }
    .save-bar { position: sticky; bottom: 0; z-index: 10; background: #fff;
                display: flex; gap: 8px; }
    /* Bouton aperçu mobile */
    .btn-apercu-mobile { display: flex !important; }
    /* Drawer aperçu */
    .apercu-drawer {
      display: block !important;
      position: fixed; inset: 0; z-index: 500;
      pointer-events: none; opacity: 0;
      transition: opacity .2s;
    }
    .apercu-drawer.open { pointer-events: all; opacity: 1; }
    .apercu-overlay {
      position: absolute; inset: 0;
      background: rgba(0,0,0,.5);
    }
    .apercu-sheet {
      position: absolute; bottom: 0; left: 0; right: 0;
      height: 85vh;
      background: #fff; border-radius: 16px 16px 0 0;
      display: flex; flex-direction: column;
      transform: translateY(100%);
      transition: transform .3s ease;
    }
    .apercu-drawer.open .apercu-sheet { transform: translateY(0); }
    .apercu-sheet-head {
      display: flex; align-items: center; justify-content: space-between;
      padding: 14px 18px; border-bottom: 1px solid #eee; flex-shrink: 0;
    }
    .apercu-sheet-head h4 { font-size: .88rem; font-weight: 700; color: #0e3d6b; }
    .apercu-close-btn {
      background: #f0f0f0; border: none; border-radius: 50%;
      width: 28px; height: 28px; cursor: pointer; font-size: .9rem;
    }
    .apercu-sheet-body { flex: 1; overflow-y: auto; padding: 16px; }
  }
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

<div class="wrap view-edit">

  <!-- LISTE DES PAGES -->
  <div class="plist">
    <div class="plist-head"><h2>Pages</h2></div>
    <div class="plist-body">
      <a href="pages.php" class="btn-new" onclick="if(window.innerWidth<768){event.preventDefault();mobileEdit('pages.php')}"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Nouvelle page</a>
      <?php foreach ($pages as $p): ?>
      <div class="pitem <?= ($edit_page && $edit_page['id'] == $p['id']) ? 'active' : '' ?>" onclick="mobileEdit('pages.php?edit=<?= $p['id'] ?>')">
        <span class="pitem-icon"><?= htmlspecialchars($p['icone']) ?></span>
        <span class="pitem-name"><?= htmlspecialchars($p['titre']) ?></span>
        <span class="badge <?= $p['visible'] ? 'b-on' : 'b-off' ?>"><?= $p['visible'] ? '✓' : '✗' ?></span>
        <div class="pitem-acts" onclick="event.stopPropagation()">
          <a href="pages.php?edit=<?= $p['id'] ?>" class="act-btn edit"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></a>
          <a href="pages.php?delete=<?= $p['id'] ?>" class="act-btn del" title="Supprimer"
             onclick="return confirm('Supprimer &quot;<?= htmlspecialchars($p['titre']) ?>&quot; ?')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg></a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- FORMULAIRE -->
  <div class="eform">
    <div class="eform-head">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:10px">
        <div>
          <h2><?= $edit_page ? htmlspecialchars($edit_page['titre']) : 'Nouvelle page' ?></h2>
          <p>Éditeur + aperçu temps réel →</p>
        </div>
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
      </div>
    </div>
    <div class="eform-body">
      <?php if ($msg): ?><div class="flash-ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="flash-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

      <form method="POST" id="pf">
        <input type="hidden" name="page_id" value="<?= $edit_page ? $edit_page['id'] : 0 ?>">

        <label>Titre *</label>
        <input type="text" name="titre" id="f-titre" value="<?= htmlspecialchars($edit_page ? $edit_page['titre'] : '') ?>" required>

        <div class="row3">
          <div><label>Slug (URL)</label><input type="text" name="slug" id="f-slug" value="<?= htmlspecialchars($edit_page ? $edit_page['slug'] : '') ?>"></div>
          <div><label>Icône</label><input type="text" name="icone" value="<?= htmlspecialchars($edit_page ? $edit_page['icone'] : '') ?>" placeholder="📋"></div>
          <div><label>Ordre</label><input type="number" name="ordre" value="<?= $edit_page ? $edit_page['ordre'] : 10 ?>" min="0" max="99"></div>
        </div>

        <div class="chk-row" style="margin-top:8px">
          <label class="chk-item"><input type="checkbox" name="visible" <?= (!$edit_page || $edit_page['visible']) ? 'checked' : '' ?>> Visible</label>
          <label class="chk-item"><input type="checkbox" name="dans_menu" <?= (!$edit_page || $edit_page['dans_menu']) ? 'checked' : '' ?>> Dans le menu</label>
        </div>

        <?php $pos = $edit_page ? ($edit_page['menu_position'] ?? 'all') : 'all'; ?>
        <label style="margin-top:10px">Position dans le menu</label>
        <select name="menu_position">
          <option value="all"       <?= $pos==='all'       ?'selected':'' ?>>Partout (header + tabs + burger)</option>
          <option value="tabs_only" <?= $pos==='tabs_only' ?'selected':'' ?>>Tabs uniquement</option>
          <option value="header"    <?= $pos==='header'    ?'selected':'' ?>>Header + burger uniquement</option>
        </select>

        <?php $aff = $edit_page ? ($edit_page['affichage_menu'] ?? 'texte') : 'texte'; ?>
        <label>Affichage dans le menu</label>
        <select name="affichage_menu">
          <option value="texte"       <?= $aff==='texte'       ?'selected':'' ?>>Texte uniquement</option>
          <option value="icone"       <?= $aff==='icone'       ?'selected':'' ?>>Icône uniquement</option>
          <option value="icone_texte" <?= $aff==='icone_texte' ?'selected':'' ?>>Icône + Texte</option>
        </select>

        <?php $btn = $edit_page ? ($edit_page['btn_style'] ?? '') : ''; ?>
        <label>Style du bouton dans le menu</label>
        <select name="btn_style">
          <option value=""        <?= $btn===''        ?'selected':'' ?>>Normal (lien blanc)</option>
          <option value="cta"     <?= $btn==='cta'     ?'selected':'' ?>>🟠 Bouton orange (CTA)</option>
          <option value="white"   <?= $btn==='white'   ?'selected':'' ?>>⬜ Bouton blanc / écriture bleue</option>
          <option value="outline" <?= $btn==='outline' ?'selected':'' ?>>◻ Contour blanc</option>
        </select>

        <?php
        // Liste des pages parentes possibles (pas elle-même, pas déjà enfant)
        try {
            $all_pages_menu = getDB()->query("SELECT id, titre, slug FROM pages WHERE dans_menu=1 AND visible=1 AND (parent_id IS NULL OR parent_id=0) ORDER BY ordre ASC")->fetchAll();
        } catch (Exception $e) { $all_pages_menu = array(); }
        $cur_parent = $edit_page ? ($edit_page['parent_id'] ?? '') : '';
        ?>
        <label>Sous-menu de (optionnel)</label>
        <select name="parent_id">
          <option value="">— Aucun (item principal)</option>
          <?php foreach ($all_pages_menu as $pp):
            if ($edit_page && $pp['id'] == $edit_page['id']) continue; // pas elle-même
          ?>
          <option value="<?= $pp['id'] ?>" <?= $cur_parent==$pp['id']?'selected':'' ?>>
            <?= htmlspecialchars($pp['titre']) ?> (<?= $pp['slug'] ?>)
          </option>
          <?php endforeach; ?>
        </select>
        <div style="font-size:.63rem;color:#aaa;margin-top:2px">Si sélectionné, cette page apparaît dans le sous-menu déroulant de l'item parent.</div>

        <label>Lien externe (optionnel)</label>
        <input type="text" name="lien_url" value="<?= htmlspecialchars($edit_page ? ($edit_page['lien_url'] ?? '') : '') ?>" placeholder="ex: membre/login.php ou #don">
        <div style="font-size:.63rem;color:#aaa;margin-top:2px">Si rempli → lien direct, pas de tab. Utilisez #don pour scroller vers la carte de don.</div>

        <!-- WIDGETS -->
        <?php if (!empty($all_widgets)): ?>
        <div class="widgets-section">
          <div class="widgets-title"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>Widgets affichés sur cette page</div>
          <?php foreach ($all_widgets as $w): ?>
          <?php $is_active = array_key_exists($w['slug'], $page_widgets_actifs); ?>
          <?php
          $w_pos = $is_active && isset($page_widgets_actifs[$w['slug']]['position'])
              ? $page_widgets_actifs[$w['slug']]['position'] : 'droite';
          ?>
          <label class="widget-item <?= $is_active ? 'active' : '' ?>" id="wlabel-<?= $w['slug'] ?>">
            <input type="checkbox" name="widgets[]"
                   value="<?= $w['slug'] ?>" <?= $is_active ? 'checked' : '' ?>
                   onchange="this.closest('label').classList.toggle('active', this.checked)">
            <span class="widget-icone"><?= htmlspecialchars($w['icone']) ?></span>
            <div class="widget-info">
              <div class="widget-titre"><?= htmlspecialchars($w['titre']) ?></div>
              <div class="widget-desc"><?= htmlspecialchars($w['description']) ?></div>
            </div>
            <div class="wpos-toggle" onclick="event.preventDefault(); toggleWPos(this, '<?= $w['slug'] ?>')">
              <span class="wpos-btn <?= $w_pos==='gauche' ? 'active' : '' ?>" data-pos="gauche">◀ Gauche</span>
              <span class="wpos-btn <?= $w_pos==='droite' ? 'active' : '' ?>" data-pos="droite">Droite ▶</span>
            </div>
            <input type="hidden" name="wpos[<?= $w['slug'] ?>]" id="wpos-<?= $w['slug'] ?>" value="<?= $w_pos ?>">
          </label>
          <?php endforeach; ?>
          <div class="widget-hint">Les widgets s'affichent dans l'ordre ci-dessus, avant le contenu texte</div>
        </div>
        <?php endif; ?>

        <label>Meta description</label>
        <input type="text" name="meta_description" value="<?= htmlspecialchars($edit_page ? ($edit_page['meta_description'] ?? '') : '') ?>" placeholder="Pour Google...">

        <!-- PALETTE DE STYLES -->
        <div class="palette">
          <div class="palette-title">Insérer un bloc stylisé</div>
          <div class="palette-grid">
            <button type="button" class="sb sb-titre"    onclick="ins('titre')">📌 Titre section</button>
            <button type="button" class="sb sb-texte"    onclick="ins('texte')">📝 Texte bleu</button>
            <button type="button" class="sb sb-cadreO"   onclick="ins('cadreO')">🟠 Cadre orange</button>
            <button type="button" class="sb sb-cadreB"   onclick="ins('cadreB')">🔵 Cadre bleu</button>
            <button type="button" class="sb sb-cadreV"   onclick="ins('cadreV')">🟢 Cadre vert</button>
            <button type="button" class="sb sb-alerte"   onclick="ins('alerte')">⚠ Alerte</button>
            <button type="button" class="sb sb-chiffre"  onclick="ins('chiffre')">🔢 Grand chiffre</button>
            <button type="button" class="sb sb-arg"      onclick="ins('arg')">💬 Argument</button>
            <button type="button" class="sb sb-liste"    onclick="ins('liste')">• Liste</button>
            <button type="button" class="sb sb-bq"       onclick="ins('bq')">❝ Citation</button>
            <button type="button" class="sb sb-img"      onclick="ouvrirMedias()">🖼 Image</button>
          </div>
        </div>

        <label style="margin-top:12px">Contenu HTML</label>
        <textarea name="contenu" id="f-contenu" class="code" oninput="maj()"><?= htmlspecialchars($edit_page ? ($edit_page['contenu'] ?? '') : '') ?></textarea>
        <div style="font-size:.63rem;color:#aaa;margin-top:2px">Aperçu → en temps réel</div>
      </form>
    </div><!-- /eform-body -->
    <div class="eform-foot">
      <button type="submit" form="pf" name="save_page" class="btn btn-p"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Sauvegarder</button>
      <?php if ($edit_page): ?>
        <a href="<?= SITE_URL ?>/?page=<?= $edit_page['slug'] ?>" target="_blank" class="btn btn-g"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg> Voir</a>
      <?php endif; ?>
      <a href="pages.php" class="btn btn-g"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Nouvelle</a>
      <button type="button" class="btn btn-g btn-apercu-mobile" onclick="openApercu()" style="display:none"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg> Aperçu</button>
    </div>
  </div>

  <!-- APERÇU -->
  <div class="apanel">
    <div class="apanel-head">
      <span class="apanel-title">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        Aperçu
      </span>
      <div class="apanel-actions">
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
        <?php if ($edit_page): ?>
          <a href="<?= SITE_URL ?>/?page=<?= $edit_page['slug'] ?>" target="_blank" style="font-size:.72rem;color:#1673B2;text-decoration:none">↗ Ouvrir</a>
        <?php endif; ?>
      </div>
    </div>
    <div class="apanel-body">
      <!-- Widgets actifs (prévisualisés) -->
      <?php if (!empty($page_widgets_actifs)): ?>
      <div style="margin-bottom:8px">
        <?php foreach (array_keys($page_widgets_actifs) as $ws): ?>
        <div class="widget-preview"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:4px"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>Widget : <?= htmlspecialchars($ws) ?></div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <div class="apanel-inner" id="preview">
        <?= $edit_page ? $edit_page['contenu'] : '<p style="color:#aaa;text-align:center;padding:40px">Sélectionnez une page</p>' ?>
      </div>
    </div>
  </div>

</div>

<script>
function setView(mode) {
  var wrap = document.querySelector('.wrap');
  if (!wrap) return;
  wrap.classList.remove('view-edit','view-preview');
  wrap.classList.add('view-' + mode);
  document.querySelectorAll('.vt-edit').forEach(function(b){
    b.classList.toggle('active', mode === 'edit');
  });
  document.querySelectorAll('.vt-preview').forEach(function(b){
    b.classList.toggle('active', mode === 'preview');
  });
  try { localStorage.setItem('pages_view', mode); } catch(e) {}
}

function maj() {
  var v = document.getElementById('f-contenu').value;
  document.getElementById('preview').innerHTML = v || '<p style="color:#aaa;text-align:center;padding:20px">Aperçu...</p>';
}

<?php if (!$edit_page): ?>
document.getElementById('f-titre').addEventListener('input', function() {
  var s = this.value.toLowerCase()
    .replace(/[àáâãäå]/g,'a').replace(/[èéêë]/g,'e')
    .replace(/[ìíîï]/g,'i').replace(/[òóôõö]/g,'o')
    .replace(/[ùúûü]/g,'u').replace(/ç/g,'c')
    .replace(/[^a-z0-9]/g,'-').replace(/-+/g,'-').replace(/^-|-$/g,'');
  document.getElementById('f-slug').value = s;
});
<?php endif; ?>

var T = {
  titre:   '<p class="orange section-title">Votre titre</p>\n',
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
  var ta = document.getElementById('f-contenu');
  var s = ta.selectionStart, e = ta.selectionEnd;
  ta.value = ta.value.substring(0,s) + T[k] + ta.value.substring(e);
  ta.selectionStart = ta.selectionEnd = s + T[k].length;
  ta.focus(); maj();
}

function toggleWPos(container, slug) {
  var btns = container.querySelectorAll('.wpos-btn');
  // Trouver le bouton actif et activer l'autre
  var current = container.querySelector('.wpos-btn.active');
  var newPos = 'droite';
  btns.forEach(function(b) {
    b.classList.remove('active');
    if (b !== current) {
      b.classList.add('active');
      newPos = b.dataset.pos;
    }
  });
  document.getElementById('wpos-' + slug).value = newPos;
}
maj();
</script>
<script>
function mobileEdit(url) {
  if (window.innerWidth >= 768) { window.location = url; return; }
  // Sur mobile : charger l'éditeur via navigation normale mais ouvrir le panneau
  window.location = url + (url.includes('?') ? '&' : '?') + 'mobile=1';
}
function mobileBack() {
  window.location = 'pages.php';
}
// Au chargement : si on a un ?edit= sur mobile, ouvrir le panneau
document.addEventListener('DOMContentLoaded', function() {
  if (window.innerWidth >= 768) {
    var hasEdit = window.location.search.includes('edit=') || window.location.search.includes('new=');
    var sv = 'edit';
    if (!hasEdit) {
      try { var s = localStorage.getItem('pages_view'); if (s==='edit'||s==='preview') sv=s; } catch(e) {}
    }
    setView(sv);
  }
  var pedit = document.querySelector('.pedit');
  if (!pedit) return;
  if (window.location.search.includes('edit=') || window.location.search.includes('new=')) {
    if (window.innerWidth < 768) pedit.classList.add('mobile-open');
  }
});
</script>
<!-- ── Modale sélection médias ── -->
<div class="media-overlay" id="media-overlay" onclick="if(event.target===this)fermerMedias()">
  <div class="media-modal">
    <div class="media-modal-head">
      <h3>🖼 Sélectionner ou uploader une image</h3>
      <button onclick="fermerMedias()" style="background:none;border:none;font-size:1.2rem;cursor:pointer;color:#888">✕</button>
    </div>
    <div class="media-modal-body">
      <!-- Zone upload -->
      <div class="media-upload-zone" id="muz" ondragover="event.preventDefault();this.classList.add('drag')" ondragleave="this.classList.remove('drag')" ondrop="mediaDropUpload(event)" onclick="document.getElementById('media-file-input').click()">
        <input type="file" id="media-file-input" accept="image/*" onchange="mediaUpload(this.files[0])">
        <div style="font-size:1.5rem">📁</div>
        <div style="font-size:.82rem;color:#555;margin-top:6px">Glissez une image ici ou <strong>cliquez pour uploader</strong></div>
        <div id="media-upload-status" style="font-size:.75rem;color:#1673B2;margin-top:4px"></div>
      </div>
      <!-- Grille médias -->
      <div class="media-grid" id="media-grid"></div>
    </div>
    <!-- Options d'insertion -->
    <div class="media-opts" id="media-opts">
      <div style="flex:0 0 100%;font-size:.75rem;font-weight:700;color:#0e3d6b">Options d'insertion :</div>
      <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
        <label>Texte alternatif</label>
        <input type="text" id="media-alt" placeholder="Description de l'image" style="flex:1;min-width:150px">
        <label>Largeur</label>
        <select id="media-width">
          <option value="">Automatique</option>
          <option value="100%">100% (pleine largeur)</option>
          <option value="75%">75%</option>
          <option value="50%">50%</option>
          <option value="33%">33%</option>
          <option value="200px">200px</option>
          <option value="400px">400px</option>
        </select>
        <label>Alignement</label>
        <select id="media-align">
          <option value="">Aucun</option>
          <option value="left">Gauche (texte autour)</option>
          <option value="center">Centré</option>
          <option value="right">Droite (texte autour)</option>
        </select>
      </div>
      <button id="media-insert-btn" class="media-insert-btn visible" onclick="insererImage()">✅ Insérer l'image</button>
    </div>
  </div>
</div>

<script>
var mediaSelected = null;

function ouvrirMedias() {
  document.getElementById('media-overlay').classList.add('open');
  chargerMedias();
}
function fermerMedias() {
  document.getElementById('media-overlay').classList.remove('open');
  mediaSelected = null;
  document.getElementById('media-opts').classList.remove('visible');
  document.querySelectorAll('.media-item').forEach(function(i){ i.classList.remove('selected'); });
}

function chargerMedias() {
  fetch('medias_api.php?action=list')
    .then(function(r){ return r.json(); })
    .then(function(data) {
      var grid = document.getElementById('media-grid');
      if (!data.length) {
        grid.innerHTML = '<div style="color:#aaa;font-size:.82rem;text-align:center;padding:20px;grid-column:1/-1">Aucun média — uploadez votre première image ci-dessus.</div>';
        return;
      }
      grid.innerHTML = data.map(function(m) {
        return '<div class="media-item" data-url="'+m.url+'" data-name="'+m.name+'" onclick="selectMedia(this)">'
          + '<img src="'+m.url+'" alt="'+m.name+'" loading="lazy">'
          + '<div class="media-item-name">'+m.name+'</div>'
          + '</div>';
      }).join('');
    })
    .catch(function(){ document.getElementById('media-grid').innerHTML = '<div style="color:#e53e3e;font-size:.82rem;padding:20px">Erreur de chargement des médias.</div>'; });
}

function selectMedia(el) {
  document.querySelectorAll('.media-item').forEach(function(i){ i.classList.remove('selected'); });
  el.classList.add('selected');
  mediaSelected = {url: el.dataset.url, name: el.dataset.name};
  document.getElementById('media-alt').value = el.dataset.name.replace(/\.[^.]+$/, '').replace(/[-_]/g,' ');
  document.getElementById('media-opts').classList.add('visible');
}

function insererImage() {
  if (!mediaSelected) return;
  var alt    = document.getElementById('media-alt').value || '';
  var width  = document.getElementById('media-width').value;
  var align  = document.getElementById('media-align').value;

  var style = '';
  if (width)  style += 'width:'+width+';';
  if (align === 'center') style += 'display:block;margin:0 auto;';
  else if (align === 'left')  style += 'float:left;margin:0 12px 8px 0;';
  else if (align === 'right') style += 'float:right;margin:0 0 8px 12px;';

  var tag = '<img src="'+mediaSelected.url+'" alt="'+alt+'"'+(style?' style="'+style+'"':'')+'>';

  // Insérer dans le textarea à la position du curseur
  var ta = document.getElementById('f-contenu');
  var start = ta.selectionStart, end = ta.selectionEnd;
  ta.value = ta.value.substring(0, start) + tag + ta.value.substring(end);
  ta.selectionStart = ta.selectionEnd = start + tag.length;
  ta.dispatchEvent(new Event('input'));
  fermerMedias();
}

function mediaDropUpload(event) {
  event.preventDefault();
  document.getElementById('muz').classList.remove('drag');
  var file = event.dataTransfer.files[0];
  if (file && file.type.startsWith('image/')) mediaUpload(file);
}

function mediaUpload(file) {
  if (!file) return;
  var status = document.getElementById('media-upload-status');
  status.textContent = '⏳ Upload en cours...';
  var fd = new FormData();
  fd.append('file', file);
  fetch('medias_api.php?action=upload', {method:'POST', body:fd})
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (d.ok) {
        status.textContent = '✅ ' + d.filename;
        chargerMedias();
      } else {
        status.textContent = '⚠ ' + (d.error || 'Erreur');
      }
    })
    .catch(function(){ status.textContent = '⚠ Erreur réseau'; });
}
</script>

</body>
</html>
