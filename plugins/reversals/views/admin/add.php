<?php if(user_can('add_reversal')): ?>
	<style>
		.reversal-card {
			max-width: 980px;
			margin: 30px auto;
			border-radius: 18px;
			box-shadow: 0 18px 45px rgba(34, 34, 34, 0.08);
			overflow: hidden;
		}
		.reversal-card .header {
			padding: 24px 28px 18px;
		}
		.reversal-card .body {
			padding: 26px 28px 30px;
		}
		.reversal-grid {
			display: grid;
			grid-template-columns: repeat(2, minmax(0, 1fr));
			gap: 18px;
		}
		.reversal-grid .full {
			grid-column: 1 / -1;
		}
		.reversal-grid .lookup-panel {
			grid-column: 1 / -1;
			padding: 18px;
			border-radius: 14px;
		}
		/*.reversal-readonly {
			background: #f8fafc !important;
		}*/
		.lookup-status {
			font-size: 13px;
		}
		.lookup-status.success {
			color: #198754;
		}
		.lookup-status.error {
			color: #dc3545;
		}
		.lookup-status.info {
			color: #6c757d;
		}
		@media (max-width: 768px) {
			.reversal-grid {
				grid-template-columns: 1fr;
			}
		}
	</style>

	<form method="post" enctype="multipart/form-data" autocomplete="off">
		<div class="reversal-card">
			<div class="header">
				<div class="d-flex align-items-center gap-3">
					<img src="<?=APP_LOGO2 ?? ''?>" style="width:60px;height:60px;object-fit:cover;">
					<div>
						<h3 class="mb-1">Create Reversal</h3>
						<div class="text-muted">Search the original transaction first, then complete the reversal request for manager acknowledgement.</div>
					</div>
				</div>
			</div>

			<div class="body">
				<?=csrf()?>

				<div class="reversal-grid">
					<div>
						<label class="form-label">Transaction Type</label>
						<select class="form-select" name="source_transaction_type" id="source_transaction_type">
							<option value="">Select transaction type</option>
							<?php foreach(($transactionTypeOptions ?? []) as $optionValue => $optionLabel): ?>
								<option <?=old_select('source_transaction_type', $optionValue)?> value="<?=$optionValue?>"><?=$optionLabel?></option>
							<?php endforeach; ?>
						</select>
						<?php if(!empty($errors['source_transaction_type'])): ?>
							<small class="text-danger"><?=$errors['source_transaction_type']?></small>
						<?php endif; ?>
					</div>

					<div>
						<label class="form-label">Transaction ID</label>
						<input type="text" class="form-control" name="source_transaction_id" id="source_transaction_id" value="<?=old_value('source_transaction_id')?>" placeholder="Enter transaction ID">
						<?php if(!empty($errors['source_transaction_id'])): ?>
							<small class="text-danger"><?=$errors['source_transaction_id']?></small>
						<?php endif; ?>
					</div>

					<div class="lookup-panel">
						<div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
							<div>
								<div class="fw-semibold">Automatic Lookup</div>
								<div id="reversalLookupStatus" class="lookup-status info">Waiting for transaction type and transaction ID.</div>
							</div>
						</div>
					</div>

					<div>
						<label class="form-label">Service Provider / Network</label>
						<input type="text" class="form-control reversal-readonly" id="lookup_network" readonly>
						<input type="hidden" name="network" id="lookup_network_hidden" value="<?=htmlspecialchars((string)old_value('network'), ENT_QUOTES)?>">
					</div>

					<div>
						<label class="form-label">Service Type</label>
						<input type="text" class="form-control reversal-readonly" id="lookup_service_type" readonly>
						<input type="hidden" name="service_type" id="lookup_service_type_hidden" value="<?=htmlspecialchars((string)old_value('service_type'), ENT_QUOTES)?>">
					</div>

					<div>
						<label class="form-label">Receiver Phone / Account Number</label>
						<input type="text" class="form-control reversal-readonly" id="lookup_receiver_details" readonly>
						<input type="hidden" name="receiver_details" id="lookup_receiver_details_hidden" value="<?=htmlspecialchars((string)old_value('receiver_details'), ENT_QUOTES)?>">
					</div>

					<div>
						<label class="form-label">Amount Involved</label>
						<input type="text" class="form-control reversal-readonly" id="lookup_amount" readonly>
						<input type="hidden" name="amount" id="lookup_amount_hidden" value="<?=htmlspecialchars((string)old_value('amount'), ENT_QUOTES)?>">
					</div>

					<div class="full">
						<label class="form-label">Source Transaction Date</label>
						<input type="text" class="form-control reversal-readonly" id="lookup_transaction_date" readonly>
						<input type="hidden" name="transaction_date" id="lookup_transaction_date_hidden" value="<?=htmlspecialchars((string)old_value('transaction_date'), ENT_QUOTES)?>">
					</div>

					<div>
						<label class="form-label">Reversal Type</label>
						<select class="form-select" name="reversal_type">
							<option value="">Select reversal type</option>
							<?php foreach(($reversalTypeOptions ?? []) as $optionValue => $optionLabel): ?>
								<option <?=old_select('reversal_type', $optionValue)?> value="<?=$optionValue?>"><?=$optionLabel?></option>
							<?php endforeach; ?>
						</select>
						<?php if(!empty($errors['reversal_type'])): ?>
							<small class="text-danger"><?=$errors['reversal_type']?></small>
						<?php endif; ?>
					</div>

					<div>
						<label class="form-label">Sender's Details</label>
						<input type="text" class="form-control" name="sender_details" value="<?=old_value('sender_details')?>" placeholder="Phone number of sender">
						<?php if(!empty($errors['sender_details'])): ?>
							<small class="text-danger"><?=$errors['sender_details']?></small>
						<?php endif; ?>
					</div>

					<div class="full">
						<label class="form-label">Assign To</label>
						<select class="form-select" name="assigned" id="assigned">
							<option value="">Select general manager or admin</option>
							<?php foreach(($reversalAssignableUsers ?? []) as $reviewer): ?>
								<?php $reviewerLabel = trim((string)(($reviewer->first_name ?? '') . ' ' . ($reviewer->last_name ?? ''))); ?>
								<option <?=old_select('assigned', (string)($reviewer->id ?? 0))?> value="<?=esc((string)($reviewer->id ?? 0))?>">
									<?=esc($reviewerLabel !== '' ? $reviewerLabel : ('User #' . (int)($reviewer->id ?? 0)))?>
								</option>
							<?php endforeach; ?>
						</select>
						<?php if(!empty($errors['assigned'])): ?>
							<small class="text-danger d-block"><?=$errors['assigned']?></small>
						<?php endif; ?>
						<?php if(empty($reversalAssignableUsers)): ?>
							<small class="text-danger d-block">No eligible general manager or admin with a cashdrawer created for today was found.</small>
						<?php else: ?>
							<small class="text-muted">Only general managers or admins with a cashdrawer created for today are listed. The selected assignee will acknowledge every review phase of this reversal.</small>
						<?php endif; ?>
					</div>
				</div>

				<div class="d-flex justify-content-between mt-4">
					<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>" class="btn btn-outline-primary">
						<i class="fa-solid fa-chevron-left"></i> Back
					</a>
					<button class="btn btn-danger" type="submit" id="reversalSubmitButton">
						<i class="fa-solid fa-paper-plane"></i> Submit For Manager Review
					</button>
				</div>
			</div>
		</div>
	</form>

	<script>
		(function(){
			const typeInput = document.getElementById('source_transaction_type');
			const idInput = document.getElementById('source_transaction_id');
			const statusBox = document.getElementById('reversalLookupStatus');
			const submitButton = document.getElementById('reversalSubmitButton');
			const assignInput = document.getElementById('assigned');
			const fields = {
				network: document.getElementById('lookup_network'),
				serviceType: document.getElementById('lookup_service_type'),
				receiver: document.getElementById('lookup_receiver_details'),
				amount: document.getElementById('lookup_amount'),
				transactionDate: document.getElementById('lookup_transaction_date'),
				networkHidden: document.getElementById('lookup_network_hidden'),
				serviceTypeHidden: document.getElementById('lookup_service_type_hidden'),
				receiverHidden: document.getElementById('lookup_receiver_details_hidden'),
				amountHidden: document.getElementById('lookup_amount_hidden'),
				transactionDateHidden: document.getElementById('lookup_transaction_date_hidden'),
			};

			let debounceTimer = null;
			let lastLookupKey = '';
			let lookupValid = false;

			function setStatus(message, type){
				if (!statusBox) return;
				statusBox.textContent = message;
				statusBox.className = 'lookup-status ' + (type || 'info');
			}

			function clearLookup(){
				fields.network.value = '';
				fields.serviceType.value = '';
				fields.receiver.value = '';
				fields.amount.value = '';
				fields.transactionDate.value = '';
				fields.networkHidden.value = '';
				fields.serviceTypeHidden.value = '';
				fields.receiverHidden.value = '';
				fields.amountHidden.value = '';
				fields.transactionDateHidden.value = '';
				lookupValid = false;
			}

			function syncSubmitState(){
				if (submitButton) {
					submitButton.disabled = !lookupValid || !(assignInput && String(assignInput.value || '').trim() !== '');
				}
			}

			async function runLookup(){
				const transactionType = String(typeInput ? typeInput.value || '' : '').trim();
				const transactionId = String(idInput ? idInput.value || '' : '').trim();
				if (!transactionType || !transactionId) {
					clearLookup();
					setStatus('Waiting for transaction type and transaction ID.', 'info');
					syncSubmitState();
					return;
				}

				const lookupKey = transactionType + '::' + transactionId;
				if (lookupKey === lastLookupKey) {
					return;
				}

				setStatus('Searching transaction...', 'info');
				try {
					const url = new URL('<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/lookup_transaction', window.location.origin);
					url.searchParams.set('transaction_type', transactionType);
					url.searchParams.set('transaction_id', transactionId);
					const response = await fetch(url.toString(), {
						headers: { 'X-Requested-With': 'XMLHttpRequest' },
						credentials: 'same-origin'
					});
					const data = await response.json();
					if (!data || !data.success || !data.data) {
						lastLookupKey = '';
						clearLookup();
						setStatus((data && data.message) ? data.message : 'Transaction lookup failed.', 'error');
						syncSubmitState();
						return;
					}

					const found = data.data;
					fields.network.value = found.service_provider || '';
					fields.serviceType.value = found.service_type || '';
					fields.receiver.value = found.receiver_details || '';
					fields.amount.value = found.amount !== undefined ? Number(found.amount).toFixed(2) : '';
					fields.transactionDate.value = found.transaction_date || '';
					fields.networkHidden.value = found.service_provider || '';
					fields.serviceTypeHidden.value = found.service_type || '';
					fields.receiverHidden.value = found.receiver_details || '';
					fields.amountHidden.value = found.amount !== undefined ? Number(found.amount).toFixed(2) : '';
					fields.transactionDateHidden.value = found.transaction_date || '';
					lastLookupKey = lookupKey;
					lookupValid = true;
					setStatus('Transaction found and details loaded successfully.', 'success');
					syncSubmitState();
				} catch (error) {
					lastLookupKey = '';
					clearLookup();
					setStatus('Transaction lookup failed. Please try again.', 'error');
					syncSubmitState();
				}
			}

			function queueLookup(){
				clearTimeout(debounceTimer);
				debounceTimer = setTimeout(runLookup, 350);
			}

			if (typeInput) typeInput.addEventListener('change', queueLookup);
			if (idInput) idInput.addEventListener('input', queueLookup);
			if (assignInput) assignInput.addEventListener('change', syncSubmitState);

			queueLookup();
			syncSubmitState();
		})();
	</script>

<?php else: ?>
	<div class="alert alert-danger text-center">
		Access denied. You don't have permission for this action.
	</div>
<?php endif ?>
