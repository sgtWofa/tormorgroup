<?php if(user_can('add_wallet')): ?>
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
		<div class="row col-md-12 col-md-10 col-lg-6 mx-auto shadow p-2 rounded">
			
			<?=csrf()?>

			<h4 class="text-center">
				<span><img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:60px;max-height: 60px;object-fit: cover;"></span>
				Create Wallet
			</h4>
	 
			<div class="col-md-12 ">
				<label for="wallet_type" class="form-label">Account type</label>
				<select class="form-select wallet_type" id="wallet_type" name="wallet_type" onchange="updateNetworkOptions()">
				  <option value="">---Select---</option>
				  <option <?=old_select('wallet_type','Bank')?> value="Bank">Bank</option>
				  <option <?=old_select('wallet_type','Cash')?> value="Cash">Cash</option>
				  <option <?=old_select('wallet_type','E-cash')?> value="E-cash">E-cash</option>
				  <option <?=old_select('wallet_type','EVD')?> value="EVD">EVD</option>
				  <option <?=old_select('wallet_type','Ezwich')?> value="Ezwich">Ezwich</option>
				  <option <?=old_select('wallet_type','sims')?> value="sims">sims</option>
				</select>
				<?php if(!empty($errors['wallet_type'])): ?>
				  <small class="text-danger"><?=$errors['wallet_type']?></small>
				<?php endif ?>
			</div>
	 
			<div class="col-md-12 ">
				<label for="network" class="form-label">Network/Currency</label>
				<select class="form-select" name="network" id="network">	
				  <option value="">---Select---</option>
				  <!-- Options will be populated dynamically based on transaction type -->
				</select>
				<?php if(!empty($errors['network'])): ?>
				  <small class="text-danger"><?=$errors['network']?></small>
				<?php endif ?>
			</div>

			<div class="col-md-12  usn">
			  <label for="name" class="form-label">Name</label>
			  <input name="name" value="<?=old_value('name')?>" type="text" class="form-control" id="name" placeholder="Enter name on wallet or account">				
				<?php if(!empty($errors['name'])): ?>
				  <small class="text-danger"><?=$errors['name']?></small>
				<?php endif ?>
			</div>

			<div class="col-md-12 ">
			  <label for="wallet_number" class="form-label">Account number</label>
			  <input name="wallet_number" value="<?=old_value('wallet_number')?>" type="text" class="form-control" id="wallet_number" placeholder="Enter wallet or account number">				
				<?php if(!empty($errors['wallet_number'])): ?>
				  <small class="text-danger"><?=$errors['wallet_number']?></small>
				<?php endif ?>
			</div>
				
			<div class="col-12">
			  <label for="description" class="form-label">Description</label>
				<textarea rows="2" id="description" class="form-control" name="description"  placeholder="Write a brief description for this wallet"><?=old_value('description')?></textarea>
				<?php if(!empty($errors['description'])):?>
				  <small class="text-danger col-12"><?=$errors['description']?></small>
				<?php endif?>
			</div>

			<div class="col-md-12  amount">
			  <label for="amount" class="form-label">Opening balance</label>
			  <input name="amount" value="<?=old_value('amount')?>" type="number" step="0.1" class="form-control" id="amount" placeholder="Enter amount">				
				<?php if(!empty($errors['amount'])): ?>
				  <small class="text-danger"><?=$errors['amount']?></small>
				<?php endif ?>
			</div>

			<div class="col-md-12  parent_account">
			  <label for="parent_account" class="form-label">Source of funds</label>
			  <select class="form-select parent_account" name="parent_account">
				  <option value="">---Select---</option>
				  <?php if(!empty($mycategories)):?>
				  	<?php foreach ($mycategories as $cat): ?>				  		
				  		<option <?=old_select('parent_account',$cat->id ?? '')?> value="<?=$cat->id ?? ' ' ?>"><?=$cat->category ?? ''?> (<?=number_format($cat->balance ?? 0, 2)?>)</option>
				  	<?php endforeach;?>
				  	<?php endif;?>
			  </select>
			  	<?php if(!empty($errors['parent_account'])): ?>
				  <small class="text-danger"><?=$errors['parent_account']?></small>
				<?php endif ?>
			</div>

			<div class="col-md-12  assigned">
			  <label for="assigned" class="form-label">In charge</label>
			  <select class="form-select assigned" name="assigned">
				  <option value="">---Select---</option>
				  <?php if(!empty($users)):?>
				  	<?php foreach ($users as $user): ?>				  		
				  		<option <?=old_select('assigned',$user->id ?? '')?> value="<?=$user->id ?? ' ' ?>"><?=$user->first_name . ' '. $user->last_name ?? ''?> (<?=$user->roles[0] ?? ''?>)</option>
				  	<?php endforeach;?>
				  	<?php endif;?>
			  </select>
			  	<?php if(!empty($errors['assigned'])): ?>
				  <small class="text-danger"><?=$errors['assigned']?></small>
				<?php endif ?>
			</div>

			<div class="col-md-12  transaction_status">
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
	 
			<div class="d-flex justify-content-between mb-5" style="margin-top:10px;">
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

<?php else: ?>
	<div class="alert alert-danger text-center">
		Access denied. You don’t have permission for this action.
	</div>
<?php endif ?>

<script>
	const networkOptions = {
		"E-cash": [
			{ value: "", text: "---Select---" },
			{ value: "at", text: "at" },
			{ value: "Ghanapay", text: "Ghanapay" },
			{ value: "G-money", text: "G-money" },
			{ value: "mtn agent", text: "mtn agent" },
			{ value: "mtn momopay", text: "mtn momopay" },
			{ value: "telecel mpesa", text: "telecel mpesa" },
			{ value: "telecel float", text: "telecel float" },
			{ value: "Zeepay", text: "Zeepay" },
		],
		"EVD": [
			{ value: "", text: "---Select---" },
			{ value: "mtn", text: "mtn" },
			{ value: "at", text: "at" },
			{ value: "telecel", text: "telecel" }
		],
		"sims": [
			{ value: "", text: "---Select---" },
			{ value: "mtn", text: "mtn" },
			{ value: "at", text: "at" },
			{ value: "telecel", text: "telecel" }
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
			{ value: "GCB", text: "GCB" },
			{ value: "Gtbank", text: "Gtbank" },
		],
		"Ezwich": [
			//{ value: "", text: "---Select---" },
			{ value: "ezwich", text: "ezwich" },
			/*{ value: "Access Bank", text: "Access Bank" },
			{ value: "ADB", text: "ADB" },
			{ value: "Cal bank", text: "Cal bank" },
			{ value: "Ecobank", text: "Ecobank" },
			{ value: "FBN", text: "FBN" },
			{ value: "Fidelity", text: "Fidelity" },
			{ value: "GCB", text: "GCB" },
			{ value: "Gtbank", text: "Gtbank" },
			{ value: "NIB", text: "NIB" },
			{ value: "Zenit bank", text: "Zenit bank" },*/
		]
	};
	const walletType = document.getElementById('wallet_type');
	const networkSelect = document.getElementById('network');

	function updateNetworkOptions() {
		const walletTypeValue = walletType.value;
		networkSelect.innerHTML = ""; // Clear current options

		// Populate options based on selected transaction type
		if (networkOptions[walletTypeValue]) {
			networkOptions[walletTypeValue].forEach(option => {
				const opt = document.createElement('option');
				opt.value = option.value;
				opt.textContent = option.text;
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

