<?php // Widget : Actualités ?>
<div class="news-section" style="margin-bottom:20px">
  <?php if (!empty($news_list)): ?>
    <?php foreach ($news_list as $ni => $n): ?>
    <div class="news-item <?= $n['epingle'] ? 'news-epingle' : '' ?>" style="border:1px solid var(--bleu-ciel);border-radius:8px;margin-bottom:10px;background:#fff;overflow:hidden">

      <!-- Résumé cliquable -->
      <div class="news-summary" onclick="toggleNews(<?= $ni ?>)" style="padding:12px;cursor:pointer">
        <?php if ($n['epingle']): ?>
          <span style="font-size:.7rem;color:var(--orange-hex);font-weight:700;display:block;margin-bottom:4px">📌 Épinglé</span>
        <?php endif; ?>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
          <div>
            <div style="font-weight:700;color:var(--bleu-hex);font-size:.88rem;margin-bottom:3px"><?= htmlspecialchars($n['titre']) ?></div>
            <div style="font-size:.7rem;color:#999"><?= date('d/m/Y', strtotime($n['date_creation'])) ?></div>
          </div>
          <span class="news-chevron" id="chev-<?= $ni ?>" style="color:var(--bleu-hex);font-size:.8rem;flex-shrink:0;transition:transform .2s;transform:<?= $n['epingle'] ? 'rotate(180deg)' : 'none' ?>">▼</span>
        </div>
        <?php if (!empty($n['accroche'])): ?>
          <div style="font-size:.8rem;color:#555;line-height:1.5;margin-top:6px"><?= htmlspecialchars($n['accroche']) ?></div>
        <?php elseif (!empty($n['contenu'])): ?>
          <div style="font-size:.8rem;color:#555;line-height:1.5;margin-top:6px"><?= htmlspecialchars(mb_strimwidth(strip_tags($n['contenu']), 0, 120, '…')) ?></div>
        <?php endif; ?>
      </div>

      <!-- Contenu complet dépliable -->
      <div class="news-full" id="news-full-<?= $ni ?>" style="display:<?= $n['epingle'] ? 'block' : 'none' ?>;padding:0 12px 12px;border-top:1px solid var(--bleu-ciel)">
        <div class="apanel-inner" style="padding:12px 0;box-shadow:none">
          <?= $n['contenu'] ?>
        </div>
      </div>

    </div>
    <?php endforeach; ?>
  <?php else: ?>
    <p style="color:#aaa;font-size:.85rem;text-align:center;padding:20px">Aucune actualité publiée.</p>
  <?php endif; ?>
</div>

<script>
function toggleNews(i) {
  var full  = document.getElementById('news-full-' + i);
  var chev  = document.getElementById('chev-' + i);
  var open  = full.style.display !== 'none';
  full.style.display = open ? 'none' : 'block';
  if (chev) chev.style.transform = open ? '' : 'rotate(180deg)';
}
</script>
