<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>À Propos de Moi</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --text-color: #ffffff;
        }

        body {
            background: #212121;
            color: var(--text-color);
            font-family: 'Roboto', sans-serif; /* Police de caractères */
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .main-container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .circle-container {
            position: relative;
            width: 700px;
            height: 700px;
            margin: 0 auto;
        }

        .center-image {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 200px;
            height: 200px;
            border-radius: 50%;
            border: 5px solid white;
            box-shadow: 0 0 20px rgba(0,0,0,0.2);
            z-index: 2;
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .center-image.rotate {
            animation: rotate 1s ease-in-out;
        }

        @keyframes rotate {
            0% {
                transform: translate(-50%, -50%) rotate(0deg);
            }
            100% {
                transform: translate(-50%, -50%) rotate(360deg);
            }
        }

        .center-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .section {
            position: absolute;
            width: 280px;
            padding: 20px;
            border-radius: 15px;
            background: rgba(0, 0, 0, 0.8);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            cursor: pointer;
            overflow: hidden;
        }

        .section-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.5s ease;
            opacity: 0;
        }

        .section.active .section-content {
            max-height: 300px;
            opacity: 1;
            margin-top: 1rem;
            overflow-y: auto;
            padding-right: 10px;
        }

        .section-title {
            font-size: 1.5rem;
            margin: 0;
            text-align: center;
            position: relative;
            padding: 10px 0;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .section-title::after {
            content: '\f107';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            transition: transform 0.3s ease;
        }

        .section.active .section-title::after {
            transform: rotate(180deg);
        }

        .profile {
            top: 25%;
            left: 0;
            transform: translateY(-50%) translateX(-35%);
            border: 2px solid #0044ff;
        }

        .level {
            bottom: 25%;
            left: 0;
            transform: translateY(50%) translateX(-35%);
            border: 2px solid #FF6F00;
        }

        .project {
            top: 25%;
            right: 0;
            transform: translateY(-50%) translateX(35%);
            border: 2px solid #FF6F00;
        }

        .technologies {
            bottom: 25%;
            right: 0;
            transform: translateY(50%) translateX(35%);
            border: 2px solid #0044ff;
        }

        .tech-category {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .tech-category:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .tech-icon {
            width: 24px;
            text-align: center;
            margin-right: 10px;
        }

        .info-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .info-list li {
            margin-bottom: 0.8rem;
            display: flex;
            align-items: center;
            color: #fff;
            opacity: 0.9;
            transition: opacity 0.3s ease;
        }

        .info-list li:hover {
            opacity: 1;
        }

        .info-list li i {
            margin-right: 10px;
            width: 20px;
            color: inherit;
        }

        .btn-retour {
            position: fixed;
            top: 20px;
            left: 20px;
            background: transparent;
            color: white;
            border: 2px solid #FF6F00;
            padding: 10px;
            border-radius: 50%;
            text-decoration: none;
            transition: all 0.3s ease;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-retour:hover {
            transform: scale(1.05);
            background: #FF6F00;
            color: white;
        }

        .btn-retour span {
            display: none;
        }

        @media (max-width: 1200px) {
            .circle-container {
                width: 600px;
                height: 600px;
            }
            .section {
                width: 250px;
            }
        }

        @media (max-width: 992px) {
            .circle-container {
                width: 100%;
                height: auto;
                display: flex;
                flex-direction: column;
                gap: 1rem;
                padding: 1rem;
            }

            .section {
                position: relative;
                width: 100%;
                max-width: 500px;
                margin: 0 auto;
                transform: none !important;
                top: auto;
                left: auto;
                right: auto;
                bottom: auto;
            }

            .center-image {
                position: relative;
                margin: 2rem auto;
                transform: none;
                width: 150px;
                height: 150px;
                top: auto;
                left: auto;
            }

            .center-image.rotate {
                animation: rotate-mobile 1s ease-in-out;
            }

            @keyframes rotate-mobile {
                0% {
                    transform: rotate(0deg);
                }
                100% {
                    transform: rotate(360deg);
                }
            }
        }

        @media (max-width: 576px) {
            body {
                padding: 1rem;
            }

            .section {
                padding: 15px;
            }

            .center-image {
                width: 120px;
                height: 120px;
                margin: 1rem auto;
            }

            .section-title {
                font-size: 1.2rem;
            }

            .info-list li {
                margin-bottom: 1rem;
            }

            .info-list li p {
                font-size: 0.85rem;
            }
        }

        @media (max-width: 360px) {
            body {
                padding: 0.5rem;
            }

            .center-image {
                width: 100px;
                height: 100px;
            }

            .section {
                padding: 10px;
            }
        }

        @media (min-width: 1400px) {
            .circle-container {
                width: 800px;
                height: 800px;
            }

            .section {
                width: 300px;
            }
        }

        .info-list li {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1.5rem;
            padding-right: 5px;
        }
        .info-list li:last-child {
            margin-bottom: 0;
        }
        .tech-icon {
            flex-shrink: 0;
            margin-top: 3px;
        }
        .info-list li div {
            margin-left: 10px;
        }
        .info-list li strong {
            display: block;
            margin-bottom: 0.2rem;
            color: white;
        }
        .info-list li p {
            font-size: 0.9rem;
            line-height: 1.3;
        }

        .section-content::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }

        .section-content::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
        }

        .section-content::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.5);
            border-radius: 10px;
            border: none;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.2);
        }

        .section-content::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.8);
        }

        .section-content {
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.5) rgba(255, 255, 255, 0.05);
        }

        .section-content {
            scroll-behavior: smooth;
        }

        .project .section-content::-webkit-scrollbar-thumb {
            background: linear-gradient(45deg, var(--primary-color), var(--third-color));
        }

        .profile .section-content::-webkit-scrollbar-thumb {
            background: linear-gradient(45deg, var(--secondary-color), var(--fourth-color));
        }

        .level .section-content::-webkit-scrollbar-thumb {
            background: linear-gradient(45deg, var(--fourth-color), var(--primary-color));
        }

        .technologies .section-content::-webkit-scrollbar-thumb {
            background: linear-gradient(45deg, var(--third-color), var(--secondary-color));
        }
    </style>
</head>
<body>
    <a href="index.php" class="btn-retour">
        <i class="fas fa-arrow-left"></i>
        <span>Retour</span>
    </a>

    <div class="main-container">
        <div class="circle-container">
            <div class="center-image">
                <img src="/" alt="Photo de Profil">
            </div>

            <div class="section project">
                <h2 class="section-title"><i class="fas fa-project-diagram"></i>Projet C'chic</h2>
                <div class="section-content">
                    <div class="project-section mb-4">
                        <h5 class="text-white-50 mb-3">Qu'est-ce que C'chic ?</h5>
                        <p>C'chic est une plateforme sociale innovante dédiée au partage de contenus audio sur differents sujets. Elle permet aux utilisateurs de s'exprimer et d'interagir à travers des notes vocales d'une durée de 60 secondes maximum, créant ainsi une expérience plus personnelle et authentique que le texte.</p>
                    </div>

                    <div class="project-section mb-4">
                        <h5 class="text-white-50 mb-3">Fonctionnalités Principales</h5>
                        <ul class="info-list">
                            <li>
                                <i class="fas fa-microphone"></i>
                                <div>
                                    <strong>Enregistrement Vocal</strong>
                                    <p class="mb-0 text-white-50">Créez et partagez facilement des messages vocaux avec possibilité d'ecouter avant de publier.</p>
                                </div>
                            </li>
                            <li>
                                <i class="fas fa-comments"></i>
                                <div>
                                    <strong>Interactions</strong>
                                    <p class="mb-0 text-white-50">Commentez en texte, partagez et reagissez (like, dislike, rire) aux publications.</p>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <div class="project-section mb-4">
                        <h5 class="text-white-50 mb-3">Guide d'Utilisation</h5>
                        <ul class="info-list">
                            <li>
                                <i class="fas fa-user-plus"></i>
                                <div>
                                    <strong>Création de Compte</strong>
                                    <p class="mb-0 text-white-50">Inscrivez-vous avec email, personnalisez votre profil avec photo et description.</p>
                                </div>
                            </li>
                            <li>
                                <i class="fas fa-record-vinyl"></i>
                                <div>
                                    <strong>Publication</strong>
                                    <p class="mb-0 text-white-50">Cliquez sur le bouton micro, enregistrez votre message, ajoutez un titre et automatiquement votre message sera publié sur le fil d'actualité.</p>
                                </div>
                            </li>
                            <li>
                                <i class="fas fa-search"></i>
                                <div>
                                    <strong>Découverte</strong>
                                    <p class="mb-0 text-white-50">Explorez le fil d'actualité, utilisez la recherche pour trouver du contenu a partir d'un mot clé ou d'un utilisateur.</p>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <div class="project-section">
                        <h5 class="text-white-50 mb-3">Sécurité et Confidentialité</h5>
                        <ul class="info-list">
                            <li>
                                <i class="fas fa-shield-alt"></i>
                                <div>
                                    <strong>Protection des Données</strong>
                                    <p class="mb-0 text-white-50">Vos données sont hashées et sécurisées.</p>
                                </div>
                            </li>
                            <li>
                                <i class="fas fa-user-shield"></i>
                                <div>
                                    <strong>Contrôle du Contenu</strong>
                                    <p class="mb-0 text-white-50">Modération active, possibilité de signaler du contenu inapproprié et l'administrateur le supprimera.</p>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <div class="project-info mt-4 pt-3 border-top border-white-50">
                        <p class="mb-2"><i class="fas fa-calendar-alt me-2"></i>Développé du 20 Mars au 29 avril 2025</p>
                        <p class="mb-0"><i class="fas fa-code-branch me-2"></i>Mises à jour régulières et nouvelles fonctionnalités</p>
                    </div>
                </div>
            </div>

            <div class="section profile">
                <h2 class="section-title"><i class="fas fa-id-card"></i>Profil</h2>
                <div class="section-content">
                    <div class="profile-section mb-4">
                        <h5 class="text-white-50 mb-3">Qui suis-je ?</h5>
                        <div class="info-card p-3 mb-3" style="background: rgba(255,255,255,0.05); border-radius: 10px;">
                            <p class="mb-2">Développeur Full-stack passionné par la création d'applications web mobile innovantes. Mon parcours unique, alliant philosophie et technologie, m'apporte une approche différente et créative dans le développement de solutions numériques.</p>
                        </div>
                        <ul class="info-list">
                            <li>
                                <i class="fas fa-user"></i>
                                <div>
                                    <strong>Kouadio Guy-Mosley Kouame</strong>
                                    <p class="mb-0 text-white-50">Développeur Full-stack</p>
                                </div>
                            </li>
                            <li>
                                <i class="fas fa-envelope"></i>
                                <div>
                                    <strong>Email</strong>
                                    <p class="mb-0 text-white-50">kouadioguymosley@gmail.com</p>
                                </div>
                            </li>
                            <li>
                                <i class="fas fa-phone"></i>
                                <div>
                                    <strong>Contact</strong>
                                    <p class="mb-0 text-white-50">+225 01 52 20 79 12</p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="section level">
                <h2 class="section-title"><i class="fas fa-chart-line"></i>Niveau</h2>
                <div class="section-content">
                    <div class="mb-4">
                        <h5 class="text-white-50 mb-3">Formation & Diplômes</h5>
                        <ul class="info-list">
                            <li>
                                <i class="fas fa-database"></i>
                                <div>
                                    <strong>Administrateur Base de Données (2024)</strong>
                                    <p class="mb-0 text-white-50">Gestion et optimisation des bases de données, sécurité des données</p>
                                </div>
                            </li>
                            <li>
                                <i class="fas fa-chart-line"></i>
                                <div>
                                    <strong>Data Analyst (2024)</strong>
                                    <p class="mb-0 text-white-50">Analyse de données, visualisation et reporting</p>
                                </div>
                            </li>
                            <li>
                                <i class="fas fa-shield-alt"></i>
                                <div>
                                    <strong>Sécurité & Hacking (2023)</strong>
                                    <p class="mb-0 text-white-50">Cybersécurité, tests d'intrusion, protection des applications</p>
                                </div>
                            </li>
                            <li>
                                <i class="fas fa-book"></i>
                                <div>
                                    <strong>Licence en Philosophie (2023)</strong>
                                    <p class="mb-0 text-white-50">Pensée analytique, résolution de problèmes complexes</p>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <div>
                        <h5 class="text-white-50 mb-3">Compétences Techniques</h5>
                        <ul class="info-list">
                            <li>
                                <i class="fas fa-laptop-code"></i>
                                <div>
                                    <strong>Développement Web</strong>
                                    <p class="mb-0 text-white-50">HTML, CSS, JavaScript - Création d'interfaces interactives et responsives</p>
                                </div>
                            </li>
                            <li>
                                <i class="fas fa-database"></i>
                                <div>
                                    <strong>PHP et MySQL</strong>
                                    <p class="mb-0 text-white-50">Développement backend, API REST, gestion de bases de données</p>
                                </div>
                            </li>
                            <li>
                                <i class="fas fa-server"></i>
                                <div>
                                    <strong>Gestion BDD</strong>
                                    <p class="mb-0 text-white-50">Optimisation des requêtes, modélisation de données, maintenance</p>
                                </div>
                            </li>
                            <li>
                                <i class="fas fa-paint-brush"></i>
                                <div>
                                    <strong>UI/UX Design</strong>
                                    <p class="mb-0 text-white-50">Création d'interfaces utilisateur et administrateur intuitives et esthétiques</p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="section technologies">
                <h2 class="section-title"><i class="fas fa-cogs"></i>Technologies</h2>
                <div class="section-content">
                    <div class="tech-category">
                        <h5 class="text-white-50 mb-3">Frontend (Ce que l'utilisateur voit)</h5>
                        <ul class="info-list">
                            <li>
                                <span class="tech-icon"><i class="fab fa-html5" style="color: #E44D26;"></i></span>
                                <div>
                                    <strong>HTML</strong>
                                    <p class="mb-0 text-white-50">Le squelette du site - Organise le contenu comme les paragraphes, images et boutons</p>
                                </div>
                            </li>
                            <li>
                                <span class="tech-icon"><i class="fab fa-css3-alt" style="color: #264DE4;"></i></span>
                                <div>
                                    <strong>CSS</strong>
                                    <p class="mb-0 text-white-50">Le style du site - Gère les couleurs, la mise en page et les animations</p>
                                </div>
                            </li>
                            <li>
                                <span class="tech-icon"><i class="fab fa-js" style="color: #F7DF1E;"></i></span>
                                <div>
                                    <strong>JavaScript</strong>
                                    <p class="mb-0 text-white-50">Le cerveau du site - Rend la page interactive et dynamique</p>
                                </div>
                            </li>
                            <li>
                                <span class="tech-icon"><i class="fab fa-bootstrap" style="color: #563D7C;"></i></span>
                                <div>
                                    <strong>Bootstrap</strong>
                                    <p class="mb-0 text-white-50">Une boîte à outils de design - Facilite la création d'un site adaptatif et moderne</p>
                                </div>
                            </li>
                        </ul>
                    </div>
                    <div class="tech-category">
                        <h5 class="text-white-50 mb-3">Backend (Ce qui se passe en coulisses)</h5>
                        <ul class="info-list">
                            <li>
                                <span class="tech-icon"><i class="fab fa-php" style="color: #777BB4;"></i></span>
                                <div>
                                    <strong>PHP</strong>
                                    <p class="mb-0 text-white-50">Le moteur du site - Traite les données et gère les fonctionnalités côté serveur</p>
                                </div>
                            </li>
                            <li>
                                <span class="tech-icon"><i class="fas fa-shield-alt" style="color: #4479A1;"></i></span>
                                <div>
                                    <strong>PDO</strong>
                                    <p class="mb-0 text-white-50">Le gardien des données - Assure une connexion sécurisée avec la base de données</p>
                                </div>
                            </li>
                        </ul>
                    </div>
                    <div class="tech-category">
                        <h5 class="text-white-50 mb-3">Base de données (Où sont stockées les informations)</h5>
                        <ul class="info-list">
                            <li>
                                <span class="tech-icon"><i class="fas fa-database" style="color: #00758F;"></i></span>
                                <div>
                                    <strong>MySQL</strong>
                                    <p class="mb-0 text-white-50">La bibliothèque du site - Stocke et organise toutes les données de manière structurée</p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sections = document.querySelectorAll('.section');
            const centerImage = document.querySelector('.center-image');
            
            sections.forEach(section => {
                section.addEventListener('click', function() {
                    if (this.classList.contains('active')) {
                        this.classList.remove('active');
                    } else {
                        sections.forEach(s => s.classList.remove('active'));
                        this.classList.add('active');
                    }
                });
            });

            centerImage.addEventListener('click', function() {
                this.classList.add('rotate');
                setTimeout(() => {
                    this.classList.remove('rotate');
                }, 1000);
            });
        });
    </script>
</body>
</html>