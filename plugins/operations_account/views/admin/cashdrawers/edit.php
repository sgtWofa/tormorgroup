<?php if(user_can('edit_wallet')):?>
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
	</style>

	<?php if(!empty($row)):?>
		<?php
			require_once plugin_path('helpers/cashdrawer-history.php');
			$session = get_session();
			$userRole = strtolower($session->roles[0] ?? '');
			$isOpsManager = ($userRole === 'operations manager');
			$isAdmin = user_can('is_admin');

			$db = new \Core\Database;
			$historySchemaError = '';
			$hasUnclassifiedHistory = false;
			$existingDenos = [];
			$existingEcash = [];
			$existingGhsOpening = (float)($row->amount ?? 0);
			$existingCfaOpening = 0.0;
			$drawerOwnerId = (int)($row->user_id ?? 0);
			$drawerOwnerLabel = $drawerOwnerId > 0 ? ('User #' . $drawerOwnerId) : 'Unknown user';
			$filterPositiveDenominations = static function(array $rows): array {
				$out = [];
				foreach($rows as $denoRow){
					if(is_array($denoRow)){
						$denoRow = (object)$denoRow;
					}
					if(!is_object($denoRow)){
						continue;
					}
					$count = (float)($denoRow->count ?? 0);
					$volume = (float)($denoRow->volume ?? 0);
					if($count <= 0 || $volume <= 0){
						continue;
					}
					$out[] = $denoRow;
				}
				return $out;
			};
			try{
				operations_account_ensure_denomination_history_schema($db);
				$drawerDenoColumns = [];
				$drawerFxDenoColumns = [];
				if($db->table_exists(['drawer_denos'])){
					$schema = $db->query("SHOW COLUMNS FROM drawer_denos") ?: [];
					foreach($schema as $col){
						$field = strtolower(trim((string)($col->Field ?? '')));
						if($field !== ''){
							$drawerDenoColumns[$field] = true;
						}
					}
				}
				if($db->table_exists(['drawer_fx_denos'])){
					$schema = $db->query("SHOW COLUMNS FROM drawer_fx_denos") ?: [];
					foreach($schema as $col){
						$field = strtolower(trim((string)($col->Field ?? '')));
						if($field !== ''){
							$drawerFxDenoColumns[$field] = true;
						}
					}
				}
				if($db->table_exists(['drawer_denos']) && !empty($drawerDenoColumns['trans_status'])){
					$legacyRows = $db->query(
						"SELECT COUNT(*) AS total FROM drawer_denos
						 WHERE drawer_id = :drawer_id
						   AND (trans_status IS NULL OR TRIM(trans_status) = '')",
						['drawer_id' => $row->id]
					) ?: [];
					$hasUnclassifiedHistory = $hasUnclassifiedHistory || (int)($legacyRows[0]->total ?? 0) > 0;
				}
				if($db->table_exists(['drawer_fx_denos']) && !empty($drawerFxDenoColumns['trans_status'])){
					$legacyRows = $db->query(
						"SELECT COUNT(*) AS total FROM drawer_fx_denos
						 WHERE drawer_id = :drawer_id
						   AND (trans_status IS NULL OR TRIM(trans_status) = '')",
						['drawer_id' => $row->id]
					) ?: [];
					$hasUnclassifiedHistory = $hasUnclassifiedHistory || (int)($legacyRows[0]->total ?? 0) > 0;
				}

				if($db->table_exists(['drawer_denos']) && !empty($drawerDenoColumns['trans_status'])){
					$where = ["drawer_id = :drawer_id"];
					$params = ['drawer_id' => $row->id];
					$where[] = "LOWER(TRIM(COALESCE(trans_status, ''))) = 'opening snapshot'";
					$rows = $db->query(
						"SELECT denomination, SUM(`count`) AS total_count, SUM(volume) AS total_volume
						 FROM drawer_denos
						 WHERE " . implode(' AND ', $where) . "
						 GROUP BY denomination
						 ORDER BY denomination DESC",
						$params
					) ?: [];
					if (function_exists('operations_account_normalize_denomination_rows')) {
						$existingDenos = array_merge($existingDenos, $filterPositiveDenominations(operations_account_normalize_denomination_rows($rows, 'GHS')));
					} else {
						foreach($rows as $r){
							$vol = (float)($r->total_volume ?? 0);
							$cnt = (int)($r->total_count ?? 0);
							$den = (float)($r->denomination ?? 0);
							if($den <= 0) continue;
							$existingDenos[] = ['currency' => 'GHS', 'denomination' => $den, 'count' => max($cnt, 0), 'volume' => max($cnt, 0) * $den];
						}
					}
					foreach($existingDenos as $denoRow){
						$currency = strtoupper(trim((string)($denoRow->currency ?? ($denoRow['currency'] ?? 'GHS'))));
						$volume = (float)($denoRow->volume ?? ($denoRow['volume'] ?? 0));
						if($currency === 'GHS'){
							$existingGhsOpening += 0; // keep explicit header amount as source of truth
						}
					}
				}
				if($db->table_exists(['drawer_fx_denos']) && !empty($drawerFxDenoColumns['trans_status'])){
					$where = ["drawer_id = :drawer_id"];
					$params = ['drawer_id' => $row->id];
					if(!empty($drawerFxDenoColumns['currency'])){
						$where[] = "currency = 'CFA'";
					}
					$where[] = "LOWER(TRIM(COALESCE(trans_status, ''))) IN ('opening snapshot', 'opening cfa snapshot')";
					$rows = $db->query(
						"SELECT denomination, SUM(`count`) AS total_count, SUM(volume) AS total_volume
						 FROM drawer_fx_denos
						 WHERE " . implode(' AND ', $where) . "
						 GROUP BY denomination
						 ORDER BY denomination DESC",
						$params
					) ?: [];
					if(function_exists('operations_account_normalize_denomination_rows')){
						$cfaRows = $filterPositiveDenominations(operations_account_normalize_denomination_rows($rows, 'CFA'));
						$existingDenos = array_merge($existingDenos, $cfaRows);
						foreach($cfaRows as $cfaRow){
							$existingCfaOpening += (float)($cfaRow->volume ?? 0);
						}
					}else{
						foreach($rows as $r){
							$cnt = (int)($r->total_count ?? 0);
							$den = (float)($r->denomination ?? 0);
							if($den <= 0) continue;
							$volume = max($cnt, 0) * $den;
							$existingDenos[] = ['currency' => 'CFA', 'denomination' => $den, 'count' => max($cnt, 0), 'volume' => $volume];
							$existingCfaOpening += $volume;
						}
					}
				}
				if($db->table_exists(['drawer_balances'])){
					$rows = $db->query("SELECT channel_key, opening_balance, proof_image FROM drawer_balances WHERE drawer_id = :drawer_id AND channel_type = 'ecash'", ['drawer_id' => $row->id]) ?: [];
					foreach($rows as $r){
						$key = strtoupper(trim((string)($r->channel_key ?? '')));
						if($key === '') continue;
						if(strpos($key, 'ECASH_') !== 0){
							$key = 'ECASH_' . $key;
						}
						if(!user_can('is_admin') && operations_account_is_shared_channel_key($key)){
							continue;
						}
						$existingEcash[$key] = [
							'opening_balance' => (float)($r->opening_balance ?? 0),
							'proof_image' => (string)($r->proof_image ?? ''),
						];
					}
				}
				if($drawerOwnerId > 0 && $db->table_exists(['users'])){
					$ownerRow = ($db->query(
						"SELECT first_name, last_name FROM users WHERE id = :id LIMIT 1",
						['id' => $drawerOwnerId]
					) ?: [])[0] ?? null;
					if($ownerRow){
						$name = trim((string)($ownerRow->first_name ?? '') . ' ' . (string)($ownerRow->last_name ?? ''));
						if($name !== ''){
							$drawerOwnerLabel = $name . ' (User #' . $drawerOwnerId . ')';
						}
					}
				}
			}catch(\Throwable $e){
				$historySchemaError = 'Cashdrawer denomination history could not be prepared. Existing records will be preserved.';
				error_log('[EditCashdrawerView] Unable to load opening denominations: ' . $e->getMessage());
			}
		?>

		<form id="cashdrawer-edit-form" method="post" enctype="multipart/form-data">		
			<div class="row col-md-12 col-md-10 col-lg-6 mx-auto shadow p-2 rounded">
				<?=csrf()?>

				<h4 class="text-center">
					<span><img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:60px;max-height: 60px;object-fit: cover;"></span>
					Update Cash Drawer
				</h4>
				<?php if($historySchemaError !== ''): ?>
					<div class="alert alert-warning"><?=$historySchemaError?></div>
				<?php endif; ?>
				<?php if($hasUnclassifiedHistory): ?>
					<div class="alert alert-info">
						Older denomination records without opening markers are preserved as history and excluded from the opening calculator. Verify the complete opening counts before saving.
					</div>
				<?php endif; ?>

				<?php if($isOpsManager): ?>
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

				<input type="hidden" id="currency" name="currency" value="<?=old_value('currency', $row->currency ?? 'GHS')?>">

				<div class="col-md-12 mt-3">
					<label for="amount" class="form-label">Opening Cash (GHS)</label>
					<div class="input-group">
						<input type="text" class="form-control" id="amount" name="amount" value="<?=htmlspecialchars(old_value('amount', number_format((float)$existingGhsOpening, 2, '.', '')), ENT_QUOTES)?>" readonly>
						<button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#staticBackdrop">
							<i class="fa fa-calculator"></i> Launch Calculator
						</button>
					</div>
					<div class="mt-2">
						<label for="amount_cfa" class="form-label">Opening Cash (CFA)</label>
						<input type="text" class="form-control" id="amount_cfa" value="<?=htmlspecialchars(number_format((float)$existingCfaOpening, 2, '.', ''), ENT_QUOTES)?>" readonly>
						<small class="text-muted">This is derived from the CFA note rows in the calculator.</small>
					</div>
					<?php
						$existingDenosJson = json_encode($existingDenos);
						if(!is_string($existingDenosJson)) $existingDenosJson = '[]';
					?>
					<input type="hidden" id="denos" name="denos" value="<?=htmlspecialchars(old_value('denos', $existingDenosJson), ENT_QUOTES)?>">
				</div>

				<div class="col-md-12 mt-4 ecash-channels-container">
					<h5>
						<span class="me-2">E-Cash Channels</span>
						<small class="text-muted">Configure MTN, Telecel, AT and other wallets</small>
					</h5>
					<div id="ecash-channels-list"></div>
					<button type="button" class="btn-add-ecash" id="btn-add-ecash">
						<i class="fas fa-plus-circle"></i> Add E-Cash Channel
					</button>
				</div>

				<div class="col-md-12 ">
					<label for="user_id" class="form-label">In charge</label>
					<input type="hidden" name="user_id" value="<?=htmlspecialchars((string)$drawerOwnerId, ENT_QUOTES)?>">
					<select class="form-select user_id" disabled>
						<option value="<?=htmlspecialchars((string)$drawerOwnerId, ENT_QUOTES)?>" selected>
							<?= esc($drawerOwnerLabel) ?>
						</option>
					</select>
					<small class="text-muted">Editing balances keeps the original drawer owner.</small>
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
						<i class="fa-solid fa-save"></i> Update
					</button>
				</div>
			</div>
		</form>

		<div class="modal fade" id="staticBackdrop" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
		  <div class="modal-dialog">
		    <div class="modal-content">
		      <div class="modal-header">
		        <h5 class="modal-title" id="staticBackdropLabel">Currency Notes</h5>
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
		          <tbody>
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
		            <tr data-denomination="500" data-currency="CFA">
		              <td class="px-5">500CFA</td>
		              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
		              <td><input type="text" class="form-control p-1 volume-input" placeholder="Volume" readonly></td>
		            </tr>
		          </tbody>
		        </table>
		      </div>
		      <div class="modal-footer">
		        <button type="button" class="btn btn-secondary reset">Clear</button>
		        <button type="button" class="btn btn-info restore">Restore</button>
		        <button type="button" class="btn btn-primary submit" data-bs-dismiss="modal">Done</button>
		      </div>
		    </div>
		  </div>
		</div>

		<script>
			document.addEventListener("DOMContentLoaded", function () {
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

				lockDrawer();

				const ecashChannelsList = document.getElementById('ecash-channels-list');
				const btnAddEcash = document.getElementById('btn-add-ecash');
				const form = document.getElementById('cashdrawer-edit-form');
				let channelCount = 0;
				let formDirty = false;

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

				const truncateNetworkName = (name, maxLen = 15) => {
					const clean = String(name || '');
					if (clean.length <= maxLen) return clean;
					return clean.slice(0, Math.max(0, maxLen - 1)) + '…';
				};

				const existingEcash = <?= json_encode($existingEcash) ?>;
				const createChannelElement = (index, network = null) => {
					const div = document.createElement('div');
					div.className = 'ecash-channel-item';
					div.id = `ecash-channel-${index}`;
					const fullNetworkName = network ? network.name : '';
					const truncatedNetworkName = network ? truncateNetworkName(network.name) : '';
					const slug = (name) => String(name || '').trim().toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
					const makeKey = (n) => 'ECASH_' + slug(n).toUpperCase();
					const channelKey = network ? makeKey(network.name) : '';
					const preset = channelKey && existingEcash && existingEcash[channelKey] ? existingEcash[channelKey] : null;
					const presetAmt = preset ? (Number(preset.opening_balance || 0) || 0) : 0;
					const presetAmtValue = presetAmt > 0 ? presetAmt.toFixed(2) : '';
					const presetProof = preset ? String(preset.proof_image || '') : '';
					div.innerHTML = `
						<div class="ecash-network-icon">
							${network ? network.icon : '📱'}
							<label title="${fullNetworkName}">${network ? truncatedNetworkName : 'Network'}</label>
							<input type="hidden" class="ecash-network-name" value="${network ? fullNetworkName : ''}">
						</div>
						<input type="number" step="0.01" class="form-control form-control-sm ecash-amount" placeholder="Opening balance" value="${presetAmtValue}">
						<input type="file" class="d-none proof-input" accept="image/*">
						<button type="button" class="recon-proof-thumb ecash-proof-thumb" data-proof-index="${index}" aria-label="Upload proof image">
							<img class="${presetProof ? '' : 'd-none'}" alt="Proof preview" ${presetProof ? `src="<?=ROOT?>/${presetProof}"` : ''} />
							<span class="recon-proof-placeholder ${presetProof ? 'd-none' : ''}"><i class="fa-solid fa-camera"></i></span>
						</button>
						${network ? '' : `
							<button type="button" class="btn-remove-ecash" onclick="removeEcashChannel(${index})">
								<i class="fas fa-trash-alt"></i> Remove
							</button>
						`}
						<small class="text-danger d-none ecash-proof-error"></small>
					`;
					return div;
				};

				const initializeDefaultChannels = () => {
					networks.forEach((network, index) => {
						ecashChannelsList.appendChild(createChannelElement(index, network));
						channelCount++;
					});
				};

				btnAddEcash.addEventListener('click', (e) => {
					e.preventDefault();
					if (channelCount < 20) {
						ecashChannelsList.appendChild(createChannelElement(channelCount));
						channelCount++;
						bindProofButtons();
						validateCreateButton();
					} else {
						showCashdrawerAlert('Limit reached', 'Maximum 20 e-cash channels allowed.', 'warning');
					}
				});

				window.removeEcashChannel = (index) => {
					const element = document.getElementById(`ecash-channel-${index}`);
					if (element) {
						element.style.animation = 'fadeOut 0.3s ease-out';
						setTimeout(() => element.remove(), 300);
					}
				};

				initializeDefaultChannels();

				const staticBackdropEl = document.getElementById('staticBackdrop');
				const countInputs = staticBackdropEl ? staticBackdropEl.querySelectorAll(".count-input") : [];
				const denosField = form ? form.querySelector("#denos") : null;
				const submitBtn = staticBackdropEl ? staticBackdropEl.querySelector('.submit') : null;
				const createBtn = form ? form.querySelector('button[type="submit"]') : null;
				const restoreBtn = staticBackdropEl ? staticBackdropEl.querySelector('.restore') : null;
				const resetBtn = staticBackdropEl ? staticBackdropEl.querySelector('.reset') : null;
				const staticBackdropModal = (window.bootstrap && staticBackdropEl) ? new bootstrap.Modal(staticBackdropEl) : null;
				const amountInput = document.getElementById("amount");
				const amountCfaInput = document.getElementById("amount_cfa");
				const isOpsManager = <?= isset($isOpsManager) && $isOpsManager ? 'true' : 'false' ?>;
				const buyingRate = <?= isset($settings) ? (float)($settings->buying_rate ?? 0) : 0 ?>;
				const sellingRate = <?= isset($settings) ? (float)($settings->selling_rate ?? 0) : 0 ?>;
				const showCashdrawerAlert = (title, text, icon = 'info') => {
					if (window.basicAdminSwalAlert && typeof window.basicAdminSwalAlert === 'function') {
						return window.basicAdminSwalAlert(title, text, icon);
					}
					if (window.Swal && typeof window.Swal.fire === 'function') {
						return window.Swal.fire({ title: title || '', text: text || '', icon });
					}
					window.alert(String(text || title || ''));
					return Promise.resolve();
				};

				const setCreateEnabled = (enabled) => {
					if (!createBtn) return;
					createBtn.disabled = !enabled;
					if (enabled) {
						createBtn.classList.remove('disabled');
					} else {
						createBtn.classList.add('disabled');
					}
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
					if (!form || !createBtn) return;
					const denos = readDenos();
					const openingCash = Number(amountInput && amountInput.value ? amountInput.value : 0) || 0;
					let hasEcashInput = false;
					let ecashProofOk = true;
					const channels = document.querySelectorAll('.ecash-channel-item');
					channels.forEach((channel) => {
						const networkNameEl = channel.querySelector('.ecash-network-name');
						const amountEl = channel.querySelector('.ecash-amount');
						const proofEl = channel.querySelector('input.proof-input');
						const existingProofImg = channel.querySelector('.ecash-proof-thumb img');
						const hasExistingProof = !!(existingProofImg && existingProofImg.getAttribute('src'));
						const networkName = networkNameEl ? networkNameEl.value.trim() : '';
						let amount = amountEl ? parseFloat(amountEl.value) : 0;
						if (isNaN(amount) || amount < 0) amount = 0;
						const usesChannel = !!networkName;
						if (usesChannel && amount > 0) {
							hasEcashInput = true;
							const hasNewProof = !!(proofEl && proofEl.files && proofEl.files[0]);
							if (!hasNewProof && !hasExistingProof) {
								ecashProofOk = false;
							}
						}
					});
					const hasAnyInput = denos.any || hasEcashInput;
					const ghsOk = denos.ghs <= 0 || openingCash > 0;
					setCreateEnabled((hasAnyInput || formDirty) && ghsOk && ecashProofOk);
				};

				const computeRowVolume = (rowEl) => {
					const denom = parseFloat(rowEl.getAttribute('data-denomination') || '0') || 0;
					const countEl = rowEl.querySelector('.count-input');
					const volumeEl = rowEl.querySelector('.volume-input');
					const rawCount = countEl ? String(countEl.value ?? '').trim() : '';
					const hasCountValue = rawCount !== '';
					const count = hasCountValue ? (parseInt(rawCount, 10) || 0) : 0;
					const vol = denom * count;
					if (volumeEl) volumeEl.value = hasCountValue ? vol.toFixed(2) : '';
					return vol;
				};

				const syncAmountDisplaysFromDenos = () => {
					const denos = readDenos();
					if (amountInput) amountInput.value = denos.ghs > 0 ? denos.ghs.toFixed(2) : '';
					if (amountCfaInput) amountCfaInput.value = denos.cfa > 0 ? denos.cfa.toFixed(2) : '';
				};

				const hydrateDenosIntoModal = () => {
					let parsed = [];
					try {
						parsed = JSON.parse(denosField ? (denosField.value || '[]') : '[]');
					} catch (e) {
						parsed = [];
					}
					if (!Array.isArray(parsed)) parsed = [];

					const buckets = {};
					parsed.forEach((r) => {
						const key = String((r && r.currency) ? r.currency : 'GHS').toUpperCase() + '|' + String((r && r.denomination) ? r.denomination : '');
						if (!Array.isArray(buckets[key])) {
							buckets[key] = [];
						}
						buckets[key].push(r);
					});

					const rows = Array.from(document.querySelectorAll('#staticBackdrop tbody tr[data-denomination][data-currency]'));
					rows.forEach((rowEl) => {
						const currency = String(rowEl.getAttribute('data-currency') || 'GHS').toUpperCase();
						const denom = String(rowEl.getAttribute('data-denomination') || '');
						const key = currency + '|' + denom;
						const queue = Array.isArray(buckets[key]) ? buckets[key] : [];
						const found = queue.length ? queue.shift() : null;
						const c = rowEl.querySelector('.count-input');
						if (c) {
							const foundCount = found && found.count !== undefined && found.count !== null ? Number(found.count) : 0;
							c.value = foundCount > 0 ? String(foundCount) : '';
						}
						computeRowVolume(rowEl);
					});
				};

				const serializeDenos = () => {
					const rows = Array.from(document.querySelectorAll('#staticBackdrop tbody tr[data-denomination][data-currency]'));
					const out = [];
					rows.forEach((rowEl) => {
						const currency = String(rowEl.getAttribute('data-currency') || 'GHS').toUpperCase();
						const denom = parseFloat(rowEl.getAttribute('data-denomination') || '0') || 0;
						const countEl = rowEl.querySelector('.count-input');
						const count = parseInt((countEl && countEl.value) ? countEl.value : '0', 10) || 0;
						const volume = denom * count;
						if (count > 0 && volume > 0) {
							out.push({ currency, denomination: denom, count, volume });
						}
					});
					if (denosField) denosField.value = JSON.stringify(out);
					syncAmountDisplaysFromDenos();
					validateCreateButton();
				};

				countInputs.forEach((input) => {
					input.addEventListener('input', (e) => {
						formDirty = true;
						const row = e.target.closest('tr');
						if (!row) return;
						computeRowVolume(row);
					});
				});

				if (ecashChannelsList) {
					ecashChannelsList.addEventListener('input', function(event) {
						if (!(event.target instanceof Element) || !event.target.matches('.ecash-amount')) return;
						formDirty = true;
						validateCreateButton();
					});
				}

				if (submitBtn) {
					submitBtn.addEventListener('click', function(){
						serializeDenos();
					});
				}
				if (resetBtn) {
					resetBtn.addEventListener('click', function(){
						formDirty = true;
						const rows = Array.from(document.querySelectorAll('#staticBackdrop tbody tr[data-denomination][data-currency]'));
						rows.forEach((rowEl) => {
							const c = rowEl.querySelector('.count-input');
							const v = rowEl.querySelector('.volume-input');
							if (c) c.value = '';
							if (v) v.value = '';
						});
						serializeDenos();
					});
				}
				if (restoreBtn) {
					restoreBtn.addEventListener('click', function(){
						hydrateDenosIntoModal();
					});
				}

				if (staticBackdropEl) {
					staticBackdropEl.addEventListener('show.bs.modal', function(){
						hydrateDenosIntoModal();
					});
				}

				const bindProofButtons = () => {
					const buttons = document.querySelectorAll('.ecash-proof-thumb');
					buttons.forEach((btn) => {
						if (btn.dataset && btn.dataset.bound) return;
						btn.dataset.bound = '1';
						btn.addEventListener('click', function(){
							const item = btn.closest('.ecash-channel-item');
							if (!item) return;
							const input = item.querySelector('input.proof-input');
							if (!input) return;
							input.click();
						});
					});
					const inputs = document.querySelectorAll('.ecash-channel-item input.proof-input');
					inputs.forEach((input) => {
						if (input.dataset && input.dataset.bound) return;
						input.dataset.bound = '1';
						input.addEventListener('change', function(){
							const item = input.closest('.ecash-channel-item');
							if (!item) return;
							const img = item.querySelector('button.ecash-proof-thumb img');
							const ph = item.querySelector('button.ecash-proof-thumb .recon-proof-placeholder');
							if (window.AppImageCropper && typeof window.AppImageCropper.handleLegacyChange === 'function') {
								window.AppImageCropper.handleLegacyChange({ currentTarget: input }, {
									previewEl: img,
									placeholderEl: ph,
									maxBytes: window.AppImageCropper.MAX_BYTES,
									modalTitle: 'Crop cashdrawer proof',
									errorTitle: 'Invalid proof image',
								}).then(function(){
									validateCreateButton();
								});
								return;
							}
							const file = input.files && input.files[0] ? input.files[0] : null;
							if (!file) return;
							const url = URL.createObjectURL(file);
							if (img) {
								img.src = url;
								img.classList.remove('d-none');
							}
							if (ph) ph.classList.add('d-none');
							validateCreateButton();
						});
					});
				};

				bindProofButtons();
				hydrateDenosIntoModal();
				syncAmountDisplaysFromDenos();
				validateCreateButton();

				// ===== SERIALIZE E-CASH CHANNELS ON FORM SUBMIT (same as add.php) =====
				form.addEventListener('submit', function(e) {
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
						const networkInput = channel.querySelector('.ecash-network-name');
						const amountInput = channel.querySelector('.ecash-amount');
						if (!networkInput || !amountInput) return;
						const networkName = networkInput.value.trim();
						let amount = parseFloat(amountInput.value);
						if (isNaN(amount) || amount < 0) amount = 0;
						if (networkName) {
							const slug = buildSlug(networkName);
							const fieldName = `ecash_${slug.toLowerCase()}`;
							ecashData[fieldName] = amount;

							const proofInput = channel.querySelector('input.proof-input');
							const existingProofImg = channel.querySelector('.ecash-proof-thumb img');
							const hasExistingProof = !!(existingProofImg && existingProofImg.getAttribute('src'));
							if (proofInput) {
								proofInput.name = `ecash_proof_${slug.toLowerCase()}`;
								if (amount > 0) {
									const hasNewProof = !!(proofInput.files && proofInput.files[0]);
									if (!hasNewProof && !hasExistingProof) {
										proofOk = false;
										if (!firstInvalidChannel) firstInvalidChannel = channel;
										setChannelProofError(channel, `Upload photo proof for ${networkName} because opening balance is greater than 0.`);
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
						showCashdrawerAlert(
							'Proof required',
							'Some e-cash channels have opening balance greater than 0. Please upload the photo proof for those channels before submitting.',
							'warning'
						);
						return;
					}

					Object.keys(ecashData).forEach(key => {
						const input = document.createElement('input');
						input.type = 'hidden';
						input.name = key;
						input.value = ecashData[key];
						form.appendChild(input);
					});
				});

				// Disable add-ecash button (same behavior as add.php)
				if (btnAddEcash) {
					btnAddEcash.disabled = true;
					btnAddEcash.style.opacity = '0.5';
					btnAddEcash.style.pointerEvents = 'none';
				}
			});
		</script>

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
		Access denied. You dont have permission for this action
	</div>
<?php endif?>
