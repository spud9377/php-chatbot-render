<?php
// api/chat.php
require_once __DIR__ . '/config.php'; // Inclut session_start(), $appConfig, et les fonctions utils

header('Content-Type: application/json');

// 1. Vérifier l'authentification de l'utilisateur via la session PHP
if (!isset($_SESSION['user_logged_in_username'])) {
    error_log("CHAT: Accès refusé - Session utilisateur non trouvée.");
    echo json_encode(['success' => false, 'message' => 'Authentification requise. Veuillez vous reconnecter.', 'auth_failed' => true]);
    exit;
}
$username = $_SESSION['user_logged_in_username']; // Utiliser le username de la session comme source de vérité

// 2. Récupérer et valider les données d'entrée JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['prompt'])) { // Le username du JS n'est plus critique ici si on se fie à la session
    error_log("CHAT: Données d'entrée invalides pour l'utilisateur: " . $username);
    echo json_encode(['success' => false, 'message' => 'Données de requête invalides.', 'auth_failed' => false]); // Pas auth_failed si la session est bonne
    exit;
}
$prompt_text_user = trim($input['prompt']);
// $simulation_profile_from_js = $input['simulation_profile'] ?? null; // On se fie à la session pour le profil

// 3. Charger les données utilisateur et vérifier l'existence
$users = loadUsers();
if (!isset($users[$username])) {
    error_log("CHAT: Utilisateur de session '$username' non trouvé en base. Forcer déconnexion.");
    // Invalider la session si l'utilisateur n'existe plus en base
    unset($_SESSION['user_logged_in_username']);
    unset($_SESSION['user_nom_display']);
    // ... nettoyer autres variables de session ...
    echo json_encode(['success' => false, 'message' => 'Erreur de compte utilisateur. Veuillez vous reconnecter.', 'auth_failed' => true]);
    exit;
}
$user_ref = &$users[$username]; // Référence pour les mises à jour de quota

// 4. Gestion du Quota (identique à avant, mais après vérification utilisateur)
$user_ref['quota_max'] = $user_ref['quota_max'] ?? ($appConfig['default_quota_max'] ?? 50);
$user_ref['quota_period'] = $user_ref['quota_period'] ?? ($appConfig['default_quota_period'] ?? 'daily');
$user_ref['requetes_faites'] = $user_ref['requetes_faites'] ?? 0;
$user_ref['derniere_requete_timestamp'] = $user_ref['derniere_requete_timestamp'] ?? 0;
$user_ref['quota_reset_timestamp'] = $user_ref['quota_reset_timestamp'] ?? 0;

resetQuotaIfNeeded($user_ref, $appConfig); // $appConfig est chargé via config.php
$quotaMax = $user_ref['quota_max'];
$requetesFaites = $user_ref['requetes_faites'];
$requetesRestantes = $quotaMax - $requetesFaites;

if ($requetesRestantes <= 0 && !($_SESSION['simulation_params_collecting_active'] ?? false) ) { // Ne pas bloquer si on collecte des params
    saveUsers($users); // Sauvegarder si resetQuotaIfNeeded a modifié quelque chose
    error_log("CHAT: Quota atteint pour '$username'.");
    echo json_encode(['success' => false, 'message' => 'Votre quota de requêtes est atteint.', 'quota_exceeded' => true, 'requetes_restantes' => 0]);
    exit;
}
$now = time();
if (($now - $user_ref['derniere_requete_timestamp']) < REQUEST_RATE_LIMIT_SECONDS && !($_SESSION['simulation_params_collecting_active'] ?? false)) {
    error_log("CHAT: Rate limit pour '$username'.");
    echo json_encode(['success' => false, 'message' => 'Veuillez attendre quelques secondes.', 'requetes_restantes' => $requetesRestantes]);
    exit;
}
// --- Fin Gestion du Quota ---


// 5. Logique de Simulation et de Collecte de Paramètres
$active_simulation_profile = $_SESSION['simulation_profile'] ?? null;
$is_simulation_active_session = isset($_SESSION['simulation_active']) && $_SESSION['simulation_active'] === true;

// Noms des paramètres à collecter (doit correspondre à l'ordre dans script.js)
$sim_params_keys_ordered = ["nom", "prénom", "classe", "niveau", "ville", "activité", "type_client", "entreprise"];


// Cas 1: L'utilisateur envoie un message alors qu'on attend la collecte des paramètres de simulation
if ($is_simulation_active_session && ($active_simulation_profile === 'etudiant' || $active_simulation_profile === 'jury') && ($_SESSION['simulation_params_collecting_active'] ?? false)) {
    
    $current_param_index_session = $_SESSION['simulation_param_current_index'] ?? 0;
    
    if ($current_param_index_session < count($sim_params_keys_ordered)) {
        $param_key_being_collected = $sim_params_keys_ordered[$current_param_index_session];
        $_SESSION['simulation_current_params'][$param_key_being_collected] = $prompt_text_user;
        $_SESSION['simulation_param_current_index']++;
        
        // Préparer le message pour demander le paramètre suivant ou indiquer la fin
        $next_param_index_session = $_SESSION['simulation_param_current_index'];
        if ($next_param_index_session < count($sim_params_keys_ordered)) {
            $next_param_name_display = $appConfig['sim_params_display_names'][$sim_params_keys_ordered[$next_param_index_session]] ?? $sim_params_keys_ordered[$next_param_index_session];
            $reply_text = "Merci. Veuillez maintenant entrer : **" . ucfirst($next_param_name_display) . "**";
        } else {
            $_SESSION['simulation_params_collecting_active'] = false; // Fin de la collecte
            $_SESSION['simulation_params_collected'] = true; // Marquer que les params sont OK
            $reply_text = "Merci ! Tous les paramètres ont été collectés. La simulation va commencer...";
            // Ici, on pourrait faire un premier appel à l'IA pour démarrer la simulation
            // Pour l'instant, on va laisser l'utilisateur envoyer un premier "vrai" message
        }
        
        echo json_encode([
            'success' => true, 
            'reply' => $reply_text, // C'est le message du "système" (chatbot)
            'requetes_restantes' => $requetesRestantes, // Le quota n'est pas décrémenté pour la collecte
            'is_collecting_params' => ($_SESSION['simulation_params_collecting_active'] ?? false)
        ]);
        exit; // Important de sortir ici pour ne pas aller à l'IA
    }
}

// Cas 2: L'utilisateur a soumis tous les paramètres et c'est la première action pour démarrer la sim
if ($is_simulation_active_session && ($_SESSION['simulation_params_collected'] ?? false) && ($input['action'] ?? '') === 'start_simulation_with_params') {
    // Les paramètres sont dans $input['params'] envoyés par le JS après la collecte.
    // On les met à jour en session si nécessaire (normalement déjà fait par la collecte ci-dessus)
    $_SESSION['simulation_current_params'] = $input['params'] ?? $_SESSION['simulation_current_params'] ?? [];
    $_SESSION['simulation_params_collected'] = true;
    unset($_SESSION['simulation_params_collecting_active']); // Assurer que ce n'est plus actif

    // TODO: Construire le premier prompt pour l'IA basé sur le rôle et les paramètres
    // et les instructions système spécifiques à la simulation.
    $system_prompt_for_simulation = "INSTRUCTIONS SYSTÈME POUR SIMULATION:\n";
    $system_prompt_for_simulation .= "Tu es un créateur de scénarios pédagogiques pour la négociation commerciale. Ton public est constitué d'étudiants en BTS NDRC 2ᵉ année et de leurs enseignants (jury). Tu guides l’utilisateur à travers une simulation d'entretien de négociation structurée.\n";
    $system_prompt_for_simulation .= "PROFIL ACTUEL: " . ucfirst($active_simulation_profile) . "\n";
    $system_prompt_for_simulation .= "PARAMÈTRES DU SCÉNARIO:\n";
    foreach ($_SESSION['simulation_current_params'] as $key => $value) {
        $displayKey = $appConfig['sim_params_display_names'][$key] ?? ucfirst(str_replace('_', ' ', $key));
        $system_prompt_for_simulation .= "- " . $displayKey . ": " . htmlspecialchars($value) . "\n";
    }
    if ($active_simulation_profile === 'etudiant') {
        $_SESSION['simulation_niveau'] = (int)($_SESSION['simulation_current_params']['niveau'] ?? 1); // S'assurer que le niveau est bien initialisé
        $system_prompt_for_simulation .= "NIVEAU ACTUEL DE L'ÉTUDIANT: " . $_SESSION['simulation_niveau'] . "\n";
        // TODO: Ajouter les instructions spécifiques au niveau 1 pour l'étudiant
        $system_prompt_for_simulation .= "L'étudiant commence. Tu es le client/prospect. Fais ta première intervention en tant que client.";
    } elseif ($active_simulation_profile === 'jury') {
        // TODO: Instructions pour le jury (ex: "Que souhaitez-vous faire ? Voir la trame, lancer une simulation pour un étudiant...")
        $system_prompt_for_simulation .= "Le jury est connecté. Présentez les options disponibles (ex: 'voir la trame du scénario', 'démarrer une simulation que j'observerai').";
    }
    
    // Pour cet appel initial, le 'prompt' de l'utilisateur est l'action de démarrage.
    // On peut l'ignorer ou l'utiliser comme un "Bonjour, je suis prêt".
    // L'IA doit initier la conversation de simulation.
    $messages_for_openai = [
        ['role' => 'system', 'content' => $system_prompt_for_simulation],
        ['role' => 'user', 'content' => "Je suis prêt à commencer la simulation en tant que " . $active_simulation_profile . "."] // Prompt utilisateur générique
    ];

} 
// Cas 3: Simulation active, paramètres collectés, conversation normale de simulation
elseif ($is_simulation_active_session && ($_SESSION['simulation_params_collected'] ?? false)) {
    // TODO: Construire le prompt pour l'IA basé sur le rôle, le niveau, les paramètres,
    // et l'historique de la simulation (à stocker en session aussi: $_SESSION['simulation_history'])
    $system_prompt_for_simulation = "INSTRUCTIONS SYSTÈME POUR SIMULATION (en cours):\n";
    $system_prompt_for_simulation .= "Tu es un créateur de scénarios pédagogiques pour la négociation commerciale... (instructions générales abrégées ou rappel)\n";
    $system_prompt_for_simulation .= "PROFIL ACTUEL: " . ucfirst($active_simulation_profile) . "\n";
    if ($active_simulation_profile === 'etudiant') {
        $system_prompt_for_simulation .= "NIVEAU ACTUEL DE L'ÉTUDIANT: " . ($_SESSION['simulation_niveau'] ?? 1) . "\n";
        // TODO: Ajouter règles spécifiques (temps, évaluation, etc.)
        // "L'étudiant a 1min30 pour répondre. Évalue sa réponse sur 20 après chaque tour. S'il obtient >=15, il passe au niveau suivant."
    }
    $system_prompt_for_simulation .= "PARAMÈTRES DU SCÉNARIO: " . json_encode($_SESSION['simulation_current_params']) . "\n";
    $system_prompt_for_simulation .= "HISTORIQUE RÉCENT: ... (à implémenter)\n"; // Important pour le contexte
    $system_prompt_for_simulation .= "Réponds à l'intervention de l'utilisateur dans le cadre de la simulation.";

    $messages_for_openai = [
        ['role' => 'system', 'content' => $system_prompt_for_simulation]
        // TODO: Ajouter $_SESSION['simulation_history'] ici
    ];
    // Ajouter le dernier message de l'utilisateur
    $messages_for_openai[] = ['role' => 'user', 'content' => $prompt_text_user];

} 
// Cas 4: Pas de simulation active, ou profil de simulation non défini (mode standard)
else {
    $messages_for_openai = [
        ['role' => 'system', 'content' => ($appConfig['system_prompt'] ?? "Tu es un assistant IA.")],
        // TODO: Ajouter historique de conversation standard si implémenté
        ['role' => 'user', 'content' => $prompt_text_user]
    ];
}
// --- Fin Logique de Simulation (construction du message pour OpenAI) ---


// 6. Appel à l'API OpenAI
$apiKey = OPENAI_API_KEY; // Défini dans config.php
if (empty($apiKey) || $apiKey == 'sk-VOTRE_CLE_API_OPENAI_ICI') { 
    error_log("CHAT: Clé API OpenAI non configurée pour '$username'.");
    echo json_encode(['success' => false, 'message' => 'Erreur de configuration serveur (clé API). Veuillez contacter l\'administrateur.', 'requetes_restantes' => $requetesRestantes]);
    exit;
}
$modelToUse = $appConfig['openai_model'] ?? 'gpt-3.5-turbo';

$data_to_openai = [
    'model' => $modelToUse,
    'messages' => $messages_for_openai, // Utilise les messages construits ci-dessus
    'max_tokens' => 500, // Augmenté pour des scénarios potentiellement plus longs
    'temperature' => 0.75 // Un peu plus de créativité pour la simulation
];

// Log du payload envoyé à OpenAI (utile pour le débogage des prompts)
// error_log("CHAT: Payload OpenAI pour '$username': " . json_encode($data_to_openai));

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data_to_openai));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiKey
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 90); 

$response_body = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// 7. Traitement de la réponse OpenAI et mise à jour du quota
if ($curlError) {
     error_log("CHAT: Erreur cURL pour '$username': " . $curlError);
     echo json_encode(['success' => false, 'message' => 'Erreur de communication avec le service IA. Veuillez réessayer plus tard.', 'requetes_restantes' => $requetesRestantes]);
    exit;
}

if ($httpcode != 200) {
    $errorDetails = json_decode($response_body, true);
    $errorMessage = $errorDetails['error']['message'] ?? 'Erreur API OpenAI inconnue.';
    error_log("CHAT: OpenAI API Error ($httpcode) pour '$username': $response_body"); 
    echo json_encode(['success' => false, 'message' => 'L\'assistant IA a rencontré un problème : ' . substr($errorMessage, 0, 100) . (strlen($errorMessage)>100 ? '...' :''), 'requetes_restantes' => $requetesRestantes]);
    exit;
}

$result = json_decode($response_body, true);
$reply_from_ai = $result['choices'][0]['message']['content'] ?? 'Désolé, je n\'ai pas pu générer de réponse (réponse vide de l\'IA).';

// TODO: Si en mode simulation, analyser $reply_from_ai pour extraire la note, le feedback,
// mettre à jour $_SESSION['simulation_niveau'], $_SESSION['simulation_history'], etc.

// Mettre à jour le quota de l'utilisateur (seulement pour les "vrais" appels à l'IA, pas la collecte)
if (!($_SESSION['simulation_params_collecting_active'] ?? false)) {
    $user_ref['requetes_faites']++;
    $user_ref['derniere_requete_timestamp'] = $now; 
    if (!saveUsers($users)) {
        error_log("CHAT: Erreur lors de la sauvegarde du quota pour '$username'.");
    }
}

echo json_encode([
    'success' => true,
    'reply' => $reply_from_ai,
    'requetes_restantes' => $quotaMax - $user_ref['requetes_faites'],
    'is_collecting_params' => ($_SESSION['simulation_params_collecting_active'] ?? false) // Renvoyer cet état au JS
]);
?>