<?php if(user_can('add_agencybanking')): ?>
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
	<form method="post" enctype="multipart/form-data" id="paymentServiceForm">		
		<div class="row col-md-8 col-lg-5 mx-auto shadow p-2 rounded">

			<?=csrf()?>
			<input type="hidden" id="cashdrawerDenos" value="<?= htmlspecialchars(json_encode($cashdrawerDenos ?? []), ENT_QUOTES, 'UTF-8') ?>">
			<input type="hidden" id="cashdrawerBalances" value="<?= htmlspecialchars(json_encode($cashdrawerBalances ?? []), ENT_QUOTES, 'UTF-8') ?>">

			<h4 class="text-center">
				<span><img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:60px;max-height: 60px;object-fit: cover;"></span>
				New Payment
			</h4>

			<div class="col-md-12">
				<label for="category" class="form-label">Category</label>
				<select class="form-select category" id="category" name="category" >
				  <option value="">---Select---</option>
				  <option <?=old_select('category','UTILITY')?> value="UTILITY">UTILITY</option>
				  <option <?=old_select('category','TV & ENTERTAINMENT')?> value="TV & ENTERTAINMENT">TV & ENTERTAINMENT</option>
				  <option <?=old_select('category','TERTIARY')?> value="TERTIARY">TERTIARY</option>
				  <option <?=old_select('category','INSURANCE')?> value="INSURANCE">INSURANCE</option>
				</select>
				<?php if(!empty($errors['category'])): ?>
				  <small class="text-danger"><?=$errors['category']?></small>
				<?php endif ?>
			</div>

			<div class="col-md-12">
				<label for="institution" class="form-label">Institution</label>
				<select class="form-select" name="institution" id="institution" data-old="<?=esc(old_value('institution'))?>">	
				  <!-- Options dynamically display -->
				</select>
				<?php if(!empty($errors['institution'])): ?>
				  <small class="text-danger"><?=$errors['institution']?></small>
				<?php endif ?>
			</div>

			<div class="col-md-12">
			  <label for="customer_phone" class="form-label">Customer's Phone</label>
			  <input name="customer_phone" value="<?=old_value('customer_phone')?>" type="text" class="form-control" id="customer_phone" placeholder="Enter customer's phone number">				
				<?php if(!empty($errors['customer_phone'])): ?>
				  <small class="text-danger"><?=$errors['customer_phone']?></small>
				<?php endif ?>
			</div>

			<div class="col-md-12 customer_id">
			  <label for="customer_id" class="form-label">Customer's ID</label>
			  <input name="customer_id" value="<?=old_value('customer_id')?>" type="text" class="form-control" id="customer_id" placeholder="Enter customer's ID">			
				<?php if(!empty($errors['customer_id'])): ?>
				  <small class="text-danger"><?=$errors['customer_id']?></small>
				<?php endif ?>
			</div>

			<div class="col-md-12 ">
			  <label for="description" class="form-label">Description</label>
				<textarea rows="2" id="description" class="form-control" name="description"  placeholder="Write a brief description for this transaction"><?=old_value('description')?></textarea>			
				<?php if(!empty($errors['description'])):?>
				  <small class="text-danger"><?=$errors['description']?></small>
				<?php endif?>
			</div>

			<div class="col-md-12 payment_medium">
			  <label for="payment_medium" class="form-label">Payment Medium</label>
			  <select class="form-select" name="payment_medium" id="payment_medium">	
				  <option value="">---Select---</option>
				  <option <?=old_select('payment_medium','Telecos')?> value="Telecos">Telecos</option>
				  <option <?=old_select('payment_medium','Bank')?> value="Bank">Bank</option>
			  </select>
			  <?php if(!empty($errors['payment_medium'])): ?>
				  <small class="text-danger"><?=$errors['payment_medium']?></small>
			  <?php endif ?>
				  </div>	

			<div class="mb-2 telco d-none" id="telco-list">
			  <label for="network" class="form-label">E-cash network</label>
			  <select class="form-select" name="network" id="network">
				  <option value="">---Select---</option>
				  <option <?=old_select('network','MTN')?> value="MTN">MTN</option>
				  <option <?=old_select('network','AT')?> value="AT">AT</option>
				  <option <?=old_select('network','Telecel')?> value="Telecel">Telecel</option>
				  <option <?=old_select('network','G-Money')?> value="G-Money">G-Money</option>
				  <option <?=old_select('network','GhanaPay')?> value="GhanaPay">GhanaPay</option>
				  <option <?=old_select('network','GHQR')?> value="GHQR">GHQR</option>
			  </select>
				<?php if(!empty($errors['network'])):?>
				  <small class="text-danger"><?=$errors['network']?></small>
				<?php endif?>
			</div>

			<div class="mb-2 bank d-none" id="bank-list">
			  <label for="bank" class="form-label">Selected bank</label>
			  <select class="form-select bank" name="bank">
				  <option value="">---Select---</option>
				  <option <?=old_select('bank','ABSA')?> value="ABSA">ABSA</option>
				  <option <?=old_select('bank','Access Bank')?> value="Access Bank">Access Bank</option>
				  <option <?=old_select('bank','ADB')?> value="ADB">ADB</option>
				  <option <?=old_select('bank','APEX BANK')?> value="APEX BANK">APEX BANK</option>
				  <option <?=old_select('bank','Bank of Africa')?> value="Bank of Africa">Bank of Africa</option>
				  <option <?=old_select('bank','CBG')?> value="CBG">CBG</option>
				  <option <?=old_select('bank','Cal Bank')?> value="Cal Bank">Cal Bank</option>
				  <option <?=old_select('bank','Ecobank')?> value="Ecobank">Ecobank</option>
				  <option <?=old_select('bank','Fidelity')?> value="Fidelity">Fidelity</option>
				  <option <?=old_select('bank','First Atlantic Bank')?> value="First Atlantic Bank">First Atlantic Bank</option>
				  <option <?=old_select('bank','First Bank Ghana')?> value="First Bank Ghana">First Bank Ghana</option>
				  <option <?=old_select('bank','FBN')?> value="FBN">FBN</option>
				  <option <?=old_select('bank','FNB')?> value="FNB">FNB</option>
				  <option <?=old_select('bank','Gtbank')?> value="Gtbank">Gtbank</option>
				  <option <?=old_select('bank','GCB')?> value="GCB">GCB</option>
				  <option <?=old_select('bank','NIB')?> value="NIB">NIB</option>
				  <option <?=old_select('bank','Republic Bank')?> value="Republic Bank">Republic Bank</option>
				  <option <?=old_select('bank','OmniBSIC Bank')?> value="OmniBSIC Bank">OmniBSIC Bank</option>
				  <option <?=old_select('bank','Society General')?> value="Society General">Society General</option>
				  <option <?=old_select('bank','Stanbic Bank')?> value="Stanbic Bank">Stanbic Bank</option>
				  <option <?=old_select('bank','Prudential Bank')?> value="Prudential Bank">Prudential Bank</option>
				  <option <?=old_select('bank','Standard Charted')?> value="Standard Charted">Standard Charted</option>
				  <option <?=old_select('bank','Zenit Bank')?> value="Zenit Bank">Zenit Bank</option>
				  <option <?=old_select('bank','umb')?> value="umb">umb</option>
				  <option <?=old_select('bank','Zenit Bank')?> value="Zenit Bank">Zenit Bank</option>
			  </select>
				<?php if(!empty($errors['bank'])):?>
				  <small class="text-danger"><?=$errors['bank']?></small>
				<?php endif?>
			</div>

			<div class="mb-2 bank d-none" id="assigned-list">
			  <label for="assigned_to" class="form-label">Assigned manager / user</label>
			  <select class="form-select" name="assigned_to" id="assigned_to">
				  <option value="">---Select---</option>
				  <?php foreach(($eligible_assignees ?? []) as $au): ?>
					  <option <?=old_select('assigned_to', $au->id ?? '')?> value="<?=$au->id ?? ''?>"><?=esc(($au->first_name ?? '').' '.($au->last_name ?? ''))?></option>
				  <?php endforeach; ?>
			  </select>
				<?php if(!empty($errors['assigned_to'])):?>
				  <small class="text-danger"><?=$errors['assigned_to']?></small>
				<?php endif?>
			</div>

			<div class="col-md-12 mt-2">
			      <label for="amount_paid" class="form-label">Amount payable</label>
			      <input name="amount_paid" value="<?=old_value('amount_paid')?>" type="number" step="0.01" class="form-control" id="amount_paid" placeholder="Enter amount paid by client">
			      <?php if(!empty($errors['amount_paid'])):?>
			        <small class="text-danger"><?=$errors['amount_paid']?></small>
			      <?php endif?>
			    </div>

			<!-- Amount Input with Calculator Modal Trigger -->
		    <div class="col-md-12">
		      <label for="amount" class="form-label">Amount (from cash denominations)</label>
		      <label class="input-group" data-bs-toggle="modal" data-bs-target="#staticBackdrop"> 
		        <i class="fa-solid fa-calculator input-group-text text-warning"></i>
		          <input name="amount" value="<?=old_value('amount')?>" type="number" step="0.1" class="form-control" id="amount" placeholder="Enter amount" readonly>
	          	<input type="hidden" id="denos" name="denos" value="<?=htmlspecialchars(old_value('denos'), ENT_QUOTES, 'UTF-8')?>" />
	          <input type="hidden" id="change_denominations" name="change_denominations" value="<?=htmlspecialchars(old_value('change_denominations'), ENT_QUOTES, 'UTF-8')?>" />
	          <input type="hidden" id="change_required" name="change_required" value="<?=htmlspecialchars(old_value('change_required'), ENT_QUOTES, 'UTF-8')?>" />
	          <input type="hidden" id="cash_received_amount" name="cash_received_amount" value="<?=htmlspecialchars(old_value('cash_received_amount') ?: old_value('amount'), ENT_QUOTES, 'UTF-8')?>" />
		      </label>
		      <?php if(!empty($errors['amount'])):?>
		        <small class="text-danger"><?=$errors['amount']?></small>
		      <?php endif?>
		      <?php if(!empty($errors['change_denominations'])):?>
		        <small class="text-danger d-block"><?=$errors['change_denominations']?></small>
		      <?php endif?>
		      <?php if(!empty($errors['change_required'])):?>
		        <small class="text-danger d-block"><?=$errors['change_required']?></small>
		      <?php endif?>
		      <small class="text-danger js-amount-error d-block"></small>
		      <div id="paymentChangeSummary" class="alert alert-warning py-2 px-3 mt-2 d-none">
		        <div class="d-flex flex-wrap align-items-center gap-2">
		          <div class="me-auto">
		            <div class="fw-semibold" id="paymentChangeSummaryText">Change required: GHS 0.00</div>
		            <small class="text-muted" id="paymentChangeSummaryNote">Select the denominations to give out so the net cash matches the payable amount.</small>
		          </div>
		          <button type="button" class="btn btn-sm btn-outline-primary" id="paymentOpenChangeBtn">Select change</button>
		          <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="paymentClearChangeBtn">Clear</button>
		        </div>
		      </div>
		    </div>

			<div class="col-md-12">
			  <label for="transaction_image" class="form-label">Transaction image</label>
			  <div class="mt-2" id="transactionImagePreviewWrap">
					<div class="transaction-preview-card" id="transactionImagePreviewCard" role="button" tabindex="0">
						<div class="transaction-preview-placeholder" id="transactionImagePreviewPlaceholder">
							<i class="fa-solid fa-cloud-arrow-up" style="font-size:1.4rem;"></i>
							<div class="title">Upload transaction picture</div>
							<div class="hint">Tap to choose an image</div>
						</div>
						<img id="transactionImagePreview" src="" alt="Transaction message picture" style="display:none;">
						<div class="transaction-preview-overlay">
							<span class="transaction-preview-badge"><i class="fa-solid fa-camera"></i> Change</span>
						</div>
					</div>
					<small class="text-muted d-block mt-1 text-center">Tap the preview to change the image</small>
			  </div>
			  <small class="text-danger js-transaction-image-error text-center d-block"></small>
			  <input name="transaction_image" type="file" class="visually-hidden-file" id="transaction_image" accept="image/*">
			  <?php if(!empty($errors['transaction_image'])):?>
				<small class="text-danger"><?=$errors['transaction_image']?></small>
			  <?php endif?>
			</div>

			<div class="col-md-12 transaction_status d-none">
			  <label for="transaction_status" class="form-label">Status</label>
			  <input type="hidden" name="transaction_status" value="Approved">
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
				<button class="btn btn-danger" id="save" type="submit">
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
	        <button type="button" class="btn btn-danger reset" >Reset</button>
	        <button type="button" class="btn btn-warning restore" >Restore</button>
	        <button type="button" class="btn btn-primary submit">Confirm</button>
	      </div>
	    </div>
	  </div>
	</div>

  <div class="modal fade" id="paymentChangeModal" tabindex="-1" aria-labelledby="paymentChangeLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold" id="paymentChangeLabel">
            <i class="fa fa-exchange-alt me-2"></i> Give Change
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-info py-2 px-3 mb-3">
            <div class="fw-semibold">Change required: <span id="paymentChangeRequiredText">GHS 0.00</span></div>
            <small class="text-muted">The full received denominations will be added first, then the selected change denominations will be deducted from the cashdrawer.</small>
          </div>

          <div class="alert alert-light border py-2 px-3 mb-3" style="font-size:0.9rem;">
            <strong>Available after receipt:</strong>
            <span id="paymentAvailableDenosText" class="ms-1 text-muted">No denominations available.</span>
          </div>

          <div id="paymentChangeRows" class="border rounded p-2" style="max-height: 200px; overflow-y: auto; background-color: var(--bs-body-bg);"></div>

          <div class="d-flex align-items-center gap-2 mt-2">
            <small class="text-muted">Selected change: <strong id="paymentChangeSelectedText" class="text-danger">GHS 0.00</strong></small>
            <small class="text-muted ms-auto">Net cash: <strong id="paymentChangeNetText">GHS 0.00</strong></small>
          </div>
          <div class="mt-1">
            <small id="paymentChangeBalanceText" class="text-warning">Add GHS 0.00 more to balance.</small>
          </div>

          <div class="d-flex justify-content-end mt-2">
            <button type="button" class="btn btn-sm" id="paymentAddChangeRowBtn" style="background-color:#dc3545; color:white; border:none; padding:0.35rem 0.75rem;">
              <i class="fa fa-plus me-1"></i> Add
            </button>
          </div>

          <div class="alert py-2 px-3 mt-3 mb-0" id="paymentChangeAlert" role="alert">
            <span id="paymentChangeAlertText">Select the denominations to give out.</span>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-sm btn-primary" id="paymentApplyChangeBtn" disabled>
            <i class="fa fa-check me-1"></i> Apply change
          </button>
        </div>
      </div>
    </div>
  </div>

<?php else: ?>
	<div class="alert alert-danger text-center">
		Access denied. You don’t have permission for this action.
	</div>
<?php endif ?>

<script>
	const categoryOptions = {
		"UTILITY": [
			{ value: "", text: "---Select---" },
			{ value: "GHANA WATER", text: "GHANA WATER" },
			{ value: "NEDCO/VRA PREPAID", text: "NEDCO/VRA PREPAID" }
		],
		"TV & ENTERTAINMENT": [
			{ value: "", text: "---Select---" },
			{ value: "DSTV", text: "DSTV" },
			{ value: "GOTV", text: "GOTV" },
			{ value: "CANAL+", text: "CANAL+" },
			{ value: "HD+", text: "HD+" },
			{ value: "STARTIMES", text: "STARTIMES" },
		],
		"TERTIARY": [
			{ value: "", text: "---Select---" },
			{ value: "KNUST - Regular student", text: "KNUST - Regular student" },
			{ value: "KNUST - Distance Learning (IDL)", text: "KNUST - Distance Learning (IDL)" },
			{ value: "Accra Technical University ( ATU )", text: "Accra Technical University ( ATU )" },
			{ value: "University of Energy and natural resources ( UENR)", text: "University of Energy and natural resources ( UENR)" },
			{ value: "University of Media, Arts and Communication (UniMAC)", text: "University of Media, Arts and Communication (UniMAC)" },
			{ value: "Sunyani Technical University ( STU)", text: "Sunyani Technical University ( STU)" },
			{ value: "University of mines and technology ( UMAT )", text: "University of mines and technology ( UMAT )" },
			{ value: "C.K Tedam University of Technology and Applied Science ( CKT- UTAS )", text: "C.K Tedam University of Technology and Applied Science ( CKT- UTAS )" },
			{ value: "Koforidua Technical University ( KTU )", text: "Koforidua Technical University ( KTU )" },
			{ value: "Prominence Institute of Management and Professional Studies", text: "Prominence Institute of Management and Professional Studies" },
			{ value: "University College of Science, Arts and Education - Sunyani", text: "University College of Science, Arts and Education - Sunyani" },
			{ value: "Family Health University", text: "Family Health University" },
			{ value: "Mountcrest Universtiy College", text: "Mountcrest Universtiy College" },
			{ value: "Central Lyceum", text: "Central Lyceum" },
			{ value: "Dominion University College", text: "Dominion University College" },
			{ value: "Christ Apostolic University college", text: "Christ Apostolic University college" },
			{ value: "River View Nursing and Allied Health College", text: "River View Nursing and Allied Health College" },
			{ value: "Mission Africa College of Excellence Ltd", text: "Mission Africa College of Excellence Ltd" },
			{ value: "SD Dombo Univesrsity of Business and Intergrated Development Studies ( SDD - UBIDS )", text: "SD Dombo Univesrsity of Business and Intergrated Development Studies ( SDD - UBIDS )" },
			{ value: "Royal Streams Int Sch Foundation", text: "Royal Streams Int Sch Foundation" },
			{ value: "Anchor Educational Consults", text: "Anchor Educational Consults" },
			{ value: "Danicom System", text: "Danicom System" },
			{ value: "Akenten Appiah-Menka University of Skills Training and Eentreprenuerial Development", text: "Akenten Appiah-Menka University of Skills Training and Eentreprenuerial Development" },
			{ value: "Institute of Human Resource Management Practitioners, Ghana", text: "Institute of Human Resource Management Practitioners, Ghana" },
			{ value: "Anchor Educational Consults", text: "Anchor Educational Consults" },
		],
		"GOVERNMENT": [
			{ value: "", text: "---Select---" },
			{ value: "GHANA CARD", text: "GHANA CARD" },
			{ value: "EPA", text: "EPA" },
		],
		"INSURANCE": [
			{ value: "", text: "---Select---" },
			{ value: "NHIS", text: "NHIS" },
			{ value: "SSNIT", text: "SSNIT" },
			{ value: "SUNU", text: "SUNU" },
			{ value: "DONEWELL", text: "DONEWELL" },
		],
	};
	const institutionType = document.getElementById('institution');
	const categorySelect = document.getElementById('category');

	function updatePaymentOptions() {
		const categorySelectValue = categorySelect.value;
		institutionType.innerHTML = ""; // Clear current options

		// Populate options based on selected transaction type
		if (categoryOptions[categorySelectValue]) {
			categoryOptions[categorySelectValue].forEach(option => {
				const opt = document.createElement('option');
				opt.value = option.value;
				opt.textContent = option.text;
				institutionType.appendChild(opt);
			});

		}

		const oldInstitution = (institutionType && institutionType.dataset) ? (institutionType.dataset.old || '') : '';
		if (oldInstitution) {
			institutionType.value = oldInstitution;
		}
	}

	// Event listeners for real-time updates
	categorySelect.addEventListener("change", updatePaymentOptions);

	document.addEventListener("DOMContentLoaded", function () {
		updatePaymentOptions();

		const countInputs = document.querySelectorAll(".count-input");
			const totalCountField = document.getElementById("total-count");
			const totalVolumeField = document.getElementById("total-volume");
		const denosField = document.querySelector("#denos");
		const submitBtn = document.querySelector('.submit');
		const restoreBtn = document.querySelector('.restore');
		const resetBtn = document.querySelector('.reset');
		const amountInput = document.getElementById('amount');
		const chargeInput = document.getElementById('charge');
			const staticBackdropModal = new bootstrap.Modal(document.getElementById('staticBackdrop'));
			const paymentMedium = document.querySelector('[name="payment_medium"]');
			const amountPaidInput = document.getElementById('amount_paid');
		const paymentForm = document.getElementById('paymentServiceForm');
		const changeDenominationsField = document.getElementById('change_denominations');
		const changeRequiredField = document.getElementById('change_required');
		const cashReceivedAmountField = document.getElementById('cash_received_amount');
		const cashdrawerDenosField = document.getElementById('cashdrawerDenos');
		const amountError = document.querySelector('.js-amount-error');
		const paymentChangeSummary = document.getElementById('paymentChangeSummary');
		const paymentChangeSummaryText = document.getElementById('paymentChangeSummaryText');
		const paymentChangeSummaryNote = document.getElementById('paymentChangeSummaryNote');
		const paymentOpenChangeBtn = document.getElementById('paymentOpenChangeBtn');
		const paymentClearChangeBtn = document.getElementById('paymentClearChangeBtn');
		const paymentChangeRows = document.getElementById('paymentChangeRows');
		const paymentAddChangeRowBtn = document.getElementById('paymentAddChangeRowBtn');
		const paymentApplyChangeBtn = document.getElementById('paymentApplyChangeBtn');
		const paymentChangeRequiredText = document.getElementById('paymentChangeRequiredText');
		const paymentAvailableDenosText = document.getElementById('paymentAvailableDenosText');
		const paymentChangeSelectedText = document.getElementById('paymentChangeSelectedText');
		const paymentChangeNetText = document.getElementById('paymentChangeNetText');
		const paymentChangeBalanceText = document.getElementById('paymentChangeBalanceText');
		const paymentChangeAlert = document.getElementById('paymentChangeAlert');
		const paymentChangeAlertText = document.getElementById('paymentChangeAlertText');
		const changeModalEl = document.getElementById('paymentChangeModal');
		const paymentChangeModal = changeModalEl ? new bootstrap.Modal(changeModalEl) : null;
		const transactionImageInput = document.getElementById('transaction_image');
		const transactionImagePreview = document.getElementById('transactionImagePreview');
		const transactionImagePreviewCard = document.getElementById('transactionImagePreviewCard');
		const transactionImagePreviewPlaceholder = document.getElementById('transactionImagePreviewPlaceholder');
		const transactionImageError = document.querySelector('.js-transaction-image-error');
		const cashDenominationOptions = [200, 100, 50, 20, 10, 5, 2, 1, 0.5, 0.2, 0.1];
		function getSeedDenos() {
			const fromField = (denosField && (denosField.value || '').trim() !== '') ? (denosField.value || '').trim() : '';
			if (fromField) {
				try {
					const parsed = JSON.parse(fromField);
					if (Array.isArray(parsed)) {
						return parsed;
					}
				} catch (e) {}
			}
			try {
				const saved = localStorage.getItem("denosData");
				const parsed = saved ? JSON.parse(saved) : null;
				return Array.isArray(parsed) ? parsed : [];
			} catch (e) {
				return [];
			}
		}

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

		function getCashReceivedAmount() {
			const hiddenValue = roundMoney(cashReceivedAmountField && cashReceivedAmountField.value);
			if (hiddenValue > 0) {
				return hiddenValue;
			}
			return roundMoney(amountInput && amountInput.value);
		}

		function getRequiredChangeAmount() {
			const received = getCashReceivedAmount();
			const payable = roundMoney(amountPaidInput && amountPaidInput.value);
			if (received > payable + 0.01) {
				return roundMoney(received - payable);
			}
			return 0;
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
			return aggregateDenominationCounts(parseJsonArray(denosField));
		}

		function getCombinedAvailabilityMap() {
			const combined = getDrawerAvailabilityMap();
			const received = getReceivedDenominationMap();
			Object.keys(received).forEach(key => {
				combined[key] = (combined[key] || 0) + (received[key] || 0);
			});
			return combined;
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

		function syncChangeSelectOptions() {
			if (!paymentChangeRows) {
				return;
			}

			const selects = Array.from(paymentChangeRows.querySelectorAll('.payment-change-value'));
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

		function getRawChangeRowsFromModal() {
			if (!paymentChangeRows) return [];
			const rows = [];
			paymentChangeRows.querySelectorAll('.payment-change-row').forEach(row => {
				const select = row.querySelector('.payment-change-value');
				const count = row.querySelector('.payment-change-count');
				rows.push({
					denomination: select ? select.value : '',
					count: count ? count.value : '',
				});
			});
			return rows;
		}

		function getChangeRowsFromModal() {
			return sanitizeChangeRows(getRawChangeRowsFromModal());
		}

		function updateChangeSummary() {
			if (!paymentChangeSummary) {
				return;
			}

			const received = getCashReceivedAmount();
			const payable = roundMoney(amountPaidInput && amountPaidInput.value);
			const required = getRequiredChangeAmount();
			const selectedRows = getSelectedChangeRows();
			const selectedTotal = getSelectedChangeTotal(selectedRows);
			const netCash = roundMoney(received - selectedTotal);
			const hasSelection = selectedRows.length > 0;
			const isBalanced = required > 0 && hasSelection && valuesMatch(selectedTotal, required) && valuesMatch(netCash, payable);

			if (required <= 0.01) {
				paymentChangeSummary.classList.add('d-none');
				paymentChangeSummary.classList.remove('alert-warning', 'alert-success', 'alert-danger');
				if (paymentClearChangeBtn) {
					paymentClearChangeBtn.classList.add('d-none');
				}
				return;
			}

			paymentChangeSummary.classList.remove('d-none', 'alert-warning', 'alert-success', 'alert-danger');
			paymentChangeSummary.classList.add(isBalanced ? 'alert-success' : (hasSelection ? 'alert-danger' : 'alert-warning'));
			if (paymentChangeSummaryText) {
				paymentChangeSummaryText.textContent = 'Change required: GHS ' + formatMoney(required);
			}

			if (paymentChangeSummaryNote) {
				if (isBalanced) {
					paymentChangeSummaryNote.textContent = 'Received GHS ' + formatMoney(received) + '. Change selected GHS ' + formatMoney(selectedTotal) + '. Net cash GHS ' + formatMoney(netCash) + ' matches the payable amount.';
				} else if (hasSelection) {
					paymentChangeSummaryNote.textContent = 'Selected change totals GHS ' + formatMoney(selectedTotal) + '. Adjust it to exactly GHS ' + formatMoney(required) + '.';
				} else {
					paymentChangeSummaryNote.textContent = 'Select the denominations to give out so the net cash matches the payable amount.';
				}
			}

			if (paymentOpenChangeBtn) {
				paymentOpenChangeBtn.textContent = hasSelection ? 'Edit change' : 'Select change';
			}
			if (paymentClearChangeBtn) {
				paymentClearChangeBtn.classList.toggle('d-none', !hasSelection);
			}
		}

		function updateChangeModalState() {
			if (!paymentChangeRows || !paymentApplyChangeBtn) {
				return;
			}

			syncChangeSelectOptions();

			const required = getRequiredChangeAmount();
			const received = getCashReceivedAmount();
			const payable = roundMoney(amountPaidInput && amountPaidInput.value);
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

			if (paymentChangeRequiredText) {
				paymentChangeRequiredText.textContent = 'GHS ' + formatMoney(required);
			}
			if (paymentChangeSelectedText) {
				paymentChangeSelectedText.textContent = 'GHS ' + formatMoney(selectedTotal);
			}
			if (paymentChangeNetText) {
				paymentChangeNetText.textContent = 'GHS ' + formatMoney(netCash);
			}
			if (paymentChangeBalanceText) {
				paymentChangeBalanceText.classList.remove('text-muted', 'text-warning', 'text-danger', 'text-success');

				if (required <= 0.01) {
					paymentChangeBalanceText.textContent = 'No extra change is needed for this transaction.';
					paymentChangeBalanceText.classList.add('text-muted');
				} else if (balanceGap > 0.01) {
					paymentChangeBalanceText.textContent = 'Add GHS ' + formatMoney(balanceGap) + ' more to balance.';
					paymentChangeBalanceText.classList.add('text-warning');
				} else if (balanceGap < -0.01) {
					paymentChangeBalanceText.textContent = 'Reduce change by GHS ' + formatMoney(Math.abs(balanceGap)) + ' to balance.';
					paymentChangeBalanceText.classList.add('text-danger');
				} else {
					paymentChangeBalanceText.textContent = 'Balanced. Change selection matches the required amount.';
					paymentChangeBalanceText.classList.add('text-success');
				}
			}
			if (paymentAvailableDenosText) {
				const labels = Object.keys(availability)
					.filter(key => (parseInt(availability[key], 10) || 0) > 0)
					.sort((a, b) => parseFloat(b) - parseFloat(a))
					.map(key => 'GHS ' + key + ' (' + availability[key] + ' units)');
				paymentAvailableDenosText.textContent = labels.length ? labels.join(', ') : 'No denominations available.';
			}

			if (required <= 0.01) {
				paymentChangeAlert.className = 'alert alert-info py-2 px-3 mt-3 mb-0';
				paymentChangeAlertText.textContent = 'No change is required for this transaction.';
				paymentApplyChangeBtn.disabled = true;
				return;
			}

			if (received + 0.01 < payable) {
				paymentChangeAlert.className = 'alert alert-warning py-2 px-3 mt-3 mb-0';
				paymentChangeAlertText.textContent = 'Amount received is below the payable amount, so change cannot be configured yet.';
				paymentApplyChangeBtn.disabled = true;
				return;
			}

			if (!rows.length) {
				paymentChangeAlert.className = 'alert alert-info py-2 px-3 mt-3 mb-0';
				paymentChangeAlertText.textContent = 'Select the denominations to give out. Add GHS ' + formatMoney(required) + ' more to balance.';
				paymentApplyChangeBtn.disabled = true;
				return;
			}

			if (insufficient.length) {
				paymentChangeAlert.className = 'alert alert-danger py-2 px-3 mt-3 mb-0';
				paymentChangeAlertText.textContent = 'Insufficient denominations after receipt: ' + insufficient.join(' | ');
				paymentApplyChangeBtn.disabled = true;
				return;
			}

			if (!valuesMatch(selectedTotal, required) || !valuesMatch(netCash, payable)) {
				paymentChangeAlert.className = 'alert alert-warning py-2 px-3 mt-3 mb-0';
				if (balanceGap > 0.01) {
					paymentChangeAlertText.textContent = 'Add GHS ' + formatMoney(balanceGap) + ' more change so the net cash equals the payable amount.';
				} else if (balanceGap < -0.01) {
					paymentChangeAlertText.textContent = 'Reduce change by GHS ' + formatMoney(Math.abs(balanceGap)) + ' so the net cash equals the payable amount.';
				} else {
					paymentChangeAlertText.textContent = 'Change must total exactly GHS ' + formatMoney(required) + ' so the net cash equals the payable amount.';
				}
				paymentApplyChangeBtn.disabled = true;
				return;
			}

			paymentChangeAlert.className = 'alert alert-success py-2 px-3 mt-3 mb-0';
			paymentChangeAlertText.textContent = 'Change selection is balanced and ready to apply.';
			paymentApplyChangeBtn.disabled = false;
		}

		function bindChangeRowEvents() {
			if (!paymentChangeRows) return;
			paymentChangeRows.querySelectorAll('.payment-change-value, .payment-change-count').forEach(input => {
				input.addEventListener('input', updateChangeModalState);
				input.addEventListener('change', updateChangeModalState);
			});
			paymentChangeRows.querySelectorAll('.payment-delete-change').forEach(button => {
				button.addEventListener('click', function () {
					const rows = paymentChangeRows.querySelectorAll('.payment-change-row');
					if (rows.length <= 1) {
						const row = this.closest('.payment-change-row');
						if (row) {
							const select = row.querySelector('.payment-change-value');
							const count = row.querySelector('.payment-change-count');
							if (select) select.value = '';
							if (count) count.value = '';
						}
					} else {
						const row = this.closest('.payment-change-row');
						if (row) row.remove();
					}
					updateChangeModalState();
				});
			});
		}

		function renderChangeRows(seedRows) {
			if (!paymentChangeRows) return;
			const rows = Array.isArray(seedRows) ? seedRows : [];
			const sourceRows = rows.length ? rows : [{ denomination: '', count: '' }];
			paymentChangeRows.innerHTML = sourceRows.map(row => {
				const denomination = row && row.denomination !== undefined && row.denomination !== null && row.denomination !== ''
					? formatMoney(row.denomination)
					: '';
				const count = row && row.count !== undefined && row.count !== null && row.count !== ''
					? String(row.count)
					: '';
				return `
					<div class="payment-change-row mb-2">
						<div class="row g-1 align-items-end">
							<div class="col-5">
								<select class="form-select form-control-sm payment-change-value" style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
									${buildChangeSelectOptions(denomination)}
								</select>
							</div>
							<div class="col-5">
								<input type="number" class="form-control form-control-sm payment-change-count" placeholder="Count" step="1" min="1" value="${count}" style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
							</div>
							<div class="col-2">
								<button type="button" class="btn btn-sm btn-outline-danger payment-delete-change" style="padding:0.25rem 0.5rem;">
									<i class="fa fa-trash"></i>
								</button>
							</div>
						</div>
					</div>
				`;
			}).join('');

			bindChangeRowEvents();
			syncChangeSelectOptions();
			updateChangeModalState();
		}

		function syncChangeRequirement() {
			const received = roundMoney(amountInput && amountInput.value);
			if (cashReceivedAmountField) {
				cashReceivedAmountField.value = received > 0 ? formatMoney(received) : '';
			}

			const required = getRequiredChangeAmount();
			const previousRequired = roundMoney(changeRequiredField && changeRequiredField.value);
			const payable = roundMoney(amountPaidInput && amountPaidInput.value);

			if (required <= 0.01) {
				resetChangeSelection();
				return { required: 0, received, payable };
			}

			if (changeRequiredField) {
				changeRequiredField.value = formatMoney(required);
			}

			if (!valuesMatch(previousRequired, required) && getSelectedChangeRows().length > 0 && changeDenominationsField) {
				changeDenominationsField.value = '[]';
			}

			updateChangeSummary();
			return { required, received, payable };
		}

		function resetChangeSelection() {
			if (changeDenominationsField) changeDenominationsField.value = '[]';
			if (changeRequiredField) changeRequiredField.value = '';
			updateChangeSummary();
		}

		function showChangeModal() {
			if (!paymentChangeModal) return;
			const state = syncChangeRequirement();
			if (state.required <= 0.01 || state.received + 0.01 < state.payable) {
				return;
			}
			renderChangeRows(getSelectedChangeRows());
			updateChangeModalState();
			paymentChangeModal.show();
		}

		function setTransactionPreviewState(hasImage) {
			if (transactionImagePreviewPlaceholder) {
				transactionImagePreviewPlaceholder.style.display = hasImage ? 'none' : 'flex';
			}
			if (transactionImagePreview) {
				transactionImagePreview.style.display = hasImage ? 'block' : 'none';
			}
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

		    // Update totals
		    updateVolumesAndTotals();
		  }
		}

		function resetDenominations() {
			countInputs.forEach(input => {
				const row = input.closest('tr');
				const volumeField = row ? row.querySelector('.volume-input') : null;
				input.value = '';
				if (volumeField) volumeField.value = '';
			});
			updateVolumesAndTotals();
			try {
				localStorage.setItem('denosData', JSON.stringify([]));
			} catch(e) {}
			if (denosField) denosField.value = JSON.stringify([]);
		}

		function showFields() {
			const bankList  = document.getElementById('bank-list');
			const assignedList  = document.getElementById('assigned-list');
			const telcoList = document.getElementById('telco-list');
			const selectedMedium = (paymentMedium.value || '').toLowerCase();
			if(bankList)  bankList.classList.add('d-none');
			if(assignedList)  assignedList.classList.add('d-none');
			if(telcoList) telcoList.classList.add('d-none');
			if (selectedMedium === 'bank' && bankList) {
				bankList.classList.remove('d-none');
				if(assignedList) assignedList.classList.remove('d-none');
			} else if (selectedMedium === 'telecos' && telcoList) {
				telcoList.classList.remove('d-none');
			}
		}

		// Event Listener for payment medium
		if(paymentMedium){
			paymentMedium.addEventListener("change", showFields);
		}

		restoreDenominations();
		showFields();

		countInputs.forEach(input => {
			input.addEventListener("input", updateVolumesAndTotals);
		});

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
          };
          currency.push(denos);
        }
      });

      totalCountField.value = totalCount;
      totalVolumeField.value = totalVolume.toFixed(2);

      // Store serialized currency details in both localStorage and the hidden field
      const serializedCurrency = JSON.stringify(currency);
      try {
        localStorage.setItem("denosData", serializedCurrency);
      } catch (e) {}
      denosField.value = serializedCurrency;
	  if (amountInput && totalVolumeField) {
	  	amountInput.value = totalVolumeField.value;
	  }
	  if (cashReceivedAmountField) {
	  	cashReceivedAmountField.value = totalVolumeField.value;
	  }
	  syncChangeRequirement();
	  updateChangeModalState();
    }

    if(submitBtn){
      submitBtn.addEventListener('click', function() {
        updateVolumesAndTotals();
        if(staticBackdropModal) staticBackdropModal.hide();
		const changeState = syncChangeRequirement();
		const selectedRows = getSelectedChangeRows();
		const selectedTotal = getSelectedChangeTotal(selectedRows);
		const netCash = roundMoney(changeState.received - selectedTotal);
		const needsPrompt = !selectedRows.length || !valuesMatch(selectedTotal, changeState.required) || !valuesMatch(netCash, changeState.payable);
		if (changeState.required > 0.01 && changeState.received >= (changeState.payable - 0.01) && needsPrompt) {
			setTimeout(function () {
				showChangeModal();
			}, 180);
		}
      });
    }

    if(restoreBtn){
      restoreBtn.addEventListener('click', function() {
        restoreDenominations();
      });
    }

    if(resetBtn){
      resetBtn.addEventListener('click', function() {
        resetDenominations();
      });
    }

    function checkAmount() {
		const received = getCashReceivedAmount();
		const payable = roundMoney(amountPaidInput && amountPaidInput.value);
		const requiredChange = getRequiredChangeAmount();
		const selectedRows = getSelectedChangeRows();
		const selectedChange = getSelectedChangeTotal(selectedRows);
		const netCash = roundMoney(received - selectedChange);
		if (amountError) amountError.textContent = '';

		if (received <= 0 || payable <= 0) {
			if (amountError) amountError.textContent = 'Enter the amount payable and received denominations.';
			return false;
		}

		if (received + 0.01 < payable) {
			if (amountError) amountError.textContent = 'Cash received cannot be less than the amount payable.';
			return false;
		}

		if (requiredChange > 0.01) {
			if (!selectedRows.length) {
				if (amountError) amountError.textContent = 'Change of GHS ' + formatMoney(requiredChange) + ' is required. Select the denominations to give out.';
				return false;
			}
			if (!valuesMatch(selectedChange, requiredChange) || !valuesMatch(netCash, payable)) {
				if (amountError) amountError.textContent = 'Change denominations must total GHS ' + formatMoney(requiredChange) + ' so the net cash matches the payable amount.';
				return false;
			}
		}

		return true;
	  }

	  if(amountPaidInput){
	  	amountPaidInput.addEventListener('input', function() {
	  		syncChangeRequirement();
	  		updateChangeModalState();
	  	});
	  }

	  if(paymentOpenChangeBtn){
	  	paymentOpenChangeBtn.addEventListener('click', showChangeModal);
	  }

	  if(paymentClearChangeBtn){
	  	paymentClearChangeBtn.addEventListener('click', function(){
	  		resetChangeSelection();
	  		updateChangeModalState();
	  	});
	  }

	  if(paymentAddChangeRowBtn){
	  	paymentAddChangeRowBtn.addEventListener('click', function(){
	  		const rows = getRawChangeRowsFromModal();
	  		rows.push({ denomination: '', count: '' });
	  		renderChangeRows(rows);
	  	});
	  }

	  if(paymentApplyChangeBtn){
	  	paymentApplyChangeBtn.addEventListener('click', function(){
	  		const rows = getChangeRowsFromModal();
	  		const required = getRequiredChangeAmount();
	  		const selectedTotal = getSelectedChangeTotal(rows);
	  		const netCash = roundMoney(getCashReceivedAmount() - selectedTotal);
	  		const payable = roundMoney(amountPaidInput && amountPaidInput.value);
	  		if (!rows.length || !valuesMatch(selectedTotal, required) || !valuesMatch(netCash, payable)) {
	  			updateChangeModalState();
	  			return;
	  		}
	  		if (changeDenominationsField) {
	  			changeDenominationsField.value = JSON.stringify(rows);
	  		}
	  		if (changeRequiredField) {
	  			changeRequiredField.value = formatMoney(required);
	  		}
	  		updateChangeSummary();
	  		if (paymentChangeModal) paymentChangeModal.hide();
	  	});
	  }

	  if (transactionImageInput && transactionImagePreviewCard && window.AppImageCropper && typeof window.AppImageCropper.bindPreviewCard === 'function') {
	  	window.AppImageCropper.bindPreviewCard({
	  		formEl: paymentForm,
	  		inputEl: transactionImageInput,
	  		cardEl: transactionImagePreviewCard,
	  		previewEl: transactionImagePreview,
	  		placeholderEl: transactionImagePreviewPlaceholder,
	  		errorEl: transactionImageError,
	  		modalTitle: 'Crop transaction image',
	  		errorTitle: 'Invalid transaction image',
	  		required: true,
	  		requiredMessage: 'Transaction picture is required',
	  		maxBytes: window.AppImageCropper.MAX_BYTES,
	  		onComplete: function() {
	  			const hasImage = !!(transactionImagePreview && String(transactionImagePreview.getAttribute('src') || '').trim() !== '');
	  			setTransactionPreviewState(hasImage);
	  		}
	  	});
	  } else {
	  	if (transactionImagePreviewCard && transactionImageInput) {
	  		transactionImagePreviewCard.addEventListener('click', function() {
	  			transactionImageInput.click();
	  		});
	  		transactionImagePreviewCard.addEventListener('keydown', function(e) {
	  			if (e.key === 'Enter' || e.key === ' ') {
	  				e.preventDefault();
	  				transactionImageInput.click();
	  			}
	  		});
	  	}

	  	if (transactionImageInput) {
	  		transactionImageInput.addEventListener('change', function() {
	  			const file = (this.files && this.files[0]) ? this.files[0] : null;
	  			if (!file) {
	  				if (transactionImagePreview) transactionImagePreview.src = '';
	  				setTransactionPreviewState(false);
	  				return;
	  			}
	  			if (!file.type || !file.type.startsWith('image/')) {
	  				if (transactionImagePreview) transactionImagePreview.src = '';
	  				setTransactionPreviewState(false);
	  				if (transactionImageError) transactionImageError.textContent = 'Invalid file type. Please select an image.';
	  				return;
	  			}
	  			if (transactionImageError) transactionImageError.textContent = '';
	  			const reader = new FileReader();
	  			reader.onload = function(e) {
	  				if (transactionImagePreview) transactionImagePreview.src = e.target.result;
	  				setTransactionPreviewState(true);
	  			};
	  			reader.readAsDataURL(file);
	  		});
	  	}
	  }

	  if(paymentForm){
	  	paymentForm.addEventListener('submit', function(e){
	  		syncChangeRequirement();
	  		const ok = checkAmount();
	  		const imageOk = transactionImageInput && transactionImageInput.files && transactionImageInput.files.length > 0;
	  		if (transactionImageError) {
	  			transactionImageError.textContent = imageOk ? '' : 'Transaction picture is required';
	  		}
	  		if (!imageOk || !ok) {
	  			e.preventDefault();
	  			if (getRequiredChangeAmount() > 0.01) {
	  				showChangeModal();
	  			}
	  		}
	  	});
	  }

		/*function calculateCharge() {
			const amount = parseFloat(amountInput.value);
			chargeInput.value = !isNaN(amount) ? (amount <= 50 ? '0.50' : (amount * 0.01).toFixed(2)) : '';
		}*/

		//amountInput.addEventListener('input', calculateCharge);
		syncChangeRequirement();
		setTransactionPreviewState(transactionImageInput && transactionImageInput.files && transactionImageInput.files.length > 0);
		updateChangeModalState();
	});
</script>

