<?php
$widget_no_scale = true;
// Widget : Barre de progression — Version NL
// $recolte, $objectif, $pct, $don_texte sont définis dans index.php
?>
<section class="progress-section">
  <div class="progress-inner">
    <div class="prog-header">
      <span class="prog-label">🎯 Doelstelling — <?= htmlspecialchars($don_texte) ?></span>
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
        <span class="stat-lab">Totale doelstelling</span>
      </div>
      <div class="stat">
        <span class="stat-val"><?= $pct ?>%</span>
        <span class="stat-lab">Bereikt</span>
      </div>
      <div class="stat">
        <span class="stat-val">+20 jaar</span>
        <span class="stat-lab">Van strijd</span>
      </div>
    </div>
  </div>
</section>
