<?php
// ═══════════════════════════════════════════════════════════════════════
//  includes/lang.php — Système multilingue FR / NL
//  Inclus dans index.php avant tout traitement de routing
// ═══════════════════════════════════════════════════════════════════════

// ── Détection / sélection de la langue ──────────────────────────────────
// Priorité :
// 1. URL : /nl/... (préfixe géré par .htaccess via ?lang=nl)
// 2. Changement explicite : ?setlang=fr|nl → enregistre cookie et redirige
// 3. Cookie 'lang' (choix précédent du visiteur)
// 4. Défaut : fr  (PAS de détection Accept-Language à ce stade, choix utilisateur)

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Changement explicite via ?setlang=nl
if (isset($_GET['setlang']) && in_array($_GET['setlang'], ['fr','nl'], true)) {
    $newLang = $_GET['setlang'];
    setcookie('lang', $newLang, [
        'expires'  => time() + 60*60*24*365, // 1 an
        'path'     => '/',
        'secure'   => true,
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
    // Redirection vers la page courante sans le paramètre setlang
    $url = strtok($_SERVER['REQUEST_URI'], '?');
    $qs  = $_GET;
    unset($qs['setlang']);
    if (!empty($qs)) $url .= '?' . http_build_query($qs);
    // Si on bascule vers NL, préfixer /nl/ si pas déjà fait
    if ($newLang === 'nl' && strpos($url, '/nl/') !== 0 && strpos($url, '/nl') !== 0) {
        $url = '/nl' . $url;
    }
    // Si on revient au FR, retirer le préfixe /nl/
    if ($newLang === 'fr' && strpos($url, '/nl/') === 0) {
        $url = substr($url, 3); // retire "/nl"
        if ($url === '') $url = '/';
    }
    header('Location: ' . $url);
    exit;
}

// Détermination effective
$LANG = 'fr';
if (!empty($_GET['lang']) && in_array($_GET['lang'], ['fr','nl'], true)) {
    $LANG = $_GET['lang'];
} elseif (!empty($_COOKIE['lang']) && in_array($_COOKIE['lang'], ['fr','nl'], true)) {
    $LANG = $_COOKIE['lang'];
}

define('LANG', $LANG);

// ── Helpers ─────────────────────────────────────────────────────────────

/**
 * Retourne la langue actuelle ('fr' ou 'nl')
 */
function lang(): string {
    return LANG;
}

/**
 * Retourne le champ traduit d'une ligne de BDD (avec fallback FR)
 *
 * Usage : tdb($row, 'titre')   →  si lang=nl et titre_nl non vide → titre_nl
 *                                 sinon → titre
 *
 * @param array  $row    Ligne SQL (associative)
 * @param string $field  Nom du champ FR (ex: 'titre', 'contenu')
 * @return string|null
 */
function tdb(array $row, string $field) {
    if (LANG === 'nl') {
        $nlField = $field . '_nl';
        if (!empty($row[$nlField])) {
            return $row[$nlField];
        }
    }
    return $row[$field] ?? null;
}

/**
 * Retourne une chaîne traduite depuis lang/fr.php ou lang/nl.php
 *
 * Usage : t('home.welcome')  →  "Bienvenue" ou "Welkom"
 *
 * @param string $key      Clé hiérarchique (ex: 'menu.donate')
 * @param array  $params   Substitutions {key} dans la chaîne
 * @return string
 */
function t(string $key, array $params = []): string {
    static $cache = [];
    $L = LANG;
    if (!isset($cache[$L])) {
        $file = __DIR__ . '/../lang/' . $L . '.php';
        $cache[$L] = file_exists($file) ? (include $file) : [];
        // Fallback FR si NL manque
        if ($L === 'nl' && !isset($cache['fr'])) {
            $cache['fr'] = include __DIR__ . '/../lang/fr.php';
        }
    }
    $value = $cache[$L][$key] ?? null;
    if ($value === null && $L === 'nl') {
        $value = $cache['fr'][$key] ?? $key; // fallback FR
    }
    if ($value === null) {
        $value = $key; // dernier recours : afficher la clé
    }
    foreach ($params as $k => $v) {
        $value = str_replace('{' . $k . '}', $v, $value);
    }
    return $value;
}

/**
 * Construit une URL en gardant la langue courante.
 * Usage : url('/?page=mobilisation')  →  '/nl/?page=mobilisation' si lang=nl
 */
function urlLang(string $path): string {
    if (LANG === 'nl') {
        // Si le path commence par '/?' ou '/' simple, préfixer /nl
        if ($path[0] === '/' && strpos($path, '/nl') !== 0) {
            return '/nl' . $path;
        }
    }
    return $path;
}

/**
 * Lit la valeur traduite d'une clé de site_config
 * Wrapper autour de cfg() qui gère le fallback NL→FR
 */
function cfgLang(string $cle, string $default = ''): string {
    global $db;
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach ($db->query("SELECT cle, valeur, valeur_nl FROM site_config") as $r) {
                $cache[$r['cle']] = $r;
            }
        } catch (Throwable $e) {
            // Colonne valeur_nl absente — recharger sans elle
            try {
                foreach ($db->query("SELECT cle, valeur FROM site_config") as $r) {
                    $cache[$r['cle']] = array('valeur' => $r['valeur'], 'valeur_nl' => null);
                }
            } catch (Throwable $e2) { /* table absente */ }
        }
    }
    if (!isset($cache[$cle])) return $default;
    if (LANG === 'nl' && !empty($cache[$cle]['valeur_nl'])) {
        return $cache[$cle]['valeur_nl'];
    }
    return $cache[$cle]['valeur'] ?? $default;
}
