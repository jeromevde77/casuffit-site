<?php
// api/aip_planning.php — Config PRS attendue selon AIP EBBR AD 2.21 AMDT 10/2013
// Retourne la configuration préférentielle attendue pour une date/heure donnée

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

/**
 * Retourne la configuration PRS attendue selon le planning AIP 2013
 * @param int $ts timestamp UTC
 * @return array ['dep'=>[], 'arr'=>[], 'label'=>string, 'plage'=>string, 'notes'=>[]]
 */
function getAipConfig($ts) {
    // Heure locale belge (CET/CEST) — utiliser DateTimeZone explicitement
    $tz   = new DateTimeZone('Europe/Brussels');
    $dt   = new DateTime('@' . $ts);
    $dt->setTimezone($tz);

    $dow   = (int)$dt->format('N');   // 1=lun, 7=dim
    $hour  = (int)$dt->format('G');   // heure locale 0-23
    $heure_be = $dt->format('H:i');

    // Plage horaire
    if ($hour >= 5 && $hour < 15)       $plage = '0500-1459';
    elseif ($hour >= 15 && $hour < 22)  $plage = '1500-2159';
    else                                 $plage = '2200-0459';

    // Planning selon AIP EBBR AD 2.EBBR-17, AMDT 10/2013
    // Notes :
    // (1) RWY 25R pour traffic via ELSIK/NIK/HELEN/DENUT/KOK/CIV, RWY 19 via LNO/SPI/SOPOK/PITES/ROUSY
    // (2) Arrivée RWY 25L à discrétion ATC
    // (3) Pas de slot décollage entre 0000-0500
    // (4) Pas de slot décollage entre 2300-0500

    $configs = [
        // Lun (1) à Ven (5)
        1 => [
            '0500-1459' => ['dep'=>['25R'],       'arr'=>['25L','25R'], 'notes'=>[]],
            '1500-2159' => ['dep'=>['25R'],       'arr'=>['25L','25R'], 'notes'=>[]],
            '2200-0459' => ['dep'=>['25R','19'],  'arr'=>['25R','25L'], 'notes'=>[1,2]],
        ],
        2 => [
            '0500-1459' => ['dep'=>['25R'],       'arr'=>['25L','25R'], 'notes'=>[]],
            '1500-2159' => ['dep'=>['25R'],       'arr'=>['25L','25R'], 'notes'=>[]],
            '2200-0459' => ['dep'=>['25R','19'],  'arr'=>['25R','25L'], 'notes'=>[1,2]],
        ],
        3 => [
            '0500-1459' => ['dep'=>['25R'],       'arr'=>['25L','25R'], 'notes'=>[]],
            '1500-2159' => ['dep'=>['25R'],       'arr'=>['25L','25R'], 'notes'=>[]],
            '2200-0459' => ['dep'=>['25R','19'],  'arr'=>['25R','25L'], 'notes'=>[1,2]],
        ],
        4 => [
            '0500-1459' => ['dep'=>['25R'],       'arr'=>['25L','25R'], 'notes'=>[]],
            '1500-2159' => ['dep'=>['25R'],       'arr'=>['25L','25R'], 'notes'=>[]],
            '2200-0459' => ['dep'=>['25R','19'],  'arr'=>['25R','25L'], 'notes'=>[1,2]],
        ],
        5 => [ // Vendredi
            '0500-1459' => ['dep'=>['25R'],       'arr'=>['25L','25R'], 'notes'=>[]],
            '1500-2159' => ['dep'=>['25R'],       'arr'=>['25L','25R'], 'notes'=>[]],
            '2200-0459' => ['dep'=>['25R'],       'arr'=>['25R'],       'notes'=>[3]],
        ],
        6 => [ // Samedi
            '0500-1459' => ['dep'=>['25R'],       'arr'=>['25L','25R'], 'notes'=>[]],
            '1500-2159' => ['dep'=>['25R','19'],  'arr'=>['25R','25L'], 'notes'=>[1,2]],
            '2200-0459' => ['dep'=>['25L'],       'arr'=>['25L'],       'notes'=>[4]],
        ],
        7 => [ // Dimanche
            '0500-1459' => ['dep'=>['25R','19'],  'arr'=>['25R','25L'], 'notes'=>[1,2]],
            '1500-2159' => ['dep'=>['25R'],       'arr'=>['25L','25R'], 'notes'=>[]],
            '2200-0459' => ['dep'=>['19'],        'arr'=>['19'],        'notes'=>[4]],
        ],
    ];

    $jours = ['','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'];
    $cfg   = $configs[$dow][$plage];

    $note_texts = [
        1 => '25R pour trafic ELSIK/NIK/HELEN/DENUT/KOK/CIV · 19 pour trafic LNO/SPI/SOPOK/PITES/ROUSY',
        2 => 'Arrivée 25L à discrétion ATC',
        3 => 'Pas de slot décollage entre 0000–0500',
        4 => 'Pas de slot décollage entre 2300–0500',
    ];

    $notes_actives = [];
    foreach ($cfg['notes'] as $n) {
        $notes_actives[] = $note_texts[$n];
    }

    return [
        'jour'      => $jours[$dow],
        'dow'       => $dow,
        'heure_be'  => $heure_be,
        'plage'     => $plage,
        'dep'       => $cfg['dep'],
        'arr'       => $cfg['arr'],
        'label_dep' => implode(' / ', $cfg['dep']),
        'label_arr' => implode(' / ', $cfg['arr']),
        'notes'     => $notes_actives,
        // Config combinée pour comparaison avec BATC
        'config_label' => 'DEP: '.implode('+', $cfg['dep']).' · ARR: '.implode('+', $cfg['arr']),
        // Est-ce une config préférentielle pure (toutes pistes 25) ?
        'is_pref_pure' => !in_array('19', $cfg['dep']) && !in_array('19', $cfg['arr']),
    ];
}

// Ne produire de sortie que si appelé directement (pas via require/include)
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    $ts = isset($_GET['ts']) ? (int)$_GET['ts'] : time();
    $date = isset($_GET['date']) ? $_GET['date'] : null;
    if ($date) $ts = strtotime($date);

    $start = isset($_GET['start']) ? strtotime($_GET['start']) : null;
    $end   = isset($_GET['end'])   ? strtotime($_GET['end'])   : null;

    if ($start && $end) {
        $results = [];
        $t = $start;
        while ($t <= $end) {
            $results[] = array_merge(['ts' => $t, 'time_utc' => date('Y-m-d\TH:i:s\Z', $t)], getAipConfig($t));
            $t += 3600;
        }
        echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } else {
        echo json_encode(getAipConfig($ts), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
