<?php // Widget : Carte de don ?>
<!-- CARTE DON -->
  <div class="donation-card">
    <div class="don-titre">💶 Soutenez notre action</div>
    <div class="don-sub"><?= htmlspecialchars(cfg('don_texte','Combat juridique — Frais et procédures')) ?></div>

    <!-- Deux options -->
    <div class="don-options">

      <!-- Option 2 : Devenir membre -->
      <div class="don-option don-option-membre" id="option-membre">
        <div class="don-option-header">
          <span class="don-option-icon">👤</span>
          <div>
            <div class="don-option-titre">Devenir membre</div>
            <div class="don-option-sub">QR code personnel + newsletter</div>
          </div>
        </div>
        <div style="padding:12px;font-size:.8rem;color:#555;line-height:1.7;background:var(--bleu-leger);border-radius:6px;margin-bottom:10px">
          ✅ QR code avec communication structurée <strong>(+++)</strong> unique<br>
          ✅ Historique de vos dons dans votre espace<br>
          ✅ Newsletter — restez informé(e) de nos actions<br>
          ✅ Accès sécurisé par lien magique (sans mot de passe)
        </div>
        <a href="membre/inscription.php" class="btn-devenir-membre">
          ✦ Créer mon espace membre
        </a>
        <a href="membre/login.php" class="btn-deja-membre">
          Déjà membre → accéder à mon espace
        </a>
      </div>


      <!-- Option 1 : Don anonyme -->
      <div class="don-option" id="option-anonyme">
        <div class="don-option-header">
          <span class="don-option-icon">🎯</span>
          <div>
            <div class="don-option-titre">Don anonyme</div>
            <div class="don-option-sub">Virement simple, sans compte</div>
          </div>
        </div>
                <!-- Montants -->
        <div class="don-montant-grid" id="montant-grid">
          <button class="don-mbtn" data-v="20"  onclick="selectMontant(this)">20 €</button>
          <button class="don-mbtn active" data-v="50" onclick="selectMontant(this)">50 €</button>
          <button class="don-mbtn" data-v="100" onclick="selectMontant(this)">100 €</button>
          <button class="don-mbtn" data-v="250" onclick="selectMontant(this)">250 €</button>
          <button class="don-mbtn" data-v="500" onclick="selectMontant(this)">500 €</button>
          <button class="don-mbtn" data-v=""    onclick="selectMontant(this)">Libre</button>
        </div>
        <div id="libre-wrap" style="display:none;margin-bottom:8px">
          <input type="number" id="montant-libre" min="1" step="1" placeholder="Montant libre en €"
                 style="width:100%;padding:7px 10px;border:1.5px solid var(--bleu-ciel);border-radius:6px;font-size:.85rem;font-family:inherit;outline:none"
                 oninput="updateMontantLibre(this.value)">
        </div>
        <div class="qr-section" id="qr-anonyme" onclick="openPayModal()" style="cursor:pointer">
          <div id="qrcode-anonyme" style="display:inline-block;border:3px solid var(--bleu-hex);border-radius:6px;background:#fff;line-height:0"></div>
          <div style="margin-top:8px;font-size:.75rem;color:#888">📷 Scannez · 📱 <span style="color:#1673B2;font-weight:700">Appuyez pour les coordonnées</span></div>
        </div>
        <div class="iban-box">
          <div class="iban-val"><?= htmlspecialchars(cfg('iban','BE41 0689 0149 6910')) ?></div>
          <div class="iban-bic">BIC : <?= htmlspecialchars(cfg('bic','GKCCBEBB')) ?> · <?= htmlspecialchars(cfg('beneficiaire','Ça suffit ! ASBL')) ?></div>
          <div class="iban-comm">Communication : <strong>DON CASUFFIT <?= date('Y') ?></strong></div>
          <button class="btn-copy" id="copy-btn" onclick="copyIBAN()">📋 Copier l'IBAN</button>
        </div>
        <label class="don-check">
          <input type="checkbox" id="no-newsletter" checked>
          <span>Je ne souhaite pas m'inscrire à la newsletter</span>
        </label>
      </div>


    </div><!-- /don-options -->

  </div><!-- /donation-card -->