<?php
// admin/site_config.php — Paramètres généraux du site
require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();
require_once __DIR__ . '/../includes/csrf.php';
$db = getDB();

$msg = isset($_GET['msg']) ? $_GET['msg'] : '';

// Charger widgets pour la zone header
$all_widgets_sc    = array();
$header_widgets_sc = array();
try {
    $all_widgets_sc = $db->query("SELECT * FROM widgets WHERE actif=1 ORDER BY id")->fetchAll();
    $rows_h = $db->query("SELECT widget_slug, ordre FROM page_widgets WHERE page_slug='__header__' ORDER BY ordre")->fetchAll();
    foreach ($rows_h as $r) $header_widgets_sc[$r['widget_slug']] = $r['ordre'];
} catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_verify();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_header_widgets'])) {
    // Sauvegarder les widgets de la zone header
    $db->prepare("DELETE FROM page_widgets WHERE page_slug='__header__'")->execute();
    $header_w = isset($_POST['header_widgets']) ? $_POST['header_widgets'] : array();
    $ordre = 0;
    foreach ($header_w as $wslug) {
        if (trim($wslug) === '') continue;
        $db->prepare("INSERT INTO page_widgets (page_slug, widget_slug, ordre) VALUES ('__header__',?,?)")
           ->execute(array($wslug, $ordre));
        $ordre++;
    }
    header('Location: site_config.php?msg='.urlencode('Zone header mise à jour.')); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = array(
        // Général
        'site_nom', 'site_slogan', 'site_email', 'site_facebook', 'urgence_texte',
        // Réseaux sociaux
        'facebook_url', 'instagram_url', 'whatsapp_url',
        // Dons
        'iban', 'bic', 'beneficiaire', 'don_texte',
        // Montants
        'montant_initial', 'montant_recolte', 'montant_objectif',
        // Annonce
        'annonce_active', 'annonce_titre', 'annonce_texte',
        // Analytics
        'ga_id',
        // Plainte
        'plainte_destinataires',
        // Alertes
        'alerte_membre_email', 'alerte_don_email',
    );
    foreach ($fields as $cle) {
        $val = isset($_POST[$cle]) ? trim($_POST[$cle]) : '';
        if ($cle === 'annonce_active') { $val = isset($_POST['annonce_active']) ? '1' : '0'; }
        if (in_array($cle, array('montant_recolte','montant_objectif'))) {
            $val = strval(floatval(str_replace(',','.',$val)));
        }
        $db->prepare("INSERT INTO site_config (cle,valeur) VALUES (?,?) ON DUPLICATE KEY UPDATE valeur=?")
           ->execute(array($cle, $val, $val));
    }
    // Champs traduisibles en néerlandais (colonne valeur_nl si elle existe)
    $hasNlCol = false;
    try { $chk = $db->query("SHOW COLUMNS FROM site_config LIKE 'valeur_nl'")->fetch(); $hasNlCol = !empty($chk); }
    catch (Exception $e) { $hasNlCol = false; }
    if ($hasNlCol) {
        $fields_nl = array('urgence_texte', 'annonce_titre', 'annonce_texte', 'site_slogan', 'don_texte');
        foreach ($fields_nl as $cle) {
            $key_nl = $cle . '_nl';
            if (isset($_POST[$key_nl])) {
                $val_nl = trim($_POST[$key_nl]);
                $db->prepare("INSERT INTO site_config (cle,valeur,valeur_nl) VALUES (?,'',?) ON DUPLICATE KEY UPDATE valeur_nl=?")
                   ->execute(array($cle, $val_nl, $val_nl));
            }
        }
    }
    // Logo upload
    if (!empty($_FILES['logo']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, array('png','jpg','jpeg','gif','webp'))) {
            $dest = __DIR__ . '/../medias/logo.png';
            // Convertir en PNG si nécessaire
            if ($ext === 'png') {
                move_uploaded_file($_FILES['logo']['tmp_name'], $dest);
            } else {
                $img = imagecreatefromstring(file_get_contents($_FILES['logo']['tmp_name']));
                imagepng($img, $dest);
                imagedestroy($img);
            }
            chmod($dest, 0644);
        }
    }
    header('Location: site_config.php?msg='.urlencode('✅ Paramètres sauvegardés.')); exit;
}

$msg = isset($_GET['msg']) ? $_GET['msg'] : '';

// Lire toute la config (valeur_nl peut ne pas exister encore)
$c = array(); $c_nl = array();
try {
    $rows = $db->query("SELECT cle,valeur,valeur_nl FROM site_config")->fetchAll();
    foreach ($rows as $r) { $c[$r['cle']] = $r['valeur']; $c_nl[$r['cle']] = $r['valeur_nl'] ?? ''; }
} catch (Exception $e) {
    // Colonne valeur_nl absente — lecture sans traduction
    $rows = $db->query("SELECT cle,valeur FROM site_config")->fetchAll();
    foreach ($rows as $r) { $c[$r['cle']] = $r['valeur']; $c_nl[$r['cle']] = ''; }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<?php include __DIR__ . '/../includes/admin_pwa_head.php'; ?>
  <title>Paramètres — Admin Ça suffit !</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333}
    <?php include __DIR__ . '/../includes/admin_sidebar_css.php'; ?>
    .main{margin-left:240px;padding:28px;max-width:800px}
    .page-title{font-size:1.3rem;font-weight:800;color:#0e3d6b;margin-bottom:20px}
    .card{background:#fff;border-radius:12px;padding:24px;box-shadow:0 2px 10px rgba(0,0,0,0.06);margin-bottom:20px}
    .card h3{font-size:.9rem;font-weight:700;color:#0e3d6b;margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid #eee}
    label{display:block;font-size:.78rem;font-weight:600;color:#555;margin-bottom:5px;margin-top:14px}
    input[type=text],input[type=email],input[type=url],input[type=number],textarea{width:100%;padding:9px 12px;border:1.5px solid #dde4ed;border-radius:7px;font-size:.88rem;color:#333;outline:none;font-family:inherit;transition:border .2s}
    input:focus,textarea:focus{border-color:#1673B2}
    .form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
    .check-inline{display:flex;align-items:center;gap:8px;margin-top:10px}
    .check-inline input{accent-color:#1673B2;width:16px;height:16px}
    .btn-primary{background:#1673B2;color:#fff;border:none;padding:12px 24px;border-radius:8px;font-size:.88rem;font-weight:700;cursor:pointer;font-family:inherit}
    .flash-ok{background:#e8f8f0;color:#276749;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:.85rem;border-left:3px solid #48bb78}
    .hint{font-size:.72rem;color:#aaa;margin-top:3px}
    .logo-preview{width:64px;height:64px;border-radius:50%;object-fit:contain;border:2px solid #dde;margin-top:8px}
    /* Preview barre progression */
    .prog-preview{background:#f8f9fa;border-radius:8px;padding:14px;margin-top:12px;border:1px solid #eee}
    .bar-wrap{background:#e9ecef;border-radius:4px;height:10px}
    .bar-fill{background:linear-gradient(90deg,#1673B2,#FF9900);height:10px;border-radius:4px;transition:width .5s}
    .bar-stats{display:flex;justify-content:space-between;font-size:.72rem;color:#888;margin-top:4px}
  
  @media (max-width: 768px) {
    .main { margin-left: 0 !important; padding: 16px !important; padding-top: 68px !important; }
    .grid2, .cards-grid { grid-template-columns: 1fr !important; }
    table { font-size: .75rem; }
    table th, table td { padding: 6px 8px !important; }
    .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .form-row { grid-template-columns: 1fr !important; }
    .btn, button[type=submit], .btn-save { width: 100%; justify-content: center; }
  }
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
  <div class="page-title">⚙ Paramètres du site</div>
  <?php if ($msg): ?><div class="flash-ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

  <form method="POST" enctype="multipart/form-data"><?= csrf_field() ?>

    <!-- Général -->
    <div class="card">
      <h3>🌐 Paramètres généraux</h3>
      <label>Nom du site</label>
      <input type="text" name="site_nom" value="<?= htmlspecialchars($c['site_nom'] ?? 'Ça suffit ! ASBL') ?>">
      <label>Slogan</label>
      <input type="text" name="site_slogan" value="<?= htmlspecialchars($c['site_slogan'] ?? '') ?>">
      <div class="form-row">
        <div>
          <label>Email de contact</label>
          <input type="email" name="site_email" value="<?= htmlspecialchars($c['site_email'] ?? '') ?>">
        </div>
        <div>
          <label>URL Facebook</label>
          <input type="url" name="site_facebook" value="<?= htmlspecialchars($c['site_facebook'] ?? '') ?>">
        </div>
      </div>
      <label>Texte du bandeau urgence (laisser vide pour masquer)</label>
      <input type="text" name="urgence_texte" value="<?= htmlspecialchars($c['urgence_texte'] ?? '') ?>" placeholder="Mobilisation nécessaire — ... (vide = masqué)">
      <div class="hint">Bandeau orange en haut du site. Laissez vide pour le masquer complètement.</div>
      <label style="color:#1673B2">🇳🇱 Bandeau urgence (néerlandais)</label>
      <input type="text" name="urgence_texte_nl" value="<?= htmlspecialchars($c_nl['urgence_texte'] ?? '') ?>" placeholder="Vertaling NL...">
      <div class="hint">Affiché sur la version /nl du site. Si vide, le texte FR est utilisé.</div>

      <label>Logo du site</label>
      <img src="../medias/logo.png" class="logo-preview" alt="Logo actuel" onerror="this.style.display='none'">
      <div class="hint">Logo actuel : <code>medias/logo.png</code>. Uploadez un nouveau fichier pour le remplacer.</div>
      <input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp" style="margin-top:8px;font-size:.82rem">
      <div class="hint">Le fichier sera sauvegardé comme <code>medias/logo.png</code>.</div>
    </div>

    <!-- Annonce -->
    <div class="card">
      <h3>📢 Annonce en haut de site</h3>
      <div class="check-inline">
        <input type="checkbox" name="annonce_active" id="annonce_active" <?= (!isset($c['annonce_active']) || $c['annonce_active']==='1') ? 'checked' : '' ?>>
        <label for="annonce_active" style="margin-top:0">Afficher l'annonce</label>
      </div>
      <label>Titre de l'annonce</label>
      <input type="text" name="annonce_titre" value="<?= htmlspecialchars($c['annonce_titre'] ?? '') ?>">
      <label>Texte de l'annonce</label>
      <textarea name="annonce_texte" rows="2"><?= htmlspecialchars($c['annonce_texte'] ?? '') ?></textarea>
      <label style="color:#1673B2;margin-top:12px">🇳🇱 Titre de l'annonce (néerlandais)</label>
      <input type="text" name="annonce_titre_nl" value="<?= htmlspecialchars($c_nl['annonce_titre'] ?? '') ?>" placeholder="Titel NL...">
      <label style="color:#1673B2">🇳🇱 Texte de l'annonce (néerlandais)</label>
      <textarea name="annonce_texte_nl" rows="2" placeholder="Tekst NL..."><?= htmlspecialchars($c_nl['annonce_texte'] ?? '') ?></textarea>
      <div class="hint">Si vide, le texte FR est utilisé sur la version /nl.</div>
    </div>

    <!-- Réseaux sociaux -->
    <div class="card">
      <h3>📱 Réseaux sociaux</h3>
      <label>URL page Facebook</label>
      <input type="text" name="facebook_url" value="<?= htmlspecialchars($c['facebook_url'] ?? 'https://www.facebook.com/piste01casuffit') ?>" placeholder="https://www.facebook.com/votrepage">
      <label>URL Instagram</label>
      <input type="text" name="instagram_url" value="<?= htmlspecialchars($c['instagram_url'] ?? '') ?>" placeholder="https://www.instagram.com/votrecompte">
      <label>Lien WhatsApp</label>
      <input type="text" name="whatsapp_url" value="<?= htmlspecialchars($c['whatsapp_url'] ?? '') ?>" placeholder="https://wa.me/32XXXXXXXXX">
      <p style="font-size:.72rem;color:#999;margin-top:4px">Laisser vide pour masquer le bouton correspondant</p>
    </div>

    <!-- Analytics -->
    <div class="card">
      <h3>📊 Google Analytics</h3>
      <label>ID de mesure Google Analytics 4</label>
      <input type="text" name="ga_id"
             value="<?= htmlspecialchars($c['ga_id'] ?? '') ?>"
             placeholder="G-XXXXXXXXXX"
             style="font-family:monospace;letter-spacing:.05em">
      <p style="font-size:.72rem;color:#999;margin-top:6px">
        Format : <code>G-XXXXXXXXXX</code> — Laisser vide pour désactiver le tracking.
        Modifiable ici lors du changement de domaine.
      </p>
    </div>

    <!-- Destinataires des plaintes -->
    <div class="card">
      <h3>✉ Destinataires des plaintes</h3>
      <label>Adresses email qui recevront les plaintes (outil météo)</label>
      <textarea name="plainte_destinataires" rows="4"
                placeholder="airportmediation@mobilit.fgov.be&#10;bourgmestre@macommune.be&#10;contact@autreasso.be"><?= htmlspecialchars($c['plainte_destinataires'] ?? 'airportmediation@mobilit.fgov.be') ?></textarea>
      <p style="font-size:.72rem;color:#999;margin-top:6px">
        Une adresse par ligne (ou séparées par des virgules). Toutes ces adresses seront pré-remplies
        dans l'email de plainte généré par l'outil « Conditions de vent ». Vous pouvez en ajouter ou en retirer à tout moment.
      </p>
    </div>

    <!-- Alerte nouvelle adhésion -->
    <div class="card">
      <h3>🔔 Alerte nouvelle adhésion</h3>
      <label>Email qui reçoit une alerte à chaque nouveau membre</label>
      <input type="email" name="alerte_membre_email"
             value="<?= htmlspecialchars($c['alerte_membre_email'] ?? '') ?>"
             placeholder="vous@exemple.be">
      <p style="font-size:.72rem;color:#999;margin-top:6px">
        Dès qu'une personne s'inscrit comme membre, un email récapitulatif est envoyé à cette adresse.
        Laissez vide pour utiliser l'email du site (<?= htmlspecialchars($c['site_email'] ?? 'non défini') ?>), ou pour désactiver si aucun n'est défini.
      </p>
      <label style="margin-top:14px">Email qui reçoit une alerte à chaque don déclaré</label>
      <input type="email" name="alerte_don_email"
             value="<?= htmlspecialchars($c['alerte_don_email'] ?? '') ?>"
             placeholder="vous@exemple.be">
      <p style="font-size:.72rem;color:#999;margin-top:6px">
        Quand un membre déclare un don depuis son espace, un email récapitulatif est envoyé ici.
        Laissez vide pour utiliser l'adresse d'alerte des membres ci-dessus (ou l'email du site).
      </p>
    </div>

    <!-- Dons -->
    <div class="card">
      <h3>💶 Informations bancaires &amp; dons</h3>
      <div class="form-row">
        <div>
          <label>IBAN</label>
          <input type="text" name="iban" value="<?= htmlspecialchars($c['iban'] ?? 'BE41 0689 0149 6910') ?>">
        </div>
        <div>
          <label>BIC</label>
          <input type="text" name="bic" value="<?= htmlspecialchars($c['bic'] ?? 'GKCCBEBB') ?>">
        </div>
      </div>
      <label>Bénéficiaire</label>
      <input type="text" name="beneficiaire" value="<?= htmlspecialchars($c['beneficiaire'] ?? 'Ça suffit ! ASBL') ?>">

      <label>Texte sous la barre de progression</label>
      <input type="text" name="don_texte" value="<?= htmlspecialchars($c['don_texte'] ?? '') ?>" placeholder="Combat juridique — Suite de nos actions">

      <div class="form-row">
        <div>
          <label>Montant initial (€) <small style="color:#888;font-weight:400">dons reçus avant le site</small></label>
          <input type="number" name="montant_initial" value="<?= htmlspecialchars(cfg('montant_initial','0')) ?>" min="0" step="1">
          <label>Montant récolté (€) <small style="color:#888;font-weight:400">calculé automatiquement depuis les dons confirmés</small></label>
          <input type="number" name="montant_recolte" value="<?= intval($c['montant_recolte'] ?? 0) ?>" min="0" inputmode="numeric">
        </div>
        <div>
          <label>Objectif (€)</label>
          <input type="number" name="montant_objectif" value="<?= intval($c['montant_objectif'] ?? 15000) ?>" min="0" inputmode="numeric">
        </div>
      </div>

      <!-- Aperçu barre -->
      <?php
        $r = intval($c['montant_recolte'] ?? 0);
        $o = intval($c['montant_objectif'] ?? 15000);
        $p = $o > 0 ? min(100, round($r/$o*100)) : 0;
      ?>
      <div class="prog-preview">
        <div style="font-size:.72rem;color:#888;margin-bottom:6px">Aperçu barre de progression</div>
        <div class="bar-wrap"><div class="bar-fill" style="width:<?= $p ?>%"></div></div>
        <div class="bar-stats"><span><?= number_format($r,0,',',' ') ?> €</span><span><?= $p ?>%</span><span><?= number_format($o,0,',',' ') ?> €</span></div>
      </div>
    </div>

    <button type="submit" class="btn-primary"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Sauvegarder tous les paramètres</button>
  </form>

  <!-- Zone header -->
  <div class="card" style="margin-top:18px">
    <h3>🔝 Zone header — Widgets globaux</h3>
    <p style="font-size:.78rem;color:#666;margin-bottom:14px">
      Ces widgets s'affichent sur <strong>toutes les pages</strong>, juste sous le header, avant les tabs.
    </p>
    <form method="POST"><?= csrf_field() ?>
      <?php foreach ($all_widgets_sc as $w): ?>
      <label style="display:flex;align-items:center;gap:10px;padding:9px 12px;border:1.5px solid <?= isset($header_widgets_sc[$w['slug']]) ? '#1673B2' : '#e0e8f0' ?>;border-radius:7px;margin-bottom:6px;cursor:pointer;background:<?= isset($header_widgets_sc[$w['slug']]) ? '#e6f1fb' : '#fff' ?>">
        <input type="checkbox"
               name="header_widgets[]"
               value="<?= $w['slug'] ?>"
               <?= isset($header_widgets_sc[$w['slug']]) ? 'checked' : '' ?>
               style="accent-color:#1673B2">
        <span style="font-size:1.1rem"><?= htmlspecialchars($w['icone'] ?: '🧩') ?></span>
        <div>
          <div style="font-size:.82rem;font-weight:700;color:#0e3d6b"><?= htmlspecialchars($w['titre']) ?></div>
          <div style="font-size:.68rem;color:#888"><?= htmlspecialchars($w['description']) ?></div>
        </div>
      </label>
      <?php endforeach; ?>
      <?php if (empty($all_widgets_sc)): ?>
        <p style="color:#aaa;font-size:.82rem">Aucun widget disponible. Créez-en depuis <a href="widgets.php">Widgets</a>.</p>
      <?php endif; ?>
      <button type="submit" name="save_header_widgets" class="btn btn-primary" style="margin-top:10px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Sauvegarder la zone header
      </button>
    </form>
  </div>

</div><!-- /main -->
</body>
</html>
