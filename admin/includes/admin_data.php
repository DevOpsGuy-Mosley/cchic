<?php
class AdminData {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    // Nouvelle méthode pour récupérer toutes les statistiques pour le graphique
    public function getGlobalStats() {
        $stats = [
            'users' => 0,
            'audios' => 0,
            'comments' => 0,
            'reactions' => 0,
            'shares' => 0,
            'reports' => 0
        ];
        
        try {
            // Nombre d'utilisateurs enregistrés
            $tableCheck = $this->db->query("SHOW TABLES LIKE 'register'");
            if ($tableCheck->rowCount() > 0) {
                $stats['users'] = $this->db->query("SELECT COUNT(*) FROM register")->fetchColumn();
            }
            
            // Nombre d'audios
            $tableCheck = $this->db->query("SHOW TABLES LIKE 'audio'");
            if ($tableCheck->rowCount() > 0) {
                $stats['audios'] = $this->db->query("SELECT COUNT(*) FROM audio")->fetchColumn();
            }
            
            // Nombre de commentaires
            $tableCheck = $this->db->query("SHOW TABLES LIKE 'comments'");
            if ($tableCheck->rowCount() > 0) {
                $stats['comments'] = $this->db->query("SELECT COUNT(*) FROM comments")->fetchColumn();
            }
            
            // Nombre de réactions
            $tableCheck = $this->db->query("SHOW TABLES LIKE 'reactions'");
            if ($tableCheck->rowCount() > 0) {
                $stats['reactions'] = $this->db->query("SELECT COUNT(*) FROM reactions")->fetchColumn();
            }
            
            // Nombre de partages
            $tableCheck = $this->db->query("SHOW TABLES LIKE 'shares'");
            if ($tableCheck->rowCount() > 0) {
                $stats['shares'] = $this->db->query("SELECT COUNT(*) FROM shares")->fetchColumn();
            }
            
            // Nombre de signalements
            $tableCheck = $this->db->query("SHOW TABLES LIKE 'reports'");
            if ($tableCheck->rowCount() > 0) {
                $stats['reports'] = $this->db->query("SELECT COUNT(*) FROM reports")->fetchColumn();
            }
            
            return $stats;
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des statistiques globales : " . $e->getMessage());
            return $stats; // Retourne les valeurs par défaut
        }
    }
    
    public function getUsers() {
        try {
            // Vérifier d'abord si la table existe
            $tableCheck = $this->db->query("SHOW TABLES LIKE 'register'");
            if ($tableCheck->rowCount() > 0) {
                // Vérifier la structure de la table
                $columns = [];
                $columnQuery = $this->db->query("SHOW COLUMNS FROM register");
                while ($col = $columnQuery->fetch(PDO::FETCH_ASSOC)) {
                    $columns[] = $col['Field'];
                }
                
                // Construire la requête en fonction des colonnes disponibles
                $select = "SELECT id, username";
                if (in_array('email', $columns)) $select .= ", email";
                if (in_array('is_active', $columns)) {
                    $select .= ", is_active as status";
                } else {
                    $select .= ", 1 as status"; // Par défaut actif
                }
                if (in_array('created_at', $columns)) {
                    $select .= ", created_at";
                } else {
                    $select .= ", NOW() as created_at"; // Date actuelle par défaut
                }
                
                $query = "$select FROM register ORDER BY " . 
                         (in_array('created_at', $columns) ? "created_at DESC" : "id DESC");
                
                $stmt = $this->db->query($query);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            return [];
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des utilisateurs : " . $e->getMessage());
            return [];
        }
    }
    
    public function getReports() {
        try {
            // Vérifier d'abord si la table existe
            $tableCheck = $this->db->query("SHOW TABLES LIKE 'reports'");
            if ($tableCheck->rowCount() > 0) {
                // Vérifier la structure de la table
                $columns = [];
                $columnQuery = $this->db->query("SHOW COLUMNS FROM reports");
                while ($col = $columnQuery->fetch(PDO::FETCH_ASSOC)) {
                    $columns[] = $col['Field'];
                }
                
                // Si la table reports existe mais n'a pas la structure attendue
                if (!in_array('type', $columns) || !in_array('reporter_id', $columns)) {
                    // Structure minimale pour la compatibilité
                    $query = "SELECT 
                                r.id, 
                                'audio' as type, 
                                r.content, 
                                " . (in_array('status', $columns) ? "r.status" : "'pending' as status") . ",
                                " . (in_array('created_at', $columns) ? "r.created_at" : "NOW() as created_at") . ",
                                'Anonyme' as reporter_name,
                                'Contenu' as reported_item
                             FROM reports r
                             ORDER BY " . (in_array('created_at', $columns) ? "r.created_at DESC" : "r.id DESC");
                    
                    $stmt = $this->db->query($query);
                    return $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
                
                // Si la structure est complète, utiliser la requête originale
                // Adaptation pour utiliser register au lieu de users
                $query = "SELECT r.id, r.type, r.content, 
                            " . (in_array('status', $columns) ? "r.status" : "'pending' as status") . ",
                            " . (in_array('created_at', $columns) ? "r.created_at" : "NOW() as created_at") . ",
                            COALESCE(u1.username, 'Anonyme') as reporter_name,
                            CASE 
                                WHEN r.type = 'user' THEN COALESCE(u2.username, 'Utilisateur inconnu')
                                WHEN r.type = 'audio' THEN COALESCE(a.title, 'Audio inconnu')
                                ELSE 'Inconnu'
                            END as reported_item
                         FROM reports r
                         LEFT JOIN register u1 ON r.reporter_id = u1.id
                         LEFT JOIN register u2 ON r.reported_id = u2.id AND r.type = 'user'
                         LEFT JOIN audio a ON r.reported_id = a.id AND r.type = 'audio'
                         ORDER BY " . (in_array('created_at', $columns) ? "r.created_at DESC" : "r.id DESC");
                
                $stmt = $this->db->query($query);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            // Si la table n'existe pas, renvoyer un tableau vide
            return [];
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des rapports : " . $e->getMessage());
            return [];
        }
    }
    
    public function updateUserStatus($userId, $status) {
        try {
            // Vérifier si la table et la colonne existent
            $tableCheck = $this->db->query("SHOW TABLES LIKE 'register'");
            if ($tableCheck->rowCount() > 0) {
                $columnCheck = $this->db->query("SHOW COLUMNS FROM register LIKE 'is_active'");
                if ($columnCheck->rowCount() > 0) {
                    $query = "UPDATE register SET is_active = ? WHERE id = ?";
                    $stmt = $this->db->prepare($query);
                    return $stmt->execute([$status, $userId]);
                }
            }
            return false;
        } catch (PDOException $e) {
            error_log("Erreur lors de la mise à jour du statut : " . $e->getMessage());
            return false;
        }
    }
    
    public function updateReportStatus($reportId, $status) {
        try {
            // Vérifier si la table et la colonne existent
            $tableCheck = $this->db->query("SHOW TABLES LIKE 'reports'");
            if ($tableCheck->rowCount() > 0) {
                $columnCheck = $this->db->query("SHOW COLUMNS FROM reports LIKE 'status'");
                if ($columnCheck->rowCount() > 0) {
                    $query = "UPDATE reports SET status = ? WHERE id = ?";
                    $stmt = $this->db->prepare($query);
                    return $stmt->execute([$status, $reportId]);
                }
            }
            return false;
        } catch (PDOException $e) {
            error_log("Erreur lors de la mise à jour du rapport : " . $e->getMessage());
            return false;
        }
    }
} 