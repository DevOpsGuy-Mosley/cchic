<?php
session_start();
require_once "../cchic/database.php";

// Vérification de la méthode de requête
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("HTTP/1.1 405 Method Not Allowed");
    echo json_encode(["error" => "Méthode non autorisée"]);
    exit;
}

// Récupération et décodage des données JSON
$inputJSON = file_get_contents("php://input");
$input = json_decode($inputJSON, true);

// Vérification de l'authentification - à décommenter en production
// if (!isset($_SESSION["id_utilisateur"])) {
//     header("HTTP/1.1 401 Unauthorized");
//     echo json_encode(["error" => "Non authentifié"]);
//     exit;
// }

// Traitement selon l'action demandée
if (!isset($input["action"])) {
    header("HTTP/1.1 400 Bad Request");
    echo json_encode(["error" => "Aucune action spécifiée"]);
    exit;
}

switch ($input["action"]) {
    // Actions concernant les rapports
    case "process_report":
        if (!isset($input["report_id"])) {
            echo json_encode(["error" => "ID du rapport manquant"]);
            exit;
        }
        
        try {
            // 1. Vérifier si la table admin_actions existe, sinon la créer
            $pdo->exec("CREATE TABLE IF NOT EXISTS admin_actions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                admin_id INT NOT NULL,
                action_entity VARCHAR(50) NOT NULL,
                action_type VARCHAR(50) NOT NULL,
                report_id INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                details TEXT,
                INDEX (action_entity),
                INDEX (action_type),
                INDEX (report_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            
            // 2. Insérer l'action dans admin_actions
            $query = "INSERT INTO admin_actions (admin_id, action_entity, action_type, report_id) 
                     VALUES (?, 'report', 'process', ?)";
            $stmt = $pdo->prepare($query);
            $adminId = $_SESSION["id_utilisateur"] ?? 1; // Fallback pour les tests
            $result = $stmt->execute([$adminId, $input["report_id"]]);
            
            // 3. Si un audio_id est fourni, supprimer l'audio associé
            if (isset($input["audio_id"]) && $input["audio_id"]) {
                // Vérifiez si la table audio existe avant de tenter de supprimer
                $audioTableExists = $pdo->query("SHOW TABLES LIKE 'audio'")->rowCount() > 0;
                if ($audioTableExists) {
                    $audioStmt = $pdo->prepare("DELETE FROM audio WHERE id = ?");
                    $audioStmt->execute([$input["audio_id"]]);
                }
            }
            
            if ($result) {
                echo json_encode([
                    "success" => true,
                    "message" => "Signalement traité avec succès"
                ]);
            } else {
                echo json_encode(["error" => "Échec du traitement du signalement"]);
            }
        } catch (PDOException $e) {
            echo json_encode(["error" => "Erreur de base de données: " . $e->getMessage()]);
        }
        break;
        
    case "delete_report":
        if (!isset($input["report_id"])) {
            echo json_encode(["error" => "ID du rapport manquant"]);
            exit;
        }
        
        try {
            // 1. Vérifier si la table admin_actions existe, sinon la créer
            $pdo->exec("CREATE TABLE IF NOT EXISTS admin_actions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                admin_id INT NOT NULL,
                action_entity VARCHAR(50) NOT NULL,
                action_type VARCHAR(50) NOT NULL,
                report_id INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                details TEXT,
                INDEX (action_entity),
                INDEX (action_type),
                INDEX (report_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            
            // 2. Insérer l'action dans admin_actions
            $query = "INSERT INTO admin_actions (admin_id, action_entity, action_type, report_id) 
                     VALUES (?, 'report', 'delete', ?)";
            $stmt = $pdo->prepare($query);
            $adminId = $_SESSION["id_utilisateur"] ?? 1; // Fallback pour les tests
            $result = $stmt->execute([$adminId, $input["report_id"]]);
            
            if ($result) {
                echo json_encode([
                    "success" => true,
                    "message" => "Signalement supprimé avec succès"
                ]);
            } else {
                echo json_encode(["error" => "Échec de la suppression du signalement"]);
            }
        } catch (PDOException $e) {
            echo json_encode(["error" => "Erreur de base de données: " . $e->getMessage()]);
        }
        break;
        
    default:
        echo json_encode(["error" => "Action non reconnue"]);
}
