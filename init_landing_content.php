<?php
require_once __DIR__ . '/config.php';
$db = getDB();

$contenu_fr = <<<HTML
<div class="why">
  <h2>Pourquoi nous agissons</h2>
  <ul>
    <li>Des dizaines de milliers de riverains survolés, souvent hors normes</li>
    <li>Non-respect des seuils de vent du PRS</li>
    <li>Une dégradation insidieuse de votre qualité de vie</li>
    <li>Référé déposé contre l'État belge — combat juridique en cours</li>
  </ul>
</div>

<div class="cta-block">
  <h3>👤 Devenez membre (gratuit)</h3>
  <p>QR code personnel, historique de vos dons, newsletter et accès à votre espace membre.</p>
  <a href="/membre/inscription.php" class="btn btn-orange" style="display:block;padding:14px;border-radius:10px;background:#FF9900;color:#fff;text-decoration:none;font-weight:700;text-align:center">✨ Créer mon espace</a>
</div>

<div class="cta-block">
  <h3>💶 Soutenir le combat juridique</h3>
  <p>Aidez-nous à financer la suite de notre combat juridique contre l'État belge.</p>
  <a href="/#don" class="btn btn-blue" style="display:block;padding:14px;border-radius:10px;background:#1673B2;color:#fff;text-decoration:none;font-weight:700;text-align:center">💶 Faire un don</a>
</div>

<div class="cta-block">
  <h3>📱 Nos outils de surveillance</h3>
  <p>Vent en direct, historique METAR, rose des vents et radar de vols — installable comme une app.</p>
  <a href="/wind.php" class="btn btn-outline" style="display:block;padding:14px;border-radius:10px;background:#fff;color:#1673B2;border:2px solid #1673B2;text-decoration:none;font-weight:700;text-align:center">🌬 Ouvrir les outils</a>
</div>

<div class="share">
  <h3>📢 Faites passer le mot</h3>
  <div class="share-btns">
    <a class="share-btn share-wa" href="https://wa.me/?text=Rejoignez%20%C3%A7a%20suffit%20!%20https%3A%2F%2Fwww.casuffit.be%2Fagir" target="_blank" style="padding:10px 16px;border-radius:8px;text-decoration:none;font-weight:600;font-size:.85rem;color:#fff;background:#25D366">WhatsApp</a>
    <a class="share-btn share-fb" href="https://www.facebook.com/sharer/sharer.php?u=https%3A%2F%2Fwww.casuffit.be%2Fagir" target="_blank" style="padding:10px 16px;border-radius:8px;text-decoration:none;font-weight:600;font-size:.85rem;color:#fff;background:#1877F2">Facebook</a>
    <a class="share-btn share-mail" href="mailto:?subject=Ca%20suffit%20!&body=https%3A%2F%2Fwww.casuffit.be%2Fagir" style="padding:10px 16px;border-radius:8px;text-decoration:none;font-weight:600;font-size:.85rem;color:#fff;background:#555">Email</a>
  </div>
</div>
HTML;

$contenu_nl = <<<HTML
<div class="why">
  <h2>Waarom doen we het?</h2>
  <ul>
    <li>Tienduizenden omwonenden overvlogen, vaak buiten de norm</li>
    <li>Niet-naleving van de PRS-windvoorschriften</li>
    <li>Sluipende verslechtering van uw levenskwaliteit</li>
    <li>Kort geding ingediend tegen de Belgische Staat — juridische strijd loopt</li>
  </ul>
</div>

<div class="cta-block">
  <h3>👤 Word lid (gratis)</h3>
  <p>Persoonlijke QR-code, donatiegeschiedenis, nieuwsbrief en toegang tot uw ledenruimte.</p>
  <a href="/membre/inscription.php" style="display:block;padding:14px;border-radius:10px;background:#FF9900;color:#fff;text-decoration:none;font-weight:700;text-align:center">✨ Mijn ruimte aanmaken</a>
</div>

<div class="cta-block">
  <h3>💶 Steun de juridische strijd</h3>
  <p>Helpt u ons het vervolg van onze juridische strijd tegen de Belgische Staat te financieren.</p>
  <a href="/#don" style="display:block;padding:14px;border-radius:10px;background:#1673B2;color:#fff;text-decoration:none;font-weight:700;text-align:center">💶 Een gift doen</a>
</div>

<div class="cta-block">
  <h3>📱 Onze surveillance-tools</h3>
  <p>Real-time wind, METAR-historiek, windroos en vluchtradar — installeerbaar als app.</p>
  <a href="/wind.php" style="display:block;padding:14px;border-radius:10px;background:#fff;color:#1673B2;border:2px solid #1673B2;text-decoration:none;font-weight:700;text-align:center">🌬 Open de tools</a>
</div>

<div class="share">
  <h3>📢 Verspreid de boodschap</h3>
  <div class="share-btns">
    <a class="share-btn share-wa" href="https://wa.me/?text=Sluit%20je%20aan%20bij%20%C3%A7a%20suffit%20!%20https%3A%2F%2Fwww.casuffit.be%2Fagir" target="_blank" style="padding:10px 16px;border-radius:8px;text-decoration:none;font-weight:600;font-size:.85rem;color:#fff;background:#25D366">WhatsApp</a>
    <a class="share-btn share-fb" href="https://www.facebook.com/sharer/sharer.php?u=https%3A%2F%2Fwww.casuffit.be%2Fagir" target="_blank" style="padding:10px 16px;border-radius:8px;text-decoration:none;font-weight:600;font-size:.85rem;color:#fff;background:#1877F2">Facebook</a>
    <a class="share-btn share-mail" href="mailto:?subject=Ca%20suffit%20!&body=https%3A%2F%2Fwww.casuffit.be%2Fagir" style="padding:10px 16px;border-radius:8px;text-decoration:none;font-weight:600;font-size:.85rem;color:#fff;background:#555">Email</a>
  </div>
</div>
HTML;

$db->prepare("UPDATE landing_pages SET contenu=?, contenu_nl=? WHERE slug='agir'")
   ->execute([$contenu_fr, $contenu_nl]);
echo "✅ Contenu par défaut injecté dans landing_pages.\n";
echo "FR: " . strlen($contenu_fr) . " chars\n";
echo "NL: " . strlen($contenu_nl) . " chars\n";
