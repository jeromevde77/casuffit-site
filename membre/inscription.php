<?php
// membre/inscription.php — Inscription nouveau membre
// error_reporting(E_ALL); ini_set('display_errors', 1); // désactivé en production
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/functions.php';

session_start();
require_once __DIR__ . '/lang.php';
$db = getDB();

// Si déjà connecté
if (getMembre($db)) {
    header('Location: dashboard.php'); exit;
}

$msg = ''; $error = ''; $success = false;

// ── Générer token CSRF ────────────────────────────────────────────────────
if (empty($_SESSION['csrf_inscription'])) {
    $_SESSION['csrf_inscription'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_inscription'];

// ── Question mathématique anti-bot ────────────────────────────────────────
if (empty($_SESSION['captcha_a']) || empty($_SESSION['captcha_b'])) {
    $_SESSION['captcha_a'] = rand(2, 9);
    $_SESSION['captcha_b'] = rand(1, 9);
}
$captcha_a      = $_SESSION['captcha_a'];
$captcha_b      = $_SESSION['captcha_b'];
$captcha_result = $captcha_a + $captcha_b;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── 1. Honeypot ──────────────────────────────────────────────────────
    if (!empty($_POST['website'])) {
        $success = true;
        $msg = tm('msg_lien_generique');
        goto end_form;
    }

    // ── 2. Token CSRF ────────────────────────────────────────────────────
    if (empty($_POST['_csrf']) || !hash_equals($csrf_token, $_POST['_csrf'])) {
        $error = tm('err_securite');
        goto end_form;
    }

    // ── 3. Question mathématique ─────────────────────────────────────────
    $captcha_input = intval($_POST['captcha_answer'] ?? -999);
    if ($captcha_input !== $captcha_result) {
        $error = tm('err_captcha');
        $_SESSION['captcha_a'] = rand(2, 9);
        $_SESSION['captcha_b'] = rand(1, 9);
        $captcha_a = $_SESSION['captcha_a'];
        $captcha_b = $_SESSION['captcha_b'];
        $captcha_result = $captcha_a + $captcha_b;
        goto end_form;
    }
    unset($_SESSION['captcha_a'], $_SESSION['captcha_b']);

    // ── 3. Rate limiting : max 3 inscriptions par heure par IP ──────────
    $ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = 'reg_' . md5($ip);
    $now = time();
    $_SESSION[$key] = array_filter($_SESSION[$key] ?? [], fn($t) => $now - $t < 3600);
    if (count($_SESSION[$key]) >= 3) {
        $error = tm('err_rate_limit');
        goto end_form;
    }
    $_SESSION[$key][] = $now;
    $email    = filter_var(trim(isset($_POST['email'])    ? $_POST['email']    : ''), FILTER_VALIDATE_EMAIL);
    $prenom   = htmlspecialchars(trim(isset($_POST['prenom'])   ? $_POST['prenom']   : ''), ENT_QUOTES, 'UTF-8');
    $nom      = htmlspecialchars(trim(isset($_POST['nom'])      ? $_POST['nom']      : ''), ENT_QUOTES, 'UTF-8');
    $commune  = htmlspecialchars(trim(isset($_POST['commune'])  ? $_POST['commune']  : ''), ENT_QUOTES, 'UTF-8');
    $adresse  = htmlspecialchars(trim(isset($_POST['adresse'])  ? $_POST['adresse']  : ''), ENT_QUOTES, 'UTF-8');
    $tel      = htmlspecialchars(trim(isset($_POST['telephone'])? $_POST['telephone']: ''), ENT_QUOTES, 'UTF-8');
    $rgpd     = !empty($_POST['rgpd']);

    if (!\$email)  { \$error = tm('err_email'); }
    elseif (!\$prenom || !\$nom) { \$error = tm('err_prenom_nom'); }
    elseif (!\$rgpd) { \$error = tm('err_rgpd'); }
    else {
        // Vérifier si email déjà inscrit
        $check = $db->prepare("SELECT id, statut FROM members WHERE email = ?");
        $check->execute(array($email));
        $existing = $check->fetch();

        if ($existing) {
            // Déjà inscrit — envoyer un lien magique directement
            $membre = $db->prepare("SELECT * FROM members WHERE id = ?")->execute(array($existing['id']));
            $membre = $db->query("SELECT * FROM members WHERE id = {$existing['id']}")->fetch();
            envoyerLienMagique($db, $membre);
            $success = true;
            $msg = tm('msg_deja_membre', $email);
        } else {
            try {
                // Créer le membre
                $token_unsub = bin2hex(random_bytes(32));

                // Insérer d'abord pour obtenir l'ID
                $db->prepare("INSERT INTO members (email, prenom, nom, commune, telephone, token_unsub, statut, newsletter, code_membre, ogm)
                    VALUES (?, ?, ?, ?, ?, ?, 'actif', 1, 'TEMP', 'TEMP')")
                   ->execute(array($email, $prenom, $nom, $commune, $tel, $token_unsub));

                $member_id   = $db->lastInsertId();
                $code_membre = genererCodeMembre($db);
                $ogm         = genererOGM($member_id);

                $db->prepare("UPDATE members SET code_membre=?, ogm=? WHERE id=?")
                   ->execute(array($code_membre, $ogm, $member_id));

                // Inscrire aussi à la newsletter si pas déjà abonné
                $sub_check = $db->prepare("SELECT id FROM subscribers WHERE email = ?");
                $sub_check->execute(array($email));
                $sub = $sub_check->fetch();
                if (!$sub) {
                    $token_confirm = bin2hex(random_bytes(32));
                    $token_unsub2  = bin2hex(random_bytes(32));
                    $db->prepare("INSERT INTO subscribers (email, prenom, nom, adresse, commune, telephone, rgpd_accepte, statut, token_confirm, token_unsub, source)
                        VALUES (?, ?, ?, ?, ?, ?, 1, 'actif', ?, ?, 'membre')")
                       ->execute(array($email, $prenom, $nom, $adresse, $commune, $tel, $token_confirm, $token_unsub2));
                    $sub_id = $db->lastInsertId();
                    $db->prepare("UPDATE members SET subscriber_id=? WHERE id=?")->execute(array($sub_id, $member_id));
                } else {
                    $db->prepare("UPDATE members SET subscriber_id=? WHERE id=?")->execute(array($sub['id'], $member_id));
                }

                // Envoyer le lien magique
                $nouveau_membre = $db->query("SELECT * FROM members WHERE id = $member_id")->fetch();
                envoyerLienMagique($db, $nouveau_membre);

                $success = true;
                $msg = tm('msg_bienvenue', \$code_membre, \$email);

            } catch (Exception $e) {
                error_log('Inscription membre: ' . $e->getMessage());
                $error = tm('err_creation') . ': ' . \$e->getMessage();
            }
        }
    }
    end_form:;
}
?>
<!DOCTYPE html>
<html lang="<?= $LANG ?>">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars(tm('inscription_page')) ?></title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:"Helvetica Neue",Arial,sans-serif;background:linear-gradient(135deg,#0e3d6b,#1673B2);min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:20px}
    .card{background:#fff;border-radius:16px;box-shadow:0 8px 40px rgba(0,0,0,0.25);padding:40px;width:100%;max-width:480px}
    .brand{text-align:center;margin-bottom:28px}
    .brand img{width:72px;height:72px;border-radius:50%;margin-bottom:12px}
    .brand h1{font-size:1.3rem;font-weight:800;color:#1673B2;line-height:1.2}
    .brand h1 span{color:#FF9900;font-style:italic}
    .brand p{font-size:0.78rem;color:#888;margin-top:4px}
    label{display:block;font-size:0.78rem;font-weight:600;color:#555;margin-bottom:5px;margin-top:14px}
    input[type=text],input[type=email],input[type=tel]{width:100%;padding:10px 12px;border:1.5px solid #dde4ed;border-radius:7px;font-size:0.88rem;color:#333;transition:border .2s;outline:none;font-family:inherit}
    input:focus{border-color:#1673B2}
    .form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .check-wrap{display:flex;align-items:flex-start;gap:8px;margin-top:14px;background:#f0f7ff;padding:10px 12px;border-radius:7px;border:1px solid #bee3f8}
    .check-wrap input{margin-top:3px;flex-shrink:0;accent-color:#1673B2}
    .check-wrap label{font-size:0.78rem;color:#2c5282;margin:0;font-weight:400}
    .btn{width:100%;background:#1673B2;color:#fff;border:none;padding:13px;border-radius:8px;font-size:0.95rem;font-weight:700;cursor:pointer;margin-top:20px;font-family:inherit;transition:background .2s}
    .btn:hover{background:#125a90}
    .msg-ok{background:#e8f8f0;color:#276749;padding:14px;border-radius:8px;margin-bottom:16px;font-size:0.85rem;border-left:3px solid #48bb78;line-height:1.6}
    .msg-err{background:#fde8e8;color:#c53030;padding:14px;border-radius:8px;margin-bottom:16px;font-size:0.85rem;border-left:3px solid #fc8181}
    .avantages{background:#f8f9fa;border-radius:8px;padding:14px;margin-bottom:20px}
    .avantages p{font-size:0.8rem;color:#555;line-height:1.7}
    .avantages strong{color:#1673B2}
    .links{text-align:center;margin-top:16px;font-size:0.78rem;color:#888}
    .links a{color:#1673B2;text-decoration:none}
    @media(max-width:400px){.form-row{grid-template-columns:1fr}}
  </style>
</head>
<body>
<div class="card">
  <div class="brand">
    <h1>ça suffit ! <span><?= $LANG==='nl'?'VZW':'ASBL' ?></span></h1>
    <p><?= tm('inscription_titre') ?></p>
  </div>
  <div style="text-align:right;margin-bottom:8px;font-size:.75rem">
    <a href="?lang=fr" style="<?= $LANG==='fr'?'font-weight:700;color:#1673B2':'color:#aaa' ?>">FR</a> |
    <a href="?lang=nl" style="<?= $LANG==='nl'?'font-weight:700;color:#1673B2':'color:#aaa' ?>">NL</a>
  </div>

  <?php if ($success): ?>
    <div class="msg-ok"><?= $msg ?></div>
    <div style="text-align:center;margin-top:10px">
      <a href="<?= SITE_URL ?>" style="font-size:0.82rem;color:#1673B2">← Retour au site</a>
    </div>
  <?php else: ?>

  <?php if ($error): ?><div class="msg-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="avantages">
    <p>
      <?= tm('avantages_intro') ?><br>
      ✅ <strong><?= tm('avantage_qr') ?></strong><br>
      ✅ <strong><?= tm('avantage_dons') ?></strong><br>
      ✅ <strong><?= tm('avantage_nl') ?></strong><br>
      ✅ <strong><?= tm('avantage_acces') ?></strong>
    </p>
  </div>

  <form method="POST">
    <!-- Token CSRF -->
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf_token) ?>">
    <!-- Honeypot : invisible pour les humains, les bots le remplissent -->
    <div style="display:none" aria-hidden="true">
      <label>Website</label>
      <input type="text" name="website" tabindex="-1" autocomplete="off">
    </div>
    <div class="form-row">
      <div>
        <label><?= tm('prenom') ?></label>
        <input type="text" name="prenom" value="<?= htmlspecialchars(isset($_POST['prenom']) ? $_POST['prenom'] : '') ?>" required>
      </div>
      <div>
        <label><?= tm('nom') ?></label>
        <input type="text" name="nom" value="<?= htmlspecialchars(isset($_POST['nom']) ? $_POST['nom'] : '') ?>" required>
      </div>
    </div>
    <label><?= tm('email') ?></label>
    <input type="email" name="email" value="<?= htmlspecialchars(isset($_POST['email']) ? $_POST['email'] : '') ?>" required>
    <label><?= tm('adresse') ?></label>
    <input type="text" name="adresse" value="<?= htmlspecialchars(isset($_POST['adresse']) ? $_POST['adresse'] : '') ?>" placeholder="<?= tm('adresse_ph') ?>">
    <div class="form-row">
      <div>
        <label><?= tm('commune') ?></label>
        <input type="text" name="commune" value="<?= htmlspecialchars(isset($_POST['commune']) ? $_POST['commune'] : '') ?>">
      </div>
      <div>
        <label><?= tm('telephone') ?></label>
        <input type="tel" name="telephone" value="<?= htmlspecialchars(isset($_POST['telephone']) ? $_POST['telephone'] : '') ?>">
      </div>
    </div>
    <div class="check-wrap">
      <input type="checkbox" name="rgpd" id="rgpd" required>
      <label for="rgpd"><?= tm('rgpd_label') ?></label>
    </div>
    <div style="background:#f0f7ff;border:1.5px solid #c8dff0;border-radius:8px;padding:14px;margin-bottom:16px">
      <label style="font-size:.82rem;font-weight:600;color:#0e3d6b;display:block;margin-bottom:8px">
        <?= tm('captcha_label') ?>
      </label>
      <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
        <span style="font-size:1rem;font-weight:700;color:#1673B2"><?= $captcha_a ?> + <?= $captcha_b ?> = ?</span>
        <input type="number" name="captcha_answer" required
               style="width:80px;padding:8px 10px;border:1.5px solid #c8dff0;border-radius:6px;font-size:1rem;font-weight:700;text-align:center"
               placeholder="?" min="0" max="99" autocomplete="off">
      </div>
    </div>
    <button type="submit" class="btn"><?= tm('btn_creer') ?></button>
  </form>

  <div class="links">
    <?= tm('deja_membre') ?> <a href="login.php"><?= tm('recevoir_lien') ?></a><br>
    <a href="<?= SITE_URL ?>"><?= tm('retour_site') ?></a>
  </div>

  <?php endif; ?>
</div>
</body>
</html>
