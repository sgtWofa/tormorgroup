<?php if(user_can('add_payment')): ?>
	<?php $selectedCategory = strtoupper(trim((string)old_value('category'))); ?>
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
		<div class="row g-3 col-sm-12 col-md-11 col-lg-6 mx-auto shadow p-2 rounded">

			<?=csrf()?>

			<h4 class="text-center">
				<span><img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:60px;max-height: 60px;object-fit: cover;"></span>
				Make Payment
			</h4>

			<div class="mb-3 col-md-12">
				<label for="payment_mode" class="form-label">Mode of Payment</label>
				<select class="form-select" id="payment_mode" name="payment_mode">
					<option value="">---Select---</option>
					<option <?=old_select('payment_mode','cash')?> value="cash">Cash</option>
					<option <?=old_select('payment_mode','ecash')?> value="ecash">E-Cash</option>
				</select>
				<?php if(!empty($errors['payment_mode'])): ?>
					<small class="text-danger"><?=$errors['payment_mode']?></small>
				<?php endif ?>
			</div>

			<div class="mb-3 col-md-12 d-none" id="ecashNetworkBox">
				<label for="ecash_network" class="form-label">E-Cash Network</label>
				<select class="form-select" id="ecash_network" name="ecash_network">
					<option value="">---Select---</option>
					<?php foreach(payments_supported_ecash_networks() as $networkValue => $networkLabel): ?>
						<option <?=old_select('ecash_network', $networkValue)?> value="<?=esc($networkValue)?>"><?=esc($networkLabel)?></option>
					<?php endforeach; ?>
				</select>
				<?php if(!empty($errors['ecash_network'])): ?>
					<small class="text-danger"><?=$errors['ecash_network']?></small>
				<?php endif ?>
			</div>

			<div class="mb-3 col-md-12">
				<label for="payment_category" class="form-label">Category</label>
				<select class="form-select" id="payment_category" name="category">
					<option <?=old_select('category','SALARY')?> value="SALARY">SALARY</option>
				</select>
				<?php if(!empty($errors['category'])): ?>
					<small class="text-danger"><?=$errors['category']?></small>
				<?php endif ?>
			</div>

			<div class="mb-3 col-md-12<?=$selectedCategory === 'SALARY' ? ' d-none' : ''?>" id="assignedStaffBox">
				<label for="assigned_staff_user_id" class="form-label">Assign To Staff Cashdrawer</label>
				<select class="form-select" id="assigned_staff_user_id" name="assigned_staff_user_id">
					<option value="">---Select---</option>
					<?php foreach(($cashdrawer_assignable_users ?? []) as $staffRow): ?>
						<?php
							$staffName = trim((string)($staffRow->first_name ?? '') . ' ' . (string)($staffRow->last_name ?? ''));
							$staffRole = trim((string)($staffRow->role_label ?? ''));
						?>
						<option <?=old_select('assigned_staff_user_id', (string)($staffRow->id ?? ''))?> value="<?=esc($staffRow->id ?? '')?>">
							<?=esc($staffName !== '' ? $staffName : ('Staff #' . ($staffRow->id ?? '')))?><?= $staffRole !== '' ? ' - ' . esc($staffRole) : '' ?>
						</option>
					<?php endforeach; ?>
				</select>
				<small class="text-muted">Only staff with a cashdrawer opened today are listed. Admin and manager roles are excluded.</small>
				<?php if(empty($cashdrawer_assignable_users ?? [])): ?>
					<small class="text-danger d-block">No eligible staff cashdrawers were found for today.</small>
				<?php endif; ?>
				<?php if(!empty($errors['assigned_staff_user_id'])): ?>
					<small class="text-danger d-block"><?=$errors['assigned_staff_user_id']?></small>
				<?php endif ?>
			</div>

			<div class="mb-3 col-md-12 d-none" id="itemsBox">
				<div class="d-flex justify-content-between align-items-center">
					<label class="form-label mb-0">Itemized Breakdown</label>
					<button type="button" class="btn btn-sm btn-outline-primary" id="itemsAddRow">
						<i class="fa fa-plus"></i> Add Item
					</button>
				</div>

				<div class="row g-2 mt-2">
					<div class="col-md-6">
						<label class="form-label">Total (auto)</label>
						<input type="text" class="form-control" id="items_total" value="0.00" readonly />
					</div>
					<div class="col-md-6">
						<label class="form-label">Hint</label>
						<input type="text" class="form-control" value="Add line items, total auto-calculates" readonly />
					</div>
				</div>

				<input type="hidden" name="items_breakdown" id="items_breakdown" value="<?=old_value('items_breakdown')?>" />

				<div class="table-responsive mt-2">
					<table class="table table-sm table-striped">
						<thead>
							<tr>
								<th>Item</th>
								<th style="width: 160px;">Amount</th>
								<th style="width: 60px;"></th>
							</tr>
						</thead>
						<tbody id="itemsRows"></tbody>
					</table>
				</div>
			</div>

			<div class="mb-3 col-md-12 d-none" id="salaryBox">
				<div class="d-flex justify-content-between align-items-center">
					<label class="form-label mb-0">Salary Apportionment</label>
					<button type="button" class="btn btn-sm btn-outline-primary" id="salaryAddRow">
						<i class="fa fa-plus"></i> Add Staff
					</button>
				</div>
				<div class="row g-2 mt-2">
					<div class="col-md-4">
						<label class="form-label" for="salary_month_select">Month</label>
						<select class="form-select" id="salary_month_select">
							<option value="">---Select---</option>
							<option value="01">January</option>
							<option value="02">February</option>
							<option value="03">March</option>
							<option value="04">April</option>
							<option value="05">May</option>
							<option value="06">June</option>
							<option value="07">July</option>
							<option value="08">August</option>
							<option value="09">September</option>
							<option value="10">October</option>
							<option value="11">November</option>
							<option value="12">December</option>
						</select>
					</div>
					<div class="col-md-4">
						<label class="form-label" for="salary_year_select">Year</label>
						<select class="form-select" id="salary_year_select"></select>
					</div>
					<div class="col-md-4">
						<label class="form-label">Total (auto)</label>
						<input type="text" class="form-control" id="salary_total" value="0.00" readonly />
					</div>
				</div>

				<input type="hidden" id="salary_month" name="salary_month" value="<?=old_value('salary_month')?>" />
				<?php if(!empty($errors['salary_month'])): ?>
					<small class="text-danger"><?=$errors['salary_month']?></small>
				<?php endif ?>

				<input type="hidden" name="salary_breakdown" id="salary_breakdown" value="<?=old_value('salary_breakdown')?>" />

				<div class="table-responsive mt-2">
					<table class="table table-sm table-striped">
						<thead>
							<tr>
								<th>Staff</th>
								<th>Role</th>
								<th style="width: 140px;">Amount Due</th>
								<th style="width: 60px;"></th>
							</tr>
						</thead>
						<tbody id="salaryRows"></tbody>
					</table>
				</div>
			</div>

			<div class="mb-3 col-md-12">
			  <label for="amount" class="form-label">Amount</label>
			  <div class="input-group">
			  	<div id="currencySymbol" class="input-group-text">GH₵</div>
			  	<input name="amount" value="<?=old_value('amount')?>" type="text" class="form-control" id="amount" placeholder="GH&cent;0.00">
			  </div>				
				<?php if(!empty($errors['amount'])): ?>
				  <small class="text-danger"><?=$errors['amount']?></small>
				<?php endif ?>
			</div>

			<div class="mb-3 col-md-12 d-none" id="cashDenoBox">
				<label for="cash_deno_display" class="form-label">Cash Denominations</label>
				<label class="input-group" data-bs-toggle="modal" data-bs-target="#paymentCashDenoModal" style="cursor:pointer;">
					<i class="fa-solid fa-calculator input-group-text text-warning"></i>
					<input type="text" class="form-control" id="cash_deno_display" placeholder="Click to enter denominations" readonly>
					<input type="hidden" id="denos_json" name="denos_json" value="<?=htmlspecialchars((string)old_value('denos_json'), ENT_QUOTES)?>" />
				</label>
				<small class="text-muted d-block">Calculator total must match the payment amount entered above.</small>
				<small class="text-muted d-block" id="cash_deno_count_text"></small>
				<small class="text-danger d-block" id="cash_deno_match_error"></small>
				<?php if(!empty($errors['denos_json'])): ?>
					<small class="text-danger d-block"><?=$errors['denos_json']?></small>
				<?php endif ?>
			</div>

			<div class="mb-3 col-md-12 input-group">
			  <label for="description" class="input-group-text">Purpose</label>
				<textarea rows="2" id="description" class="form-control" name="description"  placeholder="Write a brief description for this transaction"><?=old_value('description')?></textarea>			
				<?php if(!empty($errors['description'])):?>
				  <small class="text-danger"><?=$errors['description']?></small>
				<?php endif?>
			</div>

			<div class="mb-3 col-md-12 transaction_status">
			  <label for="transaction_status" class="form-label">Status</label>
			  <select class="form-select transaction_status" name="transaction_status">
				  <option value="">---Select---</option>
				  <option <?=old_select('transaction_status','Draft')?> value="Draft">Draft</option>
				  <option <?=old_select('transaction_status','Pending')?> value="Pending">Pending</option>
				  <?php if(user_can('approve_transaction')):?>
				  <option <?=old_select('transaction_status','Rejected')?> value="Rejected">Rejected</option>
				  <option <?=old_select('transaction_status','Approved')?> value="Approved">Approved</option>
				<?php endif;?>
			  </select>

				<?php if(!empty($errors['transaction_status'])): ?>
				  <small class="text-danger"><?=$errors['transaction_status']?></small>
				<?php endif ?>
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

	<div class="modal fade" id="paymentCashDenoModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="paymentCashDenoModalLabel" aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="paymentCashDenoModalLabel">Currency Notes</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body table-responsive">
					<div class="alert alert-info py-2 px-3 mb-3">
						Amount entered: <strong id="cash_deno_amount_text">GHS 0.00</strong>
					</div>
					<div class="alert alert-danger py-2 px-3 mb-3 d-none" id="cash_deno_modal_error"></div>
					<table class="table table-hover table-sm">
						<thead>
							<tr>
								<th>NOTES</th>
								<th>COUNT</th>
								<th>VOLUME</th>
							</tr>
						</thead>
						<tbody id="cashDenoRows">
							<?php foreach(payments_standard_ghs_denominations() as $denomination): ?>
								<?php
									$denomination = (float)$denomination;
									$isCoin = $denomination < 1;
									$label = $isCoin
										? (((int)round($denomination * 100)) . 'p')
										: ('GH&cent;' . rtrim(rtrim(number_format($denomination, 2, '.', ''), '0'), '.'));
								?>
								<tr data-denomination="<?=esc($denomination)?>">
									<td class="px-5"><?=$label?></td>
									<td><input type="number" min="0" step="1" class="form-control p-1 cash-deno-count" placeholder="Enter count"></td>
									<td><input type="text" class="form-control p-1 cash-deno-volume" placeholder="Volume" readonly></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
						<tfoot>
							<tr>
								<th class="px-5 bg-secondary text-warning">TOTAL</th>
								<th class="bg-secondary text-warning"><input type="text" class="form-control p-1" id="cash_deno_count" style="border:none;" placeholder="Total count" readonly></th>
								<th class="bg-secondary text-warning"><input type="text" class="form-control p-1" id="cash_deno_total" style="border:none;" placeholder="Total volume" readonly></th>
							</tr>
						</tfoot>
					</table>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
					<button type="button" class="btn btn-danger" id="cash_deno_reset_btn">Reset</button>
					<button type="button" class="btn btn-warning" id="cash_deno_restore_btn">Restore</button>
					<button type="button" class="btn btn-primary" id="cash_deno_confirm_btn">Confirm</button>
				</div>
			</div>
		</div>
	</div>

	<script>
	(function(){
		var users = [];
		try {
			users = <?php
				$usr = [];
				$dbRoles = new \Core\Database;
				try {
					foreach(($users ?? []) as $u){
						$roles = '';
						try{
							$roleRows = $dbRoles->query(
								"SELECT role FROM user_roles WHERE disabled = 0 AND id IN (SELECT role_id FROM user_roles_map WHERE disabled = 0 AND user_id = :uid)",
								['uid' => $u->id]
							) ?: [];
							if(!empty($roleRows)){
								$names = [];
								foreach($roleRows as $rr){
									$nm = trim((string)($rr->role ?? ''));
									if($nm !== '') $names[] = $nm;
								}
								$roles = implode(', ', $names);
							}
						}catch(\Throwable $e){ $roles = ''; }
						$usr[] = [
							'id' => (string)($u->id ?? ''),
							'name' => trim((string)($u->first_name ?? '') . ' ' . (string)($u->last_name ?? '')),
							'role' => (string)$roles,
						];
					}
				} catch(Throwable $e) {}
				echo json_encode($usr);
			?>;
		} catch(e){ users = []; }

		function money(n){
			var x = parseFloat(n);
			if(isNaN(x)) x = 0;
			return x.toFixed(2);
		}
		function esc(s){
			s = (s===undefined||s===null) ? '' : String(s);
			return s.replace(/[&<>"']/g, function(m){
				return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#039;'})[m];
			});
		}

		var paymentForm = document.querySelector('form');
		var elCategory = document.getElementById('payment_category');
		var elPaymentMode = document.getElementById('payment_mode');
		var elEcashNetworkBox = document.getElementById('ecashNetworkBox');
		var elEcashNetwork = document.getElementById('ecash_network');
		var elCashDenoBox = document.getElementById('cashDenoBox');
		var elCashDenoDisplay = document.getElementById('cash_deno_display');
		var elDenosInput = document.getElementById('denos_json');
		var elCashDenoCountText = document.getElementById('cash_deno_count_text');
		var elCashDenoMatchError = document.getElementById('cash_deno_match_error');
		var elCashDenoAmountText = document.getElementById('cash_deno_amount_text');
		var elCashDenoModalError = document.getElementById('cash_deno_modal_error');
		var elCashDenoModal = document.getElementById('paymentCashDenoModal');
		var elDenoRows = elCashDenoModal ? Array.from(elCashDenoModal.querySelectorAll('tr[data-denomination]')) : [];
		var elCashDenoCount = document.getElementById('cash_deno_count');
		var elCashDenoTotal = document.getElementById('cash_deno_total');
		var elCashDenoResetBtn = document.getElementById('cash_deno_reset_btn');
		var elCashDenoRestoreBtn = document.getElementById('cash_deno_restore_btn');
		var elCashDenoConfirmBtn = document.getElementById('cash_deno_confirm_btn');
		var elSalaryBox = document.getElementById('salaryBox');
		var elSalaryRows = document.getElementById('salaryRows');
		var elAddRow = document.getElementById('salaryAddRow');
		var elBreakdown = document.getElementById('salary_breakdown');
		var elAmount = document.getElementById('amount');
		var elSalaryTotal = document.getElementById('salary_total');
		var elSalaryMonth = document.getElementById('salary_month');
		var elSalaryMonthSelect = document.getElementById('salary_month_select');
		var elSalaryYearSelect = document.getElementById('salary_year_select');
		var elItemsBox = document.getElementById('itemsBox');
		var elItemsRows = document.getElementById('itemsRows');
		var elItemsAddRow = document.getElementById('itemsAddRow');
		var elItemsBreakdown = document.getElementById('items_breakdown');
		var elItemsTotal = document.getElementById('items_total');
		var elAssignedStaffBox = document.getElementById('assignedStaffBox');
		var elAssignedStaff = document.getElementById('assigned_staff_user_id');

		function pad2(n){
			var s = String(n||'');
			return s.length < 2 ? ('0' + s) : s;
		}
		function initSalaryMonthYear(){
			if(!elSalaryYearSelect) return;
			var now = new Date();
			var thisYear = now.getFullYear();
			var start = thisYear - 3;
			var end = thisYear + 3;
			var html = '<option value="">---Select---</option>';
			for(var y = start; y <= end; y++){
				html += '<option value="' + String(y) + '">' + String(y) + '</option>';
			}
			elSalaryYearSelect.innerHTML = html;

			var current = elSalaryMonth ? String(elSalaryMonth.value || '') : '';
			var m = '';
			var yv = '';
			if(/^\d{4}-\d{2}$/.test(current)){
				yv = current.slice(0,4);
				m = current.slice(5,7);
			}
			if(!yv) yv = String(thisYear);
			if(!m) m = pad2(now.getMonth() + 1);
			if(elSalaryMonthSelect) elSalaryMonthSelect.value = m;
			elSalaryYearSelect.value = yv;
			syncSalaryMonthHidden();
		}

		function syncSalaryMonthHidden(){
			if(!elSalaryMonth) return;
			var m = elSalaryMonthSelect ? String(elSalaryMonthSelect.value || '') : '';
			var y = elSalaryYearSelect ? String(elSalaryYearSelect.value || '') : '';
			if(m && y){
				elSalaryMonth.value = y + '-' + m;
			}else{
				elSalaryMonth.value = '';
			}
		}

		function isItemCategory(cat){
			cat = String(cat || '').toUpperCase();
			return (cat === 'MAINTENANCE' || cat === 'OPERATIONAL' || cat === 'LOGISTICS');
		}

		function getAmountValue(){
			var amount = elAmount ? parseFloat(String(elAmount.value || '').replace(/,/g, '')) : 0;
			return isNaN(amount) ? 0 : amount;
		}

		function showCashDenoModalError(message){
			if(!elCashDenoModalError) return;
			var text = String(message || '').trim();
			elCashDenoModalError.textContent = text;
			elCashDenoModalError.classList.toggle('d-none', text === '');
		}

		function setCashDenoMatchError(message){
			if(elCashDenoMatchError){
				elCashDenoMatchError.textContent = String(message || '');
			}
		}

		function readStoredCashDenos(){
			var rows = [];
			try { rows = JSON.parse(String(elDenosInput ? elDenosInput.value || '[]' : '[]')); } catch(e){ rows = []; }
			return Array.isArray(rows) ? rows : [];
		}

		function recalcCashDenos(){
			var totalCount = 0;
			var totalVolume = 0;
			var payload = [];
			elDenoRows.forEach(function(row){
				var denomination = Number(row.getAttribute('data-denomination') || 0);
				var countInput = row.querySelector('.cash-deno-count');
				var volumeInput = row.querySelector('.cash-deno-volume');
				var count = Number(countInput ? countInput.value || 0 : 0);
				if(!isFinite(count) || count < 0) count = 0;
				var volume = denomination * count;
				if(volumeInput){
					volumeInput.value = count > 0 ? money(volume) : '';
				}
				if(count > 0){
					payload.push({
						currency: 'GHS',
						denomination: denomination,
						count: count,
						volume: Number(money(volume))
					});
					totalCount += count;
					totalVolume += volume;
				}
			});
			if(elCashDenoCount) elCashDenoCount.value = totalCount ? String(totalCount) : '';
			if(elCashDenoTotal) elCashDenoTotal.value = totalVolume ? money(totalVolume) : '';
			return {
				payload: payload,
				totalCount: totalCount,
				totalVolume: Number(money(totalVolume))
			};
		}

		function restoreCashDenos(){
			var rows = readStoredCashDenos();
			elDenoRows.forEach(function(row){
				var denomination = Number(row.getAttribute('data-denomination') || 0);
				var countInput = row.querySelector('.cash-deno-count');
				var match = rows.find(function(item){
					return Number(item.denomination || 0) === denomination;
				});
				if(countInput){
					countInput.value = match ? Number(match.count || 0) : '';
				}
			});
			return recalcCashDenos();
		}

		function resetCashDenosDraft(){
			elDenoRows.forEach(function(row){
				var countInput = row.querySelector('.cash-deno-count');
				var volumeInput = row.querySelector('.cash-deno-volume');
				if(countInput) countInput.value = '';
				if(volumeInput) volumeInput.value = '';
			});
			showCashDenoModalError('');
			return recalcCashDenos();
		}

		function updateCashDenoSummary(){
			var rows = readStoredCashDenos();
			var totalCount = 0;
			var totalVolume = 0;
			rows.forEach(function(item){
				var count = Number(item.count || 0);
				var volume = Number(item.volume || 0);
				if(!isFinite(count) || count < 0) count = 0;
				if(!isFinite(volume) || volume < 0) volume = 0;
				totalCount += count;
				totalVolume += volume;
			});
			if(elCashDenoDisplay){
				elCashDenoDisplay.value = totalVolume > 0 ? money(totalVolume) : '';
			}
			if(elCashDenoCountText){
				elCashDenoCountText.textContent = totalCount > 0 ? ('Total notes/coins selected: ' + totalCount) : 'No denominations confirmed yet.';
			}
			return Number(money(totalVolume));
		}

		function validateCashDenosAgainstAmount(showError){
			var mode = elPaymentMode ? String(elPaymentMode.value || '').toLowerCase() : '';
			if(mode !== 'cash'){
				setCashDenoMatchError('');
				return true;
			}
			var amount = getAmountValue();
			var totalVolume = updateCashDenoSummary();
			if(elCashDenoAmountText){
				elCashDenoAmountText.textContent = 'GHS ' + money(amount);
			}
			var message = '';
			if(amount <= 0){
				message = 'Enter the payment amount before confirming cash denominations.';
			}else if(totalVolume <= 0){
				message = 'Use the currency denominations calculator for this cash payment.';
			}else if(Math.abs(totalVolume - amount) > 0.01){
				message = 'Confirmed denominations total GHS ' + money(totalVolume) + ', which does not match the payment amount of GHS ' + money(amount) + '.';
			}
			if(showError){
				setCashDenoMatchError(message);
			}else if(totalVolume > 0 && amount > 0 && Math.abs(totalVolume - amount) > 0.01){
				setCashDenoMatchError(message);
			}else{
				setCashDenoMatchError('');
			}
			return message === '';
		}

		function confirmCashDenos(){
			var amount = getAmountValue();
			if(amount <= 0){
				showCashDenoModalError('Enter the payment amount first, then confirm the denominations.');
				return;
			}
			var state = recalcCashDenos();
			if(state.totalVolume <= 0){
				showCashDenoModalError('Enter the denominations for this cash payment before confirming.');
				return;
			}
			if(Math.abs(state.totalVolume - amount) > 0.01){
				showCashDenoModalError('Denominations total GHS ' + money(state.totalVolume) + ' but the payment amount is GHS ' + money(amount) + '. They must match exactly.');
				return;
			}
			if(elDenosInput){
				elDenosInput.value = JSON.stringify(state.payload);
			}
			showCashDenoModalError('');
			updateCashDenoSummary();
			setCashDenoMatchError('');
			if(window.bootstrap && elCashDenoModal){
				bootstrap.Modal.getOrCreateInstance(elCashDenoModal).hide();
			}
		}

		function togglePaymentModeUI(){
			var mode = elPaymentMode ? String(elPaymentMode.value || '').toLowerCase() : '';
			var isCash = (mode === 'cash');
			var isEcash = (mode === 'ecash');
			if(elCashDenoBox){
				elCashDenoBox.classList.toggle('d-none', !isCash);
			}
			if(elEcashNetworkBox){
				elEcashNetworkBox.classList.toggle('d-none', !isEcash);
			}
			if(elEcashNetwork){
				elEcashNetwork.required = isEcash;
				if(!isEcash){
					elEcashNetwork.value = '';
				}
			}
			if(isCash){
				restoreCashDenos();
				validateCashDenosAgainstAmount(false);
			}else{
				setCashDenoMatchError('');
				showCashDenoModalError('');
			}
		}

		function addItemRow(pref){
			pref = pref || {};
			if(!elItemsRows) return;
			var tr = document.createElement('tr');
			tr.innerHTML = ''
				+ '<td><input type="text" class="form-control form-control-sm it-name" value="' + esc(pref.item_name || '') + '" placeholder="Item"></td>'
				+ '<td><input type="number" step="0.01" class="form-control form-control-sm it-amount" value="' + esc(pref.amount || '') + '" placeholder="0.00"></td>'
				+ '<td><button type="button" class="btn btn-sm btn-outline-danger it-del"><i class="fa fa-trash"></i></button></td>';
			elItemsRows.appendChild(tr);

			var nameEl = tr.querySelector('.it-name');
			var amtEl = tr.querySelector('.it-amount');
			var delEl = tr.querySelector('.it-del');
			function onChange(){ serializeItems(); }
			if(nameEl) nameEl.addEventListener('input', onChange);
			if(amtEl) amtEl.addEventListener('input', onChange);
			if(delEl) delEl.addEventListener('click', function(){ tr.remove(); serializeItems(); });
		}

		function serializeItems(){
			if(!elItemsRows || !elItemsBreakdown) return;
			var rows = Array.from(elItemsRows.querySelectorAll('tr'));
			var out = [];
			var total = 0;
			for(var i=0;i<rows.length;i++){
				var r = rows[i];
				var nameEl = r.querySelector('.it-name');
				var amtEl = r.querySelector('.it-amount');
				var name = nameEl ? String(nameEl.value || '') : '';
				var amt = amtEl ? parseFloat(amtEl.value) : 0;
				if(isNaN(amt)) amt = 0;
				if(name.trim() !== '' || amt > 0){
					out.push({ item_name: name, amount: amt });
					total += amt;
				}
			}
			elItemsBreakdown.value = JSON.stringify(out);
			if(elItemsTotal) elItemsTotal.value = money(total);
			var cat = elCategory ? String(elCategory.value || '').toUpperCase() : '';
			if(isItemCategory(cat) && elAmount){
				elAmount.value = money(total);
			}
			validateCashDenosAgainstAmount(false);
		}

		function getSelectedStaffIds(){
			if(!elSalaryRows) return [];
			var rows = Array.from(elSalaryRows.querySelectorAll('tr'));
			var out = [];
			for(var i=0;i<rows.length;i++){
				var sel = rows[i].querySelector('.sal-staff');
				var v = sel ? String(sel.value || '') : '';
				if(v) out.push(v);
			}
			return out;
		}

		function buildUserOptions(selectedId, excludeIds){
			excludeIds = Array.isArray(excludeIds) ? excludeIds : [];
			var ex = {};
			for(var i=0;i<excludeIds.length;i++) ex[String(excludeIds[i])] = true;
			var html = '<option value="">---Select---</option>';
			for(var j=0;j<users.length;j++){
				var u = users[j];
				var uid = String(u.id);
				if(ex[uid] && uid !== String(selectedId || '')) continue;
				var sel = (selectedId && uid === String(selectedId)) ? ' selected' : '';
				html += '<option value="' + esc(uid) + '"' + sel + '>' + esc(u.name) + '</option>';
			}
			return html;
		}

		function refreshStaffOptions(){
			if(!elSalaryRows) return;
			var rows = Array.from(elSalaryRows.querySelectorAll('tr'));
			var selected = getSelectedStaffIds();
			for(var i=0;i<rows.length;i++){
				var sel = rows[i].querySelector('.sal-staff');
				if(!sel) continue;
				var current = String(sel.value || '');
				var exclude = selected.filter(function(x){ return String(x) !== current; });
				sel.innerHTML = buildUserOptions(current, exclude);
				try{ sel.value = current; }catch(e){}
			}
		}

		function addSalaryRow(pref){
			pref = pref || {};
			var tr = document.createElement('tr');
			var exclude = getSelectedStaffIds();
			tr.innerHTML = ''
				+ '<td><select class="form-select form-select-sm sal-staff">' + buildUserOptions(pref.staff_id || '', exclude) + '</select></td>'
				+ '<td><input type="text" class="form-control form-control-sm sal-role" value="' + esc(pref.staff_role || '') + '" readonly></td>'
				+ '<td><input type="number" step="0.01" class="form-control form-control-sm sal-amount" value="' + esc(pref.amount_due || '') + '"></td>'
				+ '<td><button type="button" class="btn btn-sm btn-outline-danger sal-del"><i class="fa fa-trash"></i></button></td>';
			elSalaryRows.appendChild(tr);

			var sel = tr.querySelector('.sal-staff');
			var role = tr.querySelector('.sal-role');
			var amt = tr.querySelector('.sal-amount');
			var del = tr.querySelector('.sal-del');

			function syncRole(){
				var id = sel ? sel.value : '';
				if(id){
					var rows = Array.from(elSalaryRows.querySelectorAll('tr'));
					var dup = false;
					for(var di=0; di<rows.length; di++){
						if(rows[di] === tr) continue;
						var otherSel = rows[di].querySelector('.sal-staff');
						if(otherSel && String(otherSel.value || '') === String(id)) { dup = true; break; }
					}
					if(dup){
						try{ sel.value = ''; }catch(e){}
						id = '';
					}
				}
				var r = '';
				for(var i=0;i<users.length;i++){
					if(String(users[i].id) === String(id)){
						r = users[i].role || '';
						break;
					}
				}
				if(role) role.value = r;
				serializeSalary();
				refreshStaffOptions();
			}
			function onChange(){ serializeSalary(); }

			if(sel) sel.addEventListener('change', syncRole);
			if(amt) amt.addEventListener('input', onChange);
			if(del) del.addEventListener('click', function(){ tr.remove(); serializeSalary(); refreshStaffOptions(); });

			syncRole();
		}

		function serializeSalary(){
			if(!elSalaryRows || !elBreakdown) return;
			syncSalaryMonthHidden();
			var rows = Array.from(elSalaryRows.querySelectorAll('tr'));
			var out = [];
			var total = 0;
			for(var i=0;i<rows.length;i++){
				var r = rows[i];
				var staffSel = r.querySelector('.sal-staff');
				var roleInp = r.querySelector('.sal-role');
				var amtInp = r.querySelector('.sal-amount');
				var staffId = staffSel ? String(staffSel.value || '') : '';
				var staffName = '';
				for(var j=0;j<users.length;j++){
					if(String(users[j].id) === staffId){ staffName = users[j].name; break; }
				}
				var role = roleInp ? String(roleInp.value || '') : '';
				var amt = amtInp ? parseFloat(amtInp.value) : 0;
				if(isNaN(amt)) amt = 0;
				if(staffId){
					out.push({ staff_id: staffId, staff_name: staffName, staff_role: role, amount_due: amt });
					total += amt;
				}
			}
			elBreakdown.value = JSON.stringify(out);
			if(elSalaryTotal) elSalaryTotal.value = money(total);
			if(elCategory && String(elCategory.value || '').toUpperCase() === 'SALARY' && elAmount){
				elAmount.value = money(total);
			}
			validateCashDenosAgainstAmount(false);
		}

		function toggleSalary(){
			var cat = elCategory ? String(elCategory.value || '').toUpperCase() : '';
			var isSalary = (cat === 'SALARY');
			if(elAssignedStaffBox){
				elAssignedStaffBox.classList.toggle('d-none', isSalary);
			}
			if(elAssignedStaff){
				elAssignedStaff.required = !isSalary;
				if(isSalary){
					elAssignedStaff.value = '';
				}
			}
			if(isSalary){
				if(elSalaryBox) elSalaryBox.classList.remove('d-none');
				if(elItemsBox) elItemsBox.classList.add('d-none');
				if(elSalaryRows && !elSalaryRows.children.length){
					addSalaryRow({});
				}
				serializeSalary();
			}else if(isItemCategory(cat)){
				if(elSalaryBox) elSalaryBox.classList.add('d-none');
				if(elItemsBox) elItemsBox.classList.remove('d-none');
				if(elItemsRows && !elItemsRows.children.length){
					addItemRow({});
				}
				serializeItems();
			}else{
				if(elSalaryBox) elSalaryBox.classList.add('d-none');
				if(elItemsBox) elItemsBox.classList.add('d-none');
			}
			validateCashDenosAgainstAmount(false);
		}

		elDenoRows.forEach(function(row){
			var input = row.querySelector('.cash-deno-count');
			if(input){
				input.addEventListener('input', function(){
					recalcCashDenos();
					showCashDenoModalError('');
				});
			}
		});
		if(elCategory) elCategory.addEventListener('change', toggleSalary);
		if(elPaymentMode) elPaymentMode.addEventListener('change', togglePaymentModeUI);
		if(elAddRow) elAddRow.addEventListener('click', function(){ addSalaryRow({}); serializeSalary(); refreshStaffOptions(); });
		if(elItemsAddRow) elItemsAddRow.addEventListener('click', function(){ addItemRow({}); serializeItems(); });
		if(elSalaryMonthSelect) elSalaryMonthSelect.addEventListener('change', function(){ syncSalaryMonthHidden(); serializeSalary(); });
		if(elSalaryYearSelect) elSalaryYearSelect.addEventListener('change', function(){ syncSalaryMonthHidden(); serializeSalary(); });
		if(elAmount) elAmount.addEventListener('input', function(){ validateCashDenosAgainstAmount(false); });
		if(elCashDenoResetBtn) elCashDenoResetBtn.addEventListener('click', resetCashDenosDraft);
		if(elCashDenoRestoreBtn) elCashDenoRestoreBtn.addEventListener('click', function(){
			restoreCashDenos();
			showCashDenoModalError('');
		});
		if(elCashDenoConfirmBtn) elCashDenoConfirmBtn.addEventListener('click', confirmCashDenos);
		if(elCashDenoModal){
			elCashDenoModal.addEventListener('show.bs.modal', function(){
				restoreCashDenos();
				showCashDenoModalError('');
				if(elCashDenoAmountText){
					elCashDenoAmountText.textContent = 'GHS ' + money(getAmountValue());
				}
			});
		}
		if(paymentForm){
			paymentForm.addEventListener('submit', function(e){
				if(!validateCashDenosAgainstAmount(true)){
					e.preventDefault();
				}
			});
		}
		initSalaryMonthYear();
		updateCashDenoSummary();
		toggleSalary();
		togglePaymentModeUI();
	})();
	</script>

<?php else: ?>
	<div class="alert alert-danger text-center">
		Access denied. You don’t have permission for this action.
	</div>
<?php endif ?>
