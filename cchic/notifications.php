<?php
// Démarrage de la session
session_start();

// Vérification si l'utilisateur est connecté
// if (!isset($_SESSION['user_id'])) {
//     header('Location: login.php');
//     exit();
// }

// Inclure la vérification de session qui vérifie également si le compte est actif
// require_once 'check_session.php';

// Activation du débogage détaillé
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Débogage de la session et de l'utilisateur
debug_log("Démarrage de la page notifications", [
    'session_id' => session_id(),
    'user_id' => $_SESSION['user_id'] ?? 'non défini'
]);

require_once 'database.php';

// Vérification de la connexion à la base de données
try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->query("SELECT 1");
    debug_log("Connexion à la base de données réussie");
} catch (PDOException $e) {
    debug_log("Erreur de connexion à la base de données", [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], 'ERROR');
    die("Erreur de connexion à la base de données. Veuillez réessayer plus tard.");
}

// La vérification de session est déjà faite dans check_session.php, pas besoin de la répéter ici
// if (!isset($_SESSION['user_id'])) {
//     debug_log("Utilisateur non connecté, redirection vers login.php");
//     header('Location: login.php');
//     exit();
// }

// Fonction de débogage améliorée
function debug_log($message, $data = null, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[{$timestamp}] [{$level}] {$message}";
    
    if ($data !== null) {
        $log_message .= "\nData: " . print_r($data, true);
    }
    
    error_log($log_message);
    
    // Sauvegarder dans un fichier de log dédié
    $log_file = __DIR__ . '/logs/notifications_' . date('Y-m-d') . '.log';
    $log_dir = dirname($log_file);
    
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0777, true);
    }
    
    file_put_contents($log_file, $log_message . "\n", FILE_APPEND);
}

// Fonction pour formater la date relative
function formatRelativeTime($datetime) {
    $date = new DateTime($datetime);
    return $date->format('d/m/Y à H:i');
}

// Vérification des tables nécessaires
try {
    $tables = [
        'register' => "SELECT COUNT(*) FROM register",
        'audio' => "SELECT COUNT(*) FROM audio",
        'comments' => "SELECT COUNT(*) FROM comments",
        'shares' => "SELECT COUNT(*) FROM shares"
    ];
    
    $tableStatus = [];
    foreach ($tables as $table => $query) {
        try {
            $count = $pdo->query($query)->fetchColumn();
            $tableStatus[$table] = [
                'exists' => true,
                'count' => $count
            ];
        } catch (PDOException $e) {
            $tableStatus[$table] = [
                'exists' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    debug_log("État des tables de la base de données", $tableStatus);
    
    // Vérifier si toutes les tables nécessaires existent
    $missingTables = array_filter($tableStatus, function($status) {
        return !$status['exists'];
    });
    
    if (!empty($missingTables)) {
        debug_log("Tables manquantes détectées", $missingTables, 'ERROR');
        die("Erreur : certaines tables de la base de données sont manquantes. Contactez l'administrateur.");
    }
    
} catch (PDOException $e) {
    debug_log("Erreur lors de la vérification des tables", [
        'error' => $e->getMessage()
    ], 'ERROR');
}

// Récupération des notifications depuis la base de données
function fetchNotifications($pdo, $userId) {
    debug_log("Début de la récupération des notifications", ['user_id' => $userId]);
    
    // Vérifions d'abord si l'utilisateur existe
    try {
        $userCheck = $pdo->prepare("SELECT id, nom_prenoms FROM register WHERE id = ?");
        $userCheck->execute([$userId]);
        $user = $userCheck->fetch();
        
        if (!$user) {
            debug_log("Utilisateur non trouvé dans la base de données", ['user_id' => $userId], 'ERROR');
            return [
                'notifications' => [],
                'pagination' => ['current_page' => 1, 'per_page' => 20, 'total_count' => 0, 'total_pages' => 0]
            ];
        }
        
        debug_log("Utilisateur trouvé", ['user' => $user]);
    } catch (PDOException $e) {
        debug_log("Erreur lors de la vérification de l'utilisateur", ['error' => $e->getMessage()], 'ERROR');
    }
    
    // Vérifions les audios de l'utilisateur
    try {
        $audioCheck = $pdo->prepare("SELECT COUNT(*) as count FROM audio WHERE user_id = ?");
        $audioCheck->execute([$userId]);
        $audioCount = $audioCheck->fetchColumn();
        debug_log("Nombre d'audios trouvés", ['count' => $audioCount]);
    } catch (PDOException $e) {
        debug_log("Erreur lors de la vérification des audios", ['error' => $e->getMessage()], 'ERROR');
    }

    // Ajout d'un index pour optimiser la requête
    try {
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_audio_user ON audio(user_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_comments_audio ON comments(audio_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_shares_audio ON shares(audio_id)");
    } catch (PDOException $e) {
        debug_log("Erreur lors de la création des index", $e->getMessage(), 'WARNING');
    }

    // Modification de la requête SQL pour inclure les réactions
    $query = "
        WITH combined_notifications AS (
            SELECT 
                'comment' AS type,
                c.id,
                c.user_id,
                c.audio_id,
                c.created_at,
                COALESCE(c.is_read, 0) as is_read,
                r.nom_prenoms AS username,
                r.genre AS gender,
                r.photo_profil,
                a.title AS audio_title,
                NULL as reaction_type
            FROM comments c
            JOIN register r ON c.user_id = r.id
            JOIN audio a ON c.audio_id = a.id
            WHERE a.user_id = :userId
            AND c.user_id != :userId  -- Exclure les commentaires de l'utilisateur lui-même
            
            UNION ALL
            
            SELECT 
                'share' AS type,
                s.id,
                s.user_id,
                s.audio_id,
                s.created_at,
                COALESCE(s.is_read, 0) as is_read,
                r.nom_prenoms AS username,
                r.genre AS gender,
                r.photo_profil,
                a.title AS audio_title,
                NULL as reaction_type
            FROM shares s
            JOIN register r ON s.user_id = r.id
            JOIN audio a ON s.audio_id = a.id
            WHERE a.user_id = :userId
            AND s.user_id != :userId  -- Exclure les partages de l'utilisateur lui-même

            UNION ALL

            SELECT 
                'reaction' AS type,
                rc.id,
                rc.user_id,
                rc.audio_id,
                rc.created_at,
                COALESCE(rc.is_read, 0) as is_read,
                r.nom_prenoms AS username,
                r.genre AS gender,
                r.photo_profil,
                a.title AS audio_title,
                rc.type as reaction_type
            FROM reactions rc
            JOIN register r ON rc.user_id = r.id
            JOIN audio a ON rc.audio_id = a.id
            WHERE a.user_id = :userId
            AND rc.user_id != :userId  -- Exclure les réactions de l'utilisateur lui-même
        )
        SELECT *
        FROM combined_notifications
        ORDER BY created_at DESC
    ";
    
    try {
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $perPage = 10;
        $offset = ($page - 1) * $perPage;
        
        // D'abord, récupérer le nombre total de notifications
        $countQuery = "SELECT COUNT(*) FROM ($query) AS total";
        $stmtCount = $pdo->prepare($countQuery);
        $stmtCount->bindValue(':userId', $userId, PDO::PARAM_INT);
        $stmtCount->execute();
        $totalCount = $stmtCount->fetchColumn();
        
        // Ensuite, récupérer les notifications avec pagination
        $query .= " LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'notifications' => $notifications,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_count' => $totalCount,
                'total_pages' => ceil($totalCount / $perPage)
            ]
        ];
        
    } catch (PDOException $e) {
        debug_log("Erreur lors de la récupération des notifications", [
            'error' => $e->getMessage()
        ], 'ERROR');
        return [
            'notifications' => [],
            'pagination' => [
                'current_page' => 1,
                'per_page' => 20,
                'total_count' => 0,
                'total_pages' => 0
            ]
        ];
    }
}

// Traitement des requêtes AJAX
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    header('Content-Type: application/json');
    
    try {
        if (!isset($_SESSION['user_id'])) {
            throw new Exception('Session expirée');
        }
        
        $userId = $_SESSION['user_id'];
        $notifications = fetchNotifications($pdo, $userId);
        
        echo json_encode([
            'success' => true,
            'data' => $notifications
        ]);
        exit;
        
    } catch (Exception $e) {
        debug_log("Erreur lors de la requête AJAX", [
            'error' => $e->getMessage(),
            'request' => $_GET
        ], 'ERROR');
        
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        exit;
    }
}

// Récupération de l'ID de l'utilisateur depuis la session
$userId = $_SESSION['user_id'];

// Ajout de débogage
error_log("ID utilisateur : " . $userId);

$notifications = fetchNotifications($pdo, $userId);

// Débogage des notifications
error_log("Nombre de notifications récupérées : " . count($notifications['notifications']));
if (empty($notifications['notifications'])) {
    error_log("Aucune notification trouvée");
} else {
    error_log("Première notification : " . print_r($notifications['notifications'][0], true));
}

$unreadCount = array_reduce($notifications['notifications'], function($count, $n) { 
    return $count + ($n['is_read'] ? 0 : 1); 
}, 0);

// Débogage du compteur
error_log("Nombre de notifications non lues : " . $unreadCount);

// Marquer les notifications comme lues si demandé
if (isset($_POST['mark_as_read'])) {
    $notificationId = filter_input(INPUT_POST, 'notification_id', FILTER_VALIDATE_INT);
    if ($notificationId) {
        try {
            // Mise à jour des commentaires
            $stmtComments = $pdo->prepare("
                UPDATE comments 
                SET is_read = 1 
                WHERE id = ? AND audio_id IN (SELECT id FROM audio WHERE user_id = ?)
            ");
            $stmtComments->execute([$notificationId, $userId]);

            // Mise à jour des partages
            $stmtShares = $pdo->prepare("
                UPDATE shares 
                SET is_read = 1 
                WHERE id = ? AND audio_id IN (SELECT id FROM audio WHERE user_id = ?)
            ");
            $stmtShares->execute([$notificationId, $userId]);

            // Mise à jour des réactions
            $stmtReactions = $pdo->prepare("
                UPDATE reactions 
                SET is_read = 1 
                WHERE id = ? AND audio_id IN (SELECT id FROM audio WHERE user_id = ?)
            ");
            $stmtReactions->execute([$notificationId, $userId]);

            exit(json_encode(['success' => true]));
        } catch (PDOException $e) {
            error_log("Erreur de mise à jour : " . $e->getMessage());
            exit(json_encode(['success' => false, 'error' => 'Erreur lors de la mise à jour']));
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - C'chic</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="notifications.css">
</head>
<body>
    <div class="app-container">
        <header class="notifications-header">
            <div class="header-title">
                <h1>Notifications</h1>
                <span class="unread-count" id="unreadCount"><?= $unreadCount ?></span>
            </div>
            <div class="header-actions">
                <button class="btn-refresh" id="refreshNotifications" aria-label="Rafraîchir les notifications">
                    <i class="fas fa-sync-alt"></i>
                </button>
                <button class="btn-mark-all" id="markAllAsRead" aria-label="Marquer toutes comme lues">
                    <i class="fas fa-check-double"></i>
                </button>
            </div>
        </header>
        
        <div id="loadingIndicator" class="loading-indicator" style="display: none;">
            <div class="spinner"></div>
            <p>Chargement des notifications...</p>
        </div>

        <div id="errorMessage" class="error-message" style="display: none;"></div>
        
        <main class="notifications-list" id="notificationsList">
            <?php if (empty($notifications['notifications'])): ?>
                <div class="no-notifications">
                    <i class="fas fa-bell-slash"></i>
                    <p>Aucune notification pour le moment</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications['notifications'] as $notification): ?>
                    <article class="notification <?= $notification['is_read'] ? '' : 'unread' ?>" 
                             data-id="<?= $notification['id'] ?>" 
                             data-type="<?= $notification['type'] ?>"
                             role="article" 
                             aria-label="Notification de <?= htmlspecialchars($notification['username']) ?>">
                        <div class="notification-avatar <?= strtolower($notification['gender']) ?>" aria-hidden="true">
                            <?php if (!empty($notification['photo_profil'])): ?>
                                <img src="uploads/profile_photos/<?= htmlspecialchars($notification['photo_profil']) ?>" 
                                     alt="Photo de profil de <?= htmlspecialchars($notification['username']) ?>"
                                     class="avatar-image">
                            <?php else: ?>
                                <span><?= strtoupper(substr($notification['username'], 0, 2)) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="notification-content">
                            <p class="notification-text">
                                <strong><?= htmlspecialchars($notification['username']) ?></strong>
                                <?php switch ($notification['type']):
                                    case 'comment': ?>
                                        a commenté votre audio <strong>"<?= htmlspecialchars($notification['audio_title']) ?>"</strong>
                                        <?php break;
                                    case 'share': ?>
                                        a partagé votre audio <strong>"<?= htmlspecialchars($notification['audio_title']) ?>"</strong>
                                        <?php break;
                                    case 'reaction': ?>
                                        a <?= getReactionText($notification['reaction_type']) ?> votre audio <strong>"<?= htmlspecialchars($notification['audio_title']) ?>"</strong>
                                        <?php break;
                                endswitch; ?>
                            </p>
                            <div class="notification-meta">
                                <span class="time">
                                    <i class="far fa-clock"></i> 
                                    <?= formatRelativeTime($notification['created_at']) ?>
                                </span>
                                <?php if ($notification['type'] === 'reaction'): ?>
                                    <span class="reaction-icon">
                                        <?= getReactionIcon($notification['reaction_type']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <button class="notification-action" aria-label="Options de notification">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                    </article>
                <?php endforeach; ?>
                
                <?php if ($notifications['pagination']['current_page'] < $notifications['pagination']['total_pages']): ?>
                    <div class="load-more-container">
                        <button id="loadMoreBtn" class="load-more-btn">
                            <i class="fas fa-chevron-down"></i>
                            <span>Voir plus de notifications</span>
                        </button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>
    
    <nav class="navbar" aria-label="Navigation principale">
        <a href="home.php" class="nav-item" data-page="home">
            <i class="fas fa-home nav-icon"></i>
            <span class="nav-text">Accueil</span>
        </a>
        <a href="profile.php" class="nav-item" data-page="profile">
            <i class="fas fa-user nav-icon"></i>
            <span class="nav-text">Profil</span>
        </a>
        <a href="notifications.php" class="nav-item active" data-page="notifications">
            <i class="fas fa-bell nav-icon"></i>
            <span class="nav-text">Notifications</span>
            <span class="unread-badge" id="navUnreadBadge" style="<?= $unreadCount > 0 ? 'display:flex' : 'display:none' ?>"><?= $unreadCount ?></span>
        </a>
        <a href="logout.php" class="nav-item" data-page="logout">
            <i class="fas fa-sign-out-alt nav-icon"></i>
            <span class="nav-text">Déconnexion</span>
        </a>
    </nav>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const refreshBtn = document.getElementById('refreshNotifications');
        const notificationsList = document.getElementById('notificationsList');
        const unreadCountElement = document.getElementById('unreadCount');
        const navUnreadBadge = document.getElementById('navUnreadBadge');
        const markAllAsReadBtn = document.getElementById('markAllAsRead');
        const loadMoreBtn = document.getElementById('loadMoreBtn');
        let currentPage = <?= $notifications['pagination']['current_page'] ?>;

        // Fonction pour formater la date relative
        function formatRelativeTime(datetime) {
            const date = new Date(datetime);
            const day = date.getDate().toString().padStart(2, '0');
            const month = (date.getMonth() + 1).toString().padStart(2, '0');
            const year = date.getFullYear();
            const hours = date.getHours().toString().padStart(2, '0');
            const minutes = date.getMinutes().toString().padStart(2, '0');
            
            return `${day}/${month}/${year} à ${hours}:${minutes}`;
        }

        // Fonction pour obtenir le texte de la réaction
        function getReactionText(type) {
            switch (type) {
                case 'like':
                    return 'aimé';
                case 'dislike':
                    return 'n\'a pas aimé';
                case 'laugh':
                    return 'rire de';
                default:
                    return 'a réagi à';
            }
        }

        // Fonction pour obtenir l'icône de la réaction
        function getReactionIcon(type) {
            switch (type) {
                case 'like':
                    return '<i class="fas fa-thumbs-up reaction-like"></i>';
                case 'dislike':
                    return '<i class="fas fa-thumbs-down reaction-dislike"></i>';
                case 'laugh':
                    return '<i class="fas fa-laugh reaction-laugh"></i>';
                default:
                    return '<i class="far fa-smile"></i>';
            }
        }

        // Fonction pour marquer une notification comme lue
        async function markAsRead(notification) {
            const notificationId = notification.dataset.id;
            
            try {
                const response = await fetch('notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `mark_as_read=1&notification_id=${notificationId}`
                });

                const data = await response.json();
                if (data.success) {
                    notification.classList.remove('unread');
                    updateUnreadCount();
                }
            } catch (error) {
                console.error('Erreur lors du marquage de la notification:', error);
            }
        }

        // Fonction pour mettre à jour le compteur de notifications non lues
        function updateUnreadCount() {
            const unreadNotifications = document.querySelectorAll('.notification.unread');
            const count = unreadNotifications.length;
            unreadCountElement.textContent = count;
            navUnreadBadge.textContent = count;
            navUnreadBadge.style.display = count > 0 ? 'flex' : 'none';
        }

        // Gestionnaire d'événements pour les notifications
        notificationsList.addEventListener('click', function(e) {
            const notification = e.target.closest('.notification');
            if (notification && notification.classList.contains('unread')) {
                markAsRead(notification);
            }
        });

        // Gestionnaire pour le bouton "Marquer tout comme lu"
        markAllAsReadBtn.addEventListener('click', async function() {
            const unreadNotifications = document.querySelectorAll('.notification.unread');
            for (const notification of unreadNotifications) {
                await markAsRead(notification);
            }
        });

        // Gestionnaire pour le bouton de rafraîchissement - recharge simplement la page
        refreshBtn.addEventListener('click', () => {
            window.location.reload();
        });

        // Fonction pour charger plus de notifications
        async function loadMoreNotifications() {
            if (!loadMoreBtn) return;
            
            loadMoreBtn.disabled = true;
            // Sauvegarder le contenu original du bouton
            const originalContent = loadMoreBtn.innerHTML;
            // Afficher l'icône de chargement
            loadMoreBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
            try {
                const response = await fetch(`notifications.php?ajax=1&page=${currentPage + 1}`);
                const data = await response.json();
                
                if (data.success) {
                    const newNotifications = data.data.notifications;
                    if (newNotifications.length > 0) {
                        newNotifications.forEach(notification => {
                            const notificationElement = createNotificationElement(notification);
                            notificationsList.insertBefore(notificationElement, loadMoreBtn.parentElement);
                        });
                        
                        currentPage++;
                        
                        // Mettre à jour ou supprimer le bouton "Voir plus"
                        if (currentPage >= data.data.pagination.total_pages) {
                            loadMoreBtn.parentElement.remove();
                        } else {
                            loadMoreBtn.disabled = false;
                            loadMoreBtn.innerHTML = '<i class="fas fa-chevron-down"></i>';
                        }
                    }
                }
            } catch (error) {
                console.error('Erreur lors du chargement des notifications:', error);
                loadMoreBtn.innerHTML = '<i class="fas fa-exclamation-circle"></i>';
                setTimeout(() => {
                    loadMoreBtn.innerHTML = originalContent;
                }, 2000);
            } finally {
                if (loadMoreBtn && !loadMoreBtn.parentElement.remove) {
                    loadMoreBtn.disabled = false;
                }
            }
        }

        // Fonction pour créer un élément de notification
        function createNotificationElement(notification) {
            const article = document.createElement('article');
            article.className = `notification ${notification.is_read ? '' : 'unread'}`;
            article.dataset.id = notification.id;
            article.dataset.type = notification.type;
            article.setAttribute('role', 'article');
            article.setAttribute('aria-label', `Notification de ${notification.username}`);
            
            article.innerHTML = `
                <div class="notification-avatar ${notification.gender.toLowerCase()}" aria-hidden="true">
                    ${notification.photo_profil ? 
                        `<img src="uploads/profile_photos/${notification.photo_profil}" 
                              alt="Photo de profil de ${notification.username}"
                              class="avatar-image">` :
                        `<span>${notification.username.substring(0, 2).toUpperCase()}</span>`
                    }
                </div>
                <div class="notification-content">
                    <p class="notification-text">
                        <strong>${notification.username}</strong>
                        ${getNotificationText(notification)}
                    </p>
                    <div class="notification-meta">
                        <span class="time">
                            <i class="far fa-clock"></i> 
                            ${formatRelativeTime(notification.created_at)}
                        </span>
                        ${notification.type === 'reaction' ? 
                            `<span class="reaction-icon">${getReactionIcon(notification.reaction_type)}</span>` : 
                            ''}
                    </div>
                </div>
                <button class="notification-action" aria-label="Options de notification">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
            `;
            
            return article;
        }

        // Fonction pour obtenir le texte de la notification
        function getNotificationText(notification) {
            switch (notification.type) {
                case 'comment':
                    return `a commenté votre audio <strong>"${notification.audio_title}"</strong>`;
                case 'share':
                    return `a partagé votre audio <strong>"${notification.audio_title}"</strong>`;
                case 'reaction':
                    return `a ${getReactionText(notification.reaction_type)} votre audio <strong>"${notification.audio_title}"</strong>`;
                default:
                    return '';
            }
        }

        // Gestionnaire d'événements pour le bouton "Voir plus"
        if (loadMoreBtn) {
            loadMoreBtn.addEventListener('click', loadMoreNotifications);
        }
    });
    </script>

    <style>
    .pagination {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem;
        margin-top: 1rem;
        border-top: 1px solid #eee;
    }
    
    .pagination-btn {
        padding: 0.5rem 1rem;
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        color: #007bff;
        text-decoration: none;
        transition: all 0.2s;
    }
    
    .pagination-btn:hover {
        background-color: #e9ecef;
        color: #0056b3;
    }
    
    .pagination-info {
        color: #6c757d;
        font-size: 0.9rem;
    }
    
    .loading-indicator {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 2rem;
        color: #666;
    }
    
    .spinner {
        width: 40px;
        height: 40px;
        border: 4px solid #f3f3f3;
        border-top: 4px solid #007bff;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-bottom: 1rem;
    }
    
    .btn-refresh {
        padding: 0.5rem;
        background: none;
        border: none;
        color: #007bff;
        cursor: pointer;
        margin-right: 1rem;
        transition: transform 0.2s;
    }
    
    .btn-refresh:hover {
        transform: rotate(180deg);
    }
    
    .btn-refresh.loading {
        animation: spin 1s linear infinite;
    }
    
    .error-message {
        background-color: #fff3f3;
        color: #dc3545;
        padding: 1rem;
        margin: 1rem;
        border-radius: 4px;
        border: 1px solid #ffcdd2;
        text-align: center;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .load-more-container {
        text-align: center;
        padding: 0.5rem;
        width: 100%;
        position: sticky;
        bottom: 15px;
        left: 0;
        z-index: 100;
        background: transparent;
        pointer-events: none;
    }
    
    .load-more-btn {
        background-color: var(--accent-color);
        border: none;
        border-radius: 50%;
        padding: 0;
        color: white;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 45px;
        height: 45px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        pointer-events: auto;
    }
    
    .load-more-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(0, 0, 0, 0.4);
    }
    
    .load-more-btn:disabled {
        opacity: 0.7;
        cursor: not-allowed;
        background-color: var(--text-secondary);
    }
    
    .load-more-btn span {
        display: none;
    }
    
    .load-more-btn i {
        font-size: 1.3rem;
    }

    /* Styles pour tablette */
    @media (max-width: 1024px) {
        .app-container {
            margin: 15px auto;
            height: calc(100vh - 90px);
        }
    }

    /* Styles pour mobile */
    @media (max-width: 768px) {
        .app-container {
            width: 100%;
            margin: 0;
            height: calc(100vh - 60px);
            border-radius: 0;
        }
    }

    @media (max-width: 480px) {
        .app-container {
            height: calc(100vh - 60px);
        }
        
        .load-more-btn {
            width: 40px;
            height: 40px;
        }
        
        .load-more-btn i {
            font-size: 1.2rem;
        }
    }

    /* Ajustement du conteneur principal pour le scroll */
    .app-container {
        width: 95%; 
        max-width: 1000px; 
        margin: 30px auto;
        height: calc(100vh - 120px);
        box-shadow: 0 10px 25px rgba(187, 184, 184, 0.3);
        background-color: var(--secondary-bg); 
        border-radius: 15px; 
        position: relative; 
        padding: 0;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    /* Ajustement de la liste des notifications */
    .notifications-list {
        flex: 1;
        overflow-y: auto;
        padding: 10px;
        padding-bottom: 80px;
        position: relative;
    }
    </style>
</body>
</html>

<?php
// Fonction pour obtenir le texte de la réaction
function getReactionText($type) {
    switch ($type) {
        case 'like':
            return 'aimé';
        case 'dislike':
            return 'n\'a pas aimé';
        case 'laugh':
            return 'rire de';
        default:
            return 'a réagi à';
    }
}

// Fonction pour obtenir l'icône de la réaction
function getReactionIcon($type) {
    switch ($type) {
        case 'like':
            return '<i class="fas fa-thumbs-up reaction-like"></i>';
        case 'dislike':
            return '<i class="fas fa-thumbs-down reaction-dislike"></i>';
        case 'laugh':
            return '<i class="fas fa-laugh reaction-laugh"></i>';
        default:
            return '<i class="far fa-smile"></i>';
    }
}