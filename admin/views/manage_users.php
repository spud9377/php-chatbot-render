<?php
// admin/views/manage_users.php
if (!isset($usersGlobal)) $usersGlobal = loadUsers(); // S'assurer que c'est chargé
?>
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Gérer les Utilisateurs</h1>
    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="bi bi-plus-circle"></i> Ajouter un Utilisateur
    </button>
</div>

<div class="table-responsive">
    <table class="table table-striped table-sm">
        <thead>
            <tr>
                <th>Nom d'utilisateur</th>
                <th>Nom Complet</th>
                <th>Requêtes Faites</th>
                <th>Quota Max</th>
                <th>Période Quota</th>
                <th>Date Création</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($usersGlobal)): ?>
                <tr><td colspan="7" class="text-center">Aucun utilisateur trouvé.</td></tr>
            <?php else: ?>
                <?php foreach ($usersGlobal as $username => $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($username); ?></td>
                    <td><?php echo htmlspecialchars($user['nom'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($user['requetes_faites'] ?? 0); ?></td>
                    <td><?php echo htmlspecialchars($user['quota_max'] ?? ($appConfigGlobal['default_quota_max'] ?? 50) ); ?></td>
                    <td><?php echo htmlspecialchars($user['quota_period'] ?? ($appConfigGlobal['default_quota_period'] ?? 'daily') ); ?></td>
                    <td><?php echo isset($user['date_creation']) ? htmlspecialchars(date("d/m/Y H:i", strtotime($user['date_creation']))) : 'N/A'; ?></td>
                    <td>
                        <button class="btn btn-primary btn-sm" title="Modifier"
                                data-bs-toggle="modal" data-bs-target="#editUserModal"
                                data-username="<?php echo htmlspecialchars($username); ?>"
                                data-nom="<?php echo htmlspecialchars($user['nom'] ?? ''); ?>"
                                data-quota_max="<?php echo htmlspecialchars($user['quota_max'] ?? ($appConfigGlobal['default_quota_max'] ?? 50)); ?>"
                                data-quota_period="<?php echo htmlspecialchars($user['quota_period'] ?? ($appConfigGlobal['default_quota_period'] ?? 'daily')); ?>">
                            <i class="bi bi-pencil-square"></i>
                        </button>
                        <button class="btn btn-warning btn-sm" title="Réinitialiser Quota"
                                onclick="if(confirm('Réinitialiser le quota de <?php echo htmlspecialchars($username); ?> ?')) { document.getElementById('resetUserQuotaForm_<?php echo htmlspecialchars(str_replace('.', '_', $username)); ?>').submit(); }">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </button>
                        <form id="resetUserQuotaForm_<?php echo htmlspecialchars(str_replace('.', '_', $username)); ?>" action="api/admin_actions.php" method="POST" style="display:none;">
                            <input type="hidden" name="action" value="reset_user_quota">
                            <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">
                        </form>

                        <button class="btn btn-danger btn-sm" title="Supprimer"
                                onclick="if(confirm('Voulez-vous vraiment supprimer l\'utilisateur <?php echo htmlspecialchars($username); ?> ? Cette action est irréversible.')) { document.getElementById('deleteUserForm_<?php echo htmlspecialchars(str_replace('.', '_', $username)); ?>').submit(); }">
                            <i class="bi bi-trash3-fill"></i>
                        </button>
                        <form id="deleteUserForm_<?php echo htmlspecialchars(str_replace('.', '_', $username)); ?>" action="api/admin_actions.php" method="POST" style="display:none;">
                            <input type="hidden" name="action" value="delete_user">
                            <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal Ajouter Utilisateur -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="api/admin_actions.php" method="POST">
                <input type="hidden" name="action" value="add_user">
                <div class="modal-header">
                    <h5 class="modal-title" id="addUserModalLabel">Ajouter un Nouvel Utilisateur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="add_username" class="form-label">Nom d'utilisateur</label>
                        <input type="text" class="form-control" id="add_username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="add_nom" class="form-label">Nom Complet</label>
                        <input type="text" class="form-control" id="add_nom" name="nom" required>
                    </div>
                    <div class="mb-3">
                        <label for="add_password" class="form-label">Mot de passe</label>
                        <input type="password" class="form-control" id="add_password" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label for="add_quota_max" class="form-label">Quota Maximum</label>
                        <input type="number" class="form-control" id="add_quota_max" name="quota_max" value="<?php echo htmlspecialchars($appConfigGlobal['default_quota_max'] ?? 50); ?>" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label for="add_quota_period" class="form-label">Période Quota</label>
                        <select class="form-select" id="add_quota_period" name="quota_period" required>
                            <option value="none" <?php echo (($appConfigGlobal['default_quota_period'] ?? 'daily') === 'none' ? 'selected' : ''); ?>>Jamais</option>
                            <option value="daily" <?php echo (($appConfigGlobal['default_quota_period'] ?? 'daily') === 'daily' ? 'selected' : ''); ?>>Journalier</option>
                            <option value="weekly" <?php echo (($appConfigGlobal['default_quota_period'] ?? 'daily') === 'weekly' ? 'selected' : ''); ?>>Hebdomadaire</option>
                            <option value="monthly" <?php echo (($appConfigGlobal['default_quota_period'] ?? 'daily') === 'monthly' ? 'selected' : ''); ?>>Mensuel</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Ajouter Utilisateur</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Modifier Utilisateur -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="api/admin_actions.php" method="POST">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" id="edit_username_hidden" name="username">
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">Modifier Utilisateur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nom d'utilisateur</label>
                        <input type="text" class="form-control" id="edit_username_display" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="edit_nom" class="form-label">Nom Complet</label>
                        <input type="text" class="form-control" id="edit_nom" name="nom" required>
                    </div>
                     <div class="mb-3">
                        <label for="edit_password" class="form-label">Nouveau Mot de passe (laisser vide pour ne pas changer)</label>
                        <input type="password" class="form-control" id="edit_password" name="password">
                    </div>
                    <div class="mb-3">
                        <label for="edit_quota_max" class="form-label">Quota Maximum</label>
                        <input type="number" class="form-control" id="edit_quota_max" name="quota_max" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_quota_period" class="form-label">Période Quota</label>
                        <select class="form-select" id="edit_quota_period" name="quota_period" required>
                            <option value="none">Jamais</option>
                            <option value="daily">Journalier</option>
                            <option value="weekly">Hebdomadaire</option>
                            <option value="monthly">Mensuel</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer Modifications</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
// Script pour pré-remplir le modal de modification
var editUserModal = document.getElementById('editUserModal');
editUserModal.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    var username = button.getAttribute('data-username');
    var nom = button.getAttribute('data-nom');
    var quotaMax = button.getAttribute('data-quota_max');
    var quotaPeriod = button.getAttribute('data-quota_period');

    var modalTitle = editUserModal.querySelector('.modal-title');
    var usernameHiddenInput = editUserModal.querySelector('#edit_username_hidden');
    var usernameDisplayInput = editUserModal.querySelector('#edit_username_display');
    var nomInput = editUserModal.querySelector('#edit_nom');
    var quotaMaxInput = editUserModal.querySelector('#edit_quota_max');
    var quotaPeriodSelect = editUserModal.querySelector('#edit_quota_period');
    
    modalTitle.textContent = 'Modifier Utilisateur : ' + username;
    usernameHiddenInput.value = username;
    usernameDisplayInput.value = username;
    nomInput.value = nom;
    quotaMaxInput.value = quotaMax;
    quotaPeriodSelect.value = quotaPeriod;
});
</script>