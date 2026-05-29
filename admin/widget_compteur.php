<?php
// admin/widget_compteur.php — Prévisualisation du widget compteur public (draft)
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../membre/functions.php';
session_start(); requireAdmin();
$db = getDB();

// ── Données compteurs ─────────────────────────────────────────────────────
$membres_actifs   = (int)$db->query("SELECT COUNT(*) FROM members WHERE statut='actif'")->fetchColumn();
$abonnes_actifs   = (int)$db->query("SELECT COUNT(*) FROM subscribers WHERE statut='actif'")->fetchColumn();
$abonnes_nl       = (int)$db->query("SELECT COUNT(*) FROM subscribers WHERE statut='actif' AND source_import='wix'")->fetchColumn();
$communes_membres = (int)$db->query("SELECT COUNT(DISTINCT TRIM(commune)) FROM members WHERE statut='actif' AND commune IS NOT NULL AND commune != ''")->fetchColumn();

// Plaintes (si table existe)
$plaintes = 0;
try { $plaintes = (int)$db->query("SELECT COALESCE(SUM(nb_clics),0) FROM plainte_clicks")->fetchColumn(); } catch(Exception $e) {}

// Communes + nb membres pour la carte
$communes_map = $db->query("
    SELECT TRIM(commune) as commune, COUNT(*) as nb
    FROM members WHERE statut='actif' AND commune IS NOT NULL AND commune != ''
    GROUP BY TRIM(commune) ORDER BY nb DESC
")->fetchAll(PDO::FETCH_ASSOC);

$communes_json = json_encode($communes_map, JSON_UNESCAPED_UNICODE);
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Preview — Widget Compteur Public</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Helvetica Neue",Arial,sans-serif;background:#e8edf5;padding:24px 16px}
.preview-label{text-align:center;font-size:.75rem;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.1em;margin-bottom:16px}
.preview-note{background:#fff8dc;border:1px solid #e0c000;border-radius:8px;padding:10px 14px;font-size:.8rem;color:#666;margin-bottom:20px;text-align:center}

/* ── Widget compteur ── */
.compteur-widget{background:linear-gradient(135deg,#0e3d6b,#1673B2);border-radius:16px;padding:24px 20px;color:#fff;margin-bottom:20px;box-shadow:0 4px 20px rgba(14,61,107,.3)}
.compteur-title{font-size:1.05rem;font-weight:800;text-align:center;margin-bottom:4px;letter-spacing:.02em}
.compteur-sub{font-size:.72rem;text-align:center;opacity:.65;margin-bottom:22px}
.compteur-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.compteur-card{background:rgba(255,255,255,.1);border-radius:12px;padding:14px 10px;text-align:center;border:1px solid rgba(255,255,255,.15)}
.compteur-num{font-size:2rem;font-weight:900;letter-spacing:-.02em;line-height:1;color:#fff}
.compteur-num.orange{color:#FF9900}
.compteur-lbl{font-size:.65rem;opacity:.75;margin-top:6px;line-height:1.4;text-transform:uppercase;letter-spacing:.06em}
.compteur-divider{height:1px;background:rgba(255,255,255,.15);margin:18px 0}
.compteur-cta{display:flex;gap:10px;flex-wrap:wrap}
.compteur-btn{flex:1;text-align:center;padding:11px;border-radius:10px;text-decoration:none;font-size:.82rem;font-weight:700;transition:all .15s}
.compteur-btn-orange{background:#FF9900;color:#fff}
.compteur-btn-outline{background:rgba(255,255,255,.12);color:#fff;border:1.5px solid rgba(255,255,255,.3)}

/* ── Widget carte ── */
.carte-widget{background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.1);margin-bottom:20px}
.carte-header{background:linear-gradient(135deg,#0e3d6b,#1673B2);padding:16px 18px;color:#fff}
.carte-title{font-size:.95rem;font-weight:800;margin-bottom:2px}
.carte-sub{font-size:.7rem;opacity:.65}
#map-public{height:340px;width:100%}
.carte-footer{padding:10px 14px;font-size:.7rem;color:#aaa;text-align:center}

/* ── Combiné ── */
.combo-widget{background:linear-gradient(135deg,#0e3d6b,#1673B2);border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(14,61,107,.3);margin-bottom:20px;color:#fff}
.combo-map{height:280px}
.combo-stats{padding:16px 18px}
.combo-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:14px}
.combo-card{text-align:center;padding:10px 4px;background:rgba(255,255,255,.1);border-radius:10px;border:1px solid rgba(255,255,255,.15)}
.combo-num{font-size:1.4rem;font-weight:900;line-height:1;color:#fff}
.combo-num.orange{color:#FF9900}
.combo-lbl{font-size:.58rem;opacity:.7;margin-top:4px;text-transform:uppercase;letter-spacing:.05em;line-height:1.3}
.combo-cta{display:flex;gap:8px}
.combo-btn{flex:1;text-align:center;padding:11px;border-radius:10px;text-decoration:none;font-size:.82rem;font-weight:700}
.combo-btn-orange{background:#FF9900;color:#fff}
.combo-btn-outline{background:rgba(255,255,255,.12);color:#fff;border:1.5px solid rgba(255,255,255,.3)}
</style>
</head>
<body>

<div class="preview-note">
  🔒 Page de prévisualisation — admin uniquement · Non publiée · <a href="dashboard.php">← Dashboard</a>
</div>

<!-- OPTION 1 : Compteurs seuls -->
<div class="preview-label">Option 1 — Compteurs</div>
<div class="compteur-widget">
  <div class="compteur-title">Rejoignez le mouvement</div>
  <div class="compteur-sub">Riverains mobilisés contre les nuisances aériennes de Brussels Airport</div>
  <div class="compteur-grid">
    <div class="compteur-card">
      <div class="compteur-num orange"><?= $membres_actifs ?></div>
      <div class="compteur-lbl">Membres<br>adhérents</div>
    </div>
    <div class="compteur-card">
      <div class="compteur-num"><?= $abonnes_actifs ?></div>
      <div class="compteur-lbl">Abonnés<br>informés</div>
    </div>
    <div class="compteur-card">
      <div class="compteur-num orange"><?= $communes_membres ?></div>
      <div class="compteur-lbl">Communes<br>représentées</div>
    </div>
    <div class="compteur-card">
      <div class="compteur-num"><?= $plaintes ?: '—' ?></div>
      <div class="compteur-lbl">Plaintes<br>déposées</div>
    </div>
  </div>
  <?php if ($plaintes || $communes_membres): ?>
  <div class="compteur-divider"></div>
  <div class="compteur-cta">
    <a href="/membre/inscription.php" class="compteur-btn compteur-btn-orange">✦ Rejoindre</a>
    <a href="/don.php" class="compteur-btn compteur-btn-outline">💛 Soutenir</a>
  </div>
  <?php endif; ?>
</div>

<!-- OPTION 2 : Carte seule -->
<div class="preview-label">Option 2 — Carte</div>
<div class="carte-widget">
  <div class="carte-header">
    <div class="carte-title">📍 <?= $membres_actifs ?> membres — <?= $communes_membres ?> communes</div>
    <div class="carte-sub">Riverains mobilisés — positions approximatives par commune</div>
  </div>
  <div id="map-public"></div>
  <div class="carte-footer">Positions par commune uniquement · Aucune donnée personnelle affichée</div>
</div>

<!-- OPTION 3 : Carte + compteurs combinés -->
<div class="preview-label">Option 3 — Combiné (recommandé)</div>
<div class="combo-widget">
  <div class="combo-map" id="map-combo"></div>
  <div class="combo-stats">
    <div class="combo-grid">
      <div class="combo-card">
        <div class="combo-num orange"><?= $membres_actifs ?></div>
        <div class="combo-lbl">Membres</div>
      </div>
      <div class="combo-card">
        <div class="combo-num"><?= $abonnes_actifs ?></div>
        <div class="combo-lbl">Abonnés</div>
      </div>
      <div class="combo-card">
        <div class="combo-num orange"><?= $communes_membres ?></div>
        <div class="combo-lbl">Communes</div>
      </div>
      <div class="combo-card">
        <div class="combo-num"><?= $plaintes ?: '+20' ?></div>
        <div class="combo-lbl">Plaintes</div>
      </div>
    </div>
    <div class="combo-cta">
      <a href="/membre/inscription.php" class="combo-btn combo-btn-orange">✦ Rejoindre le mouvement</a>
      <a href="/don.php" class="combo-btn combo-btn-outline">💛 Soutenir</a>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
var communesData = <?= $communes_json ?>;

// Tuile sombre stylisée
var darkTile = L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_matter/{z}/{x}/{y}{r}.png', {
  attribution: '© OpenStreetMap © CARTO',
  subdomains: 'abcd', maxZoom: 13
});
var lightTile = L.tileLayer('https://{s}.basemaps.cartocdn.com/light_nolabels/{z}/{x}/{y}{r}.png', {
  attribution: '© OpenStreetMap © CARTO',
  subdomains: 'abcd', maxZoom: 13
});

// Cache des coordonnées geocodées
var geoCache = {};

function geocodeCommune(nom, cb) {
  if (geoCache[nom]) { cb(geoCache[nom]); return; }
  var url = 'https://nominatim.openstreetmap.org/search?q=' + encodeURIComponent(nom + ', Belgique') +
            '&format=json&limit=1&countrycodes=be';
  fetch(url, {headers: {'Accept-Language':'fr','User-Agent':'casuffit.be-preview'}})
    .then(function(r){return r.json();})
    .then(function(d){
      if (d && d[0]) {
        var c = {lat:parseFloat(d[0].lat), lon:parseFloat(d[0].lon)};
        geoCache[nom] = c; cb(c);
      }
    }).catch(function(){});
}

function initMap(containerId, tile, showLabels) {
  var map = L.map(containerId, {
    zoomControl:false, attributionControl:false,
    dragging:false, scrollWheelZoom:false, doubleClickZoom:false,
    touchZoom:false
  }).setView([50.89, 4.48], 10);
  tile.addTo(map);

  // Ajouter les communes avec délai pour respecter rate limit Nominatim
  var delay = 0;
  communesData.forEach(function(c) {
    setTimeout(function() {
      geocodeCommune(c.commune, function(coords) {
        // Légère variation aléatoire pour éviter superposition exacte
        var jitter = function() { return (Math.random() - 0.5) * 0.018; };
        var lat = coords.lat + jitter();
        var lon = coords.lon + jitter();

        var r = Math.max(8, Math.min(28, 8 + Math.sqrt(c.nb) * 4));
        var circle = L.circleMarker([lat, lon], {
          radius: r,
          fillColor: '#FF9900',
          color: '#fff',
          weight: 2,
          opacity: 0.9,
          fillOpacity: 0.75
        });
        if (showLabels && c.nb > 1) {
          circle.bindTooltip(c.commune + ' (' + c.nb + ')', {permanent:false, direction:'top', className:'leaflet-tooltip'});
        }
        circle.addTo(map);
      });
    }, delay);
    delay += 220; // 220ms entre chaque requête Nominatim
  });

  // Marker aéroport
  L.circleMarker([50.9008, 4.4844], {
    radius:6, fillColor:'#e53e3e', color:'#fff', weight:2,
    fillOpacity:0.9, opacity:1
  }).bindTooltip('✈ Brussels Airport', {permanent:false, direction:'top'}).addTo(map);

  return map;
}

initMap('map-public', darkTile, true);
var t2 = L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_matter/{z}/{x}/{y}{r}.png', {
  subdomains:'abcd', maxZoom:13
});
initMap('map-combo', t2, false);
</script>

</body>
</html>
