<?php if(user_can('view_momoremit')):?>

<?php
$adminRoute = $admin_route ?? 'admin';
$pluginRoute = $plugin_route ?? 'airtimebundle';
$canViewDetails = user_can('view_category_details');
$canEdit = user_can('edit_category');
$canDelete = user_can('delete_category');

$session = get_session();
$currentUserId = (int)($session->id ?? 0);
$todayDate = (new \DateTimeImmutable('now', new \DateTimeZone('Africa/Accra')))->format('Y-m-d');

$agentOptions = $users ?? [];
?>

<style>
	.ab-page { background: var(--bs-body-bg); color: var(--bs-body-color); }
	.ab-hero { border-radius: 1.25rem; padding: 2rem; border: 1px solid var(--bs-border-color); box-shadow: 0 25px 45px rgba(15,23,42,.08); }
	.ab-hero h1 { font-weight: 600; }
	.ab-hero-metric { position:relative; border-radius: 1.25rem; padding: 1.5rem; background: var(--bs-card-bg, var(--bs-body-bg)); border: 1px solid var(--bs-border-color); box-shadow: 0 18px 40px rgba(15,23,42,.12); overflow:hidden; min-height: 150px; }
	.ab-hero-metric::before { content:""; position:absolute; inset:0; background: radial-gradient(circle at top left, rgba(250,204,21,.18), transparent 55%); opacity:.9; pointer-events:none; }
	.ab-hero-metric-inner { position:relative; z-index:1; display:flex; flex-direction:column; gap:.5rem; }
	.ab-hero-label { text-transform:uppercase; letter-spacing:.08em; font-size:.7rem; font-weight:600; color:var(--bs-secondary-color); }
	.ab-hero-main { display:flex; align-items:center; justify-content:space-between; gap:1rem; }
	.ab-hero-value { font-size:1.6rem; font-weight:700; }
	.ab-hero-meta { font-size:.85rem; color:var(--bs-secondary-color); }
	.ab-hero-icon { width:42px; height:42px; border-radius:999px; display:inline-flex; align-items:center; justify-content:center; background:rgba(252,211,77,.22); color:var(--bs-warning); box-shadow:0 10px 30px rgba(15,23,42,.18); }
	.ab-hero-metric--primary::before { background: radial-gradient(circle at top left, rgba(59,130,246,.22), transparent 58%); }
	.ab-hero-metric--primary .ab-hero-icon { background:rgba(59,130,246,.18); color:var(--bs-primary); }
	.ab-hero-metric--success::before { background: radial-gradient(circle at top left, rgba(16,185,129,.22), transparent 58%); }
	.ab-hero-metric--success .ab-hero-icon { background:rgba(16,185,129,.18); color:var(--bs-success); }

	.ab-pulse { display: inline-flex; align-items: center; gap: .35rem; font-size: .85rem; color: var(--bs-success); }
	.ab-pulse::before { content: ""; width: 8px; height: 8px; border-radius: 999px; background: currentColor; animation: abPulse 1.6s infinite; }
	@keyframes abPulse { 0%{opacity:.2;transform:scale(.8);}50%{opacity:1;transform:scale(1.1);}100%{opacity:.2;transform:scale(.8);} }
	.ab-actions .btn { border-radius: 999px; font-weight: 500; }
	.ab-filters label { font-size: .78rem; text-transform: uppercase; letter-spacing: .08em; font-weight: 600; }
	.ab-tab-card { border-radius: 1.25rem; border: 1px solid var(--bs-border-color); box-shadow: 0 25px 45px rgba(15,23,42,.08); }

	.active,
	#abTabs .nav-link.active,
	#abTabs .nav-link.active:focus,
	#abTabs .nav-link.active:hover,
	#abTabs .nav-item.show .nav-link,
	#abTabs .nav-item.show .nav-link:focus,
	#abTabs .nav-item.show .nav-link:hover {
		background-image: linear-gradient(135deg, rgba(var(--bs-primary-rgb, 13,110,253),0.12), rgba(var(--bs-warning-rgb, 255,193,7),0.12)) !important;
		background-color: transparent !important;
		color: var(--bs-body-color) !important;
		border-color: rgba(var(--bs-primary-rgb,13,110,253), 0.25) !important;
	}

	.ab-filter-card { border-radius: 1rem; border: 1px solid rgba(15,23,42,.08); padding: 1rem; background: var(--bs-body-bg); box-shadow: 0 15px 35px rgba(15,23,42,.06); height: 100%; }
	.ab-filter-card-header { display:flex; justify-content:space-between; align-items:flex-start; gap:.75rem; }
	.ab-filter-title { font-weight:600; }
	.ab-selected-filters { background: rgba(15,23,42,.02); border: 1px dashed rgba(15,23,42,.15); border-radius: 1rem; padding: 1rem; }
	.ab-filter-empty { color: var(--bs-secondary-color); font-size: .9rem; }
	.ab-table thead th { text-transform: uppercase; font-size: .72rem; letter-spacing: .08em; border-top: 0; border-bottom: 1px solid var(--bs-border-color); background: rgba(var(--bs-secondary-rgb,148,163,184),.12); color: var(--bs-secondary-color); padding-top:.65rem; padding-bottom:.55rem; }
	.ab-table tbody tr { transition: background-color .15s ease, transform .05s ease; }
	.ab-table tbody tr:hover { background: rgba(var(--bs-primary-rgb,13,110,253),.05); }
	.ab-table tbody td { vertical-align: middle; border-color: rgba(148,163,184,.18); padding-top:.55rem; padding-bottom:.55rem; font-size:.86rem; }
	.ab-table tfoot th { border-top:1px solid var(--bs-border-color); font-weight:600; }
	.ab-mobile-details { min-width: 260px; }
	.ab-mobile-amounts { min-width: 140px; }
	.ab-amount-stack { display:flex; flex-direction:column; gap:2px; align-items:flex-end; }

	.ab-loader { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; background: rgba(255,255,255,.85); backdrop-filter: blur(3px); z-index:3; transition: opacity .2s ease; }
	.ab-loader.hidden { opacity:0; pointer-events:none; }
	.ab-empty, .ab-error { padding:3rem 1rem; text-align:center; }
	.ab-error { color: var(--bs-danger); }
	.ab-list-avatar { width:40px; height:40px; border-radius:999px; display:inline-flex; align-items:center; justify-content:center; background: rgba(var(--bs-warning-rgb,255,193,7),.2); color: var(--bs-warning); font-weight:600; }
	.ab-analysis-card { border-radius:1rem; border:1px solid var(--bs-border-color); padding:1.25rem; height:100%; background: linear-gradient(135deg, rgba(15,23,42,.02), rgba(15,23,42,.08)); backdrop-filter: blur(2px); }
	body[data-bs-theme="dark"] .ab-analysis-card { background: radial-gradient(circle at top left, rgba(30,64,175,.34), rgba(15,23,42,1)); }
	.ab-progress { height:8px; border-radius:999px; background: rgba(var(--bs-primary-rgb,13,110,253),.1); overflow:hidden; }
	.ab-progress span { display:block; height:100%; background: var(--bs-primary); }
	.ab-insights-pane { background: var(--bs-card-bg, var(--bs-body-bg)); border-radius: 1.25rem; padding: 1.5rem; border:1px solid var(--bs-border-color); box-shadow: 0 18px 40px rgba(15,23,42,.16); }

	.ab-list-group { list-style:none; padding-left:0; margin-bottom:0; }
	.ab-list-group li+li { border-top:1px solid rgba(148,163,184,.2); }
	@media(max-width:992px){ .ab-hero{padding:1.5rem;} }
	@media (min-width:768px) and (max-width:991.98px){
		.ab-hero-metric{min-height:unset;}
		.ab-hero-value{font-size:1.35rem;}
		.ab-hero-icon{width:38px;height:38px;}
	}
</style>

<div class="ab-page">
	<div class="ab-hero mb-4">
		<div class="row align-items-center g-3">
			<div class="col-lg-8">
				<p class="text-uppercase text-muted mb-1 small fw-semibold">Airtime & bundles overview</p>
				<h1 class="mb-2">Transactions cockpit</h1>
				<p class="mb-0 text-secondary">Search faster, layer filters, and track staff & client performance live.</p>
			</div>
			<div class="col-lg-4 text-lg-end">
				<div class="ab-actions d-flex flex-wrap justify-content-lg-end gap-2">
					<div class="dropdown">
						<button class="btn btn-warning text-dark dropdown-toggle" type="button" id="abAdd" data-bs-toggle="dropdown" aria-expanded="false">
							<i class="fa fa-plus me-1"></i> Add
						</button>
						<ul class="dropdown-menu" aria-labelledby="abAdd">
							<li><a class="dropdown-item" href="<?=ROOT?>/<?=$adminRoute?>/<?=$pluginRoute?>/airtime"><i class="fa fa-phone-alt text-warning me-2"></i>Airtime</a></li>
							<li><a class="dropdown-item" href="<?=ROOT?>/<?=$adminRoute?>/<?=$pluginRoute?>/bundle"><i class="fa fa-wifi text-warning me-2"></i>Data bundle</a></li>
						</ul>
					</div>
                    <a class="btn btn-outline-primary" href="<?=ROOT?>/<?=$adminRoute?>/<?=$pluginRoute?>/denos_audit"><i class="fa fa-clipboard-check me-2"></i>Denos audit</a>
					<button id="abExportBtn" class="btn btn-outline-danger"><i class="fa fa-file-pdf me-2"></i>Export PDF</button>
				</div>
			</div>
		</div>
		<div class="row g-3 mt-1">
			<div class="col-12 col-sm-6 col-lg-4">
				<div class="ab-hero-metric ab-hero-metric--primary h-100">
					<div class="ab-hero-metric-inner">
						<div class="d-flex justify-content-between align-items-start">
							<span class="ab-hero-label">Total volume</span>
							<div class="ab-hero-icon">
								<i class="fa fa-signal"></i>
							</div>
						</div>
						<div class="ab-hero-main">
							<div class="ab-hero-value text-primary" data-ab-total-amount>GHS 0.00</div>
						</div>
						<div class="ab-hero-meta" data-ab-count>0 transactions</div>
					</div>
				</div>
			</div>
			<div class="col-12 col-sm-6 col-lg-4">
				<div class="ab-hero-metric ab-hero-metric--success h-100">
					<div class="ab-hero-metric-inner">
						<div class="d-flex justify-content-between align-items-start">
							<span class="ab-hero-label">Total commission</span>
							<div class="ab-hero-icon">
								<i class="fa fa-coins"></i>
							</div>
						</div>
						<div class="ab-hero-main">
							<div class="ab-hero-value text-warning" data-ab-total-commission>GHS 0.00</div>
						</div>
						<div class="ab-hero-meta">Fees earned on airtime &amp; bundles.</div>
					</div>
				</div>
			</div>
			<div class="col-12 col-lg-4">
				<div class="ab-hero-metric h-100">
					<div class="ab-hero-metric-inner">
						<div class="d-flex justify-content-between align-items-start">
							<span class="ab-hero-label">Latest activity</span>
							<div class="ab-hero-icon">
								<i class="fa fa-broadcast-tower"></i>
							</div>
						</div>
						<div class="ab-hero-main">
							<div class="ab-hero-value" data-ab-latest-activity>Awaiting sync</div>
						</div>
						<span class="ab-pulse">Live</span>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Filters bar -->
	<div class="card border-0 shadow-sm mt-3 ab-filters">
		<div class="card-body">
			<form id="abFilterForm" class="row g-3 align-items-end">
				<div class="col-12 col-md-8 col-lg-4">
					<label class="form-label">Search</label>
					<div class="input-group rounded-pill overflow-hidden border">
						<span class="input-group-text bg-transparent border-0 ps-3"><i class="fa fa-search text-muted"></i></span>
						<input type="text" class="form-control border-0" id="abSearch" placeholder="Ref, phone, network, status">
					</div>
				</div>
				<div class="col-6 col-md-2 col-lg-2">
					<label class="form-label">Per page</label>
					<select class="form-select w-auto" id="abLimit">
						<option value="10">10</option>
						<option value="25" selected>25</option>
						<option value="50">50</option>
						<option value="100">100</option>
					</select>
				</div>
				<div class="col-6 col-md-2 col-lg-2 d-flex gap-2">
					<button type="button" id="abReset" class="btn btn-outline-secondary flex-fill">Reset</button>
					<button type="submit" class="btn btn-warning flex-fill d-none d-lg-inline-block">Apply</button>
				</div>
				<div class="col-12 col-lg-4 d-none d-lg-block">
					<label class="form-label">Advanced filters</label>
					<div class="dropdown w-100" data-bs-auto-close="outside">
						<button class="btn btn-outline-secondary w-100 d-flex justify-content-between align-items-center" type="button" data-bs-toggle="dropdown" aria-expanded="false">
							<span><i class="fa fa-sliders me-2"></i>Configure filters</span>
							<i class="fa fa-chevron-down text-muted"></i>
						</button>
						<div class="dropdown-menu w-100 p-3" id="abFilterMenu">
							<p class="text-muted small mb-2">Toggle which dimensions you want to filter by, then pick values below.</p>
							<div class="row g-2">
								<div class="col-6">
									<div class="form-check mb-1">
										<input class="form-check-input" type="checkbox" value="date" id="abOptDate" data-filter-toggle="date">
										<label class="form-check-label" for="abOptDate">Date range</label>
									</div>
									<div class="form-check mb-1">
										<input class="form-check-input" type="checkbox" value="created_by" id="abOptStaff" data-filter-toggle="created_by">
										<label class="form-check-label" for="abOptStaff">Staff</label>
									</div>
									<div class="form-check mb-1">
										<input class="form-check-input" type="checkbox" value="transaction_type" id="abOptType" data-filter-toggle="transaction_type">
										<label class="form-check-label" for="abOptType">Type</label>
									</div>
								</div>
								<div class="col-6">
									<div class="form-check mb-1">
										<input class="form-check-input" type="checkbox" value="transaction_status" id="abOptStatus" data-filter-toggle="transaction_status">
										<label class="form-check-label" for="abOptStatus">Status</label>
									</div>
									<div class="form-check mb-1">
										<input class="form-check-input" type="checkbox" value="network" id="abOptNetwork" data-filter-toggle="network">
										<label class="form-check-label" for="abOptNetwork">Network</label>
									</div>
									<div class="form-check mb-1">
										<input class="form-check-input" type="checkbox" value="phone" id="abOptPhone" data-filter-toggle="phone">
										<label class="form-check-label" for="abOptPhone">Client phones</label>
									</div>
								</div>
							</div>
							<hr class="my-2" />
							<div class="small text-muted">Active filter values appear as chips below the bar.</div>
						</div>
					</div>
				</div>
				<div class="col-12 mt-1 d-none d-lg-block">
					<div class="ab-selected-filters d-flex flex-wrap gap-2" id="abFilterChips">
						<span class="ab-filter-empty"><i class="fa fa-circle-xmark me-1"></i>No advanced filters applied.</span>
					</div>
				</div>
				<div class="col-12 d-none d-lg-block">
					<div class="row g-2" id="abFilterFields"></div>
				</div>
			</form>
		</div>
	</div>

	<!-- preloader -->
	<div id="preloader" class="preloader">
		<span class="word">L</span>
		<span class="word">o</span>
		<span class="word">a</span>
		<span class="word">d</span>
		<span class="word">i</span>
		<span class="word">n</span>
		<span class="word">g</span>
	</div>

	<!-- Tabs: Transactions (landing) & Insights -->
	<div class="card border-0 shadow-sm ab-tab-card mt-3">
		<div class="card-header border-0 pb-0 bg-transparent">
			<ul class="nav nav-tabs card-header-tabs" id="abTabs" role="tablist">
				<li class="nav-item" role="presentation">
					<button class="nav-link active" id="ab-tab-transactions" data-bs-toggle="tab" data-bs-target="#abPaneTransactions" type="button" role="tab" aria-controls="abPaneTransactions" aria-selected="true">
						<i class="fa fa-table me-1"></i> Transactions
					</button>
				</li>
				<li class="nav-item" role="presentation">
					<button class="nav-link" id="ab-tab-insights" data-bs-toggle="tab" data-bs-target="#abPaneInsights" type="button" role="tab" aria-controls="abPaneInsights" aria-selected="false">
						<i class="fa fa-chart-line me-1"></i> Insights & performance
					</button>
				</li>
			</ul>
		</div>
		<div class="card-body tab-content">
			<!-- Transactions tab -->
			<div class="tab-pane fade show active" id="abPaneTransactions" role="tabpanel" aria-labelledby="ab-tab-transactions">
				<div class="table-responsive">
					<table class="table table-striped table-hover tableContainer ab-table">
						<thead>
							<tr>
								<th class="d-none d-lg-table-cell"><span class="badge bg-warning text-black rounded-circle pill">#</span> Trans  Id</th>
								<th class="d-none d-lg-table-cell">Date created</th>
								<th class="d-none d-lg-table-cell">Type</th>
								<th class="d-none d-lg-table-cell">Phone #</th>
								<th class="d-none d-lg-table-cell">Amount</th>
								<th class="d-none d-lg-table-cell">Balance After</th>
								<th class="d-none d-lg-table-cell">Status</th>
								<th class="d-none d-lg-table-cell">Created By</th>
								<th class="d-lg-none">Details</th>
								<th class="text-end d-lg-none">Amounts</th>
								<th>		
						  		<script type="text/javascript">
									function exportPdf() {
									    const btn = document.querySelector('.export-pdf-btn');
									    if(btn){
									      btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Generating...';
									    }
									    window.open(
									      `<?= ROOT ?>/admin/airtimebundle/exportList_pdf`,
									      'pdf_window',
									      'width=800,height=600'
									    );
									    setTimeout(() => {
									      if(btn){
									        btn.innerHTML = '<i class="fa fa-file-pdf"></i> Export to PDF';
									      }
									    }, 5000);
						  		}
						  		</script>	
								</th>
							</tr>
						</thead>
						
						<tbody id="activityTable">
							<?php if(!empty($rows)): $id = 0;?>
								<?php foreach($rows as $row): $id ++;?>
									<?php
										$badgeClass = '';
										switch (strtolower($row->transaction_status)) {
											case 'pending':
												$badgeClass = 'badge bg-warning text-black text-dark';
												break;
											case 'sent':
												$badgeClass = 'badge bg-success';
												break;
											case 'rejected':
												$badgeClass = 'badge bg-danger';
												break;
											default:
												$badgeClass = 'badge bg-secondary';
												break;
										}
									?>
									<tr>
										<td class="d-none d-lg-table-cell"><span class="badge bg-warning text-black text-black rounded-circle pill"><?=$id?></span><?=$row->id?></td>
										<td class="d-none d-lg-table-cell"><?=get_date_time($row->date_created)?></td>
										<td class="d-none d-lg-table-cell">
											<a style="text-decoration: none;" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$row->id?>">
												<?=esc($row->network ?? '' )?> (	<?= $row->category ?? ''?>)
											</a>
										</td>
										<td class="d-none d-lg-table-cell"><?=esc($row->phone ?? '')?></td>
										<td class="d-none d-lg-table-cell"><?=number_format((float)($row->amount ?? 0), 2)?></td>
										<td class="d-none d-lg-table-cell"><?=number_format((float)($row->balance_after ?? 0), 2)?></td>
										<td class="d-none d-lg-table-cell">
											<span class="<?= $badgeClass ?>"><?=esc($row->transaction_status ?? 'N/A')?></span>
											<?php if(!empty($row->rejection_reason)):?>
												<div class="small text-muted mt-1">Reason: <?=esc($row->rejection_reason)?></div>
											<?php endif;?>
										</td>
									
										<td class="d-none d-lg-table-cell">
											<span><img src="<?=get_image($row->user_image)?>" style="width: 30px; height: 30px; object-fit: cover; border-radius: 50%;"></span>
											 <?=$row->user_first_name . ' ' . $row->user_last_name ?>
										</td>
										<td class="d-lg-none ab-mobile-details">
											<div class="fw-semibold">#<?=$row->id?> · <?=get_date_time($row->date_created)?></div>
											<div class="text-muted small"><?=esc($row->transaction_type ?? '')?></div>
											<div>
												<a style="text-decoration: none;" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$row->id?>">
													<?=esc($row->network ?? '' )?> (<?= $row->category ?? ''?>)
											</a>
											</div>
											<div class="text-muted small"><?=esc($row->phone ?? '')?></div>
											<div class="mt-1">
												<span class="<?= $badgeClass ?>"><?=esc($row->transaction_status ?? 'N/A')?></span>
												<?php if(!empty($row->rejection_reason)):?>
													<div class="small text-muted mt-1">Reason: <?=esc($row->rejection_reason)?></div>
												<?php endif;?>
											</div>
										</td>
										<td class="text-end d-lg-none ab-mobile-amounts">
											<div class="ab-amount-stack">
												<div>
													<small class="text-muted d-block">Amount</small>
													<span class="fw-semibold"><?=number_format((float)($row->amount ?? 0), 2)?></span>
												</div>
												<div>
													<small class="text-muted d-block">Balance After</small>
													<span class="fw-semibold"><?=number_format((float)($row->balance_after ?? 0), 2)?></span>
												</div>
											</div>
										</td>
									
										<td>
											<?php if(user_can('view_category_details')):?>
											<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$row->id?>">
												<button class="btn btn-primary btn-sm" title="View">
													<i class="fa-solid fa-eye"></i> 
												</button>
											</a>
											<?php endif?>				
											
											<?php if ($canEdit && !in_array(strtolower(trim((string)($row->transaction_status ?? ''))), ['cancelled','canceled','declined','rejected','reversed'], true)):?>
												<?php if($row->transaction_type === 'Data bundle'):?>
												<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/bundle_edit/<?=$row->id?>">
													<button class="btn btn-warning btn-sm" title="Edit">
														<i class="fa-solid fa-pen-to-square"></i>
													</button>
												</a>
												<?php elseif($row->transaction_type === 'Airtime'):?>
												<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/airtime_edit/<?=$row->id?>">
													<button class="btn btn-warning btn-sm" title="Edit">
														<i class="fa-solid fa-pen-to-square"></i>
													</button>
												</a>
												<?php endif;?>
												<?php if($canDelete && in_array(strtolower($row->transaction_status ?? ''), ['pending','draft'], true) && ($currentUserId > 0) && ((int)($row->created_by ?? 0) === $currentUserId || (int)($row->assigned ?? 0) === $currentUserId || $canDelete)):?>
												<a href="<?=ROOT?>/<?=$admin_route?>/<?=$pluginRoute?>/delete/<?=$row->id?>">
													<button class="btn btn-danger btn-sm" title="Delete">
														<i class="fa-solid fa-trash"></i>
													</button>
												</a>
												<?php endif;?>
											<?php endif;?>
											<?php if(substr(trim((string)($row->date_created ?? '')), 0, 10) === $todayDate && !in_array(strtolower(trim((string)($row->transaction_status ?? ''))), ['cancelled','canceled','declined','rejected','reversed'], true)):?>
												<button type="button" class="btn btn-outline-danger btn-sm js-direct-cancel" data-id="<?= (int)$row->id ?>" data-cancel-date="<?= htmlspecialchars(substr(trim((string)($row->date_created ?? '')), 0, 10), ENT_QUOTES, 'UTF-8') ?>" data-cancel-endpoint="<?=ROOT?>/<?=$admin_route?>/<?=$pluginRoute?>/cancel" data-cancel-label="Transaction #<?= (int)$row->id ?>" aria-label="Cancel transaction #<?= (int)$row->id ?>" title="Cancel transaction"><i class="fa-solid fa-arrow-rotate-left" aria-hidden="true"></i></button>
											<?php endif;?>
											</td>
									</tr>
								<?php endforeach?>
								<?php else:?>
									<tr>
										<td colspan="11" class="text-center justify-content-center"> No records to show!</td>
									</tr>
								<?php endif?>			
						</tbody>				
							<tfoot id="tableFooter">
								<tr>
									<th class="d-none d-lg-table-cell">#</th>
									<th class="d-none d-lg-table-cell">TOTAL :</th>
									<th class="d-none d-lg-table-cell"></th>
									<th class="d-none d-lg-table-cell"></th>
									<th class="d-none d-lg-table-cell"><?=number_format((float)($total_amount ?? 0) , 2)?></th>
									<th class="d-none d-lg-table-cell"><?=number_format((float)($total_balance_after ?? 0) , 2)?></th>
									<th class="d-none d-lg-table-cell"></th>
									<th class="d-none d-lg-table-cell"></th>
									<th class="d-none d-lg-table-cell"></th>
									<th class="d-lg-none text-end" colspan="3">
										TOTAL: <?=number_format((float)($total_amount ?? 0) , 2)?> · <?=number_format((float)($total_balance_after ?? 0) , 2)?>
									</th>
								</tr>
							</tfoot>	
					</table>
	   			<p id="totalPagesText" class="text-center mt-3"></p>

	   			<!-- Pagination -->
	   			<nav id="paginationNav" aria-label="Page navigation" class="mt-1">
	   			  <ul class="pagination justify-content-center" style="font-size: 8px;"></ul>
	   			</nav>
			</div>
			</div>

			<!-- Insights & rankings tab pane -->
			<div class="tab-pane fade" id="abPaneInsights" role="tabpanel" aria-labelledby="ab-tab-insights">
				<div class="ab-insights-pane mt-3">
					<div class="row g-3">
						<div class="col-lg-4">
							<div class="ab-analysis-card h-100">
								<p class="text-uppercase text-muted small mb-1">Avg ticket</p>
								<h4 data-ab-avg-ticket>GHS 0.00</h4>
								<p class="text-muted mb-0">Average value per airtime/bundle transaction.</p>
							</div>
						</div>
						<div class="col-lg-4">
							<div class="ab-analysis-card h-100">
								<p class="text-uppercase text-muted small mb-1">Commission rate</p>
								<h4 data-ab-commission-rate>0%</h4>
								<p class="text-muted mb-0">Commission relative to total volume.</p>
							</div>
						</div>
						<div class="col-lg-4">
							<div class="ab-analysis-card h-100">
								<p class="text-uppercase text-muted small mb-1">Latest sync</p>
								<h4 data-ab-last-sync>—</h4>
								<p class="text-muted mb-0">Timestamp from the live data endpoint.</p>
							</div>
						</div>
					</div>
					<div class="row g-3 mt-1">
						<div class="col-xl-4">
							<div class="ab-analysis-card h-100">
								<h5 class="mb-2">Top staff</h5>
								<ul class="ab-list-group" id="abTopAgents"></ul>
							</div>
						</div>
						<div class="col-xl-4">
							<div class="ab-analysis-card h-100">
								<h5 class="mb-2">Top clients (phone)</h5>
								<ul class="ab-list-group" id="abTopClients"></ul>
							</div>
						</div>
						<div class="col-xl-4">
							<div class="ab-analysis-card h-100">
								<h5 class="mb-2">Status breakdown</h5>
								<ul class="ab-list-group" id="abStatusBreakdown"></ul>
							</div>
						</div>
					</div>
					<div class="ab-analysis-card mt-3">
						<h5 class="mb-2">7-day trend</h5>
						<div class="table-responsive">
							<table class="table table-sm mb-0">
								<thead>
									<tr>
										<th>Date</th>
										<th>Volume</th>
										<th>Transactions</th>
									</tr>
								</thead>
								<tbody id="abTrend"></tbody>
							</table>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	
	    <!-- Scripts -->
	    <script>
	    (function(){
	    	const DATA_ENDPOINT = '<?=ROOT?>/<?=$adminRoute?>/<?=$pluginRoute?>/data';
	    	const VIEW_BASE = '<?=ROOT?>/<?=$adminRoute?>/<?=$pluginRoute?>';
	    	const FILTER_DEBOUNCE_MS = 600;
	    	const refs = {
	    		body: document.getElementById('activityTable'),
	    		footer: document.getElementById('tableFooter'),
	    		pagination: document.querySelector('#paginationNav .pagination'),
	    		preloader: document.getElementById('preloader'),
	    		statAmount: document.querySelector('[data-ab-total-amount]'),
	    		statCommission: document.querySelector('[data-ab-total-commission]'),
	    		statCount: document.querySelector('[data-ab-count]'),
	    		latestActivity: document.querySelector('[data-ab-latest-activity]'),
	    		avgTicket: document.querySelector('[data-ab-avg-ticket]'),
	    		commissionRate: document.querySelector('[data-ab-commission-rate]'),
	    		lastSync: document.querySelector('[data-ab-last-sync]'),
	    		topAgents: document.getElementById('abTopAgents'),
	    		topClients: document.getElementById('abTopClients'),
	    		statusBreakdown: document.getElementById('abStatusBreakdown'),
	    		trend: document.getElementById('abTrend'),
	    		filterForm: document.getElementById('abFilterForm'),
	    		search: document.getElementById('abSearch'),
	    		limit: document.getElementById('abLimit'),
	    		filterChips: document.getElementById('abFilterChips'),
	    		filterFields: document.getElementById('abFilterFields')
	    	};

	    	const state = {
	    		page: 1,
	    		limit: 25,
	    		find: '',
	    		filters: {
	    			date_from: '',
	    			date_to: '',
	    			created_by: [],
	    			transaction_type: [],
	    			transaction_status: [],
	    			network: [],
	    			phone: []
	    		}
	    	};

	    	let debounceTimer = null;
	    	function scheduleFetch(includeMeta = true){
	    		if(debounceTimer){
	    			clearTimeout(debounceTimer);
	    		}
	    		debounceTimer = setTimeout(() => {
	    			fetchData({ includeMeta });
	    		}, FILTER_DEBOUNCE_MS);
	    	}

	    	function applyStateToUI(){
	    		if(refs.search) refs.search.value = state.find || '';
	    		if(refs.limit) refs.limit.value = String(state.limit || 25);
	    		const from = document.getElementById('abDateFrom');
	    		const to = document.getElementById('abDateTo');
	    		if(from) from.value = state.filters.date_from || '';
	    		if(to) to.value = state.filters.date_to || '';
	    		['created_by','transaction_type','transaction_status','network','phone'].forEach(key => {
	    			const selected = new Set((state.filters[key] || []).map(v => String(v)));
	    			const checks = document.querySelectorAll(`[data-filter-multi="${key}"]`);
	    			checks.forEach(c => { c.checked = selected.has(String(c.value)); });
	    		});
	    	}

	    	function formatCurrency(value){
	    		return new Intl.NumberFormat('en-GH',{ style:'currency', currency:'GHS' }).format(Number(value || 0));
	    	}

	    	function formatDate(value){
	    		if(!value) return '';
	    		return new Date(value).toLocaleString('en-GB',{ dateStyle:'medium', timeStyle:'short' });
	    	}

	    	function escapeHtml(value){
	    		return String(value ?? '')
	    			.replace(/&/g, '&amp;')
	    			.replace(/</g, '&lt;')
	    			.replace(/>/g, '&gt;')
	    			.replace(/"/g, '&quot;')
	    			.replace(/'/g, '&#039;');
	    	}

	    	function reasonHtml(reason){
	    		const text = String(reason || '').trim();
	    		if(!text){
	    			return '';
	    		}
	    		return `<div class="small text-muted mt-1">Reason: ${escapeHtml(text)}</div>`;
	    	}

	    	function buildQuery(includeMeta = true){
	    		const params = new URLSearchParams({ page: state.page, limit: state.limit });
	    		params.append('include_meta', includeMeta ? '1' : '0');
	    		if(state.find){ params.append('find', state.find); }
	    		if(state.filters.date_from){ params.append('date_from', state.filters.date_from); }
	    		if(state.filters.date_to){ params.append('date_to', state.filters.date_to); }
	    		['created_by','transaction_type','transaction_status','network','phone'].forEach(key => {
	    			(state.filters[key] || []).forEach(val => { if(val) params.append(key+'[]', val); });
	    		});
	    		return params.toString();
	    	}

	    	function setLoading(show){
	    		if(!refs.preloader) return;
	    		refs.preloader.classList.toggle('visible', !!show);
	    	}

	    	async function fetchData(options = {}){
	    		const includeMeta = options.includeMeta !== false;
	    		const background = options.background === true;
	    		if(!background){
	    			setLoading(true);
	    		}

	    		try {
	    			const res = await fetch(`${DATA_ENDPOINT}?${buildQuery(includeMeta)}`, { headers:{ 'Accept':'application/json' }});
	    			if(!res.ok){
	    				const text = await res.text().catch(()=>'');
	    				console.error('Airtimebundle /data HTTP error', res.status, text);
	    				return;
	    			}
	    			const payload = await res.json();
	    			if(!payload.success){
	    				console.error('Airtimebundle /data error', payload);
	    				return;
	    			}
	    			applyPayload(payload, includeMeta);
	    		}catch(err){
	    			console.error('Airtimebundle /data exception', err);
	    		}finally{
	    			if(!background){
	    				setLoading(false);
	    			}
	    		}
	    	}

	    	function applyPayload(payload, includeMeta = true){
	    		const { rows, totals, pagination, latest_activity, rankings, statistics, analysis, filter_options } = payload;
	    		const hasMeta = includeMeta && payload.meta_included !== false;

	    		if(refs.statAmount) refs.statAmount.textContent = formatCurrency(totals?.total_amount || 0);

	    		if(refs.statCommission) refs.statCommission.textContent = formatCurrency(totals?.total_commission || 0);
	    		if(refs.statCount) refs.statCount.textContent = `${totals?.count || 0} transactions`;
	    		if(refs.latestActivity) refs.latestActivity.textContent = latest_activity ? formatDate(latest_activity) : '—';
	    		if(hasMeta && refs.avgTicket) refs.avgTicket.textContent = formatCurrency(statistics?.avg_ticket || 0);
	    		if(hasMeta && refs.commissionRate) refs.commissionRate.textContent = `${((statistics?.commission_rate || 0)*100).toFixed(1)}%`;
	    		if(refs.lastSync) refs.lastSync.textContent = statistics?.latest_activity ? formatDate(statistics.latest_activity) : (latest_activity ? formatDate(latest_activity) : '—');

	    		state.page = pagination?.page || 1;
	    		state.limit = pagination?.limit || state.limit;
	    		renderRows(rows || []);
	    		renderFooter(totals || {});
	    		renderPagination(pagination || { page:1, total_pages:1 });
	    		if(hasMeta){
	    			renderRankings(rankings || {});
	    			renderStatusBreakdown(analysis?.status_breakdown || []);
	    			renderTrend(analysis?.trend || []);
	    			populateOptionLists(filter_options || null);
	    		}
	    		applyStateToUI();
	    	}

	    	function renderRows(rows){
	    		if(!refs.body) return;
	    		refs.body.innerHTML = '';
	    		if(!rows.length){
	    			const tr = document.createElement('tr');
	    			tr.innerHTML = '<td colspan="11" class="text-center">No records to show!</td>';
	    			refs.body.appendChild(tr);
	    			return;
	    		}
	    		rows.forEach((row, index) => {
	    			const tr = document.createElement('tr');

	    			const status = String(row.transaction_status || '').toLowerCase();
	    			let badgeClass = 'badge bg-secondary';
	    			if(status === 'pending') badgeClass = 'badge bg-warning text-black text-dark';
	    			else if(status === 'sent' || status === 'approved') badgeClass = 'badge bg-success';
	    			else if(status === 'rejected' || status === 'reversed' || status === 'cancelled' || status === 'canceled') badgeClass = 'badge bg-danger';
	    			const viewUrl = `${VIEW_BASE}/view/${row.id}`;
	    				let editHtml = '';
	    				let deleteHtml = '';
	    				if(<?= $canEdit ? 'true' : 'false' ?> && !['cancelled','canceled','declined','rejected','reversed'].includes(status)){
	    					const editPath = row.transaction_type === 'Data bundle' ? 'bundle_edit' : 'airtime_edit';
	    					editHtml = `<a href="${VIEW_BASE}/${editPath}/${row.id}"><button class="btn btn-warning btn-sm" title="Edit"><i class="fa-solid fa-pen-to-square"></i></button></a>`;
	    				}
	    				if((row.transaction_status === 'Pending' || row.transaction_status === 'Draft')
	    					&& <?= $canDelete ? 'true' : 'false' ?>
	    					&& (Number(row.created_by || 0) === <?= (int)$currentUserId ?> || Number(row.assigned || 0) === <?= (int)$currentUserId ?> || <?= $canDelete ? 'true' : 'false' ?>)){
	    					deleteHtml = `<a href="${VIEW_BASE}/delete/${row.id}"><button class="btn btn-danger btn-sm" title="Delete"><i class="fa-solid fa-trash"></i></button></a>`;
	    				}
	    				const cancelHtml = ['cancelled','canceled','declined','rejected','reversed'].includes(status)
	    					? ''
	    					: `<button type="button" class="btn btn-outline-danger btn-sm js-direct-cancel" data-id="${row.id}" data-cancel-date="${String(row.date_created || '').slice(0, 10)}" data-cancel-endpoint="${VIEW_BASE}/cancel" data-cancel-label="Transaction #${row.id}" aria-label="Cancel transaction #${row.id}" title="Cancel transaction"><i class="fa-solid fa-arrow-rotate-left" aria-hidden="true"></i></button>`;
	    				tr.innerHTML = `
	    				<td class="d-none d-lg-table-cell"><span class="badge bg-warning text-black text-black rounded-circle pill">${((state.page-1)*state.limit)+index+1}</span>${row.id}</td>
	    				<td class="d-none d-lg-table-cell">${formatDate(row.date_created)}</td>
	    				<td class="d-none d-lg-table-cell"><a style="text-decoration: none;" href="${viewUrl}">${row.network || ''} (${row.category || ''})</a></td>
	    				<td class="d-none d-lg-table-cell">${row.phone || ''}</td>
	    				<td class="d-none d-lg-table-cell">${Number(row.amount || 0).toFixed(2)}</td>
	    				<td class="d-none d-lg-table-cell">${Number(row.balance_after || 0).toFixed(2)}</td>
	    				<td class="d-none d-lg-table-cell"><span class="${badgeClass}">${row.transaction_status || 'N/A'}</span>${reasonHtml(row.rejection_reason)}</td>
	    				<td class="d-none d-lg-table-cell"><span><img src="${row.agent?.image || ''}" style="width: 30px; height: 30px; object-fit: cover; border-radius: 50%;"></span> ${row.agent?.name || ''}</td>
	    				<td class="d-lg-none ab-mobile-details">
	    					<div class="fw-semibold">#${row.id} · ${formatDate(row.date_created)}</div>
	    					<div class="text-muted small">${row.transaction_type || ''}</div>
	    					<div><a style="text-decoration: none;" href="${viewUrl}">${row.network || ''} (${row.category || ''})</a></div>
	    					<div class="text-muted small">${row.phone || ''}</div>
	    					<div class="mt-1"><span class="${badgeClass}">${row.transaction_status || 'N/A'}</span>${reasonHtml(row.rejection_reason)}</div>
	    				</td>
	    				<td class="text-end d-lg-none ab-mobile-amounts">
	    					<div class="ab-amount-stack">
	    						<div>
	    							<small class="text-muted d-block">Amount</small>
	    							<span class="fw-semibold">${Number(row.amount || 0).toFixed(2)}</span>
	    						</div>
	    						<div>
	    							<small class="text-muted d-block">Balance After</small>
	    							<span class="fw-semibold">${Number(row.balance_after || 0).toFixed(2)}</span>
	    						</div>
	    					</div>
	    				</td>
	    				<td>
	    					${<?= $canViewDetails ? 'true' : 'false' ?> ? `<a href="${viewUrl}"><button class="btn btn-primary btn-sm" title="View"><i class="fa-solid fa-eye"></i></button></a>` : ''}
	    					${editHtml}
	    					${deleteHtml}
	    					${cancelHtml}
	    				</td>`;
	    			refs.body.appendChild(tr);
	    		});
	    	}

	    	function renderFooter(totals){
	    		if(!refs.footer) return;
	    		refs.footer.innerHTML = `
	    			<tr>
	    				<th class="bg-secondary text-warning d-none d-lg-table-cell">#</th>
	    				<th class="bg-secondary text-warning d-none d-lg-table-cell">TOTAL :</th>
	    				<th class="bg-secondary text-warning d-none d-lg-table-cell"></th>
	    				<th class="bg-secondary text-warning d-none d-lg-table-cell"></th>
	    				<th class="bg-secondary text-warning d-none d-lg-table-cell">${Number(totals.total_amount || 0).toFixed(2)}</th>
	    				<th class="bg-secondary text-warning d-none d-lg-table-cell">${Number(totals.total_balance_after || 0).toFixed(2)}</th>
	    				<th class="bg-secondary text-warning d-none d-lg-table-cell"></th>
	    				<th class="bg-secondary text-warning d-none d-lg-table-cell"></th>
	    				<th class="bg-secondary text-warning d-none d-lg-table-cell"></th>
	    				<th class="bg-secondary text-warning d-lg-none text-end" colspan="3">TOTAL: ${Number(totals.total_amount || 0).toFixed(2)} · ${Number(totals.total_balance_after || 0).toFixed(2)}</th>
	    			</tr>`;
	    	}

	    	function renderPagination(pagination){
	    		if(!refs.pagination) return;
	    		const totalPages = pagination.total_pages || 1;
	    		const current = pagination.page || 1;
	    		const items = [];
	    		const makeItem = (page, label = page, disabled = false, active = false) => `
	    			<li class="page-item ${disabled ? 'disabled' : ''} ${active ? 'active' : ''}">
	    				<button type="button" class="page-link" data-page="${page}" ${disabled ? 'disabled' : ''}>${label}</button>
	    			</li>`;
	    		items.push(makeItem(current-1,'&laquo;', current===1));
	    		for(let i=1;i<=totalPages;i++){
	    			if(i===1 || i===totalPages || Math.abs(i-current)<=1){
	    				items.push(makeItem(i,i,false,i===current));
	    			}else if(Math.abs(i-current)===2){
	    				items.push('<li class="page-item disabled"><span class="page-link">…</span></li>');
	    			}
	    		}
	    		items.push(makeItem(current+1,'&raquo;', current===totalPages));
	    		refs.pagination.innerHTML = items.join('');
	    	}

	    	function renderRankings(rankings){
	    		if(refs.topAgents){
	    			const agents = rankings.agents || [];
	    			refs.topAgents.innerHTML = agents.length ? agents.map(agent => `
	    				<li class="py-2 d-flex justify-content-between">
	    					<div>
	    						<strong>${agent.name || '—'}</strong>
	    						<div class="text-muted small">#${agent.id} · ${agent.count || 0} txns</div>
	    					</div>
	    					<div class="text-end">
	    						<div>${formatCurrency(agent.total || 0)}</div>
	    					</div>
	    				</li>`).join('') : '<li class="text-muted small">No staff data.</li>';
	    		}
	    		if(refs.topClients){
	    			const clients = rankings.clients || [];
	    			refs.topClients.innerHTML = clients.length ? clients.map(client => `
	    				<li class="py-2 d-flex justify-content-between">
	    					<div>
	    						<strong>${client.phone || 'Unknown'}</strong>
	    						<div class="text-muted small">${client.count || 0} txns</div>
	    					</div>
	    					<div class="text-end">
	    						<div>${formatCurrency(client.total || 0)}</div>
	    					</div>
	    				</li>`).join('') : '<li class="text-muted small">No client data.</li>';
	    		}
	    	}

	    	function renderStatusBreakdown(items){
	    		if(!refs.statusBreakdown) return;
	    		if(!items.length){
	    			refs.statusBreakdown.innerHTML = '<li class="text-muted small">No status data.</li>';
	    			return;
	    		}
	    		refs.statusBreakdown.innerHTML = items.map(item => `
	    			<li class="py-2 d-flex justify-content-between align-items-center">
	    				<div>
	    					<strong>${item.label || 'Unknown'}</strong>
	    					<div class="text-muted small">${item.count || 0} txns</div>
	    				</div>
	    				<div class="text-end">
	    					<div>${formatCurrency(item.amount || 0)}</div>
	    				</div>
	    			</li>`).join('');
	    	}

	    	function renderTrend(trend){
	    		if(!refs.trend) return;
	    		if(!trend.length){
	    			refs.trend.innerHTML = '<tr><td colspan="3" class="text-muted small">No trend data.</td></tr>';
	    			return;
	    		}
	    		refs.trend.innerHTML = trend.map(item => `
	    			<tr>
	    				<td>${item.label}</td>
	    				<td>${formatCurrency(item.amount || 0)}</td>
	    				<td>${item.count || 0}</td>
	    			</tr>`).join('');
	    	}

	    	function syncChips(){
	    		if(!refs.filterChips) return;
	    		const chips = [];
	    		if(state.filters.date_from || state.filters.date_to){
	    			chips.push({ key:'date', label:`Date: ${state.filters.date_from || '…'} → ${state.filters.date_to || '…'}` });
	    		}

	    		['created_by','transaction_type','transaction_status','network','phone'].forEach(key => {
	    			(state.filters[key] || []).forEach(val => {
	    				chips.push({ key, value:val, label:`${key.replace('_',' ')}: ${val}` });
	    			});
	    		});
	    		refs.filterChips.innerHTML = '';
	    		if(!chips.length){
	    			refs.filterChips.innerHTML = '<span class="ab-filter-empty"><i class="fa fa-circle-xmark me-1"></i>No advanced filters applied.</span>';
	    			return;
	    		}
	    		chips.forEach(chip => {
	    			const el = document.createElement('span');
	    			el.className = 'badge rounded-pill text-bg-light d-flex align-items-center gap-1';
	    			el.innerHTML = `<span>${chip.label}</span><button type="button" class="btn btn-link p-0 text-muted" data-chip-remove data-key="${chip.key}" data-value="${chip.value || ''}"><i class="fa fa-times"></i></button>`;
	    			refs.filterChips.appendChild(el);
	    		});
	    	}

	    	function collectFiltersFromUI(){
	    		state.find = refs.search ? refs.search.value.trim() : '';
	    		const from = document.getElementById('abDateFrom');
	    		const to = document.getElementById('abDateTo');
	    		state.filters.date_from = from ? from.value : '';
	    		state.filters.date_to = to ? to.value : '';
	    		['created_by','transaction_type','transaction_status','network','phone'].forEach(key => {
	    			const checks = document.querySelectorAll(`[data-filter-multi="${key}"]`);
	    			state.filters[key] = Array.from(checks).filter(c => c.checked).map(c => c.value);
	    		});
	    		syncChips();
	    	}

	    	// Toggle filter blocks using dropdown toggles
	    	const filterMenuEl = document.getElementById('abFilterMenu');
	    	if(filterMenuEl){
	    		filterMenuEl.addEventListener('change', (e) => {
	    			const toggle = e.target.closest('[data-filter-toggle]');
	    			if(!toggle) return;
	    			const key = toggle.getAttribute('data-filter-toggle');
	    			const section = document.getElementById(`abSection-${key}`);
	    			if(section){ section.classList.toggle('d-none', !toggle.checked); }
	    		});

	    	}

	    	// Build filter sections once DOM is ready
	    	function ensureFilterSections(){
	    		if(!refs.filterFields) return;
	    		// Date section
	    		if(!document.getElementById('abSection-date')){
	    			const div = document.createElement('div');
	    			div.id = 'abSection-date';
	    			div.className = 'col-12 col-md-6 col-xl-4 d-none';
	    			div.innerHTML = `
	    				<div class="border rounded-3 p-2 mb-2">
	    					<div class="d-flex justify-content-between align-items-center mb-2">
	    						<strong class="small">Date range</strong>
	    					</div>
	    					<div class="row g-2">
	    						<div class="col-6">
	    							<label class="form-label small mb-1">From</label>
	    							<input type="date" class="form-control form-control-sm" id="abDateFrom" />
	    						</div>
	    						<div class="col-6">
	    							<label class="form-label small mb-1">To</label>
	    							<input type="date" class="form-control form-control-sm" id="abDateTo" />
	    						</div>
	    					</div>
	    				</div>
	    			`;
	    			refs.filterFields.appendChild(div);
	    		}
	    		// Staff section
	    		if(!document.getElementById('abSection-created_by')){
	    			const div = document.createElement('div');
	    			div.id = 'abSection-created_by';
	    			div.className = 'col-12 col-md-6 col-xl-4 d-none';
	    			div.innerHTML = `<div class="border rounded-3 p-2 mb-2"><strong class="small d-block mb-1">Staff</strong><div class="ab-filter-scroll" style="max-height:160px;overflow:auto;" data-options-container="created_by"></div></div>`;
	    			refs.filterFields.appendChild(div);
	    		}
	    		// Placeholders for other sections; options will come from payload filter_options
	    		['transaction_type','transaction_status','network','phone'].forEach(key => {
	    			if(document.getElementById(`abSection-${key}`)) return;

	    			const title = key === 'transaction_type' ? 'Types' : key === 'transaction_status' ? 'Statuses' : key === 'network' ? 'Networks' : 'Client phones';
	    			const div = document.createElement('div');
	    			div.id = `abSection-${key}`;
	    			div.className = 'col-12 col-md-6 col-xl-4 d-none';
	    			div.innerHTML = `<div class="border rounded-3 p-2 mb-2"><strong class="small d-block mb-1">${title}</strong><div class="ab-filter-scroll" style="max-height:160px;overflow:auto;" data-options-container="${key}"></div></div>`;
	    			refs.filterFields.appendChild(div);
	    		});
	    	}

	    	function populateOptionLists(filterOptions){
	    		if(!refs.filterFields || !filterOptions) return;
	    		const map = {
	    			created_by: filterOptions.agents || [],
	    			transaction_type: filterOptions.transaction_types || [],
	    			transaction_status: filterOptions.transaction_statuses || [],
	    			network: filterOptions.networks || [],
	    			phone: filterOptions.phones || []
	    		};
	    		Object.keys(map).forEach(key => {
	    			const container = refs.filterFields.querySelector(`[data-options-container="${key}"]`);

	    			if(!container) return;
	    			const values = map[key];
	    			const selected = new Set((state.filters[key] || []).map(v => String(v)));
	    			container.innerHTML = '';
	    			if(!values.length){
	    				container.innerHTML = '<p class="text-muted small mb-0">No options.</p>';
	    				return;
	    			}
	    			values.forEach(val => {
	    				const optionValue = key === 'created_by' ? String(val && val.id ? val.id : '') : String(val);
	    				if(!optionValue) return;
	    				const optionLabel = key === 'created_by'
	    					? `${optionValue ? '#' + optionValue + ' - ' : ''}${(val && val.name ? String(val.name) : 'Unknown User')}`
	    					: String(val);
	    				const id = `ab-${key}-${btoa(optionValue).replace(/=/g,'')}`;
	    				const checked = selected.has(optionValue);
	    				const div = document.createElement('div');
	    				div.className = 'form-check form-check-sm small';
	    				div.innerHTML = `<input class="form-check-input" type="checkbox" value="${optionValue.replace(/"/g,'&quot;')}" id="${id}" data-filter-multi="${key}" ${checked ? 'checked' : ''}><label class="form-check-label" for="${id}">${optionLabel}</label>`;
	    				container.appendChild(div);
	    			});
	    		});
	    	}

	    		document.addEventListener('DOMContentLoaded', () => {
	    			ensureFilterSections();
	    			applyStateToUI();
	    			// Pagination clicks
	    			if(refs.pagination){
	    				refs.pagination.addEventListener('click', (e) => {
	    					const btn = e.target.closest('button.page-link[data-page]');
	    					if(!btn || btn.disabled) return;
	    					const page = parseInt(btn.getAttribute('data-page') || '1', 10) || 1;
	    					state.page = page < 1 ? 1 : page;
	    					fetchData({ includeMeta:false });
	    				});
	    			}
	    			// Async search on input
	    			if(refs.search){
	    				refs.search.addEventListener('input', () => {
	    					state.find = refs.search.value.trim();
	    					state.page = 1;
	    					scheduleFetch(false);
	    				});
	    			}
	    			// Per-page limit
	    			if(refs.limit){
	    				refs.limit.addEventListener('change', () => {
	    					state.limit = parseInt(refs.limit.value || '25',10) || 25;
	    					state.page = 1;
	    					scheduleFetch(false);
	    				});
	    			}
	    			// Apply on form submit as fallback
	    			if(refs.filterForm){
	    				refs.filterForm.addEventListener('submit', (e) => {
	    					e.preventDefault();
	    					collectFiltersFromUI();
	    					state.page = 1;
	    					fetchData({ includeMeta:true });
	    				});
	    			}
	    			// Debounced auto-apply when any multi-checkbox or date changes
	    			document.addEventListener('change', (e) => {
	    				if(e.target.matches('[data-filter-multi]') || e.target.id === 'abDateFrom' || e.target.id === 'abDateTo'){
	    					collectFiltersFromUI();
	    					state.page = 1;
	    					scheduleFetch(true);
	    				}
	    			});

	    		// Reset button
	    		const resetBtn = document.getElementById('abReset');
	    		if(resetBtn){
	    			resetBtn.addEventListener('click', () => {
	    				state.page = 1;
	    				state.limit = 25;
	    				state.find = '';
	    				state.filters = { date_from:'', date_to:'', created_by:[], transaction_type:[], transaction_status:[], network:[], phone:[] };
	    				if(refs.search) refs.search.value = '';
	    				if(refs.limit) refs.limit.value = '25';
	    				const checks = document.querySelectorAll('[data-filter-multi]');
	    				checks.forEach(c => { c.checked = false; });
	    				const toggles = document.querySelectorAll('[data-filter-toggle]');
	    				toggles.forEach(t => { t.checked = false; });
	    				['date','created_by','transaction_type','transaction_status','network','phone'].forEach(key => {
	    					const section = document.getElementById(`abSection-${key}`);
	    					if(section) section.classList.add('d-none');
	    				});
	    				const from = document.getElementById('abDateFrom');
	    				const to = document.getElementById('abDateTo');
	    				if(from) from.value = '';
	    				if(to) to.value = '';
	    				syncChips();
	    				fetchData({ includeMeta:true });
	    			});
	    		}
	    		// Chip removal
	    		if(refs.filterChips){
	    			refs.filterChips.addEventListener('click', (e) => {
	    				const btn = e.target.closest('[data-chip-remove]');
	    				if(!btn) return;
	    				const key = btn.getAttribute('data-key');
	    				const val = btn.getAttribute('data-value');
	    				if(key === 'date'){
	    					state.filters.date_from = '';
	    					state.filters.date_to = '';
	    					const from = document.getElementById('abDateFrom');
	    					const to = document.getElementById('abDateTo');
	    					if(from) from.value = '';
	    					if(to) to.value = '';
	    				}else if(key && state.filters[key]){
	    					state.filters[key] = state.filters[key].filter(v => String(v) !== String(val));
	    					const checks = document.querySelectorAll(`[data-filter-multi="${key}"]`);
	    					checks.forEach(c => { if(String(c.value) === String(val)) c.checked = false; });
	    				}
	    				syncChips();
	    				state.page = 1;
	    				fetchData({ includeMeta:true });
	    			});
	    		}
	    		fetchData({ includeMeta:false });
	    		if(window.requestIdleCallback){
	    			window.requestIdleCallback(() => fetchData({ includeMeta:true, background:true }));
	    		}else{
	    			setTimeout(() => fetchData({ includeMeta:true, background:true }), 250);
	    		}
	    	});
	    })();
	    </script>
	    <div class="d-none" data-direct-cancel-csrf><?=csrf()?></div>
	    <script src="<?=ROOT?>/plugins/reversals/assets/direct-cancel.js?v=20261116-1"></script>

</div>
<?php else:?>
	<div class="alert alert-danger text-center">
		Access denied. You dont have permission for this action
<?php endif?>
