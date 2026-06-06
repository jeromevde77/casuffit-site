<?php
// Widget contact — formulaire + email + Facebook (sans lien plainte)
$w_lang = defined('LANG') ? LANG : 'fr';
$w_nl   = ($w_lang === 'nl');
function wct(bool $nl, string $fr, string $nls): string { return $nl ? $nls : $fr; }
?>
<div class="wct-wrap">
<style>
.wct-wrap{max-width:560px}
.wct-links{display:flex;gap:12px;margin-bottom:22px;flex-wrap:wrap}
.wct-link{display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:10px;text-decoration:none;color:#fff;font-weight:700;font-size:.88rem;flex:1;min-width:180px;transition:opacity .18s}
.wct-link:hover{opacity:.85}
.wct-link.wct-email{background:#0e3d6b}
.wct-link.wct-fb{background:#1877f2}
.wct-link .wct-sub{font-size:.72rem;font-weight:400;opacity:.85;margin-top:1px}
.wct-form-title{font-size:1rem;font-weight:700;color:#0e3d6b;margin-bottom:14px}
.wct-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
@media(max-width:480px){.wct-row{grid-template-columns:1fr}}
.wct-group{margin-bottom:12px}
.wct-group label{display:block;font-size:.8rem;font-weight:700;color:#0e3d6b;margin-bottom:3px}
.wct-group input,.wct-group select,.wct-group textarea{
  width:100%;padding:9px 12px;border:1.5px solid #cdd8e5;border-radius:8px;
  font-size:.88rem;font-family:inherit;box-sizing:border-box;transition:border-color .2s}
.wct-group input:focus,.wct-group textarea:focus{outline:none;border-color:#1673B2}
.wct-group textarea{min-height:110px;resize:vertical}
.wct-btn{background:#FF9900;color:#fff;border:none;padding:12px 28px;border-radius:8px;
  font-weight:700;font-size:.95rem;cursor:pointer;transition:background .2s}
.wct-btn:hover{background:#e08800}
.wct-btn:disabled{opacity:.6;cursor:default}
.wct-alert{padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:.88rem}
.wct-ok{background:#e8f8f0;border:1px solid #27ae60;color:#1a6e3c}
.wct-err{background:#fdf0ed;border:1px solid #e74c3c;color:#922b21}
</style>

<!-- Liens rapides -->
<div class="wct-links">
  <a class="wct-link wct-email" href="mailto:info@casuffit.be">
    <span>✉</span>
    <div><div>info@casuffit.be</div><div class="wct-sub"><?= wct($w_nl,'Per e-mail','Par email') ?></div></div>
  </a>
  <a class="wct-link wct-fb" href="http://www.facebook.com/piste01casuffit" target="_blank" rel="noopener">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="white"><path d="M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.69 4.53-4.69 1.31 0 2.68.24 2.68.24v2.97h-1.51c-1.49 0-1.96.93-1.96 1.89v2.25h3.33l-.53 3.49h-2.8V24C19.61 23.1 24 18.1 24 12.07z"/></svg>
    <div><div>piste01casuffit</div><div class="wct-sub">Facebook</div></div>
  </a>
</div>

<!-- Formulaire -->
<div class="wct-form-title">📝 <?= wct($w_nl,'Stuur een bericht','Envoyer un message') ?></div>
<div id="wct-alert" style="display:none"></div>
<form id="wct-form" onsubmit="wctSubmit(event)">
  <div class="wct-row">
    <div class="wct-group">
      <label><?= wct($w_nl,'Naam *','Nom *') ?></label>
      <input type="text" name="nom" required placeholder="<?= wct($w_nl,'Uw naam','Votre nom') ?>">
    </div>
    <div class="wct-group">
      <label><?= wct($w_nl,'E-mail *','Email *') ?></label>
      <input type="email" name="email" required placeholder="votre@email.be">
    </div>
  </div>
  <div class="wct-group">
    <label><?= wct($w_nl,'Onderwerp','Sujet') ?></label>
    <select name="sujet">
      <?php
      $opts = $w_nl ? [''=>'— Selecteer —','Devenir membre'=>'Lid worden','Signalement de nuisance'=>'Hinder melden','Question juridique'=>'Juridische vraag','Presse / média'=>'Pers / media','Autre'=>'Andere']
                    : [''=>'— Choisir —','Devenir membre'=>'Devenir membre','Signalement de nuisance'=>'Signalement','Question juridique'=>'Question juridique','Presse / média'=>'Presse / média','Autre'=>'Autre'];
      foreach ($opts as $v=>$l) echo '<option value="'.htmlspecialchars($v).'">'.htmlspecialchars($l).'</option>';
      ?>
    </select>
  </div>
  <div class="wct-group">
    <label><?= wct($w_nl,'Bericht *','Message *') ?></label>
    <textarea name="message" required placeholder="<?= wct($w_nl,'Uw bericht...','Votre message...') ?>"></textarea>
  </div>
  <button type="submit" class="wct-btn" id="wct-btn">
    📨 <?= wct($w_nl,'Bericht verzenden','Envoyer') ?>
  </button>
</form>

<script>
function wctSubmit(e) {
  e.preventDefault();
  var btn = document.getElementById('wct-btn');
  var alrt = document.getElementById('wct-alert');
  btn.disabled = true;
  btn.textContent = '<?= wct($w_nl,'Verzenden...','Envoi...') ?>';
  var data = new FormData(document.getElementById('wct-form'));
  data.append('lang', '<?= $w_nl ? 'nl' : 'fr' ?>');
  fetch('/api/contact_submit.php', {method:'POST', body:data})
    .then(function(r){ return r.json(); })
    .then(function(d){
      alrt.className = 'wct-alert ' + (d.ok ? 'wct-ok' : 'wct-err');
      alrt.textContent = d.message;
      alrt.style.display = 'block';
      if (d.ok) {
        document.getElementById('wct-form').reset();
        btn.textContent = '✅ <?= wct($w_nl,'Verzonden','Envoyé') ?>';
      } else {
        btn.disabled = false;
        btn.textContent = '📨 <?= wct($w_nl,'Bericht verzenden','Envoyer') ?>';
      }
    })
    .catch(function(){
      alrt.className = 'wct-alert wct-err';
      alrt.textContent = '<?= wct($w_nl,'Verbindingsfout. Probeer opnieuw.','Erreur réseau. Réessayez.') ?>';
      alrt.style.display = 'block';
      btn.disabled = false;
      btn.textContent = '📨 <?= wct($w_nl,'Bericht verzenden','Envoyer') ?>';
    });
}
</script>
</div>
