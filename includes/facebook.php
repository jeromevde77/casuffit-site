<?php
// includes/facebook.php — Nombre de followers de la page Facebook (Graph API Meta).
//
// L'id/username de la page et le token de page sont saisis dans l'admin
// (Admin → Paramètres → Réseaux sociaux) et stockés dans site_config :
//   facebook_page_id      → id numérique ou username (ex. 'Piste01casuffit')
//   facebook_graph_token  → token de page longue durée (EAAB...)
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
    // $force = true ignore le TTL (utilisé après une sauvegarde dans l'admin).
    function fbFollowers(PDO $db, int $ttl = 21600, bool $force = false): int {  // 21600 s = 6 h
        $cached = (int) cfg('facebook_followers', 0);
        $pageId = trim((string) cfg('facebook_page_id', ''));
        $token  = trim((string) cfg('facebook_graph_token', ''));

        // Page / token non configurés → valeur en cache (souvent 0).
        if ($pageId === '' || $token === '') return $cached;

        // Cache encore frais ?
        $synced = (string) cfg('facebook_followers_synced_at', '');
        if (!$force && $synced !== '' && (time() - strtotime($synced)) < $ttl) {
            return $cached;
        }

        // Marque la tentative tout de suite : un autre visiteur simultané utilisera
        // le cache au lieu de relancer un appel API en parallèle.
        fbSetCfg($db, 'facebook_followers_synced_at', date('Y-m-d H:i:s'));

        // Appel Graph API (timeout court : on ne bloque jamais durablement la page).
        $url = 'https://graph.facebook.com/v19.0/' . rawurlencode($pageId)
             . '?fields=followers_count,fan_count&access_token=' . urlencode($token);
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
