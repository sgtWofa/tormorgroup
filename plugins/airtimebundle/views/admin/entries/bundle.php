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
	<form method="post" enctype="multipart/form-data" class="mb-5">
		
	<div class="row g-2 col-md-8 col-lg-5 mx-auto shadow p-2 rounded">
		
		<?=csrf()?>

		<!-- Hidden field to store cashdrawer denominations for validation -->
		<input type="hidden" id="cashdrawerDenos" value="<?=htmlspecialchars(json_encode($cashdrawerData['denos'] ?? []), ENT_QUOTES, 'UTF-8')?>">
		<input type="hidden" id="change_denominations" name="change_denominations" value="<?=htmlspecialchars((string)old_value('change_denominations'), ENT_QUOTES, 'UTF-8')?>">
		<input type="hidden" id="change_required" name="change_required" value="<?=htmlspecialchars((string)old_value('change_required'), ENT_QUOTES, 'UTF-8')?>">
		<input type="hidden" id="cash_received_amount" name="cash_received_amount" value="<?=htmlspecialchars((string)old_value('cash_received_amount'), ENT_QUOTES, 'UTF-8')?>">

		<h4 class="text-center">
			<span><img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:60px;max-height: 60px;object-fit: cover;"></span>
			DATA BUNDLE
		</h4>

    <div class="col-md-12">
      <label for="transaction_type" class="form-label">Transaction type</label>
      <select class="form-select" id="transaction_type" name="transaction_type">
        <option <?=old_select('transaction_type','Data bundle')?> value="Data bundle" selected>Data bundle</option>
      </select>
      <?php if(!empty($errors['transaction_type'])):?>
        <small class="text-danger"><?=$errors['transaction_type']?></small>
      <?php endif?>
    </div>
 
		<div class="col-md-12">
      <label for="network" class="form-label">Network</label>
      <select class="form-select" name="network" id="network">
        <option value="" >---Select Category---</option>
        <option <?=old_select('network','MTN')?> value="MTN" >MTN</option>
        <option <?=old_select('network','AT')?> value="AT" >AT</option>
        <option <?=old_select('network','TELECEL')?> value="TELECEL" >TELECEL</option>
      </select>
	  <input type="hidden" id="oldNetwork" value="<?=htmlspecialchars((string)old_value('network'), ENT_QUOTES, 'UTF-8')?>">
      <?php if(!empty($errors['network'])):?>
        <small class="text-danger"><?=$errors['network']?></small>
      <?php endif?>
    </div>

    <div class="col-md-12">
      <label for="category" class="form-label">Category</label>
      <select class="form-select" id="category" name="category">
        <option value="" >---Select Category---</option>
      </select>
	  <input type="hidden" id="oldCategory" value="<?=htmlspecialchars((string)old_value('category'), ENT_QUOTES, 'UTF-8')?>">
      <?php if(!empty($errors['category'])):?>
        <small class="text-danger"><?=$errors['category']?></small>
      <?php endif?>
    </div>

    <div class="col-md-12">
      <label for="receiver" class="form-label">Receiver's Number</label>
      <input name="receiver" value="<?=old_value('receiver')?>" type="text" class="form-control" id="receiver" placeholder="Enter receiver number">
      <?php if(!empty($errors['receiver'])):?>
        <small class="text-danger"><?=$errors['receiver']?></small>
      <?php endif?>
    </div>

    <div class="col-md-12">
      <label for="phone" class="form-label">Walk in Customer's Number</label>
      <input name="phone" value="<?=old_value('phone')?>" type="text" class="form-control" id="phone" placeholder="Enter client's phone number">
      <?php if(!empty($errors['phone'])):?>
        <small class="text-danger"><?=$errors['phone']?></small>
      <?php endif?>
    </div>

    <div class="col-md-12 just4u-fields d-none">
			<label for="just4u_times" class="form-label">Number of times</label>
			<input type="number" step="1" min="1" class="form-control" id="just4u_times" name="just4u_times" value="<?=old_value('just4u_times') ?: '1'?>">
			<?php if(!empty($errors['just4u_times'])):?>
				<small class="text-danger"><?=$errors['just4u_times']?></small>
			<?php endif?>

			<label for="just4u_amount" class="form-label">Load/ Amount</label>
			<input type="number" step="5" min="5" class="form-control" id="just4u_amount" name="just4u_amount" value="<?=old_value('just4u_amount') ?: '5.00'?>" readonly>
			<?php if(!empty($errors['just4u_amount'])):?>
				<small class="text-danger"><?=$errors['just4u_amount']?></small>
			<?php endif?>

			<label for="just4u_charge" class="form-label mt-2">Charge</label>
			<input type="text" class="form-control" id="just4u_charge" name="just4u_charge" value="0.50" readonly>
			<label for="just4u_payable" class="form-label mt-2">Amount Payable</label>
			<input type="text" class="form-control" id="just4u_payable" name="just4u_payable" value="5.50" readonly>
			<small class="text-muted d-block mt-1" id="just4u_split_hint"></small>
			<label for="just4u_paid" class="form-label mt-2">Amount Paid</label>
			<label class="input-group" data-bs-toggle="modal" data-bs-target="#staticBackdrop">
				<i class="fa-solid fa-calculator input-group-text text-warning"></i>
				<input type="text" class="form-control" id="just4u_paid" name="just4u_paid" placeholder="Enter amount paid" value="<?=old_value('just4u_paid')?>" readonly>
				<input type="hidden" id="denos_just4u" name="denos_just4u" value="<?=htmlspecialchars((string)old_value('denos_just4u'), ENT_QUOTES, 'UTF-8')?>" />
			</label>
			<?php if(!empty($errors['just4u_paid'])):?>
				<small class="text-danger"><?=$errors['just4u_paid']?></small>
			<?php endif?>
			<small class="text-danger js-just4u-error"></small>
		</div>

		<div class="col-md-12 mashup-fields d-none">
			<label for="mashup_amount" class="form-label">Load/Amount</label>
			<input name="mashup_amount" value="<?=old_value('mashup_amount')?>" type="number" step="0.01" class="form-control" id="mashup_amount" placeholder="Enter amount">
			<?php if(!empty($errors['mashup_amount'])):?>
        <small class="text-danger"><?=$errors['mashup_amount']?></small>
      <?php endif?>
			<label for="mashup_paid" class="form-label mt-2">Amount Paid</label>
			<label class="input-group" data-bs-toggle="modal" data-bs-target="#staticBackdrop">
				<i class="fa-solid fa-calculator input-group-text text-warning"></i>
				<input type="text" class="form-control" id="mashup_paid" name="mashup_paid" placeholder="Enter amount paid" value="<?=old_value('mashup_paid')?>" readonly>
				<input type="hidden" id="denos_mashup" name="denos_mashup" value="<?=htmlspecialchars((string)old_value('denos_mashup'), ENT_QUOTES, 'UTF-8')?>" />
			</label>
			<small class="text-danger js-mashup-error"></small>
		</div>

		<div class="col-md-12 special-fields d-none">
			<label for="special_data_size" class="form-label">Data Size</label>
			<input name="special_data_size" value="<?=old_value('special_data_size')?>" type="text" class="form-control" id="special_data_size" placeholder="Enter data size">
			<?php if(!empty($errors['special_data_size'])):?>
        <small class="text-danger"><?=$errors['special_data_size']?></small>
      <?php endif?>
			<label for="special_amount_load" class="form-label mt-2">Amount/Load</label>
			<input name="special_amount_load" value="<?=old_value('special_amount_load')?>" type="number" step="0.01" class="form-control" id="special_amount_load" placeholder="Enter amount/load">
			<?php if(!empty($errors['special_amount_load'])):?>
        <small class="text-danger"><?=$errors['special_amount_load']?></small>
      <?php endif?>
			<label for="just4u_amount_payable" class="form-label mt-2 d-none js-just4u-payable-label">Amount Payable</label>
			<input name="just4u_amount_payable" value="<?=old_value('just4u_amount_payable')?>" type="text" class="form-control d-none js-just4u-payable-field" id="just4u_amount_payable" placeholder="Amount deducted from e-cash" readonly>
			<label for="special_amount_paid" class="form-label mt-2">Amount Paid</label>
			<label class="input-group" data-bs-toggle="modal" data-bs-target="#staticBackdrop">
				<i class="fa-solid fa-calculator input-group-text text-warning"></i>
				<input type="text" class="form-control" id="special_amount_paid" name="special_amount_paid" placeholder="Enter amount paid value" value="<?=old_value('special_amount_paid')?>" readonly>
				<input type="hidden" id="denos_special" name="denos_special" value="<?=htmlspecialchars((string)old_value('denos_special'), ENT_QUOTES, 'UTF-8')?>" />
			</label>
			<small class="text-muted d-none js-just4u-fixed-charge-hint">JUST4U deducts Amount Payable from e-cash. Amount paid must match the full entered amount; GHS 0.50 is recorded as commission.</small>
			<small class="text-danger js-special-error"></small>
		</div>
		
    <div class="col-md-12 generic-amount-field">
      <label for="amount" class="form-label">Amount</label>
       		<label class="input-group" data-bs-toggle="modal" data-bs-target="#staticBackdrop"> 
			<i class="fa-solid fa-calculator input-group-text text-warning"></i>
			<input name="amount" value="<?=old_value('amount')?>" type="number" step="0.01" class="form-control" id="amount" placeholder="Enter amount" readonly>
			<input type="hidden" id="denos" name="denos" value="<?=htmlspecialchars((string)old_value('denos'), ENT_QUOTES, 'UTF-8')?>" />
		</label>
		<?php if(!empty($errors['amount'])):?>
			<small class="text-danger"><?=$errors['amount']?></small>
		<?php endif?>
	</div>

	<div class="col-md-12">
		<?php if(!empty($errors['change_denominations'])):?>
			<small class="text-danger d-block"><?=$errors['change_denominations']?></small>
		<?php endif?>
		<?php if(!empty($errors['change_required'])):?>
			<small class="text-danger d-block"><?=$errors['change_required']?></small>
		<?php endif?>
		<div id="bundleChangeSummary" class="alert alert-warning py-2 px-3 mt-2 d-none">
			<div class="d-flex flex-wrap align-items-center gap-2">
				<div class="me-auto">
					<div class="fw-semibold" id="bundleChangeSummaryText">Change required: GHS 0.00</div>
					<small class="text-muted" id="bundleChangeSummaryNote">Select the denominations to give out so the net cash matches the payable amount.</small>
				</div>
				<button type="button" class="btn btn-sm btn-outline-primary" id="bundleOpenChangeBtn">Select change</button>
				<button type="button" class="btn btn-sm btn-outline-danger d-none" id="bundleClearChangeBtn">Clear</button>
			</div>
		</div>
	</div>

    <div class="col-md-12 generic-data-size-field">
      <label for="data_size" class="form-label">Data Size</label>
        <input name="data_size" value="<?=old_value('data_size')?>" type="text" class="form-control" id="data_size" placeholder="Enter the value of data to be sent">
		  <?php if(!empty($errors['data_size'])):?>
				  <small class="text-danger"><?=$errors['data_size']?></small>
				<?php endif?> 
			  <small class="text-danger js-balance-after-error"></small>
    </div>

		<div class="col-md-12  assigned">
		  <label for="assigned" class="form-label">Assigned to </label>
		  <select class="form-select assigned" name="assigned" id="assigned">
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

		<?php require plugin_path('views/admin/entries/transaction_image_upload.php'); ?>

		<div class="mb-2 transaction_status ">
		  <label for="transaction_status" class="form-label">Delivery Status</label>
		  <select class="form-select transaction_status" name="transaction_status">
			  <option value="">---Select---</option>
			  <option <?=old_select('transaction_status','Draft')?> value="Draft">Draft</option>
			  <option <?=old_select('transaction_status','Pending')?> value="Pending">Pending</option>
			  <option <?=old_select('transaction_status','Sent')?> value="Sent">Sent</option>
		  </select>				
			<?php if(!empty($errors['transaction_status'])):?>
			  <small class="text-danger"><?=$errors['transaction_status']?></small>
			<?php endif?>
		</div>
 
		<div class="d-flex justify-content-between" style="margin-top:10px;">
			<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
				<button type="button" class="btn btn-outline-primary" style="border:none;">
					<i class="fa-solid fa-chevron-left"></i> Back
				</button>
			</a>
			<button class="btn btn-outline-danger save" style="border:none;">
				<i class="fa-solid fa-save"></i> Save
			</button>
		</div>
	</div>
	</form>

	<div class="modal fade" id="staticBackdrop" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
	  <div class="modal-dialog">
	    <div class="modal-content">
	      <div class="modal-header">
	        <h5 class="modal-title" id="staticBackdropLabel">Currency Notes</h5>
	        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
	      </div>

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
	              <th class="px-5 bg-secondary text-warning">TOTAL</th>
	              <th class="bg-secondary text-warning"><input type="text" class="form-control p-1" id="total-count" style="border:none; " placeholder="Total count" readonly></th>
	              <th class="bg-secondary text-warning"><input type="text" class="form-control p-1" id="total-volume" style="border:none; " placeholder="Total volume" readonly></th>
	            </tr>
	          </tfoot>
	        </table>
	      </div>

	      <div class="modal-footer">
	        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
	        <button type="button" class="btn btn-danger reset" >Reset</button>
	        <button type="button" class="btn btn-warning restore" >Restore</button>
	        <button type="button" class="btn btn-primary submit">Confirm</button>
	</div>
	</div>
	</div>
	</div>

	<div class="modal fade" id="bundleChangeModal" tabindex="-1" aria-labelledby="bundleChangeLabel" aria-hidden="true">
		<div class="modal-dialog modal-md">
			<div class="modal-content border-0 shadow-lg">
				<div class="modal-header border-0 pb-0">
					<h5 class="modal-title fw-bold" id="bundleChangeLabel">
						<i class="fa fa-exchange-alt me-2"></i> Give Change
					</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div class="alert alert-info py-2 px-3 mb-3">
						<div class="fw-semibold">Change required: <span id="bundleChangeRequiredText">GHS 0.00</span></div>
						<small class="text-muted">The full received denominations will be added first, then the selected change denominations will be deducted from the cashdrawer.</small>
					</div>

					<div class="alert alert-light border py-2 px-3 mb-3" style="font-size:0.9rem;">
						<strong>Available after receipt:</strong>
						<span id="bundleAvailableDenosText" class="ms-1 text-muted">No denominations available.</span>
					</div>

					<div id="bundleChangeRows" class="border rounded p-2" style="max-height: 200px; overflow-y: auto; background-color: var(--bs-body-bg);"></div>

					<div class="d-flex align-items-center gap-2 mt-2">
						<small class="text-muted">Selected change: <strong id="bundleChangeSelectedText" class="text-danger">GHS 0.00</strong></small>
						<small class="text-muted ms-auto">Net cash: <strong id="bundleChangeNetText">GHS 0.00</strong></small>
					</div>
					<div class="mt-1">
						<small id="bundleChangeBalanceText" class="text-warning">Add GHS 0.00 more to balance.</small>
					</div>

					<div class="d-flex justify-content-end mt-2">
						<button type="button" class="btn btn-sm" id="bundleAddChangeRowBtn" style="background-color:#dc3545; color:white; border:none; padding:0.35rem 0.75rem;">
							<i class="fa fa-plus me-1"></i> Add
						</button>
					</div>

					<div class="alert py-2 px-3 mt-3 mb-0" id="bundleChangeAlert" role="alert">
						<span id="bundleChangeAlertText">Select the denominations to give out.</span>
					</div>
				</div>
				<div class="modal-footer border-0 pt-0">
					<button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="button" class="btn btn-sm btn-primary" id="bundleApplyChangeBtn" disabled>
						<i class="fa fa-check me-1"></i> Apply change
					</button>
				</div>
			</div>
		</div>
	</div>

<script>

	const categoryOptions = {
		
		"MTN": [
			{ value: "", text: "---Select---" },
			{ value: "MTN-DATA", text: "MTN-DATA" },
			{ value: "MASHUP", text: "MASHUP" },
			{ value: "JUST4U", text: "JUST4U" },
			{ value: "MTN UP2U", text: "MTN UP2U" },
			{ value: "STAFF BUNDLE", text: "STAFF BUNDLE" },
		],
		
		"TELECEL": [
			{ value: "", text: "---Select---" },
			{ value: "TELECEL-DATA", text: "TELECEL-DATA" },
			{ value: "5959", text: "5959" },
		],
		"AT": [
			{ value: "", text: "---Select---" },
			{ value: "AT-DATA", text: "AT-DATA" },
			{ value: "ISHARE", text: "ISHARE" },
		],
	};

	const transactionType = document.getElementById('transaction_type'); // Corrected ID selection
	const categorySelect = document.getElementById('category');
	const networkSelect = document.getElementById('network');
	const categoryDiv = document.querySelector('.category');
  const saveBtn = document.querySelector('.save');
  saveBtn.classList.add('disabled');

	function updateCategoryOptions() {
		const networkValue = network.value;
		categorySelect.innerHTML = ""; // Clear current options

		// Populate options based on selected transaction type
		if (categoryOptions[networkValue]) {
			categoryOptions[networkValue].forEach(option => {
				const opt = document.createElement('option');
				opt.value = option.value;
				opt.textContent = option.text;
				categorySelect.appendChild(opt);
			});
		} 
	}

	networkSelect.addEventListener("change", updateCategoryOptions);

  document.addEventListener("DOMContentLoaded", function () {
     const countInputs = document.querySelectorAll(".count-input");
	  const totalCountField = document.getElementById("total-count");
	  const totalVolumeField = document.getElementById("total-volume");
	  const denosField = document.querySelector("#denos");
	  const submitBtn = document.querySelector('.submit');
	  const restoreBtn = document.querySelector('.restore');
	  const resetBtn = document.querySelector('.reset');
	  const staticBackdropModal = new bootstrap.Modal(document.getElementById('staticBackdrop'));
	  const amountInput = document.getElementById("amount");
	  const userId = <?= json_encode($user_id); ?>;
	  const balanceAfter = document.getElementById('balance_after');
	  const balanceAfterError = document.querySelector('.js-balance-after-error');
		
	  const genericAmountField = document.querySelector('.generic-amount-field');
	  const genericDataSizeField = document.querySelector('.generic-data-size-field');
		
		const just4uFields = document.querySelector('.just4u-fields');
		const mashupFields = document.querySelector('.mashup-fields');
		const specialFields = document.querySelector('.special-fields');
		
		const just4uPaidInput = document.getElementById('just4u_paid');
		const just4uPayableInput = document.getElementById('just4u_payable');
		const just4uAmountInput = document.getElementById('just4u_amount');
		const just4uTimesInput = document.getElementById('just4u_times');
		const just4uChargeInput = document.getElementById('just4u_charge');
		const just4uSplitHint = document.getElementById('just4u_split_hint');
		const just4uDenosField = document.getElementById('denos_just4u');
		const just4uError = document.querySelector('.js-just4u-error');
		
		const mashupAmountInput = document.getElementById('mashup_amount');
		const mashupPaidInput = document.getElementById('mashup_paid');
		const mashupDenosField = document.getElementById('denos_mashup');
		const mashupError = document.querySelector('.js-mashup-error');
		
		const specialAmountLoadInput = document.getElementById('special_amount_load');
		const specialAmountPaidInput = document.getElementById('special_amount_paid');
		const specialDenosField = document.getElementById('denos_special');
		const specialError = document.querySelector('.js-special-error');
		const just4uFixedChargeHint = document.querySelector('.js-just4u-fixed-charge-hint');
		const just4uPayableLabel = document.querySelector('.js-just4u-payable-label');
		const just4uPayableField = document.querySelector('.js-just4u-payable-field');

        function getSeedDenos(){
            const categoryValue = categorySelect ? categorySelect.value : '';
            let activeField = denosField;
            if (['MASHUP', 'MTN-DATA', 'AT-DATA', 'TELECEL-DATA'].includes(categoryValue)) {
                activeField = mashupDenosField;
            } else if (['5959', 'MTN UP2U', 'ISHARE', 'JUST4U'].includes(categoryValue)) {
                activeField = specialDenosField;
            }

            const fromField = (activeField && (activeField.value || '').trim() !== '') ? (activeField.value || '').trim() : '';
            if(fromField){
                try{
                    const parsed = JSON.parse(fromField);
                    if(Array.isArray(parsed)) return parsed;
                }catch(e){}
            }

            try{
                const saved = localStorage.getItem('denosData');
                const parsed = saved ? JSON.parse(saved) : null;
                return Array.isArray(parsed) ? parsed : [];
            }catch(e){
                return [];
            }
        }

        // Restore selects after validation reload
        const oldNetwork = document.getElementById('oldNetwork') ? document.getElementById('oldNetwork').value : '';
        const oldCategory = document.getElementById('oldCategory') ? document.getElementById('oldCategory').value : '';
        if (oldNetwork) {
            networkSelect.value = oldNetwork;
        }
        updateCategoryOptions();
        if (oldCategory) {
            categorySelect.value = oldCategory;
        }
        // Re-apply dependent field visibility + validation
        toggleFields();
        validateAmounts();

        function restoreDenominations() {
            const currency = getSeedDenos();
            if (!currency || !currency.length) {
                return;
            }
            const rowMeta = Array.from(countInputs).map((input, idx) => {
                const row = input.closest('tr');
                return {
                    idx,
                    denomination: row ? parseFloat(row.getAttribute('data-denomination')) : NaN,
                };
            });
            const usedRowIdx = new Set();

            currency.forEach((item) => {
                let targetIdx = (item && item.row_index !== undefined && item.row_index !== null) ? parseInt(item.row_index) : NaN;
                if (!Number.isInteger(targetIdx) || !countInputs[targetIdx]) {
                    targetIdx = NaN;
                }
                if (!Number.isInteger(targetIdx)) {
                    const denom = parseFloat(item.denomination);
                    const match = rowMeta.find(m => !usedRowIdx.has(m.idx) && m.denomination === denom);
                    if (match) targetIdx = match.idx;
                }
                if (!Number.isInteger(targetIdx)) return;
                if (usedRowIdx.has(targetIdx)) return;

                const input = countInputs[targetIdx];
                const row = input.closest('tr');
                const volumeField = row ? row.querySelector('.volume-input') : null;
                input.value = item.count;
                if (volumeField) {
                    volumeField.value = (parseFloat(item.denomination) * (parseFloat(item.count) || 0)).toFixed(2);
                }
                usedRowIdx.add(targetIdx);
            });
            updateVolumesAndTotals();
        }

        // Auto-restore counts after validation reload
        restoreDenominations();

        // Add event listeners for count inputs
        countInputs.forEach(input => {
            input.addEventListener("input", updateVolumesAndTotals);
        });

        function resetDenominations() {
            countInputs.forEach((input) => {
                const row = input.closest('tr');
                const volumeField = row ? row.querySelector('.volume-input') : null;
                input.value = '';
                if (volumeField) volumeField.value = '';
            });
            updateVolumesAndTotals();
            try {
                localStorage.setItem('denosData', JSON.stringify([]));
            } catch (e) {}
            if (denosField) denosField.value = JSON.stringify([]);
        }

        function syncJust4uPayableField() {
            const isJust4u = categorySelect && categorySelect.value === 'JUST4U';
            const loadAmount = parseFloat(specialAmountLoadInput?.value || '0') || 0;
            const payableAmount = Math.max(0, loadAmount - 0.50);

            if (just4uPayableLabel) {
                just4uPayableLabel.classList.toggle('d-none', !isJust4u);
            }
            if (just4uPayableField) {
                just4uPayableField.classList.toggle('d-none', !isJust4u);
                //just4uPayableField.value = isJust4u && loadAmount > 0 ? payableAmount.toFixed(2) : '';
            }
            if (just4uFixedChargeHint) {
                just4uFixedChargeHint.classList.toggle('d-none', !isJust4u);
            }
        }

        function toggleFields() {
            const categoryValue = categorySelect.value;
            
            // Hide all custom fields
            just4uFields.classList.add('d-none');
            mashupFields.classList.add('d-none');
            specialFields.classList.add('d-none');
        
            // Hide generic fields
            genericAmountField.classList.add('d-none');
            genericDataSizeField.classList.add('d-none');
        
            // Show specific fields based on category selection
            switch (categoryValue) {
                case 'MASHUP':
                case 'MTN-DATA':
                case 'AT-DATA':
                case 'TELECEL-DATA':
                    mashupFields.classList.remove('d-none');
                    break;
                case '5959':
                case 'MTN UP2U':
                case 'ISHARE':
                case 'JUST4U':
                    specialFields.classList.remove('d-none');
                    break;

                case 'STAFF BUNDLE':
                    genericAmountField.classList.remove('d-none');
                    break;
                default:
                    break;
            }
            syncJust4uPayableField();
        }
        
        function validateAmounts() {
            const categoryValue = categorySelect.value;
        
            // Disable save button if no category selected
            if (!categoryValue) {
                saveBtn.classList.add('disabled');
                return;
            }

            if (['MASHUP', 'MTN-DATA', 'AT-DATA', 'TELECEL-DATA'].includes(categoryValue)) {
                const loadAmount = parseFloat(mashupAmountInput?.value || '0');
                const paidAmount = parseFloat(mashupPaidInput?.value || '0');
                mashupError.textContent = '';

                if (!loadAmount || loadAmount <= 0) {
                    mashupError.textContent = 'Load/Amount is required';
                    saveBtn.classList.add('disabled');
                    return;
                }
                if (!paidAmount || paidAmount <= 0) {
                    mashupError.textContent = 'Please use the calculator to enter amount paid';
                    saveBtn.classList.add('disabled');
                    return;
                }
                if (Math.abs(paidAmount - loadAmount) > 0.01) {
                    mashupError.textContent = `Amount paid must equal ${loadAmount.toFixed(2)}`;
                    saveBtn.classList.add('disabled');
                    return;
                }
            } else if (['5959', 'MTN UP2U', 'ISHARE', 'JUST4U'].includes(categoryValue)) {
                const loadAmount = parseFloat(specialAmountLoadInput?.value || '0');
                const paidAmount = parseFloat(specialAmountPaidInput?.value || '0');
                const ecashPayableAmount = Math.max(0, loadAmount - 0.50);
                syncJust4uPayableField();
                specialError.textContent = '';

                if (!loadAmount || loadAmount <= 0) {
                    specialError.textContent = 'Amount/Load is required';
                    saveBtn.classList.add('disabled');
                    return;
                }
                if (!paidAmount || paidAmount <= 0) {
                    specialError.textContent = 'Please use the calculator to enter amount paid';
                    saveBtn.classList.add('disabled');
                    return;
                }
                if (categoryValue === 'JUST4U' && paidAmount + 0.01 < loadAmount) {
                    specialError.textContent = `Amount paid must be at least ${loadAmount.toFixed(2)}. Amount Payable for e-cash is ${ecashPayableAmount.toFixed(2)}.`;
                    saveBtn.classList.add('disabled');
                    return;
                }
                if (categoryValue !== 'JUST4U' && Math.abs(paidAmount - loadAmount) > 0.01) {
                    specialError.textContent = `Amount paid must equal ${loadAmount.toFixed(2)}`;
                    saveBtn.classList.add('disabled');
                    return;
                }
            }

            // Enable save button when category is valid
            saveBtn.classList.remove('disabled');
        }
        
        function updateVolumesAndTotals() {

            let totalCount = 0;
            let totalVolume = 0;
            const currency = [];

            countInputs.forEach((input, rowIndex) => {
                const row = input.closest("tr");
                const denomination = parseFloat(row.getAttribute("data-denomination"));
                const count = parseFloat(input.value) || 0;
                const volumeField = row.querySelector(".volume-input");
                const volume = denomination * count;

                volumeField.value = volume.toFixed(2);
                totalCount += count;
                totalVolume += volume;

                // Only add to currency array if count is greater than zero
                if (count > 0) {
                    const denos = {
                        currency: `GHS`,
                        row_index: rowIndex,
                        denomination: denomination,
                        count: count,
                        volume: volume,
                        trans_in: 1,
                        user_id: <?= json_encode($user_id); ?>,
                    };

                    currency.push(denos);
                }
            });

            totalCountField.value = totalCount;
            totalVolumeField.value = totalVolume.toFixed(2);

            // Store serialized currency details in both localStorage and the hidden field
            const serializedCurrency = JSON.stringify(currency);
            localStorage.setItem("denosData", serializedCurrency);
            
            const categoryValue = categorySelect.value;
            
            // Update the correct hidden input field based on the selected category
            if (['MASHUP', 'MTN-DATA', 'AT-DATA', 'TELECEL-DATA'].includes(categoryValue)) {
                mashupDenosField.value = serializedCurrency;
            } else if (['5959', 'MTN UP2U', 'ISHARE', 'JUST4U'].includes(categoryValue)) {
                specialDenosField.value = serializedCurrency;
            } else {
                denosField.value = serializedCurrency;
            }
        }

        submitBtn.addEventListener('click', function() {
            const categoryValue = categorySelect.value;
            const totalVolume = parseFloat(totalVolumeField.value);
            
            if (['MASHUP', 'MTN-DATA', 'AT-DATA', 'TELECEL-DATA'].includes(categoryValue)) {
                mashupPaidInput.value = totalVolume.toFixed(2);
            } else if (['5959', 'MTN UP2U', 'ISHARE', 'JUST4U'].includes(categoryValue)) {
                specialAmountPaidInput.value = totalVolume.toFixed(2);
            } else {
                amountInput.value = totalVolume.toFixed(2);
            }
            
            validateAmounts();
            updateVolumesAndTotals();
            staticBackdropModal.hide();
        });
        
        restoreBtn.addEventListener('click', function() {
            // Restore denominations
            restoreDenominations();
        });

        resetBtn.addEventListener('click', function() {
            // Reset denominations
            resetDenominations();
        });

        const transactionType = document.getElementById('transaction_type');
        
        // Initial call to hide/show fields based on default selection
        toggleFields();

        // Event Listeners
        categorySelect.addEventListener('change', function() {
            toggleFields();
            validateAmounts();
        });

        mashupAmountInput.addEventListener('input', validateAmounts);
        specialAmountLoadInput.addEventListener('input', validateAmounts);
        mashupPaidInput.addEventListener('input', validateAmounts);
        specialAmountPaidInput.addEventListener('input', validateAmounts);

    });
</script>

<script>
  document.addEventListener("DOMContentLoaded", function () {
    const bundleForm = document.querySelector('form');
    const categorySelect = document.getElementById('category');
    const networkSelect = document.getElementById('network');
    const saveBtn = document.querySelector('.save');
    const cashdrawerDenosField = document.getElementById('cashdrawerDenos');
    const changeDenominationsField = document.getElementById('change_denominations');
    const changeRequiredField = document.getElementById('change_required');
    const cashReceivedAmountField = document.getElementById('cash_received_amount');
    const countInputs = document.querySelectorAll('.count-input');
    const submitBtn = document.querySelector('.submit');
    const denosField = document.getElementById('denos');
    const amountInput = document.getElementById('amount');
    const mashupAmountInput = document.getElementById('mashup_amount');
    const mashupPaidInput = document.getElementById('mashup_paid');
    const mashupDenosField = document.getElementById('denos_mashup');
    const mashupError = document.querySelector('.js-mashup-error');
    const specialAmountLoadInput = document.getElementById('special_amount_load');
    const specialAmountPaidInput = document.getElementById('special_amount_paid');
    const specialDenosField = document.getElementById('denos_special');
    const specialError = document.querySelector('.js-special-error');
    const just4uFixedChargeHint = document.querySelector('.js-just4u-fixed-charge-hint');
    const just4uPayableLabel = document.querySelector('.js-just4u-payable-label');
    const just4uPayableField = document.querySelector('.js-just4u-payable-field');

    const bundleChangeSummary = document.getElementById('bundleChangeSummary');
    const bundleChangeSummaryText = document.getElementById('bundleChangeSummaryText');
    const bundleChangeSummaryNote = document.getElementById('bundleChangeSummaryNote');
    const bundleOpenChangeBtn = document.getElementById('bundleOpenChangeBtn');
    const bundleClearChangeBtn = document.getElementById('bundleClearChangeBtn');
    const bundleChangeRows = document.getElementById('bundleChangeRows');
    const bundleAddChangeRowBtn = document.getElementById('bundleAddChangeRowBtn');
    const bundleApplyChangeBtn = document.getElementById('bundleApplyChangeBtn');
    const bundleChangeRequiredText = document.getElementById('bundleChangeRequiredText');
    const bundleAvailableDenosText = document.getElementById('bundleAvailableDenosText');
    const bundleChangeSelectedText = document.getElementById('bundleChangeSelectedText');
    const bundleChangeNetText = document.getElementById('bundleChangeNetText');
    const bundleChangeBalanceText = document.getElementById('bundleChangeBalanceText');
    const bundleChangeAlert = document.getElementById('bundleChangeAlert');
    const bundleChangeAlertText = document.getElementById('bundleChangeAlertText');
    const changeModalEl = document.getElementById('bundleChangeModal');
    const bundleChangeModal = changeModalEl ? new bootstrap.Modal(changeModalEl) : null;
    const cashDenominationOptions = [200, 100, 50, 20, 10, 5, 2, 1, 0.5, 0.2, 0.1];

    const mashupCategories = ['MASHUP', 'MTN-DATA', 'AT-DATA', 'TELECEL-DATA'];
    const specialCategories = ['5959', 'MTN UP2U', 'ISHARE', 'JUST4U'];

    function roundMoney(value) {
      return Math.round((Number(value) || 0) * 100) / 100;
    }

    function formatMoney(value) {
      return roundMoney(value).toFixed(2);
    }

    function valuesMatch(a, b) {
      return Math.abs(roundMoney(a) - roundMoney(b)) <= 0.01;
    }

    function syncJust4uPayableField() {
      const isJust4u = categorySelect && categorySelect.value === 'JUST4U';
      const loadAmount = roundMoney(specialAmountLoadInput && specialAmountLoadInput.value);
      const payableAmount = Math.max(0, loadAmount - 0.50);

      if (just4uPayableLabel) {
        just4uPayableLabel.classList.toggle('d-none', !isJust4u);
      }
      if (just4uPayableField) {
        just4uPayableField.classList.toggle('d-none', !isJust4u);
        just4uPayableField.value = isJust4u && loadAmount > 0 ? formatMoney(payableAmount) : '';
      }
      if (just4uFixedChargeHint) {
        just4uFixedChargeHint.classList.toggle('d-none', !isJust4u);
      }
    }

    function normalizeDenominationKey(value) {
      const num = Number(value);
      if (!Number.isFinite(num) || num <= 0) {
        return '';
      }
      return num.toFixed(2);
    }

    function parseJsonArray(inputEl) {
      if (!inputEl || !inputEl.value) {
        return [];
      }
      try {
        const parsed = JSON.parse(inputEl.value);
        return Array.isArray(parsed) ? parsed : [];
      } catch (e) {
        return [];
      }
    }

    function sanitizeChangeRows(rows) {
      if (!Array.isArray(rows)) {
        return [];
      }

      return rows.reduce((carry, item) => {
        const denomination = roundMoney(item && item.denomination);
        const count = parseInt(item && item.count, 10) || 0;
        if (denomination > 0 && count > 0) {
          carry.push({
            denomination,
            count,
            volume: roundMoney(denomination * count),
          });
        }
        return carry;
      }, []);
    }

    function aggregateDenominationCounts(rows) {
      const map = {};
      (rows || []).forEach(item => {
        const key = normalizeDenominationKey(item && item.denomination);
        const count = parseInt(item && item.count, 10) || 0;
        if (!key || count <= 0) {
          return;
        }
        map[key] = (map[key] || 0) + count;
      });
      return map;
    }

    function getActiveContext() {
      const categoryValue = categorySelect.value;

      if (mashupCategories.includes(categoryValue)) {
        return {
          categoryValue,
          eligible: true,
          paidInput: mashupPaidInput,
          payableInput: mashupAmountInput,
          denosInput: mashupDenosField,
          errorEl: mashupError,
        };
      }

      if (specialCategories.includes(categoryValue)) {
        return {
          categoryValue,
          eligible: true,
          paidInput: specialAmountPaidInput,
          payableInput: specialAmountLoadInput,
          denosInput: specialDenosField,
          errorEl: specialError,
        };
      }

      return {
        categoryValue,
        eligible: false,
        paidInput: amountInput,
        payableInput: amountInput,
        denosInput: denosField,
        errorEl: null,
      };
    }

    function getSelectedChangeRows() {
      return sanitizeChangeRows(parseJsonArray(changeDenominationsField));
    }

    function getSelectedChangeTotal(rows) {
      return roundMoney((rows || []).reduce((sum, item) => {
        return sum + (roundMoney(item.denomination) * (parseInt(item.count, 10) || 0));
      }, 0));
    }

    function getDrawerAvailabilityMap() {
      return aggregateDenominationCounts(parseJsonArray(cashdrawerDenosField));
    }

    function getReceivedDenominationMap() {
      return aggregateDenominationCounts(parseJsonArray(getActiveContext().denosInput));
    }

    function getCombinedAvailabilityMap() {
      const combined = getDrawerAvailabilityMap();
      const received = getReceivedDenominationMap();
      Object.keys(received).forEach(key => {
        combined[key] = (combined[key] || 0) + (received[key] || 0);
      });
      return combined;
    }

    function getCashReceivedAmount() {
      const hiddenValue = roundMoney(cashReceivedAmountField && cashReceivedAmountField.value);
      if (hiddenValue > 0) {
        return hiddenValue;
      }
      return roundMoney(getActiveContext().paidInput && getActiveContext().paidInput.value);
    }

    function getPayableAmount() {
      const context = getActiveContext();
      if (!context.eligible) {
        return 0;
      }
      const loadAmount = roundMoney(context.payableInput && context.payableInput.value);
      return loadAmount;
    }

    function getRequiredChangeAmount() {
      const payable = getPayableAmount();
      const received = getCashReceivedAmount();
      if (payable > 0 && received > payable + 0.01) {
        return roundMoney(received - payable);
      }
      return 0;
    }

    function resetChangeSelection() {
      if (changeDenominationsField) {
        changeDenominationsField.value = '[]';
      }
      if (changeRequiredField) {
        changeRequiredField.value = '';
      }
      syncChangeSummary();
    }

    function syncChangeSummary() {
      if (!bundleChangeSummary) {
        return;
      }

      const context = getActiveContext();
      const payable = getPayableAmount();
      const received = getCashReceivedAmount();
      const required = getRequiredChangeAmount();
      const selectedRows = getSelectedChangeRows();
      const selectedTotal = getSelectedChangeTotal(selectedRows);
      const netCash = roundMoney(received - selectedTotal);
      const hasSelection = selectedRows.length > 0;
      const isBalanced = required > 0 && hasSelection && valuesMatch(selectedTotal, required) && valuesMatch(netCash, payable);

      if (!context.eligible || required <= 0.01) {
        bundleChangeSummary.classList.add('d-none');
        bundleChangeSummary.classList.remove('alert-warning', 'alert-success', 'alert-danger');
        if (bundleClearChangeBtn) {
          bundleClearChangeBtn.classList.add('d-none');
        }
        return;
      }

      bundleChangeSummary.classList.remove('d-none', 'alert-warning', 'alert-success', 'alert-danger');
      bundleChangeSummary.classList.add(isBalanced ? 'alert-success' : (hasSelection ? 'alert-danger' : 'alert-warning'));
      bundleChangeSummaryText.textContent = 'Change required: GHS ' + formatMoney(required);

      if (isBalanced) {
        bundleChangeSummaryNote.textContent = 'Received GHS ' + formatMoney(received) + '. Change selected GHS ' + formatMoney(selectedTotal) + '. Net cash GHS ' + formatMoney(netCash) + ' matches the payable amount.';
      } else if (hasSelection) {
        bundleChangeSummaryNote.textContent = 'Selected change totals GHS ' + formatMoney(selectedTotal) + '. Adjust it to exactly GHS ' + formatMoney(required) + '.';
      } else {
        bundleChangeSummaryNote.textContent = 'Select the denominations to give out so the net cash matches the payable amount.';
      }

      if (bundleOpenChangeBtn) {
        bundleOpenChangeBtn.textContent = hasSelection ? 'Edit change' : 'Select change';
      }
      if (bundleClearChangeBtn) {
        bundleClearChangeBtn.classList.toggle('d-none', !hasSelection);
      }
    }

    function syncChangeRequirement() {
      const context = getActiveContext();
      const received = roundMoney(context.paidInput && context.paidInput.value);
      if (cashReceivedAmountField) {
        cashReceivedAmountField.value = received > 0 ? formatMoney(received) : '';
      }

      const payable = getPayableAmount();
      if (!context.eligible || payable <= 0.01) {
        resetChangeSelection();
        return { received, payable, required: 0 };
      }

      const required = getRequiredChangeAmount();
      const previousRequired = roundMoney(changeRequiredField && changeRequiredField.value);

      if (required <= 0.01) {
        resetChangeSelection();
        return { received, payable, required: 0 };
      }

      if (changeRequiredField) {
        changeRequiredField.value = formatMoney(required);
      }

      if (!valuesMatch(previousRequired, required) && getSelectedChangeRows().length > 0 && changeDenominationsField) {
        changeDenominationsField.value = '[]';
      }

      syncChangeSummary();

      return { received, payable, required };
    }

    function buildChangeSelectOptions(selectedValue) {
      const currentKey = normalizeDenominationKey(selectedValue);
      return ['<option value="">---Amount (GHS)---</option>']
        .concat(cashDenominationOptions.map(value => {
          const key = normalizeDenominationKey(value);
          const selected = key === currentKey ? ' selected' : '';
          return '<option value="' + formatMoney(value) + '"' + selected + '>' + formatMoney(value) + '</option>';
        }))
        .join('');
    }

    function getRawChangeRowsFromModal() {
      if (!bundleChangeRows) {
        return [];
      }
      return Array.from(bundleChangeRows.querySelectorAll('.bundle-change-row')).map(row => {
        const valueEl = row.querySelector('.bundle-change-value');
        const countEl = row.querySelector('.bundle-change-count');
        return {
          denomination: valueEl ? valueEl.value : '',
          count: countEl ? countEl.value : '',
        };
      });
    }

    function getChangeRowsFromModal() {
      return sanitizeChangeRows(getRawChangeRowsFromModal());
    }

    function syncChangeSelectOptions() {
      if (!bundleChangeRows) {
        return;
      }

      const selects = Array.from(bundleChangeRows.querySelectorAll('.bundle-change-value'));
      const selected = new Set();

      selects.forEach(select => {
        const key = normalizeDenominationKey(select.value);
        if (key) {
          selected.add(key);
        }
      });

      selects.forEach(select => {
        const currentKey = normalizeDenominationKey(select.value);
        Array.from(select.options).forEach((option, index) => {
          if (index === 0) {
            option.disabled = false;
            option.hidden = false;
            return;
          }
          const optionKey = normalizeDenominationKey(option.value || option.text);
          const taken = optionKey && selected.has(optionKey) && optionKey !== currentKey;
          option.disabled = !!taken;
          option.hidden = !!taken;
        });
      });
    }

    function updateChangeModalState() {
      if (!bundleChangeRows || !bundleApplyChangeBtn) {
        return;
      }

      const context = getActiveContext();
      syncChangeSelectOptions();

      const required = getRequiredChangeAmount();
      const received = getCashReceivedAmount();
      const payable = getPayableAmount();
      const rows = getChangeRowsFromModal();
      const selectedTotal = getSelectedChangeTotal(rows);
      const netCash = roundMoney(received - selectedTotal);
      const balanceGap = roundMoney(required - selectedTotal);
      const availability = getCombinedAvailabilityMap();
      const requested = {};

      rows.forEach(item => {
        const key = normalizeDenominationKey(item.denomination);
        if (!key) {
          return;
        }
        requested[key] = (requested[key] || 0) + (parseInt(item.count, 10) || 0);
      });

      const insufficient = [];
      Object.keys(requested).forEach(key => {
        const have = parseInt(availability[key], 10) || 0;
        const need = parseInt(requested[key], 10) || 0;
        if (need > have) {
          insufficient.push('GHS ' + key + ' (need ' + need + ', available ' + have + ')');
        }
      });

      if (bundleChangeRequiredText) {
        bundleChangeRequiredText.textContent = 'GHS ' + formatMoney(required);
      }
      if (bundleChangeSelectedText) {
        bundleChangeSelectedText.textContent = 'GHS ' + formatMoney(selectedTotal);
      }
      if (bundleChangeNetText) {
        bundleChangeNetText.textContent = 'GHS ' + formatMoney(netCash);
      }
      if (bundleChangeBalanceText) {
        bundleChangeBalanceText.classList.remove('text-muted', 'text-warning', 'text-danger', 'text-success');

        if (!context.eligible || required <= 0.01) {
          bundleChangeBalanceText.textContent = 'No extra change is needed for this transaction.';
          bundleChangeBalanceText.classList.add('text-muted');
        } else if (balanceGap > 0.01) {
          bundleChangeBalanceText.textContent = 'Add GHS ' + formatMoney(balanceGap) + ' more to balance.';
          bundleChangeBalanceText.classList.add('text-warning');
        } else if (balanceGap < -0.01) {
          bundleChangeBalanceText.textContent = 'Reduce change by GHS ' + formatMoney(Math.abs(balanceGap)) + ' to balance.';
          bundleChangeBalanceText.classList.add('text-danger');
        } else {
          bundleChangeBalanceText.textContent = 'Balanced. Change selection matches the required amount.';
          bundleChangeBalanceText.classList.add('text-success');
        }
      }
      if (bundleAvailableDenosText) {
        const labels = Object.keys(availability)
          .filter(key => (parseInt(availability[key], 10) || 0) > 0)
          .sort((a, b) => parseFloat(b) - parseFloat(a))
          .map(key => 'GHS ' + key + ' (' + availability[key] + ' units)');
        bundleAvailableDenosText.textContent = labels.length ? labels.join(', ') : 'No denominations available.';
      }

      if (!context.eligible) {
        bundleChangeAlert.className = 'alert alert-info py-2 px-3 mt-3 mb-0';
        bundleChangeAlertText.textContent = 'Change is only used when this category has a separate payable amount.';
        bundleApplyChangeBtn.disabled = true;
        return;
      }

      if (required <= 0.01) {
        bundleChangeAlert.className = 'alert alert-info py-2 px-3 mt-3 mb-0';
        bundleChangeAlertText.textContent = 'No change is required for this transaction.';
        bundleApplyChangeBtn.disabled = true;
        return;
      }

      if (received + 0.01 < payable) {
        bundleChangeAlert.className = 'alert alert-warning py-2 px-3 mt-3 mb-0';
        bundleChangeAlertText.textContent = 'Amount received is below the payable amount, so change cannot be configured yet.';
        bundleApplyChangeBtn.disabled = true;
        return;
      }

      if (!rows.length) {
        bundleChangeAlert.className = 'alert alert-info py-2 px-3 mt-3 mb-0';
        bundleChangeAlertText.textContent = 'Select the denominations to give out. Add GHS ' + formatMoney(required) + ' more to balance.';
        bundleApplyChangeBtn.disabled = true;
        return;
      }

      if (insufficient.length) {
        bundleChangeAlert.className = 'alert alert-danger py-2 px-3 mt-3 mb-0';
        bundleChangeAlertText.textContent = 'Insufficient denominations after receipt: ' + insufficient.join(' | ');
        bundleApplyChangeBtn.disabled = true;
        return;
      }

      if (!valuesMatch(selectedTotal, required) || !valuesMatch(netCash, payable)) {
        bundleChangeAlert.className = 'alert alert-warning py-2 px-3 mt-3 mb-0';
        if (balanceGap > 0.01) {
          bundleChangeAlertText.textContent = 'Add GHS ' + formatMoney(balanceGap) + ' more change so the net cash equals the payable amount.';
        } else if (balanceGap < -0.01) {
          bundleChangeAlertText.textContent = 'Reduce change by GHS ' + formatMoney(Math.abs(balanceGap)) + ' so the net cash equals the payable amount.';
        } else {
          bundleChangeAlertText.textContent = 'Change must total exactly GHS ' + formatMoney(required) + ' so the net cash equals the payable amount.';
        }
        bundleApplyChangeBtn.disabled = true;
        return;
      }

      bundleChangeAlert.className = 'alert alert-success py-2 px-3 mt-3 mb-0';
      bundleChangeAlertText.textContent = 'Change selection is balanced and ready to apply.';
      bundleApplyChangeBtn.disabled = false;
    }

    function bindChangeRowEvents() {
      if (!bundleChangeRows) {
        return;
      }

      bundleChangeRows.querySelectorAll('.bundle-change-value, .bundle-change-count').forEach(input => {
        input.addEventListener('input', updateChangeModalState);
        input.addEventListener('change', updateChangeModalState);
      });

      bundleChangeRows.querySelectorAll('.bundle-delete-change').forEach(button => {
        button.addEventListener('click', function () {
          const rows = bundleChangeRows.querySelectorAll('.bundle-change-row');
          if (rows.length <= 1) {
            const row = this.closest('.bundle-change-row');
            if (row) {
              const select = row.querySelector('.bundle-change-value');
              const count = row.querySelector('.bundle-change-count');
              if (select) select.value = '';
              if (count) count.value = '';
            }
          } else {
            const row = this.closest('.bundle-change-row');
            if (row) row.remove();
          }
          updateChangeModalState();
        });
      });
    }

    function renderChangeRows(seedRows) {
      if (!bundleChangeRows) {
        return;
      }

      const rows = Array.isArray(seedRows) ? seedRows : [];
      const sourceRows = rows.length ? rows : [{ denomination: '', count: '' }];

      bundleChangeRows.innerHTML = sourceRows.map(row => {
        const denomination = row && row.denomination !== undefined && row.denomination !== null && row.denomination !== ''
          ? formatMoney(row.denomination)
          : '';
        const count = row && row.count !== undefined && row.count !== null && row.count !== ''
          ? String(row.count)
          : '';
        return `
          <div class="bundle-change-row d-flex align-items-center gap-2 mb-2">
            <select class="form-select bundle-change-value">${buildChangeSelectOptions(denomination)}</select>
            <input type="number" min="1" step="1" class="form-control bundle-change-count" placeholder="Count" value="${count}">
            <button type="button" class="btn btn-sm btn-outline-danger bundle-delete-change"><i class="fa fa-trash"></i></button>
          </div>
        `;
      }).join('');

      bindChangeRowEvents();
      syncChangeSelectOptions();
      updateChangeModalState();
    }

    function showChangeModal() {
      if (!bundleChangeModal) {
        return;
      }

      const state = syncChangeRequirement();
      if (!getActiveContext().eligible || state.required <= 0.01 || state.received + 0.01 < state.payable) {
        return;
      }

      renderChangeRows(getSelectedChangeRows());
      updateChangeModalState();
      bundleChangeModal.show();
    }

    function validateBundleChangeState() {
      const context = getActiveContext();
      const required = getRequiredChangeAmount();
      const received = getCashReceivedAmount();
      const payable = getPayableAmount();
      const selectedRows = getSelectedChangeRows();
      const selectedTotal = getSelectedChangeTotal(selectedRows);
      const netCash = roundMoney(received - selectedTotal);

      if (!context.eligible) {
        syncChangeSummary();
        return !saveBtn.classList.contains('disabled');
      }

      if (context.errorEl) {
        context.errorEl.textContent = '';
      }

      if (payable <= 0) {
        if (context.errorEl) {
          context.errorEl.textContent = mashupCategories.includes(context.categoryValue) ? 'Load/Amount is required' : 'Amount/Load is required';
        }
        saveBtn.classList.add('disabled');
        return false;
      }

      if (received <= 0) {
        if (context.errorEl) {
          context.errorEl.textContent = 'Please use the calculator to enter amount paid';
        }
        saveBtn.classList.add('disabled');
        return false;
      }

      if (received + 0.01 < payable) {
        if (context.errorEl) {
          context.errorEl.textContent = 'Amount paid cannot be less than the payable amount';
        }
        saveBtn.classList.add('disabled');
        return false;
      }

      if (required > 0.01 && (!selectedRows.length || !valuesMatch(selectedTotal, required) || !valuesMatch(netCash, payable))) {
        if (context.errorEl) {
          context.errorEl.textContent = 'Change of GHS ' + formatMoney(required) + ' is required. Select the denominations to give out.';
        }
        saveBtn.classList.add('disabled');
        return false;
      }

      saveBtn.classList.remove('disabled');
      return true;
    }

    function syncFromActivePaidField() {
      const context = getActiveContext();
      syncJust4uPayableField();
      if (cashReceivedAmountField) {
        const received = roundMoney(context.paidInput && context.paidInput.value);
        cashReceivedAmountField.value = received > 0 ? formatMoney(received) : '';
      }
      syncChangeRequirement();
      validateBundleChangeState();
    }

    categorySelect.addEventListener('change', syncFromActivePaidField);
    networkSelect.addEventListener('change', syncFromActivePaidField);
    mashupAmountInput.addEventListener('input', syncFromActivePaidField);
    specialAmountLoadInput.addEventListener('input', syncFromActivePaidField);
    mashupPaidInput.addEventListener('input', syncFromActivePaidField);
    specialAmountPaidInput.addEventListener('input', syncFromActivePaidField);

    countInputs.forEach(input => {
      input.addEventListener('input', function () {
        setTimeout(syncFromActivePaidField, 0);
      });
    });

    if (submitBtn) {
      submitBtn.addEventListener('click', function () {
        setTimeout(function () {
          syncFromActivePaidField();
          const state = syncChangeRequirement();
          if (getActiveContext().eligible && state.required > 0.01) {
            showChangeModal();
          }
        }, 0);
      });
    }

    if (bundleOpenChangeBtn) {
      bundleOpenChangeBtn.addEventListener('click', showChangeModal);
    }

    if (bundleClearChangeBtn) {
      bundleClearChangeBtn.addEventListener('click', function () {
        resetChangeSelection();
        validateBundleChangeState();
      });
    }

    if (bundleAddChangeRowBtn) {
      bundleAddChangeRowBtn.addEventListener('click', function () {
        const rows = getRawChangeRowsFromModal();
        rows.push({ denomination: '', count: '' });
        renderChangeRows(rows);
      });
    }

    if (bundleApplyChangeBtn) {
      bundleApplyChangeBtn.addEventListener('click', function () {
        const rows = getChangeRowsFromModal();
        const required = getRequiredChangeAmount();
        const selectedTotal = getSelectedChangeTotal(rows);
        const netCash = roundMoney(getCashReceivedAmount() - selectedTotal);

        if (!rows.length || !valuesMatch(selectedTotal, required) || !valuesMatch(netCash, getPayableAmount())) {
          updateChangeModalState();
          return;
        }

        if (changeDenominationsField) {
          changeDenominationsField.value = JSON.stringify(rows);
        }
        if (changeRequiredField) {
          changeRequiredField.value = formatMoney(required);
        }
        syncChangeSummary();
        bundleChangeModal.hide();
        validateBundleChangeState();
      });
    }

    if (bundleForm) {
      bundleForm.addEventListener('submit', function (e) {
        syncFromActivePaidField();
        if (!validateBundleChangeState()) {
          e.preventDefault();
          if (getRequiredChangeAmount() > 0.01) {
            showChangeModal();
          }
        }
      });
    }

    renderChangeRows(getSelectedChangeRows());
    syncFromActivePaidField();
  });
</script>


<?php else:?>
    <div class="alert alert-danger text-center">
        Access denied. You dont have permission for this action
    </div>
<?php endif?>
