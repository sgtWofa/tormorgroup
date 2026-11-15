<?php
$isBalanced = $generalBalanceError === '' && abs((float)$generalBalance['variance']) < 0.01;
$money = static function ($amount): string {
	return 'GH₵ ' . number_format((float)$amount, 2);
};
$generalBalanceFilterQuery = http_build_query(array_filter([
	'start_date' => $generalBalanceStartDate ?? '',
	'end_date' => $generalBalanceEndDate ?? '',
	'branch_id' => (int)($generalBalanceBranchId ?? 0),
], static function ($value): bool {
	return $value !== '' && $value !== 0;
}));
$generalBalanceFilterSuffix = $generalBalanceFilterQuery !== '' ? '?' . $generalBalanceFilterQuery : '';
$generalBalanceActiveTab = (string)($_GET['tab'] ?? '');
if (isset($_GET['check_id']) && (int)$_GET['check_id'] > 0) {
	$generalBalanceActiveTab = 'manual-checks';
} elseif (!in_array($generalBalanceActiveTab, ['manual-checks', 'adjustments'], true)) {
	$generalBalanceActiveTab = 'overview';
}
?>
<style>
	.general-balance-shell{width:100%;max-width:1180px;min-width:0;margin:1.5rem auto;box-sizing:border-box;overflow-x:clip}
	.general-balance-shell .row{margin-left:0;margin-right:0}
	.general-balance-shell [class^="col-"],.general-balance-shell [class*=" col-"]{min-width:0}
	.general-balance-hero{min-width:0;border:1px solid var(--bs-border-color);border-radius:1rem;padding:1.25rem 1.5rem;background:linear-gradient(135deg,rgba(13,110,253,.12),rgba(255,193,7,.14))}
	.general-balance-card{min-width:0;border:1px solid var(--bs-border-color);border-radius:1rem;background:var(--bs-body-bg)}
	.general-balance-filter,.gb-manual-reconciliation{border:1px solid var(--bs-border-color);border-radius:.85rem;background:var(--bs-body-bg)}
	.general-balance-filter>summary,.gb-manual-reconciliation>summary{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.85rem 1rem;cursor:pointer;list-style:none;font-weight:600}
	.general-balance-filter>summary::-webkit-details-marker,.gb-manual-reconciliation>summary::-webkit-details-marker{display:none}
	.general-balance-filter>summary::after,.gb-manual-reconciliation>summary::after{content:"+";font-size:1.25rem;line-height:1;color:var(--bs-primary)}
	.general-balance-filter[open]>summary::after,.gb-manual-reconciliation[open]>summary::after{content:"−"}
	.gb-filter-content,.gb-manual-content{padding:0 1rem 1rem}
	.gb-history-item{min-width:0;border:1px solid var(--bs-border-color);border-radius:.85rem;padding:1rem}
	.gb-history-item+.gb-history-item{margin-top:.75rem}
	.gb-history-meta{display:flex;flex-wrap:wrap;gap:.35rem 1rem}
	.gb-check-line{display:grid;grid-template-columns:minmax(0,1fr) repeat(3,minmax(5rem,9rem));gap:.75rem;align-items:start;padding:.65rem 0;border-bottom:1px solid var(--bs-border-color)}
	.gb-check-line:last-child{border-bottom:0}
	.gb-check-line>span{min-width:0;overflow-wrap:anywhere}
	.gb-check-amount{text-align:right;font-variant-numeric:tabular-nums}
	.gb-check-detail{min-width:0;overflow:hidden}
	.gb-tab-list{flex-wrap:wrap}
	.gb-tab-list .nav-link{white-space:normal}
	.modal-content{min-width:0}
	.gb-manual-input-row{min-width:0}
	#gb-manual-check-modal .modal-dialog{height:calc(100dvh - 2rem);max-height:calc(100dvh - 2rem)}
	#gb-manual-check-modal .modal-content{height:100%;max-height:100%}
	#gb-manual-check-modal .modal-content>form{display:flex;flex:1 1 auto;flex-direction:column;height:100%;max-height:100%;min-height:0;overflow:hidden}
	#gb-manual-check-modal .modal-body{flex:1 1 auto;min-height:0;max-height:65vh;max-height:min(65vh,42rem);overflow-y:auto;overscroll-behavior:contain}
	.general-balance-total{font-size:1.4rem;font-weight:700}
	.gb-breakdown-list{width:100%;min-width:0}
	.gb-entry{width:100%;min-width:0;display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:start;gap:.75rem;text-align:left;border:0;border-bottom:1px solid var(--bs-border-color);background:transparent;color:inherit;padding:.8rem .35rem}
	.gb-entry>strong{min-width:0;white-space:normal;overflow-wrap:anywhere;text-align:right}
	.gb-entry:hover,.gb-entry[aria-expanded="true"]{background:rgba(var(--bs-primary-rgb),.06)}
	.gb-entry-label{min-width:0;display:flex;align-items:flex-start;gap:.65rem}
	.gb-entry-label>span{min-width:0;overflow-wrap:anywhere}
	.gb-entry-icon{color:var(--bs-primary);transition:transform .18s ease}
	.gb-entry[aria-expanded="true"] .gb-entry-icon{transform:rotate(90deg)}
	.gb-detail{min-width:0;background:rgba(var(--bs-secondary-rgb),.035);border-bottom:1px solid var(--bs-border-color)}
	.gb-detail-row{min-width:0;display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:start;gap:.75rem;padding:.55rem .75rem .55rem 2.2rem;font-size:.88rem}
	.gb-detail-row>span:first-child{min-width:0;overflow-wrap:anywhere}
	.gb-detail-row>span:last-child{white-space:normal;text-align:right;overflow-wrap:anywhere}
	.gb-detail-row + .gb-detail-row{border-top:1px dashed var(--bs-border-color)}
	.gb-adjustment-form{border:1px solid var(--bs-border-color);border-radius:1rem;background:var(--bs-body-bg)}
	.gb-reconciliation-note{border-left:4px solid var(--bs-warning);border-radius:.5rem;background:rgba(var(--bs-warning-rgb),.1)}
	@media(max-width:575.98px){
		.general-balance-shell{width:100%;margin:1rem auto}
		.general-balance-hero{padding:1rem}
		.gb-entry{grid-template-columns:minmax(0,1fr) auto;gap:.5rem;padding:.75rem .25rem}
		.gb-detail-row{grid-template-columns:minmax(0,1fr);gap:.2rem;padding-left:1rem}
		.gb-detail-row>span:last-child{justify-self:end}
		.gb-check-line{grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:.35rem .75rem}
		.gb-check-line>span:first-child{grid-column:1/-1}
		.gb-check-amount{text-align:left}
	}
	@media print{.gb-no-print{display:none!important}.general-balance-shell{max-width:none;margin:0}}
</style>

<main class="general-balance-shell">
	<div class="general-balance-hero mb-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
		<div>
			<div class="small text-uppercase text-secondary fw-semibold">
				Backoffice · <?=!empty($generalBalanceSelectedBranchName) ? esc($generalBalanceSelectedBranchName) : 'All branches'?>
				· <?=($generalBalanceStartDate !== '' || $generalBalanceEndDate !== '') ? 'Filtered activity' : 'Current snapshot'?>
			</div>
			<h1 class="h3 mb-1">General Balancing</h1>
			<p class="text-secondary mb-0">Compare recorded activity for a date range and branch against the latest available balances.</p>
		</div>
		<div class="d-flex flex-wrap gap-2 gb-no-print">
			<a class="btn btn-outline-secondary" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/general_balance_pdf<?=esc($generalBalanceFilterSuffix)?>">
				<i class="fa-solid fa-file-pdf"></i> Export PDF
			</a>
			<a class="btn btn-outline-primary" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/general_balance<?=esc($generalBalanceFilterSuffix)?>">
				<i class="fa-solid fa-rotate"></i> Refresh balances
			</a>
		</div>
	</div>

	<details class="general-balance-filter mb-3 gb-no-print">
		<summary>
			<span><i class="fa-solid fa-sliders me-2 text-primary" aria-hidden="true"></i>Balance filters</span>
			<span class="small text-secondary fw-normal">
				<?php if($generalBalanceStartDate !== '' || $generalBalanceEndDate !== '' || $generalBalanceBranchId > 0): ?>
					<?php if($generalBalanceStartDate !== '' || $generalBalanceEndDate !== ''): ?><?=esc($generalBalanceStartDate !== '' ? $generalBalanceStartDate : 'Beginning')?> – <?=esc($generalBalanceEndDate !== '' ? $generalBalanceEndDate : 'Today')?><?php endif; ?>
					<?php if($generalBalanceBranchId > 0): ?> · <?=esc($generalBalanceSelectedBranchName)?><?php endif; ?>
				<?php else: ?>
					All branches · Current snapshot
				<?php endif; ?>
			</span>
		</summary>
		<div class="gb-filter-content">
			<form method="get" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/general_balance">
				<div class="row g-3 align-items-end">
					<div class="col-sm-6 col-lg-3">
						<label class="form-label" for="gb-start-date">Start date</label>
						<input class="form-control" id="gb-start-date" type="date" name="start_date" value="<?=esc($generalBalanceStartDate ?? '')?>">
					</div>
					<div class="col-sm-6 col-lg-3">
						<label class="form-label" for="gb-end-date">End date</label>
						<input class="form-control" id="gb-end-date" type="date" name="end_date" value="<?=esc($generalBalanceEndDate ?? '')?>">
					</div>
					<div class="col-sm-8 col-lg-4">
						<label class="form-label" for="gb-branch">Branch</label>
						<select class="form-select" id="gb-branch" name="branch_id">
							<option value="">All branches</option>
							<?php foreach(($generalBalanceBranches ?? []) as $branch): ?>
								<option value="<?= (int)($branch->id ?? 0) ?>" <?= (int)($generalBalanceBranchId ?? 0) === (int)($branch->id ?? 0) ? 'selected' : '' ?>>
									<?=esc((string)($branch->name ?? ''))?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-sm-4 col-lg-2 d-flex gap-2">
						<button class="btn btn-primary flex-grow-1" type="submit"><i class="fa-solid fa-filter me-1"></i>Apply</button>
						<a class="btn btn-outline-secondary" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/general_balance" aria-label="Clear filters" title="Clear filters"><i class="fa-solid fa-xmark"></i></a>
					</div>
				</div>
				<div class="small text-secondary mt-2">Dates filter dated accounting entries. Drawer balances are shown as of the selected end date; vault stock remains current.</div>
			</form>
		</div>
	</details>

	<?php if($generalBalanceError !== ''): ?>
		<div class="alert alert-danger" role="alert">
			<div class="fw-semibold">General Balancing is unavailable.</div>
			<div><?=esc($generalBalanceError)?></div>
		</div>
	<?php else: ?>
		<ul class="nav nav-tabs gb-tab-list mb-3" id="general-balance-tabs" role="tablist">
			<li class="nav-item" role="presentation">
				<button class="nav-link <?=$generalBalanceActiveTab === 'overview' ? 'active' : ''?>" id="gb-overview-tab" data-bs-toggle="tab" data-bs-target="#gb-overview-pane" type="button" role="tab" aria-controls="gb-overview-pane" aria-selected="<?=$generalBalanceActiveTab === 'overview' ? 'true' : 'false'?>">Overview</button>
			</li>
			<li class="nav-item" role="presentation">
				<button class="nav-link <?=$generalBalanceActiveTab === 'manual-checks' ? 'active' : ''?>" id="gb-manual-checks-tab" data-bs-toggle="tab" data-bs-target="#gb-manual-checks-pane" type="button" role="tab" aria-controls="gb-manual-checks-pane" aria-selected="<?=$generalBalanceActiveTab === 'manual-checks' ? 'true' : 'false'?>">
					Manual checks <span class="badge text-bg-secondary ms-1"><?=count($generalBalanceChecks)?></span>
				</button>
			</li>
			<li class="nav-item" role="presentation">
				<button class="nav-link <?=$generalBalanceActiveTab === 'adjustments' ? 'active' : ''?>" id="gb-adjustments-tab" data-bs-toggle="tab" data-bs-target="#gb-adjustments-pane" type="button" role="tab" aria-controls="gb-adjustments-pane" aria-selected="<?=$generalBalanceActiveTab === 'adjustments' ? 'true' : 'false'?>">Adjustments</button>
			</li>
		</ul>
		<div class="tab-content" id="general-balance-tab-content">
			<section class="tab-pane fade <?=$generalBalanceActiveTab === 'overview' ? 'show active' : ''?>" id="gb-overview-pane" role="tabpanel" aria-labelledby="gb-overview-tab" tabindex="0">
				<div class="row g-3 mb-3">
					<?php foreach(['left' => ['Accounting total', $generalBalance['left_total']], 'right' => ['Current balance total', $generalBalance['right_total']]] as $side => [$label, $amount]): ?>
						<div class="col-md-6">
							<div class="general-balance-card p-3">
								<div class="small text-uppercase text-secondary fw-semibold"><?=esc($label)?></div>
								<div class="general-balance-total"><?=esc($money($amount))?></div>
							</div>
						</div>
					<?php endforeach; ?>
					<div class="col-12">
						<div class="general-balance-card p-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
							<div>
								<div class="small text-uppercase text-secondary fw-semibold">Difference (accounting − balances)</div>
								<div class="general-balance-total"><?=esc($money($generalBalance['variance']))?></div>
							</div>
							<span class="badge fs-6 <?=$isBalanced ? 'text-bg-success' : 'text-bg-warning'?>"><?=$isBalanced ? 'Balanced' : 'Out of balance'?></span>
						</div>
					</div>
				</div>

				<div class="row g-3">
					<?php foreach(['left' => ['Accounting basis', $generalBalance['left']], 'right' => ['Live balances', $generalBalance['right']]] as $side => [$title, $items]): ?>
						<div class="col-lg-6">
							<section class="general-balance-card p-3 h-100">
								<h2 class="h5 mb-3"><?=esc($title)?></h2>
								<div class="gb-breakdown-list">
									<?php foreach($items as $index => $item): ?>
										<?php
											$details = $generalBalanceDetails[$side][$item['label']] ?? [];
											$collapseId = 'gb-' . $side . '-' . $index;
										?>
										<button class="gb-entry" type="button" data-bs-toggle="collapse" data-bs-target="#<?=esc($collapseId)?>" aria-expanded="false" aria-controls="<?=esc($collapseId)?>">
											<span class="gb-entry-label">
												<i class="fa-solid fa-chevron-right gb-entry-icon" aria-hidden="true"></i>
												<span><?=esc($item['label'])?><?php if(!empty($item['detail'])): ?><small class="text-secondary d-block"><?=esc($item['detail'])?></small><?php endif; ?></span>
											</span>
											<strong class="text-nowrap"><?=esc($money($item['value']))?></strong>
										</button>
										<div class="collapse gb-detail" id="<?=esc($collapseId)?>">
											<?php if(empty($details)): ?>
												<div class="gb-detail-row text-secondary"><span>No detailed entries</span><span>—</span></div>
											<?php else: ?>
												<?php foreach($details as $detail): ?>
													<div class="gb-detail-row">
														<span><?=esc($detail['label'])?><?php if(!empty($detail['detail'])): ?><small class="text-secondary d-block"><?=esc($detail['detail'])?></small><?php endif; ?></span>
														<span class="text-nowrap"><?=esc($money($detail['value']))?></span>
													</div>
												<?php endforeach; ?>
											<?php endif; ?>
										</div>
									<?php endforeach; ?>
								</div>
							</section>
						</div>
					<?php endforeach; ?>
				</div>
				<?php if(!$isBalanced && !empty($generalBalanceVarianceExplanation)): ?>
					<section class="gb-reconciliation-note p-3 mt-3" aria-labelledby="gb-reconciliation-heading">
						<h2 class="h6 mb-2" id="gb-reconciliation-heading">Reconciliation explanation</h2>
						<p class="mb-2"><?=esc($generalBalanceVarianceExplanation['summary'])?></p>
						<ul class="mb-0">
							<?php foreach($generalBalanceVarianceExplanation['checks'] as $check): ?><li><?=esc($check)?></li><?php endforeach; ?>
						</ul>
					</section>
				<?php endif; ?>
				<p class="small text-secondary mt-3 mb-0">
					Cash pickup accounting includes approved receipts, less confirmed cash paid out, plus pickup charges earned as income. CFA cash is converted to GHS using the system CFA rate. Sold SIMs use their recorded sale amount; unsold inventory uses retail price. Commission totals include recorded commission entries, outstanding cashout commissions, and transaction-level SIM, airtime bundle, and agent service commissions.
				</p>
			</section>

			<section class="tab-pane fade <?=$generalBalanceActiveTab === 'manual-checks' ? 'show active' : ''?>" id="gb-manual-checks-pane" role="tabpanel" aria-labelledby="gb-manual-checks-tab" tabindex="0">
				<div class="general-balance-card p-3">
					<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
						<div>
							<h2 class="h5 mb-1">Manual balance checks</h2>
							<p class="small text-secondary mb-0">Saved checks keep the system values captured at submission. Actuals appear only when you open a check.</p>
						</div>
						<button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#gb-manual-check-modal" <?=empty($generalBalance['right']) ? 'disabled' : ''?>>
							<i class="fa-solid fa-scale-balanced me-1" aria-hidden="true"></i> New manual check
						</button>
					</div>
					<div class="modal fade" id="gb-manual-check-details-modal" tabindex="-1" aria-labelledby="gb-check-detail-heading" aria-hidden="true">
						<div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
							<div class="modal-content">
								<div class="modal-header">
									<div>
										<div class="small text-uppercase text-secondary fw-semibold" id="gb-check-detail-number"></div>
										<h2 class="modal-title fs-5" id="gb-check-detail-heading">Category comparison</h2>
										<div class="small text-secondary" id="gb-check-detail-meta"></div>
									</div>
									<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
								</div>
								<div class="modal-body" id="gb-check-details-content"></div>
								<div class="modal-footer">
									<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
								</div>
							</div>
						</div>
					</div>
					<?php if(empty($generalBalanceChecks)): ?>
						<div class="text-center rounded border p-4">
							<div class="text-primary fs-3 mb-2"><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i></div>
							<div class="fw-semibold">No manual checks yet</div>
							<div class="small text-secondary">Create a check to record your counted balance for each category.</div>
						</div>
					<?php else: ?>
						<div class="small text-secondary mb-2"><?=count($generalBalanceChecks)?> saved check<?=count($generalBalanceChecks) === 1 ? '' : 's'?></div>
						<?php foreach($generalBalanceChecks as $check): ?>
							<?php
								$checkBranchLabel = 'All branches';
								foreach($generalBalanceBranches as $branch) {
									if ((int)($branch->id ?? 0) === (int)($check->branch_id ?? 0)) {
										$checkBranchLabel = (string)($branch->name ?? $checkBranchLabel);
										break;
									}
								}
								$checkDateRange = ($check->start_date ?? null) || ($check->end_date ?? null)
									? (($check->start_date ?? '') ?: 'Beginning') . ' – ' . (($check->end_date ?? '') ?: 'Today')
									: 'Current snapshot';
							?>
							<div class="gb-history-item">
								<div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
									<div class="min-w-0">
										<div class="fw-semibold">Check #<?= (int)$check->id ?> <span class="badge text-bg-light border"><?=esc($checkBranchLabel)?></span></div>
										<div class="gb-history-meta small text-secondary mt-1">
											<span><?=esc($checkDateRange)?></span>
											<span><?=esc((string)$check->date_created)?></span>
											<span>By <?=esc(trim((string)($check->created_by_name ?? '')) ?: 'User #' . (int)$check->created_by)?></span>
										</div>
									</div>
									<button class="btn btn-sm btn-outline-primary flex-shrink-0" type="button" data-gb-check-id="<?=(int)$check->id?>">
										<i class="fa-solid fa-eye me-1" aria-hidden="true"></i> View details
									</button>
								</div>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</section>

			<section class="tab-pane fade <?=$generalBalanceActiveTab === 'adjustments' ? 'show active' : ''?>" id="gb-adjustments-pane" role="tabpanel" aria-labelledby="gb-adjustments-tab" tabindex="0">
				<div class="general-balance-card p-3">
					<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
						<div>
							<h2 class="h5 mb-1">Balance adjustments</h2>
							<p class="small text-secondary mb-0">Audited adjustments are included in the selected balance totals.</p>
						</div>
						<?php if(function_exists('backoffice_user_is_admin') && backoffice_user_is_admin(get_session())): ?>
							<button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#gb-adjustment-modal"><i class="fa-solid fa-plus me-1" aria-hidden="true"></i> Record an adjustment</button>
						<?php endif; ?>
					</div>
					<?php if(empty($generalBalanceAdjustmentHistory)): ?>
						<div class="text-center rounded border p-4">
							<div class="fw-semibold">No adjustments recorded</div>
							<div class="small text-secondary">New audited adjustments will appear here.</div>
						</div>
					<?php else: ?>
						<?php foreach($generalBalanceAdjustmentHistory as $adjustment): ?>
							<div class="gb-history-item">
								<div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
									<div class="min-w-0">
										<div class="fw-semibold"><?=esc((string)$adjustment->reason)?></div>
										<div class="gb-history-meta small text-secondary mt-1">
											<span><?=strtolower((string)$adjustment->side) === 'left' ? 'Accounting basis' : 'Live balances'?></span>
											<span><?=esc((string)$adjustment->date_created)?></span>
											<span>By <?=esc(trim((string)($adjustment->created_by_name ?? '')) ?: 'Admin')?></span>
										</div>
									</div>
									<strong class="text-nowrap"><?=esc($money($adjustment->amount))?></strong>
								</div>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</section>
		</div>

		<?php if(!empty($generalBalance['right'])): ?>
			<div class="modal fade gb-no-print" id="gb-manual-check-modal" tabindex="-1" aria-labelledby="gb-manual-check-title" aria-hidden="true">
				<div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
					<div class="modal-content">
						<form method="post" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/general_balance">
							<div class="modal-header">
								<div>
									<div class="small text-uppercase text-secondary fw-semibold">Physical / external count</div>
									<h2 class="modal-title fs-5" id="gb-manual-check-title">New manual balance check</h2>
								</div>
								<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
							</div>
							<div class="modal-body">
								<?=csrf()?>
								<input type="hidden" name="action" value="manual_check">
								<input type="hidden" name="start_date" value="<?=esc($generalBalanceStartDate)?>">
								<input type="hidden" name="end_date" value="<?=esc($generalBalanceEndDate)?>">
								<input type="hidden" name="branch_id" value="<?=$generalBalanceBranchId > 0 ? (int)$generalBalanceBranchId : ''?>">
								<p class="small text-secondary">Enter the counted amount for every category. System balances are intentionally hidden during entry and will be shown in the saved check details.</p>
								<?php foreach(array_values($generalBalance['right']) as $index => $item): ?>
									<fieldset class="gb-manual-input-row border rounded p-3 mb-3" data-gb-category="<?=$index?>" data-next-item="1">
										<legend class="float-none w-auto fs-6 fw-semibold px-1 mb-2"><?=esc($item['label'])?></legend>
										<div data-gb-item-list>
											<div class="row g-2 align-items-center mb-2" data-gb-item>
												<div class="col-sm-6">
													<label class="visually-hidden" for="gb-manual-label-<?=$index?>-0">Item name for <?=esc($item['label'])?></label>
													<input class="form-control" id="gb-manual-label-<?=$index?>-0" name="manual_items[<?=$index?>][0]" maxlength="190" placeholder="Breakdown item (e.g. main till)" required>
												</div>
												<div class="col-sm-5">
													<label class="visually-hidden" for="gb-manual-amount-<?=$index?>-0">Amount</label>
													<div class="input-group">
														<span class="input-group-text">GH₵</span>
														<input class="form-control" id="gb-manual-amount-<?=$index?>-0" name="manual_amounts[<?=$index?>][0]" type="number" step="0.01" inputmode="decimal" placeholder="Counted amount" required>
													</div>
												</div>
												<div class="col-sm-1 d-grid">
													<button class="btn btn-outline-danger" type="button" data-gb-remove-item aria-label="Remove item" disabled><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
												</div>
											</div>
										</div>
										<button class="btn btn-sm btn-outline-primary" type="button" data-gb-add-item><i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Add breakdown item</button>
										<div class="small text-secondary mt-2">Category total: <strong data-gb-category-total>GH₵ 0.00</strong></div>
									</fieldset>
								<?php endforeach; ?>
								<div class="text-end fw-semibold">Entered total: <span id="gb-manual-entered-total">GH₵ 0.00</span></div>
							</div>
							<div class="modal-footer">
								<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
								<button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i>Save check</button>
							</div>
						</form>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<?php if(function_exists('backoffice_user_is_admin') && backoffice_user_is_admin(get_session())): ?>
			<div class="modal fade gb-no-print" id="gb-adjustment-modal" tabindex="-1" aria-labelledby="gb-adjustment-title" aria-hidden="true">
				<div class="modal-dialog modal-dialog-centered">
					<div class="modal-content">
						<form method="post" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/general_balance">
							<div class="modal-header">
								<div>
									<div class="small text-uppercase text-secondary fw-semibold">Audited balance change</div>
									<h2 class="modal-title fs-5" id="gb-adjustment-title">Record an adjustment</h2>
								</div>
								<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
							</div>
							<div class="modal-body">
								<?=csrf()?>
								<input type="hidden" name="action" value="adjustment">
								<input type="hidden" name="start_date" value="<?=esc($generalBalanceStartDate)?>">
								<input type="hidden" name="end_date" value="<?=esc($generalBalanceEndDate)?>">
								<input type="hidden" name="branch_id" value="<?=$generalBalanceBranchId > 0 ? (int)$generalBalanceBranchId : ''?>">
								<p class="small text-secondary">A negative amount reduces the selected side. This change is audited and included in balance totals.</p>
								<div class="mb-3">
									<label class="form-label" for="gb-adjustment-side">Apply to</label>
									<select class="form-select" id="gb-adjustment-side" name="side" required>
										<option value="left">Accounting basis</option>
										<option value="right">Live balances</option>
									</select>
								</div>
								<div class="mb-3">
									<label class="form-label" for="gb-adjustment-amount">Adjustment (GHS)</label>
									<input class="form-control" id="gb-adjustment-amount" name="amount" type="number" step="0.01" required placeholder="e.g. 125.00 or -25.00">
								</div>
								<div>
									<label class="form-label" for="gb-adjustment-reason">Reason</label>
									<textarea class="form-control" id="gb-adjustment-reason" name="reason" maxlength="500" rows="3" required></textarea>
								</div>
							</div>
							<div class="modal-footer">
								<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
								<button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Record adjustment</button>
							</div>
						</form>
					</div>
				</div>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</main>
<script>
	(() => {
		const params = new URLSearchParams(window.location.search);
		document.querySelectorAll('#general-balance-tabs [data-bs-toggle="tab"]').forEach(button => {
			button.addEventListener('shown.bs.tab', event => {
				const activeTab = event.target.id === 'gb-manual-checks-tab'
					? 'manual-checks'
					: (event.target.id === 'gb-adjustments-tab' ? 'adjustments' : '');
				const currentUrl = new URL(window.location.href);
				if (activeTab) currentUrl.searchParams.set('tab', activeTab);
				else currentUrl.searchParams.delete('tab');
				window.history.replaceState({}, '', currentUrl);
			});
		});
		const checkDetailsModal = document.getElementById('gb-manual-check-details-modal');
		const checkDetailsNumber = document.getElementById('gb-check-detail-number');
		const checkDetailsMeta = document.getElementById('gb-check-detail-meta');
		const checkDetailsContent = document.getElementById('gb-check-details-content');
		const formatMoney = amount => `GH₵ ${Number(amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
		const appendText = (parent, tagName, className, text) => {
			const element = document.createElement(tagName);
			element.className = className;
			element.textContent = text;
			parent.append(element);
			return element;
		};
		const loadCheckDetails = async checkId => {
			if (!checkDetailsModal || !checkDetailsContent) return;
			checkDetailsNumber.textContent = `Check #${checkId}`;
			checkDetailsMeta.textContent = '';
			checkDetailsContent.replaceChildren();
			appendText(checkDetailsContent, 'div', 'text-secondary', 'Loading check details…');
			try {
				const requestUrl = new URL(window.location.href);
				requestUrl.searchParams.set('check_details', '1');
				requestUrl.searchParams.set('check_id', checkId);
				const response = await fetch(requestUrl, {
					credentials: 'same-origin',
					headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
				});
				const responseText = await response.text();
				let data;
				try {
					data = JSON.parse(responseText);
				} catch (parseError) {
					throw new Error(response.redirected
						? 'Your session may have expired. Refresh the page and sign in again.'
						: 'The server returned an unexpected response while loading check details. Refresh the page and try again.');
				}
				if (!response.ok) throw new Error(data.error || 'Unable to load manual balance check details.');
				checkDetailsNumber.textContent = `Check #${data.check.id}`;
				checkDetailsMeta.textContent = `${data.check.branch} · ${data.check.date_range} · ${data.check.date_created}`;
				checkDetailsContent.replaceChildren();
				const heading = document.createElement('div');
				heading.className = 'gb-check-line small fw-semibold text-secondary d-none d-sm-grid';
				[
					['span', '', 'Balance category'],
					['span', 'gb-check-amount', 'Entered'],
					['span', 'gb-check-amount', 'System actual'],
					['span', 'gb-check-amount', 'Difference']
				].forEach(([tagName, className, text]) => appendText(heading, tagName, className, text));
				checkDetailsContent.append(heading);
				data.items.forEach(item => {
					const difference = Number(item.entered) - Number(item.system);
					const row = document.createElement('div');
					row.className = 'gb-check-line';
					appendText(row, 'span', '', item.category);
					[
						['Entered · ', item.entered, 'gb-check-amount'],
						['System · ', item.system, 'gb-check-amount'],
						['Difference · ', difference, `gb-check-amount fw-semibold ${Math.abs(difference) < 0.01 ? 'text-success' : 'text-danger'}`]
					].forEach(([label, amount, className], index) => {
						const amountCell = document.createElement('span');
						amountCell.className = className;
						appendText(amountCell, 'span', 'd-sm-none text-secondary', label);
						amountCell.append(document.createTextNode(`${index === 2 && difference > 0 ? '+' : ''}${formatMoney(amount)}`));
						row.append(amountCell);
					});
					checkDetailsContent.append(row);
					data.line_items.filter(lineItem => lineItem.category === item.category).forEach(lineItem => {
						const detailRow = document.createElement('div');
						detailRow.className = 'd-flex flex-wrap justify-content-between gap-2 small text-secondary ps-3 py-1';
						const detailLabel = document.createElement('span');
						const detailIcon = document.createElement('i');
						detailIcon.className = 'fa-solid fa-turn-up me-1';
						detailIcon.setAttribute('aria-hidden', 'true');
						detailLabel.append(detailIcon, document.createTextNode(lineItem.label));
						detailRow.append(detailLabel);
						appendText(detailRow, 'span', 'text-nowrap', formatMoney(lineItem.amount));
						checkDetailsContent.append(detailRow);
					});
				});
				const totalDifference = Number(data.check.manual_total) - Number(data.check.system_total);
				const totals = document.createElement('div');
				totals.className = 'd-flex flex-wrap justify-content-between gap-2 border-top pt-3 mt-2 fw-semibold';
				appendText(totals, 'span', '', 'Totals');
				appendText(totals, 'span', 'text-end', `${formatMoney(data.check.manual_total)} entered · ${formatMoney(data.check.system_total)} system · ${totalDifference > 0 ? '+' : ''}${formatMoney(totalDifference)} difference`);
				checkDetailsContent.append(totals);
				appendText(checkDetailsContent, 'div', 'small text-secondary mt-2', `Recorded by ${data.check.created_by}. This comparison uses the system snapshot saved with this check.`);
				const currentUrl = new URL(window.location.href);
				currentUrl.searchParams.set('tab', 'manual-checks');
				currentUrl.searchParams.set('check_id', String(data.check.id));
				window.history.replaceState({}, '', currentUrl);
			} catch (error) {
				checkDetailsContent.replaceChildren();
				appendText(checkDetailsContent, 'div', 'text-danger', error.message || 'Unable to load manual balance check details.');
			}
		};
		if (checkDetailsModal) {
			document.body.appendChild(checkDetailsModal);
			checkDetailsModal.addEventListener('hidden.bs.modal', () => {
				const currentUrl = new URL(window.location.href);
				currentUrl.searchParams.delete('check_id');
				window.history.replaceState({}, '', currentUrl);
			});
		}
		document.addEventListener('click', event => {
			const detailsButton = event.target.closest('[data-gb-check-id]');
			if (!detailsButton || !checkDetailsModal || !window.bootstrap || !window.bootstrap.Modal) return;
			const checkId = detailsButton.dataset.gbCheckId;
			if (!checkId) return;
			window.bootstrap.Modal.getOrCreateInstance(checkDetailsModal).show();
			loadCheckDetails(checkId);
		});
		const initializeBootstrapUi = () => {
			if (!window.bootstrap) return;
			const activeTabButton = document.querySelector('#general-balance-tabs .nav-link.active');
			if (activeTabButton && window.bootstrap.Tab) {
				window.bootstrap.Tab.getOrCreateInstance(activeTabButton).show();
			}
			if (checkDetailsModal && params.has('check_id') && window.bootstrap.Modal) {
				window.bootstrap.Modal.getOrCreateInstance(checkDetailsModal).show();
				loadCheckDetails(params.get('check_id'));
			}
		};
		if (document.readyState === 'complete') initializeBootstrapUi();
		else window.addEventListener('load', initializeBootstrapUi, {once: true});

		const createInput = (className, id, name, placeholder, type = 'text') => {
			const input = document.createElement('input');
			input.className = className;
			input.id = id;
			input.name = name;
			input.type = type;
			input.placeholder = placeholder;
			input.required = true;
			if (type === 'number') {
				input.step = '0.01';
				input.inputMode = 'decimal';
			} else {
				input.maxLength = 190;
			}
			return input;
		};
		const syncRemoveButtons = category => {
			const items = Array.from(category.querySelectorAll('[data-gb-item]'));
			items.forEach(item => {
				const button = item.querySelector('[data-gb-remove-item]');
				if (button) button.disabled = items.length <= 1;
			});
		};
		const updateEnteredTotals = () => {
			let enteredTotal = 0;
			document.querySelectorAll('[data-gb-category]').forEach(category => {
				let categoryTotal = 0;
				category.querySelectorAll('input[name^="manual_amounts["]').forEach(input => {
					if (input.value.trim() !== '' && Number.isFinite(Number(input.value))) categoryTotal += Number(input.value);
				});
				enteredTotal += categoryTotal;
				const output = category.querySelector('[data-gb-category-total]');
				if (output) output.textContent = `GH₵ ${categoryTotal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
			});
			const totalOutput = document.getElementById('gb-manual-entered-total');
			if (totalOutput) totalOutput.textContent = `GH₵ ${enteredTotal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
		};
		document.addEventListener('input', event => {
			if (event.target.matches('input[name^="manual_amounts["]')) updateEnteredTotals();
		});
		document.addEventListener('click', event => {
			const addButton = event.target.closest('[data-gb-add-item]');
			if (addButton) {
				const category = addButton.closest('[data-gb-category]');
				if (!category) return;
				const categoryIndex = Number(category.dataset.gbCategory);
				const itemIndex = Number(category.dataset.nextItem);
				if (!Number.isInteger(categoryIndex) || !Number.isInteger(itemIndex)) return;
				category.dataset.nextItem = String(itemIndex + 1);
				const item = document.createElement('div');
				item.className = 'row g-2 align-items-center mb-2';
				item.dataset.gbItem = '';
				const labelColumn = document.createElement('div');
				labelColumn.className = 'col-sm-6';
				const labelInput = createInput('form-control', `gb-manual-label-${categoryIndex}-${itemIndex}`, `manual_items[${categoryIndex}][${itemIndex}]`, 'Breakdown item (e.g. main till)');
				const label = document.createElement('label');
				label.className = 'visually-hidden';
				label.htmlFor = labelInput.id;
				label.textContent = 'Breakdown item';
				labelColumn.append(label, labelInput);
				const amountColumn = document.createElement('div');
				amountColumn.className = 'col-sm-5';
				const group = document.createElement('div');
				group.className = 'input-group';
				const prefix = document.createElement('span');
				prefix.className = 'input-group-text';
				prefix.textContent = 'GH₵';
				const amountInput = createInput('form-control', `gb-manual-amount-${categoryIndex}-${itemIndex}`, `manual_amounts[${categoryIndex}][${itemIndex}]`, 'Counted amount', 'number');
				group.append(prefix, amountInput);
				amountColumn.append(group);
				const actionColumn = document.createElement('div');
				actionColumn.className = 'col-sm-1 d-grid';
				const removeButton = document.createElement('button');
				removeButton.className = 'btn btn-outline-danger';
				removeButton.type = 'button';
				removeButton.dataset.gbRemoveItem = '';
				removeButton.setAttribute('aria-label', 'Remove item');
				const icon = document.createElement('i');
				icon.className = 'fa-solid fa-trash';
				icon.setAttribute('aria-hidden', 'true');
				removeButton.append(icon);
				actionColumn.append(removeButton);
				item.append(labelColumn, amountColumn, actionColumn);
				category.querySelector('[data-gb-item-list]').append(item);
				syncRemoveButtons(category);
				updateEnteredTotals();
				labelInput.focus();
				return;
			}
			const removeButton = event.target.closest('[data-gb-remove-item]');
			if (removeButton) {
				const category = removeButton.closest('[data-gb-category]');
				const item = removeButton.closest('[data-gb-item]');
				if (category && item && category.querySelectorAll('[data-gb-item]').length > 1) {
					item.remove();
					syncRemoveButtons(category);
					updateEnteredTotals();
				}
			}
		});
		updateEnteredTotals();
	})();
</script>
