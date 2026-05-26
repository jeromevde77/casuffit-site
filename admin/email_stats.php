<?php
// v2 — fix affichage CSS sidebar (inclusion dans <style>)
require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();
$db = getDB();

$table_ok = true;
$stats = []; $details = []; $campagne_sel = $_GET['campagne'] ?? '';
try {
    // Agrégat par campagne
    $stats = $db->query("
        SELECT campagne,
               COUNT(*) AS envoyes,
               SUM(CASE WHEN premiere_ouverture IS NOT NULL THEN 1 ELSE 0 END) AS ouverts,
               MAX(derniere_ouverture) AS derniere
        FROM email_opens
        GROUP BY campagne
        ORDER BY derniere DESC
    ")->fetchAll();

    // Détail d'une campagne sélectionnée
    if ($campagne_sel) {
        $st = $db->prepare("SELECT email, premiere_ouverture, derniere_ouverture, nb_ouvertures
                            FROM email_opens WHERE campagne = ? ORDER BY premiere_ouverture IS NULL, derniere_ouverture DESC");
        $st->execute([$campagne_sel]);
        $details = $st->fetchAll();
    }
} catch (Throwable $e) {
    $table_ok = false;
}

function pct($o, $e) { return $e > 0 ? round($o * 100 / $e) : 0; }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ouvertures emails — Admin Ça suffit !</title>
<style>
<?php include __DIR__ . '/../includes/admin_sidebar_css.php'; ?>
.admin-content { margin-left: 240px; min-height: 100vh; }
@media (max-width: 768px) { .admin-content { margin-left: 0; padding-top: 52px; } }
.es-wrap { padding: 24px; max-width: 1100px; }
.es-wrap h1 { font-size:1.3rem; color:#0e3d6b; margin-bottom:18px; }
.es-card { background:#fff; border:1px solid #e0e8f0; border-radius:10px; padding:18px; margin-bottom:18px; }
table { width:100%; border-collapse:collapse; }
th,td { text-align:left; padding:9px 12px; border-bottom:1px solid #eef2f6; font-size:.85rem; }
th { font-size:.72rem; text-transform:uppercase; letter-spacing:.04em; color:#888; }
.bar { background:#eef2f6; border-radius:6px; height:18px; overflow:hidden; min-width:120px; }
.bar span { display:block; height:100%; background:#1673B2; }
.taux { font-weight:700; color:#1673B2; }
.lien-camp { color:#1673B2; text-decoration:none; font-weight:600; }
.muted { color:#aaa; }
.note { background:#fff8ee; border:1px solid #FF9900; border-radius:8px; padding:12px 16px; font-size:.82rem; color:#555; margin-bottom:18px; line-height:1.5; }
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>
<div class="admin-content">
<div class="es-wrap">
  <h1>📬 Ouvertures des emails</h1>

  <?php if (!$table_ok): ?>
    <div class="note">La table <code>email_opens</code> n'existe pas encore. Exécutez <code>migrate_email_opens.sql</code> dans phpMyAdmin pour activer le suivi.</div>
  <?php else: ?>

  <div class="note">
    Le taux d'ouverture est <strong>indicatif</strong> : certains clients mail (Apple Mail, Gmail) préchargent ou bloquent les images,
    ce qui peut sous-estimer ou surestimer les chiffres. À lire comme une tendance, pas une mesure exacte.
  </div>

  <div class="es-card">
    <h3 style="margin:0 0 12px;font-size:.95rem;color:#0e3d6b">Par campagne</h3>
    <?php if (empty($stats)): ?>
      <p class="muted" style="font-size:.85rem">Aucun email tracké pour l'instant. Les ouvertures apparaîtront ici après vos prochains envois.</p>
    <?php else: ?>
    <table>
      <tr><th>Campagne</th><th>Envoyés</th><th>Ouverts</th><th>Taux</th><th>Dernière ouverture</th></tr>
      <?php foreach ($stats as $s): $p = pct($s['ouverts'], $s['envoyes']); ?>
      <tr>
        <td><a class="lien-camp" href="?campagne=<?= urlencode($s['campagne']) ?>"><?= htmlspecialchars($s['campagne']) ?></a></td>
        <td><?= (int)$s['envoyes'] ?></td>
        <td><?= (int)$s['ouverts'] ?></td>
        <td>
          <div style="display:flex;align-items:center;gap:8px">
            <div class="bar"><span style="width:<?= $p ?>%"></span></div>
            <span class="taux"><?= $p ?>%</span>
          </div>
        </td>
        <td class="muted"><?= $s['derniere'] ? date('d/m/Y H:i', strtotime($s['derniere'])) : '—' ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>

  <?php if ($campagne_sel && $details): ?>
  <div class="es-card">
    <h3 style="margin:0 0 12px;font-size:.95rem;color:#0e3d6b">Détail — <?= htmlspecialchars($campagne_sel) ?></h3>
    <table>
      <tr><th>Email</th><th>Ouvert ?</th><th>1re ouverture</th><th>Dernière</th><th>Nb</th></tr>
      <?php foreach ($details as $d): ?>
      <tr>
        <td style="font-size:.8rem"><?= htmlspecialchars($d['email']) ?></td>
        <td><?= $d['premiere_ouverture'] ? '<span style="color:#1a7a4a;font-weight:700">✓ oui</span>' : '<span class="muted">non</span>' ?></td>
        <td class="muted"><?= $d['premiere_ouverture'] ? date('d/m/Y H:i', strtotime($d['premiere_ouverture'])) : '—' ?></td>
        <td class="muted"><?= $d['derniere_ouverture'] ? date('d/m/Y H:i', strtotime($d['derniere_ouverture'])) : '—' ?></td>
        <td><?= (int)$d['nb_ouvertures'] ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>

  <?php endif; ?>
</div>
</div>
</body>
</html>
