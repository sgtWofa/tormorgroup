<?php
	require_once plugin_path('helpers/transactions-helper.php');
	$currentStatus = sim_normalize_status($row->transaction_status ?? '', 'Unassigned');
	$currentStatusLower = strtolower($currentStatus);
	$isAccountingStatus = sim_status_has_accounting_effect($currentStatus);
	$isReversedStatus = sim_status_is_reversed($currentStatus);
?>
<?php if(user_can('edit_sim') && !$isReversedStatus): ?>
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
				Edit Sim
			</h4>

			<?php if($isAccountingStatus):?>
				<div class="alert alert-warning d-flex align-items-center gap-2">
					<i class="fa-solid fa-lock"></i>
					<span>This SIM transaction is already approved/sold. Only the status can be changed to Declined or Rejected, with a rejection reason.</span>
				</div>
			<?php endif;?>
	 
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

			<div class="mb-1 col-md-12">
			  <label class="form-label">SIM Image</label>
			  <div class="d-flex align-items-center gap-3">
				<img src="<?=get_image($row->image ?? '')?>" class="rounded" style="width:64px;height:64px;object-fit:cover;">
				<label class="btn btn-outline-secondary mb-0">
				  Change image
				  <input type="file" name="image" accept="image/*" class="d-none">
				</label>
			  </div>
			  <?php if(!empty($errors['image'])): ?>
				<small class="text-danger"><?=$errors['image']?></small>
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
			  	<input name="wholesale_price" value="<?=old_value('wholesale_price' , number_format($row->wholesale_price ?? 0, 2))?>" type="text" class="form-control" id="wholesale_price" placeholder="GH&cent;0.00" <?=$isAccountingStatus ? 'readonly' : ''?>>
			  </div>				
				<?php if(!empty($errors['wholesale_price'])): ?>
				  <small class="text-danger"><?=$errors['wholesale_price']?></small>
				<?php endif ?>
			</div>

			<div class="mb-1 col-md-12 ">
			  <label for="retail_price" class="form-label">Retail price </label>
			  <div class="input-group">
			  	<div id="currencySymbol" class="input-group-text">GH₵</div>
			  	<input name="retail_price" value="<?=old_value('retail_price', number_format($row->retail_price ?? 0, 2))?>" type="text" class="form-control" id="retail_price" placeholder="GH&cent;0.00" <?=$isAccountingStatus ? 'readonly' : ''?>>
			  </div>				
				<?php if(!empty($errors['retail_price'])): ?>
				  <small class="text-danger"><?=$errors['retail_price']?></small>
				<?php endif ?>
			</div>

			<?php if(user_can('approve_transactions')):?>
			<div class="mb-1 col-md-12  transaction_status">
			  <label for="transaction_status" class="form-label">Sim Status</label>
			  <select class="form-select transaction_status" id="transaction_status" name="transaction_status" onchange="showFields()">
				  <?php if($isAccountingStatus):?>
					  <option <?=old_select('transaction_status',$currentStatus, $row->transaction_status ?? '')?> value="<?=$currentStatus?>"><?=$currentStatus?></option>
					  <option <?=old_select('transaction_status','Declined', $row->transaction_status ?? '')?> value="Declined">Declined</option>
					  <option <?=old_select('transaction_status','Rejected', $row->transaction_status ?? '')?> value="Rejected">Rejected</option>
				  <?php else:?>
					  <option value="">---Select---</option>
					  <option <?=old_select('transaction_status','Unassigned', $row->transaction_status ?? '')?> value="Unassigned">Unassigned</option>
					  <option <?=old_select('transaction_status','Assigned', $row->transaction_status ?? '')?> value="Assigned">Assigned</option>
					  <option <?=old_select('transaction_status','Expired', $row->transaction_status ?? '')?> value="Expired">Expired</option>
					  <option <?=old_select('transaction_status','Declined', $row->transaction_status ?? '')?> value="Declined">Declined</option>
					  <option <?=old_select('transaction_status','Rejected', $row->transaction_status ?? '')?> value="Rejected">Rejected</option>
				  <?php endif;?>
			  </select>				
				<?php if(!empty($errors['transaction_status'])): ?>
				  <small class="text-danger"><?=$errors['transaction_status']?></small>
				<?php endif ?>
			</div>

			<div class="mb-1 col-md-12 <?= in_array(strtolower((string)old_value('transaction_status', $currentStatus)), ['declined','rejected'], true) ? '' : 'd-none' ?>" id="simRejectionReasonGroup">
			  <label for="rejection_reason" class="form-label">Rejection reason</label>
			  <textarea rows="3" class="form-control" id="rejection_reason" name="rejection_reason" placeholder="State clearly why this SIM transaction is being declined or rejected."><?=old_value('rejection_reason', $row->rejection_reason ?? '')?></textarea>
				<?php if(!empty($errors['rejection_reason'])): ?>
				  <small class="text-danger"><?=$errors['rejection_reason']?></small>
				<?php endif ?>
				<small class="text-danger js-rejection-reason-error"></small>
			</div>
			<?php endif;?>
			
			<?php if(user_can('add_sim')):?>
			<div class="mb-1 col-md-12 ">
			  <label for="assigned" class="form-label">In charge</label>
			  <select class="form-select assigned" name="assigned" <?=$isAccountingStatus ? 'disabled' : ''?>>
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
			  	<input name="buyer_phone" value="<?=old_value('buyer_phone', $row->buyer_phone ?? '')?>" type="text" class="form-control" id="buyer_phone" placeholder="Enter buyer's phone" <?=$isAccountingStatus ? 'readonly' : ''?>>
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
		<?= $isReversedStatus ? 'Declined or rejected SIM transactions can no longer be edited.' : 'Access denied. You do not have permission for this action.' ?>
	</div>
<?php endif ?>

<script>

	const transactionStatus = document.getElementById('transaction_status'); // Corrected ID selection
	const rejectionReasonGroup = document.getElementById('simRejectionReasonGroup');
	const rejectionReasonInput = document.getElementById('rejection_reason');
	const rejectionReasonError = document.querySelector('.js-rejection-reason-error');
	const saveButton = document.querySelector('form button[type="submit"]');
	const isAccountingStatus = <?= $isAccountingStatus ? 'true' : 'false' ?>;

	function showFields() {
		const assignedSelect = document.querySelector('.assigned');
		const buyerPhone = document.querySelector('.buyer_phone');
		if (!transactionStatus) return;

		//assignedSelect.classList.add('d-none');
		if (buyerPhone) buyerPhone.classList.add('d-none');

		// Show fields based on selected category for Withdrawal
		if (transactionStatus.value === 'Assigned' && assignedSelect) {
			assignedSelect.classList.remove('d-none');
		}else
		if (transactionStatus.value === 'Sold - wholesale' || transactionStatus.value === 'Sold - retail') {
			if (buyerPhone) buyerPhone.classList.remove('d-none');
		}
		syncRejectionReasonState();
	}

	function statusNeedsRejectionReason(){
		const status = String(transactionStatus?.value || '').trim().toLowerCase();
		return status === 'declined' || status === 'rejected';
	}

	function syncRejectionReasonState(){
		const needsReason = statusNeedsRejectionReason();
		const hasReason = String(rejectionReasonInput?.value || '').trim() !== '';
		if(rejectionReasonGroup){
			rejectionReasonGroup.classList.toggle('d-none', !needsReason);
		}
		if(rejectionReasonInput){
			rejectionReasonInput.toggleAttribute('required', needsReason);
		}
		if(rejectionReasonError){
			rejectionReasonError.textContent = (needsReason && !hasReason) ? 'Rejection reason is required.' : '';
		}
		const canSave = isAccountingStatus ? (needsReason && hasReason) : (!needsReason || hasReason);
		if(saveButton){
			saveButton.disabled = !canSave;
			saveButton.classList.toggle('disabled', !canSave);
		}
		return canSave;
	}

	// Initialize on page load
	document.addEventListener("DOMContentLoaded", function() {
		showFields();
		syncRejectionReasonState();
	});

	// Event listeners for real-time updates
	if(transactionStatus){
		transactionStatus.addEventListener("change", showFields);
	}
	if(rejectionReasonInput){
		rejectionReasonInput.addEventListener('input', syncRejectionReasonState);
	}
	const form = document.querySelector('form');
	if(form){
		form.addEventListener('submit', function(event){
			if(!syncRejectionReasonState()){
				event.preventDefault();
			}
		});
	}
</script>
