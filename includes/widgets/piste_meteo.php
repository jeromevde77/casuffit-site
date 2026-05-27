<?php // includes/widgets/piste_meteo.php — v5 METAR + composantes + TAF ?>
<?php
// Destinataires de la plainte — configurable dans Admin → Paramètres (clé plainte_destinataires)
// Format en base : adresses séparées par virgule, point-virgule ou retour à la ligne
$pmw_dest_raw = function_exists('cfg') ? cfg('plainte_destinataires', 'airportmediation@mobilit.fgov.be') : 'airportmediation@mobilit.fgov.be';
$pmw_dest_list = preg_split('/[,;\n\r]+/', $pmw_dest_raw);
$pmw_dest_list = array_values(array_filter(array_map('trim', $pmw_dest_list)));
if (empty($pmw_dest_list)) $pmw_dest_list = ['airportmediation@mobilit.fgov.be'];
?>
<div class="pmw" id="pmw" data-plainte-dest="<?= htmlspecialchars(implode(',', $pmw_dest_list)) ?>">

  <div class="pmw-header">
    <div class="pmw-title">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"/></svg>
      Conditions de vent — Brussels Airport (EBBR)
    </div>
    <div style="display:flex;align-items:center;gap:8px">
      <button class="pmw-help-btn" onclick="pmwOpenHelp()" title="Mode d'emploi" aria-label="Mode d'emploi">?</button>
      <div class="pmw-upd" id="pmw-upd">Chargement…</div>
    </div>
  </div>

  <div class="pmw-body">

    <!-- ── VENT ACTUEL ───────────────────────────────────────────────── -->
    <div class="pmw-wind-section">

      <div class="pmw-wind">
        <div class="pmw-compass">
          <svg class="pmw-compass-ring" viewBox="0 0 100 100">
            <circle cx="50" cy="50" r="46" fill="none" stroke="#e8eef5" stroke-width="2"/>
            <text x="50" y="12"  text-anchor="middle" class="pmw-cdir">N</text>
            <text x="50" y="96"  text-anchor="middle" class="pmw-cdir">S</text>
            <text x="94" y="54" text-anchor="middle" class="pmw-cdir">E</text>
            <text x="6"  y="54" text-anchor="middle" class="pmw-cdir">O</text>
            <!-- Flèche vent : pointe vers le haut = vient du nord -->
            <g id="pmw-arrow-g" transform="rotate(0 50 50)">
              <line x1="50" y1="72" x2="50" y2="22" stroke="#1673B2" stroke-width="3.5" stroke-linecap="round"/>
              <polygon points="50,14 43,28 57,28" fill="#1673B2"/>
              <circle cx="50" cy="50" r="5" fill="#1673B2" opacity=".2"/>
            </g>
          </svg>
        </div>
        <div class="pmw-wind-vals">
          <div class="pmw-wdir" id="pmw-wdir">—</div>
          <div class="pmw-wspd" id="pmw-wspd">—</div>
          <div class="pmw-wgst" id="pmw-wgst"></div>
          <div class="pmw-weff" id="pmw-weff"></div>
        </div>
        <div class="pmw-prs-badge-wrap">
          <div class="pmw-prs-badge" id="pmw-prs-badge">—</div>
          <div class="pmw-prs-sub" id="pmw-prs-sub"></div>
          <div class="pmw-rwy-now" id="pmw-rwy-now"></div>
          <div class="pmw-alt-configs" id="pmw-alt-configs" style="display:none"></div>
        </div>
      </div>

      <div class="pmw-reason" id="pmw-reason"></div>


      <!-- Exceptions PRS -->
      <div class="pmw-exc" id="pmw-exc" style="display:none">
        <div class="pmw-exc-title">⚠ Exceptions PRS actives (AIP EBBR AD 2.21)</div>
        <ul id="pmw-exc-list"></ul>
      </div>
    </div>

    <!-- Planning PRS — au-dessus du tableau composantes -->
    <div class="pmw-section">
      <div class="pmw-planning-common" id="pmw-planning-box" style="display:none">
        <div class="pmw-planning-common-title">
          📅 Planning PRS — commun aux deux AIP
        </div>
        <div id="pmw-planning-detail" class="pmw-planning-common-detail"></div>
      </div>
    </div>

    <!-- ── TABLEAU COMPOSANTES ──────────────────────────────────────── -->
    <div class="pmw-section">
      <div class="pmw-section-title pmw-collapse-toggle" onclick="pmwToggleSection('composantes')" title="Cliquer pour afficher/masquer">
        Composantes de vent par piste
        <span class="pmw-toggle-icon" id="pmw-icon-composantes">▼</span>
      </div>
      <div id="pmw-sec-composantes" class="pmw-collapse-body">
      <div class="pmw-gust-note">
        💨 Format : <b>vent moyen / rafales</b> — Le seuil PRS (7 kt vent arrière) s'applique rafales incluses. <b>— = pas de rafales dans le METAR</b>
      </div>
      <table class="pmw-table" id="pmw-rwy-table">
        <thead>
          <tr>
            <th>Piste<br><span>QFU</span></th>
            <th>Vent de face<br><span>↗ moy / rafales</span></th>
            <th>Vent arrière<br><span>↙ moy / rafales · seuil 7</span></th>
            <th>Vent latéral<br><span>↔ moy / rafales · seuil 20</span></th>
            <th>PRS</th>
          </tr>
        </thead>
        <tbody id="pmw-rwy-tbody">
          <tr><td colspan="5" class="pmw-loading">Chargement…</td></tr>
        </tbody>
      </table>
      <div class="pmw-legend">
        <span class="pmw-leg pmw-ok">OK</span>
        <span class="pmw-leg pmw-warn">≥ 4 kt</span>
        <span class="pmw-leg pmw-danger">≥ 7 kt — hors PRS</span>
        <span class="pmw-leg pmw-xw">lat. ≥ 20 kt — hors PRS</span>
      </div>
      </div><!-- /pmw-sec-composantes -->
    </div>

    <!-- ── PREVISIONS TAF ───────────────────────────────────────────── -->
    <div class="pmw-section">
      <div class="pmw-section-title pmw-collapse-toggle" onclick="pmwToggleSection('previsions')" title="Cliquer pour afficher/masquer">
        Prévisions météo à Brussels Airport
        <span style="display:flex;align-items:center;gap:6px;margin-left:auto">
          <span id="pmw-taf-valid" class="pmw-taf-valid"></span>
          <span class="pmw-toggle-icon" id="pmw-icon-previsions">▼</span>
        </span>
      </div>
      <div id="pmw-sec-previsions" class="pmw-collapse-body">
      <div id="pmw-taf-grid" class="pmw-taf-grid">
        <div class="pmw-loading">Chargement…</div>
      </div>
      </div>
    </div>

    <div class="pmw-section">
      <div class="pmw-section-title pmw-collapse-toggle" onclick="pmwToggleSection('reglementaire')" title="Cliquer pour afficher/masquer">
        Comparaison réglementaire
        <span class="pmw-toggle-icon" id="pmw-icon-reglementaire">▼</span>
      </div>
      <div id="pmw-sec-reglementaire" class="pmw-collapse-body">
      <!-- Explication AIP, dans la comparaison, style neutre -->
      <div class="pmw-aip-note">
        ⚖ L'AIP sept. 2013 découle de l'instruction ministérielle du 17/07/2013 — seule base légale valide.
        L'AIP actuel (skeyes) applique des seuils plus permissifs, jugés illégaux.
      </div>

      <div class="pmw-cmp3-grid pmw-cmp2-grid">

        <!-- Colonne AIP 2013 (légal) -->
        <div class="pmw-cmp3-col pmw-cmp3-legal">
          <div class="pmw-cmp3-head">⚖ AIP sept. 2013</div>
          <div class="pmw-cmp3-sub">Instruction ministérielle 17/07/2013<br><b>Base légale</b></div>
          <div class="pmw-cmp3-seuils">
            Arrière 25 : <b>7 kt</b> (max 10 kt rafales)<br>
            Latéral 25 : <b>15 kt</b> (max 20 kt)<br>
            Arrière 01/07 : max <b>5 kt</b><br>
            <span style="color:#1a7a4a;font-weight:600">Rafales incluses ✓</span>
          </div>
          <div class="pmw-cmp3-badge" id="pmw-2013-badge">—</div>
          <div class="pmw-cmp3-rwys" id="pmw-2013-rwys"></div>
          <div class="pmw-cmp3-reason" id="pmw-2013-reason"></div>
        </div>

        <div class="pmw-cmp3-col pmw-cmp3-now">
          <div class="pmw-cmp3-head">📋 AIP actuel</div>
          <div class="pmw-cmp3-sub">skeyes 2025<br><b>Contesté juridiquement</b></div>
          <div class="pmw-cmp3-seuils">
            Arrière 25 : <b>7 kt</b> (texte)<br>
            <span style="color:#c0392b;font-weight:600">⚡ Pratique : ~6.5 kt</span><br>
            Latéral 25 : <b>20 kt</b> ⚠<br>
            Arrière 01/07 : <b>non mentionné</b> ⚠<br>
            <span style="color:#c97200;font-weight:600">Pas de tableau par piste</span>
          </div>
          <div class="pmw-cmp3-badge" id="pmw-now-badge">—</div>
          <div class="pmw-cmp3-rwys" id="pmw-now-rwys"></div>
          <div class="pmw-cmp3-reason" id="pmw-now-reason"></div>
          <div class="pmw-cmp3-pratique" id="pmw-pratique-note"></div>
        </div>

      </div>

      </div><!-- /pmw-sec-reglementaire -->
    </div>

    <!-- ── VÉRIFICATION BATC (bloc séparé, action de l'utilisateur) ──── -->
    <div class="pmw-batc-block">
      <div class="pmw-batc-block-head">
        <span class="pmw-batc-block-title">Vérifier la piste réellement en service</span>
      </div>
      <div class="pmw-batc-explain">
        <p class="pmw-batc-explain-intro">La piste réellement en service n'est pas récupérable automatiquement. Pour vérifier si elle respecte le PRS :</p>
        <div class="pmw-batc-step">
          <span class="pmw-batc-step-num">1</span>
          <span>Ouvrez <a href="https://www.batc.be/fr/pistes-en-usage/actuel-prevision" target="_blank" rel="noopener" class="pmw-batc-link">batc.be ↗</a> et notez la configuration affichée</span>
        </div>
        <div class="pmw-batc-step">
          <span class="pmw-batc-step-num">2</span>
          <span>Cliquez ci-dessous sur la même configuration</span>
        </div>
        <div class="pmw-batc-step">
          <span class="pmw-batc-step-num">3</span>
          <span>L'outil vous dit si elle respecte le PRS et propose de générer une plainte</span>
        </div>
      </div>
      <div class="pmw-batc-row">
        <button class="pmw-batc-btn" onclick="setBatc('25R/25L')">25R/25L</button>
        <button class="pmw-batc-btn" onclick="setBatc('19/25R')">19/25R</button>
        <button class="pmw-batc-btn" onclick="setBatc('07L/07R')">07L/07R</button>
        <button class="pmw-batc-btn" onclick="setBatc('01/07R')">01/07R</button>
        <button class="pmw-batc-btn" onclick="setBatc('01/01')">01/01</button>
        <button class="pmw-batc-btn" onclick="setBatc('19/19')">19/19</button>
        <button class="pmw-batc-btn pmw-batc-clear" onclick="setBatc(null)">✕</button>
      </div>
      <div class="pmw-batc-result" id="pmw-batc-rwys">
        <span style="color:#bbb;font-size:.78rem">Aucune configuration sélectionnée</span>
      </div>
      <!-- Verdict + bouton plainte -->
      <div class="pmw-verdict" id="pmw-verdict" style="display:none"></div>
    </div>
    <details class="pmw-details">
      <summary>METAR / TAF bruts</summary>
      <div class="pmw-raw-lbl">METAR</div>
      <code id="pmw-raw-metar"></code>
      <div class="pmw-raw-lbl">TAF</div>
      <code id="pmw-raw-taf"></code>
    </details>

    <div class="pmw-links">
      <a href="https://www.batc.be/fr/meteo/mesures-meteo" target="_blank" rel="noopener">BATC météo ↗</a>
      <a href="https://metar-taf.com/metar/EBBR" target="_blank" rel="noopener">METAR EBBR ↗</a>
      <a href="https://www.batc.be/fr/pistes-en-usage/actuel-prevision" target="_blank" rel="noopener">Pistes en service ↗</a>
    </div>

    <p class="pmw-disclaimer">
      Calcul basé sur le PRS skeyes/BATC (AIP EBBR AD 2.21) — QFU : 07L=066°, 25R=246°, 07R=071°, 25L=251°, 01=014°, 19=194°.
      Rafales incluses. Donnée indicative, non contractuelle.
    </p>

    <!-- Bouton "Porter plainte" permanent -->
    <div class="pmw-report-wrap">
      <button class="pmw-report-btn" id="pmw-report-btn" onclick="pmwOpenRwySelector()">
        Je constate un usage anormal des pistes, je désire porter plainte
      </button>
    </div>

  </div><!-- /pmw-body -->
</div>

<!-- Modale sélection piste -->
<div class="pmw-rwy-overlay" id="pmw-rwy-overlay" onclick="if(event.target===this)pmwCloseRwySelector()">
  <div class="pmw-rwy-modal">
    <div class="pmw-rwy-title">Quelle piste observez-vous ?</div>
    <div class="pmw-rwy-sub">Regardez l'avion depuis chez vous — vers le nord ou vers l'est ?</div>
    <div class="pmw-rwy-btns">
      <button class="pmw-rwy-btn" onclick="pmwSelectRwy('01')">
        <span class="pmw-rwy-icon">↑</span>
        <strong>Piste 01</strong>
        <span>vers le nord</span>
      </button>
      <button class="pmw-rwy-btn" onclick="pmwSelectRwy('07')">
        <span class="pmw-rwy-icon">→</span>
        <strong>Piste 07</strong>
        <span>07L / 07R — vers l'est</span>
      </button>
    </div>
    <details class="pmw-rwy-details">
      <summary>🔗 Vérifier la piste en service sur BATC</summary>
      <div class="pmw-rwy-details-body">
        <p>Si vous n'êtes pas sûr(e) de la piste utilisée, consultez le site BATC qui affiche en temps réel les pistes en service à Brussels Airport.</p>
        <a href="https://www.batc.be/fr/pistes-en-usage/actuel-prevision" target="_blank" rel="noopener">
          Ouvrir BATC — Pistes en service ↗
        </a>
      </div>
    </details>
    <button class="pmw-rwy-cancel" onclick="pmwCloseRwySelector()">Annuler</button>
  </div>
</div>
<!-- Modale mode d'emploi -->
<div class="pmw-help-overlay" id="pmw-help-overlay" onclick="if(event.target===this) pmwCloseHelp()">
  <div class="pmw-help-modal">
    <div class="pmw-help-head">
      <span>Comment lire cet outil ?</span>
      <button class="pmw-help-close" onclick="pmwCloseHelp()" aria-label="Fermer">✕</button>
    </div>
    <div class="pmw-help-body">

      <div class="pmw-help-block">
        <div class="pmw-help-q">À quoi sert cet outil ?</div>
        <p>Il compare automatiquement les <strong>conditions de vent réelles</strong> à l'aéroport de Bruxelles-National avec les <strong>règles du Plan de Répartition du Survol (PRS)</strong>. Objectif : détecter quand une piste est utilisée alors que les conditions météo ne le justifient pas.</p>
      </div>

      <div class="pmw-help-block">
        <div class="pmw-help-q">D'où viennent les données ?</div>
        <p>Le vent, les rafales et le METAR proviennent des relevés officiels de l'aéroport (METAR EBBR), récupérés <strong>automatiquement toutes les 30 minutes</strong>. Vous n'avez rien à faire : les données s'affichent et se mettent à jour seules.</p>
      </div>

      <div class="pmw-help-block">
        <div class="pmw-help-q">Le badge vert / rouge (PRS)</div>
        <p><span class="pmw-help-badge pmw-help-on">PRS RESPECTÉ</span> : la configuration de pistes attendue selon le vent correspond aux règles.<br>
        <span class="pmw-help-badge pmw-help-off">PRS NON RESPECTÉ</span> : les conditions de vent ne justifient pas la piste utilisée — c'est une situation potentiellement contestable.</p>
      </div>

      <div class="pmw-help-block">
        <div class="pmw-help-q">La colonne « Réel BATC »</div>
        <p>L'outil <strong>ne connaît pas automatiquement</strong> quelle piste est réellement en service à un instant donné. Pour le savoir, cliquez sur le lien <strong>« Voir batc.be ↗ »</strong> : il ouvre le site officiel de Brussels Airport qui indique la configuration en cours.</p>
        <p>Revenez ensuite sur l'outil et <strong>cliquez sur le bouton correspondant</strong> à la configuration que vous avez lue (ex : <em>25R/25L</em>, <em>01/07R</em>…). L'outil compare alors cette piste réelle aux règles et vous dit si elle est conforme.</p>
      </div>

      <div class="pmw-help-block">
        <div class="pmw-help-q">Générer une plainte</div>
        <p>Si une violation est constatée, le bouton <strong>« Générer une plainte »</strong> prépare un message complet (données météo, configuration, analyse réglementaire) que vous copiez en un clic pour le coller dans votre email à l'autorité compétente.</p>
      </div>

      <div class="pmw-help-note">
        Cet outil est un appui citoyen : il met en forme des données publiques. Il ne remplace pas une démarche officielle mais la facilite.
      </div>

    </div>
    <div class="pmw-help-foot">
      <button class="pmw-help-ok" onclick="pmwCloseHelp()">J'ai compris</button>
    </div>
  </div>
</div>

<!-- Modale plainte -->
<div class="pmw-plainte-overlay" id="pmw-plainte-overlay" onclick="if(event.target===this) pmwClosePlainte()">
  <div class="pmw-plainte-modal">
    <div class="pmw-plainte-title">✉ Envoyer une plainte</div>
    <div class="pmw-plainte-sub">Aux autorités compétentes (médiateur, communes, associations…)</div>

    <!-- Mode d'emploi en 3 étapes -->
    <div class="pmw-plainte-steps">
      <div class="pmw-plainte-step-row">
        <span class="pmw-plainte-step-n">1</span>
        <div><b>Copiez le contenu de la plainte</b> — toutes les données (météo, configuration, analyse) sont copiées en un clic.</div>
      </div>
      <div class="pmw-plainte-step-row">
        <span class="pmw-plainte-step-n">2</span>
        <div><b>Ouvrez un nouvel email</b> vers les destinataires (bouton ci-dessous, les adresses sont pré-remplies).</div>
      </div>
      <div class="pmw-plainte-step-row">
        <span class="pmw-plainte-step-n">3</span>
        <div><b>Collez</b> le contenu dans le corps du message (Ctrl+V / Cmd+V) et envoyez.</div>
      </div>
    </div>

    <!-- Boutons principaux -->
    <div class="pmw-plainte-actions">
      <button class="pmw-plainte-btn pmw-plainte-btn-copy" onclick="pmwCopyComplaint()">📋 Copier le contenu de la plainte</button>
      <button class="pmw-plainte-btn pmw-plainte-btn-mail" onclick="pmwOpenMail()">✉ Ouvrir un email pré-adressé</button>
    </div>

    <!-- Capture image : option discrète -->
    <div class="pmw-plainte-capture-zone">
      <div id="pmw-plainte-loading" class="pmw-plainte-loading-mini">⏳ Capture en cours…</div>
      <img id="pmw-plainte-img" class="pmw-plainte-capture-mini" style="display:none" alt="Capture conditions EBBR">
      <a href="#" class="pmw-plainte-capture-link" onclick="pmwDownloadCapture(); return false;">⬇ Télécharger l'image (preuve visuelle facultative à joindre)</a>
    </div>

    <button class="pmw-plainte-btn pmw-plainte-btn-close" onclick="pmwClosePlainte()">Fermer</button>
  </div>
</div>

<style>
/* ── Base ── */
.pmw{font-family:"Helvetica Neue",Arial,sans-serif;background:#fff;border-radius:12px;border:1.5px solid #dde6f0;overflow:hidden;max-width:580px;margin:0 auto;font-size:14px}
.pmw-header{background:#0e3d6b;color:#fff;padding:13px 18px;display:flex;align-items:center;justify-content:space-between;gap:8px}
.pmw-title{display:flex;align-items:center;gap:7px;font-weight:700;font-size:.88rem}
.pmw-upd{font-size:.67rem;color:rgba(255,255,255,.55);white-space:nowrap;flex-shrink:0}
.pmw-body{padding:16px;display:flex;flex-direction:column;gap:16px}

/* ── Vent actuel ── */
.pmw-wind-section{border-bottom:1.5px solid #f0f4f8;padding-bottom:14px}
.pmw-wind{display:flex;align-items:center;gap:14px;margin-bottom:10px}
.pmw-compass{width:80px;height:80px;flex-shrink:0}
.pmw-compass-ring{width:100%;height:100%}
.pmw-cdir{font-size:12px;font-weight:700;fill:#aaa}
.pmw-wind-vals{flex:1;min-width:0}
.pmw-wdir{font-size:1.5rem;font-weight:800;color:#0e3d6b;line-height:1.1}
.pmw-wspd{font-size:1rem;font-weight:600;color:#1673B2;margin-top:2px}
.pmw-wgst{font-size:.8rem;color:#e07000;font-weight:600;margin-top:2px}
.pmw-weff{font-size:.7rem;color:#888;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.pmw-prs-badge-wrap{text-align:right;flex-shrink:0}
.pmw-prs-badge{display:inline-block;padding:4px 12px;border-radius:20px;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px}
.pmw-prs-on{background:#e8f8f0;color:#1a7a4a;border:1.5px solid #b2f0d0}
.pmw-prs-off{background:#fde8e8;color:#c0392b;border:1.5px solid #fca5a5}
.pmw-prs-sub{font-size:.67rem;color:#999;margin-bottom:6px}
.pmw-rwy-now{display:flex;gap:5px;flex-wrap:wrap;justify-content:flex-end}
/* Configs alternatives classées */
.pmw-alt-configs{margin-top:6px;text-align:right}
.pmw-alt-configs-title{font-size:.62rem;color:#aaa;margin-bottom:4px;text-transform:uppercase;letter-spacing:.04em}
.pmw-alt-list{display:flex;flex-direction:column;gap:3px;align-items:flex-end}
.pmw-alt-item{display:flex;align-items:center;gap:5px;font-size:.72rem;font-weight:700;padding:3px 8px;border-radius:6px;white-space:nowrap}
.pmw-alt-best{background:#e8f8f0;color:#1a7a4a;border:1.5px solid #b2f0d0}
.pmw-alt-good{background:#fff8ee;color:#c97200;border:1.5px solid #ffd080}
.pmw-alt-hw{font-size:.62rem;font-weight:400;opacity:.8}
.pmw-rwy{display:inline-flex;align-items:center;padding:4px 11px;border-radius:7px;font-size:1.05rem;font-weight:800;background:#e8f8f0;color:#1a7a4a;border:2px solid #1a7a4a}
.pmw-rwy-25,.pmw-rwy-07,.pmw-rwy-01,.pmw-rwy-19{background:#e8f8f0;color:#1a7a4a;border-color:#1a7a4a}
.pmw-rwy-cfg{background:#e8f8f0;color:#1a7a4a;border:2px solid #1a7a4a;font-size:.95rem}
.pmw-reason{font-size:.78rem;color:#444;line-height:1.6;background:#f7fafd;border-radius:7px;padding:8px 12px;border-left:3px solid #1673B2}
.pmw-reason.alert{border-left-color:#e53e3e;background:#fff5f5;color:#742a2a}
.pmw-plan-jour{font-weight:700;color:#0e3d6b;font-size:.8rem}
.pmw-plan-plage{color:#888;font-size:.7rem}
.pmw-plan-dep{color:#1673B2;font-weight:600}
.pmw-plan-arr{color:#555;font-weight:600}
.pmw-plan-sep{color:#ccc}
.pmw-plan-note{font-size:.67rem;color:#888;font-style:italic;margin-top:4px}
.pmw-exc{background:#fff5f5;border:1.5px solid #fca5a5;border-radius:7px;padding:9px 13px}
.pmw-exc-title{font-size:.72rem;font-weight:700;color:#c0392b;margin-bottom:5px}
.pmw-exc ul{list-style:none;margin:0;padding:0}
.pmw-exc ul li{font-size:.77rem;color:#742a2a;padding:2px 0;display:flex;align-items:center;gap:5px}
.pmw-exc ul li::before{content:'✕';font-size:.62rem;color:#e53e3e}

/* ── Sections ── */
.pmw-section{display:flex;flex-direction:column;gap:10px}
.pmw-section-title{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#0e3d6b;display:flex;align-items:center;gap:8px}
.pmw-collapse-toggle{cursor:pointer;user-select:none;justify-content:space-between}
.pmw-collapse-toggle:hover{color:#1673B2}
.pmw-toggle-icon{font-size:.7rem;color:#aaa;transition:transform .2s}
.pmw-toggle-icon.collapsed{transform:rotate(-90deg)}
.pmw-collapse-body{overflow:hidden}
.pmw-collapse-body.collapsed{display:none}
.pmw-taf-valid{font-weight:400;color:#aaa;text-transform:none;letter-spacing:0;font-size:.67rem}

/* ── Tableau composantes ── */
.pmw-table{width:100%;border-collapse:collapse;font-size:.77rem}
.pmw-table th{text-align:left;padding:6px 8px;font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#888;border-bottom:2px solid #f0f4f8;background:#fafbfc;line-height:1.4}
.pmw-gust-val{font-size:.72rem;font-weight:700;color:#666}
.pmw-gust-na{font-size:.68rem;color:#ccc}
.pmw-table th span{font-weight:400;text-transform:none;letter-spacing:0;color:#bbb}
.pmw-gust-note{font-size:.67rem;color:#e07000;background:#fff8ee;border:1px solid #ffd080;border-radius:5px;padding:4px 10px;margin-top:6px;display:inline-block}
.pmw-table td{padding:8px 8px;border-bottom:1px solid #f5f5f5;vertical-align:middle}
.pmw-table tr:last-child td{border-bottom:none}
.pmw-table tr.pmw-tr-active td{background:#f0f8ff}
.pmw-table tr.pmw-tr-pref td:first-child{border-left:3px solid #1a7a4a}
.pmw-table tr.pmw-tr-alert td:first-child{border-left:3px solid #e53e3e}
.pmw-rwy-name{font-weight:700;color:#0e3d6b;font-size:.88rem}
.pmw-qfu{font-size:.62rem;color:#bbb;display:block}
.pmw-val{font-weight:700;font-size:.85rem}
.pmw-ok{color:#1a7a4a}.pmw-warn{color:#c97200}.pmw-danger{color:#c0392b}.pmw-grey{color:#bbb}
.pmw-prs-ok{display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;background:#e8f8f0;border-radius:50%;color:#1a7a4a;font-size:.72rem;font-weight:700}
.pmw-prs-ko{display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;background:#fde8e8;border-radius:50%;color:#c0392b;font-size:.72rem;font-weight:700}
.pmw-loading{text-align:center;color:#bbb;padding:12px}
.pmw-legend{display:flex;gap:8px;flex-wrap:wrap;font-size:.65rem}
.pmw-leg{padding:2px 8px;border-radius:4px;font-weight:600}
.pmw-leg.pmw-ok{background:#e8f8f0;color:#1a7a4a;border:1px solid #b2f0d0}
.pmw-leg.pmw-warn{background:#fff8ee;color:#c97200;border:1px solid #ffd080}
.pmw-leg.pmw-danger{background:#fde8e8;color:#c0392b;border:1px solid #fca5a5}
.pmw-leg.pmw-xw{background:#f5f0ff;color:#7c3aed;border:1px solid #ddd6fe}

/* ── TAF ── */
.pmw-taf-grid{display:flex;flex-direction:column;gap:5px}
.pmw-taf-row{border-radius:8px;border:1.5px solid #e8eef5;background:#fafbfc;overflow:hidden}
.pmw-taf-row.pmw-taf-alert{border-color:#fca5a5;background:#fff8f8}
.pmw-taf-row.pmw-taf-now{border-color:#b2f0d0;background:#f0fdf6}
.pmw-taf-main{display:grid;grid-template-columns:80px 1fr auto;align-items:center;gap:10px;padding:9px 12px}
.pmw-taf-time{line-height:1.4}
.pmw-taf-hlocal{font-size:.95rem;font-weight:800;display:block;color:#0e3d6b}
.pmw-taf-utc{font-weight:400;color:#aaa;font-size:.65rem;display:block}
.pmw-taf-now-lbl{font-size:.6rem;background:#1a7a4a;color:#fff;padding:1px 5px;border-radius:3px;font-weight:700;display:inline-block;margin-bottom:2px}
.pmw-taf-wind{font-size:.82rem;color:#333;line-height:1.5}
.pmw-taf-prs{text-align:center;font-size:.75rem;font-weight:800;padding:5px 10px;border-radius:8px;min-width:85px;line-height:1.4;white-space:nowrap}
.pmw-taf-prs span{font-weight:400;font-size:.65rem;display:block}
.pmw-taf-prs-ok{background:#e8f8f0;color:#1a7a4a;border:1.5px solid #b2f0d0}
.pmw-taf-prs-ko{background:#fff8ee;color:#c97200;border:1.5px solid #ffd080}
/* ── Config bar TAF ── */
.pmw-taf-cfgbar{padding:6px 12px 8px;display:flex;flex-wrap:wrap;gap:6px;border-top:1px solid #f0f4f8}
.pmw-taf-cfggroup{display:flex;align-items:center;gap:4px;flex-wrap:wrap}
.pmw-taf-cfglbl{font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;padding:2px 5px;border-radius:3px}
.pmw-cfg-lbl-ko{background:#fde8e8;color:#c0392b}
.pmw-cfg-lbl-ok{background:#e8f0fa;color:#1673B2}
.pmw-taf-cfgtag{font-size:.68rem;font-weight:700;padding:2px 7px;border-radius:5px;cursor:default}
.pmw-cfg-ko{background:#fde8e8;color:#c0392b;text-decoration:line-through;opacity:.7}
.pmw-cfg-best{background:#e8f8f0;color:#1a7a4a;border:1px solid #b2f0d0}
.pmw-cfg-ok{background:#fff8ee;color:#c97200;border:1px solid #ffd080}

/* ── Note légale ── */
.pmw-aip-note{font-size:.73rem;color:#555;background:#fafbfc;border:1.5px solid #e2e8f0;border-radius:7px;padding:8px 12px;line-height:1.5;margin-bottom:10px}
/* ── Grille 3 colonnes ── */
.pmw-cmp3-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-top:10px}
.pmw-cmp2-grid{grid-template-columns:1fr 1fr}
@media(max-width:520px){
  .pmw-cmp3-grid{grid-template-columns:1fr}
  .pmw-cmp2-grid{grid-template-columns:1fr}
  /* ── Bloc vent principal ── */
  .pmw-wind{gap:8px}
  .pmw-compass{width:64px;height:64px;flex-shrink:0}
  .pmw-wind-vals{min-width:0;flex:1}
  .pmw-wdir{font-size:1.2rem;line-height:1}
  .pmw-wspd{font-size:.88rem;margin-top:1px}
  .pmw-wgst{font-size:.75rem;margin-top:1px}
  .pmw-weff{font-size:.65rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  /* ── Badge PRS ── */
  .pmw-prs-badge-wrap{max-width:110px}
  .pmw-prs-badge{font-size:.65rem;padding:3px 8px;white-space:nowrap}
  .pmw-rwy-now{justify-content:flex-end;gap:3px}
  .pmw-alt-item{font-size:.65rem;padding:2px 6px;white-space:nowrap}
  /* ── Cartes de piste ── */
  .pmw{overflow-x:hidden}
  .pmw-rwy-now{flex-wrap:wrap}
  /* ── Reason bar ── */
  .pmw-reason{font-size:.72rem;line-height:1.5;overflow-wrap:break-word;word-break:break-word}
  /* ── Section comparaison ── */
  .pmw-cmp3-col{padding:8px}
}
.pmw-cmp3-col{border-radius:9px;padding:12px;display:flex;flex-direction:column;gap:7px}
.pmw-cmp3-legal{background:#f0fdf6;border:2px solid #b2f0d0}
.pmw-cmp3-now{background:#fff8ee;border:2px solid #ffd080}
.pmw-cmp3-head{font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#0e3d6b}
.pmw-cmp3-sub{font-size:.67rem;color:#666;line-height:1.4}
.pmw-cmp3-seuils{font-size:.68rem;color:#555;line-height:1.6;background:rgba(255,255,255,.6);border-radius:5px;padding:5px 8px}
.pmw-cmp3-badge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;width:fit-content}
.pmw-cmp3-prs-on{background:#e8f8f0;color:#1a7a4a;border:1.5px solid #b2f0d0}
.pmw-cmp3-prs-off{background:#fde8e8;color:#c0392b;border:1.5px solid #fca5a5}
.pmw-cmp3-rwys{display:flex;gap:4px;flex-wrap:wrap;min-height:28px;align-items:center}
.pmw-planning-common{background:#f0f8ff;border:1.5px solid #b0d4f0;border-radius:8px;padding:10px 14px;display:flex;align-items:center;gap:14px;flex-wrap:wrap}
.pmw-planning-common-title{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#0e3d6b;white-space:nowrap}
.pmw-cfg-grid{display:grid;grid-template-columns:1fr 1fr;gap:4px;margin-top:6px}
.pmw-cfg-row{display:flex;align-items:center;gap:4px;margin:2px 0;flex-wrap:wrap}
.pmw-cfg-lbl{font-size:.62rem;color:#888;font-weight:600;min-width:16px}
.pmw-cfg-badge{display:inline-flex;align-items:center;justify-content:center;padding:4px 8px;border-radius:6px;font-size:.78rem;font-weight:800;border:2px solid;width:100%;text-align:center}
.pmw-cb-ok{background:#e8f8f0;color:#1a7a4a;border-color:#1a7a4a}
.pmw-cb-ko{background:#fde8e8;color:#c0392b;border-color:#fca5a5;text-decoration:line-through;opacity:.7}
.pmw-crit:last-child{border-bottom:none}
.pmw-crit-ok{color:#1a7a4a}
.pmw-crit-ko{color:#c0392b;font-weight:700}
.pmw-crit-icon{font-size:.78rem;font-weight:700}
.pmw-crit-lbl{color:inherit;opacity:.9}
.pmw-crit-val{font-weight:700;text-align:right}
.pmw-crit-seuil{color:#aaa;font-size:.62rem;margin-left:2px}
.pmw-planning-common-detail{display:flex;gap:16px;flex-wrap:wrap;align-items:center;font-size:.78rem}
.pmw-planning-dep{color:#0e3d6b;font-weight:600}
.pmw-planning-arr{color:#555;font-weight:600}
.pmw-planning-plage{color:#888;font-size:.68rem}
.pmw-planning-note-txt{color:#aaa;font-style:italic;font-size:.65rem}
.pmw-batc-link{font-size:.67rem;color:#1673B2;text-decoration:underline;font-weight:700}
.pmw-batc-link:hover{text-decoration:underline}
.pmw-batc-row{display:flex;gap:4px;flex-wrap:wrap;margin-top:6px}
.pmw-batc-explain{margin:4px 0 8px;text-align:left}
.pmw-batc-explain-intro{font-size:.66rem;color:#777;line-height:1.4;margin:0 0 6px}
.pmw-batc-step{display:flex;align-items:flex-start;gap:6px;font-size:.66rem;color:#555;line-height:1.35;margin-bottom:4px}
.pmw-batc-step-num{flex-shrink:0;width:15px;height:15px;border-radius:50%;background:#1673B2;color:#fff;font-size:.6rem;font-weight:700;display:flex;align-items:center;justify-content:center;margin-top:1px}
.pmw-batc-step a{color:#1673B2;font-weight:700}
.pmw-batc-btn{padding:4px 10px;border-radius:7px;border:1.5px solid #c8dcef;background:#fff;font-size:.8rem;font-weight:700;cursor:pointer;font-family:inherit;color:#0e3d6b;transition:all .15s}
/* classes pmw-bc-* supprimées — boutons BATC neutres */
.pmw-batc-btn:hover{background:#e8f0fa;border-color:#1673B2}
.pmw-batc-btn.active{background:#1673B2;color:#fff;border-color:#1673B2}
.pmw-batc-clear{color:#e53e3e;border-color:#fca5a5;background:#fff5f5}
.pmw-batc-clear:hover{background:#fee2e2}
/* ── Bloc BATC séparé visuellement (neutre) ── */
.pmw-batc-block{margin-top:6px;background:#fafbfc;border:1.5px solid #e2e8f0;border-radius:12px;padding:14px 16px}
.pmw-batc-block-head{display:flex;align-items:center;gap:8px;margin-bottom:10px}
.pmw-batc-block-title{font-size:.82rem;font-weight:800;color:#0e3d6b}
.pmw-batc-result{margin-top:10px}
.pmw-verdict-txt{margin-bottom:8px;line-height:1.5}
.pmw-mail-btn{display:block;width:100%;margin-top:8px;padding:10px 14px;background:#0e3d6b;color:#fff;border:none;border-radius:8px;font-size:.82rem;font-weight:700;cursor:pointer;font-family:inherit;text-align:center;transition:background .15s}
.pmw-mail-btn:hover{background:#1673B2}
/* Modale plainte */
.pmw-plainte-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9999;align-items:center;justify-content:center;padding:16px}
.pmw-plainte-overlay.open{display:flex}
.pmw-plainte-modal{background:#fff;border-radius:14px;padding:24px;max-width:640px;width:100%;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.3)}
.pmw-plainte-title{font-size:1rem;font-weight:800;color:#0e3d6b;margin-bottom:4px}
.pmw-plainte-sub{font-size:.75rem;color:#888;margin-bottom:16px}
.pmw-plainte-loading{text-align:center;padding:20px;color:#888;font-size:.85rem}
/* Étapes mode d'emploi */
.pmw-plainte-steps{margin-bottom:16px;display:flex;flex-direction:column;gap:10px}
.pmw-plainte-step-row{display:flex;align-items:flex-start;gap:10px;font-size:.82rem;color:#444;line-height:1.45}
.pmw-plainte-step-n{flex-shrink:0;width:22px;height:22px;border-radius:50%;background:#1673B2;color:#fff;font-size:.78rem;font-weight:700;display:flex;align-items:center;justify-content:center;margin-top:1px}
.pmw-plainte-step-row b{color:#0e3d6b}
/* Boutons principaux */
.pmw-plainte-actions{display:flex;flex-direction:column;gap:8px;margin-bottom:14px}
.pmw-plainte-btn{padding:13px;border:none;border-radius:8px;font-size:.88rem;font-weight:700;cursor:pointer;font-family:inherit;width:100%}
.pmw-plainte-btn-copy{background:#F5A623;color:#fff}
.pmw-plainte-btn-copy:hover{background:#e0950f}
.pmw-plainte-btn-copy.pmw-copied{background:#1a7a4a}
.pmw-plainte-btn-mail{background:#0e3d6b;color:#fff}
.pmw-plainte-btn-mail:hover{background:#1673B2}
.pmw-plainte-btn-close{background:none;color:#999;font-weight:600;font-size:.8rem;padding:8px;width:auto;margin:0 auto;display:block}
.pmw-plainte-btn-close:hover{color:#555;text-decoration:underline}
/* Capture image — option discrète */
.pmw-plainte-capture-zone{border-top:1px solid #eef2f6;padding-top:12px;margin-bottom:12px;text-align:center}
.pmw-plainte-loading-mini{font-size:.7rem;color:#bbb}
.pmw-plainte-capture-mini{width:100%;max-width:220px;border-radius:6px;border:1px solid #e8e8e8;margin:0 auto 6px;display:block;opacity:.9}
.pmw-plainte-capture-link{font-size:.72rem;color:#999;text-decoration:none}
.pmw-plainte-capture-link:hover{color:#1673B2;text-decoration:underline}

/* ── Bouton aide "?" ── */
.pmw-help-btn{width:22px;height:22px;border-radius:50%;border:1.5px solid rgba(255,255,255,.5);background:rgba(255,255,255,.12);color:#fff;font-weight:700;font-size:.8rem;cursor:pointer;flex-shrink:0;line-height:1;display:flex;align-items:center;justify-content:center;font-family:inherit;transition:background .15s}
.pmw-help-btn:hover{background:rgba(255,255,255,.28)}

/* ── Modale mode d'emploi ── */
.pmw-help-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:10000;align-items:center;justify-content:center;padding:16px}
.pmw-help-overlay.open{display:flex}
.pmw-help-modal{background:#fff;border-radius:14px;max-width:520px;width:100%;max-height:88vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 18px 50px rgba(0,0,0,.3)}
.pmw-help-head{background:#0e3d6b;color:#fff;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;font-weight:700;font-size:.95rem;flex-shrink:0}
.pmw-help-close{background:rgba(255,255,255,.15);border:none;color:#fff;width:28px;height:28px;border-radius:7px;cursor:pointer;font-size:.9rem;font-family:inherit}
.pmw-help-close:hover{background:rgba(255,255,255,.3)}
.pmw-help-body{padding:20px;overflow-y:auto}
.pmw-help-block{margin-bottom:18px}
.pmw-help-q{font-weight:700;color:#1673B2;font-size:.86rem;margin-bottom:5px}
.pmw-help-body p{font-size:.82rem;color:#444;line-height:1.6;margin:0 0 6px}
.pmw-help-badge{display:inline-block;padding:2px 9px;border-radius:12px;font-size:.68rem;font-weight:700;margin:2px 0}
.pmw-help-on{background:#e8f8f0;color:#1a7a4a;border:1.5px solid #b2f0d0}
.pmw-help-off{background:#fde8e8;color:#c0392b;border:1.5px solid #fca5a5}
.pmw-help-note{background:#f7fafd;border-left:3px solid #1673B2;border-radius:7px;padding:11px 14px;font-size:.76rem;color:#666;line-height:1.5;font-style:italic}
.pmw-help-foot{padding:14px 20px;border-top:1px solid #f0f4f8;flex-shrink:0}
.pmw-help-ok{width:100%;padding:12px;background:#1673B2;color:#fff;border:none;border-radius:8px;font-weight:700;font-size:.86rem;cursor:pointer;font-family:inherit}
.pmw-help-ok:hover{background:#0e3d6b}
.pmw-verdict-ok{background:#e8f8f0;border:1.5px solid #b2f0d0;color:#1a5c35}
.pmw-verdict-warn{background:#fff8ee;border:1.5px solid #ffd080;color:#7a4400}
.pmw-verdict-danger{background:#fff0f0;border:1.5px solid #fca5a5;color:#7a1a1a}
/* ── Détails bruts ── */
.pmw-details{border:1px solid #f0f4f8;border-radius:7px;overflow:hidden}
.pmw-details summary{font-size:.7rem;color:#aaa;cursor:pointer;padding:7px 12px;user-select:none;background:#fafbfc}
.pmw-details summary:hover{color:#1673B2}
.pmw-raw-lbl{font-size:.62rem;color:#aaa;font-weight:700;text-transform:uppercase;letter-spacing:.05em;padding:6px 12px 2px}
.pmw-details code{display:block;background:#1e2a3a;color:#7ecfff;padding:8px 12px;font-size:.73rem;word-break:break-all;line-height:1.6;font-family:"Courier New",monospace}

/* ── Pied ── */
.pmw-links{display:flex;gap:12px;flex-wrap:wrap;padding-top:4px}
.pmw-links a{font-size:.7rem;color:#1673B2;text-decoration:none}
.pmw-links a:hover{text-decoration:underline}
/* Bouton "Porter plainte" permanent */
.pmw-report-wrap{padding-top:6px}
.pmw-report-btn{width:100%;padding:14px 16px;border:none;border-radius:10px;background:#FF9900;color:#fff;font-size:.88rem;font-weight:700;font-family:inherit;cursor:pointer;text-align:center;transition:all .18s;line-height:1.4;box-shadow:0 2px 8px rgba(255,153,0,.3)}
.pmw-report-btn:hover{background:#e08800;box-shadow:0 3px 12px rgba(255,153,0,.45)}
.pmw-report-btn.alert{background:#e53e3e;box-shadow:0 2px 8px rgba(229,62,62,.3)}
.pmw-report-btn.alert:hover{background:#c0392b}
/* Modale sélection piste */
.pmw-rwy-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9000;align-items:center;justify-content:center}
.pmw-rwy-overlay.open{display:flex}
.pmw-rwy-modal{background:#fff;border-radius:16px;padding:30px 28px;max-width:420px;width:94%;box-shadow:0 8px 40px rgba(0,0,0,.25);text-align:center}
.pmw-rwy-title{font-size:1.1rem;font-weight:800;color:#0e3d6b;margin-bottom:6px}
.pmw-rwy-sub{font-size:.8rem;color:#888;margin-bottom:20px;line-height:1.5}
.pmw-rwy-btns{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:18px}
.pmw-rwy-btn{display:flex;flex-direction:column;align-items:center;gap:5px;padding:18px 12px;border:2px solid #dde4ed;border-radius:12px;background:#f8fafc;cursor:pointer;font-family:inherit;transition:all .15s}
.pmw-rwy-btn:hover{border-color:#1673B2;background:#eef5fc}
.pmw-rwy-icon{font-size:1.6rem;line-height:1;color:#0e3d6b}
.pmw-rwy-btn strong{font-size:.95rem;color:#0e3d6b}
.pmw-rwy-btn span{font-size:.72rem;color:#888}
.pmw-rwy-batc{display:block;font-size:.78rem;color:#1673B2;text-decoration:none;margin-bottom:14px}
.pmw-rwy-batc:hover{text-decoration:underline}
.pmw-rwy-details{margin-bottom:14px;text-align:left}
.pmw-rwy-details summary{font-size:.78rem;color:#1673B2;cursor:pointer;padding:7px 10px;border-radius:6px;background:#f0f9ff;border:1px solid #bdd5f5;list-style:none;display:flex;align-items:center;gap:6px;user-select:none}
.pmw-rwy-details summary::-webkit-details-marker{display:none}
.pmw-rwy-details summary::before{content:'▶';font-size:.6rem;color:#1673B2;transition:transform .2s}
.pmw-rwy-details[open] summary::before{transform:rotate(90deg)}
.pmw-rwy-details-body{padding:10px 12px;font-size:.78rem;color:#555;line-height:1.6;background:#f8fafc;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 6px 6px}
.pmw-rwy-details-body a{display:inline-block;margin-top:8px;color:#1673B2;font-weight:600;text-decoration:none}
.pmw-rwy-details-body a:hover{text-decoration:underline}
.pmw-rwy-cancel{background:none;border:none;color:#aaa;font-size:.78rem;cursor:pointer;font-family:inherit;padding:4px}
.pmw-disclaimer{font-size:.62rem;color:#bbb;line-height:1.6;font-style:italic;margin:0}
</style>

<script>
(function(){
var REFRESH = 10*60*1000;
var lastData = null;   // dernières données météo
window._pmwData = function() { return {data: lastData, rwy: batcRwy}; };
var batcRwy  = null;   // piste saisie depuis BATC
var RWY_ORDER = ['25R','25L','07L','07R','01','19'];
var RWY_QFU   = {'25R':246,'25L':251,'07L':66,'07R':71,'01':14,'19':194};
var RWY_PREF  = {'25R':true,'25L':true};

/* ── Utilitaires ── */
function dirText(d){
  if(!d||d===0) return 'Variable';
  return ['N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSO','SO','OSO','O','ONO','NO','NNO'][Math.round(d/22.5)%16];
}
function rwyClass(r){
  if(r.indexOf('25')>-1) return 'r25';
  if(r.indexOf('07')>-1) return 'r07';
  if(r.indexOf('19')>-1) return 'r19';
  return 'r01';
}
// Mappe un tableau de pistes vers la config BATC complète la plus proche
function rwysToConfig(rwys, aip) {
  if(!rwys || !rwys.length) return '—';
  var CONFIGS = [
    {label:'25R/25L', rwys:['25R','25L']},
    {label:'19/25R',  rwys:['19','25R']},
    {label:'07L/07R', rwys:['07L','07R']},
    {label:'01/07R',  rwys:['01','07R']},
    {label:'01/01',   rwys:['01']},
    {label:'19/19',   rwys:['19']},
    {label:'25R',     rwys:['25R']},
    {label:'25L',     rwys:['25L']},
  ];
  // Chercher la config exacte d'abord
  var exact = CONFIGS.find(function(c){
    return c.rwys.length === rwys.length &&
           c.rwys.every(function(r){ return rwys.indexOf(r)>-1; });
  });
  if(exact) return exact.label;
  // Sinon chercher la config englobante
  var match = CONFIGS.find(function(c){
    return rwys.every(function(r){ return c.rwys.indexOf(r)>-1; });
  });
  return match ? match.label : rwys.join('/');
}

function rwyCssClass(r){
  if(r.indexOf('25')>-1) return 'pmw-rwy-25';
  if(r.indexOf('07')>-1) return 'pmw-rwy-07';
  if(r.indexOf('19')>-1) return 'pmw-rwy-19';
  return 'pmw-rwy-01';
}
function valCss(v,warn,danger){
  return v>=danger?'pmw-danger':v>=warn?'pmw-warn':'pmw-ok';
}
function fmtKt(v){
  return v===0?'<span class="pmw-grey">—</span>':'<span class="pmw-val '+valCss(v,4,7)+'">'+v+' kt</span>';
}
function fmtXw(v){
  return v===0?'<span class="pmw-grey">—</span>':'<span class="pmw-val '+(v>=20?'pmw-danger':v>=12?'pmw-warn':'pmw-grey')+'">'+v+' kt</span>';
}
function utcStr(ts){
  var d=new Date(ts*1000);
  return d.toLocaleTimeString('fr-BE',{hour:'2-digit',minute:'2-digit',timeZone:'UTC'})+' UTC';
}
function localStr(ts){
  var d=new Date(ts*1000);
  return d.toLocaleTimeString('fr-BE',{hour:'2-digit',minute:'2-digit',timeZone:'Europe/Brussels'})
        +' '+d.toLocaleDateString('fr-BE',{weekday:'short',day:'numeric',timeZone:'Europe/Brussels'});
}

/* ── Rendu vent actuel ── */
function renderWind(d){
  // Heure
  try{
    var ts=new Date(d.obs_time);
    var utcH = ts.toLocaleTimeString('fr-BE',{hour:'2-digit',minute:'2-digit',timeZone:'UTC'});
    var locH = ts.toLocaleTimeString('fr-BE',{hour:'2-digit',minute:'2-digit',timeZone:'Europe/Brussels'});
    document.getElementById('pmw-upd').textContent=
      'METAR '+locH+' ('+utcH+' UTC)';
  }catch(e){}

  // Flèche
  var rot = (d.variable||!d.wdir) ? 0 : d.wdir;
  document.getElementById('pmw-arrow-g').setAttribute('transform','rotate('+rot+' 50 50)');

  // Valeurs
  document.getElementById('pmw-wdir').textContent =
    d.variable ? 'Variable' : d.wdir+'° '+dirText(d.wdir);
  document.getElementById('pmw-wspd').textContent = (d.wspd||0)+' kt (moy)';
  if(d.wgst || d.wgst_irm){
    var gEl = document.getElementById('pmw-wgst');
    var parts = [];
    if(d.wgst_metar) parts.push('METAR: '+d.wgst_metar+' kt');
    if(d.wgst_irm)   parts.push('IRM: '+d.wgst_irm+' kt');
    if(d.wgst && !d.wgst_metar && !d.wgst_irm) parts.push(d.wgst+' kt');
    gEl.innerHTML = '💨 Rafales — ' + parts.join(' · ');
    // Note si IRM révèle rafale non publiée dans METAR
    if(d.wgst_irm && !d.wgst_metar) {
      gEl.innerHTML += ' <span style="font-size:.65rem;background:#fff8ee;color:#c97200;padding:1px 5px;border-radius:3px">non publiée METAR</span>';
    }
  }
  if(d.wgst && d.wspd_eff > d.wspd){
    var effLabel = '→ Eff. PRS : '+d.wspd_eff+' kt'+(d.wgst_irm&&!d.wgst_metar?' (IRM)':'');
    document.getElementById('pmw-weff').textContent = effLabel;
  }

  // Badge PRS
  var badge = document.getElementById('pmw-prs-badge');
  // Secteur nord : vent entre 330° et 030° → pistes 25 naturellement favorisées
  var wdir = d.wdir || 0;
  var isNordSector = !d.variable && (wdir >= 330 || wdir <= 30);

  if(d.prs_active){
    badge.className='pmw-prs-badge pmw-prs-on'; badge.textContent='PRS actif';
  if(window.pmwUpdateReportBtn) pmwUpdateReportBtn(false);
    var subTxt = 'Pistes préférentielles applicables';
    if(isNordSector) subTxt += ' — vent de face optimal (' + wdir + '° ' + dirText(wdir) + ')';
    document.getElementById('pmw-prs-sub').textContent = subTxt;
    document.getElementById('pmw-alt-configs').style.display='none';
  }else{
    badge.className='pmw-prs-badge pmw-prs-off'; badge.textContent='HORS PRS';
  if(window.pmwUpdateReportBtn) pmwUpdateReportBtn(true);
    var subTxt2 = 'Pistes alternatives possibles :';
    if(isNordSector) subTxt2 = '⚠ Vent ' + wdir + '° ' + dirText(wdir) + ' — secteur favorable 25, mais seuils dépassés';
    document.getElementById('pmw-prs-sub').textContent = subTxt2;
    renderAltConfigs(d);
  }

  // Pistes probables — masqué, remplacé par pmw-alt-configs
  var rwyNow = document.getElementById('pmw-rwy-now');
  if(rwyNow) rwyNow.innerHTML = '';

  // Raison
  var rEl=document.getElementById('pmw-reason');
  rEl.textContent=d.reason||'';
  rEl.className='pmw-reason'+(d.alert?' alert':'');

  // Planning PRS en cours

  // Exceptions PRS
  var excEl=document.getElementById('pmw-exc');
  if(d.prs_exceptions&&d.prs_exceptions.length){
    var ul=document.getElementById('pmw-exc-list'); ul.innerHTML='';
    d.prs_exceptions.forEach(function(e){
      var li=document.createElement('li'); li.textContent=e; ul.appendChild(li);
    });
    excEl.style.display='';
  }else excEl.style.display='none';
}

/* ── Configs alternatives classées par headwind ── */
function renderAltConfigs(d) {
  var el = document.getElementById('pmw-alt-configs');
  if(!el) return;
  var comps = d.components || {};

  var ALT_CONFIGS = [
    {label:'01/01',   rwys:['01']},
    {label:'01/07R',  rwys:['01','07R']},
    {label:'07L/07R', rwys:['07L','07R']},
  ];

  function configUsable(rwys) {
    return rwys.every(function(rwy) {
      var c = comps[rwy]; if(!c) return true;
      var tw_m = c.tw || 0;
      var tw_g = (c.tw_g !== null && c.tw_g !== undefined) ? c.tw_g : tw_m;
      return tw_m <= 7 && tw_g <= 10;
    });
  }

  function configHeadwind(rwys) {
    var total = 0, count = 0;
    rwys.forEach(function(rwy) {
      var c = comps[rwy]; if(!c) return;
      total += (c.hw || 0); count++;
    });
    return count > 0 ? total / count : 0;
  }

  var usable = ALT_CONFIGS.filter(function(cfg) {
    return configUsable(cfg.rwys);
  }).sort(function(a, b) {
    return configHeadwind(b.rwys) - configHeadwind(a.rwys);
  });

  if(usable.length === 0) { el.style.display='none'; return; }

  var html = '<div class="pmw-alt-list">';
  usable.forEach(function(cfg, i) {
    var hw = configHeadwind(cfg.rwys);
    var cls = 'pmw-alt-best';
    var rank = i === 0 ? '★ ' : '↳ ';
    html += '<div class="pmw-alt-item '+cls+'">'
          + rank + cfg.label
          + ' <span class="pmw-alt-hw">face '+hw.toFixed(1)+'kt</span>'
          + '</div>';
  });
  html += '</div>';
  el.innerHTML = html;
  el.style.display = 'block';
}

/* ── Tableau composantes ── */
function renderTable(comps, activeRunways, seuils_tw_moy, seuils_tw_gust){
  var tbody=document.getElementById('pmw-rwy-tbody');
  if(!tbody) return;
  tbody.innerHTML='';
  // Seuils par défaut AIP actuel si non fournis
  seuils_tw_moy  = seuils_tw_moy  || 7;
  seuils_tw_gust = seuils_tw_gust || 7;

  RWY_ORDER.forEach(function(rwy){
    var c=comps[rwy]; if(!c) return;
    var hf=c.hf||0, tw=c.tw||0, xw=c.xw||0;
    var hf_g=c.hf_g, tw_g=c.tw_g, xw_g=c.xw_g;

    var tr=document.createElement('tr');

    // Bordure basée sur les conditions météo réelles :
    // Vert = piste utilisable (vent arrière moyen ≤ seuil ET rafale ≤ seuil)
    // Rouge = piste inutilisable (vent arrière dépasse un seuil)
    var twMoy  = tw  || 0;
    var twGust = tw_g !== null ? (tw_g || 0) : 0;
    var twEff  = Math.max(twMoy, twGust);
    var xwEff  = Math.max(xw, xw_g||0);

    var isUsable = (twMoy <= seuils_tw_moy) && (twGust <= seuils_tw_gust);
    if(isUsable) tr.classList.add('pmw-tr-pref');   // vert
    else          tr.classList.add('pmw-tr-alert');  // rouge

    // Formatage : "moy" ou "moy / rafales" si rafales présentes
    // Vent de face — toujours afficher moy et rafales
    var hfHtml = '<span class="pmw-val pmw-ok">↗ '+hf+' kt</span>'
               + (hf_g!==null
                  ? ' <span class="pmw-gust-val">/ '+(hf_g>0?hf_g:'—')+'💨</span>'
                  : ' <span class="pmw-gust-na">/ —</span>');
    if(hf===0) hfHtml = '<span class="pmw-grey">—</span>'
              + (hf_g!==null ? ' <span class="pmw-gust-na">/ —</span>' : ' <span class="pmw-gust-na">/ —</span>');

    // Vent arrière — toujours afficher moy ET rafales
    var twMoyStr = tw > 0
      ? '↙ <span class="pmw-val '+valCss(tw,4,7)+'">'+tw+' kt'+(tw>=7?' ⚠':'')+'</span>'
      : '<span class="pmw-grey">—</span>';
    var twGustStr = tw_g !== null
      ? (tw_g > 0
          ? '/ <span class="pmw-gust-val '+valCss(tw_g,4,7)+'">'+tw_g+'💨'+(tw_g>=7?' ⚠':'')+'</span>'
          : '<span class="pmw-gust-na">/ —</span>')
      : '<span class="pmw-gust-na">/ —</span>';
    var twHtml = twMoyStr + ' ' + twGustStr;

    // Vent latéral — toujours afficher moy ET rafales
    var xwMoyStr = xw > 0
      ? '↔ <span class="pmw-val '+(xw>=20?'pmw-danger':xw>=12?'pmw-warn':'pmw-grey')+'">'+xw+' kt'+(xw>=20?' ⚠':'')+'</span>'
      : '<span class="pmw-grey">—</span>';
    var xwGustStr = xw_g !== null
      ? (xw_g > 0
          ? '/ <span class="pmw-gust-val '+(xw_g>=20?'pmw-danger':xw_g>=12?'pmw-warn':'pmw-grey')+'">'+xw_g+'💨'+(xw_g>=20?' ⚠':'')+'</span>'
          : '<span class="pmw-gust-na">/ —</span>')
      : '<span class="pmw-gust-na">/ —</span>';
    var xwHtml = xwMoyStr + ' ' + xwGustStr;

    // PRS : icône pour toutes les pistes impliquées dans une config PRS
    // 25R, 25L : config préférentielle
    // 19 : config 19/25R et 19/19 (même seuil vent arrière AIP 2013)
    var PRS_RWYS = {'25R': true, '25L': true, '19': true};
    var prsHtml = '';
    if(PRS_RWYS[rwy]) {
      prsHtml = isUsable
        ? '<span class="pmw-prs-ok">✓</span>'
        : '<span class="pmw-prs-ko">✕</span>';
    }

    tr.innerHTML=
      '<td><span class="pmw-rwy-name">'+rwy+'</span><span class="pmw-qfu">'+RWY_QFU[rwy]+'°M</span></td>'
      +'<td>'+hfHtml+'</td>'
      +'<td>'+twHtml+'</td>'
      +'<td>'+xwHtml+'</td>'
      +'<td>'+prsHtml+'</td>';
    tbody.appendChild(tr);
  });
}

/* ── Prévisions TAF ── */
function renderTaf(forecast, rawTaf){
  document.getElementById('pmw-raw-taf').textContent=rawTaf||'—';

  var grid=document.getElementById('pmw-taf-grid');
  if(!forecast||!forecast.length){
    grid.innerHTML='<div class="pmw-loading">Prévisions non disponibles</div>';
    return;
  }

  var now=Math.floor(Date.now()/1000);
  grid.innerHTML='';

  forecast.forEach(function(f){
    var isNow = (f.time_from<=now && (!f.time_to||f.time_to>now));
    var wrap=document.createElement('div');
    wrap.className='pmw-taf-row'+(f.alert?' pmw-taf-alert':'')+(isNow?' pmw-taf-now':'');

    // Heure locale Bruxelles
    var d_from = new Date(f.time_from*1000);
    var locH = d_from.toLocaleTimeString('fr-BE',{hour:'2-digit',minute:'2-digit',timeZone:'Europe/Brussels'});
    var locD = d_from.toLocaleDateString('fr-BE',{weekday:'short',day:'numeric',month:'short',timeZone:'Europe/Brussels'});
    var nowLabel = isNow ? '<span class="pmw-taf-now-lbl">EN COURS</span>' : '';
    var timeHtml = '<div class="pmw-taf-time">'
      + nowLabel
      + '<span class="pmw-taf-hlocal">'+locH+'</span>'
      + '<span class="pmw-taf-utc">'+locD+'</span>'
      + '</div>';

    // Vent — lisible
    var wdirTxt = f.variable ? 'Vent variable' : ('Vent '+f.wdir+'° '+dirText(f.wdir));
    var spdTxt  = f.wspd + ' kt';
    var gstTxt  = f.wgst ? ' · rafales '+f.wgst+' kt' : '';
    var windHtml = '<div class="pmw-taf-wind">'
      + '<b>'+spdTxt+gstTxt+'</b>'
      + '<br><span style="font-size:.7rem;color:#666">'+wdirTxt+'</span>'
      + '</div>';

    // ── Classement des configs selon composantes ──
    var ALL_CONFIGS = [
      {label:'25R/25L', rwys:['25R','25L']},
      {label:'19/25R',  rwys:['19','25R']},
      {label:'07L/07R', rwys:['07L','07R']},
      {label:'01/07R',  rwys:['01','07R']},
      {label:'01/01',   rwys:['01']},
      {label:'19/19',   rwys:['19']},
    ];
    var comps = f.components || {};

    // Piste 01 : possible uniquement si le vent vient du secteur 335°–040°
    // (axe d'atterrissage de la 01, cap ~014°). Hors de ce secteur, atterrir en 01
    // signifierait un vent arrière ou de travers inacceptable.
    function dir01Possible(wdir, variable) {
      if (variable) return false; // vent variable → pas d'atterrissage 01 justifié
      return (wdir >= 335 || wdir <= 40);
    }

    // Une config est impossible si vent arrière moyen > 7kt OU si PRS actif et config non-PRS
    function cfgImpossible(rwys, label) {
      if (f.prs_active && label !== '25R/25L' && label !== '19/25R') return true;
      // Contrainte directionnelle 01
      if (rwys.indexOf('01') !== -1 && !dir01Possible(f.wdir||0, f.variable)) return true;
      return rwys.some(function(rwy){
        var c = comps[rwy]; if(!c) return false;
        return (c.tw||0) > 7;
      });
    }
    // Headwind moyen de la config
    function cfgHw(rwys) {
      var t=0,n=0;
      rwys.forEach(function(rwy){ var c=comps[rwy]; if(c){ t+=(c.hw||0); n++; } });
      return n>0 ? t/n : 0;
    }

    var impossible=[], possible=[];
    ALL_CONFIGS.forEach(function(cfg){
      if(cfgImpossible(cfg.rwys, cfg.label)) impossible.push(cfg.label);
      else possible.push({label:cfg.label, hw:cfgHw(cfg.rwys)});
    });
    // Trier les possibles par headwind décroissant
    possible.sort(function(a,b){ return b.hw - a.hw; });

    // Verdict PRS principal + note secteur nord
    var fWdir = f.wdir || 0;
    var fNord = !f.variable && (fWdir >= 330 || fWdir <= 30);
    var prsHtml;
    if(f.prs_active){
      var nordNote = fNord ? ' <span style="font-size:.6rem;opacity:.8">· face</span>' : '';
      prsHtml = '<div class="pmw-taf-prs pmw-taf-prs-ok">✓ Pistes 25'+nordNote+'<br><span>PRS possible</span></div>';
    } else {
      var bestAlt = possible.filter(function(p){ return p.label!=='25R/25L'&&p.label!=='19/25R'; });
      var altTxt = bestAlt.length ? bestAlt[0].label : '—';
      var nordWarn = fNord ? '<span style="font-size:.6rem;color:#c0392b;display:block">⚠ Secteur nord, seuils dépassés</span>' : '';
      prsHtml = '<div class="pmw-taf-prs pmw-taf-prs-ko">↗ Alternatives<br><span>'+altTxt+'</span>'+nordWarn+'</div>';
    }

    var main=document.createElement('div');
    main.className='pmw-taf-main';
    main.innerHTML=timeHtml+windHtml+prsHtml;
    wrap.appendChild(main);

    // ── Barre de configs impossible/favorable/moins favorable ──
    var barHtml = '<div class="pmw-taf-cfgbar">';
    // Impossibles
    if(impossible.length) {
      barHtml += '<div class="pmw-taf-cfggroup">'
        + '<span class="pmw-taf-cfglbl pmw-cfg-lbl-ko">✗ Impossible</span>'
        + impossible.map(function(l){ return '<span class="pmw-taf-cfgtag pmw-cfg-ko">'+l+'</span>'; }).join('')
        + '</div>';
    }
    // Possibles — si PRS actif, seules les configs PRS sont affichées
    var possibleAffiche = possible;
    if (f.prs_active) {
      possibleAffiche = possible.filter(function(p) {
        return p.label === '25R/25L' || p.label === '19/25R';
      });
    }
    if(possibleAffiche.length) {
      barHtml += '<div class="pmw-taf-cfggroup">'
        + '<span class="pmw-taf-cfglbl pmw-cfg-lbl-ok">✓ Possible</span>'
        + possibleAffiche.map(function(p,i){
            var cls = i===0 ? 'pmw-cfg-best' : 'pmw-cfg-ok';
            return '<span class="pmw-taf-cfgtag '+cls+'" title="face '+p.hw.toFixed(1)+'kt">'+p.label+'</span>';
          }).join('')
        + '</div>';
    }
    barHtml += '</div>';

    var barEl=document.createElement('div');
    barEl.innerHTML=barHtml;
    wrap.appendChild(barEl.firstChild);

    grid.appendChild(wrap);
  });
}

/* ── Comparaison 3 colonnes AIP 2013 / AIP actuel / BATC ── */
function rwyBadge(r, small) {
  var cls = rwyCssClass(r);
  var sz = small ? 'font-size:.85rem;padding:3px 8px' : 'font-size:.95rem;padding:4px 11px';
  return '<span class="pmw-rwy '+cls+'" style="'+sz+'">'+r+'</span>';
}

function render3Col(d) {
  // ── Planning commun aux deux AIP ──
  var planBox = document.getElementById('pmw-planning-box');
  var planDet = document.getElementById('pmw-planning-detail');
  if (planBox && planDet && d.aip_planning) {
    var pl = d.aip_planning;
    planBox.style.display = '';
    var _notes = (pl.notes && pl.notes.length)
      ? pl.notes.map(function(n){return '<span class="pmw-planning-note-txt">ℹ '+n+'</span>';}).join('')
      : '';
    planDet.innerHTML =
      '<span class="pmw-planning-dep">✈ DEP : '+pl.label_dep+'</span>'
      +'<span class="pmw-planning-arr">↘ ARR : '+pl.label_arr+'</span>'
      +'<span class="pmw-planning-plage">'+pl.jour+' · '+pl.plage+' (heure locale)</span>'
      +_notes;
  }
  // ── Fonction rendu colonne AIP avec détail visuel ──
  function renderAipCol(badgeId, rwysId, reasonId, prsData, seuils_tw_moy, seuils_tw_gust, seuils_xw_moy, seuils_xw_gust, dataObj) {
    var d = dataObj || {};
    var tw_moy  = prsData.tw_25_moy  != null ? prsData.tw_25_moy  : (prsData.tw||0);
    var tw_gust = prsData.tw_25_gust != null ? prsData.tw_25_gust : null;
    var xw_moy  = prsData.xw_25_moy  != null ? prsData.xw_25_moy  : (prsData.xw||0);
    var xw_gust = prsData.xw_25_gust != null ? prsData.xw_25_gust : null;

    // Badge PRS
    var badge = document.getElementById(badgeId);
    if(badge){
      badge.className = 'pmw-cmp3-badge ' + (prsData.prs_active ? 'pmw-cmp3-prs-on' : 'pmw-cmp3-prs-off');
      badge.textContent = prsData.prs_active ? 'PRS actif' : 'HORS PRS';
    }

    // Badges des 6 configurations possibles — vert si possible, rouge si impossible
    var rwys = document.getElementById(rwysId);
    if(rwys){
      var comps = (d && d.components) ? d.components : {};
      var prs_active_local = prsData.prs_active;

      // Les 6 configurations BATC
      var CONFIGS = [
        {label:'25R/25L', rwys:['25R','25L']},
        {label:'19/25R',  rwys:['19','25R']},
        {label:'07L/07R', rwys:['07L','07R']},
        {label:'01/07R',  rwys:['01','07R']},
        {label:'01/01',   rwys:['01']},
        {label:'19/19',   rwys:['19']},
      ];

      // Une config est possible si AUCUNE de ses pistes n'a de vent arrière > seuil
      // Pour AIP 2013 : seuil 10 kt rafale arrière, pour AIP actuel : 7 kt rafale arrière
      function configOk(rwys_list, label) {
        // Si PRS actif, seules les configs PRS sont utilisables par règlement
        if (prs_active_local && label !== '25R/25L' && label !== '19/25R') return false;
        return rwys_list.every(function(rwy) {
          var c = comps[rwy];
          if(!c) return true;
          var tw = Math.max(c.tw||0, c.tw_g||0);
          return tw <= seuils_tw_gust;
        });
      }

      var html = '';
      CONFIGS.forEach(function(cfg) {
        var ok = configOk(cfg.rwys, cfg.label);
        var cls = ok ? 'pmw-cb-ok' : 'pmw-cb-ko';
        html += '<span class="pmw-cfg-badge '+cls+'">'+cfg.label+'</span>';
      });
      rwys.innerHTML = '<div class="pmw-cfg-grid">'+html+'</div>';
    }

    // Détail des conditions ✓/✗
    var reason = document.getElementById(reasonId);
    if(reason){
      function crit(label, val, seuil, ok) {
        var cls = ok ? 'pmw-crit-ok' : 'pmw-crit-ko';
        var icon = ok ? '✓' : '✗';
        return '<div class="pmw-crit '+cls+'"><span class="pmw-crit-icon">'+icon+'</span>'
          +'<span class="pmw-crit-lbl">'+label+'</span>'
          +'<span class="pmw-crit-val">'+parseFloat(val).toFixed(1)+' kt</span>'
          +'<span class="pmw-crit-seuil">seuil '+seuil+' kt</span></div>';
      }
      var html2 = '';
      html2 += crit('Arrière moy.', tw_moy, seuils_tw_moy, tw_moy <= seuils_tw_moy);
      if(tw_gust !== null)
        html2 += crit('Arrière rafale', tw_gust, seuils_tw_gust, tw_gust <= seuils_tw_gust);
      html2 += crit('Latéral moy.', xw_moy, seuils_xw_moy, xw_moy <= seuils_xw_moy);
      if(xw_gust !== null)
        html2 += crit('Latéral rafale', xw_gust, seuils_xw_gust, xw_gust <= seuils_xw_gust);
      reason.innerHTML = html2;
    }
  }

  // ── AIP 2013 (moyen > 7 kt OU rafale > 12 kt) ──
  var a = d.aip2013 || {};
  renderAipCol('pmw-2013-badge','pmw-2013-rwys','pmw-2013-reason', a, 7, 10, 15, 20, d);

  // ── Note seuil pratique ──
  var pratEl = document.getElementById('pmw-pratique-note');
  if (pratEl && d.aip_pratique && d.aip2013) {
    if (!d.aip_pratique.prs_active && d.aip2013.prs_active) {
      pratEl.innerHTML = '<div style="margin-top:4px;font-size:.67rem;background:#fff8ee;border:1px solid #ffd080;border-radius:5px;padding:4px 8px;color:#c97200">'
        + '⚡ Seuil pratique (6.5 kt) atteint — skeyes bascule généralement ici'
        + '</div>';
    } else { pratEl.innerHTML = ''; }
  }

  // ── AIP actuel (moyen > 7 kt OU rafale > 7 kt, latéral > 20 kt) ──
  var n = d.aip_now || {};
  renderAipCol('pmw-now-badge','pmw-now-rwys','pmw-now-reason', n, 7, 7, 20, 20, d);
  // ── BATC (saisie) ──
  renderBatc(d);
}

function renderBatc(d) {
  var el = document.getElementById('pmw-batc-rwys');
  if(!el) return;
  if(!batcRwy){
    el.innerHTML = '<span style="color:#bbb;font-size:.75rem">Non saisi</span>';
    document.getElementById('pmw-verdict').style.display = 'none';
    return;
  }
  el.innerHTML = rwyBadge(batcRwy, true);

  // Verdict : vérifier chaque piste de la config BATC individuellement
  var a = d.aip2013 || {};
  var comps = d.components || {};

  // Mapping config → pistes
  var CFG_RWYS = {'25R/25L':['25R','25L'],'19/25R':['19','25R'],'07L/07R':['07L','07R'],'01/07R':['01','07R'],'01/01':['01'],'19/19':['19']};
  var batcPistes = CFG_RWYS[batcRwy] || [];

  // Une piste est OK si vent arrière moyen ≤ seuil ET rafale ≤ seuil max
  function pisteOk(rwy, seuilMoy, seuilGust) {
    var c = comps[rwy]; if(!c) return true;
    var tw_m = c.tw  || 0;
    var tw_g = (c.tw_g !== null && c.tw_g !== undefined) ? c.tw_g : tw_m;
    return tw_m <= seuilMoy && tw_g <= seuilGust;
  }

  var prsActif2013 = d.aip2013 && d.aip2013.prs_active;
  var prsActifNow  = d.aip_pratique && d.aip_pratique.prs_active;
  var configEstPRS = (batcRwy === '25R/25L' || batcRwy === '19/25R');

  // Contrainte directionnelle : la piste 01 n'est justifiée que si le vent
  // vient du secteur 335°–040° (axe d'atterrissage de la 01). Sinon, atterrir
  // en 01 implique un vent arrière/de travers inacceptable.
  var wdir01 = d.wdir || 0;
  var dir01OK = !d.variable && (wdir01 >= 335 || wdir01 <= 40);
  var configA01 = batcPistes.indexOf('01') !== -1;
  var violation01 = configA01 && !dir01OK;

  var ok2013 = batcPistes.length > 0
    && batcPistes.every(function(r){ return pisteOk(r, 7, 10); })
    && (!prsActif2013 || configEstPRS)
    && !violation01;
  var okNow  = batcPistes.length > 0
    && batcPistes.every(function(r){ return pisteOk(r, 7, 7); })
    && (!prsActifNow || configEstPRS)
    && !violation01;
  var prsViolation2013 = prsActif2013 && !configEstPRS;
  var prsViolationNow  = prsActifNow  && !configEstPRS;

  // Rafales décisives (info complémentaire)
  var comps25 = comps['25R'] || {};
  var tw_moy  = comps25.tw  || 0;
  var tw_gust = (comps25.tw_g !== null && comps25.tw_g !== undefined) ? comps25.tw_g : null;
  var rafale_decisive_tw = tw_gust !== null && tw_gust > 7 && tw_moy <= 7;

  var cls, txt;

  // ── Cas prioritaire : VIOLATION DIRECTIONNELLE de la piste 01 ──
  // La 01 est utilisée alors que le vent n'est pas dans son secteur (335°–040°).
  if (violation01) {
    cls = 'pmw-verdict-danger';
    var dirInfo = d.variable
      ? 'le vent est variable (aucune direction dominante)'
      : 'le vent vient du ' + wdir01 + '° (' + (typeof dirText==='function'?dirText(wdir01):'') + ')';
    txt = '⚠ PISTE 01 INJUSTIFIÉE — La configuration ' + batcRwy + ' utilise la piste 01, '
        + 'mais celle-ci n\'est justifiée que si le vent vient du secteur 335°–040° (son axe d\'atterrissage). '
        + 'Or ' + dirInfo + ', ce qui implique un vent arrière ou de travers. '
        + 'Configuration attendue : ' + ((a.runways||[]).join('/')||'25R/25L') + '.';
  } else if (prsViolation2013 || prsViolationNow) {
    cls = 'pmw-verdict-danger';
    txt = '⚠ VIOLATION DU PRS — Le PRS est actif (les conditions imposent les pistes préférentielles 25), '
        + 'or BATC indique la configuration ' + batcRwy + ' qui n\'est pas conforme. '
        + 'Configuration attendue : ' + ((a.runways||[]).join('/')||'25R/25L') + '.';
    // Compléter avec le détail vent si une piste dépasse aussi un seuil
    var whyPrs = [];
    batcPistes.forEach(function(rwy){
      var c2 = comps[rwy]; if(!c2) return;
      var m = c2.tw||0, g = (c2.tw_g!==null&&c2.tw_g!==undefined)?c2.tw_g:m;
      if(m>7) whyPrs.push('piste '+rwy+' : vent arrière '+m.toFixed(1)+'kt > 7kt');
      else if(g>10) whyPrs.push('piste '+rwy+' : rafale arrière '+g.toFixed(1)+'kt > 10kt');
    });
    if(whyPrs.length) txt += ' De plus : ' + whyPrs.join(', ') + '.';
  } else if (ok2013 && okNow) {
    cls = 'pmw-verdict-ok';
    txt = '✓ Config justifiée — '+batcRwy+' est possible selon les deux AIP dans les conditions actuelles.';
  } else if (!ok2013 && !okNow) {
    cls = 'pmw-verdict-danger';
    // Détail par piste
    var why = [];
    batcPistes.forEach(function(rwy){
      var c2 = comps[rwy]; if(!c2) return;
      var m = c2.tw||0, g = (c2.tw_g!==null&&c2.tw_g!==undefined)?c2.tw_g:m;
      if(m>7) why.push('piste '+rwy+' : vent arrière moyen '+m.toFixed(1)+'kt > 7kt');
      else if(g>10) why.push('piste '+rwy+' : rafale arrière '+g.toFixed(1)+'kt > 10kt');
    });
    txt = '⚠ VIOLATION — '+batcRwy+' est barrée dans les deux AIP. '+why.join(', ')+'.'
        + ' Config recommandée : '+((a.runways||[]).join('/')||'?')+'.';
  } else if (!ok2013 && okNow) {
    cls = 'pmw-verdict-danger';
    var why2 = [];
    batcPistes.forEach(function(rwy){
      var c2 = comps[rwy]; if(!c2) return;
      var m = c2.tw||0, g = (c2.tw_g!==null&&c2.tw_g!==undefined)?c2.tw_g:m;
      if(m>7) why2.push('vent arrière moyen '+m.toFixed(1)+'kt > 7kt');
      else if(g>10) why2.push('rafale arrière '+g.toFixed(1)+'kt > 10kt (seuil AIP 2013)');
    });
    txt = '⚠ VIOLATION AIP 2013 — '+batcRwy+' est barrée selon l\'instruction ministérielle du 17/07/2013'
        + ' ('+why2.join(', ')+') mais tolérée par l\'AIP actuel skeyes.'
        + ' Config légale recommandée : '+((a.runways||[]).join('/')||'?')+'.';
  } else {
    // ok2013 && !okNow — OK selon AIP 2013 mais barré AIP actuel
    cls = 'pmw-verdict-warn';
    txt = '⚡ Écart AIP actuel — '+batcRwy+' est autorisée par l\'AIP 2013 mais barrée selon l\'AIP skeyes actuel.';
  }

  if (rafale_decisive_tw && cls !== 'pmw-verdict-ok') {
    txt += ' ⚠ NOTE : c\'est la rafale ('+tw_gust+'kt) qui est déterminante, pas le vent moyen ('+tw_moy+'kt).';
  }

  var vel = document.getElementById('pmw-verdict');
  // Ajouter bouton plainte si violation
  var mailBtn = '';
  if (cls === 'pmw-verdict-danger') {
    mailBtn = '<button class="pmw-mail-btn" onclick="pmwOpenPlainte()">✉ Générer une plainte avec capture</button>';
  }

  vel.className = 'pmw-verdict ' + cls;
  vel.innerHTML = '<div class="pmw-verdict-txt">' + txt + '</div>' + mailBtn;
  vel.style.display = '';
}

window.setBatc = function(rwy) {
  batcRwy = rwy;
  // Boutons actifs
  document.querySelectorAll('.pmw-batc-btn').forEach(function(b){
    b.classList.toggle('active', rwy && b.textContent.trim()===rwy);
  });
  if(lastData) renderBatc(lastData);
}

function renderCompare(d) {
  // ── Colonne Météo ──
  var meteoRwys = d.runways||[];
  var meteoEl = document.getElementById('pmw-cmp-meteo-rwys');
  meteoEl.innerHTML = meteoRwys.map(function(r){
    return '<span class="pmw-rwy '+rwyCssClass(r)+'" style="font-size:.95rem;padding:3px 10px">'+r+'</span>';
  }).join('');
  document.getElementById('pmw-cmp-meteo-detail').textContent =
    d.prs_active ? 'PRS actif — vent arrière '+(d.components&&d.components['25R']?Math.abs(d.components['25R'].tw):0)+' kt'
                 : 'Hors PRS — '+(d.prs_exceptions||[]).join(', ');

  // ── Colonne BATC ──
  var batcEl = document.getElementById('pmw-cmp-batc-rwys');
  if(!batcRwy){
    batcEl.innerHTML='<span class="pmw-rwy pmw-rwy-load" style="font-size:.8rem">Non saisi</span>';
    document.getElementById('pmw-verdict').style.display='none';
    return;
  }
  batcEl.innerHTML='<span class="pmw-rwy '+rwyCssClass(batcRwy)+'" style="font-size:.95rem;padding:3px 10px">'+batcRwy+'</span>';

  // ── Verdict ──
  var verdictEl = document.getElementById('pmw-verdict');
  var isPref = batcRwy && batcRwy.indexOf('25')>-1;
  var meteoWantsPref = !d.alert;  // true si PRS actif et pistes 25 suggérées
  var meteoMatches = meteoRwys.indexOf(batcRwy) > -1;

  var cls, txt;
  if(meteoMatches){
    // BATC correspond à ce que la météo suggère
    cls = 'pmw-verdict-ok';
    txt = '✓ Cohérent — BATC utilise '+ batcRwy +' comme le calcul météo le suggère.';
    if(isPref && !d.prs_active){
      // BATC utilise les 25 mais le PRS n'est pas applicable !
      cls = 'pmw-verdict-danger';
      txt = '⚠ Anomalie PRS — BATC annonce '+ batcRwy +' (préférentielle) alors que les conditions météo imposent une piste alternative. '
          +'Vent arrière sur pistes 25 : '+(d.tw_25_max||0)+' kt > 7 kt.';
    }
  } else {
    // BATC utilise une piste différente de ce que la météo suggère
    if(!isPref && meteoWantsPref){
      // BATC utilise une piste non-préférentielle sans raison météo
      cls = 'pmw-verdict-warn';
      txt = '💨 Écart — BATC utilise '+ batcRwy +' (non-préférentielle) mais la météo ne justifie pas de sortir du PRS. '
          +'Raison probable : plan de dispersion, travaux ou capacité trafic.';
    } else if(isPref && !meteoWantsPref){
      // BATC garde les 25 malgré conditions hors PRS
      cls = 'pmw-verdict-danger';
      txt = '⚠ Anomalie PRS — BATC maintient '+ batcRwy +' malgré vent arrière '+(d.tw_25_max||0)+' kt > 7 kt. '
          +'Possible : changement de configuration en cours (délai 30 min).';
    } else {
      cls = 'pmw-verdict-warn';
      txt = '💨 Différence — Météo suggère '+ meteoRwys.join('/') +', BATC utilise '+ batcRwy +'.';
    }
  }

  verdictEl.className = 'pmw-verdict '+cls;
  verdictEl.textContent = txt;
  verdictEl.style.display = '';
}

/* ── Affiche le bloc planning à partir d'un objet pl ── */
function renderPlanningBox(pl) {
  if (!pl) return;
  var planBox = document.getElementById('pmw-planning-box');
  var planDet = document.getElementById('pmw-planning-detail');
  if (!planBox || !planDet) return;
  planBox.style.display = '';
  var _n = (pl.notes && pl.notes.length)
    ? pl.notes.map(function(n){return '<span class="pmw-planning-note-txt">ℹ '+n+'</span>';}).join('')
    : '';
  planDet.innerHTML =
    '<span class="pmw-planning-dep">✈ DEP : '+pl.label_dep+'</span>'
    +'<span class="pmw-planning-arr">↘ ARR : '+pl.label_arr+'</span>'
    +'<span class="pmw-planning-plage">'+pl.jour+' · '+pl.plage+' (heure locale)</span>'
    +_n;
}

/* ── Chargement principal ── */
function load(){
  // Fetch parallèle direct du planning : robuste si metar.php tombe en HTTP 500
  fetch('/api/aip_planning.php?_='+Date.now())
    .then(function(r){return r.ok ? r.json() : null;})
    .then(function(pl){ if (pl && !pl.error) renderPlanningBox(pl); })
    .catch(function(){});

  fetch('/api/metar.php?_='+Date.now())
    .then(function(r){return r.json();})
    .then(function(d){
      if(d.error){document.getElementById('pmw-upd').textContent='Erreur: '+d.error;return;}
      renderWind(d);
      if(d.components) renderTable(d.components, d.runways, 7, 10);
      renderTaf(d.forecast, d.taf);
      document.getElementById('pmw-raw-metar').textContent=d.metar||'—';
      // Comparaison
      lastData = d;
      render3Col(d);
    })
    .catch(function(e){document.getElementById('pmw-upd').textContent='Erreur de connexion';});
}

load();
setInterval(load, REFRESH);
})();

// ── Fonctions plainte (globales, hors IIFE) ──────────────────────────────
var pmwCaptureDataUrl = null;
var pmwMailBody = '';

window.pmwOpenPlainte = function(pisteObservee) {
  var _pd = window._pmwData ? window._pmwData() : null;
  if(!_pd || !_pd.data) { alert('Données météo non disponibles, veuillez patienter.'); return; }
  if(!pisteObservee && !_pd.rwy) { alert('Sélectionnez d\'abord une configuration BATC.'); return; }
  var d = _pd.data;
  // ── Tracking clic plainte ──────────────────────────────────────────
  fetch('/api/track_plainte.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({source:'piste_meteo', alert: d.alert||false})
  }).catch(function(){});
  if(_pd.rwy) window._currentBatcRwy = _pd.rwy;
  var overlay = document.getElementById('pmw-plainte-overlay');
  var loadEl  = document.getElementById('pmw-plainte-loading');
  var imgEl   = document.getElementById('pmw-plainte-img');
  overlay.classList.add('open');
  loadEl.style.display = 'block';
  imgEl.style.display  = 'none';

  // ── Texte du mail ──
  var now     = new Date();
  var dateStr = now.toLocaleDateString('fr-BE', {day:'2-digit',month:'2-digit',year:'numeric'});
  var utcTimeStr = now.toLocaleTimeString('fr-BE', {hour:'2-digit',minute:'2-digit',timeZone:'UTC'});
  var locTimeStr = now.toLocaleTimeString('fr-BE', {hour:'2-digit',minute:'2-digit',timeZone:'Europe/Brussels'});
  var timeStr = locTimeStr + ' heure Bruxelles (' + utcTimeStr + ' UTC)';
  var a       = d.aip2013 || {};
  var plan    = d.aip_planning || {};
  var planStr = plan.label_dep ? 'DEP: '+plan.label_dep+' / ARR: '+plan.label_arr+' ('+plan.jour+' · '+plan.plage+')' : 'Non disponible';

  var comps = d.components || {};
  var tw25R = comps['25R'] ? (comps['25R'].tw||0).toFixed(1) : '—';
  var tw25L = comps['25L'] ? (comps['25L'].tw||0).toFixed(1) : '—';
  var xw25R = comps['25R'] ? (comps['25R'].xw||0).toFixed(1) : '—';
  var tw25Rg = comps['25R'] && comps['25R'].tw_g!==null && comps['25R'].tw_g!==undefined ? (comps['25R'].tw_g||0).toFixed(1) : null;

  if (pisteObservee) {
    // ── Texte humble : piste observée par le résident ─────────────
    var pisteLabel = pisteObservee === '07' ? 'piste 07 (07L/07R)' : 'piste 01';
    var obsTime = d.obs_time ? new Date(d.obs_time) : new Date();
    var obsDateStr = obsTime.toLocaleDateString('fr-BE',{day:'2-digit',month:'2-digit',year:'numeric'});
    var obsTimeStr = obsTime.toLocaleTimeString('fr-BE',{hour:'2-digit',minute:'2-digit',timeZone:'UTC'})+' UTC';

    pmwMailBody =
      'Madame, Monsieur,\n\n' +
      'Je me permets de vous contacter afin de vous signaler qu\'en date du '+obsDateStr+
      ' vers '+obsTimeStr+', j\'ai observé un usage de la '+pisteLabel+
      ' à l\'aéroport de Bruxelles-National (EBBR).\n\n' +
      'Les conditions météorologiques au moment de mon observation semblent indiquer' +
      ' que l\'utilisation des pistes préférentielles 25 aurait pu être envisagée :\n\n' +
      '=== CONDITIONS MÉTÉO EBBR ===\n' +
      'METAR EBBR              : '+(d.metar||'—')+'\n' +
      'Date / Heure (METAR)    : '+obsDateStr+' à '+obsTimeStr+'\n' +
      'Vent moyen              : '+(d.wdir||'—')+'° / '+(d.wspd||'—')+' kt\n' +
      'Rafales                 : '+(d.wgst ? d.wgst+' kt' : '—')+(d.wgst_irm?' (IRM: '+d.wgst_irm+' kt)':'')+' \n' +
      'Composante arrière 25R  : '+tw25R+' kt  (seuil légal AIP 2013 : 7 kt)\n' +
      'Composante arrière 25L  : '+tw25L+' kt\n' +
      'Composante latérale 25R : '+xw25R+' kt  (seuil légal : 15 kt)\n' +
      (tw25Rg ? 'Rafale arrière 25R      : '+tw25Rg+' kt  (seuil légal : 10 kt)\n' : '') +
      '\n=== PLANNING PRS APPLICABLE ===\n' +
      planStr+'\n\n' +
      'Selon l\'instruction ministérielle du 17/07/2013 (AIP EBBR AD 2.21), les pistes 25 constituent' +
      ' la configuration préférentielle lorsque les conditions météorologiques le permettent.\n\n' +
      'Je souhaiterais dès lors obtenir les raisons opérationnelles ou météorologiques qui ont' +
      ' justifié l\'utilisation de la '+pisteLabel+' dans ces conditions.\n\n' +
      'Je vous remercie de l\'attention portée à ce message et reste disponible pour tout' +
      ' complément d\'information.\n\n' +
      'Cordialement,\n\n' +
      '— Via Ça suffit ! ASBL — casuffit.be';

  } else {
    // ── Texte technique BATC (flux existant, inchangé) ─────────────
    var CFG_RWYS = {'25R/25L':['25R','25L'],'19/25R':['19','25R'],'07L/07R':['07L','07R'],'01/07R':['01','07R'],'01/01':['01'],'19/19':['19']};
    var batcPistes = CFG_RWYS[window._currentBatcRwy] || [];
    var why = [];
    batcPistes.forEach(function(rwy){
      var c = comps[rwy]; if(!c) return;
      var m = c.tw||0, g = (c.tw_g!==null&&c.tw_g!==undefined)?c.tw_g:m;
      if(m>7) why.push('piste '+rwy+' : vent arrière moyen '+m.toFixed(1)+' kt > 7 kt');
      else if(g>10) why.push('piste '+rwy+' : rafale arrière '+g.toFixed(1)+' kt > 10 kt (seuil AIP 2013)');
    });
    pmwMailBody =
      'Madame, Monsieur,\n\n' +
      'Je vous contacte suite à des nuisances aériennes constatées ce jour au-dessus de ma commune.\n\n' +
      '=== CONDITIONS MÉTÉO AU MOMENT DE LA PLAINTE ===\n' +
      'Date / Heure     : '+dateStr+' à '+timeStr+'\n' +
      'METAR EBBR       : '+(d.metar||'—')+'\n' +
      'Vent moyen       : '+(d.wspd||'—')+' kt / '+(d.wdir||'—')+'°\n' +
      'Rafales          : '+(d.wgst ? d.wgst+' kt' : '—')+(d.wgst_irm?' (IRM: '+d.wgst_irm+' kt)':'')+'\n\n' +
      '=== CONFIG BATC EN SERVICE ===\n' +
      'Config saisie    : '+window._currentBatcRwy+'\n\n' +
      '=== PLANNING AIP ===\n' +
      planStr+'\n\n' +
      '=== VIOLATION CONSTATÉE ===\n' +
      'Selon l\'instruction ministérielle du 17/07/2013 (AIP EBBR AD 2.21),\n' +
      'la config '+window._currentBatcRwy+' n\'est PAS autorisée dans les conditions actuelles :\n' +
      why.map(function(w){ return '  • '+w; }).join('\n')+'\n\n' +
      'Une capture d\'écran du tableau de bord est jointe à ce message.\n\n' +
      'Dans l\'attente de votre réponse,\n' +
      'Veuillez agréer mes salutations distinguées.\n\n' +
      '— Via Ça suffit ! ASBL — casuffit.be';
  }

  var mailEl = document.getElementById('pmw-plainte-mail');
  if(mailEl) mailEl.textContent = pmwMailBody;

  // ── Capture html2canvas ──
  var script = document.querySelector('script[src*="html2canvas"]');
  function doCapture() {
    var el = document.querySelector('.pmw') || document.querySelector('[data-widget="piste_meteo"]');
    if(!el || typeof html2canvas === 'undefined') {
      loadEl.textContent = '⚠ Capture non disponible. Joignez manuellement une capture d\'écran.';
      return;
    }
    html2canvas(el, {
      scale: 2,
      useCORS: true,
      backgroundColor: '#ffffff',
      logging: false
    }).then(function(canvas) {
      if(canvas.width < 10) {
        loadEl.style.display = 'block';
        loadEl.textContent = '⚠ Capture impossible — joignez une capture d\'écran manuellement.';
        return;
      }
      pmwCaptureDataUrl = canvas.toDataURL('image/png');
      imgEl.src = pmwCaptureDataUrl;
      imgEl.style.display = 'block';
      loadEl.style.display = 'none';
    }).catch(function(err) {
      loadEl.style.display = 'block';
      loadEl.textContent = '⚠ Capture non disponible (' + (err.message||'erreur') + ').';
    });
  }

  if(typeof html2canvas !== 'undefined') {
    doCapture();
  } else {
    // Charger html2canvas à la demande
    var s = document.createElement('script');
    s.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
    s.onload = doCapture;
    s.onerror = function(){ loadEl.textContent = '⚠ Impossible de charger la librairie de capture.'; };
    document.head.appendChild(s);
  }
};

window.pmwClosePlainte = function() {
  document.getElementById('pmw-plainte-overlay').classList.remove('open');
};

// ── Sélecteur de piste ───────────────────────────────────────────────────
window.pmwOpenRwySelector = function() {
  document.getElementById('pmw-rwy-overlay').classList.add('open');
};
window.pmwCloseRwySelector = function() {
  document.getElementById('pmw-rwy-overlay').classList.remove('open');
};
window.pmwSelectRwy = function(piste) {
  pmwCloseRwySelector();
  pmwOpenPlainte(piste); // passe la piste sélectionnée
};

// ── Met à jour le bouton report selon l'état PRS ──────────────────────────
window.pmwUpdateReportBtn = function(alert) {
  var btn = document.getElementById('pmw-report-btn');
  if (!btn) return;
  if (alert) {
    btn.className = 'pmw-report-btn alert';
    btn.textContent = 'Je constate un usage anormal des pistes, je désire porter plainte';
  } else {
    btn.className = 'pmw-report-btn';
    btn.textContent = 'Je constate un usage anormal des pistes, je désire porter plainte';
  }
};

// Ouvre un nouvel email vers le médiateur, sujet pré-rempli (le corps se colle ensuite)
window.pmwOpenMail = function() {
  var now = new Date();
  var dateStr = now.toLocaleDateString('fr-BE',{day:'2-digit',month:'2-digit',year:'numeric'});
  var timeStr = now.toLocaleTimeString('fr-BE',{hour:'2-digit',minute:'2-digit'});
  var cfg = window._currentBatcRwy || '';
  var sujet = 'Plainte nuisance aérienne EBBR — ' + (cfg ? cfg + ' — ' : '') + dateStr + ' ' + timeStr;
  var corps = 'Bonjour,\n\n(Collez ici le contenu copié depuis l\'outil — Ctrl+V / Cmd+V)\n';
  // Destinataires configurés dans l'admin (attribut data sur le widget)
  var pmwEl = document.getElementById('pmw');
  var dest = (pmwEl && pmwEl.getAttribute('data-plainte-dest')) || 'airportmediation@mobilit.fgov.be';
  var mailto = 'mailto:' + encodeURIComponent(dest).replace(/%2C/g, ',')
             + '?subject=' + encodeURIComponent(sujet)
             + '&body=' + encodeURIComponent(corps);
  window.location.href = mailto;
};

window.pmwDownloadCapture = function() {
  if(!pmwCaptureDataUrl) return;
  var a = document.createElement('a');
  var now = new Date();
  a.download = 'plainte-ebbr-'+now.toISOString().slice(0,16).replace(/[T:]/g,'-')+'.png';
  a.href = pmwCaptureDataUrl;
  a.click();
};

window.pmwCopyMail = function() {
  if(navigator.clipboard) {
    navigator.clipboard.writeText(pmwMailBody).then(function(){
      var btn = document.querySelector('.pmw-plainte-btn-copy');
      if(btn){ btn.textContent = '✓ Copié !'; setTimeout(function(){ btn.textContent = '📋 Copier le texte'; }, 2000); }
    });
  }
};

// ── Sections repliables avec mémorisation (localStorage) ──────────────────
window.pmwToggleSection = function(name) {
  var body = document.getElementById('pmw-sec-' + name);
  var icon = document.getElementById('pmw-icon-' + name);
  if(!body) return;
  var collapsed = body.classList.toggle('collapsed');
  if(icon) icon.classList.toggle('collapsed', collapsed);
  try { localStorage.setItem('pmw_sec_' + name, collapsed ? '0' : '1'); } catch(e){}
};

// Restaurer l'état mémorisé des 3 sections au chargement
// Défauts : composantes + prévisions déployées, comparaison réglementaire repliée
(function pmwRestoreSections(){
  var defaults = { composantes:'1', previsions:'1', reglementaire:'0' }; // 1=déployé, 0=replié
  function apply(){
    Object.keys(defaults).forEach(function(name){
      var saved;
      try { saved = localStorage.getItem('pmw_sec_' + name); } catch(e){ saved = null; }
      var state = (saved === '0' || saved === '1') ? saved : defaults[name];
      if(state === '0'){ // replié
        var body = document.getElementById('pmw-sec-' + name);
        var icon = document.getElementById('pmw-icon-' + name);
        if(body) body.classList.add('collapsed');
        if(icon) icon.classList.add('collapsed');
      }
    });
  }
  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', apply);
  } else { apply(); }
})();

window.pmwOpenHelp = function() {
  var o = document.getElementById('pmw-help-overlay');
  if(o) o.classList.add('open');
};
window.pmwCloseHelp = function() {
  var o = document.getElementById('pmw-help-overlay');
  if(o) o.classList.remove('open');
};

window.pmwCopyComplaint = function() {
  var now = new Date();
  var dateStr = now.toLocaleDateString('fr-BE',{weekday:'long',day:'2-digit',month:'long',year:'numeric'});
  var timeStr = now.toLocaleTimeString('fr-BE',{hour:'2-digit',minute:'2-digit'});

  // Données météo
  var pmwState = window._pmwData ? window._pmwData() : {};
  var d = pmwState.data || {};
  var planStr = pmwMailBody.split('=== PLANNING AIP ===')[1]
    ? pmwMailBody.split('=== PLANNING AIP ===')[1].split('===')[0].trim() : '';
  var whyArr = pmwMailBody.split('=== VIOLATION CONSTATÉE ===')[1]
    ? pmwMailBody.split('=== VIOLATION CONSTATÉE ===')[1].split('\u2022').slice(1).map(function(w){return w.trim().split('\n')[0];}) : [];

  var captureHtml = pmwCaptureDataUrl
    ? '<p><img src="'+pmwCaptureDataUrl+'" style="max-width:100%;border:1px solid #ddd;border-radius:8px" alt="Capture conditions EBBR"></p>' : '';

  // ── HTML riche du message (collable dans Gmail/Outlook/Mail) ──────────
  var pisteLabel2 = pisteObservee ? (pisteObservee==='07' ? 'piste 07 (07L/07R)' : 'piste 01') : null;
  var htmlIntro = pisteLabel2
    ? '<p>Madame, Monsieur,</p><p>Je me permets de vous contacter afin de vous signaler qu\'en date du '+obsDateStr+' vers '+obsTimeStr+', j\'ai observé un usage de la <strong>'+pisteLabel2+'</strong> à l\'aéroport de Bruxelles-National (EBBR).</p><p>Les conditions météorologiques semblent indiquer que l\'utilisation des pistes préférentielles 25 aurait pu être envisagée.</p>'
    : '<p>Madame, Monsieur,</p><p>Je vous contacte suite à des nuisances aériennes constatées au-dessus de ma commune. Les conditions météorologiques relevées démontrent une violation du Plan de Répartition du Survol (PRS).</p>';

  var htmlBody = '<div style="font-family:Arial,sans-serif;color:#333;max-width:700px">'
    + htmlIntro
    + '<h3 style="color:#0e3d6b;border-bottom:2px solid #0e3d6b;padding-bottom:6px">Conditions météo EBBR</h3>'
    + '<table style="width:100%;border-collapse:collapse;font-size:.9em">'
    + '<tr style="background:#f0f4f8"><td style="padding:8px 12px;font-weight:bold">Date / Heure</td><td style="padding:8px 12px">'+dateStr+' à '+timeStr+'</td></tr>'
    + '<tr><td style="padding:8px 12px;font-weight:bold">METAR</td><td style="padding:8px 12px;font-family:monospace;font-size:.85em">'+(d.metar||'—')+'</td></tr>'
    + '<tr style="background:#f0f4f8"><td style="padding:8px 12px;font-weight:bold">Vent moyen</td><td style="padding:8px 12px">'+(d.wspd||'—')+' kt — '+(d.wdir||'—')+'°</td></tr>'
    + '<tr><td style="padding:8px 12px;font-weight:bold">Rafales</td><td style="padding:8px 12px">'+(d.wgst ? d.wgst+' kt' : '—')+(d.wgst_irm?' (IRM: '+d.wgst_irm+' kt)':'')+'</td></tr>'
    + '</table>'
    + '<h3 style="color:#0e3d6b;border-bottom:2px solid #0e3d6b;padding-bottom:6px;margin-top:20px">Configuration BATC en service</h3>'
    + '<div style="display:inline-block;background:#fde8e8;border:2px solid #e53e3e;border-radius:8px;padding:10px 20px;font-size:1.1em;font-weight:bold;color:#c0392b">'+window._currentBatcRwy+' — NON AUTORISÉE</div>'
    + '<h3 style="color:#0e3d6b;border-bottom:2px solid #0e3d6b;padding-bottom:6px;margin-top:20px">Planning PRS applicable</h3>'
    + '<div style="background:#f0f4f8;padding:12px;border-radius:6px;font-family:monospace;font-size:.85em;white-space:pre-wrap">'+planStr+'</div>'
    + '<h3 style="color:#0e3d6b;border-bottom:2px solid #0e3d6b;padding-bottom:6px;margin-top:20px">Analyse réglementaire</h3>'
    + '<table style="width:100%;border-collapse:collapse;font-size:.85em">'
    + '<tr style="background:#0e3d6b;color:#fff"><th style="padding:8px 12px;text-align:left">Critère</th><th style="padding:8px 12px;text-align:left">AIP 2013 (légal)</th><th style="padding:8px 12px;text-align:left">AIP Actuel (skeyes)</th></tr>'
    + '<tr><td style="padding:8px 12px;border-bottom:1px solid #eee">Vent arrière piste 25</td><td style="padding:8px 12px;border-bottom:1px solid #eee">7 kt (max 10 kt rafales)</td><td style="padding:8px 12px;border-bottom:1px solid #eee">7 kt (pratique ~6.5 kt)</td></tr>'
    + '<tr style="background:#f9f9f9"><td style="padding:8px 12px;border-bottom:1px solid #eee">Vent latéral piste 25</td><td style="padding:8px 12px;border-bottom:1px solid #eee">15 kt (max 20 kt rafales)</td><td style="padding:8px 12px;border-bottom:1px solid #eee">20 kt</td></tr>'
    + '</table>'
    + (pisteLabel2
      ? '<h3 style="color:#0e3d6b;border-bottom:2px solid #0e3d6b;padding-bottom:6px;margin-top:20px">Demande</h3>'
        + '<div style="background:#f0f4f8;border-radius:8px;padding:14px 18px;font-size:.95em">Je souhaiterais obtenir les raisons opérationnelles ou météorologiques qui ont justifié l\'utilisation de la <strong>'+pisteLabel2+'</strong> dans ces conditions.</div>'
      : '<h3 style="color:#c0392b;border-bottom:2px solid #c0392b;padding-bottom:6px;margin-top:20px">Violations constatées</h3>'
        + '<ul style="background:#fde8e8;border-radius:6px;padding:16px 16px 16px 32px">'
        + whyArr.map(function(w){return '<li>'+w+'</li>';}).join('')
        + '</ul>')
    + captureHtml
    + '<p style="margin-top:8px;font-size:.85em;color:#666">Selon l\'instruction ministérielle du 17/07/2013 (AIP EBBR AD 2.21).</p>'
    + (pisteLabel2
      ? '<p>Je vous remercie de l\'attention portée à ce message et reste disponible pour tout complément d\'information.</p>'
      : '<p>Je vous remercie de bien vouloir prendre en compte cette plainte et de m\'informer des suites qui y seront données.</p>')
    + '<p>Cordialement,</p>'
    + '</div>';

  var btn = document.querySelector('.pmw-plainte-btn-copy');

  // Copie HTML riche via Clipboard API moderne (garde la mise en forme)
  function copyOk() {
    if(btn){ btn.textContent = '✓ Copié ! Collez-le dans votre email'; btn.classList.add('pmw-copied');
      setTimeout(function(){ btn.textContent = '📋 Copier le contenu de la plainte'; btn.classList.remove('pmw-copied'); }, 4000); }
  }
  function copyFallback() {
    navigator.clipboard.writeText(pmwMailBody).then(copyOk).catch(function(){
      alert('Copie impossible. Sélectionnez et copiez le texte manuellement.');
    });
  }

  if (navigator.clipboard && window.ClipboardItem) {
    try {
      var blobHtml = new Blob([htmlBody], {type:'text/html'});
      var blobText = new Blob([pmwMailBody], {type:'text/plain'});
      var item = new ClipboardItem({'text/html': blobHtml, 'text/plain': blobText});
      navigator.clipboard.write([item]).then(copyOk).catch(copyFallback);
    } catch(e) { copyFallback(); }
  } else {
    copyFallback();
  }
};
</script>
