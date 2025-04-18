<?php
class AdminStats {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function getUserStats() {
        $stats = [
            'user_status' => [
                'active' => 0,
                'inactive' => 0,
                'banned' => 0
            ],
            'new_users_week' => 0,
            'total_users' => 0
        ];
        
        try {
            // Vérification de l'existence de la table
            if (!$this->tableExists('register')) {
                return $stats;
            }
            
            // Récupération du total des utilisateurs
            $query = "SELECT COUNT(*) as total FROM register";
            $stmt = $this->db->query($query);
            $stats['total_users'] = (int)$stmt->fetchColumn();
            
            // Récupération des utilisateurs par statut
            $query = "SELECT 
                        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
                        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive,
                        SUM(CASE WHEN is_active = 2 THEN 1 ELSE 0 END) as banned
                    FROM register";
            $stmt = $this->db->query($query);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $stats['user_status']['active'] = (int)$result['active'];
                $stats['user_status']['inactive'] = (int)$result['inactive'];
                $stats['user_status']['banned'] = (int)$result['banned'];
            }
            
            // Récupération des nouveaux utilisateurs de la semaine
            $query = "SELECT COUNT(*) as new_users 
                     FROM register 
                     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            $stmt = $this->db->query($query);
            $stats['new_users_week'] = (int)$stmt->fetchColumn();
            
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des statistiques utilisateurs: " . $e->getMessage());
        }
        
        return $stats;
    }
    
    public function getReportStats() {
        $stats = [
            'report_status' => [],
            'pending_reports' => 0,
            'processed_reports' => 0,
            'deleted_reports' => 0,
            'total_reports' => 0,
            'new_reports_week' => 0,
            'new_reports_day' => 0,
            'new_reports_month' => 0,
            'reports_by_day' => [],
            'reports_by_month' => []
        ];
        
        try {
            // Vérification de l'existence de la table
            if (!$this->tableExists('reports')) {
                error_log("La table reports n'existe pas");
                return $stats;
            }

            // Récupération du total des signalements
            $query = "SELECT COUNT(*) FROM reports";
            $stats['total_reports'] = (int)$this->db->query($query)->fetchColumn();
            
            // Récupération des signalements par statut
            $query = "SELECT 
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                        SUM(CASE WHEN status = 'processed' THEN 1 ELSE 0 END) as processed,
                        SUM(CASE WHEN status = 'deleted' THEN 1 ELSE 0 END) as deleted
                    FROM reports";
            $stmt = $this->db->query($query);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $stats['pending_reports'] = (int)$result['pending'];
                $stats['processed_reports'] = (int)$result['processed'];
                $stats['deleted_reports'] = (int)$result['deleted'];
            }
            
            // Récupération des nouveaux signalements par période
            $query = "SELECT 
                        COUNT(*) as day_count
                    FROM reports 
                    WHERE DATE(created_at) = CURDATE()";
            $stats['new_reports_day'] = (int)$this->db->query($query)->fetchColumn();
            
            $query = "SELECT 
                        COUNT(*) as week_count
                    FROM reports 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            $stats['new_reports_week'] = (int)$this->db->query($query)->fetchColumn();
            
            $query = "SELECT 
                        COUNT(*) as month_count
                    FROM reports 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            $stats['new_reports_month'] = (int)$this->db->query($query)->fetchColumn();
            
            // Récupération des signalements par jour des 7 derniers jours
            $query = "SELECT 
                        DATE(created_at) as date,
                        COUNT(*) as count
                    FROM reports 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    GROUP BY DATE(created_at)
                    ORDER BY date DESC";
            $stmt = $this->db->query($query);
            $stats['reports_by_day'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Récupération des signalements par mois des 6 derniers mois
            $query = "SELECT 
                        DATE_FORMAT(created_at, '%Y-%m') as month,
                        COUNT(*) as count
                    FROM reports 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                    ORDER BY month DESC";
            $stmt = $this->db->query($query);
            $stats['reports_by_month'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des statistiques des signalements: " . $e->getMessage());
        }
        
        return $stats;
    }

    /**
     * Récupère les données d'activité du site
     * @return array Tableau contenant les labels et les valeurs
     */
    public function getActivityData() {
        try {
            // Récupérer les données des 7 derniers jours
            $query = "SELECT 
                        DATE(created_at) as date,
                        COUNT(*) as count
                    FROM (
                        SELECT created_at FROM users
                        UNION ALL
                        SELECT created_at FROM audio
                        UNION ALL
                        SELECT created_at FROM comments
                        UNION ALL
                        SELECT created_at FROM reactions
                    ) as activity
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    GROUP BY DATE(created_at)
                    ORDER BY date ASC";

            $stmt = $this->db->query($query);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $labels = [];
            $values = [];

            // Initialiser les 7 derniers jours
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $labels[] = date('d/m', strtotime($date));
                $values[] = 0;
            }

            // Mettre à jour les valeurs avec les données réelles
            foreach ($results as $row) {
                $date = date('d/m', strtotime($row['date']));
                $index = array_search($date, $labels);
                if ($index !== false) {
                    $values[$index] = (int)$row['count'];
                }
            }

            return [
                'labels' => $labels,
                'values' => $values
            ];
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des données d'activité: " . $e->getMessage());
            return [
                'labels' => [],
                'values' => []
            ];
        }
    }

    /**
     * Récupère les données de croissance des utilisateurs
     * @return array Tableau contenant les labels et les valeurs
     */
    public function getUserGrowthData() {
        try {
            // Récupérer les données des 6 derniers mois
            $query = "SELECT 
                        DATE_FORMAT(created_at, '%Y-%m') as month,
                        COUNT(*) as count
                    FROM users
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                    ORDER BY month ASC";

            $stmt = $this->db->query($query);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $labels = [];
            $values = [];

            // Initialiser les 6 derniers mois
            for ($i = 5; $i >= 0; $i--) {
                $month = date('Y-m', strtotime("-$i months"));
                $labels[] = date('m/Y', strtotime($month));
                $values[] = 0;
            }

            // Mettre à jour les valeurs avec les données réelles
            foreach ($results as $row) {
                $month = date('m/Y', strtotime($row['month']));
                $index = array_search($month, $labels);
                if ($index !== false) {
                    $values[$index] = (int)$row['count'];
                }
            }

            return [
                'labels' => $labels,
                'values' => $values
            ];
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des données de croissance: " . $e->getMessage());
            return [
                'labels' => [],
                'values' => []
            ];
        }
    }

    /**
     * Récupère les statistiques du contenu
     * @return array Tableau contenant les statistiques du contenu
     */
    public function getContentStats() {
        try {
            $stats = [
                'audios' => 0,
                'comments' => 0,
                'reactions' => 0,
                'shares' => 0
            ];

            // Compter les audios
            $query = "SELECT COUNT(*) FROM audio";
            $stats['audios'] = (int)$this->db->query($query)->fetchColumn();

            // Compter les commentaires
            $query = "SELECT COUNT(*) FROM comments";
            $stats['comments'] = (int)$this->db->query($query)->fetchColumn();

            // Compter les réactions
            $query = "SELECT COUNT(*) FROM reactions";
            $stats['reactions'] = (int)$this->db->query($query)->fetchColumn();

            // Compter les partages
            $query = "SELECT COUNT(*) FROM shares";
            $stats['shares'] = (int)$this->db->query($query)->fetchColumn();

            return $stats;
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des statistiques du contenu: " . $e->getMessage());
            return [
                'audios' => 0,
                'comments' => 0,
                'reactions' => 0,
                'shares' => 0
            ];
        }
    }

    private function tableExists($table) {
        try {
            $stmt = $this->db->prepare("SHOW TABLES LIKE :table");
            $stmt->execute(['table' => $table]);
            $exists = $stmt->rowCount() > 0;
            error_log("Vérification de la table $table : " . ($exists ? "existe" : "n'existe pas"));
            return $exists;
        } catch (PDOException $e) {
            error_log("Erreur lors de la vérification de la table $table : " . $e->getMessage());
            return false;
        }
    }
} 