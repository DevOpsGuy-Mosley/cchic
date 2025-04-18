<?php
// Ce fichier doit être inclus au début de chaque page qui nécessite une connexion
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    // L'utilisateur n'est pas connecté, rediriger vers la page de connexion
    header('Location: login.php');
    exit;
}

// Vérifier si l'utilisateur est toujours actif dans la base de données
require_once 'database.php';
try {
    $stmt = $pdo->prepare("SELECT is_active FROM register WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    // Si l'utilisateur n'existe pas ou n'est pas actif (supprimé, banni, etc.)
    if (!$user || $user['is_active'] != 1) {
        // Détruire la session
        session_unset();
        session_destroy();
        
        // Rediriger vers la page de connexion avec un message d'erreur
        $_SESSION['error_message'] = "Votre session a expiré ou votre compte a été désactivé.";
        header('Location: login.php');
        exit;
    }
} catch (PDOException $e) {
    // En cas d'erreur de base de données, on continue
    error_log("Erreur lors de la vérification de la session : " . $e->getMessage());
}
?> 