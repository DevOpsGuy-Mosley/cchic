<?php
// Démarrer la session
session_start();

// Inclure les fichiers nécessaires
require_once '../cchic/database.php';
require_once 'includes/admin_stats.php';
require_once 'includes/admin_data.php';

// Vérification de la session admin (à adapter selon votre système)
if (!isset($_SESSION['id_utilisateur'])) {
    // Décommenter cette ligne en production
    // header('Location: ../cchic/login.php');
    // exit;
    
    // Pour les tests, on force un ID utilisateur
    $_SESSION['id_utilisateur'] = 1;
}

// Créer les instances des classes pour accéder aux données
$adminStats = new AdminStats($pdo);
$adminData = new AdminData($pdo);

// Récupérer les statistiques
$userStats = $adminStats->getUserStats();
$reportStats = $adminStats->getReportStats();
$globalStats = $adminData->getGlobalStats();

// Récupération de tous les signalements historiques (y compris traités/supprimés)
try {
    $allHistoricalReports = 0;
    $reportsToday = 0;
    
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'reports'");
    if ($tableCheck->rowCount() > 0) {
        // Vérifier si la table admin_logs existe pour les signalements traités/supprimés
        $logsTableCheck = $pdo->query("SHOW TABLES LIKE 'admin_logs'");
        if ($logsTableCheck->rowCount() > 0) {
            // Compter les signalements traités dans les logs
            $logQuery = "SELECT COUNT(*) FROM admin_logs 
                         WHERE action_type IN ('process_report', 'delete_report')";
            $processedReports = $pdo->query($logQuery)->fetchColumn();
            
            // Compter les signalements actuels
            $currentReports = $pdo->query("SELECT COUNT(*) FROM reports")->fetchColumn();
            
            // Total historique = actuels + traités
            $allHistoricalReports = $currentReports + $processedReports;
        } else {
            // Si pas de table logs, utiliser juste le total actuel
            $allHistoricalReports = $pdo->query("SELECT COUNT(*) FROM reports")->fetchColumn();
        }
        
        // Récupérer les signalements des dernières 24h
        $columnCheck = $pdo->query("SHOW COLUMNS FROM reports LIKE 'created_at'");
        if ($columnCheck->rowCount() > 0) {
            $todayQuery = "SELECT COUNT(*) FROM reports 
                          WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
            $reportsToday = $pdo->query($todayQuery)->fetchColumn();
        }
    }
} catch (PDOException $e) {
    $allHistoricalReports = 0;
    $reportsToday = 0;
}

// Récupération du nombre total d'audios pour l'aperçu
try {
    $totalAudios = 0;
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'audio'");
    if ($tableCheck->rowCount() > 0) {
        $totalAudios = $pdo->query("SELECT COUNT(*) FROM audio")->fetchColumn();
    }
} catch (PDOException $e) {
    $totalAudios = 0;
}

// Préparation des données pour JavaScript
$dashboardData = [
    'totalUsers' => ($userStats['user_status']['active'] ?? 0) + ($userStats['user_status']['inactive'] ?? 0) + ($userStats['user_status']['banned'] ?? 0),
    'activeUsers' => $userStats['user_status']['active'] ?? 0,
    'totalAudios' => $totalAudios,
    'reportsHandled' => $reportsToday, // Afficher les signalements des dernières 24h
    'totalReports' => $allHistoricalReports, // Tous les signalements historiques
    'globalStats' => [
        'users' => $globalStats['users'],
        'audios' => $globalStats['audios'],
        'comments' => $globalStats['comments'],
        'reactions' => $globalStats['reactions'],
        'shares' => $globalStats['shares'],
        'reports' => $allHistoricalReports // Utiliser le total historique
    ],
    'userStatus' => [
        'active' => $userStats['user_status']['active'] ?? 0,
        'inactive' => $userStats['user_status']['inactive'] ?? 0, 
        'banned' => $userStats['user_status']['banned'] ?? 0
    ]
];

// Convertir en JSON pour JavaScript
$dashboardDataJson = json_encode($dashboardData);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Tableau de bord Administrateur - C'chic">
    <title>Admin Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Ajout de la bibliothèque Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --primary-bg: #1e1e1e;
            --secondary-bg: #2c2c2c;
            --card-bg: #2d2d2d;
            --text-primary: #ffffff;
            --text-secondary: #f0f0f0;
            --accent-color: #ff7f00;
            --accent-hover: #e67300;
            --admin-blue: #3b82f6;
            --admin-red: #ef4444;
            --admin-red-hover: #0b0b0b;
            --admin-green: #22c55e;
            --admin-yellow: #f59e0b;
            --admin-purple: #8b5cf6;
            --separator-color: #4a4a4a;
            --border-radius-sm: 6px;
            --border-radius-md: 10px;
            --border-radius-lg: 16px;
            --box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
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

        .admin-container {
            display: flex;
            width: 100%;
        }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--secondary-bg);
            padding: 25px 0;
            display: flex;
            flex-direction: column;
            height: 100vh;
            position: fixed;
            border-right: 1px solid var(--separator-color);
            z-index: 1000;
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
            font-size: 0.95rem;
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
            padding: 10px;
            margin-left: var(--sidebar-width);
            transition: var(--transition);
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 10px;
            margin-bottom: 10px;
            border-bottom: 1px solid var(--separator-color);
        }

        .header h1 {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            margin: 0;
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

        /* Logout Button Style */
        .logout-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 10px;
            background-color: #3b82f6;
            color: var(--text-primary);
            text-decoration: none;
            border-radius: var(--border-radius-sm);
            font-size: 0.8rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: var(--transition);
        }

        .logout-btn:hover {
            background-color: var(--admin-red-hover);
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
        }

        .logout-btn i {
           font-size: 0.9rem;
        }
        /* --- End of Logout Button Style --- */


        .status-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 5px 12px;
            border-radius: var(--border-radius-sm);
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .status-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .status-badge.online {
            background-color: rgba(34, 197, 94, 0.2);
            color: var(--admin-green);
            border: 1px solid var(--admin-green);
        }

        /* Dashboard grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 10px;
        }

        /* Card */
        .card {
            background-color: var(--card-bg);
            border-radius: var(--border-radius-md);
            padding: 10px;
            box-shadow: var(--box-shadow);
            border: 1px solid var(--separator-color);
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(255,127,0,0.1) 0%, rgba(255,127,0,0) 100%);
            opacity: 0;
            transition: var(--transition);
            z-index: 0;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
        }

        .card:hover::before {
            opacity: 1;
        }

        .card-header {
            margin-bottom: 5px;
            padding-bottom: 5px;
            border-bottom: 1px solid var(--separator-color);
            position: relative;
            z-index: 1;
        }

        .card-header h2 {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            margin: 0;
        }

        .card-header h2 i {
            color: var(--accent-color);
            transition: var(--transition);
        }

        .card:hover .card-header h2 {
            color: var(--accent-color);
        }

        .card:hover .card-header h2 i {
            transform: scale(1.1);
        }

        /* Stats grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            text-align: center;
            position: relative;
            z-index: 1;
            padding: 5px;
            height: calc(100% - 40px);
        }

        .stat-item {
            padding: 12px 10px;
            background-color: rgba(0, 0, 0, 0.15);
            border-radius: var(--border-radius-sm);
            transition: var(--transition);
            cursor: default;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .stat-item i {
            font-size: 1.8rem;
            margin-bottom: 10px;
            transition: var(--transition);
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            display: block;
            line-height: 1.3;
            transition: var(--transition);
            color: #ffffff;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-top: 5px;
            transition: var(--transition);
        }

        .stat-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            background-color: rgba(0, 0, 0, 0.25);
        }

        .stat-item:hover i {
            transform: scale(1.1);
        }

        .stat-item:hover .stat-value {
            color: var(--accent-color);
        }

        .stat-item:hover .stat-label {
            color: var(--text-primary);
        }

        /* Chart container */
        .chart-container {
            position: relative;
            height: 210px;
            width: 100%;
            margin-top: 5px;
            padding: 5px;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: var(--transition);
        }

        .global-chart-container {
            position: relative;
            height: 300px;
            width: 100%;
            margin-top: 5px;
            transition: var(--transition);
        }

        /* Card links */
        .card-link {
            margin-top: auto;
            padding-top: 15px;
            text-align: right;
            position: relative;
            z-index: 1;
        }

        .card-link a {
            color: var(--accent-color);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: var(--transition);
        }

        .card-link a:hover {
            color: var(--text-primary);
            gap: 8px;
        }

        .card-link a i {
            transition: var(--transition);
        }

        .card-link a:hover i {
            transform: translateX(3px);
        }

        /* Footer */
        .footer {
            text-align: center;
            padding-top: 8px;
            color: var(--text-secondary);
            font-size: 0.8rem;
            border-top: 1px solid var(--separator-color);
            transition: var(--transition);
            margin-top: 5px;
        }

        .footer:hover {
            color: var(--text-primary);
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="sidebar-logo">
                <i class="fas fa-headphones-alt"></i> C'chic
            </div>
            <ul class="sidebar-nav">
                <li><a href="admin_dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Tableau de Bord</a></li>
                <li><a href="admin_gestions.php"><i class="fas fa-users"></i> Gestions</a></li>
                <li><a href="admin_signalements.php"><i class="fas fa-flag"></i> Signalements</a></li>
            </ul>
        </nav>

        <!-- Main content -->
        <main class="main-content">
            <header class="header">
                <h1><i class="fas fa-tachometer-alt"></i> Tableau de Bord</h1>
                <!-- Logout Button Added Here -->
                <a href="../cchic/login.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                </a>
            </header>

            <!-- Dashboard grid -->
            <div class="dashboard-grid">
                <!-- Stats Card -->
                <div class="card" style="grid-column: span 1;">
                    <div class="card-header">
                        <h2><i class="fas fa-chart-pie"></i> Aperçu Rapide</h2>
                    </div>
                    <div class="stats-grid">
                        <div class="stat-item stat-users">
                            <i class="fas fa-users" style="color: var(--admin-blue)"></i>
                            <span class="stat-value" id="total-users">...</span>
                            <span class="stat-label">Utilisateurs</span>
                        </div>
                        <div class="stat-item stat-active">
                            <i class="fas fa-user-check" style="color: var(--admin-purple)"></i>
                            <span class="stat-value" id="active-users-perc">...%</span>
                            <span class="stat-label">Actifs (30j)</span>
                        </div>
                        <div class="stat-item stat-audios">
                            <i class="fas fa-headphones-alt" style="color: var(--admin-green)"></i>
                            <span class="stat-value" id="total-audios">...</span>
                            <span class="stat-label">Audios</span>
                        </div>
                        <div class="stat-item stat-handled">
                            <i class="fas fa-gavel" style="color: var(--admin-yellow)"></i>
                            <span class="stat-value" id="total-reports">...</span>
                            <span class="stat-label">Signalements</span>
                        </div>
                        <div class="stat-item stat-comments">
                            <i class="fas fa-comments" style="color: var(--admin-blue)"></i>
                            <span class="stat-value" id="total-comments">...</span>
                            <span class="stat-label">Commentaires</span>
                        </div>
                        <div class="stat-item stat-shares">
                            <i class="fas fa-share-alt" style="color: var(--admin-green)"></i>
                            <span class="stat-value" id="total-shares">...</span>
                            <span class="stat-label">Partages</span>
                        </div>
                    </div>
                </div>
                
                <!-- User Status Chart Card -->
                <div class="card" style="grid-column: span 1;">
                    <div class="card-header">
                        <h2><i class="fas fa-users-cog"></i> Statut des Utilisateurs</h2>
                    </div>
                    <div class="chart-container">
                        <canvas id="userStatusChart"></canvas>
                    </div>
                </div>
                
                <!-- Global Stats Chart Card -->
                <div class="card" style="grid-column: span 2;">
                    <div class="card-header">
                        <h2><i class="fas fa-chart-bar"></i> Charte de Statistiques Globales</h2>
                    </div>
                    <div class="global-chart-container">
                        <canvas id="globalStatsChart"></canvas>
                    </div>
                </div>
            </div>

            <footer class="footer">
                <p>© 2025 - C'chic Administration - Tous droits réservés</p>
            </footer>
        </main>
    </div>

    <script>
        // Utiliser les données PHP au lieu de données simulées
        const dashboardData = <?php echo $dashboardDataJson; ?>;

        // Update stats
        function updateStats() {
            document.getElementById('total-users').textContent = dashboardData.totalUsers.toLocaleString('fr');
            document.getElementById('active-users-perc').textContent =
                Math.round((dashboardData.activeUsers / (dashboardData.totalUsers || 1)) * 100) + '%';
            document.getElementById('total-audios').textContent = dashboardData.totalAudios.toLocaleString('fr');
            document.getElementById('total-reports').textContent = dashboardData.totalReports.toLocaleString('fr');
            document.getElementById('total-comments').textContent = dashboardData.globalStats.comments.toLocaleString('fr');
            document.getElementById('total-shares').textContent = dashboardData.globalStats.shares.toLocaleString('fr');
        }
        
        // Initialiser le graphique des statistiques globales
        function initGlobalStatsChart() {
            const ctx = document.getElementById('globalStatsChart').getContext('2d');
            
            // Configuration globale pour Chart.js
            Chart.defaults.color = '#ffffff';
            Chart.defaults.borderColor = 'rgba(74, 74, 74, 0.8)';
            Chart.defaults.font.family = "'Roboto', sans-serif";
            Chart.defaults.font.size = 11;
            
            // Données pour le graphique
            const labels = [
                'Utilisateurs', 
                'Audios', 
                'Commentaires', 
                'Réactions', 
                'Partages', 
                'Signalements'
            ];
            
            // Données en barres (volumes)
            const barData = [
                dashboardData.globalStats.users,
                dashboardData.globalStats.audios,
                dashboardData.globalStats.comments,
                dashboardData.globalStats.reactions,
                dashboardData.globalStats.shares,
                dashboardData.globalStats.reports
            ];
            
            // Couleurs pour les barres
            const backgroundColors = [
                'rgba(59, 130, 246, 0.7)',  // Bleu pour utilisateurs
                'rgba(34, 197, 94, 0.7)',   // Vert pour audios
                'rgba(168, 85, 247, 0.7)',  // Violet pour commentaires
                'rgba(249, 115, 22, 0.7)',  // Orange pour réactions
                'rgba(14, 165, 233, 0.7)',  // Bleu clair pour partages
                'rgba(239, 68, 68, 0.7)'    // Rouge pour signalements
            ];
            
            const borderColors = [
                'rgba(59, 130, 246, 1)',    // Bleu pour utilisateurs
                'rgba(34, 197, 94, 1)',     // Vert pour audios
                'rgba(168, 85, 247, 1)',    // Violet pour commentaires
                'rgba(249, 115, 22, 1)',    // Orange pour réactions
                'rgba(14, 165, 233, 1)',    // Bleu clair pour partages
                'rgba(239, 68, 68, 1)'      // Rouge pour signalements
            ];
            
            // Création du graphique
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        data: barData,
                        backgroundColor: backgroundColors,
                        borderColor: borderColors,
                        borderWidth: 2,
                        borderRadius: 10,
                        borderSkipped: false,
                        barPercentage: 0.7,
                        categoryPercentage: 0.8,
                        hoverBackgroundColor: backgroundColors.map(color => color.replace('0.7', '0.9')),
                        hoverBorderColor: borderColors,
                        hoverBorderWidth: 3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',  // Barres horizontales
                    plugins: {
                        legend: { 
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0,0,0,0.9)',
                            titleFont: { weight: 'bold', size: 15 },
                            bodyFont: { size: 14},
                            padding: 10,
                            cornerRadius: 6,
                            displayColors: true,
                            boxWidth: 10,
                            boxHeight: 10,
                            boxPadding: 5,
                            usePointStyle: true,
                            callbacks: {
                                label: function(context) {
                                    return `${context.label}: ${context.parsed.x.toLocaleString('fr')} éléments`;
                                },
                                labelPointStyle: function() {
                                    return {
                                        pointStyle: 'rectRounded',
                                        rotation: 0
                                    };
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            grid: { 
                                color: 'rgba(74, 74, 74, 0.5)', 
                                tickLength: 0,
                                drawBorder: false
                            },
                            ticks: { 
                                color: '#ffffff',
                                font: { size: 13 },
                                callback: function(value) {
                                    if (value >= 1000) {
                                        return (value / 1000).toLocaleString('fr') + 'k';
                                    }
                                    return value.toLocaleString('fr');
                                }
                            }
                        },
                        y: {
                            grid: { 
                                display: false,
                                drawBorder: false
                            },
                            ticks: { 
                                color: '#ffffff', 
                                font: { size: 13, weight: 'bold' },
                                crossAlign: 'far',
                                padding: 10
                            }
                        }
                    },
                    animation: {
                        duration: 800,
                        easing: 'easeOutQuart'
                    },
                    layout: {
                        padding: {
                            top: 20,
                            right: 20,
                            bottom: 10,
                            left: 20
                        }
                    }
                }
            });
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            updateStats();
            initGlobalStatsChart();
            initUserStatusChart();
        });
        
        // Initialiser le graphique de statut des utilisateurs
        function initUserStatusChart() {
            const ctx = document.getElementById('userStatusChart').getContext('2d');
            
            // Données pour le graphique
            const data = [
                dashboardData.userStatus.active,
                dashboardData.userStatus.inactive,
                dashboardData.userStatus.banned
            ];
            
            // Création du graphique en demi-cercle
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Actifs', 'Inactifs', 'Bannis'],
                    datasets: [{
                        data: data,
                        backgroundColor: [
                            'rgba(34, 197, 94, 0.8)',
                            'rgba(148, 163, 184, 0.8)',
                            'rgba(239, 68, 68, 0.8)'
                        ],
                        borderColor: [
                            'rgba(34, 197, 94, 1)',
                            'rgba(148, 163, 184, 1)',
                            'rgba(239, 68, 68, 1)'
                        ],
                        borderWidth: 2,
                        circumference: 180, // Demi-cercle (180 degrés)
                        rotation: -90, // Rotation pour commencer en haut
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '60%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 10,
                                usePointStyle: true,
                                pointStyle: 'circle',
                                pointStyleWidth: 8,
                                color: '#ffffff',
                                font: {
                                    size: 14,
                                    weight: 'bold'
                                },
                                boxWidth: 10,
                                boxHeight: 10
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0,0,0,0.9)',
                            titleFont: { weight: 'bold', size: 15 },
                            bodyFont: { size: 14 },
                            padding: 8,
                            cornerRadius: 4,
                            callbacks: {
                                label: function(context) {
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                    const percentage = total ? Math.round((value / total) * 100) : 0;
                                    return `${context.label}: ${percentage}% (${value})`;
                                }
                            }
                        }
                    },
                    animation: {
                        duration: 600
                    },
                    layout: {
                        padding: {
                            bottom: 10,
                            top: 10,
                            left: 10,
                            right: 10
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>