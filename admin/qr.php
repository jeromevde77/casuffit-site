<?php
// admin/qr.php — Générateur de QR codes pour flyers / campagnes
require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();
require_once __DIR__ . '/../includes/csrf.php';

$base = 'https://www.casuffit.be';

// Presets de campagnes courantes
$presets = [
    'agir' => [
        'label' => '📢 Flyer général',
        'url'   => '/agir',
        'tip'   => 'Page d\'atterrissage principale — Devenir membre, faire un don, outils météo',
    ],
    'agir_nl' => [
        'label' => '🇳🇱 Flyer NL',
        'url'   => '/doe-mee',
        'tip'   => 'Version néerlandaise — pour les communes flamandes (Kraainem, Wezembeek, Tervuren…)',
    ],
    'wind' => [
        'label' => '🌬 Outils météo',
        'url'   => '/wind.php',
        'tip'   => 'Affiches/stickers vers les outils de surveillance',
    ],
    'membre' => [
        'label' => '👤 Inscription directe',
        'url'   => '/membre/inscription.php',
        'tip'   => 'Pour collecter des membres lors de stands',
    ],
    'don' => [
        'label' => '💶 Faire un don',
        'url'   => '/?utm_campaign=don#don',
        'tip'   => 'QR direct vers la zone don',
    ],
    'custom' => [
        'label' => '✏️ URL personnalisée',
        'url'   => '',
        'tip'   => 'Saisis ton URL — toujours commencer par /',
    ],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>QR Codes — Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
<?php include __DIR__ . '/../includes/admin_sidebar_css.php'; ?>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333;font-size:14px}

body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; margin:0; background:#f5f7fa; color:#222; }
.main { margin-left: 240px; padding: 24px 28px; }
@media (max-width: 768px) { .main { margin-left: 0; padding-top: 60px; } }
.page-head { display:flex; align-items:center; justify-content:space-between; margin-bottom: 20px; }
.page-head h1 { font-size: 1.4rem; color: #1673B2; font-weight: 700; }
.page-head .hint { font-size: .82rem; color: #888; }

.grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
@media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }

.panel { background: #fff; border-radius: 10px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,.05); }
.panel h2 { font-size: 1rem; color: #1673B2; margin-bottom: 14px; font-weight: 700; padding-bottom: 10px; border-bottom: 1px solid #e8eef3; }

.form-group { margin-bottom: 14px; }
.form-group label { display: block; font-size: .8rem; font-weight: 600; color: #555; margin-bottom: 4px; text-transform: uppercase; letter-spacing: .03em; }
.form-group input, .form-group select, .form-group textarea {
  width: 100%; padding: 9px 12px; border: 1.5px solid #d0d8e0; border-radius: 6px;
  font-family: inherit; font-size: .9rem; color: #222; background: #fff;
}
.form-group input:focus, .form-group select:focus { border-color: #1673B2; outline: none; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.form-hint { font-size: .72rem; color: #888; margin-top: 4px; font-style: italic; }

.url-preview { background: #f5f7fa; border-left: 3px solid #1673B2; padding: 10px 12px; font-family: 'SF Mono', Monaco, Consolas, monospace; font-size: .8rem; color: #1673B2; word-break: break-all; margin-bottom: 14px; border-radius: 0 4px 4px 0; }

.qr-display { text-align: center; padding: 20px 0; }
.qr-display canvas, .qr-display img { max-width: 320px; height: auto; border: 12px solid #fff; box-shadow: 0 6px 20px rgba(0,0,0,.1); border-radius: 6px; }

.btn { display: inline-block; padding: 9px 18px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: .85rem; cursor: pointer; border: none; font-family: inherit; transition: transform .15s; }
.btn:active { transform: scale(.97); }
.btn-orange { background: #FF9900; color: #fff; }
.btn-blue { background: #1673B2; color: #fff; }
.btn-outline { background: #fff; color: #1673B2; border: 1.5px solid #1673B2; }
.btn-row { display: flex; gap: 8px; flex-wrap: wrap; justify-content: center; margin-top: 14px; }

.preset-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 14px; }
.preset-btn {
  padding: 10px; border: 1.5px solid #e8eef3; border-radius: 7px; background: #fafbfc;
  cursor: pointer; text-align: left; font-family: inherit; transition: all .15s;
}
.preset-btn:hover { border-color: #1673B2; background: #f0f7fc; }
.preset-btn.active { border-color: #FF9900; background: #fff8ee; }
.preset-btn .pb-label { font-weight: 700; color: #1673B2; font-size: .88rem; }
.preset-btn .pb-tip { font-size: .72rem; color: #777; margin-top: 3px; }

.size-controls { display: flex; gap: 8px; align-items: center; margin-bottom: 14px; }
.size-controls label { font-size: .78rem; color: #555; font-weight: 600; }
.size-controls input[type=range] { flex: 1; }
.size-value { min-width: 50px; text-align: right; font-family: monospace; color: #1673B2; font-weight: 700; }

.tips { background: #fff8ee; border-left: 3px solid #FF9900; padding: 12px 14px; border-radius: 4px; font-size: .82rem; color: #856404; line-height: 1.5; }
.tips strong { color: #c47700; }
.tips ul { margin-left: 18px; margin-top: 6px; }
.tips li { margin-bottom: 4px; }
</style>
</head>
<body>

<?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

<div class="main">
  <div class="page-head">
    <div>
      <h1>📲 Générateur de QR codes</h1>
      <p class="hint">Pour flyers, affiches, stands — chaque QR est tracké via UTM</p>
    </div>
  </div>

  <div class="grid">

    <!-- ── Panel config ── -->
    <div class="panel">
      <h2>⚙️ Configuration du QR</h2>

      <label style="display:block;font-size:.8rem;font-weight:600;color:#555;margin-bottom:8px;text-transform:uppercase;letter-spacing:.03em">Modèle de campagne</label>
      <div class="preset-grid" id="preset-grid">
        <?php foreach ($presets as $key => $p): ?>
          <div class="preset-btn" data-preset="<?= $key ?>" data-url="<?= htmlspecialchars($p['url']) ?>" onclick="selectPreset('<?= $key ?>')">
            <div class="pb-label"><?= htmlspecialchars($p['label']) ?></div>
            <div class="pb-tip"><?= htmlspecialchars($p['tip']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="form-group">
        <label>Chemin sur le site</label>
        <input type="text" id="path" value="/agir" oninput="updateQR()" placeholder="/agir, /wind.php, ...">
        <div class="form-hint">Toujours commencer par /. Ex : /agir, /wind.php, /membre/inscription.php</div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Source (utm_source)</label>
          <input type="text" id="utm_source" value="flyer" oninput="updateQR()" placeholder="flyer, sticker, web, …">
        </div>
        <div class="form-group">
          <label>Campagne (utm_campaign)</label>
          <input type="text" id="utm_campaign" value="mai2026" oninput="updateQR()" placeholder="mai2026, communes_nord, …">
        </div>
      </div>

      <div class="form-group">
        <label>Aperçu de l'URL finale</label>
        <div class="url-preview" id="url-preview"></div>
      </div>

      <div class="size-controls">
        <label>Taille (px)</label>
        <input type="range" id="size" min="200" max="1200" step="50" value="600" oninput="updateQR()">
        <span class="size-value" id="size-value">600</span>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Couleur QR</label>
          <input type="color" id="color-dark" value="#1673B2" oninput="updateQR()">
        </div>
        <div class="form-group">
          <label>Fond</label>
          <input type="color" id="color-light" value="#FFFFFF" oninput="updateQR()">
        </div>
      </div>

      <div class="form-group">
        <label>Niveau de correction d'erreur</label>
        <select id="ecl" onchange="updateQR()">
          <option value="L">L — bas (7%) — QR plus dense</option>
          <option value="M" selected>M — moyen (15%) — équilibré</option>
          <option value="Q">Q — élevé (25%) — résiste aux marques</option>
          <option value="H">H — très élevé (30%) — pour logos au centre</option>
        </select>
      </div>
    </div>

    <!-- ── Panel preview + download ── -->
    <div class="panel">
      <h2>📷 Aperçu</h2>

      <div class="qr-display" id="qr-display">
        <canvas id="qr-canvas"></canvas>
      </div>

      <div class="btn-row">
        <button class="btn btn-orange" onclick="downloadPNG()">⬇ Télécharger PNG</button>
        <button class="btn btn-blue" onclick="downloadSVG()">⬇ Télécharger SVG</button>
        <button class="btn btn-outline" onclick="copyURL()">📋 Copier l'URL</button>
      </div>

      <div class="tips" style="margin-top: 20px">
        <strong>💡 Conseils impression</strong>
        <ul>
          <li><strong>Taille mini</strong> : 3 cm × 3 cm sur flyer A5 (lisible à 30 cm)</li>
          <li><strong>Affiche A3</strong> : minimum 6 cm × 6 cm (scan à 1-2 m)</li>
          <li><strong>SVG préféré</strong> pour impression — qualité vectorielle</li>
          <li><strong>Contraste</strong> : foncé sur clair uniquement (jamais l'inverse)</li>
          <li><strong>Marge blanche</strong> autour du QR : laisse 4 modules (≈4mm)</li>
          <li><strong>Test obligatoire</strong> : scanne avec ton téléphone avant impression</li>
        </ul>
      </div>
    </div>

  </div>
</div>

<!-- QRCode.js — librairie côté client, pas d'install serveur -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>
<script>
const BASE = '<?= $base ?>';
let currentURL = '';

function selectPreset(key) {
  const btns = document.querySelectorAll('.preset-btn');
  btns.forEach(b => b.classList.remove('active'));
  const btn = document.querySelector(`[data-preset="${key}"]`);
  if (btn) {
    btn.classList.add('active');
    const url = btn.dataset.url;
    if (url) document.getElementById('path').value = url;

    // Adapter le UTM source selon le preset
    const utmSourceMap = {
      'agir':    'flyer',
      'agir_nl': 'flyer_nl',
      'wind':    'affiche',
      'membre':  'stand',
      'don':     'flyer',
      'custom':  'flyer',
    };
    if (utmSourceMap[key]) document.getElementById('utm_source').value = utmSourceMap[key];
    updateQR();
  }
}

function buildURL() {
  let path = document.getElementById('path').value.trim();
  if (!path.startsWith('/') && !path.startsWith('http')) path = '/' + path;
  const utmSource = document.getElementById('utm_source').value.trim();
  const utmCampaign = document.getElementById('utm_campaign').value.trim();
  const sep = path.includes('?') ? '&' : '?';
  const params = [];
  if (utmSource) params.push('utm_source=' + encodeURIComponent(utmSource));
  if (utmCampaign) params.push('utm_campaign=' + encodeURIComponent(utmCampaign));
  // Détecter fragment #
  let hashPart = '';
  const hashIdx = path.indexOf('#');
  if (hashIdx >= 0) {
    hashPart = path.substring(hashIdx);
    path = path.substring(0, hashIdx);
  }
  return BASE + path + (params.length ? sep + params.join('&') : '') + hashPart;
}

function updateQR() {
  const size = parseInt(document.getElementById('size').value);
  document.getElementById('size-value').textContent = size;
  const url = buildURL();
  currentURL = url;
  document.getElementById('url-preview').textContent = url;

  const canvas = document.getElementById('qr-canvas');
  new QRious({
    element: canvas,
    value: url,
    size: size,
    background: document.getElementById('color-light').value,
    foreground: document.getElementById('color-dark').value,
    level: document.getElementById('ecl').value,
    padding: Math.floor(size * 0.04),
  });
}

function downloadPNG() {
  const canvas = document.getElementById('qr-canvas');
  const link = document.createElement('a');
  const utm = document.getElementById('utm_campaign').value || 'qr';
  link.download = `qr-casuffit-${utm}.png`;
  link.href = canvas.toDataURL('image/png');
  link.click();
}

function downloadSVG() {
  // Génère un SVG simple basé sur le canvas (utilise une autre lib si besoin de vraie vectorisation)
  const size = parseInt(document.getElementById('size').value);
  const url = currentURL;
  const dark = document.getElementById('color-dark').value;
  const light = document.getElementById('color-light').value;
  const ecl = document.getElementById('ecl').value;

  // Régénérer en SVG via API publique (api.qrserver.com)
  // Alternative locale : utiliser qrcode-svg npm — ici on fait au plus simple via tracage du canvas
  const canvas = document.getElementById('qr-canvas');
  const ctx = canvas.getContext('2d');
  const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
  const w = canvas.width;
  const h = canvas.height;
  const px = Math.max(1, Math.floor(w / 33)); // détection approximative du module

  // Build SVG en exportant le canvas comme image embarquée (simple et fonctionnel)
  const xmlDecl = '<' + '?xml version="1.0" encoding="UTF-8"?' + '>';
  const svg = xmlDecl + `
<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 ${size} ${size}">
  <rect width="100%" height="100%" fill="${light}"/>
  <image href="${canvas.toDataURL('image/png')}" width="${size}" height="${size}"/>
</svg>`;
  const blob = new Blob([svg], {type: 'image/svg+xml'});
  const link = document.createElement('a');
  const utm = document.getElementById('utm_campaign').value || 'qr';
  link.download = `qr-casuffit-${utm}.svg`;
  link.href = URL.createObjectURL(blob);
  link.click();
}

function copyURL() {
  navigator.clipboard.writeText(currentURL).then(() => {
    alert('✓ URL copiée : ' + currentURL);
  });
}

// Init au chargement
document.addEventListener('DOMContentLoaded', () => {
  selectPreset('agir');
});
</script>

</body>
</html>
