<?php
// agir.php — Page d'atterrissage dédiée aux flyers / QR codes
// URL courte : casuffit.be/agir
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/lang.php';

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

// Charger le CSS custom depuis la BDD
$agir_css = '';
try {
    $stmt = $db->prepare("SELECT valeur FROM site_config WHERE cle='agir_css' LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch();
    $agir_css = $row ? ($row['valeur'] ?? '') : '';
} catch (Exception $e) {}
$obj_total  = (float)cfg('objectif_total', 20000);
$pct = $obj_total > 0 ? round($obj_actuel / $obj_total * 100) : 0;

// Charger le contenu éditable depuis la BDD (page slug=agir)
$agir_contenu = '';
try {
    $row = $db->prepare("SELECT contenu, contenu_nl FROM pages WHERE slug='agir' LIMIT 1");
    $row->execute();
    $agir_page = $row->fetch();
    if ($agir_page) {
        $agir_contenu = ($is_nl && !empty($agir_page['contenu_nl']))
            ? $agir_page['contenu_nl']
            : ($agir_page['contenu'] ?? '');
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="<?= LANG ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
<title><?= $is_nl ? 'Doe mee' : 'Agir' ?> — ça suffit ! <?= $is_nl ? 'VZW' : 'ASBL' ?></title>
<meta name="description" content="<?= htmlspecialchars(t('seo.description')) ?>">
<link rel="icon" type="image/png" href="/favicon-32.png">

<!-- Open Graph -->
<meta property="og:title" content="<?= $is_nl ? 'Sluit u aan — Stop de hinder' : 'Rejoignez-nous — Stop aux nuisances' ?>">
<meta property="og:description" content="<?= htmlspecialchars(t('seo.description')) ?>">
<meta property="og:image" content="https://www.casuffit.be/medias/og-image.jpg">
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
    <div class="logo"><img src="/medias/logo.png" alt="ça suffit !"></div>
    <h1>
      <?php if ($is_nl): ?>
        <span class="accent">ça suffit !</span><br>Sluit u aan
      <?php else: ?>
        <span class="accent">ça suffit !</span><br>Rejoignez-nous
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

    <!-- Progression -->
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

    <!-- Contenu éditable depuis admin/pages.php (slug=agir) -->
    <?php if (!empty($agir_contenu)): ?>
    <div class="why content-text">
      <?= $agir_contenu ?>
    </div>
    <?php else: ?>
    <!-- Contenu par défaut si la page BDD est vide -->
    <div class="why">
      <h2><?= $is_nl ? 'Waarom doen we het?' : 'Pourquoi nous agissons' ?></h2>
      <ul>
        <?php if ($is_nl): ?>
          <li>Tienduizenden omwonenden overvlogen, vaak buiten de norm</li>
          <li>Niet-naleving van de PRS-windvoorschriften</li>
          <li>Sluipende verslechtering van uw levenskwaliteit</li>
          <li>Référé déposé tegen de Belgische Staat — juridische strijd loopt</li>
        <?php else: ?>
          <li>Des dizaines de milliers de riverains survolés, souvent hors normes</li>
          <li>Non-respect des seuils de vent du PRS</li>
          <li>Une dégradation insidieuse de votre qualité de vie</li>
          <li>Référé déposé contre l'État belge — combat juridique en cours</li>
        <?php endif; ?>
      </ul>
    </div>
    <?php endif; ?>

    <!-- CTA 1 : devenir membre -->
    <div class="cta-block">
      <h3>👤 <?= $is_nl ? 'Word lid (gratis)' : 'Devenez membre (gratuit)' ?></h3>
      <p><?= $is_nl 
        ? 'Persoonlijke QR-code, donatiegeschiedenis, nieuwsbrief en toegang tot uw ledenruimte.'
        : 'QR code personnel, historique de vos dons, newsletter et accès à votre espace membre.' ?></p>
      <a href="/membre/inscription.php?utm_source=<?= urlencode($source) ?>&utm_campaign=<?= urlencode($campaign) ?>" class="btn btn-orange">
        ✨ <?= $is_nl ? 'Mijn ruimte aanmaken' : 'Créer mon espace' ?>
      </a>
    </div>

    <!-- CTA 2 : faire un don -->
    <div class="cta-block">
      <h3>💶 <?= $is_nl ? 'Steun de juridische strijd' : 'Soutenir le combat juridique' ?></h3>
      <p><?= $is_nl 
        ? 'Helpt u ons het vervolg van onze juridische strijd tegen de Belgische Staat te financieren.'
        : 'Aidez-nous à financer la suite de notre combat juridique contre l\'État belge.' ?></p>
      <a href="/?utm_source=<?= urlencode($source) ?>&utm_campaign=<?= urlencode($campaign) ?>#don" class="btn btn-blue">
        💶 <?= $is_nl ? 'Een gift doen' : 'Faire un don' ?>
      </a>
    </div>

    <!-- CTA 3 : outils météo -->
    <div class="cta-block">
      <h3>📱 <?= $is_nl ? 'Onze surveillance-tools' : 'Nos outils de surveillance' ?></h3>
      <p><?= $is_nl 
        ? 'Real-time wind, METAR-historiek, windroos en vluchtradar — installeerbaar als app.'
        : 'Vent en direct, historique METAR, rose des vents et radar de vols — installable comme une app.' ?></p>
      <a href="/wind.php?utm_source=<?= urlencode($source) ?>&utm_campaign=<?= urlencode($campaign) ?>" class="btn btn-outline">
        🌬 <?= $is_nl ? 'Open de tools' : 'Ouvrir les outils' ?>
      </a>
    </div>

    <!-- Partage -->
    <div class="share">
      <h3>📢 <?= $is_nl ? 'Verspreid de boodschap' : 'Faites passer le mot' ?></h3>
      <div class="share-btns">
        <?php
          $shareText = $is_nl 
            ? 'Sluit je aan bij ça suffit ! Stop de hinder van baan 01 — '
            : 'Rejoignez ça suffit ! Stop aux nuisances de la piste 01 — ';
          $shareUrl = 'https://www.casuffit.be/agir';
        ?>
        <a class="share-btn share-wa" href="https://wa.me/?text=<?= rawurlencode($shareText . $shareUrl) ?>" target="_blank">WhatsApp</a>
        <a class="share-btn share-fb" href="https://www.facebook.com/sharer/sharer.php?u=<?= rawurlencode($shareUrl) ?>" target="_blank">Facebook</a>
        <a class="share-btn share-mail" href="mailto:?subject=<?= rawurlencode($shareText) ?>&body=<?= rawurlencode($shareText . $shareUrl) ?>">Email</a>
        <button class="share-btn share-copy" onclick="navigator.clipboard.writeText('<?= $shareUrl ?>').then(()=>this.textContent='✓ Copié')"><?= $is_nl ? 'Kopiëren' : 'Copier' ?></button>
      </div>
    </div>

  </div>

  <div class="footer">
    <a href="/">← <?= $is_nl ? 'Volledige site' : 'Site complet' ?> casuffit.be</a>
  </div>

</div>

</body>
</html>
