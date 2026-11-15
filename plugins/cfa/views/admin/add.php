<?php if(user_can('add_cfa')): ?>
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
			<center class="text-center mb-3">
				<h4>
					<span><img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:60px;max-height: 60px;object-fit: cover;"></span>
					New Exchange
				</h4>
				<small class="text-warning">Current rate: CFA1,000 = GH&cent;<?=$settings->cfa_rate ?? 0?></small>
			</center>

			<div class="mb-3 col-md-12 col-lg-6 transaction_type">
			  <label for="transaction_type" class="form-label">Transaction type</label>
			  <select class="form-select transaction_type" name="transaction_type" id="transaction_type">
				  <option value="">---Select---</option>
				  <option <?=old_select('transaction_type','CFA to GH₵')?> value="CFA to GH₵">CFA to GH₵</option>
				  <option <?=old_select('transaction_type','GH₵ to CFA')?> value="GH₵ to CFA">GH₵ to CFA</option>
			  </select>
				
				<?php if(!empty($errors['transaction_type'])): ?>
				  <small class="text-danger"><?=$errors['transaction_type']?></small>
				<?php endif ?>
			</div>

			<div class="mb-3 col-md-12 col-lg-6 amount_cfa">
			  <label for="amount_cfa" class="form-label">Amount in CFA</label>
			  <div class="input-group">
			  	<div id="currencySymbol" class="input-group-text">CFA</div>
			  	<input name="amount_cfa" value="<?=old_value('amount_cfa')?>" type="text" class="form-control" id="amount_cfa" placeholder="Enter CFA value">
			  	<input name="cfa_rate" value="<?=old_value('amount_cfa', $settings->cfa_rate ?? 0)?>" type="text" class="form-control d-none" id="amount_cfa" placeholder="Enter CFA rate">
			  </div>				
				<?php if(!empty($errors['amount_cfa'])): ?>
				  <small class="text-danger"><?=$errors['amount_cfa']?></small>
				<?php endif ?>
			</div>

			<div class="mb-3 col-md-12 col-lg-6 amount_ghs">
			  <label for="amount_ghs" class="form-label">Amount in GH₵</label>
			  <div class="input-group">
			  	<div id="currencySymbol" class="input-group-text">GH₵</div>
			  	<input name="amount_ghs" value="<?=old_value('amount_ghs')?>" type="text" class="form-control" id="amount_ghs" placeholder="Enter GH&cent; value">
			  </div>				
				<?php if(!empty($errors['amount_ghs'])): ?>
				  <small class="text-danger"><?=$errors['amount_ghs']?></small>
				<?php endif ?>
			</div>

			<div class="mb-3 col-md-12 col-lg-6 wallet_in_id">
			  <label for="wallet_in_id" class="form-label">Receiving wallet</label>
			  <select class="form-select id_type" name="wallet_in_id" id="wallet_in_id">
				  <option value="">---Select---</option>
				  <?php if(!empty($wallets)):?>
				  	<?php foreach ($wallets as $wallet): ?>			  		
				  		<option <?=old_select('wallet_in_id', $wallet->id)?> balance="<?=$wallet->balance ?? 0?>" value="<?=$wallet->id?>"><?=$wallet->name . ' ' . $wallet->network ?? ''?> [<?=number_format($wallet->balance ?? 0, 2)?>]</option>
				  	<?php endforeach ?>
				  <?php endif;?>
			  </select>			
				<?php if(!empty($errors['wallet_in_id'])):?>
				  <small class="text-danger js-wallet-out-error"><?=$errors['wallet_in_id']?></small>
				<?php endif?>
			</div>

			<div class="mb-3 col-md-12 col-lg-6 wallet_out_id">
			  <label for="wallet_out_id" class="form-label">Paying wallet</label>
			  <select class="form-select id_type" name="wallet_out_id" id="wallet_out_id">
				  <option value="">---Select---</option>
				  <?php if(!empty($wallets)):?>
				  	<?php foreach ($wallets as $wallet): ?>			  		
				  		<option <?=old_select('wallet_out_id', $wallet->id)?> balance="<?=$wallet->balance ?? 0?>" value="<?=$wallet->id?>"><?=$wallet->name . ' ' . $wallet->network ?? ''?> [<?=number_format($wallet->balance ?? 0, 2)?>]</option>
				  	<?php endforeach ?>
				  <?php endif;?>
			  </select>			
				<?php if(!empty($errors['wallet_out_id'])):?>
				  <small class="text-danger js-wallet-out-error"><?=$errors['wallet_out_id']?></small>
				<?php endif?>
			</div>

			<div class="mb-3 col-md-12 col-lg-6 transaction_status">
			  <label for="transaction_status" class="form-label">Status</label>
			  <select class="form-select transaction_status" name="transaction_status">
				  <option value="">---Select---</option>
				  <option <?=old_select('transaction_status','Draft')?> value="Draft">Draft</option>
				  <option <?=old_select('transaction_status','Pending')?> value="Pending">Pending</option>
				  <?php if(user_can('approve_transactions')):?>
				  <option <?=old_select('transaction_status','Rejected')?> value="Rejected">Rejected</option>
				  <option <?=old_select('transaction_status','Complete')?> value="Complete">Complete</option>
				<?php endif;?>
			  </select>
				
				<?php if(!empty($errors['transaction_status'])): ?>
				  <small class="text-danger"><?=$errors['transaction_status']?></small>
				<?php endif ?>
			</div>
				
			<div class="mb-3 col-md-12 col-lg-6 input-group">
			  <label for="description" class="input-group-text">Description</label>
				<textarea rows="2" id="description" class="form-control" name="description"  placeholder="Write a brief description for this transaction"><?=old_value('description')?></textarea>			
				<?php if(!empty($errors['description'])):?>
				  <small class="text-danger w-100"><?=$errors['description']?></small>
				<?php endif?>
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

	const transactionType = document.getElementById('transaction_type'); // Corrected ID selection
	const transactionTypeValue = transactionType.value;
	const amountCfaInput = document.getElementById('amount_cfa');
	const amountGhsInput = document.getElementById('amount_ghs');
	const cfa_rate = <?=$settings->cfa_rate ?? 0?>;
		amountGhsInput.value = (0).toFixed(2); 
		amountCfaInput.value = (0).toFixed(2); 

	function calculateExchangeValue() {
		
		amountCfaInput.addEventListener('input', function(){

			console.log('Cfa amount entered');
				if (!isNaN(amountCfaInput.value)) {
					amountGhsInput.value = (amountCfaInput.value * (cfa_rate/1000)).toFixed(2); 
				}else{
					amountGhsInput.value = 0;
				}
			});

		amountGhsInput.addEventListener('input', function(){
			console.log('Ghs amount entered');
			if (!isNaN(amountGhsInput.value) || amountGhsInput.value !== 0) {
				amountCfaInput.value = (amountGhsInput.value * (1000/cfa_rate)).toFixed(2); 
			}else{
				amountCfaInput.value = 0;
			}
		}); 

		amountCfaInput.value = amountCfaInput.value;
		amountGhsInput.value = amountGhsInput.value;
	}

	// Initialize on page load
	document.addEventListener("DOMContentLoaded", function() {
		calculateExchangeValue();
	});

	// Event listeners for real-time updates
	amount_cfa.addEventListener("input", function(){
		calculateExchangeValue();
	});
	amount_ghs.addEventListener("input", function(){
		calculateExchangeValue();
	});
</script>