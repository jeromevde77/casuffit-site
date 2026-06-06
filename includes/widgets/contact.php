<?php // Widget contact — FR ?>
<div class="wct-wrap">
<style>
.wct-wrap{max-width:560px}
.wct-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
@media(max-width:480px){.wct-row{grid-template-columns:1fr}}
.wct-group{margin-bottom:12px}
.wct-group label{display:block;font-size:.8rem;font-weight:700;color:#0e3d6b;margin-bottom:3px}
.wct-group input,.wct-group select,.wct-group textarea{width:100%;padding:9px 12px;border:1.5px solid #cdd8e5;border-radius:8px;font-size:.88rem;font-family:inherit;box-sizing:border-box;transition:border-color .2s}
.wct-group input:focus,.wct-group textarea:focus{outline:none;border-color:#1673B2}
.wct-group textarea{min-height:110px;resize:vertical}
.wct-btn{background:#FF9900;color:#fff;border:none;padding:12px 28px;border-radius:8px;font-weight:700;font-size:.95rem;cursor:pointer;transition:background .2s}
.wct-btn:hover{background:#e08800}.wct-btn:disabled{opacity:.6;cursor:default}
.wct-alert{padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:.88rem}
.wct-ok{background:#e8f8f0;border:1px solid #27ae60;color:#1a6e3c}
.wct-err{background:#fdf0ed;border:1px solid #e74c3c;color:#922b21}
.wct-links{display:flex;gap:10px;margin-top:18px;flex-wrap:wrap}
.wct-link{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:7px;text-decoration:none;font-size:.8rem;font-weight:600;border:1.5px solid;transition:opacity .18s}
.wct-link:hover{opacity:.7}
.wct-link.wct-email{color:#0e3d6b;border-color:#b0c4d8;background:#f0f4f8}
.wct-link.wct-fb{color:#1877f2;border-color:#b3ccf5;background:#f0f5ff}
</style>

<div class="wct-form-title" style="font-size:1rem;font-weight:700;color:#0e3d6b;margin-bottom:14px">📝 Envoyer un message</div>
<div id="wct-alert" style="display:none"></div>
<form id="wct-form" onsubmit="wctSubmit(event)">
  <div class="wct-row">
    <div class="wct-group"><label>Nom *</label><input type="text" name="nom" required placeholder="Votre nom"></div>
    <div class="wct-group"><label>Email *</label><input type="email" name="email" required placeholder="votre@email.be"></div>
  </div>
  <div class="wct-group"><label>Sujet</label>
    <select name="sujet">
      <option value="">— Choisir —</option>
      <option>Devenir membre</option>
      <option>Signalement de nuisance</option>
      <option>Question juridique</option>
      <option>Presse / média</option>
      <option>Autre</option>
    </select>
  </div>
  <div class="wct-group"><label>Message *</label><textarea name="message" required placeholder="Votre message..."></textarea></div>
  <button type="submit" class="wct-btn" id="wct-btn">📨 Envoyer</button>
</form>

<div class="wct-links">
  <a class="wct-link wct-email" href="mailto:info@casuffit.be">✉ info@casuffit.be</a>
  <a class="wct-link wct-fb" href="http://www.facebook.com/piste01casuffit" target="_blank" rel="noopener">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="#1877f2"><path d="M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.69 4.53-4.69 1.31 0 2.68.24 2.68.24v2.97h-1.51c-1.49 0-1.96.93-1.96 1.89v2.25h3.33l-.53 3.49h-2.8V24C19.61 23.1 24 18.1 24 12.07z"/></svg>
    piste01casuffit
  </a>
</div>

<script>
function wctSubmit(e){
  e.preventDefault();
  var btn=document.getElementById('wct-btn'),alrt=document.getElementById('wct-alert');
  btn.disabled=true;btn.textContent='Envoi...';
  var data=new FormData(document.getElementById('wct-form'));
  data.append('lang','fr');
  fetch('/api/contact_submit.php',{method:'POST',body:data})
    .then(function(r){return r.json();})
    .then(function(d){
      alrt.className='wct-alert '+(d.ok?'wct-ok':'wct-err');
      alrt.textContent=d.message;alrt.style.display='block';
      if(d.ok){document.getElementById('wct-form').reset();btn.textContent='✅ Envoyé';}
      else{btn.disabled=false;btn.textContent='📨 Envoyer';}
    })
    .catch(function(){alrt.className='wct-alert wct-err';alrt.textContent='Erreur réseau. Réessayez.';alrt.style.display='block';btn.disabled=false;btn.textContent='📨 Envoyer';});
}
</script>
</div>
