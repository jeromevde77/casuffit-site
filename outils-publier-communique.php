<?php
/* outils-publier-communique.php — v1
 * Outil ponctuel : insere l'actualite "Communique de presse conjoint — On atterrit
 * en survolant des champs, pas des gens" (FR), epinglee + publiee + deployee par defaut.
 * Protege par requireAdmin(). A SUPPRIMER apres usage (voir CLAUDE.md).
 */
require_once __DIR__ . '/config.php';
session_start();
requireAdmin();
$db = getDB();

// ── Contenu FR (texte du communique repris tel quel) ────────────────────
$titre     = "On atterrit en survolant des champs, pas des gens";
// Ancien titre (avec emoji) : permet de retrouver un article deja publie et de le renommer
$titre_old = "\u{1F4E2} On atterrit en survolant des champs, pas des gens";
$accroche = "Communiqué de presse conjoint (7 septembre 2026) — Le survol n'est pas un problème bruxellois. La périphérie et le Brabant wallon entendent ne pas en être les victimes silencieuses.";
$contenu  = <<<'HTML'
<p style="font-size:.85rem;color:#666;margin-bottom:18px"><strong>COMMUNIQUÉ DE PRESSE CONJOINT</strong><br>ASBL « Piste 01, ça suffit ! » — UBCNA/BUTV — AwaCCS<br>Le 7 septembre 2026</p>

<p><strong>Le survol n'est pas un problème bruxellois. La périphérie et le Brabant wallon entendent ne pas en être les victimes silencieuses.</strong></p>

<p>Après plusieurs communes de l'est de Bruxelles, la commune de Waterloo et l'ASBL « Piste 01, ça suffit ! » déposeront une requête en intervention volontaire dans la procédure qui oppose la Région de Bruxelles-Capitale à l'État fédéral sur l'utilisation des pistes de Bruxelles-National. D'autres communes font de même.</p>

<p>On entend depuis des mois que les atterrissages en piste 01 ne survoleraient « que la forêt de Soignes et peu de monde ». On peut débattre de tout ; on ne peut pas dire n'importe quoi. Kraainem, Wezembeek-Oppem, Woluwe-Saint-Pierre, Rhode-Saint-Genèse, Lasne, La Hulpe, Waterloo : nous figurons sur la liste officielle du Service fédéral de médiation des communes survolées en piste 01. Nos habitants subissent ces approches depuis plus de vingt ans. Ce que Bruxelles découvre aujourd'hui, nous le vivons depuis deux décennies.</p>

<h3>À la commune de Dilbeek</h3>

<p>Sur un point, nous rejoignons Dilbeek sans réserve : déplacer une route aérienne d'une commune vers une autre ne résout rien. C'est un jeu à somme nulle, qui use les riverains autant que les finances publiques. Nous partageons ce diagnostic depuis vingt ans.</p>

<p>Mais demander l'usage de la piste 01 « de manière plus intensive », « notamment l'après-midi et la nuit », c'est appliquer exactement le raisonnement que l'on dénonce à la ligne précédente. <strong>Ce n'est pas une solution — et ce n'est pas une solution que nous allons accepter.</strong></p>

<p>Quant aux « dix fois moins d'habitants » : le couloir d'approche de la piste 01 survole <strong>plus de 175 000 habitants dans trois Régions</strong>, dont plus de 75 % hors de Bruxelles. À Waterloo, les avions passent en palier à 2 000 pieds, au moment précis où ils décélèrent, sortent le train et virent : la distance ne protège pas. Ce couloir n'est pas vide — il n'a simplement jamais été compté, parce que les modélisations s'arrêtent aux frontières régionales. Nous demandons la publication intégrale de la méthodologie des études invoquées, et une comparaison à méthode identique.</p>

<p>Rappelons enfin ce qui est déjà jugé : les atterrissages de nuit en piste 01 ont fait l'objet d'une décision de cessation dès 2017, puis d'une interdiction sous astreinte prolongée en 2025, pour des dizaines de millions d'euros à charge de l'État. Réclamer plus de vols de nuit sur la 01, c'est réclamer le rétablissement de ce qu'un juge a interdit.</p>

<h3>Ce que nous demandons — et ce que nous ne demandons pas</h3>

<p>Nous ne sommes pas opposés à l'aéroport, outil économique majeur. Nous ne demandons ni déplacement des nuisances, ni clé de répartition entre pistes.</p>

<p>Nous demandons le retour à ce qui a fonctionné pendant des décennies : privilégier au maximum les pistes 25R et 25L, au décollage comme à l'atterrissage. Elles ne sont pas préférentielles par hasard — elles sont dans l'axe du vent dominant et leurs approches sont précédées de zones agricoles et peu urbanisées.</p>

<p><strong>Aucune piste non préférentielle ne devrait être utilisée à l'atterrissage lorsque le système préférentiel est opérationnel.</strong></p>

<p>Le problème doit être traité à la racine : les normes de vent, leur application, et l'encadrement strict des sorties du système préférentiel. Toute proposition qui n'y touche pas est un emplâtre sur une jambe de bois — nos ASBL ne la soutiendront pas. L'État dispose d'une instruction documentée, tenue pour légale : il suffit de l'appliquer. Si des ajustements sont nécessaires, nous entendons être concertés.</p>

<h3>La sécurité n'est pas négociable — et elle n'est pas un alibi</h3>

<p>Hors système préférentiel, la sécurité impose de voler face au vent : la 01 par vent de nord fort, la 07 par vent d'est fort, la 19 par vent de sud. Ces situations doivent rester exceptionnelles. Utiliser la 01 dans des conditions de vent qui ne le justifient pas, nous ne l'accepterons pas.</p>

<p>Et à ceux qui croient, chiffres à l'appui, que la piste 01 ne serait plus utilisée : depuis janvier, plus de 5 500 avions ont survolé nos riverains. Pourquoi autant d'avions utilisent-ils les pistes 01 et 07 ? Poser la question, c'est y répondre.</p>

<p>La santé des dizaines de milliers de personnes survolées en piste 01 ne vaut pas moins que celle des autres.</p>

<h3>Déclarations</h3>

<p><strong>Charles Sohet, président de « Piste 01, ça suffit ! » :</strong><br>
« Depuis vingt ans, nous soulignons l'importance des normes de vent et de leur interprétation correcte. Ce que nous vivons depuis deux décennies devient aujourd'hui un enjeu politique majeur. L'ASBL a mis l'intégralité de ses archives à la disposition des communes affectées, et déposera un dossier présentant une perspective alternative. Concentrer les nuisances sur la piste 01 n'est pas une solution ; réduire l'usage des pistes non préférentielles, si. »</p>

<p><strong>Peggy Cortois, administratrice de l'UBCNA/BUTV :</strong><br>
« L'instruction du 17 juillet 2013 encadre très précisément l'usage des pistes non préférentielles, et sa publication avait entraîné une réduction significative de cet usage. Nos habitants n'ont voix au chapitre nulle part. Nous intervenons pour qu'ils soient entendus. Leur santé ne vaut pas moins que celle des Bruxellois. »</p>

<hr style="border:none;border-top:1px solid #e0e6ee;margin:24px 0">

<p style="font-size:.85rem;color:#666"><strong>Contact presse</strong> — ASBL « Piste 01, ça suffit ! », Charles Sohet, président<br>
+32 498 66 96 73 — charles@piste01casuffit.be — <a href="https://www.piste01casuffit.be">www.piste01casuffit.be</a><br>
UBCNA/BUTV : ubcna.butv@gmail.com — AwaCCS : awacss@proximus.be</p>
HTML;

// ── Detection des colonnes optionnelles ────────────────────────────────
function colExists($db, $col) {
    try { return (bool) $db->query("SHOW COLUMNS FROM news LIKE " . $db->quote($col))->fetch(); }
    catch (Exception $e) { return false; }
}
$hasDeploye = colExists($db, 'deploye_defaut');

$out = [];

// ── Idempotence : ne pas reinserer si deja present ─────────────────────
// On cherche sous le titre actuel OU l'ancien titre (avec emoji) pour eviter un doublon
$st = $db->prepare("SELECT id, titre FROM news WHERE titre = ? OR titre = ? LIMIT 1");
$st->execute([$titre, $titre_old]);
$existing = $st->fetch();

if ($existing) {
    // Deja publiee : on met a jour titre (sans emoji), texte et date
    $newId = (int) $existing['id'];
    $db->prepare("UPDATE news SET titre=?, accroche=?, contenu=?, date_publication=? WHERE id=?")
       ->execute([$titre, $accroche, $contenu, '2026-09-07 09:00:00', $newId]);
    $renomme = ($existing['titre'] !== $titre) ? " Titre renommé (émoji retiré)." : "";
    $out[] = "✅ Actualité déjà présente (id=$newId) — texte et date mis à jour (07/09/2026).$renomme";
} else {
    // FR uniquement : les colonnes NL gardent leur valeur par défaut (à compléter dans Admin > Actualités)
    $db->prepare("INSERT INTO news (titre,accroche,contenu,image_url,statut,epingle,date_publication,created_by) VALUES (?,?,?,?,?,?,?,?)")
       ->execute([$titre, $accroche, $contenu, null, 'publie', 1, '2026-09-07 09:00:00', ADMIN_USER]);
    $newId = (int) $db->lastInsertId();
    $out[] = "✅ Actualité créée (id=$newId), statut=publié, épinglée, datée du 07/09/2026.";
    $out[] = "ℹ️ Version NL non insérée (à ajouter dans Admin › Actualités si souhaité).";
}

// deploye_defaut (ouverture auto sur la page)
if ($hasDeploye && $newId) {
    $db->prepare("UPDATE news SET deploye_defaut = 1 WHERE id = ?")->execute([$newId]);
    $out[] = "✅ deploye_defaut = 1 (l'article s'ouvre automatiquement).";
}

// ── Brouillon de newsletter (appel aux dons) — a relire dans Admin > Redaction ──
$nl_sujet     = "Survol : une décision est attendue le 1er octobre — nous avons besoin de vous";
// Ancien sujet : permet de retrouver et de remplacer le brouillon precedent
$nl_sujet_old = "Piste 01 : nous allons en justice — aidez-nous à financer l'intervention";
$nl_html  = <<<'HTML'
<p>Chers membres,</p>

<p>La Région de Bruxelles-Capitale a engagé une action en cessation contre la procédure RNP 07L. Si elle aboutit <strong>sans réforme des normes de vent</strong>, le trafic ne disparaîtra pas : il sera reporté sur d'autres communes. Déplacer une route aérienne d'une commune vers une autre ne résout rien — c'est un jeu à somme nulle, qui use les riverains autant que les finances publiques.</p>

<p>Notre position n'a pas changé depuis vingt ans : <strong>le problème n'est pas telle ou telle route, c'est l'usage excessif des pistes non préférentielles</strong>. Nous demandons le retour à ce qui a fonctionné pendant des décennies : privilégier au maximum les <strong>pistes 25R et 25L</strong>, qui sont dans l'axe du vent dominant et dont les approches survolent des zones agricoles et peu urbanisées. Cela suppose de corriger le problème à la racine : les <strong>normes de vent</strong>, leur application, et l'encadrement strict des sorties du système préférentiel.</p>

<h3 style="color:#0e3d6b;font-size:1rem;margin:26px 0 10px">Où nous en sommes</h3>

<p>Le 7 septembre, avec l'UBCNA/BUTV et AwaCCS, nous avons publié un <strong>communiqué de presse conjoint</strong>. La commune de <strong>Waterloo</strong> a décidé de se joindre à la procédure par une <strong>requête en intervention volontaire</strong>, et nous l'accompagnons. Plusieurs communes de l'est de Bruxelles ont fait de même ; Rhode-Saint-Genèse envisage de suivre.</p>

<p>Et le calendrier se resserre : le ministre de la Mobilité a annoncé qu'une décision devait être prise <strong>au plus tard le 1er octobre</strong>.</p>

<p style="font-size:.92rem"><a href="https://www.casuffit.be/?news=13" style="color:#1673B2">Lire notre communiqué du 7 septembre</a> — et la <a href="https://www.casuffit.be/#actualites" style="color:#1673B2">revue de presse</a> (La Libre, La Dernière Heure).</p>

<h3 style="color:#0e3d6b;font-size:1rem;margin:26px 0 10px">Nous avons besoin de vous — de votre nom</h3>

<p>Devant un tribunal, une association pèse. <strong>Des centaines de riverains agissant en leur nom propre pèsent infiniment plus.</strong> Chaque personne qui se joint à la démarche rend notre voix plus difficile à ignorer.</p>

<p>Connectez-vous à votre espace membre et cochez la case <em>« Je souhaite être contacté(e) pour participer à l'action en mon nom »</em>.</p>

<ul style="line-height:1.7">
  <li><strong>Cela ne vous engage à rien aujourd'hui.</strong> Rien ne sera introduit sans votre accord écrit et signé, que nous vous transmettrons ultérieurement.</li>
  <li>Vous pouvez décocher à tout moment.</li>
  <li><strong>Votre adresse complète doit être renseignée dans votre profil</strong> : elle établit votre intérêt à agir, c'est-à-dire le fait que vous êtes réellement survolé(e).</li>
</ul>

<p style="text-align:center;margin:26px 0">
  <a href="https://www.casuffit.be/membre/dashboard.php" style="display:inline-block;background:#1673B2;color:#fff;font-weight:800;padding:15px 30px;border-radius:8px;text-decoration:none;font-size:1rem">Accéder à mon espace membre</a>
</p>

<h3 style="color:#0e3d6b;font-size:1rem;margin:26px 0 10px">La justice a un coût</h3>

<p>Analyser les procédures, consulter des spécialistes, mobiliser des avocats, intervenir devant le tribunal : tout cela représente des sommes considérables pour une ASBL de riverains.</p>

<p><strong>Nous n'avons pas les moyens d'une Région.</strong> Face à des institutions qui disposent de budgets et de services juridiques entiers, nous n'avons que vous. Chaque don, chaque adhésion, chaque euro nous permet de rester dans la partie.</p>

<p style="text-align:center;margin:26px 0">
  <a href="https://www.casuffit.be/don.php" style="display:inline-block;background:#FF9900;color:#fff;font-weight:800;padding:15px 30px;border-radius:8px;text-decoration:none;font-size:1rem">Soutenir notre action</a>
</p>

<p style="font-size:.9rem;color:#555">Ou par virement : <strong>IBAN BE41 0689 0149 6910</strong> — BIC GKCCBEBB — ASBL « Piste 01, ça suffit ! »<br>Communication : <em>Don Piste 01</em></p>

<p>Merci pour votre confiance et votre soutien.<br><strong>Piste 01 Ça Suffit !</strong></p>
HTML;

// On cherche sous le sujet actuel OU l'ancien, pour remplacer le brouillon precedent
$st = $db->prepare("SELECT id FROM newsletters WHERE sujet = ? OR sujet = ? LIMIT 1");
$st->execute([$nl_sujet, $nl_sujet_old]);
if ($nlx = $st->fetch()) {
    $nlId = (int) $nlx['id'];
    // Mise a jour uniquement tant qu'elle est en brouillon (jamais si deja envoyee)
    $upd = $db->prepare("UPDATE newsletters SET sujet=?, contenu_html=?, contenu_text=? WHERE id=? AND statut='brouillon'");
    $upd->execute([$nl_sujet, $nl_html, strip_tags($nl_html), $nlId]);
    $out[] = $upd->rowCount()
        ? "✅ Brouillon de newsletter (id=$nlId) — sujet et contenu remplacés par la nouvelle version."
        : "ℹ️ Newsletter (id=$nlId) déjà envoyée ou modifiée — laissée intacte.";
} else {
    $db->prepare("INSERT INTO newsletters (sujet, contenu_html, contenu_text, statut) VALUES (?,?,?,'brouillon')")
       ->execute([$nl_sujet, $nl_html, strip_tags($nl_html)]);
    $nlId = (int) $db->lastInsertId();
    $out[] = "✅ Brouillon de newsletter créé (id=$nlId) — à relire dans Admin › Rédaction avant envoi.";
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">
<title>Publication communiqué</title>
<style>body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333;max-width:680px;margin:40px auto;padding:0 16px;line-height:1.6}
.box{background:#fff;border:1px solid #d6e2ee;border-radius:10px;padding:24px}
li{margin:4px 0}a.btn{display:inline-block;margin-top:14px;background:#1673B2;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none}
.warn{background:#fff3e0;border-left:4px solid #FF9900;padding:12px;border-radius:6px;margin-top:18px}</style>
</head><body><div class="box">
<h2>Publication « On atterrit en survolant des champs, pas des gens »</h2>
<ul><?php foreach ($out as $line) echo '<li>' . htmlspecialchars($line) . '</li>'; ?></ul>
<a class="btn" href="/?news=<?= (int) $newId ?>#actualites" target="_blank">Voir l'article sur le site →</a>
<div class="warn"><strong>⚠️ Sécurité :</strong> prévenez Claude que c'est fait — <code>outils-publier-communique.php</code> sera retiré du dépôt et du serveur (CLAUDE.md).</div>
</div></body></html>
