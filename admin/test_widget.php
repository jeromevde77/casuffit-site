<?php
require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();
echo '<h1>OK - Admin authentifié</h1>';
echo '<pre>Session: '; print_r($_SESSION); echo '</pre>';
