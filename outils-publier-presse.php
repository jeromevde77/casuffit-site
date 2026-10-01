<?php
/* outils-publier-presse.php — v4
 * Outil ponctuel : ajoute 4 actualites (La Libre + DH du 29-30/08/2026, retombees du
 * communique du 07/09, communique UBCNA du 01/10). Pages floutees + liens vers les originaux.
 * Idempotent : relancer met a jour au lieu de dupliquer.
 * Protege par requireAdmin(). A SUPPRIMER apres usage (voir CLAUDE.md).
 */
require_once __DIR__ . '/config.php';
session_start();
requireAdmin();
$db  = getDB();
$url = defined('SITE_URL') ? SITE_URL : 'https://www.casuffit.be';

/* URL des articles sur les sites des journaux — A COMPLETER avec les liens exacts. */
const URL_LALIBRE = 'https://www.lalibre.be/belgique/societe/2026/08/29/survol-de-bruxelles-le-plan-de-jean-luc-crucke-pour-sortir-du-bourbier-politique-CDXPVCU7KBCGPN754WRQXBTJWM/';
const URL_DH      = 'https://www.dhnet.be/regions/bruxelles/bruxelles-mobilite/2026/08/29/nuisances-aeriennes-waterloo-simmisce-dans-laction-en-justice-bruxelloise-la-sante-des-brabancons-wallons-ne-vaut-pas-moins-que-celle-des-bruxellois-YHUQP5OVKRBHZOR7KC2HK3SPX4/';

/* Page parue : floutee, seul le titre reste lisible (droit de citation).
   Pas de reproduction exploitable, pas de PDF telechargeable, + lien vers l'original. */
function page_img(string $src, string $alt, string $credit, string $lien, string $journal): string {
    return '<figure style="margin:26px 0;text-align:center">'
         . '<a href="' . $lien . '" target="_blank" rel="noopener">'
         . '<img src="' . $src . '" alt="' . htmlspecialchars($alt, ENT_QUOTES) . '" '
         . 'style="max-width:100%;height:auto;border:1px solid #e0e6ee;border-radius:6px"></a>'
         . '<figcaption style="font-size:.78rem;color:#888;margin-top:8px">' . $credit . '<br>'
         . '<a href="' . $lien . '" target="_blank" rel="noopener" style="color:#1673B2;font-weight:700">'
         . 'Lire l\'article sur ' . $journal . '</a></figcaption></figure>';
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

                . page_img('/assets/img/presse/2026-08-29-la-libre-page.jpg',
                            'Page de La Libre Belgique du 29-30 août 2026 (texte volontairement flouté)',
                            'La Libre Belgique, éd. des 29-30 août 2026 (Adrien de Marneffe). Tous droits réservés — '
                            . 'page reproduite floutée, seul le titre est lisible.',
                            URL_LALIBRE, 'lalibre.be'),
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

                . page_img('/assets/img/presse/2026-08-29-dh-page.jpg',
                            'Page de La Dernière Heure du 29-30 août 2026 (texte volontairement flouté)',
                            'La Dernière Heure, éd. des 29-30 août 2026 (Mathieu Ladevèze). Tous droits réservés — '
                            . 'page reproduite floutée, seul le titre est lisible.',
                            URL_DH, 'dhnet.be'),
];

// ── 3. Retombées du communiqué du 7 septembre ────────────────────────────
$articles[] = [
    'titre'    => "Après notre communiqué, la périphérie entre enfin dans le débat",
    'accroche' => "Au lendemain de notre communiqué de presse conjoint, trois médias consacrent un article à "
                . "l'entrée en justice de la commune de Waterloo et de notre ASBL. Pour la première fois, "
                . "la périphérie et le Brabant wallon sont nommés dans ce dossier.",
    'date'     => '2026-09-08 12:00:00',
    'contenu'  => '<p>Le 7 septembre, avec l\'UBCNA/BUTV et AwaCCS, nous publiions un '
                . '<a href="https://www.casuffit.be/?news=13" style="color:#1673B2">communiqué de presse conjoint</a> : '
                . '<em>« On atterrit en survolant des champs, pas des gens. »</em></p>'

                . '<p>Dès le lendemain, <strong>trois médias</strong> y consacrent un article. C\'est un tournant : '
                . 'depuis des mois, le débat public sur le survol se limitait à Bruxelles. Aujourd\'hui, '
                . '<strong>Waterloo, le Brabant wallon et les communes de la périphérie</strong> sont nommés — '
                . 'et l\'action en justice qu\'ils engagent avec nous est relayée.</p>'

                . '<h3>Les articles</h3>'
                . '<ul style="line-height:1.9">'
                . '<li><strong>BX1</strong> — <a href="https://bx1.be/categories/news/survols-aeriens-waterloo-et-lasbl-piste-01-ca-suffit-en-justice-contre-le-federal/" target="_blank" rel="noopener" style="color:#1673B2">Survols aériens : Waterloo et l\'ASBL « Piste 01 ça suffit » en justice contre le fédéral</a></li>'
                . '<li><strong>21News</strong> — <a href="https://www.21news.be/survol-de-bruxelles-waterloo-et-les-riverains-de-la-piste-01-contre-attaquent-en-justice/" target="_blank" rel="noopener" style="color:#1673B2">Survol de Bruxelles : Waterloo et les riverains de la piste 01 contre-attaquent en justice</a></li>'
                . '<li><strong>L\'Avenir</strong> — <a href="https://www.lavenir.net/regions/brabantwallon/waterloo/2026/09/08/nuisances-des-avions-lasbl-piste-01-ca-suffit-et-la-commune-de-waterloo-en-justice-contre-le-federal-LOCA2OIV3FCJ5BZRD37HYN4POA/" target="_blank" rel="noopener" style="color:#1673B2">Nuisances des avions : l\'ASBL « Piste 01 ça suffit » et la commune de Waterloo en justice contre le fédéral</a></li>'
                . '</ul>'

                . '<h3>Ce que nous continuerons de répéter</h3>'
                . '<p>Être enfin cités ne suffit pas : encore faut-il que le fond soit entendu. Nous ne demandons '
                . '<strong>ni déplacement des nuisances, ni clé de répartition entre pistes</strong>. Le problème n\'est '
                . 'pas telle ou telle route : c\'est l\'<strong>usage excessif des pistes non préférentielles</strong>. '
                . 'Notre demande reste la même — privilégier au maximum les <strong>pistes 25R et 25L</strong>, et traiter '
                . 'la question à la racine : les <strong>normes de vent</strong>, leur application, et l\'encadrement '
                . 'strict des sorties du système préférentiel.</p>'

                . '<p>Une décision du ministre de la Mobilité est attendue <strong>au plus tard le 1er octobre</strong>.</p>',
];

// ── 4. Communiqué de l'UBCNA/BUTV du 1er octobre 2026 ────────────────────
$ubcna = <<<'HTML'
<p style="font-size:.85rem;color:#666;margin-bottom:18px"><strong>COMMUNIQUÉ DE PRESSE DE L'UBCNA/BUTV</strong><br>Jeudi 1er octobre 2026</p>

<p style="background:#f0f6ff;border-left:4px solid #1673B2;padding:12px 16px;border-radius:0 6px 6px 0;font-size:.9rem">
Ce communiqué émane de l'<strong>UBCNA/BUTV</strong>, association partenaire. Nous le relayons intégralement à titre d'information.
</p>

<p>Nous constatons une très grande confusion dans l'analyse de la situation actuelle des procédures de survol autour de Bruxelles-National.</p>

<p>Aussi nous apportons les précisions suivantes :</p>

<ul style="line-height:1.8">
<li>en septembre 2026, <strong>0 avion</strong>, aucun survol, pas un seul atterrissage n'a eu lieu sur la route faussement nommée Crucke, qui est en fait une décision de 2016/2018/2019 des Ministres successifs Galant, Belot et Gilkinet. Aucun passage d'avion n'a été organisé sur l'axe de l'atterrissage 07 Left tout au long du mois de septembre 2026 ;</li>
<li>0 utilisation de la route RNP 07L démontre que c'est bien une utilisation éventuelle uniquement en fonction des conditions météorologiques ; en septembre on n'a pas constaté de vent d'Est ;</li>
<li>la <strong>piste 01 à l'atterrissage a été activée pour 195 atterrissages</strong> en septembre 2026 à cause du vent de Nord ;</li>
<li>ce sont uniquement les conditions de vent qui imposent soit l'usage de la 01 soit de la RNP 07L (30 activations 07L en juillet 2026, 2515 en août 2026 et 0 en septembre 2026).</li>
</ul>

<h3>Une nouvelle description de la procédure RNP 07 Left</h3>

<p>Une nouvelle description définitive et permanente de la procédure d'approche RNP 07 Left a été publiée le <strong>20 août 2026</strong>, et entrée en service (si le vent l'impose) ce jeudi :</p>

<ul style="line-height:1.8">
<li>alignement à 4000 pieds dans l'axe de la piste plus loin, soit à Oetingen (Pajottegem) ;</li>
<li>stabilisation des avions à 4000 pieds au-dessus de Lennik ;</li>
<li>descente continue et rectiligne sans remise des gaz vers la piste 07L en survolant plus haut les zones situées dans l'axe de la piste.</li>
</ul>

<p>Les propositions alternatives d'approches non rectilignes par vent d'Est vers la piste 07L violent l'Arrêté royal de 1954 interdisant le survol du Domaine Royal de Laeken et envoient les avions au-dessus de la seule zone classée risque élevé SEVESO de tout Bruxelles : le terminal gazier de NOH.</p>

<p>Le tracé rectiligne à 4000 pieds est conforme à la réglementation européenne, ne souffre d'aucune irrégularité (aucune étude ni consultation n'est nécessaire), et cette mesure ne rentre pas dans le cadre de l'approche équilibrée. Aucune disposition réglementaire nationale ou internationale ne prend en compte la densité de population ; les chiffres de densité énoncés sont très contestables car fort approximatifs.</p>

<h3>Ce qui détermine l'usage des pistes</h3>

<p>La procédure d'atterrissage RNP 07L n'est pas une procédure fixée par un horaire, ni par des dates et dépend uniquement de l'orientation et l'intensité des vents :</p>

<ul style="line-height:1.8">
<li>si le vent souffle de l'EST ce sera 07 et du NORD ce sera 01 ;</li>
<li>il faut que le vent soit assez fort et ne permette pas de rester sur les pistes 25R/L, donc un vent qui soit supérieur à une composante effective de 7 nœuds ;</li>
<li>les atterrissages 07L évitent des utilisations de pistes qui se croisent au sol et qui sont un schéma plus risqué ;</li>
<li>la piste 07L est la plus longue et la mieux équipée (3 638 mètres) alors que la piste 01 est la plus courte et la moins bien équipée (2 987 mètres) ;</li>
<li>l'approche RNP 07L par guidage GPS est actuellement la procédure qui assure la sécurité maximale des atterrissages.</li>
</ul>

<h3>Sur l'échéance du 1er octobre</h3>

<p>Nous remarquons également que :</p>

<ul style="line-height:1.8">
<li>l'échéance de présentation d'un plan pour le 1er octobre 2026 résulte d'un jugement connu depuis le 26 février 2025 ;</li>
<li>ce jugement de février 2025 ne concerne que les procédures Canal, Ring et Virage Gauche ainsi que les atterrissages 01 ;</li>
<li>l'État doit présenter un Plan sous contrainte d'une astreinte de 10 000 € par jour de retard.</li>
</ul>

<p>Toutes ces précisions sont importantes pour vous permettre une analyse objective de la situation.</p>

<hr style="border:none;border-top:1px solid #e0e6ee;margin:24px 0">
<p style="font-size:.82rem;color:#888">Source : communiqué de presse de l'UBCNA/BUTV, 1er octobre 2026.</p>
HTML;

$articles[] = [
    'titre'    => "UBCNA : 0 avion sur la route Crucke en septembre 2026",
    'accroche' => "Communiqué de l'UBCNA/BUTV du 1er octobre 2026 : aucun atterrissage sur la RNP 07L en septembre, "
                . "mais 195 atterrissages en piste 01 à cause du vent de Nord. Ce sont les conditions de vent, "
                . "et elles seules, qui déterminent l'usage des pistes.",
    'date'     => '2026-10-01 10:00:00',
    'contenu'  => $ubcna,
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

// Verification de la presence des extraits image
foreach ([
    '/assets/img/presse/2026-08-29-la-libre-page.jpg',
    '/assets/img/presse/2026-08-29-dh-page.jpg',
] as $p) {
    $out[] = file_exists(__DIR__ . $p)
        ? "Extrait présent sur le serveur : $p"
        : "Extrait MANQUANT sur le serveur : $p (attendre la fin du déploiement)";
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
