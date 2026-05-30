<?php
// includes/header.php — Header commun du site public
$page_active = isset($page_active) ? $page_active : '';
$logo_b64 = cfg('logo_b64', '');

// Récupérer les pages du menu avec parent_id et btn_style
try {
    $menu_pages = getDB()->query("
        SELECT slug, titre, icone, css_class, btn_style, parent_id, lien_url, affichage_menu
        FROM pages
        WHERE dans_menu=1 AND visible=1
        ORDER BY COALESCE(parent_id,0) ASC, ordre ASC
    ")->fetchAll();
} catch (Exception $e) { $menu_pages = array(); }

// Organiser en arbre : parents et enfants
$menu_parents  = array();
$menu_children = array(); // parent_slug => [enfants]
foreach ($menu_pages as $p) {
    if (!$p['parent_id']) {
        $menu_parents[] = $p;
    } else {
        // Chercher le slug du parent
        foreach ($menu_pages as $pp) {
            if ($pp['slug'] && !$pp['parent_id']) {
                // On indexe par parent_id (id numérique)
            }
        }
        $menu_children[$p['parent_id']][] = $p;
    }
}
// Indexer les parents par leur id BDD
try {
    $menu_all = getDB()->query("
        SELECT id, slug, titre, icone, css_class, btn_style, parent_id, lien_url, affichage_menu
        FROM pages WHERE dans_menu=1 AND visible=1
        ORDER BY COALESCE(parent_id,0) ASC, ordre ASC
    ")->fetchAll();
    $menu_by_id = array();
    foreach ($menu_all as $p) $menu_by_id[$p['id']] = $p;
    $menu_parents  = array();
    $menu_children = array();
    foreach ($menu_all as $p) {
        if (!$p['parent_id']) $menu_parents[] = $p;
        else $menu_children[$p['parent_id']][] = $p;
    }
} catch (Exception $e) { $menu_parents = $menu_pages; $menu_children = array(); }

// Fonction : rendu d'un lien de menu
function menuLink($p, $page_active, $extra_class='') {
    $href = $p['lien_url'] ? '/'.$p['lien_url'] : '/?page='.$p['slug'];
    $is_active = ($page_active === $p['slug']) ? ' active' : '';

    // Style du bouton
    $btn = '';
    switch ($p['btn_style'] ?? '') {
        case 'cta':     $btn = ' nav-cta'; break;
        case 'white':   $btn = ' nav-white'; break;
        case 'outline': $btn = ' nav-outline'; break;
    }
    $cls = trim($is_active . $btn . ' ' . ($p['css_class'] ?? '') . ' ' . $extra_class);

    // Affichage du label
    switch ($p['affichage_menu'] ?? 'texte') {
        case 'icone':       $label = $p['icone']; break;
        case 'icone_texte': $label = $p['icone'].' '.htmlspecialchars($p['titre']); break;
        default:            $label = htmlspecialchars($p['titre']); break;
    }

    return '<a href="'.htmlspecialchars($href).'" class="'.htmlspecialchars($cls).'">'.$label.'</a>';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= isset($page_titre) ? htmlspecialchars($page_titre).' — ' : '' ?><?= htmlspecialchars(cfg('site_nom', 'Piste01 Ça Suffit ASBL')) ?></title>
  <meta name="description" content="<?= isset($page_meta) ? htmlspecialchars($page_meta) : htmlspecialchars(cfg('site_slogan', '')) ?>">
  <meta name="theme-color" content="#1673B2">
  <style>
    /* ── Reset ─────────────────────────────────────────────────── */
    *{box-sizing:border-box;margin:0;padding:0}
    :root{
      --bleu:#1673B2; --bleu-fonce:#0e3d6b; --orange:#FF9900;
      --bleu-clair:#e6f1fb; --texte:#333; --gris:#888;
      --blanc:#fff; --fond:#f5f8fc;
    }
    body{font-family:"Helvetica Neue",Arial,sans-serif;background:var(--fond);color:var(--texte);line-height:1.6}
    a{color:var(--bleu);text-decoration:none}
    a:hover{text-decoration:underline}
    /* ── Header ────────────────────────────────────────────────── */
    .site-header{background:linear-gradient(135deg,var(--bleu-fonce) 0%,var(--bleu) 100%);position:sticky;top:0;z-index:100;box-shadow:0 2px 20px rgba(0,0,0,0.2)}
    .header-inner{max-width:1000px;margin:0 auto;padding:0 20px;display:flex;align-items:center;min-height:64px;gap:0}
    .logo-wrap{width:50px;height:50px;flex-shrink:0;margin-right:14px;filter:drop-shadow(0 2px 4px rgba(0,0,0,0.3))}
    .logo-wrap img{width:100%;height:100%;object-fit:contain;border-radius:50%}
    .logo-placeholder{width:50px;height:50px;border-radius:50%;background:rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#fff}
    .header-brand{margin-right:auto}
    .header-brand h1{color:#fff;font-size:1.05rem;font-weight:800;line-height:1.1}
    .header-brand h1 .orange{color:var(--orange);font-style:italic}
    .header-brand .tagline{color:rgba(255,255,255,0.65);font-size:0.68rem;letter-spacing:0.05em}
    .header-nav{display:flex;align-items:stretch;height:100%;gap:2px}
    .header-nav a{display:flex;align-items:center;padding:0 11px;color:rgba(255,255,255,0.80);font-size:0.75rem;font-weight:500;border-radius:5px;transition:all .2s;white-space:nowrap;text-decoration:none}
    .header-nav a:hover{color:#fff;background:rgba(255,255,255,0.12);text-decoration:none}
    .header-nav a.active{color:var(--orange);font-weight:700}
    .header-nav a.nav-cta{background:var(--orange);color:#fff;font-weight:700;border-radius:7px;margin-left:6px;padding:0 14px}
    .header-nav a.nav-cta:hover{background:#e68800;color:#fff}
    .header-nav a.nav-white{background:#fff;color:var(--bleu-fonce);font-weight:700;border-radius:7px;margin-left:6px;padding:0 14px;border:none}
    .header-nav a.nav-white:hover{background:#e6f1fb;color:var(--bleu-fonce)}
    .header-nav a.nav-outline{border:1.5px solid rgba(255,255,255,0.7);color:#fff;border-radius:7px;margin-left:6px;padding:0 13px}
    .header-nav a.nav-outline:hover{background:rgba(255,255,255,0.15);color:#fff}
    .header-nav a.nav-membre{border:1px solid rgba(255,255,255,0.3);color:rgba(255,255,255,0.85);border-radius:6px;padding:0 10px;margin-left:6px}
    .header-nav a.nav-admin{color:rgba(255,255,255,0.35);font-size:0.68rem;padding:0 6px}
    /* ── Sous-menus dropdown ─────────────────────────────────── */
    .nav-dropdown{position:relative;display:flex;align-items:stretch}
    .nav-dropdown > .nav-parent{cursor:pointer;display:flex;align-items:center;gap:4px;padding:0 11px;color:rgba(255,255,255,0.80);font-size:0.75rem;font-weight:500;border-radius:5px;transition:all .2s;white-space:nowrap;text-decoration:none}
    .nav-dropdown > .nav-parent::after{content:'▾';font-size:.6rem;opacity:.7;margin-left:2px}
    .nav-dropdown:hover > .nav-parent,.nav-dropdown > .nav-parent.active{color:#fff;background:rgba(255,255,255,0.12)}
    .nav-dropdown > .nav-parent.active{color:var(--orange);font-weight:700}
    .nav-submenu{display:none;position:absolute;top:calc(100% + 4px);left:0;background:#fff;border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,0.15);border:1px solid #e0e8f0;min-width:180px;padding:6px;z-index:200}
    .nav-dropdown:hover .nav-submenu{display:block}
    .nav-submenu a{display:flex;align-items:center;gap:8px;padding:7px 12px;color:var(--bleu-fonce);font-size:0.78rem;font-weight:500;border-radius:5px;transition:background .15s;white-space:nowrap;text-decoration:none}
    .nav-submenu a:hover{background:var(--bleu-clair);color:var(--bleu)}
    .nav-submenu a.active{color:var(--bleu);font-weight:700;background:var(--bleu-clair)}
    .nav-submenu-divider{height:1px;background:#f0f4f8;margin:4px 0}
    /* ── Annonce ───────────────────────────────────────────────── */
    .annonce-bar{background:linear-gradient(90deg,#0e3d6b,#1673B2);color:#fff;padding:9px 20px;text-align:center;position:relative;display:none}
    .annonce-bar.visible{display:block}
    .annonce-bar .annonce-inner{max-width:1000px;margin:0 auto;font-size:0.8rem;line-height:1.5}
    .annonce-bar strong{color:var(--orange)}
    .annonce-close{position:absolute;right:16px;top:50%;transform:translateY(-50%);background:none;border:none;color:rgba(255,255,255,0.5);cursor:pointer;font-size:1rem;padding:4px}
    .annonce-close:hover{color:#fff}
    /* ── Urgence ticker ────────────────────────────────────────── */
    .urgence{background:var(--orange);color:#fff;font-size:0.78rem;font-weight:600;padding:7px 0;overflow:hidden;white-space:nowrap}
    .urgence-inner{display:inline-block;animation:ticker 25s linear infinite;padding-left:100%}
    @keyframes ticker{from{transform:translateX(0)}to{transform:translateX(-100%)}}
    @media(max-width:768px){.header-nav{display:none}.header-inner{padding:0 14px}}
  </style>
</head>
<body>

<?php if (cfg('annonce_active') === '1'): ?>
<div class="annonce-bar visible" id="annonce-bar">
  <div class="annonce-inner">
    🤝 <strong><?= htmlspecialchars(cfg('annonce_titre')) ?></strong> — <?= htmlspecialchars(cfg('annonce_texte')) ?>
  </div>
  <button class="annonce-close" onclick="this.parentElement.style.display='none'">✕</button>
</div>
<?php endif; ?>

<header class="site-header">
  <div class="header-inner">
    <div class="logo-wrap">
      <?php if ($logo_b64): ?>
        <img src="data:image/png;base64,<?= $logo_b64 ?>" alt="<?= htmlspecialchars(cfg('site_nom')) ?>">
      <?php else: ?>
        <div class="logo-placeholder">CS!</div>
      <?php endif; ?>
    </div>
    <div class="header-brand">
      <h1><span class="orange">Ça suffit !</span> ASBL</h1>
      <div class="tagline"><?= htmlspecialchars(cfg('site_slogan', 'Piste 01 · UBCNA')) ?></div>
    </div>
    <nav class="header-nav">
      <?php foreach ($menu_parents as $p):
        $id = $p['id'];
        $children = isset($menu_children[$id]) ? $menu_children[$id] : array();
        if ($children):
          // Item avec sous-menu dropdown
          $parent_active = ($page_active === $p['slug']) ? ' active' : '';
          // Vérifier si un enfant est actif
          foreach ($children as $c) {
            if ($page_active === $c['slug']) { $parent_active = ' active'; break; }
          }
          $parent_label = $p['icone'] ? $p['icone'].' '.htmlspecialchars($p['titre']) : htmlspecialchars($p['titre']);
      ?>
      <div class="nav-dropdown">
        <span class="nav-parent<?= $parent_active ?>"><?= $parent_label ?></span>
        <div class="nav-submenu">
          <?php foreach ($children as $c): ?>
          <?= menuLink($c, $page_active) ?>
          <?php endforeach; ?>
        </div>
      </div>
      <?php else: ?>
      <?= menuLink($p, $page_active) ?>
      <?php endif; ?>
      <?php endforeach; ?>
      <a href="/?page=soutenir" class="nav-cta <?= $page_active === 'soutenir' ? 'active' : '' ?>">💶 Nous soutenir</a>
      <a href="/membre/login.php" class="nav-membre">👤 Mon espace</a>
      <a href="/admin/" class="nav-admin" title="Administration">⚙</a>
    </nav>
  </div>
</header>

<?php if (cfg('urgence_texte')): ?>
<div class="urgence"><div class="urgence-inner"><?= htmlspecialchars(cfg('urgence_texte')) ?></div></div>
<?php endif; ?>
