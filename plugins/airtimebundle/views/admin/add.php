<?php if(user_can('add_momoremit')):?>
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

    <!-- Transaction Type Selection -->
    <div class="mb-3 col-md-12 col-lg-6">
      <label for="transaction_type" class="form-label">Transaction type</label>
      <select class="form-select" id="transaction_type" name="transaction_type">
        <option value="">---Select---</option>
        <option <?=old_select('transaction_type','Sending')?> value="Sending">Sending</option>
        <option <?=old_select('transaction_type','Deposit')?> value="Deposit">Deposit</option>
        <option <?=old_select('transaction_type','Withdrawal')?> value="Withdrawal">Withdrawal</option>
        <option <?=old_select('transaction_type','CIT')?> value="CIT">CIT</option>
      </select>
      <?php if(!empty($errors['transaction_type'])):?>
        <small class="text-danger"><?=$errors['transaction_type']?></small>
      <?php endif?>
    </div>
 
		<!-- Network Selection -->
    <div class="mb-3 col-md-12 col-lg-6">
      <label for="network" class="form-label">Select Network</label>
      <select class="form-select" name="network">
        <option value="">---Select---</option>
        <option <?=old_select('network','mtn')?> value="mtn">mtn</option>
        <option <?=old_select('network','telecel')?> value="telecel">telecel</option>
        <option <?=old_select('network','at')?> value="at">at</option>
        <option <?=old_select('network','zeepay')?> value="zeepay">zeepay</option>
        <option <?=old_select('network','Ghanapay')?> value="Ghanapay">Ghanapay</option>
        <option <?=old_select('network','G-money')?> value="G-money">G-money</option>
      </select>
      <?php if(!empty($errors['network'])):?>
        <small class="text-danger"><?=$errors['network']?></small>
      <?php endif?>
    </div>

    <!-- Category Selection, dynamic options populated by JavaScript -->
    <div class="mb-3 col-md-12 col-lg-6 category d-none">
      <label for="category" class="form-label">Category</label>
      <select class="form-select" name="category" id="category"></select>
      <?php if(!empty($errors['category'])):?>
        <small class="text-danger"><?=$errors['category']?></small>
      <?php endif?>
    </div>

    <!-- Phone Input -->
    <div class="mb-3 col-md-12 col-lg-6">
      <label for="phone" class="form-label">Phone</label>
      <input name="phone" value="<?=old_value('phone')?>" type="text" class="form-control" id="phone" placeholder="Enter phone number">
      <?php if(!empty($errors['phone'])):?>
        <small class="text-danger"><?=$errors['phone']?></small>
      <?php endif?>
    </div>

    <!-- Token Number Input, visible for specific categories only -->
    <div class="mb-3 col-md-12 col-lg-6 token d-none">
      <label for="token" class="form-label">Token number</label>
      <input name="token" value="<?=old_value('token')?>" type="text" class="form-control" id="token" placeholder="Enter token number">
      <?php if(!empty($errors['token'])):?>
        <small class="text-danger"><?=$errors['token']?></small>
      <?php endif?>
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

    <!-- Charge Display (dynamically calculated) -->
    <div class="mb-3 col-md-12 col-lg-6">
      <label for="charge" class="form-label">Charge </label>
      <div class="input-group">
        <div id="currencySymbol" class="input-group-text">GH₵</div>
        <input name="charge" value="<?=old_value('charge')?>" type="text" class="form-control" id="charge" placeholder="GH&cent;0.00" readonly>
      </div>
    </div>

		<div class="mb-3 col-md-12 col-lg-6 wallet_in_id">
		  <label for="wallet_in_id" class="form-label">Receiving Wallet</label>
		  <select class="form-select id_type" name="wallet_in_id" id="wallet_in_id">
			  <option value="">---Select---</option>
			  <?php if(!empty($wallets)):?>
			  	<?php foreach ($wallets as $wallet): ?>			  		
			  		<option <?=old_select('wallet_in_id', $wallet->id, $row->wallet_in_id ?? '')?> value="<?=$wallet->id?>"><?=$wallet->name . ' ' . $wallet->network ?? ''?> [<?=number_format($wallet->balance ?? 0, 2)?>]</option>
			  	<?php endforeach ?>
			  <?php endif;?>
		  </select>			
			<?php if(!empty($errors['wallet_in_id'])):?>
			  <small class="text-danger"><?=$errors['wallet_in_id']?></small>
			<?php endif?>
		</div>

		<div class="mb-3 col-md-12 col-lg-6 wallet_out_id">
		  <label for="wallet_out_id" class="form-label">Paying Wallet</label>
		  <select class="form-select id_type" name="wallet_out_id" id="wallet_out_id">
			  <option value="">---Select---</option>
			  <?php if(!empty($wallets)):?>
			  	<?php foreach ($wallets as $wallet): ?>			  		
			  		<option <?=old_select('wallet_out_id', $wallet->id, $row->wallet_out_id ?? '')?> value="<?=$wallet->id?>"><?=$wallet->name . ' ' . $wallet->network ?? ''?> [<?=number_format($wallet->balance ?? 0, 2)?>]</option>
			  	<?php endforeach ?>
			  <?php endif;?>
		  </select>			
			<?php if(!empty($errors['wallet_out_id'])):?>
			  <small class="text-danger js-wallet-out-error"><?=$errors['wallet_out_id']?></small>
			<?php endif?>
		</div>

		<div class="mb-3 col-md-12 col-lg-6 token d-none">
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

	    <div class="mb-3 col-md-12 col-lg-6 token d-none">
	      <label for="id_number" class="form-label">ID number</label>
	      <input name="id_number" value="<?=old_value('id_number')?>" type="text" class="form-control" id="id_number" placeholder="Enter ID number here">
	    </div>

	    <div class="mb-3 col-md-12 col-lg-6 token receiver d-none">
	      <label for="receiver" class="form-label">Receiver's phone</label>
	      <input name="receiver" value="<?=old_value('receiver')?>" type="text" class="form-control" id="receiver" placeholder="Enter receiver's phone number">
	    </div>

		<div class="mb-3 col-md-12 col-lg-6 token d-none">
		  <label for="secret_code" class="form-label">Secret code</label>
		  <input name="secret_code" value="<?=old_value('secret_code')?>" type="text" class="form-control" id="secret_code" placeholder="Enter ID number here">
			
			<?php if(!empty($errors['secret_code'])):?>
			  <small class="text-danger"><?=$errors['secret_code']?></small>
			<?php endif?>
		</div>
 
		<div class="d-flex justify-content-between" style="margin-top:10px;">
			<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
				<button type="button" class="btn btn-outline-primary" style="border:none;">
					<i class="fa-solid fa-chevron-left"></i> Back
				</button>
			</a>
			<button class="btn btn-outline-danger" style="border:none;">
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

<!-- JavaScript: Calculate Charges, Validate Wallet, Show Fields -->
<script>
  document.addEventListener("DOMContentLoaded", function () {
    const countInputs = document.querySelectorAll(".count-input");
    const totalCountField = document.getElementById("total-count");
    const totalVolumeField = document.getElementById("total-volume");
    const submitBtn = document.querySelector('.submit');
    const staticBackdropModal = new bootstrap.Modal(document.getElementById('staticBackdrop'));
    const amountInput = document.getElementById('amount');
    const chargeInput = document.getElementById('charge');
    const walletOutSelect = document.getElementById('wallet_out_id');
    const walletOutError = document.querySelector('.js-wallet-out-error');

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

    amountInput.addEventListener('input', () => { calculateCharge(); });

    // Dynamic Field Display Based on Transaction Type and Category
    const categoryOptions = {
      "Sending": [
        { value: "", text: "---Select---" },
        { value: "amount-charge", text: "amount-charge" },
        { value: "amount+charge", text: "amount+charge" },
        { value: "amount+in & out", text: "amount+in & out" }
      ],
      "Withdrawal": [
        { value: "", text: "---Select---" },
        { value: "self", text: "self" },
        { value: "3rd party", text: "3rd party" },
        { value: "token", text: "token" }
      ]
    };

  const transactionType = document.getElementById('transaction_type');
  const categorySelect = document.getElementById('category');
  const categoryDiv = document.querySelector('.category');
  const tokenFields = document.querySelectorAll('.token');
  const receiver = document.querySelector('.receiver');

  // Event listener to call updateCategoryOptions when transaction type changes
  transactionType.addEventListener("change", updateCategoryOptions);

  function updateCategoryOptions() {
    const transactionTypeValue = transactionType.value;
    categorySelect.innerHTML = "";
    if (categoryOptions[transactionTypeValue]) {
      categoryDiv.classList.remove('d-none');
      categoryOptions[transactionTypeValue].forEach(option => {
        const opt = document.createElement('option');
        opt.value = option.value;
        opt.textContent = option.text;
        categorySelect.appendChild(opt);
      });
    } else {
      categoryDiv.classList.add('d-none');
    }
    showFields();
  }

    function showFields() {
      const transactionTypeValue = transactionType.value;
      const categoryValue = categorySelect.value;
      tokenFields.forEach(field => field.classList.add('d-none'));
      receiver.classList.add('d-none');

      if (transactionTypeValue === 'Withdrawal') {
        if (categoryValue === 'token') {
          tokenFields.forEach(field => field.classList.remove('d-none'));
          receiver.classList.remove('d-none');
        } else if (categoryValue === '3rd party') {
          receiver.classList.remove('d-none');
        }
      }
    }

    categorySelect.addEventListener("change", showFields);
  });
</script>


<?php else:?>
	<div class="alert alert-danger text-center">
		Access denied. You dont have permission for this action
	</div>
<?php endif?>

