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
		<div class="row col-9 col-lg-5 mx-auto shadow p-4 mb-5 rounded">
			
			<?=csrf()?>

			<h4 class="text-center">
				<span><img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:60px;max-height: 60px;object-fit: cover;"></span>
				Balance Enquiry
			</h4>
	 
			<div class="mb-2">
				<label for="transaction_type" class="form-label">Transaction type</label>
				<select class="form-select transaction_type" id="transaction_type" name="transaction_type" onchange="updateCategoryOptions()" onchange="updateSourceOptions()">
				  <option value="">---Select---</option>
				  <option <?=old_select('transaction_type','Balance enquiry')?> value="Balance enquiry" selected>Balance enquiry</option>
				</select>

				<?php if(!empty($errors['transaction_type'])):?>
				  <small class="text-danger"><?=$errors['transaction_type']?></small>
				<?php endif?>
			</div>
	 
			<div class="mb-2">
				<label for="network" class="form-label">Service provider</label>
				<select class="form-select" name="network" id="network" onchange="showFields()">			  
				  <!-- Options will be populated dynamically based on transaction type -->
				</select>

				<?php if(!empty($errors['network'])):?>
				  <small class="text-danger"><?=$errors['network']?></small>
				<?php endif?>
			</div>
	 
			<div class="mb-2 category d-none">
				<label for="category" class="form-label">Category</label>
				<select class="form-select" name="category" id="category">
				  <!-- Options will be populated dynamically based on transaction type -->
				</select>

				<?php if(!empty($errors['category'])):?>
				  <small class="text-danger"><?=$errors['category']?></small>
				<?php endif?>
			</div>

			<div class="mb-2">
			  <label for="phone" class="form-label">Phone</label>
			  <input name="phone" value="<?=old_value('phone')?>" type="text" class="form-control" id="phone" placeholder="Enter phone number">
				
				<?php if(!empty($errors['phone'])):?>
				  <small class="text-danger"><?=$errors['phone']?></small>
				<?php endif?>
			</div>

			<div class="mb-2 account_number">
			  <label for="account_number" class="form-label">Account number</label>
			  <input name="account_number" value="<?=old_value('account_number')?>" type="text" class="form-control" id="account_number" placeholder="Enter account number">
				
				<?php if(!empty($errors['account_number'])):?>
				  <small class="text-danger"><?=$errors['account_number']?></small>
				<?php endif?>
			</div>

			<div class="mb-2 id_type">
			  <label for="id_type" class="form-label">ID type</label>
			  <select class="form-select id_type" name="id_type">
				  <option value="">---Select---</option>
				  <option <?=old_select('id_type','Ghana card')?> value="Ghana card">Ghana card</option>
				  <option <?=old_select('id_type','Voter ID')?> value="Voter ID">Voter ID</option>
				  <option <?=old_select('id_type','NHIS')?> value="NHIS">NHIS</option>
				  <option <?=old_select('id_type','Passport')?> value="Passport">Passport</option>
				  <option <?=old_select('id_type','Driver license')?> value="Driver license">Driver license</option>
			  </select>			
				<?php if(!empty($errors['id_type'])):?>
				  <small class="text-danger"><?=$errors['id_type']?></small>
				<?php endif?>
			</div>

			<div class="mb-2 id_number">
			  <label for="id_number" class="form-label">ID number</label>
			  <input name="id_number" value="<?=old_value('id_number')?>" type="text" class="form-control" id="id_number" placeholder="Enter ID number here">			
				<?php if(!empty($errors['id_number'])):?>
				  <small class="text-danger"><?=$errors['id_number']?></small>
				<?php endif?>
			</div>

			<div class="mb-2 transaction_status d-none">
			  <label for="transaction_status" class="form-label">Status</label>
			  <select class="form-select transaction_status" name="transaction_status">
				  <option <?=old_select('transaction_status','Approved')?> value="Approved">Approved</option>
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
				<button class="btn btn-danger">
					<i class="fa-solid fa-save"></i> Save
				</button>
			</div>
		</div>
	</form>

<?php else:?>
	<div class="alert alert-danger text-center">
		Access denied. You dont have permission for this action
	</div>
<?php endif?>

<script>

	const sourceOptions = {
		
		"Balance enquiry": [
			{ value: "Fidelity", text: "Fidelity" },
		]
	};

	const transactionType = document.getElementById('transaction_type'); // Corrected ID selection
	const sourceSelect = document.getElementById('network');

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
		} 
	}

	// Initialize on page load
	document.addEventListener("DOMContentLoaded", function() {
		updateSourceOptions();
		//calculateCharge();
	});

	// Event listeners for real-time updates
	//document.getElementById('amount').addEventListener('input', calculateCharge);
	transactionType.addEventListener("change", updateSourceOptions);
</script>

