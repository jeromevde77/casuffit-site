<?php // Widget : Newsletter ?>
<div class="newsletter-widget">
  <div style="max-width:680px; margin:0 auto">

    <div class="orange section-title" style="font-size:1.2rem;margin-bottom:16px">✉ S'inscrire à la newsletter</div>

    <div class="cadre-bleu" style="margin-bottom:20px">
      Restez informé(e) de toutes nos actions judiciaires et mobilisations. Inscription gratuite, désinscription en un clic.
    </div>

    <form class="newsletter-form" id="newsletter-form-widget" novalidate>
      <!-- Honeypot anti-bot -->
      <div style="display:none" aria-hidden="true">
        <input type="text" name="website" tabindex="-1" autocomplete="off">
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
        <div>
          <label style="display:block;font-size:.78rem;font-weight:700;color:#0e3d6b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px">Prénom</label>
          <input type="text" name="prenom" placeholder="Jean" style="width:100%;padding:9px 12px;border:1.5px solid #c8dff0;border-radius:6px;font-size:.88rem;box-sizing:border-box">
        </div>
        <div>
          <label style="display:block;font-size:.78rem;font-weight:700;color:#0e3d6b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px">Nom</label>
          <input type="text" name="nom" placeholder="Dupont" style="width:100%;padding:9px 12px;border:1.5px solid #c8dff0;border-radius:6px;font-size:.88rem;box-sizing:border-box">
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
        <div>
          <label style="display:block;font-size:.78rem;font-weight:700;color:#0e3d6b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px">Email *</label>
          <input type="email" name="email" placeholder="jean@exemple.be" required id="nl-email-w" style="width:100%;padding:9px 12px;border:1.5px solid #c8dff0;border-radius:6px;font-size:.88rem;box-sizing:border-box">
        </div>
        <div>
          <label style="display:block;font-size:.78rem;font-weight:700;color:#0e3d6b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px">Adresse</label>
          <input type="text" name="adresse" placeholder="Rue des Lilas 42" style="width:100%;padding:9px 12px;border:1.5px solid #c8dff0;border-radius:6px;font-size:.88rem;box-sizing:border-box">
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
        <div>
          <label style="display:block;font-size:.78rem;font-weight:700;color:#0e3d6b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px">Commune</label>
          <input type="text" name="commune" placeholder="Waterloo" style="width:100%;padding:9px 12px;border:1.5px solid #c8dff0;border-radius:6px;font-size:.88rem;box-sizing:border-box">
        </div>
        <div>
          <label style="display:block;font-size:.78rem;font-weight:700;color:#0e3d6b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px">Téléphone</label>
          <input type="tel" name="telephone" placeholder="0470 12 34 56" style="width:100%;padding:9px 12px;border:1.5px solid #c8dff0;border-radius:6px;font-size:.88rem;box-sizing:border-box">
        </div>
      </div>

      <label style="display:flex;align-items:flex-start;gap:10px;font-size:.82rem;color:#555;cursor:pointer;margin-bottom:16px">
        <input type="checkbox" id="nl-rgpd-w" required style="margin-top:3px;flex-shrink:0">
        J'accepte que mes données soient utilisées pour recevoir la newsletter de Ça suffit !
      </label>

      <div id="form-msg-w" class="form-msg"></div>

      <button type="submit" id="btn-subscribe-w" class="btn-subscribe" style="width:100%;padding:12px;font-size:.95rem;font-weight:700;background:var(--bleu-hex);color:#fff;border:none;border-radius:8px;cursor:pointer">
        ✉ S'abonner à la newsletter
      </button>

    </form>
  </div>

  <script>
  (function(){
    var form = document.getElementById('newsletter-form-widget');
    if (!form) return;
    form.addEventListener('submit', async function(e) {
      e.preventDefault();
      var email = document.getElementById('nl-email-w').value.trim();
      var rgpd  = document.getElementById('nl-rgpd-w').checked;
      var btn   = document.getElementById('btn-subscribe-w');
      var msg   = document.getElementById('form-msg-w');
      if (!email || !rgpd) { msg.textContent = 'Email et consentement requis.'; msg.className='form-msg error'; return; }
      btn.disabled = true; btn.textContent = 'Envoi...';
      var data = new FormData(form);
      data.append('lang', 'fr');
      try {
        var resp = await fetch('newsletter/subscribe.php', {method:'POST', body:data});
        var json = await resp.json();
        if (json.success) { msg.textContent = json.message; msg.className='form-msg success'; form.reset(); }
        else { msg.textContent = json.message; msg.className='form-msg error'; btn.disabled=false; btn.textContent='✉ S\'abonner'; }
      } catch(err) { msg.textContent='Erreur réseau.'; msg.className='form-msg error'; btn.disabled=false; btn.textContent='✉ S\'abonner'; }
    });
  })();
  </script>
</div>
