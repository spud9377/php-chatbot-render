<?php
// admin/api/admin_actions.php
require_once __DIR__ . '/../../api/config.php'; // Chemin ajusté pour remonter de deux niveaux

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Si pas admin, rediriger ou renvoyer une erreur.
    header('Location: ../login.php?message=' . urlencode("Accès non autorisé.") . '&status=error');
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? null;
$redirect_page = '../index.php?page=dashboard'; // Page de redirection par défaut
$message = '';
$status = 'error'; // 'success' or 'error' ou 'info'

// --- Fonctions de gestion de la clé API dans config.php ---
// Cette fonction tente de mettre à jour la clé API directement dans le fichier config.php.
// ATTENTION: Cela nécessite que le fichier api/config.php soit inscriptible par le serveur web,
// ce qui peut être un risque de sécurité et n'est souvent pas possible sur des hébergements
// comme Render où le système de fichiers du code est en lecture seule après le build.
// La méthode recommandée pour gérer la clé API est via les variables d'environnement du serveur.
function update_openai_api_key_in_config($new_key) {
    $config_file_path = __DIR__ . '/../../api/config.php'; // Chemin vers api/config.php
    
    // Vérifier si le fichier est inscriptible
    if (!is_writable($config_file_path)) {
        // Si le fichier n'est pas inscriptible, vérifier si le dossier parent l'est
        // (cela peut être nécessaire pour certains hébergeurs pour modifier les permissions du fichier)
        if (!is_writable(dirname($config_file_path))) {
            return "Le fichier de configuration principal (api/config.php) et son dossier parent ne sont pas inscriptibles. Veuillez mettre à jour la clé API manuellement dans le fichier ou via les variables d'environnement du serveur.";
        }
        // Tenter de rendre le fichier inscriptible temporairement (peut ne pas fonctionner)
        // @chmod($config_file_path, 0664);
        // if (!is_writable($config_file_path)) {
        //     return "Le fichier de configuration principal (api/config.php) n'est pas inscriptible (tentative de chmod échouée).";
        // }
    }

    $config_content = file_get_contents($config_file_path);
    if ($config_content === false) {
        return "Impossible de lire le fichier de configuration principal (api/config.php).";
    }

    // Regex pour trouver define('OPENAI_API_KEY', '...');
    $pattern = "/(define\s*\(\s*['\"]OPENAI_API_KEY['\"]\s*,\s*['\"])(.*?)(['\"]\s*\)\s*;)/";
    $replacement = '${1}' . addslashes($new_key) . '${3}'; // addslashes pour échapper les '
    
    $new_config_content = preg_replace($pattern, $replacement, $config_content, 1, $count);

    if ($count > 0) {
        if (file_put_contents($config_file_path, $new_config_content) !== false) {
            // Optionnel: Tenter de remettre des permissions plus restrictives (peut ne pas fonctionner)
            // @chmod($config_file_path, 0644);
            return true; // Succès
        } else {
            return "Impossible d'écrire dans le fichier de configuration principal (api/config.php) après modification.";
        }
    } else {
        // Si define n'a pas été trouvé, on peut essayer de l'ajouter, mais c'est plus risqué
        // Pour l'instant, on considère que c'est une erreur si la constante n'est pas trouvée.
        return "La constante OPENAI_API_KEY n'a pas été trouvée ou le format est inattendu dans api/config.php. La clé n'a pas été mise à jour.";
    }
}


// --- Gestion des utilisateurs ---
if ($action === 'add_user') {
    $redirect_page = '../index.php?page=users';
    $username = trim($_POST['username'] ?? '');
    $nom = trim($_POST['nom'] ?? '');
    $password = $_POST['password'] ?? '';
    $quota_max = (int)($_POST['quota_max'] ?? ($appConfig['default_quota_max'] ?? 50));
    $quota_period = $_POST['quota_period'] ?? ($appConfig['default_quota_period'] ?? 'daily');

    if (empty($username) || empty($nom) || empty($password)) {
        $message = "Tous les champs (nom d'utilisateur, nom, mot de passe) sont requis pour l'ajout.";
    } elseif (strlen($password) < 6) {
        $message = "Le mot de passe doit faire au moins 6 caractères.";
    } elseif (!preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) { // Autoriser points et tirets aussi
        $message = 'Le nom d\'utilisateur ne peut contenir que des lettres, chiffres, underscores (_), points (.) et tirets (-).';
    } else {
        $users = loadUsers();
        if (isset($users[$username])) {
            $message = "Le nom d'utilisateur '$username' existe déjà.";
        } else {
            $users[$username] = [
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'nom' => $nom,
                'requetes_faites' => 0,
                'quota_max' => $quota_max,
                'quota_period' => $quota_period,
                'derniere_requete_timestamp' => 0,
                'quota_reset_timestamp' => time(),
                'date_creation' => date('c')
            ];
            if (saveUsers($users)) {
                $message = "Utilisateur '$username' ajouté avec succès.";
                $status = 'success';
            } else {
                $message = "Erreur lors de la sauvegarde de l'utilisateur '$username'.";
            }
        }
    }
} elseif ($action === 'edit_user') {
    $redirect_page = '../index.php?page=users';
    $username = trim($_POST['username'] ?? ''); 
    $nom = trim($_POST['nom'] ?? '');
    $new_password = $_POST['password'] ?? '';
    $quota_max = (int)($_POST['quota_max'] ?? ($appConfig['default_quota_max'] ?? 50));
    $quota_period = $_POST['quota_period'] ?? ($appConfig['default_quota_period'] ?? 'daily');

    if (empty($username) || empty($nom)) {
        $message = "Le nom d'utilisateur et le nom complet sont requis.";
    } else {
        $users = loadUsers();
        if (!isset($users[$username])) {
            $message = "Utilisateur '$username' non trouvé.";
        } else {
            $users[$username]['nom'] = $nom;
            $users[$username]['quota_max'] = $quota_max;
            $users[$username]['quota_period'] = $quota_period;
            if (!empty($new_password)) {
                if (strlen($new_password) < 6) {
                     $message = "Le nouveau mot de passe doit faire au moins 6 caractères (non modifié).";
                } else {
                    $users[$username]['password_hash'] = password_hash($new_password, PASSWORD_DEFAULT);
                }
            }
            if (saveUsers($users)) {
                $message = "Utilisateur '$username' mis à jour avec succès.";
                $status = 'success';
            } else {
                $message = "Erreur lors de la sauvegarde des modifications pour '$username'.";
            }
        }
    }
} elseif ($action === 'delete_user') {
    $redirect_page = '../index.php?page=users';
    $username = trim($_POST['username'] ?? '');
    if (empty($username)) {
        $message = "Nom d'utilisateur manquant pour la suppression.";
    } elseif ($username === ADMIN_USERNAME) { 
         $message = "L'utilisateur admin principal ne peut pas être supprimé via cette interface.";
    }else {
        $users = loadUsers();
        if (!isset($users[$username])) {
            $message = "Utilisateur '$username' non trouvé.";
        } else {
            unset($users[$username]);
            if (saveUsers($users)) {
                $message = "Utilisateur '$username' supprimé avec succès.";
                $status = 'success';
            } else {
                $message = "Erreur lors de la suppression de l'utilisateur '$username'.";
            }
        }
    }
} elseif ($action === 'reset_user_quota') {
    $redirect_page = '../index.php?page=users';
    $username = trim($_POST['username'] ?? '');
     if (empty($username)) {
        $message = "Nom d'utilisateur manquant pour la réinitialisation du quota.";
    } else {
        $users = loadUsers();
        if (!isset($users[$username])) {
            $message = "Utilisateur '$username' non trouvé.";
        } else {
            $users[$username]['requetes_faites'] = 0;
            $users[$username]['quota_reset_timestamp'] = time(); 
            if (saveUsers($users)) {
                $message = "Quota de l'utilisateur '$username' réinitialisé avec succès.";
                $status = 'success';
            } else {
                $message = "Erreur lors de la réinitialisation du quota pour '$username'.";
            }
        }
    }
}

// --- Gestion des paramètres de l'application (incluant codes de simulation) ---
elseif ($action === 'update_app_settings') {
    $redirect_page = '../index.php?page=settings';
    $currentConfig = loadAppConfig();

    $currentConfig['allow_registration'] = isset($_POST['allow_registration']); 
    $currentConfig['default_quota_max'] = (int)($_POST['default_quota_max'] ?? 50);
    $currentConfig['default_quota_period'] = $_POST['default_quota_period'] ?? 'daily';
    
    // Ajout pour les codes de simulation
    if (isset($_POST['simulation_codes']) && is_array($_POST['simulation_codes'])) {
        $currentConfig['simulation_codes']['etudiant'] = trim($_POST['simulation_codes']['etudiant'] ?? 'eto');
        $currentConfig['simulation_codes']['jury'] = trim($_POST['simulation_codes']['jury'] ?? 'eto');
    } else {
        // S'assurer que la structure existe même si le formulaire est vide
        $currentConfig['simulation_codes']['etudiant'] = $currentConfig['simulation_codes']['etudiant'] ?? 'eto';
        $currentConfig['simulation_codes']['jury'] = $currentConfig['simulation_codes']['jury'] ?? 'eto';
    }
    
    if (saveAppConfig($currentConfig)) {
        $message = "Paramètres généraux et codes de simulation mis à jour avec succès.";
        $status = 'success';
    } else {
        $message = "Erreur lors de la sauvegarde des paramètres généraux et/ou des codes de simulation.";
    }
}

// --- Gestion des paramètres OpenAI ---
elseif ($action === 'update_openai_settings') {
    $redirect_page = '../index.php?page=openai_settings';
    $currentAppConfig = loadAppConfig(); // Charger app_config.json
    $appConfigModified = false;
    $apiKeyUpdateStatusMessage = '';
    $apiKeyUpdateSuccessful = false;

    // Mettre à jour la clé API dans api/config.php si fournie et différente de la version masquée
    if (!empty($_POST['openai_api_key'])) {
        $new_key = trim($_POST['openai_api_key']);
        // Petite heuristique pour ne pas essayer d'écrire la clé masquée contenant des '*'
        if (strpos($new_key, '*') === false && $new_key !== OPENAI_API_KEY) {
            $update_result = update_openai_api_key_in_config($new_key);
            if ($update_result === true) {
                $apiKeyUpdateStatusMessage = "Clé API OpenAI mise à jour dans le fichier de configuration (api/config.php). ";
                $apiKeyUpdateSuccessful = true; 
            } else {
                $apiKeyUpdateStatusMessage = "Erreur tentative mise à jour clé API : " . htmlspecialchars($update_result) . ". Vérifiez les permissions du fichier api/config.php. ";
            }
        } elseif ($new_key === OPENAI_API_KEY) {
            // Clé inchangée, pas d'action, pas de message spécifique
        } else {
            // Clé masquée reçue, ne rien faire
        }
    }
    
    // Mettre à jour le modèle et le prompt système dans app_config.json
    if (isset($_POST['openai_model']) && $_POST['openai_model'] !== ($currentAppConfig['openai_model'] ?? '')) {
        $currentAppConfig['openai_model'] = $_POST['openai_model'];
        $appConfigModified = true;
    }
    if (isset($_POST['system_prompt']) && $_POST['system_prompt'] !== ($currentAppConfig['system_prompt'] ?? '')) {
        $currentAppConfig['system_prompt'] = $_POST['system_prompt'];
        $appConfigModified = true;
    }

    if ($appConfigModified) {
        if (saveAppConfig($currentAppConfig)) {
            $message = $apiKeyUpdateStatusMessage . "Modèle GPT et/ou prompt système (dans app_config.json) mis à jour avec succès.";
            $status = 'success';
        } else {
            $message = $apiKeyUpdateStatusMessage . "Erreur lors de la sauvegarde du modèle GPT et/ou prompt système.";
            // Si la clé API a été mise à jour avec succès mais la sauvegarde de app_config a échoué
            if ($apiKeyUpdateSuccessful) $status = 'warning'; // Ou un autre statut pour indiquer un succès partiel
        }
    } elseif (!empty($apiKeyUpdateStatusMessage)) { // Si seule la clé a été (tentée d'être) mise à jour
         $message = $apiKeyUpdateStatusMessage;
         if ($apiKeyUpdateSuccessful) $status = 'success'; 
    } else {
        $message = "Aucun changement n'a été soumis ou appliqué pour les paramètres OpenAI.";
        $status = 'info'; 
    }
}

// --- Si aucune action ne correspond (ne devrait pas arriver avec des formulaires bien formés) ---
elseif ($action === null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = "Action non spécifiée ou inconnue.";
    // Rediriger vers le dashboard par défaut ou la page d'où vient la requête si possible
    $redirect_page = $_SERVER['HTTP_REFERER'] ?? '../index.php?page=dashboard';
}


// Redirection finale
// Vérifier que $message n'est pas vide avant d'ajouter à l'URL,
// pour éviter un '&message=&status=' si aucune action n'a été traitée et aucun message défini.
if (!empty($message)) {
    $redirect_url = $redirect_page . (strpos($redirect_page, '?') === false ? '?' : '&') . 'message=' . urlencode($message) . '&status=' . $status;
} else {
    $redirect_url = $redirect_page;
}

header('Location: ' . $redirect_url);
exit;
?>