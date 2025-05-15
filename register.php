<?php
require_once 'api/config.php'; // Pour charger $appConfig et ALLOW_REGISTRATION

if (!(ALLOW_REGISTRATION ?? true)) { // Vérifier si les inscriptions sont autorisées
    header("Location: index.php?message=" . urlencode("Les inscriptions sont actuellement fermées."));
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - Chatbot Pédagogique</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css"> <!-- Vous pouvez créer un style spécifique si besoin -->
</head>
<body>
    <div class="container mt-5">
        <div id="register-section" class="card shadow-sm mx-auto" style="max-width: 500px;">
            <div class="card-body">
                <h1 class="card-title text-center mb-4">Inscription</h1>
                <p class="text-center">Créez votre compte pour accéder au Chatbot.</p>
                
                <form id="register-form">
                    <div class="mb-3">
                        <label for="reg-username" class="form-label">Nom d'utilisateur</label>
                        <input type="text" id="reg-username" class="form-control" required>
                        <div class="form-text">Sera utilisé pour vous connecter. Doit être unique.</div>
                    </div>
                    <div class="mb-3">
                        <label for="reg-nom" class="form-label">Nom complet (ou pseudo)</label>
                        <input type="text" id="reg-nom" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="reg-password" class="form-label">Mot de passe</label>
                        <input type="password" id="reg-password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="reg-password-confirm" class="form-label">Confirmer le mot de passe</label>
                        <input type="password" id="reg-password-confirm" class="form-control" required>
                    </div>
                    <button type="submit" id="register-btn" class="btn btn-primary w-100">S'inscrire</button>
                </form>
                <div id="register-message" class="mt-3"></div>
                <p class="mt-3 text-center">
                    Déjà un compte ? <a href="index.php">Connectez-vous ici</a>.
                </p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Simple script pour la page d'inscription
        document.addEventListener('DOMContentLoaded', () => {
            const registerForm = document.getElementById('register-form');
            const registerBtn = document.getElementById('register-btn');
            const registerMessage = document.getElementById('register-message');

            registerForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                registerMessage.textContent = '';
                registerMessage.className = 'mt-3'; // Reset class

                const username = document.getElementById('reg-username').value.trim();
                const nom = document.getElementById('reg-nom').value.trim();
                const password = document.getElementById('reg-password').value;
                const passwordConfirm = document.getElementById('reg-password-confirm').value;

                if (!username || !nom || !password || !passwordConfirm) {
                    registerMessage.textContent = 'Tous les champs sont requis.';
                    registerMessage.classList.add('text-danger');
                    return;
                }
                if (password !== passwordConfirm) {
                    registerMessage.textContent = 'Les mots de passe ne correspondent pas.';
                    registerMessage.classList.add('text-danger');
                    return;
                }
                if (password.length < 6) { // Exemple de règle simple
                    registerMessage.textContent = 'Le mot de passe doit contenir au moins 6 caractères.';
                    registerMessage.classList.add('text-danger');
                    return;
                }


                registerBtn.disabled = true;
                registerBtn.textContent = 'Inscription en cours...';

                try {
                    const response = await fetch('api/register_handler.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ username, nom, password })
                    });
                    const data = await response.json();

                    if (data.success) {
                        registerMessage.textContent = data.message + " Vous allez être redirigé...";
                        registerMessage.classList.add('text-success');
                        setTimeout(() => {
                            window.location.href = 'index.php?registration=success';
                        }, 2000);
                    } else {
                        registerMessage.textContent = data.message || "Erreur lors de l'inscription.";
                        registerMessage.classList.add('text-danger');
                    }
                } catch (error) {
                    registerMessage.textContent = 'Erreur de communication avec le serveur.';
                    registerMessage.classList.add('text-danger');
                    console.error("Registration error:", error);
                } finally {
                    registerBtn.disabled = false;
                    registerBtn.textContent = "S'inscrire";
                }
            });
        });
    </script>
</body>
</html>