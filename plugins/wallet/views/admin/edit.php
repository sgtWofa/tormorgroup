<?php if(user_can('edit_wallet')):?>
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

	<?php if(!empty($row)):?>

	<form method="post" enctype="multipart/form-data">		
		<div class="row col-9 col-lg-5 mx-auto shadow p-2 rounded">
			
			<?=csrf()?>

			<h4 class="text-center">
				<span><img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:60px;max-height: 60px;object-fit: cover;"></span>
				Update Account
			</h4>
	 
			<div class="col-md-12 ">
				<label for="wallet_type" class="form-label">Account type</label>
				<select class="form-select wallet_type" id="wallet_type" name="wallet_type" onchange="updateNetworkOptions()">
				  <option value="">---Select---</option>
				  <option <?=old_select('wallet_type','Cash', $row->wallet_type ?? '')?> value="Cash">Cash</option>
				  <option <?=old_select('wallet_type','Bank', $row->wallet_type ?? '')?> value="Bank">Bank</option>
				  <option <?=old_select('wallet_type','E-cash', $row->wallet_type ?? '')?> value="E-cash">E-cash</option>
				</select>
				<?php if(!empty($errors['wallet_type'])): ?>
				  <small class="text-danger"><?=$errors['wallet_type']?></small>
				<?php endif ?>
			</div>
	 
			<div class="col-md-12 ">
				<label for="category" class="form-label">Category</label>
				<select class="form-select" name="category" id="category">	
				  <option value="">---Select---</option>
				  <!-- Options will be populated dynamically based on transaction type -->				  
				  <?php if(!empty($categories)):?>
				  	<?php foreach ($categories as $cat): ?>
				  	<option <?=old_select('category',$cat->id, $row->category ?? '')?> value="<?=$cat->id ?? ''?>"><?=ucfirst($cat->category)?></option>				  		
				  	<?php endforeach ?>
				  	<?php endif; ?>
				</select>
				<?php if(!empty($errors['category'])): ?>
				  <small class="text-danger"><?=$errors['category']?></small>
				<?php endif ?>
			</div>
	 
			<div class="col-md-12 ">
				<label for="network" class="form-label">Currency</label>
				<select class="form-select" name="network" id="network">	
				  <option value="">---Select---</option>
				  <!-- Options will be populated dynamically based on transaction type -->
				  <option <?=old_select('network','mtn', $row->network ?? '')?> value="mtn">mtn</option>
				  <option <?=old_select('network','at', $row->network ?? '')?> value="at">at</option>
				  <option <?=old_select('network','telecel', $row->network ?? '')?> value="telecel">telecel</option>
				  <option <?=old_select('network','GHS cash', $row->network ?? '')?> value="GHS cash">GHS cash</option>
				  <option <?=old_select('network','CFA cash', $row->network ?? '')?> value="CFA cash">CFA cash</option>
				</select>
				<?php if(!empty($errors['network'])): ?>
				  <small class="text-danger"><?=$errors['network']?></small>
				<?php endif ?>
			</div>

			<div class="col-md-12  usn">
			  <label for="name" class="form-label">Account name</label>
			  <input name="name" value="<?=old_value('name', $row->name ?? '')?>" type="text" class="form-control" id="name" placeholder="Enter name on wallet or account">				
				<?php if(!empty($errors['name'])): ?>
				  <small class="text-danger"><?=$errors['name']?></small>
				<?php endif ?>
			</div>

			<div class="col-md-12 ">
			  <label for="wallet_number" class="form-label">Account number</label>
			  <input name="wallet_number" value="<?=old_value('wallet_number', $row->wallet_number ?? '')?>" type="text" class="form-control" id="wallet_number" placeholder="Enter wallet or account number">				
				<?php if(!empty($errors['wallet_number'])): ?>
				  <small class="text-danger"><?=$errors['wallet_number']?></small>
				<?php endif ?>
			</div>

			<div class="col-md-12  assignedne">
			  <label for="assigned" class="form-label">In charge</label>
			  <select class="form-select assigned" name="assigned">
				  <option value="">---Select---</option>
				  <?php if(!empty($users)):?>
				  	<?php foreach ($users as $user): ?>				  		
				  		<option <?=old_select('assigned',$user->id, $row->assigned)?> value="<?=$user->id ?? ' ' ?>"><?=$user->first_name . ' '. $user->last_name ?? ''?></option>
				  	<?php endforeach;?>
				  	<?php endif;?>
			  </select>
			  	<?php if(!empty($errors['assigned'])): ?>
				  <small class="text-danger"><?=$errors['assigned']?></small>
				<?php endif ?>
			</div>

			<div class="col-md-12  amount">
			  <label for="amount" class="form-label">Opening balance</label>
			  <input name="amount" value="<?=old_value('amount', $row->amount)?>" type="number" step="0.1" class="form-control" id="amount" placeholder="Enter amount" readonly>				
				<?php if(!empty($errors['amount'])): ?>
				  <small class="text-danger"><?=$errors['amount']?></small>
				<?php endif ?>
			</div>
				
			<div class="mb-3 col-12">
			  <label for="description" class="form-label">Description/Narration</label>
				<textarea rows="2" id="description" class="form-control" name="description"  placeholder="Write a brief description for this transaction"><?=old_value('description', $row->description ?? '')?></textarea>
			
				<?php if(!empty($errors['description'])):?>
				  <small class="text-danger"><?=$errors['description']?></small>
				<?php endif?>
			</div>

			<div class="col-md-12  transaction_status">
			  <label for="transaction_status" class="form-label">Status</label>
			  <select class="form-select transaction_status" name="transaction_status">
				  <option value="">---Select---</option>
				  <option <?=old_select('transaction_status','Draft', $row->transaction_status ?? '')?> value="Draft">Draft</option>
				  <option <?=old_select('transaction_status','Pending', $row->transaction_status ?? '')?> value="Pending">Pending</option>
				  <option <?=old_select('transaction_status','Rejected', $row->transaction_status ?? '')?> value="Rejected">Rejected</option>
				  <option <?=old_select('transaction_status','Approved', $row->transaction_status ?? '')?> value="Approved">Approved</option>
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
	const networkOptions = {
		"E-cash": [
			{ value: "", text: "---Select---" },
			{ value: "mtn", text: "mtn" },
			{ value: "at", text: "at" },
			{ value: "telecel", text: "telecel" },
			{ value: "G-money", text: "G-money" },
		],
		"Cash": [
			{ value: "", text: "---Select---" },
			{ value: "GHS", text: "GHS" },
			{ value: "CFA", text: "CFA" }
		],
		"Bank": [
			{ value: "", text: "---Select---" },
			{ value: "Fidelity", text: "Fidelity" },
			{ value: "Cal bank", text: "Cal bank" },
			{ value: "Ecobank", text: "Ecobank" },
			{ value: "Access Bank", text: "Access Bank" },
			{ value: "FBN", text: "FBN" },
			{ value: "Gtbank", text: "Gtbank" },
		]
	};
	const walletType = document.getElementById('wallet_type');
	const networkSelect = document.getElementById('network');
	const existingNetwork = "<?= $row->network ?? '' ?>"; // PHP variable from backend

	function updateNetworkOptions() {
		const walletTypeValue = walletType.value;
		networkSelect.innerHTML = ""; // Clear current options

		// Populate options based on selected transaction type
		if (networkOptions[walletTypeValue]) {
			networkOptions[walletTypeValue].forEach(option => {
				const opt = document.createElement('option');
				opt.value = option.value;
				opt.textContent = option.text;
				
				// Pre-select the option if it matches the existing category
				if (option.value === existingNetwork) {
					opt.selected = true;
				}

				networkSelect.appendChild(opt);
			});
		} 
	}

	// Initialize on page load
	document.addEventListener("DOMContentLoaded", function() {
		updateNetworkOptions();
	});

	// Event listeners for real-time updates
	walletType.addEventListener("change", updateNetworkOptions);

</script>