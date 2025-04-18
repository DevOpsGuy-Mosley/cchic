<!DOCTYPE html> <!-- Déclaration du type de document HTML5 -->
<html lang="fr"> <!-- Élément racine HTML, langue définie en français -->
<head>
    <!-- Section d'en-tête contenant les métadonnées -->
    <meta charset="UTF-8"> <!-- Définit l'encodage des caractères en UTF-8 -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- Configure la fenêtre d'affichage pour les appareils mobiles (responsive design) -->
    <title>C'chic - Mot de Passe Oublié</title> <!-- Définit le titre de la page affiché dans l'onglet du navigateur -->
    <link rel="icon" href="favicon.ico" type="image/x-icon"> <!-- Lie l'icône de favori (favicon) à la page -->

    <!-- Liens vers les feuilles de style externes -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet"> <!-- Lie la feuille de style CSS de Bootstrap -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css" rel="stylesheet"> <!-- Lie la feuille de style CSS de Font Awesome (pour les icônes) -->
    <link rel="stylesheet" href="formulaire.css"> <!-- Lie la feuille de style CSS personnalisée -->
</head>
<body>
    <!-- Corps principal du document HTML -->

    <!-- Formulaire de mot de passe oublié -->
    <div class="container mt-5" id="forgotPasswordFormContainer"> <!-- Conteneur principal pour le formulaire, utilisant les classes Bootstrap pour le style et la marge -->
        <h3 class="text-center">Mot de passe oublié?</h3> <!-- Titre du formulaire, centré -->
        <form id="forgotPasswordForm"> <!-- Début du formulaire de récupération de mot de passe. L'ID est utilisé par JavaScript -->
            <!-- Champ email -->
            <div class="form-group"> <!-- Groupe de formulaire pour l'étiquette et le champ email -->
                <label for="forgotEmail"><i class="fas fa-envelope"></i> E-mail</label> <!-- Étiquette pour le champ email, avec une icône -->
                <input type="email" class="form-control" id="forgotEmail" placeholder="Entrez votre e-mail" required> <!-- Champ de saisie pour l'adresse e-mail. `required` le rend obligatoire -->
            </div>
            <button type="submit" class="btn btn-warning btn-lg btn-block">Réinitialiser le mot de passe</button> <!-- Bouton pour soumettre le formulaire. `btn-block` le fait s'étendre sur toute la largeur -->

            <!-- Lien pour retourner à l'accueil -->
            <p class="text-center mt-3"> <!-- Paragraphe pour le lien de retour, centré et avec une marge supérieure -->
                 <a href="index.php">Accueil</a> <!-- Lien hypertexte pour retourner à la page d'accueil -->
            </p>
        </form>
    </div>

    <!-- Scripts JavaScript (identiques pour toutes les pages) -->
    <!-- Inclusion des scripts JavaScript nécessaires (jQuery, Popper.js pour Bootstrap, Bootstrap JS, et le script personnalisé) -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script> <!-- Inclusion de la bibliothèque jQuery -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script> <!-- Inclusion de Popper.js (requis par certains composants Bootstrap) -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script> <!-- Inclusion du JavaScript de Bootstrap -->
    <script src="formulaire.js"></script> <!-- Inclusion du script JavaScript personnalisé -->
</body>
</html>