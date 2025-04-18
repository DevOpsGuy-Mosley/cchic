<!DOCTYPE html>
<html lang="fr">
<head>
    <!-- Métadonnées de base -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Page de déconnexion de C'chic">
    <title>Déconnexion - C'chic</title>
    
    <!-- Liens vers les polices Google et Font Awesome -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* Définition des variables CSS pour les couleurs */
        :root {
            --primary-bg: #1e1e1e;       /* Fond principal */
            --secondary-bg: #2c2c2c;     /* Fond secondaire */
            --text-primary: #f0f0f0;     /* Texte principal */
            --text-secondary: #b0b0b0;   /* Texte secondaire */
            --accent-color: #ff7f00;     /* Couleur d'accentuation */
            --separator-color: #4a4a4a;  /* Couleur des séparateurs */
        }

        /* Réinitialisation des styles par défaut */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        /* Styles de base pour le corps de la page */
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: var(--primary-bg);
            color: var(--text-primary);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px 10px 60px; /* Ajout de padding horizontal */
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        /* Conteneur principal de la page de déconnexion */
        .app-logout-wrapper {
            width: 95%;
            max-width: 850px;
            min-height: 60vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background-color: var(--primary-bg);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(187, 184, 184, 0.3);
            animation: fadeIn 0.5s ease; /* Animation d'apparition */
            margin: 20px 0;
        }

        /* Contenu de la boîte de déconnexion */
        .logout-container {
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
        }

        /* Icône de déconnexion avec animation de pulsation */
        .logout-icon {
            font-size: 3em;
            color: var(--accent-color);
            margin: 0 auto 20px;
            animation: pulse 2s infinite;
            display: flex;
            justify-content: center;
        }

        /* Titre de la page */
        .logout-container h1 {
            font-family: 'Montserrat', sans-serif;
            margin-bottom: 15px;
            font-size: 1.8em;
            color: var(--text-primary);
        }

        /* Message de confirmation */
        .logout-message {
            margin-bottom: 25px;
            line-height: 1.5;
            color: var(--text-secondary);
            font-size: 1.1em;
        }

        /* Conteneur des boutons */
        .logout-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
        }

        /* Styles de base des boutons */
        .logout-btn {
            padding: 12px 25px;
            border-radius: 25px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            font-size: 1em;
            min-width: 150px;
        }

        /* Bouton de confirmation (orange) */
        .logout-confirm {
            background-color: var(--accent-color);
            color: white;
        }

        /* Effets au survol du bouton de confirmation */
        .logout-confirm:hover, 
        .logout-confirm:focus {
            background-color: #e65c00;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(255, 111, 0, 0.3);
            outline: none;
        }

        /* Bouton d'annulation (transparent) */
        .logout-cancel {
            background-color: transparent;
            color: var(--text-secondary);
            border: 1px solid var(--text-secondary);
        }

        /* Effets au survol du bouton d'annulation */
        .logout-cancel:hover,
        .logout-cancel:focus {
            color: var(--text-primary);
            border-color: var(--text-primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(255, 255, 255, 0.1);
            outline: none;
        }

        /* Barre de navigation (identique à profile.html) */
        .navbar { 
            position: fixed; 
            bottom: 0; 
            left: 0; 
            width: 100%; 
            background-color: #2c2c2c; 
            border-top: 1px solid #4a4a4a; 
            display: flex; 
            justify-content: space-around; 
            align-items: center; 
            padding: 8px 0; 
            z-index: 1000; 
            box-shadow: 0 -4px 10px rgba(0, 0, 0, 0.2); 
        }

        /* Éléments de la barre de navigation */
        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: var(--text-secondary);
            text-decoration: none;
            cursor: pointer;
            padding: 5px 10px;
            transition: color 0.3s ease, transform 0.2s ease;
            flex: 1;
            text-align: center;
        }

        /* Élément actif dans la barre de navigation */
        .nav-item.active {
            color: var(--accent-color);
            font-weight: 600;
        }

        /* Effet au survol des éléments de navigation */
        .nav-item:hover {
            color: var(--text-primary);
        }

        /* Effet lors du clic sur un élément de navigation */
        .nav-item:active {
            transform: scale(0.95);
        }

        /* Icônes de la barre de navigation */
        .nav-icon {
            font-size: 1.5em;
            margin-bottom: 4px;
        }

        /* Texte sous les icônes de navigation */
        .nav-text {
            font-size: 0.8em;
            font-weight: 500;
        }

        /* Styles responsives pour grands écrans */
        @media (min-width: 1200px) {
            .app-logout-wrapper {
                max-width: 1000px;
            }

            .logout-container {
                padding: 40px;
            }

            .logout-icon {
                font-size: 4em;
            }

            .logout-container h1 {
                font-size: 2.2em;
            }

            .logout-message {
                font-size: 1.3em;
            }
        }

        /* Styles responsives pour tablettes et écrans moyens */
        @media (max-width: 768px) {
            .app-logout-wrapper {
                width: 95%;
                min-height: 60vh;
                border-radius: 10px;
                margin: 10px;
            }

            .logout-container {
                padding: 25px;
                max-width: 95%;
            }

            .logout-buttons {
                flex-direction: column;
                gap: 15px;
                width: 80%;
                margin: 20px auto;
            }

            .logout-btn {
                width: 100%;
                padding: 15px;
            }
        }

        /* Styles responsives pour petites tablettes */
        @media (max-width: 600px) {
            .app-logout-wrapper {
                width: 100%;
                margin: 0;
            }

            .logout-container {
                padding: 20px 15px;
            }

            .logout-icon {
                font-size: 2.8em;
            }

            .logout-container h1 {
                font-size: 1.6em;
            }

            .logout-message {
                font-size: 1.1em;
                padding: 0 10px;
            }
        }

        /* Styles responsives pour mobiles */
        @media (max-width: 480px) {
            body {
                padding: 10px 5px 60px;
            }

            .app-logout-wrapper {
                min-height: 50vh;
            }

            .logout-icon {
                font-size: 2.2em;
                margin-bottom: 15px;
            }

            .logout-container h1 {
                font-size: 1.4em;
                margin-bottom: 10px;
            }

            .logout-message {
                font-size: 0.95em;
                line-height: 1.4;
            }

            .logout-buttons {
                width: 90%;
            }

            .logout-btn {
                padding: 12px;
                font-size: 0.9em;
            }

            .nav-icon {
                font-size: 1.2em;
            }

            .nav-text {
                font-size: 0.65em;
            }
        }

        /* Styles responsives pour très petits écrans */
        @media (max-width: 320px) {
            .logout-container {
                padding: 15px 10px;
            }

            .logout-icon {
                font-size: 2em;
            }

            .logout-container h1 {
                font-size: 1.2em;
            }

            .logout-message {
                font-size: 0.9em;
            }

            .logout-btn {
                padding: 10px;
                font-size: 0.85em;
            }
        }

        /* Support pour les écrans à orientation paysage sur mobile */
        @media (max-height: 480px) and (orientation: landscape) {
            .app-logout-wrapper {
                min-height: 80vh;
            }

            .logout-container {
                padding: 15px;
            }

            .logout-buttons {
                flex-direction: row;
                gap: 10px;
            }

            .logout-btn {
                width: auto;
                min-width: 120px;
            }
        }
    </style>
</head>
<body>
    <!-- Contenu principal de la page -->
    <main class="app-logout-wrapper">
        <!-- Section de déconnexion avec attributs ARIA pour l'accessibilité -->
        <section class="logout-container" role="dialog" aria-modal="true" aria-labelledby="logoutTitle" aria-describedby="logoutMessage">
            <!-- Icône de déconnexion -->
            <div class="logout-icon" aria-hidden="true">
                <i class="fas fa-sign-out-alt"></i>
            </div>
            <!-- Titre de la page -->
            <h1 id="logoutTitle">Déconnexion</h1>
            <!-- Message de confirmation -->
            <p class="logout-message" id="logoutMessage">
                Êtes-vous sûr de vouloir vous déconnecter de votre compte C'chic ?
            </p>
            <!-- Boutons d'action -->
            <div class="logout-buttons">
                <button type="button" class="logout-btn logout-confirm" id="confirmLogout" aria-label="Confirmer la déconnexion">
                    Déconnexion
                </button>
                <button type="button" class="logout-btn logout-cancel" id="cancelLogout" aria-label="Annuler la déconnexion">
                    Annuler
                </button>
            </div>
        </section>
    </main>

    <!-- Barre de navigation fixée en bas -->
    <nav class="navbar" aria-label="Navigation principale">
        <!-- Lien vers l'accueil -->
        <a href="home.php" class="nav-item" data-page="home">
            <i class="fas fa-home nav-icon"></i>
            <span class="nav-text">Accueil</span>
        </a>
        <!-- Lien vers le profil -->
        <a href="profile.php" class="nav-item" data-page="profile">
            <i class="fas fa-user nav-icon"></i>
            <span class="nav-text">Profil</span>
        </a>
        <!-- Lien vers les notifications -->
        <a href="notifications.php" class="nav-item" data-page="notifications">
            <i class="fas fa-bell nav-icon"></i>
            <span class="nav-text">Notifications</span>
        </a>
        <!-- Lien actif vers la déconnexion -->
        <a href="logout.php" class="nav-item active" data-page="logout" aria-current="page">
            <i class="fas fa-sign-out-alt nav-icon"></i>
            <span class="nav-text">Déconnexion</span>
        </a>
    </nav>

    <script>
        // Code exécuté une fois le DOM chargé
        document.addEventListener('DOMContentLoaded', () => {
            // Récupération des boutons
            const confirmLogout = document.getElementById('confirmLogout');
            const cancelLogout = document.getElementById('cancelLogout');

            // Gestion du clic sur le bouton de confirmation
            confirmLogout.addEventListener('click', () => {
                // Normalement, ici on ferait une requête au serveur pour déconnecter l'utilisateur
                alert('Vous avez été déconnecté avec succès.');
                window.location.href = 'index.php'; // Redirection après déconnexion
            });

            // Gestion du clic sur le bouton d'annulation
            cancelLogout.addEventListener('click', () => {
                window.location.href = 'home.php'; // Retour à l'accueil
            });

            // Gestion des touches clavier pour l'accessibilité (confirmation)
            confirmLogout.addEventListener('keyup', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    confirmLogout.click();
                }
            });

            // Gestion des touches clavier pour l'accessibilité (annulation)
            cancelLogout.addEventListener('keyup', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    cancelLogout.click();
                }
            });

            // Mise en surbrillance de l'élément de navigation actif
            const currentPage = 'logout';
            document.querySelectorAll('.nav-item').forEach(item => {
                item.classList.toggle('active', item.getAttribute('data-page') === currentPage);
            });
        });
    </script>
</body>
</html>