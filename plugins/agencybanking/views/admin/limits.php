<?php if(user_can('is_admin')): ?>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">Agency Banking Transaction Limits</h4>
            <p class="text-muted mb-0">Set maximum amounts by transaction type and bank network.</p>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>"><i class="fa fa-arrow-left me-1"></i>Back</a>
            <button class="btn btn-primary" type="button" id="agencyLimitAdd" data-bs-toggle="modal" data-bs-target="#agencyLimitModal"><i class="fa fa-plus me-1"></i>Add limit</button>
        </div>
    </div>

    <div class="modal fade" id="agencyLimitModal" tabindex="-1" aria-labelledby="agencyLimitModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="agencyLimitModalLabel">Add transaction limit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" id="agencyLimitForm">
                    <div class="modal-body">
                <?=csrf()?>
                <input type="hidden" name="id" value="0">
                <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Transaction type</label>
                    <select name="transaction_type" class="form-select" id="agencyLimitTransactionType" required>
                        <option value="">Select type</option>
                        <option value="deposit">Deposit</option>
                        <option value="withdrawal">Withdrawal</option>
                        <option value="direct_transfer">Direct transfer</option>
                        <option value="interbank">Interbank</option>
                        <option value="account_creation">Account creation</option>
                    </select>
                </div>
                <div class="col-md-6 d-none" id="agencyWithdrawalTypeWrap">
                    <label class="form-label">Withdrawal type</label>
                    <select name="withdrawal_type" class="form-select" id="agencyWithdrawalType">
                        <option value="">Select withdrawal type</option>
                        <option value="cit">Withdrawal CIT</option>
                        <option value="ait">Withdrawal AIT</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Bank network</label>
                    <input name="network" class="form-control" placeholder="GCB, Fidelity Bank, ABSA" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Maximum amount (GHS)</label>
                    <input name="max_amount" type="number" min="0.01" step="0.01" inputmode="decimal" class="form-control js-limit-amount" required>
                </div>
                <div class="col-12">
                    <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="active" value="1" checked id="agencyLimitActive">
                    <label class="form-check-label" for="agencyLimitActive">Active</label>
                    </div>
                </div>
                </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-primary" type="submit"><i class="fa fa-save me-1"></i>Save limit</button>
                    </div>
                </form>
                <div id="agencyLimitMessage" class="alert d-none mx-3 mb-3"></div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="row g-3 mb-3 align-items-end">
                <div class="col-12 col-lg-6"><label class="form-label small text-muted mb-1" for="agencyLimitSearch">Search</label><input id="agencyLimitSearch" class="form-control" placeholder="Transaction type or bank network"></div>
                <div class="col-12 col-sm-6 col-lg-3"><label class="form-label small text-muted mb-1" for="agencyLimitTypeFilter">Transaction type</label><select id="agencyLimitTypeFilter" class="form-select"><option value="">All transaction types</option><option value="deposit">Deposit</option><option value="withdrawal">Withdrawal</option><option value="direct_transfer">Direct transfer</option><option value="interbank">Interbank</option><option value="account_creation">Account creation</option></select></div>
                <div class="col-12 col-sm-6 col-lg-2"><label class="form-label small text-muted mb-1" for="agencyLimitStatusFilter">Status</label><select id="agencyLimitStatusFilter" class="form-select"><option value="">All statuses</option><option value="1">Active</option><option value="0">Inactive</option></select></div>
                <div class="col-12 col-lg-1"><button type="button" id="agencyLimitReset" class="btn btn-outline-secondary w-100">Reset</button></div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-nowrap">
                    <thead><tr><th>Transaction type</th><th>Bank network</th><th class="text-end">Maximum</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                    <tbody id="agencyLimitsBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
(function(){
    const endpoint = '<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/limits-api';
    const form = document.getElementById('agencyLimitForm');
    const body = document.getElementById('agencyLimitsBody');
    const message = document.getElementById('agencyLimitMessage');
    const modalElement = document.getElementById('agencyLimitModal');
    const modalTitle = document.getElementById('agencyLimitModalLabel');
    const modal = modalElement && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(modalElement) : null;
    const rows = new Map();
    let allRows = [];
    let pendingSaves = 0;
    form.noValidate = true;
    function esc(value){ return String(value ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c])); }
    function notify(text, error){ message.textContent = text; message.className = 'alert mt-3 mb-0 ' + (error ? 'alert-danger' : 'alert-success'); }
    function render(){
        const search = document.getElementById('agencyLimitSearch').value.trim().toLowerCase();
        const type = document.getElementById('agencyLimitTypeFilter').value;
        const status = document.getElementById('agencyLimitStatusFilter').value;
        rows.clear();
        const filtered = allRows.filter(row => {
            const text = (row.transaction_type + ' ' + row.network).toLowerCase();
            return (!search || text.includes(search)) && (!type || row.transaction_type === type) && (!status || String(Number(row.active)) === status);
        });
        filtered.forEach(row => rows.set(String(row.id), row));
        body.innerHTML = filtered.length ? filtered.map(row => '<tr><td>' + esc(row.transaction_type) + (row.withdrawal_type ? ' (' + esc(String(row.withdrawal_type).toUpperCase()) + ')' : '') + '</td><td>' + esc(String(row.network).toUpperCase()) + '</td><td>GHS ' + Number(row.max_amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td><td><span class="badge ' + (Number(row.active) ? 'bg-success' : 'bg-secondary') + '">' + (Number(row.active) ? 'Active' : 'Inactive') + '</span></td><td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary js-agency-edit" data-bs-toggle="modal" data-bs-target="#agencyLimitModal" data-id="' + esc(row.id) + '" data-type="' + esc(row.transaction_type) + '" data-withdrawal-type="' + esc(row.withdrawal_type || '') + '" data-network="' + esc(row.network) + '" data-amount="' + esc(row.max_amount) + '" data-active="' + esc(row.active) + '"><i class="fa fa-pen"></i></button> <button type="button" class="btn btn-sm btn-outline-danger js-agency-delete" data-id="' + esc(row.id) + '"><i class="fa fa-trash"></i></button></td></tr>').join('') : '<tr><td colspan="5" class="text-center text-muted py-4">No limits configured.</td></tr>';
    }
    async function load(){
        const response = await fetch(endpoint + '?action=list&_=' + Date.now(), {credentials:'include', cache:'no-store', headers:{Accept:'application/json'}});
        const payload = await response.json();
        if(!response.ok || !payload.success) throw new Error(payload.message || 'Unable to load limits.');
        allRows = payload.rows || []; render();
    }
    function reset(){
        form.querySelector('[name="id"]').value = '0';
        form.querySelector('[name="transaction_type"]').value = '';
        form.querySelector('[name="withdrawal_type"]').value = '';
        form.querySelector('[name="network"]').value = '';
        form.querySelector('[name="max_amount"]').value = '';
        form.querySelector('[name="active"]').checked = true;
        form.querySelector('button[type="submit"]').innerHTML = '<i class="fa fa-save me-1"></i>Save limit';
        modalTitle.textContent = 'Add transaction limit';
        updateWithdrawalType();
    }
    function updateWithdrawalType(){
        const isWithdrawal = form.querySelector('[name="transaction_type"]').value === 'withdrawal';
        document.getElementById('agencyWithdrawalTypeWrap').classList.toggle('d-none', !isWithdrawal);
        form.querySelector('[name="withdrawal_type"]').required = isWithdrawal;
        if(!isWithdrawal) form.querySelector('[name="withdrawal_type"]').value = '';
    }
    function sanitizeAmount(input){
        const cleaned = input.value.replace(/[^0-9.]/g, '');
        const parts = cleaned.split('.');
        input.value = parts.length > 1 ? parts[0] + '.' + parts.slice(1).join('').slice(0, 2) : parts[0];
    }
    form.addEventListener('submit', async event => {
        event.preventDefault();
        pendingSaves++;
        const submitButton = form.querySelector('button[type="submit"]');
        const originalButtonText = submitButton.innerHTML;
        submitButton.disabled = false;
        submitButton.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i>Saving...';
        const data = new FormData(form);
        data.set('action', 'save');
        data.set('id', form.querySelector('[name="id"]').value || '0');
        data.set('transaction_type', form.querySelector('[name="transaction_type"]').value || '');
        data.set('withdrawal_type', form.querySelector('[name="withdrawal_type"]').value || '');
        data.set('network', form.querySelector('[name="network"]').value.trim());
        data.set('max_amount', form.querySelector('[name="max_amount"]').value || '');
        data.set('active', form.querySelector('[name="active"]').checked ? '1' : '0');
        const currentType = data.get('transaction_type');
        const currentWithdrawalType = data.get('withdrawal_type');
        const currentNetwork = String(data.get('network') || '').trim();
        const currentAmount = Number(data.get('max_amount') || 0);
        const submittedState = {
            id: String(data.get('id') || '0'),
            transactionType: String(currentType),
            withdrawalType: String(currentWithdrawalType || ''),
            network: currentNetwork,
            amount: String(data.get('max_amount') || ''),
        };
        if(!currentType || !currentNetwork || currentAmount <= 0 || (currentType === 'withdrawal' && !currentWithdrawalType)){
            notify('Transaction type, bank network, and a positive maximum amount are required. Withdrawal type is required for withdrawals.', true);
            pendingSaves--;
            submitButton.innerHTML = originalButtonText;
            return;
        }
        try {
            const response = await fetch(endpoint, {method:'POST', body:data, credentials:'include', cache:'no-store', headers:{Accept:'application/json'}});
            const payload = await response.json();
            if(!response.ok || !payload.success) throw new Error(payload.message || 'Unable to save limit.');
            notify(payload.message, false);
            if(modal) modal.hide();
            window.location.reload();
        } catch(error) {
            notify(error.message, true);
        } finally {
            pendingSaves--;
            if(pendingSaves === 0){
                submitButton.disabled = false;
                submitButton.innerHTML = form.querySelector('[name="id"]').value === '0'
                    ? '<i class="fa fa-save me-1"></i>Save limit'
                    : originalButtonText;
            }
        }
    });
    document.getElementById('agencyLimitAdd').addEventListener('click', reset);
    modalElement.addEventListener('hidden.bs.modal', reset);
    form.querySelector('[name="max_amount"]').addEventListener('input', event => sanitizeAmount(event.target));
    document.getElementById('agencyLimitTransactionType').addEventListener('change', updateWithdrawalType);
    document.getElementById('agencyLimitReset').addEventListener('click', () => { document.getElementById('agencyLimitSearch').value=''; document.getElementById('agencyLimitTypeFilter').value=''; document.getElementById('agencyLimitStatusFilter').value=''; render(); });
    ['agencyLimitSearch'].forEach(id => document.getElementById(id).addEventListener('input', render));
    ['agencyLimitTypeFilter','agencyLimitStatusFilter'].forEach(id => document.getElementById(id).addEventListener('change', render));
    document.addEventListener('click', async event => { const edit = event.target.closest('.js-agency-edit'); const del = event.target.closest('.js-agency-delete'); if(edit){ form.querySelector('[name="id"]').value=edit.dataset.id || '0'; form.querySelector('[name="transaction_type"]').value=edit.dataset.type || ''; form.querySelector('[name="withdrawal_type"]').value=edit.dataset.withdrawalType || ''; updateWithdrawalType(); form.querySelector('[name="network"]').value=edit.dataset.network || ''; form.querySelector('[name="max_amount"]').value=edit.dataset.amount || ''; form.querySelector('[name="active"]').checked=Number(edit.dataset.active)===1; form.querySelector('button[type="submit"]').innerHTML='<i class="fa fa-save me-1"></i>Update limit'; modalTitle.textContent = 'Edit transaction limit'; if(modal) modal.show(); return; } if(del && confirm('Delete this Agency Banking limit?')){ const data=new FormData(); data.set('action','delete'); data.set('id',del.dataset.id); data.set('csrf',form.querySelector('[name="csrf"]').value); try { const response=await fetch(endpoint,{method:'POST',body:data,credentials:'include',headers:{Accept:'application/json'}}); const payload=await response.json(); if(!response.ok || !payload.success) throw new Error(payload.message || 'Unable to delete limit.'); window.location.reload(); } catch(error) { notify(error.message,true); } } });
    updateWithdrawalType();
    load().catch(error => notify(error.message, true));
})();
</script>
<?php endif; ?>
