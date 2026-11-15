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

		/* Forced Completion Styles */
		body.locked-drawer {
			overflow: hidden;
		}

		.sidebar-disabled {
			opacity: 0.4 !important;
			pointer-events: none !important;
		}

		.nav-link-disabled {
			opacity: 0.4 !important;
			pointer-events: none !important;
			cursor: not-allowed !important;
		}

		/* E-Cash Channels Section - Theme Aware */
		.ecash-channels-container {
			background: var(--bs-tertiary-bg);
			border: 2px solid var(--bs-border-color);
			border-radius: 12px;
			padding: 20px;
			margin-top: 20px;
			color: var(--bs-body-color);
		}

		.ecash-channels-container h5 {
			color: var(--bs-body-color);
			font-weight: 700;
			margin-bottom: 15px;
			display: flex;
			align-items: center;
			gap: 10px;
		}

		.ecash-channel-item {
			background: var(--bs-body-bg);
			border: 2px solid var(--bs-border-color);
			border-radius: 8px;
			padding: 15px;
			margin-bottom: 12px;
			display: grid;
			grid-template-columns: minmax(0, 1fr) clamp(96px, 12vw, 128px) auto auto;
			gap: 12px;
			align-items: center;
			transition: all 0.3s ease;
		}

		.ecash-amount {
			width: 100%;
			min-width: 96px;
			max-width: 128px;
			justify-self: end;
		}

		.ecash-network-name-display {
			font-weight: 700;
			max-width: 100%;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}

		.ecash-proof-error {
			grid-column: 1 / -1;
			display: block;
			margin-top: 6px;
		}

		#staticBackdrop {
			z-index: 20000;
		}
		.modal-backdrop {
			z-index: 19999;
		}
		@media (max-width: 992px) {
			#staticBackdrop .modal-dialog {
				margin-bottom: 120px;
			}
		}
		#staticBackdrop .modal-footer {
			padding-bottom: calc(1rem + 90px);
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
			background: rgba(0,0,0,0.12);
			border: 1px solid rgba(0,0,0,0.12);
			padding: 0;
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
			font-size: 14px;
			line-height: 1;
		}

		.ecash-channel-item:hover {
			background: var(--bs-secondary-bg);
			border-color: var(--bs-primary);
			box-shadow: 0 4px 12px var(--bs-shadow-color);
		}

		.ecash-network-icon {
			font-size: 24px;
			min-width: 30px;
			text-align: center;
		}

		.ecash-network-wrapper {
			display: flex;
			flex-direction: column;
			gap: 4px;
		}

		.ecash-network-badge {
			display: inline-block;
			background: var(--bs-secondary-bg);
			color: var(--bs-body-color);
			padding: 4px 12px;
			border-radius: 20px;
			font-size: 11px;
			font-weight: 700;
		}

		.ecash-channel-item input {
			background: var(--bs-body-bg) !important;
			border: 1px solid var(--bs-border-color) !important;
			color: var(--bs-body-color) !important;
			font-weight: 600;
		}

		.ecash-channel-item input:focus {
			background: var(--bs-body-bg) !important;
			border-color: var(--bs-primary) !important;
			color: var(--bs-body-color) !important;
			box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
		}

		.btn-add-ecash {
			background: var(--bs-secondary-bg);
			border: 2px dashed var(--bs-border-color);
			color: var(--bs-body-color);
			border-radius: 8px;
			padding: 12px 20px;
			font-weight: 600;
			transition: all 0.3s ease;
			cursor: pointer;
			width: 100%;
			margin-top: 10px;
		}

		.btn-add-ecash:hover {
			background: var(--bs-tertiary-bg);
			border-color: var(--bs-primary);
			color: var(--bs-primary);
			transform: translateY(-2px);
			box-shadow: 0 4px 12px var(--bs-shadow-color);
		}

		.btn-remove-ecash {
			background: var(--bs-danger-bg-subtle);
			border: none;
			color: var(--bs-danger);
			border-radius: 6px;
			padding: 6px 12px;
			font-size: 12px;
			font-weight: 600;
			cursor: pointer;
			transition: all 0.3s ease;
		}

		.btn-remove-ecash:hover {
			background: var(--bs-danger);
			color: white;
			transform: scale(1.05);
		}

		.back-btn-disabled {
			opacity: 0.5 !important;
			pointer-events: none !important;
			cursor: not-allowed !important;
		}

		.back-btn-enabled {
			opacity: 1;
			pointer-events: auto;
			cursor: pointer;
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
	</style>
	<form method="post" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/add_cashdrawer" enctype="multipart/form-data">		
		<div class="row col-md-12 col-md-10 col-lg-6 mx-auto shadow p-2 rounded">
			
			<?=csrf()?>

			<h4 class="text-center">
				<span><img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:60px;max-height: 60px;object-fit: cover;"></span>
				Create Cash Drawer
			</h4>

			<?php
				$errors = get_value('errors');
				$errors = is_array($errors) ? $errors : [];
				$globalDrawerErrors = [];
				if (!empty($errors) && is_array($errors)) {
					foreach ($errors as $errorKey => $errorMessage) {
						if (strpos((string)$errorKey, 'ecash_proof_') === 0) {
							continue;
						}
						$errorMessage = trim((string)$errorMessage);
						if ($errorMessage !== '') {
							$globalDrawerErrors[] = $errorMessage;
						}
					}
					$globalDrawerErrors = array_values(array_unique($globalDrawerErrors));
				}
			?>
			<?php if(!empty($globalDrawerErrors)): ?>
				<div class="alert alert-danger mt-2">
					<div class="fw-bold mb-1">Cashdrawer could not be created</div>
					<?php foreach($globalDrawerErrors as $drawerError): ?>
						<div class="small"><?=esc($drawerError)?></div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<script>
				window.__cashdrawerCreateErrors = <?= json_encode($globalDrawerErrors, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
			</script>
			<?php if (!empty($cashdrawerCarryForwardError)): ?>
				<div class="alert alert-danger mt-2">
					<?= esc($cashdrawerCarryForwardError) ?>
				</div>
			<?php elseif (!empty($cashdrawerCarryForward)): ?>
				<div class="col-12">
					<div class="alert alert-primary mt-2 mb-3">
						<div class="fw-bold">Previous cashdrawer #<?= (int)$cashdrawerCarryForward['id'] ?> ·
							<?= esc(date('j M Y, H:i', strtotime($cashdrawerCarryForward['date_created']))) ?></div>
						<div class="small mt-1">
							Previous closing cash: GHS <?= number_format((float)$cashdrawerCarryForward['ghs_total'], 2) ?>
							<?php if ((float)$cashdrawerCarryForward['cfa_total'] > 0): ?>
								· CFA <?= number_format((float)$cashdrawerCarryForward['cfa_total'], 0) ?>
							<?php endif; ?>
						</div>
						<div class="small">Preview of the balances that will carry forward into today’s drawer. No balance changes are available here.</div>
						<?php if (!empty($cashdrawerCarryForward['denominations'])): ?>
							<div class="table-responsive mt-2">
								<table class="table table-sm table-bordered mb-2">
									<thead><tr><th>Currency</th><th>Denomination</th><th class="text-end">Count</th><th class="text-end">Value</th></tr></thead>
									<tbody>
										<?php foreach ($cashdrawerCarryForward['denominations'] as $openingLine): ?>
											<tr>
												<td><?= esc($openingLine['currency']) ?></td>
												<td><?= number_format((float)$openingLine['denomination'], $openingLine['currency'] === 'CFA' ? 0 : 2) ?></td>
												<td class="text-end"><?= number_format((int)$openingLine['count']) ?></td>
												<td class="text-end"><?= number_format((float)$openingLine['volume'], 2) ?></td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						<?php endif; ?>
						<?php if (!empty($cashdrawerCarryForward['ecash'])): ?>
							<div class="small fw-semibold">E-cash balances to carry forward</div>
							<div class="table-responsive mt-2">
								<table class="table table-sm table-bordered mb-0">
									<thead><tr><th>Channel</th><th class="text-end">Balance (GHS)</th></tr></thead>
									<tbody>
										<?php foreach ($cashdrawerCarryForward['ecash'] as $previousChannel): ?>
											<tr>
												<td><?= esc($previousChannel['name']) ?></td>
												<td class="text-end"><?= number_format((float)$previousChannel['balance'], 2) ?></td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php 
				$session = get_session();
				$userRole = strtolower($session->roles[0] ?? '');
				$isOpsManager = ($userRole === 'operations manager');
				$isAdmin = user_can('is_admin');

				$sharedBankBalances = [];
				$sharedBankProofs = [];
				$postedEcashBalances = [];
				foreach ($_POST as $postedField => $postedValue) {
					$postedField = (string)$postedField;
					if (strpos($postedField, 'ecash_') === 0
						&& strpos($postedField, 'ecash_proof_') !== 0
						&& is_scalar($postedValue)) {
						$postedEcashBalances[$postedField] = (string)$postedValue;
					}
				}
				if ($isAdmin) {
					try {
						$dbShared = new \Core\Database;
						if ($dbShared->table_exists(['drawer_balances','cashdrawers','user_roles_map','user_roles'])) {
							$today = date('Y-m-d');
							$bankNetworkNames = [
								'Absa Bank',
								'Apex Nafana Bank',
								'Apex Suma Bank',
								'Access Bank',
								'Cal Bank',
								'Ecobank',
								'Fidelity Bank',
								'First National Bank (FNB)',
								'First Bank Ghana (FBG)',
								'GCB Bank',
								'GT Bank',
								'Stanbic Bank',
							];
							$bankKeyToName = [];
							$params = ['today' => $today];
							$sharedRoleNames = [
								'admin',
								'manager',
								'general manager',
								'general_manager',
								'generalmanager',
								'gm',
								'operations manager',
								'operations_manager',
								'operationsmanager',
								'op manager',
								'op_manager',
							];
							$rolePlaceholders = [];
							foreach ($sharedRoleNames as $rIdx => $roleName) {
								$rk = 'role' . (string)$rIdx;
								$rolePlaceholders[] = ':' . $rk;
								$params[$rk] = strtolower((string)$roleName);
							}
							$placeholders = [];
							$idx = 0;
							foreach ($bankNetworkNames as $name) {
								$key = 'ECASH_' . strtoupper(preg_replace('/[^A-Z0-9]+/i', '_', (string)$name));
								$key = trim($key, '_');
								$bankKeyToName[$key] = $name;
								$ph = 'k' . $idx;
								$placeholders[] = ':' . $ph;
								$params[$ph] = $key;
								$idx++;
							}
							if (!empty($placeholders) && !empty($rolePlaceholders)) {
								$sql = "SELECT db.channel_key, db.opening_balance, db.credit, db.debit, db.closing_balance, db.proof_image
								        FROM drawer_balances AS db
								        INNER JOIN (
								          SELECT db2.channel_key,
								            COALESCE(
								              MAX(CASE WHEN LOWER(COALESCE(db2.trans_status, '')) <> 'opening snapshot' THEN db2.id END),
								              MAX(db2.id)
								            ) AS max_id
								          FROM drawer_balances AS db2
								          INNER JOIN cashdrawers AS cd ON cd.id = db2.drawer_id
								          INNER JOIN user_roles_map AS urm ON urm.user_id = cd.user_id AND (urm.disabled = 0 OR urm.disabled IS NULL)
								          INNER JOIN user_roles AS r ON r.id = urm.role_id AND (r.disabled = 0 OR r.disabled IS NULL)
								          WHERE db2.channel_type = 'ecash'
								            AND DATE(cd.date_created) = :today
								            AND LOWER(r.role) IN (" . implode(',', $rolePlaceholders) . ")
								            AND db2.channel_key IN (" . implode(',', $placeholders) . ")
								          GROUP BY db2.channel_key
								        ) latest ON latest.max_id = db.id";
								$rows = $dbShared->query($sql, $params) ?: [];
								foreach ($rows as $r) {
									$key = strtoupper(trim((string)($r->channel_key ?? '')));
									if ($key === '' || empty($bankKeyToName[$key])) {
										continue;
									}
									$name = $bankKeyToName[$key];
									$opening = (float)($r->opening_balance ?? 0);
									$credit  = (float)($r->credit ?? 0);
									$debit   = (float)($r->debit ?? 0);
									$closing = (float)($r->closing_balance ?? ($opening + $credit - $debit));
									$sharedBankBalances[$name] = $closing;
									if (!empty($r->proof_image)) {
										$sharedBankProofs[$name] = (string)$r->proof_image;
									}
								}
							}
						}
					} catch (\Throwable $e) {
						$sharedBankBalances = [];
						$sharedBankProofs = [];
					}
				}
			?>
			<?php if($isOpsManager && empty($cashdrawerCarryForward)): ?>
				<div class="alert alert-info mt-2">
					<div class="fw-bold mb-1">CFA Exchange Configuration (Operations Manager)</div>
					<div class="small">Current Buying rate (per CFA 1,000): GH&cent;<?=number_format($settings->buying_rate ?? 0, 2)?> &nbsp;|&nbsp; Current Selling rate (per CFA 1,000): GH&cent;<?=number_format($settings->selling_rate ?? 0, 2)?></div>
					<div class="row mt-2 g-2">
						<div class="col-6">
							<label class="form-label mb-0">Buying rate (CFA1,000 → GHS)</label>
							<input type="number" step="0.01" min="0" name="buying_rate" class="form-control" value="<?=old_value('buying_rate', $settings->buying_rate ?? '')?>" required>
						</div>
						<div class="col-6">
							<label class="form-label mb-0">Selling rate (CFA1,000 → GHS)</label>
							<input type="number" step="0.01" min="0" name="selling_rate" class="form-control" value="<?=old_value('selling_rate', $settings->selling_rate ?? '')?>" required>
						</div>
					</div>
				</div>
			<?php endif; ?>
	 
			<!-- Currency is now inferred from denominations; keep as hidden field for backend -->
			<input type="hidden" id="currency" name="currency" value="<?=old_value('currency','GHS')?>">

			<!-- Amount Input with Calculator Modal Trigger -->
			<div class="col-md-12 mt-3">
				<?php if (!empty($cashdrawerCarryForward)): ?>
					<input type="hidden" id="amount" name="amount" value="<?= esc(number_format((float)$cashdrawerCarryForward['ghs_total'], 2, '.', '')) ?>">
				<?php else: ?>
					<label for="amount" class="form-label">Opening Cash (GHS)</label>
					<div class="input-group">
						<input type="text" class="form-control" id="amount" name="amount" value="<?=old_value('amount')?>" readonly>
						<button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#staticBackdrop">
							<i class="fa fa-calculator"></i> Launch Calculator
						</button>
					</div>
				<?php endif; ?>
				<!-- Hidden denominations payload for controller (GHS + CFA) -->
				<input type="hidden" id="denos" name="denos" value="<?=old_value('denos','[]')?>">
				<?php if (!empty($cashdrawerCarryForward)): ?>
					<div class="alert alert-info mt-2 mb-0">
						Review the previous drawer preview above, then submit to open today’s drawer with the same balances.
					</div>
				<?php elseif(!empty($cashdrawerVaultLimits['lookup_error'])): ?>
					<div class="alert alert-danger mt-2 mb-0">
						<?=esc($cashdrawerVaultLimits['lookup_error'])?> Cash counts cannot be submitted until availability can be verified.
					</div>
				<?php elseif(empty($cashdrawerVaultLimits['branch_assigned'])): ?>
					<div class="alert alert-warning mt-2 mb-0">
						No branch is assigned to your account. Please consult an administrator to be assigned to a branch. You may still create this cashdrawer; it will not be linked to a branch vault.
					</div>
				<?php else: ?>
					<div class="alert alert-info mt-2 mb-0">
						Opening cash counts are limited to the available denominations in your branch vault.
						<?php if(empty($cashdrawerVaultLimits['vault_count'])): ?>
							No active vault is assigned to this branch, so no cash denominations are currently available. Please contact an administrator.
						<?php endif; ?>
					</div>
					<div id="vault-denomination-error" class="alert alert-danger mt-2 mb-0 d-none" role="alert"></div>
				<?php endif; ?>
				<?php if(!empty($errors['amount'])): ?>
					<small class="text-danger d-block mt-1"><?=$errors['amount']?></small>
				<?php endif ?>
				<?php if(!empty($errors['currency'])): ?>
					<small class="text-danger d-block mt-1"><?=$errors['currency']?></small>
				<?php endif ?>
			</div>

			<!-- E-Cash Channels Container -->
			<div class="col-md-12 mt-4 ecash-channels-container <?= !empty($cashdrawerCarryForward) ? 'd-none' : '' ?>">
				<?php if (empty($cashdrawerCarryForward)): ?>
					<h5>
						<span class="me-2">E-Cash Channels</span>
						<small class="text-muted">Configure MTN, Telecel, AT and other wallets</small>
					</h5>
				<?php endif; ?>
				<div id="ecash-channels-list"></div>
				<button type="button" class="btn-add-ecash" id="btn-add-ecash">
					<i class="fas fa-plus-circle"></i> Add E-Cash Channel
				</button>
			</div>

			<div class="col-md-12 ">
				<?php $session = get_session(); ?>
				<?php if (!empty($cashdrawerCarryForward) && !empty($session->id)): ?>
					<div class="small text-muted mb-2">Cashdrawer owner</div>
					<div class="fw-semibold mb-3"><?= esc(($session->first_name ?? '') . ' ' . ($session->last_name ?? '')) ?></div>
					<input type="hidden" name="user_id" value="<?= (int)$session->id ?>">
				<?php else: ?>
					<label for="user_id" class="form-label">In charge</label>
					<select class="form-select user_id" name="user_id">
						<?php if(!empty($session) && !empty($session->id)): ?>
						<option value="<?=$session->id?>" selected>
							<?= esc(($session->first_name ?? '') . ' ' . ($session->last_name ?? '')) ?> (<?= esc($session->roles[0] ?? '') ?>)
						</option>
						<?php else: ?>
						<option value="">No logged-in user</option>
						<?php endif; ?>
					</select>
				<?php endif; ?>
				<?php if(!empty($errors['user_id'])): ?>
					<small class="text-danger"><?=$errors['user_id']?></small>
				<?php endif ?>
			</div>
	 
			<div class="d-flex justify-content-between mb-5" style="margin-top:10px;">
				<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
					<button type="button" class="btn btn-primary">
						<i class="fa-solid fa-chevron-left"></i> Back
					</button>
				</a>
				<button class="btn btn-danger" type="submit" disabled>
					<i class="fa-solid fa-save"></i> Create
				</button>
			</div>
		</div>
	</form>

	<!-- Progress Overlay for Form Submission -->
	<div class="progress-overlay" id="progressOverlay">
		<div class="progress-container">
			<div class="progress-spinner pulse" id="progressSpinner"></div>
			<div class="progress-title">Creating Cashdrawer...</div>
			<div class="progress-status" id="progressStatus">Preparing data...</div>
			<div class="progress-bar-container">
				<div class="progress-bar-fill" id="progressBarFill"></div>
			</div>
			<div class="progress-percentage" id="progressPercentage">0%</div>
		</div>
	</div>

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
	          	<!-- GHS Notes -->
	            <tr data-denomination="200" data-currency="GHS">
	              <td class="px-5">GH&cent;200</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Volume" readonly></td>
	            </tr>
	            <tr data-denomination="100" data-currency="GHS">
	              <td class="px-5">GH&cent;100</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Volume" readonly></td>
	            </tr>
	             <tr data-denomination="50" data-currency="GHS">
	              <td class="px-5">GH&cent;50</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
	            </tr>
	            <tr data-denomination="20" data-currency="GHS">
	              <td class="px-5">GH&cent;20</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
	            </tr>
	            <tr data-denomination="10" data-currency="GHS">
	              <td class="px-5">GH&cent;10</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
	            </tr>
	            <tr data-denomination="5" data-currency="GHS">
	              <td class="px-5">GH&cent;5</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
	            </tr>
	            <tr data-denomination="2" data-currency="GHS">
	              <td class="px-5">GH&cent;2</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
	            </tr>
	            <tr data-denomination="1" data-currency="GHS">
	              <td class="px-5">GH&cent;1</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
	            </tr>
	            <tr>
	              <td colspan="3">COINS</td>
	            </tr>
	
	            <tr data-denomination="0.5" data-currency="GHS">
	              <td class="px-5">50p</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
	            </tr>
	
	            <tr data-denomination="0.2" data-currency="GHS">
	              <td class="px-5">20p</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
	            </tr>
	
	            <tr data-denomination="0.1" data-currency="GHS">
	              <td class="px-5">10p</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
	            </tr>

	          	<!-- CFA Notes (visible for operations manager, but safe for all) -->
	          	<tr class="table-active">
	          		<td colspan="3" class="fw-bold">CFA Notes</td>
	          	</tr>
	            <tr data-denomination="10000" data-currency="CFA">
	              <td class="px-5">10000CFA</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Volume" readonly></td>
	            </tr>
	            <tr data-denomination="5000" data-currency="CFA">
	              <td class="px-5">5000CFA</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Volume" readonly></td>
	            </tr>
	            <tr data-denomination="2000" data-currency="CFA">
	              <td class="px-5">2000CFA</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Volume" readonly></td>
	            </tr>
	            <tr data-denomination="1000" data-currency="CFA">
	              <td class="px-5">1000CFA</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Volume" readonly></td>
	            </tr>
	            <tr data-denomination="500" data-currency="CFA">
	              <td class="px-5">500CFA</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Volume" readonly></td>
	            </tr>
	            <tr data-denomination="200" data-currency="CFA">
	              <td class="px-5">200CFA</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Volume" readonly></td>
	            </tr>
	            <tr data-denomination="100" data-currency="CFA">
	              <td class="px-5">100CFA</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Volume" readonly></td>
	            </tr>
	             <tr data-denomination="50" data-currency="CFA">
	              <td class="px-5">50CFA</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
	            </tr>
	            <tr data-denomination="20" data-currency="CFA">
	              <td class="px-5">20CFA</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
	            </tr>
	            <tr data-denomination="10" data-currency="CFA">
	              <td class="px-5">10CFA</td>
	              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
	              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
	            </tr>
	          </tbody>
	          <tfoot>
	            <tr>
	              <th class="px-5 bg-secondary text-warning">TOTAL GHS</th>
	              <th class="bg-secondary text-warning"><input type="text" class="form-control p-1" id="total-count" style="border:none; " placeholder="Total count" readonly></th>
	              <th class="bg-secondary text-warning"><input type="text" class="form-control p-1" id="total-volume" style="border:none; " placeholder="Total volume" readonly></th>
	            </tr>
	            <tr>
	              <th class="px-5 bg-secondary text-info">TOTAL CFA</th>
	              <th class="bg-secondary text-info"><input type="text" class="form-control p-1" id="total-count-cfa" style="border:none; " placeholder="Total count" readonly></th>
	              <th class="bg-secondary text-info"><input type="text" class="form-control p-1" id="total-volume-cfa" style="border:none; " placeholder="Total volume" readonly></th>
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

	<!-- JavaScript: Dynamic E-Cash Channels, Calculator, and Forced Completion -->
<script>
	document.addEventListener("DOMContentLoaded", function () {
		// ===== PROGRESS OVERLAY FUNCTIONALITY =====
		const progressOverlay = document.getElementById('progressOverlay');
		const progressBarFill = document.getElementById('progressBarFill');
		const progressPercentage = document.getElementById('progressPercentage');
		const progressStatus = document.getElementById('progressStatus');
		// Use existing form reference from below
		const cashdrawerForm = document.querySelector('form');

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
				{ percent: 10, status: 'Preparing cashdrawer data...', delay: 100 },
				{ percent: 25, status: 'Validating denominations...', delay: 300 },
				{ percent: 40, status: 'Processing e-cash channels...', delay: 500 },
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
		if (cashdrawerForm) {
			cashdrawerForm.addEventListener('submit', function(e) {
				// Check if form is valid (has required data)
				const amountInput = document.getElementById("amount");
				const denosField = document.querySelector("#denos");
				const hasAmount = amountInput && parseFloat(amountInput.value) > 0;
				let hasDenos = false;
				try {
					const denos = JSON.parse(denosField?.value || '[]');
					hasDenos = Array.isArray(denos) && denos.length > 0;
				} catch (e) {
					hasDenos = false;
				}

				// Only show progress if we have actual data to submit
				if (hasAmount || hasDenos) {
					showProgress();
					simulateProgress();
					// Form will submit normally, progress shows until page reloads
				}
			});
		}

		// ===== FORCED COMPLETION LOGIC =====
		const lockDrawer = () => {
			document.body.classList.add('locked-drawer');
			const sidebar = document.querySelector('.sidebar');
			const navLinks = document.querySelectorAll('a[href*="admin"]');
			const backBtn = document.querySelector('a[href*="plugin_route"]');
			
			if (sidebar) sidebar.classList.add('sidebar-disabled');
			navLinks.forEach(link => {
				if (!link.closest('form')) {
					link.classList.add('nav-link-disabled');
					link.addEventListener('click', (e) => e.preventDefault());
				}
			});
			if (backBtn) {
				backBtn.style.pointerEvents = 'none';
				backBtn.style.opacity = '0.4';
			}
		};

		// Lock on page load
		lockDrawer();

		// ===== DYNAMIC E-CASH CHANNELS =====
		const ecashChannelsList = document.getElementById('ecash-channels-list');
		const btnAddEcash = document.getElementById('btn-add-ecash');
		// Reuse cashdrawerForm from progress code above
		const form = document.querySelector('form');
		let channelCount = 0;

		const networks = [
			{ name: 'MTN', icon: '📱', color: '#FFD700' },
			{ name: 'Telecel', icon: '📲', color: '#FF6B6B' },
			{ name: 'AT', icon: '📞', color: '#4ECDC4' },
			{ name: 'MTN EVD', icon: '📱', color: '#FFD700' },
			{ name: 'Telecel EVD', icon: '📲', color: '#FF6B6B' },
			{ name: 'AT EVD', icon: '📞', color: '#4ECDC4' },
			{ name: 'G-MONEY', icon: '📡', color: '#95E1D3' },
			{ name: 'GHANAPAY', icon: '💳', color: '#A8E6CF' },
			{ name: 'ZEEPAY', icon: '💸', color: '#B39DDB' }
		];

		if (<?= isset($isAdmin) && $isAdmin ? 'true' : 'false' ?>) {
			networks.push(
				{ name: 'MTN MOMOPAY', icon: '📱', color: '#FFD700' },
				{ name: 'Absa Bank', icon: '🏦', color: '#D1C4E9' },
				{ name: 'Apex Nafana Bank', icon: '🏦', color: '#D1C4E9' },
				{ name: 'Apex Suma Bank', icon: '🏦', color: '#D1C4E9' },
				{ name: 'Access Bank', icon: '🏦', color: '#D1C4E9' },
				{ name: 'Cal Bank', icon: '🏦', color: '#D1C4E9' },
				{ name: 'Ecobank', icon: '🏦', color: '#D1C4E9' },
				{ name: 'Fidelity Bank', icon: '🏦', color: '#D1C4E9' },
				{ name: 'First National Bank (FNB)', icon: '🏦', color: '#D1C4E9' },
				{ name: 'First Bank Ghana (FBG)', icon: '🏦', color: '#D1C4E9' },
				{ name: 'GCB Bank', icon: '🏦', color: '#D1C4E9' },
				{ name: 'GT Bank', icon: '🏦', color: '#D1C4E9' },
				{ name: 'Stanbic Bank', icon: '🏦', color: '#D1C4E9' }
			);
		}

		const sharedBankBalances = <?= json_encode($sharedBankBalances ?? []) ?>;
		const sharedBankProofs = <?= json_encode($sharedBankProofs ?? []) ?>;
		const postedEcashBalances = <?= json_encode($postedEcashBalances ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
		const getSharedBalance = (name) => {
			if (!name) return 0;
			const raw = sharedBankBalances[name];
			const value = typeof raw === 'number' ? raw : parseFloat(raw || 0);
			return Number.isFinite(value) ? value : 0;
		};
		const getSharedProof = (name) => {
			if (!name) return '';
			return sharedBankProofs[name] || '';
		};

		const truncateNetworkName = (name, maxLen = 15) => {
			const clean = String(name || '');
			if (clean.length <= maxLen) return clean;
			return clean.slice(0, Math.max(0, maxLen - 1)) + '…';
		};

		const createChannelElement = (index, network = null) => {
			const div = document.createElement('div');
			div.className = 'ecash-channel-item';
			div.id = `ecash-channel-${index}`;
			const fullNetworkName = network ? network.name : '';
			const truncatedNetworkName = network ? truncateNetworkName(network.name) : '';
			const sharedBalance = network ? getSharedBalance(fullNetworkName) : 0;
			const sharedProof = network ? getSharedProof(fullNetworkName) : '';
			if (sharedBalance > 0) {
				div.dataset.sharedBalance = String(sharedBalance);
				div.dataset.sharedProof = sharedProof || '';
			}
			const sharedBadge = !isCarryForwardLocked && sharedBalance > 0
				? `<span class="ecash-network-badge">Shared today: GHS ${sharedBalance.toFixed(2)}</span>`
				: '';
			const amountValue = !isCarryForwardLocked && sharedBalance > 0 ? sharedBalance.toFixed(2) : '0.00';
			div.innerHTML = `
				<div class="ecash-network-icon">
					${network ? network.icon : '📱'}
					<label title="${fullNetworkName}">${network ? truncatedNetworkName : 'Network'}</label>
					<input type="hidden" class="ecash-network-name" value="${network ? fullNetworkName : ''}">
					${sharedBadge}
				</div>
				<input type="number" step="0.01" class="form-control form-control-sm ecash-amount" placeholder="Opening balance" value="${amountValue}" ${isCarryForwardLocked ? 'readonly' : ''}>
				<input type="file" class="d-none proof-input" accept="image/*">
				<button type="button" class="recon-proof-thumb ecash-proof-thumb" data-proof-index="${index}" aria-label="Upload proof image">
					<img class="d-none" alt="Proof preview" />
					<span class="recon-proof-placeholder"><i class="fa-solid fa-camera"></i></span>
				</button>
				${network || isCarryForwardLocked ? '' : `
					<button type="button" class="btn-remove-ecash" onclick="removeEcashChannel(${index})">
						<i class="fas fa-trash-alt"></i> Remove
					</button>
				`}
				<small class="text-danger d-none ecash-proof-error"></small>
			`;
			return div;
		};

		// Initialize with default networks
		const initializeDefaultChannels = () => {
			networks.forEach((network, index) => {
				ecashChannelsList.appendChild(createChannelElement(index, network));
				channelCount++;
			});
		};

		const ecashChannelKey = (name) => {
			const slug = String(name || '').trim().toUpperCase()
				.replace(/[^A-Z0-9]+/g, '_').replace(/^_+|_+$/g, '') || 'CUSTOM';
			return 'ECASH_' + slug;
		};

		const restorePreviousDrawerEcash = () => {
			if (!previousDrawerCarryForward || !previousDrawerCarryForward.ecash) return;
			Object.keys(previousDrawerCarryForward.ecash).forEach((channelKey) => {
				const previous = previousDrawerCarryForward.ecash[channelKey];
				const expectedKey = String(channelKey || '').toUpperCase();
				let channel = Array.from(ecashChannelsList.querySelectorAll('.ecash-channel-item')).find((item) => {
					const nameInput = item.querySelector('.ecash-network-name');
					return nameInput && ecashChannelKey(nameInput.value) === expectedKey;
				});
				if (!channel) {
					const index = channelCount++;
					channel = createChannelElement(index);
					ecashChannelsList.appendChild(channel);
					const nameInput = channel.querySelector('.ecash-network-name');
					const label = channel.querySelector('.ecash-network-icon label');
					const name = String(previous.name || 'Other');
					nameInput.value = name;
					label.textContent = truncateNetworkName(name);
					label.title = name;
				}
				const amountInput = channel.querySelector('.ecash-amount');
				const balance = Math.max(0, Number(previous.balance || 0));
				amountInput.value = balance.toFixed(2);
				channel.dataset.carryForwardBalance = String(balance);
				channel.dataset.carryForwardProof = String(previous.proof_image || '');
			});
		};

		const restorePostedEcashBalances = () => {
			Object.keys(postedEcashBalances).forEach((fieldName) => {
				const suffix = fieldName.slice('ecash_'.length).toUpperCase();
				const expectedKey = 'ECASH_' + suffix;
				let channel = Array.from(ecashChannelsList.querySelectorAll('.ecash-channel-item')).find((item) => {
					const nameInput = item.querySelector('.ecash-network-name');
					return nameInput && ecashChannelKey(nameInput.value) === expectedKey;
				});
				if (!channel) {
					const index = channelCount++;
					channel = createChannelElement(index);
					ecashChannelsList.appendChild(channel);
					const name = suffix.toLowerCase().split('_')
						.map(part => part.charAt(0).toUpperCase() + part.slice(1)).join(' ');
					channel.querySelector('.ecash-network-name').value = name;
					const label = channel.querySelector('.ecash-network-icon label');
					label.textContent = truncateNetworkName(name);
					label.title = name;
				}
				const amount = Number(postedEcashBalances[fieldName]);
				channel.querySelector('.ecash-amount').value =
					Number.isFinite(amount) && amount >= 0 ? amount.toFixed(2) : '0.00';
			});
		};

		// Add new channel
		btnAddEcash.addEventListener('click', (e) => {
			e.preventDefault();
			if (channelCount < 20) {
				ecashChannelsList.appendChild(createChannelElement(channelCount));
				channelCount++;
				validateCreateButton();
			} else {
				alert('Maximum 20 e-cash channels allowed');
			}
		});

		// Remove channel
		window.removeEcashChannel = (index) => {
			const element = document.getElementById(`ecash-channel-${index}`);
			if (element) {
				element.style.animation = 'fadeOut 0.3s ease-out';
				setTimeout(() => element.remove(), 300);
			}
		};

		// ===== CALCULATOR LOGIC =====
		const previousDrawerCarryForward = <?= json_encode($cashdrawerCarryForward ?? null, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
		const isCarryForwardLocked = !!previousDrawerCarryForward;
		const countInputs = document.querySelectorAll(".count-input");
		const totalCountField = document.getElementById("total-count");
		const totalVolumeField = document.getElementById("total-volume");
		const totalCountCfaField = document.getElementById("total-count-cfa");
		const totalVolumeCfaField = document.getElementById("total-volume-cfa");
		const denosField = document.querySelector("#denos");
		const submitBtn = document.querySelector('.submit');
		const createBtn = cashdrawerForm ? cashdrawerForm.querySelector('button[type="submit"]') : null;
		const restoreBtn = document.querySelector('.restore');
		const resetBtn = document.querySelector('.reset');
		const staticBackdropEl = document.getElementById('staticBackdrop');
		const staticBackdropModal = (window.bootstrap && staticBackdropEl) ? new bootstrap.Modal(staticBackdropEl) : null;
		const amountInput = document.getElementById("amount");
		if (isCarryForwardLocked) {
			countInputs.forEach((input) => { input.readOnly = true; });
			[submitBtn, resetBtn, restoreBtn].forEach((button) => {
				if (button) button.disabled = true;
			});
		}
		const currencySelect = document.getElementById('currency');
		const isOpsManager = <?= isset($isOpsManager) && $isOpsManager ? 'true' : 'false' ?>;
		const buyingRate = <?= isset($settings) ? (float)($settings->buying_rate ?? 0) : 0 ?>;
		const sellingRate = <?= isset($settings) ? (float)($settings->selling_rate ?? 0) : 0 ?>;
		const postedDenosPayload = <?= json_encode(old_value('denos', '[]'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
		const vaultOpeningLimits = <?= json_encode($cashdrawerVaultLimits ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
		const carryForwardError = <?= json_encode((string)($cashdrawerCarryForwardError ?? ''), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

		// FX summary fields for operations manager
		const summaryGhsTotal = document.getElementById('summary-ghs-total');
		const summaryCfaTotal = document.getElementById('summary-cfa-total');
		const summaryCfaGhsBuy = document.getElementById('summary-cfa-ghs-buy');
		const summaryCfaGhsSell = document.getElementById('summary-cfa-ghs-sell');
		const summaryTotalBuy = document.getElementById('summary-total-buy');
		const summaryTotalSell = document.getElementById('summary-total-sell');

		const setCreateEnabled = (enabled) => {
			if (!createBtn) return;
			createBtn.disabled = !enabled;
			if (enabled) {
				createBtn.classList.remove('disabled');
			} else {
				createBtn.classList.add('disabled');
			}
		};

		const setupVaultAvailability = () => {
			if (previousDrawerCarryForward) {
				const previousCounts = {};
				(previousDrawerCarryForward.denominations || []).forEach((line) => {
					const currency = String(line.currency || 'GHS').toUpperCase();
					const denomination = Number(line.denomination || 0).toFixed(2);
					previousCounts[currency + ':' + denomination] =
						(previousCounts[currency + ':' + denomination] || 0) + Number(line.count || 0);
				});
				countInputs.forEach((input) => {
					const row = input.closest('tr');
					if (!row) return;
					const currency = String(row.dataset.currency || 'GHS').toUpperCase();
					const denomination = Number(row.dataset.denomination || 0).toFixed(2);
					const previous = Number(previousCounts[currency + ':' + denomination] || 0);
					let note = input.parentElement.querySelector('.vault-available-count');
					if (!note) {
						note = document.createElement('small');
						note.className = 'text-muted d-block vault-available-count';
						input.parentElement.appendChild(note);
					}
					const available = Number((vaultOpeningLimits.available &&
						(vaultOpeningLimits.available[currency] || {})[denomination]) || 0);
					note.textContent = vaultOpeningLimits.branch_assigned
						? `Previous close: ${previous}; available in branch vault for additions: ${available}`
						: `Previous close: ${previous}; branch vault availability unavailable`;
				});
				return;
			}
			if (!vaultOpeningLimits.branch_assigned) return;
			countInputs.forEach((input) => {
				const row = input.closest('tr');
				if (!row) return;
				const currency = String(row.dataset.currency || 'GHS').toUpperCase();
				const denomination = Number(row.dataset.denomination || 0).toFixed(2);
				const available = Number((vaultOpeningLimits.available && vaultOpeningLimits.available[currency] || {})[denomination] || 0);
				input.max = String(available);
				let note = input.parentElement.querySelector('.vault-available-count');
				if (!note) {
					note = document.createElement('small');
					note.className = 'vault-available-count text-muted d-block';
					input.parentElement.appendChild(note);
				}
				note.textContent = `${available} available in branch vault`;
			});
		};

		const validateVaultDenominationCounts = () => {
			const errorEl = document.getElementById('vault-denomination-error');
			if (previousDrawerCarryForward) {
				const previousCounts = {};
				(previousDrawerCarryForward.denominations || []).forEach((line) => {
					const currency = String(line.currency || 'GHS').toUpperCase();
					const denomination = Number(line.denomination || 0).toFixed(2);
					const key = currency + ':' + denomination;
					previousCounts[key] = (previousCounts[key] || 0) + Number(line.count || 0);
				});
				let valid = true;
				let message = '';
				countInputs.forEach((input) => {
					const row = input.closest('tr');
					if (!row) return;
					const currency = String(row.dataset.currency || 'GHS').toUpperCase();
					const denomination = Number(row.dataset.denomination || 0).toFixed(2);
					const previous = Number(previousCounts[currency + ':' + denomination] || 0);
					const requested = input.value.trim() === '' ? 0 : Number(input.value);
					const delta = requested - previous;
					let issue = '';
					if (!Number.isFinite(requested) || requested < 0 || !Number.isInteger(requested)) {
						issue = 'counts must be whole numbers and cannot be negative';
					} else if (delta !== 0 && vaultOpeningLimits.lookup_error) {
						issue = 'branch vault availability could not be checked';
					} else if (delta > 0 && !vaultOpeningLimits.branch_assigned) {
						issue = 'a branch vault is required to issue additional cash';
					} else if (delta > 0 && Number(delta) > Number((vaultOpeningLimits.available &&
						(vaultOpeningLimits.available[currency] || {})[denomination]) || 0)) {
						issue = 'additional count exceeds branch vault stock';
					} else if (delta < 0 && (!vaultOpeningLimits.branch_assigned || Number(vaultOpeningLimits.vault_count || 0) <= 0)) {
						issue = 'an active branch vault is required to receive returned cash';
					}
					input.classList.toggle('is-invalid', issue !== '');
					if (issue !== '' && valid) {
						valid = false;
						message = currency + ' ' + denomination.replace(/\.?0+$/, '') + ': ' + issue + '.';
					}
				});
				if (errorEl) {
					errorEl.textContent = message;
					errorEl.classList.toggle('d-none', valid);
				}
				return valid;
			}
			if (!vaultOpeningLimits.branch_assigned) return true;
			if (vaultOpeningLimits.lookup_error) {
				if (errorEl) {
					errorEl.textContent = 'Branch vault availability could not be checked. Cashdrawer creation is blocked; please contact an administrator.';
					errorEl.classList.remove('d-none');
				}
				return false;
			}
			let valid = true;
			let message = '';
			countInputs.forEach((input) => {
				const row = input.closest('tr');
				if (!row) return;
				const currency = String(row.dataset.currency || 'GHS').toUpperCase();
				const denomination = Number(row.dataset.denomination || 0).toFixed(2);
				const available = Number((vaultOpeningLimits.available && vaultOpeningLimits.available[currency] || {})[denomination] || 0);
				const requested = input.value.trim() === '' ? 0 : Number(input.value);
				const exceeds = !Number.isFinite(requested) || requested < 0 || requested > available || !Number.isInteger(requested);
				input.classList.toggle('is-invalid', exceeds);
				if (exceeds && valid) {
					valid = false;
					message = `${currency} ${denomination.replace(/\.?0+$/, '')}: requested ${input.value || 0}, available ${available}.`;
				}
			});
			if (errorEl) {
				errorEl.textContent = message;
				errorEl.classList.toggle('d-none', valid);
			}
			return valid;
		};

		const readDenos = () => {
			let parsed = [];
			if (!denosField) {
				return { ghs: 0, cfa: 0, any: false };
			}
			try {
				parsed = JSON.parse(denosField.value || '[]');
			} catch (e) {
				parsed = [];
			}
			if (!Array.isArray(parsed)) {
				parsed = [];
			}
			let ghs = 0;
			let cfa = 0;
			parsed.forEach((row) => {
				const currency = String(row && row.currency ? row.currency : 'GHS').toUpperCase();
				const volume = Number(row && row.volume ? row.volume : 0);
				if (!isFinite(volume) || volume <= 0) return;
				if (currency === 'CFA') {
					cfa += volume;
				} else {
					ghs += volume;
				}
			});
			return { ghs, cfa, any: (ghs > 0 || cfa > 0) };
		};

		const validateCreateButton = () => {
			if (!cashdrawerForm || !createBtn) return;
			const denominationCountsOk = validateVaultDenominationCounts();
			const denos = readDenos();
			const openingCash = Number(amountInput && amountInput.value ? amountInput.value : 0) || 0;
			let hasEcashInput = false;
			let ecashProofOk = true;
			const channels = document.querySelectorAll('.ecash-channel-item');
			channels.forEach((channel) => {
				const networkNameEl = channel.querySelector('.ecash-network-name');
				const amountEl = channel.querySelector('.ecash-amount');
				const proofEl = channel.querySelector('input.proof-input');
				const networkName = networkNameEl ? networkNameEl.value.trim() : '';
				let amount = amountEl ? parseFloat(amountEl.value) : 0;
				if (isNaN(amount) || amount < 0) amount = 0;
				const usesChannel = !!networkName;
				if (usesChannel && amount > 0) {
					hasEcashInput = true;
					// Allow shared proof: channel may have data-shared-balance and data-shared-proof
					const sharedBalanceRaw = channel.dataset.sharedBalance || '';
					const sharedProof = channel.dataset.sharedProof || '';
					const sharedBalance = Number.isFinite(parseFloat(sharedBalanceRaw || '0')) ? parseFloat(sharedBalanceRaw || '0') : 0;
					const sharedProofAllowed = sharedProof && Number.isFinite(sharedBalance) && Math.abs(amount - sharedBalance) < 0.01;
					const carryForwardBalance = Number(channel.dataset.carryForwardBalance || 0);
					const carryForwardBalanceAllowed = channel.dataset.carryForwardBalance !== undefined
						&& Number.isFinite(carryForwardBalance)
						&& Math.abs(amount - carryForwardBalance) < 0.01;
					if (!proofEl || !proofEl.files || !proofEl.files[0]) {
						if (!sharedProofAllowed && !carryForwardBalanceAllowed) {
							ecashProofOk = false;
						}
					}
				}
			});

			const hasAnyInput = denos.any || hasEcashInput;
			const ghsOk = denos.ghs <= 0 || openingCash > 0;
			setCreateEnabled(!carryForwardError
				&& denominationCountsOk
				&& (isCarryForwardLocked || (hasAnyInput && ghsOk && ecashProofOk)));
		};

		setupVaultAvailability();

		function clearDenominations() {
			countInputs.forEach(input => {
				const row = input.closest("tr");
				const volumeField = row.querySelector(".volume-input");
				input.value = '';
				volumeField.value = '';
			});
			totalCountField.value = '';
			totalVolumeField.value = '';
			if (totalCountCfaField) totalCountCfaField.value = '';
			if (totalVolumeCfaField) totalVolumeCfaField.value = '';
			if (denosField) denosField.value = "[]";
			if (isOpsManager) {
				if (summaryGhsTotal) summaryGhsTotal.value = '';
				if (summaryCfaTotal) summaryCfaTotal.value = '';
				if (summaryCfaGhsBuy) summaryCfaGhsBuy.value = '';
				if (summaryCfaGhsSell) summaryCfaGhsSell.value = '';
				if (summaryTotalBuy) summaryTotalBuy.value = '';
				if (summaryTotalSell) summaryTotalSell.value = '';
			}
			validateCreateButton();
		}

		function restorePostedDenominations() {
			if (!denosField || !postedDenosPayload || postedDenosPayload === '[]') {
				return;
			}
			let postedRows = [];
			try {
				postedRows = JSON.parse(postedDenosPayload);
			} catch (e) {
				postedRows = [];
			}
			if (!Array.isArray(postedRows) || postedRows.length === 0) {
				return;
			}

			postedRows.forEach((postedRow) => {
				const currency = String(postedRow && postedRow.currency ? postedRow.currency : 'GHS').toUpperCase();
				const denomination = Number(postedRow && postedRow.denomination ? postedRow.denomination : 0);
				const count = Number(postedRow && postedRow.count ? postedRow.count : 0);
				if (!isFinite(denomination) || !isFinite(count) || count <= 0) {
					return;
				}
				countInputs.forEach((input) => {
					const tr = input.closest('tr');
					if (!tr) return;
					const rowCurrency = String(tr.getAttribute('data-currency') || 'GHS').toUpperCase();
					const rowDenomination = Number(tr.getAttribute('data-denomination') || 0);
					if (rowCurrency === currency && Math.abs(rowDenomination - denomination) < 0.000001) {
						input.value = String(count);
					}
				});
			});
			updateVolumesAndTotals();
			if (amountInput) amountInput.value = totalVolumeField.value;
		}

		function restorePreviousDrawerDenominations() {
			if (!previousDrawerCarryForward || !Array.isArray(previousDrawerCarryForward.denominations)) {
				return;
			}
			previousDrawerCarryForward.denominations.forEach((openingLine) => {
				const currency = String(openingLine.currency || 'GHS').toUpperCase();
				const denomination = Number(openingLine.denomination || 0);
				const count = Number(openingLine.count || 0);
				countInputs.forEach((input) => {
					const row = input.closest('tr');
					if (!row) return;
					if (String(row.dataset.currency || 'GHS').toUpperCase() === currency
						&& Math.abs(Number(row.dataset.denomination || 0) - denomination) < 0.000001) {
						input.value = count > 0 ? String(count) : '';
					}
				});
			});
			updateVolumesAndTotals();
			if (amountInput) amountInput.value = totalVolumeField.value;
		}

		countInputs.forEach(input => {
			input.addEventListener("input", updateVolumesAndTotals);
		});

		function updateVolumesAndTotals() {
			let totalCountGhs = 0;
			let totalVolumeGhs = 0;
			let totalCountCfa = 0;
			let totalVolumeCfa = 0;
			const currencyPayload = [];

			countInputs.forEach(input => {
				const row = input.closest("tr");
				const denomination = parseFloat(row.getAttribute("data-denomination"));
				const currencyCode = (row.getAttribute("data-currency") || 'GHS').toUpperCase();
				const count = parseFloat(input.value) || 0;
				const volumeField = row.querySelector(".volume-input");
				const volume = denomination * count;
				volumeField.value = volume ? volume.toFixed(2) : '';

				if (currencyCode === 'CFA') {
					totalCountCfa += count;
					totalVolumeCfa += volume;
				} else {
					totalCountGhs += count;
					totalVolumeGhs += volume;
				}

				if (count > 0 && volume !== 0) {
					currencyPayload.push({
						currency: currencyCode,
						denomination: denomination,
						count: count,
						volume: volume,
					});
				}
			});

			// Update per-currency totals
			totalCountField.value = totalCountGhs;
			totalVolumeField.value = totalVolumeGhs.toFixed(2);
			if (totalCountCfaField) totalCountCfaField.value = totalCountCfa;
			if (totalVolumeCfaField) totalVolumeCfaField.value = totalVolumeCfa.toFixed(2);

			// Infer header currency from entered denominations
			// If both GHS and CFA are present we keep header currency as GHS;
			// denominations themselves are already tagged per-currency in JSON.
			if (currencySelect) {
				let inferredCurrency = 'GHS';
				if (totalVolumeGhs === 0 && totalVolumeCfa > 0) {
					inferredCurrency = 'CFA';
				}
				currencySelect.value = inferredCurrency;
			}

			// Persist serialized payload for controller (GHS + CFA)
			if (denosField) {
				denosField.value = JSON.stringify(currencyPayload);
			}

			// FX summaries for operations manager
			if (isOpsManager) {
				const cfaInThousands = totalVolumeCfa / 1000;
				const cfaInGhsBuy = cfaInThousands * buyingRate;
				const cfaInGhsSell = cfaInThousands * sellingRate;
				const totalBuy = totalVolumeGhs + cfaInGhsBuy;
				const totalSell = totalVolumeGhs + cfaInGhsSell;

				if (summaryGhsTotal) summaryGhsTotal.value = totalVolumeGhs.toFixed(2);
				if (summaryCfaTotal) summaryCfaTotal.value = totalVolumeCfa.toFixed(2);
				if (summaryCfaGhsBuy) summaryCfaGhsBuy.value = cfaInGhsBuy.toFixed(2);
				if (summaryCfaGhsSell) summaryCfaGhsSell.value = cfaInGhsSell.toFixed(2);
				if (summaryTotalBuy) summaryTotalBuy.value = totalBuy.toFixed(2);
				if (summaryTotalSell) summaryTotalSell.value = totalSell.toFixed(2);
			}
			validateCreateButton();
		}

		submitBtn.addEventListener('click', function() {
			updateVolumesAndTotals();
			if (!validateVaultDenominationCounts()) return;
			// Opening amount remains GHS physical cash only
			amountInput.value = totalVolumeField.value;
			if (staticBackdropModal) {
				staticBackdropModal.hide();
			}
			validateCreateButton();
		});

		// Also open calculator when clicking directly into the opening cash field
		if (amountInput) {
			amountInput.addEventListener('click', function () {
				if (!isCarryForwardLocked && staticBackdropModal) {
					staticBackdropModal.show();
				}
			});
		}

		restoreBtn.addEventListener('click', function() {
			// Restore does nothing
		});

		resetBtn.addEventListener('click', function() {
			clearDenominations();
			validateCreateButton();
		});

		// ===== SERIALIZE E-CASH CHANNELS ON FORM SUBMIT =====
		cashdrawerForm.addEventListener('submit', function(e) {
			updateVolumesAndTotals();
			if (!validateVaultDenominationCounts()) {
				e.preventDefault();
				const firstInvalid = document.querySelector('.count-input.is-invalid');
				if (firstInvalid) {
					firstInvalid.focus();
				}
				return;
			}
			const channels = document.querySelectorAll('.ecash-channel-item');
			const ecashData = {};
			const slugCounts = {};

			const buildSlug = (name) => {
				let slug = name.trim().toUpperCase().replace(/[^A-Z0-9]+/g, '_').replace(/^_+|_+$/g, '');
				if (!slug) slug = 'CUSTOM';
				if (slugCounts[slug]) {
					slugCounts[slug] += 1;
					slug = `${slug}_${slugCounts[slug]}`;
				} else {
					slugCounts[slug] = 1;
				}
				return slug;
			};

			const clearChannelProofError = (channel) => {
				channel.classList.remove('border', 'border-danger');
				const err = channel.querySelector('.ecash-proof-error');
				if (err) {
					err.textContent = '';
					err.classList.add('d-none');
				}
			};

				const setChannelProofError = (channel, message) => {
					channel.classList.add('border', 'border-danger');
					const err = channel.querySelector('.ecash-proof-error');
					if (err) {
						err.textContent = message;
						err.classList.remove('d-none');
					}
				};

			let proofOk = true;
			let firstInvalidChannel = null;
			channels.forEach((channel) => {
				const networkName = channel.querySelector('.ecash-network-name').value.trim();
				let amount = parseFloat(channel.querySelector('.ecash-amount').value);
				if (isNaN(amount) || amount < 0) amount = 0;
				
				if (networkName) {
					const slug = buildSlug(networkName);
					const fieldName = `ecash_${slug.toLowerCase()}`;
					ecashData[fieldName] = amount;

					const proofInput = channel.querySelector('input.proof-input');
					if (proofInput) {
						proofInput.name = `ecash_proof_${slug.toLowerCase()}`;
						if (amount > 0) {
							const sharedBalanceRaw = channel.dataset.sharedBalance || '';
							const sharedProof = channel.dataset.sharedProof || '';
							const sharedBalance = parseFloat(sharedBalanceRaw || '0');
							const sharedProofAllowed = sharedProof && Number.isFinite(sharedBalance) && Math.abs(amount - sharedBalance) < 0.01;
							const carryForwardBalance = Number(channel.dataset.carryForwardBalance || 0);
							const carryForwardProof = channel.dataset.carryForwardProof || '';
							const carryForwardBalanceAllowed = channel.dataset.carryForwardBalance !== undefined
								&& Number.isFinite(carryForwardBalance)
								&& Math.abs(amount - carryForwardBalance) < 0.01;

								if (!proofInput.files || !proofInput.files[0]) {
								if (sharedProofAllowed || carryForwardBalanceAllowed) {
									const acceptedProof = carryForwardBalanceAllowed ? carryForwardProof : sharedProof;
									if (acceptedProof) {
										const existingInput = document.createElement('input');
										existingInput.type = 'hidden';
										existingInput.name = `ecash_proof_existing_${slug.toLowerCase()}`;
										existingInput.value = acceptedProof;
										cashdrawerForm.appendChild(existingInput);
									}
									clearChannelProofError(channel);
								} else {
									proofOk = false;
									if (!firstInvalidChannel) firstInvalidChannel = channel;
									setChannelProofError(channel, `Upload photo proof for ${networkName} because opening balance is greater than 0.`);
								}
							} else {
								clearChannelProofError(channel);
							}
						} else {
							clearChannelProofError(channel);
						}
					}
				}
			});

			if (!proofOk) {
				e.preventDefault();
				validateCreateButton();
				if (firstInvalidChannel && typeof firstInvalidChannel.scrollIntoView === 'function') {
					firstInvalidChannel.scrollIntoView({ behavior: 'smooth', block: 'center' });
				}
				alert('Some e-cash channels have opening balance greater than 0. Please upload the photo proof for those channels before submitting.');
				return;
			}

			// Add e-cash data to form as hidden inputs
			Object.keys(ecashData).forEach(key => {
				const input = document.createElement('input');
				input.type = 'hidden';
				input.name = key;
				input.value = ecashData[key];
				cashdrawerForm.appendChild(input);
			});
		});

		// Proof thumbnail UX (same pattern as reconciliations)
		cashdrawerForm.addEventListener('click', function(e){
			const btn = e.target.closest('.ecash-proof-thumb');
			if (!btn) return;
			const channel = btn.closest('.ecash-channel-item');
			if (!channel) return;
			const input = channel.querySelector('input.proof-input');
			if (input) input.click();
		});

		cashdrawerForm.addEventListener('change', function(e){
			const input = e.target.closest('input.proof-input');
			if (!input) return;
			const channel = input.closest('.ecash-channel-item');
			if (!channel) return;
			const thumb = channel.querySelector('.ecash-proof-thumb');
			if (!thumb) return;
			const img = thumb.querySelector('img');
			const placeholder = thumb.querySelector('.recon-proof-placeholder');
			if (window.AppImageCropper && typeof window.AppImageCropper.handleLegacyChange === 'function') {
				window.AppImageCropper.handleLegacyChange({ currentTarget: input }, {
					previewEl: img,
					placeholderEl: placeholder,
					maxBytes: window.AppImageCropper.MAX_BYTES,
					modalTitle: 'Crop cashdrawer proof',
					errorTitle: 'Invalid proof image',
					onSuccess: function(){
						channel.classList.remove('border', 'border-danger');
						const err = channel.querySelector('.ecash-proof-error');
						if (err) {
							err.textContent = '';
							err.classList.add('d-none');
						}
					}
				}).then(function(){
					validateCreateButton();
				});
				return;
			}
			if (input.files && input.files[0] && img) {
				const reader = new FileReader();
				reader.onload = function(ev){
					img.src = String(ev.target && ev.target.result ? ev.target.result : '');
					img.classList.remove('d-none');
					if (placeholder) placeholder.classList.add('d-none');
					channel.classList.remove('border', 'border-danger');
					const err = channel.querySelector('.ecash-proof-error');
					if (err) {
						err.textContent = '';
						err.classList.add('d-none');
					}
				};
				reader.readAsDataURL(input.files[0]);
			} else if (img) {
				img.removeAttribute('src');
				img.classList.add('d-none');
				if (placeholder) placeholder.classList.remove('d-none');
			}
			validateCreateButton();
		});

		cashdrawerForm.addEventListener('input', function(e){
			const amountField = e.target.closest('.ecash-amount');
			if (!amountField) return;
			const channel = amountField.closest('.ecash-channel-item');
			if (!channel) return;
			let amount = parseFloat(amountField.value);
			if (isNaN(amount) || amount < 0) amount = 0;
			const proofInput = channel.querySelector('input.proof-input');
			if (amount <= 0) {
				channel.classList.remove('border', 'border-danger');
				const err = channel.querySelector('.ecash-proof-error');
				if (err) {
					err.textContent = '';
					err.classList.add('d-none');
				}
			}
			validateCreateButton();
		});

		cashdrawerForm.addEventListener('focusin', function(e){
			const amountField = e.target.closest('.ecash-amount');
			if (!amountField) return;
			if ((amountField.value || '').trim() === '0.00') {
				amountField.value = '';
			}
		});

		cashdrawerForm.addEventListener('input', function(e){
			const networkField = e.target.closest('.ecash-network-name');
			if (!networkField) return;
			validateCreateButton();
		});

		// Initialize default channels
		if (!isCarryForwardLocked) {
			initializeDefaultChannels();
		}
		restorePreviousDrawerEcash();
		if (!isCarryForwardLocked) {
			restorePostedEcashBalances();
		}
		if (!isCarryForwardLocked && postedDenosPayload && postedDenosPayload !== '[]') {
			restorePostedDenominations();
		} else {
			restorePreviousDrawerDenominations();
		}
		if (isCarryForwardLocked && amountInput) {
			amountInput.value = Number(previousDrawerCarryForward.ghs_total || 0).toFixed(2);
		}
		if (btnAddEcash) {
			btnAddEcash.disabled = true;
			btnAddEcash.style.opacity = '0.5';
			btnAddEcash.style.pointerEvents = 'none';
		}
		validateCreateButton();

		// ===== DISPLAY PHP ERRORS FOR E-CASH PROOF UPLOADS =====
		const phpErrors = <?= json_encode($errors, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
		const drawerCreateErrors = Array.isArray(window.__cashdrawerCreateErrors) ? window.__cashdrawerCreateErrors.filter(Boolean) : [];
		if (drawerCreateErrors.length > 0) {
			const message = drawerCreateErrors.join('\n');
			if (window.basicAdminSwalAlert && typeof window.basicAdminSwalAlert === 'function') {
				window.basicAdminSwalAlert('Cashdrawer could not be created', message, 'error');
			} else if (window.Swal && typeof window.Swal.fire === 'function') {
				window.Swal.fire({
					icon: 'error',
					title: 'Cashdrawer could not be created',
					text: message
				});
			} else {
				alert(message);
			}
		}
		if (phpErrors && Object.keys(phpErrors).length > 0) {
			const channels = document.querySelectorAll('.ecash-channel-item');
			channels.forEach((channel) => {
				const networkName = channel.querySelector('.ecash-network-name').value.trim();
				if (networkName) {
					const slug = networkName.trim().toUpperCase().replace(/[^A-Z0-9]+/g, '_').replace(/^_+|_+$/g, '');
					const fileKey = `ecash_proof_${slug.toLowerCase()}`;
					if (phpErrors[fileKey]) {
						const err = channel.querySelector('.ecash-proof-error');
						if (err) {
							err.textContent = phpErrors[fileKey];
							err.classList.remove('d-none');
						}
						channel.classList.add('border', 'border-danger');
						channel.scrollIntoView({ behavior: 'smooth', block: 'center' });
					}
				}
			});
		}

		// Add fade-out animation
		const style = document.createElement('style');
		style.textContent = `
			@keyframes fadeOut {
				from { opacity: 1; transform: translateX(0); }
				to { opacity: 0; transform: translateX(-20px); }
			}
		`;
		document.head.appendChild(style);
	});
</script>
