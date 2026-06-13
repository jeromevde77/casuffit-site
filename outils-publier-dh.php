<?php
/* outils-publier-dh.php — v1
 * Outil ponctuel : insere l'actualite "La DH relate notre analyse" (FR + NL),
 * epinglee + publiee + deployee par defaut, avec image.
 * Protege par requireAdmin(). A SUPPRIMER apres usage (voir CLAUDE.md).
 */
require_once __DIR__ . '/config.php';
session_start();
requireAdmin();
$db = getDB();

$image = (defined('SITE_URL') ? SITE_URL : 'https://www.casuffit.be') . '/medias/news/dh-se-trompe-de-combat.jpg';

// ── Contenu FR ──────────────────────────────────────────────────────────
$titre    = "📰 La DH relate notre analyse : « La Région bruxelloise se trompe de combat »";
$accroche = "La Dernière Heure (éd. 13-14 juin) consacre un article à notre collectif et reprend notre position. Nous ne nous laisserons pas faire.";
$contenu  = <<<'HTML'
<p><em>La Dernière Heure</em> (éd. du 13-14 juin) consacre un article au collectif <strong>« Piste 01 Ça Suffit ! »</strong> et relate notre analyse : en attaquant la procédure d'approche <strong>RNP07L</strong> sans réformer les <strong>normes de vent</strong>, la Région bruxelloise <strong>« se trompe de combat »</strong>.</p>

<p>Supprimer cette trajectoire ne résoudrait rien : le trafic serait simplement reporté vers le sud et l'est de Bruxelles, le Brabant wallon… et <strong>la piste 01</strong>. <em>« On déshabille Pierre pour habiller Paul. »</em></p>

<p>Pendant que Bruxelles, avec ses moyens, organise sa défense, <em>« ce sont nos communes qui risquent de récupérer le trafic dont la capitale ne veut plus. »</em></p>

<p><strong>Nous ne nous laisserons pas faire.</strong> Le vrai combat, c'est la réforme des <strong>normes de vent</strong> — pour une répartition des survols plus juste et plus supportable pour tous les riverains.</p>

<p style="text-align:center;margin:22px 0">
  <a href="/don.php" style="display:inline-block;background:#FF9900;color:#fff;font-weight:800;padding:16px 32px;border-radius:8px;text-decoration:none;font-size:1.05rem;box-shadow:0 4px 12px rgba(255,153,0,.4)">🔥 SOUTENIR LE COMBAT — JE FAIS UN DON</a>
</p>
<p style="font-size:.8rem;color:#888;text-align:center;margin-top:14px">Source : La Dernière Heure, éd. du 13-14 juin 2026 (Jérémy Zysberg). Photo © La DH / J.-L. Flemal.</p>
HTML;

// ── Contenu NL ──────────────────────────────────────────────────────────
$titre_nl    = "📰 De DH geeft onze analyse weer: « Het Brussels Gewest voert de verkeerde strijd »";
$accroche_nl = "La Dernière Heure (ed. 13-14 juni) wijdt een artikel aan ons collectief en herneemt ons standpunt. Wij laten dit niet gebeuren.";
$contenu_nl  = <<<'HTML'
<p><em>La Dernière Heure</em> (ed. van 13-14 juni) wijdt een artikel aan het collectief <strong>« Piste 01 Ça Suffit ! »</strong> en geeft onze analyse weer: door de aanvliegprocedure <strong>RNP07L</strong> aan te vallen zonder de <strong>windnormen</strong> te hervormen, voert het Brussels Gewest <strong>« de verkeerde strijd »</strong>.</p>

<p>Het schrappen van deze route lost niets op: het verkeer wordt gewoon verlegd naar het zuiden en oosten van Brussel, naar Waals-Brabant… en naar <strong>baan 01</strong>. <em>« Men kleedt de ene uit om de andere aan te kleden. »</em></p>

<p>Terwijl Brussel met zijn middelen zijn verdediging organiseert, <em>« dreigen onze gemeenten het verkeer te krijgen waar de hoofdstad niet meer van wil. »</em></p>

<p><strong>Wij laten dit niet gebeuren.</strong> De echte strijd is de hervorming van de <strong>windnormen</strong> — voor een eerlijkere en draaglijkere verdeling van de overvluchten voor alle omwonenden.</p>

<p style="text-align:center;margin:22px 0">
  <a href="/don.php" style="display:inline-block;background:#FF9900;color:#fff;font-weight:800;padding:16px 32px;border-radius:8px;text-decoration:none;font-size:1.05rem;box-shadow:0 4px 12px rgba(255,153,0,.4)">🔥 STEUN DE STRIJD — IK DOE EEN GIFT</a>
</p>
<p style="font-size:.8rem;color:#888;text-align:center;margin-top:14px">Bron: La Dernière Heure, ed. van 13-14 juni 2026 (Jérémy Zysberg). Foto © La DH / J.-L. Flemal.</p>
HTML;

// ── Detection des colonnes optionnelles ────────────────────────────────
function colExists($db, $col) {
    try { return (bool) $db->query("SHOW COLUMNS FROM news LIKE " . $db->quote($col))->fetch(); }
    catch (Exception $e) { return false; }
}
$hasNl      = colExists($db, 'titre_nl');
$hasNlStat  = colExists($db, 'nl_status');
$hasDeploye = colExists($db, 'deploye_defaut');

$out = [];

// ── Idempotence : ne pas reinserer si deja present ─────────────────────
$st = $db->prepare("SELECT id FROM news WHERE titre = ? LIMIT 1");
$st->execute([$titre]);
$existing = $st->fetch();

if ($existing) {
    $newId = (int) $existing['id'];
    $out[] = "⚠️ L'actualité existe déjà (id=$newId) — aucune insertion (idempotent).";
} else {
    $cols = ['titre','accroche','contenu','image_url','statut','epingle','date_publication','created_by'];
    $vals = [$titre, $accroche, $contenu, $image, 'publie', 1, date('Y-m-d H:i:s'), ADMIN_USER];
    if ($hasNl) {
        array_splice($cols, 3, 0, ['titre_nl','accroche_nl','contenu_nl']);
        array_splice($vals, 3, 0, [$titre_nl, $accroche_nl, $contenu_nl]);
        if ($hasNlStat) { $cols[] = 'nl_status'; $vals[] = 'relu'; }
    }
    $ph = implode(',', array_fill(0, count($cols), '?'));
    $db->prepare("INSERT INTO news (" . implode(',', $cols) . ") VALUES ($ph)")->execute($vals);
    $newId = (int) $db->lastInsertId();
    $out[] = "✅ Actualité créée (id=$newId), statut=publié, épinglé, avec image.";
    if ($hasNl) $out[] = "✅ Version NL insérée (nl_status=relu)."; else $out[] = "ℹ️ Colonnes NL absentes : seule la version FR a été insérée.";
}

// deploye_defaut (ouverture auto sur la page)
if ($hasDeploye && $newId) {
    $db->prepare("UPDATE news SET deploye_defaut = 1 WHERE id = ?")->execute([$newId]);
    $out[] = "✅ deploye_defaut = 1 (l'article s'ouvre automatiquement).";
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">
<title>Publication DH</title>
<style>body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333;max-width:680px;margin:40px auto;padding:0 16px;line-height:1.6}
.box{background:#fff;border:1px solid #d6e2ee;border-radius:10px;padding:24px}
li{margin:4px 0}a.btn{display:inline-block;margin-top:14px;background:#1673B2;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none}
.warn{background:#fff3e0;border-left:4px solid #FF9900;padding:12px;border-radius:6px;margin-top:18px}</style>
</head><body><div class="box">
<h2>Publication « La DH relate notre analyse »</h2>
<ul><?php foreach ($out as $line) echo '<li>' . htmlspecialchars($line) . '</li>'; ?></ul>
<a class="btn" href="/?news=<?= (int) $newId ?>#actualites" target="_blank">Voir l'article sur le site →</a>
<div class="warn"><strong>⚠️ Sécurité :</strong> supprimez maintenant <code>outils-publier-dh.php</code> du dépôt et du serveur (CLAUDE.md).</div>
</div></body></html>
