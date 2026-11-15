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
	</style>

    <form method="post" enctype="multipart/form-data">		
		<div class="row col-9 col-lg-5 mx-auto shadow p-2 mb-5 rounded">
			
			<?=csrf()?>

			<h4 class="text-center">
				<span><img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:60px;max-height: 60px;object-fit: cover;"></span>
				Funds Transfer
			</h4>
	 
			<div class="mb-1">
				<label for="transaction_type" class="form-label">Transaction type</label>
				<select class="form-select transaction_type" id="transaction_type" name="transaction_type" onchange="updateCategoryOptions(); updateSourceOptions();">
				  <option value="">---Select---</option>
				  <option selected <?=old_select('transaction_type','Funds transfer')?> value="Funds transfer">Funds transfer</option>
				</select>

				<?php if(!empty($errors['transaction_type'])):?>
				  <small class="text-danger"><?=$errors['transaction_type']?></small>
				<?php endif?>
			</div>
	 
			<div class="mb-1">
				<label for="network" class="form-label">Service provider</label>
				<select class="form-select" name="network" id="network" onchange="showFields()">			  
				  <!-- Options will be populated dynamically based on transaction type -->
				</select>
                <div id="networkError" class="text-error text-danger d-none">Service provider is required</div>

				<?php if(!empty($errors['network'])):?>
				  <small class="text-danger"><?=$errors['network']?></small>
				<?php endif?>
			</div>
	 
			<div class="mb-1 category">
				<label for="category" class="form-label">Category</label>
				<select class="form-select" name="category" id="category" onchange="showFields()">
				  <!-- Options will be populated dynamically based on transaction type -->
				</select>

				<?php if(!empty($errors['category'])):?>
				  <small class="text-danger"><?=$errors['category']?></small>
				<?php endif?>
			</div>

			<div class="mb-1">
			  <label for="phone" class="form-label">Phone</label>
			  <input name="phone" value="<?=old_value('phone')?>" type="text" class="form-control" id="phone" placeholder="Enter phone number">
          		<div id="phoneError" class="text-error text-danger d-none">Phone number is required</div>
				
				<?php if(!empty($errors['phone'])):?>
				  <small class="text-danger"><?=$errors['phone']?></small>
				<?php endif?>
			</div>

			<div class="mb-1 cash_source d-none">
			  <label for="cash_source" class="form-label">Source of funds</label>
			  <input name="cash_source" value="<?=old_value('cash_source')?>" type="text" class="form-control" id="cash_source" placeholder="Enter source of funds">
				
				<?php if(!empty($errors['cash_source'])):?>
				  <small class="text-danger"><?=$errors['cash_source']?></small>
				<?php endif?>
			</div>

			<div class="mb-1 balance_before">
			  <label for="balance_before" class="form-label">Balance before</label>
			  <input name="balance_before" value="<?=old_value('balance_before')?>" type="number" step="0.01" class="form-control" id="balance_before" placeholder="Enter balance_before">
                <div id="balanceBeforeError" class="text-error text-danger d-none">Balance before is required</div>
				
				<?php if(!empty($errors['balance_before'])):?>
				  <small class="text-danger"><?=$errors['balance_before']?></small>
				<?php endif?>
			</div>

			<div class="mb-1 amount">
			  <label for="amount" class="form-label">Load</label>
			  <input name="amount" value="<?=old_value('amount')?>" type="number" step="0.01" class="form-control" id="amount" placeholder="Enter load amount">
                <div id="loadError" class="text-error text-danger d-none">Load is required</div>
				
				<?php if(!empty($errors['amount'])):?>
				  <small class="text-danger"><?=$errors['amount']?></small>
				<?php endif?>
			</div>

		    <!-- Amount Input with Calculator Modal Trigger -->
		    <div class="col-md-12">
		      <label for="amount_paid" class="form-label">Amount paid</label>
			   <label class="input-group" data-bs-toggle="modal" data-bs-target="#staticBackdrop"> 
		        <i class="fa-solid fa-calculator input-group-text text-warning"></i>
		          <input name="amount_paid" value="<?=old_value('amount_paid')?>" type="number" step="0.01" class="form-control" id="amount_paid" placeholder="Enter amount_paid" readonly>
			      <input type="hidden" id="denos" name="denos" value="<?=htmlspecialchars((string)old_value('denos'), ENT_QUOTES, 'UTF-8')?>" />
		      </label>
                <div id="paidError"       class="text-error text-danger d-none">Amount paid is required</div>
                <div id="paidMismatchError" class="text-error text-danger">Amount paid must equal load</div>
		      <?php if(!empty($errors['amount_paid'])):?>
		        <small class="text-danger"><?=$errors['amount_paid']?></small>
		      <?php endif?>
				<?php if(!empty($errors['wallet_out_id'])):?>
				  <small class="text-danger"><?=$errors['wallet_out_id']?></small>
				<?php endif?>
		    </div>

			<div class="mb-1 balance">
			  <label for="balance" class="form-label">New Balance</label>
			  <input name="balance" value="<?=old_value('balance')?>" type="text" class="form-control" id="balance" placeholder="GH&cent;" readonly>
				
				<?php if(!empty($errors['balance'])):?>
				  <small class="text-danger"><?=$errors['balance']?></small>
				<?php endif?>
				  <small class="text-danger" id="balance-error"></small>
			</div>

			<div class="mb-1 transaction_status">
			  <label for="transaction_status" class="form-label">Status</label>
			  <select class="form-select transaction_status" name="transaction_status">
				  <option value="">---Select---</option>
				  <option <?=old_select('transaction_status','Draft')?> value="Draft">Draft</option>
				  <option <?=old_select('transaction_status','Pending')?> value="Pending">Pending</option>
				 <?php if(user_can('approve_transactions')):?>
				  <option <?=old_select('transaction_status','Rejected')?> value="Rejected">Rejected</option>
				  <option <?=old_select('transaction_status','Approved')?> value="Approved">Approved</option>
				<?php endif;?>
			  </select>
				
				<?php if(!empty($errors['transaction_status'])):?>
				  <small class="text-danger"><?=$errors['transaction_status']?></small>
				<?php endif?>
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

    <!-- Calculator Modal -->
    <div class="modal fade" id="staticBackdrop" data-bs-backdrop="static" data-bs-keyboard="false"
         tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">

          <div class="modal-header">
            <h5 class="modal-title" id="staticBackdropLabel">Currency Notes</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>

          <div class="modal-body table-responsive">
            <table class="table table-hover table-sm">
              <thead><tr><th>NOTES</th><th>COUNT</th><th>VOLUME</th></tr></thead>
              <tbody>
                <?php foreach([200,100,50,20,10,5,2,1] as $den): ?>
                <tr data-denomination="<?=$den?>">
                  <td>GH₵<?=$den?></td>
                  <td><input class="form-control p-1 count-input"></td>
                  <td><input class="form-control p-1 volume-input" readonly></td>
                </tr>
                <?php endforeach;?>
                <tr><td colspan="3">COINS</td></tr>
                <?php foreach([0.5,0.2,0.1] as $den): ?>
                <tr data-denomination="<?=$den?>">
                  <td><?= $den<1 ? ($den*100).'p' : 'GH₵'.$den ?></td>
                  <td><input class="form-control p-1 count-input"></td>
                  <td><input class="form-control p-1 volume-input" readonly></td>
                </tr>
                <?php endforeach;?>
              </tbody>
              <tfoot>
                <tr class="bg-secondary text-warning">
                  <th>Total</th>
                  <th><input id="total-count" class="form-control p-1" readonly></th>
                  <th><input id="total-volume" class="form-control p-1" readonly></th>
                </tr>
				<tr><th colspan="3" class="text-center"><p class="text-info mb-1" id="required-currency-total">Required total: 0.00</p></th></tr>
              </tfoot>
            </table>
          </div>

          <div class="modal-footer">
            <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button class="btn btn-danger reset">Reset</button>
            <button class="btn btn-warning restore">Restore</button>
			<button class="btn btn-primary submit">Confirm</button>
          </div>

          <div id="modalMismatch" class="text-error text-danger d-none mb-2"></div>
        </div>
      </div>
    </div>

<?php else:?>
	<div class="alert alert-danger text-center">
		Access denied. You dont have permission for this action
	</div>
<?php endif?>


<script>
	const oldNetworkValue = "<?=htmlspecialchars((string)old_value('network'), ENT_QUOTES, 'UTF-8')?>";
	const oldCategoryValue = "<?=htmlspecialchars((string)old_value('category'), ENT_QUOTES, 'UTF-8')?>";
	const categoryOptions = {
		"Deposit": [
			{ value: "", text: "---Select---" },
			{ value: "self", text: "self" },
			{ value: "3rd party", text: "3rd party" }
		]
	};

	const sourceOptions = {
		"Funds transfer": [
			{ value: "", text: "---Select---" },
			{ value: "Fidelity", text: "Fidelity" },
		],
	};

	const transactionType = document.getElementById('transaction_type'); // Corrected ID selection
	const categorySelect = document.getElementById('category');
	const networkSelect = document.getElementById('network');
	const loadInput = document.getElementById('amount');
	const paidInput = document.getElementById('amount_paid');
	const saveBtn = document.getElementById('save');
	const categoryDiv = document.querySelector('.category');
    const phoneInput    = document.getElementById('phone');
    const balanceInput    = document.getElementById('balance');
    const balanceBeforeInput    = document.getElementById('balance_before');
    const modalMismatch    = document.getElementById('modalMismatch');

    // error placeholders
    const mismatchError    = document.getElementById('paidMismatchError');
    const loadError       = document.getElementById('loadError');
    const paidError       = document.getElementById('paidError');
    const balanceBeforeError    = document.getElementById('balanceBeforeError');
    const balanceError    = document.getElementById('balanceError');
    const networkError    = document.getElementById('networkError');
    const phoneError    = document.getElementById('phoneError');

      const countInputs = document.querySelectorAll(".count-input");
	  const totalCountField = document.getElementById("total-count");
	  const totalVolumeField = document.getElementById("total-volume");
	  const denosField = document.querySelector("#denos");
	  const confirmBtn = document.querySelector('.submit');
	  const restoreBtn = document.querySelector('.restore');
	  const resetBtn = document.querySelector('.reset');
	  // Load denomination data from localStorage
	  const savedDenos = localStorage.getItem("denosData");

    let attempted = false;

	  // --- Utility: toggle d-none by truthiness ---
	  function toggle(el, show) {
	    el.classList.toggle('d-none', !show);
	  }

	function updateCategoryOptions() {
		const transactionTypeValue = transactionType.value;
		categorySelect.innerHTML = ""; // Clear current options

		// Populate options based on selected transaction type
		if (categoryOptions[transactionTypeValue]) {
			categorySelect.classList.remove('d-none');
			categoryDiv.classList.remove('d-none');
			categoryOptions[transactionTypeValue].forEach(option => {
				const opt = document.createElement('option');
				opt.value = option.value;
				opt.textContent = option.text;
				categorySelect.appendChild(opt);
			});
			if (oldCategoryValue) {
				categorySelect.value = oldCategoryValue;
			}
		} else {
			categorySelect.classList.add('d-none'); // Hide if no options are available
			categoryDiv.classList.add('d-none'); // Hide if no options are available
		}
		showFields();
	}

	function updateSourceOptions() {
		const transactionTypeValue = transactionType.value;
		networkSelect.innerHTML = ""; // Clear current options

		// Populate options based on selected transaction type
		if (sourceOptions[transactionTypeValue]) {
			sourceOptions[transactionTypeValue].forEach(option => {
				const opt = document.createElement('option');
				opt.value = option.value;
				opt.textContent = option.text;
				networkSelect.appendChild(opt);
			});
			if (oldNetworkValue) {
				networkSelect.value = oldNetworkValue;
			}
		} 
		showFields();
	}

	function showFields() {
		const transactionTypeValue = transactionType.value;
		const categoryValue = categorySelect.value;
		const balance = document.querySelector('.balance');
		const balance_before = document.querySelector('.balance_before');
		const transaction_status = document.querySelector('.transaction_status');
		const cash_source = document.querySelector('.cash_source');
		cash_source.classList.add('d-none');

		// Show fields based on selected category for Withdrawal
		if (transactionTypeValue === 'Funds transfer') {
			cash_source.classList.remove('d-none');
		}
	}

	function calculateBalance() {
		const transactionType = document.getElementById('transaction_type').value;
		const balanceBefore = parseFloat(document.getElementById('balance_before').value) || 0;
		const amount = parseFloat(document.getElementById('amount').value) || 0;
		const balanceField = document.getElementById('balance');
		const balanceError = document.getElementById('balance-error');

		let newBalance = balanceBefore;
		balanceError.innerText = '';
		// Adjust balance based on transaction type
		switch (transactionType) {
			case 'Funds transfer':
				newBalance = balanceBefore - amount;
				break;
			default:
				// For other transaction types, keep balance unchanged or handle as needed
				newBalance = balanceBefore;
				break;
		}

		saveBtn.disabled = false;

		balanceField.value = newBalance.toFixed(2);
		if (amount > balanceBefore) {
			balanceError.innerText = "Insufficient balance";
			saveBtn.disabled = true;
			return;
		}
	}

	// Initialize on page load
	document.addEventListener("DOMContentLoaded", function() {
	  const staticBackdropModal = new bootstrap.Modal(document.getElementById('staticBackdrop'));
	  const modalEl = document.getElementById('staticBackdrop');

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

	  // --- Form validation & mismatch logic ---
	  function validateForm() {
	    //if (!attempted) return saveBtn.disabled = true;
	    if (!attempted) {
	        // never show errors until user tries
	        saveBtn.disabled = true;
	        mismatchError.classList.add('d-none');
	        return;
	    }
	    const nv = !!networkSelect.value;
	    const ph = !!phoneInput.value;
	    const bb = !!balanceBeforeInput.value;
	    const ld = !!loadInput.value;
	    const pd = !!paidInput.value;

	    // toggle(networkError, nv);
	    // toggle(phoneError,   ph);
	    // toggle(balanceBeforeError, bb);
	    // toggle(loadError,    ld);
	    // toggle(paidError,    pd);



	    // toggle required-field errors
	    loadError      .classList.toggle('d-none', !!loadInput.value);
	    paidError      .classList.toggle('d-none', !!paidInput.value);
	    balanceBeforeError   .classList.toggle('d-none', !!balanceBeforeInput.value);
	    networkError   .classList.toggle('d-none', !!networkSelect.value);
	    phoneError   .classList.toggle('d-none', !!phoneInput.value);
	    mismatchError   .classList.toggle('d-none', !!phoneInput.value);

	    const lv = +loadInput.value || 0;
	    const pv = +paidInput.value || 0;
	    const mismatch = pd && pv !== lv;
	    toggle(mismatchError, mismatch);

	    const allOK = nv && ph && bb && ld && pd && !mismatch;
	    saveBtn.disabled = !allOK;
	  }

	  // --- On modal Confirm click ---
	  confirmBtn.addEventListener('click', () => {
	    attempted = true;
	    updateVolumesAndTotals();
	    calculateBalance();

	    const lv = +loadInput.value || 0;
	    const tv = +totalVolumeField.value || 0;
	    const requiredTotal = document.getElementById('required-currency-total');
	    if (requiredTotal) requiredTotal.textContent = `Required total: ${lv.toFixed(2)}`;
	    const diff = (tv - lv).toFixed(2);

	    if (diff != 0) {
	      // Show errors, keep modal open
	      modalMismatch.textContent = `Amount paid and Load Mismatch: ${diff>0?'+':'‒'}GH₵${Math.abs(diff)}`;
	      toggle(modalMismatch, true);
	      toggle(mismatchError, true);
	    } else {
	      // All good: set paid, hide errors & modal
	      paidInput.value = tv.toFixed(2);
	      toggle(modalMismatch,    false);
	      toggle(mismatchError,    false);
	      staticBackdropModal.hide();
	    }
	    validateForm();
	  });

	  // --- Wire up inputs & modal buttons ---
	  document.querySelectorAll('.reset').forEach(b => b.onclick = () => {
	    countInputs.forEach(i=> i.value = i.closest('tr').querySelector('.volume-input').value = '');
	    updateVolumesAndTotals();
	  });
	  document.querySelectorAll('.restore').forEach(b => b.onclick = () => {
	    const saved = localStorage.getItem('denosData');
	    if (saved) {
	      JSON.parse(saved).forEach((d,i) => {
	        countInputs[i].value = d.count;
	        countInputs[i].closest('tr').querySelector('.volume-input').value = d.volume.toFixed(2);
	      });
	      updateVolumesAndTotals();
	    }
	  });

	  countInputs.forEach(i => i.addEventListener('input', updateVolumesAndTotals));
	  [loadInput, balanceBeforeInput].forEach(i => i.addEventListener('input', () => {
	    calculateBalance(); validateForm();
	  }));
	  [paidInput, phoneInput, networkSelect].forEach(i => 
	    i.addEventListener('change', validateForm)
	  );

    // Watch for changes on load, paid (set by modal “Confirm”) and balance
    loadInput.addEventListener('input', validateForm);
    paidInput.addEventListener('change', validateForm);  // after modal confirm
    phoneInput.addEventListener('input', validateForm);  // after modal confirm
    balanceBeforeInput.addEventListener('input', validateForm);
    networkSelect.addEventListener('change', validateForm);

	// on Save click:
	saveBtn.addEventListener('click', function(e){
	    attempted = true;
	    validateForm();
	    if (saveBtn.disabled) {
	        e.preventDefault(); // stop actual submit if invalid
	    }
	});

    	validateForm();
		updateCategoryOptions();
		restoreDenominations();
		updateSourceOptions();
	});

	// Event listeners for real-time updates
	transactionType.addEventListener("change", updateCategoryOptions);
	transactionType.addEventListener("change", updateSourceOptions);
	categorySelect.addEventListener("change", showFields);
	transactionType.addEventListener("change", calculateBalance);
	document.getElementById('amount').addEventListener('input', calculateBalance);
	document.getElementById('balance_before').addEventListener('input', calculateBalance);
</script>

