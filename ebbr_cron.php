<?php
// ebbr_cron.php — Point d'entrée public pour la collecte des traces EBBR
header('Content-Type: text/plain; charset=utf-8');

// Capturer les erreurs fatales pour diagnostic
register_shutdown_function(function() {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo "\n\n*** ERREUR FATALE ***\n";
        echo $e['message'] . "\n";
        echo "Fichier: " . $e['file'] . " ligne " . $e['line'] . "\n";
    }
});

require_once __DIR__ . '/cron/ebbr_tracks.php';
