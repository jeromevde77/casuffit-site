<?php // Widget : Actualités ?>
<div class="news-section" style="margin-bottom:20px">
  <?php if (!empty($news_list)): ?>
    <?php foreach ($news_list as $ni => $n): ?>
    <?php $is_open = !empty($n['deploye_defaut']) || !empty($n['epingle']); ?>
    <div class="news-item <?= $n['epingle'] ? 'news-epingle' : '' ?>" id="news-item-<?= (int)$n['id'] ?>" data-news-id="<?= (int)$n['id'] ?>" style="border:1px solid var(--bleu-ciel);border-radius:8px;margin-bottom:10px;background:#fff;overflow:hidden">

      <!-- Résumé cliquable -->
      <div class="news-summary" onclick="toggleNews(<?= $ni ?>)" style="padding:12px;cursor:pointer">
        <?php if ($n['epingle']): ?>
          <span style="font-size:.7rem;color:var(--orange-hex);font-weight:700;display:block;margin-bottom:4px">📌 Épinglé</span>
        <?php endif; ?>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
          <div style="flex:1">
            <div style="font-weight:700;color:var(--bleu-hex);font-size:.88rem;margin-bottom:3px"><?= htmlspecialchars(tdb($n,'titre') ?? $n['titre']) ?></div>
            <div style="font-size:.7rem;color:#999"><?= date('d/m/Y', strtotime($n['date_creation'])) ?></div>
          </div>
          <?php if (!empty($n['image_url'])): ?>
            <img src="<?= htmlspecialchars($n['image_url']) ?>" alt="" style="width:64px;height:44px;object-fit:cover;border-radius:6px;flex-shrink:0">
          <?php endif; ?>
          <span class="news-chevron" id="chev-<?= $ni ?>" style="color:var(--bleu-hex);font-size:.8rem;flex-shrink:0;transition:transform .2s;transform:<?= $is_open ? 'rotate(180deg)' : 'none' ?>">▼</span>
        </div>
        <?php
        $accroche_aff = tdb($n,'accroche') ?? $n['accroche'] ?? '';
        $contenu_aff  = tdb($n,'contenu')  ?? $n['contenu']  ?? '';
        ?>
        <?php if (!empty($accroche_aff)): ?>
          <div style="font-size:.8rem;color:#555;line-height:1.5;margin-top:6px"><?= htmlspecialchars($accroche_aff) ?></div>
        <?php elseif (!empty($contenu_aff)): ?>
          <div style="font-size:.8rem;color:#555;line-height:1.5;margin-top:6px"><?= htmlspecialchars(mb_strimwidth(strip_tags($contenu_aff), 0, 120, '…')) ?></div>
        <?php endif; ?>
      </div>

      <!-- Contenu complet dépliable -->
      <div class="news-full" id="news-full-<?= $ni ?>" style="display:<?= $is_open ? 'block' : 'none' ?>;padding:0 12px 12px;border-top:1px solid var(--bleu-ciel)">
        <?php if (!empty($n['image_url'])): ?>
          <img src="<?= htmlspecialchars($n['image_url']) ?>" alt="<?= htmlspecialchars(tdb($n,'titre') ?? $n['titre']) ?>" style="width:100%;height:auto;display:block;margin-bottom:10px;border-radius:0 0 4px 4px">
        <?php endif; ?>
        <div class="apanel-inner" style="padding:12px 0;box-shadow:none">
          <?= tdb($n,'contenu') ?? $n['contenu'] ?>
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

// Ouverture directe d'un article via ?news=ID (partage Facebook/lien direct)
(function() {
  var params = new URLSearchParams(window.location.search);
  var newsId = params.get('news');
  if (!newsId) return;
  function openTarget() {
    var item = document.getElementById('news-item-' + newsId);
    if (!item) return;
    // Ouvrir l'onglet Actualités si fonction dispo
    if (typeof showTab === 'function') { try { showTab('actualites'); } catch(e){} }
    // Déplier l'article
    var full = item.querySelector('.news-full');
    var chev = item.querySelector('.news-chevron');
    if (full) full.style.display = 'block';
    if (chev) chev.style.transform = 'rotate(180deg)';
    // Mettre en évidence + scroller
    item.style.boxShadow = '0 0 0 3px var(--orange-hex)';
    setTimeout(function(){ item.scrollIntoView({behavior:'smooth', block:'center'}); }, 300);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function(){ setTimeout(openTarget, 400); });
  } else {
    setTimeout(openTarget, 400);
  }
})();
</script>
