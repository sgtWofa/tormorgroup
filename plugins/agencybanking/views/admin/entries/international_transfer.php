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
		<div class="row col-md-10 col-8 mx-auto shadow p-4 mb-5 rounded">
			
			<?=csrf()?>

			<h4 class="text-center">
				<span><img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:60px;max-height: 60px;object-fit: cover;"></span>
				International Transfer
			</h4>
	 
			<div class="mb-2">
				<label for="transaction_type" class="form-label">Transaction type</label>
				<select class="form-select transaction_type" id="transaction_type" name="transaction_type" onchange="updateCategoryOptions()" onchange="updateSourceOptions()">
				  <option value="">---Select---</option>
				  <option selected <?=old_select('transaction_type','International transfer')?> value="International transfer">International transfer</option>
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
				<select class="form-select" name="category" id="category" onchange="showFields()">
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

			<div class="mb-2 account_number d-none">
			  <label for="account_number" class="form-label">Account number</label>
			  <input name="account_number" value="<?=old_value('account_number')?>" type="text" class="form-control" id="account_number" placeholder="Enter account number">
				
				<?php if(!empty($errors['account_number'])):?>
				  <small class="text-danger"><?=$errors['account_number']?></small>
				<?php endif?>
			</div>

			<div class="mb-2 destination_bank d-none">
			  <label for="destination_bank" class="form-label">Destination bank</label>
			  <select class="form-select destination_bank" name="destination_bank">
				  <option value="">---Select---</option>
				  <option <?=old_select('destination_bank','Access Bank')?> value="Access Bank">Access Bank</option>
				  <option <?=old_select('destination_bank','ADB')?> value="ADB">ADB</option>
				  <option <?=old_select('destination_bank','CBG')?> value="CBG">CBG</option>
				  <option <?=old_select('destination_bank','Cal Bank')?> value="Cal Bank">Cal Bank</option>
				  <option <?=old_select('destination_bank','Ecobank')?> value="Ecobank">Ecobank</option>
				  <option <?=old_select('destination_bank','Fidelity')?> value="Fidelity">Fidelity</option>
				  <option <?=old_select('destination_bank','FBN')?> value="FBN">FBN</option>
				  <option <?=old_select('destination_bank','Gtbank')?> value="Gtbank">Gtbank</option>
				  <option <?=old_select('destination_bank','GCB')?> value="GCB">GCB</option>
				  <option <?=old_select('destination_bank','NIB')?> value="NIB">NIB</option>
				  <option <?=old_select('destination_bank','Prudential Bank')?> value="Prudential Bank">Prudential Bank</option>
				  <option <?=old_select('destination_bank','Zenit Bank')?> value="Zenit Bank">Zenit Bank</option>
			  </select>
				<?php if(!empty($errors['destination_bank'])):?>
				  <small class="text-danger"><?=$errors['destination_bank']?></small>
				<?php endif?>
			</div>

			<div class="mb-2 source_bank d-none">
			  <label for="source_bank" class="form-label">Source bank</label>
			  <input name="source_bank" value="<?=old_value('source_bank')?>" type="text" class="form-control" id="source_bank" placeholder="Enter destination bank ">			
				<?php if(!empty($errors['source_bank'])):?>
				  <small class="text-danger"><?=$errors['source_bank']?></small>
				<?php endif?>
			</div>

			<div class="mb-2 sender_phone d-none">
			  <label for="sender_phone" class="form-label">Sender's phone</label>
			  <input name="sender_phone" value="<?=old_value('sender_phone')?>" type="text" class="form-control" id="sender_phone" placeholder="Enter sender's phone number">			
				<?php if(!empty($errors['sender_phone'])):?>
				  <small class="text-danger"><?=$errors['sender_phone']?></small>
				<?php endif?>
			</div>

			<div class="mb-2 ussd d-none">
			  <label for="ussd" class="form-label">USSD used</label>
			  <input name="ussd" value="<?=old_value('ussd')?>" type="text" class="form-control" id="ussd" placeholder="Enter ussd used">
				
				<?php if(!empty($errors['ussd'])):?>
				  <small class="text-danger"><?=$errors['ussd']?></small>
				<?php endif?>
			</div>

			<div class="mb-2 cash_source d-none">
			  <label for="cash_source" class="form-label">Source of funds</label>
			  <input name="cash_source" value="<?=old_value('cash_source')?>" type="text" class="form-control" id="cash_source" placeholder="Enter source of funds">
				
				<?php if(!empty($errors['cash_source'])):?>
				  <small class="text-danger"><?=$errors['cash_source']?></small>
				<?php endif?>
			</div>

			<div class="mb-2 balance_before d-none">
			  <label for="balance_before" class="form-label">Balance before</label>
			  <input name="balance_before" value="<?=old_value('balance_before')?>" type="number" step="0.01" class="form-control" id="balance_before" placeholder="Enter balance_before">
				
				<?php if(!empty($errors['balance_before'])):?>
				  <small class="text-danger"><?=$errors['balance_before']?></small>
				<?php endif?>
			</div>

			<div class="mb-2 amount d-none">
			  <label for="amount" class="form-label">Amount</label>
			  <input name="amount" value="<?=old_value('amount')?>" type="number" step="0.01" class="form-control" id="amount" placeholder="Enter amount">
				
				<?php if(!empty($errors['amount'])):?>
				  <small class="text-danger"><?=$errors['amount']?></small>
				<?php endif?>
			</div>

			<div class="mb-2 full_name d-none">
			  <label for="full_name" class="form-label">Full name</label>
			  <input name="full_name" value="<?=old_value('full_name')?>" type="text" class="form-control" id="full_name" placeholder="Enter full name here">			
				<?php if(!empty($errors['full_name'])):?>
				  <small class="text-danger"><?=$errors['full_name']?></small>
				<?php endif?>
			</div>

			<div class="mb-2 dob d-none">
			  <label for="dob" class="form-label">Date of birth</label>
			  <input name="dob" value="<?=old_value('dob')?>" type="date" class="form-control" id="dob" placeholder="Date of birth">			
				<?php if(!empty($errors['dob'])):?>
				  <small class="text-danger"><?=$errors['dob']?></small>
				<?php endif?>
			</div>

			<div class="mb-2 house_number d-none">
			  <label for="house_number" class="form-label">House number</label>
			  <input name="house_number" value="<?=old_value('house_number')?>" type="text" class="form-control" id="house_number" placeholder="Enter house number here">			
				<?php if(!empty($errors['house_number'])):?>
				  <small class="text-danger"><?=$errors['house_number']?></small>
				<?php endif?>
			</div>

			<div class="mb-2 nearest_landmark d-none">
			  <label for="nearest_landmark" class="form-label">Nearest landmark</label>
			  <input name="nearest_landmark" value="<?=old_value('nearest_landmark')?>" type="text" class="form-control" id="nearest_landmark" placeholder="Enter nearst landmark here">			
				<?php if(!empty($errors['nearest_landmark'])):?>
				  <small class="text-danger"><?=$errors['nearest_landmark']?></small>
				<?php endif?>
			</div>

			<div class="mb-2 id_type d-none">
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

			<div class="mb-2 id_number d-none">
			  <label for="id_number" class="form-label">ID number</label>
			  <input name="id_number" value="<?=old_value('id_number')?>" type="text" class="form-control" id="id_number" placeholder="Enter ID number here">			
				<?php if(!empty($errors['id_number'])):?>
				  <small class="text-danger"><?=$errors['id_number']?></small>
				<?php endif?>
			</div>

			<div class="mb-2 next_of_king d-none">
			  <label for="next_of_king" class="form-label">Next of king</label>
			  <input name="next_of_king" value="<?=old_value('next_of_king')?>" type="text" class="form-control" id="next_of_king" placeholder="Enter name of next of king">
				
				<?php if(!empty($errors['next_of_king'])):?>
				  <small class="text-danger"><?=$errors['next_of_king']?></small>
				<?php endif?>
			</div>

			<div class="mb-2 nok_phone d-none">
			  <label for="nok_phone" class="form-label">Next of king phone</label>
			  <input name="nok_phone" value="<?=old_value('nok_phone')?>" type="text" class="form-control" id="nok_phone" placeholder="Enter next of king phone number">			
				<?php if(!empty($errors['nok_phone'])):?>
				  <small class="text-danger"><?=$errors['nok_phone']?></small>
				<?php endif?>
			</div>

			<div class="mb-2 nok_relation d-none">
			  <label for="nok_relation" class="form-label">Relation with Next of king</label>
			  <input name="nok_relation" value="<?=old_value('nok_relation')?>" type="text" class="form-control" id="nok_relation" placeholder="Client's relationship with next of king">			
				<?php if(!empty($errors['nok_relation'])):?>
				  <small class="text-danger"><?=$errors['nok_relation']?></small>
				<?php endif?>
			</div>

			<div class="mb-2 token receiver d-none">
			  <label for="receiver" class="form-label">Receiver's phone</label>
			  <input name="receiver" value="<?=old_value('receiver')?>" type="text" class="form-control" id="receiver" placeholder="Enter receiver's phone number">
				
				<?php if(!empty($errors['receiver'])):?>
				  <small class="text-danger"><?=$errors['receiver']?></small>
				<?php endif?>
			</div>

			<div class="mb-2 d-none">
			  <label for="charge" class="form-label">Charge </label>
			  <div class="input-group">
			  	<div id="currencySymbol" class="input-group-text">GH₵</div>
			  	<input name="charge" value="<?=old_value('charge')?>" type="text" class="form-control" id="charge" placeholder="GH&cent;0.00">
			  </div>
				
				<?php if(!empty($errors['charge'])):?>
				  <small class="text-danger"><?=$errors['charge']?></small>
				<?php endif?>
			</div>

			<div class="mb-2 balance d-none">
			  <label for="balance" class="form-label">New Balance</label>
			  <input name="balance" value="<?=old_value('balance')?>" type="text" class="form-control" id="balance" placeholder="GH&cent;">
				
				<?php if(!empty($errors['balance'])):?>
				  <small class="text-danger"><?=$errors['balance']?></small>
				<?php endif?>
			</div>

			<!-- Receiving/Paying Wallet fields removed; wallets are now derived automatically in the controller
			based on the agent's cash wallet and the selected network, and cash/e-cash movements are tracked
			through the cashdrawer implementation.

			<div class="mb-2 transaction_status d-none">
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
	const categoryOptions = {
		"Deposit": [
			{ value: "", text: "---Select---" },
			{ value: "self", text: "self" },
			{ value: "3rd party", text: "3rd party" }
		]
	};

	const sourceOptions = {
		"Withdrawal (CIT)": [
			{ value: "", text: "---Select---" },
			{ value: "GhQR", text: "GhQR" },
			{ value: "Cal bank", text: "Cal bank" },
			{ value: "Ecobank", text: "Ecobank" }
		],
		"Deposit": [
			{ value: "", text: "---Select---" },
			{ value: "Fidelity", text: "Fidelity" },
			{ value: "Cal bank", text: "Cal bank" },
			{ value: "Ecobank", text: "Ecobank" },
			{ value: "FBN", text: "FBN" },
			{ value: "Access Bank", text: "Access Bank" },
		],
		"Withdrawal (MIT)": [
			{ value: "", text: "---Select---" },
			{ value: "Cal bank", text: "Cal bank" },
			{ value: "FBN", text: "FBN" },
			{ value: "Access Bank", text: "Access Bank" }
		],
		"Account opening": [
			{ value: "", text: "---Select---" },
			{ value: "Fidelity", text: "Fidelity" },
			{ value: "Cal bank", text: "Cal bank" },
			{ value: "Ecobank", text: "Ecobank" }
		],
		"Mini statement": [
			{ value: "", text: "---Select---" },
			{ value: "Fidelity", text: "Fidelity" },
		],
		"Balance enquiry": [
			{ value: "", text: "---Select---" },
			{ value: "Fidelity", text: "Fidelity" },
		],
		"Funds transfer": [
			{ value: "", text: "---Select---" },
			{ value: "Fidelity", text: "Fidelity" },
		],
		"Interbank (gip)": [
			{ value: "", text: "---Select---" },
			{ value: "Gtbank", text: "Gtbank" },
			{ value: "Ecobank", text: "Ecobank" },
			{ value: "FBN", text: "FBN" },
			{ value: "Access Bank", text: "Access Bank" },
		],
		"International transfer": [
			{ value: "", text: "---Select---" },
			{ value: "Ecobank", text: "Ecobank" },
		],
	};

	const transactionType = document.getElementById('transaction_type'); // Corrected ID selection
	const categorySelect = document.getElementById('category');
	const sourceSelect = document.getElementById('network');
	const categoryDiv = document.querySelector('.category');

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
		} else {
			categorySelect.classList.add('d-none'); // Hide if no options are available
			categoryDiv.classList.add('d-none'); // Hide if no options are available
		}
		showFields();
	}

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
		showFields();
	}

	function showFields() {
		const sourceValue = sourceSelect.value;
		const transactionTypeValue = transactionType.value;
		const categoryValue = categorySelect.value;
		const account_number = document.querySelector('.account_number');
		const destination_bank = document.querySelector('.destination_bank');
		const balance = document.querySelector('.balance');
		const balance_before = document.querySelector('.balance_before');
		const sender_phone = document.querySelector('.sender_phone');
		const transaction_status = document.querySelector('.transaction_status');
		const cash_source = document.querySelector('.cash_source');
		const ussd = document.querySelector('.ussd');
		const amount = document.querySelector('.amount');
		const full_name = document.querySelector('.full_name');
		const dob = document.querySelector('.dob');
		const house_number = document.querySelector('.house_number');
		const nearest_landmark = document.querySelector('.nearest_landmark');
		const id_type = document.querySelector('.id_type');
		const id_number = document.querySelector('.id_number');
		const next_of_king = document.querySelector('.next_of_king');
		const nok_phone = document.querySelector('.nok_phone');
		const nok_relation = document.querySelector('.nok_relation');

		account_number.classList.add('d-none');
		destination_bank.classList.add('d-none');
		balance_before.classList.add('d-none');
		balance.classList.add('d-none');
		sender_phone.classList.add('d-none');
		transaction_status.classList.add('d-none');
		cash_source.classList.add('d-none');
		ussd.classList.add('d-none');
		amount.classList.add('d-none');
		full_name.classList.add('d-none');
		dob.classList.add('d-none');
		house_number.classList.add('d-none');
		nearest_landmark.classList.add('d-none');
		id_type.classList.add('d-none');
		id_number.classList.add('d-none');
		next_of_king.classList.add('d-none');
		nok_phone.classList.add('d-none');
		nok_relation.classList.add('d-none');

		// Show fields based on selected category for Withdrawal
		if (transactionTypeValue === 'Withdrawal') {
			account_number.classList.remove('d-none');
			balance_before.classList.remove('d-none');
			balance.classList.remove('d-none');
			transaction_status.classList.remove('d-none');
			amount.classList.remove('d-none');
		}else
		if (transactionTypeValue === 'CIT') {
			cash_source.classList.remove('d-none');
			transaction_status.classList.remove('d-none');
			amount.classList.remove('d-none');

			if (sourceValue === 'GhQR') {
				ussd.classList.remove('d-none');
			}
		}else
		if (transactionTypeValue === 'Mini statement') {
			account_number.classList.remove('d-none');
			id_type.classList.remove('d-none');
			id_number.classList.remove('d-none');
			transaction_status.classList.remove('d-none');
		}else
		if (transactionTypeValue === 'Balance enquiry') {
			account_number.classList.remove('d-none');
			id_type.classList.remove('d-none');
			id_number.classList.remove('d-none');
			transaction_status.classList.remove('d-none');
		}else
		if (transactionTypeValue === 'Account opening') {
			full_name.classList.remove('d-none');
			dob.classList.remove('d-none');
			house_number.classList.remove('d-none');
			id_type.classList.remove('d-none');
			id_number.classList.remove('d-none');
			next_of_king.classList.remove('d-none');
			nok_phone.classList.remove('d-none');
			nok_relation.classList.remove('d-none');
			transaction_status.classList.remove('d-none');
		}else
		if (transactionTypeValue === 'Funds transfer') {
			cash_source.classList.remove('d-none');
			amount.classList.remove('d-none');
		}else
		if (transactionTypeValue === 'Deposit') {
			account_number.classList.remove('d-none');
			balance_before.classList.remove('d-none');
			balance.classList.remove('d-none');
			amount.classList.remove('d-none');
			transaction_status.classList.remove('d-none');
		}else
		if (transactionTypeValue === 'Interbank (gip)') {
			account_number.classList.remove('d-none');
			destination_bank.classList.remove('d-none');
			balance_before.classList.remove('d-none');
			amount.classList.remove('d-none');
			balance.classList.remove('d-none');
			sender_phone.classList.remove('d-none');
			transaction_status.classList.remove('d-none');
		}else{
			account_number.classList.add('d-none');
			destination_bank.classList.add('d-none');
			balance_before.classList.add('d-none');
			balance.classList.add('d-none');
			sender_phone.classList.add('d-none');
			transaction_status.classList.add('d-none');
			cash_source.classList.add('d-none');
			ussd.classList.add('d-none');
			amount.classList.add('d-none');
			full_name.classList.add('d-none');
			dob.classList.add('d-none');
			house_number.classList.add('d-none');
			nearest_landmark.classList.add('d-none');
			id_type.classList.add('d-none');
			id_number.classList.add('d-none');
			next_of_king.classList.add('d-none');
			nok_phone.classList.add('d-none');
			nok_relation.classList.add('d-none');
		}
	}

	function calculateCharge() {
		const amountInput = document.getElementById('amount');
		const chargeInput = document.getElementById('charge');
		const amount = parseFloat(amountInput.value);

		if (!isNaN(amount)) {				
			if (amount <= 50) {
				chargeInput.value = '0.50';
				chargeInput.innerText += chargeInput.value;
			} else {
				chargeInput.value = (amount * 0.01).toFixed(2); // 1% of amount
			}
		} else {
			chargeInput.value = (0 * 0.01).toFixed(2); // Clear charge if amount is not a valid number
				chargeInput.innerText += chargeInput.value;
		}
	}

	function calculateBalance() {
		const transactionType = document.getElementById('transaction_type').value;
		const balanceBefore = parseFloat(document.getElementById('balance_before').value) || 0;
		const amount = parseFloat(document.getElementById('amount').value) || 0;
		const charge = parseFloat(document.getElementById('charge').value) || 0;
		const balanceField = document.getElementById('balance');

		let newBalance = balanceBefore;

		// Adjust balance based on transaction type
		switch (transactionType) {
			case 'Deposit':
				newBalance = balanceBefore + amount - charge;
				break;
			case 'Withdrawal':
				newBalance = balanceBefore - amount - charge;
				break;
			case 'Funds transfer':
			case 'Interbank (gip)':
			case 'International transfer':
				newBalance = balanceBefore - amount - charge;
				break;
			default:
				// For other transaction types, keep balance unchanged or handle as needed
				newBalance = balanceBefore;
				break;
		}

		balanceField.value = newBalance.toFixed(2);
	}

	// Initialize on page load
	document.addEventListener("DOMContentLoaded", function() {
		updateCategoryOptions();
		updateSourceOptions();
		calculateCharge();
	});

	// Event listeners for real-time updates
	document.getElementById('amount').addEventListener('input', calculateCharge);
	transactionType.addEventListener("change", updateCategoryOptions);
	transactionType.addEventListener("change", updateSourceOptions);
	categorySelect.addEventListener("change", showFields);
	transactionType.addEventListener("change", calculateBalance);
	document.getElementById('amount').addEventListener('input', calculateBalance);
	document.getElementById('balance_before').addEventListener('input', calculateBalance);
	document.getElementById('charge').addEventListener('input', calculateBalance);
</script>

