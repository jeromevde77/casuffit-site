<?php
// admin/newsletters.php — Liste et envoi des newsletters
require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();
require_once __DIR__ . '/../includes/csrf.php';
$db = getDB();

$msg = ''; $error = '';

// ── Envoyer une newsletter ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_verify();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_nl'])) {
    $id = intval($_POST['nl_id'] ?? 0);
    if ($id > 0) {
        $nl = $db->prepare("SELECT * FROM newsletters WHERE id=? AND statut='brouillon'");
        $nl->execute(array($id));
        $nl = $nl->fetch();
        if ($nl) {
            $abonnes = $db->query("SELECT id FROM subscribers WHERE statut='actif'")->fetchAll();
            $db->prepare("UPDATE newsletters SET statut='envoi' WHERE id=?")->execute(array($id));
            $stmt = $db->prepare("INSERT INTO send_queue (newsletter_id, subscriber_id, statut) VALUES (?,?,'en_attente')");
            foreach ($abonnes as $a) { $stmt->execute(array($id, $a['id'])); }
            $msg = 'Envoi lancé pour '.count($abonnes).' abonnés.';
        } else {
            $error = 'Newsletter introuvable ou déjà envoyée.';
        }
    }
}

// ── Supprimer une newsletter ──────────────────────────────────────────────
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $db->prepare("DELETE FROM newsletters WHERE id=? AND statut='brouillon'")->execute(array(intval($_GET['delete'])));
    $db->prepare("DELETE FROM send_queue WHERE newsletter_id=?")->execute(array(intval($_GET['delete'])));
    header('Location: newsletters.php?msg=Newsletter+supprimée.'); exit;
}

$msg   = $_GET['msg'] ?? $msg;
$error = $_GET['error'] ?? $error;

// Auto-correction : passer en « Envoyée » toute newsletter dont la file est entièrement traitée
// (débloque celles restées « En cours » à tort)
try {
    $db->exec("UPDATE newsletters n
               SET n.statut='envoye', n.sent_at=COALESCE(n.sent_at, NOW())
               WHERE n.statut='envoi'
                 AND NOT EXISTS (SELECT 1 FROM send_queue q WHERE q.newsletter_id=n.id AND q.statut='en_attente')");
} catch (Throwable $e) {}

$newsletters  = $db->query("SELECT n.*, (SELECT COUNT(*) FROM send_queue sq WHERE sq.newsletter_id=n.id) as nb_queue, (SELECT COUNT(*) FROM send_queue sq WHERE sq.newsletter_id=n.id AND sq.statut='envoye') as nb_envoyes_queue FROM newsletters n ORDER BY n.created_at DESC")->fetchAll();
$nb_abonnes   = $db->query("SELECT COUNT(*) FROM subscribers WHERE statut='actif'")->fetchColumn();
$brouillons   = array_filter($newsletters, function($n){ return $n['statut'] === 'brouillon'; });
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<?php include __DIR__ . '/../includes/admin_pwa_head.php'; ?>
  <title>Newsletters — Admin Ça suffit !</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333}
    <?php include __DIR__ . '/../includes/admin_sidebar_css.php'; ?>
    .main{margin-left:240px;padding:28px;max-width:900px}
    .page-title{font-size:1.3rem;font-weight:800;color:#0e3d6b;margin-bottom:4px}
    .page-sub{font-size:.8rem;color:#888;margin-bottom:24px}

    .card{background:#fff;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.06);padding:22px;margin-bottom:20px}
    .card h3{font-size:.92rem;font-weight:700;color:#0e3d6b;margin-bottom:16px;padding-bottom:8px;border-bottom:1px solid #e0e8f0}

    /* Zone d'envoi */
    .send-zone{background:linear-gradient(135deg,#0e3d6b,#1673B2);border-radius:10px;padding:24px;margin-bottom:20px;color:#fff}
    .send-zone h3{color:#fff;font-size:1rem;font-weight:700;margin-bottom:6px}
    .send-zone p{color:rgba(255,255,255,.7);font-size:.8rem;margin-bottom:16px}
    .send-form{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap}
    .send-form select{flex:1;min-width:200px;padding:9px 12px;border-radius:6px;border:none;font-size:.85rem;font-family:inherit;outline:none;color:#0e3d6b;font-weight:600}
    .send-form .abonnes-badge{background:rgba(255,255,255,.15);border-radius:20px;padding:4px 12px;font-size:.75rem;color:rgba(255,255,255,.9);align-self:center}
    
    .no-brouillon{color:rgba(255,255,255,.6);font-style:italic;font-size:.82rem}

    /* Liste newsletters */
    .nl-item{display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid #f5f5f5}
    .nl-item:last-child{border-bottom:none}
    .nl-statut{width:10px;height:10px;border-radius:50%;flex-shrink:0}
    .s-brouillon{background:#aaa}
    .s-envoi{background:#FF9900}
    .s-envoye{background:#27ae60}
    .nl-info{flex:1;min-width:0}
    .nl-sujet{font-weight:600;font-size:.88rem;color:#0e3d6b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .nl-meta{font-size:.68rem;color:#aaa;margin-top:2px}
    .nl-badge{font-size:.65rem;padding:2px 8px;border-radius:10px;font-weight:700;flex-shrink:0}
    .nb-brouillon{background:#f0f0f0;color:#666}
    .nb-envoi{background:#fff8ee;color:#FF9900}
    .nb-envoye{background:#e8f8f0;color:#27ae60}
    .nl-actions{display:flex;gap:6px;flex-shrink:0}

    .btn{padding:7px 14px;border-radius:6px;font-size:.78rem;font-weight:600;cursor:pointer;border:1.5px solid transparent;font-family:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:all .15s;line-height:1}
.btn-p{background:#1673B2;color:#fff;border-color:#1673B2}
.btn-p:hover{background:#125a90;color:#fff;text-decoration:none}
.btn-g{background:#f0f4f8;color:#555;border-color:#dde4ed}
.btn-g:hover{background:#e0e8f0;color:#333;text-decoration:none}
.btn-r{background:#fff5f5;color:#e53e3e;border-color:#fed7d7}
.btn-r:hover{background:#fee2e2;text-decoration:none}
.btn-sm{padding:4px 10px;font-size:.72rem}
.btn-apercu-mobile{display:none}
.btn-retour-mobile{display:none;font-size:.78rem;color:rgba(255,255,255,.85);text-decoration:none;font-weight:600;margin-bottom:8px;align-items:center;gap:4px}

    .flash-ok{background:#e8f8f0;color:#276749;padding:9px 14px;border-radius:6px;margin-bottom:16px;font-size:.8rem;border-left:3px solid #48bb78}
    .flash-err{background:#fde8e8;color:#c53030;padding:9px 14px;border-radius:6px;margin-bottom:16px;font-size:.8rem;border-left:3px solid #fc8181}
    .empty{text-align:center;color:#aaa;padding:30px;font-size:.85rem}
  
  @media (max-width: 768px) {
    .main{ margin-left: 0 !important; padding: 16px !important; padding-top: 68px !important; }
    .grid2, .cards-grid { grid-template-columns: 1fr !important; }
    table { font-size: .75rem; }
    table th, table td { padding: 6px 8px !important; }
    .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .form-row { grid-template-columns: 1fr !important; }
    .btn, button[type=submit], .btn-save { width: 100%; justify-content: center; }
  }
.act-btn{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;border:1.5px solid;cursor:pointer;text-decoration:none;transition:all .15s;background:none;font-family:inherit;flex-shrink:0}
.act-btn.edit{color:#4a5568;border-color:#e2e8f0;background:#f7f8fa}
.act-btn.edit:hover{background:#edf2f7;border-color:#cbd5e0;color:#2d3748;text-decoration:none}
.act-btn.del{color:#e53e3e;border-color:#fed7d7;background:#fff5f5}
.act-btn.del:hover{background:#fee2e2;border-color:#fc8181;text-decoration:none}
.act-btn.view{color:#38a169;border-color:#c6f6d5;background:#f0fff4}
.act-btn.view:hover{background:#dcfce7;text-decoration:none}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

<div class="main">
  <div class="page-title">Newsletters</div>
  <div class="page-sub">Envoi et historique des newsletters</div>

  <?php if ($msg): ?><div class="flash-ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="flash-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <a href="compose.php" class="btn btn-p" style="margin-bottom:20px"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Nouvelle newsletter</a>

  <!-- ZONE D'ENVOI -->
  <div class="send-zone">
    <h3>Envoyer une newsletter</h3>
    <p>Sélectionnez un brouillon et lancez l'envoi à tous les abonnés actifs.</p>
    <?php if (empty($brouillons)): ?>
      <p class="no-brouillon">Aucun brouillon disponible. <a href="compose.php" style="color:#FF9900">Rédigez-en un →</a></p>
    <?php else: ?>
    <form method="POST" class="send-form" onsubmit="return confirm('Envoyer cette newsletter à <?= $nb_abonnes ?> abonnés ?')">
      <?= csrf_field() ?>
      <select name="nl_id" required>
        <option value="">— Choisir un brouillon —</option>
        <?php foreach ($brouillons as $b): ?>
        <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['sujet']) ?> — <?= date('d/m/Y', strtotime($b['created_at'])) ?></option>
        <?php endforeach; ?>
      </select>
      <span class="abonnes-badge">👥 <?= $nb_abonnes ?> abonnés actifs</span>
      <button type="submit" name="send_nl" class="btn btn-p" <?= $nb_abonnes == 0 ? 'disabled' : '' ?>>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> Envoyer
      </button>
    </form>
    <?php endif; ?>

    <?php $nb_en_attente = (int)$db->query("SELECT COUNT(*) FROM send_queue WHERE statut='en_attente'")->fetchColumn(); ?>
    <?php if ($nb_en_attente > 0): ?>
    <div style="margin-top:16px;padding-top:14px;border-top:1px solid rgba(255,255,255,.18)">
      <button type="button" id="btn-flush" class="btn" style="background:#1a7a4a;color:#fff;border-color:#1a7a4a"
              onclick="flushQueue()">▶ Traiter la file maintenant (<span id="q-reste"><?= $nb_en_attente ?></span> en attente)</button>
      <span style="font-size:.72rem;color:rgba(255,255,255,.75);margin-left:8px">Envoi par paquets, avec avancement en direct.</span>
      <div id="q-progress" style="display:none;margin-top:12px">
        <div style="height:12px;background:rgba(255,255,255,.25);border-radius:6px;overflow:hidden">
          <div id="q-bar" style="height:100%;width:0;background:#fff;border-radius:6px;transition:width .3s"></div>
        </div>
        <div id="q-stat" style="font-size:.78rem;color:#fff;margin-top:6px"></div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- HISTORIQUE -->
  <div class="card">
    <h3><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> Historique (<?= count($newsletters) ?>)</h3>
    <?php if (empty($newsletters)): ?>
      <div class="empty">Aucune newsletter pour le moment.</div>
    <?php else: ?>
      <?php foreach ($newsletters as $nl): ?>
      <div class="nl-item">
        <div class="nl-statut s-<?= $nl['statut'] ?>"></div>
        <div class="nl-info">
          <div class="nl-sujet"><?= htmlspecialchars($nl['sujet']) ?></div>
          <div class="nl-meta">
            Créée le <?= date('d/m/Y à H:i', strtotime($nl['created_at'])) ?>
            <?php if ($nl['statut'] === 'envoye' && $nl['sent_at']): ?>
              · Envoyée le <?= date('d/m/Y à H:i', strtotime($nl['sent_at'])) ?>
              · <?= $nl['nb_envoyes_queue'] ?>/<?= $nl['nb_queue'] ?> envoyés
            <?php elseif ($nl['statut'] === 'envoi'): ?>
              · En cours d'envoi (<?= $nl['nb_envoyes_queue'] ?>/<?= $nl['nb_queue'] ?>)
            <?php endif; ?>
          </div>
        </div>
        <span class="nl-badge nb-<?= $nl['statut'] ?>">
          <?= $nl['statut'] === 'brouillon' ? 'Brouillon' : ($nl['statut'] === 'envoi' ? 'En cours' : 'Envoyée') ?>
        </span>
        <div class="nl-actions">
          <?php if ($nl['statut'] === 'brouillon'): ?>
            <a href="compose.php?id=<?= $nl['id'] ?>" class="act-btn edit" title="Éditer"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></a>
            <a href="newsletters.php?delete=<?= $nl['id'] ?>" class="act-btn del" title="Supprimer"
               onclick="return confirm('Supprimer ce brouillon ?')"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg></a>
          <?php else: ?>
            <a href="compose.php?id=<?= $nl['id'] ?>" class="act-btn view" title="Voir"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></a>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div>
<script>
function flushQueue(){
  var btn   = document.getElementById('btn-flush');
  var resteEl = document.getElementById('q-reste');
  var total = parseInt(resteEl.textContent) || 0;
  if(total <= 0){ alert('File vide.'); return; }
  if(!confirm('Envoyer la newsletter à '+total+' destinataire(s) ?')) return;

  btn.disabled = true;
  var prog = document.getElementById('q-progress'); prog.style.display = 'block';
  var bar  = document.getElementById('q-bar');
  var stat = document.getElementById('q-stat');
  var totalErr = 0, prevReste = total, stall = 0;

  function step(){
    fetch('/admin/send_now.php?json=1&max=25', {cache:'no-store'})
      .then(function(r){ return r.json(); })
      .then(function(d){
        if(!d || !d.ok){ stat.textContent = 'Erreur : '+((d&&d.error)||'réponse invalide'); btn.disabled=false; return; }
        totalErr += d.errors;
        var done = total - d.reste; if(done < 0) done = 0;
        var pct  = total > 0 ? Math.round(done/total*100) : 100;
        bar.style.width = pct + '%';
        stat.textContent = done+' / '+total+' traités · '+totalErr+' erreur(s) · '+d.reste+' restant(s)';
        resteEl.textContent = d.reste;

        // Garde-fou : si la file ne diminue plus, on arrête
        if(d.reste >= prevReste){ stall++; } else { stall = 0; }
        prevReste = d.reste;
        if(stall >= 2){ stat.textContent = '⚠ Arrêt : la file ne diminue plus ('+d.reste+' restant). Vérifie les erreurs / la config email.'; btn.disabled=false; return; }

        if(d.reste > 0){ step(); }
        else {
          bar.style.width = '100%';
          stat.textContent = '✅ Terminé : '+done+' envoyé(s), '+totalErr+' erreur(s).';
          setTimeout(function(){ location.reload(); }, 1800);
        }
      })
      .catch(function(e){ stat.textContent = 'Erreur réseau : '+e.message; btn.disabled=false; });
  }
  step();
}
</script>
</body>
</html>
