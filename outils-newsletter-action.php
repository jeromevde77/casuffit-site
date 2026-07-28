<?php
/* outils-newsletter-action.php — v1
 * Crée en BROUILLON la newsletter d'appel à participation à l'action.
 * ⚠ À SUPPRIMER après usage.
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

$SUJET = "Souhaitez-vous participer en votre nom à notre action en justice ?";

$CONTENU = <<<'HTML'
<p>Chers membres,</p>

<p>La <strong>Région de Bruxelles-Capitale a engagé une action en cessation contre la procédure RNP 07L</strong>. Si elle aboutit sans réforme des normes de vent, le trafic ne disparaîtra pas : il sera reporté — notamment sur la piste 01, au-dessus de nos communes.</p>

<p>Nous avons analysé en détail les procédures en cours et pris contact avec plusieurs communes situées sous l'axe de la piste 01. Nous préparons désormais notre propre intervention judiciaire.</p>

<h2 style="color:#FF9900;font-size:17px;font-weight:600;border-bottom:1px solid #c8dff0;padding-bottom:6px;margin:24px 0 10px">Nous avons besoin de vous — de votre nom</h2>

<p>Devant un tribunal, une association pèse. <strong>Des centaines de riverains agissant en leur nom propre pèsent infiniment plus.</strong> Chaque personne qui se joint à la démarche rend notre voix plus difficile à ignorer.</p>

<div style="padding:14px 18px;background:#e8f3fb;border-left:4px solid #1673B2;color:#1673B2;margin:18px 0">
  <strong>Comment nous le faire savoir</strong><br>
  Connectez-vous à votre espace membre et cochez simplement la case
  « <em>Je souhaite être contacté(e) pour participer à l'action en mon nom</em> ».
</div>

<p>Cela <strong>ne vous engage à rien aujourd'hui</strong>. Rien ne sera introduit sans votre accord écrit et signé, que nous vous transmettrons ultérieurement. Vous pouvez décocher à tout moment.</p>

<p>Votre <strong>adresse complète</strong> doit être renseignée dans votre profil : elle établit votre intérêt à agir, c'est-à-dire le fait que vous êtes réellement survolé(e).</p>

<p style="text-align:center;margin:26px 0">
  <a href="https://www.casuffit.be/membre/login.php" style="display:inline-block;background:#1673B2;color:#fff;font-weight:700;font-size:15px;padding:14px 30px;border-radius:8px;text-decoration:none">→ Accéder à mon espace membre</a>
</p>

<h2 style="color:#FF9900;font-size:17px;font-weight:600;border-bottom:1px solid #c8dff0;padding-bottom:6px;margin:28px 0 10px">La justice a un coût</h2>

<p>Analyser les procédures, consulter des spécialistes, mobiliser des avocats, intervenir devant le tribunal : tout cela représente des sommes considérables pour une ASBL de riverains.</p>

<p><strong>Nous n'avons pas les moyens d'une Région.</strong> Face à des institutions qui disposent de budgets et de services juridiques entiers, nous n'avons que vous. Chaque don, chaque adhésion, chaque euro nous permet de rester dans la partie.</p>

<p style="text-align:center;margin:26px 0">
  <a href="https://www.casuffit.be/don.php" style="display:inline-block;background:#FF9900;color:#fff;font-weight:700;font-size:15px;padding:14px 30px;border-radius:8px;text-decoration:none">→ Soutenir notre action</a>
</p>

<p>Merci pour votre confiance et votre soutien.</p>

<p><strong>Piste 01 Ça Suffit !</strong></p>
HTML;

$db = getDB();
$log = array(); $done = false; $nid = null;

// Vérifier si un brouillon identique existe déjà
$deja = null;
try {
    $s = $db->prepare("SELECT id, statut FROM newsletters WHERE sujet=? ORDER BY id DESC LIMIT 1");
    $s->execute(array($SUJET)); $deja = $s->fetch();
} catch (Exception $e) { $log[] = '⚠ '.$e->getMessage(); }

// Vérifier que la migration DB est faite (sinon la case membre ne marchera pas)
$migre = false;
try {
    foreach ($db->query("SHOW COLUMNS FROM members")->fetchAll() as $c)
        if ($c['Field'] === 'action_participe') { $migre = true; break; }
} catch (Exception $e) {}

$nb_membres = 0;
try { $nb_membres = (int)$db->query("SELECT COUNT(*) FROM subscribers WHERE statut='actif'")->fetchColumn(); } catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['go'])) {
    try {
        if ($deja && !empty($_POST['ecraser'])) {
            $db->prepare("UPDATE newsletters SET contenu_html=?, statut='brouillon' WHERE id=?")
               ->execute(array($CONTENU, $deja['id']));
            $nid = $deja['id'];
            $log[] = "✏ Brouillon existant (id $nid) mis à jour.";
        } else {
            $db->prepare("INSERT INTO newsletters (sujet, contenu_html, statut) VALUES (?,?,'brouillon')")
               ->execute(array($SUJET, $CONTENU));
            $nid = $db->lastInsertId();
            $log[] = "✅ Newsletter créée en brouillon (id $nid).";
        }
        $log[] = "ℹ Aucun envoi n'a été déclenché — relisez puis envoyez depuis Rédaction.";
        $done = true;
    } catch (Exception $e) { $log[] = '❌ '.$e->getMessage(); }
}
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><title>Newsletter — Appel à participation</title>
<style>
body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333;margin:0;padding:36px 18px}
.box{max-width:780px;margin:0 auto;background:#fff;border-radius:14px;box-shadow:0 2px 14px rgba(0,0,0,.09);padding:28px 32px}
h1{font-size:1.2rem;color:#0e3d6b;margin:0 0 6px}.sub{font-size:.84rem;color:#888;margin-bottom:20px}
.chk{padding:12px 16px;border-radius:9px;font-size:.84rem;margin-bottom:12px;line-height:1.55}
.chk.ok{background:#e8f8f0;color:#1a6e3c;border:1px solid #a8e6c0}
.chk.no{background:#fdecea;color:#922b21;border:1px solid #f5b7b1}
.chk.info{background:#eff6ff;color:#1673B2;border:1px solid #cfe2fb}
.sujet{background:#f7fafd;border:1.5px solid #e3ecf6;border-radius:9px;padding:12px 16px;font-size:.9rem;margin-bottom:16px}
.sujet b{color:#0e3d6b}
details{margin-bottom:18px}summary{cursor:pointer;font-size:.85rem;color:#1673B2;font-weight:700;padding:8px 0}
.prev{border:1.5px solid #e3ecf6;border-radius:10px;padding:20px 24px;background:#fcfdff;max-height:460px;overflow-y:auto;font-size:.88rem;line-height:1.65;color:#444}
.prev p{margin:0 0 11px}
label.c{display:flex;align-items:center;gap:9px;font-size:.86rem;margin:12px 0;cursor:pointer}
label.c input{width:17px;height:17px}
.btn{padding:12px 26px;border:none;border-radius:9px;font-size:.9rem;font-weight:700;cursor:pointer;background:#FF9900;color:#fff;font-family:inherit}
.btn:hover{background:#e08800}
.log{background:#0e2438;color:#d6e4f0;padding:14px 18px;border-radius:9px;font-size:.82rem;line-height:1.8;margin-bottom:18px}
.warn{background:#fdecea;border:1px solid #f5b7b1;color:#922b21;padding:13px 16px;border-radius:9px;font-size:.82rem;margin-top:20px;line-height:1.6}
a.lnk{color:#1673B2;font-weight:700;text-decoration:none;font-size:.88rem;margin-right:16px}
</style></head><body>
<div class="box">
  <h1>✉ Newsletter — Appel à participation</h1>
  <div class="sub">Crée la newsletter <strong>en brouillon</strong>. Aucun envoi automatique.</div>

  <?php if ($log): ?><div class="log"><?php foreach($log as $l) echo htmlspecialchars($l).'<br>'; ?></div><?php endif; ?>

  <?php if (!$done): ?>
    <div class="chk <?= $migre ? 'ok' : 'no' ?>">
      <?= $migre
        ? '✅ Base de données prête — la case à cocher fonctionne dans l\'espace membre.'
        : '❌ <strong>Migration non effectuée !</strong> Lancez d\'abord <code>outils-action-migration.php</code>, sinon les membres ne pourront pas cocher la case.' ?>
    </div>
    <div class="chk info">📬 <strong><?= $nb_membres ?></strong> abonné(s) actif(s) recevront cette newsletter si vous ne ciblez aucune commune.</div>
    <?php if ($deja): ?>
      <div class="chk info">ℹ Une newsletter avec ce sujet existe déjà (id <?= $deja['id'] ?>, statut « <?= htmlspecialchars($deja['statut']) ?> »).</div>
    <?php endif; ?>

    <div class="sujet"><b>Objet :</b> <?= htmlspecialchars($SUJET) ?></div>

    <details open><summary>👁 Aperçu du contenu</summary><div class="prev"><?= $CONTENU ?></div></details>

    <form method="POST">
      <?php if ($deja): ?>
        <label class="c"><input type="checkbox" name="ecraser" checked> Mettre à jour le brouillon existant plutôt que d'en créer un nouveau</label>
      <?php endif; ?>
      <button type="submit" name="go" value="1" class="btn">✉ Créer le brouillon</button>
    </form>
  <?php else: ?>
    <div>
      <a class="lnk" href="/admin/compose.php?id=<?= (int)$nid ?>" target="_blank">✏ Ouvrir dans Rédaction</a>
      <a class="lnk" href="/admin/newsletters.php" target="_blank">📋 Toutes les newsletters</a>
    </div>
  <?php endif; ?>

  <div class="warn">
    <strong>⚠ Avant d'envoyer</strong><br>
    1. Vérifiez que la migration DB est faite et testez la case dans votre propre espace membre.<br>
    2. Dans Rédaction, envoyez-vous d'abord un <strong>test</strong>.<br>
    3. Ne cochez <strong>aucune commune</strong> dans le ciblage pour toucher tous les membres.<br>
    4. Supprimez ensuite les fichiers <code>outils-*.php</code> du serveur.
  </div>
</div></body></html>
