<?php
session_start();

// Décommenter pour redirection après débogage
// if (!isset($_SESSION['id_utilisateur'])) {
//     header('Location: ../cchic/login.php');
//     exit;
// }

require_once '../cchic/database.php';

// Pour le débogage, nous allons supposer que l'utilisateur avec ID 1 est l'administrateur
// En production, vous devriez remplacer ceci par la véritable vérification d'admin
$_SESSION['id_utilisateur'] = 1; // Temporaire, pour les tests

// Vérification du statut administrateur avec la colonne is_admin
try {
    $query = "SELECT is_admin FROM register WHERE id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_SESSION['id_utilisateur']]);
    $user = $stmt->fetch();
    
    // En mode débogage, nous forçons is_admin à 1
    $isAdmin = true; // Forcer l'accès administrateur pour les tests
    
    if (!$isAdmin) {
        // Redirection si l'utilisateur n'est pas administrateur
        // header('Location: ../cchic/index.php');
        // exit;
        echo "<div style='background-color: #ff6b6b; color: white; padding: 10px; margin: 20px;'>
              Vous n'êtes pas administrateur. Ce message est affiché uniquement en mode débogage.
              </div>";
    }
} catch(PDOException $e) {
    die("Erreur : " . $e->getMessage());
}

// Vérifier et créer les tables nécessaires si elles n'existent pas
try {
    // Vérifier si la table reports existe
    $reportsTableExists = $pdo->query("SHOW TABLES LIKE 'reports'")->rowCount() > 0;
    if (!$reportsTableExists) {
        // Créer la table reports
        $pdo->exec("CREATE TABLE IF NOT EXISTS reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            audio_id INT NOT NULL,
            user_id INT NOT NULL,
            motif TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (audio_id),
            INDEX (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }
    
    // Vérifier si la table admin_actions existe
    $actionsTableExists = $pdo->query("SHOW TABLES LIKE 'admin_actions'")->rowCount() > 0;
    if (!$actionsTableExists) {
        // Créer la table admin_actions
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
    }
} catch (PDOException $e) {
    echo '<div style="background-color: #ff6b6b; color: white; padding: 10px; margin: 20px; border-radius: 5px;">
          Erreur de création/vérification des tables : ' . htmlspecialchars($e->getMessage()) . '
          </div>';
}

// Créer le fichier ajax_handler_reports.php s'il n'existe pas
$ajax_handler_path = __DIR__ . '/ajax_handler_reports.php';
if (!file_exists($ajax_handler_path)) {
    $ajax_handler_content = '<?php
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

// Vérification de l\'authentification - à décommenter en production
// if (!isset($_SESSION["id_utilisateur"])) {
//     header("HTTP/1.1 401 Unauthorized");
//     echo json_encode(["error" => "Non authentifié"]);
//     exit;
// }

// Traitement selon l\'action demandée
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
            
            // 2. Insérer l\'action dans admin_actions
            $query = "INSERT INTO admin_actions (admin_id, action_entity, action_type, report_id) 
                     VALUES (?, \'report\', \'process\', ?)";
            $stmt = $pdo->prepare($query);
            $adminId = $_SESSION["id_utilisateur"] ?? 1; // Fallback pour les tests
            $result = $stmt->execute([$adminId, $input["report_id"]]);
            
            // 3. Si un audio_id est fourni, supprimer l\'audio associé
            if (isset($input["audio_id"]) && $input["audio_id"]) {
                // Vérifiez si la table audio existe avant de tenter de supprimer
                $audioTableExists = $pdo->query("SHOW TABLES LIKE \'audio\'")->rowCount() > 0;
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
            
            // 2. Insérer l\'action dans admin_actions
            $query = "INSERT INTO admin_actions (admin_id, action_entity, action_type, report_id) 
                     VALUES (?, \'report\', \'delete\', ?)";
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
';
    
    // Écrire le contenu dans le fichier
    file_put_contents($ajax_handler_path, $ajax_handler_content);
} 

class ReportsData {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }

    public function getReports($page = 1, $limit = 6) {
        try {
            // Vérifier si la table reports existe
            $reportsTableExists = $this->db->query("SHOW TABLES LIKE 'reports'")->rowCount() > 0;
            
            if (!$reportsTableExists) {
                return [
                    'reports' => [],
                    'pagination' => [
                        'current_page' => $page,
                        'total_pages' => 0,
                        'total_reports' => 0,
                        'limit' => $limit
                    ]
                ];
            }
            
            // Calculer l'offset pour la pagination
            $offset = ($page - 1) * $limit;
            
            // Requête pour récupérer le nombre total de rapports pour la pagination
            $countQuery = "SELECT COUNT(*) FROM reports";
            $totalReports = $this->db->query($countQuery)->fetchColumn();
            
            // Vérifier si la table admin_actions existe
            $actionsTableExists = $this->db->query("SHOW TABLES LIKE 'admin_actions'")->rowCount() > 0;
            
            if ($actionsTableExists) {
                // Version avec jointure sur admin_actions
                $query = "SELECT r.id, r.audio_id, r.motif, r.user_id, r.created_at, 
                         u.nom_prenoms as reporter_name,
                         CASE 
                             WHEN a.action_type = 'process' THEN 'process'
                             WHEN a.action_type = 'delete' THEN 'delete'
                             ELSE 'pending' 
                         END as status
                         FROM reports r
                         LEFT JOIN register u ON r.user_id = u.id
                         LEFT JOIN (
                             SELECT report_id, action_type 
                             FROM admin_actions 
                             WHERE action_entity = 'report'
                             GROUP BY report_id
                             ORDER BY created_at DESC
                         ) a ON a.report_id = r.id
                         ORDER BY r.created_at DESC
                         LIMIT :limit OFFSET :offset";
            } else {
                // Version simplifiée sans admin_actions
                $query = "SELECT r.id, r.audio_id, r.motif, r.user_id, r.created_at, 
                         u.nom_prenoms as reporter_name,
                         'pending' as status
                         FROM reports r
                         LEFT JOIN register u ON r.user_id = u.id
                         ORDER BY r.created_at DESC
                         LIMIT :limit OFFSET :offset";
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            if (!$stmt) {
                error_log("Erreur lors de l'exécution de la requête pour les rapports");
                return [
                    'reports' => [],
                    'pagination' => [
                        'current_page' => $page,
                        'total_pages' => 0,
                        'total_reports' => 0,
                        'limit' => $limit
                    ]
                ];
            }
            
            $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $formattedReports = [];
            foreach ($reports as $report) {
                // Créer un rapport formaté avec les données disponibles
                $formattedReport = [
                    'id' => $report['id'],
                    'user_id' => $report['user_id'],
                    'audio_id' => $report['audio_id'],
                    'type' => 'audio',
                    'content' => $report['motif'] ?? 'Aucun motif spécifié',
                    'date_report' => $report['created_at'],
                    'reporter_name' => $report['reporter_name'] ?? 'Utilisateur #' . $report['user_id'],
                    'reported_item' => 'Audio #' . $report['audio_id'],
                    'status' => $report['status'] ?? 'pending'
                ];
                
                // Tenter de récupérer le titre de l'audio séparément pour éviter les jointures qui pourraient échouer
                try {
                    $audioExists = $this->db->query("SHOW TABLES LIKE 'audio'")->rowCount() > 0;
                    if ($audioExists) {
                        $audioQuery = $this->db->prepare("SELECT titre FROM audio WHERE id = ?");
                        $audioQuery->execute([$report['audio_id']]);
                        $audio = $audioQuery->fetch(PDO::FETCH_ASSOC);
                        if ($audio && isset($audio['titre'])) {
                            $formattedReport['reported_item'] = $audio['titre'];
                        }
                    }
                } catch (PDOException $e) {
                    // Si erreur, garder la valeur par défaut
                    error_log("Erreur lors de la récupération du titre audio: " . $e->getMessage());
                }
                
                $formattedReports[] = $formattedReport;
            }
            
            // Calculer le nombre total de pages
            $totalPages = ceil($totalReports / $limit);
            
            return [
                'reports' => $formattedReports,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_reports' => $totalReports,
                    'limit' => $limit
                ]
            ];
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des rapports : " . $e->getMessage());
            if (isset($GLOBALS['DEBUG']) && $GLOBALS['DEBUG']) {
                echo "<div style='background-color: #ff6b6b; color: white; padding: 10px; margin: 20px;'>
                    Erreur de récupération des rapports : " . htmlspecialchars($e->getMessage()) . "
                    </div>";
            }
            return [
                'reports' => [],
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => 0,
                    'total_reports' => 0,
                    'limit' => $limit
                ]
            ];
        }
    }

    public function getReportStats() {
        $stats = [];
        
        try {
            // Vérifier si la table reports existe
            $reportsTableExists = $this->db->query("SHOW TABLES LIKE 'reports'")->rowCount() > 0;
            $actionsTableExists = $this->db->query("SHOW TABLES LIKE 'admin_actions'")->rowCount() > 0;
            
            if (!$reportsTableExists) {
                // Si la table n'existe pas, retourner des valeurs par défaut
                return [
                    'total_reports' => 0,
                    'unprocessed_reports' => 0,
                    'processed_reports' => 0,
                    'deleted_reports' => 0,
                    'new_reports_week' => 0
                ];
            }
            
            // Vérifier d'abord s'il y a des rapports dans la table
            $totalQuery = "SELECT COUNT(*) as total FROM reports";
            $stats['total_reports'] = $this->db->query($totalQuery)->fetchColumn();
            
            // Si aucun rapport, retourner tout à zéro
            if ($stats['total_reports'] == 0) {
                return [
                    'total_reports' => 0,
                    'unprocessed_reports' => 0,
                    'processed_reports' => 0,
                    'deleted_reports' => 0,
                    'new_reports_week' => 0
                ];
            }
            
            if ($actionsTableExists) {
                // Rapports traités (avec action_type = 'process')
                $queryProcessed = "SELECT COUNT(DISTINCT report_id) FROM admin_actions 
                                  WHERE action_entity = 'report' AND action_type = 'process'";
                $stats['processed_reports'] = $this->db->query($queryProcessed)->fetchColumn();
                
                // Rapports supprimés (avec action_type = 'delete')
                $queryDeleted = "SELECT COUNT(DISTINCT report_id) FROM admin_actions 
                                 WHERE action_entity = 'report' AND action_type = 'delete'";
                $stats['deleted_reports'] = $this->db->query($queryDeleted)->fetchColumn();
                
                // Compter directement les rapports qui n'ont pas été traités ni supprimés
                $queryUnprocessed = "SELECT COUNT(*) FROM reports r 
                                    WHERE NOT EXISTS (
                                        SELECT 1 FROM admin_actions a 
                                        WHERE a.report_id = r.id AND a.action_entity = 'report'
                                    )";
                $stats['unprocessed_reports'] = $this->db->query($queryUnprocessed)->fetchColumn();
                
                // Vérification supplémentaire pour s'assurer que les nombres sont cohérents
                if ($stats['processed_reports'] + $stats['deleted_reports'] + $stats['unprocessed_reports'] != $stats['total_reports']) {
                    // Si incohérence, recalculer pour s'assurer que tout concorde
                    $stats['unprocessed_reports'] = $stats['total_reports'] - $stats['processed_reports'] - $stats['deleted_reports'];
                    
                    // S'assurer que la valeur ne soit pas négative
                    if ($stats['unprocessed_reports'] < 0) {
                        $stats['unprocessed_reports'] = 0;
                    }
                }
            } else {
                // Si admin_actions n'existe pas, tous les rapports sont considérés comme non traités
                $stats['unprocessed_reports'] = $stats['total_reports'];
                $stats['processed_reports'] = 0;
                $stats['deleted_reports'] = 0;
            }
            
            // Nouveaux rapports cette semaine
            $query = "SELECT COUNT(*) FROM reports WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            $stats['new_reports_week'] = $this->db->query($query)->fetchColumn();
            
            return $stats;
            
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des statistiques de rapports : " . $e->getMessage());
            
            // En cas d'erreur, retourner des valeurs par défaut
            return [
                'total_reports' => 0,
                'unprocessed_reports' => 0,
                'processed_reports' => 0,
                'deleted_reports' => 0,
                'new_reports_week' => 0
            ];
        }
    }
}

// Initialisation de la classe ReportsData
$reportsData = new ReportsData($pdo);

// Récupération du numéro de page depuis l'URL
$reportPage = isset($_GET['report_page']) ? intval($_GET['report_page']) : 1;
if($reportPage < 1) $reportPage = 1;

// Récupération des données avec pagination
$reports = $reportsData->getReports($reportPage);
$reportStats = $reportsData->getReportStats();

// Contrôle du mode débogage - définir sur true pour voir les détails
$DEBUG = false; // Changer à true pour activer le débogage

// Fonction pour formater la date
function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}

// Afficher des informations de débogage sur les rapports
if ($DEBUG) {
    echo '<div style="background-color: #333; color: white; padding: 15px; margin: 15px; border-radius: 5px;">';
    echo '<h3>Informations de débogage</h3>';
    
    // Vérifier la structure de la table reports
    try {
        $tableInfo = $pdo->query("SHOW TABLES LIKE 'reports'");
        $reportsTableExists = $tableInfo->rowCount() > 0;
        
        echo '<p>Table reports existe: ' . ($reportsTableExists ? 'Oui' : 'Non') . '</p>';
        
        if ($reportsTableExists) {
            // Vérifier la structure
            $columns = $pdo->query("DESCRIBE reports")->fetchAll(PDO::FETCH_ASSOC);
            echo '<p>Structure de la table reports: <pre>' . print_r($columns, true) . '</pre></p>';
            
            // Compter les enregistrements
            $countReports = $pdo->query("SELECT COUNT(*) FROM reports")->fetchColumn();
            echo '<p>Nombre total de signalements: ' . $countReports . '</p>';
            
            // Vérifier les données brutes
            if ($countReports > 0) {
                $rawReports = $pdo->query("SELECT * FROM reports LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
                echo '<p>Exemples de signalements: <pre>' . print_r($rawReports, true) . '</pre></p>';
            }
        }
        
        // Vérifier la table admin_actions
        $tableInfo = $pdo->query("SHOW TABLES LIKE 'admin_actions'");
        $actionsTableExists = $tableInfo->rowCount() > 0;
        
        echo '<p>Table admin_actions existe: ' . ($actionsTableExists ? 'Oui' : 'Non') . '</p>';
        
        if ($actionsTableExists) {
            // Vérifier la structure
            $columns = $pdo->query("DESCRIBE admin_actions")->fetchAll(PDO::FETCH_ASSOC);
            echo '<p>Structure de la table admin_actions: <pre>' . print_r($columns, true) . '</pre></p>';
            
            // Compter les enregistrements
            $countActions = $pdo->query("SELECT COUNT(*) FROM admin_actions WHERE action_entity = 'report'")->fetchColumn();
            echo '<p>Nombre total d\'actions sur les signalements: ' . $countActions . '</p>';
        }
        
        // Afficher les données récupérées
        echo '<p>Données de rapports récupérées: <pre>' . print_r($reports, true) . '</pre></p>';
        echo '<p>Statistiques récupérées: <pre>' . print_r($reportStats, true) . '</pre></p>';
        
    } catch (PDOException $e) {
        echo '<p style="color: red;">Erreur lors de la vérification des tables: ' . $e->getMessage() . '</p>';
    }
    
    echo '</div>';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Signalements - Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <!-- Ajout de Chart.js pour les graphiques -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --primary-bg: #1e1e1e;
            --secondary-bg: #2c2c2c;
            --card-bg: #2d2d2d;
            --text-primary: #f0f0f0;
            --text-secondary: #b0b0b0;
            --accent-color: #ff7f00;
            --admin-red: #ef4444;
            --admin-green: #22c55e;
            --admin-blue: #3b82f6;
            --admin-yellow: #f59e0b;
            --separator-color: #4a4a4a;
            --border-radius-sm: 6px;
            --border-radius-md: 10px;
            --sidebar-width: 240px;
            --transition: all 0.3s ease;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background-color: var(--primary-bg);
            color: var(--text-primary);
            line-height: 1.6;
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar with hover effects */
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--secondary-bg);
            padding: 25px 0;
            display: flex;
            flex-direction: column;
            height: 100vh;
            position: fixed;
            border-right: 1px solid var(--separator-color);
            transition: var(--transition);
        }

        .sidebar-logo {
            padding: 0 25px 25px;
            margin-bottom: 20px;
            font-family: 'Montserrat', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            border-bottom: 1px solid var(--separator-color);
            display: flex;
            align-items: center;
            gap: 10px;
            transition: var(--transition);
        }

        .sidebar-logo:hover {
            color: var(--accent-color);
        }

        .sidebar-logo i {
            color: var(--accent-color);
            transition: var(--transition);
        }

        .sidebar-nav ul {
            list-style: none;
        }

        .sidebar-nav li a {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 25px;
            color: var(--text-secondary);
            text-decoration: none;
            border-left: 4px solid transparent;
            transition: var(--transition);
        }

        .sidebar-nav li a:hover {
            color: var(--text-primary);
            background-color: rgba(255, 255, 255, 0.05);
        }

        .sidebar-nav li a.active {
            background-color: rgba(255, 127, 0, 0.1);
            color: var(--accent-color);
            border-left-color: var(--accent-color);
        }

        .sidebar-nav li a i {
            transition: var(--transition);
        }

        .sidebar-nav li a:hover i {
            transform: scale(1.1);
        }

        /* Main content */
        .main-content {
            flex-grow: 1;
            padding: 25px;
            margin-left: var(--sidebar-width);
            transition: var(--transition);
        }

        /* Header with hover effects */
        .header {
            padding-bottom: 15px;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--separator-color);
        }

        .header h1 {
            font-family: 'Montserrat', sans-serif;
            font-size: 2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: var(--transition);
        }

        .header h1:hover {
            color: var(--accent-color);
        }

        .header h1 i {
            color: var(--accent-color);
            transition: var(--transition);
        }

        .header h1:hover i {
            transform: rotate(15deg);
        }

        /* Table wrapper with hover effect */
        .table-wrapper {
            background-color: var(--card-bg);
            border-radius: var(--border-radius-md);
            border: 1px solid var(--separator-color);
            margin-bottom: 15px;
            overflow-x: auto;
            transition: var(--transition);
            max-height: calc(100vh - 390px);
            overflow-y: auto;
        }

        .table-wrapper:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 8px 12px;
            text-align: left;
            border-bottom: 1px solid var(--separator-color);
            transition: var(--transition);
        }

        th {
            background-color: var(--secondary-bg);
            font-weight: 600;
        }

        tr:hover td {
            background-color: rgba(255, 255, 255, 0.03);
        }

        /* Status badges with hover effects */
        .status-badge {
            padding: 3px 8px;
            border-radius: var(--border-radius-sm);
            font-weight: 500;
            font-size: 0.85rem;
            transition: var(--transition);
            display: inline-block;
        }

        .status-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .status-badge.pending {
            background-color: rgba(245, 158, 11, 0.2);
            color: var(--admin-yellow);
        }
        .status-badge.processed {
            background-color: rgba(34, 197, 94, 0.2);
            color: var(--admin-green);
        }
        .status-badge.ignored {
            background-color: rgba(176, 176, 176, 0.2);
            color: var(--text-secondary);
        }

        /* Style pour les rapports traités */
        tr.processed {
            background-color: rgba(34, 197, 94, 0.05) !important;
        }
        
        tr.processed td {
            color: var(--text-secondary);
            text-decoration: line-through;
        }
        
        tr.processed .action-btn {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Action buttons with hover effects */
        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .action-btn {
            background: none;
            border: none;
            padding: 3px;
            cursor: pointer;
            color: var(--text-secondary);
            transition: var(--transition);
            border-radius: 50%;
            width: 26px;
            height: 26px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .action-btn:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: var(--accent-color);
            transform: scale(1.1);
        }
        .action-btn.delete:hover {
            background-color: rgba(239, 68, 68, 0.2);
            color: var(--admin-red);
        }
        .action-btn.success:hover {
            background-color: rgba(34, 197, 94, 0.2);
            color: var(--admin-green);
        }

        /* Footer with hover effect */
        .footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid var(--separator-color);
            transition: var(--transition);
        }

        .footer:hover {
            color: var(--text-primary);
        }

        /* Styles pour les statistiques */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stats-card {
            background-color: var(--card-bg);
            border-radius: var(--border-radius-md);
            padding: 15px;
            border: 1px solid var(--separator-color);
        }

        .stats-card h3 {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            color: var(--accent-color);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
        }

        .stat-item {
            text-align: center;
            padding: 8px;
            background-color: var(--secondary-bg);
            border-radius: var(--border-radius-sm);
        }

        .stat-label {
            display: block;
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 5px;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        /* Styles pour la pagination */
        .pagination {
            margin-top: 15px;
            margin-bottom: 15px;
            display: flex;
            justify-content: center;
        }

        .pagination-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            background-color: var(--card-bg);
            padding: 8px 15px;
            border-radius: var(--border-radius-sm);
            border: 1px solid var(--separator-color);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .pagination-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 35px;
            height: 35px;
            background-color: var(--secondary-bg);
            color: var(--text-primary);
            border-radius: var(--border-radius-sm);
            text-decoration: none;
            transition: var(--transition);
            font-weight: bold;
        }

        .pagination-btn:hover {
            background-color: var(--accent-color);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
        }
        
        .pagination-btn.active {
            background-color: var(--accent-color);
            color: white;
            box-shadow: 0 0 0 2px rgba(255, 127, 0, 0.3);
        }

        .pagination-info {
            color: var(--text-secondary);
            padding: 0 10px;
            font-size: 0.9rem;
        }
        
        .pagination-page-numbers {
            display: flex;
            gap: 5px;
        }

        /* Optimisations pour les signalements */
        tbody td:nth-child(4) {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .stat-value {
            font-size: 1.3rem;
        }

        /* Animations pour les notifications */
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }

        @media (min-height: 800px) {
            .table-wrapper {
                max-height: calc(100vh - 300px);
            }
        }
        
        @media (max-height: 700px) {
            .table-wrapper {
                max-height: calc(100vh - 250px);
            }
            
            .stats-container {
                margin-bottom: 10px;
            }
        }

        /* Styles pour les graphiques */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .chart-container {
            height: 180px;
            position: relative;
            margin-bottom: 10px;
        }
        
        .chart-legend {
            display: flex;
            justify-content: flex-start;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 10px;
            padding-left: 10px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.85rem;
        }
        
        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-logo">
            <i class="fas fa-headphones-alt"></i> C'chic
        </div>
        <ul class="sidebar-nav">
            <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Tableau de Bord</a></li>
            <li><a href="admin_gestions.php"><i class="fas fa-users"></i> Gestions</a></li>
            <li><a href="admin_signalements.php" class="active"><i class="fas fa-flag"></i> Signalements</a></li>
        </ul>
    </div>

    <div class="main-content">
        <header class="header">
            <h1><i class="fas fa-flag"></i> Gestion des Signalements</h1>
        </header>

        <div class="stats-container">
            <div class="stats-card">
                <h3><i class="fas fa-chart-pie"></i> Vue d'ensemble des signalements</h3>
                <div class="chart-container">
                    <canvas id="reports-chart"></canvas>
                </div>
                <div class="stats-grid">
                    <div class="stat-item">
                        <span class="stat-label">Total</span>
                        <span class="stat-value"><?php echo $reportStats['total_reports'] ?? 0; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Traités</span>
                        <span class="stat-value"><?php echo $reportStats['processed_reports'] ?? 0; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Supprimés</span>
                        <span class="stat-value"><?php echo $reportStats['deleted_reports'] ?? 0; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-wrapper">
            <table id="reports-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Élément Signalé</th>
                        <th>Type</th>
                        <th>Contenu</th>
                        <th>Signalé par</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($reports['reports'])): ?>
                        <tr>
                            <td colspan="7" style="text-align: center;">Aucun signalement trouvé</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($reports['reports'] as $report): ?>
                        <tr class="<?php echo ($report['status'] != 'pending') ? 'processed' : ''; ?>">
                            <td><?php echo htmlspecialchars($report['id'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($report['reported_item'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($report['type'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($report['content'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($report['reporter_name'] ?? 'N/A'); ?></td>
                            <td><?php echo isset($report['date_report']) ? formatDate($report['date_report']) : 'N/A'; ?></td>
                            <td>
                                <?php if($report['status'] == 'pending'): ?>
                                <div class="action-buttons">
                                    <button class="action-btn success" title="Traiter" data-id="<?php echo $report['id']; ?>" data-audio-id="<?php echo $report['audio_id'] ?? null; ?>" data-action="process">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button class="action-btn delete" title="Supprimer" data-id="<?php echo $report['id']; ?>" data-audio-id="<?php echo $report['audio_id'] ?? null; ?>" data-action="delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                                <?php else: ?>
                                <span class="status-badge <?php echo ($report['status'] == 'process') ? 'processed' : 'ignored'; ?>">
                                    <?php echo ($report['status'] == 'process') ? 'Traité' : 'Supprimé'; ?>
                                </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination pour les rapports -->
        <div class="pagination">
            <?php if ($reports['pagination']['total_pages'] > 1): ?>
                <div class="pagination-controls">
                    <?php if ($reports['pagination']['current_page'] > 1): ?>
                        <a href="?report_page=1" class="pagination-btn" title="Première page"><i class="fas fa-angle-double-left"></i></a>
                        <a href="?report_page=<?php echo $reports['pagination']['current_page'] - 1; ?>" class="pagination-btn" title="Page précédente"><i class="fas fa-angle-left"></i></a>
                    <?php else: ?>
                        <span class="pagination-btn" style="opacity: 0.5; cursor: not-allowed;"><i class="fas fa-angle-double-left"></i></span>
                        <span class="pagination-btn" style="opacity: 0.5; cursor: not-allowed;"><i class="fas fa-angle-left"></i></span>
                    <?php endif; ?>
                    
                    <div class="pagination-page-numbers">
                        <?php
                        $startPage = max(1, $reports['pagination']['current_page'] - 1);
                        $endPage = min($reports['pagination']['total_pages'], $startPage + 2);
                        if ($endPage - $startPage < 2) $startPage = max(1, $endPage - 2);
                        
                        for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                            <a href="?report_page=<?php echo $i; ?>" class="pagination-btn <?php echo ($i == $reports['pagination']['current_page']) ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                    
                    <span class="pagination-info">
                        Page <?php echo $reports['pagination']['current_page']; ?> sur <?php echo $reports['pagination']['total_pages']; ?>
                    </span>
                    
                    <?php if ($reports['pagination']['current_page'] < $reports['pagination']['total_pages']): ?>
                        <a href="?report_page=<?php echo $reports['pagination']['current_page'] + 1; ?>" class="pagination-btn" title="Page suivante"><i class="fas fa-angle-right"></i></a>
                        <a href="?report_page=<?php echo $reports['pagination']['total_pages']; ?>" class="pagination-btn" title="Dernière page"><i class="fas fa-angle-double-right"></i></a>
                    <?php else: ?>
                        <span class="pagination-btn" style="opacity: 0.5; cursor: not-allowed;"><i class="fas fa-angle-right"></i></span>
                        <span class="pagination-btn" style="opacity: 0.5; cursor: not-allowed;"><i class="fas fa-angle-double-right"></i></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <footer class="footer">
            <p>© 2025 - C'chic Administration - Tous droits réservés</p>
        </footer>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            console.log("Document loaded, initializing signalements...");
            
            // Initialisation du graphique
            initReportsChart();
            
            // Gestion des actions sur les signalements
            document.querySelectorAll('#reports-table .action-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const action = this.dataset.action;
                    const reportId = this.dataset.id;
                    const audioId = this.dataset.audioId;
                    const row = this.closest('tr');
                    
                    let confirmMessage = '';
                    if (action === 'process') {
                        confirmMessage = 'Êtes-vous sûr de vouloir traiter ce signalement ? L\'audio associé sera supprimé.';
                    } else if (action === 'delete') {
                        confirmMessage = 'Êtes-vous sûr de vouloir supprimer ce signalement ?';
                    }
                    
                    if (confirm(confirmMessage)) {
                        // Préparation des données
                        const requestData = {
                            action: action === 'process' ? 'process_report' : 'delete_report',
                            report_id: reportId
                        };
                        
                        if (audioId) {
                            requestData.audio_id = audioId;
                        }
                        
                        // Envoyer la requête AJAX
                        fetch('ajax_handler_reports.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify(requestData)
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`Erreur HTTP ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                // Ajouter la classe processed à la ligne
                                row.classList.add('processed');
                                
                                // Remplacer les boutons d'action par le badge de statut
                                const actionsCell = row.querySelector('.action-buttons').parentNode;
                                const statusBadge = document.createElement('span');
                                statusBadge.className = `status-badge ${action === 'process' ? 'processed' : 'ignored'}`;
                                statusBadge.textContent = action === 'process' ? 'Traité' : 'Supprimé';
                                actionsCell.innerHTML = '';
                                actionsCell.appendChild(statusBadge);
                                
                                // Mettre à jour les compteurs
                                updateReportCounters(action);
                                
                                showNotification(data.message || 'Action réussie !', 'success');
                            } else {
                                throw new Error(data.error || 'Erreur inconnue');
                            }
                        })
                        .catch(error => {
                            console.error('Erreur:', error);
                            showNotification(error.message || 'Une erreur est survenue lors du traitement de la demande', 'error');
                        });
                    }
                });
            });
        });

        /**
         * Initialisation du graphique des signalements
         */
        function initReportsChart() {
            const ctx = document.getElementById('reports-chart').getContext('2d');
            
            // Récupération des données
            const processedReports = <?php echo $reportStats['processed_reports'] ?? 0; ?>;
            const deletedReports = <?php echo $reportStats['deleted_reports'] ?? 0; ?>;
            const totalReports = <?php echo $reportStats['total_reports'] ?? 0; ?>;
            
            // Création du graphique
            window.reportsChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Total', 'Traités', 'Supprimés'],
                    datasets: [{
                        data: [totalReports, processedReports, deletedReports],
                        backgroundColor: [
                            'rgba(255, 165, 0, 0.7)',    // Orange pour total
                            'rgba(0, 0, 255, 0.7)',      // Bleu pour traités
                            'rgba(255, 0, 0, 0.7)'       // Rouge pour supprimés
                        ],
                        borderColor: [
                            'rgba(255, 165, 0, 1)',
                            'rgba(0, 0, 255, 1)',
                            'rgba(255, 0, 0, 1)'
                        ],
                        borderWidth: 1,
                        borderRadius: 6,
                        barThickness: 50,  // Augmentation de la largeur des barres
                        maxBarThickness: 60
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data[0]; // Total est le premier élément
                                    const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: 'rgba(255, 255, 255, 0.7)',
                                font: {
                                    size: 14,
                                    weight: 'bold'
                                }
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(255, 255, 255, 0.1)'
                            },
                            ticks: {
                                color: 'rgba(255, 255, 255, 0.7)',
                                font: {
                                    size: 14
                                }
                            }
                        }
                    }
                }
            });
        }

        /**
         * Met à jour les compteurs de statistiques des rapports sans recharger la page
         */
        function updateReportCounters(action) {
            // Récupérer les éléments HTML qui contiennent les compteurs
            const totalElement = document.querySelector('.stats-grid .stat-item:nth-child(1) .stat-value');
            const processedElement = document.querySelector('.stats-grid .stat-item:nth-child(2) .stat-value');
            const deletedElement = document.querySelector('.stats-grid .stat-item:nth-child(3) .stat-value');
            
            // Vérifier l'existence des éléments essentiels
            if (!totalElement || !processedElement || !deletedElement) {
                console.warn("Certains éléments de compteurs sont manquants");
                return;
            }
            
            // Extraire les valeurs actuelles
            const totalReports = parseInt(totalElement.textContent) || 0;
            const processedReports = parseInt(processedElement.textContent) || 0;
            const deletedReports = parseInt(deletedElement.textContent) || 0;
            
            // Mise à jour des compteurs selon l'action
            if (action === 'process') {
                // Augmenter les traités
                processedElement.textContent = processedReports + 1;
                
                // Mettre à jour le graphique
                if (window.reportsChart) {
                    window.reportsChart.data.datasets[0].data[1] += 1; // Augmenter traités
                    window.reportsChart.update();
                }
            } else if (action === 'delete') {
                // Augmenter les supprimés
                deletedElement.textContent = deletedReports + 1;
                
                // Mettre à jour le graphique
                if (window.reportsChart) {
                    window.reportsChart.data.datasets[0].data[2] += 1; // Augmenter supprimés
                    window.reportsChart.update();
                }
            }
        }

        /**
         * Fonction utilitaire pour afficher les notifications
         */
        function showNotification(message, type = 'success') {
            // Créer un élément de notification
            const notification = document.createElement('div');
            
            // Définir le style en fonction du type
            let bgColor = '#3b82f6'; // Bleu par défaut
            if (type === 'success') bgColor = '#22c55e'; // Vert pour succès
            if (type === 'error') bgColor = '#ef4444'; // Rouge pour erreur
            if (type === 'warning') bgColor = '#f59e0b'; // Jaune pour avertissement
            
            // Appliquer les styles
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background-color: ${bgColor};
                color: white;
                padding: 12px 20px;
                border-radius: 6px;
                z-index: 9999;
                box-shadow: 0 4px 8px rgba(0,0,0,0.2);
                max-width: 80%;
                animation: slideIn 0.3s forwards;
            `;
            
            // Ajouter le texte
            notification.textContent = message;
            
            // Ajouter au document
            document.body.appendChild(notification);
            
            // Supprimer après 3 secondes
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s forwards';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }
    </script>
</body>
</html> 