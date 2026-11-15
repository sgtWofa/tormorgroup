<?php if(user_can('add_payment')): ?>
<?php
	$errors = get_value('errors');
	if (!is_array($errors)) {
		$errors = [];
	}
	$bankOptions = backoffice_commission_bank_options();
	$networkOptions = ['MTN', 'TELECEL', 'AT', 'G-MONEY'];
	$formSession = get_session();
	$formUserId = (int)($formSession->id ?? 0);
	$formRouteConfig = get_value();
	$formAdminRoute = (string)($formRouteConfig['admin_route'] ?? 'admin');
	$formPluginRoute = (string)($formRouteConfig['plugin_route'] ?? 'back_office');
	$formWithdrawerUsers = backoffice_bank_transfer_candidates_excluding([$formUserId]);
	$formSecondPartyUsers = backoffice_bank_transfer_candidates_excluding([$formUserId]);
	$formThirdPartyUsers = backoffice_bank_transfer_third_party_candidates();
	$formAssignmentUserIds = array_merge(
		backoffice_bank_transfer_user_ids($formWithdrawerUsers),
		backoffice_bank_transfer_user_ids($formSecondPartyUsers),
		backoffice_bank_transfer_user_ids($formThirdPartyUsers)
	);
	$formBankTransferAssignmentsMap = backoffice_user_sim_assignments_map(array_values(array_unique($formAssignmentUserIds)));
	$canCreateFromBank = backoffice_user_can_create_from_bank_transfer($formSession);
	$oldTransactionType = (string)old_value('transaction_type');
	$defaultTransactionType = $oldTransactionType !== ''
		? $oldTransactionType
		: (in_array((string)($_GET['transaction_type'] ?? ''), ['To Bank', 'From Bank', 'To Vault', 'From Vault'], true) ? (string)$_GET['transaction_type'] : '');
	$defaultSourceVaultId = $oldTransactionType !== '' ? (int)old_value('source_vault_id') : (int)($_GET['source_vault_id'] ?? 0);
	$defaultDestinationVaultId = $oldTransactionType !== '' ? (int)old_value('destination_vault_id') : (int)($_GET['destination_vault_id'] ?? 0);
?>
<style>
	#bankTransferForm .form-control,
	#bankTransferForm .form-select{ border-radius: 6px !important; }
	#bankTransferForm .form-label{ color: gray; font-size: 12px; }
	.bt-section-card{
		border: 1px solid var(--bs-border-color);
		border-radius: 14px;
		padding: 1rem;
		background: rgba(var(--bs-secondary-rgb, 148, 163, 184), 0.04);
	}
	.bt-upload-box{
		border: 1px dashed var(--bs-border-color);
		border-radius: 12px;
		padding: .9rem;
		background: rgba(var(--bs-secondary-rgb, 148, 163, 184), 0.05);
	}
	.bt-upload-preview{
		display: grid;
		grid-template-columns: 100px 1fr;
		gap: .9rem;
		align-items: center;
		margin-top: .85rem;
	}
	.bt-upload-thumb{
		width: 100px;
		height: 100px;
		border-radius: 12px;
		border: 1px solid var(--bs-border-color);
		overflow: hidden;
		background: rgba(var(--bs-secondary-rgb, 148, 163, 184), 0.08);
		display: flex;
		align-items: center;
		justify-content: center;
	}
	.bt-upload-thumb img{ width:100%; height:100%; object-fit:cover; }
	.bt-upload-empty{ font-size:.82rem; text-align:center; color:var(--bs-secondary-color); padding:.5rem; }
	@media (max-width: 575.98px){
		.bt-upload-preview{ grid-template-columns: 1fr; }
	}
</style>

<form method="post" enctype="multipart/form-data" autocomplete="off" id="bankTransferForm">
	<div class="row col-sm-12 col-md-9 col-lg-7 mx-auto shadow p-3 rounded">
		<?=csrf()?>
		<h4 class="text-center mb-3">
			<span><img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:60px;max-height:60px;object-fit:cover;"></span>
			Add TO &amp; FROM
		</h4>

		<div class="col-md-12 mb-3">
			<label for="transaction_type" class="form-label">Transaction type</label>
			<select class="form-select" name="transaction_type" id="transaction_type">
				<option value="">---Select---</option>
				<option <?=old_select('transaction_type','To Bank')?><?=($defaultTransactionType === 'To Bank') ? ' selected' : ''?> value="To Bank">To Bank</option>
				<?php if($canCreateFromBank): ?>
					<option <?=old_select('transaction_type','From Bank')?><?=($defaultTransactionType === 'From Bank') ? ' selected' : ''?> value="From Bank">From Bank</option>
					<option <?=old_select('transaction_type','To Vault')?><?=($defaultTransactionType === 'To Vault') ? ' selected' : ''?> value="To Vault">To Vault</option>
				<?php endif; ?>
				<option <?=old_select('transaction_type','From Vault')?><?=($defaultTransactionType === 'From Vault') ? ' selected' : ''?> value="From Vault">From Vault</option>
			</select>
			<?php if(!empty($errors['transaction_type'])): ?><small class="text-danger"><?=$errors['transaction_type']?></small><?php endif; ?>
			<?php if(!empty($errors['db_error'])): ?><small class="text-danger d-block">DB error: <?=esc((string)$errors['db_error'])?></small><?php endif; ?>
		</div>

		<div class="col-md-12 mb-3">
			<label for="bank_name" class="form-label">Select Bank</label>
			<select class="form-select" name="bank_name" id="bank_name">
				<option value="">---Select---</option>
				<?php foreach($bankOptions as $bankName): ?>
					<option <?=old_select('bank_name',$bankName)?> value="<?=esc($bankName)?>"><?=esc($bankName)?></option>
				<?php endforeach; ?>
			</select>
			<?php if(!empty($errors['bank_name'])): ?><small class="text-danger"><?=$errors['bank_name']?></small><?php endif; ?>
		</div>

		<div class="col-md-12 mb-3 d-none" id="btToBankSection">
			<div class="bt-section-card">
				<div class="row g-3">
					<div class="col-md-12 d-none" id="btSourceVaultWrap">
						<label for="source_vault_id" class="form-label">Source vault</label>
						<select class="form-select" name="source_vault_id" id="source_vault_id">
							<option value="">---Select vault---</option>
							<?php foreach(($vaultOptions ?? []) as $vault): ?>
								<option data-branch-id="<?=esc((string)($vault->branch_id ?? 0))?>" <?=((int)old_value('source_vault_id') === (int)($vault->id ?? 0) || $defaultSourceVaultId === (int)($vault->id ?? 0)) ? ' selected' : ''?> value="<?=esc((string)($vault->id ?? ''))?>"><?=esc((string)($vault->name ?? 'Vault'))?> · <?=esc((string)($vault->branch_name ?? ''))?></option>
							<?php endforeach; ?>
						</select>
						<small class="text-muted">GHS notes are deducted from this vault after 2nd-party verification.</small>
						<?php if(!empty($errors['source_vault_id'])): ?><small class="text-danger d-block"><?=$errors['source_vault_id']?></small><?php endif; ?>
					</div>
					<div class="col-md-12">
						<label for="transfer_category" class="form-label">Category</label>
						<select class="form-select" name="transfer_category" id="transfer_category">
							<option value="">---Select---</option>
							<option <?=old_select('transfer_category','E-Cash')?> value="E-Cash">E-Cash</option>
							<option <?=old_select('transfer_category','Bank Float')?> value="Bank Float">Bank Float</option>
						</select>
						<?php if(!empty($errors['transfer_category'])): ?><small class="text-danger"><?=$errors['transfer_category']?></small><?php endif; ?>
					</div>

					<div class="col-md-12 d-none" id="btNetworkWrap">
						<label for="network_name" class="form-label">Select Network</label>
						<select class="form-select" name="network_name" id="network_name">
							<option value="">---Select---</option>
							<?php foreach($networkOptions as $networkName): ?>
								<option <?=old_select('network_name',$networkName)?> value="<?=esc($networkName)?>"><?=esc($networkName)?></option>
							<?php endforeach; ?>
						</select>
						<?php if(!empty($errors['network_name'])): ?><small class="text-danger"><?=$errors['network_name']?></small><?php endif; ?>
					</div>

					<div class="col-md-12">
						<label for="third_party_user_id" class="form-label">Assign to 3rd party</label>
						<select class="form-select" name="third_party_user_id" id="third_party_user_id">
							<option value="">---Select---</option>
							<?php foreach($formThirdPartyUsers as $user): ?>
								<option data-branch-id="<?=esc((string)($user->branch_id ?? 0))?>" <?=old_select('third_party_user_id',$user->id ?? '')?> value="<?=esc((string)($user->id ?? ''))?>">
									<?=esc(trim((string)(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))))?> (<?=esc((string)($user->roles[0] ?? ''))?>)
								</option>
							<?php endforeach; ?>
						</select>
						<small class="text-muted">Only admins or general managers with today's cashdrawer are listed. For From Vault, the 3rd party must also belong to the source vault's branch.</small>
						<?php if(!empty($errors['third_party_user_id'])): ?><small class="text-danger d-block"><?=$errors['third_party_user_id']?></small><?php endif; ?>
					</div>

					<div class="col-md-12">
						<div id="btAssignedDetails" class="alert alert-secondary mb-0">Select transaction details and a 3rd party to load account or SIM details.</div>
					</div>
				</div>
			</div>
		</div>

		<div class="col-md-12 mb-3 d-none" id="btFromBankSection">
			<div class="bt-section-card">
				<div class="row g-3">
					<div class="col-md-12 d-none" id="btDestinationVaultWrap">
						<label for="destination_vault_id" class="form-label">Destination vault</label>
						<select class="form-select" name="destination_vault_id" id="destination_vault_id">
							<option value="">---Select vault---</option>
							<?php foreach(($vaultOptions ?? []) as $vault): ?>
								<option data-branch-id="<?=esc((string)($vault->branch_id ?? 0))?>" <?=((int)old_value('destination_vault_id') === (int)($vault->id ?? 0) || $defaultDestinationVaultId === (int)($vault->id ?? 0)) ? ' selected' : ''?> value="<?=esc((string)($vault->id ?? ''))?>"><?=esc((string)($vault->name ?? 'Vault'))?> · <?=esc((string)($vault->branch_name ?? ''))?></option>
							<?php endforeach; ?>
						</select>
						<small class="text-muted">The bank withdrawal is deposited to this vault after the assigned 2nd party verifies the cash and denomination count.</small>
						<?php if(!empty($errors['destination_vault_id'])): ?><small class="text-danger d-block"><?=$errors['destination_vault_id']?></small><?php endif; ?>
					</div>
					<div class="col-md-12">
						<label for="withdrawing_user_id" class="form-label">Assign user to withdraw</label>
						<select class="form-select" name="withdrawing_user_id" id="withdrawing_user_id">
							<option value="">---Select---</option>
							<?php foreach($formWithdrawerUsers as $user): ?>
								<option data-branch-id="<?=esc((string)($user->branch_id ?? 0))?>" <?=old_select('withdrawing_user_id',$user->id ?? '')?> value="<?=esc((string)($user->id ?? ''))?>">
									<?=esc(trim((string)(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))))?> (<?=esc((string)($user->roles[0] ?? ''))?>)
								</option>
							<?php endforeach; ?>
						</select>
						<small class="text-muted">Choose another user with today's cashdrawer to handle the bank withdrawal. For To Vault, the user must belong to the destination vault's branch.</small>
						<?php if(!empty($errors['withdrawing_user_id'])): ?><small class="text-danger d-block"><?=$errors['withdrawing_user_id']?></small><?php endif; ?>
					</div>

					<div class="col-md-6">
						<label for="cheque_number" class="form-label">Cheque Number</label>
						<input type="text" class="form-control" name="cheque_number" id="cheque_number" value="<?=esc((string)old_value('cheque_number'))?>" placeholder="Enter cheque number">
						<?php if(!empty($errors['cheque_number'])): ?><small class="text-danger d-block"><?=$errors['cheque_number']?></small><?php endif; ?>
					</div>

					<div class="col-md-6">
						<label for="cheque_date" class="form-label">Date on Cheque</label>
						<input type="date" class="form-control" name="cheque_date" id="cheque_date" value="<?=esc((string)old_value('cheque_date'))?>">
						<?php if(!empty($errors['cheque_date'])): ?><small class="text-danger d-block"><?=$errors['cheque_date']?></small><?php endif; ?>
					</div>

					<div class="col-md-12">
						<label class="form-label">Upload picture of Cheque</label>
						<div class="bt-upload-box">
							<input type="file" name="cheque_photo" id="cheque_photo" class="d-none js-create-upload-input" accept="image/*" data-preview-image="chequePreviewImage" data-preview-empty="chequePreviewEmpty" data-preview-meta="chequePreviewMeta">
							<label for="cheque_photo" class="btn btn-outline-secondary btn-sm">
								<i class="fa-solid fa-image"></i> Choose Cheque Image
							</label>
							<div class="bt-upload-preview">
								<div class="bt-upload-thumb">
									<div class="bt-upload-empty" id="chequePreviewEmpty">No cheque image selected</div>
									<img id="chequePreviewImage" class="d-none" alt="Cheque preview">
								</div>
								<div id="chequePreviewMeta" class="small text-muted">A thumbnail and file details will appear here.</div>
							</div>
						</div>
						<?php if(!empty($errors['cheque_photo'])): ?><small class="text-danger d-block"><?=$errors['cheque_photo']?></small><?php endif; ?>
					</div>
				</div>
			</div>
		</div>

		<div class="col-md-12 mb-3">
			<label for="amount" class="form-label" id="btAmountLabel">Amount / Load</label>
			<div class="input-group" id="btAmountGroup" data-bs-toggle="modal" data-bs-target="#btDenoModal" style="cursor:pointer;">
				<span class="input-group-text"><i class="fa-solid fa-calculator text-warning"></i></span>
				<input name="amount" value="<?=old_value('amount')?>" type="number" step="0.01" class="form-control" id="amount" placeholder="Use calculator" readonly>
			</div>
			<small class="text-muted" id="btAmountHint">Use the currency calculator to populate the amount.</small>
			<input type="hidden" id="denos_json" name="denos_json" value="<?=htmlspecialchars((string)old_value('denos_json'), ENT_QUOTES)?>" />
			<?php if(!empty($errors['amount'])): ?><small class="text-danger d-block"><?=$errors['amount']?></small><?php endif; ?>
			<?php if(!empty($errors['denos_json'])): ?><small class="text-danger d-block"><?=$errors['denos_json']?></small><?php endif; ?>
		</div>

		<div class="col-md-12 mb-3">
			<label for="second_party_user_id" class="form-label">Assign to 2nd party</label>
			<select class="form-select" name="second_party_user_id" id="second_party_user_id"></select>
			<small class="text-muted" id="btSecondPartyHint">Only other users with today's cashdrawer are listed here.</small>
			<?php if(!empty($errors['second_party_user_id'])): ?><small class="text-danger d-block"><?=$errors['second_party_user_id']?></small><?php endif; ?>
		</div>

		<div class="col-md-12 mb-2">
			<label for="notes" class="form-label">Notes</label>
			<textarea rows="3" class="form-control" name="notes" id="notes" placeholder="Optional notes for this movement"><?=old_value('notes')?></textarea>
		</div>

		<div class="d-flex justify-content-between mt-3">
			<a href="<?=ROOT?>/<?=$formAdminRoute?>/<?=$formPluginRoute?>" class="btn btn-primary">
				<i class="fa-solid fa-chevron-left"></i> Back
			</a>
			<button class="btn btn-danger" type="submit">
				<i class="fa-solid fa-save"></i> Save
			</button>
		</div>
	</div>
</form>

<div class="modal fade" id="btDenoModal" tabindex="-1" aria-hidden="true">
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
						<?php foreach($denomsGhs as $deno): ?>
							<tr data-denomination="<?=$deno?>">
								<td>GH&#x20B5;<?=rtrim(rtrim(number_format($deno,2), '0'), '.')?></td>
								<td><input type="number" min="0" class="form-control form-control-sm bt-deno-count" /></td>
								<td><input type="text" class="form-control form-control-sm bt-deno-volume" readonly /></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
					<tfoot>
						<tr>
							<th>Total</th>
							<th><input type="text" class="form-control form-control-sm" id="btDenoCount" readonly /></th>
							<th><input type="text" class="form-control form-control-sm" id="btDenoTotal" readonly /></th>
						</tr>
					</tfoot>
				</table>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-outline-secondary" id="btDenoReset">Reset</button>
				<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
				<button type="button" class="btn btn-primary" id="btDenoApply">Apply</button>
			</div>
		</div>
	</div>
</div>

<script>
const bankTransferAssignmentsMap = <?=json_encode($formBankTransferAssignmentsMap, JSON_UNESCAPED_SLASHES)?>;
const bankTransferSecondPartyUsers = <?=json_encode(array_map(function($user){
	return [
		'id' => (int)($user->id ?? 0),
		'branch_id' => (int)($user->branch_id ?? 0),
		'name' => trim((string)(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))),
		'role' => (string)($user->roles[0] ?? ''),
	];
}, $formSecondPartyUsers), JSON_UNESCAPED_SLASHES)?>;
const bankTransferThirdPartyUsers = <?=json_encode(array_map(function($user){
	return [
		'id' => (int)($user->id ?? 0),
		'branch_id' => (int)($user->branch_id ?? 0),
	];
}, $formThirdPartyUsers), JSON_UNESCAPED_SLASHES)?>;
const bankTransferCreatorId = <?=json_encode($formUserId)?>;

function btEscapeHtml(value) {
	return String(value ?? '').replace(/[&<>"']/g, function(char){
		return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char];
	});
}

function btNormalizeKey(value) {
	let v = String(value || '').trim().toUpperCase();
	v = v.replace(/[^A-Z0-9]+/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
	if (v === 'GMONEY' || v === 'G-MONEY') return 'G-MONEY';
	return v;
}

function btPickAssignmentRow(rows, category, bankName, networkName) {
	rows = Array.isArray(rows) ? rows : [];
	const categoryKey = btNormalizeKey(category);
	const bankKey = btNormalizeKey(bankName);
	const networkKey = btNormalizeKey(networkName);
	if (categoryKey === 'BANK-FLOAT') {
		return rows.find(row => String(row.sim_type || '').toLowerCase() === 'bank' && btNormalizeKey(row.network || '') === bankKey && String(row.account_number || '').trim() !== '')
			|| rows.find(row => String(row.sim_type || '').toLowerCase() === 'bank' && String(row.account_number || '').trim() !== '')
			|| null;
	}
	const matches = rows.filter(row => btNormalizeKey(row.network || '') === networkKey && !['bank', 'evd'].includes(String(row.sim_type || '').toLowerCase()));
	if (networkKey === 'MTN' || networkKey === 'G-MONEY') return matches.find(row => String(row.phone_number || '').trim() !== '') || matches[0] || null;
	if (networkKey === 'TELECEL') return matches.find(row => String(row.till_number || '').trim() !== '' || String(row.shortcode || '').trim() !== '') || matches[0] || null;
	if (networkKey === 'AT') return matches.find(row => String(row.phone_number || '').trim() !== '' || String(row.till_number || '').trim() !== '' || String(row.shortcode || '').trim() !== '') || matches[0] || null;
	return matches[0] || null;
}

function btRenderAssignedDetails() {
	const panel = document.getElementById('btAssignedDetails');
	const category = document.getElementById('transfer_category').value;
	const bankName = document.getElementById('bank_name').value;
	const networkName = document.getElementById('network_name').value;
	const thirdParty = document.getElementById('third_party_user_id');
	const transactionType = document.getElementById('transaction_type').value;
	if (!panel) return;
	if (!['To Bank', 'From Vault'].includes(transactionType)) {
		panel.className = 'alert alert-secondary mb-0';
		panel.innerHTML = '3rd-party account or SIM details are only needed for To Bank and From Vault entries.';
		return;
	}
	if (!thirdParty.value) {
		panel.className = 'alert alert-secondary mb-0';
		panel.innerHTML = 'Select transaction details and a 3rd party to load account or SIM details.';
		return;
	}
	if (!category) {
		panel.className = 'alert alert-secondary mb-0';
		panel.innerHTML = 'Select the category to display the 3rd-party details.';
		return;
	}
	if (category === 'E-Cash' && !networkName) {
		panel.className = 'alert alert-secondary mb-0';
		panel.innerHTML = 'Select the network to display the 3rd-party details.';
		return;
	}
	const rows = bankTransferAssignmentsMap[String(thirdParty.value)] || [];
	const row = btPickAssignmentRow(rows, category, bankName, networkName);
	const label = thirdParty.options[thirdParty.selectedIndex] ? thirdParty.options[thirdParty.selectedIndex].textContent : 'selected 3rd party';
	if (!row) {
		panel.className = 'alert alert-warning mb-0';
		panel.innerHTML = '<strong>Assigned details:</strong> No saved account or SIM details found for ' + btEscapeHtml(label) + '.';
		return;
	}
	let lines = [];
	if (category === 'Bank Float') {
		lines = [{ label: 'Bank', value: bankName }, { label: 'Account number', value: row.account_number || '' }];
	} else if (networkName === 'MTN' || networkName === 'G-MONEY') {
		lines = [{ label: 'Phone number', value: row.phone_number || '' }];
	} else if (networkName === 'TELECEL') {
		lines = [{ label: 'Till number', value: row.till_number || '' }, { label: 'Shortcode', value: row.shortcode || '' }];
	} else if (networkName === 'AT') {
		lines = [{ label: 'Phone number', value: row.phone_number || '' }, { label: 'Till number', value: row.till_number || '' }, { label: 'Shortcode', value: row.shortcode || '' }];
	}
	panel.className = 'alert alert-info mb-0';
	panel.innerHTML = '<div class="fw-semibold mb-1">Assigned details for ' + btEscapeHtml(label) + '</div>' + lines.map(function(line){
		const value = String(line.value || '').trim();
		return '<div><strong>' + btEscapeHtml(line.label) + ':</strong> ' + (value ? btEscapeHtml(value) : '<span class="text-warning">Not saved</span>') + '</div>';
	}).join('');
}

function btPopulateSecondPartyOptions() {
	const secondParty = document.getElementById('second_party_user_id');
	const withdrawer = document.getElementById('withdrawing_user_id');
	const transactionType = document.getElementById('transaction_type').value;
	const vaultBranchId = btSelectedVaultBranchId(transactionType);
	const currentValue = String(secondParty.dataset.currentValue || secondParty.value || '');
	const excluded = [String(bankTransferCreatorId)];
	if (['From Bank', 'To Vault'].includes(transactionType) && withdrawer && withdrawer.value) {
		excluded.push(String(withdrawer.value));
	}
	secondParty.innerHTML = '<option value="">---Select---</option>';
	bankTransferSecondPartyUsers.forEach(function(user){
		const userId = String(user.id || '');
		if (!userId || excluded.includes(userId)) return;
		if (vaultBranchId && Number(user.branch_id || 0) !== vaultBranchId) return;
		const option = document.createElement('option');
		option.value = userId;
		option.dataset.branchId = String(user.branch_id || 0);
		option.textContent = user.name + (user.role ? ' (' + user.role + ')' : '');
		if (currentValue && currentValue === userId) option.selected = true;
		secondParty.appendChild(option);
	});
}

function btSelectedVaultBranchId(transactionType) {
	const vaultSelect = transactionType === 'From Vault'
		? document.getElementById('source_vault_id')
		: (transactionType === 'To Vault' ? document.getElementById('destination_vault_id') : null);
	if (!vaultSelect || !vaultSelect.value) return 0;
	const option = vaultSelect.options[vaultSelect.selectedIndex];
	return Number(option ? option.dataset.branchId || 0 : 0);
}

function btFilterPartyOptions(select, allowedBranchIds, branchId) {
	if (!select) return;
	Array.from(select.options).forEach(function(option){
		if (!option.value) return;
		const allowedBranchId = allowedBranchIds[String(option.value)];
		const showOption = !branchId || Number(allowedBranchId || option.dataset.branchId || 0) === branchId;
		option.hidden = !showOption;
		option.disabled = !showOption;
		if (!showOption && option.selected) select.value = '';
	});
}

function btSyncVisibility() {
	const transactionType = document.getElementById('transaction_type').value;
	const category = document.getElementById('transfer_category').value;
	const toBankSection = document.getElementById('btToBankSection');
	const fromBankSection = document.getElementById('btFromBankSection');
	const sourceVaultWrap = document.getElementById('btSourceVaultWrap');
	const destinationVaultWrap = document.getElementById('btDestinationVaultWrap');
	const sourceVault = document.getElementById('source_vault_id');
	const destinationVault = document.getElementById('destination_vault_id');
	const networkWrap = document.getElementById('btNetworkWrap');
	const amountInput = document.getElementById('amount');
	const amountGroup = document.getElementById('btAmountGroup');
	const amountLabel = document.getElementById('btAmountLabel');
	const amountHint = document.getElementById('btAmountHint');
	const denosInput = document.getElementById('denos_json');
	const secondPartyHint = document.getElementById('btSecondPartyHint');

	const toBankFlow = ['To Bank', 'From Vault'].includes(transactionType);
	const fromBankFlow = ['From Bank', 'To Vault'].includes(transactionType);
	toBankSection.classList.toggle('d-none', !toBankFlow);
	fromBankSection.classList.toggle('d-none', !fromBankFlow);
	sourceVaultWrap.classList.toggle('d-none', transactionType !== 'From Vault');
	destinationVaultWrap.classList.toggle('d-none', transactionType !== 'To Vault');
	sourceVault.required = transactionType === 'From Vault';
	destinationVault.required = transactionType === 'To Vault';
	if (transactionType !== 'From Vault') sourceVault.value = '';
	if (transactionType !== 'To Vault') destinationVault.value = '';
	networkWrap.classList.toggle('d-none', !(toBankFlow && category === 'E-Cash'));

	if (fromBankFlow) {
		amountLabel.textContent = 'Amount on Cheque';
		amountHint.textContent = 'Enter the cheque amount exactly as written on the cheque.';
		amountInput.readOnly = false;
		amountInput.placeholder = 'Enter amount on cheque';
		amountGroup.removeAttribute('data-bs-toggle');
		amountGroup.removeAttribute('data-bs-target');
		amountGroup.style.cursor = 'default';
		if (denosInput) denosInput.value = '';
		secondPartyHint.textContent = transactionType === 'To Vault'
			? 'Choose a 2nd party with today\'s cashdrawer from the destination vault branch.'
			: 'Choose another user, different from the creator and the withdrawing user, with today\'s cashdrawer.';
	} else {
		amountLabel.textContent = 'Amount / Load';
		amountHint.textContent = 'Use the currency calculator to populate the amount.';
		amountInput.readOnly = true;
		amountInput.placeholder = 'Use calculator';
		amountGroup.setAttribute('data-bs-toggle', 'modal');
		amountGroup.setAttribute('data-bs-target', '#btDenoModal');
		amountGroup.style.cursor = 'pointer';
		secondPartyHint.textContent = transactionType === 'From Vault'
			? 'Choose a 2nd party with today\'s cashdrawer from the source vault branch.'
			: 'Only other users with today\'s cashdrawer are listed here.';
	}

	btPopulateSecondPartyOptions();
	const vaultBranchId = btSelectedVaultBranchId(transactionType);
	const thirdPartyBranches = {};
	bankTransferThirdPartyUsers.forEach(function(user){
		thirdPartyBranches[String(user.id)] = Number(user.branch_id || 0);
	});
	btFilterPartyOptions(document.getElementById('third_party_user_id'), thirdPartyBranches, transactionType === 'From Vault' ? vaultBranchId : 0);
	const withdrawerBranches = {};
	bankTransferSecondPartyUsers.forEach(function(user){
		withdrawerBranches[String(user.id)] = Number(user.branch_id || 0);
	});
	btFilterPartyOptions(document.getElementById('withdrawing_user_id'), withdrawerBranches, transactionType === 'To Vault' ? vaultBranchId : 0);
	btRenderAssignedDetails();
}

document.addEventListener('DOMContentLoaded', function(){
	const secondParty = document.getElementById('second_party_user_id');
	secondParty.dataset.currentValue = <?=json_encode((string)old_value('second_party_user_id'))?>;

	['transaction_type','bank_name','transfer_category','network_name','third_party_user_id','withdrawing_user_id','source_vault_id','destination_vault_id'].forEach(function(id){
		const el = document.getElementById(id);
		if (el) el.addEventListener('change', btSyncVisibility);
	});
	btSyncVisibility();

	(function(){
		const modalEl = document.getElementById('btDenoModal');
		if(!modalEl) return;
		const denosInput = document.getElementById('denos_json');
		const amountInput = document.getElementById('amount');
		const countField = document.getElementById('btDenoCount');
		const totalField = document.getElementById('btDenoTotal');
		const resetBtn = document.getElementById('btDenoReset');
		const applyBtn = document.getElementById('btDenoApply');
		const denomRows = modalEl.querySelectorAll('tbody tr[data-denomination]');

		function recalc(){
			let totalCount = 0;
			let totalAmount = 0;
			denomRows.forEach(function(row){
				const denomination = Number(row.getAttribute('data-denomination') || 0);
				const countInput = row.querySelector('.bt-deno-count');
				const volumeInput = row.querySelector('.bt-deno-volume');
				const count = Number(countInput ? countInput.value || 0 : 0);
				const volume = denomination * count;
				totalCount += count;
				totalAmount += volume;
				if (volumeInput) volumeInput.value = volume > 0 ? volume.toFixed(2) : '';
			});
			if (countField) countField.value = totalCount ? String(totalCount) : '';
			if (totalField) totalField.value = totalAmount ? totalAmount.toFixed(2) : '';
			return totalAmount;
		}

		function loadFromHidden(){
			let rows = [];
			try { rows = JSON.parse(String(denosInput.value || '[]')); } catch(e) { rows = []; }
			denomRows.forEach(function(row){
				const denomination = Number(row.getAttribute('data-denomination') || 0);
				const countInput = row.querySelector('.bt-deno-count');
				const match = rows.find(function(item){ return Number(item.denomination || 0) === denomination; });
				if (countInput) countInput.value = match ? Number(match.count || 0) : '';
			});
			recalc();
		}

		denomRows.forEach(function(row){
			const countInput = row.querySelector('.bt-deno-count');
			if (countInput) countInput.addEventListener('input', recalc);
		});
		if (resetBtn) resetBtn.addEventListener('click', function(){
			denomRows.forEach(function(row){
				const countInput = row.querySelector('.bt-deno-count');
				const volumeInput = row.querySelector('.bt-deno-volume');
				if (countInput) countInput.value = '';
				if (volumeInput) volumeInput.value = '';
			});
			recalc();
		});
		if (applyBtn) applyBtn.addEventListener('click', function(){
			const payload = [];
			denomRows.forEach(function(row){
				const denomination = Number(row.getAttribute('data-denomination') || 0);
				const countInput = row.querySelector('.bt-deno-count');
				const count = Number(countInput ? countInput.value || 0 : 0);
				if (count > 0) {
					payload.push({ currency:'GHS', denomination:denomination, count:count, volume:Number((denomination * count).toFixed(2)) });
				}
			});
			const total = recalc();
			if (denosInput) denosInput.value = JSON.stringify(payload);
			if (amountInput) amountInput.value = total ? total.toFixed(2) : '';
			const modalInstance = bootstrap.Modal.getInstance(modalEl);
			if (modalInstance) modalInstance.hide();
		});
		modalEl.addEventListener('show.bs.modal', function(){
			if (!['To Bank', 'From Vault'].includes(document.getElementById('transaction_type').value)) {
				return false;
			}
			loadFromHidden();
		});
		loadFromHidden();
	})();

	(function(){
		const formatBytes = function(bytes){
			const value = Number(bytes || 0);
			if (!(value > 0)) return '0 KB';
			if (value >= 1024 * 1024) return (value / (1024 * 1024)).toFixed(2) + ' MB';
			return Math.max(1, Math.round(value / 1024)) + ' KB';
		};
		document.querySelectorAll('.js-create-upload-input').forEach(function(input){
			const imageEl = document.getElementById(String(input.dataset.previewImage || ''));
			const emptyEl = document.getElementById(String(input.dataset.previewEmpty || ''));
			const metaEl = document.getElementById(String(input.dataset.previewMeta || ''));
			if (!imageEl || !emptyEl || !metaEl) return;
			input.addEventListener('change', function(){
				const file = input.files && input.files[0] ? input.files[0] : null;
				if (!file) {
					imageEl.classList.add('d-none');
					imageEl.removeAttribute('src');
					emptyEl.classList.remove('d-none');
					metaEl.textContent = 'A thumbnail and file details will appear here.';
					return;
				}
				metaEl.innerHTML = '<div class="fw-semibold">' + btEscapeHtml(file.name) + '</div><div>Type: ' + btEscapeHtml(file.type || 'image') + '</div><div>Size: ' + btEscapeHtml(formatBytes(file.size || 0)) + '</div>';
				const reader = new FileReader();
				reader.onload = function(event){
					imageEl.src = String(event.target && event.target.result ? event.target.result : '');
					imageEl.classList.remove('d-none');
					emptyEl.classList.add('d-none');
				};
				reader.readAsDataURL(file);
			});
		});
	})();
});
</script>
<?php else: ?>
	<div class="alert alert-danger text-center">Access denied.</div>
<?php endif; ?>
