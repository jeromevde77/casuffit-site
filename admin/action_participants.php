<?php
// admin/action_participants.php — v1 — Membres volontaires pour l'action en justice
require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();

// Accès restreint : superadmin uniquement (donnée sensible — liste de requérants potentiels)
$role = $_SESSION['admin_role'] ?? '';
if ($role !== 'superadmin') {
    http_response_code(403);
    exit('<p style="font-family:Arial;padding:40px">Accès réservé au superadmin — cette liste contient des données judiciaires sensibles.</p>');
}

$db = getDB();

// Vérifier que la migration a été faite
$migre = false;
try {
    foreach ($db->query("SHOW COLUMNS FROM members")->fetchAll() as $c)
        if ($c['Field'] === 'action_participe') { $migre = true; break; }
} catch (Exception $e) {}

$rows = array();
if ($migre) {
    try {
        $rows = $db->query("SELECT prenom, nom, email, adresse, commune, telephone, code_membre, action_participe_at
                            FROM members
                            WHERE statut='actif' AND action_participe=1
                            ORDER BY TRIM(commune) ASC, nom ASC")->fetchAll();
    } catch (Exception $e) {}
}

// Export CSV (UTF-8 BOM pour Excel FR, séparateur point-virgule)
if (isset($_GET['export']) && $migre) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="participants_action_'.date('Ymd').'.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, array('Prénom','Nom','Email','Adresse','Commune','Téléphone','Code membre','Date du choix'), ';');
    foreach ($rows as $r) {
        fputcsv($out, array($r['prenom'], $r['nom'], $r['email'], $r['adresse'], $r['commune'],
                            $r['telephone'], $r['code_membre'],
                            $r['action_participe_at'] ? date('d/m/Y H:i', strtotime($r['action_participe_at'])) : ''), ';');
    }
    fclose($out); exit;
}

// Répartition par commune
$par_commune = array();
foreach ($rows as $r) {
    $c = trim($r['commune']) ?: '(non renseignée)';
    $par_commune[$c] = ($par_commune[$c] ?? 0) + 1;
}
arsort($par_commune);

$total_membres = 0;
try { $total_membres = (int)$db->query("SELECT COUNT(*) FROM members WHERE statut='actif'")->fetchColumn(); } catch (Exception $e) {}
$pct = $total_membres ? round(count($rows) / $total_membres * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Participants à l'action — Admin</title>
<style>
<?php include __DIR__.'/../includes/admin_sidebar_css.php'; ?>
body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333;margin:0}
*{box-sizing:border-box}
.page-header{padding:22px 28px 0}
.page-header h1{font-size:1.2rem;font-weight:800;color:#0e3d6b;margin:0 0 4px}
.page-header p{font-size:.82rem;color:#888;margin:0}
.card{background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.08);margin:18px 28px;padding:20px 24px}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin:18px 28px}
.st{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.07);padding:16px 18px;border-top:3px solid #1673B2}
.st .v{font-size:1.7rem;font-weight:800;color:#1673B2;line-height:1}
.st .l{font-size:.72rem;color:#999;text-transform:uppercase;letter-spacing:.04em;margin-top:5px}
.btn{padding:10px 20px;border:none;border-radius:8px;font-size:.85rem;font-weight:700;cursor:pointer;font-family:inherit;text-decoration:none;display:inline-block}
.btn-x{background:#1a7a45;color:#fff}.btn-x:hover{background:#146138}
table{width:100%;border-collapse:collapse;font-size:.84rem}
th{text-align:left;padding:9px 10px;background:#f7fafd;color:#0e3d6b;font-size:.7rem;text-transform:uppercase;letter-spacing:.04em;border-bottom:2px solid #e6eef7;position:sticky;top:0}
td{padding:9px 10px;border-bottom:1px solid #f0f4f8;vertical-align:top}
tr:hover td{background:#fafcfe}
.cm{display:inline-block;background:#e8f3fb;color:#1673B2;font-size:.72rem;font-weight:700;padding:2px 9px;border-radius:11px}
.empty{text-align:center;padding:50px 20px;color:#aaa}
.empty .big{font-size:2.4rem;margin-bottom:10px}
.warn{background:#fff8ee;border:1.5px solid #FF9900;color:#7a4500;padding:13px 17px;border-radius:10px;font-size:.84rem;line-height:1.6;margin:18px 28px}
.rgpd{background:#eff6ff;border:1px solid #cfe2fb;color:#1673B2;padding:12px 16px;border-radius:9px;font-size:.78rem;line-height:1.6;margin-top:16px}
.cbar{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
.cpill{background:#f0f7fd;border:1px solid #cfe2fb;border-radius:20px;padding:5px 13px;font-size:.78rem;color:#0e3d6b}
.cpill b{color:#1673B2}
</style>
</head>
<body>
<?php include __DIR__.'/../includes/admin_sidebar.php'; ?>
<div class="wrap">

<div class="page-header">
  <h1>⚖️ Participants à l'action en justice</h1>
  <p>Membres ayant demandé à être contactés pour participer en leur nom</p>
</div>

<?php if (!$migre): ?>
  <div class="warn">
    ⚠ Les colonnes de participation n'existent pas encore en base.
    Lancez d'abord <code>outils-action-migration.php</code> à la racine du site.
  </div>
<?php else: ?>

<div class="stats">
  <div class="st"><div class="v"><?= count($rows) ?></div><div class="l">Volontaires</div></div>
  <div class="st"><div class="v"><?= $total_membres ?></div><div class="l">Membres actifs</div></div>
  <div class="st"><div class="v"><?= $pct ?>%</div><div class="l">Taux de réponse</div></div>
  <div class="st"><div class="v"><?= count($par_commune) ?></div><div class="l">Communes</div></div>
</div>

<?php if ($par_commune): ?>
<div class="card">
  <div style="font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#888">Répartition par commune</div>
  <div class="cbar">
    <?php foreach ($par_commune as $c => $n): ?>
      <span class="cpill"><?= htmlspecialchars($c) ?> <b><?= $n ?></b></span>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px">
    <div style="font-size:.92rem;font-weight:800;color:#0e3d6b">Liste des volontaires</div>
    <?php if ($rows): ?><a href="?export=1" class="btn btn-x">⬇ Exporter en CSV (Excel)</a><?php endif; ?>
  </div>

  <?php if (!$rows): ?>
    <div class="empty"><div class="big">📭</div>
      Aucun membre ne s'est encore porté volontaire.<br>
      <span style="font-size:.82rem">Envoyez la newsletter pour lancer l'appel.</span></div>
  <?php else: ?>
    <div style="overflow-x:auto">
    <table>
      <tr><th>Nom</th><th>Adresse</th><th>Commune</th><th>Contact</th><th>Code</th><th>Date du choix</th></tr>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td><strong><?= htmlspecialchars($r['prenom'].' '.$r['nom']) ?></strong></td>
        <td><?= htmlspecialchars($r['adresse'] ?: '—') ?></td>
        <td><span class="cm"><?= htmlspecialchars($r['commune'] ?: '—') ?></span></td>
        <td style="font-size:.78rem;color:#666">
          <?= htmlspecialchars($r['email']) ?><?= $r['telephone'] ? '<br>'.htmlspecialchars($r['telephone']) : '' ?>
        </td>
        <td style="font-size:.76rem;color:#888"><?= htmlspecialchars($r['code_membre']) ?></td>
        <td style="font-size:.78rem;color:#888">
          <?= $r['action_participe_at'] ? date('d/m/Y H:i', strtotime($r['action_participe_at'])) : '—' ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    </div>
  <?php endif; ?>

  <div class="rgpd">
    <strong>🔒 Données sensibles — RGPD</strong><br>
    Cette liste est constituée sur base du consentement explicite et révocable de chaque membre.
    Finalité unique : constituer le groupe de requérants volontaires pour l'action judiciaire.
    Accès réservé au superadmin. Ne pas diffuser hors du conseil d'administration et du conseil juridique
    de l'ASBL. Chaque membre peut retirer son consentement à tout moment depuis son espace personnel.
  </div>
</div>

<?php endif; ?>
</div>
</body>
</html>
