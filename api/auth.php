<?php
// api/auth.php
require_once __DIR__ . '/config.php'; // Pour $appConfig et les fonctions utils

header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['username']) || !isset($input['password'])) {
    echo json_encode(['success' => false, 'message' => 'Nom d\'utilisateur et mot de passe requis.']);
    exit;
}

$username = trim($input['username']);
$password = $input['password'];

$users = loadUsers();

if (!isset($users[$username])) {
    echo json_encode(['success' => false, 'message' => 'Nom d\'utilisateur inconnu.']);
    exit;
}

$user_data = &$users[$username]; // Utiliser une référence pour mettre à jour directement

if (!isset($user_data['password_hash']) || !password_verify($password, $user_data['password_hash'])) {
    echo json_encode(['success' => false, 'message' => 'Mot de passe incorrect.']);
    exit;
}

// Le mot de passe est correct

// Réinitialiser le quota si nécessaire
// Assurer que les champs de quota existent pour cet utilisateur avant de les passer
$user_data['quota_max'] = $user_data['quota_max'] ?? ($appConfig['default_quota_max'] ?? 50);
$user_data['quota_period'] = $user_data['quota_period'] ?? ($appConfig['default_quota_period'] ?? 'daily');
$user_data['requetes_faites'] = $user_data['requetes_faites'] ?? 0;
$user_data['quota_reset_timestamp'] = $user_data['quota_reset_timestamp'] ?? 0;


$quotaWasReset = resetQuotaIfNeeded($user_data, $appConfig);
if ($quotaWasReset) {
    saveUsers($users); // Sauvegarder si le quota a été réinitialisé
}

$requetesRestantes = ($user_data['quota_max']) - ($user_data['requetes_faites']);

// Préparer les données utilisateur à renvoyer (sans le hash du mot de passe)
$user_info_for_client = [
    'username' => $username, // Utiliser username comme ID unique
    'nom' => $user_data['nom'] ?? 'Utilisateur',
    'quota_max' => $user_data['quota_max'],
    'requetes_restantes' => max(0, $requetesRestantes)
];

echo json_encode([
    'success' => true,
    'user' => $user_info_for_client
    // Optionnel: 'token' => session_id() // Si vous utilisez des sessions PHP robustes
]);
?>