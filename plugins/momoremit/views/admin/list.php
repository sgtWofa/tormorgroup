<?php if(user_can('view_momoremit')):?>

<?php
	$adminRoute = $admin_route ?? 'admin';
	$pluginRoute = $plugin_route ?? 'momoremit';
	$canViewDetails = user_can('view_category_details');
	$agentOptions = [];
	$transactionTypes = [];
	$networkOptions = [];
	try {
		$agentModel = new \Momoremits\User;
		$agentOptions = $agentModel->query("SELECT id, first_name, last_name FROM users WHERE deleted = 0 ORDER BY first_name ASC LIMIT 200");
		if (!$agentOptions || !is_array($agentOptions)) {
			$agentOptions = [];
		}
	} catch (\Throwable $th) {
		$agentOptions = [];
	}

	try {
		$db = new \Core\Database;
		$transactionTypes = $db->query("SELECT DISTINCT transaction_type FROM momoremits WHERE transaction_type IS NOT NULL AND transaction_type <> '' ORDER BY transaction_type ASC LIMIT 20");
		$networkOptions = $db->query("SELECT DISTINCT network FROM momoremits WHERE network IS NOT NULL AND network <> '' ORDER BY network ASC LIMIT 100");
		if (!$transactionTypes || !is_array($transactionTypes)) {
			$transactionTypes = [];
		}
		if (!$networkOptions || !is_array($networkOptions)) {
			$networkOptions = [];
		}
	} catch (\Throwable $th) {
		$transactionTypes = [];
	}
?>

<style>
	.momoremit-page {
		background: var(--bs-body-bg);
		color: var(--bs-body-color);
	}

	.momoremit-hero {
		border-radius: 1.25rem;
		padding: 2rem;
		border: 1px solid var(--bs-border-color);
		background: linear-gradient(135deg, rgba(var(--bs-primary-rgb, 13,110,253),0.12), rgba(var(--bs-warning-rgb, 255,193,7),0.12));
		box-shadow: 0 25px 45px rgba(15, 23, 42, 0.08);
	}

	.momoremit-actions .btn,
	.momoremit-actions .dropdown-toggle {
		border-radius: 999px;
		font-weight: 500;
	}

	.momoremit-stat-card {
		border-radius: 1rem;
		padding: 1.25rem;
		background: var(--bs-body-bg);
		border: 1px solid rgba(15, 23, 42, 0.08);
		box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08);
		min-height: 150px;
	}

	.momoremit-stat-card h3,
	.momoremit-stat-card h4 {
		font-weight: 600;
		margin-bottom: .35rem;
	}

	.momoremit-chip-list {
		display: flex;
		flex-wrap: wrap;
		gap: 0.4rem;
	}

	.momoremit-chip {
		border-radius: 999px;
		padding: 0.35rem 1rem;
		border: 1px dashed rgba(var(--bs-primary-rgb,13,110,253), 0.4);
		font-size: 0.85rem;
		font-weight: 500;
	}

	.momoremit-chip.muted {
		border-color: var(--bs-border-color);
		color: var(--bs-secondary-color, #6c757d);
	}

	.momoremit-filters label {
		font-size: 0.78rem;
		text-transform: uppercase;
		letter-spacing: 0.08em;
		font-weight: 600;
	}

	.momoremit-tab-card {
		border-radius: 1.25rem;
		border: 1px solid var(--bs-border-color);
		box-shadow: 0 25px 45px rgba(15, 23, 42, 0.08);
	}

	.active,
	#momoremitTabs .nav-link.active,
	#momoremitTabs .nav-link.active:focus,
	#momoremitTabs .nav-link.active:hover,
	#momoremitTabs .show > .nav-link,
	#momoremitTabs .show > .nav-link:focus,
	#momoremitTabs .show > .nav-link:hover {
		background-image: linear-gradient(135deg, rgba(var(--bs-primary-rgb, 13,110,253),0.12), rgba(var(--bs-warning-rgb, 255,193,7),0.12)) !important;
		background-color: transparent !important;
		color: var(--bs-body-color) !important;
		border-color: rgba(var(--bs-primary-rgb,13,110,253), 0.25) !important;
	}

	.momoremit-table thead th {
		text-transform: uppercase;
		font-size: 0.75rem;
		letter-spacing: 0.08em;
		border-top: 0;
	}

	.momoremit-table tbody td {
		vertical-align: middle;
		border-color: rgba(148, 163, 184, 0.25);
	}

	.momoremit-status-badge {
		display: inline-flex;
		align-items: center;
		border-radius: 999px;
		padding: 0.2rem 0.65rem;
		font-size: 0.72rem;
		font-weight: 700;
		letter-spacing: 0.04em;
		border: 1px solid transparent;
	}

	.momoremit-status-approved {
		background: rgba(25, 135, 84, 0.18);
		color: #7ee2a8;
		border-color: rgba(25, 135, 84, 0.35);
	}

	.momoremit-status-pending {
		background: rgba(255, 193, 7, 0.16);
		color: #ffd666;
		border-color: rgba(255, 193, 7, 0.32);
	}

	.momoremit-status-declined,
	.momoremit-status-rejected {
		background: rgba(220, 53, 69, 0.16);
		color: #ff9aa5;
		border-color: rgba(220, 53, 69, 0.32);
	}

	.momoremit-transaction-meta {
		display: flex;
		flex-wrap: wrap;
		gap: 0.45rem;
		margin-top: 0.45rem;
	}

	.momoremit-reason {
		margin-top: 0.35rem;
		max-width: 24rem;
	}

	.momoremit-balance-empty {
		color: var(--bs-secondary-color, #6c757d);
	}

	.momoremit-loader {
		position: absolute;
		top: 0;
		left: 0;
		right: 0;
		bottom: 0;
		display: flex;
		align-items: center;
		justify-content: center;
		background: rgba(255,255,255,0.8);
		backdrop-filter: blur(3px);
		z-index: 3;
		transition: opacity .2s ease;
	}

	.momoremit-loader.hidden {
		opacity: 0;
		pointer-events: none;
	}

	.momoremit-empty,
	.momoremit-error {
		padding: 3rem 1rem;
		text-align: center;
	}

	.momoremit-error {
		color: var(--bs-danger);
	}

	.momoremit-avatar,
	.momoremit-list-avatar {
		width: 40px;
		height: 40px;
		border-radius: 999px;
		object-fit: cover;
	}

	.momoremit-list-avatar {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		background: rgba(var(--bs-warning-rgb,255,193,7),0.2);
		color: var(--bs-warning);
		font-weight: 600;
	}

	.momoremit-analysis-card {
		border-radius: 1rem;
		border: 1px solid var(--bs-border-color);
		padding: 1.25rem;
		height: 100%;
	}

	.momoremit-progress {
		height: 8px;
		border-radius: 999px;
		background: rgba(var(--bs-primary-rgb,13,110,253),0.1);
		overflow: hidden;
	}

	.momoremit-progress span {
		display: block;
		height: 100%;
		background: var(--bs-primary);
	}

	.momoremit-pulse {
		display: inline-flex;
		align-items: center;
		gap: 0.35rem;
		font-size: 0.85rem;
		color: var(--bs-success);
	}

	.momoremit-pulse::before {
		content: "";
		width: 8px;
		height: 8px;
		border-radius: 999px;
		background: currentColor;
		animation: momoremitPulse 1.6s infinite;
	}

	@keyframes momoremitPulse {
		0% { opacity: 0.2; transform: scale(0.8); }
		50% { opacity: 1; transform: scale(1.1); }
		100% { opacity: 0.2; transform: scale(0.8); }
	}

	.momoremit-insights-pane {
		background: var(--bs-white);
		border-radius: 1.25rem;
		padding: 1.5rem;
	}

	body[data-bs-theme="dark"] .momoremit-insights-pane {
		background: rgba(15, 23, 42, 0.95);
		box-shadow: inset 0 0 0 1px rgba(255,255,255,0.05);
	}

	.momoremit-amount-stack {
		display: flex;
		flex-direction: column;
		gap: .5rem;
	}

	.momoremit-mobile-details .momoremit-refline {
		font-weight: 600;
	}

	.momoremit-mobile-agent{
		display:flex;
		align-items:center;
		gap:.5rem;
	}

	.momoremit-filters-toggle{
		display:flex;
		flex-wrap:wrap;
		gap:.75rem;
		align-items:flex-end;
		justify-content:space-between;
	}

	.momoremit-filters-search{
		flex: 1 1 280px;
		min-width: 240px;
	}

	.momoremit-filter-btn{
		min-width: 44px;
		height: 44px;
		display:inline-flex;
		align-items:center;
		justify-content:center;
		border-radius: 999px;
	}

	.momoremit-filters-top{
		display:flex;
		flex-wrap:wrap;
		gap: .75rem;
		align-items:center;
		justify-content:center;
	}

	@media (max-width: 992px) {
		.momoremit-filters-toggle{
			display:flex;
			align-items:flex-end;
			justify-content:space-between;
			gap:.75rem;
		}
		#momoremitFiltersBody{
			display:none;
		}

		#momoremitFiltersBody.show{
			display:block;
		}
	}

	@media (min-width:768px) and (max-width:991.98px){
		.momoremit-stat-card{padding:1.25rem;}
		.momoremit-stat-card h3{font-size:1.35rem;}
	}
</style>

<div class="momoremit-page">
	<div class="momoremit-hero mb-4">
		<div class="row align-items-center g-3">
			<div class="col-lg-8">
				<p class="text-uppercase text-muted mb-1 small fw-semibold">Momoremit overview</p>
				<h1 class="mb-2">Transactions dashboard</h1>
				<p class="mb-0 text-secondary">Monitor momo remit inflows, charges, and agent performance with a refreshed, responsive layout.</p>
			</div>
			<div class="col-lg-4 text-lg-end">
				<div class="momoremit-actions d-flex flex-wrap justify-content-lg-end gap-2">
					<div class="dropdown">
						<button class="btn btn-warning text-dark dropdown-toggle" type="button" id="addDropdown" data-bs-toggle="dropdown" aria-expanded="false">
							<i class="fa fa-plus me-1"></i> Add
						</button>
						<ul class="dropdown-menu" aria-labelledby="addDropdown">
							<?php if(user_can('view_momoremit')):?>
								<li><a class="dropdown-item" href="<?=ROOT?>/<?=$adminRoute?>/<?=$pluginRoute?>/withdrawal"><i class="fa fa-arrow-up text-warning me-2"></i>Withdrawal</a></li>
								<li><a class="dropdown-item" href="<?=ROOT?>/<?=$adminRoute?>/<?=$pluginRoute?>/cashin"><i class="fa fa-paper-plane text-warning me-2"></i>Cash in</a></li>
								<li><a class="dropdown-item" href="<?=ROOT?>/<?=$adminRoute?>/<?=$pluginRoute?>/cit"><i class="fa fa-user-edit text-warning me-2"></i>CIT</a></li>
							<?php endif?>
						</ul>
					</div>
					<button id="export-pdf-btn" class="btn btn-outline-danger export-pdf-btn" onclick="exportPdf()">
						<i class="fa fa-file-pdf me-2"></i> Export PDF
					</button>
					<?php if(user_can('is_admin')): ?>
						<a class="btn btn-outline-primary" href="<?=ROOT?>/<?=$adminRoute?>/<?=$pluginRoute?>/limits">
							<i class="fa fa-sliders me-2"></i> Transaction limits
						</a>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<div class="row g-3 mt-1">
			<div class="col-12 col-sm-6 col-lg-4">
				<div class="momoremit-stat-card h-100">
					<p class="text-uppercase text-muted small mb-1">Total Volume</p>
					<h3 class="text-primary mb-2" data-total-volume>GHS 0.00</h3>
					<small class="text-muted" data-tx-count>0 transactions</small>
				</div>
			</div>
			<div class="col-12 col-sm-6 col-lg-4">
				<div class="momoremit-stat-card h-100">
					<p class="text-uppercase text-muted small mb-1">Total Charges</p>
					<h3 class="text-warning mb-2" data-total-charge>GHS 0.00</h3>
					<small class="text-muted">Service fees collected</small>
				</div>
			</div>
			<div class="col-12 col-lg-4">
				<div class="momoremit-stat-card h-100">
					<p class="text-uppercase text-muted small mb-1">Latest activity</p>
					<h3 class="mb-2" data-latest-activity>Awaiting sync</h3>
					<span class="momoremit-pulse">Live</span>
				</div>
			</div>
		</div>
	</div>

	<div class="card border-0 shadow-sm mt-4 momoremit-filters">
		<div class="card-body">
			<form id="momoremitFilters" class="row g-3 align-items-end">
				<div class="col-12">
					<div class="momoremit-filters-toggle">
						<div class="momoremit-filters-search">
							<label class="form-label">Search</label>
							<div class="input-group rounded-pill overflow-hidden border">
								<span class="input-group-text bg-transparent border-0 ps-3"><i class="fa fa-search text-muted"></i></span>
								<input type="text" class="form-control border-0" id="momoremitSearch" placeholder="Name, phone, wallet, ref">
								<button type="button" class="btn btn-warning text-dark momoremit-filter-btn d-none" id="momoremitToggleFilters" aria-label="Filters">
									<i class="fa fa-filter"></i>
								</button>
							</div>
						</div>
						<div class="d-flex gap-2 flex-wrap justify-content-end align-items-end">
							<div>
								<label class="form-label">Per page</label>
								<select class="form-select w-auto" id="momoremitLimit">
									<option value="10">10</option>
									<option value="25">25</option>
									<option value="50">50</option>
									<option value="100">100</option>
								</select>
							</div>
							<button type="button" class="btn btn-outline-secondary" id="momoremitResetFilters">Reset</button>
							<button type="submit" class="btn btn-primary d-none d-lg-inline-block">Apply</button>
						</div>
					</div>
				</div>

				<div class="col-12 d-none d-lg-block" id="momoremitFiltersBody">
					<div class="row g-3">
						<div class="col-12 col-sm-6 col-lg-3">
							<label class="form-label">Agent search</label>
							<input type="search" class="form-control" id="momoremitAgentSearch" placeholder="Agent name or ID">
						</div>
						<div class="col-12 col-sm-6 col-lg-3">
							<label class="form-label">Network</label>
							<select class="form-select" id="momoremitNetwork">
								<option value="">All</option>
								<?php foreach($networkOptions as $network): ?>
									<option value="<?=esc($network->network)?>"><?=esc(strtoupper((string)$network->network))?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="col-12 col-sm-6 col-lg-3">
							<label class="form-label">Date From</label>
							<input type="date" class="form-control" id="momoremitDateFrom">
						</div>
						<div class="col-12 col-sm-6 col-lg-3">
							<label class="form-label">Date To</label>
							<input type="date" class="form-control" id="momoremitDateTo">
						</div>
						<div class="col-12 col-sm-6 col-lg-3">
							<label class="form-label">Created By</label>
							<select class="form-select" id="momoremitCreatedBy">
								<option value="">All</option>
								<?php foreach($agentOptions as $agent): ?>
									<option value="<?=htmlspecialchars($agent->id)?>"><?=htmlspecialchars(trim(($agent->first_name ?? '') . ' ' . ($agent->last_name ?? '')))?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="col-12 col-sm-6 col-lg-3">
							<label class="form-label">Transaction Type</label>
							<select class="form-select" id="momoremitTransactionType">
								<option value="">All</option>
								<?php foreach($transactionTypes as $type): ?>
									<option value="<?=htmlspecialchars($type->transaction_type)?>"><?=htmlspecialchars($type->transaction_type)?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
				</div>
			</form>
		</div>
	</div>

	<ul class="nav nav-tabs mt-4" id="momoremitTabs" role="tablist">
		<li class="nav-item" role="presentation">
			<button class="nav-link active" id="momoremit-live-tab" data-bs-toggle="tab" data-bs-target="#momoremitLive" type="button" role="tab" aria-controls="momoremitLive" aria-selected="true">Live transactions</button>
		</li>
		<li class="nav-item" role="presentation">
			<button class="nav-link" id="momoremit-insights-tab" data-bs-toggle="tab" data-bs-target="#momoremitInsights" type="button" role="tab" aria-controls="momoremitInsights" aria-selected="false">Insights & analysis</button>
		</li>
		<?php if(user_can('view_momoremit_commissions') || user_can('view_momoremit') || user_can('view_momoremit_details')): ?>
		<li class="nav-item" role="presentation">
			<a class="nav-link" href="<?=esc(ROOT . '/' . $admin_route . '/' . $plugin_route . '/cashout-commissions')?>" role="tab">💰 Cashout Commissions</a>
		</li>
		<?php endif; ?>
	</ul>

	<div class="tab-content mt-3">
		<div class="tab-pane fade show active" id="momoremitLive" role="tabpanel" aria-labelledby="momoremit-live-tab">
			<div class="momoremit-tab-card position-relative">
				<div class="card-header bg-transparent border-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
					<div>
						<p class="mb-0 text-muted small">Showing <span data-result-count>0</span> / <span data-total-count>0</span> transactions</p>
						<small class="text-muted">Updated <span data-last-sync>--</span></small>
					</div>
					<div class="momoremit-chip-list">
						<span class="momoremit-chip">Async</span>
						<span class="momoremit-chip muted">No reloads</span>
					</div>
				</div>
				<div class="position-relative">
					<div class="momoremit-loader hidden" id="momoremitTableLoader">
						<div class="text-center">
							<div class="spinner-border text-primary mb-2" role="status"></div>
							<p class="text-muted mb-0">Fetching latest data...</p>
						</div>
					</div>
					<div class="table-responsive">
						<table class="table align-middle momoremit-table mb-0">
							<thead>
								<tr>
									<th class="d-none d-lg-table-cell"># / Ref</th>
									<th class="d-none d-lg-table-cell">Date</th>
									<th class="d-none d-lg-table-cell">Transaction</th>
									<th class="text-end d-none d-lg-table-cell">Amounts</th>
									<th class="text-end d-none d-lg-table-cell">Charge</th>
									<th class="text-end d-none d-lg-table-cell">Balance after</th>
									<th class="d-none d-lg-table-cell">Agent</th>
									<th class="d-lg-none">Details</th>
									<th class="text-end d-lg-none">Amounts</th>
									<th class="text-end">Actions</th>
								</tr>
							</thead>
							<tbody id="momoremitTableBody"></tbody>
						</table>
					</div>
					<div class="momoremit-empty text-muted d-none" id="momoremitEmpty">
						<i class="fa fa-box-open fa-2x mb-2"></i>
						<p class="mb-0">No transactions match the current filters.</p>
					</div>
					<div class="momoremit-error text-danger d-none" id="momoremitError"></div>
				</div>
				<div class="card-footer bg-transparent border-0">
					<nav aria-label="Momoremit pagination">
						<ul class="pagination justify-content-center mb-0" id="momoremitPagination"></ul>
					</nav>
				</div>
			</div>
		</div>
		<div class="tab-pane fade momoremit-insights-pane" id="momoremitInsights" role="tabpanel" aria-labelledby="momoremit-insights-tab">
			<div class="row g-3">
				<div class="col-lg-4">
					<div class="momoremit-analysis-card h-100">
						<p class="text-uppercase text-muted small mb-1">Avg ticket</p>
						<h4 data-avg-ticket>GHS 0.00</h4>
						<p class="text-muted mb-0">Average momo remit amount per transaction.</p>
					</div>
				</div>
				<div class="col-lg-4">
					<div class="momoremit-analysis-card h-100">
						<p class="text-uppercase text-muted small mb-1">Charge rate</p>
						<h4 data-charge-rate>0%</h4>
						<p class="text-muted mb-0">Charges as a share of total volume.</p>
					</div>
				</div>
				<div class="col-lg-4">
					<div class="momoremit-analysis-card h-100">
						<p class="text-uppercase text-muted small mb-1">Latest sync</p>
						<h4 data-last-sync-insight>--</h4>
						<p class="text-muted mb-0">Timestamp returned by the endpoint.</p>
					</div>
				</div>
			</div>
			<div class="row g-3 mt-1">
				<div class="col-xl-6">
					<div class="momoremit-analysis-card h-100">
						<div class="d-flex justify-content-between align-items-center mb-3">
							<h6 class="mb-0 text-uppercase text-muted">Top agents</h6>
							<small class="text-muted">Volume & counts</small>
						</div>
						<ul class="list-group list-group-flush" id="momoremitTopAgents"></ul>
					</div>
				</div>
				<div class="col-xl-6">
					<div class="momoremit-analysis-card h-100">
						<h6 class="text-uppercase text-muted mb-3">Network mix</h6>
						<div id="momoremitNetwork"></div>
					</div>
				</div>
			</div>
			<div class="row g-3 mt-1">
				<div class="col-12">
					<div class="momoremit-analysis-card">
						<h6 class="text-uppercase text-muted mb-3">7-day trend</h6>
						<div id="momoremitTrend" class="row g-3"></div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<?php if(user_can('is_admin')): ?>
<div class="modal fade" id="rejectTransactionModal" tabindex="-1" aria-labelledby="rejectTransactionModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
	<div class="modal-dialog">
		<form method="post" id="rejectTransactionForm" class="modal-content">
			<?=csrf()?>
			<input type="hidden" name="reject_only" value="1">
			<input type="hidden" name="transaction_status" value="Rejected">
			<div class="modal-header">
				<h5 class="modal-title" id="rejectTransactionModalLabel">Reject Transaction</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<p id="rejectTransactionPrompt">Rejecting this transaction will reverse its accounting impacts and mark its linked transaction record as deleted. This action cannot be undone here.</p>
				<div class="alert alert-danger d-none" id="rejectTransactionError" role="alert"></div>
				<div class="d-none align-items-center gap-2 text-primary py-2" id="rejectTransactionProgress" role="status" aria-live="polite">
					<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
					<span>Checking balances and processing rejection. Please wait...</span>
				</div>
				<label for="reject_transaction_reason" class="form-label">Rejection reason</label>
				<textarea name="rejection_reason" id="reject_transaction_reason" class="form-control" rows="3" required></textarea>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
				<button type="submit" class="btn btn-danger">Confirm Rejection</button>
			</div>
		</form>
	</div>
</div>
<?php endif; ?>

<script>
	function exportPdf() {
		const btn = document.querySelector('.export-pdf-btn');
		if(!btn) return;
		btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Generating...';

		const params = new URLSearchParams(window.location.search);
		const exportUrl = new URL('<?= ROOT ?>/<?=$adminRoute?>/<?=$pluginRoute?>/exportList_pdf', window.location.origin);
		const currentState = {
			find: params.get('find') || '',
			agent_search: params.get('agent_search') || '',
			network: params.get('network') || '',
			date_from: params.get('date_from') || '',
			date_to: params.get('date_to') || '',
			created_by: params.get('created_by') || '',
			transaction_type: params.get('transaction_type') || ''
		};

		if(currentState.find) exportUrl.searchParams.set('find', currentState.find);
		if(currentState.agent_search) exportUrl.searchParams.set('agent_search', currentState.agent_search);
		if(currentState.network) exportUrl.searchParams.set('network', currentState.network);
		if(currentState.date_from) exportUrl.searchParams.set('date_from', currentState.date_from);
		if(currentState.date_to) exportUrl.searchParams.set('date_to', currentState.date_to);
		if(currentState.created_by) exportUrl.searchParams.set('created_by', currentState.created_by);
		if(currentState.transaction_type) exportUrl.searchParams.set('transaction_type', currentState.transaction_type);

		window.open(exportUrl.toString(),'pdf_window','width=900,height=700');
		setTimeout(() => {
			btn.innerHTML = '<i class="fa fa-file-pdf"></i> Export PDF';
		}, 4000);
	}

	(function(){
		const endpoint = '<?=ROOT?>/<?=$adminRoute?>/<?=$pluginRoute?>/data';
		const canViewDetails = <?= $canViewDetails ? 'true' : 'false' ?>;
		const canEditTransactions = <?= user_can('edit_momoremit') ? 'true' : 'false' ?>;
		const canRejectTransactions = <?= user_can('is_admin') ? 'true' : 'false' ?>;
		const viewBaseUrl = '<?=ROOT?>/<?=$adminRoute?>/<?=$pluginRoute?>/view/';
		const editBaseUrl = '<?=ROOT?>/<?=$adminRoute?>/<?=$pluginRoute?>/edit/';
		const rejectForm = document.getElementById('rejectTransactionForm');
		const rejectReason = document.getElementById('reject_transaction_reason');
		const rejectPrompt = document.getElementById('rejectTransactionPrompt');
		const rejectError = document.getElementById('rejectTransactionError');
		const rejectProgress = document.getElementById('rejectTransactionProgress');
		const rejectSubmitButton = rejectForm?.querySelector('button[type="submit"]');
		const rejectModal = document.getElementById('rejectTransactionModal');

		const refs = {
			totalVolume: document.querySelector('[data-total-volume]'),
			txCount: document.querySelector('[data-tx-count]'),
			totalCharge: document.querySelector('[data-total-charge]'),
			latestActivity: document.querySelector('[data-latest-activity]'),
			lastSync: document.querySelector('[data-last-sync]'),
			lastSyncInsight: document.querySelector('[data-last-sync-insight]'),
			resultCount: document.querySelector('[data-result-count]'),
			totalCount: document.querySelector('[data-total-count]'),
			tableBody: document.getElementById('momoremitTableBody'),
			emptyState: document.getElementById('momoremitEmpty'),
			errorState: document.getElementById('momoremitError'),
			loader: document.getElementById('momoremitTableLoader'),
			pagination: document.getElementById('momoremitPagination'),
			avgTicket: document.querySelector('[data-avg-ticket]'),
			chargeRate: document.querySelector('[data-charge-rate]'),
			topAgents: document.getElementById('momoremitTopAgents'),
			network: document.getElementById('momoremitNetwork'),
			trend: document.getElementById('momoremitTrend')
		};

		const inputs = {
			form: document.getElementById('momoremitFilters'),
			search: document.getElementById('momoremitSearch'),
			agentSearch: document.getElementById('momoremitAgentSearch'),
			network: document.getElementById('momoremitNetwork'),
			dateFrom: document.getElementById('momoremitDateFrom'),
			dateTo: document.getElementById('momoremitDateTo'),
			createdBy: document.getElementById('momoremitCreatedBy'),
			transactionType: document.getElementById('momoremitTransactionType'),
			limit: document.getElementById('momoremitLimit'),
			reset: document.getElementById('momoremitResetFilters'),
			toggleFilters: document.getElementById('momoremitToggleFilters'),
			filtersBody: document.getElementById('momoremitFiltersBody')
		};

		const isMobile = () => {
			return window.matchMedia && window.matchMedia('(max-width: 992px)').matches;
		};

		const setFiltersOpen = (open) => {
			if(!inputs.filtersBody || !inputs.toggleFilters) return;
			inputs.filtersBody.classList.toggle('show', !!open);
			inputs.toggleFilters.setAttribute('aria-expanded', open ? 'true' : 'false');
			inputs.toggleFilters.classList.toggle('btn-dark', !!open);
			inputs.toggleFilters.classList.toggle('text-white', !!open);
			inputs.toggleFilters.classList.toggle('btn-warning', !open);
			inputs.toggleFilters.classList.toggle('text-dark', !open);
		};

		if(inputs.toggleFilters && inputs.filtersBody){
			inputs.toggleFilters.setAttribute('aria-controls', 'momoremitFiltersBody');
			inputs.toggleFilters.setAttribute('aria-expanded', 'false');
			inputs.toggleFilters.addEventListener('click', () => {
				setFiltersOpen(!inputs.filtersBody.classList.contains('show'));
			});
		}

		const state = {
			page: 1,
			limit: 10,
			find: '',
			agent_search: '',
			network: '',
			date_from: '',
			date_to: '',
			created_by: '',
			transaction_type: ''
		};

		let activeController = null;
		const currencyFormatter = new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
		const numberFormatter = new Intl.NumberFormat();

		const debounce = (fn, wait = 350) => {
			let timeout;
			return (...args) => {
				clearTimeout(timeout);
				timeout = setTimeout(() => fn.apply(null, args), wait);
			};
		};

		const escapeHtml = (value = '') => {
			if(value === null || value === undefined){
				return '';
			}
			return value
				.toString()
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;')
				.replace(/'/g, '&#39;');
		};

		const formatCurrency = (value = 0) => `${currencyFormatter.format(Number(value) || 0)}`;
		const formatOptionalCurrency = (value) => {
			if(value === null || value === undefined || value === ''){
				return '';
			}
			const numeric = Number(value);
			return Number.isFinite(numeric) ? formatCurrency(numeric) : '';
		};
		const renderOptionalAmountBlock = (label, value, textClass) => {
			const formatted = formatOptionalCurrency(value);
			if(!formatted){
				return '';
			}
			return `
				<div>
					<small class="text-muted d-block">${escapeHtml(label)}</small>
					<span class="fw-semibold ${textClass}">${formatted}</span>
				</div>
			`;
		};
		const normalizeStatus = (value = '') => {
			const normalized = String(value || '').trim().toLowerCase();
			if(normalized === 'approved') return 'Approved';
			if(normalized === 'declined') return 'Declined';
			if(normalized === 'rejected') return 'Rejected';
			if(normalized === 'reversed') return 'Reversed';
			if(normalized === 'pending') return 'Pending';
			return 'Approved';
		};
		const renderStatusBadge = (value) => {
			const status = normalizeStatus(value);
			const classMap = {
				Approved: 'momoremit-status-approved',
				Pending: 'momoremit-status-pending',
				Declined: 'momoremit-status-declined',
				Rejected: 'momoremit-status-rejected',
				Reversed: 'momoremit-status-rejected'
			};
			return `<span class="momoremit-status-badge ${classMap[status] || classMap.Pending}">${escapeHtml(status)}</span>`;
		};
		const renderBalanceAfter = (value, className = 'fw-semibold') => {
			const formatted = formatOptionalCurrency(value);
			return formatted
				? `<span class="${escapeHtml(className)}">${formatted}</span>`
				: '<span class="momoremit-balance-empty">&mdash;</span>';
		};
		const formatPercent = (value = 0) => `${(Number(value) * 100).toFixed(1)}%`;
		const formatDateTime = (value) => {
			if(!value) return 'No activity yet';
			const parsed = new Date(value);
			return Number.isNaN(parsed.getTime()) ? value : parsed.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
		};

		const syncUrl = () => {
			const params = new URLSearchParams();
			if(state.find) params.set('find', state.find);
			if(state.agent_search) params.set('agent_search', state.agent_search);
			if(state.network) params.set('network', state.network);
			if(state.date_from) params.set('date_from', state.date_from);
			if(state.date_to) params.set('date_to', state.date_to);
			if(state.created_by) params.set('created_by', state.created_by);
			if(state.transaction_type) params.set('transaction_type', state.transaction_type);
			if(state.page > 1) params.set('page', state.page);
			if(state.limit !== 10) params.set('limit', state.limit);

			const query = params.toString();
			const newUrl = query ? `${location.pathname}?${query}` : location.pathname;
			window.history.replaceState({}, '', newUrl);
		};

		const hydrateFromUrl = () => {
			const params = new URLSearchParams(window.location.search);
			state.find = params.get('find') || '';
			state.agent_search = params.get('agent_search') || '';
			state.network = params.get('network') || '';
			state.date_from = params.get('date_from') || '';
			state.date_to = params.get('date_to') || '';
			state.created_by = params.get('created_by') || '';
			state.transaction_type = params.get('transaction_type') || '';
			state.page = parseInt(params.get('page') || '1', 10) || 1;
			state.limit = parseInt(params.get('limit') || state.limit, 10);

			if(inputs.search) inputs.search.value = state.find;
			if(inputs.agentSearch) inputs.agentSearch.value = state.agent_search;
			if(inputs.network) inputs.network.value = state.network;
			if(inputs.dateFrom) inputs.dateFrom.value = state.date_from;
			if(inputs.dateTo) inputs.dateTo.value = state.date_to;
			if(inputs.createdBy) inputs.createdBy.value = state.created_by;
			if(inputs.transactionType) inputs.transactionType.value = state.transaction_type;
			if(inputs.limit) inputs.limit.value = state.limit;
		};

		const setLoading = (isLoading) => {
			if(refs.loader) refs.loader.classList.toggle('hidden', !isLoading);
			if(refs.errorState) refs.errorState.classList.add('d-none');
		};

		const showError = (message) => {
			if(!refs.errorState) return;
			refs.errorState.textContent = message || 'Something went wrong while loading data.';
			refs.errorState.classList.remove('d-none');
		};

		const renderRows = (rows = []) => {
			if(!refs.tableBody || !refs.emptyState) return;
			if(!rows.length){
				refs.tableBody.innerHTML = '';
				refs.emptyState.classList.remove('d-none');
				return;
			}
			refs.emptyState.classList.add('d-none');
			refs.tableBody.innerHTML = rows.map((row, index) => {
				const normalizedStatus = normalizeStatus(row.transaction_status);
				const agentName = escapeHtml(row.agent?.name || 'N/A');
				const agentImage = row.agent?.image
					? `<img src="${escapeHtml(row.agent.image)}" class="momoremit-avatar" alt="Agent">`
					: `<span class="momoremit-list-avatar">${agentName.slice(0,2) || 'NA'}</span>`;
				const agentImageMini = row.agent?.image
					? `<img src="${escapeHtml(row.agent.image)}" alt="Agent" style="width:22px;height:22px;border-radius:999px;object-fit:cover;">`
					: `<span class="momoremit-list-avatar" style="width:22px;height:22px;font-size:.7rem;">${agentName.slice(0,2) || 'NA'}</span>`;
				const actionParts = [];
				if (canViewDetails) {
					actionParts.push(`<a href="${viewBaseUrl}${row.id}" class="btn btn-sm btn-outline-primary rounded-pill px-3"><i class="fa-solid fa-eye me-1"></i> View</a>`);
				}
				if (canEditTransactions && !['Rejected', 'Declined', 'Reversed'].includes(normalizedStatus)) {
					actionParts.push(`<a href="${editBaseUrl}${row.id}" class="btn btn-sm btn-outline-warning rounded-pill px-3"><i class="fa-solid fa-pen-to-square me-1"></i> Edit</a>`);
				}
				if (canRejectTransactions && !['Rejected', 'Declined', 'Reversed'].includes(normalizedStatus)) {
					actionParts.push(`<button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#rejectTransactionModal" data-reject-url="${editBaseUrl}${encodeURIComponent(row.id)}" data-reject-reference="${escapeHtml(row.reference || row.id || '')}"><i class="fa-solid fa-ban me-1"></i> Reject</button>`);
				}
				const actions = actionParts.join(' ');
				const refLabel = escapeHtml(row.reference || row.id || '');
				const dateLabel = formatDateTime(row.date_created);
				const transactionTitle = `${escapeHtml(row.network)} ${escapeHtml(row.transaction_type)}`;
				const transactionSub = `${escapeHtml(row.sending_category || '')} ${escapeHtml(row.phone || 'N/A')}`;
				const charginType = `${escapeHtml(row.category || '***')}`;
				const transactionStatus = renderStatusBadge(row.transaction_status);
				const rejectionReason = row.rejection_reason
					? `<div class="small text-danger momoremit-reason">Reason: ${escapeHtml(row.rejection_reason)}</div>`
					: '';
				const chargeCell = formatCurrency(row.charge);
				const mobileChargeBlock = `
							<div>
								<small class="text-muted d-block">Charge</small>
								<span class="fw-semibold text-warning">${formatCurrency(row.charge)}</span>
							</div>`;
				return `
				<tr>
					<td class="d-none d-lg-table-cell">
						<div class="d-flex align-items-center gap-2">
							<span class="badge bg-warning text-dark rounded-pill">#${((state.page - 1) * state.limit) + index + 1}</span>
							<div>
								<div class="fw-semibold">${refLabel}</div>
								<div class="d-flex align-items-center gap-2 mt-1 text-muted small d-lg-none">
									${agentImageMini}
									<span>${agentName}</span>
								</div>
							</div>
						</div>
					</td>
					<td class="d-none d-lg-table-cell"><div class="fw-semibold">${dateLabel}</div></td>
					<td class="d-none d-lg-table-cell">
						<div class="fw-semibold">${transactionTitle}</div>
						<div class="text-muted small">${transactionSub}</div>
						<div class="text-muted small">type: ${charginType}</div>
						<div class="momoremit-transaction-meta">${transactionStatus}</div>
						${rejectionReason}
					</td>
					<td class="text-end fw-semibold d-none d-lg-table-cell">
						${renderOptionalAmountBlock('Payable', row.amount_payable, 'text-success')}
						${renderOptionalAmountBlock('Sendable', row.amount_sendable, 'text-info')}
					</td>					
					<td class="text-end text-warning fw-semibold d-none d-lg-table-cell">${chargeCell}</td>
					<td class="text-end d-none d-lg-table-cell">${renderBalanceAfter(row.balance_after)}</td>
					<td class="d-none d-lg-table-cell">
						<div class="d-flex align-items-center gap-2">
							${agentImage}
							<div>
								<div class="fw-semibold">${agentName}</div>
								<small class="text-muted">User ID #${escapeHtml(row.agent?.id ?? '--')}</small>
							</div>
						</div>
					</td>
					<td class="d-lg-none momoremit-mobile-details">
						<div class="momoremit-refline">${refLabel}</div>
						<div class="momoremit-mobile-agent mt-1 text-muted small">
							${agentImageMini}
							<span>${agentName}</span>
						</div>
						<div class="text-muted small mt-1">${dateLabel}</div>
						<div class="mt-1">
							<div class="fw-semibold">${transactionTitle}</div>
							<div class="text-muted small">${transactionSub}</div>
							<div class="momoremit-transaction-meta">${transactionStatus}</div>
							${rejectionReason}
						</div>
					</td>
					<td class="text-end d-lg-none">
						<div class="momoremit-amount-stack">
							<div>
								<small class="text-muted d-block">Amount</small>
								<span class="fw-semibold text-primary">${formatCurrency(row.amount)}</span>
							</div>
							${renderOptionalAmountBlock('Payable', row.amount_payable, 'text-success')}
							${renderOptionalAmountBlock('Sendable', row.amount_sendable, 'text-info')}
							${mobileChargeBlock}
							<div>
								<small class="text-muted d-block">Balance After</small>
								${renderBalanceAfter(row.balance_after)}
							</div>
						</div>
					</td>
					<td class="text-end">${actions}</td>
				</tr>`;
			}).join('');
		};

		if (rejectModal && rejectForm) {
			rejectModal.addEventListener('show.bs.modal', (event) => {
				const button = event.relatedTarget;
				if (!button || !button.matches('[data-reject-url]')) return;
				rejectForm.dataset.submitting = 'false';
				rejectForm.action = button.dataset.rejectUrl;
				if (rejectReason) rejectReason.value = '';
				if (rejectError) {
					rejectError.textContent = '';
					rejectError.classList.add('d-none');
				}
				if (rejectProgress) rejectProgress.classList.add('d-none');
				if (rejectSubmitButton) {
					rejectSubmitButton.disabled = false;
					rejectSubmitButton.innerHTML = 'Confirm Rejection';
				}
				if (rejectReason) rejectReason.disabled = false;
				if (rejectPrompt) {
					rejectPrompt.textContent = `Rejecting transaction ${button.dataset.rejectReference || ''} will reverse its accounting impacts and mark its linked transaction record as deleted. This action cannot be undone here.`;
				}
			});
		}

		if (rejectForm) {
			rejectForm.addEventListener('submit', async (event) => {
				event.preventDefault();
				if (!rejectForm.action || rejectForm.dataset.submitting === 'true') return;
				const rejectionRequestBody = new FormData(rejectForm);
				rejectForm.dataset.submitting = 'true';
				if (rejectSubmitButton) {
					rejectSubmitButton.disabled = true;
					rejectSubmitButton.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Rejecting...';
				}
				if (rejectReason) rejectReason.disabled = true;
				rejectForm.querySelectorAll('button[type="button"]').forEach((button) => { button.disabled = true; });
				if (rejectProgress) {
					rejectProgress.classList.remove('d-none');
					rejectProgress.classList.add('d-flex');
				}
				if (rejectError) {
					rejectError.textContent = '';
					rejectError.classList.add('d-none');
				}

				try {
					const response = await fetch(rejectForm.action, {
						method: 'POST',
						body: rejectionRequestBody,
						credentials: 'include',
						headers: {
							'Accept': 'application/json',
							'X-Requested-With': 'XMLHttpRequest'
						}
					});
					const responseText = await response.text();
					let payload;
					try {
						payload = JSON.parse(responseText);
					} catch (parseError) {
						const contentType = response.headers.get('content-type') || '';
						const detail = contentType.includes('text/html')
							? responseText.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 300)
							: responseText.trim().slice(0, 300);
						throw new Error(detail || `The server returned an invalid response (HTTP ${response.status}). The transaction was not confirmed as rejected.`);
					}
					if (!response.ok || !payload.success) {
						throw new Error(payload.message || 'Unable to reject this transaction.');
					}

					const modalElement = document.getElementById('rejectTransactionModal');
					const modalInstance = modalElement ? bootstrap.Modal.getInstance(modalElement) : null;
					if (modalInstance) modalInstance.hide();
					fetchData();
				} catch (error) {
					if (rejectError) {
						rejectError.textContent = error.message || 'Unable to reject this transaction.';
						rejectError.classList.remove('d-none');
					}
				} finally {
					rejectForm.dataset.submitting = 'false';
					if (rejectSubmitButton) {
						rejectSubmitButton.disabled = false;
						rejectSubmitButton.innerHTML = 'Confirm Rejection';
					}
					if (rejectReason) rejectReason.disabled = false;
					rejectForm.querySelectorAll('button[type="button"]').forEach((button) => { button.disabled = false; });
					if (rejectProgress) {
						rejectProgress.classList.add('d-none');
						rejectProgress.classList.remove('d-flex');
					}
				}
			});
		}

		const renderPagination = (pagination) => {
			if(!refs.pagination) return;
			refs.pagination.innerHTML = '';
			if(!pagination || pagination.total_pages <= 1){
				return;
			}
			const createButton = (label, page, disabled = false, active = false) => {
				const li = document.createElement('li');
				li.className = 'page-item' + (active ? ' active' : '') + (disabled ? ' disabled' : '');
				const button = document.createElement('button');
				button.type = 'button';
				button.className = 'page-link';
				if(!disabled){
					button.dataset.page = page;
				}
				button.textContent = label;
				li.appendChild(button);
				return li;
			};

			const { page, total_pages } = pagination;
			refs.pagination.appendChild(createButton('«', page - 1, page <= 1));
			const windowSize = 5;
			let start = Math.max(1, page - 2);
			let end = Math.min(total_pages, start + windowSize - 1);
			if(end - start < windowSize - 1){
				start = Math.max(1, end - windowSize + 1);
			}
			if(start > 1){
				refs.pagination.appendChild(createButton('1', 1));
				if(start > 2){
					const ellipsis = document.createElement('li');
					ellipsis.className = 'page-item disabled';
					ellipsis.innerHTML = '<span class="page-link">...</span>';
					refs.pagination.appendChild(ellipsis);
				}
			}
			for(let i = start; i <= end; i++){
				refs.pagination.appendChild(createButton(i, i, false, i === page));
			}
			if(end < total_pages){
				if(end < total_pages - 1){
					const ellipsis = document.createElement('li');
					ellipsis.className = 'page-item disabled';
					ellipsis.innerHTML = '<span class="page-link">...</span>';
					refs.pagination.appendChild(ellipsis);
				}
				refs.pagination.appendChild(createButton(total_pages, total_pages));
			}
			refs.pagination.appendChild(createButton('»', page + 1, page >= total_pages));
		};

		const renderStats = (totals = {}, latestActivity = null, pagination = null, currentRows = 0) => {
			if(refs.totalVolume) refs.totalVolume.textContent = formatCurrency(totals.total_amount || 0);
			if(refs.totalCharge) refs.totalCharge.textContent = formatCurrency(totals.total_charge || 0);
			const count = totals.count || pagination?.total_rows || 0;
			if(refs.txCount) refs.txCount.textContent = `${numberFormatter.format(count)} transactions`;
			if(refs.latestActivity) refs.latestActivity.textContent = formatDateTime(latestActivity);
			if(refs.resultCount) refs.resultCount.textContent = numberFormatter.format(currentRows);
			if(refs.totalCount) refs.totalCount.textContent = numberFormatter.format(pagination?.total_rows || 0);
		};

		const renderTopAgents = (agents = []) => {
			if(!refs.topAgents) return;
			refs.topAgents.innerHTML = agents.length
				? agents.map((agent, index) => `
				<li class="list-group-item d-flex align-items-center justify-content-between">
					<div>
						<strong class="me-2">#${index + 1}</strong>
						<span>${escapeHtml(agent.name || 'Unknown')}</span>
					</div>
					<div class="text-end">
						<div class="fw-semibold">${formatCurrency(agent.total)}</div>
						<small class="text-muted">${numberFormatter.format(agent.count)} txs</small>
					</div>
				</li>`).join('')
				: '<li class="list-group-item text-muted">No data</li>';
		};

		const renderNetwork = (network = []) => {
			if(!refs.network) return;
			refs.network.innerHTML = network.length
				? network.map(item => `
				<div class="mb-3">
					<div class="d-flex justify-content-between mb-1">
						<strong>${escapeHtml(item.label)}</strong>
						<span>${formatCurrency(item.amount)} (${formatPercent(item.percent)})</span>
					</div>
					<div class="momoremit-progress"><span style="width:${item.percent * 100}%"></span></div>
					<small class="text-muted">${numberFormatter.format(item.count)} transactions</small>
				</div>`).join('')
				: '<p class="text-muted mb-0">No network data.</p>';
		};

		const renderTrend = (trend = []) => {
			if(!refs.trend) return;
			refs.trend.innerHTML = trend.length
				? trend.map(item => `
				<div class="col-md-3 col-sm-6">
					<div class="border rounded-3 p-3 h-100">
						<p class="text-muted mb-1 small">${escapeHtml(item.label)}</p>
						<strong>${formatCurrency(item.amount)}</strong>
						<div class="text-muted small">${numberFormatter.format(item.count)} txs</div>
					</div>
				</div>`).join('')
				: '<p class="text-muted mb-0">No trend data.</p>';
		};

		const renderAnalysis = (analysis = {}, latest) => {
			if(refs.avgTicket) refs.avgTicket.textContent = formatCurrency(analysis.avg_ticket || 0);
			if(refs.chargeRate) refs.chargeRate.textContent = formatPercent(analysis.charge_rate || 0);
			if(refs.lastSyncInsight) refs.lastSyncInsight.textContent = formatDateTime(latest);
			renderTopAgents(analysis.top_agents || []);
			renderNetwork(analysis.network_breakdown || []);
			renderTrend(analysis.trend || []);
		};

		const fetchData = async () => {
			setLoading(true);

			if(refs.emptyState) refs.emptyState.classList.add('d-none');
			if(refs.errorState) refs.errorState.classList.add('d-none');
			if(activeController){
				activeController.abort();
			}
			activeController = new AbortController();
			const params = new URLSearchParams({
				page: state.page,
				limit: state.limit,
				find: state.find,
				agent_search: state.agent_search,
				network: state.network,
				date_from: state.date_from,
				date_to: state.date_to,
				created_by: state.created_by,
				transaction_type: state.transaction_type
			});

			try {
				const response = await fetch(`${endpoint}?${params.toString()}`, {
					signal: activeController.signal,
					credentials: 'include',
					headers: {
						'Accept': 'application/json',
						'X-Requested-With': 'XMLHttpRequest'
					}
				});

				const responseText = await response.text();
				let payload;
				try {
					payload = JSON.parse(responseText);
				} catch (parseError) {
					const contentType = response.headers.get('content-type') || '';
					const reason = contentType.includes('text/html')
						? 'The server returned an HTML page. Your session may have expired; refresh and sign in again.'
						: 'The server returned an invalid response. Please refresh and try again.';
					throw new Error(reason);
				}
				if(!response.ok){
					throw new Error(payload.message || 'Unable to fetch data');
				}
				if(!payload.success){
					throw new Error(payload.message || 'Unexpected error');
				}
				const rows = payload.rows || [];
				renderRows(rows);
				renderPagination(payload.pagination);
				renderStats(payload.totals || {}, payload.latest_activity, payload.pagination, rows.length);
				renderAnalysis(payload.analysis || {}, payload.latest_activity);
				if(refs.lastSync) refs.lastSync.textContent = formatDateTime(payload.latest_activity);
			} catch (error) {
				if(error.name !== 'AbortError'){
					console.error('Momoremit fetch error:', error);
					showError(error.message);
				}
			} finally {
				setLoading(false);
			}
		};

		const updateStateFromForm = () => {
			state.find = (inputs.search?.value || '').trim();
			state.agent_search = (inputs.agentSearch?.value || '').trim();
			state.network = inputs.network?.value || '';
			state.date_from = inputs.dateFrom?.value || '';
			state.date_to = inputs.dateTo?.value || '';
			state.created_by = inputs.createdBy?.value || '';
			state.transaction_type = inputs.transactionType?.value || '';
			state.limit = parseInt(inputs.limit?.value || '10', 10) || 10;
			state.page = 1;
		};

		if(inputs.form){
			inputs.form.addEventListener('submit', (event) => {
				event.preventDefault();
				updateStateFromForm();
				syncUrl();
				fetchData();
				if(isMobile()){
					setFiltersOpen(false);
				}
			});
		}

		if(inputs.reset){
			inputs.reset.addEventListener('click', () => {
				if(inputs.search) inputs.search.value = '';
				if(inputs.agentSearch) inputs.agentSearch.value = '';
				if(inputs.network) inputs.network.value = '';
				if(inputs.dateFrom) inputs.dateFrom.value = '';
				if(inputs.dateTo) inputs.dateTo.value = '';
				if(inputs.createdBy) inputs.createdBy.value = '';
				if(inputs.transactionType) inputs.transactionType.value = '';
				if(inputs.limit) inputs.limit.value = '10';

				updateStateFromForm();
				syncUrl();
				fetchData();
				if(isMobile()){
					setFiltersOpen(false);
				}
			});
		}

		if(inputs.search){
			inputs.search.addEventListener('input', debounce(() => {
				state.find = inputs.search.value.trim();
				state.page = 1;
				syncUrl();
				fetchData();
			}));
		}

		if(inputs.agentSearch){
			inputs.agentSearch.addEventListener('input', debounce(() => {
				state.agent_search = inputs.agentSearch.value.trim();
				state.page = 1;
				syncUrl();
				fetchData();
			}));
		}

		['change'].forEach(evt => {
			if(inputs.limit){
				inputs.limit.addEventListener(evt, () => {
					state.limit = parseInt(inputs.limit.value, 10) || 10;
					state.page = 1;
					syncUrl();
					fetchData();
				});
			}
			if(inputs.createdBy){
				inputs.createdBy.addEventListener(evt, () => {
					state.created_by = inputs.createdBy.value;
					state.page = 1;
					syncUrl();
					fetchData();
				});
			}
			if(inputs.network){
				inputs.network.addEventListener(evt, () => {
					state.network = inputs.network.value;
					state.page = 1;
					syncUrl();
					fetchData();
				});
			}
			if(inputs.transactionType){
				inputs.transactionType.addEventListener(evt, () => {
					state.transaction_type = inputs.transactionType.value;
					state.page = 1;
					syncUrl();
					fetchData();
				});
			}
			if(inputs.dateFrom){
				inputs.dateFrom.addEventListener(evt, () => {
					state.date_from = inputs.dateFrom.value;
					state.page = 1;
					syncUrl();
					fetchData();
				});
			}
			if(inputs.dateTo){
				inputs.dateTo.addEventListener(evt, () => {
					state.date_to = inputs.dateTo.value;
					state.page = 1;
					syncUrl();
					fetchData();
				});
			}
		});

		if(refs.pagination){
			refs.pagination.addEventListener('click', (event) => {
				const target = event.target.closest('[data-page]');
				if(!target) return;
				const nextPage = parseInt(target.dataset.page, 10);
				if(Number.isNaN(nextPage) || nextPage === state.page || nextPage < 1) return;
				state.page = nextPage;
				syncUrl();
				fetchData();
			});
		}

		hydrateFromUrl();
		syncUrl();
		fetchData();
	})();
</script>

<?php else:?>
	<div class="alert alert-danger text-center">
		Access denied. You dont have permission for this action
	</div>
<?php endif?>
