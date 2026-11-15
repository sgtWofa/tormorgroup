<?php if(user_can('edit_payment')): ?>
	<?php
		$__old_post = get_value('old_post');
		if (is_array($__old_post) && !empty($__old_post)) {
			$_POST = $__old_post;
			set_value('old_post', []);
		}
		$__row_network_upper = strtoupper(trim((string)($row->network ?? '')));
		$__row_payment_type_upper = strtoupper(trim((string)($row->payment_type ?? '')));
		$__is_cash_network = ($__row_network_upper === 'CASH');
		$__cash_currency = ($__row_payment_type_upper === 'CFA') ? 'CFA' : 'GHS';
		$__bulk_breakdown = function_exists('backoffice_payment_row_breakdown') ? backoffice_payment_row_breakdown($row) : ['items' => [], 'total' => 0.0];
		$__bulk_items = $__bulk_breakdown['items'] ?? [];
		$__is_bulk_payment = in_array($__row_network_upper, ['E-CASH', 'EVD'], true) && !empty($__bulk_items);
		$__raw_denos_json = (string)old_value('denos_json', $row->denos_json ?? '');
		$__raw_denos_json_trim = trim($__raw_denos_json);
		$__denos_json_value = ($__raw_denos_json_trim === '') ? '[]' : $__raw_denos_json_trim;
		if (function_exists('BackOfficeCashdrawer\\normalizeDenominations')) {
			$__normalized = \BackOfficeCashdrawer\normalizeDenominations($__denos_json_value, (float)($row->amount ?? 0), $__cash_currency);
			$__denos_json_value = json_encode($__normalized);
		}
	?>
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
	<?php if(!empty($row)): ?>
	<?php
		$paymentAssignedSnapshot = (!$__is_bulk_payment && function_exists('backoffice_assignment_snapshot_lines'))
			? backoffice_assignment_snapshot_lines((int)($row->pay_to ?? 0), $row->network ?? '', $row->payment_type ?? '')
			: [];
	?>
	<form method="post" enctype="multipart/form-data">		
		<div class="row col-sm-12 col-md-8 col-lg-5 mx-auto shadow p-2 rounded">
			
			<?=csrf()?>

			<h4 class="text-center">
				<span><img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:60px;max-height: 60px;object-fit: cover;"></span>
				Make Payment
			</h4>

			<div class=" col-md-12">
			  <label for="amount" class="form-label">Amount</label>
			  <div class="input-group" id="editPaymentCashAmountGroup" <?php if($__is_cash_network){echo('data-bs-toggle="modal" data-bs-target="#editPaymentDenoModal" style="cursor:pointer;"');} ?> >
			  	<div id="currencySymbol" class="input-group-text">GH₵</div>
			  	<input name="amount" value="<?=old_value('amount', $row->amount ?? 0.00)?>" type="text" class="form-control" id="amount" placeholder="GH&cent;0.00" readonly>
			  </div>				
			  <input type="hidden" id="denos_json" name="denos_json" value="<?=htmlspecialchars($__denos_json_value, ENT_QUOTES)?>" />
				<?php if(!empty($errors['amount'])): ?>
				  <small class="text-danger"><?=$errors['amount']?></small>
				<?php endif ?>
				<?php if(!empty($errors['denos_json']) || !empty($errors['denos_json_mismatch'])): ?>
				  <small class="text-danger d-block"><?=$errors['denos_json'] ?? $errors['denos_json_mismatch']?></small>
				<?php endif ?>
			</div>

			<div class=" col-md-12 ">
				<label for="network" class="form-label">Payment Option</label>
				<select class="form-select" name="network" id="network" onchange="updatePaymentOptions()" disabled>	
				  <option value="">---Select---</option>
				  <!-- Options will be populated dynamically based on transaction type -->
				  <option <?=old_select('network','BANK', $row->network ?? '')?> value="BANK">BANK</option>
				  <option <?=old_select('network','CASH', $row->network ?? '')?> value="CASH">CASH</option>
				  <option <?=old_select('network','E-CASH', $row->network ?? '')?> value="E-CASH">E-CASH</option>
				  <option <?=old_select('network','EVD', $row->network ?? '')?> value="EVD">EVD</option>
				</select>
				<input type="hidden" name="network" value="<?=old_value('network', $row->network ?? '')?>" />
				<?php if(!empty($errors['network'])): ?>
				  <small class="text-danger"><?=$errors['network']?></small>
				<?php endif ?>
			</div>

			<div class=" col-md-12 ">
				<label for="payment_type" class="form-label">Payment Type</label>
				<select class="form-select payment_type" id="payment_type" name="payment_type" disabled>
					<!-- Options will be displayed dynamically -->
				 </select>
				<input type="hidden" name="payment_type" value="<?=old_value('payment_type', $row->payment_type ?? '')?>" />
				<?php if(!empty($errors['payment_type'])): ?>
				  <small class="text-danger"><?=$errors['payment_type']?></small>
				<?php endif ?>
			</div>

			<?php if($__is_bulk_payment): ?>
				<div class="col-md-12">
					<input type="hidden" name="bulk_payment_items" id="bulk_payment_items" value="<?=htmlspecialchars((string)old_value('bulk_payment_items', json_encode($__bulk_items)), ENT_QUOTES)?>" />
					<div class="border rounded p-2 mb-2">
						<div class="d-flex justify-content-between align-items-center mb-2">
							<div class="fw-semibold">Bulk payment breakdown</div>
							<div class="badge bg-primary-subtle text-primary">Total: GHS <span id="bulkPaymentTotal"><?=number_format((float)($__bulk_breakdown['total'] ?? 0), 2)?></span></div>
						</div>
						<div class="table-responsive">
							<table class="table table-sm align-middle mb-0">
								<thead>
									<tr>
										<th>Payment Type</th>
										<th style="width:180px;">Amount Involved</th>
									</tr>
								</thead>
								<tbody id="bulkPaymentRows"></tbody>
							</table>
						</div>
						<small class="text-muted">The payment amount is calculated from these channel amounts.</small>
					</div>
				</div>
			<?php endif; ?>

			<?php if(!empty($paymentAssignedSnapshot)): ?>
				<div class="col-md-12">
					<div class="alert <?=!empty($paymentAssignedSnapshot['missing']) ? 'alert-warning' : 'alert-info'?> mb-2">
						<div class="fw-semibold mb-1">Assigned SIM / Wallet Snapshot</div>
						<?php if(!empty($paymentAssignedSnapshot['missing'])): ?>
							<div><?=esc($paymentAssignedSnapshot['message'] ?? 'No assigned SIM/wallet details found.')?></div>
						<?php else: ?>
							<?php foreach(($paymentAssignedSnapshot['lines'] ?? []) as $line): ?>
								<div>
									<span class="text-muted"><?=esc($line['label'] ?? '')?>:</span>
									<?php if(!empty($line['missing'])): ?>
										<span class="text-warning fw-semibold">Not saved</span>
									<?php else: ?>
										<span class="fw-semibold"><?=esc($line['value'] ?? '')?></span>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>

			<div class=" col-md-12 wallet_out_id">
			  <label for="wallet_out_id" class="form-label">Source of funds</label>
			  <select class="form-select id_type" name="wallet_out_id" id="wallet_out_id" disabled>
				  <option value="">---Select---</option>
				  <?php if(!empty($wallets)):?>
				  	<?php foreach ($wallets as $wallet): ?>			  		
				  		<option <?=old_select('wallet_out_id', $wallet->id, $row->wallet_out_id ?? '')?> value="<?=$wallet->id?>"><?=$wallet->name . ' ' . $wallet->network ?? ''?> [<?=number_format($wallet->balance ?? 0, 2)?>]</option>
				  	<?php endforeach ?>
				  <?php endif;?>
			  </select>			
			  <input type="hidden" name="wallet_out_id" value="<?=old_value('wallet_out_id', (string)($row->wallet_out_id ?? ''))?>" />
				<?php if(!empty($errors['wallet_out_id'])):?>
				  <small class="text-danger js-wallet-out-error"><?=$errors['wallet_out_id']?></small>
				<?php endif?>
			</div>

			<div class=" col-md-12 pay_to">
			  <label for="pay_to" class="form-label">Pay to</label>
			  <select class="form-select id_type" name="pay_to" id="pay_to" disabled>
				  <option value="">---Select---</option>
				  <?php if(!empty($users)):?>
				  	<?php foreach ($users as $user): ?>			  		
				  		<option <?=old_select('pay_to', $user->id, $row->pay_to ?? '')?> value="<?=$user->id?>"><?=$user->first_name . ' ' . $user->last_name ?? ''?> [<?=$user->roles[0]?>]</option>
				  	<?php endforeach ?>
				  <?php endif;?>
			  </select>			
			  <input type="hidden" name="pay_to" value="<?=old_value('pay_to', (string)($row->pay_to ?? ''))?>" />
				<?php if(!empty($errors['pay_to'])):?>
				  <small class="text-danger js-payto-error"><?=$errors['pay_to']?></small>
				<?php endif?>
			</div>

		    <!-- Till/Code Input, visible for specific categories only -->
		    <div class=" col-md-12 receiving_wallet">
		      <label for="receiving_wallet" class="form-label">Receiving account</label>
		      <select class="form-select receiving_wallet" name="receiving_wallet" disabled>
					  <option value="">---Select---</option>
					  <?php if(!empty($otherWallets)):?>
					  	<?php foreach ($otherWallets as $wallet): ?>				  		
					  		<option <?=old_select('receiving_wallet',$wallet->id, $row->receiving_wallet ?? '')?> value="<?=$wallet->id ?? ' ' ?>"><?=strtoupper($wallet->network . ' '. $wallet->wallet_type ?? '')?> (<?=esc($wallet->wallet_number ?? '')?>)</option>
					  <?php endforeach;?>
					  <?php endif;?>
				  </select>
				  <input type="hidden" name="receiving_wallet" value="<?=old_value('receiving_wallet', (string)($row->receiving_wallet ?? ''))?>" />
		      <?php if(!empty($errors['receiving_wallet'])):?>
		        <small class="text-danger"><?=$errors['receiving_wallet']?></small>
		      <?php endif?>
		    </div>
				
			<div class=" col-md-12">
			  <label for="description" class="form-label">Description</label>
				<textarea rows="2" id="description" class="form-control" name="description"  placeholder="Write a brief description for this transaction" readonly><?=old_value('description', $row->description ?? '')?></textarea>			
			</div>
				<?php if(!empty($errors['description'])):?>
				  <small class="text-danger"><?=$errors['description']?></small>
				<?php endif?>

			<div class=" col-md-12 transaction_status">
			  <label for="transaction_status" class="form-label">Status</label>
			  <select class="form-select transaction_status" name="transaction_status"
			  <?php if($row->created_by != $user_id && !user_can('approve_transaction')){echo ('disabled');}?>
			  >
				  <option value="">---Select---</option>
				  <option <?=old_select('transaction_status','Draft', $row->transaction_status ?? '')?> value="Draft">Draft</option>
				  <option <?=old_select('transaction_status','Pending', $row->transaction_status ?? '')?> value="Pending">Pending</option>
				  <option <?=old_select('transaction_status','Rejected', $row->transaction_status ?? '')?> value="Rejected">Rejected</option>
			  </select>
				
				<?php if(!empty($errors['transaction_status'])): ?>
				  <small class="text-danger"><?=$errors['transaction_status']?></small>
				<?php endif ?>
			</div>
			<?php if($row->created_by != $user_id && !user_can('approve_transaction')): ?>
				<input type="hidden" name="transaction_status" value="<?=old_value('transaction_status', $row->transaction_status ?? '')?>" />
			<?php endif ?>

			<div class="d-flex justify-content-between mb-5" style="margin-top:10px;">
				<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
					<button type="button" class="btn btn-primary">
						<i class="fa-solid fa-chevron-left"></i> Back
					</button>
				</a>
				<button class="btn btn-success text-center" type="submit">
					<i class="fa-solid fa-save"></i> Save
				</button>
			</div>
		</div>
	</form>
	<?php else:?>
		<div class="alert alert-danger text-center">
			That record was not found!
		</div>
		
		<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
			<button class="btn btn-primary">Back</button>
		</a>
	<?php endif?>

<?php else: ?>
	<div class="alert alert-danger text-center">
		Access denied. You don’t have permission for this action.
	</div>
<?php endif ?>


<div class="modal fade" id="editPaymentDenoModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
	<div class="modal-content">
	  <div class="modal-header">
		<h5 class="modal-title">Currency Notes</h5>
		<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
	  </div>
	  <div class="modal-body table-responsive">
		<table class="table table-sm align-middle">
		  <thead>
			<tr>
			  <th>Notes</th>
			  <th style="width:90px;">Count</th>
			  <th style="width:120px;">Volume</th>
			</tr>
		  </thead>
		  <tbody>
			<?php $denomsGhs = [200,100,50,20,10,5,2,1,0.5,0.2,0.1]; ?>
			<?php $denomsCfa = [10000,5000,2000,1000,500,200,100,50,20,10]; ?>
			<?php foreach($denomsGhs as $deno): ?>
			<tr data-currency="GHS" data-denomination="<?=$deno?>">
			  <td>GH&#x20B5;<?=rtrim(rtrim(number_format($deno,2), '0'), '.')?></td>
			  <td><input type="number" min="0" class="form-control form-control-sm deno-count" /></td>
			  <td><input type="text" class="form-control form-control-sm deno-volume" readonly /></td>
			</tr>
			<?php endforeach; ?>
			<?php foreach($denomsCfa as $deno): ?>
			<tr data-currency="CFA" data-denomination="<?=$deno?>">
			  <td><?=number_format($deno,0)?> CFA</td>
			  <td><input type="number" min="0" class="form-control form-control-sm deno-count" /></td>
			  <td><input type="text" class="form-control form-control-sm deno-volume" readonly /></td>
			</tr>
			<?php endforeach; ?>
		  </tbody>
		  <tfoot>
			<tr>
			  <th>Total</th>
			  <th><input type="text" class="form-control form-control-sm" id="editPaymentDenoCount" readonly /></th>
			  <th><input type="text" class="form-control form-control-sm" id="editPaymentDenoTotal" readonly /></th>
			</tr>
		  </tfoot>
		</table>
	  </div>
	  <div class="modal-footer">
		<button type="button" class="btn btn-outline-secondary" id="editPaymentDenoReset">Reset</button>
		<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
		<button type="button" class="btn btn-primary" id="editPaymentDenoApply">Apply</button>
	  </div>
	</div>
  </div>
</div>

<script>
	/*const networkOptions = {
		"CASH": [
			{ value: "", text: "---Select---" },
			{ value: "GHS", text: "GHS" },
			{ value: "CFA", text: "CFA" }
		],
		"E-CASH": [
			{ value: "", text: "---Select---" },
			{ value: "AT", text: "AT" },
			{ value: "G-MONEY", text: "G-MONEY" },
			{ value: "MTN", text: "MTN" },
			{ value: "TELECEL", text: "TELECEL" },
			{ value: "ZEEPAY", text: "ZEEPAY" },
			{ value: "GHANAPAY", text: "GHANAPAY" },
		],
		"EVD": [
			{ value: "", text: "---Select---" },
			{ value: "AT", text: "AT" },
			{ value: "MTN", text: "MTN" },
			{ value: "TELECEL", text: "TELECEL" },
		],
		"BANK": [
			{ value: "CALBANK", text: "CALBANK" },
		],
	};
	const paymentType = document.getElementById('payment_type');
	const networkSelect = document.getElementById('network');
	const walletOutSelect = document.getElementById('wallet_out_id');
*/

// Existing values from the PHP backend
const existingNetwork = "<?=addslashes($row->network ?? '')?>";
const existingPaymentType = "<?=addslashes($row->payment_type ?? '')?>";

const networkOptions = {
    "CASH": [
        { value: "", text: "---Select---" },
        { value: "GHS", text: "GHS" },
        { value: "CFA", text: "CFA" }
    ],
    "E-CASH": [
        { value: "", text: "---Select---" },
        { value: "AT", text: "AT" },
        { value: "G-MONEY", text: "G-MONEY" },
        { value: "GHANAPAY", text: "GHANAPAY" },
        { value: "MTN", text: "MTN" },
        { value: "TELECEL", text: "TELECEL" },
        { value: "ZEEPAY", text: "ZEEPAY" },
    ],
    "EVD": [
        { value: "", text: "---Select---" },
        { value: "AT_EVD", text: "AT_EVD" },
        { value: "MTN_EVD", text: "MTN_EVD" },
        { value: "TELECEL_EVD", text: "TELECEL_EVD" },
    ],
    "BANK": [
        { value: "CALBANK", text: "CALBANK" },
    ],
};

const paymentType = document.getElementById('payment_type');
const networkSelect = document.getElementById('network');
const walletOutSelect = document.getElementById('wallet_out_id');
const amountInputMain = document.getElementById('amount');
const bulkPaymentRows = document.getElementById('bulkPaymentRows');
const bulkPaymentItemsInput = document.getElementById('bulk_payment_items');
const bulkPaymentTotal = document.getElementById('bulkPaymentTotal');

function updatePaymentOptions() {
    const networkSelectValue = networkSelect.value;
    paymentType.innerHTML = ""; // Clear current options

    // Populate options based on the selected network type
    if (networkOptions[networkSelectValue]) {
        networkOptions[networkSelectValue].forEach(option => {
            const opt = document.createElement('option');
            opt.value = option.value;
            opt.textContent = option.text;

            // Pre-select the option if it matches the existing payment type
            if (option.value === existingPaymentType) {
                opt.selected = true;
            }

            paymentType.appendChild(opt);
        });
    }
}

function bulkItemsMap(){
	const map = {};
	if (!bulkPaymentItemsInput) return map;
	try {
		const items = JSON.parse(String(bulkPaymentItemsInput.value || '[]'));
		if (Array.isArray(items)) {
			items.forEach(item => {
				const channel = String(item.channel || '').toUpperCase();
				const amount = Number(item.amount || 0);
				if (channel && Number.isFinite(amount) && amount > 0) map[channel] = amount;
			});
		}
	} catch(e) {}
	return map;
}

function renderEditBulkRows(){
	if (!bulkPaymentRows || !bulkPaymentItemsInput) return;
	const networkValue = String(existingNetwork || '').toUpperCase();
	const options = (networkOptions[networkValue] || []).filter(option => String(option.value || '').trim() !== '');
	const existing = bulkItemsMap();
	bulkPaymentRows.innerHTML = options.map(option => {
		const channel = String(option.value || '').toUpperCase();
		const amount = existing[channel] || '';
		return '<tr><td class="fw-semibold">' + String(option.text || channel) + '</td><td><input type="number" min="0" step="' + (channel.includes('TELECEL') ? '1' : '0.01') + '" class="form-control form-control-sm bulk-payment-amount" data-channel="' + channel + '" value="' + (amount ? String(amount) : '') + '"></td></tr>';
	}).join('');
	bulkPaymentRows.querySelectorAll('.bulk-payment-amount').forEach(input => input.addEventListener('input', recalcEditBulkTotal));
	recalcEditBulkTotal();
}

function recalcEditBulkTotal(){
	if (!bulkPaymentRows || !bulkPaymentItemsInput) return 0;
	const items = [];
	let total = 0;
	bulkPaymentRows.querySelectorAll('.bulk-payment-amount').forEach(input => {
		const channel = String(input.dataset.channel || '').toUpperCase();
		const amount = Number(input.value || 0);
		if (channel.includes('TELECEL')) {
			input.setCustomValidity((!input.value || Math.abs(amount - Math.round(amount)) < 0.00001) ? '' : 'Telecel payments must use whole-number amounts only.');
		}
		if (channel && Number.isFinite(amount) && amount > 0) {
			const rounded = Math.round(amount * 100) / 100;
			items.push({channel, amount: rounded});
			total += rounded;
		}
	});
	total = Math.round(total * 100) / 100;
	bulkPaymentItemsInput.value = JSON.stringify(items);
	if (bulkPaymentTotal) bulkPaymentTotal.textContent = total.toFixed(2);
	if (amountInputMain) amountInputMain.value = total ? total.toFixed(2) : '';
	return total;
}

// Prefill fields on page load
document.addEventListener("DOMContentLoaded", function () {
    if (existingNetwork) {
        networkSelect.value = existingNetwork;
    }
    updatePaymentOptions(); // Populate payment type options based on network
	renderEditBulkRows();
});

// Event listener for real-time updates
networkSelect.addEventListener("change", updatePaymentOptions);

(function(){
	const modalEl = document.getElementById('editPaymentDenoModal');
	if(!modalEl) return;
	const denosInput = document.getElementById('denos_json');
	const amountInput = document.getElementById('amount');
	const countField = document.getElementById('editPaymentDenoCount');
	const totalField = document.getElementById('editPaymentDenoTotal');
	const resetBtn = document.getElementById('editPaymentDenoReset');
	const applyBtn = document.getElementById('editPaymentDenoApply');
	const countInputs = modalEl.querySelectorAll('.deno-count');
	const denomRows = modalEl.querySelectorAll('tbody tr[data-denomination]');

	function getSelectedCurrency(){
		if (paymentType && String(paymentType.value || '').toUpperCase() === 'CFA') return 'CFA';
		if (denosInput) {
			const raw = String(denosInput.value || '').trim();
			if (raw) {
				try{
					const payload = JSON.parse(raw);
					if (Array.isArray(payload)) {
						for (const entry of payload) {
							if (String(entry?.currency || '').toUpperCase() === 'CFA') return 'CFA';
						}
					}
				}catch(e){}
			}
		}
		return 'GHS';
	}

	function applyCurrencyVisibility(){
		const cur = getSelectedCurrency();
		denomRows.forEach(row => {
			row.classList.toggle('d-none', String(row.dataset.currency || 'GHS').toUpperCase() !== cur);
		});
	}

	function recalc(){
		applyCurrencyVisibility();
		const cur = getSelectedCurrency();
		let totalCount = 0;
		let totalVolume = 0;
		const snapshot = [];
		denomRows.forEach(row => {
			if (row.classList.contains('d-none')) return;
			const denom = parseFloat(row.dataset.denomination || '0') || 0;
			const inp = row.querySelector('.deno-count');
			const count = inp ? (parseInt(inp.value || '0', 10) || 0) : 0;
			const volume = denom * count;
			const volInput = row.querySelector('.deno-volume');
			if (volInput) volInput.value = volume ? volume.toFixed((cur === 'CFA') ? 0 : 2) : '';
			if (count > 0 && volume > 0) {
				snapshot.push({currency: cur, denomination: denom, count, volume});
				totalCount += count;
				totalVolume += volume;
			}
		});
		if (countField) countField.value = totalCount ? String(totalCount) : '';
		if (totalField) totalField.value = totalVolume ? totalVolume.toFixed((cur === 'CFA') ? 0 : 2) : '';
		return {snapshot, totalVolume};
	}

	function restoreFromHidden(){
		countInputs.forEach(inp => {
			inp.value = '';
			const row = inp.closest('tr');
			if (row) {
				const vol = row.querySelector('.deno-volume');
				if (vol) vol.value = '';
			}
		});
		applyCurrencyVisibility();
		try{
			const payload = JSON.parse((denosInput && denosInput.value) ? denosInput.value : '[]');
			if (Array.isArray(payload)) {
				payload.forEach(entry => {
					countInputs.forEach(inp => {
						const row = inp.closest('tr');
						if (!row) return;
						const rowCur = String(row.dataset.currency || 'GHS').toUpperCase();
						const denom = parseFloat(row.dataset.denomination || '0') || 0;
						const entryCur = String(entry.currency || 'GHS').toUpperCase();
						const entryDenom = parseFloat(entry.denomination || 0) || 0;
						if (rowCur === entryCur && Math.abs(entryDenom - denom) < 0.0001) {
							inp.value = String(parseInt(entry.count || 0, 10) || 0);
						}
					});
				});
			}
		}catch(e){}
		recalc();
		if (amountInput && totalField && totalField.value) {
			const cur = getSelectedCurrency();
			amountInput.value = parseFloat(totalField.value).toFixed((cur === 'CFA') ? 0 : 2);
		}
	}

	modalEl.addEventListener('shown.bs.modal', restoreFromHidden);
	countInputs.forEach(input => input.addEventListener('input', recalc));
	if (paymentType) paymentType.addEventListener('change', () => {
		if (denosInput) denosInput.value = '';
		countInputs.forEach(inp => {
			inp.value = '';
			const row = inp.closest('tr');
			if (row) {
				const vol = row.querySelector('.deno-volume');
				if (vol) vol.value = '';
			}
		});
		recalc();
	});
	if (resetBtn) resetBtn.addEventListener('click', () => {
		if (denosInput) denosInput.value = '';
		countInputs.forEach(inp => {
			inp.value = '';
			const row = inp.closest('tr');
			if (row) {
				const vol = row.querySelector('.deno-volume');
				if (vol) vol.value = '';
			}
		});
		recalc();
	});
	if (applyBtn) applyBtn.addEventListener('click', () => {
		const {snapshot, totalVolume} = recalc();
		if (denosInput) denosInput.value = JSON.stringify(snapshot);
		if (amountInput) {
			const cur = getSelectedCurrency();
			amountInput.value = totalVolume ? Number(totalVolume).toFixed((cur === 'CFA') ? 0 : 2) : '';
		}
		const bsModal = bootstrap.Modal.getInstance(modalEl);
		if (bsModal) bsModal.hide();
	});
})();

</script>
