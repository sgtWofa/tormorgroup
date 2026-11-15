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
		<div class="row col-9 col-lg-5 mx-auto shadow p-2 rounded">
			
			<?=csrf()?>

			<h4 class="text-center">
				<span><img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:60px;max-height: 60px;object-fit: cover;"></span>
				Withdraw Funds
			</h4>

			<div class="mb-1 col-12 usn">
			  <label for="wallet_out_id" class="form-label">From</label>
			  <div class="form-control disabled"><?=$row->name ?? '' ?> <?=$row->network ?? '' ?>  (<?=$row->wallet_number ?? ''?>)</div>
			  <input name="wallet_out_id" value="<?=old_value('wallet_out_id', $row->id)?>" type="text" class="form-control d-none" id="wallet_out_id" placeholder="" >				
				<?php if(!empty($errors['wallet_out_id'])): ?>
				  <small class="text-danger"><?=$errors['wallet_out_id']?></small>
				<?php endif ?>
			</div>

			<div class="mb-1 col-12">
			  <label for="amount" class="form-label">Amount</label>
			  <div class="input-group">
			  	<div id="currencySymbol" class="input-group-text">GH₵</div>
			  	<input name="amount" value="<?=old_value('amount')?>" type="text" class="form-control" id="amount" placeholder="GH&cent;0.00">
			  </div>				
				<?php if(!empty($errors['amount'])): ?>
				  <small class="text-danger"><?=$errors['amount']?></small>
				<?php endif ?>
			</div>

			<div class="mb-1 col-12">
			  <label for="balance" class="form-label">Balance</label>
			  <div class="input-group">
			  	<div id="currencySymbol" class="input-group-text">GH₵</div>
			  	<input name="balance" value="<?=old_value('balance')?>" type="text" class="form-control" id="balance" placeholder="GH&cent;0.00">
			  </div>				
				<?php if(!empty($errors['balance'])): ?>
				  <small class="text-danger"><?=$errors['balance']?></small>
				<?php endif ?>
			</div>
				
			<div class="mb-3 col-12">
			  <label for="description" class="form-label">Description/Narration</label>
				<textarea rows="2" id="description" class="form-control" name="description"  placeholder="Write a brief description for this transaction"><?=old_value('description')?></textarea>			
				<?php if(!empty($errors['description'])):?>
				  <small class="text-danger"><?=$errors['description']?></small>
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

	function calculateBalance() {
		const amountInput = document.getElementById('amount');
		const balanceInput = document.getElementById('balance');
		const amount = parseFloat(amountInput.value);
		const oldBalance = <?=$row->balance ?? 0?>;

		if (!isNaN(amount)) {
				balanceInput.value = (oldBalance - amount).toFixed(2); // 1% of amount
		} else {
			balanceInput.value = oldBalance.toFixed(2); // Clear charge if amount is not a valid number
		}
	}	

	function validateWallet() {
		const amountInput = document.getElementById('amount');
		const amountInputError = document.querySelector('.js-amount-error');
		const amount = parseFloat(amountInput.value);
		const oldBalance = <?=$row->balance ?? 0?>;

		if (!isNaN(amount) && !isNaN(oldBalance)) {
			if (amount > oldBalance) {
				amountInputError.innerHTML = "Amount cannot be greater than paying wallet's balance";
				return;
			}
		}
	}

	// Initialize on page load
	document.addEventListener("DOMContentLoaded", function() {
		calculateBalance();
		validateWallet();
	});

	// Event listeners for real-time updates
	document.getElementById('amount').addEventListener('input', calculateBalance);
	document.getElementById('amount').addEventListener('input', validateWallet);

</script>
