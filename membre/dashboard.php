<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/functions.php';

session_start();
require_once __DIR__ . '/lang.php';
$db     = getDB();
$membre = requireMembre($db);

// ── Actions POST ──────────────────────────────────────────────────────────

// Mise à jour profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profil'])) {
    $prenom      = trim($_POST['prenom']      ?? '');
    $nom         = trim($_POST['nom']         ?? '');
    $rue         = trim($_POST['rue']         ?? '');
    $numero      = trim($_POST['numero']      ?? '');
    $boite       = trim($_POST['boite']       ?? '');
    $code_postal = trim($_POST['code_postal'] ?? '');
    $commune     = trim($_POST['commune']     ?? '');
    $telephone   = trim($_POST['telephone']   ?? '');
    $iban_membre = strtoupper(preg_replace('/\s+/', '', $_POST['iban_membre'] ?? ''));
    $rgpd        = isset($_POST['rgpd_accepte']) ? 1 : 0;
    // Adresse compatible (champ legacy)
    $adresse = trim("$rue $numero" . ($boite ? " bte $boite" : ''));

    $db->prepare("UPDATE members SET
        prenom=?, nom=?, rue=?, numero=?, boite=?, code_postal=?, commune=?, adresse=?,
        telephone=?, iban_membre=?,
        rgpd_accepte=?,
        rgpd_date = CASE WHEN ? = 1 AND (rgpd_accepte = 0 OR rgpd_date IS NULL) THEN NOW() ELSE rgpd_date END,
        donnees_verifiees_at = NOW()
        WHERE id=?")
        ->execute([$prenom, $nom, $rue, $numero, $boite, $code_postal, $commune, $adresse,
                   $telephone, $iban_membre ?: null, $rgpd, $rgpd, $membre['id']]);

    if ($membre['subscriber_id']) {
        $db->prepare("UPDATE subscribers SET prenom=?, nom=?, adresse=?, commune=?, telephone=? WHERE id=?")
           ->execute([$prenom, $nom, $adresse, $commune, $telephone, $membre['subscriber_id']]);
    }
    header('Location: dashboard.php?msg=profil_ok'); exit;
}

// Changement d'email — demande
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['changer_email'])) {
    $email_nouveau = strtolower(trim($_POST['email_nouveau'] ?? ''));
    if (!filter_var($email_nouveau, FILTER_VALIDATE_EMAIL)) {
        header('Location: dashboard.php?tab=profil&msg=email_invalide'); exit;
    }
    // Vérifier que l'email n'est pas déjà pris
    $exist = $db->prepare("SELECT id FROM members WHERE email=? AND id!=?");
    $exist->execute([$email_nouveau, $membre['id']]);
    if ($exist->fetch()) {
        header('Location: dashboard.php?tab=profil&msg=email_pris'); exit;
    }
    $token = bin2hex(random_bytes(32));
    $db->prepare("UPDATE members SET email_nouveau=?, token_email_change=? WHERE id=?")
       ->execute([$email_nouveau, $token, $membre['id']]);
    // Envoyer email de confirmation
    $lien = SITE_URL . '/membre/confirm_email.php?token=' . $token;
    $sujet = 'Confirmation changement d\'email — ça suffit ! ASBL';
    $corps = "Bonjour {$membre['prenom']},\n\nVous avez demandé à changer votre email vers : $email_nouveau\n\nCliquez sur ce lien pour confirmer :\n$lien\n\nCe lien expire dans 24h. Si vous n'avez pas fait cette demande, ignorez ce message.\n\nL'équipe ça suffit ! ASBL";
    @mail($email_nouveau, $sujet, $corps, "From: " . cfg('site_email', 'info@casuffit.be') . "\r\nContent-Type: text/plain; charset=UTF-8");
    header('Location: dashboard.php?tab=profil&msg=email_confirm_envoye'); exit;
}

// Nouveau don
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['creer_don'])) {
    $montant = floatval(str_replace(',', '.', $_POST['montant'] ?? 0));
    if ($montant >= 1) {
        $db->prepare("INSERT INTO member_dons (member_id, montant, statut) VALUES (?, ?, 'en_attente')")
           ->execute([$membre['id'], $montant]);
        $don_id = $db->lastInsertId();
        $ogm_don = genererOGMDon($membre['id'], $don_id);
        $db->prepare("UPDATE member_dons SET ogm_don=?, communication=? WHERE id=?")
           ->execute([$ogm_don, $ogm_don, $don_id]);
        header('Location: dashboard.php?don_id='.$don_id.'&msg=don_cree'); exit;
    }
}

// Suppression compte
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supprimer_compte'])) {
    if (trim($_POST['confirm_suppression'] ?? '') === 'SUPPRIMER') {
        $db->prepare("UPDATE members SET
            prenom='[Supprimé]', nom='[Supprimé]', rue=NULL, numero=NULL, boite=NULL,
            code_postal=NULL, adresse=NULL, commune=NULL, telephone=NULL, iban_membre=NULL,
            token_magic=NULL, token_magic_expiry=NULL, email_nouveau=NULL, token_email_change=NULL,
            compte_supprime=1, statut='inactif',
            email=CONCAT('supprime_', id, '_', UNIX_TIMESTAMP(), '@casuffit.deleted')
            WHERE id=?")->execute([$membre['id']]);
        if ($membre['subscriber_id'])
            $db->prepare("UPDATE subscribers SET statut='desabonne' WHERE id=?")->execute([$membre['subscriber_id']]);
        session_destroy();
        header('Location: '.SITE_URL.'?msg=compte_supprime'); exit;
    }
}

// Actions GET
if (isset($_GET['desabonner'])) {
    $db->prepare("UPDATE members SET newsletter=0 WHERE id=?")->execute([$membre['id']]);
    if ($membre['subscriber_id'])
        $db->prepare("UPDATE subscribers SET statut='desabonne' WHERE id=?")->execute([$membre['subscriber_id']]);
    header('Location: dashboard.php?msg=desabonne'); exit;
}
if (isset($_GET['reabonner'])) {
    $db->prepare("UPDATE members SET newsletter=1 WHERE id=?")->execute([$membre['id']]);
    if ($membre['subscriber_id'])
        $db->prepare("UPDATE subscribers SET statut='actif' WHERE id=?")->execute([$membre['subscriber_id']]);
    header('Location: dashboard.php?msg=reabonne'); exit;
}

// Recharger membre
$stmt = $db->prepare("SELECT * FROM members WHERE id=?");
$stmt->execute([$membre['id']]);
$membre = $stmt->fetch();

// Données
$dons = $db->prepare("SELECT * FROM member_dons WHERE member_id=? ORDER BY date_don DESC");
$dons->execute([$membre['id']]);
$historique = $dons->fetchAll();
$stmt_total = $db->prepare("SELECT COALESCE(SUM(montant),0) FROM member_dons WHERE member_id=? AND statut='confirme'");
$stmt_total->execute([$membre['id']]);
$total = $stmt_total->fetchColumn();

$don_actif = null;
if (isset($_GET['don_id']) && is_numeric($_GET['don_id'])) {
    $s = $db->prepare("SELECT * FROM member_dons WHERE id=? AND member_id=?");
    $s->execute([intval($_GET['don_id']), $membre['id']]);
    $don_actif = $s->fetch();
}
if (!$don_actif) {
    foreach ($historique as $d) { if ($d['statut'] === 'en_attente') { $don_actif = $d; break; } }
}

$objectif  = floatval(cfg('montant_objectif', 15000));
$msg_flash = $_GET['msg'] ?? '';
$tab_actif = $_GET['tab'] ?? 'dons';
$iban      = cfg('iban', 'BE41 0689 0149 6910');
$bic       = cfg('bic', 'GKCCBEBB');
$prenom    = $membre['prenom'] ?: 'Membre';

// Bannières
$rgpd_manquant = !($membre['rgpd_accepte'] ?? 0);
$besoin_maj = true;
if (!empty($membre['donnees_verifiees_at'])) {
    $besoin_maj = (new DateTime())->diff(new DateTime($membre['donnees_verifiees_at']))->days > 365;
}
if ($rgpd_manquant || $besoin_maj) $tab_actif = 'profil';
if (in_array($msg_flash, ['profil_ok','email_confirm_envoye','email_invalide','email_pris'])) $tab_actif = 'profil';
?>
<!DOCTYPE html>
<html lang="<?= $LANG ?>">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= tm('dashboard_page') ?></title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333;min-height:100vh}
    .site-header{background:linear-gradient(135deg,#0e3d6b,#1673B2);padding:0 20px;display:flex;align-items:center;min-height:58px;gap:12px}
    .site-header h1{color:#fff;font-size:1rem;font-weight:700}
    .site-header h1 span{color:#FF9900;font-style:italic}
    .site-header .back{margin-left:auto;color:rgba(255,255,255,.7);text-decoration:none;font-size:.8rem}
    .container{max-width:900px;margin:0 auto;padding:24px 16px 48px}
    .welcome{font-size:1.2rem;font-weight:700;color:#0e3d6b;margin-bottom:4px}
    .code-badge{display:inline-block;background:#e6f1fb;color:#1673B2;font-size:.75rem;font-weight:700;padding:3px 10px;border-radius:20px;border:1px solid #b5d4f4;margin-bottom:14px;letter-spacing:.04em}
    .tabs{display:flex;border-bottom:2px solid #e0e8f0;margin-bottom:20px}
    .tab-btn{padding:10px 20px;background:none;border:none;border-bottom:3px solid transparent;margin-bottom:-2px;cursor:pointer;font-size:.85rem;font-weight:600;color:#888;font-family:inherit;transition:all .15s}
    .tab-btn.active{color:#0e3d6b;border-bottom-color:#1673B2}
    .tab-panel{display:none}.tab-panel.active{display:block}
    .card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,.06);margin-bottom:16px}
    .card h2{font-size:.88rem;font-weight:700;color:#0e3d6b;margin-bottom:14px;padding-bottom:8px;border-bottom:1px solid #eee}
    .grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
    .flash{padding:11px 14px;border-radius:8px;margin-bottom:16px;font-size:.82rem;border-left:3px solid}
    .flash-ok{background:#e8f8f0;color:#276749;border-color:#48bb78}
    .flash-info{background:#e6f1fb;color:#1673B2;border-color:#1673B2}
    .flash-warn{background:#fff3e0;color:#856404;border-color:#FF9900}
    .flash-err{background:#fde8e8;color:#c53030;border-color:#e53e3e}
    .banner-maj{background:#fff3e0;border:1.5px solid #FF9900;border-radius:10px;padding:14px 16px;margin-bottom:18px;display:flex;align-items:center;gap:12px}
    .banner-maj .bm-txt{flex:1;font-size:.82rem;color:#856404;line-height:1.5}
    .banner-rgpd{background:#fde8e8;border:1.5px solid #e53e3e;border-radius:10px;padding:14px 16px;margin-bottom:18px}
    .banner-rgpd strong{color:#c53030}
    /* Don */
    .montant-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin-bottom:10px}
    .mbtn{padding:9px 4px;border:1.5px solid #dde4ed;border-radius:6px;background:#f8f9fa;font-size:.85rem;font-weight:600;cursor:pointer;color:#333;transition:all .15s;font-family:inherit;text-align:center}
    .mbtn.active,.mbtn:hover{border-color:#1673B2;background:#e6f1fb;color:#1673B2}
    .input-libre{width:100%;padding:8px 10px;border:1.5px solid #dde4ed;border-radius:6px;font-size:.85rem;font-family:inherit;display:none;outline:none;margin-bottom:8px}
    .btn-orange{background:#FF9900;color:#fff;border:none;padding:11px;border-radius:7px;font-size:.88rem;font-weight:700;cursor:pointer;font-family:inherit;width:100%}
    .btn-blue{background:#1673B2;color:#fff;border:none;padding:9px 14px;border-radius:7px;font-size:.82rem;font-weight:700;cursor:pointer;font-family:inherit}
    .btn-dark{background:#0e3d6b;color:#fff;border:none;padding:11px;border-radius:7px;font-size:.88rem;font-weight:700;cursor:pointer;font-family:inherit;width:100%}
    .btn-green{background:#27ae60;color:#fff;border:none;padding:9px;border-radius:7px;font-size:.8rem;font-weight:700;cursor:pointer;font-family:inherit;width:100%;margin-top:6px}
    .btn-gray{background:#f0f4f8;color:#555;border:1.5px solid #dde6f0;padding:9px;border-radius:7px;font-size:.8rem;font-weight:600;cursor:pointer;font-family:inherit;width:100%;margin-top:6px}
    .btn-red{background:#e53e3e;color:#fff;border:none;padding:9px;border-radius:7px;font-size:.82rem;font-weight:700;cursor:pointer;font-family:inherit;width:100%;margin-top:6px}
    /* QR */
    .qr-section{text-align:center}
    .qr-section img{border-radius:8px;border:4px solid #1673B2;display:block;margin:0 auto 10px}
    .ogm-display{font-family:monospace;font-size:.95rem;font-weight:700;color:#1673B2;background:#e6f1fb;padding:8px 12px;border-radius:6px;text-align:center;margin-bottom:8px;word-break:break-all}
    .montant-display{font-size:1.4rem;font-weight:800;color:#FF9900;margin-bottom:4px}
    .iban-mini{font-size:.72rem;color:#888;line-height:1.7;text-align:center}
    /* Historique */
    .don-row{display:flex;align-items:center;gap:8px;padding:9px 4px;border-bottom:1px solid #f5f5f5;font-size:.8rem;cursor:pointer;border-radius:4px}
    .don-row:hover{background:#f8fbff}.don-row:last-child{border:none}
    .don-montant{font-weight:700;color:#0e3d6b;min-width:55px}
    .don-ogm{color:#1673B2;flex:1;font-size:.7rem;font-family:monospace}
    .don-date{color:#aaa;font-size:.7rem;white-space:nowrap}
    .badge{display:inline-block;padding:2px 7px;border-radius:10px;font-size:.62rem;font-weight:700}
    .b-ok{background:#e8f8f0;color:#27ae60}.b-wait{background:#fff3e0;color:#FF9900}
    .don-row.active-don{background:#e6f1fb;border-left:3px solid #1673B2}
    .stat-val{font-size:1.7rem;font-weight:800;color:#1673B2}
    .stat-lab{font-size:.7rem;color:#888;text-transform:uppercase;letter-spacing:.06em;margin-top:2px}
    /* Profil */
    .form-group{margin-bottom:14px}
    .form-group label{display:block;font-size:.72rem;font-weight:700;color:#555;margin-bottom:4px;text-transform:uppercase;letter-spacing:.04em}
    .form-group input,.form-group select{width:100%;padding:9px 12px;border:1.5px solid #dde4ed;border-radius:7px;font-size:.88rem;font-family:inherit;outline:none;color:#333}
    .form-group input:focus{border-color:#1673B2}
    .form-group input:disabled{background:#f5f5f5;color:#888}
    .form-row-3{display:grid;grid-template-columns:2fr 1fr 1fr;gap:10px}
    .form-row-2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .form-row-cp{display:grid;grid-template-columns:1fr 2fr;gap:10px}
    .section-title{font-size:.78rem;font-weight:800;color:#0e3d6b;text-transform:uppercase;letter-spacing:.06em;margin:18px 0 10px;padding-bottom:6px;border-bottom:1.5px solid #e8f0fa}
    .rgpd-box{background:#f0f6ff;border:1.5px solid #b0d4f0;border-radius:8px;padding:14px;margin:14px 0}
    .rgpd-label{display:flex;align-items:flex-start;gap:10px;cursor:pointer}
    .rgpd-label input{margin-top:3px;accent-color:#1673B2;width:16px;height:16px;flex-shrink:0}
    .rgpd-label span{font-size:.8rem;color:#333;line-height:1.5}
    .iban-box{background:#fff8e8;border:1.5px solid #ffd580;border-radius:8px;padding:14px;margin:14px 0}
    .iban-box .ib-title{font-size:.8rem;font-weight:700;color:#856404;margin-bottom:6px}
    .iban-box p{font-size:.75rem;color:#666;line-height:1.5;margin-bottom:10px}
    .email-change-box{background:#f7fafd;border:1.5px solid #b0d4f0;border-radius:8px;padding:14px;margin:14px 0}
    .email-change-box .ec-title{font-size:.8rem;font-weight:700;color:#0e3d6b;margin-bottom:8px}
    .email-change-box input{width:100%;padding:8px 12px;border:1.5px solid #dde4ed;border-radius:7px;font-size:.88rem;font-family:inherit;outline:none;margin-bottom:8px}
    .email-change-box input:focus{border-color:#1673B2}
    .danger-zone{border:1.5px solid #fed7d7;border-radius:10px;padding:16px;margin-top:16px}
    .danger-zone h3{color:#c53030;font-size:.85rem;margin-bottom:8px}
    .danger-zone p{font-size:.78rem;color:#666;line-height:1.6;margin-bottom:10px}
    #suppression-form{display:none;margin-top:10px}
    #suppression-form input{width:100%;padding:9px 12px;border:1.5px solid #e53e3e;border-radius:7px;font-size:.88rem;font-family:inherit;outline:none;margin-bottom:8px}
    .nl-row{display:flex;align-items:center;gap:10px;padding:10px;background:#f8f9fa;border-radius:8px;margin-bottom:10px}
    .nl-dot{width:9px;height:9px;border-radius:50%;flex-shrink:0}
    .nl-dot.on{background:#27ae60}.nl-dot.off{background:#e74c3c}
    .nl-txt{font-size:.8rem;color:#555;flex:1}
    .logout-link{display:block;text-align:center;margin-top:20px;font-size:.75rem;color:#aaa;text-decoration:none}
    @media(max-width:600px){.grid2{grid-template-columns:1fr}.form-row-3,.form-row-2,.form-row-cp{grid-template-columns:1fr}}
  </style>
</head>
<body>
<header class="site-header">
  <img src="<?= SITE_URL ?>/medias/logo.png" style="width:36px;height:36px;object-fit:contain" alt="" onerror="this.style.display='none'">
  <h1>ça suffit ! <span>ASBL</span></h1>
  <a href="<?= SITE_URL ?>" class="back"><?= tm('back_site') ?></a>
    <div style="font-size:.72rem;margin-left:auto">
      <a href="?lang=fr" style="<?= $LANG==='fr'?'font-weight:700;color:#fff':'color:rgba(255,255,255,.5)' ?>">FR</a> |
      <a href="?lang=nl" style="<?= $LANG==='nl'?'font-weight:700;color:#fff':'color:rgba(255,255,255,.5)' ?>">NL</a>
    </div>
</header>

<div class="container">

  <div class="welcome"><?= tm("bonjour") ?> <?= htmlspecialchars($prenom) ?> 👋</div>
  <div><span class="code-badge"><?= tm('membre_depuis') ?> <?= htmlspecialchars($membre['code_membre']) ?></span></div>

  <?php if ($msg_flash === 'profil_ok'): ?>
    <div class="flash flash-ok"><?= tm('msg_profil_maj') ?></div>
  <?php elseif ($msg_flash === 'don_cree'): ?>
    <div class="flash flash-ok"><?= tm('msg_qr_genere') ?></div>
  <?php elseif ($msg_flash === 'desabonne'): ?>
    <div class="flash flash-info"><?= tm('msg_desabonne') ?></div>
  <?php elseif ($msg_flash === 'reabonne'): ?>
    <div class="flash flash-ok"><?= tm('msg_reabonne') ?></div>
  <?php elseif ($msg_flash === 'email_confirm_envoye'): ?>
    <div class="flash flash-info"><?= tm('msg_email_confirm') ?></div>
  <?php elseif ($msg_flash === 'email_invalide'): ?>
    <div class="flash flash-err"><?= tm('err_email_inv') ?></div>
  <?php elseif ($msg_flash === 'email_pris'): ?>
    <div class="flash flash-err"><?= tm('err_email_pris2') ?></div>
  <?php endif; ?>

  <?php if ($rgpd_manquant): ?>
  <div class="banner-rgpd">
    <strong><?= tm('err_rgpd_req') ?></strong>
    <p style="font-size:.8rem;color:#555;margin-top:4px"><?= tm('banner_rgpd_txt') ?></p>
  </div>
  <?php elseif ($besoin_maj): ?>
  <div class="banner-maj">
    <span style="font-size:1.3rem">📋</span>
    <div class="bm-txt"><?= tm('banner_maj_txt') ?></div>
    <button class="btn-blue" onclick="showTab('profil')" style="flex-shrink:0"><?= tm('btn_maj') ?></button>
  </div>
  <?php endif; ?>

  <div class="tabs">
    <button class="tab-btn <?= $tab_actif==='dons'?'active':'' ?>" id="tab-btn-dons" onclick="showTab('dons')"><?= tm('tab_dons') ?></button>
    <button class="tab-btn <?= $tab_actif==='profil'?'active':'' ?>" id="tab-btn-profil" onclick="showTab('profil')"><?= tm('tab_profil') ?></button>
  </div>

  <!-- ══════════ ONGLET DONS ══════════ -->
  <div class="tab-panel <?= $tab_actif==='dons'?'active':'' ?>" id="tab-dons">
    <div class="grid2">
      <div class="card">
        <h2><?= tm('generer_qr') ?></h2>
        <form method="POST">
          <div class="montant-grid">
            <?php foreach ([20,50,100,250,500] as $m): ?>
            <button type="button" class="mbtn <?= $m===50?'active':'' ?>" onclick="selectM(this,'<?= $m ?>')"><?= $m ?> €</button>
            <?php endforeach; ?>
            <button type="button" class="mbtn" onclick="selectM(this,'libre')"><?= tm('libre') ?></button>
          </div>
          <input type="number" name="montant_libre" id="input-libre" class="input-libre" placeholder="<?= tm('montant_ph') ?>" min="1" step="0.01">
          <input type="hidden" name="montant" id="input-montant" value="50">
          <button type="submit" name="creer_don" class="btn-orange"><?= tm('btn_generer_qr') ?></button>
        </form>
        <div style="margin-top:10px;font-size:.72rem;color:#aaa;line-height:1.5"><?= tm('qr_hint') ?></div>
      </div>

      <div class="card">
        <h2><?= tm('qr_actif') ?></h2>
        <?php if ($don_actif): ?>
        <div class="qr-section">
          <div class="montant-display"><?= number_format($don_actif['montant'],2,',',' ') ?> €</div>
          <img id="qrcode-img" src="" width="160" height="160" alt="QR">
          <div class="ogm-display"><?= htmlspecialchars($don_actif['ogm_don']?:$membre['ogm']) ?></div>
          <div class="iban-mini"><strong><?= htmlspecialchars($iban) ?></strong><br>BIC : <?= htmlspecialchars($bic) ?><br><?= htmlspecialchars(cfg('beneficiaire','ça suffit ! ASBL')) ?></div>
          <div style="font-size:.7rem;color:#aaa;margin-top:4px">Réf. don #<?= $don_actif['id'] ?></div>
          <button class="btn-green" onclick="genererQR(ogm_actif,montant_actif)"><?= tm('actualiser_qr') ?></button>
          <button class="btn-gray" onclick="telechargerQR()"><?= tm('telecharger_qr') ?></button>
        </div>
        <?php else: ?>
        <div style="text-align:center;padding:30px 10px;color:#aaa">
          <div style="font-size:2rem;margin-bottom:10px">💳</div>
          <div style="font-size:.85rem"><?= tm('qr_placeholder') ?></div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="grid2">
      <div class="card">
        <h2><?= tm('historique_dons') ?></h2>
        <?php if (empty($historique)): ?>
          <div style="color:#aaa;font-size:.82rem;text-align:center;padding:16px"><?= tm('aucun_don') ?></div>
        <?php else: ?>
          <?php foreach ($historique as $d): ?>
          <div class="don-row <?= ($don_actif&&$don_actif['id']==$d['id'])?'active-don':'' ?>"
               onclick="afficherDon('<?= addslashes($d['ogm_don']?:$membre['ogm']) ?>',<?= $d['montant'] ?>,<?= $d['id'] ?>)">
            <div class="don-montant"><?= number_format($d['montant'],2,',',' ') ?> €</div>
            <div class="don-ogm"><?= htmlspecialchars($d['ogm_don']?:'—') ?></div>
            <div class="don-date"><?= date('d/m/Y',strtotime($d['date_don'])) ?></div>
            <span class="badge <?= $d['statut']==='confirme'?'b-ok':'b-wait' ?>"><?= $d['statut']==='confirme'?'✓':'⏳' ?></span>
          </div>
          <?php endforeach; ?>
          <div style="font-size:.68rem;color:#aaa;text-align:center;padding:8px;font-style:italic"><?= tm('clic_qr') ?></div>
        <?php endif; ?>
      </div>

      <div style="display:flex;flex-direction:column;gap:16px">
        <div class="card">
          <h2><?= tm('mes_contributions') ?></h2>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div><div class="stat-val"><?= number_format($total,0,',',' ') ?> €</div><div class="stat-lab"><?= tm('total_confirme') ?></div></div>
            <div><div class="stat-val"><?= count($historique) ?></div><div class="stat-lab"><?= tm('dons_count') ?></div></div>
          </div>
          <?php if ($objectif > 0 && $total > 0): ?>
          <div style="background:#eee;border-radius:4px;height:5px;margin-top:12px">
            <div style="background:linear-gradient(90deg,#1673B2,#FF9900);height:5px;border-radius:4px;width:<?= min(100,round($total/$objectif*100)) ?>%"></div>
          </div>
          <div style="font-size:.68rem;color:#aaa;margin-top:3px"><?= round($total/$objectif*100) ?>% <?= tm('objectif_pct') ?></div>
          <?php endif; ?>
        </div>
        <div class="card">
          <h2><?= tm('newsletter_titre') ?></h2>
          <div class="nl-row">
            <div class="nl-dot <?= $membre['newsletter']?'on':'off' ?>"></div>
            <div class="nl-txt"><?= $membre['newsletter'] ? tm('abonne') : tm('non_abonne') ?></div>
          </div>
          <?php if ($membre['newsletter']): ?>
            <a href="dashboard.php?desabonner=1" onclick="return confirm('<?= tm('confirm_desabo') ?>')" style="display:block;text-align:center;background:#fde8e8;color:#c53030;border-radius:7px;padding:7px;font-size:.78rem;font-weight:700;text-decoration:none"><?= tm('se_desabonner') ?></a>
          <?php else: ?>
            <a href="dashboard.php?reabonner=1" style="display:block;text-align:center;background:#e8f8f0;color:#27ae60;border-radius:7px;padding:7px;font-size:.78rem;font-weight:700;text-decoration:none"><?= tm('se_reabonner') ?></a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- ══════════ ONGLET PROFIL ══════════ -->
  <div class="tab-panel <?= $tab_actif==='profil'?'active':'' ?>" id="tab-profil">
    <div class="card">
      <h2><?= tm('mes_donnees') ?></h2>
      <form method="POST">

        <div class="section-title"><?= tm('section_identite') ?></div>
        <div class="form-row-2">
          <div class="form-group"><label><?= tm('label_prenom') ?></label><input type="text" name="prenom" value="<?= htmlspecialchars($membre['prenom']??'') ?>" required></div>
          <div class="form-group"><label><?= tm('label_nom') ?></label><input type="text" name="nom" value="<?= htmlspecialchars($membre['nom']??'') ?>"></div>
        </div>
        <div class="form-group"><label><?= tm('label_tel') ?></label><input type="tel" name="telephone" value="<?= htmlspecialchars($membre['telephone']??'') ?>" placeholder="+32 ..."></div>

        <div class="section-title"><?= tm('section_adresse') ?></div>
        <div class="form-row-3">
          <div class="form-group"><label><?= tm('label_rue') ?></label><input type="text" name="rue" value="<?= htmlspecialchars($membre['rue']??'') ?>" placeholder="Nom de la rue"></div>
          <div class="form-group"><label><?= tm('label_numero') ?></label><input type="text" name="numero" value="<?= htmlspecialchars($membre['numero']??'') ?>" placeholder="12"></div>
          <div class="form-group"><label><?= tm('label_boite') ?></label><input type="text" name="boite" value="<?= htmlspecialchars($membre['boite']??'') ?>" placeholder="B3"></div>
        </div>
        <div class="form-row-cp">
          <div class="form-group"><label><?= tm('label_cp') ?></label><input type="text" name="code_postal" value="<?= htmlspecialchars($membre['code_postal']??'') ?>" placeholder="1420"></div>
          <div class="form-group"><label><?= tm('label_commune') ?></label><input type="text" name="commune" value="<?= htmlspecialchars($membre['commune']??'') ?>" placeholder="Braine-l'Alleud"></div>
        </div>

        <!-- IBAN volontaire -->
        <div class="iban-box">
          <div class="ib-title"><?= tm('iban_titre') ?></div>
          <p><?= tm('iban_txt') ?></p>
          <div class="form-group" style="margin:0">
            <label><?= tm('label_iban') ?></label>
            <input type="text" name="iban_membre"
                   value="<?= htmlspecialchars($membre['iban_membre']??'') ?>"
                   placeholder="BE41 0689 0149 6910"
                   style="font-family:monospace;letter-spacing:.05em">
          </div>
        </div>

        <!-- RGPD -->
        <div class="rgpd-box">
          <label class="rgpd-label">
            <input type="checkbox" name="rgpd_accepte" <?= ($membre['rgpd_accepte']??0)?'checked':'' ?>>
            <span><?= tm('rgpd_dash_label') ?></span>
          </label>
          <?php if (!empty($membre['rgpd_date'])): ?>
            <div style="font-size:.7rem;color:#888;margin-top:6px"><?= tm('rgpd_accepte_le', date('d/m/Y', strtotime($membre['rgpd_date']))) ?></div>
          <?php endif; ?>
        </div>

        <?php if (!empty($membre['donnees_verifiees_at'])): ?>
          <div style="font-size:.72rem;color:#aaa;margin-bottom:12px"><?= tm('derniere_verif', date('d/m/Y', strtotime($membre['donnees_verifiees_at']))) ?></div>
        <?php endif; ?>

        <button type="submit" name="update_profil" class="btn-dark"><?= tm('btn_enregistrer_donnees') ?></button>
      </form>

      <!-- Changement d'email -->
      <div class="email-change-box" style="margin-top:20px">
        <div class="ec-title"><?= tm('modifier_email') ?></div>
        <p style="font-size:.75rem;color:#555;margin-bottom:10px">
          <?= tm('email_actuel') ?> <strong><?= htmlspecialchars($membre['email']) ?></strong>
          <?php if (!empty($membre['email_nouveau'])): ?>
            <br><em style="color:#856404"><?= tm('email_en_attente') ?> <?= htmlspecialchars($membre['email_nouveau']) ?></em>
          <?php endif; ?>
        </p>
        <form method="POST">
          <input type="email" name="email_nouveau" placeholder="<?= tm('email_nouveau_ph') ?>" required>
          <button type="submit" name="changer_email" class="btn-blue" style="width:100%"><?= tm('btn_envoyer_confirm') ?></button>
        </form>
        <div style="font-size:.7rem;color:#aaa;margin-top:6px"><?= tm('email_confirm_hint') ?></div>
      </div>
    </div>

    <!-- Suppression de compte -->
    <div class="danger-zone">
      <h3><?= tm('danger_titre') ?></h3>
      <p><?= tm('danger_txt') ?></p>
      <button class="btn-gray" onclick="this.style.display='none';document.getElementById('suppression-form').style.display='block'"><?= tm('btn_demander_suppression') ?></button>
      <div id="suppression-form">
        <form method="POST" onsubmit="return confirm('Supprimer définitivement votre compte ?')">
          <div style="font-size:.8rem;font-weight:700;color:#c53030;margin-bottom:6px"><?= tm('confirm_suppression_label') ?></div>
          <input type="text" name="confirm_suppression" placeholder="<?= tm('confirm_suppression_ph') ?>" autocomplete="off">
          <button type="submit" name="supprimer_compte" class="btn-red"><?= tm('btn_supprimer') ?></button>
          <button type="button" class="btn-gray" onclick="document.getElementById('suppression-form').style.display='none'"><?= tm('btn_annuler') ?></button>
        </form>
      </div>
    </div>

    <a href="logout.php" class="logout-link"><?= tm('btn_deconnecter') ?></a>
  </div>

</div>

<script>
var bic          = "<?= addslashes($bic) ?>";
var iban_raw     = "<?= preg_replace('/\s+/', '', $iban) ?>";
var montant_actif = <?= $don_actif ? $don_actif['montant'] : 50 ?>;
var ogm_actif    = "<?= addslashes($don_actif ? ($don_actif['ogm_don'] ?: $membre['ogm']) : '') ?>";

function showTab(name) {
  ['dons','profil'].forEach(function(t) {
    document.getElementById('tab-'+t).classList.toggle('active', t===name);
    document.getElementById('tab-btn-'+t).classList.toggle('active', t===name);
  });
}

function selectM(btn, val) {
  document.querySelectorAll('.mbtn').forEach(function(b){b.classList.remove('active');});
  btn.classList.add('active');
  var libre = document.getElementById('input-libre');
  if (val === 'libre') {
    libre.style.display='block'; libre.focus();
    libre.oninput = function(){ document.getElementById('input-montant').value = this.value; };
  } else {
    libre.style.display='none';
    document.getElementById('input-montant').value = val;
  }
}

function getEPC(ogm, montant) {
  return ['BCD','002','1','SCT',bic,
    '<?= addslashes(cfg('beneficiaire','ca suffit ! ASBL')) ?>',
    iban_raw, 'EUR'+parseFloat(montant).toFixed(2), '', ogm, ''].join('\n');
}
function genererQR(ogm, montant) {
  var img = document.getElementById('qrcode-img');
  if (!img) return;
  var enc = encodeURIComponent(getEPC(ogm||ogm_actif, montant||montant_actif));
  img.src = 'https://quickchart.io/qr?text='+enc+'&size=160&margin=1&ecLevel=M';
  img.onerror = function(){ img.src='https://api.qrserver.com/v1/create-qr-code/?size=160x160&data='+enc; };
}
function telechargerQR() {
  var img = document.getElementById('qrcode-img');
  if (!img||!img.src){alert('QR non disponible');return;}
  var a=document.createElement('a'); a.download='qr-don.png'; a.href=img.src; a.click();
}
function afficherDon(ogm, montant, id) {
  ogm_actif=ogm; montant_actif=montant;
  document.querySelectorAll('.don-row').forEach(function(r){r.classList.remove('active-don');});
  event.currentTarget.classList.add('active-don');
  var md=document.querySelector('.montant-display'); if(md) md.textContent=parseFloat(montant).toLocaleString('fr-BE',{minimumFractionDigits:2})+' €';
  var od=document.querySelector('.ogm-display'); if(od) od.textContent=ogm;
  genererQR(ogm,montant);
}
window.addEventListener('load', function(){ if(ogm_actif) genererQR(ogm_actif,montant_actif); });
</script>
</body>
</html>
