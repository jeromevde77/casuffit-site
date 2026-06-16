<?php
// admin/doublons.php — Détection des doublons de dons (même membre, même montant, dates proches)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../membre/functions.php';
session_start(); requireAdmin();
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/dons.php';
$db = getDB();
$anonId = getAnonymousMemberId($db); // exclu de la détection : les dons anonymes ne sont pas des doublons entre eux

if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_verify();

// Supprimer un don (résolution d'un doublon)
if (isset($_POST['supprimer_don'])) {
    $did = (int)($_POST['don_id'] ?? 0);
    if ($did > 0) $db->prepare("DELETE FROM member_dons WHERE id=?")->execute([$did]);
    header('Location: doublons.php?msg=supprime' . (isset($_GET['tous']) ? '&tous=1' : '')); exit;
}

$tous       = isset($_GET['tous']);          // afficher tous les groupes même montant
$fenetre    = 10;                            // jours pour considérer deux dons comme proches
$msg        = $_GET['msg'] ?? '';

// Groupes candidats : même membre + même montant, au moins 2 dons
$stCands = $db->prepare("SELECT d.member_id, d.montant, COUNT(*) c, m.prenom, m.nom, m.code_membre
                         FROM member_dons d JOIN members m ON m.id = d.member_id
                         WHERE d.member_id <> ?
                         GROUP BY d.member_id, d.montant
                         HAVING c > 1
                         ORDER BY m.nom, m.prenom");
$stCands->execute([$anonId]);
$cands = $stCands->fetchAll();

$groupes = [];
$normComm = function($d) {
    $c = trim((string)($d['ogm_don'] ?: ($d['communication'] ?? '')));
    return mb_strtolower($c);
};
$stDons = $db->prepare("SELECT id, date_don, communication, ogm_don, statut, ref_import, note
                        FROM member_dons WHERE member_id=? AND ABS(montant-?)<0.01 ORDER BY date_don");
foreach ($cands as $g) {
    $stDons->execute([$g['member_id'], $g['montant']]);
    $dons = $stDons->fetchAll();
    // On compare chaque paire : doublon probable si MÊME communication OU dates proches.
    // (Communications différentes À des dates différentes => PAS un doublon.)
    $suspect = false; $memeComm = false; $proche = false; $mixte = false;
    $n = count($dons);
    for ($i = 0; $i < $n; $i++) {
        for ($j = $i + 1; $j < $n; $j++) {
            $ci = $normComm($dons[$i]); $cj = $normComm($dons[$j]);
            $sameComm  = ($ci !== '' && $ci === $cj);
            $t0 = strtotime($dons[$i]['date_don']); $t1 = strtotime($dons[$j]['date_don']);
            $closeDate = ($t0 && $t1 && abs($t1 - $t0) <= $fenetre * 86400);
            if ($sameComm || $closeDate) {
                $suspect = true;
                if ($sameComm)  $memeComm = true;
                if ($closeDate) $proche = true;
                $ri = trim($dons[$i]['ref_import'] ?? '') === '';
                $rj = trim($dons[$j]['ref_import'] ?? '') === '';
                if ($ri !== $rj) $mixte = true; // un don importé + un don manuel dans la paire douteuse
            }
        }
    }
    if ($tous || $suspect) {
        $groupes[] = ['g' => $g, 'dons' => $dons, 'proche' => $proche, 'mixte' => $mixte, 'memeComm' => $memeComm];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<?php include __DIR__ . '/../includes/admin_pwa_head.php'; ?>
  <title>Doublons de dons — Admin Ça suffit !</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333}
    <?php include __DIR__ . '/../includes/admin_sidebar_css.php'; ?>
    .main{margin-left:240px;padding:28px;max-width:1100px}
    .page-title{font-size:1.2rem;font-weight:800;color:#0e3d6b;margin-bottom:8px}
    .sub{color:#888;font-size:.82rem;margin-bottom:18px}
    .card{background:#fff;border-radius:12px;padding:18px 20px;box-shadow:0 2px 10px rgba(0,0,0,.06);margin-bottom:16px}
    .grp-head{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:10px;flex-wrap:wrap}
    .grp-membre{font-weight:700;color:#0e3d6b}
    .grp-membre a{color:#1673B2;text-decoration:none}
    .grp-montant{font-weight:800;color:#FF9900}
    table{width:100%;border-collapse:collapse;font-size:.8rem}
    th{text-align:left;padding:7px 9px;color:#888;font-weight:600;font-size:.65rem;text-transform:uppercase;border-bottom:2px solid #eee}
    td{padding:7px 9px;border-bottom:1px solid #f5f5f5;vertical-align:middle}
    .badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:.64rem;font-weight:700}
    .b-ok{background:#e8f8f0;color:#27ae60}.b-wait{background:#fff3e0;color:#ba7517}.b-off{background:#fde8e8;color:#c53030}
    .b-imp{background:#e6f1fb;color:#1673B2}.b-man{background:#f0f0f0;color:#777}
    .tag{display:inline-block;padding:2px 9px;border-radius:10px;font-size:.66rem;font-weight:700;margin-left:6px}
    .t-mixte{background:#fde8e8;color:#c53030}.t-proche{background:#fff3e0;color:#ba7517}.t-comm{background:#e6f1fb;color:#1673B2}
    .ogm{font-family:monospace;font-size:.72rem;color:#1673B2}
    .btn{padding:6px 12px;border-radius:6px;font-size:.74rem;font-weight:700;cursor:pointer;border:1.5px solid transparent;font-family:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:5px;line-height:1}
    .btn-del{background:#fff5f5;color:#c53030;border-color:#fed7d7}.btn-del:hover{background:#fee2e2}
    .btn-g{background:#f0f4f8;color:#555;border-color:#dde4ed}.btn-g:hover{background:#e0e8f0}
    .flash-ok{background:#e8f8f0;color:#276749;padding:11px 14px;border-radius:8px;margin-bottom:14px;font-size:.82rem;border-left:3px solid #48bb78}
    .empty{text-align:center;padding:40px;color:#aaa}
    @media(max-width:768px){.main{margin-left:0!important;padding:14px!important;padding-top:68px!important}table{font-size:.72rem}}
  </style>
</head>
<body>
<?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>
<div class="main">
  <div class="page-title">🔁 Doublons de dons potentiels</div>
  <div class="sub">Doublon probable = même membre + même montant <em>et</em> (<strong>même communication</strong> ou <strong>dates proches</strong>, &lt; <?= $fenetre ?> j). Des communications <em>différentes</em> à des dates <em>différentes</em> ne sont pas signalées.</div>

  <?php if ($msg === 'supprime'): ?><div class="flash-ok">✅ Don supprimé.</div><?php endif; ?>

  <div style="margin-bottom:14px">
    <?php if ($tous): ?>
      <a href="doublons.php" class="btn btn-g">← N'afficher que les doublons probables</a>
    <?php else: ?>
      <a href="doublons.php?tous=1" class="btn btn-g">Afficher tous les groupes « même montant » (<?= count($cands) ?>)</a>
    <?php endif; ?>
  </div>

  <?php if (empty($groupes)): ?>
    <div class="card empty">
      <div style="font-size:2.2rem;margin-bottom:8px">✅</div>
      Aucun doublon probable détecté.
    </div>
  <?php else: ?>
    <div class="sub"><?= count($groupes) ?> groupe(s) à vérifier.</div>
    <?php foreach ($groupes as $G): $g = $G['g']; ?>
    <div class="card">
      <div class="grp-head">
        <div class="grp-membre">
          <a href="member_detail.php?id=<?= (int)$g['member_id'] ?>&back=<?= urlencode('doublons.php'.($tous?'?tous=1':'')) ?>">
            <?= htmlspecialchars(trim($g['prenom'].' '.$g['nom'])) ?>
          </a>
          <span style="font-family:monospace;font-size:.7rem;color:#999"><?= htmlspecialchars($g['code_membre']) ?></span>
          <?php if ($G['memeComm']): ?><span class="tag t-comm">🔁 Même communication</span><?php endif; ?>
          <?php if ($G['mixte']): ?><span class="tag t-mixte">⚠ Import + manuel</span><?php endif; ?>
          <?php if ($G['proche']): ?><span class="tag t-proche">⏱ Dates proches</span><?php endif; ?>
        </div>
        <div class="grp-montant"><?= number_format((float)$g['montant'], 2, ',', ' ') ?> € × <?= (int)$g['c'] ?></div>
      </div>
      <table>
        <tr><th>Date</th><th>Communication</th><th>Statut</th><th>Source</th><th>Note</th><th></th></tr>
        <?php foreach ($G['dons'] as $d): ?>
        <tr>
          <td><?= $d['date_don'] ? date('d/m/Y', strtotime($d['date_don'])) : '—' ?></td>
          <td><?php $o = $d['ogm_don'] ?: $d['communication']; ?>
              <?php if ($o): ?><span class="ogm"><?= htmlspecialchars($o) ?></span><?php else: ?><span style="color:#bbb">—</span><?php endif; ?></td>
          <td><?= $d['statut']==='confirme'?'<span class="badge b-ok">Confirmé</span>':($d['statut']==='annule'?'<span class="badge b-off">Annulé</span>':'<span class="badge b-wait">En attente</span>') ?></td>
          <td><?= trim($d['ref_import']??'')!=='' ? '<span class="badge b-imp">Import</span>' : '<span class="badge b-man">Manuel</span>' ?></td>
          <td style="font-size:.7rem;color:#999"><?= htmlspecialchars(mb_substr($d['note']??'',0,40)) ?></td>
          <td>
            <form method="POST" style="margin:0" onsubmit="return confirm('Supprimer définitivement ce don de <?= number_format((float)$g['montant'],2,',',' ') ?> € ?');">
              <?= csrf_field() ?>
              <input type="hidden" name="don_id" value="<?= (int)$d['id'] ?>">
              <button type="submit" name="supprimer_don" class="btn btn-del">🗑 Supprimer</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
</body>
</html>
