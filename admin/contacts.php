<?php
// admin/contacts.php — v2 — Gestion des messages de contact
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/mail_helper.php';
session_start(); requireAdmin();
$db = getDB();

$msg = ''; $err = '';

// Répondre à un message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_id'])) {
    $id      = (int)$_POST['reply_id'];
    $reponse = trim($_POST['reponse'] ?? '');
    $contact = $db->prepare("SELECT * FROM contacts WHERE id=?")->execute([$id]) ? null : null;
    $stmt = $db->prepare("SELECT * FROM contacts WHERE id=? LIMIT 1");
    $stmt->execute([$id]);
    $contact = $stmt->fetch();
    if ($contact && $reponse) {
        $html = "
        <p>Bonjour ".htmlspecialchars($contact['nom']).",</p>
        <p>".nl2br(htmlspecialchars($reponse))."</p>
        <hr style='margin:20px 0'>
        <p style='color:#888;font-size:.85rem'>— Votre message du ".date('d/m/Y', strtotime($contact['created_at']))." :<br><em>".nl2br(htmlspecialchars($contact['message']))."</em></p>
        <p style='margin-top:20px'><em>Ça suffit !<br><a href='https://www.casuffit.be'>casuffit.be</a></em></p>";
        $text = $reponse."\n\n---\nVotre message :\n".$contact['message'];
        if (sendMail($contact['email'], $contact['nom'], 'Re: '.($contact['sujet'] ?: 'Votre message — Ça suffit !'), $html, $text)) {
            $db->prepare("UPDATE contacts SET statut='repondu', reponse=?, repondu_at=NOW() WHERE id=?")->execute([$reponse, $id]);
            $msg = '✅ Réponse envoyée à '.$contact['email'];
        } else { $err = '❌ Échec d\'envoi email.'; }
    }
}

// Marquer comme lu
if (isset($_GET['lu'])) {
    $db->prepare("UPDATE contacts SET statut='lu' WHERE id=? AND statut='nouveau'")->execute([(int)$_GET['lu']]);
    header('Location: contacts.php'); exit;
}

// Supprimer
if (isset($_GET['del']) && isset($_GET['_csrf'])) {
    if (hash_equals($_SESSION['_csrf_token'] ?? '', $_GET['_csrf'])) {
        $db->prepare("DELETE FROM contacts WHERE id=?")->execute([(int)$_GET['del']]);
        header('Location: contacts.php'); exit;
    }
}

$selected_id = (int)($_GET['id'] ?? 0);
if ($selected_id) {
    $db->prepare("UPDATE contacts SET statut=IF(statut='nouveau','lu',statut) WHERE id=?")->execute([$selected_id]);
}

$contacts = $db->query("SELECT * FROM contacts ORDER BY created_at DESC LIMIT 100")->fetchAll();
$stats    = ['total' => 0, 'nouveau' => 0, 'repondu' => 0];
foreach ($contacts as $c) { $stats['total']++; $stats[$c['statut']] = ($stats[$c['statut']] ?? 0) + 1; }

$detail = null;
if ($selected_id) {
    $s = $db->prepare("SELECT * FROM contacts WHERE id=? LIMIT 1");
    $s->execute([$selected_id]); $detail = $s->fetch();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Messages — Admin Ça suffit !</title>
<style>
<?php include __DIR__.'/../includes/admin_sidebar_css.php'; ?>
body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333;margin:0}
.stats-bar { display:flex; gap:12px; margin-bottom:20px; flex-wrap:wrap; }
.stat-box { background:#fff; border-radius:10px; padding:12px 20px; text-align:center; box-shadow:0 1px 4px rgba(0,0,0,.08); min-width:90px; }
.stat-box .val { font-size:1.6rem; font-weight:800; color:#0e3d6b; }
.stat-box .lbl { font-size:.72rem; color:#999; margin-top:2px; }
.stat-box.nouveau .val { color:#e74c3c; }
.stat-box.repondu .val { color:#27ae60; }
.contacts-table { width:100%; border-collapse:collapse; background:#fff; border-radius:10px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,.08); }
.contacts-table th { background:#f0f4f8; color:#0e3d6b; font-size:.8rem; text-transform:uppercase; padding:10px 14px; text-align:left; }
.contacts-table td { padding:10px 14px; font-size:.88rem; border-top:1px solid #f0f4f8; vertical-align:middle; }
.contacts-table tr:hover td { background:#f8fafc; }
.contacts-table tr.nouveau td { font-weight:700; }
.contacts-table tr.selected td { background:#eff6ff; }
.badge { display:inline-block; padding:2px 8px; border-radius:10px; font-size:.72rem; font-weight:700; }
.badge.nouveau  { background:#fde8e8; color:#c0392b; }
.badge.lu       { background:#e8f0fe; color:#1673B2; }
.badge.repondu  { background:#e8f8f0; color:#27ae60; }
.detail-panel { background:#fff; border-radius:12px; padding:24px; box-shadow:0 2px 8px rgba(0,0,0,.1); margin-top:20px; }
.detail-panel h3 { margin:0 0 4px; color:#0e3d6b; font-size:1.05rem; }
.detail-panel .meta { font-size:.82rem; color:#888; margin-bottom:16px; }
.detail-panel .message-box { background:#f8fafc; border-left:3px solid #1673B2; padding:14px 16px; border-radius:0 8px 8px 0; font-size:.9rem; white-space:pre-wrap; margin-bottom:20px; }
.detail-panel .reponse-box { background:#e8f8f0; border-left:3px solid #27ae60; padding:14px 16px; border-radius:0 8px 8px 0; font-size:.9rem; white-space:pre-wrap; margin-bottom:16px; }
.reply-form textarea { width:100%; min-height:120px; padding:10px 12px; border:1.5px solid #cdd8e5; border-radius:8px; font-size:.9rem; font-family:inherit; resize:vertical; box-sizing:border-box; }
.btn-reply { background:#0e3d6b; color:#fff; border:none; padding:10px 22px; border-radius:8px; font-weight:700; cursor:pointer; font-size:.9rem; }
.btn-reply:hover { background:#1673B2; }
a.del { color:#e74c3c; font-size:.8rem; text-decoration:none; }
</style>
</head>
<body>
<?php include __DIR__.'/../includes/admin_sidebar.php'; ?>
<div class="wrap">
<div style="padding:20px 24px 0">
<div class="dash-header">
  <h1>📬 Messages de contact</h1>
  <span class="date"><?= date('d/m/Y') ?></span>
</div>

<?php if ($msg): ?><div style="background:#e8f8f0;border:1px solid #27ae60;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:.9rem"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div style="background:#fde8e8;border:1px solid #e74c3c;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:.9rem"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<!-- Stats -->
<div class="stats-bar">
  <div class="stat-box"><div class="val"><?= $stats['total'] ?></div><div class="lbl">Total</div></div>
  <div class="stat-box nouveau"><div class="val"><?= $stats['nouveau'] ?? 0 ?></div><div class="lbl">Nouveaux</div></div>
  <div class="stat-box repondu"><div class="val"><?= $stats['repondu'] ?? 0 ?></div><div class="lbl">Répondus</div></div>
</div>

<!-- Tableau -->
<table class="contacts-table">
  <thead><tr><th>Date</th><th>Nom</th><th>Email</th><th>Sujet</th><th>Statut</th><th></th></tr></thead>
  <tbody>
  <?php if (empty($contacts)): ?>
    <tr><td colspan="6" style="text-align:center;color:#999;padding:30px">Aucun message reçu pour l'instant</td></tr>
  <?php endif; ?>
  <?php foreach ($contacts as $c): ?>
  <tr class="<?= $c['statut'] ?><?= $c['id'] == $selected_id ? ' selected' : '' ?>">
    <td style="white-space:nowrap;color:#888;font-size:.8rem"><?= date('d/m H:i', strtotime($c['created_at'])) ?></td>
    <td><?= htmlspecialchars($c['nom']) ?></td>
    <td><a href="mailto:<?= htmlspecialchars($c['email']) ?>" style="color:#1673B2"><?= htmlspecialchars($c['email']) ?></a></td>
    <td style="color:#666"><?= htmlspecialchars($c['sujet'] ?: '—') ?></td>
    <td><span class="badge <?= $c['statut'] ?>"><?= $c['statut'] ?></span></td>
    <td style="white-space:nowrap">
      <a href="contacts.php?id=<?= $c['id'] ?>" style="color:#1673B2;font-size:.8rem;text-decoration:none;margin-right:8px">
        <?= $c['id'] == $selected_id ? '▲ Fermer' : '✉ Voir / Répondre' ?>
      </a>
      <a class="del" href="contacts.php?del=<?= $c['id'] ?>&_csrf=<?= htmlspecialchars(csrf_token()) ?>" onclick="return confirm('Supprimer ce message ?')">✕</a>
    </td>
  </tr>
  <?php if ($detail && $detail['id'] == $c['id']): ?>
  <tr><td colspan="6" style="padding:0 16px 16px">
    <div class="detail-panel">
      <h3><?= htmlspecialchars($detail['sujet'] ?: 'Message sans sujet') ?></h3>
      <div class="meta">
        De : <strong><?= htmlspecialchars($detail['nom']) ?></strong> &lt;<?= htmlspecialchars($detail['email']) ?>&gt;
        · <?= date('d/m/Y à H:i', strtotime($detail['created_at'])) ?>
      </div>
      <div class="message-box"><?= htmlspecialchars($detail['message']) ?></div>

      <?php if ($detail['reponse']): ?>
        <div style="font-size:.82rem;color:#27ae60;font-weight:700;margin-bottom:6px">✅ Réponse envoyée le <?= date('d/m/Y', strtotime($detail['repondu_at'])) ?></div>
        <div class="reponse-box"><?= htmlspecialchars($detail['reponse']) ?></div>
      <?php endif; ?>

      <form method="POST" action="contacts.php?id=<?= $detail['id'] ?>">
        <input type="hidden" name="reply_id" value="<?= $detail['id'] ?>">
        <div style="margin-bottom:8px;font-weight:700;font-size:.9rem;color:#0e3d6b">
          <?= $detail['reponse'] ? '📝 Envoyer un autre message' : '📝 Répondre à '.$detail['nom'] ?>
        </div>
        <textarea name="reponse" placeholder="Votre réponse..." required><?= htmlspecialchars($detail['reponse'] ?? '') ?></textarea>
        <div style="display:flex;align-items:center;gap:12px;margin-top:10px">
          <button type="submit" class="btn-reply">📨 Envoyer la réponse</button>
          <span style="font-size:.8rem;color:#888">→ sera envoyée à <?= htmlspecialchars($detail['email']) ?></span>
        </div>
      </form>
    </div>
  </td></tr>
  <?php endif; ?>
  <?php endforeach; ?>
  </tbody>
</table>
</div><!-- /wrap -->
</body></html>
