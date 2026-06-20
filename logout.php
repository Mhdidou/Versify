<?php
session_start();
session_unset();
session_destroy();

// Supprimer le cookie "se souvenir de moi"
if (isset($_COOKIE['versify_remember'])) {
    setcookie('versify_remember', '', time() - 3600, '/', '', false, true);
}

header("Location: index.php");
die();
?>
