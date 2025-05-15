<?php
// api/chat.php
require_once __DIR__ . '/config.php'; // Pour $appConfig et les fonctions utils

header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true);

// L'authentification se base sur le username envoyé par le client.
// Pour une sécurité accrue, vous devriez implémenter des tokens de session.
// Pour l'instant, on fait confiance que le client (JS) envoie le bon username après un login réussi.
if (!$input || !isset($input['username']) || !isset($input['prompt'])) {
    echo json_encode(['success' => false, 'message' => 'Données invalides.', 'auth_failed' => true]);
    exit;
}

$username = trim($input['username']);
$prompt = trim($input['prompt']);

$users = loadUsers();

if (!isset($users[$username])) {
    echo json_encode(['success' => false, 'message' => 'Utilisateur non authentifié ou inconnu.', 'auth_failed' => true]);
    exit;
}

$user_ref = &$users[$username]; // Référence pour modifier directement

// Assurer que les champs de quota existent
$user_ref['quota_max'] = $user_ref['quota_max'] ?? ($appConfig['default_quota_max'] ?? 50);
$user_ref['quota_period'] = $user_ref['quota_period'] ?? ($appConfig['default_quota_period'] ?? 'daily');
$user_ref['requetes_faites'] = $user_ref['requetes_faites'] ?? 0;
$user_ref['derniere_requete_timestamp'] = $user_ref['derniere_requete_timestamp'] ?? 0;
$user_ref['quota_reset_timestamp'] = $user_ref['quota_reset_timestamp'] ?? 0;


resetQuotaIfNeeded($user_ref, $appConfig);

$quotaMax = $user_ref['quota_max'];
$requetesFaites = $user_ref['requetes_faites'];
$requetesRestantes = $quotaMax - $requetesFaites;

if ($requetesRestantes <= 0) {
    saveUsers($users); // Sauvegarder si resetQuotaIfNeeded a modifié qqch
    echo json_encode([
        'success' => false,
        'message' => 'Votre quota de requêtes est atteint.',
        'quota_exceeded' => true,
        'requetes_restantes' => 0
    ]);
    exit;
}

// Rate Limiting simple
$now = time();
if (($now - $user_ref['derniere_requete_timestamp']) < REQUEST_RATE_LIMIT_SECONDS) {
    echo json_encode([
        'success' => false,
        'message' => 'Veuillez attendre quelques secondes avant de renvoyer une requête.',
        'requetes_restantes' => $requetesRestantes
    ]);
    exit;
}

// --- Appel à l'API OpenAI ---
$apiKey = OPENAI_API_KEY;
if (empty($apiKey) || $apiKey == 'sk-VOTRE_CLE_API_OPENAI_ICI') { // Vérification de base
    error_log("CHAT.PHP: Clé API OpenAI non configurée dans config.php");
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur de configuration serveur (clé API). Veuillez contacter l\'administrateur.',
        'requetes_restantes' => $requetesRestantes
    ]);
    exit;
}

$modelToUse = OPENAI_MODEL; // Vient de config.php, qui lit app_config.json
$systemPromptToUse = SYSTEM_PROMPT; // Vient de config.php, qui lit app_config.json

$data = [
    'model' => $modelToUse,
    'messages' => [
        ['role' => 'system', 'content' => $systemPromptToUse],
        ['role' => 'user', 'content' => $prompt]
    ],
    'max_tokens' => 300, // Augmenté un peu
    'temperature' => 0.7
];

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiKey
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 90); // Augmenté un peu le timeout

$response_body = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
     error_log("CHAT.PHP: Erreur cURL: " . $curlError);
     echo json_encode([
        'success' => false, 
        'message' => 'Erreur de communication avec le service IA. Veuillez réessayer plus tard.',
        'requetes_restantes' => $requetesRestantes
    ]);
    exit;
}

if ($httpcode != 200) {
    $errorDetails = json_decode($response_body, true);
    $errorMessage = $errorDetails['error']['message'] ?? 'Erreur API OpenAI inconnue.';
    error_log("CHAT.PHP: OpenAI API Error ($httpcode): $response_body"); 
    echo json_encode([
        'success' => false, 
        'message' => 'L\'assistant IA a rencontré un problème. (' . $errorMessage . ')',
        'requetes_restantes' => $requetesRestantes
    ]);
    exit;
}

$result = json_decode($response_body, true);
$reply = $result['choices'][0]['message']['content'] ?? 'Désolé, je n\'ai pas pu générer de réponse (réponse vide de l\'IA).';

// Mettre à jour le quota de l'utilisateur
$user_ref['requetes_faites']++;
$user_ref['derniere_requete_timestamp'] = $now; 
if (!saveUsers($users)) {
    error_log("CHAT.PHP: Erreur lors de la sauvegarde des utilisateurs après requête de " . $username);
    // Continuer quand même à envoyer la réponse à l'utilisateur
}

echo json_encode([
    'success' => true,
    'reply' => $reply,
    'requetes_restantes' => $quotaMax - $user_ref['requetes_faites']
]);
?>