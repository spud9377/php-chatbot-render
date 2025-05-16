<?php
// api/set_simulation_profile.php
require_once __DIR__ . '/config.php'; // Pour session_start, $appConfig et les fonctions utils

header('Content-Type: application/json');

if (!isset($_SESSION['user_logged_in_username'])) { // Vérifier si l'utilisateur est connecté via la session principale
    // Cette variable de session devrait être settée dans api/auth.php après un login réussi
    echo json_encode(['success' => false, 'message' => 'Utilisateur non authentifié pour définir un profil.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['username']) || $input['username'] !== $_SESSION['user_logged_in_username']) {
    echo json_encode(['success' => false, 'message' => 'Incohérence utilisateur. Veuillez vous reconnecter.']);
    exit;
}

if (!isset($input['simulation_code']) || !isset($input['role'])) {
    echo json_encode(['success' => false, 'message' => 'Code de simulation ou rôle manquant.']);
    exit;
}

$simulation_code_input = trim($input['simulation_code']);
$role_input = trim($input['role']); // 'etudiant' ou 'jury'

// Charger les codes de simulation depuis app_config.json (via $appConfig déjà chargé)
$valid_code_etudiant = $appConfig['simulation_codes']['etudiant'] ?? null;
$valid_code_jury = $appConfig['simulation_codes']['jury'] ?? null;

$profile_set = false;
$message_to_user = '';

if ($role_input === 'etudiant' && $simulation_code_input === $valid_code_etudiant) {
    $_SESSION['simulation_profile'] = 'etudiant';
    $_SESSION['simulation_niveau'] = 1; // Initialiser niveau pour étudiant
    $_SESSION['simulation_params_collected'] = false; // Pour la collecte des 8 paramètres
    $_SESSION['simulation_current_params'] = [];
    $profile_set = true;
    $message_to_user = "Profil Étudiant activé. Préparation de la simulation...";
} elseif ($role_input === 'jury' && $simulation_code_input === $valid_code_jury) {
    $_SESSION['simulation_profile'] = 'jury';
    // Pas de niveau pour le jury de cette manière, il aura accès à tout
    $_SESSION['simulation_params_collected'] = false; // Le jury pourrait aussi définir des params pour observer
    $_SESSION['simulation_current_params'] = [];
    $profile_set = true;
    $message_to_user = "Profil Jury activé. Vous avez accès aux fonctionnalités jury.";
}

if ($profile_set) {
    $_SESSION['simulation_active'] = true; // Marqueur global que la simulation est en cours/configurée
    echo json_encode([
        'success' => true, 
        'profile' => $_SESSION['simulation_profile'],
        'message_to_user' => $message_to_user
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Code d\'accès à la simulation incorrect pour le rôle sélectionné.']);
}
?>