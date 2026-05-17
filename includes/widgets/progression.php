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
  </div>
</section>
