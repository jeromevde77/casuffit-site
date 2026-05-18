<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// Capturer toute erreur fatale via shutdown handler
register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo "<pre>FATAL ERROR:\n";
        print_r($err);
        echo "</pre>";
    }
});

// Include le dashboard tel quel
ob_start();
include __DIR__ . '/dashboard.php';
$content = ob_get_clean();
echo "Dashboard output length: " . strlen($content) . "\n";
echo substr($content, 0, 500);
