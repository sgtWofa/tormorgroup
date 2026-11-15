<?php
$session = get_session();
$currentUserId = (int)($session->id ?? 0);
$status = trim((string)($row->status ?? 'Pending'));
$statusLower = strtolower($status);
$stage = (int)($row->stage ?? 1);
$ct = $row->customer_type ?? '';
$st = $row->service_type ?? '';

$badgeClass = 'bg-secondary';
if ($statusLower === 'completed') $badgeClass = 'bg-success';
elseif ($statusLower === 'rejected') $badgeClass = 'bg-danger';
elseif (strpos($statusLower, 'pending') !== false) $badgeClass = 'bg-warning text-dark';
elseif ($statusLower === 'assigned') $badgeClass = 'bg-info text-dark';
elseif ($statusLower === 'confirmed') $badgeClass = 'bg-primary';
elseif ($statusLower === 'processing') $badgeClass = 'bg-primary';
elseif (in_array($statusLower, ['cancelled', 'canceled'], true)) $badgeClass = 'bg-dark';

$isCreator   = (int)($row->creator_id ?? 0) === $currentUserId;
$isAssignee  = (int)($row->assigned_to ?? 0) === $currentUserId;
$isAdmin     = user_can('is_admin');
$isOps       = user_can('is_operations_manager')
    || (function_exists('backoffice_user_can_bank_transfer_third_party') && backoffice_user_can_bank_transfer_third_party($session));
$isAdminOrOps = $isAdmin || $isOps;
$canCancel = $isAdmin && !in_array($statusLower, ['cancelled', 'canceled', 'rejected', 'declined', 'denied'], true);

// Stage-based action permissions
// Stage 1 = creator submitted, waiting for assign
// Stage 2 = assigned user needs to process
// Stage 3 = creator completes
// Stage 4 = completed

$canAssign = ($isAdminOrOps || $isCreator || $isAdmin) 
    && $stage == 1 
    && $statusLower === 'pending';

$canEditPending = ($isAdminOrOps || $isCreator)
  && ($stage == 1 || $stage == 2)
  && $statusLower === 'pending';

$canEditStage2 = ($isAdminOrOps || $isCreator)
  && $stage == 2
  && $statusLower === 'pending';

$canProcessStage2 = ($isAssignee || $isAdminOrOps)
    && $stage == 2
    && !in_array($statusLower, ['completed', 'rejected', 'confirmed']);

$canProcessStage3 = $isCreator
    && $stage == 3
    && !in_array($statusLower, ['completed', 'rejected']);

$canEdit = $canEditPending || $canProcessStage2 || $canProcessStage3;

$providerText = !empty($row->provider) ? $row->provider : $row->paying_network;
if (empty($providerText) && !empty($row->receiving_network)) {
    $providerText = $row->receiving_network;
}
$metadata = json_decode($row->metadata ?? '{}', true) ?: [];
$storedProofImages = is_array($metadata['proof_images'] ?? null) ? $metadata['proof_images'] : [];
if (empty($storedProofImages) && !empty($row->proof_image)) {
    $storedProofImages = ['latest' => $row->proof_image];
}

// Stage description
$stageDescriptions = [
    1 => 'Awaiting Assignment',
    2 => 'Awaiting Stage 2 Confirmation',
    3 => 'Awaiting Creator Completion',
    4 => 'Completed',
];
$stageLabel = $stageDescriptions[$stage] ?? "Stage {$stage}";
?>

<div class="backoffice-shell col-md-10 mx-auto">
  
  <div class="d-flex justify-content-between align-items-center mb-3">
    <a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>#agent-services" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
      <i class="fa-solid fa-arrow-left me-1"></i> Back
    </a>
    <div class="d-flex gap-2 align-items-center">
      <span class="badge bg-primary-subtle text-primary border px-3 py-2 rounded-pill small">
        <i class="fa-solid fa-layer-group me-1"></i> <?=esc($stageLabel)?>
      </span>
      <span class="badge <?=esc($badgeClass)?> fs-6 px-3 py-2 rounded-pill shadow-sm">
        Status: <?=esc($status)?>
      </span>
    </div>
  </div>
  <div class="d-flex justify-content-end gap-2 mb-3">
    <a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/export?entity=agent_services&resource=view&id=<?=esc($row->id)?>&format=pdf" class="btn btn-outline-secondary btn-sm rounded-pill" target="_blank">
      <i class="fa-solid fa-file-pdf me-1"></i> Export PDF
    </a>
    <a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/export?entity=agent_services&resource=view&id=<?=esc($row->id)?>&format=xlsx" class="btn btn-outline-success btn-sm rounded-pill" target="_blank">
      <i class="fa-solid fa-file-excel me-1"></i> Export Excel
    </a>
  </div>

  <div class="row g-4">
    <div class="col-md-8">
      <!-- Details Card -->
      <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header border-bottom py-3">
          <h5 class="mb-0 fw-bold">Transaction Details #<?=esc($row->id)?></h5>
        </div>
        <div class="card-body">
          <div class="row g-3 mb-3">
            <div class="col-sm-4 text-muted small text-uppercase">Service</div>
            <div class="col-sm-8 fw-semibold">
              <?=esc($ct)?> &mdash; <?=esc($st)?>
            </div>
          </div>
          
          <?php if (!empty($row->agent_name) || !empty($row->business_name)): ?>
          <div class="row g-3 mb-3">
            <div class="col-sm-4 text-muted small text-uppercase">Agent Name</div>
            <div class="col-sm-8 fw-semibold">
              <?=esc($row->agent_name)?> <?= !empty($row->business_name) ? '('.esc($row->business_name).')' : '' ?>
            </div>
          </div>
          <?php endif; ?>
          <?php if (in_array($statusLower, ['cancelled', 'canceled'], true)): ?>
          <div class="alert alert-secondary mt-4">
            <div class="fw-bold mb-2"><i class="fa-solid fa-ban me-1"></i> Transaction Cancelled</div>
            <?php if (!empty($row->cancel_reason)): ?>
              <div><strong>Cancellation Reason:</strong><br><?=esc($row->cancel_reason)?></div>
            <?php else: ?>
              <div>No cancellation reason was recorded.</div>
            <?php endif; ?>
          </div>
          <?php endif; ?>

          <?php if (!empty($row->agent_contact)): ?>
          <div class="row g-3 mb-3">
            <div class="col-sm-4 text-muted small text-uppercase">Contact</div>
            <div class="col-sm-8 fw-semibold"><?=esc($row->agent_contact)?></div>
          </div>
          <?php endif; ?>

          <?php if (!empty($providerText)): ?>
          <div class="row g-3 mb-3">
            <div class="col-sm-4 text-muted small text-uppercase">Network/Provider</div>
            <div class="col-sm-8 fw-semibold"><?=esc($providerText)?></div>
          </div>
          <?php endif; ?>

          <?php if (!empty($row->receiving_network) && !empty($row->paying_network)): ?>
          <div class="row g-3 mb-3">
            <div class="col-sm-4 text-muted small text-uppercase">Receiving Network</div>
            <div class="col-sm-8 fw-semibold"><?=esc($row->receiving_network)?></div>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-sm-4 text-muted small text-uppercase">Paying Network</div>
            <div class="col-sm-8 fw-semibold"><?=esc($row->paying_network)?></div>
          </div>
          <?php endif; ?>
          
          <?php if (!empty($row->agent_number)): ?>
          <div class="row g-3 mb-3">
            <div class="col-sm-4 text-muted small text-uppercase">Phone/Account</div>
            <div class="col-sm-8 fw-semibold"><?=esc($row->agent_number)?></div>
          </div>
          <?php endif; ?>
          
          <div class="row g-3 mb-3">
            <div class="col-sm-4 text-muted small text-uppercase">Amount</div>
            <div class="col-sm-8 fw-bold text-success fs-5">
              GH&cent; <?=number_format((float)($row->amount ?? 0), 2)?>
            </div>
          </div>

          <?php if ((float)$row->commission_amount > 0): ?>
          <div class="row g-3 mb-3">
            <div class="col-sm-4 text-muted small text-uppercase">Commission</div>
            <div class="col-sm-8 fw-semibold text-primary">
              GH&cent; <?=number_format((float)($row->commission_amount ?? 0), 2)?> (<?=esc($row->commission_type)?>)
            </div>
          </div>
          <?php endif; ?>

          <?php if (!empty($metadata['type_of_customer'])): ?>
          <div class="row g-3 mb-3">
            <div class="col-sm-4 text-muted small text-uppercase">Customer Type</div>
            <div class="col-sm-8 fw-semibold"><?=esc($metadata['type_of_customer'])?></div>
          </div>
          <?php endif; ?>
          
          <?php if (!empty($storedProofImages)): ?>
          <div class="row g-3 mb-3">
            <div class="col-sm-4 text-muted small text-uppercase">Proof Images</div>
            <div class="col-sm-8">
              <div class="row g-3">
                <?php foreach ($storedProofImages as $proofKey => $proofPath): ?>
                  <?php if (empty($proofPath)) continue; ?>
                  <div class="col-md-6">
                    <div class="small text-muted mb-1">
                      <?= ($proofKey === 'stage2' || strtolower((string)$proofKey) === 'payment') ? 'Payment Proof' : (($proofKey === 'stage3' || strtolower((string)$proofKey) === 'received') ? 'Received Proof' : 'Proof Image') ?>
                    </div>
                    <a href="<?=get_image($proofPath)?>" target="_blank">
                      <img src="<?=get_image($proofPath)?>" class="img-thumbnail" style="max-height: 150px; width: 100%; object-fit: cover;">
                    </a>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
          <?php endif; ?>

          <?php 
          $denos = json_decode($row->denominations_json ?? '[]', true);
          $changeDenos = json_decode($row->change_denominations_json ?? '[]', true);
          $hasChange = ((float)($row->change_amount ?? 0) > 0) || (!empty($changeDenos) && is_array($changeDenos));
          if (!empty($denos) && is_array($denos)):
          ?>
          <div class="row g-3 mb-3">
            <div class="col-sm-4 text-muted small text-uppercase">Denominations</div>
            <div class="col-sm-8">
              <div class="alert alert-light py-2 mb-0">
                <?php foreach ($denos as $d): ?>
                  <div class="d-flex justify-content-between small">
                    <span>GH&cent;<?=esc($d['denomination'])?> &times; <?=esc($d['count'])?></span>
                    <strong>GH&cent;<?=number_format((float)($d['volume']),2)?></strong>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
          <?php endif; ?>

          <?php if ($hasChange): ?>
          <div class="row g-3 mb-3">
            <div class="col-sm-4 text-muted small text-uppercase">Change</div>
            <div class="col-sm-8">
              <?php if ((float)($row->change_amount ?? 0) > 0): ?>
                <div class="fw-semibold text-danger mb-2">GH&cent; <?=number_format((float)($row->change_amount ?? 0), 2)?></div>
              <?php endif; ?>
              <?php if (!empty($changeDenos) && is_array($changeDenos)): ?>
                <div class="alert alert-warning py-2 mb-0">
                  <?php foreach ($changeDenos as $d): ?>
                    <div class="d-flex justify-content-between small">
                      <span>GH&cent;<?=esc($d['denomination'])?> &times; <?=esc($d['count'])?></span>
                      <strong>GH&cent;<?=number_format((float)($d['volume']),2)?></strong>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
          <?php endif; ?>
          
          <?php if ($statusLower === 'rejected'): ?>
          <div class="alert alert-danger mt-4">
            <div class="fw-bold mb-2"><i class="bi bi-x-circle me-1"></i> Transaction Rejected and Workflow Closed</div>
            <?php if (!empty($row->reject_reason)): ?>
              <div><strong>Rejection Reason:</strong><br><?=esc($row->reject_reason)?></div>
            <?php else: ?>
              <div>No rejection reason was provided.</div>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <?php if (!empty($row->date_completed) || !empty($row->date_created)): ?>
      <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body">
          <h6 class="text-uppercase text-muted small mb-3">Timeline</h6>
          <?php if (!empty($row->date_created)): ?>
          <div class="d-flex gap-2 mb-2 align-items-center">
            <i class="fa-regular fa-calendar-plus text-secondary"></i>
            <span class="small text-muted">Created: <strong><?=esc($row->date_created)?></strong><?php if (!empty($row->creator_first_name) || !empty($row->creator_last_name)): ?> by <strong><?=esc(trim(($row->creator_first_name ?? '') . ' ' . ($row->creator_last_name ?? '')))?></strong><?php endif; ?></span>
          </div>
          <?php endif; ?>
          <?php if (!empty($row->date_updated)): ?>
          <div class="d-flex gap-2 mb-2 align-items-center">
            <i class="fa-solid fa-pen text-info"></i>
            <span class="small text-muted">Updated: <strong><?=esc($row->date_updated)?></strong><?php if (!empty($row->updated_by_first_name) || !empty($row->updated_by_last_name)): ?> by <strong><?=esc(trim(($row->updated_by_first_name ?? '') . ' ' . ($row->updated_by_last_name ?? '')))?></strong><?php endif; ?></span>
          </div>
          <?php endif; ?>
          <?php if (!empty($row->date_completed)): ?>
          <div class="d-flex gap-2 mb-2 align-items-center">
            <i class="fa-regular fa-circle-check text-success"></i>
            <span class="small text-muted">Completed: <strong><?=esc($row->date_completed)?></strong></span>
          </div>
          <?php endif; ?>
          <?php if (!empty($row->updated_by_first_name) || !empty($row->updated_by_last_name)): ?>
          <div class="d-flex gap-2 mb-2 align-items-center">
            <i class="fa-solid fa-user-pen text-secondary"></i>
            <span class="small text-muted">Handled By: <strong><?=esc(trim(($row->updated_by_first_name ?? '') . ' ' . ($row->updated_by_last_name ?? '')))?></strong></span>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
    
    <div class="col-md-4">
      <!-- People Card -->
      <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body">
          <h6 class="text-uppercase text-muted small mb-3">Roles</h6>
          <div class="mb-3">
            <div class="small text-muted">Created By</div>
            <div class="fw-semibold"><?=esc(trim(($row->creator_first_name ?? '') . ' ' . ($row->creator_last_name ?? '')))?></div>
          </div>
          <div class="mb-3">
            <div class="small text-muted">Assigned To (Stage 2)</div>
            <div class="fw-semibold">
              <?= !empty($row->assigned_to) ? esc(trim(($row->assigned_first_name ?? '') . ' ' . ($row->assigned_last_name ?? ''))) : '<span class="text-warning"><i class="fa-solid fa-circle-exclamation me-1"></i>Unassigned</span>' ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Actions Card -->
      <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body">
          <h6 class="text-uppercase text-muted small mb-3">Actions</h6>
          
          <?php if ($canEditPending): ?>
            <a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit_agent_service/<?=$row->id?>" class="btn btn-primary w-100 rounded-pill mb-2">
              <i class="fa-regular fa-pen-to-square me-1"></i>
              <?= $stage === 2 && $ct === 'Internal' ? 'Edit Internal Transaction' : ($stage === 2 ? 'Edit Pending Stage 2' : 'Edit Pending Transaction') ?>
            </a>
          <?php endif; ?>

          <?php if ($canProcessStage2): ?>
            <a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/process_agent_service/<?=$row->id?>" class="btn btn-success w-100 rounded-pill mb-2">
              <i class="fa-solid fa-gears me-1"></i>
              Process Stage 2
            </a>
          <?php endif; ?>

          <?php if ($canProcessStage3): ?>
            <a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit_agent_service/<?=$row->id?>" class="btn btn-success w-100 rounded-pill mb-2">
              <i class="fa-solid fa-check2 me-1"></i>
              Complete Transaction
            </a>
          <?php endif; ?>

          <?php if ($canAssign): ?>
            <button type="button" class="btn btn-outline-info w-100 rounded-pill mb-2" 
              id="openAssignModalBtn"
              onclick="document.getElementById('assignModal').style.display='flex'; document.getElementById('assignModal').classList.add('show');">
              <i class="fa-solid fa-user-plus me-1"></i> Assign to User
            </button>
          <?php endif; ?>

          <?php if ($canCancel): ?>
            <button type="button" class="btn btn-outline-danger w-100 rounded-pill mb-2" data-bs-toggle="modal" data-bs-target="#cancelAgentServiceModal">
              <i class="fa-solid fa-ban me-1"></i> Cancel Transaction
            </button>
          <?php endif; ?>

          <?php if (!$canEdit && !$canAssign && !in_array($statusLower, ['completed', 'rejected', 'cancelled', 'canceled'])): ?>
            <div class="text-center text-muted small mt-2 p-3 bg-warning-subtle text-warning rounded-3">
              <i class="fa-solid fa-hourglass-half d-block fs-4 mb-2 text-warning"></i>
              Waiting for another user to process this stage...
            </div>
          <?php elseif ($statusLower === 'completed'): ?>
            <div class="text-center text-success small mt-2 p-3 bg-success bg-opacity-10 rounded-3">
              <i class="fa-solid fa-circle-check d-block fs-4 mb-2"></i>
              Transaction Completed
            </div>
          <?php elseif ($statusLower === 'rejected'): ?>
            <div class="text-center text-danger small mt-2 p-3 bg-danger bg-opacity-10 rounded-3">
              <i class="fa-solid fa-circle-xmark d-block fs-4 mb-2"></i>
              Transaction Rejected
            </div>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>
</div>

<?php if ($canAssign): ?>
<!-- Assign Modal -->
<div class="modal fade" id="assignModal" tabindex="-1" aria-labelledby="assignModalLabel" aria-modal="true" role="dialog">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view_agent_service/<?=$row->id?>">
        <?=csrf()?>
        <input type="hidden" name="action" value="agent_services_assign">
        <div class="modal-header">
          <h5 class="modal-title" id="assignModalLabel"><i class="fa-solid fa-user-plus me-2"></i>Assign Transaction</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" onclick="closeAssignModal()"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">Assign To</label>
            <select name="assigned_to" class="form-select" required>
              <option value="">-- Select User --</option>
              <?php 
                $assignableUsers = backoffice_agent_services_assignable_users();
                foreach ($assignableUsers as $u): 
              ?>
                <option value="<?=$u->id?>"><?=esc($u->first_name . ' ' . $u->last_name)?></option>
              <?php endforeach; ?>
            </select>
            <?php if (empty($assignableUsers)): ?>
              <small class="text-warning"><i class="fa-solid fa-triangle-exclamation me-1"></i>No users available to assign. Make sure users have active cash drawers today.</small>
            <?php endif; ?>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal" onclick="closeAssignModal()">Cancel</button>
          <button type="submit" class="btn btn-primary px-4 rounded-pill">
            <i class="fa-solid fa-user-check me-1"></i> Assign
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Initialize Bootstrap modal
    var assignModalEl = document.getElementById('assignModal');
    
    if (assignModalEl && typeof bootstrap !== 'undefined') {
        var assignModal = new bootstrap.Modal(assignModalEl, {
            keyboard: true,
            backdrop: true
        });
        
        // Wire the open button
        var openBtn = document.getElementById('openAssignModalBtn');
        if (openBtn) {
            openBtn.onclick = function() {
                assignModal.show();
            };
        }
        
        // Wire close buttons
        assignModalEl.querySelectorAll('[data-bs-dismiss="modal"]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                assignModal.hide();
            });
        });
    } else if (assignModalEl) {
        // Fallback if bootstrap object not yet available
        document.getElementById('openAssignModalBtn').onclick = function() {
            assignModalEl.style.display = 'flex';
            assignModalEl.classList.add('show');
            document.body.classList.add('modal-open');
            var backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            backdrop.id = 'assignBackdrop';
            document.body.appendChild(backdrop);
        };
    }
});

function closeAssignModal() {
    var assignModalEl = document.getElementById('assignModal');
    if (!assignModalEl) return;
    if (typeof bootstrap !== 'undefined') {
        var m = bootstrap.Modal.getInstance(assignModalEl);
        if (m) m.hide();
    } else {
        assignModalEl.style.display = 'none';
        assignModalEl.classList.remove('show');
        document.body.classList.remove('modal-open');
        var bd = document.getElementById('assignBackdrop');
        if (bd) bd.remove();
    }
}
</script>
<?php endif; ?>

<?php if ($canCancel): ?>
<div class="modal fade" id="cancelAgentServiceModal" tabindex="-1" aria-labelledby="cancelAgentServiceModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4">
      <form method="post" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view_agent_service/<?=$row->id?>">
        <?=csrf()?>
        <input type="hidden" name="action" value="agent_services_cancel">
        <div class="modal-header border-0 pb-0">
          <div>
            <span class="badge rounded-pill bg-danger-subtle text-danger mb-2">Admin action</span>
            <h5 class="modal-title fw-bold" id="cancelAgentServiceModalLabel">Cancel Agent Service</h5>
            <p class="text-muted small mb-0">This closes the transaction and reverses its recorded drawer and commission impacts.</p>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body pt-3">
          <div class="rounded-3 bg-body-tertiary text-body border p-3 mb-3">
            <div class="small text-body-secondary">Transaction</div>
            <div class="fw-semibold">#<?=esc($row->id)?> - <?=esc($ct)?> - <?=esc($st)?></div>
            <div class="small text-body-secondary mt-1">Current status: <?=esc($status)?></div>
          </div>
          <label for="agentServiceCancellationReason" class="form-label fw-semibold">Cancellation reason <span class="text-danger">*</span></label>
          <textarea class="form-control" id="agentServiceCancellationReason" name="cancellation_reason" rows="4" maxlength="500" required placeholder="Explain why this transaction is being cancelled."></textarea>
          <div class="form-text">Required. The reason is saved to the transaction record.</div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Keep Transaction</button>
          <button type="submit" class="btn btn-danger rounded-pill px-4">
            <i class="fa-solid fa-ban me-1"></i> Confirm Cancellation
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (window.location.hash === '#cancel-agent-service' && typeof bootstrap !== 'undefined') {
    var modalElement = document.getElementById('cancelAgentServiceModal');
    if (modalElement) {
      new bootstrap.Modal(modalElement).show();
    }
  }
});
</script>
<?php endif; ?>