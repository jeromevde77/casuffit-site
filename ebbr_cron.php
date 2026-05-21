<?php
// ebbr_cron.php — Point d'entrée public pour la collecte des traces EBBR
// URL cron-job.org : https://www.casuffit.be/ebbr_cron.php?secret=XXX
// Le script gère la reprise : relancez-le plusieurs fois pour compléter une journée.
require_once __DIR__ . '/cron/ebbr_tracks.php';
