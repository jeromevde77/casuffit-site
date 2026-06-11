<?php
/* outils-publier-rnp07l.php — v1
 * Outil ponctuel : insere l'actualite "RNP07L / normes de vent" (FR + NL),
 * epinglee + publiee + deployee par defaut.
 * Protege par requireAdmin(). A SUPPRIMER apres usage (voir CLAUDE.md).
 */
require_once __DIR__ . '/config.php';
session_start();
requireAdmin();
$db = getDB();

// ── Contenu FR ──────────────────────────────────────────────────────────
$titre    = "🚨 La Région bruxelloise se trompe de combat — le vrai enjeu, ce sont les normes de vent";
$accroche = "Le 11 juin 2026, Bruxelles a décidé d’attaquer le fédéral pour faire fermer la piste 07L. Mais c'est le mauvais combat : sans réforme des normes de vent, on ne fait que déplacer le problème — vers le sud et l'est de Bruxelles, le Brabant wallon… et la piste 01.";
$contenu  = <<<'HTML'
<p>Le 11 juin 2026, le gouvernement de la Région bruxelloise a dégainé l'arme judiciaire contre l'État fédéral : une <strong>action en cessation</strong> pour faire interdire la RNP07L.</p>

<p style="background:#fff3e0;border-left:4px solid #FF9900;padding:14px;border-radius:4px"><strong>🚨 La Région bruxelloise se trompe de combat.</strong> S'attaquer à la procédure d'approche (la RNP07L) sans toucher au véritable nœud du problème — <strong>les normes de vent</strong> — ne règle rien. Cela ne fait que <strong>déplacer les avions ailleurs</strong>.</p>

<p>Ce sont les normes de vent qui décident, chaque jour, quelle piste est utilisée. Tant qu'elles ne sont pas réformées, supprimer une trajectoire revient à <strong>tout reporter sur le sud et l'est de Bruxelles, sur le Brabant wallon… et à renforcer l'usage de la piste 01.</strong></p>

<p>On déshabille Pierre pour habiller Paul — et Paul, c'est toujours le même. Pendant que Bruxelles, avec ses moyens et ses avocats, organise sa défense, ce sont nos communes qui risquent de récolter <strong>le trafic dont la capitale ne veut plus</strong>.</p>

<p><strong>Nous ne serons pas les dindons de la farce.</strong> Le vrai combat, c'est la <strong>réforme des normes de vent</strong> : une répartition juste et supportable pour tous, pas un jeu de chaises musicales où le perdant est toujours le riverain de la 01.</p>

<p><strong>« Ça suffit ! » doit porter ce message haut et fort.</strong> Avocats, recours, expertises, mesures de bruit, mobilisation : rien n'est gratuit. Face à une Région qui sort l'artillerie lourde sur le mauvais front, nous avons besoin de moyens pour défendre le bon — <strong>maintenant</strong>.</p>

<p style="text-align:center;margin:22px 0">
  <a href="/don.php" style="display:inline-block;background:#FF9900;color:#fff;font-weight:800;padding:16px 32px;border-radius:8px;text-decoration:none;font-size:1.05rem;box-shadow:0 4px 12px rgba(255,153,0,.4)">🔥 JE REFUSE LA 01 — JE FAIS UN DON</a>
</p>
<p style="text-align:center;font-size:.9rem;color:#555">Le vrai combat, ce sont les normes de vent.</p>
<p style="font-size:.8rem;color:#888;text-align:center;margin-top:14px">Sources : RTBF, La Libre, BX1 — 11 juin 2026.</p>
HTML;

// ── Contenu NL ──────────────────────────────────────────────────────────
$titre_nl    = "🚨 Het Brussels Gewest voert de verkeerde strijd — de echte inzet zijn de windnormen";
$accroche_nl = "Op 11 juni 2026 besloot Brussel de federale staat aan te vallen om baan 07L te laten sluiten. Maar dat is de verkeerde strijd: zonder hervorming van de windnormen verplaatst men het probleem alleen maar — naar het zuiden en oosten van Brussel, naar Waals-Brabant… en naar baan 01.";
$contenu_nl  = <<<'HTML'
<p>Op 11 juni 2026 trok de Brusselse Gewestregering het juridische wapen tegen de federale staat: een <strong>vordering tot staking</strong> om de RNP07L te laten verbieden.</p>

<p style="background:#fff3e0;border-left:4px solid #FF9900;padding:14px;border-radius:4px"><strong>🚨 Het Brussels Gewest voert de verkeerde strijd.</strong> De aanvliegprocedure (de RNP07L) aanvallen zonder de echte kern van het probleem aan te pakken — <strong>de windnormen</strong> — lost niets op. Het verplaatst de vliegtuigen alleen maar.</p>

<p>Het zijn de windnormen die elke dag bepalen welke baan wordt gebruikt. Zolang ze niet hervormd worden, betekent het schrappen van één route dat <strong>alles wordt verlegd naar het zuiden en oosten van Brussel, naar Waals-Brabant… en dat het gebruik van baan 01 toeneemt.</strong></p>

<p>Men kleedt de ene uit om de andere aan te kleden — en die andere is altijd dezelfde. Terwijl Brussel met zijn middelen en advocaten zijn verdediging organiseert, dreigen onze gemeenten <strong>het verkeer te krijgen waar de hoofdstad niet meer van wil</strong>.</p>

<p><strong>Wij worden niet de dupe.</strong> De echte strijd is de <strong>hervorming van de windnormen</strong>: een eerlijke en draaglijke verdeling voor iedereen, geen stoelendans waarbij de omwonende van baan 01 altijd de verliezer is.</p>

<p><strong>« Ça suffit! » moet deze boodschap luid en duidelijk uitdragen.</strong> Advocaten, beroepen, expertises, geluidsmetingen, mobilisatie: niets is gratis. Tegenover een Gewest dat zwaar geschut bovenhaalt op het verkeerde front, hebben we middelen nodig om het juiste te verdedigen — <strong>nu</strong>.</p>

<p style="text-align:center;margin:22px 0">
  <a href="/don.php" style="display:inline-block;background:#FF9900;color:#fff;font-weight:800;padding:16px 32px;border-radius:8px;text-decoration:none;font-size:1.05rem;box-shadow:0 4px 12px rgba(255,153,0,.4)">🔥 IK WEIGER BAAN 01 — IK DOE EEN GIFT</a>
</p>
<p style="text-align:center;font-size:.9rem;color:#555">De echte strijd zijn de windnormen.</p>
<p style="font-size:.8rem;color:#888;text-align:center;margin-top:14px">Bronnen: RTBF, La Libre, BX1 — 11 juni 2026.</p>
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
    $cols = ['titre','accroche','contenu','statut','epingle','date_publication','created_by'];
    $vals = [$titre, $accroche, $contenu, 'publie', 1, date('Y-m-d H:i:s'), ADMIN_USER];
    if ($hasNl) {
        array_splice($cols, 3, 0, ['titre_nl','accroche_nl','contenu_nl']);
        array_splice($vals, 3, 0, [$titre_nl, $accroche_nl, $contenu_nl]);
        if ($hasNlStat) { $cols[] = 'nl_status'; $vals[] = 'relu'; }
    }
    $ph = implode(',', array_fill(0, count($cols), '?'));
    $db->prepare("INSERT INTO news (" . implode(',', $cols) . ") VALUES ($ph)")->execute($vals);
    $newId = (int) $db->lastInsertId();
    $out[] = "✅ Actualité créée (id=$newId), statut=publié, épinglé.";
    if ($hasNl)      $out[] = "✅ Version NL insérée (nl_status=relu)."; else $out[] = "ℹ️ Colonnes NL absentes : seule la version FR a été insérée.";
}

// deploye_defaut (ouverture auto sur la page)
if ($hasDeploye && $newId) {
    $db->prepare("UPDATE news SET deploye_defaut = 1 WHERE id = ?")->execute([$newId]);
    $out[] = "✅ deploye_defaut = 1 (l'article s'ouvre automatiquement).";
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">
<title>Publication RNP07L</title>
<style>body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333;max-width:680px;margin:40px auto;padding:0 16px;line-height:1.6}
.box{background:#fff;border:1px solid #d6e2ee;border-radius:10px;padding:24px}
li{margin:4px 0}a.btn{display:inline-block;margin-top:14px;background:#1673B2;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none}
.warn{background:#fff3e0;border-left:4px solid #FF9900;padding:12px;border-radius:6px;margin-top:18px}</style>
</head><body><div class="box">
<h2>Publication « RNP07L / normes de vent »</h2>
<ul><?php foreach ($out as $line) echo '<li>' . htmlspecialchars($line) . '</li>'; ?></ul>
<a class="btn" href="/?news=<?= (int) $newId ?>#actualites" target="_blank">Voir l'article sur le site →</a>
<div class="warn"><strong>⚠️ Sécurité :</strong> supprimez maintenant <code>outils-publier-rnp07l.php</code> du dépôt et du serveur (CLAUDE.md).</div>
</div></body></html>
