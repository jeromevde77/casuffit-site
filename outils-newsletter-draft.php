<?php
/**
 * outils-newsletter-draft.php — OUTIL PONCTUEL
 * Crée un brouillon de newsletter pré-mis en forme, puis s'auto-supprime.
 * Protégé par session admin. À supprimer du dépôt après usage.
 */
require_once __DIR__ . '/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireAdmin();

$db = getDB();

$sujet = "Piste 01 en pleine canicule : nous ne comprenons pas";

$contenu = <<<'HTML'
<p style="margin:0 0 16px;font-size:16px;color:#0e3d6b;"><strong>Chers survolés,</strong></p>

<p style="margin:0 0 16px;">Vous le savez, nous vous alertons depuis longtemps sur les conséquences de l'inaction politique face aux nuisances que nous subissons.</p>

<p style="margin:0 0 16px;">Alors qu'une vague de chaleur oblige chacun à dormir fenêtres ouvertes, <strong>des centaines d'avions sont déviés vers la piste 01 actuellement sans raisons valables.</strong></p>

<h3 style="margin:26px 0 8px;color:#0e3d6b;font-size:18px;font-weight:800;">Pourquoi&nbsp;?</h3>

<p style="margin:0 0 16px;">Nous avons du mal à l'expliquer dans de nombreux cas.</p>

<p style="margin:0 0 16px;">Si l'utilisation de la piste 01 peut se justifier par des vents très forts de Nord, ce n'est absolument pas le cas pour le moment. <strong>À l'heure où nous écrivons, le vent est orienté à l'Est et était faible ce matin.</strong> Nous avons écrit à ce sujet à plusieurs reprises. Nous ne comprenons pas.</p>

<div style="background:#fff8ee;border:1px solid #ffd9a8;border-left:4px solid #FF9900;border-radius:8px;padding:16px 20px;margin:24px 0;color:#7a4a00;font-size:15px;line-height:1.65;">
<strong style="color:#c4651a;">Nous avons besoin de votre soutien.</strong> Parlez-en à vos voisins et sensibilisez-les à la cause.
</div>

<div style="background:#fff5f5;border:1px solid #f5c2c2;border-left:4px solid #e53e3e;border-radius:8px;padding:16px 20px;margin:24px 0;color:#7a1a1a;font-size:15px;line-height:1.65;">
<strong style="display:block;margin-bottom:4px;">⚠ Procédure de la Région bruxelloise</strong>
La région bruxelloise a lancé une procédure contre l'État belge. Si les demandes sont satisfaites, ce sont <strong>des dizaines de jours supplémentaires de piste 01</strong> que vous devrez subir à l'avenir.
</div>

<p style="margin:0 0 16px;"><strong>Il faut agir et faire respecter nos droits.</strong> La justice a toujours donné raison aux riverains en cas de non-respect des normes de vent. Il ne faut absolument pas que la situation actuelle débouche sur une loi qui bétonnerait pour toujours les injustices et donc les nuisances.</p>

<p style="margin:28px 0 0;color:#0e3d6b;"><span style="color:#888;">Bien à vous,</span><br><strong style="font-size:16px;">L'équipe Ça suffit&nbsp;!</strong></p>
HTML;

// Éviter les doublons si la page est rechargée (au cas où l'auto-suppression échoue)
$stmt = $db->prepare("SELECT id FROM newsletters WHERE sujet=? AND statut='brouillon' ORDER BY id DESC LIMIT 1");
$stmt->execute([$sujet]);
$existing = $stmt->fetchColumn();

if ($existing) {
    $id = (int)$existing;
    $note = "Un brouillon avec ce sujet existait déjà (aucun doublon créé).";
} else {
    $db->prepare("INSERT INTO newsletters (sujet, contenu_html, statut) VALUES (?,?,'brouillon')")
       ->execute([$sujet, $contenu]);
    $id = (int)$db->lastInsertId();
    $note = "Brouillon créé avec succès.";
}

// Auto-suppression (sécurité) — le fichier ne doit pas rester accessible
$selfDeleted = @unlink(__FILE__);
?>
<!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Brouillon newsletter</title>
<style>
  body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;padding:40px 16px;color:#333}
  .box{max-width:560px;margin:0 auto;background:#fff;border-radius:12px;padding:28px;box-shadow:0 4px 20px rgba(0,0,0,.08)}
  h2{color:#0e3d6b;margin:0 0 10px}
  a.btn{display:inline-block;background:#1673B2;color:#fff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:700;margin-top:8px}
  .ok{background:#e8f8f0;border-left:4px solid #1a7a4a;padding:10px 14px;border-radius:6px;font-size:.85rem;color:#1a5c35;margin-top:18px}
  .warn{background:#fff5f5;border-left:4px solid #e53e3e;padding:10px 14px;border-radius:6px;font-size:.85rem;color:#7a1a1a;margin-top:12px}
  code{background:#f0f4f8;padding:1px 5px;border-radius:4px}
</style>
</head><body><div class="box">
  <h2>✅ <?= htmlspecialchars($note) ?></h2>
  <p>Le brouillon « <strong><?= htmlspecialchars($sujet) ?></strong> » est prêt. Tu peux le relire, ajuster, puis l'envoyer.</p>
  <a class="btn" href="/admin/compose.php?id=<?= $id ?>">Ouvrir le brouillon dans l'éditeur →</a>
  <div class="ok"><?= $selfDeleted ? "🔒 Ce fichier outil s'est auto-supprimé du serveur." : "ℹ️ Auto-suppression non confirmée — le fichier sera retiré du dépôt." ?></div>
  <div class="warn">Tu peux changer le <strong>sujet</strong> et la mise en forme directement dans l'éditeur.</div>
</div></body></html>
