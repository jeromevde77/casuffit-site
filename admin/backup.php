<?php
require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();
$db = getDB();

// ── Action : export SQL ──────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'sql') {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="backup_casuffit_' . date('Y-m-d_His') . '.sql"');
    header('Cache-Control: no-cache');

    echo "-- ================================================================\n";
    echo "-- Backup BDD ca suffit !\n";
    echo "-- Date : " . date('Y-m-d H:i:s') . "\n";
    echo "-- ================================================================\n\n";
    echo "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";

    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        echo "\n-- TABLE `$table`\n";
        $create = $db->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
        $key = array_keys($create)[1];
        echo "DROP TABLE IF EXISTS `$table`;\n";
        echo $create[$key] . ";\n\n";

        $rows = $db->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) { echo "-- (vide)\n"; continue; }

        $cols = '`' . implode('`, `', array_keys($rows[0])) . '`';
        foreach ($rows as $row) {
            $vals = array_map(function($v) use ($db) {
                if ($v === null) return 'NULL';
                return "'" . addslashes($v) . "'";
            }, array_values($row));
            echo "INSERT INTO `$table` ($cols) VALUES (" . implode(', ', $vals) . ");\n";
        }
    }
    echo "\nSET FOREIGN_KEY_CHECKS = 1;\n";
    exit;
}

// ── Action : export ZIP fichiers ─────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'zip') {
    if (!class_exists('ZipArchive')) {
        die('ZipArchive non disponible sur ce serveur.');
    }
    $tmpFile = tempnam(sys_get_temp_dir(), 'backup_') . '.zip';
    $zip = new ZipArchive();
    $zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    $root = realpath(__DIR__ . '/..');
    $exclude = ['/tmp', '/.git', '/node_modules', '/vendor'];

    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($iter as $file) {
        $path = $file->getRealPath();
        $rel  = substr($path, strlen($root) + 1);
        foreach ($exclude as $ex) {
            if (strpos($rel, ltrim($ex, '/')) === 0) continue 2;
        }
        $zip->addFile($path, $rel);
    }
    $zip->close();

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="backup_site_casuffit_' . date('Y-m-d_His') . '.zip"');
    header('Content-Length: ' . filesize($tmpFile));
    header('Cache-Control: no-cache');
    readfile($tmpFile);
    unlink($tmpFile);
    exit;
}

// ── Stats pour l'affichage ───────────────────────────────────────────────
$nb_tables  = $db->query("SHOW TABLES")->rowCount();
$nb_membres = $db->query("SELECT COUNT(*) FROM members")->fetchColumn();
$nb_sub     = $db->query("SELECT COUNT(*) FROM subscribers")->fetchColumn();
$nb_pages   = $db->query("SELECT COUNT(*) FROM pages")->fetchColumn();
$nb_news    = $db->query("SELECT COUNT(*) FROM news")->fetchColumn();

// Compter les fichiers
$root = realpath(__DIR__ . '/..');
$nb_files = 0; $total_bytes = 0;
$iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
foreach ($iter as $f) { $nb_files++; $total_bytes += $f->getSize(); }
$size_mb = round($total_bytes / 1024 / 1024, 1);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<?php include __DIR__ . '/../includes/admin_pwa_head.php'; ?>
<title>Backup — Admin ca suffit !</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333}
<?php include __DIR__ . '/../includes/admin_sidebar_css.php'; ?>
.main{margin-left:240px;padding:24px;min-height:100vh}
.page-header{margin-bottom:24px}
.page-header h1{font-size:1.3rem;font-weight:800;color:#0e3d6b}
.page-header p{font-size:.82rem;color:#888;margin-top:4px}
.backup-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px}
.backup-card{background:#fff;border-radius:14px;padding:28px;box-shadow:0 2px 12px rgba(0,0,0,.06)}
.backup-card h2{font-size:1rem;font-weight:800;color:#0e3d6b;margin-bottom:6px}
.backup-card p{font-size:.8rem;color:#888;margin-bottom:20px;line-height:1.5}
.stats{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px}
.stat-item{text-align:center;background:#f7fafd;border-radius:8px;padding:10px 14px}
.stat-val{font-size:1.2rem;font-weight:800;color:#1673B2}
.stat-lbl{font-size:.65rem;color:#aaa;text-transform:uppercase;letter-spacing:.05em}
.btn-dl{display:block;width:100%;padding:14px;text-align:center;border:none;border-radius:10px;font-size:.9rem;font-weight:700;cursor:pointer;text-decoration:none;transition:opacity .15s}
.btn-sql{background:#0e3d6b;color:#fff}
.btn-sql:hover{opacity:.85}
.btn-zip{background:#F5A623;color:#fff}
.btn-zip:hover{opacity:.85}
.info-box{background:#e8f0fa;border-radius:10px;padding:14px;font-size:.78rem;color:#1673B2;line-height:1.6;margin-top:16px}
.info-box strong{display:block;font-weight:700;margin-bottom:4px}
.last-backup{font-size:.72rem;color:#bbb;text-align:center;margin-top:12px}
.restore-card{background:#fff;border-radius:14px;padding:24px;box-shadow:0 2px 12px rgba(0,0,0,.06);margin-bottom:20px}
.restore-card h2{font-size:1rem;font-weight:800;color:#0e3d6b;margin-bottom:14px}
.restore-card ol{font-size:.82rem;color:#555;line-height:2.2;padding-left:20px}
.restore-card code{background:#f0f4f8;padding:1px 5px;border-radius:3px;font-family:monospace}
@media(max-width:700px){.backup-grid{grid-template-columns:1fr}.main{margin-left:0;padding:16px}}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>
<main class="main">
  <div class="page-header">
    <h1>Backup &amp; Export</h1>
    <p>Sauvegarde complete de la base de donnees et des fichiers du site</p>
  </div>

  <div class="backup-grid">
    <div class="backup-card">
      <h2>Base de donnees (SQL)</h2>
      <p>Export complet de toutes les tables MySQL.</p>
      <div class="stats">
        <div class="stat-item"><div class="stat-val"><?= $nb_tables ?></div><div class="stat-lbl">Tables</div></div>
        <div class="stat-item"><div class="stat-val"><?= $nb_membres ?></div><div class="stat-lbl">Membres</div></div>
        <div class="stat-item"><div class="stat-val"><?= $nb_sub ?></div><div class="stat-lbl">Abonnes</div></div>
        <div class="stat-item"><div class="stat-val"><?= $nb_pages ?></div><div class="stat-lbl">Pages</div></div>
      </div>
      <a href="backup.php?action=sql" class="btn-dl btn-sql">Telecharger le SQL</a>
      <div class="info-box">
        <strong>Contenu</strong>
        Toutes les tables avec DROP + CREATE + INSERT. A importer dans phpMyAdmin pour restaurer la BDD.
      </div>
      <div class="last-backup">Genere a la demande · <?= date('d/m/Y H:i') ?></div>
    </div>

    <div class="backup-card">
      <h2>Fichiers du site (ZIP)</h2>
      <p>Archive ZIP de tous les fichiers PHP, assets, templates.</p>
      <div class="stats">
        <div class="stat-item"><div class="stat-val"><?= $nb_files ?></div><div class="stat-lbl">Fichiers</div></div>
        <div class="stat-item"><div class="stat-val"><?= $size_mb ?> Mo</div><div class="stat-lbl">Taille</div></div>
        <div class="stat-item"><div class="stat-val"><?= $nb_news ?></div><div class="stat-lbl">Articles</div></div>
      </div>
      <a href="backup.php?action=zip" class="btn-dl btn-zip"
         onclick="this.textContent='Generation en cours...';setTimeout(()=>this.textContent='Telecharger le ZIP',8000)">
        Telecharger le ZIP
      </a>
      <div class="info-box">
        <strong>Contenu</strong>
        Tous les fichiers PHP, assets, medias. Le fichier config.php est inclus (identifiants BDD).
      </div>
      <div class="last-backup">Genere a la demande · <?= date('d/m/Y H:i') ?></div>
    </div>
  </div>

  <div class="restore-card">
    <h2>Procedure de restauration</h2>
    <ol>
      <li>Creer une nouvelle base de donnees MySQL (utf8mb4_unicode_ci) dans phpMyAdmin</li>
      <li>Importer le fichier <code>.sql</code> dans cette nouvelle base</li>
      <li>Decompresser le <code>.zip</code> et uploader les fichiers sur OVH via FTP</li>
      <li>Modifier <code>config.php</code> avec les nouveaux identifiants BDD si necessaire</li>
      <li>Verifier que <code>.htaccess</code> est bien uploade (fichier cache)</li>
    </ol>
  </div>
</main>
</body>
</html>
