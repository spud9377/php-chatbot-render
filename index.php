<?php
require_once 'api/config.php'; 
$registration_allowed = ALLOW_REGISTRATION ?? true; 
$login_message = $_GET['message'] ?? '';
if (isset($_GET['registration']) && $_GET['registration'] === 'success') {
    $login_message = "Inscription réussie ! Vous pouvez maintenant vous connecter.";
}
if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $login_message = "Vous avez été déconnecté.";
}
// La variable $appConfig est déjà chargée via config.php puis utils.php
// On peut l'utiliser pour le system_prompt si besoin, mais pour l'instant
// elle est surtout utile pour les codes de simulation côté serveur.
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chatbot Pédagogique GPT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/remarkable/2.0.1/remarkable.min.js"></script> <!-- Ajout CDN Remarkable -->
</head>
<body>
    <div class="container mt-5">
        <!-- Section Connexion (initialement visible) -->
        <div id="login-section" class="card shadow-sm mx-auto" style="max-width: 500px;">
            <div class="card-body">
                <h1 class="card-title text-center mb-4">📘 Chatbot Pédagogique</h1>
                <p class="text-center">Connectez-vous pour commencer.</p>
                
                <?php if ($login_message): ?>
                    <div class="alert <?php echo (isset($_GET['registration']) && $_GET['registration'] === 'success') || (isset($_GET['logout']) && $_GET['logout'] === 'success') ? 'alert-success' : 'alert-info'; ?>" role="alert">
                        <?php echo htmlspecialchars($login_message); ?>
                    </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label for="username" class="form-label">Nom d'utilisateur</label>
                    <input type="text" id="username" class="form-control" placeholder="Votre nom d'utilisateur">
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Mot de passe</label>
                    <input type="password" id="password" class="form-control" placeholder="Votre mot de passe">
                </div>
                <button id="login-btn" class="btn btn-primary w-100">Se connecter</button>
                <div id="login-error" class="text-danger mt-2"></div>

                <?php if ($registration_allowed): ?>
                <p class="mt-3 text-center">
                    Pas encore de compte ? <a href="register.php">Inscrivez-vous ici</a>.
                </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Section Code Simulation (initialement cachée) -->
        <div id="simulation-code-section" class="card shadow-sm mx-auto d-none" style="max-width: 500px;">
            <div class="card-body">
                <h3 class="card-title text-center mb-3">Accès à la Simulation</h3>
                <p class="text-center">Veuillez entrer le code d'accès correspondant à votre profil pour la simulation de négociation.</p>
                <div class="mb-3">
                    <label for="simulation-code" class="form-label">Code d'accès Simulation</label>
                    <input type="text" id="simulation-code" class="form-control" placeholder="Entrez le code (ex: eto)">
                </div>
                 <div class="mb-3">
                    <label for="simulation-role" class="form-label">Choisissez votre rôle</label>
                    <select id="simulation-role" class="form-select">
                        <option value="etudiant" selected>Étudiant</option>
                        <option value="jury">Jury</option>
                    </select>
                </div>
                <button id="submit-simulation-code-btn" class="btn btn-info w-100">Valider le profil de simulation</button>
                <div id="simulation-code-error" class="text-danger mt-2"></div>
                <p class="mt-3 text-center"><button id="skip-simulation-btn" class="btn btn-sm btn-link">Utiliser le chatbot normalement (sans simulation)</button></p>
            </div>
        </div>


        <!-- Section Chat (initialement cachée) -->
        <div id="chat-section" class="card shadow-sm d-none mx-auto" style="max-width: 700px;">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span id="welcome-message">Chat avec l'IA</span>
                <div>
                    <small>Quota: <span id="quota-display">N/A</span></small>
                    <button id="logout-btn" class="btn btn-sm btn-outline-secondary ms-2">Déconnexion</button>
                </div>
            </div>
            <div class="card-body chat-window" id="chat-window">
                <!-- Les messages du chat apparaîtront ici -->
            </div>
            <div class="card-footer">
                <div class="input-group">
                    <textarea id="user-input" class="form-control" placeholder="Posez votre question..." rows="2"></textarea>
                    <button id="send-btn" class="btn btn-success">Envoyer</button>
                </div>
                <div id="chat-info" class="text-muted small mt-2"></div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js"></script> 
</body>
</html>