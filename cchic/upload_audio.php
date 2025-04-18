<?php
// Assurez-vous que les sessions sont bien démarrées en haut du fichier
session_start();
require 'database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Utilisateur non connecté']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['audio'])) {
    $uploadsDir = "audio-uploads/";
    // Répertoire où enregistrer les fichiers audio
    if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0777, true);
    // Crée le répertoire s'il n'existe pas

    // Vérifier si le fichier a été correctement uploadé
    if ($_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'upload: ' . $_FILES['audio']['error']]);
        exit;
    }

    // Récupérer les informations de l'utilisateur
    $stmt = $pdo->prepare("SELECT nom_prenoms FROM register WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Utilisateur non trouvé']);
        exit;
    }

    // Générer un nom de fichier unique avec l'extension .mp3
    $fileName = uniqid('audio_') . '.mp3';
    $filePath = $uploadsDir . $fileName;

    // Récupérer le titre et les informations utilisateur
    $title = isset($_POST['title']) ? trim($_POST['title']) : 'Sans titre';
    $user_id = $_SESSION['user_id'];
    $nom_prenoms = $user['nom_prenoms'];

    try {
        // Déplacer le fichier uploadé
        if (move_uploaded_file($_FILES['audio']['tmp_name'], $filePath)) {
            // S'assurer que le fichier a les bonnes permissions
            chmod($filePath, 0644);

            // Modifier la requête pour ne pas inclure photo_profil
            $stmt = $pdo->prepare("
                INSERT INTO audio (user_id, nom_prenoms, title, notevocale, datenote) 
                VALUES (:user_id, :nom_prenoms, :title, :notevocale, NOW())
            ");
            
            // Utiliser le chemin relatif pour l'accès web
            $webPath = 'audio-uploads/' . $fileName;
            
            $result = $stmt->execute([
                'user_id' => $user_id,
                'nom_prenoms' => $nom_prenoms,
                'title' => $title,
                'notevocale' => $webPath
            ]);

            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Audio enregistré avec succès',
                    'audio_path' => $webPath
                ]);
            } else {
                unlink($filePath);
                echo json_encode([
                    'success' => false,
                    'message' => 'Erreur lors de l\'enregistrement dans la base de données'
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors du déplacement du fichier'
            ]);
        }
    } catch (Exception $e) {
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        echo json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Requête invalide ou fichier audio manquant'
    ]);
}
?>