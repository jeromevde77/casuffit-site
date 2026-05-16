<?php
// api/metar_stats.php — Statistiques historiques METAR depuis metar_history
// Paramètres GET :
//   ?period=7d|30d|90d|365d|all   (défaut: 30d)
//   ?view=daily|monthly|raw       (défaut: daily)

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=1800'); // 30 min cache

define('ROOT', dirname(__DIR__));
require_once ROOT . '/config.php';

$period = $_GET['period'] ?? '30d';
$view   = $_GET['view']   ?? 'daily';

// ── Connexion BDD ─────────────────────────────────────────────────────────
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    echo json_encode(['error' => 'BDD unavailable']); exit;
}

// ── Calcul de la fenêtre temporelle ──────────────────────────────────────
$since = match($period) {
    '7d'   => date('Y-m-d H:i:s', strtotime('-7 days')),
    '30d'  => date('Y-m-d H:i:s', strtotime('-30 days')),
    '90d'  => date('Y-m-d H:i:s', strtotime('-90 days')),
    '365d' => date('Y-m-d H:i:s', strtotime('-365 days')),
    'all'  => '2000-01-01 00:00:00',
    default=> date('Y-m-d H:i:s', strtotime('-30 days')),
};

// ── Vue : stats quotidiennes ──────────────────────────────────────────────
if ($view === 'daily') {
    $sql = "SELECT
        DATE(obs_time)                          AS day,
        COUNT(*)                                AS records,
        ROUND(AVG(wind_speed), 1)               AS avg_speed,
        MAX(wind_speed)                         AS max_speed,
        MAX(COALESCE(wind_gust, wind_speed))    AS max_gust,
        ROUND(AVG(wind_dir))                    AS avg_dir,
        SUM(prs_active)                         AS prs_count,
        SUM(prs_2013)                           AS prs_2013_count,
        GROUP_CONCAT(DISTINCT runways ORDER BY runways SEPARATOR '|') AS all_runways
      FROM metar_history
      WHERE obs_time >= :since
      GROUP BY DATE(obs_time)
      ORDER BY day ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':since' => $since]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculer % jours PRS
    $total_days  = count($rows);
    $prs_days    = 0;
    $prs13_days  = 0;
    foreach ($rows as $r) {
        if ($r['prs_count'] > 0)     $prs_days++;
        if ($r['prs_2013_count'] > 0) $prs13_days++;
    }

    echo json_encode([
        'period'       => $period,
        'view'         => 'daily',
        'since'        => $since,
        'total_days'   => $total_days,
        'prs_days'     => $prs_days,
        'prs_days_pct' => $total_days > 0 ? round($prs_days / $total_days * 100, 1) : null,
        'prs13_days'   => $prs13_days,
        'prs13_days_pct' => $total_days > 0 ? round($prs13_days / $total_days * 100, 1) : null,
        'data'         => $rows,
    ], JSON_UNESCAPED_UNICODE);

// ── Vue : stats mensuelles ────────────────────────────────────────────────
} elseif ($view === 'monthly') {
    $sql = "SELECT
        DATE_FORMAT(obs_time, '%Y-%m')          AS month,
        COUNT(DISTINCT DATE(obs_time))          AS days,
        COUNT(*)                                AS records,
        ROUND(AVG(wind_speed), 1)               AS avg_speed,
        MAX(COALESCE(wind_gust, wind_speed))    AS max_gust,
        SUM(prs_active)                         AS prs_records,
        COUNT(DISTINCT CASE WHEN prs_active=1 THEN DATE(obs_time) END) AS prs_days,
        SUM(prs_2013)                           AS prs13_records,
        COUNT(DISTINCT CASE WHEN prs_2013=1  THEN DATE(obs_time) END) AS prs13_days
      FROM metar_history
      WHERE obs_time >= :since
      GROUP BY DATE_FORMAT(obs_time, '%Y-%m')
      ORDER BY month ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':since' => $since]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'period' => $period,
        'view'   => 'monthly',
        'since'  => $since,
        'data'   => $rows,
    ], JSON_UNESCAPED_UNICODE);

// ── Vue : données brutes (pour graphiques wind rose, etc.) ────────────────
} elseif ($view === 'raw') {
    $limit = min((int)($_GET['limit'] ?? 500), 5000);
    $sql = "SELECT obs_time, wind_dir, wind_speed, wind_gust, wind_variable,
                   runways, prs_active, prs_2013, tw_25, xw_25
              FROM metar_history
             WHERE obs_time >= :since
             ORDER BY obs_time ASC
             LIMIT :limit";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':since', $since);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'period' => $period,
        'view'   => 'raw',
        'since'  => $since,
        'count'  => count($rows),
        'data'   => $rows,
    ], JSON_UNESCAPED_UNICODE);

} else {
    echo json_encode(['error' => 'Vue inconnue. Utiliser: daily, monthly, raw']);
}
