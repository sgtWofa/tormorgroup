<?php
$branches = admin_settings_fetch_branches(['include_deleted' => false]);
?>
<div class="tab-pane fade" id="tab-branches" role="tabpanel" aria-labelledby="tab-branches-btn" tabindex="0">
    <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="mb-0">Branches</h5>
                <p class="text-muted mb-0">Create new branches and control whether they are active for user assignment.</p>
            </div>
            <button type="button" class="btn btn-primary" id="settingsAddBranchBtn">Add Branch</button>
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(!empty($branches)): ?>
                        <?php foreach($branches as $index => $branch): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= esc($branch->name ?? '') ?></td>
                                <td><?= esc($branch->code ?? '') ?></td>
                                <td>
                                    <?php if((int)($branch->active ?? 0) === 1): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary settings-edit-branch-btn" data-branch='<?= json_encode($branch, JSON_HEX_APOS|JSON_HEX_QUOT) ?>'>Edit</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">No branches found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="settingsBranchModal" tabindex="-1" aria-labelledby="settingsBranchModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="settingsBranchModalLabel">Add Branch</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="settingsBranchForm" method="post">
                    <?= csrf() ?>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="settings_branch_id" value="">
                        <div class="mb-3">
                            <label for="settings_branch_name" class="form-label">Branch Name</label>
                            <input type="text" class="form-control" name="name" id="settings_branch_name" required>
                            <div class="invalid-feedback" id="settings_branch_name_error"></div>
                        </div>
                        <div class="mb-3">
                            <label for="settings_branch_code" class="form-label">Branch Code</label>
                            <input type="text" class="form-control" name="code" id="settings_branch_code">
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="active" id="settings_branch_active" value="1" checked>
                            <label class="form-check-label" for="settings_branch_active">Active</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Branch</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    const branchModalEl = document.getElementById('settingsBranchModal');
    if (!branchModalEl) return;
    const branchModal = new bootstrap.Modal(branchModalEl);
    const branchForm = document.getElementById('settingsBranchForm');
    const branchIdInput = document.getElementById('settings_branch_id');
    const branchNameInput = document.getElementById('settings_branch_name');
    const branchCodeInput = document.getElementById('settings_branch_code');
    const branchActiveInput = document.getElementById('settings_branch_active');
    const branchNameError = document.getElementById('settings_branch_name_error');
    const branchModalLabel = document.getElementById('settingsBranchModalLabel');

    const resetBranchForm = () => {
        branchIdInput.value = '';
        branchNameInput.value = '';
        branchCodeInput.value = '';
        branchActiveInput.checked = true;
        branchNameInput.classList.remove('is-invalid');
        branchNameError.textContent = '';
        branchModalLabel.textContent = 'Add Branch';
    };

    document.getElementById('settingsAddBranchBtn').addEventListener('click', function(){
        resetBranchForm();
        branchModal.show();
    });

    document.querySelectorAll('.settings-edit-branch-btn').forEach(function(button){
        button.addEventListener('click', function(){
            const branch = JSON.parse(this.getAttribute('data-branch'));
            branchIdInput.value = branch.id || '';
            branchNameInput.value = branch.name || '';
            branchCodeInput.value = branch.code || '';
            branchActiveInput.checked = parseInt(branch.active || 0, 10) === 1;
            branchNameInput.classList.remove('is-invalid');
            branchNameError.textContent = '';
            branchModalLabel.textContent = 'Edit Branch';
            branchModal.show();
        });
    });

    branchForm.addEventListener('submit', function(event){
        event.preventDefault();
        const data = new FormData(branchForm);
        const url = '<?= ROOT ?>/<?= $admin_route ?>/<?= $plugin_route ?>/branches';

        fetch(url, {
            method: 'POST',
            body: data,
        })
        .then(response => response.json())
        .then(json => {
            if(json.success){
                window.location.reload();
                return;
            }
            if(json.errors && json.errors.name){
                branchNameInput.classList.add('is-invalid');
                branchNameError.textContent = json.errors.name;
            }
        })
        .catch(() => {
            branchNameInput.classList.add('is-invalid');
            branchNameError.textContent = 'Unable to save branch. Please try again.';
        });
    });
})();
</script>
