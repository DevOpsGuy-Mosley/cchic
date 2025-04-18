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
    // Vérifier si la table register existe
    $registerTableExists = $pdo->query("SHOW TABLES LIKE 'register'")->rowCount() > 0;
    if (!$registerTableExists) {
        // Créer la table register si elle n'existe pas
        $pdo->exec("CREATE TABLE IF NOT EXISTS register (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom_prenoms VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            is_active TINYINT DEFAULT 1,
            is_admin TINYINT DEFAULT 0,
            date_insertion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (email),
            INDEX (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }
} catch (PDOException $e) {
    echo '<div style="background-color: #ff6b6b; color: white; padding: 10px; margin: 20px; border-radius: 5px;">
          Erreur de création/vérification des tables : ' . htmlspecialchars($e->getMessage()) . '
          </div>';
}

// Créer le fichier ajax_handler.php s'il n'existe pas
$ajax_handler_path = __DIR__ . '/ajax_handler.php';
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
                echo json_encode(["error" => "Échec du bannissement de l\'utilisateur"]);
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

class AdminData {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function getUsers($page = 1, $limit = 10) {
        // Calculer l'offset pour la pagination
        $offset = ($page - 1) * $limit;
        
        // Requête pour récupérer le nombre total d'utilisateurs pour la pagination
        $countQuery = "SELECT COUNT(*) FROM register";
        $totalUsers = $this->db->query($countQuery)->fetchColumn();
        
        // Requête pour récupérer les utilisateurs avec pagination
        $query = "SELECT id, nom_prenoms as username, email, is_active as status, date_insertion, is_admin 
                 FROM register 
                 ORDER BY date_insertion DESC
                 LIMIT :limit OFFSET :offset";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Renommer is_admin en admin pour la compatibilité avec le reste du code
            // et date_insertion en created_at
            foreach($users as &$user) {
                $user['admin'] = $user['is_admin'];
                unset($user['is_admin']);
                $user['created_at'] = $user['date_insertion'];
                unset($user['date_insertion']);
            }
            
            // Calculer le nombre total de pages
            $totalPages = ceil($totalUsers / $limit);
            
            return [
                'users' => $users,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_users' => $totalUsers,
                    'limit' => $limit
                ]
            ];
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des utilisateurs : " . $e->getMessage());
            // Pour le débogage, afficher l'erreur
            echo "<div style='background-color: #ff6b6b; color: white; padding: 10px; margin: 20px;'>
                  Erreur de récupération des utilisateurs : " . htmlspecialchars($e->getMessage()) . "
                  </div>";
            return [
                'users' => [],
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => 0,
                    'total_users' => 0,
                    'limit' => $limit
                ]
            ];
        }
    }
    
    public function getUserStats() {
        $stats = [];
        
        // Total des utilisateurs
        $query = "SELECT COUNT(*) as total FROM register";
        try {
            $stats['total_users'] = $this->db->query($query)->fetchColumn();
        } catch (PDOException $e) {
            $stats['total_users'] = 0;
            // Pour le débogage, afficher l'erreur
            echo "<div style='background-color: #ff6b6b; color: white; padding: 10px; margin: 20px;'>
                  Erreur de comptage des utilisateurs : " . htmlspecialchars($e->getMessage()) . "
                  </div>";
        }
        
        // Nouveaux utilisateurs cette semaine
        $query = "SELECT COUNT(*) FROM register WHERE date_insertion >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        try {
            $stats['new_users_week'] = $this->db->query($query)->fetchColumn();
        } catch (PDOException $e) {
            $stats['new_users_week'] = 0;
            // Pour le débogage, afficher l'erreur
            echo "<div style='background-color: #ff6b6b; color: white; padding: 10px; margin: 20px;'>
                  Erreur de comptage des nouveaux utilisateurs : " . htmlspecialchars($e->getMessage()) . "
                  </div>";
        }
        
        return $stats;
    }
    
    public function updateUserStatus($userId, $status) {
        $query = "UPDATE register SET is_active = ? WHERE id = ?";
        try {
            $stmt = $this->db->prepare($query);
            return $stmt->execute([$status, $userId]);
        } catch (PDOException $e) {
            error_log("Erreur lors de la mise à jour du statut : " . $e->getMessage());
            return false;
        }
    }
}

// Initialisation de la classe AdminData
$adminData = new AdminData($pdo);

// Récupération du numéro de page depuis l'URL
$userPage = isset($_GET['user_page']) ? intval($_GET['user_page']) : 1;
if($userPage < 1) $userPage = 1;

// Récupération des données avec pagination
$users = $adminData->getUsers($userPage);
$userStats = $adminData->getUserStats();

// Contrôle du mode débogage - définir sur true pour voir les détails
$DEBUG = false; // Changer à true pour activer le débogage

// Fonction pour formater la date
function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}

// Initialisation du mode DEBUG
$DEBUG = false;

// Fonction d'aide pour le débogage
function debug_message($message, $type = 'info') {
    global $DEBUG;
    if (!$DEBUG) return;
    
    $bg_color = ($type == 'error') ? '#ff6b6b' : '#4e73df';
    echo "<div style='background-color: {$bg_color}; color: white; padding: 10px; margin: 20px;'>
          DEBUG: " . htmlspecialchars($message) . "
          </div>";
}

// Affichons la structure de la table register pour le débogage
if ($DEBUG) {
    try {
        $tableInfo = $pdo->query("DESCRIBE register");
        $columns = $tableInfo->fetchAll(PDO::FETCH_ASSOC);
        
        debug_message("Structure de la table register: " . json_encode($columns));
        
        // Comptons le nombre d'utilisateurs
        $countUsers = $pdo->query("SELECT COUNT(*) FROM register")->fetchColumn();
        debug_message("Nombre total d'utilisateurs: " . $countUsers);
        
    } catch (PDOException $e) {
        debug_message("Erreur lors de l'inspection de la table: " . $e->getMessage(), 'error');
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

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

        /* Filter bar with hover effects */
        .filter-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .filter-bar input,
        .filter-bar select {
            padding: 8px 12px;
            background-color: var(--card-bg);
            border: 1px solid var(--separator-color);
            border-radius: var(--border-radius-sm);
            color: var(--text-primary);
            transition: var(--transition);
        }

        .filter-bar input:focus,
        .filter-bar select:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 2px rgba(255, 127, 0, 0.2);
        }

        .filter-bar button {
            padding: 8px 15px;
            background-color: var(--accent-color);
            border: none;
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
        }

        .filter-bar button:hover {
            background-color: var(--accent-hover);
            transform: translateY(-2px);
        }

        .filter-bar button i {
            transition: var(--transition);
        }

        .filter-bar button:hover i {
            transform: scale(1.1);
        }

        /* Import button */
        .import-btn {
            padding: 8px 15px;
            background-color: var(--admin-blue);
            border: none;
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            margin-left: auto;
        }

        .import-btn:hover {
            background-color: #2563eb;
            transform: translateY(-2px);
        }

        .import-btn i {
            transition: var(--transition);
        }

        .import-btn:hover i {
            transform: scale(1.1);
        }

        /* Hidden file input */
        .file-input {
            display: none;
        }

        /* Table wrapper with hover effect */
        .table-wrapper {
            background-color: var(--card-bg);
            border-radius: var(--border-radius-md);
            border: 1px solid var(--separator-color);
            margin-bottom: 15px;
            overflow-x: auto;
            transition: var(--transition);
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

        .status-badge.active {
            background-color: rgba(34, 197, 94, 0.2);
            color: var(--admin-green);
        }
        .status-badge.inactive {
            background-color: rgba(176, 176, 176, 0.2);
            color: var(--text-secondary);
        }
        .status-badge.banned {
            background-color: rgba(239, 68, 68, 0.2);
            color: var(--admin-red);
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
        }

        .pagination-btn:hover {
            background-color: var(--accent-color);
            color: white;
            transform: translateY(-2px);
        }

        .pagination-info {
            color: var(--text-secondary);
            padding: 0 10px;
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

        /* Ajout du style pour le badge administrateur */
        .admin-badge {
            color: var(--accent-color);
            margin-left: 5px;
        }

        .admin-badge i {
            font-size: 0.8em;
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
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-logo">
            <i class="fas fa-headphones-alt"></i> C'chic
        </div>
        <ul class="sidebar-nav">
            <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Tableau de Bord</a></li>
            <li><a href="admin_gestions.php" class="active"><i class="fas fa-users"></i> Gestions</a></li>
            <li><a href="admin_signalements.php"><i class="fas fa-flag"></i> Signalements</a></li>
        </ul>
    </div>

    <div class="main-content">
        <header class="header">
            <h1><i class="fas fa-users"></i> Gestion des Utilisateurs</h1>
        </header>

        <div class="stats-container">
            <div class="stats-card">
                <h3><i class="fas fa-users"></i> Statistiques Utilisateurs</h3>
                <div class="stats-grid">
                    <div class="stat-item">
                        <span class="stat-label">Total Utilisateurs</span>
                        <span class="stat-value"><?php echo $userStats['total_users']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Nouveaux (7 jours)</span>
                        <span class="stat-value"><?php echo $userStats['new_users_week']; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="filter-bar">
            <input type="text" id="user-search" placeholder="Rechercher...">
            <select id="user-status-filter">
                <option value="">Tous les statuts</option>
                <option value="active">Actif</option>
                <option value="inactive">Inactif</option>
                <option value="banned">Banni</option>
            </select>
            <button type="button" id="filter-users-btn"><i class="fas fa-filter"></i> Filtrer</button>
        </div>

        <div class="table-wrapper">
            <table id="users-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nom d'utilisateur</th>
                        <th>Email</th>
                        <th>Statut</th>
                        <th>Inscrit le</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($users['users'])): ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">Aucun utilisateur trouvé</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($users['users'] as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['id']); ?></td>
                            <td>
                                <strong>
                                    <?php echo htmlspecialchars($user['username']); ?>
                                    <?php if($user['admin'] == 1): ?>
                                        <span class="admin-badge"><i class="fas fa-crown" title="Administrateur"></i></span>
                                    <?php endif; ?>
                                </strong>
                            </td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="status-badge 
                                    <?php if($user['status'] == 1): ?>active
                                    <?php elseif($user['status'] == 2): ?>banned
                                    <?php else: ?>inactive<?php endif; ?>">
                                    <?php 
                                    if($user['status'] == 1) echo 'Actif';
                                    elseif($user['status'] == 2) echo 'Banni';
                                    else echo 'Inactif';
                                    ?>
                                </span>
                            </td>
                            <td><?php echo formatDate($user['created_at']); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <?php if($user['admin'] != 1): // Ne pas afficher les actions pour les administrateurs ?>
                                        <?php if($user['status'] == 2): // Utilisateur banni ?>
                                            <button class="action-btn success" title="Débannir" data-id="<?php echo $user['id']; ?>" data-action="unban">
                                                <i class="fas fa-user-check"></i>
                                            </button>
                                        <?php elseif($user['status'] == 1): // Utilisateur actif ?>
                                            <button class="action-btn warning" title="Bannir" data-id="<?php echo $user['id']; ?>" data-action="ban">
                                                <i class="fas fa-gavel"></i>
                                            </button>
                                            <button class="action-btn delete" title="Désactiver" data-id="<?php echo $user['id']; ?>" data-action="deactivate">
                                                <i class="fas fa-user-slash"></i>
                                            </button>
                                        <?php else: // Utilisateur inactif ?>
                                            <button class="action-btn success" title="Activer" data-id="<?php echo $user['id']; ?>" data-action="activate">
                                                <i class="fas fa-user-check"></i>
                                            </button>
                                            <button class="action-btn warning" title="Bannir" data-id="<?php echo $user['id']; ?>" data-action="ban">
                                                <i class="fas fa-gavel"></i>
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination pour les utilisateurs -->
        <div class="pagination">
            <?php if ($users['pagination']['total_pages'] > 1): ?>
                <div class="pagination-controls">
                    <?php if ($users['pagination']['current_page'] > 1): ?>
                        <a href="?user_page=1" class="pagination-btn">&laquo;</a>
                        <a href="?user_page=<?php echo $users['pagination']['current_page'] - 1; ?>" class="pagination-btn">&lsaquo;</a>
                    <?php endif; ?>
                    
                    <span class="pagination-info">
                        Page <?php echo $users['pagination']['current_page']; ?> sur <?php echo $users['pagination']['total_pages']; ?>
                    </span>
                    
                    <?php if ($users['pagination']['current_page'] < $users['pagination']['total_pages']): ?>
                        <a href="?user_page=<?php echo $users['pagination']['current_page'] + 1; ?>" class="pagination-btn">&rsaquo;</a>
                        <a href="?user_page=<?php echo $users['pagination']['total_pages']; ?>" class="pagination-btn">&raquo;</a>
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
            console.log("Document loaded, initializing...");

            // Users tab functionality
            document.getElementById('filter-users-btn')?.addEventListener('click', filterUsers);

            // Action buttons pour les utilisateurs
            document.querySelectorAll('#users-table .action-btn').forEach(btn => {
                btn.addEventListener('click', async function() {
                    const userId = this.dataset.id;
                    const action = this.dataset.action;
                    let confirmMessage, actionType, newStatus;
                    
                    // Déterminer l'action
                    switch(action) {
                        case 'activate':
                            confirmMessage = 'Voulez-vous activer cet utilisateur ?';
                            actionType = 'update_user_status';
                            newStatus = 1;
                            break;
                        case 'deactivate':
                            confirmMessage = 'Voulez-vous désactiver cet utilisateur ?';
                            actionType = 'update_user_status';
                            newStatus = 0;
                            break;
                        case 'ban':
                            confirmMessage = 'Voulez-vous bannir cet utilisateur ?';
                            actionType = 'ban_user';
                            break;
                        case 'unban':
                            confirmMessage = 'Voulez-vous débannir cet utilisateur ?';
                            actionType = 'update_user_status';
                            newStatus = 1;
                            break;
                        default:
                            return;
                    }
                    
                    if (confirm(confirmMessage)) {
                        try {
                            const requestData = {
                                action: actionType,
                                user_id: userId
                            };
                            
                            if (newStatus !== undefined) {
                                requestData.is_active = newStatus;
                            }
                            
                            const response = await fetch('ajax_handler.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify(requestData)
                            });
                            
                            const data = await response.json();
                            
                            if (response.ok && data.success) {
                                showNotification(data.message || 'Action réussie !', 'success');
                                location.reload();
                            } else {
                                throw new Error(data.error || 'Erreur lors du traitement');
                            }
                        } catch (error) {
                            console.error('Erreur:', error);
                            showNotification(error.message || 'Une erreur est survenue', 'error');
                        }
                    }
                });
            });
        });

        function filterUsers() {
            const searchTerm = document.getElementById('user-search').value.toLowerCase();
            const statusFilter = document.getElementById('user-status-filter').value;
            
            document.querySelectorAll('#users-table tbody tr').forEach(row => {
                const username = row.querySelector('td:nth-child(2) strong').textContent.toLowerCase();
                const email = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
                const status = row.querySelector('.status-badge').className.includes('active') ? 'active' : 'inactive';
                
                const matchesSearch = !searchTerm || username.includes(searchTerm) || email.includes(searchTerm);
                const matchesStatus = !statusFilter || status === statusFilter;
                
                row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
            });
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