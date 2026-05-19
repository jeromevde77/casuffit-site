<?php
// sitemap.xml — Généré dynamiquement depuis la BDD
require_once __DIR__ . '/config.php';

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml">

  <!-- Accueil -->
  <url>
    <loc>https://www.casuffit.be/</loc>
    <changefreq>daily</changefreq>
    <priority>1.0</priority>
    <xhtml:link rel="alternate" hreflang="fr-BE" href="https://www.casuffit.be/"/>
    <xhtml:link rel="alternate" hreflang="nl-BE" href="https://www.casuffit.be/nl/"/>
  </url>

  <!-- App météo -->
  <url>
    <loc>https://www.casuffit.be/wind.php</loc>
    <changefreq>hourly</changefreq>
    <priority>0.8</priority>
  </url>

  <!-- Inscription membre -->
  <url>
    <loc>https://www.casuffit.be/membre/inscription.php</loc>
    <changefreq>monthly</changefreq>
    <priority>0.9</priority>
  </url>

<?php
// Pages dynamiques depuis la BDD
try {
    $db = getDB();
    $pages = $db->query("SELECT slug FROM pages WHERE visible=1 AND slug != '' AND lien_url='' ORDER BY ordre ASC")->fetchAll();
    foreach ($pages as $p) {
        $slug = htmlspecialchars($p['slug']);
        echo "  <url>\n";
        echo "    <loc>https://www.casuffit.be/?page={$slug}</loc>\n";
        echo "    <changefreq>weekly</changefreq>\n";
        echo "    <priority>0.7</priority>\n";
        echo "    <xhtml:link rel=\"alternate\" hreflang=\"fr-BE\" href=\"https://www.casuffit.be/?page={$slug}\"/>\n";
        echo "    <xhtml:link rel=\"alternate\" hreflang=\"nl-BE\" href=\"https://www.casuffit.be/nl/?page={$slug}\"/>\n";
        echo "  </url>\n";
    }

    // Actualités publiées
    $news = $db->query("SELECT id, updated_at FROM news WHERE statut='publie' ORDER BY id DESC LIMIT 100")->fetchAll();
    foreach ($news as $n) {
        $date = !empty($n['updated_at']) ? date('c', strtotime($n['updated_at'])) : date('c');
        echo "  <url>\n";
        echo "    <loc>https://www.casuffit.be/?news={$n['id']}</loc>\n";
        echo "    <lastmod>{$date}</lastmod>\n";
        echo "    <changefreq>monthly</changefreq>\n";
        echo "    <priority>0.6</priority>\n";
        echo "  </url>\n";
    }
} catch (Exception $e) {
    // Échec silencieux : sitemap minimal au moins servi
}
?>
</urlset>
