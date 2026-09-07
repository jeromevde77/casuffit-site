<?php
/* outils-publier-presse.php — v1
 * Outil ponctuel : ajoute 2 actualites "revue de presse" (La Libre + DH du 29-30/08/2026)
 * avec lien vers le PDF de la page parue (deposé dans /assets/docs/).
 * Idempotent : relancer met a jour au lieu de dupliquer.
 * Protege par requireAdmin(). A SUPPRIMER apres usage (voir CLAUDE.md).
 */
require_once __DIR__ . '/config.php';
session_start();
requireAdmin();
$db  = getDB();
$url = defined('SITE_URL') ? SITE_URL : 'https://www.casuffit.be';

function pdf_box(string $href, string $label): string {
    return '<p style="text-align:center;margin:26px 0">'
         . '<a href="' . $href . '" target="_blank" rel="noopener" '
         . 'style="display:inline-block;background:#1673B2;color:#fff;font-weight:800;padding:14px 30px;'
         . 'border-radius:8px;text-decoration:none;font-size:1rem">' . $label . '</a></p>';
}

$articles = [];

// ── 1. La Libre Belgique ─────────────────────────────────────────────────
$articles[] = [
    'titre'    => "La Libre : le plan de Jean-Luc Crucke pour sortir du bourbier du survol",
    'accroche' => "La Libre Belgique (29-30 août 2026) détaille la note stratégique du ministre de la Mobilité : "
                . "maximiser l'usage des pistes préférentielles 25R/25L et réviser l'arrêté sur les normes de vent. "
                . "Une décision est attendue au plus tard le 1er octobre.",
    'date'     => '2026-08-29 08:00:00',
    'contenu'  => '<p style="font-size:.85rem;color:#666;margin-bottom:18px"><strong>REVUE DE PRESSE</strong><br>'
                . 'La Libre Belgique — samedi 29 et dimanche 30 août 2026 — article d\'Adrien de Marneffe</p>'

                . '<p>Sous le titre <em>« Survol de Bruxelles : le plan de Jean-Luc Crucke pour sortir du bourbier '
                . 'politique »</em>, La Libre consacre une double page au dossier et détaille la note stratégique '
                . 'déposée par le cabinet du ministre de la Mobilité en vue d\'un accord politique.</p>'

                . '<h3>Ce que prévoit la note</h3>'
                . '<p>L\'objectif annoncé est triple : <strong>maximiser l\'usage du système de pistes préférentiel</strong> '
                . '(PRS, pistes 25R et 25L), <strong>objectiver le choix des autres pistes</strong> (dont la R07 et la R01) '
                . 'et <strong>renforcer les contrôles</strong>. Concrètement, il ne s\'agit pas de stopper l\'utilisation '
                . 'de la R07, mais d\'en réduire l\'usage en révisant l\'arrêté sur les <strong>normes de vent</strong>. '
                . 'Le ministre situe l\'échéance clairement : <em>« Compte tenu du calendrier judiciaire, une décision '
                . 'doit être prise au plus tard le 1er octobre. »</em></p>'

                . '<h3>Le nœud : quelle instruction Skeyes applique-t-il ?</h3>'
                . '<p>L\'article confirme ce que nous répétons depuis des années. Philippe Touwaide, analyste en '
                . 'transport et ancien médiateur fédéral, y souligne que <em>« le problème ne vient pas tant de la route '
                . 'en elle-même que de son usage excessif »</em>, et pointe que Skeyes continue d\'appliquer une '
                . 'instruction de <strong>décembre 2013</strong> plutôt que celle du <strong>17 juillet 2013</strong>. '
                . 'Or, selon lui, si l\'instruction du 17 juillet 2013 était appliquée, l\'opérateur serait contraint '
                . 'de renvoyer une partie du trafic vers la 25R — les atterrissages se faisant alors au-dessus de champs '
                . 'et de prairies — et <strong>la R01 serait donc aussi moins utilisée</strong>.</p>'

                . '<p>Skeyes répond appliquer « strictement la dernière instruction ministérielle en vigueur » et estime '
                . 'qu\'il ne lui appartient pas d\'arbitrer entre des décisions de justice aux interprétations divergentes. '
                . 'L\'article relève par ailleurs un facteur climatique structurel : une baisse des vents du nord au profit '
                . 'des vents d\'est, qui augmente mécaniquement la sollicitation de la piste 07.</p>'

                . '<h3>Les obstacles</h3>'
                . '<p>L\'État belge cumule <strong>plus de 45 millions d\'euros d\'astreintes depuis 2020</strong>. '
                . 'Une étude d\'incidences du bureau CGX valide l\'existence d\'approches courbes alternatives pour la 07, '
                . 'compatibles avec les exigences européennes de sécurité. Restent les réticences des partis flamands face '
                . 'à tout report de nuisances, et l\'échéance du renouvellement du permis de Brussels Airport en 2028.</p>'

                . pdf_box('/assets/docs/2026-08-29-la-libre-plan-crucke.pdf', 'Lire la page parue (PDF)')

                . '<p style="font-size:.8rem;color:#888;text-align:center">Source : La Libre Belgique, '
                . 'éd. des 29-30 août 2026 (Adrien de Marneffe).</p>',
];

// ── 2. La Dernière Heure ─────────────────────────────────────────────────
$articles[] = [
    'titre'    => "La DH : Waterloo s'immisce dans l'action en justice bruxelloise",
    'accroche' => "« La santé des Brabançons wallons ne vaut pas moins que celle des Bruxellois. » La Dernière Heure "
                . "(29-30 août 2026) consacre une double page à l'entrée de la commune de Waterloo et de notre ASBL "
                . "dans l'action en cessation environnementale.",
    'date'     => '2026-08-29 09:00:00',
    'contenu'  => '<p style="font-size:.85rem;color:#666;margin-bottom:18px"><strong>REVUE DE PRESSE</strong><br>'
                . 'La Dernière Heure — 29-30 août 2026 — article de Mathieu Ladevèze</p>'

                . '<p>La DH consacre une double page aux riverains survolés et à l\'entrée de nouveaux acteurs dans '
                . 'la procédure judiciaire.</p>'

                . '<h3>Waterloo rejoint l\'action</h3>'
                . '<p>La commune de Waterloo se joint à <strong>l\'action en cessation environnementale</strong> intentée '
                . 'en juin dernier par le gouvernement bruxellois contre l\'État belge, via une '
                . '<strong>intervention volontaire</strong>. La décision a été prise en collège communal, ce que confirme '
                . 'la bourgmestre <strong>Florence Reuter</strong> (MR). Elle est soutenue par l\'ASBL '
                . '<strong>« Piste 01, ça suffit ! »</strong>, qui rejoint elle aussi l\'action. La commune de '
                . 'Rhode-Saint-Genèse envisage également de s\'associer à la démarche.</p>'

                . '<p>L\'objectif est résumé ainsi : <em>« protéger les gens qui ne sont pas protégés par l\'action '
                . 'bruxelloise »</em>. L\'intervention volontaire permet d\'intervenir dans l\'action d\'une partie '
                . 'et d\'y faire valoir son propre point de vue.</p>'

                . '<h3>Notre position dans l\'article</h3>'
                . '<p>Notre président <strong>Charles Sohet</strong> y déclare : <em>« La santé des Brabançons wallons '
                . 'ne vaut pas moins que celles des Bruxellois. Nous nous sentons oubliés dans ce combat. Actuellement, '
                . 'on ne parle que de Bruxelles, pas des victimes de la piste 01. »</em> Il rappelle nos demandes : '
                . 'revenir aux <strong>normes de vent d\'avant 2003</strong>, revoir le changement de règles instauré '
                . 'à l\'époque par le ministre Bert Anciaux, et redéfinir l\'<strong>usage exceptionnel des pistes 07 '
                . 'et 01</strong>. Il précise également : <em>« Notre but n\'est pas d\'opposer Bruxellois et Brabançons '
                . 'wallons. Il faut que les intérêts des uns et des autres soient pris en compte. Nous sommes pour un '
                . 'débat serein. Nous sommes prêts à collaborer. »</em></p>'

                . '<p>Florence Reuter insiste dans le même sens : <em>« Il faut que les intérêts des Waterlootois soient '
                . 'pris en compte. À partir du moment où les communes bruxelloises contestent la situation actuelle, '
                . 'nous voulons aussi être entendus. »</em></p>'

                . '<p>L\'article rend également compte des tensions entre collectifs de riverains et rappelle que le '
                . 'ministre Jean-Luc Crucke a l\'obligation d\'apporter une solution pour le 1er octobre prochain.</p>'

                . pdf_box('/assets/docs/2026-08-29-dh-waterloo-intervention.pdf', 'Lire la page parue (PDF)')

                . '<p style="font-size:.8rem;color:#888;text-align:center">Source : La Dernière Heure, '
                . 'éd. des 29-30 août 2026 (Mathieu Ladevèze).</p>',
];

// ── Colonnes optionnelles ────────────────────────────────────────────────
function colExists($db, $col) {
    try { return (bool) $db->query("SHOW COLUMNS FROM news LIKE " . $db->quote($col))->fetch(); }
    catch (Exception $e) { return false; }
}
$hasDeploye = colExists($db, 'deploye_defaut');

$out = []; $ids = [];

foreach ($articles as $a) {
    $st = $db->prepare("SELECT id FROM news WHERE titre = ? LIMIT 1");
    $st->execute([$a['titre']]);
    if ($row = $st->fetch()) {
        $id = (int) $row['id'];
        $db->prepare("UPDATE news SET accroche=?, contenu=?, date_publication=? WHERE id=?")
           ->execute([$a['accroche'], $a['contenu'], $a['date'], $id]);
        $out[] = "Actualité déjà présente (id=$id) — contenu mis à jour : « {$a['titre']} »";
    } else {
        $db->prepare("INSERT INTO news (titre,accroche,contenu,image_url,statut,epingle,date_publication,created_by) VALUES (?,?,?,?,?,?,?,?)")
           ->execute([$a['titre'], $a['accroche'], $a['contenu'], null, 'publie', 0, $a['date'], ADMIN_USER]);
        $id = (int) $db->lastInsertId();
        $out[] = "Actualité créée (id=$id), publiée, non épinglée : « {$a['titre']} »";
    }
    if ($hasDeploye) $db->prepare("UPDATE news SET deploye_defaut = 0 WHERE id = ?")->execute([$id]);
    $ids[] = $id;
}

// Verification de la presence des PDF
foreach ([
    '/assets/docs/2026-08-29-la-libre-plan-crucke.pdf',
    '/assets/docs/2026-08-29-dh-waterloo-intervention.pdf',
] as $p) {
    $out[] = file_exists(__DIR__ . $p)
        ? "PDF présent sur le serveur : $p"
        : "PDF MANQUANT sur le serveur : $p (attendre la fin du déploiement)";
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">
<title>Publication revue de presse</title>
<style>body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333;max-width:720px;margin:40px auto;padding:0 16px;line-height:1.6}
.box{background:#fff;border:1px solid #d6e2ee;border-radius:10px;padding:24px}
li{margin:6px 0}a.btn{display:inline-block;margin:14px 8px 0 0;background:#1673B2;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none}
.warn{background:#fff3e0;border-left:4px solid #FF9900;padding:12px;border-radius:6px;margin-top:18px}</style>
</head><body><div class="box">
<h2>Revue de presse — 29-30 août 2026</h2>
<ul><?php foreach ($out as $l) echo '<li>' . htmlspecialchars($l) . '</li>'; ?></ul>
<?php foreach ($ids as $i): ?>
<a class="btn" href="/?news=<?= (int) $i ?>#actualites" target="_blank">Voir l'article <?= (int) $i ?> →</a>
<?php endforeach; ?>
<div class="warn"><strong>Sécurité :</strong> supprimez <code>outils-publier-presse.php</code> après usage (CLAUDE.md).</div>
</div></body></html>
