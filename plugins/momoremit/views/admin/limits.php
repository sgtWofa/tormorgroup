<?php if(user_can('is_admin')): ?>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">Momoremit Transaction Limits</h4>
            <p class="text-muted mb-0">Set maximum amounts by transaction type, SIM type, and network. Withdrawal and CIT limits use network only.</p>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>"><i class="fa fa-arrow-left me-1"></i>Back</a>
            <button class="btn btn-primary" type="button" id="limitAdd" data-bs-toggle="modal" data-bs-target="#limitModal"><i class="fa fa-plus me-1"></i>Add limit</button>
        </div>
    </div>

    <div class="modal fade" id="limitModal" tabindex="-1" aria-labelledby="limitModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="limitModalLabel">Add transaction limit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" id="limitForm">
                    <div class="modal-body">
                <?=csrf()?>
                <input type="hidden" name="limit_action" value="save">
                <input type="hidden" name="id" value="<?=esc($editLimit->id ?? 0)?>">
                <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Transaction type</label>
                    <select name="transaction_type" class="form-select" required>
                        <option value="">Select type</option>
                        <option value="withdrawal" <?=($editLimit->transaction_type ?? '') === 'withdrawal' ? 'selected' : ''?>>Withdrawal</option>
                        <option value="cashin" <?=($editLimit->transaction_type ?? '') === 'cashin' ? 'selected' : ''?>>Cash in / Deposit</option>
                        <option value="cit" <?=($editLimit->transaction_type ?? '') === 'cit' ? 'selected' : ''?>>CIT</option>
                    </select>
                </div>
                <div class="col-md-6" id="limitSimTypeWrap">
                    <label class="form-label">SIM type</label>
                    <select name="sim_type" id="limitSimType" class="form-select">
                        <option value="">Select SIM type</option>
                        <option value="subscriber" <?=($editLimit->sim_type ?? '') === 'subscriber' ? 'selected' : ''?>>Subscriber</option>
                        <option value="merchant" <?=($editLimit->sim_type ?? '') === 'merchant' ? 'selected' : ''?>>Merchant</option>
                        <option value="agent" <?=($editLimit->sim_type ?? '') === 'agent' ? 'selected' : ''?>>Agent</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Network</label>
                    <input name="network" class="form-control" placeholder="MTN, Telecel, or new network" value="<?=esc($editLimit->network ?? '')?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Maximum amount (GHS)</label>
                    <input name="max_amount" type="number" min="0.01" step="0.01" inputmode="decimal" class="form-control js-limit-amount" value="<?=esc($editLimit->max_amount ?? '')?>" required>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="active" value="1" <?=empty($editLimit) || !empty($editLimit->active) ? 'checked' : ''?> id="limitActive">
                        <label class="form-check-label" for="limitActive">Active</label>
                    </div>
                    </div>
                </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-primary" type="submit"><i class="fa fa-save me-1"></i><?=!empty($editLimit) ? 'Update limit' : 'Save limit'?></button>
                    </div>
                </form>
                <div id="limitMessage" class="alert d-none mx-3 mb-3" role="status"></div>
            </div>
        </div>
    </div>
    <script>
    (function(){
        const type = document.querySelector('#limitForm select[name="transaction_type"]');
        const sim = document.getElementById('limitSimType');
        const wrap = document.getElementById('limitSimTypeWrap');
        function updateSim(){
            const simNotRequired = type && ['withdrawal', 'cit'].includes(type.value);
            if(wrap) wrap.classList.toggle('d-none', simNotRequired);
            if(sim){ sim.disabled = simNotRequired; sim.required = !simNotRequired; if(simNotRequired) sim.value = ''; }
        }
        if(type) type.addEventListener('change', updateSim);
        updateSim();
    })();
    </script>

    <!-- filters -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="get" id="limitFilters" class="row g-3 align-items-end">
                <div class="col-12 col-lg-4">
                    <label class="form-label" for="limitFind">Search</label>
                    <input id="limitFind" name="find" class="form-control" placeholder="Transaction, SIM type, or network" value="<?=esc($limitFind ?? '')?>">
                </div>
                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label" for="limitFilterType">Transaction type</label>
                    <select id="limitFilterType" name="transaction_type" class="form-select">
                        <option value="">All types</option>
                        <option value="cashin" <?=($limitTransactionType ?? '') === 'cashin' ? 'selected' : ''?>>Cash in</option>
                        <option value="withdrawal" <?=($limitTransactionType ?? '') === 'withdrawal' ? 'selected' : ''?>>Withdrawal</option>
                        <option value="cit" <?=($limitTransactionType ?? '') === 'cit' ? 'selected' : ''?>>CIT</option>
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label" for="limitFilterSim">SIM type</label>
                    <select id="limitFilterSim" name="sim_type" class="form-select">
                        <option value="">All SIM types</option>
                        <option value="subscriber" <?=($limitSimType ?? '') === 'subscriber' ? 'selected' : ''?>>Subscriber</option>
                        <option value="merchant" <?=($limitSimType ?? '') === 'merchant' ? 'selected' : ''?>>Merchant</option>
                        <option value="agent" <?=($limitSimType ?? '') === 'agent' ? 'selected' : ''?>>Agent</option>
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label" for="limitFilterStatus">Status</label>
                    <select id="limitFilterStatus" name="status" class="form-select">
                        <option value="">All statuses</option>
                        <option value="active" <?=($limitStatus ?? '') === 'active' ? 'selected' : ''?>>Active</option>
                        <option value="inactive" <?=($limitStatus ?? '') === 'inactive' ? 'selected' : ''?>>Inactive</option>
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label" for="limitFilterNetwork">Network</label>
                    <input id="limitFilterNetwork" name="network" class="form-control" placeholder="All networks" value="<?=esc($limitNetwork ?? '')?>">
                </div>
                <div class="col-12 col-sm-6 col-lg-1">
                    <button class="btn btn-primary w-100" type="submit" title="Apply filters"><i class="fa fa-filter"></i><span class="visually-hidden">Apply filters</span></button>
                </div>
                <div class="col-12 col-sm-6 col-lg-1">
                    <button class="btn btn-outline-secondary w-100 js-reset-limit-filters" type="button">Reset</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-nowrap" id="limitsTable">
                <thead>
                    <tr><th>Transaction</th><th>SIM type</th><th>Network</th><th>Maximum</th><th>Status</th><th class="text-end">Action</th></tr>
                </thead>
                <tbody id="limitsTableBody">
                <?php if(empty($momoremitLimits)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No limits match the current filters.</td></tr>
                <?php else: foreach($momoremitLimits as $limit): ?>
                    <tr>
                        <td><?=esc(ucwords(str_replace('cashin', 'cash in', (string)$limit->transaction_type)))?></td>
                        <td><?=in_array(momoremits_normalize_limit_type($limit->transaction_type ?? ''), ['withdrawal', 'cit'], true) ? 'All SIM types' : esc(ucfirst((string)$limit->sim_type))?></td>
                        <td><?=esc(strtoupper((string)$limit->network))?></td>
                        <td>GHS <?=number_format((float)$limit->max_amount, 2)?></td>
                        <td><span class="badge <?=!empty($limit->active) ? 'bg-success' : 'bg-secondary'?>"><?=!empty($limit->active) ? 'Active' : 'Inactive'?></span></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-primary js-edit-limit" type="button" data-bs-toggle="modal" data-bs-target="#limitModal" data-limit-id="<?=esc($limit->id)?>" title="Edit limit"><i class="fa fa-pen"></i></button>
                            <form method="post" class="d-inline js-delete-limit">
                                <?=csrf()?>
                                <input type="hidden" name="limit_action" value="delete">
                                <input type="hidden" name="id" value="<?=esc($limit->id)?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit" title="Delete limit"><i class="fa fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
(function(){
    const endpoint = '<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/limits-api';
    const filters = document.getElementById('limitFilters');
    const form = document.getElementById('limitForm');
    const tableBody = document.getElementById('limitsTableBody');
    const message = document.getElementById('limitMessage');
    const modalElement = document.getElementById('limitModal');
    const modalTitle = document.getElementById('limitModalLabel');
    const modal = modalElement && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(modalElement) : null;
    const type = form && form.querySelector('[name="transaction_type"]');
    const sim = document.getElementById('limitSimType');
    const simWrap = document.getElementById('limitSimTypeWrap');
    const limitRows = new Map();
    let allLimitRows = [];
    let listRequestController = null;
    let listRequestSequence = 0;

    function escapeHtml(value){
        return String(value ?? '').replace(/[&<>'"]/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[character]));
    }

    function showMessage(text, isError){
        if(!message) return;
        message.textContent = text;
        message.className = 'alert mt-3 mb-0 ' + (isError ? 'alert-danger' : 'alert-success');
    }

    function updateSim(){
        const simNotRequired = type && ['withdrawal', 'cit'].includes(type.value);
        if(simWrap) simWrap.classList.toggle('d-none', simNotRequired);
        if(sim){
            sim.disabled = simNotRequired;
            sim.required = !simNotRequired;
            if(simNotRequired) sim.value = '';
        }
    }
    function sanitizeAmount(input){
        const cleaned = input.value.replace(/[^0-9.]/g, '');
        const parts = cleaned.split('.');
        input.value = parts.length > 1 ? parts[0] + '.' + parts.slice(1).join('').slice(0, 2) : parts[0];
    }

    function resetForm(){
        if(!form) return;
        form.reset();
        form.querySelector('[name="id"]').value = '0';
        form.querySelector('button[type="submit"]').innerHTML = '<i class="fa fa-save me-1"></i>Save limit';
        modalTitle.textContent = 'Add transaction limit';
        updateSim();
    }

    function renderRows(rows){
        limitRows.clear();
        if(!tableBody) return;
        rows.forEach(row => limitRows.set(String(row.id), row));
        if(!rows.length){
            tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No limits match the current filters.</td></tr>';
            return;
        }
        tableBody.innerHTML = rows.map(row => {
            const typeLabel = row.transaction_type === 'cashin' ? 'Cash In' : row.transaction_type.charAt(0).toUpperCase() + row.transaction_type.slice(1);
            const simLabel = ['withdrawal', 'cit'].includes(row.transaction_type) ? 'All SIM types' : escapeHtml(row.sim_type).replace(/^./, character => character.toUpperCase());
            const statusLabel = Number(row.active) === 1 ? 'Active' : 'Inactive';
            const statusClass = Number(row.active) === 1 ? 'bg-success' : 'bg-secondary';
            return '<tr>'
                + '<td>' + escapeHtml(typeLabel) + '</td>'
                + '<td>' + simLabel + '</td>'
                + '<td>' + escapeHtml(String(row.network).toUpperCase()) + '</td>'
                + '<td>GHS ' + Number(row.max_amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>'
                + '<td><span class="badge ' + statusClass + '">' + statusLabel + '</span></td>'
                + '<td class="text-end"><button class="btn btn-sm btn-outline-primary js-edit-limit" type="button" data-bs-toggle="modal" data-bs-target="#limitModal" data-limit-id="' + escapeHtml(row.id) + '" title="Edit limit"><i class="fa fa-pen"></i></button> '
                + '<button class="btn btn-sm btn-outline-danger js-delete-limit" type="button" data-limit-id="' + escapeHtml(row.id) + '" title="Delete limit"><i class="fa fa-trash"></i></button></td>'
                + '</tr>';
        }).join('');
    }

    async function loadLimits(){
        const params = new URLSearchParams({
            action: 'list',
            _: String(Date.now())
        });
        const requestSequence = ++listRequestSequence;
        if(listRequestController) listRequestController.abort();
        listRequestController = new AbortController();
        try{
            const response = await fetch(endpoint + '?' + params.toString(), {
                credentials: 'include',
                cache: 'no-store',
                signal: listRequestController.signal,
                headers: {'Accept': 'application/json'}
            });
            const payload = await response.json();
            if(requestSequence !== listRequestSequence) return;
            if(!response.ok || !payload.success) throw new Error(payload.message || 'Unable to load limits.');
            allLimitRows = payload.rows || [];
            applyCurrentFilters();
        }catch(error){
            if(error.name === 'AbortError' || requestSequence !== listRequestSequence) return;
            showMessage(error.message, true);
        }
    }

    function applyCurrentFilters(){
        const find = document.getElementById('limitFind')?.value.trim().toLowerCase() || '';
        const network = document.getElementById('limitFilterNetwork')?.value.trim().toLowerCase() || '';
        const transactionType = document.getElementById('limitFilterType')?.value || '';
        const simType = document.getElementById('limitFilterSim')?.value || '';
        const status = document.getElementById('limitFilterStatus')?.value || '';
        const filteredRows = allLimitRows.filter(row => {
            const searchable = [row.transaction_type, row.sim_type, row.network].join(' ').toLowerCase();
            return (!find || searchable.includes(find))
                && (!network || String(row.network).toLowerCase().includes(network))
                && (!transactionType || row.transaction_type === transactionType)
                && (!simType || row.sim_type === simType)
                && (!status || (status === 'active' ? Number(row.active) === 1 : Number(row.active) === 0));
        });
        renderRows(filteredRows);
    }

    async function submitLimit(data){
        try{
            const response = await fetch(endpoint, {method: 'POST', body: data, credentials: 'include', headers: {'Accept': 'application/json'}});
            const payload = await response.json();
            if(!response.ok || !payload.success) throw new Error(payload.message || 'Unable to save limit.');
            showMessage(payload.message, false);
            if(modal) modal.hide();
            window.location.reload();
        }catch(error){
            showMessage(error.message, true);
        }
    }

    if(type) type.addEventListener('change', updateSim);
    if(filters) filters.addEventListener('submit', event => { event.preventDefault(); applyCurrentFilters(); });
    document.getElementById('limitAdd').addEventListener('click', resetForm);
    modalElement.addEventListener('hidden.bs.modal', resetForm);
    form.querySelector('[name="max_amount"]').addEventListener('input', event => sanitizeAmount(event.target));
    const resetFilters = document.querySelector('.js-reset-limit-filters');
    if(resetFilters) resetFilters.addEventListener('click', () => {
        if(filters) filters.reset();
        applyCurrentFilters();
    });
    ['limitFind', 'limitFilterNetwork'].forEach(id => {
        const input = document.getElementById(id);
        if(input) input.addEventListener('input', applyCurrentFilters);
    });
    ['limitFilterType', 'limitFilterSim', 'limitFilterStatus'].forEach(id => {
        const select = document.getElementById(id);
        if(select) select.addEventListener('change', applyCurrentFilters);
    });
    if(form) form.addEventListener('submit', event => {
        event.preventDefault();
        const data = new FormData(form);
        data.set('action', 'save');
        submitLimit(data);
    });
    document.addEventListener('click', event => {
        const editButton = event.target.closest('.js-edit-limit');
        if(editButton){
            const row = limitRows.get(String(editButton.dataset.limitId));
            if(!row || !form) return;
            form.querySelector('[name="id"]').value = row.id;
            type.value = row.transaction_type;
            sim.value = row.sim_type;
            form.querySelector('[name="network"]').value = row.network;
            form.querySelector('[name="max_amount"]').value = row.max_amount;
            form.querySelector('[name="active"]').checked = Number(row.active) === 1;
            form.querySelector('button[type="submit"]').innerHTML = '<i class="fa fa-save me-1"></i>Update limit';
            modalTitle.textContent = 'Edit transaction limit';
            updateSim();
            if(modal) modal.show();
            return;
        }
        const deleteButton = event.target.closest('.js-delete-limit');
        if(deleteButton){
            event.preventDefault();
            if(!confirm('Delete this limit?')) return;
            const data = new FormData();
            data.set('action', 'delete');
            const deleteId = deleteButton.dataset.limitId || deleteButton.querySelector('[name="id"]')?.value || '0';
            data.set('id', deleteId);
            const token = form && form.querySelector('[name="csrf"]');
            if(token) data.set(token.name, token.value);
            submitLimit(data);
        }
    });
    updateSim();
    loadLimits();
})();
</script>
<?php endif; ?>
