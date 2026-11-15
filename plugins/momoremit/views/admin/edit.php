<?php if(user_can('edit_momoremit')):?>
	<style>
		form {
			margin-tohttp://localhost/www/tormorgroup/admin/usersp: 40px !important;
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

	<?php if(!empty($row)): ?>

		<form method="post" enctype="multipart/form-data">
			<div class="row g-3 col-md-10 col-lg-8 mx-auto shadow p-4 rounded">
		
				<?=csrf()?>

				<h4 class="text-center">
					<span><img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:60px;max-height: 60px;object-fit: cover;"></span>
					Edit Transaction
				</h4>

				<?php $currentStatus = momoremits_normalize_status($row->transaction_status ?? null, 'Approved'); ?>
				<?php if(strcasecmp($currentStatus, 'Approved') === 0): ?>
				<div class="col-12">
					<div class="alert alert-warning mb-0">
						This transaction is already approved. To keep the accounting trail safe, its transaction details cannot be changed.
					</div>
				</div>
				<?php endif; ?>

				<div class="mb-3 col-md-12 col-lg-6">
					<label for="transaction_status" class="form-label">Transaction status</label>
					<select class="form-select" name="transaction_status" id="transaction_status">
					  <option <?=old_select('transaction_status','Approved', $currentStatus)?> value="Approved">Approved</option>
					  <option <?=old_select('transaction_status','Pending', $currentStatus)?> value="Pending">Pending</option>
					</select>

					<?php if(!empty($errors['transaction_status'])):?>
					  <small class="text-danger"><?=$errors['transaction_status']?></small>
					<?php endif?>
				</div>

		 
				<div class="mb-3 col-md-12 col-lg-6">
					<label for="network" class="form-label">Select Network</label>
					<select class="form-select" name="network">
					  <option value="">---Select---</option>
					  <option <?=old_select('network','mtn',$row->network ?? '')?> value="mtn">mtn</option>
					  <option <?=old_select('network','telecel',$row->network ?? '')?> value="telecel">telecel</option>
					  <option <?=old_select('network','at',$row->network ?? '')?> value="at">at</option>
					  <option <?=old_select('network','zeepay',$row->network ?? '')?> value="zeepay">zeepay</option>
					  <option <?=old_select('network','Ghanapay',$row->network ?? '')?> value="Ghanapay">Ghanapay</option>
					  <option <?=old_select('network','G-money', $row->network ?? '')?> value="G-money">G-money</option>
					</select>

					<?php if(!empty($errors['network'])):?>
					  <small class="text-danger"><?=$errors['network']?></small>
					<?php endif?>
				</div>
		 
				<div class="mb-3 col-md-12 col-lg-6">
					<label for="transaction_type" class="form-label">Transaction type</label>
					<select class="form-select transaction_type" name="transaction_type" id="transaction_type" onchange="updateCategoryOptions()">
					  <option value="">---Select---</option>
					  <option <?=old_select('transaction_type','Sending', $row->transaction_type ?? '')?> value="Sending">Sending</option>
					  <option <?=old_select('transaction_type','Cash in', $row->transaction_type ?? '')?> value="Cash in">Cash in</option>
					  <option <?=old_select('transaction_type','Deposit', $row->transaction_type ?? '')?> value="Deposit">Deposit</option>
					  <option <?=old_select('transaction_type','Withdrawal', $row->transaction_type ?? '')?> value="Withdrawal">Withdrawal</option>
					  <option <?=old_select('transaction_type','CIT', $row->transaction_type ?? '')?> value="CIT">CIT</option>
					</select>

					<?php if(!empty($errors['transaction_type'])):?>
					  <small class="text-danger"><?=$errors['transaction_type']?></small>
					<?php endif?>
				</div>
 
				<div class="mb-3 col-md-12 col-lg-6 category d-none">
					<label for="category" class="form-label">Category</label>
					<select class="form-select" name="category" id="category" onchange="showFields()">
					  <!-- Options will be populated dynamically based on transaction type -->
					</select>

					<?php if(!empty($errors['category'])):?>
					  <small class="text-danger"><?=$errors['category']?></small>
					<?php endif?>
				</div>				

				<div class="mb-3 col-md-12 col-lg-6">
				  <label for="phone" class="form-label">Phone</label>
				  <input name="phone" value="<?=old_value('phone', $row->phone ?? '')?>" type="text" class="form-control" id="phone" placeholder="Enter phone number">
					
					<?php if(!empty($errors['phone'])):?>
					  <small class="text-danger"><?=$errors['phone']?></small>
					<?php endif?>
				</div>

				<div class="mb-3 col-md-12 col-lg-6 token d-none">
				  <label for="token" class="form-label">Token number</label>
				  <input name="token" value="<?=old_value('token', $row->token ?? '')?>" type="text" class="form-control" id="token" placeholder="Enter token number">
					
					<?php if(!empty($errors['token'])):?>
					  <small class="text-danger"><?=$errors['token']?></small>
					<?php endif?>
				</div>

			    <!-- Amount Input with Calculator Modal Trigger -->
			    <div class="mb-3 col-md-12 col-lg-6">
			      <label for="amount" class="form-label">Amount</label>
			      <label class="input-group" data-bs-toggle="modal" data-bs-target="#staticBackdrop"> 
			        <i class="fa-solid fa-calculator input-group-text text-warning"></i>
			          <input name="amount" value="<?=old_value('amount',$row->amount ?? '')?>" type="number" step="0.1" class="form-control" id="amount" placeholder="Enter amount" readonly>
					  <?php
						$db = new \Core\Database;
						$seedDenos = [];
						try {
							$seedDenos = $db->query("SELECT denomination, SUM(count) AS count FROM denos WHERE trans_id = :trans_id GROUP BY denomination ORDER BY denomination DESC", ['trans_id' => $row->id]) ?? [];
						} catch (\Throwable $e) {
							$seedDenos = [];
						}
						$seedCurrency = [];
						if (!empty($seedDenos)) {
							foreach ($seedDenos as $d) {
								$den = (float)($d->denomination ?? 0);
								$cnt = (float)($d->count ?? 0);
								if ($cnt > 0) {
									$seedCurrency[] = [
										'currency' => 'GHS',
										'row_index' => null,
										'denomination' => $den,
										'count' => $cnt,
										'volume' => $den * $cnt,
									];
								}
							}
						}
						$seedDenosJson = json_encode($seedCurrency);
					  ?>
					  <input type="hidden" id="denos" name="denos" value="<?=htmlspecialchars((string)old_value('denos', $seedDenosJson ?? '[]'), ENT_QUOTES, 'UTF-8')?>" />
			      </label>
			      <?php if(!empty($errors['amount'])):?>
			        <small class="text-danger"><?=$errors['amount']?></small>
			      <?php endif?>
			    </div>

				<div class="mb-3 col-md-12 col-lg-6">
				  <label for="charge" class="form-label">Charge </label>
				  <div class="input-group">
				  	<div id="currencySymbol" class="input-group-text">GH₵</div>
				  	<input name="charge" value="<?=old_value('charge', $row->charge ?? 0.00)?>" type="text" class="form-control" id="charge" placeholder="GH&cent;0.00">
				  </div>
				  <?php if(!empty($errors['id_type'])):?>
					  <small class="text-danger"><?=$errors['id_type']?></small>
					<?php endif?>
				</div>

				<div class="mb-3 col-md-12 col-lg-6 token d-none">
				  <label for="id_type" class="form-label">ID type</label>
				  <select class="form-select id_type" name="id_type">
					  <option value="">---Select---</option>
					  <option <?=old_select('id_type','Ghana card', $row->id_type ?? '')?> value="Ghana card">Ghana card</option>
					  <option <?=old_select('id_type','Voter ID', $row->id_type ?? '')?> value="Voter ID">Voter ID</option>
					  <option <?=old_select('id_type','NHIS', $row->id_type ?? '')?> value="NHIS">NHIS</option>
					  <option <?=old_select('id_type','Passport', $row->id_type ?? '')?> value="Passport">Passport</option>
					  <option <?=old_select('id_type','Driver license', $row->id_type ?? '')?> value="Driver license">Driver license</option>
				  </select>
					
					<?php if(!empty($errors['id_type'])):?>
					  <small class="text-danger"><?=$errors['id_type']?></small>
					<?php endif?>
				</div>

				<div class="mb-3 col-md-12 col-lg-6 token d-none">
				  <label for="id_number" class="form-label">ID number</label>
				  <input name="id_number" value="<?=old_value('id_number', $row->id_number ?? '')?>" type="text" class="form-control" id="id_number" placeholder="GHA-123456789-1" pattern="GHA-[0-9]{9}-[0-9]">
					
					<?php if(!empty($errors['id_number'])):?>
					  <small class="text-danger"><?=$errors['id_number']?></small>
					<?php endif?>
					<small class="text-muted d-block">Use format: GHA-123456789-1</small>
				</div>

				<div class="mb-3 col-md-12 col-lg-6 token d-none">
				  <label for="secret_code" class="form-label">Secret code</label>
				  <input name="secret_code" value="<?=old_value('secret_code', $row->secret_code ?? '')?>" type="text" class="form-control" id="secret_code" placeholder="Enter secret code here">
					
					<?php if(!empty($errors['secret_code'])):?>
					  <small class="text-danger"><?=$errors['secret_code']?></small>
					<?php endif?>
				</div>

				<div class="mb-3 col-md-12 col-lg-6 token receiver d-none">
				  <label for="receiver" class="form-label">Receiver's phone</label>
				  <input name="receiver" value="<?=old_value('receiver', $row->receiver ?? '')?>" type="text" class="form-control" id="receiver" placeholder="Enter receiver's phone number">
					
					<?php if(!empty($errors['receiver'])):?>
					  <small class="text-danger"><?=$errors['receiver']?></small>
					<?php endif?>
				</div>
		 
				<div class="d-flex justify-content-between" style="margin-top: 10px;">
					<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
						<button type="button" class="btn btn-outline-primary" style="border:none;">
							<i class="fa-solid fa-chevron-left"></i> Back
						</button>
					</a>
					<button class="btn btn-outline-danger" style="border:none;">
						<i class="fa-solid fa-save"></i> Save
					</button>
				</div>
			</div>
		</form>

	<!-- Calculator modal -->
	<!-- Modal -->
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
	              <th class="px-5">TOTAL</th>
	              <th><input type="text" class="form-control p-1" id="total-count" style="border:none; " placeholder="Total count" readonly></th>
	              <th><input type="text" class="form-control p-1" id="total-volume" style="border:none; " placeholder="Total volume" readonly></th>
	            </tr>
	          </tfoot>
	        </table>
	      </div>

	      <!-- Modal Footer -->
	      <div class="modal-footer">
	        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
	        <button type="button" class="btn btn-primary submit">Confirm</button>
	      </div>
	    </div>
	  </div>
	</div>
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

<?php
  $momoremitServerErrors = [];
  if (!empty($errors) && is_array($errors)) {
    foreach ($errors as $errorText) {
      $errorText = trim((string)$errorText);
      if ($errorText !== '') {
        $momoremitServerErrors[] = $errorText;
      }
    }
  }
  $momoremitServerErrors = array_values(array_unique($momoremitServerErrors));
  $momoremitServerErrorText = implode("\n", $momoremitServerErrors);
?>

<script>
  document.addEventListener("DOMContentLoaded", function () {
    // Element references for inputs, fields, and buttons
    const countInputs = document.querySelectorAll(".count-input");
    const totalCountField = document.getElementById("total-count");
    const totalVolumeField = document.getElementById("total-volume");
    const submitBtn = document.querySelector('.submit');
    const staticBackdropModal = new bootstrap.Modal(document.getElementById('staticBackdrop'));
    const amountInput = document.getElementById('amount');
	 const denosField = document.getElementById('denos');
    const chargeInput = document.getElementById('charge');
    const transactionType = document.getElementById('transaction_type'); 
    const categorySelect = document.getElementById('category');
    const categoryDiv = document.querySelector('.category');
    const tokenFields = document.querySelectorAll('.token');
    const receiver = document.querySelector('.receiver');
    const existingCategory = "<?= $row->category ?? '' ?>"; // PHP variable for existing category
    const statusSelect = document.getElementById('transaction_status');
    const originalStatus = "<?= strtoupper($currentStatus) ?>";
    const editForm = document.querySelector('form');
    const serverErrorMessage = <?=json_encode($momoremitServerErrorText, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)?>;

    // Event listener for updating volumes
    countInputs.forEach(input => {
      input.addEventListener("input", updateVolumesAndTotals);
    });

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
		if (!currency || !currency.length) return;
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

    function updateVolumesAndTotals() {
      let totalCount = 0;
      let totalVolume = 0;
	  const currency = [];

      countInputs.forEach((input, rowIndex) => {
        const row = input.closest("tr");
        const denomination = parseFloat(row.getAttribute("data-denomination"));
        const count = parseFloat(input.value) || 0;
        const volumeField = row.querySelector(".volume-input");
        const volume = denomination * count;
        volumeField.value = volume.toFixed(2);
        totalCount += count;
        totalVolume += volume;
		if (count > 0) {
			currency.push({
				currency: `GHS`,
				row_index: rowIndex,
				denomination: denomination,
				count: count,
				volume: volume,
			});
		}
      });

      totalCountField.value = totalCount;
      totalVolumeField.value = totalVolume.toFixed(2);

	  const serializedCurrency = JSON.stringify(currency);
	  try {
		localStorage.setItem('denosData', serializedCurrency);
	  } catch (e) {}
	  if (denosField) denosField.value = serializedCurrency;
    }

    submitBtn.addEventListener('click', function() {
	  updateVolumesAndTotals();
      amountInput.value = totalVolumeField.value;
      calculateCharge();
      staticBackdropModal.hide();
    });

    function calculateCharge() {
      const amount = parseFloat(amountInput.value);
      chargeInput.value = !isNaN(amount) ? (amount <= 50 ? '0.50' : (amount * 0.01).toFixed(2)) : '';
    }

    amountInput.addEventListener('input', calculateCharge);

    // Define category options based on transaction type
    const categoryOptions = {
      "Sending": [
        { value: "", text: "---Select---" },
        { value: "amount-charge", text: "amount-charge" },
        { value: "amount+charge", text: "amount+charge" },
        { value: "amount+in & out", text: "amount+in & out" }
      ],
      "Withdrawal": [
        { value: "", text: "---Select---" },
        { value: "self", text: "self" },
        { value: "3rd party", text: "3rd party" },
        { value: "token", text: "token" }
      ]
    };

    // Update category options based on selected transaction type
    function updateCategoryOptions() {
      const transactionTypeValue = transactionType.value;
      categorySelect.innerHTML = ""; // Clear current options

      // Populate options based on selected transaction type
      if (categoryOptions[transactionTypeValue]) {
        categoryDiv.classList.remove('d-none');
        categoryOptions[transactionTypeValue].forEach(option => {
          const opt = document.createElement('option');
          opt.value = option.value;
          opt.textContent = option.text;

          // Pre-select the option if it matches the existing category
          if (option.value === existingCategory) {
            opt.selected = true;
          }

          categorySelect.appendChild(opt);
        });
      } else {
        categoryDiv.classList.add('d-none'); // Hide if no options are available
      }
      showFields();
    }

    // Show or hide fields based on selected category
    function showFields() {
      const transactionTypeValue = transactionType.value;
      const categoryValue = categorySelect.value;

      // Hide all token fields and receiver initially
      tokenFields.forEach(field => field.classList.add('d-none'));
      receiver.classList.add('d-none');

      // Show fields based on selected category for Withdrawal
      if (transactionTypeValue === 'Withdrawal') {
        if (categoryValue === 'token') {
          tokenFields.forEach(field => field.classList.remove('d-none')); // Show all token fields
          receiver.classList.remove('d-none'); // Show receiver
        } else if (categoryValue === '3rd party') {
          receiver.classList.remove('d-none'); // Show receiver only
        }
      }
    }

    function lockApprovedTransactionForm() {
      if (originalStatus !== 'APPROVED' || !editForm) return;
      const allowedNames = new Set(['transaction_status']);
      editForm.querySelectorAll('input, select, textarea').forEach((field) => {
        const fieldName = field.getAttribute('name') || '';
        const fieldType = (field.getAttribute('type') || '').toLowerCase();
        if (!fieldName || allowedNames.has(fieldName) || fieldType === 'hidden') {
          return;
        }
        field.disabled = true;
      });
    }

    function showServerErrorAlert() {
      if (!serverErrorMessage) return;
      const title = /cashdrawer|balance|shortfall/i.test(serverErrorMessage)
        ? 'Cashdrawer balance too low'
        : 'Unable to update transaction';

      if (window.basicAdminSwalAlert) {
        window.basicAdminSwalAlert(title, serverErrorMessage, 'error');
        return;
      }

      if (window.Swal && window.Swal.fire) {
        const theme = window.basicAdminSwalTheme ? window.basicAdminSwalTheme() : {};
        window.Swal.fire(Object.assign({
          icon: 'error',
          title: title,
          text: serverErrorMessage,
          confirmButtonText: 'OK'
        }, theme));
        return;
      }

      alert(serverErrorMessage);
    }

    // Event listeners
    transactionType.addEventListener("change", updateCategoryOptions);
    categorySelect.addEventListener("change", showFields);
    // Initialize category options on page load
    updateCategoryOptions();
    restoreDenominations();
    lockApprovedTransactionForm();
    showServerErrorAlert();
  });
</script>
