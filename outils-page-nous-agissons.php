<?php
/* outils-page-nous-agissons.php — v2 (contenu combiné + 2 modes)
 * Outil PONCTUEL : crée la page « Nous agissons » dans la table `pages`.
 * ⚠ À SUPPRIMER après usage (règle de sécurité du projet).
 * Placé à la racine car le service worker met en cache /admin/.
 */
require_once __DIR__ . '/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!function_exists('isAdminLoggedIn') || !isAdminLoggedIn()) {
    http_response_code(403);
    echo '<!DOCTYPE html><meta charset="utf-8"><div style="font-family:Arial;max-width:520px;margin:60px auto;padding:26px 30px;background:#fff;border-radius:12px;box-shadow:0 2px 14px rgba(0,0,0,.1)">'
       . '<h2 style="color:#0e3d6b;margin:0 0 10px;font-size:1.1rem">🔒 Accès refusé</h2>'
       . '<p style="color:#555;font-size:.9rem;line-height:1.6">Vous devez être connecté à l\'administration pour utiliser cet outil.</p>'
       . '<p style="font-size:.8rem;color:#999">Session PHP : ' . session_status() . ' · ID : ' . (session_id() ?: 'aucun') . '</p>'
       . '<a href="/admin/login.php" style="display:inline-block;margin-top:8px;background:#FF9900;color:#fff;font-weight:700;padding:11px 24px;border-radius:8px;text-decoration:none;font-size:.9rem">Se connecter →</a>'
       . '</div>';
    exit;
}

$SLUG_MOB  = 'mobilisation';     // page existante
$SLUG_NEW  = 'nous-agissons';    // nouvelle page (mode alternatif)
$TITRE_NEW = 'Nous agissons';

$CONTENU = <<<'HTML'
<div class="cadre-bleu">
  <strong>Chers membres,</strong><br>
  La Région de Bruxelles-Capitale a engagé une <strong>action en cessation contre la procédure RNP 07L</strong>. Si elle aboutit sans réforme des normes de vent, le trafic ne disparaîtra pas : il sera simplement reporté — notamment sur la piste 01. Il est urgent de réagir et de <strong>ne pas laisser les riverains de la piste 01 payer la facture des erreurs passées.</strong>
</div>

<h2 class="section-title">Piste 01 : nous agissons</h2>

<p>Ces derniers jours, <strong>Piste 01 Ça Suffit !</strong> a procédé à une analyse approfondie de la situation juridique actuelle autour de Brussels Airport. Nous avons étudié en détail les citations introduites par des riverains flamands, ainsi que celle de la <strong>Région de Bruxelles-Capitale contre l'État belge</strong>.</p>

<p>Ces procédures peuvent avoir des conséquences qui dépassent largement les territoires concernés par les demandes initiales, et modifier l'équilibre actuel <strong>au détriment des populations situées sous l'axe de la piste 01</strong>.</p>

<div class="cadre-vert">
  <div class="cv-titre">Ce que nous avons fait</div>
  <ul>
    <li>Analyse approfondie de la situation juridique autour de Brussels Airport</li>
    <li>Étude détaillée des citations introduites par des riverains flamands</li>
    <li>Étude de la citation de la Région de Bruxelles-Capitale contre l'État belge</li>
    <li>Prise de contact avec plusieurs communes situées sous l'axe de la piste 01</li>
  </ul>
</div>

<p>Pour des raisons évidentes, nous ne dévoilerons pas publiquement le contenu de notre analyse ni notre stratégie. En revanche, nous mettons à la disposition des communes concernées les documents dont nous disposons, nos analyses techniques et juridiques, ainsi que le travail accumulé par notre association depuis de nombreuses années.</p>

<h2 class="section-title">Notre position n'a pas changé</h2>

<p>Aucun citoyen ne souhaite être survolé de manière injuste et récurrente. C'est le combat des habitants de l'Est de Bruxelles, de Waterloo, Lasne, Braine-l'Alleud, La Hulpe et des communes limitrophes depuis plus de vingt ans.</p>

<div class="cadre-bleu">
  <strong>Depuis près de 15 ans, nous défendons la même idée : le problème n'est pas la piste 01. Le problème est global.</strong><br>
  Il réside dans les normes de vent, telles qu'elles ont été dévoyées depuis 2003, et dans la <strong>sortie trop rapide du système préférentiel de pistes (PRS)</strong>.
</div>

<ul>
  <li><strong>Maintenir le PRS aussi longtemps que les conditions de vent et de sécurité le permettent</strong> — les pistes 25 ne survolent personne sur les derniers kilomètres.</li>
  <li>Lorsque les conditions imposent réellement d'en sortir, ce sont <strong>la sécurité, le vent réel et les contraintes opérationnelles</strong> qui doivent déterminer la piste appropriée.</li>
  <li>Revenir à la norme historique : <strong>8 nœuds de vent arrière, sans notion de rafale</strong> — appliquée pendant plus de 30 ans, et toujours en vigueur à Charleroi.</li>
</ul>

<p><strong>Nous ne demandons donc pas de déplacer les nuisances d'une population vers une autre.</strong></p>

<h2 class="section-title">Ce que notre procédure en référé a obtenu</h2>

<div class="cadre-vert">
  <div class="cv-titre">Résultats judiciaires</div>
  <ul>
    <li>Le juge a reconnu (une nouvelle fois) <strong>l'illégalité de l'instruction du 16 décembre 2013</strong> et ses effets persistants</li>
  </ul>
</div>

<p>Sur la forme, le tribunal a estimé que l'urgence n'était plus réunie à ce stade. Nous l'acceptons. Mais pour combien de temps ?</p>

<div class="alerte">
  <div class="al-titre">⚠ Notre vigilance</div>
  <p>Il apparaît aujourd'hui que certains défendent une vision différente. Si les procédures en cours conduisent à déplacer les avions vers la 01 plutôt qu'à corriger les normes de vent, un nouveau contentieux pourrait aboutir à une <strong>réactivation massive et durable de la piste 01</strong>. Nous serons extrêmement attentifs à ce que ces procédures ne protègent pas certaines populations en reportant simplement les nuisances sur d'autres.</p>
</div>

<p>Waterloo, Braine-l'Alleud, l'Est de Bruxelles, Kraainem, Wezembeek-Oppem et les autres populations concernées par les approches de la piste 01 <strong>doivent elles aussi être entendues</strong>.</p>

<h2 class="section-title">Ce que vous pouvez faire</h2>

<div class="actions-grid">
  <div class="action-card">
    <div class="ac-num">01</div>
    <div class="ac-titre">Porter le bon message</div>
    <p class="ac-text">Le problème n'est pas une piste ou une autre, mais <strong>les normes de vent</strong> dévoyées depuis 2003.</p>
  </div>
  <div class="action-card">
    <div class="ac-num">02</div>
    <div class="ac-titre">Interpeller les élus</div>
    <p class="ac-text">Demandez à vos autorités locales d'exiger la seule solution : <strong>effacer les erreurs de 2003</strong> et revenir à 8 nœuds sans rafales.</p>
  </div>
  <div class="action-card">
    <div class="ac-num">03</div>
    <div class="ac-titre">Nous soutenir</div>
    <p class="ac-text">Face à l'État et ses moyens considérables, votre don nous permet de <strong>rester debout et crédibles</strong> dans cette bataille judiciaire.</p>
  </div>
</div>

<h2 class="section-title">Pour continuer, nous avons besoin de vous</h2>

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

<p>Nous saluons par ailleurs la prise de position courageuse de <strong>Florence Reuter</strong>, qui a publiquement relayé ce message dans la presse et sur les réseaux sociaux.</p>

<div class="cadre-bleu">
  <strong>Notre combat n'est pas 01 contre 07.</strong><br>
  Notre combat est celui d'une politique aéroportuaire cohérente, sûre et équitable, qui ne consiste pas à déplacer les nuisances d'une population vers une autre.
</div>
HTML;

$META = "Piste 01 Ça Suffit ! agit face aux procédures judiciaires en cours autour de Brussels Airport : analyses juridiques, contacts avec les communes et défense d'une politique aéroportuaire équitable.";

$db   = getDB();
$log  = array();
$done = false;

// ── État actuel des deux pages ───────────────────────────────────────────
function page_row($db, $slug) {
    try { $s=$db->prepare("SELECT id,slug,titre,visible,dans_menu,ordre,LENGTH(contenu) AS taille FROM pages WHERE slug=?");
          $s->execute(array($slug)); return $s->fetch(); } catch (Exception $e) { return null; }
}
$row_mob = page_row($db, $SLUG_MOB);
$row_new = page_row($db, $SLUG_NEW);

// Widgets de la page mobilisation (pour recopie si nouvelle page)
$src_widgets = array();
try {
    $s=$db->prepare("SELECT widget_slug, ordre, COALESCE(position,'droite') AS position FROM page_widgets WHERE page_slug=? ORDER BY ordre ASC");
    $s->execute(array($SLUG_MOB)); $src_widgets=$s->fetchAll();
} catch (Exception $e) {}

// ── Action ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['go'])) {
    $mode = ($_POST['mode'] ?? 'update') === 'new' ? 'new' : 'update';
    try {
        if ($mode === 'update') {
            if (!$row_mob) throw new Exception("La page « $SLUG_MOB » est introuvable.");

            // Sauvegarde de l'ancien contenu dans une page masquée
            if (!empty($_POST['backup'])) {
                $s=$db->prepare("SELECT contenu, titre FROM pages WHERE slug=?");
                $s->execute(array($SLUG_MOB)); $old=$s->fetch();
                $bslug = 'mobilisation-sauvegarde-'.date('Ymd-Hi');
                $db->prepare("INSERT INTO pages (slug,titre,contenu,meta_description,ordre,visible,dans_menu,icone,css_class,menu_position,lien_url,affichage_menu,btn_style,parent_id,updated_by)
                              VALUES (?,?,?,?,?,0,0,'','','all','','texte','',NULL,?)")
                   ->execute(array($bslug, 'Sauvegarde — '.$old['titre'].' ('.date('d/m/Y H:i').')', $old['contenu'], '', 99, ADMIN_USER));
                $log[] = "💾 Ancien contenu sauvegardé dans la page masquée « $bslug ».";
            }

            $db->prepare("UPDATE pages SET contenu=?, meta_description=?, updated_by=? WHERE slug=?")
               ->execute(array($CONTENU, $META, ADMIN_USER, $SLUG_MOB));
            $log[] = "✅ Page « $SLUG_MOB » mise à jour avec le contenu combiné.";
            $log[] = "ℹ Titre, position dans le menu et widgets inchangés.";
            $target = $SLUG_MOB;

        } else {
            $visible   = isset($_POST['visible'])   ? 1 : 0;
            $dans_menu = isset($_POST['dans_menu']) ? 1 : 0;
            $ordre     = (int)($_POST['ordre'] ?? 1);

            if ($row_new) {
                $db->prepare("UPDATE pages SET titre=?, contenu=?, meta_description=?, ordre=?, visible=?, dans_menu=?, updated_by=? WHERE slug=?")
                   ->execute(array($TITRE_NEW, $CONTENU, $META, $ordre, $visible, $dans_menu, ADMIN_USER, $SLUG_NEW));
                $log[] = "✏ Page « $SLUG_NEW » mise à jour (elle existait déjà).";
            } else {
                $db->prepare("INSERT INTO pages (slug,titre,contenu,meta_description,ordre,visible,dans_menu,icone,css_class,menu_position,lien_url,affichage_menu,btn_style,parent_id,updated_by)
                              VALUES (?,?,?,?,?,?,?,'','','all','','texte','',NULL,?)")
                   ->execute(array($SLUG_NEW, $TITRE_NEW, $CONTENU, $META, $ordre, $visible, $dans_menu, ADMIN_USER));
                $log[] = "✅ Page « $SLUG_NEW » créée (id ".$db->lastInsertId().").";
            }
            if (!empty($_POST['copy_widgets']) && $src_widgets) {
                $db->prepare("DELETE FROM page_widgets WHERE page_slug=?")->execute(array($SLUG_NEW));
                $ins=$db->prepare("INSERT INTO page_widgets (page_slug,widget_slug,ordre,position) VALUES (?,?,?,?)");
                foreach ($src_widgets as $w) $ins->execute(array($SLUG_NEW,$w['widget_slug'],$w['ordre'],$w['position']));
                $log[] = "✅ ".count($src_widgets)." widget(s) recopié(s) depuis « $SLUG_MOB ».";
            }
            $log[] = "ℹ La page « $SLUG_MOB » n'a pas été modifiée.";
            $target = $SLUG_NEW;
        }
        $done = true;
    } catch (Exception $e) { $log[] = '❌ Erreur : '.$e->getMessage(); }
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
.fix{background:#eff6ff;border:1px solid #cfe2fb;color:#1673B2;padding:10px 13px;border-radius:8px;font-size:.8rem;line-height:1.55;margin-bottom:12px}
.fix code{background:#dbeafe;padding:1px 5px;border-radius:4px;font-family:ui-monospace,Menlo,monospace;font-size:.92em}
.links a{color:#1673B2;font-weight:700;text-decoration:none;font-size:.88rem;margin-right:16px}
label.mode{display:flex;gap:11px;align-items:flex-start;padding:13px 15px;border:2px solid #e3ecf6;border-radius:10px;margin-bottom:10px;cursor:pointer;transition:border-color .15s,background .15s}
label.mode:has(input:checked){border-color:#FF9900;background:#fffaf2}
label.mode input{margin-top:3px;width:17px;height:17px;flex-shrink:0;cursor:pointer}
label.mode strong{display:block;font-size:.92rem;color:#0e3d6b;margin-bottom:3px}
label.mode small{color:#888;font-size:.78rem;line-height:1.5;display:block}
details summary{cursor:pointer;font-size:.85rem;color:#1673B2;font-weight:700;padding:8px 0}
.prev{border:1.5px solid #e3ecf6;border-radius:10px;padding:16px 20px;margin-top:10px;max-height:420px;overflow-y:auto;background:#fcfdff;font-size:.85rem;line-height:1.6;color:#444}
.prev h2,.prev .section-title{color:#FF9900;font-weight:400;font-size:1.05rem;border-bottom:1px solid #c8dff0;padding-bottom:6px;margin:20px 0 10px}
.prev .cadre-bleu{padding:10px 14px;background:#e8f3fb;border-left:4px solid #1673B2;color:#1673B2;margin:10px 0}
.prev .cadre-vert{padding:10px 14px;background:#e8f5e9;border-left:4px solid #2e7d32;margin:10px 0}
.prev .cadre-vert ul{list-style:none;padding:0}
.prev .cadre-vert li{color:#2e7d32;font-size:90%}
.prev .cadre-vert li::before{content:"\2713  ";font-weight:700}
.prev .cv-titre{font-weight:700;color:#1b5e20;font-size:75%;text-transform:uppercase;margin-bottom:6px}
.prev .cadre-orange{padding:10px 14px;background:#FF9900;color:#fff;margin:10px 0}
.prev .alerte{padding:10px 14px;border:2px solid #FF9900;background:#fff8ee;margin:10px 0}
.prev .al-titre{font-weight:700;color:#cc7a00;font-size:82%;text-transform:uppercase;margin-bottom:5px}
.prev .alerte p{color:#7a4500}
.prev .actions-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin:10px 0}
.prev .action-card{background:#e8f3fb;border-top:3px solid #1673B2;padding:11px}
.prev .ac-num{font-size:1.2rem;font-weight:700;color:#c8dff0}
.prev .ac-titre{font-weight:600;color:#1673B2;font-size:88%;margin:3px 0}
.prev .ac-text{font-size:78%;color:#555;margin:0}
</style>
</head>
<body>
<div class="box">
  <h1>📄 Publier le nouveau contenu « Mobilisation »</h1>
  <div class="sub">Contenu combiné : page Mobilisation actuelle + communiqué « Nous agissons ».</div>

  <?php if ($log): ?><div class="log"><?php foreach ($log as $l) echo htmlspecialchars($l).'<br>'; ?></div><?php endif; ?>

  <?php if (!$done): ?>
    <form method="POST">
      <fieldset>
        <legend>Que voulez-vous faire ?</legend>

        <label class="mode">
          <input type="radio" name="mode" value="update" checked onchange="maj()">
          <span>
            <strong>Mettre à jour la page « Mobilisation »</strong>
            <small>Remplace le contenu de l'onglet principal. Titre, menu et widgets conservés.
            <?= $row_mob ? '(page trouvée — '.number_format($row_mob['taille']).' caractères actuellement)' : '<span style="color:#a5352a">⚠ page introuvable</span>' ?></small>
          </span>
        </label>

        <label class="mode">
          <input type="radio" name="mode" value="new" onchange="maj()">
          <span>
            <strong>Créer une nouvelle page « Nous agissons »</strong>
            <small>La page Mobilisation reste strictement inchangée.
            <?= $row_new ? '(une page « '.htmlspecialchars($SLUG_NEW).' » existe déjà — elle sera mise à jour)' : '' ?></small>
          </span>
        </label>
      </fieldset>

      <fieldset id="opt-update">
        <legend>Options — mise à jour</legend>
        <div class="fix">🎨 <strong>Correctif CSS appliqué</strong> — les titres utilisent désormais la classe
        <code>section-title</code> (orange + filet), comme sur le reste du site. Relancez la mise à jour pour l'appliquer.</div>
        <label class="c"><input type="checkbox" name="backup"> Sauvegarder l'ancien contenu
          <small>— copié dans une page masquée, restaurable depuis Admin → Pages</small></label>
      </fieldset>

      <fieldset id="opt-new" style="display:none">
        <legend>Options — nouvelle page</legend>
        <label class="c"><input type="checkbox" name="visible" checked> Page visible</label>
        <label class="c"><input type="checkbox" name="dans_menu" checked> Afficher dans le menu</label>
        <label class="c"><input type="checkbox" name="copy_widgets" <?= $src_widgets ? 'checked' : 'disabled' ?>>
          Recopier les widgets de « Mobilisation »
          <small>— <?= $src_widgets ? count($src_widgets).' widget(s)' : 'aucun trouvé' ?></small></label>
        <div class="fo"><span>Ordre dans le menu :</span><input type="number" name="ordre" value="1" min="0" max="99"></div>
      </fieldset>

      <details style="margin-bottom:18px">
        <summary>👁 Prévisualiser le contenu qui sera publié</summary>
        <div class="prev"><?= $CONTENU ?></div>
      </details>

      <button type="submit" name="go" value="1" class="btn" id="btn-go">✅ Publier le contenu combiné</button>
    </form>

    <script>
    function maj(){
      var m = document.querySelector('[name=mode]:checked').value;
      document.getElementById('opt-update').style.display = (m==='update') ? '' : 'none';
      document.getElementById('opt-new').style.display    = (m==='new')    ? '' : 'none';
      document.getElementById('btn-go').textContent =
        (m==='update') ? '✅ Mettre à jour la page Mobilisation' : '✅ Créer la page « Nous agissons »';
    }
    maj();
    </script>
  <?php else: ?>
    <div class="links">
      <a href="/?page=<?= htmlspecialchars($target ?? $SLUG_MOB) ?>" target="_blank">👁 Voir la page</a>
      <a href="/admin/pages.php" target="_blank">⚙ Gérer les pages</a>
      <a href="/reset-sw.php" target="_blank">🔄 Vider le cache</a>
    </div>
  <?php endif; ?>

  <div class="warn">
    <strong>⚠ À faire ensuite</strong><br>
    1. Passer <code>montant_objectif</code> de <strong>35000</strong> à <strong>40000</strong> dans Admin → Paramètres,
       sinon la barre affichera 35 000 € alors que la page annonce 40 000 €.<br>
    2. Supprimer ce fichier <code>outils-page-nous-agissons.php</code> du serveur (règle de sécurité du projet).
  </div>
</div>
</body>
</html>
