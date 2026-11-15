<?php if(user_can('edit_settings')): ?>
<style>
    /* Modern Styling Guidelines */
    .branch-card {
        border: 1px solid rgba(148, 163, 184, 0.15);
        border-radius: 1rem;
        background: #ffffff;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
        overflow: hidden;
        transition: all 0.2s ease-in-out;
    }

    [data-bs-theme='dark'] .branch-card {
        background: rgba(15, 23, 42, 0.7);
        border-color: rgba(255, 255, 255, 0.08);
        backdrop-filter: blur(12px);
    }

    .branch-header {
        padding: 1.5rem;
        background: transparent;
        border-bottom: 1px solid rgba(148, 163, 184, 0.1);
    }

    .branch-title {
        font-weight: 700;
        font-size: 1.15rem;
        letter-spacing: -0.01em;
        margin-bottom: 0.25rem;
    }

    .branch-subtitle {
        font-size: 0.875rem;
        color: #64748b;
    }

    /* Table Styling */
    .branch-table {
        margin-bottom: 0;
        vertical-align: middle;
    }

    .branch-table thead th {
        background: rgba(248, 250, 252, 0.8);
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        font-weight: 700;
        color: #64748b;
        padding: 0.875rem 1.25rem;
        border-bottom: 1px solid rgba(148, 163, 184, 0.1);
    }

    [data-bs-theme='dark'] .branch-table thead th {
        background: rgba(30, 41, 59, 0.5);
        color: #94a3b8;
    }

    .branch-table tbody td {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid rgba(148, 163, 184, 0.08);
        font-size: 0.9rem;
    }

    .branch-table tbody tr {
        transition: background-color 0.15s ease;
    }

    .branch-table tbody tr:hover {
        background-color: rgba(59, 130, 246, 0.03);
    }

    /* Soft Badges */
    .badge-soft-success {
        background-color: rgba(34, 197, 94, 0.12);
        color: #16a34a;
        font-weight: 600;
        padding: 0.35em 0.75em;
        border-radius: 2rem;
        font-size: 0.75rem;
    }

    .badge-soft-secondary {
        background-color: rgba(148, 163, 184, 0.15);
        color: #64748b;
        font-weight: 600;
        padding: 0.35em 0.75em;
        border-radius: 2rem;
        font-size: 0.75rem;
    }

    /* Modern Buttons */
    .btn-gradient-primary {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        color: #fff;
        border: none;
        font-weight: 600;
        padding: 0.5rem 1.25rem;
        border-radius: 0.5rem;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        transition: all 0.2s ease;
    }

    .btn-gradient-primary:hover {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: #fff;
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(37, 99, 235, 0.3);
    }

    .btn-action-edit {
        border-radius: 0.5rem;
        font-weight: 600;
        font-size: 0.8rem;
        padding: 0.35rem 0.85rem;
        transition: all 0.15s ease;
    }

    /* Code Pill */
    .code-pill {
        font-family: SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 0.825rem;
        background: rgba(148, 163, 184, 0.1);
        padding: 0.2rem 0.5rem;
        border-radius: 0.375rem;
    }

    /* Modern Modal Styling */
    .modern-modal .modal-content {
        border: 1px solid rgba(148, 163, 184, 0.2);
        border-radius: 1.25rem;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        overflow: hidden;
    }

    .modern-modal .modal-header {
        border-bottom: 1px solid rgba(148, 163, 184, 0.1);
        padding: 1.25rem 1.5rem;
    }

    .modern-modal .modal-footer {
        border-top: 1px solid rgba(148, 163, 184, 0.1);
        padding: 1rem 1.5rem;
    }

    .modern-modal .form-control:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
    }
</style>

<div class="settings-shell mx-auto">
    <div class="card branch-card">
        <div class="branch-header d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="branch-title">Branch Management</div>
                <div class="branch-subtitle">Create and manage operational branches. Active branches appear in Users Manager.</div>
            </div>
            <button class="btn btn-gradient-primary d-inline-flex align-items-center gap-2" type="button" id="addBranchBtn">
                <i class="fa-solid fa-plus"></i> Add Branch
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table branch-table align-middle">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 60px;">#</th>
                            <th>Branch Name</th>
                            <th>Code</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $branches = admin_settings_fetch_branches(['include_deleted' => false]); ?>
                        <?php if(!empty($branches)): ?>
                            <?php foreach($branches as $index => $branch): ?>
                                <tr>
                                    <td class="text-center text-muted fw-semibold"><?=$index + 1?></td>
                                    <td class="fw-semibold text-body"><?=esc($branch->name ?? '')?></td>
                                    <td>
                                        <?php if(!empty($branch->code)): ?>
                                            <span class="code-pill text-muted"><?=esc($branch->code)?></span>
                                        <?php else: ?>
                                            <span class="text-muted small">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if((int)($branch->active ?? 0) === 1): ?>
                                            <span class="badge badge-soft-success d-inline-flex align-items-center gap-1">
                                                <i class="fa-solid fa-circle-check fs-6"></i> Active
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-soft-secondary d-inline-flex align-items-center gap-1">
                                                <i class="fa-solid fa-circle-minus fs-6"></i> Inactive
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-primary btn-action-edit edit-branch-btn" data-branch='<?=json_encode($branch, JSON_HEX_APOS|JSON_HEX_QUOT)?>'>
                                            <i class="fa-solid fa-pen-to-square me-1"></i> Edit
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-5">
                                    <i class="fa-solid fa-code-branch fs-3 d-block mb-2 text-secondary opacity-50"></i>
                                    No branches configured yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modern Branch Modal -->
<div class="modal fade modern-modal" id="branchModal" tabindex="-1" aria-labelledby="branchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="branchModalLabel">Add Branch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="branchForm" method="post">
                <?=csrf()?>
                <div class="modal-body p-4">
                    <input type="hidden" name="id" id="branch_id" value="">
                    <div class="mb-3">
                        <label for="branch_name" class="form-label text-muted small fw-semibold text-uppercase">Branch Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-lg fs-6" name="name" id="branch_name" placeholder="e.g. Head Office / Accra North" required>
                        <div class="invalid-feedback" id="branch_name_error"></div>
                    </div>
                    <div class="mb-3">
                        <label for="branch_code" class="form-label text-muted small fw-semibold text-uppercase">Branch Code</label>
                        <input type="text" class="form-control form-control-lg fs-6" name="code" id="branch_code" placeholder="e.g. ACC-01">
                    </div>
                    <div class="form-check form-switch mt-4">
                        <input class="form-check-input" type="checkbox" role="switch" name="active" id="branch_active" value="1" checked>
                        <label class="form-check-label fw-semibold" for="branch_active">Active Branch</label>
                        <div class="form-text text-muted small">Active branches can be assigned to system users.</div>
                    </div>
                </div>
                <div class="modal-footer bg-light bg-opacity-50">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-gradient-primary px-4">Save Branch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const branchModal = new bootstrap.Modal(document.getElementById('branchModal'));
    const branchForm = document.getElementById('branchForm');
    const branchModalLabel = document.getElementById('branchModalLabel');
    const branchIdInput = document.getElementById('branch_id');
    const branchNameInput = document.getElementById('branch_name');
    const branchCodeInput = document.getElementById('branch_code');
    const branchActiveInput = document.getElementById('branch_active');
    const branchNameError = document.getElementById('branch_name_error');

    document.getElementById('addBranchBtn').addEventListener('click', function () {
        branchModalLabel.textContent = 'Add Branch';
        branchIdInput.value = '';
        branchNameInput.value = '';
        branchCodeInput.value = '';
        branchActiveInput.checked = true;
        branchNameInput.classList.remove('is-invalid');
        branchNameError.textContent = '';
        branchModal.show();
    });

    document.querySelectorAll('.edit-branch-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            const branch = JSON.parse(this.getAttribute('data-branch'));
            branchModalLabel.textContent = 'Edit Branch';
            branchIdInput.value = branch.id || '';
            branchNameInput.value = branch.name || '';
            branchCodeInput.value = branch.code || '';
            branchActiveInput.checked = parseInt(branch.active || 0, 10) === 1;
            branchNameInput.classList.remove('is-invalid');
            branchNameError.textContent = '';
            branchModal.show();
        });
    });

    branchForm.addEventListener('submit', function (event) {
        event.preventDefault();

        const data = new FormData(branchForm);
        const url = '<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/branches';

        fetch(url, {
            method: 'POST',
            body: data,
        })
        .then(res => res.json())
        .then(json => {
            if (json.success) {
                window.location.reload();
                return;
            }
            if (json.errors && json.errors.name) {
                branchNameError.textContent = json.errors.name;
                branchNameInput.classList.add('is-invalid');
            }
        })
        .catch(() => {
            branchNameError.textContent = 'Unable to save branch. Please try again.';
            branchNameInput.classList.add('is-invalid');
        });
    });
});
</script>
<?php endif; ?>