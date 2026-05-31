<?php
// outils-contenu.php — Insère l'actu + newsletter "fonds juridique / norme de vent" en brouillon
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

$nl_sujet = "Préparons-nous à agir : la norme de vent et notre fonds juridique";

$nl_html = <<<'HTML'
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

echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Création contenu</title>';
echo '<style>body{font-family:sans-serif;max-width:760px;margin:30px auto;padding:0 20px;line-height:1.6}.ok{color:#27ae60;font-weight:700}.err{color:#c0392b}code{background:#eef2f7;padding:2px 6px;border-radius:4px}a.btn{display:inline-block;background:#1673B2;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none;margin:6px 6px 0 0}</style></head><body>';
echo '<h2>📝 Création actualité + newsletter (brouillons)</h2>';

try {
    if (($_GET['apply'] ?? '') === '1') {
        // Actualité (brouillon)
        $chk = $db->prepare("SELECT id FROM news WHERE titre=? LIMIT 1");
        $chk->execute([$titre]);
        $ex = $chk->fetch();
        if ($ex) {
            echo '<p class=err>⚠ Une actu avec ce titre existe déjà (ID '.$ex['id'].'). Non recréée.</p>';
        } else {
            $cb = defined('ADMIN_USER') ? ADMIN_USER : ($_SESSION['admin_user'] ?? 'admin');
            $db->prepare("INSERT INTO news (titre,accroche,contenu,image_url,statut,epingle,date_publication,created_by) VALUES (?,?,?,'','brouillon',0,NOW(),?)")
               ->execute([$titre,$accroche,$contenu,$cb]);
            echo '<p class=ok>✅ Actualité créée en brouillon (ID '.$db->lastInsertId().')</p>';
        }
        // Newsletter (brouillon)
        $chkn = $db->prepare("SELECT id FROM newsletters WHERE sujet=? LIMIT 1");
        $chkn->execute([$nl_sujet]);
        $exn = $chkn->fetch();
        if ($exn) {
            echo '<p class=err>⚠ Une newsletter avec ce sujet existe déjà (ID '.$exn['id'].'). Non recréée.</p>';
        } else {
            $db->prepare("INSERT INTO newsletters (sujet, contenu_html, statut) VALUES (?,?,'brouillon')")
               ->execute([$nl_sujet,$nl_html]);
            echo '<p class=ok>✅ Newsletter créée en brouillon (ID '.$db->lastInsertId().')</p>';
        }
        echo '<p style="margin-top:18px"><a class=btn href="/admin/news.php">→ Voir les actualités</a> <a class=btn href="/admin/newsletters.php">→ Voir les newsletters</a></p>';
    } else {
        echo '<p>Va créer (en <strong>brouillon</strong>, à relire avant publication/envoi) :</p><ul>';
        echo '<li>1 <strong>actualité</strong> : « '.htmlspecialchars($titre).' »</li>';
        echo '<li>1 <strong>newsletter</strong> : « '.htmlspecialchars($nl_sujet).' »</li></ul>';
        echo '<p><a href="/outils-contenu.php?apply=1" style="background:#FF9900;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:700">⚙ Créer les brouillons</a></p>';
    }
} catch (Exception $e) {
    echo '<p class=err>❌ '.htmlspecialchars($e->getMessage()).'</p>';
}
echo '</body></html>';
