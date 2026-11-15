<?php if(user_can('view_payment')):?>
<style type="text/css">
		#paymentsTable tbody tr{
			white-space: nowrap;
		}
		#paymentsTable thead tr{
			white-space: nowrap;
		}
		#paymentsTable tfoot tr{
			white-space: nowrap;
		}

		#paymentsTable td.wrap{
			white-space: normal;
		}

		@media (max-width: 991.98px){
			#paymentsTable tbody tr{ margin-bottom: .75rem; }
		}
	</style>

<div class="row my-3 mx-auto">
	<div class="col-12 mb-2">
		<div class="d-flex flex-wrap gap-2 mb-2" id="pay_metrics" style="min-height: 30px;">
			<span class="badge bg-success">Approved: <span id="m_approved">0</span></span>
			<span class="badge bg-warning text-dark">Pending: <span id="m_pending">0</span></span>
			<span class="badge bg-secondary">Draft: <span id="m_draft">0</span></span>
			<span class="badge bg-danger">Rejected: <span id="m_rejected">0</span></span>
		</div>
		<div class="row g-2">
			<div class="col-12 col-md-4">
				<input id="pay_q" class="form-control" placeholder="Search (description, id, status, user)" type="text" />
			</div>
			<div class="col-6 col-md-2 d-none d-lg-inline-block">
				<select id="pay_status" class="form-select">
					<option value="">All Status</option>
					<option value="Draft">Draft</option>
					<option value="Pending">Pending</option>
					<option value="Approved">Approved</option>
					<option value="Rejected">Rejected</option>
				</select>
			</div>
			<div class="col-6 col-md-2 d-none d-lg-inline-block">
				<select id="pay_category" class="form-select">
					<option value="">All Categories</option>
					<option value="GENERAL">GENERAL</option>
					<option value="SALARY">SALARY</option>
					<option value="UTILITIES">UTILITIES</option>
					<option value="MAINTENANCE">MAINTENANCE</option>
					<option value="OPERATIONAL">OPERATIONAL</option>
					<option value="LOGISTICS">LOGISTICS</option>
					<option value="RENT">RENT</option>
					<option value="MARKETING">MARKETING</option>
				</select>
			</div>
			<div class="col-6 col-md-2 d-none d-lg-inline-block">
				<input id="pay_from" class="form-control" type="date" />
			</div>
			<div class="col-6 col-md-2 d-none d-lg-inline-block">
				<input id="pay_to" class="form-control" type="date" />
			</div>
			<div class="col-12 col-md-1 input-group">
				<select id="pay_limit" class="form-select" style="max-width: 80px;">
					<option value="10">10</option>
					<option value="30" selected>30</option>
					<option value="50">50</option>
					<option value="100">100</option>
				</select>
				<label class="input-group-text">Per Page</label>
			</div>
		</div>
	</div>
	<div class="col-12 d-flex justify-content-lg-end align-items-start gap-2">
		<button class="btn btn-outline-secondary" type="button" id="pay_clear">Clear</button>
		<button class="btn btn-primary" type="button" id="pay_tab_list">
			<i class="fa-solid fa-list"></i> List
		</button>
		<button class="btn btn-outline-primary" type="button" id="pay_tab_analytics">
			<i class="fa-solid fa-chart-line"></i> Analytics
		</button>
		<?php if(user_can('add_payment')):?>
		<a style="text-decoration: none;" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/add">
			<button class="btn btn-warning" type="button">
				<i class="fa-solid fa-plus"></i> New
			</button>
		</a>
		<?php endif?>
	</div>
</div>

<div class="table-responsive">
	<table class="table table-striped table-hover" id="paymentsTable">
		<thead>
			<tr>
				<th class="bg-secondary text-warning d-lg-none">Details</th>
				<th class="bg-secondary text-warning d-lg-none">Status</th>
				<th class="bg-secondary text-warning d-lg-none">Action</th>

				<th class="bg-secondary text-warning d-none d-lg-table-cell">#</th>
				<th class="bg-secondary text-warning d-none d-lg-table-cell">Date created</th>
				<th class="bg-secondary text-warning d-none d-lg-table-cell">Category</th>
				<th class="bg-secondary text-warning d-none d-lg-table-cell">Description</th>
				<th class="bg-secondary text-warning d-none d-lg-table-cell">Amount</th>
				<th class="bg-secondary text-warning d-none d-lg-table-cell">Status</th>
				<th class="bg-secondary text-warning d-none d-lg-table-cell">Created by</th>
				<th class="bg-secondary text-warning d-none d-lg-table-cell">Action</th>
			</tr>
		</thead>
		<tbody id="pay_tbody">
			<tr><td colspan="11" class="text-center">Loading...</td></tr>
		</tbody>
		<tfoot class="d-none d-lg-table-footer-group">			
			<tr>
				<th colspan="4" class="bg-secondary text-warning text-center">TOTAL (Filtered)</th>
				<th class="bg-secondary text-warning" id="pay_total_amount">0.00</th>
				<th class="bg-secondary text-warning"></th>
				<th class="bg-secondary text-warning"></th>
				<th class="bg-secondary text-warning"></th>
			</tr>
		</tfoot>
	</table>

	<div class="d-flex justify-content-between align-items-center">
		<div class="text-muted" id="pay_meta"></div>
		<div class="d-flex gap-2">
			<button class="btn btn-sm btn-outline-primary" type="button" id="pay_prev">Prev</button>
			<button class="btn btn-sm btn-outline-primary" type="button" id="pay_next">Next</button>
		</div>
	</div>
</div>

<div class="mt-3 d-none" id="pay_analytics_panel">
	<div class="row g-2">
		<div class="col-12 col-md-3">
			<div class="p-3 border rounded">
				<div class="text-muted">Total Spend</div>
				<div class="h4 mb-0" id="an_total_amount">GH₵ 0.00</div>
				<div class="text-muted small"><span id="an_total_count">0</span> payment(s)</div>
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
				<div class="text-muted">Approval Rate</div>
				<div class="h4 mb-0" id="an_approval_rate">0%</div>
				<div class="text-muted small">Pending: <span id="an_pending_rate">0%</span></div>
			</div>
		</div>
		<div class="col-12 col-md-3">
			<div class="p-3 border rounded">
				<div class="text-muted">Top Category</div>
				<div class="h6 mb-0" id="an_top_category">-</div>
				<div class="text-muted">GH₵ <span id="an_top_amount">0.00</span></div>
			</div>
		</div>
	</div>

	<div class="row g-3 mt-1">
		<div class="col-12 col-lg-6">
			<div class="p-3 border rounded">
				<div class="fw-bold">Spend by Category</div>
				<canvas id="an_cat_chart" height="140"></canvas>
			</div>
		</div>
		<div class="col-12 col-lg-6">
			<div class="p-3 border rounded">
				<div class="fw-bold">Spend Trend (Monthly)</div>
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
	var canEdit = <?=user_can('edit_payment') ? 'true' : 'false'?>;
	var chartJsLoaded = false;
	var charts = { cat: null, trend: null, status: null };
	var activeTab = 'list';

	var elQ = document.getElementById('pay_q');
	var elStatus = document.getElementById('pay_status');
	var elCategory = document.getElementById('pay_category');
	var elFrom = document.getElementById('pay_from');
	var elTo = document.getElementById('pay_to');
	var elLimit = document.getElementById('pay_limit');
	var elClear = document.getElementById('pay_clear');
	var elBody = document.getElementById('pay_tbody');
	var elMeta = document.getElementById('pay_meta');
	var elTotalAmount = document.getElementById('pay_total_amount');
	var elPrev = document.getElementById('pay_prev');
	var elNext = document.getElementById('pay_next');
	var elMApproved = document.getElementById('m_approved');
	var elMPending = document.getElementById('m_pending');
	var elMDraft = document.getElementById('m_draft');
	var elMRejected = document.getElementById('m_rejected');
	var elTabList = document.getElementById('pay_tab_list');
	var elTabAnalytics = document.getElementById('pay_tab_analytics');
	var elAnalyticsPanel = document.getElementById('pay_analytics_panel');
	var elTableWrap = document.querySelector('.table-responsive');
	var elAnTotalAmount = document.getElementById('an_total_amount');
	var elAnTotalCount = document.getElementById('an_total_count');
	var elAnAvg = document.getElementById('an_avg_amount');
	var elAnMedian = document.getElementById('an_median_amount');
	var elAnApprovalRate = document.getElementById('an_approval_rate');
	var elAnPendingRate = document.getElementById('an_pending_rate');
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
		try{ return 'GH₵ ' + x.toLocaleString(undefined,{minimumFractionDigits:2, maximumFractionDigits:2}); }catch(e){ return 'GH₵ ' + money(x); }
	}
	function percentLabel(x){
		x = parseFloat(x);
		if(isNaN(x)) x = 0;
		return (x * 100).toFixed(1) + '%';
	}

	function badge(status){
		var s = String(status||'');
		var k = s.toLowerCase();
		var cls = 'badge bg-secondary';
		if(k === 'pending') cls = 'badge bg-warning text-dark';
		if(k === 'approved') cls = 'badge bg-success';
		if(k === 'rejected') cls = 'badge bg-danger';
		return '<span class="' + cls + '">' + esc(s) + '</span>';
	}
	function rowActions(r){
		var viewUrl = root + '/' + adminRoute + '/' + pluginRoute + '/view/' + encodeURIComponent(r.id);
		var html = '<a class="btn btn-sm btn-outline-primary" href="' + viewUrl + '"><i class="fa fa-eye"></i> View</a>';
		if(canEdit){
			var st = String(r.transaction_status||'').toLowerCase();
			if(st === 'draft' || st === 'pending'){
				var editUrl = root + '/' + adminRoute + '/' + pluginRoute + '/edit/' + encodeURIComponent(r.id);
				html += ' <a class="btn btn-sm btn-outline-warning" href="' + editUrl + '"><i class="fa fa-pen"></i></a>';
			}
		}
		return html;
	}
	function fmtDate(dt){
		return esc(dt || '');
	}
	function schedule(){
		if(t) clearTimeout(t);
		t = setTimeout(function(){ page = 1; fetchList(); }, 250);
	}
	function buildUrl(){
		var u = root + '/' + adminRoute + '/' + pluginRoute + '/ajax_list';

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
		return u + '?' + p.join('&');
	}
	function setLoading(){
		if(elBody) elBody.innerHTML = '<tr><td colspan="11" class="text-center">Loading...</td></tr>';
	}
	function fetchList(){
		if(inflight) try{ inflight.abort(); }catch(e){}
		setLoading();
		var url = buildUrl();
		var xhr = new XMLHttpRequest();
		inflight = xhr;
		xhr.open('GET', url, true);
		xhr.onreadystatechange = function(){
			if(xhr.readyState !== 4) return;
			if(inflight === xhr) inflight = null;
			var res = null;
			try{ res = JSON.parse(xhr.responseText); }catch(e){ res = null; }
			if(!res || !res.success){
				var msg = 'Failed to load';
				try{ if(res && res.message) msg = String(res.message); }catch(e){}
				var raw = '';
				try{ raw = String(xhr.responseText || '').slice(0, 200); }catch(e){}
				if(elBody) elBody.innerHTML = '<tr><td colspan="11" class="text-center">' + esc(msg) + (raw ? ('<div class="text-muted small mt-1">' + esc(raw) + '</div>') : '') + '</td></tr>';
				return;
			}
			render(res.data || [], res.meta || {}, res.metrics || {});
		};
		xhr.send();
	}
	function buildAnalyticsUrl(){
		var u = root + '/' + adminRoute + '/' + pluginRoute + '/ajax_analytics';
		var p = [];
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
		return u + '?' + p.join('&');
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
			var sLabels = ['Approved','Pending','Draft','Rejected'];
			var sCounts = [
				parseInt(status.approved_count||0,10) || 0,
				parseInt(status.pending_count||0,10) || 0,
				parseInt(status.draft_count||0,10) || 0,
				parseInt(status.rejected_count||0,10) || 0,
			];
			function tickMoney(v){
				try{ return 'GH₵ ' + Number(v||0).toLocaleString(undefined,{minimumFractionDigits:0, maximumFractionDigits:0}); }catch(e){ return v; }
			}
			if(elAnCatCanvas && window.Chart){
				charts.cat = new Chart(elAnCatCanvas, {
					type: 'bar',
					data: { labels: labels, datasets: [{ label: 'Total', data: totals, backgroundColor: 'rgba(13,110,253,0.35)', borderColor: 'rgba(13,110,253,0.9)', borderWidth: 1 }] },
					options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { ticks: { callback: tickMoney } } } }
				});
			}
			if(elAnTrendCanvas && window.Chart){
				charts.trend = new Chart(elAnTrendCanvas, {
					type: 'line',
					data: { labels: tLabels, datasets: [{ label: 'Monthly Total', data: tTotals, fill: true, backgroundColor: 'rgba(25,135,84,0.12)', borderColor: 'rgba(25,135,84,0.9)', tension: 0.25, pointRadius: 3 }] },
					options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { ticks: { callback: tickMoney } } } }
				});
			}
			if(elAnStatusCanvas && window.Chart){
				charts.status = new Chart(elAnStatusCanvas, {
					type: 'doughnut',
					data: { labels: sLabels, datasets: [{ data: sCounts, backgroundColor: ['rgba(25,135,84,0.85)','rgba(255,193,7,0.85)','rgba(108,117,125,0.85)','rgba(220,53,69,0.85)'] }] },
					options: { responsive: true, maintainAspectRatio: false, cutout: '68%', plugins: { legend: { position: 'bottom' } } }
				});
			}
		});
	}
	function fetchAnalytics(){
		if(!elAnalyticsPanel) return;
		if(elAnInsight) elAnInsight.textContent = 'Loading...';
		var url = buildAnalyticsUrl();
		var xhr = new XMLHttpRequest();
		xhr.open('GET', url, true);
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
			var approvedRate = (totalCount > 0) ? ((parseFloat(status.approved_count||0)||0) / totalCount) : 0;
			var pendingRate = (totalCount > 0) ? ((parseFloat(status.pending_count||0)||0) / totalCount) : 0;
			if(elAnApprovalRate) elAnApprovalRate.textContent = percentLabel(approvedRate);
			if(elAnPendingRate) elAnPendingRate.textContent = percentLabel(pendingRate);

			var topCat = byCategory.length ? String(byCategory[0].category || '-') : '-';
			var topAmt = byCategory.length ? (parseFloat(byCategory[0].total||0) || 0) : 0;
			if(elAnTopCat) elAnTopCat.textContent = topCat;
			if(elAnTopAmt) elAnTopAmt.textContent = money(topAmt);

			var rejectedRate = (totalCount > 0) ? ((parseFloat(status.rejected_count||0)||0) / totalCount) : 0;
			var insight = '';
			if(totalCount <= 0){
				insight = 'No payments found for the selected filters';
			}else if(rejectedRate >= 0.2){
				insight = 'Improve approval workflow (high rejection rate: ' + percentLabel(rejectedRate) + ')';
			}else if(pendingRate >= 0.3){
				insight = 'Reduce pending backlog (pending rate: ' + percentLabel(pendingRate) + ')';
			}else if(topCat && topCat !== '-'){
				insight = 'Review controls for ' + topCat + ' spending (highest category)';
			}else{
				insight = 'No specific risk detected';
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
			html = '<tr><td colspan="11" class="text-center">No records to show!</td></tr>';
		}else{
			for(var i=0;i<rows.length;i++){
				var r = rows[i];
				var createdBy = '';
				if(r.user_first_name || r.user_last_name){
					createdBy = esc((r.user_first_name||'') + ' ' + (r.user_last_name||''));
				}

				var compactDetails = '';
				compactDetails += '<div class="fw-bold">' + esc(r.description || '') + '</div>';
				compactDetails += '<div class="text-muted small">#' + esc(r.id) + ' | ' + fmtDate(r.date_created) + '</div>';
				compactDetails += '<div class="small mt-1"><span class="badge bg-secondary">' + esc(r.category || '') + '</span> <span class="ms-2 fw-bold">GH₵ ' + money(r.amount) + '</span></div>';

				var compactStatus = '';
				compactStatus += '<div>' + badge(r.transaction_status) + '</div>';
				compactStatus += '<div class="text-muted small mt-2">By</div>';
				compactStatus += '<div class="small">' + createdBy + '</div>';

				html += ''
					+ '<tr>'
						+ '<td class="d-lg-none wrap">' + compactDetails + '</td>'
						+ '<td class="d-lg-none">' + compactStatus + '</td>'
						+ '<td class="d-lg-none text-end" style="white-space:nowrap;">' + rowActions(r) + '</td>'

						+ '<td class="d-none d-lg-table-cell"><span class="badge bg-warning text-black rounded-circle pill">' + String(i+1) + '</span> ' + esc(r.id) + '</td>'
						+ '<td class="d-none d-lg-table-cell wrap">' + fmtDate(r.date_created) + '</td>'
						+ '<td class="d-none d-lg-table-cell">' + esc(r.category || '') + '</td>'
						+ '<td class="d-none d-lg-table-cell wrap">' + esc(r.description || '') + '</td>'
						+ '<td class="d-none d-lg-table-cell">' + money(r.amount) + '</td>'
						+ '<td class="d-none d-lg-table-cell">' + badge(r.transaction_status) + '</td>'
						+ '<td class="d-none d-lg-table-cell">' + createdBy + '</td>'
						+ '<td class="d-none d-lg-table-cell" style="white-space:nowrap;">' + rowActions(r) + '</td>'
					+ '</tr>';
			}
		}

		if(elBody) elBody.innerHTML = html;
		totalPages = parseInt(meta.total_pages || 1, 10);
		if(isNaN(totalPages) || totalPages < 1) totalPages = 1;
		if(elTotalAmount) elTotalAmount.textContent = money(meta.total_amount || 0);
		if(elMeta) elMeta.textContent = 'Page ' + String(meta.page || page) + ' of ' + String(totalPages) + ' | ' + String(meta.total || 0) + ' record(s)';
		if(elPrev) elPrev.disabled = page <= 1;
		if(elNext) elNext.disabled = page >= totalPages;
		if(elMApproved) elMApproved.textContent = String(metrics.approved || 0);
		if(elMPending) elMPending.textContent = String(metrics.pending || 0);
		if(elMDraft) elMDraft.textContent = String(metrics.draft || 0);
		if(elMRejected) elMRejected.textContent = String(metrics.rejected || 0);
	}

	if(elQ) elQ.addEventListener('input', function(){
		if(activeTab === 'analytics'){
			if(t) clearTimeout(t);
			t = setTimeout(function(){ fetchAnalytics(); }, 250);
		}else{
			schedule();
		}
	});
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

	fetchList();
	showTab('list');
})();
</script>

<?php else:?>
	<div class="alert alert-danger text-center">
		Access denied. You dont have permission for this action
	</div>
<?php endif?>