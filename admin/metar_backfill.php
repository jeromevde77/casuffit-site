<?php
require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();

$days = min((int)($_GET['days'] ?? 30), 365);
?><!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">
<title>Backfill METAR — Admin</title>
<style>
body{font-family:monospace;background:#0e1a2a;color:#7ef;padding:24px;margin:0}
h2{color:#FF9900}
a{color:#FF9900}
.form{margin:16px 0;display:flex;align-items:center;gap:10px;flex-wrap:wrap}
select,button{padding:6px 12px;border-radius:5px;border:none;font-size:.85rem;cursor:pointer}
select{background:#1a2a3a;color:#7ef;border:1px solid #335}
.btn-pri{background:#1673B2;color:#fff;font-weight:700}
.btn-sec{background:#2a3a4a;color:#7ef}
#log{background:#1a2a3a;padding:14px;border-radius:6px;font-size:.78rem;line-height:1.7;
     min-height:200px;max-height:65vh;overflow-y:auto;white-space:pre-wrap;margin-top:16px}
.ok{color:#2ecc71}.err{color:#e74c3c}.info{color:#FF9900}.skip{color:#556}
#progress{color:#aac;font-size:.8rem;margin-top:8px}
.bar{background:#1a2a3a;border-radius:4px;height:8px;margin-top:8px;overflow:hidden}
.bar-fill{background:#1673B2;height:100%;transition:width .3s;width:0}
</style></head><body>
<h2>📥 Backfill historique METAR + IRM</h2>
<p>← <a href="/admin/">Admin</a></p>

<div class="form">
  <label>Période :
    <select id="sel-days">
      <option value="7">7 jours</option>
      <option value="30" <?= $days==30?'selected':'' ?>>30 jours</option>
      <option value="90" <?= $days==90?'selected':'' ?>>90 jours</option>
      <option value="180">6 mois</option>
      <option value="365">1 an</option>
    </select>
  </label>
  <button class="btn-pri" onclick="run(false)">▶ Lancer</button>
  <button class="btn-sec" onclick="run(true)">🔍 Simuler</button>
  <button class="btn-sec" id="btn-stop" onclick="stop()" style="display:none;background:#8B0000;color:#fff">⏹ Arrêter</button>
</div>
<div class="bar"><div class="bar-fill" id="bar-fill"></div></div>
<div id="progress"></div>
<div id="log">En attente…</div>

<script>
var running = false;

function stop() { running = false; }

function run(dry) {
  var days  = +document.getElementById('sel-days').value;
  var log   = document.getElementById('log');
  var prog  = document.getElementById('progress');
  var bar   = document.getElementById('bar-fill');
  var btnStop = document.getElementById('btn-stop');

  log.innerHTML = '';
  prog.textContent = 'Préparation…';
  bar.style.width = '0%';
  btnStop.style.display = 'inline';
  running = true;

  // Construire la liste des jours à traiter
  var dates = [];
  for (var i = days; i >= 0; i--) {
    var d = new Date();
    d.setUTCDate(d.getUTCDate() - i);
    dates.push(d.toISOString().slice(0, 10));
  }

  var total = dates.length, done = 0, savedTotal = 0, skippedTotal = 0;

  function next() {
    if (!running || done >= total) {
      btnStop.style.display = 'none';
      running = false;
      var sym = dry ? '🔍 SIMULATION' : '✅ TERMINÉ';
      log.innerHTML += '\n<span class="ok">' + sym + ' — ' + savedTotal + ' sauvés / ' + skippedTotal + ' ignorés</span>';
      log.scrollTop = log.scrollHeight;
      prog.textContent = '';
      bar.style.width = '100%';
      return;
    }

    var date = dates[done];
    prog.textContent = date + ' (' + (done+1) + '/' + total + ')';
    bar.style.width = Math.round((done / total) * 100) + '%';

    fetch('/admin/metar_backfill_day.php?date=' + date + (dry ? '&dry=1' : ''), {credentials: 'include'})
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (d.error) {
          log.innerHTML += '<span class="err">❌ ' + date + ' — ' + d.error + '</span>\n';
        } else {
          var s = d.saved, sk = d.skipped;
          savedTotal   += s;
          skippedTotal += sk;
          if (d.records && d.records.length > 0) {
            log.innerHTML += '<span class="info">── ' + date + ' (' + d.records.length + ' METARs)</span>\n';
            d.records.forEach(function(r) {
              var irm = r.irm ? r.irm + 'kt' : '-';
              var cls = r.prs == 0 ? 'err' : 'ok';
              log.innerHTML += '<span class="' + cls + '">  ' + r.time + 'Z  wd=' + (r.wd||'VRB') + '° ws=' + r.ws + 'kt wg=' + (r.wg||'-') + 'kt irm=' + irm + ' prs=' + r.prs + '</span>\n';
            });
            if (!dry) {
              log.innerHTML += '<span class="skip">  → ' + s + ' sauvés, ' + sk + ' ignorés</span>\n';
            }
          } else {
            log.innerHTML += '<span class="skip">── ' + date + ' — aucun METAR</span>\n';
          }
        }
        log.scrollTop = log.scrollHeight;
        done++;
        setTimeout(next, 300); // pause entre jours
      })
      .catch(function(e) {
        log.innerHTML += '<span class="err">❌ ' + date + ' — erreur réseau</span>\n';
        done++;
        setTimeout(next, 500);
      });
  }

  next();
}
</script>
</body></html>
