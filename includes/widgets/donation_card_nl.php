<?php // Widget : Carte de don — Version NL ?>
<!-- CARTE DON NL -->
  <div class="donation-card">
    <div class="don-titre">💶 Steun onze actie</div>
    <div class="don-sub"><?= htmlspecialchars(cfgLang('don_texte','Gerechtskosten — Kortgedingprocedure')) ?></div>

    <!-- Twee opties -->
    <div class="don-options">

      <!-- Optie 2 : Lid worden -->
      <div class="don-option don-option-membre" id="option-membre">
        <div class="don-option-header">
          <span class="don-option-icon">👤</span>
          <div>
            <div class="don-option-titre">Lid worden</div>
            <div class="don-option-sub">Persoonlijke QR-code + nieuwsbrief</div>
          </div>
        </div>
        <div style="padding:12px;font-size:.8rem;color:#555;line-height:1.7;background:var(--bleu-leger);border-radius:6px;margin-bottom:10px">
          ✅ QR-code met unieke gestructureerde mededeling <strong>(+++)</strong><br>
          ✅ Overzicht van uw giften in uw persoonlijke ruimte<br>
          ✅ Nieuwsbrief — blijf op de hoogte van onze acties<br>
          ✅ Beveiligde toegang via magische link (zonder wachtwoord)
        </div>
        <a href="membre/inscription.php" class="btn-devenir-membre">
          ✦ Mijn ledenruimte aanmaken
        </a>
        <a href="membre/login.php" class="btn-deja-membre">
          Al lid → toegang tot mijn ruimte
        </a>
      </div>

      <!-- Optie 1 : Anonieme gift -->
      <div class="don-option" id="option-anonyme">
        <div class="don-option-header">
          <span class="don-option-icon">🎯</span>
          <div>
            <div class="don-option-titre">Anonieme gift</div>
            <div class="don-option-sub">Eenvoudige overschrijving, zonder account</div>
          </div>
        </div>
        <!-- Bedragen -->
        <div class="don-montant-grid" id="montant-grid">
          <button class="don-mbtn" data-v="20"  onclick="selectMontant(this)">20 €</button>
          <button class="don-mbtn active" data-v="50" onclick="selectMontant(this)">50 €</button>
          <button class="don-mbtn" data-v="100" onclick="selectMontant(this)">100 €</button>
          <button class="don-mbtn" data-v="250" onclick="selectMontant(this)">250 €</button>
          <button class="don-mbtn" data-v="500" onclick="selectMontant(this)">500 €</button>
          <button class="don-mbtn" data-v=""    onclick="selectMontant(this)">Vrij bedrag</button>
        </div>
        <div id="libre-wrap" style="display:none;margin-bottom:8px">
          <input type="number" id="montant-libre" min="1" step="1" placeholder="Vrij bedrag in €"
                 style="width:100%;padding:7px 10px;border:1.5px solid var(--bleu-ciel);border-radius:6px;font-size:.85rem;font-family:inherit;outline:none"
                 oninput="updateMontantLibre(this.value)">
        </div>
        <div class="qr-section" id="qr-anonyme">
          <div id="qrcode-anonyme" style="display:inline-block;border:3px solid var(--bleu-hex);border-radius:6px;background:#fff;line-height:0"></div>
          <div style="margin-top:8px;font-size:.75rem;color:#888">Scan met uw bank-app</div>
        </div>
        <div class="iban-box">
          <div class="iban-val"><?= htmlspecialchars(cfg('iban','BE41 0689 0149 6910')) ?></div>
          <div class="iban-bic">BIC : <?= htmlspecialchars(cfg('bic','GKCCBEBB')) ?> · <?= htmlspecialchars(cfg('beneficiaire','Ça suffit ! ASBL')) ?></div>
          <div class="iban-comm">Mededeling : <strong>DON CASUFFIT <?= date('Y') ?></strong></div>
          <button class="btn-copy" id="copy-btn" onclick="copyIBAN()">📋 IBAN kopiëren</button>
        </div>
        <label class="don-check">
          <input type="checkbox" id="no-newsletter" checked>
          <span>Ik wens mij niet in te schrijven voor de nieuwsbrief</span>
        </label>
      </div>

    </div><!-- /don-options -->

  </div><!-- /donation-card -->
