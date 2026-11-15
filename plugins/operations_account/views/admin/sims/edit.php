<?php if(user_can('edit_sim')): ?>
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
		<div class="row g-3 col-sm-12 col-md-8 col-lg-6 mx-auto shadow p-4 rounded">
			
			<?=csrf()?>

			<h4 class="text-center">
				<span><img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:60px;max-height: 60px;object-fit: cover;"></span>
				New Sim
			</h4>
	 
			<div class="mb-1 col-md-12 ">
				<label for="network" class="form-label">Network</label>
				<select class="form-select" name="network" id="network" disabled>	
				  <option value="">---Select---</option>
				  <option <?=old_select('network','mtn', $row->network ?? '')?> value="mtn">mtn</option>
				  <option <?=old_select('network','at', $row->network ?? '')?> value="at">at</option>
				  <option <?=old_select('network','telecel', $row->network)?> value="telecel">telecel</option>
				  <option <?=old_select('network','starLink', $row->network)?> value="starLink">starLink</option>
				</select>
				<?php if(!empty($errors['network'])): ?>
				  <small class="text-danger"><?=$errors['network']?></small>
				<?php endif ?>
			</div>

			<div class="mb-1 col-md-12  puk">
			  <label for="puk" class="form-label">PUK</label>
			  <input name="puk" value="<?=old_value('puk', $row->puk ?? '')?>" type="text" class="form-control" id="puk" placeholder="Enter puk used" disabled>				
				<?php if(!empty($errors['puk'])): ?>
				  <small class="text-danger"><?=$errors['puk']?></small>
				<?php endif ?>
			</div>

			<div class="mb-1 col-md-12  serial_number">
			  <label for="serial_number" class="form-label">Serial number</label>
			  <input name="serial_number" value="<?=old_value('serial_number', $row->serial_number ?? '')?>" type="text" class="form-control" id="serial_number" placeholder="Enter serial_number used" disabled>				
				<?php if(!empty($errors['serial_number'])): ?>
				  <small class="text-danger"><?=$errors['serial_number']?></small>
				<?php endif ?>
			</div>

			<div class="mb-1 col-md-12 ">
			  <label for="cost" class="form-label">Cost price </label>
			  <div class="input-group">
			  	<div id="currencySymbol" class="input-group-text">GH₵</div>
			  	<input name="cost" value="<?=old_value('cost', number_format($row->cost ?? 0, 2))?>" type="text" class="form-control" id="cost" placeholder="GH&cent;0.00" disabled>
			  </div>				
				<?php if(!empty($errors['cost'])): ?>
				  <small class="text-danger"><?=$errors['cost']?></small>
				<?php endif ?>
			</div>

			<div class="mb-1 col-md-12 ">
			  <label for="wholesale_price" class="form-label">Wholesale price </label>
			  <div class="input-group">
			  	<div id="currencySymbol" class="input-group-text">GH₵</div>
			  	<input name="wholesale_price" value="<?=old_value('wholesale_price' , number_format($row->wholesale_price ?? 0, 2))?>" type="text" class="form-control" id="wholesale_price" placeholder="GH&cent;0.00">
			  </div>				
				<?php if(!empty($errors['wholesale_price'])): ?>
				  <small class="text-danger"><?=$errors['wholesale_price']?></small>
				<?php endif ?>
			</div>

			<div class="mb-1 col-md-12 ">
			  <label for="retail_price" class="form-label">Retail price </label>
			  <div class="input-group">
			  	<div id="currencySymbol" class="input-group-text">GH₵</div>
			  	<input name="retail_price" value="<?=old_value('retail_price', number_format($row->retail_price ?? 0, 2))?>" type="text" class="form-control" id="retail_price" placeholder="GH&cent;0.00">
			  </div>				
				<?php if(!empty($errors['retail_price'])): ?>
				  <small class="text-danger"><?=$errors['retail_price']?></small>
				<?php endif ?>
			</div>

			<?php if(user_can('approve_transactions')):?>
			<div class="mb-1 col-md-12  transaction_status">
			  <label for="transaction_status" class="form-label">Sim Status</label>
			  <select class="form-select transaction_status" id="transaction_status" name="transaction_status" onchange="showFields()">
				  <option value="">---Select---</option>
				  <option <?=old_select('transaction_status','Unassigned', $row->transaction_status ?? '')?> value="Unassigned">Unassigned</option>
				  <option <?=old_select('transaction_status','Assigned', $row->transaction_status ?? '')?> value="Assigned">Assigned</option>
				  <option <?=old_select('transaction_status','Expired', $row->transaction_status ?? '')?> value="Expired">Expired</option>
			  </select>				
				<?php if(!empty($errors['transaction_status'])): ?>
				  <small class="text-danger"><?=$errors['transaction_status']?></small>
				<?php endif ?>
			</div>
			<?php endif;?>
			
			<?php if(user_can('add_sim')):?>
			<div class="mb-1 col-md-12 ">
			  <label for="assigned" class="form-label">In charge</label>
			  <select class="form-select assigned" name="assigned">
				  <option value="">---Select---</option>
				  <?php if(!empty($users)):?>
				  	<?php foreach ($users as $user): ?>				  		
				  		<option <?=old_select('assigned',$user->id ?? '', $row->assigned ?? '')?> value="<?=$user->id ?? ' ' ?>"><?=$user->first_name . ' '. $user->last_name ?? ''?></option>
				  	<?php endforeach;?>
				  	<?php endif;?>
			  </select>
			  	<?php if(!empty($errors['assigned'])): ?>
				  <small class="text-danger"><?=$errors['assigned']?></small>
				<?php endif ?>
			</div>
			<?php endif;?>

			<!-- <div class="mb-1 col-md-12  number_assigned">
			  <label for="number_assigned" class="form-label">Number assigned to sim</label>
			  <input name="number_assigned" value="<?=old_value('number_assigned', $row->number_assigned ?? '')?>" type="text" class="form-control" id="number_assigned" placeholder="Enter number assigned to sim">			
				<?php if(!empty($errors['number_assigned'])): ?>
				  <small class="text-danger"><?=$errors['number_assigned']?></small>
				<?php endif ?>
			</div> -->

			<div class="mb-1 col-md-12  buyer_phone">
			  <label for="buyer_phone" class="form-label">Buyer's number</label>
			  <div class="input-group">
			  	<div id="currencySymbol" class="input-group-text"><i class="fa-solid fa-phone"></i></div>
			  	<input name="buyer_phone" value="<?=old_value('buyer_phone', $row->buyer_phone ?? '')?>" type="text" class="form-control" id="buyer_phone" placeholder="Enter buyer's phone">
			  </div>				
				<?php if(!empty($errors['buyer_phone'])): ?>
				  <small class="text-danger"><?=$errors['buyer_phone']?></small>
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

<?php else: ?>
	<div class="alert alert-danger text-center">
		Access denied. You don’t have permission for this action.
	</div>
<?php endif ?>

<script>

	const transactionStatus = document.getElementById('transaction_status'); // Corrected ID selection

	function showFields() {
		const assignedSelect = document.querySelector('.assigned');
		const buyerPhone = document.querySelector('.buyer_phone');

		//assignedSelect.classList.add('d-none');
		buyerPhone.classList.add('d-none');

		// Show fields based on selected category for Withdrawal
		if (transactionStatus.value === 'Assigned') {
			assignedSelect.classList.remove('d-none');
		}else
		if (transactionStatus.value === 'Sold - wholesale' || transactionStatus.value === 'Sold - retail') {
			buyerPhone.classList.remove('d-none');
		}
	}

	// Initialize on page load
	document.addEventListener("DOMContentLoaded", function() {
		showFields();
	});

	// Event listeners for real-time updates
	transactionStatus.addEventListener("change", showFields);
</script>
