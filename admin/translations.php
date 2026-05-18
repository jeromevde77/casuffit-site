<?php
// admin/translations.php — Dashboard de suivi des traductions FR/NL
require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();
$db = getDB();

// Vérifier que les colonnes _nl existent
$hasNl = false;
try {
    $cols = $db->query("SHOW COLUMNS FROM pages LIKE 'titre_nl'")->fetch();
    $hasNl = !empty($cols);
} catch (Exception $e) {}

$pages = [];
if ($hasNl) {
    try {
        $pages = $db->query("SELECT id, slug, titre, titre_nl, contenu, contenu_nl, nl_status, visible,
                                    updated_at, nl_translated_at
                             FROM pages
                             ORDER BY ordre ASC, titre ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
}

$stats = ['vide' => 0, 'auto' => 0, 'relu' => 0, 'desync' => 0];
foreach ($pages as $p) {
    $stats[$p['nl_status'] ?? 'vide']++;
    // Compter les pages désynchronisées (FR modifié après traduction NL)
    if (!empty($p['nl_translated_at']) && !empty($p['updated_at'])
        && ($p['nl_status'] ?? 'vide') !== 'vide'
        && strtotime($p['updated_at']) > strtotime($p['nl_translated_at'])) {
        $stats['desync']++;
    }
}
$total = count($pages);
?><!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Traductions NL — Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
  <?php include dirname(__DIR__) . '/includes/admin_sidebar_css.php'; ?>
    body { font-family: -apple-system, sans-serif; background: #f0f4f8; margin: 0; }
    .main { padding: 24px; max-width: 1200px; }
    h1 { color: #0e3d6b; }
    .stats { display: flex; gap: 14px; margin: 18px 0 28px; flex-wrap: wrap; }
    .stat-card { background: #fff; padding: 16px 22px; border-radius: 10px; border-left: 4px solid #ddd; min-width: 140px; }
    .stat-num  { font-size: 1.8rem; font-weight: 800; color: #0e3d6b; }
    .stat-lbl  { font-size: .72rem; color: #888; text-transform: uppercase; letter-spacing: .04em; margin-top: 3px; }
    .stat-vide { border-color: #999; }
    .stat-auto { border-color: #d97706; }
    .stat-relu { border-color: #27ae60; }
    table { width: 100%; background: #fff; border-collapse: collapse; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
    th { background: #0e3d6b; color: #fff; text-align: left; padding: 10px 14px; font-size: .78rem; }
    td { padding: 10px 14px; border-bottom: 1px solid #f0f4f8; font-size: .84rem; }
    tr:hover td { background: #f8fafc; }
    .badge { display: inline-block; padding: 3px 9px; border-radius: 12px; font-size: .68rem; font-weight: 700; }
    .badge-vide { background: #f0f0f0; color: #777; }
    .badge-auto { background: #fff7e6; color: #d97706; }
    .badge-relu { background: #e8f5e9; color: #27ae60; }
    .btn-edit  { background: #1673B2; color: #fff; padding: 5px 11px; border-radius: 5px; text-decoration: none; font-size: .76rem; font-weight: 600; }
    .btn-edit:hover { background: #0e5a96; }
    .btn-preview { background: #FF9900; color: #fff; padding: 5px 11px; border-radius: 5px; text-decoration: none; font-size: .76rem; font-weight: 600; margin-left: 4px; }
    .badge-invisible { background:#fee; color:#c00; font-size:.62rem; padding:1px 6px; border-radius:8px; margin-left:6px }
    .warn-box { background:#fff7e6; border:1px solid #FF9900; padding:14px; border-radius:8px; margin-bottom:24px; color:#7a4400; }
  </style>
</head>
<body>

<?php include dirname(__DIR__) . '/includes/admin_sidebar.php'; ?>

<main class="main">
  <h1>🌐 Tableau de bord — Traductions FR / NL</h1>

  <?php if (!$hasNl): ?>
    <div class="warn-box">
      ⚠ <strong>Migration BDD requise</strong> — les colonnes <code>titre_nl</code>, <code>contenu_nl</code>, <code>nl_status</code> ne sont pas encore présentes dans la table <code>pages</code>.<br>
      Exécutez le fichier <code>migrate_bilingual_nl.sql</code> dans phpMyAdmin pour activer le système de traduction.
    </div>
  <?php else: ?>

  <div class="stats">
    <div class="stat-card stat-vide"><div class="stat-num"><?= $stats['vide'] ?></div><div class="stat-lbl">⚪ Vide</div></div>
    <div class="stat-card stat-auto"><div class="stat-num"><?= $stats['auto'] ?></div><div class="stat-lbl">🤖 Auto (à relire)</div></div>
    <div class="stat-card stat-relu"><div class="stat-num"><?= $stats['relu'] ?></div><div class="stat-lbl">✅ Relu</div></div>
    <?php if ($stats['desync'] > 0): ?>
    <div class="stat-card" style="border-color:#c00"><div class="stat-num" style="color:#c00"><?= $stats['desync'] ?></div><div class="stat-lbl">⚠️ Désynchronisées</div></div>
    <?php endif; ?>
    <div class="stat-card"><div class="stat-num"><?= $total ?></div><div class="stat-lbl">Total pages</div></div>
  </div>

  <table>
    <thead>
      <tr>
        <th>Slug</th>
        <th>Titre FR</th>
        <th>Titre NL</th>
        <th>État</th>
        <th>Synchro</th>
        <th>Contenu</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($pages as $p):
        $status = $p['nl_status'] ?? 'vide';
        $badge  = ['vide'=>'⚪ Vide','auto'=>'🤖 Auto','relu'=>'✅ Relu'][$status];
        $lenFr  = strlen(strip_tags($p['contenu'] ?? ''));
        $lenNl  = strlen(strip_tags($p['contenu_nl'] ?? ''));
        $pct    = $lenFr > 0 ? round(100 * $lenNl / $lenFr) : 0;

        // Détection désynchronisation : FR modifié APRÈS la dernière traduction NL
        $updatedAt    = $p['updated_at']       ?? null;
        $translatedAt = $p['nl_translated_at'] ?? null;
        $isDesynced   = false;
        $syncLabel    = '—';
        if ($status !== 'vide' && $translatedAt && $updatedAt) {
            $isDesynced = strtotime($updatedAt) > strtotime($translatedAt);
            $syncLabel  = $isDesynced
                ? '<span style="color:#c00;font-weight:700">⚠️ Désynchronisée</span>'
                : '<span style="color:#27ae60">✓ À jour</span>';
        } elseif ($status !== 'vide' && !$translatedAt) {
            // Traduit avant qu'on ait le champ nl_translated_at — on ne sait pas
            $syncLabel = '<span style="color:#d97706">? Inconnu</span>';
        }
      ?>
      <tr <?= $isDesynced ? 'style="background:#fff8f0"' : '' ?>>
        <td><code><?= htmlspecialchars($p['slug']) ?></code><?= !$p['visible'] ? '<span class="badge-invisible">caché</span>' : '' ?></td>
        <td><?= htmlspecialchars($p['titre']) ?></td>
        <td><?= htmlspecialchars($p['titre_nl'] ?: '—') ?></td>
        <td><span class="badge badge-<?= $status ?>"><?= $badge ?></span></td>
        <td style="font-size:.78rem"><?= $syncLabel ?></td>
        <td>
          <?= number_format($lenNl) ?> / <?= number_format($lenFr) ?> car.
          <?php if ($lenFr > 0): ?>
            <span style="color:<?= $pct >= 50 ? '#27ae60' : ($pct > 0 ? '#d97706' : '#c00') ?>;font-size:.7rem;margin-left:6px">(<?= $pct ?>%)</span>
          <?php endif; ?>
        </td>
        <td>
          <a href="pages.php?edit=<?= $p['id'] ?>" class="btn-edit">✏ Éditer</a>
          <a href="<?= SITE_URL ?>/nl/?page=<?= htmlspecialchars($p['slug']) ?>" target="_blank" class="btn-preview">👁 Voir NL</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <p style="margin-top:24px;color:#888;font-size:.85rem;line-height:1.5">
    💡 Pour traduire automatiquement une page : ouvrez-la dans <strong>Éditer</strong>, dépliez le bloc <strong>🇳🇱 Version néerlandaise</strong>, cliquez sur <strong>🤖 Traduire automatiquement</strong>.<br>
    Le contenu est ensuite à relire avant de passer en mode <strong>✅ Relu</strong>.
  </p>

  <?php endif; ?>
</main>

</body>
</html>
