<?php if(user_can('view_reversal')):?>
	<style>
		.rev-list-wrap {
			max-width: 1180px;
			margin: 24px auto;
		}
		.rev-list-card {
			border-radius: 18px;
			border: 0.3px solid #efe3b0;
			box-shadow: 0 16px 38px rgba(25, 35, 58, 0.08);
			overflow: hidden;
		}
		.rev-list-head {
			padding: 20px 24px;
			background: linear-gradient(135deg, #fff6d210 0%, #fffdf710 100%);
			border-bottom: 0.3px solid #f2e8bf;
		}
		.rev-toolbar .btn{
			border-radius: 999px;
		}
		.rev-metrics {
			display: grid;
			grid-template-columns: repeat(4, minmax(0, 1fr));
			gap: 14px;
			margin-bottom: 18px;
		}
		.rev-metric {
			border-radius: 14px;
			padding: 14px 16px;
			background: #fafbfd20;
			border: 1px solid; #edf1f7;
		}
		.rev-metric-head,
		.rev-analytics-panel .metric-head{
			display:flex;
			align-items:flex-start;
			justify-content:space-between;
			gap:12px;
		}
		.rev-metric-copy,
		.rev-analytics-panel .metric-copy{
			min-width:0;
			flex:1;
		}
		.rev-metric .label {
			font-size: 12px;
			color: #6c757d;
		}
		.rev-metric .value {
			font-size: 20px;
			font-weight: 700;
		}
		.rev-metric-icon,
		.rev-analytics-panel .metric-icon{
			width: 42px;
			height: 42px;
			border-radius: 12px;
			display:inline-flex;
			align-items:center;
			justify-content:center;
			font-size: 16px;
			flex:0 0 auto;
			border: 1px solid transparent;
		}
		.rev-icon-total{
			color:#0d6efd;
			background:rgba(13,110,253,0.12);
			border-color:rgba(13,110,253,0.18);
		}
		.rev-icon-review{
			color:#d39e00;
			background:rgba(255,193,7,0.14);
			border-color:rgba(255,193,7,0.22);
		}
		.rev-icon-tracking{
			color:#0dcaf0;
			background:rgba(13,202,240,0.14);
			border-color:rgba(13,202,240,0.22);
		}
		.rev-icon-closed{
			color:#198754;
			background:rgba(25,135,84,0.14);
			border-color:rgba(25,135,84,0.22);
		}
		.rev-icon-amount{
			color:#198754;
			background:rgba(25,135,84,0.14);
			border-color:rgba(25,135,84,0.22);
		}
		.rev-icon-average{
			color:#6610f2;
			background:rgba(102,16,242,0.12);
			border-color:rgba(102,16,242,0.18);
		}
		.rev-icon-rate{
			color:#fd7e14;
			background:rgba(253,126,20,0.12);
			border-color:rgba(253,126,20,0.18);
		}
		.rev-icon-provider{
			color:#20c997;
			background:rgba(32,201,151,0.12);
			border-color:rgba(32,201,151,0.18);
		}
		.rev-person {
			display: flex;
			align-items: center;
			gap: .45rem;
			min-width: 150px;
		}
		.rev-person img {
			width: 28px;
			height: 28px;
			object-fit: cover;
			border-radius: 50%;
			background: #f1f3f5;
			flex: 0 0 28px;
		}
		.rev-person-label {
			font-size: .72rem;
			color: #6c757d;
			line-height: 1.1;
		}
		.rev-person-name {
			font-size: .84rem;
			font-weight: 600;
			line-height: 1.15;
		}
		.rev-mobile-meta {
			display: none;
		}
		@media (max-width: 991.98px) {
			.rev-provider-col,
			.rev-status-col,
			.rev-followup-col,
			.rev-date-col {
				display: none;
			}
			.rev-mobile-meta {
				display: block;
				margin-top: .25rem;
				font-size: .78rem;
				line-height: 1.35;
				color: var(--bs-secondary-color);
				white-space: normal;
			}
			.rev-list-card table {
				table-layout: fixed;
				width: 100%;
			}
			.rev-list-card th,
			.rev-list-card td {
				white-space: normal;
				vertical-align: top;
				overflow-wrap: anywhere;
			}
			.rev-list-card th:nth-child(1),
			.rev-list-card td:nth-child(1) {
				width: 6%;
			}
			.rev-list-card th:nth-child(2),
			.rev-list-card td:nth-child(2) {
				width: 22%;
			}
			.rev-list-card th:nth-child(4),
			.rev-list-card td:nth-child(4) {
				width: 22%;
			}
			.rev-list-card th:nth-child(5),
			.rev-list-card td:nth-child(5) {
				width: 25%;
			}
			.rev-list-card th:nth-child(6),
			.rev-list-card td:nth-child(6) {
				width: 13%;
			}
			.rev-list-card th:nth-child(10),
			.rev-list-card td:nth-child(10) {
				width: 12%;
			}
			.rev-person {
				min-width: 0;
			}
		}
		@media (max-width: 900px) {
			.rev-metrics {
				grid-template-columns: repeat(2, minmax(0, 1fr));
			}
		}
		.rev-analytics-panel .metric-card{
			border-radius: 16px;
			border: 0.3px solid #eaeef5;
			padding: 16px 18px;
			box-shadow: 0 10px 26px rgba(18, 38, 63, 0.04);
		}
		.rev-analytics-panel .metric-card .label{
			color: #6c757d;
			font-size: 12px;
			text-transform: uppercase;
			letter-spacing: .04em;
		}
		.rev-analytics-panel .metric-card .value{
			font-size: 24px;
			font-weight: 700;
			margin-top: 6px;
		}
	</style>

	<div class="rev-list-wrap">
		<div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
			<div>
				<h3 class="mb-1">Reversals</h3>
				<div class="text-muted">Monitor acknowledgements, tracking, refunds and claims from one place.</div>
			</div>
			<div class="d-flex gap-2 flex-wrap rev-toolbar">
				<button class="btn btn-primary" type="button" id="rev_tab_list">
					<i class="fa-solid fa-list"></i> List
				</button>
				<button class="btn btn-outline-primary" type="button" id="rev_tab_analytics">
					<i class="fa-solid fa-chart-line"></i> Analytics
				</button>
				<button class="btn btn-outline-danger" type="button" id="rev_export_pdf">
					<i class="fa-solid fa-file-pdf"></i> Export PDF
				</button>
				<?php if(user_can('add_reversal')):?>
					<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/add" class="btn btn-primary">
						<i class="fa-solid fa-plus"></i> New Reversal
					</a>
				<?php endif;?>
			</div>
		</div>

		<div class="rev-metrics">
			<div class="rev-metric">
				<div class="rev-metric-head">
					<div class="rev-metric-copy">
						<div class="label">Total reversals</div>
						<div class="value"><?=number_format((int)($metrics['total_count'] ?? 0))?></div>
					</div>
					<span class="rev-metric-icon rev-icon-total"><i class="fa-solid fa-arrow-rotate-left"></i></span>
				</div>
			</div>
			<div class="rev-metric">
				<div class="rev-metric-head">
					<div class="rev-metric-copy">
						<div class="label">Pending / review</div>
						<div class="value"><?=number_format((int)($metricSummary['review'] ?? 0))?></div>
					</div>
					<span class="rev-metric-icon rev-icon-review"><i class="fa-solid fa-hourglass-half"></i></span>
				</div>
			</div>
			<div class="rev-metric">
				<div class="rev-metric-head">
					<div class="rev-metric-copy">
						<div class="label">Tracking active</div>
						<div class="value"><?=number_format((int)($metricSummary['tracking'] ?? 0))?></div>
					</div>
					<span class="rev-metric-icon rev-icon-tracking"><i class="fa-solid fa-location-crosshairs"></i></span>
				</div>
			</div>
			<div class="rev-metric">
				<div class="rev-metric-head">
					<div class="rev-metric-copy">
						<div class="label">Closed</div>
						<div class="value"><?=number_format((int)($metricSummary['closed'] ?? 0))?></div>
					</div>
					<span class="rev-metric-icon rev-icon-closed"><i class="fa-solid fa-circle-check"></i></span>
				</div>
			</div>
		</div>

		<div class="rev-list-card" id="rev_list_card">
			<div class="rev-list-head">
				<form class="row g-2 align-items-end" id="rev_filters_form">
					<div class="col-md-4">
						<label class="form-label">Search</label>
						<input type="text" class="form-control" id="rev_find" name="find" value="<?=old_value('find','','get')?>" placeholder="Search by transaction ID, provider, status or sender">
					</div>
					<div class="col-6 col-md-2">
						<label class="form-label">Status</label>
						<select class="form-select" id="rev_status" name="status">
							<option value="">All statuses</option>
							<?php foreach(($statusOptions ?? []) as $statusValue => $statusLabel): ?>
								<option value="<?=esc($statusValue)?>" <?=($statusFilter === strtolower((string)$statusValue)) ? 'selected' : ''?>>
									<?=esc($statusLabel)?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-6 col-md-2">
						<label class="form-label">Provider</label>
						<select class="form-select" id="rev_network" name="network">
							<option value="">All providers</option>
							<?php foreach(($providerOptions ?? []) as $providerValue => $providerLabel): ?>
								<option value="<?=esc($providerValue)?>" <?=((string)$networkFilter === (string)$providerValue) ? 'selected' : ''?>>
									<?=esc($providerLabel)?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-6 col-md-2">
						<label class="form-label">From</label>
						<input type="date" class="form-control" id="rev_date_from" name="date_from" value="<?=old_value('date_from','','get')?>">
					</div>
					<div class="col-6 col-md-2">
						<label class="form-label">To</label>
						<input type="date" class="form-control" id="rev_date_to" name="date_to" value="<?=old_value('date_to','','get')?>">
					</div>
					<div class="col-12 d-flex justify-content-end gap-2">
						<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>" class="btn btn-outline-secondary" id="rev_reset_filters">Reset</a>
						<button class="btn btn-primary" type="submit">Filter</button>
					</div>
				</form>
			</div>

			<div class="table-responsive">
				<table class="table table-hover align-middle mb-0">
					<thead>
						<tr>
							<th>#</th>
							<th>Source</th>
							<th class="rev-provider-col">Provider</th>
							<th>Sender / Receiver</th>
							<th>Initiator / Reviewer</th>
							<th>Amount</th>
							<th class="rev-status-col">Status</th>
							<th class="rev-followup-col">Follow Up</th>
							<th class="rev-date-col">Date</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php if(!empty($rows)): ?>
							<?php foreach($rows as $index => $row): ?>
								<?php
									$initiatorName = trim((string)(($row->user_first_name ?? '') . ' ' . ($row->user_last_name ?? '')));
									$initiatorName = $initiatorName !== '' ? $initiatorName : ((int)($row->created_by ?? 0) > 0 ? ('User #' . (int)$row->created_by) : 'Unknown user');
									$reviewerName = trim((string)(($row->assigned_first_name ?? '') . ' ' . ($row->assigned_last_name ?? '')));
									$reviewerName = $reviewerName !== '' ? $reviewerName : ((int)($row->assigned ?? 0) > 0 ? ('User #' . (int)$row->assigned) : 'Not assigned');
								?>
								<tr>
									<td><?=($index + 1)?></td>
									<td>
										<div class="fw-semibold"><?=esc(reversals_transaction_type_options()[$row->source_transaction_type ?? ''] ?? ($row->source_transaction_type ?? ''))?></div>
										<div class="small text-muted">ID: <?=esc($row->source_transaction_id ?? $row->id)?></div>
										<div class="rev-mobile-meta">
											<div><strong>Provider:</strong> <?=esc($row->network ?? '')?></div>
											<?php if(!empty($row->service_type)): ?>
												<div><?=esc($row->service_type ?? '')?></div>
											<?php endif; ?>
											<div><strong>Date:</strong> <?=get_date_time($row->date_created ?? '')?></div>
										</div>
									</td>
									<td class="rev-provider-col">
										<div class="fw-semibold"><?=esc($row->network ?? '')?></div>
										<div class="small text-muted"><?=esc($row->service_type ?? '')?></div>
									</td>
									<td>
										<div class="small"><strong>Sender:</strong> <?=esc($row->sender_details ?? '')?></div>
										<div class="small"><strong>Receiver:</strong> <?=esc($row->receiver_details ?? '')?></div>
									</td>
									<td>
										<div class="rev-person mb-1">
											<img src="<?=get_image($row->user_image ?? '')?>" alt="">
											<div>
												<div class="rev-person-label">Initiated by</div>
												<div class="rev-person-name"><?=esc($initiatorName)?></div>
											</div>
										</div>
										<div class="rev-person">
											<img src="<?=get_image($row->assigned_image ?? '')?>" alt="">
											<div>
												<div class="rev-person-label">Assigned reviewer</div>
												<div class="rev-person-name"><?=esc($reviewerName)?></div>
											</div>
										</div>
										<div class="rev-mobile-meta">
											<div><strong>Status:</strong> <span class="<?=$row->status_badge_class?>"><?=esc($row->transaction_status ?? '')?></span></div>
											<div><strong>Follow Up:</strong>
												<?php if(!empty($row->followup_required)): ?>
													<span class="badge bg-danger">Required</span>
												<?php else: ?>
													<span class="badge bg-light text-dark">No</span>
												<?php endif; ?>
											</div>
										</div>
									</td>
									<td><?=number_format((float)($row->amount ?? 0), 2)?></td>
									<td class="rev-status-col"><span class="<?=$row->status_badge_class?>"><?=esc($row->transaction_status ?? '')?></span></td>
									<td class="rev-followup-col">
										<?php if(!empty($row->followup_required)): ?>
											<span class="badge bg-danger">Required</span>
										<?php else: ?>
											<span class="badge bg-light text-dark">No</span>
										<?php endif; ?>
									</td>
									<td class="rev-date-col"><?=get_date_time($row->date_created ?? '')?></td>
									<td class="text-end">
										<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$row->id?>" class="btn btn-sm btn-outline-primary">
											<i class="fa-solid fa-eye"></i>
										</a>
										<?php if(reversals_user_can_view_row($row, get_session()) && !$row->is_closed): ?>
											<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit/<?=$row->id?>" class="btn btn-sm btn-outline-warning">
												<i class="fa-solid fa-pen-to-square"></i>
											</a>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php else: ?>
							<tr>
								<td colspan="10" class="text-center py-4">No reversals found.</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>

		<div class="rev-analytics-panel d-none mt-3" id="rev_analytics_panel">
			<div class="row g-3">
				<div class="col-12 col-md-3">
					<div class="metric-card h-100">
						<div class="metric-head">
							<div class="metric-copy">
								<div class="label">Total Amount</div>
								<div class="value" id="rev_an_total_amount">0.00</div>
								<div class="text-muted small"><span id="rev_an_total_count">0</span> reversal(s)</div>
							</div>
							<span class="metric-icon rev-icon-amount"><i class="fa-solid fa-sack-dollar"></i></span>
						</div>
					</div>
				</div>
				<div class="col-12 col-md-3">
					<div class="metric-card h-100">
						<div class="metric-head">
							<div class="metric-copy">
								<div class="label">Average / Median</div>
								<div class="fw-semibold mt-2">Avg: <span id="rev_an_avg_amount">0.00</span></div>
								<div class="text-muted">Median: <span id="rev_an_median_amount">0.00</span></div>
							</div>
							<span class="metric-icon rev-icon-average"><i class="fa-solid fa-scale-balanced"></i></span>
						</div>
					</div>
				</div>
				<div class="col-12 col-md-3">
					<div class="metric-card h-100">
						<div class="metric-head">
							<div class="metric-copy">
								<div class="label">Closed / Review</div>
								<div class="fw-semibold mt-2">Closed: <span id="rev_an_closed_rate">0%</span></div>
								<div class="text-muted">In review: <span id="rev_an_review_rate">0%</span></div>
							</div>
							<span class="metric-icon rev-icon-rate"><i class="fa-solid fa-clipboard-check"></i></span>
						</div>
					</div>
				</div>
				<div class="col-12 col-md-3">
					<div class="metric-card h-100">
						<div class="metric-head">
							<div class="metric-copy">
								<div class="label">Top Provider</div>
								<div class="fw-semibold mt-2" id="rev_an_top_provider">-</div>
								<div class="text-muted">Amount: <span id="rev_an_top_amount">0.00</span></div>
							</div>
							<span class="metric-icon rev-icon-provider"><i class="fa-solid fa-tower-broadcast"></i></span>
						</div>
					</div>
				</div>
			</div>

			<div class="row g-3 mt-1">
				<div class="col-12 col-lg-6">
					<div class="metric-card">
						<div class="fw-bold">Amount by Provider</div>
						<canvas id="rev_provider_chart" height="140"></canvas>
					</div>
				</div>
				<div class="col-12 col-lg-6">
					<div class="metric-card">
						<div class="fw-bold">Amount Trend (Monthly)</div>
						<canvas id="rev_trend_chart" height="140"></canvas>
					</div>
				</div>
			</div>

			<div class="row g-3 mt-1">
				<div class="col-12 col-lg-6">
					<div class="metric-card">
						<div class="fw-bold">Status Mix</div>
						<div style="height:220px;" class="mt-2">
							<canvas id="rev_status_chart"></canvas>
						</div>
					</div>
				</div>
				<div class="col-12 col-lg-6">
					<div class="metric-card">
						<div class="fw-bold">Insights</div>
						<div class="text-muted mt-2" id="rev_an_insight">Loading...</div>
					</div>
				</div>
			</div>
		</div>
	</div>
<?php else:?>
	<div class="alert alert-danger text-center">
		Access denied. You dont have permission for this action
	</div>
<?php endif?>

<script>
(function(){
	var root = "<?=ROOT?>";
	var adminRoute = "<?=esc($admin_route)?>";
	var pluginRoute = "<?=esc($plugin_route)?>";
	var activeTab = 'list';
	var chartJsLoaded = false;
	var charts = { provider: null, trend: null, status: null };
	var analyticsTimer = null;

	var elForm = document.getElementById('rev_filters_form');
	var elListCard = document.getElementById('rev_list_card');
	var elAnalyticsPanel = document.getElementById('rev_analytics_panel');
	var elTabList = document.getElementById('rev_tab_list');
	var elTabAnalytics = document.getElementById('rev_tab_analytics');
	var elExportPdf = document.getElementById('rev_export_pdf');
	var elFind = document.getElementById('rev_find');
	var elStatus = document.getElementById('rev_status');
	var elNetwork = document.getElementById('rev_network');
	var elFrom = document.getElementById('rev_date_from');
	var elTo = document.getElementById('rev_date_to');
	var elInsight = document.getElementById('rev_an_insight');
	var elTotalAmount = document.getElementById('rev_an_total_amount');
	var elTotalCount = document.getElementById('rev_an_total_count');
	var elAvgAmount = document.getElementById('rev_an_avg_amount');
	var elMedianAmount = document.getElementById('rev_an_median_amount');
	var elClosedRate = document.getElementById('rev_an_closed_rate');
	var elReviewRate = document.getElementById('rev_an_review_rate');
	var elTopProvider = document.getElementById('rev_an_top_provider');
	var elTopAmount = document.getElementById('rev_an_top_amount');
	var elProviderChart = document.getElementById('rev_provider_chart');
	var elTrendChart = document.getElementById('rev_trend_chart');
	var elStatusChart = document.getElementById('rev_status_chart');

	function money(value){
		var num = parseFloat(value || 0);
		if(isNaN(num)) num = 0;
		return num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	}
	function pct(value){
		var num = parseFloat(value || 0);
		if(isNaN(num)) num = 0;
		return (num * 100).toFixed(1) + '%';
	}
	function queryString(){
		var p = [];
		if(elFind && elFind.value.trim()) p.push('find=' + encodeURIComponent(elFind.value.trim()));
		if(elStatus && elStatus.value.trim()) p.push('status=' + encodeURIComponent(elStatus.value.trim()));
		if(elNetwork && elNetwork.value.trim()) p.push('network=' + encodeURIComponent(elNetwork.value.trim()));
		if(elFrom && elFrom.value.trim()) p.push('date_from=' + encodeURIComponent(elFrom.value.trim()));
		if(elTo && elTo.value.trim()) p.push('date_to=' + encodeURIComponent(elTo.value.trim()));
		return p.join('&');
	}
	function endpoint(action){
		var qs = queryString();
		return root + '/' + adminRoute + '/' + pluginRoute + '/' + action + (qs ? ('?' + qs) : '');
	}
	function ensureChartJs(cb){
		if(chartJsLoaded && window.Chart) return cb();
		var existing = document.getElementById('rev_chartjs_loader');
		if(existing){
			existing.addEventListener('load', function(){ chartJsLoaded = true; cb(); });
			return;
		}
		var s = document.createElement('script');
		s.id = 'rev_chartjs_loader';
		s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
		s.onload = function(){ chartJsLoaded = true; cb(); };
		s.onerror = function(){ if(elInsight) elInsight.textContent = 'Failed to load charts library'; };
		document.head.appendChild(s);
	}
	function destroyCharts(){
		try{ if(charts.provider) charts.provider.destroy(); }catch(e){}
		try{ if(charts.trend) charts.trend.destroy(); }catch(e){}
		try{ if(charts.status) charts.status.destroy(); }catch(e){}
		charts.provider = charts.trend = charts.status = null;
	}
	function showTab(name){
		activeTab = name;
		if(elTabList && elTabAnalytics){
			if(name === 'analytics'){
				elTabList.classList.remove('btn-primary');
				elTabList.classList.add('btn-outline-primary');
				elTabAnalytics.classList.remove('btn-outline-primary');
				elTabAnalytics.classList.add('btn-primary');
			}else{
				elTabList.classList.remove('btn-outline-primary');
				elTabList.classList.add('btn-primary');
				elTabAnalytics.classList.remove('btn-primary');
				elTabAnalytics.classList.add('btn-outline-primary');
			}
		}
		if(name === 'analytics'){
			if(elListCard) elListCard.classList.add('d-none');
			if(elAnalyticsPanel) elAnalyticsPanel.classList.remove('d-none');
			fetchAnalytics();
		}else{
			if(elAnalyticsPanel) elAnalyticsPanel.classList.add('d-none');
			if(elListCard) elListCard.classList.remove('d-none');
		}
	}
	function renderCharts(byProvider, trend, statusMap){
		ensureChartJs(function(){
			destroyCharts();
			var providerLabels = [];
			var providerValues = [];
			(byProvider || []).forEach(function(row){
				providerLabels.push(String(row.provider || 'UNSPECIFIED'));
				providerValues.push(parseFloat(row.total || 0) || 0);
			});
			var trendLabels = [];
			var trendValues = [];
			(trend || []).forEach(function(row){
				trendLabels.push(String(row.ym || ''));
				trendValues.push(parseFloat(row.total || 0) || 0);
			});
			var statusLabels = [];
			var statusValues = [];
			Object.keys(statusMap || {}).forEach(function(key){
				statusLabels.push(String(key || 'unknown'));
				statusValues.push(parseInt((statusMap[key] || {}).count || 0, 10) || 0);
			});

			if(elProviderChart && window.Chart){
				charts.provider = new Chart(elProviderChart, {
					type: 'bar',
					data: { labels: providerLabels, datasets: [{ label: 'Total Amount', data: providerValues, backgroundColor: 'rgba(13,110,253,0.35)', borderColor: 'rgba(13,110,253,0.9)', borderWidth: 1 }] },
					options: { responsive: true, plugins: { legend: { display: false } } }
				});
			}
			if(elTrendChart && window.Chart){
				charts.trend = new Chart(elTrendChart, {
					type: 'line',
					data: { labels: trendLabels, datasets: [{ label: 'Monthly Amount', data: trendValues, fill: true, backgroundColor: 'rgba(25,135,84,0.12)', borderColor: 'rgba(25,135,84,0.9)', tension: 0.25, pointRadius: 3 }] },
					options: { responsive: true, plugins: { legend: { display: false } } }
				});
			}
			if(elStatusChart && window.Chart){
				charts.status = new Chart(elStatusChart, {
					type: 'doughnut',
					data: { labels: statusLabels, datasets: [{ data: statusValues, backgroundColor: ['#facc15','#0dcaf0','#198754','#dc3545','#6f42c1','#fd7e14','#6c757d','#20c997'] }] },
					options: { responsive: true, maintainAspectRatio: false, cutout: '68%', plugins: { legend: { position: 'bottom' } } }
				});
			}
		});
	}
	function fetchAnalytics(){
		if(!elAnalyticsPanel) return;
		if(elInsight) elInsight.textContent = 'Loading...';
		var xhr = new XMLHttpRequest();
		xhr.open('GET', endpoint('ajax_analytics'), true);
		xhr.onreadystatechange = function(){
			if(xhr.readyState !== 4) return;
			var res = null;
			try{ res = JSON.parse(xhr.responseText); }catch(e){ res = null; }
			if(!res || !res.success){
				if(elInsight) elInsight.textContent = 'Failed to load analytics';
				return;
			}
			var summary = res.summary || {};
			var status = res.status || {};
			var byProvider = res.by_provider || [];
			var trend = res.trend || [];
			var totalCount = parseFloat(summary.total_count || 0) || 0;
			var statusSummary = res && res.status_summary ? res.status_summary : {};
			var closedCount = parseFloat(statusSummary.closed || 0) || 0;
			var reviewCount = parseFloat(statusSummary.review || 0) || 0;
			var trackingCount = parseFloat(statusSummary.tracking || 0) || 0;
			if(!statusSummary || !Object.keys(statusSummary).length || (!closedCount && !reviewCount && !trackingCount && totalCount > 0)){
				Object.keys(status || {}).forEach(function(key){
					var bucketCount = parseFloat((status[key] || {}).count || 0) || 0;
					var lowered = String(key || '').toLowerCase().trim();
					if(['closed','manager rejected','non-reversible'].indexOf(lowered) !== -1){
						closedCount += bucketCount;
					}else if(['proof uploaded - start tracking','tracking active','follow up required'].indexOf(lowered) !== -1){
						trackingCount += bucketCount;
					}else{
						reviewCount += bucketCount;
					}
				});
			}

			if(elTotalAmount) elTotalAmount.textContent = money(summary.total_amount || 0);
			if(elTotalCount) elTotalCount.textContent = String(summary.total_count || 0);
			if(elAvgAmount) elAvgAmount.textContent = money(summary.avg_amount || 0);
			if(elMedianAmount) elMedianAmount.textContent = money(summary.median_amount || 0);
			if(elClosedRate) elClosedRate.textContent = pct(totalCount > 0 ? (closedCount / totalCount) : 0);
			if(elReviewRate) elReviewRate.textContent = pct(totalCount > 0 ? (reviewCount / totalCount) : 0);

			var top = byProvider.length ? byProvider[0] : null;
			if(elTopProvider) elTopProvider.textContent = top ? String(top.provider || '-') : '-';
			if(elTopAmount) elTopAmount.textContent = money(top ? (top.total || 0) : 0);

			var insight = 'No reversals found for the selected filters.';
			if(totalCount > 0){
				var closedRate = totalCount > 0 ? (closedCount / totalCount) : 0;
				insight = 'Filtered reversals total ' + money(summary.total_amount || 0) + ' across ' + String(summary.total_count || 0) + ' record(s). ';
				if(top){
					insight += 'Top provider is ' + String(top.provider || 'UNSPECIFIED') + ' with ' + money(top.total || 0) + '. ';
				}
				insight += 'Closure rate is ' + pct(closedRate) + '.';
			}
			if(elInsight) elInsight.textContent = insight;
			renderCharts(byProvider, trend, status);
		};
		xhr.send();
	}
	function scheduleAnalytics(){
		if(analyticsTimer) clearTimeout(analyticsTimer);
		analyticsTimer = setTimeout(function(){ if(activeTab === 'analytics') fetchAnalytics(); }, 250);
	}

	if(elTabList) elTabList.addEventListener('click', function(){ showTab('list'); });
	if(elTabAnalytics) elTabAnalytics.addEventListener('click', function(){ showTab('analytics'); });
	if(elExportPdf) elExportPdf.addEventListener('click', function(){
		window.open(endpoint('export_pdf'), 'reversals_pdf', 'width=1000,height=700');
	});
	[elFind, elStatus, elNetwork, elFrom, elTo].forEach(function(el){
		if(!el) return;
		var eventName = (el.type === 'date') ? 'change' : 'input';
		el.addEventListener(eventName, scheduleAnalytics);
	});
})();
</script>
