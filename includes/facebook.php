<?php
// includes/facebook.php — Nombre de followers de la page Facebook (Graph API Meta).
//
// Le token de page et l'id/username de la page sont définis dans config.php :
//   define('FB_PAGE_ID',     'Piste01casuffit');           // id numérique ou username
//   define('FB_GRAPH_TOKEN', 'EAAB...');                   // token de page longue durée
//
// La valeur est mise en cache dans site_config (clé 'facebook_followers') et
// rafraîchie au plus une fois par TTL (déclenché paresseusement au chargement
// d'une page) afin de ne jamais bloquer l'affichage public.

if (!function_exists('fbSetCfg')) {
    function fbSetCfg(PDO $db, string $cle, string $val): void {
        try {
            $db->prepare("INSERT INTO site_config (cle, valeur, groupe) VALUES (?, ?, 'reseaux')
                          ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)")
               ->execute([$cle, $val]);
        } catch (Throwable $e) { /* silencieux : le cache reste sur l'ancienne valeur */ }
    }
}

if (!function_exists('fbFollowers')) {
    // Renvoie le nombre de followers (0 si indisponible / non configuré).
    function fbFollowers(PDO $db, int $ttl = 21600): int {  // 21600 s = 6 h
        $cached = (int) cfg('facebook_followers', 0);

        // Pas de token / page configuré → on se contente de la valeur en cache.
        if (!defined('FB_GRAPH_TOKEN') || FB_GRAPH_TOKEN === '' ||
            !defined('FB_PAGE_ID')     || FB_PAGE_ID === '') {
            return $cached;
        }

        // Cache encore frais ?
        $synced = (string) cfg('facebook_followers_synced_at', '');
        if ($synced !== '' && (time() - strtotime($synced)) < $ttl) {
            return $cached;
        }

        // Marque la tentative tout de suite : un autre visiteur simultané utilisera
        // le cache au lieu de relancer un appel API en parallèle.
        fbSetCfg($db, 'facebook_followers_synced_at', date('Y-m-d H:i:s'));

        // Appel Graph API (timeout court : on ne bloque jamais durablement la page).
        $url = 'https://graph.facebook.com/v19.0/' . rawurlencode(FB_PAGE_ID)
             . '?fields=followers_count,fan_count&access_token=' . urlencode(FB_GRAPH_TOKEN);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 4,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp === false || $code !== 200) return $cached;
        $data = json_decode($resp, true);
        if (!is_array($data)) return $cached;

        // followers_count (abonnés) prioritaire, sinon fan_count (mentions « J'aime »).
        $n = (int) ($data['followers_count'] ?? $data['fan_count'] ?? 0);
        if ($n > 0) {
            fbSetCfg($db, 'facebook_followers', (string) $n);
            return $n;
        }
        return $cached;
    }
}
