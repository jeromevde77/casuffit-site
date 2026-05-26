<?php
// v2 — sitemap enrichi : lastmod sur les pages, hreflang NL conditionnel, URLs news invalides retirées
// sitemap.xml — Généré dynamiquement depuis la BDD
// Routé via .htaccess : RewriteRule ^sitemap\.xml$ sitemap.php
require_once __DIR__ . '/config.php';

$base = 'https://www.casuffit.be';   // domaine canonique (cf. <link rel=canonical> dans index.php)

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";

function smesc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Émet un bloc <url>. $alts = ['fr-BE'=>url, 'nl-BE'=>url, 'x-default'=>url]
function smUrl($loc, $changefreq, $priority, $lastmod = null, $alts = array()) {
    echo "  <url>\n";
    echo "    <loc>" . smesc($loc) . "</loc>\n";
    if ($lastmod) echo "    <lastmod>" . smesc($lastmod) . "</lastmod>\n";
    echo "    <changefreq>{$changefreq}</changefreq>\n";
    echo "    <priority>{$priority}</priority>\n";
    foreach ($alts as $hl => $href) {
        echo "    <xhtml:link rel=\"alternate\" hreflang=\"" . smesc($hl) . "\" href=\"" . smesc($href) . "\"/>\n";
    }
    echo "  </url>\n";
}
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml">
<?php
// ── Accueil (FR + NL) ──────────────────────────────────────────────────
smUrl("$base/", 'daily', '1.0', null, array(
    'fr-BE'     => "$base/",
    'nl-BE'     => "$base/nl/",
    'x-default' => "$base/",
));

// ── Page d'action / flyers (URL courte mémorable) ──────────────────────
smUrl("$base/agir", 'monthly', '0.8', null, array(
    'fr-BE'     => "$base/agir",
    'nl-BE'     => "$base/doe-mee",
    'x-default' => "$base/agir",
));

// ── App météo / vent (PWA) ─────────────────────────────────────────────
smUrl("$base/wind.php", 'hourly', '0.8');

// ── Inscription membre ─────────────────────────────────────────────────
smUrl("$base/membre/inscription.php", 'monthly', '0.9');

// ── Politique de confidentialité ───────────────────────────────────────
smUrl("$base/politique-confidentialite.php", 'yearly', '0.3');

// ── Pages dynamiques (onglets adressables via ?page=slug) ──────────────
try {
    $db = getDB();
    $pages = $db->query("SELECT slug, titre_nl, nl_status, updated_at
                         FROM pages
                         WHERE visible=1 AND slug <> '' AND (lien_url IS NULL OR lien_url='')
                         ORDER BY ordre ASC")->fetchAll();
    foreach ($pages as $p) {
        $slug    = rawurlencode($p['slug']);
        $lastmod = !empty($p['updated_at']) ? date('c', strtotime($p['updated_at'])) : null;
        $has_nl  = !empty($p['titre_nl']) || (isset($p['nl_status']) && $p['nl_status'] !== 'vide');
        $alts = array(
            'fr-BE'     => "$base/?page=$slug",
            'x-default' => "$base/?page=$slug",
        );
        if ($has_nl) $alts['nl-BE'] = "$base/nl/?page=$slug";
        smUrl("$base/?page=$slug", 'weekly', '0.7', $lastmod, $alts);
    }
} catch (Exception $e) {
    // Échec silencieux : le sitemap statique reste servi
}
?>
</urlset>
