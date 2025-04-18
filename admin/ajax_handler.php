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
    // Actions concernant les utilisateurs
    case "update_user_status":
        if (!isset($input["user_id"]) || !isset($input["is_active"])) {
            echo json_encode(["error" => "Paramètres manquants"]);
            exit;
        }
        
        try {
            $query = "UPDATE register SET is_active = ? WHERE id = ?";
            $stmt = $pdo->prepare($query);
            $result = $stmt->execute([$input["is_active"], $input["user_id"]]);
            
            if ($result) {
                echo json_encode([
                    "success" => true,
                    "message" => "Statut utilisateur mis à jour"
                ]);
            } else {
                echo json_encode(["error" => "Échec de la mise à jour du statut"]);
            }
        } catch (PDOException $e) {
            echo json_encode(["error" => "Erreur de base de données: " . $e->getMessage()]);
        }
        break;
        
    case "ban_user":
        if (!isset($input["user_id"])) {
            echo json_encode(["error" => "ID utilisateur manquant"]);
            exit;
        }
        
        try {
            $query = "UPDATE register SET is_active = 2 WHERE id = ?";
            $stmt = $pdo->prepare($query);
            $result = $stmt->execute([$input["user_id"]]);
            
            if ($result) {
                echo json_encode([
                    "success" => true,
                    "message" => "Utilisateur banni avec succès"
                ]);
            } else {
                echo json_encode(["error" => "Échec du bannissement de l'utilisateur"]);
            }
        } catch (PDOException $e) {
            echo json_encode(["error" => "Erreur de base de données: " . $e->getMessage()]);
        }
        break;
        
    default:
        echo json_encode(["error" => "Action non reconnue"]);
}
