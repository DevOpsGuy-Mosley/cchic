<?php
session_start();
header('Content-Type: application/json');

// Vérification de la session
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Vous devez être connecté pour effectuer cette action']);
    exit();
}

require 'database.php';

try {
    if (!isset($_POST['audio_id'])) {
        throw new Exception('ID de l\'audio manquant');
    }

    $audioId = filter_var($_POST['audio_id'], FILTER_VALIDATE_INT);
    if ($audioId === false) {
        throw new Exception('ID de l\'audio invalide');
    }

    // Vérifier si l'utilisateur est propriétaire de l'audio
    $stmt = $pdo->prepare("
        SELECT a.* 
        FROM audio a 
        INNER JOIN register r ON a.nom_prenoms = r.nom_prenoms 
        WHERE a.id = :audio_id AND r.id = :user_id
    ");
    
    $stmt->execute([
        'audio_id' => $audioId,
        'user_id' => $_SESSION['user_id']
    ]);

    $audio = $stmt->fetch();

    if (!$audio) {
        throw new Exception('Vous n\'êtes pas autorisé à supprimer cet audio');
    }

    // Supprimer les réactions associées
    $stmt = $pdo->prepare("DELETE FROM reactions WHERE audio_id = ?");
    $stmt->execute([$audioId]);

    // Supprimer les commentaires associés
    $stmt = $pdo->prepare("DELETE FROM comments WHERE audio_id = ?");
    $stmt->execute([$audioId]);

    // Supprimer l'audio de la base de données
    $stmt = $pdo->prepare("DELETE FROM audio WHERE id = ?");
    $stmt->execute([$audioId]);

    // Supprimer le fichier audio s'il existe
    $audioFile = 'audio-uploads/' . $audioId . '.mp3';
    if (file_exists($audioFile)) {
        unlink($audioFile);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Audio supprimé avec succès'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 