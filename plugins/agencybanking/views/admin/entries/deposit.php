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

		.transaction-preview-card{
			position: relative;
			width: 100%;
			max-width: 240px;
			margin: 0 auto;
			border-radius: 14px;
			overflow: hidden;
			border: 1px solid rgba(229,231,235,0.25);
			background: rgba(15, 23, 42, 0.10);
			box-shadow: 0 12px 28px rgba(0,0,0,0.18);
			cursor: pointer;
			outline: none;
		}
		.transaction-preview-card:focus{
			box-shadow: 0 0 0 3px rgba(234,179,8,0.35), 0 12px 28px rgba(0,0,0,0.18);
		}
		.transaction-preview-card img{
			display:block;
			width:100%;
			height: 160px;
			object-fit: cover;
		}
		.transaction-preview-placeholder{
			height: 160px;
			display:flex;
			align-items:center;
			justify-content:center;
			flex-direction:column;
			gap:.35rem;
			border: 1px dashed rgba(234,179,8,0.45);
			border-radius: 14px;
			margin: .5rem;
			color: rgba(229,231,235,0.85);
			background: rgba(2,6,23,0.35);
		}
		.transaction-preview-placeholder .title{
			font-weight: 700;
			font-size: .9rem;
		}
		.transaction-preview-placeholder .hint{
			font-size: .78rem;
			opacity: .85;
		}
		.transaction-preview-overlay{
			position:absolute;
			inset:0;
			display:flex;
			align-items:flex-end;
			justify-content:flex-end;
			padding: .5rem;
			background: linear-gradient(180deg, rgba(2,6,23,0) 30%, rgba(2,6,23,0.75) 100%);
			opacity: 0;
			transition: opacity .18s ease;
		}
		.transaction-preview-card:hover .transaction-preview-overlay{
			opacity: 1;
		}
		.transaction-preview-badge{
			display:inline-flex;
			align-items:center;
			gap:.35rem;
			font-size: .8rem;
			font-weight: 600;
			padding: .3rem .6rem;
			border-radius: 999px;
			background: rgba(234,179,8,0.92);
			color: #111827;
		}
		#cashinChangeSummary{
			margin-top: .5rem;
		}
		#cashinChangeSummary .btn{
			font-size: .8rem;
		}
		.visually-hidden-file{
			position:absolute !important;
			width:1px !important;
			height:1px !important;
			padding:0 !important;
			margin:-1px !important;
			overflow:hidden !important;
			clip: rect(0,0,0,0) !important;
			white-space:nowrap !important;
			border:0 !important;
		}
	</style>
	<form method="post" enctype="multipart/form-data">		
		<div class="row col-9 col-lg-5 mx-auto shadow p-4 mb-5 rounded">
			
			<?=csrf()?>

			<h4 class="text-center">
				<span><img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:60px;max-height: 60px;object-fit: cover;"></span>
				Deposit
			</h4>
	 
			<div class="mb-2">
				<label for="transaction_type" class="form-label">Transaction type</label>
				<select class="form-select transaction_type" id="transaction_type" name="transaction_type" onchange="updateCategoryOptions(); updateSourceOptions();">
				  <option value="">---Select---</option>
				  <option <?=old_select('transaction_type','Deposit')?> value="Deposit" selected>Deposit</option>
				</select>

				<?php if(!empty($errors['transaction_type'])):?>
				  <small class="text-danger"><?=$errors['transaction_type']?></small>
				<?php endif?>
			</div>
	 
			<div class="mb-2">
				<label for="network" class="form-label">Service provider</label>
				<select class="form-select" name="network" id="network" onchange="showFields()">			  
				  </select>
           <div id="networkError" class="text-error text-danger d-none">Service provider is required</div>

				<?php if(!empty($errors['network'])):?>
				  <small class="text-danger"><?=$errors['network']?></small>
				<?php endif?>
			</div>
	 
			<div class="mb-2 category d-none">
				<label for="category" class="form-label">Category</label>
				<select class="form-select" name="category" id="category" onchange="showFields()">
				  </select>

				<?php if(!empty($errors['category'])):?>
				  <small class="text-danger"><?=$errors['category']?></small>
				<?php endif?>
			</div>

			<div class="mb-2">
			  <label for="phone" class="form-label">Phone</label>
			  <input name="phone" value="<?=old_value('phone')?>" type="text" class="form-control" id="phone" placeholder="Enter phone number">
          <div id="phoneError" class="text-error text-danger d-none">Phone number is required</div>
				
				<?php if(!empty($errors['phone'])):?>
				  <small class="text-danger"><?=$errors['phone']?></small>
				<?php endif?>
			</div>

			<div class="mb-2 account_number d-none">
			  <label for="account_number" class="form-label">Account number</label>
			  <input name="account_number" value="<?=old_value('account_number')?>" type="text" class="form-control" id="account_number" placeholder="Enter account number">
                <div id="account_numberError" class="text-error text-danger d-none">Account number is required</div>
				
				<?php if(!empty($errors['account_number'])):?>
				  <small class="text-danger"><?=$errors['account_number']?></small>
				<?php endif?>
			</div>

			<div class="mb-2 destination_bank d-none">
			  <label for="destination_bank" class="form-label">Destination bank</label>
			  <input name="destination_bank" value="<?=old_value('destination_bank')?>" type="text" class="form-control" id="destination_bank" placeholder="Enter destination bank ">			
				<?php if(!empty($errors['destination_bank'])):?>
				  <small class="text-danger"><?=$errors['destination_bank']?></small>
				<?php endif?>
			</div>

			<div class="mb-2 cash_source d-none">
			  <label for="cash_source" class="form-label">Source of funds</label>
			  <input name="cash_source" value="<?=old_value('cash_source')?>" type="text" class="form-control" id="cash_source" placeholder="Enter source of funds">
				
				<?php if(!empty($errors['cash_source'])):?>
				  <small class="text-danger"><?=$errors['cash_source']?></small>
				<?php endif?>
			</div>

            <div class="mb-2">
                <label for="load" class="form-label">Load</label>
                <input
                  name="amount"
                  id="load"
                  type="number"
                  step="0.01"
                  class="form-control"
                  placeholder="Enter load"
                  value="<?=old_value('amount')?>"
                >
                <div id="loadError" class="text-error text-danger d-none">Load is required</div>
                <?php if(!empty($errors['amount'])):?>
                  <small class="text-danger"><?=$errors['amount']?></small>
                <?php endif?>
            </div>

            <div class="mb-2">
                <label for="amount_paid" class="form-label">Amount paid</label>
                <label class="input-group" data-bs-toggle="modal" data-bs-target="#staticBackdrop">
                    <i class="fa-solid fa-calculator input-group-text text-warning"></i>
                    <input
                      name="amount_paid"
                      id="amount_paid"
                      type="number"
                      step="0.01"
                      class="form-control"
                      placeholder="Amount paid"
                      readonly
                      value="<?=old_value('amount_paid')?>"
                    >
                    <input type="hidden" id="denos" name="denos" value="<?=htmlspecialchars((string)old_value('denos'), ENT_QUOTES, 'UTF-8')?>" />
                    <input type="hidden" id="change_denominations" name="change_denominations" value="<?=htmlspecialchars((string)old_value('change_denominations'), ENT_QUOTES, 'UTF-8')?>" />
                    <input type="hidden" id="change_required" name="change_required" value="<?=htmlspecialchars((string)old_value('change_required'), ENT_QUOTES, 'UTF-8')?>" />
                    <input type="hidden" id="cash_received_amount" name="cash_received_amount" value="<?=htmlspecialchars((string)(old_value('cash_received_amount') ?: old_value('amount_paid') ?: old_value('amount')), ENT_QUOTES, 'UTF-8')?>" />
                    <input type="hidden" id="cashdrawerDenos" value="<?=htmlspecialchars(json_encode($cashdrawerDenos ?? []), ENT_QUOTES, 'UTF-8')?>" />
                    <input type="hidden" id="cashdrawerBalances" value="<?=htmlspecialchars(json_encode($cashdrawerBalances ?? []), ENT_QUOTES, 'UTF-8')?>" />
                </label>
                <div id="paidError" class="text-error text-danger d-none">Amount paid is required</div>
                <div id="paidMismatchError" class="text-error text-danger">Amount paid must equal load</div>
                <div id="cashinChangeSummary" class="alert alert-warning py-2 px-3 mt-2 d-none">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <div class="me-auto">
                            <div class="fw-semibold" id="cashinChangeSummaryText">Change required: GHS 0.00</div>
                            <small class="text-muted" id="cashinChangeSummaryNote">Select the denominations to give out so the net cash matches the deposit load.</small>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="cashinOpenChangeBtn">Select change</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="cashinClearChangeBtn">Clear</button>
                    </div>
                </div>
                <?php if(!empty($errors['amount_paid'])):?> 
                  <small class="text-danger"><?=$errors['amount_paid']?></small>
                <?php endif?>
            </div>

            <div class="mb-2">
              <label class="form-label">Transaction image</label>
              <div id="transactionImagePreviewCard" class="transaction-preview-card" tabindex="0" role="button" aria-label="Upload transaction image">
                <div id="transactionImagePreviewPlaceholder" class="transaction-preview-placeholder">
                  <div class="title">Add transaction picture</div>
                  <div class="hint">Tap to select an image</div>
                </div>
                <img id="transactionImagePreview" src="" alt="Transaction image preview" style="display:none;">
                <div class="transaction-preview-overlay">
                  <span class="transaction-preview-badge"><i class="fa-solid fa-camera"></i> Change</span>
                </div>
              </div>
              <small class="text-muted d-block mt-1 text-center">Tap the preview to change the image</small>
              <small class="text-danger js-transaction-image-error text-center d-block"><?=(string)($errors['transaction_image'] ?? '')?></small>
              <input name="transaction_image" type="file" class="visually-hidden-file" id="transaction_image" accept="image/*">
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

			<div class="mb-2 transaction_status d-none">
			  <label for="transaction_status" class="form-label">Status</label>
			  <select class="form-select transaction_status" name="transaction_status">
				  <option value="">---Select---</option>
				  <option <?=old_select('transaction_status','Draft')?> value="Draft">Draft</option>
				  <option <?=old_select('transaction_status','Pending')?> value="Pending">Pending</option>
				  <option <?=old_select('transaction_status','Approved','Approved')?> value="Approved">Approved</option>
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
                <button
                  type="submit"
                  class="btn btn-danger"
                  id="saveBtn"
                  disabled
                >
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
				<tr><th colspan="3" class="text-center"><p class="text-info mb-1" id="required-currency-total">Required total: 0.00</p></th></tr>
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

            <div id="modalMismatch" class="text-error text-danger mb-2"></div>
	    </div>
	  </div>
	</div>

	<div class="modal fade" id="cashinChangeModal" tabindex="-1" aria-labelledby="cashinChangeLabel" aria-hidden="true">
	  <div class="modal-dialog modal-md">
	    <div class="modal-content border-0 shadow-lg">
	      <div class="modal-header border-0 pb-0">
	        <h5 class="modal-title fw-bold" id="cashinChangeLabel">
	          <i class="fa fa-exchange-alt me-2"></i> Give Change
	        </h5>
	        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
	      </div>
	      <div class="modal-body">
	        <div class="alert alert-info py-2 px-3 mb-3">
	          <div class="fw-semibold">Change required: <span id="cashinChangeRequiredText">GHS 0.00</span></div>
	          <small class="text-muted">The full received denominations will be added first, then the selected change denominations will be deducted from the cashdrawer.</small>
	        </div>
	        <div class="alert alert-light border py-2 px-3 mb-3" style="font-size:0.9rem;">
	          <strong>Available after receipt:</strong>
	          <span id="cashinAvailableDenosText" class="ms-1 text-muted">No denominations available.</span>
	        </div>
	        <div id="cashinChangeRows" class="border rounded p-2" style="max-height: 200px; overflow-y: auto; background-color: var(--bs-body-bg);"></div>
	        <div class="d-flex align-items-center gap-2 mt-2">
	          <small class="text-muted">Selected change: <strong id="cashinChangeSelectedText" class="text-danger">GHS 0.00</strong></small>
	          <small class="text-muted ms-auto">Net cash: <strong id="cashinChangeNetText">GHS 0.00</strong></small>
	        </div>
	        <div class="mt-1">
	          <small id="cashinChangeBalanceText" class="text-warning">Add GHS 0.00 more to balance.</small>
	        </div>
	        <div class="d-flex justify-content-end mt-2">
	          <button type="button" class="btn btn-sm" id="cashinAddChangeRowBtn" style="background-color:#dc3545; color:white; border:none; padding:0.35rem 0.75rem;">
	            <i class="fa fa-plus me-1"></i> Add
	          </button>
	        </div>
	        <div class="alert py-2 px-3 mt-3 mb-0" id="cashinChangeAlert" role="alert">
	          <span id="cashinChangeAlertText">Select the denominations to give out.</span>
	        </div>
	      </div>
	      <div class="modal-footer border-0 pt-0">
	        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
	        <button type="button" class="btn btn-sm btn-primary" id="cashinApplyChangeBtn" disabled>
	          <i class="fa fa-check me-1"></i> Apply change
	        </button>
	      </div>
	    </div>
	  </div>
	</div>

<?php else:?>
	<div class="alert alert-danger text-center">
		Access denied. You dont have permission for this action
	</div>
<?php endif?>

<script>
	<?=get_app_image_cropper_inline_source()?>
	const oldNetworkValue = "<?=htmlspecialchars((string)old_value('network'), ENT_QUOTES, 'UTF-8')?>";
	const oldCategoryValue = "<?=htmlspecialchars((string)old_value('category'), ENT_QUOTES, 'UTF-8')?>";
	const categoryOptions = {
		"Deposit": [
			{ value: "", text: "---Select---" },
			{ value: "self", text: "self" },
			{ value: "3rd party", text: "3rd party" }
		]
	};

	const sourceOptions = {
		
		"Deposit": [
			{ value: "", text: "---Select---" },
			{ value: "Absa Bank", text: "Absa Bank" },
			{ value: "Access Bank", text: "Access Bank" },
			{ value: "ADB", text: "ADB" },
			{ value: "Apex Nafana Bank", text: "Apex Nafana Bank" },
			{ value: "Apex Suma Bank", text: "Apex Suma Bank" },
			{ value: "Cal Bank", text: "Cal Bank" },
			{ value: "Ecobank", text: "Ecobank" },
			{ value: "GCB", text: "GCB" },
			{ value: "GT Bank", text: "Ecobank" },
			{ value: "Fidelity", text: "Fidelity" },
			{ value: "First Bank Ghana (FBG)", text: "First Bank Ghana (FBG)" },
			{ value: "First National Bank (FBN)", text: "First National Bank (FBN)" },
			{ value: "OmniBSIC", text: "OmniBSIC" },
			{ value: "UBA", text: "UBA" },
		]
	};

	const transactionType = document.getElementById('transaction_type'); // Corrected ID selection
	const categorySelect = document.getElementById('category');
	const amountPaidInput = document.getElementById('amount_paid');
	const networkSelect = document.getElementById('network');
	const categoryDiv = document.querySelector('.category');

    const loadInput       = document.getElementById('load');
    const paidInput       = document.getElementById('amount_paid');
    const phoneInput    = document.getElementById('phone');
    const account_numberInput    = document.getElementById('account_number');
    const saveBtn         = document.getElementById('saveBtn');
    const modalMismatch    = document.getElementById('modalMismatch');
    const changeDenominationsField = document.getElementById('change_denominations');
    const changeRequiredField = document.getElementById('change_required');
    const cashReceivedAmountField = document.getElementById('cash_received_amount');
    const cashdrawerDenosField = document.getElementById('cashdrawerDenos');
    const cashdrawerBalancesField = document.getElementById('cashdrawerBalances');
    const changeModalEl = document.getElementById('cashinChangeModal');
    const cashinChangeModal = (function(){
        if (!changeModalEl) return null;
        if (window.bootstrap && typeof bootstrap.Modal === 'function') {
            return new bootstrap.Modal(changeModalEl);
        }

        const createBackdrop = function() {
            if (document.querySelector('.modal-backdrop')) return;
            const backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            document.body.appendChild(backdrop);
        };
        const removeBackdrop = function() {
            const backdrop = document.querySelector('.modal-backdrop');
            if (backdrop) {
                backdrop.remove();
            }
        };
        const show = function() {
            changeModalEl.classList.add('show');
            changeModalEl.style.display = 'block';
            changeModalEl.setAttribute('aria-modal', 'true');
            changeModalEl.removeAttribute('aria-hidden');
            document.body.classList.add('modal-open');
            createBackdrop();
        };
        const hide = function() {
            changeModalEl.classList.remove('show');
            changeModalEl.style.display = 'none';
            changeModalEl.setAttribute('aria-hidden', 'true');
            changeModalEl.removeAttribute('aria-modal');
            document.body.classList.remove('modal-open');
            removeBackdrop();
        };
        const closeButton = changeModalEl.querySelector('[data-bs-dismiss="modal"]');
        if (closeButton) {
            closeButton.addEventListener('click', function() {
                hide();
            });
        }
        return { show, hide };
    })();
    const cashinChangeSummary = document.getElementById('cashinChangeSummary');
    const cashinChangeSummaryText = document.getElementById('cashinChangeSummaryText');
    const cashinChangeSummaryNote = document.getElementById('cashinChangeSummaryNote');
    const cashinOpenChangeBtn = document.getElementById('cashinOpenChangeBtn');
    const cashinClearChangeBtn = document.getElementById('cashinClearChangeBtn');
    const cashinChangeRows = document.getElementById('cashinChangeRows');
    const cashinAddChangeRowBtn = document.getElementById('cashinAddChangeRowBtn');
    const cashinApplyChangeBtn = document.getElementById('cashinApplyChangeBtn');
    const cashinChangeRequiredText = document.getElementById('cashinChangeRequiredText');
    const cashinAvailableDenosText = document.getElementById('cashinAvailableDenosText');
    const cashinChangeSelectedText = document.getElementById('cashinChangeSelectedText');
    const cashinChangeNetText = document.getElementById('cashinChangeNetText');
    const cashinChangeBalanceText = document.getElementById('cashinChangeBalanceText');
    const cashinChangeAlert = document.getElementById('cashinChangeAlert');
    const cashinChangeAlertText = document.getElementById('cashinChangeAlertText');
    const cashDenominationOptions = [200, 100, 50, 20, 10, 5, 2, 1, 0.5, 0.2, 0.1];

    // error placeholders
    const mismatchError    = document.getElementById('paidMismatchError');
    const loadError       = document.getElementById('loadError');
    const paidError       = document.getElementById('paidError');
    const networkError    = document.getElementById('networkError');
    const phoneError    = document.getElementById('phoneError');
    const account_numberError    = document.getElementById('account_numberError');

	function roundMoney(value) {
		return Math.round((Number(value) || 0) * 100) / 100;
	}

	function formatMoney(value) {
		return roundMoney(value).toFixed(2);
	}

	function valuesMatch(a, b) {
		return Math.abs(roundMoney(a) - roundMoney(b)) <= 0.01;
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
				carry.push({ denomination, count, volume: roundMoney(denomination * count) });
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

	function getDrawerAvailabilityMap() {
		return aggregateDenominationCounts(parseJsonArray(cashdrawerDenosField));
	}

	function getSelectedChangeRows() {
		return sanitizeChangeRows(parseJsonArray(changeDenominationsField));
	}

	function getSelectedChangeTotal(rows) {
		return roundMoney((rows || []).reduce((sum, item) => sum + (roundMoney(item.denomination) * (parseInt(item.count, 10) || 0)), 0));
	}

	function getCashReceivedAmount() {
		const hiddenValue = roundMoney(cashReceivedAmountField && cashReceivedAmountField.value);
		if (hiddenValue > 0) {
			return hiddenValue;
		}
		return roundMoney(paidInput.value);
	}

	function getRequiredChangeAmount() {
		const received = getCashReceivedAmount();
		const load = roundMoney(loadInput.value);
		if (received > load + 0.01) {
			return roundMoney(received - load);
		}
		return 0;
	}

	function resetChangeSelection() {
		if (changeDenominationsField) {
			changeDenominationsField.value = '[]';
		}
		if (changeRequiredField) {
			changeRequiredField.value = '0.00';
		}
		syncChangeSummary();
	}

	function syncChangeSummary() {
		if (!cashinChangeSummary) {
			return;
		}
		const received = getCashReceivedAmount();
		const load = roundMoney(loadInput.value);
		const required = getRequiredChangeAmount();
		const selectedRows = getSelectedChangeRows();
		const selectedTotal = getSelectedChangeTotal(selectedRows);
		const netCash = roundMoney(received - selectedTotal);
		const hasSelection = selectedRows.length > 0;
		const isBalanced = required > 0 && hasSelection && valuesMatch(selectedTotal, required) && valuesMatch(netCash, load);

		if (required <= 0.01) {
			cashinChangeSummary.classList.add('d-none');
			cashinChangeSummary.classList.remove('alert-warning', 'alert-success', 'alert-danger');
			if (cashinClearChangeBtn) {
				cashinClearChangeBtn.classList.add('d-none');
			}
			return;
		}

		cashinChangeSummary.classList.remove('d-none', 'alert-warning', 'alert-success', 'alert-danger');
		cashinChangeSummary.classList.add(isBalanced ? 'alert-success' : (hasSelection ? 'alert-danger' : 'alert-warning'));
		cashinChangeSummaryText.textContent = 'Change required: GHS ' + formatMoney(required);

		if (isBalanced) {
			cashinChangeSummaryNote.textContent = 'Received GHS ' + formatMoney(received) + '. Change selected GHS ' + formatMoney(selectedTotal) + '. Net cash GHS ' + formatMoney(netCash) + ' matches the load.';
		} else if (hasSelection) {
			cashinChangeSummaryNote.textContent = 'Selected change totals GHS ' + formatMoney(selectedTotal) + '. Adjust it to exactly GHS ' + formatMoney(required) + '.';
		} else {
			cashinChangeSummaryNote.textContent = 'Select the denominations to give out so the net cash matches the deposit load.';
		}
		if (cashinOpenChangeBtn) {
			cashinOpenChangeBtn.textContent = hasSelection ? 'Edit change' : 'Select change';
		}
		if (cashinClearChangeBtn) {
			cashinClearChangeBtn.classList.toggle('d-none', !hasSelection);
		}
	}

	function syncChangeRequirement() {
		const received = roundMoney(paidInput.value);
		if (cashReceivedAmountField) {
			cashReceivedAmountField.value = received > 0 ? formatMoney(received) : '';
		}
		const required = getRequiredChangeAmount();
		const previousRequired = roundMoney(changeRequiredField && changeRequiredField.value);
		if (required <= 0.01) {
			resetChangeSelection();
			return { received, load: roundMoney(loadInput.value), required: 0 };
		}
		if (changeRequiredField) {
			changeRequiredField.value = formatMoney(required);
		}
		if (!valuesMatch(previousRequired, required) && getSelectedChangeRows().length > 0 && changeDenominationsField) {
			changeDenominationsField.value = '[]';
		}
		syncChangeSummary();
		return { received, load: roundMoney(loadInput.value), required };
	}

	function buildChangeSelectOptions(selectedValue) {
		const currentKey = normalizeDenominationKey(selectedValue);
		return ['<option value="">---Amount (GHS)---</option>'].concat(cashDenominationOptions.map(value => {
			const key = normalizeDenominationKey(value);
			const selected = key === currentKey ? ' selected' : '';
			return '<option value="' + formatMoney(value) + '"' + selected + '>' + formatMoney(value) + '</option>';
		})).join('');
	}

	function getRawChangeRowsFromModal() {
		if (!cashinChangeRows) {
			return [];
		}
		return Array.from(cashinChangeRows.querySelectorAll('.cashin-change-row')).map(row => {
			const valueEl = row.querySelector('.cashin-change-value');
			const countEl = row.querySelector('.cashin-change-count');
			return { denomination: valueEl ? valueEl.value : '', count: countEl ? countEl.value : '' };
		});
	}

	function getChangeRowsFromModal() {
		return sanitizeChangeRows(getRawChangeRowsFromModal());
	}

	function syncChangeSelectOptions() {
		if (!cashinChangeRows) {
			return;
		}
		const selects = Array.from(cashinChangeRows.querySelectorAll('.cashin-change-value'));
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
		if (!cashinChangeRows || !cashinApplyChangeBtn) {
			return;
		}
		syncChangeSelectOptions();
		const required = getRequiredChangeAmount();
		const received = getCashReceivedAmount();
		const load = roundMoney(loadInput.value);
		const rows = getChangeRowsFromModal();
		const selectedTotal = getSelectedChangeTotal(rows);
		const netCash = roundMoney(received - selectedTotal);
		const balanceGap = roundMoney(required - selectedTotal);
		const availability = getDrawerAvailabilityMap();
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
		if (cashinChangeRequiredText) {
			cashinChangeRequiredText.textContent = 'GHS ' + formatMoney(required);
		}
		if (cashinChangeSelectedText) {
			cashinChangeSelectedText.textContent = 'GHS ' + formatMoney(selectedTotal);
		}
		if (cashinChangeNetText) {
			cashinChangeNetText.textContent = 'GHS ' + formatMoney(netCash);
		}
		if (cashinChangeBalanceText) {
			cashinChangeBalanceText.classList.remove('text-muted', 'text-warning', 'text-danger', 'text-success');
			if (required <= 0.01) {
				cashinChangeBalanceText.textContent = 'No extra change is needed for this transaction.';
				cashinChangeBalanceText.classList.add('text-muted');
			} else if (balanceGap > 0.01) {
				cashinChangeBalanceText.textContent = 'Add GHS ' + formatMoney(balanceGap) + ' more to balance.';
				cashinChangeBalanceText.classList.add('text-warning');
			} else if (balanceGap < -0.01) {
				cashinChangeBalanceText.textContent = 'Reduce change by GHS ' + formatMoney(Math.abs(balanceGap)) + ' to balance.';
				cashinChangeBalanceText.classList.add('text-danger');
			} else {
				cashinChangeBalanceText.textContent = 'Balanced. Change selection matches the required amount.';
				cashinChangeBalanceText.classList.add('text-success');
			}
		}
		if (cashinAvailableDenosText) {
			const labels = Object.keys(availability)
				.filter(key => (parseInt(availability[key], 10) || 0) > 0)
				.sort((a, b) => parseFloat(b) - parseFloat(a))
				.map(key => 'GHS ' + key + ' (' + availability[key] + ' units)');
			cashinAvailableDenosText.textContent = labels.length ? labels.join(', ') : 'No denominations available.';
		}
		if (required <= 0.01) {
			cashinChangeAlert.className = 'alert alert-info py-2 px-3 mt-3 mb-0';
			cashinChangeAlertText.textContent = 'No change is required for this transaction.';
			cashinApplyChangeBtn.disabled = true;
			return;
		}
		if (received + 0.01 < load) {
			cashinChangeAlert.className = 'alert alert-warning py-2 px-3 mt-3 mb-0';
			cashinChangeAlertText.textContent = 'Amount received is below the deposit load, so change cannot be configured yet.';
			cashinApplyChangeBtn.disabled = true;
			return;
		}
		if (!rows.length) {
			cashinChangeAlert.className = 'alert alert-info py-2 px-3 mt-3 mb-0';
			cashinChangeAlertText.textContent = 'Select the denominations to give out. Add GHS ' + formatMoney(required) + ' more to balance.';
			cashinApplyChangeBtn.disabled = true;
			return;
		}
		if (insufficient.length) {
			cashinChangeAlert.className = 'alert alert-danger py-2 px-3 mt-3 mb-0';
			cashinChangeAlertText.textContent = 'Insufficient denominations after receipt: ' + insufficient.join(' | ');
			cashinApplyChangeBtn.disabled = true;
			return;
		}
		if (!valuesMatch(selectedTotal, required) || !valuesMatch(netCash, load)) {
			cashinChangeAlert.className = 'alert alert-warning py-2 px-3 mt-3 mb-0';
			cashinChangeAlertText.textContent = balanceGap > 0.01 ? 'Add GHS ' + formatMoney(balanceGap) + ' more change so the net cash equals the load.' : 'Change must total exactly GHS ' + formatMoney(required) + ' so the net cash equals the load.';
			cashinApplyChangeBtn.disabled = true;
			return;
		}
		cashinChangeAlert.className = 'alert alert-success py-2 px-3 mt-3 mb-0';
		cashinChangeAlertText.textContent = 'Change selection is balanced and ready to apply.';
		cashinApplyChangeBtn.disabled = false;
	}

	function bindChangeRowEvents() {
		if (!cashinChangeRows) {
			return;
		}
		cashinChangeRows.querySelectorAll('.cashin-change-value, .cashin-change-count').forEach(input => {
			input.addEventListener('input', updateChangeModalState);
			input.addEventListener('change', updateChangeModalState);
		});
		cashinChangeRows.querySelectorAll('.cashin-delete-change').forEach(button => {
			button.addEventListener('click', function () {
				const rows = cashinChangeRows.querySelectorAll('.cashin-change-row');
				if (rows.length <= 1) {
					const row = this.closest('.cashin-change-row');
					if (row) {
						const select = row.querySelector('.cashin-change-value');
						const count = row.querySelector('.cashin-change-count');
						if (select) select.value = '';
						if (count) count.value = '';
					}
				} else {
					const row = this.closest('.cashin-change-row');
					if (row) row.remove();
				}
				updateChangeModalState();
			});
		});
	}

	function renderChangeRows(seedRows) {
		if (!cashinChangeRows) {
			return;
		}
		const rows = Array.isArray(seedRows) ? seedRows : [];
		const sourceRows = rows.length ? rows : [{ denomination: '', count: '' }];
		cashinChangeRows.innerHTML = sourceRows.map(row => {
			const denomination = row && row.denomination !== undefined && row.denomination !== null && row.denomination !== '' ? formatMoney(row.denomination) : '';
			const count = row && row.count !== undefined && row.count !== null && row.count !== '' ? String(row.count) : '';
			return `
				<div class="cashin-change-row mb-2">
					<div class="row g-1 align-items-end">
						<div class="col-5">
							<select class="form-select form-control-sm cashin-change-value" style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
								${buildChangeSelectOptions(denomination)}
							</select>
						</div>
						<div class="col-5">
							<input type="number" class="form-control form-control-sm cashin-change-count" placeholder="Count" step="1" min="1" value="${count}" style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
						</div>
						<div class="col-2">
							<button type="button" class="btn btn-sm btn-outline-danger cashin-delete-change" style="padding:0.25rem 0.5rem;"><i class="fa fa-trash"></i></button>
						</div>
					</div>
				</div>`;
		}).join('');
		bindChangeRowEvents();
		syncChangeSelectOptions();
		updateChangeModalState();
	}

	function showChangeModal() {
		if (!cashinChangeModal) {
			return;
		}
		const state = syncChangeRequirement();
		if (state.required <= 0.01 || state.received + 0.01 < state.load) {
			return;
		}
		renderChangeRows(getSelectedChangeRows());
		updateChangeModalState();
		cashinChangeModal.show();
	}

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
			if (oldCategoryValue) {
				categorySelect.value = oldCategoryValue;
			}
		} else {
			categorySelect.classList.add('d-none'); // Hide if no options are available
			categoryDiv.classList.add('d-none'); // Hide if no options are available
		}
		showFields();
	}

	function updateSourceOptions() {
		const transactionTypeValue = transactionType.value;
		networkSelect.innerHTML = ""; // Clear current options

		// Populate options based on selected transaction type
		if (sourceOptions[transactionTypeValue]) {
			sourceOptions[transactionTypeValue].forEach(option => {
				const opt = document.createElement('option');
				opt.value = option.value;
				opt.textContent = option.text;
				networkSelect.appendChild(opt);
			});
			if (oldNetworkValue) {
				networkSelect.value = oldNetworkValue;
			}
		} 
		showFields();
	}

	function showFields() {
		const networkValue = networkSelect.value;
		const transactionTypeValue = transactionType.value;
		const categoryValue = categorySelect.value;
		const account_number = document.querySelector('.account_number');
		const destination_bank = document.querySelector('.destination_bank');
		const transaction_status = document.querySelector('.transaction_status');

		account_number.classList.add('d-none');
		destination_bank.classList.add('d-none');
		transaction_status.classList.add('d-none');

		// Show fields based on selected category for Deposit
		if (transactionTypeValue === 'Deposit') {
			account_number.classList.remove('d-none');
			transaction_status.classList.remove('d-none');
		}
	}

	// Initialize on page load
	document.addEventListener("DOMContentLoaded", function() {

      const countInputs = document.querySelectorAll(".count-input");
	  const totalCountField = document.getElementById("total-count");
	  const totalVolumeField = document.getElementById("total-volume");
	  const denosField = document.querySelector("#denos");
	  const confirmBtn = document.querySelector('.submit');
	  const restoreBtn = document.querySelector('.restore');
	  const resetBtn = document.querySelector('.reset');
	  const staticBackdropModal = new bootstrap.Modal(document.getElementById('staticBackdrop'));
	  
	  function getSeedDenos(){
		const fromField = (denosField && (denosField.value || '').trim() !== '') ? (denosField.value || '').trim() : '';
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

	  let attempted = false;

	  function getCurrentLoadAmount() {
		const field = document.querySelector('#load[name="amount"]') || document.querySelector('input[name="amount"]');
		return parseFloat(String(field ? field.value : '').replace(/,/g, '')) || 0;
	  }

	  function updateRequiredCurrencyTotal() {
		const loadValue = getCurrentLoadAmount();
		const requiredLabel = document.getElementById('required-currency-total');
		if (requiredLabel) requiredLabel.textContent = `Required total: ${loadValue.toFixed(2)}`;
		return loadValue;
	  }

	  function restoreDenominations() {
		const currency = getSeedDenos();
		if (currency && currency.length) {
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
	  }

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

    function updateVolumesAndTotals() {
	  let totalCount = 0;
	  let totalVolume = 0;
	  const currency = [];

	  countInputs.forEach(input => {
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
	        denomination: denomination,
	        count: count,
	        volume: volume,
	      };
	      currency.push(denos);
	    }
	  });

	  totalCountField.value = totalCount;
	  totalVolumeField.value = totalVolume.toFixed(2);
	  updateRequiredCurrencyTotal();

	  // Store serialized currency details in both localStorage and the hidden field
	  const serializedCurrency = JSON.stringify(currency);
	  localStorage.setItem("denosData", serializedCurrency);
	  denosField.value = serializedCurrency;
	}

    // override validateForm:
		function validateForm() {
		    const lv = parseFloat(loadInput.value)||0;
		    const pv = parseFloat(paidInput.value)||0;
		    const nv = networkSelect.value.trim();

		    if (!attempted) {
		        // never show errors until user tries
		        saveBtn.disabled = true;
		        mismatchError.classList.add('d-none');
		        return;
		    }

		    // toggle required-field errors
		    loadError      .classList.toggle('d-none', !!loadInput.value);
		    paidError      .classList.toggle('d-none', !!paidInput.value);
		    networkError   .classList.toggle('d-none', !!networkSelect.value);
		    phoneError   .classList.toggle('d-none', !!phoneInput.value);
		    account_numberError   .classList.toggle('d-none', !!account_numberInput.value);

		    // mismatch
		    const mismatch = (pv !== lv) && paidInput.value;
		    mismatchError .classList.toggle('d-none', !mismatch);

		    // enable Save only if all filled & no mismatch
		    const changeRequired = getRequiredChangeAmount();
		    const selectedRows = getSelectedChangeRows();
		    const selectedTotal = getSelectedChangeTotal(selectedRows);
		    const netCash = roundMoney(getCashReceivedAmount() - selectedTotal);
		    const changeBalanced = changeRequired <= 0.01 || (selectedRows.length > 0 && valuesMatch(selectedTotal, changeRequired) && valuesMatch(netCash, roundMoney(loadInput.value)));
		    const allFilled = loadInput.value && paidInput.value && networkSelect.value && phoneInput.value && account_numberInput.value;
		    			const ok = allFilled && !mismatch && changeBalanced;
			saveBtn.disabled = !ok;
		}

		// Edit-prefill: if denos + amount_paid already exist and match load, allow Save immediately
		try{
			const denosNow = denosField && denosField.value ? String(denosField.value).trim() : '';
			const lvNow = parseFloat(loadInput.value)||0;
			const pvNow = parseFloat(paidInput.value)||0;
			if (denosNow !== '' && lvNow > 0 && pvNow > 0 && Math.abs(pvNow - lvNow) < 0.001) {
				attempted = true;
			}
		}catch(e){}

    confirmBtn.addEventListener('click', function(){
    	attempted = true;                // now show errors
        updateVolumesAndTotals();
		const loadVal = updateRequiredCurrencyTotal();
        const paidVal = parseFloat(totalVolumeField.value)||0;
		document.getElementById('required-currency-total').textContent = `Required total: ${loadVal.toFixed(2)}`;
        const diff = (paidVal - loadVal).toFixed(2);

        if (diff != 0) {
            modalMismatch.textContent = `Mismatch: ${diff>0?'+':'‒'}GH₵${Math.abs(diff)}`;
            modalMismatch.style.display = 'block';
        } else {
            paidInput.value    = paidVal.toFixed(2);
            modalMismatch.style.display = 'none';
            staticBackdropModal.hide();
            const changeState = syncChangeRequirement();
            const selectedRows = getSelectedChangeRows();
            const selectedTotal = getSelectedChangeTotal(selectedRows);
            const netCash = roundMoney(changeState.received - selectedTotal);
            const needsPrompt = !selectedRows.length || !valuesMatch(selectedTotal, changeState.required) || !valuesMatch(netCash, changeState.load);
            if (changeState.required > 0.01 && needsPrompt) {
                setTimeout(showChangeModal, 180);
            }
            validateForm();
        }
    });
	    


	countInputs.forEach(input => {
      input.addEventListener("input", updateVolumesAndTotals);
    });

    restoreBtn.addEventListener('click', function() {
    	// Restore denominations
  		restoreDenominations();
    });

    const modalEl = document.getElementById('staticBackdrop');
		if (modalEl) {
			modalEl.addEventListener('show.bs.modal', function(){
				restoreDenominations();
			});
		}

    resetBtn.addEventListener('click', function() {
    	// Reset denominations
  		resetDenominations();
    });

    // Watch for changes on load, paid (set by modal “Confirm”) and balance
    	loadInput.addEventListener('input', function(){ syncChangeRequirement(); validateForm(); });
	paidInput.addEventListener('change', function(){ syncChangeRequirement(); validateForm(); });  // after modal confirm
	paidInput.addEventListener('input', function(){ syncChangeRequirement(); validateForm(); });
	phoneInput.addEventListener('input', function(){ validateForm(); });
	account_numberInput.addEventListener('input', function(){ validateForm(); });
	networkSelect.addEventListener('change', function(){ validateForm(); });
    const submitDenoBtn = document.querySelector('.submit');
    submitDenoBtn.addEventListener('click', function() {
        // existing code that sets amount_paid = totalVolumeField.value
        // then hide modal...
        // finally:
        validateForm();
    });

	if (cashinOpenChangeBtn) {
		cashinOpenChangeBtn.addEventListener('click', showChangeModal);
	}
	if (cashinClearChangeBtn) {
		cashinClearChangeBtn.addEventListener('click', function(){
			resetChangeSelection();
			validateForm();
		});
	}
	if (cashinAddChangeRowBtn) {
		cashinAddChangeRowBtn.addEventListener('click', function(){
			const rows = getRawChangeRowsFromModal();
			rows.push({ denomination: '', count: '' });
			renderChangeRows(rows);
		});
	}
	if (cashinApplyChangeBtn) {
		cashinApplyChangeBtn.addEventListener('click', function(){
			const rows = getChangeRowsFromModal();
			const required = getRequiredChangeAmount();
			const selectedTotal = getSelectedChangeTotal(rows);
			const netCash = roundMoney(getCashReceivedAmount() - selectedTotal);
			if (!rows.length || !valuesMatch(selectedTotal, required) || !valuesMatch(netCash, roundMoney(loadInput.value))) {
				updateChangeModalState();
				return;
			}
			changeDenominationsField.value = JSON.stringify(rows);
			if (changeRequiredField) {
				changeRequiredField.value = formatMoney(required);
			}
			syncChangeSummary();
			cashinChangeModal.hide();
			validateForm();
		});
	}

	// on Save click:
	saveBtn.addEventListener('click', function(e){
	    attempted = true;
	    syncChangeRequirement();
	    validateForm();
	    if (saveBtn.disabled) {
	        e.preventDefault(); // stop actual submit if invalid
	    }
	});
	loadInput.addEventListener('input', updateRequiredCurrencyTotal);
	loadInput.addEventListener('change', updateRequiredCurrencyTotal);
	try {
		document.getElementById('staticBackdrop').addEventListener('show.bs.modal', updateRequiredCurrencyTotal);
	} catch (e) {}
	updateRequiredCurrencyTotal();

		// Event listeners for real-time updates
		transactionType.addEventListener("change", updateCategoryOptions);
		transactionType.addEventListener("change", updateSourceOptions);
		categorySelect.addEventListener("change", showFields);

		syncChangeRequirement();
    validateForm();
		restoreDenominations();
		updateCategoryOptions();
		updateSourceOptions();
	});

	(function(){
		function bindSimplePreviewCard(){
			const inputEl = document.getElementById('transaction_image');
			const cardEl = document.getElementById('transactionImagePreviewCard');
			const previewEl = document.getElementById('transactionImagePreview');
			const placeholderEl = document.getElementById('transactionImagePreviewPlaceholder');
			if(!inputEl || !cardEl || !previewEl || !placeholderEl || cardEl.dataset.simplePreviewBound === '1'){
				return;
			}

			cardEl.dataset.simplePreviewBound = '1';
			const openPicker = function(event){
				if(event){
					event.preventDefault();
				}
				inputEl.click();
			};

			cardEl.addEventListener('click', openPicker);
			cardEl.addEventListener('keydown', function(event){
				if(event.key === 'Enter' || event.key === ' '){
					openPicker(event);
				}
			});

			inputEl.addEventListener('change', function(){
				const file = inputEl.files && inputEl.files[0] ? inputEl.files[0] : null;
				if(!file){
					return;
				}
				const reader = new FileReader();
				reader.onload = function(loadEvent){
					previewEl.src = loadEvent.target && loadEvent.target.result ? loadEvent.target.result : '';
					previewEl.style.display = 'block';
					placeholderEl.style.display = 'none';
				};
				reader.readAsDataURL(file);
			});
		}

		function bindCropperCard(){
			if(!window.AppImageCropper || typeof window.AppImageCropper.bindPreviewCard !== 'function'){
				bindSimplePreviewCard();
				return;
			}

			window.AppImageCropper.bindPreviewCard({
				inputId: 'transaction_image',
				cardId: 'transactionImagePreviewCard',
				previewId: 'transactionImagePreview',
				placeholderId: 'transactionImagePreviewPlaceholder',
				errorSelector: '.js-transaction-image-error',
				aspectRatio: 1.5,
				maxBytes: window.AppImageCropper.MAX_BYTES,
				modalTitle: 'Crop transaction picture',
				required: true,
				requiredMessage: 'Transaction picture is required'
			});
		}

		bindCropperCard();
		document.addEventListener('app-image-cropper-ready', bindCropperCard);
	})();
</script>
