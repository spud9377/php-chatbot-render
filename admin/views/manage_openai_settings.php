<?php
// admin/views/manage_openai_settings.php
if (!isset($appConfigGlobal)) $appConfigGlobal = loadAppConfig();
$current_openai_key_display = OPENAI_API_KEY;
if (strlen($current_openai_key_display) > 10) { // Masquer une partie de la clé pour l'affichage
    $current_openai_key_display = substr($current_openai_key_display, 0, 5) . str_repeat('*', strlen($current_openai_key_display) - 10) . substr($current_openai_key_display, -5);
}
?>
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Paramètres OpenAI</h1>
</div>

<form action="api/admin_actions.php" method="POST">
    <input type="hidden" name="action" value="update_openai_settings">
    
    <div class="row">
        <div class="col-md-8">
             <div class="card mb-3">
                <div class="card-header">Configuration API et Modèle</div>
                <div class="card-body">
                     <div class="mb-3">
                        <label for="openai_api_key" class="form-label">Clé API OpenAI</label>
                        <input type="text" class="form-control" id="openai_api_key" name="openai_api_key" placeholder="Entrez une nouvelle clé pour la changer (actuelle: <?php echo $current_openai_key_display; ?>)">
                        <div class="form-text">Laissez vide pour conserver la clé actuelle définie dans <code>api/config.php</code>. Si vous entrez une nouvelle clé ici, elle sera écrite dans <code>api/config.php</code> (si le fichier est inscriptible par le serveur).</div>
                    </div>
                    <div class="mb-3">
                        <label for="openai_model" class="form-label">Modèle GPT à utiliser</label>
                        <select class="form-select" id="openai_model" name="openai_model" required>
                            <!-- Vous pouvez ajouter plus de modèles ici si besoin -->
                            <option value="gpt-3.5-turbo" <?php echo (($appConfigGlobal['openai_model'] ?? 'gpt-3.5-turbo') === 'gpt-3.5-turbo' ? 'selected' : ''); ?>>gpt-3.5-turbo (Rapide, Économique)</option>
                            <option value="gpt-3.5-turbo-16k" <?php echo (($appConfigGlobal['openai_model'] ?? '') === 'gpt-3.5-turbo-16k' ? 'selected' : ''); ?>>gpt-3.5-turbo-16k</option>
                            <option value="gpt-4" <?php echo (($appConfigGlobal['openai_model'] ?? '') === 'gpt-4' ? 'selected' : ''); ?>>gpt-4 (Plus puissant, Plus cher)</option>
                            <option value="gpt-4-turbo-preview" <?php echo (($appConfigGlobal['openai_model'] ?? '') === 'gpt-4-turbo-preview' ? 'selected' : ''); ?>>gpt-4-turbo-preview</option>
                             <option value="gpt-4o" <?php echo (($appConfigGlobal['openai_model'] ?? '') === 'gpt-4o' ? 'selected' : ''); ?>>gpt-4o (Nouveau, Puissant, Multimodal)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="system_prompt" class="form-label">Prompt Système (Persona de l'IA)</label>
                        <textarea class="form-control" id="system_prompt" name="system_prompt" rows="5" required><?php echo htmlspecialchars($appConfigGlobal['system_prompt'] ?? ''); ?></textarea>
                        <div class="form-text">Ceci définit le comportement de base de l'IA pour toutes les interactions.</div>
                    </div>
                </div>
            </div>
             <button type="submit" class="btn btn-primary">Enregistrer les Paramètres OpenAI</button>
        </div>
    </div>
</form>