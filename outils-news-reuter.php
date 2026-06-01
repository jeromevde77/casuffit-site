<?php
require_once __DIR__ . '/config.php';
session_start();
if (empty($_SESSION['admin_logged_in'])) { header('Location: /admin/login.php'); exit; }
$db = getDB();

$titre    = "Reuter (Waterloo) : « Hors de question de tout renvoyer vers la Piste 01 »";
$accroche = "La députée-bourgmestre de Waterloo monte au créneau : le Brabant Wallon refuse de devenir la variable d'ajustement. Elle exige la concertation, la clarification des normes de vent et le maintien de la procédure PBN sur la Piste 07.";
$contenu  = <<<'HTML'
<p>Alors que les associations bruxelloises demandent une réduction du trafic sur la route d'atterrissage 07L, la députée-bourgmestre de Waterloo <strong>Florence Reuter (MR)</strong> tire la sonnette d'alarme : le Brabant Wallon refuse de devenir la variable d'ajustement.</p>

<p>Dans un article publié le 1er juin 2026 dans <em>La Dernière Heure</em>, elle déclare qu'il est hors de question de <em>"tout renvoyer vers la piste 01"</em>. Elle souligne que la piste 01, moins bien équipée sur le plan opérationnel, est déjà régulièrement critiquée pour ses limites et utilisée dans des conditions parfois contestées.</p>

<h2>Concertation et transparence</h2>
<p>Florence Reuter exige que les communes du Brabant Wallon soient <strong>systématiquement consultées</strong> lors de toute modification des normes de vent ou des trajectoires. Elle demande également que ces normes soient <strong>clarifiées et publiées</strong>, et que la procédure PBN sur la Piste 07 soit maintenue.</p>

<p>Elle souligne que la démarche doit inclure toutes les parties : <em>"On demande à être concerté, y compris avec Bruxelles"</em>. Une approche qui rejoint pleinement la position de Ça suffit ! : <strong>la solidarité entre riverains du nord et du sud est la seule voie efficace</strong>, face à un système qui oppose les quartiers au lieu de réduire le trafic.</p>

<h2>Le nœud : la norme de vent</h2>
<p>L'article rappelle que les normes de vent — modifiées il y a vingt ans sous Bert Anciaux — constituent le cadre légal flou qui permet ces basculements. L'État fédéral a déjà été condamné pour ne pas avoir clarifié les règles d'utilisation des pistes. Ce constat confirme notre analyse : <strong>la norme de vent est le vrai levier</strong>, et c'est là que doit porter l'action juridique.</p>

<p style="font-size:.85rem;color:#888;margin-top:20px"><em>Source : La Dernière Heure, 01/06/2026 · Photo : © Jean-Luc Flemal / DH</em></p>
HTML;

$titre_nl    = "Reuter (Waterloo): « Uitgesloten om alles naar Baan 01 terug te sturen »";
$accroche_nl = "De schepen-burgemeester van Waterloo slaat alarm: Brabant Wallon weigert de sluitpost te worden. Ze eist overleg, verduidelijking van de windnormen en handhaving van de PBN-procedure op Baan 07.";
$contenu_nl  = <<<'HTML'
<p>Terwijl Brusselse verenigingen vragen om een vermindering van het verkeer op landingsroute 07L, slaat de schepen-burgemeester van Waterloo <strong>Florence Reuter (MR)</strong> alarm: Brabant Wallon weigert de sluitpost te worden.</p>
<p>In een artikel gepubliceerd op 1 juni 2026 in <em>La Dernière Heure</em> verklaart ze dat het uitgesloten is om <em>"alles terug te sturen naar baan 01"</em>. Ze benadrukt dat de gemeenten van Brabant Wallon <strong>systematisch geconsulteerd</strong> moeten worden bij elke wijziging van windnormen of trajecten, dat die normen verduidelijkt en gepubliceerd moeten worden, en dat de PBN-procedure op Baan 07 gehandhaafd moet blijven.</p>
<p>Haar standpunt sluit volledig aan bij dat van Ça suffit !: <strong>solidariteit tussen omwonenden van noord en zuid is de enige doeltreffende weg</strong>, tegenover een systeem dat wijken tegen elkaar opzet.</p>
<p style="font-size:.85rem;color:#888;margin-top:20px"><em>Bron: La Dernière Heure, 01/06/2026 · Foto: © Jean-Luc Flemal / DH</em></p>
HTML;

$image_url = '/assets/img/dh-reuter-piste01.jpg';

header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Créer actu Reuter</title>';
echo '<style>body{font-family:sans-serif;max-width:700px;margin:30px auto;padding:0 20px}.ok{color:#27ae60;font-weight:700}.err{color:#c0392b}a.btn{display:inline-block;background:#1673B2;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none;margin-top:12px}</style></head><body>';
echo '<h2>📰 Créer l\'actu Florence Reuter / DH</h2>';

if (($_GET['apply'] ?? '') === '1') {
    try {
        $chk = $db->prepare("SELECT id FROM news WHERE titre=? LIMIT 1");
        $chk->execute([$titre]); $ex = $chk->fetch();
        if ($ex) {
            $db->prepare("UPDATE news SET accroche=?,contenu=?,image_url=?,titre_nl=?,accroche_nl=?,contenu_nl=?,nl_status='publie' WHERE id=?")
               ->execute([$accroche,$contenu,$image_url,$titre_nl,$accroche_nl,$contenu_nl,$ex['id']]);
            echo '<p class=ok>✅ Actu mise à jour (ID '.$ex['id'].')</p>';
        } else {
            $cb = defined('ADMIN_USER') ? ADMIN_USER : 'admin';
            $db->prepare("INSERT INTO news (titre,accroche,contenu,image_url,statut,epingle,date_publication,titre_nl,accroche_nl,contenu_nl,nl_status,created_by) VALUES (?,?,?,?,'brouillon',0,NOW(),?,?,?,'publie',?)")
               ->execute([$titre,$accroche,$contenu,$image_url,$titre_nl,$accroche_nl,$contenu_nl,$cb]);
            echo '<p class=ok>✅ Actu créée en brouillon (ID '.$db->lastInsertId().')</p>';
        }
        echo '<a class=btn href="/admin/news.php">→ Voir dans l\'admin</a>';
    } catch (Exception $e) { echo '<p class=err>❌ '.htmlspecialchars($e->getMessage()).'</p>'; }
} else {
    echo '<p>Titre : <strong>'.htmlspecialchars($titre).'</strong></p>';
    echo '<p>Image : <code>'.$image_url.'</code> (bilingue FR+NL)</p>';
    echo '<p><a href="/outils-news-reuter.php?apply=1" style="background:#FF9900;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:700">⚙ Créer le brouillon</a></p>';
}
echo '</body></html>';
