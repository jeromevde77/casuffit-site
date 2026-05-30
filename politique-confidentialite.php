<?php require_once __DIR__ . '/config.php'; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Politique de confidentialité — Piste01 Ça Suffit ASBL</title>
  <link rel="icon" type="image/x-icon" href="/favicon.ico">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, "Helvetica Neue", Arial, sans-serif; background: #f0f4f8; color: #333; }
    header { background: #0e3d6b; color: #fff; padding: 20px 24px; }
    header a { color: #F5A623; text-decoration: none; font-size: .85rem; }
    header h1 { font-size: 1.3rem; font-weight: 800; margin-top: 8px; }
    .container { max-width: 760px; margin: 32px auto; padding: 0 16px 60px; }
    .card { background: #fff; border-radius: 14px; padding: 28px 32px; box-shadow: 0 2px 12px rgba(0,0,0,.06); margin-bottom: 20px; }
    h2 { font-size: 1rem; font-weight: 800; color: #0e3d6b; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid #e8f0fa; }
    h3 { font-size: .9rem; font-weight: 700; color: #333; margin: 16px 0 6px; }
    p { font-size: .85rem; line-height: 1.7; color: #555; margin-bottom: 10px; }
    ul { font-size: .85rem; line-height: 1.7; color: #555; padding-left: 20px; margin-bottom: 10px; }
    table { width: 100%; border-collapse: collapse; font-size: .82rem; margin: 12px 0; }
    th { background: #f0f4f8; color: #0e3d6b; font-weight: 700; padding: 8px 10px; text-align: left; border-bottom: 2px solid #dde6f0; }
    td { padding: 8px 10px; border-bottom: 1px solid #f0f4f8; vertical-align: top; }
    .btn-reset { display: inline-block; margin-top: 12px; padding: 10px 20px; background: #0e3d6b; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: .85rem; font-weight: 700; }
    .updated { font-size: .75rem; color: #aaa; margin-top: 4px; }
  </style>
</head>
<body>
<header>
  <a href="/">← Retour au site</a>
  <h1>Politique de confidentialité &amp; Cookies</h1>
  <p class="updated">Dernière mise à jour : <?= date('d/m/Y') ?></p>
</header>

<div class="container">

  <div class="card">
    <h2>1. Responsable du traitement</h2>
    <p><strong>Piste01 Ça Suffit ASBL</strong><br>
    Association sans but lucratif de droit belge<br>
    Contact : <a href="mailto:<?= htmlspecialchars(cfg('site_email','info@casuffit.be')) ?>"><?= htmlspecialchars(cfg('site_email','info@casuffit.be')) ?></a></p>
  </div>

  <div class="card">
    <h2>2. Données collectées et finalités</h2>

    <h3>2.1 Espace membre</h3>
    <p>Lors de votre inscription, nous collectons votre prénom, nom et adresse e-mail. Ces données sont utilisées pour :</p>
    <ul>
      <li>Gérer votre compte et votre accès à l'espace membre</li>
      <li>Vous envoyer la newsletter si vous y avez souscrit</li>
      <li>Vous informer des actions et actualités de l'ASBL</li>
    </ul>
    <p><strong>Base légale :</strong> intérêt légitime (gestion associative) et consentement (newsletter).</p>
    <p><strong>Durée de conservation :</strong> tant que votre compte est actif. Sur demande de suppression, vos données sont effacées dans les 30 jours.</p>

    <h3>2.2 Suivi de nos campagnes par email</h3>
    <p>Afin d'améliorer notre communication, nous mesurons de manière statistique l'ouverture de certains emails de campagne que nous vous envoyons (par exemple nos invitations ou appels à la mobilisation), via un indicateur d'ouverture intégré au message. Nous enregistrons la date et le nombre d'ouvertures.</p>
    <p>Ce suivi ne concerne <strong>pas</strong> les emails techniques (lien de connexion, confirmations). Ces données servent uniquement à des fins d'analyse interne, ne sont jamais transmises à des tiers, et vous pouvez vous y opposer en désactivant l'affichage des images dans votre logiciel de messagerie.</p>
    <p><strong>Base légale :</strong> intérêt légitime (mesure et amélioration de notre communication associative).</p>

    <h3>2.3 Cookies de session</h3>
    <p>Un cookie de session PHP (<code>PHPSESSID</code>) est déposé lors de votre connexion. Ce cookie est <strong>strictement nécessaire</strong> au fonctionnement de l'espace membre et ne peut pas être refusé. Il expire à la fermeture du navigateur.</p>

    <h3>2.4 Google Analytics (si accepté)</h3>
    <p>Avec votre consentement, Google Analytics 4 mesure le trafic sur ce site (pages visitées, durée, pays d'origine). Les adresses IP sont anonymisées. Aucune donnée personnelle identifiable n'est transmise.</p>
    <p><strong>Responsable tiers :</strong> Google LLC, 1600 Amphitheatre Parkway, Mountain View, CA 94043, États-Unis.</p>

    <h3>2.5 Widget Facebook (si accepté)</h3>
    <p>Avec votre consentement, un widget de la page Facebook de l'ASBL est affiché. Meta Platforms Ireland Ltd peut déposer des cookies tiers pour mesurer les interactions.</p>
    <p><strong>Responsable tiers :</strong> Meta Platforms Ireland Ltd, 4 Grand Canal Square, Dublin 2, Irlande.</p>
  </div>

  <div class="card">
    <h2>3. Tableau récapitulatif des cookies</h2>
    <table>
      <thead>
        <tr><th>Cookie</th><th>Type</th><th>Finalité</th><th>Durée</th></tr>
      </thead>
      <tbody>
        <tr><td>PHPSESSID</td><td>Nécessaire</td><td>Session membre</td><td>Session</td></tr>
        <tr><td>_ga, _ga_*</td><td>Analytique</td><td>Google Analytics</td><td>2 ans</td></tr>
        <tr><td>rgpd_consent</td><td>Fonctionnel</td><td>Mémoriser vos choix RGPD</td><td>1 an</td></tr>
        <tr><td>Cookies Meta</td><td>Réseaux sociaux</td><td>Widget Facebook</td><td>Variable</td></tr>
      </tbody>
    </table>
  </div>

  <div class="card">
    <h2>4. Vos droits</h2>
    <p>Conformément au RGPD (Règlement EU 2016/679) et à la loi belge du 30 juillet 2018, vous disposez des droits suivants :</p>
    <ul>
      <li><strong>Accès :</strong> obtenir une copie de vos données personnelles</li>
      <li><strong>Rectification :</strong> corriger des données inexactes</li>
      <li><strong>Suppression :</strong> demander l'effacement de vos données</li>
      <li><strong>Opposition :</strong> vous opposer à certains traitements</li>
      <li><strong>Portabilité :</strong> recevoir vos données dans un format structuré</li>
    </ul>
    <p>Pour exercer vos droits : <a href="mailto:<?= htmlspecialchars(cfg('site_email','info@casuffit.be')) ?>"><?= htmlspecialchars(cfg('site_email','info@casuffit.be')) ?></a></p>
    <p>En cas de litige non résolu, vous pouvez introduire une plainte auprès de l'<strong>Autorité de Protection des Données (APD)</strong> : <a href="https://www.autoriteprotectiondonnees.be" target="_blank" rel="noopener">www.autoriteprotectiondonnees.be</a></p>
  </div>

  <div class="card">
    <h2>5. Gérer vos préférences cookies</h2>
    <p>Vous pouvez modifier vos choix à tout moment en cliquant sur le bouton ci-dessous. Vos préférences sont sauvegardées dans <code>localStorage</code> sur votre appareil.</p>
    <button class="btn-reset" onclick="localStorage.removeItem('rgpd_consent'); alert('Préférences réinitialisées. Rechargez la page pour choisir à nouveau.'); window.location='/'">
      Réinitialiser mes préférences cookies
    </button>
  </div>

</div>
</body>
</html>
