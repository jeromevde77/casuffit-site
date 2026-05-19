<?php
/**
 * CSRF Protection — Helper centralisé
 *
 * Usage :
 *   1. require_once 'includes/csrf.php';     // après session_start()
 *   2. Dans les formulaires :
 *        <?= csrf_field() ?>
 *   3. À l'entrée des handlers POST :
 *        csrf_verify();   // throw 403 si invalide
 *
 * Le token est lié à la session et regénéré au login.
 */

if (!function_exists('csrf_token')) {

    /**
     * Retourne le token CSRF de la session (le crée si absent).
     */
    function csrf_token(): string {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            // Pas de session → pas de protection possible
            return '';
        }
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    /**
     * Champ HTML à insérer dans les formulaires POST.
     */
    function csrf_field(): string {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token()) . '">';
    }

    /**
     * Vérifie le token du POST courant. Termine avec 403 si invalide.
     * À appeler AU DÉBUT de tout handler POST sensible.
     */
    function csrf_verify(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
        $submitted = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $expected  = $_SESSION['_csrf_token'] ?? '';
        if (!$expected || !$submitted || !hash_equals($expected, $submitted)) {
            http_response_code(403);
            // Réponse différente selon le contexte (API JSON vs HTML)
            $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
            if (strpos($accept, 'application/json') !== false || isset($_POST['_ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'error' => 'CSRF token invalide']);
            } else {
                header('Content-Type: text/html; charset=utf-8');
                echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>403</title></head><body style="font-family:Arial;padding:40px;text-align:center"><h1>403 — Action non autorisée</h1><p>Le jeton de sécurité est invalide ou expiré.</p><p><a href="javascript:history.back()">← Retour</a></p></body></html>';
            }
            exit;
        }
    }

    /**
     * Régénère le token (à appeler après login/logout pour invalider l'ancien).
     */
    function csrf_regenerate(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
    }
}
