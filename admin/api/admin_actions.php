<?php
// admin/api/admin_actions.php
require_once __DIR__ . '/../../api/config.php'; // Chemin ajusté pour remonter de deux niveaux

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Si pas admin, rediriger ou renvoyer une erreur.
    // Pour une API, il serait mieux de renvoyer un JSON d'erreur.
    // Mais comme ce sont des soumissions de formulaire, on redirige avec un message.
    header('Location: ../login.php?message=' . urlencode("Accès non autorisé.") . '&status=error');
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? null;
$redirect_page = '../index.php?page=dashboard'; // Page de redirection par défaut
$message = '';
$status = 'error'; // 'success' or 'error'

// --- Fonctions de gestion de la clé API dans config.php ---
function update_openai_api_key_in_config($new_key) {
    $config_file_path = __DIR__ . '/../../api/config.php';
    if (!is_writable($config_file_path)) {
        return "Le fichier de configuration principal (api/config.php) n'est pas inscriptible. Veuillez mettre à jour la clé API manuellement.";
    }
    $config_content = file_get_contents($config_file_path);
    if ($config_content === false) {
        return "Impossible de lire le fichier de configuration principal.";
    }

    // Remplacer la ligne define('OPENAI_API_KEY', '...');
    // Attention : cette regex est simple et suppose un format spécifique.
    $pattern = "/define\('OPENAI_API_KEY', '.*?'\);/";
    $replacement = "define('OPENAI_API_KEY', '" . addslashes($new_key) . "');";
    $new_config_content = preg_replace($pattern, $replacement, $config_content, 1, $count);

    if ($count > 0) {
        if (file_put_contents($config_file_path, $new_config_content) !== false) {
            return true; // Succès
        } else {
            return "Impossible d'écrire dans le fichier de configuration principal après modification.";
        }
    } else {
        return "La constante OPENAI_API_KEY n'a pas été trouvée ou le format est inattendu dans api/config.php.";
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
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $message = 'Le nom d\'utilisateur ne peut contenir que des lettres, chiffres et underscores (_).';
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
    $username = trim($_POST['username'] ?? ''); // C'est le username original (clé)
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
                     // Ne pas enregistrer si le mot de passe est invalide mais que d'autres champs le sont
                } else {
                    $users[$username]['password_hash'] = password_hash($new_password, PASSWORD_DEFAULT);
                }
            }
            // Ne pas réinitialiser requetes_faites ici, sauf si c'est une action explicite
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
    } elseif ($username === ADMIN_USERNAME) { // Sécurité: ne pas supprimer l'admin par cette voie
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
            $users[$username]['quota_reset_timestamp'] = time(); // Marquer le moment du reset manuel
            if (saveUsers($users)) {
                $message = "Quota de l'utilisateur '$username' réinitialisé avec succès.";
                $status = 'success';
            } else {
                $message = "Erreur lors de la réinitialisation du quota pour '$username'.";
            }
        }
    }
}

// --- Gestion des paramètres de l'application ---
elseif ($action === 'update_app_settings') {
    $redirect_page = '../index.php?page=settings';
    $currentConfig = loadAppConfig();

    $currentConfig['allow_registration'] = isset($_POST['allow_registration']); // Checkbox value is 1 if checked
    $currentConfig['default_quota_max'] = (int)($_POST['default_quota_max'] ?? 50);
    $currentConfig['default_quota_period'] = $_POST['default_quota_period'] ?? 'daily';
    
    if (saveAppConfig($currentConfig)) {
        $message = "Paramètres généraux mis à jour avec succès.";
        $status = 'success';
    } else {
        $message = "Erreur lors de la sauvegarde des paramètres généraux.";
    }
}

// --- Gestion des paramètres OpenAI ---
elseif ($action === 'update_openai_settings') {
    $redirect_page = '../index.php?page=openai_settings';
    $currentConfig = loadAppConfig(); // Charger app_config.json
    $openaiConfigUpdated = false;
    $apiKeyUpdateStatusMessage = '';

    // Mettre à jour la clé API dans api/config.php si fournie
    if (!empty($_POST['openai_api_key'])) {
        $new_key = trim($_POST['openai_api_key']);
        if (strpos($new_key, '*') === false) { // Ne pas essayer d'écrire si c'est la clé masquée
            $update_result = update_openai_api_key_in_config($new_key);
            if ($update_result === true) {
                $apiKeyUpdateStatusMessage = "Clé API OpenAI mise à jour dans le fichier de configuration. ";
                $openaiConfigUpdated = true;
            } else {
                $apiKeyUpdateStatusMessage = "Erreur lors de la mise à jour de la clé API : " . htmlspecialchars($update_result) . ". ";
            }
        }
    }
    
    // Mettre à jour le modèle et le prompt système dans app_config.json
    $modelChanged = false;
    if (isset($_POST['openai_model']) && $_POST['openai_model'] !== ($currentConfig['openai_model'] ?? '')) {
        $currentConfig['openai_model'] = $_POST['openai_model'];
        $modelChanged = true;
    }
    if (isset($_POST['system_prompt']) && $_POST['system_prompt'] !== ($currentConfig['system_prompt'] ?? '')) {
        $currentConfig['system_prompt'] = $_POST['system_prompt'];
        $modelChanged = true;
    }

    if ($modelChanged) {
        if (saveAppConfig($currentConfig)) {
            $message = $apiKeyUpdateStatusMessage . "Modèle GPT et/ou prompt système mis à jour avec succès.";
            $status = 'success';
        } else {
            $message = $apiKeyUpdateStatusMessage . "Erreur lors de la sauvegarde du modèle GPT et/ou prompt système.";
        }
    } elseif (!empty($apiKeyUpdateStatusMessage)) { // Si seule la clé a été (tentée d'être) mise à jour
         $message = $apiKeyUpdateStatusMessage;
         if ($openaiConfigUpdated) $status = 'success'; // Si la mise à jour de la clé a réussi
    } else {
        $message = "Aucun changement détecté pour les paramètres OpenAI.";
        $status = 'info'; // Pas une erreur, juste pas de changement
    }

}


// Redirection finale
header('Location: ' . $redirect_page . '&message=' . urlencode($message) . '&status=' . $status);
exit;

?>