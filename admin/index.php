<?php
// admin/index.php
require_once __DIR__ . '/../api/config.php'; // Pour session_start() et utils
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
$appConfigGlobal = loadAppConfig(); // Charger la config globale pour affichage
$usersGlobal = loadUsers(); // Charger les utilisateurs pour statistiques rapides
$page = $_GET['page'] ?? 'dashboard'; // Page par défaut
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Chatbot Pédagogique</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css"> <!-- Peut être un style admin spécifique -->
    <style>
        body { background-color: #f4f7f6; }
        .sidebar { position: fixed; top: 0; bottom: 0; left: 0; z-index: 100; padding: 48px 0 0; box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1); background-color: #343a40; }
        .sidebar-sticky { position: relative; top: 0; height: calc(100vh - 48px); padding-top: .5rem; overflow-x: hidden; overflow-y: auto; }
        .nav-link { color: #c2c7d0; }
        .nav-link.active, .nav-link:hover { color: #fff; background-color: #495057; }
        .main-content { margin-left: 220px; padding: 20px;}
        .navbar-brand { padding-top: .75rem; padding-bottom: .75rem; font-size: 1rem; background-color: rgba(0, 0, 0, .25); box-shadow: inset -1px 0 0 rgba(0, 0, 0, .25); color: #fff; }
    </style>
</head>
<body>
    <header class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0 shadow">
        <a class="navbar-brand col-md-3 col-lg-2 me-0 px-3" href="#">Admin Chatbot</a>
        <button class="navbar-toggler position-absolute d-md-none collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="navbar-nav">
            <div class="nav-item text-nowrap">
                <a class="nav-link px-3" href="logout.php">Déconnexion (<?php echo htmlspecialchars($_SESSION['admin_username']); ?>)</a>
            </div>
        </div>
    </header>

    <div class="container-fluid">
        <div class="row">
            <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="sidebar-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($page === 'dashboard' ? 'active' : ''); ?>" href="index.php?page=dashboard">
                                <i class="bi bi-house-door-fill"></i> Tableau de bord
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($page === 'users' ? 'active' : ''); ?>" href="index.php?page=users">
                                <i class="bi bi-people-fill"></i> Gérer les Utilisateurs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($page === 'settings' ? 'active' : ''); ?>" href="index.php?page=settings">
                                <i class="bi bi-gear-fill"></i> Paramètres de l'App
                            </a>
                        </li>
                         <li class="nav-item">
                            <a class="nav-link <?php echo ($page === 'openai_settings' ? 'active' : ''); ?>" href="index.php?page=openai_settings">
                                <i class="bi bi-robot"></i> Paramètres OpenAI
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <main class="main-content col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <?php
                // Charger le contenu de la page demandée
                if ($page === 'dashboard') {
                    include __DIR__ . '/views/dashboard.php';
                } elseif ($page === 'users') {
                    include __DIR__ . '/views/manage_users.php';
                } elseif ($page === 'settings') {
                    include __DIR__ . '/views/manage_app_settings.php';
                } elseif ($page === 'openai_settings') {
                     include __DIR__ . '/views/manage_openai_settings.php';
                } else {
                    echo "<div class='alert alert-warning'>Page non trouvée.</div>";
                }
                ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Script JS spécifique à l'admin si besoin -->
    <script>
        // Petit script pour gérer les messages flash
        const urlParams = new URLSearchParams(window.location.search);
        const message = urlParams.get('message');
        const status = urlParams.get('status');

        if (message && status) {
            const alertPlaceholder = document.createElement('div');
            alertPlaceholder.innerHTML = `<div class="alert alert-${status === 'success' ? 'success' : 'danger'} alert-dismissible fade show" role="alert">
                                            ${decodeURIComponent(message)}
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                          </div>`;
            const mainContent = document.querySelector('.main-content');
            if (mainContent) {
                mainContent.insertBefore(alertPlaceholder, mainContent.firstChild);
            }
            // Nettoyer l'URL pour éviter que le message ne réapparaisse au rechargement
            window.history.replaceState({}, document.title, window.location.pathname + "?page=" + urlParams.get('page'));
        }
    </script>
</body>
</html>