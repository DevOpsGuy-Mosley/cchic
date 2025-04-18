<?php
// Inclure la vérification de session qui vérifie également si le compte est actif
require_once 'check_session.php';
require 'database.php';

// if (!isset($_SESSION['user_id'])) {
//    header("Location: login.php");
//    exit();
// }

// Fonction pour obtenir le nombre de notifications non lues
function getUnreadNotificationsCount($pdo, $userId) {
    try {
        $query = "
            SELECT COUNT(*) as unread_count FROM (
                SELECT id FROM comments 
                WHERE audio_id IN (SELECT id FROM audio WHERE user_id = ?) 
                AND user_id != ?  -- Exclure les commentaires de l'utilisateur lui-même
                AND (is_read = 0 OR is_read IS NULL)
                
                UNION ALL
                
                SELECT id FROM shares 
                WHERE audio_id IN (SELECT id FROM audio WHERE user_id = ?)
                AND user_id != ?  -- Exclure les partages de l'utilisateur lui-même
                AND (is_read = 0 OR is_read IS NULL)
                
                UNION ALL
                
                SELECT id FROM reactions 
                WHERE audio_id IN (SELECT id FROM audio WHERE user_id = ?)
                AND user_id != ?  -- Exclure les réactions de l'utilisateur lui-même
                AND (is_read = 0 OR is_read IS NULL)
            ) AS combined_notifications
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$userId, $userId, $userId, $userId, $userId, $userId]);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Erreur lors du comptage des notifications non lues: " . $e->getMessage());
        return 0;
    }
}

// Obtenir le compteur de notifications non lues si demandé par AJAX
if (isset($_GET['get_unread_count']) && $_GET['get_unread_count'] == 1) {
    header('Content-Type: application/json');
    $unreadCount = getUnreadNotificationsCount($pdo, $_SESSION['user_id']);
    echo json_encode(['success' => true, 'unreadCount' => $unreadCount]);
    exit;
}

$sql = "SELECT * FROM audio ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$audios = $stmt->fetchAll(PDO::FETCH_ASSOC);
function fetchAudios($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT 
                a.*,
                r.nom_prenoms,
                r.id as user_id,
                r.photo_profil,
                (SELECT COUNT(*) FROM reactions WHERE audio_id = a.id AND type = 'like') AS like_count,
                (SELECT COUNT(*) FROM reactions WHERE audio_id = a.id AND type = 'dislike') AS dislike_count,
                (SELECT COUNT(*) FROM reactions WHERE audio_id = a.id AND type = 'laugh') AS laugh_count,
                (SELECT COUNT(*) FROM comments WHERE audio_id = a.id) AS comment_count
            FROM audio a
            LEFT JOIN register r ON a.user_id = r.id
            ORDER BY a.datenote DESC, a.id DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur dans fetchAudios: " . $e->getMessage());
        return [];
    }
}

function addComment($pdo, $audioId, $userId, $commentContent) {
    $stmt = $pdo->prepare("INSERT INTO comments (audio_id, user_id, content, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$audioId, $userId, $commentContent]);
    return $pdo->lastInsertId();
}

function fetchComments($pdo, $audioId) {
    $stmt = $pdo->prepare("SELECT c.*, u.nom_prenoms FROM comments c JOIN register u ON c.user_id = u.id WHERE c.audio_id = ? ORDER BY c.created_at DESC");
    $stmt->execute([$audioId]);
    return $stmt->fetchAll();
}

function addReaction($pdo, $audioId, $userId, $reactionType) {
    // Vérifier si l'utilisateur a déjà réagi
    $stmt = $pdo->prepare("SELECT type FROM reactions WHERE audio_id = ? AND user_id = ?");
    $stmt->execute([$audioId, $userId]);
    $existingReaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingReaction) {
        // Si l'utilisateur a déjà mis la même réaction, on la supprime
        if ($existingReaction['type'] === $reactionType) {
            $stmt = $pdo->prepare("DELETE FROM reactions WHERE audio_id = ? AND user_id = ? AND type = ?");
            $stmt->execute([$audioId, $userId, $reactionType]);
            return ['status' => 'success', 'action' => 'removed', 'audio_id' => $audioId, 'type' => $reactionType];
        } else {
            // Si l'utilisateur a mis une réaction différente, on met à jour
            $stmt = $pdo->prepare("UPDATE reactions SET type = ?, created_at = NOW() WHERE audio_id = ? AND user_id = ?");
            $stmt->execute([$reactionType, $audioId, $userId]);
            return ['status' => 'success', 'action' => 'changed', 'audio_id' => $audioId, 'old_type' => $existingReaction['type'], 'new_type' => $reactionType];
        }
    } else {
        // Si l'utilisateur n'a pas encore réagi, on ajoute une nouvelle réaction
        $stmt = $pdo->prepare("INSERT INTO reactions (audio_id, user_id, type, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$audioId, $userId, $reactionType]);
        return ['status' => 'success', 'action' => 'added', 'audio_id' => $audioId, 'type' => $reactionType];
    }
}

function getUserReaction($pdo, $audioId, $userId) {
    $stmt = $pdo->prepare("SELECT type FROM reactions WHERE audio_id = ? AND user_id = ?");
    $stmt->execute([$audioId, $userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['type'] : null;
}

$audios = fetchAudios($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_comment') {
        $audioId = filter_input(INPUT_POST, 'audio_id', FILTER_VALIDATE_INT);
        $commentContent = trim(filter_input(INPUT_POST, 'content', FILTER_SANITIZE_STRING));

        if ($audioId && $commentContent) {
            $commentId = addComment($pdo, $audioId, $_SESSION['user_id'], $commentContent);
            echo json_encode(['status' => 'success', 'comment_id' => $commentId]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Données invalides']);
        }
        exit;
    }

    if ($_POST['action'] === 'add_reaction') {
        $audioId = filter_input(INPUT_POST, 'audio_id', FILTER_VALIDATE_INT);
        $userId = $_SESSION['user_id'];
        $reactionType = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);

        if ($audioId && $userId && in_array($reactionType, ['like', 'dislike', 'laugh'])) {
            $result = addReaction($pdo, $audioId, $userId, $reactionType);
            echo json_encode($result);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Données invalides']);
        }
        exit;
    }

    if ($_POST['action'] === 'add_report') {
        $audioId = filter_input(INPUT_POST, 'audio_id', FILTER_VALIDATE_INT);
        $userId = $_SESSION['user_id'];

        if ($audioId && $userId) {
            $reportId = addReport($pdo, $audioId, $userId, $_POST['motif']);
            echo json_encode(['status' => 'success', 'report_id' => $reportId, 'motif' => $_POST['motif']]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Données invalides']);
        }
        exit;
    }

    if ($_POST['action'] === 'add_share') {
        $audioId = filter_input(INPUT_POST, 'audio_id', FILTER_VALIDATE_INT);
        $userId = $_SESSION['user_id'];

        if ($audioId && $userId) {
            $shareId = addShare($pdo, $audioId, $userId);
            echo json_encode(['status' => 'success', 'share_id' => $shareId]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Données invalides']);
        }
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_comments') {
    $audioId = filter_input(INPUT_GET, 'audio_id', FILTER_VALIDATE_INT);
    $comments = fetchComments($pdo, $audioId);
    echo json_encode(['status' => 'success', 'comments' => $comments]);
    exit;
}

function addReport($pdo, $audioId, $userId, $motif) {
    $stmt = $pdo->prepare("INSERT INTO reports (audio_id, user_id, motif, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$audioId, $userId, $motif]);
    return $pdo->lastInsertId();
}

function addShare($pdo, $audioId, $userId) {
    $stmt = $pdo->prepare("INSERT INTO shares (audio_id, user_id, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$audioId, $userId]);
    return $pdo->lastInsertId();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accueil - C'chic</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="home.css">
    <style>
        .emoji-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 20px;
            opacity: 0.6;
            transition: all 0.2s ease;
            margin-right: 8px;
            padding: 0;
            line-height: 1;
        }
        
        .emoji-btn:hover {
            transform: scale(1.2);
            opacity: 1;
        }
        
        .emoji-btn.active {
            opacity: 1;
            transform: scale(1.1);
            filter: drop-shadow(0 0 5px rgba(255, 255, 255, 0.5));
        }
        
        .reaction-card {
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: center;
            margin: 0 8px;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 6px 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            min-width: 70px;
            transition: all 0.3s ease;
        }
        
        .reaction-card:has(.emoji-btn.active),
        .reaction-card.active-card {
            background-color: rgba(255, 255, 255, 0.25);
            box-shadow: 0 0 10px rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }
        
        .reaction-count {
            font-weight: bold;
            color: white;
            font-size: 14px;
        }
        
        .emoji-container {
            display: flex;
            justify-content: center;
            padding: 10px 0;
            flex-wrap: wrap;
        }
        
        /* Styles pour le bouton d'enregistrement */
        .floating-record {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .floating-record.recording {
            background-color: #e74c3c;
            animation: pulse 1.5s infinite;
        }
        
        .floating-record.recording i {
            color: white;
        }
        
        .recording-timer {
            position: absolute;
            top: -30px;
            left: 50%;
            transform: translateX(-50%);
            background-color: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: bold;
            display: none;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(231, 76, 60, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(231, 76, 60, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(231, 76, 60, 0);
            }
        }
        
        @media (max-width: 480px) {
            .reaction-card {
                margin: 0 4px;
                padding: 5px 8px;
            }
            
            .emoji-btn {
                font-size: 18px;
                margin-right: 6px;
            }
            
            .reaction-count {
                font-size: 12px;
            }
        }

        /* Styles améliorés pour la barre de recherche */
        .header {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        #search-bar {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: rgba(45, 45, 45, 0.8);
            border-radius: 20px;
            padding: 5px 10px;
            margin: 0 10px;
            position: static;
            transform: none;
        }

        #search-input {
            flex: 1;
            padding: 8px;
            border: 1px solid #444;
            border-radius: 15px;
            margin-right: 5px;
            background-color: #212121;
            color: white;
            width: 100%;
        }

        .feed-title {
            text-align: right;
        }

        /* Badge de notifications non lues */
        .nav-item .unread-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color:#1E3A8A;
            color: white;
            border-radius: 50%;
            min-width: 25px;
            height: 25px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 0.7em;
            font-weight: bold;
            padding: 2px;
        }

        .nav-item {
            position: relative;
        }
    </style>
</head>
<body>
    <div class="app-wrapper">
        <div class="header">
            <div class="logo">C'chic</div>
            <button id="search-button" class="search-button">
            <i class="fas fa-search"></i>
            </button>
            <div id="search-bar" style="display: none;">
            <input type="text" id="search-input" placeholder="Rechercher...">
            <button id="submit-search" class="btn btn-primary btn-sm">
                <i class="fas fa-arrow-right"></i>
            </button>
            </div>
            <div class="feed-title">Fil d'actualité</div>
        </div>

        <div class="main-content">
            <?php foreach ($audios as $audio): ?>
                <?php $userReaction = getUserReaction($pdo, $audio['id'], $_SESSION['user_id']); ?>
                <div class="audio-card" data-audio-id="<?= htmlspecialchars($audio['id']) ?>">
                    <div class="user-frame" style="display: flex; align-items: center; padding: 10px;">
                        <a href="profile.php?user_id=<?= htmlspecialchars($audio['user_id']) ?>" class="profile-photo" style="width: 50px; height: 50px; border-radius: 50%; margin-right: 10px; overflow: hidden; text-decoration: none;">
                            <?php if (!empty($audio['photo_profil'])): ?>
                                <img src="uploads/profile_photos/<?= htmlspecialchars($audio['photo_profil']) ?>" alt="Photo de profil" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <div style="width: 100%; height: 100%; background-color: #ccc; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                                    <?= strtoupper(substr($audio['nom_prenoms'], 0, 2)) ?>
                                </div>
                            <?php endif; ?>
                        </a>
                        <h3 class="username" style="font-weight: 500; color: white; text-align: center; width: 100%; margin: 0;">
                            <?= htmlspecialchars($audio['nom_prenoms']) ?>
                        </h3>
                    </div>
                    <div class="audio-frame">
                        <audio controls style="width: 100%;">
                            <source src="<?= htmlspecialchars($audio['notevocale'], ENT_QUOTES, 'UTF-8') ?>" type="audio/mp3">
                            Votre navigateur ne supporte pas l'élément audio.
                        </audio>
                        <div class="audio-details" style="display: flex; justify-content: space-between; align-items: center; padding: 10px;">
                            <div class="time-elapsed" style="color: #fffff; font-size: 0.9em;" data-datetime="<?= htmlspecialchars($audio['datenote'], ENT_QUOTES, 'UTF-8') ?>">
                                <?php
                                $datenote = new DateTime($audio['datenote']);
                                $now = new DateTime();
                                $interval = $datenote->diff($now);
                                $secondsAgo = $now->getTimestamp() - $datenote->getTimestamp();

                                if ($secondsAgo < 5) {
                                    echo 'À l\'instant';
                                } elseif ($secondsAgo < 60) {
                                    echo 'Il y a ' . $secondsAgo . ' seconde' . ($secondsAgo > 1 ? 's' : '');
                                } elseif ($secondsAgo < 3600) {
                                    $minutes = floor($secondsAgo / 60);
                                    echo 'Il y a ' . $minutes . ' minute' . ($minutes > 1 ? 's' : '');
                                } elseif ($secondsAgo < 86400) {
                                    $hours = floor($secondsAgo / 3600);
                                    echo 'Il y a ' . $hours . ' heure' . ($hours > 1 ? 's' : '');
                                } elseif ($interval->days < 3) {
                                    echo 'Il y a ' . $interval->days . ' jour' . ($interval->days > 1 ? 's' : '');
                                } else {
                                    echo 'Le ' . $datenote->format('d/m/Y à H:i');
                                }
                                ?>
                            </div>
                            <div class="audio-title" style="font-weight: bold; color: #fffff;">
                                <?= !empty($audio['title']) ? htmlspecialchars($audio['title'], ENT_QUOTES, 'UTF-8') : 'Sans titre' ?>
                            </div>
                        </div>
                    </div>
                    <div class="actions-frame">
                        <div class="emoji-container">
                            <div class="reaction-card">
                                <button class="emoji-btn <?= $userReaction === 'like' ? 'active' : '' ?>" data-reaction-type="like" title="J'aime">❤️</button>
                                <div class="reaction-count"><?= htmlspecialchars($audio['like_count']) ?></div>
                            </div>
                            <div class="reaction-card">
                                <button class="emoji-btn <?= $userReaction === 'dislike' ? 'active' : '' ?>" data-reaction-type="dislike" title="Je n'aime pas">👎</button>
                                <div class="reaction-count"><?= htmlspecialchars($audio['dislike_count']) ?></div>
                            </div>
                            <div class="reaction-card">
                                <button class="emoji-btn <?= $userReaction === 'laugh' ? 'active' : '' ?>" data-reaction-type="laugh" title="Rire">😂</button>
                                <div class="reaction-count"><?= htmlspecialchars($audio['laugh_count']) ?></div>
                            </div>
                        </div>
                        <div class="action-buttons">
                            <button class="action-btn report-btn" title="Signaler"><i class="fas fa-exclamation-triangle"></i></button>
                            <button class="action-btn share-btn" title="Partager"><i class="fas fa-share-alt"></i></button>
                        </div>
                    </div>
                    <div class="comment-input-area">
                        <input type="text" class="comment-input" placeholder="Ajouter un commentaire..." required>
                        <button class="send-comment-btn btn btn-primary btn-sm">Envoyer</button>
                        <button class="toggle-comments-btn" title="Afficher/Masquer les commentaires">
                            <i class="fas fa-comment"></i>
                            <span class="comment-count"><?= htmlspecialchars($audio['comment_count']) ?></span>
                        </button>
                    </div>
                    <div class="comments-container" style="display: none;">
                        <?php foreach (fetchComments($pdo, $audio['id']) as $comment): ?>
                            <div class="comment">
                                <strong><?= htmlspecialchars($comment['nom_prenoms']) ?>:</strong>
                                <?= htmlspecialchars($comment['content']) ?>
                                <div class="comment-date" style="font-size: 0.8em; color: gray;">
                                    <?= htmlspecialchars($comment['created_at']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="floating-record" title="Cliquer pour démarrer l'enregistrement">
            <i class="fas fa-microphone"></i>
        </div>
    </div>

    <div class="navbar">
        <a href="home.php" class="nav-item active" data-page="home">
            <i class="fas fa-home nav-icon"></i>
            <span class="nav-text">Accueil</span>
        </a>
        <a href="profile.php" class="nav-item" data-page="profile">
            <i class="fas fa-user nav-icon"></i>
            <span class="nav-text">Profil</span>
        </a>
        <a href="notifications.php" class="nav-item" data-page="notifications">
            <i class="fas fa-bell nav-icon"></i>
            <span class="nav-text">Notifications</span>
            <span class="unread-badge" id="navUnreadBadge" style="display: none;">0</span>
        </a>
        <a href="logout.php" class="nav-item" data-page="logout">
            <i class="fas fa-sign-out-alt nav-icon"></i>
            <span class="nav-text">Déconnexion</span>
        </a>
    </div>

    <script>
        // Variables globales
        let mediaRecorder;
        let audioChunks = [];
        let audioStream;
        let audioBlob;
        let floatingRecord;
        let recordingTimer;

        // Fonction pour mettre à jour les timestamps en temps réel
        function updateTimestamps() {
            document.querySelectorAll('.time-elapsed').forEach(timeElement => {
                const dateTime = new Date(timeElement.getAttribute('data-datetime'));
                const now = new Date();
                const secondsAgo = Math.floor((now - dateTime) / 1000);
                
                let timeText;
                if (secondsAgo < 5) {
                    timeText = 'À l\'instant';
                } else if (secondsAgo < 60) {
                    timeText = `Il y a ${secondsAgo} seconde${secondsAgo > 1 ? 's' : ''}`;
                } else if (secondsAgo < 3600) {
                    const minutes = Math.floor(secondsAgo / 60);
                    timeText = `Il y a ${minutes} minute${minutes > 1 ? 's' : ''}`;
                } else if (secondsAgo < 86400) {
                    const hours = Math.floor(secondsAgo / 3600);
                    timeText = `Il y a ${hours} heure${hours > 1 ? 's' : ''}`;
                } else if (secondsAgo < 259200) { // 3 jours en secondes
                    const days = Math.floor(secondsAgo / 86400);
                    timeText = `Il y a ${days} jour${days > 1 ? 's' : ''}`;
                } else {
                    const date = dateTime.toLocaleDateString('fr-FR', { 
                        day: 'numeric', 
                        month: 'numeric', 
                        year: 'numeric' 
                    });
                    const time = dateTime.toLocaleTimeString('fr-FR', { 
                        hour: '2-digit', 
                        minute: '2-digit' 
                    });
                    timeText = `Le ${date} à ${time}`;
                }
                
                timeElement.textContent = timeText;
            });
        }

        // Fonctions pour l'enregistrement audio
        async function startRecording(e) {
            e.preventDefault();
            
            // Vérifier que les éléments nécessaires sont bien définis
            if (!floatingRecord || !recordingTimer) {
                console.error("Les éléments d'enregistrement n'ont pas été correctement initialisés");
                return;
            }
            
            try {
                floatingRecord.classList.add('recording');
                floatingRecord.innerHTML = '<i class="fas fa-stop-circle"></i>';
                floatingRecord.title = "Cliquer pour arrêter l'enregistrement";
                
                // S'assurer que le timer est visible et positionné correctement
                recordingTimer.style.display = 'block';
                recordingTimer.style.position = 'absolute';
                
                audioStream = await navigator.mediaDevices.getUserMedia({ 
                    audio: {
                        channelCount: 1,
                        sampleRate: 44100
                    }
                });
                
                mediaRecorder = new MediaRecorder(audioStream, {
                    mimeType: 'audio/webm;codecs=opus'
                });
                
                audioChunks = [];

                mediaRecorder.ondataavailable = event => {
                    audioChunks.push(event.data);
                };

                mediaRecorder.start();
                startTimer();
                
                console.log("Enregistrement démarré avec succès", {
                    recorderState: mediaRecorder.state,
                    timerVisible: recordingTimer.style.display,
                    recordingClassAdded: floatingRecord.classList.contains('recording')
                });
                
            } catch (err) {
                console.error('Erreur microphone:', err);
                if (floatingRecord) {
                    floatingRecord.classList.remove('recording');
                    floatingRecord.innerHTML = '<i class="fas fa-microphone"></i>';
                    floatingRecord.title = "Cliquer pour démarrer l'enregistrement";
                }
                if (recordingTimer) recordingTimer.style.display = 'none';
                
                // Réinitialiser l'état d'enregistrement dans le gestionnaire d'événements parent
                if (typeof isRecording !== 'undefined') isRecording = false;
                
                // Afficher une modale d'erreur plus détaillée
                showMicrophoneErrorModal();
            }
        }

        function stopRecording() {
            if (!mediaRecorder || mediaRecorder.state === 'inactive') {
                console.log("Tentative d'arrêt d'un enregistrement inactif");
                return;
            }
            
            console.log("Arrêt de l'enregistrement...", {
                recorderState: mediaRecorder.state,
                chunksLength: audioChunks.length
            });
            
            // Arrêter l'enregistrement
            mediaRecorder.stop();
            
            // Arrêter les pistes audio
            if (audioStream) {
                audioStream.getTracks().forEach(track => track.stop());
            }
            
            // Réinitialiser l'interface utilisateur
            if (floatingRecord) {
                floatingRecord.classList.remove('recording');
                floatingRecord.innerHTML = '<i class="fas fa-microphone"></i>';
                floatingRecord.title = "Cliquer pour démarrer l'enregistrement";
            }
            
            if (recordingTimer) {
                recordingTimer.style.display = 'none';
            }
            
            // Traiter l'audio enregistré avec un léger délai pour s'assurer que mediaRecorder.ondataavailable a été appelé
            setTimeout(() => {
                if (audioChunks && audioChunks.length > 0) {
                    audioBlob = new Blob(audioChunks, { type: 'audio/mp3' });
                    console.log("Blob audio créé, affichage de la fenêtre de détails...", {
                        blobSize: audioBlob.size,
                        blobType: audioBlob.type
                    });
                    showAudioDetailsModal(audioBlob);
                } else {
                    console.error("Aucun chunk audio disponible");
                }
            }, 200);
        }

        function startTimer() {
            if (!recordingTimer) {
                console.error("Le timer d'enregistrement n'est pas initialisé");
                return;
            }
            
            // Réinitialiser le style du timer pour s'assurer qu'il est visible
            recordingTimer.style.display = 'block';
            recordingTimer.style.position = 'absolute';
            recordingTimer.style.top = '-30px';
            recordingTimer.style.left = '50%';
            recordingTimer.style.transform = 'translateX(-50%)';
            recordingTimer.style.zIndex = '10';
            
            let seconds = 60; // Durée maximale de 60 secondes
            recordingTimer.textContent = '01:00';
            
            console.log("Timer d'enregistrement démarré", {
                timerElement: recordingTimer,
                timerVisible: recordingTimer.style.display,
                initialValue: recordingTimer.textContent,
                timerPosition: {
                    top: recordingTimer.style.top,
                    left: recordingTimer.style.left
                }
            });
            
            const timerInterval = setInterval(() => {
                if (!mediaRecorder || mediaRecorder.state === 'inactive') {
                    clearInterval(timerInterval);
                    return;
                }
                
                if (seconds <= 0) {
                    clearInterval(timerInterval);
                    stopRecording();
                } else {
                    seconds--;
                    const minutes = Math.floor(seconds / 60);
                    const remainingSeconds = seconds % 60;
                    recordingTimer.textContent = `${String(minutes).padStart(2, '0')}:${String(remainingSeconds).padStart(2, '0')}`;
                }
            }, 1000);
        }

        function showAudioDetailsModal(audioBlob) {
            if (!document || !document.body || !audioBlob) {
                console.error("Document, document.body ou audioBlob non disponible");
                return;
            }
            
            console.log("Préparation de la fenêtre de détails audio...");
            
            // Créer la modal
            const modal = document.createElement('div');
            modal.id = 'audioDetailsModal';
            modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            `;

            // Contenu de la modal
            const modalContent = document.createElement('div');
            modalContent.style.cssText = `
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            `;

            // Titre de la modal
            const title = document.createElement('h2');
            title.textContent = 'Détails de l\'audio';
            title.style.marginBottom = '20px';
            title.style.color = '#333';
            modalContent.appendChild(title);

            // Ajouter un lecteur audio pour prévisualiser
            const audioPreview = document.createElement('audio');
            audioPreview.controls = true;
            audioPreview.style.width = '100%';
            audioPreview.style.marginBottom = '15px';
            
            const audioUrl = URL.createObjectURL(audioBlob);
            audioPreview.src = audioUrl;
            modalContent.appendChild(audioPreview);

            // Input pour le titre
            const titleLabel = document.createElement('label');
            titleLabel.textContent = 'Titre de votre audio :';
            titleLabel.htmlFor = 'audio-title-input';
            titleLabel.style.display = 'block';
            titleLabel.style.marginBottom = '5px';
            titleLabel.style.fontWeight = 'bold';
            modalContent.appendChild(titleLabel);
            
            const titleInput = document.createElement('input');
            titleInput.id = 'audio-title-input';
            titleInput.type = 'text';
            titleInput.placeholder = 'Ajouter un titre';
            titleInput.classList.add('form-control');
            titleInput.style.marginBottom = '20px';
            modalContent.appendChild(titleInput);

            // Boutons
            const buttonsContainer = document.createElement('div');
            buttonsContainer.style.display = 'flex';
            buttonsContainer.style.justifyContent = 'space-between';
            buttonsContainer.style.marginTop = '20px';

            const uploadButton = document.createElement('button');
            uploadButton.textContent = 'Publier';
            uploadButton.classList.add('btn', 'btn-primary');
            uploadButton.style.padding = '8px 20px';
            uploadButton.addEventListener('click', async () => {
                const audioTitle = titleInput.value.trim();
                if (audioTitle) {
                    uploadButton.textContent = 'Publication en cours...';
                    uploadButton.disabled = true;
                    
                    await uploadAudio(audioBlob, audioTitle);
                    
                    // Libérer les ressources
                    URL.revokeObjectURL(audioUrl);
                    modal.remove();
                } else {
                    // Remplacer l'alerte par un message d'erreur visuel
                    titleInput.classList.add('is-invalid');
                    if (!titleInput.nextElementSibling || !titleInput.nextElementSibling.classList.contains('invalid-feedback')) {
                        const errorMsg = document.createElement('div');
                        errorMsg.classList.add('invalid-feedback');
                        errorMsg.textContent = 'Veuillez ajouter un titre pour votre audio.';
                        errorMsg.style.display = 'block';
                        titleInput.parentNode.insertBefore(errorMsg, titleInput.nextSibling);
                    }
                    titleInput.focus();
                }
            });
            buttonsContainer.appendChild(uploadButton);

            const cancelButton = document.createElement('button');
            cancelButton.textContent = 'Annuler';
            cancelButton.classList.add('btn', 'btn-secondary');
            cancelButton.style.padding = '8px 20px';
            cancelButton.addEventListener('click', () => {
                // Libérer les ressources
                URL.revokeObjectURL(audioUrl);
                modal.remove();
            });
            buttonsContainer.appendChild(cancelButton);

            modalContent.appendChild(buttonsContainer);
            modal.appendChild(modalContent);
            document.body.appendChild(modal);

            // Empêcher la fermeture de la modal en cliquant à l'extérieur
            modalContent.addEventListener('click', (event) => {
                event.stopPropagation();
            });
            modal.addEventListener('click', () => {
                URL.revokeObjectURL(audioUrl);
                modal.remove();
            });
            
            console.log("Fenêtre de détails audio affichée");
        }

        async function uploadAudio(audioBlob, title) {
            // Créer un fichier audio à partir du blob avec le bon type MIME
            const audioFile = new File([audioBlob], 'audio.mp3', { type: 'audio/mp3' });
            
            const formData = new FormData();
            formData.append('audio', audioFile);
            formData.append('title', title);

            try {
                const response = await fetch('upload_audio.php', {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                if (data.success) {
                    // Notification visuelle de succès supprimée, redirection directe
                    location.reload();
                } else {
                    console.error('Erreur:', data.message);
                    // Notification visuelle d'erreur supprimée
                }
            } catch (error) {
                console.error('Erreur:', error);
                // Notification visuelle d'erreur supprimée
            }
        }

        function showMicrophoneErrorModal() {
            if (!document || !document.body) {
                console.error("Document ou document.body n'est pas disponible");
                return;
            }
            
            // Créer la modal d'erreur
            const modal = document.createElement('div');
            modal.id = 'microphoneErrorModal';
            modal.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.7);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 1000;
            `;

            // Contenu de la modal
            const modalContent = document.createElement('div');
            modalContent.style.cssText = `
                background-color: white;
                padding: 20px;
                border-radius: 5px;
                width: 90%;
                max-width: 500px;
                text-align: center;
            `;

            // Icône d'erreur
            const errorIcon = document.createElement('div');
            errorIcon.innerHTML = '<i class="fas fa-microphone-slash" style="font-size: 3rem; color: #dc3545; margin-bottom: 15px;"></i>';
            modalContent.appendChild(errorIcon);

            // Titre de la modal
            const title = document.createElement('h2');
            title.textContent = 'Accès au microphone refusé';
            title.style.color = '#dc3545';
            modalContent.appendChild(title);

            // Message d'erreur
            const message = document.createElement('p');
            message.innerHTML = `
                Votre navigateur a bloqué l'accès au microphone. Pour utiliser cette fonctionnalité, vous devez autoriser l'accès au microphone :
                <br><br>
                <b>1.</b> Vérifiez la barre d'adresse de votre navigateur pour voir s'il y a une icône de microphone barrée
                <br>
                <b>2.</b> Cliquez sur cette icône et choisissez "Autoriser"
                <br>
                <b>3.</b> Rechargez la page et réessayez
                <br><br>
                Si le problème persiste, vérifiez les paramètres de votre navigateur ou utilisez un autre navigateur.
            `;
            message.style.textAlign = 'left';
            message.style.lineHeight = '1.5';
            modalContent.appendChild(message);

            // Bouton pour fermer
            const closeButton = document.createElement('button');
            closeButton.textContent = 'Compris';
            closeButton.classList.add('btn', 'btn-primary', 'mt-3');
            closeButton.addEventListener('click', () => {
                modal.remove();
            });
            modalContent.appendChild(closeButton);

            modal.appendChild(modalContent);
            document.body.appendChild(modal);

            // Empêcher la fermeture de la modal en cliquant à l'extérieur
            modalContent.addEventListener('click', (event) => {
                event.stopPropagation();
            });
            modal.addEventListener('click', () => {
                modal.remove();
            });
        }

        // Lorsque le DOM est chargé, initialiser tous les gestionnaires d'événements
        document.addEventListener('DOMContentLoaded', function() {
            // Mettre à jour les timestamps immédiatement et toutes les 30 secondes
            updateTimestamps();
            setInterval(updateTimestamps, 30000);

            // Récupérer le nombre de notifications non lues
            fetchUnreadNotificationsCount();
            // Vérifier périodiquement les nouvelles notifications (toutes les 60 secondes)
            setInterval(fetchUnreadNotificationsCount, 60000);

            // Ajouter la classe active-card aux cartes de réaction
            document.querySelectorAll('.emoji-btn.active').forEach(btn => {
                btn.closest('.reaction-card').classList.add('active-card');
            });

            // Fonction pour récupérer le nombre de notifications non lues
            function fetchUnreadNotificationsCount() {
                // Utiliser l'endpoint dédié pour récupérer uniquement le compteur
                fetch('home.php?get_unread_count=1')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const unreadCount = data.unreadCount;
                            const navUnreadBadge = document.getElementById('navUnreadBadge');
                            
                            if (unreadCount > 0) {
                                navUnreadBadge.textContent = unreadCount;
                                navUnreadBadge.style.display = 'flex';
                            } else {
                                navUnreadBadge.style.display = 'none';
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Erreur lors de la récupération des notifications:', error);
                        // En cas d'erreur, essayer l'ancienne approche
                        fetchFallbackUnreadCount();
                    });
            }
            
            // Fonction de secours pour récupérer le nombre de notifications non lues
            function fetchFallbackUnreadCount() {
                fetch('notifications.php')
                    .then(response => response.text())
                    .then(html => {
                        // Essayer de trouver le compteur de notifications dans le HTML
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const unreadBadge = doc.querySelector('#navUnreadBadge');
                        
                        if (unreadBadge && unreadBadge.textContent) {
                            const count = parseInt(unreadBadge.textContent.trim());
                            if (!isNaN(count) && count > 0) {
                                const navUnreadBadge = document.getElementById('navUnreadBadge');
                                navUnreadBadge.textContent = count;
                                navUnreadBadge.style.display = 'flex';
                            }
                        }
                    })
                    .catch(error => console.error('Erreur lors de la récupération alternative:', error));
            }

            // Initialiser les éléments d'enregistrement audio
            floatingRecord = document.querySelector('.floating-record');
            recordingTimer = document.createElement('div');
            recordingTimer.classList.add('recording-timer');
            recordingTimer.textContent = '01:00';
            floatingRecord.appendChild(recordingTimer);

            // Variable pour suivre l'état d'enregistrement
            let isRecording = false;

            // Modifier le gestionnaire d'événements pour basculer entre démarrage et arrêt
            floatingRecord.addEventListener('click', function(e) {
                e.preventDefault();
                
                if (isRecording) {
                    stopRecording();
                    isRecording = false;
                } else {
                    startRecording(e);
                    isRecording = true;
                }
            });

            // Initialiser les éléments de recherche
            const searchButton = document.getElementById('search-button');
            const searchBar = document.getElementById('search-bar');
            const searchInput = document.getElementById('search-input');
            const submitSearch = document.getElementById('submit-search');

            if (searchButton && searchBar && searchInput && submitSearch) {
                searchButton.addEventListener('click', function() {
                    searchButton.style.display = 'none';
                    searchBar.style.display = 'flex';
                    setTimeout(() => {
                        searchInput.focus();
                    }, 100);
                });

                submitSearch.addEventListener('click', function() {
                    const searchTerm = searchInput.value.trim().toLowerCase();
                    const audioCards = document.querySelectorAll('.audio-card');
                    
                    let resultsFound = false;

                    audioCards.forEach(card => {
                        const audioTitle = card.querySelector('.audio-title').textContent.toLowerCase();
                        const username = card.querySelector('.username').textContent.toLowerCase();
                        if (audioTitle.includes(searchTerm) || username.includes(searchTerm)) {
                            card.style.display = 'block';
                            resultsFound = true;
                        } else {
                            card.style.display = 'none';
                        }
                    });

                    // Vérifier s'il y a des résultats
                    if (!resultsFound) {
                        // Créer ou mettre à jour le message "aucun résultat"
                        let noResultsMessage = document.getElementById('no-results-message');
                        
                        if (!noResultsMessage) {
                            noResultsMessage = document.createElement('div');
                            noResultsMessage.id = 'no-results-message';
                            noResultsMessage.style.cssText = `
                                padding: 20px;
                                text-align: center;
                                color: white;
                                background-color: rgba(0, 0, 0, 0.2);
                                border-radius: 10px;
                                margin: 20px auto;
                                max-width: 90%;
                            `;
                            document.querySelector('.main-content').appendChild(noResultsMessage);
                        }
                        
                        noResultsMessage.innerHTML = `
                            <i class="fas fa-search" style="font-size: 2rem; margin-bottom: 10px; opacity: 0.5;"></i>
                            <p>Aucun résultat ne correspond à "<strong>${searchTerm}</strong>"</p>
                            <p style="font-size: 0.9em; opacity: 0.8;">Essayez avec d'autres termes ou consultez le fil d'actualité complet</p>
                        `;
                        noResultsMessage.style.display = 'block';
                    } else {
                        // Cacher le message s'il existe
                        const existingMessage = document.getElementById('no-results-message');
                        if (existingMessage) {
                            existingMessage.style.display = 'none';
                        }
                    }

                    searchBar.style.display = 'none';
                    searchButton.style.display = 'block';
                    searchInput.value = '';
                });
                
                // Fermer la recherche en appuyant sur Échap
                searchInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        searchBar.style.display = 'none';
                        searchButton.style.display = 'block';
                        searchInput.value = '';
                        
                        // Réinitialiser la recherche et afficher toutes les cartes
                        document.querySelectorAll('.audio-card').forEach(card => {
                            card.style.display = 'block';
                        });
                        
                        // Cacher le message "aucun résultat"
                        const noResultsMessage = document.getElementById('no-results-message');
                        if (noResultsMessage) {
                            noResultsMessage.style.display = 'none';
                        }
                    } else if (e.key === 'Enter') {
                        submitSearch.click();
                    }
                });
            }

            // Gestionnaires pour les commentaires
            document.querySelectorAll('.send-comment-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const audioCard = this.closest('.audio-card');
                    const input = audioCard.querySelector('.comment-input');
                    const audioId = audioCard.getAttribute('data-audio-id');
                    const content = input.value.trim();

                    if (content) {
                        fetch('', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: new URLSearchParams({
                                'action': 'add_comment',
                                'audio_id': audioId,
                                'content': content
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                input.value = '';
                                loadComments(audioId);
                            } else {
                                console.error('Erreur:', data.message);
                                // Afficher visuellement l'erreur sous forme de texte rouge
                                input.classList.add('is-invalid');
                                if (!input.nextElementSibling || !input.nextElementSibling.classList.contains('invalid-feedback')) {
                                    const errorMsg = document.createElement('div');
                                    errorMsg.classList.add('invalid-feedback');
                                    errorMsg.textContent = data.message || 'Erreur lors de l\'ajout du commentaire';
                                    errorMsg.style.display = 'block';
                                    input.parentNode.insertBefore(errorMsg, input.nextSibling);
                                }
                            }
                        })
                        .catch(error => console.error('Erreur:', error));
                    } else {
                        // Remplacer l'alerte par une mise en évidence visuelle du champ
                        input.classList.add('is-invalid');
                        if (!input.nextElementSibling || !input.nextElementSibling.classList.contains('invalid-feedback')) {
                            const errorMsg = document.createElement('div');
                            errorMsg.classList.add('invalid-feedback');
                            errorMsg.textContent = 'Veuillez écrire un commentaire';
                            errorMsg.style.display = 'block';
                            input.parentNode.insertBefore(errorMsg, input.nextSibling);
                        }
                        input.focus();
                    }
                });
            });

            document.querySelectorAll('.toggle-comments-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const audioCard = this.closest('.audio-card');
                    const commentsContainer = audioCard.querySelector('.comments-container');
                    const icon = this.querySelector('i');
                    if (commentsContainer.style.display === 'none' || commentsContainer.style.display === '') {
                        loadComments(audioCard.getAttribute('data-audio-id'));
                        commentsContainer.style.display = 'block';
                        icon.classList.remove('fa-comment');
                        icon.classList.add('fa-comments');
                    } else {
                        commentsContainer.style.display = 'none';
                        icon.classList.remove('fa-comments');
                        icon.classList.add('fa-comment');
                    }
                });
            });

            // Gestionnaires pour les réactions
            document.querySelectorAll('.emoji-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const audioCard = this.closest('.audio-card');
                    const audioId = audioCard.getAttribute('data-audio-id');
                    const reactionType = this.getAttribute('data-reaction-type');
                    const userId = '<?= htmlspecialchars($_SESSION["user_id"] ?? 0); ?>';
                    const reactionCard = this.closest('.reaction-card');

                    fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: new URLSearchParams({
                            'action': 'add_reaction',
                            'audio_id': audioId,
                            'user_id': userId,
                            'type': reactionType
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            // Récupérer tous les boutons de réaction dans cette carte audio
                            const reactionButtons = audioCard.querySelectorAll('.emoji-btn');
                            const allReactionCards = audioCard.querySelectorAll('.reaction-card');
                            
                            // Mise à jour des compteurs et classes en fonction de l'action
                            if (data.action === 'added') {
                                // Ajouter la classe active au bouton cliqué
                                this.classList.add('active');
                                reactionCard.classList.add('active-card');
                                // Incrémenter le compteur
                                const countElement = this.parentNode.querySelector('.reaction-count');
                                let count = parseInt(countElement.textContent) || 0;
                                countElement.textContent = count + 1;
                            } else if (data.action === 'removed') {
                                // Supprimer la classe active
                                this.classList.remove('active');
                                reactionCard.classList.remove('active-card');
                                // Décrémenter le compteur
                                const countElement = this.parentNode.querySelector('.reaction-count');
                                let count = parseInt(countElement.textContent) || 0;
                                countElement.textContent = Math.max(0, count - 1);
                            } else if (data.action === 'changed') {
                                // Supprimer la classe active de tous les boutons
                                reactionButtons.forEach(btn => {
                                    if (btn.getAttribute('data-reaction-type') === data.old_type) {
                                        btn.classList.remove('active');
                                        btn.closest('.reaction-card').classList.remove('active-card');
                                        // Décrémenter l'ancien compteur
                                        const countElement = btn.parentNode.querySelector('.reaction-count');
                                        let count = parseInt(countElement.textContent) || 0;
                                        countElement.textContent = Math.max(0, count - 1);
                                    }
                                });
                                
                                // Ajouter la classe active au bouton cliqué
                                this.classList.add('active');
                                reactionCard.classList.add('active-card');
                                // Incrémenter le nouveau compteur
                                const countElement = this.parentNode.querySelector('.reaction-count');
                                let count = parseInt(countElement.textContent) || 0;
                                countElement.textContent = count + 1;
                            }
                        } else {
                            console.error('Erreur de réaction:', data.message);
                            // Notification visuelle d'erreur supprimée
                        }
                    })
                    .catch(error => console.error('Erreur:', error));
                });
            });

            // Gestionnaires pour les signalements et partages
            document.querySelectorAll('.report-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const audioCard = this.closest('.audio-card');
                    const audioId = audioCard.getAttribute('data-audio-id');
                    const userId = '<?= htmlspecialchars($_SESSION["user_id"] ?? 0); ?>';

                    // Créer une modale pour le formulaire de signalement au lieu d'utiliser prompt
                    const modal = document.createElement('div');
                    modal.style.cssText = `
                        position: fixed;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        background-color: rgba(0, 0, 0, 0.7);
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        z-index: 1000;
                    `;
                    
                    const modalContent = document.createElement('div');
                    modalContent.style.cssText = `
                        background-color: white;
                        padding: 20px;
                        border-radius: 10px;
                        width: 90%;
                        max-width: 500px;
                    `;
                    
                    const modalTitle = document.createElement('h4');
                    modalTitle.textContent = 'Signaler cet audio';
                    modalTitle.style.marginBottom = '15px';
                    
                    const reasonInput = document.createElement('textarea');
                    reasonInput.placeholder = 'Veuillez indiquer le motif du signalement';
                    reasonInput.style.width = '100%';
                    reasonInput.style.padding = '10px';
                    reasonInput.style.borderRadius = '5px';
                    reasonInput.style.border = '1px solid #ddd';
                    reasonInput.style.marginBottom = '15px';
                    reasonInput.style.minHeight = '100px';
                    
                    const buttonContainer = document.createElement('div');
                    buttonContainer.style.display = 'flex';
                    buttonContainer.style.justifyContent = 'space-between';
                    
                    const cancelBtn = document.createElement('button');
                    cancelBtn.textContent = 'Annuler';
                    cancelBtn.classList.add('btn', 'btn-secondary');
                    cancelBtn.addEventListener('click', () => modal.remove());
                    
                    const submitBtn = document.createElement('button');
                    submitBtn.textContent = 'Signaler';
                    submitBtn.classList.add('btn', 'btn-danger');
                    
                    // Feedback message
                    const feedbackMsg = document.createElement('div');
                    feedbackMsg.style.display = 'none';
                    feedbackMsg.style.padding = '10px';
                    feedbackMsg.style.marginBottom = '15px';
                    feedbackMsg.style.borderRadius = '5px';
                    
                    submitBtn.addEventListener('click', () => {
                        const reason = reasonInput.value.trim();
                        if (!reason) {
                            reasonInput.style.borderColor = 'red';
                            feedbackMsg.textContent = 'Le motif du signalement est requis.';
                            feedbackMsg.style.backgroundColor = '#f8d7da';
                            feedbackMsg.style.color = '#721c24';
                            feedbackMsg.style.display = 'block';
                            return;
                        }
                        
                        fetch('', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: new URLSearchParams({
                                'action': 'add_report',
                                'audio_id': audioId,
                                'user_id': userId,
                                'motif': reason
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                feedbackMsg.textContent = 'Signalement effectué avec succès.';
                                feedbackMsg.style.backgroundColor = '#d4edda';
                                feedbackMsg.style.color = '#155724';
                                feedbackMsg.style.display = 'block';
                                
                                // Fermer la modale après 2 secondes
                                setTimeout(() => modal.remove(), 2000);
                            } else {
                                feedbackMsg.textContent = data.message || 'Une erreur est survenue.';
                                feedbackMsg.style.backgroundColor = '#f8d7da';
                                feedbackMsg.style.color = '#721c24';
                                feedbackMsg.style.display = 'block';
                            }
                        })
                        .catch(error => {
                            console.error('Erreur:', error);
                            feedbackMsg.textContent = 'Une erreur est survenue lors du signalement.';
                            feedbackMsg.style.backgroundColor = '#f8d7da';
                            feedbackMsg.style.color = '#721c24';
                            feedbackMsg.style.display = 'block';
                        });
                    });
                    
                    buttonContainer.appendChild(cancelBtn);
                    buttonContainer.appendChild(submitBtn);
                    
                    modalContent.appendChild(modalTitle);
                    modalContent.appendChild(feedbackMsg);
                    modalContent.appendChild(reasonInput);
                    modalContent.appendChild(buttonContainer);
                    modal.appendChild(modalContent);
                    document.body.appendChild(modal);
                    
                    // Empêcher la fermeture en cliquant à l'extérieur
                    modalContent.addEventListener('click', e => e.stopPropagation());
                    modal.addEventListener('click', () => modal.remove());
                    
                    // Focus sur l'input
                    reasonInput.focus();
                });
            });

            document.querySelectorAll('.share-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const audioCard = this.closest('.audio-card');
                    const audioId = audioCard.getAttribute('data-audio-id');
                    const audioUrl = audioCard.querySelector('audio source').getAttribute('src');

                    if (audioUrl) {
                        navigator.share({
                            title: 'Partage d\'audio',
                            text: 'Écoutez cet audio intéressant sur C\'chic !',
                            url: audioUrl
                        }).then(() => {
                            // Enregistrer le partage dans la base de données
                            fetch('', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                                },
                                body: new URLSearchParams({
                                    'action': 'add_share',
                                    'audio_id': audioId
                                })
                            }).then(response => response.json())
                            .then(data => {
                                if (data.status !== 'success') {
                                    console.error('Erreur lors de l\'enregistrement du partage:', data.message);
                                }
                            }).catch(error => {
                                console.error('Erreur lors de l\'enregistrement du partage:', error);
                            });
                        }).catch(error => {
                            console.error('Erreur lors du partage:', error);
                            
                            // Créer une notification temporaire au lieu d'une alerte
                            const notification = document.createElement('div');
                            notification.textContent = 'Le partage n\'est pas pris en charge sur ce navigateur.';
                            notification.style.cssText = `
                                position: fixed;
                                bottom: 20px;
                                left: 50%;
                                transform: translateX(-50%);
                                background-color: rgba(0, 0, 0, 0.8);
                                color: white;
                                padding: 10px 20px;
                                border-radius: 5px;
                                z-index: 1000;
                                animation: fadeOut 3s forwards 1s;
                            `;
                            
                            // Ajouter une animation de fondu
                            const style = document.createElement('style');
                            style.textContent = `
                                @keyframes fadeOut {
                                    from { opacity: 1; }
                                    to { opacity: 0; visibility: hidden; }
                                }
                            `;
                            document.head.appendChild(style);
                            
                            document.body.appendChild(notification);
                            setTimeout(() => notification.remove(), 4000);
                        });
                    } else {
                        // Créer une notification temporaire au lieu d'une alerte
                        const notification = document.createElement('div');
                        notification.textContent = 'Impossible de partager cet audio.';
                        notification.style.cssText = `
                            position: fixed;
                            bottom: 20px;
                            left: 50%;
                            transform: translateX(-50%);
                            background-color: rgba(0, 0, 0, 0.8);
                            color: white;
                            padding: 10px 20px;
                            border-radius: 5px;
                            z-index: 1000;
                            animation: fadeOut 3s forwards 1s;
                        `;
                        
                        document.body.appendChild(notification);
                        setTimeout(() => notification.remove(), 4000);
                    }
                });
            });
        });

        // Fonction pour charger les commentaires
        function loadComments(audioId) {
            fetch(`?action=get_comments&audio_id=${audioId}`)
            .then(response => response.json())
            .then(data => {
                const audioCard = document.querySelector(`.audio-card[data-audio-id="${audioId}"]`);
                const commentsContainer = audioCard.querySelector('.comments-container');
                if (data.status === 'success') {
                    commentsContainer.innerHTML = data.comments.map(comment => `
                        <div class="comment">
                            <strong>${comment.nom_prenoms}:</strong> ${comment.content}
                            <div class="comment-date" style="font-size: 0.8em; color: gray;">
                                ${comment.created_at}
                            </div>
                        </div>
                    `).join('');
                    const commentCountEl = audioCard.querySelector('.comment-count');
                    commentCountEl.textContent = data.comments.length;
                    commentsContainer.style.display = 'block';
                }
            })
            .catch(error => console.error('Erreur lors du chargement des commentaires:', error));
        }
    </script>
</body>
</html>