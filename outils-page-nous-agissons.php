<?php
/* outils-page-nous-agissons.php — v1
 * Outil PONCTUEL : crée la page « Nous agissons » dans la table `pages`.
 * ⚠ À SUPPRIMER après usage (règle de sécurité du projet).
 * Placé à la racine car le service worker met en cache /admin/.
 */
require_once __DIR__ . '/config.php';
session_start();
if (empty($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    exit('Accès refusé — connectez-vous à l\'admin puis rechargez cette page.');
}

$SLUG   = 'nous-agissons';
$TITRE  = 'Nous agissons';
$SOURCE = 'mobilisation';   // page dont on recopie les widgets

$CONTENU = <<<'HTML'
<div class="cadre-orange">
  <strong>Piste 01 : nous agissons.</strong>
</div>

<p>Ces derniers jours, <strong>Piste 01 Ça Suffit !</strong> a procédé à une analyse approfondie de la situation juridique actuelle autour de Brussels Airport.</p>

<p>Nous avons étudié en détail les citations introduites par des riverains flamands, ainsi que celle de la <strong>Région de Bruxelles-Capitale contre l'État belge</strong>.</p>

<p>Ces procédures peuvent avoir des conséquences qui dépassent largement les territoires concernés par les demandes initiales, et modifier l'équilibre actuel <strong>au détriment des populations situées sous l'axe de la piste 01</strong>.</p>

<h2>Ce que nous avons fait</h2>

<div class="cadre-vert">
  <div class="cv-titre">Travail réalisé</div>
  <ul>
    <li>Analyse approfondie de la situation juridique autour de Brussels Airport</li>
    <li>Étude détaillée des citations introduites par des riverains flamands</li>
    <li>Étude de la citation de la Région de Bruxelles-Capitale contre l'État belge</li>
    <li>Prise de contact avec plusieurs communes situées sous l'axe de la piste 01</li>
  </ul>
</div>

<p>Pour des raisons évidentes, nous ne dévoilerons pas publiquement le contenu de notre analyse ni notre stratégie.</p>

<p>En revanche, nous mettons à la disposition des communes concernées les documents dont nous disposons, nos analyses techniques et juridiques, ainsi que le travail accumulé par notre association depuis de nombreuses années.</p>

<h2>Notre position n'a pas changé</h2>

<div class="cadre-bleu">
  <strong>Depuis près de 15 ans, nous défendons la même idée : le problème n'est pas la piste 01. Le problème est global.</strong>
</div>

<p>Il réside notamment dans la <strong>sortie trop rapide du système préférentiel de pistes (PRS)</strong>.</p>

<ul>
  <li>La priorité doit être de <strong>maintenir le PRS aussi longtemps que les conditions de vent et de sécurité le permettent</strong>, afin de réduire le nombre de situations nécessitant le recours aux pistes non préférentielles.</li>
  <li>Lorsque les conditions imposent réellement d'en sortir, ce sont <strong>la sécurité, le vent réel et les contraintes opérationnelles</strong> qui doivent déterminer la piste appropriée.</li>
</ul>

<p><strong>Nous ne demandons donc pas de déplacer les nuisances d'une population vers une autre.</strong></p>

<div class="alerte">
  <div class="al-titre">Notre vigilance</div>
  <p>Il apparaît aujourd'hui que certains défendent une vision différente. Nous serons extrêmement attentifs à ce que les procédures en cours ne conduisent pas, directement ou indirectement, à protéger certaines populations en reportant simplement les nuisances sur d'autres.</p>
</div>

<p>Waterloo, Braine-l'Alleud, l'Est de Bruxelles, Kraainem, Wezembeek-Oppem et les autres populations concernées par les approches de la piste 01 <strong>doivent elles aussi être entendues</strong>.</p>

<h2>Pour continuer, nous avons besoin de vous</h2>

<p>Analyser plusieurs procédures judiciaires, consulter des spécialistes, mobiliser des avocats et, si nécessaire, intervenir juridiquement représente un coût considérable pour une ASBL.</p>

<div class="cadre-orange">
  Notre objectif de financement est aujourd'hui de <strong>40 000 €</strong>.
</div>

<div class="cadre-vert">
  <div class="cv-titre">Où nous en sommes</div>
  <ul>
    <li>Les dossiers sont constitués</li>
    <li>Les analyses sont réalisées</li>
    <li>La mobilisation des communes a commencé</li>
  </ul>
</div>

<p>Il nous faut maintenant les moyens de défendre ce travail juridiquement. <strong>Chaque membre, chaque adhésion et chaque soutien financier renforce notre capacité d'action.</strong></p>

<div class="cadre-bleu">
  <strong>Notre combat n'est pas 01 contre 07.</strong><br>
  Notre combat est celui d'une politique aéroportuaire cohérente, sûre et équitable, qui ne consiste pas à déplacer les nuisances d'une population vers une autre.
</div>
HTML;

$META = "Piste 01 Ça Suffit ! agit face aux procédures judiciaires en cours autour de Brussels Airport : analyses juridiques, contacts avec les communes et défense d'une politique aéroportuaire équitable.";

$db  = getDB();
$log = array();
$done = false;

// ── État actuel ──────────────────────────────────────────────────────────
$existe = null;
try {
    $st = $db->prepare("SELECT id, slug, titre, visible, dans_menu, ordre FROM pages WHERE slug=?");
    $st->execute(array($SLUG));
    $existe = $st->fetch();
} catch (Exception $e) { $log[] = '⚠ Lecture impossible : '.$e->getMessage(); }

// Widgets de la page source (pour recopie)
$src_widgets = array();
try {
    $st = $db->prepare("SELECT widget_slug, ordre, COALESCE(position,'droite') AS position FROM page_widgets WHERE page_slug=? ORDER BY ordre ASC");
    $st->execute(array($SOURCE));
    $src_widgets = $st->fetchAll();
} catch (Exception $e) {}

// ── Action ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['go'])) {
    $visible    = isset($_POST['visible'])    ? 1 : 0;
    $dans_menu  = isset($_POST['dans_menu'])  ? 1 : 0;
    $copy_widg  = isset($_POST['copy_widgets']);
    $ordre      = (int)($_POST['ordre'] ?? 1);

    try {
        if ($existe) {
            $db->prepare("UPDATE pages SET titre=?, contenu=?, meta_description=?, ordre=?, visible=?, dans_menu=?, updated_by=? WHERE slug=?")
               ->execute(array($TITRE, $CONTENU, $META, $ordre, $visible, $dans_menu, ADMIN_USER, $SLUG));
            $log[] = "✏ Page « $SLUG » mise à jour (elle existait déjà).";
        } else {
            $db->prepare("INSERT INTO pages (slug,titre,contenu,meta_description,ordre,visible,dans_menu,icone,css_class,menu_position,lien_url,affichage_menu,btn_style,parent_id,updated_by)
                          VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
               ->execute(array($SLUG, $TITRE, $CONTENU, $META, $ordre, $visible, $dans_menu, '', '', 'all', '', 'texte', '', null, ADMIN_USER));
            $log[] = "✅ Page « $SLUG » créée (id ".$db->lastInsertId().").";
        }

        if ($copy_widg && $src_widgets) {
            $db->prepare("DELETE FROM page_widgets WHERE page_slug=?")->execute(array($SLUG));
            $ins = $db->prepare("INSERT INTO page_widgets (page_slug, widget_slug, ordre, position) VALUES (?,?,?,?)");
            foreach ($src_widgets as $w) {
                $ins->execute(array($SLUG, $w['widget_slug'], $w['ordre'], $w['position']));
            }
            $log[] = "✅ ".count($src_widgets)." widget(s) recopié(s) depuis « $SOURCE ».";
        }

        $log[] = "ℹ La page « $SOURCE » n'a pas été modifiée.";
        $done = true;
    } catch (Exception $e) {
        $log[] = '❌ Erreur : '.$e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Publier « Nous agissons »</title>
<style>
body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333;margin:0;padding:36px 18px}
.box{max-width:760px;margin:0 auto;background:#fff;border-radius:14px;box-shadow:0 2px 14px rgba(0,0,0,.09);padding:28px 32px}
h1{font-size:1.25rem;color:#0e3d6b;margin:0 0 6px}
.sub{font-size:.84rem;color:#888;margin-bottom:20px}
.st{padding:12px 16px;border-radius:9px;font-size:.86rem;margin-bottom:18px}
.st.new{background:#eff6ff;color:#1673B2;border:1px solid #cfe2fb}
.st.upd{background:#fff8ee;color:#9a6a00;border:1px solid #ffd699}
fieldset{border:1.5px solid #e3ecf6;border-radius:10px;padding:16px 18px;margin:0 0 18px}
legend{font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#888;padding:0 6px}
label.c{display:flex;align-items:center;gap:9px;font-size:.88rem;margin:9px 0;cursor:pointer}
label.c input{width:17px;height:17px;cursor:pointer}
label.c small{color:#999;font-size:.76rem}
.fo{display:flex;align-items:center;gap:10px;margin-top:12px;font-size:.88rem}
.fo input{width:80px;padding:7px 10px;border:1.5px solid #cdd8e5;border-radius:7px;font-family:inherit}
.btn{padding:12px 26px;border:none;border-radius:9px;font-size:.92rem;font-weight:700;cursor:pointer;font-family:inherit;background:#FF9900;color:#fff}
.btn:hover{background:#e08800}
.btn.grey{background:#eef3f9;color:#0e3d6b;text-decoration:none;display:inline-block}
.log{background:#0e2438;color:#d6e4f0;padding:14px 18px;border-radius:9px;font-size:.82rem;line-height:1.8;margin-bottom:18px}
.warn{background:#fdecea;border:1px solid #f5b7b1;color:#922b21;padding:13px 16px;border-radius:9px;font-size:.82rem;line-height:1.6;margin-top:22px}
.links a{color:#1673B2;font-weight:700;text-decoration:none;font-size:.88rem;margin-right:16px}
</style>
</head>
<body>
<div class="box">
  <h1>📄 Publier la page « Nous agissons »</h1>
  <div class="sub">Crée une nouvelle page sans toucher à la page « Mobilisation » existante.</div>

  <?php if ($log): ?><div class="log"><?php foreach ($log as $l) echo htmlspecialchars($l).'<br>'; ?></div><?php endif; ?>

  <?php if (!$done): ?>
    <div class="st <?= $existe ? 'upd' : 'new' ?>">
      <?php if ($existe): ?>
        ⚠ Une page avec le slug <strong><?= htmlspecialchars($SLUG) ?></strong> existe déjà
        (« <?= htmlspecialchars($existe['titre']) ?> »). Elle sera <strong>mise à jour</strong>.
      <?php else: ?>
        ✨ Aucune page <strong><?= htmlspecialchars($SLUG) ?></strong> pour l'instant — elle sera <strong>créée</strong>.
      <?php endif; ?>
    </div>

    <form method="POST">
      <fieldset>
        <legend>Options de publication</legend>
        <label class="c"><input type="checkbox" name="visible" checked> Page visible <small>— accessible sur le site</small></label>
        <label class="c"><input type="checkbox" name="dans_menu" checked> Afficher dans le menu <small>— nouvel onglet</small></label>
        <label class="c"><input type="checkbox" name="copy_widgets" <?= $src_widgets ? 'checked' : 'disabled' ?>>
          Recopier les widgets de « Mobilisation »
          <small>— <?= $src_widgets ? count($src_widgets).' widget(s) : '.htmlspecialchars(implode(', ', array_column($src_widgets,'widget_slug'))) : 'aucun widget trouvé' ?></small>
        </label>
        <div class="fo"><span>Ordre dans le menu :</span><input type="number" name="ordre" value="1" min="0" max="99"><small style="color:#999">1 = en premier</small></div>
      </fieldset>

      <button type="submit" name="go" value="1" class="btn">
        <?= $existe ? '✏ Mettre à jour la page' : '✅ Créer la page' ?>
      </button>
    </form>
  <?php else: ?>
    <div class="links">
      <a href="/?page=<?= htmlspecialchars($SLUG) ?>" target="_blank">👁 Voir la page</a>
      <a href="/admin/pages.php" target="_blank">⚙ Gérer les pages</a>
      <a href="/reset-sw.php" target="_blank">🔄 Vider le cache</a>
    </div>
  <?php endif; ?>

  <div class="warn">
    <strong>⚠ À faire après usage</strong><br>
    1. Supprimer ce fichier <code>outils-page-nous-agissons.php</code> du serveur (règle de sécurité du projet).<br>
    2. Passer <code>montant_objectif</code> à <strong>40000</strong> dans Admin → Paramètres, sinon la barre de progression
    affichera encore 20 000 € alors que la page annonce 40 000 €.
  </div>
</div>
</body>
</html>
