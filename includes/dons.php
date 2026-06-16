<?php
// includes/dons.php — Helpers pour les dons anonymes (membre spécial réutilisable)

if (defined('ANON_DON_EMAIL')) return; // déjà chargé
define('ANON_DON_EMAIL', 'dons-anonymes@casuffit.be');
define('ANON_DON_CODE',  'ANONYME');

/**
 * Renvoie l'id du membre spécial « Donateur anonyme », en le créant si besoin.
 * Sert de réceptacle aux dons non attribués (anonymes), comptés dans les totaux
 * et réassignables plus tard à un vrai membre.
 */
function getAnonymousMemberId(PDO $db): int {
    static $id = null;
    if ($id !== null) return $id;
    $q = $db->prepare("SELECT id FROM members WHERE email = ? LIMIT 1");
    $q->execute([ANON_DON_EMAIL]);
    $r = $q->fetchColumn();
    if ($r) { $id = (int)$r; return $id; }
    try {
        $db->prepare("INSERT INTO members (email, prenom, nom, code_membre, statut, newsletter)
                      VALUES (?, ?, ?, ?, 'actif', 0)")
           ->execute([ANON_DON_EMAIL, 'Donateur', 'anonyme', ANON_DON_CODE]);
        $id = (int)$db->lastInsertId();
    } catch (Throwable $e) {
        // course/contrainte : relire
        $q->execute([ANON_DON_EMAIL]);
        $id = (int)$q->fetchColumn();
    }
    return $id;
}

/** Vrai si ce membre est le réceptacle « Donateur anonyme ». */
function isAnonymousMember(PDO $db, int $mid): bool {
    return $mid > 0 && $mid === getAnonymousMemberId($db);
}
