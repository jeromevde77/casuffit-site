<?php
// admin/widgets.php — Gestion des widgets
require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();
require_once __DIR__ . '/../includes/csrf.php';
$db = getDB();

$msg = ''; $error = '';
$edit_widget = null;
$widgets_dir = __DIR__ . '/../includes/widgets/';

// ── Sauvegarder un widget ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_verify();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_widget'])) {
    $id          = intval(isset($_POST['widget_id']) ? $_POST['widget_id'] : 0);
    $slug        = preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($_POST['slug'] ?? '')));
    $titre       = htmlspecialchars(trim($_POST['titre'] ?? ''), ENT_QUOTES, 'UTF-8');
    $description = htmlspecialchars(trim($_POST['description'] ?? ''), ENT_QUOTES, 'UTF-8');
    $icone       = htmlspecialchars(trim($_POST['icone'] ?? ''), ENT_QUOTES, 'UTF-8');
    $actif       = isset($_POST['actif']) ? 1 : 0;
    $contenu_php = $_POST['contenu_php'] ?? '';

    if (empty($slug) || empty($titre)) {
        $error = 'Le slug et le titre sont obligatoires.';
    } else {
        // Sauvegarder en BDD
        if ($id > 0) {
            $db->prepare("UPDATE widgets SET slug=?,titre=?,description=?,icone=?,actif=? WHERE id=?")
               ->execute(array($slug,$titre,$description,$icone,$actif,$id));
        } else {
            $db->prepare("INSERT INTO widgets (slug,titre,description,icone,actif) VALUES (?,?,?,?,?)")
               ->execute(array($slug,$titre,$description,$icone,$actif));
            $id = $db->lastInsertId();
        }
        // Gérer l'option no-scale mobile
        $no_scale = isset($_POST['no_scale']) ? 1 : 0;
        $no_scale_line = "\$widget_no_scale = true; // Bloquer l'agrandissement mobile\n";
        // Retirer toute ligne no_scale existante du contenu
        $contenu_php = preg_replace('/\$widget_no_scale\s*=\s*true;[^\n]*\n?/', '', $contenu_php);
        // Ajouter si coché (après <?php ou en début)
        if ($no_scale) {
            if (strpos($contenu_php, '<?php') !== false) {
                $contenu_php = preg_replace('/<\?php\s*\n?/', "<?php\n" . $no_scale_line, $contenu_php, 1);
            } else {
                $contenu_php = $no_scale_line . $contenu_php;
            }
        }
        // Sauvegarder le fichier PHP du widget (FR ou NL selon le mode)
        $is_nl_edit  = !empty($_POST['editing_nl']);
        $widget_file = $is_nl_edit
            ? $widgets_dir . $slug . '_nl.php'
            : $widgets_dir . $slug . '.php';
        file_put_contents($widget_file, $contenu_php);
        $redirect_nl = $is_nl_edit ? '&view_nl=1' : '';
        header('Location: widgets.php?edit='.$id.$redirect_nl.'&msg='.urlencode('Widget sauvegardé.')); exit;
    }
}

// ── Supprimer un widget ───────────────────────────────────────────────────
if (isset($_GET['delete']) && is_numeric($_GET['delete']) && hash_equals(csrf_token(), $_GET['_csrf'] ?? '')) {
    $row = $db->query("SELECT slug FROM widgets WHERE id=".intval($_GET['delete']))->fetch();
    if ($row) {
        // Vérifier qu'aucune page n'utilise ce widget
        $used = $db->prepare("SELECT COUNT(*) FROM page_widgets WHERE widget_slug=?");
        $used->execute(array($row['slug']));
        if ($used->fetchColumn() > 0) {
            header('Location: widgets.php?error='.urlencode('Ce widget est utilisé par des pages. Retirez-le d\'abord.')); exit;
        }
        $db->prepare("DELETE FROM widgets WHERE id=?")->execute(array(intval($_GET['delete'])));
        $wfile = $widgets_dir . $row['slug'] . '.php';
        if (file_exists($wfile)) unlink($wfile);
    }
    header('Location: widgets.php?msg=Widget+supprimé.'); exit;
}

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM widgets WHERE id=?");
    $stmt->execute(array(intval($_GET['edit'])));
    $edit_widget = $stmt->fetch();
}

$msg   = isset($_GET['msg'])   ? $_GET['msg']   : $msg;
$error = isset($_GET['error']) ? $_GET['error'] : $error;
$widgets = $db->query("SELECT w.*, COUNT(pw.page_slug) as nb_pages FROM widgets w LEFT JOIN page_widgets pw ON pw.widget_slug=w.slug GROUP BY w.id ORDER BY w.id ASC")->fetchAll();

// Lire le contenu du fichier PHP du widget en cours d'édition
// et détecter l'option no_scale
$widget_php_content = '';
$viewing_nl = isset($_GET['view_nl']) && $_GET['view_nl'] == '1';
if ($edit_widget) {
    $nl_file = $widgets_dir . $edit_widget['slug'] . '_nl.php';
    $fr_file = $widgets_dir . $edit_widget['slug'] . '.php';

    if ($viewing_nl && file_exists($nl_file)) {
        $wfile = $nl_file;
    } else {
        $wfile = $fr_file;
        $viewing_nl = false;
    }

    if (file_exists($wfile)) {
        $widget_php_content = file_get_contents($wfile);
    } else {
        $widget_php_content = "<?php // Widget : " . $edit_widget['titre'] . " ?>\n<!-- Contenu HTML du widget ici -->\n";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<?php include __DIR__ . '/../includes/admin_pwa_head.php'; ?>
<title>Widgets — Admin Ça suffit !</title>
<style>
<?php include __DIR__ . '/../includes/admin_sidebar_css.php'; ?>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333}
.wrap{margin-left:240px;display:grid;grid-template-columns:280px 1fr 320px;height:100vh;overflow:hidden}
.wrap.view-edit    { grid-template-columns: 280px 1fr; }
.wrap.view-edit    .apanel  { display:none!important; }
.wrap.view-preview { grid-template-columns: 280px 1fr; }
.wrap.view-preview .eform   { display:none!important; }
/* Toggle edit/aperçu */
.view-toggle{display:flex;gap:3px;background:rgba(255,255,255,.15);border-radius:6px;padding:3px;flex-shrink:0}
.vt-btn{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border:none;border-radius:4px;font-size:.72rem;font-weight:600;cursor:pointer;color:rgba(255,255,255,.7);background:transparent;transition:all .15s;font-family:inherit}
.vt-btn.active{background:rgba(255,255,255,.25);color:#fff}
.vt-btn:hover{color:#fff}

/* Liste widgets */
.wlist{background:#fff;border-right:1px solid #e0e8f0;display:flex;flex-direction:column;overflow:hidden;width:280px;flex-shrink:0}
.wlist-head{flex-shrink:0;border-bottom:1px solid #e0e8f0}
.wlist-title{padding:14px 16px 8px;font-size:.95rem;font-weight:700;color:#0e3d6b;margin:0;display:flex;align-items:center}
.btn-new{display:block;margin:0 12px 12px;padding:9px 14px;background:#FF9900;color:#fff;text-align:center;border-radius:6px;font-size:.78rem;font-weight:700;text-decoration:none;transition:background .15s}
.btn-new:hover{background:#e68800;color:#fff;text-decoration:none}
.wlist-body{flex:1;overflow-y:auto}
.witem{padding:10px 14px;cursor:pointer;border-bottom:1px solid #f0f4f8;transition:background .1s;display:flex;align-items:center;gap:10px}
.witem:hover{background:#f5f8fc}
.witem.active{background:#e8f3fb;border-left:3px solid #1673B2}
.witem-icone{font-size:1.2rem;flex-shrink:0;width:24px;text-align:center}
.witem-info{flex:1;min-width:0}
.witem-titre{font-size:.82rem;font-weight:600;color:#0e3d6b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.witem-meta{font-size:.68rem;color:#aaa;margin-top:2px}
.witem-acts{display:flex;gap:3px;flex-shrink:0}
.badge{display:inline-block;padding:1px 5px;border-radius:6px;font-size:.6rem;font-weight:700}
.b-on{background:#e8f8f0;color:#27ae60}
.b-off{background:#f0f0f0;color:#888}

/* act-btn */
.act-btn{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:7px;border:1.5px solid;cursor:pointer;text-decoration:none;transition:all .15s;background:none;flex-shrink:0}
.act-btn.edit{color:#4a5568;border-color:#e2e8f0;background:#f7f8fa}
.act-btn.edit:hover{background:#edf2f7;border-color:#cbd5e0;text-decoration:none}
.act-btn.del{color:#e53e3e;border-color:#fed7d7;background:#fff5f5}
.act-btn.del:hover{background:#fee2e2;border-color:#fc8181;text-decoration:none}

/* Palette blocs stylisés */
.palette{margin-bottom:10px;padding:10px 12px;background:#f8fafc;border:1px solid #e0e8f0;border-radius:6px}
.palette-title{font-size:.65rem;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.06em;margin-bottom:7px}
.palette-grid{display:grid;grid-template-columns:1fr 1fr;gap:5px}
.sb{padding:6px 8px;border-radius:5px;font-size:.7rem;cursor:pointer;text-align:left;border:1.5px solid transparent;font-family:inherit;font-weight:500;transition:all .15s}
.sb:hover{transform:translateY(-1px);box-shadow:0 2px 6px rgba(0,0,0,.12)}
.sb-titre  {background:#fff8e1;border-color:#FF9900;color:#a05000}
.sb-texte  {background:#e6f1fb;border-color:#1673B2;color:#0e3d6b}
.sb-cadreO {background:#FF9900;border-color:#e68800;color:#fff}
.sb-cadreB {background:#e6f1fb;border-color:#b5d4f4;color:#1673B2}
.sb-cadreV {background:#e8f5e9;border-color:#a5d6a7;color:#1b5e20}
.sb-alerte {background:#fff8ee;border-color:#FF9900;color:#a05000}
.sb-chiffre{background:#f3e5f5;border-color:#ce93d8;color:#6a1b9a}
.sb-arg    {background:#fff3e0;border-color:#ffcc80;color:#e65100}
.sb-liste  {background:#f5f5f5;border-color:#bdbdbd;color:#424242}
.sb-bq     {background:#fce4ec;border-color:#f48fb1;color:#880e4f}


.eform{background:#fff;display:flex;flex-direction:column;overflow:hidden;border-right:1px solid #e0e8f0}
.eform-head{padding:12px 16px;background:#1673B2;color:#fff;flex-shrink:0}
.eform-head h2{font-size:.9rem;font-weight:700}
.eform-head p{font-size:.72rem;opacity:.7;margin-top:2px}
.btn-retour-mobile{display:none;font-size:.78rem;color:rgba(255,255,255,.85);text-decoration:none;font-weight:600;margin-bottom:8px;align-items:center;gap:4px}
.eform-body{flex:1;overflow-y:auto;padding:16px}
.info-box{background:#f0f7ff;border:1px solid #c8dff0;border-radius:8px;padding:12px;font-size:.75rem;color:#0e3d6b;margin-bottom:16px;font-family:monospace;line-height:1.8}
.info-box strong{color:#1673B2}
.row3{display:grid;grid-template-columns:1fr 80px auto;gap:10px;margin-bottom:12px;align-items:start}
label{display:block;font-size:.72rem;font-weight:700;color:#0e3d6b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px}
input[type=text]{width:100%;padding:8px 10px;border:1.5px solid #c8dff0;border-radius:6px;font-size:.82rem;font-family:inherit}
input[type=text]:focus{outline:none;border-color:#1673B2}
.check-inline{display:flex;align-items:center;gap:6px;font-size:.82rem;color:#555;cursor:pointer;margin-top:22px}
.code-wrap{margin-top:4px}
.code-wrap label{margin-bottom:6px}
textarea.code{width:100%;min-height:280px;background:#1e1e2e;color:#cdd6f4;font-family:"Courier New",monospace;font-size:.75rem;padding:12px;border:none;border-radius:6px;resize:vertical;line-height:1.5}
.pages-using{margin-top:12px;font-size:.75rem;color:#888}
.pages-using strong{color:#0e3d6b}

/* Footer */
.eform-foot{padding:10px 16px;border-top:1px solid #eee;background:#fafbfc;display:flex;gap:8px;align-items:center;flex-wrap:wrap;flex-shrink:0}
.btn{padding:7px 14px;border-radius:6px;font-size:.78rem;font-weight:600;cursor:pointer;border:1.5px solid transparent;font-family:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:all .15s;line-height:1}
.btn-p{background:#1673B2;color:#fff;border-color:#1673B2}
.btn-p:hover{background:#125a90;color:#fff;text-decoration:none}
.btn-g{background:#f0f4f8;color:#555;border-color:#dde4ed}
.btn-g:hover{background:#e0e8f0;color:#333;text-decoration:none}
.btn-r{background:#fff5f5;color:#e53e3e;border-color:#fed7d7}
.btn-r:hover{background:#fee2e2;text-decoration:none}
.btn-apercu-mobile{display:none}

/* Aperçu */
.apanel{background:#f5f8fc;display:flex;flex-direction:column;overflow:hidden}
.apanel-head{padding:10px 16px;background:#1673B2;color:#fff;font-size:.82rem;font-weight:700;flex-shrink:0;display:flex;align-items:center;justify-content:space-between;gap:10px}
.apanel-title{display:inline-flex;align-items:center;gap:6px;flex-shrink:0}
.apanel-actions{display:flex;align-items:center;gap:10px;flex-shrink:0}
.apanel-link{font-size:.72rem;color:rgba(255,255,255,.7);text-decoration:none;white-space:nowrap}
.apanel-link:hover{color:#fff}
.apanel-body{flex:1;overflow-y:auto;padding:16px;font-size:.82rem}
.apanel-empty{color:#aaa;text-align:center;padding:40px 20px;line-height:1.6}
.ph-badge{display:inline-block;font-size:.65rem;padding:0 5px;border-radius:3px;font-family:monospace;vertical-align:middle;margin:0 1px}
.ph-php{background:#fff3cd;color:#856404}
.ph-var{background:#d4edda;color:#155724}

/* Flash */
.flash-ok{padding:8px 16px;background:#e8f5e9;color:#2e7d32;font-size:.78rem;font-weight:600;flex-shrink:0}
.flash-err{padding:8px 16px;background:#fce4ec;color:#c62828;font-size:.78rem;font-weight:600;flex-shrink:0}

/* Drawer aperçu mobile */
.apercu-drawer{display:none}
@media (max-width:768px) {
  .wrap{margin-left:0!important;display:block!important;height:auto!important;overflow:visible!important;padding-top:52px}
  .wlist{width:100%!important;border-right:none!important;display:block}
  .eform{width:100%!important;height:auto!important;overflow:visible!important}
  .apanel{display:none!important}
  .view-toggle{display:none!important}
  .row3{grid-template-columns:1fr!important}
  .eform-body{padding:14px!important}
  .eform-foot{position:sticky;bottom:0;z-index:10;background:#fff}
  .btn-apercu-mobile{display:inline-flex!important}
  .btn-retour-mobile{display:flex!important}
  .apercu-drawer{display:block!important;position:fixed;inset:0;z-index:500;pointer-events:none;opacity:0;transition:opacity .2s}
  .apercu-drawer.open{pointer-events:all;opacity:1}
  .apercu-overlay{position:absolute;inset:0;background:rgba(0,0,0,.5)}
  .apercu-sheet{position:absolute;bottom:0;left:0;right:0;height:85vh;background:#fff;border-radius:16px 16px 0 0;display:flex;flex-direction:column;transform:translateY(100%);transition:transform .3s ease}
  .apercu-drawer.open .apercu-sheet{transform:translateY(0)}
  .apercu-sheet-head{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid #eee;flex-shrink:0}
  .apercu-sheet-head h4{font-size:.88rem;font-weight:700;color:#0e3d6b}
  .apercu-close-btn{background:#f0f0f0;border:none;border-radius:50%;width:28px;height:28px;cursor:pointer;font-size:.9rem}
  .apercu-sheet-body{flex:1;overflow-y:auto;padding:16px}
}

/* ── Palette de styles flottante ──────────────────────────────────── */
#style-palette, #w-style-palette {
  display: none; position: fixed; z-index: 9999;
  background: #fff; border: 1px solid #c8dff0; border-radius: 10px;
  box-shadow: 0 8px 32px rgba(0,0,0,.18); padding: 14px;
  width: 560px; max-width: 96vw; max-height: 80vh; overflow-y: auto;
}
#style-palette.open, #w-style-palette.open { display: block; }
#style-palette h4, #w-style-palette h4 { font-size: .72rem; font-weight: 700; color: #888;
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

  <!-- LISTE -->
  <div class="wlist">
    <div class="wlist-head">
      <h2 class="wlist-title">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px;margin-right:4px" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
        Widgets
      </h2>
      <a href="widgets.php?new=1" class="btn-new">+ Nouveau widget</a>
    </div>
    <div class="wlist-body">
      <?php foreach ($widgets as $w): ?>
      <div class="witem <?= ($edit_widget && $edit_widget['id'] == $w['id']) ? 'active' : '' ?>"
           onclick="window.location='widgets.php?edit=<?= $w['id'] ?>'">
        <span class="witem-icone"><?= htmlspecialchars($w['icone']) ?></span>
        <div class="witem-info">
          <div class="witem-titre"><?= htmlspecialchars($w['titre']) ?></div>
          <div class="witem-meta">
            <?php if ($w['actif']): ?>
            <span style="color:#27ae60">● actif</span>
            <?php else: ?>
            <span style="color:#bbb">○ inactif</span>
            <?php endif; ?>
            · <?= intval($w['nb_pages']) ?> page(s)
            <?php if (file_exists(__DIR__ . '/../includes/widgets/' . $w['slug'] . '_nl.php')): ?>
            · <a href="widgets.php?edit=<?= $w['id'] ?>&view_nl=1" onclick="event.stopPropagation()" style="background:#fff3cd;color:#856404;border-radius:4px;padding:1px 5px;font-size:.62rem;font-weight:700;text-decoration:none">🇳🇱 NL</a>
            <?php else: ?>
            · <span style="background:#f8f9fa;color:#aaa;border-radius:4px;padding:1px 5px;font-size:.62rem">FR only</span>
            <?php endif; ?>
          </div>
        </div>
        <div class="witem-acts" onclick="event.stopPropagation()">
          <a href="widgets.php?edit=<?= $w['id'] ?>" class="act-btn edit"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></a>
          <a href="widgets.php?delete=<?= $w['id'] ?>&_csrf=<?= htmlspecialchars(csrf_token()) ?>" class="act-btn del"
             onclick="return confirm('Supprimer ce widget ?')"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg></a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ÉDITEUR -->
  <div class="eform">
    <?php if ($msg): ?><div class="flash-ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="flash-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="eform-head">
      <?php if ($edit_widget): ?>
      <a href="widgets.php" class="btn-retour-mobile">← Retour</a>
      <?php endif; ?>
      <div style="display:flex;align-items:center;justify-content:space-between;gap:10px">
        <div>
          <h2><?= $edit_widget ? htmlspecialchars($edit_widget['titre']) : 'Nouveau widget' ?></h2>
          <p>
            <?php if ($edit_widget): ?>
            <?php $has_nl = file_exists(__DIR__ . '/../includes/widgets/' . $edit_widget['slug'] . '_nl.php'); ?>
            <?php if ($viewing_nl): ?>
              <span style="color:#856404;font-weight:700">🇳🇱 includes/widgets/<?= $edit_widget['slug'] ?>_nl.php</span>
              · <a href="widgets.php?edit=<?= $edit_widget['id'] ?>" style="color:rgba(255,255,255,.7);font-size:.75rem">→ voir FR</a>
            <?php else: ?>
              includes/widgets/<?= $edit_widget['slug'] ?>.php
              <?php if ($has_nl): ?>
              · <a href="widgets.php?edit=<?= $edit_widget['id'] ?>&view_nl=1" style="color:#ffd47e;font-size:.75rem">→ voir NL</a>
              <?php endif; ?>
            <?php endif; ?>
            <?php else: ?>
              Créer un nouveau widget PHP
            <?php endif; ?>
          </p>
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
      <div class="info-box">
        Variables PHP disponibles dans les widgets :<br>
        <strong>$db</strong> — connexion BDD &nbsp; <strong>$objectif</strong> &nbsp; <strong>$recolte</strong> &nbsp; <strong>$pct</strong> — progression<br>
        <strong>$iban</strong> &nbsp; <strong>$bic</strong> &nbsp; <strong>$beneficiaire</strong> — coordonnées bancaires<br>
        <strong>$news_list</strong> — actualités publiées &nbsp; <strong>$don_texte</strong> — texte de la campagne<br>
        <strong>cfg('cle', 'defaut')</strong> — lire une config
      </div>

      <form id="wf" method="POST"><?= csrf_field() ?>
        <input type="hidden" name="widget_id" value="<?= $edit_widget ? intval($edit_widget['id']) : 0 ?>">
        <input type="hidden" name="save_widget" value="1">
        <?php if ($viewing_nl): ?>
        <input type="hidden" name="editing_nl" value="1">
        <?php endif; ?>

        <div class="row3">
          <div>
            <label>Titre *</label>
            <input type="text" name="titre" value="<?= $edit_widget ? htmlspecialchars($edit_widget['titre']) : '' ?>" placeholder="Ex: Carte de don" required>
          </div>
          <div>
            <label>Icône</label>
            <input type="text" name="icone" value="<?= $edit_widget ? htmlspecialchars($edit_widget['icone']) : '' ?>" placeholder="💳">
          </div>
          <div>
            <label class="check-inline" style="margin-top:0">
              <input type="checkbox" name="actif" value="1" <?= (!$edit_widget || $edit_widget['actif']) ? 'checked' : '' ?>>
              Actif
            </label>
          </div>
        </div>

        <div style="margin-bottom:12px">
          <label>Slug (nom du fichier)</label>
          <input type="text" name="slug" value="<?= $edit_widget ? htmlspecialchars($edit_widget['slug']) : '' ?>" placeholder="mon_widget" <?= $edit_widget ? 'readonly style="background:#f5f5f5"' : '' ?>>
        </div>

        <div style="margin-bottom:12px">
          <label>Description</label>
          <input type="text" name="description" value="<?= $edit_widget ? htmlspecialchars($edit_widget['description']) : '' ?>" placeholder="Courte description du widget">
        </div>

        <div class="code-wrap">
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
            <label>Contenu PHP/HTML du widget</label>
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;background:<?= isset($widget_is_no_scale) && $widget_is_no_scale ? '#fff8ee' : '#f0f8ff' ?>;border:1px solid <?= isset($widget_is_no_scale) && $widget_is_no_scale ? '#FF9900' : '#c8dff0' ?>;border-radius:6px;padding:4px 10px;font-size:.75rem;font-weight:600;color:<?= isset($widget_is_no_scale) && $widget_is_no_scale ? '#c07000' : '#1673B2' ?>">
              <input type="checkbox" name="no_scale" <?= isset($widget_is_no_scale) && $widget_is_no_scale ? 'checked' : '' ?> style="width:14px;height:14px;cursor:pointer">
              📵 Bloquer agrandissement mobile
            </label>
          </div>
          <div style="margin-bottom:6px">
            <button type="button" onclick="openWPalette(this)" style="background:#1673B2;color:#fff;border:none;border-radius:5px;padding:5px 14px;font-size:.78rem;font-weight:700;cursor:pointer">＋ Insérer un style</button>
          </div>
          <textarea name="contenu_php" id="w-contenu" class="code" spellcheck="false" oninput="syncApercu()"><?= htmlspecialchars($widget_php_content) ?></textarea>
        </div>

        <?php if ($edit_widget): ?>
        <div class="pages-using">
          <?php
          $pages_using = $db->prepare("SELECT p.titre FROM page_widgets pw JOIN pages p ON p.slug=pw.page_slug WHERE pw.widget_slug=?");
          $pages_using->execute(array($edit_widget['slug']));
          $plist = $pages_using->fetchAll();
          ?>
          <?php if ($plist): ?>
          <strong>Utilisé par :</strong> <?= implode(', ', array_map(function($p){ return htmlspecialchars($p['titre']); }, $plist)) ?>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </form>
    </div>

    <div class="eform-foot">
      <button type="submit" form="wf" name="save_widget" class="btn btn-p"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Sauvegarder</button>
      <a href="widgets.php" class="btn btn-g"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Nouveau</a>
      <?php if ($edit_widget): ?>
      <?php $has_nl_file = file_exists(__DIR__ . '/../includes/widgets/' . $edit_widget['slug'] . '_nl.php'); ?>
      <button type="button" id="btn-create-nl"
              onclick="createNlVersion('<?= htmlspecialchars($edit_widget['slug'], ENT_QUOTES) ?>', <?= $has_nl_file ? 'true' : 'false' ?>)"
              style="background:<?= $has_nl_file ? '#d97706' : '#1673B2' ?>;color:#fff;border:none;padding:7px 14px;border-radius:6px;font-size:.78rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px">
        <?= $has_nl_file ? '🔄 Re-traduire en NL' : '🇳🇱 Créer version NL' ?>
      </button>
      <a href="widgets.php?delete=<?= $edit_widget['id'] ?>&_csrf=<?= htmlspecialchars(csrf_token()) ?>" class="btn btn-r"
         onclick="return confirm('Supprimer ce widget ?')"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg> Supprimer</a>
      <?php endif; ?>
      <button type="button" class="btn btn-g btn-apercu-mobile" onclick="openApercu()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg> Aperçu</button>
    </div>
  </div>

  <!-- APERÇU -->
  <div class="apanel">
    <div class="apanel-head">
      <span class="apanel-title">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
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
        <a href="<?= SITE_URL ?>" target="_blank" class="apanel-link">Ouvrir le site →</a>
      </div>
    </div>
    <div class="apanel-body" id="apanel-body" style="padding:0;overflow:hidden">
      <iframe id="apercu-frame" src="about:blank" style="width:100%;height:100%;min-height:500px;border:none;display:block"></iframe>
    </div>
  </div>

</div>

<!-- Drawer aperçu mobile -->
<div class="apercu-drawer" id="apercu-drawer">
  <div class="apercu-overlay" onclick="closeApercu()"></div>
  <div class="apercu-sheet">
    <div class="apercu-sheet-head">
      <h4>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        Aperçu
      </h4>
      <button class="apercu-close-btn" onclick="closeApercu()">✕</button>
    </div>
    <div class="apercu-sheet-body" id="apercu-sheet-body"></div>
  </div>
</div>

<script>
var WT = {
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
function wIns(k) {
  var ta = document.getElementById('w-contenu');
  var s = ta.selectionStart, e = ta.selectionEnd;
  ta.value = ta.value.substring(0,s) + WT[k] + ta.value.substring(e);
  ta.selectionStart = ta.selectionEnd = s + WT[k].length;
  ta.focus();
  syncApercu();
}

var apercuTimer = null;

function setView(mode) {
  var wrap = document.querySelector('.wrap');
  if (!wrap) return;
  wrap.classList.remove('view-edit', 'view-preview');
  if (mode !== 'both') wrap.classList.add('view-' + mode);
  // Activer les bons boutons dans TOUS les toggles (eform-head + apanel-head)
  document.querySelectorAll('.vt-edit').forEach(function(b){
    b.classList.toggle('active', mode === 'edit' || mode === 'both');
  });
  document.querySelectorAll('.vt-preview').forEach(function(b){
    b.classList.toggle('active', mode === 'preview' || mode === 'both');
  });
  // Si on passe en aperçu, forcer le refresh
  if (mode === 'preview') syncApercu();
  // Sauvegarder la préférence
  try { localStorage.setItem('widget_view', mode); } catch(e) {}
}



function syncApercu() {
  var ta    = document.getElementById('w-contenu');
  var frame = document.getElementById('apercu-frame');
  var mob   = document.getElementById('apercu-sheet-body');
  if (!ta) return;

  clearTimeout(apercuTimer);
  apercuTimer = setTimeout(function() {
    var contenu = ta.value;
    if (!contenu.trim()) {
      if (frame) {
        var doc = frame.contentDocument || frame.contentWindow.document;
        doc.open(); doc.write('<div style="color:#aaa;text-align:center;padding:40px 20px;font-family:sans-serif;font-size:.85rem">Ecrivez du HTML pour voir l apercu...</div>'); doc.close();
      }
      return;
    }

    var fd = new FormData();
    fd.append('contenu', contenu);

    fetch('widget_preview.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function(r) { return r.text(); })
      .then(function(html) {
        // Écrire dans l'iframe pour isoler les styles
        if (frame) {
          var doc = frame.contentDocument || frame.contentWindow.document;
          doc.open(); doc.write(html); doc.close();
        }
        // Drawer mobile : innerHTML simple
        if (mob) mob.innerHTML = html;
      })
      .catch(function(err) {
        var msg = '<div style="color:#c33;padding:12px;font-family:sans-serif;font-size:.78rem">Erreur : ' + err.message + '</div>';
        if (frame) {
          var doc = frame.contentDocument || frame.contentWindow.document;
          doc.open(); doc.write(msg); doc.close();
        }
      });
  }, 400);
}

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

document.addEventListener('DOMContentLoaded', function() {
  if (window.innerWidth >= 768) {
    var hasEdit = window.location.search.includes('edit=') || window.location.search.includes('new=');
    var sv = 'edit';
    if (!hasEdit) {
      // Sur la page liste sans édition : respecter la préférence sauvegardée
      try {
        var saved = localStorage.getItem('widget_view');
        if (saved === 'edit' || saved === 'preview') sv = saved;
      } catch(e) {}
    }
    // Si on édite/crée : toujours forcer edit (et mettre à jour localStorage)
    setView(sv);
  }
  // Mobile : masquer liste ou éditeur selon contexte
  if (window.innerWidth < 768) {
    var hasEdit = window.location.search.includes('edit=') || window.location.search.includes('new=');
    if (hasEdit) {
      var wl = document.querySelector('.wlist');
      if (wl) wl.style.display = 'none';
    } else {
      var ef = document.querySelector('.eform');
      if (ef) ef.style.display = 'none';
    }
  }
  // Lancer l'aperçu initial
  syncApercu();
});

var WBLOCS = {
  cadreB:    '<div class="cadre-bleu">Information en bleu.</div>\n',
  cadreO:    '<div class="cadre-orange"><strong>Message important</strong></div>\n',
  cadreV:    '<div class="cadre-vert"><div class="cv-titre">Points positifs</div><ul><li>Point 1</li></ul></div>\n',
  alerte:    '<div class="alerte"><div class="al-titre">⚠ Attention</div><p>Description...</p></div>\n',
  lettre:    '<div class="lettre-intro"><p>Chers membres, ...</p></div>\n',
  citation:  '<div class="citation-box"><p>« Votre citation »</p></div>\n',
  bq:        '<blockquote>Citation mise en valeur.</blockquote>\n',
  signature: '<div class="signature">Cordialement,<strong>L\'équipe Ça suffit !</strong></div>\n',
  grid:      '<div class="actions-grid"><div class="action-card"><div class="ac-num">01</div><div class="ac-titre">Titre</div><div class="ac-text">Description.</div></div></div>\n',
  liste:     '<ul><li>Élément 1</li><li>Élément 2</li></ul>\n',
  chiffre:   '<div style="display:inline-block;text-align:center;margin:8px 16px 8px 0"><span class="chiffre-val">320</span><span class="chiffre-label">avions/jour</span></div>\n',
  titre:     '<h3 class="orange section-title">Votre titre</h3>\n',
  texte:     '<p class="content-text">Texte informatif...</p>\n',
};
function wInsBloc(k) {
  if (!k || !WBLOCS[k]) return;
  var ta = document.getElementById('w-contenu');
  if (!ta) return;
  var s = ta.selectionStart || ta.value.length;
  ta.value = ta.value.substring(0,s) + WBLOCS[k] + ta.value.substring(s);
  ta.selectionStart = ta.selectionEnd = s + WBLOCS[k].length;
  ta.focus();
  if (typeof syncApercu === 'function') syncApercu();
  closeWPalette();
}
function openWPalette(btn) {
  var p = document.getElementById('w-style-palette');
  if (!p) return;
  if (p.classList.contains('open')) { p.classList.remove('open'); return; }
  var r = btn.getBoundingClientRect();
  var maxH = Math.round(window.innerHeight * 0.75);
  p.style.maxHeight = maxH + 'px';
  p.style.overflowY = 'auto';
  var left = Math.min(Math.max(10, r.left + window.scrollX), window.innerWidth - 570);
  var top = (r.bottom + 4 + window.scrollY);
  if (r.bottom + maxH + 10 > window.innerHeight) top = Math.max(60, r.top - maxH - 6 + window.scrollY);
  p.style.top = top + 'px';
  p.style.left = left + 'px';
  p.classList.add('open');
}
function closeWPalette() {
  var p = document.getElementById('w-style-palette');
  if (p) p.classList.remove('open');
}
document.addEventListener('click', function(e) {
  var p = document.getElementById('w-style-palette');
  if (!p || !p.classList.contains('open')) return;
  if (!p.contains(e.target) && !e.target.closest('[onclick*="openWPalette"]')) p.classList.remove('open');
});

</script>

<div id="w-style-palette">
  <button class="sp-close" onclick="closeWPalette()">✕</button>
  <h4>Choisir un style</h4>

  <div class="sp-grid">

    <div class="sp-item" onclick="wInsBloc('cadreB'); closeWPalette()">
      <div class="sp-item-label">Cadre bleu</div>
      <div class="sp-prev-cadreB">Information mise en avant</div>
    </div>

    <div class="sp-item" onclick="wInsBloc('cadreO'); closeWPalette()">
      <div class="sp-item-label">Cadre orange</div>
      <div class="sp-prev-cadreO">Message important</div>
    </div>

    <div class="sp-item" onclick="wInsBloc('cadreV'); closeWPalette()">
      <div class="sp-item-label">Cadre vert</div>
      <div class="sp-prev-cadreV">Points positifs</div>
    </div>

    <div class="sp-item" onclick="wInsBloc('alerte'); closeWPalette()">
      <div class="sp-item-label">⚠ Alerte</div>
      <div class="sp-prev-alerte"><strong>Attention</strong> — Description</div>
    </div>

    <div class="sp-item" onclick="wInsBloc('lettre'); closeWPalette()">
      <div class="sp-item-label">Lettre intro</div>
      <div class="sp-prev-lettre">Chers membres, ...</div>
    </div>

    <div class="sp-item" onclick="wInsBloc('citation'); closeWPalette()">
      <div class="sp-item-label">Citation</div>
      <div class="sp-prev-citation">« Citation mise en valeur »</div>
    </div>

    <div class="sp-item" onclick="wInsBloc('bq'); closeWPalette()">
      <div class="sp-item-label">Blockquote</div>
      <div class="sp-prev-bq">Citation courte</div>
    </div>

    <div class="sp-item" onclick="wInsBloc('signature'); closeWPalette()">
      <div class="sp-item-label">Signature</div>
      <div class="sp-prev-sign">L'équipe Ça suffit !<br><small>Contact : ...</small></div>
    </div>

    <div class="sp-item" style="grid-column:1/-1" onclick="wInsBloc('grid'); closeWPalette()">
      <div class="sp-item-label">Grille d'actions (3 colonnes)</div>
      <div class="sp-prev-grid">
        <div class="sp-prev-grid-item"><strong>01</strong><br>Titre</div>
        <div class="sp-prev-grid-item"><strong>02</strong><br>Titre</div>
        <div class="sp-prev-grid-item"><strong>03</strong><br>Titre</div>
      </div>
    </div>

    <div class="sp-item" onclick="wInsBloc('liste'); closeWPalette()">
      <div class="sp-item-label">Liste à puces</div>
      <div class="sp-prev-liste">• Élément 1<br>• Élément 2</div>
    </div>

    <div class="sp-item" onclick="wInsBloc('chiffre'); closeWPalette()">
      <div class="sp-item-label">Chiffre clé</div>
      <div class="sp-prev-chiffre">
        <span class="sp-prev-chiffre-val">320</span>
        <span class="sp-prev-chiffre-lbl">avions/jour</span>
      </div>
    </div>

    <div class="sp-item" onclick="wInsBloc('titre'); closeWPalette()">
      <div class="sp-item-label">Titre de section</div>
      <div class="sp-prev-titre">Votre titre</div>
    </div>

    <div class="sp-item" onclick="wInsBloc('texte'); closeWPalette()">
      <div class="sp-item-label">Texte bleu</div>
      <div class="sp-prev-texteB">Texte informatif en bleu</div>
    </div>

  </div>
</div>

<script>
function createNlVersion(slug, alreadyExists) {
  var msg = alreadyExists
    ? 'Une version NL existe déjà pour ce widget.\nVoulez-vous la remplacer par une nouvelle traduction automatique ?'
    : 'Créer une version NL de ce widget par traduction automatique (Claude) ?\n\nLe fichier ' + slug + '_nl.php sera créé. Il sera à relire avant mise en production.';
  if (!confirm(msg)) return;

  var btn = document.getElementById('btn-create-nl');
  var origText = btn.textContent;
  btn.textContent = '⏳ Traduction en cours…';
  btn.disabled = true;

  // Récupérer le contenu actuel de l'éditeur
  var contenu = document.getElementById('w-contenu')?.value || '';

  fetch('/admin/translate_widget.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'slug=' + encodeURIComponent(slug) + '&contenu=' + encodeURIComponent(contenu)
  })
  .then(r => r.json())
  .then(d => {
    if (d.ok) {
      btn.textContent = '✅ Version NL créée !';
      btn.style.background = '#27ae60';
      setTimeout(() => {
        // Recharger la page pour mettre à jour le badge et le bouton
        window.location.href = 'widgets.php?edit=' + encodeURIComponent(document.querySelector('input[name=widget_id]')?.value || '') + '&msg=' + encodeURIComponent('Version NL créée avec succès — à relire avant mise en production.');
      }, 1200);
    } else {
      alert('Erreur : ' + (d.error || 'inconnue'));
      btn.textContent = origText;
      btn.disabled = false;
    }
  })
  .catch(e => {
    alert('Erreur réseau : ' + e.message);
    btn.textContent = origText;
    btn.disabled = false;
  });
}
</script>
</body>
</html>
