<?php
// api/config.php
session_start(); // Nécessaire pour l'authentification admin plus tard

define('OPENAI_API_KEY', 'sk-VOTRE_CLE_API_OPENAI_ICI'); // REMPLACEZ PAR VOTRE VRAIE CLÉ
define('USER_DATA_FILE', __DIR__ . '/../data/users.json');
define('APP_CONFIG_FILE', __DIR__ . '/../data/app_config.json');

// Protection de base contre les requêtes trop rapides (optionnel)
define('REQUEST_RATE_LIMIT_SECONDS', 2); 

// Identifiants pour l'Administrateur du Back-Office
// !! CHANGEZ CE MOT DE PASSE !! Utilisez un mot de passe fort.
// Pour générer un hash sécurisé pour un nouveau mot de passe, vous pouvez utiliser temporairement :
// echo password_hash("VotreNouveauMotDePasseAdmin", PASSWORD_DEFAULT); die();
// Puis copiez le hash généré ici.
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD_HASH', '$2y$10$xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'); // REMPLACEZ PAR UN HASH VALIDE GENERÉ POUR VOTRE MOT DE PASSE ADMIN

// Inclure les fonctions utilitaires
require_once __DIR__ . '/utils.php';

// Charger la configuration de l'application
$appConfig = loadAppConfig();
define('OPENAI_MODEL', $appConfig['openai_model'] ?? 'gpt-3.5-turbo');
define('DEFAULT_QUOTA_MAX', $appConfig['default_quota_max'] ?? 50);
define('DEFAULT_QUOTA_PERIOD', $appConfig['default_quota_period'] ?? 'daily');
define('ALLOW_REGISTRATION', $appConfig['allow_registration'] ?? true);
define('SYSTEM_PROMPT', $appConfig['system_prompt'] ?? "Tu es un assistant IA.");

?>