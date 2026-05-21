<?php
// ebbr_cron.php — Point d'entrée public pour la collecte des traces EBBR
// Redirige vers cron/ebbr_tracks.php en interne
// Accessible : https://www.casuffit.be/ebbr_cron.php?secret=XXX

require_once __DIR__ . '/cron/ebbr_tracks.php';
