<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/functions.php';

session_start();
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/lang.php';
$db     = getDB();
$membre = requireMembre($db);

$success = '';
$error   = '';

// ── Traitement du formulaire ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_verify(); // CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $prenom  = trim($_POST['prenom']  ?? '');
    $nom     = trim($_POST['nom']     ?? '');
    $email   = trim(strtolower($_POST['email'] ?? ''));
    $adresse = trim($_POST['adresse'] ?? '');
    $commune = trim($_POST['commune'] ?? '');
    $tel     = trim($_POST['telephone'] ?? '');

    // Validation
    if (!$prenom || !$nom || !$email) {
        $error = tm('err_champs_req');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = tm('err_email_invalide2');
    } else {
        // Vérifier que l'email n'est pas déjà utilisé par un autre membre
        $check = $db->prepare("SELECT id FROM members WHERE email = ? AND id != ?");
        $check->execute([$email, $membre['id']]);
        if ($check->fetch()) {
            $error = tm('err_email_pris');
        } else {
            try {
                // Mettre à jour le membre
                $db->prepare("UPDATE members SET prenom=?, nom=?, email=?, commune=?, telephone=? WHERE id=?")
                   ->execute([$prenom, $nom, $email, $commune, $tel, $membre['id']]);

                // Mettre à jour aussi l'abonné newsletter si lié
                if ($membre['subscriber_id']) {
                    $db->prepare("UPDATE subscribers SET prenom=?, nom=?, email=?, adresse=?, commune=?, telephone=? WHERE id=?")
                       ->execute([$prenom, $nom, $email, $adresse, $commune, $tel, $membre['subscriber_id']]);
                }

                // Recharger le membre
                $stmt = $db->prepare("SELECT * FROM members WHERE id=?");
                $stmt->execute([$membre['id']]);
                $membre = $stmt->fetch();

                $success = tm('msg_profil_ok');

            } catch (Exception $e) {
                error_log('Profil update: ' . $e->getMessage());
                $error = tm('err_creation');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $LANG ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= tm('profil_page') ?></title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Helvetica Neue', Arial, sans-serif; background: #1a3a5c; min-height: 100vh; display: flex; align-items: flex-start; justify-content: center; padding: 40px 16px; }
    .card { background: #fff; border-radius: 16px; padding: 40px; max-width: 560px; width: 100%; box-shadow: 0 8px 40px rgba(0,0,0,.25); }
    .logo { text-align: center; margin-bottom: 28px; }
    .logo h1 { font-size: 1.6rem; color: #1673B2; }
    .logo h1 span { color: #F5A623; font-style: italic; }
    .logo p { color: #888; font-size: .85rem; margin-top: 4px; }

    .alert { padding: 12px 16px; border-radius: 8px; font-size: .88rem; margin-bottom: 20px; }
    .alert-ok  { background: #e8f8f0; color: #1a7a4a; border: 1px solid #b2f0d0; }
    .alert-err { background: #fff0f0; color: #c0392b; border: 1px solid #fca5a5; }

    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px; }
    .form-group { margin-bottom: 14px; }
    label { display: block; font-size: .8rem; font-weight: 600; color: #555; margin-bottom: 5px; text-transform: uppercase; letter-spacing: .04em; }
    input[type=text], input[type=email], input[type=tel] {
      width: 100%; padding: 10px 12px; border: 1.5px solid #ddd; border-radius: 8px;
      font-size: .95rem; font-family: inherit; transition: border-color .2s;
    }
    input:focus { outline: none; border-color: #1673B2; }

    .section-title { font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #1673B2; margin: 24px 0 14px; padding-bottom: 6px; border-bottom: 2px solid #e8f0fa; }

    .info-box { background: #f0f6ff; border-radius: 8px; padding: 12px 14px; font-size: .82rem; color: #1673B2; margin-bottom: 20px; }
    .info-box strong { display: block; margin-bottom: 3px; }

    .readonly-field { background: #f8f9fa; border: 1.5px solid #eee; color: #888; border-radius: 8px; padding: 10px 12px; font-size: .95rem; }

    button[type=submit] {
      width: 100%; padding: 13px; background: #1673B2; color: #fff; font-size: 1rem;
      font-weight: 700; border: none; border-radius: 10px; cursor: pointer; font-family: inherit;
      transition: background .2s; margin-top: 8px;
    }
    button[type=submit]:hover { background: #0e3d6b; }

    .links { text-align: center; margin-top: 20px; font-size: .85rem; }
    .links a { color: #1673B2; text-decoration: none; }
    .links a:hover { text-decoration: underline; }
    .links span { color: #ccc; margin: 0 8px; }

    @media(max-width:500px){ .form-row { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
<div class="card">

  <div class="logo">
    <h1>Ça suffit !</h1>
    <p><?= tm('profil_titre') ?></p>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-ok">✓ <?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-err">⚠ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- Infos non modifiables -->
  <div class="info-box">
    <strong><?= tm('info_membre', htmlspecialchars($membre['code_membre'] ?? '—')) ?></strong><br>
    <?= tm('cree_le', $membre['created_at'] ? date('d/m/Y', strtotime($membre['created_at'])) : '—') ?>
  </div>

  <form method="POST">
      <?= csrf_field() ?>

    <div class="section-title"><?= tm('identite') ?></div>

    <div class="form-row">
      <div class="form-group">
        <label><?= tm('prenom') ?></label>
        <input type="text" name="prenom" value="<?= htmlspecialchars($membre['prenom'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label><?= tm('nom') ?></label>
        <input type="text" name="nom" value="<?= htmlspecialchars($membre['nom'] ?? '') ?>" required>
      </div>
    </div>

    <div class="section-title"><?= tm('contact') ?></div>

    <div class="form-group">
      <label>Email *</label>
      <input type="email" name="email" value="<?= htmlspecialchars($membre['email'] ?? '') ?>" required>
      <p style="font-size:.75rem;color:#888;margin-top:4px"><?= tm('email_warn') ?></p>
    </div>

    <div class="form-group">
      <label>Téléphone</label>
      <input type="tel" name="telephone" value="<?= htmlspecialchars($membre['telephone'] ?? '') ?>" placeholder="<?= tm('telephone_ph') ?>">
    </div>

    <div class="section-title"><?= tm('adresse_section') ?></div>

    <div class="form-group">
      <label>Rue et numéro</label>
      <input type="text" name="adresse" value="<?= htmlspecialchars($membre['adresse'] ?? '') ?>" placeholder="<?= tm('adresse_ph') ?>">
    </div>

    <div class="form-group">
      <label>Commune</label>
      <input type="text" name="commune" value="<?= htmlspecialchars($membre['commune'] ?? '') ?>" placeholder="<?= tm('commune_ph') ?>">
    </div>

    <button type="submit"><?= tm('btn_enregistrer') ?></button>

  </form>

  <div class="links">
    <a href="dashboard.php"><?= tm('retour_espace') ?></a>
    <span>|</span>
    <a href="../index.php"><?= tm('retour_site') ?></a>
  </div>

</div>
</body>
</html>
