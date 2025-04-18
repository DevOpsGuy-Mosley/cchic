<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start(); // Démarre une nouvelle session ou reprend une session existante
}
if (isset($_SESSION["user_id"] )) {
    session_unset(); // Détruit toutes les variables de session
    session_destroy(); // Détruit la session
    header("Location: index.php"); // Redirige vers la page d'accueil
}
?>