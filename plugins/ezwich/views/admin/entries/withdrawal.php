<?php if(user_can('add_agencybanking')):?>
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
		<div class="rowcol-md-8 col-lg-5 mx-auto shadow p-2 rounded">
			
			<?=csrf()?>

			<!-- Hidden field to store cashdrawer denominations for validation -->
			<input type="hidden" id="cashdrawerDenos" value="<?=htmlspecialchars(json_encode($cashdrawerData['denos'] ?? []), ENT_QUOTES, 'UTF-8')?>">

			<h4 class="text-center">
				<span><img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:60px;max-height: 60px;object-fit: cover;"></span>
				Ezwich Withdrawal
			</h4>
	 
			<div class="col-mb-12 ">
				<label for="transaction_type" class="form-label">Transaction type</label>
				<select class="form-select transaction_type" id="transaction_type" name="transaction_type" >
				  <option <?=old_select('transaction_type','Withdrawal')?> value="Withdrawal" selected>Withdrawal</option>
				</select>
				<?php if(!empty($errors['transaction_type'])): ?>
				  <small class="text-danger"><?=$errors['transaction_type']?></small>
				<?php endif ?>
			</div>
	 
			<div class="col-mb-12 ">
				<label for="transfer_type" class="form-label">Withdrawal type</label>
				<select class="form-select" name="transfer_type" id="transfer_type">	
				  <option value="">---Select---</option>
				  <option <?=old_select('transfer_type','All')?> value="All">All</option>
				  <option <?=old_select('transfer_type','Part')?> value="Part">Part</option>
				  <option <?=old_select('transfer_type','Full')?> value="Full">Full</option>
				</select>
				<?php if(!empty($errors['transfer_type'])): ?>
				  <small class="text-danger"><?=$errors['transfer_type']?></small>
				<?php endif ?>
				</div>

			<div class="col-mb-12  usn">
			  <label for="usn" class="form-label">Client USN</label>
			  <input name="usn" value="<?=old_value('usn')?>" type="text" class="form-control" id="usn" placeholder="Enter usn used">				
				<?php if(!empty($errors['usn'])): ?>
				  <small class="text-danger"><?=$errors['usn']?></small>
				<?php endif ?>
			</div>

			<div class="col-mb-12 ">
			  <label for="phone" class="form-label">Client's Phone</label>
			  <input name="phone" value="<?=old_value('phone')?>" type="text" class="form-control" id="phone" placeholder="Enter phone number">				
				<?php if(!empty($errors['phone'])): ?>
				  <small class="text-danger"><?=$errors['phone']?></small>
				<?php endif ?>
			</div>

			<div class="col-mb-12  receiving d-none">
			  <label for="receiving" class="form-label">Receiving sim</label>
			  <select class="form-select" name="receiving" id="receiving">	
				  <option value="">---Select---</option>
				  <option <?=old_select('transfer_to','Company sim')?> value="Company sim" selected>Company sim</option>
				  <option <?=old_select('transfer_to','Client sim')?> value="Client sim">Client sim</option>
			  </select>
			  <?php if(!empty($errors['receiving'])): ?>
				  <small class="text-danger"><?=$errors['receiving']?></small>
			  <?php endif ?>
			</div>		    

			<div class="col-mb-12  receiving_sim d-none">
			  <label for="receiving_sim" class="form-label">Withdrawal to</label>
			  <input name="receiving_sim" value="<?=old_value('receiving_sim')?>" type="text" class="form-control" id="receiving_sim" placeholder="Enter sim number">			
				<?php if(!empty($errors['receiving_sim'])): ?>
				  <small class="text-danger"><?=$errors['receiving_sim']?></small>
				<?php endif ?>
			</div>

			<div class="col-mb-12  balance d-none">
			  <label for="balance" class="form-label">Current Balance</label>
			  <input name="balance" value="<?=old_value('balance')?>" type="number" class="form-control" id="balance" placeholder="Enter current balance">			
				<?php if(!empty($errors['balance'])): ?>
				  <small class="text-danger"><?=$errors['balance']?></small>
				<?php endif ?>
			</div>

		    <div class="col-mb-12">
		      <label for="amount_payable" class="form-label">Amount to withdraw</label>
		          <input name="amount_payable" value="<?=old_value('amount_payable')?>" type="hidden" step="0.01" class="form-control" id="amount_payable" placeholder="Enter amount to withdraw">
		          <input name="amount_payableRounded" value="<?=old_value('amount_payableRounded')?>" type="readonly" step="0.01" class="form-control" id="amount_payableRounded" placeholder="system generated">
		      <?php if(!empty($errors['amount_payable'])):?>
		        <small class="text-danger"><?=$errors['amount_payable']?></small>
		      <?php endif?>
		    </div>

		    <div class="col-mb-12">
		      <label for="charge" class="form-label">Charge</label>
		          <input name="charge" value="<?=old_value('charge')?>" type="number" step="0.01" class="form-control" id="charge" readonly>
		    </div>

			<div class="col-md-12">
			  <label for="merchant_receipt" class="form-label">Merchant receipt</label>
			  <div class="mt-2" id="merchantReceiptPreviewWrap">
					<div class="transaction-preview-card" id="merchantReceiptPreviewCard" role="button" tabindex="0">
						<div class="transaction-preview-placeholder" id="merchantReceiptPreviewPlaceholder">
							<i class="fa-solid fa-cloud-arrow-up" style="font-size:1.4rem;"></i>
							<div class="title">Upload merchant receipt</div>
							<div class="hint">Tap to choose an image</div>
						</div>
						<img id="merchantReceiptPreview" src="" alt="Merchant receipt" style="display:none;">
						<div class="transaction-preview-overlay">
							<span class="transaction-preview-badge"><i class="fa-solid fa-camera"></i> Change</span>
						</div>
					</div>
			  	<small class="text-muted d-block mt-1 text-center">Tap the preview to change the image</small>
			  </div>
			  <small class="text-danger js-merchant-receipt-error text-center d-block"><?=(string)($errors['merchant_receipt'] ?? '')?></small>
			  <input name="merchant_receipt" type="file" class="visually-hidden-file" id="merchant_receipt" accept="image/*">
			</div>

			<div class="col-md-12">
			  <label for="customer_receipt" class="form-label">Customer receipt</label>
			  <div class="mt-2" id="customerReceiptPreviewWrap">
					<div class="transaction-preview-card" id="customerReceiptPreviewCard" role="button" tabindex="0">
						<div class="transaction-preview-placeholder" id="customerReceiptPreviewPlaceholder">
							<i class="fa-solid fa-cloud-arrow-up" style="font-size:1.4rem;"></i>
							<div class="title">Upload customer receipt</div>
							<div class="hint">Tap to choose an image</div>
						</div>
						<img id="customerReceiptPreview" src="" alt="Customer receipt" style="display:none;">
						<div class="transaction-preview-overlay">
							<span class="transaction-preview-badge"><i class="fa-solid fa-camera"></i> Change</span>
						</div>
					</div>
			  	<small class="text-muted d-block mt-1 text-center">Tap the preview to change the image</small>
			  </div>
			  <small class="text-danger js-customer-receipt-error text-center d-block"><?=(string)($errors['customer_receipt'] ?? '')?></small>
			  <input name="customer_receipt" type="file" class="visually-hidden-file" id="customer_receipt" accept="image/*">
			</div>

		    <!-- Amount Input with Calculator Modal Trigger -->
		    <div class="col-mb-12 ">
		      <label for="amount" class="form-label">Amount Paid Out</label>
		      <label class="input-group" data-bs-toggle="modal" data-bs-target="#staticBackdrop"> 
		        <i class="fa-solid fa-calculator input-group-text text-warning"></i>
		          <input name="amount" value="<?=old_value('amount')?>" type="number" step="0.1" class="form-control" id="amount" placeholder="Enter amount" readonly>
		          <input type="hidden" name="denos" id="denos" value="<?=old_value('denos')?>">
		      </label>
		      <?php if(!empty($errors['amount'])):?>
		        <small class="text-danger"><?=$errors['amount']?></small>
		      <?php endif?>
				  <small class="text-danger js-amount-error"></small>
		    </div>

			<div class="col-md-12  transaction_id">
			  <label for="transaction_id" class="form-label">Transaction Id</label>
			          <input type="text" class="form-control" name="transaction_id" value="<?=old_value('transaction_id')?>" id="transaction_id">
			  	<?php if(!empty($errors['transaction_id'])): ?>
				  <small class="text-danger"><?=$errors['transaction_id']?></small>
				<?php endif ?>
				  <small class="text-danger js-transaction-id-error"></small>
			</div>

			<!-- Wallet functionality removed - cashdrawer is central accounting system -->

			<div class="col-mb-12">
			  <label for="transaction_status" class="form-label">Transaction Status</label>
			  <select class="form-select" name="transaction_status" id="transaction_status">
				  <option <?=old_select('transaction_status','Draft')?> value="Draft">Draft</option>
				  <option <?=old_select('transaction_status','Pending')?> value="Pending">Pending</option>
				  <option <?=old_select('transaction_status','Rejected')?> value="Rejected">Rejected</option>
				  <option <?=old_select('transaction_status','Approved')?> value="Approved" selected>Approved</option>
			  </select>
				
				<?php if(!empty($errors['transaction_status'])): ?>
				  <small class="text-danger"><?=$errors['transaction_status']?></small>
				<?php endif ?>
			</div>
	 
			<div class="d-flex justify-content-between mb-5" style="margin-top:10px;">
				<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
					<button type="button" class="btn btn-primary">
						<i class="fa-solid fa-chevron-left"></i> Back
					</button>
				</a>
				<button class="btn btn-danger save" type="submit">
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
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count" min="0" step="1"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Volume" readonly></td>
	            </tr>
	            <tr data-denomination="100">
	              <td class="px-5">GH&cent;100</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count" min="0" step="1"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Volume" readonly></td>
	            </tr>
	             <tr data-denomination="50">
	              <td class="px-5">GH&cent;50</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count" min="0" step="1"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
	            </tr>
	            <tr data-denomination="20">
	              <td class="px-5">GH&cent;20</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count" min="0" step="1"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
	            </tr>
	            <tr data-denomination="10">
	              <td class="px-5">GH&cent;10</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count" min="0" step="1"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
	            </tr>
	            <tr data-denomination="5">
	              <td class="px-5">GH&cent;5</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count" min="0" step="1"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
	            </tr>
	            <tr data-denomination="2">
	              <td class="px-5">GH&cent;2</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count" min="0" step="1"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
	            </tr>
	            <tr data-denomination="1">
	              <td class="px-5">GH&cent;1</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count" min="0" step="1"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
	            </tr>
	            <tr>
	              <td colspan="3">COINS</td>
	            </tr>
	            <tr data-denomination="2">
	              <td class="px-5">GH&cent;2</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count" min="0" step="1"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
	            </tr>

	            <tr data-denomination="1">
	              <td class="px-5">GH&cent;1</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count" min="0" step="1"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
	            </tr>

	            <tr data-denomination="0.5">
	              <td class="px-5">50p</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count" min="0" step="1"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
	            </tr>

	            <tr data-denomination="0.2">
	              <td class="px-5">20p</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count" min="0" step="1"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
	            </tr>

	            <tr data-denomination="0.1">
	              <td class="px-5">10p</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count" min="0" step="1"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
	            </tr>
	          </tbody>
	          <tfoot>
	            <tr>
	              <th class="px-5 bg-secondary text-warning">TOTAL</th>
	              <th class="bg-secondary text-warning"><input type="text" class="form-control p-1" id="total-count" style="border:none; " placeholder="Total count" readonly></th>
	              <th class="bg-secondary text-warning"><input type="text" class="form-control p-1" id="total-volume" style="border:none; " placeholder="Total volume" readonly></th>
	            </tr>
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

<?php else: ?>
	<div class="alert alert-danger text-center">
		Access denied. You don’t have permission for this action.
	</div>
<?php endif ?>

<script>
	document.addEventListener("DOMContentLoaded", function () {
		const countInputs = document.querySelectorAll(".count-input");
		const totalCountField = document.getElementById("total-count");
		const totalVolumeField = document.getElementById("total-volume");
	  const denosField = document.querySelector("#denos");
	  const submitBtn = document.querySelector('.submit');
	  const restoreBtn = document.querySelector('.restore');
	  const resetBtn = document.querySelector('.reset');
	  const amountInput = document.getElementById('amount');
	  const chargeInput = document.getElementById('charge');
	  const staticBackdropModal = new bootstrap.Modal(document.getElementById('staticBackdrop'));
	  const transactionId = document.getElementById('transaction_id');
	  const transactionIdError = document.querySelector('.js-transaction-id-error');
	  const amountError = document.querySelector(".js-amount-error");
	  const saveBtn = document.querySelector('.save');

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

		countInputs.forEach(input => {
			input.addEventListener("input", updateVolumesAndTotals);
		});

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
	      let count = parseFloat(input.value) || 0;
	      
	      // Enforce integer values only - round down to nearest integer
	      count = Math.floor(count);
	      input.value = count;
	      
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
	    // Validate denomination availability in cashdrawer
	    const denosData = JSON.parse(denosField.value || '[]');
	    if (denosData.length === 0) {
	      amountError.textContent = "Please select denominations";
	      return;
	    }

	    const cashdrawerDenosStr = document.getElementById('cashdrawerDenos').value;
	    let cashdrawerDenos = [];
	    try {
	      // Unescape HTML entities before parsing
	      const decodedStr = new DOMParser().parseFromString('<!doctype html><body>' + cashdrawerDenosStr, 'text/html').body.textContent;
	      cashdrawerDenos = JSON.parse(decodedStr || '[]');
	    } catch (e) {
	      console.error('Failed to parse cashdrawer denominations:', e);
	      console.log('Raw value:', cashdrawerDenosStr);
	      cashdrawerDenos = [];
	    }
	    
	    // Create a map of cashdrawer denominations by denomination value
	    const cashdrawerMap = {};
	    if (Array.isArray(cashdrawerDenos)) {
	      cashdrawerDenos.forEach(deno => {
	        const key = deno.denomination;
	        cashdrawerMap[key] = (cashdrawerMap[key] || 0) + (deno.count || 0);
	      });
	    }

	    // Check if selected cash denominations are available (cash going OUT)
	    let insufficientMsg = '';
	    denosData.forEach(selected => {
	      const available = cashdrawerMap[selected.denomination] || 0;
	      if (selected.count > available) {
	        insufficientMsg += `GHS ${selected.denomination}: Need ${selected.count}, Available ${available}\n`;
	      }
	    });

	    if (insufficientMsg) {
	      amountError.textContent = "Insufficient cash denominations in cashdrawer:\n" + insufficientMsg;
	      return;
	    }

	    amountInput.value = totalVolumeField.value;
	 		updateVolumesAndTotals();
	 		checkAmountPaid();
	    staticBackdropModal.hide();
	  });
	    

	  restoreBtn.addEventListener('click', function() {
	  	// Restore denominations
			restoreDenominations();
	  });

	  resetBtn.addEventListener('click', function() {
	  	// Reset denominations
			resetDenominations();
	  });

		// Dynamic Field Display Based on Transaction Type and Category
		const balanceInput = document.getElementById("balance");
		const amountPayableInput = document.getElementById("amount_payable");
		const amountPayableRoundedInput = document.getElementById("amount_payableRounded");
		const transactionType = document.getElementById('transaction_type');
		const transactionIdInput = document.getElementById("transaction_id");
		const transferType = document.getElementById('transfer_type');
		const receivingSelect = document.getElementById('receiving');
		const receivingSimInput = document.getElementById('receiving_sim');

		transactionType.addEventListener("change", showFields);
		transferType.addEventListener("change", showFields);

		// Function to show/hide fields based on transaction type
		function showFields() {
			const transactionTypeValue = transactionType.value;
			const transferTypeValue = transferType.value;
			const currentBalance = document.querySelector('.balance');
			const receiving = document.querySelector('.receiving');
			const receivingSim = document.querySelector('.receiving_sim');

			// Hide all fields initially
			receiving.classList.add('d-none');
			receivingSim.classList.add('d-none');

			// Show fields if transaction type is "Withdrawal"
			if (transactionTypeValue === 'Withdrawal') {
				receiving.classList.remove('d-none');
				receivingSim.classList.remove('d-none');
			}

			// Show fields if transaction type is "Withdrawal"
			if (transferTypeValue === 'All' || transferTypeValue === 'Full') {
				currentBalance.classList.remove('d-none');
			}else{
				currentBalance.classList.add('d-none');
			}
		}

		// Automatically show fields if a transaction type is pre-selected on page load
		showFields();
		updateReceivingSim();

		// Update receiving SIM input based on selection
		function updateReceivingSim() {
		    if (receivingSelect.value === 'Company sim') {
		        // Default sim value from admin settings
		        const companySim = <?= !empty($settings->company_sim) ? $settings->company_sim : 'update sim from settings' ?>;
		        receivingSimInput.value = companySim;
		        receivingSimInput.readOnly = true;
		    } else {
		        receivingSimInput.value = '';
		        receivingSimInput.readOnly = false;
		    }
		}

		transferType.addEventListener("change", function() {
			amountPayableInput.value = "";
			amountPayableRoundedInput.value = "";
			balanceInput.value = "";
			chargeInput.value = "";
			amountError.textContent = "";
			saveBtn.classList.remove("disabled");
			amountPayableRoundedInput.readOnly = (transferType.value === "All" || transferType.value === "Full");
			calculateCharge();
			checkAmountPaid();

	 		//calculateCharge();
		});

	  function calculateCharge() {
		  const charge = <?=$settings->ezwich_charges ?? 0?>;
		  const capValue = 10;
		  const type = (transferType.value || "").trim();

		  const toNum = (val) => {
			  const n = parseFloat(val);
			  return Number.isFinite(n) ? n : 0;
		  };

		  if (type === "") {
			  chargeInput.value = "";
			  amountPayableInput.value = "";
			  return;
		  }

		  if (type === "All" || type === "Full") {
			  const balance = toNum(balanceInput.value);
			  if (!balance) {
				  chargeInput.value = "";
				  amountPayableInput.value = "";
				  amountPayableRoundedInput.value = "";
				  return;
			  }
			  const computedCharge = Math.min(balance * charge, capValue);
			  chargeInput.value = computedCharge.toFixed(2);
			  const amountPayable = balance - computedCharge;
			  amountPayableInput.value = amountPayable.toFixed(2);
			  amountPayableRoundedInput.value = amountPayable.toFixed(0);
			  return;
		  }

		  if (type === "Part") {
			  const requestedRaw = toNum(amountPayableRoundedInput.value);
			  const requested = requestedRaw ? Math.round(requestedRaw) : 0;
			  if (!requested) {
				  chargeInput.value = "";
				  amountPayableInput.value = "";
				  return;
			  }
			  amountPayableRoundedInput.value = requested.toString();
			  amountPayableInput.value = requested.toFixed(2);
			  const computedCharge = Math.min(requested * charge, capValue);
			  chargeInput.value = computedCharge.toFixed(2);
		  }
	    }

	    function checkAmountPaid() {
	    	const toNum = (val) => {
	    		const n = parseFloat(val);
	    		return Number.isFinite(n) ? n : 0;
	    	};
	    	const amountPayable = toNum(amountPayableInput.value || amountPayableRoundedInput.value);
	    	const amountPayableRounded = amountPayable ? Math.round(amountPayable).toString() : "";
	    	const amountPaid = toNum(amountInput.value);
	    	const amountPaidRounded = amountPaid ? Math.round(amountPaid).toString() : "";
	    	
	    	if (!amountPayableRounded || !amountPaidRounded) {
	    		amountError.textContent = "";
	    		saveBtn.classList.remove("disabled");
	    		return;
	    	}
	    	
	    	if (amountPaidRounded !== amountPayableRounded) {
	    		amountError.textContent = "Amount paid out must be equal to amount to withdraw";
	    		saveBtn.classList.add("disabled");
	    	}else{
	    		amountError.textContent = "";
	    		saveBtn.classList.remove("disabled");
	    	}
	    }

	    // Validate transaction ID - must be 11 digits
	    transactionIdInput.addEventListener("blur", function() {
	    	const value = this.value.trim();
	    	if (value && !/^\d{11}$/.test(value)) {
	    		transactionIdError.textContent = "Transaction ID must be exactly 11 digits";
	    	} else {
	    		transactionIdError.textContent = "";
	    	}
	    });

	    // Call checkAmountPaid when amount fields change
	    amountInput.addEventListener("change", checkAmountPaid);
	    amountPayableInput.addEventListener("change", checkAmountPaid);
	    balanceInput.addEventListener("change", checkAmountPaid);
	    transferType.addEventListener("change", checkAmountPaid);
		balanceInput.addEventListener("input", function(){
			if (transferType.value === "All" || transferType.value === "Full") {
				calculateCharge();
				checkAmountPaid();
			}
		});
		amountPayableRoundedInput.addEventListener("input", function(){
			if (transferType.value === "Part") {
				calculateCharge();
				checkAmountPaid();
			}
		});

		(function(){
			const formEl = document.querySelector('form');
			if(!formEl) return;

			const wire = (opts) => {
				const inputEl = document.getElementById(opts.inputId);
				const cardEl = document.getElementById(opts.cardId);
				const previewEl = document.getElementById(opts.previewId);
				const placeholderEl = document.getElementById(opts.placeholderId);
				const errorEl = document.querySelector(opts.errorSelector);
				if(!inputEl || !cardEl || !previewEl || !placeholderEl) return;

				if (window.AppImageCropper && typeof window.AppImageCropper.bindPreviewCard === 'function') {
					window.AppImageCropper.bindPreviewCard({
						formEl: formEl,
						inputEl: inputEl,
						cardEl: cardEl,
						previewEl: previewEl,
						placeholderEl: placeholderEl,
						errorEl: errorEl,
						modalTitle: opts.modalTitle || 'Crop image',
						errorTitle: opts.errorTitle || 'Invalid image',
						maxBytes: window.AppImageCropper.MAX_BYTES,
						required: true,
						requiredMessage: opts.requiredMessage || 'Please upload an image.',
					});
					return;
				}

				const setPreviewState = (hasImage) => {
					placeholderEl.style.display = hasImage ? 'none' : 'flex';
					previewEl.style.display = hasImage ? 'block' : 'none';
				};

				cardEl.addEventListener('click', function(){
					inputEl.click();
				});
				cardEl.addEventListener('keydown', function(e){
					if (e.key === 'Enter' || e.key === ' ') {
						e.preventDefault();
						inputEl.click();
					}
				});

				inputEl.addEventListener('change', function(){
					const file = (this.files && this.files[0]) ? this.files[0] : null;
					if(!file){
						previewEl.src = '';
						setPreviewState(false);
						return;
					}
					if (!file.type || !file.type.startsWith('image/')) {
						this.value = '';
						previewEl.src = '';
						setPreviewState(false);
						if(errorEl) errorEl.textContent = 'Invalid file type. Please select an image.';
						return;
					}
					const reader = new FileReader();
					reader.onload = function(ev){
						previewEl.src = ev.target.result;
						setPreviewState(true);
						if(errorEl) errorEl.textContent = '';
					};
					reader.readAsDataURL(file);
				});
			};

			wire({
				inputId: 'merchant_receipt',
				cardId: 'merchantReceiptPreviewCard',
				previewId: 'merchantReceiptPreview',
				placeholderId: 'merchantReceiptPreviewPlaceholder',
				errorSelector: '.js-merchant-receipt-error',
				modalTitle: 'Crop merchant receipt',
				errorTitle: 'Invalid merchant receipt',
				requiredMessage: 'Merchant receipt is required',
			});
			wire({
				inputId: 'customer_receipt',
				cardId: 'customerReceiptPreviewCard',
				previewId: 'customerReceiptPreview',
				placeholderId: 'customerReceiptPreviewPlaceholder',
				errorSelector: '.js-customer-receipt-error',
				modalTitle: 'Crop customer receipt',
				errorTitle: 'Invalid customer receipt',
				requiredMessage: 'Customer receipt is required',
			});

			formEl.addEventListener('submit', function(e){
				const merchantOk = (document.getElementById('merchant_receipt').files || []).length > 0;
				const customerOk = (document.getElementById('customer_receipt').files || []).length > 0;
				if(!merchantOk){
					e.preventDefault();
					const err = document.querySelector('.js-merchant-receipt-error');
					if(err) err.textContent = 'Merchant receipt is required';
				}
				if(!customerOk){
					e.preventDefault();
					const err = document.querySelector('.js-customer-receipt-error');
					if(err) err.textContent = 'Customer receipt is required';
				}
			});
		})();

	});
</script>


