<?php
// admin/email_templates.php — Éditeur de templates email
require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/email_renderer.php';
$db = getDB();

$msg = ''; $error = '';

// ── Init templates si table vide ────────────────────────────────────────
try {
    $count = $db->query("SELECT COUNT(*) FROM email_templates")->fetchColumn();
    if ($count == 0) initEmailTemplates($db);
} catch (Exception $e) {
    $error = 'Table email_templates manquante — exécutez migrate_email_templates.sql';
}

// ── Sauvegarder un template ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_template'])) {
    csrf_verify();
    $slug       = preg_replace('/[^a-z_]/', '', $_POST['slug'] ?? '');
    $sujet_fr   = trim($_POST['sujet_fr'] ?? '');
    $sujet_nl   = trim($_POST['sujet_nl'] ?? '');
    $contenu_fr = $_POST['contenu_fr'] ?? '';
    $contenu_nl = $_POST['contenu_nl'] ?? '';
    if ($slug && $sujet_fr) {
        $db->prepare("UPDATE email_templates SET sujet_fr=?, sujet_nl=?, contenu_fr=?, contenu_nl=? WHERE slug=?")
           ->execute([$sujet_fr, $sujet_nl, $contenu_fr, $contenu_nl, $slug]);
        $msg = '✅ Template "' . $slug . '" sauvegardé.';
    }
}

// ── Reset un template aux valeurs par défaut ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_template'])) {
    csrf_verify();
    $slug = preg_replace('/[^a-z_]/', '', $_POST['slug'] ?? '');
    $defaults = getDefaultEmailTemplates();
    if ($slug && isset($defaults[$slug])) {
        $d = $defaults[$slug];
        $db->prepare("UPDATE email_templates SET sujet_fr=?, sujet_nl=?, contenu_fr=?, contenu_nl=? WHERE slug=?")
           ->execute([$d['sujet_fr'], $d['sujet_nl'] ?? '', $d['contenu_fr'], $d['contenu_nl'] ?? '', $slug]);
        $msg = '↺ Template "' . $slug . '" réinitialisé aux valeurs par défaut.';
    }
}

// ── Charger tous les templates ───────────────────────────────────────────
$templates = [];
try {
    $templates = $db->query("SELECT * FROM email_templates ORDER BY id")->fetchAll();
} catch (Exception $e) {}

// Template actif (depuis URL)
$active_slug = $_GET['tpl'] ?? ($templates[0]['slug'] ?? '');
$active = null;
foreach ($templates as $t) { if ($t['slug'] === $active_slug) { $active = $t; break; } }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Templates email — Admin</title>
<style>
<?php include __DIR__ . '/../includes/admin_sidebar_css.php'; ?>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333;font-size:14px}
.main{margin-left:240px;padding:0;height:100vh;display:flex;flex-direction:column;overflow:hidden}
@media(max-width:768px){.main{margin-left:0;padding-top:52px}}

/* Top bar */
.top-bar{background:#fff;border-bottom:1px solid #e0e8f0;padding:10px 20px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;flex-wrap:wrap;gap:8px}
.top-bar h1{font-size:1rem;color:#1673B2;font-weight:700}

/* Layout */
.editor-layout{flex:1;display:grid;grid-template-columns:240px 1fr 1fr;overflow:hidden}
@media(max-width:1100px){.editor-layout{grid-template-columns:200px 1fr}}

/* Sidebar templates */
.tpl-list{border-right:1px solid #e0e8f0;background:#fafbfc;overflow-y:auto;flex-shrink:0}
.tpl-item{padding:12px 14px;cursor:pointer;border-bottom:1px solid #f0f3f7;font-size:.82rem;display:block;text-decoration:none;color:#333;transition:background .1s}
.tpl-item:hover{background:#f0f7fc}
.tpl-item.active{background:#e8f3fb;border-left:3px solid #1673B2;color:#1673B2;font-weight:700}
.tpl-item .tpl-slug{font-size:.68rem;color:#aaa;margin-top:2px}

/* Éditeur */
.editor-pane{display:flex;flex-direction:column;overflow:hidden;border-right:1px solid #e0e8f0}
.editor-pane form{flex:1;display:flex;flex-direction:column;overflow:hidden;min-height:0}
.editor-section{flex:1;display:flex;flex-direction:column;overflow:hidden}
.editor-section-head{padding:8px 14px;background:#f5f7fa;border-bottom:1px solid #e0e8f0;font-size:.72rem;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.04em;flex-shrink:0;display:flex;align-items:center;justify-content:space-between}
.lang-tabs{display:flex;border-bottom:1px solid #e0e8f0;flex-shrink:0}
.lang-tab{padding:8px 16px;font-size:.78rem;font-weight:700;cursor:pointer;border:none;background:none;color:#888;border-bottom:2px solid transparent;font-family:inherit}
.lang-tab.active{color:#1673B2;border-bottom-color:#1673B2;background:#fff}
.tpl-section{display:none}
.tpl-section.active{display:flex;flex-direction:column;flex:1;overflow:hidden}
.sujet-input{padding:10px 14px;border:none;border-bottom:1px solid #e0e8f0;font-family:inherit;font-size:.9rem;width:100%;outline:none;background:#fff;flex-shrink:0}
.sujet-input::placeholder{color:#bbb}
.code-editor{flex:1;padding:14px;font-family:'SF Mono',Monaco,Consolas,monospace;font-size:.78rem;line-height:1.6;border:none;outline:none;resize:none;background:#1e1e2e;color:#cdd6f4;tab-size:2}

/* Variables badge */
.vars-bar{padding:6px 14px;background:#fff8ee;border-bottom:1px solid #ffe4b5;display:flex;gap:6px;flex-wrap:wrap;align-items:center;flex-shrink:0}
.var-badge{background:#fff;border:1px solid #FF9900;color:#c47700;padding:2px 7px;border-radius:10px;font-size:.7rem;font-family:monospace;cursor:pointer}
.var-badge:hover{background:#FF9900;color:#fff}

/* Preview */
.preview-pane{display:flex;flex-direction:column;overflow:hidden}
@media(max-width:1100px){.preview-pane{display:none}}
.preview-head{padding:8px 14px;background:#f5f7fa;border-bottom:1px solid #e0e8f0;font-size:.72rem;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.04em;flex-shrink:0;display:flex;align-items:center;justify-content:space-between}
iframe#email-preview{flex:1;border:none;background:#f0f4f8}

/* Boutons */
.btn{padding:7px 14px;border-radius:6px;border:none;font-size:.82rem;font-weight:600;cursor:pointer;font-family:inherit;text-decoration:none}
.btn-blue{background:#1673B2;color:#fff}
.btn-orange{background:#FF9900;color:#fff}
.btn-gray{background:#e8eef3;color:#555}
.btn-red{background:#fee2e2;color:#c53030;border:1px solid #fca5a5}
.flash{padding:6px 14px;border-radius:5px;font-size:.82rem;font-weight:600}
.flash-ok{background:#e8f5e9;color:#2e7d32}
.flash-err{background:#fee2e2;color:#c53030}

.empty-state{padding:40px;text-align:center;color:#aaa;font-size:.9rem}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

<div class="main">
  <div class="top-bar">
    <div>
      <h1>✉ Templates email</h1>
      <span style="font-size:.72rem;color:#888">Éditez le sujet et le contenu HTML de chaque email envoyé par le site</span>
    </div>
    <div style="display:flex;gap:8px;align-items:center">
      <?php if($msg): ?><span class="flash flash-ok"><?= htmlspecialchars($msg) ?></span><?php endif; ?>
      <?php if($error): ?><span class="flash flash-err"><?= htmlspecialchars($error) ?></span><?php endif; ?>
      <?php if($active): ?>
        <form method="POST" style="display:inline" onsubmit="return confirm('Remettre le template par défaut ?')">
          <?= csrf_field() ?>
          <input type="hidden" name="slug" value="<?= htmlspecialchars($active['slug']) ?>">
          <button type="submit" name="reset_template" class="btn btn-gray">↺ Défaut</button>
        </form>
        <button class="btn btn-orange" onclick="saveTemplate()">💾 Sauvegarder</button>
      <?php endif; ?>
    </div>
  </div>

  <div class="editor-layout">

    <!-- ── Liste des templates ── -->
    <div class="tpl-list">
      <?php if (empty($templates)): ?>
        <div class="empty-state">Aucun template.<br>Exécutez migrate_email_templates.sql</div>
      <?php else: ?>
        <?php foreach ($templates as $t): ?>
          <a href="?tpl=<?= urlencode($t['slug']) ?>" class="tpl-item <?= $t['slug']===$active_slug?'active':'' ?>">
            <?= htmlspecialchars($t['label']) ?>
            <div class="tpl-slug"><?= htmlspecialchars($t['slug']) ?></div>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- ── Éditeur ── -->
    <div class="editor-pane">
      <?php if (!$active): ?>
        <div class="empty-state">Sélectionnez un template dans la liste</div>
      <?php else:
        $vars = json_decode($active['variables'] ?? '[]', true) ?: [];
      ?>
      <form method="POST" id="tpl-form">
        <?= csrf_field() ?>
        <input type="hidden" name="save_template" value="1">
        <input type="hidden" name="slug" value="<?= htmlspecialchars($active['slug']) ?>">

        <div class="vars-bar">
          <span style="font-size:.68rem;font-weight:700;color:#c47700">Variables :</span>
          <?php foreach ($vars as $v): ?>
            <span class="var-badge" onclick="insertVar('<?= htmlspecialchars($v) ?>')" title="Cliquer pour insérer"><?= htmlspecialchars($v) ?></span>
          <?php endforeach; ?>
        </div>

        <div class="lang-tabs">
          <button type="button" class="lang-tab active" onclick="switchLang('fr',this)">🇫🇷 Français</button>
          <button type="button" class="lang-tab" onclick="switchLang('nl',this)">🇳🇱 Nederlands</button>
        </div>

        <!-- FR -->
        <div class="tpl-section active" id="section-fr">
          <input class="sujet-input" type="text" name="sujet_fr" id="sujet-fr"
                 value="<?= htmlspecialchars($active['sujet_fr']) ?>"
                 placeholder="Sujet de l'email (FR)">
          <textarea class="code-editor" name="contenu_fr" id="contenu-fr"
                    spellcheck="false" oninput="updatePreview()"><?= htmlspecialchars($active['contenu_fr'] ?? '') ?></textarea>
        </div>

        <!-- NL -->
        <div class="tpl-section" id="section-nl">
          <input class="sujet-input" type="text" name="sujet_nl" id="sujet-nl"
                 value="<?= htmlspecialchars($active['sujet_nl']) ?>"
                 placeholder="Onderwerp (NL)">
          <textarea class="code-editor" name="contenu_nl" id="contenu-nl"
                    spellcheck="false"><?= htmlspecialchars($active['contenu_nl'] ?? '') ?></textarea>
        </div>
      </form>
      <?php endif; ?>
    </div>

    <!-- ── Preview ── -->
    <div class="preview-pane">
      <div class="preview-head">
        <span>Aperçu</span>
        <div style="display:flex;gap:6px">
          <button class="btn btn-gray" style="font-size:.72rem;padding:4px 10px" onclick="substituteVars()">🔤 Simuler variables</button>
        </div>
      </div>
      <?php if ($active): ?>
      <iframe id="email-preview"></iframe>
      <?php else: ?>
      <div class="empty-state">Sélectionnez un template</div>
      <?php endif; ?>
    </div>

  </div>
</div>

<?php if ($active): ?>
<script>
// ── Init preview ──────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', updatePreview);

function updatePreview() {
  const html = document.getElementById('contenu-fr')?.value || '';
  const iframe = document.getElementById('email-preview');
  if (!iframe) return;
  const doc = iframe.contentDocument || iframe.contentWindow.document;
  doc.open(); doc.write(html); doc.close();
}

// ── Changer de langue ─────────────────────────────────────────────────────
function switchLang(lang, btn) {
  document.querySelectorAll('.tpl-section').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('.lang-tab').forEach(b => b.classList.remove('active'));
  document.getElementById('section-' + lang).classList.add('active');
  btn.classList.add('active');

  if (lang === 'fr') updatePreview();
  else {
    const html = document.getElementById('contenu-nl')?.value || '';
    const iframe = document.getElementById('email-preview');
    if (iframe) { const d = iframe.contentDocument||iframe.contentWindow.document; d.open(); d.write(html); d.close(); }
  }
}

// ── Insérer une variable ─────────────────────────────────────────────────
function insertVar(v) {
  const ta = document.activeElement;
  const target = (ta && (ta.tagName === 'TEXTAREA' || ta.tagName === 'INPUT')) ? ta
               : document.getElementById('contenu-fr');
  if (!target) return;
  const s = target.selectionStart, e = target.selectionEnd;
  target.value = target.value.slice(0, s) + v + target.value.slice(e);
  target.selectionStart = target.selectionEnd = s + v.length;
  target.focus();
  updatePreview();
}

// ── Simuler les variables dans l'aperçu ──────────────────────────────────
function substituteVars() {
  const vars = <?= json_encode($vars) ?>;
  const demos = {
    '{{prenom}}':       'Jérôme',
    '{{nom}}':          'Vanden Eynde',
    '{{email}}':        'jerome@example.com',
    '{{email_nouveau}}':'nouveau@example.com',
    '{{code_membre}}':  'MBR-2026-00001',
    '{{magic_url}}':    'https://www.casuffit.be/membre/magic.php?token=exemple',
    '{{url}}':          'https://www.casuffit.be/membre/inscription.php?invite=exemple',
    '{{confirm_url}}':  'https://www.casuffit.be/newsletter/confirm.php?token=exemple',
    '{{lien}}':         'https://www.casuffit.be/membre/confirm_email.php?token=exemple',
    '{{expiry}}':       '21/05/2026 à 14h00',
  };
  const section = document.querySelector('.tpl-section.active');
  const ta = section?.querySelector('textarea');
  if (!ta) return;
  let html = ta.value;
  vars.forEach(v => { if (demos[v]) html = html.replaceAll(v, demos[v]); });
  const iframe = document.getElementById('email-preview');
  if (iframe) { const d = iframe.contentDocument||iframe.contentWindow.document; d.open(); d.write(html); d.close(); }
}

// ── Sauvegarder ──────────────────────────────────────────────────────────
function saveTemplate() {
  document.getElementById('tpl-form').submit();
}
</script>
<?php endif; ?>
</body>
</html>
