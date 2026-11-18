<?php if(user_can('view_agencybanking')):?>

<?php
$adminRoute = $admin_route ?? 'admin';
$pluginRoute = $plugin_route ?? 'agencybanking';
$canViewDetails = user_can('view_agencybanking_details');
$canEdit = user_can('edit_agencybanking');
$canDelete = user_can('delete_agencybanking');
$canApprove = user_can('approve_transactions');

$agentOptions = $users ?? [];
if (empty($agentOptions)) {
	try {
		$agentModel = new \Agencybankings\User;
		$agentOptions = $agentModel->query("SELECT id, first_name, last_name FROM users WHERE deleted = 0 ORDER BY first_name ASC LIMIT 200");
	} catch (\Throwable $th) {
		$agentOptions = [];
	}
}

$transactionTypes = [];
$networkOptions = [];
try {
	$db = new \Core\Database;
	$transactionTypes = $db->query("SELECT DISTINCT transaction_type FROM agencybankings WHERE transaction_type IS NOT NULL AND transaction_type <> '' ORDER BY transaction_type ASC LIMIT 25");
	if (!$transactionTypes || !is_array($transactionTypes)) {
		$transactionTypes = [];
	}
	$networkOptions = $db->query("SELECT DISTINCT network FROM agencybankings WHERE network IS NOT NULL AND network <> '' ORDER BY network ASC LIMIT 25");
	if (!$networkOptions || !is_array($networkOptions)) {
		$networkOptions = [];
	}
} catch (\Throwable $th) {
	$transactionTypes = [];
	$networkOptions = [];
}
?>

<style>
	.agency-page {
		background: var(--bs-body-bg);
		color: var(--bs-body-color);
	}

	.agency-hero {
		border-radius: 1.25rem;
		padding: 2rem;
		border: 1px solid var(--bs-border-color);
		background: linear-gradient(135deg, rgba(var(--bs-primary-rgb, 13,110,253),0.12), rgba(var(--bs-warning-rgb, 255,193,7),0.12));
		box-shadow: 0 25px 45px rgba(15, 23, 42, 0.08);
	}

	#agencyTabs .nav-link.active,
	.active,
	#agencyTabs .nav-link.active:focus,
	#agencyTabs .nav-link.active:hover {
		background: linear-gradient(135deg, rgba(var(--bs-primary-rgb, 13,110,253),0.12), rgba(var(--bs-warning-rgb, 255,193,7),0.12)) !important;
		color: var(--bs-body-color) !important;
		border-color: rgba(var(--bs-primary-rgb,13,110,253), 0.25);
	}

	#agencyInsights .agency-analysis-card h4 {
		color: var(--bs-body-color);
	}

	body[data-bs-theme="dark"] #agencyInsights .agency-analysis-card h4 {
		color: rgba(255,255,255,0.92) !important;
	}

	.agency-actions .btn,
	.agency-actions .dropdown-toggle {
		border-radius: 999px;
		font-weight: 500;
	}

	.agency-stat-card {
		border-radius: 1rem;
		padding: 1.25rem;
		background: var(--bs-body-bg);
		border: 1px solid rgba(15, 23, 42, 0.08);
		box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08);
		min-height: 150px;
	}

	.agency-stat-card h3,
	.agency-stat-card h4 {
		font-weight: 600;
		margin-bottom: .35rem;
	}

	.agency-chip-list {
		display: flex;
		flex-wrap: wrap;
		gap: 0.4rem;
	}

	.agency-chip {
		border-radius: 999px;
		padding: 0.35rem 1rem;
		border: 1px dashed rgba(var(--bs-primary-rgb,13,110,253), 0.4);
		font-size: 0.85rem;
		font-weight: 500;
	}

	.agency-chip.muted {
		border-color: var(--bs-border-color);
		color: var(--bs-secondary-color, #6c757d);
	}

	.agency-filters label {
		font-size: 0.78rem;
		text-transform: uppercase;
		letter-spacing: 0.08em;
		font-weight: 600;
	}

	.agency-tab-card {
		border-radius: 1.25rem;
		border: 1px solid var(--bs-border-color);
		box-shadow: 0 25px 45px rgba(179, 180, 182, 0.08);
		background: var(--bs-body-bg);
		overflow: hidden;
	}

	.agency-filter-card {
		border: 1px solid rgba(15,23,42,0.08);
		border-radius: 1rem;
		padding: 1rem;
		height: 100%;
		background: var(--bs-body-bg);
		box-shadow: 0 15px 35px rgba(15,23,42,0.06);
	}

	.agency-filter-card-header {
		display: flex;
		justify-content: space-between;
		align-items: flex-start;
		gap: .75rem;
	}

	.agency-filter-title {
		font-weight: 600;
	}

	.agency-selected-filters {
		background: rgba(15, 23, 42, 0.02);
		border: 1px dashed rgba(15, 23, 42, 0.15);
		border-radius: 1rem;
		padding: 1rem;
	}

	.agency-filter-empty {
		color: var(--bs-secondary-color);
		font-size: 0.9rem;
	}

	.agency-table thead th {
		text-transform: uppercase;
		font-size: 0.75rem;
		letter-spacing: 0.08em;
		border-top: 0;
		color: var(--bs-secondary-color, #6c757d);
		background: var(--bs-tertiary-bg, rgba(108, 117, 125, 0.06));
	}

	.agency-table-head {
		display: none;
	}

	@media (min-width: 992px) {
		.agency-table-head {
			display: table-header-group;
		}
	}

	.agency-table tbody td {
		vertical-align: middle;
		border-color: rgba(148, 163, 184, 0.25);
	}

	.agency-table tbody tr {
		transition: background-color 0.16s ease;
	}

	.agency-table tbody tr:hover > td {
		background-color: var(--bs-tertiary-bg, rgba(108, 117, 125, 0.045));
	}

	.agency-stack-title {
		color: var(--bs-body-color);
		font-weight: 700;
	}

	.agency-stack-meta {
		color: var(--bs-secondary-color, #6c757d);
		font-size: 0.78rem;
		line-height: 1.4;
		overflow-wrap: anywhere;
	}

	.agency-stack-amount {
		color: var(--bs-body-color);
		font-size: 0.92rem;
		font-weight: 800;
		overflow-wrap: anywhere;
	}

	.agency-actions-cell {
		min-width: 150px;
	}

	.agency-row-actions {
		display: flex;
		align-items: center;
		justify-content: flex-end;
		flex-wrap: wrap;
		gap: 0.35rem;
	}

	.agency-row-action {
		display: inline-flex;
		min-width: 2.15rem;
		height: 2.15rem;
		align-items: center;
		justify-content: center;
		padding: 0 0.55rem;
		border: 1px solid var(--bs-border-color);
		border-radius: 0.7rem;
		background: var(--bs-body-bg);
		color: var(--bs-secondary-color, #6c757d);
		font-size: 0.82rem;
		line-height: 1;
		text-decoration: none;
		transition: transform 0.16s ease, box-shadow 0.16s ease, color 0.16s ease, border-color 0.16s ease;
	}

	.agency-row-action:hover,
	.agency-row-action:focus-visible {
		transform: translateY(-1px);
		box-shadow: 0 0.35rem 0.8rem rgba(15, 23, 42, 0.12);
	}

	.agency-row-action--view:hover,
	.agency-row-action--view:focus-visible {
		border-color: rgba(var(--bs-primary-rgb, 13, 110, 253), 0.45);
		color: var(--bs-primary);
	}

	.agency-row-action--edit:hover,
	.agency-row-action--edit:focus-visible {
		border-color: rgba(var(--bs-warning-rgb, 255, 193, 7), 0.65);
		color: var(--bs-warning-text-emphasis, #997404);
	}

	.agency-row-action--delete:hover,
	.agency-row-action--delete:focus-visible {
		border-color: rgba(var(--bs-danger-rgb, 220, 53, 69), 0.45);
		color: var(--bs-danger);
	}

	.agency-row-action--cancel {
		border-color: rgba(var(--bs-danger-rgb, 220, 53, 69), 0.35);
		background: rgba(var(--bs-danger-rgb, 220, 53, 69), 0.08);
		color: var(--bs-danger);
	}

	.agency-row-action--cancel:hover,
	.agency-row-action--cancel:focus-visible {
		border-color: var(--bs-danger);
		background: var(--bs-danger);
		color: #fff;
	}

	.agency-row-action--approve {
		gap: 0.4rem;
		border-color: rgba(var(--bs-success-rgb, 25, 135, 84), 0.35);
		background: rgba(var(--bs-success-rgb, 25, 135, 84), 0.1);
		color: var(--bs-success-text-emphasis, #146c43);
		font-weight: 700;
		white-space: nowrap;
	}

	.agency-row-action--approve:hover,
	.agency-row-action--approve:focus-visible {
		border-color: var(--bs-success);
		background: var(--bs-success);
		color: #fff;
	}

	.agency-no-actions {
		color: var(--bs-secondary-color, #6c757d);
		font-size: 0.8rem;
	}

	.agency-loader {
		position: absolute;
		top: 0;
		left: 0;
		right: 0;
		bottom: 0;
		display: flex;
		align-items: center;
		justify-content: center;
		background: rgba(255,255,255,0.85);
		backdrop-filter: blur(3px);
		z-index: 3;
		transition: opacity .2s ease;
	}

	.agency-loader.hidden {
		opacity: 0;
		pointer-events: none;
	}

	.agency-empty,
	.agency-error {
		padding: 3rem 1rem;
		text-align: center;
	}

	.agency-error {
		color: var(--bs-danger);
	}

	.agency-avatar,
	.agency-list-avatar {
		width: 40px;
		height: 40px;
		border-radius: 999px;
		object-fit: cover;
	}

	.agency-list-avatar {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		background: rgba(var(--bs-warning-rgb,255,193,7),0.2);
		color: var(--bs-warning);
		font-weight: 600;
	}

	.agency-analysis-card {
		border-radius: 1rem;
		border: 1px solid var(--bs-border-color);
		padding: 1.25rem;
		height: 100%;
	}

	.agency-progress {
		height: 8px;
		border-radius: 999px;
		background: rgba(var(--bs-primary-rgb,13,110,253),0.1);
		overflow: hidden;
	}

	.agency-progress span {
		display: block;
		height: 100%;
		background: var(--bs-primary);
	}

	.agency-pulse {
		display: inline-flex;
		align-items: center;
		gap: 0.35rem;
		font-size: 0.85rem;
		color: var(--bs-success);
	}

	.agency-pulse::before {
		content: "";
		width: 8px;
		height: 8px;
		border-radius: 999px;
		background: currentColor;
		animation: agencyPulse 1.6s infinite;
	}

	@keyframes agencyPulse {
		0% { opacity: 0.2; transform: scale(0.8); }
		50% { opacity: 1; transform: scale(1.1); }
		100% { opacity: 0.2; transform: scale(0.8); }
	}

	.agency-insights-pane {
		background: var(--bs-white);
		border-radius: 1.25rem;
		padding: 1.5rem;
	}

	body[data-bs-theme="dark"] .agency-insights-pane {
		background: rgba(15, 23, 42, 0.95);
		box-shadow: inset 0 0 0 1px rgba(255,255,255,0.05);
	}

	.agency-list-group {
		list-style: none;
		padding-left: 0;
		margin-bottom: 0;
	}

	.agency-list-group li + li {
		border-top: 1px solid rgba(148, 163, 184, 0.2);
	}

	@media (max-width: 992px) {
		.agency-hero { padding: 1.5rem; }
		.agency-actions-cell { min-width: 0; }
		.agency-table tbody tr.d-lg-none > td { padding: 0.85rem !important; }
		.agency-mobile-actions {
			padding-top: 0.6rem;
			border-top: 1px solid var(--bs-border-color);
		}
		.agency-mobile-actions .agency-row-actions { justify-content: flex-start; }
	}

	@media (min-width:768px) and (max-width:991.98px){
		.agency-stat-card{padding:1.15rem;}
		.agency-stat-card h3{font-size:1.35rem;}
	}

	/* ... */
</style>

<div class="agency-page">
	<div class="agency-hero mb-4">
		<div class="row align-items-center g-3">
			<div class="col-lg-8">
				<p class="text-uppercase text-muted mb-1 small fw-semibold">Agency banking overview</p>
				<h1 class="mb-2">Transactions dashboard</h1>
				<p class="mb-0 text-secondary">Monitor cash & e-cash flows, approvals, and agent performance with live analytics.</p>
			</div>
			<div class="col-lg-4 text-lg-end">
				<div class="agency-actions d-flex flex-wrap justify-content-lg-end gap-2">
					<?php if(user_can('is_admin')): ?>
						<a class="btn btn-outline-warning" href="<?=ROOT?>/<?=$adminRoute?>/<?=$pluginRoute?>/limits"><i class="fa fa-sliders me-1"></i>Limits</a>
					<?php endif; ?>
					<div class="dropdown">
						<button class="btn btn-warning text-dark dropdown-toggle" type="button" id="addAgency" data-bs-toggle="dropdown" aria-expanded="false">
							<i class="fa fa-plus me-1"></i> Add
						</button>
						<ul class="dropdown-menu" aria-labelledby="addAgency">
							<li><a class="dropdown-item" href="<?=ROOT?>/<?=$adminRoute?>/<?=$pluginRoute?>/deposit"><i class="fa fa-arrow-down text-warning me-2"></i>Deposit</a></li>
							<li><a class="dropdown-item" href="<?=ROOT?>/<?=$adminRoute?>/<?=$pluginRoute?>/withdrawal"><i class="fa fa-arrow-up text-warning me-2"></i>Withdrawal</a></li>
							<li><a class="dropdown-item" href="<?=ROOT?>/<?=$adminRoute?>/<?=$pluginRoute?>/direct_transfer"><i class="fa fa-exchange-alt text-warning me-2"></i>Direct Transfer</a></li>
							<li><a class="dropdown-item" href="<?=ROOT?>/<?=$adminRoute?>/<?=$pluginRoute?>/interbank"><i class="fa fa-exchange-alt text-warning me-2"></i>Interbank</a></li>
							<li><a class="dropdown-item" href="<?=ROOT?>/<?=$adminRoute?>/<?=$pluginRoute?>/account_opening"><i class="fa fa-circle-plus text-warning me-2"></i>Account Opening</a></li>
						</ul>
					</div>
					<button id="export-pdf-btn" class="btn btn-outline-danger export-pdf-btn" onclick="exportPdf()">
						<i class="fa fa-file-pdf me-2"></i> Export PDF
					</button>
				</div>
			</div>
		</div>

		<div class="row g-3 mt-1">
			<div class="col-12 col-sm-6 col-lg-4">
				<div class="agency-stat-card h-100">
					<p class="text-uppercase text-muted small mb-1">Total Volume</p>
					<h3 class="text-primary mb-2" data-total-volume>GHS 0.00</h3>
					<small class="text-muted" data-tx-count>0 transactions</small>
				</div>
			</div>
			<div class="col-12 col-sm-6 col-lg-4">
				<div class="agency-stat-card h-100">
					<p class="text-uppercase text-muted small mb-1">Total Charges</p>
					<h3 class="text-warning mb-2" data-total-charge>GHS 0.00</h3>
					<small class="text-muted">Service fees collected</small>
				</div>
			</div>
			<div class="col-12 col-lg-4">
				<div class="agency-stat-card h-100">
					<p class="text-uppercase text-muted small mb-1">Latest activity</p>
					<h3 class="mb-2" data-latest-activity>Awaiting sync</h3>
					<span class="agency-pulse">Live</span>
				</div>
			</div>
		</div>
	</div>

	<div class="card border-0 shadow-sm mt-4 agency-filters">
		<div class="card-body">
			<form id="agencyFilters" class="row g-3">
				<div class="col-8 col-md-9 col-lg-4">
					<label class="form-label">Search</label>
					<div class="input-group rounded-pill overflow-hidden border">
						<span class="input-group-text bg-transparent border-0 ps-3"><i class="fa fa-search text-muted"></i></span>
						<input type="text" class="form-control border-0" id="agencySearch" placeholder="Name, phone, network, ref">
					</div>
				</div>
				<div class="col-4 col-md-3 col-lg-2">
					<label class="form-label">Per page</label>
					<select class="form-select w-auto" id="agencyLimit">
						<option value="10">10</option>
						<option value="25">25</option>
						<option value="50">50</option>
						<option value="100">100</option>
					</select>
				</div>
				<div class="col-12 col-md-4 col-lg-4 d-none d-lg-block">
					<label class="form-label">Advanced filters</label>
					<div class="dropdown w-100" data-bs-auto-close="outside">
						<button class="btn btn-outline-secondary w-100 d-flex justify-content-between align-items-center" type="button" data-bs-toggle="dropdown" aria-expanded="false">
							<span><i class="fa fa-sliders me-2"></i>Add filter</span>
							<i class="fa fa-chevron-down text-muted"></i>
						</button>
						<div class="dropdown-menu w-100 p-3" id="agencyFilterMenu">
							<div class="form-check mb-2">
								<input class="form-check-input" type="checkbox" value="date" id="agencyFilterOptDate" data-filter-option="date">
								<label class="form-check-label" for="agencyFilterOptDate">Date range</label>
							</div>
							<div class="form-check mb-2">
								<input class="form-check-input" type="checkbox" value="created_by" id="agencyFilterOptCreated" data-filter-option="created_by">
								<label class="form-check-label" for="agencyFilterOptCreated">Created by</label>
							</div>
							<div class="form-check mb-2">
								<input class="form-check-input" type="checkbox" value="transaction_type" id="agencyFilterOptType" data-filter-option="transaction_type">
								<label class="form-check-label" for="agencyFilterOptType">Transaction type</label>
							</div>
							<div class="form-check mb-2">
								<input class="form-check-input" type="checkbox" value="transaction_status" id="agencyFilterOptStatus" data-filter-option="transaction_status">
								<label class="form-check-label" for="agencyFilterOptStatus">Status</label>
							</div>
							<div class="form-check">
								<input class="form-check-input" type="checkbox" value="network" id="agencyFilterOptNetwork" data-filter-option="network">
								<label class="form-check-label" for="agencyFilterOptNetwork">Network</label>
							</div>
						</div>
					</div>
					<small class="text-muted">Select multiple filters to refine the dataset.</small>
				</div>
				<div class="col-12 col-md-12 col-lg-2 d-flex gap-2 align-self-end agency-filter-actions d-none d-lg-flex">
					<button type="submit" class="btn btn-dark flex-fill d-none">Apply</button>
					<button type="button" id="agencyResetFilters" class="btn btn-outline-secondary flex-fill">Reset</button>
				</div>
				<div class="col-12 d-none d-lg-block">
					<div class="agency-selected-filters" id="agencySelectedFilters">
						<div class="agency-filter-empty" id="agencyFilterEmpty">
							<i class="fa fa-circle-xmark me-1"></i> No advanced filters selected.
						</div>
						<div class="row g-3" id="agencyFilterFields"></div>
					</div>
				</div>
			</form>
		</div>
	</div>

	<template id="agencyFilterTemplate-date">
		<div class="col-12 col-lg-6" data-filter-block="date">
			<div class="d-flex justify-content-between align-items-center mb-2">
				<strong class="small text-uppercase text-muted">Date range</strong>
				<button type="button" class="btn btn-sm btn-outline-secondary" data-remove-filter="date">Remove</button>
			</div>
			<div class="row g-2">
				<div class="col-12 col-md-6">
					<input type="date" class="form-control" id="agencyDateFrom" placeholder="From">
				</div>
				<div class="col-12 col-md-6">
					<input type="date" class="form-control" id="agencyDateTo" placeholder="To">
				</div>
			</div>
		</div>
	</template>

	<template id="agencyFilterTemplate-created">
		<div class="col-12 col-md-6 col-lg-4" data-filter-block="created_by">
			<div class="d-flex justify-content-between align-items-center mb-2">
				<strong class="small text-uppercase text-muted">Created by</strong>
				<button type="button" class="btn btn-sm btn-outline-secondary" data-remove-filter="created_by">Remove</button>
			</div>
			<select class="form-select" id="agencyCreatedBy">
				<option value="">All</option>
				<?php foreach(($agentOptions ?? []) as $agent):
					$agentId = is_object($agent) ? ($agent->id ?? '') : ($agent['id'] ?? '');
					$first = is_object($agent) ? ($agent->first_name ?? '') : ($agent['first_name'] ?? '');
					$last  = is_object($agent) ? ($agent->last_name ?? '') : ($agent['last_name'] ?? '');
					$name  = trim($first.' '.$last);
					if(!$agentId) continue;
				?>
					<option value="<?=htmlspecialchars((string)$agentId, ENT_QUOTES)?>"><?=htmlspecialchars($name ?: (string)$agentId, ENT_QUOTES)?></option>
				<?php endforeach; ?>
			</select>
		</div>
	</template>

	<template id="agencyFilterTemplate-transaction_type">
		<div class="col-12 col-md-6 col-lg-4" data-filter-block="transaction_type">
			<div class="d-flex justify-content-between align-items-center mb-2">
				<strong class="small text-uppercase text-muted">Transaction type</strong>
				<button type="button" class="btn btn-sm btn-outline-secondary" data-remove-filter="transaction_type">Remove</button>
			</div>
			<select class="form-select" id="agencyTransactionType">
				<option value="">All</option>
				<?php foreach(($transactionTypes ?? []) as $typeRow):
					$label = is_object($typeRow) ? ($typeRow->transaction_type ?? '') : ($typeRow['transaction_type'] ?? '');
					$label = trim((string)$label);
					if($label === '') continue;
				?>
					<option value="<?=htmlspecialchars($label, ENT_QUOTES)?>"><?=htmlspecialchars($label, ENT_QUOTES)?></option>
				<?php endforeach; ?>
			</select>
		</div>
	</template>

	<template id="agencyFilterTemplate-transaction_status">
		<div class="col-12 col-md-6 col-lg-4" data-filter-block="transaction_status">
			<div class="d-flex justify-content-between align-items-center mb-2">
				<strong class="small text-uppercase text-muted">Status</strong>
				<button type="button" class="btn btn-sm btn-outline-secondary" data-remove-filter="transaction_status">Remove</button>
			</div>
			<select class="form-select" id="agencyTransactionStatus">
				<option value="">All</option>
				<option value="pending">Pending</option>
				<option value="approved">Approved</option>
				<option value="declined">Declined</option>
				<option value="rejected">Rejected</option>
				<option value="reversed">Reversed</option>
				<option value="draft">Draft</option>
			</select>
		</div>
	</template>

	<template id="agencyFilterTemplate-network">
		<div class="col-12 col-md-6 col-lg-4" data-filter-block="network">
			<div class="d-flex justify-content-between align-items-center mb-2">
				<strong class="small text-uppercase text-muted">Network</strong>
				<button type="button" class="btn btn-sm btn-outline-secondary" data-remove-filter="network">Remove</button>
			</div>
			<select class="form-select" id="agencyNetwork">
				<option value="">All</option>
				<?php foreach(($networkOptions ?? []) as $netRow):
					$label = is_object($netRow) ? ($netRow->network ?? '') : ($netRow['network'] ?? '');
					$label = trim((string)$label);
					if($label === '') continue;
				?>
					<option value="<?=htmlspecialchars($label, ENT_QUOTES)?>"><?=htmlspecialchars($label, ENT_QUOTES)?></option>
				<?php endforeach; ?>
			</select>
		</div>
	</template>

	<ul class="nav nav-pills mt-4" id="agencyTabs" role="tablist">
		<li class="nav-item" role="presentation">
			<button class="nav-link active" id="agency-live-tab" data-bs-toggle="tab" data-bs-target="#agencyLive" type="button" role="tab" aria-controls="agencyLive" aria-selected="true">Live results</button>
		</li>
		<li class="nav-item" role="presentation">
			<button class="nav-link" id="agency-insights-tab" data-bs-toggle="tab" data-bs-target="#agencyInsights" type="button" role="tab" aria-controls="agencyInsights" aria-selected="false">Insights & analysis</button>
		</li>
	</ul>

	<div class="tab-content mt-3">
		<div class="tab-pane fade show active" id="agencyLive" role="tabpanel" aria-labelledby="agency-live-tab">
			<div class="agency-tab-card position-relative">
				<div class="card-header bg-transparent border-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
					<div>
						<p class="mb-0 text-muted small">Showing <span data-result-count>0</span> / <span data-total-count>0</span> transactions</p>
						<small class="text-muted">Updated <span data-last-sync>—</span></small>
					</div>
					<div class="agency-chip-list">
						<span class="agency-chip">Async</span>
						<span class="agency-chip muted">No reloads</span>
					</div>
				</div>
				<div class="position-relative">
					<div class="agency-loader hidden" id="agencyTableLoader">
						<div class="text-center">
							<div class="spinner-border text-primary mb-2" role="status"></div>
							<p class="text-muted mb-0">Fetching latest data…</p>
						</div>
					</div>
					<div class="table-responsive">
						<table class="table align-middle agency-table mb-0">
							<thead class="agency-table-head">
								<tr>
									<th># / Ref</th>
									<th>Date</th>
									<th>Transaction</th>
									<th>Status</th>
									<th class="text-end">Amount</th>
									<th>Agent</th>
									<th class="text-end">Actions</th>
								</tr>
							</thead>
							<tbody id="agencyTableBody"></tbody>
						</table>
					</div>
					<div class="agency-empty text-muted d-none" id="agencyEmpty">
						<i class="fa fa-box-open fa-2x mb-2"></i>
						<p class="mb-0">No transactions match the current filters.</p>
					</div>
					<div class="agency-error text-danger d-none" id="agencyError"></div>
				</div>
				<div class="card-footer bg-transparent border-0">
					<nav aria-label="Agency pagination">
						<ul class="pagination justify-content-center mb-0" id="agencyPagination"></ul>
					</nav>
				</div>
			</div>
		</div>
		<div class="tab-pane fade agency-insights-pane" id="agencyInsights" role="tabpanel" aria-labelledby="agency-insights-tab">
			<div class="row g-3">
				<div class="col-lg-4">
					<div class="agency-analysis-card h-100">
						<p class="text-uppercase text-muted small mb-1">Avg ticket</p>
						<h4 data-avg-ticket>GHS 0.00</h4>
						<p class="text-muted mb-0">Average amount per agency transaction.</p>
					</div>
				</div>
				<div class="col-lg-4">
					<div class="agency-analysis-card h-100">
						<p class="text-uppercase text-muted small mb-1">Charge rate</p>
						<h4 data-charge-rate>0%</h4>
						<p class="text-muted mb-0">Charges relative to total volume.</p>
					</div>
				</div>
				<div class="col-lg-4">
					<div class="agency-analysis-card h-100">
						<p class="text-uppercase text-muted small mb-1">Latest sync</p>
						<h4 data-last-sync-insight>—</h4>
						<p class="text-muted mb-0">Timestamp from the live data endpoint.</p>
					</div>
				</div>
			</div>
			<div class="row g-3 mt-1">
				<div class="col-xl-4">
					<div class="agency-analysis-card h-100">
						<h5 class="mb-3">Top agents</h5>
						<ul class="agency-list-group" id="agencyTopAgents"></ul>
					</div>
				</div>
				<div class="col-xl-4">
					<div class="agency-analysis-card h-100">
						<h5 class="mb-3">Network mix</h5>
						<ul class="agency-list-group" id="agencyNetworkMix"></ul>
					</div>
				</div>
				<div class="col-xl-4">
					<div class="agency-analysis-card h-100">
						<h5 class="mb-3">Status breakdown</h5>
						<ul class="agency-list-group" id="agencyStatusBreakdown"></ul>
					</div>
				</div>
			</div>
			<div class="agency-analysis-card mt-3">
				<h5 class="mb-3">7-day trend</h5>
				<div class="table-responsive">
					<table class="table table-sm mb-0">
						<thead>
							<tr>
								<th>Date</th>
								<th>Volume</th>
								<th>Transactions</th>
							</tr>
						</thead>
						<tbody id="agencyTrend"></tbody>
					</table>
				</div>
	</div>
</div>

<div id="agencyApprovalSecurity" class="d-none"><?= csrf() ?></div>

<script>
(function(){
	const DATA_ENDPOINT = '<?=ROOT?>/<?=$adminRoute?>/<?=$pluginRoute?>/data';
	const VIEW_BASE = '<?=ROOT?>/<?=$adminRoute?>/<?=$pluginRoute?>';
	const CURRENT_USER_ID = <?= (int)(get_session()->id ?? 0) ?>;
	const approvalCsrfToken = document.querySelector('#agencyApprovalSecurity input[name="csrf"]')?.value || '';
	const permissions = {
		view: <?= $canViewDetails ? 'true' : 'false' ?>,
		edit: <?= $canEdit ? 'true' : 'false' ?>,
		delete: <?= $canDelete ? 'true' : 'false' ?>,
		approve: <?= $canApprove ? 'true' : 'false' ?>
	};

	const refs = {
		form: document.getElementById('agencyFilters'),
		reset: document.getElementById('agencyResetFilters'),
		search: document.getElementById('agencySearch'),
		limit: document.getElementById('agencyLimit'),
		filterMenu: document.getElementById('agencyFilterMenu'),
		filterOptions: document.querySelectorAll('[data-filter-option]'),
		filterFields: document.getElementById('agencyFilterFields'),
		filterEmpty: document.getElementById('agencyFilterEmpty'),
		loader: document.getElementById('agencyTableLoader'),
		body: document.getElementById('agencyTableBody'),
		pagination: document.getElementById('agencyPagination'),
		empty: document.getElementById('agencyEmpty'),
		error: document.getElementById('agencyError'),
		resultCount: document.querySelector('[data-result-count]'),
		totalCount: document.querySelector('[data-total-count]'),
		lastSync: document.querySelector('[data-last-sync]'),
		statVolume: document.querySelector('[data-total-volume]'),
		statCharge: document.querySelector('[data-total-charge]'),
		statTx: document.querySelector('[data-tx-count]'),
		latestActivity: document.querySelector('[data-latest-activity]'),
		avgTicket: document.querySelector('[data-avg-ticket]'),
		chargeRate: document.querySelector('[data-charge-rate]'),
		lastSyncInsight: document.querySelector('[data-last-sync-insight]'),
		topAgents: document.getElementById('agencyTopAgents'),
		networkMix: document.getElementById('agencyNetworkMix'),
		statusBreakdown: document.getElementById('agencyStatusBreakdown'),
		trend: document.getElementById('agencyTrend'),
	};

	const state = {
		page: 1,
		limit: parseInt(refs.limit.value, 10) || 10,
		filters: {
			find: '',
			date_from: '',
			date_to: '',
			created_by: '',
			transaction_type: '',
			transaction_status: '',
			network: ''
		}
	};

	function updateUrlFromState(){
		try {
			const params = new URLSearchParams();
			if(state.page && state.page !== 1) params.set('page', String(state.page));
			if(state.limit && state.limit !== 10) params.set('limit', String(state.limit));
			if(state.filters.find) params.set('find', state.filters.find);
			if(state.filters.date_from) params.set('date_from', state.filters.date_from);
			if(state.filters.date_to) params.set('date_to', state.filters.date_to);
			if(state.filters.created_by) params.set('created_by', state.filters.created_by);
			if(state.filters.transaction_type) params.set('transaction_type', state.filters.transaction_type);
			if(state.filters.transaction_status) params.set('transaction_status', state.filters.transaction_status);
			if(state.filters.network) params.set('network', state.filters.network);
			const active = Array.from(activeFilters);
			if(active.length) params.set('filters', active.join(','));
			const next = params.toString();
			const url = window.location.pathname + (next ? ('?' + next) : '');
			window.history.replaceState({}, '', url);
		} catch (e) {
			// ignore
		}
	}

	function restoreStateFromUrl(){
		try {
			const params = new URLSearchParams(window.location.search || '');
			const urlPage = parseInt(params.get('page') || '1', 10);
			const urlLimit = parseInt(params.get('limit') || String(state.limit || 10), 10);
			state.page = Number.isFinite(urlPage) && urlPage > 0 ? urlPage : 1;
			state.limit = Number.isFinite(urlLimit) && urlLimit > 0 ? urlLimit : (parseInt(refs.limit.value, 10) || 10);
			state.filters.find = (params.get('find') || '').trim();
			state.filters.date_from = (params.get('date_from') || '').trim();
			state.filters.date_to = (params.get('date_to') || '').trim();
			state.filters.created_by = (params.get('created_by') || '').trim();
			state.filters.transaction_type = (params.get('transaction_type') || '').trim();
			state.filters.transaction_status = (params.get('transaction_status') || '').trim();
			state.filters.network = (params.get('network') || '').trim();
			if(refs.search) refs.search.value = String(state.filters.find || '');
			if(refs.limit) refs.limit.value = String(state.limit || 10);
			const fromParam = (params.get('filters') || '').split(',').map(s => s.trim()).filter(Boolean);
			const inferred = [];
			if(state.filters.date_from || state.filters.date_to) inferred.push('date');
			if(state.filters.created_by) inferred.push('created_by');
			if(state.filters.transaction_type) inferred.push('transaction_type');
			if(state.filters.transaction_status) inferred.push('transaction_status');
			if(state.filters.network) inferred.push('network');
			const desired = new Set([...(fromParam || []), ...(inferred || [])]);
			refs.filterOptions.forEach(option => {
				const key = option.dataset.filterOption;
				option.checked = key ? desired.has(key) : false;
			});
		} catch (e) {
			// ignore
		}
	}

	const FILTER_DEFINITIONS = {
		date: {
			templateId: 'agencyFilterTemplate-date',
			applyValues(){
				const from = document.getElementById('agencyDateFrom');
				const to = document.getElementById('agencyDateTo');
				if(from) from.value = state.filters.date_from || '';
				if(to) to.value = state.filters.date_to || '';
			},
			clear(){
				state.filters.date_from = '';
				state.filters.date_to = '';
			},
		},
		created_by: {
			templateId: 'agencyFilterTemplate-created',
			applyValues(){
				const select = document.getElementById('agencyCreatedBy');
				if(select) select.value = state.filters.created_by || '';
			},
			clear(){ state.filters.created_by = ''; },
		},
		transaction_type: {
			templateId: 'agencyFilterTemplate-transaction_type',
			applyValues(){
				const select = document.getElementById('agencyTransactionType');
				if(select) select.value = state.filters.transaction_type || '';
			},
			clear(){ state.filters.transaction_type = ''; },
		},
		transaction_status: {
			templateId: 'agencyFilterTemplate-transaction_status',
			applyValues(){
				const select = document.getElementById('agencyTransactionStatus');
				if(select) select.value = state.filters.transaction_status || '';
			},
			clear(){ state.filters.transaction_status = ''; },
		},
		network: {
			templateId: 'agencyFilterTemplate-network',
			applyValues(){
				const select = document.getElementById('agencyNetwork');
				if(select) select.value = state.filters.network || '';
			},
			clear(){ state.filters.network = ''; },
		},
	};

	const activeFilters = new Set();

	function updateFilterEmptyState(){
		const hasFilters = refs.filterFields.children.length > 0;
		refs.filterEmpty.classList.toggle('d-none', hasFilters);
	}

	function mountFilterBlock(key){
		const definition = FILTER_DEFINITIONS[key];
		if(!definition) return;
		const template = document.getElementById(definition.templateId);
		if(!template) return;
		const fragment = template.content.cloneNode(true);
		refs.filterFields.appendChild(fragment);
		definition.applyValues?.();
		updateFilterEmptyState();
	}

	function unmountFilterBlock(key){
		const block = refs.filterFields.querySelector(`[data-filter-block="${key}"]`);
		if(block && block.parentNode){
			block.parentNode.removeChild(block);
		}
		FILTER_DEFINITIONS[key]?.clear?.();
		updateFilterEmptyState();
	}

	function syncFilterSelection(option){
		const key = option.dataset.filterOption;
		if(!key) return;
		if(option.checked){
			if(!activeFilters.has(key)){
				activeFilters.add(key);
				mountFilterBlock(key);
			}
		}else{
			activeFilters.delete(key);
			unmountFilterBlock(key);
		}
	}

	restoreStateFromUrl();
	refs.filterOptions.forEach(option => {
		syncFilterSelection(option);
	});

	refs.filterOptions.forEach(option => {
		option.addEventListener('change', () => {
			syncFilterSelection(option);
			applyFiltersAndFetch(true);
		});
	});

	refs.filterFields.addEventListener('click', (event) => {
		const removalBtn = event.target.closest('[data-remove-filter]');
		if(!removalBtn) return;
		const key = removalBtn.getAttribute('data-remove-filter');
		const option = refs.filterMenu.querySelector(`[data-filter-option="${key}"]`);
		if(option){
			option.checked = false;
			activeFilters.delete(key);
		}
		unmountFilterBlock(key);
		applyFiltersAndFetch(true);
	});

	function collectFilterValues(){
		state.filters = {
			find: refs.search.value.trim(),
			date_from: document.getElementById('agencyDateFrom')?.value || '',
			date_to: document.getElementById('agencyDateTo')?.value || '',
			created_by: document.getElementById('agencyCreatedBy')?.value || '',
			transaction_type: document.getElementById('agencyTransactionType')?.value || '',
			transaction_status: document.getElementById('agencyTransactionStatus')?.value || '',
			network: document.getElementById('agencyNetwork')?.value || ''
		};
	}

	function debounce(fn, wait = 350){
		let timer;
		return (...args) => {
			window.clearTimeout(timer);
			timer = window.setTimeout(() => fn(...args), wait);
		};
	}

	function applyFiltersAndFetch(resetPage = true){
		collectFilterValues();
		state.limit = parseInt(refs.limit?.value, 10) || 10;
		if(resetPage) state.page = 1;
		updateUrlFromState();
		fetchData();
	}

	const scheduleApplyFilters = debounce(() => applyFiltersAndFetch(true), 400);

	refs.search?.addEventListener('input', scheduleApplyFilters);
	refs.limit?.addEventListener('change', () => applyFiltersAndFetch(true));
	refs.filterFields?.addEventListener('input', (event) => {
		if(!event.target?.matches('input, select, textarea')) return;
		scheduleApplyFilters();
	});
	refs.filterFields?.addEventListener('change', (event) => {
		if(!event.target?.matches('input, select, textarea')) return;
		applyFiltersAndFetch(true);
	});

	function formatCurrency(value){
		return new Intl.NumberFormat('en-GH', { style: 'currency', currency: 'GHS' }).format(Number(value || 0));
	}

	function formatDate(value){
		if(!value) return '—';
		return new Date(value).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' });
	}

	function agencyEscapeHtml(value){
		return String(value ?? '').replace(/[&<>"']/g, function(char){
			return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[char] || char;
		});
	}

	function toggleLoader(show){
		refs.loader.classList.toggle('hidden', !show);
	}

	function renderRows(rows){
		if(!rows || rows.length === 0){
			refs.body.innerHTML = '';
			refs.empty.classList.remove('d-none');
			return;
		}
		refs.empty.classList.add('d-none');
		const html = rows.map(row => {
			const status = String(row.transaction_status || 'Pending').trim().toLowerCase();
			const isAccountingStatus = status.includes('approved') || status.includes('sent');
			const isClosedStatus = ['cancelled', 'canceled', 'declined', 'rejected', 'reversed'].includes(status);
			const badgeClass = status.includes('pending') ? 'badge bg-warning text-dark' :
				status.includes('draft') ? 'badge bg-secondary' :
				(status.includes('approved') || status.includes('sent')) ? 'badge bg-success' : 'badge bg-danger';
			const canModify = !!row.can_modify;
			const isOwner = !!row.is_owner;
			const rejectionReason = String(row.rejection_reason || '').trim();
			const reasonHtml = rejectionReason
				? `<div class="agency-stack-meta text-danger mt-1" style="white-space:normal;"><strong>Reason:</strong> ${agencyEscapeHtml(rejectionReason)}</div>`
				: '';
			const actions = [];
			if(permissions.view){
				actions.push(`<a href="${VIEW_BASE}/view/${row.id}" class="agency-row-action agency-row-action--view" title="View transaction" aria-label="View transaction ${row.id}"><i class="fa fa-eye"></i></a>`);
			}
			if(!isClosedStatus && (permissions.edit || canModify)){
				actions.push(`<a href="${VIEW_BASE}/edit/${row.id}" class="agency-row-action agency-row-action--edit" title="Edit transaction" aria-label="Edit transaction ${row.id}"><i class="fa fa-pen"></i></a>`);
			}
			if(!isAccountingStatus && !isClosedStatus && (permissions.delete || canModify)){
				actions.push(`<a href="${VIEW_BASE}/delete/${row.id}" class="agency-row-action agency-row-action--delete" title="Delete transaction" aria-label="Delete transaction ${row.id}"><i class="fa fa-trash"></i></a>`);
			}
			if(permissions.approve && String(row.transaction_type || '').trim().toLowerCase() === 'account opening' && status.startsWith('pending')){
				actions.push(`<button type="button" class="agency-row-action agency-row-action--approve" data-agency-approve="${agencyEscapeHtml(row.id)}" aria-label="Approve account opening"><i class="fa fa-check" aria-hidden="true"></i><span>Approve</span></button>`);
			}
			if(!isClosedStatus){
				actions.push(`<button type="button" class="agency-row-action agency-row-action--cancel js-direct-cancel" data-id="${row.id}" data-cancel-endpoint="${VIEW_BASE}/cancel" data-cancel-label="Transaction #${row.id}" aria-label="Cancel transaction #${row.id}" title="Cancel transaction"><i class="fa-solid fa-arrow-rotate-left" aria-hidden="true"></i></button>`);
			}
			const actionHtml = actions.length
				? `<div class="agency-row-actions">${actions.join('')}</div>`
				: '<span class="agency-no-actions">No actions</span>';
			const agentName = row.agent?.name || '—';
			const agentInitials = (agentName || '??').slice(0,2).toUpperCase();
			const transactionTitle = row.transaction_type || '—';
			const transactionSub = [row.network || '—', row.phone || ''].filter(Boolean).join(' · ');
			const accountOpeningMeta = [];
			if (row.transaction_type === 'Account opening') {
				if (row.full_name) {
					accountOpeningMeta.push(agencyEscapeHtml(row.full_name));
				}
				if (row.id_type || row.id_number) {
					let idLabel = row.id_type ? agencyEscapeHtml(row.id_type) : 'ID';
					if (row.id_number) {
						idLabel += `: ${agencyEscapeHtml(row.id_number)}`;
					}
					accountOpeningMeta.push(idLabel);
				}
				if (row.nok_phone) {
					accountOpeningMeta.push(`NoK: ${agencyEscapeHtml(row.nok_phone)}`);
				}
			}
			const accountOpeningDetails = accountOpeningMeta.length ? `<div class="agency-stack-meta">${accountOpeningMeta.join(' · ')}</div>` : '';
			const amountHtml = `
				<div class="agency-stack-amount">${formatCurrency(row.amount)}</div>
				${row.charge ? `<div class="agency-stack-meta">Charge: ${formatCurrency(row.charge)}</div>` : '<div class="agency-stack-meta">Charge: —</div>'}
			`;
			return `
				<tr class="d-lg-none">
					<td colspan="7">
						<div class="row g-2 align-items-start">
							<div class="col-8">
								<div class="d-flex gap-2">
									<span class="agency-list-avatar">${agentInitials}</span>
									<div class="flex-grow-1">
										<div class="d-flex flex-wrap align-items-center gap-2">
											<span class="agency-stack-title">${transactionTitle}</span>
											<span class="${badgeClass}">${row.transaction_status || 'Unknown'}</span>
										</div>
										${reasonHtml}
										<div class="agency-stack-meta">#${row.id} · ${row.reference}</div>
										<div class="agency-stack-meta">${formatDate(row.date_created)}</div>
										<div class="agency-stack-meta">${transactionSub}</div>
										${accountOpeningDetails}
										<div class="agency-stack-meta">Agent: ${agentName}</div>
									</div>
								</div>
							</div>
							<div class="col-4 text-end">
								${amountHtml}
							</div>
							<div class="col-12 agency-mobile-actions">
								${actionHtml}
							</div>
						</div>
					</td>
				</tr>
				<tr class="d-none d-lg-table-row">
					<td><div class="d-flex flex-column"><strong>#${row.id}</strong><small class="text-muted">${row.reference}</small></div></td>
					<td>
						<div class="d-flex flex-column">
							<span>${formatDate(row.date_created)}</span>
							<small class="text-muted">${row.network || '—'}</small>
						</div>
					</td>
					<td>
						<div class="d-flex flex-column">
							<strong>${row.transaction_type || '—'}</strong>
							<small class="text-muted">${row.phone || ''}</small>
						</div>
						${accountOpeningDetails}
					</td>
					<td><span class="${badgeClass}">${row.transaction_status || 'Unknown'}</span>${reasonHtml}</td>
					<td class="text-end">
						<strong>${formatCurrency(row.amount)}</strong>
						${row.charge ? `<div><small class="text-muted">Charge: ${formatCurrency(row.charge)}</small></div>` : ''}
					</td>
					<td>
						<div class="d-flex align-items-center gap-2">
							<span class="agency-list-avatar">${agentInitials}</span>
							<div>
								<strong>${agentName}</strong>
								<small class="text-muted">ID: ${row.agent?.id || '—'}</small>
							</div>
						</div>
					</td>
					<td class="agency-actions-cell">${actionHtml}</td>
				</tr>
			`;
		}).join('');
		refs.body.innerHTML = html;
	}

	refs.body?.addEventListener('click', (event) => {
		const button = event.target.closest('button[data-agency-approve]');
		if(!button || button.disabled) return;
		const transactionId = String(button.dataset.agencyApprove || '').trim();
		if(!/^\d+$/.test(transactionId)) return;
		button.disabled = true;
		button.innerHTML = '<i class="fa fa-spinner fa-spin" aria-hidden="true"></i><span>Approving…</span>';

		const form = document.createElement('form');
		form.method = 'post';
		form.action = `${VIEW_BASE}/edit/${encodeURIComponent(transactionId)}`;
		form.hidden = true;
		[
			['csrf', approvalCsrfToken],
			['action', 'approve']
		].forEach(([name, value]) => {
			const input = document.createElement('input');
			input.type = 'hidden';
			input.name = name;
			input.value = value;
			form.appendChild(input);
		});
		document.body.appendChild(form);
		form.submit();
	});

	function renderPagination(pagination){
		const totalPages = pagination.total_pages || 1;
		const current = pagination.page || 1;
		const pages = [];

		const createItem = (page, label = page, disabled = false, active = false) => `
			<li class="page-item ${disabled ? 'disabled' : ''} ${active ? 'active' : ''}">
				<button class="page-link" data-page="${page}" ${disabled ? 'disabled' : ''}>${label}</button>
			</li>
		`;
		pages.push(createItem(current - 1, '&laquo;', current === 1));
		for(let i = 1; i <= totalPages; i++){
			if(i === 1 || i === totalPages || Math.abs(i - current) <= 1){
				pages.push(createItem(i, i, false, i === current));
			}else if(Math.abs(i - current) === 2){
				pages.push('<li class="page-item disabled"><span class="page-link">…</span></li>');
			}
		}
		pages.push(createItem(current + 1, '&raquo;', current === totalPages));
		refs.pagination.innerHTML = pages.join('');
	}

	function renderInsights(analysis = {}, totals = {}, latestActivity){
		const avg = analysis.avg_ticket || 0;
		const rate = (analysis.charge_rate || 0) * 100;
		if(refs.avgTicket) refs.avgTicket.textContent = formatCurrency(avg);
		if(refs.chargeRate) refs.chargeRate.textContent = `${rate.toFixed(1)}%`;
		if(refs.lastSyncInsight) refs.lastSyncInsight.textContent = latestActivity ? formatDate(latestActivity) : '—';

		const makeList = (target, data, formatter) => {
			if(!target) return;
			target.innerHTML = (data || []).map(formatter).join('') || '<li class="text-muted">No data</li>';
		};

		makeList(refs.topAgents, analysis.top_agents, (agent) => `
			<li class="py-2 d-flex justify-content-between">
				<div>
					<strong>${agent.name || '—'}</strong>
					<div class="text-muted small">${agent.count || 0} tx</div>
				</div>
				<div class="text-end">
					<strong>${formatCurrency(agent.total || 0)}</strong>
				</div>
			</li>
		`);

		makeList(refs.networkMix, analysis.network_breakdown, (net) => {
			const pct = ((net.percent || 0) * 100);
			const percent = Number.isFinite(pct) ? pct.toFixed(1) : '0.0';
			return `
				<li class="py-2">
					<div class="d-flex justify-content-between">
						<strong>${net.label || '—'}</strong>
						<span>${percent}%</span>
					</div>
					<div class="agency-progress mt-1"><span style="width:${percent}%"></span></div>
					<div class="text-muted small">${formatCurrency(net.amount || 0)} · ${(net.count || 0)} tx</div>
				</li>
			`;
		});

		makeList(refs.statusBreakdown, analysis.status_breakdown, (item) => `
			<li class="py-2 d-flex justify-content-between">
				<span>${item.label || '—'}</span>
				<div class="text-end">
					<strong>${item.count || 0}</strong>
					<div class="text-muted small">${formatCurrency(item.amount || 0)}</div>
				</div>
			</li>
		`);

		if(refs.trend){
			const trend = analysis.trend || [];
			refs.trend.innerHTML = (trend || []).map(day => {
				const date = day.date || day.label || '—';
				const volume = day.amount ?? day.total ?? day.volume ?? 0;
				const count = day.count ?? day.transactions ?? 0;
				return `
					<tr>
						<td>${date}</td>
						<td>${formatCurrency(volume)}</td>
						<td>${count}</td>
					</tr>
				`;
			}).join('') || '<tr><td colspan="3" class="text-muted">No trend data</td></tr>';
		}

		if(refs.statVolume) refs.statVolume.textContent = formatCurrency(totals.total_amount || 0);
		if(refs.statCharge) refs.statCharge.textContent = formatCurrency(totals.total_charge || 0);
		if(refs.statTx) refs.statTx.textContent = `${totals.count || 0} transactions`;
		if(refs.latestActivity) refs.latestActivity.textContent = latestActivity ? formatDate(latestActivity) : 'Awaiting sync';
	}

	let activeAbortController = null;
	let activeRequestId = 0;

	async function fetchData(){
		const requestId = ++activeRequestId;
		if(activeAbortController) activeAbortController.abort();
		activeAbortController = new AbortController();

		if(refs.error) refs.error.classList.add('d-none');
		if(refs.empty) refs.empty.classList.add('d-none');
		toggleLoader(true);
		const params = new URLSearchParams({
			page: state.page,
			limit: state.limit,
			find: state.filters.find || '',
			date_from: state.filters.date_from || '',
			date_to: state.filters.date_to || '',
			created_by: state.filters.created_by || '',
			transaction_type: state.filters.transaction_type || '',
			transaction_status: state.filters.transaction_status || '',
			network: state.filters.network || ''
		});

		try {
			const response = await fetch(`${DATA_ENDPOINT}?${params.toString()}`, {
				credentials: 'include',
				headers: { 'X-Requested-With': 'XMLHttpRequest' },
				signal: activeAbortController.signal
			});
			const text = await response.text();
			let payload;
			try {
				payload = JSON.parse(text);
			} catch (parseErr) {
				throw new Error('Unexpected response from server. Please refresh and ensure you are logged in.');
			}
			if(!payload.success){
				throw new Error('Failed to load data');
			}
			if(requestId !== activeRequestId) return;
			if(refs.resultCount) refs.resultCount.textContent = payload.rows.length;
			if(refs.totalCount) refs.totalCount.textContent = payload.pagination.total_rows || 0;
			if(refs.lastSync) refs.lastSync.textContent = payload.latest_activity ? formatDate(payload.latest_activity) : '—';
			renderRows(payload.rows);
			renderPagination(payload.pagination);
			renderInsights(payload.analysis, payload.totals, payload.latest_activity);
		} catch (error) {
			if(error?.name === 'AbortError') return;
			if(refs.error){
				refs.error.textContent = error.message || 'Unable to load data';
				refs.error.classList.remove('d-none');
			}
			renderRows([]);
		} finally {
			if(requestId === activeRequestId) toggleLoader(false);
		}
	}

	refs.form?.addEventListener('submit', (event) => {
		event.preventDefault();
		applyFiltersAndFetch(true);
	});

	refs.reset?.addEventListener('click', () => {
		if(refs.search) refs.search.value = '';
		if(refs.limit) refs.limit.value = '10';

		refs.filterOptions?.forEach(option => {
			option.checked = false;
			activeFilters.delete(option.dataset.filterOption);
		});
		if(refs.filterFields) refs.filterFields.innerHTML = '';
		Object.values(FILTER_DEFINITIONS).forEach(def => def.clear?.());
		updateFilterEmptyState();
		state.page = 1;
		state.limit = 10;
		state.filters = { find: '', date_from: '', date_to: '', created_by: '', transaction_type: '', transaction_status: '', network: '' };
		try {
			window.history.replaceState({}, '', window.location.pathname);
		} catch (e) {
			// ignore
		}
		fetchData();
	});

	refs.pagination?.addEventListener('click', (event) => {
		const button = event.target.closest('button[data-page]');
		if(!button || button.disabled) return;
		const nextPage = parseInt(button.getAttribute('data-page'), 10);
		if(Number.isNaN(nextPage) || nextPage < 1) return;
		state.page = nextPage;
		fetchData();
	});

	window.exportPdf = function(){
		const btn = document.querySelector('.export-pdf-btn');
		if(btn) btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Generating...';
		window.open('<?=ROOT?>/<?=$adminRoute?>/<?=$pluginRoute?>/exportList_pdf', 'pdf_window', 'width=900,height=650');
		setTimeout(() => {
			const btn2 = document.querySelector('.export-pdf-btn');
			if(btn2) btn2.innerHTML = '<i class="fa fa-file-pdf"></i> Export PDF';
		}, 4000);
	};

	fetchData();
})();
</script>
<div class="d-none" data-direct-cancel-csrf><?=csrf()?></div>
<script src="<?=ROOT?>/plugins/reversals/assets/direct-cancel.js"></script>

<?php else: ?>
	<div class="alert alert-danger text-center">
		Access denied. You dont have permission for this action
	</div>
<?php endif ?>
