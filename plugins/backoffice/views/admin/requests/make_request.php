<?php if(user_can('add_request')): ?>
	<?php
		$__errors_session = get_value('errors');
		$__request_swal_error = '';
		if (is_array($__errors_session) && !empty($__errors_session)) {
			foreach (['network', 'request_type', 'amount', 'denos_json', 'denos_json_mismatch', 'assigned', 'description', 'transaction_status', 'usn'] as $__errorKey) {
				$__candidateError = trim((string)($__errors_session[$__errorKey] ?? ''));
				if ($__candidateError !== '') {
					$__request_swal_error = $__candidateError;
					break;
				}
			}
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
	<form method="post" enctype="multipart/form-data">		
		<div class="row col-md-12 col-md-8 col-lg-6 mx-auto shadow p-2 rounded">
			
			<?=csrf()?>
			<input type="hidden" id="mr_server_error" value="<?=htmlspecialchars($__request_swal_error, ENT_QUOTES)?>" />

			<h4 class="text-center">
				<span><img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:60px;max-height: 60px;object-fit: cover;"></span>
				Make Request
			</h4>
	 
			<div class="col-md-12 ">
				<label for="network" class="form-label">Request option</label>
				<select class="form-select" name="network" id="network" onchange="updateRequestOptions()">	
				  <option value="">---Select---</option>
				  <!-- Options will be populated dynamically based on transaction type -->
				  <option <?=old_select('network','BANK')?> value="BANK">BANK</option>
				  <option <?=old_select('network','CASH')?> value="CASH">CASH</option>
				  <option <?=old_select('network','E-CASH')?> value="E-CASH">E-CASH</option>
				  <option <?=old_select('network','EVD')?> value="EVD">EVD</option>
				</select>
				<?php if(!empty($errors['network']) && (string)$errors['network'] !== $__request_swal_error): ?>
				  <small class="text-danger"><?=$errors['network']?></small>
				<?php endif ?>
			</div>
	 
			<div class="col-md-12 ">
				<label for="request_type" class="form-label">Request type</label>
				<select class="form-select request_type" id="request_type" name="request_type">
					<!-- Options will be displayed dynamically -->
				 </select>
				<?php if(!empty($errors['request_type']) && (string)$errors['request_type'] !== $__request_swal_error): ?>
				  <small class="text-danger"><?=$errors['request_type']?></small>
				<?php endif ?>
			</div>

			<div class="col-md-12">
				<div id="requestAssignedSimDetails" class="alert alert-secondary d-none mb-2"></div>
			</div>

		    <div class="col-md-12">
		      <label for="amount" class="form-label">Amount </label>
		      <div class="input-group" id="cashAmountGroup" data-bs-toggle="modal" data-bs-target="#requestDenoModal" style="cursor:pointer;">
		        <span class="input-group-text"><i class="fa-solid fa-calculator text-warning"></i></span>
		        <input name="amount" value="<?=old_value('amount')?>" type="number" step="0.01" class="form-control" id="amount" placeholder="Use calculator" readonly>
		      </div>
		      <small class="text-muted" id="amountHint">Use the calculator for cash requests. Other request types allow manual amounts.</small>
		      <input type="hidden" name="denos_json" id="denos_json" value="<?=old_value('denos_json')?>" />
		      <?php if(!empty($errors['amount']) && (string)$errors['amount'] !== $__request_swal_error):?>
		        <small class="text-danger d-block"><?=$errors['amount']?></small>
		      <?php endif?>
		      <?php if((!empty($errors['denos_json']) || !empty($errors['denos_json_mismatch'])) && (($errors['denos_json'] ?? $errors['denos_json_mismatch']) !== $__request_swal_error)):?>
		        <small class="text-danger d-block"><?=$errors['denos_json'] ?? $errors['denos_json_mismatch']?></small>
		      <?php endif?>
		    </div>

		    <div class="col-md-12">
		    	<div class="alert alert-info d-flex align-items-center gap-2">
		    		<i class="fa-solid fa-vault"></i>
		    		<span>Cashdrawer will be detected automatically when the request is saved.</span>
		    	</div>
		    </div>

			<div class="col-md-12  assigned">
			  <label for="assigned" class="form-label">Assigned to <small>(who is the request going to?):</small></label>
			  <select class="form-select assigned" name="assigned">
				  <option value="">---Select---</option>
				  <?php if(!empty($users)):?>
				  	<?php foreach ($users as $user): ?>				  		
				  		<option <?=old_select('assigned',$user->id ?? '')?> value="<?=$user->id ?? ' ' ?>"><?=$user->first_name . ' '. $user->last_name ?? ''?> (<?=$user->roles[0] ?? ''?>)</option>
				  	<?php endforeach;?>
				  	<?php endif;?>
			  </select>
			  	<?php if(!empty($errors['assigned']) && (string)$errors['assigned'] !== $__request_swal_error): ?>
				  <small class="text-danger"><?=$errors['assigned']?></small>
				<?php endif ?>
			</div>
				
			<div class="col-md-12">
			  <label for="description" class="form-label">Description</label>
				<textarea rows="2" id="description" class="form-control" name="description"  placeholder="Write a brief description for this request: if you are requesting for sims, indicate the quantity you want here. "><?=old_value('description')?></textarea>
			</div>			
				<?php if(!empty($errors['description']) && (string)$errors['description'] !== $__request_swal_error):?>
				  <small class="text-danger"><?=$errors['description']?></small>
				<?php endif?>

			<div class="col-md-12  transaction_status">
			  <label for="transaction_status" class="form-label">Status</label>
			  <select class="form-select transaction_status" name="transaction_status">
				  <option value="">---Select---</option>
				  <option <?=old_select('transaction_status','Draft')?> value="Draft">Draft</option>
				  <option <?=old_select('transaction_status','Pending')?> value="Pending">Pending</option>
			  </select>				
				<?php if(!empty($errors['transaction_status']) && (string)$errors['transaction_status'] !== $__request_swal_error): ?>
				  <small class="text-danger"><?=$errors['transaction_status']?></small>
				<?php endif ?>
			</div>
	 
			<div class="d-flex justify-content-between mb-5" style="margin-top:10px;">
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

	<div class="modal fade" id="requestDenoModal" tabindex="-1" aria-hidden="true">
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
			  <tbody id="requestDenoTableBody"></tbody>
			  <tfoot>
				<tr>
				  <th>Total</th>
				  <th><input type="text" class="form-control form-control-sm" id="requestDenoCount" readonly /></th>
				  <th><input type="text" class="form-control form-control-sm" id="requestDenoTotal" readonly /></th>
				</tr>
			  </tfoot>
			</table>
		  </div>
		  <div class="modal-footer">
			<button type="button" class="btn btn-outline-secondary" id="requestDenoReset">Reset</button>
			<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
			<button type="button" class="btn btn-primary" id="requestDenoApply">Apply</button>
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
	function mrGetForm(){
		return document.querySelector('form');
	}

	let mrPrecheckRunning = false;

	function mrShowClientError(msg){
		const message = String(msg || 'Validation failed');
		if (typeof window.basicAdminSwalAlert === 'function') {
			window.basicAdminSwalAlert('Unable to Submit Request', message, 'error');
			return;
		}
		if (window.Swal && typeof window.Swal.fire === 'function') {
			window.Swal.fire({
				icon: 'error',
				title: 'Unable to Submit Request',
				text: message
			});
			return;
		}
		alert(message);
	}

	async function mrPrecheck(){
		const form = mrGetForm();
		if(!form) return {ok:true};
		const network = String((form.querySelector('[name="network"]')?.value) || '').toUpperCase();
		const amountValue = Number((form.querySelector('[name="amount"]')?.value) || 0);
		if(network === '' || !Number.isFinite(amountValue) || amountValue <= 0) return {ok:true};

		const fd = new FormData(form);
		try{
			const res = await fetch('<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/request_precheck', { method: 'POST', body: fd, credentials: 'same-origin' });
			const data = await res.json();
			if(!data || !data.success){
				return {ok:false, message: (data && data.message) ? data.message : 'Validation failed'};
			}
			return {ok:true};
		}catch(e){
			return {ok:false, message: 'Failed to validate before submit. Please try again.'};
		}
	}

	function mrSetSubmittingState(form, isBusy){
		if(!form) return;
		if (!isBusy && typeof window.basicAdminReleaseSubmitLock === 'function') {
			window.basicAdminReleaseSubmitLock(form);
		} else if (form.dataset) {
			if (isBusy) {
				form.dataset.boSubmitting = '1';
			} else {
				delete form.dataset.boSubmitting;
			}
		}
		const submitButtons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
		submitButtons.forEach(function(btn){
			btn.disabled = !!isBusy;
			if (isBusy) {
				btn.setAttribute('aria-disabled', 'true');
			} else {
				btn.removeAttribute('aria-disabled');
			}
			if(btn.tagName === 'BUTTON'){
				if(isBusy){
					if(!btn.dataset.originalText){
						btn.dataset.originalText = btn.innerHTML;
					}
					btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Checking...';
				}else if(btn.dataset.originalText){
					btn.innerHTML = btn.dataset.originalText;
				}
			}
		});
	}

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
			{ value: "", text: "---Select---" },
			{ value: "CALBANK", text: "CALBANK" },
		],
	};
	const requestType = document.getElementById('request_type');
	const networkSelect = document.getElementById('network');
	const existingRequestType = "<?=addslashes(old_value('request_type'))?>";
	const backofficeRequestAssignments = <?=json_encode($backofficeCurrentUserAssignments ?? [], JSON_UNESCAPED_SLASHES)?>;
	const amountInput = document.getElementById('amount');
	const cashAmountGroup = document.getElementById('cashAmountGroup');
	const amountHint = document.getElementById('amountHint');
	const denosHiddenField = document.getElementById('denos_json');

	function normalizeNetworkKey(value) {
		let v = String(value || '').trim().toUpperCase();
		// Normalize various dash characters to a standard hyphen
		v = v.replace(/[–—−]/g, '-');
		// Normalize separators to hyphens and collapse duplicates
		v = v.replace(/[^A-Z0-9]+/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
		if (v === 'E-CASH' || v === 'ECASH') return 'E-CASH';
		if (v === 'BANK') return 'BANK';
		if (v === 'EVD') return 'EVD';
		if (v === 'CASH') return 'CASH';
		return v;
	}

	function boEscapeHtml(value) {
		return String(value ?? '').replace(/[&<>"']/g, function(char){
			return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char];
		});
	}

	function boNormalizeDetailKey(value) {
		let v = String(value || '').trim().toUpperCase();
		v = v.replace(/[â€“â€”âˆ’]/g, '-');
		v = v.replace(/[^A-Z0-9]+/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
		if (v === 'GMONEY' || v === 'G-MONEY' || v === 'G-MONEY') return 'G-MONEY';
		if (v === 'GHANA-PAY') return 'GHANAPAY';
		if (v === 'ECASH') return 'E-CASH';
		return v;
	}

	function boPickAssignment(rows, option, type) {
		rows = Array.isArray(rows) ? rows : [];
		const opt = boNormalizeDetailKey(option);
		const typeKey = boNormalizeDetailKey(type);
		if (opt === 'BANK') {
			return rows.find(row => String(row.sim_type || '').toLowerCase() === 'bank' && String(row.account_number || '').trim() !== '')
				|| rows.find(row => String(row.account_number || '').trim() !== '')
				|| null;
		}

		const matches = rows.filter(row => boNormalizeDetailKey(row.network || '') === typeKey);
		if (opt === 'EVD') {
			return matches.find(row => String(row.sim_type || '').toLowerCase() === 'evd' && String(row.phone_number || '').trim() !== '')
				|| matches.find(row => String(row.phone_number || '').trim() !== '')
				|| matches[0]
				|| null;
		}

		return matches.find(row => !['evd', 'bank'].includes(String(row.sim_type || '').toLowerCase()))
			|| matches[0]
			|| null;
	}

	function boDetailSpec(option, type) {
		const opt = boNormalizeDetailKey(option);
		const typeKey = boNormalizeDetailKey(type);
		if (opt === 'BANK') return [{key:'account_number', label:'CalBank account number'}];
		if (opt === 'EVD') return [{key:'phone_number', label:'Phone number'}];
		if (opt !== 'E-CASH') return [];
		if (['MTN', 'GHANAPAY', 'ZEEPAY'].includes(typeKey)) return [{key:'phone_number', label:'Phone number'}];
		if (typeKey === 'TELECEL') return [{key:'till_number', label:'Till number'}, {key:'shortcode', label:'Shortcode'}];
		if (typeKey === 'AT') return [{key:'shortcode', label:'Shortcode'}, {key:'phone_number', label:'Phone number'}];
		if (typeKey === 'G-MONEY') return [{key:'shortcode', label:'Shortcode'}];
		return [];
	}

	function renderBackofficeRequestDetails() {
		const panel = document.getElementById('requestAssignedSimDetails');
		if (!panel || !networkSelect || !requestType) return;
		const option = boNormalizeDetailKey(networkSelect.value);
		const type = boNormalizeDetailKey(requestType.value);
		const spec = boDetailSpec(option, type);
		if (!option || option === 'CASH' || !type || spec.length === 0) {
			panel.className = 'alert alert-secondary d-none mb-2';
			panel.innerHTML = '';
			return;
		}

		const row = boPickAssignment(backofficeRequestAssignments, option, type);
		if (!row) {
			panel.className = 'alert alert-warning mb-2';
			panel.innerHTML = '<strong>Assigned details:</strong> No saved details found for your ' + boEscapeHtml(type) + ' ' + boEscapeHtml(option) + ' assignment.';
			return;
		}

		const items = spec.map(item => {
			const value = String(row[item.key] || '').trim();
			return '<div><strong>' + boEscapeHtml(item.label) + ':</strong> ' + (value ? boEscapeHtml(value) : '<span class="text-warning">Not saved</span>') + '</div>';
		}).join('');
		panel.className = 'alert alert-info mb-2';
		panel.innerHTML = '<div class="fw-semibold mb-1">Your assigned details</div>' + items;
	}

	function updateRequestOptions() {
		if (!requestType || !networkSelect) return;
		const networkSelectValue = normalizeNetworkKey(networkSelect.value);
		requestType.innerHTML = ""; // Clear current options
		requestType.appendChild(new Option('---Select---', ''));

		const list = networkOptions[networkSelectValue];
		if (!list || !Array.isArray(list) || list.length === 0) {
			// Keep UI non-empty even if the network value does not match the map
			requestType.appendChild(new Option('No request types available', ''));
			requestType.selectedIndex = 0;
			return;
		}

		// Populate options based on selected transaction type
		if (networkOptions[networkSelectValue]) {
			list.forEach(option => {
				if (option && String(option.value || '') === '') {
					return;
				}
				const opt = document.createElement('option');
				opt.value = option.value;
				opt.textContent = option.text;
				if (existingRequestType && String(option.value) === String(existingRequestType)) {
					opt.selected = true;
				}
				requestType.appendChild(opt);
			});
		}

		// Ensure something visible is selected
		if (!requestType.value && requestType.options.length > 0) {
			requestType.selectedIndex = 0;
		} 
		renderBackofficeRequestDetails();
	}

	const ghanaDenoms = [200,100,50,20,10,5,2,1,0.5,0.2,0.1];
	const cfaDenoms = [10000,5000,2000,1000,500,200,100,50,20,10,5,2,1];
	function buildDenoRows(currency){
		const body = document.getElementById('requestDenoTableBody');
		if(!body) return;
		const fragment = document.createDocumentFragment();
		const denoms = currency === 'CFA' ? cfaDenoms : ghanaDenoms;
		body.innerHTML = '';
		denoms.forEach(value => {
			const formattedValue = Number.isInteger(value) ? value : value.toFixed(2);
			const tr = document.createElement('tr');
			tr.dataset.denomination = value;
			tr.dataset.currency = currency;
			tr.innerHTML = `
				<td>${currency === 'CFA' ? 'CFA' : 'GH\u00A2'}${formattedValue}</td>
				<td><input type="number" min="0" class="form-control form-control-sm deno-count" /></td>
				<td><input type="text" class="form-control form-control-sm deno-volume" readonly /></td>
			`;
			fragment.appendChild(tr);
		});
		body.appendChild(fragment);
	}

	function toggleAmountMode(){
		const isCash = networkSelect.value === 'CASH';
		if(isCash){
			cashAmountGroup.setAttribute('data-bs-toggle','modal');
			cashAmountGroup.setAttribute('data-bs-target','#requestDenoModal');
			cashAmountGroup.style.cursor = 'pointer';
			amountInput.readOnly = true;
			amountInput.value = amountInput.value || '';
			const reqType = requestType.value;
			if(reqType === 'CFA'){
				amountHint.textContent = 'Use the CFA calculator for cash requests.';
				buildDenoRows('CFA');
			}else{
				amountHint.textContent = 'Use the GHS calculator for cash requests.';
				buildDenoRows('GHS');
			}
		} else {
			cashAmountGroup.removeAttribute('data-bs-toggle');
			cashAmountGroup.removeAttribute('data-bs-target');
			cashAmountGroup.style.cursor = 'default';
			amountInput.readOnly = false;
			amountInput.placeholder = 'Enter amount';
			amountHint.textContent = 'Enter the amount manually for non-cash requests.';
			denosHiddenField.value = '';
		}
	}

	document.addEventListener("DOMContentLoaded", function() {
		const serverErrorEl = document.getElementById('mr_server_error');
		const serverErrorMessage = serverErrorEl ? String(serverErrorEl.value || '').trim() : '';
		if (serverErrorMessage) {
			setTimeout(function(){
				mrShowClientError(serverErrorMessage);
			}, 50);
		}

		updateRequestOptions();
		toggleAmountMode();
		renderBackofficeRequestDetails();
		const form = mrGetForm();
		if(form){
			form.addEventListener('submit', async function(e){
				if (form.dataset.mrBypassSubmit === '1') {
					return true;
				}
				e.preventDefault();
				if (mrPrecheckRunning) {
					return false;
				}
				mrPrecheckRunning = true;
				mrSetSubmittingState(form, true);
				const result = await mrPrecheck();
				if(!result.ok){
					mrPrecheckRunning = false;
					form.dataset.mrBypassSubmit = '0';
					mrSetSubmittingState(form, false);
					mrShowClientError(result.message);
					return false;
				}
				form.dataset.mrBypassSubmit = '1';
				if (typeof form.submit === 'function') {
					HTMLFormElement.prototype.submit.call(form);
				}
				return true;
			});
		}
	});

	// Event listeners for real-time updates
	networkSelect.addEventListener("change", () => {
		updateRequestOptions();
		toggleAmountMode();
		renderBackofficeRequestDetails();
	});
	requestType.addEventListener('change', () => {
		if(networkSelect.value === 'CASH'){
			toggleAmountMode();
		}
		renderBackofficeRequestDetails();
	});

	(function(){
		const modalEl = document.getElementById('requestDenoModal');
		if(!modalEl) return;
		const denosInput = document.getElementById('denos_json');
		const amountInput = document.getElementById('amount');
		const countField = document.getElementById('requestDenoCount');
		const totalField = document.getElementById('requestDenoTotal');
		const resetBtn = document.getElementById('requestDenoReset');
		const applyBtn = document.getElementById('requestDenoApply');
		const getCountInputs = () => modalEl.querySelectorAll('.deno-count');

		function recalc(){
			let totalCount = 0;
			let totalVolume = 0;
			const snapshot = [];
			getCountInputs().forEach(input => {
				const row = input.closest('tr');
				const denom = parseFloat(row.dataset.denomination);
				const currency = row.dataset.currency || 'GHS';
				const qty = parseFloat(input.value) || 0;
				const volume = denom * qty;
				row.querySelector('.deno-volume').value = qty ? volume.toFixed(2) : '';
				totalCount += qty;
				totalVolume += volume;
				if(qty > 0){
					snapshot.push({currency:currency, denomination:denom, count:qty, volume:volume});
				}
			});
			countField.value = totalCount ? totalCount : '';
			totalField.value = totalVolume ? totalVolume.toFixed(2) : '';
			return {snapshot, totalVolume};
		}

		function resetCounts(){
			getCountInputs().forEach(input => {
				input.value = '';
				const row = input.closest('tr');
				row.querySelector('.deno-volume').value = '';
			});
			recalc();
		}

		function restoreFromHidden(){
			try {
				const payload = JSON.parse(denosInput.value || '[]');
				if(Array.isArray(payload)){
					payload.forEach(entry => {
						getCountInputs().forEach(input => {
							const row = input.closest('tr');
							const denom = parseFloat(row.dataset.denomination);
							if(Number(entry.denomination) === denom){
								input.value = entry.count;
							}
						});
					});
				}
			} catch (e) {}
			recalc();
			if(amountInput && totalField.value){
				amountInput.value = parseFloat(totalField.value).toFixed(2);
			}
		}

		modalEl.addEventListener('shown.bs.modal', () => {
			restoreFromHidden();
			getCountInputs().forEach(input => input.addEventListener('input', recalc));
		});
		resetBtn.addEventListener('click', () => {
			denosInput.value = '';
			resetCounts();
		});
		applyBtn.addEventListener('click', () => {
			if(networkSelect.value !== 'CASH'){
				const bsModal = bootstrap.Modal.getInstance(modalEl);
				if(bsModal) bsModal.hide();
				return;
			}
			const {snapshot, totalVolume} = recalc();
			denosInput.value = JSON.stringify(snapshot);
			if(amountInput){
				amountInput.value = totalVolume ? totalVolume.toFixed(2) : '';
			}
			const bsModal = bootstrap.Modal.getInstance(modalEl);
			if(bsModal) bsModal.hide();
		});
	})();
</script>

