<?php if (user_can('view_pettycash') || pettycash_user_is_privileged(get_session())): ?>
<style type="text/css">
	#pettyTable tbody tr{
		white-space: nowrap;
	}
	#pettyTable thead tr{
		white-space: nowrap;
	}
	#pettyTable tfoot tr{
		white-space: nowrap;
	}
	#pettyTable td.wrap{
		white-space: normal;
	}
	@media (max-width: 991.98px){
		#pettyTable tbody tr{ margin-bottom: .75rem; }
	}
</style>

<div class="row my-3 mx-auto">
	<div class="col-12 mb-2">
		<div class="d-flex flex-wrap gap-2 mb-2" id="petty_metrics" style="min-height: 30px;">
			<span class="badge bg-warning text-dark">Pending Ack: <span id="m_pending_ack">0</span></span>
			<span class="badge bg-info text-dark">Acknowledged: <span id="m_acknowledged">0</span></span>
			<span class="badge bg-success">Completed: <span id="m_completed">0</span></span>
			<span class="badge bg-danger">Rejected: <span id="m_rejected">0</span></span>
		</div>
		<div class="row g-2">
			<div class="col-12 col-md-4">
				<input id="petty_q" class="form-control" placeholder="Search (reference, title, description, user)" type="text" />
			</div>
			<div class="col-6 col-md-2">
				<select id="petty_status" class="form-select">
					<option value="">All Status</option>
					<option value="pending_ack">Pending Acknowledgement</option>
					<option value="acknowledged">Acknowledged</option>
					<option value="completed">Completed</option>
					<option value="rejected">Rejected</option>
				</select>
			</div>
			<div class="col-6 col-md-2">
				<select id="petty_category" class="form-select">
					<option value="">All Categories</option>
					<?php foreach (pettycash_categories() as $categoryValue => $categoryLabel): ?>
						<option value="<?=esc($categoryValue)?>"><?=esc($categoryLabel)?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="col-6 col-md-2">
				<input id="petty_from" class="form-control" type="date" />
			</div>
			<div class="col-6 col-md-2">
				<input id="petty_to" class="form-control" type="date" />
			</div>
			<div class="col-12 col-md-2 input-group">
				<select id="petty_limit" class="form-select" style="max-width: 80px;">
					<option value="10">10</option>
					<option value="30" selected>30</option>
					<option value="50">50</option>
					<option value="100">100</option>
				</select>
				<label class="input-group-text">Per Page</label>
			</div>
		</div>
	</div>
	<div class="col-12 d-flex justify-content-lg-end align-items-start gap-2 flex-wrap">
		<button class="btn btn-outline-secondary" type="button" id="petty_clear">Clear</button>
		<button class="btn btn-primary" type="button" id="petty_tab_list">
			<i class="fa-solid fa-list"></i> List
		</button>
		<button class="btn btn-outline-primary" type="button" id="petty_tab_analytics">
			<i class="fa-solid fa-chart-line"></i> Analytics
		</button>
		<button class="btn btn-outline-danger" type="button" id="petty_export_pdf">
			<i class="fa-solid fa-file-pdf"></i> Export PDF
		</button>
		<?php if (pettycash_user_is_privileged(get_session()) && user_can('add_pettycash')): ?>
		<a style="text-decoration: none;" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/add">
			<button class="btn btn-warning" type="button">
				<i class="fa-solid fa-plus"></i> New Petty Cash
			</button>
		</a>
		<?php endif; ?>
	</div>
</div>

<div class="table-responsive">
	<table class="table table-striped table-hover" id="pettyTable">
		<thead>
			<tr>
				<th class="d-lg-none">Details</th>
				<th class="d-lg-none">Status</th>
				<th class="d-lg-none">Action</th>

				<th class="d-none d-lg-table-cell">#</th>
				<th class="d-none d-lg-table-cell">Reference / Date Created</th>
				<th class="d-none d-lg-table-cell">Category</th>
				<th class="d-none d-lg-table-cell">Description</th>
				<th class="d-none d-lg-table-cell">Budget / Status</th>
				<th class="d-none d-lg-table-cell">Manager / Assigned</th>
				<th class="d-none d-lg-table-cell">Action</th>
			</tr>
		</thead>
		<tbody id="petty_tbody">
			<tr><td colspan="10" class="text-center">Loading...</td></tr>
		</tbody>
		<tfoot class="d-none d-lg-table-footer-group">
			<tr>
				<th colspan="4" class="text-center">TOTAL BUDGET (Filtered)</th>
				<th id="petty_total_amount">0.00</th>
				<th></th>
				<th></th>
			</tr>
		</tfoot>
	</table>

	<div class="d-flex justify-content-between align-items-center">
		<div class="text-muted" id="petty_meta"></div>
		<div class="d-flex gap-2">
			<button class="btn btn-sm btn-outline-primary" type="button" id="petty_prev">Prev</button>
			<button class="btn btn-sm btn-outline-primary" type="button" id="petty_next">Next</button>
		</div>
	</div>
</div>

<div class="mt-3 d-none" id="petty_analytics_panel">
	<div class="row g-2">
		<div class="col-12 col-md-3">
			<div class="p-3 border rounded">
				<div class="text-muted">Total Budget</div>
				<div class="h4 mb-0" id="an_total_amount">0.00</div>
				<div class="text-muted small"><span id="an_total_count">0</span> transaction(s)</div>
			</div>
		</div>
		<div class="col-12 col-md-3">
			<div class="p-3 border rounded">
				<div class="text-muted">Average / Median</div>
				<div class="h6 mb-0">Avg: <span id="an_avg_amount">0.00</span></div>
				<div class="text-muted">Median: <span id="an_median_amount">0.00</span></div>
			</div>
		</div>
		<div class="col-12 col-md-3">
			<div class="p-3 border rounded">
				<div class="text-muted">Completion Rate</div>
				<div class="h4 mb-0" id="an_completion_rate">0%</div>
				<div class="text-muted small">Rejected: <span id="an_rejected_rate">0%</span></div>
			</div>
		</div>
		<div class="col-12 col-md-3">
			<div class="p-3 border rounded">
				<div class="text-muted">Top Category</div>
				<div class="h6 mb-0" id="an_top_category">-</div>
				<div class="text-muted">Budget: <span id="an_top_amount">0.00</span></div>
			</div>
		</div>
	</div>

	<div class="row g-3 mt-1">
		<div class="col-12 col-lg-6">
			<div class="p-3 border rounded">
				<div class="fw-bold">Budget by Category</div>
				<canvas id="an_cat_chart" height="140"></canvas>
			</div>
		</div>
		<div class="col-12 col-lg-6">
			<div class="p-3 border rounded">
				<div class="fw-bold">Budget Trend (Monthly)</div>
				<canvas id="an_trend_chart" height="140"></canvas>
			</div>
		</div>
	</div>

	<div class="row g-3 mt-1">
		<div class="col-12 col-lg-6">
			<div class="p-3 border rounded">
				<div class="fw-bold">Status Mix (Count)</div>
				<div style="height:220px;" class="mt-2">
					<canvas id="an_status_chart"></canvas>
				</div>
			</div>
		</div>
		<div class="col-12 col-lg-6">
			<div class="p-3 border rounded">
				<div class="fw-bold">Insights</div>
				<div class="text-muted mt-2" id="an_insight">Loading...</div>
			</div>
		</div>
	</div>
</div>

<script>
(function(){
	var root = "<?=ROOT?>";
	var adminRoute = "<?=esc($admin_route)?>";
	var pluginRoute = "<?=esc($plugin_route)?>";
	var pettycashPath = window.location.pathname.replace(/\/+$/, '');
	var canManage = <?=pettycash_user_is_privileged(get_session()) ? 'true' : 'false'?>;
	var currentUserId = <?=json_encode((int)(get_session()->id ?? 0))?>;
	var chartJsLoaded = false;
	var charts = { cat: null, trend: null, status: null };
	var activeTab = 'list';

	var elQ = document.getElementById('petty_q');
	var elStatus = document.getElementById('petty_status');
	var elCategory = document.getElementById('petty_category');
	var elFrom = document.getElementById('petty_from');
	var elTo = document.getElementById('petty_to');
	var elLimit = document.getElementById('petty_limit');
	var elClear = document.getElementById('petty_clear');
	var elBody = document.getElementById('petty_tbody');
	var elMeta = document.getElementById('petty_meta');
	var elTotalAmount = document.getElementById('petty_total_amount');
	var elPrev = document.getElementById('petty_prev');
	var elNext = document.getElementById('petty_next');
	var elMPendingAck = document.getElementById('m_pending_ack');
	var elMAcknowledged = document.getElementById('m_acknowledged');
	var elMCompleted = document.getElementById('m_completed');
	var elMRejected = document.getElementById('m_rejected');
	var elTabList = document.getElementById('petty_tab_list');
	var elTabAnalytics = document.getElementById('petty_tab_analytics');
	var elAnalyticsPanel = document.getElementById('petty_analytics_panel');
	var elTableWrap = document.querySelector('.table-responsive');
	var elExportPdf = document.getElementById('petty_export_pdf');
	var elAnTotalAmount = document.getElementById('an_total_amount');
	var elAnTotalCount = document.getElementById('an_total_count');
	var elAnAvg = document.getElementById('an_avg_amount');
	var elAnMedian = document.getElementById('an_median_amount');
	var elAnCompletionRate = document.getElementById('an_completion_rate');
	var elAnRejectedRate = document.getElementById('an_rejected_rate');
	var elAnTopCat = document.getElementById('an_top_category');
	var elAnTopAmt = document.getElementById('an_top_amount');
	var elAnInsight = document.getElementById('an_insight');
	var elAnCatCanvas = document.getElementById('an_cat_chart');
	var elAnTrendCanvas = document.getElementById('an_trend_chart');
	var elAnStatusCanvas = document.getElementById('an_status_chart');

	var page = 1;
	var totalPages = 1;
	var inflight = null;
	var t = null;

	function esc(s){
		s = (s===undefined||s===null) ? '' : String(s);
		return s.replace(/[&<>"']/g, function(m){
			return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#039;'})[m];
		});
	}
	function money(n){
		var x = parseFloat(n);
		if(isNaN(x)) x = 0;
		return x.toFixed(2);
	}
	function moneyLabel(n){
		var x = parseFloat(n);
		if(isNaN(x)) x = 0;
		try{ return x.toLocaleString(undefined,{minimumFractionDigits:2, maximumFractionDigits:2}); }catch(e){ return money(x); }
	}
	function percentLabel(x){
		x = parseFloat(x);
		if(isNaN(x)) x = 0;
		return (x * 100).toFixed(1) + '%';
	}
	function statusLabel(status){
		var s = String(status || '').toLowerCase();
		if(s === 'pending_ack') return 'Pending Acknowledgement';
		if(s === 'acknowledged') return 'Acknowledged';
		if(s === 'completed') return 'Completed';
		if(s === 'rejected') return 'Rejected';
		return String(status || '');
	}
	function badge(status){
		var k = String(status || '').toLowerCase();
		var cls = 'badge bg-secondary';
		if(k === 'pending_ack') cls = 'badge bg-warning text-dark';
		if(k === 'acknowledged') cls = 'badge bg-info text-dark';
		if(k === 'completed') cls = 'badge bg-success';
		if(k === 'rejected') cls = 'badge bg-danger';
		return '<span class="' + cls + '">' + esc(statusLabel(status)) + '</span>';
	}
	function budgetLabel(row){
		var currency = String((row && row.currency) || 'GHS').toUpperCase();
		return esc(currency) + ' ' + moneyLabel((row && row.budget_amount) || 0);
	}
	function fmtDate(dt){
		return esc(dt || '');
	}
	function rowActions(r){
		var viewUrl = root + '/' + adminRoute + '/' + pluginRoute + '/view/' + encodeURIComponent(r.id);
		var html = '<a class="btn btn-sm btn-outline-primary" href="' + viewUrl + '"><i class="fa fa-eye"></i> View</a>';
		var canEdit = canManage || Number(r.created_by || 0) === currentUserId;
		if(canEdit && String(r.status || '').toLowerCase() === 'pending_ack'){
			var editUrl = root + '/' + adminRoute + '/' + pluginRoute + '/edit/' + encodeURIComponent(r.id);
			var deleteUrl = root + '/' + adminRoute + '/' + pluginRoute + '/delete/' + encodeURIComponent(r.id);
			html += ' <a class="btn btn-sm btn-outline-warning" href="' + editUrl + '"><i class="fa fa-pen"></i></a>';
			html += ' <a class="btn btn-sm btn-outline-danger" href="' + deleteUrl + '"><i class="fa fa-trash"></i></a>';
		}
		return html;
	}
	function schedule(){
		if(t) clearTimeout(t);
		t = setTimeout(function(){ page = 1; fetchList(); }, 250);
	}
	function queryParts(){
		var p = [];
		p.push('page=' + encodeURIComponent(String(page)));
		p.push('limit=' + encodeURIComponent(String(elLimit ? elLimit.value : '30')));
		var q = elQ ? String(elQ.value || '').trim() : '';
		var st = elStatus ? String(elStatus.value || '').trim() : '';
		var cat = elCategory ? String(elCategory.value || '').trim() : '';
		var df = elFrom ? String(elFrom.value || '').trim() : '';
		var dt = elTo ? String(elTo.value || '').trim() : '';
		if(q) p.push('q=' + encodeURIComponent(q));
		if(st) p.push('status=' + encodeURIComponent(st));
		if(cat) p.push('category=' + encodeURIComponent(cat));
		if(df) p.push('date_from=' + encodeURIComponent(df));
		if(dt) p.push('date_to=' + encodeURIComponent(dt));
		return p;
	}
	function buildUrl(endpoint){
		return pettycashPath + '/' + endpoint + '?' + queryParts().join('&');
	}
	function setLoading(){
		if(elBody) elBody.innerHTML = '<tr><td colspan="10" class="text-center">Loading...</td></tr>';
	}
	function fetchList(){
		if(inflight) try{ inflight.abort(); }catch(e){}
		setLoading();
		var xhr = new XMLHttpRequest();
		inflight = xhr;
		xhr.open('GET', buildUrl('ajax_list'), true);
		xhr.withCredentials = true;
		xhr.withCredentials = true;
		xhr.onreadystatechange = function(){
			if(xhr.readyState !== 4) return;
			if(inflight === xhr) inflight = null;
			var res = null;
			try{ res = JSON.parse(xhr.responseText); }catch(e){ res = null; }
			if(!res || !res.success){
				var msg = 'Failed to load';
				try{ if(res && res.message) msg = String(res.message); }catch(e){}
				if(!res && xhr.responseText){
					msg += ' (The server returned a non-JSON response. Please refresh your session.)';
				}
				if(elBody) elBody.innerHTML = '<tr><td colspan="10" class="text-center">' + esc(msg) + '</td></tr>';
				return;
			}
			render(res.data || [], res.meta || {}, res.metrics || {});
		};
		xhr.send();
	}
	function ensureChartJs(cb){
		if(chartJsLoaded && window.Chart) return cb();
		var existing = document.getElementById('chartjs_loader');
		if(existing){
			existing.addEventListener('load', function(){ chartJsLoaded = true; cb(); });
			return;
		}
		var s = document.createElement('script');
		s.id = 'chartjs_loader';
		s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
		s.onload = function(){ chartJsLoaded = true; cb(); };
		s.onerror = function(){ if(elAnInsight) elAnInsight.textContent = 'Failed to load charts library'; };
		document.head.appendChild(s);
	}
	function destroyCharts(){
		try{ if(charts.cat) charts.cat.destroy(); }catch(e){}
		try{ if(charts.trend) charts.trend.destroy(); }catch(e){}
		try{ if(charts.status) charts.status.destroy(); }catch(e){}
		charts.cat = charts.trend = charts.status = null;
	}
	function renderCharts(byCategory, trend, status){
		ensureChartJs(function(){
			destroyCharts();
			var labels = [];
			var totals = [];
			for(var i=0;i<(byCategory||[]).length;i++){
				labels.push(String(byCategory[i].category||''));
				totals.push(parseFloat(byCategory[i].total||0) || 0);
			}
			var tLabels = [];
			var tTotals = [];
			for(var j=0;j<(trend||[]).length;j++){
				tLabels.push(String(trend[j].ym||''));
				tTotals.push(parseFloat(trend[j].total||0) || 0);
			}
			var sLabels = ['Pending Ack', 'Acknowledged', 'Completed', 'Rejected'];
			var sCounts = [
				parseInt(status.pending_ack_count||0,10) || 0,
				parseInt(status.acknowledged_count||0,10) || 0,
				parseInt(status.completed_count||0,10) || 0,
				parseInt(status.rejected_count||0,10) || 0
			];
			if(elAnCatCanvas && window.Chart){
				charts.cat = new Chart(elAnCatCanvas, {
					type: 'bar',
					data: { labels: labels, datasets: [{ label: 'Total Budget', data: totals, backgroundColor: 'rgba(13,110,253,0.35)', borderColor: 'rgba(13,110,253,0.9)', borderWidth: 1 }] },
					options: { responsive: true, plugins: { legend: { display: false } } }
				});
			}
			if(elAnTrendCanvas && window.Chart){
				charts.trend = new Chart(elAnTrendCanvas, {
					type: 'line',
					data: { labels: tLabels, datasets: [{ label: 'Monthly Budget', data: tTotals, fill: true, backgroundColor: 'rgba(25,135,84,0.12)', borderColor: 'rgba(25,135,84,0.9)', tension: 0.25, pointRadius: 3 }] },
					options: { responsive: true, plugins: { legend: { display: false } } }
				});
			}
			if(elAnStatusCanvas && window.Chart){
				charts.status = new Chart(elAnStatusCanvas, {
					type: 'doughnut',
					data: { labels: sLabels, datasets: [{ data: sCounts, backgroundColor: ['rgba(255,193,7,0.85)','rgba(13,202,240,0.85)','rgba(25,135,84,0.85)','rgba(220,53,69,0.85)'] }] },
					options: { responsive: true, maintainAspectRatio: false, cutout: '68%', plugins: { legend: { position: 'bottom' } } }
				});
			}
		});
	}
	function fetchAnalytics(){
		if(!elAnalyticsPanel) return;
		if(elAnInsight) elAnInsight.textContent = 'Loading...';
		var xhr = new XMLHttpRequest();
		xhr.open('GET', buildUrl('ajax_analytics'), true);
		xhr.onreadystatechange = function(){
			if(xhr.readyState !== 4) return;
			var res = null;
			try{ res = JSON.parse(xhr.responseText); }catch(e){ res = null; }
			if(!res || !res.success){
				if(elAnInsight) elAnInsight.textContent = 'Failed to load analytics';
				return;
			}
			var summary = res.summary || {};
			var status = res.status || {};
			var byCategory = res.by_category || [];
			var trend = res.trend || [];

			if(elAnTotalAmount) elAnTotalAmount.textContent = moneyLabel(summary.total_amount || 0);
			if(elAnTotalCount) elAnTotalCount.textContent = String(summary.total_count || 0);
			if(elAnAvg) elAnAvg.textContent = money(summary.avg_amount || 0);
			if(elAnMedian) elAnMedian.textContent = money(summary.median_amount || 0);

			var totalCount = parseFloat(summary.total_count || 0) || 0;
			var completionRate = (totalCount > 0) ? ((parseFloat(status.completed_count||0)||0) / totalCount) : 0;
			var rejectedRate = (totalCount > 0) ? ((parseFloat(status.rejected_count||0)||0) / totalCount) : 0;
			if(elAnCompletionRate) elAnCompletionRate.textContent = percentLabel(completionRate);
			if(elAnRejectedRate) elAnRejectedRate.textContent = percentLabel(rejectedRate);

			var topCat = byCategory.length ? String(byCategory[0].category || '-') : '-';
			var topAmt = byCategory.length ? (parseFloat(byCategory[0].total||0) || 0) : 0;
			if(elAnTopCat) elAnTopCat.textContent = topCat;
			if(elAnTopAmt) elAnTopAmt.textContent = money(topAmt);

			var pendingRate = (totalCount > 0)
				? (((parseFloat(status.pending_ack_count||0)||0) + (parseFloat(status.acknowledged_count||0)||0)) / totalCount)
				: 0;
			var insight = '';
			if(totalCount <= 0){
				insight = 'No petty cash transactions found for the selected filters';
			}else if(rejectedRate >= 0.2){
				insight = 'Review approval discipline and budgeting accuracy (high rejection rate: ' + percentLabel(rejectedRate) + ')';
			}else if(pendingRate >= 0.3){
				insight = 'Follow up on open petty cash transactions (open rate: ' + percentLabel(pendingRate) + ')';
			}else if(topCat && topCat !== '-'){
				insight = 'Monitor ' + topCat + ' petty cash usage because it currently carries the highest budget share';
			}else{
				insight = 'No specific petty cash risk detected';
			}
			if(elAnInsight) elAnInsight.textContent = insight;
			renderCharts(byCategory, trend, status);
		};
		xhr.send();
	}
	function showTab(name){
		activeTab = name;
		if(elTabList && elTabAnalytics){
			if(name === 'analytics'){
				elTabAnalytics.classList.remove('btn-outline-primary');
				elTabAnalytics.classList.add('btn-primary');
				elTabList.classList.remove('btn-primary');
				elTabList.classList.add('btn-outline-primary');
			}else{
				elTabList.classList.remove('btn-outline-primary');
				elTabList.classList.add('btn-primary');
				elTabAnalytics.classList.remove('btn-primary');
				elTabAnalytics.classList.add('btn-outline-primary');
			}
		}
		if(name === 'analytics'){
			if(elTableWrap) elTableWrap.classList.add('d-none');
			if(elAnalyticsPanel) elAnalyticsPanel.classList.remove('d-none');
			fetchAnalytics();
		}else{
			if(elAnalyticsPanel) elAnalyticsPanel.classList.add('d-none');
			if(elTableWrap) elTableWrap.classList.remove('d-none');
		}
	}
	function render(rows, meta, metrics){
		var html = '';
		if(!rows || !rows.length){
			html = '<tr><td colspan="10" class="text-center">No records to show!</td></tr>';
		}else{
			for(var i=0;i<rows.length;i++){
				var r = rows[i];
				var manager = esc(((r.creator_first_name||'') + ' ' + (r.creator_last_name||'')).trim());
				var assigned = esc(((r.assignee_first_name||'') + ' ' + (r.assignee_last_name||'')).trim());
				var compactDetails = '';
				compactDetails += '<div class="fw-bold">' + esc(r.title || '') + '</div>';
				compactDetails += '<div class="text-muted small">' + esc(r.reference || '') + ' | ' + fmtDate(r.date_created) + '</div>';
				compactDetails += '<div class="small mt-1"><span class="badge bg-secondary">' + esc(r.category || '') + '</span> <span class="ms-2 fw-bold">' + budgetLabel(r) + '</span></div>';
				compactDetails += '<div class="text-muted small mt-1">' + esc(r.description || '') + '</div>';
				var compactStatus = '';
				compactStatus += '<div>' + badge(r.status) + '</div>';
				compactStatus += '<div class="text-muted small mt-2">Assigned</div>';
				compactStatus += '<div class="small">' + assigned + '</div>';
				html += ''
					+ '<tr>'
						+ '<td class="d-lg-none wrap">' + compactDetails + '</td>'
						+ '<td class="d-lg-none">' + compactStatus + '</td>'
						+ '<td class="d-lg-none text-end" style="white-space:nowrap;">' + rowActions(r) + '</td>'
						+ '<td class="d-none d-lg-table-cell"><span class="badge bg-warning text-black rounded-circle pill">' + String(i+1) + '</span></td>'
						+ '<td class="d-none d-lg-table-cell wrap"><div class="fw-semibold">' + esc(r.reference || r.id) + '</div><div class="text-muted small">' + fmtDate(r.date_created) + '</div></td>'
						+ '<td class="d-none d-lg-table-cell">' + esc(r.category || '') + '</td>'
						+ '<td class="d-none d-lg-table-cell wrap"><div class="fw-semibold">' + esc(r.title || '') + '</div><div class="text-muted small">' + esc(r.description || '') + '</div></td>'
						+ '<td class="d-none d-lg-table-cell"><div class="fw-semibold">' + budgetLabel(r) + '</div><div class="mt-1">' + badge(r.status) + '</div></td>'
						+ '<td class="d-none d-lg-table-cell"><div class="fw-semibold">' + manager + '</div><div class="text-muted small mt-1">Assigned: ' + assigned + '</div></td>'
						+ '<td class="d-none d-lg-table-cell" style="white-space:nowrap;">' + rowActions(r) + '</td>'
					+ '</tr>';
			}
		}
		if(elBody) elBody.innerHTML = html;
		totalPages = parseInt(meta.total_pages || 1, 10);
		if(isNaN(totalPages) || totalPages < 1) totalPages = 1;
		if(elTotalAmount) elTotalAmount.textContent = moneyLabel(meta.total_amount || 0);
		if(elMeta) elMeta.textContent = 'Page ' + String(meta.page || page) + ' of ' + String(totalPages) + ' | ' + String(meta.total || 0) + ' record(s)';
		if(elPrev) elPrev.disabled = page <= 1;
		if(elNext) elNext.disabled = page >= totalPages;
		if(elMPendingAck) elMPendingAck.textContent = String(metrics.pending_ack || 0);
		if(elMAcknowledged) elMAcknowledged.textContent = String(metrics.acknowledged || 0);
		if(elMCompleted) elMCompleted.textContent = String(metrics.completed || 0);
		if(elMRejected) elMRejected.textContent = String(metrics.rejected || 0);
	}

	if(elQ) elQ.addEventListener('input', function(){ activeTab === 'analytics' ? scheduleAnalytics() : schedule(); });
	if(elStatus) elStatus.addEventListener('change', function(){ page = 1; fetchList(); if(activeTab === 'analytics') fetchAnalytics(); });
	if(elCategory) elCategory.addEventListener('change', function(){ page = 1; fetchList(); if(activeTab === 'analytics') fetchAnalytics(); });
	if(elFrom) elFrom.addEventListener('change', function(){ page = 1; fetchList(); if(activeTab === 'analytics') fetchAnalytics(); });
	if(elTo) elTo.addEventListener('change', function(){ page = 1; fetchList(); if(activeTab === 'analytics') fetchAnalytics(); });
	if(elLimit) elLimit.addEventListener('change', function(){ page = 1; fetchList(); });
	if(elClear) elClear.addEventListener('click', function(){
		if(elQ) elQ.value = '';
		if(elStatus) elStatus.value = '';
		if(elCategory) elCategory.value = '';
		if(elFrom) elFrom.value = '';
		if(elTo) elTo.value = '';
		page = 1;
		fetchList();
		if(activeTab === 'analytics') fetchAnalytics();
	});
	if(elPrev) elPrev.addEventListener('click', function(){ if(page > 1){ page--; fetchList(); } });
	if(elNext) elNext.addEventListener('click', function(){ if(page < totalPages){ page++; fetchList(); } });
	if(elTabList) elTabList.addEventListener('click', function(){ showTab('list'); });
	if(elTabAnalytics) elTabAnalytics.addEventListener('click', function(){ showTab('analytics'); });
	if(elExportPdf) elExportPdf.addEventListener('click', function(){
		window.open(buildUrl('export_pdf'), 'pettycash_pdf', 'width=1000,height=700');
	});

	function scheduleAnalytics(){
		if(t) clearTimeout(t);
		t = setTimeout(function(){ fetchAnalytics(); }, 250);
	}

	fetchList();
	showTab('list');
})();
</script>

<?php else: ?>
	<div class="alert alert-danger text-center">Access denied.</div>
<?php endif; ?>
