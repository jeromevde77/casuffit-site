<?php
require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();
header('Location: dashboard.php');
exit;
