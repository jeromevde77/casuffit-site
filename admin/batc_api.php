<?php
// admin/batc_api.php — v2 — Explorateur des endpoints API BATC (skeyes / Brussels Airport)
require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();

// ── Candidats d'endpoints (convention observée : /{lang}/api/visualisation/{nom}) ──
$CANDIDATS = [
    // Connu et déjà utilisé par le site
    'statistics_airport_movements',
    // Statistiques (d'après les rubriques du site BATC)
    'statistics_night_movements',
    'statistics_movements_night',
    'statistics_night_flights',
    'statistics_cdo',
    'statistics_continuous_descent_operations',
    'statistics_prs',
    'statistics_prs_movements',
    'statistics_wind_roses',
    'statistics_windroses',
    'statistics_wind_rose',
    // Pistes en usage
    'runway_usage',
    'runways_in_use',
    'current_runway_usage',
    'runway_configuration',
    'runway_forecast',
    'previous_runway_usage',
    // Bruit & météo
    'noise_measurements',
    'noise_measurement',
    'noise_contours',
    'weather_measurements',
    'weather_observations',
    'meteo_measurements',
    // Radar
    'radar_tracks',
    'flight_radar',
    'tracks',
];

$PREFIXES = ['/fr/api/visualisation/', '/fr/api/'];

$results = null;
$custom_result = null;

function batc_call(string $url, int $timeout = 8): array {
    $ctx = stream_context_create(['http' => [
        'timeout'       => $timeout,
        'user_agent'    => 'Mozilla/5.0 (compatible; casuffit-explorer/1.0)',
        'ignore_errors' => true,
        'header'        => "Accept: application/json\r\n",
    ]]);
    $t0   = microtime(true);
    $body = @file_get_contents($url, false, $ctx);
    $ms   = round((microtime(true) - $t0) * 1000);
    $code = 0; $ctype = '';
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) $code = (int)$m[1];
            if (stripos($h, 'content-type:') === 0)          $ctype = trim(substr($h, 13));
        }
    }
    $is_json = false; $parsed = null;
    if ($body !== false && $body !== '') {
        $parsed  = json_decode($body, true);
        $is_json = (json_last_error() === JSON_ERROR_NONE) && (is_array($parsed));
    }
    return [
        'code' => $code, 'ctype' => $ctype, 'ms' => $ms,
        'ok'   => ($body !== false), 'is_json' => $is_json,
        'len'  => $body === false ? 0 : strlen($body),
        'body' => $body === false ? '' : $body,
        'keys' => $is_json ? array_slice(array_keys($parsed), 0, 12) : [],
    ];
}

// ── Sondage des candidats ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['probe'])) {
    $date_ts   = strtotime($_POST['date'] ?? 'yesterday') ?: time();
    $aggregate = in_array($_POST['aggregate'] ?? 'month', ['day','week','month','year']) ? $_POST['aggregate'] : 'month';
    $qs = 'time_of_day=day_night&aggregate=' . $aggregate . '&date=' . $date_ts
        . '&departures_arrivals=departures_arrivals';
    $results = [];
    foreach ($PREFIXES as $pref) {
        foreach ($CANDIDATS as $ep) {
            $url = 'https://www.batc.be' . $pref . $ep . '?' . $qs;
            $r = batc_call($url);
            $r['url'] = $url; $r['endpoint'] = $pref . $ep;
            $results[] = $r;
            usleep(350000); // 350 ms — ne pas surcharger BATC
        }
    }
}

// ── Découverte automatique : analyse des bundles JS de batc.be ──────────
$scan_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['scan'])) {
    $pages = [
        'https://www.batc.be/fr/statistiques/roses-des-vents',
        'https://www.batc.be/fr/statistiques/prs',
        'https://www.batc.be/fr/statistiques/mouvements-aeroportuaires',
        'https://www.batc.be/fr/pistes-en-usage/actuel-prevision',
        'https://www.batc.be/fr/bruit/mesures-sonores',
        'https://www.batc.be/fr/meteo/mesures-meteo',
    ];
    $scripts = [];   // url => true
    $errors  = [];
    foreach ($pages as $pg) {
        $r = batc_call($pg, 12);
        if (!$r['ok'] || $r['code'] >= 400) { $errors[] = "Page inaccessible : $pg (HTTP {$r['code']})"; continue; }
        // Extraire les <script src>
        if (preg_match_all('#<script[^>]+src=["\']([^"\']+)["\']#i', $r['body'], $m)) {
            foreach ($m[1] as $src) {
                // Résoudre les URL relatives, ne garder que batc.be
                if (strpos($src, '//') === 0)      $src = 'https:' . $src;
                elseif (strpos($src, '/') === 0)   $src = 'https://www.batc.be' . $src;
                elseif (!preg_match('#^https?://#', $src)) continue;
                if (!preg_match('#^https://(www\.)?batc\.be/#', $src)) continue;
                $scripts[$src] = true;
            }
        }
        usleep(250000);
    }
    // Télécharger chaque bundle et y chercher des chemins d'API
    $found = [];   // chemin => [scripts...]
    $scanned = 0;
    foreach (array_keys($scripts) as $js_url) {
        if ($scanned >= 20) break;
        $r = batc_call($js_url, 15);
        $scanned++;
        if (!$r['ok'] || $r['code'] >= 400 || $r['len'] === 0) continue;
        $body = $r['body'];
        $patterns = [
            '#["\'](/(?:[a-z]{2}/)?api/[A-Za-z0-9_\-/]+)["\']#',
            '#["\'](https://(?:www\.)?batc\.be/[^"\']*api[^"\']*)["\']#',
            '#["\'](visualisation/[A-Za-z0-9_\-/]+)["\']#',
            '#["\'](/[a-z]{2}/api/[^"\'?]+)#',
        ];
        foreach ($patterns as $pat) {
            if (preg_match_all($pat, $body, $mm)) {
                foreach ($mm[1] as $path) {
                    $path = rtrim($path, '/');
                    if (strlen($path) < 5 || strlen($path) > 200) continue;
                    if (!isset($found[$path])) $found[$path] = [];
                    $short = basename(parse_url($js_url, PHP_URL_PATH));
                    if (!in_array($short, $found[$path])) $found[$path][] = $short;
                }
            }
        }
        usleep(250000);
    }
    ksort($found);
    $scan_result = [
        'pages'    => count($pages),
        'scripts'  => count($scripts),
        'scanned'  => $scanned,
        'found'    => $found,
        'errors'   => $errors,
    ];
}

// ── Test d'une URL libre ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['custom'])) {
    $u = trim($_POST['url'] ?? '');
    if ($u && preg_match('#^https://(www\.)?batc\.be/#', $u)) {
        $custom_result = batc_call($u, 12);
        $custom_result['url'] = $u;
    } else {
        $custom_result = ['error' => 'URL invalide — seules les URL https://www.batc.be/… sont autorisées.'];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>API BATC — Admin Ça suffit !</title>
<style>
<?php include __DIR__.'/../includes/admin_sidebar_css.php'; ?>
body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333;margin:0}
*{box-sizing:border-box}
.page-header{padding:22px 28px 0}
.page-header h1{font-size:1.2rem;font-weight:800;color:#0e3d6b;margin:0 0 4px}
.page-header p{font-size:.82rem;color:#888;margin:0}
.card{background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.08);margin:18px 28px;padding:20px 24px}
.card h2{font-size:.92rem;font-weight:800;color:#0e3d6b;margin:0 0 14px;padding-bottom:9px;border-bottom:2px solid #f0f4f8}
.ctrl{display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end}
.fg{display:flex;flex-direction:column;gap:4px}
.fg label{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#888}
.fg input,.fg select{padding:8px 12px;border:1.5px solid #cdd8e5;border-radius:8px;font-size:.86rem;font-family:inherit}
.fg input:focus,.fg select:focus{outline:none;border-color:#1673B2}
.fg.grow{flex:1;min-width:280px}
.fg.grow input{width:100%}
.btn{padding:10px 22px;border:none;border-radius:8px;font-size:.86rem;font-weight:700;cursor:pointer;font-family:inherit}
.btn-go{background:#FF9900;color:#fff}.btn-go:hover{background:#e08800}
.btn-2{background:#0e3d6b;color:#fff}.btn-2:hover{background:#1673B2}
.note{font-size:.75rem;color:#999;margin-top:10px;line-height:1.6}
table{width:100%;border-collapse:collapse;font-size:.82rem}
th{text-align:left;padding:8px 10px;background:#f7fafd;color:#0e3d6b;font-size:.7rem;text-transform:uppercase;letter-spacing:.04em;border-bottom:2px solid #e6eef7}
td{padding:8px 10px;border-bottom:1px solid #f0f4f8;vertical-align:top}
tr.hit{background:#f0fbf4}
tr.hit td{border-bottom-color:#cdeed9}
code{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:.78rem;background:#f4f7fb;padding:1px 5px;border-radius:4px}
.badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:.68rem;font-weight:800}
.b-ok{background:#e8f8f0;color:#1a6e3c}
.b-no{background:#fdecea;color:#a5352a}
.b-warn{background:#fff6e0;color:#9a6a00}
.keys{font-size:.72rem;color:#666;margin-top:3px}
details summary{cursor:pointer;font-size:.75rem;color:#1673B2;font-weight:700}
pre{background:#0e2438;color:#d6e4f0;padding:12px 14px;border-radius:8px;overflow-x:auto;font-size:.72rem;line-height:1.5;margin-top:8px;max-height:340px}
.sum{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px}
.pill{padding:5px 13px;border-radius:20px;font-size:.78rem;font-weight:700;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.08)}
</style>
</head>
<body>
<?php include __DIR__.'/../includes/admin_sidebar.php'; ?>
<div class="wrap">

<div class="page-header">
  <h1>🔌 Explorateur API BATC</h1>
  <p>Sonde les endpoints de batc.be depuis le serveur (skeyes / Brussels Airport)</p>
</div>

<div class="card">
  <h2>1. Découverte automatique (analyse des scripts JS de batc.be)</h2>
  <form method="POST">
    <button type="submit" name="scan" value="1" class="btn btn-go">🔎 Analyser les bundles JS et extraire les chemins d'API</button>
    <div class="note">
      Cette méthode télécharge les pages statistiques de batc.be, récupère leurs fichiers JavaScript,
      et y cherche les chemins d'API réellement appelés par le site. C'est la façon la plus fiable de
      trouver les vrais endpoints (plutôt que de les deviner). Comptez ~20 à 40 secondes.
    </div>
  </form>

  <?php if (isset($scan_result) && $scan_result !== null): ?>
    <div class="sum" style="margin-top:16px">
      <span class="pill"><?= $scan_result['pages'] ?> pages analysées</span>
      <span class="pill"><?= $scan_result['scripts'] ?> scripts trouvés</span>
      <span class="pill"><?= $scan_result['scanned'] ?> bundles scannés</span>
      <span class="pill" style="color:<?= $scan_result['found'] ? '#1a6e3c' : '#a5352a' ?>">
        <?= count($scan_result['found']) ?> chemin(s) d'API détecté(s)
      </span>
    </div>
    <?php if ($scan_result['errors']): ?>
      <div class="note" style="color:#9a6a00">
        <?php foreach ($scan_result['errors'] as $e): ?>⚠ <?= htmlspecialchars($e) ?><br><?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if ($scan_result['found']): ?>
      <table style="margin-top:8px">
        <tr><th style="width:60%">Chemin d'API détecté</th><th>Trouvé dans</th><th style="width:90px">Tester</th></tr>
        <?php foreach ($scan_result['found'] as $path => $srcs):
          $full = (strpos($path,'http')===0) ? $path : ('https://www.batc.be'.(strpos($path,'/')===0?'':'/fr/api/').$path);
        ?>
        <tr class="hit">
          <td><code><?= htmlspecialchars($path) ?></code></td>
          <td style="font-size:.72rem;color:#888"><?= htmlspecialchars(implode(', ', array_slice($srcs,0,3))) ?></td>
          <td><a href="#test" onclick="document.querySelector('[name=url]').value='<?= htmlspecialchars($full) ?>';document.querySelector('[name=url]').scrollIntoView({block:'center'});return false;" style="font-size:.74rem;color:#1673B2;font-weight:700">↓ tester</a></td>
        </tr>
        <?php endforeach; ?>
      </table>
    <?php else: ?>
      <div class="note" style="color:#a5352a;font-weight:700;margin-top:10px">
        Aucun chemin d'API trouvé dans les bundles. Le site charge peut-être ses données autrement
        (GraphQL, endpoints construits dynamiquement). Utilisez alors la méthode manuelle ci-dessous
        via l'inspecteur réseau du navigateur.
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<div class="card">
  <h2>2. Sonder les endpoints candidats</h2>
  <form method="POST">
    <div class="ctrl">
      <div class="fg">
        <label>Date de référence</label>
        <input type="date" name="date" value="<?= htmlspecialchars($_POST['date'] ?? date('Y-m-d', strtotime('-1 day'))) ?>">
      </div>
      <div class="fg">
        <label>Agrégation</label>
        <select name="aggregate">
          <?php foreach (['day','week','month','year'] as $a): ?>
          <option value="<?= $a ?>" <?= (($_POST['aggregate'] ?? 'month')===$a)?'selected':'' ?>><?= $a ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" name="probe" value="1" class="btn btn-go">🔍 Sonder <?= count($CANDIDATS)*count($PREFIXES) ?> URL</button>
    </div>
    <div class="note">
      Le sondage teste deux préfixes (<code>/fr/api/visualisation/</code> et <code>/fr/api/</code>) sur
      <?= count($CANDIDATS) ?> noms d'endpoints plausibles, avec 350 ms entre chaque appel pour ne pas
      surcharger le service de BATC. Comptez environ <?= round(count($CANDIDATS)*count($PREFIXES)*0.6) ?> secondes.
    </div>
  </form>
</div>

<?php if ($results !== null):
  $hits = array_filter($results, function($r){ return $r['is_json'] && $r['code'] >= 200 && $r['code'] < 300; });
?>
<div class="card">
  <h2>Résultats du sondage</h2>
  <div class="sum">
    <span class="pill" style="color:#1a6e3c"><?= count($hits) ?> endpoint(s) JSON valide(s)</span>
    <span class="pill" style="color:#888"><?= count($results) ?> URL testées</span>
  </div>
  <table>
    <tr><th style="width:34%">Endpoint</th><th style="width:9%">HTTP</th><th style="width:9%">Temps</th><th style="width:9%">Taille</th><th>Contenu</th></tr>
    <?php foreach ($results as $r):
      $is_hit = $r['is_json'] && $r['code'] >= 200 && $r['code'] < 300;
      // On masque le bruit : n'afficher que les réponses intéressantes ou les erreurs franches
      if (!$is_hit && $r['code'] === 404) continue;
    ?>
    <tr class="<?= $is_hit ? 'hit' : '' ?>">
      <td><code><?= htmlspecialchars($r['endpoint']) ?></code></td>
      <td><span class="badge <?= $is_hit ? 'b-ok' : ($r['code']>=200&&$r['code']<400 ? 'b-warn' : 'b-no') ?>"><?= $r['code'] ?: 'ERR' ?></span></td>
      <td><?= $r['ms'] ?> ms</td>
      <td><?= number_format($r['len']) ?> o</td>
      <td>
        <?php if ($is_hit): ?>
          <strong style="color:#1a6e3c">✅ JSON</strong>
          <?php if ($r['keys']): ?><div class="keys">Clés : <?= htmlspecialchars(implode(', ', $r['keys'])) ?></div><?php endif; ?>
          <details><summary>Voir la réponse</summary><pre><?= htmlspecialchars(substr($r['body'], 0, 3000)) ?></pre></details>
        <?php else: ?>
          <span style="color:#999"><?= htmlspecialchars($r['ctype'] ?: 'pas de réponse') ?></span>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <div class="note">Les réponses <code>404</code> sont masquées. Un endpoint « JSON valide » est directement exploitable via un proxy PHP comme <code>api/batc_stats.php</code>.</div>
</div>
<?php endif; ?>

<div class="card">
  <h2 id="test">3. Tester une URL précise</h2>
  <form method="POST">
    <div class="ctrl">
      <div class="fg grow">
        <label>URL (doit commencer par https://www.batc.be/)</label>
        <input type="text" name="url" placeholder="https://www.batc.be/fr/api/visualisation/…"
               value="<?= htmlspecialchars($_POST['url'] ?? '') ?>">
      </div>
      <button type="submit" name="custom" value="1" class="btn btn-2">▶ Tester</button>
    </div>
  </form>

  <?php if ($custom_result !== null): ?>
    <?php if (isset($custom_result['error'])): ?>
      <div class="note" style="color:#a5352a;font-weight:700">⚠ <?= htmlspecialchars($custom_result['error']) ?></div>
    <?php else: ?>
      <div style="margin-top:14px">
        <span class="badge <?= $custom_result['is_json'] ? 'b-ok' : 'b-warn' ?>">HTTP <?= $custom_result['code'] ?></span>
        <span style="font-size:.78rem;color:#888;margin-left:8px">
          <?= htmlspecialchars($custom_result['ctype']) ?> · <?= $custom_result['ms'] ?> ms · <?= number_format($custom_result['len']) ?> octets
        </span>
        <pre><?= htmlspecialchars(substr($custom_result['body'], 0, 6000)) ?></pre>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Endpoint déjà exploité par le site</h2>
  <p style="font-size:.84rem;line-height:1.7;color:#555;margin:0">
    <code>api/batc_stats.php</code> relaie déjà :<br>
    <code>https://www.batc.be/fr/api/visualisation/statistics_airport_movements</code><br>
    Paramètres : <code>time_of_day</code>, <code>aggregate</code> (day/week/month), <code>date</code> (timestamp Unix),
    <code>departures_arrivals</code>.
  </p>
  <div class="note">
    Rappel : ces données sont publiées publiquement par skeyes et Brussels Airport à destination des riverains.
    Restez sur des appels ponctuels et mettez les réponses en cache côté serveur (comme le fait
    <code>api/rose_vents.php</code> pour l'IRM) plutôt que d'interroger BATC à chaque visite.
  </div>
</div>

</div>
</body>
</html>
