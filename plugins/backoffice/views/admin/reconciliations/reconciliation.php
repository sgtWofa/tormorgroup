<?php if(user_can('view_backoffice')): ?>

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

		.input-group-text {

			font-weight: bolder;

		}

		.form-label {

			color: gray;

			font-size: 12px;

		}

		.network-group {

			font-weight: bold;

			text-transform: uppercase;

		}

		.recon-proof-thumb {

			width: 46px;

			height: 38px;

			border-radius: 10px;

			overflow: hidden;

			position: relative;

			display: inline-flex;

			align-items: center;

			justify-content: center;

			background: rgba(0,0,0,0.06);

			border: 1px solid rgba(0,0,0,0.12);

		}

		.recon-proof-thumb:hover {

			filter: brightness(0.98);

			border-color: rgba(13,110,253,0.35);

		}

		.recon-proof-thumb img {

			width: 100%;

			height: 100%;

			object-fit: cover;

			display: block;

		}

		.recon-proof-placeholder {

			color: rgba(0,0,0,0.55);

			font-size: 14px;

			line-height: 1;

		}

		.recon-required-row td {

			background: rgba(255, 193, 7, 0.08);

		}

		.recon-required-badge {

			display: inline-block;

			font-size: 10px;

			font-weight: 700;

			padding: 2px 6px;

			border-radius: 999px;

			background: rgba(255, 193, 7, 0.22);

			color: #7a5200;

			vertical-align: middle;

			margin-left: 6px;

		}

		/* Progress Overlay Styles */
		.progress-overlay {
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background: rgba(0, 0, 0, 0.7);
			backdrop-filter: blur(4px);
			display: none;
			justify-content: center;
			align-items: center;
			z-index: 99999;
			flex-direction: column;
		}

		.progress-overlay.active {
			display: flex;
		}

		.progress-container {
			background: var(--bs-body-bg);
			border-radius: 16px;
			padding: 40px;
			text-align: center;
			box-shadow: 0 20px 60px rgba(0,0,0,0.3);
			max-width: 400px;
			width: 90%;
		}

		.progress-spinner {
			width: 80px;
			height: 80px;
			border: 4px solid var(--bs-border-color);
			border-top-color: var(--bs-primary);
			border-radius: 50%;
			animation: spin 1s linear infinite;
			margin: 0 auto 20px;
		}

		.progress-spinner.pulse {
			animation: spin 1s linear infinite, pulse 1.5s ease-in-out infinite;
		}

		@keyframes spin {
			to { transform: rotate(360deg); }
		}

		@keyframes pulse {
			0%, 100% { opacity: 1; }
			50% { opacity: 0.7; }
		}

		.progress-title {
			font-size: 1.25rem;
			font-weight: 600;
			margin-bottom: 10px;
			color: var(--bs-body-color);
		}

		.progress-status {
			font-size: 0.9rem;
			color: var(--bs-secondary);
			margin-bottom: 20px;
		}

		.progress-bar-container {
			width: 100%;
			height: 8px;
			background: var(--bs-secondary-bg);
			border-radius: 4px;
			overflow: hidden;
			margin-bottom: 10px;
		}

		.progress-bar-fill {
			height: 100%;
			background: linear-gradient(90deg, var(--bs-primary), var(--bs-info));
			border-radius: 4px;
			width: 0%;
			transition: width 0.3s ease;
		}

		.progress-percentage {
			font-size: 0.85rem;
			font-weight: 600;
			color: var(--bs-primary);
		}

		[data-bs-theme="dark"] .recon-required-row td {

			background: rgba(255, 193, 7, 0.26) !important;

		}

		[data-bs-theme="dark"] .recon-required-badge {

			font-size: 11px !important;

			background: #ffc107 !important;

			color: #000 !important;

			border: 1px solid rgba(255, 193, 7, 0.95) !important;

			box-shadow: 0 0 0 2px rgba(255, 193, 7, 0.18) !important;

		}

		@media (prefers-color-scheme: dark) {

			.recon-required-row td {

				background: rgba(255, 193, 7, 0.26) !important;

			}

			.recon-required-badge {

				font-size: 11px !important;

				background: #ffc107 !important;

				color: #000 !important;

				border: 1px solid rgba(255, 193, 7, 0.95) !important;

				box-shadow: 0 0 0 2px rgba(255, 193, 7, 0.18) !important;

			}

		}

	</style>

	<?php if(!empty($lateReconciliationAccess)): ?>
		<div class="alert alert-info" data-attention-sound="1" data-attention-key="late-reconciliation-open-<?=esc((string)($lateReconciliationAccess->id ?? 0))?>">
			<div class="fw-semibold">Past cashdrawer reconciliation access</div>
			<div>You are reconciling the cashdrawer from <?=esc(get_date($lateReconciliationAccess->cashdrawer_date ?? $lateReconciliationAccess->drawer_date ?? ''))?>.</div>
		</div>
	<?php endif; ?>

	<form method="post" enctype="multipart/form-data" id="reconciliationForm" data-reconciliation-locked="<?=!empty($reconciliationLocked) ? '1' : '0'?>">

		<div class="row g-3 col-md-12 col-md-10 col-lg-8 mx-auto shadow p-4 rounded">

			

			<?=csrf()?>
			<?php if(!empty($lateReconciliationAccess)): ?>
				<input type="hidden" name="late_access_id" value="<?=esc((string)($lateReconciliationAccess->id ?? 0))?>">
			<?php endif; ?>

			<?php

				$errors = get_value('errors');

				if (!is_array($errors)) {

					$errors = [];

				}

				set_value('errors', null);

				

				$reconOld = get_value('recon_old');

				$reconOldRows = is_array($reconOld) && !empty($reconOld['rows']) && is_array($reconOld['rows']) ? $reconOld['rows'] : [];

				$reconOldMap = [];

				foreach ($reconOldRows as $oldRow) {

					$oldChannel = (string)($oldRow['channel_id'] ?? '');

					if ($oldChannel === '') {

						continue;

					}

					$reconOldMap[$oldChannel] = $oldRow;

				}

			?>



			<h4 class="text-center">

				<span><img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:60px;max-height: 60px;object-fit: cover;"></span>

				Reconciliation

			</h4>

			<?php $isPettyCashCompletionLock = stripos((string)($reconciliationLockMessage ?? ''), 'Complete all acknowledged petty cash transactions') === 0; ?>
			<?php if(!empty($reconciliationLocked) && !$isPettyCashCompletionLock): ?>
				<div class="alert alert-warning" role="alert">
					<div class="fw-bold"><?=esc($reconciliationLockTitle ?? 'Reconciliation unavailable')?></div>
					<div><?=esc($reconciliationLockMessage ?: 'Admin must reject your first reconciliation with a reason before you can redo it.')?></div>
					<?php if(!empty($activeReconciliation->id)): ?>
						<a class="btn btn-sm btn-outline-primary mt-2" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view_reconciliation/<?=esc((string)$activeReconciliation->id)?>">
							View submitted reconciliation
						</a>
					<?php elseif(strtolower((string)($reconciliationLockTitle ?? '')) === 'outstanding e-cash balances'): ?>
						<a class="btn btn-sm btn-outline-primary mt-2" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/make_payment?recon_payment=1">
							Initiate Backoffice Payment
						</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>



			<!-- Modal Body -->

			<div class="table-responsive">

				<table class="table table-hover table-sm">

					<thead>

						<tr>

							<th>DESCRIPTION</th>

							<th>BALANCE</th>

						</tr>

					</thead>

					<tbody>

						<?php if (!empty($reconChannels)): $id = 0;?>

							<?php

							$grouped_channels = [];

							$globalIndex = 0;

							foreach ($reconChannels as $row) {

								if (is_object($row)) {
									$row = get_object_vars($row);
								}
								if (!is_array($row)) {
									continue;
								}

								$grouped_channels[$row['group'] ?? 'Other'][] = $row;

							}

							?>



							<?php if(empty($grouped_channels)): ?>

								<tr><td colspan="2" class="text-center justify-content-center">No reconciliation channels are available for today.</td></tr>

							<?php else: ?>

							<?php foreach ($grouped_channels as $group => $channels): ?>

								<tr>

									<td colspan="2" class="network-group text-muted"><?=esc($group)?>:</td>

								</tr>

								<?php foreach ($channels as $row): $id++;?>

									<?php

										$channelId = (string)($row['channel_id'] ?? '');

										$isCommission = (!empty($row['is_commission']) || strpos($channelId, 'commission:') === 0);

										$isCash = (strpos($channelId, 'cash:') === 0);

										$cashCurrency = $isCash ? strtoupper(trim(substr($channelId, 5))) : '';

										$isBankChannel = !empty($row['is_bank']);

										$isPrivileged = !empty($isReconPrivileged);

										$prefillBank = (!$isPrivileged && $isBankChannel);

										$displayLabel = '';

										if (strpos($channelId, 'cash:') === 0) {

											$displayLabel = strtoupper(substr($channelId, 5));

										} elseif (strpos($channelId, 'ecash:') === 0) {

											$displayLabel = strtoupper(substr($channelId, 6));

										} elseif (strpos($channelId, 'bank:') === 0) {

											$displayLabel = strtoupper(substr($channelId, 5));

										} else {

											$displayLabel = (string)($row['label'] ?? '');

										}

										$expectedBalance = (float)($row['expected'] ?? 0);

										$cashRequired = ($isCash && abs($expectedBalance) >= 0.01);

										$forceOldDrawerRequired = (!empty($lateReconciliationAccess) && !$isCash && !$isCommission && abs($expectedBalance) >= 0.01);
										if ($forceOldDrawerRequired) {
											$prefillBank = false;
										}

										$isBalanceRequired = $cashRequired;

										$hasNonZeroExpected = (abs($expectedBalance) >= 0.01);
										if ($forceOldDrawerRequired) {
											$hasNonZeroExpected = true;
										}
										if ($isCommission) {
											$hasNonZeroExpected = (abs($expectedBalance) >= 0.01);
										}

										$showRequiredIndicator = (abs($expectedBalance) >= 0.01);

										$oldRow = $reconOldMap[$channelId] ?? [];

										$oldBalance = isset($oldRow['balance']) ? (string)$oldRow['balance'] : '';

										$oldDenos = isset($oldRow['denos_json']) ? (string)$oldRow['denos_json'] : '';

										$oldExistingProof = isset($oldRow['existing_proof']) ? (string)$oldRow['existing_proof'] : '';

										$balanceErrorKey = 'balance_' . (string)$globalIndex;

										$proofErrorKey = 'proof_image_' . (int)$globalIndex;

										$balanceInvalidClass = !empty($errors[$balanceErrorKey]) ? 'is-invalid' : '';

										$proofInvalidClass = !empty($errors[$proofErrorKey]) ? 'border border-danger' : '';

									?>

									<tr class="<?= $showRequiredIndicator ? 'recon-required-row' : '' ?>">

										<td style="white-space: nowrap;" class="px-1">

											<?=esc($displayLabel)?>

											<?php if ($showRequiredIndicator): ?>

												<span class="recon-required-badge">Required</span>

											<?php endif; ?>

											<input type="text" name="rows[<?= $globalIndex ?>][channel_id]" class="d-none" value="<?=esc($row['channel_id'] ?? '')?>">

										</td>

										<td>

											<?php if($isCash): ?>

												<div class="input-group" data-bs-toggle="modal" data-bs-target="#reconDenoModal" data-recon-currency="<?=esc($cashCurrency)?>" data-recon-index="<?= (int)$globalIndex ?>" style="cursor:pointer;">

													<span class="input-group-text"><i class="fa-solid fa-calculator text-warning"></i></span>

													<input type="number" step="0.01" name="rows[<?= $globalIndex ?>][balance]" class="form-control p-1 balance-input recon-cash-input <?=$balanceInvalidClass?>" id="recon_balance_<?= (int)$globalIndex ?>" placeholder="Use calculator" value="<?=esc($oldBalance)?>" readonly <?=$cashRequired ? 'required' : ''?>>

												</div>

												<input type="hidden" name="rows[<?= $globalIndex ?>][denos_json]" id="recon_denos_<?= (int)$globalIndex ?>" value="<?=esc($oldDenos)?>">

											<?php elseif($isCommission): ?>

												<?php
													$balanceValue = $oldBalance;
													if ($balanceValue === '') {
														$balanceValue = (string)$expectedBalance;
													}
												?>

												<input type="number" step="0.01" name="rows[<?= $globalIndex ?>][balance]" class="form-control p-1 balance-input <?=$balanceInvalidClass?>" id="recon_balance_<?= (int)$globalIndex ?>" placeholder="Auto-added commissions" value="<?=esc($balanceValue)?>" readonly <?=$hasNonZeroExpected ? 'required' : ''?>>
												<small class="text-muted">Auto-added from today's commissions.</small>

											<?php else: ?>

												<div class="d-flex align-items-center gap-2">

													<?php

														$balanceValue = $oldBalance;

														if ($balanceValue === '' && $prefillBank) {

															$balanceValue = (string)$expectedBalance;

														}

													?>

													<?php $balanceIsRequired = $hasNonZeroExpected; ?>

													<input type="number" step="0.01" name="rows[<?= $globalIndex ?>][balance]" class="form-control p-1 balance-input <?=$balanceInvalidClass?>" id="recon_balance_<?= (int)$globalIndex ?>" placeholder="Enter current balance" value="<?=esc($balanceValue)?>" <?=$prefillBank ? 'readonly' : ''?> <?=$balanceIsRequired ? 'required' : ''?>>

													<input type="hidden" name="rows[<?= $globalIndex ?>][existing_proof]" value="<?=esc($oldExistingProof)?>" />

													<input type="file" name="proof_image_<?= (int)$globalIndex ?>" class="d-none proof-input" accept="image/*" >

													<button type="button" class="recon-proof-thumb <?=$proofInvalidClass?>" data-proof-index="<?= (int)$globalIndex ?>" aria-label="Upload proof image">

														<?php $hasOldProof = ($oldExistingProof !== ''); ?>

														<img class="<?=$hasOldProof ? '' : 'd-none'?>" alt="Proof preview" <?=$hasOldProof ? 'src="'.esc($oldExistingProof).'"' : ''?> />

														<span class="recon-proof-placeholder <?=$hasOldProof ? 'd-none' : ''?>"><i class="fa-solid fa-camera"></i></span>

													</button>

												</div>

												<?php if(!empty($errors[$proofErrorKey])): ?>

													<small class="text-danger"><?=$errors[$proofErrorKey]?></small>

												<?php endif ?>

											<?php endif; ?>

											<?php if(!empty($errors[$balanceErrorKey])): ?>

												<small class="text-danger"><?=$errors[$balanceErrorKey]?></small>

											<?php endif ?>

											<?php if(!empty($errors['balance'])): ?>

											  <small class="text-danger"><?=$errors['balance']?></small>

											<?php endif ?>

										</td>

									</tr>

									<?php $globalIndex++; ?>

							<?php endforeach; ?>

						<?php endforeach; ?>

						<?php endif; ?>

						<?php else:?>

								<tr><td colspan="2" class="text-center justify-content-center">No records to show!</td></tr>

						<?php endif; ?>

					</tbody>

					<tfoot>

						<tr>

							<th class="px-1 ">TOTAL ENTERED</th>

							<th class="">

								<input type="text" class="form-control p-1" id="total-balance" style="border:none;" placeholder="Total balance" readonly>

							</th>

						</tr>

					</tfoot>

				</table>

				<div class="text-muted" style="font-size: 12px; margin-top: 6px;">

					Submit only after you have filled all required inputs.

					If your totals do not match the system cashdrawer totals, the difference will be recorded automatically as <b>Miscellaneous</b>

					(and classified as a <b>Shortage</b> or an <b>Overage</b>).

				</div>

			</div>



			<div class="d-flex justify-content-between" style="margin-top:10px;">

				<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">

					<button type="button" class="btn btn-danger">

						<i class="fa-solid fa-chevron-left"></i> Exit

					</button>

				</a>

				<button class="btn btn-primary" type="submit" id="submit">

					<i class="fa-solid fa-save"></i> Submit

				</button>

			</div>

		</div>

	</form>

	<!-- Progress Overlay for Form Submission -->
	<div class="progress-overlay" id="progressOverlay">
		<div class="progress-container">
			<div class="progress-spinner pulse" id="progressSpinner"></div>
			<div class="progress-title">Submitting Reconciliation...</div>
			<div class="progress-status" id="progressStatus">Preparing data...</div>
			<div class="progress-bar-container">
				<div class="progress-bar-fill" id="progressBarFill"></div>
			</div>
			<div class="progress-percentage" id="progressPercentage">0%</div>
		</div>
	</div>



	<div class="modal fade" id="reconDenoModal" tabindex="-1" aria-hidden="true">

	  <div class="modal-dialog modal-dialog-centered">

		<div class="modal-content">

		  <div class="modal-header">

			<h5 class="modal-title" id="reconDenoTitle">Currency Notes</h5>

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

			  <tbody id="reconDenoTableBody"></tbody>

			  <tfoot>

				<tr>

				  <th>Total</th>

				  <th><input type="text" class="form-control form-control-sm" id="reconDenoCount" readonly /></th>

				  <th><input type="text" class="form-control form-control-sm" id="reconDenoTotal" readonly /></th>

				</tr>

			  </tfoot>

			</table>

		  </div>

		  <div class="modal-footer">

			<button type="button" class="btn btn-outline-secondary" id="reconDenoReset">Reset</button>

			<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>

			<button type="button" class="btn btn-primary" id="reconDenoApply">Apply</button>

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
<?=get_app_image_cropper_inline_source()?>

document.addEventListener("DOMContentLoaded", function () {

	// ===== PROGRESS OVERLAY FUNCTIONALITY =====
	const progressOverlay = document.getElementById('progressOverlay');
	const progressBarFill = document.getElementById('progressBarFill');
	const progressPercentage = document.getElementById('progressPercentage');
	const progressStatus = document.getElementById('progressStatus');
	const reconciliationForm = document.querySelector('form');

	const showProgress = () => {
		progressOverlay.classList.add('active');
		document.body.style.overflow = 'hidden';
	};

	const hideProgress = () => {
		progressOverlay.classList.remove('active');
		document.body.style.overflow = '';
	};

	const updateProgress = (percent, status) => {
		progressBarFill.style.width = percent + '%';
		progressPercentage.textContent = percent + '%';
		if (status) {
			progressStatus.textContent = status;
		}
	};

	// Simulate progress steps during form submission
	const simulateProgress = () => {
		const steps = [
			{ percent: 10, status: 'Preparing reconciliation data...', delay: 100 },
			{ percent: 25, status: 'Validating balances...', delay: 300 },
			{ percent: 40, status: 'Processing channel entries...', delay: 500 },
			{ percent: 60, status: 'Uploading proof images...', delay: 700 },
			{ percent: 80, status: 'Saving to database...', delay: 900 },
			{ percent: 95, status: 'Finalizing...', delay: 1100 }
		];

		steps.forEach(step => {
			setTimeout(() => {
				updateProgress(step.percent, step.status);
			}, step.delay);
		});
	};

	// Intercept form submission
	if (reconciliationForm) {
		reconciliationForm.addEventListener('submit', function(e) {
			// Check if form is valid and has data
			const balanceInputs = document.querySelectorAll('.balance-input');
			const hasBalances = Array.from(balanceInputs).some(input => {
				return input.value.trim() !== '' && Number.isFinite(Number(input.value));
			});

			// Only show progress if we have data to submit
			if (hasBalances) {
				showProgress();
				simulateProgress();
				// Form will submit normally, progress shows until page reloads
			}
		});
	}

	// ===== EXISTING RECONCILIATION LOGIC =====
  const balanceInputs = document.querySelectorAll(".balance-input");

  const proofInputs = document.querySelectorAll('.proof-input');

	const proofThumbs = document.querySelectorAll('.recon-proof-thumb');

  const totalBalanceField = document.getElementById("total-balance");

  const submitBtn = document.getElementById("submit");

	const denoModal = document.getElementById('reconDenoModal');

	const denoBody = document.getElementById('reconDenoTableBody');

	const denoCount = document.getElementById('reconDenoCount');

	const denoTotal = document.getElementById('reconDenoTotal');

	const denoApply = document.getElementById('reconDenoApply');

	const denoReset = document.getElementById('reconDenoReset');

	const denoTitle = document.getElementById('reconDenoTitle');

	let currentReconIndex = null;

	let currentReconCurrency = 'GHS';



  // Disable submit button initially

  submitBtn.disabled = true;

	const reconciliationLocked = !!(reconciliationForm && reconciliationForm.getAttribute('data-reconciliation-locked') === '1');
	if (reconciliationLocked) {
		balanceInputs.forEach(input => {
			input.readOnly = true;
			input.disabled = true;
		});
		proofInputs.forEach(input => {
			input.disabled = true;
		});
		proofThumbs.forEach(btn => {
			btn.disabled = true;
			btn.style.pointerEvents = 'none';
			btn.style.opacity = '0.55';
		});
		if (submitBtn) {
			submitBtn.disabled = true;
		}
		return;
	}



  balanceInputs.forEach(input => {

    input.addEventListener("input", () => {

      updateTotals();

      toggleSubmitButton();

    });

  });

	function setProofPreviewState(img, placeholder, src) {
		const hasSrc = String(src || '').trim() !== '';
		if (img) {
			if (hasSrc) {
				img.src = src;
				img.classList.remove('d-none');
				img.style.display = 'block';
			} else {
				img.removeAttribute('src');
				img.classList.add('d-none');
				img.style.display = 'none';
			}
		}
		if (placeholder) {
			placeholder.classList.toggle('d-none', hasSrc);
			placeholder.style.display = hasSrc ? 'none' : '';
		}
	}

	function previewProofFile(input, img, placeholder) {
		if (!input.files || !input.files[0] || !img) {
			setProofPreviewState(img, placeholder, '');
			return;
		}
		const reader = new FileReader();
		reader.onload = function (e) {
			setProofPreviewState(img, placeholder, String(e.target && e.target.result ? e.target.result : ''));
		};
		reader.readAsDataURL(input.files[0]);
	}



	proofInputs.forEach(input => {

		input.addEventListener('change', () => {

			const name = input.getAttribute('name') || '';

			const match = name.match(/proof_image_(\d+)/);

			const idx = match ? match[1] : null;

			if (idx !== null) {

				const thumb = document.querySelector('.recon-proof-thumb[data-proof-index="' + idx + '"]');

				if (thumb) {

					const img = thumb.querySelector('img');

					const placeholder = thumb.querySelector('.recon-proof-placeholder');

					previewProofFile(input, img, placeholder);

					if (window.AppImageCropper && typeof window.AppImageCropper.handleLegacyChange === 'function') {
						window.AppImageCropper.handleLegacyChange({ currentTarget: input }, {
							previewEl: img,
							placeholderEl: placeholder,
							maxBytes: window.AppImageCropper.MAX_BYTES,
							modalTitle: 'Crop reconciliation proof',
							errorTitle: 'Invalid proof image',
						}).then(function(){
							if (img && String(img.getAttribute('src') || '').trim() !== '') {
								setProofPreviewState(img, placeholder, img.getAttribute('src'));
							}
							toggleSubmitButton();
						}).catch(function(){
							previewProofFile(input, img, placeholder);
							toggleSubmitButton();
						});
						return;
					}

				}

			}

			toggleSubmitButton();

		});

	});



	proofThumbs.forEach((thumb) => {

		thumb.addEventListener('click', function(){

			const idx = thumb.getAttribute('data-proof-index');

			if (!idx) return;

			const input = document.querySelector('input[name="proof_image_' + idx + '"]');

			if (input) input.click();

		});

	});



	const ghanaDenoms = [200,100,50,20,10,5,2,1,0.5,0.2,0.1];

	const cfaDenoms = [10000,5000,2000,1000,500,200,100,50,20,10,5,2,1];



	function buildReconDenoRows(currency, presetCounts){

		if(!denoBody) return;

		const fragment = document.createDocumentFragment();

		const denoms = currency === 'CFA' ? cfaDenoms : ghanaDenoms;

		denoBody.innerHTML = '';

		denoms.forEach(value => {

			const formattedValue = Number.isInteger(value) ? value : value.toFixed(2);

			const presetKey = String(value);

			const tr = document.createElement('tr');

			tr.dataset.denomination = value;

			tr.dataset.currency = currency;

			tr.innerHTML = `

				<td>${currency === 'CFA' ? 'CFA' : 'GH\u00A2'}${formattedValue}</td>

				<td><input type="number" min="0" class="form-control form-control-sm deno-count" /></td>

				<td><input type="text" class="form-control form-control-sm deno-volume" readonly /></td>

			`;

			const countInput = tr.querySelector('.deno-count');

			if (countInput && presetCounts && Object.prototype.hasOwnProperty.call(presetCounts, presetKey)) {

				countInput.value = String(presetCounts[presetKey] ?? '');

			}

			fragment.appendChild(tr);

		});

		denoBody.appendChild(fragment);

		updateReconDenoTotals();

	}



	function updateReconDenoTotals(){

		if(!denoBody || !denoCount || !denoTotal) return;

		let total = 0;

		let countTotal = 0;

		denoBody.querySelectorAll('tr').forEach((tr) => {

			const denom = parseFloat(tr.dataset.denomination) || 0;

			const countInput = tr.querySelector('.deno-count');

			const volInput = tr.querySelector('.deno-volume');

			const count = parseFloat(countInput && countInput.value ? countInput.value : 0) || 0;

			const vol = denom * count;

			if (volInput) volInput.value = vol.toFixed(2);

			total += vol;

			countTotal += count;

		});

		denoCount.value = String(countTotal);

		denoTotal.value = total.toFixed(2);

	}



	if(denoBody){

		denoBody.addEventListener('input', function(e){

			if(e.target && e.target.classList.contains('deno-count')){

				updateReconDenoTotals();

			}

		});

	}



	if(denoReset){

		denoReset.addEventListener('click', function(){

			if(!denoBody) return;

			denoBody.querySelectorAll('.deno-count').forEach((inp) => inp.value = '');

			updateReconDenoTotals();

		});

	}



	if(denoApply){

		denoApply.addEventListener('click', function(){

			if(currentReconIndex === null) return;

			const target = document.getElementById('recon_balance_' + currentReconIndex);

			if(target){

				target.value = (denoTotal && denoTotal.value) ? denoTotal.value : '';

				target.dispatchEvent(new Event('input', { bubbles: true }));

			}

			const denosField = document.getElementById('recon_denos_' + currentReconIndex);

			if(denosField && denoBody){

				const payload = [];

				denoBody.querySelectorAll('tr').forEach((tr) => {

					const denom = parseFloat(tr.dataset.denomination) || 0;

					const countInput = tr.querySelector('.deno-count');

					const count = parseFloat(countInput && countInput.value ? countInput.value : 0) || 0;

					if(!countInput || countInput.value.trim() === '') return;

					payload.push({

						currency: currentReconCurrency,

						denomination: denom,

						count: count,

						volume: denom * count,

					});

				});

				denosField.value = JSON.stringify(payload);

			}



			if (denoModal && window.bootstrap && typeof window.bootstrap.Modal === 'function') {

				window.bootstrap.Modal.getOrCreateInstance(denoModal).hide();

			}

		});

	}



	if(denoModal){

		denoModal.addEventListener('show.bs.modal', function(e){

			const trigger = e.relatedTarget;

			if(!trigger) return;

			const currency = (trigger.getAttribute('data-recon-currency') || 'GHS').toUpperCase();

			const index = trigger.getAttribute('data-recon-index');

			currentReconCurrency = (currency === 'CFA') ? 'CFA' : 'GHS';

			currentReconIndex = (index !== null) ? String(index) : null;

			if(denoTitle) denoTitle.textContent = currentReconCurrency + ' Notes';



			let presetCounts = {};

			if (currentReconIndex !== null) {

				const denosField = document.getElementById('recon_denos_' + currentReconIndex);

				const raw = denosField && denosField.value ? String(denosField.value) : '';

				if (raw.trim() !== '') {

					try {

						const parsed = JSON.parse(raw);

						if (Array.isArray(parsed)) {

							parsed.forEach((x) => {

								const denomKey = String(x && x.denomination !== undefined ? x.denomination : '');

								const cnt = parseFloat(x && x.count !== undefined ? x.count : 0) || 0;

								if (denomKey !== '' && cnt > 0) {

									presetCounts[denomKey] = cnt;

								}

							});

						}

					} catch (err) {

						presetCounts = {};

					}

				}

			}



			buildReconDenoRows(currentReconCurrency, presetCounts);

		});

	}



  function updateTotals() {

    let totalBalance = 0;

    balanceInputs.forEach(input => {

      const balance = input.value.trim() === '' ? 0 : (parseFloat(input.value) || 0);

      totalBalance += balance;

    });



    totalBalanceField.value = totalBalance.toFixed(2);

  }



  function toggleSubmitButton() {

		const requiredBalancesFilled = Array.from(balanceInputs).every(input => (!input.required) || (String(input.value || '').trim() !== ""));

		const proofsOk = Array.from(proofInputs).every(input => {

			const name = input.getAttribute('name') || '';

			const match = name.match(/proof_image_(\d+)/);

			const idx = match ? match[1] : null;

			if (idx === null) return true;

			const bal = document.getElementById('recon_balance_' + idx);

			const balanceFilled = !!(bal && String(bal.value || '').trim() !== '');

			if (!balanceFilled) return true;



			const existing = document.querySelector('input[name="rows[' + idx + '][existing_proof]"]');

			const hasExisting = !!(existing && String(existing.value || '').trim() !== '');

			return hasExisting || (input.files && input.files.length > 0);

		});

	submitBtn.disabled = !(requiredBalancesFilled && proofsOk);

	}



	updateTotals();

	toggleSubmitButton();

});



</script>
