<?php // includes/footer.php ?>
<footer style="background:#0e3d6b;color:#fff;padding:32px 20px;margin-top:40px">
  <div style="max-width:1000px;margin:0 auto;display:grid;grid-template-columns:1fr 1fr 1fr;gap:24px;flex-wrap:wrap">
    <div>
      <div style="font-size:1rem;font-weight:800;margin-bottom:8px">Ça suffit !</div>
      <div style="font-size:0.75rem;color:rgba(255,255,255,0.6);line-height:1.8">
        Piste 01 Ça suffit ! · UBCNA<br>
        <a href="mailto:<?= htmlspecialchars(cfg('site_email','info@casuffit.be')) ?>" style="color:rgba(255,255,255,0.7)"><?= htmlspecialchars(cfg('site_email','info@casuffit.be')) ?></a>
      </div>
      <div style="margin-top:12px">
        <a href="/?page=contact" style="display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,0.12);color:#fff;text-decoration:none;padding:7px 14px;border-radius:8px;font-size:0.78rem;font-weight:600;border:1px solid rgba(255,255,255,0.2);transition:background .18s" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.12)'">
          📬 Nous contacter
        </a>
      </div>
    </div>
    <div>
      <div style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.08em;color:rgba(255,255,255,0.4);margin-bottom:8px">Don par virement</div>
      <div style="font-size:0.78rem;color:rgba(255,255,255,0.75);line-height:1.8;font-family:monospace">
        <?= htmlspecialchars(cfg('iban', 'BE41 0689 0149 6910')) ?><br>
        BIC : <?= htmlspecialchars(cfg('bic', 'GKCCBEBB')) ?><br>
        <?= htmlspecialchars(cfg('beneficiaire', 'Ça suffit !')) ?>
      </div>
    </div>
    <div>
      <div style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.08em;color:rgba(255,255,255,0.4);margin-bottom:8px">Liens</div>
      <div style="display:flex;flex-direction:column;gap:4px">
        <?php if (cfg('site_facebook')): ?>
        <a href="<?= htmlspecialchars(cfg('site_facebook')) ?>" target="_blank" style="color:rgba(255,255,255,0.7);font-size:0.78rem">Facebook</a>
        <?php endif; ?>
        <a href="/membre/inscription.php" style="color:rgba(255,255,255,0.7);font-size:0.78rem">Devenir membre</a>
        <a href="/?page=soutenir#newsletter" style="color:rgba(255,255,255,0.7);font-size:0.78rem">Newsletter</a>
      </div>
    </div>
  </div>
  <div style="max-width:1000px;margin:20px auto 0;padding-top:16px;border-top:1px solid rgba(255,255,255,0.1);font-size:0.7rem;color:rgba(255,255,255,0.3);text-align:center">
    © <?= date('Y') ?> <?= htmlspecialchars(cfg('site_nom', 'Ça suffit !')) ?> · Tous droits réservés
  </div>
</footer>
<script>
// Smooth scroll + mobile menu burger si nécessaire
document.querySelectorAll('a[href^="#"]').forEach(function(a) {
  a.addEventListener('click', function(e) {
    var target = document.getElementById(this.getAttribute('href').slice(1));
    if (target) { e.preventDefault(); target.scrollIntoView({behavior:'smooth'}); }
  });
});
</script>
</body>
</html>
