<?php
// admin/compose.php — Compositeur de newsletter avec aperçu HTML
require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();
$db = getDB();

$msg = ''; $error = '';

// ── Sauvegarder le brouillon ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_draft'])) {
    $sujet   = htmlspecialchars(trim($_POST['sujet'] ?? ''), ENT_QUOTES, 'UTF-8');
    $contenu = $_POST['contenu_html'] ?? '';
    if (empty($sujet)) { $error = 'Le sujet est obligatoire.'; }
    else {
        $id = intval($_POST['nl_id'] ?? 0);
        if ($id > 0) {
            $db->prepare("UPDATE newsletters SET sujet=?, contenu_html=? WHERE id=? AND statut='brouillon'")
               ->execute(array($sujet, $contenu, $id));
        } else {
            $db->prepare("INSERT INTO newsletters (sujet, contenu_html, statut) VALUES (?,?,'brouillon')")
               ->execute(array($sujet, $contenu));
            $id = $db->lastInsertId();
        }
        header('Location: compose.php?id='.$id.'&msg='.urlencode('Brouillon sauvegardé.')); exit;
    }
}

// ── Envoyer la newsletter ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_newsletter'])) {
    $id = intval($_POST['nl_id'] ?? 0);
    if ($id > 0) {
        // Mettre en file d'envoi
        $abonnes = $db->query("SELECT id FROM subscribers WHERE statut='actif'")->fetchAll();
        $db->prepare("UPDATE newsletters SET statut='envoi' WHERE id=?")->execute(array($id));
        $stmt = $db->prepare("INSERT INTO send_queue (newsletter_id, subscriber_id, statut) VALUES (?,?,'en_attente')");
        foreach ($abonnes as $a) { $stmt->execute(array($id, $a['id'])); }
        header('Location: newsletters.php?msg='.urlencode('Envoi lancé pour '.count($abonnes).' abonnés.')); exit;
    }
}

// Charger une newsletter existante
$nl = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $nl = $db->prepare("SELECT * FROM newsletters WHERE id=?");
    $nl->execute(array(intval($_GET['id'])));
    $nl = $nl->fetch();
}
$msg = $_GET['msg'] ?? $msg;

// Infos site pour les templates
$site_nom = cfg('site_nom', 'ça suffit ! ASBL');
$iban     = cfg('iban', 'BE41 0689 0149 6910');
$annee    = date('Y');

// Calculer le montant récolté total
$montant_initial = floatval(cfg('montant_initial', 0));
$dons_confirmes  = 0;
try {
    $dons_confirmes = floatval($db->query("SELECT COALESCE(SUM(montant),0) FROM member_dons WHERE statut='confirme'")->fetchColumn());
} catch (Exception $e) {}
$recolte_total = $montant_initial + $dons_confirmes;

// Charger les widgets disponibles
$all_widgets = array();
try {
    $all_widgets = $db->query("SELECT * FROM widgets WHERE actif=1 ORDER BY id")->fetchAll();
} catch (Exception $e) {}

// Compter les abonnés actifs
$nb_abonnes = $db->query("SELECT COUNT(*) FROM subscribers WHERE statut='actif'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<?php include __DIR__ . '/../includes/admin_pwa_head.php'; ?>
  <title>Composer newsletter — Admin</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333}
    <?php include __DIR__ . '/../includes/admin_sidebar_css.php'; ?>

    .wrap{margin-left:240px;display:grid;grid-template-columns:1fr 420px;height:100vh;overflow:hidden}

    /* Éditeur */
    .editor{background:#fff;border-right:1px solid #e0e8f0;display:flex;flex-direction:column;overflow:hidden}
    .editor-head{padding:14px 18px;background:#1673B2;color:#fff;flex-shrink:0}
    .editor-head h2{font-size:.92rem;font-weight:700}
    .editor-head p{font-size:.68rem;color:rgba(255,255,255,.6);margin-top:2px}
    .editor-body{flex:1;overflow-y:auto;padding:16px}
    .editor-foot{padding:10px 16px;border-top:1px solid #eee;background:#fafbfc;display:flex;gap:8px;align-items:center;flex-wrap:wrap;flex-shrink:0}

    label{display:block;font-size:.7rem;font-weight:600;color:#555;margin-bottom:3px;margin-top:12px;text-transform:uppercase;letter-spacing:.04em}
    label:first-child{margin-top:0}
    input[type=text]{width:100%;padding:8px 10px;border:1.5px solid #dde4ed;border-radius:6px;font-size:.85rem;outline:none;font-family:inherit}
    input:focus{border-color:#1673B2}

    .btn{padding:7px 14px;border-radius:6px;font-size:.78rem;font-weight:600;cursor:pointer;border:1.5px solid transparent;font-family:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:all .15s;line-height:1}
.btn-p{background:#1673B2;color:#fff;border-color:#1673B2}
.btn-p:hover{background:#125a90;color:#fff;text-decoration:none}
.btn-g{background:#f0f4f8;color:#555;border-color:#dde4ed}
.btn-g:hover{background:#e0e8f0;color:#333;text-decoration:none}
.btn-r{background:#fff5f5;color:#e53e3e;border-color:#fed7d7}
.btn-r:hover{background:#fee2e2;text-decoration:none}
.btn-sm{padding:4px 10px;font-size:.72rem}
.btn-apercu-mobile{display:none}
.btn-retour-mobile{display:none;font-size:.78rem;color:rgba(255,255,255,.85);text-decoration:none;font-weight:600;margin-bottom:8px;align-items:center;gap:4px}
    .flash-ok{background:#e8f8f0;color:#276749;padding:8px 12px;border-radius:6px;margin-bottom:10px;font-size:.78rem;border-left:3px solid #48bb78}
    .flash-err{background:#fde8e8;color:#c53030;padding:8px 12px;border-radius:6px;margin-bottom:10px;font-size:.78rem;border-left:3px solid #fc8181}

    /* Blocs template */
    .blocs-titre{font-size:.68rem;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px;margin-top:16px}
    .blocs-grid{display:grid;grid-template-columns:1fr 1fr;gap:5px;margin-bottom:12px}
    .bloc-btn{padding:7px 8px;border-radius:5px;font-size:.72rem;cursor:pointer;text-align:left;border:1.5px solid transparent;font-family:inherit;font-weight:500;transition:all .15s}
    .bloc-btn:hover{transform:translateY(-1px);box-shadow:0 2px 6px rgba(0,0,0,.1)}
    .bb-titre{background:#fff8e1;border-color:#FF9900;color:#a05000}
    .bb-texte{background:#e6f1fb;border-color:#1673B2;color:#0e3d6b}
    .bb-orange{background:#FF9900;border-color:#e68800;color:#fff}
    .bb-bleu{background:#e6f1fb;border-color:#b5d4f4;color:#1673B2}
    .bb-alerte{background:#fff8ee;border-color:#FF9900;color:#a05000}
    .bb-separateur{background:#f5f5f5;border-color:#ddd;color:#555}
    .bb-bouton{background:#1673B2;border-color:#125a90;color:#fff}
    .bb-signature{background:#f0f7ff;border-color:#bee3f8;color:#2c5282}

    .code-editor{font-family:'Courier New',monospace;font-size:.73rem;line-height:1.6;width:100%;padding:12px;border:1.5px solid #dde4ed;border-radius:6px;min-height:200px;resize:vertical;outline:none;background:#1e1e2e;color:#cdd6f4}
    .code-editor:focus{border-color:#1673B2}

    .abonnes-info{font-size:.72rem;color:#888;margin-left:auto}
    .abonnes-info strong{color:#27ae60}

    /* Aperçu */
    .preview{background:#f5f8fc;display:flex;flex-direction:column;overflow:hidden}
    .preview-head{padding:10px 16px;background:#fff;border-bottom:1px solid #e0e8f0;display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
    .preview-head h3{font-size:.82rem;font-weight:700;color:#0e3d6b}
    .preview-body{flex:1;overflow-y:auto;padding:16px}
    .email-frame{background:#fff;border-radius:8px;box-shadow:0 2px 12px rgba(0,0,0,.08);max-width:580px;margin:0 auto;overflow:hidden}
  
  @media (max-width: 768px) {
    .wrap { margin-left: 0 !important; padding-top: 52px !important; }
    .grid2, .cards-grid { grid-template-columns: 1fr !important; }
    table { font-size: .75rem; }
    table th, table td { padding: 6px 8px !important; }
    .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
  }
.act-btn{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;border:1.5px solid;cursor:pointer;text-decoration:none;transition:all .15s;background:none;font-family:inherit;flex-shrink:0}
.act-btn.edit{color:#4a5568;border-color:#e2e8f0;background:#f7f8fa}
.act-btn.edit:hover{background:#edf2f7;border-color:#cbd5e0;color:#2d3748;text-decoration:none}
.act-btn.del{color:#e53e3e;border-color:#fed7d7;background:#fff5f5}
.act-btn.del:hover{background:#fee2e2;border-color:#fc8181;text-decoration:none}
.act-btn.view{color:#38a169;border-color:#c6f6d5;background:#f0fff4}
.act-btn.view:hover{background:#dcfce7;text-decoration:none}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

<div class="wrap">

  <!-- ÉDITEUR -->
  <div class="editor">
    <div class="editor-head">
      <h2>✉ Composer une newsletter</h2>
      <p>Aperçu en temps réel → avec les styles du site</p>
    </div>
    <div class="editor-body">
      <?php if ($msg): ?><div class="flash-ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="flash-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

      <form method="POST" id="nlf">
        <input type="hidden" name="nl_id" value="<?= $nl ? $nl['id'] : 0 ?>">

        <label>Sujet de l'email *</label>
        <input type="text" name="sujet" id="f-sujet" placeholder="Ex: Mise à jour — Action en référé"
               value="<?= htmlspecialchars($nl ? $nl['sujet'] : '') ?>" oninput="majApercu()">

        <!-- Blocs prêts à insérer -->
        <div class="blocs-titre">📦 Insérer un bloc</div>
        <div class="blocs-grid">
          <button type="button" class="bloc-btn bb-titre"   onclick="ins('titre')">📌 Titre section</button>
          <button type="button" class="bloc-btn bb-texte"   onclick="ins('texte')">📝 Paragraphe</button>
          <button type="button" class="bloc-btn bb-orange"  onclick="ins('orange')">🟠 Encadré orange</button>
          <button type="button" class="bloc-btn bb-bleu"    onclick="ins('bleu')">🔵 Encadré bleu</button>
          <button type="button" class="bloc-btn bb-alerte"  onclick="ins('alerte')">⚠ Alerte</button>
          <button type="button" class="bloc-btn bb-bouton"  onclick="ins('bouton')">🔗 Bouton</button>
          <button type="button" class="bloc-btn bb-separateur" onclick="ins('sep')">— Séparateur</button>
          <button type="button" class="bloc-btn bb-signature"  onclick="ins('sign')">✍ Signature</button>
        </div>

        <!-- Widgets du site -->
        <div class="blocs-titre">🧩 Insérer un widget du site</div>
        <div class="blocs-grid">
          <?php foreach ($all_widgets as $w): ?>
          <button type="button" class="bloc-btn" style="background:#f0f7ff;border-color:#bee3f8;color:#2c5282"
                  onclick="insWidget('<?= $w['slug'] ?>')">
            <?= htmlspecialchars($w['icone'] ?: '🧩') ?> <?= htmlspecialchars($w['titre']) ?>
          </button>
          <?php endforeach; ?>
        </div>

        <label>Contenu HTML</label>
        <textarea name="contenu_html" id="f-contenu" class="code-editor"
                  rows="20" oninput="majApercu()" spellcheck="false"><?= htmlspecialchars($nl ? $nl['contenu_html'] : '') ?></textarea>
      </form>
    </div>
    <div class="editor-foot">
      <button type="submit" form="nlf" name="save_draft" class="btn btn-p"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Sauvegarder</button>
      <?php if ($nl && $nl['statut'] === 'brouillon'): ?>
      <button type="submit" form="nlf" name="send_newsletter" class="btn btn-send"
              onclick="return confirm('Envoyer à <?= $nb_abonnes ?> abonnés actifs ?')">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> Envoyer (<?= $nb_abonnes ?> abonnés)
      </button>
      <?php endif; ?>
      <a href="newsletters.php" class="btn btn-g"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> Historique</a>
      <span class="abonnes-info"><strong><?= $nb_abonnes ?></strong> abonnés actifs</span>
    </div>
  </div>

  <!-- APERÇU EMAIL -->
  <div class="preview">
    <div class="preview-head">
      <h3>👁 Aperçu email</h3>
      <span style="font-size:.7rem;color:#aaa">Rendu réel dans la boîte mail</span>
    </div>
    <div class="preview-body">
      <div class="email-frame" id="email-preview">
        <?= buildEmailPreview($nl ? $nl['sujet'] : 'Sujet...', $nl ? $nl['contenu_html'] : '', $site_nom, $iban, $annee) ?>
      </div>
    </div>
  </div>

</div>

<?php
function buildEmailPreview($sujet, $contenu, $site_nom, $iban, $annee) {
    return '
    <!-- En-tête email -->
    <div style="background:#0e3d6b;padding:20px 28px;text-align:center">
      <div style="color:#fff;font-size:1.3rem;font-weight:800"><span style="color:#FF9900">ça suffit !</span> ASBL</div>
      <div style="color:rgba(255,255,255,.55);font-size:.72rem;margin-top:3px">Piste 01 · UBCNA · Union citoyenne</div>
    </div>
    <!-- Sujet mis en valeur -->
    <div style="background:#FF9900;padding:12px 28px">
      <div id="prev-sujet" style="color:#fff;font-weight:700;font-size:.95rem">' . htmlspecialchars($sujet ?: 'Sujet de la newsletter...') . '</div>
    </div>
    <!-- Corps -->
    <div style="padding:24px 28px;font-family:Helvetica Neue,Arial,sans-serif;font-size:.88rem;line-height:1.7;color:#333" id="prev-corps">
      ' . ($contenu ?: '<p style="color:#aaa;text-align:center;padding:20px">Commencez à rédiger votre newsletter...</p>') . '
    </div>
    <!-- Pied de page -->
    <div style="background:#f5f8fc;padding:16px 28px;border-top:2px solid #e0e8f0;font-size:.72rem;color:#888;text-align:center">
      <p style="margin-bottom:4px"><strong style="color:#0e3d6b">ça suffit ! ASBL</strong> · ' . htmlspecialchars($iban) . '</p>
      <p>Pour vous désabonner : <a href="#" style="color:#1673B2">cliquez ici</a></p>
      <p style="margin-top:4px;color:#bbb">© ' . $annee . ' ' . htmlspecialchars($site_nom) . '</p>
    </div>';
}
?>

<?php
// Préparer les données pour les widgets email
$montant_initial = floatval(cfg('montant_initial', 0));
$dons_confirmes_email = 0;
try { $dons_confirmes_email = floatval($db->query("SELECT COALESCE(SUM(montant),0) FROM member_dons WHERE statut='confirme'")->fetchColumn()); } catch(Exception $e) {}
$recolte_email   = $montant_initial + $dons_confirmes_email;
$objectif_email  = floatval(cfg('montant_objectif', 15000));
$pct_email       = $objectif_email > 0 ? min(100, round($recolte_email / $objectif_email * 100)) : 0;
$don_texte_email = cfg('don_texte', 'Action en cours');
$iban_email      = cfg('iban', 'BE41 0689 0149 6910');
$bic_email       = cfg('bic', 'GKCCBEBB');
$news_email_list = array();
try {
    $rows_ne = $db->query("SELECT titre, accroche, date_creation FROM news WHERE statut='publie' ORDER BY epingle DESC, date_creation DESC LIMIT 3")->fetchAll();
    foreach ($rows_ne as $ne) {
        $news_email_list[] = array(
            'titre'   => $ne['titre'],
            'accroche'=> $ne['accroche'],
            'date'    => date('d/m/Y', strtotime($ne['date_creation']))
        );
    }
} catch(Exception $e) {}
?>
<script>
var T = {
    titre:  '<h2 style="color:#FF9900;font-size:1.1rem;font-weight:400;margin:20px 0 8px;padding-bottom:6px;border-bottom:1px solid #e0e8f0">\n  Titre de section\n</h2>\n',
    texte:  '<p style="margin-bottom:12px;color:#444;line-height:1.7">\n  Votre texte ici...\n</p>\n',
    orange: '<div style="padding:14px 18px;background:#FF9900;color:#fff;margin:14px 0;font-weight:200">\n  <strong>Message important</strong><br>\n  Contenu ici...\n</div>\n',
    bleu:   '<div style="padding:14px 18px;background:#e6f1fb;border-left:4px solid #1673B2;color:#1673B2;margin:14px 0">\n  Information importante.\n</div>\n',
    alerte: '<div style="padding:14px 18px;border:2px solid #FF9900;border-left:5px solid #FF9900;background:#fff8ee;margin:14px 0">\n  <strong style="color:#a05000;font-size:.8rem;text-transform:uppercase">⚠ Attention</strong><br>\n  <span style="color:#7a4500">Message d\'alerte ici.</span>\n</div>\n',
    bouton: '<div style="text-align:center;margin:20px 0">\n  <a href="<?= SITE_URL ?>" style="display:inline-block;padding:12px 28px;background:#1673B2;color:#fff;text-decoration:none;font-weight:700;border-radius:6px">Voir le site →</a>\n</div>\n',
    sep:    '<hr style="border:none;border-top:2px solid #e0e8f0;margin:20px 0">\n',
    sign:   '<div style="margin-top:24px;padding-top:16px;border-top:1px solid #e0e8f0;font-size:.82rem;color:#555">\n  <strong style="color:#0e3d6b">L\'équipe ça suffit ! ASBL</strong><br>\n  Piste 01 · UBCNA · Union citoyenne<br>\n  <a href="<?= SITE_URL ?>" style="color:#1673B2"><?= str_replace("https://","",SITE_URL) ?></a>\n</div>\n',
};

function ins(k) {
    var ta = document.getElementById('f-contenu');
    var s = ta.selectionStart, e = ta.selectionEnd;
    ta.value = ta.value.substring(0,s) + T[k] + ta.value.substring(e);
    ta.selectionStart = ta.selectionEnd = s + T[k].length;
    ta.focus(); majApercu();
}

function majApercu() {
    var sujet  = document.getElementById('f-sujet').value || 'Sujet...';
    var corps  = document.getElementById('f-contenu').value;
    var ps = document.getElementById('prev-sujet');
    var pc = document.getElementById('prev-corps');
    if (ps) ps.textContent = sujet;
    if (pc) pc.innerHTML = corps || '<p style="color:#aaa;text-align:center;padding:20px">Commencez à rédiger...</p>';
}

majApercu();

// Données pour les widgets (préparées en PHP)
var WD = {
    objectif : <?= $objectif_email ?>,
    recolte  : <?= $recolte_email ?>,
    pct      : <?= $pct_email ?>,
    don_texte: <?= json_encode($don_texte_email) ?>,
    iban     : <?= json_encode($iban_email) ?>,
    bic      : <?= json_encode($bic_email) ?>,
    news     : <?= json_encode($news_email_list) ?>,
    site_url : '<?= SITE_URL ?>'
};

// Templates email des widgets
function insWidget(slug) {
    var html = '';
    switch(slug) {
        case 'progression':   html = getWidgetProgression(); break;
        case 'donation_card': html = getWidgetDonation(); break;
        case 'news':          html = getWidgetNews(); break;
        case 'newsletter':    html = getWidgetNewsletter(); break;
        default: html = '<!-- Widget ' + slug + ' -->';
    }
    var ta = document.getElementById('f-contenu');
    var s = ta.selectionStart;
    ta.value = ta.value.substring(0,s) + html + ta.value.substring(s);
    ta.selectionStart = ta.selectionEnd = s + html.length;
    ta.focus(); majApercu();
}

function getWidgetProgression() {
    return '<div style="background:#f0f7ff;border-radius:8px;padding:18px;margin:16px 0">\n'
         + '  <div style="font-size:.8rem;color:#1673B2;font-weight:700;margin-bottom:8px">🎯 ' + WD.don_texte + '</div>\n'
         + '  <div style="background:#e0e8f0;border-radius:4px;height:12px;overflow:hidden;margin-bottom:10px">\n'
         + '    <div style="background:#FF9900;height:100%;width:' + WD.pct + '%;border-radius:4px"></div>\n'
         + '  </div>\n'
         + '  <div style="display:flex;justify-content:space-between;font-size:.78rem;color:#555">\n'
         + '    <span><strong style="color:#1673B2">' + WD.recolte + ' €</strong> récoltés</span>\n'
         + '    <span>' + WD.pct + '% atteint</span>\n'
         + '    <span>Objectif : <strong>' + WD.objectif + ' €</strong></span>\n'
         + '  </div>\n'
         + '</div>\n';
}

function getWidgetDonation() {
    return '<div style="background:#fff8ee;border:2px solid #FF9900;border-radius:8px;padding:18px;margin:16px 0;text-align:center">\n'
         + '  <div style="font-size:1rem;font-weight:700;color:#FF9900;margin-bottom:8px">💶 Soutenez notre action</div>\n'
         + '  <div style="font-size:.82rem;color:#555;margin-bottom:12px">Effectuez votre virement bancaire :</div>\n'
         + '  <div style="background:#fff;border-radius:6px;padding:12px;font-family:monospace;font-size:.9rem;color:#0e3d6b;font-weight:700">' + WD.iban + '</div>\n'
         + '  <div style="font-size:.72rem;color:#888;margin-top:6px">BIC : ' + WD.bic + '</div>\n'
         + '  <div style="margin-top:14px"><a href="' + WD.site_url + '/#don" style="display:inline-block;padding:10px 24px;background:#FF9900;color:#fff;text-decoration:none;font-weight:700;border-radius:6px">Faire un don →</a></div>\n'
         + '</div>\n';
}

function getWidgetNews() {
    if (WD.news.length === 0) return '<p style="color:#aaa">Aucune actualité publiée.</p>\n';
    var html = '<div style="margin:16px 0">\n'
             + '  <h2 style="color:#FF9900;font-size:1rem;font-weight:400;margin-bottom:12px;padding-bottom:6px;border-bottom:1px solid #e0e8f0">📰 Actualités</h2>\n';
    WD.news.forEach(function(n) {
        html += '  <div style="margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid #f0f4f8">\n'
              + '    <div style="font-weight:700;color:#0e3d6b;font-size:.88rem">' + n.titre + '</div>\n'
              + '    <div style="font-size:.72rem;color:#aaa;margin:2px 0">' + n.date + '</div>\n'
              + (n.accroche ? '    <div style="font-size:.82rem;color:#555">' + n.accroche + '</div>\n' : '')
              + '  </div>\n';
    });
    html += '  <div style="text-align:center;margin-top:10px"><a href="' + WD.site_url + '" style="color:#1673B2;font-size:.78rem">Voir toutes les actualités →</a></div>\n</div>\n';
    return html;
}

function getWidgetNewsletter() {
    return '<div style="background:#e6f1fb;border-radius:8px;padding:18px;margin:16px 0;text-align:center">\n'
         + '  <div style="font-size:.95rem;font-weight:700;color:#0e3d6b;margin-bottom:6px">✉ Restez informé(e)</div>\n'
         + '  <div style="font-size:.8rem;color:#555;margin-bottom:12px">Inscrivez vos proches à notre newsletter</div>\n'
         + '  <a href="' + WD.site_url + '/#newsletter" style="display:inline-block;padding:10px 24px;background:#1673B2;color:#fff;text-decoration:none;font-weight:700;border-radius:6px">S\'inscrire →</a>\n'
         + '</div>\n';
}
</script>
</body>
</html>
