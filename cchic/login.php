<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'database.php';

// Récupérer le message d'erreur de session si présent
if (isset($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']); // Effacer le message pour qu'il ne s'affiche qu'une fois
}

$error = null; // Initialiser $error pour éviter les notices si aucune erreur n'est déclenchée

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Pas strictement nécessaire si on vérifie déjà POST, mais ne fait pas de mal
    if (isset($_POST["submit"])) {
            if (empty($_POST["email"]) || empty($_POST["password"])) {
                $error = "Tous les champs sont obligatoires.";
            } else {
                // htmlspecialchars est utile pour l'affichage, moins pour la recherche DB (prepared statements gèrent ça)
                // Mais on peut le laisser pour la cohérence ou le retirer.
                $email = htmlspecialchars($_POST["email"]);
                $password = $_POST["password"];

                $stmt = $pdo->prepare("SELECT * FROM register WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC); // Utiliser FETCH_ASSOC est souvent plus clair

                // 1. Vérifier si l'utilisateur existe ET si le mot de passe est correct
                if ($user && password_verify($password, $user["password"])) {
                    
                    // Vérifier si l'utilisateur est actif
                    if ($user["is_active"] != 1) {
                        if ($user["is_active"] == 2) {
                            $error = "Votre compte a été banni. Veuillez contacter l'administrateur.";
                        } else {
                            $error = "Votre compte est désactivé. Veuillez contacter l'administrateur.";
                        }
                    } else {
                        // 2. Maintenant que le mdp est vérifié, vérifier si c'est un admin
                        if ($user["is_admin"] == 1) {
                            // C'est un admin
                            $_SESSION['user_id'] = $user["id"];
                            $_SESSION["nom_prenoms"] = $user["nom_prenoms"];
                            $_SESSION["email"] = $user["email"];
                            $_SESSION['is_admin'] = true;
                            header("Location: ../admin/admin_dashboard.php");
                            exit();
                        } else {
                            // C'est un utilisateur normal
                            $_SESSION['user_id'] = $user["id"];
                            $_SESSION["nom_prenoms"] = $user["nom_prenoms"];
                            $_SESSION["email"] = $user["email"];
                            $_SESSION['is_admin'] = false;
                            header("Location: home.php");
                            exit();
                        }
                    }
                } else {
                    // L'email n'existe pas OU le mot de passe est incorrect
                    $error = "Email ou mot de passe incorrect.";
                }
            }
    } else {
         // Si le formulaire est soumis sans le bouton "submit" (peu probable mais possible)
         // $error = "Soumission invalide."; // Optionnel
    }
}

// Affichage de l'erreur quelque part dans votre HTML plus bas
// if ($error) {
//    echo '<p style="color:red;">' . $error . '</p>';
// }
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>C'chic - Connexion</title>
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="formulaire.css">
</head>
<body>
    <!-- Formulaire de connexion -->
    <div class="container mt-5" id="loginFormContainer">
        <h3 class="text-center">Se connecter</h3>
        <form id="loginForm" method="POST" action="">
            <!-- Affichage des erreurs -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger mt-3" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Champ email -->
            <div class="form-group">
                <label for="loginEmail"><i class="fas fa-envelope"></i> E-mail</label>
                <input type="email" name="email" class="form-control" id="loginEmail" 
                       value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" 
                       placeholder="Entrez votre e-mail" required>
            </div>

            <!-- Champ mot de passe avec toggle -->
            <div class="form-group">
                <label for="loginPassword"><i class="fas fa-lock"></i> Mot de passe</label>
                <div class="input-group">
                    <input type="password" name="password" class="form-control" id="loginPassword" 
                           placeholder="Entrez votre mot de passe" required>
                    <div class="input-group-append">
                        <span class="input-group-text toggle-password" data-target="#loginPassword">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                </div>
            </div>

            <button type="submit" name="submit" class="btn btn-warning btn-lg btn-block">Se connecter</button>

            <!-- Liens -->
            <p class="text-center mt-3">
                Mot de passe oublié? <a href="forgot-password.php">Récupérer</a>
            </p>
            <p class="text-center mt-3">
                <a href="index.php">Accueil</a>
            </p>
            <?php if(!empty($error)): ?>
                    <p class= "text-danger text-center"><?= $error ?></p>
                <?php endif; ?>
        </form>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Toggle password visibility
        $(document).ready(function() {
            $('.toggle-password').click(function() {
                const target = $(this).data('target');
                const input = $(target);
                const icon = $(this).find('i');
                
                if (input.attr('type') === 'password') {
                    input.attr('type', 'text');
                    icon.removeClass('fa-eye').addClass('fa-eye-slash');
                } else {
                    input.attr('type', 'password');
                    icon.removeClass('fa-eye-slash').addClass('fa-eye');
                }
            });

            // Show loader on form submission
            $('#loginForm').submit(function() {
                $('#loaderOverlay').fadeIn();
            });
        });
    </script>
</body>
</html>