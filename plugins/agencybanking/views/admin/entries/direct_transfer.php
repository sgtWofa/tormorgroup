<?php if(user_can('add_agencybanking') || user_can('edit_momoremit')):?>
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

		.transaction-preview-card{
			position: relative;
			width: 100%;
			max-width: 240px;
			margin: 0 auto;
			border-radius: 14px;
			overflow: hidden;
			border: 1px solid rgba(229,231,235,0.25);
			background: rgba(15, 23, 42, 0.10);
			box-shadow: 0 12px 28px rgba(0,0,0,0.18);
			cursor: pointer;
			outline: none;
		}
		.transaction-preview-card:focus{
			box-shadow: 0 0 0 3px rgba(234,179,8,0.35), 0 12px 28px rgba(0,0,0,0.18);
		}
		.transaction-preview-card img{
			display:block;
			width:100%;
			height: 160px;
			object-fit: cover;
		}
		.transaction-preview-placeholder{
			height: 160px;
			display:flex;
			align-items:center;
			justify-content:center;
			flex-direction:column;
			gap:.35rem;
			border: 1px dashed rgba(234,179,8,0.45);
			border-radius: 14px;
			margin: .5rem;
			color: rgba(229,231,235,0.85);
			background: rgba(2,6,23,0.35);
		}
		.transaction-preview-placeholder .title{
			font-weight: 700;
			font-size: .9rem;
		}
		.transaction-preview-placeholder .hint{
			font-size: .78rem;
			opacity: .85;
		}
		.transaction-preview-overlay{
			position:absolute;
			inset:0;
			display:flex;
			align-items:flex-end;
			justify-content:flex-end;
			padding: .5rem;
			background: linear-gradient(180deg, rgba(2,6,23,0) 30%, rgba(2,6,23,0.75) 100%);
			opacity: 0;
			transition: opacity .18s ease;
		}
		.transaction-preview-card:hover .transaction-preview-overlay{
			opacity: 1;
		}
		.transaction-preview-badge{
			display:inline-flex;
			align-items:center;
			gap:.35rem;
			font-size: .8rem;
			font-weight: 600;
			padding: .3rem .6rem;
			border-radius: 999px;
			background: rgba(234,179,8,0.92);
			color: #111827;
		}
		.visually-hidden-file{
			position:absolute !important;
			width:1px !important;
			height:1px !important;
			padding:0 !important;
			margin:-1px !important;
			overflow:hidden !important;
			clip: rect(0,0,0,0) !important;
			white-space:nowrap !important;
			border:0 !important;
		}
	</style>
	<form method="post" enctype="multipart/form-data">		
		<div class="row col-9 col-lg-6 mx-auto shadow p-4 rounded mb-5">
			
			<?=csrf()?>

			<h4 class="text-center">
				<span><img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:60px;max-height: 60px;object-fit: cover;"></span>
				Direct Transfer
			</h4>
	 
			<div class="mb-1">
				<label for="transaction_type" class="form-label">Transaction type</label>
				<select class="form-select transaction_type" id="transaction_type" name="transaction_type" onchange="updateSourceOptions();">
				  <option value="">---Select---</option>
				  <option <?=old_select('transaction_type','Direct transfer (CIT)')?> value="Direct transfer (CIT)" selected>Direct transfer (CIT)</option>
				</select>
				<?php if(!empty($errors['transaction_type'])):?>
				  <small class="text-danger"><?=$errors['transaction_type']?></small>
				<?php endif?>
			</div>
	 
			<div class="mb-1">
				<label for="network" class="form-label">Source bank</label>
				<select class="form-select" name="network" id="network">			  
				  <!-- Options will be populated dynamically based on transaction type -->
				</select>
				<?php if(!empty($errors['network'])):?>
				  <small class="text-danger"><?=$errors['network']?></small>
				<?php endif?>
			</div>
	 

			<div class="mb-1">
			  <label for="phone" class="form-label">Phone</label>
			  <input name="phone" value="<?=old_value('phone')?>" type="text" class="form-control" id="phone" placeholder="Enter phone number">
				
				<?php if(!empty($errors['phone'])):?>
				  <small class="text-danger"><?=$errors['phone']?></small>
				<?php endif?>
			</div>

			<div class="mb-1 account_number">
			  <label for="account_number" class="form-label">Customer name</label>
			  <input name="account_number" value="<?=old_value('account_number')?>" type="text" class="form-control" id="account_number" placeholder="Customer name">
				
				<?php if(!empty($errors['account_number'])):?>
				  <small class="text-danger"><?=$errors['account_number']?></small>
				<?php endif?>
			</div>

			<div class="mb-1 balance_before">
			  <label for="balance_before" class="form-label">Amount Transferred</label>
			  <input name="balance_before" value="<?=old_value('balance_before')?>" type="number" step="0.01" class="form-control" id="balance_before" placeholder="Enter amount transferred">
				
				<?php if(!empty($errors['balance_before'])):?>
				  <small class="text-danger"><?=$errors['balance_before']?></small>
				<?php endif?>
			</div>

			<div class="mb-1 charge">
			  <label for="charge" class="form-label">Charge <small>(to be negotiated)</small></label>
			  <input name="charge" value="<?=old_value('charge')?>" type="number" step="0.01" class="form-control" id="charge" placeholder="Enter charge">
				
				<?php if(!empty($errors['charge'])):?>
				  <small class="text-danger"><?=$errors['charge']?></small>
				<?php endif?>
			</div>

			<div class="mb-1 amount_to_pay">
			  <label for="amount_to_pay" class="form-label">Amount to Pay</label>
			  <input name="amount_to_pay" value="<?=old_value('amount_to_pay')?>" type="text" class="form-control" id="amount_to_pay" placeholder="GH&cent;" readonly>
				  <small class="text-danger" id="amount_to_pay-error"></small>
			</div>

			<div class="col-md-12">
			  <label for="transaction_image" class="form-label">Transaction image</label>
			  <div class="mt-2" id="transactionImagePreviewWrap">
					<div class="transaction-preview-card" id="transactionImagePreviewCard" role="button" tabindex="0">
						<div class="transaction-preview-placeholder" id="transactionImagePreviewPlaceholder">
							<i class="fa-solid fa-cloud-arrow-up" style="font-size:1.4rem;"></i>
							<div class="title">Upload transaction picture</div>
							<div class="hint">Tap to choose an image</div>
						</div>
						<img id="transactionImagePreview" src="" alt="Transaction message picture" style="display:none;">
						<div class="transaction-preview-overlay">
							<span class="transaction-preview-badge"><i class="fa-solid fa-camera"></i> Change</span>
						</div>
					</div>
			  	<small class="text-muted d-block mt-1 text-center">Tap the preview to change the image</small>
			  </div>
			  
			  <small class="text-danger js-transaction-image-error text-center d-block"><?=(string)($errors['transaction_image'] ?? '')?></small>
			  <input name="transaction_image" type="file" class="visually-hidden-file" id="transaction_image" accept="image/*">
			</div>

		    <!-- Amount Input with Calculator Modal Trigger -->
		    <div class="col-md-12">
		      <label for="amount" class="form-label">Amount Paid Out</label>
			   <label class="input-group" data-bs-toggle="modal" data-bs-target="#staticBackdrop"> 
	        <i class="fa-solid fa-calculator input-group-text text-warning"></i>
	          <input name="amount" value="<?=old_value('amount')?>" type="number" step="0.01" class="form-control" id="amount" placeholder="Enter amount" readonly>
		      <input type="hidden" id="denos" name="denos" value="<?=htmlspecialchars((string)old_value('denos'), ENT_QUOTES, 'UTF-8')?>" />
	      </label>
	      <?php if(!empty($errors['amount'])):?>
	        <small class="text-danger"><?=$errors['amount']?></small>
	    <?php endif;?>
				 <small class="text-danger" id="amount_to_pay-error"></small>
		    </div>

			<div class="mb-1 transaction_status">
			  <label for="transaction_status" class="form-label">Status</label>
			  <select class="form-select transaction_status" name="transaction_status">
				  <option value="">---Select---</option>
				  <option <?=old_select('transaction_status','Draft')?> value="Draft">Draft</option>
				  <option <?=old_select('transaction_status','Pending')?> value="Pending">Pending</option>
				 <?php if(user_can('approve_transactions')):?>
				  <option <?=old_select('transaction_status','Rejected')?> value="Rejected">Rejected</option>
				  <option <?=old_select('transaction_status','Approved','Approved')?> value="Approved">Approved</option>
				<?php endif;?>
			  </select>
			</div>
	 
			<div class="d-flex justify-content-between" style="margin-top:10px;">
				<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
					<button type="button" class="btn btn-primary">
						<i class="fa-solid fa-chevron-left"></i> Back
					</button>
				</a>
				<button class="btn btn-danger" id="save">
					<i class="fa-solid fa-save"></i> Save
				</button>
			</div>
		</div>
	</form>

	<!-- Calculator modal -->
	<div class="modal fade" id="staticBackdrop" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
	  <div class="modal-dialog">
	    <div class="modal-content">
	      <!-- Modal Header -->
	      <div class="modal-header">
	        <h5 class="modal-title" id="staticBackdropLabel">Currency Notes</h5>
	        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
	      </div>

	      <!-- Modal Body -->
	      <div class="modal-body table-responsive">

	        <table class="table table-hover table-sm">
	          <thead>
	            <tr>
	              <th>NOTES</th>
	              <th>COUNT</th>
	              <th>VOLUME</th>
	            </tr>
	          </thead>
	          <tbody>
	            <tr data-denomination="200">
	              <td class="px-5">GH&cent;200</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Volume" readonly></td>
	            </tr>
	            <tr data-denomination="100">
	              <td class="px-5">GH&cent;100</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Volume" readonly></td>
	            </tr>
	             <tr data-denomination="50">
	              <td class="px-5">GH&cent;50</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
	            </tr>
	            <tr data-denomination="20">
	              <td class="px-5">GH&cent;20</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
	            </tr>
	            <tr data-denomination="10">
	              <td class="px-5">GH&cent;10</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
	            </tr>
	            <tr data-denomination="5">
	              <td class="px-5">GH&cent;5</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
	            </tr>
	            <tr data-denomination="2">
	              <td class="px-5">GH&cent;2</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
	            </tr>
	            <tr data-denomination="1">
	              <td class="px-5">GH&cent;1</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
	            </tr>
	            <tr>
	              <td colspan="3">COINS</td>
	            </tr>
	            <tr data-denomination="0.5">
	              <td class="px-5">50p</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
	            </tr>

	            <tr data-denomination="0.2">
	              <td class="px-5">20p</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
	            </tr>

	            <tr data-denomination="0.1">
	              <td class="px-5">10p</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
	            </tr>
	          </tbody>
	          <tfoot>
	            <tr>
	              <th class="px-5 bg-secondary text-warning">TOTAL</th>
	              <th class="bg-secondary text-warning"><input type="text" class="form-control p-1" id="total-count" style="border:none; " placeholder="Total count" readonly></th>
	              <th class="bg-secondary text-warning"><input type="text" class="form-control p-1" id="total-volume" style="border:none; " placeholder="Total volume" readonly></th>
	            </tr>
	            <tr><th colspan="3" class="text-center"><p class="text-info mb-1" id="required-currency-total">Required total: 0.00</p><p class="text-danger" id="currency-total-error" style="display:none;"></p></th></tr>
	          </tfoot>
	        </table>
	      </div>

	      <!-- Modal Footer -->
	      <div class="modal-footer">
	        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
	        <button type="button" class="btn btn-danger reset" >Reset</button>
	        <button type="button" class="btn btn-warning restore" >Restore</button>
	        <button type="button" class="btn btn-primary submit">Confirm</button>
	      </div>
	    </div>
	  </div>
	</div>

<?php else:?>
	<div class="alert alert-danger text-center">
		Access denied. You dont have permission for this action
	</div>
<?php endif?>

<script>
	<?=get_app_image_cropper_inline_source()?>
	const oldNetworkValue = "<?=htmlspecialchars((string)old_value('network'), ENT_QUOTES, 'UTF-8')?>";

	const sourceOptions = {
		
		"Direct transfer (CIT)": [
			{ value: "", text: "---Select---" },
			{ value: "ABSA", text: "ABSA" },
			{ value: "Access Bank", text: "Access Bank" },
			{ value: "ADB", text: "ADB" },
			{ value: "APEX BANK", text: "APEX BANK" },
			{ value: "Bank of Africa", text: "Bank of Africa" },
			{ value: "Cal bank", text: "Cal bank" },
			{ value: "CBG", text: "CBG" },
			{ value: "Ecobank", text: "Ecobank" },
			{ value: "Fidelity", text: "Fidelity" },
			{ value: "First Atlantic Bank", text: "First Atlantic Bank" },
			{ value: "First Bank Ghana", text: "First Bank Ghana" },
			{ value: "FNB", text: "FNB" },
			{ value: "GCB", text: "GCB" },
			{ value: "Ghana Pay", text: "Ghana Pay" },
			{ value: "Gtbank", text: "Gtbank" },
			{ value: "NIB", text: "NIB" },
			{ value: "Prudential Bank", text: "Prudential Bank" },
			{ value: "Republic Bank", text: "Republic Bank" },
			{ value: "OmniBSIC Bank", text: "OmniBSIC Bank" },
			{ value: "Society General", text: "Society General" },
			{ value: "Stanbic Bank", text: "Stanbic Bank" },
			{ value: "Standard Charted", text: "Standard Charted" },
			{ value: "UBA", text: "UBA" },
			{ value: "umb", text: "umb" },
			{ value: "Zenit Bank", text: "Zenit Bank" },
		],
	};

	const transactionType = document.getElementById('transaction_type'); // Corrected ID selection
	const sourceSelect = document.getElementById('network');
	const amountInput = document.getElementById('amount');
	const amount_to_payInput = document.getElementById('amount_to_pay');
	const chargeInput = document.getElementById('charge');
	const saveBtn = document.getElementById('save');
	const amount_to_payError = document.querySelector('#amount_to_pay-error');
	saveBtn.disabled= true;

	function updateSourceOptions() {
		const transactionTypeValue = transactionType.value;
		sourceSelect.innerHTML = ""; // Clear current options

		// Populate options based on selected transaction type
		if (sourceOptions[transactionTypeValue]) {
			sourceOptions[transactionTypeValue].forEach(option => {
				const opt = document.createElement('option');
				opt.value = option.value;
				opt.textContent = option.text;
				sourceSelect.appendChild(opt);
			});
			if (oldNetworkValue) {
				sourceSelect.value = oldNetworkValue;
			}
		} 
	}


	function calculateAmountPayable() {
		const transactionType = document.getElementById('transaction_type').value;
		const balanceBefore = parseFloat(document.getElementById('balance_before').value) || 0;
		const amount = parseFloat(document.getElementById('amount').value) || 0;
		const charge = parseFloat(document.getElementById('charge').value) || 0;
		const amount_to_payField = document.getElementById('amount_to_pay');

		let newBalance = balanceBefore;

		// Adjust balance based on transaction type
		switch (transactionType) {
			case 'Direct transfer (CIT)':
				newBalance = balanceBefore - charge;
				break;
			default:
				// For other transaction types, keep balance unchanged or handle as needed
				newBalance = balanceBefore;
				break;
		}

		amount_to_payField.value = newBalance.toFixed(2);
		const requiredLabel = document.getElementById('required-currency-total');
		if (requiredLabel) requiredLabel.textContent = 'Required total: ' + newBalance.toFixed(2);
		if (amount > newBalance || amount < newBalance) {
			amount_to_payError.innerText = "Amount paid out must be equal to amount to pay";
			saveBtn.disabled = true;
			return;
		}else{
			amount_to_payError.innerText = "";
			saveBtn.disabled = false;
		}
	}

	// Initialize on page load
	document.addEventListener("DOMContentLoaded", function() {

      const countInputs = document.querySelectorAll(".count-input");
	  const totalCountField = document.getElementById("total-count");
	  const totalVolumeField = document.getElementById("total-volume");
	  const denosField = document.querySelector("#denos");
	  const submitBtn = document.querySelector('.submit');
	  const restoreBtn = document.querySelector('.restore');
	  const resetBtn = document.querySelector('.reset');
	  const staticBackdropModal = new bootstrap.Modal(document.getElementById('staticBackdrop'));
	  const modalEl = document.getElementById('staticBackdrop');
	  const amountInput = document.getElementById("amount");
	  const amountToPayField = document.getElementById('amount_to_pay');
	  const requiredCurrencyTotal = document.getElementById('required-currency-total');
	  const currencyTotalError = document.getElementById('currency-total-error');
	  function updateRequiredCurrencyTotal(){
		const required = parseFloat(String(amountToPayField ? amountToPayField.value : '').replace(/,/g, '')) || 0;
		if(requiredCurrencyTotal) requiredCurrencyTotal.textContent = 'Required total: ' + required.toFixed(2);
		return required;
	  }

	  function getSeedDenos(){
		const fromField = (denosField && (denosField.value || '').trim() !== '') ? (denosField.value || '').trim() : '';
		if(fromField){
			try{
				const parsed = JSON.parse(fromField);
				if(Array.isArray(parsed)) return parsed;
			}catch(e){}
		}
		try{
			const saved = localStorage.getItem('denosData');
			const parsed = saved ? JSON.parse(saved) : null;
			return Array.isArray(parsed) ? parsed : [];
		}catch(e){
			return [];
		}
	  }

	  if (modalEl) {
		modalEl.addEventListener('show.bs.modal', function(){
			restoreDenominations();
			updateRequiredCurrencyTotal();
		});
	  }

	  function restoreDenominations() {
		const currency = getSeedDenos();
		if (currency && currency.length) {
			const rowMeta = Array.from(countInputs).map((input, idx) => {
				const row = input.closest('tr');
				return {
					idx,
					denomination: row ? parseFloat(row.getAttribute('data-denomination')) : NaN,
				};
			});
			const usedRowIdx = new Set();

			currency.forEach((item) => {
				let targetIdx = (item && item.row_index !== undefined && item.row_index !== null) ? parseInt(item.row_index) : NaN;
				if (!Number.isInteger(targetIdx) || !countInputs[targetIdx]) {
					targetIdx = NaN;
				}
				if (!Number.isInteger(targetIdx)) {
					const denom = parseFloat(item.denomination);
					const match = rowMeta.find(m => !usedRowIdx.has(m.idx) && m.denomination === denom);
					if (match) targetIdx = match.idx;
				}
				if (!Number.isInteger(targetIdx)) return;
				if (usedRowIdx.has(targetIdx)) return;

				const input = countInputs[targetIdx];
				const row = input.closest('tr');
				const volumeField = row ? row.querySelector('.volume-input') : null;
				input.value = item.count;
				if (volumeField) {
					volumeField.value = (parseFloat(item.denomination) * (parseFloat(item.count) || 0)).toFixed(2);
				}
				usedRowIdx.add(targetIdx);
			});

			updateVolumesAndTotals();
		}
	  }

	  function resetDenominations() {
		countInputs.forEach((input) => {
			const row = input.closest('tr');
			const volumeField = row ? row.querySelector('.volume-input') : null;
			input.value = '';
			if (volumeField) volumeField.value = '';
		});
		updateVolumesAndTotals();
		try {
			localStorage.setItem('denosData', JSON.stringify([]));
		} catch (e) {}
		if (denosField) denosField.value = JSON.stringify([]);
	  }
	countInputs.forEach(input => {
      input.addEventListener("input", updateVolumesAndTotals);
    });

    function updateVolumesAndTotals() {
	  let totalCount = 0;
	  let totalVolume = 0;
	  const currency = [];

	  countInputs.forEach(input => {
	    const row = input.closest("tr");
	    const denomination = parseFloat(row.getAttribute("data-denomination"));
	    const count = parseFloat(input.value) || 0;
	    const volumeField = row.querySelector(".volume-input");
	    const volume = denomination * count;

	    volumeField.value = volume.toFixed(2);
	    totalCount += count;
	    totalVolume += volume;

	    // Only add to currency array if count is greater than zero
	    if (count > 0) {
	      const denos = {
	        currency: `GHS`,
	        denomination: denomination,
	        count: count,
	        volume: volume,
	      };
	      currency.push(denos);
	    }
	  });

	  totalCountField.value = totalCount;
	  totalVolumeField.value = totalVolume.toFixed(2);

	  // Store serialized currency details in both localStorage and the hidden field
	  const serializedCurrency = JSON.stringify(currency);
	  localStorage.setItem("denosData", serializedCurrency);
	  denosField.value = serializedCurrency;
	}

	submitBtn.addEventListener('click', function() {
	const required = updateRequiredCurrencyTotal();
	const entered = parseFloat(totalVolumeField.value || '0') || 0;
	if (required <= 0 || Math.abs(required - entered) > 0.001) {
	  currencyTotalError.textContent = 'Currency total must equal ' + required.toFixed(2) + '. Entered total: ' + entered.toFixed(2) + '.';
	  currencyTotalError.style.display = 'block';
	  amountInput.value = '';
	  return;
	}
	currencyTotalError.style.display = 'none';
	amountInput.value = entered.toFixed(2);
   		updateVolumesAndTotals();
   		calculateAmountPayable();
      staticBackdropModal.hide();
    });
	    

    restoreBtn.addEventListener('click', function() {
    	// Restore denominations
  		restoreDenominations();
		updateRequiredCurrencyTotal();
    });

    resetBtn.addEventListener('click', function() {
    	// Reset denominations
  		resetDenominations();
    });

		restoreDenominations();
		updateSourceOptions();
	});

	// Event listeners for real-time updates
	transactionType.addEventListener("change", updateSourceOptions);
	transactionType.addEventListener("change", calculateAmountPayable);
	document.getElementById('amount').addEventListener('input', function() {
		calculateAmountPayable();
		saveBtn.disabled = false;
	});
	document.getElementById('balance_before').addEventListener('input', calculateAmountPayable);
	document.getElementById('charge').addEventListener('input', calculateAmountPayable);

	(function(){
		if(!window.AppImageCropper || typeof window.AppImageCropper.bindPreviewCard !== 'function') return;

		window.AppImageCropper.bindPreviewCard({
			inputId: 'transaction_image',
			cardId: 'transactionImagePreviewCard',
			previewId: 'transactionImagePreview',
			placeholderId: 'transactionImagePreviewPlaceholder',
			errorSelector: '.js-transaction-image-error',
			aspectRatio: 1.5,
			maxBytes: window.AppImageCropper.MAX_BYTES,
			modalTitle: 'Crop transaction picture',
			required: true,
			requiredMessage: 'Transaction picture is required'
		});
	})();
</script>

