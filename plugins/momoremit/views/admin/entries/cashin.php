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
	<form method="post" enctype="multipart/form-data" class="mb-3" id="momoremitCashinForm">

		<div class="row col-sm-12 col-md-8 col-lg-5 mx-auto shadow p-4 rounded">
			<?=csrf()?>
			<!-- Hidden fields to store cashdrawer data -->
			<input type="hidden" id="cashdrawerDenos" value="<?= htmlspecialchars(json_encode($cashdrawerDenos ?? []), ENT_QUOTES, 'UTF-8') ?>">
			<input type="hidden" id="cashdrawerBalances" value="<?= htmlspecialchars(json_encode($cashdrawerBalances ?? []), ENT_QUOTES, 'UTF-8') ?>">

			<h4 class="text-center">
				<span><img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:60px;max-height: 60px;object-fit: cover;"></span>
				CASH IN
			</h4>

	    <!-- Transaction Type Selection -->
	    <div class="mb-3 col-md-12">
	      <label for="transaction_type" class="form-label">Transaction</label>
	      <select class="form-select"  name="transaction_type">
	        <option <?=old_select('transaction_type','Cash in')?> value="Cash in" selected>Cash in</option>
	      </select>
	      <?php if(!empty($errors['transaction_type'])):?>
	        <small class="text-danger"><?=$errors['transaction_type']?></small>
	      <?php endif?>
	    </div>

			<!-- Network Selection -->
	    <div class="mb-3 col-md-12">
	      <label for="network" class="form-label">Select Network</label>
	      <select class="form-select" name="network" data-old="<?=esc(old_value('network'))?>">
	        <option value="">---Select---</option>
	        <option value="MTN">MTN</option>
	        <option value="TELECEL">TELECEL</option>
	        <option value="AT">AT</option>
	        <option value="ZEEPAY">ZEEPAY</option>
	        <option value="G-MONEY">G-MONEY</option>
	        <option value="GHANAPAY">GHANAPAY</option>
	      </select>
	      <?php if(!empty($errors['network'])):?>
	        <small class="text-danger"><?=$errors['network']?></small>
	      <?php endif?>
	  <?php if(!empty($errors['wallet_in_id'])):?>
				  <small class="text-danger"><?=$errors['wallet_in_id']?></small>
				<?php endif?>
				  </div>

			<div class="mb-3 col-md-12">
				<label for="sending_category" class="form-label">Transaction type</label>
				<select class="form-select" name="sending_category" id="sending_category" data-old="<?=esc(old_value('sending_category'))?>">
				  <option value="">---Select---</option>
				  <option <?=old_select('sending_category','Deposit')?> value="Deposit">Deposit</option>
				  <option <?=old_select('sending_category','Sending')?> value="Sending">Sending</option>
				</select>
				<?php if(!empty($errors['sending_category'])):?>
				  <small class="text-danger"><?=$errors['sending_category']?></small>
				<?php endif?>
			</div>

			<div class="mb-3 col-md-12">
				<label for="sim_type" class="form-label">Sim type</label>
				<select class="form-select" name="sim_type" id="sim_type" data-old="<?=esc(old_value('sim_type'))?>">
				  <option value="">---Select---</option>
				  <option <?=old_select('sim_type','Subscriber')?> value="Subscriber">Subscriber</option>
				  <option <?=old_select('sim_type','Agent')?> value="Agent">Agent</option>
				  <option <?=old_select('sim_type','Merchant')?> value="Merchant">Merchant</option>
				</select>
				<?php if(!empty($errors['sim_type'])):?>
				  <small class="text-danger"><?=$errors['sim_type']?></small>
				<?php endif?>
			</div>

			<div class="mb-3 col-md-12 bank_name d-none">
				<label for="bank_name" class="form-label">Bank</label>
				<select class="form-select" name="bank_name" id="bank_name" data-old="<?=esc(old_value('bank_name'))?>">
				  <option value="">---Select---</option>
				</select>
				<?php if(!empty($errors['bank_name'])):?>
				  <small class="text-danger"><?=$errors['bank_name']?></small>
				<?php endif?>
			</div>

	    <!-- Charging type -->
	    <div class="mb-3 col-md-12 charging_type">
	      <label for="charging_type" class="form-label">Charging type</label>
	      <select class="form-select" name="charging_type" id="charging_type">
				  <option value="">---Select---</option>
				  <option <?=old_select('charging_type','+fee')?> value="+fee">+fee</option>
				  <option <?=old_select('charging_type','-fee')?> value="-fee">-fee</option>
	      	<!-- Options will be displayed dynamc -->
	      </select>
	      <?php if(!empty($errors['charging_type'])):?>
	        <small class="text-danger"><?=$errors['charging_type']?></small>
	      <?php endif?>
	    </div>

	    <!-- Category Selection, dynamic options populated by JavaScript -->
	    <div class="mb-3 col-md-12 category d-none">
	      <label for="category" class="form-label">Sending type</label>
	      <select class="form-select" name="category" id="category" data-old="<?=esc(old_value('category'))?>">
				  <option value="">---Select---</option>
	      	<!-- Options will be displayed dynamc -->
	      </select>
	      <?php if(!empty($errors['category'])):?>
	        <small class="text-danger"><?=$errors['category']?></small>
	      <?php endif?>
	    </div>

	    <!-- Phone Input -->
	    <div class="mb-3 col-md-12 phone">
	      <label for="phone" class="form-label phone_label">Phone</label>
	      <input name="phone" value="<?=old_value('phone')?>" type="text" class="form-control" id="phone" placeholder="Enter phone number">
	      <?php if(!empty($errors['phone'])):?>
	        <small class="text-danger"><?=$errors['phone']?></small>
	      <?php endif?>
	    </div>

	    <!-- Sender Number Input, visible for Sending cashin -->
	    <div class="mb-3 col-md-12 sender_number d-none">
	      <label for="sender" class="form-label">Sender number</label>
	      <input name="sender" value="<?=old_value('sender')?>" type="text" class="form-control" id="sender" placeholder="Enter sender phone number">
        <small id="senderClientError" class="text-danger"></small>
	    </div>

	    <!-- Token Number Input, visible for specific categories only -->
	    <div class="mb-3 col-md-12 token d-none">
	      <label for="token" class="form-label">Token number</label>
	      <input name="token" value="<?=old_value('token')?>" type="text" class="form-control" id="token" placeholder="Enter token number">
	      <?php if(!empty($errors['token'])):?>
	        <small class="text-danger"><?=$errors['token']?></small>
	      <?php endif?>
	    </div>

	    <!-- Token Number Input, visible for specific categories only -->
	    <div class="mb-3 col-md-12 till_number d-none">
	      <label for="till_number" class="form-label till_label">Till number/ Short Code/Merchant ID</label>
	      <input name="till_number" value="<?=old_value('till_number')?>" type="text" class="form-control" id="till_number" placeholder="Enter till number or short code">
	      <?php if(!empty($errors['till_number'])):?>
	        <small class="text-danger"><?=$errors['till_number']?></small>
	      <?php endif?>
	    </div>

	    <!-- Token Number Input, visible for specific categories only -->
	    <div class="mb-3 col-md-12">
	      <label for="amount_load" class="form-label amount_load_load">Amount/Load</label>
	      <input name="amount_load" value="<?=old_value('amount_load')?>" type="number" step="0.01" min="0" class="form-control" id="amount_load" placeholder="Enter amount or load">
	      <?php if(!empty($errors['amount_load'])):?>
	        <small class="text-danger"><?=$errors['amount_load']?></small>
	      <?php endif?>
	    </div>

	    <!-- Charge Display (dynamically calculated) -->
	    <div class="mb-3 col-md-12">
	      <label for="charge" class="form-label">Charge (system generated) </label>
	      <div class="input-group">
	        <div id="currencySymbol" class="input-group-text">GH₵</div>
	        <input name="charge" value="<?=old_value('charge')?>" type="text" class="form-control" id="charge" placeholder="GH&cent;0.00" readonly>
	      </div>
	    </div>

    <div class="col-md-12">
      <label for="amount_payable" class="form-label">Amount Payable (system generated)</label>
      <div class="input-group">
        <div id="currencySymbol" class="input-group-text">GH₵</div>
        <input name="amount_payable" value="<?=old_value('amount_payable')?>" type="text" class="form-control" id="amount_payable" placeholder="GH&cent;0.00" readonly>
      </div>     
    </div>

    <div class="col-md-12">
      <label for="amount_sendable" class="form-label">Amount to Send (e-cash deduction, system generated)</label>
      <div class="input-group">
        <div id="currencySymbol" class="input-group-text">GH₵</div>
        <input name="amount_sendable" value="<?=old_value('amount_sendable')?>" type="text" class="form-control" id="amount_sendable" placeholder="GH&cent;0.00" readonly>
      </div>     
    </div>

    <!-- Amount Input with Calculator Modal Trigger -->
    <div class="mb-3 col-md-12">
      <label for="amount" class="form-label">Amount Paid</label>
      <label class="input-group" data-bs-toggle="modal" data-bs-target="#staticBackdrop"> 
        <i class="fa-solid fa-calculator input-group-text text-warning"></i>
          <input name="amount" type="number" step="0.01" class="form-control" id="amount" placeholder="Enter amount" readonly>
          <input type="hidden" id="denos" name="denos" value="<?=htmlspecialchars(old_value('denos'), ENT_QUOTES, 'UTF-8')?>" />
          <input type="hidden" id="change_denominations" name="change_denominations" value="<?=htmlspecialchars(old_value('change_denominations'), ENT_QUOTES, 'UTF-8')?>" />
          <input type="hidden" id="change_required" name="change_required" value="<?=htmlspecialchars(old_value('change_required'), ENT_QUOTES, 'UTF-8')?>" />
          <input type="hidden" id="cash_received_amount" name="cash_received_amount" value="<?=htmlspecialchars(old_value('cash_received_amount') ?: old_value('amount'), ENT_QUOTES, 'UTF-8')?>" />
      </label>
          <small id="limitValidationError" class="text-danger d-block mt-1"></small>
      <?php if(!empty($errors['amount'])):?>
        <small class="text-danger"><?=$errors['amount']?></small>
      <?php endif?>
			  <small class="text-danger js-amount-error"></small>
      <?php if(!empty($errors['change_denominations'])):?>
        <small class="text-danger d-block"><?=$errors['change_denominations']?></small>
      <?php endif?>
      <?php if(!empty($errors['change_required'])):?>
        <small class="text-danger d-block"><?=$errors['change_required']?></small>
      <?php endif?>
      <div id="cashinChangeSummary" class="alert alert-warning py-2 px-3 mt-2 d-none">
        <div class="d-flex flex-wrap align-items-center gap-2">
          <div class="me-auto">
            <div class="fw-semibold" id="cashinChangeSummaryText">Change required: GHS 0.00</div>
            <small class="text-muted" id="cashinChangeSummaryNote">Select the denominations to give out so the net cash matches the payable amount.</small>
          </div>
          <button type="button" class="btn btn-sm btn-outline-primary" id="cashinOpenChangeBtn">Select change</button>
          <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="cashinClearChangeBtn">Clear</button>
        </div>
      </div>
    </div>

    <!-- Charge Display (dynamically calculated) -->
    <div class="col-md-12">
      <label for="balance_after_display" class="form-label">Balance after</label>
      <div class="input-group">
        <div id="currencySymbol" class="input-group-text">GH₵</div>
        <input name="balance_after_display" value="<?=old_value('balance_after_display')?>" type="number" step="0.01" class="form-control" id="balance_after_display" placeholder="GH&cent;0.00">
      </div>
      <input type="hidden" name="balance_after" id="balance_after" value="<?=htmlspecialchars(old_value('balance_after'), ENT_QUOTES, 'UTF-8')?>">
		  <?php if(!empty($errors['balance_after'])):?>
				  <small class="text-danger"><?=$errors['balance_after']?></small>
				<?php endif?>
		  <?php if(!empty($errors['balance_after_display'])):?>
				  <small class="text-danger"><?=$errors['balance_after_display']?></small>
				<?php endif?>
			  <small class="text-danger js-balance-after-error"></small>
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

			<div class="mb-3 col-md-12 token d-none">
			  <label for="assign" class="form-label">Forward transaction</label>
			  <input name="assign" value="<?=old_value('assign')?>" type="text" class="form-control" id="assign" placeholder="Enter ID number here">				
				<?php if(!empty($errors['assign'])):?>
				  <small class="text-danger"><?=$errors['assign']?></small>
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
	              <th class="px-5 bg-secondary text-warning">TOTAL</th>
	              <th class="bg-secondary text-warning"><input type="text" class="form-control p-1" id="total-count" style="border:none; " placeholder="Total count" readonly></th>
	              <th class="bg-secondary text-warning"><input type="text" class="form-control p-1" id="total-volume" style="border:none; " placeholder="Total volume" readonly></th>
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

<!-- JavaScript: Calculate Charges, Validate Wallet, Show Fields -->
<script>
    document.addEventListener("DOMContentLoaded", function () {
      const cashinForm = document.getElementById('momoremitCashinForm');
      const countInputs = document.querySelectorAll(".count-input");
      const totalCountField = document.getElementById("total-count");
      const totalVolumeField = document.getElementById("total-volume");
      const denosField = document.querySelector("#denos");
      const changeDenominationsField = document.getElementById('change_denominations');
      const changeRequiredField = document.getElementById('change_required');
      const cashReceivedAmountField = document.getElementById('cash_received_amount');
      const cashdrawerDenosField = document.getElementById('cashdrawerDenos');
      const submitBtn = document.querySelector('.submit');
      const restoreBtn = document.querySelector('.restore');
      const resetBtn = document.querySelector('.reset');
      const staticBackdropModal = new bootstrap.Modal(document.getElementById('staticBackdrop'));
      const changeModalEl = document.getElementById('cashinChangeModal');
      const cashinChangeModal = changeModalEl ? new bootstrap.Modal(changeModalEl) : null;
      const amountInput = document.getElementById("amount");
      const amountPayableInput = document.getElementById("amount_payable");
      const amountToSendInput = document.getElementById("amount_sendable");
      const amountLoadInput = document.getElementById("amount_load");
      const chargeInput = document.getElementById("charge");
      const networkSelect = document.querySelector('[name="network"]');
      const cashdrawerBalancesField = document.getElementById('cashdrawerBalances');
      const balanceAfter = document.getElementById('balance_after');
      const balanceAfterDisplay = document.getElementById('balance_after_display');
      const balanceAfterError = document.querySelector('.js-balance-after-error');
      const amountError = document.querySelector('.js-amount-error');
      const saveBtn = document.querySelector('.save');
      const transactionImageInput = document.getElementById('transaction_image');
      const transactionImagePreview = document.getElementById('transactionImagePreview');
      const transactionImagePreviewCard = document.getElementById('transactionImagePreviewCard');
      const transactionImagePreviewPlaceholder = document.getElementById('transactionImagePreviewPlaceholder');
      const transactionImageError = document.querySelector('.js-transaction-image-error');
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
    let limitBlocked = false;
    let limitRequest = 0;
    const limitValidationError = document.getElementById('limitValidationError');
    async function validateConfiguredLimit(){
    const network = (networkSelect && networkSelect.value || '').trim();
    const sim = (simType && simType.value || '').trim();
    const values = ['amount', 'amount_payable', 'amount_load', 'amount_sendable'].map(id => parseFloat((document.getElementById(id)?.value || '0').replace(/,/g, '')) || 0);
    const amount = Math.max(...values);
    if(!network || !sim || amount <= 0){ limitBlocked = false; if(limitValidationError) limitValidationError.textContent = ''; validateSaveState(); return; }
    const requestId = ++limitRequest;
    limitBlocked = true;
    if(limitValidationError) limitValidationError.textContent = 'Checking configured transaction limit...';
    validateSaveState();
    try{
      const params = new URLSearchParams({transaction_type: 'Cash in', sim_type: sim, network, amount: String(amount)});
      const response = await fetch('<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/limit-check?' + params.toString(), {headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}, credentials: 'include'});
      const responseText = await response.text();
      let data;
      try { data = JSON.parse(responseText); } catch (parseError) { throw new Error('The server returned an invalid limit response. Please refresh and try again.'); }
      if(!response.ok || !data.success) throw new Error(data.message || 'Unable to verify the configured transaction limit.');
      if(requestId !== limitRequest) return;
      limitBlocked = Boolean(data.exceeded);
      if(limitValidationError) limitValidationError.textContent = data.exceeded ? 'Limit exceeded. ' + data.message : (data.configured ? data.message : '');
    }catch(error){ if(requestId === limitRequest){ limitBlocked = true; if(limitValidationError) limitValidationError.textContent = 'Unable to verify the configured transaction limit.'; } }
    validateSaveState();
    }

      const tillNumberDiv = document.querySelector('.till_number');
      const chargingTypeSelect = document.getElementById('charging_type');
      const chargingTypeWrapper = document.querySelector('.charging_type'); // wrapper div
      const simType = document.getElementById('sim_type');
      const phoneDiv = document.querySelector('.phone');
      const tillLabel = document.querySelector('.till_label');
      const phoneLabel = document.querySelector('.phone_label');
      const tillNumber = document.getElementById('till_number');
      const phoneNumber = document.getElementById('phone');
      const senderNumberDiv = document.querySelector('.sender_number');
      const senderInput = document.getElementById('sender');
      const bankSelect = document.getElementById('bank_name');
      const bankDiv = document.querySelector('.bank_name');

      const ghanaPayExcludedBankNames = ['FBN', 'FNB', 'FIDELITY', 'ABSA', 'CAL BANK', 'ECOBANK', 'NAFANA', 'SUMA'];
      const ghanaPayBankList = [
        'ABSA',
        'Access Bank',
        'ADB',
        'Bank of Africa',
        'CBG',
        'Cal Bank',
        'Ecobank',
        'Fidelity Bank',
        'First Atlantic Bank',
        'First Bank Ghana',
        'FBN',
        'FNB',
        'Gtbank',
        'GCB',
        'NIB',
        'Republic Bank',
        'OmniBSIC Bank',
        'Societe Generale',
        'Stanbic Bank',
        'Prudential Bank',
        'Standard Chartered',
        'Zenith Bank',
        'Apex Nafana Bank',
        'Apex Suma Bank',
        'United Bank for Africa'
      ];

      const sendingType = document.getElementById("sending_category");

      function isGhanapayNetwork() {
        return normVal(networkSelect.value) === 'ghanapay';
      }

      function getGhanapayBankOptions() {
        return ghanaPayBankList.filter(bankName => {
          const normalized = (bankName || '').toString().toUpperCase();
          return !ghanaPayExcludedBankNames.some(excluded => normalized.includes(excluded));
        });
      }

      function renderBankOptions() {
        if (!bankSelect) {
          return;
        }

        const currentValue = bankSelect.value || '';
        const bankOptions = getGhanapayBankOptions();
        const options = ['<option value="">---Select---</option>']
          .concat(bankOptions.map(bankName => {
            const selected = bankName === currentValue ? ' selected' : '';
            return '<option value="' + bankName + '"' + selected + '>' + bankName + '</option>';
          }));
        bankSelect.innerHTML = options.join('');
      }

      function updateSimTypeOptions() {
        if (!simType) {
          return;
        }

        const selectedValue = simType.value;
        const options = [{ value: '', text: '---Select---' }];

        if (isGhanapayNetwork()) {
          options.push({ value: 'Subscriber', text: 'Subscriber' });
          options.push({ value: 'Bank', text: 'Bank' });
        } else {
          options.push({ value: 'Subscriber', text: 'Subscriber' });
          options.push({ value: 'Agent', text: 'Agent' });
          options.push({ value: 'Merchant', text: 'Merchant' });
        }

        simType.innerHTML = '';
        options.forEach(optionData => {
          const opt = document.createElement('option');
          opt.value = optionData.value;
          opt.textContent = optionData.text;
          if (optionData.value === selectedValue) {
            opt.selected = true;
          }
          simType.appendChild(opt);
        });

        if (isGhanapayNetwork() && !['subscriber', 'bank'].includes(normVal(selectedValue))) {
          simType.value = '';
        }
      }
      const categorySelect = document.getElementById("category");
      const categoryDiv = document.querySelector(".category");

      // Load denomination data from localStorage
      const savedDenos = localStorage.getItem("denosData");

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

      function getDrawerAvailabilityMap() {
        return aggregateDenominationCounts(parseJsonArray(cashdrawerDenosField));
      }

      function getCashdrawerBalanceRows() {
        return parseJsonArray(cashdrawerBalancesField);
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

      function getSelectedChangeRows() {
        return sanitizeChangeRows(parseJsonArray(changeDenominationsField));
      }

      function getSelectedChangeTotal(rows) {
        return roundMoney((rows || []).reduce((sum, item) => {
          return sum + (roundMoney(item.denomination) * (parseInt(item.count, 10) || 0));
        }, 0));
      }

      function getCashReceivedAmount() {
        const hiddenValue = roundMoney(cashReceivedAmountField && cashReceivedAmountField.value);
        if (hiddenValue > 0) {
          return hiddenValue;
        }
        return roundMoney(amountInput.value);
      }

      function getRequiredChangeAmount() {
        const received = getCashReceivedAmount();
        const payable = roundMoney(amountPayableInput.value);
        if (received > payable + 0.01) {
          return roundMoney(received - payable);
        }
        return 0;
      }

      function normalizeChannelKey(value) {
        return (value || '')
          .toString()
          .trim()
          .toUpperCase()
          .replace(/[^A-Z0-9]+/g, '_')
          .replace(/^_+|_+$/g, '');
      }

      function getResolvedNetworkBalance(networkValue) {
        const rows = getCashdrawerBalanceRows();
        if (!rows.length) {
          return { found: false, key: '', balance: 0 };
        }

        const networkUpper = (networkValue || '').toString().trim().toUpperCase();
        if (!networkUpper) {
          return { found: false, key: '', balance: 0 };
        }

        const networkNormalized = normalizeChannelKey(networkUpper);
        const baseChannelKey = ['MTN', 'TELECEL', 'AT'].includes(networkUpper) ? networkUpper : networkNormalized;
        const isMomoPay = ['MOMOPAY', 'MTN_MOMOPAY', 'MTN-MOMOPAY'].includes(networkUpper);
        const isGhanaPayBank = networkUpper === 'GHANAPAY'
          && simType
          && normVal(simType.value) === 'bank'
          && bankSelect;
        const bankNormalized = isGhanaPayBank ? normalizeChannelKey(bankSelect.value) : '';
        const bankKeys = bankNormalized ? [
          'ECASH_' + bankNormalized,
          'BANK_' + bankNormalized,
          bankNormalized,
        ] : [];
        const keysToTry = isMomoPay ? [
          'ECASH_MTN_MOMOPAY',
          'MTN_MOMOPAY',
          'MTN-MOMOPAY',
          'ECASH_MTN-MOMOPAY',
          'ECASH_MTN_MOMOPAY_OUT',
          'ECASH_MTN-MOMOPAY-OUT',
        ] : bankKeys.concat([
          networkUpper,
          networkNormalized,
          networkNormalized.replace(/_/g, ' '),
          networkNormalized.replace(/_/g, '-'),
          baseChannelKey,
          'ECASH_' + networkUpper,
          'ECASH_' + networkNormalized,
          'ECASH_' + baseChannelKey,
          'BANK_' + networkUpper,
          'BANK_' + networkNormalized,
          'BANK_' + baseChannelKey,
        ]);

        for (const key of keysToTry) {
          const match = rows.find(row => normalizeChannelKey(row.channel_key) === normalizeChannelKey(key));
          if (match) {
            return {
              found: true,
              key: match.channel_key || key,
              balance: roundMoney(match.closing_balance || 0),
            };
          }
        }

        return {
          found: false,
          key: keysToTry[0] || networkUpper,
          balance: 0,
        };
      }

      function updateBalanceAfterField() {
        if (!balanceAfter) {
          return { found: false, key: '', balance: 0, projected: 0 };
        }

        const resolved = getResolvedNetworkBalance(networkSelect ? networkSelect.value : '');
        let sendableAmount = roundMoney(amountToSendInput && amountToSendInput.value);
        if (isMerchantCashinSending()) {
          sendableAmount = calculateMerchantCashinSending().ecashDebit;
        }

        if (!resolved.found || sendableAmount <= 0) {
          balanceAfter.value = '';
          return {
            found: resolved.found,
            key: resolved.key,
            balance: resolved.balance,
            projected: 0,
          };
        }

        const projected = roundMoney(resolved.balance - sendableAmount);
        balanceAfter.value = formatMoney(projected);

        return {
          found: true,
          key: resolved.key,
          balance: resolved.balance,
          projected,
        };
      }

      function applyOldSelect(el){
        if(!el || !el.dataset) return;
        const v = (el.dataset.old || '').toString();
        if(v !== '') el.value = v;
      }

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
          const row = input.closest("tr");
          const volumeField = row.querySelector(".volume-input");
          input.value = '';
          volumeField.value = '';
        });
        updateVolumesAndTotals();
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
        const payable = roundMoney(amountPayableInput.value);
        const required = getRequiredChangeAmount();
        const selectedRows = getSelectedChangeRows();
        const selectedTotal = getSelectedChangeTotal(selectedRows);
        const netCash = roundMoney(received - selectedTotal);
        const hasSelection = selectedRows.length > 0;
        const isBalanced = required > 0 && hasSelection && valuesMatch(selectedTotal, required) && valuesMatch(netCash, payable);

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
          cashinChangeSummaryNote.textContent = 'Received GHS ' + formatMoney(received) + '. Change selected GHS ' + formatMoney(selectedTotal) + '. Net cash GHS ' + formatMoney(netCash) + ' matches the payable amount.';
        } else if (hasSelection) {
          cashinChangeSummaryNote.textContent = 'Selected change totals GHS ' + formatMoney(selectedTotal) + '. Adjust it to exactly GHS ' + formatMoney(required) + '.';
        } else {
          cashinChangeSummaryNote.textContent = 'Select the denominations to give out so the net cash matches the payable amount.';
        }

        if (cashinOpenChangeBtn) {
          cashinOpenChangeBtn.textContent = hasSelection ? 'Edit change' : 'Select change';
        }
        if (cashinClearChangeBtn) {
          cashinClearChangeBtn.classList.toggle('d-none', !hasSelection);
        }
      }

      function syncChangeRequirement() {
        const received = roundMoney(amountInput.value);
        if (cashReceivedAmountField) {
          cashReceivedAmountField.value = received > 0 ? formatMoney(received) : '';
        }

        const required = getRequiredChangeAmount();
        const previousRequired = roundMoney(changeRequiredField && changeRequiredField.value);

        if (required <= 0.01) {
          resetChangeSelection();
          return {
            received,
            payable: roundMoney(amountPayableInput.value),
            required: 0,
          };
        }

        if (changeRequiredField) {
          changeRequiredField.value = formatMoney(required);
        }

        if (!valuesMatch(previousRequired, required) && getSelectedChangeRows().length > 0 && changeDenominationsField) {
          changeDenominationsField.value = '[]';
        }

        syncChangeSummary();

        return {
          received,
          payable: roundMoney(amountPayableInput.value),
          required,
        };
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
        if (!cashinChangeRows) {
          return [];
        }
        return Array.from(cashinChangeRows.querySelectorAll('.cashin-change-row')).map(row => {
          const valueEl = row.querySelector('.cashin-change-value');
          const countEl = row.querySelector('.cashin-change-count');
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
        const payable = roundMoney(amountPayableInput.value);
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

        if (received + 0.01 < payable) {
          cashinChangeAlert.className = 'alert alert-warning py-2 px-3 mt-3 mb-0';
          cashinChangeAlertText.textContent = 'Amount received is below the payable amount, so change cannot be configured yet.';
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

        if (!valuesMatch(selectedTotal, required) || !valuesMatch(netCash, payable)) {
          cashinChangeAlert.className = 'alert alert-warning py-2 px-3 mt-3 mb-0';
          if (balanceGap > 0.01) {
            cashinChangeAlertText.textContent = 'Add GHS ' + formatMoney(balanceGap) + ' more change so the net cash equals the payable amount.';
          } else if (balanceGap < -0.01) {
            cashinChangeAlertText.textContent = 'Reduce change by GHS ' + formatMoney(Math.abs(balanceGap)) + ' so the net cash equals the payable amount.';
          } else {
            cashinChangeAlertText.textContent = 'Change must total exactly GHS ' + formatMoney(required) + ' so the net cash equals the payable amount.';
          }
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
          const denomination = row && row.denomination !== undefined && row.denomination !== null && row.denomination !== ''
            ? formatMoney(row.denomination)
            : '';
          const count = row && row.count !== undefined && row.count !== null && row.count !== ''
            ? String(row.count)
            : '';
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
                  <button type="button" class="btn btn-sm btn-outline-danger cashin-delete-change" style="padding:0.25rem 0.5rem;">
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

      // Wallet functionality removed - cashdrawer is central accounting system

      countInputs.forEach(input => {
        input.addEventListener("input", updateVolumesAndTotals);
      });

      function updateVolumesAndTotals() {
        let totalCount = 0;
        let totalVolume = 0;
        const currency = [];

        countInputs.forEach((input, rowIndex) => {
          const row = input.closest("tr");
          const denomination = parseFloat(row.getAttribute("data-denomination")) || 0;
          const count = parseFloat(input.value) || 0;
          const volumeField = row.querySelector(".volume-input");
          const volume = denomination * count;
          volumeField.value = volume.toFixed(2);
          totalCount += count;
          totalVolume += volume;

          if (count > 0) {
            currency.push({
              currency: `GHS`,
              row_index: rowIndex,
              denomination: denomination,
              count: count,
              volume: volume
            });
          }
        });

        totalCountField.value = totalCount;
        totalVolumeField.value = totalVolume.toFixed(2);

        const serializedCurrency = JSON.stringify(currency);
        localStorage.setItem("denosData", serializedCurrency);
        denosField.value = serializedCurrency;
      }

      submitBtn.addEventListener('click', function() {
        updateVolumesAndTotals();
        amountInput.value = totalVolumeField.value;
        if (cashReceivedAmountField) {
          cashReceivedAmountField.value = totalVolumeField.value;
        }
        calculateCharge();
        staticBackdropModal.hide();
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
        validateSaveState();
      });

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
            validateSaveState();
            return;
          }
          if (!file.type || !file.type.startsWith('image/')) {
            if (transactionImagePreview) transactionImagePreview.src = '';
            setTransactionPreviewState(false);
            if (transactionImageError) transactionImageError.textContent = 'Invalid file type. Please select an image.';
            saveBtn.classList.add('disabled');
            saveBtn.disabled = true;
            return;
          }
          const reader = new FileReader();
          reader.onload = function(e) {
            if (transactionImagePreview) transactionImagePreview.src = e.target.result;
            setTransactionPreviewState(true);
            validateSaveState();
          };
          reader.readAsDataURL(file);
        });
      }

      restoreBtn.addEventListener('click', function() {
        restoreDenominations();
        syncChangeRequirement();
        validateSaveState();
      });

      resetBtn.addEventListener('click', function() {
        resetDenominations();
        syncChangeRequirement();
        validateSaveState();
      });

      function checkAmount() {
        const amount = getCashReceivedAmount();
        const amountPayable = roundMoney(amountPayableInput.value);
        const requiredChange = getRequiredChangeAmount();
        const selectedRows = getSelectedChangeRows();
        const selectedChange = getSelectedChangeTotal(selectedRows);
        const netCash = roundMoney(amount - selectedChange);
        amountError.innerText = "";

        if (amount <= 0 || amountPayable <= 0) {
          amountError.textContent = "Enter the transaction amount and received denominations";
          return false;
        }

        if (amount + 0.01 < amountPayable) {
          amountError.textContent = "Amount received cannot be less than amount payable";
          return false;
        }

        if (requiredChange > 0.01) {
          if (!selectedRows.length) {
            amountError.textContent = "Change of GHS " + formatMoney(requiredChange) + " is required. Select the denominations to give out.";
            return false;
          }

          if (!valuesMatch(selectedChange, requiredChange) || !valuesMatch(netCash, amountPayable)) {
            amountError.textContent = "Change denominations must total GHS " + formatMoney(requiredChange) + " so the net cash matches the payable amount";
            return false;
          }
        }

        amountError.innerText = "";
        return true;
      }

      const setTransactionPreviewState = (hasImage) => {
        if (transactionImagePreviewPlaceholder) {
          transactionImagePreviewPlaceholder.style.display = hasImage ? 'none' : 'flex';
        }
        if (transactionImagePreview) {
          transactionImagePreview.style.display = hasImage ? 'block' : 'none';
        }
      };

      function validateSaveState() {
        const balanceState = updateBalanceAfterField();
        const balanceEntered = (balanceAfterDisplay && balanceAfterDisplay.value || '').trim() !== '';
        const systemBalance = (balanceAfter && balanceAfter.value || '').trim() !== '';
        const balanceMatches = balanceState.found && balanceEntered && systemBalance && valuesMatch(balanceAfterDisplay.value, balanceAfter.value);
        const imageOk = transactionImageInput && transactionImageInput.files && transactionImageInput.files.length > 0;
        syncChangeSummary();
        const amountOk = checkAmount();
        /*if (balanceAfterError) {
          if (!balanceState.found) {
            balanceAfterError.textContent = 'Select a network and enter an amount to calculate the system balance.';
          } else if (!balanceEntered) {
            balanceAfterError.textContent = 'Balance after is required';
          } else {
            balanceAfterError.textContent = '';
          }
        }*/
        if (transactionImageError) {
          transactionImageError.textContent = '';
          if (!imageOk) {
            transactionImageError.textContent = 'Transaction picture is required';
          }
        }
        if (imageOk && amountOk && !limitBlocked) {
          saveBtn.classList.remove('disabled');
          saveBtn.disabled = false;
        } else {
          saveBtn.classList.add('disabled');
          saveBtn.disabled = true;
        }
      }

      function showChangeModal(){
        if (!cashinChangeModal) {
          return;
        }
        const state = syncChangeRequirement();
        if (state.required <= 0.01 || state.received + 0.01 < state.payable) {
          return;
        }
        renderChangeRows(getSelectedChangeRows());
        updateChangeModalState();
        cashinChangeModal.show();
      }

      saveBtn.classList.add('disabled');
      saveBtn.disabled = true;

      // Utility - normalize values for robust comparisons
      function normVal(v) {
        return (v || '').toString().toLowerCase().replace(/\s+/g, '').replace(/\//g, '').replace(/[^a-z0-9+-]/g,'');
      }

      function normalizePhoneInput(value) {
        return (value || '').toString().replace(/\s+/g, '').trim();
      }

      function normalizePhoneField(input) {
        if (!input) {
          return;
        }
        const normalized = normalizePhoneInput(input.value);
        if (input.value !== normalized) {
          input.value = normalized;
        }
      }

      if (phoneNumber) {
        phoneNumber.addEventListener('input', function () {
          normalizePhoneField(phoneNumber);
          validateSender();
        });
        phoneNumber.addEventListener('blur', function () {
          normalizePhoneField(phoneNumber);
          validateSender();
        });
      }

      if (senderInput) {
        senderInput.addEventListener('input', function () {
          normalizePhoneField(senderInput);
          validateSender();
        });
        senderInput.addEventListener('blur', function () {
          normalizePhoneField(senderInput);
          validateSender();
        });
      }

      function validateSender() {
        if (!senderInput) {
          return true;
        }
        const senderValue = normalizePhoneInput(senderInput.value);
        const phoneValue = normalizePhoneInput(phoneNumber.value);
        const errorEl = document.getElementById('senderClientError');
        let errorMessage = '';

        if (sendingType.value === 'Sending') {
          if (senderValue === '') {
            errorMessage = 'Sender number is required for Sending transactions';
          } else if (!/^(0\d{9}|\+233\d{9})$/.test(senderValue)) {
            errorMessage = 'Sender number not valid';
          } else if (senderValue === phoneValue && phoneValue !== '') {
            errorMessage = 'Sender number and phone number cannot be the same';
          }
        }

        if (errorEl) {
          errorEl.textContent = errorMessage;
        }

        return errorMessage === '';
      }

      function isMerchantCashinSending() {
        return normVal(simType.value) === 'merchant' && normVal(sendingType.value) === 'sending';
      }

      // Fee helpers (RETURN NUMBERS)
      const feeRate = 0.005;    // 0.5%
      const inRate  = 0.01;     // 1%
      const outRate = 0.01;     // 1%
      const outCap  = 20;       // GH₵20 cap @ ≥2000
      const feeCap  = 25;       // GH₵25 cap @ ≥5000

      const calcFee = a => Math.min(a * feeRate, feeCap);           // merchant 0.5% capped at feeCap
      const calcInFee = a => a * inRate;
      const calcOutFee = a => (a >= 2000 ? outCap : a * outRate);
      const calcMechantFee = a => (a >= 5000 ? feeCap : a * feeRate);

      function calculateMerchantCashinSending() {
        const amount = roundMoney(parseFloat(amountLoadInput.value) || 0);
        const category = normVal(categorySelect.value);
        const chargingType = normVal(chargingTypeSelect.value);
        const roundSpecialFee = (value) => {
          const v = Number(value) || 0;
          if (v <= 0) return 0;
          const whole = Math.floor(v + 1e-9);
          let frac = v - whole;
          if (frac < 0) frac = 0;
          if (frac > 0.999999) frac = 0.999999;
          if (frac > 0.000001 && frac < 0.5 - 0.000001) {
            return whole + 0.5;
          }
          if (frac > 0.5 + 0.000001) {
            return whole + 1;
          }
          return (frac >= 0.5 - 0.000001 && frac <= 0.5 + 0.000001) ? (whole + 0.5) : whole;
        };
        const inFee = roundSpecialFee(amount * 0.01);
        const outFee = category === 'inout' ? roundSpecialFee(Math.min(amount * 0.005, 20)) : 0;
        let sendBeforeMerchantCharge = amount;
        let payable = amount;

        if (category === 'subtractin') {
          sendBeforeMerchantCharge = Math.max(0, roundMoney(amount - inFee));
        } else if (category === 'onlyin') {
          payable = roundMoney(amount + inFee);
        } else if (category === 'inout') {
          sendBeforeMerchantCharge = roundMoney(amount + outFee);
          payable = roundMoney(amount + inFee + outFee);
        }

        const rawMerchantCharge = roundMoney(Math.min(sendBeforeMerchantCharge * 0.005, 25));
        const merchantCharge = roundSpecialFee(rawMerchantCharge);
        let toSend = sendBeforeMerchantCharge;
        let ecashDebit = roundMoney(sendBeforeMerchantCharge + rawMerchantCharge);

        if (chargingType === '-fee') {
          toSend = Math.max(0, roundMoney(sendBeforeMerchantCharge - rawMerchantCharge));
          ecashDebit = sendBeforeMerchantCharge;
        } else {
          payable = roundMoney(payable + merchantCharge);
        }

        return {
          charge: roundMoney(inFee + outFee + merchantCharge),
          payable,
          toSend,
          ecashDebit,
        };
      }

      function calculateCharge() {
        const amt = parseFloat(amountLoadInput.value) || 0;

        // normalized values for safe comparison
        const sim = normVal(simType.value);
        const sendType = normVal(sendingType.value);
        const category = normVal(categorySelect.value);         // e.g. "inout", "onlyin", "subtractin", "+fee" etc
        const chargingType = normVal(chargingTypeSelect.value); // e.g. "+fee" or "-fee"

        // defaults
        let charge = 0;
        let payable = amt;
        let toSend = amt;
        let applySpecialFinalRounding = true;

		// Special rounding for charges and (optionally) to-send:
		//  - decimals 0.01..0.49 => round UP to .50
		//  - decimals 0.51..0.99 => round UP to next integer
		//  - .00 and .50 stay as-is
		const roundSpecial = (value) => {
			const v = Number(value) || 0;
			if (v <= 0) return 0;
			const whole = Math.floor(v + 1e-9);
			let frac = v - whole;
			if (frac < 0) frac = 0;
			if (frac > 0.999999) frac = 0.999999;
			if (frac > 0.000001 && frac < 0.5 - 0.000001) {
				return whole + 0.5;
			}
			if (frac > 0.5 + 0.000001) {
				return whole + 1;
			}
			return (frac >= 0.5 - 0.000001 && frac <= 0.5 + 0.000001) ? (whole + 0.5) : whole;
		};
		const round2 = (value) => {
			const v = Number(value) || 0;
			return Math.round(v * 100) / 100;
		};

        const isGhanapay = normVal(networkSelect.value) === 'ghanapay';
        const isBankSim = sim === 'bank';
        if (isGhanapay && isBankSim && amt > 0) {
          const specialCharge = Math.min(amt * 0.01, 10);
          charge = round2(specialCharge);
          payable = round2(amt + charge);
          toSend = round2(amt);
          chargeInput.value = Number(charge || 0).toFixed(2);
          amountPayableInput.value = Number(payable || 0).toFixed(2);
          amountToSendInput.value = Number(toSend || 0).toFixed(2);
          validateSaveState();
          return;
        }

        // DEPOSIT rules
        if (sendType === 'deposit') {
          if (sim === 'subscriber' || (sim === 'agent' && normVal(networkSelect.value) === 'mtn') || (sim === 'agent' && ['telecel','g-money','at'].includes(normVal(networkSelect.value)))) {
            charge = 0;
            payable = amt;
            toSend = amt;
          } else if (sim === 'merchant' && normVal(networkSelect.value) === 'mtn') {
            // Deposit for merchant on MTN: use merchant fee cap rules and respect + / - selection
			const f = roundSpecial(calcMechantFee(amt));
            const plus = chargingType === '+fee' || category === '+fee';
            const minus = chargingType === '-fee' || category === '-fee';
            if (plus) {
              charge = f;
              payable = (amt + charge);
              toSend = amt;              

              console.log('sendType: ', sendType,'chargingType: ', chargingType, 'amount: ', amt,'charge: ', charge, 'payable: ', payable,'payableType: ', payable.type, 'toSend: ', toSend);
            } else if (minus) {
              charge = f;
              payable = amt;
              toSend = Math.max(0, amt - charge);

              console.log('sendType: ', sendType,'chargingType: ', chargingType, 'amount: ', amt,'charge: ', charge, 'payable: ', payable,'payableType: ', payable.type, 'toSend: ', toSend);
            } else {
              charge = 0;
              payable = amt;
              toSend = amt;
            }
          }
        }

        // SENDING rules
        else if (sendType === 'sending') {
          if (sim === 'subscriber' || sim === 'agent') {
			const inFee = roundSpecial(calcInFee(amt));
			const outFee = roundSpecial(calcOutFee(amt));
            if (category === 'inout') {
              charge = inFee + outFee;
              payable = amt + charge;
              toSend = amt + outFee;
            } else if (category === 'subtractin') {
              charge = inFee;
              payable = amt;
              toSend = Math.max(0, amt - charge);
            } else if (category === 'onlyin') {
              charge = inFee;
              payable = amt + inFee;
              toSend = amt;
            } else {
              charge = 0;
              payable = amt;
              toSend = amt;
            }
          }
          else if (sim === 'merchant') {
            const merchantCalc = calculateMerchantCashinSending();
            charge = merchantCalc.charge;
            payable = merchantCalc.payable;
            toSend = merchantCalc.toSend;
            applySpecialFinalRounding = false;
          }
        }

		// Final normalization to avoid display inconsistencies (e.g. charge rounded but toSend not rounded)
		if (applySpecialFinalRounding) {
			charge = roundSpecial(charge);
			toSend = roundSpecial(toSend);
		}
		payable = round2(payable);
		toSend = round2(toSend);

        // render with 2 decimals
        chargeInput.value = Number(charge || 0).toFixed(2);
        amountPayableInput.value = Number(payable || 0).toFixed(2);
        amountToSendInput.value = Number(toSend || 0).toFixed(2);
        validateSaveState();
      }

      // Only show charging type selector when sim type is Merchant
      function updateChargingTypeVisibility() {
        if (simType.value === "Merchant") {
          chargingTypeWrapper.classList.remove('d-none');
        } else {
          chargingTypeWrapper.classList.add('d-none');
          chargingTypeSelect.value = ""; // reset
        }
      }

      // Category options mapping
      const categoryOptions = {
        "Sending": [
          { value: "", text: "---Select---" },
          { value: "Only In", text: "Only In" },
          { value: "Subtract In", text: "Subtract In" },
          { value: "In/Out", text: "In/Out" },
        ],
        "Deposit": [
          { value: "", text: "---Select---" },
          { value: "+ Fee", text: "+ Fee" },
          { value: "- Fee", text: "- Fee" },
        ],
      };

      sendingType.addEventListener("change", updateCategoryOptions);

      sendingType.addEventListener("change", showFields);
      networkSelect.addEventListener("change", showFields);
      simType.addEventListener("change", showFields);

      chargingTypeSelect.addEventListener("change", calculateCharge);

      function updateCategoryOptions() {
        const sendingTypeValue = sendingType.value;
        categorySelect.innerHTML = "";
        if (categoryOptions[sendingTypeValue]) {
          categoryDiv.classList.remove("d-none");
          categoryOptions[sendingTypeValue].forEach(option => {
            const opt = document.createElement("option");
            opt.value = option.value;
            opt.textContent = option.text;
            categorySelect.appendChild(opt);
          });
        } else {
          categoryDiv.classList.add("d-none");
        }
      }

      function showFields() {
        // Keep existing field display logic but ensure charging type visibility is updated
        updateChargingTypeVisibility();
        updateSimTypeOptions();

        const isGhanapayBankSending = isGhanapayNetwork() && normVal(sendingType.value) === 'sending' && normVal(simType.value) === 'bank';

        if (isGhanapayBankSending) {
          categoryDiv.classList.add('d-none');
          if (categorySelect) {
            categorySelect.value = '';
          }
        }

        if (simType.value === "Agent" && sendingType.value === "Deposit") {
          if (networkSelect.value === "TELECEL" || networkSelect.value === "G-MONEY") {
            phoneDiv.classList.remove('d-none');
            tillNumberDiv.classList.remove('d-none');
            phoneLabel.innerText = "Phone number: ";
            tillLabel.innerText = "Till number/Short Code: ";
          } else if (networkSelect.value === "AT") {
            tillNumberDiv.value = '';
            phoneDiv.classList.remove('d-none');
            tillNumberDiv.classList.add('d-none');
            phoneLabel.innerText = "Phone number/Short Code: ";
          } else {
            tillNumberDiv.value = '';
            phoneDiv.classList.remove('d-none');
            tillNumberDiv.classList.add('d-none');
            tillLabel.innerText = "Phone number: ";
          }
        } else if (sendingType.value === "Deposit") {
            categoryDiv.classList.add("d-none");
          /*if (simType.value === "Merchant" && networkSelect.value === "MTN") {
          } else {
            categorySelect.value = '';
            categoryDiv.classList.add("d-none");
          }*/
        } else if ((simType.value === "Agent" || simType.value === "Subscriber") && sendingType.value === "Sending") {
          tillNumber.value = '';
          phoneDiv.classList.remove('d-none');
          tillNumberDiv.classList.add('d-none');
        } else if (simType.value === "Merchant") {
          if (networkSelect.value === "MTN" || networkSelect.value === "G-MONEY" || networkSelect.value === "AT") {
            tillNumber.value = '';
            phoneDiv.classList.remove('d-none');
            tillNumberDiv.classList.add('d-none');
            phoneLabel.innerText = "Phone number/Merchant ID: ";
          } else {
            tillNumber.value = '';
            phoneDiv.classList.remove('d-none');
            tillNumberDiv.classList.add('d-none');
            phoneLabel.innerText = "Phone number: ";
          }
        } else {
          tillNumber.value = '';
          phoneDiv.classList.remove('d-none');
          tillNumberDiv.classList.add('d-none');
        }

        if (isGhanapayNetwork() && simType.value === 'Bank') {
          bankDiv.classList.remove('d-none');
          renderBankOptions();
          senderNumberDiv.classList.remove('d-none');
        } else {
          bankDiv.classList.add('d-none');
          if (bankSelect) {
            bankSelect.value = '';
          }

          if (sendingType.value === 'Sending') {
            senderNumberDiv.classList.remove('d-none');
          } else {
            senderNumberDiv.classList.add('d-none');
            if (senderInput) {
              senderInput.value = '';
            }
          }
        }

        // Recalculate on every field visibility change to keep amounts in sync
        calculateCharge();
      }

      // trigger calculate on relevant changes
      amountLoadInput.addEventListener("change", calculateCharge);
      amountLoadInput.addEventListener("input", calculateCharge);
      amountLoadInput.addEventListener("change", validateSaveState);
      simType.addEventListener("change", calculateCharge);
      sendingType.addEventListener("change", calculateCharge);
      networkSelect.addEventListener("change", calculateCharge);
      categorySelect.addEventListener("change", calculateCharge);
      if (bankSelect) {
        bankSelect.addEventListener('change', validateSaveState);
      }

      if (cashinOpenChangeBtn) {
        cashinOpenChangeBtn.addEventListener('click', showChangeModal);
      }

      if (cashinClearChangeBtn) {
        cashinClearChangeBtn.addEventListener('click', function () {
          resetChangeSelection();
          validateSaveState();
        });
      }

      if (cashinAddChangeRowBtn) {
        cashinAddChangeRowBtn.addEventListener('click', function () {
          const rows = getRawChangeRowsFromModal();
          rows.push({ denomination: '', count: '' });
          renderChangeRows(rows);
        });
      }

      if (cashinApplyChangeBtn) {
        cashinApplyChangeBtn.addEventListener('click', function () {
          const rows = getChangeRowsFromModal();
          const required = getRequiredChangeAmount();
          const selectedTotal = getSelectedChangeTotal(rows);
          const netCash = roundMoney(getCashReceivedAmount() - selectedTotal);
          if (!rows.length || !valuesMatch(selectedTotal, required) || !valuesMatch(netCash, roundMoney(amountPayableInput.value))) {
            updateChangeModalState();
            return;
          }
          changeDenominationsField.value = JSON.stringify(rows);
          if (changeRequiredField) {
            changeRequiredField.value = formatMoney(required);
          }
          syncChangeSummary();
          cashinChangeModal.hide();
          validateSaveState();
        });
      }

      // restore selects and init visibility & calculation
      applyOldSelect(networkSelect);
      applyOldSelect(sendingType);
      applyOldSelect(simType);
      if (bankSelect) {
        applyOldSelect(bankSelect);
      }
      updateCategoryOptions();
      applyOldSelect(categorySelect);
      restoreDenominations();
      if ((!amountInput.value || roundMoney(amountInput.value) <= 0) && roundMoney(totalVolumeField.value) > 0) {
        amountInput.value = totalVolumeField.value;
      }
      showFields();
      validateSender();
      if (cashReceivedAmountField && (!cashReceivedAmountField.value || roundMoney(cashReceivedAmountField.value) <= 0) && roundMoney(amountInput.value) > 0) {
        cashReceivedAmountField.value = formatMoney(amountInput.value);
      }
      updateChargingTypeVisibility();
      calculateCharge();
      syncChangeRequirement();
      setTransactionPreviewState(transactionImageInput && transactionImageInput.files && transactionImageInput.files.length > 0);
      validateSaveState();

      if (balanceAfterDisplay) {
        balanceAfterDisplay.addEventListener('input', validateSaveState);
      }
      amountInput.addEventListener('change', function () {
        syncChangeRequirement();
        validateSaveState();
        validateConfiguredLimit();
      });
	  ['amount_load', 'amount_payable', 'amount_sendable', 'cash_received_amount'].forEach(function(fieldId){
		const field = document.getElementById(fieldId);
		if(field){
			field.addEventListener('input', validateConfiguredLimit);
			field.addEventListener('change', validateConfiguredLimit);
		}
	  });
	  if(networkSelect) networkSelect.addEventListener('change', validateConfiguredLimit);
	  if(simType) simType.addEventListener('change', validateConfiguredLimit);

      if (cashinForm) {
        cashinForm.addEventListener('submit', function (e) {
          syncChangeRequirement();
          if (limitBlocked || !checkAmount()) {
            e.preventDefault();
            if (getRequiredChangeAmount() > 0.01) {
              showChangeModal();
            }
          }
        });
      }
    });
</script>

<?php else:?>
	<div class="alert alert-danger text-center">
		Access denied. You dont have permission for this action
	</div>
<?php endif?>
