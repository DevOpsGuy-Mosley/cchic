<?php
// Ce fichier doit être inclus au début de chaque page qui nécessite une connexion
require_once 'TokenManager.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si un token est présent dans le cookie
$token = $_COOKIE['auth_token'] ?? null;

if ($token) {
    // Valider le token
    $payload = TokenManager::validateToken($token);
    
    if ($payload) {
        // Token valide, mettre à jour la session
        $_SESSION['user_id'] = $payload['user_id'];
        // Rafraîchir le token si nécessaire
        if ($payload['exp'] - time() < 1800) { // Si moins de 30 minutes restantes
            $newToken = TokenManager::refreshToken($token);
            setcookie('auth_token', $newToken, [
                'expires' => time() + 3600,
                'path' => '/',
                'domain' => '',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
        }
    } else {
        // Token invalide, détruire la session
        session_unset();
        session_destroy();
        setcookie('auth_token', '', time() - 3600, '/', '', true, true);
        header('Location: login.php');
        exit;
    }
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
        setcookie('auth_token', '', time() - 3600, '/', '', true, true);
        
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