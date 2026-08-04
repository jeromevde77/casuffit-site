<?php
/* outils-irm-migration.php — v1
 * Ajoute les colonnes irm_dir / irm_speed à metar_history et permet de
 * remplir rétroactivement l'historique depuis opendata.meteo.be (station 6451).
 * ⚠ À SUPPRIMER après usage.
 */
require_once __DIR__ . '/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!function_exists('isAdminLoggedIn') || !isAdminLoggedIn()) {
    http_response_code(403);
    exit('<!DOCTYPE html><meta charset="utf-8"><div style="font-family:Arial;padding:40px">🔒 Connectez-vous à l\'administration.</div>');
}

$db  = getDB();
$log = array();

$COLS = array(
    'irm_dir'   => "SMALLINT UNSIGNED NULL COMMENT 'Direction vent IRM (°) — wind_direction'",
    'irm_speed' => "FLOAT NULL COMMENT 'Vitesse vent IRM (kt) — wind_speed'",
);

// État des colonnes
$existantes = array();
try { foreach ($db->query("SHOW COLUMNS FROM metar_history")->fetchAll() as $c) $existantes[] = $c['Field']; }
catch (Exception $e) { $log[] = '❌ '.$e->getMessage(); }
$manquantes = array_diff(array_keys($COLS), $existantes);

// Statistiques de remplissage
$stat = array('total'=>0,'avec_irm'=>0,'sans_irm'=>0,'min'=>null,'max'=>null);
if (!$manquantes) {
    try {
        $r = $db->query("SELECT COUNT(*) t,
                                SUM(CASE WHEN irm_speed IS NOT NULL THEN 1 ELSE 0 END) a,
                                MIN(obs_time) mn, MAX(obs_time) mx
                         FROM metar_history")->fetch();
        $stat = array('total'=>(int)$r['t'], 'avec_irm'=>(int)$r['a'],
                      'sans_irm'=>(int)$r['t']-(int)$r['a'], 'min'=>$r['mn'], 'max'=>$r['mx']);
    } catch (Exception $e) {}
}

// ── 1. Création des colonnes ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['create'])) {
    foreach ($manquantes as $col) {
        try { $db->exec("ALTER TABLE metar_history ADD COLUMN `$col` ".$COLS[$col]." AFTER `irm_gust`");
              $log[] = "✅ Colonne « $col » ajoutée."; }
        catch (Exception $e) { $log[] = "❌ $col : ".$e->getMessage(); }
    }
    header('Location: '.basename(__FILE__).'?done=1'); exit;
}

// ── 1bis. Test brut d'une requête IRM (diagnostic) ───────────────────────
$probe = null;
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['probe'])) {
    $ts  = strtotime(($_POST['pdate'] ?? date('Y-m-d')).' 12:00:00 UTC');
    $ctx = stream_context_create(['http'=>['timeout'=>15,'user_agent'=>'casuffit/1.0','ignore_errors'=>true]]);
    $f = gmdate('Y-m-d\TH:i:s\Z', $ts);
    $t = gmdate('Y-m-d\TH:i:s\Z', $ts + 3600);
    $url = 'https://opendata.meteo.be/service/ows?service=WFS&version=2.0.0&request=GetFeature'
         . '&typeName=synop:synop_data&outputFormat=application/json&count=1&sortBy=timestamp+D'
         . '&CQL_FILTER=' . urlencode("code=6451 AND timestamp >= '$f' AND timestamp <= '$t'");
    $raw = @file_get_contents($url, false, $ctx);
    $hdr = isset($http_response_header) ? implode(' | ', array_slice($http_response_header,0,3)) : '(aucun)';
    $json = $raw !== false ? json_decode($raw, true) : null;
    $props = $json['features'][0]['properties'] ?? null;
    $probe = [
        'url'   => $url,
        'http'  => $hdr,
        'ok'    => $raw !== false,
        'len'   => $raw === false ? 0 : strlen($raw),
        'nfeat' => isset($json['features']) ? count($json['features']) : 0,
        'props' => $props,
        'raw'   => $raw === false ? '' : substr($raw, 0, 1500),
    ];
}

// ── 2. Remplissage rétroactif ────────────────────────────────────────────
$fill = null;
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['fill']) && !$manquantes) {
    $d1 = $_POST['d1'] ?? date('Y-m-d', strtotime('-30 days'));
    $d2 = $_POST['d2'] ?? date('Y-m-d');
    $ctx = stream_context_create(['http'=>['timeout'=>60,'user_agent'=>'casuffit/1.0','ignore_errors'=>true]]);

    // UNE SEULE requête WFS pour toute la période (l'IRM limite le nombre d'appels).
    $f = gmdate('Y-m-d\TH:i:s\Z', strtotime($d1.' 00:00:00 UTC'));
    $t = gmdate('Y-m-d\TH:i:s\Z', min(strtotime($d2.' 23:59:59 UTC'), time()));
    $url = 'https://opendata.meteo.be/service/ows?service=WFS&version=2.0.0&request=GetFeature'
         . '&typeName=synop:synop_data&outputFormat=application/json&count=5000&sortBy=timestamp+A'
         . '&CQL_FILTER=' . urlencode("code=6451 AND timestamp >= '$f' AND timestamp <= '$t'");

    $raw = @file_get_contents($url, false, $ctx);

    if ($raw === false) {
        $log[] = "❌ Requête impossible — le serveur n'a pas pu joindre l'IRM.";
    } elseif (stripos($raw, 'Too many data request') !== false || stripos($raw, '<html') === 0) {
        $log[] = "🚫 <strong>Quota IRM dépassé</strong> — le serveur est temporairement bloqué. Réessayez dans une heure.";
    } else {
        $feats = json_decode($raw, true)['features'] ?? null;
        if ($feats === null) {
            $log[] = "❌ Réponse illisible (".strlen($raw)." octets).";
        } elseif (!$feats) {
            $log[] = "ℹ Aucune observation IRM sur cette période.";
        } else {
            // Indexer les mesures IRM par heure UTC
            $par_heure = [];
            foreach ($feats as $ft) {
                $p = $ft['properties'] ?? [];
                if (!isset($p['timestamp']) || !isset($p['wind_speed'])) continue;
                $h = gmdate('Y-m-d H:00:00', strtotime($p['timestamp']));
                $par_heure[$h] = [
                    'dir' => isset($p['wind_direction']) && $p['wind_direction'] !== null
                           ? (int)round((float)$p['wind_direction']) : null,
                    'spd' => round((float)$p['wind_speed'] * 1.94384, 1),
                ];
            }
            // Rapprocher chaque observation METAR de l'heure IRM correspondante
            $sel = $db->prepare("SELECT id, obs_time FROM metar_history
                                 WHERE obs_time >= ? AND obs_time <= ? AND irm_speed IS NULL");
            $sel->execute([gmdate('Y-m-d H:i:s', strtotime($d1.' 00:00:00 UTC')),
                           gmdate('Y-m-d H:i:s', min(strtotime($d2.' 23:59:59 UTC'), time()))]);
            $upd = $db->prepare("UPDATE metar_history SET irm_dir=?, irm_speed=? WHERE id=?");
            $maj = 0; $sans = 0;
            foreach ($sel->fetchAll() as $row) {
                $h = date('Y-m-d H:00:00', strtotime($row['obs_time']));
                if (isset($par_heure[$h])) {
                    $upd->execute([$par_heure[$h]['dir'], $par_heure[$h]['spd'], $row['id']]);
                    $maj++;
                } else { $sans++; }
            }
            $log[] = "✅ <strong>1 requête</strong> a suffi : ".count($feats)." mesure(s) IRM reçue(s), "
                   . count($par_heure)." heure(s) exploitable(s).";
            $log[] = "✅ $maj observation(s) mise(s) à jour" . ($sans ? ", $sans sans correspondance horaire." : ".");
        }
    }
}
if (isset($_GET['done'])) $log[] = "✅ Colonnes créées.";
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><title>Migration IRM — vent</title>
<style>
body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333;margin:0;padding:36px 18px}
.box{max-width:720px;margin:0 auto;background:#fff;border-radius:14px;box-shadow:0 2px 14px rgba(0,0,0,.09);padding:28px 32px;margin-bottom:18px}
h1{font-size:1.2rem;color:#0e3d6b;margin:0 0 6px}.sub{font-size:.84rem;color:#888;margin-bottom:20px}
h2{font-size:.9rem;color:#0e3d6b;margin:0 0 12px;padding-bottom:8px;border-bottom:2px solid #f0f4f8}
table{width:100%;border-collapse:collapse;font-size:.85rem;margin-bottom:16px}
th{text-align:left;padding:8px 10px;background:#f7fafd;color:#0e3d6b;font-size:.7rem;text-transform:uppercase;border-bottom:2px solid #e6eef7}
td{padding:8px 10px;border-bottom:1px solid #f0f4f8}
code{font-family:ui-monospace,Menlo,monospace;font-size:.82rem;background:#f4f7fb;padding:1px 6px;border-radius:4px}
.ok{color:#1a6e3c;font-weight:700}.miss{color:#cc7a00;font-weight:700}
.btn{padding:11px 24px;border:none;border-radius:9px;font-size:.88rem;font-weight:700;cursor:pointer;background:#FF9900;color:#fff;font-family:inherit}
.btn:hover{background:#e08800}.btn:disabled{opacity:.5;cursor:default}
.btn2{background:#1673B2}.btn2:hover{background:#0e5a96}
.log{background:#0e2438;color:#d6e4f0;padding:14px 18px;border-radius:9px;font-size:.82rem;line-height:1.8;margin-bottom:18px}
.warn{background:#fdecea;border:1px solid #f5b7b1;color:#922b21;padding:13px 16px;border-radius:9px;font-size:.82rem;margin-top:18px;line-height:1.6}
.info{background:#eff6ff;border:1px solid #cfe2fb;color:#1673B2;padding:12px 16px;border-radius:9px;font-size:.82rem;line-height:1.6;margin-bottom:16px}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:12px;margin-bottom:16px}
.st{background:#f7fafd;border-radius:10px;padding:13px;text-align:center}
.st .v{font-size:1.4rem;font-weight:800;color:#1673B2}.st .l{font-size:.66rem;color:#999;text-transform:uppercase;margin-top:3px}
.fg{display:inline-flex;flex-direction:column;gap:4px;margin-right:14px;margin-bottom:12px}
.fg label{font-size:.7rem;font-weight:700;text-transform:uppercase;color:#888}
.fg input{padding:8px 12px;border:1.5px solid #cdd8e5;border-radius:8px;font-family:inherit;font-size:.86rem}
a.lnk{color:#1673B2;font-weight:700;text-decoration:none;font-size:.88rem}
</style></head><body>

<div class="box">
  <h1>🌬 Données IRM — direction et vitesse du vent</h1>
  <div class="sub">Complète <code>metar_history</code> avec les mesures de l'IRM (station 6451 Zaventem/Melsbroek).</div>

  <?php if ($log): ?><div class="log"><?php foreach($log as $l) echo htmlspecialchars($l).'<br>'; ?></div><?php endif; ?>

  <div class="info">
    Actuellement, seule la <strong>rafale IRM</strong> (<code>irm_gust</code>) est enregistrée.
    L'IRM publie aussi la <strong>direction</strong> et la <strong>vitesse moyenne</strong> :
    les ajouter permet de croiser deux sources indépendantes (METAR aéroportuaire ↔ IRM officiel),
    ce qui renforce la valeur probatoire des relevés.
  </div>

  <h2>1. Colonnes en base</h2>
  <table>
    <tr><th>Colonne</th><th>Description</th><th>État</th></tr>
    <?php foreach ($COLS as $c => $t): $ok = in_array($c, $existantes); ?>
    <tr><td><code><?= $c ?></code></td>
        <td style="color:#888;font-size:.78rem"><?= $c==='irm_dir' ? 'Direction du vent en degrés' : 'Vitesse moyenne en nœuds' ?></td>
        <td class="<?= $ok?'ok':'miss' ?>"><?= $ok?'✅ présente':'⚠ à créer' ?></td></tr>
    <?php endforeach; ?>
  </table>
  <?php if ($manquantes): ?>
    <form method="POST"><button name="create" value="1" class="btn">🗄 Créer les <?= count($manquantes) ?> colonne(s)</button></form>
  <?php else: ?>
    <p class="ok" style="font-size:.88rem">✅ La table est prête.</p>
  <?php endif; ?>
</div>

<?php if (!$manquantes): ?>
<div class="box">
  <h2>Test d'une requête IRM</h2>
  <p style="font-size:.82rem;color:#888;margin:0 0 12px">
    Avant d'importer, vérifiez que l'IRM répond et voyez les champs réellement disponibles.
  </p>
  <form method="POST">
    <div class="fg"><label>Date à tester</label>
      <input type="date" name="pdate" value="<?= htmlspecialchars($_POST['pdate'] ?? '2026-07-15') ?>"></div>
    <button name="probe" value="1" class="btn btn2">🔬 Tester</button>
  </form>

  <?php if ($probe): ?>
    <table style="margin-top:14px">
      <tr><td>Réponse HTTP</td><td><code><?= htmlspecialchars($probe['http']) ?></code></td></tr>
      <tr><td>Taille reçue</td><td><code><?= number_format($probe['len']) ?> octets</code>
          <?= $probe['ok'] ? '' : ' ❌ requête échouée (réseau bloqué ?)' ?></td></tr>
      <tr><td>Observations trouvées</td><td><code><?= $probe['nfeat'] ?></code></td></tr>
    </table>
    <?php if ($probe['props']): ?>
      <p style="font-size:.82rem;font-weight:700;color:#0e3d6b;margin:14px 0 6px">Champs disponibles :</p>
      <table>
        <?php foreach ($probe['props'] as $k => $v): ?>
        <tr><td><code><?= htmlspecialchars($k) ?></code></td>
            <td><code><?= htmlspecialchars(is_scalar($v) ? (string)$v : json_encode($v)) ?></code>
            <?= in_array($k, ['wind_speed','wind_direction','wind_peak_speed']) ? ' ← <strong>utilisé</strong>' : '' ?></td></tr>
        <?php endforeach; ?>
      </table>
    <?php else: ?>
      <div class="warn" style="margin-top:12px">
        Aucune observation renvoyée pour cette date/heure.
        <?php if ($probe['raw']): ?>
          <details style="margin-top:8px"><summary style="cursor:pointer;font-weight:700">Voir la réponse brute</summary>
          <code style="display:block;margin-top:8px;white-space:pre-wrap;font-size:.72rem"><?= htmlspecialchars($probe['raw']) ?></code></details>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <details style="margin-top:10px"><summary style="cursor:pointer;font-size:.8rem;color:#1673B2;font-weight:700">Voir l'URL interrogée</summary>
      <code style="display:block;margin-top:6px;white-space:pre-wrap;font-size:.7rem"><?= htmlspecialchars($probe['url']) ?></code></details>
  <?php endif; ?>
</div>

<div class="box">
  <h2>2. Remplissage rétroactif de l'historique</h2>

  <div class="stats">
    <div class="st"><div class="v"><?= number_format($stat['total'],0,',',' ') ?></div><div class="l">Observations</div></div>
    <div class="st"><div class="v" style="color:#1a6e3c"><?= number_format($stat['avec_irm'],0,',',' ') ?></div><div class="l">Avec IRM</div></div>
    <div class="st"><div class="v" style="color:#cc7a00"><?= number_format($stat['sans_irm'],0,',',' ') ?></div><div class="l">Sans IRM</div></div>
  </div>

  <?php if ($stat['min']): ?>
    <p style="font-size:.82rem;color:#888;margin-bottom:14px">
      Historique disponible du <?= date('d/m/Y', strtotime($stat['min'])) ?>
      au <?= date('d/m/Y', strtotime($stat['max'])) ?>.
    </p>
  <?php endif; ?>

  <form method="POST">
    <div class="fg"><label>Du</label>
      <input type="date" name="d1" value="<?= htmlspecialchars($_POST['d1'] ?? date('Y-m-d', strtotime('-30 days'))) ?>" required></div>
    <div class="fg"><label>Au</label>
      <input type="date" name="d2" value="<?= htmlspecialchars($_POST['d2'] ?? date('Y-m-d')) ?>" required></div>
    <br>
    <button name="fill" value="1" class="btn btn2">🌬 Récupérer les données IRM</button>
  </form>

  <div class="info" style="margin-top:14px">
    <strong>ℹ Une seule requête</strong> — l'outil interroge l'IRM une fois pour toute la période
    (jusqu'à 5 000 mesures), puis rapproche chaque observation METAR de l'heure IRM correspondante.
    Cela évite le blocage pour dépassement de quota.<br>
    Seules les lignes sans donnée IRM sont complétées : vous pouvez relancer sans risque de doublon.
  </div>
</div>
<?php endif; ?>

<div class="box">
  <a class="lnk" href="/admin/export_vent.php" target="_blank">💨 Ouvrir l'extraction vent →</a>
  <div class="warn"><strong>⚠</strong> Supprimez ce fichier du serveur une fois la migration terminée.</div>
</div>

</body></html>
