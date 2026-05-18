<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/functions.php';
session_start();
echo "config ok\n";
require_once __DIR__ . '/lang.php';
echo "lang ok, LANG=$LANG\n";
echo "tm test: " . tm('dashboard_page') . "\n";
echo "tm test2: " . tm('tab_dons') . "\n";
$db = getDB();
echo "db ok\n";
echo "✅ Tout OK\n";
