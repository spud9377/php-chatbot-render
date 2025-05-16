<?php
// admin/views/manage_app_settings.php
if (!isset($appConfigGlobal)) $appConfigGlobal = loadAppConfig(); 
?>
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Paramètres Généraux de l'Application</h1>
</div>

<form action="api/admin_actions.php" method="POST">
    <input type="hidden" name="action" value="update_app_settings">
    
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header">Configuration des Inscriptions et Quotas</div>
                <div class="card-body">
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="allow_registration" name="allow_registration" value="1" <?php echo ($appConfigGlobal['allow_registration'] ?? false) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="allow_registration">Autoriser les nouvelles inscriptions</label>
                    </div>

                    <div class="mb-3">
                        <label for="default_quota_max" class="form-label">Quota maximum par défaut (par nouvel utilisateur)</label>
                        <input type="number" class="form-control" id="default_quota_max" name="default_quota_max" value="<?php echo htmlspecialchars($appConfigGlobal['default_quota_max'] ?? 50); ?>" min="0" required>
                    </div>

                    <div class="mb-3">
                        <label for="default_quota_period" class="form-label">Période de réinitialisation du quota par défaut</label>
                        <select class="form-select" id="default_quota_period" name="default_quota_period" required>
                            <option value="none" <?php echo (($appConfigGlobal['default_quota_period'] ?? 'daily') === 'none' ? 'selected' : ''); ?>>Jamais (Quota unique)</option>
                            <option value="daily" <?php echo (($appConfigGlobal['default_quota_period'] ?? 'daily') === 'daily' ? 'selected' : ''); ?>>Journalier</option>
                            <option value="weekly" <?php echo (($appConfigGlobal['default_quota_period'] ?? 'daily') === 'weekly' ? 'selected' : ''); ?>>Hebdomadaire</option>
                            <option value="monthly" <?php echo (($appConfigGlobal['default_quota_period'] ?? 'daily') === 'monthly' ? 'selected' : ''); ?>>Mensuel</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header">Codes d'Accès Simulation Pédagogique</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="sim_code_etudiant" class="form-label">Code Accès Étudiant</label>
                        <input type="text" class="form-control" id="sim_code_etudiant" name="simulation_codes[etudiant]" value="<?php echo htmlspecialchars($appConfigGlobal['simulation_codes']['etudiant'] ?? 'eto'); ?>" required>
                        <div class="form-text">Code que les étudiants doivent entrer pour accéder à la simulation.</div>
                    </div>
                    <div class="mb-3">
                        <label for="sim_code_jury" class="form-label">Code Accès Jury</label>
                        <input type="text" class="form-control" id="sim_code_jury" name="simulation_codes[jury]" value="<?php echo htmlspecialchars($appConfigGlobal['simulation_codes']['jury'] ?? 'eto'); ?>" required>
                        <div class="form-text">Code que le jury doit entrer pour accéder aux fonctionnalités jury.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <button type="submit" class="btn btn-primary mt-3">Enregistrer Tous les Paramètres</button>
</form>