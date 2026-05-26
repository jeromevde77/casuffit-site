<?php // Widget : Page Facebook
$fb_url  = cfg('facebook_url', 'https://www.facebook.com/piste01casuffit');
?>
<div class="facebook-widget" style="width:100%">
  <div class="orange section-title" style="margin-bottom:16px">📘 Suivez-nous sur Facebook</div>

  <div id="fb-iframe-wrap" style="width:100%;border-radius:8px;overflow:hidden;border:1px solid #e0e8f0;background:#f0f4f8;min-height:120px;display:flex;align-items:center;justify-content:center">
    <div id="fb-blocked-msg" style="text-align:center;padding:24px;color:#888">
      <div style="font-size:1.5rem;margin-bottom:8px">📘</div>
      <div style="font-size:.85rem;margin-bottom:12px">Le widget Facebook nécessite votre consentement.</div>
      <button onclick="rgpdAcceptSocial()" style="padding:8px 16px;background:#1877F2;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:.82rem;font-weight:700">Accepter les cookies réseaux sociaux</button>
    </div>
  </div>

  <div style="text-align:center;margin-top:16px">
    <a href="<?= htmlspecialchars($fb_url) ?>" target="_blank" rel="noopener"
       style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:#1877F2;color:#fff;border-radius:6px;font-weight:700;font-size:.88rem;text-decoration:none">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="white"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
      Suivre notre page Facebook
    </a>
  </div>
</div>

<script>
(function() {
  // Accepter les réseaux sociaux depuis le widget
  window.rgpdAcceptSocial = function() {
    try {
      var c = JSON.parse(localStorage.getItem('rgpd_consent') || '{}');
      c.social = true; c.date = new Date().toISOString();
      localStorage.setItem('rgpd_consent', JSON.stringify(c));
    } catch(e) {}
    window.rgpdSocialAccepted = true;
    loadFbIframe();
  };

  function loadFbIframe() {
    var wrap = document.getElementById('fb-iframe-wrap');
    if (!wrap) return;
    var w = Math.max(180, Math.min(wrap.offsetWidth || 500, 500));
    var fbUrl = '<?= addslashes($fb_url) ?>';
    var src = 'https://www.facebook.com/plugins/page.php'
      + '?href=' + encodeURIComponent(fbUrl)
      + '&tabs=timeline'
      + '&width=' + w
      + '&height=700'
      + '&small_header=false'
      + '&adapt_container_width=false'
      + '&hide_cover=false'
      + '&show_facepile=true'
      + '&locale=fr_FR';
    wrap.innerHTML = '<iframe src="' + src + '" '
      + 'style="width:' + w + 'px;height:700px;border:none;display:block;margin:0 auto" '
      + 'scrolling="yes" frameborder="0" '
      + 'allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share">'
      + '</iframe>';
  }
  // Charger seulement si consentement social donné
  function tryLoad() {
    if (window.rgpdSocialAccepted) {
      if (document.readyState === 'complete') {
        setTimeout(loadFbIframe, 100);
      } else {
        window.addEventListener('load', function() { setTimeout(loadFbIframe, 100); });
      }
    } else {
      document.addEventListener('rgpd-social-accepted', function() {
        setTimeout(loadFbIframe, 100);
      });
    }
  }
  tryLoad();
})();
</script>
