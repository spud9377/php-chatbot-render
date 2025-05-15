<?php
// admin/views/dashboard.php
// Assurez-vous que $appConfigGlobal et $usersGlobal sont disponibles (chargés dans index.php)
if (!isset($appConfigGlobal) || !isset($usersGlobal)) {
    // Fallback si ce fichier est appelé directement ou si les variables ne sont pas settées
    $appConfigGlobal = loadAppConfig(); 
    $usersGlobal = loadUsers();
}
$totalUsers = count($usersGlobal);
$totalRequestsToday = 0; // Logique plus complexe pour calculer précisément
// Pour un calcul simple, on pourrait sommer les requetes_faites si le reset est journalier et récent
foreach ($usersGlobal as $user) {
    if (isset($user['quota_period']) && $user['quota_period'] === 'daily' && 
        isset($user['quota_reset_timestamp']) && (time() - $user['quota_reset_timestamp'] < 24*60*60) ) {
        $totalRequestsToday += ($user['requetes_faites'] ?? 0);
    } else if (!isset($user['quota_period'])) { // Si pas de période, on assume que c'est des requêtes "actives"
         $totalRequestsToday += ($user['requetes_faites'] ?? 0);
    }
}

?>
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Tableau de bord</h1>
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <h5 class="card-title"><?php echo $totalUsers; ?></h5>
                <p class="card-text">Utilisateurs Inscrits</p>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card text-white bg-info">
            <div class="card-body">
                <h5 class="card-title"><?php echo htmlspecialchars($appConfigGlobal['openai_model'] ?? 'N/A'); ?></h5>
                <p class="card-text">Modèle GPT Actif</p>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <h5 class="card-title"><?php echo $totalRequestsToday; ?></h5>
                <p class="card-text">Requêtes (Approximation)</p> 
            </div>
        </div>
    </div>
</div>

<div class="mt-4">
    <h4>Bienvenue, <?php echo htmlspecialchars($_SESSION['admin_username']); ?> !</h4>
    <p>Utilisez le menu de gauche pour gérer les utilisateurs et les paramètres de l'application.</p>
    <p><strong>Configuration actuelle :</strong></p>
    <ul>
        <li>Inscriptions ouvertes : <strong><?php echo ($appConfigGlobal['allow_registration'] ?? false) ? 'Oui' : 'Non'; ?></strong></li>
        <li>Quota par défaut : <strong><?php echo htmlspecialchars($appConfigGlobal['default_quota_max'] ?? 'N/A'); ?></strong> requêtes / <strong><?php echo htmlspecialchars($appConfigGlobal['default_quota_period'] ?? 'N/A'); ?></strong></li>
    </ul>
</div>