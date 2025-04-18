<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>À Propos de Moi</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background-color: #212121;
            color: #ffffff;
            padding: 2rem;
        }
        .card {
            background-color: #2d2d2d;
            border-radius: 10px;
            margin-bottom: 2rem;
            transition: transform 0.3s;
        }
        .card:hover {
            transform: scale(1.02);
        }
        .profile-photo {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
        }
        .info-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 1rem;
        }
        .photo-container {
            margin: 1rem 0;
        }
        .chart-container {
            position: relative;
            height: 300px;
            margin: 20px 0;
        }
        .tech-details {
            margin-top: 20px;
        }
        .btn-retour {
            position: fixed;
            top: 15px;
            left: 15px;
            background-color: #2d2d2d;
            color: #ffffff;
            border: 1px solid #ffffff;
            padding: 6px 12px;
            border-radius: 4px;
            transition: all 0.3s ease;
            z-index: 1000;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .btn-retour:hover {
            background-color: #ffffff;
            color: #2d2d2d;
            transform: scale(1.05);
        }

        /* Styles responsifs */
        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }
            .container {
                padding: 0;
            }
            .card {
                margin-bottom: 1rem;
            }
            .info-container {
                text-align: center;
            }
            .photo-container {
                margin: 1rem auto;
            }
            .chart-container {
                height: 250px;
            }
            .btn-retour {
                top: 10px;
                left: 10px;
                padding: 8px;
                font-size: 14px;
            }
            .btn-text {
                display: none;
            }
        }

        @media (max-width: 576px) {
            .info-container {
                flex-direction: column;
            }
            .photo-container {
                margin: 1rem auto;
            }
            .chart-container {
                height: 200px;
            }
            .tech-details {
                margin-top: 1rem;
            }
        }

        @media (min-width: 768px) {
            .info-container {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }
            .photo-container {
                margin-left: 20px;
                margin-top: 0;
            }
        }
    </style>
</head>
<body>
    <a href="index.php" class="btn-retour" title="Retour à l'accueil">
        <i class="fas fa-arrow-left"></i>
        <span class="btn-text">Retour</span>
    </a>

    <div class="container">
        <!-- Cadre 1: Informations personnelles -->
        <div class="card">
            <div class="card-body info-container">
                
                <div class="info-text text-center">
                    
                    <h5>Kouadio Guy-Mosley Kouame</h5>
                    <p>kouadioguymosley@gmail.com</p>
                    <p>+225 01 52 20 79 12</p>
                </div>
                <div>
                    <h3><i class="fas fa-code"></i> Développeur Full-stack</h3>
                </div>
                <div class="photo-container">
                    <div style="width: 150px; height: 150px; overflow: hidden;">
                        <img src="Logo.png" alt="Photo de Profil" class="img-fluid" style="width: 100%; height: 100%; object-fit: cover;">
                    </div>
                </div>
            </div>
        </div>

        <!-- Cadre 2: Parcours scolaire et compétences -->
        <div class="card">
            <div class="card-body">
                <h5 class="text-center"><i class="fas fa-graduation-cap"></i> Parcours Scolaire et Compétences</h5>
                <br>
                
                <div class="row">
                    <!-- Parcours scolaire et diplômes -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="text-center">Parcours Scolaire et Diplômes</h6>
                                <ol>
                                    <li><strong>Administrateur Base de Données:</strong> Biffary Academy, 2024</li>
                                    <li><strong>Data Analyst:</strong> Biffary Academy, 2024</li>
                                    <li><strong>Sécurité & Hacking:</strong> Enov, 2023</li>
                                    <li><strong>Licence en Philosophie:</strong> UAO, 2023</li>
                                    <li><strong>Baccalauréat:</strong> 2019</li>
                                </ol>
                            </div>
                        </div>
                    </div>

                    <!-- Compétences -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="text-center">Compétences</h6>
                                <ol>
                                    <li>Développement Web (HTML, CSS, JavaScript) <span style="color: gold;">★★★☆☆</span></li>
                                    <li>PHP et MySQL <span style="color: gold;">★★★☆☆</span></li>
                                    <li>Gestion de bases de données <span style="color: gold;">★★★☆☆</span></li>
                                    <li>Conception d'interfaces Utilisateur et Administrateur <span style="color: gold;">★★★★☆</span></li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cadre 3: Description du projet -->
        <div class="card">
            <div class="card-body">
                <h5 class="text-center"><i class="fas fa-project-diagram"></i> Description du Projet</h5>
                <p>
                    C'chic est une plateforme dédiée à la création et au partage de contenus audio. 
                    Le projet a été lancé pour permettre aux utilisateurs d'interagir et d'échanger des idées à travers des notes vocales.
                </p>
                <p>
                    L'objectif principal de C'chic est de faciliter la communication et le partage d'expériences 
                    de manière simple et accessible.
                </p>
                <p>
                    Le développement de la plateforme a commencé le 20 Mars 2025 et s'est terminé le 29 avril 2025,
                    avec des tests et des améliorations continues pour garantir une expérience utilisateur optimale.
                </p>
            </div>
        </div>

        <!-- Cadre 4: Technologies utilisées -->
        <div class="card">
            <div class="card-body">
                <h5 class="text-center"><i class="fas fa-cogs"></i> Technologies Utilisées</h5>
                
                <div class="row">
                    <div class="col-md-6 tech-details">
                        <p><strong>Frontend:</strong> HTML, CSS, JavaScript, Bootstrap</p>
                        <ul>
                            <li><strong>HTML:</strong> Utilisé pour structurer le contenu de la page.</li>
                            <li><strong>CSS:</strong> Utilisé pour styliser le contenu et rendre la page visuellement attrayante.</li>
                            <li><strong>JavaScript:</strong> Utilisé pour ajouter des interactions dynamiques, comme le contrôle des formulaires.</li>
                            <li><strong>Bootstrap:</strong> Une bibliothèque CSS qui facilite le design responsive.</li>
                        </ul>
                        <p><strong>Backend:</strong> PHP, PDO</p>
                        <ul>
                            <li><strong>PHP:</strong> Utilisé pour gérer la logique serveur et interagir avec la base de données.</li>
                            <li><strong>PDO:</strong> Une extension PHP pour une connexion sécurisée à la base de données.</li>
                        </ul>
                        <p><strong>Base de données:</strong> MySQL</p>
                        <ul>
                            <li><strong>MySQL:</strong> Utilisé pour stocker les données des utilisateurs et les audios.</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <div class="chart-container">
                            <canvas id="techChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('techChart').getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['HTML', 'CSS', 'JavaScript', 'Bootstrap', 'PHP', 'PDO', 'MySQL'],
                    datasets: [{
                        data: [20, 20, 20, 15, 15, 5, 5],
                        backgroundColor: [
                            '#E44D26', // HTML
                            '#264DE4', // CSS
                            '#F7DF1E', // JavaScript
                            '#563D7C', // Bootstrap
                            '#777BB4', // PHP
                            '#4479A1', // PDO
                            '#00758F'  // MySQL
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    rotation: -90,
                    circumference: 180,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: '#ffffff',
                                font: {
                                    size: 12
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>