<?php
// outils-newsletter-cessation.php — Crée le BROUILLON de newsletter « Appel aux dons — cessation environnementale »
// Outil ponctuel (hors scope /admin/ du Service Worker). À SUPPRIMER après usage.
require_once __DIR__ . '/config.php';
session_start();
requireAdmin();
$db = getDB();

$sujet = "Appel aux dons — préparons l'action en cessation environnementale";

$contenu_html = <<<'HTML'
<div class="lettre-intro"><p>Chères amies, chers amis de la mobilisation,</p></div>

<p style="margin-bottom:12px;color:#444;line-height:1.7">Nous lançons un nouvel appel à votre générosité. Cette fois, c'est pour préparer une étape décisive : notre <strong>intervention dans l'action en cessation environnementale</strong> que la Région bruxelloise s'apprête à introduire.</p>

<p style="margin-bottom:12px;color:#444;line-height:1.7">Pour y participer et porter la voix des riverains de la piste 01, nous devons être <strong>prêts dès le départ</strong> — un dossier solide se construit en amont, jamais dans l'urgence.</p>

<div style="padding:14px 18px;border:2px solid #FF9900;border-left:5px solid #FF9900;background:#fff8ee;margin:16px 0">
  <strong style="color:#a05000;font-size:.85rem;text-transform:uppercase">Objectif : 35 000 €</strong><br>
  <span style="color:#7a4500">L'estimation du coût d'une telle action s'élève à 35 000 €. Nous le savons : c'est beaucoup. Mais la justice est ainsi faite — sans votre aide, nous n'y arriverons pas.</span>
</div>

<p style="margin-bottom:12px;color:#444;line-height:1.7">Bonne nouvelle : nous venons de <strong>clôturer les frais de notre action en référé</strong>, qui a obtenu des résultats. Ces acquis, nous les <strong>réutiliserons</strong> pour cette nouvelle bataille.</p>

<div style="background:#fff8ee;border:2px solid #FF9900;border-radius:8px;padding:18px;margin:18px 0;text-align:center">
  <div style="font-size:1rem;font-weight:700;color:#FF9900;margin-bottom:8px">💶 Soutenez notre action</div>
  <div style="font-size:.82rem;color:#555;margin-bottom:12px">Par virement bancaire :</div>
  <div style="background:#fff;border-radius:6px;padding:12px;font-family:monospace;font-size:.95rem;color:#0e3d6b;font-weight:700">BE41 0689 0149 6910</div>
  <div style="font-size:.72rem;color:#888;margin-top:6px">BIC : GKCCBEBB</div>
  <div style="margin-top:14px"><a href="https://www.casuffit.be/don" style="display:inline-block;padding:11px 26px;background:#FF9900;color:#fff;text-decoration:none;font-weight:700;border-radius:6px">Faire un don &rarr;</a></div>
</div>

<p style="margin-bottom:12px;color:#444;line-height:1.7">Merci pour votre soutien et votre fidélité.</p>

<div style="margin-top:20px;padding-top:16px;border-top:1px solid #e0e8f0;font-size:.85rem;color:#555">
  <strong style="color:#0e3d6b">L'équipe Ça suffit !</strong><br>
  Piste 01 &middot; UBCNA &middot; Union citoyenne<br>
  <a href="https://www.casuffit.be" style="color:#1673B2">casuffit.be</a>
</div>
HTML;

// Idempotent : ne pas dupliquer si un brouillon avec ce sujet existe déjà
$st = $db->prepare("SELECT id, statut FROM newsletters WHERE sujet=? ORDER BY id DESC LIMIT 1");
$st->execute([$sujet]);
$row = $st->fetch();

if ($row) {
    $id = (int)$row['id'];
    $info = "Un brouillon avec ce sujet existe déjà (#{$id}, statut : {$row['statut']}). Aucun doublon créé.";
} else {
    $db->prepare("INSERT INTO newsletters (sujet, contenu_html, statut) VALUES (?,?,'brouillon')")
       ->execute([$sujet, $contenu_html]);
    $id = (int)$db->lastInsertId();
    $info = "Brouillon créé (#{$id}).";
}

// ── Objectif de dons → 35 000 € ───────────────────────────────────────────
$objectif_cible = '35000';
$db->prepare("INSERT INTO site_config (cle,valeur) VALUES ('montant_objectif',?) ON DUPLICATE KEY UPDATE valeur=?")
   ->execute([$objectif_cible, $objectif_cible]);
$info_obj = "Objectif de dons mis à jour : 35 000 €.";
?>
<!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Outil — Newsletter cessation</title>
<style>
  body{font-family:"Helvetica Neue",Arial,sans-serif;background:#f0f4f8;color:#333;padding:40px 16px}
  .box{background:#fff;max-width:640px;margin:0 auto;padding:28px;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,.08)}
  h1{color:#0e3d6b;font-size:1.1rem;margin-bottom:14px}
  a.btn{display:inline-block;margin:6px 8px 6px 0;padding:10px 18px;background:#1673B2;color:#fff;text-decoration:none;border-radius:7px;font-weight:700;font-size:.85rem}
  a.btn.orange{background:#FF9900}
  .warn{margin-top:22px;padding:12px 14px;background:#fff5f5;border-left:3px solid #e53e3e;border-radius:6px;color:#c0392b;font-size:.85rem}
</style></head><body>
<div class="box">
  <h1><?= htmlspecialchars($info) ?></h1>
  <p style="font-size:.92rem;color:#1a7a4a;font-weight:600">✓ <?= htmlspecialchars($info_obj) ?></p>
  <p style="font-size:.9rem;color:#555;line-height:1.6">Le brouillon est prêt. <strong>Relisez-le</strong>, puis envoyez-le aux abonnés depuis l'admin (l'envoi n'est <em>pas</em> automatique).</p>
  <p>
    <a class="btn orange" href="/admin/compose.php?id=<?= $id ?>">Relire / éditer le brouillon</a>
    <a class="btn" href="/admin/newsletters.php">Page Newsletters (envoyer)</a>
  </p>
  <div class="warn"><strong>Sécurité :</strong> supprimez ce fichier <code>outils-newsletter-cessation.php</code> après usage.</div>
</div>
</body></html>
