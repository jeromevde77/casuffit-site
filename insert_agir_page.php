<?php
require_once __DIR__ . '/config.php';
$db = getDB();

// Vérifier si la page existe déjà
$existing = $db->prepare("SELECT id FROM pages WHERE slug = 'agir'");
$existing->execute();
if ($existing->fetch()) { echo "Page 'agir' déjà en BDD.\n"; exit; }

$contenu_fr = <<<HTML
<div class="cadre-bleu" style="margin-bottom:20px">
  🛡 <strong>ça suffit ! ASBL</strong> mène la mobilisation citoyenne contre les nuisances aériennes de Brussels Airport. Rejoignez notre combat — chaque soutien compte.
</div>

<h2>Pourquoi nous agissons</h2>
<ul>
  <li>Des dizaines de milliers de riverains survolés, souvent hors normes</li>
  <li>Non-respect des seuils de vent du PRS (Plan de Répartition du Survol)</li>
  <li>Une dégradation insidieuse de votre qualité de vie</li>
  <li>Un référé déposé contre l'État belge — combat juridique en cours</li>
</ul>

<h2>Comment agir ?</h2>
<div class="actions-grid">
  <div class="action-card">
    <div class="ac-num">👤</div>
    <div class="ac-titre"><a href="/membre/inscription.php">Devenir membre →</a></div>
    <div class="ac-text">QR code personnel, historique de vos dons, newsletter et accès à votre espace membre. Gratuit.</div>
  </div>
  <div class="action-card">
    <div class="ac-num">💶</div>
    <div class="ac-titre"><a href="/?page=nous-soutenir">Faire un don →</a></div>
    <div class="ac-text">Aidez-nous à financer la suite de notre combat juridique contre l'État belge.</div>
  </div>
  <div class="action-card">
    <div class="ac-num">🌬</div>
    <div class="ac-titre"><a href="/wind.php">Surveiller les vols →</a></div>
    <div class="ac-text">Vent en direct, historique METAR, rose des vents et radar de vols — installable comme une app.</div>
  </div>
  <div class="action-card">
    <div class="ac-num">📢</div>
    <div class="ac-titre"><a href="/?page=mobilisation">S'impliquer →</a></div>
    <div class="ac-text">Partagez, signez, sensibilisez vos voisins. Ensemble nous sommes plus forts.</div>
  </div>
</div>

<div class="signature">
  👉 Partagez cette page avec vos voisins : <strong>casuffit.be/agir</strong>
</div>
HTML;

$db->prepare("INSERT INTO pages (titre, slug, visible, dans_menu, menu_position, ordre, contenu, meta_description) VALUES (?,?,1,0,'none',99,?,?)")
   ->execute([
     'Agir avec nous',
     'agir',
     $contenu_fr,
     'Rejoignez ça suffit ! ASBL — Devenez membre, faites un don, surveillez les vols en temps réel.'
   ]);

$id = $db->lastInsertId();
echo "✅ Page 'agir' créée (ID=$id)\n";
