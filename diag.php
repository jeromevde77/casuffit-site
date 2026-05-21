<?php
header('Content-Type: text/plain');
echo "DIAG OK " . date('H:i:s') . "\n";
require_once __DIR__ . '/config.php';
echo "config: ok\n";
echo "OPENSKY_CLIENT_ID: " . (defined('OPENSKY_CLIENT_ID') && OPENSKY_CLIENT_ID ? 'OUI' : 'NON') . "\n";
echo "CRON_SECRET: " . (defined('CRON_SECRET') ? 'OUI' : 'NON') . "\n";
try { getDB()->query("SELECT 1 FROM ebbr_runway_tracks LIMIT 1"); echo "table: OK\n"; }
catch (Exception $e) { echo "table: MANQUE\n"; }
