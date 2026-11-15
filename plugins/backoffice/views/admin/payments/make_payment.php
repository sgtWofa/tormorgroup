<?php if(user_can('add_payment')): ?>
	<?php
		$__old_post = get_value('old_post');
		$__mp_restore = (int)(get_value('mp_restore') ?? 0);
		if ($__mp_restore) {
			set_value('mp_restore', 0);
		}
		$__errors_session = get_value('errors');
		$__has_errors = is_array($__errors_session) && !empty($__errors_session);
		$__mp_clear_draft = (int)(get_value('mp_clear_draft') ?? 0);
		if ($__mp_clear_draft) {
			set_value('mp_clear_draft', 0);
		}
		$__is_fresh_visit = empty($_POST) && !$__has_errors && !$__mp_restore;
		if ($__is_fresh_visit) {
			set_value('old_post', []);
			set_value('errors', []);
			set_value('mp_restore', 0);
			$__old_post = [];
			$__mp_clear_draft = 1;
		}
		$__should_restore_draft = (!$__mp_clear_draft) && ($__mp_restore || $__has_errors || (is_array($__old_post) && !empty($__old_post)) || !empty($_POST));
		if (is_array($__old_post) && !empty($__old_post)) {
			$_POST = $__old_post;
			set_value('old_post', []);
		}
		$__payment_swal_error = '';
		if (is_array($__errors_session) && !empty($__errors_session)) {
			foreach (['network', 'payment_type', 'amount', 'denos_json', 'denos_json_mismatch'] as $__errorKey) {
				$__candidateError = trim((string)($__errors_session[$__errorKey] ?? ''));
				if ($__candidateError !== '') {
					$__payment_swal_error = $__candidateError;
					break;
				}
			}
		}
		$__late_access_rows = is_array($lateReconciliationAccessNotifications ?? null) ? $lateReconciliationAccessNotifications : [];
		$__recon_payment_context = is_array($reconciliationPaymentContext ?? null) ? $reconciliationPaymentContext : null;
		$__recon_payment_items = [];
		$__recon_payment_total = 0.0;
		if (!empty($__recon_payment_context['channels']) && is_array($__recon_payment_context['channels'])) {
			foreach ($__recon_payment_context['channels'] as $__ctx_row) {
				if (is_object($__ctx_row)) {
					$__ctx_row = get_object_vars($__ctx_row);
				}
				if (!is_array($__ctx_row)) {
					continue;
				}
				$__ctx_channel = strtoupper(trim((string)($__ctx_row['channel'] ?? '')));
				$__ctx_amount = round((float)($__ctx_row['balance'] ?? $__ctx_row['amount'] ?? 0), 2);
				if ($__ctx_channel !== '' && $__ctx_amount > 0) {
					$__recon_payment_items[] = ['channel' => $__ctx_channel, 'amount' => $__ctx_amount];
					$__recon_payment_total += $__ctx_amount;
				}
			}
		}
		if (!empty($__recon_payment_items) && empty($_POST) && !$__has_errors && !$__mp_restore) {
			$_POST['network'] = 'E-CASH';
			$_POST['payment_type'] = 'BULK';
			$_POST['bulk_payment_items'] = json_encode($__recon_payment_items);
			$_POST['amount'] = number_format($__recon_payment_total, 2, '.', '');
			$_POST['description'] = 'Outstanding reconciliation e-cash payment';
			$_POST['transaction_status'] = 'Pending';
			$__mp_clear_draft = 0;
			$__should_restore_draft = true;
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
	<form method="post" enctype="multipart/form-data" autocomplete="off">		
		<div class="row col-sm-12 col-md-8 col-lg-6 mx-auto shadow p-2 rounded">
			
			<?=csrf()?>
			<?php if(!empty($__recon_payment_items)): ?>
				<input type="hidden" name="recon_late_access_id" value="<?=esc((string)($__recon_payment_context['late_access_id'] ?? 0))?>" />
			<?php endif; ?>
			<input type="hidden" id="mp_restore_draft" value="<?=($__should_restore_draft ? '1' : '0')?>" />
			<input type="hidden" id="mp_clear_draft" value="<?=($__mp_clear_draft ? '1' : '0')?>" />
			<input type="hidden" id="mp_force_server_state" value="<?=(!empty($__recon_payment_items) ? '1' : '0')?>" />
			<input type="hidden" id="mp_server_error" value="<?=htmlspecialchars($__payment_swal_error, ENT_QUOTES)?>" />
			<?php if($__should_restore_draft): ?>
				<?php
					$__mp_state = [
						'network' => (string)old_value('network'),
						'payment_type' => (string)old_value('payment_type'),
						'bulk_payment_items' => (string)old_value('bulk_payment_items'),
						'amount' => (string)old_value('amount'),
						'denos_json' => (string)old_value('denos_json'),
						'description' => (string)old_value('description'),
						'pay_to' => (string)old_value('pay_to'),
						'transaction_status' => (string)old_value('transaction_status'),
					];
				?>
				<input type="hidden" id="mp_server_state" value="<?=htmlspecialchars((string)json_encode($__mp_state), ENT_QUOTES)?>" />
			<?php endif; ?>

			<h4 class="text-center">
				<span><img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:60px;max-height: 60px;object-fit: cover;"></span>
				Make Payment
			</h4>

			<?php if(!empty($__late_access_rows)): ?>
				<?php foreach($__late_access_rows as $__late_access): ?>
					<div class="col-md-12">
						<div class="alert alert-info py-2 mb-2" data-attention-sound="1" data-attention-key="make-payment-late-reconciliation-<?=esc((string)($__late_access->id ?? 0))?>">
							<div class="fw-semibold">Late reconciliation access is still active</div>
							<div class="small">After settling outstanding payments, return to reconcile <?=esc(get_date($__late_access->cashdrawer_date ?? $__late_access->drawer_date ?? ''))?>.</div>
							<a class="btn btn-sm btn-primary mt-2" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/reconciliation?late_access=<?=esc((string)($__late_access->id ?? 0))?>">
								Open past reconciliation
							</a>
						</div>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>

			<?php if(!empty($__recon_payment_items)): ?>
				<div class="col-md-12">
					<div class="alert alert-warning py-2 mb-2">
						<div class="fw-semibold">Outstanding e-cash channels from reconciliation</div>
						<div class="small mb-2">The e-cash bulk payment form has been prefilled with these outstanding balances.</div>
						<div class="table-responsive">
							<table class="table table-sm align-middle mb-0">
								<thead>
									<tr>
										<th>Channel</th>
										<th class="text-end">Amount</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach($__recon_payment_items as $__item): ?>
										<tr>
											<td><?=esc($__item['channel'])?></td>
											<td class="text-end">GHS <?=number_format((float)$__item['amount'], 2)?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			<?php endif; ?>

			<div class=" col-md-12">
				<label for="network" class="form-label">Payment Option</label>
				<select class="form-select" name="network" id="network" onchange="updatePaymentOptions()">	
				  <option value="">---Select---</option>
				  <!-- Options will be populated dynamically based on transaction type -->
				  <option <?=old_select('network','BANK')?> value="BANK">BANK</option>
				  <option <?=old_select('network','CASH')?> value="CASH">CASH</option>
				  <option <?=old_select('network','E-CASH')?> value="E-CASH">E-CASH</option>
				  <option <?=old_select('network','EVD')?> value="EVD">EVD</option>
				</select>
				<?php if(!empty($errors['network']) && (string)$errors['network'] !== $__payment_swal_error): ?>
				  <small class="text-danger"><?=$errors['network']?></small>
				<?php endif ?>
			</div>

			<div class=" col-md-12">
				<label for="payment_type" class="form-label">Payment Type</label>
				<select class="form-select payment_type" id="payment_type" name="payment_type">
					<!-- Options will be displayed dynamically -->
				 </select>
				<?php if(!empty($errors['payment_type']) && (string)$errors['payment_type'] !== $__payment_swal_error): ?>
				  <small class="text-danger"><?=$errors['payment_type']?></small>
				<?php endif ?>
			</div>

			<div class="col-md-12 d-none" id="bulkPaymentCard">
				<input type="hidden" name="bulk_payment_items" id="bulk_payment_items" value="<?=htmlspecialchars((string)old_value('bulk_payment_items'), ENT_QUOTES)?>" />
				<div class="border rounded p-2 mb-2">
					<div class="d-flex justify-content-between align-items-center mb-2">
						<div class="fw-semibold">Bulk payment breakdown</div>
						<div class="badge bg-primary-subtle text-primary">Total: GHS <span id="bulkPaymentTotal">0.00</span></div>
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
					<small class="text-muted">The total amount is calculated from the payment types above.</small>
				</div>
			</div>

			<!-- <div class=" col-md-12">
			  <div class="alert alert-info d-flex align-items-center gap-2">
				<i class="fa-solid fa-vault"></i>
				<span>Cashdrawer will be detected automatically on save.</span>
			  </div>
			</div> -->

			<div class="col-md-12">
			  <label for="amount" class="form-label">Amount</label>
			  <div class="input-group" id="paymentCashAmountGroup" data-bs-toggle="modal" data-bs-target="#paymentDenoModal" style="cursor:pointer;">
				<span class="input-group-text"><i class="fa-solid fa-calculator text-warning"></i></span>
			  	<input name="amount" value="<?=old_value('amount')?>" type="number" step="0.01" class="form-control" id="amount" placeholder="Use calculator" readonly>
			  </div>
			  <small class="text-muted" id="amountHint">Use the calculator for cash payments. Other payment types allow manual amounts.</small>
			  <input type="hidden" id="denos_json" name="denos_json" value="<?=htmlspecialchars((string)old_value('denos_json'), ENT_QUOTES)?>" />
				<?php if(!empty($errors['amount']) && (string)$errors['amount'] !== $__payment_swal_error): ?>
				  <small class="text-danger"><?=$errors['amount']?></small>
				<?php endif ?>
				<?php if((!empty($errors['denos_json']) || !empty($errors['denos_json_mismatch'])) && (($errors['denos_json'] ?? $errors['denos_json_mismatch']) !== $__payment_swal_error)): ?>
				  <small class="text-danger d-block"><?=$errors['denos_json'] ?? $errors['denos_json_mismatch']?></small>
				<?php endif ?>
			</div>

			<div class=" col-md-12">
			  <label for="description" class="form-label">Description</label>
				<textarea rows="2" id="description" class="form-control" name="description"  placeholder="Write a brief description for this transaction"><?=old_value('description')?></textarea>			
			</div>
				<?php if(!empty($errors['description'])):?>
				  <small class="text-danger"><?=$errors['description']?></small>
				<?php endif?>

			<div class="col-md-12  assigned">
			  <label for="pay_to" class="form-label">Assigned to <small>(who is the payment going to?):</small></label>
			  <select class="form-select pay_to" name="pay_to" required>
				  <option value="">---Select---</option>
				  <?php if(!empty($users)):?>
				  	<?php foreach ($users as $user): ?>				  		
				  		<option <?=old_select('pay_to',$user->id ?? '')?> value="<?=$user->id ?? ' ' ?>"><?=$user->first_name . ' '. $user->last_name ?? ''?> (<?=$user->roles[0] ?? ''?>)</option>
				  	<?php endforeach;?>
				  	<?php endif;?>
			  </select>
			  	<?php if(!empty($errors['pay_to'])): ?>
				  <small class="text-danger"><?=$errors['pay_to']?></small>
				<?php endif ?>
			</div>

			<div class="col-md-12">
				<div id="paymentAssignedSimDetails" class="alert alert-secondary d-none mb-2"></div>
			</div>

			<div class=" col-md-12 transaction_status">
			  <label for="transaction_status" class="form-label">Status</label>
			  <select class="form-select transaction_status" name="transaction_status">
				  <option value="">---Select---</option>
				  <option <?=old_select('transaction_status','Draft')?> value="Draft">Draft</option>
				  <option <?=old_select('transaction_status','Pending')?> value="Pending">Pending</option>
			  </select>
				
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
				<button class="btn btn-danger" type="submit">
					<i class="fa-solid fa-save"></i> Save
				</button>
			</div>
		</div>
	</form>

	<div class="modal fade" id="paymentDenoModal" tabindex="-1" aria-hidden="true">
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
				  <th><input type="text" class="form-control form-control-sm" id="paymentDenoCount" readonly /></th>
				  <th><input type="text" class="form-control form-control-sm" id="paymentDenoTotal" readonly /></th>
				</tr>
			  </tfoot>
			</table>
		  </div>
		  <div class="modal-footer">
			<button type="button" class="btn btn-outline-secondary" id="paymentDenoReset">Reset</button>
			<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
			<button type="button" class="btn btn-primary" id="paymentDenoApply">Apply</button>
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
	const MAKE_PAYMENT_STATE_KEY = 'backoffice_make_payment_state_v1';
	function mpGetForm(){
		return document.querySelector('form');
	}
	function mpSerialize(){
		const form = mpGetForm();
		if(!form) return null;
		const get = (name) => {
			const el = form.querySelector(`[name="${name}"]`);
			return el ? String(el.value ?? '') : '';
		};
		return {
			network: get('network'),
			payment_type: get('payment_type'),
			bulk_payment_items: get('bulk_payment_items'),
			amount: get('amount'),
			denos_json: get('denos_json'),
			description: get('description'),
			pay_to: get('pay_to'),
			transaction_status: get('transaction_status'),
			ts: Date.now(),
		};
	}
	function mpPersist(){
		try{
			const state = mpSerialize();
			if(!state) return;
			sessionStorage.setItem(MAKE_PAYMENT_STATE_KEY, JSON.stringify(state));
		}catch(e){}
	}
	function mpRestoreIfBlank(){
		const form = mpGetForm();
		if(!form) return;
		const clearFlag = document.getElementById('mp_clear_draft');
		if (clearFlag && String(clearFlag.value || '') === '1') {
			try{ sessionStorage.removeItem(MAKE_PAYMENT_STATE_KEY); }catch(e){}
			try{
				const clear = (name) => {
					const el = form.querySelector(`[name="${name}"]`);
					if(!el) return;
					el.value = '';
				};
				clear('network');
				clear('payment_type');
				clear('bulk_payment_items');
				clear('amount');
				clear('denos_json');
				clear('description');
				clear('pay_to');
				clear('transaction_status');
				existingPaymentType = '';
			}catch(e){}
			return;
		}

		const restoreFlag = document.getElementById('mp_restore_draft');
		let allowRestore = restoreFlag && String(restoreFlag.value || '') === '1';
		if (!allowRestore) {
			// If the server rendered any validation errors, we must restore and must NOT clear the draft.
			const hasVisibleErrors = !!(
				document.querySelector('.alert.alert-danger') ||
				Array.from(document.querySelectorAll('.text-danger')).some(el => String(el.textContent || '').trim() !== '')
			);
			if (hasVisibleErrors) {
				allowRestore = true;
			}
		}
		if (!allowRestore) {
			try{ sessionStorage.removeItem(MAKE_PAYMENT_STATE_KEY); }catch(e){}
			return;
		}

		let stateRaw = '';
		const serverEl = document.getElementById('mp_server_state');
		const forceServerEl = document.getElementById('mp_force_server_state');
		const forceServerState = forceServerEl && String(forceServerEl.value || '') === '1';
		if (forceServerState && serverEl && serverEl.value) {
			stateRaw = String(serverEl.value);
		}
		if(!stateRaw){
			try{ stateRaw = sessionStorage.getItem(MAKE_PAYMENT_STATE_KEY) || ''; }catch(e){}
			if(!stateRaw && serverEl && serverEl.value){
				stateRaw = String(serverEl.value);
			}
		}

		if(!stateRaw) return;
		let state = null;
		try{ state = JSON.parse(stateRaw); }catch(e){ state=null; }
		if(!state || typeof state !== 'object') return;
		if(state.ts && (Date.now() - Number(state.ts)) > (30 * 60 * 1000)){
			try{ sessionStorage.removeItem(MAKE_PAYMENT_STATE_KEY); }catch(e){}
			return;
		}

		const isBlank = (name) => {
			const el = form.querySelector(`[name="${name}"]`);
			if(!el) return true;
			return String(el.value || '').trim() === '';
		};

		// Restore field-by-field only when the target field is blank (prevents overwriting intentional edits)

		const set = (name, value) => {
			const el = form.querySelector(`[name="${name}"]`);
			if(!el) return;
			el.value = value ?? '';
		};

		if (isBlank('network')) set('network', state.network || '');
		if (isBlank('payment_type')) set('payment_type', state.payment_type || '');
		if (isBlank('bulk_payment_items')) set('bulk_payment_items', state.bulk_payment_items || '');
		if (isBlank('amount')) set('amount', state.amount || '');
		if (isBlank('denos_json')) set('denos_json', state.denos_json || '');
		if (isBlank('description')) set('description', state.description || '');
		if (isBlank('pay_to')) set('pay_to', state.pay_to || '');
		if (isBlank('transaction_status')) set('transaction_status', state.transaction_status || '');
		if (state.payment_type) {
			existingPaymentType = String(state.payment_type || '');
		}
		mpPersist();
	}

	function mpShowClientError(msg){
		const message = String(msg || 'Validation failed');
		if (typeof window.basicAdminSwalAlert === 'function') {
			window.basicAdminSwalAlert('Unable to Submit Payment', message, 'error');
			return;
		}
		if (window.Swal && typeof window.Swal.fire === 'function') {
			window.Swal.fire({
				icon: 'error',
				title: 'Unable to Submit Payment',
				text: message
			});
			return;
		}
		alert(message);
	}

	function mpSetSubmitting(isSubmitting){
		const form = mpGetForm();
		if(!form) return;
		if (!isSubmitting && typeof window.basicAdminReleaseSubmitLock === 'function') {
			window.basicAdminReleaseSubmitLock(form);
		} else if (form.dataset) {
			if (isSubmitting) {
				form.dataset.boSubmitting = '1';
			} else {
				delete form.dataset.boSubmitting;
			}
		}
		form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(btn => {
			btn.disabled = !!isSubmitting;
			if (isSubmitting) {
				btn.setAttribute('aria-disabled', 'true');
			} else {
				btn.removeAttribute('aria-disabled');
			}
		});
	}

	async function mpPrecheck(){
		const form = mpGetForm();
		if(!form) return {ok:true};
		if (form.querySelector('[name="recon_bulk_payment"]')) return {ok:true};
		const network = String((form.querySelector('[name="network"]')?.value) || '').toUpperCase();
		if(network === '') return {ok:true};
		const amountValue = Number((form.querySelector('[name="amount"]')?.value) || 0);
		if(!Number.isFinite(amountValue) || amountValue <= 0) return {ok:true};

		// Always persist before precheck so reload restores
		mpPersist();

		const fd = new FormData(form);
		try{
			const res = await fetch('<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/payment_precheck', { method: 'POST', body: fd, credentials: 'same-origin' });
			const data = await res.json();
			if(!data || !data.success){
				return {ok:false, message: (data && data.message) ? data.message : 'Validation failed'};
			}
			return {ok:true};
		}catch(e){
			return {ok:false, message: 'Failed to validate before submit. Please try again.'};
		}
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
	const amountInput = document.getElementById('amount');
	const cashAmountGroup = document.getElementById('paymentCashAmountGroup');
	const amountHint = document.getElementById('amountHint');
	const denosHiddenField = document.getElementById('denos_json');
	const bulkPaymentCard = document.getElementById('bulkPaymentCard');
	const bulkPaymentRows = document.getElementById('bulkPaymentRows');
	const bulkPaymentItemsInput = document.getElementById('bulk_payment_items');
	const bulkPaymentTotal = document.getElementById('bulkPaymentTotal');
	var existingPaymentType = "<?=addslashes((string)old_value('payment_type'))?>";
	const forceServerBulkState = !!(document.getElementById('mp_force_server_state') && String(document.getElementById('mp_force_server_state').value || '') === '1');
	const backofficePaymentAssignmentsMap = <?=json_encode($backofficePaymentAssignmentsMap ?? [], JSON_UNESCAPED_SLASHES)?>;

	function selectedPaymentTypeUpper(){
		return paymentType ? String(paymentType.value || '').trim().toUpperCase() : '';
	}

	function isTelecelManualPayment(){
		return !!(networkSelect && networkSelect.value !== 'CASH' && selectedPaymentTypeUpper().includes('TELECEL'));
	}

	function isBulkPaymentNetwork(){
		const value = networkSelect ? String(networkSelect.value || '').toUpperCase() : '';
		return value === 'E-CASH' || value === 'EVD';
	}

	function currentBulkItemsMap(){
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

	function recalcBulkPaymentTotal(){
		if (!bulkPaymentRows || !bulkPaymentItemsInput) return 0;
		const items = [];
		let total = 0;
		bulkPaymentRows.querySelectorAll('.bulk-payment-amount').forEach(input => {
			const channel = String(input.dataset.channel || '').toUpperCase();
			const amount = Number(input.value || 0);
			if (channel && Number.isFinite(amount) && amount > 0) {
				const rounded = Math.round(amount * 100) / 100;
				items.push({channel, amount: rounded});
				total += rounded;
			}
		});
		total = Math.round(total * 100) / 100;
		bulkPaymentItemsInput.value = JSON.stringify(items);
		if (bulkPaymentTotal) bulkPaymentTotal.textContent = total.toFixed(2);
		if (amountInput && isBulkPaymentNetwork()) amountInput.value = total ? total.toFixed(2) : '';
		return total;
	}

	function renderBulkPaymentRows(){
		if (!bulkPaymentRows || !networkSelect) return;
		const networkValue = String(networkSelect.value || '').toUpperCase();
		const existing = currentBulkItemsMap();
		const affectedChannels = Object.keys(existing).filter(channel => Number(existing[channel] || 0) > 0);
		let options = (networkOptions[networkValue] || []).filter(option => String(option.value || '').trim() !== '');
		if (forceServerBulkState && affectedChannels.length) {
			const affectedSet = new Set(affectedChannels);
			options = options.filter(option => affectedSet.has(String(option.value || '').toUpperCase()));
		}
		bulkPaymentRows.innerHTML = options.map(option => {
			const channel = String(option.value || '').toUpperCase();
			const amount = existing[channel] || '';
			return '<tr><td class="fw-semibold">' + boEscapeHtml(option.text || channel) + '</td><td><input type="number" min="0" step="' + (channel.includes('TELECEL') ? '1' : '0.01') + '" class="form-control form-control-sm bulk-payment-amount" data-channel="' + boEscapeHtml(channel) + '" value="' + (amount ? String(amount) : '') + '"></td></tr>';
		}).join('');
		bulkPaymentRows.querySelectorAll('.bulk-payment-amount').forEach(input => input.addEventListener('input', () => {
			if (String(input.dataset.channel || '').includes('TELECEL')) {
				const value = Number(input.value || 0);
				input.setCustomValidity((!input.value || Math.abs(value - Math.round(value)) < 0.00001) ? '' : 'Telecel payments must use whole-number amounts only.');
			}
			recalcBulkPaymentTotal();
			mpPersist();
		}));
		recalcBulkPaymentTotal();
	}

	function syncPaymentAmountConstraints(){
		if (!amountInput) return;
		if (isBulkPaymentNetwork()) {
			amountInput.step = '0.01';
			amountInput.placeholder = 'Calculated from breakdown';
			if (amountHint) amountHint.textContent = 'Amount is calculated from the bulk payment breakdown.';
			return;
		}
		if (isTelecelManualPayment()) {
			amountInput.step = '1';
			amountInput.placeholder = 'Enter whole number';
			if (amountHint) amountHint.textContent = 'Telecel payments only allow whole-number amounts.';
			return;
		}

		amountInput.step = '0.01';
		if (!(networkSelect && networkSelect.value === 'CASH')) {
			amountInput.placeholder = 'Enter amount';
			if (amountHint) amountHint.textContent = 'Enter amount manually for non-cash payments.';
		}
	}

	function validateTelecelWholeAmount(){
		if (!isTelecelManualPayment() || !amountInput) {
			return {ok:true};
		}
		const raw = String(amountInput.value || '').trim();
		if (!raw) {
			return {ok:true};
		}
		const amountValue = Number(raw);
		if (!Number.isFinite(amountValue) || Math.abs(amountValue - Math.round(amountValue)) > 0.00001) {
			return {ok:false, message:'Telecel payments must use whole-number amounts only.'};
		}
		return {ok:true};
	}

	function togglePaymentAmountMode(){
		const isCash = (networkSelect && networkSelect.value === 'CASH');
		const isBulk = isBulkPaymentNetwork();
		if (bulkPaymentCard) bulkPaymentCard.classList.toggle('d-none', !isBulk);
		if (paymentType) {
			const holder = paymentType.closest('.col-md-12');
			if (holder) holder.classList.toggle('d-none', isBulk);
			if (isBulk) {
				paymentType.innerHTML = '<option value="BULK" selected>BULK</option>';
			}
		}
		if(isCash){
			if (cashAmountGroup) {
				cashAmountGroup.setAttribute('data-bs-toggle','modal');
				cashAmountGroup.setAttribute('data-bs-target','#paymentDenoModal');
				cashAmountGroup.style.cursor = 'pointer';
			}
			if (amountInput) {
				amountInput.readOnly = true;
				amountInput.placeholder = 'Use calculator';
			}
			if (amountHint) amountHint.textContent = 'Use the calculator for cash payments. Other payment types allow manual amounts.';
		} else if (isBulk) {
			if (cashAmountGroup) {
				cashAmountGroup.removeAttribute('data-bs-toggle');
				cashAmountGroup.removeAttribute('data-bs-target');
				cashAmountGroup.style.cursor = 'default';
			}
			if (amountInput) {
				amountInput.readOnly = true;
				amountInput.placeholder = 'Calculated from breakdown';
			}
			if (amountHint) amountHint.textContent = 'Amount is calculated from the bulk payment breakdown.';
			if (denosHiddenField) denosHiddenField.value = '';
			renderBulkPaymentRows();
		} else {
			if (cashAmountGroup) {
				cashAmountGroup.removeAttribute('data-bs-toggle');
				cashAmountGroup.removeAttribute('data-bs-target');
				cashAmountGroup.style.cursor = 'default';
			}
			if (amountInput) {
				amountInput.readOnly = false;
				amountInput.placeholder = 'Enter amount';
			}
			if (amountHint) amountHint.textContent = 'Enter amount manually for non-cash payments.';
			if (denosHiddenField) denosHiddenField.value = '';
			if (bulkPaymentItemsInput) bulkPaymentItemsInput.value = '';
		}
		syncPaymentAmountConstraints();
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
		if (v === 'GMONEY' || v === 'G-MONEY') return 'G-MONEY';
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

	function renderBackofficePaymentDetails() {
		const panel = document.getElementById('paymentAssignedSimDetails');
		const payToSelect = document.querySelector('[name="pay_to"]');
		if (!panel || !networkSelect || !paymentType || !payToSelect) return;
		const option = boNormalizeDetailKey(networkSelect.value);
		const type = boNormalizeDetailKey(paymentType.value);
		const spec = boDetailSpec(option, type);
		if (!option || option === 'CASH' || !type || spec.length === 0) {
			panel.className = 'alert alert-secondary d-none mb-2';
			panel.innerHTML = '';
			return;
		}

		const selectedUserId = String(payToSelect.value || '').trim();
		if (!selectedUserId) {
			panel.className = 'alert alert-secondary mb-2';
			panel.innerHTML = '<strong>Assigned details:</strong> Select the assigned user to display their saved details.';
			return;
		}

		const rows = backofficePaymentAssignmentsMap[selectedUserId] || [];
		const row = boPickAssignment(rows, option, type);
		const selectedUserName = payToSelect.options[payToSelect.selectedIndex] ? payToSelect.options[payToSelect.selectedIndex].textContent : 'selected user';
		if (!row) {
			panel.className = 'alert alert-warning mb-2';
			panel.innerHTML = '<strong>Assigned details:</strong> No saved details found for ' + boEscapeHtml(selectedUserName) + ' on ' + boEscapeHtml(type) + ' ' + boEscapeHtml(option) + '.';
			return;
		}

		const items = spec.map(item => {
			const value = String(row[item.key] || '').trim();
			return '<div><strong>' + boEscapeHtml(item.label) + ':</strong> ' + (value ? boEscapeHtml(value) : '<span class="text-warning">Not saved</span>') + '</div>';
		}).join('');
		panel.className = 'alert alert-info mb-2';
		panel.innerHTML = '<div class="fw-semibold mb-1">Assigned details for ' + boEscapeHtml(selectedUserName) + '</div>' + items;
	}

	function updatePaymentOptions() {
		const networkSelectValue = networkSelect ? networkSelect.value : '';
		if (!paymentType) return;
		paymentType.innerHTML = "";
		if (networkOptions[networkSelectValue]) {
			networkOptions[networkSelectValue].forEach(option => {
				const opt = document.createElement('option');
				opt.value = option.value;
				opt.textContent = option.text;
				const desired = existingPaymentType || (paymentType && paymentType.value ? paymentType.value : '');
				if (desired && option.value === desired) {
					opt.selected = true;
				}
				paymentType.appendChild(opt);
			});
		}
		togglePaymentAmountMode();
		renderBackofficePaymentDetails();
	}

	document.addEventListener('DOMContentLoaded', function(){
		mpRestoreIfBlank();
		const serverErrorEl = document.getElementById('mp_server_error');
		const serverErrorMessage = serverErrorEl ? String(serverErrorEl.value || '').trim() : '';
		if (serverErrorMessage) {
			setTimeout(function(){
				mpShowClientError(serverErrorMessage);
			}, 50);
		}
		updatePaymentOptions();
		togglePaymentAmountMode();
		if (networkSelect) networkSelect.addEventListener('change', updatePaymentOptions);
		if (paymentType) paymentType.addEventListener('change', () => {
			syncPaymentAmountConstraints();
			renderBackofficePaymentDetails();
		});
		if (amountInput) amountInput.addEventListener('input', () => {
			const result = validateTelecelWholeAmount();
			amountInput.setCustomValidity(result.ok ? '' : result.message);
		});
		const payToSelect = document.querySelector('[name="pay_to"]');
		if (payToSelect) payToSelect.addEventListener('change', renderBackofficePaymentDetails);
		renderBackofficePaymentDetails();
		const form = mpGetForm();
		if(form){
			form.addEventListener('input', mpPersist);
			form.addEventListener('change', mpPersist);
			form.addEventListener('submit', async function(e){
				if (form.dataset.mpPrecheckPassed === '1') {
					return true;
				}
				e.preventDefault();
				if (form.dataset.mpPrechecking === '1') {
					return false;
				}
				form.dataset.mpPrechecking = '1';
				mpSetSubmitting(true);
				mpPersist();
				const telecelValidation = validateTelecelWholeAmount();
				if(!telecelValidation.ok){
					form.dataset.mpPrechecking = '0';
					form.dataset.mpPrecheckPassed = '0';
					mpSetSubmitting(false);
					mpShowClientError(telecelValidation.message);
					return false;
				}
				const result = await mpPrecheck();
				if(!result.ok){
					form.dataset.mpPrechecking = '0';
					form.dataset.mpPrecheckPassed = '0';
					mpSetSubmitting(false);
					mpShowClientError(result.message);
					return false;
				}
				form.dataset.mpPrecheckPassed = '1';
				if (typeof form.submit === 'function') {
					form.submit();
				}
				return true;
			});
		}
	});

	(function(){
		const modalEl = document.getElementById('paymentDenoModal');
		if(!modalEl) return;
		const denosInput = document.getElementById('denos_json');
		const amountInput = document.getElementById('amount');
		const countField = document.getElementById('paymentDenoCount');
		const totalField = document.getElementById('paymentDenoTotal');
		const resetBtn = document.getElementById('paymentDenoReset');
		const applyBtn = document.getElementById('paymentDenoApply');
		const countInputs = modalEl.querySelectorAll('.deno-count');
		const denomRows = modalEl.querySelectorAll('tbody tr[data-denomination]');

		function getSelectedCurrency(){
			const isCash = (networkSelect && networkSelect.value === 'CASH');
			if (!isCash) return 'GHS';
			const candidate = (paymentType && paymentType.value) ? String(paymentType.value).toUpperCase() : '';
			return (candidate === 'CFA') ? 'CFA' : 'GHS';
		}

		function applyCurrencyVisibility(){
			const selectedCurrency = getSelectedCurrency();
			denomRows.forEach(row => {
				const rowCurrency = String(row.dataset.currency || 'GHS').toUpperCase();
				row.classList.toggle('d-none', rowCurrency !== selectedCurrency);
			});
		}

		function recalc(){
			const selectedCurrency = getSelectedCurrency();
			let totalCount = 0;
			let totalVolume = 0;
			const snapshot = [];
			countInputs.forEach(input => {
				const row = input.closest('tr');
				const rowCurrency = String(row.dataset.currency || 'GHS').toUpperCase();
				const denom = parseFloat(row.dataset.denomination);
				const qty = parseFloat(input.value) || 0;
				if (rowCurrency !== selectedCurrency) {
					row.querySelector('.deno-volume').value = '';
					return;
				}
				const volume = denom * qty;
				row.querySelector('.deno-volume').value = qty ? volume.toFixed(2) : '';
				totalCount += qty;
				totalVolume += volume;
				if(qty > 0){
					snapshot.push({currency:selectedCurrency, denomination:denom, count:qty, volume:volume});
				}
			});
			if (countField) countField.value = totalCount ? totalCount : '';
			if (totalField) totalField.value = totalVolume ? totalVolume.toFixed(2) : '';
			return {snapshot, totalVolume};
		}

		function resetCounts(){
			countInputs.forEach(input => {
				input.value = '';
				const row = input.closest('tr');
				row.querySelector('.deno-volume').value = '';
			});
			recalc();
		}

		function restoreFromHidden(){
			applyCurrencyVisibility();
			try {
				const payload = JSON.parse((denosInput && denosInput.value) ? denosInput.value : '[]');
				if(Array.isArray(payload)){
					payload.forEach(entry => {
						countInputs.forEach(input => {
							const row = input.closest('tr');
							const rowCurrency = String(row.dataset.currency || 'GHS').toUpperCase();
							const denom = parseFloat(row.dataset.denomination);
							const entryCurrency = String(entry.currency || 'GHS').toUpperCase();
							const entryDenom = parseFloat(entry.denomination || 0) || 0;
							if(entryCurrency === rowCurrency && Math.abs(entryDenom - denom) < 0.0001){
								input.value = String(parseInt(entry.count || 0, 10) || 0);
							}
						});
					});
				}
			} catch (e) {}
			recalc();

			if(amountInput && totalField && totalField.value){
				const cur = getSelectedCurrency();
				amountInput.value = parseFloat(totalField.value).toFixed((cur === 'CFA') ? 0 : 2);
			}
		}

		modalEl.addEventListener('shown.bs.modal', restoreFromHidden);
		countInputs.forEach(input => input.addEventListener('input', recalc));
		if (paymentType) paymentType.addEventListener('change', () => {
			if (denosInput) denosInput.value = '';
			countInputs.forEach(input => {
				input.value = '';
				const row = input.closest('tr');
				row.querySelector('.deno-volume').value = '';
			});
			applyCurrencyVisibility();
			recalc();
		});
		if (resetBtn) resetBtn.addEventListener('click', () => {
			if (denosInput) denosInput.value = '';
			resetCounts();
		});

		if (applyBtn) applyBtn.addEventListener('click', () => {
			const {snapshot, totalVolume} = recalc();
			if (denosInput) denosInput.value = JSON.stringify(snapshot);
			if(amountInput){
				amountInput.value = totalVolume ? totalVolume.toFixed(2) : '';
			}
			const bsModal = bootstrap.Modal.getInstance(modalEl);
			if(bsModal) bsModal.hide();
		});
	})();
</script>
