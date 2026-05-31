<?php
// outils-contenu.php — Insère/MAJ actu (FR+NL) + newsletters FR & NL "fonds juridique / norme de vent"
require_once __DIR__ . '/config.php';
session_start();
if (empty($_SESSION['admin_logged_in'])) {
    echo '<p style="font-family:sans-serif;max-width:600px;margin:40px auto">⚠ Connecte-toi à l\'admin. <a href="/admin/login.php">→ Login</a> puis reviens ici.</p>';
    exit;
}
header('Content-Type: text/html; charset=utf-8');
$db = getDB();

$titre = "Norme de vent : préparons-nous à agir";
$accroche = "Le vrai nœud du problème aérien bruxellois, c'est la norme de vent. Face aux décisions qui s'annoncent, nous lançons un appel aux dons pour constituer un fonds d'action juridique et pouvoir réagir vite — solidaires de tous les riverains.";

$contenu = <<<'HTML'
<p>La situation aérienne autour de Brussels Airport est en train de bouger vite — et nous devons être prêts.</p>
<h2>La norme de vent, cœur du problème</h2>
<p>Au cœur du problème, il y a une question technique aux conséquences énormes : <strong>la norme de vent</strong>. C'est elle qui détermine quelles pistes sont utilisées, et pendant combien de temps. En assouplissant ses seuils, les autorités peuvent justifier l'usage intensif de telle ou telle trajectoire — et déplacer les nuisances d'un quartier à l'autre, sans jamais réduire le trafic global. C'est le véritable nœud du problème aérien bruxellois, celui dont on parle trop peu.</p>
<h2>La RNP 07 fait partie de l'équation</h2>
<p>La route <strong>RNP 07</strong>, aujourd'hui contestée par les communes du nord de Bruxelles, fait partie de cette équation. <strong>Nous sommes pleinement solidaires de ces riverains et de leurs associations</strong> : nous menons le même combat, contre le même système qui oppose les habitants les uns aux autres au lieu de s'attaquer à la cause réelle. Notre adversaire, ce ne sont pas les voisins du nord ou de l'ouest — c'est une gestion qui se contente de redistribuer le bruit au gré de normes opaques.</p>
<h2>Refuser d'être la variable d'ajustement</h2>
<p>Nous connaissons le mécanisme des <strong>vases communicants</strong> : quand une trajectoire est modifiée quelque part, le trafic ne disparaît pas — il se reporte ailleurs. Et selon la manière dont la norme de vent est appliquée, cet « ailleurs » peut devenir, du jour au lendemain, le ciel au-dessus de la <strong>Piste 01</strong>. Nous refusons d'être la variable d'ajustement d'un système qui ne règle rien.</p>
<h2>Une échéance proche : la réunion Skeyes du 15 juin</h2>
<p>Le 15 juin, <strong>Skeyes</strong> réunit l'ensemble des associations de riverains. Des orientations importantes sur les trajectoires et leur usage pourraient y être présentées. Nous y serons, attentifs à tout ce qui touche à la norme de vent et à la Piste 01 — car c'est à ces occasions que se préparent les décisions qui nous concernent ensuite directement.</p>
<h2>Un fonds d'action juridique</h2>
<p>C'est pourquoi nous lançons dès aujourd'hui un <strong>appel aux dons pour constituer un fonds d'action juridique</strong>. L'objectif est simple : disposer des moyens nécessaires pour saisir la justice <strong>immédiatement</strong> si une décision — sur la norme de vent ou sur les trajectoires — venait aggraver les nuisances au-dessus de nos quartiers. Une procédure solide se prépare en amont ; on ne la monte pas en quelques jours. En donnant maintenant, vous nous donnez la capacité d'agir au bon moment.</p>
<p><strong>Chaque don compte.</strong> C'est notre capacité collective à nous défendre, à temps et avec un dossier sérieux, qui se joue. Préparons-nous ensemble — aux côtés de tous les riverains de Brussels Airport, du nord au sud.</p>
<p style="text-align:center;margin:28px 0"><a href="/don" style="display:inline-block;background:#FF9900;color:#fff;padding:14px 28px;border-radius:8px;text-decoration:none;font-weight:700;font-size:1.05rem">👉 Je soutiens le fonds d'action juridique</a></p>
HTML;

$titre_nl = "Windnorm: laten we ons voorbereiden om te handelen";
$accroche_nl = "De echte kern van het Brusselse luchtvaartprobleem is de windnorm. Met het oog op de beslissingen die eraan komen, lanceren we een oproep tot giften om een juridisch actiefonds op te bouwen en snel te kunnen reageren — solidair met alle omwonenden.";

$contenu_nl = <<<'HTML'
<p>De luchtvaartsituatie rond Brussels Airport evolueert snel — en we moeten klaarstaan.</p>
<h2>De windnorm, de kern van het probleem</h2>
<p>De kern van het probleem is een technische kwestie met enorme gevolgen: <strong>de windnorm</strong>. Die bepaalt welke banen worden gebruikt, en hoe lang. Door de drempels te versoepelen kunnen de autoriteiten het intensieve gebruik van deze of gene route rechtvaardigen — en zo de hinder van de ene wijk naar de andere verschuiven, zonder ooit het totale verkeer te verminderen. Dat is de echte kern van het Brusselse luchtvaartprobleem, waarover veel te weinig wordt gesproken.</p>
<h2>De RNP 07 maakt deel uit van de vergelijking</h2>
<p>De route <strong>RNP 07</strong>, die vandaag wordt aangevochten door de gemeenten in het noorden van Brussel, maakt deel uit van deze vergelijking. <strong>Wij zijn volledig solidair met deze omwonenden en hun verenigingen</strong>: wij voeren dezelfde strijd, tegen hetzelfde systeem dat bewoners tegen elkaar opzet in plaats van de echte oorzaak aan te pakken. Onze tegenstander zijn niet de buren in het noorden of het westen — het is een beheer dat zich beperkt tot het herverdelen van het lawaai volgens ondoorzichtige normen.</p>
<h2>Weigeren de sluitpost te zijn</h2>
<p>We kennen het mechanisme van de <strong>communicerende vaten</strong>: wanneer ergens een route wordt gewijzigd, verdwijnt het verkeer niet — het verschuift naar elders. En afhankelijk van hoe de windnorm wordt toegepast, kan dat "elders" van de ene dag op de andere de hemel boven <strong>Baan 01</strong> worden. Wij weigeren de sluitpost te zijn van een systeem dat niets oplost.</p>
<h2>Een nabije deadline: de Skeyes-vergadering van 15 juni</h2>
<p>Op 15 juni brengt <strong>Skeyes</strong> alle bewonersverenigingen samen. Er kunnen belangrijke richtlijnen over de routes en hun gebruik worden voorgesteld. Wij zullen aanwezig zijn, alert op alles wat de windnorm en Baan 01 aanbelangt — want het is bij zulke gelegenheden dat de beslissingen worden voorbereid die ons daarna rechtstreeks treffen.</p>
<h2>Een juridisch actiefonds</h2>
<p>Daarom lanceren we vandaag een <strong>oproep tot giften om een juridisch actiefonds op te bouwen</strong>. Het doel is eenvoudig: over de middelen beschikken om <strong>onmiddellijk</strong> naar de rechter te stappen als een beslissing — over de windnorm of de routes — de hinder boven onze wijken zou verergeren. Een sterke procedure wordt vooraf voorbereid; je zet ze niet in enkele dagen op. Door nu te geven, geeft u ons de mogelijkheid om op het juiste moment te handelen.</p>
<p><strong>Elke gift telt.</strong> Het is ons collectieve vermogen om ons op tijd en met een degelijk dossier te verdedigen dat op het spel staat. Laten we ons samen voorbereiden — aan de zijde van alle omwonenden van Brussels Airport, van noord tot zuid.</p>
<p style="text-align:center;margin:28px 0"><a href="/don?lang=nl" style="display:inline-block;background:#FF9900;color:#fff;padding:14px 28px;border-radius:8px;text-decoration:none;font-weight:700;font-size:1.05rem">👉 Ik steun het juridisch actiefonds</a></p>
HTML;

$nl_sujet_fr = "Préparons-nous à agir : la norme de vent et notre fonds juridique";
$nl_html_fr = <<<'HTML'
<p>Chère riveraine, cher riverain,</p>
<p>La situation aérienne autour de Brussels Airport bouge vite, et nous devons être prêts à réagir.</p>
<p><strong>Le vrai nœud du problème, c'est la norme de vent.</strong> C'est elle qui décide quelles pistes sont utilisées et pendant combien de temps. En assouplissant ses seuils, on peut déplacer les nuisances d'un quartier à l'autre — sans jamais réduire le trafic. La route RNP 07, contestée au nord de Bruxelles, fait partie de cette même équation.</p>
<p><strong>Nous sommes solidaires de tous les riverains</strong>, du nord au sud. Notre adversaire n'est pas le quartier voisin, mais un système qui redistribue le bruit au lieu de le réduire. Or, par le jeu des vases communicants, ce qui se décide ailleurs peut intensifier les survols au-dessus de la Piste 01 du jour au lendemain.</p>
<p><strong>Échéance importante :</strong> le 15 juin, Skeyes réunit les associations de riverains. Des orientations décisives pourraient y être présentées. Nous y serons.</p>
<p>Pour pouvoir <strong>saisir la justice immédiatement</strong> si une décision aggrave la situation, nous constituons un <strong>fonds d'action juridique</strong>. Une procédure sérieuse se prépare à l'avance. En donnant aujourd'hui, vous nous donnez les moyens d'agir au bon moment.</p>
<p style="text-align:center;margin:28px 0"><a href="https://www.casuffit.be/don" style="display:inline-block;background:#FF9900;color:#fff;padding:14px 28px;border-radius:8px;text-decoration:none;font-weight:700">👉 Je soutiens le fonds d'action juridique</a></p>
<p>Merci pour votre soutien et votre mobilisation.</p>
<p><em>Ça suffit !</em></p>
HTML;

$nl_sujet_nl = "Laten we ons voorbereiden om te handelen: de windnorm en ons juridisch fonds";
$nl_html_nl = <<<'HTML'
<p>Beste omwonende,</p>
<p>De luchtvaartsituatie rond Brussels Airport evolueert snel, en we moeten klaarstaan om te reageren.</p>
<p><strong>De echte kern van het probleem is de windnorm.</strong> Die bepaalt welke banen worden gebruikt en hoe lang. Door de drempels te versoepelen kan men de hinder van de ene wijk naar de andere verschuiven — zonder ooit het verkeer te verminderen. De route RNP 07, aangevochten in het noorden van Brussel, maakt deel uit van diezelfde vergelijking.</p>
<p><strong>Wij zijn solidair met alle omwonenden</strong>, van noord tot zuid. Onze tegenstander is niet de buurwijk, maar een systeem dat het lawaai herverdeelt in plaats van het te verminderen. Door het spel van de communicerende vaten kan wat elders wordt beslist, van de ene dag op de andere de overvluchten boven Baan 01 intensiveren.</p>
<p><strong>Belangrijke deadline:</strong> op 15 juni brengt Skeyes de bewonersverenigingen samen. Er kunnen beslissende richtlijnen worden voorgesteld. Wij zullen aanwezig zijn.</p>
<p>Om <strong>onmiddellijk naar de rechter te kunnen stappen</strong> als een beslissing de situatie verergert, bouwen we een <strong>juridisch actiefonds</strong> op. Een degelijke procedure wordt op voorhand voorbereid. Door vandaag te geven, geeft u ons de middelen om op het juiste moment te handelen.</p>
<p style="text-align:center;margin:28px 0"><a href="https://www.casuffit.be/don?lang=nl" style="display:inline-block;background:#FF9900;color:#fff;padding:14px 28px;border-radius:8px;text-decoration:none;font-weight:700">👉 Ik steun het juridisch actiefonds</a></p>
<p>Bedankt voor uw steun en mobilisatie.</p>
<p><em>Genoeg!</em></p>
HTML;

echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Création contenu</title>';
echo '<style>body{font-family:sans-serif;max-width:760px;margin:30px auto;padding:0 20px;line-height:1.6}.ok{color:#27ae60;font-weight:700}.err{color:#c0392b}code{background:#eef2f7;padding:2px 6px;border-radius:4px}a.btn{display:inline-block;background:#1673B2;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none;margin:6px 6px 0 0}</style></head><body>';
echo '<h2>📝 Création contenu FR + NL (brouillons)</h2>';

try {
    if (($_GET['apply'] ?? '') === '1') {
        // Actualité : créer ou compléter avec NL
        $chk = $db->prepare("SELECT id FROM news WHERE titre=? LIMIT 1");
        $chk->execute([$titre]);
        $ex = $chk->fetch();
        if ($ex) {
            $db->prepare("UPDATE news SET accroche=?, contenu=?, titre_nl=?, accroche_nl=?, contenu_nl=?, nl_status='publie' WHERE id=?")
               ->execute([$accroche,$contenu,$titre_nl,$accroche_nl,$contenu_nl,$ex['id']]);
            echo '<p class=ok>✅ Actualité existante (ID '.$ex['id'].') complétée avec le contenu NL</p>';
        } else {
            $cb = defined('ADMIN_USER') ? ADMIN_USER : ($_SESSION['admin_user'] ?? 'admin');
            $db->prepare("INSERT INTO news (titre,accroche,contenu,image_url,statut,epingle,date_publication,titre_nl,accroche_nl,contenu_nl,nl_status,created_by) VALUES (?,?,?,'','brouillon',0,NOW(),?,?,?,'publie',?)")
               ->execute([$titre,$accroche,$contenu,$titre_nl,$accroche_nl,$contenu_nl,$cb]);
            echo '<p class=ok>✅ Actualité créée en brouillon FR+NL (ID '.$db->lastInsertId().')</p>';
        }
        // Newsletter FR
        foreach ([[$nl_sujet_fr,$nl_html_fr,'FR'],[$nl_sujet_nl,$nl_html_nl,'NL']] as $n) {
            $c = $db->prepare("SELECT id FROM newsletters WHERE sujet=? LIMIT 1"); $c->execute([$n[0]]); $e = $c->fetch();
            if ($e) { echo '<p class=err>⚠ Newsletter '.$n[2].' déjà existante (ID '.$e['id'].')</p>'; }
            else {
                $db->prepare("INSERT INTO newsletters (sujet, contenu_html, statut) VALUES (?,?,'brouillon')")->execute([$n[0],$n[1]]);
                echo '<p class=ok>✅ Newsletter '.$n[2].' créée en brouillon (ID '.$db->lastInsertId().')</p>';
            }
        }
        echo '<p style="margin-top:18px"><a class=btn href="/admin/news.php">→ Actualités</a> <a class=btn href="/admin/newsletters.php">→ Newsletters</a></p>';
    } else {
        echo '<p>Va créer/compléter (en <strong>brouillon</strong>) :</p><ul>';
        echo '<li>1 <strong>actualité bilingue</strong> (FR + NL) : « '.htmlspecialchars($titre).' »</li>';
        echo '<li>1 <strong>newsletter FR</strong> + 1 <strong>newsletter NL</strong></li></ul>';
        echo '<p><a href="/outils-contenu.php?apply=1" style="background:#FF9900;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:700">⚙ Créer les brouillons</a></p>';
    }
} catch (Exception $e) {
    echo '<p class=err>❌ '.htmlspecialchars($e->getMessage()).'</p>';
}
echo '</body></html>';
