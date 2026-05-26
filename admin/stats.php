<?php
require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();
require_once __DIR__ . '/../includes/csrf.php';
$db = getDB();

$msg = '';

// Sauvegarde de l'URL Looker Studio
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['looker_url'])) {
    $url = trim($_POST['looker_url']);
    $db->prepare("INSERT INTO site_config (cle, valeur, label, groupe)
                  VALUES ('looker_url', :v, 'URL Looker Studio', 'stats')
                  ON DUPLICATE KEY UPDATE valeur = :v")
       ->execute([':v' => $url]);
    $msg = 'URL sauvegardée.';
}

// Récupérer l'URL actuelle
$looker_url = cfg('looker_url', '');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Statistiques — Admin Ça suffit !</title>
<style>
<?php include __DIR__ . '/../includes/admin_sidebar_css.php'; ?>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333;font-size:14px}

/* Conteneur principal : laisse la place à la sidebar fixe (240px) */
.admin-content { margin-left: 240px; min-height: 100vh; }
@media (max-width: 768px) { .admin-content { margin-left: 0; padding-top: 52px; } }

.stats-wrap { padding: 24px; max-width: 1400px; }
.stats-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; flex-wrap:wrap; gap:12px; }
.stats-header h1 { font-size:1.3rem; font-weight:700; color:#0e3d6b; margin:0; }
.stats-config { background:#f0f7ff; border:1.5px solid #c8dff0; border-radius:8px; padding:14px 18px; margin-bottom:20px; }
.stats-config h3 { font-size:.82rem; font-weight:700; color:#1673B2; text-transform:uppercase; letter-spacing:.04em; margin:0 0 10px; }
.stats-config-row { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
.stats-config input[type=url] { flex:1; min-width:300px; padding:8px 12px; border:1.5px solid #c8dff0; border-radius:6px; font-size:.85rem; font-family:inherit; }
.stats-config input[type=url]:focus { outline:none; border-color:#1673B2; }
.btn-save { background:#1673B2; color:#fff; border:none; border-radius:6px; padding:8px 18px; font-size:.85rem; font-weight:700; cursor:pointer; white-space:nowrap; }
.btn-save:hover { background:#0e5a96; }
.stats-help { font-size:.72rem; color:#888; margin-top:8px; line-height:1.5; }
.stats-help a { color:#1673B2; }
.stats-frame { width:100%; border:none; border-radius:8px; box-shadow:0 2px 12px rgba(0,0,0,.1); display:block; }
.stats-placeholder { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:16px; background:#f8fafb; border:2px dashed #c8ddf5; border-radius:8px; min-height:400px; color:#888; text-align:center; padding:40px; }
.stats-placeholder svg { opacity:.3; }
.stats-placeholder h2 { font-size:1.1rem; color:#0e3d6b; margin:0; }
.stats-placeholder p { font-size:.82rem; margin:0; line-height:1.6; max-width:500px; }
.stats-placeholder ol { text-align:left; font-size:.82rem; line-height:2; max-width:480px; }
.stats-placeholder code { background:#e8f0f8; padding:2px 6px; border-radius:4px; font-size:.78rem; }
.msg-ok { background:#f0fef4; border:1px solid #86efac; color:#166534; padding:8px 14px; border-radius:6px; font-size:.82rem; margin-bottom:12px; }
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

<div class="admin-content">
<div class="stats-wrap">

  <div class="stats-header">
    <h1>📊 Statistiques — Google Analytics</h1>
    <a href="https://analytics.google.com" target="_blank" class="btn-save" style="background:#e8710a">
      Ouvrir GA4 ↗
    </a>
  </div>

  <?php if ($msg): ?>
    <div class="msg-ok">✓ <?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <!-- Config URL -->
  <div class="stats-config">
    <h3>⚙ Configuration — URL du rapport Looker Studio</h3>
    <form method="post">
      <div class="stats-config-row">
        <input type="url" name="looker_url"
               value="<?= htmlspecialchars($looker_url) ?>"
               placeholder="https://lookerstudio.google.com/embed/reporting/…"
               required>
        <button type="submit" class="btn-save">💾 Sauvegarder</button>
      </div>
    </form>
    <div class="stats-help">
      Comment obtenir l'URL : dans <a href="https://lookerstudio.google.com" target="_blank">Looker Studio</a>,
      ouvre ton rapport → <strong>Fichier → Paramètres du rapport → Intégrer le rapport</strong>
      → coche <em>"Autoriser l'intégration"</em> → copie l'URL <code>https://lookerstudio.google.com/embed/reporting/…</code>
    </div>
  </div>

  <!-- Iframe ou placeholder -->
  <?php if ($looker_url): ?>
    <iframe src="<?= htmlspecialchars($looker_url) ?>"
            class="stats-frame"
            style="height: calc(100vh - 220px); min-height: 600px;"
            allowfullscreen
            sandbox="allow-storage-access-by-user-activation allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox">
    </iframe>
  <?php else: ?>
    <div class="stats-placeholder">
      <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="#1673B2" stroke-width="1.5">
        <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/>
        <line x1="6" y1="20" x2="6" y2="14"/>
      </svg>
      <h2>Rapport Looker Studio non configuré</h2>
      <p>Pour afficher tes statistiques Google Analytics ici, suis ces étapes :</p>
      <ol>
        <li>Va sur <a href="https://lookerstudio.google.com" target="_blank"><strong>lookerstudio.google.com</strong></a></li>
        <li>Crée un rapport → connecte ta source <strong>Google Analytics (GA4)</strong></li>
        <li>Ajoute les métriques souhaitées (sessions, pages vues, pays…)</li>
        <li><strong>Fichier → Paramètres du rapport → Intégrer le rapport</strong></li>
        <li>Coche <em>"Autoriser l'intégration"</em> et copie l'URL <code>embed/reporting/…</code></li>
        <li>Colle l'URL dans le champ ci-dessus et clique Sauvegarder</li>
      </ol>
    </div>
  <?php endif; ?>

</div>
</div>
</body>
</html>
