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
      <?php if (!empty($membres_effectifs)): ?>
      <div class="stat">
        <span class="stat-val"><?= number_format($membres_effectifs, 0, ',', ' ') ?></span>
        <span class="stat-lab">Effectieve leden</span>
      </div>
      <?php endif; ?>
      <?php if (!empty($fb_followers)): ?>
      <div class="stat">
        <span class="stat-val"><?= number_format($fb_followers, 0, ',', ' ') ?></span>
        <span class="stat-lab">Facebook-volgers</span>
      </div>
      <?php endif; ?>
    </div>
    <a href="/plainte.php?lang=nl" style="display:block;width:100%;margin-top:14px;padding:14px 16px;background:#FF9900;color:#fff;font-weight:700;font-size:.88rem;text-align:center;border-radius:10px;text-decoration:none;box-shadow:0 2px 8px rgba(255,153,0,.3);">
      ⚠ Klacht indienen — abnormaal gebruik
    </a>
    <p style="font-size:.72rem;color:#888;margin-top:6px;line-height:1.5;text-align:center">
      Voor een overlast <strong>op dit moment</strong>. Voor een overlast in het <strong>verleden</strong> → <a href="/wind.php#historique" style="color:#1673B2">Windgeschiedenis</a>
    </p>
    <a href="/membre/inscription.php?lang=nl" style="display:block;width:100%;margin-top:10px;padding:14px 16px;background:#1673B2;color:#fff;font-weight:700;font-size:.88rem;text-align:center;border-radius:10px;text-decoration:none;box-shadow:0 2px 8px rgba(22,115,178,.3);">
      ✊ Lid worden — sluit u aan bij de strijd
    </a>
  </div>
</section>
