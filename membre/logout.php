<?php
// membre/logout.php
session_start();
unset($_SESSION['membre_id'], $_SESSION['membre_email']);
session_destroy();
header('Location: login.php');
exit;
