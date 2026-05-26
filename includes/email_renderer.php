<?php
// includes/email_renderer.php — Charge et rend les templates email depuis la BDD
// Si le template n'existe pas en BDD, utilise le template PHP par défaut

/**
 * Charge le sujet et le HTML d'un template email.
 *
 * @param  PDO    $db
 * @param  string $slug      ex: 'magic_link', 'invite_membre'
 * @param  array  $vars      Variables à remplacer : ['{{prenom}}' => 'Jérôme', ...]
 * @param  string $lang      'fr' ou 'nl'
 * @return array  ['sujet'=>..., 'html'=>..., 'text'=>...]
 */
function renderEmailTemplate(PDO $db, string $slug, array $vars = [], string $lang = 'fr'): array {
    // Charger depuis la BDD
    try {
        $stmt = $db->prepare("SELECT * FROM email_templates WHERE slug=? LIMIT 1");
        $stmt->execute([$slug]);
        $tpl = $stmt->fetch();
    } catch (Exception $e) {
        $tpl = null;
    }

    $sujet_field   = 'sujet_' . $lang;
    $contenu_field = 'contenu_' . $lang;

    if ($tpl && !empty($tpl[$contenu_field])) {
        // Remplacer les variables dans le template BDD
        $sujet = applyVars($tpl[$sujet_field] ?: $tpl['sujet_fr'], $vars);
        $html  = applyVars($tpl[$contenu_field], $vars);
        $text  = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $html));
        return ['sujet' => $sujet, 'html' => $html, 'text' => $text, 'from_db' => true];
    }

    // Fallback : template PHP par défaut
    return ['sujet' => '', 'html' => '', 'text' => '', 'from_db' => false];
}

/**
 * Remplace les variables {{nom}} dans une chaîne.
 */
function applyVars(string $tpl, array $vars): string {
    foreach ($vars as $k => $v) {
        $tpl = str_replace($k, $v, $tpl);
    }
    return $tpl;
}

/**
 * Initialise les templates par défaut en BDD si absents.
 * À appeler une seule fois lors de l'installation.
 */
function initEmailTemplates(PDO $db): void {
    $defaults = getDefaultEmailTemplates();
    $stmt = $db->prepare("INSERT IGNORE INTO email_templates (slug, label, sujet_fr, sujet_nl, contenu_fr, contenu_nl, variables) VALUES (?,?,?,?,?,?,?)");
    foreach ($defaults as $slug => $tpl) {
        $stmt->execute([
            $slug,
            $tpl['label'],
            $tpl['sujet_fr'],
            $tpl['sujet_nl'] ?? $tpl['sujet_fr'],
            $tpl['contenu_fr'],
            $tpl['contenu_nl'] ?? $tpl['contenu_fr'],
            json_encode($tpl['variables'] ?? []),
        ]);
    }
}

function getDefaultEmailTemplates(): array {
    $header = fn($titre, $sous = '') => '
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4f8;padding:30px 0;font-family:\'Helvetica Neue\',Arial,sans-serif">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:10px;overflow:hidden;max-width:560px">
<tr><td style="background:#1673B2;padding:28px 32px;text-align:center">
  <div style="font-size:22px;font-weight:800;color:#FF9900">Ça suffit !</div>
  <div style="font-size:12px;color:rgba(255,255,255,.7);margin-top:4px">' . $sous . '</div>
</td></tr>
<tr><td style="background:#FF9900;height:3px"></td></tr>
<tr><td style="padding:32px">';

    $footer = fn($email = '{{email}}') => '
</td></tr>
<tr><td style="background:#f5f7fa;padding:16px 32px;text-align:center;border-top:1px solid #e0e8f0">
  <p style="font-size:11px;color:#aaa;margin:0">
    Ça suffit ! ASBL · <a href="https://www.casuffit.be" style="color:#1673B2">casuffit.be</a><br>
    Email envoyé à <strong>' . $email . '</strong>
  </p>
</td></tr>
</table></td></tr></table>';

    return [

        'magic_link' => [
            'label'     => 'Lien magique — Connexion espace membre',
            'sujet_fr'  => 'Votre accès espace membre — Ça suffit ! ASBL',
            'sujet_nl'  => 'Uw toegang ledenruimte — Ça suffit ! VZW',
            'variables' => ['{{prenom}}','{{code_membre}}','{{magic_url}}','{{email}}','{{expiry}}'],
            'contenu_fr' => $header('Connexion à votre espace membre', 'ASBL — Piste 01 · UBCNA · Espace membre')
. '<p style="font-size:15px;font-weight:700;color:#0e3d6b;margin:0 0 16px">Bonjour {{prenom}},</p>
<p style="font-size:14px;color:#555;line-height:1.7;margin:0 0 20px">
  Voici votre <strong>lien de connexion sécurisé</strong> pour accéder à votre espace membre.<br>
  Ce lien est <strong>personnel, à usage unique</strong> et valable <strong>24 heures</strong>.
</p>
<div style="background:#FF9900;color:#fff;text-align:center;padding:8px;font-weight:700;font-size:13px;letter-spacing:.06em;border-radius:4px;margin-bottom:20px">{{code_membre}}</div>
<table cellpadding="0" cellspacing="0" width="100%">
  <tr><td align="center" style="padding:8px 0 20px">
    <a href="{{magic_url}}" style="background:#1673B2;color:#fff;text-decoration:none;padding:14px 32px;border-radius:8px;font-size:15px;font-weight:700;display:inline-block">✨ Accéder à mon espace membre</a>
  </td></tr>
</table>
<div style="background:#f0f7ff;border-radius:8px;padding:14px;border:1px solid #bee3f8;margin-bottom:16px">
  <p style="color:#1673B2;font-size:13px;margin:0 0 6px;font-weight:700">Votre QR code personnel vous permet de :</p>
  <p style="color:#555;font-size:12px;line-height:1.7;margin:0">
    ✅ Effectuer un virement avec communication structurée (+++)<br>
    ✅ Être identifié automatiquement lors de la réception du don<br>
    ✅ Voir l\'historique de vos contributions
  </p>
</div>
<p style="color:#aaa;font-size:12px">Si vous n\'avez pas demandé ce lien, ignorez cet email. Expire le : <strong>{{expiry}}</strong></p>'
. $footer(),
        ],

        'invite_membre' => [
            'label'     => 'Invitation abonné → Espace membre',
            'sujet_fr'  => 'Votre espace membre vous attend — Ça suffit ! ASBL',
            'sujet_nl'  => 'Uw ledenruimte wacht op u — Ça suffit ! VZW',
            'variables' => ['{{prenom}}','{{url}}','{{email}}'],
            'contenu_fr' => $header('Invitation — Espace membre', 'ASBL — Stop aux nuisances aériennes de Brussels Airport')
. '<p style="font-size:15px;font-weight:700;color:#0e3d6b;margin:0 0 16px">Bonjour {{prenom}},</p>
<p style="font-size:14px;color:#555;line-height:1.6;margin:0 0 16px">
  En tant qu\'abonné(e) à notre newsletter, nous vous invitons à créer votre <strong style="color:#1673B2">espace membre gratuit</strong> :
</p>
<table cellpadding="0" cellspacing="0" style="margin-bottom:20px">
  <tr><td style="padding:5px 0;font-size:13px;color:#333"><span style="color:#FF9900;font-weight:700">✓</span>&nbsp; <strong>QR code de paiement personnel</strong> avec communication structurée unique</td></tr>
  <tr><td style="padding:5px 0;font-size:13px;color:#333"><span style="color:#FF9900;font-weight:700">✓</span>&nbsp; <strong>Historique de vos dons</strong> dans votre espace privé sécurisé</td></tr>
  <tr><td style="padding:5px 0;font-size:13px;color:#333"><span style="color:#FF9900;font-weight:700">✓</span>&nbsp; <strong>Accès sans mot de passe</strong> par lien magique</td></tr>
</table>
<table cellpadding="0" cellspacing="0" width="100%">
  <tr><td align="center" style="padding:8px 0 20px">
    <a href="{{url}}" style="background:#FF9900;color:#fff;text-decoration:none;padding:14px 36px;border-radius:8px;font-weight:700;font-size:15px;display:inline-block">✨ Créer mon espace membre</a>
  </td></tr>
</table>
<p style="font-size:11px;color:#bbb;text-align:center">Lien valable 30 jours · Vous pouvez rester simplement abonné à la newsletter.</p>'
. $footer(),
        ],

        'invite_wix' => [
            'label'     => 'Relance anciens membres (Ça Suffit)',
            'sujet_fr'  => 'Le mouvement Ça Suffit reprend vie — rejoignez-nous',
            'sujet_nl'  => 'De beweging Ça Suffit komt weer tot leven — doe mee',
            'variables' => ['{{prenom}}','{{url}}','{{email}}'],
            'contenu_fr' => $header('Le mouvement Ça Suffit reprend vie', 'Stop aux nuisances aériennes de Brussels Airport')
. '<p style="font-size:15px;font-weight:700;color:#0e3d6b;margin:0 0 16px">Bonjour {{prenom}},</p>
<p style="font-size:14px;color:#555;line-height:1.6;margin:0 0 16px">
  Vous aviez rejoint le mouvement <strong>Piste 01 — Ça Suffit</strong> il y a quelques années pour défendre votre quartier contre le survol injuste. Nous ne vous avons pas oublié(e).
</p>
<p style="font-size:14px;color:#555;line-height:1.6;margin:0 0 16px">
  Aujourd\'hui, <strong>le mouvement « Ça Suffit » reprend vie</strong>. UBCNA et Piste 01 unissent à nouveau leurs forces, et nous lançons un tout nouveau site pour relancer la mobilisation de tous les survolés injustement.
</p>
<p style="font-size:14px;color:#555;line-height:1.6;margin:0 0 20px">
  Cette fois, nous mettons à votre disposition de vrais outils pour comprendre <strong style="color:#1673B2">pourquoi vous êtes survolé(e)</strong> — et savoir si c\'est justifié ou non selon la météo :
</p>
<table cellpadding="0" cellspacing="0" style="margin-bottom:20px">
  <tr><td style="padding:5px 0;font-size:13px;color:#333"><span style="color:#FF9900;font-weight:700">✓</span>&nbsp; <strong>Conditions de vent en direct</strong> et suivi des atterrissages</td></tr>
  <tr><td style="padding:5px 0;font-size:13px;color:#333"><span style="color:#FF9900;font-weight:700">✓</span>&nbsp; <strong>Un assistant pour porter plainte</strong> en quelques clics</td></tr>
  <tr><td style="padding:5px 0;font-size:13px;color:#333"><span style="color:#FF9900;font-weight:700">✓</span>&nbsp; <strong>Espace membre</strong> : dons, suivi des paiements, newsletter</td></tr>
</table>
<p style="font-size:14px;color:#555;line-height:1.6;margin:0 0 20px">Nous avons besoin de vous. Redevenez membre du mouvement en quelques secondes :</p>
<table cellpadding="0" cellspacing="0" width="100%">
  <tr><td align="center" style="padding:8px 0 20px">
    <a href="{{url}}" style="background:#FF9900;color:#fff;text-decoration:none;padding:14px 36px;border-radius:8px;font-weight:700;font-size:15px;display:inline-block">Je rejoins le mouvement</a>
  </td></tr>
</table>
<p style="font-size:11px;color:#bbb;text-align:center">Lien valable 30 jours · Notre combat reste juste : faire respecter les règles pour tous, sans déplacer le problème d\'une ville à l\'autre.</p>'
. $footer(),
        ],

        'confirm_newsletter' => [
            'label'     => 'Confirmation d\'abonnement newsletter',
            'sujet_fr'  => 'Confirmez votre abonnement — Ça suffit ! ASBL',
            'sujet_nl'  => 'Bevestig uw abonnement — Ça suffit ! VZW',
            'variables' => ['{{prenom}}','{{confirm_url}}','{{email}}'],
            'contenu_fr' => $header('Confirmation d\'abonnement', 'ASBL — Stop aux nuisances aériennes')
. '<p style="font-size:15px;font-weight:700;color:#0e3d6b;margin:0 0 16px">Bonjour {{prenom}},</p>
<p style="font-size:14px;color:#555;line-height:1.6;margin:0 0 20px">
  Merci de votre intérêt pour Ça suffit ! ASBL.<br>
  Veuillez confirmer votre abonnement en cliquant sur le bouton ci-dessous.
</p>
<table cellpadding="0" cellspacing="0" width="100%">
  <tr><td align="center" style="padding:8px 0 20px">
    <a href="{{confirm_url}}" style="background:#1673B2;color:#fff;text-decoration:none;padding:14px 32px;border-radius:8px;font-size:15px;font-weight:700;display:inline-block">✅ Confirmer mon abonnement</a>
  </td></tr>
</table>
<p style="font-size:12px;color:#aaa;text-align:center">Si vous n\'avez pas demandé cet abonnement, ignorez cet email.</p>'
. $footer(),
        ],

        'confirm_email' => [
            'label'     => 'Confirmation changement d\'email (espace membre)',
            'sujet_fr'  => 'Confirmez votre nouvel email — Ça suffit ! ASBL',
            'sujet_nl'  => 'Bevestig uw nieuw e-mailadres — Ça suffit ! VZW',
            'variables' => ['{{prenom}}','{{lien}}','{{email_nouveau}}','{{email}}'],
            'contenu_fr' => $header('Changement d\'email', 'ASBL — Espace membre')
. '<p style="font-size:15px;font-weight:700;color:#0e3d6b;margin:0 0 16px">Bonjour {{prenom}},</p>
<p style="font-size:14px;color:#555;line-height:1.6;margin:0 0 20px">
  Vous avez demandé à changer votre email vers : <strong>{{email_nouveau}}</strong><br><br>
  Cliquez sur le lien ci-dessous pour confirmer ce changement.
</p>
<table cellpadding="0" cellspacing="0" width="100%">
  <tr><td align="center" style="padding:8px 0 20px">
    <a href="{{lien}}" style="background:#1673B2;color:#fff;text-decoration:none;padding:14px 32px;border-radius:8px;font-size:15px;font-weight:700;display:inline-block">✅ Confirmer mon nouvel email</a>
  </td></tr>
</table>
<p style="font-size:12px;color:#aaa;text-align:center">Ce lien expire dans 24h. Si vous n\'avez pas fait cette demande, ignorez cet email.</p>'
. $footer('{{email_nouveau}}'),
        ],

    ];
}
