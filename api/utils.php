<?php
// api/utils.php

if (!defined('USER_DATA_FILE')) {
    // Ceci est une protection pour s'assurer que config.php a été inclus avant utils.php
    // ou que les constantes nécessaires sont définies.
    // Pour l'instant, on va supposer que config.php est toujours inclus avant.
    // define('USER_DATA_FILE', __DIR__ . '/../data/users.json'); // Exemple de fallback
    // define('APP_CONFIG_FILE', __DIR__ . '/../data/app_config.json');
}


function loadUsers() {
    if (!file_exists(USER_DATA_FILE)) {
        if (!is_dir(dirname(USER_DATA_FILE))) {
             mkdir(dirname(USER_DATA_FILE), 0755, true);
        }
        file_put_contents(USER_DATA_FILE, json_encode([]));
        return [];
    }
    $jsonData = file_get_contents(USER_DATA_FILE);
    $users = json_decode($jsonData, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("UTILS: Erreur de décodage JSON dans loadUsers: " . json_last_error_msg() . " Fichier: " . USER_DATA_FILE);
        return [];
    }
    return $users ?? [];
}

function saveUsers($users) {
    $dir = dirname(USER_DATA_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $fp = fopen(USER_DATA_FILE, 'w');
    if (!$fp) {
        error_log("UTILS: Impossible d'ouvrir le fichier users.json en écriture.");
        return false;
    }
    if (flock($fp, LOCK_EX)) {
        fwrite($fp, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        fflush($fp);
        flock($fp, LOCK_UN);
    } else {
        error_log("UTILS: Impossible d'obtenir un verrou sur users.json.");
        fclose($fp);
        return false;
    }
    fclose($fp);
    return true;
}

function loadAppConfig() {
    if (!file_exists(APP_CONFIG_FILE)) {
        // Créer une configuration par défaut si le fichier n'existe pas
        $defaultConfig = [
            'openai_model' => 'gpt-3.5-turbo',
            'default_quota_max' => 50,
            'default_quota_period' => 'daily', // 'daily', 'weekly', 'monthly', 'none'
            'allow_registration' => true,
            'system_prompt' => "Tu es un assistant pédagogique IA. Ton rôle est d'aider les étudiants à comprendre des concepts, à réviser leurs cours et à répondre à leurs questions de manière claire et concise. Sois patient et encourageant."
        ];
        if (!is_dir(dirname(APP_CONFIG_FILE))) {
             mkdir(dirname(APP_CONFIG_FILE), 0755, true);
        }
        file_put_contents(APP_CONFIG_FILE, json_encode($defaultConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $defaultConfig;
    }
    $jsonData = file_get_contents(APP_CONFIG_FILE);
    $config = json_decode($jsonData, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("UTILS: Erreur de décodage JSON dans loadAppConfig: " . json_last_error_msg() . " Fichier: " . APP_CONFIG_FILE);
        // Retourner une config par défaut en cas d'erreur pour éviter de casser l'app
         return [
            'openai_model' => 'gpt-3.5-turbo', 'default_quota_max' => 50, 'default_quota_period' => 'daily', 'allow_registration' => true,
            'system_prompt' => "Erreur de config. Assistant par défaut."
        ];
    }
    return $config ?? [];
}

function saveAppConfig($config) {
    $dir = dirname(APP_CONFIG_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $fp = fopen(APP_CONFIG_FILE, 'w');
    if (!$fp) {
        error_log("UTILS: Impossible d'ouvrir le fichier app_config.json en écriture.");
        return false;
    }
    if (flock($fp, LOCK_EX)) {
        fwrite($fp, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        fflush($fp);
        flock($fp, LOCK_UN);
    } else {
        error_log("UTILS: Impossible d'obtenir un verrou sur app_config.json.");
        fclose($fp);
        return false;
    }
    fclose($fp);
    return true;
}

function resetQuotaIfNeeded(&$user_ref, $appConfig) {
    $quotaPeriod = $user_ref['quota_period'] ?? $appConfig['default_quota_period'] ?? 'daily';
    if ($quotaPeriod === 'none') return false;

    $now = time();
    $resetPeriodSeconds = 0;
    switch ($quotaPeriod) {
        case 'daily': $resetPeriodSeconds = 24 * 60 * 60; break;
        case 'weekly': $resetPeriodSeconds = 7 * 24 * 60 * 60; break;
        case 'monthly': $resetPeriodSeconds = 30 * 24 * 60 * 60; break; // Approx.
    }

    if (!isset($user_ref['quota_reset_timestamp'])) $user_ref['quota_reset_timestamp'] = 0;
    if (!isset($user_ref['requetes_faites'])) $user_ref['requetes_faites'] = 0;

    if ($resetPeriodSeconds > 0 && ($now - $user_ref['quota_reset_timestamp']) > $resetPeriodSeconds) {
        $user_ref['requetes_faites'] = 0;
        $user_ref['quota_reset_timestamp'] = $now;
        return true;
    }
    return false;
}

// Fonction pour récupérer l'utilisateur par username
function getUserByUsername($username) {
    $users = loadUsers();
    return $users[$username] ?? null;
}

?>