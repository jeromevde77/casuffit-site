<?php
$widget_no_scale = true; // Ne pas agrandir la police sur mobile
 // Widget : Barre de progression
// $recolte, $objectif, $pct, $don_texte sont définis dans index.php
?>
<section class="progress-section">
  <div class="progress-inner">
    <div class="prog-header">
      <span class="prog-label">🎯 Objectif — <?= htmlspecialchars($don_texte) ?></span>
      <span class="prog-chiffres">
        <strong><?= number_format($recolte, 0, ',', ' ') ?> €</strong>
        / <?= number_format($objectif, 0, ',', ' ') ?> €
      </span>
    </div>
    <div class="bar-wrap">
      <div class="bar-fill" style="width:<?= $pct ?>%"></div>
    </div>
    <div class="prog-stats">
      <div class="stat">
        <span class="stat-val"><?= number_format($objectif, 0, ',', ' ') ?> €</span>
        <span class="stat-lab">Objectif total</span>
      </div>
      <div class="stat">
        <span class="stat-val"><?= $pct ?>%</span>
        <span class="stat-lab">Atteint</span>
      </div>
      <div class="stat">
        <span class="stat-val">+20 ans</span>
        <span class="stat-lab">De combat</span>
      </div>
    </div>
    <a href="/plainte.php" style="display:block;width:100%;margin-top:14px;padding:14px 16px;background:#FF9900;color:#fff;font-weight:700;font-size:.88rem;text-align:center;border-radius:10px;text-decoration:none;box-shadow:0 2px 8px rgba(255,153,0,.3);">
      Je constate un usage anormal des pistes, je désire porter plainte
    </a>
    <p style="font-size:.72rem;color:#888;margin-top:6px;line-height:1.5;text-align:center">
      Pour une nuisance <strong>en ce moment</strong>. Pour une nuisance <strong>passée</strong> → <a href="/wind.php#historique" style="color:#1673B2">Historique du vent</a>
    </p>
  </div>
</section>
