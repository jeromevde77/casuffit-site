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
/* Éditeur WYSIWYG contenteditable */
.adv-toggle { margin-top:10px; border:1px solid #e0e8f0; border-radius:6px; }
.adv-toggle summary { padding:8px 12px; cursor:pointer; font-size:.72rem; font-weight:700;
  color:#1673B2; text-transform:uppercase; letter-spacing:.05em; user-select:none;
  list-style:none; display:flex; align-items:center; gap:6px; }
.adv-toggle summary::-webkit-details-marker { display:none; }
.adv-toggle summary::before { content:'▶'; font-size:.6rem; transition:transform .2s; }
.adv-toggle[open] summary::before { transform:rotate(90deg); }
.adv-toggle-body { padding:10px 12px; border-top:1px solid #e0e8f0; display:flex; flex-direction:column; gap:6px; }
.adv-toggle-body label { font-size:.72rem; font-weight:700; color:#555; margin:0; }
.adv-toggle-body select, .adv-toggle-body input[type=text] { 
  width:100%; padding:5px 8px; border:1px solid #c8dff0; border-radius:4px; font-size:.82rem; }
#wysiwyg-editor {
  min-height: 200px; max-height: 50vh; overflow-y: auto;
  padding: 14px; border: 1px solid #c8dff0; border-radius: 0 0 6px 6px;
  background: #fff; font-family: "Helvetica Neue",Arial,sans-serif; font-size: .88rem;
  line-height: 1.7; color: #333; outline: none; cursor: text;
}
#wysiwyg-editor:focus { border-color: #1673B2; }
#wysiwyg-toolbar { background: #f8fafc; border: 1px solid #c8dff0; border-bottom: none;
  border-radius: 6px 6px 0 0; padding: 6px 10px; display: flex; gap: 4px; flex-wrap: wrap; align-items: center;
  position: sticky; top: 0; z-index: 50; }
.wt-btn { background: #fff; border: 1px solid #dde; border-radius: 4px; padding: 3px 8px;
  cursor: pointer; font-size: .82rem; color: #333; min-width: 28px; text-align: center; }
.wt-btn:hover { background: #e8f3fb; border-color: #1673B2; }
.wt-sep { width: 1px; background: #dde; margin: 0 4px; align-self: stretch; }
#wysiwyg-editor .cadre-bleu   { padding: 12px 16px; background: #e8f3fb; border-left: 4px solid #1673B2; color: #1673B2; margin: 10px 0; border-radius: 4px; display: block; }
#wysiwyg-editor .cadre-orange { padding: 12px 16px; background: #FF9900; color: #fff; margin: 10px 0; border-radius: 4px; display: block; }
#wysiwyg-editor .cadre-vert   { padding: 12px 16px; background: #e8f5e9; border-left: 4px solid #2e7d32; margin: 10px 0; border-radius: 4px; display: block; }
#wysiwyg-editor .alerte       { background: #fff8ee; border: 2px solid #FF9900; padding: 12px 16px; border-radius: 6px; margin: 10px 0; display: block; }
#wysiwyg-editor .al-titre     { font-weight: 700; color: #FF9900; margin-bottom: 6px; display: block; }
#wysiwyg-editor h2, #wysiwyg-editor h3 { color: #FF9900; font-weight: 600; border-bottom: 1px solid #c8dff0; padding-bottom: 4px; margin: 16px 0 8px; }
#wysiwyg-editor .content-text { color: #1673B2; }
#wysiwyg-editor .ac-item      { background: #f0f6fb; border-left: 3px solid #1673B2; padding: 10px 14px; margin: 8px 0; }
#wysiwyg-editor ul, #wysiwyg-editor ol { padding-left: 20px; }
#wysiwyg-editor blockquote    { border-left: 4px solid #FF9900; padding: 8px 14px; background: #fff8ee; margin: 10px 0; }
/* Styles manquants */
#wysiwyg-editor .lettre-intro  { background: #0e3d6b; color: #fff; padding: 16px 20px; margin-bottom: 16px; display: block; }
#wysiwyg-editor .lettre-intro p { color: #fff; margin: 0; line-height: 1.55; }
#wysiwyg-editor .citation-box  { background: #f5f5f5; border-left: 4px solid #1673B2; padding: 12px 16px; margin: 10px 0; display: block; }
#wysiwyg-editor .citation-box p { font-style: italic; color: #1673B2; margin: 0 0 4px; }
#wysiwyg-editor .actions-grid  { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px,1fr)); gap: 10px; margin: 10px 0; }
#wysiwyg-editor .action-card   { background: #e8f3fb; border-top: 3px solid #1673B2; padding: 12px 10px; }
#wysiwyg-editor .ac-num        { font-size: 1.3rem; font-weight: 700; color: #7ec8e3; }
#wysiwyg-editor .ac-titre      { font-weight: 600; color: #0e3d6b; font-size: .88rem; }
#wysiwyg-editor .ac-text       { font-size: .78rem; color: #555; }
#wysiwyg-editor .cadre-vert .cv-titre { font-weight: 600; color: #1b5e20; font-size: .78rem; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 6px; display: block; }
#wysiwyg-editor .signature     { background: #e8f3fb; border-left: 3px solid #1673B2; padding: 12px 16px; margin-top: 16px; font-size: .88rem; color: #1673B2; display: block; }
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

        <?php
        $pos = $edit_page ? ($edit_page['menu_position'] ?? 'all') : 'all';
        $aff = $edit_page ? ($edit_page['affichage_menu'] ?? 'texte') : 'texte';
        $btn = $edit_page ? ($edit_page['btn_style'] ?? '') : '';
        try {
            $all_pages_menu = getDB()->query("SELECT id, titre, slug FROM pages WHERE dans_menu=1 AND visible=1 AND (parent_id IS NULL OR parent_id=0) ORDER BY ordre ASC")->fetchAll();
        } catch (Exception $e) { $all_pages_menu = array(); }
        $cur_parent = $edit_page ? ($edit_page['parent_id'] ?? '') : '';
        $has_adv = ($pos !== 'all' || $aff !== 'texte' || $btn !== '' || $cur_parent || ($edit_page && $edit_page['lien_url']));
        ?>
        <details class="adv-toggle" <?= $has_adv ? 'open' : '' ?>>
          <summary>⚙ Options avancées du menu</summary>
          <div class="adv-toggle-body">
            <label>Position dans le menu</label>
            <select name="menu_position">
              <option value="all"       <?= $pos==='all'       ?'selected':'' ?>>Partout (header + tabs + burger)</option>
              <option value="tabs_only" <?= $pos==='tabs_only' ?'selected':'' ?>>Tabs uniquement</option>
              <option value="header"    <?= $pos==='header'    ?'selected':'' ?>>Header + burger uniquement</option>
            </select>
            <label>Affichage dans le menu</label>
            <select name="affichage_menu">
              <option value="texte"       <?= $aff==='texte'       ?'selected':'' ?>>Texte uniquement</option>
              <option value="icone"       <?= $aff==='icone'       ?'selected':'' ?>>Icône uniquement</option>
              <option value="icone_texte" <?= $aff==='icone_texte' ?'selected':'' ?>>Icône + Texte</option>
            </select>
            <label>Style du bouton dans le menu</label>
            <select name="btn_style">
              <option value=""        <?= $btn===''        ?'selected':'' ?>>Normal (lien blanc)</option>
              <option value="cta"     <?= $btn==='cta'     ?'selected':'' ?>>🟠 Bouton orange (CTA)</option>
              <option value="white"   <?= $btn==='white'   ?'selected':'' ?>>⬜ Bouton blanc / écriture bleue</option>
              <option value="outline" <?= $btn==='outline' ?'selected':'' ?>>◻ Contour blanc</option>
            </select>
            <label>Sous-menu de (optionnel)</label>
            <select name="parent_id">
              <option value="">— Aucun (item principal)</option>
              <?php foreach ($all_pages_menu as $pp):
                if ($edit_page && $pp['id'] == $edit_page['id']) continue;
              ?>
              <option value="<?= $pp['id'] ?>" <?= $cur_parent==$pp['id']?'selected':'' ?>>
                <?= htmlspecialchars($pp['titre']) ?> (<?= $pp['slug'] ?>)
              </option>
              <?php endforeach; ?>
            </select>
            <label>Lien externe (optionnel)</label>
            <input type="text" name="lien_url" value="<?= htmlspecialchars($edit_page ? ($edit_page['lien_url'] ?? '') : '') ?>" placeholder="ex: membre/login.php ou #don">
            <div style="font-size:.63rem;color:#aaa;margin-top:2px">Si rempli → lien direct. Utilisez #don pour scroller vers la carte de don.</div>
          </div>
        </details>

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
        <label style="margin-top:12px">Contenu HTML</label>
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
          <div class="wt-sep"></div><button type="button" class="wt-btn" onclick="openPalette(this)" style="background:#1673B2;color:#fff;padding:3px 12px;font-weight:700">＋ Style</button>
        </div>
        <div id="wysiwyg-editor" contenteditable="true" oninput="syncEditor()"><?= $edit_page ? ($edit_page['contenu'] ?? '') : '' ?></div>
        <textarea name="contenu" id="f-contenu" style="display:none"><?= htmlspecialchars($edit_page ? ($edit_page['contenu'] ?? '') : '') ?></textarea>
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
      <iframe id="preview-frame" style="width:100%;border:none;height:600px;max-height:80vh;background:#fff;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,.06);overflow:auto" sandbox="allow-same-origin"></iframe>
    </div>
  </div>

</div>

<script>
function syncEditor() {
  var ed = document.getElementById('wysiwyg-editor');
  var ta = document.getElementById('f-contenu');
  if (ed && ta) ta.value = ed.innerHTML;
}
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
  // Recalculer l'aperçu quand on bascule en mode preview
  if (mode === 'preview') {
    setTimeout(function() { maj(); }, 50);
  }
}

function maj() {
  if (typeof syncEditor === 'function') syncEditor(); var v = document.getElementById('f-contenu').value;
  var frame = document.getElementById('preview-frame');
  if (!frame) return;
  var doc = frame.contentDocument || frame.contentWindow.document;
  doc.open();
  doc.write(`<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/assets/css/main.css">
<style>:root {
  --bleu-hex:#1673B2;--bleu-fonce:#0e5a96;--bleu-leger:#e8f3fb;--bleu-ciel:#c8dff0;
  --orange-hex:#FF9900;--orange-sombre:#cc7a00;--gris-texte:#555;--gris-clair:#f5f5f5;
  --vert-bg:#e8f5e9;--vert:#2e7d32;
}
html { scroll-behavior:smooth; overflow-x:hidden; }
body {
  margin:0; padding:28px 36px;
  font-weight:200;
  font-family:"Helvetica Neue",Arial,sans-serif;
  font-size:90%;
  background:#fff;
  color:var(--bleu-hex);
  overflow-x:hidden;
}
img { max-width:100%; height:auto; }


/* ══ CHARTE EXACTE casuffit.be ══════════════════════════════════════ */
:root {
  --bleu:        rgba(22, 115, 178, 1);   /* #1673B2 */
  --bleu-hex:    #1673B2;
  --bleu-fonce:  #0e5a96;
  --bleu-leger:  #e8f3fb;
  --bleu-ciel:   #c8dff0;
  --orange:      rgba(255, 153, 0, 1);    /* #FF9900 */
  --orange-hex:  #FF9900;
  --orange-sombre: #cc7a00;
  --blanc:       #ffffff;
  --gris-texte:  #555;
  --gris-bord:   #ccc;
  --gris-clair:  #f5f5f5;
  --vert-bg:     #e8f5e9;
  --vert:        #2e7d32;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }

body {
  margin: 0; padding: 0; height: 100%;
  font-weight: 200;
  font-family: "Helvetica Neue", Arial, sans-serif;
  font-size: 90%;
  background: #fff;
  color: var(--bleu-hex);
}

a {
  text-decoration: none;
  font-weight: 200;
  color: var(--orange-hex);
  line-height: 25px;
}
a:hover { text-decoration: underline; }

strong { font-size: 100%; font-weight: 700; }

/* ══ HEADER MODERNE ════════════════════════════════════════════════════ */
header.site-header {
  background: linear-gradient(135deg, #0e3d6b 0%, #1673B2 60%, #1a85cc 100%);
  position: sticky;
  top: 0;
  z-index: 100;
  box-shadow: 0 2px 20px rgba(0,0,0,0.25);
}
.header-inner{
  max-width:960px;
  margin:0 auto;
  padding:0 20px;
  display:flex;
  align-items:center;
  flex-direction:row;
  flex-wrap:nowrap;
  gap:0;
  min-height:64px;
}
.logo-wrap {
  width: 52px;
  height: 52px;
  flex-shrink: 0;
  margin-right: 12px;
  filter: drop-shadow(0 2px 4px rgba(0,0,0,0.25));
  transition: transform 0.2s;
}
.logo-wrap:hover { transform: scale(1.05); }
.logo-wrap img { width: 52px; height: 52px; object-fit: contain; display: block; border-radius: 0; }
.header-brand {
  display: flex;
  flex-direction: column;
  justify-content: center;
  margin-right: auto;
  flex-shrink: 0;
}
.header-brand h1 {
  font-family: "Helvetica Neue", Arial, sans-serif;
  font-size: 1.2rem;
  font-weight: 800;
  color: #ffffff;
  line-height: 1.1;
  letter-spacing: -0.02em;
  margin: 0;
}
.header-brand h1 .accent {
  color: #FF9900;
  font-style: italic;
}
.header-badge {
  display: inline-block;
  background: rgba(255,255,255,0.15);
  color: rgba(255,255,255,0.75);
  font-size: 0.58rem;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  padding: 2px 6px;
  border-radius: 20px;
  border: 1px solid rgba(255,255,255,0.2);
  margin-top: 3px;
  width: fit-content;
  white-space: nowrap;
}
/* NAV intégrée dans le header */
.header-nav {
  display: flex;
  align-items: stretch;
  height: 100%;
  gap: 2px;
  margin-left: 24px;
}
.header-nav a {
  display: flex;
  align-items: center;
  padding: 0 14px;
  color: rgba(255,255,255,0.80);
  font-size: 0.78rem;
  font-weight: 500;
  font-family: "Helvetica Neue", Arial, sans-serif;
  text-decoration: none;
  white-space: nowrap;
  transition: color 0.2s, background 0.2s;
  border-radius: 6px;
  letter-spacing: 0.02em;
}
.header-nav a:hover { color: #ffffff; background: rgba(255,255,255,0.12); }
.header-nav a.active { color: #FF9900; font-weight: 700; }
.header-nav a.nav-cta {
  background: #FF9900;
  color: #fff;
  font-weight: 700;
  border-radius: 8px;
  padding: 0 16px;
  margin-left: 8px;
}
.header-nav a.nav-cta:hover { background: #e68800; color: #fff; }
.header-nav a.nav-white {
  background: #fff; color: #0e3d6b; font-weight: 700;
  border-radius: 7px; margin-left: 6px; padding: 0 14px;
}
.header-nav a.nav-white:hover { background: #e6f1fb; color: #0e3d6b; }
.header-nav a.nav-outline {
  border: 1.5px solid rgba(255,255,255,0.7); color: #fff;
  border-radius: 7px; margin-left: 6px; padding: 0 13px;
}
.header-nav a.nav-outline:hover { background: rgba(255,255,255,0.15); }
/* ── Dropdown sous-menu ── */
.nav-dropdown { position: relative; display: flex; align-items: stretch; }
.nav-dropdown > .nav-parent {
  display: flex; align-items: center; gap: 4px;
  padding: 0 11px; color: rgba(255,255,255,0.80);
  font-size: 0.75rem; font-weight: 500; border-radius: 5px;
  cursor: pointer; white-space: nowrap; transition: all .2s; user-select: none;
}
.nav-dropdown > .nav-parent::after { content: '▾'; font-size: .6rem; opacity:.7; margin-left: 2px; }
.nav-dropdown:hover > .nav-parent { color: #fff; background: rgba(255,255,255,0.12); }
.nav-dropdown > .nav-parent.active { color: #FF9900; font-weight: 700; }
.nav-submenu {
  display: none; position: absolute; top: calc(100% + 2px); left: 0;
  background: #fff; border-radius: 8px;
  box-shadow: 0 8px 24px rgba(0,0,0,0.15); border: 1px solid #e0e8f0;
  min-width: 190px; padding: 6px; z-index: 200;
}
.nav-dropdown:hover .nav-submenu { display: block; }
.nav-submenu a {
  display: flex; align-items: center; gap: 8px;
  padding: 8px 12px; color: #0e3d6b; font-size: 0.78rem; font-weight: 500;
  border-radius: 5px; transition: background .15s; white-space: nowrap; text-decoration: none;
}
.nav-submenu a:hover { background: #e6f1fb; color: #1673B2; }
.nav-submenu a.active { color: #1673B2; font-weight: 700; background: #e6f1fb; }
@media (max-width: 700px) {
  .header-nav { display: none; }
  .header-inner { padding: 0 16px; min-height: 60px; }
  .logo-wrap { width: 46px; height: 46px; }
  .header-brand h1 { font-size: 1.1rem; }
}

/* ══ URGENCE STRIP ════════════════════════════════════════════════════ */
.urgence {
  width: 100%;
  background-color: var(--orange-hex);
  color: #fff;
  text-align: center;
  padding: 9px 20px;
  font-weight: 600;
  font-size: 95%;
}

/* ══ PROGRESS BAR ═════════════════════════════════════════════════════ */
.progress-section {
  background: #fff;
  border-bottom: 1px solid var(--bleu-ciel);
  padding: 28px 20px;
}
.progress-inner { max-width: 900px; margin: 0 auto; }
.prog-header { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 12px; flex-wrap: wrap; gap: 8px; }
.prog-label { font-weight: 400; font-size: 95%; color: var(--bleu-hex); text-transform: uppercase; letter-spacing: 0.04em; }
.prog-chiffres { font-size: 1.4rem; font-weight: 700; color: var(--bleu-hex); }
.prog-chiffres b { color: var(--orange-hex); }
.bar-wrap { background: var(--bleu-ciel); height: 12px; border-radius: 6px; overflow: hidden; margin-bottom: 14px; }
.bar-fill { height: 100%; background: linear-gradient(90deg, var(--bleu-hex), var(--orange-hex)); border-radius: 6px; width: 0%; transition: width 2s cubic-bezier(.22,1,.36,1); }
.prog-stats { display: flex; gap: 32px; flex-wrap: wrap; }
.stat { display: flex; flex-direction: column; }
.stat-val { font-size: 1.5rem; font-weight: 700; color: var(--orange-hex); }
.stat-lab { font-size: 75%; font-weight: 400; color: var(--gris-texte); text-transform: uppercase; letter-spacing: 0.05em; }

/* ══ TABS ═════════════════════════════════════════════════════════════ */
.tabs-wrap { max-width: 960px; margin: 0 auto; padding: 16px 20px 0; }
.tabs {
  display: flex; gap: 0;
  border-bottom: 2px solid var(--bleu-ciel);
  overflow-x: auto;
}
.tab-btn {
  background: none; border: none;
  border-bottom: 3px solid transparent;
  margin-bottom: -2px;
  padding: 8px 12px;
  font-family: "Helvetica Neue", Arial, sans-serif;
  font-size: 80%; font-weight: 500;
  color: var(--bleu-hex); cursor: pointer;
  transition: all 0.2s; white-space: nowrap;
  letter-spacing: -0.01em;
}
.tab-btn:hover { color: var(--orange-hex); }
.tab-btn.active { color: var(--orange-hex); border-bottom-color: var(--orange-hex); font-weight: 600; }
/* ── Sous-tabs ── */
.subtabs-wrap {
  display: none;
  background: #f0f6ff;
  border-bottom: 2px solid #c8ddf5;
  padding: 0 20px;
}
.subtabs-wrap.visible { display: block; }
.subtabs { max-width: 960px; margin: 0 auto; display: flex; gap: 2px; }
.subtab-btn {
  padding: 7px 14px;
  font-size: .75rem;
  font-weight: 500;
  color: #1673B2;
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  cursor: pointer;
  font-family: inherit;
  transition: all .2s;
  white-space: nowrap;
}
.subtab-btn:hover { color: #0e3d6b; background: rgba(22,115,178,.08); }
.subtab-btn.active { color: #0e3d6b; border-bottom-color: #1673B2; font-weight: 700; }

/* ══ MAIN LAYOUT ══════════════════════════════════════════════════════ */
.main-wrap {
  max-width: 960px;
  margin: 0 auto;
  padding: 20px 20px 40px;
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 28px;
  align-items: start;
}
@media (max-width: 680px) {
  .main-wrap { grid-template-columns: 1fr; }
  .donation-card {
  order: 2;
  position: sticky;
  top: 80px;
}
  .tabs { white-space: nowrap; }
}

/* ══ PANNEAUX CONTENU ════════════════════════════════════════════════ */
.tab-panel { display: none; }
.tab-panel.active { display: block; padding: 20px 24px; }
/* Limiter la largeur quand pas de colonne droite */
#colonne-gauche { min-width: 0; overflow: hidden; }

.section-title {
  color: var(--orange-hex);
  font-weight: 400; font-size: 20px;
  margin: 24px 0 10px; padding-bottom: 6px;
  border-bottom: 1px solid var(--bleu-ciel);
}
.section-title:first-child { margin-top: 0; }

.content-text {
  color: var(--bleu-hex);
  margin-bottom: 12px;
  font-size: 95%; line-height: 1.65;
}

/* Encadrés */
.cadre-orange {
  padding: 12px 16px;
  background-color: var(--orange-hex);
  color: #fff;
  margin: 14px 0;
  font-weight: 200;
}
.cadre-bleu {
  padding: 12px 16px;
  background-color: var(--bleu-leger);
  border-left: 4px solid var(--bleu-hex);
  color: var(--bleu-hex);
  margin: 14px 0;
  font-size: 95%;
}
.cadre-vert {
  padding: 12px 16px;
  background-color: var(--vert-bg);
  border-left: 4px solid var(--vert);
  margin: 14px 0;
}
.cadre-vert ul { list-style: none; padding: 0; }
.cadre-vert ul li { color: var(--vert); font-size: 90%; padding: 3px 0; }
.cadre-vert ul li::before { content: "✓  "; font-weight: 700; }
.cadre-vert .cv-titre { font-weight: 600; color: #1b5e20; font-size: 80%; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 8px; }

.alerte {
  padding: 12px 16px;
  border: 2px solid var(--orange-hex);
  border-left: 5px solid var(--orange-hex);
  background: #fff8ee;
  margin: 14px 0;
  font-size: 95%;
}
.alerte .al-titre { font-weight: 700; color: var(--orange-sombre); font-size: 85%; text-transform: uppercase; margin-bottom: 6px; }
.alerte p { color: #7a4500; margin: 0; }

/* Frise chronologique */
.timeline { position: relative; padding-left: 24px; margin: 14px 0; }
.timeline::before { content: ""; position: absolute; left: 6px; top: 0; bottom: 0; width: 2px; background: var(--bleu-ciel); }
.tl-item { position: relative; margin-bottom: 14px; }
.tl-item::before { content: ""; position: absolute; left: -20px; top: 5px; width: 10px; height: 10px; border-radius: 50%; background: var(--bleu-hex); border: 2px solid #fff; box-shadow: 0 0 0 2px var(--bleu-hex); }
.tl-item.bad::before { background: var(--orange-hex); box-shadow: 0 0 0 2px var(--orange-hex); }
.tl-date { font-weight: 600; font-size: 85%; color: var(--bleu-hex); }
.tl-item.bad .tl-date { color: var(--orange-sombre); }
.tl-text { font-size: 90%; color: var(--gris-texte); line-height: 1.5; }

/* Chiffres */
.chiffres-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px,1fr)); gap: 10px; margin: 14px 0; }
.chiffre-card { background: var(--bleu-leger); border-top: 3px solid var(--bleu-hex); padding: 14px; text-align: center; }
.chiffre-val { font-size: 1.5rem; font-weight: 700; color: var(--orange-hex); }
.chiffre-lab { font-size: 70%; color: var(--gris-texte); margin-top: 4px; }
.chiffre-label { font-size: 70%; color: var(--gris-texte); display: block; margin-top: 2px; }

/* Styles palette éditeur */
.orange.section-title, div.orange.section-title {
  color: var(--orange-hex);
  font-weight: 400;
  font-size: 1.05rem;
  margin: 20px 0 8px;
  padding-bottom: 4px;
  border-bottom: 1px solid var(--bleu-ciel);
}
blockquote {
  border-left: 4px solid var(--orange-hex);
  padding: 10px 16px;
  background: #fff8ee;
  margin: 14px 0;
  font-style: italic;
  color: #555;
}
.ac-item { margin: 10px 0; }
.ac-item .ac-text { font-size: 88%; color: var(--gris-texte); line-height: 1.5; }

/* Demandes */
.demandes-list { list-style: none; padding: 0; margin: 12px 0; }
.demande-item { display: flex; gap: 14px; align-items: flex-start; padding: 12px 0; border-bottom: 1px solid var(--bleu-ciel); }
.demande-item:last-child { border-bottom: none; }
.demande-num { width: 32px; height: 32px; background: var(--bleu-hex); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 90%; flex-shrink: 0; margin-top: 2px; }
.dc-titre { font-weight: 600; color: var(--bleu-hex); font-size: 95%; margin-bottom: 4px; }
.dc-text { font-size: 85%; color: var(--gris-texte); margin: 0; }

/* Actions grille */
.actions-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr)); gap: 12px; margin: 14px 0; }
.action-card { background: var(--bleu-leger); border-top: 3px solid var(--bleu-hex); padding: 14px 12px; }
.ac-num { font-size: 1.4rem; font-weight: 700; color: var(--bleu-ciel); line-height: 1; margin-bottom: 5px; }
.ac-titre { font-weight: 600; color: var(--bleu-hex); font-size: 90%; margin-bottom: 5px; }
.ac-text { font-size: 80%; color: var(--gris-texte); margin: 0; line-height: 1.45; }

.citation-box { background: #f5f5f5; border-left: 4px solid var(--bleu-hex); padding: 12px 16px; margin: 14px 0; display: flex; gap: 12px; align-items: flex-start; }
.citation-box p { font-style: italic; color: var(--bleu-hex); font-size: 90%; margin: 0 0 6px; font-weight: 400; }
.citation-box a { font-size: 85%; color: var(--bleu-hex); font-weight: 400; }

.sep { border: none; border-top: 1px solid var(--bleu-ciel); margin: 20px 0; }

.lettre-intro { background: var(--bleu-hex); color: #fff; padding: 16px 20px; margin-bottom: 20px; }
.lettre-intro p { color: #fff; font-weight: 400; font-size: 95%; margin: 0; line-height: 1.55; }

.signature { background: var(--bleu-leger); border-left: 3px solid var(--bleu-hex); padding: 14px 18px; margin-top: 20px; font-size: 90%; color: var(--bleu-hex); }
.signature strong { display: block; margin-top: 6px; font-size: 88%; }

/* ══ CARTE DON ════════════════════════════════════════════════════════ */
.donation-card {
  background: #fff;
  border: 1px solid var(--bleu-ciel);
  border-top: 4px solid var(--orange-hex);
  padding: 24px 20px;
  box-shadow: 0 4px 16px rgba(22,115,178,0.1);
}
@media (min-width: 801px) { .donation-card { position: sticky; top: 20px; } }

.don-titre { font-size: 110%; font-weight: 600; color: var(--bleu-hex); margin-bottom: 3px; }
.don-sub { font-size: 75%; color: var(--gris-texte); text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 18px; font-weight: 400; }

.montant-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 7px; margin-bottom: 10px; }
.montant-btn {
  background: var(--bleu-leger); border: 2px solid var(--bleu-ciel);
  color: var(--bleu-hex); font-family: "Helvetica Neue", Arial;
  font-size: 100%; font-weight: 600;
  padding: 10px 4px; cursor: pointer; border-radius: 0;
  transition: all 0.15s; text-align: center; line-height: 1.2;
}
.montant-btn small { display: block; font-size: 60%; font-weight: 200; color: var(--gris-texte); margin-top: 2px; text-transform: uppercase; }
.montant-btn:hover { border-color: var(--bleu-hex); }
.montant-btn.active { background: var(--bleu-hex); border-color: var(--bleu-hex); color: #fff; }
.montant-btn.active small { color: #c8dff0; }

.custom-wrap { display: none; margin-bottom: 14px; }
.custom-label { font-size: 72%; font-weight: 400; color: var(--gris-texte); text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 5px; }
.custom-row { display: flex; }
.custom-input {
  flex: 1; border: 2px solid var(--bleu-ciel); border-right: none;
  padding: 10px 14px; font-family: "Helvetica Neue", Arial;
  font-size: 130%; font-weight: 600; color: var(--bleu-hex); outline: none;
  transition: border-color 0.2s;
}
.custom-input:focus { border-color: var(--bleu-hex); }
.euro-tag { background: var(--bleu-hex); color: white; padding: 10px 14px; font-size: 120%; font-weight: 600; }

.divider { display: flex; align-items: center; gap: 10px; margin: 16px 0; color: var(--gris-texte); font-size: 72%; font-weight: 400; text-transform: uppercase; letter-spacing: 0.08em; }
.divider::before, .divider::after { content: ""; flex: 1; height: 1px; background: var(--bleu-ciel); }

/* QR */
.qr-section { background: var(--bleu-leger); border: 1px solid var(--bleu-ciel); padding: 16px; margin-bottom: 12px; text-align: center; }
.qr-title { font-weight: 600; font-size: 90%; color: var(--bleu-hex); margin-bottom: 3px; }
.qr-sub { font-size: 72%; color: var(--gris-texte); margin-bottom: 12px; }
#qrcode { display: inline-block; background: white; padding: 8px; border: 2px solid var(--bleu-ciel); margin-bottom: 8px; }
#qrcode img, #qrcode canvas { display: block; }
.qr-instructions { font-size: 70%; color: var(--gris-texte); line-height: 1.5; }
.qr-instructions b { color: var(--bleu-hex); }

/* Bancontact */
.btn-bancontact {
  display: flex; align-items: center; justify-content: center; gap: 10px;
  width: 100%; background: var(--bleu-hex);
  color: white; border: none; padding: 13px 16px;
  font-family: "Helvetica Neue", Arial; font-size: 95%; font-weight: 400;
  cursor: pointer; text-decoration: none; transition: background 0.2s;
  margin-bottom: 8px;
}
.btn-bancontact:hover { background: var(--bleu-fonce); text-decoration: none; color: white; }
.bancontact-icon { width: 34px; height: 22px; background: white; padding: 2px 4px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.bancontact-icon svg { width: 26px; height: 16px; }

/* Virement */
.virement-box { background: #f9f9f9; border: 1px solid var(--bleu-ciel); padding: 14px 16px; margin-bottom: 14px; }
.vir-label { font-size: 68%; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--bleu-hex); margin-bottom: 8px; }
.iban-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
.iban-val { font-size: 100%; font-weight: 700; color: var(--bleu-hex); letter-spacing: 0.04em; }
.iban-bank { font-size: 70%; color: var(--gris-texte); font-weight: 200; margin-top: 2px; }
.copy-btn { background: var(--bleu-hex); color: white; border: none; padding: 5px 10px; font-family: "Helvetica Neue", Arial; font-size: 70%; font-weight: 400; cursor: pointer; transition: background 0.2s; flex-shrink: 0; }
.copy-btn:hover { background: var(--bleu-fonce); }
.copy-btn.ok { background: #2a8a2a; }
.comm-box { margin-top: 10px; background: rgba(255,153,0,0.08); border: 1px dashed var(--orange-hex); padding: 8px 12px; }
.comm-label { font-size: 65%; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--orange-sombre); margin-bottom: 2px; }
.comm-val { font-size: 82%; font-style: italic; color: var(--bleu-hex); font-weight: 400; }

/* Fiscal */
.fiscal-box { background: #fffdf0; border: 1px solid #fcd99a; padding: 10px 14px; margin-bottom: 14px; display: flex; gap: 10px; align-items: flex-start; }
.fiscal-text { font-size: 78%; color: #7a5010; line-height: 1.4; }
.fiscal-text strong { font-weight: 700; display: block; color: #b06800; font-size: 82%; margin-bottom: 2px; }

/* Membre */
.membre-area { padding-top: 14px; border-top: 1px solid var(--bleu-ciel); }
.membre-area p { font-size: 80%; color: var(--gris-texte); margin-bottom: 10px; line-height: 1.5; }
.btn-membre {
  display: block; width: 100%; background: transparent;
  border: 2px solid var(--bleu-hex); color: var(--bleu-hex);
  font-family: "Helvetica Neue", Arial; font-size: 88%; font-weight: 400;
  padding: 10px; cursor: pointer; text-align: center; text-decoration: none;
  transition: all 0.2s;
}
.btn-membre:hover { background: var(--bleu-hex); color: white; text-decoration: none; }

/* ══ FOOTER ═══════════════════════════════════════════════════════════ */
.margepied { clear: both; padding-top: 50px; }
/* ══ FOOTER ══════════════════════════════════════════════════════════ */
#pied {
  width: 100%;
  background: #fff;
  border-top: 1px solid var(--gris-bord);
  color: var(--gris-texte);
  font-size: .85rem;
}
.pied-grid {
  max-width: 1100px;
  margin: 0 auto;
  padding: 36px 24px 24px;
  display: grid;
  grid-template-columns: 1.2fr 1fr 1.3fr;
  gap: 36px;
  align-items: start;
}
.pied-col h4 {
  font-size: .8rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: var(--bleu-fonce);
  margin: 0 0 12px;
}
.pied-logo {
  max-width: 130px;
  height: auto;
  margin-bottom: 12px;
  display: block;
}
.pied-mission {
  font-size: .82rem;
  line-height: 1.55;
  color: var(--gris-texte);
}
.pied-mission strong { color: var(--bleu-fonce); font-weight: 700; }

.pied-col-nav ul {
  list-style: none;
  margin: 0; padding: 0;
}
.pied-col-nav li { margin-bottom: 7px; }
.pied-col-nav a {
  color: var(--gris-texte);
  text-decoration: none;
  font-size: .85rem;
  transition: color .15s;
}
.pied-col-nav a:hover { color: var(--orange-hex); }

.pied-iban {
  font-size: .9rem;
  font-weight: 700;
  color: var(--bleu-hex);
  margin-bottom: 4px;
}
.pied-comm {
  font-size: .78rem;
  color: #888;
  margin-bottom: 12px;
}
.pied-email {
  display: inline-block;
  color: var(--orange-hex);
  text-decoration: none;
  font-size: .85rem;
  margin-bottom: 14px;
}
.pied-email:hover { text-decoration: underline; }

.pied-rs { display: flex; gap: 10px; flex-wrap: wrap; }
.pied-rs-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 38px; height: 38px;
  border-radius: 8px;
  text-decoration: none;
  transition: transform .15s;
}
.pied-rs-icon:hover { transform: translateY(-2px); }
.pied-rs-fb { background: #1877F2; }
.pied-rs-ig { background: radial-gradient(circle at 30% 107%, #fdf497 0%, #fdf497 5%, #fd5949 45%, #d6249f 60%, #285AEB 90%); }
.pied-rs-wa { background: #25D366; }

.pied-bottom {
  border-top: 1px solid #eee;
  padding: 14px 24px;
  text-align: center;
  font-size: .72rem;
  color: #999;
}

@media (max-width: 768px) {
  .pied-grid {
    grid-template-columns: 1fr;
    gap: 28px;
    padding: 28px 20px 20px;
    text-align: center;
  }
  .pied-logo { margin-left: auto; margin-right: auto; }
  .pied-rs { justify-content: center; }
}

/* Animations */
.fade-in { opacity: 0; transform: translateY(14px); animation: fadeUp 0.5s ease forwards; }
@keyframes fadeUp { to { opacity: 1; transform: none; } }
.fade-in:nth-child(2) { animation-delay: 0.15s; }
.fade-in:nth-child(3) { animation-delay: 0.3s; }

/* ══ HERO IMAGE — section pourquoi ═══════════════════════════════════ */
.pourquoi-hero {
  width: 100%;
  border-radius: 12px;
  overflow: hidden;
  margin-bottom: 24px;
  position: relative;
}
.pourquoi-hero img {
  width: 100%;
  height: auto;
  display: block;
}
.pourquoi-hero-caption {
  position: absolute;
  bottom: 0; left: 0; right: 0;
  background: linear-gradient(transparent, rgba(14,61,107,0.85));
  color: #fff;
  font-size: 0.75rem;
  font-weight: 400;
  padding: 28px 16px 10px;
  letter-spacing: 0.02em;
}
.pourquoi-hero-caption strong {
  color: #FF9900;
  font-weight: 700;
  font-size: 0.85rem;
}

/* ══ NEWSLETTER FORM ═════════════════════════════════════════════════ */
.newsletter-intro {
  background: var(--bleu-leger);
  border-left: 4px solid var(--bleu-hex);
  padding: 14px 18px;
  border-radius: 0 8px 8px 0;
  margin-bottom: 22px;
  font-size: 0.9rem;
  color: var(--texte);
  line-height: 1.6;
}
.newsletter-form { max-width: 580px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
.form-group { display: flex; flex-direction: column; gap: 5px; margin-bottom: 12px; }
.form-group label { font-size: 0.78rem; font-weight: 600; color: var(--bleu-hex); text-transform: uppercase; letter-spacing: 0.05em; }
.form-group input[type=text],
.form-group input[type=email],
.form-group input[type=tel] {
  padding: 10px 12px;
  border: 1.5px solid #dde4ed;
  border-radius: 7px;
  font-size: 0.88rem;
  color: var(--texte);
  font-family: inherit;
  transition: border-color 0.2s;
  background: #fff;
}
.form-group input:focus { outline: none; border-color: var(--bleu-hex); }
.form-check { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 10px; }
.form-check input[type=checkbox] { margin-top: 3px; width: 16px; height: 16px; flex-shrink: 0; accent-color: var(--bleu-hex); }
.form-check label { font-size: 0.83rem; color: var(--texte); line-height: 1.5; cursor: pointer; }
.form-check.rgpd { background: #f0f7ff; border: 1px solid #bee3f8; border-radius: 7px; padding: 10px 12px; }
.form-check.rgpd label { color: #2c5282; font-size: 0.78rem; }
.btn-subscribe {
  background: var(--bleu-hex);
  color: #fff;
  border: none;
  padding: 12px 28px;
  border-radius: 8px;
  font-size: 0.9rem;
  font-weight: 700;
  font-family: inherit;
  cursor: pointer;
  transition: background 0.2s;
  margin-top: 8px;
}
.btn-subscribe:hover { background: #125a90; }
.btn-subscribe:disabled { background: #888; cursor: not-allowed; }
.form-msg { margin-top: 14px; padding: 12px 16px; border-radius: 8px; font-size: 0.85rem; display: none; }
.form-msg.ok  { background: #e8f8f0; color: #276749; border-left: 3px solid #48bb78; display: block; }
.form-msg.err { background: #fde8e8; color: #c53030; border-left: 3px solid #fc8181; display: block; }
@media (max-width: 600px) { .form-row { grid-template-columns: 1fr; } }

/* ══ ANNONCE UNION ═══════════════════════════════════════════════ */
.annonce-union {
  background: linear-gradient(90deg, #0e3d6b, #1673B2);
  color: #fff;
  padding: 7px 40px 7px 16px;
  text-align: center;
  position: relative;
  line-height: 1.3;
}
.annonce-union .annonce-date {
  display: inline-block;
  background: #FF9900;
  color: #fff;
  font-size: 0.62rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  padding: 1px 6px;
  border-radius: 10px;
  margin-right: 6px;
  vertical-align: middle;
}
.annonce-union h2 {
  display: inline;
  font-size: 0.8rem;
  font-weight: 700;
  color: #fff;
  margin: 0;
}
.annonce-union p {
  display: inline;
  font-size: 0.78rem;
  color: rgba(255,255,255,0.82);
  margin: 0 0 0 5px;
}
.annonce-union .annonce-logos { display: none; }
.annonce-close {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  right: 10px;
  background: none;
  border: none;
  color: rgba(255,255,255,0.55);
  font-size: 0.85rem;
  cursor: pointer;
  padding: 4px;
  line-height: 1;
}
.annonce-close:hover { color: #fff; }

  
/* ══ DONATION OPTIONS ═══════════════════════════════════════════════ */
.don-options { display: flex; flex-direction: column; gap: 12px; margin-top: 14px; }
.don-option {
  border: 2px solid var(--bleu-ciel);
  border-radius: 10px;
  padding: 14px;
  background: #fff;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.don-option-membre { border-color: var(--orange-hex); background: #fffdf7; }
.don-option-header { display: flex; align-items: center; gap: 10px; margin-bottom: 4px; }
.don-option-icon { font-size: 1.4rem; flex-shrink: 0; }
.don-option-titre { font-size: .88rem; font-weight: 700; color: var(--bleu-fonce); }
.don-option-sub { font-size: .72rem; color: var(--gris-texte); }
.iban-box { background: var(--bleu-leger); border-radius: 6px; padding: 10px 12px; font-size: .78rem; }
.iban-val { font-family: monospace; font-size: .9rem; font-weight: 700; color: var(--bleu-fonce); margin-bottom: 3px; }
.iban-bic { color: #666; margin-bottom: 3px; }
.iban-comm { color: #555; }
.btn-copy {
  display: block; width: 100%; margin-top: 8px;
  background: var(--bleu-hex); color: #fff; border: none;
  padding: 7px; border-radius: 5px; font-size: .75rem; font-weight: 700;
  cursor: pointer; font-family: inherit; transition: background .2s;
}
.btn-copy:hover { background: var(--bleu-fonce); }
.btn-copy.ok { background: #27ae60; }
.don-check { display: flex; align-items: center; gap: 6px; font-size: .75rem; color: #777; cursor: pointer; }
.don-check input { accent-color: var(--bleu-hex); }
.btn-devenir-membre {
  display: block; text-align: center; background: var(--orange-hex); color: #fff;
  padding: 11px; border-radius: 7px; font-size: .85rem; font-weight: 700;
  text-decoration: none; transition: background .2s;
}
.btn-devenir-membre:hover { background: var(--orange-sombre); text-decoration: none; }
.btn-deja-membre {
  display: block; text-align: center; font-size: .75rem; color: var(--bleu-hex);
  text-decoration: none; padding: 6px;
}
.btn-deja-membre:hover { text-decoration: underline; }


/* ── MENU BURGER MOBILE ─────────────────────────────────── */
.burger {
  display: none;
  flex-direction: column;
  gap: 5px;
  cursor: pointer;
  padding: 6px;
  margin-left: 8px;
  background: none;
  border: none;
  flex-shrink: 0;
}
.burger span {
  display: block;
  width: 24px;
  height: 2px;
  background: #fff;
  border-radius: 2px;
  transition: all .3s;
}
.burger.open span:nth-child(1) { transform: rotate(45deg) translate(5px,5px); }
.burger.open span:nth-child(2) { opacity: 0; }
.burger.open span:nth-child(3) { transform: rotate(-45deg) translate(5px,-5px); }

.mobile-menu {
  display: none;
  position: fixed;
  top: 64px;
  left: 0; right: 0;
  background: #0e3d6b;
  z-index: 999;
  flex-direction: column;
  border-top: 1px solid rgba(255,255,255,.15);
  box-shadow: 0 8px 20px rgba(0,0,0,.3);
  max-height: calc(100vh - 64px);
  overflow-y: auto;
}
.mobile-menu.open { display: flex; }
.mobile-menu a {
  padding: 13px 20px;
  color: rgba(255,255,255,.85);
  text-decoration: none;
  font-size: .88rem;
  border-bottom: 1px solid rgba(255,255,255,.08);
  display: flex;
  align-items: center;
  gap: 8px;
  transition: background .15s;
}
.mobile-menu a:hover { background: rgba(255,255,255,.1); color: #fff; }
.mobile-menu a.nav-cta-m {
  background: #FF9900;
  color: #fff;
  font-weight: 700;
}
.mobile-menu a.nav-cta-m:hover { background: #e68800; }
.mobile-menu a.nav-membre-m {
  background: rgba(255,255,255,.1);
  color: rgba(255,255,255,.9);
  font-weight: 600;
}

@media (max-width: 680px) {
  .main-wrap { grid-template-columns: 1fr; }
  .donation-card { position: static; order: -1; }
}

@media (min-width: 681px) {
  .main-wrap { grid-template-columns: 1fr 340px; }
}

@media (max-width: 768px) {
  /* Header */
  .header-nav { display: none !important; }
  .burger { display: flex; }
  .site-header { position: relative; }
  .header-inner { padding: 0 12px; min-height: 56px; }
  .header-brand h1 { font-size: .95rem; }
  .header-badge { display: none; }
  .logo-wrap { width: 40px; height: 40px; }
  .logo-wrap img { width: 40px; height: 40px; }

  /* Annonce — une seule ligne */
  .annonce-union { padding: 6px 32px 6px 10px; }
  .annonce-union h2 { font-size: .72rem; }
  .annonce-union p { display: none; }

  /* Urgence */
  .urgence { font-size: .72rem; }

  /* Progress */
  .progress-section { padding: 16px 12px; }
  .prog-stats { grid-template-columns: repeat(2,1fr); gap: 8px; }

  /* Tabs */
  .tabs-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
  .tabs { min-width: max-content; }
  .tab-btn { font-size: .72rem; padding: 8px 10px; }

  /* Main layout */
  .main-wrap { flex-direction: column; padding: 10px 12px; }
  .main-wrap > div { width: 100% !important; }

  /* Donation card */
  .donation-card { width: 100% !important; position: static !important; }
  .don-options { gap: 10px; }
  .don-option { padding: 12px; }
  .montant-grid { grid-template-columns: repeat(3,1fr); gap: 5px; }
  .montant-btn { font-size: .78rem; padding: 7px 4px; }

  /* Contenu pages */
  .tab-panel { padding: 0; }
  .tab-panel img { max-width: 100%; height: auto; }

  /* Global */
  body { overflow-x: hidden; }
  * { max-width: 100%; }
  img { max-width: 100%; height: auto; }
  table { font-size: .78rem; }
  pre, code { white-space: pre-wrap; word-break: break-word; }
}

@media (max-width: 480px) {
  .prog-stats { grid-template-columns: repeat(2,1fr); }
  .header-brand h1 { font-size: .88rem; }
}

/* ── TABS MOBILE ──────────────────────────────────────────────────── */
.tabs-mobile { display: none; padding: 10px 12px; }
.tabs-mobile select {
  width: 100%;
  padding: 10px 14px;
  border: 2px solid var(--bleu-ciel);
  border-radius: 8px;
  font-size: .92rem;
  font-family: inherit;
  color: var(--bleu-fonce);
  background: #fff;
  font-weight: 600;
  outline: none;
  -webkit-appearance: auto;
  cursor: pointer;
}

/* Bouton fixe "Soutenir" sur mobile */
.btn-soutenir-fixe {
  display: none;
  position: fixed;
  bottom: 0; left: 0; right: 0;
  background: #FF9900;
  color: #fff;
  text-align: center;
  padding: 14px;
  font-size: .92rem;
  font-weight: 700;
  z-index: 500;
  text-decoration: none;
  cursor: pointer;
  border: none;
  font-family: inherit;
}

@media (max-width: 900px) {
  .tabs-desktop { display: none !important; }
  .tabs-mobile { display: block; }
  .tabs-wrap { background: #fff; border-bottom: 1px solid var(--bleu-ciel); }
  .btn-soutenir-fixe { display: block; }
  /* Padding bas pour le bouton fixe */
  body { padding-bottom: 52px; }
}

/* ── BOUTONS MONTANT DONATION CARD ──────────────────────────────── */
.don-montant-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 5px;
  margin-bottom: 10px;
}
.don-mbtn {
  padding: 7px 4px;
  border: 1.5px solid var(--bleu-ciel);
  border-radius: 6px;
  background: var(--bleu-leger);
  color: var(--bleu-hex);
  font-size: .8rem;
  font-weight: 600;
  cursor: pointer;
  font-family: inherit;
  transition: all .15s;
  text-align: center;
}
.don-mbtn:hover, .don-mbtn.active {
  background: var(--bleu-hex);
  color: #fff;
  border-color: var(--bleu-hex);
}

/* ── COLONNES LAYOUT ─────────────────────────────────────────────────── */
#colonne-gauche {
  min-width: 0;
}
#colonne-droite {
  min-width: 0;
  align-self: start;
  position: sticky;
  top: 20px;
}
#colonne-droite > [data-widget] {
  margin-bottom: 16px;
}

/* ── ACTUALITÉS ─────────────────────────────────────────────────────── */
.news-section { margin: 20px 0; }
.news-item {
  background: #fff;
  border: 1px solid var(--bleu-ciel);
  border-left: 4px solid var(--bleu-hex);
  border-radius: 6px;
  padding: 14px 16px;
  margin-bottom: 12px;
}
.news-epingle {
  border-left-color: var(--orange-hex);
  background: #fffdf7;
}
.news-pin {
  font-size: .7rem;
  font-weight: 700;
  color: var(--orange-hex);
  text-transform: uppercase;
  letter-spacing: .05em;
  display: block;
  margin-bottom: 4px;
}
.news-titre {
  font-weight: 700;
  color: var(--bleu-fonce);
  font-size: .95rem;
  margin-bottom: 4px;
}
.news-date {
  font-size: .72rem;
  color: #aaa;
  margin-bottom: 8px;
}
.news-contenu {
  font-size: .85rem;
  color: #555;
  line-height: 1.6;
}
.news-contenu p { margin-bottom: 6px; }

</style>
</head>
<body>${v || '<p style="color:#aaa;text-align:center;padding:40px">Aperçu...</p>'}
</body>
</html>`);
  doc.close();
  // Hauteur fixe — scroll dans l'iframe
}

<?php if ($edit_page): ?>
// Initialiser l'aperçu au chargement
document.addEventListener('DOMContentLoaded', function() { setTimeout(maj, 200);
 });
<?php endif; ?>
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
function syncEditor() {
  var ed = document.getElementById('wysiwyg-editor');
  var ta = document.getElementById('f-contenu');
  if (ed && ta) ta.value = ed.innerHTML;
}
function openApercu() {
  syncEditor(); maj();
  var d = document.querySelector('.apercu-drawer');
  if (d) d.classList.add('open');
}
function closeApercu() {
  var d = document.querySelector('.apercu-drawer');
  if (d) d.classList.remove('open');
}


// ── Palette flottante ─────────────────────────────────────────────────
var BLOCS = {
  cadreB:    '<div class="cadre-bleu">Information en bleu.</div>',
  cadreO:    '<div class="cadre-orange"><strong>Message important</strong></div>',
  cadreV:    '<div class="cadre-vert"><div class="cv-titre">Points positifs</div><ul><li>Point 1</li><li>Point 2</li></ul></div>',
  alerte:    '<div class="alerte"><div class="al-titre">⚠ Attention</div><p>Description...</p></div>',
  lettre:    '<div class="lettre-intro"><p>Chers membres,<br>votre message ici.</p></div>',
  citation:  '<div class="citation-box"><p>« Votre citation »</p><a href="#">— Source</a></div>',
  bq:        '<blockquote>Citation mise en valeur.</blockquote>',
  signature: '<div class="signature">Cordialement,<strong>L\'équipe ça suffit ! ASBL</strong></div>',
  grid:      '<div class="actions-grid"><div class="action-card"><div class="ac-num">01</div><div class="ac-titre">Titre</div><div class="ac-text">Description courte.</div></div><div class="action-card"><div class="ac-num">02</div><div class="ac-titre">Titre</div><div class="ac-text">Description courte.</div></div><div class="action-card"><div class="ac-num">03</div><div class="ac-titre">Titre</div><div class="ac-text">Description courte.</div></div></div>',
  liste:     '<ul><li>Élément 1</li><li>Élément 2</li><li>Élément 3</li></ul>',
  chiffre:   '<div style="display:inline-block;text-align:center;margin:8px 16px 8px 0"><span class="chiffre-val">320</span><span class="chiffre-label">avions/jour</span></div>',
  titre:     '<h3 class="orange section-title">Votre titre de section</h3>',
  texte:     '<p class="content-text">Texte informatif...</p>',
};

function insBloc(k) {
  if (!k || !BLOCS[k]) return;
  var ed = document.getElementById('wysiwyg-editor');
  if (!ed) return;
  // Si curseur/sélection dans un bloc stylé -> remplacer sa classe
  var styled = null;
  var SCLASSES = ['cadre-bleu','cadre-orange','cadre-vert','alerte','lettre-intro','citation-box','signature','actions-grid','ac-item','content-text'];
  var sel = window.getSelection();
  if (sel && sel.rangeCount > 0) {
    var node = sel.getRangeAt(0).commonAncestorContainer;
    if (node.nodeType === 3) node = node.parentNode;
    while (node && node !== ed) {
      if (node.nodeType === 1) {
        for (var i = 0; i < SCLASSES.length; i++) {
          if (node.classList && node.classList.contains(SCLASSES[i])) { styled = node; break; }
        }
      }
      if (styled) break;
      node = node.parentNode;
    }
  }
  if (styled) {
    var tmp = document.createElement('div');
    tmp.innerHTML = BLOCS[k];
    var newEl = tmp.firstElementChild;
    if (newEl) { styled.className = newEl.className; syncEditor(); if (typeof closePalette==='function') closePalette(); return; }
  }
  // Sinon insérer un nouveau bloc
  ed.focus();
  document.execCommand('insertHTML', false, BLOCS[k]);
  syncEditor();
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
      <div class="sp-prev-sign">L&apos;équipe ça suffit !<br><small>Contact : ...</small></div>
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
</html>
