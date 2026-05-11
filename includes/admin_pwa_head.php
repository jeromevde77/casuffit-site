<?php // includes/admin_pwa_head.php — Balises PWA pour l'admin ?>
<link rel="manifest" href="/admin/manifest.json">
<meta name="theme-color" content="#0e3d6b">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="CSA Admin">
<link rel="apple-touch-icon" href="/favicon-192.png">
<script>
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function() {
    navigator.serviceWorker.register('/admin/sw.js', {scope: '/admin/'})
      .catch(function(e) { console.warn('SW:', e); });
  });
}
</script>
