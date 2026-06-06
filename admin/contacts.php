<?php
// admin/contacts.php — v3 — Gestion des messages de contact
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/mail_helper.php';
session_start(); requireAdmin();
$db = getDB();

$msg = ''; $err = '';

// Répondre
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_id'])) {
    $id = (int)$_POST['reply_id'];
    $reponse = trim($_POST['reponse'] ?? '');
    $stmt = $db->prepare("SELECT * FROM contacts WHERE id=? LIMIT 1");
    $stmt->execute([$id]); $contact = $stmt->fetch();
    if ($contact && $reponse) {
        $html = "<p>Bonjour ".htmlspecialchars($contact['nom']).",</p>
        <p>".nl2br(htmlspecialchars($reponse))."</p>
        <hr style='margin:20px 0;border:none;border-top:1px solid #eee'>
        <p style='color:#999;font-size:.83rem'>— Votre message du ".date('d/m/Y', strtotime($contact['created_at']))." :<br>
        <em>".nl2br(htmlspecialchars($contact['message']))."</em></p>
        <p style='margin-top:20px;font-size:.9rem'><em>Ça suffit !<br><a href='https://www.casuffit.be' style='color:#1673B2'>casuffit.be</a></em></p>";
        $text = $reponse."\n\n---\nVotre message :\n".$contact['message'];
        if (sendMail($contact['email'], $contact['nom'], 'Re: '.($contact['sujet'] ?: 'Votre message — Ça suffit !'), $html, $text)) {
            $db->prepare("UPDATE contacts SET statut='repondu', reponse=?, repondu_at=NOW() WHERE id=?")->execute([$reponse, $id]);
            $msg = 'Réponse envoyée à '.$contact['email'];
        } else { $err = 'Échec envoi email.'; }
    }
}

// Supprimer
if (isset($_GET['del'], $_GET['_csrf']) && hash_equals($_SESSION['_csrf_token'] ?? '', $_GET['_csrf'])) {
    $db->prepare("DELETE FROM contacts WHERE id=?")->execute([(int)$_GET['del']]);
    header('Location: contacts.php'); exit;
}

$selected_id = (int)($_GET['id'] ?? 0);
if ($selected_id)
    $db->prepare("UPDATE contacts SET statut=IF(statut='nouveau','lu',statut) WHERE id=?")->execute([$selected_id]);

try {
    $contacts = $db->query("SELECT * FROM contacts ORDER BY created_at DESC LIMIT 100")->fetchAll();
} catch (Exception $e) { $contacts = []; }

$stats = ['total'=>count($contacts), 'nouveau'=>0, 'lu'=>0, 'repondu'=>0];
foreach ($contacts as $c) $stats[$c['statut']] = ($stats[$c['statut']] ?? 0) + 1;

$detail = null;
if ($selected_id) {
    $s = $db->prepare("SELECT * FROM contacts WHERE id=? LIMIT 1");
    $s->execute([$selected_id]); $detail = $s->fetch();
}

function initiales($nom) {
    $p = explode(' ', trim($nom));
    return strtoupper(mb_substr($p[0],0,1) . (isset($p[1]) ? mb_substr($p[1],0,1) : ''));
}
function avatar_color($nom) {
    $colors = ['#1673B2','#0e3d6b','#27ae60','#8e44ad','#c0392b','#d35400','#16a085'];
    return $colors[abs(crc32($nom)) % count($colors)];
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
*{box-sizing:border-box}
body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333;margin:0}

/* ── Header ── */
.page-header{display:flex;align-items:center;justify-content:space-between;padding:22px 28px 0;flex-wrap:wrap;gap:12px}
.page-header h1{font-size:1.2rem;font-weight:800;color:#0e3d6b;margin:0;display:flex;align-items:center;gap:8px}
.stats-pills{display:flex;gap:8px;flex-wrap:wrap}
.pill{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:20px;font-size:.78rem;font-weight:700;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.pill .n{font-size:.95rem}
.pill.nouveau{color:#c0392b}.pill.nouveau .n{color:#c0392b}
.pill.repondu{color:#27ae60}.pill.repondu .n{color:#27ae60}
.pill.total{color:#0e3d6b}.pill.total .n{color:#0e3d6b}

/* ── Alerte ── */
.alert{margin:14px 28px 0;padding:10px 16px;border-radius:8px;font-size:.88rem;display:flex;align-items:center;gap:8px}
.alert.ok{background:#e8f8f0;border:1px solid #a8e6c0;color:#1a6e3c}
.alert.err{background:#fde8e8;border:1px solid #f5b7b1;color:#922b21}

/* ── Layout deux colonnes ── */
.contacts-layout{display:grid;grid-template-columns:340px 1fr;gap:0;margin:18px 28px 28px;background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.08);overflow:hidden;min-height:500px}

/* ── Liste messages ── */
.msg-list{border-right:1px solid #edf2f7;overflow-y:auto;max-height:70vh}
.msg-list-header{padding:14px 16px;font-size:.7rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#aaa;border-bottom:1px solid #edf2f7;background:#fafbfc}
.msg-item{display:flex;gap:12px;padding:14px 16px;border-bottom:1px solid #f0f4f8;cursor:pointer;text-decoration:none;color:inherit;transition:background .15s;position:relative}
.msg-item:hover{background:#f8fafc}
.msg-item.active{background:#eff6ff;border-left:3px solid #1673B2}
.msg-item.nouveau{background:#fffbf0}
.msg-item.nouveau:hover{background:#fff8e6}
.msg-item.nouveau .msg-name{font-weight:800}
.msg-avatar{width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.78rem;color:#fff;flex-shrink:0;margin-top:2px}
.msg-body{flex:1;min-width:0}
.msg-meta{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:3px}
.msg-name{font-size:.88rem;color:#0e3d6b}
.msg-date{font-size:.72rem;color:#bbb;white-space:nowrap}
.msg-sujet{font-size:.82rem;color:#555;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:2px}
.msg-preview{font-size:.75rem;color:#aaa;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.msg-dot{position:absolute;right:12px;top:50%;transform:translateY(-50%);width:8px;height:8px;border-radius:50%;background:#FF9900}
.badge-sm{display:inline-block;padding:1px 6px;border-radius:8px;font-size:.65rem;font-weight:700;margin-left:4px}
.badge-sm.repondu{background:#e8f8f0;color:#27ae60}
.empty-list{padding:40px 20px;text-align:center;color:#bbb;font-size:.88rem}

/* ── Panneau détail ── */
.msg-detail{display:flex;flex-direction:column;background:#fafbfc}
.msg-detail-empty{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#ccc;gap:12px}
.msg-detail-empty .icon{font-size:3rem;opacity:.4}
.msg-detail-empty p{font-size:.88rem}
.detail-header{padding:20px 24px 16px;border-bottom:1px solid #edf2f7;background:#fff}
.detail-header h2{font-size:1rem;font-weight:800;color:#0e3d6b;margin:0 0 6px}
.detail-from{font-size:.82rem;color:#888;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.detail-from a{color:#1673B2;text-decoration:none}
.detail-from a:hover{text-decoration:underline}
.detail-actions{display:flex;gap:8px;margin-top:12px}
.btn-sm{padding:5px 12px;border-radius:6px;font-size:.78rem;font-weight:600;border:none;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:4px;transition:background .15s}
.btn-del{background:#fde8e8;color:#c0392b}.btn-del:hover{background:#fbd7d7}
.detail-body{flex:1;overflow-y:auto;padding:20px 24px}
.msg-bubble{background:#fff;border:1px solid #e8eef5;border-radius:12px;padding:16px 18px;font-size:.9rem;line-height:1.6;white-space:pre-wrap;color:#333;margin-bottom:16px}
.reply-received{background:#e8f8f0;border:1px solid #b2e8c8;border-radius:12px;padding:14px 18px;font-size:.88rem;white-space:pre-wrap;color:#1a5e36;margin-bottom:12px}
.reply-label{font-size:.75rem;font-weight:700;color:#27ae60;margin-bottom:6px;text-transform:uppercase;letter-spacing:.06em}
.detail-reply{padding:16px 24px 20px;border-top:1px solid #edf2f7;background:#fff}
.detail-reply label{display:block;font-size:.78rem;font-weight:700;color:#0e3d6b;margin-bottom:6px}
.detail-reply textarea{width:100%;min-height:110px;padding:10px 12px;border:1.5px solid #cdd8e5;border-radius:8px;font-size:.9rem;font-family:inherit;resize:vertical;box-sizing:border-box;transition:border-color .2s}
.detail-reply textarea:focus{outline:none;border-color:#1673B2}
.reply-footer{display:flex;align-items:center;gap:12px;margin-top:10px}
.btn-send{background:#0e3d6b;color:#fff;border:none;padding:10px 22px;border-radius:8px;font-weight:700;cursor:pointer;font-size:.88rem;transition:background .15s}
.btn-send:hover{background:#1673B2}
.reply-to{font-size:.78rem;color:#aaa}
</style>
</head>
<body>
<?php include __DIR__.'/../includes/admin_sidebar.php'; ?>
<div class="wrap">

<div class="page-header">
  <h1>📬 Messages</h1>
  <div class="stats-pills">
    <span class="pill total"><span class="n"><?= $stats['total'] ?></span> total</span>
    <span class="pill nouveau"><span class="n"><?= $stats['nouveau'] ?? 0 ?></span> nouveau<?= ($stats['nouveau']??0)>1?'x':'' ?></span>
    <span class="pill repondu"><span class="n"><?= $stats['repondu'] ?? 0 ?></span> répondu<?= ($stats['repondu']??0)>1?'s':'' ?></span>
  </div>
</div>

<?php if ($msg): ?><div class="alert ok">✅ <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert err">❌ <?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="contacts-layout">

  <!-- Liste -->
  <div class="msg-list">
    <div class="msg-list-header"><?= count($contacts) ?> message<?= count($contacts)>1?'s':'' ?></div>
    <?php if (empty($contacts)): ?>
      <div class="empty-list">📭<br>Aucun message pour l'instant</div>
    <?php endif; ?>
    <?php foreach ($contacts as $c):
      $init = initiales($c['nom']);
      $color = avatar_color($c['nom']);
      $is_active = ($c['id'] == $selected_id);
    ?>
    <a href="contacts.php?id=<?= $c['id'] ?>" class="msg-item <?= $c['statut'] ?><?= $is_active?' active':'' ?>">
      <div class="msg-avatar" style="background:<?= $color ?>"><?= htmlspecialchars($init) ?></div>
      <div class="msg-body">
        <div class="msg-meta">
          <span class="msg-name">
            <?= htmlspecialchars($c['nom']) ?>
            <?php if ($c['statut']==='repondu'): ?><span class="badge-sm repondu">répondu</span><?php endif; ?>
          </span>
          <span class="msg-date"><?= date('d/m H:i', strtotime($c['created_at'])) ?></span>
        </div>
        <div class="msg-sujet"><?= htmlspecialchars($c['sujet'] ?: 'Sans sujet') ?></div>
        <div class="msg-preview"><?= htmlspecialchars(mb_substr($c['message'],0,60)) ?>…</div>
      </div>
      <?php if ($c['statut']==='nouveau'): ?><span class="msg-dot"></span><?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Détail -->
  <div class="msg-detail">
  <?php if (!$detail): ?>
    <div class="msg-detail-empty">
      <div class="icon">✉️</div>
      <p>Sélectionnez un message pour le lire</p>
    </div>
  <?php else: ?>
    <div class="detail-header">
      <h2><?= htmlspecialchars($detail['sujet'] ?: 'Message sans sujet') ?></h2>
      <div class="detail-from">
        <div class="msg-avatar" style="background:<?= avatar_color($detail['nom']) ?>;width:28px;height:28px;font-size:.68rem"><?= htmlspecialchars(initiales($detail['nom'])) ?></div>
        <strong><?= htmlspecialchars($detail['nom']) ?></strong>
        <a href="mailto:<?= htmlspecialchars($detail['email']) ?>"><?= htmlspecialchars($detail['email']) ?></a>
        <span>· <?= date('d/m/Y à H:i', strtotime($detail['created_at'])) ?></span>
      </div>
      <div class="detail-actions">
        <a class="btn-sm btn-del" href="contacts.php?del=<?= $detail['id'] ?>&_csrf=<?= htmlspecialchars(csrf_token()) ?>" onclick="return confirm('Supprimer ce message ?')">🗑 Supprimer</a>
      </div>
    </div>

    <div class="detail-body">
      <div class="msg-bubble"><?= htmlspecialchars($detail['message']) ?></div>

      <?php if ($detail['reponse']): ?>
        <div class="reply-label">✅ Réponse envoyée le <?= date('d/m/Y', strtotime($detail['repondu_at'])) ?></div>
        <div class="reply-received"><?= htmlspecialchars($detail['reponse']) ?></div>
      <?php endif; ?>
    </div>

    <div class="detail-reply">
      <form method="POST" action="contacts.php?id=<?= $detail['id'] ?>">
        <input type="hidden" name="reply_id" value="<?= $detail['id'] ?>">
        <label><?= $detail['reponse'] ? '📝 Envoyer un autre message' : '📝 Répondre à '.$detail['nom'] ?></label>
        <textarea name="reponse" placeholder="Votre réponse..." required></textarea>
        <div class="reply-footer">
          <button type="submit" class="btn-send">📨 Envoyer</button>
          <span class="reply-to">→ <?= htmlspecialchars($detail['email']) ?></span>
        </div>
      </form>
    </div>
  <?php endif; ?>
  </div>

</div>
</div>
</body>
</html>
