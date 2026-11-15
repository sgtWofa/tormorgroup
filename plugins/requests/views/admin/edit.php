<?php if(user_can('edit_request')): ?>
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
		<div class="row g-3 col-sm-12 col-md-8 col-lg-6 mx-auto shadow p-4 rounded">
			
			<?=csrf();?>

			<h4 class="text-center">
				<span><img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:60px;max-height: 60px;object-fit: cover;"></span>
				Update Request
			</h4>
	 
			<div class="mb-3 col-md-12 ">
				<label for="network" class="form-label">Request option</label>
				<select class="form-select" name="network" id="network" onchange="updateRequestOptions()" disabled>	
				  <option value="">---Select---</option>
				  <option <?=old_select('network','BANK', $row->network ?? '')?> value="BANK">BANK</option>
				  <option <?=old_select('network','CASH', $row->network ?? '')?> value="CASH">CASH</option>
				  <option <?=old_select('network','E-CASH', $row->network ?? '')?> value="E-CASH">E-CASH</option>
				  <option <?=old_select('network','EVD', $row->network ?? '')?> value="EVD">EVD</option>
				</select>
				<?php if(!empty($errors['network'])): ?>
				  <small class="text-danger"><?=$errors['network']?></small>
				<?php endif ?>
			</div>
	 
			<div class="mb-3 col-md-12 ">
				<label for="request_type" class="form-label">Request type</label>
				<select class="form-select request_type" id="request_type" name="request_type" disabled>
					<!-- Options will be displayed dynamically -->
				</select>
				<?php if(!empty($errors['request_type'])): ?>
				  <small class="text-danger"><?=$errors['request_type']?></small>
				<?php endif ?>
			</div>

		    <div class="mb-3 col-md-12">
		      <label for="amount" class="form-label">Amount </label>
		      <div class="input-group">
		        <div id="currencySymbol" class="input-group-text">GH₵</div>
		        <input name="amount" value="<?=old_value('amount', $row->amount ?? 0.00)?>" type="number" step="0.10" class="form-control" id="amount" placeholder="GH&cent;0.00" disabled>
		      </div>
		    </div>
		    <div class="mb-3 col-md-12 wallet_number">
		      <label for="wallet_number" class="form-label">Receiving account</label>
		      <select class="form-select wallet_number" name="wallet_number" disabled>
				  <option value="">---Select---</option>
				  <?php if(!empty($mywallets)):?>
				  	<?php foreach ($mywallets as $mywallet): ?>				  		
				  		<option <?=old_select('wallet_number',$mywallet->id, $row->wallet_number ?? '')?> value="<?=$mywallet->id ?? ' ' ?>"><?=$mywallet->wallet_type . ' '. $mywallet->network ?? ''?> (<?=$mywallet->wallet_number ?? ''?>)</option>
				  	<?php endforeach;?>
				  	<?php endif;?>
			  </select>
		      <?php if(!empty($errors['wallet_number'])):?>
		        <small class="text-danger"><?=$errors['wallet_number']?></small>
		      <?php endif?>
		    </div>

		    <!-- Till/Code Input, visible for specific categories only -->
		    <div class="mb-3 col-md-12 till_number d-none" id="till_number">
		      <label for="till_number" class="form-label">Till/Code</label>
		      <input name="till_number" value="<?=old_value('till_number', $row->till_number ?? '')?>" type="text" class="form-control" id="till_number" placeholder="Enter till_number number or shortcode used" disabled>
		      <?php if(!empty($errors['till_number'])):?>
		        <small class="text-danger"><?=$errors['till_number']?></small>
		      <?php endif?>
		    </div>

			<div class="mb-3 col-md-12  assigned">
			  <label for="assigned" class="form-label">Assigned to <small>(who is the request going to?):</small></label>
			  <select class="form-select assigned" name="assigned" disabled>
				  <option value="">---Select---</option>
				  <?php if(!empty($users)):?>
				  	<?php foreach ($users as $user): ?>				  		
				  		<option <?=old_select('assigned',$user->id , $row->assigned ?? '')?> value="<?=$user->id ?? ' ' ?>"><?=$user->first_name . ' '. $user->last_name ?? ''?>  (<?=$user->roles[0] ?? ''?>)</option>
				  	<?php endforeach;?>
				  	<?php endif;?>
			  </select>
			  	<?php if(!empty($errors['assigned'])): ?>
				  <small class="text-danger"><?=$errors['assigned']?></small>
				<?php endif ?>
			</div>
				
			<div class="mb-3 col-12">
			  <label for="description" class="form-label">Description</label>
				<textarea  id="description" class="form-control" name="description"  placeholder="Write a brief description for this request: if you are requesting for sims, indicate the quantity you want here. "><?=old_value('description', $row->description ?? '')?></textarea>
			
				<?php if(!empty($errors['description'])):?>
				  <small class="text-danger"><?=$errors['description']?></small>
				<?php endif?>
			</div>

			<div class="mb-3 col-md-12  transaction_status">
			  <label for="transaction_status" class="form-label">Status</label>
			  <select class="form-select transaction_status" name="transaction_status">
				  <option value="">---Select---</option>
				  <?php if ($row->created_by == $session->id): ?>
					  <option <?=old_select('transaction_status','Draft', $row->transaction_status ??'')?> value="Draft">Draft</option>
					  <option <?=old_select('transaction_status','Pending', $row->transaction_status ??'')?> value="Pending">Pending</option>
					<?php else:?>
					  <option <?=old_select('transaction_status','Rejected', $row->transaction_status ??'')?> value="Rejected">Rejected</option>
					  <option <?=old_select('transaction_status','Approved', $row->transaction_status ??'')?> value="Approved">Approved</option>
				  <?php endif ?>
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

<?php else: ?>
	<div class="alert alert-danger text-center">
		Access denied. You don’t have permission for this action.
	</div>
<?php endif ?>

<script>
	const networkOptions = {
		"CASH": [
			{ value: "", text: "---Select---" },
			{ value: "GHS", text: "GHS" },
			{ value: "CFA", text: "CFA" }
		],
		"E-CASH": [
			{ value: "", text: "---Select---" },
			{ value: "AT", text: "AT" },
			{ value: "G-MONEY", text: "G-MONEY" },
			{ value: "MTN", text: "MTN" },
			{ value: "TELECEL", text: "TELECEL" },
			{ value: "ZEEPAY", text: "ZEEPAY" },
			{ value: "GHANAPAY", text: "GHANAPAY" },
		],
		"EVD": [
			{ value: "", text: "---Select---" },
			{ value: "AT", text: "AT" },
			{ value: "MTN", text: "MTN" },
			{ value: "TELECEL", text: "TELECEL" },
		],
		"BANK": [
			{ value: "CALBANK", text: "CALBANK" },
		],
	};

const requestType = document.getElementById('request_type');
const tillNumber = document.getElementById('till_number');
const networkSelect = document.getElementById('network');

// Ensure the PHP value is properly quoted
const existingRequestType = "<?=addslashes($row->request_type ?? '')?>"; // Wrap the value in quotes

function updateRequestOptions() {
    const networkSelectValue = networkSelect.value;
    requestType.innerHTML = ""; // Clear current options

    // Populate options based on selected transaction type
    if (networkOptions[networkSelectValue]) {
        networkOptions[networkSelectValue].forEach(option => {
            const opt = document.createElement('option');
            opt.value = option.value;
            opt.textContent = option.text;

            // Pre-select the option if it matches the existing request type
            if (option.value === existingRequestType) {
                opt.selected = true;
            }
            requestType.appendChild(opt);
        });
        showFields();
    } 
}

function showFields() {
    const requestTypeValue = requestType.value;
    tillNumber.classList.toggle('d-none', requestTypeValue !== 'float');
}

// Initialize on page load
document.addEventListener("DOMContentLoaded", function () {
    updateRequestOptions();
    showFields();
});

// Event listeners for real-time updates
requestType.addEventListener("change", showFields);
networkSelect.addEventListener("change", updateRequestOptions);

</script>
