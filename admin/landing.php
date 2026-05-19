<?php
// admin/landing.php — Éditeur dédié pour la page /agir (contenu + CSS)
require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();
require_once __DIR__ . '/../includes/csrf.php';
$db = getDB();

$msg = ''; $error = '';

// ── Charger depuis la table landing_pages (indépendante de pages et site_config) ──
function lpGet($db) {
    $stmt = $db->prepare("SELECT * FROM landing_pages WHERE slug='agir' LIMIT 1");
    $stmt->execute();
    $r = $stmt->fetch();
    if (!$r) {
        $db->prepare("INSERT INTO landing_pages (slug, titre, contenu, contenu_nl, css) VALUES ('agir','Page Agir avec nous','','',' ')")->execute();
        $r = ['contenu'=>'','contenu_nl'=>'','css'=>''];
    }
    return $r;
}
$lp = lpGet($db);

// ── Sauvegarder ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? 'save_all';

    $contenu    = ($action === 'save_content' || $action === 'save_all') ? ($_POST['contenu'] ?? '')    : $lp['contenu'];
    $contenu_nl = ($action === 'save_content' || $action === 'save_all') ? ($_POST['contenu_nl'] ?? '') : $lp['contenu_nl'];
    $css        = ($action === 'save_css'     || $action === 'save_all') ? ($_POST['agir_css'] ?? '')   : $lp['css'];

    $db->prepare("UPDATE landing_pages SET contenu=?, contenu_nl=?, css=? WHERE slug='agir'")
       ->execute([$contenu, $contenu_nl, $css]);

    $msg = '✅ Sauvegardé.';
    $lp = lpGet($db);
}

$contenu    = $lp['contenu']    ?? '';
$contenu_nl = $lp['contenu_nl'] ?? '';
$agir_css   = $lp['css']        ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Landing /agir — Admin</title>
<style>
<?php include __DIR__ . '/../includes/admin_sidebar_css.php'; ?>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333;font-size:14px}

.main{margin-left:240px;height:100vh;display:flex;flex-direction:column;overflow:hidden}
@media(max-width:768px){.main{margin-left:0;padding-top:52px}}

/* ── Top bar ── */
.top-bar{background:#fff;border-bottom:1px solid #e0e8f0;padding:10px 20px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;gap:10px;flex-wrap:wrap}
.top-bar h1{font-size:1rem;color:#1673B2;font-weight:700}
.top-bar .hint{font-size:.75rem;color:#888}
.btn-row{display:flex;gap:8px;align-items:center}
.btn{padding:7px 16px;border-radius:6px;border:none;font-size:.82rem;font-weight:700;cursor:pointer;font-family:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:5px}
.btn-orange{background:#FF9900;color:#fff}
.btn-blue{background:#1673B2;color:#fff}
.btn-outline{background:#fff;border:1.5px solid #1673B2;color:#1673B2}
.btn-gray{background:#e8eef3;color:#555}
.flash{padding:7px 14px;border-radius:6px;font-size:.82rem;font-weight:600}
.flash-ok{background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7}

/* ── Layout éditeur ── */
.editor-layout{flex:1;display:grid;grid-template-columns:1fr 1fr;overflow:hidden}
@media(max-width:900px){.editor-layout{grid-template-columns:1fr}}

/* ── Panneau gauche : onglets ── */
.left-pane{display:flex;flex-direction:column;border-right:1px solid #e0e8f0;overflow:hidden}
.tabs-nav{display:flex;border-bottom:1px solid #e0e8f0;background:#fafbfc;flex-shrink:0}
.tab-btn{padding:10px 16px;font-size:.8rem;font-weight:700;border:none;background:none;cursor:pointer;color:#888;border-bottom:3px solid transparent;font-family:inherit}
.tab-btn.active{color:#1673B2;border-bottom-color:#1673B2;background:#fff}
.tab-pane{display:none;flex:1;flex-direction:column;overflow:hidden}
.tab-pane.active{display:flex}

/* ── Éditeur texte ── */
.editor-wrap{flex:1;display:flex;flex-direction:column;overflow:hidden}
.editor-label{font-size:.7rem;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.05em;padding:8px 12px;background:#f5f7fa;border-bottom:1px solid #e8eef3;flex-shrink:0}
.wysiwyg-toolbar{display:flex;gap:3px;flex-wrap:wrap;padding:6px 10px;background:#fff;border-bottom:1px solid #e0e8f0;flex-shrink:0;align-items:center}
.wt-btn{padding:4px 9px;border:1px solid #d0d8e0;border-radius:4px;background:#fafbfc;font-size:.78rem;cursor:pointer;font-family:inherit}
.wt-btn:hover{background:#1673B2;color:#fff;border-color:#1673B2}
.wt-sep{width:1px;height:20px;background:#e0e8f0;margin:0 3px}

/* ── Palette de blocs ── */
.bloc-palette-btn{padding:5px 10px;border:1.5px solid #FF9900;border-radius:5px;background:#fff8ee;color:#c47700;font-size:.78rem;font-weight:700;cursor:pointer;font-family:inherit;margin-left:6px}
.bloc-palette-btn:hover{background:#FF9900;color:#fff}
.bloc-palette{display:none;position:fixed;z-index:9999;background:#fff;border:1.5px solid #e0e8f0;border-radius:10px;box-shadow:0 8px 32px rgba(0,0,0,.15);padding:12px;width:560px;max-height:80vh;overflow-y:auto}
.bloc-palette.open{display:block}
.bp-title{font-size:.7rem;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.05em;padding:0 4px 8px;border-bottom:1px solid #f0f3f7;margin-bottom:10px}
.bp-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.bp-item{border:1.5px solid #e0e8f0;border-radius:8px;padding:10px;cursor:pointer;transition:border-color .15s,background .15s;background:#fafbfc}
.bp-item:hover{border-color:#1673B2;background:#f0f7fc}
.bp-item .bp-label{font-size:.7rem;font-weight:700;color:#1673B2;margin-bottom:6px;display:flex;align-items:center;gap:4px}
.bp-item .bp-preview{font-size:.68rem;line-height:1.4;pointer-events:none}
/* Mini-previews dans la palette */
.bp-preview .prev-titre{font-weight:700;color:#1673B2;font-size:.8rem}
.bp-preview .prev-btn-o{background:#FF9900;color:#fff;padding:2px 8px;border-radius:4px;display:inline-block;font-size:.68rem;font-weight:700}
.bp-preview .prev-btn-b{background:#1673B2;color:#fff;padding:2px 8px;border-radius:4px;display:inline-block;font-size:.68rem;font-weight:700}
.bp-preview .prev-btn-w{background:#fff;color:#1673B2;border:1.5px solid #1673B2;padding:2px 8px;border-radius:4px;display:inline-block;font-size:.68rem;font-weight:700}
.bp-preview .prev-card{background:#fff;border-radius:6px;padding:6px 8px;box-shadow:0 2px 6px rgba(0,0,0,.08);font-size:.68rem}
.bp-preview .prev-card h4{color:#1673B2;font-size:.72rem;margin-bottom:3px}
.bp-preview .prev-bleu{background:#e8f3fb;border-left:3px solid #1673B2;padding:4px 8px;font-size:.68rem;color:#0e3d6b}
.bp-preview .prev-vert{background:#e8f5e9;border-left:3px solid #2e7d32;padding:4px 8px;font-size:.68rem;color:#1b5e20}
.bp-preview .prev-orange{background:#fff8ee;border:1.5px solid #FF9900;padding:5px 8px;border-radius:5px;font-size:.68rem;color:#c47700}
.bp-preview .prev-li{padding-left:14px;font-size:.68rem;color:#555}
.bp-preview .prev-li::before{content:'✓ ';color:#FF9900;font-weight:700}
.wysiwyg-editor{flex:1;overflow-y:auto;padding:14px;outline:none;font-size:.88rem;line-height:1.7;cursor:text}
.wysiwyg-editor:focus{background:#fffef0}
textarea.code-editor{flex:1;padding:12px;font-family:'SF Mono',Monaco,Consolas,'Courier New',monospace;font-size:.8rem;line-height:1.6;border:none;outline:none;resize:none;background:#1e1e2e;color:#cdd6f4;tab-size:2}
textarea.code-editor::selection{background:#45475a}

/* ── Panneau droit : preview ── */
.right-pane{display:flex;flex-direction:column;overflow:hidden;background:#e8eef3}
.preview-header{padding:8px 14px;background:#fff;border-bottom:1px solid #e0e8f0;display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.preview-header span{font-size:.75rem;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.05em}
.preview-btns{display:flex;gap:4px}
.size-btn{padding:4px 10px;font-size:.75rem;border:1px solid #d0d8e0;border-radius:4px;background:#fafbfc;cursor:pointer;font-family:inherit}
.size-btn.active{background:#1673B2;color:#fff;border-color:#1673B2}
.preview-frame-wrap{flex:1;display:flex;align-items:center;justify-content:center;overflow:hidden;padding:12px;transition:all .3s}
iframe#preview{border:none;border-radius:8px;box-shadow:0 4px 24px rgba(0,0,0,.15);background:#fff;transition:all .3s}
iframe#preview.mobile{width:390px;max-height:844px}
iframe#preview.desktop{width:100%;height:100%}

/* ── CSS de la landing page agir — scopé au WYSIWYG ── */
/* Styles de base */
#wysiwyg-fr, #wysiwyg-nl { background: #f5f7fa; }
#wysiwyg-fr h2, #wysiwyg-nl h2 { color: #1673B2; font-size: 1.2rem; margin: 10px 0 8px; font-weight: 700; }
#wysiwyg-fr h3, #wysiwyg-nl h3 { color: #1673B2; font-size: 1rem; margin: 8px 0 6px; font-weight: 700; }
#wysiwyg-fr p, #wysiwyg-nl p { font-size: .9rem; color: #333; margin-bottom: 8px; }
#wysiwyg-fr a, #wysiwyg-nl a { color: #1673B2; }
/* Classes de la landing page */
#wysiwyg-fr .urgence-banner, #wysiwyg-nl .urgence-banner{ display: inline-block; background: #FF9900; color: #fff; padding: 6px 16px; border-radius: 20px; font-weight: 700; font-size: .85rem; }
#wysiwyg-fr .content, #wysiwyg-nl .content{ background: #f5f7fa; padding: 28px 20px; border-radius: 16px 16px 0 0; margin-top: 12px; }
#wysiwyg-fr .progress-card, #wysiwyg-nl .progress-card{ background: #fff; border: 2px solid #FF9900; border-radius: 12px; padding: 16px 18px; margin-bottom: 22px; }
#wysiwyg-fr .progress-title, #wysiwyg-nl .progress-title{ font-size: .75rem; font-weight: 700; color: #FF9900; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 6px; }
#wysiwyg-fr .progress-amounts, #wysiwyg-nl .progress-amounts{ display: flex; justify-content: space-between; align-items: baseline; font-weight: 700; color: #1673B2; margin-bottom: 8px; }
#wysiwyg-fr .progress-amounts .obj, #wysiwyg-nl .progress-amounts .obj{ color: #555; font-weight: 600; font-size: .85rem; }
#wysiwyg-fr .progress-bar, #wysiwyg-nl .progress-bar{ height: 10px; background: #e8eef3; border-radius: 5px; overflow: hidden; }
#wysiwyg-fr .progress-fill, #wysiwyg-nl .progress-fill{ height: 100%; background: linear-gradient(90deg, #FF9900, #FFB84D); border-radius: 5px; transition: width .8s; }
#wysiwyg-fr .why, #wysiwyg-nl .why{ margin-bottom: 24px; }
#wysiwyg-fr .why h2, #wysiwyg-nl .why h2{ color: #1673B2; font-size: 1.2rem; margin-bottom: 12px; font-weight: 700; }
#wysiwyg-fr .why ul, #wysiwyg-nl .why ul{ list-style: none; }
#wysiwyg-fr .why li, #wysiwyg-nl .why li{ padding: 8px 0; padding-left: 26px; position: relative; font-size: .95rem; }
#wysiwyg-fr .why li::before, #wysiwyg-nl .why li::before{ content: '✓'; position: absolute; left: 0; top: 8px; color: #FF9900; font-weight: 700; font-size: 1.1rem; }
#wysiwyg-fr .cta-block, #wysiwyg-nl .cta-block{ background: #fff; border-radius: 12px; padding: 22px 20px; margin-bottom: 14px; box-shadow: 0 4px 16px rgba(0,0,0,.06); }
#wysiwyg-fr .cta-block h3, #wysiwyg-nl .cta-block h3{ color: #1673B2; font-size: 1.05rem; font-weight: 700; margin-bottom: 6px; }
#wysiwyg-fr .cta-block p, #wysiwyg-nl .cta-block p{ font-size: .88rem; color: #555; margin-bottom: 14px; }
#wysiwyg-fr .btn, #wysiwyg-nl .btn{ display: block; width: 100%; padding: 14px; border-radius: 10px; text-decoration: none; text-align: center; font-weight: 700; font-size: 1rem; transition: transform .15s, box-shadow .15s; }
#wysiwyg-fr .btn:active, #wysiwyg-nl .btn:active{ transform: scale(.98); }
#wysiwyg-fr .btn-orange, #wysiwyg-nl .btn-orange{ background: #FF9900; color: #fff; box-shadow: 0 4px 14px rgba(255,153,0,.35); }
#wysiwyg-fr .btn-blue, #wysiwyg-nl .btn-blue{ background: #1673B2; color: #fff; box-shadow: 0 4px 14px rgba(22,115,178,.35); }
#wysiwyg-fr .btn-outline, #wysiwyg-nl .btn-outline{ background: #fff; color: #1673B2; border: 2px solid #1673B2; }
#wysiwyg-fr .divider, #wysiwyg-nl .divider{ text-align: center; color: #aaa; font-size: .8rem; margin: 16px 0; }
#wysiwyg-fr .share, #wysiwyg-nl .share{ margin-top: 24px; text-align: center; }
#wysiwyg-fr .share h3, #wysiwyg-nl .share h3{ color: #1673B2; font-size: 1rem; margin-bottom: 12px; }
#wysiwyg-fr .share-btns, #wysiwyg-nl .share-btns{ display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; }
#wysiwyg-fr .share-btn, #wysiwyg-nl .share-btn{ padding: 10px 16px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: .85rem; color: #fff; border: none; cursor: pointer; }
#wysiwyg-fr .share-wa, #wysiwyg-nl .share-wa{ background: #25D366; }
#wysiwyg-fr .share-fb, #wysiwyg-nl .share-fb{ background: #1877F2; }
#wysiwyg-fr .share-mail, #wysiwyg-nl .share-mail{ background: #555; }
#wysiwyg-fr .share-copy, #wysiwyg-nl .share-copy{ background: #FF9900; }
</style>
</head>
<body>

<?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

<div class="main">

  <!-- Top bar -->
  <div class="top-bar">
    <div>
      <h1>✏️ Page d'atterrissage <strong>/agir</strong></h1>
      <span class="hint">Contenu + CSS — aperçu en temps réel → <a href="https://www.casuffit.be/agir" target="_blank">casuffit.be/agir ↗</a></span>
    </div>
    <div class="btn-row">
      <?php if ($msg): ?><span class="flash flash-ok"><?= htmlspecialchars($msg) ?></span><?php endif; ?>
      <button class="btn btn-gray" onclick="resetCSS()">↺ Reset CSS</button>
      <button class="btn btn-orange" onclick="saveAll()">💾 Tout sauvegarder</button>
    </div>
  </div>

  <!-- Layout principal -->
  <div class="editor-layout">

    <!-- ── PANNEAU GAUCHE ── -->
    <div class="left-pane">

      <div class="tabs-nav">
        <button class="tab-btn active" onclick="switchTab('contenu-fr')">🇫🇷 Contenu FR</button>
        <button class="tab-btn" onclick="switchTab('contenu-nl')">🇳🇱 Contenu NL</button>
        <button class="tab-btn" onclick="switchTab('css')">🎨 CSS</button>
      </div>

      <!-- Contenu FR -->
      <div class="tab-pane active" id="tab-contenu-fr">
        <div class="editor-wrap">
          <div class="editor-label">Contenu HTML — version française</div>
          <div class="wysiwyg-toolbar">
            <button class="wt-btn" onclick="fmt('bold')"><b>G</b></button>
            <button class="wt-btn" onclick="fmt('italic')"><i>I</i></button>
            <div class="wt-sep"></div>
            <button class="wt-btn" onclick="fmtBlock('h2')">H2</button>
            <button class="wt-btn" onclick="fmtBlock('h3')">H3</button>
            <div class="wt-sep"></div>
            <button class="wt-btn" onclick="fmt('insertUnorderedList')">• —</button>
            <button class="wt-btn" onclick="fmt('insertOrderedList')">1.</button>
            <div class="wt-sep"></div>
            <button class="wt-btn" onclick="insertLink()">🔗</button>
            <button class="wt-btn" onclick="fmt('removeFormat')">Tx</button>
            <button class="bloc-palette-btn" onclick="togglePalette(this)">＋ Insérer un bloc ▾</button>
          </div>

          <!-- Palette de blocs -->
          <div class="bloc-palette" id="bloc-palette">
            <div class="bp-title">Choisissez un bloc à insérer</div>
            <div class="bp-grid">

              <div class="bp-item" onclick="insertBloc('titre-h2')">
                <div class="bp-label">📝 Titre H2</div>
                <div class="bp-preview"><div class="prev-titre">Mon titre de section</div></div>
              </div>

              <div class="bp-item" onclick="insertBloc('btn-orange')">
                <div class="bp-label">🟠 Bouton orange</div>
                <div class="bp-preview"><span class="prev-btn-o">Devenir membre</span></div>
              </div>

              <div class="bp-item" onclick="insertBloc('btn-bleu')">
                <div class="bp-label">🔵 Bouton bleu</div>
                <div class="bp-preview"><span class="prev-btn-b">Faire un don</span></div>
              </div>

              <div class="bp-item" onclick="insertBloc('btn-contour')">
                <div class="bp-label">⬜ Bouton contour</div>
                <div class="bp-preview"><span class="prev-btn-w">En savoir plus</span></div>
              </div>

              <div class="bp-item" onclick="insertBloc('carte-blanche')">
                <div class="bp-label">🃏 Carte blanche</div>
                <div class="bp-preview">
                  <div class="prev-card">
                    <h4>Titre de la carte</h4>
                    <div>Description courte ici</div>
                  </div>
                </div>
              </div>

              <div class="bp-item" onclick="insertBloc('carte-complete')">
                <div class="bp-label">📦 Carte avec bouton</div>
                <div class="bp-preview">
                  <div class="prev-card">
                    <h4>Titre</h4>
                    <div style="margin-bottom:4px">Description</div>
                    <span class="prev-btn-o">Bouton</span>
                  </div>
                </div>
              </div>

              <div class="bp-item" onclick="insertBloc('cadre-bleu')">
                <div class="bp-label">🔷 Cadre bleu</div>
                <div class="bp-preview"><div class="prev-bleu">Message important en bleu</div></div>
              </div>

              <div class="bp-item" onclick="insertBloc('cadre-vert')">
                <div class="bp-label">🟢 Cadre vert</div>
                <div class="bp-preview"><div class="prev-vert">Étapes ou instructions</div></div>
              </div>

              <div class="bp-item" onclick="insertBloc('cadre-orange')">
                <div class="bp-label">🟡 Encadré alerte</div>
                <div class="bp-preview"><div class="prev-orange">⚠ Point important</div></div>
              </div>

              <div class="bp-item" onclick="insertBloc('liste-coches')">
                <div class="bp-label">✅ Liste à coches</div>
                <div class="bp-preview">
                  <div class="prev-li">Point 1</div>
                  <div class="prev-li">Point 2</div>
                  <div class="prev-li">Point 3</div>
                </div>
              </div>

              <div class="bp-item" onclick="insertBloc('separateur')">
                <div class="bp-label">➖ Séparateur</div>
                <div class="bp-preview"><hr style="border:none;border-top:1px solid #e0e8f0;margin:4px 0"></div>
              </div>

              <div class="bp-item" onclick="insertBloc('texte-centre')">
                <div class="bp-label">⬛ Texte centré</div>
                <div class="bp-preview" style="text-align:center;color:#555;font-size:.68rem">Texte centré</div>
              </div>

              <div class="bp-item" onclick="openQRModal()">
                <div class="bp-label">📲 QR Code</div>
                <div class="bp-preview" style="text-align:center">
                  <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                    <rect x="2" y="2" width="16" height="16" rx="2" fill="#1673B2" opacity=".15"/>
                    <rect x="5" y="5" width="10" height="10" rx="1" fill="#1673B2" opacity=".4"/>
                    <rect x="22" y="2" width="16" height="16" rx="2" fill="#1673B2" opacity=".15"/>
                    <rect x="25" y="5" width="10" height="10" rx="1" fill="#1673B2" opacity=".4"/>
                    <rect x="2" y="22" width="16" height="16" rx="2" fill="#1673B2" opacity=".15"/>
                    <rect x="5" y="25" width="10" height="10" rx="1" fill="#1673B2" opacity=".4"/>
                    <rect x="22" y="22" width="5" height="5" rx="1" fill="#1673B2" opacity=".4"/>
                    <rect x="30" y="22" width="8" height="5" rx="1" fill="#1673B2" opacity=".4"/>
                    <rect x="22" y="30" width="8" height="5" rx="1" fill="#1673B2" opacity=".4"/>
                    <rect x="33" y="30" width="5" height="8" rx="1" fill="#1673B2" opacity=".4"/>
                  </svg>
                  <div style="font-size:.65rem;color:#1673B2;margin-top:2px">Générer & insérer</div>
                </div>
              </div>

            </div>
          </div>

          <!-- ── Modale QR Code ── -->
          <div id="qr-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:10000;align-items:center;justify-content:center">
            <div style="background:#fff;border-radius:12px;padding:24px;width:360px;box-shadow:0 12px 40px rgba(0,0,0,.2)">
              <h3 style="color:#1673B2;font-size:1rem;margin-bottom:16px;font-weight:700">📲 Insérer un QR Code</h3>

              <label style="display:block;font-size:.75rem;font-weight:700;color:#555;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px">URL</label>
              <input id="qr-url" type="text" value="https://www.casuffit.be/agir"
                     style="width:100%;padding:8px 10px;border:1.5px solid #d0d8e0;border-radius:6px;font-size:.88rem;margin-bottom:12px"
                     oninput="previewQR()">

              <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
                <div>
                  <label style="display:block;font-size:.75rem;font-weight:700;color:#555;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px">Taille</label>
                  <select id="qr-size" onchange="previewQR()"
                          style="width:100%;padding:7px;border:1.5px solid #d0d8e0;border-radius:6px;font-size:.85rem">
                    <option value="120">Petit (120px)</option>
                    <option value="180" selected>Moyen (180px)</option>
                    <option value="240">Grand (240px)</option>
                    <option value="320">Très grand (320px)</option>
                  </select>
                </div>
                <div>
                  <label style="display:block;font-size:.75rem;font-weight:700;color:#555;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px">Alignement</label>
                  <select id="qr-align"
                          style="width:100%;padding:7px;border:1.5px solid #d0d8e0;border-radius:6px;font-size:.85rem">
                    <option value="left">Gauche</option>
                    <option value="center" selected>Centré</option>
                    <option value="right">Droite</option>
                  </select>
                </div>
              </div>

              <label style="display:block;font-size:.75rem;font-weight:700;color:#555;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px">Légende (optionnel)</label>
              <input id="qr-caption" type="text" placeholder="Ex: casuffit.be/agir"
                     style="width:100%;padding:8px 10px;border:1.5px solid #d0d8e0;border-radius:6px;font-size:.88rem;margin-bottom:14px">

              <div style="text-align:center;margin-bottom:14px;background:#f5f7fa;border-radius:8px;padding:10px">
                <canvas id="qr-preview-canvas"></canvas>
              </div>

              <div style="display:flex;gap:8px">
                <button onclick="closeQRModal()" style="flex:1;padding:9px;border:1.5px solid #d0d8e0;border-radius:6px;background:#fff;cursor:pointer;font-family:inherit;font-size:.85rem">Annuler</button>
                <button onclick="doInsertQR()" style="flex:2;padding:9px;border:none;border-radius:6px;background:#1673B2;color:#fff;cursor:pointer;font-family:inherit;font-size:.85rem;font-weight:700">✅ Insérer le QR code</button>
              </div>
            </div>
          </div>
          <div id="wysiwyg-fr" class="wysiwyg-editor" contenteditable="true" oninput="syncFR(); updatePreview()"></div>
          <textarea id="f-contenu" name="contenu" style="display:none"><?= htmlspecialchars($contenu) ?></textarea>
        </div>
      </div>

      <!-- Contenu NL -->
      <div class="tab-pane" id="tab-contenu-nl">
        <div class="editor-wrap">
          <div class="editor-label">Contenu HTML — versie Nederlands</div>
          <div class="wysiwyg-toolbar">
            <button class="wt-btn" onclick="fmtNl('bold')"><b>G</b></button>
            <button class="wt-btn" onclick="fmtNl('italic')"><i>I</i></button>
            <button class="wt-btn" onclick="fmtBlockNl('h2')">H2</button>
            <button class="wt-btn" onclick="fmtBlockNl('h3')">H3</button>
            <button class="wt-btn" onclick="fmtNl('insertUnorderedList')">• —</button>
            <button class="wt-btn" onclick="fmtNl('insertOrderedList')">1.</button>
            <button class="wt-btn" onclick="fmt('removeFormat')">Tx</button>
            <button class="wt-btn btn-blue" onclick="autoTranslate()" style="margin-left:auto">🤖 Traduire auto</button>
          </div>
          <div id="wysiwyg-nl" class="wysiwyg-editor" contenteditable="true" oninput="syncNL()"></div>
          <textarea id="f-contenu-nl" name="contenu_nl" style="display:none"><?= htmlspecialchars($contenu_nl) ?></textarea>
        </div>
      </div>

      <!-- CSS -->
      <div class="tab-pane" id="tab-css">
        <div class="editor-wrap">
          <div class="editor-label" style="display:flex;align-items:center;justify-content:space-between">
            <span>CSS personnalisé — appliqué sur /agir</span>
            <span style="font-size:.68rem;color:#aaa">Les styles sont ajoutés après le CSS de base</span>
          </div>
          <textarea id="f-css" class="code-editor" spellcheck="false" oninput="updatePreview()" placeholder="/* Exemple : changer la couleur du bouton orange */
.btn-orange {
  background: #e65c00;
}

/* Changer le dégradé d'arrière-plan */
.hero {
  background: linear-gradient(180deg, #0e3d6b 0%, #1673B2 100%);
}

/* Police personnalisée */
body {
  font-family: Georgia, serif;
}"><?= htmlspecialchars($agir_css) ?></textarea>
        </div>
      </div>

    </div>

    <!-- ── PANNEAU DROIT : PREVIEW ── -->
    <div class="right-pane">
      <div class="preview-header">
        <span>Aperçu</span>
        <div class="preview-btns">
          <button class="size-btn active" onclick="setSize('mobile')" id="btn-mobile">📱 Mobile</button>
          <button class="size-btn" onclick="setSize('desktop')" id="btn-desktop">🖥 Desktop</button>
        </div>
      </div>
      <div class="preview-frame-wrap" id="preview-wrap">
        <iframe id="preview" class="mobile" src="/agir?_preview=1" style="width:390px;height:100%"></iframe>
      </div>
    </div>

  </div>

  <!-- Forms cachés pour POST -->
  <form id="save-form" method="POST" style="display:none">
    <?= csrf_field() ?>
    <input type="hidden" name="action" id="f-action" value="save_all">
    <textarea name="contenu" id="post-contenu"></textarea>
    <textarea name="contenu_nl" id="post-contenu-nl"></textarea>
    <textarea name="agir_css" id="post-css"></textarea>
  </form>

</div>

<script>
// ── Init éditeurs ──────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  const ta = document.getElementById('f-contenu');
  const ed = document.getElementById('wysiwyg-fr');
  if (ta && ed) ed.innerHTML = ta.value;

  const taNl = document.getElementById('f-contenu-nl');
  const edNl = document.getElementById('wysiwyg-nl');
  if (taNl && edNl) edNl.innerHTML = taNl.value;

  updatePreview();
});

// ── Onglets ───────────────────────────────────────────────────────────
function switchTab(id) {
  document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + id).classList.add('active');
  event.currentTarget.classList.add('active');
  updatePreview();
}

// ── Sync editors ──────────────────────────────────────────────────────
function syncFR() {
  document.getElementById('f-contenu').value = document.getElementById('wysiwyg-fr').innerHTML;
}
function syncNL() {
  document.getElementById('f-contenu-nl').value = document.getElementById('wysiwyg-nl').innerHTML;
}

// ── Formatage ─────────────────────────────────────────────────────────
function fmt(cmd) {
  document.getElementById('wysiwyg-fr').focus();
  document.execCommand(cmd, false, null);
  syncFR(); updatePreview();
}
function fmtNl(cmd) {
  document.getElementById('wysiwyg-nl').focus();
  document.execCommand(cmd, false, null);
  syncNL();
}
function fmtBlock(tag) {
  document.getElementById('wysiwyg-fr').focus();
  document.execCommand('formatBlock', false, tag);
  syncFR(); updatePreview();
}
function fmtBlockNl(tag) {
  document.getElementById('wysiwyg-nl').focus();
  document.execCommand('formatBlock', false, tag);
  syncNL();
}
function insertLink() {
  const url = prompt('URL du lien :');
  if (url) { document.getElementById('wysiwyg-fr').focus(); document.execCommand('createLink', false, url); syncFR(); updatePreview(); }
}
function togglePalette(btn) {
  const p = document.getElementById('bloc-palette');
  if (p.classList.contains('open')) { p.classList.remove('open'); return; }
  const rect = btn.getBoundingClientRect();
  p.style.top  = (rect.bottom + 6) + 'px';
  p.style.left = Math.min(rect.left, window.innerWidth - 580) + 'px';
  p.classList.add('open');
}
document.addEventListener('click', e => {
  const p = document.getElementById('bloc-palette');
  if (p && !p.contains(e.target) && !e.target.closest('.bloc-palette-btn')) p.classList.remove('open');
});

function insertBloc(type) {
  document.getElementById('bloc-palette').classList.remove('open');
  const ed = document.getElementById('wysiwyg-fr');
  ed.focus();
  const T = {
    'titre-h2':     '<h2>Titre de section</h2>',
    'btn-orange':   '<p><a href="#" class="btn btn-orange" style="display:inline-block;width:auto;padding:12px 24px;border-radius:10px;background:#FF9900;color:#fff;text-decoration:none;font-weight:700">Texte du bouton</a></p>',
    'btn-bleu':     '<p><a href="#" class="btn btn-blue" style="display:inline-block;width:auto;padding:12px 24px;border-radius:10px;background:#1673B2;color:#fff;text-decoration:none;font-weight:700">Texte du bouton</a></p>',
    'btn-contour':  '<p><a href="#" class="btn btn-outline" style="display:inline-block;width:auto;padding:12px 24px;border-radius:10px;background:#fff;color:#1673B2;border:2px solid #1673B2;text-decoration:none;font-weight:700">Texte du bouton</a></p>',
    'carte-blanche':'<div class="cta-block" style="background:#fff;border-radius:12px;padding:20px;margin-bottom:14px;box-shadow:0 4px 16px rgba(0,0,0,.06)"><h3 style="color:#1673B2;font-size:1rem;font-weight:700;margin-bottom:6px">Titre de la carte</h3><p style="font-size:.88rem;color:#555">Description de la carte.</p></div>',
    'carte-complete':'<div class="cta-block" style="background:#fff;border-radius:12px;padding:20px;margin-bottom:14px;box-shadow:0 4px 16px rgba(0,0,0,.06)"><h3 style="color:#1673B2;font-size:1rem;font-weight:700;margin-bottom:6px">Titre de la carte</h3><p style="font-size:.88rem;color:#555;margin-bottom:14px">Description courte et percutante.</p><a href="#" style="display:block;padding:12px;border-radius:10px;background:#FF9900;color:#fff;text-decoration:none;font-weight:700;text-align:center">Bouton d\'action</a></div>',
    'cadre-bleu':   '<div style="background:#e8f3fb;border-left:4px solid #1673B2;padding:12px 16px;margin:10px 0;border-radius:4px;color:#0e3d6b">Message ou information importante</div>',
    'cadre-vert':   '<div style="background:#e8f5e9;border-left:4px solid #2e7d32;padding:12px 16px;margin:10px 0;border-radius:4px"><strong style="color:#1b5e20;font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;display:block;margin-bottom:4px">Titre</strong>Texte du cadre vert</div>',
    'cadre-orange': '<div style="background:#fff8ee;border:2px solid #FF9900;padding:12px 16px;margin:10px 0;border-radius:6px"><strong style="color:#FF9900">⚠ Point important</strong><br>Texte de l\'alerte ici</div>',
    'liste-coches': '<ul style="list-style:none;padding:0;margin:10px 0"><li style="padding:6px 0 6px 24px;position:relative">✓ &nbsp;Premier point</li><li style="padding:6px 0 6px 24px;position:relative">✓ &nbsp;Deuxième point</li><li style="padding:6px 0 6px 24px;position:relative">✓ &nbsp;Troisième point</li></ul>',
    'separateur':   '<hr style="border:none;border-top:1px solid #e0e8f0;margin:16px 0">',
    'texte-centre': '<p style="text-align:center;color:#888;font-size:.85rem">Texte centré</p>',
  };
  document.execCommand('insertHTML', false, T[type] || '');
  syncFR(); updatePreview();
}

// ── Preview ───────────────────────────────────────────────────────────
let previewTimeout = null;
function updatePreview() {
  clearTimeout(previewTimeout);
  previewTimeout = setTimeout(_doUpdatePreview, 400);
}

function _doUpdatePreview() {
  const contenu = document.getElementById('wysiwyg-fr').innerHTML;
  const css = document.getElementById('f-css').value;
  const iframe = document.getElementById('preview');
  const iDoc = iframe.contentDocument || iframe.contentWindow.document;

  // Injecter le CSS custom dans la page preview
  try {
    let styleTag = iDoc.getElementById('custom-css-preview');
    if (!styleTag) {
      styleTag = iDoc.createElement('style');
      styleTag.id = 'custom-css-preview';
      iDoc.head.appendChild(styleTag);
    }
    styleTag.textContent = css;

    // Injecter le contenu dans la zone éditable de la preview
    const zone = iDoc.querySelector('.why, .content-editable-zone, [data-editable="agir"]');
    if (zone) zone.innerHTML = contenu;
  } catch(e) {
    // Cross-origin ou autre — recharger l'iframe
    iframe.src = '/agir?_preview=1&_t=' + Date.now();
  }
}

function setSize(mode) {
  const iframe = document.getElementById('preview');
  const wrap = document.getElementById('preview-wrap');
  document.getElementById('btn-mobile').classList.toggle('active', mode === 'mobile');
  document.getElementById('btn-desktop').classList.toggle('active', mode === 'desktop');
  if (mode === 'mobile') {
    iframe.className = 'mobile';
    iframe.style.width = '390px';
    iframe.style.height = '844px';
    wrap.style.alignItems = 'flex-start';
    wrap.style.overflowY = 'auto';
  } else {
    iframe.className = 'desktop';
    iframe.style.width = '100%';
    iframe.style.height = '100%';
    wrap.style.alignItems = 'stretch';
    wrap.style.overflowY = 'hidden';
  }
}

// ── Sauvegarder ───────────────────────────────────────────────────────
function saveAll() {
  document.getElementById('post-contenu').value    = document.getElementById('wysiwyg-fr').innerHTML;
  document.getElementById('post-contenu-nl').value = document.getElementById('wysiwyg-nl').innerHTML;
  document.getElementById('post-css').value        = document.getElementById('f-css').value;
  document.getElementById('f-action').value        = 'save_all';
  document.getElementById('save-form').submit();
}

function resetCSS() {
  if (!confirm('Remettre le CSS par défaut ?')) return;
  document.getElementById('f-css').value = '';
  updatePreview();
}

// ── Traduction automatique NL ─────────────────────────────────────────
async function autoTranslate() {
  const contenu = document.getElementById('wysiwyg-fr').innerHTML;
  if (!contenu.trim()) { alert('Le contenu FR est vide.'); return; }

  const btn = event.currentTarget;
  btn.textContent = '⏳ Traduction…';
  btn.disabled = true;

  try {
    const resp = await fetch('/admin/translate_auto.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: 'type=html&text=' + encodeURIComponent(contenu) + '&_csrf=<?= htmlspecialchars(csrf_token()) ?>'
    });
    const data = await resp.json();
    if (data.ok && data.nl) {
      document.getElementById('wysiwyg-nl').innerHTML = data.nl;
      syncNL();
    } else {
      alert('Erreur traduction : ' + (data.error || 'inconnue'));
    }
  } catch(e) {
    alert('Erreur réseau : ' + e.message);
  }
  btn.textContent = '🤖 Traduire auto';
  btn.disabled = false;
}
</script>
<!-- QRious — génération QR code côté client -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>
<script>
let _qr = null;

function openQRModal() {
  document.getElementById('bloc-palette').classList.remove('open');
  const modal = document.getElementById('qr-modal');
  modal.style.display = 'flex';
  previewQR();
}

function closeQRModal() {
  document.getElementById('qr-modal').style.display = 'none';
}

function previewQR() {
  const url  = document.getElementById('qr-url').value || 'https://www.casuffit.be/agir';
  const size = parseInt(document.getElementById('qr-size').value) || 180;
  const canvas = document.getElementById('qr-preview-canvas');
  _qr = new QRious({
    element: canvas,
    value: url,
    size: Math.min(size, 180),
    background: 'transparent',
    backgroundAlpha: 0,
    foreground: '#1673B2',
    level: 'Q',
    padding: 6,
  });
}

function doInsertQR() {
  const url     = document.getElementById('qr-url').value || 'https://www.casuffit.be/agir';
  const size    = parseInt(document.getElementById('qr-size').value) || 180;
  const align   = document.getElementById('qr-align').value;
  const caption = document.getElementById('qr-caption').value.trim();

  // Générer en vraie taille
  const tmpCanvas = document.createElement('canvas');
  new QRious({
    element: tmpCanvas,
    value: url,
    size: size,
    background: 'transparent',
    backgroundAlpha: 0,
    foreground: '#1673B2',
    level: 'Q',
    padding: Math.round(size * 0.04),
  });
  const dataUrl = tmpCanvas.toDataURL('image/png');

  // Construire le HTML
  const alignStyle = align === 'center' ? 'display:block;margin:10px auto' :
                     align === 'right'  ? 'display:block;margin:10px 0 10px auto' :
                                          'display:block;margin:10px 0';
  let html = `<div style="text-align:${align}">`;
  html += `<img src="${dataUrl}" width="${size}" height="${size}" alt="QR code ${url}" style="${alignStyle}">`;
  if (caption) {
    html += `<p style="font-size:.75rem;color:#888;text-align:center;margin:4px 0 10px">${caption}</p>`;
  }
  html += '</div>';

  // Insérer dans le WYSIWYG
  const ed = document.getElementById('wysiwyg-fr');
  ed.focus();
  document.execCommand('insertHTML', false, html);
  syncFR(); updatePreview();
  closeQRModal();
}

// Fermer modale sur clic extérieur
document.getElementById('qr-modal')?.addEventListener('click', function(e) {
  if (e.target === this) closeQRModal();
});
</script>
</body>
</html>
