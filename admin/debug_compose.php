<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo "<pre>FATAL:\n";
        print_r($err);
        echo "</pre>";
    }
});
ob_start();
include __DIR__ . '/compose.php';
$content = ob_get_clean();
echo "Length: " . strlen($content) . "\n";
echo substr($content, 0, 500);
