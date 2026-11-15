<?php if (user_can('edit_payment_service')): ?>
	<?php
		$editErrors = $errors ?? [];
		$eligibleAssignees = $eligible_assignees ?? [];
		$isDeclined = !empty($row) ? payment_service_status_is_declined($row->transaction_status ?? '') : false;
		$isApprovedLocked = !empty($row) ? payment_service_status_is_approved($row->transaction_status ?? '') : false;
		$currentStatus = payment_service_normalize_status(old_value('transaction_status', $row->transaction_status ?? 'Pending'), $row->transaction_status ?? 'Pending');
		$categories = ['UTILITY', 'TV & ENTERTAINMENT', 'TERTIARY', 'INSURANCE'];
		$paymentMediums = ['Telecos', 'Bank'];
		$networkOptions = ['MTN', 'AT', 'Telecel', 'G-Money', 'GhanaPay', 'GHQR'];
		$bankOptions = ['ABSA', 'Access Bank', 'ADB', 'APEX BANK', 'Bank of Africa', 'CBG', 'Cal Bank', 'Ecobank', 'Fidelity', 'First Atlantic Bank', 'First Bank Ghana', 'FBN', 'FNB', 'Gtbank', 'GCB', 'NIB', 'Republic Bank', 'OmniBSIC Bank', 'Society General', 'Stanbic Bank', 'Prudential Bank', 'Standard Charted', 'Zenit Bank', 'umb'];
	?>
	<style>
		.ps-edit-wrap{max-width:960px;margin:40px auto;}
		.ps-edit-card{border:1px solid var(--bs-border-color);border-radius:1.25rem;padding:1.5rem;box-shadow:0 20px 45px rgba(15,23,42,.08);background:var(--bs-body-bg);}
		.ps-summary-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-top:1rem;}
		.ps-summary-item{border:1px solid rgba(148,163,184,.25);border-radius:1rem;padding:.9rem 1rem;background:rgba(148,163,184,.06);}
		.ps-summary-item small{display:block;color:var(--bs-secondary-color);text-transform:uppercase;letter-spacing:.06em;font-size:.7rem;margin-bottom:.3rem;}
		.ps-preview{border:1px dashed rgba(148,163,184,.35);border-radius:1rem;padding:1rem;background:rgba(148,163,184,.05);}
		.ps-preview img{width:100%;max-height:240px;object-fit:contain;border-radius:.75rem;}
	</style>

	<?php if (!empty($row)): ?>
		<div class="ps-edit-wrap">
			<?php if ($fail = message_fail('', true)): ?>
				<div class="alert alert-danger shadow-sm"><?=$fail?></div>
			<?php endif; ?>
			<?php if ($success = message_success('', true)): ?>
				<div class="alert alert-success shadow-sm"><?=$success?></div>
			<?php endif; ?>

			<?php if ($isDeclined): ?>
				<div class="alert alert-warning shadow-sm">
					This transaction is already declined. To keep the records consistent, it can no longer be edited.
				</div>
				<div class="d-flex justify-content-between align-items-center mt-3">
					<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>" class="btn btn-outline-primary">
						<i class="fa-solid fa-chevron-left"></i> Back
					</a>
					<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$row->id?>" class="btn btn-outline-secondary">
						<i class="fa-solid fa-eye"></i> View
					</a>
				</div>
			<?php else: ?>
				<form method="post" enctype="multipart/form-data" class="ps-edit-card" id="paymentServiceEditForm">
					<?=csrf()?>

					<div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
						<div>
							<h3 class="mb-1">Edit Payment Service</h3>
							<p class="text-muted mb-0">Reference: PS<?=esc($row->id)?></p>
						</div>
						<div class="text-end">
							<div class="text-muted small">Created</div>
							<div class="fw-semibold"><?=get_date_time($row->date_created ?? '')?></div>
						</div>
					</div>

					<?php if ($isApprovedLocked): ?>
						<div class="alert alert-warning mt-4 mb-3">
							This transaction is already approved. To keep accounting consistent, only the status can be changed to Declined, and a rejection reason is required.
						</div>

						<div class="ps-summary-grid">
							<div class="ps-summary-item">
								<small>Category</small>
								<div class="fw-semibold"><?=esc($row->category ?? '—')?></div>
							</div>
							<div class="ps-summary-item">
								<small>Institution</small>
								<div class="fw-semibold"><?=esc($row->institution ?? '—')?></div>
							</div>
							<div class="ps-summary-item">
								<small>Customer Phone</small>
								<div class="fw-semibold"><?=esc($row->customer_phone ?? '—')?></div>
							</div>
							<div class="ps-summary-item">
								<small>Payment Medium</small>
								<div class="fw-semibold"><?=esc($row->payment_medium ?? '—')?><?=!empty($row->network) ? ' · ' . esc($row->network) : ''?><?=!empty($row->bank) ? ' · ' . esc($row->bank) : ''?></div>
							</div>
							<div class="ps-summary-item">
								<small>Amount</small>
								<div class="fw-semibold"><?=number_format((float)($row->amount ?? 0), 2)?></div>
							</div>
							<div class="ps-summary-item">
								<small>Charge</small>
								<div class="fw-semibold"><?=number_format((float)($row->charge ?? 0), 2)?></div>
							</div>
						</div>

						<div class="row g-3 mt-2">
							<div class="col-md-6">
								<label class="form-label">Transaction Status</label>
								<select class="form-select" name="transaction_status" id="transaction_status">
									<option value="Approved" <?=old_select('transaction_status', 'Approved', $currentStatus)?>>Approved</option>
									<option value="Declined" <?=old_select('transaction_status', 'Declined', $currentStatus)?>>Declined</option>
								</select>
								<?php if (!empty($editErrors['transaction_status'])): ?>
									<small class="text-danger"><?=$editErrors['transaction_status']?></small>
								<?php endif; ?>
							</div>
							<div class="col-12">
								<label class="form-label">Rejection Reason</label>
								<textarea class="form-control" rows="4" name="rejection_reason" id="rejection_reason" placeholder="State clearly why this approved transaction is being declined"><?=esc(old_value('rejection_reason', $row->rejection_reason ?? ''))?></textarea>
								<?php if (!empty($editErrors['rejection_reason'])): ?>
									<small class="text-danger"><?=$editErrors['rejection_reason']?></small>
								<?php endif; ?>
							</div>
						</div>
					<?php else: ?>
						<div class="row g-3 mt-2">
							<div class="col-md-6">
								<label class="form-label">Category</label>
								<select class="form-select" name="category" id="category">
									<option value="">---Select---</option>
									<?php foreach ($categories as $category): ?>
										<option <?=old_select('category', $category, $row->category ?? '')?> value="<?=$category?>"><?=$category?></option>
									<?php endforeach; ?>
								</select>
								<?php if (!empty($editErrors['category'])): ?>
									<small class="text-danger"><?=$editErrors['category']?></small>
								<?php endif; ?>
							</div>
							<div class="col-md-6">
								<label class="form-label">Institution</label>
								<input type="text" class="form-control" name="institution" id="institution" value="<?=esc(old_value('institution', $row->institution ?? ''))?>" placeholder="Enter institution">
								<?php if (!empty($editErrors['institution'])): ?>
									<small class="text-danger"><?=$editErrors['institution']?></small>
								<?php endif; ?>
							</div>
							<div class="col-md-6">
								<label class="form-label">Customer's Phone</label>
								<input type="text" class="form-control" name="customer_phone" id="customer_phone" value="<?=esc(old_value('customer_phone', $row->customer_phone ?? ''))?>" placeholder="Enter customer's phone number">
								<?php if (!empty($editErrors['customer_phone'])): ?>
									<small class="text-danger"><?=$editErrors['customer_phone']?></small>
								<?php endif; ?>
							</div>
							<div class="col-md-6">
								<label class="form-label">Customer's ID</label>
								<input type="text" class="form-control" name="customer_id" id="customer_id" value="<?=esc(old_value('customer_id', $row->customer_id ?? ''))?>" placeholder="Enter customer's ID">
								<?php if (!empty($editErrors['customer_id'])): ?>
									<small class="text-danger"><?=$editErrors['customer_id']?></small>
								<?php endif; ?>
							</div>
							<div class="col-12">
								<label class="form-label">Description</label>
								<textarea class="form-control" rows="3" name="description" id="description" placeholder="Write a brief description for this transaction"><?=esc(old_value('description', $row->description ?? ''))?></textarea>
								<?php if (!empty($editErrors['description'])): ?>
									<small class="text-danger"><?=$editErrors['description']?></small>
								<?php endif; ?>
							</div>
							<div class="col-md-4">
								<label class="form-label">Payment Medium</label>
								<select class="form-select" name="payment_medium" id="payment_medium">
									<option value="">---Select---</option>
									<?php foreach ($paymentMediums as $medium): ?>
										<option <?=old_select('payment_medium', $medium, $row->payment_medium ?? '')?> value="<?=$medium?>"><?=$medium?></option>
									<?php endforeach; ?>
								</select>
								<?php if (!empty($editErrors['payment_medium'])): ?>
									<small class="text-danger"><?=$editErrors['payment_medium']?></small>
								<?php endif; ?>
							</div>
							<div class="col-md-4" id="networkWrap">
								<label class="form-label">E-cash Network</label>
								<select class="form-select" name="network" id="network">
									<option value="">---Select---</option>
									<?php foreach ($networkOptions as $network): ?>
										<option <?=old_select('network', $network, $row->network ?? '')?> value="<?=$network?>"><?=$network?></option>
									<?php endforeach; ?>
								</select>
								<?php if (!empty($editErrors['network'])): ?>
									<small class="text-danger"><?=$editErrors['network']?></small>
								<?php endif; ?>
							</div>
							<div class="col-md-4 d-none" id="bankWrap">
								<label class="form-label">Selected Bank</label>
								<select class="form-select" name="bank" id="bank">
									<option value="">---Select---</option>
									<?php foreach ($bankOptions as $bank): ?>
										<option <?=old_select('bank', $bank, $row->bank ?? '')?> value="<?=$bank?>"><?=$bank?></option>
									<?php endforeach; ?>
								</select>
								<?php if (!empty($editErrors['bank'])): ?>
									<small class="text-danger"><?=$editErrors['bank']?></small>
								<?php endif; ?>
							</div>
							<div class="col-md-6 d-none" id="assignedWrap">
								<label class="form-label">Assigned Manager / User</label>
								<select class="form-select" name="assigned_to" id="assigned_to">
									<option value="">---Select---</option>
									<?php foreach ($eligibleAssignees as $assignee): ?>
										<option <?=old_select('assigned_to', $assignee->id ?? '', $row->assigned_to ?? '')?> value="<?=$assignee->id ?? ''?>">
											<?=esc(trim(($assignee->first_name ?? '') . ' ' . ($assignee->last_name ?? '')))?>
										</option>
									<?php endforeach; ?>
								</select>
								<?php if (!empty($editErrors['assigned_to'])): ?>
									<small class="text-danger"><?=$editErrors['assigned_to']?></small>
								<?php endif; ?>
							</div>
							<div class="col-md-4">
								<label class="form-label">Amount Payable</label>
								<input type="number" step="0.01" class="form-control" name="amount_paid" id="amount_paid" value="<?=esc(old_value('amount_paid', $row->amount_paid ?? ''))?>" placeholder="Enter amount payable">
								<?php if (!empty($editErrors['amount_paid'])): ?>
									<small class="text-danger"><?=$editErrors['amount_paid']?></small>
								<?php endif; ?>
							</div>
							<div class="col-md-4">
								<label class="form-label">Transaction Amount</label>
								<input type="number" step="0.01" class="form-control" name="amount" id="amount" value="<?=esc(old_value('amount', $row->amount ?? ''))?>" placeholder="Enter transaction amount">
								<?php if (!empty($editErrors['amount'])): ?>
									<small class="text-danger"><?=$editErrors['amount']?></small>
								<?php endif; ?>
							</div>
							<div class="col-md-4">
								<label class="form-label">Charge</label>
								<input type="number" step="0.01" class="form-control" name="charge" id="charge" value="<?=esc(old_value('charge', $row->charge ?? ''))?>" placeholder="Enter charge">
								<?php if (!empty($editErrors['charge'])): ?>
									<small class="text-danger"><?=$editErrors['charge']?></small>
								<?php endif; ?>
							</div>
							<div class="col-md-6">
								<label class="form-label">Transaction Status</label>
								<select class="form-select" name="transaction_status" id="transaction_status">
									<option value="">---Select---</option>
									<option <?=old_select('transaction_status', 'Draft', $currentStatus)?> value="Draft">Draft</option>
									<option <?=old_select('transaction_status', 'Pending', $currentStatus)?> value="Pending">Pending</option>
									<option <?=old_select('transaction_status', 'Rejected', $currentStatus)?> value="Rejected">Rejected</option>
									<option <?=old_select('transaction_status', 'Approved', $currentStatus)?> value="Approved">Approved</option>
									<option <?=old_select('transaction_status', 'Declined', $currentStatus)?> value="Declined">Declined</option>
								</select>
								<?php if (!empty($editErrors['transaction_status'])): ?>
									<small class="text-danger"><?=$editErrors['transaction_status']?></small>
								<?php endif; ?>
							</div>
							<div class="col-12 d-none" id="rejectionReasonWrap">
								<label class="form-label">Rejection Reason</label>
								<textarea class="form-control" rows="4" name="rejection_reason" id="rejection_reason" placeholder="State clearly why this transaction is being declined"><?=esc(old_value('rejection_reason', $row->rejection_reason ?? ''))?></textarea>
								<?php if (!empty($editErrors['rejection_reason'])): ?>
									<small class="text-danger"><?=$editErrors['rejection_reason']?></small>
								<?php endif; ?>
							</div>
							<div class="col-12">
								<label class="form-label">Transaction Image</label>
								<?php if (!empty($row->transaction_image)): ?>
									<div class="ps-preview mb-2">
										<img src="<?=get_image($row->transaction_image)?>" alt="Transaction image">
									</div>
								<?php endif; ?>
								<input type="file" class="form-control" name="transaction_image" id="transaction_image" accept="image/*">
								<?php if (!empty($editErrors['transaction_image'])): ?>
									<small class="text-danger"><?=$editErrors['transaction_image']?></small>
								<?php endif; ?>
							</div>
						</div>
					<?php endif; ?>

					<div class="d-flex justify-content-between align-items-center mt-4">
						<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>" class="btn btn-outline-primary">
							<i class="fa-solid fa-chevron-left"></i> Back
						</a>
						<button class="btn btn-danger" type="submit" id="saveBtn">
							<i class="fa-solid fa-save"></i> Save
						</button>
					</div>
				</form>
			<?php endif; ?>
		</div>
	<?php else: ?>
		<div class="alert alert-danger text-center">That record was not found!</div>
		<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>" class="btn btn-primary">Back</a>
	<?php endif; ?>
<?php else: ?>
	<div class="alert alert-danger text-center">
		Access denied. You dont have permission for this action
	</div>
<?php endif; ?>

<script>
(() => {
	const paymentMedium = document.getElementById('payment_medium');
	const networkWrap = document.getElementById('networkWrap');
	const bankWrap = document.getElementById('bankWrap');
	const assignedWrap = document.getElementById('assignedWrap');
	const statusSelect = document.getElementById('transaction_status');
	const rejectionReasonWrap = document.getElementById('rejectionReasonWrap');
	const rejectionReasonField = document.getElementById('rejection_reason');
	const transactionImageInput = document.getElementById('transaction_image');
	const transactionImagePreview = document.querySelector('.ps-preview img');
	const saveBtn = document.getElementById('saveBtn');
	const approvedLocked = <?= !empty($row) && $isApprovedLocked ? 'true' : 'false' ?>;

	function toggleMediumFields() {
		if (!paymentMedium) return;
		const isBank = String(paymentMedium.value || '').toLowerCase() === 'bank';
		if (networkWrap) networkWrap.classList.toggle('d-none', isBank);
		if (bankWrap) bankWrap.classList.toggle('d-none', !isBank);
		if (assignedWrap) assignedWrap.classList.toggle('d-none', !isBank);
	}

	function toggleRejectionReason() {
		if (!statusSelect) return;
		const isDeclined = String(statusSelect.value || '').toLowerCase() === 'declined';
		if (rejectionReasonWrap) {
			rejectionReasonWrap.classList.toggle('d-none', !isDeclined);
		}
		if (!isDeclined && rejectionReasonField && !approvedLocked) {
			rejectionReasonField.value = '';
		}
		updateSaveState();
	}

	function updateSaveState() {
		if (!saveBtn || !approvedLocked || !statusSelect) return;
		const wantsDecline = String(statusSelect.value || '').toLowerCase() === 'declined';
		const hasReason = String(rejectionReasonField?.value || '').trim() !== '';
		saveBtn.disabled = !(wantsDecline && hasReason);
	}

	document.addEventListener('DOMContentLoaded', () => {
		toggleMediumFields();
		toggleRejectionReason();
		updateSaveState();
		if (transactionImageInput && window.AppImageCropper && typeof window.AppImageCropper.handleLegacyChange === 'function') {
			transactionImageInput.addEventListener('change', function(e){
				window.AppImageCropper.handleLegacyChange(e, {
					previewEl: transactionImagePreview,
					maxBytes: window.AppImageCropper.MAX_BYTES,
					modalTitle: 'Crop transaction image',
					errorTitle: 'Invalid transaction image',
				});
			});
		}
	});

	if (paymentMedium) {
		paymentMedium.addEventListener('change', toggleMediumFields);
	}
	if (statusSelect) {
		statusSelect.addEventListener('change', toggleRejectionReason);
	}
	if (rejectionReasonField) {
		rejectionReasonField.addEventListener('input', updateSaveState);
	}
})();
</script>
