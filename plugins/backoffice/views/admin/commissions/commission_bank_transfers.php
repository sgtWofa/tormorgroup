<?php
$commissionBankIsAdmin = backoffice_user_is_admin(get_session());
$commissionBankCurrentUserId = (int)(get_session()->id ?? 0);
$commissionBankFormat = static fn($amount): string => 'GH₵ ' . number_format((float)$amount, 2);
$commissionBankWhatsappUrl = static function ($transfer) use ($commissionBankCurrentUserId): string {
	if ((int)($transfer->created_by ?? 0) !== $commissionBankCurrentUserId || (int)($transfer->assigned_admin_id ?? 0) <= 0 || !function_exists('backoffice_whatsapp_phone')) {
		return '';
	}
	$phone = backoffice_whatsapp_phone((int)$transfer->assigned_admin_id);
	if (($phone['phone'] ?? '') === '') {
		return '';
	}
	$message = 'Hello ' . trim((string)($transfer->assigned_admin_name ?? 'Admin'))
		. ', please review commission bank transfer request #' . (int)$transfer->id
		. ' for ' . (string)$transfer->bank_name . ' (GH₵ ' . number_format((float)$transfer->amount, 2) . ').';
	return 'https://wa.me/' . rawurlencode((string)$phone['phone']) . '?text=' . rawurlencode($message);
};
$commissionBankProofUrl = static function ($path): string {
	$path = ltrim(str_replace('\\', '/', (string)$path), '/');
	return ROOT . '/' . implode('/', array_map('rawurlencode', explode('/', $path)));
};
$commissionBankPendingCount = (int)($commissionBankLedgerSummary->pending_count ?? 0);
$commissionBankApprovedTotal = (float)($commissionBankLedgerSummary->approved_total ?? 0);
?>
<style>
	.cb-page{max-width:1440px;margin:0 auto}
	.cb-hero{position:relative;overflow:hidden;border:0;border-radius:1.35rem;background:linear-gradient(125deg,#102b50,#174a70 58%,#117c77);color:#fff;box-shadow:0 18px 48px rgba(16,43,80,.2)}
	.cb-hero:after{content:"";position:absolute;width:270px;height:270px;right:-90px;top:-135px;border:1px solid rgba(255,255,255,.18);border-radius:50%;box-shadow:0 0 0 32px rgba(255,255,255,.04),0 0 0 68px rgba(255,255,255,.03)}
	.cb-hero-content{position:relative;z-index:1}
	.cb-kpi,.cb-panel{border:1px solid var(--bs-border-color);border-radius:1rem;background:var(--bs-body-bg);box-shadow:0 12px 30px rgba(15,23,42,.055)}
	.cb-kpi{padding:1rem;height:100%}
	.cb-kpi-label{font-size:.68rem;text-transform:uppercase;letter-spacing:.09em;color:var(--bs-secondary-color);font-weight:700}
	.cb-kpi-value{font-size:1.2rem;font-weight:800;margin-top:.25rem}
	.cb-panel-head{padding:1rem 1.15rem;border-bottom:1px solid var(--bs-border-color)}
	.cb-panel-body{padding:1rem 1.15rem}
	.cb-branch{border:1px solid var(--bs-border-color);border-radius:.9rem;overflow:hidden;background:var(--bs-body-bg)}
	.cb-branch+.cb-branch{margin-top:.75rem}
	.cb-branch-head{display:flex;justify-content:space-between;align-items:center;gap:.75rem;padding:.8rem 1rem;background:rgba(var(--bs-primary-rgb),.055)}
	.cb-user{display:grid;grid-template-columns:minmax(0,1fr) 150px;gap:.85rem;align-items:center;padding:.85rem 1rem;border-top:1px solid var(--bs-border-color)}
	.cb-user-main{min-width:0}
	.cb-user-name{font-weight:700}
	.cb-user-meta{font-size:.78rem;color:var(--bs-secondary-color)}
	.cb-user-amount{max-width:220px;margin:.65rem 0;font-variant-numeric:tabular-nums;font-weight:700;text-align:left}
	.cb-commission-details{margin-top:.4rem;font-size:.75rem}
	.cb-commission-details summary{cursor:pointer;color:var(--bs-primary);font-weight:600}
	.cb-commission-details table{font-size:.75rem}
	.cb-selection-scroll{padding:1rem 1.15rem}
	.cb-modal-summary{position:sticky;top:0}
	.cb-total{border-radius:1rem;padding:1rem;background:linear-gradient(135deg,rgba(var(--bs-primary-rgb),.1),rgba(var(--bs-success-rgb),.09));border:1px solid rgba(var(--bs-primary-rgb),.18)}
	.cb-proof-preview{max-height:180px;max-width:100%;border-radius:.75rem;object-fit:contain;display:none}
	.cb-transfer-ledger{width:100%;overflow:visible}
	.cb-ledger-table{width:100%;table-layout:fixed;margin-bottom:0;font-size:.88rem}
	.cb-ledger-table th{white-space:normal;font-size:.7rem;text-transform:uppercase;letter-spacing:.04em;color:var(--bs-secondary-color)}
	.cb-ledger-table td,.cb-ledger-table th{padding:.65rem .5rem;overflow-wrap:anywhere}
	.cb-ledger-table td:nth-child(2),.cb-ledger-table th:nth-child(2){white-space:nowrap}
	.cb-ledger-table td{vertical-align:middle}
	.cb-ledger-amount{font-weight:750;font-variant-numeric:tabular-nums;white-space:nowrap}
	.cb-ledger-meta{font-size:.8rem;color:var(--bs-secondary-color)}
	.cb-ledger-submitter{min-width:0}
	.cb-ledger-submitter-name{overflow-wrap:anywhere}
	.cb-ledger-row-selected>td{background:var(--bs-primary-bg-subtle)!important;color:var(--bs-emphasis-color)!important}
	.cb-ledger-row-selected .cb-ledger-meta{color:var(--bs-secondary-color)!important}
	.cb-ledger-dropdown{display:flex;justify-content:flex-end}
	.cb-ledger-dropdown .dropdown-menu{min-width:12rem}
	.cb-ledger-dropdown .dropdown-toggle{padding:.3rem .55rem}
	.cb-proof-thumbnail{display:block;width:100%;max-height:360px;object-fit:contain;cursor:zoom-in;border:1px solid var(--bs-border-color);border-radius:.75rem;background:var(--bs-tertiary-bg)}
	.cb-proof-image{display:block;max-width:100%;max-height:calc(100dvh - 10rem);margin:auto;object-fit:contain}
	.cb-ledger-filter{display:grid;grid-template-columns:minmax(12rem,1.4fr) repeat(2,minmax(9rem,.8fr)) auto auto;gap:.5rem;align-items:end;padding:.85rem 1rem;border-bottom:1px solid var(--bs-border-color)}
	.cb-ledger-filter-actions{display:flex;gap:.4rem;flex-wrap:wrap}
	.cb-ajax-feedback:empty{display:none}
	.cb-loading-overlay{position:fixed;inset:0;z-index:2000;display:flex;align-items:center;justify-content:center;padding:1rem;background:rgba(15,23,42,.35);backdrop-filter:blur(2px)}
	.cb-loading-overlay[hidden]{display:none}
	.cb-loading-card{display:flex;align-items:center;gap:.85rem;max-width:min(26rem,100%);padding:1rem 1.25rem;border:1px solid var(--bs-border-color);border-radius:1rem;background:var(--bs-body-bg);color:var(--bs-body-color);box-shadow:0 1rem 3rem rgba(0,0,0,.2)}
	.cb-transfer-dialog{height:calc(100dvh - 2rem);max-height:calc(100dvh - 2rem)}
	.cb-transfer-modal-content{height:100%;min-height:0;max-height:100%}
	.cb-transfer-form{display:flex;flex:1 1 auto;flex-direction:column;min-height:0;max-height:100%;overflow:hidden}
	.cb-transfer-modal-body{flex:1 1 auto;min-height:0;overflow-y:auto;overscroll-behavior:contain}
	.cb-user{display:block}
	.cb-status{font-size:.72rem;font-weight:700;letter-spacing:.03em}
	.cb-empty{border:1px dashed var(--bs-border-color);border-radius:.9rem;padding:2rem 1rem;text-align:center;color:var(--bs-secondary-color)}
	@media(min-width:992px){
		.cb-modal-dialog{max-width:min(1120px,calc(100vw - 2rem))}
		.cb-selection-scroll{max-height:calc(100vh - 17rem);overflow-y:auto;overscroll-behavior:contain;scrollbar-gutter:stable}
		.cb-selection-scroll::-webkit-scrollbar{width:.55rem}
		.cb-selection-scroll::-webkit-scrollbar-thumb{background:rgba(var(--bs-secondary-rgb),.38);border-radius:1rem}
	}
	@media(max-width:767.98px){
		.cb-transfer-dialog{height:calc(100dvh - 1rem);max-height:calc(100dvh - 1rem);margin:.5rem}
		.cb-ledger-table{font-size:.76rem}
		.cb-ledger-table td,.cb-ledger-table th{padding:.55rem .3rem}
		.cb-ledger-table th{font-size:.62rem;letter-spacing:0}
		.cb-ledger-amount{font-size:.72rem}
		.cb-ledger-dropdown .dropdown-toggle{padding:.25rem .4rem}
		.cb-ledger-filter{grid-template-columns:repeat(2,minmax(0,1fr));padding:.75rem;gap:.5rem}
		.cb-ledger-filter .cb-filter-search{grid-column:1/-1}
		.cb-ledger-filter-actions{grid-column:1/-1}
		.cb-user-amount .form-control{padding:.35rem .45rem;font-size:.86rem}
		.cb-panel-head,.cb-panel-body{padding:.8rem}
		.cb-selection-scroll{padding:.8rem}
		.cb-modal-summary{position:static}
	}
	@media(max-width:991.98px){
		.cb-transfer-dialog{height:calc(100dvh - 1rem);max-height:calc(100dvh - 1rem);margin:.5rem}
		.cb-selection-scroll{max-height:none;overflow:visible}
		.cb-modal-summary{position:static}
	}
</style>

<div class="cb-page py-3">
	<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
		<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/list#commissions" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
			<i class="fa-solid fa-arrow-left me-1"></i> Back to commissions
		</a>
		<span class="small text-muted"><i class="fa-solid fa-shield-halved me-1"></i> Admin approval required</span>
	</div>

	<section class="cb-hero p-4 p-lg-5 mb-3">
		<div class="cb-hero-content row align-items-center g-3">
			<div class="col-lg-8">
				<div class="small text-white-50 text-uppercase fw-bold mb-2" style="letter-spacing:.12em">Commission settlement</div>
				<h1 class="h3 fw-bold mb-2">Move earned commissions to bank</h1>
				<p class="mb-0 text-white-50">Choose a bank, select approved commissions by branch and user, attach transfer proof, and submit for admin approval.</p>
			</div>
			<div class="col-lg-4 text-lg-end">
				<span class="badge rounded-pill bg-white text-primary px-3 py-2"><i class="fa-solid fa-lock me-1"></i> Approval-controlled</span>
			</div>
		</div>
	</section>

	<div class="alert alert-dismissible fade show cb-ajax-feedback mb-3" id="commissionBankFeedback" role="status" aria-live="polite"></div>
	<div class="cb-loading-overlay" id="commissionBankLoading" role="status" aria-live="polite" aria-atomic="true" hidden>
		<div class="cb-loading-card">
			<span class="spinner-border text-primary flex-shrink-0" aria-hidden="true"></span>
			<span id="commissionBankLoadingMessage">Please wait…</span>
		</div>
	</div>
	<div class="row g-2 mb-4" id="commissionBankKpis">
		<div class="col-6 col-lg-3"><div class="cb-kpi"><div class="cb-kpi-label">Available to transfer</div><div class="cb-kpi-value"><?=esc($commissionBankFormat($commissionBankTotalAvailable))?></div><div class="small text-muted">Approved, not reserved or cleared</div></div></div>
		<div class="col-6 col-lg-3"><div class="cb-kpi"><div class="cb-kpi-label">Branches represented</div><div class="cb-kpi-value"><?=count($commissionBankBranches)?></div><div class="small text-muted">Across active approved commission balances</div></div></div>
		<div class="col-6 col-lg-3"><div class="cb-kpi"><div class="cb-kpi-label">Awaiting admin</div><div class="cb-kpi-value"><?=$commissionBankPendingCount?></div><div class="small text-muted">Pending transfer requests</div></div></div>
		<div class="col-6 col-lg-3"><div class="cb-kpi"><div class="cb-kpi-label">Approved transfers</div><div class="cb-kpi-value"><?=esc($commissionBankFormat($commissionBankApprovedTotal))?></div><div class="small text-muted">Recent ledger activity</div></div></div>
	</div>

	<div class="d-flex justify-content-end mb-3">
		<button type="button" class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#commissionBankTransferModal" <?=!$commissionBankBranches ? 'disabled' : ''?>>
			<i class="fa-solid fa-building-columns me-2"></i> Create bank transfer
		</button>
	</div>

	<div class="modal fade" id="commissionBankTransferModal" tabindex="-1" aria-labelledby="commissionBankTransferModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable cb-modal-dialog cb-transfer-dialog">
			<div class="modal-content border-0 shadow-lg cb-transfer-modal-content">
				<form method="post" enctype="multipart/form-data" id="commissionBankTransferForm" class="cb-transfer-form" data-commission-bank-ajax>
					<?=csrf()?>
					<input type="hidden" name="action" value="submit_transfer">
					<div class="modal-header">
						<div>
							<div class="small text-uppercase text-primary fw-bold" style="letter-spacing:.08em">Commission settlement</div>
							<h2 class="modal-title fs-5 fw-bold" id="commissionBankTransferModalLabel">Select commission balances</h2>
							<div class="small text-muted">Pending transfers are reserved; approved transfers are cleared from commissions.</div>
						</div>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
					</div>
					<div class="modal-body cb-transfer-modal-body">
						<div class="row g-3 align-items-start">
							<div class="col-12 col-lg-8">
								<div class="d-flex justify-content-between align-items-center gap-2 px-1 pb-2">
									<div class="small text-muted">Select full or partial amounts by user.</div>
									<div class="d-flex gap-2 flex-shrink-0">
										<button type="button" class="btn btn-outline-primary btn-sm rounded-pill" id="commissionBankSelectAll">Transfer all</button>
										<button type="button" class="btn btn-outline-secondary btn-sm rounded-pill" id="commissionBankClear">Clear</button>
									</div>
								</div>
								<div class="cb-selection-scroll">
									<?php if (!$commissionBankBranches): ?>
										<div class="cb-empty">
											<i class="fa-solid fa-circle-check fs-3 d-block mb-2"></i>
											<div class="fw-semibold">Nothing is waiting to be transferred</div>
											<div class="small mt-1">Approved commissions will appear here once they have an available balance.</div>
										</div>
									<?php else: ?>
										<?php foreach ($commissionBankBranches as $branch): ?>
											<section class="cb-branch">
												<div class="cb-branch-head">
													<div>
														<div class="fw-bold"><i class="fa-solid fa-location-dot text-primary me-1"></i><?=esc($branch['name'])?></div>
														<div class="small text-muted"><?=count($branch['users'])?> user<?=count($branch['users']) === 1 ? '' : 's'?></div>
													</div>
													<span class="badge rounded-pill text-bg-primary"><?=esc($commissionBankFormat($branch['total_available']))?></span>
												</div>
												<?php foreach ($branch['users'] as $user): ?>
													<div class="cb-user">
														<div class="cb-user-main">
															<div class="cb-user-name"><?=esc($user['name'])?></div>
															<div class="cb-user-meta"><?=esc((string)$user['commission_count'])?> approved record<?=((int)$user['commission_count'] === 1) ? '' : 's'?> · Available <?=esc($commissionBankFormat($user['available']))?></div>
															<div class="cb-user-amount">
																<label class="form-label small mb-1" for="commissionBankUser<?=esc((string)$user['id'])?>">Transfer amount</label>
																<div class="input-group input-group-sm">
																	<span class="input-group-text">GH₵</span>
																	<input type="number" class="form-control commission-bank-amount" id="commissionBankUser<?=esc((string)$user['id'])?>" name="allocations[<?=esc((string)$user['id'])?>]" min="0" max="<?=esc(number_format((float)$user['available'], 2, '.', ''))?>" step="0.01" value="0.00" data-available="<?=esc(number_format((float)$user['available'], 2, '.', ''))?>" aria-label="Transfer amount for <?=esc($user['name'])?>">
																	<button class="btn btn-outline-primary" type="button" data-commission-bank-fill>All</button>
																</div>
															</div>
															<details class="cb-commission-details">
																<summary>View totals by category</summary>
																<div class="table-responsive mt-2">
																	<table class="table table-sm table-bordered align-middle mb-0">
																		<thead><tr><th>Category</th><th>Records / networks</th><th class="text-end">Available</th></tr></thead>
																		<tbody>
																			<?php foreach ($user['categories'] as $category): ?>
																				<tr>
																					<td><?=esc($category['name'])?></td>
																					<td><?=esc((string)$category['record_count'])?> records<div class="text-muted"><?=esc($category['networks'] ? implode(', ', $category['networks']) : 'All networks')?></div></td>
																					<td class="text-end text-nowrap"><?=esc($commissionBankFormat($category['available']))?></td>
																				</tr>
																			<?php endforeach; ?>
																		</tbody>
																	</table>
																</div>
															</details>
														</div>
													</div>
												<?php endforeach; ?>
											</section>
										<?php endforeach; ?>
									<?php endif; ?>
								</div>
								<div class="small text-muted mt-2"><i class="fa-solid fa-circle-info me-1"></i> Pending requests temporarily reserve selected amounts.</div>
							</div>
							<div class="col-12 col-lg-4">
								<div class="cb-modal-summary">
									<div class="cb-total mb-3">
										<div class="cb-kpi-label">Transfer total</div>
										<div class="cb-kpi-value text-primary" id="commissionBankTotal">GH₵ 0.00</div>
										<div class="small text-muted" id="commissionBankSelectedCount">No commission amounts selected</div>
									</div>
									<div class="mb-3">
										<label class="form-label fw-semibold" for="commissionBankName">Receiving bank</label>
										<select class="form-select" id="commissionBankName" name="bank_name" required>
											<option value="">Choose a bank</option>
											<?php foreach (backoffice_commission_bank_options() as $bankOption): ?>
												<option value="<?=esc($bankOption)?>"><?=esc($bankOption)?></option>
											<?php endforeach; ?>
										</select>
									</div>
									<div class="mb-3">
										<label class="form-label fw-semibold" for="commissionBankProof">Transfer proof</label>
										<div class="border rounded-3 p-3 bg-body-secondary">
											<input class="form-control" type="file" id="commissionBankProof" name="proof_image" accept="image/jpeg,image/png,image/webp" required>
											<div class="small text-muted mt-2">JPEG, PNG or WebP · maximum 1MB</div>
											<img src="" id="commissionBankProofPreview" class="cb-proof-preview mt-2" alt="Transfer proof preview">
										</div>
									</div>
									<div class="mb-3">
										<label class="form-label fw-semibold" for="commissionBankAssignedAdmin">Admin assigned to approve</label>
										<select class="form-select" id="commissionBankAssignedAdmin" name="assigned_admin_id" required>
											<option value="">Choose an admin with an open cashdrawer</option>
											<?php foreach ($commissionBankApprovers as $approver): ?>
												<option value="<?=esc((string)$approver->id)?>"><?=esc(trim((string)($approver->first_name ?? '') . ' ' . (string)($approver->last_name ?? '')) ?: 'Admin #' . (int)$approver->id)?></option>
											<?php endforeach; ?>
										</select>
										<div class="form-text">The selected admin owns the shared bank-account cashdrawer entry and will receive your WhatsApp review prompt.</div>
										<?php if (!$commissionBankApprovers): ?><div class="small text-danger mt-1">No admin with an open cashdrawer is currently available to assign.</div><?php endif; ?>
									</div>
									<button type="submit" class="btn btn-primary btn-lg w-100 rounded-pill" id="commissionBankSubmit" <?=(!$commissionBankBranches || !$commissionBankApprovers) ? 'disabled' : ''?>>
										<i class="fa-solid fa-paper-plane me-2"></i> Submit for admin approval
									</button>
									<div class="small text-center text-muted mt-2">Bank balance and commission clearance update only after approval.</div>
								</div>
							</div>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<section class="cb-panel mb-4" id="commissionBankLedger">
		<div class="cb-panel-head d-flex justify-content-between align-items-center gap-2">
			<div>
				<h2 class="h6 fw-bold mb-1">Transfer ledger</h2>
				<div class="small text-muted">Proof, allocations and review status for recent requests.</div>
			</div>
			<span class="badge rounded-pill text-bg-secondary"><?=count($commissionBankTransfers)?> recent</span>
		</div>
		<form class="cb-ledger-filter" id="commissionBankLedgerFilters" method="get" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/commission_bank_transfers">
			<div class="cb-filter-search">
				<label class="form-label small fw-semibold mb-1" for="commissionBankLedgerSearch">Search transfers</label>
				<input class="form-control form-control-sm" id="commissionBankLedgerSearch" type="search" name="search" value="<?=esc($commissionBankSearch)?>" placeholder="Bank, submitter, assigned admin, status or request #">
			</div>
			<div>
				<label class="form-label small fw-semibold mb-1" for="commissionBankLedgerStart">Start date</label>
				<input class="form-control form-control-sm" id="commissionBankLedgerStart" type="date" name="start_date" value="<?=esc($commissionBankStartDate)?>">
			</div>
			<div>
				<label class="form-label small fw-semibold mb-1" for="commissionBankLedgerEnd">End date</label>
				<input class="form-control form-control-sm" id="commissionBankLedgerEnd" type="date" name="end_date" value="<?=esc($commissionBankEndDate)?>">
			</div>
			<div class="cb-ledger-filter-actions">
				<button class="btn btn-primary btn-sm" type="submit"><i class="fa-solid fa-magnifying-glass me-1"></i>Search</button>
				<button class="btn btn-outline-secondary btn-sm" type="button" data-commission-bank-clear-filters>Clear</button>
			</div>
			<a class="btn btn-outline-primary btn-sm" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/commission_bank_transfers/pdf<?=($commissionBankSearch !== '' || $commissionBankStartDate !== '' || $commissionBankEndDate !== '') ? '?' . esc(http_build_query(array_filter(['search' => $commissionBankSearch, 'start_date' => $commissionBankStartDate, 'end_date' => $commissionBankEndDate]))) : ''?>" target="_blank" rel="noopener">
				<i class="fa-solid fa-file-pdf me-1"></i>PDF
			</a>
		</form>
		<div class="cb-transfer-ledger">
			<?php if (!$commissionBankTransfers): ?>
				<div class="cb-empty"><i class="fa-solid fa-receipt fs-3 d-block mb-2"></i>No commission bank transfers have been submitted yet.</div>
			<?php else: ?>
				<table class="table table-hover align-middle cb-ledger-table">
					<colgroup>
						<col style="width:32%">
						<col style="width:22%">
						<col style="width:30%">
						<col style="width:16%">
					</colgroup>
					<thead>
						<tr>
							<th scope="col">Bank / request</th>
							<th scope="col" class="text-end">Amount</th>
							<th scope="col">Submitted / status</th>
							<th scope="col" class="text-end">Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($commissionBankTransfers as $transfer): ?>
							<?php
								$transferStatus = strtolower(trim((string)($transfer->status ?? 'pending')));
								$statusBadge = $transferStatus === 'approved' ? 'text-bg-success' : ($transferStatus === 'rejected' ? 'text-bg-danger' : 'text-bg-warning');
								$transferId = (int)$transfer->id;
								$transferSelected = $commissionBankSelectedTransferId === $transferId;
								$canReviewTransfer = $commissionBankIsAdmin
									&& $transferStatus === 'pending'
									&& ((int)($transfer->assigned_admin_id ?? 0) <= 0 || (int)$transfer->assigned_admin_id === $commissionBankCurrentUserId);
								$whatsappUrl = $commissionBankWhatsappUrl($transfer);
							?>
							<tr class="<?=$transferSelected ? 'cb-ledger-row-selected' : ''?>">
								<td>
									<div class="fw-semibold"><?=esc((string)$transfer->bank_name)?></div>
									<div class="cb-ledger-meta">Request #<?=esc((string)$transferId)?> · <?=esc((string)$transfer->date_created)?></div>
								</td>
								<td class="text-end cb-ledger-amount"><?=esc($commissionBankFormat($transfer->amount))?></td>
								<td>
									<div class="cb-ledger-submitter">
										<div class="cb-ledger-submitter-name"><?=esc(trim((string)($transfer->creator_name ?? 'Unknown')))?></div>
										<div class="cb-ledger-meta">Admin: <?=esc(trim((string)($transfer->assigned_admin_name ?? '')) ?: 'Unassigned')?></div>
										<span class="badge rounded-pill cb-status <?=$statusBadge?>"><?=esc(ucfirst($transferStatus))?></span>
									</div>
								</td>
								<td>
									<div class="dropdown cb-ledger-dropdown">
										<button class="btn btn-sm btn-outline-secondary rounded-pill dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false" aria-label="Actions for transfer #<?=esc((string)$transferId)?>">
											<i class="fa-solid fa-ellipsis" aria-hidden="true"></i><span class="d-none d-sm-inline ms-1">Actions</span>
										</button>
										<ul class="dropdown-menu dropdown-menu-end shadow">
											<li><button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#commissionBankDetails<?=$transferId?>"><i class="fa-solid fa-eye me-2 text-primary"></i>View details</button></li>
											<?php if ($whatsappUrl !== ''): ?><li><a class="dropdown-item text-success" href="<?=esc($whatsappUrl)?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp me-2"></i>Prompt assigned admin</a></li><?php endif; ?>
											<?php if ($canReviewTransfer): ?>
												<li>
													<form method="post" data-commission-bank-ajax>
														<?=csrf()?>
														<input type="hidden" name="action" value="review_transfer">
														<input type="hidden" name="review_decision" value="approve">
														<input type="hidden" name="transfer_id" value="<?=esc((string)$transferId)?>">
														<button class="dropdown-item text-success" type="submit"><i class="fa-solid fa-check me-2"></i>Approve &amp; credit bank</button>
													</form>
												</li>
												<li><button class="dropdown-item text-danger" type="button" data-bs-toggle="modal" data-bs-target="#commissionBankRejectModal" data-transfer-id="<?=esc((string)$transferId)?>" data-bank-name="<?=esc((string)$transfer->bank_name)?>" data-transfer-amount="<?=esc($commissionBankFormat($transfer->amount))?>"><i class="fa-solid fa-xmark me-2"></i>Reject request</button></li>
											<?php endif; ?>
										</ul>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</section>
	<?php if ($commissionBankTransfers): ?>
		<div id="commissionBankDetailModals">
				<?php foreach ($commissionBankTransfers as $transfer): ?>
					<?php
						$transferStatus = strtolower(trim((string)($transfer->status ?? 'pending')));
						$transferId = (int)$transfer->id;
						$proofUrl = $commissionBankProofUrl($transfer->proof_image);
						$whatsappUrl = $commissionBankWhatsappUrl($transfer);
					?>
					<div class="modal fade" id="commissionBankDetails<?=$transferId?>" tabindex="-1" aria-labelledby="commissionBankDetailsTitle<?=$transferId?>" aria-hidden="true">
						<div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
							<div class="modal-content border-0 shadow-lg">
								<div class="modal-header">
									<div>
										<div class="small text-uppercase text-primary fw-bold">Transfer details</div>
										<h2 class="modal-title fs-5 fw-bold" id="commissionBankDetailsTitle<?=$transferId?>"><?=esc((string)$transfer->bank_name)?> · Request #<?=esc((string)$transferId)?></h2>
										<div class="small text-muted"><?=esc((string)$transfer->date_created)?> · <?=esc(trim((string)($transfer->creator_name ?? 'Unknown')))?></div>
									</div>
									<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
								</div>
								<div class="modal-body">
									<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
										<span class="badge rounded-pill <?=$transferStatus === 'approved' ? 'text-bg-success' : ($transferStatus === 'rejected' ? 'text-bg-danger' : 'text-bg-warning')?>"><?=esc(ucfirst($transferStatus))?></span>
										<strong class="fs-5"><?=esc($commissionBankFormat($transfer->amount))?></strong>
									</div>
									<dl class="row g-2 small mb-4">
										<dt class="col-sm-4 text-muted">Receiving bank</dt><dd class="col-sm-8 mb-0"><?=esc((string)$transfer->bank_name)?></dd>
										<dt class="col-sm-4 text-muted">Created by</dt><dd class="col-sm-8 mb-0"><?=esc(trim((string)($transfer->creator_name ?? '')) ?: 'User #' . (int)$transfer->created_by)?></dd>
										<dt class="col-sm-4 text-muted">Assigned admin</dt><dd class="col-sm-8 mb-0"><?=esc(trim((string)($transfer->assigned_admin_name ?? '')) ?: 'Unassigned')?></dd>
										<dt class="col-sm-4 text-muted">Created at</dt><dd class="col-sm-8 mb-0"><?=esc((string)$transfer->date_created)?></dd>
										<dt class="col-sm-4 text-muted">Last updated by</dt><dd class="col-sm-8 mb-0"><?=esc(trim((string)($transfer->updated_by_name ?? '')) ?: '—')?></dd>
										<dt class="col-sm-4 text-muted">Last updated at</dt><dd class="col-sm-8 mb-0"><?=esc((string)($transfer->date_updated ?? '') ?: '—')?></dd>
										<?php if (!empty($transfer->approved_at)): ?>
											<dt class="col-sm-4 text-muted">Reviewed by</dt><dd class="col-sm-8 mb-0"><?=esc(trim((string)($transfer->reviewer_name ?? '')) ?: '—')?></dd>
											<dt class="col-sm-4 text-muted">Reviewed at</dt><dd class="col-sm-8 mb-0"><?=esc((string)$transfer->approved_at)?></dd>
										<?php endif; ?>
									</dl>
									<?php if ($whatsappUrl !== ''): ?><a class="btn btn-success btn-sm rounded-pill mb-3" href="<?=esc($whatsappUrl)?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp me-1"></i>Prompt assigned admin on WhatsApp</a><?php endif; ?>
									<div class="row g-4">
										<div class="col-12 col-lg-7">
											<h3 class="h6 text-uppercase text-muted fw-bold mb-2">User allocations</h3>
											<?php foreach ($commissionBankTransferDetails[$transferId] ?? [] as $item): ?>
												<div class="d-flex justify-content-between border-bottom py-2 gap-2">
													<div><div class="fw-semibold"><?=esc((string)($item->user_name ?: 'User #' . $item->user_id))?></div><div class="small text-muted"><?=esc((string)$item->branch_name)?></div></div>
													<div class="fw-bold text-nowrap"><?=esc($commissionBankFormat($item->amount))?></div>
												</div>
											<?php endforeach; ?>
											<?php if ($transferStatus === 'rejected' && !empty($transfer->reject_reason)): ?>
												<div class="alert alert-danger py-2 mt-3 mb-0 small"><strong>Rejection reason:</strong> <?=esc((string)$transfer->reject_reason)?></div>
											<?php elseif ($transferStatus === 'approved'): ?>
												<div class="small text-success mt-3">Approved by <?=esc(trim((string)($transfer->reviewer_name ?? 'Admin')))?> · <?=esc((string)$transfer->approved_at)?></div>
											<?php endif; ?>
										</div>
										<div class="col-12 col-lg-5">
											<h3 class="h6 text-uppercase text-muted fw-bold mb-2">Transfer proof</h3>
											<button type="button" class="btn p-0 border-0 w-100" data-commission-proof-open data-proof-src="<?=esc($proofUrl)?>" data-proof-alt="Transfer proof for <?=esc((string)$transfer->bank_name)?> request #<?=esc((string)$transferId)?>" aria-label="View transfer proof image">
												<img class="cb-proof-thumbnail" src="<?=esc($proofUrl)?>" alt="Transfer proof thumbnail">
											</button>
											<div class="small text-muted mt-2">Select the image to enlarge.</div>
										</div>
									</div>
								</div>
								<div class="modal-footer">
									<button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">Close</button>
								</div>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>

<?php if ($commissionBankIsAdmin): ?>
	<div class="modal fade" id="commissionBankRejectModal" tabindex="-1" aria-labelledby="commissionBankRejectModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content border-0 shadow-lg">
				<form method="post" data-commission-bank-ajax>
					<?=csrf()?>
					<input type="hidden" name="action" value="review_transfer">
					<input type="hidden" name="review_decision" value="reject">
					<input type="hidden" name="transfer_id" id="commissionBankRejectTransferId" value="">
					<div class="modal-header">
						<div>
							<div class="small text-uppercase text-danger fw-bold">Reject transfer</div>
							<h2 class="modal-title fs-5 fw-bold" id="commissionBankRejectModalLabel">Confirm rejection</h2>
						</div>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
					</div>
					<div class="modal-body">
						<p class="text-muted mb-3" id="commissionBankRejectContext"></p>
						<label class="form-label fw-semibold" for="commissionBankRejectReason">Reason for rejection <span class="text-danger">*</span></label>
						<textarea class="form-control" id="commissionBankRejectReason" name="reject_reason" rows="4" maxlength="500" required placeholder="Explain why this transfer request is being rejected."></textarea>
						<div class="form-text">Required, up to 500 characters. The reason will be recorded with the request.</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">Keep request</button>
						<button type="submit" class="btn btn-danger rounded-pill"><i class="fa-solid fa-xmark me-1"></i> Reject request</button>
					</div>
				</form>
			</div>
		</div>
	</div>
<?php endif; ?>

<div class="modal fade" id="commissionBankProofImageModal" tabindex="-1" aria-labelledby="commissionBankProofImageTitle" aria-hidden="true">
	<div class="modal-dialog modal-xl modal-dialog-centered">
		<div class="modal-content border-0 shadow-lg">
			<div class="modal-header">
				<h2 class="modal-title fs-6" id="commissionBankProofImageTitle">Transfer proof</h2>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body text-center">
				<img id="commissionBankProofFullImage" class="cb-proof-image" src="" alt="">
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="commissionBankWhatsAppPromptModal" tabindex="-1" aria-labelledby="commissionBankWhatsAppPromptTitle" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content border-0 shadow-lg">
			<div class="modal-header">
				<div>
					<div class="small text-uppercase text-success fw-bold">Transfer submitted</div>
					<h2 class="modal-title fs-5 fw-bold" id="commissionBankWhatsAppPromptTitle">Notify the assigned admin</h2>
				</div>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<p class="text-muted">Your request is pending approval. Review the prepared WhatsApp message, then send it to the assigned admin.</p>
				<label class="form-label fw-semibold" for="commissionBankWhatsAppMessage">WhatsApp message</label>
				<textarea class="form-control font-monospace small" id="commissionBankWhatsAppMessage" rows="10" readonly></textarea>
				<div class="alert alert-warning small mt-3 mb-0 d-none" id="commissionBankWhatsAppUnavailable" role="status"></div>
			</div>
			<div class="modal-footer flex-wrap">
				<a class="btn btn-success rounded-pill" id="commissionBankWhatsAppSend" href="#" target="_blank" rel="noopener">
					<i class="fa-brands fa-whatsapp me-1"></i> Send WhatsApp prompt
				</a>
				<a class="btn btn-outline-primary rounded-pill" id="commissionBankWhatsAppTransaction" href="#" target="_blank" rel="noopener">
					<i class="fa-solid fa-arrow-up-right-from-square me-1"></i> View transaction
				</a>
				<button type="button" class="btn btn-outline-secondary rounded-pill" id="commissionBankWhatsAppCopy">
					<i class="fa-regular fa-copy me-1"></i> Copy message
				</button>
				<button type="button" class="btn btn-link text-muted" data-bs-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
	let returnToDetailsModal = null;
	let searchDebounce = null;
	let refreshController = null;
	let refreshRequestId = 0;
	let loadingRequests = 0;
	const formatMoney = amount => 'GH₵ ' + Number(amount || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});

	function beginLoading(message) {
		loadingRequests++;
		const overlay = document.getElementById('commissionBankLoading');
		const messageElement = document.getElementById('commissionBankLoadingMessage');
		if (messageElement) messageElement.textContent = message;
		if (overlay) overlay.hidden = false;
		document.body.setAttribute('aria-busy', 'true');
	}

	function endLoading() {
		loadingRequests = Math.max(0, loadingRequests - 1);
		if (loadingRequests > 0) return;
		const overlay = document.getElementById('commissionBankLoading');
		if (overlay) overlay.hidden = true;
		document.body.removeAttribute('aria-busy');
	}

	function setButtonLoading(button, message) {
		if (!button) return () => {};
		const originalHtml = button.innerHTML;
		button.disabled = true;
		button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>' + message;
		return function () {
			if (!button.isConnected) return;
			button.innerHTML = originalHtml;
			button.disabled = false;
		};
	}

	function showFeedback(message, success) {
		const feedback = document.getElementById('commissionBankFeedback');
		if (!feedback) return;
		feedback.className = 'alert alert-dismissible fade show cb-ajax-feedback mb-3 ' + (success ? 'alert-success' : 'alert-danger');
		feedback.textContent = message;
	}

	function showTransferActionAlert(title, message, success) {
		const icon = success ? 'success' : 'error';
		if (typeof window.basicAdminSwalAlert === 'function') {
			return window.basicAdminSwalAlert(title, message, icon);
		}
		if (window.Swal && typeof window.Swal.fire === 'function') {
			const options = {title: title, text: message, icon: icon};
			if (typeof window.basicAdminSwalTheme === 'function') {
				Object.assign(options, window.basicAdminSwalTheme());
			}
			return window.Swal.fire(options);
		}
		window.alert(message);
		return Promise.resolve();
	}

	function showWhatsAppPrompt(prompt) {
		if (!prompt || !window.bootstrap || !window.bootstrap.Modal) return;
		const modal = document.getElementById('commissionBankWhatsAppPromptModal');
		const message = document.getElementById('commissionBankWhatsAppMessage');
		const sendButton = document.getElementById('commissionBankWhatsAppSend');
		const transactionLink = document.getElementById('commissionBankWhatsAppTransaction');
		const unavailable = document.getElementById('commissionBankWhatsAppUnavailable');
		if (!modal || !message || !sendButton || !transactionLink || !unavailable) return;
		message.value = prompt.message || '';
		transactionLink.href = prompt.transaction_url || '#';
		sendButton.href = prompt.whatsapp_url || '#';
		sendButton.classList.toggle('d-none', !prompt.whatsapp_url);
		unavailable.textContent = prompt.unavailable_reason || 'The assigned admin does not have a WhatsApp number configured.';
		unavailable.classList.toggle('d-none', Boolean(prompt.whatsapp_url));
		window.bootstrap.Modal.getOrCreateInstance(modal).show();
	}

	function initializeTransferModal() {
		const modal = document.getElementById('commissionBankTransferModal');
		if (!modal) return;
		const form = modal.querySelector('#commissionBankTransferForm');
		const amountInputs = Array.from(modal.querySelectorAll('.commission-bank-amount'));
		const totalLabel = modal.querySelector('#commissionBankTotal');
		const countLabel = modal.querySelector('#commissionBankSelectedCount');
		const submitButton = modal.querySelector('#commissionBankSubmit');
		if (!amountInputs.length || !totalLabel || !countLabel || !submitButton) return;

		function updateCommissionBankTotal() {
			let total = 0;
			let selectedUsers = 0;
			amountInputs.forEach(input => {
				const max = Number(input.dataset.available || 0);
				const raw = input.value.trim();
				let value = raw === '' ? 0 : Number(raw);
				if (!Number.isFinite(value) || value < 0) {
					value = 0;
					input.value = '0.00';
				}
				if (value > max) {
					value = max;
					input.value = max.toFixed(2);
				}
				total += value;
				if (value > 0) selectedUsers++;
			});
			totalLabel.textContent = formatMoney(total);
			countLabel.textContent = selectedUsers ? selectedUsers + ' user' + (selectedUsers === 1 ? '' : 's') + ' selected' : 'No commission amounts selected';
			submitButton.disabled = total <= 0 || !modal.querySelector('#commissionBankAssignedAdmin')?.value;
		}

		amountInputs.forEach(input => {
			input.addEventListener('focus', function () {
				if (Number(this.value) === 0) this.value = '';
			});
			input.addEventListener('input', updateCommissionBankTotal);
			input.addEventListener('blur', function () {
				const value = Number(this.value || 0);
				this.value = (Number.isFinite(value) && value > 0 ? value : 0).toFixed(2);
				updateCommissionBankTotal();
			});
		});
		modal.querySelector('#commissionBankAssignedAdmin')?.addEventListener('change', updateCommissionBankTotal);
		modal.querySelector('#commissionBankSelectAll')?.addEventListener('click', function () {
			amountInputs.forEach(input => input.value = Number(input.dataset.available || 0).toFixed(2));
			updateCommissionBankTotal();
		});
		modal.querySelector('#commissionBankClear')?.addEventListener('click', function () {
			amountInputs.forEach(input => input.value = '0.00');
			updateCommissionBankTotal();
		});
		modal.querySelectorAll('[data-commission-bank-fill]').forEach(button => {
			button.addEventListener('click', function () {
				const input = this.closest('.input-group')?.querySelector('.commission-bank-amount');
				if (!input) return;
				input.value = Number(input.dataset.available || 0).toFixed(2);
				updateCommissionBankTotal();
			});
		});
		modal.querySelector('#commissionBankProof')?.addEventListener('change', function () {
			const preview = modal.querySelector('#commissionBankProofPreview');
			if (!this.files || !this.files[0]) {
				preview.removeAttribute('src');
				preview.style.display = 'none';
				return;
			}
			preview.src = URL.createObjectURL(this.files[0]);
			preview.style.display = 'block';
		});
		form?.addEventListener('submit', function (event) {
			const bankName = modal.querySelector('#commissionBankName');
			const assignedAdmin = modal.querySelector('#commissionBankAssignedAdmin');
			const proof = modal.querySelector('#commissionBankProof');
			if (!bankName.value || !assignedAdmin.value || !proof.files || !proof.files.length || amountInputs.every(input => Number(input.value || 0) <= 0)) {
				event.preventDefault();
				showFeedback('Choose a bank and assigned admin, select commission amounts, and upload transfer proof.', false);
			}
			updateCommissionBankTotal();
		});
		updateCommissionBankTotal();
	}

	async function hideOpenModals() {
		if (!window.bootstrap || !window.bootstrap.Modal) return;
		const openModals = Array.from(document.querySelectorAll('.modal.show'));
		await Promise.all(openModals.map(modal => new Promise(resolve => {
			const instance = window.bootstrap.Modal.getOrCreateInstance(modal);
			const timeout = window.setTimeout(resolve, 500);
			modal.addEventListener('hidden.bs.modal', () => {
				window.clearTimeout(timeout);
				resolve();
			}, {once: true});
			instance.hide();
		})));
	}

	async function refreshCommissionBankPage(url) {
		if (refreshController) refreshController.abort();
		const requestId = ++refreshRequestId;
		const controller = new AbortController();
		refreshController = controller;
		beginLoading('Loading filtered transfer records…');
		try {
			const response = await fetch(url.href, {
				credentials: 'same-origin',
				signal: controller.signal,
				headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html'}
			});
			if (requestId !== refreshRequestId) return;
			if (!response.ok) throw new Error('Unable to refresh transfer data. Please try again.');
			const html = await response.text();
			if (requestId !== refreshRequestId) return;
			const nextDocument = new DOMParser().parseFromString(html, 'text/html');
			const replacements = ['commissionBankKpis', 'commissionBankLedger', 'commissionBankTransferModal', 'commissionBankDetailModals'];
			for (const id of replacements) {
				const current = document.getElementById(id);
				const next = nextDocument.getElementById(id);
				if (current && next) current.replaceWith(document.importNode(next, true));
				else if (current) current.remove();
				else if (next && id === 'commissionBankDetailModals') {
					document.querySelector('#commissionBankLedger')?.after(document.importNode(next, true));
				}
			}
			for (const badgeId of ['view_backoffice', 'backoffice_commission', 'backoffice_bank_transfers']) {
				const selector = '[data-notification-badge="' + badgeId + '"]';
				const currentBadges = document.querySelectorAll(selector);
				const nextBadges = nextDocument.querySelectorAll(selector);
				currentBadges.forEach((currentBadge, index) => {
					const nextBadge = nextBadges[index] || nextBadges[0];
					if (!nextBadge) return;
					currentBadge.className = nextBadge.className;
					currentBadge.style.cssText = nextBadge.style.cssText;
					currentBadge.textContent = nextBadge.textContent;
				});
			}
			window.history.replaceState({}, '', url.href);
			initializeTransferModal();
		} catch (error) {
			if (error.name === 'AbortError') return;
			throw error;
		} finally {
			if (requestId === refreshRequestId) refreshController = null;
			endLoading();
		}
	}

	function filterUrl() {
		const filterForm = document.getElementById('commissionBankLedgerFilters');
		const url = new URL(filterForm?.getAttribute('action') || window.location.href, window.location.href);
		if (filterForm) {
			new FormData(filterForm).forEach((value, key) => {
				const text = String(value).trim();
				if (text) url.searchParams.set(key, text);
				else url.searchParams.delete(key);
			});
		}
		url.searchParams.delete('transfer');
		return url;
	}

	document.addEventListener('submit', async function (event) {
		const form = event.target.closest('form[data-commission-bank-ajax]');
		if (!form || event.defaultPrevented) return;
		event.preventDefault();
		if (form.dataset.submitting === 'true') return;
		form.dataset.submitting = 'true';
		const submitButton = event.submitter;
		const action = form.querySelector('[name="action"]')?.value || '';
		const decision = form.querySelector('[name="review_decision"]')?.value || '';
		const loadingMessage = action === 'submit_transfer' ? 'Submitting transfer…' : (decision === 'reject' ? 'Rejecting transfer…' : 'Approving transfer…');
		const restoreButton = setButtonLoading(submitButton, loadingMessage);
		beginLoading(loadingMessage);
		let loadingEnded = false;
		try {
			const response = await fetch(form.getAttribute('action') || window.location.href, {
				method: 'POST',
				body: new FormData(form),
				credentials: 'same-origin',
				headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}
			});
			const responseText = await response.text();
			let result;
			try {
				result = JSON.parse(responseText);
			} catch {
				if (response.redirected || /\/login(?:[/?#]|$)/i.test(response.url)) {
					throw new Error('Your session has expired. Sign in again, then retry the transfer action.');
				}
				const detail = responseText.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 180);
				throw new Error('The server returned an unexpected response (HTTP ' + response.status + '). ' + (detail || 'Check the application error log.'));
			}
			if (!response.ok || !result.success) throw new Error(result.message || 'The transfer action failed.');
			await hideOpenModals();
			try {
				await refreshCommissionBankPage(filterUrl());
			} catch (refreshError) {
				endLoading();
				loadingEnded = true;
				const failureTitle = action === 'submit_transfer' ? 'Commission transfer submitted' : (decision === 'reject' ? 'Transfer rejected' : 'Transfer approved');
				await showTransferActionAlert(failureTitle, (result.message || 'Transfer updated.') + ' The page could not refresh automatically; reload it to see the latest ledger.', false);
				return;
			}
			endLoading();
			loadingEnded = true;
			const successTitle = action === 'submit_transfer' ? 'Commission transfer submitted' : (decision === 'reject' ? 'Transfer rejected' : 'Transfer approved');
			await showTransferActionAlert(successTitle, result.message || 'Transfer updated.', true);
			if (action === 'submit_transfer') showWhatsAppPrompt(result.whatsapp_prompt);
		} catch (error) {
			if (!loadingEnded) {
				endLoading();
				loadingEnded = true;
			}
			const failureTitle = action === 'submit_transfer' ? 'Unable to submit transfer' : (decision === 'reject' ? 'Unable to reject transfer' : 'Unable to approve transfer');
			await showTransferActionAlert(failureTitle, error.message || 'The transfer action could not be completed.', false);
		} finally {
			form.dataset.submitting = 'false';
			restoreButton();
			if (!loadingEnded) endLoading();
		}
	});

	document.addEventListener('submit', function (event) {
		if (event.target.id !== 'commissionBankLedgerFilters') return;
		event.preventDefault();
		const restoreButton = setButtonLoading(event.target.querySelector('button[type="submit"]'), 'Searching…');
		refreshCommissionBankPage(filterUrl())
			.catch(error => showFeedback(error.message, false))
			.finally(restoreButton);
	});
	document.addEventListener('input', function (event) {
		if (event.target.id !== 'commissionBankLedgerSearch') return;
		window.clearTimeout(searchDebounce);
		searchDebounce = window.setTimeout(() => refreshCommissionBankPage(filterUrl()).catch(error => showFeedback(error.message, false)), 300);
	});
	document.addEventListener('change', function (event) {
		if (!['commissionBankLedgerStart', 'commissionBankLedgerEnd'].includes(event.target.id)) return;
		window.clearTimeout(searchDebounce);
		searchDebounce = window.setTimeout(() => refreshCommissionBankPage(filterUrl()).catch(error => showFeedback(error.message, false)), 100);
	});
	document.addEventListener('click', function (event) {
		const clearFilters = event.target.closest('[data-commission-bank-clear-filters]');
		if (!clearFilters) return;
		event.preventDefault();
		window.clearTimeout(searchDebounce);
		searchDebounce = null;
		const filterForm = document.getElementById('commissionBankLedgerFilters');
		if (!filterForm) return;
		filterForm.reset();
		const url = new URL(filterForm.getAttribute('action') || window.location.href, window.location.href);
		refreshCommissionBankPage(url).catch(error => showFeedback(error.message, false));
	});
	document.addEventListener('click', async function (event) {
		const copyButton = event.target.closest('#commissionBankWhatsAppCopy');
		if (!copyButton) return;
		const message = document.getElementById('commissionBankWhatsAppMessage');
		if (!message) return;
		try {
			await navigator.clipboard.writeText(message.value);
			copyButton.innerHTML = '<i class="fa-solid fa-check me-1"></i> Copied';
		} catch (error) {
			message.focus();
			message.select();
			if (!document.execCommand('copy')) {
				showFeedback('Unable to copy the WhatsApp message. Select and copy the text manually.', false);
				return;
			}
			copyButton.innerHTML = '<i class="fa-solid fa-check me-1"></i> Copied';
		}
	});

	document.addEventListener('click', function (event) {
		const proofButton = event.target.closest('[data-commission-proof-open]');
		if (!proofButton) return;
		const detailsModal = proofButton.closest('.modal');
		const imageModal = document.getElementById('commissionBankProofImageModal');
		const fullImage = document.getElementById('commissionBankProofFullImage');
		if (!detailsModal || !imageModal || !fullImage || !window.bootstrap) return;
		returnToDetailsModal = detailsModal;
		fullImage.src = proofButton.dataset.proofSrc || '';
		fullImage.alt = proofButton.dataset.proofAlt || 'Transfer proof';
		detailsModal.addEventListener('hidden.bs.modal', function () {
			window.bootstrap.Modal.getOrCreateInstance(imageModal).show();
		}, {once: true});
		window.bootstrap.Modal.getOrCreateInstance(detailsModal).hide();
	});
	document.addEventListener('hidden.bs.modal', function (event) {
		if (event.target.id !== 'commissionBankProofImageModal') return;
		const detailsModal = returnToDetailsModal;
		returnToDetailsModal = null;
		document.getElementById('commissionBankProofFullImage').removeAttribute('src');
		if (detailsModal && detailsModal.isConnected) {
			window.bootstrap.Modal.getOrCreateInstance(detailsModal).show();
		}
	});
	document.addEventListener('show.bs.modal', function (event) {
		if (event.target.id !== 'commissionBankRejectModal') return;
		const trigger = event.relatedTarget;
		if (!trigger) return;
		document.getElementById('commissionBankRejectTransferId').value = trigger.dataset.transferId || '';
		document.getElementById('commissionBankRejectContext').textContent =
			(trigger.dataset.bankName || 'Bank transfer') + ' · ' + (trigger.dataset.transferAmount || '');
		document.getElementById('commissionBankRejectReason').value = '';
	});
	initializeTransferModal();
});
</script>
