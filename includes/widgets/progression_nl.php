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
    <a href="/plainte.php?lang=nl" style="display:block;width:100%;margin-top:14px;padding:14px 16px;background:#FF9900;color:#fff;font-weight:700;font-size:.88rem;text-align:center;border-radius:10px;text-decoration:none;box-shadow:0 2px 8px rgba(255,153,0,.3);">
      Ik stel een abnormaal gebruik van de startbaan vast en wil klacht indienen
    </a>
    <p style="font-size:.72rem;color:#888;margin-top:6px;line-height:1.5;text-align:center">
      Voor een overlast <strong>op dit moment</strong>. Voor een overlast in het <strong>verleden</strong> → <a href="/wind.php#historique" style="color:#1673B2">Windgeschiedenis</a>
    </p>
  </div>
</section>
