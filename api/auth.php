<?php
// api/auth.php
require_once __DIR__ . '/config.php'; // Inclut session_start(), $appConfig, et les fonctions utils

header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['username']) || !isset($input['password'])) {
    echo json_encode(['success' => false, 'message' => 'Nom d\'utilisateur et mot de passe requis.']);
    exit;
}

$username = trim($input['username']);
$password = $input['password'];

// Valider la longueur et les caractères du nom d'utilisateur si nécessaire
if (empty($username) || strlen($username) > 50) { // Exemple de validation
    echo json_encode(['success' => false, 'message' => 'Format de nom d\'utilisateur invalide.']);
    exit;
}
if (empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Le mot de passe ne peut pas être vide.']);
    exit;
}

$users = loadUsers();

if (!isset($users[$username])) {
    // Pour la sécurité, il est parfois préférable de ne pas indiquer si c'est le nom d'utilisateur ou le mot de passe qui est incorrect.
    // Cependant, pour une application interne, un message plus clair peut être acceptable.
    error_log("AUTH: Tentative de connexion échouée - Utilisateur inconnu: " . $username);
    echo json_encode(['success' => false, 'message' => 'Identifiants incorrects.']); // Message générique
    exit;
}

$user_data = &$users[$username]; // Utiliser une référence pour les mises à jour potentielles (comme le reset de quota)

if (!isset($user_data['password_hash']) || !password_verify($password, $user_data['password_hash'])) {
    error_log("AUTH: Tentative de connexion échouée - Mot de passe incorrect pour: " . $username);
    echo json_encode(['success' => false, 'message' => 'Identifiants incorrects.']); // Message générique
    exit;
}

// Authentification réussie !
// Stocker l'identifiant de l'utilisateur dans la session PHP.
// Cela sera utilisé par les autres endpoints API pour vérifier que l'utilisateur est connecté.
$_SESSION['user_logged_in_username'] = $username;
$_SESSION['user_nom_display'] = $user_data['nom'] ?? $username; // Pour un affichage potentiel

// Effacer les anciens états de simulation s'ils existent, car une nouvelle connexion
// devrait démarrer une nouvelle configuration de simulation.
unset($_SESSION['simulation_profile']);
unset($_SESSION['simulation_active']);
unset($_SESSION['simulation_niveau']);
unset($_SESSION['simulation_params_collected']);
unset($_SESSION['simulation_current_params']);
// ... autres variables de session liées à la simulation à nettoyer ...


// Réinitialiser le quota si nécessaire
// S'assurer que les champs de quota existent pour cet utilisateur avant de les passer
$user_data['quota_max'] = $user_data['quota_max'] ?? ($appConfig['default_quota_max'] ?? 50);
$user_data['quota_period'] = $user_data['quota_period'] ?? ($appConfig['default_quota_period'] ?? 'daily');
$user_data['requetes_faites'] = $user_data['requetes_faites'] ?? 0;
$user_data['quota_reset_timestamp'] = $user_data['quota_reset_timestamp'] ?? 0; // Initialiser si manquant

$quotaWasReset = resetQuotaIfNeeded($user_data, $appConfig);
if ($quotaWasReset) {
    // $user_data a été modifié par référence par resetQuotaIfNeeded,
    // donc $users contient déjà les modifications.
    if (!saveUsers($users)) {
        error_log("AUTH: Erreur lors de la sauvegarde des utilisateurs après reset de quota pour: " . $username);
        // Ne pas bloquer la connexion pour ça, mais logguer l'erreur.
    }
}

$requetesRestantes = ($user_data['quota_max']) - ($user_data['requetes_faites']);

// Préparer les données utilisateur à renvoyer au client (sans le hash du mot de passe)
$user_info_for_client = [
    'username' => $username, // Le JS utilise ceci comme un ID
    'nom' => $user_data['nom'] ?? 'Utilisateur Inconnu', // Fournir un nom par défaut
    'quota_max' => $user_data['quota_max'],
    'requetes_restantes' => max(0, $requetesRestantes) // S'assurer que ce n'est pas négatif
];

error_log("AUTH: Utilisateur connecté avec succès: " . $username);
echo json_encode([
    'success' => true,
    'user' => $user_info_for_client
    // Optionnel: Vous pourriez renvoyer un token de session ici si vous n'utilisez pas les sessions PHP natives.
    // 'token' => session_id() // Exemple si les sessions PHP sont utilisées et que vous voulez le token côté client
]);
?>