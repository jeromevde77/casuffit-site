<?php // reset-sw.php — Désinstalle tous les service workers et vide les caches ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Réinitialisation cache</title>
<style>
body{font-family:sans-serif;max-width:600px;margin:40px auto;padding:0 20px;line-height:1.6;text-align:center}
.box{background:#f0f4f8;border-radius:12px;padding:24px;margin:20px 0}
.ok{color:#27ae60;font-weight:700}
.btn{display:inline-block;background:#1673B2;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:700;margin-top:16px}
#log{text-align:left;font-size:.85rem;color:#666;background:#fff;border-radius:8px;padding:14px;margin-top:16px;font-family:monospace}
</style>
</head>
<body>
<h2>🔄 Réinitialisation du cache</h2>
<div class="box">
  <p id="status">Nettoyage en cours…</p>
  <div id="log"></div>
</div>
<a href="/admin/dashboard.php" class="btn" id="continue" style="display:none">→ Retour au dashboard</a>

<script>
var log = document.getElementById('log');
function addLog(msg) { log.innerHTML += msg + '<br>'; }

async function reset() {
  // 1. Désinstaller tous les service workers
  if ('serviceWorker' in navigator) {
    var regs = await navigator.serviceWorker.getRegistrations();
    addLog('Service workers trouvés : ' + regs.length);
    for (var reg of regs) {
      await reg.unregister();
      addLog('✓ Désinstallé : ' + (reg.scope || '?'));
    }
  } else {
    addLog('Pas de support service worker');
  }

  // 2. Vider tous les caches
  if ('caches' in window) {
    var keys = await caches.keys();
    addLog('Caches trouvés : ' + keys.length);
    for (var key of keys) {
      await caches.delete(key);
      addLog('✓ Cache vidé : ' + key);
    }
  }

  document.getElementById('status').innerHTML = '<span class="ok">✅ Terminé ! Le service worker et tous les caches ont été supprimés.</span>';
  document.getElementById('continue').style.display = 'inline-block';
}

reset().catch(function(e){ addLog('Erreur : ' + e.message); });
</script>
</body>
</html>
