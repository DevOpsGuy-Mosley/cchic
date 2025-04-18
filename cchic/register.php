<?php
require 'database.php';

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Récupération et nettoyage des données
    $nom_prenoms = filter_input(INPUT_POST, 'nom_prenoms', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $genre = filter_input(INPUT_POST, 'genre', FILTER_SANITIZE_STRING);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation des champs
    if (empty($nom_prenoms) || empty($email) || empty($genre) || empty($password) || empty($confirm_password)) {
        $error = "Tous les champs sont obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "L'adresse email n'est pas valide.";
    } elseif (strlen($password) < 8) {
        $error = "Le mot de passe doit contenir au moins 8 caractères.";
    } elseif ($password !== $confirm_password) {
        $error = "Les mots de passe ne correspondent pas.";
    } else {
        try {
            // Vérifier si l'email existe déjà
            $stmt = $pdo->prepare("SELECT id FROM register WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() > 0) {
                $error = "Cet email est déjà utilisé.";
            } else {
                // Hachage du mot de passe
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                
                // Insertion dans la base de données
                $stmt = $pdo->prepare("INSERT INTO register (nom_prenoms, email, genre, password) VALUES (?, ?, ?, ?)");
                if ($stmt->execute([$nom_prenoms, $email, $genre, $hashed_password])) {
                    $success = "Inscription réussie! Vous pouvez maintenant vous connecter.";
                    // Réinitialisation des champs après succès
                    $nom_prenoms = $email = $genre = '';
                    header("Location: login.php?success=registered");
                    exit();
                }
            }
        } catch (PDOException $e) {
            error_log('Registration error: ' . $e->getMessage());
            $error = "Une erreur est survenue lors de l'inscription. Veuillez réessayer.";
        }
        
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>C'chic - Inscription</title>
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="formulaire.css">
</head>
<body>
    <div class="container mt-5" id="registrationFormContainer">
        <h3 class="text-center">Créer un compte</h3>
        
        <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <form method="post" action="">
            <!-- Champ nom complet -->
            <div class="form-group">
                <label for="fullName"><i class="fas fa-user"></i> Nom & Prénoms</label>
                <input type="text" name="nom_prenoms" class="form-control" id="fullName" 
                       value="<?= isset($nom_prenoms) ? htmlspecialchars($nom_prenoms) : '' ?>" 
                       placeholder="Entrez votre nom et prénoms" required>
            </div>
            
            <!-- Champ email -->
            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> E-mail</label>
                <input type="email" name="email" class="form-control" id="email" 
                       value="<?= isset($email) ? htmlspecialchars($email) : '' ?>" 
                       placeholder="Entrez votre e-mail" required>
            </div>
            
            <!-- Champ genre -->
            <div class="form-group">
                <label for="gender"><i class="fas fa-venus-mars"></i> Genre</label>
                <select name="genre" class="form-control" id="gender" required>
                    <option value="">Choisissez un genre</option>
                    <option value="masculin" >Masculin</option>
                    <option value="feminin" >Féminin</option>
                    <option value="autre" >Autre</option>
                </select>
            </div>
            
            <!-- Champ mot de passe -->
            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> Mot de passe</label>
                <div class="input-group">
                    <input type="password" name="password" class="form-control" id="password" 
                           placeholder="Entrez votre mot de passe" required>
                    <div class="input-group-append">
                        <span class="input-group-text toggle-password" data-target="#password">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                </div>
                <small class="form-text text-muted">Minimum 8 caractères</small>
            </div>
            
            <!-- Champ confirmation mot de passe -->
            <div class="form-group">
                <label for="confirmPassword"><i class="fas fa-lock"></i> Confirmer mot de passe</label>
                <div class="input-group">
                    <input type="password" name="confirm_password" class="form-control" id="confirmPassword" 
                           placeholder="Confirmez votre mot de passe" required>
                    <div class="input-group-append">
                        <span class="input-group-text toggle-password" data-target="#confirmPassword">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="btn btn-warning btn-lg btn-block">S'inscrire</button>
        </form>
        
        <p class="text-center mt-3">
            Vous avez déjà un compte? <a href="login.php">Se connecter</a>
        </p>
        <p class="text-center mt-3">
            <a href="index.php">Accueil</a>
        </p>
    </div>

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
        });
    </script>
</body>
</html>