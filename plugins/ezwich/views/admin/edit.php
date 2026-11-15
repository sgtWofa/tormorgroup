<?php if(user_can('edit_ezwich')):?>
	<style>
		form {
			margin-top: 40px !important;
		}
		.form-control, .form-select {
			border-top: none !important;
			border-left: 0.5px solid gray !important;
			border-right: none !important;
			border: 1px solid yellowgreen;
			border-radius: 5px !important;
		}
		.input-group-text{
			font-weight: bolder;
		}
		.form-label {
			color: gray;
			font-size: 12px;
		}
		.ez-summary-card{
			border:1px solid rgba(128,128,128,.25);
			border-radius:12px;
			padding:14px 16px;
			background:rgba(128,128,128,.04);
		}
		.ez-summary-label{
			color:gray;
			font-size:12px;
			margin-bottom:6px;
			display:block;
		}
		.ez-summary-value{
			font-size:20px;
			font-weight:600;
			line-height:1.25;
			word-break:break-word;
		}
		.ez-approved-actions{
			margin-top:8px;
		}
	</style>

	<?php if(!empty($row)):?>

		<?php
			$denoRows = $denoRows ?? [];
			$hasDenos = !empty($denoRows);
			$denoPayload = [];
			$currentStatus = ezwich_normalize_status($row->transaction_status ?? '', 'Draft');
			$isApprovedRecord = ezwich_status_is_approved($currentStatus);
			$isDeclinedRecord = ezwich_status_is_declined($currentStatus);
			$currentRejectionReason = old_value('rejection_reason', $row->rejection_reason ?? '');
			$statusNeedingReason = strtoupper((string)old_value('transaction_status', $currentStatus));
			$showRejectionReason = in_array($statusNeedingReason, ['REJECTED', 'DECLINED'], true);
			if($hasDenos){
				foreach($denoRows as $denRow){
					$denomination = (float)($denRow->denomination ?? 0);
					$count = (int)($denRow->count ?? 0);
					$volume = (float)($denRow->volume ?? ($denomination * $count));
					$currency = $denRow->currency ?? 'GHS';
					$denoPayload[] = [
						'denomination' => $denomination,
						'count' => $count,
						'currency' => $currency,
						'volume' => $volume,
					];
				}
			}
		?>

		<?php if($isDeclinedRecord):?>
			<div class="row g-3 col-md-10 col-lg-8 mx-auto shadow p-4 rounded">
				<div class="alert alert-warning mb-0">
					Declined ezwich transactions are locked and can no longer be edited.
				</div>
				<div class="d-flex justify-content-between" style="margin-top:10px;">
					<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
						<button type="button" class="btn btn-primary">
							<i class="fa-solid fa-chevron-left"></i> Back
						</button>
					</a>
				</div>
			</div>
		<?php elseif($isApprovedRecord):?>
		<form method="post" enctype="multipart/form-data">
			<div class="row g-3 col-md-10 col-lg-8 mx-auto shadow p-4 rounded">
				<?=csrf()?>

				<h4 class="text-center">
					<span><img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:60px;max-height: 60px;object-fit: cover;"></span>
					Edit Transaction
				</h4>

				<div class="col-12">
					<div class="alert alert-warning mb-0">
						This transaction is already approved. To keep accounting consistent, only the status can be changed to Declined.
					</div>
				</div>
				<?php if(!empty($errors)): ?>
				<div class="col-12">
					<div class="alert alert-danger mb-0">
						<?php foreach($errors as $error): ?>
							<?php if(!empty($error)): ?>
								<div><?=esc($error)?></div>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				</div>
				<?php endif; ?>

				<div class="mb-3 col-12">
					<label for="transaction_status" class="form-label">Status</label>
					<select class="form-select" id="transaction_status" name="transaction_status">
						<option <?=old_select('transaction_status','Approved', $currentStatus)?> value="Approved">Approved</option>
						<option <?=old_select('transaction_status','Declined', $currentStatus)?> value="Declined">Declined</option>
					</select>
					<?php if(!empty($errors['transaction_status'])): ?>
						<small class="text-danger"><?=$errors['transaction_status']?></small>
					<?php endif ?>
				</div>

				<div class="mb-3 col-12 <?= $showRejectionReason ? '' : 'd-none' ?>" id="ezwichRejectionReasonWrap">
					<label for="rejection_reason" class="form-label">Rejection reason</label>
					<textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="3" placeholder="State clearly why this transaction is being declined or rejected."><?=esc($currentRejectionReason)?></textarea>
					<?php if(!empty($errors['rejection_reason'])): ?>
						<small class="text-danger"><?=$errors['rejection_reason']?></small>
					<?php endif ?>
				</div>

				<div class="col-md-6">
					<div class="ez-summary-card h-100">
						<span class="ez-summary-label">Transaction type</span>
						<div class="ez-summary-value"><?=esc($row->transaction_type ?? 'N/A')?></div>
					</div>
				</div>
				<div class="col-md-6">
					<div class="ez-summary-card h-100">
						<span class="ez-summary-label">Bank</span>
						<div class="ez-summary-value"><?=esc($row->network ?? 'N/A')?></div>
					</div>
				</div>
				<div class="col-md-6">
					<div class="ez-summary-card h-100">
						<span class="ez-summary-label">Client USN</span>
						<div class="ez-summary-value"><?=esc($row->usn ?? 'N/A')?></div>
					</div>
				</div>
				<div class="col-md-6">
					<div class="ez-summary-card h-100">
						<span class="ez-summary-label">Client's Phone</span>
						<div class="ez-summary-value"><?=esc($row->phone ?? 'N/A')?></div>
					</div>
				</div>
				<?php if(!empty($row->destination_bank)):?>
				<div class="col-md-6">
					<div class="ez-summary-card h-100">
						<span class="ez-summary-label">Destination</span>
						<div class="ez-summary-value"><?=esc($row->destination_bank)?></div>
					</div>
				</div>
				<?php endif;?>
				<?php if(!empty($row->receiving) || !empty($row->receiving_sim)):?>
				<div class="col-md-6">
					<div class="ez-summary-card h-100">
						<span class="ez-summary-label">Receiving sim</span>
						<div class="ez-summary-value"><?=esc(trim(($row->receiving ?? '').' '.($row->receiving_sim ?? '')) ?: 'N/A')?></div>
					</div>
				</div>
				<?php endif;?>
				<div class="col-md-6">
					<div class="ez-summary-card h-100">
						<span class="ez-summary-label">Amount Paid Out</span>
						<div class="ez-summary-value"><?=number_format((float)($row->amount ?? 0), 2)?></div>
					</div>
				</div>
				<div class="col-md-6">
					<div class="ez-summary-card h-100">
						<span class="ez-summary-label">Charge</span>
						<div class="ez-summary-value"><?=number_format((float)($row->charge ?? 0), 2)?></div>
					</div>
				</div>

				<div class="col-12 d-flex justify-content-between ez-approved-actions">
					<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
						<button type="button" class="btn btn-primary">
							<i class="fa-solid fa-chevron-left"></i> Back
						</button>
					</a>
					<button class="btn btn-danger disabled" type="submit" id="ezwichApprovedSaveBtn" disabled>
						<i class="fa-solid fa-save"></i> Save
					</button>
				</div>
			</div>
		</form>
		<?php else:?>
		<form method="post" enctype="multipart/form-data">
			<div class="row g-3 col-md-10 col-lg-8 mx-auto shadow p-4 rounded">
				<?=csrf()?>

				<h4 class="text-center">
					<span><img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:60px;max-height: 60px;object-fit: cover;"></span>
					Edit Transaction
				</h4>

				<div class="mb-3 col-md-12 col-lg-6">
					<label for="transaction_type" class="form-label">Transaction type</label>
					<select class="form-select transaction_type" id="transaction_type" name="transaction_type" onchange="showFields()">
					  <option value="">---Select---</option>
					  <option <?=old_select('transaction_type','Withdrawal', $row->transaction_type ?? '')?> value="Withdrawal">Withdrawal</option>
					  <option <?=old_select('transaction_type','Transfer', $row->transaction_type ?? '')?> value="Transfer">Transfer</option>
					</select>
					<?php if(!empty($errors['transaction_type'])): ?>
					  <small class="text-danger"><?=$errors['transaction_type']?></small>
					<?php endif ?>
				</div>

				<div class="mb-3 col-md-12 col-lg-6">
					<label for="network" class="form-label">Bank</label>
					<select class="form-select" name="network" id="network">
					  <option value="">---Select---</option>
					  <option <?=old_select('network','GCB', $row->network ?? '')?> value="GCB">GCB</option>
					  <option <?=old_select('network','Zenit bank', $row->network ?? '')?> value="Zenit bank">Zenit bank</option>
					  <option <?=old_select('network','Fidelity', $row->network ?? '')?> value="Fidelity">Fidelity</option>
					  <option <?=old_select('network','NIB', $row->network ?? '')?> value="NIB">NIB</option>
					  <option <?=old_select('network','Ecobank', $row->network ?? '')?> value="Ecobank">Ecobank</option>
					  <option <?=old_select('network','FBN', $row->network ?? '')?> value="FBN">FBN</option>
					  <option <?=old_select('network','ADB', $row->network ?? '')?> value="ADB">ADB</option>
					</select>
					<?php if(!empty($errors['network'])): ?>
					  <small class="text-danger"><?=$errors['network']?></small>
					<?php endif ?>
				</div>

				<div class="mb-3 col-md-12 col-lg-6 usn">
				  <label for="usn" class="form-label">Client USN</label>
				  <input name="usn" value="<?=old_value('usn', $row->usn ?? '')?>" type="text" class="form-control" id="usn" placeholder="Enter usn used">
					<?php if(!empty($errors['usn'])): ?>
					  <small class="text-danger"><?=$errors['usn']?></small>
					<?php endif ?>
				</div>

				<div class="mb-3 col-md-12 col-lg-6">
				  <label for="phone" class="form-label">Client's Phone</label>
				  <input name="phone" value="<?=old_value('phone', $row->phone)?>" type="text" class="form-control" id="phone" placeholder="Enter phone number">
					<?php if(!empty($errors['phone'])): ?>
					  <small class="text-danger"><?=$errors['phone']?></small>
					<?php endif ?>
				</div>

				<div class="mb-3 col-md-12 col-lg-6 destination_bank d-none">
				  <label for="destination_bank" class="form-label">Destination</label>
				  <input name="destination_bank" value="<?=old_value('destination_bank', $row->destination_bank)?>" type="text" class="form-control" id="destination_bank" placeholder="Enter destination">
					<?php if(!empty($errors['destination_bank'])): ?>
					  <small class="text-danger"><?=$errors['destination_bank']?></small>
					<?php endif ?>
				</div>

				<div class="mb-3 col-md-12 col-lg-6 receiving d-none">
				  <label for="receiving" class="form-label">Receiving sim</label>
				  <select class="form-select" name="receiving" id="receiving">
					  <option value="">---Select---</option>
					  <option <?=old_select('receiving','Company sim', $row->receiving)?> value="Company sim">Company sim</option>
					  <option <?=old_select('receiving','Client sim', $row->receiving)?> value="Client sim">Client sim</option>
				  </select>
				  <?php if(!empty($errors['receiving'])): ?>
					  <small class="text-danger"><?=$errors['receiving']?></small>
				  <?php endif ?>
				</div>

				<div class="mb-3 col-md-12 col-lg-6 receiving_sim d-none">
				  <label for="receiving_sim" class="form-label">Transfer to #</label>
				  <input name="receiving_sim" value="<?=old_value('receiving_sim', $row->receiving_sim)?>" type="text" class="form-control" id="receiving_sim" placeholder="Enter sim number">
					<?php if(!empty($errors['receiving_sim'])): ?>
					  <small class="text-danger"><?=$errors['receiving_sim']?></small>
					<?php endif ?>
				</div>

				<div class="mb-3 col-md-12 col-lg-6 amount">
				  <label for="amount" class="form-label">Amount Paid Out</label>
				  <label class="input-group" data-bs-toggle="modal" data-bs-target="#ezDenoModal">
				  	<span class="input-group-text bg-dark text-warning"><i class="fa-solid fa-calculator"></i></span>
				  	<input name="amount" value="<?=old_value('amount', $row->amount)?>" type="number" step="0.1" class="form-control" id="amount" placeholder="Launch calculator" readonly>
				  	<input type="hidden" id="denos" name="denos" value="<?= $hasDenos ? htmlspecialchars(json_encode($denoPayload), ENT_QUOTES, 'UTF-8') : '' ?>">
				  </label>
					<?php if(!empty($errors['amount'])): ?>
					  <small class="text-danger"><?=$errors['amount']?></small>
					<?php endif ?>
					<?php if(!empty($errors['denos'])): ?>
					  <small class="text-danger"><?=$errors['denos']?></small>
					<?php endif ?>
				</div>

				<div class="mb-3 col-md-12 col-lg-6">
				  <label for="charge" class="form-label">Charge </label>
				  <div class="input-group">
				  	<div id="currencySymbol" class="input-group-text">GHS</div>
				  	<input name="charge" value="<?=old_value('charge', $row->charge)?>" type="text" class="form-control" id="charge" placeholder="GHS 0.00">
				  </div>
					<?php if(!empty($errors['charge'])): ?>
					  <small class="text-danger"><?=$errors['charge']?></small>
					<?php endif ?>
				</div>

				<div class="mb-3 col-md-12 col-lg-6 transaction_status">
				  <label for="transaction_status" class="form-label">Status</label>
				  <select class="form-select" id="transaction_status" name="transaction_status">
					  <option value="">---Select---</option>
					  <option <?=old_select('transaction_status','Draft', $currentStatus)?> value="Draft">Draft</option>
					  <option <?=old_select('transaction_status','Pending', $currentStatus)?> value="Pending">Pending</option>
					  <option <?=old_select('transaction_status','Rejected', $currentStatus)?> value="Rejected">Rejected</option>
					  <option <?=old_select('transaction_status','Approved', $currentStatus)?> value="Approved">Approved</option>
					  <option <?=old_select('transaction_status','Declined', $currentStatus)?> value="Declined">Declined</option>
				  </select>
					<?php if(!empty($errors['transaction_status'])): ?>
					  <small class="text-danger"><?=$errors['transaction_status']?></small>
					<?php endif ?>
				</div>

				<div class="mb-3 col-md-12 rejection_reason <?= $showRejectionReason ? '' : 'd-none' ?>" id="ezwichRejectionReasonWrap">
					<label for="rejection_reason" class="form-label">Rejection reason</label>
					<textarea rows="3" class="form-control" id="rejection_reason" name="rejection_reason" placeholder="State clearly why this transaction is being declined or rejected."><?=esc($currentRejectionReason)?></textarea>
					<?php if(!empty($errors['rejection_reason'])): ?>
						<small class="text-danger"><?=$errors['rejection_reason']?></small>
					<?php endif ?>
				</div>

				<div class="d-flex justify-content-between" style="margin-top:10px;">
					<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
						<button type="button" class="btn btn-primary">
							<i class="fa-solid fa-chevron-left"></i> Back
						</button>
					</a>
					<button class="btn btn-danger" type="submit">
						<i class="fa-solid fa-save"></i> Save
					</button>
				</div>
			</div>
		</form>
		<?php endif;?>

		<?php if(!$isApprovedRecord):?>
		<div class="modal fade" id="ezDenoModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="ezDenoModalLabel" aria-hidden="true">
		  <div class="modal-dialog">
		    <div class="modal-content">
		      <div class="modal-header">
		        <h5 class="modal-title" id="ezDenoModalLabel">Currency Notes</h5>
		        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
		      </div>
		      <div class="modal-body table-responsive">
		        <table class="table table-hover table-sm">
		          <thead>
		            <tr>
		              <th>NOTES</th>
		              <th>COUNT</th>
		              <th>VOLUME</th>
		            </tr>
		          </thead>
		          <tbody id="ezDenoTableBody"></tbody>
		          <tfoot>
		            <tr>
		              <th class="px-5 bg-secondary text-warning">TOTAL</th>
		              <th class="bg-secondary text-warning"><input type="text" class="form-control p-1" id="ez-total-count" style="border:none;" placeholder="Total count" readonly></th>
		              <th class="bg-secondary text-warning"><input type="text" class="form-control p-1" id="ez-total-volume" style="border:none;" placeholder="Total volume" readonly></th>
		            </tr>
		          </tfoot>
		        </table>
		      </div>
		      <div class="modal-footer">
		        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
		        <button type="button" class="btn btn-danger ez-reset">Reset</button>
		        <button type="button" class="btn btn-primary ez-submit">Confirm</button>
		      </div>
		    </div>
		  </div>
		</div>
		<?php endif;?>

	<?php else:?>
		<div class="alert alert-danger text-center">
			That record was not found!
		</div>

		<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
			<button class="btn btn-primary">Back</button>
		</a>
	<?php endif?>

<?php else:?>
	<div class="alert alert-danger text-center">
		Access denied. You dont have permission for this action
	</div>
<?php endif?>

<script>
	const isApprovedEdit = <?= !empty($isApprovedRecord) ? 'true' : 'false' ?>;
	const transactionType = document.getElementById('transaction_type');
	const receivingSelect = document.getElementById('receiving');
	const receivingSimInput = document.getElementById('receiving_sim');
	const transactionStatusSelect = document.getElementById('transaction_status');
	const approvedSaveButton = document.getElementById('ezwichApprovedSaveBtn');
	const rejectionReasonWrap = document.getElementById('ezwichRejectionReasonWrap');
	const rejectionReasonInput = document.getElementById('rejection_reason');

	function showFields() {
		if (isApprovedEdit || !transactionType) {
			return;
		}

		const transactionTypeValue = transactionType.value;
		const destinationBank = document.querySelector('.destination_bank');
		const receiving = document.querySelector('.receiving');
		const receivingSim = document.querySelector('.receiving_sim');
		if (!destinationBank || !receiving || !receivingSim) {
			return;
		}

		destinationBank.classList.add('d-none');
		receiving.classList.add('d-none');
		receivingSim.classList.add('d-none');

		if (transactionTypeValue === 'Transfer') {
			destinationBank.classList.remove('d-none');
			receiving.classList.remove('d-none');
			receivingSim.classList.remove('d-none');
		}
	}

	function calculateCharge() {
		if (isApprovedEdit) {
			return;
		}

		const amountInput = document.getElementById('amount');
		const chargeInput = document.getElementById('charge');
		if (!amountInput || !chargeInput) {
			return;
		}

		const amount = parseFloat(amountInput.value);
		if (!isNaN(amount)) {
			chargeInput.value = amount <= 50 ? '0.50' : (amount * 0.01).toFixed(2);
		} else {
			chargeInput.value = '0.00';
		}
	}

	function updateReceivingSim() {
		if (isApprovedEdit || !receivingSelect || !receivingSimInput) {
			return;
		}

		if (receivingSelect.value === 'Company sim') {
			receivingSimInput.value = '0548214842';
			receivingSimInput.readOnly = true;
		} else {
			receivingSimInput.value = '';
			receivingSimInput.readOnly = false;
		}
	}

	function syncApprovedSaveButton() {
		if (!isApprovedEdit || !transactionStatusSelect || !approvedSaveButton) {
			return;
		}

		const hasReason = !ezwichStatusNeedsReason() || (rejectionReasonInput && String(rejectionReasonInput.value || '').trim() !== '');
		const canSubmit = transactionStatusSelect.value === 'Declined' && hasReason;
		approvedSaveButton.disabled = !canSubmit;
		approvedSaveButton.classList.toggle('disabled', !canSubmit);
	}

	function ezwichStatusNeedsReason() {
		if (!transactionStatusSelect) {
			return false;
		}

		const value = String(transactionStatusSelect.value || '').trim().toLowerCase();
		return value === 'declined' || value === 'rejected';
	}

	function syncEzwichRejectionReason() {
		if (!rejectionReasonWrap || !rejectionReasonInput) {
			return;
		}

		const needsReason = ezwichStatusNeedsReason();
		rejectionReasonWrap.classList.toggle('d-none', !needsReason);
		if (needsReason) {
			rejectionReasonInput.setAttribute('required', 'required');
		} else {
			rejectionReasonInput.removeAttribute('required');
		}
	}

	document.addEventListener("DOMContentLoaded", function() {
		syncEzwichRejectionReason();
		syncApprovedSaveButton();
		showFields();
		updateReceivingSim();
		calculateCharge();

		if (isApprovedEdit) {
			return;
		}

		const existingDenos = <?= json_encode($denoPayload) ?>;
		const existingAmount = <?= json_encode($row->amount ?? 0) ?>;
		const hasExistingDenos = <?= $hasDenos ? 'true' : 'false' ?>;
		const denoTableBody = document.getElementById('ezDenoTableBody');
		if (!denoTableBody) {
			return;
		}

		const denominations = [200, 100, 50, 20, 10, 5, 2, 1, 0.5, 0.2, 0.1];
		const denoMap = {};

		existingDenos.forEach(function(deno){
			const key = Number(deno.denomination);
			denoMap[key] = {
				count: Number(deno.count) || 0,
				volume: Number(deno.volume) || 0
			};
		});

		denoTableBody.innerHTML = '';
		denominations.forEach(function(den){
			const row = document.createElement('tr');
			row.setAttribute('data-denomination', den);
			const label = den >= 1 ? `GH${den}` : `${(den * 100).toFixed(0)}p`;
			const count = denoMap[den] ? denoMap[den].count : 0;
			const volume = denoMap[den] ? denoMap[den].volume : 0;
			row.innerHTML = `
				<td class="px-5">${label}</td>
				<td><input type="number" class="form-control p-1 ez-count-input" value="${count}" placeholder="Enter count"></td>
				<td><input type="text" class="form-control p-1 ez-volume-input" value="${volume.toFixed(2)}" placeholder="Volume" readonly></td>
			`;
			denoTableBody.appendChild(row);
		});

		function recalcDeno() {
			let totalCount = 0;
			let totalVolume = 0;
			const rows = document.querySelectorAll('#ezDenoTableBody tr[data-denomination]');
			rows.forEach(function(row){
				const den = parseFloat(row.getAttribute('data-denomination'));
				const countInput = row.querySelector('.ez-count-input');
				const volInput = row.querySelector('.ez-volume-input');
				const count = parseInt(countInput.value) || 0;
				const volume = den * count;
				volInput.value = volume.toFixed(2);
				totalCount += count;
				totalVolume += volume;
			});
			document.getElementById('ez-total-count').value = totalCount;
			document.getElementById('ez-total-volume').value = totalVolume.toFixed(2);
			const amountField = document.getElementById('amount');
			if(amountField){
				amountField.value = totalVolume.toFixed(2);
			}
		}

		Array.prototype.forEach.call(document.querySelectorAll('.ez-count-input'), function(input){
			input.addEventListener('input', recalcDeno);
		});

		if(hasExistingDenos && existingAmount > 0){
			const amountField = document.getElementById('amount');
			if(amountField){
				amountField.value = parseFloat(existingAmount).toFixed(2);
			}
			const hiddenDenos = document.getElementById('denos');
			if(hiddenDenos){
				hiddenDenos.value = JSON.stringify(existingDenos);
			}
		}

		recalcDeno();

		const hiddenDenosInput = document.getElementById('denos');
		const submitBtn = document.querySelector('#ezDenoModal .ez-submit');
		const resetBtn = document.querySelector('#ezDenoModal .ez-reset');
		if(submitBtn){
			submitBtn.addEventListener('click', function(){
				const data = [];
				const rows = document.querySelectorAll('#ezDenoTableBody tr[data-denomination]');
				rows.forEach(function(row){
					const den = parseFloat(row.getAttribute('data-denomination'));
					const count = parseInt(row.querySelector('.ez-count-input').value) || 0;
					if(count > 0){
						data.push({
							denomination: den,
							count: count,
							currency: 'GHS',
							volume: den * count
						});
					}
				});
				if(hiddenDenosInput){
					hiddenDenosInput.value = JSON.stringify(data);
				}
				const modal = bootstrap.Modal.getInstance(document.getElementById('ezDenoModal'));
				if(modal){
					modal.hide();
				}
			});
		}
		if(resetBtn){
			resetBtn.addEventListener('click', function(){
				Array.prototype.forEach.call(document.querySelectorAll('.ez-count-input'), function(input){
					input.value = '';
				});
				recalcDeno();
			});
		}
	});

	if (transactionStatusSelect) {
		transactionStatusSelect.addEventListener('change', function () {
			syncEzwichRejectionReason();
			syncApprovedSaveButton();
		});
	}
	if (rejectionReasonInput) {
		rejectionReasonInput.addEventListener('input', syncApprovedSaveButton);
	}
	if (transactionType) {
		transactionType.addEventListener("change", showFields);
	}
	if (receivingSelect) {
		receivingSelect.addEventListener("change", updateReceivingSim);
	}
	const amountInput = document.getElementById('amount');
	if (amountInput) {
		amountInput.addEventListener('input', calculateCharge);
	}
</script>
