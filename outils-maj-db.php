<?php
/* outils-maj-db.php — v1
 * Page-outil : applique les mises à jour de schéma en attente, de façon idempotente.
 * Protégée par requireAdmin(). À SUPPRIMER après usage (voir CLAUDE.md).
 */
require_once __DIR__ . '/config.php';
session_start();
requireAdmin();
$db = getDB();

function tblExists(PDO $db, string $t): bool {
    try { $db->query("SELECT 1 FROM `$t` LIMIT 1"); return true; }
    catch (Throwable $e) { return false; }
}
function colExists(PDO $db, string $t, string $c): bool {
    try { return (bool) $db->query("SHOW COLUMNS FROM `$t` LIKE " . $db->quote($c))->fetch(); }
    catch (Throwable $e) { return false; }
}

$log = [];
function step(array &$log, string $label, callable $fn): void {
    try { $log[] = ['ok', $label, $fn()]; }
    catch (Throwable $e) { $log[] = ['err', $label, $e->getMessage()]; }
}

// 1) Table member_emails (historique des e-mails aux membres)
step($log, 'Table member_emails', function () use ($db) {
    if (tblExists($db, 'member_emails')) return 'déjà présente';
    $db->exec("CREATE TABLE `member_emails` (
        `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `member_id`      INT UNSIGNED NOT NULL,
        `sujet`          VARCHAR(255) NOT NULL,
        `message`        TEXT,
        `envoye_par`     VARCHAR(100) DEFAULT NULL,
        `statut`         ENUM('envoye','echec') NOT NULL DEFAULT 'envoye',
        `pieces_jointes` VARCHAR(500) DEFAULT NULL,
        `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `member_id` (`member_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    return 'créée';
});

// 2) member_emails.pieces_jointes (si table préexistante sans la colonne)
step($log, 'member_emails.pieces_jointes', function () use ($db) {
    if (!tblExists($db, 'member_emails')) return 'table absente — ignoré';
    if (colExists($db, 'member_emails', 'pieces_jointes')) return 'déjà présente';
    $db->exec("ALTER TABLE `member_emails` ADD COLUMN `pieces_jointes` VARCHAR(500) DEFAULT NULL AFTER `statut`");
    return 'ajoutée';
});

// 3) member_dons.merci_envoye (idempotence du mail de remerciement)
step($log, 'member_dons.merci_envoye', function () use ($db) {
    if (colExists($db, 'member_dons', 'merci_envoye')) return 'déjà présente';
    $db->exec("ALTER TABLE `member_dons` ADD COLUMN `merci_envoye` TINYINT(1) NOT NULL DEFAULT 0 AFTER `statut`");
    return 'ajoutée';
});

// 4) member_dons.merci_date
step($log, 'member_dons.merci_date', function () use ($db) {
    if (colExists($db, 'member_dons', 'merci_date')) return 'déjà présente';
    $db->exec("ALTER TABLE `member_dons` ADD COLUMN `merci_date` DATETIME DEFAULT NULL AFTER `merci_envoye`");
    return 'ajoutée';
});

$nb_err = count(array_filter($log, fn($l) => $l[0] === 'err'));

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">
<title>Mise à jour de la base</title>
<style>
body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333;max-width:720px;margin:40px auto;padding:0 16px;line-height:1.6}
.box{background:#fff;border:1px solid #d6e2ee;border-radius:10px;padding:24px}
h2{margin-top:0;color:#0e3d6b}
table{width:100%;border-collapse:collapse;font-size:.9rem;margin-top:8px}
td{padding:8px 10px;border-bottom:1px solid #f0f0f0}
.ok{color:#27ae60;font-weight:700}.err{color:#c53030;font-weight:700}
.badge{display:inline-block;padding:2px 9px;border-radius:10px;font-size:.72rem;font-weight:700}
.b-ok{background:#e8f8f0;color:#27ae60}.b-err{background:#fde8e8;color:#c53030}
.summary{padding:12px 14px;border-radius:8px;margin:14px 0;font-weight:700}
.s-ok{background:#e8f8f0;color:#276749}.s-err{background:#fde8e8;color:#c53030}
.warn{background:#fff3e0;border-left:4px solid #FF9900;padding:12px;border-radius:6px;margin-top:18px;font-size:.9rem}
a.btn{display:inline-block;margin-top:14px;background:#1673B2;color:#fff;padding:9px 16px;border-radius:8px;text-decoration:none}
</style></head><body><div class="box">
<h2>🛠️ Mise à jour de la base de données</h2>

<?php if ($nb_err === 0): ?>
  <div class="summary s-ok">✅ Mise à jour terminée — aucune erreur.</div>
<?php else: ?>
  <div class="summary s-err">⚠️ Terminé avec <?= $nb_err ?> erreur(s) — voir le détail ci-dessous.</div>
<?php endif; ?>

<table>
<?php foreach ($log as [$st, $label, $detail]): ?>
  <tr>
    <td><?= htmlspecialchars($label) ?></td>
    <td style="text-align:right">
      <?php if ($st === 'ok'): ?>
        <span class="badge b-ok">OK</span> <span style="color:#888"><?= htmlspecialchars($detail) ?></span>
      <?php else: ?>
        <span class="badge b-err">ERREUR</span> <span class="err"><?= htmlspecialchars($detail) ?></span>
      <?php endif; ?>
    </td>
  </tr>
<?php endforeach; ?>
</table>

<a class="btn" href="/admin/members.php">→ Aller à l'admin Membres</a>

<div class="warn"><strong>⚠️ Sécurité :</strong> cette page applique des modifications de schéma. <strong>Supprimez <code>outils-maj-db.php</code></strong> du dépôt et du serveur dès que la mise à jour est faite (CLAUDE.md).</div>
</div></body></html>
