<!DOCTYPE html> <!-- Déclaration du type de document HTML5 -->
<html lang="fr"> <!-- Élément racine HTML, langue définie en français -->
<head>
    <!-- Section d'en-tête contenant les métadonnées -->
    <meta charset="UTF-8"> <!-- Encodage des caractères -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- Configuration du viewport pour le responsive -->
    <title>C'chic - Accueil</title> <!-- Titre de la page d'accueil -->
    <!-- Favicon -->
    <link rel="icon" href="favicon.ico" type="image/x-icon"> <!-- Icône de favori -->
    <!-- Liens vers les feuilles de style externes -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet"> <!-- CSS Bootstrap -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css" rel="stylesheet"> <!-- CSS Font Awesome (icônes) -->
    <!-- Lien vers la feuille de style personnalisée -->
    <link rel="stylesheet" href="formulaire.css"> <!-- CSS personnalisé -->
</head>
<body>
    <!-- Corps principal du document HTML -->

    <!-- Conteneur principal de la page d'accueil -->
    <div class="container text-center mt-5" id="welcomeContainer"> <!-- Conteneur centré avec marge supérieure -->
        <img src="Logo.png" alt="C'chic Logo" class="img-fluid logo">
        <h2 class="mb-4">Bienvenue sur C'chic</h2> <!-- Titre de bienvenue avec marge inférieure -->

        <div class="mb-4"> 
            <a href="login.php" class="btn btn-warning btn-lg mx-4">Se Connecter</a>
            <a href="register.php" class="btn btn-primary btn-lg mx-4">S'inscrire</a>
        </div>

        <p class="lead">Venez rigoler 😂</p>
    </div>

    <!-- Scripts JavaScript (communs à toutes les pages) -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script> <!-- jQuery -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script> <!-- Popper.js -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script> <!-- Bootstrap JS -->
    <script src="formulaire.js"></script> <!-- Script personnalisé (contient la logique JS partagée comme le toggle password) -->
</body>
</html>