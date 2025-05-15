<?php
// api/register_handler.php
require_once __DIR__ . '/config.php'; // Pour $appConfig et les fonctions utils

header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true);

if (!(ALLOW_REGISTRATION ?? true)) {
    echo json_encode(['success' => false, 'message' => 'Les inscriptions sont actuellement fermées.']);
    exit;
}

if (!$input || !isset($input['username']) || !isset($input['nom']) || !isset($input['password'])) {
    echo json_encode(['success' => false, 'message' => 'Données d\'inscription invalides.']);
    exit;
}

$username = trim($input['username']);
$nom = trim($input['nom']);
$password = $input['password'];

// Validation simple
if (empty($username) || empty($nom) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Tous les champs sont requis.']);
    exit;
}
if (strlen($username) < 3 || strlen($username) > 50) {
    echo json_encode(['success' => false, 'message' => 'Le nom d\'utilisateur doit contenir entre 3 et 50 caractères.']);
    exit;
}
if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    echo json_encode(['success' => false, 'message' => 'Le nom d\'utilisateur ne peut contenir que des lettres, chiffres et underscores (_).']);
    exit;
}
if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Le mot de passe doit contenir au moins 6 caractères.']);
    exit;
}


$users = loadUsers();

if (isset($users[$username])) {
    echo json_encode(['success' => false, 'message' => 'Ce nom d\'utilisateur est déjà pris.']);
    exit;
}

$password_hash = password_hash($password, PASSWORD_DEFAULT);

$users[$username] = [
    'password_hash' => $password_hash,
    'nom' => $nom,
    'requetes_faites' => 0,
    'quota_max' => $appConfig['default_quota_max'] ?? 50, // Utiliser le quota par défaut de app_config
    'quota_period' => $appConfig['default_quota_period'] ?? 'daily',
    'derniere_requete_timestamp' => 0,
    'quota_reset_timestamp' => time(), // Initialiser au moment de la création
    'date_creation' => date('c') // Format ISO 8601
];

if (saveUsers($users)) {
    echo json_encode(['success' => true, 'message' => 'Inscription réussie !']);
} else {
    error_log("REGISTER_HANDLER: Erreur lors de la sauvegarde des utilisateurs après inscription de " . $username);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur lors de la création du compte.']);
}
?>