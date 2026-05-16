<?php
require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();

define('ROOT', dirname(__DIR__));

require_once ROOT . '/cron/save_metar.php';

$logfile = ROOT . '/cron/save_metar.log';
$lines = file_exists($logfile) ? array_slice(file($logfile, FILE_IGNORE_NEW_LINES), -15) : ['(log vide)'];
?><!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>Cron METAR</title>
<style>body{font-family:monospace;background:#0e1a2a;color:#7ef;padding:24px}
h2{color:#FF9900}pre{background:#1a2a3a;padding:14px;border-radius:6px;white-space:pre-wrap}
.ok{color:#2ecc71}.err{color:#e74c3c}a{color:#FF9900}</style></head><body>
<h2>✅ Cron exécuté</h2>
<p>← <a href="/admin/">Admin</a> · <a href="run_cron.php">↻ Relancer</a></p>
<pre><?php foreach($lines as $l){
  $l=htmlspecialchars(rtrim($l));
  echo '<span class="'.(str_contains($l,'ERREUR')?'err':'ok').'">'.$l."</span>\n";
}?></pre>
</body></html>
