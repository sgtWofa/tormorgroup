<div class="backoffice-shell col-md-10 mx-auto">
  <div class="bo-hero mb-4">
    <div class="bo-hero-title">Add Agent Service</div>
    <p class="bo-hero-sub mb-0">Create a new agent service transaction (Cash4Ecash, Ecash4Cash, Ecash4Ecash)</p>
  </div>

  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
      <form method="post" enctype="multipart/form-data" id="agentServiceForm">
        <?=csrf()?>
        <input type="hidden" name="action" value="agent_services_create">
        <input type="hidden" name="denominations_json" id="denominations_json" value="<?=esc(old_value('denominations_json', '[]'))?>">
        <input type="hidden" name="change_amount" id="change_amount" value="<?=esc(old_value('change_amount', '0.00'))?>">
        <input type="hidden" name="change_denominations_json" id="change_denominations_json" value="<?=esc(old_value('change_denominations_json', '[]'))?>">

        <div class="row g-3 mb-4">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Customer Type</label>
            <select class="form-select" name="customer_type" id="customer_type" required onchange="document.getElementById('service_type') && (document.getElementById('service_type').disabled = false)">
              <option value="">--- Select ---</option>
              <option <?=old_select('customer_type', 'External')?> value="External">External</option>
              <option <?=old_select('customer_type', 'Internal')?> value="Internal">Internal</option>
            </select>
            <?php if(!empty($errors['customer_type'])):?>
              <small class="text-danger"><?=$errors['customer_type']?></small>
            <?php endif?>
          </div>
          
          <div class="col-md-6">
            <label class="form-label fw-semibold">Service Type</label>
            <select class="form-select" name="service_type" id="service_type" required disabled>
              <option value="">--- Select ---</option>
              <option <?=old_select('service_type', 'Cash4Ecash')?> value="Cash4Ecash">Cash4Ecash</option>
              <option <?=old_select('service_type', 'Ecash4Cash')?> value="Ecash4Cash">Ecash4Cash</option>
              <option <?=old_select('service_type', 'Ecash4Ecash')?> value="Ecash4Ecash">Ecash4Ecash</option>
            </select>
            <?php if(!empty($errors['service_type'])):?>
              <small class="text-danger"><?=$errors['service_type']?></small>
            <?php endif?>
          </div>
        </div>

        <div id="dynamicFields" class="row g-3">
          <!-- Common Agent Details -->
          <div class="col-md-12 js-agent-details d-none">
            <h6 class="border-bottom pb-2 mb-3 mt-2">Agent Details</h6>
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">Agent Name</label>
                <input type="text" class="form-control" name="agent_name" value="<?=esc(old_value('agent_name'))?>">
              </div>
              <div class="col-md-4">
                <label class="form-label">Contact Number</label>
                <input type="text" class="form-control" name="agent_contact" value="<?=esc(old_value('agent_contact'))?>">
              </div>
              <div class="col-md-4">
                <label class="form-label">Business Name</label>
                <input type="text" class="form-control" name="business_name" value="<?=esc(old_value('business_name'))?>">
              </div>
            </div>
          </div>

          <!-- Provider Select -->
          <div class="col-md-6 js-provider d-none">
            <label class="form-label">Service Provider</label>
            <select class="form-select" name="provider" id="provider">
              <option value="">--- Select ---</option>
              <?php foreach(['Mtn', 'Telecel', 'AT', 'Gmoney', 'Ghanapay', 'Fidelity Bank', 'Ecobank', 'Access Bank', 'GCB Bank', 'GtBank', 'Apex Nafana Bank', 'Apex Suma Bank', 'Stanbic Bank', 'First Bank Ghana (FBG)', 'First National Bank (FNB)', 'Zeepay'] as $prov): ?>
                <option <?=old_select('provider', $prov)?> value="<?=$prov?>"><?=$prov?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Networks -->
          <div class="col-md-6 js-receiving-network d-none">
            <label class="form-label">Receiving Network</label>
            <select class="form-select" name="receiving_network" id="receiving_network">
              <option value="">--- Select ---</option>
              <?php foreach(['Mtn', 'Telecel', 'AT', 'Zeepay', 'Ghanapay', 'G-money'] as $prov): ?>
                <option <?=old_select('receiving_network', $prov)?> value="<?=$prov?>"><?=$prov?></option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div class="col-md-6 js-paying-network d-none">
            <label class="form-label">Paying Network / Sent Ecash</label>
            <select class="form-select" name="paying_network" id="paying_network">
              <option value="">--- Select ---</option>
              <?php foreach(['Mtn', 'Telecel', 'AT', 'Zeepay', 'Ghanapay', 'G-money'] as $prov): ?>
                <option <?=old_select('paying_network', $prov)?> value="<?=$prov?>"><?=$prov?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Numbers -->
          <div class="col-md-6 js-agent-number d-none">
            <label class="form-label js-agent-number-label">Paying / Receiving Phone</label>
            <input type="text" class="form-control" name="agent_number" value="<?=esc(old_value('agent_number'))?>">
          </div>

          <!-- Target Amount/Load -->
          <div class="col-md-6 js-amount-field d-none">
            <label class="form-label js-amount-label">Amount / Load</label>
            <input type="number" step="0.01" class="form-control" name="amount" id="amount" value="<?=esc(old_value('amount'))?>" placeholder="Enter amount">
          </div>

          <!-- Cash Received (Triggers Currency Calculator Modal) -->
          <div class="col-md-6 js-cash-received-field d-none">
            <label class="form-label text-success fw-semibold">
              <i class="fa-solid fa-money-bill-wave me-1"></i> Cash Received
            </label>
            <div class="input-group cursor-pointer" id="cashReceivedTrigger"> 
              <span class="input-group-text text-warning"><i class="fa-solid fa-calculator"></i></span>
              <input type="number" step="0.01" class="form-control cursor-pointer" name="cash_received" id="cash_received" placeholder="Click to calculate cash" value="<?=esc(old_value('cash_received'))?>" readonly>
            </div>
            <small class="text-muted" id="changeNoticeSummary"></small>
          </div>
          
          <!-- Image Upload -->
          <div class="col-md-6 js-proof-image d-none">
            <label class="form-label">Picture Upload (Proof)</label>
            <input type="file" class="form-control" name="proof_image" accept="image/*">
          </div>

          <!-- Assignment -->
          <?php
            $currentSession = get_session();
            $isAdminOrOps = backoffice_user_is_admin($currentSession) || user_can('is_operations_manager');
            $externalAllowedRoles = ['admin','manager','operations manager','operation manager','operations_manager','operation_manager','operationsmanager','operationmanager'];
          ?>
          <div class="col-md-6 js-assign d-none">
            <label class="form-label">Assign To (Admin/Ops)</label>
            <select class="form-select" name="assigned_to" id="assigned_to">
              <option value="">--- Select ---</option>
              <?php if (!empty($users)): ?>
                <?php foreach($users as $u): ?>
                  <?php
                    $displayName = trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? ''));
                    $balance = '';
                    if (!empty($u->current_balance) || $u->current_balance === 0.0 || $u->current_balance === '0') {
                        $balance = ' (GH¢ ' . number_format((float)$u->current_balance, 2) . ')';
                    }
                    $userRoles = $u->roles ?? [''];
                    if (!is_array($userRoles)) {
                        $userRoles = [$userRoles];
                    }
                    $normalizedRoles = array_map(function($role){ return strtolower(trim((string)$role)); }, $userRoles);
                    $roleAttr = esc(trim(implode(',', $normalizedRoles)));

                    // Only users with an active cashdrawer are fetched here; customer-type filtering
                    // is applied in the UI and enforced on the server for external assignments.
                    $showUser = true;
                  ?>
                  <?php if ($showUser): ?>
                    <?php
                      $assignDisplay = '';
                      if (!empty($backofficePaymentAssignmentsMap[$u->id])) {
                        $val = $backofficePaymentAssignmentsMap[$u->id];
                        if (is_string($val)) {
                          $assignDisplay = $val;
                        } else {
                          // convert arrays/objects to readable string
                          $assignDisplay = json_encode($val);
                        }
                      }
                    ?>
                    <option <?=old_select('assigned_to', $u->id)?> value="<?=$u->id?>" data-display-name="<?=esc($displayName)?>" data-roles="<?=$roleAttr?>">
                      <?=esc($displayName)?>
                      <?php if ($assignDisplay !== ''): ?>
                        (<?=esc($assignDisplay)?>)
                      <?php endif; ?>
                    </option>
                  <?php endif; ?>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>
            <div id="assignedBalanceInfo" class="form-text text-muted mt-2">Select a user and service type to view balance.</div>
            <?php if(!empty($errors['assigned_to'])):?>
              <small class="text-danger"><?=$errors['assigned_to']?></small>
            <?php endif?>
          </div>
        </div>

        <div class="d-flex justify-content-between mt-5 pt-3 border-top">
          <a id="agentServiceCancel" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>#agent-services" class="btn btn-outline-secondary px-4 rounded-pill">
            <i class="bi bi-arrow-left me-1"></i> Cancel
          </a>
          <button id="agentServiceSubmit" type="submit" class="btn btn-primary px-5 rounded-pill shadow-sm">
            Submit Request <i class="bi bi-check2-circle ms-1"></i>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Currency Calculator Modal (Received Cash) -->
<div class="modal fade" id="currencyModal" data-bs-backdrop="static" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-light">
        <h5 class="modal-title fw-bold"><i class="fa-solid fa-calculator text-warning me-2"></i>Cash Received Calculator</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body table-responsive">
        <div class="alert alert-info py-2 mb-3">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="fw-semibold"><i class="bi bi-info-circle me-1"></i> Target Amount / Load:</span>
            <strong id="modalTargetAmount" class="fs-6 text-primary">GH&cent; 0.00</strong>
          </div>
          <div class="d-flex justify-content-between align-items-center">
            <span class="fw-semibold text-danger" id="modalAmountLeftText">&nbsp;</span>
            <span class="small text-muted">Enter cash notes to meet or exceed the target.</span>
          </div>
        </div>
        <table class="table table-hover table-sm">
          <thead><tr><th>NOTES</th><th>COUNT</th><th>VOLUME</th></tr></thead>
          <tbody>
            <?php foreach([200, 100, 50, 20, 10, 5, 2, 1, 0.5, 0.2, 0.1] as $deno): ?>
            <tr data-denomination="<?=$deno?>">
              <td class="px-4 fw-semibold">GH&cent;<?=$deno < 1 ? ($deno * 100).'p' : $deno?></td>
              <td><input type="number" class="form-control p-1 count-input" placeholder="0" min="0"></td>
              <td><input type="text" class="form-control p-1 volume-input" placeholder="0.00" readonly></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr class="table-light">
              <th class="px-4">TOTAL</th>
              <th><input type="text" class="form-control p-1 bg-transparent border-0 fw-bold" id="total-count" readonly></th>
              <th><input type="text" class="form-control p-1 bg-transparent border-0 fw-bold text-success fs-6" id="total-volume" readonly></th>
            </tr>
          </tfoot>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary rounded-pill px-4" id="confirmCalcBtn">Confirm Cash</button>
      </div>
    </div>
  </div>
</div>

<!-- Change Calculator Modal (Given Change) -->
<div class="modal fade" id="changeModal" data-bs-backdrop="static" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content border-warning">
      <div class="modal-header bg-warning bg-opacity-10">
        <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-hand-holding-dollar text-warning me-2"></i>Record Physical Change Notes</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body table-responsive">
        <div class="alert alert-warning py-2 mb-3">
          <div class="d-flex justify-content-between"><span>Cash Received:</span> <strong id="modalCashReceivedText">GH&cent; 0.00</strong></div>
          <div class="d-flex justify-content-between"><span>Target Amount:</span> <strong id="modalTargetAmountChangeText">GH&cent; 0.00</strong></div>
          <hr class="my-1">
          <div class="d-flex justify-content-between fs-6 fw-bold text-danger">
            <span>Change Required:</span>
            <span id="modalChangeDueText">GH&cent; 0.00</span>
          </div>
        </div>
        <table class="table table-hover table-sm">
          <thead><tr><th>NOTES</th><th>COUNT</th><th>VOLUME</th></tr></thead>
          <tbody>
            <?php foreach([200, 100, 50, 20, 10, 5, 2, 1, 0.5, 0.2, 0.1] as $deno): ?>
            <tr data-denomination="<?=$deno?>">
              <td class="px-4 fw-semibold">GH&cent;<?=$deno < 1 ? ($deno * 100).'p' : $deno?></td>
              <td><input type="number" class="form-control p-1 change-count-input" placeholder="0" min="0"></td>
              <td><input type="text" class="form-control p-1 change-volume-input" placeholder="0.00" readonly></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr class="table-light">
              <th class="px-4">TOTAL CHANGE</th>
              <th><input type="text" class="form-control p-1 bg-transparent border-0 fw-bold" id="change-total-count" readonly></th>
              <th><input type="text" class="form-control p-1 bg-transparent border-0 fw-bold text-danger fs-6" id="change-total-volume" readonly></th>
            </tr>
          </tfoot>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-warning text-dark fw-semibold rounded-pill px-4" id="confirmChangeBtn">Save Change Notes</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
  try {
    const currentUserId = <?= (int)(get_session()->id ?? 0) ?>;
    const customerType = document.getElementById('customer_type');
    const serviceType = document.getElementById('service_type');
    const providerSelect = document.getElementById('provider');
    const receivingSelect = document.getElementById('receiving_network');
    const payingSelect = document.getElementById('paying_network');
    const allFields = document.querySelectorAll('#dynamicFields > div');

    const fAgentDetails = document.querySelector('.js-agent-details');
    const fProvider = document.querySelector('.js-provider');
    const fReceivingNet = document.querySelector('.js-receiving-network');
    const fPayingNet = document.querySelector('.js-paying-network');
    const fAgentNumber = document.querySelector('.js-agent-number');
    const fAmountField = document.querySelector('.js-amount-field');
    const fCashReceivedField = document.querySelector('.js-cash-received-field');
    const fProofImage = document.querySelector('.js-proof-image');
    const fAssign = document.querySelector('.js-assign');
    const amountLabel = document.querySelector('.js-amount-label');
    const amountInput = document.getElementById('amount');
    const cashReceivedLabel = document.querySelector('.js-cash-received-field label');
    const cashReceivedInput = document.getElementById('cash_received');
    const cashReceivedTrigger = document.getElementById('cashReceivedTrigger');
    const changeNoticeSummary = document.getElementById('changeNoticeSummary');

    // Modals
    const calcModalEl = document.getElementById('currencyModal');
    const calcModal = new bootstrap.Modal(calcModalEl);
    const changeModalEl = document.getElementById('changeModal');
    const changeModal = new bootstrap.Modal(changeModalEl);

    const denosJsonInput = document.getElementById('denominations_json');
    const changeAmountInput = document.getElementById('change_amount');
    const changeDenosJsonInput = document.getElementById('change_denominations_json');

    const modalTargetAmount = document.getElementById('modalTargetAmount');
    const modalCashReceivedText = document.getElementById('modalCashReceivedText');
    const modalTargetAmountChangeText = document.getElementById('modalTargetAmountChangeText');
    const modalChangeDueText = document.getElementById('modalChangeDueText');

    const assignedToInput = document.getElementById('assigned_to');
    const assignedBalanceInfo = document.getElementById('assignedBalanceInfo');
    const currentUserCanSkipAssign = <?= (backoffice_user_is_admin(get_session()) || user_can('is_operations_manager')) ? 'true' : 'false' ?>;

    let expectedChangeDue = 0;

    customerType.addEventListener('change', function() {
        if (this.value) {
            serviceType.disabled = false;
            updateFields();
        } else {
            serviceType.disabled = true;
            serviceType.value = '';
            hideAll();
        }
    });

    serviceType.addEventListener('change', updateFields);
    if (receivingSelect) {
        receivingSelect.addEventListener('change', function() {
            if (serviceType.value === 'Ecash4Ecash') {
                hidePayingSameAsReceiving();
            }
        });
    }

    if (customerType.value && serviceType.value) {
        updateFields();
    }

    const blockedProviderNames = ['fidelity', 'ecobank', 'access bank', 'gcb', 'fnb', 'fbn'];

    function hideAll() {
        allFields.forEach(f => f.classList.add('d-none'));
        fAssign.querySelector('select').required = false;
    }

    function isBankProvider(name) {
        const normalized = String(name).trim().toLowerCase();
        if (!normalized) return false;
        if (blockedProviderNames.includes(normalized)) return true;
        return normalized.includes('bank');
    }

    function refreshProviderOptions() {
        if (!providerSelect) return;

        const ct = customerType.value;
        const st = serviceType.value;
        const filterBanks = !currentUserCanSkipAssign && ct === 'External' && ['Cash4Ecash', 'Ecash4Cash'].includes(st);

        Array.from(providerSelect.options).forEach(option => {
            if (!option.value) {
                option.hidden = false;
                option.disabled = false;
                return;
            }
            if (filterBanks && isBankProvider(option.value)) {
                option.hidden = true;
                option.disabled = true;
            } else {
                option.hidden = false;
                option.disabled = false;
            }
        });

        if (filterBanks && providerSelect.value && isBankProvider(providerSelect.value)) {
            providerSelect.value = '';
        }
    }

    function hidePayingSameAsReceiving() {
        if (!payingSelect || !receivingSelect) return;
        const st = serviceType.value;
        const receivingValue = receivingSelect.value;

        Array.from(payingSelect.options).forEach(option => {
            if (!option.value) {
                option.hidden = false;
                option.disabled = false;
                return;
            }
            if (st === 'Ecash4Ecash' && receivingValue && option.value === receivingValue) {
                option.hidden = true;
                option.disabled = true;
            } else {
                option.hidden = false;
                option.disabled = false;
            }
        });

        if (st === 'Ecash4Ecash' && payingSelect.value && payingSelect.value === receivingValue) {
            payingSelect.value = '';
        }
    }

    function updateFields() {
        hideAll();
        const ct = customerType.value;
        const st = serviceType.value;

        if (!ct || !st) return;

        fAssign.classList.remove('d-none');
        assignedToInput.required = !(ct === 'External' && currentUserCanSkipAssign);

        if (ct === 'External') {
            fAgentDetails.classList.remove('d-none');
            fAgentNumber.classList.remove('d-none');
            fAgentNumber.querySelector('label').textContent = 'Paying / Receiving Phone';
            
            if (st === 'Cash4Ecash') {
                fProvider.classList.remove('d-none');
                fAmountField.classList.remove('d-none');
                amountLabel.textContent = 'Amount / Load';
            } else if (st === 'Ecash4Cash') {
                fProvider.classList.remove('d-none');
                fAmountField.classList.remove('d-none');
                amountLabel.textContent = 'Amount / Load';
            } else if (st === 'Ecash4Ecash') {
                fReceivingNet.classList.remove('d-none');
                fPayingNet.classList.remove('d-none');
                fAmountField.classList.remove('d-none');
                amountLabel.textContent = 'Amount / Load';
            }
        } else if (ct === 'Internal') {
            fAgentNumber.classList.remove('d-none');
            fAgentNumber.querySelector('label').textContent = 'Agent Number / Account / Shortcode';

            if (st === 'Cash4Ecash') {
                fReceivingNet.classList.remove('d-none');
                fReceivingNet.querySelector('label').textContent = 'Ecash Needed';
                fAmountField.classList.remove('d-none');
                fCashReceivedField.classList.remove('d-none');
                amountLabel.textContent = 'Load / Amount';
                if (cashReceivedLabel) {
                    cashReceivedLabel.innerHTML = '<i class="fa-solid fa-money-bill-wave me-1"></i> Cash to Send';
                }
            } else if (st === 'Ecash4Ecash') {
                fReceivingNet.classList.remove('d-none');
                fReceivingNet.querySelector('label').textContent = 'Ecash Needed';
                fPayingNet.classList.remove('d-none');
                fPayingNet.querySelector('label').textContent = 'Ecash Sent';
                fAmountField.classList.remove('d-none');
                amountLabel.textContent = 'Load / Amount';
                if (cashReceivedLabel) {
                    cashReceivedLabel.innerHTML = '<i class="fa-solid fa-money-bill-wave me-1"></i> Cash Received';
                }
            } else if (st === 'Ecash4Cash') {
                fPayingNet.classList.remove('d-none');
                fPayingNet.querySelector('label').textContent = 'Ecash Sent';
                fAmountField.classList.remove('d-none');
                amountLabel.textContent = 'Cash Needed';
                if (cashReceivedLabel) {
                    cashReceivedLabel.innerHTML = '<i class="fa-solid fa-money-bill-wave me-1"></i> Cash Received';
                }
            }
        }

        refreshProviderOptions();
        hidePayingSameAsReceiving();
        filterAssignedOptionsForExternal();
    }

    function filterAssignedOptionsForExternal() {
        const ct = customerType.value;
        const isExternal = ct === 'External';
        const isAdminOrOps = currentUserCanSkipAssign;
        const allowedRoles = ['admin','manager','operations manager','operation manager','operations_manager','operation_manager','operationsmanager','operationmanager'];

        if (!assignedToInput) {
            return;
        }

        Array.from(assignedToInput.options).forEach(option => {
            if (!option.value) {
                option.hidden = false;
                return;
            }

            if (!isExternal || isAdminOrOps) {
                option.hidden = false;
                return;
            }

            const roles = (option.dataset.roles || '').toLowerCase().split(',').map(role => role.trim()).filter(Boolean);
            const allowed = roles.some(role => allowedRoles.includes(role));
            option.hidden = !allowed;
        });

        if (assignedToInput.value) {
            const selected = assignedToInput.selectedOptions[0];
            if (selected && selected.hidden) {
                assignedToInput.value = '';
            }
        }
    }

    // Received Cash Calculator Logic
    const countInputs = document.querySelectorAll("#currencyModal .count-input");
    const totalCountField = document.getElementById("total-count");
    const totalVolumeField = document.getElementById("total-volume");
    const confirmCalcBtn = document.getElementById("confirmCalcBtn");

    function updateReceivedVolumes() {
        let tCount = 0;
        let tVol = 0;
        let denos = [];

        countInputs.forEach(input => {
            const row = input.closest("tr");
            const denom = parseFloat(row.getAttribute("data-denomination"));
            const count = parseInt(input.value) || 0;
            const volField = row.querySelector(".volume-input");
            const vol = denom * count;
            
            volField.value = vol > 0 ? vol.toFixed(2) : '';
            tCount += count;
            tVol += vol;

            if (count > 0) {
                denos.push({ currency: 'GHS', denomination: denom, count: count, volume: vol });
            }
        });

        totalCountField.value = tCount || '';
        totalVolumeField.value = tVol > 0 ? tVol.toFixed(2) : '0.00';
        return { count: tCount, volume: tVol, denos: denos };
    }

    countInputs.forEach(input => input.addEventListener("input", updateReceivedVolumes));

    function updateCalcState() {
        const tgtVal = parseFloat(amountInput.value) || 0;
        const res = updateReceivedVolumes();
        const left = tgtVal - res.volume;
        const amountLeftText = document.getElementById('modalAmountLeftText');

        if (tgtVal <= 0) {
            confirmCalcBtn.disabled = true;
            if (amountLeftText) amountLeftText.textContent = 'Enter a valid target amount first.';
            return;
        }

        if (res.volume < tgtVal) {
            confirmCalcBtn.disabled = true;
            if (amountLeftText) amountLeftText.textContent = 'Amount left: GH¢ ' + left.toFixed(2);
        } else {
            confirmCalcBtn.disabled = false;
            if (amountLeftText) amountLeftText.textContent = 'Target reached or exceeded. Confirm to continue.';
        }
    }

    countInputs.forEach(input => input.addEventListener("input", updateCalcState));

    confirmCalcBtn.addEventListener('click', function() {
        const res = updateReceivedVolumes();
        const tgtVal = parseFloat(amountInput.value) || 0;

        if (res.volume < tgtVal) {
            alert('The counted cash is below the target amount. Add more cash or adjust notes.');
            return;
        }
        
        cashReceivedInput.value = res.volume.toFixed(2);
        denosJsonInput.value = JSON.stringify(res.denos);
        calcModal.hide();

        if (res.volume > tgtVal && tgtVal > 0) {
            expectedChangeDue = res.volume - tgtVal;
            modalCashReceivedText.textContent = 'GH¢ ' + res.volume.toFixed(2);
            modalTargetAmountChangeText.textContent = 'GH¢ ' + tgtVal.toFixed(2);
            modalChangeDueText.textContent = 'GH¢ ' + expectedChangeDue.toFixed(2);
            
            setTimeout(() => { changeModal.show(); }, 400);
        } else {
            changeAmountInput.value = '0.00';
            changeDenosJsonInput.value = '[]';
            changeNoticeSummary.textContent = '';
        }
    });

    amountInput.addEventListener('input', updateCalcState);
    cashReceivedTrigger.addEventListener('click', function() {
        const tgtVal = parseFloat(amountInput.value) || 0;
        modalTargetAmount.textContent = 'GH¢ ' + tgtVal.toFixed(2);
        updateCalcState();
        calcModal.show();
    });

    // Change Calculator Logic
    const changeCountInputs = document.querySelectorAll("#changeModal .change-count-input");
    const changeTotalCountField = document.getElementById("change-total-count");
    const changeTotalVolumeField = document.getElementById("change-total-volume");
    const confirmChangeBtn = document.getElementById("confirmChangeBtn");

    function updateChangeVolumes() {
        let tCount = 0;
        let tVol = 0;
        let denos = [];

        changeCountInputs.forEach(input => {
            const row = input.closest("tr");
            const denom = parseFloat(row.getAttribute("data-denomination"));
            const count = parseInt(input.value) || 0;
            const volField = row.querySelector(".change-volume-input");
            const vol = denom * count;
            
            volField.value = vol > 0 ? vol.toFixed(2) : '';
            tCount += count;
            tVol += vol;

            if (count > 0) {
                denos.push({ currency: 'GHS', denomination: denom, count: count, volume: vol });
            }
        });

        changeTotalCountField.value = tCount || '';
        changeTotalVolumeField.value = tVol > 0 ? tVol.toFixed(2) : '0.00';
        return { count: tCount, volume: tVol, denos: denos };
    }

    changeCountInputs.forEach(input => input.addEventListener("input", updateChangeVolumes));

    confirmChangeBtn.addEventListener('click', function() {
        const res = updateChangeVolumes();
        if (Math.abs(res.volume - expectedChangeDue) > 0.01) {
            alert('Change notes volume (GH¢ ' + res.volume.toFixed(2) + ') must equal the expected change due (GH¢ ' + expectedChangeDue.toFixed(2) + ').');
            return;
        }
        
        changeAmountInput.value = res.volume.toFixed(2);
        changeDenosJsonInput.value = JSON.stringify(res.denos);
        changeNoticeSummary.innerHTML = '<span class="text-danger font-monospace fw-bold"><i class="bi bi-arrow-return-left"></i> Change: GH¢ ' + res.volume.toFixed(2) + '</span>';
        
        changeModal.hide();
    });

    const submitBtn = document.querySelector('#agentServiceForm button[type="submit"]');

    const balanceTriggerInputs = [serviceType, document.querySelector('select[name="provider"]'), document.querySelector('select[name="paying_network"]'), document.querySelector('select[name="receiving_network"]'), amountInput, assignedToInput];

    function getSelectedNetwork() {
        const st = serviceType.value;
        if (st === 'Ecash4Cash' || st === 'Ecash4Ecash') {
            var providerEl = document.querySelector('select[name="provider"]');
            var payingEl = document.querySelector('select[name="paying_network"]');
            var receivingEl = document.querySelector('select[name="receiving_network"]');
            return (providerEl && providerEl.value) || (payingEl && payingEl.value) || (receivingEl && receivingEl.value) || '';
        }
        return '';
    }

    function getInputValue(selector) {
        var el = document.querySelector(selector);
        return el ? el.value : '';
    }

    function getInputValueById(id) {
        var el = document.getElementById(id);
        return el ? el.value : '';
    }

    function getCreatorRequiredPayment(st, ct) {
        if (st === 'Cash4Ecash') {
            return ct === 'Internal' ? 'cash' : 'ecash';
        }
        if (st === 'Ecash4Cash') {
            return ct === 'Internal' ? 'ecash' : 'cash';
        }
        if (st === 'Ecash4Ecash') {
            return 'ecash';
        }
        return 'ecash';
    }

    function formatBalanceLine(type, c) {
        if (type === 'cash') {
            return c.cash_available !== null ? `Your Cash: GH¢ ${parseFloat(c.cash_available).toFixed(2)}` : '';
        }
        if (type === 'ecash') {
            return (c.ecash_available !== null && c.ecash_channel_key) ? `Your ${c.ecash_channel_key.toUpperCase()} e-cash: GH¢ ${parseFloat(c.ecash_available).toFixed(2)}` : '';
        }
        return '';
    }

    function setSubmitButtonState(disabled) {
        if (!submitBtn) return;
        submitBtn.disabled = disabled;
        if (disabled) {
            submitBtn.classList.add('disabled');
            submitBtn.setAttribute('aria-disabled', 'true');
        } else {
            submitBtn.classList.remove('disabled');
            submitBtn.removeAttribute('aria-disabled');
        }
    }

    function fetchAssignedBalance() {
        const assignedId = parseInt(assignedToInput.value) || 0;
        const st = serviceType.value;
        const provider = getInputValue('select[name="provider"]');
        const payingNetwork = getInputValue('select[name="paying_network"]');
        const receivingNetwork = getInputValue('select[name="receiving_network"]');
        const amount = parseFloat(amountInput.value) || 0;
        const ct = getInputValueById('customer_type');

        setSubmitButtonState(true);

        if (!assignedId) {
          if (ct === 'External' && currentUserCanSkipAssign) {
            // Admin/ops creating External may proceed without assignee — check creator balances
            assignedBalanceInfo.textContent = 'Checking your balance for this transaction...';
            assignedBalanceInfo.className = 'form-text text-muted mt-2';

            const paramsNoAssign = new URLSearchParams({
              assigned_id: 0,
              creator_id: currentUserId,
              customer_type: getInputValueById('customer_type') || '',
              service_type: st,
              provider: provider,
              paying_network: payingNetwork,
              receiving_network: receivingNetwork,
              amount: amount,
            });

            fetch(`<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/agent_services_balance?${paramsNoAssign.toString()}`, {
              headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
              if (!data || data.status !== 'success') {
                assignedBalanceInfo.textContent = (data && data.message) || 'Unable to load balance information.';
                assignedBalanceInfo.className = 'form-text text-danger mt-2';
                if (submitBtn) submitBtn.disabled = true;
                return;
              }
              // Use creator info to decide sufficiency
              const c = data.creator || {};
              const parts = [];
              if (c.cash_available !== null) parts.push(`Your Cash: GH¢ ${parseFloat(c.cash_available).toFixed(2)}`);
              if (c.ecash_available !== null && c.ecash_channel_key) parts.push(`Your ${c.ecash_channel_key.toUpperCase()} e-cash: GH¢ ${parseFloat(c.ecash_available).toFixed(2)}`);

              const requiredPayment = (data.creator && data.creator.payment_type) || getCreatorRequiredPayment(st, ct);
              let sufficient = true;
              if (requiredPayment === 'cash') {
                sufficient = (c.cash_available !== null && parseFloat(c.cash_available) + 0.0 >= amount);
              } else if (requiredPayment === 'ecash') {
                sufficient = (c.ecash_available !== null && parseFloat(c.ecash_available) + 0.0 >= amount);
              }

              const requiredPaymentLabel = requiredPayment === 'cash' ? 'Cash' : 'E-Cash';
              parts.push(`<strong class="text-muted">Required payment: ${requiredPaymentLabel}</strong>`);
              if (!sufficient) parts.push(`<strong class="text-danger">Your balance is insufficient.</strong>`);

              assignedBalanceInfo.innerHTML = parts.join(' · ');
              assignedBalanceInfo.className = sufficient ? 'form-text text-success mt-2' : 'form-text text-danger mt-2';
              setSubmitButtonState(!sufficient);
            })
            .catch(error => {
              assignedBalanceInfo.textContent = error && error.message ? error.message : 'Unable to load balance information.';
              assignedBalanceInfo.className = 'form-text text-danger mt-2';
              setSubmitButtonState(true);
            });

            return;
          }

          assignedBalanceInfo.textContent = 'Select a user and service type to view balance.';
          assignedBalanceInfo.className = 'form-text text-muted mt-2';
          setSubmitButtonState(true);
          return;
        }

        if (ct === 'Internal') {
          assignedBalanceInfo.textContent = 'Checking both assigned and your balance...';
        } else {
          assignedBalanceInfo.textContent = 'Checking your balance for this transaction...';
        }
        assignedBalanceInfo.className = 'form-text text-muted mt-2';

        const params = new URLSearchParams({
          assigned_id: assignedId,
          creator_id: currentUserId,
          customer_type: getInputValueById('customer_type') || '',
          service_type: st,
          provider: provider,
          paying_network: payingNetwork,
          receiving_network: receivingNetwork,
          amount: amount,
        });

        fetch(`<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/agent_services_balance?${params.toString()}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (!data || data.status !== 'success') {
              assignedBalanceInfo.textContent = (data && data.message) || 'Unable to load balance information.';
              assignedBalanceInfo.className = 'form-text text-danger mt-2';
              return;
            }

            // Show only the creator balance for External transactions; otherwise show assigned and creator balances.
            const parts = [];
            const isExternalTransaction = ct === 'External';
            if (!isExternalTransaction && data.assigned) {
              const a = data.assigned;
              if (a.cash_available !== null) parts.push(`Assigned Cash: GH¢ ${parseFloat(a.cash_available).toFixed(2)}`);
              if (a.ecash_available !== null && a.ecash_channel_key) parts.push(`Assigned ${a.ecash_channel_key.toUpperCase()} e-cash: GH¢ ${parseFloat(a.ecash_available).toFixed(2)}`);
              if (!a.sufficient) parts.push(`<strong class="text-danger">Assigned insufficient for GH¢ ${amount.toFixed(2)}</strong>`);
            }
            if (data.creator) {
              const c = data.creator;
              if (c.cash_available !== null) parts.push(`Your Cash: GH¢ ${parseFloat(c.cash_available).toFixed(2)}`);
              if (c.ecash_available !== null && c.ecash_channel_key) parts.push(`Your ${c.ecash_channel_key.toUpperCase()} e-cash: GH¢ ${parseFloat(c.ecash_available).toFixed(2)}`);

              const stLocal = serviceType.value;
              const requiredPayment = (c.payment_type) || getCreatorRequiredPayment(stLocal, ct);
              let sufficient = true;
              if (requiredPayment === 'cash') {
                sufficient = (c.cash_available !== null && parseFloat(c.cash_available) + 0.0 >= amount);
              } else if (requiredPayment === 'ecash') {
                sufficient = (c.ecash_available !== null && parseFloat(c.ecash_available) + 0.0 >= amount);
              }
              parts.push(`<strong class="text-muted">Required payment: ${requiredPayment === 'cash' ? 'Cash' : 'E-Cash'}</strong>`);
              if (!sufficient) {
                parts.push(`<strong class="text-danger">Your balance is insufficient.</strong>`);
              }
            }

            assignedBalanceInfo.innerHTML = parts.join(' · ');
            const anyInsufficient = parts.some(p => p.includes('insufficient'));
            assignedBalanceInfo.className = anyInsufficient ? 'form-text text-danger mt-2' : 'form-text text-success mt-2';

            // Disable submit unless the relevant balance checks succeed and are sufficient.
            try {
              const isExternalTransaction = ct === 'External';
              let disableSubmit = false;

              if (data && data.creator) {
                const creatorSufficient = data.creator.sufficient === true;
                disableSubmit = !creatorSufficient;
              }

              if (!disableSubmit && !isExternalTransaction && data && data.assigned) {
                const assignedSufficient = data.assigned.sufficient === true;
                disableSubmit = !assignedSufficient;
              }

              setSubmitButtonState(disableSubmit);
            } catch (e) {
              // noop - don't block form if something unexpected happens
            }
        })
        .catch(error => {
            assignedBalanceInfo.textContent = error && error.message ? error.message : 'Unable to load balance information.';
            assignedBalanceInfo.className = 'form-text text-danger mt-2';
            setSubmitButtonState(true);
        });
    }

    balanceTriggerInputs.forEach(input => {
        if (!input) return;
        input.addEventListener('change', fetchAssignedBalance);
        input.addEventListener('input', fetchAssignedBalance);
    });

    // Update option labels to show the balance relevant to what they'd pay
    async function updateAssignOptionsBalances() {
      const opts = Array.from(assignedToInput.options).filter(o => !!o.value);
      const st = serviceType.value;
      const ct = getInputValueById('customer_type') || '';
      const provider = getInputValue('select[name="provider"]');
      const payingNetwork = getInputValue('select[name="paying_network"]');
      const receivingNetwork = getInputValue('select[name="receiving_network"]');
      const amountVal = parseFloat(amountInput.value) || 0;

      // Throttle: small delay between requests to avoid overwhelming server
      for (const opt of opts) {
        const assignedId = parseInt(opt.value) || 0;
        const baseName = opt.getAttribute('data-display-name') || opt.textContent || '';
        if (!assignedId) continue;
        try {
          const params = new URLSearchParams({
            assigned_id: assignedId,
            creator_id: currentUserId,
            customer_type: ct,
            service_type: st,
            provider: provider,
            paying_network: payingNetwork,
            receiving_network: receivingNetwork,
            amount: amountVal,
          });
          const res = await fetch(`<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/agent_services_balance?${params.toString()}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
          const data = await res.json();
          let balanceText = '';
          if (data && data.status === 'success' && data.assigned && ct !== 'External') {
            const a = data.assigned;
            // Decide which balance to surface based on service/customer type
            if (ct === 'Internal' && st === 'Ecash4Cash') {
              // assigned will pay cash
              if (a.cash_available !== null) balanceText = `GH¢ ${parseFloat(a.cash_available).toFixed(2)}`;
            } else {
              // default to ecash availability and channel
              if (a.ecash_available !== null && a.ecash_channel_key) balanceText = `${a.ecash_channel_key.toUpperCase()} GH¢ ${parseFloat(a.ecash_available).toFixed(2)}`;
              else if (a.cash_available !== null) balanceText = `GH¢ ${parseFloat(a.cash_available).toFixed(2)}`;
            }
            if (balanceText) {
              if (!a.sufficient) {
                opt.text = `${baseName} (${balanceText} - insufficient)`;
              } else {
                opt.text = `${baseName} (${balanceText})`;
              }
            } else {
              opt.text = `${baseName}`;
            }
          } else {
            opt.text = `${baseName}`;
          }
        } catch (e) {
          opt.text = `${baseName}`;
        }
        // brief pause
        await new Promise(r => setTimeout(r, 60));
      }
    }

    // Hook updateAssignOptionsBalances to the same triggers
    balanceTriggerInputs.forEach(input => {
      if (!input) return;
      input.addEventListener('change', updateAssignOptionsBalances);
      input.addEventListener('input', updateAssignOptionsBalances);
    });

    if (customerType.value) {
        customerType.dispatchEvent(new Event('change'));
    }

    fetchAssignedBalance();
  } catch (err) {
    try {
      console && console.error && console.error(err);
    } catch (e) {}
    // defensive fallback: ensure service select enabled and basic fields visible
    try {
      var serv = document.getElementById('service_type');
      if (serv) serv.disabled = false;
      var reveal = document.querySelectorAll('.js-agent-details, .js-provider, .js-receiving-network, .js-paying-network, .js-agent-number, .js-amount-field, .js-cash-received-field, .js-proof-image, .js-assign');
      reveal.forEach(function(r){ r.classList.remove('d-none'); });
    } catch (e) {/* swallow */}
  }
});
</script>

<!-- Small resilient fallback: ensure service select and action buttons visible for all users -->
<script>
document.addEventListener('DOMContentLoaded', function(){
  try{
    var cust = document.getElementById('customer_type');
    var serv = document.getElementById('service_type');
    if(cust && serv){
      cust.addEventListener('change', function(){ try{ serv.disabled = false; }catch(e){} });
      // if user already selected before JS attached, ensure enabled
      if(cust.value) serv.disabled = false;
    }

    var submit = document.getElementById('agentServiceSubmit');
    var cancel = document.getElementById('agentServiceCancel');
    if(submit){ submit.style.display = 'inline-block'; submit.style.visibility = 'visible'; submit.classList.remove('d-none'); }
    if(cancel){ cancel.style.display = 'inline-block'; cancel.style.visibility = 'visible'; cancel.classList.remove('d-none'); }
  }catch(e){/* swallow to avoid blocking other scripts */}
});
</script>
