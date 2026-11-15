<?php
	$reviewOld = get_value('old_post');
	$reviewOld = is_array($reviewOld) ? $reviewOld : [];
	$rejectReasonValue = trim((string)($reviewOld['reject_reason'] ?? ($row->reject_reason ?? '')));
	$assignedId = backoffice_commission_assignee_id($row);
	$assignedName = trim((string)($row->assigned_first_name ?? $row->payto_first_name ?? '') . ' ' . (string)($row->assigned_last_name ?? $row->payto_last_name ?? ''));
	$reviewSession = get_session();
	$viewerId = (int)($reviewSession->id ?? 0);
	$canReviewAnyCommission = backoffice_user_is_admin($reviewSession) || backoffice_user_is_operations_manager($reviewSession);
	$canAct = in_array(strtolower(trim((string)($row->transaction_status ?? ''))), ['pending', 'draft'], true)
		&& ($assignedId === $viewerId || $canReviewAnyCommission);
	$children = !empty($bulkChildren) ? $bulkChildren : [$row];
?>

<div class="card col-lg-10 mx-auto shadow-sm">
	<div class="card-header">
		<div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
			<div>
				<h4 class="mb-1">Commission Review</h4>
				<div class="text-muted">Assigned SIM commissions waiting for review by the assigned reviewer, admin, or operations manager.</div>
			</div>
			<div class="text-lg-end">
				<div class="fw-semibold"><?=esc($assignedName !== '' ? $assignedName : 'Not assigned')?></div>
				<div class="text-muted small">Assigned reviewer</div>
			</div>
		</div>
	</div>

	<div class="card-body">
		<?php if(!empty($errors['review_action'])): ?>
			<div class="alert alert-danger"><?=$errors['review_action']?></div>
		<?php endif; ?>

		<div class="row g-3 mb-4">
			<div class="col-md-3">
				<div class="text-muted small">Date Submitted</div>
				<div class="fw-semibold"><?=get_date_time($row->date_created ?? '')?></div>
			</div>
			<div class="col-md-3">
				<div class="text-muted small">Date Received</div>
				<div class="fw-semibold"><?=get_date($row->date_received ?? '')?></div>
			</div>
			<div class="col-md-3">
				<div class="text-muted small">Created By</div>
				<div class="fw-semibold"><?=esc(trim((string)($row->user_first_name ?? '') . ' ' . (string)($row->user_last_name ?? '')))?></div>
			</div>
			<div class="col-md-3">
				<div class="text-muted small">Status</div>
				<div class="fw-semibold"><?=esc($row->transaction_status ?? '')?></div>
			</div>
		</div>

		<?php if(trim((string)($row->description ?? '')) !== ''): ?>
			<div class="alert alert-light border">
				<div class="text-muted small mb-1">Creator Note</div>
				<div><?=esc($row->description)?></div>
			</div>
		<?php endif; ?>

		<div class="table-responsive">
			<table class="table table-bordered align-middle">
				<thead class="">
					<tr>
						<th style="width: 40px;">#</th>
						<th style="width:300px;">Network</th>
						<th style="width: 170px;">Amount</th>
						<th style="width: 120px;">Proof</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($children as $index => $child): ?>
						<?php
							$snapshot = json_decode((string)($child->assignment_snapshot_json ?? ''), true);
							$snapshot = is_array($snapshot) ? $snapshot : [];
							$detailLabel = backoffice_commission_assignment_label($snapshot);
							$proofImage = trim((string)($child->proof_image ?? ''));
							$proofImageUrl = $proofImage !== '' ? get_image($proofImage) : '';
							$commissionLabel = trim((string)($snapshot['commission_label'] ?? ($child->commission_type ?? 'Commission')));
							$expectedDay = (int)($snapshot['expected_day'] ?? 0);
							$expectedLabels = [];
							foreach (backoffice_commission_day_fields() as $field => $label) {
								$days = (int)($snapshot[$field] ?? 0);
								if ($days > 0) {
									$simTypeForLabel = strtolower(trim((string)($snapshot['sim_type'] ?? '')));
									$displayLabel = backoffice_commission_day_payment_label($field, $label, $simTypeForLabel);
									$expectedLabels[] = $displayLabel . ': every ' . backoffice_commission_expected_interval_label($days);
								}
							}
						?>
						<tr>
							<td><?=$index + 1?></td>
							<td>
								<div class="fw-semibold"><?=esc($child->network ?? ($snapshot['network'] ?? ''))?></div>
								<div class="mt-1"><span class="badge bg-secondary text-dark border"><?=esc($commissionLabel)?></span></div>
								<?php if($expectedDay > 0): ?>
									<div class="text-muted small">Collected every <?=esc(backoffice_commission_expected_interval_label($expectedDay))?></div>
								<?php elseif(!empty($expectedLabels)): ?>
									<div class="text-muted small"><?=esc(implode(' | ', $expectedLabels))?></div>
								<?php endif; ?>
								<?php if($detailLabel !== ''): ?>
									<div class="text-muted small"><?=esc($detailLabel)?></div>
								<?php endif; ?>
							</td>
							<td class="text-end fw-semibold"><?=number_format((float)($child->amount ?? 0), 2)?></td>
							<td>
								<?php if($proofImageUrl !== ''): ?>
									<a href="<?=esc($proofImageUrl)?>" target="_blank" rel="noopener">
										<img src="<?=esc($proofImageUrl)?>" alt="Proof image" style="width:68px;height:52px;object-fit:cover;border-radius:10px;border:1px solid rgba(0,0,0,0.08);">
									</a>
								<?php else: ?>
									<span class="text-muted">No proof</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
				<tfoot>
					<tr>
						<th colspan="2" class="text-end">Total</th>
						<th class="text-end"><?=number_format((float)($row->amount ?? 0), 2)?></th>
						<th></th>
					</tr>
				</tfoot>
			</table>
		</div>

		<?php if(trim((string)($row->reject_reason ?? '')) !== ''): ?>
			<div class="alert alert-danger mt-3 mb-0">
				<div class="fw-semibold">Reject Reason</div>
				<div><?=esc($row->reject_reason)?></div>
			</div>
		<?php endif; ?>
	</div>

	<div class="card-footer">
		<div class="d-flex flex-column gap-3">
			<?php if($canAct): ?>
				<form method="post" class="border rounded p-3">
					<?=csrf()?>
					<input type="hidden" name="review_action" value="reject">
					<label class="form-label">Reject Reason</label>
					<textarea class="form-control" rows="3" name="reject_reason" placeholder="Explain why this commission is being rejected"><?=esc($rejectReasonValue)?></textarea>
					<?php if(!empty($errors['reject_reason'])): ?>
						<small class="text-danger"><?=$errors['reject_reason']?></small>
					<?php endif; ?>
					<div class="mt-3">
						<button type="submit" class="btn btn-outline-danger">Reject Commission</button>
					</div>
				</form>

				<form method="post" class="d-inline">
					<?=csrf()?>
					<input type="hidden" name="review_action" value="approve">
					<button type="submit" class="btn btn-success">Approve And Credit Cashdrawer</button>
				</form>
			<?php else: ?>
				<div class="alert alert-info mb-0">Only the assigned reviewer, admin, or operations manager can approve or reject this commission.</div>
			<?php endif; ?>

			<div class="d-flex justify-content-between">
				<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>" class="btn btn-outline-secondary">
					<i class="fa-solid fa-chevron-left"></i> Back
				</a>
				<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view_commission/<?=$row->id?>" class="btn btn-outline-primary">Open Details</a>
			</div>
		</div>
	</div>
</div>
