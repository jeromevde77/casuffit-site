<?php
// agir.php — Redirige vers la page admin-éditable /?page=agir
// L'URL courte casuffit.be/agir est préservée, le contenu est géré dans l'admin.
// Les paramètres UTM sont transmis pour le tracking.

$qs = '';
$params = [];
if (!empty($_GET['utm_source']))   $params[] = 'utm_source='   . urlencode($_GET['utm_source']);
if (!empty($_GET['utm_campaign'])) $params[] = 'utm_campaign=' . urlencode($_GET['utm_campaign']);
if (!empty($_GET['lang']))         $params[] = 'lang='          . urlencode($_GET['lang']);
if ($params) $qs = '&' . implode('&', $params);

header('Location: /?page=agir' . $qs, true, 301);
exit;
