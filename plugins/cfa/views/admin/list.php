<?php if(user_can('view_cfa')):?>
<?php
	$drawerBalanceGhs = $drawerSnapshot['ghs_balance'] ?? 0;
	$drawerBalanceCfa = $drawerSnapshot['cfa_balance'] ?? 0;
	$drawerHistoryGhs = $drawerSnapshot['ghs_history'] ?? [];
	$drawerHistoryCfa = $drawerSnapshot['cfa_history'] ?? [];
	$hasDrawer = !empty($drawerSnapshot);
	$cfaDenominations = [10000,5000,2000,1000,500,200,100,50,20,10];
	$rows = ($rows ?? []) ?: [];
	$totalTransactions = count($rows);
	$totalAmountCfa = $total_amount_cfa ?? 0;
	$totalAmountGhs = $total_amount_ghs ?? 0;
	$totalAmountInCfa = 0;
	$totalAmountInGhs = 0;
	$totalAmountOutCfa = 0;
	$totalAmountOutGhs = 0;
	foreach($rows as $rowTotal){
		$transactionTypeNormalized = function_exists('cfa_normalize_transaction_type')
			? cfa_normalize_transaction_type($rowTotal->transaction_type ?? '')
			: strtolower($rowTotal->transaction_type ?? '');
		$amountCfa = floatval($rowTotal->amount_cfa ?? 0);
		$amountGhs = floatval($rowTotal->amount_ghs ?? 0);
		if($transactionTypeNormalized === 'cfa to ghs' || $transactionTypeNormalized === 'cfa to ghc' || $transactionTypeNormalized === 'cfa to gh¢'){
			$totalAmountInCfa += $amountCfa;
			$totalAmountOutGhs += $amountGhs;
		}elseif($transactionTypeNormalized === 'ghs to cfa' || $transactionTypeNormalized === 'ghc to cfa' || $transactionTypeNormalized === 'gh¢ to cfa'){
			$totalAmountInGhs += $amountGhs;
			$totalAmountOutCfa += $amountCfa;
		}
	}
	$averageTicket = $totalTransactions ? ($totalAmountGhs / $totalTransactions) : 0;
	$initialTypeFilter = $cfaInitialTypeFilter ?? 'all';
	$initialTypeFilter = in_array($initialTypeFilter, ['all', 'buys', 'sells'], true) ? $initialTypeFilter : 'all';
	$currentUserId = (int)(get_session()->id ?? 0);
?>

<style>
	:root {
		--cfa-gold: #f6c343;
		--cfa-charcoal: #0f172a;
		--cfa-slate: #1e293b;
	}

	.active,
	#cfaTabNav .nav-link.active,
	#cfaTabNav .nav-link.active:focus,
	#cfaTabNav .nav-link.active:hover,
	#cfaTabNav .nav-item.show .nav-link,
	#cfaTabNav .nav-item.show .nav-link:focus,
	#cfaTabNav .nav-item.show .nav-link:hover {
		background-image: linear-gradient(135deg, rgba(var(--bs-primary-rgb, 13,110,253),0.12), rgba(var(--bs-warning-rgb, 255,193,7),0.12)) !important;
		background-color: transparent !important;
		color: var(--bs-body-color) !important;
		border-color: rgba(var(--bs-primary-rgb,13,110,253), 0.25) !important;
	}

	.cfa-hero {
		background: linear-gradient(135deg, rgba(246,195,67,0.15), rgba(79,70,229,0.25));
		border: 1px solid rgba(255,255,255,0.08);
		border-radius: 18px;
		padding: 24px;
		backdrop-filter: blur(6px);
	}

	body[data-bs-theme="light"] .cfa-hero {
		background: linear-gradient(135deg, rgba(246,195,67,0.2), rgba(79,70,229,0.12));
		border-color: rgba(15,23,42,0.08);
		color: #0f172a;
	}

	.cfa-hero .hero-title,
	.cfa-hero .hero-eyebrow,
	.cfa-hero .hero-muted {
		color: inherit;
	}

	.cfa-hero .hero-muted {
		opacity: .78;
	}

	body[data-bs-theme="light"] .cfa-hero .hero-muted {
		opacity: 1;
		color: #475569;
	}

	.cfa-stat-card {
		background: rgba(255,255,255,0.1);
		border-radius: 14px;
		padding: 14px 18px;
		border: 1px solid rgba(255,255,255,0.5);
	}

	body[data-bs-theme="light"] .cfa-stat-card {
		background: rgba(15,23,42);
		border-color: rgba(15,23,42,0.8);
		color: #0f172a;
	}

	.cfa-action-bar .btn {
		border-radius: 999px;
	}

	.cfa-action-bar .btn-group .btn {
		min-height: 34px;
		padding-inline: 1.1rem;
	}

	.date-filter-wrapper {
		position: relative;
	}

	.date-toggle-btn {
		border-radius: 999px;
		gap: 0.25rem;
		display: inline-flex;
		align-items: center;
	}

	.date-toggle-btn .status-dot {
		width: 8px;
		height: 8px;
		border-radius: 50%;
		background: var(--cfa-gold);
		display: inline-block;
	}

	.date-range-panel {
		position: absolute;
		top: calc(100% + 0.5rem);
		right: 0;
		background: var(--cfa-slate);
		border: 1px solid rgba(255,255,255,0.08);
		border-radius: 14px;
		padding: 1rem;
		min-width: 420px;
		box-shadow: 0 20px 45px rgba(15,23,42,0.35);
		display: none;
		z-index: 5;
	}

	.date-range-panel.show {
		display: block;
	}

	body[data-bs-theme="light"] .date-range-panel {
		border-color: rgba(15,23,42,0.08);
		box-shadow: 0 18px 40px rgba(15,23,42,0.15);
	}

	.date-filter-group .form-control,
	.date-filter-group .input-group-text {
		border-radius: 0;
	}

	.date-filter-group .input-group-text:first-of-type,
	.date-filter-group .form-control:first-of-type {
		border-top-left-radius: 999px;
		border-bottom-left-radius: 999px;
	}

	.date-filter-group .form-control:last-of-type {
		border-top-right-radius: 999px;
		border-bottom-right-radius: 999px;
	}

	.date-filter-group .input-group-text {
		background: rgba(255,255,255,0.08);
		border: 1px solid rgba(255,255,255,0.1);
		color: #94a3b8;
	}

	body[data-bs-theme="light"] .date-filter-group .input-group-text {
		background: #e2e8f0;
		border-color: #cbd5f5;
		color: #475569;
	}

	.date-range-panel .form-control.cfa-date-input {
		min-width: 140px;
	}

	body[data-bs-theme="light"] .date-filter-group .input-group-text {
		background: #e2e8f0;
		border-color: #aeaeaeff;
		color: #475569;
	}

	.date-filter-panel-actions {
		font-size: 12px;
		color: rgba(248,250,252,0.7);
	}

	body[data-bs-theme="light"] .date-filter-panel-actions {
		color: #475569;
	}

	@media (max-width: 576px) {
		.date-range-panel {
			position: static;
			width: 100%;
		}

		.date-filter-group {
			display: grid;
			grid-template-columns: auto minmax(0, 1fr);
			gap: .5rem;
		}

		.date-filter-group .input-group-text,
		.date-filter-group .form-control {
			width: 100%;
			min-width: 0;
			border-radius: .5rem !important;
		}

		.date-range-panel .form-control.cfa-date-input {
			width: 100%;
		}
	}

	.modern-table thead th {
		border: none;
		font-size: 11px;
		letter-spacing: .08em;
		text-transform: uppercase;
	}

	body[data-bs-theme="light"] .modern-table thead th {
		background: #f8fafc;
		color: #334155;
	}

	.modern-table td {
		border-color: rgba(255,255,255,0.05);
	}

	body[data-bs-theme="light"] .modern-table td {
		border-color: rgba(15,23,42,0.08);
		color: #0f172a;
	}

	body[data-bs-theme="light"] .modern-table tbody tr {
		background: #ffffff;
	}

	body[data-bs-theme="light"] .modern-table tbody tr:nth-child(even) {
		background: #f8fafc;
	}

	body[data-bs-theme="light"] .modern-table tbody tr:hover {
		background: #e2e8f0;
	}

	body[data-bs-theme="light"] .modern-table a.text-white {
		color: #0f172a !important;
	}

	.table-link {
		font-weight: 600;
	}

	body[data-bs-theme="light"] .table-link {
		color: #0f172a;
	}

	.status-pill {
		border-radius: 999px;
		padding: 4px 4px;
		font-size: 10px;
		font-weight: 600;
	}

	body[data-bs-theme="light"] .status-pill.bg-secondary {
		color: #ffffffff;
	}

	.avatar-sm {
		width: 34px;
		height: 34px;
		border-radius: 50%;
		object-fit: cover;
		border: 1px solid rgba(255,255,255,0.2);
	}

	.table-total-row th {
		background: rgba(128, 107, 56, 0.1) !important;
		border: none;
	}

	td.wrap {
		white-space: normal;
	}

	.cfa-mobile-details {
		min-width: 0;
		width: 100%;
		max-width: 500px;
		overflow-wrap: anywhere;
		word-break: normal;
	}

	.cfa-amount-stack {
		display: flex;
		flex-direction: column;
		gap: 2px;
		align-items: flex-end;
		min-width: 0;
		max-width: 100%;
	}

	.cfa-amount-stack > div {
		min-width: 0;
		max-width: 100%;
		overflow-wrap: anywhere;
	}

	.rate-chart-shell {
		background: linear-gradient(180deg, rgba(15,23,42,0.96), rgba(15,23,42,0.9));
		border-radius: 14px;
		border: 1px solid rgba(148,163,184,0.18);
		color: #f8fafc;
		overflow: hidden;
	}

	body[data-bs-theme="light"] .rate-chart-shell,
	html[data-bs-theme="light"] .rate-chart-shell {
		background: var(--bs-body-bg);
		border-color: var(--bs-border-color);
		color: var(--bs-body-color);
	}

	.rate-chart-toolbar {display:flex;flex-wrap:wrap;gap:10px;justify-content:space-between;align-items:center;padding:16px 18px;border-bottom:1px solid rgba(148,163,184,0.18);}
	.rate-chart-toolbar .btn-group .btn {border-radius:999px;}
	.rate-chart-card {min-height:360px;padding:16px 18px 22px;}
	.rate-chart-canvas-wrap {position:relative;height:360px;width:100%;}
	.rate-metric {border:1px solid rgba(148,163,184,0.18);border-radius:10px;padding:12px 14px;background:rgba(255,255,255,0.04);min-height:86px;}
	.rate-metric .label {color:#94a3b8;font-size:12px;text-transform:uppercase;}
	.rate-metric .value {font-size:1.45rem;font-weight:700;letter-spacing:0;}
	.rate-range-panel {background:rgba(15,23,42,0.75);border:1px solid rgba(148,163,184,0.18);border-radius:12px;padding:12px;}
	.rate-range-panel .form-control,
	.rate-range-panel .input-group-text {border-color:rgba(148,163,184,0.3);}
	.rate-chart-empty {position:absolute;inset:0;display:none;align-items:center;justify-content:center;text-align:center;color:#cbd5e1;background:rgba(15,23,42,0.7);border-radius:12px;}
	.rate-chart-empty.show {display:flex;}
	.rate-mini-grid {display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;}
	.rate-mini-stat {border:1px solid rgba(148,163,184,0.18);border-radius:10px;padding:10px 12px;background:rgba(255,255,255,0.035);}
	.rate-mini-stat .label {font-size:11px;text-transform:uppercase;color:#94a3b8;}
	.rate-mini-stat .value {font-weight:700;}
	.rate-alert-line {display:none;border-radius:10px;padding:10px 12px;margin-bottom:12px;}
	.rate-alert-line.show {display:block;}

	body[data-bs-theme="light"] .rate-chart-toolbar,
	html[data-bs-theme="light"] .rate-chart-toolbar {
		border-bottom-color: var(--bs-border-color);
	}

	body[data-bs-theme="light"] .rate-chart-shell .text-white-50,
	html[data-bs-theme="light"] .rate-chart-shell .text-white-50 {
		color: var(--bs-secondary-color) !important;
	}

	body[data-bs-theme="light"] .rate-chart-toolbar .btn-outline-light,
	html[data-bs-theme="light"] .rate-chart-toolbar .btn-outline-light {
		color: var(--bs-body-color);
		border-color: var(--bs-border-color);
		background: var(--bs-body-bg);
	}

	body[data-bs-theme="light"] .rate-chart-toolbar .btn-outline-light.active,
	body[data-bs-theme="light"] .rate-chart-toolbar .btn-outline-light:hover,
	html[data-bs-theme="light"] .rate-chart-toolbar .btn-outline-light.active,
	html[data-bs-theme="light"] .rate-chart-toolbar .btn-outline-light:hover {
		background: var(--bs-primary);
		border-color: var(--bs-primary);
		color: #fff;
	}

	body[data-bs-theme="light"] .rate-metric,
	html[data-bs-theme="light"] .rate-metric,
	body[data-bs-theme="light"] .rate-mini-stat,
	html[data-bs-theme="light"] .rate-mini-stat,
	body[data-bs-theme="light"] .rate-range-panel,
	html[data-bs-theme="light"] .rate-range-panel {
		background: var(--bs-tertiary-bg, #f8f9fa);
		border-color: var(--bs-border-color);
	}

	body[data-bs-theme="light"] .rate-metric .label,
	html[data-bs-theme="light"] .rate-metric .label,
	body[data-bs-theme="light"] .rate-mini-stat .label,
	html[data-bs-theme="light"] .rate-mini-stat .label,
	body[data-bs-theme="light"] .rate-range-panel .form-label,
	html[data-bs-theme="light"] .rate-range-panel .form-label {
		color: var(--bs-secondary-color) !important;
	}

	body[data-bs-theme="light"] .rate-chart-empty,
	html[data-bs-theme="light"] .rate-chart-empty {
		background: rgba(255,255,255,0.86);
		color: var(--bs-secondary-color);
	}	@media (max-width: 991.98px) {
		.table-responsive {
			min-width: 0;
			overflow-x: hidden;
		}

		.table-responsive > .table {
			width: 100%;
			table-layout: fixed;
		}

		.table-responsive th,
		.table-responsive td {
			overflow-wrap: anywhere;
		}

		.cfa-transaction-table-wrap {
			min-width: 0;
			overflow-x: hidden;
		}

		.modern-table {
			width: 100%;
			min-width: 0;
		}

		.modern-table thead tr,
		.modern-table tbody tr {
			display: grid;
			grid-template-columns: minmax(160px, 1fr) minmax(140px, 220px) minmax(56px, 90px);
			column-gap: 12px;
			align-items: start;
		}

		.modern-table thead th,
		.modern-table tbody td {
			width: auto;
			min-width: 0;
			white-space: normal;
		}

		.modern-table tbody tr {
			padding: 12px 12px;
		}

		.modern-table tbody td {
			padding: 0;
			border-top: none;
		}

		.modern-table tbody td:last-child {
			justify-self: end;
			align-self: center;
		}
	}

	@media (max-width: 576px) {
		.cfa-action-bar .btn-group {
			flex-wrap: wrap;
		}

		.modern-table thead tr,
		.modern-table tbody tr {
			grid-template-columns: 1fr auto;
			row-gap: 8px;
		}

		.modern-table thead th.cfa-details-col,
		.modern-table tbody td.cfa-details-col {
			grid-column: 1 / -1;
			min-width: 0;
		}

		.cfa-mobile-details {
			max-width: none;
		}
	}

	@media (min-width: 768px) and (max-width: 991.98px) {
		.cfa-stat-card {
			padding: 14px 16px;
		}
		.cfa-stat-card h3 {
			font-size: 1.35rem;
		}
	}
</style>

<?php if($success = message_success('', true)):?>
	<div class="alert alert-success shadow-sm"><?=$success?></div>
<?php endif;?>
<?php if($fail = message_fail('', true)):?>
	<div class="alert alert-danger shadow-sm"><?=$fail?></div>
<?php endif;?>

<div class="cfa-hero my-3">
	<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
		<div>
			<p class="text-uppercase small mb-1 hero-eyebrow">CFA Exchange Overview</p>
			<h3 class="fw-semibold mb-0 hero-title">Seamless FX desk</h3>
			<p class="hero-muted mb-0">Keep your CFA <> GHS operations synchronized with real-time drawer visibility.</p>
		</div>

		<div class="d-flex flex-wrap gap-2 align-items-center">
			<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/buy" class="btn btn-sm btn-warning text-dark fw-semibold"><i class="fa-solid fa-arrow-down me-1"></i> Buy CFA</a>
			<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="bottom" title="Buy CFA" data-bs-content="Buy: customer pays CFA, receives GHS."><i class="fa-solid fa-circle-info"></i></button>
			<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/sell" class="btn btn-sm btn-outline-secondary fw-semibold"><i class="fa-solid fa-arrow-up me-1"></i> Sell CFA</a>
			<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="bottom" title="Sell CFA" data-bs-content="Sell: customer pays GHS, receives CFA."><i class="fa-solid fa-circle-info"></i></button>
		</div>
	</div>
	<div class="row g-3 mt-3">
		<div class="col-12 col-sm-6 col-lg-4">
			<div class="cfa-stat-card h-100">
				<span class="label">Total CFA volume</span>
				<h3 class="mt-1 mb-0 text-warning" id="heroCfaVolume"><?=number_format($totalAmountCfa,2)?> CFA</h3>
			</div>
		</div>
		<div class="col-12 col-sm-6 col-lg-4">
			<div class="cfa-stat-card h-100">
				<span class="label">Total GHS volume</span>
				<h3 class="mt-1 mb-0 text-info" id="heroGhsVolume">GHS <?=number_format($totalAmountGhs,2)?></h3>
				<small class="hero-muted">Avg ticket GHS <span id="heroAvgTicket"><?=number_format($averageTicket,2)?></span></small>
			</div>
		</div>
		<div class="col-12 col-lg-4">
			<div class="cfa-stat-card h-100">
				<span class="label">Transactions logged</span>
				<h3 class="mt-1 mb-0" id="heroTxCount"><?=$totalTransactions?></h3>
				<small class="hero-muted" id="heroLatestActivity"><?= $totalTransactions ? 'Last updated '.get_date_time($rows[0]->date_created ?? '') : 'No activity yet'?></small>
			</div>
		</div>
	</div>
</div>

<ul class="nav nav-tabs" id="cfaTabNav" role="tablist">
	<li class="nav-item" role="presentation">
		<button class="nav-link active" id="transactions-tab" data-bs-toggle="tab" data-bs-target="#transactions" type="button" role="tab">Transactions</button>
	</li>
	<li class="nav-item" role="presentation">
		<button class="nav-link" id="rates-chart-tab" data-bs-toggle="tab" data-bs-target="#rates-chart" type="button" role="tab">
			<i class="fa-solid fa-chart-line me-1"></i> Rate Chart
		</button>
	</li>
	<li class="nav-item" role="presentation">
		<button class="nav-link" id="cashdrawer-tab" data-bs-toggle="tab" data-bs-target="#cashdrawer" type="button" role="tab">
			Cashdrawer Snapshot
		</button>
	</li>
</ul>

<div class="tab-content py-3" id="cfaTabsContent">
	<div class="tab-pane fade show active" id="transactions" role="tabpanel" aria-labelledby="transactions-tab">
		<div class="card border-0 shadow-sm cfa-action-bar mb-3">
			<div class="card-body d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
				<div>
					<h5 class="mb-0">CFA Transactions</h5>
					<small class="text-muted">Track every buy/sell routed through your cashdrawer.</small>
				</div>
				<div class="d-flex flex-column flex-md-row align-items-md-center gap-3 w-100 justify-content-md-end">
					<div class="btn-group" role="group" aria-label="Transaction filters">
						<button type="button" class="btn btn-outline-secondary btn-sm js-filter-btn active" data-filter="all"><i class="fa fa-list me-1"></i> All</button>
						<button type="button" class="btn btn-outline-secondary btn-sm js-filter-btn" data-filter="buys"><i class="fa fa-arrow-down me-1"></i> Buys</button>
						<button type="button" class="btn btn-outline-secondary btn-sm js-filter-btn" data-filter="sells"><i class="fa fa-arrow-up me-1"></i> Sells</button>

                        <button id="export-pdf-btn" class="btn btn-sm btn-outline-danger export-pdf-btn" onclick="exportPdf()">
                            <i class="fa fa-file-pdf me-1"></i> PDF
                        </button>
					</div>
					<div class="date-filter-wrapper">
						<button type="button" class="btn btn-outline-secondary btn-sm date-toggle-btn" id="toggleDateFilters">
							<i class="fa fa-calendar me-1"></i> Date
							<span class="status-dot d-none" id="dateFilterActiveDot"></span>
						</button>
						<div class="date-range-panel" id="dateRangePanel">
							<div class="input-group input-group-sm date-filter-group mb-3">
								<span class="input-group-text"><i class="fa fa-calendar me-1"></i>From</span>
								<input type="date" class="form-control cfa-date-input" id="filterStartDate">
								<span class="input-group-text">To</span>
								<input type="date" class="form-control cfa-date-input" id="filterEndDate">
							</div>
							<div class="d-flex justify-content-between align-items-center date-filter-panel-actions">
								<small>Choose a date range to refine the list.</small>
								<button type="button" class="btn btn-outline-secondary btn-sm" id="clearDateFilters"><i class="fa fa-times me-1"></i>Clear</button>
							</div>
							<small class="text-muted d-block mt-2" id="dateRangeSummary">Showing: All dates</small>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="card border-0 shadow-sm">
			<div class="card-body p-0">
				<div class="table-responsive cfa-transaction-table-wrap">
					<table class="table align-middle custom-table modern-table" id="cfa-table">
                        <thead>
                            <tr>
                                <th class="d-none d-lg-table-cell">#</th>
                                <th class="d-none d-lg-table-cell">Date created</th>
                                <th class="d-none d-lg-table-cell">Customer & Description</th>
                                <th class="d-none d-lg-table-cell">Type / Status</th>
                                <th class="d-lg-none col-4 cfa-details-col">Details</th>
                                <th class="text-end d-none d-lg-table-cell">Amount  IN/OUT</th>
                                <th class="text-end d-lg-none">Amounts</th>
                                <th class="d-none d-lg-table-cell">Created by</th>
                                <th class="text-end">ACTIONS</th>
                            </tr>
                        </thead>
						<tbody id="activityTable">
							<tr>
								<td colspan="11" class="text-center text-muted py-4">Loading transactions...</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
			<div class="card-footer d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
				<p id="totalPagesText" class="mb-0 text-muted"></p>
				<nav id="paginationNav" aria-label="Page navigation">
			      <ul class="pagination pagination-sm justify-content-center mb-0"></ul>
			    </nav>
			</div>
		</div>
	</div>

	<div class="tab-pane fade" id="rates-chart" role="tabpanel" aria-labelledby="rates-chart-tab">
		<div class="rate-chart-shell shadow-sm">
			<div class="rate-chart-toolbar">
				<div>
					<h5 class="mb-0">CFA Rate Movement</h5>
					<small class="text-white-50">Buying and selling rate changes, updated live from settings.</small>
				</div>
				<div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Rate range filters">
					<button type="button" class="btn btn-outline-light js-rate-range active" data-range="daily">Daily</button>
					<button type="button" class="btn btn-outline-light js-rate-range" data-range="weekly">Weekly</button>
					<button type="button" class="btn btn-outline-light js-rate-range" data-range="monthly">Monthly</button>
					<button type="button" class="btn btn-outline-light js-rate-range" data-range="quarterly">Quarterly</button>
					<button type="button" class="btn btn-outline-light js-rate-range" data-range="yearly">Yearly</button>
				</div>
			</div>
			<div class="rate-chart-card">
				<div class="row g-3 mb-3">
					<div class="col-md-4">
						<div class="rate-metric">
							<div class="label">Buying rate</div>
							<div class="value text-warning" id="rateBuyingValue">0.00</div>
							<small class="text-white-50">Change <span id="rateBuyingChange">0.00</span></small>
						</div>
					</div>
					<div class="col-md-4">
						<div class="rate-metric">
							<div class="label">Selling rate</div>
							<div class="value text-info" id="rateSellingValue">0.00</div>
							<small class="text-white-50">Change <span id="rateSellingChange">0.00</span></small>
						</div>
					</div>
					<div class="col-md-4">
						<div class="rate-metric">
							<div class="label">Last updated</div>
							<div class="value fs-6" id="rateLastUpdated">No history yet</div>
							<small class="text-white-50"><span id="ratePointCount">0</span> points - <span id="rateChangeCount">0</span> changes</small>
						</div>
					</div>
				</div>
				<div class="rate-range-panel mb-3">
					<div class="row g-2 align-items-end">
						<div class="col-md-3">
							<label class="form-label small text-white-50 mb-1" for="rateChartMode">View</label>
							<select class="form-select form-select-sm" id="rateChartMode">
								<option value="line">Rates line</option>
								<option value="ohlc">OHLC range</option>
								<option value="spread">Spread</option>
								<option value="volume">Volume</option>
							</select>
						</div>
						<div class="col-md-3">
							<label class="form-label small text-white-50 mb-1" for="rateInterval">Interval</label>
							<select class="form-select form-select-sm" id="rateInterval">
								<option value="auto">Auto</option>
								<option value="5m">5 min</option>
								<option value="15m">15 min</option>
								<option value="30m">30 min</option>
								<option value="1h">Hourly</option>
								<option value="1d">Daily</option>
								<option value="1w">Weekly</option>
							</select>
						</div>
						<div class="col-md-3">
							<label class="form-label small text-white-50 mb-1" for="rateSpreadAlert">Spread alert</label>
							<input type="number" step="0.01" min="0" class="form-control form-control-sm" id="rateSpreadAlert" placeholder="e.g. 1.00">
						</div>
						<div class="col-md-3">
							<label class="form-label small text-white-50 mb-1" for="rateMoveAlert">Move alert</label>
							<input type="number" step="0.01" min="0" class="form-control form-control-sm" id="rateMoveAlert" placeholder="e.g. 0.50">
						</div>
						<div class="col-12 d-flex flex-wrap gap-3">
							<label class="form-check form-check-inline mb-0">
								<input class="form-check-input" type="checkbox" id="showBuyingRate" checked>
								<span class="form-check-label small">Buying</span>
							</label>
							<label class="form-check form-check-inline mb-0">
								<input class="form-check-input" type="checkbox" id="showSellingRate" checked>
								<span class="form-check-label small">Selling</span>
							</label>
						</div>
						<div class="col-md-4">
							<label class="form-label small text-white-50 mb-1" for="rateStartDate">From</label>
							<input type="date" class="form-control form-control-sm" id="rateStartDate">
						</div>
						<div class="col-md-4">
							<label class="form-label small text-white-50 mb-1" for="rateEndDate">To</label>
							<input type="date" class="form-control form-control-sm" id="rateEndDate">
						</div>
						<div class="col-md-2">
							<label class="form-label small text-white-50 mb-1" for="rateStartTime">From time</label>
							<input type="time" class="form-control form-control-sm" id="rateStartTime">
						</div>
						<div class="col-md-2">
							<label class="form-label small text-white-50 mb-1" for="rateEndTime">To time</label>
							<input type="time" class="form-control form-control-sm" id="rateEndTime">
						</div>
						<div class="col-md-4 d-flex gap-2">
							<button type="button" class="btn btn-sm btn-warning flex-fill" id="applyRateRange"><i class="fa fa-filter me-1"></i> Apply</button>
							<button type="button" class="btn btn-sm btn-outline-danger" id="clearRateRange"><i class="fa fa-times"></i></button>
							<button type="button" class="btn btn-sm btn-outline-success" id="exportRateCsv" title="Export CSV"><i class="fa fa-file-csv"></i></button>
							<button type="button" class="btn btn-sm btn-outline-danger" id="exportRatePdf" title="Export PDF"><i class="fa fa-file-pdf"></i></button>
							<button type="button" class="btn btn-sm btn-outline-secondary" id="printRateChart" title="Print chart"><i class="fa fa-print"></i></button>
						</div>
					</div>
				</div>
				<div class="rate-mini-grid mb-3">
					<div class="rate-mini-stat"><div class="label">OHLC</div><div class="small">Open, high, low, and close rates inside each selected interval.</div></div>
					<div class="rate-mini-stat"><div class="label">Spread</div><div class="small">Selling rate minus buying rate, showing the exchange margin.</div></div>
					<div class="rate-mini-stat"><div class="label">Volume</div><div class="small">CFA and GHS transaction totals grouped by the selected interval.</div></div>
				</div>
				<div class="alert alert-warning rate-alert-line" id="rateAlertLine"></div>
				<div class="rate-mini-grid mb-3">
					<div class="rate-mini-stat"><div class="label">Buying high / low</div><div class="value"><span id="rateHighBuying">0.00</span> / <span id="rateLowBuying">0.00</span></div></div>
					<div class="rate-mini-stat"><div class="label">Selling high / low</div><div class="value"><span id="rateHighSelling">0.00</span> / <span id="rateLowSelling">0.00</span></div></div>
					<div class="rate-mini-stat"><div class="label">Average spread</div><div class="value" id="rateAverageSpread">0.00</div></div>
					<div class="rate-mini-stat"><div class="label">CFA volume</div><div class="value" id="rateCfaVolume">0.00</div></div>
					<div class="rate-mini-stat"><div class="label">GHS volume</div><div class="value" id="rateGhsVolume">0.00</div></div>
				</div>
				<div class="rate-chart-canvas-wrap">
					<canvas id="cfaRateChart"></canvas>
					<div class="rate-chart-empty" id="rateChartEmpty">No rate history found for this range.</div>
				</div>
			</div>
		</div>
	</div>

	<div class="tab-pane fade" id="cashdrawer" role="tabpanel" aria-labelledby="cashdrawer-tab">
		<div class="row g-3">
			<div class="col-md-6">
				<div class="card shadow border-0">
					<div class="card-body">
						<h5 class="card-title text-muted">GHS Cashdrawer</h5>
						<p class="display-6 fw-bold text-success">GHS <?=number_format($drawerBalanceGhs,2)?></p>
						<p class="text-muted small mb-0">Latest 10 denomination entries</p>
					</div>
				</div>
			</div>
			<div class="col-md-6">
				<div class="card shadow border-0">
					<div class="card-body">
						<h5 class="card-title text-muted">CFA Cashdrawer</h5>
						<p class="display-6 fw-bold text-primary">CFA <?=number_format($drawerBalanceCfa,2)?></p>
						<p class="text-muted small mb-0">Latest 10 denomination entries</p>
					</div>
				</div>
			</div>
		</div>

		<?php if(!$hasDrawer):?>
			<div class="alert alert-warning mt-3">No active cashdrawer found for this user. Open a cashdrawer from Operations &gt; Cashdrawer to start tracking CFA.</div>
		<?php endif;?>

		<div class="row g-3 mt-2">
			<div class="col-lg-6">
				<div class="card border-0 shadow-sm h-100">
					<div class="card-header">
						<h6 class="mb-0">GHS Movements</h6>
					</div>
					<div class="card-body p-0">
						<div class="table-responsive">
							<table class="table table-sm table-striped mb-0">
								<thead class="">
									<tr>
										<th>Note</th>
										<th>Count</th>
										<th>Volume</th>
										<th>Date</th>
									</tr>
								</thead>
								<tbody>
								<?php if(!empty($drawerHistoryGhs)):?>
									<?php foreach($drawerHistoryGhs as $history):?>
									<tr>
										<td>GHS <?=number_format($history->denomination ?? 0,2)?></td>
										<td><?=number_format($history->count ?? 0)?></td>
										<td><?=number_format($history->volume ?? 0,2)?></td>
										<td><?=get_date_time($history->last_date ?? $history->date_created ?? '')?></td>
									</tr>
									<?php endforeach;?>
								<?php else:?>
									<tr><td colspan="4" class="text-center text-muted">No entries yet.</td></tr>
								<?php endif;?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>
			<div class="col-lg-6">
				<div class="card border-0 shadow-sm h-100">
					<div class="card-header d-flex justify-content-between align-items-center">
						<h6 class="mb-0">CFA Movements</h6>
						<span class="badge bg-primary">FX</span>
					</div>
					<div class="card-body p-0">
						<div class="table-responsive">
							<table class="table table-sm table-striped mb-0">
								<thead class="">
									<tr>
										<th>Note</th>
										<th>Count</th>
										<th>Volume</th>
										<th>Date</th>
									</tr>
								</thead>
								<tbody>
								<?php if(!empty($drawerHistoryCfa)):?>
									<?php foreach($drawerHistoryCfa as $history):?>
									<tr>
										<td><?=number_format($history->denomination ?? 0,0)?> CFA</td>
										<td><?=number_format($history->count ?? 0)?></td>
										<td><?=number_format($history->volume ?? 0,2)?></td>
										<td><?=get_date_time($history->last_date ?? '')?></td>
									</tr>
									<?php endforeach;?>
								<?php else:?>
									<tr><td colspan="4" class="text-center text-muted">No entries yet.</td></tr>
								<?php endif;?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="card border-0 shadow-sm mt-4">
			<div class="card-header d-flex justify-content-between align-items-center">
				<h6 class="mb-0">Manual CFA Denomination Update</h6>
				<small>Add notes received directly to the cashdrawer.</small>
			</div>
			<div class="card-body">
				<form method="post" class="row g-3">
					<?=csrf()?>
					<input type="hidden" name="drawer_action" value="adjust_fx" />
					<input type="hidden" name="drawer_cfa_payload" id="drawerCfaPayload" />
					<input type="hidden" name="drawer_cfa_fallback" id="drawerCfaFallback" />
					<div class="col-12">
						<div class="table-responsive">
							<table class="table table-sm table-bordered align-middle mb-0">
								<thead class="">
									<tr>
										<th>Note</th>
										<th style="width:140px;">Count</th>
										<th>Volume</th>
									</tr>
								</thead>
								<tbody>
								<?php foreach($cfaDenominations as $denomination):?>
								<tr data-cfa-denomination="<?=$denomination?>">
									<td><?=number_format($denomination,0)?> CFA</td>
									<td><input type="number" min="0" class="form-control form-control-sm drawer-count" placeholder="0"></td>
									<td class="drawer-volume text-muted">0.00</td>
								</tr>
								<?php endforeach;?>
								</tbody>
							</table>
						</div>
					</div>
					<div class="col-md-6">
						<label class="form-label">Quick total override (optional)</label>
						<input type="number" step="0.01" class="form-control" placeholder="CFA amount" oninput="document.getElementById('drawerCfaFallback').value = this.value" />
						<small class="text-muted">If provided, this overrides the calculated volume.</small>
					</div>
					<div class="col-12 d-flex justify-content-end gap-2">
						<button type="button" class="btn btn-outline-secondary" id="drawerCfaReset">Reset</button>
						<button type="submit" class="btn btn-primary" <?= $hasDrawer ? '' : 'disabled'?> id="drawerCfaSubmit">
							<i class="fa-solid fa-vault me-1"></i> Sync Cashdrawer
						</button>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

    <!-- Scripts -->
	    <script src="<?=ROOT?>/plugins/dashboard/assets/chartjs/Chart.js"></script>
	    <script>

				function exportPdf() {
				    // Add loading state
				    const btn = document.querySelector('.export-pdf-btn');
				    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Generating...';
				    
				    // Open PDF in new tab
				    const pdfWindow = window.open(
				        `<?= ROOT ?>/admin/cfa/exportList_pdf`,
				        'pdf_window',
				        'width=800,height=600'
				    );
				    
				    // Reset button after 5 seconds
				    setTimeout(() => {
				        btn.innerHTML = '<i class="fa fa-file-pdf"></i> Export to PDF';
				    }, 8000);
				}
      document.addEventListener("DOMContentLoaded", function () {
        const DATA_ENDPOINT = `<?= ROOT ?>/<?= $admin_route ?>/<?= $plugin_route ?>/data`;
        const RATE_HISTORY_ENDPOINT = `<?= ROOT ?>/<?= $admin_route ?>/<?= $plugin_route ?>/rate-history`;
        const VIEW_BASE = `<?= ROOT ?>/<?= $admin_route ?>/<?= $plugin_route ?>`;
        const filterButtons = document.querySelectorAll('.js-filter-btn');
        const paginationNav = document.querySelector('#paginationNav .pagination');
        const totalPagesText = document.getElementById('totalPagesText');
        const tableBody = document.getElementById('activityTable');
        const tableTotalCfa = document.getElementById('tableTotalCfa');
        const tableTotalGhs = document.getElementById('tableTotalGhs');
        const tableTotalInCfa = document.getElementById('tableTotalInCfa');
        const tableTotalInGhs = document.getElementById('tableTotalInGhs');
        const tableTotalOutCfa = document.getElementById('tableTotalOutCfa');
        const tableTotalOutGhs = document.getElementById('tableTotalOutGhs');
        const heroCfaVolume = document.getElementById('heroCfaVolume');
        const heroGhsVolume = document.getElementById('heroGhsVolume');
        const heroAvgTicket = document.getElementById('heroAvgTicket');
        const heroTxCount = document.getElementById('heroTxCount');
        const heroLatestActivity = document.getElementById('heroLatestActivity');
        const startDateInput = document.getElementById('filterStartDate');
        const endDateInput = document.getElementById('filterEndDate');
        const clearDateFiltersBtn = document.getElementById('clearDateFilters');
        const toggleDateFiltersBtn = document.getElementById('toggleDateFilters');
        const dateRangePanel = document.getElementById('dateRangePanel');
        const dateFilterActiveDot = document.getElementById('dateFilterActiveDot');
        const dateRangeSummary = document.getElementById('dateRangeSummary');
        const rateRangeButtons = document.querySelectorAll('.js-rate-range');
        const rateStartDate = document.getElementById('rateStartDate');
        const rateEndDate = document.getElementById('rateEndDate');
        const rateStartTime = document.getElementById('rateStartTime');
        const rateEndTime = document.getElementById('rateEndTime');
        const rateChartMode = document.getElementById('rateChartMode');
        const rateInterval = document.getElementById('rateInterval');
        const rateSpreadAlert = document.getElementById('rateSpreadAlert');
        const rateMoveAlert = document.getElementById('rateMoveAlert');
        const showBuyingRate = document.getElementById('showBuyingRate');
        const showSellingRate = document.getElementById('showSellingRate');
        const exportRateCsvBtn = document.getElementById('exportRateCsv');
        const exportRatePdfBtn = document.getElementById('exportRatePdf');
        const printRateChartBtn = document.getElementById('printRateChart');
        const applyRateRangeBtn = document.getElementById('applyRateRange');
        const clearRateRangeBtn = document.getElementById('clearRateRange');
        const rateBuyingValue = document.getElementById('rateBuyingValue');
        const rateSellingValue = document.getElementById('rateSellingValue');
        const rateBuyingChange = document.getElementById('rateBuyingChange');
        const rateSellingChange = document.getElementById('rateSellingChange');
        const rateLastUpdated = document.getElementById('rateLastUpdated');
        const ratePointCount = document.getElementById('ratePointCount');
        const rateChangeCount = document.getElementById('rateChangeCount');
        const rateChartEmpty = document.getElementById('rateChartEmpty');
        const rateChartCanvas = document.getElementById('cfaRateChart');
        const rateAlertLine = document.getElementById('rateAlertLine');
        const rateHighBuying = document.getElementById('rateHighBuying');
        const rateLowBuying = document.getElementById('rateLowBuying');
        const rateHighSelling = document.getElementById('rateHighSelling');
        const rateLowSelling = document.getElementById('rateLowSelling');
        const rateAverageSpread = document.getElementById('rateAverageSpread');
        const rateCfaVolume = document.getElementById('rateCfaVolume');
        const rateGhsVolume = document.getElementById('rateGhsVolume');
        const tabs = document.querySelectorAll('#cfaTabNav button[data-bs-toggle="tab"]');
        const CAN_EDIT = <?= user_can('edit_cfa') ? 'true' : 'false' ?>;
        const CAN_DELETE = <?= user_can('delete_cfa') ? 'true' : 'false' ?>;
        const CURRENT_USER_ID = <?= $currentUserId ?>;

        const state = {
          page: 1,
          limit: 10,
          type: '<?= $initialTypeFilter ?>',
          date_from: '',
          date_to: ''
        };

        const rateState = {
          range: 'daily',
          date_from: '',
          date_to: '',
          time_from: '',
          time_to: '',
          interval: 'auto',
          mode: 'line',
          showBuying: true,
          showSelling: true
        };
        let latestRatePayload = null;
        let rateChart = null;
        let ratePollingTimer = null;

        if(typeof bootstrap !== 'undefined' && bootstrap.Popover){
          document.querySelectorAll('[data-bs-toggle="popover"]').forEach(el => {
            try { new bootstrap.Popover(el); } catch (e) {}
          });
        }

        function escapeHtml(value){
          return String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char] || char));
        }

        function formatNumber(num){
          return Number(num || 0).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
        }

        function formatDate(value){
          if(!value) return 'N/A';
          const date = new Date(String(value).replace(' ', 'T'));
          if(Number.isNaN(date.getTime())) return String(value);
          return date.toLocaleString(undefined, {
            year: 'numeric',
            month: 'short',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
          });
        }

        function setLoading(){
          if(!tableBody) return;
          tableBody.innerHTML = '<tr><td colspan="11" class="text-center text-muted py-4">Loading transactions...</td></tr>';
          if(totalPagesText){
            totalPagesText.textContent = 'Loading transactions...';
          }
        }

        function reasonHtml(reason) {
            if (!reason) return '';
            return `<small class="text-danger d-block mt-1"><i class="fa-solid fa-triangle-exclamation me-1"></i>${escapeHtml(reason)}</small>`;
        }
        
        // Map transaction types to direction badges and icons
        function getTypeMeta(typeKind) {
            const kind = String(typeKind || '').toLowerCase();
            if (kind === 'buys' || kind === 'buy') {
                return { badgeClass: 'bg-success text-light', badgeIcon: 'fa-arrow-down', badgeLabel: 'BUY' };
            }
            if (kind === 'sells' || kind === 'sell') {
                return { badgeClass: 'bg-danger text-light', badgeIcon: 'fa-arrow-up', badgeLabel: 'SELL' };
            }
            return { badgeClass: 'bg-secondary text-white', badgeIcon: 'fa-circle', badgeLabel: 'N/A' };
        }
        
        // Map transaction statuses to badge styles
        function getStatusMeta(status) {
            const s = String(status || '').toLowerCase();
            if (s === 'approved' || s === 'completed') {
                return { badgeClass: 'bg-success-subtle text-success border border-success-subtle', badgeIcon: 'fa-circle-check' };
            }
            if (s === 'rejected' || s === 'cancelled') {
                return { badgeClass: 'bg-danger-subtle text-danger border border-danger-subtle', badgeIcon: 'fa-circle-xmark' };
            }
            return { badgeClass: 'bg-warning-subtle text-warning border border-warning-subtle', badgeIcon: 'fa-clock' };
        }

        function syncActiveFilterButtons(){
          filterButtons.forEach(btn => {
            btn.classList.toggle('active', (btn.dataset.filter || 'all') === state.type);
          });
        }

        function isDateFilterActive(){
          return Boolean(state.date_from || state.date_to);
        }

        function updateDateFilterIndicator(){
          if(dateFilterActiveDot){
            dateFilterActiveDot.classList.toggle('d-none', !isDateFilterActive());
          }
        }

        function updateDateRangeSummary(){
          if(!dateRangeSummary) return;
          if(!isDateFilterActive()){
            dateRangeSummary.textContent = 'Showing: All dates';
            return;
          }
          const startLabel = state.date_from ? new Date(state.date_from + 'T00:00:00').toLocaleDateString(undefined,{year:'numeric', month:'short', day:'2-digit'}) : 'Earliest';
          const endLabel = state.date_to ? new Date(state.date_to + 'T00:00:00').toLocaleDateString(undefined,{year:'numeric', month:'short', day:'2-digit'}) : 'Latest';
          dateRangeSummary.textContent = `Showing: ${startLabel} -> ${endLabel}`;
        }

        function buildQuery(){
          const params = new URLSearchParams({
            page: String(state.page),
            limit: String(state.limit),
            type: state.type
          });
          if(state.date_from){ params.append('date_from', state.date_from); }
          if(state.date_to){ params.append('date_to', state.date_to); }
          return params.toString();
        }

        function renderRows(rows, pagination){
          if(!tableBody) return;
          if(!rows.length){
            tableBody.innerHTML = '<tr><td colspan="11" class="text-center text-muted py-4">No records to show!</td></tr>';
            return;
          }
          tableBody.innerHTML = rows.map((row, index) => {
            const status = String(row.transaction_status || '').toLowerCase();
            const typeMeta = getTypeMeta(row.type_kind);
            const statusMeta = getStatusMeta(row.transaction_status);
            const viewUrl = `${VIEW_BASE}/view/${row.id}`;
            const editUrl = `${VIEW_BASE}/edit/${row.id}`;
            const deleteUrl = `${VIEW_BASE}/delete/${row.id}`;
            const rowNumber = ((pagination.page - 1) * pagination.limit) + index + 1;
            const canEdit = status !== 'rejected' && (CAN_EDIT || Number(row.created_by || 0) === CURRENT_USER_ID);
            const canDelete = ['draft', 'pending'].includes(status) && (CAN_DELETE || Number(row.created_by || 0) === CURRENT_USER_ID);
            const actionButtons = [`<a class="btn btn-sm btn-outline-warning" href="${viewUrl}" title="View"><i class="fa fa-eye"></i></a>`];
            if(canEdit){ actionButtons.push(`<a class="btn btn-sm btn-outline-primary" href="${editUrl}" title="Update"><i class="fa fa-pen"></i></a>`); }
            if(canDelete){ actionButtons.push(`<a class="btn btn-sm btn-outline-danger" href="${deleteUrl}" title="Delete"><i class="fa fa-trash"></i></a>`); }
            const statusLabel = status ? status.charAt(0).toUpperCase() + status.slice(1) : 'Unknown';
            const customerPhone = row.customer_phone ? escapeHtml(row.customer_phone) : '<span class="text-muted">N/A</span>';

            return `
              <tr>
                <td class="d-none d-lg-table-cell"><span class="badge bg-warning text-dark rounded-circle">${rowNumber}</span></td>
                <td class="d-none d-lg-table-cell"><a class="text-decoration-none table-link" href="${viewUrl}">${escapeHtml(formatDate(row.date_created))}</a></td>
                <td class="wrap table-desc d-none d-lg-table-cell">
                  <div class="fw-semibold"><i class="fa-solid fa-phone me-1 small"></i>${customerPhone}</div>
                  <small class="d-block">${escapeHtml(row.description || 'No description')}</small>
                </td>
                <td class="d-none d-lg-table-cell">
                  <span class="status-pill ${typeMeta.badgeClass} mb-1 d-inline-block">
                    <i class="fa-solid ${typeMeta.badgeIcon} me-1"></i>${typeMeta.badgeLabel}
                  </span><br>
                  <span class="status-pill ${statusMeta.badgeClass}">
                    <i class="fa-solid ${statusMeta.badgeIcon} me-1" title="${escapeHtml(row.transaction_status || '')}"></i>${escapeHtml(statusLabel)}
                  </span>
                  ${reasonHtml(row.rejection_reason)}
                </td>
                <td class="d-lg-none col-4 cfa-details-col">
                  <div class="fw-bold"><a href="${viewUrl}">${escapeHtml(formatDate(row.date_created))}</a></div>
                  <div><i class="fa-solid fa-phone me-1 text-muted small"></i>${customerPhone}</div>
                  <small class="text-muted d-block text-truncate">${escapeHtml(row.description || '')}</small>
                  <div class="mt-1">
                    <span class="status-pill ${typeMeta.badgeClass}"><i class="fa-solid ${typeMeta.badgeIcon} me-1"></i>${typeMeta.badgeLabel}</span>
                    <span class="status-pill ${statusMeta.badgeClass}">${escapeHtml(statusLabel)}</span>
                  </div>
                </td>
                <td class="text-end fw-semibold d-none d-lg-table-cell">
                    <div>IN: <code class="text-muted me-1">${escapeHtml(row.amount_in_label || '')}</code><span class="text-info">${formatNumber(row.amount_in_value)}</span></div>
                    <div>
                        OUT: <code class="text-muted me-1">${escapeHtml(row.amount_out_label || '')}</code>
                        <span class="text-warning">${formatNumber(row.amount_out_value)}</span>
                    </div>
                </td>
                <td class="text-end d-lg-none"><div class="cfa-amount-stack"><div><small class="text-muted d-block">Amount In</small><span class="fw-semibold text-info"><code>${escapeHtml(row.amount_in_label || '')}</code> ${formatNumber(row.amount_in_value)}</span></div><div><small class="text-muted d-block">Amount Out</small><span class="fw-semibold text-warning"><code>${escapeHtml(row.amount_out_label || '')}</code> ${formatNumber(row.amount_out_value)}</span></div></div></td>
                <td class="d-none d-lg-table-cell"><div class="creator-name">${escapeHtml(row.user?.name || 'Unknown User')}</div></td>
                <td class="text-end"><div class="d-inline-flex flex-wrap gap-1 justify-content-end">${actionButtons.join('')}</div></td>
              </tr>`;
          }).join('');
        }

        function renderTotals(totals, latestActivity){
          if(tableTotalCfa){ tableTotalCfa.textContent = formatNumber(totals.total_amount_cfa); }
          if(tableTotalGhs){ tableTotalGhs.textContent = formatNumber(totals.total_amount_ghs); }
          if(tableTotalInCfa){ tableTotalInCfa.textContent = formatNumber(totals.total_in_cfa); }
          if(tableTotalInGhs){ tableTotalInGhs.textContent = formatNumber(totals.total_in_ghs); }
          if(tableTotalOutCfa){ tableTotalOutCfa.textContent = formatNumber(totals.total_out_cfa); }
          if(tableTotalOutGhs){ tableTotalOutGhs.textContent = formatNumber(totals.total_out_ghs); }
          if(heroCfaVolume){ heroCfaVolume.textContent = `${formatNumber(totals.total_amount_cfa)} CFA`; }
          if(heroGhsVolume){ heroGhsVolume.textContent = `GHS ${formatNumber(totals.total_amount_ghs)}`; }
          if(heroAvgTicket){ heroAvgTicket.textContent = formatNumber(totals.avg_ticket_ghs); }
          if(heroTxCount){ heroTxCount.textContent = String(totals.count || 0); }
          if(heroLatestActivity){ heroLatestActivity.textContent = latestActivity ? `Last updated ${formatDate(latestActivity)}` : 'No activity yet'; }
        }

        function getPaginationRange(current, total){
          const range = [];
          if(total <= 7){
            for(let i = 1; i <= total; i++) range.push(i);
            return range;
          }
          range.push(1);
          const start = Math.max(2, current - 1);
          const end = Math.min(total - 1, current + 1);
          if(start > 2){ range.push('ellipsis'); }
          for(let i = start; i <= end; i++) range.push(i);
          if(end < total - 1){ range.push('ellipsis'); }
          range.push(total);
          return range;
        }

        function renderPagination(pagination){
          if(!paginationNav) return;
          const totalPages = Math.max(1, Number(pagination.total_pages || 1));
          const currentPage = Math.max(1, Number(pagination.page || 1));
          const totalRows = Number(pagination.total_rows || 0);
          if(totalPagesText){ totalPagesText.textContent = `Page ${currentPage} of ${totalPages} • ${totalRows} transactions`; }
          const items = [];
          items.push(`<li class="page-item ${currentPage === 1 ? 'disabled' : ''}"><button type="button" class="page-link" data-page="${currentPage - 1}" ${currentPage === 1 ? 'disabled' : ''}>&laquo;</button></li>`);
          getPaginationRange(currentPage, totalPages).forEach(item => {
            if(item === 'ellipsis'){
              items.push('<li class="page-item disabled"><span class="page-link">...</span></li>');
            }else{
              items.push(`<li class="page-item ${item === currentPage ? 'active' : ''}"><button type="button" class="page-link" data-page="${item}">${item}</button></li>`);
            }
          });
          items.push(`<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}"><button type="button" class="page-link" data-page="${currentPage + 1}" ${currentPage === totalPages ? 'disabled' : ''}>&raquo;</button></li>`);
          paginationNav.innerHTML = items.join('');
        }

        async function fetchData(){
          setLoading();
          try {
            const response = await fetch(`${DATA_ENDPOINT}?${buildQuery()}`, { headers: { Accept: 'application/json' } });
            if(!response.ok){ throw new Error(`HTTP ${response.status}`); }
            const payload = await response.json();
            if(!payload || payload.success === false){ throw new Error(payload?.message || 'Unable to load CFA transactions.'); }
            renderRows(payload.rows || [], payload.pagination || { page: state.page, limit: state.limit });
            renderTotals(payload.totals || {}, payload.latest_activity || null);
            renderPagination(payload.pagination || { page: state.page, total_pages: 1, total_rows: 0 });
            syncActiveFilterButtons();
            updateDateFilterIndicator();
            updateDateRangeSummary();
          } catch (error) {
            console.error('CFA /data exception', error);
            if(tableBody){ tableBody.innerHTML = '<tr><td colspan="11" class="text-center text-danger py-4">Unable to load transactions right now.</td></tr>'; }
            if(totalPagesText){ totalPagesText.textContent = 'Unable to load pagination.'; }
          }
        }

        function buildRateQuery(){
          const params = new URLSearchParams({ range: rateState.range });
          if(rateState.date_from){ params.append('date_from', rateState.date_from); }
          if(rateState.date_to){ params.append('date_to', rateState.date_to); }
          if(rateState.time_from){ params.append('time_from', rateState.time_from); }
          if(rateState.time_to){ params.append('time_to', rateState.time_to); }
          if(rateState.interval){ params.append('interval', rateState.interval); }
          return params.toString();
        }

        function syncRateRangeButtons(){
          rateRangeButtons.forEach(btn => {
            btn.classList.toggle('active', (btn.dataset.range || 'daily') === rateState.range);
          });
        }

        function formatRateChange(value){
          const number = Number(value || 0);
          const prefix = number > 0 ? '+' : '';
          return `${prefix}${number.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}`;
        }

        function updateRateMetrics(summary){
          if(rateBuyingValue){ rateBuyingValue.textContent = formatNumber(summary.latest_buying_rate || 0); }
          if(rateSellingValue){ rateSellingValue.textContent = formatNumber(summary.latest_selling_rate || 0); }
          if(rateBuyingChange){ rateBuyingChange.textContent = formatRateChange(summary.buying_change || 0); }
          if(rateSellingChange){ rateSellingChange.textContent = formatRateChange(summary.selling_change || 0); }
          if(rateLastUpdated){ rateLastUpdated.textContent = summary.last_updated ? formatDate(summary.last_updated) : 'No history yet'; }
          if(ratePointCount){ ratePointCount.textContent = String(summary.count || 0); }
          if(rateChangeCount){ rateChangeCount.textContent = String(summary.rate_changes || 0); }
          if(rateHighBuying){ rateHighBuying.textContent = formatNumber(summary.highest_buying_rate || 0); }
          if(rateLowBuying){ rateLowBuying.textContent = formatNumber(summary.lowest_buying_rate || 0); }
          if(rateHighSelling){ rateHighSelling.textContent = formatNumber(summary.highest_selling_rate || 0); }
          if(rateLowSelling){ rateLowSelling.textContent = formatNumber(summary.lowest_selling_rate || 0); }
          if(rateAverageSpread){ rateAverageSpread.textContent = formatNumber(summary.average_spread || 0); }
          if(rateCfaVolume){ rateCfaVolume.textContent = formatNumber(summary.total_cfa_volume || 0); }
          if(rateGhsVolume){ rateGhsVolume.textContent = formatNumber(summary.total_ghs_volume || 0); }
          renderRateAlerts(summary);
        }

        function renderRateAlerts(summary){
          if(!rateAlertLine) return;
          const spreadThreshold = Number(rateSpreadAlert?.value || 0);
          const moveThreshold = Number(rateMoveAlert?.value || 0);
          const messages = [];
          if(spreadThreshold > 0 && Number(summary.highest_spread || 0) >= spreadThreshold){
            messages.push(`Spread reached ${formatNumber(summary.highest_spread)}.`);
          }
          if(moveThreshold > 0 && Math.max(Math.abs(Number(summary.buying_change || 0)), Math.abs(Number(summary.selling_change || 0))) >= moveThreshold){
            messages.push(`Rate moved by at least ${formatNumber(moveThreshold)} in this range.`);
          }
          rateAlertLine.textContent = messages.join(' ');
          rateAlertLine.classList.toggle('show', messages.length > 0);
        }

        function makeDataset(label, data, color, extra = {}){
          return Object.assign({
            label,
            data,
            borderColor: color,
            backgroundColor: color,
            pointBackgroundColor: color,
            pointBorderColor: extra.pointBorderColor || '#ffffff',
            borderWidth: 2,
            tension: 0.35
          }, extra);
        }

        function buildRateDatasets(points, ohlc, theme){
          const mode = rateState.mode || 'line';
          const showBuying = rateState.showBuying !== false;
          const showSelling = rateState.showSelling !== false;
          if(mode === 'spread'){
            return {
              labels: points.map(point => point.label || formatDate(point.date_created)),
              datasets: [makeDataset('Spread', points.map(point => Number(point.spread || 0)), '#8b5cf6', {
                pointRadius: points.length < 40 ? 4 : 0,
                fill: false,
                pointBorderColor: theme.pointBorder
              })]
            };
          }
          if(mode === 'volume'){
            return {
              labels: ohlc.map(row => row.label),
              datasets: [
                makeDataset('CFA volume', ohlc.map(row => Number(row.volume_cfa || 0)), '#fbbf24', { type: 'bar', yAxisID: 'yVolume', borderWidth: 1 }),
                makeDataset('GHS volume', ohlc.map(row => Number(row.volume_ghs || 0)), '#38bdf8', { type: 'bar', yAxisID: 'yVolume', borderWidth: 1 })
              ]
            };
          }
          if(mode === 'ohlc'){
            const datasets = [];
            if(showBuying){
              datasets.push(
                makeDataset('Buying low-high', ohlc.map(row => [Number(row.low_buying || 0), Number(row.high_buying || 0)]), '#fbbf24', { type: 'bar', borderWidth: 1, borderSkipped: false, barPercentage: 0.45 }),
                makeDataset('Buying close', ohlc.map(row => Number(row.close_buying || 0)), '#f59e0b', { fill: false, pointRadius: 3, pointBorderColor: theme.pointBorder })
              );
            }
            if(showSelling){
              datasets.push(
                makeDataset('Selling low-high', ohlc.map(row => [Number(row.low_selling || 0), Number(row.high_selling || 0)]), '#38bdf8', { type: 'bar', borderWidth: 1, borderSkipped: false, barPercentage: 0.45 }),
                makeDataset('Selling close', ohlc.map(row => Number(row.close_selling || 0)), '#0284c7', { fill: false, pointRadius: 3, pointBorderColor: theme.pointBorder })
              );
            }
            return {
              labels: ohlc.map(row => row.label),
              datasets
            };
          }
          const changedAt = points.map((point, index) => index > 0 && (Number(point.buying_rate || 0) !== Number(points[index - 1].buying_rate || 0) || Number(point.selling_rate || 0) !== Number(points[index - 1].selling_rate || 0)));
          const datasets = [];
          if(showBuying){
            datasets.push(makeDataset('Buying rate', points.map(point => Number(point.buying_rate || 0)), '#fbbf24', { pointRadius: ctx => changedAt[ctx.dataIndex] ? 6 : (points.length < 40 ? 4 : 0), fill: false, pointBorderColor: theme.pointBorder }));
          }
          if(showSelling){
            datasets.push(makeDataset('Selling rate', points.map(point => Number(point.selling_rate || 0)), '#38bdf8', { pointRadius: ctx => changedAt[ctx.dataIndex] ? 6 : (points.length < 40 ? 4 : 0), fill: false, pointBorderColor: theme.pointBorder }));
          }
          datasets.push(makeDataset('Spread', points.map(point => Number(point.spread || 0)), '#8b5cf6', { pointRadius: 0, borderDash: [6, 4], fill: false, hidden: true, pointBorderColor: theme.pointBorder }));
          return {
            labels: points.map(point => point.label || formatDate(point.date_created)),
            datasets
          };
        }

        function renderRateChart(points, ohlc = []){
          if(!rateChartCanvas || typeof Chart === 'undefined') return;
          points = Array.isArray(points) ? points : [];
          ohlc = Array.isArray(ohlc) ? ohlc : [];
          const hasPoints = points.length > 0;
          if(rateChartEmpty){ rateChartEmpty.classList.toggle('show', !hasPoints); }
          const ctx = rateChartCanvas.getContext('2d');
          const themeHost = document.documentElement.getAttribute('data-bs-theme') || document.body.getAttribute('data-bs-theme') || 'light';
          const isLightTheme = themeHost === 'light';
          const theme = {
            text: isLightTheme ? '#475569' : '#94a3b8',
            legend: isLightTheme ? '#334155' : '#e2e8f0',
            grid: isLightTheme ? 'rgba(15,23,42,0.08)' : 'rgba(148,163,184,0.1)',
            pointBorder: isLightTheme ? '#ffffff' : '#0f172a',
            tooltipBg: isLightTheme ? 'rgba(255,255,255,0.98)' : 'rgba(15,23,42,0.95)',
            tooltipText: isLightTheme ? '#0f172a' : '#f8fafc'
          };
          const chartData = buildRateDatasets(points, ohlc, theme);
          const showVolumeAxis = (rateState.mode || 'line') === 'volume';
          const chartOptions = {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
              legend: { labels: { color: theme.legend, boxWidth: 12 } },
              tooltip: {
                mode: 'index',
                intersect: false,
                backgroundColor: theme.tooltipBg,
                titleColor: theme.tooltipText,
                bodyColor: theme.tooltipText,
                borderColor: 'rgba(148,163,184,0.25)',
                borderWidth: 1,
                callbacks: {
                  afterBody: function(items){
                    if((rateState.mode || 'line') !== 'line' || !items.length) return '';
                    const point = points[items[0].dataIndex] || {};
                    return [`CFA volume: ${formatNumber(point.amount_cfa || 0)}`, `GHS volume: ${formatNumber(point.amount_ghs || 0)}`, point.cfa_id ? `Transaction #${point.cfa_id}` : ''];
                  },
                  label: function(context){
                    const raw = context.raw;
                    if(Array.isArray(raw)) return `${context.dataset.label}: ${formatNumber(raw[0])} - ${formatNumber(raw[1])}`;
                    return `${context.dataset.label}: ${formatNumber(context.parsed.y)}`;
                  }
                }
              }
            },
            scales: {
              x: { ticks: { color: theme.text, maxRotation: 0, autoSkip: true }, grid: { color: theme.grid } },
              y: { ticks: { color: theme.text, callback: function(value){ return formatNumber(value); } }, grid: { color: theme.grid } },
              yVolume: { display: showVolumeAxis, position: 'right', ticks: { color: theme.text, callback: function(value){ return formatNumber(value); } }, grid: { drawOnChartArea: false } }
            }
          };

          if(rateChart){
            rateChart.data = chartData;
            rateChart.options = chartOptions;
            rateChart.update(0);
            return;
          }
          rateChart = new Chart(ctx, { type: 'line', data: chartData, options: chartOptions });
        }
        async function fetchRateHistory(){
          try {
            const response = await fetch(`${RATE_HISTORY_ENDPOINT}?${buildRateQuery()}`, { headers: { Accept: 'application/json' } });
            if(!response.ok){ throw new Error(`HTTP ${response.status}`); }
            const payload = await response.json();
            if(!payload || payload.success === false){ throw new Error(payload?.message || 'Unable to load CFA rates.'); }
            latestRatePayload = payload;
            updateRateMetrics(payload.summary || {});
            renderRateChart(payload.points || [], payload.ohlc || []);
            syncRateRangeButtons();
          } catch (error) {
            console.error('CFA /rate-history exception', error);
            if(rateChartEmpty){
              rateChartEmpty.textContent = 'Unable to load rate history right now.';
              rateChartEmpty.classList.add('show');
            }
          }
        }

        function startRatePolling(){
          if(ratePollingTimer) return;
          fetchRateHistory();
          ratePollingTimer = window.setInterval(fetchRateHistory, 15000);
        }

        function stopRatePolling(){
          if(ratePollingTimer){
            window.clearInterval(ratePollingTimer);
            ratePollingTimer = null;
          }
        }

        function exportRateCsv(){
          const points = latestRatePayload?.points || [];
          if(!points.length) return;
          const headers = ['date_created','customer_phone','buying_rate','selling_rate','spread','amount_cfa','amount_ghs','cfa_id','changed_by'];
          const lines = [headers.join(',')].concat(points.map(point => headers.map(key => `"${String(point[key] ?? '').replace(/"/g, '""')}"`).join(',')));
          const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
          const link = document.createElement('a');
          link.href = URL.createObjectURL(blob);
          link.download = `cfa-rate-history-${new Date().toISOString().slice(0,10)}.csv`;
          document.body.appendChild(link);
          link.click();
          link.remove();
          URL.revokeObjectURL(link.href);
        }

        function buildRateReportHtml(includeTable = true){
          const payload = latestRatePayload || {};
          const summary = payload.summary || {};
          const points = payload.points || [];
          const chartImage = rateChartCanvas ? rateChartCanvas.toDataURL('image/png') : '';
          const rows = points.map(point => `
            <tr>
              <td>${escapeHtml(formatDate(point.date_created))}</td>
              <td>${escapeHtml(point.customer_phone || '')}</td>
              <td>${formatNumber(point.buying_rate)}</td>
              <td>${formatNumber(point.selling_rate)}</td>
              <td>${formatNumber(point.spread)}</td>
              <td>${formatNumber(point.amount_cfa)}</td>
              <td>${formatNumber(point.amount_ghs)}</td>
              <td>${escapeHtml(point.cfa_id || '')}</td>
            </tr>
          `).join('');
          return `<!doctype html>
            <html>
              <head>
                <title>CFA Rate Chart Export</title>
                <style>
                  body{font-family:Arial,sans-serif;color:#0f172a;margin:24px;}
                  h1{font-size:20px;margin:0 0 4px;}
                  .muted{color:#64748b;font-size:12px;}
                  .grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin:16px 0;}
                  .card{border:1px solid #cbd5e1;border-radius:8px;padding:10px;}
                  .label{font-size:10px;text-transform:uppercase;color:#64748b;}
                  .value{font-weight:700;font-size:16px;}
                  img{width:100%;max-height:430px;object-fit:contain;border:1px solid #e2e8f0;border-radius:8px;}
                  table{width:100%;border-collapse:collapse;margin-top:18px;font-size:11px;}
                  th,td{border:1px solid #e2e8f0;padding:6px;text-align:right;}
                  th:first-child,td:first-child{text-align:left;}
                  th{background:#f8fafc;}
                  @media print{button{display:none;} body{margin:12px;} }
                </style>
              </head>
              <body>
                <button onclick="window.print()">Print / Save PDF</button>
                <h1>CFA Rate Movement</h1>
                <div class="muted">Range: ${escapeHtml(payload.date_from || '')} to ${escapeHtml(payload.date_to || '')} | View: ${escapeHtml(rateState.mode || 'line')} | Interval: ${escapeHtml(payload.interval || rateState.interval || 'auto')}</div>
                <div class="grid">
                  <div class="card"><div class="label">Latest buying</div><div class="value">${formatNumber(summary.latest_buying_rate)}</div></div>
                  <div class="card"><div class="label">Latest selling</div><div class="value">${formatNumber(summary.latest_selling_rate)}</div></div>
                  <div class="card"><div class="label">Average spread</div><div class="value">${formatNumber(summary.average_spread)}</div></div>
                  <div class="card"><div class="label">Rate changes</div><div class="value">${summary.rate_changes || 0}</div></div>
                </div>
                ${chartImage ? `<img src="${chartImage}" alt="CFA rate chart">` : ''}
                ${includeTable ? `<table><thead><tr><th>Date</th><th>Phone</th><th>Buying</th><th>Selling</th><th>Spread</th><th>CFA volume</th><th>GHS volume</th><th>Transaction</th></tr></thead><tbody>${rows}</tbody></table>` : ''}
              </body>
            </html>`;
        }

        function openRateReport(includeTable = true){
          const reportWindow = window.open('', 'cfa_rate_report', 'width=1100,height=800');
          if(!reportWindow) return;
          reportWindow.document.open();
          reportWindow.document.write(buildRateReportHtml(includeTable));
          reportWindow.document.close();
          reportWindow.focus();
        }

        function exportRatePdf(){
          openRateReport(true);
        }

        function printRateChart(){
          openRateReport(false);
        }

        if(startDateInput){
          startDateInput.addEventListener('change', () => {
            state.date_from = startDateInput.value || '';
            state.page = 1;
            fetchData();
          });
        }

        if(endDateInput){
          endDateInput.addEventListener('change', () => {
            state.date_to = endDateInput.value || '';
            state.page = 1;
            fetchData();
          });
        }

        if(clearDateFiltersBtn){
          clearDateFiltersBtn.addEventListener('click', () => {
            state.date_from = '';
            state.date_to = '';
            if(startDateInput) startDateInput.value = '';
            if(endDateInput) endDateInput.value = '';
            state.page = 1;
            fetchData();
          });
        }

        if(toggleDateFiltersBtn){
          toggleDateFiltersBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            dateRangePanel?.classList.toggle('show');
          });
        }

        document.addEventListener('click', (event) => {
          if(!dateRangePanel || !toggleDateFiltersBtn) return;
          if(!dateRangePanel.contains(event.target) && !toggleDateFiltersBtn.contains(event.target)){
            dateRangePanel.classList.remove('show');
          }
        });

        filterButtons.forEach(button => {
          button.addEventListener('click', () => {
            state.type = button.dataset.filter || 'all';
            state.page = 1;
            fetchData();
          });
        });

        if(paginationNav){
          paginationNav.addEventListener('click', (event) => {
            const button = event.target.closest('button[data-page]');
            if(!button || button.disabled) return;
            const nextPage = parseInt(button.getAttribute('data-page') || '1', 10);
            if(Number.isNaN(nextPage) || nextPage < 1) return;
            state.page = nextPage;
            fetchData();
          });
        }

        if(rateChartMode){
          rateChartMode.addEventListener('change', () => {
            rateState.mode = rateChartMode.value || 'line';
            renderRateChart(latestRatePayload?.points || [], latestRatePayload?.ohlc || []);
          });
        }

        if(rateInterval){
          rateInterval.addEventListener('change', () => {
            rateState.interval = rateInterval.value || 'auto';
            fetchRateHistory();
          });
        }

        [showBuyingRate, showSellingRate].forEach(input => {
          if(!input) return;
          input.addEventListener('change', () => {
            rateState.showBuying = showBuyingRate ? showBuyingRate.checked : true;
            rateState.showSelling = showSellingRate ? showSellingRate.checked : true;
            renderRateChart(latestRatePayload?.points || [], latestRatePayload?.ohlc || []);
          });
        });

        [rateSpreadAlert, rateMoveAlert].forEach(input => {
          if(!input) return;
          input.value = localStorage.getItem(input.id) || '';
          input.addEventListener('change', () => {
            localStorage.setItem(input.id, input.value || '');
            renderRateAlerts(latestRatePayload?.summary || {});
          });
        });

        if(exportRateCsvBtn){
          exportRateCsvBtn.addEventListener('click', exportRateCsv);
        }

        if(exportRatePdfBtn){
          exportRatePdfBtn.addEventListener('click', exportRatePdf);
        }

        if(printRateChartBtn){
          printRateChartBtn.addEventListener('click', printRateChart);
        }

        rateRangeButtons.forEach(button => {
          button.addEventListener('click', () => {
            rateState.range = button.dataset.range || 'daily';
            rateState.date_from = '';
            rateState.date_to = '';
            rateState.time_from = '';
            rateState.time_to = '';
            rateState.interval = rateInterval?.value || 'auto';
            if(rateStartDate) rateStartDate.value = '';
            if(rateEndDate) rateEndDate.value = '';
            if(rateStartTime) rateStartTime.value = '';
            if(rateEndTime) rateEndTime.value = '';
            fetchRateHistory();
          });
        });

        if(applyRateRangeBtn){
          applyRateRangeBtn.addEventListener('click', () => {
            rateState.range = 'custom';
            rateState.date_from = rateStartDate?.value || '';
            rateState.date_to = rateEndDate?.value || '';
            rateState.time_from = rateStartTime?.value || '';
            rateState.time_to = rateEndTime?.value || '';
            rateState.interval = rateInterval?.value || 'auto';
            fetchRateHistory();
          });
        }

        if(clearRateRangeBtn){
          clearRateRangeBtn.addEventListener('click', () => {
            rateState.range = 'daily';
            rateState.date_from = '';
            rateState.date_to = '';
            rateState.time_from = '';
            rateState.time_to = '';
            rateState.interval = 'auto';
            if(rateInterval) rateInterval.value = 'auto';
            if(rateStartDate) rateStartDate.value = '';
            if(rateEndDate) rateEndDate.value = '';
            if(rateStartTime) rateStartTime.value = '';
            if(rateEndTime) rateEndTime.value = '';
            fetchRateHistory();
          });
        }

        const storedTab = localStorage.getItem('cfa_active_tab');
        if(storedTab){
          const targetTab = document.querySelector(`#cfaTabNav button[data-bs-target="${storedTab}"]`);
          if(targetTab){
            const tabInstance = new bootstrap.Tab(targetTab);
            tabInstance.show();
          }
        }
        tabs.forEach(tabBtn => {
          tabBtn.addEventListener('shown.bs.tab', (event) => {
            const target = event.target.getAttribute('data-bs-target');
            localStorage.setItem('cfa_active_tab', target);
            if(target === '#rates-chart'){
              startRatePolling();
            }else{
              stopRatePolling();
            }
          });
        });

        if(document.querySelector('#rates-chart.show.active')){
          startRatePolling();
        }else{
          fetchRateHistory();
        }

        syncActiveFilterButtons();
        updateDateFilterIndicator();
        updateDateRangeSummary();
        fetchData();
      });
    </script>
    <script>
      const cfaRows = document.querySelectorAll('[data-cfa-denomination]');
      const payloadField = document.getElementById('drawerCfaPayload');
      const fallbackField = document.getElementById('drawerCfaFallback');
      const resetBtn = document.getElementById('drawerCfaReset');
      function recalcDrawerPayload(){
        const payload = [];
        let total = 0;
        cfaRows.forEach(row => {
          const denom = parseFloat(row.dataset.cfaDenomination);
          const countInput = row.querySelector('.drawer-count');
          const count = parseInt(countInput.value || '0', 10) || 0;
          const volumeCell = row.querySelector('.drawer-volume');
          const volume = count * denom;
          volumeCell.textContent = volume.toFixed(2);
          if(count > 0){
            payload.push({currency:'CFA', denomination:denom, count:count, volume:volume});
            total += volume;
          }
        });
        payloadField.value = JSON.stringify(payload);
        if(!(document.activeElement && document.activeElement.id === 'drawerCfaFallback')){
          fallbackField.value = total.toFixed(2);
        }
      }
      cfaRows.forEach(row => {
        const input = row.querySelector('.drawer-count');
        input.addEventListener('input', recalcDrawerPayload);
      });
      if(resetBtn){
        resetBtn.addEventListener('click', () => {
          cfaRows.forEach(row => {
            row.querySelector('.drawer-count').value = '';
            row.querySelector('.drawer-volume').textContent = '0.00';
          });
          payloadField.value = '[]';
          fallbackField.value = '0.00';
        });
      }
      recalcDrawerPayload();
    </script>
</div>
<?php else:?>
	<div class="alert alert-danger text-center">
		Access denied. You dont have permission for this action
	</div>
<?php endif?>