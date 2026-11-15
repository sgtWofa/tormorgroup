<style type="text/css">
    tbody tr {
        white-space: nowrap;
    }

    thead tr {
        white-space: nowrap;
    }

    tfoot tr {
        white-space: nowrap;
    }

    a{
      text-decoration: none;
    }

    .input-group-text {
        font-weight: bolder;
    }

    .form-label {
        color: gray;
        font-size: 12px;
    }
    .late-recon-user-date {
        display: none;
    }
    @media (max-width: 991.98px) {
        #lateAccessMiniPane .late-recon-date-col {
            display: none;
        }
        #lateAccessMiniPane .late-recon-user-date {
            display: block;
            margin-top: .2rem;
            font-size: .82rem;
            color: var(--bs-secondary-color);
            white-space: normal;
        }
        #lateAccessMiniPane table {
            table-layout: fixed;
            width: 100%;
        }
        #lateAccessMiniPane th:first-child,
        #lateAccessMiniPane td:first-child {
            width: 48%;
            white-space: normal;
        }
        #lateAccessMiniPane th:nth-child(3),
        #lateAccessMiniPane td:nth-child(3) {
            width: 27%;
        }
        #lateAccessMiniPane th:nth-child(4),
        #lateAccessMiniPane td:nth-child(4) {
            width: 25%;
        }
    }

    .network-group {
        font-weight: bold;
        text-transform: uppercase;
    }

    /* Print-specific styles */
    @media print {
        .no-print {
            display: none !important;
        }

        /* Include background styles */
        body, #printableContent {
            background-image: none !important; /* If needed, replace with desired print background */
        }

        .modal-content {
            width: 100%;
            border: none;
            box-shadow: none;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td, th {
            border-bottom: 0.5px dashed; #ddd;
            padding: 8px;
        }

        .print-color {
            color: #6c757d !important; /* secondary text color */
        }

        body {
            visibility: hidden;
        }

        #printableContent {
            visibility: visible;
        }
    }
</style>

<?php
  $canRejectReconciliations = !empty($canRejectReconciliations);
  $canApproveReconciliations = !empty($canApproveReconciliations);
  $reconciliationLocked = !empty($reconciliationLocked);
  $reconciliationLockMessage = $reconciliationLockMessage ?? '';
  $isPettyCashCompletionLock = stripos($reconciliationLockMessage, 'Complete all acknowledged petty cash transactions') === 0;
  $lockedReconciliation = $activeReconciliation ?? null;
  $lockedReconciliationStatus = is_object($lockedReconciliation)
      ? strtolower(trim((string)($lockedReconciliation->transaction_status ?? 'Submitted')))
      : '';
  $lockedReconciliationId = is_object($lockedReconciliation)
      ? (string)($lockedReconciliation->id ?? $lockedReconciliation->session_id ?? '')
      : '';
  $canGrantLateReconciliationAccess = !empty($canGrantLateReconciliationAccess);
  $lateReconciliationAccessRows = (array)($lateReconciliationAccessRows ?? []);
  $lateReconciliationPendingCount = 0;
  foreach ($lateReconciliationAccessRows as $lateCountRow) {
    if (empty($lateCountRow->active_access_id)) {
      $lateReconciliationPendingCount++;
    }
  }
  $lateReconciliationTotalCount = count($lateReconciliationAccessRows);
?>

<?php if($reconciliationLocked && $isPettyCashCompletionLock): ?>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var message = <?=json_encode($reconciliationLockMessage, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)?>;
      var pettyCashUrl = <?=json_encode(ROOT . '/' . $admin_route . '/pettycash', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)?>;
      if (window.Swal && typeof window.Swal.fire === 'function') {
        var htmlMessage = '<p>' + String(message).replace(/[&<>"']/g, function (character) {
          return {'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;'}[character];
        }) + '</p>' +
          '<a class="btn btn-primary" href="' + pettyCashUrl.replace(/"/g, '&quot;') + '">Open Petty Cash</a>';
        window.Swal.fire({
          title: 'Petty Cash completion required',
          html: htmlMessage,
          icon: 'warning',
          confirmButtonText: 'OK',
          allowOutsideClick: false
        });
      } else if (typeof window.basicAdminSwalAlert === 'function') {
        window.basicAdminSwalAlert('Petty Cash completion required', message, 'warning');
      }
    });
  </script>
<?php endif; ?>


<div class="row my-3 mx-auto" >
  <div class="input-group">
  
  <span class="mx-2">
    <?php if($reconciliationLocked): ?>
      <?php if($lockedReconciliationStatus === 'approved' && $lockedReconciliationId !== ''): ?>
        <a class="btn btn-warning btn-sm" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view_reconciliation/<?=esc($lockedReconciliationId)?>" title="<?=esc($reconciliationLockMessage)?>">
          <i class="fa-solid fa-lock"></i> View Last Reconciliation
        </a>
      <?php else: ?>
        <button class="btn btn-warning btn-sm" type="button" disabled title="<?=esc($reconciliationLockMessage)?>">
          <i class="fa-solid fa-lock"></i> Reconciliation Submitted
        </button>
      <?php endif; ?>
    <?php else: ?>
    <a style="text-decoration: none;" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/reconciliation">
      <button class="btn btn-warning btn-sm">
        <i class="fa-solid fa-plus"></i> New Reconciliation
      </button>
    </a>
    <?php endif; ?>
  </span>
  <span class="mx-2">
    <button type="button" class="btn btn-outline-danger btn-sm" id="reconExportPdfBtn">
      <i class="fa-solid fa-file-pdf"></i> Export PDF
    </button>
  </span>
  </div>
  <?php if($reconciliationLocked && !$isPettyCashCompletionLock): ?>
    <div class="alert alert-warning mt-2 mb-0" role="alert">
      <?=esc($reconciliationLockMessage ?: 'Reconciliation has already been submitted for today. Admin must reject it with a reason before redo is allowed.')?>
    </div>
  <?php endif; ?>
  <?php if(!empty($lateReconciliationAccessNotifications)): ?>
    <?php foreach($lateReconciliationAccessNotifications as $lateAccess): ?>
      <div class="alert alert-info mt-2 mb-0" role="alert" data-attention-sound="1" data-attention-key="late-reconciliation-access-<?=esc((string)($lateAccess->id ?? 0))?>">
        <div class="fw-semibold">Late reconciliation access granted</div>
        <div>You can now reconcile your past cashdrawer for <?=esc(get_date($lateAccess->cashdrawer_date ?? $lateAccess->drawer_date ?? ''))?>.</div>
        <a class="btn btn-sm btn-primary mt-2" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/reconciliation?late_access=<?=esc((string)($lateAccess->id ?? 0))?>">
          Open past reconciliation
        </a>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

  <?php if($canGrantLateReconciliationAccess): ?>
    <ul class="nav nav-pills mt-3 gap-2" id="reconciliationMiniTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="recon-history-mini-tab" data-bs-toggle="pill" data-bs-target="#reconciliationHistoryMiniPane" data-mini-tab-target="#reconciliationHistoryMiniPane" type="button" role="tab" aria-controls="reconciliationHistoryMiniPane" aria-selected="true">
          Reconciliation history
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="late-access-mini-tab" data-bs-toggle="pill" data-bs-target="#lateAccessMiniPane" data-mini-tab-target="#lateAccessMiniPane" type="button" role="tab" aria-controls="lateAccessMiniPane" aria-selected="false">
          Late access <span class="badge bg-secondary ms-1"><?=esc((string)$lateReconciliationPendingCount)?></span>
        </button>
      </li>
    </ul>
    <div class="tab-content mt-2" id="reconciliationMiniTabContent">
      <div class="tab-pane fade" id="lateAccessMiniPane" role="tabpanel" aria-labelledby="late-access-mini-tab" tabindex="0">
  <?php endif; ?>
  <?php if($canGrantLateReconciliationAccess): ?>
    <div class="alert alert-light border mt-2 mb-0">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <div>
          <div class="fw-semibold">Grant late reconciliation access</div>
          <div class="small text-muted">Past unreconciled cashdrawers from Operations Accounts appear here.</div>
        </div>
        <div class="d-flex gap-2">
          <span class="badge bg-warning text-dark"><?=esc((string)$lateReconciliationPendingCount)?> pending</span>
          <span class="badge bg-secondary"><?=esc((string)$lateReconciliationTotalCount)?> found</span>
        </div>
      </div>
      <?php if(!empty($lateReconciliationAccessRows)): ?>
        <div class="row g-2 align-items-end mb-2">
          <div class="col-12 col-md-5">
            <label class="form-label" for="lateReconSearchInput">Search</label>
            <input type="search" class="form-control form-control-sm" id="lateReconSearchInput" placeholder="User, date or drawer ID">
          </div>
          <div class="col-7 col-md-4">
            <label class="form-label" for="lateReconStatusFilter">Status</label>
            <select class="form-select form-select-sm" id="lateReconStatusFilter">
              <option value="">All statuses</option>
              <option value="granted">Access already granted</option>
              <option value="pending">Not reconciled</option>
            </select>
          </div>
          <div class="col-5 col-md-3">
            <button type="button" class="btn btn-sm btn-outline-secondary w-100" id="lateReconClearFilters">Clear</button>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead>
              <tr>
                <th>User</th>
                <th class="late-recon-date-col">Cashdrawer date</th>
                <th>Status</th>
                <th class="text-end">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($lateReconciliationAccessRows as $lateRow): ?>
                <?php
                  $lateName = trim((string)($lateRow->first_name ?? '') . ' ' . (string)($lateRow->last_name ?? ''));
                  $lateDate = get_date_time($lateRow->date_created ?? '');
                  $lateStatusKey = !empty($lateRow->active_access_id) ? 'granted' : 'pending';
                  $lateSearchBlob = strtolower(trim($lateName . ' ' . $lateDate . ' ' . (string)($lateRow->id ?? '') . ' ' . (string)($lateRow->user_id ?? '') . ' ' . $lateStatusKey));
                ?>
                <tr data-late-recon-row="1" data-late-search="<?=esc($lateSearchBlob)?>" data-late-status="<?=esc($lateStatusKey)?>">
                  <td>
                    <div><?=esc($lateName)?></div>
                    <div class="late-recon-user-date"><?=esc($lateDate)?></div>
                  </td>
                  <td class="late-recon-date-col"><?=esc($lateDate)?></td>
                  <td>
                    <?php if(!empty($lateRow->active_access_id)): ?>
                      <span class="badge bg-info text-dark">Access already granted</span>
                    <?php else: ?>
                      <span class="badge bg-warning text-dark">Not reconciled</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end">
                    <?php if(empty($lateRow->active_access_id)): ?>
                      <form method="post" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/grant_reconciliation_access" class="d-inline">
                        <?=csrf()?>
                        <input type="hidden" name="cashdrawer_id" value="<?=esc((string)($lateRow->id ?? 0))?>">
                        <input type="hidden" name="user_id" value="<?=esc((string)($lateRow->user_id ?? 0))?>">
                        <button type="submit" class="btn btn-sm btn-outline-primary">Grant access</button>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <div class="text-muted small mt-2" id="lateReconFilterSummary"></div>
          <nav id="lateReconPaginationNav" aria-label="Late reconciliation access pages" class="mt-2">
            <ul class="pagination pagination-sm justify-content-center mb-0"></ul>
          </nav>
        </div>
      <?php else: ?>
        <div class="text-muted small">No past unreconciled cashdrawer is waiting for late reconciliation access.</div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

<?php if($canGrantLateReconciliationAccess): ?>
      </div>
      <div class="tab-pane fade show active" id="reconciliationHistoryMiniPane" role="tabpanel" aria-labelledby="recon-history-mini-tab" tabindex="0">
<?php endif; ?>

<div class="row g-2 align-items-end mb-3">
  <div class="col-12 col-lg-3">
    <label class="form-label" for="reconSearchInput">Search</label>
    <input type="search" class="form-control form-control-sm" id="reconSearchInput" placeholder="Agent, status, date or remarks">
  </div>
  <div class="col-6 col-lg-2">
    <label class="form-label" for="reconStatusFilter">Status</label>
    <select class="form-select form-select-sm" id="reconStatusFilter">
      <option value="">All statuses</option>
      <option value="submitted">Submitted</option>
      <option value="approved">Approved</option>
      <option value="rejected">Rejected</option>
      <option value="declined">Declined</option>
    </select>
  </div>
  <div class="col-6 col-lg-2">
    <label class="form-label" for="reconRemarksFilter">Remarks</label>
    <select class="form-select form-select-sm" id="reconRemarksFilter">
      <option value="">All remarks</option>
      <option value="balanced">Balanced</option>
      <option value="not-balanced">Not balanced</option>
      <option value="shortage">Shortage</option>
      <option value="overage">Overage</option>
    </select>
  </div>
  <div class="col-6 col-lg-2">
    <label class="form-label" for="reconDateFrom">From</label>
    <input type="date" class="form-control form-control-sm" id="reconDateFrom">
  </div>
  <div class="col-6 col-lg-2">
    <label class="form-label" for="reconDateTo">To</label>
    <input type="date" class="form-control form-control-sm" id="reconDateTo">
  </div>
  <div class="col-12 col-lg-1">
    <button type="button" class="btn btn-sm btn-outline-secondary w-100" id="reconClearFilters">Clear</button>
  </div>
</div>

<div class="table-responsive">
	<table class="table table-striped table-hover">
		<thead class="d-none d-lg-table-header-group">
			<tr>
				<th class="">#</th>
				<th class="">DATE/TIME</th>
				<th class="">AGENT</th>
				<th class="">STATUS</th>
				<th class="">REMARKS</th>
				<th class=""></th>
			</tr>
		</thead>
		<tbody id="activityTable">
		<?php if(!empty($reconciliations_summary)): $id = 0; ?>
			<?php foreach($reconciliations_summary as $row): $id ++;
				$statusLabel = trim((string)($row->transaction_status ?? 'Submitted'));
				if ($statusLabel === '') {
					$statusLabel = 'Submitted';
				}
				$statusLower = strtolower($statusLabel);
				$isRejected = in_array($statusLower, ['rejected', 'declined'], true);
				$isSubmitted = $statusLower === 'submitted';
				$statusClass = $isRejected ? 'bg-danger' : ($isSubmitted ? 'bg-warning text-dark' : 'bg-success');
				$rejectionReason = trim((string)($row->rejection_reason ?? ''));
				$agentName = trim((string)($row->user_first_name ?? '') . ' ' . (string)($row->user_last_name ?? ''));
				$isLateAccessHistory = !empty($row->late_access_id_resolved) || !empty($row->late_access_id) || !empty($row->late_submitted_at);
				$cashdrawerDateTimeRaw = $isLateAccessHistory && !empty($row->late_cashdrawer_date)
					? (string)$row->late_cashdrawer_date
					: (string)($row->date_created ?? '');
				$submittedDateTimeRaw = $isLateAccessHistory && !empty($row->late_submitted_at)
					? (string)$row->late_submitted_at
					: (string)($row->date_created ?? '');
				$cashdrawerDateLabel = $cashdrawerDateTimeRaw !== '' ? get_date($cashdrawerDateTimeRaw) : '';
				$submittedDateLabel = $submittedDateTimeRaw !== '' ? get_date_time($submittedDateTimeRaw) : '';
				$accessTypeLabel = $isLateAccessHistory ? 'Late access' : 'Normal';
				$rowDate = $cashdrawerDateTimeRaw !== '' ? date('Y-m-d', strtotime($cashdrawerDateTimeRaw)) : '';
				$miscBalance = (float)($row->misc_balance ?? 0);
				if (abs($miscBalance) >= 0.01) {
					$filterRemarks = $miscBalance > 0 ? 'shortage' : 'overage';
				} else if (($row->remarks ?? '') == 'Balanced') {
					$filterRemarks = 'balanced';
				} else {
					$filterRemarks = 'not-balanced';
				}
				$searchBlob = strtolower(trim($agentName . ' ' . $statusLabel . ' ' . $cashdrawerDateTimeRaw . ' ' . $submittedDateTimeRaw . ' ' . $accessTypeLabel . ' ' . $filterRemarks . ' ' . (string)($row->remarks ?? '') . ' ' . $rejectionReason));
			?>
				<tr data-recon-row="1" data-recon-search="<?=esc($searchBlob)?>" data-recon-status="<?=esc($statusLower)?>" data-recon-remarks="<?=esc($filterRemarks)?>" data-recon-date="<?=esc($rowDate)?>">
					<td class="d-lg-none" colspan="6">
						<?php 
						   $miscBalance = (float)($row->misc_balance ?? 0);
						   if (abs($miscBalance) >= 0.01) {
							  $label = ($miscBalance > 0) ? 'Shortage' : 'Overage';
							  $amount = number_format(abs($miscBalance), 2);
							  $remarksBadge = '<i class="fas fa-exclamation-triangle text-warning fs-5"></i> ' . $label . ' <span class="text-muted">(' . $amount . ')</span>';
						   } else if (($row->remarks ?? '') == 'Balanced') {
							  $remarksBadge = '<i class="fas fa-circle-check text-success fs-5"></i> Balanced';
						   } else {
							  $remarksBadge = '<i class="fas fa-exclamation-triangle text-danger fs-5"></i> Not Balanced';
						   }
						?>
						<div class="row g-2 align-items-start">
							<div class="col-7 bo-stack-cell">
								<div class="line">
									<span class="badge bg-warning text-black rounded-circle pill"><?=$id?></span>
									<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view_reconciliation/<?=$row->id?>">
										<?=esc($cashdrawerDateLabel)?>
									</a>
								</div>
								<div class="line"><span class="k">Submitted</span><span><?=esc($submittedDateLabel)?></span></div>
								<div class="line"><span class="k">Type</span><span class="badge <?=$isLateAccessHistory ? 'bg-info text-dark' : 'bg-secondary'?>"><?=esc($accessTypeLabel)?></span></div>
								<div class="line"><span class="k">Agent</span><span><?=esc(($row->user_first_name ?? '') . ' ' . ($row->user_last_name ?? ''))?></span></div>
								<div class="line"><span class="k">Status</span><span class="badge <?=$statusClass?>"><?=esc($statusLabel)?></span></div>
								<?php if($rejectionReason !== ''): ?>
									<div class="line">
										<span class="k">Reason</span>
										<button type="button" class="btn btn-sm btn-outline-danger recon-reason-btn" data-bs-toggle="modal" data-bs-target="#reconciliationReasonModal" data-reason="<?=esc($rejectionReason)?>" data-agent="<?=esc(($row->user_first_name ?? '') . ' ' . ($row->user_last_name ?? ''))?>" data-date="<?=esc($cashdrawerDateLabel)?>">
											View reason
										</button>
									</div>
								<?php endif; ?>
								<div class="line"><span class="k">Remarks</span><span><span class="p-1 fst-italic" style="border:none;"><?=$remarksBadge?></span></span></div>
							</div>
							<div class="col-3 bo-stack-amount">
								<div class="k">Amounts</div>
								<div class="fw-semibold">Bal: <?=number_format($row->balance ?? 0, 2)?></div>
							</div>
							<div class="col-2 text-end">
								<a class="btn btn-sm btn-outline-secondary" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view_reconciliation/<?=$row->id?>" style="border:none;">
									<i class="fas fa-eye"></i>
								</a>
							</div>
						</div>
						<?php if($canRejectReconciliations && !$isRejected): ?>
							<?php if($canApproveReconciliations && $isSubmitted): ?>
								<form method="post" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/approve_reconciliation/<?=$row->id?>" class="d-inline">
									<?=csrf()?>
									<button type="submit" class="btn btn-sm btn-outline-success mt-2">
										Approve
									</button>
								</form>
							<?php endif; ?>
							<button type="button" class="btn btn-sm btn-outline-danger mt-2 recon-reject-btn" data-bs-toggle="modal" data-bs-target="#rejectReconciliationModal" data-action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/reject_reconciliation/<?=$row->id?>" data-agent="<?=esc(($row->user_first_name ?? '') . ' ' . ($row->user_last_name ?? ''))?>" data-date="<?=esc($cashdrawerDateLabel)?>">
								Reject
							</button>
						<?php endif; ?>
					</td>
					<td class="d-none d-lg-table-cell"><span class="badge bg-warning text-black rounded-circle pill"><?=$id?></span></td>
					<td class="d-none d-lg-table-cell">
						<div class="d-flex flex-column gap-1">
							<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view_reconciliation/<?=$row->id?>">
								<span><?=esc($submittedDateLabel)?></span>
							</a>
							<div>
								<small class="text-muted small text-uppercase" style="font-size: 11px;">
								  <code>cashdrawer: </code>
                  <?=esc($cashdrawerDateLabel)?>
                </small>
							</div>
						</div>
				  </td>
					<td class="d-none d-lg-table-cell">
						<div class="d-flex align-items-start gap-2">
							<span><img src="<?=get_image($row->user_image)?>" style="width: 30px; height: 30px; object-fit: cover; border-radius: 50%;"></span>
							<div class="d-flex flex-column gap-1">
								<span><?=esc($row->user_first_name . ' ' . $row->user_last_name)?></span>
								<span class="badge badge-sm text-uppercase align-self-start <?=$isLateAccessHistory ? 'bg-info text-dark' : 'bg-secondary'?>" style="font-size: 11px;"><?=esc($accessTypeLabel)?></span>
							</div>
						</div>
					</td>
			  <td class="d-none d-lg-table-cell">
			  	<span class="badge <?=$statusClass?> text-uppercase" style="font-size: 11px;"><?=esc($statusLabel)?></span>
			  	<?php if($rejectionReason !== ''): ?>
			  		<button type="button" class="btn btn-sm btn-outline-danger recon-reason-btn" data-bs-toggle="modal" data-bs-target="#reconciliationReasonModal" data-reason="<?=esc($rejectionReason)?>" data-agent="<?=esc(($row->user_first_name ?? '') . ' ' . ($row->user_last_name ?? ''))?>" data-date="<?=esc($cashdrawerDateLabel)?>">
			  			View reason
			  		</button>
			  	<?php endif; ?>
			  </td>
			  <td class="d-none d-lg-table-cell">
						<?php 
					   $miscBalance = (float)($row->misc_balance ?? 0);
					   if (abs($miscBalance) >= 0.01) {
						  $label = ($miscBalance > 0) ? 'Shortage' : 'Overage';
						  $amount = number_format(abs($miscBalance), 2);
						  $remarksBadge = '<i class="fas fa-exclamation-triangle text-warning fs-5"></i> ' . $label . ' <span class="text-muted">(' . $amount . ')</span>';
					   } else if (($row->remarks ?? '') == 'Balanced') {
						  $remarksBadge = '<i class="fas fa-circle-check text-success fs-5"></i> Balanced';
					   } else {
						  $remarksBadge = '<i class="fas fa-exclamation-triangle text-danger fs-5"></i> Not Balanced';
					   }
						?>
						<div class="p-1 fst-italic
						" id="total-remarks-div" style="border:none;" ><?=$remarksBadge?></div>
					</td>
					<td class="d-none d-lg-table-cell">    
						<a  href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view_reconciliation/<?=$row->id?>"><i class="fas fa-eye"></i> Details</a>
						<?php if($canRejectReconciliations && !$isRejected): ?>
							<?php if($canApproveReconciliations && $isSubmitted): ?>
								<form method="post" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/approve_reconciliation/<?=$row->id?>" class="d-inline ms-2">
									<?=csrf()?>
									<button type="submit" class="btn btn-sm btn-outline-success">
										Approve
									</button>
								</form>
							<?php endif; ?>
							<button type="button" class="btn btn-sm btn-outline-danger ms-2 recon-reject-btn" data-bs-toggle="modal" data-bs-target="#rejectReconciliationModal" data-action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/reject_reconciliation/<?=$row->id?>" data-agent="<?=esc(($row->user_first_name ?? '') . ' ' . ($row->user_last_name ?? ''))?>" data-date="<?=esc($cashdrawerDateLabel)?>">
								Reject
							</button>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach?>
		<?php else:?>
			<tr><td colspan="6" class="text-center justify-content-center">No records to show!</td></tr>
		<?php endif?>
		</tbody>
	</table>

	<div class="modal fade" id="rejectReconciliationModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<form method="post" id="rejectReconciliationForm" class="modal-content">
				<?=csrf()?>
				<div class="modal-header">
					<h5 class="modal-title">Reject reconciliation</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div class="alert alert-warning py-2 mb-3">
						<div class="fw-semibold">This will allow the user to redo reconciliation for that day.</div>
						<div class="small" id="rejectReconciliationTarget"></div>
					</div>
					<label class="form-label" for="rejectReconciliationReason">Rejection reason</label>
					<textarea class="form-control" id="rejectReconciliationReason" name="rejection_reason" rows="4" placeholder="State the reason clearly" required></textarea>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-danger">Reject</button>
				</div>
			</form>
		</div>
	</div>

	<div class="modal fade" id="reconciliationReasonModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Rejection reason</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div class="small text-muted mb-2" id="reconciliationReasonMeta"></div>
					<div class="alert alert-danger mb-0" id="reconciliationReasonText"></div>
				</div>
			</div>
		</div>
	</div>
	<!-- Total Pages Display -->
    <p id="totalPagesText" class="text-center mt-3"></p>

    <!-- Pagination -->
    <nav id="paginationNav" aria-label="Page navigation" class="mt-1">
      <ul class="pagination justify-content-center" style="font-size: 8px;"></ul>
    </nav>

    <!-- Scripts -->
    <script>
document.addEventListener("DOMContentLoaded", function () {
        const rowsPerPage = 10;
        const table = document.getElementById("activityTable");
        const paginationNav = document.querySelector("#paginationNav .pagination");
        const searchInput = document.getElementById("reconSearchInput");
        const statusFilter = document.getElementById("reconStatusFilter");
        const remarksFilter = document.getElementById("reconRemarksFilter");
        const dateFromInput = document.getElementById("reconDateFrom");
        const dateToInput = document.getElementById("reconDateTo");
        const clearFiltersBtn = document.getElementById("reconClearFilters");
        const exportPdfBtn = document.getElementById("reconExportPdfBtn");
        const totalPagesText = document.getElementById("totalPagesText");
        if (!table || !paginationNav || !totalPagesText) return;

        const miniTabButtons = Array.from(document.querySelectorAll("#reconciliationMiniTabs [data-mini-tab-target]"));
        const miniTabPanes = Array.from(document.querySelectorAll("#reconciliationMiniTabContent .tab-pane"));
        function activateMiniTab(button) {
          const targetSelector = button?.getAttribute("data-mini-tab-target") || button?.getAttribute("data-bs-target") || "";
          const targetPane = targetSelector ? document.querySelector(targetSelector) : null;
          if (!targetPane) return;
          miniTabButtons.forEach((btn) => {
            const isActive = btn === button;
            btn.classList.toggle("active", isActive);
            btn.setAttribute("aria-selected", isActive ? "true" : "false");
          });
          miniTabPanes.forEach((pane) => {
            const isActive = pane === targetPane;
            pane.classList.toggle("active", isActive);
            pane.classList.toggle("show", isActive);
          });
        }
        miniTabButtons.forEach((button) => {
          button.addEventListener("click", function (event) {
            event.preventDefault();
            activateMiniTab(button);
          });
        });

        const lateReconSearchInput = document.getElementById("lateReconSearchInput");
        const lateReconStatusFilter = document.getElementById("lateReconStatusFilter");
        const lateReconClearFilters = document.getElementById("lateReconClearFilters");
        const lateReconRows = Array.from(document.querySelectorAll("tr[data-late-recon-row='1']"));
        const lateReconFilterSummary = document.getElementById("lateReconFilterSummary");
        const lateReconPagination = document.querySelector("#lateReconPaginationNav .pagination");
        const lateRowsPerPage = 10;
        let lateReconCurrentPage = 1;
        let lateReconFilteredRows = [...lateReconRows];
        function updateLateReconPager() {
          if (!lateReconPagination) return;
          lateReconPagination.innerHTML = "";
          const totalPages = Math.ceil(lateReconFilteredRows.length / lateRowsPerPage);
          if (totalPages <= 1) return;
          getPaginationRange(lateReconCurrentPage, totalPages).forEach((item) => {
            const li = document.createElement("li");
            li.className = "page-item" + (item === lateReconCurrentPage ? " active" : "");
            const a = document.createElement("a");
            a.className = "page-link";
            if (item === "...") {
              li.classList.add("disabled");
              a.textContent = "...";
            } else {
              a.href = "#";
              a.textContent = item;
              a.addEventListener("click", function (event) {
                event.preventDefault();
                if (typeof item === "number") displayLateReconPage(item);
              });
            }
            li.appendChild(a);
            lateReconPagination.appendChild(li);
          });
        }
        function displayLateReconPage(page) {
          const totalPages = Math.max(1, Math.ceil(lateReconFilteredRows.length / lateRowsPerPage));
          lateReconCurrentPage = Math.min(Math.max(1, page), totalPages);
          const start = (lateReconCurrentPage - 1) * lateRowsPerPage;
          const end = start + lateRowsPerPage;
          lateReconRows.forEach((row) => {
            row.style.display = "none";
          });
          lateReconFilteredRows.forEach((row, index) => {
            row.style.display = index >= start && index < end ? "" : "none";
          });
          updateLateReconPager();
        }
        function applyLateReconFilters() {
          const term = (lateReconSearchInput?.value || "").trim().toLowerCase();
          const status = (lateReconStatusFilter?.value || "").trim().toLowerCase();
          lateReconFilteredRows = lateReconRows.filter((row) => {
            const rowSearch = row.getAttribute("data-late-search") || row.textContent.toLowerCase();
            const rowStatus = (row.getAttribute("data-late-status") || "").toLowerCase();
            return (!term || rowSearch.includes(term)) && (!status || rowStatus === status);
          });
          displayLateReconPage(1);
          if (lateReconFilterSummary) {
            lateReconFilterSummary.textContent = lateReconRows.length
              ? `${lateReconFilteredRows.length} of ${lateReconRows.length} late reconciliation drawer(s) shown`
              : "";
          }
        }
        [lateReconSearchInput, lateReconStatusFilter].forEach((control) => {
          control?.addEventListener("input", applyLateReconFilters);
          control?.addEventListener("change", applyLateReconFilters);
        });
        lateReconClearFilters?.addEventListener("click", function () {
          if (lateReconSearchInput) lateReconSearchInput.value = "";
          if (lateReconStatusFilter) lateReconStatusFilter.value = "";
          applyLateReconFilters();
          lateReconSearchInput?.focus();
        });
        applyLateReconFilters();

        let currentPage = 1;
        let originalRows = Array.from(table.querySelectorAll("tr[data-recon-row='1']")); // Store the original rows for filtering
        let filteredRows = [...originalRows]; // To hold filtered rows

        // Function to display the current page
        function displayPage(page) {
          const start = (page - 1) * rowsPerPage;
          const end = start + rowsPerPage;

          // Hide all rows initially
          originalRows.forEach((row) => {
            row.style.display = "none";
          });
          filteredRows.forEach((row, index) => {
            row.style.display = index >= start && index < end ? "" : "none";
          });

          currentPage = page;
          updatePagination();
        }

        // Function to update pagination controls
        function updatePagination() {
          paginationNav.innerHTML = "";
          const totalPages = Math.ceil(filteredRows.length / rowsPerPage);

          // Update total pages display
          totalPagesText.textContent = filteredRows.length ? `Total Pages: ${totalPages}` : "No reconciliations match the current filters";
          if (!filteredRows.length) {
            return;
          }

          // Generate pagination range
          const paginationRange = getPaginationRange(currentPage, totalPages);

          // Render pagination navigation with ellipsis
          paginationRange.forEach((item) => {
            const li = document.createElement("li");
            li.className = "page-item" + (item === currentPage ? " active" : "");
            const a = document.createElement("a");
            a.className = "page-link";

            if (item === "...") {
              li.classList.add("disabled");
              a.textContent = "...";
            } else {
              a.textContent = item;
              a.href = "#";
              a.addEventListener("click", (e) => {
                e.preventDefault();
                if (typeof item === "number") displayPage(item);
              });
            }

            li.appendChild(a);
            paginationNav.appendChild(li);
          });
        }

        // Function to calculate pagination range with ellipsis
        function getPaginationRange(current, total) {
          const range = [];
          const maxVisiblePages = 10;

          if (total <= maxVisiblePages) {
            // Show all pages if total <= maxVisiblePages
            for (let i = 1; i <= total; i++) range.push(i);
          } else {
            range.push(1); // Always include the first page

            const start = Math.max(2, current - 1); // Start from one page before the current page
            for (let i = start; i < Math.min(start + 3, total - 1); i++) {
              range.push(i);
            }

            if (current < total - 3) range.push("..."); // Add ellipsis if necessary

            range.push(total - 1); // Include the second-to-last page
            range.push(total); // Include the last page
          }

          return range;
        }

        function applyFilters() {
          const term = (searchInput?.value || "").trim().toLowerCase();
          const status = (statusFilter?.value || "").trim().toLowerCase();
          const remarks = (remarksFilter?.value || "").trim().toLowerCase();
          const fromDate = dateFromInput?.value || "";
          const toDate = dateToInput?.value || "";

          filteredRows = originalRows.filter((row) => {
            const rowSearch = row.getAttribute("data-recon-search") || row.textContent.toLowerCase();
            const rowStatus = (row.getAttribute("data-recon-status") || "").toLowerCase();
            const rowRemarks = (row.getAttribute("data-recon-remarks") || "").toLowerCase();
            const rowDate = row.getAttribute("data-recon-date") || "";

            if (term && !rowSearch.includes(term)) return false;
            if (status && rowStatus !== status) return false;
            if (remarks && rowRemarks !== remarks) return false;
            if (fromDate && rowDate && rowDate < fromDate) return false;
            if (toDate && rowDate && rowDate > toDate) return false;
            return true;
          });

          displayPage(1);
        }

        // Initialize the table with the first page
        displayPage(1);
        [searchInput, statusFilter, remarksFilter, dateFromInput, dateToInput].forEach((control) => {
          control?.addEventListener("input", applyFilters);
          control?.addEventListener("change", applyFilters);
        });
        clearFiltersBtn?.addEventListener("click", function () {
          [searchInput, statusFilter, remarksFilter, dateFromInput, dateToInput].forEach((control) => {
            if (control) control.value = "";
          });
          applyFilters();
          searchInput?.focus();
        });

        exportPdfBtn?.addEventListener("click", function () {
          const params = new URLSearchParams({
            entity: "reconciliations",
            resource: "list",
            format: "pdf"
          });
          const values = {
            find: searchInput?.value || "",
            status: statusFilter?.value || "",
            remarks: remarksFilter?.value || "",
            date_from: dateFromInput?.value || "",
            date_to: dateToInput?.value || ""
          };
          Object.entries(values).forEach(([key, value]) => {
            value = String(value || "").trim();
            if (value) params.set(key, value);
          });
          window.open("<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/export?" + params.toString(), "reconciliations_pdf", "width=1000,height=700");
        });

        const rejectForm = document.getElementById("rejectReconciliationForm");
        const rejectTarget = document.getElementById("rejectReconciliationTarget");
        const rejectReason = document.getElementById("rejectReconciliationReason");
        document.querySelectorAll(".recon-reject-btn").forEach((btn) => {
          btn.addEventListener("click", function () {
            if (rejectForm) {
              rejectForm.action = btn.getAttribute("data-action") || "";
            }
            if (rejectTarget) {
              const agent = btn.getAttribute("data-agent") || "this user";
              const date = btn.getAttribute("data-date") || "";
              rejectTarget.textContent = agent + (date ? " - " + date : "");
            }
            if (rejectReason) {
              rejectReason.value = "";
              setTimeout(() => rejectReason.focus(), 250);
            }
          });
        });

        const reasonMeta = document.getElementById("reconciliationReasonMeta");
        const reasonText = document.getElementById("reconciliationReasonText");
        document.querySelectorAll(".recon-reason-btn").forEach((btn) => {
          btn.addEventListener("click", function () {
            const agent = btn.getAttribute("data-agent") || "";
            const date = btn.getAttribute("data-date") || "";
            if (reasonMeta) {
              reasonMeta.textContent = [agent, date].filter(Boolean).join(" - ");
            }
            if (reasonText) {
              reasonText.textContent = btn.getAttribute("data-reason") || "No rejection reason captured.";
            }
          });
        });
      });
    </script>
</div>
<?php if($canGrantLateReconciliationAccess): ?>
      </div>
    </div>
<?php endif; ?>
