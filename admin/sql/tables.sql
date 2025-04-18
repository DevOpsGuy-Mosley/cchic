-- Table des administrateurs (si elle n'existe pas déjà)
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table des logs administratifs
CREATE TABLE IF NOT EXISTS admin_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    action_details TEXT NOT NULL,
    target_id INT,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (admin_id) REFERENCES admins(id)
);

-- Table de sauvegarde des utilisateurs
CREATE TABLE IF NOT EXISTS users_backup (
    id INT NOT NULL,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    status VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL,
    backup_date DATETIME NOT NULL,
    PRIMARY KEY (id, backup_date)
);

-- Table de sauvegarde des rapports
CREATE TABLE IF NOT EXISTS reports_backup (
    id INT NOT NULL,
    type VARCHAR(20) NOT NULL,
    content TEXT NOT NULL,
    status VARCHAR(20) NOT NULL,
    reporter_id INT NOT NULL,
    reported_id INT NOT NULL,
    created_at DATETIME NOT NULL,
    backup_date DATETIME NOT NULL,
    PRIMARY KEY (id, backup_date)
);

-- Ajout d'un administrateur par défaut (mot de passe : admin123)
INSERT INTO admins (username, password, email) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@cchic.fr')
ON DUPLICATE KEY UPDATE id = id; 