<?php
	$session = get_session();
	$currentUserId = (int)($session->id ?? 0);
	$rowOwnerId = (is_object($row ?? null) ? (int)($row->created_by ?? 0) : 0);
	$currentStatusLower = strtolower(trim((string)($row->transaction_status ?? '')));
	$isApprovedStatusOnlyEdit = in_array($currentStatusLower, ['approved','sent'], true);
	$isClosedStatus = in_array($currentStatusLower, ['declined','rejected'], true);
	$canEdit = (user_can('edit_agencybanking') || ($rowOwnerId > 0 && $rowOwnerId === $currentUserId)) && !$isClosedStatus;
?>

<?php if($canEdit):?>
	<link rel="stylesheet" href="<?=plugin_http_path('assets/css/agency-forms.css')?>?v=<?= (int)filemtime(__DIR__ . '/../../assets/css/agency-forms.css') ?>">
	<style>
		.transaction-preview-card{
			position: relative;
			width: 100%;
			max-width: 240px;
			margin: 0 auto;
			border-radius: 14px;
			overflow: hidden;
			border: 1px solid var(--bs-border-color);
			background: var(--bs-secondary-bg, var(--bs-body-bg));
			box-shadow: 0 12px 28px rgba(15,23,42,0.12);
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
			border: 1px dashed rgba(var(--bs-warning-rgb,255,193,7),0.55);
			border-radius: 14px;
			margin: .5rem;
			color: var(--bs-secondary-color, #6c757d);
			background: var(--bs-tertiary-bg, var(--bs-body-bg));
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

	<?php if(!empty($row)):?>
		<?php
			$txType = trim((string)($row->transaction_type ?? ''));
			$txTypeLower = strtolower($txType);
			$entryTemplate = '';
			if ($txTypeLower === 'deposit') {
				$entryTemplate = 'deposit.php';
			} elseif (strpos($txTypeLower, 'withdrawal') !== false) {
				$entryTemplate = 'withdrawal.php';
			} elseif ($txTypeLower === 'funds transfer') {
				$entryTemplate = 'funds_transfer.php';
			} elseif (strpos($txTypeLower, 'interbank') !== false) {
				$entryTemplate = 'interbank.php';
			} elseif (strpos($txTypeLower, 'international transfer') !== false) {
				$entryTemplate = 'international_transfer.php';
			} elseif (strpos($txTypeLower, 'direct transfer') !== false) {
				$entryTemplate = 'direct_transfer.php';
			} elseif ($txTypeLower === 'account opening') {
				$entryTemplate = 'account_opening.php';
			} elseif ($txTypeLower === 'mini statement') {
				$entryTemplate = 'mini_statement.php';
			} elseif ($txTypeLower === 'balance enquiry') {
				$entryTemplate = 'balance_enquiry.php';
			}

			if ($entryTemplate !== '') {
				if (empty($_POST)) {
					foreach (get_object_vars($row) as $k => $v) {
						if (!array_key_exists($k, $_POST)) {
							$_POST[$k] = $v;
						}
					}
					$_POST['transaction_type'] = $txType;
					$_POST['network'] = (string)($row->network ?? '');
				}

				$existingTransactionImage = trim((string)($row->transaction_image ?? ''));
				$existingTransactionImageUrl = '';
				if ($existingTransactionImage !== '') {
					if (preg_match('/^https?:\/\//i', $existingTransactionImage)) {
						$existingTransactionImageUrl = $existingTransactionImage;
					} else {
						$existingTransactionImageUrl = rtrim((string)ROOT, '/') . '/' . ltrim($existingTransactionImage, '/');
					}
				}

				$denoSeedPayload = [];
				try{
					$denoModelSeed = new \Agencybankings\Deno;
					$denoRowsSeed = $denoModelSeed->where(['trans_id' => $row->id]) ?? [];
					if(!empty($denoRowsSeed)){
						foreach($denoRowsSeed as $denRowSeed){
							$denom = (float)($denRowSeed->denomination ?? 0);
							$cnt = (int)($denRowSeed->count ?? 0);
							if($denom <= 0 || $cnt <= 0) continue;
							$vol = $denom * $cnt;
							$denoSeedPayload[] = [
								'currency' => $denRowSeed->currency ?? 'GHS',
								'denomination' => $denom,
								'count' => $cnt,
								'volume' => (float)$vol,
							];
						}
					}
				}catch(\Throwable $e){
					$denoSeedPayload = [];
				}

				// Seed old_value-backed fields (paid + denos JSON) for initial GET load.
				// Some entry templates (e.g. interbank) rely on old_value('paid') and old_value('denos'),
				// but these are not persisted in agencybankings table.
				if (empty($_POST['denos']) && !empty($denoSeedPayload)) {
					$_POST['denos'] = json_encode($denoSeedPayload, JSON_UNESCAPED_SLASHES);
				}
				if (empty($_POST['paid'])) {
					$paidSeed = 0.0;
					if (!empty($denoSeedPayload)) {
						foreach ($denoSeedPayload as $d) {
							$paidSeed += ((float)($d['denomination'] ?? 0)) * ((float)($d['count'] ?? 0));
						}
					}
					if ($paidSeed <= 0) {
						$amt = (float)($row->amount ?? 0);
						$chg = (float)($row->charge ?? 0);
						$txLowerForPaid = strtolower(trim((string)($row->transaction_type ?? '')));

						// Interbank/GIP and transfers typically pay amount + charge.
						$paidSeed = (strpos($txLowerForPaid, 'interbank') !== false || strpos($txLowerForPaid, 'transfer') !== false)
							? ($amt + $chg)
							: $amt;
					}
					if ($paidSeed > 0) {
						$_POST['paid'] = number_format($paidSeed, 2, '.', '');
					}
				}

				?>
				<div class="agency-form-page">
					<?php require plugin_path('views/admin/entries/' . $entryTemplate); ?>
				</div>
				<script>
					(function(){
						const preset = {
							transaction_type: <?= json_encode((string)($row->transaction_type ?? '')) ?>,
							network: <?= json_encode((string)($row->network ?? '')) ?>,
							category: <?= json_encode((string)($row->category ?? '')) ?>,
							destination_bank: <?= json_encode((string)($row->destination_bank ?? '')) ?>,
							sof_option: <?= json_encode((string)($row->sof_option ?? '')) ?>,
							payment_medium: <?= json_encode((string)($row->payment_medium ?? '')) ?>,
							charging_type: <?= json_encode((string)($row->charging_type ?? '')) ?>,
							transaction_status: <?= json_encode((string)($row->transaction_status ?? '')) ?>,
						};

						function setSelectValueWhenReady(selectId, value, onApplied) {
							if (!value) return;
							let tries = 0;
							const maxTries = 40;
							const timer = setInterval(function(){
								tries++;
								const el = document.getElementById(selectId);
								if (!el) {
									if (tries >= maxTries) clearInterval(timer);
									return;
								}
								if (el.options && el.options.length > 0) {
									el.value = value;
									if (el.value !== value) {
										const target = String(value).trim().toLowerCase();
										for (let i = 0; i < el.options.length; i++) {
											if (String(el.options[i].value || '').trim().toLowerCase() === target) {
												el.selectedIndex = i;
												break;
											}
										}
									}
									try { el.dispatchEvent(new Event('change', { bubbles: true })); } catch(e) {}
									clearInterval(timer);
									if (typeof onApplied === 'function') onApplied(el);
								}
								if (tries >= maxTries) clearInterval(timer);
							}, 50);
						}

						document.addEventListener('DOMContentLoaded', function(){
							setSelectValueWhenReady('transaction_type', preset.transaction_type, function(){ showFields(); });
							setSelectValueWhenReady('network', preset.network, function(){ showFields(); });
							setSelectValueWhenReady('category', preset.category, function(){ showFields(); });
							setSelectValueWhenReady('destination_bank', preset.destination_bank, function(){ showFields(); });
							setSelectValueWhenReady('sof_option', preset.sof_option, function(){ showFields(); });
							setSelectValueWhenReady('payment_medium', preset.payment_medium, function(){ showFields(); });
							setSelectValueWhenReady('charging_type', preset.charging_type, function(){ showFields(); });
							setSelectValueWhenReady('transaction_status', preset.transaction_status, function(){ showFields(); });
							setTimeout(function(){ showFields(); }, 150);
						});
					})();
				</script>
				<script>
					(function(){
						const denosSeed = <?= json_encode($denoSeedPayload) ?>;
						if (!Array.isArray(denosSeed) || denosSeed.length === 0) return;

						function seedDenosWhenReady(){
							let tries = 0;
							const maxTries = 60;
							const timer = setInterval(function(){
								tries++;
								const countInputs = document.querySelectorAll('.count-input');
								const denosField = document.getElementById('denos');
								if (!countInputs || countInputs.length === 0 || !denosField) {
									if (tries >= maxTries) clearInterval(timer);
									return;
								}

								try {
									localStorage.setItem('denosData', JSON.stringify(denosSeed));
								} catch (e) {}
								denosField.value = JSON.stringify(denosSeed);

								// Fill counts based on denomination values
								countInputs.forEach(function(input){
									const row = input.closest('tr');
									if (!row) return;
									const denom = parseFloat(row.getAttribute('data-denomination'));
									if (!denom) return;
									const match = denosSeed.find(function(d){ return parseFloat(d.denomination) === denom; });
									if (match) {
										input.value = match.count;
										const vol = row.querySelector('.volume-input');
										if (vol) vol.value = (denom * (parseFloat(match.count)||0)).toFixed(2);
									} else {
										input.value = '';
										const vol = row.querySelector('.volume-input');
										if (vol) vol.value = '';
									}
								});

								// Set totals + readonly paid fields if present
								let totalVol = 0;
								denosSeed.forEach(function(d){
									totalVol += (parseFloat(d.denomination)||0) * (parseFloat(d.count)||0);
								});
								const totalVolumeField = document.getElementById('total-volume');
								if (totalVolumeField) totalVolumeField.value = totalVol.toFixed(2);

								const amountPaid = document.getElementById('amount_paid');
								if (amountPaid && (!amountPaid.value || parseFloat(amountPaid.value) === 0)) {
									amountPaid.value = totalVol.toFixed(2);
								}
								const amount = document.getElementById('amount');
								if (amount && (!amount.value || parseFloat(amount.value) === 0)) {
									amount.value = totalVol.toFixed(2);
								}

								// Trigger any template listeners to recalc
								try {
									if (countInputs[0]) countInputs[0].dispatchEvent(new Event('input', { bubbles: true }));
								} catch (e) {}
								clearInterval(timer);
							}, 50);
						}

						document.addEventListener('DOMContentLoaded', seedDenosWhenReady);
					})();
				</script>
				<?php
				if ($existingTransactionImageUrl !== ''):
			?>
			<script>
				(function(){
					var img = document.getElementById('transactionImagePreview');
					if (!img) return;
					img.src = <?= json_encode($existingTransactionImageUrl) ?>;
					img.style.display = 'block';
					var placeholder = document.getElementById('transactionImagePreviewPlaceholder');
					if (placeholder) placeholder.style.display = 'none';
				})();
			</script>
			<?php
				endif;
			?>
			<script>
				(function(){
					if (<?= $isApprovedStatusOnlyEdit ? 'true' : 'false' ?>) {
						return;
					}
					function initAgencyReason(){
						const form = document.querySelector('form');
						if(!form) return;
						const existingEntryReason = <?= json_encode((string)old_value('rejection_reason', $row->rejection_reason ?? '')) ?>;
						const entryReasonErrorText = <?= json_encode((string)($errors['rejection_reason'] ?? '')) ?>;
						const status = form.querySelector('select[name="transaction_status"]');
						if(!status) return;
						if(!Array.from(status.options).some(option => String(option.value).toLowerCase() === 'declined')){
							const option = document.createElement('option');
							option.value = 'Declined';
							option.textContent = 'Declined';
							const rejected = Array.from(status.options).find(option => String(option.value).toLowerCase() === 'rejected');
							status.insertBefore(option, rejected || null);
						}
						let group = document.getElementById('agencyEntryRejectionReasonGroup');
						if(!group){
							group = document.createElement('div');
							group.className = 'mb-2 d-none';
							group.id = 'agencyEntryRejectionReasonGroup';
							group.innerHTML = `
								<label for="rejection_reason" class="form-label">Rejection reason</label>
								<textarea rows="3" name="rejection_reason" class="form-control" id="rejection_reason" placeholder="State clearly why this transaction is being declined or rejected."></textarea>
								<small class="text-danger js-agency-rejection-reason-error"></small>`;
							const actions = Array.from(form.querySelectorAll('button')).find(btn => btn.type === 'submit' || btn.classList.contains('btn-danger') || btn.classList.contains('save'))?.closest('.d-flex');
							if(actions && actions.parentNode){
								actions.parentNode.insertBefore(group, actions);
							}
							group.querySelector('textarea').value = existingEntryReason;
							group.querySelector('small').textContent = entryReasonErrorText;
						}
						const reason = document.getElementById('rejection_reason');
						const error = document.querySelector('.js-agency-rejection-reason-error');
						function sync(){
							const needsReason = ['declined','rejected'].includes(String(status.value || '').trim().toLowerCase());
							const hasReason = String(reason?.value || '').trim() !== '';
							group?.classList.toggle('d-none', !needsReason);
							reason?.toggleAttribute('required', needsReason);
							if(error && needsReason && !hasReason) error.textContent = 'Rejection reason is required.';
							else if(error && !needsReason) error.textContent = '';
							return !needsReason || hasReason;
						}
						status.addEventListener('change', sync);
						reason?.addEventListener('input', sync);
						form.addEventListener('submit', function(event){
							if(!sync()) event.preventDefault();
						});
						sync();
					}
					if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initAgencyReason);
					else initAgencyReason();
				})();
			</script>
			<?php
				if ($isApprovedStatusOnlyEdit):
			?>
			<script>
				(function(){
					const currentStatus = <?= json_encode((string)($row->transaction_status ?? 'Approved')) ?>;
					const existingReason = <?= json_encode((string)($row->rejection_reason ?? '')) ?>;
					const escapeHtml = function(value){
						return String(value ?? '').replace(/[&<>"']/g, function(ch){
							return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;', "'":'&#039;'}[ch];
						});
					};
					function initStatusOnlyEdit(){
						const form = document.querySelector('form');
						if(!form) return;
						const submitButtons = Array.from(form.querySelectorAll('button')).filter(function(btn){
							return btn.type === 'submit' || btn.classList.contains('save') || btn.id === 'saveBtn' || btn.classList.contains('btn-danger');
						});
						Array.from(form.elements || []).forEach(function(el){
							if(!el || !el.name) return;
							if(['csrf_token','transaction_status','rejection_reason'].includes(el.name)) return;
							if(el.type === 'hidden') return;
							if(el.tagName === 'SELECT' || el.type === 'file' || el.type === 'checkbox' || el.type === 'radio'){
								el.disabled = true;
							}else{
								el.readOnly = true;
							}
						});
						form.querySelectorAll('[data-bs-toggle]').forEach(function(el){
							el.removeAttribute('data-bs-toggle');
							el.removeAttribute('data-bs-target');
						});

						form.querySelectorAll('[name="transaction_status"]').forEach(function(el){
							el.disabled = true;
							el.removeAttribute('name');
						});
						form.querySelectorAll('[name="rejection_reason"]').forEach(function(el){
							el.removeAttribute('name');
							if(el.id === 'rejection_reason') el.id = 'rejection_reason_locked_original';
						});
						const existingReasonGroup = document.getElementById('agencyEntryRejectionReasonGroup') || document.getElementById('agencyRejectionReasonGroup');
						if(existingReasonGroup) existingReasonGroup.classList.add('d-none');

						const actions = submitButtons[0]?.closest('.d-flex') || form.lastElementChild;
						const currentStatusHtml = escapeHtml(currentStatus || 'Approved');
						const block = document.createElement('div');
						block.className = 'mb-3';
						block.innerHTML = `
							<div class="alert alert-warning d-flex align-items-center gap-2">
								<i class="fa-solid fa-lock"></i>
								<span>This approved transaction is locked. Only the status can be changed to Declined or Rejected, with a rejection reason.</span>
							</div>
							<label for="agency_status_only_status" class="form-label">Status</label>
							<select class="form-select" id="agency_status_only_status" name="transaction_status">
								<option value="${currentStatusHtml}">${currentStatusHtml}</option>
								<option value="Declined">Declined</option>
								<option value="Rejected">Rejected</option>
							</select>
							<label for="rejection_reason" class="form-label mt-2">Rejection reason</label>
							<textarea rows="3" class="form-control" id="rejection_reason" name="rejection_reason" placeholder="State clearly why this agency banking transaction is being declined or rejected."></textarea>
							<small class="text-danger js-agency-rejection-error"></small>
						`;
						if(actions && actions.parentNode){
							actions.parentNode.insertBefore(block, actions);
						}else{
							form.appendChild(block);
						}

						const status = document.getElementById('agency_status_only_status');
						const reason = document.getElementById('rejection_reason');
						const error = document.querySelector('.js-agency-rejection-error');
						if(reason) reason.value = existingReason;
						function sync(){
							const needsReason = ['declined','rejected'].includes(String(status?.value || '').trim().toLowerCase());
							const hasReason = String(reason?.value || '').trim() !== '';
							if(reason) reason.toggleAttribute('required', needsReason);
							if(error) error.textContent = (needsReason && !hasReason) ? 'Rejection reason is required.' : '';
							const canSave = needsReason && hasReason;
							submitButtons.forEach(function(btn){
								btn.disabled = !canSave;
								btn.classList.toggle('disabled', !canSave);
							});
							return canSave;
						}
						status?.addEventListener('change', sync);
						reason?.addEventListener('input', sync);
						form.addEventListener('submit', function(event){
							if(!sync()){
								event.preventDefault();
							}
						});
						sync();
					}
					if(document.readyState === 'loading'){
						document.addEventListener('DOMContentLoaded', initStatusOnlyEdit);
					}else{
						initStatusOnlyEdit();
					}
				})();
			</script>
			<?php
				endif;
				return;
			}
		?>

		<?php
			$denoRows = $denoRows ?? [];
			$hasDenos = !empty($denoRows);
			$denoPayload = [];
			$denoTotalVolume = 0;
			$denoTotalCount = 0;
			$calculatorEligibleTypes = [
				'CIT',
				'Deposit',
				'Withdrawal (CIT)',
				'Withdrawal (AIT)',
				'Funds transfer',
				'Interbank (gip)',
				'International transfer',
				'Direct transfer',
				'Direct transfer (CIT)',
			];
			$currentTxType = trim($row->transaction_type ?? '');
			$showDenoCalculator = $hasDenos || in_array($currentTxType, $calculatorEligibleTypes, true);
			if($hasDenos){
				foreach($denoRows as $denRow){
					$denomination = (float)($denRow->denomination ?? 0);
					$count = (int)($denRow->count ?? 0);
					$volume = (float)($denRow->volume ?? ($denomination * $count));
					$currency = $denRow->currency ?? 'GHS';
					$denoPayload[] = [
						'denomination' => $denomination,
						'count' => $count,
						'currency' => $currency,
						'volume' => $volume,
					];
					$denoTotalVolume += $volume;
					$denoTotalCount += $count;
				}
			}
		?>

		<form method="post" enctype="multipart/form-data" class="agency-entry-form">
			<div class="agency-form-fields row g-3">
				
				<?=csrf()?>
				<input type="hidden" name="transaction_type" id="transaction_type_hidden" value="<?=old_value('transaction_type', $row->transaction_type ?? '')?>">
				<input type="hidden" name="network" id="network_hidden" value="<?=old_value('network', $row->network ?? '')?>">

				<h4 class="text-center">
					<span><img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:60px;max-height: 60px;object-fit: cover;"></span>
					Edit Transaction
				</h4>
		 
				<div class="">
					<label for="transaction_type" class="form-label">Transaction type</label>
					<select class="form-select" name="transaction_type" id="transaction_type" onchange="showFields()" disabled>
					  <option value="">---Select---</option>
					  <option <?=old_select('transaction_type','CIT', $row->transaction_type ?? '')?> value="CIT">CIT</option>
					  <option <?=old_select('transaction_type','Deposit', $row->transaction_type ?? '')?> value="Deposit">Deposit</option>
					  <option <?=old_select('transaction_type','Withdrawal (CIT)', $row->transaction_type ?? '')?> value="Withdrawal (CIT)">Withdrawal (CIT)</option>
					  <option <?=old_select('transaction_type','Withdrawal (AIT)', $row->transaction_type ?? '')?> value="Withdrawal (AIT)">Withdrawal (AIT)</option>
					  <option <?=old_select('transaction_type','Account opening', $row->transaction_type ?? '')?> value="Account opening">Account opening</option>
					  <option <?=old_select('transaction_type','Mini statement', $row->transaction_type ?? '')?> value="Mini statement">Mini statement</option>
					  <option <?=old_select('transaction_type','Balance enquiry', $row->transaction_type ?? '')?> value="Balance enquiry">Balance enquiry</option>
					  <option <?=old_select('transaction_type','Funds transfer', $row->transaction_type ?? '')?> value="Funds transfer">Funds transfer</option>
					  <option <?=old_select('transaction_type','Direct transfer (CIT)', $row->transaction_type ?? '')?> value="Direct transfer (CIT)">Direct transfer (CIT)</option>
					  <option <?=old_select('transaction_type','Interbank (gip)', $row->transaction_type ?? '')?> value="Interbank (gip)">Interbank (gip)</option>
					  <option <?=old_select('transaction_type','International transfer', $row->transaction_type ?? '')?> value="International transfer">International transfer</option>
					</select>

					<?php if(!empty($errors['transaction_type'])):?>
					  <small class="text-danger"><?=$errors['transaction_type']?></small>
					<?php endif?>
				</div>
		 
				<div class="">
					<label for="network" class="form-label">Source</label>
					<select class="form-select" name="network" id="network" onchange="showFields()">			  
					  <!-- Options will be populated dynamically based on transaction type -->
					</select>

					<?php if(!empty($errors['network'])):?>
					  <small class="text-danger"><?=$errors['network']?></small>
					<?php endif?>
				</div>
		 
				<div class=" category d-none">
					<label for="category" class="form-label">Category</label>
					<select class="form-select" name="category" id="category" onchange="showFields()" disabled>
					  <!-- Options will be populated dynamically based on transaction type -->
					</select>

					<?php if(!empty($errors['category'])):?>
					  <small class="text-danger"><?=$errors['category']?></small>
					<?php endif?>
				</div>

				<div class="">
				  <label for="phone" class="form-label">Phone</label>
				  <input name="phone" value="<?=old_value('phone', $row->phone)?>" type="text" class="form-control" id="phone" placeholder="Enter phone number">
					
					<?php if(!empty($errors['phone'])):?>
					  <small class="text-danger"><?=$errors['phone']?></small>
					<?php endif?>
				</div>

				<div class=" account_number d-none">
				  <label for="account_number" class="form-label">Account number</label>
				  <input name="account_number" value="<?=old_value('account_number', $row->account_number ?? '')?>" type="text" class="form-control" id="account_number" placeholder="Enter account number">
					
					<?php if(!empty($errors['account_number'])):?>
					  <small class="text-danger"><?=$errors['account_number']?></small>
					<?php endif?>
				</div>

				<div class=" destination_bank d-none">
				  <label for="destination_bank" class="form-label">Destination bank</label>
				  <input name="destination_bank" value="<?=old_value('destination_bank', $row->destination_bank ?? '')?>" type="text" class="form-control" id="destination_bank" placeholder="Enter destination bank ">
					
					<?php if(!empty($errors['destination_bank'])):?>
					  <small class="text-danger"><?=$errors['destination_bank']?></small>
					<?php endif?>
				</div>

				<div class=" source_bank d-none">
				  <label for="source_bank" class="form-label">Source bank</label>
				  <input name="source_bank" value="<?=old_value('source_bank', $row->source_bank ?? '')?>" type="text" class="form-control" id="source_bank" placeholder="Enter source bank ">
					
					<?php if(!empty($errors['source_bank'])):?>
					  <small class="text-danger"><?=$errors['source_bank']?></small>
					<?php endif?>
				</div>

				<div class=" sender_phone d-none">
				  <label for="sender_phone" class="form-label">Sender's phone</label>
				  <input name="sender_phone" value="<?=old_value('sender_phone', $row->sender_phone ?? '')?>" type="text" class="form-control" id="sender_phone" placeholder="Enter sender's phone number">
					
					<?php if(!empty($errors['sender_phone'])):?>
					  <small class="text-danger"><?=$errors['sender_phone']?></small>
					<?php endif?>
				</div>

				<div class=" ussd d-none">
				  <label for="ussd" class="form-label">USSD used</label>
				  <input name="ussd" value="<?=old_value('ussd', $row->ussd ?? '')?>" type="text" class="form-control" id="ussd" placeholder="Enter ussd used">
					
					<?php if(!empty($errors['ussd'])):?>
					  <small class="text-danger"><?=$errors['ussd']?></small>
					<?php endif?>
				</div>

				<div class=" cash_source d-none">
				  <label for="cash_source" class="form-label">Source of funds</label>
				  <input name="cash_source" value="<?=old_value('cash_source', $row->cash_source ?? '')?>" type="text" class="form-control" id="cash_source" placeholder="Enter source of funds">
					
					<?php if(!empty($errors['cash_source'])):?>
					  <small class="text-danger"><?=$errors['cash_source']?></small>
					<?php endif?>
				</div>

				<div class=" balance_before d-none">
				  <label for="balance_before" class="form-label">Balance before</label>
				  <input name="balance_before" value="<?=old_value('balance_before', $row->balance_before ?? '')?>" type="number" step="0.01" class="form-control" id="balance_before" placeholder="Enter balance_before">
					
					<?php if(!empty($errors['balance_before'])):?>
					  <small class="text-danger"><?=$errors['balance_before']?></small>
					<?php endif?>
				</div>

				<div class=" amount d-none">
				  <label for="amount" class="form-label">Amount</label>
				  <input name="amount" value="<?=old_value('amount', $row->amount ?? '')?>" type="number" step="0.1" class="form-control" id="amount" placeholder="Enter amount">
					
					<?php if(!empty($errors['amount'])):?>
					  <small class="text-danger"><?=$errors['amount']?></small>
					<?php endif?>
				</div>

				<div class=" full_name d-none">
				  <label for="full_name" class="form-label">Full name</label>
				  <input name="full_name" value="<?=old_value('full_name', $row->full_name ?? '')?>" type="text" class="form-control" id="full_name" placeholder="Enter full name here">
					
					<?php if(!empty($errors['full_name'])):?>
					  <small class="text-danger"><?=$errors['full_name']?></small>
					<?php endif?>
				</div>

				<div class=" dob d-none">
				  <label for="dob" class="form-label">Date of birth</label>
				  <input name="dob" value="<?=old_date('dob', $row->dob ?? '')?>" type="date" class="form-control" id="dob" placeholder="Date of birth">
					
					<?php if(!empty($errors['dob'])):?>
					  <small class="text-danger"><?=$errors['dob']?></small>
					<?php endif?>
				</div>

				<div class=" house_number d-none">
				  <label for="house_number" class="form-label">House number</label>
				  <input name="house_number" value="<?=old_value('house_number', $row->house_number ?? '')?>" type="text" class="form-control" id="house_number" placeholder="Enter house number here">
					
					<?php if(!empty($errors['house_number'])):?>
					  <small class="text-danger"><?=$errors['house_number']?></small>
					<?php endif?>
				</div>

				<div class=" nearest_landmark d-none">
				  <label for="nearest_landmark" class="form-label">Nearest landmark</label>
				  <input name="nearest_landmark" value="<?=old_value('nearest_landmark', $row->nearest_landmark ?? '')?>" type="text" class="form-control" id="nearest_landmark" placeholder="Enter nearst landmark here">
					
					<?php if(!empty($errors['nearest_landmark'])):?>
					  <small class="text-danger"><?=$errors['nearest_landmark']?></small>
					<?php endif?>
				</div>

				<div class=" id_type d-none">
				  <label for="id_type" class="form-label">ID type</label>
				  <select class="form-select id_type" name="id_type">
					  <option value="">---Select---</option>
					  <option <?=old_select('id_type','Ghana card', $row->id_type ?? '')?> value="Ghana card">Ghana card</option>
					  <option <?=old_select('id_type','Voter ID', $row->id_type ?? '')?> value="Voter ID">Voter ID</option>
					  <option <?=old_select('id_type','NHIS', $row->id_type ?? '')?> value="NHIS">NHIS</option>
					  <option <?=old_select('id_type','Passport', $row->id_type ?? '')?> value="Passport">Passport</option>
					  <option <?=old_select('id_type','Driver license', $row->id_type ?? '')?> value="Driver license">Driver license</option>
				  </select>
					
					<?php if(!empty($errors['id_type'])):?>
					  <small class="text-danger"><?=$errors['id_type']?></small>
					<?php endif?>
				</div>

				<div class=" id_number d-none">
				  <label for="id_number" class="form-label">ID number</label>
				  <input name="id_number" value="<?=old_value('id_number', $row->id_number ?? '')?>" type="text" class="form-control" id="id_number" placeholder="Enter ID number here">
					
					<?php if(!empty($errors['id_number'])):?>
					  <small class="text-danger"><?=$errors['id_number']?></small>
					<?php endif?>
				</div>

				<div class=" next_of_king d-none">
				  <label for="next_of_king" class="form-label">Next of king</label>
				  <input name="next_of_king" value="<?=old_value('next_of_king', $row->next_of_king ?? '')?>" type="text" class="form-control" id="next_of_king" placeholder="Enter name of next of king">
					
					<?php if(!empty($errors['next_of_king'])):?>
					  <small class="text-danger"><?=$errors['next_of_king']?></small>
					<?php endif?>
				</div>

				<div class=" nok_phone d-none">
				  <label for="nok_phone" class="form-label">Next of king phone</label>
				  <input name="nok_phone" value="<?=old_value('nok_phone', $row->nok_phone ?? '')?>" type="text" class="form-control" id="nok_phone" placeholder="Enter next of king phone number">
					
					<?php if(!empty($errors['nok_phone'])):?>
					  <small class="text-danger"><?=$errors['nok_phone']?></small>
					<?php endif?>
				</div>

				<div class=" nok_relation d-none">
				  <label for="nok_relation" class="form-label">Relation with Next of king</label>
				  <input name="nok_relation" value="<?=old_value('nok_relation', $row->nok_relation ?? '')?>" type="text" class="form-control" id="nok_relation" placeholder="Client's relationship with next of king">
					
					<?php if(!empty($errors['nok_relation'])):?>
					  <small class="text-danger"><?=$errors['nok_relation']?></small>
					<?php endif?>
				</div>

				<div class=" token receiver d-none">
				  <label for="receiver" class="form-label">Receiver's phone</label>
				  <input name="receiver" value="<?=old_value('receiver', $row->receiver ?? '')?>" type="text" class="form-control" id="receiver" placeholder="Enter receiver's phone number">
					
					<?php if(!empty($errors['receiver'])):?>
					  <small class="text-danger"><?=$errors['receiver']?></small>
					<?php endif?>
				</div>

				<div class="">
				  <label for="charge" class="form-label">Charge </label>
				  <div class="input-group">
				  	<div id="currencySymbol" class="input-group-text">GH₵</div>
				  	<input name="charge" value="<?=old_value('charge', $row->charge ?? '')?>" type="number" step="0.01" class="form-control" id="charge" placeholder="GH&cent;0.00">
				  </div>
					
					<?php if(!empty($errors['charge'])):?>
					  <small class="text-danger"><?=$errors['charge']?></small>
					<?php endif?>
				</div>

				<div class=" amount_to_pay d-none">
				  <label for="amount_to_pay" class="form-label">Amount to Pay</label>
				  <input name="amount_to_pay" value="<?=old_value('amount_to_pay', $row->amount_to_pay ?? '')?>" type="number" step="0.01" class="form-control" id="amount_to_pay" placeholder="GH&cent;" readonly>
				  <?php if(!empty($errors['amount_to_pay'])):?>
				    <small class="text-danger"><?=$errors['amount_to_pay']?></small>
				  <?php endif?>
				  <small class="text-danger" id="amount_to_pay-error"></small>
				</div>

				<div class=" balance d-none">
				  <label for="balance" class="form-label">New Balance</label>
				  <input name="balance" value="<?=old_value('balance', $row->balance ?? '')?>" type="text" class="form-control" id="balance" placeholder="GH&cent;" readonly>
					
					<?php if(!empty($errors['balance'])):?>
					  <small class="text-danger"><?=$errors['balance']?></small>
					<?php endif?>
				</div>

				<?php
					$existingTransactionImage = trim((string)($row->transaction_image ?? ''));
					$existingTransactionImageUrl = '';
					if ($existingTransactionImage !== '') {
						if (preg_match('/^https?:\/\//i', $existingTransactionImage)) {
							$existingTransactionImageUrl = $existingTransactionImage;
						} else {
							$existingTransactionImageUrl = rtrim((string)ROOT, '/') . '/' . ltrim($existingTransactionImage, '/');
						}
					}
				?>
				<div class="mb-2 transaction_image">
				  <label for="transaction_image" class="form-label">Transaction image</label>
				  <div class="mt-2" id="transactionImagePreviewWrap">
						<div class="transaction-preview-card" id="transactionImagePreviewCard" role="button" tabindex="0">
							<div class="transaction-preview-placeholder" id="transactionImagePreviewPlaceholder">
								<i class="fa-solid fa-cloud-arrow-up" style="font-size:1.4rem;"></i>
								<div class="title">Upload transaction picture</div>
								<div class="hint">Tap to choose an image</div>
							</div>
							<img id="transactionImagePreview" src="<?=esc($existingTransactionImageUrl)?>" alt="Transaction message picture" style="display:none;">
							<div class="transaction-preview-overlay">
								<span class="transaction-preview-badge"><i class="fa-solid fa-camera"></i> Change</span>
							</div>
						</div>
				  	<small class="text-muted d-block mt-1 text-center">Tap the preview to change the image</small>
				  </div>
				  <small class="text-danger js-transaction-image-error text-center d-block"><?=(string)($errors['transaction_image'] ?? '')?></small>
				  <input name="transaction_image" type="file" class="visually-hidden-file" id="transaction_image" accept="image/*">
				</div>

				<div class="mb-2 amount_denos <?= $showDenoCalculator ? '' : 'd-none' ?>">
			  <label class="form-label">Amount Paid Out</label>
			  <label class="input-group" data-bs-toggle="modal" data-bs-target="#denoModal">
			    <span class="input-group-text bg-dark text-warning"><i class="fa-solid fa-calculator"></i></span>
			    <input name="amount" type="number" step="0.01" class="form-control" id="deno_amount" placeholder="Launch calculator" readonly>
			    <input type="hidden" id="denos" name="denos" value="<?=htmlspecialchars(old_value('denos', $hasDenos ? json_encode($denoPayload) : ''), ENT_QUOTES, 'UTF-8')?>" />
			  </label>
			  <?php if($hasDenos): ?>
			  <small class="text-muted d-block mt-1">Prefilled with <?=number_format($denoTotalCount)?> notes · GHS <?=number_format($denoTotalVolume, 2)?>.
				Use the calculator to adjust.</small>
			  <?php else: ?>
			  <small class="text-muted d-block mt-1">Use the currency calculator to capture denominations for this transaction.</small>
			  <?php endif; ?>
			  <?php if(!empty($errors['amount'])):?>
			    <small class="text-danger"><?=$errors['amount']?></small>
			  <?php endif?>
			  <?php if(!empty($errors['denos'])):?>
			    <small class="text-danger"><?=$errors['denos']?></small>
			  <?php endif?>
			</div>

				<div class=" transaction_status d-none">
				  <label for="transaction_status" class="form-label">Status</label>
				  <select class="form-select transaction_status" name="transaction_status">
					  <option value="">---Select---</option>
					  <option <?=old_select('transaction_status','Draft', $row->transaction_status ?? '')?> value="Draft">Draft</option>
					  <option <?=old_select('transaction_status','Pending', $row->transaction_status ?? '')?> value="Pending">Pending</option>
					 <?php if(user_can('approve_transactions')):?>
					  <option <?=old_select('transaction_status','Declined', $row->transaction_status ?? '')?> value="Declined">Declined</option>
					  <option <?=old_select('transaction_status','Rejected', $row->transaction_status ?? '')?> value="Rejected">Rejected</option>
					  <option <?=old_select('transaction_status','Approved', $row->transaction_status ?? '')?> value="Approved">Approved</option>
					<?php endif;?>
				  </select>
					
					<?php if(!empty($errors['transaction_status'])):?>
					  <small class="text-danger"><?=$errors['transaction_status']?></small>
					<?php endif?>
				</div>

				<div class="mb-2 <?= in_array(strtolower((string)old_value('transaction_status', $row->transaction_status ?? '')), ['declined','rejected'], true) ? '' : 'd-none' ?>" id="agencyRejectionReasonGroup">
				  <label for="rejection_reason" class="form-label">Rejection reason</label>
				  <textarea rows="3" name="rejection_reason" class="form-control" id="rejection_reason" placeholder="State clearly why this agency banking transaction is being declined or rejected."><?=old_value('rejection_reason', $row->rejection_reason ?? '')?></textarea>
					<?php if(!empty($errors['rejection_reason'])):?>
					  <small class="text-danger"><?=$errors['rejection_reason']?></small>
					<?php endif?>
					<small class="text-danger js-agency-rejection-reason-error"></small>
				</div>
		 
				<div class="d-flex justify-content-between" style="margin-top:10px;">
					<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
						<button type="button" class="btn btn-primary">
							<i class="fa-solid fa-chevron-left"></i> Back
						</button>
					</a>
					<button class="btn btn-danger">
						<i class="fa-solid fa-save"></i> Save
					</button>
				</div>
			</div>
		</form>

		<!-- Denomination Modal -->
		<div class="modal fade" id="denoModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="denoModalLabel" aria-hidden="true">
		  <div class="modal-dialog">
		    <div class="modal-content">
		      <div class="modal-header">
		        <h5 class="modal-title" id="denoModalLabel">Currency Notes</h5>
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
		          <tbody id="denoTableBody">
		            <!-- Populated by JavaScript -->
		          </tbody>
		          <tfoot>
		            <tr>
		              <th class="px-5 bg-secondary text-warning">TOTAL</th>
		              <th class="bg-secondary text-warning"><input type="text" class="form-control p-1" id="total-count" style="border:none;" placeholder="Total count" readonly></th>
		              <th class="bg-secondary text-warning"><input type="text" class="form-control p-1" id="total-volume" style="border:none;" placeholder="Total volume" readonly></th>
		            </tr>
		          </tfoot>
		        </table>
		      </div>
		      <div class="modal-footer">
		        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
		        <button type="button" class="btn btn-danger reset">Reset</button>
		        <button type="button" class="btn btn-primary submit">Confirm</button>
		      </div>
		    </div>
		  </div>
		</div>

		<?php if($isApprovedStatusOnlyEdit):?>
		<script>
			(function(){
				const form = document.querySelector('form');
				if(!form) return;
				const currentStatus = <?= json_encode((string)($row->transaction_status ?? 'Approved')) ?>;
				const existingReason = <?= json_encode((string)($row->rejection_reason ?? '')) ?>;
				const escapeHtml = function(value){
					return String(value ?? '').replace(/[&<>"']/g, function(ch){
						return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;', "'":'&#039;'}[ch];
					});
				};
				const submitButtons = Array.from(form.querySelectorAll('button')).filter(btn => btn.type === 'submit' || btn.classList.contains('btn-danger'));
				Array.from(form.elements || []).forEach(function(el){
					if(!el || !el.name) return;
					if(['csrf_token','transaction_status','rejection_reason'].includes(el.name) || el.type === 'hidden') return;
					if(el.tagName === 'SELECT' || el.type === 'file') el.disabled = true;
					else el.readOnly = true;
				});
				form.querySelectorAll('[name="transaction_status"]').forEach(function(el){
					el.disabled = true;
					el.removeAttribute('name');
				});
				form.querySelectorAll('[name="rejection_reason"]').forEach(function(el){
					el.removeAttribute('name');
					if(el.id === 'rejection_reason') el.id = 'rejection_reason_locked_original';
				});
				const existingReasonGroup = document.getElementById('agencyRejectionReasonGroup');
				if(existingReasonGroup) existingReasonGroup.classList.add('d-none');
				const actions = submitButtons[0]?.closest('.d-flex') || form.lastElementChild;
				const currentStatusHtml = escapeHtml(currentStatus || 'Approved');
				const block = document.createElement('div');
				block.className = 'mb-3';
				block.innerHTML = `
					<div class="alert alert-warning d-flex align-items-center gap-2"><i class="fa-solid fa-lock"></i><span>This approved transaction is locked. Only the status can be changed to Declined or Rejected, with a rejection reason.</span></div>
					<label class="form-label" for="agency_status_only_status">Status</label>
					<select class="form-select" id="agency_status_only_status" name="transaction_status"><option value="${currentStatusHtml}">${currentStatusHtml}</option><option value="Declined">Declined</option><option value="Rejected">Rejected</option></select>
					<label class="form-label mt-2" for="rejection_reason">Rejection reason</label>
					<textarea rows="3" class="form-control" id="rejection_reason" name="rejection_reason" placeholder="State clearly why this transaction is being declined or rejected."></textarea>
					<small class="text-danger js-agency-rejection-error"></small>`;
				actions?.parentNode?.insertBefore(block, actions);
				const status = document.getElementById('agency_status_only_status');
				const reason = document.getElementById('rejection_reason');
				const error = document.querySelector('.js-agency-rejection-error');
				if(reason) reason.value = existingReason;
				function sync(){
					const needsReason = ['declined','rejected'].includes(String(status?.value || '').trim().toLowerCase());
					const hasReason = String(reason?.value || '').trim() !== '';
					if(error) error.textContent = needsReason && !hasReason ? 'Rejection reason is required.' : '';
					submitButtons.forEach(btn => {
						btn.disabled = !(needsReason && hasReason);
						btn.classList.toggle('disabled', !(needsReason && hasReason));
					});
					return needsReason && hasReason;
				}
				status?.addEventListener('change', sync);
				reason?.addEventListener('input', sync);
				form.addEventListener('submit', function(event){ if(!sync()) event.preventDefault(); });
				sync();
			})();
		</script>
		<?php endif;?>

	<?php else:?>
		<div class="alert alert-danger text-center">
			That record was not found!
		</div>
		
		<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
			<button class="btn btn-primary">Back</button>
		</a>
	<?php endif?>

<?php else:?>
	<div class="alert alert-danger text-center">
		<?= $isClosedStatus ? 'Declined or rejected agency banking transactions can no longer be edited.' : 'Access denied. You dont have permission for this action' ?>
	</div>
<?php endif?>

<script>
	const categoryOptions = {
		"Deposit": [
			{ value: "", text: "---Select---" },
			{ value: "self", text: "self" },
			{ value: "3rd party", text: "3rd party" }
		]
	};

	const sourceOptions = {
		"CIT": [
			{ value: "", text: "---Select---" },
			{ value: "GhQR", text: "GhQR" },
			{ value: "Cal bank", text: "Cal bank" },
			{ value: "Ecobank", text: "Ecobank" }
		],
		"Direct transfer (CIT)": [
			{ value: "", text: "---Select---" },
			{ value: "ABSA", text: "ABSA" },
			{ value: "Access Bank", text: "Access Bank" },
			{ value: "ADB", text: "ADB" },
			{ value: "APEX BANK", text: "APEX BANK" },
			{ value: "Bank of Africa", text: "Bank of Africa" },
			{ value: "Cal bank", text: "Cal bank" },
			{ value: "CBG", text: "CBG" },
			{ value: "Ecobank", text: "Ecobank" },
			{ value: "Fidelity", text: "Fidelity" },
			{ value: "First Atlantic Bank", text: "First Atlantic Bank" },
			{ value: "First Bank Ghana", text: "First Bank Ghana" },
			{ value: "FNB", text: "FNB" },
			{ value: "GCB", text: "GCB" },
			{ value: "Ghana Pay", text: "Ghana Pay" },
			{ value: "Gtbank", text: "Gtbank" },
			{ value: "NIB", text: "NIB" },
			{ value: "Prudential Bank", text: "Prudential Bank" },
			{ value: "Republic Bank", text: "Republic Bank" },
			{ value: "OmniBSIC Bank", text: "OmniBSIC Bank" },
			{ value: "Society General", text: "Society General" },
			{ value: "Stanbic Bank", text: "Stanbic Bank" },
			{ value: "Standard Charted", text: "Standard Charted" },
			{ value: "UBA", text: "UBA" },
			{ value: "umb", text: "umb" },
			{ value: "Zenit Bank", text: "Zenit Bank" },
		],
		"Deposit": [
			{ value: "", text: "---Select---" },
			{ value: "Fidelity", text: "Fidelity" },
			{ value: "Cal bank", text: "Cal bank" },
			{ value: "Ecobank", text: "Ecobank" },
			{ value: "FBN", text: "FBN" },
			{ value: "Access Bank", text: "Access Bank" },
		],
		"Withdrawal (AIT)": [
	        { value: "", text: "---Select---" },
	        { value: "Cal bank", text: "Cal bank" },
	        { value: "FBN", text: "FBN" },
	        { value: "Access Bank", text: "Access Bank" }
	    ],
	    "Withdrawal (CIT)": [
	        { value: "", text: "---Select---" },
	        { value: "GhQR", text: "GhQR" },
	        { value: "Cal bank", text: "Cal bank" },
	        { value: "Ecobank", text: "Ecobank" },
	        { value: "Fidelity Bank", text: "Fidelity Bank [Account to Account" }
	    ],
		"Account opening": [
			{ value: "", text: "---Select---" },
			{ value: "Fidelity", text: "Fidelity" },
			{ value: "Cal bank", text: "Cal bank" },
			{ value: "Ecobank", text: "Ecobank" }
		],
		"Mini statement": [
			{ value: "", text: "---Select---" },
			{ value: "Fidelity", text: "Fidelity" },
		],
		"Balance enquiry": [
			{ value: "", text: "---Select---" },
			{ value: "Fidelity", text: "Fidelity" },
		],
		"Funds transfer": [
			{ value: "", text: "---Select---" },
			{ value: "Fidelity", text: "Fidelity" },
		],
		"Interbank (gip)": [
			{ value: "", text: "---Select---" },
			{ value: "Gtbank", text: "Gtbank" },
			{ value: "Ecobank", text: "Ecobank" },
			{ value: "FBN", text: "FBN" },
			{ value: "Access Bank", text: "Access Bank" },
		],
		"International transfer": [
			{ value: "", text: "---Select---" },
			{ value: "Ecobank", text: "Ecobank" },
		],
	};

	const transactionType = document.getElementById('transaction_type'); // Corrected ID selection
	const categorySelect = document.getElementById('category');
	const sourceSelect = document.getElementById('network');
	const categoryDiv = document.querySelector('.category');
	// Get the existing transaction type, category, and source from the backend
	const txTypeHidden = document.getElementById('transaction_type_hidden');
	const networkHidden = document.getElementById('network_hidden');
	const existingTransactionType = txTypeHidden ? (txTypeHidden.value || "") : "";
	const existingCategory = "<?= esc(old_value('category', $row->category ?? '')) ?>";
	const existingSource = networkHidden ? (networkHidden.value || "") : "";


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

	            // Pre-select the option if it matches the existing category
	            if (option.value === existingCategory) {
	                opt.selected = true;
	            }

	            categorySelect.appendChild(opt);
	        });
	    } else {
	        categorySelect.classList.add('d-none'); // Hide if no options are available
	        categoryDiv.classList.add('d-none'); // Hide if no options are available
	    }
	    showFields();
	}

	function updateSourceOptions() {
	    const transactionTypeValue = transactionType.value;
	    sourceSelect.innerHTML = ""; // Clear current options

	    // Populate options based on selected transaction type
	    if (sourceOptions[transactionTypeValue]) {
	        sourceOptions[transactionTypeValue].forEach(option => {
	            const opt = document.createElement('option');
	            opt.value = option.value;
	            opt.textContent = option.text;

	            // Pre-select the option if it matches the existing source
	            if (option.value === existingSource) {
	                opt.selected = true;
	            }

	            sourceSelect.appendChild(opt);
	        });
	    }
	    showFields();
	}

	function showFields() {
		const sourceValue = sourceSelect.value;
		const transactionTypeValue = transactionType.value;
		const categoryValue = categorySelect.value;
		const account_number = document.querySelector('.account_number');
		const destination_bank = document.querySelector('.destination_bank');
		const balance = document.querySelector('.balance');
		const balance_before = document.querySelector('.balance_before');
		const sender_phone = document.querySelector('.sender_phone');
		const transaction_status = document.querySelector('.transaction_status');
		const cash_source = document.querySelector('.cash_source');
		const ussd = document.querySelector('.ussd');
		const amount = document.querySelector('.amount');
		const amount_denos = document.querySelector('.amount_denos');
		const full_name = document.querySelector('.full_name');
		const dob = document.querySelector('.dob');
		const house_number = document.querySelector('.house_number');
		const nearest_landmark = document.querySelector('.nearest_landmark');
		const id_type = document.querySelector('.id_type');
		const id_number = document.querySelector('.id_number');
		const next_of_king = document.querySelector('.next_of_king');
		const nok_phone = document.querySelector('.nok_phone');
		const nok_relation = document.querySelector('.nok_relation');
		const amount_to_pay = document.querySelector('.amount_to_pay');

		account_number.classList.add('d-none');
		destination_bank.classList.add('d-none');
		balance_before.classList.add('d-none');
		balance.classList.add('d-none');
		sender_phone.classList.add('d-none');
		transaction_status.classList.add('d-none');
		cash_source.classList.add('d-none');
		ussd.classList.add('d-none');
		amount.classList.add('d-none');
		amount_denos.classList.add('d-none');
		full_name.classList.add('d-none');
		dob.classList.add('d-none');
		house_number.classList.add('d-none');
		nearest_landmark.classList.add('d-none');
		id_type.classList.add('d-none');
		id_number.classList.add('d-none');
		next_of_king.classList.add('d-none');
		nok_phone.classList.add('d-none');
		nok_relation.classList.add('d-none');
		amount_to_pay.classList.add('d-none');

		// Show fields based on transaction type - use denomination calculator for cash transactions
		if (transactionTypeValue === 'Withdrawal (CIT)' || transactionTypeValue === 'Withdrawal (AIT)') {
			account_number.classList.remove('d-none');
			balance_before.classList.remove('d-none');
			balance.classList.remove('d-none');
			transaction_status.classList.remove('d-none');
			amount_denos.classList.remove('d-none');
		}else
		if (transactionTypeValue === 'Direct transfer (CIT)') {
			account_number.classList.remove('d-none');
			balance_before.classList.remove('d-none');
			const amountToPayWrap = document.querySelector('.amount_to_pay');
			if (amountToPayWrap) amountToPayWrap.classList.remove('d-none');
			transaction_status.classList.remove('d-none');
			amount_denos.classList.remove('d-none');
			sourceSelect.disabled = false;
		}else
		if (transactionTypeValue === 'CIT') {
			cash_source.classList.remove('d-none');
			transaction_status.classList.remove('d-none');
			amount.classList.remove('d-none');

			if (sourceValue === 'GhQR') {
				ussd.classList.remove('d-none');
			}
		}else
		if (transactionTypeValue === 'Mini statement') {
			account_number.classList.remove('d-none');
			id_type.classList.remove('d-none');
			id_number.classList.remove('d-none');
			transaction_status.classList.remove('d-none');
		}else
		if (transactionTypeValue === 'Balance enquiry') {
			account_number.classList.remove('d-none');
			id_type.classList.remove('d-none');
			id_number.classList.remove('d-none');
			transaction_status.classList.remove('d-none');
		}else
		if (transactionTypeValue === 'Account opening') {
			full_name.classList.remove('d-none');
			dob.classList.remove('d-none');
			house_number.classList.remove('d-none');
			id_type.classList.remove('d-none');
			id_number.classList.remove('d-none');
			next_of_king.classList.remove('d-none');
			nok_phone.classList.remove('d-none');
			nok_relation.classList.remove('d-none');
			transaction_status.classList.remove('d-none');
			const statusSelect = document.querySelector('select[name="transaction_status"]');
			if (statusSelect) {
				statusSelect.disabled = false;
			}
		}else
		if (transactionTypeValue === 'Funds transfer') {
			cash_source.classList.remove('d-none');
			amount.classList.remove('d-none');
			transaction_status.classList.remove('d-none');
		}else
		if (transactionTypeValue === 'Deposit') {
			account_number.classList.remove('d-none');
			balance_before.classList.remove('d-none');
			balance.classList.remove('d-none');
			transaction_status.classList.remove('d-none');
			amount_denos.classList.remove('d-none');
		}else
		if (transactionTypeValue === 'Interbank (gip)') {
			account_number.classList.remove('d-none');
			destination_bank.classList.remove('d-none');
			balance_before.classList.remove('d-none');
			amount.classList.remove('d-none');
			balance.classList.remove('d-none');
			sender_phone.classList.remove('d-none');
			transaction_status.classList.remove('d-none');
		}else{
			account_number.classList.add('d-none');
			destination_bank.classList.add('d-none');
			balance_before.classList.add('d-none');
			balance.classList.add('d-none');
			sender_phone.classList.add('d-none');
			transaction_status.classList.add('d-none');
			cash_source.classList.add('d-none');
			ussd.classList.add('d-none');
			amount.classList.add('d-none');
			full_name.classList.add('d-none');
			dob.classList.add('d-none');
			house_number.classList.add('d-none');
			nearest_landmark.classList.add('d-none');
			id_type.classList.add('d-none');
			id_number.classList.add('d-none');
			next_of_king.classList.add('d-none');
			nok_phone.classList.add('d-none');
			nok_relation.classList.add('d-none');
		}
	}

	function calculateCharge() {
		const amountInput = document.getElementById('amount');
		const chargeInput = document.getElementById('charge');
		const amount = parseFloat(amountInput.value);

		if (!isNaN(amount)) {				
			if (amount <= 50) {
				chargeInput.value = '0.50';
				chargeInput.innerText += chargeInput.value;
			} else {
				chargeInput.value = (amount * 0.01).toFixed(2); // 1% of amount
			}
		} else {
			chargeInput.value = (0 * 0.01).toFixed(2); // Clear charge if amount is not a valid number
			chargeInput.innerText += chargeInput.value;
		}
	}

	function calculateBalance() {
    const transactionType = document.getElementById('transaction_type').value;
    const balanceBefore = parseFloat(document.getElementById('balance_before').value) || 0;
    const charge = parseFloat(document.getElementById('charge').value) || 0;
    const balanceField = document.getElementById('balance');

    // Get amount from either denomination field (for withdrawals) or regular amount field
    let amount = 0;
    const denoAmountField = document.querySelector('.amount_denos input[name="amount"]');
    const regularAmountField = document.getElementById('amount');
    
    if (denoAmountField && !denoAmountField.parentElement.parentElement.classList.contains('d-none')) {
        amount = parseFloat(denoAmountField.value) || 0;
    } else if (regularAmountField && !regularAmountField.parentElement.classList.contains('d-none')) {
        amount = parseFloat(regularAmountField.value) || 0;
    }

    let newBalance = balanceBefore;

    // Adjust balance based on transaction type
    switch (transactionType) {
        case 'Deposit':
            newBalance = balanceBefore + amount - charge;
            break;
        case 'Withdrawal (CIT)':
        case 'Withdrawal (AIT)':
            newBalance = balanceBefore - amount - charge;
            break;
        case 'Direct transfer (CIT)':
            newBalance = balanceBefore - charge;
            break;
        case 'Funds transfer':
        case 'Interbank (gip)':
        case 'International transfer':
            newBalance = balanceBefore - amount - charge;
            break;
        default:
            // For other transaction types, keep balance unchanged or handle as needed
            newBalance = balanceBefore;
            break;
    }

    balanceField.value = newBalance.toFixed(2);
    const amountToPayField = document.getElementById('amount_to_pay');
    if (amountToPayField) {
        amountToPayField.value = newBalance.toFixed(2);
    }
}

// Initialize on page load
document.addEventListener("DOMContentLoaded", function () {
    //console.log("Existing Transaction Type:", existingTransactionType); // Debugging output
	    if (existingTransactionType) {
	        const options = Array.from(transactionType.options);

	        const matchingOption = options.find(option => {
	           // console.log("Comparing:", option.value.trim(), "with", existingTransactionType.trim());
	            return option.value.trim() === existingTransactionType.trim(); // Trim whitespace for exact match
	        });

	        if (matchingOption) {
	            transactionType.value = matchingOption.value; // Set the existing value
	           // console.log("Transaction Type Matched:", matchingOption.value);
	        } else {
	            console.warn("Transaction Type Value not found in options. Check for whitespace or encoding issues.");
	        }
	    }

	    updateCategoryOptions();
	    updateSourceOptions();
	    calculateBalance();
	});


	// Event listeners for real-time updates
	transactionType.addEventListener("change", updateCategoryOptions);
	transactionType.addEventListener("change", updateSourceOptions);
	const amountEl = document.getElementById('amount');
	if (amountEl) {
		amountEl.addEventListener('input', calculateCharge);
		amountEl.addEventListener('input', calculateBalance);
	}
	const balanceBeforeEl = document.getElementById('balance_before');
	if (balanceBeforeEl) {
		balanceBeforeEl.addEventListener('input', calculateBalance);
	}
	const chargeEl = document.getElementById('charge');
	if (chargeEl) {
		chargeEl.addEventListener('input', calculateBalance);
	}


	// Load existing denominations from backend
	let existingDenos = <?= json_encode($denoPayload) ?>;
	const existingAmount = <?= json_encode(old_value('amount', $row->amount ?? 0)) ?>;
	const hasExistingDenos = <?= $hasDenos ? 'true' : 'false' ?>;
	
	(function(){
		const hiddenDenos = document.getElementById('denos');
		const raw = hiddenDenos ? (hiddenDenos.value || '').trim() : '';
		if(!raw) return;
		try{
			const parsed = JSON.parse(raw);
			if(Array.isArray(parsed)) existingDenos = parsed;
		}catch(e){}
	})();
	
	function loadDenoModal() {
		const denoTableBody = document.getElementById('denoTableBody');
		const denominations = [200, 100, 50, 20, 10, 5, 2, 1, 0.5, 0.2, 0.1];
		const denoMap = {};
		
		// Build map from existing denominations
		existingDenos.forEach(deno => {
			const key = Number(deno.denomination);
			denoMap[key] = {
				count: Number(deno.count) || 0,
				volume: Number(deno.volume) || 0
			};
		});
		
		// Build table rows
		denoTableBody.innerHTML = '';
		denominations.forEach(den => {
			const row = document.createElement('tr');
			row.setAttribute('data-denomination', den);
			const label = den >= 1 ? `GH₵${den}` : `${(den * 100).toFixed(0)}p`;
			const count = denoMap[den]?.count || 0;
			const volume = denoMap[den]?.volume || 0;
			
			row.innerHTML = `
				<td class="px-5">${label}</td>
				<td><input type="number" class="form-control p-1 count-input" value="${count}" placeholder="Enter count"></td>
				<td><input type="text" class="form-control p-1 volume-input" value="${volume.toFixed(2)}" placeholder="Volume" readonly></td>
			`;
			denoTableBody.appendChild(row);
		});
		
		// Attach listeners for count inputs
		document.querySelectorAll('.count-input').forEach(input => {
			input.addEventListener('input', calculateDenoVolume);
		});
		
		calculateDenoVolume();
		
		// Populate the amount field with existing amount
		const amountField = document.querySelector('.amount_denos input[name="amount"]');
		if (amountField && existingAmount > 0) {
			amountField.value = parseFloat(existingAmount).toFixed(2);
		}

		if(hasExistingDenos){
			const hiddenDenos = document.getElementById('denos');
			if(hiddenDenos){
				hiddenDenos.value = JSON.stringify(existingDenos);
			}
		}
	}
	
	function calculateDenoVolume() {
		let totalCount = 0;
		let totalVolume = 0;
		
		document.querySelectorAll('tr[data-denomination]').forEach(row => {
			const den = parseFloat(row.getAttribute('data-denomination'));
			const count = parseInt(row.querySelector('.count-input').value) || 0;
			const volume = den * count;
			row.querySelector('.volume-input').value = volume.toFixed(2);
			totalCount += count;
			totalVolume += volume;
		});
		
		document.getElementById('total-count').value = totalCount;
		document.getElementById('total-volume').value = totalVolume.toFixed(2);
		
		// Update the denomination amount field (for withdrawals)
		const denoAmountField = document.querySelector('.amount_denos input[name="amount"]');
		if (denoAmountField) {
			denoAmountField.value = totalVolume.toFixed(2);
		}
	}
	
	// Modal submit handler
	const hiddenDenosInput = document.getElementById('denos');
	const amountToPayError = document.getElementById('amount_to_pay-error');
	const denoAmountInput = document.getElementById('deno_amount');
	const amountToPayInput = document.getElementById('amount_to_pay');
	const saveBtn = document.querySelector('form button.btn.btn-danger');

	function validateDirectTransferMatch() {
		if (<?= $isApprovedStatusOnlyEdit ? 'true' : 'false' ?>) return;
		if (!transactionType) return;
		if (transactionType.value !== 'Direct transfer (CIT)') return;
		if (!denoAmountInput || !amountToPayInput) return;
		const paidOut = parseFloat(denoAmountInput.value) || 0;
		const toPay = parseFloat(amountToPayInput.value) || 0;
		const mismatch = (Math.abs(paidOut - toPay) > 0.01);
		if (amountToPayError) {
			amountToPayError.textContent = mismatch ? 'Amount paid out must be equal to amount to pay' : '';
		}
		if (saveBtn) {
			saveBtn.disabled = mismatch;
		}
	}
	
	document.querySelector('#denoModal .submit').addEventListener('click', function() {
		const denoData = [];
		document.querySelectorAll('tr[data-denomination]').forEach((row, rowIndex) => {
			const den = parseFloat(row.getAttribute('data-denomination'));
			const count = parseInt(row.querySelector('.count-input').value) || 0;
			if (count > 0) {
				denoData.push({
					row_index: rowIndex,
					denomination: den,
					count: count,
					currency: 'GHS',
					volume: (den * count)
				});
			}
		});

		if(hiddenDenosInput){
			hiddenDenosInput.value = JSON.stringify(denoData);
		}
		const modal = bootstrap.Modal.getInstance(document.getElementById('denoModal'));
		modal.hide();
		calculateBalance();
		validateDirectTransferMatch();
	});
	
	document.querySelector('#denoModal .reset').addEventListener('click', function() {
		document.querySelectorAll('.count-input').forEach(input => input.value = '');
		calculateDenoVolume();
	});
	
	// Initialize on page load
	loadDenoModal();
	calculateBalance();
	validateDirectTransferMatch();
	if (chargeEl) {
		chargeEl.addEventListener('input', validateDirectTransferMatch);
	}
	if (balanceBeforeEl) {
		balanceBeforeEl.addEventListener('input', validateDirectTransferMatch);
	}
	if (sourceSelect) {
		sourceSelect.addEventListener('change', validateDirectTransferMatch);
	}

	(function(){
		const formEl = document.querySelector('form');
		const inputEl = document.getElementById('transaction_image');
		const cardEl = document.getElementById('transactionImagePreviewCard');
		const previewEl = document.getElementById('transactionImagePreview');
		const placeholderEl = document.getElementById('transactionImagePreviewPlaceholder');
		const errorEl = document.querySelector('.js-transaction-image-error');
		if(!formEl || !inputEl || !cardEl || !previewEl || !placeholderEl) return;

		const setPreviewState = (hasImage) => {
			placeholderEl.style.display = hasImage ? 'none' : 'flex';
			previewEl.style.display = hasImage ? 'block' : 'none';
		};

		setPreviewState(!!(previewEl.getAttribute('src') || '').trim());

		cardEl.addEventListener('click', function(){
			inputEl.click();
		});
		cardEl.addEventListener('keydown', function(e){
			if (e.key === 'Enter' || e.key === ' ') {
				e.preventDefault();
				inputEl.click();
			}
		});

		inputEl.addEventListener('change', function(){
			const file = (this.files && this.files[0]) ? this.files[0] : null;
			if(!file){
				if ((previewEl.getAttribute('src') || '').trim() !== '') {
					setPreviewState(true);
					return;
				}
				previewEl.src = '';
				setPreviewState(false);
				return;
			}
			if (!file.type || !file.type.startsWith('image/')) {
				this.value = '';
				previewEl.src = '';
				setPreviewState(false);
				if(errorEl) errorEl.textContent = 'Invalid file type. Please select an image.';
				return;
			}
			const reader = new FileReader();
			reader.onload = function(ev){
				previewEl.src = ev.target.result;
				setPreviewState(true);
				if(errorEl) errorEl.textContent = '';
			};
			reader.readAsDataURL(file);
		});
	})();
</script>
<script>
	(function(){
		if (<?= $isApprovedStatusOnlyEdit ? 'true' : 'false' ?>) {
			return;
		}
		const statusSelect = document.querySelector('select[name="transaction_status"]');
		const reasonGroup = document.getElementById('agencyRejectionReasonGroup');
		const reasonInput = document.getElementById('rejection_reason');
		const reasonError = document.querySelector('.js-agency-rejection-reason-error');
		const form = document.querySelector('form');
		function syncReason(){
			const status = String(statusSelect?.value || '').trim().toLowerCase();
			const needsReason = status === 'declined' || status === 'rejected';
			const hasReason = String(reasonInput?.value || '').trim() !== '';
			if(reasonGroup) reasonGroup.classList.toggle('d-none', !needsReason);
			if(reasonInput) reasonInput.toggleAttribute('required', needsReason);
			if(reasonError) reasonError.textContent = needsReason && !hasReason ? 'Rejection reason is required.' : '';
			return !needsReason || hasReason;
		}
		statusSelect?.addEventListener('change', syncReason);
		reasonInput?.addEventListener('input', syncReason);
		form?.addEventListener('submit', function(event){
			if(!syncReason()){
				event.preventDefault();
			}
		});
		syncReason();
	})();
</script>
