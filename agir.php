<?php
// agir.php — Page d'atterrissage dédiée aux flyers / QR codes
// URL courte : casuffit.be/agir
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/lang.php';
session_start();
$is_logged_in_membre = !empty($_SESSION['membre_id']);

// UTM tracking — toutes les visites depuis cette page sont taggées
$source = $_GET['utm_source'] ?? 'flyer';
$campaign = $_GET['utm_campaign'] ?? '';

$is_nl = (LANG === 'nl');

// Compteur de visites (analytics simple en BDD)
try {
    $db = getDB();
    $db->prepare("INSERT INTO landing_stats (source, campaign, lang, visited_at, ip_hash) VALUES (?,?,?,NOW(),?)")
       ->execute([$source, $campaign, LANG, hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '')]);
} catch (Exception $e) { /* table inexistante → silencieux */ }

// Charger depuis landing_pages (table dédiée, indépendante de pages et site_config)
$agir_css = ''; $agir_contenu = '';
try {
    $stmt = $db->prepare("SELECT contenu, contenu_nl, css FROM landing_pages WHERE slug='agir' LIMIT 1");
    $stmt->execute();
    $lp = $stmt->fetch();
    if ($lp) {
        $agir_css     = $lp['css'] ?? '';
        $agir_contenu = ($is_nl && !empty($lp['contenu_nl'])) ? $lp['contenu_nl'] : ($lp['contenu'] ?? '');
    }
} catch (Exception $e) {}
$obj_total      = (float)cfg('objectif_total', 20000);
$date_lancement = cfg('date_lancement', '2026-05-25');
$montant_initial = (float)cfg('montant_initial', 0);
try {
    $q = $db->prepare("SELECT COALESCE(SUM(montant),0) FROM member_dons WHERE statut='confirme' AND date_don >= ?");
    $q->execute([$date_lancement]);
    $obj_actuel = $montant_initial + (float)$q->fetchColumn();
} catch (Exception $e) { $obj_actuel = $montant_initial; }
$pct = $obj_total > 0 ? min(100, round($obj_actuel / $obj_total * 100)) : 0;
?>
<!DOCTYPE html>
<html lang="<?= LANG ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
<title><?= $is_nl ? 'Doe mee' : 'Agir' ?> — Ça suffit !</title>
<meta name="description" content="<?= htmlspecialchars(t('seo.description')) ?>">
<link rel="icon" type="image/png" href="/favicon-32.png">

<!-- Open Graph -->
<meta property="og:title" content="<?= $is_nl ? 'Sluit u aan — Stop de hinder' : 'Rejoignez-nous — Stop aux nuisances' ?>">
<meta property="og:description" content="<?= htmlspecialchars(t('seo.description')) ?>">
<meta property="og:image" content="https://www.casuffit.be/assets/img/og-image.jpg">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:type" content="website">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:image" content="https://www.casuffit.be/assets/img/og-image.jpg">
<meta property="og:url" content="https://www.casuffit.be/agir">

<style>
*,*::before,*::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; line-height: 1.55; color: #333; background: linear-gradient(180deg, #1673B2 0%, #0e5a96 100%); min-height: 100vh; }
.hero { padding: 28px 20px 24px; text-align: center; color: #fff; }
.logo { width: 100px; height: 100px; margin: 0 auto 12px; background: rgba(255,255,255,.95); border-radius: 50%; display: flex; align-items: center; justify-content: center; padding: 8px; }
.logo img { width: 100%; height: 100%; object-fit: contain; }
.hero h1 { font-size: 1.8rem; font-weight: 800; margin-bottom: 8px; line-height: 1.2; }
.hero h1 .accent { color: #FF9900; }
.hero .tagline { font-size: 1rem; opacity: .95; margin-bottom: 14px; }
.urgence-banner { display: inline-block; background: #FF9900; color: #fff; padding: 6px 16px; border-radius: 20px; font-weight: 700; font-size: .85rem; }

.content { background: #f5f7fa; padding: 28px 20px; border-radius: 16px 16px 0 0; margin-top: 12px; }

.progress-card { background: #fff; border: 2px solid #FF9900; border-radius: 12px; padding: 16px 18px; margin-bottom: 22px; }
.progress-title { font-size: .75rem; font-weight: 700; color: #FF9900; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 6px; }
.progress-amounts { display: flex; justify-content: space-between; align-items: baseline; font-weight: 700; color: #1673B2; margin-bottom: 8px; }
.progress-amounts .obj { color: #555; font-weight: 600; font-size: .85rem; }
.progress-bar { height: 10px; background: #e8eef3; border-radius: 5px; overflow: hidden; }
.progress-fill { height: 100%; background: linear-gradient(90deg, #FF9900, #FFB84D); border-radius: 5px; transition: width .8s; }

.why { margin-bottom: 24px; }
.why h2 { color: #1673B2; font-size: 1.2rem; margin-bottom: 12px; font-weight: 700; }
.why ul { list-style: none; }
.why li { padding: 8px 0; padding-left: 26px; position: relative; font-size: .95rem; }
.why li::before { content: '✓'; position: absolute; left: 0; top: 8px; color: #FF9900; font-weight: 700; font-size: 1.1rem; }

.cta-block { background: #fff; border-radius: 12px; padding: 22px 20px; margin-bottom: 14px; box-shadow: 0 4px 16px rgba(0,0,0,.06); }
.cta-block h3 { color: #1673B2; font-size: 1.05rem; font-weight: 700; margin-bottom: 6px; }
.cta-block p { font-size: .88rem; color: #555; margin-bottom: 14px; }
.btn { display: block; width: 100%; padding: 14px; border-radius: 10px; text-decoration: none; text-align: center; font-weight: 700; font-size: 1rem; transition: transform .15s, box-shadow .15s; }
.btn:active { transform: scale(.98); }
.btn-orange { background: #FF9900; color: #fff; box-shadow: 0 4px 14px rgba(255,153,0,.35); }
.btn-blue { background: #1673B2; color: #fff; box-shadow: 0 4px 14px rgba(22,115,178,.35); }
.btn-outline { background: #fff; color: #1673B2; border: 2px solid #1673B2; }

.divider { text-align: center; color: #aaa; font-size: .8rem; margin: 16px 0; }

.share { margin-top: 24px; text-align: center; }
.share h3 { color: #1673B2; font-size: 1rem; margin-bottom: 12px; }
.share-btns { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; }
.share-btn { padding: 10px 16px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: .85rem; color: #fff; border: none; cursor: pointer; }
.share-wa { background: #25D366; }
.share-fb { background: #1877F2; }
.share-mail { background: #555; }
.share-copy { background: #FF9900; }

.lang-switch { position: absolute; top: 14px; right: 16px; }
.lang-switch a { color: #fff; text-decoration: none; padding: 4px 10px; border: 1px solid rgba(255,255,255,.4); border-radius: 4px; font-size: .8rem; font-weight: 700; }
.lang-switch a.active { background: rgba(255,255,255,.2); }

.footer { text-align: center; padding: 24px 20px; color: #aaa; font-size: .78rem; }
.footer a { color: #1673B2; text-decoration: none; }

@media (min-width: 600px) {
  .hero h1 { font-size: 2.2rem; }
  .container { max-width: 540px; margin: 0 auto; }
}
</style>
<?php if (!empty($agir_css)): ?>
<style id="agir-custom-css"><?= $agir_css ?></style>
<?php endif; ?>
</head>
<body>

<div class="lang-switch">
  <a href="?lang=fr" class="<?= !$is_nl ? 'active' : '' ?>">FR</a>
  <a href="?lang=nl" class="<?= $is_nl ? 'active' : '' ?>">NL</a>
</div>

<div class="container">

  <div class="hero">
    <div class="logo"><img src="/medias/logo.png" alt="Ça suffit !"></div>
    <h1>
      <?php if ($is_nl): ?>
        <span class="accent">Ça suffit !</span><br>Sluit u aan
      <?php else: ?>
        <span class="accent">Ça suffit !</span><br>Rejoignez-nous
      <?php endif; ?>
    </h1>
    <p class="tagline">
      <?= $is_nl
        ? 'Mobilisatie tegen de hinder van baan 01' 
        : 'Mobilisation contre les nuisances de la piste 01' ?>
    </p>
    <div class="urgence-banner">🤝 <?= htmlspecialchars($urgence) ?></div>
  </div>

  <div class="content">

    <!-- 1. PROGRESSION DES DONS -->
    <div class="progress-card">
      <div class="progress-title">
        <?= $is_nl ? '🎯 Doelstelling — Juridische strijd' : '🎯 Objectif — Combat juridique' ?>
      </div>
      <div class="progress-amounts">
        <span><?= number_format($obj_actuel, 0, ',', ' ') ?> €</span>
        <span class="obj">/ <?= number_format($obj_total, 0, ',', ' ') ?> €</span>
      </div>
      <div class="progress-bar"><div class="progress-fill" style="width:<?= $pct ?>%"></div></div>
    </div>

    <!-- 2. POURQUOI / CONTENU ÉDITABLE -->
    <?php if (!empty($agir_contenu)): ?>
    <div class="content-editable-zone"><?= $agir_contenu ?></div>
    <?php endif; ?>

    <!-- 3. ESPACE MEMBRE -->
    <div class="cta-block">
      <h3>👤 <?= $is_nl ? 'Ledenruimte' : 'Espace membre' ?></h3>
      <?php if ($is_logged_in_membre): ?>
        <p><?= $is_nl ? 'U bent verbonden.' : 'Vous êtes connecté.' ?></p>
        <a href="/membre/dashboard.php" class="btn btn-blue">→ <?= $is_nl ? 'Mijn ledenruimte' : 'Mon espace membre' ?></a>
      <?php else: ?>
        <p><?= $is_nl ? 'Volg uw lidmaatschap en giften op. Al een account?' : 'Suivez votre adhésion et vos dons. Déjà un compte ?' ?></p>
        <a href="/membre/login.php" class="btn btn-blue" style="margin-bottom:8px">→ <?= $is_nl ? 'Inloggen' : 'Me connecter' ?></a>
        <a href="/membre/inscription.php" class="btn btn-outline" style="font-size:.88rem;padding:11px 14px">✨ <?= $is_nl ? 'Mijn gratis ledenruimte aanmaken' : 'Créer mon espace membre gratuit' ?></a>
      <?php endif; ?>
    </div>

    <!-- 4. PORTER PLAINTE -->
    <div class="cta-block">
      <h3>⚠ <?= $is_nl ? 'Klacht indienen' : 'Porter plainte' ?></h3>
      <p><?= $is_nl ? 'Stelt u <strong>nu</strong> een abnormaal gebruik van de startbaan vast?' : 'Vous constatez <strong>en ce moment</strong> un usage anormal de la piste ?' ?></p>
      <a href="/plainte.php<?= $is_nl ? '?lang=nl' : '' ?>" class="btn btn-orange" style="margin-bottom:8px">⚠ <?= $is_nl ? 'Klacht indienen — abnormaal gebruik' : 'Porter plainte — usage anormal' ?></a>
      <a href="/wind.php" class="btn btn-outline" style="font-size:.88rem;padding:11px 14px">🕐 <?= $is_nl ? 'Overlast in het verleden → Windgeschiedenis' : 'Nuisance passée → Historique du vent' ?></a>
    </div>

    <!-- 5. SOUTENIR / DON -->
    <div class="cta-block">
      <h3>💛 <?= $is_nl ? 'De juridische strijd steunen' : 'Soutenir le combat juridique' ?></h3>
      <p><?= $is_nl ? 'Help ons de juridische strijd tegen de Belgische Staat te financieren.' : 'Aidez-nous à financer la suite de notre combat juridique contre l\'État belge.' ?></p>
      <a href="/" class="btn btn-orange">💳 <?= $is_nl ? 'Een gift doen' : 'Faire un don' ?></a>
    </div>

    <!-- 6. NOS OUTILS -->
    <div class="cta-block">
      <h3>📡 <?= $is_nl ? 'Onze tools' : 'Nos outils' ?></h3>
      <p><?= $is_nl ? 'Weer, vluchten, windgeschiedenis en PRS-analyse in realtime.' : 'Météo, vols, historique du vent et analyse PRS en temps réel.' ?></p>
      <a href="/wind.php" class="btn btn-blue">→ <?= $is_nl ? 'Toegang tot de tools' : 'Accéder aux outils' ?></a>
    </div>

  </div>

  <div class="footer">
    <a href="/">← <?= $is_nl ? 'Volledige site' : 'Site complet' ?> casuffit.be</a>
  </div>

</div>

</body>
</html>
