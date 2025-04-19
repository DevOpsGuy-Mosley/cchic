<?php
// Démarrer la mise en tampon de sortie
// ob_start();

// Activer l'affichage des erreurs pour le débogage
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_log("Starting profile.php");

// Inclure la vérification de session qui vérifie également si le compte est actif
require_once 'check_session.php';

// La vérification de session est déjà faite dans check_session.php, pas besoin de la répéter ici
// if (!isset($_SESSION['user_id'])) {
//     header('Location: login.php');
//     exit();
// }

// Traitement des requêtes POST (API)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Nettoyer toute sortie précédente
    ob_end_clean();
    
    // Définir l'en-tête JSON
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        require 'database.php';
        
        $response = ['success' => false, 'message' => ''];
        $userId = $_SESSION['user_id'];
        
        // Validation des entrées
        $newUsername = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
        $newEmail = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $newDescription = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
        $newPassword = filter_input(INPUT_POST, 'password');

        if (empty($newUsername)) {
            throw new Exception("Le nom d'utilisateur est requis");
        }

        // Gestion de l'upload de photo
        $photoUpdated = false;
        $photoName = null;
        
        if (isset($_FILES['photo_profil']) && $_FILES['photo_profil']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/profile_photos/';
            
            // Créer le dossier s'il n'existe pas
            if (!file_exists($uploadDir) && !mkdir($uploadDir, 0777, true)) {
                throw new Exception("Impossible de créer le dossier d'upload");
            }

            // Vérifier le type de fichier
            $fileInfo = pathinfo($_FILES['photo_profil']['name']);
            $extension = strtolower($fileInfo['extension']);
            
            if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                throw new Exception("Type de fichier non autorisé. Utilisez JPG, PNG ou GIF.");
            }

            // Générer un nom unique
            $photoName = uniqid('profile_') . '.' . $extension;
            $targetPath = $uploadDir . $photoName;

            // Récupérer l'ancienne photo
            $stmt = $pdo->prepare("SELECT photo_profil FROM register WHERE id = ?");
            $stmt->execute([$userId]);
            $oldPhoto = $stmt->fetchColumn();

            // Supprimer l'ancienne photo
            if ($oldPhoto && file_exists($uploadDir . $oldPhoto)) {
                @unlink($uploadDir . $oldPhoto);
            }

            // Upload de la nouvelle photo
            if (!move_uploaded_file($_FILES['photo_profil']['tmp_name'], $targetPath)) {
                throw new Exception("Erreur lors de l'upload de la photo");
            }
            
            $photoUpdated = true;
        }

        // Mise à jour du profil
        $sql = "UPDATE register SET nom_prenoms = :username";
        $params = ['username' => $newUsername, 'user_id' => $userId];

        if (!empty($newEmail)) {
            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("L'adresse email n'est pas valide");
            }
            $sql .= ", email = :email";
            $params['email'] = $newEmail;
        }

        if (isset($newDescription)) {
            $sql .= ", description = :description";
            $params['description'] = $newDescription;
        }

        if (!empty($newPassword)) {
            $sql .= ", password = :password";
            $params['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
        }

        if ($photoUpdated) {
            $sql .= ", photo_profil = :photo_profil";
            $params['photo_profil'] = $photoName;
        }

        $sql .= " WHERE id = :user_id";

        $stmt = $pdo->prepare($sql);
        if (!$stmt->execute($params)) {
            throw new Exception("Erreur lors de la mise à jour du profil");
        }

        $response = [
            'success' => true,
            'message' => 'Profil mis à jour avec succès',
            'photo_url' => $photoUpdated ? 'uploads/profile_photos/' . $photoName : null
        ];

    } catch (Exception $e) {
        $response = [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }

    echo json_encode($response);
    exit();
}

// Pour les requêtes GET (affichage de la page)
try {
    require 'database.php';
} catch (Exception $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}

// Récupération des informations de l'utilisateur
$userId = isset($_GET['user_id']) ? $_GET['user_id'] : $_SESSION['user_id'];
$isOwnProfile = $userId == $_SESSION['user_id'];

// Récupération des audios de l'utilisateur
$stmt = $pdo->prepare("
    SELECT a.*, 
        (SELECT COUNT(*) FROM reactions WHERE audio_id = a.id AND type = 'like') AS like_count,
        (SELECT COUNT(*) FROM reactions WHERE audio_id = a.id AND type = 'dislike') AS dislike_count,
        (SELECT COUNT(*) FROM reactions WHERE audio_id = a.id AND type = 'laugh') AS laugh_count,
        (SELECT COUNT(*) FROM comments WHERE audio_id = a.id) AS comment_count
    FROM audio a 
    WHERE a.user_id = :user_id
    ORDER BY a.datenote DESC
");
$stmt->bindParam(':user_id', $userId);
$stmt->execute();
$userAudios = $stmt->fetchAll();

try {
    $stmt = $pdo->prepare("SELECT nom_prenoms, email, description, genre, photo_profil FROM register WHERE id = :user_id");
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    $userInfo = $stmt->fetch();

    if ($userInfo) {
        $avatarInitials = strtoupper(substr($userInfo['nom_prenoms'], 0, 2));
        $genreClass = strtolower($userInfo['genre']); // 'male', 'female' ou 'other'
        $photoUrl = $userInfo['photo_profil'] ? 'uploads/profile_photos/' . $userInfo['photo_profil'] : '';
    } else {
        header('Location: home.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Erreur de base de données : " . $e->getMessage());
    header('Location: error.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - C'chic</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="profile.css">
    <style>
        .edit-profile-section {
            display: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .edit-profile-section.active {
            opacity: 1;
        }
        
        .profile-stats-section.hidden {
            display: none;
        }

        .audio-title-date {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 5px;
            width: 100%;
        }

        .separator {
            display: none;
        }

        .audio-title {
            margin: 0;
            font-size: 1.1em;
            text-align: center;
            font-weight: normal;
        }

        .audio-published-time {
            margin: 0;
            color: #888;
            font-size: 0.9em;
            text-align: center;
        }

        @media (min-width: 768px) {
            .audio-title-date {
                flex-direction: row;
                gap: 15px;
            }

            .separator {
                display: inline;
                color: #888;
                font-weight: normal;
            }
        }

        .presentation-frame {
            width: 100%;
            max-width: 600px;
            margin: 10px auto;
            padding: 8px;
            background-color: #212121;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            min-height: auto;
            height: auto;
        }

        @media (max-width: 768px) {
            .presentation-frame {
                max-width: 90%;
                margin: 5px auto;
                padding: 5px;
                border-radius: 6px;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <div class="profile-header">
            <?php if (!$isOwnProfile): ?>
            <a href="home.php" class="back-button">
                <i class="fas fa-arrow-left"></i>
            </a>
            <?php endif; ?>
        </div>

        <header class="profile-header-container">
            <div class="profile-identity">
                <div class="profile-photo-container">
                    <?php if ($photoUrl): ?>
                        <img src="<?php echo htmlspecialchars($photoUrl); ?>" alt="Photo de profil" class="profile-photo" style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover;">
                    <?php else: ?>
                        <div class="avatar <?php echo $genreClass; ?>"><?php echo $avatarInitials; ?></div>
                    <?php endif; ?>
                </div>
                <h1 class="profile-name" id="display-username"><?php echo htmlspecialchars($userInfo['nom_prenoms']); ?></h1>
            </div>
            <div class="presentation-frame">
                <p id="description-text"><?php echo htmlspecialchars($userInfo['description'] ?? ''); ?></p>
            </div>
            <div class="profile-actions">
                <?php if ($isOwnProfile): ?>
                    <button class="btn-edit-profile" onclick="toggleEditProfile()">
                        <i class="fas fa-pencil-alt" style="margin-right: 8px;"></i> Modifier
                    </button>
                <?php endif; ?>
            </div>
        </header>

        <section class="profile-stats">
            <div class="stat-item">
                <i class="fas fa-thumbs-up"></i>
                <span class="stat-value"><?= array_sum(array_column($userAudios, 'like_count')) ?></span>
                <span class="stat-label">J'aime</span>
            </div>
            <div class="stat-item">
                <i class="fas fa-thumbs-down"></i>
                <span class="stat-value"><?= array_sum(array_column($userAudios, 'dislike_count')) ?></span>
                <span class="stat-label">J'aime pas</span>
            </div>
            <div class="stat-item">
                <i class="fas fa-laugh"></i>
                <span class="stat-value"><?= array_sum(array_column($userAudios, 'laugh_count')) ?></span>
                <span class="stat-label">Rires</span>
            </div>
            <div class="stat-item">
                <i class="fas fa-comment"></i>
                <span class="stat-value"><?= array_sum(array_column($userAudios, 'comment_count')) ?></span>
                <span class="stat-label">Commentaires</span>
            </div>
        </section>

        <section class="edit-profile-section" id="edit-profile-section">
             <div class="edit-profile-fields">
            <h1 style="text-align: center; margin-bottom: 20px;">Modifier les informations</h1>
            <div id="form-message" class="form-message" style="display: none;"></div>
            
            <div class="form-field-container">
                <!-- <div class="field-icon">
                    <i class="fas fa-camera"></i>
                    <span>Photo de profil</span>
                </div> -->
                <div class="field-edit-frame photo-frame">
                    <div class="form-group photo-upload-group">
                        <div class="photo-preview-container">
                            <?php if ($photoUrl): ?>
                                <img id="photo-preview" src="<?php echo htmlspecialchars($photoUrl); ?>" alt="Photo de profil">
                            <?php else: ?>
                                <div id="photo-preview-placeholder" class="avatar <?php echo $genreClass; ?>"><?php echo $avatarInitials; ?></div>
                            <?php endif; ?>
                            <div class="photo-upload-overlay">
                                <i class="fas fa-camera"></i>
                                <span>Changer la photo</span>
                            </div>
                        </div>
                        <input type="file" id="photo-upload" name="photo_profil" accept="image/*" style="display: none;">
                    </div>
                </div>
            </div>
            
            <br>

            <div class="form-field-container">
                <div class="field-icon">
                <i class="fas fa-user"></i>
                <span>Nom d'utilisateur</span>
                </div>
                <div class="field-edit-frame">
                <div class="form-group">
                    <input type="text" id="edit-username" value="<?php echo htmlspecialchars($userInfo['nom_prenoms']); ?>" style="width: 100%; padding: 10px; background-color: #212121; color: white; border: 1px solid #444;">
                </div>
                </div>
            </div>


                <div class="form-field-container">
                    <div class="field-icon">
                        <i class="fas fa-envelope"></i>
                        <span>E-mail</span>
                    </div>
                    <div class="field-edit-frame">
                        <div class="form-group">
                            <input type="email" id="edit-email" value="<?php echo htmlspecialchars($userInfo['email']); ?>" style="width: 100%; padding: 10px; background-color: #212121; color: white; border: 1px solid #444;">
                        </div>
                    </div>
                </div>

                <div class="form-field-container">
                    <div class="field-icon">
                        <i class="fas fa-lock"></i>
                        <span>Mot de passe</span>
                    </div>
                    <div class="field-edit-frame">
                        <div class="form-group">
                            <input type="password" id="edit-password" placeholder="Laissez vide pour ne pas changer" style="width: 100%; padding: 10px; background-color: #212121; color: white; border: 1px solid #444;">
                        </div>
                    </div>
                </div>

                <div class="form-field-container">
                    <div class="field-icon">
                        <i class="fas fa-comment-alt"></i>
                        <span>Description</span>
                    </div>
                    <div class="field-edit-frame">
                        <div class="form-group">
                            <textarea id="edit-description" maxlength="900" oninput="updateWordCount()" style="width: 100%; padding: 10px; background-color: #212121; color: white; border: 1px solid #444; height: 50px; resize: none;"><?php echo htmlspecialchars($userInfo['description'] ?? ''); ?></textarea>
                            <div class="word-count" id="word-count"><?php echo $userInfo['description'] ? strlen($userInfo['description']) : '0'; ?>/100 mot(s).</div>
                        </div>
                    </div>
                </div>

                <div class="edit-profile-buttons">
                    <button class="btn-cancel-profile" onclick="cancelEditProfile()">Annuler</button>
                    <button class="btn-submit-profile" onclick="submitProfileChanges()">
                         <i class="fas fa-save" style="margin-right: 8px;"></i> Sauvegarder
                    </button>
                </div>
            </div>
        </section>

        <section class="profile-stats-section" id="profile-stats-section">
            <h2>
                <span><i class="fas fa-microphone-alt" style="margin-right: 10px; color: var(--accent-color);"></i> Mes audios</span>
                <span class="audio-count-bubble" id="audio-count"><?php echo count($userAudios); ?></span>
            </h2>
            
            <!-- Liste des audios -->
            <div class="audio-list-container" id="audio-list">
                <?php if (empty($userAudios)): ?>
                    <div class="no-audio-message">
                        <i class="fas fa-music" style="font-size: 2em; margin-bottom: 10px;"></i>
                        <p>Vous n'avez pas encore publié d'audio</p>
                    </div>
                <?php else: ?>
                    <div class="audio-list-loading" style="display: none;">
                        <div class="loading-spinner"></div>
                        <p>Chargement des audios...</p>
                    </div>
                    <?php foreach ($userAudios as $audio): ?>
                        <div class="audio-item" data-audio-id="<?php echo htmlspecialchars($audio['id']); ?>" data-audio-path="<?php echo htmlspecialchars($audio['notevocale']); ?>">
                            <div class="audio-main-content">
                                <div class="audio-play-wrapper" onclick="togglePlay(this, <?php echo htmlspecialchars($audio['duration'] ?? 30); ?>)">
                                    <span class="audio-timer"><?php echo htmlspecialchars($audio['duration'] ?? 30); ?></span>
                                    <i class="fas fa-play audio-play-icon"></i>
                                </div>
                                <div class="audio-info">
                                    <div class="audio-title-date">
                                        <h3 class="audio-title">Titre : <?php echo htmlspecialchars($audio['title'] ?? 'Sans titre'); ?></h3>
                                        <span class="separator">|</span>
                                        <p class="audio-published-time">
                                            <?php 
                                            $date = new DateTime($audio['datenote']);
                                            echo 'Publié le ' . $date->format('d/m/Y à H:i');
                                            ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="audio-actions">
                                <?php if ($isOwnProfile): ?>
                                <button class="delete-audio-button" onclick="deleteAudio(this)" title="Supprimer cet audio">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

    </div>

    <nav class="navbar">
        <a href="home.php" class="nav-item" data-page="home">
            <i class="fas fa-home nav-icon"></i><span class="nav-text">Accueil</span>
        </a>
        <a href="profile.php" class="nav-item active" data-page="profile">
            <i class="fas fa-user nav-icon"></i><span class="nav-text">Profil</span>
        </a>
        <a href="notifications.php" class="nav-item" data-page="notifications">
            <i class="fas fa-bell nav-icon"></i><span class="nav-text">Notifications</span>
        </a>
        <a href="logout.php" class="nav-item" data-page="logout">
            <i class="fas fa-sign-out-alt nav-icon"></i><span class="nav-text">Déconnexion</span>
        </a>
    </nav>

    <script>
        const editSection = document.getElementById('edit-profile-section');
        const editFieldsContainer = editSection.querySelector('.edit-profile-fields');
        const audioSection = document.getElementById('profile-stats-section');
        const displayUsername = document.getElementById('display-username');
        const displayDescription = document.getElementById('description-text');
        const displayAvatar = document.getElementById('display-avatar');
        const editUsernameInput = document.getElementById('edit-username');
        const editDescriptionInput = document.getElementById('edit-description');
        const editEmailInput = document.getElementById('edit-email');
        const editPasswordInput = document.getElementById('edit-password');
        const wordCountDisplay = document.getElementById('word-count');
        const audioListContainer = document.getElementById('audio-list');
        const audioCountElement = document.getElementById('audio-count');
        const statusMessage = document.getElementById('status-message');
        const formMessage = document.getElementById('form-message');
        const editProfileButton = document.querySelector('.btn-edit-profile');

        let activeAudioTimer = null;
        let activeAudioWrapper = null;

        const MAX_WORDS = 100;
        const MAX_CHARS = 900;

        function showStatusMessage(message, isSuccess = true, duration = 3000) {
            const statusMessageElement = document.createElement('div');
            statusMessageElement.className = `status-message ${isSuccess ? 'success' : 'error'}`;
            statusMessageElement.textContent = message;
            
            document.body.appendChild(statusMessageElement);
            
            setTimeout(() => {
                statusMessageElement.classList.add('show');
            }, 100);
            
            setTimeout(() => {
                statusMessageElement.classList.remove('show');
                setTimeout(() => {
                    statusMessageElement.remove();
                }, 300);
            }, duration);
        }

        function toggleEditProfile() {
            if (!editSection || !audioSection || !editProfileButton || !editFieldsContainer) return;
            
            const isEditing = editSection.classList.contains('active');
            
            if (!isEditing) {
                // Remplir les champs avec les valeurs actuelles
                editUsernameInput.value = displayUsername.textContent.trim();
                editDescriptionInput.value = displayDescription.textContent.trim();
                editEmailInput.value = editEmailInput.placeholder;
                editPasswordInput.value = '';
                
                // Mettre à jour le compteur de mots
                updateWordCount();
                
                // Réinitialiser les messages
                formMessage.style.display = 'none';
                
                // Afficher la section d'édition
                editSection.style.display = 'block';
                
                // Attendre le prochain frame pour l'animation
                setTimeout(() => {
                    audioSection.classList.add('hidden');
                    editSection.classList.add('active');
                    editFieldsContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }, 10);
                
                editProfileButton.innerHTML = '<i class="fas fa-times" style="margin-right: 8px;"></i> Annuler';
            } else {
                // Masquer la section d'édition
                editSection.classList.remove('active');
                
                // Attendre la fin de l'animation
                setTimeout(() => {
                    audioSection.classList.remove('hidden');
                    editSection.style.display = 'none';
                }, 300);
                
                editProfileButton.innerHTML = '<i class="fas fa-pencil-alt" style="margin-right: 8px;"></i> Modifier';
            }
        }

        function cancelEditProfile() { 
            toggleEditProfile(); 
        }

        function updateWordCount() {
            if (!editDescriptionInput || !wordCountDisplay) return;
            let text = editDescriptionInput.value;
            let currentLength = text.length;
            let words = text.trim() === '' ? 0 : text.trim().split(/\s+/).filter(Boolean).length;
            let needsTruncation = false;

            if (words > MAX_WORDS) {
                needsTruncation = true;
                const wordArray = text.trim().split(/\s+/).filter(Boolean);
                const limitedWords = wordArray.slice(0, MAX_WORDS);
                text = limitedWords.join(' ');
                editDescriptionInput.value = text;
                currentLength = text.length;
                words = MAX_WORDS;
            }

            const remainingChars = MAX_CHARS - currentLength;
            let message = `${words}/${MAX_WORDS} mot(s). `;
            let color = 'var(--text-secondary)';

            if (currentLength > MAX_CHARS) {
                message += `Limite de ${MAX_CHARS} car. dépassée !`;
                color = 'var(--delete-color)';
            } else if (words >= MAX_WORDS) {
                message += `Limite atteinte.`;
                color = needsTruncation ? 'var(--delete-color)' : 'var(--accent-color)';
            }

            wordCountDisplay.textContent = message;
            wordCountDisplay.style.color = color;
        }

        function submitProfileChanges() {
            const newUsername = editUsernameInput.value.trim();
            const newDescription = editDescriptionInput.value.trim();
            const newEmail = editEmailInput.value.trim();
            const newPassword = editPasswordInput.value;
            const photoFile = photoUpload.files[0];

            // Validation côté client
            if (!newUsername) {
                showFormMessage("Le nom d'utilisateur est requis", false);
                editUsernameInput.focus();
                return;
            }

            // Préparation des données
            const formData = new FormData();
            formData.append('username', newUsername);
            formData.append('email', newEmail);
            formData.append('description', newDescription);
            if (newPassword) formData.append('password', newPassword);
            if (photoFile) formData.append('photo_profil', photoFile);

            // Afficher un message de chargement
            showFormMessage("Mise à jour en cours...", true);

            // Envoi de la requête
            fetch('profile.php', {
                method: 'POST',
                body: formData
            })
            .then(async response => {
                let data;
                try {
                    const text = await response.text();
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('Réponse brute:', text);
                    throw new Error('Réponse invalide du serveur');
                }
                return data;
            })
            .then(data => {
                if (data.success) {
                    // Mise à jour de l'interface
                    displayUsername.textContent = newUsername;
                    displayDescription.textContent = newDescription || '';
                    
                    if (data.photo_url) {
                        const timestamp = new Date().getTime();
                        const newUrl = data.photo_url + '?t=' + timestamp;
                        
                        // Mettre à jour toutes les images de profil
                        document.querySelectorAll('img.profile-photo').forEach(img => {
                            img.src = newUrl;
                        });
                        
                        // Mettre à jour la prévisualisation
                        const preview = document.getElementById('photo-preview');
                        if (preview) {
                            preview.src = newUrl;
                        }
                    }

                    showFormMessage(data.message, true);
                    setTimeout(() => {
                        toggleEditProfile();
                    }, 1500);
                } else {
                    throw new Error(data.message || "Erreur lors de la mise à jour du profil");
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showFormMessage(error.message || "Erreur lors de la communication avec le serveur", false);
            });
        }

        function showFormMessage(message, isSuccess = true, duration = 3000) {
            if (!formMessage) return;
            formMessage.textContent = message;
            formMessage.className = 'form-message ' + (isSuccess ? 'success' : 'error');
            formMessage.style.display = 'block';
            
            if (duration > 0) {
                setTimeout(() => {
                    if (formMessage.textContent === message) {
                        formMessage.style.display = 'none';
                    }
                }, duration);
            }
        }

        function updateAudioCount() { 
            if (!audioListContainer || !audioCountElement) return; 
            const count = audioListContainer.querySelectorAll('.audio-item').length; 
            audioCountElement.textContent = count; 
        }

        function deleteAudio(button) {
            const audioItem = button.closest('.audio-item');
            if (!audioItem) return;
            
            if (confirm("Êtes-vous sûr de vouloir supprimer cet audio ? Cette action est irréversible.")) {
                const audioId = audioItem.dataset.audioId;
                
                // Désactiver le bouton pendant la suppression
                button.disabled = true;
                button.style.opacity = '0.5';
                
                // Appel AJAX pour supprimer l'audio
                fetch('delete_audio.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        'audio_id': audioId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Animation de suppression
                        audioItem.style.transition = 'all 0.3s ease';
                        audioItem.style.opacity = '0';
                        audioItem.style.transform = 'translateX(-20px)';
                        audioItem.style.height = '0';
                        audioItem.style.padding = '0';
                        audioItem.style.margin = '0';
                        audioItem.style.overflow = 'hidden';
                        
                        // Supprimer l'élément après l'animation
                        setTimeout(() => {
                            audioItem.remove();
                            updateAudioCount();
                            showStatusMessage("L'audio a été supprimé avec succès", true);
                        }, 300);
                    } else {
                        // Réactiver le bouton en cas d'erreur
                        button.disabled = false;
                        button.style.opacity = '1';
                        showStatusMessage(data.message || "Erreur lors de la suppression de l'audio", false);
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    // Réactiver le bouton en cas d'erreur
                    button.disabled = false;
                    button.style.opacity = '1';
                    showStatusMessage("Une erreur est survenue lors de la suppression", false);
                });
            }
        }

        function togglePlay(wrapper, duration) {
            if (!wrapper) {
                console.error('Wrapper non trouvé');
                return;
            }
            
            const audioItem = wrapper.closest('.audio-item');
            const audioId = audioItem.dataset.audioId;
            const audioPath = audioItem.dataset.audioPath;
            let audioElement = audioItem.querySelector('audio');
            
            if (!audioPath) {
                console.error('Chemin audio non trouvé');
                showStatusMessage("Impossible de trouver le fichier audio", false);
                return;
            }
            
            console.log('Tentative de lecture/pause audio:', {
                audioId: audioId,
                audioPath: audioPath,
                existingAudio: !!audioElement,
                isPlaying: audioElement?.paused === false
            });
            
            // Arrêter l'audio en cours s'il y en a un et qu'il est différent
            if (activeAudioWrapper && activeAudioWrapper !== wrapper) {
                console.log('Arrêt de l\'audio précédent');
                stopActiveAudio();
            }
            
            const icon = wrapper.querySelector('.audio-play-icon');
            const timer = wrapper.querySelector('.audio-timer');
            if (!icon || !timer) {
                console.error('Éléments UI manquants:', { icon: !!icon, timer: !!timer });
                return;
            }
            
            // Si c'est le même wrapper actif, on fait pause/play
            if (wrapper === activeAudioWrapper && audioElement) {
                if (audioElement.paused) {
                    console.log('Reprise de la lecture');
                    audioElement.play()
                        .then(() => {
                            icon.classList.remove('fa-play');
                            icon.classList.add('fa-pause');
                            startTimer(timer, Math.floor(audioElement.duration - audioElement.currentTime));
                        })
                        .catch(error => {
                            console.error('Erreur lors de la reprise:', error);
                            showStatusMessage("Impossible de reprendre la lecture", false);
                        });
                } else {
                    console.log('Mise en pause');
                    audioElement.pause();
                    icon.classList.remove('fa-pause');
                    icon.classList.add('fa-play');
                    if (activeAudioTimer) {
                        clearInterval(activeAudioTimer);
                        activeAudioTimer = null;
                    }
                }
                return;
            }
            
            // Création d'un nouvel élément audio si nécessaire
            if (!audioElement) {
                console.log('Création d\'un nouvel élément audio');
                audioElement = document.createElement('audio');
                console.log('Utilisation du chemin audio:', audioPath);
                
                audioElement.style.display = 'none';
                audioElement.preload = 'metadata';
                
                // Configuration des événements audio
                audioElement.addEventListener('loadedmetadata', () => {
                    console.log('Métadonnées audio chargées:', {
                        duration: audioElement.duration,
                        readyState: audioElement.readyState
                    });
                    startTimer(timer, Math.floor(audioElement.duration));
                });
                
                audioElement.addEventListener('play', () => {
                    console.log('Événement play déclenché');
                    icon.classList.remove('fa-play');
                    icon.classList.add('fa-pause');
                    wrapper.classList.add('playing');
                });
                
                audioElement.addEventListener('pause', () => {
                    console.log('Événement pause déclenché');
                    icon.classList.remove('fa-pause');
                    icon.classList.add('fa-play');
                    wrapper.classList.remove('playing');
                });
                
                audioElement.addEventListener('ended', () => {
                    console.log('Audio terminé');
                    stopActiveAudio();
                });
                
                audioElement.addEventListener('error', (e) => {
                    const error = e.target.error;
                    console.error('Erreur audio:', {
                        code: error.code,
                        message: error.message,
                        name: error.name,
                        state: {
                            readyState: audioElement.readyState,
                            networkState: audioElement.networkState,
                            src: audioElement.src
                        }
                    });
                    showStatusMessage("Erreur lors de la lecture de l'audio", false);
                    stopActiveAudio();
                });
                
                audioItem.appendChild(audioElement);
                
                // Vérifier si le fichier existe avant de le charger
                fetch(audioPath, { method: 'HEAD' })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Fichier audio non trouvé');
                        }
                        audioElement.src = audioPath;
                        return audioElement.play();
                    })
                    .then(() => {
                        console.log('Lecture démarrée avec succès');
                        activeAudioWrapper = wrapper;
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        showStatusMessage(error.message || "Impossible de lire l'audio", false);
                        stopActiveAudio();
                    });
            } else {
                // Réutilisation d'un élément audio existant
                console.log('Utilisation d\'un élément audio existant');
                audioElement.currentTime = 0;
                audioElement.play()
                    .then(() => {
                        console.log('Lecture démarrée avec succès');
                        activeAudioWrapper = wrapper;
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        showStatusMessage("Impossible de lire l'audio", false);
                        stopActiveAudio();
                    });
            }
        }

        function stopActiveAudio() {
            if (activeAudioTimer) {
                clearInterval(activeAudioTimer);
                activeAudioTimer = null;
            }
            
            if (activeAudioWrapper) {
                const audioItem = activeAudioWrapper.closest('.audio-item');
                const audioElement = audioItem.querySelector('audio');
                if (audioElement) {
                    audioElement.pause();
                    audioElement.currentTime = 0;
                }
                
                const icon = activeAudioWrapper.querySelector('.audio-play-icon');
                const timer = activeAudioWrapper.querySelector('.audio-timer');
                
                if (icon) {
                    icon.classList.remove('fa-pause');
                    icon.classList.add('fa-play');
                }
                if (timer) {
                    timer.style.display = 'none';
                }
                
                activeAudioWrapper.classList.remove('playing');
                activeAudioWrapper = null;
            }
        }

        function startTimer(timerElement, duration) {
            if (!timerElement || !duration) return;
            
            let remainingTime = duration;
            timerElement.textContent = formatTime(remainingTime);
            timerElement.style.display = 'inline-block';
            
            if (activeAudioTimer) {
                clearInterval(activeAudioTimer);
            }
            
            activeAudioTimer = setInterval(() => {
                if (timerElement.closest('.audio-play-wrapper') !== activeAudioWrapper) {
                    clearInterval(activeAudioTimer);
                    activeAudioTimer = null;
                    timerElement.style.display = 'none';
                    return;
                }
                
                remainingTime--;
                timerElement.textContent = formatTime(remainingTime);
                
                if (remainingTime <= 0) {
                    stopActiveAudio();
                }
            }, 1000);
        }

        function formatTime(seconds) {
            const minutes = Math.floor(seconds / 60);
            const remainingSeconds = seconds % 60;
            return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
        }

        // Ajout de la gestion de la photo de profil
        const photoUpload = document.getElementById('photo-upload');
        const photoPreview = document.getElementById('photo-preview');
        const photoPreviewContainer = document.querySelector('.photo-preview-container');

        if (photoPreviewContainer) {
            photoPreviewContainer.addEventListener('click', () => {
                photoUpload.click();
            });
        }

        if (photoUpload) {
            photoUpload.addEventListener('change', function(e) {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        photoPreview.src = e.target.result;
                    };
                    reader.readAsDataURL(this.files[0]);
                }
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            updateWordCount();
            const currentPage = 'profile';
            document.querySelectorAll('.nav-item').forEach(item => {
                item.classList.toggle('active', item.getAttribute('data-page') === currentPage);
            });
            const initials = (displayUsername.textContent || '').split(/[\s-]+/).map(n => n[0]).slice(0, 2).join('').toUpperCase();
            displayAvatar.textContent = initials || '?';
            updateAudioCount();
        });
    </script>
</body>
</html>