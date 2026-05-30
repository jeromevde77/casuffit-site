<?php
// Script usage unique — à supprimer après exécution
require_once __DIR__ . '/../config.php';
session_start(); requireAdmin();
$db = getDB();

$titre = "La norme de vent : nœud du problème aérien bruxellois";
$accroche = "Comment la manipulation des seuils de vent arrière depuis 2004 a bouleversé l'organisation des pistes à Brussels Airport — et pourquoi le retour au PRS 25/25 est la seule solution viable.";
$contenu = <<<HTML
<p class="news-intro" style="font-size:1.05rem;font-weight:500;color:#1673B2;border-left:4px solid #FF9900;padding-left:14px;margin-bottom:24px">La stabilisation des normes de vent est le nœud de tout le problème. Tant que cette question ne sera pas résolue, l'organisation du trafic aérien autour de Bruxelles restera source de conflits, de nuisances injustes et d'incertitude pour les riverains.</p>

<h3>Un aéroport qui fonctionnait bien — avant 2004</h3>
<p>Jusqu'en 2003, Brussels Airport fonctionnait sans accident ni incident majeur avec une norme stable et sécurisée : <strong>8 nœuds de composante de vent arrière, sans rafales</strong>. Cette norme avait été appliquée pendant 30 ans sans la moindre contestation.</p>
<p>En 2004, Bert Anciaux a compris qu'en abaissant artificiellement cette valeur, il pourrait reporter une partie du trafic vers d'autres pistes — dans le but de préserver le Noordrand, qu'il estimait trop survolé par les décollages 25R effectuant le virage à droite.</p>

<h3>Les pistes 25R/25L : construites pour absorber tout le trafic</h3>
<p>Depuis 1958, les pistes parallèles et indépendantes 25R/25L ont été spécifiquement conçues pour absorber le maximum du trafic aérien :</p>
<ul>
  <li>Ce sont les <strong>pistes les plus longues et les mieux équipées</strong> de l'aéroport</li>
  <li>Elles sont <strong>parallèles sans croisement au sol</strong>, ce qui évite tout conflit entre arrivées et départs</li>
  <li>À l'est des pistes 25R/L, entre Louvain et l'aéroport, une <strong>zone non constructible</strong> (<em>non aedificandi</em>) a été réservée pour créer un corridor aérien ne survolant que des champs et prairies</li>
</ul>

<h3>Le jeu des vases communicants</h3>
<p>C'est précisément pour éviter d'utiliser la 25R — pourtant la meilleure piste — que l'on fait appel à d'autres configurations. Et chaque piste alternative à l'atterrissage entraîne mécaniquement des décollages vers d'autres directions :</p>
<p>Pour ne pas survoler le Noordrand au décollage, on fait atterrir les avions sur Bruxelles Ouest, Bruxelles Sud, la périphérie Est et le Brabant Wallon — et les décollages repartent alors vers Kampenhout, Tildonk ou en direction de Louvain.</p>
<p>Pourtant, le Noordrand ne devrait pas se plaindre : les décollages 25R qui virent à droite sont répartis sur 4 trajectoires distinctes, et le week-end, l'une d'elles (la Chièvres) est déplacée vers le Canal. Le virage gauche de la 25R concentre le flux depuis Haren, Evere et Woluwe vers Wezembeek, avant de s'écarter en 5 trajectoires distinctes après la balise d'Huldenberg.</p>

<h3>Pourquoi la norme est déterminante</h3>
<p><strong>Plus cette norme est basse, instable ou mal appliquée, plus on changera de pistes en permanence</strong> — réduisant la capacité opérationnelle de l'aéroport et générant des conflits liés à l'utilisation de pistes qui se croisent au sol.</p>
<p>À l'inverse, une norme élevée et stable maintient le système préférentiel 25R/25L en fonctionnement — le système en fonction duquel tout le monde est venu s'installer dans un large cercle autour de l'aéroport.</p>

<h3>Notre position légale et technique</h3>
<p>Légalement, la composante de vent arrière peut être portée à <strong>10 nœuds</strong> (normes ICAO et FAA). La norme historique de 8 nœuds sans rafales, appliquée pendant 30 ans sans incident, ne prête à aucune interprétation ni contestation.</p>
<p><strong>Nous ne réclamons pas un transfert aléatoire du trafic de la 01 vers la 07.</strong> Nous défendons le retour aux conditions historiques d'emploi des pistes :</p>
<ul>
  <li><strong>25R/25L en préférentiel</strong> — chaque fois que le vent le permet</li>
  <li><strong>01</strong> par vent de Nord</li>
  <li><strong>07</strong> par vent d'Est</li>
  <li><strong>19</strong> par vent de Sud</li>
</ul>
<p>Il est par ailleurs établi que l'évolution climatique apporte de plus en plus de vent d'Est en alternative au vent dominant d'Ouest, et de moins en moins de vent de Nord. Cette diminution du vent de Nord n'est imputable à aucun facteur humain — les roses des vents le confirment.</p>

<h3>Conclusion : le retour au PRS 25/25</h3>
<p>Le retour au PRS 25/25 est la meilleure façon de ramener la sérénité dans le dossier de l'organisation des vols autour de Bruxelles — <strong>à condition que des mesures opérationnelles soient prises</strong> pour réduire l'impact sonore des décollages 25R :</p>
<ul>
  <li>Construction d'un mur antibruit</li>
  <li>Décollage depuis le seuil de piste</li>
  <li>Respect et contrôle des procédures de navigation</li>
  <li>Application correcte de l'altitude de virage et du taux de montée</li>
  <li>Poussée maximale des moteurs sur la piste</li>
  <li>Élimination progressive des avions cargos anciens et bruyants</li>
  <li>Réflexion sur de nouvelles procédures permettant de diminuer les nuisances pour tous</li>
</ul>
<p>Le trafic aérien de Bruxelles doit être remis au maximum sur les pistes 25 — pour des motifs de <strong>sécurité, de capacité opérationnelle et de respect des décisions de justice</strong>. Si et seulement si les mesures de vent sur les pistes 25R/L indiquent un dépassement réel de la composante de vent arrière, d'autres pistes devront être activées.</p>
HTML;

try {
    // Vérifier si déjà inséré
    $exists = $db->prepare("SELECT id FROM news WHERE titre=? LIMIT 1");
    $exists->execute([$titre]);
    if ($exists->fetch()) {
        echo '<p style="color:orange">⚠ Cette actualité existe déjà.</p>';
    } else {
        $db->prepare("INSERT INTO news (titre, accroche, contenu, statut, epingle, date_publication, date_creation)
                      VALUES (?, ?, ?, 'brouillon', 0, NOW(), NOW())")
           ->execute([$titre, $accroche, $contenu]);
        $id = $db->lastInsertId();
        echo '<p style="color:green;font-family:sans-serif;font-size:1.1rem">✅ Actualité créée en BROUILLON (ID: '.$id.')</p>';
        echo '<p style="font-family:sans-serif"><a href="news.php">→ Ouvrir dans l\'éditeur pour relire et publier</a></p>';
    }
} catch (Exception $e) {
    echo '<p style="color:red">Erreur : '.htmlspecialchars($e->getMessage()).'</p>';
}
?>
