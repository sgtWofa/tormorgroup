<?php if(user_can('edit_payment')): ?>
	<?php
		$__old_post = get_value('old_post');
		if (is_array($__old_post) && !empty($__old_post)) {
			$_POST = $__old_post;
			set_value('old_post', []);
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
		$rowCollectionPeriod = strtoupper(trim((string)($row->colletion_period ?? '')));
		$rowNetwork = strtoupper(trim((string)($row->network ?? '')));
		$rowStatus = strtolower(trim((string)($row->transaction_status ?? '')));
		$rawDenosJson = (string)old_value('denos_json', $row->denos_json ?? '');
		$rawDenosJsonTrim = trim($rawDenosJson);
		$denosJsonValue = ($rawDenosJsonTrim === '') ? '[]' : $rawDenosJsonTrim;
		if (function_exists('BackOfficeCashdrawer\\normalizeDenominations')) {
			$currency = ($rowNetwork === 'CFA') ? 'CFA' : 'GHS';
			$normalized = \BackOfficeCashdrawer\normalizeDenominations($denosJsonValue, (float)($row->amount ?? 0), $currency);
			$denosJsonValue = json_encode($normalized);
		}
	?>
	<form method="post" enctype="multipart/form-data">		
		<div class="row g-3 col-sm-12 col-md-11 col-lg-6 mx-auto shadow p-2 rounded">
			
			<?=csrf()?>

			<h4 class="text-center">
				<span><img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:60px;max-height: 60px;object-fit: cover;"></span>
				Update Commission
			</h4>

			<div class="col-md-12">
			  <label for="date_received" class="form-label">Date Received</label>
			  <div class="input-group">
			  	<div id="currencySymbol" class="input-group-text"><i class="fas fa-calendar-alt"></i></div>
			  	<input name="date_received" value="<?=old_date('date_received', $row->date_received)?>" type="date" class="form-control" id="date_received">
			  </div>			
			</div>

			<div class="col-md-12 ">
				<label for="colletion_period" class="form-label">Collection Period</label>
				<select class="form-select" name="colletion_period" id="colletion_period">	
				  <option value="">---Select---</option>
				  <!-- Options will be populated dynamically based on transaction type -->
				  <option <?=old_select('colletion_period','INSTANT DIRECT', $rowCollectionPeriod)?> value="INSTANT DIRECT">INSTANT DIRECT</option>
				  <option <?=old_select('colletion_period','INSTANT INDIRECT', $rowCollectionPeriod)?> value="INSTANT INDIRECT">INSTANT INDIRECT</option>
				  <option <?=old_select('colletion_period','NEXTDAY DIRECT', $rowCollectionPeriod)?> value="NEXTDAY DIRECT">NEXTDAY DIRECT</option>
				  <option <?=old_select('colletion_period','END OF MONTH DIRECT', $rowCollectionPeriod)?> value="END OF MONTH DIRECT">END OF MONTH DIRECT</option>
				  <option <?=old_select('colletion_period','END OF MONTH INDIRECT', $rowCollectionPeriod)?> value="END OF MONTH INDIRECT">END OF MONTH INDIRECT</option>
				  <option <?=old_select('colletion_period','QUARTERLY', $rowCollectionPeriod)?> value="QUARTERLY">QUARTERLY</option>
				</select>
				<?php if(!empty($errors['colletion_period'])): ?>
				  <small class="text-danger"><?=$errors['colletion_period']?></small>
				<?php endif ?>
			</div>

			<div class="col-md-12 ">
				<label for="network" class="form-label">Service Provider</label>
				<select class="form-select" name="network" id="network" onchange="updatePaymentOptions()">	
				  <option value="">---Select---</option>
				  <!-- Options will be populated dynamically based on transaction type -->
				  <option <?=old_select('network','AT', $rowNetwork)?> value="AT">AT</option>
				  <option <?=old_select('network','G-MONEY', $rowNetwork)?> value="G-MONEY">G-MONEY</option>
				  <option <?=old_select('network','GHANAPAY', $rowNetwork)?> value="GHANAPAY">GHANAPAY</option>
				  <option <?=old_select('network','MTN', $rowNetwork)?> value="MTN">MTN</option>
				  <option <?=old_select('network','TELECEL', $rowNetwork)?> value="TELECEL">TELECEL</option>
				  <option <?=old_select('network','ZEEPAY', $rowNetwork)?> value="ZEEPAY">ZEEPAY</option>
				  <option <?=old_select('network','FIDELITY', $rowNetwork)?> value="FIDELITY">FIDELITY</option>
				  <option <?=old_select('network','FBN', $rowNetwork)?> value="FBN">FBN</option>
				  <option <?=old_select('network','ACCESS', $rowNetwork)?> value="ACCESS">ACCESS</option>
				  <option <?=old_select('network','ECOBANK', $rowNetwork)?> value="ECOBANK">ECOBANK</option>
				  <option <?=old_select('network','CAL BANK', $rowNetwork)?> value="CAL BANK">CAL BANK</option>
				  <option <?=old_select('network','GHQR', $rowNetwork)?> value="GhQR">GhQR</option>
				  <option <?=old_select('network','EZWICH', $rowNetwork)?> value="EZWICH">EZWICH</option>
				  <option <?=old_select('network','CFA', $rowNetwork)?> value="CFA">CFA</option>
				</select>
				<?php if(!empty($errors['network'])): ?>
				  <small class="text-danger"><?=$errors['network']?></small>
				<?php endif ?>
			</div>

			<div class="col-md-12 ">
				<label for="commission_type" class="form-label">Commission Type</label>
				<select class="form-select commission_type" id="commission_type" name="commission_type">
					<!-- Options will be displayed dynamically -->
				</select>
				<?php if(!empty($errors['commission_type'])): ?>
				  <small class="text-danger"><?=$errors['commission_type']?></small>
				<?php endif ?>
			</div>

			<div class="col-md-12">
            <label for="pay_to" class="form-label">Pay to</label>
			<select class="form-select" name="pay_to" id="pay_to" onchange="validateWallet()">
                <option value="">---Select---</option>
                <?php if(!empty($users)): ?>
                    <?php foreach ($users as $wallet): ?>                        
                        <option <?=old_select('pay_to', $wallet->id, $row->pay_to)?> value="<?=$wallet->id?>">
                            <?=$wallet->first_name . ' ' . $wallet->last_name ?? ''?> [<?=$wallet->roles[0]?>]
                        </option>
                    <?php endforeach ?>
                <?php endif; ?>
            </select>
            <?php if(!empty($errors['pay_to'])): ?>
                <small class="text-danger"><?=$errors['pay_to']?></small>
            <?php endif ?>
        </div>

			<div class="col-md-12">
			  <label for="amount" class="form-label">Amount</label>
			  <div class="input-group" id="commissionEditCashAmountGroup" data-bs-toggle="modal" data-bs-target="#commissionEditDenoModal" style="cursor:pointer;">
				<span class="input-group-text"><i class="fa-solid fa-calculator text-warning"></i></span>
			  	<input name="amount" value="<?=old_value('amount', $row->amount ?? '')?>" type="number" step="0.01" class="form-control" id="amount" placeholder="Use calculator" readonly>
			  </div>
			  <small class="text-warning d-block fw-semibold" id="commissionEditDenoPrompt">Click the calculator to enter cash denominations before submitting.</small>
			  <input type="hidden" id="denos_json" name="denos_json" value="<?=htmlspecialchars($denosJsonValue, ENT_QUOTES)?>" />
			  <small class="text-danger d-none" id="commissionEditDenoRequiredHint">Cash denominations are required when Status is Approved.</small>
			  <small class="text-warning d-none" id="commissionEditDenoClearHint">Denominations are stored but not required unless Status is Approved.</small>
				<?php if(!empty($errors['denos_json']) || !empty($errors['denos_json_mismatch'])): ?>
				  <small class="text-danger d-block"><?=$errors['denos_json'] ?? $errors['denos_json_mismatch']?></small>
				<?php endif ?>
			</div>
				
			<div class="col-md-12">
			  <label for="description" class="form-label">Description</label>
				<textarea rows="2" id="description" class="form-control" name="description"  placeholder="Write a brief description for this transaction"><?=old_value('description', $row->description ?? '')?></textarea>			
			</div>

			<div class="col-md-12 transaction_status">
			  <label for="transaction_status" class="form-label">Status</label>
			  <select class="form-select transaction_status" name="transaction_status">
				  <option value="">---Select---</option>
				  <option <?=old_select('transaction_status','Draft', ucfirst($rowStatus))?> value="Draft">Draft</option>
				  <option <?=old_select('transaction_status','Pending', ucfirst($rowStatus))?> value="Pending">Pending</option>
				  <option <?=old_select('transaction_status','Rejected', ucfirst($rowStatus))?> value="Rejected">Rejected</option>
				  <option <?=old_select('transaction_status','Approved', ucfirst($rowStatus))?> value="Approved">Approved</option>
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
				<button class="btn btn-success" type="submit">
					<i class="fa-solid fa-save"></i> Submit
				</button>
			</div>
		</div>
	</form>

	<div class="modal fade" id="commissionEditDenoModal" tabindex="-1" aria-hidden="true">
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
				  <th><input type="text" class="form-control form-control-sm" id="commissionEditDenoCount" readonly /></th>
				  <th><input type="text" class="form-control form-control-sm" id="commissionEditDenoTotal" readonly /></th>
				</tr>
			  </tfoot>
			</table>
		  </div>
		  <div class="modal-footer">
			<button type="button" class="btn btn-outline-secondary" id="commissionEditDenoReset">Reset</button>
			<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
			<button type="button" class="btn btn-primary" id="commissionEditDenoApply">Apply</button>
		  </div>
		</div>
	  </div>
	</div>
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

<script>
	const networkOptions = {
		"AT": [
			{ value: "", text: "---Select---" },
			{ value: "AGENT COMMISSION", text: "AGENT COMMISSION" },
			{ value: "BIOMETRIC", text: "BIOMETRIC" },
			{ value: "CASH OUT", text: "CASH OUT" },
			{ value: "ECASH2PHYSICAL", text: "ECASH2PHYSICAL" },
			{ value: "EVD", text: "EVD" },
			{ value: "EVD WHOLESALE", text: "EVD WHOLESALE" },
			{ value: "INTEREST", text: "INTEREST" },
			{ value: "ISHARE", text: "ISHARE" },
			{ value: "SIMS WHOLESALE", text: "SIMS WHOLESALE" },
			{ value: "SIM RETAIL", text: "SIM RETAIL" },
			{ value: "SIM REGISTRATION", text: "SIM REGISTRATION" },
			{ value: "SENDING", text: "SENDING" },
			{ value: "TOKENS", text: "TOKENS" },
		],
		"MTN": [
			{ value: "", text: "---Select---" },
			{ value: "AGENT COMMISSION", text: "AGENT COMMISSION" },
			{ value: "BIOMETRIC", text: "BIOMETRIC" },
			{ value: "CASH OUT", text: "CASH OUT" },
			{ value: "ECASH2PHYSICAL", text: "ECASH2PHYSICAL" },
			{ value: "EVD", text: "EVD" },
			{ value: "EVD WHOLESALE", text: "EVD WHOLESALE" },
			{ value: "INTEREST", text: "INTEREST" },
			{ value: "ISHARE", text: "ISHARE" },
			{ value: "SIMS WHOLESALE", text: "SIMS WHOLESALE" },
			{ value: "SIM RETAIL", text: "SIM RETAIL" },
			{ value: "SIM REGISTRATION", text: "SIM REGISTRATION" },
			{ value: "SENDING", text: "SENDING" },
			{ value: "TOKENS", text: "TOKENS" },
		],
		"TELECEL": [
			{ value: "", text: "---Select---" },
			{ value: "AGENT COMMISSION", text: "AGENT COMMISSION" },
			{ value: "BIOMETRIC", text: "BIOMETRIC" },
			{ value: "CASH OUT", text: "CASH OUT" },
			{ value: "ECASH2PHYSICAL", text: "ECASH2PHYSICAL" },
			{ value: "EVD", text: "EVD" },
			{ value: "EVD WHOLESALE", text: "EVD WHOLESALE" },
			{ value: "INTEREST", text: "INTEREST" },
			{ value: "ISHARE", text: "ISHARE" },
			{ value: "SIMS WHOLESALE", text: "SIMS WHOLESALE" },
			{ value: "SIM RETAIL", text: "SIM RETAIL" },
			{ value: "SIM REGISTRATION", text: "SIM REGISTRATION" },
			{ value: "SENDING", text: "SENDING" },
			{ value: "TOKENS", text: "TOKENS" },
		],
		"GHANAPAY": [
			{ value: "", text: "---Select---" },
			{ value: "AGENT COMMISSION", text: "AGENT COMMISSION" },
			{ value: "AIRTIME COMMISSION", text: "AIRTIME COMMISSION" },
			{ value: "CASH OUT", text: "CASH OUT" },
			{ value: "INTEREST", text: "INTEREST" },
			{ value: "SENDING", text: "SENDING" },
		],
		"G-MONEY": [
			{ value: "", text: "---Select---" },
			{ value: "AGENT COMMISSION", text: "AGENT COMMISSION" },
			{ value: "CASH OUT", text: "CASH OUT" },
			{ value: "INTEREST", text: "INTEREST" },
			{ value: "SENDING", text: "SENDING" },
		],
		"ZEEPAY": [
			{ value: "", text: "---Select---" },
			{ value: "AGENT COMMISSION", text: "AGENT COMMISSION" },
			{ value: "CASH OUT", text: "CASH OUT" },
			{ value: "EVD", text: "EVD" },
			{ value: "DATA", text: "DATA" },
			{ value: "INTEREST", text: "INTEREST" },
			{ value: "SENDING", text: "SENDING" },
		],
		"FIDELITY": [
			{ value: "", text: "---Select---" },
			{ value: "AGENT COMMISSION", text: "AGENT COMMISSION" },
			{ value: "WITHDRAWAL", text: "WITHDRAWAL" },
			{ value: "ACCOUNT OPENING", text: "ACCOUNT OPENING" },
			{ value: "STATEMENT INQUIRY", text: "STATEMENT INQUIRY" },
			{ value: "BALANCE INQUIRY", text: "BALANCE INQUIRY" },
		],
		"FBN": [
			{ value: "AGENT COMMISSION", text: "AGENT COMMISSION" }
		],
		"ACCESS": [
			{ value: "AGENT COMMISSION", text: "AGENT COMMISSION" }
		],
		"ECOBANK": [
			{ value: "AGENT COMMISSION", text: "AGENT COMMISSION" }
		],
		"GTBANK": [
			{ value: "AGENT COMMISSION", text: "AGENT COMMISSION" }
		],
		"CAL BANK": [
			{ value: "AGENT COMMISSION", text: "AGENT COMMISSION" }
		],
		"EZWICH": [
			{ value: "AGENT COMMISSION", text: "AGENT COMMISSION" }
		],
		"GhQR": [
			{ value: "AGENT COMMISSION", text: "AGENT COMMISSION" }
		],
		"CFA": [
			{ value: "BUY & SELL", text: "BUY & SELL" }
		],
	};

	const commissionTypeSelect = document.getElementById('commission_type');
	const networkSelect = document.getElementById('network');
	const existingCommissionType = <?=json_encode(strtoupper(trim((string)old_value('commission_type', $row->commission_type ?? ''))))?>;

	function updatePaymentOptions() {
		if(!commissionTypeSelect || !networkSelect) return;
		const networkSelectValue = String(networkSelect.value || '');
		commissionTypeSelect.innerHTML = '';
		if (networkOptions[networkSelectValue]) {
			networkOptions[networkSelectValue].forEach(option => {
				const opt = document.createElement('option');
				opt.value = option.value;
				opt.textContent = option.text;
				commissionTypeSelect.appendChild(opt);
			});
			if(existingCommissionType){
				Array.from(commissionTypeSelect.options).forEach(opt => {
					if(String(opt.value || '').toUpperCase() === existingCommissionType){
						opt.selected = true;
					}
				});
			}
		}
	}

	document.addEventListener('DOMContentLoaded', function(){
		updatePaymentOptions();
		const modalEl = document.getElementById('commissionEditDenoModal');
		if(!modalEl) return;
		const denosInput = document.getElementById('denos_json');
		const amountInput = document.getElementById('amount');
		const networkSelect = document.getElementById('network');
		const statusSelect = document.querySelector('select[name="transaction_status"]');
		const submitBtn = document.querySelector('button[type="submit"]');
		const denosRequiredHint = document.getElementById('commissionEditDenoRequiredHint');
		const denosClearHint = document.getElementById('commissionEditDenoClearHint');
		const countField = document.getElementById('commissionEditDenoCount');
		const totalField = document.getElementById('commissionEditDenoTotal');
		const resetBtn = document.getElementById('commissionEditDenoReset');
		const applyBtn = document.getElementById('commissionEditDenoApply');
		const countInputs = modalEl.querySelectorAll('.deno-count');
		const denomRows = modalEl.querySelectorAll('tbody tr[data-denomination]');

		function getStatusLower(){
			return statusSelect ? String(statusSelect.value || '').trim().toLowerCase() : '';
		}
		function getAmountValue(){
			return parseFloat((amountInput && amountInput.value) ? amountInput.value : '0') || 0;
		}
		function hasDenosPayload(){
			if(!denosInput) return false;
			const raw = String(denosInput.value || '').trim();
			if(!raw) return false;
			try{
				const payload = JSON.parse(raw);
				return Array.isArray(payload) && payload.length > 0;
			}catch(e){
				return false;
			}
		}
		function toggleSubmitAvailability(){
			if(!submitBtn) return;
			const isApproved = getStatusLower() === 'approved';
			const amountValue = getAmountValue();
			const requiresDenos = isApproved && amountValue > 0;
			const ok = (!requiresDenos) || hasDenosPayload();
			submitBtn.disabled = !ok;
			if(denosRequiredHint){
				denosRequiredHint.classList.toggle('d-none', ok || !requiresDenos);
			}
			if(denosClearHint){
				denosClearHint.classList.toggle('d-none', !(!requiresDenos && hasDenosPayload()));
			}
		}

		function getSelectedCurrency(){
			if(networkSelect && String(networkSelect.value || '').toUpperCase() === 'CFA') return 'CFA';
			if(denosInput){
				const raw = String(denosInput.value || '').trim();
				if(raw){
					try{
						const payload = JSON.parse(raw);
						if(Array.isArray(payload)){
							for(const entry of payload){
								if(String(entry?.currency || '').toUpperCase() === 'CFA') return 'CFA';
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
				row.style.display = (String(row.dataset.currency || '').toUpperCase() === cur) ? '' : 'none';
			});
		}
		function resetCounts(){
			countInputs.forEach(input => {
				input.value='';
				const row = input.closest('tr');
				if(row){
					const vol = row.querySelector('.deno-volume');
					if(vol) vol.value='';
				}
			});
			recalc();
		}
		function recalc(){
			applyCurrencyVisibility();
			let totalCount = 0;
			let totalVolume = 0;
			const snapshot = [];
			const cur = getSelectedCurrency();
			denomRows.forEach(row => {
				if(row.style.display === 'none') return;
				const denom = parseFloat(row.dataset.denomination || '0') || 0;
				const input = row.querySelector('.deno-count');
				const count = input ? (parseInt(input.value || '0', 10) || 0) : 0;
				const volume = denom * count;
				const volInput = row.querySelector('.deno-volume');
				if(volInput) volInput.value = volume ? volume.toFixed((cur === 'CFA') ? 0 : 2) : '';
				if(count > 0 && volume > 0){
					snapshot.push({currency: cur, denomination: denom, count, volume});
					totalCount += count;
					totalVolume += volume;
				}
			});
			if(countField) countField.value = totalCount ? String(totalCount) : '';
			if(totalField) totalField.value = totalVolume ? totalVolume.toFixed((cur === 'CFA') ? 0 : 2) : '';
			return {snapshot, totalVolume};
		}
		function restoreFromHidden(){
			countInputs.forEach(inp => {
				inp.value = '';
				const row = inp.closest('tr');
				if(row){
					const vol = row.querySelector('.deno-volume');
					if(vol) vol.value = '';
				}
			});
			applyCurrencyVisibility();
			try{
				const payload = JSON.parse((denosInput && denosInput.value) ? denosInput.value : '[]');
				if(Array.isArray(payload)){
					payload.forEach(entry => {
						countInputs.forEach(inp => {
							const row = inp.closest('tr');
							if(!row) return;
							const denom = parseFloat(row.dataset.denomination || '0') || 0;
							const rowCur = String(row.dataset.currency || '').toUpperCase();
							const entryCur = String(entry.currency || '').toUpperCase();
							if(rowCur === entryCur && Math.abs(denom - (parseFloat(entry.denomination || 0) || 0)) < 0.0001){
								inp.value = String(parseInt(entry.count || 0, 10) || 0);
							}
						});
					});
				}
			}catch(e){}
			recalc();
		}

		modalEl.addEventListener('shown.bs.modal', restoreFromHidden);
		countInputs.forEach(input => input.addEventListener('input', recalc));
		if(networkSelect) networkSelect.addEventListener('change', () => {
			updatePaymentOptions();
			if(denosInput) denosInput.value = '';
			resetCounts();
			toggleSubmitAvailability();
		});
		if(resetBtn) resetBtn.addEventListener('click', () => {
			if(denosInput) denosInput.value = '';
			resetCounts();
			toggleSubmitAvailability();
		});
		if(applyBtn) applyBtn.addEventListener('click', () => {
			const {snapshot, totalVolume} = recalc();
			if(denosInput) denosInput.value = JSON.stringify(snapshot);
			if(amountInput){
				amountInput.value = totalVolume ? Number(totalVolume).toFixed((getSelectedCurrency() === 'CFA') ? 0 : 2) : '';
			}
			toggleSubmitAvailability();
			const bsModal = bootstrap.Modal.getInstance(modalEl);
			if(bsModal) bsModal.hide();
		});

		if(statusSelect) statusSelect.addEventListener('change', toggleSubmitAvailability);
		if(denosInput) denosInput.addEventListener('input', toggleSubmitAvailability);
		toggleSubmitAvailability();
	});

 </script>
