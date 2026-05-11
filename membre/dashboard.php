<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/functions.php';

session_start();
$db     = getDB();
$membre = requireMembre($db);

// ── Actions GET ───────────────────────────────────────────────────────────
if (isset($_GET['desabonner'])) {
    $db->prepare("UPDATE members SET newsletter=0 WHERE id=?")->execute(array($membre['id']));
    if ($membre['subscriber_id']) {
        $db->prepare("UPDATE subscribers SET statut='desabonne' WHERE id=?")->execute(array($membre['subscriber_id']));
    }
    header('Location: dashboard.php?msg=desabonne'); exit;
}
if (isset($_GET['reabonner'])) {
    $db->prepare("UPDATE members SET newsletter=1 WHERE id=?")->execute(array($membre['id']));
    if ($membre['subscriber_id']) {
        $db->prepare("UPDATE subscribers SET statut='actif' WHERE id=?")->execute(array($membre['subscriber_id']));
    }
    header('Location: dashboard.php?msg=reabonne'); exit;
}

// ── Créer un nouveau don avec OGM unique ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['creer_don'])) {
    $montant = floatval(str_replace(',', '.', isset($_POST['montant']) ? $_POST['montant'] : 0));
    if ($montant >= 1) {
        // Insérer le don en attente
        $db->prepare("INSERT INTO member_dons (member_id, montant, statut) VALUES (?, ?, 'en_attente')")
           ->execute(array($membre['id'], $montant));
        $don_id = $db->lastInsertId();
        // Générer l'OGM unique pour ce don
        $ogm_don = genererOGMDon($membre['id'], $don_id);
        $db->prepare("UPDATE member_dons SET ogm_don=?, communication=? WHERE id=?")
           ->execute(array($ogm_don, $ogm_don, $don_id));
        header('Location: dashboard.php?don_id=' . $don_id . '&msg=don_cree'); exit;
    }
}

// Recharger le membre
$stmt = $db->prepare("SELECT * FROM members WHERE id=?");
$stmt->execute(array($membre['id']));
$membre = $stmt->fetch();

// Historique des dons
$dons = $db->prepare("SELECT * FROM member_dons WHERE member_id=? ORDER BY date_don DESC");
$dons->execute(array($membre['id']));
$historique = $dons->fetchAll();

// Total confirmé
$stmt_total = $db->prepare("SELECT COALESCE(SUM(montant),0) FROM member_dons WHERE member_id=? AND statut='confirme'");
$stmt_total->execute(array($membre['id']));
$total = $stmt_total->fetchColumn();

// Don actif à afficher (depuis URL ou dernier en_attente)
$don_actif = null;
if (isset($_GET['don_id']) && is_numeric($_GET['don_id'])) {
    $stmt = $db->prepare("SELECT * FROM member_dons WHERE id=? AND member_id=?");
    $stmt->execute(array(intval($_GET['don_id']), $membre['id']));
    $don_actif = $stmt->fetch();
}
if (!$don_actif && !empty($historique)) {
    // Prendre le dernier don en attente
    foreach ($historique as $d) {
        if ($d['statut'] === 'en_attente') { $don_actif = $d; break; }
    }
}

$objectif  = floatval(cfg('montant_objectif', 15000));
$prenom    = $membre['prenom'] ?: 'Membre';
$msg_flash = isset($_GET['msg']) ? $_GET['msg'] : '';
$iban      = cfg('iban', 'BE41 0689 0149 6910');
$bic       = cfg('bic', 'GKCCBEBB');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Mon espace — ça suffit ! ASBL</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333;min-height:100vh}
    .site-header{background:linear-gradient(135deg,#0e3d6b,#1673B2);padding:0 20px;display:flex;align-items:center;min-height:58px;gap:12px}
    .site-header h1{color:#fff;font-size:1rem;font-weight:700}
    .site-header h1 span{color:#FF9900;font-style:italic}
    .site-header .back{margin-left:auto;color:rgba(255,255,255,0.7);text-decoration:none;font-size:0.8rem}
    .container{max-width:900px;margin:0 auto;padding:24px 16px}
    .welcome{font-size:1.2rem;font-weight:700;color:#0e3d6b;margin-bottom:4px}
    .code-badge{display:inline-block;background:#e6f1fb;color:#1673B2;font-size:0.75rem;font-weight:700;padding:3px 10px;border-radius:20px;border:1px solid #b5d4f4;margin-bottom:18px;letter-spacing:0.04em}
    .grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
    .card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,0.06)}
    .card h2{font-size:0.88rem;font-weight:700;color:#0e3d6b;margin-bottom:14px;padding-bottom:8px;border-bottom:1px solid #eee}

    /* Créer un don */
    .don-form{display:flex;flex-direction:column;gap:10px}
    .montant-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:6px}
    .mbtn{padding:9px 4px;border:1.5px solid #dde4ed;border-radius:6px;background:#f8f9fa;font-size:0.85rem;font-weight:600;cursor:pointer;color:#333;transition:all .15s;font-family:inherit;text-align:center}
    .mbtn.active,.mbtn:hover{border-color:#1673B2;background:#e6f1fb;color:#1673B2}
    .input-libre{width:100%;padding:8px 10px;border:1.5px solid #dde4ed;border-radius:6px;font-size:0.85rem;font-family:inherit;display:none;outline:none}
    .input-libre:focus{border-color:#1673B2}
    .btn-creer{background:#FF9900;color:#fff;border:none;padding:11px;border-radius:7px;font-size:0.88rem;font-weight:700;cursor:pointer;font-family:inherit;width:100%}
    .btn-creer:hover{background:#e68800}
    .btn-blue{background:#1673B2;color:#fff;border:none;padding:9px;border-radius:7px;font-size:0.8rem;font-weight:700;cursor:pointer;font-family:inherit;width:100%;margin-top:5px}
    .btn-blue:hover{background:#125a90}
    .btn-green{background:#27ae60;color:#fff;border:none;padding:9px;border-radius:7px;font-size:0.8rem;font-weight:700;cursor:pointer;font-family:inherit;width:100%;margin-top:5px}

    /* QR Section */
    .qr-section{text-align:center}
    .qr-section canvas{border-radius:8px;border:4px solid #1673B2;display:block;margin:0 auto 10px}
    .ogm-display{font-family:monospace;font-size:0.95rem;font-weight:700;color:#1673B2;background:#e6f1fb;padding:8px 12px;border-radius:6px;text-align:center;margin-bottom:8px;letter-spacing:0.08em;word-break:break-all}
    .montant-display{font-size:1.4rem;font-weight:800;color:#FF9900;margin-bottom:4px}
    .iban-mini{font-size:0.72rem;color:#888;line-height:1.7;text-align:center}
    .iban-mini strong{color:#333}
    .don-ref{font-size:0.7rem;color:#aaa;margin-top:4px}

    /* Historique */
    .don-row{display:flex;align-items:center;gap:8px;padding:9px 0;border-bottom:1px solid #f5f5f5;font-size:0.8rem;cursor:pointer;transition:background .1s;border-radius:4px;padding-left:4px}
    .don-row:hover{background:#f8fbff}
    .don-row:last-child{border:none}
    .don-montant{font-weight:700;color:#0e3d6b;min-width:55px}
    .don-ogm{color:#1673B2;flex:1;font-size:0.7rem;font-family:monospace}
    .don-date{color:#aaa;font-size:0.7rem;white-space:nowrap}
    .badge{display:inline-block;padding:2px 7px;border-radius:10px;font-size:0.62rem;font-weight:700}
    .b-ok{background:#e8f8f0;color:#27ae60}
    .b-wait{background:#fff3e0;color:#FF9900}
    .don-row.active-don{background:#e6f1fb;border-left:3px solid #1673B2}
    .don-row-hint{font-size:0.68rem;color:#aaa;text-align:center;padding:8px 0;font-style:italic}

    /* Stats */
    .stat-val{font-size:1.7rem;font-weight:800;color:#1673B2}
    .stat-lab{font-size:0.7rem;color:#888;text-transform:uppercase;letter-spacing:0.06em;margin-top:2px}

    /* Newsletter */
    .nl-row{display:flex;align-items:center;gap:10px;padding:10px;background:#f8f9fa;border-radius:8px;margin-bottom:10px}
    .nl-dot{width:9px;height:9px;border-radius:50%;flex-shrink:0}
    .nl-dot.on{background:#27ae60} .nl-dot.off{background:#e74c3c}
    .nl-txt{font-size:0.8rem;color:#555;flex:1}
    .btn-nl{padding:6px 12px;border-radius:6px;font-size:0.72rem;font-weight:700;cursor:pointer;border:none;font-family:inherit;text-decoration:none;display:inline-block}
    .btn-nl-off{background:#fde8e8;color:#c53030}
    .btn-nl-on{background:#e8f8f0;color:#27ae60}

    .flash{padding:11px 14px;border-radius:8px;margin-bottom:14px;font-size:0.82rem;border-left:3px solid}
    .flash-ok{background:#e8f8f0;color:#276749;border-color:#48bb78}
    .flash-info{background:#e6f1fb;color:#1673B2;border-color:#1673B2}
    .flash-warn{background:#fff3e0;color:#856404;border-color:#FF9900}
    .logout-link{display:block;text-align:center;margin-top:18px;font-size:0.75rem;color:#aaa;text-decoration:none}
    @media(max-width:600px){.grid2{grid-template-columns:1fr}}
  </style>
</head>
<body>
<header class="site-header">
  <img src="<?= SITE_URL ?>/medias/logo.png" style="width:36px;height:36px;object-fit:contain" alt="logo" onerror="this.style.display='none'">
  <h1>ça suffit ! <span>ASBL</span></h1>
  <a href="<?= SITE_URL ?>" class="back">← Site principal</a>
</header>

<div class="container">

  <?php if ($msg_flash === 'desabonne'): ?>
    <div class="flash flash-info">✓ Désabonné(e) de la newsletter.</div>
  <?php elseif ($msg_flash === 'reabonne'): ?>
    <div class="flash flash-ok">✓ Réabonné(e) à la newsletter.</div>
  <?php elseif ($msg_flash === 'don_cree'): ?>
    <div class="flash flash-ok">✓ QR code généré ! Scannez-le pour effectuer votre virement.</div>
  <?php endif; ?>

  <div class="welcome">Bonjour <?= htmlspecialchars($prenom) ?> 👋</div>
  <div class="code-badge">Membre <?= htmlspecialchars($membre['code_membre']) ?></div>
  <?php if ($membre['adresse'] || $membre['commune']): ?>
  <div style="font-size:.78rem;color:#888;margin-bottom:14px">
    📍 <?= htmlspecialchars(trim(($membre['adresse'] ?? '').' '.($membre['commune'] ?? ''))) ?>
  </div>
  <?php endif; ?>

  <div class="grid2">

    <!-- ── CRÉER UN DON ── -->
    <div class="card">
      <h2>💶 Nouveau don — générer un QR</h2>
      <form method="POST" class="don-form" id="don-form">
        <div>
          <div style="font-size:0.72rem;color:#888;margin-bottom:6px;font-weight:600;text-transform:uppercase">Choisissez un montant</div>
          <div class="montant-grid">
            <button type="button" class="mbtn" onclick="selectM(this,'20')">20 €</button>
            <button type="button" class="mbtn active" onclick="selectM(this,'50')">50 €</button>
            <button type="button" class="mbtn" onclick="selectM(this,'100')">100 €</button>
            <button type="button" class="mbtn" onclick="selectM(this,'250')">250 €</button>
            <button type="button" class="mbtn" onclick="selectM(this,'500')">500 €</button>
            <button type="button" class="mbtn" onclick="selectM(this,'libre')">Libre</button>
          </div>
          <input type="number" name="montant_libre" id="input-libre" class="input-libre" placeholder="Montant en €" min="1" step="0.01">
          <input type="hidden" name="montant" id="input-montant" value="50">
        </div>
        <button type="submit" name="creer_don" class="btn-creer">
          ✨ Générer mon QR code de paiement
        </button>
      </form>

      <div style="margin-top:14px;padding-top:12px;border-top:1px solid #eee;font-size:0.75rem;color:#888;line-height:1.6">
        <strong style="color:#555">Comment ça marche ?</strong><br>
        Choisissez un montant → cliquez → un QR code unique est généré avec votre communication structurée personnelle. Scannez-le avec votre app bancaire pour payer.
      </div>
    </div>

    <!-- ── QR CODE ACTIF ── -->
    <div class="card">
      <h2>📱 QR code de paiement</h2>
      <?php if ($don_actif): ?>
      <div class="qr-section" id="qr-zone">
        <div class="montant-display"><?= number_format($don_actif['montant'], 2, ',', '.') ?> €</div>
        <div id="qrcode-wrap" style="border:4px solid #1673B2;border-radius:8px;display:inline-block;background:#fff;line-height:0">
        <img id="qrcode-img" src="" width="160" height="160" style="display:block" alt="QR code">
      </div>
        <div class="ogm-display"><?= htmlspecialchars($don_actif['ogm_don'] ?: $membre['ogm']) ?></div>
        <div class="iban-mini">
          <strong><?= htmlspecialchars($iban) ?></strong><br>
          BIC : <?= htmlspecialchars($bic) ?><br>
          <?= htmlspecialchars(cfg('beneficiaire','ça suffit ! ASBL')) ?>
        </div>
        <div class="don-ref">Réf. don #<?= $don_actif['id'] ?></div>
        <button class="btn-blue" onclick="genererQR(ogm_actif, montant_actif)">🔄 Actualiser</button>
        <button class="btn-green" onclick="telechargerQR()">⬇ Télécharger</button>
      </div>
      <?php else: ?>
      <div style="text-align:center;padding:30px 10px;color:#aaa">
        <div style="font-size:2rem;margin-bottom:10px">💳</div>
        <div style="font-size:0.85rem">Choisissez un montant<br>et générez votre QR code →</div>
      </div>
      <?php endif; ?>
    </div>

  </div>

  <div class="grid2">

    <!-- ── HISTORIQUE DONS ── -->
    <div class="card">
      <h2>📋 Historique de mes dons</h2>
      <?php if (empty($historique)): ?>
        <div style="color:#aaa;font-size:0.82rem;text-align:center;padding:16px">
          Aucun don enregistré.<br>
          <span style="font-size:0.75rem">Générez votre premier QR code ci-dessus.</span>
        </div>
      <?php else: ?>
        <?php foreach ($historique as $d): ?>
        <div class="don-row <?= ($don_actif && $don_actif['id'] == $d['id']) ? 'active-don' : '' ?>"
             onclick="afficherDon('<?= addslashes($d['ogm_don'] ?: $membre['ogm']) ?>', <?= $d['montant'] ?>, <?= $d['id'] ?>)"
             title="Cliquer pour afficher ce QR">
          <div class="don-montant"><?= number_format($d['montant'], 2, ',', '.') ?> €</div>
          <div class="don-ogm"><?= htmlspecialchars($d['ogm_don'] ?: '—') ?></div>
          <div class="don-date"><?= date('d/m/Y', strtotime($d['date_don'])) ?></div>
          <?php if ($d['statut'] === 'confirme'): ?>
            <span class="badge b-ok">✓</span>
          <?php else: ?>
            <span class="badge b-wait">⏳</span>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <div class="don-row-hint">Cliquez sur un don pour afficher son QR code</div>
      <?php endif; ?>
    </div>

    <!-- ── STATS + NEWSLETTER ── -->
    <div style="display:flex;flex-direction:column;gap:16px">

      <div class="card">
        <h2>📊 Mes contributions</h2>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div>
            <div class="stat-val"><?= number_format($total, 0, ',', '.') ?> €</div>
            <div class="stat-lab">Total confirmé</div>
          </div>
          <div>
            <div class="stat-val"><?= count($historique) ?></div>
            <div class="stat-lab">Don(s)</div>
          </div>
        </div>
        <?php if ($objectif > 0 && $total > 0): ?>
        <div style="background:#eee;border-radius:4px;height:5px;margin-top:12px">
          <div style="background:linear-gradient(90deg,#1673B2,#FF9900);height:5px;border-radius:4px;width:<?= min(100,round($total/$objectif*100)) ?>%"></div>
        </div>
        <div style="font-size:0.68rem;color:#aaa;margin-top:3px"><?= round($total/$objectif*100) ?>% de l'objectif <?= number_format($objectif,0,',',' ') ?> €</div>
        <?php endif; ?>
      </div>

      <div class="card">
        <h2>📧 Newsletter</h2>
        <div class="nl-row">
          <div class="nl-dot <?= $membre['newsletter'] ? 'on' : 'off' ?>"></div>
          <div class="nl-txt"><?= $membre['newsletter'] ? 'Abonné(e) aux actualités' : 'Non abonné(e)' ?></div>
        </div>
        <?php if ($membre['newsletter']): ?>
          <a href="dashboard.php?desabonner=1" class="btn-nl btn-nl-off" onclick="return confirm('Se désabonner ?')" style="display:block;text-align:center;text-decoration:none;padding:7px">Se désabonner</a>
        <?php else: ?>
          <a href="dashboard.php?reabonner=1" class="btn-nl btn-nl-on" style="display:block;text-align:center;text-decoration:none;padding:7px">Se réabonner</a>
        <?php endif; ?>
      </div>

    </div>
  </div>

  <a href="logout.php" class="logout-link">⎋ Se déconnecter</a>
  <a href="profil.php" class="logout-link" style="margin-top:8px;background:#f0f6ff;color:#1673B2;border-color:#b0d4f0">✏️ Modifier mon profil</a>
</div>

<script>
var membre_prenom = "<?= addslashes($membre['prenom']) ?>";
var membre_nom    = "<?= addslashes($membre['nom']) ?>";
var membre_code   = "<?= addslashes($membre['code_membre']) ?>";
var iban_raw      = "<?= preg_replace('/\s+/', '', $iban) ?>";
var bic           = "<?= addslashes($bic) ?>";
var montant_actif = <?= $don_actif ? $don_actif['montant'] : 50 ?>;
var ogm_actif     = "<?= addslashes($don_actif ? ($don_actif['ogm_don'] ?: $membre['ogm']) : '') ?>";
var montant_selec = 50;
var qr_obj        = null;

function selectM(btn, val) {
    document.querySelectorAll('.mbtn').forEach(function(b){b.classList.remove('active')});
    btn.classList.add('active');
    var libre = document.getElementById('input-libre');
    if (val === 'libre') {
        libre.style.display = 'block';
        libre.focus();
        libre.addEventListener('input', function() {
            document.getElementById('input-montant').value = this.value;
        });
    } else {
        libre.style.display = 'none';
        document.getElementById('input-montant').value = val;
        montant_selec = parseFloat(val);
    }
}

function getEPC(ogm, montant) {
    var lines = [
        'BCD',
        '002',
        '1',
        'SCT',
        bic,
        '<?= addslashes(cfg('beneficiaire','ca suffit ! ASBL')) ?>',
        iban_raw,
        montant ? 'EUR' + parseFloat(montant).toFixed(2) : 'EUR0.00',
        '',
        ogm,
        ''
    ];
    return lines.join('\n');
}

function genererQR(ogm, montant) {
    ogm     = ogm     || ogm_actif;
    montant = montant || montant_actif;
    var img = document.getElementById('qrcode-img');
    if (!img) return;
    var epc     = getEPC(ogm, montant);
    var encoded = encodeURIComponent(epc);
    // quickchart.io — API QR gratuite et fiable (remplace Google Charts)
    img.src = 'https://quickchart.io/qr?text=' + encoded + '&size=160&margin=1&ecLevel=M';
    img.onerror = function() {
        // Fallback : QR code API alternative
        img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=' + encoded;
    };
}

function telechargerQR() {
    var img = document.getElementById('qrcode-img');
    if (!img || !img.src) { alert('QR code non disponible'); return; }
    var link = document.createElement('a');
    link.download = 'qr-don-' + ogm_actif.replace(/\+/g,'').replace(/\//g,'-') + '.png';
    link.href = img.src;
    link.click();
}

function afficherDon(ogm, montant, don_id) {
    // Mettre à jour l'affichage QR sans rechargement
    ogm_actif     = ogm;
    montant_actif = montant;
    // Mettre en évidence la ligne cliquée
    document.querySelectorAll('.don-row').forEach(function(r){r.classList.remove('active-don')});
    event.currentTarget.classList.add('active-don');
    // Mettre à jour le montant et l'OGM affichés
    var md = document.querySelector('.montant-display');
    if (md) md.textContent = montant.toLocaleString('fr-BE', {minimumFractionDigits:2}) + ' €';
    var od = document.querySelector('.ogm-display');
    if (od) od.textContent = ogm;
    var ref = document.querySelector('.don-ref');
    if (ref) ref.textContent = 'Réf. don #' + don_id;
    genererQR(ogm, montant);
}


// Initialiser le QR au chargement
function waitQR() {
    if (ogm_actif) {
        genererQR(ogm_actif, montant_actif);
    }
}
window.addEventListener('load', waitQR);
</script>
</body>
</html>
