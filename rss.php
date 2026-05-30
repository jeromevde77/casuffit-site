<?php
// rss.php — Flux RSS des actualités casuffit.be
// Compatible Zapier, Make, lecteurs RSS, et agrégateurs Facebook
require_once __DIR__ . '/config.php';
$db = getDB();

$site_url  = 'https://www.casuffit.be';
$site_name = 'Ça suffit ! — Nuisances aériennes Brussels Airport';
$site_desc = 'Actualités du mouvement citoyen contre les nuisances aériennes de Brussels Airport (EBBR)';
$rss_url   = $site_url . '/rss.php';

// Langue (FR par défaut, NL via ?lang=nl)
$lang = ($_GET['lang'] ?? 'fr') === 'nl' ? 'nl' : 'fr';

try {
    $news = $db->query(
        "SELECT id, titre, titre_nl, accroche, accroche_nl,
                contenu, contenu_nl, image_url, date_publication, date_creation, statut
         FROM news
         WHERE statut = 'publie'
         ORDER BY date_publication DESC, date_creation DESC
         LIMIT 20"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $news = [];
}

// ── Helpers ──────────────────────────────────────────────────────────────
function rss_date(string $d): string {
    try { return (new DateTime($d))->format(DateTime::RSS); }
    catch (Exception $e) { return date(DateTime::RSS); }
}

function rss_esc(string $s): string {
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function strip_to_summary(string $html, int $max = 400): string {
    $text = strip_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/\s+/', ' ', trim($text));
    return mb_strlen($text) > $max ? mb_substr($text, 0, $max) . '…' : $text;
}

// ── Réponse ───────────────────────────────────────────────────────────────
header('Content-Type: application/rss+xml; charset=UTF-8');
header('Cache-Control: public, max-age=900'); // 15 min
header('X-Robots-Tag: noindex');

$build_date = $news ? rss_date($news[0]['date_publication'] ?: $news[0]['date_creation']) : date(DateTime::RSS);
$lang_label = $lang === 'nl' ? 'nl-BE' : 'fr-BE';
$site_desc_out = $lang === 'nl'
    ? 'Actualiteiten van de burgerbeweging tegen luchthinder van Brussels Airport (EBBR)'
    : $site_desc;

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0"
     xmlns:atom="http://www.w3.org/2005/Atom"
     xmlns:dc="http://purl.org/dc/elements/1.1/"
     xmlns:content="http://purl.org/rss/1.0/modules/content/">
  <channel>
    <title><?= rss_esc($site_name) ?></title>
    <link><?= rss_esc($site_url) ?></link>
    <description><?= rss_esc($site_desc_out) ?></description>
    <language><?= $lang_label ?></language>
    <lastBuildDate><?= $build_date ?></lastBuildDate>
    <ttl>15</ttl>
    <atom:link href="<?= rss_esc($rss_url . ($lang === 'nl' ? '?lang=nl' : '')) ?>"
               rel="self" type="application/rss+xml"/>
    <image>
      <url><?= rss_esc($site_url) ?>/assets/img/logo.png</url>
      <title><?= rss_esc($site_name) ?></title>
      <link><?= rss_esc($site_url) ?></link>
    </image>

<?php foreach ($news as $n):
    $titre   = ($lang === 'nl' && !empty($n['titre_nl']))    ? $n['titre_nl']    : $n['titre'];
    $accroche = ($lang === 'nl' && !empty($n['accroche_nl'])) ? $n['accroche_nl'] : $n['accroche'];
    $contenu  = ($lang === 'nl' && !empty($n['contenu_nl']))  ? $n['contenu_nl']  : $n['contenu'];

    $titre    = $titre ?: '(sans titre)';
    $pub_date = rss_date($n['date_publication'] ?: $n['date_creation']);
    $item_url = $site_url . '/?news=' . (int)$n['id'];

    // Description : accroche ou résumé du contenu
    $desc = $accroche ? strip_to_summary($accroche, 300) : strip_to_summary($contenu, 300);

    // GUID stable basé sur l'ID
    $guid = $site_url . '/news/' . (int)$n['id'];
?>
    <item>
      <title><?= rss_esc($titre) ?></title>
      <link><?= rss_esc($item_url) ?></link>
      <guid isPermaLink="false"><?= rss_esc($guid) ?></guid>
      <pubDate><?= $pub_date ?></pubDate>
      <dc:creator><?= rss_esc('Piste01 Ça Suffit ASBL') ?></dc:creator>
      <description><?= rss_esc($desc) ?></description>
<?php if ($contenu): ?>
      <content:encoded><![CDATA[<?= $contenu ?>]]></content:encoded>
<?php endif; ?>
<?php if ($n['image_url']): ?>
      <enclosure url="<?= rss_esc($n['image_url']) ?>" type="image/jpeg" length="0"/>
<?php endif; ?>
    </item>
<?php endforeach; ?>

  </channel>
</rss>
