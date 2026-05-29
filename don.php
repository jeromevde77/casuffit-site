<?php
// don.php — Page de don dédiée, partageable par lien ou QR code
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/lang.php';
session_start();
$is_nl = (LANG === 'nl');
$is_logged = !empty($_SESSION['membre_id']);

$db = getDB();
$date_lancement = cfg('date_lancement', '2026-05-25');
$montant_initial = floatval(cfg('montant_initial', 0));
$objectif = floatval(cfg('montant_objectif', 15000));
try {
    $q = $db->prepare("SELECT COALESCE(SUM(montant),0) FROM member_dons WHERE statut='confirme' AND date_don >= ?");
    $q->execute([$date_lancement]);
    $recolte = $montant_initial + floatval($q->fetchColumn());
} catch (Exception $e) { $recolte = $montant_initial; }
$pct = $objectif > 0 ? min(100, round($recolte / $objectif * 100)) : 0;
$iban = cfg('iban', 'BE41 0689 0149 6910');
$bic  = cfg('bic', 'GKCCBEBB');
$benef = cfg('beneficiaire', 'Ça suffit ! ASBL');
$don_texte = cfgLang('don_texte', 'Combat juridique — Frais et procédures');
?><!DOCTYPE html>
<html lang="<?= $is_nl ? 'nl' : 'fr' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $is_nl ? 'Steun ons — Ça suffit !' : 'Soutenir le combat — Ça suffit !' ?></title>
<meta name="theme-color" content="#0e3d6b">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --bleu-hex:#1673B2;--bleu-fonce:#0e3d6b;--bleu-ciel:#bdd5f5;--bleu-leger:#eef5fc;
  --orange-hex:#FF9900;--orange-sombre:#e08000;--gris-texte:#666;
}
body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333;min-height:100vh}
a{color:var(--bleu-hex)}

/* Header */
.hd{background:linear-gradient(135deg,var(--bleu-fonce),var(--bleu-hex));color:#fff;padding:28px 20px 22px;text-align:center}
.hd img{width:62px;height:62px;border-radius:50%;border:3px solid var(--orange-hex);margin-bottom:10px}
.hd h1{font-size:1.6rem;font-weight:800;margin-bottom:2px}
.hd h1 span{color:var(--orange-hex)}
.hd p{font-size:.82rem;opacity:.75}

/* Wrap */
.wrap{max-width:540px;margin:0 auto;padding:20px 16px 40px}

/* Progress */
.prog-card{background:#fff;border-radius:12px;padding:18px 16px;margin-bottom:16px;box-shadow:0 2px 10px rgba(0,0,0,.07)}
.prog-label{font-size:.72rem;font-weight:700;color:var(--bleu-fonce);text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px}
.prog-amounts{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:8px}
.prog-amounts .cur{font-size:1.4rem;font-weight:800;color:var(--bleu-fonce)}
.prog-amounts .tot{font-size:.85rem;color:var(--gris-texte)}
.prog-bar{height:10px;background:#e2e8f0;border-radius:5px;overflow:hidden}
.prog-fill{height:100%;background:linear-gradient(90deg,var(--bleu-hex),var(--orange-hex));border-radius:5px;transition:width .5s}
.prog-pct{font-size:.72rem;color:var(--gris-texte);text-align:right;margin-top:4px}

/* Donation card */
.donation-card{background:#fff;border:1px solid var(--bleu-ciel);border-top:4px solid var(--orange-hex);border-radius:12px;padding:22px 18px;box-shadow:0 2px 10px rgba(0,0,0,.07);margin-bottom:16px}
.don-titre{font-size:1.05rem;font-weight:700;color:var(--bleu-hex);margin-bottom:3px}
.don-sub{font-size:.72rem;color:var(--gris-texte);text-transform:uppercase;letter-spacing:.06em;margin-bottom:18px}
.don-options{display:flex;flex-direction:column;gap:14px;margin-top:6px}
.don-option{border:2px solid var(--bleu-ciel);border-radius:10px;padding:16px;background:#fff;display:flex;flex-direction:column;gap:8px}
.don-option-membre{border-color:var(--orange-hex);background:#fffdf7}
.don-option-header{display:flex;align-items:center;gap:10px;margin-bottom:4px}
.don-option-icon{font-size:1.5rem;flex-shrink:0}
.don-option-titre{font-size:.9rem;font-weight:700;color:var(--bleu-fonce)}
.don-option-sub{font-size:.73rem;color:var(--gris-texte)}
.don-montant-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.don-mbtn{padding:10px 4px;border:1.5px solid var(--bleu-ciel);border-radius:8px;background:var(--bleu-leger);color:var(--bleu-hex);font-size:.85rem;font-weight:600;cursor:pointer;font-family:inherit;transition:all .15s;text-align:center}
.don-mbtn:hover,.don-mbtn.active{background:var(--bleu-hex);color:#fff;border-color:var(--bleu-hex)}
#libre-wrap{display:none;margin-top:6px}
#libre-wrap input{width:100%;padding:9px 12px;border:1.5px solid var(--bleu-ciel);border-radius:8px;font-size:.95rem;font-family:inherit;outline:none}
#libre-wrap input:focus{border-color:var(--bleu-hex)}
.qr-section{background:var(--bleu-leger);border:1px solid var(--bleu-ciel);border-radius:8px;padding:16px;margin-top:4px;text-align:center;cursor:pointer}
.iban-box{background:var(--bleu-leger);border-radius:8px;padding:12px 14px;font-size:.8rem;margin-top:4px}
.iban-val{font-family:monospace;font-size:.95rem;font-weight:700;color:var(--bleu-fonce);margin-bottom:3px}
.iban-bic{color:#666;margin-bottom:3px}
.iban-comm{color:#555}
.btn-copy{display:block;width:100%;margin-top:10px;background:var(--bleu-hex);color:#fff;border:none;padding:9px;border-radius:7px;font-size:.8rem;font-weight:700;cursor:pointer;font-family:inherit;transition:background .2s}
.btn-copy:hover{background:var(--bleu-fonce)}
.btn-copy.ok{background:#27ae60}
.don-check{display:flex;align-items:center;gap:7px;font-size:.75rem;color:#777;cursor:pointer;margin-top:4px}
.don-check input{accent-color:var(--bleu-hex)}
.btn-devenir-membre{display:block;text-align:center;background:var(--orange-hex);color:#fff;padding:13px;border-radius:8px;font-size:.9rem;font-weight:700;text-decoration:none;transition:background .2s;margin-top:4px}
.btn-devenir-membre:hover{background:var(--orange-sombre);text-decoration:none}
.btn-deja-membre{display:block;text-align:center;font-size:.78rem;color:var(--bleu-hex);text-decoration:none;padding:7px}
.btn-deja-membre:hover{text-decoration:underline}

/* Back button */
.back-btn{display:block;max-width:340px;margin:0 auto;padding:14px;background:var(--bleu-hex);color:#fff;text-align:center;border-radius:10px;text-decoration:none;font-weight:700;font-size:.95rem}
.back-btn:hover{background:var(--bleu-fonce);text-decoration:none;color:#fff}

/* Footer */
footer{text-align:center;padding:20px 16px;font-size:.72rem;color:#aaa}

/* Modal paiement */
@keyframes pmSlideUp{from{transform:translateY(100%)}to{transform:translateY(0)}}
</style>
</head>
<body>

<div class="hd">
  <img src="/assets/img/logo.png" alt="Ça suffit !">
  <h1>Ça suffit <span>!</span></h1>
  <p><?= $is_nl ? 'Steun de juridische strijd' : 'Soutenez le combat juridique' ?></p>
</div>

<div class="wrap">

  <!-- Progression -->
  <div class="prog-card">
    <div class="prog-label">🎯 <?= $is_nl ? 'Doelstelling — Juridische strijd' : 'Objectif — Combat juridique' ?></div>
    <div class="prog-amounts">
      <span class="cur"><?= number_format($recolte, 0, ',', ' ') ?> €</span>
      <span class="tot">/ <?= number_format($objectif, 0, ',', ' ') ?> €</span>
    </div>
    <div class="prog-bar"><div class="prog-fill" style="width:<?= $pct ?>%"></div></div>
    <div class="prog-pct"><?= $pct ?>% <?= $is_nl ? 'bereikt' : 'atteint' ?></div>
  </div>

  <!-- Widget don -->
  <div class="donation-card">
    <div class="don-titre">💶 <?= $is_nl ? 'Steun onze actie' : 'Soutenez notre action' ?></div>
    <div class="don-sub"><?= htmlspecialchars($don_texte) ?></div>

    <div class="don-options">

      <!-- Option membre -->
      <div class="don-option don-option-membre">
        <div class="don-option-header">
          <span class="don-option-icon">👤</span>
          <div>
            <div class="don-option-titre"><?= $is_nl ? 'Lid worden' : 'Devenir membre' ?></div>
            <div class="don-option-sub"><?= $is_nl ? 'Persoonlijke QR-code + nieuwsbrief' : 'QR code personnel + newsletter' ?></div>
          </div>
        </div>
        <div style="padding:12px;font-size:.8rem;color:#555;line-height:1.8;background:var(--bleu-leger);border-radius:6px">
          ✅ <?= $is_nl ? 'QR-code met uw persoonlijk communicatienummer' : 'QR code avec communication structurée <strong>(+++)</strong> unique' ?><br>
          ✅ <?= $is_nl ? 'Historiek van uw giften in uw ledenruimte' : 'Historique de vos dons dans votre espace' ?><br>
          ✅ <?= $is_nl ? 'Nieuwsbrief — blijf op de hoogte' : 'Newsletter — restez informé(e) de nos actions' ?><br>
          ✅ <?= $is_nl ? 'Veilig inloggen via magische link' : 'Accès sécurisé par lien magique (sans mot de passe)' ?>
        </div>
        <?php if ($is_logged): ?>
          <a href="/membre/dashboard.php" class="btn-devenir-membre">→ <?= $is_nl ? 'Mijn ledenruimte' : 'Mon espace membre' ?></a>
        <?php else: ?>
          <a href="/membre/inscription.php" class="btn-devenir-membre">✦ <?= $is_nl ? 'Mijn ledenruimte aanmaken' : 'Créer mon espace membre' ?></a>
          <a href="/membre/login.php" class="btn-deja-membre"><?= $is_nl ? 'Al lid → naar mijn ruimte' : 'Déjà membre → accéder à mon espace' ?></a>
        <?php endif; ?>
      </div>

      <!-- Option anonyme -->
      <div class="don-option">
        <div class="don-option-header">
          <span class="don-option-icon">🎯</span>
          <div>
            <div class="don-option-titre"><?= $is_nl ? 'Anonieme gift' : 'Don anonyme' ?></div>
            <div class="don-option-sub"><?= $is_nl ? 'Eenvoudige overschrijving, zonder account' : 'Virement simple, sans compte' ?></div>
          </div>
        </div>
        <div class="don-montant-grid">
          <button class="don-mbtn" data-v="20"  onclick="selectMontant(this)">20 €</button>
          <button class="don-mbtn active" data-v="50" onclick="selectMontant(this)">50 €</button>
          <button class="don-mbtn" data-v="100" onclick="selectMontant(this)">100 €</button>
          <button class="don-mbtn" data-v="250" onclick="selectMontant(this)">250 €</button>
          <button class="don-mbtn" data-v="500" onclick="selectMontant(this)">500 €</button>
          <button class="don-mbtn" data-v=""    onclick="selectMontant(this)"><?= $is_nl ? 'Vrij' : 'Libre' ?></button>
        </div>
        <div id="libre-wrap">
          <input type="number" id="montant-libre" min="1" step="1"
                 placeholder="<?= $is_nl ? 'Vrij bedrag in €' : 'Montant libre en €' ?>"
                 oninput="updateMontantLibre(this.value)">
        </div>
        <div class="qr-section" onclick="openPayModal()">
          <div id="qrcode-anonyme" style="display:inline-block;line-height:0;border:3px solid var(--bleu-hex);border-radius:6px;background:#fff"></div>
          <div style="margin-top:8px;font-size:.75rem;color:#888">📷 <?= $is_nl ? 'Scan · 📱 Tik voor rekeninggegevens' : 'Scannez · 📱 Appuyez pour les coordonnées' ?></div>
        </div>
        <div class="iban-box">
          <div class="iban-val"><?= htmlspecialchars($iban) ?></div>
          <div class="iban-bic">BIC : <?= htmlspecialchars($bic) ?> · <?= htmlspecialchars($benef) ?></div>
          <div class="iban-comm"><?= $is_nl ? 'Mededeling' : 'Communication' ?> : <strong>DON CASUFFIT <?= date('Y') ?></strong></div>
          <button class="btn-copy" id="copy-btn" onclick="copyIBAN()">📋 <?= $is_nl ? 'IBAN kopiëren' : 'Copier l\'IBAN' ?></button>
        </div>
      </div>

    </div><!-- /don-options -->
  </div>

  <!-- Retour site -->
  <a href="/" class="back-btn">← <?= $is_nl ? 'Naar de volledige site' : 'Retour au site complet' ?> casuffit.be</a>

</div>

<!-- Modal paiement -->
<div id="pay-modal" onclick="if(event.target===this)closePayModal()"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9999;align-items:flex-end;justify-content:center;padding:0">
  <div style="background:#fff;border-radius:20px 20px 0 0;padding:26px 22px 38px;width:100%;max-width:520px;animation:pmSlideUp .22s ease">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
      <strong style="font-size:1rem;color:#0e3d6b">💳 <?= $is_nl ? 'Rekeninggegevens' : 'Coordonnées de paiement' ?></strong>
      <button onclick="closePayModal()" style="border:none;background:none;font-size:1.5rem;cursor:pointer;color:#bbb;line-height:1;padding:0 4px">&times;</button>
    </div>
    <div id="pay-modal-content"></div>
    <div style="background:#f0f7ff;border-radius:8px;padding:12px 14px;font-size:.72rem;color:#2c5282;line-height:1.9;margin-top:4px">
      <strong><?= $is_nl ? 'Hoe betalen' : 'Comment payer' ?> :</strong><br>
      <?= $is_nl
        ? "1. Kopieer het IBAN → open uw bank-app<br>2. Nieuwe overschrijving → plak het IBAN<br>3. Bedrag + mededeling invullen<br>4. Valideren"
        : "1. Copiez l'IBAN → ouvrez votre app bancaire<br>2. Nouveau virement → collez l'IBAN<br>3. Entrez le montant + collez la communication<br>4. Validez" ?>
    </div>
  </div>
</div>

<footer>© <?= date('Y') ?> Ça suffit ! ASBL</footer>

<script>
var curMontant = 50;

function selectMontant(btn) {
  document.querySelectorAll('.don-mbtn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  var v = btn.dataset.v;
  if (v === '') {
    document.getElementById('libre-wrap').style.display = 'block';
    curMontant = null;
  } else {
    document.getElementById('libre-wrap').style.display = 'none';
    curMontant = parseInt(v);
    genQRAnon(curMontant);
  }
}

function updateMontantLibre(val) {
  var v = parseInt(val);
  if (v > 0) { curMontant = v; genQRAnon(v); }
}

function genQRAnon(montant) {
  montant = montant || curMontant;
  var el = document.getElementById('qrcode-anonyme');
  if (!el) return;
  el.innerHTML = '';
  var iban_raw = '<?= preg_replace('/\s+/', '', cfg('iban','BE41068901496910')) ?>';
  var epc = ['BCD','002','1','SCT',
    '<?= cfg('bic','GKCCBEBB') ?>',
    '<?= addslashes(cfg('beneficiaire','ca suffit ! ASBL')) ?>',
    iban_raw,
    montant ? 'EUR' + parseFloat(montant).toFixed(2) : '',
    '', 'DON CASUFFIT <?= date('Y') ?>', ''].join('\n');
  var src = 'https://quickchart.io/qr?text=' + encodeURIComponent(epc) + '&size=160&margin=1&ecLevel=M';
  var img = document.createElement('img');
  img.width = 160; img.height = 160; img.alt = 'QR code don';
  img.onerror = function() {
    this.src = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=' + encodeURIComponent(epc);
  };
  img.src = src;
  el.appendChild(img);
}

function copyIBAN() {
  var iban = '<?= htmlspecialchars(cfg('iban','BE41 0689 0149 6910'), ENT_QUOTES) ?>';
  navigator.clipboard.writeText(iban).then(function() {
    var btn = document.getElementById('copy-btn');
    btn.textContent = '✓ <?= $is_nl ? 'Gekopieerd!' : 'Copié !' ?>'; btn.classList.add('ok');
    setTimeout(function(){ btn.textContent = '📋 <?= $is_nl ? 'IBAN kopiëren' : 'Copier l\'IBAN' ?>'; btn.classList.remove('ok'); }, 2500);
  });
}

function openPayModal() {
  var iban = '<?= htmlspecialchars(cfg('iban','BE41 0689 0149 6910'), ENT_QUOTES) ?>';
  var comm = 'DON CASUFFIT <?= date('Y') ?>';
  document.getElementById('pay-modal-content').innerHTML =
    _payRow('IBAN', iban, iban.replace(/\s/g,''), 'pm-iban-btn', '#1673B2') +
    _payRow('Communication', comm, comm, 'pm-comm-btn', '#b85c00');
  document.getElementById('pay-modal').style.display = 'flex';
}
function _payRow(lbl, display, copyVal, btnId, color) {
  return '<div style="margin-bottom:14px">' +
    '<div style="font-size:.6rem;color:#999;text-transform:uppercase;letter-spacing:.07em;margin-bottom:4px">' + lbl + '</div>' +
    '<div style="display:flex;align-items:center;gap:8px">' +
    '<code style="flex:1;font-size:.95rem;font-weight:700;color:' + color + ';word-break:break-all">' + display + '</code>' +
    '<button id="' + btnId + '" onclick="copyPayField(\'' + copyVal.replace(/'/g,"\\'") + '\',\'' + btnId + '\')" ' +
    'style="padding:7px 13px;background:' + color + ';color:#fff;border:none;border-radius:7px;font-size:.75rem;font-weight:700;cursor:pointer;font-family:inherit;white-space:nowrap;flex-shrink:0">📋 <?= $is_nl ? 'Kopiëren' : 'Copier' ?></button>' +
    '</div></div>';
}
function copyPayField(val, btnId) {
  navigator.clipboard.writeText(val).then(function() {
    var b = document.getElementById(btnId);
    if (b) { var t=b.textContent; b.textContent='✓'; b.style.background='#27ae60';
      setTimeout(function(){b.textContent=t;b.style.background='';},2200); }
  }).catch(function(){ prompt('<?= $is_nl ? 'Kopieer manueel:' : 'Copiez manuellement :' ?>', val); });
}
function closePayModal() { document.getElementById('pay-modal').style.display='none'; }

document.addEventListener('DOMContentLoaded', function() { genQRAnon(50); });
</script>

</body>
</html>
