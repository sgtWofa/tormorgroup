<?php if(user_can('add_payment')): ?>
	<?php
		$__old_post = get_value('old_post');
		if (is_array($__old_post) && !empty($__old_post)) {
			$_POST = $__old_post;
			set_value('old_post', []);
		}

		$postedRows = isset($_POST['rows']) && is_array($_POST['rows']) ? $_POST['rows'] : [];
		$dateReceivedValue = old_date('date_received', date('Y-m-d'));
		$assignedValue = (int)old_value('assigned');
		$descriptionValue = old_value('description');
		$assignedSims = is_array($assignedSims ?? null) ? $assignedSims : [];
		$users = is_array($users ?? null) ? $users : [];
		$canSelfAssignCommission = function_exists('backoffice_commission_creator_can_self_assign')
			? backoffice_commission_creator_can_self_assign(get_session())
			: false;
		$overdueAssignments = backoffice_overdue_commission_assignments((int)(get_session()->id ?? 0));
		$overdueAssignments = backoffice_commission_filter_overdue_assignments_for_session($overdueAssignments, get_session());
		$overdueAssignmentIds = [];
		$overdueAssignmentMap = [];
		foreach ($overdueAssignments as $overdueAssignment) {
			$assignmentId = (int)($overdueAssignment['assignment']->id ?? 0);
			$commissionField = trim((string)($overdueAssignment['commission_field'] ?? ''));
			if ($assignmentId <= 0) {
				continue;
			}
			$overdueAssignmentIds[] = $assignmentId;
			$overdueKey = $assignmentId . '|' . $commissionField;
			if (empty($overdueAssignmentMap[$overdueKey])) {
				$overdueAssignmentMap[$overdueKey] = [];
			}
			$overdueAssignmentMap[$overdueKey][] = $overdueAssignment;
		}
		$overdueAssignmentIds = array_values(array_unique(array_filter($overdueAssignmentIds)));
		$commissionRows = [];
		foreach ($assignedSims as $sim) {
			foreach (backoffice_commission_assignment_payment_lines($sim) as $line) {
				$commissionRows[] = $line;
			}
		}
	?>
	<style>
		.bo-commission-card {
			border: 1px solid rgba(25, 135, 84, 0.12);
			border-radius: 18px;
			box-shadow: 0 18px 48px rgba(15, 23, 42, 0.08);
		}
		.bo-commission-card .table th,
		.bo-commission-card .table td {
			vertical-align: middle;
		}
		.bo-commission-meta {
			font-size: 12px;
			color: #64748b;
		}
		.commission-proof-thumb {
			width: 48px;
			height: 40px;
			border-radius: 10px;
			border: 1px dashed rgba(13,110,253,0.35);
			background: rgba(13,110,253,0.04);
			display: inline-flex;
			align-items: center;
			justify-content: center;
			overflow: hidden;
			cursor: pointer;
		}
		.commission-proof-thumb img {
			width: 100%;
			height: 100%;
			object-fit: cover;
			display: block;
		}
		.commission-proof-placeholder {
			color: rgba(0,0,0,0.55);
			font-size: 14px;
			line-height: 1;
		}
		.commission-proof-needed td {
			background: rgba(255, 193, 7, 0.08);
		}
	</style>

	<form method="post" enctype="multipart/form-data">
		<div class="bo-commission-card p-3 p-lg-4 col-12 mx-auto">
			<?=csrf()?>
			<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
				<div>
					<h4 class="mb-1">Other Commissions</h4>
					<div class="text-muted">Fill amounts only for the commission payments received, then attach proof for each filled row.</div>
				</div>
				<div class="text-lg-end">
					<div class="fw-semibold" id="commissionSelectedCount">0 commission rows selected</div>
					<div class="bo-commission-meta">Photo proof is required for every row with an amount.</div>
				</div>
			</div>

			<?php if(!empty($overdueAssignments)): ?>
				<div class="alert alert-warning" data-attention-sound="1" data-attention-key="pay-commission-overdue-<?=date('Y-m-d')?>-<?=count($overdueAssignments)?>">
					<div class="fw-semibold mb-1">Overdue commission submission</div>
					<div><?=esc(backoffice_overdue_commission_message($overdueAssignments))?></div>
				</div>
			<?php endif; ?>

			<?php if(!empty($errors['rows'])): ?>
				<div class="alert alert-danger"><?=$errors['rows']?></div>
			<?php endif; ?>
			<?php if(empty($commissionRows)): ?>
				<div class="alert alert-warning mb-3">No assigned SIMs were found in Users Management for your account.</div>
			<?php else: ?>
				<div class="table-responsive">
					<table class="table table-bordered align-middle">
						<thead>
							<tr>
								<th style="width: 40px;">#</th>
								<th style="width:300px;">Network</th>
								<th style="width: 170px;">Amount</th>
								<th style="width: 120px;">Proof</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($commissionRows as $index => $commissionRow): ?>
								<?php
									$rowPost = $postedRows[$index] ?? [];
									$sim = $commissionRow['assignment'] ?? null;
									$snapshot = is_array($commissionRow['snapshot'] ?? null) ? $commissionRow['snapshot'] : backoffice_commission_assignment_snapshot($sim);
									$detailLabel = backoffice_commission_assignment_label($snapshot);
									$commissionField = trim((string)($commissionRow['commission_field'] ?? ($snapshot['commission_field'] ?? 'general')));
									$commissionLabel = trim((string)($commissionRow['commission_label'] ?? ($snapshot['commission_label'] ?? ($snapshot['commission_type'] ?? 'Commission'))));
									$expectedDay = (int)($commissionRow['expected_day'] ?? ($snapshot['expected_day'] ?? 0));
									$amountValue = trim((string)($rowPost['amount'] ?? ''));
									$existingProof = trim((string)($rowPost['existing_proof'] ?? ''));
									$amountErrorKey = 'amount_' . $index;
									$proofErrorKey = 'proof_image_' . $index;
									$hasExistingProof = $existingProof !== '';
									$needsProofHighlight = $amountValue !== '' && (float)$amountValue > 0;
									$assignmentId = (int)($snapshot['id'] ?? 0);
									$overdueKey = $assignmentId . '|' . $commissionField;
									$overdueMetas = $overdueAssignmentMap[$overdueKey] ?? [];
									$isOverdueRow = !empty($overdueMetas);
									$expectedLabels = [];
									foreach (backoffice_commission_day_fields() as $field => $label) {
										$days = (int)($snapshot[$field] ?? 0);
										if ($days > 0) {
											$simTypeForLabel = strtolower(trim((string)($snapshot['sim_type'] ?? '')));
											$displayLabel = backoffice_commission_day_payment_label($field, $label, $simTypeForLabel);
											$expectedLabels[] = $displayLabel . ': every ' . backoffice_commission_expected_interval_label($days);
										}
									}
									$overdueLabels = [];
									foreach ($overdueMetas as $overdueMeta) {
										$dueAtLabel = '';
										if (!empty($overdueMeta['due_at']) && $overdueMeta['due_at'] instanceof DateTimeInterface) {
											$dueAtLabel = $overdueMeta['due_at']->format('jS M, Y');
										}
										$overdueLabels[] = trim((string)($overdueMeta['commission_label'] ?? 'Commission') . ($dueAtLabel !== '' ? ' due since ' . $dueAtLabel : ''));
									}
								?>
								<tr class="<?=trim(($needsProofHighlight ? 'commission-proof-needed ' : '') . ($isOverdueRow ? 'table-warning' : ''))?>">
									<td>
										<?=$index + 1?>
										<input type="hidden" name="rows[<?=$index?>][sim_assignment_id]" value="<?=esc((string)($snapshot['id'] ?? 0))?>">
										<input type="hidden" name="rows[<?=$index?>][commission_field]" value="<?=esc($commissionField)?>">
									</td>
									<td>
										<div class="fw-semibold"><?=esc($snapshot['network'] ?? '')?></div>
										<div class="mt-1"><span class="badge bg-secondary text-dark border"><?=esc($commissionLabel)?></span></div>
										<?php if($expectedDay > 0): ?>
											<div class="bo-commission-meta">Collected every <?=esc(backoffice_commission_expected_interval_label($expectedDay))?></div>
										<?php elseif(!empty($expectedLabels)): ?>
											<div class="bo-commission-meta"><?=esc(implode(' | ', $expectedLabels))?></div>
										<?php endif; ?>
										<?php if($isOverdueRow): ?>
											<div><span class="badge bg-danger mt-1">Overdue</span></div>
											<?php if(!empty($overdueLabels)): ?>
												<div class="bo-commission-meta"><?=esc(implode(' | ', $overdueLabels))?></div>
											<?php endif; ?>
										<?php endif; ?>
									</td>
									<!-- <td>
										<div><?=esc($detailLabel)?></div>
										<div class="bo-commission-meta"><?=esc($commissionLabel)?></div>
										<?php if(($snapshot['notes'] ?? '') !== ''): ?>
											<div class="bo-commission-meta"><?=esc($snapshot['notes'])?></div>
										<?php endif; ?>
									</td> -->
									<td>
										<input
											type="number"
											step="0.01"
											min="0"
											class="form-control commission-amount-input <?=!empty($errors[$amountErrorKey]) ? 'is-invalid' : ''?>"
											id="commission_amount_<?=$index?>"
											name="rows[<?=$index?>][amount]"
											value="<?=esc($amountValue)?>"
											placeholder="0.00"
										>
										<?php if(!empty($errors[$amountErrorKey])): ?>
											<small class="text-danger"><?=$errors[$amountErrorKey]?></small>
										<?php endif; ?>
									</td>
									<td>
										<input type="hidden" name="rows[<?=$index?>][existing_proof]" value="<?=esc($existingProof)?>">
										<input type="file" class="d-none commission-proof-input" name="proof_image_<?=$index?>" accept="image/*">
										<button type="button" class="commission-proof-thumb <?=!empty($errors[$proofErrorKey]) ? 'border border-danger' : ''?>" data-proof-index="<?=$index?>" aria-label="Upload proof image">
											<img class="<?=$hasExistingProof ? '' : 'd-none'?>" alt="Proof preview" <?=$hasExistingProof ? 'src="' . esc(get_image($existingProof)) . '"' : ''?>>
											<span class="commission-proof-placeholder <?=$hasExistingProof ? 'd-none' : ''?>"><i class="fa-solid fa-camera"></i></span>
										</button>
										<?php if(!empty($errors[$proofErrorKey])): ?>
											<small class="text-danger d-block"><?=$errors[$proofErrorKey]?></small>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>

			<div class="row g-3 mb-3">
				<div class="col-md-3">
					<label class="form-label">Date Received</label>
					<input type="date" class="form-control" name="date_received" value="<?=esc($dateReceivedValue)?>">
					<?php if(!empty($errors['date_received'])): ?>
						<small class="text-danger"><?=$errors['date_received']?></small>
					<?php endif; ?>
				</div>
				<div class="col-md-5">
					<label class="form-label">Assign To</label>
					<select class="form-select" name="assigned" id="commissionAssignedTo" required>
						<option value=""><?=$canSelfAssignCommission ? 'Select general manager, admin, or yourself' : 'Select general manager or admin'?></option>
						<?php foreach ($users as $user): ?>
							<?php
								$userId = (int)($user->id ?? 0);
								$roleNames = $user->roles ?? [];
								if (!is_array($roleNames)) {
									$roleNames = [$roleNames];
								}
								$roleLabel = trim((string)($roleNames[0] ?? ''));
							?>
							<option value="<?=$userId?>" <?=$assignedValue === $userId ? 'selected' : ''?>>
								<?=esc(trim((string)($user->first_name ?? '') . ' ' . (string)($user->last_name ?? '')))?>
								<?=$roleLabel !== '' ? ' [' . esc($roleLabel) . ']' : ''?>
							</option>
						<?php endforeach; ?>
					</select>
					<?php if(!empty($errors['assigned'])): ?>
						<small class="text-danger"><?=$errors['assigned']?></small>
					<?php elseif(empty($users)): ?>
						<small class="text-warning">Only admins or general managers with a cashdrawer opened today can be assigned. If you are an admin or general manager, you may also assign to yourself.</small>
					<?php else: ?>
						<small class="text-muted"><?=$canSelfAssignCommission ? 'Admins and general managers present are listed.' : 'Only admins or general managers today are listed.'?></small>
					<?php endif; ?>
				</div>
				<div class="col-md-4">
					<label class="form-label">Notes</label>
					<textarea class="form-control" rows="2" name="description" placeholder="Optional note for the reviewer"><?=esc($descriptionValue)?></textarea>
				</div>
			</div>

			<div class="d-flex justify-content-between align-items-center mt-4">
				<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>" class="btn btn-outline-secondary">
					<i class="fa-solid fa-chevron-left"></i> Back
				</a>
				<button class="btn btn-success" type="submit" id="commissionSubmitBtn" <?=empty($commissionRows) ? 'disabled' : ''?>>
					<i class="fa-solid fa-save"></i> Submit For Approval
				</button>
			</div>
		</div>
	</form>
<?php else: ?>
	<div class="alert alert-danger text-center">
		Access denied. You don’t have permission for this action.
	</div>
<?php endif ?>

<script>
<?=get_app_image_cropper_inline_source()?>

document.addEventListener('DOMContentLoaded', function(){
	const amountInputs = document.querySelectorAll('.commission-amount-input');
	const proofInputs = document.querySelectorAll('.commission-proof-input');
	const proofThumbs = document.querySelectorAll('.commission-proof-thumb');
	const submitBtn = document.getElementById('commissionSubmitBtn');
	const assignedSelect = document.getElementById('commissionAssignedTo');
	const selectedCount = document.getElementById('commissionSelectedCount');

	function setProofPreview(idx, src){
		const thumb = document.querySelector('.commission-proof-thumb[data-proof-index="' + idx + '"]');
		if (!thumb) {
			return;
		}

		const img = thumb.querySelector('img');
		const placeholder = thumb.querySelector('.commission-proof-placeholder');
		if (!img) {
			return;
		}

		if (src) {
			img.src = src;
			img.classList.remove('d-none');
			if (placeholder) {
				placeholder.classList.add('d-none');
			}
			return;
		}

		img.removeAttribute('src');
		img.classList.add('d-none');
		if (placeholder) {
			placeholder.classList.remove('d-none');
		}
	}

	function refreshProofPreviewFromInput(input, idx){
		if (!input || !input.files || !input.files[0]) {
			setProofPreview(idx, '');
			return Promise.resolve(false);
		}

		return new Promise(function(resolve){
			const reader = new FileReader();
			reader.onload = function (e) {
				setProofPreview(idx, String(e.target && e.target.result ? e.target.result : ''));
				resolve(true);
			};
			reader.onerror = function(){
				setProofPreview(idx, '');
				resolve(false);
			};
			reader.readAsDataURL(input.files[0]);
		});
	}

	function toggleSubmitButton(){
		let hasAmount = false;
		let proofsOk = true;

		amountInputs.forEach(function(input){
			const idx = String(input.id || '').replace('commission_amount_', '');
			const raw = String(input.value || '').trim();
			const amount = raw === '' ? 0 : parseFloat(raw);
			if (!raw || !isFinite(amount) || amount <= 0) {
				return;
			}

			hasAmount = true;
			const fileInput = document.querySelector('input[name="proof_image_' + idx + '"]');
			const existingInput = document.querySelector('input[name="rows[' + idx + '][existing_proof]"]');
			const hasExisting = !!(existingInput && String(existingInput.value || '').trim() !== '');
			const hasFile = !!(fileInput && fileInput.files && fileInput.files.length > 0);
			if (!hasExisting && !hasFile) {
				proofsOk = false;
			}
		});

		if (selectedCount) {
			selectedCount.textContent = hasAmount
				? Array.from(amountInputs).filter(function(input){
					const raw = String(input.value || '').trim();
					return raw !== '' && parseFloat(raw) > 0;
				}).length + ' commission rows selected'
				: '0 commission rows selected';
		}

		const hasAssignee = !!(assignedSelect && String(assignedSelect.value || '').trim() !== '');
		if (submitBtn) {
			submitBtn.disabled = !(hasAmount && proofsOk && hasAssignee);
		}
	}

	amountInputs.forEach(function(input){
		input.addEventListener('input', toggleSubmitButton);
	});

	proofInputs.forEach(function(input){
		input.addEventListener('change', function(){
			const name = input.getAttribute('name') || '';
			const match = name.match(/proof_image_(\d+)/);
			const idx = match ? match[1] : null;
			if (idx === null) {
				toggleSubmitButton();
				return;
			}

			const thumb = document.querySelector('.commission-proof-thumb[data-proof-index="' + idx + '"]');
			if (!thumb) {
				toggleSubmitButton();
				return;
			}

			const img = thumb.querySelector('img');
			const placeholder = thumb.querySelector('.commission-proof-placeholder');
			const existingInput = document.querySelector('input[name="rows[' + idx + '][existing_proof]"]');

			if (window.AppImageCropper && typeof window.AppImageCropper.handleLegacyChange === 'function') {
				window.AppImageCropper.handleLegacyChange({ currentTarget: input }, {
					previewEl: img,
					placeholderEl: placeholder,
					maxBytes: window.AppImageCropper.MAX_BYTES,
					modalTitle: 'Crop commission proof',
					errorTitle: 'Invalid commission proof',
				}).then(function(result){
					if (result && result.cancelled) {
						toggleSubmitButton();
						return;
					}
					if (existingInput) {
						existingInput.value = '';
					}
					refreshProofPreviewFromInput(input, idx).then(function(){
						toggleSubmitButton();
					});
				}).catch(function(){
					toggleSubmitButton();
				});
				return;
			}

			if (input.files && input.files[0]) {
				if (existingInput) {
					existingInput.value = '';
				}
				refreshProofPreviewFromInput(input, idx).then(function(){
					toggleSubmitButton();
				});
				return;
			}

			toggleSubmitButton();
		});
	});

	proofThumbs.forEach(function(thumb){
		thumb.addEventListener('click', function(){
			const idx = thumb.getAttribute('data-proof-index');
			const input = document.querySelector('input[name="proof_image_' + idx + '"]');
			if (input) {
				input.click();
			}
		});
	});

	if (assignedSelect) {
		assignedSelect.addEventListener('change', toggleSubmitButton);
	}

	toggleSubmitButton();
});
</script>
