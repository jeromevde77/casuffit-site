<?php
/**
 * outils-wix-to-members.php — OUTIL PONCTUEL
 * Convertit les abonnés importés de Wix (source_import='wix', actifs, pas déjà membres)
 * en MEMBRES — même sans réponse à l'invitation.
 *   - Sans paramètre : APERÇU (aucune modification).
 *   - ?go=1          : EXÉCUTE la conversion, puis s'auto-supprime.
 * Protégé par session admin. À retirer du dépôt après usage.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/membre/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireAdmin();

$db = getDB();
$go = isset($_GET['go']) && $_GET['go'] === '1';

// Compteurs récap
$nb_wix        = (int)$db->query("SELECT COUNT(*) FROM subscribers WHERE source_import='wix'")->fetchColumn();
$nb_wix_desab  = (int)$db->query("SELECT COUNT(*) FROM subscribers WHERE source_import='wix' AND statut='desabonne'")->fetchColumn();
$nb_deja_membre= (int)$db->query("SELECT COUNT(*) FROM subscribers s WHERE s.source_import='wix' AND EXISTS (SELECT 1 FROM members m WHERE m.email=s.email)")->fetchColumn();

// Éligibles : Wix + actifs + pas déjà membres
$eligibles = $db->query("SELECT s.* FROM subscribers s
    WHERE s.source_import='wix' AND s.statut='actif'
      AND NOT EXISTS (SELECT 1 FROM members m WHERE m.email = s.email)
    ORDER BY s.id")->fetchAll();
$nb_eligibles = count($eligibles);

$created = 0; $skipped = 0; $errors = []; $selfDeleted = false;

if ($go) {
    foreach ($eligibles as $s) {
        try {
            $token_unsub = bin2hex(random_bytes(32));
            $db->prepare("INSERT INTO members (email, prenom, nom, adresse, commune, telephone, token_unsub, statut, newsletter, code_membre, ogm, subscriber_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'actif', 1, 'TEMP', 'TEMP', ?)")
               ->execute([$s['email'], $s['prenom'], $s['nom'], $s['adresse'] ?? '', $s['commune'], $s['telephone'], $token_unsub, $s['id']]);
            $mid  = (int)$db->lastInsertId();
            $code = genererCodeMembre($db);
            $ogm  = genererOGM($mid);
            $db->prepare("UPDATE members SET code_membre=?, ogm=? WHERE id=?")->execute([$code, $ogm, $mid]);
            $created++;
        } catch (Exception $e) {
            $skipped++;
            if (count($errors) < 10) $errors[] = htmlspecialchars($s['email'].' — '.$e->getMessage());
        }
    }
    $selfDeleted = @unlink(__FILE__); // sécurité : retrait après exécution réelle
}
?>
<!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Conversion Wix → Membres</title>
<style>
  body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333;padding:34px 16px}
  .box{max-width:620px;margin:0 auto;background:#fff;border-radius:12px;padding:26px;box-shadow:0 4px 20px rgba(0,0,0,.08)}
  h1{color:#0e3d6b;font-size:1.2rem;margin:0 0 14px}
  table{width:100%;border-collapse:collapse;margin:6px 0 16px;font-size:.9rem}
  td{padding:7px 10px;border-bottom:1px solid #eef2f6}
  td.v{text-align:right;font-weight:700}
  .big{font-size:1.05rem}
  .go{display:inline-block;background:#FF9900;color:#fff;text-decoration:none;padding:13px 24px;border-radius:8px;font-weight:700;margin-top:6px}
  .btn{display:inline-block;background:#1673B2;color:#fff;text-decoration:none;padding:11px 20px;border-radius:8px;font-weight:700;margin-top:8px}
  .ok{background:#e8f8f0;border-left:4px solid #1a7a4a;padding:11px 14px;border-radius:6px;color:#1a5c35;margin-top:14px}
  .warn{background:#fff8ee;border-left:4px solid #FF9900;padding:11px 14px;border-radius:6px;color:#7a4500;font-size:.86rem;margin-top:14px}
  ul.s{font-size:.8rem;color:#555;columns:2;margin:8px 0}
  code{background:#f0f4f8;padding:1px 5px;border-radius:4px}
</style></head><body><div class="box">

<?php if (!$go): ?>
  <h1>Aperçu — conversion des contacts Wix en membres</h1>
  <table>
    <tr><td>Contacts importés de Wix (total)</td><td class="v"><?= $nb_wix ?></td></tr>
    <tr><td>— déjà membres (ignorés)</td><td class="v"><?= $nb_deja_membre ?></td></tr>
    <tr><td>— désabonnés (exclus)</td><td class="v"><?= $nb_wix_desab ?></td></tr>
    <tr><td class="big"><strong>À convertir en membres</strong></td><td class="v big" style="color:#FF9900"><strong><?= $nb_eligibles ?></strong></td></tr>
  </table>

  <?php if ($nb_eligibles > 0): ?>
    <div style="font-size:.8rem;color:#888">Échantillon :</div>
    <ul class="s">
      <?php foreach (array_slice($eligibles, 0, 12) as $s): ?>
        <li><?= htmlspecialchars(trim(($s['prenom'].' '.$s['nom'])) ?: $s['email']) ?></li>
      <?php endforeach; ?>
    </ul>
    <div class="warn">Chaque contact deviendra un <strong>membre actif</strong> (code MBR-<?= date('Y') ?>-xxxxx + OGM, lié à son abonnement). Action <strong>non réversible</strong> en un clic — vérifie le nombre ci-dessus.</div>
    <a class="go" href="?go=1" onclick="return confirm('Convertir <?= $nb_eligibles ?> contacts Wix en membres ?');">✓ Convertir les <?= $nb_eligibles ?> contacts en membres</a>
  <?php else: ?>
    <div class="ok">Rien à convertir — aucun contact Wix actif et non-membre.</div>
  <?php endif; ?>
  <div style="margin-top:10px"><a class="btn" href="/admin/members.php">Voir les membres</a></div>

<?php else: ?>
  <h1>✅ Conversion terminée</h1>
  <table>
    <tr><td>Membres créés</td><td class="v" style="color:#1a7a4a"><?= $created ?></td></tr>
    <tr><td>Ignorés / erreurs</td><td class="v"><?= $skipped ?></td></tr>
  </table>
  <?php if ($errors): ?>
    <div class="warn"><strong>Détails ignorés :</strong><br><?= implode('<br>', $errors) ?></div>
  <?php endif; ?>
  <div class="ok"><?= $selfDeleted ? "🔒 Outil auto-supprimé du serveur." : "ℹ️ Auto-suppression non confirmée — à retirer du dépôt." ?></div>
  <div style="margin-top:10px"><a class="btn" href="/admin/members.php">Voir les <?= $created ?> nouveaux membres</a></div>
<?php endif; ?>

</div></body></html>
