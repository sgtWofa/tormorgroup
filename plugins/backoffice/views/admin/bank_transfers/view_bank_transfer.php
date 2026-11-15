<?php
$session = get_session();
$currentUserId = (int)($session->id ?? 0);
$transactionTypeLower = strtolower(trim((string)($row->transaction_type ?? '')));
$status = trim((string)($row->transaction_status ?? 'Pending 2nd Party'));
$statusLower = strtolower($status);
$badgeClass = 'badge bg-secondary';
if ($statusLower === 'approved') $badgeClass = 'badge bg-success';
elseif ($statusLower === 'rejected') $badgeClass = 'badge bg-danger';
elseif (strpos($statusLower, 'pending') !== false) $badgeClass = 'badge bg-warning text-dark';
$isFromBank = backoffice_bank_transfer_is_from_bank_flow($transactionTypeLower);
$isToBank = backoffice_bank_transfer_is_to_bank_flow($transactionTypeLower);
$isToVault = $transactionTypeLower === 'to vault';
$isWithdrawerStep = $isFromBank && $statusLower === 'pending withdrawal' && (int)($row->withdrawing_user_id ?? 0) === $currentUserId;
$isSecondPartyStep = $statusLower === 'pending 2nd party' && (int)($row->second_party_user_id ?? 0) === $currentUserId;
$isCreatorReceiptStep = $isToBank && $statusLower === 'pending creator receipt' && (int)($row->created_by ?? 0) === $currentUserId;
$isThirdPartyStep = $isToBank
	&& in_array($statusLower, ['pending 3rd party', 'pending creator receipt'], true)
	&& (int)($row->third_party_user_id ?? 0) === $currentUserId
	&& backoffice_user_can_bank_transfer_third_party($session);
$isLegacySecondPartyFinalStep = $isFromBank && $statusLower === 'pending creator approval' && (int)($row->second_party_user_id ?? 0) === $currentUserId;
$thirdPartyStatusLower = strtolower(trim((string)($row->third_party_status ?? '')));
$canReassignBankTransfer = (backoffice_user_is_admin($session) || (int)($row->created_by ?? 0) === $currentUserId)
	&& $statusLower !== 'approved'
	&& $thirdPartyStatusLower !== 'approved';
$oldPost = get_value('old_post');
if (!is_array($oldPost)) {
	$oldPost = [];
}
$snapshot = [];
try {
	$snapshot = json_decode((string)($row->assignment_snapshot_json ?? ''), true);
	if (!is_array($snapshot)) $snapshot = [];
} catch (\Throwable $e) {
	$snapshot = [];
}
$snapshotLines = $snapshot['lines'] ?? [];
$errors = get_value('errors');
if (!is_array($errors)) {
	$errors = [];
}
$fromBankDenoJsonValue = (string)old_value('denos_json');
if ($fromBankDenoJsonValue === '') {
	$fromBankDenoJsonValue = (string)($row->denos_json ?? '[]');
}
$fromBankRejectReasonValue = (string)old_value('reject_reason');
$thirdPartyRejectReasonValue = (string)old_value('reject_reason');
$denoPayload = [];
$denoTotals = [];
if (!empty($row->denos_json)) {
	try {
		$decodedDenos = json_decode((string)$row->denos_json, true);
		if (is_array($decodedDenos)) {
			foreach ($decodedDenos as $entry) {
				$currency = strtoupper(trim((string)($entry['currency'] ?? 'GHS')));
				if ($currency === '') {
					$currency = 'GHS';
				}
				$denomination = (float)($entry['denomination'] ?? 0);
				$count = (int)($entry['count'] ?? 0);
				$volume = (float)($entry['volume'] ?? ($denomination * $count));
				if ($denomination <= 0 || $count <= 0) {
					continue;
				}
				if (!isset($denoPayload[$currency])) {
					$denoPayload[$currency] = [];
				}
				$denoPayload[$currency][] = [
					'denomination' => $denomination,
					'count' => $count,
					'volume' => $volume,
				];
				$denoTotals[$currency] = ($denoTotals[$currency] ?? 0) + $volume;
			}
			foreach ($denoPayload as $currency => $entries) {
				usort($entries, function(array $a, array $b): int {
					return (float)$b['denomination'] <=> (float)$a['denomination'];
				});
				$denoPayload[$currency] = $entries;
			}
		}
	} catch (\Throwable $e) {
		$denoPayload = [];
		$denoTotals = [];
	}
}
$treasuryImageUrl = !empty($row->treasury_slip_photo) ? get_image($row->treasury_slip_photo) : '';
$bankReceiptImageUrl = !empty($row->creator_bank_receipt_photo) ? get_image($row->creator_bank_receipt_photo) : '';
$chequeImageUrl = !empty($row->cheque_photo) ? get_image($row->cheque_photo) : '';
$reassignSecondPartyValue = (string)($oldPost['second_party_user_id'] ?? ($row->second_party_user_id ?? ''));
$reassignThirdPartyValue = (string)($oldPost['third_party_user_id'] ?? ($row->third_party_user_id ?? ''));
$reassignWithdrawerValue = (string)($oldPost['withdrawing_user_id'] ?? ($row->withdrawing_user_id ?? ''));
?>

<style>
	.bt-upload-card{
		border: 1px dashed var(--bs-border-color);
		border-radius: 14px;
		padding: 1rem;
		background: rgba(var(--bs-secondary-rgb, 148, 163, 184), 0.06);
	}
	.bt-upload-actions{
		display: flex;
		align-items: center;
		gap: .75rem;
		flex-wrap: wrap;
	}
	.bt-upload-trigger{
		display: inline-flex;
		align-items: center;
		gap: .5rem;
		padding: .6rem .9rem;
		border-radius: 10px;
		border: 1px solid var(--bs-border-color);
		background: var(--bs-body-bg);
		cursor: pointer;
		font-weight: 600;
	}
	.bt-upload-trigger:hover{
		border-color: var(--bs-primary);
		color: var(--bs-primary);
	}
	.bt-upload-hint{
		font-size: .88rem;
		color: var(--bs-secondary-color);
	}
	.bt-upload-preview{
		margin-top: 1rem;
		display: grid;
		grid-template-columns: 120px 1fr;
		gap: 1rem;
		align-items: start;
	}
	.bt-upload-thumb{
		width: 120px;
		height: 120px;
		border-radius: 12px;
		border: 1px solid var(--bs-border-color);
		background: rgba(var(--bs-secondary-rgb, 148, 163, 184), 0.08);
		display: flex;
		align-items: center;
		justify-content: center;
		overflow: hidden;
	}
	.bt-upload-thumb img{
		width: 100%;
		height: 100%;
		object-fit: cover;
	}
	.bt-upload-empty{
		text-align: center;
		padding: .75rem;
		font-size: .82rem;
		color: var(--bs-secondary-color);
		line-height: 1.35;
	}
	.bt-upload-meta{
		min-height: 120px;
		display: flex;
		flex-direction: column;
		justify-content: center;
	}
	.bt-upload-meta .title{
		font-weight: 700;
		margin-bottom: .35rem;
	}
	.bt-upload-meta .sub{
		font-size: .9rem;
		color: var(--bs-secondary-color);
	}
	.bt-proof-stack{
		display: grid;
		gap: 1rem;
	}
	.bt-proof-tile{
		border: 1px solid var(--bs-border-color);
		border-radius: 14px;
		padding: .85rem;
		background: rgba(var(--bs-secondary-rgb, 148, 163, 184), 0.05);
	}
	.bt-proof-thumb{
		display: block;
		width: 100%;
		aspect-ratio: 4 / 3;
		border-radius: 12px;
		overflow: hidden;
		border: 1px solid var(--bs-border-color);
		background: rgba(var(--bs-secondary-rgb, 148, 163, 184), 0.08);
	}
	.bt-proof-thumb img{
		width: 100%;
		height: 100%;
		object-fit: cover;
	}
	.bt-proof-foot{
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: .75rem;
		margin-top: .75rem;
	}
	.bt-form-errors{
		border-left: 4px solid var(--bs-danger);
	}
	@media (max-width: 575.98px){
		.bt-upload-preview{
			grid-template-columns: 1fr;
		}
		.bt-upload-thumb{
			width: 100%;
			max-width: 180px;
			height: 180px;
		}
	}
</style>

<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
	<div>
		<h4 class="mb-0">TO &amp; FROM</h4>
		<small class="text-muted">Transaction #<?=esc((string)$row->id)?></small>
	</div>
	<div class="d-flex gap-2 flex-wrap">
		<a class="btn btn-outline-secondary" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">Back to Backoffice</a>
		<span class="<?=$badgeClass?> align-self-center"><?=esc($status)?></span>
	</div>
</div>

<div class="row g-3">
	<div class="col-lg-8">
		<?php if(!empty($errors)): ?>
			<div class="alert alert-danger bt-form-errors shadow-sm">
				<div class="fw-semibold mb-1">Please fix the highlighted issue<?=count($errors) > 1 ? 's' : ''?>.</div>
				<?php foreach($errors as $error): ?>
					<div class="small"><?=esc((string)$error)?></div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<div class="card border-0 shadow-sm">
			<div class="card-body">
				<div class="row g-3">
					<div class="col-md-4">
						<div class="small text-muted">Transaction type</div>
						<div class="fw-semibold"><?=esc((string)($row->transaction_type ?? ''))?></div>
					</div>
					<div class="col-md-4">
						<div class="small text-muted">Category</div>
						<div class="fw-semibold"><?=esc((string)($row->transfer_category ?? ($isFromBank ? 'Cheque Withdrawal' : '')))?></div>
					</div>
					<div class="col-md-4">
						<div class="small text-muted"><?=$isFromBank ? 'Amount on Cheque' : 'Amount / Load'?></div>
						<div class="fw-semibold"><?=number_format((float)($row->amount ?? 0), 2)?></div>
					</div>
					<div class="col-md-4">
						<div class="small text-muted">Bank</div>
						<div class="fw-semibold"><?=esc((string)($row->bank_name ?? ''))?></div>
					</div>
					<?php if((int)($row->source_vault_id ?? 0) > 0): ?>
						<div class="col-md-4">
							<div class="small text-muted">Cash source</div>
							<div class="fw-semibold">Vault · <?=esc((string)($row->source_vault_name ?? ('#' . (int)$row->source_vault_id)))?></div>
						</div>
					<?php elseif((int)($row->destination_vault_id ?? 0) > 0): ?>
						<div class="col-md-4">
							<div class="small text-muted">Deposit destination</div>
							<div class="fw-semibold">Vault · <?=esc((string)($row->destination_vault_name ?? ('#' . (int)$row->destination_vault_id)))?></div>
						</div>
					<?php endif; ?>
					<div class="col-md-4">
						<div class="small text-muted">Network</div>
						<div class="fw-semibold"><?=esc((string)($row->network_name ?? ($isFromBank ? 'N/A' : '')))?></div>
					</div>
					<div class="col-md-4">
						<div class="small text-muted">Created</div>
						<div class="fw-semibold"><?=esc(get_date_time($row->date_created ?? ''))?></div>
					</div>
					<?php if($isFromBank): ?>
						<div class="col-md-4">
							<div class="small text-muted">Cheque Number</div>
							<div class="fw-semibold"><?=esc((string)($row->cheque_number ?? ''))?></div>
						</div>
						<div class="col-md-4">
							<div class="small text-muted">Date on Cheque</div>
							<div class="fw-semibold"><?=esc((string)($row->cheque_date ?? ''))?></div>
						</div>
						<div class="col-md-4">
							<div class="small text-muted">Withdrawal Status</div>
							<div class="fw-semibold"><?=esc((string)($row->withdrawal_status ?? 'Pending'))?></div>
						</div>
					<?php endif; ?>
					<div class="col-md-12">
						<div class="small text-muted">Notes</div>
						<div><?=nl2br(esc((string)($row->notes ?? '')))?></div>
					</div>
				</div>
			</div>
		</div>

		<div class="card border-0 shadow-sm mt-3">
			<div class="card-body">
				<h5 class="mb-3">Assigned parties</h5>
				<div class="row g-3">
					<div class="col-md-4">
						<div class="small text-muted">Creator</div>
						<div class="fw-semibold"><?=esc(trim((string)(($row->creator_first_name ?? '') . ' ' . ($row->creator_last_name ?? ''))))?></div>
					</div>
					<div class="col-md-4">
						<div class="small text-muted">2nd party</div>
						<div class="fw-semibold"><?=esc(trim((string)(($row->second_party_first_name ?? '') . ' ' . ($row->second_party_last_name ?? ''))))?></div>
						<div class="small text-muted"><?=esc((string)($row->second_party_status ?? 'Pending'))?></div>
					</div>
					<?php if($isFromBank): ?>
						<div class="col-md-4">
							<div class="small text-muted">3rd party</div>
							<div class="fw-semibold"><?=esc(trim((string)(($row->withdrawer_first_name ?? '') . ' ' . ($row->withdrawer_last_name ?? ''))))?></div>
							<div class="small text-muted">Withdrawal status: <?=esc((string)($row->withdrawal_status ?? 'Pending'))?></div>
						</div>
					<?php else: ?>
						<div class="col-md-4">
							<div class="small text-muted">3rd party</div>
							<div class="fw-semibold"><?=esc(trim((string)(($row->third_party_first_name ?? '') . ' ' . ($row->third_party_last_name ?? ''))))?></div>
							<div class="small text-muted"><?=esc((string)($row->third_party_status ?? 'Pending'))?></div>
						</div>
					<?php endif; ?>
					<?php if($canReassignBankTransfer): ?>
						<div class="col-md-12">
							<div class="border rounded-3 p-3 bg-body-tertiary">
								<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
									<div>
										<div class="fw-semibold">Reassign parties</div>
										<div class="small text-muted">Admins and the creator can update assignments until final approval.</div>
									</div>
									<span class="badge bg-warning-subtle text-warning-emphasis">Pending final approval</span>
								</div>
								<form method="post" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/bank_transfer_reassign/<?=esc((string)$row->id)?>">
									<?=csrf()?>
									<div class="row g-3 align-items-end">
										<div class="col-md-<?= $isFromBank ? '6' : '6' ?>">
											<label class="form-label">2nd party</label>
											<select name="second_party_user_id" class="form-select">
												<option value="">---Select---</option>
												<?php foreach(($secondPartyReassignUsers ?? []) as $user): ?>
													<option value="<?=esc((string)($user->id ?? ''))?>" <?= $reassignSecondPartyValue === (string)($user->id ?? '') ? 'selected' : '' ?>>
														<?=esc(trim((string)(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))))?>
													</option>
												<?php endforeach; ?>
											</select>
											<?php if(!empty($errors['second_party_user_id'])): ?><small class="text-danger d-block"><?=$errors['second_party_user_id']?></small><?php endif; ?>
										</div>
										<?php if($isFromBank): ?>
											<div class="col-md-6">
												<label class="form-label">3rd party / withdrawing user</label>
												<select
													name="withdrawing_user_id"
													class="form-select"
													<?=strtolower(trim((string)($row->withdrawal_status ?? ''))) === 'withdrawn' ? 'disabled' : ''?>
												>
													<option value="">---Select---</option>
													<?php foreach(($withdrawerReassignUsers ?? []) as $user): ?>
														<option value="<?=esc((string)($user->id ?? ''))?>" <?= $reassignWithdrawerValue === (string)($user->id ?? '') ? 'selected' : '' ?>>
															<?=esc(trim((string)(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))))?>
														</option>
													<?php endforeach; ?>
												</select>
												<?php if(strtolower(trim((string)($row->withdrawal_status ?? ''))) === 'withdrawn'): ?>
													<input type="hidden" name="withdrawing_user_id" value="<?=esc((string)($row->withdrawing_user_id ?? ''))?>">
													<small class="text-muted d-block">Withdrawal is already confirmed, so only the 2nd party can be reassigned.</small>
												<?php endif; ?>
												<?php if(!empty($errors['withdrawing_user_id'])): ?><small class="text-danger d-block"><?=$errors['withdrawing_user_id']?></small><?php endif; ?>
											</div>
										<?php else: ?>
											<div class="col-md-6">
												<label class="form-label">3rd party</label>
												<select name="third_party_user_id" class="form-select">
													<option value="">---Select---</option>
													<?php foreach(($thirdPartyUsers ?? []) as $user): ?>
														<option value="<?=esc((string)($user->id ?? ''))?>" <?= $reassignThirdPartyValue === (string)($user->id ?? '') ? 'selected' : '' ?>>
															<?=esc(trim((string)(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))))?> (<?=esc((string)($user->roles[0] ?? ''))?>)
														</option>
													<?php endforeach; ?>
												</select>
												<?php if(!empty($errors['third_party_user_id'])): ?><small class="text-danger d-block"><?=$errors['third_party_user_id']?></small><?php endif; ?>
											</div>
										<?php endif; ?>
										<div class="col-12">
											<button type="submit" class="btn btn-outline-primary">
												<i class="fa-solid fa-user-pen"></i> Update assignments
											</button>
										</div>
									</div>
								</form>
							</div>
						</div>
					<?php endif; ?>
					<?php if(!empty($snapshotLines)): ?>
						<div class="col-md-12">
							<div class="alert alert-info mb-0">
								<div class="fw-semibold mb-2">Assigned detail snapshot</div>
								<?php foreach($snapshotLines as $line): ?>
									<div><strong><?=esc((string)($line['label'] ?? 'Detail'))?>:</strong> <?=esc((string)($line['value'] ?? ''))?></div>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>
					<?php if(!empty($row->third_party_reject_reason)): ?>
						<div class="col-md-12">
							<div class="alert alert-danger mb-0">
								<strong>Reject reason:</strong> <?=nl2br(esc((string)$row->third_party_reject_reason))?>
							</div>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<div class="card border-0 shadow-sm mt-3">
			<div class="card-body">
				<h5 class="mb-3">Denomination Breakdown</h5>
				<?php if (!empty($denoPayload)): ?>
					<div class="row g-3">
						<?php foreach ($denoPayload as $currency => $entries): ?>
							<?php $currencyTotal = (float)($denoTotals[$currency] ?? 0); ?>
							<div class="col-12">
								<div class="border rounded-3 overflow-hidden">
									<div class="bg-body-tertiary px-3 py-2 d-flex justify-content-between align-items-center">
										<span class="fw-semibold text-uppercase small"><?=esc($currency)?> Notes</span>
										<span class="badge bg-primary-subtle text-primary">Total: <?=number_format($currencyTotal, 2)?></span>
									</div>
									<div class="table-responsive">
										<table class="table table-sm mb-0 align-middle text-nowrap">
											<thead class="text-uppercase text-muted small">
												<tr>
													<th>Denomination</th>
													<th>Count</th>
													<th>Volume</th>
												</tr>
											</thead>
											<tbody>
												<?php foreach ($entries as $entry): ?>
													<tr>
														<td><?=number_format((float)$entry['denomination'], ($currency === 'CFA') ? 0 : 2)?></td>
														<td><?=number_format((int)$entry['count'])?></td>
														<td class="fw-semibold"><?=number_format((float)$entry['volume'], 2)?></td>
													</tr>
												<?php endforeach; ?>
											</tbody>
										</table>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php else: ?>
					<div class="alert alert-warning mb-0">
						<?=$isFromBank ? 'No denomination breakdown has been submitted yet for this cheque withdrawal.' : 'No currency denomination breakdown was saved on this transaction.'?>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<?php if($isSecondPartyStep): ?>
			<div class="card border-0 shadow-sm mt-3">
				<div class="card-body">
					<h5 class="mb-3">2nd Party Final Review</h5>
					<?php if($isFromBank): ?>
						<p class="text-muted mb-3">Upload the signed treasury slip, confirm the denominations received, and complete the transaction. <?= $isToVault ? 'Approval updates the selected vault immediately.' : 'Approval updates your cashdrawer immediately.' ?></p>
					<?php else: ?>
						<p class="text-muted mb-3">Upload the signed treasury slip, compare it against the denomination breakdown below, then either send it to the 3rd party or reject it with a clear reason.</p>
					<?php endif; ?>
					<form method="post" enctype="multipart/form-data" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/bank_transfer_verify/<?=esc((string)$row->id)?>">
						<?=csrf()?>
						<input type="hidden" name="verification_action" id="fromBankVerificationAction" value="verify">
						<?php if($isFromBank): ?>
							<div class="mb-3">
								<label class="form-label">Denominations to <?= $isToVault ? 'deposit into the vault' : 'add to your cashdrawer' ?></label>
								<div class="input-group" id="fromBankDenoGroup" data-bs-toggle="modal" data-bs-target="#fromBankDenoModal" style="cursor:pointer;">
									<span class="input-group-text"><i class="fa-solid fa-calculator text-warning"></i></span>
									<input type="text" class="form-control" id="fromBankDenoAmount" value="<?=number_format((float)($row->amount ?? 0), 2)?>" readonly>
								</div>
								<small class="text-muted d-block mt-1">Use the calculator to capture the exact notes received. The total must match the bank withdrawal amount.</small>
								<input type="hidden" name="denos_json" id="fromBankDenoJson" value="<?=htmlspecialchars($fromBankDenoJsonValue, ENT_QUOTES)?>">
								<?php if(!empty($errors['denos_json'])): ?><small class="text-danger d-block"><?=$errors['denos_json']?></small><?php endif; ?>
							</div>
							<div class="alert alert-warning d-none" id="fromBankMismatchAlert"></div>
							<div class="mb-3 d-none" id="fromBankRejectReasonWrap">
								<label class="form-label">Reject reason</label>
								<textarea class="form-control" name="reject_reason" id="fromBankRejectReason" rows="3" placeholder="State clearly why the denominations do not match the amount withdrawn."><?=esc($fromBankRejectReasonValue)?></textarea>
								<small class="text-muted d-block mt-1">Explain the shortage, overage, or any issue found during recheck.</small>
								<?php if(!empty($errors['reject_reason'])): ?><small class="text-danger d-block"><?=$errors['reject_reason']?></small><?php endif; ?>
							</div>
						<?php endif; ?>
						<div class="mb-3">
							<label class="form-label">Signed treasury slip photo</label>
							<div class="bt-upload-card">
								<input
									type="file"
									name="treasury_slip_photo"
									class="d-none js-bank-upload-input"
									id="treasury_slip_photo_input"
									accept="image/*"
									data-preview-image="treasurySlipPreviewImage"
									data-preview-empty="treasurySlipPreviewEmpty"
									data-preview-meta="treasurySlipPreviewMeta"
								>
								<div class="bt-upload-actions">
									<label for="treasury_slip_photo_input" class="bt-upload-trigger">
										<i class="fa-solid fa-image"></i>
										<span>Choose Image</span>
									</label>
									<div class="bt-upload-hint">Preview the signed slip before you submit it.</div>
								</div>
								<div class="bt-upload-preview">
									<div class="bt-upload-thumb">
										<div class="bt-upload-empty" id="treasurySlipPreviewEmpty">
											<i class="fa-regular fa-image d-block mb-2 fs-4"></i>
											No image selected yet
										</div>
										<img id="treasurySlipPreviewImage" alt="Treasury slip preview" class="d-none">
									</div>
									<div class="bt-upload-meta" id="treasurySlipPreviewMeta">
										<div class="title">Treasury slip preview</div>
										<div class="sub">Selected file details and thumbnail will appear here.</div>
									</div>
								</div>
							</div>
							<?php if(!empty($errors['treasury_slip_photo'])): ?><small class="text-danger"><?=$errors['treasury_slip_photo']?></small><?php endif; ?>
						</div>
						<?php if(!$isFromBank): ?>
							<div class="mb-3">
								<label class="form-label">Reject reason</label>
								<textarea class="form-control" name="reject_reason" rows="3" placeholder="State clearly why the treasury slip does not tally the denomination breakdown."><?=esc($fromBankRejectReasonValue)?></textarea>
								<?php if(!empty($errors['reject_reason'])): ?><small class="text-danger d-block"><?=$errors['reject_reason']?></small><?php endif; ?>
							</div>
							<div class="d-flex gap-2 flex-wrap">
								<button type="submit" class="btn btn-success" onclick="document.getElementById('fromBankVerificationAction').value='verify'">Submit to 3rd Party</button>
								<button type="submit" class="btn btn-danger" onclick="document.getElementById('fromBankVerificationAction').value='reject'">Reject Transaction</button>
							</div>
						<?php else: ?>
							<button type="submit" class="btn btn-success" id="fromBankVerifyButton"><?=$isToVault ? 'Approve &amp; Deposit to Vault' : 'Approve &amp; Update Cashdrawer'?></button>
						<?php endif; ?>
					</form>
				</div>
			</div>
		<?php endif; ?>

		<?php if($isWithdrawerStep): ?>
			<div class="card border-0 shadow-sm mt-3">
				<div class="card-body">
					<h5 class="mb-3">Bank Receipt Upload</h5>
					<p class="text-muted mb-3">Upload the bank receipt after the withdrawal is completed. The transaction will then move to the 2nd party for final review.</p>
					<form method="post" enctype="multipart/form-data" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/bank_transfer_withdraw/<?=esc((string)$row->id)?>">
						<?=csrf()?>
						<div class="mb-3">
							<label class="form-label">Bank receipt photo</label>
							<div class="bt-upload-card">
								<input
									type="file"
									name="creator_bank_receipt_photo"
									class="d-none js-bank-upload-input"
									id="creator_bank_receipt_photo_input"
									accept="image/*"
									required
									data-preview-image="bankReceiptPreviewImage"
									data-preview-empty="bankReceiptPreviewEmpty"
									data-preview-meta="bankReceiptPreviewMeta"
								>
								<div class="bt-upload-actions">
									<label for="creator_bank_receipt_photo_input" class="bt-upload-trigger">
										<i class="fa-solid fa-receipt"></i>
										<span>Choose Receipt</span>
									</label>
									<div class="bt-upload-hint">Use a clear image so the 2nd party can review it quickly.</div>
								</div>
								<div class="bt-upload-preview">
									<div class="bt-upload-thumb">
										<div class="bt-upload-empty" id="bankReceiptPreviewEmpty">
											<i class="fa-regular fa-image d-block mb-2 fs-4"></i>
											No image selected yet
										</div>
										<img id="bankReceiptPreviewImage" alt="Bank receipt preview" class="d-none">
									</div>
									<div class="bt-upload-meta" id="bankReceiptPreviewMeta">
										<div class="title">Bank receipt preview</div>
										<div class="sub">Selected file details and thumbnail will appear here.</div>
									</div>
								</div>
							</div>
							<?php if(!empty($errors['creator_bank_receipt_photo'])): ?><small class="text-danger"><?=$errors['creator_bank_receipt_photo']?></small><?php endif; ?>
						</div>
						<button type="submit" class="btn btn-primary">Upload Receipt &amp; Send to 2nd Party</button>
					</form>
				</div>
			</div>
		<?php endif; ?>

		<?php if($isCreatorReceiptStep): ?>
			<?php
				$thirdPartyAssignments = $thirdPartyAssignmentsMap ?? [];
				$networkOptions = ['MTN', 'TELECEL', 'AT', 'G-MONEY'];
			?>
			<div class="card border-0 shadow-sm mt-3">
				<div class="card-body">
					<h5 class="mb-3">Creator Bank Receipt Upload</h5>
					<form method="post" enctype="multipart/form-data" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/bank_transfer_creator_receipt/<?=esc((string)$row->id)?>" id="creatorReceiptForm">
						<?=csrf()?>
						<div class="mb-3">
							<label class="form-label">Assign to 3rd party</label>
							<select name="third_party_user_id" id="receipt_third_party_user_id" class="form-select">
								<option value="">---Select---</option>
								<?php foreach(($thirdPartyUsers ?? []) as $user): ?>
									<option value="<?=esc((string)($user->id ?? ''))?>" <?= ((string)($row->third_party_user_id ?? '') === (string)($user->id ?? '')) ? 'selected' : '' ?>>
										<?=esc(trim((string)(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))))?> (<?=esc((string)($user->roles[0] ?? ''))?>)
									</option>
								<?php endforeach; ?>
							</select>
							<?php if(!empty($errors['third_party_user_id'])): ?><small class="text-danger"><?=$errors['third_party_user_id']?></small><?php endif; ?>
						</div>
						<div class="mb-3">
							<div id="receiptAssignedDetails" class="alert alert-secondary mb-0">Select the 3rd party to refresh account or SIM details.</div>
						</div>
						<div class="mb-3">
							<label class="form-label">Bank receipt photo</label>
							<div class="bt-upload-card">
								<input
									type="file"
									name="creator_bank_receipt_photo"
									class="d-none js-bank-upload-input"
									id="creator_bank_receipt_photo_input"
									accept="image/*"
									required
									data-preview-image="bankReceiptPreviewImage"
									data-preview-empty="bankReceiptPreviewEmpty"
									data-preview-meta="bankReceiptPreviewMeta"
								>
								<div class="bt-upload-actions">
									<label for="creator_bank_receipt_photo_input" class="bt-upload-trigger">
										<i class="fa-solid fa-receipt"></i>
										<span>Choose Image</span>
									</label>
									<div class="bt-upload-hint">Use a clear image so the 3rd party can review it quickly.</div>
								</div>
								<div class="bt-upload-preview">
									<div class="bt-upload-thumb">
										<div class="bt-upload-empty" id="bankReceiptPreviewEmpty">
											<i class="fa-regular fa-image d-block mb-2 fs-4"></i>
											No image selected yet
										</div>
										<img id="bankReceiptPreviewImage" alt="Bank receipt preview" class="d-none">
									</div>
									<div class="bt-upload-meta" id="bankReceiptPreviewMeta">
										<div class="title">Bank receipt preview</div>
										<div class="sub">Selected file details and thumbnail will appear here.</div>
									</div>
								</div>
							</div>
							<?php if(!empty($errors['creator_bank_receipt_photo'])): ?><small class="text-danger"><?=$errors['creator_bank_receipt_photo']?></small><?php endif; ?>
						</div>
						<button type="submit" class="btn btn-primary">Upload Receipt &amp; Submit</button>
					</form>
				</div>
			</div>
			<script>
			(function(){
				const assignmentsMap = <?=json_encode($thirdPartyAssignmentsMap ?? [], JSON_UNESCAPED_SLASHES)?>;
				const thirdParty = document.getElementById('receipt_third_party_user_id');
				const panel = document.getElementById('receiptAssignedDetails');
				function normalizeKey(value){
					let v = String(value || '').trim().toUpperCase();
					v = v.replace(/[^A-Z0-9]+/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
					if (v === 'GMONEY' || v === 'G-MONEY') return 'G-MONEY';
					return v;
				}
				function pickRow(rows, category, bankName, networkName){
					rows = Array.isArray(rows) ? rows : [];
					const categoryKey = normalizeKey(category);
					const bankKey = normalizeKey(bankName);
					const networkKey = normalizeKey(networkName);
					if (categoryKey === 'BANK-FLOAT') {
						return rows.find(row => String(row.sim_type || '').toLowerCase() === 'bank' && normalizeKey(row.network || '') === bankKey && String(row.account_number || '').trim() !== '')
							|| rows.find(row => String(row.sim_type || '').toLowerCase() === 'bank' && String(row.account_number || '').trim() !== '')
							|| null;
					}
					const matches = rows.filter(row => normalizeKey(row.network || '') === networkKey && !['bank', 'evd'].includes(String(row.sim_type || '').toLowerCase()));
					return matches[0] || null;
				}
				function escapeHtml(value){
					return String(value ?? '').replace(/[&<>"']/g, function(char){
						return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char];
					});
				}
				function render(){
					if (!panel || !thirdParty) return;
					if (!thirdParty.value) {
						panel.className = 'alert alert-secondary mb-0';
						panel.innerHTML = 'Select the 3rd party to refresh account or SIM details.';
						return;
					}
					const rows = assignmentsMap[String(thirdParty.value)] || [];
					const row = pickRow(rows, <?=json_encode((string)($row->transfer_category ?? ''))?>, <?=json_encode((string)($row->bank_name ?? ''))?>, <?=json_encode((string)($row->network_name ?? ''))?>);
					if (!row) {
						panel.className = 'alert alert-warning mb-0';
						panel.innerHTML = 'No saved account or SIM details were found for the selected 3rd party.';
						return;
					}
					const category = <?=json_encode((string)($row->transfer_category ?? ''))?>;
					const network = <?=json_encode((string)($row->network_name ?? ''))?>;
					const lines = [];
					if (category === 'Bank Float') {
						lines.push({label:'Bank', value: <?=json_encode((string)($row->bank_name ?? ''))?>});
						lines.push({label:'Account number', value: row.account_number || ''});
					} else if (network === 'MTN' || network === 'G-MONEY') {
						lines.push({label:'Phone number', value: row.phone_number || ''});
					} else if (network === 'TELECEL') {
						lines.push({label:'Till number', value: row.till_number || ''});
						lines.push({label:'Shortcode', value: row.shortcode || ''});
					} else if (network === 'AT') {
						lines.push({label:'Phone number', value: row.phone_number || ''});
						lines.push({label:'Till number', value: row.till_number || ''});
						lines.push({label:'Shortcode', value: row.shortcode || ''});
					}
					panel.className = 'alert alert-info mb-0';
					panel.innerHTML = '<div class="fw-semibold mb-1">Assigned detail preview</div>' + lines.map(function(line){
						const value = String(line.value || '').trim();
						return '<div><strong>' + escapeHtml(line.label) + ':</strong> ' + (value ? escapeHtml(value) : '<span class="text-warning">Not saved</span>') + '</div>';
					}).join('');
				}
				if (thirdParty) thirdParty.addEventListener('change', render);
				render();
			})();
			</script>
		<?php endif; ?>

		<?php if($isThirdPartyStep): ?>
			<div class="card border-0 shadow-sm mt-3">
				<div class="card-body">
					<h5 class="mb-3">Final Receipt &amp; 3rd-Party Review</h5>
					<p class="text-muted mb-3">Upload the final receipt, then approve or reject the transaction. This completes the <?=esc($transactionTypeLower === 'from vault' ? 'From Vault' : 'To Bank')?> flow.</p>
					<form method="post" enctype="multipart/form-data" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/bank_transfer_review/<?=esc((string)$row->id)?>" class="mt-3">
						<?=csrf()?>
						<input type="hidden" name="review_action" id="thirdPartyReviewAction" value="approve">
						<div class="mb-3">
							<label class="form-label">Final receipt photo</label>
							<div class="bt-upload-card">
								<input
									type="file"
									name="creator_bank_receipt_photo"
									class="d-none js-bank-upload-input"
									id="third_party_receipt_photo_input"
									accept="image/*"
									<?=$bankReceiptImageUrl === '' ? 'required' : ''?>
									data-preview-image="thirdPartyReceiptPreviewImage"
									data-preview-empty="thirdPartyReceiptPreviewEmpty"
									data-preview-meta="thirdPartyReceiptPreviewMeta"
								>
								<div class="bt-upload-actions">
									<label for="third_party_receipt_photo_input" class="bt-upload-trigger">
										<i class="fa-solid fa-receipt"></i>
										<span><?=$bankReceiptImageUrl === '' ? 'Choose Receipt' : 'Replace Receipt'?></span>
									</label>
									<div class="bt-upload-hint">
										<?=$bankReceiptImageUrl === '' ? 'Upload the receipt before final approval or rejection.' : 'A receipt is already on file. Choose a new image only if you need to replace it.'?>
									</div>
								</div>
								<div class="bt-upload-preview">
									<div class="bt-upload-thumb">
										<div class="bt-upload-empty" id="thirdPartyReceiptPreviewEmpty">
											<i class="fa-regular fa-image d-block mb-2 fs-4"></i>
											No new image selected yet
										</div>
										<img id="thirdPartyReceiptPreviewImage" alt="Final receipt preview" class="d-none">
									</div>
									<div class="bt-upload-meta" id="thirdPartyReceiptPreviewMeta">
										<div class="title">Final receipt preview</div>
										<div class="sub">Selected file details and thumbnail will appear here.</div>
									</div>
								</div>
							</div>
							<?php if($bankReceiptImageUrl !== ''): ?>
								<div class="small text-muted mt-2">Current saved receipt is shown in the proof section below.</div>
							<?php endif; ?>
							<?php if(!empty($errors['creator_bank_receipt_photo'])): ?><small class="text-danger"><?=$errors['creator_bank_receipt_photo']?></small><?php endif; ?>
						</div>
						<label class="form-label">Reject reason</label>
						<textarea class="form-control" name="reject_reason" rows="3" placeholder="State why this bank transfer is being rejected."><?=$thirdPartyRejectReasonValue !== '' ? esc($thirdPartyRejectReasonValue) : ''?></textarea>
						<?php if(!empty($errors['reject_reason'])): ?><small class="text-danger"><?=$errors['reject_reason']?></small><?php endif; ?>
						<div class="d-flex gap-2 flex-wrap mt-3">
							<button type="submit" class="btn btn-success" onclick="document.getElementById('thirdPartyReviewAction').value='approve'">Upload Receipt &amp; Approve</button>
							<button type="submit" class="btn btn-danger" onclick="document.getElementById('thirdPartyReviewAction').value='reject'">Upload Receipt &amp; Reject</button>
						</div>
					</form>
				</div>
			</div>
		<?php endif; ?>

		<?php if($isLegacySecondPartyFinalStep): ?>
			<div class="card border-0 shadow-sm mt-3">
				<div class="card-body">
					<h5 class="mb-3">2nd Party Final Approval</h5>
					<p class="text-muted mb-3">This older transaction is still waiting on the final cashdrawer update step. Approve to complete it, or reject with a reason.</p>
					<div class="d-flex gap-2 flex-wrap">
						<form method="post" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/bank_transfer_creator_approve/<?=esc((string)$row->id)?>">
							<?=csrf()?>
							<input type="hidden" name="review_action" value="approve">
							<button type="submit" class="btn btn-success">Approve &amp; Update Cashdrawer</button>
						</form>
					</div>
						<form method="post" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/bank_transfer_creator_approve/<?=esc((string)$row->id)?>" class="mt-3">
							<?=csrf()?>
							<input type="hidden" name="review_action" value="reject">
							<label class="form-label">Reject reason</label>
							<textarea class="form-control" name="reject_reason" rows="3" placeholder="State why this From Bank transaction is being rejected."><?=esc($fromBankRejectReasonValue)?></textarea>
							<?php if(!empty($errors['reject_reason'])): ?><small class="text-danger"><?=$errors['reject_reason']?></small><?php endif; ?>
							<button type="submit" class="btn btn-danger mt-2">Reject</button>
						</form>
					</div>
			</div>
		<?php endif; ?>
	</div>

	<div class="col-lg-4">
		<div class="card border-0 shadow-sm">
			<div class="card-body">
				<h5 class="mb-3">Proofs</h5>
				<div class="bt-proof-stack">
					<?php if($isFromBank): ?>
						<div class="bt-proof-tile">
							<div class="small text-muted mb-2">Cheque photo</div>
							<?php if($chequeImageUrl !== ''): ?>
								<a href="<?=esc($chequeImageUrl)?>" target="_blank" class="bt-proof-thumb">
									<img src="<?=esc($chequeImageUrl)?>" alt="Cheque photo thumbnail">
								</a>
								<div class="bt-proof-foot">
									<div class="small text-muted">Uploaded proof thumbnail</div>
									<a href="<?=esc($chequeImageUrl)?>" target="_blank" class="btn btn-outline-secondary btn-sm">Open</a>
								</div>
							<?php else: ?>
								<div class="text-muted small">Not uploaded yet.</div>
							<?php endif; ?>
						</div>
					<?php endif; ?>
					<div class="bt-proof-tile">
						<div class="small text-muted mb-2">Treasury slip</div>
						<?php if($treasuryImageUrl !== ''): ?>
							<a href="<?=esc($treasuryImageUrl)?>" target="_blank" class="bt-proof-thumb">
								<img src="<?=esc($treasuryImageUrl)?>" alt="Treasury slip thumbnail">
							</a>
							<div class="bt-proof-foot">
								<div class="small text-muted">Uploaded proof thumbnail</div>
								<a href="<?=esc($treasuryImageUrl)?>" target="_blank" class="btn btn-outline-secondary btn-sm">Open</a>
							</div>
						<?php else: ?>
							<div class="text-muted small">Not uploaded yet.</div>
						<?php endif; ?>
					</div>
					<div class="bt-proof-tile">
						<div class="small text-muted mb-2">Bank receipt</div>
						<?php if($bankReceiptImageUrl !== ''): ?>
							<a href="<?=esc($bankReceiptImageUrl)?>" target="_blank" class="bt-proof-thumb">
								<img src="<?=esc($bankReceiptImageUrl)?>" alt="Bank receipt thumbnail">
							</a>
							<div class="bt-proof-foot">
								<div class="small text-muted">Uploaded proof thumbnail</div>
								<a href="<?=esc($bankReceiptImageUrl)?>" target="_blank" class="btn btn-outline-secondary btn-sm">Open</a>
							</div>
						<?php else: ?>
							<div class="text-muted small">Not uploaded yet.</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<?php if($isSecondPartyStep && $isFromBank): ?>
	<div class="modal fade" id="fromBankDenoModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">GHS Denominations</h5>
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
									<td><input type="number" min="0" class="form-control form-control-sm fb-deno-count"></td>
									<td><input type="text" class="form-control form-control-sm fb-deno-volume" readonly></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
						<tfoot>
							<tr>
								<th>Total</th>
								<th><input type="text" class="form-control form-control-sm" id="fromBankDenoCount" readonly></th>
								<th><input type="text" class="form-control form-control-sm" id="fromBankDenoTotal" readonly></th>
							</tr>
						</tfoot>
					</table>
					<div class="small text-muted">Expected total: <?=number_format((float)($row->amount ?? 0), 2)?></div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-outline-secondary" id="fromBankDenoReset">Reset</button>
					<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
					<button type="button" class="btn btn-primary" id="fromBankDenoApply">Apply</button>
				</div>
			</div>
		</div>
	</div>
<?php endif; ?>

<script>
(function(){
	const formatBytes = function(bytes){
		const value = Number(bytes || 0);
		if (!(value > 0)) return '0 KB';
		if (value >= 1024 * 1024) return (value / (1024 * 1024)).toFixed(2) + ' MB';
		return Math.max(1, Math.round(value / 1024)) + ' KB';
	};
	const previewLabels = {
		treasury_slip_photo: 'Treasury slip preview',
		creator_bank_receipt_photo: 'Bank receipt preview',
		cheque_photo: 'Cheque preview',
	};
	const uploadCache = new WeakMap();
	const uploadCachePending = new WeakMap();

	const cacheUploadFile = function(input, file, metaEl){
		if (!input || !file || typeof file.arrayBuffer !== 'function') {
			return Promise.resolve(null);
		}

		const pending = file.arrayBuffer().then(function(buffer){
			const cachedFile = new File([buffer], file.name, {
				type: file.type || 'application/octet-stream',
				lastModified: Date.now(),
			});
			uploadCache.set(input, cachedFile);

			if (typeof DataTransfer !== 'undefined') {
				const dataTransfer = new DataTransfer();
				dataTransfer.items.add(cachedFile);
				input.files = dataTransfer.files;
			}

			return cachedFile;
		}).catch(function(){
			uploadCache.delete(input);
			if (metaEl) {
				metaEl.innerHTML =
					'<div class="title text-danger">Could not prepare file</div>' +
					'<div class="sub">Please choose the image again from a stable local folder before submitting.</div>';
			}
			return null;
		}).finally(function(){
			uploadCachePending.delete(input);
		});
		uploadCachePending.set(input, pending);
		return pending;
	};

	const restoreCachedUploadFile = function(input){
		if (!input || typeof DataTransfer === 'undefined') {
			return;
		}
		const cachedFile = uploadCache.get(input);
		if (!cachedFile) {
			return;
		}
		const dataTransfer = new DataTransfer();
		dataTransfer.items.add(cachedFile);
		input.files = dataTransfer.files;
	};

	const bindPreview = function(input){
		if (!input) return;
		const imageEl = document.getElementById(String(input.dataset.previewImage || ''));
		const emptyEl = document.getElementById(String(input.dataset.previewEmpty || ''));
		const metaEl = document.getElementById(String(input.dataset.previewMeta || ''));
		if (!imageEl || !emptyEl || !metaEl) return;

		const resetPreview = function(){
			imageEl.classList.add('d-none');
			imageEl.removeAttribute('src');
			emptyEl.classList.remove('d-none');
			metaEl.innerHTML = '<div class="title">' + (previewLabels[input.name] || 'Image preview') + '</div><div class="sub">Selected file details and thumbnail will appear here.</div>';
		};

		input.addEventListener('change', function(){
			const file = input.files && input.files[0] ? input.files[0] : null;
			uploadCache.delete(input);
			if (!file) {
				resetPreview();
				return;
			}

			metaEl.innerHTML =
				'<div class="title">' + file.name + '</div>' +
				'<div class="sub">Type: ' + (file.type || 'image') + '</div>' +
				'<div class="sub">Size: ' + formatBytes(file.size || 0) + '</div>';

			if (!String(file.type || '').startsWith('image/')) {
				imageEl.classList.add('d-none');
				imageEl.removeAttribute('src');
				emptyEl.classList.remove('d-none');
				emptyEl.innerHTML = '<i class="fa-regular fa-file-lines d-block mb-2 fs-4"></i>Preview unavailable';
				return;
			}

			const reader = new FileReader();
			reader.onload = function(event){
				imageEl.src = String(event.target && event.target.result ? event.target.result : '');
				imageEl.classList.remove('d-none');
				emptyEl.classList.add('d-none');
			};
			reader.readAsDataURL(file);
			cacheUploadFile(input, file, metaEl);
		});
	};

	document.querySelectorAll('.js-bank-upload-input').forEach(bindPreview);
	document.querySelectorAll('form[enctype="multipart/form-data"]').forEach(function(form){
		form.addEventListener('submit', function(event){
			if (form.dataset.uploadCacheReady === '1') {
				form.dataset.uploadCacheReady = '';
				form.querySelectorAll('.js-bank-upload-input').forEach(restoreCachedUploadFile);
				return;
			}

			const uploadInputs = Array.from(form.querySelectorAll('.js-bank-upload-input'));
			const pending = uploadInputs
				.map(function(input){ return uploadCachePending.get(input); })
				.filter(Boolean);

			if (!pending.length) {
				uploadInputs.forEach(restoreCachedUploadFile);
				return;
			}

			event.preventDefault();
			Promise.allSettled(pending).then(function(){
				uploadInputs.forEach(restoreCachedUploadFile);
				form.dataset.uploadCacheReady = '1';
				if (typeof form.requestSubmit === 'function') {
					form.requestSubmit(event.submitter || undefined);
				} else {
					form.submit();
				}
			});
		});
	});

	(function(){
		const modalEl = document.getElementById('fromBankDenoModal');
		if (!modalEl) return;
		const denosInput = document.getElementById('fromBankDenoJson');
		const amountField = document.getElementById('fromBankDenoAmount');
		const actionInput = document.getElementById('fromBankVerificationAction');
		const rejectWrap = document.getElementById('fromBankRejectReasonWrap');
		const rejectReason = document.getElementById('fromBankRejectReason');
		const mismatchAlert = document.getElementById('fromBankMismatchAlert');
		const verifyButton = document.getElementById('fromBankVerifyButton');
		const treasuryInput = document.getElementById('treasury_slip_photo_input');
		const countField = document.getElementById('fromBankDenoCount');
		const totalField = document.getElementById('fromBankDenoTotal');
		const resetBtn = document.getElementById('fromBankDenoReset');
		const applyBtn = document.getElementById('fromBankDenoApply');
		const rows = modalEl.querySelectorAll('tbody tr[data-denomination]');
		const expectedTotal = <?=json_encode(round((float)($row->amount ?? 0), 2))?>;

		function recalc(){
			let totalCount = 0;
			let totalAmount = 0;
			rows.forEach(function(row){
				const denomination = Number(row.getAttribute('data-denomination') || 0);
				const countInput = row.querySelector('.fb-deno-count');
				const volumeInput = row.querySelector('.fb-deno-volume');
				const count = Number(countInput ? countInput.value || 0 : 0);
				const volume = denomination * count;
				totalCount += count;
				totalAmount += volume;
				if (volumeInput) {
					volumeInput.value = volume > 0 ? volume.toFixed(2) : '';
				}
			});
			if (countField) countField.value = totalCount ? String(totalCount) : '';
			if (totalField) totalField.value = totalAmount ? totalAmount.toFixed(2) : '';
			if (amountField) amountField.value = totalAmount ? totalAmount.toFixed(2) : '';
			syncVerificationState(totalAmount, totalCount);
			return totalAmount;
		}

		function syncVerificationState(totalAmount, totalCount){
			const hasEntries = Number(totalCount || 0) > 0;
			const mismatch = hasEntries && Math.abs(Number(totalAmount || 0) - expectedTotal) > 0.009;
			if (actionInput) actionInput.value = mismatch ? 'reject' : 'verify';
			if (rejectWrap) rejectWrap.classList.toggle('d-none', !mismatch);
			if (rejectReason) rejectReason.required = mismatch;
			if (treasuryInput) treasuryInput.required = true;
			if (mismatchAlert) {
				if (mismatch) {
					mismatchAlert.classList.remove('d-none');
					mismatchAlert.innerHTML = 'Denomination mismatch detected. Expected <strong>' + expectedTotal.toFixed(2) + '</strong> but entered <strong>' + Number(totalAmount || 0).toFixed(2) + '</strong>. Enter a clear reason and submit the rejection.';
				} else {
					mismatchAlert.classList.add('d-none');
					mismatchAlert.innerHTML = '';
				}
			}
			if (verifyButton) {
				verifyButton.textContent = mismatch ? 'Reject & Submit' : 'Approve & Update Cashdrawer';
				verifyButton.classList.toggle('btn-success', !mismatch);
				verifyButton.classList.toggle('btn-danger', mismatch);
			}
		}

		function loadFromHidden(){
			let payload = [];
			try {
				payload = JSON.parse(String(denosInput ? denosInput.value || '[]' : '[]'));
			} catch (error) {
				payload = [];
			}
			rows.forEach(function(row){
				const denomination = Number(row.getAttribute('data-denomination') || 0);
				const countInput = row.querySelector('.fb-deno-count');
				const match = payload.find(function(item){
					return Number(item.denomination || 0) === denomination;
				});
				if (countInput) {
					countInput.value = match ? Number(match.count || 0) : '';
				}
			});
			recalc();
		}

		rows.forEach(function(row){
			const countInput = row.querySelector('.fb-deno-count');
			if (countInput) {
				countInput.addEventListener('input', recalc);
			}
		});

		if (resetBtn) {
			resetBtn.addEventListener('click', function(){
				rows.forEach(function(row){
					const countInput = row.querySelector('.fb-deno-count');
					const volumeInput = row.querySelector('.fb-deno-volume');
					if (countInput) countInput.value = '';
					if (volumeInput) volumeInput.value = '';
				});
				recalc();
			});
		}

		if (applyBtn) {
			applyBtn.addEventListener('click', function(){
				const payload = [];
				rows.forEach(function(row){
					const denomination = Number(row.getAttribute('data-denomination') || 0);
					const countInput = row.querySelector('.fb-deno-count');
					const count = Number(countInput ? countInput.value || 0 : 0);
					if (count > 0) {
						payload.push({
							currency: 'GHS',
							denomination: denomination,
							count: count,
							volume: Number((denomination * count).toFixed(2)),
						});
					}
				});
				const total = recalc();
				if (denosInput) denosInput.value = JSON.stringify(payload);
				if (amountField) amountField.value = total ? total.toFixed(2) : '';
				const modalInstance = bootstrap.Modal.getInstance(modalEl);
				if (modalInstance) modalInstance.hide();
			});
		}

		modalEl.addEventListener('show.bs.modal', function(){
			loadFromHidden();
		});

		loadFromHidden();
	})();
})();
</script>
