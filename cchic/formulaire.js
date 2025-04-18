$(document).ready(function() {

    // --- Fonctionnalité Toggle Password ---
    $(document).on('click', '.toggle-password', function() {
        const target = $(this).data('target');
        const input = $(target);
        const icon = $(this).find('i');

        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            $(this).addClass('active');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            input.attr('type', 'password');
            $(this).removeClass('active');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });

    // --- Gestion de la soumission du formulaire de connexion ---
    if ($('#loginForm').length) {
        $('#loginForm').submit(function(event) {
            event.preventDefault(); // Empêche la soumission standard

            const loginEmail = $('#loginEmail').val().trim();
            const loginPassword = $('#loginPassword').val().trim();

            // Validation simple
            if (!loginEmail || !loginPassword) {
                // Si invalide, on arrête ici, pas de chargeur
                alert('Veuillez fournir un e-mail et un mot de passe valides.');
                return;
            }

            // --- Début de la logique d'affichage du chargeur ---
            // La validation est réussie (ou du moins, les champs sont remplis)

            // 1. Masquer le conteneur du formulaire de connexion avec un fondu
            $('#loginFormContainer').fadeOut(300, function() {
                // Cette fonction callback s'exécute APRES la fin du fadeOut

                // 2. Afficher l'overlay du chargeur avec un fondu
                $('#loaderOverlay').fadeIn(300);

                // 3. Simuler un temps de chargement/traitement (ex: 3 secondes)
                //    Pendant ce temps, le chargeur est visible.
                setTimeout(function() {
                    // --- Fin de la simulation de chargement ---

                    // 4. Masquer l'overlay du chargeur avec un fondu
                    $('#loaderOverlay').fadeOut(300, function() {
                        // Cette fonction callback s'exécute APRES la fin du fadeOut du loader

                        // 5. Action finale après le chargement
                        //    Normalement, rediriger vers la page principale :
                        //    window.location.href = 'dashboard.html'; // Mettez l'URL de votre vraie page ici

                        //    Pour la démo, on affiche une alerte :
                        alert(`Connexion réussie (simulation) pour ${loginEmail}. Redirection vers la page principale...`);

                        // Optionnel : si la redirection échoue ou pour démo, on pourrait
                        // réafficher le formulaire de connexion :
                        // $('#loginFormContainer').fadeIn(300);
                    });

                }, 10000); // Durée de la simulation en millisecondes (10 secondes)
            }); // Fin du callback de fadeOut du formulaire

            // --- Fin de la logique d'affichage du chargeur ---

        }); // Fin du gestionnaire .submit() pour #loginForm
    } // Fin du if ($('#loginForm').length)

    // --- Gestion de la soumission du formulaire de récupération  ---
    if ($('#forgotPasswordForm').length) {
        $('#forgotPasswordForm').submit(function(event) {
            event.preventDefault();
            const forgotEmail = $('#forgotEmail').val().trim();
            if (!forgotEmail) {
                alert('Veuillez fournir une adresse e-mail valide.');
                return;
            }
            alert(`Instructions de réinitialisation envoyées à ${forgotEmail}. Vérifiez votre boîte mail.`);
            // Logique réelle ici...
        });
    }

    // --- Gestion de la soumission du formulaire d'inscription ---
    if ($('#registrationForm').length) {
        $('#registrationForm').submit(function(event) {
            event.preventDefault();
            const fullName = $('#fullName').val().trim();
            const email = $('#email').val().trim();
            const gender = $('#gender').val();
            const password = $('#password').val();
            const confirmPassword = $('#confirmPassword').val();

            if (!fullName || !email || !gender) {
                alert('Veuillez remplir tous les champs obligatoires.');
                return;
            }
            if (password.length < 8) {
                alert('Le mot de passe doit contenir au moins 8 caractères.');
                return;
            }
            if (password !== confirmPassword) {
                alert('Les mots de passe ne correspondent pas.');
                return;
            }
            alert(`Inscription réussie pour ${fullName} (${gender}) avec l'e-mail ${email}. Bienvenue !`);
            // Redirection vers la page principale
            window.location.href = 'home.php';
        });
    }

}); // Fin de $(document).ready()