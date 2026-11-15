<?php

	$existingDenos = [];

	if(!empty($row->id ?? null)){

		$denoRows = $deno->where(['trans_id'=>$row->id]);

		if(!empty($denoRows)){

			foreach($denoRows as $denomination){

				$existingDenos[] = [

					'currency' => $denomination->currency ?? '',

					'denomination' => (float)($denomination->denomination ?? 0),

					'count' => (int)($denomination->count ?? 0),

					'volume' => (float)($denomination->volume ?? 0),

				];

			}

		}

	}

	$defaultType = strtolower($row->transaction_type ?? '') === 'gh₵ to cfa' ? 'GH₵ to CFA' : 'CFA to GH₵';

	$isCfaToGhs = $defaultType === 'CFA to GH₵';

	$defaultAmountIn = $isCfaToGhs ? ($row->amount_cfa ?? 0) : ($row->amount_ghs ?? 0);

	$defaultAmountOut = $isCfaToGhs ? cfa_normalize_payable_ghs_amount($row->amount_ghs ?? 0) : ($row->amount_cfa ?? 0);

	$defaultAmountGhsField = $isCfaToGhs ? cfa_normalize_payable_ghs_amount($row->amount_ghs ?? 0) : ($row->amount_ghs ?? 0);

	$currentStatus = strtolower(trim((string)($row->transaction_status ?? '')));

	$isCompletedTransaction = $currentStatus === 'complete';

?>



<?php if(user_can('edit_cfa')): ?>

	<style>

		form{

			margin-top: 30px !important;

			padding-bottom: 100px;

		}

		@media (max-width: 576px){

			form{ padding-bottom: 140px; }

		}

		.cfa-form-card {
			background: rgba(255, 255, 255, 0.03);
			border: 1px solid rgba(255, 255, 255, 0.08);
			border-radius: 16px;
			backdrop-filter: blur(10px);
		}

		body[data-bs-theme="light"] .cfa-form-card {
			background: #ffffff;
			border-color: rgba(15, 23, 42, 0.08);
			box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
		}

		.form-control, .form-select {
			border-radius: 10px !important;
			padding: 10px 14px;
		}

		.input-group-text{

			font-weight: 600;

			border-radius: 10px;

		}

		.form-label {

			font-size: 13px;

			font-weight: 500;

			margin-bottom: 6px;

		}

	</style>



	<form method="post" enctype="multipart/form-data">		

		<div class="row col-md-8 col-lg-6 mx-auto p-4 rounded-4 cfa-form-card shadow-sm">

			<?=csrf()?>

			<center class="text-center mb-4">

				<h4 class="mb-1 fw-bold">

					<span><img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:50px;max-height: 50px;object-fit: contain;" class="me-2"></span>

					Update CFA Exchange

				</h4>

				<small class="text-primary d-block fw-semibold">Current Buying rate: CFA1,000 = GH&cent;<?=$settings->buying_rate ?? 0?></small>

				<small class="text-success fw-semibold">Current Selling rate: CFA1,000 = GH&cent;<?=$settings->selling_rate ?? 0?></small>

			</center>

			<div class="mb-3">

			  <label for="customer_phone" class="form-label">Customer Phone Number</label>
			  <div class="input-group">
				<span class="input-group-text"><i class="fa-solid fa-phone text-primary"></i></span>
				<input type="tel" name="customer_phone" id="customer_phone" class="form-control" placeholder="e.g. 0241234567" value="<?=old_value('customer_phone', $row->customer_phone ?? '')?>" <?=$isCompletedTransaction ? 'readonly' : ''?>>
			  </div>
				<?php if(!empty($errors['customer_phone'])): ?>
				  <small class="text-danger"><?=$errors['customer_phone']?></small>
				<?php endif ?>
			</div>

			<div class="transaction_type mb-3">

			  <label for="transaction_type" class="form-label">Transaction type</label>

			  <select class="form-select" name="transaction_type" id="transaction_type" <?=$isCompletedTransaction ? 'disabled' : ''?>>

				  <option value="">---Select---</option>

				  <option <?=old_select('transaction_type','CFA to GH₵', $row->transaction_type ?? $defaultType)?> value="CFA to GH₵">CFA to GH₵</option>

				  <option <?=old_select('transaction_type','GH₵ to CFA', $row->transaction_type ?? $defaultType)?> value="GH₵ to CFA">GH₵ to CFA</option>

			  </select>

				<?php if(!empty($errors['transaction_type'])): ?>

				  <small class="text-danger"><?=$errors['transaction_type']?></small>

				<?php endif ?>

			</div>



			<div class="mb-3">

		      <label for="amount_in" class="form-label">Currency Converter</label>

		      <div class="input-group"> 

		      	<span class="input-group-text js-converter-in-label">CFA</span>

		        <input name="amount_in" value="<?=old_value('amount_in', $defaultAmountIn)?>" type="number" step="0.01" class="form-control" id="amount_in" placeholder="" <?=$isCompletedTransaction ? 'readonly' : ''?>>

		      	<span class="input-group-text js-converter-out-label">GH₵</span>

		        <input name="amount_out" value="<?=old_value('amount_out', $defaultAmountOut)?>" type="number" step="0.01" class="form-control" id="amount_out" <?=$isCompletedTransaction ? 'readonly' : ''?>>

		      </div>

		    </div>



			<div class="mb-3">

		      <label for="amount_cfa" class="form-label">CFA Notes Calculator</label>

		      <div class="input-group" <?=$isCompletedTransaction ? '' : 'data-bs-toggle="modal" data-bs-target="#staticBackdropCfa"'?>> 

		      	<span class="input-group-text"><i class="fa-solid fa-calculator px-1 text-warning"></i> CFA</span>

		        <input name="amount_cfa" value="<?=old_value('amount_cfa', $row->amount_cfa ?? 0)?>" type="number" step="0.01" class="form-control" id="amount_cfa" placeholder="Tap to set CFA amount" readonly>

		        <input name="buying_rate" value="<?=$settings->buying_rate ?? 0?>" type="hidden" step="0.01" class="form-control" id="buying_rate">

		        <input name="selling_rate" value="<?=$settings->selling_rate ?? 0?>" type="hidden" step="0.01" class="form-control" id="selling_rate">

		      </div>

		      <?php if(!empty($errors['amount_cfa'])):?>

		        <small class="text-danger"><?=$errors['amount_cfa']?></small>

		      <?php endif?>

		        <small class="text-danger js-amount-out-error"></small>

		    </div>



		    <div class="mb-3">

		      <label for="amount_ghs" class="form-label">GHS Notes Calculator</label>

		      <div class="input-group" <?=$isCompletedTransaction ? '' : 'data-bs-toggle="modal" data-bs-target="#staticBackdropGhs"'?>> 

		      	<span class="input-group-text"><i class="fa-solid fa-calculator px-1 text-success"></i> GH₵</span>

		        <input name="amount_ghs" value="<?=old_value('amount_ghs', $defaultAmountGhsField)?>" type="number" step="0.01" class="form-control" id="amount_ghs" placeholder="Tap to set GH₵ amount" readonly>

		    	<input type="hidden" id="denos" name="denos" value='<?=htmlspecialchars(json_encode($existingDenos), ENT_QUOTES, "UTF-8")?>' />

		      </div>

		      <?php if(!empty($errors['amount_ghs'])):?>

		        <small class="text-danger"><?=$errors['amount_ghs']?></small>

		      <?php endif?>

		        <small class="text-danger js-amount-in-error"></small>

		    </div>



			<div class="alert alert-info d-flex align-items-center gap-2 rounded-3 py-2 px-3 fs-6">

				<i class="fa-solid fa-vault"></i>

				<span>Edits sync directly to your active cashdrawer (GHS & CFA).</span>

			</div>

			<?php if($isCompletedTransaction):?>
				<div class="alert alert-warning d-flex align-items-center gap-2 rounded-3 py-2 px-3 fs-6">
					<i class="fa-solid fa-lock"></i>
					<span>This transaction is locked. Only status can be updated to Rejected.</span>
				</div>
			<?php endif;?>



			<div class="transaction_status mb-3">

			  <label for="transaction_status" class="form-label">Status</label>

			  <select class="form-select" id="transaction_status" name="transaction_status">

				  <?php if($isCompletedTransaction):?>
					  <option <?=old_select('transaction_status','Complete', $row->transaction_status ?? '')?> value="Complete">Complete</option>
					  <option <?=old_select('transaction_status','Rejected', $row->transaction_status ?? '')?> value="Rejected">Rejected</option>
				  <?php else:?>
					  <option value="">---Select---</option>
					  <option <?=old_select('transaction_status','Draft', $row->transaction_status ?? '')?> value="Draft">Draft</option>
					  <option <?=old_select('transaction_status','Pending', $row->transaction_status ?? '')?> value="Pending">Pending</option>
					  <?php if(user_can('approve_transactions')):?>
					  <option <?=old_select('transaction_status','Rejected', $row->transaction_status ?? '')?> value="Rejected">Rejected</option>
					  <option <?=old_select('transaction_status','Complete', $row->transaction_status ?? '')?> value="Complete">Complete</option>
					<?php endif;?>
				  <?php endif;?>

			  </select>

				<?php if(!empty($errors['transaction_status'])): ?>

				  <small class="text-danger"><?=$errors['transaction_status']?></small>

				<?php endif ?>

			</div>

			<div class="mb-3 <?= strtolower(trim((string)old_value('transaction_status', $row->transaction_status ?? ''))) === 'rejected' ? '' : 'd-none' ?>" id="cfaRejectionReasonGroup">

			  <label for="rejection_reason" class="form-label">Rejection reason</label>

				<textarea rows="2" id="rejection_reason" class="form-control" name="rejection_reason" placeholder="State clearly why this transaction is being rejected."><?=old_value('rejection_reason', $row->rejection_reason ?? '')?></textarea>

				<?php if(!empty($errors['rejection_reason'])):?>

				  <small class="text-danger w-100"><?=$errors['rejection_reason']?></small>

				<?php endif?>

				<small class="text-danger w-100 js-rejection-reason-error"></small>

			</div>

				

			<div class="mb-3">

			  <label for="description" class="form-label">Description</label>

				<textarea rows="2" id="description" class="form-control" name="description"  placeholder="Write a brief description for this transaction" <?=$isCompletedTransaction ? 'readonly' : ''?>><?=old_value('description', $row->description ?? '')?></textarea>

				<?php if(!empty($errors['description'])):?>

				  <small class="text-danger w-100"><?=$errors['description']?></small>

				<?php endif?>

			</div>

	 

			<div class="d-flex justify-content-between align-items-center mt-3 pt-2">

				<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">

					<button type="button" class="btn btn-outline-secondary px-4 rounded-pill">

						<i class="fa-solid fa-chevron-left me-1"></i> Back

					</button>

				</a>

				<button class="btn btn-primary px-4 rounded-pill" id="save" type="submit">

					<i class="fa-solid fa-save me-1"></i> Save Changes

				</button>

			</div>

		</div>

	</form>



<?php else: ?>

	<div class="alert alert-danger text-center">

		Access denied. You don’t have permission for this action.

	</div>

<?php endif ?>



<!-- Calculator modal CFA-->

<div class="modal fade col-7 mx-auto" id="staticBackdropCfa" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabelCfa" aria-hidden="true">

	<div class="modal-dialog">

		<div class="modal-content">

			<div class="modal-header">

				<h5 class="modal-title" id="staticBackdropLabelCfa">CFA Notes</h5>

				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

			</div>

			<div class="modal-body table-responsive">

				<table class="table table-hover table-sm js-denomination-table">

					<thead>

						<tr>

							<th>NOTES</th>

							<th>COUNT</th>

							<th>VOLUME</th>

						</tr>

					</thead>

					<tbody>

						<?php

							$cfaNotes = [10000,5000,2000,1000,500,200,100,50,20,10,5,2,1];

							foreach($cfaNotes as $note): ?>

						<tr data-denomination="<?=$note?>" data-currency="CFA">

							<td class="px-4"><?=number_format($note)?> CFA</td>

							<td><input type="number" min="0" class="form-control p-1 count-input" placeholder="Count" <?=$isCompletedTransaction ? 'readonly disabled' : ''?>></td>

							<td><input type="text" class="form-control p-1 volume-input" placeholder="Volume" readonly></td>

						</tr>

						<?php endforeach; ?>

					</tbody>

					<tfoot>

						<tr>

							<th class="px-4 bg-secondary text-warning">TOTAL</th>

							<th class="bg-secondary text-warning"><input type="text" class="form-control p-1" id="total-countCfa" style="border:none;" readonly></th>

							<th class="bg-secondary text-warning"><input type="text" class="form-control p-1" id="total-volumeCfa" style="border:none;" readonly></th>

						</tr>

					</tfoot>

				</table>

			</div>

			<div class="modal-footer">

				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>

				<button type="button" class="btn btn-primary submit">Confirm</button>

			</div>

		</div>

	</div>

</div>



<!-- Calculator modal GHS-->

<div class="modal fade col-10 mx-auto" id="staticBackdropGhs" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabelGhs" aria-hidden="true">

	<div class="modal-dialog">

		<div class="modal-content">

			<div class="modal-header">

				<h5 class="modal-title" id="staticBackdropLabelGhs">GHS Notes & Coins</h5>

				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

			</div>

			<div class="modal-body table-responsive">

				<table class="table table-hover table-sm js-denomination-table">

					<thead>

						<tr>

							<th>NOTES</th>

							<th>COUNT</th>

							<th>VOLUME</th>

						</tr>

					</thead>

					<tbody>

						<?php

							$ghsNotes = [200,100,50,20,10,5,2,1,0.5,0.2,0.1];

							foreach($ghsNotes as $note): ?>

						<tr data-denomination="<?=$note?>" data-currency="GHS">

							<td class="px-4">GH&cent;<?=rtrim(rtrim(number_format($note,2,'.',''), '0'),'.')?></td>

							<td><input type="number" min="0" class="form-control p-1 count-input" placeholder="Count" <?=$isCompletedTransaction ? 'readonly disabled' : ''?>></td>

							<td><input type="text" class="form-control p-1 volume-input" placeholder="Volume" readonly></td>

						</tr>

						<?php endforeach; ?>

					</tbody>

					<tfoot>

						<tr>

							<th class="px-4 bg-secondary text-warning">TOTAL</th>

							<th class="bg-secondary text-warning"><input type="text" class="form-control p-1" id="total-countGhs" style="border:none;" readonly></th>

							<th class="bg-secondary text-warning"><input type="text" class="form-control p-1" id="total-volumeGhs" style="border:none;" readonly></th>

						</tr>

					</tfoot>

				</table>

			</div>

			<div class="modal-footer">

				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>

				<button type="button" class="btn btn-primary submitGhs">Confirm</button>

			</div>

		</div>

	</div>

</div>



<script>

  document.addEventListener("DOMContentLoaded", function () {

	const countInputs = document.querySelectorAll(".count-input");

	const totalCountCfaField = document.getElementById("total-countCfa");

	const totalVolumeCfaField = document.getElementById("total-volumeCfa");

	const totalCountGhsField = document.getElementById("total-countGhs");

	const totalVolumeGhsField = document.getElementById("total-volumeGhs");

	const denosField = document.getElementById("denos");

	const submitBtn = document.querySelector('.submit');

	const submitGhsBtn = document.querySelector('.submitGhs');

	const saveBtn = document.getElementById('save');
	const transactionStatusSelect = document.getElementById('transaction_status');
	const rejectionReasonGroup = document.getElementById('cfaRejectionReasonGroup');
	const rejectionReasonInput = document.getElementById('rejection_reason');
	const rejectionReasonError = document.querySelector('.js-rejection-reason-error');

	const form = document.querySelector('form');

	if(form && saveBtn){

		form.addEventListener('submit', function(e){

			if(!syncRejectionReasonState()){
				e.preventDefault();
				return;
			}

			saveBtn.setAttribute('disabled', 'disabled');

			saveBtn.classList.add('disabled');

		});

	}



	const cfaModalInstance = new bootstrap.Modal(document.getElementById('staticBackdropCfa'));

	const ghsModalInstance = new bootstrap.Modal(document.getElementById('staticBackdropGhs'));

	const amountIn = document.getElementById('amount_in');

	const amountOut = document.getElementById('amount_out');

	const amountInLabel = document.querySelector('.js-converter-in-label');

	const amountOutLabel = document.querySelector('.js-converter-out-label');

	const amountInError = document.querySelector('.js-amount-in-error');

	const amountOutError = document.querySelector('.js-amount-out-error');

	const transactionTypeSelect = document.getElementById('transaction_type');



	const amountCfaInput = document.getElementById('amount_cfa');

	const amountGhsInput = document.getElementById('amount_ghs');

	const buyingRate = <?=$settings->buying_rate ?? 0?>;

	const sellingRate = <?=$settings->selling_rate ?? 0?>;



	function roundToTwoFixed(value){
		const num = parseFloat(value) || 0;
		return (Math.round((num + Number.EPSILON) * 100) / 100).toFixed(2);
	}

	function normalizePayableGhsFixed(value){
		const num = Math.max(0, parseFloat(value) || 0);
		const scaled = num * 10;
		const nearest = Math.round(scaled);
		const normalized = Math.abs(scaled - nearest) <= 0.00001
			? nearest / 10
			: Math.floor(scaled + 0.00001) / 10;
		return normalized.toFixed(2);
	}



	let savedDenos = [];

	try {

		savedDenos = JSON.parse(denosField.value || '[]') || [];

	} catch (error) {

		savedDenos = [];

	}



	function getFlow(){

		const value = (transactionTypeSelect.value || '').toLowerCase();

		if(value.includes('gh') && value.includes('cfa') && !value.includes('cfa to gh')){

			return {

				inLabel: 'GH₵',

				outLabel: 'CFA',

				rate: sellingRate,

				inField: amountGhsInput,

				outField: amountCfaInput,

			};

		}

		return {

			inLabel: 'CFA',

			outLabel: 'GH₵',

			rate: buyingRate,

			inField: amountCfaInput,

			outField: amountGhsInput,

		};

	}



	function formatNumber(value){

		return Number(parseFloat(value ?? 0).toFixed(2));

	}



	function syncConverterUI(){

		const flow = getFlow();

		amountInLabel.textContent = flow.inLabel;

		amountOutLabel.textContent = flow.outLabel;

		amountIn.value = flow.inField.value || 0;

		amountOut.value = flow.outField.value || 0;

	}



	function calculateExchangeValue(source){

		const flow = getFlow();

		const amountInValue = parseFloat(amountIn.value) || 0;

		const amountOutValue = parseFloat(amountOut.value) || 0;



		if(flow.inLabel === 'CFA'){

			if(source === 'in'){

				amountOut.value = normalizePayableGhsFixed(amountInValue * (buyingRate / 1000));

			}else{

				amountIn.value = roundToTwoFixed(amountOutValue * (1000 / buyingRate));

			}

		}else{

			if(source === 'in'){

				amountOut.value = roundToTwoFixed(amountInValue * (1000 / sellingRate));

			}else{

				amountIn.value = roundToTwoFixed(amountOutValue * (sellingRate / 1000));

			}

		}

	}



	function toggleSaveButton(disable){

		if(!saveBtn){

			return;

		}

		if(disable){

			saveBtn.classList.add('disabled');

			saveBtn.setAttribute('disabled', 'disabled');

		}else{

			saveBtn.classList.remove('disabled');

			saveBtn.removeAttribute('disabled');

		}

	}

	function statusNeedsRejectionReason(){

		return String(transactionStatusSelect?.value || '').trim().toLowerCase() === 'rejected';

	}

	function syncRejectionReasonState(){

		const requiresReason = statusNeedsRejectionReason();

		if(rejectionReasonGroup){
			rejectionReasonGroup.classList.toggle('d-none', !requiresReason);
		}

		if(rejectionReasonInput){
			if(requiresReason){
				rejectionReasonInput.setAttribute('required', 'required');
			}else{
				rejectionReasonInput.removeAttribute('required');
			}
		}

		const hasReason = !requiresReason || String(rejectionReasonInput?.value || '').trim() !== '';
		if(rejectionReasonError){
			rejectionReasonError.textContent = hasReason ? '' : 'Rejection reason is required.';
		}
		if(requiresReason && !hasReason){
			toggleSaveButton(true);
			return false;
		}
		if(!requiresReason){
			if(rejectionReasonError){
				rejectionReasonError.textContent = '';
			}
			toggleSaveButton(false);
		}else{
			toggleSaveButton(false);
		}
		return hasReason;

	}



	function checkAmountPaid(){

		const flow = getFlow();

		const converterIn = formatNumber(amountIn.value);

		const converterOut = formatNumber(amountOut.value);

		const fieldIn = formatNumber(flow.inField.value);

		const fieldOut = formatNumber(flow.outField.value);

		const hasValues = [converterIn, converterOut, fieldIn, fieldOut].some(val => val > 0);



		if(!hasValues){

			amountInError.textContent = '';

			amountOutError.textContent = '';

			amountIn.classList.remove('border-danger');

			amountOut.classList.remove('border-danger');

			toggleSaveButton(false);
			syncRejectionReasonState();

			return;

		}



		if(Math.abs(converterIn - fieldIn) > 0.01){

			amountInError.textContent = `Converter ${flow.inLabel} amount must match the denomination total.`;

			amountIn.classList.add('border-danger');

		}else{

			amountInError.textContent = '';

			amountIn.classList.remove('border-danger');

		}



		if(Math.abs(converterOut - fieldOut) > 0.01){

			amountOutError.textContent = `Converter ${flow.outLabel} amount must match the denomination total.`;

			amountOut.classList.add('border-danger');

		}else{

			amountOutError.textContent = '';

			amountOut.classList.remove('border-danger');

		}



		toggleSaveButton(false);
		syncRejectionReasonState();

	}



	countInputs.forEach(input => {

		input.addEventListener("input", function(){

			updateVolumesAndTotals();

		});

	});



	function updateVolumesAndTotals(){

		let totalCountCfa = 0;

		let totalCountGhs = 0;

		let totalVolumeCfa = 0;

		let totalVolumeGhs = 0;

		const currency = [];



		countInputs.forEach(input => {

			const row = input.closest("tr");

			const denomination = parseFloat(row.getAttribute("data-denomination"));

			const currencyType = row.getAttribute("data-currency");

			const count = parseFloat(input.value) || 0;

			const volumeField = row.querySelector(".volume-input");

			const volume = denomination * count;

			volumeField.value = volume.toFixed(2);



			if (currencyType === "CFA") {

				totalCountCfa += count;

				totalVolumeCfa += volume;

			}



			if (currencyType === "GHS") {

				totalCountGhs += count;

				totalVolumeGhs += volume;

			}



			currency.push({

				currency: currencyType,

				denomination: denomination,

				count: count,

				volume: volume,

			});

		});



		totalCountCfaField.value = totalCountCfa;

		totalVolumeCfaField.value = totalVolumeCfa.toFixed(2);

		totalCountGhsField.value = totalCountGhs;

		totalVolumeGhsField.value = totalVolumeGhs.toFixed(2);



		const serializedCurrency = JSON.stringify(currency);

		localStorage.setItem("denosData", serializedCurrency);

		denosField.value = serializedCurrency;

	}



	function hydrateSavedDenos(){

		if(!Array.isArray(savedDenos)) return;

		savedDenos.forEach(item => {

			const selector = `tr[data-currency="${item.currency ?? ''}"][data-denomination="${parseFloat(item.denomination ?? 0)}"] .count-input`;

			const input = document.querySelector(selector);

			if(input){

				input.value = item.count ?? 0;

				const volumeField = input.closest('tr').querySelector('.volume-input');

				if(volumeField){

					volumeField.value = (parseFloat(item.volume ?? 0)).toFixed(2);

				}

			}

		});

		updateVolumesAndTotals();

	}



	submitBtn.addEventListener('click', function(){

		amountCfaInput.value = totalVolumeCfaField.value || 0;

		updateVolumesAndTotals();

		cfaModalInstance.hide();

		syncConverterUI();

		checkAmountPaid();

	});



	submitGhsBtn.addEventListener('click', function(){

		amountGhsInput.value = totalVolumeGhsField.value || 0;

		updateVolumesAndTotals();

		ghsModalInstance.hide();

		syncConverterUI();

		checkAmountPaid();

	});



	amountIn.addEventListener("input", function(){

		calculateExchangeValue('in');

		checkAmountPaid();

	});



	amountOut.addEventListener("input", function(){

		calculateExchangeValue('out');

		checkAmountPaid();

	});



	transactionTypeSelect.addEventListener('change', function(){

		syncConverterUI();

		calculateExchangeValue('in');

		checkAmountPaid();

	});

	if(transactionStatusSelect){
		transactionStatusSelect.addEventListener('change', syncRejectionReasonState);
	}

	if(rejectionReasonInput){
		rejectionReasonInput.addEventListener('input', syncRejectionReasonState);
	}



	hydrateSavedDenos();

	syncConverterUI();

	calculateExchangeValue('in');

	checkAmountPaid();
	syncRejectionReasonState();

	localStorage.setItem("denosData", JSON.stringify(savedDenos));

	});

</script>