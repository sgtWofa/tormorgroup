<?php if(user_can('view_ezwich')):?>
<?php
$adminRoute = $admin_route ?? 'admin';
$pluginRoute = $plugin_route ?? 'ezwich';
$canAdd = user_can('add_ezwich');
$canViewDetails = user_can('view_ezwich_details');
$canEdit = user_can('edit_ezwich');
$canDelete = user_can('delete_ezwich');

$agentOptions = $users ?? [];
if(empty($agentOptions)){
	try {
		$userModel = new \Ezwichs\User;
		$agentOptions = $userModel->query("SELECT id, first_name, last_name FROM users WHERE deleted = 0 ORDER BY first_name ASC LIMIT 200");
	} catch (\Throwable $th) {
		$agentOptions = [];
	}
}

$transactionTypes = [];
$networkOptions = [];
try {
	$db = new \Core\Database;
	$transactionTypes = $db->query("SELECT DISTINCT transaction_type FROM ezwichs WHERE transaction_type IS NOT NULL AND transaction_type <> '' ORDER BY transaction_type ASC LIMIT 25");
	$networkOptions = $db->query("SELECT DISTINCT network FROM ezwichs WHERE network IS NOT NULL AND network <> '' ORDER BY network ASC LIMIT 25");
} catch (\Throwable $th) {
	$transactionTypes = [];
	$networkOptions = [];
}

$statusOptions = ['Approved','Pending','Draft','Rejected','Declined'];

$transactionTypeLabels = [];
if(!is_array($transactionTypes)){
	$transactionTypes = [];
}
foreach($transactionTypes as $type){
	$label = trim($type->transaction_type ?? '');
	if($label && !in_array($label, $transactionTypeLabels, true)){
		$transactionTypeLabels[] = $label;
	}
}

$networkLabels = [];
if(!is_array($networkOptions)){
	$networkOptions = [];
}
foreach($networkOptions as $network){
	$label = trim($network->network ?? '');
	if($label && !in_array($label, $networkLabels, true)){
		$networkLabels[] = $label;
	}
}

$agentChoices = [];
foreach($agentOptions as $agent){
	$id = (int)($agent->id ?? 0);
	if(!$id){
		continue;
	}
	$name = trim(($agent->first_name ?? '').' '.($agent->last_name ?? ''));
	$agentChoices[] = [
		'id' => $id,
		'name' => $name ?: ('ID '.$id)
	];
}

$filterMetaPayload = [
	'statusOptions' => array_values($statusOptions),
	'transactionTypes' => $transactionTypeLabels,
	'networks' => $networkLabels,
	'agents' => $agentChoices,
];

$defaultFilters = ['date','status'];

$filterOptions = [
	['key' => 'date', 'label' => 'Date range', 'description' => 'Limit by created date'],
	['key' => 'status', 'label' => 'Status', 'description' => 'Approved, pending, draft, rejected, declined'],
	['key' => 'transaction_type', 'label' => 'Transaction type', 'description' => 'Withdrawals vs transfers'],
	['key' => 'network', 'label' => 'Network', 'description' => 'Focus on specific networks'],
	['key' => 'created_by', 'label' => 'Created by', 'description' => 'Filter by staff member'],
];

$filterLabelMap = [];
foreach($filterOptions as $option){
	$filterLabelMap[$option['key']] = $option['label'];
}
$filterOptionKeys = array_keys($filterLabelMap);

$defaultFilterLabels = [];
foreach($defaultFilters as $key){
	if(isset($filterLabelMap[$key])){
		$defaultFilterLabels[] = $filterLabelMap[$key];
	}
}
?>
<style>
	.ez-page{color:var(--bs-body-color);}
	.ez-hero{border:1px solid var(--bs-border-color);border-radius:1.5rem;background:linear-gradient(135deg,rgba(var(--bs-primary-rgb,13,110,253),.1),rgba(var(--bs-warning-rgb,255,193,7),.1));box-shadow:0 25px 45px rgba(15,23,42,.08);}

	.active,
	.ez-page .nav-pills .nav-link.active,
	.ez-page .nav-pills .nav-link.active:focus,
	.ez-page .nav-pills .nav-link.active:hover,
	.ez-page .nav-pills .show > .nav-link,
	.ez-page .nav-pills .show > .nav-link:focus,
	.ez-page .nav-pills .show > .nav-link:hover {
		background-image: linear-gradient(135deg, rgba(var(--bs-primary-rgb, 13,110,253),0.12), rgba(var(--bs-warning-rgb, 255,193,7),0.12)) !important;
		background-color: transparent !important;
		color: var(--bs-body-color) !important;
		border-color: rgba(var(--bs-primary-rgb,13,110,253), 0.25) !important;
	}

	.ez-actions .btn,.ez-actions .dropdown-toggle{border-radius:999px;}
	.ez-stat-card{border-radius:1rem;border:1px solid rgba(15,23,42,.08);background:var(--bs-body-bg);padding:1.25rem;display:flex;justify-content:space-between;align-items:center;gap:1rem;}
	.ez-hero{border:1px solid var(--bs-border-color);border-radius:1.5rem;background:linear-gradient(135deg,rgba(var(--bs-primary-rgb,13,110,253),.1),rgba(var(--bs-warning-rgb,255,193,7),.1));box-shadow:0 25px 45px rgba(15,23,42,.08);}
	.ez-actions .btn,.ez-actions .dropdown-toggle{border-radius:999px;}
	.ez-stat-card{border-radius:1rem;border:1px solid rgba(15,23,42,.08);background:var(--bs-body-bg);padding:1.25rem;display:flex;justify-content:space-between;align-items:center;gap:1rem;}
	.ez-filter-card{border:1px solid rgba(15,23,42,.08);border-radius:1.2rem;padding:1.1rem;background:var(--bs-body-bg);box-shadow:0 20px 45px rgba(15,23,42,.04);}
	.ez-selected-filters{border-radius:1rem;background:rgba(15,23,42,.03);border:1px dashed rgba(15,23,42,.15);padding:.75rem 1rem;min-height:50px;display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;}
	.ez-chip,.ez-selected-filters button{border-radius:999px;border:1px solid rgba(var(--bs-primary-rgb,13,110,253),.3);background:rgba(var(--bs-primary-rgb,13,110,253),.08);padding:.35rem .9rem;font-size:.85rem;font-weight:500;color:inherit;}
	.ez-chip.muted{background:transparent;border-color:var(--bs-border-color);color:var(--bs-secondary-color);}
	.ez-tab-card{border-radius:1.25rem;border:1px solid var(--bs-border-color);box-shadow:0 25px 45px rgba(15,23,42,.08);}
	.ez-table thead th{text-transform:uppercase;font-size:.75rem;letter-spacing:.08em;border-top:0;}
	.ez-loader{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.9);backdrop-filter:blur(3px);z-index:2;transition:opacity .2s ease;}
	.ez-loader.hidden{opacity:0;pointer-events:none;}
	.ez-analysis-card{border-radius:1rem;border:1px solid var(--bs-border-color);padding:1.25rem;background:var(--bs-body-bg);height:100%;}
	.ez-progress{height:8px;border-radius:999px;background:rgba(var(--bs-primary-rgb,13,110,253),.1);overflow:hidden;}
	.ez-progress span{display:block;height:100%;background:var(--bs-primary);}
	.ez-ranking-card{border-radius:1.25rem;border:1px solid rgba(15,23,42,.08);padding:1.5rem;background:var(--bs-body-bg);box-shadow:0 20px 45px rgba(15,23,42,.06);}
	.ez-ranking-list{list-style:none;padding-left:0;margin-bottom:0;}
	.ez-ranking-list li+li{border-top:1px solid rgba(148,163,184,.3);}
	.ez-ranking-avatar{width:42px;height:42px;border-radius:999px;background:rgba(var(--bs-warning-rgb,255,193,7),.2);color:var(--bs-warning);display:inline-flex;align-items:center;justify-content:center;font-weight:600;}
	body[data-bs-theme="dark"] .ez-loader{background:rgba(15,23,42,.85);}
	body[data-bs-theme="dark"] .ez-hero .text-muted,
	body[data-bs-theme="dark"] .ez-filter-card .text-muted{color:rgba(255,255,255,.88)!important;font-weight:600;}
	body[data-bs-theme="dark"] .ez-hero h2{color:#fef08a;font-weight:700;}
	body[data-bs-theme="dark"] .ez-chip,
	body[data-bs-theme="dark"] .ez-chip.muted{color:#fff;font-weight:600;border-color:rgba(255,255,255,.35);background:rgba(255,255,255,.08);}
	body[data-bs-theme="dark"] .ez-chip.muted{background:rgba(255,255,255,.04);}
	body[data-bs-theme="dark"] .ez-stat-icon{background:rgba(247,201,72,.18)!important;color:#f7c948!important;font-weight:700;}
	body[data-bs-theme="dark"] .ez-selected-filters{border-color:rgba(255,255,255,.2);background:rgba(255,255,255,.03);}
	body[data-bs-theme="dark"] #ezAnalytics h3,
	body[data-bs-theme="dark"] #ezAnalytics h5,
	body[data-bs-theme="dark"] #ezPerformance h3,
	body[data-bs-theme="dark"] #ezPerformance h5{color:#ffffff;}
	body[data-bs-theme="dark"] #ezPerformance .badge.bg-primary-subtle.text-primary{background:rgba(255,255,255,.12);color:#ffffff;}
	body[data-bs-theme="dark"] #ezAnalytics .text-primary,
	body[data-bs-theme="dark"] #ezAnalytics a,
	body[data-bs-theme="dark"] #ezPerformance .text-primary,
	body[data-bs-theme="dark"] #ezPerformance a{color:#ffffff!important;}

	.ez-filter-dropdown .dropdown-menu{min-width:240px;max-height:300px;overflow:auto;}
	.ez-filter-dropdown .form-check{margin-bottom:.25rem;}
	.ez-filter-dropdown button{padding:.4rem .95rem;font-size:.9rem;}
	.ez-filter-inline{gap:.6rem;align-items:center;}
	.ez-filter-chip-bar{min-height:38px;gap:.4rem;}
	.ez-filter-group{border:1px dashed rgba(15,23,42,.12);border-radius:.9rem;padding:.85rem;background:rgba(15,23,42,.02);height:100%;font-size:.9rem;}

	.ez-row-mobile{display:none;}
	.ez-mobile-grid{display:grid;grid-template-columns:minmax(220px,1fr) minmax(160px,220px) auto;gap:.9rem;align-items:start;}
	.ez-mobile-card{border:1px solid rgba(15,23,42,.08);border-radius:1rem;padding:1rem;background:var(--bs-body-bg);}
	.ez-mobile-meta{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;justify-content:space-between;}
	.ez-mobile-kv{font-size:.85rem;color:var(--bs-secondary-color);}
	.ez-mobile-actions .btn{border-radius:999px;}

	@media (max-width: 991.98px){
		.ez-row-desktop{display:none;}
		.ez-row-mobile{display:table-row;}
		.ez-table thead{display:none;}
		.ez-table td{text-align:left !important;}
		.ez-table td.text-end{text-align:left !important;}
		.ez-table .ez-mobile-actions{text-align:left !important;}
	}

	@media(max-width:767px){.ez-hero{padding:1.25rem;}}
	@media(max-width:992px){
		.ez-filter-card .d-flex{align-items:stretch;}
		.ez-filter-card .d-flex > div{width:100%;}
		.ez-filter-card select{width:auto;}
		.ez-table .ez-mobile-actions{text-align:left !important;}
	}
	@media (min-width:768px) and (max-width:991.98px){
		.ez-stat-card{padding:1.1rem;}
		.ez-stat-card h3{font-size:1.35rem;}
	}
</style>

<div class="ez-page container-fluid py-4">
	<div class="ez-hero p-4">
		<div class="d-flex flex-column flex-xl-row gap-3 align-items-start">
			<div class="flex-grow-1">
				<p class="text-uppercase text-muted small mb-1">Channel health</p>
				<h2 class="fw-bold mb-2">Ezwich Operations Center</h2>
				<p class="text-muted mb-0">Monitor withdrawals & transfers with asynchronous filters, metrics, and rankings.</p>
			</div>
			<div class="d-flex flex-wrap gap-2 ez-actions">
				<button class="btn btn-outline-secondary" type="button" id="ezExportPdf">
					<span class="me-2"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H9a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2Z"/><path d="M9 2h6"/><path d="M12 11h.01"/><path d="M12 16h.01"/></svg></span>
					Export PDF
				</button>
				<?php if($canAdd):?>
				<div class="dropdown">
					<button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
						<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
						New transaction
					</button>
					<ul class="dropdown-menu dropdown-menu-end shadow">
						<li><a class="dropdown-item" href="<?=ROOT?>/<?=$adminRoute?>/<?=$pluginRoute?>/withdrawal">Withdrawal</a></li>
						<li><a class="dropdown-item" href="<?=ROOT?>/<?=$adminRoute?>/<?=$pluginRoute?>/transfer">Transfer</a></li>
					</ul>
				</div>
				<?php endif;?>
			</div>
		</div>
		<div class="row g-3 mt-1">
			<div class="col-12 col-sm-6 col-lg-4">
				<div class="ez-stat-card">
					<div>
						<p class="text-uppercase small text-muted mb-1">Total volume</p>
						<h3 class="fw-bolder fw-semibold mb-1" data-total-volume>GHS 0.00</h3>
						<small class="text-muted">Latest activity · <span data-latest-activity>—</span></small>
					</div>
					<span class="ez-stat-icon">
						<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20V10"/><path d="M18 20V4"/><path d="M6 20v-4"/></svg>
					</span>
				</div>
			</div>
			<div class="col-12 col-sm-6 col-lg-4">
				<div class="ez-stat-card">
					<div>
						<p class="text-uppercase small text-muted mb-1">Charges collected</p>
						<h3 class="fw-bolder fw-semibold mb-1" data-total-charge>GHS 0.00</h3>
						<small class="text-muted">Auto-calculated from async feed</small>
					</div>
					<span class="ez-stat-icon" style="background:rgba(var(--bs-success-rgb,25,135,84),.12);color:var(--bs-success);">
						<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
					</span>
				</div>
			</div>
			<div class="col-12 col-lg-4">
				<div class="ez-stat-card">
					<div>
						<p class="text-uppercase small text-muted mb-1">Transactions</p>
						<h3 class="fw-bolder fw-semibold mb-1" data-tx-count>0</h3>
						<small class="text-muted">Live count of filtered rows</small>
					</div>
					<span class="ez-stat-icon" style="background:rgba(var(--bs-warning-rgb,255,193,7),.2);color:var(--bs-warning);">
						<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 5 2 14h14l2-14Z"/><path d="M10 11h3"/><path d="M10 15h3"/></svg>
					</span>
				</div>
			</div>
		</div>
	</div>

	<div class="ez-filter-card mt-3">
		<div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
			<div class="d-none d-lg-block">
				<h6 class="mb-1">Smart filters</h6>
				<p class="text-muted small mb-0">Update the table, metrics, and rankings without leaving the page.</p>
			</div>
			<div class="d-flex flex-wrap gap-2 align-items-center">
				<label class="text-muted small">Rows per page</label>
				<select class="form-select form-select-sm w-auto" id="ezLimit">
					<option value="10">10</option>
					<option value="25">25</option>
					<option value="50">50</option>
					<option value="100">100</option>
				</select>
				<button class="btn btn-outline-secondary btn-sm" type="button" id="ezResetFilters">Reset</button>
			</div>
		</div>
		<form class="mt-3" id="ezFilters">
			<div class="row gy-2 gx-3 align-items-end">
				<div class="col-lg-4 col-md-6">
					<label class="form-label small text-uppercase text-muted mb-1">Search</label>
					<input type="search" class="form-control form-control-sm" name="find" id="ezSearch" placeholder="Reference, phone, network" data-filter>
				</div>
				<div class="col-lg-8 col-md-6 d-none d-lg-block">
					<label class="form-label small text-uppercase text-muted mb-1">Filter by</label>
					<div class="d-flex flex-wrap ez-filter-inline">
						<div class="dropdown ez-filter-dropdown">
							<button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Select filters</button>
							<div class="dropdown-menu p-3" id="ezFilterMenu">
								<?php foreach($filterOptions as $option):?>
								<div class="form-check">
									<input class="form-check-input" type="checkbox" value="<?=esc($option['key'])?>" id="filter-<?=esc($option['key'])?>" data-filter-option data-label="<?=esc($option['label'])?>" <?=in_array($option['key'], $defaultFilters, true) ? 'checked' : ''?>>
									<label class="form-check-label" for="filter-<?=esc($option['key'])?>">
										<strong><?=esc($option['label'])?></strong>
										<small class="d-block text-muted"><?=esc($option['description'])?></small>
									</label>
								</div>
								<?php endforeach;?>
							</div>
						</div>
						<small class="text-muted">Multiple picks add compact fields below.</small>
					</div>
					<div class="d-flex flex-wrap ez-filter-chip-bar mt-2" id="ezFilterTags">
						<?php if(empty($defaultFilterLabels)):?>
						<span class="text-muted small">No advanced filters selected</span>
						<?php else:?>
							<?php foreach($defaultFilterLabels as $label):?>
							<span class="ez-chip"><?=esc($label)?></span>
							<?php endforeach;?>
						<?php endif;?>
					</div>
				</div>
			</div>
			<div class="row gy-2 gx-3 mt-2 d-none d-lg-flex" id="ezDynamicFilters">
				<div class="col-12">
					<div class="ez-filter-group text-center text-muted py-3">
						Filter controls will appear here once selected.
					</div>
				</div>
			</div>
		</form>

		<div class="ez-selected-filters mt-3 d-none d-lg-flex" id="ezActiveFilters">
			<small class="text-muted">No filters applied • live feed</small>
		</div>
	</div>

	<ul class="nav nav-tabs mt-4" id="ezTabs" role="tablist">
		<li class="nav-item" role="presentation">
			<button class="nav-link active" id="ez-live-tab" data-bs-toggle="tab" data-bs-target="#ezLive" type="button" role="tab">Live transactions</button>
		</li>
		<li class="nav-item" role="presentation">
			<button class="nav-link" id="ez-analytics-tab" data-bs-toggle="tab" data-bs-target="#ezAnalytics" type="button" role="tab">Insights & analytics</button>
		</li>
		<li class="nav-item" role="presentation">
			<button class="nav-link" id="ez-performance-tab" data-bs-toggle="tab" data-bs-target="#ezPerformance" type="button" role="tab">Performance rankings</button>
		</li>
	</ul>

	<div class="tab-content mt-3">
		<div class="tab-pane fade show active" id="ezLive" role="tabpanel">
			<div class="ez-tab-card position-relative">
				<div class="card-header border-0 bg-transparent d-flex flex-wrap justify-content-between align-items-center gap-2">
					<div>
						<p class="mb-0 text-muted small">Showing <span data-result-count>0</span> / <span data-total-count>0</span> transactions</p>
						<small class="text-muted">Last sync <span data-last-sync>—</span></small>
					</div>
					<div class="d-flex gap-2 flex-wrap">
						<span class="ez-chip">Async feed</span>
						<span class="ez-chip muted">Metrics auto-update</span>
					</div>
				</div>
				<div class="position-relative">
					<div class="ez-loader hidden" id="ezTableLoader">
						<div class="text-center">
							<div class="spinner-border text-primary mb-2" role="status"></div>
							<p class="text-muted mb-0">Fetching latest data…</p>
						</div>
					</div>
					<div class="table-responsive">
						<table class="table align-middle ez-table mb-0">
							<thead>
								<tr>
									<th># / Ref</th>
									<th>Date</th>
									<th>Type</th>
									<th>Status</th>
									<th class="text-end">Amount</th>
									<th>Phone</th>
									<th>Staff</th>
									<th class="text-end">Actions</th>
								</tr>
							</thead>
							<tbody id="ezTableBody"></tbody>
						</table>
					</div>
					<div class="text-center text-muted py-4 d-none" id="ezEmpty">
						<i class="fa fa-box-open fa-2x mb-2"></i>
						<p class="mb-0">No transactions match the current filters.</p>
					</div>
					<div class="text-center text-danger py-3 d-none" id="ezError"></div>
				</div>
				<div class="card-footer bg-transparent border-0">
					<nav aria-label="Ezwich pagination">
						<ul class="pagination justify-content-center mb-0" id="ezPagination"></ul>
					</nav>
				</div>
			</div>
		</div>
		<div class="tab-pane fade" id="ezAnalytics" role="tabpanel">
			<div class="row g-3">
				<div class="col-lg-4">
					<div class="ez-analysis-card h-100">
						<p class="text-uppercase text-muted small mb-1">Avg ticket</p>
						<h3 data-avg-ticket>GHS 0.00</h3>
						<p class="text-muted mb-0">Average amount per transaction.</p>
					</div>
				</div>
				<div class="col-lg-4">
					<div class="ez-analysis-card h-100">
						<p class="text-uppercase text-muted small mb-1">Charge rate</p>
						<h3 data-charge-rate>0%</h3>
						<p class="text-muted mb-0">Charges relative to total volume.</p>
					</div>
				</div>
				<div class="col-lg-4">
					<div class="ez-analysis-card h-100">
						<p class="text-uppercase text-muted small mb-1">Latest sync</p>
						<h3 data-last-sync-insight>—</h3>
						<p class="text-muted mb-0">Timestamp from live data endpoint.</p>
					</div>
				</div>
			</div>
			<div class="row g-3 mt-1">
				<div class="col-xl-4">
					<div class="ez-analysis-card h-100">
						<h5 class="mb-3">Network mix</h5>
						<ul class="ez-ranking-list" id="ezNetworkMix"></ul>
					</div>
				</div>
				<div class="col-xl-4">
					<div class="ez-analysis-card h-100">
						<h5 class="mb-3">Status breakdown</h5>
						<ul class="ez-ranking-list" id="ezStatusBreakdown"></ul>
					</div>
				</div>
				<div class="col-xl-4">
					<div class="ez-analysis-card h-100">
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
								<tbody id="ezTrend"></tbody>
							</table>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="tab-pane fade" id="ezPerformance" role="tabpanel">
			<div class="row g-3">
				<div class="col-lg-6">
					<div class="ez-ranking-card h-100">
						<div class="d-flex justify-content-between align-items-center">
							<h5 class="mb-0">Top staff</h5>
							<span class="badge bg-primary-subtle text-primary">Leaderboard</span>
						</div>
						<ul class="ez-ranking-list mt-2" id="ezTopStaff"></ul>
					</div>
				</div>
				<div class="col-lg-6">
					<div class="ez-ranking-card h-100">
						<div class="d-flex justify-content-between align-items-center">
							<h5 class="mb-0">Top clients</h5>
							<span class="badge bg-warning-subtle text-dark">Phone-based</span>
						</div>
						<ul class="ez-ranking-list mt-2" id="ezTopClients"></ul>
					</div>
				</div>
				<div class="col-12">
					<div class="ez-ranking-card">
						<h5 class="mb-3">Status performance snapshot</h5>
						<div class="row g-2" id="ezStatusSummary"></div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
(function(){
	const DATA_ENDPOINT = '<?=ROOT?>/<?=$adminRoute?>/<?=$pluginRoute?>/data';
	const VIEW_BASE = '<?=ROOT?>/<?=$adminRoute?>/<?=$pluginRoute?>';
	const permissions = {
		view: <?= $canViewDetails ? 'true' : 'false' ?>,
		edit: <?= $canEdit ? 'true' : 'false' ?>,
		delete: <?= $canDelete ? 'true' : 'false' ?>
	};
	const FILTER_META = <?=json_encode($filterMetaPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)?>;
	const FILTER_LABELS = <?=json_encode($filterLabelMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)?>;
	const DEFAULT_FILTER_KEYS = <?=json_encode($defaultFilters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)?>;

	const refs = {
		form: document.getElementById('ezFilters'),
		activeFilters: document.getElementById('ezActiveFilters'),
		limit: document.getElementById('ezLimit'),
		reset: document.getElementById('ezResetFilters'),
		tableLoader: document.getElementById('ezTableLoader'),
		tableBody: document.getElementById('ezTableBody'),
		pagination: document.getElementById('ezPagination'),
		empty: document.getElementById('ezEmpty'),
		error: document.getElementById('ezError'),
		resultCount: document.querySelector('[data-result-count]'),
		totalCount: document.querySelector('[data-total-count]'),
		lastSync: document.querySelector('[data-last-sync]'),
		totalVolume: document.querySelector('[data-total-volume]'),
		totalCharge: document.querySelector('[data-total-charge]'),
		txCount: document.querySelector('[data-tx-count]'),
		latestActivity: document.querySelector('[data-latest-activity]'),
		avgTicket: document.querySelector('[data-avg-ticket]'),
		chargeRate: document.querySelector('[data-charge-rate]'),
		lastSyncInsight: document.querySelector('[data-last-sync-insight]'),
		networkMix: document.getElementById('ezNetworkMix'),
		statusBreakdown: document.getElementById('ezStatusBreakdown'),
		trend: document.getElementById('ezTrend'),
		topStaff: document.getElementById('ezTopStaff'),
		topClients: document.getElementById('ezTopClients'),
		statusSummary: document.getElementById('ezStatusSummary'),
		exportBtn: document.getElementById('ezExportPdf'),
		filterMenu: document.getElementById('ezFilterMenu'),
		filterTags: document.getElementById('ezFilterTags'),
		dynamicFilters: document.getElementById('ezDynamicFilters')
	};

	const filterLabels = {
		find: 'Search',
		date_from: 'From',
		date_to: 'To',
		transaction_status: 'Status',
		transaction_type: 'Type',
		network: 'Network',
		created_by: 'Staff'
	};

	const state = {
		page: 1,
		limit: parseInt(refs.limit.value, 10) || 10,
		filters: {}
	};

	const FILTER_PLACEHOLDER = '<div class="col-12"><div class="ez-filter-group text-center text-muted py-4">Filter controls will appear here once selected.</div></div>';

	const escapeHtml = (value = '') => String(value)
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')
		.replace(/'/g, '&#039;');

	const buildOptionTags = (items = [], valueKey, labelKey) => items.map((item) => {
		const rawValue = valueKey ? item?.[valueKey] : item;
		const rawLabel = labelKey ? item?.[labelKey] : item;
		if(rawValue === undefined || rawValue === null) return '';
		const value = String(rawValue).trim();
		const label = String(rawLabel ?? rawValue).trim();
		if(!value) return '';
		return `<option value="${escapeHtml(value)}">${escapeHtml(label || value)}</option>`;
	}).join('');

	const filterBuilders = {
		date: () => `
			<div class="col-xl-6 col-lg-7" data-filter-block="date">
				<div class="row g-2">
					<div class="col-sm-6">
						<label class="form-label small text-uppercase text-muted">Date from</label>
						<input type="date" class="form-control" name="date_from" data-filter>
					</div>
					<div class="col-sm-6">
						<label class="form-label small text-uppercase text-muted">Date to</label>
						<input type="date" class="form-control" name="date_to" data-filter>
					</div>
				</div>
			</div>`,
		status: () => `
			<div class="col-xl-3 col-md-6" data-filter-block="status">
				<label class="form-label small text-uppercase text-muted">Status</label>
				<select class="form-select" name="transaction_status" data-filter>
					<option value="">All statuses</option>
					${buildOptionTags(FILTER_META.statusOptions || [])}
				</select>
			</div>`,
		transaction_type: () => `
			<div class="col-xl-3 col-md-6" data-filter-block="transaction_type">
				<label class="form-label small text-uppercase text-muted">Transaction type</label>
				<select class="form-select" name="transaction_type" data-filter>
					<option value="">All types</option>
					${buildOptionTags(FILTER_META.transactionTypes || [])}
				</select>
			</div>`,
		network: () => `
			<div class="col-xl-3 col-md-6" data-filter-block="network">
				<label class="form-label small text-uppercase text-muted">Network</label>
				<select class="form-select" name="network" data-filter>
					<option value="">All networks</option>
					${buildOptionTags(FILTER_META.networks || [])}
				</select>
			</div>`,
		created_by: () => `
			<div class="col-xl-3 col-md-6" data-filter-block="created_by">
				<label class="form-label small text-uppercase text-muted">Created by</label>
				<select class="form-select" name="created_by" data-filter>
					<option value="">All staff</option>
					${buildOptionTags(FILTER_META.agents || [], 'id', 'name')}
				</select>
			</div>`
	};

	const debounce = (fn, delay = 350) => {
		let timer;
		return (...args) => {
			clearTimeout(timer);
			timer = setTimeout(() => fn.apply(null, args), delay);
		};
	};

	function registerFilterInputs(scope = refs.form){
		if(!scope) return;
		scope.querySelectorAll('[data-filter]').forEach(input => {
			if(input.dataset.listenerAttached) return;
			let handler = handleFilterChange;
			let eventName = 'input';
			if(input.type === 'search'){
				handler = debounce(handleFilterChange);
			}else if(input.tagName === 'SELECT' || input.type === 'date'){
				eventName = 'change';
			}
			if(input.type === 'search'){
				eventName = 'input';
			}
			input.addEventListener(eventName, handler);
			input.dataset.listenerAttached = 'true';
		});
	}

	function getSelectedFilters(){
		if(!refs.filterMenu) return [];
		return Array.from(refs.filterMenu.querySelectorAll('[data-filter-option]:checked')).map(input => input.value);
	}

	function updateFilterTags(selectedKeys){
		if(!refs.filterTags) return;
		if(!selectedKeys.length){
			refs.filterTags.innerHTML = '<span class="text-muted small">No advanced filters selected</span>';
			return;
		}
		refs.filterTags.innerHTML = selectedKeys.map(key => {
			const label = FILTER_LABELS[key] || key;
			return `<button type="button" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1" data-remove-filter="${key}"><span>${escapeHtml(label)}</span><span aria-hidden="true">&times;</span></button>`;
		}).join('');
	}

	function renderDynamicFilters(selectedKeys){
		if(!refs.dynamicFilters) return;
		if(!selectedKeys.length){
			refs.dynamicFilters.innerHTML = FILTER_PLACEHOLDER;
			return;
		}
		const blocks = selectedKeys.map(key => filterBuilders[key]?.()).filter(Boolean);
		refs.dynamicFilters.innerHTML = blocks.length ? blocks.join('') : FILTER_PLACEHOLDER;
		registerFilterInputs(refs.dynamicFilters);
	}

	function init(){
		const initialSelection = getSelectedFilters();
		renderDynamicFilters(initialSelection);
		updateFilterTags(initialSelection);
		registerFilterInputs(refs.form);

		refs.limit.addEventListener('change', () => {
			state.limit = parseInt(refs.limit.value, 10) || 10;
			state.page = 1;
			fetchData();
		});
		refs.reset.addEventListener('click', resetFilters);

		refs.filterMenu?.addEventListener('change', () => {
			const selected = getSelectedFilters();
			renderDynamicFilters(selected);
			updateFilterTags(selected);
			state.filters = collectFilters();
			state.page = 1;
			updateActiveFilters();
			fetchData();
		});

		refs.filterTags?.addEventListener('click', (event) => {
			const btn = event.target.closest('[data-remove-filter]');
			if(!btn) return;
			const key = btn.getAttribute('data-remove-filter');
			const checkbox = refs.filterMenu?.querySelector(`[data-filter-option][value="${key}"]`);
			if(checkbox){
				checkbox.checked = false;
				checkbox.dispatchEvent(new Event('change', { bubbles: true }));
			}
		});

		refs.pagination.addEventListener('click', (event) => {
			const btn = event.target.closest('[data-page]');
			if(!btn) return;

			event.preventDefault();
			const page = parseInt(btn.getAttribute('data-page'), 10);
			if(page && page !== state.page){
				state.page = page;
				fetchData();
			}
		});

		refs.exportBtn?.addEventListener('click', () => {
			const btn = refs.exportBtn;
			const original = btn.innerHTML;
			btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generating…';
			window.open('<?=ROOT?>/<?=$adminRoute?>/<?=$pluginRoute?>/exportList_pdf', '_blank', 'width=900,height=700');
			setTimeout(() => btn.innerHTML = original, 2500);
		});
		fetchData();
	}

	function collectFilters(){
		const filters = {};
		refs.form.querySelectorAll('[data-filter]').forEach(input => {
			const value = (input.value || '').trim();
			if(value){
				filters[input.name] = value;
			}
		});
		return filters;
	}

	function updateActiveFilters(){
		const filters = state.filters;
		const keys = Object.keys(filters);
		if(!keys.length){
			refs.activeFilters.innerHTML = '<small class="text-muted">No filters applied • live feed</small>';
			return;
		}
		refs.activeFilters.innerHTML = keys.map(key => {
			const label = filterLabels[key] || key;
			return `<button type="button" class="btn btn-sm btn-light" data-clear="${key}">${label}: ${filters[key]} &times;</button>`;
		}).join('');
		refs.activeFilters.querySelectorAll('[data-clear]').forEach(btn => {
			btn.addEventListener('click', () => {
				const target = btn.getAttribute('data-clear');
				delete state.filters[target];
				const input = refs.form.querySelector(`[name="${target}"]`);
				if(input){ input.value = ''; }
				state.page = 1;
				updateActiveFilters();
				fetchData();
			});
		});
	}

	function handleFilterChange(){
		state.filters = collectFilters();
		state.page = 1;
		updateActiveFilters();
		fetchData();
	}

	function resetFilters(){
		refs.form.reset();
		const defaults = getSelectedFilters();
		renderDynamicFilters(defaults);
		updateFilterTags(defaults);
		state.filters = {};
		state.page = 1;
		updateActiveFilters();
		fetchData();
	}

	function toggleLoader(show){
		refs.tableLoader.classList.toggle('hidden', !show);
	}

	const formatCurrency = (value) => {
		const amount = Number(value) || 0;
		return new Intl.NumberFormat('en-GH', { style: 'currency', currency: 'GHS', minimumFractionDigits: 2 }).format(amount);
	};

	const formatNumber = (value) => new Intl.NumberFormat().format(Number(value) || 0);

	const formatDate = (value) => {
		if(!value) return '—';
		try {
			return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
		}catch (error){
			return value;
		}
	};

	function badgeClass(status){
		if(!status) return 'badge bg-secondary-subtle text-secondary';
		switch(status.toLowerCase()){
			case 'approved': return 'badge bg-success-subtle text-success';
			case 'pending': return 'badge bg-warning-subtle text-warning';
			case 'draft': return 'badge bg-secondary-subtle text-secondary';
			case 'rejected': return 'badge bg-danger-subtle text-danger';
			case 'declined': return 'badge bg-danger-subtle text-danger';
			default: return 'badge bg-secondary-subtle text-secondary';
		}
	}

	function reasonHtml(reason){
		const text = String(reason || '').trim();
		if(!text){
			return '';
		}
		return `<div class="small text-muted mt-1">Reason: ${escapeHtml(text)}</div>`;
	}

	function buildActions(row){
		const actions = [];
		if(permissions.view){
			actions.push(`<a href="${VIEW_BASE}/view/${row.id}" class="btn btn-outline-primary btn-sm"><i class="fa fa-eye"></i></a>`);
		}
		const statusSlug = (row.transaction_status || '').toLowerCase();
		if(permissions.edit && statusSlug !== 'declined'){
			actions.push(`<a href="${VIEW_BASE}/edit/${row.id}" class="btn btn-outline-warning btn-sm"><i class="fa fa-pen"></i></a>`);
		}
		const deletableStatuses = ['pending','draft'];
		if(permissions.delete && deletableStatuses.includes(statusSlug)){
			actions.push(`<a href="${VIEW_BASE}/delete/${row.id}" class="btn btn-outline-danger btn-sm"><i class="fa fa-trash"></i></a>`);
		}
		return actions.length ? actions.join(' ') : '<span class="text-muted small">No actions</span>';
	}

	function renderRows(rows){
		refs.tableBody.innerHTML = '';
		refs.empty.classList.toggle('d-none', rows.length !== 0);
		if(!rows.length){
			return;
		}
		rows.forEach((row, index) => {
			const desktopTr = document.createElement('tr');
			desktopTr.className = 'ez-row-desktop';
			desktopTr.innerHTML = `
				<td>
					<div class="d-flex flex-column">
						<strong>${state.limit * (state.page - 1) + index + 1}</strong>
						<small class="text-muted">${row.reference || '—'}</small>
					</div>
				</td>
				<td>${formatDate(row.date_created)}</td>
				<td>
					<div class="fw-semibold">${row.transaction_type || '—'}</div>
					<small class="text-muted">${row.network || 'Network n/a'}</small>
				</td>
				<td>
					<span class="${badgeClass(row.transaction_status)}">${row.transaction_status || '—'}</span>
					${reasonHtml(row.rejection_reason)}
				</td>
				<td class="text-end">
					<div>${formatCurrency(row.amount)}</div>
					<small class="text-muted">${formatCurrency(row.charge || 0)} fee</small>
				</td>
				<td>${row.phone || '—'}</td>
				<td>
					<div class="d-flex align-items-center gap-2">
						<img src="${row.agent?.image || '<?=get_image()?>'}" alt="agent" style="width:36px;height:36px;border-radius:50%;object-fit:cover;" />
						<div>
							<div class="fw-semibold">${row.agent?.name || '—'}</div>
							<small class="text-muted">ID: ${row.agent?.id || 'n/a'}</small>
						</div>
					</div>
				</td>
				<td class="text-end">${buildActions(row)}</td>
			`;

			const mobileTr = document.createElement('tr');
			mobileTr.className = 'ez-row-mobile';
			mobileTr.innerHTML = `
				<td colspan="8">
					<div class="ez-mobile-card">
						<div class="ez-mobile-grid">
							<div>
								<div class="ez-mobile-meta">
									<div>
										<div class="fw-bold">#${state.limit * (state.page - 1) + index + 1} <span class="text-muted">${row.reference || '—'}</span></div>
										<div class="ez-mobile-kv">${formatDate(row.date_created)}</div>
									</div>
									<div>
										<span class="${badgeClass(row.transaction_status)}">${row.transaction_status || '—'}</span>
										${reasonHtml(row.rejection_reason)}
									</div>
								</div>
								<div class="mt-2">
									<div class="fw-semibold">${row.transaction_type || '—'}</div>
									<div class="ez-mobile-kv">${row.network || 'Network n/a'} · ${row.phone || '—'}</div>
								</div>
								<div class="mt-2 d-flex align-items-center gap-2">
									<img src="${row.agent?.image || '<?=get_image()?>'}" alt="agent" style="width:34px;height:34px;border-radius:50%;object-fit:cover;" />
									<div>
										<div class="fw-semibold">${row.agent?.name || '—'}</div>
										<div class="ez-mobile-kv">ID: ${row.agent?.id || 'n/a'}</div>
									</div>
								</div>
							</div>
							<div>
								<div class="fw-semibold">${formatCurrency(row.amount)}</div>
								<div class="ez-mobile-kv">${formatCurrency(row.charge || 0)} fee</div>
							</div>
							<div class="ez-mobile-actions">${buildActions(row)}</div>
						</div>
					</div>
				</td>
			`;

			refs.tableBody.appendChild(desktopTr);
			refs.tableBody.appendChild(mobileTr);
		});
	}

	function renderPagination(pagination){
		if(!pagination){
			refs.pagination.innerHTML = '';
			return;
		}
		const { page, total_pages } = pagination;
		const createItem = (p, label, disabled = false, active = false) => `
			<li class="page-item ${disabled ? 'disabled' : ''} ${active ? 'active' : ''}">
				<a class="page-link" href="#" data-page="${p}">${label}</a>
			</li>`;
		const pages = [];
		pages.push(createItem(Math.max(1, page - 1), '&laquo;', page === 1));
		for(let i = 1; i <= total_pages; i++){
			if(i === 1 || i === total_pages || Math.abs(i - page) <= 1){
				pages.push(createItem(i, i, false, i === page));
			}else if(Math.abs(i - page) === 2){
				pages.push('<li class="page-item disabled"><span class="page-link">…</span></li>');
			}
		}
		pages.push(createItem(Math.min(total_pages, page + 1), '&raquo;', page === total_pages));
		refs.pagination.innerHTML = pages.join('');
	}

	function renderInsights(analysis = {}, totals = {}, latestActivity){
		refs.avgTicket.textContent = formatCurrency(analysis.avg_ticket || 0);
		refs.chargeRate.textContent = `${((analysis.charge_rate || 0) * 100).toFixed(1)}%`;
		refs.lastSyncInsight.textContent = latestActivity ? formatDate(latestActivity) : '—';
		refs.totalVolume.textContent = formatCurrency(totals.total_amount || 0);
		refs.totalCharge.textContent = formatCurrency(totals.total_charge || 0);
		refs.txCount.textContent = formatNumber(totals.count || 0);
		refs.latestActivity.textContent = latestActivity ? formatDate(latestActivity) : 'Awaiting sync';

		const listBuilder = (target, data, formatter) => {
			if(!target) return;
			target.innerHTML = data && data.length ? data.map(formatter).join('') : '<li class="text-muted small py-2">No data</li>';
		};

		listBuilder(refs.networkMix, analysis.network_breakdown || [], (net) => {
			const percent = ((net.percent || 0) * 100).toFixed(1);
			return `
				<li class="py-2">
					<div class="d-flex justify-content-between">
						<strong>${net.label || 'Unspecified'}</strong>
						<span>${percent}%</span>
					</div>
					<div class="ez-progress mt-1"><span style="width:${percent}%"></span></div>
					<small class="text-muted">${formatCurrency(net.amount)} · ${formatNumber(net.count)} tx</small>
				</li>`;
		});

		listBuilder(refs.statusBreakdown, analysis.status_breakdown || [], (item) => `
			<li class="py-2 d-flex justify-content-between align-items-center">
				<span>${item.label || 'Unknown'}</span>
				<div class="text-end">
					<strong>${formatNumber(item.count || 0)}</strong>
					<div class="text-muted small">${formatCurrency(item.amount || 0)}</div>
				</div>
			</li>`);

		refs.trend.innerHTML = (analysis.trend || []).map(day => `
			<tr>
				<td>${day.label}</td>
				<td>${formatCurrency(day.amount)}</td>
				<td>${formatNumber(day.count)}</td>
			</tr>`).join('') || '<tr><td colspan="3" class="text-muted">No trend data</td></tr>';
	}

	function renderPerformance(analysis = {}){
		const topStaff = analysis.top_agents || [];
		const topClients = analysis.top_clients || [];
		const statusSummary = analysis.status_summary || {};

		const toList = (target, data, buildAvatar) => {
			target.innerHTML = data.length ? data.map((item, index) => `
				<li class="py-2 d-flex justify-content-between align-items-center">
					<div class="d-flex align-items-center gap-2">
						<div class="ez-ranking-avatar">${buildAvatar(item, index)}</div>
						<div>
							<strong>${item.name || item.phone || '—'}</strong>
							<div class="text-muted small">${formatNumber(item.count || 0)} tx</div>
						</div>
					</div>
					<div class="text-end">
						<strong>${formatCurrency(item.total || 0)}</strong>
					</div>
				</li>`).join('') : '<li class="text-muted py-2">No data</li>';
		};

		toList(refs.topStaff, topStaff, (item, index) => (item.name || 'S')[0]?.toUpperCase() || index + 1);
		toList(refs.topClients, topClients, (item) => (item.phone || 'C')[0]);

		const summaries = Object.entries(statusSummary).map(([key, value]) => ({ key, ...value }));
		refs.statusSummary.innerHTML = summaries.length ? summaries.map(entry => `
			<div class="col-md-3 col-6">
				<div class="border rounded-4 p-3 h-100">
					<p class="text-uppercase small text-muted mb-1">${entry.key}</p>
					<h3 class="fw-bolder mb-0">${formatCurrency(entry.amount || 0)}</h3>
					<small class="text-muted">${formatNumber(entry.count || 0)} tx</small>
				</div>
			</div>`).join('') : '<div class="col-12"><p class="text-muted">No performance data.</p></div>';
	}

	async function fetchData(){
		refs.error.classList.add('d-none');
		refs.empty.classList.add('d-none');
		toggleLoader(true);
		const params = new URLSearchParams({
			page: state.page,
			limit: state.limit,
			...state.filters
		});
		try {
			const response = await fetch(`${DATA_ENDPOINT}?${params.toString()}`, {
				credentials: 'include',
				headers: { 'X-Requested-With': 'XMLHttpRequest' }
			});
			if(!response.ok){
				throw new Error('Unable to load data');
			}
			const payload = await response.json();
			if(!payload.success){
				throw new Error(payload.message || 'Unable to load data');
			}
			refs.resultCount.textContent = payload.rows.length;
			refs.totalCount.textContent = payload.pagination.total_rows || 0;
			refs.lastSync.textContent = payload.latest_activity ? formatDate(payload.latest_activity) : '—';
			renderRows(payload.rows || []);
			renderPagination(payload.pagination);
			renderInsights(payload.analysis, payload.totals, payload.latest_activity);
			renderPerformance(payload.analysis);
		}catch(error){
			refs.error.textContent = error.message || 'Unable to load data';
			refs.error.classList.remove('d-none');
			renderRows([]);
		}finally{
			toggleLoader(false);
		}
	}

	document.addEventListener('DOMContentLoaded', init);
})();
</script>
<?php else:?>
	<div class="alert alert-danger text-center">
		Access denied. You dont have permission for this action
	</div>
<?php endif?>
