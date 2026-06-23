<?php
session_start();

// Invalider le jeton "se souvenir de moi" cote serveur ET cote navigateur
if (isset($_COOKIE['versify_remember'])) {
    try {
        require_once __DIR__ . '/config.php';
        require_once __DIR__ . '/_auth.php';
        auth_remember_oublier($pdo);
    } catch (Exception $e) { /* on continue la deconnexion malgre tout */ }
}

session_unset();
session_destroy();

header("Location: index.php");
die();
?>
