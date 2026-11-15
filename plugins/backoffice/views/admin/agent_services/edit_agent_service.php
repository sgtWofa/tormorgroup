<?php
$session = get_session();
$currentUserId = (int)($session->id ?? 0);
$status = trim((string)($row->status ?? 'Pending'));
$statusLower = strtolower($status);
$stage = (int)($row->stage ?? 1);
$ct = $row->customer_type ?? '';
$st = $row->service_type ?? '';
$meta = json_decode($row->metadata ?? '{}', true) ?: [];

$isAdminOrOps = backoffice_user_is_admin($session) || user_can('is_operations_manager');
$isAssignee = (int)($row->assigned_to ?? 0) === $currentUserId;
$isProcessorMode = $isProcessorMode ?? false;
$canEditStage2 = ($stage === 2 && $statusLower === 'pending' && !$isProcessorMode && (
  ($ct !== 'Internal' && ($isAdminOrOps || $currentUserId === (int)($row->creator_id ?? 0)))
  || ($ct === 'Internal' && ($isAssignee || $isAdminOrOps || $currentUserId === (int)($row->creator_id ?? 0)))
));
$isStage2Processor = ($stage === 2 && $statusLower === 'pending' && $isProcessorMode && ($isAssignee || $isAdminOrOps || $currentUserId === (int)($row->creator_id ?? 0)));
$formAction = ($stage === 1 && $statusLower === 'pending') || $canEditStage2 ? 'agent_services_update' : ($stage == 2 && $isProcessorMode ? 'agent_services_confirm' : ($stage == 2 ? 'agent_services_confirm' : 'agent_services_complete'));

// Fetch assignable users for reassignment UI when needed
$assignableUsers = [];
if ($isAdminOrOps && function_exists('\BackOfficeCashdrawer\getAssignableUsersToday')) {
  $assignableUsers = \BackOfficeCashdrawer\getAssignableUsersToday();
}

// Get creator SIM assignments (passed from plugin.php handler)
$creatorSimMap = $agentServiceCreatorSimMap ?? [];
$creatorId = (string)(int)($row->creator_id ?? 0);
$creatorSims = $creatorSimMap[$creatorId] ?? [];

// Find SIM details for a specific network
function findSimForNetwork(array $sims, string $network): ?array {
    $networkUpper = strtoupper(trim($network));
    foreach ($sims as $sim) {
        $simNet = strtoupper(trim($sim['network'] ?? ''));
        // Normalize common network names
        $normalizeNet = function($n) {
            $n = strtoupper(trim($n));
            if ($n === 'G-MONEY' || $n === 'GMONEY') return 'GMONEY';
            if ($n === 'GHANAPAY' || $n === 'GHANA-PAY') return 'GHANAPAY';
            return $n;
        };
        if ($normalizeNet($simNet) === $normalizeNet($networkUpper)) {
            return $sim;
        }
    }
    return null;
}

// Determine the network for which to show creator's SIM details
$networkForSim = '';
if ($ct === 'Internal') {
    if ($st === 'Cash4Ecash') $networkForSim = $row->receiving_network ?? '';
    elseif ($st === 'Ecash4Ecash') $networkForSim = $row->receiving_network ?? $row->paying_network ?? '';
    elseif ($st === 'Ecash4Cash') $networkForSim = $row->paying_network ?? '';
}
$creatorSimForNetwork = $networkForSim ? findSimForNetwork($creatorSims, $networkForSim) : null;
?>
<div class="backoffice-shell col-md-10 mx-auto">
  <div class="bo-hero mb-4">
    <div class="bo-hero-title"><?= esc(($formAction === 'agent_services_update') ? 'Edit Stage ' . $stage : 'Process Stage ' . $stage) ?></div>
    <p class="bo-hero-sub mb-0"><?=esc($ct)?> &mdash; <?=esc($st)?></p>
  </div>

  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">

      <!-- Transaction Summary -->
      <div class="alert alert-light border mb-4">
        <div class="row g-2">
          <div class="col-sm-6">
            <span class="text-muted small text-uppercase">Transaction #</span>
            <div class="fw-bold"><?=esc($row->id)?></div>
          </div>
          <div class="col-sm-6">
            <span class="text-muted small text-uppercase">Amount</span>
            <div class="fw-bold text-success">GH&cent; <?=number_format((float)$row->amount, 2)?></div>
          </div>
          <?php if (!empty($row->provider) || !empty($row->receiving_network) || !empty($row->paying_network)): ?>
          <div class="col-sm-6">
            <span class="text-muted small text-uppercase">Network/Provider</span>
            <div class="fw-semibold"><?=esc($row->provider ?: $row->receiving_network ?: $row->paying_network)?></div>
          </div>
          <?php endif; ?>
          <?php if (!empty($row->agent_name)): ?>
          <div class="col-sm-6">
            <span class="text-muted small text-uppercase">Agent</span>
            <div class="fw-semibold"><?=esc($row->agent_name)?> <?=!empty($row->business_name)?'('.esc($row->business_name).')':''?></div>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <form id="agentServiceForm" method="post" enctype="multipart/form-data" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/<?= $isProcessorMode ? 'process_agent_service' : 'edit_agent_service' ?>/<?=$row->id?>">
        <?=csrf()?>
        <input type="hidden" name="action" value="<?=esc($formAction)?>">
        
        <?php if ($stage === 1 && $statusLower === 'pending'): ?>
          <h6 class="border-bottom pb-2 mb-3 text-primary"><i class="bi bi-pen me-2"></i>Edit Pending Transaction</h6>
          <div class="row g-3 mb-4">
            <div class="col-md-4">
              <label class="form-label fw-semibold">Customer Type</label>
              <select name="customer_type" class="form-select" required>
                <option value="External" <?=($row->customer_type === 'External' ? 'selected' : '')?>>External</option>
                <option value="Internal" <?=($row->customer_type === 'Internal' ? 'selected' : '')?>>Internal</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Service Type</label>
              <select name="service_type" class="form-select" required>
                <option value="Cash4Ecash" <?=($row->service_type === 'Cash4Ecash' ? 'selected' : '')?>>Cash4Ecash</option>
                <option value="Ecash4Cash" <?=($row->service_type === 'Ecash4Cash' ? 'selected' : '')?>>Ecash4Cash</option>
                <option value="Ecash4Ecash" <?=($row->service_type === 'Ecash4Ecash' ? 'selected' : '')?>>Ecash4Ecash</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Amount</label>
              <input type="number" step="0.01" min="0" name="amount" class="form-control" value="<?=number_format((float)$row->amount, 2, '.', '')?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Provider</label>
              <input type="text" name="provider" class="form-control" value="<?=esc($row->provider ?? '')?>">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Paying Network</label>
              <input type="text" name="paying_network" class="form-control" value="<?=esc($row->paying_network ?? '')?>">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Receiving Network</label>
              <input type="text" name="receiving_network" class="form-control" value="<?=esc($row->receiving_network ?? '')?>">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Agent Name</label>
              <input type="text" name="agent_name" class="form-control" value="<?=esc($row->agent_name ?? '')?>">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Agent Contact</label>
              <input type="text" name="agent_contact" class="form-control" value="<?=esc($row->agent_contact ?? '')?>">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Business Name</label>
              <input type="text" name="business_name" class="form-control" value="<?=esc($row->business_name ?? '')?>">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Agent Number</label>
              <input type="text" name="agent_number" class="form-control" value="<?=esc($row->agent_number ?? '')?>">
            </div>
          </div>
        <?php endif; ?>

        <?php if ($canEditStage2): ?>
          <h6 class="border-bottom pb-2 mb-3 text-primary"><i class="bi bi-pen me-2"></i>Edit Pending Stage 2</h6>
          <div class="row g-3 mb-4">
            <div class="col-md-4">
              <label class="form-label fw-semibold">Customer Type</label>
              <select id="customer_type" name="customer_type" class="form-select" required>
                <option value="External" <?=($row->customer_type === 'External' ? 'selected' : '')?>>External</option>
                <option value="Internal" <?=($row->customer_type === 'Internal' ? 'selected' : '')?>>Internal</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Service Type</label>
              <select id="service_type" name="service_type" class="form-select" required>
                <option value="Cash4Ecash" <?=($row->service_type === 'Cash4Ecash' ? 'selected' : '')?>>Cash4Ecash</option>
                <option value="Ecash4Cash" <?=($row->service_type === 'Ecash4Cash' ? 'selected' : '')?>>Ecash4Cash</option>
                <option value="Ecash4Ecash" <?=($row->service_type === 'Ecash4Ecash' ? 'selected' : '')?>>Ecash4Ecash</option>
              </select>
            </div>
          </div>

          <div id="editDynamicFields" class="row g-3 mb-4">
            <div class="col-md-12 js-agent-details d-none">
              <h6 class="border-bottom pb-2 mb-3 mt-2">Agent Details</h6>
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label">Agent Name</label>
                  <input type="text" class="form-control" name="agent_name" value="<?=esc($row->agent_name ?? '')?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Contact Number</label>
                  <input type="text" class="form-control" name="agent_contact" value="<?=esc($row->agent_contact ?? '')?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Business Name</label>
                  <input type="text" class="form-control" name="business_name" value="<?=esc($row->business_name ?? '')?>">
                </div>
              </div>
            </div>

            <div class="col-md-6 js-provider d-none">
              <label class="form-label">Service Provider</label>
              <select class="form-select" name="provider">
                <option value="">--- Select ---</option>
                <?php foreach(['Mtn', 'Telecel', 'AT', 'Gmoney', 'Ghanapay', 'Fidelity Bank', 'Ecobank', 'Access Bank', 'GCB Bank', 'GtBank', 'Apex Nafana Bank', 'Apex Suma Bank', 'Stanbic Bank', 'First Bank Ghana (FBG)', 'First National Bank (FNB)', 'Zeepay'] as $prov): ?>
                  <option value="<?=$prov?>" <?=($row->provider === $prov ? 'selected' : '')?>><?=$prov?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-6 js-receiving-network d-none">
              <label class="form-label">Receiving Network</label>
              <select class="form-select" name="receiving_network">
                <option value="">--- Select ---</option>
                <?php foreach(['Mtn', 'Telecel', 'AT', 'Zeepay', 'Ghanapay', 'G-money'] as $prov): ?>
                  <option value="<?=$prov?>" <?=($row->receiving_network === $prov ? 'selected' : '')?>><?=$prov?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-6 js-paying-network d-none">
              <label class="form-label">Paying Network</label>
              <select class="form-select" name="paying_network">
                <option value="">--- Select ---</option>
                <?php foreach(['Mtn', 'Telecel', 'AT', 'Zeepay', 'Ghanapay', 'G-money'] as $prov): ?>
                  <option value="<?=$prov?>" <?=($row->paying_network === $prov ? 'selected' : '')?>><?=$prov?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-6 js-agent-number d-none">
              <label class="form-label js-agent-number-label">Paying / Receiving Phone</label>
              <input type="text" class="form-control" name="agent_number" value="<?=esc($row->agent_number ?? '')?>">
            </div>

            <div class="col-md-6 js-amount-field d-none">
              <label class="form-label js-amount-label">Amount / Load</label>
              <input type="number" step="0.01" min="0" class="form-control" name="amount" id="amount" value="<?=number_format((float)$row->amount, 2, '.', '')?>" required>
            </div>

            <div class="col-md-6 js-cash-received-field d-none">
              <label class="form-label text-success fw-semibold"><i class="fa-solid fa-money-bill-wave me-1"></i> Cash Received</label>
              <div class="input-group cursor-pointer" id="cashReceivedTrigger">
                <span class="input-group-text text-warning"><i class="fa-solid fa-calculator"></i></span>
                <input type="number" step="0.01" min="0" class="form-control cursor-pointer" name="cash_received" id="cash_received" placeholder="Click to count cash" readonly value="<?= ($row->cash_received > 0) ? number_format((float)$row->cash_received, 2, '.', '') : '' ?>">
              </div>
              <small class="text-muted" id="changeNoticeSummary"></small>
            </div>

            <?php if ($isAdminOrOps): ?>
            <div class="col-md-6 js-assign d-none">
              <label class="form-label">Assign To</label>
              <select class="form-select" name="assigned_to" id="assigned_to">
                <option value="0">-- Keep current (<?=esc($row->assigned_to ?: 'Unassigned')?>) --</option>
                <?php foreach ($assignableUsers as $u): ?>
                  <option value="<?= (int)$u->id ?>" <?= ((int)$row->assigned_to === (int)$u->id) ? 'selected' : '' ?> data-display-name="<?=esc(trim($u->first_name . ' ' . $u->last_name))?>" data-roles="<?=esc($u->roles[0] ?? '')?>"><?=esc(trim($u->first_name . ' ' . $u->last_name))?> (<?=esc($u->roles[0] ?? '')?>)</option>
                <?php endforeach; ?>
              </select>
              <div class="form-text text-muted mt-2">Admins/Operations can reassign who will process this payment.</div>
            </div>
            <?php endif; ?>
            <div class="col-12">
              <div id="assignedBalanceInfo" class="form-text text-muted mt-2">Select a user and service type to view balance.</div>
            </div>
          </div>

          <script>
          document.addEventListener('DOMContentLoaded', function() {
            try {
              const customerType = document.getElementById('customer_type');
              const serviceType = document.getElementById('service_type');
              const allFields = document.querySelectorAll('#editDynamicFields > div');
              const fAgentDetails = document.querySelector('.js-agent-details');
              const fProvider = document.querySelector('.js-provider');
              const fReceivingNet = document.querySelector('.js-receiving-network');
              const fPayingNet = document.querySelector('.js-paying-network');
              const fAgentNumber = document.querySelector('.js-agent-number');
              const fAmountField = document.querySelector('.js-amount-field');
              const fCashReceivedField = document.querySelector('.js-cash-received-field');
              const fAssign = document.querySelector('.js-assign');
              const amountLabel = document.querySelector('.js-amount-label');
              const cashReceivedLabel = document.querySelector('.js-cash-received-field label');
              const assignedToInput = document.getElementById('assigned_to');
              const amountInput = document.getElementById('amount');
              const assignedBalanceInfo = document.getElementById('assignedBalanceInfo');
              const currentUserCanSkipAssign = <?= $isAdminOrOps ? 'true' : 'false' ?>;
              const currentUserId = <?= (int)$currentUserId ?>;
              const assignedUserFallback = <?= (int)($row->assigned_to ?? 0) ?>;

              function hideAll() {
                allFields.forEach(function(f){ f.classList.add('d-none'); });
                if (assignedToInput) assignedToInput.required = false;
              }

              function getAssignedId() {
                if (assignedToInput) {
                  const selected = parseInt(assignedToInput.value, 10) || 0;
                  return selected || assignedUserFallback || 0;
                }
                return assignedUserFallback || 0;
              }

              function getInputValue(selector) {
                const el = document.querySelector(selector);
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

              function setSubmitButtonState(disabled) {
                const submitBtn = document.querySelector('#agentServiceForm button[type="submit"]');
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

              function updateBalanceInfoText(text, success) {
                if (!assignedBalanceInfo) return;
                assignedBalanceInfo.innerHTML = text;
                assignedBalanceInfo.className = success ? 'form-text text-success mt-2' : 'form-text text-danger mt-2';
              }

              function fetchAssignedBalance() {
                if (!assignedBalanceInfo) return;
                const assignedId = getAssignedId();
                const st = serviceType.value;
                const provider = getInputValue('select[name="provider"]');
                const payingNetwork = getInputValue('select[name="paying_network"]');
                const receivingNetwork = getInputValue('select[name="receiving_network"]');
                const amount = parseFloat(amountInput ? amountInput.value : 0) || 0;
                const ct = customerType.value;

                setSubmitButtonState(true);

                if (!assignedId) {
                  if (ct === 'External' && currentUserCanSkipAssign) {
                    assignedBalanceInfo.textContent = 'Checking your balance for this transaction...';
                    assignedBalanceInfo.className = 'form-text text-muted mt-2';
                    const paramsNoAssign = new URLSearchParams({
                      assigned_id: 0,
                      creator_id: currentUserId,
                      customer_type: ct,
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
                        setSubmitButtonState(true);
                        return;
                      }
                      const c = data.creator || {};
                      const parts = [];
                      if (c.cash_available !== null) parts.push(`Your Cash: GH¢ ${parseFloat(c.cash_available).toFixed(2)}`);
                      if (c.ecash_available !== null && c.ecash_channel_key) parts.push(`Your ${c.ecash_channel_key.toUpperCase()} e-cash: GH¢ ${parseFloat(c.ecash_available).toFixed(2)}`);
                      const requiredPayment = (c.payment_type) || getCreatorRequiredPayment(st, ct);
                      let sufficient = true;
                      if (requiredPayment === 'cash') {
                        sufficient = (c.cash_available !== null && parseFloat(c.cash_available) >= amount);
                      } else if (requiredPayment === 'ecash') {
                        sufficient = (c.ecash_available !== null && parseFloat(c.ecash_available) >= amount);
                      }
                      parts.push(`<strong class="text-muted">Required payment: ${requiredPayment === 'cash' ? 'Cash' : 'E-Cash'}</strong>`);
                      if (!sufficient) parts.push('<strong class="text-danger">Your balance is insufficient.</strong>');
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

                assignedBalanceInfo.textContent = ct === 'Internal' ? 'Checking both assigned and your balance...' : 'Checking your balance for this transaction...';
                assignedBalanceInfo.className = 'form-text text-muted mt-2';
                const params = new URLSearchParams({
                  assigned_id: assignedId,
                  creator_id: currentUserId,
                  customer_type: ct,
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

                  const parts = [];
                  const isExternalTransaction = ct === 'External';
                  if (!isExternalTransaction && data.assigned) {
                    const a = data.assigned;
                    if (a.cash_available !== null) parts.push(`Assigned Cash: GH¢ ${parseFloat(a.cash_available).toFixed(2)}`);
                    if (a.ecash_available !== null && a.ecash_channel_key) parts.push(`Assigned ${a.ecash_channel_key.toUpperCase()} e-cash: GH¢ ${parseFloat(a.ecash_available).toFixed(2)}`);
                    if (!a.sufficient) parts.push('<strong class="text-danger">Assigned insufficient for GH¢ ' + amount.toFixed(2) + '</strong>');
                  }
                  if (data.creator) {
                    const c = data.creator;
                    if (c.cash_available !== null) parts.push(`Your Cash: GH¢ ${parseFloat(c.cash_available).toFixed(2)}`);
                    if (c.ecash_available !== null && c.ecash_channel_key) parts.push(`Your ${c.ecash_channel_key.toUpperCase()} e-cash: GH¢ ${parseFloat(c.ecash_available).toFixed(2)}`);
                    const requiredPayment = (c.payment_type) || getCreatorRequiredPayment(st, ct);
                    parts.push(`<strong class="text-muted">Required payment: ${requiredPayment === 'cash' ? 'Cash' : 'E-Cash'}</strong>`);
                    let creatorSufficient = true;
                    if (requiredPayment === 'cash') {
                      creatorSufficient = (c.cash_available !== null && parseFloat(c.cash_available) >= amount);
                    } else if (requiredPayment === 'ecash') {
                      creatorSufficient = (c.ecash_available !== null && parseFloat(c.ecash_available) >= amount);
                    }
                    if (!creatorSufficient) {
                      parts.push('<strong class="text-danger">Your balance is insufficient.</strong>');
                    }
                  }
                  assignedBalanceInfo.innerHTML = parts.join(' · ');
                  const anyInsufficient = parts.some(p => p.includes('insufficient'));
                  assignedBalanceInfo.className = anyInsufficient ? 'form-text text-danger mt-2' : 'form-text text-success mt-2';

                  let disableSubmit = false;
                  if (data.creator && data.creator.sufficient !== true) {
                    disableSubmit = true;
                  }
                  if (!disableSubmit && !isExternalTransaction && data.assigned && data.assigned.sufficient !== true) {
                    disableSubmit = true;
                  }
                  setSubmitButtonState(disableSubmit);
                })
                .catch(error => {
                  assignedBalanceInfo.textContent = error && error.message ? error.message : 'Unable to load balance information.';
                  assignedBalanceInfo.className = 'form-text text-danger mt-2';
                  setSubmitButtonState(true);
                });
              }

              const balanceTriggerInputs = [serviceType, document.querySelector('select[name="provider"]'), document.querySelector('select[name="paying_network"]'), document.querySelector('select[name="receiving_network"]'), amountInput, assignedToInput];
              balanceTriggerInputs.forEach(input => {
                if (!input) return;
                input.addEventListener('change', fetchAssignedBalance);
                input.addEventListener('input', fetchAssignedBalance);
              });

              async function updateAssignOptionsBalances() {
                if (!assignedToInput) return;
                const opts = Array.from(assignedToInput.options).filter(o => !!o.value);
                const st = serviceType.value;
                const ct = customerType.value;
                const provider = getInputValue('select[name="provider"]');
                const payingNetwork = getInputValue('select[name="paying_network"]');
                const receivingNetwork = getInputValue('select[name="receiving_network"]');
                const amountVal = parseFloat(amountInput ? amountInput.value : 0) || 0;
                for (const opt of opts) {
                  const assignedId = parseInt(opt.value, 10) || 0;
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
                      if (ct === 'Internal' && st === 'Ecash4Cash') {
                        if (a.cash_available !== null) balanceText = `GH¢ ${parseFloat(a.cash_available).toFixed(2)}`;
                      } else {
                        if (a.ecash_available !== null && a.ecash_channel_key) balanceText = `${a.ecash_channel_key.toUpperCase()} GH¢ ${parseFloat(a.ecash_available).toFixed(2)}`;
                        else if (a.cash_available !== null) balanceText = `GH¢ ${parseFloat(a.cash_available).toFixed(2)}`;
                      }
                      if (balanceText) {
                        opt.text = `${baseName} (${balanceText}${a.sufficient ? '' : ' - insufficient'})`;
                      } else {
                        opt.text = `${baseName}`;
                      }
                    } else {
                      opt.text = `${baseName}`;
                    }
                  } catch (e) {
                    opt.text = `${baseName}`;
                  }
                  await new Promise(r => setTimeout(r, 120));
                }
              }

              balanceTriggerInputs.forEach(input => {
                if (!input) return;
                input.addEventListener('change', updateAssignOptionsBalances);
                input.addEventListener('input', updateAssignOptionsBalances);
              });

              if (customerType.value) {
                customerType.dispatchEvent(new Event('change'));
              }

              fetchAssignedBalance();


              function updateFields() {
                hideAll();
                const ct = customerType.value;
                const st = serviceType.value;
                if (!ct || !st) return;

                fAgentNumber.classList.remove('d-none');
                assignedToInput && fAssign.classList.remove('d-none');
                assignedToInput && (assignedToInput.required = !(ct === 'External' && currentUserCanSkipAssign));

                if (ct === 'External') {
                  fAgentDetails.classList.remove('d-none');
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
                    fCashReceivedField.classList.remove('d-none');
                    amountLabel.textContent = 'Cash Needed';
                    if (cashReceivedLabel) {
                      cashReceivedLabel.innerHTML = '<i class="fa-solid fa-money-bill-wave me-1"></i> Cash Received';
                    }
                  }
                }
              }

              customerType.addEventListener('change', function() {
                serviceType.disabled = !this.value;
                updateFields();
              });
              serviceType.addEventListener('change', updateFields);

              if (customerType.value) {
                serviceType.disabled = false;
                updateFields();
              }
            } catch (err) {
              console && console.error && console.error(err);
            }
          });
          </script>
        <?php endif; ?>

        <?php /* ============================================================
               STAGE 2 / STAGE 3 SECTIONS (Assigned User's Job / Creator Completion)
               ============================================================ */ ?>

        <?php if ($stage !== 1 && !$canEditStage2): ?>
          <?php if ($ct === 'External' && $st === 'Cash4Ecash' && $stage == 2): ?>
            <!-- External Cash4Ecash - Stage 2 (Assignee reviews & sets commission) -->
          <h6 class="border-bottom pb-2 mb-3 text-primary"><i class="bi bi-person-check me-2"></i>Stage 2: Review & Set Commission</h6>
          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Type of Customer</label>
              <select name="type_of_customer" class="form-select" id="type_of_customer" required>
                <option value="">-- Select --</option>
                <option value="Other Agent" <?= (($meta['type_of_customer'] ?? '') === 'Other Agent') ? 'selected' : '' ?>>Other Agent</option>
                <option value="Bulk Sending" <?= (($meta['type_of_customer'] ?? '') === 'Bulk Sending') ? 'selected' : '' ?>>Bulk Sending</option>
              </select>
            </div>
            <div class="col-md-6 d-none js-commission-amount">
              <label class="form-label">Commission Amount (GH&cent;)</label>
              <input type="number" step="0.01" min="0" name="commission_amount" class="form-control" 
                value="<?=esc(number_format((float)($row->commission_amount ?? 0), 2))?>">
              <small class="text-muted">Leave 0 if no commission applies.</small>
            </div>
          </div>
          <script>
          document.getElementById('type_of_customer').addEventListener('change', function() {
            document.querySelector('.js-commission-amount').classList.toggle('d-none', this.value !== 'Bulk Sending');
          });
          // Init on page load
          if (document.getElementById('type_of_customer').value === 'Bulk Sending') {
            document.querySelector('.js-commission-amount').classList.remove('d-none');
          }
          </script>

        <?php elseif ($ct === 'External' && $st === 'Cash4Ecash' && $stage == 3): ?>
          <!-- External Cash4Ecash - Stage 3 (Creator completes) -->
          <h6 class="border-bottom pb-2 mb-3 text-primary"><i class="bi bi-check2-circle me-2"></i>Stage 3: Complete Transaction</h6>
          
          <?php if ((float)($row->commission_amount ?? 0) > 0): ?>
          <div class="alert alert-info mb-3">
            <div class="d-flex justify-content-between align-items-center">
              <span><i class="bi bi-info-circle me-2"></i>Commission from Stage 2:</span>
              <strong class="fs-5">GH&cent; <?=number_format((float)$row->commission_amount, 2)?></strong>
            </div>
          </div>
          <?php endif; ?>

          <div class="row g-3 mb-4">
            <?php if ((float)($row->commission_amount ?? 0) > 0): ?>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Commission Type</label>
              <select name="commission_type" class="form-select" id="commTypeSelect" required>
                <option value="">-- Select --</option>
                <option value="+fee" <?= ($row->commission_type === '+fee') ? 'selected' : '' ?>>+ Fee (Add commission to amount payable)</option>
                <option value="-fee" <?= ($row->commission_type === '-fee') ? 'selected' : '' ?>>- Fee (Deduct commission from amount)</option>
              </select>
              <div id="commSummary" class="mt-2 small text-muted"></div>
            </div>
            <?php endif; ?>
            
            <div class="col-md-6">
              <label class="form-label fw-semibold text-success"><i class="fa-solid fa-money-bill-wave me-1"></i> Cash Received (Amount Counter)</label>
              <div class="input-group cursor-pointer" id="cashReceivedTrigger">
                <span class="input-group-text text-warning"><i class="fa-solid fa-calculator"></i></span>
                <input type="number" step="0.01" class="form-control cursor-pointer" 
                  id="calculated_amount" name="cash_received" 
                  placeholder="Click to count cash" readonly required
                  value="<?= ($row->cash_received > 0) ? number_format((float)$row->cash_received, 2) : '' ?>">
              </div>
              <small class="text-muted" id="changeNoticeSummary"></small>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Agent Number / Account / Shortcode</label>
              <input type="text" name="agent_number" class="form-control" 
                value="<?=esc($row->agent_number)?>" placeholder="e.g. 0244000000" required>
            </div>
            <div class="col-md-12">
              <label class="form-label fw-semibold">Upload Proof Picture</label>
              <input type="file" name="proof_image" class="form-control js-agent-upload-input" accept="image/*" required data-existing-src="<?=(!empty($row->proof_image) ? esc(get_image($row->proof_image)) : '')?>">
              <small class="text-muted">Upload screenshot or photo of the transaction.</small>
            </div>
          </div>
          <script>
          (function(){
            var comm = <?=(float)($row->commission_amount ?? 0)?>;
            var amount = <?=(float)$row->amount?>;
            var commTypeSelect = document.getElementById('commTypeSelect');
            var commSummary = document.getElementById('commSummary');
            function updateSummary(){
              if (!commSummary || !commTypeSelect) return;
              var type = commTypeSelect ? commTypeSelect.value : '';
              if(type === '+fee'){
                commSummary.innerHTML = '<span class="text-info"><i class="bi bi-calculator me-1"></i>Amount payable: GH¢' + (amount+comm).toFixed(2) + ' | E-cash sent: GH¢' + amount.toFixed(2) + '</span>';
              } else if(type === '-fee'){
                commSummary.innerHTML = '<span class="text-warning"><i class="bi bi-calculator me-1"></i>Cash IN: GH¢' + amount.toFixed(2) + ' | E-cash OUT: GH¢' + (amount-comm).toFixed(2) + '</span>';
              } else {
                commSummary.innerHTML = '';
              }
            }
            if (commTypeSelect) commTypeSelect.addEventListener('change', updateSummary);
            updateSummary();
          })();
          </script>

        <?php elseif ($ct === 'External' && $st === 'Ecash4Cash' && $stage == 2): ?>
          <!-- External Ecash4Cash - Stage 2 (Assigned confirms ecash receipt) -->
          <h6 class="border-bottom pb-2 mb-3 text-primary"><i class="bi bi-person-check me-2"></i>Stage 2: Confirm E-Cash Receipt</h6>
          <div class="row g-3 mb-4">
            <div class="col-md-12">
              <div class="alert alert-info">
                <p class="mb-2">Confirm that the incoming e-cash has been received on the selected network.</p>
                <p class="mb-0"><strong>Upload proof of the e-cash receipt before confirming.</strong></p>
              </div>
            </div>
            <div class="col-md-12">
              <label class="form-label fw-semibold">Upload E-Cash Receipt Proof</label>
              <input type="file" name="proof_image" class="form-control js-agent-upload-input" accept="image/*" required data-existing-src="<?=(!empty($row->proof_image) ? esc(get_image($row->proof_image)) : '')?>">
              <small class="text-muted">Upload the screenshot or photo showing the incoming e-cash receipt.</small>
              </div>
            </div>
          </div>

        <?php elseif ($ct === 'External' && $st === 'Ecash4Cash' && $stage == 3): ?>
          <!-- External Ecash4Cash - Stage 3 (Creator enters amount paid) -->
          <h6 class="border-bottom pb-2 mb-3 text-primary"><i class="bi bi-check2-circle me-2"></i>Stage 3: Complete Transaction</h6>
          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label class="form-label fw-semibold text-success"><i class="fa-solid fa-money-bill-wave me-1"></i> Cash Paid (Amount Counter)</label>
              <div class="input-group cursor-pointer" id="cashReceivedTrigger">
                <span class="input-group-text text-warning"><i class="fa-solid fa-calculator"></i></span>
                <input type="number" step="0.01" class="form-control cursor-pointer" 
                  id="calculated_amount" name="cash_received" 
                  placeholder="Click to count cash" readonly required
                  value="<?= ($row->cash_received > 0) ? number_format((float)$row->cash_received, 2) : '' ?>">
              </div>
              <small class="text-muted" id="changeNoticeSummary"></small>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">E-Cash Receipt Proof</label>
              <?php if (!empty($row->proof_image)): ?>
                <div>
                  <a href="<?=esc(get_image($row->proof_image))?>" target="_blank" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-image me-1"></i>View Stage 2 Receipt Proof
                  </a>
                </div>
              <?php else: ?>
                <div class="alert alert-warning mb-0">No stage 2 receipt proof was uploaded.</div>
              <?php endif; ?>
            </div>
          </div>

        <?php elseif ($ct === 'External' && $st === 'Ecash4Ecash' && $stage == 2): ?>
          <!-- External Ecash4Ecash - Stage 2 (Assigned approves transaction) -->
          <h6 class="border-bottom pb-2 mb-3 text-primary"><i class="bi bi-person-check me-2"></i>Stage 2: Approve Transaction</h6>
          <div class="row g-3 mb-4">
            <div class="col-md-12">
              <div class="alert alert-info">
                <p class="mb-2">Review the creator's request and confirm approval for stage 3 completion by the creator.</p>
                <p class="mb-0"><strong>Creator will upload proof and complete the transfer in Stage 3.</strong></p>
              </div>
            </div>
            <div class="col-md-12">
              <label class="form-label fw-semibold">Upload Payment Proof</label>
              <input type="file" name="proof_image" class="form-control js-agent-upload-input" accept="image/*" required data-existing-src="<?=(!empty($row->proof_image) ? esc(get_image($row->proof_image)) : '')?>">
              <small class="text-muted">Upload proof that the payment / e-cash transfer was sent.</small>
            </div>
          </div>

        <?php elseif ($ct === 'External' && $st === 'Ecash4Ecash' && $stage == 3): ?>
          <!-- External Ecash4Ecash - Stage 3 (Creator completes) -->
          <h6 class="border-bottom pb-2 mb-3 text-primary"><i class="bi bi-send me-2"></i>Stage 3: Complete Transfer</h6>
          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Pay To Phone Number</label>
              <input type="text" name="agent_number" class="form-control" 
                value="<?=esc($row->agent_number)?>" placeholder="e.g. 0244000000" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Load (Amount to Send)</label>
              <input type="text" class="form-control" readonly value="GH¢ <?=number_format((float)$row->amount, 2)?>">
              <small class="text-muted">Auto-filled from amount received.</small>
            </div>
            <div class="col-md-12">
              <label class="form-label fw-semibold">Upload Completion Proof</label>
              <input type="file" name="proof_image" class="form-control js-agent-upload-input" accept="image/*" required data-existing-src="<?=(!empty($row->proof_image) ? esc(get_image($row->proof_image)) : '')?>">
              <small class="text-muted">Upload screenshot or photo confirming the e-cash transfer completion.</small>
            </div>
          </div>

        <?php elseif ($ct === 'Internal' && $st === 'Cash4Ecash' && $stage == 2): ?>
          <!-- Internal Cash4Ecash - Stage 2 (Assignee cross-checks denominations & provides SIM) -->
          <h6 class="border-bottom pb-2 mb-3 text-primary"><i class="bi bi-calculator me-2"></i>Stage 2: Cross-check Denominations</h6>

          <?php 
          $denos = json_decode($row->denominations_json ?? '[]', true);
          if (!empty($denos)): ?>
          <div class="alert alert-light border mb-3">
            <h6 class="mb-2 fw-semibold"><i class="bi bi-list-check me-1"></i>Denominations from Creator (verify against physical cash)</h6>
            <div class="row g-1">
              <?php foreach ($denos as $d): ?>
              <div class="col-6 col-md-4">
                <div class="d-flex justify-content-between small border rounded px-2 py-1">
                  <span>GH¢<?=esc($d['denomination'])?> &times; <?=esc($d['count'])?></span>
                  <strong>GH¢<?=number_format((float)($d['volume']),2)?></strong>
                </div>
              </div>
              <?php endforeach; ?>
              <div class="col-12 mt-2">
                <div class="d-flex justify-content-between fw-bold border-top pt-1">
                  <span>Total:</span>
                  <span class="text-success">GH¢ <?=number_format(array_sum(array_column($denos,'volume')),2)?></span>
                </div>
              </div>
            </div>
          </div>
          <?php endif; ?>

          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label class="form-label fw-semibold text-success"><i class="fa-solid fa-money-bill-wave me-1"></i> Cross-check Received Cash</label>
              <div class="input-group cursor-pointer" id="cashReceivedTrigger">
                <span class="input-group-text text-warning"><i class="fa-solid fa-calculator"></i></span>
                <input type="number" step="0.01" class="form-control cursor-pointer" 
                  id="calculated_amount" name="cash_received" 
                  placeholder="Click to count received cash" readonly required
                  value="<?= ($row->cash_received > 0) ? number_format((float)$row->cash_received, 2) : '' ?>">
              </div>
              <small class="text-muted">Count the physical notes and confirm total matches creator's amount.</small>
            </div>
          </div>

          <?php if ($creatorSimForNetwork): ?>
          <div class="alert alert-success mb-3">
            <h6 class="mb-2"><i class="bi bi-sim me-1"></i>Creator's <?=esc($networkForSim)?> Details (for e-cash transfer)</h6>
            <?php if (!empty($creatorSimForNetwork['phone_number'])): ?>
              <div><strong>Phone Number:</strong> <?=esc($creatorSimForNetwork['phone_number'])?></div>
            <?php endif; ?>
            <?php if (!empty($creatorSimForNetwork['till_number'])): ?>
              <div><strong>Till Number:</strong> <?=esc($creatorSimForNetwork['till_number'])?></div>
            <?php endif; ?>
            <?php if (!empty($creatorSimForNetwork['shortcode'])): ?>
              <div><strong>Shortcode:</strong> <?=esc($creatorSimForNetwork['shortcode'])?></div>
            <?php endif; ?>
            <?php if (!empty($creatorSimForNetwork['account_number'])): ?>
              <div><strong>Account Number:</strong> <?=esc($creatorSimForNetwork['account_number'])?></div>
            <?php endif; ?>
            <?php if (!empty($creatorSimForNetwork['notes'])): ?>
              <div class="mt-1 small text-muted"><?=esc($creatorSimForNetwork['notes'])?></div>
            <?php endif; ?>
          </div>
          <?php elseif ($networkForSim): ?>
          <div class="alert alert-warning mb-3">
            <i class="bi bi-exclamation-triangle me-1"></i>
            No <?=esc($networkForSim)?> SIM assignment found for the creator. Please confirm transfer details manually.
          </div>
          <?php endif; ?>

        <?php elseif ($ct === 'Internal' && $st === 'Cash4Ecash' && $stage == 3): ?>
          <!-- Internal Cash4Ecash - Stage 3 (Creator uploads picture) -->
          <h6 class="border-bottom pb-2 mb-3 text-primary"><i class="bi bi-camera me-2"></i>Stage 3: Upload Confirmation Picture</h6>
          <div class="row g-3 mb-4">
            <div class="col-md-12">
              <div class="alert alert-info">
                <div class="row g-2">
                  <div class="col-sm-6"><strong>Receiving Network/Channel:</strong> <?=esc($row->receiving_network ?? '-')?></div>
                  <div class="col-sm-6"><strong>Amount:</strong> GH¢ <?=number_format((float)$row->amount, 2)?></div>
                  <?php if (!empty($row->paying_network)): ?>
                    <div class="col-sm-6"><strong>Paying Network/Channel:</strong> <?=esc($row->paying_network)?></div>
                  <?php endif; ?>
                </div>
              </div>
              <div class="alert alert-info">
                <i class="bi bi-info-circle me-1"></i>
                Upload a picture confirming the e-cash has been sent to your account.
              </div>
              <label class="form-label fw-semibold">Upload Picture of Confirmation</label>
              <input type="file" name="proof_image" class="form-control js-agent-upload-input" accept="image/*" required data-existing-src="<?=(!empty($row->proof_image) ? esc(get_image($row->proof_image)) : '')?>">
            </div>
          </div>

        <?php elseif ($ct === 'Internal' && $st === 'Ecash4Ecash' && $stage == 2): ?>
          <!-- Internal Ecash4Ecash - Stage 2 (Assignee confirms ecash received & sends back) -->
          <h6 class="border-bottom pb-2 mb-3 text-primary"><i class="bi bi-arrow-left-right me-2"></i>Stage 2: Confirm & Process E-Cash Exchange</h6>
          
          <div class="row g-3 mb-4">
            <div class="col-md-12">
              <div class="alert alert-info">
                <div class="row g-2">
                  <div class="col-sm-6"><strong>Receiving Network:</strong> <?=esc($row->receiving_network)?></div>
                  <div class="col-sm-6"><strong>Paying Network:</strong> <?=esc($row->paying_network)?></div>
                  <div class="col-sm-6"><strong>Amount:</strong> GH¢ <?=number_format((float)$row->amount, 2)?></div>
                </div>
              </div>
            </div>
            <div class="col-md-12">
              <label class="form-label fw-semibold">Upload Payment Proof</label>
              <input type="file" name="proof_image" class="form-control js-agent-upload-input" accept="image/*" required data-existing-src="<?=(!empty($row->proof_image) ? esc(get_image($row->proof_image)) : '')?>">
              <small class="text-muted">Upload proof of the payment or transfer sent from this stage.</small>
            </div>
          </div>

          <?php if ($creatorSimForNetwork): ?>
          <div class="alert alert-success mb-3">
            <h6 class="mb-2"><i class="bi bi-sim me-1"></i>Creator's <?=esc($networkForSim)?> Details (send e-cash to this)</h6>
            <?php if (!empty($creatorSimForNetwork['phone_number'])): ?>
              <div><strong>Phone Number:</strong> <?=esc($creatorSimForNetwork['phone_number'])?></div>
            <?php endif; ?>
            <?php if (!empty($creatorSimForNetwork['till_number'])): ?>
              <div><strong>Till Number:</strong> <?=esc($creatorSimForNetwork['till_number'])?></div>
            <?php endif; ?>
            <?php if (!empty($creatorSimForNetwork['shortcode'])): ?>
              <div><strong>Shortcode:</strong> <?=esc($creatorSimForNetwork['shortcode'])?></div>
            <?php endif; ?>
            <?php if (!empty($creatorSimForNetwork['account_number'])): ?>
              <div><strong>Account Number:</strong> <?=esc($creatorSimForNetwork['account_number'])?></div>
            <?php endif; ?>
          </div>
          <?php elseif ($networkForSim): ?>
          <div class="alert alert-warning mb-3">
            <i class="bi bi-exclamation-triangle me-1"></i>
            No <?=esc($networkForSim)?> SIM assignment found for the creator.
          </div>
          <?php endif; ?>

        <?php elseif ($ct === 'Internal' && $st === 'Ecash4Ecash' && $stage == 3): ?>
          <!-- Internal Ecash4Ecash - Stage 3 (Creator uploads proof received) -->
          <h6 class="border-bottom pb-2 mb-3 text-primary"><i class="bi bi-camera me-2"></i>Stage 3: Upload Proof of E-Cash Received</h6>
          
          <div class="alert alert-info mb-3">
            <div class="row g-2">
              <div class="col-sm-6"><strong>Paying Network/Channel:</strong> <?=esc($row->paying_network ?? '-')?></div>
              <div class="col-sm-6"><strong>Receiving Network/Channel:</strong> <?=esc($row->receiving_network ?? '-')?></div>
              <div class="col-sm-12"><strong>Amount:</strong> GH¢ <?=number_format((float)$row->amount, 2)?></div>
            </div>
          </div>
          
          <?php if (!empty($row->proof_image)): ?>
          <div class="alert alert-info mb-3">
            <i class="bi bi-image me-1"></i>Assignee's proof: <a href="<?=get_image($row->proof_image)?>" target="_blank" class="fw-semibold">View Image</a>
          </div>
          <?php endif; ?>
          
          <div class="row g-3 mb-4">
            <div class="col-md-12">
              <label class="form-label fw-semibold">Upload Received Proof</label>
              <input type="file" name="proof_image" class="form-control js-agent-upload-input" accept="image/*" required data-existing-src="<?=(!empty($row->proof_image) ? esc(get_image($row->proof_image)) : '')?>">
              <small class="text-muted">Upload proof showing the e-cash was received in this stage.</small>
            </div>
          </div>

        <?php elseif ($ct === 'Internal' && $st === 'Ecash4Cash' && $stage == 2): ?>
          <!-- Internal Ecash4Cash - Stage 2 (Assignee confirms ecash, enters cash paid) -->
          <h6 class="border-bottom pb-2 mb-3 text-primary"><i class="bi bi-cash me-2"></i>Stage 2: Confirm E-Cash & Pay Cash</h6>
          
          <div class="row g-3 mb-4">
            <div class="col-md-12">
              <div class="alert alert-info">
                <div class="row g-2">
                  <div class="col-sm-6"><strong>E-Cash Sent by Creator:</strong> <?=esc($row->paying_network)?></div>
                  <div class="col-sm-6"><strong>Amount:</strong> GH¢ <?=number_format((float)$row->amount, 2)?></div>
                </div>
              </div>
            </div>
            <div class="col-md-12">
              <label class="form-label fw-semibold">Upload Proof Picture (E-cash received)</label>
              <input type="file" name="proof_image" class="form-control js-agent-upload-input" accept="image/*" required data-existing-src="<?=(!empty($row->proof_image) ? esc(get_image($row->proof_image)) : '')?>">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold text-success"><i class="fa-solid fa-money-bill-wave me-1"></i> Cash Paid (Amount Counter)</label>
              <div class="input-group cursor-pointer" id="cashReceivedTrigger">
                <span class="input-group-text text-warning"><i class="fa-solid fa-calculator"></i></span>
                <input type="number" step="0.01" class="form-control cursor-pointer" 
                  id="calculated_amount" name="cash_received" 
                  placeholder="Click to count cash paid" readonly required
                  value="<?= ($row->cash_received > 0) ? number_format((float)$row->cash_received, 2) : '' ?>">
              </div>
              <small class="text-danger small"><i class="bi bi-exclamation-triangle me-1"></i>Must equal e-cash amount: GH¢ <?=number_format((float)$row->amount, 2)?></small>
              <small class="text-muted" id="changeNoticeSummary"></small>
            </div>
          </div>

        <?php elseif ($ct === 'Internal' && $st === 'Ecash4Cash' && $stage == 3): ?>
          <!-- Internal Ecash4Cash - Stage 3 (Creator cross-checks denominations received) -->
          <h6 class="border-bottom pb-2 mb-3 text-primary"><i class="bi bi-calculator me-2"></i>Stage 3: Verify Cash Received</h6>

          <div class="alert alert-info mb-3">
            <div class="row g-2">
              <div class="col-sm-6"><strong>Paying Network/Channel:</strong> <?=esc($row->paying_network ?? '-')?></div>
              <div class="col-sm-6"><strong>Amount:</strong> GH¢ <?=number_format((float)$row->amount, 2)?></div>
              <?php if (!empty($row->receiving_network)): ?>
                <div class="col-sm-6"><strong>Receiving Network/Channel:</strong> <?=esc($row->receiving_network)?></div>
              <?php endif; ?>
            </div>
          </div>

          <?php 
          $assigneeDenos = json_decode($row->denominations_json ?? '[]', true);
          if (!empty($assigneeDenos)): ?>
          <div class="alert alert-light border mb-3">
            <h6 class="mb-2 fw-semibold"><i class="bi bi-list-check me-1"></i>Assignee's Cash Denominations (verify against what you received)</h6>
            <div class="row g-1">
              <?php foreach ($assigneeDenos as $d): ?>
              <div class="col-6 col-md-4">
                <div class="d-flex justify-content-between small border rounded px-2 py-1">
                  <span>GH¢<?=esc($d['denomination'])?> &times; <?=esc($d['count'])?></span>
                  <strong>GH¢<?=number_format((float)($d['volume']),2)?></strong>
                </div>
              </div>
              <?php endforeach; ?>
              <div class="col-12 mt-2">
                <div class="d-flex justify-content-between fw-bold border-top pt-1">
                  <span>Total:</span>
                  <span class="text-success">GH¢ <?=number_format(array_sum(array_column($assigneeDenos,'volume')),2)?></span>
                </div>
              </div>
            </div>
          </div>
          <?php endif; ?>

          <?php if (!empty($row->proof_image)): ?>
          <div class="alert alert-info mb-3">
            <i class="bi bi-image me-1"></i>Assignee's proof: <a href="<?=get_image($row->proof_image)?>" target="_blank" class="fw-semibold">View Image</a>
          </div>
          <?php endif; ?>

          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label class="form-label fw-semibold text-success"><i class="fa-solid fa-money-bill-wave me-1"></i> Verify Denominations Received</label>
              <div class="input-group cursor-pointer" id="cashReceivedTrigger">
                <span class="input-group-text text-warning"><i class="fa-solid fa-calculator"></i></span>
                <input type="number" step="0.01" class="form-control cursor-pointer" 
                  id="calculated_amount" name="cash_received" 
                  placeholder="Click to count received cash" readonly required
                  value="<?= ($row->cash_received > 0) ? number_format((float)$row->cash_received, 2) : '' ?>">
              </div>
              <small class="text-danger small"><i class="bi bi-exclamation-triangle me-1"></i>Must match assignee's total: GH¢ <?=number_format(array_sum(array_column($assigneeDenos,'volume')),2)?></small>
              <small class="text-muted" id="changeNoticeSummary"></small>
            </div>
          </div>

        <?php else: ?>
          <!-- Fallback for unhandled stage/type combinations -->
          <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-1"></i>
            No specific processing form for <?=esc($ct)?> / <?=esc($st)?> at Stage <?=esc($stage)?>.
          </div>
        <?php endif; ?>
        <?php endif; ?>

        <input type="hidden" name="denominations_json" id="denominations_json" value="[]">
        <input type="hidden" name="change_amount" id="change_amount" value="0">
        <input type="hidden" name="change_denominations_json" id="change_denominations_json" value="[]">

        <div class="d-flex justify-content-between mt-5 pt-3 border-top">
          <a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view_agent_service/<?=$row->id?>" class="btn btn-outline-secondary px-4 rounded-pill">
            <i class="bi bi-arrow-left me-1"></i> Back
          </a>
          <div class="d-flex gap-2 align-items-center">
            <?php if ($stage == 2 && $formAction !== 'agent_services_update' && $isAdminOrOps): ?>
              <button type="button" class="btn btn-outline-danger px-4 rounded-pill" data-bs-toggle="modal" data-bs-target="#rejectReasonModal">
                <i class="bi bi-x-circle me-1"></i> Reject
              </button>
            <?php endif; ?>
            <button type="submit" id="agentServiceSubmitButton" class="btn btn-primary px-5 rounded-pill shadow-sm">
              <?php if ($formAction === 'agent_services_update'): ?>
                <i class="bi bi-save me-1"></i> Save Changes
              <?php elseif ($formAction === 'agent_services_confirm'): ?>
                <i class="bi bi-check2 me-1"></i> Confirm & Continue
              <?php else: ?>
                <i class="bi bi-check2-circle me-1"></i> Complete Transaction
              <?php endif; ?>
            </button>
          </div>
        </div>

        <?php if ($stage == 2 && $formAction !== 'agent_services_update' && $isAdminOrOps): ?>
        <div class="modal fade" id="rejectReasonModal" data-bs-backdrop="static" tabindex="-1">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow-sm">
              <div class="modal-header bg-danger text-white border-0">
                <h5 class="modal-title"><i class="bi bi-x-circle me-2"></i>Reject Transaction</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <p class="mb-3 text-muted">Provide a clear reason for rejecting this transaction. This will close the workflow immediately.</p>
                <div class="mb-3">
                  <label class="form-label fw-semibold">Rejection Reason</label>
                  <textarea name="reject_reason" id="rejectReasonTextarea" class="form-control" rows="4" placeholder="State why this transaction is being rejected..."><?=esc($row->reject_reason ?? '')?></textarea>
                  <div class="invalid-feedback" id="rejectReasonError">A rejection reason is required.</div>
                </div>
              </div>
              <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger rounded-pill px-4" id="confirmRejectBtn" formnovalidate>Confirm Reject</button>
              </div>
            </div>
          </div>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
          const confirmRejectBtn = document.getElementById('confirmRejectBtn');
          const rejectReasonTextarea = document.getElementById('rejectReasonTextarea');
          const rejectReasonError = document.getElementById('rejectReasonError');
          const rejectReasonModal = document.getElementById('rejectReasonModal');
          const actionInput = document.querySelector('input[name="action"]');
          const defaultActionValue = actionInput ? actionInput.value : '';

          if (!confirmRejectBtn || !rejectReasonTextarea) return;

          confirmRejectBtn.addEventListener('click', function(event) {
            event.preventDefault();
            const reason = rejectReasonTextarea.value.trim();
            if (!reason) {
              rejectReasonTextarea.classList.add('is-invalid');
              if (rejectReasonError) rejectReasonError.style.display = 'block';
              return;
            }
            rejectReasonTextarea.classList.remove('is-invalid');
            if (rejectReasonError) rejectReasonError.style.display = 'none';
            const form = document.getElementById('agentServiceForm');
            if (form) {
              form.noValidate = true;
              if (actionInput) actionInput.value = 'agent_services_reject';
              form.submit();
            }
          });

          rejectReasonTextarea.addEventListener('input', function() {
            if (this.value.trim()) {
              this.classList.remove('is-invalid');
              if (rejectReasonError) rejectReasonError.style.display = 'none';
            }
          });

          if (rejectReasonModal) {
            rejectReasonModal.addEventListener('hidden.bs.modal', function() {
              if (actionInput) actionInput.value = defaultActionValue;
              rejectReasonTextarea.classList.remove('is-invalid');
              if (rejectReasonError) rejectReasonError.style.display = 'none';
            });
          }
        });
        </script>
        <?php endif; ?>
      </form>
    </div>
  </div>
</div>

<!-- Currency Calculator Modal -->
<div class="modal fade" id="currencyModal" data-bs-backdrop="static" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold"><i class="fa-solid fa-calculator text-warning me-2"></i>Cash Counter</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body table-responsive">
        <div class="alert alert-info py-2 d-flex justify-content-between align-items-center mb-3">
          <span class="fw-semibold"><i class="bi bi-info-circle me-1"></i> Target Amount:</span>
          <strong class="fs-6 text-primary" id="currencyModalTargetAmountText">GH&cent; <?=number_format((float)$row->amount, 2)?></strong>
        </div>
        <table class="table table-hover table-sm">
          <thead><tr><th>NOTES</th><th>COUNT</th><th>VOLUME</th></tr></thead>
          <tbody>
            <?php foreach([200, 100, 50, 20, 10, 5, 2, 1, 0.5, 0.2, 0.1] as $deno): ?>
            <tr data-denomination="<?=$deno?>">
              <td class="px-5 fw-semibold">GH&cent;<?=$deno < 1 ? ($deno * 100).'p' : $deno?></td>
              <td><input type="number" class="form-control p-1 count-input" placeholder="Count" min="0"></td>
              <td><input type="text" class="form-control p-1 volume-input" placeholder="0.00" readonly></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <th class="px-5">TOTAL</th>
              <th><input type="text" class="form-control p-1 border-0 fw-bold" id="total-count" readonly></th>
              <th><input type="text" class="form-control p-1 border-0 fw-bold text-success" id="total-volume" readonly></th>
            </tr>
          </tfoot>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary rounded-pill px-4" id="confirmCalcBtn">
          <i class="bi bi-check2 me-1"></i> Confirm
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Change Calculator Modal -->
<div class="modal fade" id="changeModal" data-bs-backdrop="static" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content border-warning">
      <div class="modal-header bg-warning bg-opacity-10">
        <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-hand-holding-dollar text-warning me-2"></i>Record Change Notes</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body table-responsive">
        <div class="alert alert-warning py-2 mb-3">
          <div class="d-flex justify-content-between"><span>Cash Received:</span> <strong id="modalCashReceivedText">GH¢ 0.00</strong></div>
          <div class="d-flex justify-content-between"><span>Target Amount:</span> <strong id="modalTargetAmountText">GH¢ <?=number_format((float)$row->amount, 2)?></strong></div>
          <hr class="my-1">
          <div class="d-flex justify-content-between fs-6 fw-bold text-danger">
            <span>Change Required:</span>
            <span id="modalChangeDueText">GH¢ 0.00</span>
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
              <th><input type="text" class="form-control p-1 bg-transparent border-0 fw-bold text-danger" id="change-total-volume" readonly></th>
            </tr>
          </tfoot>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-warning text-dark fw-semibold rounded-pill px-4" id="confirmChangeBtn">
          <i class="bi bi-check2 me-1"></i> Save Change Notes
        </button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Cash Counter Logic
    var cashTrigger = document.getElementById('cashReceivedTrigger');
    var calcAmountField = document.getElementById('calculated_amount');
    var denosJson = document.getElementById('denominations_json');
    var completionSubmitButton = document.getElementById('agentServiceSubmitButton');
    var requireCompletionDenominations = <?=json_encode($formAction === 'agent_services_complete' && $stage == 3)?> && !!document.getElementById('cashReceivedTrigger');
    var completionDenominationsConfirmed = false;
    var changeAmountInput = document.getElementById('change_amount');
    var changeDenosJson = document.getElementById('change_denominations_json');
    var changeNoticeSummary = document.getElementById('changeNoticeSummary');

    var calcModalEl = document.getElementById('currencyModal');
    var changeModalEl = document.getElementById('changeModal');
    var calcModal = calcModalEl ? new bootstrap.Modal(calcModalEl) : null;
    var changeModal = changeModalEl ? new bootstrap.Modal(changeModalEl) : null;

    var countInputs = document.querySelectorAll("#currencyModal .count-input");
    var totalCountField = document.getElementById("total-count");
    var totalVolumeField = document.getElementById("total-volume");
    var confirmCalcBtn = document.getElementById("confirmCalcBtn");

    var changeCountInputs = document.querySelectorAll("#changeModal .change-count-input");
    var changeTotalCountField = document.getElementById("change-total-count");
    var changeTotalVolumeField = document.getElementById("change-total-volume");
    var confirmChangeBtn = document.getElementById("confirmChangeBtn");

    var expectedChangeDue = 0;
    var baseAmount = <?=(float)$row->amount?>;
    var commissionAmount = <?=(float)($row->commission_amount ?? 0)?>;
    var commTypeSelect = document.getElementById('commTypeSelect');

    function getTargetAmount() {
        var commType = commTypeSelect ? commTypeSelect.value : '';
        return commType === '+fee' ? baseAmount + commissionAmount : baseAmount;
    }

    function updateModalTargetAmount() {
        var target = getTargetAmount();
        var currencyModalTargetAmountText = document.getElementById('currencyModalTargetAmountText');
        var modalTargetAmountText = document.getElementById('modalTargetAmountText');
        if (currencyModalTargetAmountText) {
            currencyModalTargetAmountText.textContent = 'GH¢ ' + target.toFixed(2);
        }
        if (modalTargetAmountText) {
            modalTargetAmountText.textContent = 'GH¢ ' + target.toFixed(2);
        }
    }

    if (commTypeSelect) {
        commTypeSelect.addEventListener('change', updateModalTargetAmount);
    }

    updateModalTargetAmount();

    function updateReceivedVolumes() {
        var tCount = 0, tVol = 0, denos = [];
        countInputs.forEach(function(input) {
            var row = input.closest("tr");
            var denom = parseFloat(row.getAttribute("data-denomination"));
            var count = parseInt(input.value) || 0;
            var volField = row.querySelector(".volume-input");
            var vol = denom * count;
            volField.value = vol > 0 ? vol.toFixed(2) : '';
            tCount += count;
            tVol += vol;
            if (count > 0) denos.push({currency:'GHS',denomination:denom,count:count,volume:vol});
        });
        if (totalCountField) totalCountField.value = tCount || '';
        if (totalVolumeField) totalVolumeField.value = tVol > 0 ? tVol.toFixed(2) : '0.00';
        return {count:tCount, volume:tVol, denos:denos};
    }

    function updateCompletionSubmitState() {
        if (!completionSubmitButton || !requireCompletionDenominations || !denosJson) return;
        try {
          var denos = JSON.parse(denosJson.value || '[]');
          completionSubmitButton.disabled = !completionDenominationsConfirmed || !Array.isArray(denos) || !denos.some(function(row) {
            return Number(row.count || 0) > 0 && Number(row.volume || 0) > 0;
          });
        } catch (error) {
          completionSubmitButton.disabled = true;
        }
      }

    function updateChangeVolumes() {
        var tCount = 0, tVol = 0, denos = [];
        changeCountInputs.forEach(function(input) {
            var row = input.closest("tr");
            var denom = parseFloat(row.getAttribute("data-denomination"));
            var count = parseInt(input.value) || 0;
            var volField = row.querySelector(".change-volume-input");
            var vol = denom * count;
            volField.value = vol > 0 ? vol.toFixed(2) : '';
            tCount += count;
            tVol += vol;
            if (count > 0) denos.push({currency:'GHS',denomination:denom,count:count,volume:vol});
        });
        if (changeTotalCountField) changeTotalCountField.value = tCount || '';
        if (changeTotalVolumeField) changeTotalVolumeField.value = tVol > 0 ? tVol.toFixed(2) : '0.00';
        return {count:tCount, volume:tVol, denos:denos};
    }

    if (countInputs.length) countInputs.forEach(function(input) {
      input.addEventListener("input", function() {
        updateReceivedVolumes();
        completionDenominationsConfirmed = false;
        if (denosJson) denosJson.value = '[]';
        updateCompletionSubmitState();
      });
    });
    if (changeCountInputs.length) changeCountInputs.forEach(function(input) { input.addEventListener("input", updateChangeVolumes); });

    if (cashTrigger && calcModal) {
        cashTrigger.addEventListener('click', function() { calcModal.show(); });
    }

    if (confirmCalcBtn) {
        confirmCalcBtn.addEventListener('click', function() {
            var res = updateReceivedVolumes();
            var targetAmount = getTargetAmount();
            if (calcAmountField) calcAmountField.value = res.volume.toFixed(2);
            if (denosJson) denosJson.value = JSON.stringify(res.denos);
            completionDenominationsConfirmed = res.denos.length > 0;
            updateCompletionSubmitState();
            if (calcModal) calcModal.hide();

            if (res.volume > targetAmount && targetAmount > 0 && changeModal) {
                expectedChangeDue = res.volume - targetAmount;
                var modalCashText = document.getElementById('modalCashReceivedText');
                var modalChangeDue = document.getElementById('modalChangeDueText');
                if (modalCashText) modalCashText.textContent = 'GH¢ ' + res.volume.toFixed(2);
                if (modalChangeDue) modalChangeDue.textContent = 'GH¢ ' + expectedChangeDue.toFixed(2);
                setTimeout(function() { changeModal.show(); }, 400);
            } else {
                if (changeAmountInput) changeAmountInput.value = '0';
                if (changeDenosJson) changeDenosJson.value = '[]';
                if (changeNoticeSummary) changeNoticeSummary.textContent = '';
            }
        });
    }

    updateCompletionSubmitState();

    if (confirmChangeBtn) {
        confirmChangeBtn.addEventListener('click', function() {
            var res = updateChangeVolumes();
            if (Math.abs(res.volume - expectedChangeDue) > 0.01) {
                alert('Change notes total (GH¢ ' + res.volume.toFixed(2) + ') must equal the change required (GH¢ ' + expectedChangeDue.toFixed(2) + ').');
                return;
            }
            if (changeAmountInput) changeAmountInput.value = res.volume.toFixed(2);
            if (changeDenosJson) changeDenosJson.value = JSON.stringify(res.denos);
            if (changeNoticeSummary) changeNoticeSummary.innerHTML = '<span class="text-danger font-monospace fw-bold"><i class="bi bi-arrow-return-left"></i> Change: GH¢ ' + res.volume.toFixed(2) + '</span>';
            if (changeModal) changeModal.hide();
        });
    }

    // Image preview for proof uploads
    (function() {
        function formatBytes(bytes) {
            var value = Number(bytes || 0);
            if (!(value > 0)) return '0 KB';
            if (value >= 1024 * 1024) return (value / (1024 * 1024)).toFixed(2) + ' MB';
            return Math.max(1, Math.round(value / 1024)) + ' KB';
        }

        var uploadInputs = document.querySelectorAll('.js-agent-upload-input');
        uploadInputs.forEach(function(input) {
            var previewWrapper = document.createElement('div');
            previewWrapper.className = 'mt-2';

            var info = document.createElement('div');
            info.className = 'small text-muted js-upload-preview-info';
            info.textContent = input.dataset.existingSrc ? 'Existing proof image attached. New upload will replace it.' : 'No image selected yet.';

            var previewImage = document.createElement('img');
            previewImage.className = 'img-fluid rounded-2 mt-2 d-none js-upload-preview-thumb';
            previewImage.style.maxHeight = '160px';

            if (input.dataset.existingSrc) {
                previewImage.src = input.dataset.existingSrc;
                previewImage.classList.remove('d-none');
            }

            previewWrapper.appendChild(info);
            previewWrapper.appendChild(previewImage);
            input.parentNode.insertBefore(previewWrapper, input.nextSibling);

            input.addEventListener('change', function() {
                var file = input.files && input.files[0] ? input.files[0] : null;
                if (!file) {
                    if (input.dataset.existingSrc) {
                        info.textContent = 'Existing proof image attached. New upload will replace it.';
                        previewImage.src = input.dataset.existingSrc;
                        previewImage.classList.remove('d-none');
                    } else {
                        info.textContent = 'No image selected yet.';
                        previewImage.src = '';
                        previewImage.classList.add('d-none');
                    }
                    return;
                }

                info.innerHTML = '<strong>' + file.name + '</strong> (' + file.type + ', ' + formatBytes(file.size) + ')';
                var reader = new FileReader();
                reader.onload = function(event) {
                    previewImage.src = event.target && event.target.result ? event.target.result : '';
                    previewImage.classList.remove('d-none');
                };
                reader.readAsDataURL(file);
            });
        });
    })();
});
</script>
