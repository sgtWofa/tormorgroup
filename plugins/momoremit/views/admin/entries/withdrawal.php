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
	<form method="post" enctype="multipart/form-data" class="mb-3">
		
	<div class="row col-md-8 col-lg-5 mx-auto shadow p-2 rounded">
		
		<?=csrf()?>
		<!-- Hidden fields to store cashdrawer data -->
		<input type="hidden" id="cashdrawerDenos" value="<?= htmlspecialchars(json_encode($cashdrawerDenos ?? []), ENT_QUOTES, 'UTF-8') ?>">
		<input type="hidden" id="cashdrawerBalances" value="<?= htmlspecialchars(json_encode($cashdrawerBalances ?? []), ENT_QUOTES, 'UTF-8') ?>">

		<h4 class="text-center">
			<span><img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:60px;max-height: 60px;object-fit: cover;"></span>
			Cash Out
		</h4>

    <!-- Transaction Type Selection -->
    <div class="col-md-12">
      <label for="transaction_type" class="form-label">Transaction type</label>
      <select class="form-select" id="transaction_type" name="transaction_type">
        <option <?=old_select('transaction_type','Withdrawal')?> value="Withdrawal" selected>Withdrawal</option>
      </select>
      <?php if(!empty($errors['transaction_type'])):?>
        <small class="text-danger"><?=$errors['transaction_type']?></small>
      <?php endif?>
    </div>
 
		<!-- Network Selection -->
    <div class="col-md-12">
      <label for="network" class="form-label">Select Network</label>
	  <select class="form-select" name="network" id="network" data-old="<?=esc(old_value('network'))?>">
        <option value="">---Select---</option>
        <option value="MTN">MTN</option>
        <option value="TELECEL">TELECEL</option>
        <option value="AT">AT</option>
        <option value="ZEEPAY">ZEEPAY</option>
        <option value="GHANAPAY">GHANAPAY</option>
        <option value="G-MONEY">G-MONEY</option>
      </select>
      <?php if(!empty($errors['network'])):?>
        <small class="text-danger"><?=$errors['network']?></small>
      <?php endif?> 
    </div>

    <!-- Category Selection, dynamic options populated by JavaScript -->
    <div class="col-md-12 category">
      <label for="category" class="form-label">Category</label>
	  <select class="form-select" name="category" id="category" data-old="<?=esc(old_value('category'))?>"></select>
      <?php if(!empty($errors['category'])):?>
        <small class="text-danger"><?=$errors['category']?></small>
      <?php endif?>
    </div>

    <!-- Phone Input -->
    <div class="col-md-12">
      <label for="phone" class="form-label">Phone</label>
      <input name="phone" value="<?=old_value('phone')?>" type="text" class="form-control" id="phone" placeholder="Enter phone number">
      <?php if(!empty($errors['phone'])):?>
        <small class="text-danger"><?=$errors['phone']?></small>
      <?php endif?>
    </div>

    <!-- Token Number Input, visible for specific categories only -->
    <div class="col-md-12 token d-none">
      <label for="token" class="form-label">Token number</label>
      <input name="token" value="<?=old_value('token')?>" type="text" class="form-control" id="token" placeholder="Enter token number">
      <?php if(!empty($errors['token'])):?>
        <small class="text-danger"><?=$errors['token']?></small>
      <?php endif?>
    </div>

	    <div class="col-md-12 token d-none">
	      <label for="id_number" class="form-label">ID number</label>
	      <input name="id_number" value="<?=old_value('id_number')?>" type="text" class="form-control" id="id_number" placeholder="GHA-123456789-1" pattern="GHA-[0-9]{9}-[0-9]">
	      <?php if(!empty($errors['id_number'])):?>
	        <small class="text-danger"><?=$errors['id_number']?></small>
	      <?php endif?>
	      <small class="text-muted d-block">Use format: GHA-123456789-1</small>
	    </div>

	    <div class="col-md-12 token receiver d-none">
	      <label for="receiver" class="form-label">Receiver's phone</label>
	      <input name="receiver" value="<?=old_value('receiver')?>" type="text" class="form-control" id="receiver" placeholder="Enter receiver's phone number">
	    </div>

		<div class="col-md-12 token d-none">
		  <label for="secret_code" class="form-label">Secret code</label>
		  <input name="secret_code" value="<?=old_value('secret_code')?>" type="text" class="form-control" id="secret_code" placeholder="Enter ID number here">
			
			<?php if(!empty($errors['secret_code'])):?>
			  <small class="text-danger"><?=$errors['secret_code']?></small>
			<?php endif?>
		</div>

    <!-- Amount Input with Calculator Modal Trigger -->
    <div class="col-md-12">
      <label for="amount_to_withdraw" class="form-label">Amount to withdraw</label>
          <input name="amount_to_withdraw" value="<?=old_value('amount_to_withdraw')?>" type="number" step="0.01" class="form-control" id="amount_to_withdraw" placeholder="Enter amount to withdraw" required>
					<small id="limitValidationError" class="text-danger d-block mt-1"></small>
    </div>

    <!-- Balance After -->
    <div class="col-md-12">
      <label for="balance_after" class="form-label">Balance after (system generated)</label>
      <div class="input-group">
        <div id="currencySymbol" class="input-group-text">GH₵</div>
        <input name="balance_after" value="<?=old_value('balance_after')?>" type="text" inputmode="decimal" class="form-control" id="balance_after" placeholder="GH&cent;0.00" readonly>
      </div>
	  <?php if(!empty($errors['balance_after'])):?>
				  <small class="text-danger"><?=$errors['balance_after']?></small>
			<?php endif?>
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

    <div class="col-md-12">
      <label for="amount" class="form-label">Amount paid Out</label>
      <label class="input-group" data-bs-toggle="modal" data-bs-target="#staticBackdrop"> 
        <i class="fa-solid fa-calculator input-group-text text-warning"></i>
          <input name="amount" type="number" step="0.01" class="form-control" id="amount" placeholder="Enter amount" readonly>
	        <input type="hidden" id="denos" name="denos" value="<?=htmlspecialchars(old_value('denos'), ENT_QUOTES, 'UTF-8')?>" />
      </label>
        <small class="text-danger js-amount-error"></small>
      <?php if(!empty($errors['denos'])):?>
        <small class="text-danger"><?=$errors['denos']?></small>
      <?php endif?>
      <?php if(!empty($errors['amount'])):?>
        <small class="text-danger"><?=$errors['amount']?></small>
      <?php endif?>
    </div>
 
		<div class="d-grid gap-2 d-sm-flex justify-content-between mt-3 mb-5">
			<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>" class="w-100">
				<button type="button" class="btn btn-outline-primary w-100" style="border:none;">
					<i class="fa-solid fa-chevron-left"></i> Back
				</button>
			</a>
			<button class="btn btn-outline-danger save w-100" style="border:none;" type="submit">
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

<!-- JavaScript: Calculate Charges, Validate Wallet, Show Fields -->
<script>
  document.addEventListener("DOMContentLoaded", function () {
    const countInputs = document.querySelectorAll(".count-input");
	  const totalCountField = document.getElementById("total-count");
	  const totalVolumeField = document.getElementById("total-volume");
	  const denosField = document.querySelector("#denos");
	  const submitBtn = document.querySelector('.submit');
	  const saveBtn = document.querySelector('.save');
	  const restoreBtn = document.querySelector('.restore');
	  const resetBtn = document.querySelector('.reset');
	  const staticBackdropModal = new bootstrap.Modal(document.getElementById('staticBackdrop'));
	  const amountInput = document.getElementById("amount");
	  const amountToWithdrawInput = document.getElementById("amount_to_withdraw");
	  const networkSelect = document.querySelector('[name="network"]');
	  const balanceAfter = document.getElementById('balance_after');
	  const cashdrawerBalancesField = document.getElementById('cashdrawerBalances');
	  const amountError = document.querySelector('.js-amount-error');
	  const transactionImageInput = document.getElementById('transaction_image');
	  const transactionImagePreviewWrap = document.getElementById('transactionImagePreviewWrap');
	  const transactionImagePreview = document.getElementById('transactionImagePreview');
	  const transactionImagePreviewCard = document.getElementById('transactionImagePreviewCard');
	  const transactionImagePreviewPlaceholder = document.getElementById('transactionImagePreviewPlaceholder');
	  const transactionImageError = document.querySelector('.js-transaction-image-error');
	  const limitValidationError = document.getElementById('limitValidationError');
	  let limitBlocked = false;
	  let limitRequest = 0;

	  async function validateConfiguredLimit(){
		const network = (networkSelect && networkSelect.value || '').trim();
		const amount = parseFloat((amountToWithdrawInput && amountToWithdrawInput.value || '0').replace(/,/g, '')) || 0;
		if(!network || amount <= 0){
			limitBlocked = false;
			if(limitValidationError) limitValidationError.textContent = '';
			validateSaveState();
			return;
		}
		const requestId = ++limitRequest;
		limitBlocked = true;
		if(limitValidationError) limitValidationError.textContent = 'Checking configured withdrawal limit...';
		validateSaveState();
		try{
			const params = new URLSearchParams({transaction_type: 'Withdrawal', network, amount: String(amount)});
			const response = await fetch('<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/limit-check?' + params.toString(), {headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}, credentials: 'include'});
			const responseText = await response.text();
			let data;
			try { data = JSON.parse(responseText); } catch (parseError) { throw new Error('The server returned an invalid limit response. Please refresh and try again.'); }
			if(!response.ok || !data.success) throw new Error(data.message || 'Unable to verify the configured withdrawal limit.');
			if(requestId !== limitRequest) return;
			limitBlocked = Boolean(data.exceeded);
			if(limitValidationError){
				limitValidationError.textContent = data.exceeded ? 'Limit exceeded. ' + data.message : (data.configured ? data.message : '');
			}
		}catch(error){
			if(requestId !== limitRequest) return;
			limitBlocked = true;
			if(limitValidationError) limitValidationError.textContent = 'Unable to verify the configured withdrawal limit.';
		}
		validateSaveState();
	  }

    // Load denomination data from localStorage
	  const savedDenos = localStorage.getItem("denosData");

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

	  function parseJsonArray(field){
		try{
			const raw = (field && typeof field.value === 'string') ? field.value.trim() : '';
			if(!raw){
				return [];
			}
			const decoded = new DOMParser().parseFromString('<!doctype html><body>' + raw, 'text/html').body.textContent;
			const parsed = JSON.parse(decoded || '[]');
			return Array.isArray(parsed) ? parsed : [];
		}catch(e){
			return [];
		}
	  }

	  function normalizeChannelKey(value){
		return (value || '')
			.toString()
			.trim()
			.toUpperCase()
			.replace(/[^A-Z0-9]+/g, '_')
			.replace(/^_+|_+$/g, '');
	  }

	  function getResolvedNetworkBalance(networkValue){
		const rows = parseJsonArray(cashdrawerBalancesField);
		if(!rows.length){
			return { found: false, key: '', balance: 0 };
		}

		const networkUpper = (networkValue || '').toString().trim().toUpperCase();
		if(!networkUpper){
			return { found: false, key: '', balance: 0 };
		}

		const networkNormalized = normalizeChannelKey(networkUpper);
		const baseChannelKey = ['MTN', 'TELECEL', 'AT'].includes(networkUpper) ? networkUpper : networkNormalized;
		const isMomoPay = ['MOMOPAY', 'MTN_MOMOPAY', 'MTN-MOMOPAY'].includes(networkUpper);
		const keysToTry = isMomoPay ? [
			'ECASH_MTN_MOMOPAY',
			'MTN_MOMOPAY',
			'MTN-MOMOPAY',
			'ECASH_MTN-MOMOPAY',
			'ECASH_MTN_MOMOPAY_OUT',
			'ECASH_MTN-MOMOPAY-OUT',
		] : [
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
		];

		for(const key of keysToTry){
			const match = rows.find(row => normalizeChannelKey(row.channel_key) === normalizeChannelKey(key) && String(row.channel_key || '').trim().toUpperCase() !== 'CASH_DRAWER');
			if(match){
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

	  function roundMoney(value){
		const num = parseFloat(value) || 0;
		return Math.round((num + Number.EPSILON) * 100) / 100;
	  }

	  function updateBalanceAfterField(){
		if(!balanceAfter){
			return { found: false, balance: 0, projected: 0 };
		}
		const resolved = getResolvedNetworkBalance(networkSelect ? networkSelect.value : '');
		const withdrawnAmount = parseFloat(amountToWithdrawInput.value) || 0;
		if(!resolved.found || withdrawnAmount <= 0){
			balanceAfter.value = '';
			return {
				found: resolved.found,
				balance: resolved.balance,
				projected: 0,
			};
		}
		const projected = roundMoney(resolved.balance + withdrawnAmount);
		balanceAfter.value = projected.toFixed(2);
		return {
			found: true,
			balance: resolved.balance,
			projected,
		};
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
	    updateBalanceAfterField();
	  }

	  // restore selects + dependent options
	  const categorySelectEl = document.getElementById('category');
	  applyOldSelect(networkSelect);
	  updateCategoryOptions();
	  applyOldSelect(categorySelectEl);
	  restoreDenominations();

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
		updateBalanceAfterField();
	  }

	  function updateCategoryOptions() {
	    const networkSelectValue = networkSelect.value;
	    const categorySelect = document.getElementById('category');
	    categorySelect.innerHTML = ""; // Clear existing options

	    if (networkSelectValue) {
	      categorySelect.innerHTML = `
	        <option value="">---Select---</option>
	        <option value="self">Self</option>
	        <option value="3rd party">3rd Party</option>
	        <option value="token">Token</option>
	      `;
	    }
	  }

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
      const totalVolumeText = totalVolume.toFixed(2);
      totalVolumeField.value = totalVolumeText;
      if (amountInput) {
        amountInput.value = totalCount > 0 ? totalVolumeText : '';
      }

	    // Store serialized currency details in both localStorage and the hidden field
	    const serializedCurrency = JSON.stringify(currency);
	    localStorage.setItem("denosData", serializedCurrency);
	    denosField.value = serializedCurrency;
	   }	

    submitBtn.addEventListener('click', function() {
   		updateVolumesAndTotals();
      staticBackdropModal.hide();
      validateSaveState();
    });	    

    restoreBtn.addEventListener('click', function() {
    	// Restore denominations
  		restoreDenominations();
    });

    resetBtn.addEventListener('click', function() {
    	// Reset denominations
  		resetDenominations();
    });

     //saveBtn.classList.add('disabled'); //disable submit btn

    function checkAmount() {
      const amount = parseFloat(amountInput.value) || 0;
      const amountToWithdraw = parseFloat(amountToWithdrawInput.value) || 0;
	    	amountError.innerText = "";
      if (Math.abs(amount - amountToWithdraw) > 0.01) {
	    	amountError.textContent = "Amount paid out must match the amount to withdraw";
	    	return false;
	    }else{
	    	amountError.innerText = "";
	    	return true;

      }
    }


	  function validateSaveState() {
	  	updateBalanceAfterField();
	  	const withdrawAmountOk = (amountToWithdrawInput.value || '').trim() !== '';
	  	const categoryOk = categorySelectEl && (categorySelectEl.value || '').trim() !== '';
	  	const dynamicFieldsOk = Array.from(document.querySelectorAll('.token:not(.d-none) input')).every(function(input) {
	  		return (input.value || '').trim() !== '';
	  	});
	  	const imageOk = transactionImageInput && transactionImageInput.files && transactionImageInput.files.length > 0;
	  	const amountOk = checkAmount();
	  	
	  	transactionImageError.textContent = '';
	  	if (!imageOk) {
	  		transactionImageError.textContent = 'Transaction picture is required';
	  	}
	  	
	  	if (withdrawAmountOk && categoryOk && dynamicFieldsOk && imageOk && amountOk && !limitBlocked) {
	  		saveBtn.classList.remove('disabled');
	  		saveBtn.disabled = false;
	  	} else {
	  		saveBtn.classList.add('disabled');
	  		saveBtn.disabled = true;
	  	}
	  }

	  saveBtn.classList.add('disabled');
	  saveBtn.disabled = true;


	  const setTransactionPreviewState = (hasImage) => {
	  	if (transactionImagePreviewPlaceholder) {
	  		transactionImagePreviewPlaceholder.style.display = hasImage ? 'none' : 'flex';
	  	}
	  	if (transactionImagePreview) {
	  		transactionImagePreview.style.display = hasImage ? 'block' : 'none';
	  	}
	  };

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
	  			transactionImagePreview.src = '';
	  			setTransactionPreviewState(false);
	  			validateSaveState();
	  			return;
	  		}
	  		if (!file.type || !file.type.startsWith('image/')) {
	  			transactionImagePreview.src = '';
	  			setTransactionPreviewState(false);
	  			transactionImageError.textContent = 'Invalid file type. Please select an image.';
	  			saveBtn.classList.add('disabled');
	  			saveBtn.disabled = true;
	  			return;
	  		}
	  		const reader = new FileReader();
	  		reader.onload = function(e) {
	  			transactionImagePreview.src = e.target.result;
	  			setTransactionPreviewState(true);
	  			validateSaveState();
	  		};
	  		reader.readAsDataURL(file);
	  	});
	  }

	  balanceAfter.addEventListener('input', validateSaveState);
	  amountToWithdrawInput.addEventListener('input', function(){
		validateSaveState();
		validateConfiguredLimit();
	  });
	  amountInput.addEventListener('change', validateSaveState);
	  setTransactionPreviewState(transactionImageInput && transactionImageInput.files && transactionImageInput.files.length > 0);
	  validateSaveState();

    // Dynamic Field Display Based on Transaction Type and Category
    const categoryOptions = {
      "MTN": [
        { value: "", text: "---Select---" },
        { value: "self", text: "self" },
        { value: "3rd party", text: "3rd party" },
        { value: "token", text: "token" }
      ],
      "ZEEPAY": [
        { value: "", text: "---Select---" },
        { value: "self", text: "self" },
        { value: "3rd party", text: "3rd party" },
        { value: "token", text: "token" }
      ],
      "GHANAPAY": [
        { value: "", text: "---Select---" },
        { value: "self", text: "self" },
        { value: "3rd party", text: "3rd party" },
        { value: "token", text: "token" }
      ],
      "AT": [
        { value: "", text: "---Select---" },
        { value: "token", text: "token" }
      ],
      "TELECEL": [
        { value: "", text: "---Select---" },
        { value: "token", text: "token" }
      ],
      "G-MONEY": [
        { value: "", text: "---Select---" },
        { value: "token", text: "token" }
      ]
    };

  const transactionType = document.getElementById('transaction_type');
  const categorySelect = document.getElementById('category');
  const categoryDiv = document.querySelector('.category');
  const tokenFields = document.querySelectorAll('.token');
  const receiver = document.querySelector('.receiver');
  tokenFields.forEach(function(field) {
    field.querySelectorAll('input, select, textarea').forEach(function(input) {
      input.addEventListener('input', validateSaveState);
      input.addEventListener('change', validateSaveState);
    });
  });

  // Add event listener for network change
	networkSelect.addEventListener('change', function() {
	  updateCategoryOptionsDynamic();
	  updateBalanceAfterField();
	  validateSaveState();
	  validateConfiguredLimit();
	});

// Modify updateCategoryOptions to correctly populate categories
function updateCategoryOptionsDynamic() {
  const networkSelectValue = networkSelect.value;
  categorySelect.innerHTML = ""; // Clear existing options

  if (categoryOptions[networkSelectValue]) {
    categoryDiv.classList.remove('d-none');
    categoryOptions[networkSelectValue].forEach(option => {
      const opt = document.createElement('option');
      opt.value = option.value;
      opt.textContent = option.text;
      categorySelect.appendChild(opt);
    });
  } else {
    categoryDiv.classList.add('d-none');
  }
  showFields(); // Update visibility of token fields
}

    function showFields() {
      const transactionTypeValue = transactionType.value;
      const categoryValue = categorySelect.value;
      tokenFields.forEach(field => field.classList.add('d-none'));
      receiver.classList.add('d-none');

        if (categoryValue === 'token') {
          tokenFields.forEach(field => field.classList.remove('d-none'));
          receiver.classList.remove('d-none');
        } else if (categoryValue === '3rd party') {
          receiver.classList.remove('d-none');
        }
    }

    categorySelect.addEventListener("change", function() {
      showFields();
      validateSaveState();
    });
  });
</script>


<?php else:?>
	<div class="alert alert-danger text-center">
		Access denied. You dont have permission for this action
	</div>
<?php endif?>

