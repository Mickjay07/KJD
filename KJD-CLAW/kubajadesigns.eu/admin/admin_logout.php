<?php
session_start();

// Zrušení všech session proměnných
$_SESSION = array();

// Zničení session
session_destroy();

// Přesměrování na přihlašovací stránku
header('Location: admin_login.php');
exit;
?> 