<?php if(user_can('add_agencybanking')): ?>
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
		<div class="row g-3 col-md-10 col-lg-8 mx-auto shadow p-4 rounded">
			
			<?=csrf()?>

			<h4 class="text-center">
				<span><img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:60px;max-height: 60px;object-fit: cover;"></span>
				New Transaction
			</h4>
	 
			<div class="mb-3 col-md-12 col-lg-6">
				<label for="transaction_type" class="form-label">Transaction type</label>
				<select class="form-select transaction_type" id="transaction_type" name="transaction_type" >
				  <option value="">---Select---</option>
				  <option <?=old_select('transaction_type','Withdrawal')?> value="Withdrawal">Withdrawal</option>
				  <option <?=old_select('transaction_type','Transfer')?> value="Transfer">Transfer</option>
				</select>
				<?php if(!empty($errors['transaction_type'])): ?>
				  <small class="text-danger"><?=$errors['transaction_type']?></small>
				<?php endif ?>
			</div>
	 
			<div class="mb-3 col-md-12 col-lg-6">
				<label for="network" class="form-label">Bank</label>
				<select class="form-select" name="network" id="network">	
				  <option value="">---Select---</option>
				  <option <?=old_select('network','GCB')?> value="GCB">GCB</option>
				  <option <?=old_select('network','Zenit bank')?> value="Zenit bank">Zenit bank</option>
				  <option <?=old_select('network','Fidelity')?> value="Fidelity">Fidelity</option>
				  <option <?=old_select('network','NIB')?> value="NIB">NIB</option>
				  <option <?=old_select('network','Ecobank')?> value="Ecobank">Ecobank</option>
				  <option <?=old_select('network','FBN')?> value="FBN">FBN</option>
				  <option <?=old_select('network','ADB')?> value="ADB">ADB</option>
				</select>
				<?php if(!empty($errors['network'])): ?>
				  <small class="text-danger"><?=$errors['network']?></small>
				<?php endif ?>
			</div>

			<div class="mb-3 col-md-12 col-lg-6 usn">
			  <label for="usn" class="form-label">Client USN</label>
			  <input name="usn" value="<?=old_value('usn')?>" type="text" class="form-control" id="usn" placeholder="Enter usn used">				
				<?php if(!empty($errors['usn'])): ?>
				  <small class="text-danger"><?=$errors['usn']?></small>
				<?php endif ?>
			</div>

			<div class="mb-3 col-md-12 col-lg-6">
			  <label for="phone" class="form-label">Client's Phone</label>
			  <input name="phone" value="<?=old_value('phone')?>" type="text" class="form-control" id="phone" placeholder="Enter phone number">				
				<?php if(!empty($errors['phone'])): ?>
				  <small class="text-danger"><?=$errors['phone']?></small>
				<?php endif ?>
			</div>

			<div class="mb-3 col-md-12 col-lg-6 destination_bank d-none">
			  <label for="destination_bank" class="form-label">Destination</label>
			  <input name="destination_bank" value="<?=old_value('destination_bank')?>" type="text" class="form-control" id="destination_bank" placeholder="Enter destination">			
				<?php if(!empty($errors['destination_bank'])): ?>
				  <small class="text-danger"><?=$errors['destination_bank']?></small>
				<?php endif ?>
			</div>

			<div class="mb-3 col-md-12 col-lg-6 receiving d-none">
			  <label for="receiving" class="form-label">Receiving sim</label>
			  <select class="form-select" name="receiving" id="receiving">	
				  <option value="">---Select---</option>
				  <option <?=old_select('transfer_to','Company sim')?> value="Company sim">Company sim</option>
				  <option <?=old_select('transfer_to','Client sim')?> value="Client sim">Client sim</option>
			  </select>
			  <?php if(!empty($errors['receiving'])): ?>
				  <small class="text-danger"><?=$errors['receiving']?></small>
			  <?php endif ?>
			</div>

			<div class="mb-3 col-md-12 col-lg-6 receiving_sim d-none">
			  <label for="receiving_sim" class="form-label">Transfer to</label>
			  <input name="receiving_sim" value="<?=old_value('receiving_sim')?>" type="text" class="form-control" id="receiving_sim" placeholder="Enter sim number">			
				<?php if(!empty($errors['receiving_sim'])): ?>
				  <small class="text-danger"><?=$errors['receiving_sim']?></small>
				<?php endif ?>
			</div>

		    <!-- Amount Input with Calculator Modal Trigger -->
		    <div class="mb-3 col-md-12 col-lg-6">
		      <label for="amount" class="form-label">Amount</label>
		      <label class="input-group" data-bs-toggle="modal" data-bs-target="#staticBackdrop"> 
		        <i class="fa-solid fa-calculator input-group-text text-warning"></i>
		          <input name="amount" value="<?=old_value('amount')?>" type="number" step="0.1" class="form-control" id="amount" placeholder="Enter amount" readonly>
		      </label>
		      <?php if(!empty($errors['amount'])):?>
		        <small class="text-danger"><?=$errors['amount']?></small>
		      <?php endif?>
		    </div>

			<div class="mb-3 col-md-12 col-lg-6">
			  <label for="charge" class="form-label">Charge </label>
			  <div class="input-group">
			  	<div id="currencySymbol" class="input-group-text">GH₵</div>
			  	<input name="charge" value="<?=old_value('charge')?>" type="text" class="form-control" id="charge" placeholder="GH&cent;0.00">
			  </div>				
				<?php if(!empty($errors['charge'])): ?>
				  <small class="text-danger"><?=$errors['charge']?></small>
				<?php endif ?>
			</div>

		<!-- Wallet functionality removed - cashdrawer is central accounting system -->

			<div class="mb-3 col-md-12 col-lg-6 transaction_status">
			  <label for="transaction_status" class="form-label">Status</label>
			  <select class="form-select transaction_status" name="transaction_status">
				  <option value="">---Select---</option>
				  <option <?=old_select('transaction_status','Draft')?> value="Draft">Draft</option>
				  <option <?=old_select('transaction_status','Pending')?> value="Pending">Pending</option>
				  <option <?=old_select('transaction_status','Rejected')?> value="Rejected">Rejected</option>
				  <option <?=old_select('transaction_status','Approved')?> value="Approved">Approved</option>
			  </select>
				
				<?php if(!empty($errors['transaction_status'])): ?>
				  <small class="text-danger"><?=$errors['transaction_status']?></small>
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
		const submitBtn = document.querySelector('.submit');
		const amountInput = document.getElementById('amount');
		const chargeInput = document.getElementById('charge');
		const staticBackdropModal = new bootstrap.Modal(document.getElementById('staticBackdrop'));

		countInputs.forEach(input => {
			input.addEventListener("input", updateVolumesAndTotals);
		});

		function updateVolumesAndTotals() {
			let totalCount = 0;
			let totalVolume = 0;

			countInputs.forEach(input => {
				const row = input.closest("tr");
				const denomination = parseFloat(row.getAttribute("data-denomination"));
				const count = parseFloat(input.value) || 0;
				const volumeField = row.querySelector(".volume-input");
				const volume = denomination * count;
				volumeField.value = volume.toFixed(2);
				totalCount += count;
				totalVolume += volume;
			});

			totalCountField.value = totalCount;
			totalVolumeField.value = totalVolume.toFixed(2);
		}

		submitBtn.addEventListener('click', function() {
			amountInput.value = totalVolumeField.value;
			calculateCharge();
			staticBackdropModal.hide();
		});

		function calculateCharge() {
			const amount = parseFloat(amountInput.value);
			chargeInput.value = !isNaN(amount) ? (amount <= 50 ? '0.50' : (amount * 0.01).toFixed(2)) : '';
		}

		amountInput.addEventListener('input', calculateCharge);

		// Dynamic Field Display Based on Transaction Type and Category
		const transactionType = document.getElementById('transaction_type');
		const receivingSelect = document.getElementById('receiving');
		const receivingSimInput = document.getElementById('receiving_sim');

		receivingSelect.addEventListener("change", updateReceivingSim);
		transactionType.addEventListener("change", showFields);
		
		function showFields() {
			const transactionTypeValue = transactionType.value;
			const destinationBank = document.querySelector('.destination_bank');
			const receiving = document.querySelector('.receiving');
			const receivingSim = document.querySelector('.receiving_sim');

			destinationBank.classList.add('d-none');
			receiving.classList.add('d-none');
			receivingSim.classList.add('d-none');

			if (transactionTypeValue === 'Transfer') {
				destinationBank.classList.remove('d-none');
				receiving.classList.remove('d-none');
				receivingSim.classList.remove('d-none');
			}
		}

		function updateReceivingSim() {
			if (receivingSelect.value === 'Company sim') {
				receivingSimInput.value = '0548214842';
				receivingSimInput.readOnly = true;
			} else {
				receivingSimInput.value = '';
				receivingSimInput.readOnly = false;
			}
		}
	});
</script>

