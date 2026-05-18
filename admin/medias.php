<?php
// admin/medias.php — Upload et gestion des images
if (!defined('MEDIAS_DIR')) define('MEDIAS_DIR', __DIR__ . '/../medias/');
require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();
$db = getDB();

// Créer le dossier médias si nécessaire
if (!is_dir(MEDIAS_DIR)) { mkdir(MEDIAS_DIR, 0755, true); }

$msg = ''; $error = '';

// Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['media']['tmp_name'])) {
    $file    = $_FILES['media'];
    $alt     = htmlspecialchars(trim(isset($_POST['alt_text']) ? $_POST['alt_text'] : ''), ENT_QUOTES, 'UTF-8');
    $allowed = array('image/jpeg','image/png','image/gif','image/webp','image/svg+xml');
    $ext_map = array('image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp','image/svg+xml'=>'svg');

    if ($file['error'] !== UPLOAD_ERR_OK) { $error = 'Erreur upload.'; }
    elseif ($file['size'] > 5*1024*1024) { $error = 'Fichier trop lourd (max 5 MB).'; }
    elseif (!in_array($file['type'], $allowed)) { $error = 'Format non autorisé (JPG, PNG, GIF, WebP, SVG).'; }
    else {
        $ext      = $ext_map[$file['type']];
        $filename = date('Ymd_His') . '_' . preg_replace('/[^a-z0-9]/', '', strtolower(pathinfo($file['name'], PATHINFO_FILENAME))) . '.' . $ext;
        $dest     = MEDIAS_DIR . $filename;
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $db->prepare("INSERT INTO medias (fichier, nom, type, taille) VALUES (?,?,?,?)")
               ->execute(array($filename, $file['name'], $file['type'], $file['size']));
            $msg = '✅ Image uploadée : '.$filename;
        } else { $error = 'Impossible de déplacer le fichier.'; }
    }
}

// Supprimer
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $stmt = $db->prepare("SELECT fichier FROM medias WHERE id=?");
    $stmt->execute(array(intval($_GET['delete'])));
    $media = $stmt->fetch();
    if ($media) {
        $path = MEDIAS_DIR . $media['fichier'];
        if (file_exists($path)) { unlink($path); }
        $db->prepare("DELETE FROM medias WHERE id=?")->execute(array(intval($_GET['delete'])));
        header('Location: medias.php?msg='.urlencode('Média supprimé.')); exit;
    }
}

$msg = isset($_GET['msg']) ? $_GET['msg'] : $msg;
$medias = $db->query("SELECT * FROM medias ORDER BY uploaded_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<?php include __DIR__ . '/../includes/admin_pwa_head.php'; ?>
  <title>Médias — Admin ça suffit !</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333}
    <?php include __DIR__ . '/../includes/admin_sidebar_css.php'; ?>
    .main{margin-left:240px;padding:28px;max-width:1100px}
    .page-title{font-size:1.3rem;font-weight:800;color:#0e3d6b;margin-bottom:20px}
    .card{background:#fff;border-radius:12px;padding:22px;box-shadow:0 2px 10px rgba(0,0,0,0.06);margin-bottom:20px}
    .card h3{font-size:.9rem;font-weight:700;color:#0e3d6b;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid #eee}
    label{display:block;font-size:.78rem;font-weight:600;color:#555;margin-bottom:5px;margin-top:12px}
    input[type=text]{width:100%;padding:9px 12px;border:1.5px solid #dde4ed;border-radius:7px;font-size:.88rem;outline:none;font-family:inherit}
    input:focus{border-color:#1673B2}
    .upload-zone{border:2px dashed #bee3f8;border-radius:10px;padding:28px;text-align:center;background:#f0f7ff;cursor:pointer}
    .upload-zone:hover,.upload-zone.drag{border-color:#1673B2;background:#e6f1fb}
    .upload-zone input{display:none}
    .upload-zone .icon{font-size:2.5rem;margin-bottom:8px}
    .btn-primary{background:#1673B2;color:#fff;border:none;padding:10px 20px;border-radius:7px;font-size:.82rem;font-weight:700;cursor:pointer;font-family:inherit;margin-top:12px}
    .btn-red{background:#e74c3c;color:#fff;border:none;padding:4px 9px;border-radius:5px;font-size:.7rem;cursor:pointer;font-family:inherit}
    .flash-ok{background:#e8f8f0;color:#276749;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:.85rem;border-left:3px solid #48bb78}
    .flash-err{background:#fde8e8;color:#c53030;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:.85rem;border-left:3px solid #fc8181}
    .media-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px}
    .media-item{background:#f8f9fa;border-radius:8px;overflow:hidden;border:1px solid #eee;position:relative}
    .media-item img{width:100%;height:110px;object-fit:cover;display:block}
    .media-item .media-info{padding:8px;font-size:.7rem;color:#666}
    .media-item .media-name{font-weight:600;color:#333;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .media-item .media-url{color:#1673B2;cursor:pointer;word-break:break-all;font-size:.68rem}
    .media-item .media-actions{display:flex;gap:5px;justify-content:space-between;align-items:center;padding:6px 8px;border-top:1px solid #eee}
    .media-copy{background:#1673B2;color:#fff;border:none;border-radius:4px;padding:3px 8px;font-size:.68rem;cursor:pointer}
    .copied{background:#27ae60!important}
    .hint{font-size:.72rem;color:#aaa;margin-top:4px}
  
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
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

<div class="main">
  <div class="page-title">🖼 Médiathèque</div>

  <?php if ($msg): ?><div class="flash-ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="flash-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <!-- Upload -->
  <div class="card">
    <h3>⬆ Uploader une image</h3>
    <form method="POST" enctype="multipart/form-data" id="upload-form">
      <div class="upload-zone" id="upload-zone" onclick="document.getElementById('media-file').click()">
        <input type="file" id="media-file" name="media" accept="image/*" onchange="updateZone(this)">
        <div class="icon" id="upload-icon">🖼</div>
        <p id="upload-text" style="color:#555;font-size:.85rem">Cliquez ou glissez une image ici</p>
        <small style="color:#aaa;font-size:.72rem">JPG, PNG, GIF, WebP, SVG — max 5 MB</small>
      </div>
      <label>Texte alternatif (description pour l'accessibilité)</label>
      <input type="text" name="alt_text" placeholder="Ex: Avion survolant Waterloo à basse altitude" id="alt-input">
      <div class="hint">Ce texte sera utilisé comme attribut alt= dans le HTML.</div>
      <button type="submit" class="btn-primary" id="btn-upload" style="display:none">⬆ Uploader</button>
    </form>
  </div>

  <!-- Grille médias -->
  <div class="card">
    <h3>📁 Images disponibles (<?= count($medias) ?>)</h3>
    <?php if (empty($medias)): ?>
      <p style="color:#aaa;font-size:.88rem;text-align:center;padding:20px">Aucune image. Uploadez-en une ci-dessus.</p>
    <?php else: ?>
    <div class="media-grid">
      <?php foreach ($medias as $m): ?>
      <div class="media-item">
        <img src="<?= htmlspecialchars(MEDIAS_URL . $m['fichier']) ?>" alt="<?= htmlspecialchars($m['nom']) ?>">
        <div class="media-info">
          <div class="media-name" title="<?= htmlspecialchars($m['nom']) ?>"><?= htmlspecialchars($m['nom']) ?></div>
          <div style="color:#aaa"><?= round($m['taille']/1024) ?> KB — <?= date('d/m/Y', strtotime($m['uploaded_at'])) ?></div>
        </div>
        <div class="media-actions">
          <button class="media-copy" onclick="copyUrl('<?= htmlspecialchars(MEDIAS_URL . $m['fichier']) ?>', this)" title="Copier l'URL">📋 Copier URL</button>
          <a href="medias.php?delete=<?= $m['id'] ?>" onclick="return confirm('Supprimer cette image ?')">
            <button class="btn-red">🗑</button>
          </a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
var zone = document.getElementById('upload-zone');
zone.addEventListener('dragover', function(e){e.preventDefault();this.classList.add('drag');});
zone.addEventListener('dragleave', function(){this.classList.remove('drag');});
zone.addEventListener('drop', function(e){
  e.preventDefault();this.classList.remove('drag');
  document.getElementById('media-file').files = e.dataTransfer.files;
  updateZone({files:[e.dataTransfer.files[0]]});
});

function updateZone(input) {
  var f = input.files[0];
  if (f) {
    document.getElementById('upload-icon').textContent = '✅';
    document.getElementById('upload-text').textContent = f.name + ' (' + (f.size/1024).toFixed(0) + ' KB)';
    document.getElementById('btn-upload').style.display = 'inline-block';
    // Auto-remplir alt si vide
    var alt = document.getElementById('alt-input');
    if (!alt.value) alt.value = f.name.replace(/\.[^.]+$/, '').replace(/[-_]/g, ' ');
  }
}

function copyUrl(url, btn) {
  navigator.clipboard.writeText(url).then(function(){
    btn.textContent = '✅ Copié !';
    btn.classList.add('copied');
    setTimeout(function(){ btn.textContent = '📋 Copier URL'; btn.classList.remove('copied'); }, 2000);
  });
}
</script>
</body>
</html>
