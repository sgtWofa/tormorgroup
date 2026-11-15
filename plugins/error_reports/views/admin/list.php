<?php

if(!user_can('view_error_reports')){
	echo '<div class="alert alert-danger text-center">Access denied.</div>';
	return;
}
$vars = get_value();
$admin_route = $vars['admin_route'];
$plugin_route = $vars['plugin_route'];

$csrf = csrf();

$initialFind = trim((string)($_GET['find'] ?? ''));
$initialSeverity = strtoupper(trim((string)($_GET['severity'] ?? '')));
$initialStatus = strtoupper(trim((string)($_GET['status'] ?? '')));
$initialDateFrom = trim((string)($_GET['date_from'] ?? ''));
$initialDateTo = trim((string)($_GET['date_to'] ?? ''));
$initialLimit = (int)($_GET['limit'] ?? 10);
$initialLimit = $initialLimit > 0 ? min(200, $initialLimit) : 10;
$initialPage = (int)($_GET['page'] ?? 1);
$initialPage = $initialPage < 1 ? 1 : $initialPage;

?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
	:root{
		--er-surface: linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(248,250,252,0.95) 35%, rgba(255,255,255,0.92) 100%);
		--er-surface-2: linear-gradient(135deg, rgba(255,255,255,0.92) 0%, rgba(245,247,255,0.92) 40%, rgba(255,255,255,0.9) 100%);
		--er-border: rgba(0,0,0,0.10);
		--er-text: #111827;
		--er-muted: rgba(17,24,39,0.65);
	}
	html[data-bs-theme="dark"]{
		--er-surface: linear-gradient(135deg, rgba(17,24,39,0.92) 0%, rgba(2,6,23,0.88) 55%, rgba(17,24,39,0.92) 100%);
		--er-surface-2: linear-gradient(135deg, rgba(15,23,42,0.92) 0%, rgba(2,6,23,0.88) 55%, rgba(15,23,42,0.92) 100%);
		--er-border: rgba(255,255,255,0.14);
		--er-text: rgba(255,255,255,0.92);
		--er-muted: rgba(255,255,255,0.62);
	}
	html[data-bs-theme="dark"] .er-table thead th{color: rgba(255,255,255,0.82);}
	html[data-bs-theme="dark"] .er-table td{color: rgba(255,255,255,0.90);}
	html[data-bs-theme="dark"] .text-muted{color: var(--er-muted) !important;}
	html[data-bs-theme="dark"] .btn-outline-secondary{border-color: rgba(255,255,255,0.24); color: rgba(255,255,255,0.86);}
	html[data-bs-theme="dark"] .btn-outline-secondary:hover{background: rgba(255,255,255,0.10);}
	html[data-bs-theme="dark"] .form-control,
	html[data-bs-theme="dark"] .form-select{background-color: rgba(2,6,23,0.35); border-color: rgba(255,255,255,0.18); color: rgba(255,255,255,0.92);}
	html[data-bs-theme="dark"] .form-control::placeholder{color: rgba(255,255,255,0.55);}
	html[data-bs-theme="dark"] .form-control:focus,
	html[data-bs-theme="dark"] .form-select:focus{background-color: rgba(2,6,23,0.45); color: rgba(255,255,255,0.92);}
	html[data-bs-theme="dark"] .er-pagebtn{background: rgba(2,6,23,0.35); border-color: rgba(255,255,255,0.18); color: rgba(255,255,255,0.86);}
	html[data-bs-theme="dark"] .er-pages .er-pagebtn.active{background:#0d6efd;color:#fff;border-color:#0d6efd;}

	.er-kpis{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;margin-bottom:14px;}
	@media (max-width: 1200px){.er-kpis{grid-template-columns:repeat(3,minmax(0,1fr));}}
	@media (max-width: 768px){.er-kpis{grid-template-columns:repeat(2,minmax(0,1fr));}}
	.er-kpi{border:1px solid var(--er-border);border-radius:14px;padding:12px;background: var(--er-surface);box-shadow: 0 10px 24px rgba(0,0,0,0.06);}
	.er-kpi .k{font-size:0.85rem;color:var(--er-muted);font-weight:800;}
	.er-kpi .v{font-size:1.45rem;font-weight:900;line-height:1.15;}
	.er-kpi .s{font-size:0.8rem;color:var(--er-muted);}
	.er-toolbar{border:1px solid var(--er-border);border-radius:14px;padding:12px;background: var(--er-surface-2);box-shadow: 0 10px 24px rgba(0,0,0,0.06);}
	.er-table td, .er-table th{vertical-align:middle;}
	.er-empty{border:1px dashed var(--er-border);border-radius:14px;padding:18px;background: var(--er-surface);}
	.er-pagination{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;}
	.er-pagebtn{border:1px solid rgba(0,0,0,0.12);background:#fff;border-radius:10px;padding:8px 12px;font-weight:900;cursor:pointer;}
	.er-pagebtn[disabled]{opacity:0.5;cursor:not-allowed;}
	.er-pages{display:flex;gap:6px;flex-wrap:wrap;}
	.er-pages .er-pagebtn.active{background:#0d6efd;color:#fff;border-color:#0d6efd;}

	.er-msg{
		border: 1px solid var(--er-border);
		border-left: 3px solid #619ffcff;
		border-radius: 14px;
		background: var(--er-surface);
		box-shadow: 0 10px 22px rgba(0,0,0,0.06);
		white-space: normal;
		overflow-wrap: anywhere;
		word-break: break-word;
		padding: 12px;
	}
	html[data-bs-theme="dark"] .er-msg{
		border-left-color: rgba(13,110,253,0.95);
	}
</style>

<div class="container-fluid">
	<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
		<div>
			<h3 class="m-0 fw-bolder">Error Reports</h3>
			<div class="text-muted" style="font-size:0.9rem;">Monitor, triage, and resolve issues across the system</div>
		</div>
		<div class="text-muted" style="font-size:0.85rem;">
			<span id="erTotalText">Loading…</span>
		</div>
	</div>

	<div class="er-kpis">
		<div class="er-kpi">
			<div class="k">Open</div>
			<div class="v text-primary" id="kpiOpen">—</div>
			<div class="s">Needs action</div>
		</div>
		<div class="er-kpi">
			<div class="k">Resolved</div>
			<div class="v text-success" id="kpiResolved">—</div>
			<div class="s">Closed out</div>
		</div>
		<div class="er-kpi">
			<div class="k">Today</div>
			<div class="v" id="kpiToday">—</div>
			<div class="s">New reports</div>
		</div>
		<div class="er-kpi">
			<div class="k" id="kpiWindowLabel">Last 7 days</div>
			<div class="v" id="kpiWeek">—</div>
			<div class="s">Trend window</div>
		</div>
		<div class="er-kpi">
			<div class="k">Fatal (Open)</div>
			<div class="v text-danger" id="kpiFatalOpen">—</div>
			<div class="s">Highest priority</div>
		</div>
	</div>

	<div class="er-toolbar mb-3">
		<div class="row g-2 align-items-end">
			<div class="col-md-4">
				<label class="form-label text-muted" style="font-size:0.85rem;">Search</label>
				<input id="erFind" class="form-control" value="<?= htmlspecialchars($initialFind) ?>" placeholder="Title, message, user, url...">
			</div>
			<div class="col-md-2">
				<label class="form-label text-muted" style="font-size:0.85rem;">Severity</label>
				<select id="erSeverity" class="form-select">
					<option value="">All</option>
					<?php foreach(['INFO','WARN','ERROR','FATAL'] as $sev): ?>
						<option value="<?= $sev ?>" <?= $initialSeverity===$sev?'selected':'' ?>><?= $sev ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="col-md-2">
				<label class="form-label text-muted" style="font-size:0.85rem;">Status</label>
				<select id="erStatus" class="form-select">
					<option value="">All</option>
					<?php foreach(['OPEN','RESOLVED'] as $st): ?>
						<option value="<?= $st ?>" <?= $initialStatus===$st?'selected':'' ?>><?= $st ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="col-md-2">
				<label class="form-label text-muted" style="font-size:0.85rem;">From</label>
				<input id="erDateFrom" type="date" class="form-control" value="<?= htmlspecialchars($initialDateFrom) ?>">
			</div>
			<div class="col-md-2">
				<label class="form-label text-muted" style="font-size:0.85rem;">To</label>
				<input id="erDateTo" type="date" class="form-control" value="<?= htmlspecialchars($initialDateTo) ?>">
			</div>
			<div class="col-12 d-flex gap-2 mt-1">
				<button id="erApply" type="button" class="btn btn-primary">Apply</button>
				<button id="erReset" type="button" class="btn btn-outline-secondary">Reset</button>
				<div class="ms-auto d-flex align-items-center gap-2">
					<span class="text-muted" style="font-size:0.85rem;">Rows</span>
					<select id="erLimit" class="form-select" style="width:auto;">
						<?php foreach([10,20,30,50,100,200] as $l): ?>
							<option value="<?= (int)$l ?>" <?= $initialLimit===(int)$l?'selected':'' ?>><?= (int)$l ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
		</div>
	</div>

	<div class="er-toolbar mb-3">
		<div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
			<div class="fw-bolder">Trend</div>
			<div class="d-flex align-items-center gap-2">
				<div id="erHourlyNav" class="d-none align-items-center gap-2">
					<button type="button" class="btn btn-sm btn-outline-secondary" id="erPrevDay">Prev</button>
					<input type="date" class="form-control form-control-sm" id="erTrendDay" style="width:auto;" />
					<button type="button" class="btn btn-sm btn-outline-secondary" id="erNextDay">Next</button>
				</div>
				<button type="button" class="btn btn-sm btn-outline-primary" data-er-trend="hourly">Daily (Hourly)</button>
				<button type="button" class="btn btn-sm btn-outline-primary d-none" id="erTrendCustom" data-er-trend="custom">Custom</button>
				<button type="button" class="btn btn-sm btn-outline-primary" data-er-trend="days" data-er-days="7">7 days</button>
				<button type="button" class="btn btn-sm btn-outline-primary" data-er-trend="days" data-er-days="30">30 days</button>
			</div>
		</div>
		<div class="mt-2" style="height:260px;">
			<canvas id="erTrend"></canvas>
		</div>
	</div>

	<div class="table-responsive">
		<table class="table table-hover er-table">
			<thead>
				<tr>
					<th class="d-lg-none">Details</th>
					<th class="d-lg-none">Status / Source</th>
					<th class="d-lg-none"></th>

					<th class="d-none d-lg-table-cell">#</th>
					<th class="d-none d-lg-table-cell">Severity</th>
					<th class="d-none d-lg-table-cell">Title</th>
					<th class="d-none d-lg-table-cell">User</th>
					<th class="d-none d-lg-table-cell">Source</th>
					<th class="d-none d-lg-table-cell">Status</th>
					<th class="d-none d-lg-table-cell">Date</th>
					<th class="d-none d-lg-table-cell"></th>
				</tr>
			</thead>
			<tbody id="erTbody"></tbody>
		</table>
	</div>

	<div class="er-pagination mt-2">
		<div class="text-muted" style="font-size:0.85rem;" id="erPageMeta">—</div>
		<div class="er-pages" id="erPages"></div>
	</div>

	<form id="erCsrfForm" method="post" style="display:none;">
		<?= $csrf ?>
	</form>
</div>

<script>
(function(){
	const adminRoute = <?= json_encode((string)$admin_route) ?>;
	const pluginRoute = <?= json_encode((string)$plugin_route) ?>;
	const baseUrl = <?= json_encode((string)(ROOT . '/' . $admin_route . '/' . $plugin_route)) ?>;

	const elFind = document.getElementById('erFind');
	const elSeverity = document.getElementById('erSeverity');
	const elStatus = document.getElementById('erStatus');
	const elDateFrom = document.getElementById('erDateFrom');
	const elDateTo = document.getElementById('erDateTo');
	const elLimit = document.getElementById('erLimit');
	const elApply = document.getElementById('erApply');
	const elReset = document.getElementById('erReset');

	const elTbody = document.getElementById('erTbody');
	const elTotalText = document.getElementById('erTotalText');
	const elPageMeta = document.getElementById('erPageMeta');
	const elPages = document.getElementById('erPages');

	const kpiOpen = document.getElementById('kpiOpen');
	const kpiResolved = document.getElementById('kpiResolved');
	const kpiToday = document.getElementById('kpiToday');
	const kpiWeek = document.getElementById('kpiWeek');
	const kpiFatalOpen = document.getElementById('kpiFatalOpen');
	const kpiWindowLabel = document.getElementById('kpiWindowLabel');

	const csrfInput = document.querySelector('#erCsrfForm input[name=csrf]');
	const csrfToken = csrfInput ? csrfInput.value : '';

	let currentPage = <?= (int)$initialPage ?>;
	let currentDays = 7;
	let currentTrendMode = 'hourly';
	let currentTrendDay = '';
	let listAbort = null;
	let trendAbort = null;
	let trendChart = null;
	let trendButtons = [];
	let trendCustomActive = false;
	const elHourlyNav = document.getElementById('erHourlyNav');
	const elTrendDay = document.getElementById('erTrendDay');
	const elPrevDay = document.getElementById('erPrevDay');
	const elNextDay = document.getElementById('erNextDay');
	const elTrendCustom = document.getElementById('erTrendCustom');

	function esc(s){
		return String(s ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
	}

	function badgeSeverity(sev){
		sev = String(sev || '').toUpperCase();
		let cls = 'bg-secondary';
		if(sev === 'FATAL') cls = 'bg-danger';
		else if(sev === 'ERROR') cls = 'bg-warning text-dark';
		else if(sev === 'WARN') cls = 'bg-info text-dark';
		return `<span class="badge ${cls}">${esc(sev || 'ERROR')}</span>`;
	}

	function badgeStatus(st){
		st = String(st || 'OPEN').toUpperCase();
		const cls = st === 'RESOLVED' ? 'bg-success' : 'bg-primary';
		return `<span class="badge ${cls}">${esc(st)}</span>`;
	}

	function buildQuery(page){
		const q = new URLSearchParams();
		q.set('page', String(page));
		q.set('limit', String(parseInt(elLimit.value || '10', 10)));
		if(elFind.value.trim() !== '') q.set('find', elFind.value.trim());
		if(elSeverity.value.trim() !== '') q.set('severity', elSeverity.value.trim());
		if(elStatus.value.trim() !== '') q.set('status', elStatus.value.trim());
		if(elDateFrom.value.trim() !== '') q.set('date_from', elDateFrom.value.trim());
		if(elDateTo.value.trim() !== '') q.set('date_to', elDateTo.value.trim());
		return q;
	}

	function refreshTrendButtonStates(){
		if(!trendButtons || trendButtons.length === 0) return;
		for(const b of trendButtons){
			b.classList.remove('active');
		}
		if(currentTrendMode === 'hourly'){
			const btn = document.querySelector('[data-er-trend="hourly"]');
			if(btn) btn.classList.add('active');
			if(elHourlyNav) elHourlyNav.classList.remove('d-none');
			return;
		}
		if(elHourlyNav) elHourlyNav.classList.add('d-none');
		if(trendCustomActive){
			if(elTrendCustom) elTrendCustom.classList.add('active');
			return;
		}
		const btn = document.querySelector('[data-er-trend="days"][data-er-days="' + String(currentDays) + '"]');
		if(btn) btn.classList.add('active');
	}

	function pad2(n){
		n = parseInt(n, 10);
		return (n < 10 ? '0' : '') + String(n);
	}

	function toYmd(d){
		return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
	}

	function parseYmd(s){
		const m = String(s || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
		if(!m) return null;
		const dt = new Date(parseInt(m[1],10), parseInt(m[2],10) - 1, parseInt(m[3],10));
		if(isNaN(dt.getTime())) return null;
		return dt;
	}

	function setTrendDay(ymd){
		currentTrendDay = String(ymd || '').trim();
		if(elTrendDay){
			elTrendDay.value = currentTrendDay;
		}
	}

	function initTrendDay(){
		let d = null;
		if(elDateFrom && String(elDateFrom.value || '').trim() !== ''){
			d = parseYmd(elDateFrom.value);
		}
		if(!d) d = new Date();
		setTrendDay(toYmd(d));
	}

	function updateTrendRangeUi(){
		trendCustomActive = false;
		const from = String(elDateFrom && elDateFrom.value || '').trim();
		const to = String(elDateTo && elDateTo.value || '').trim();
		const f = parseYmd(from);
		const t = parseYmd(to);
		const btn7 = document.querySelector('[data-er-trend="days"][data-er-days="7"]');
		const btn30 = document.querySelector('[data-er-trend="days"][data-er-days="30"]');
		if(f && t){
			let start = f;
			let end = t;
			if(start.getTime() > end.getTime()){
				const tmp = start; start = end; end = tmp;
			}
			const rangeDays = Math.floor((end.getTime() - start.getTime()) / 86400000) + 1;
			trendCustomActive = true;
			if(currentTrendMode === 'hourly'){
				currentTrendMode = 'days';
			}
			if(elTrendCustom){
				elTrendCustom.classList.remove('d-none');
				elTrendCustom.textContent = 'Custom (' + String(rangeDays) + ' day' + (rangeDays === 1 ? '' : 's') + ')';
			}
			if(btn7) btn7.classList.add('d-none');
			if(btn30) btn30.classList.add('d-none');
		}else{
			if(elTrendCustom) elTrendCustom.classList.add('d-none');
			if(btn7) btn7.classList.remove('d-none');
			if(btn30) btn30.classList.remove('d-none');
		}
		refreshTrendButtonStates();
	}

	function updateUrl(page){
		const q = buildQuery(page);
		const newUrl = baseUrl + '?' + q.toString();
		window.history.replaceState({}, '', newUrl);
	}

	function renderPagination(page, totalPages){
		elPages.innerHTML = '';
		const mk = (label, p, disabled, active) => {
			const b = document.createElement('button');
			b.type = 'button';
			b.className = 'er-pagebtn' + (active ? ' active' : '');
			b.textContent = label;
			b.disabled = !!disabled;
			b.addEventListener('click', () => {
				currentPage = p;
				fetchList();
			});
			return b;
		};

		elPages.appendChild(mk('First', 1, page === 1, false));
		elPages.appendChild(mk('Prev', Math.max(1, page - 1), page === 1, false));

		const win = 2;
		let start = Math.max(1, page - win);
		let end = Math.min(totalPages, page + win);
		if(end - start < win * 2){
			start = Math.max(1, end - win * 2);
			end = Math.min(totalPages, start + win * 2);
		}
		for(let p = start; p <= end; p++){
			elPages.appendChild(mk(String(p), p, false, p === page));
		}

		elPages.appendChild(mk('Next', Math.min(totalPages, page + 1), page === totalPages, false));
		elPages.appendChild(mk('Last', totalPages, page === totalPages, false));
	}

	async function fetchList(){
		if(listAbort) listAbort.abort();
		listAbort = new AbortController();

		elTotalText.textContent = 'Loading…';
		elTbody.innerHTML = `<tr><td colspan="8"><div class="er-empty text-center"><div class="fw-bolder">Loading…</div><div class="text-muted" style="font-size:0.9rem;">Fetching error reports</div></div></td></tr>`;

		const q = buildQuery(currentPage);
		updateUrl(currentPage);
		const url = baseUrl + '/ajax_list?' + q.toString();

		try{
			const res = await fetch(url, {signal: listAbort.signal});
			const json = await res.json();
			if(!json || !json.success){
				throw new Error(json?.error || 'Failed');
			}
			const d = json.data;
			const rows = Array.isArray(d.rows) ? d.rows : [];
			const total = parseInt(d.total || 0, 10);
			const page = parseInt(d.page || 1, 10);
			const limit = parseInt(d.limit || 30, 10);
			const totalPages = parseInt(d.total_pages || 1, 10);
			const canManage = !!d.can_manage;

			currentPage = page;
			elTotalText.textContent = `Showing ${total} result(s)`;
			elPageMeta.textContent = `Page ${page} of ${totalPages} • ${limit} per page`;

			kpiOpen.textContent = d.kpi?.open ?? '—';
			kpiResolved.textContent = d.kpi?.resolved ?? '—';
			kpiToday.textContent = d.kpi?.today ?? '—';
			kpiWeek.textContent = d.kpi?.week ?? '—';
			kpiFatalOpen.textContent = d.kpi?.fatal_open ?? '—';
			if(kpiWindowLabel){
				kpiWindowLabel.textContent = d.kpi?.window_label ?? 'Last 7 days';
			}

			if(rows.length === 0){
				elTbody.innerHTML = `<tr><td colspan="8"><div class="er-empty text-center"><div class="fw-bolder">No error reports found</div><div class="text-muted" style="font-size:0.9rem;">Try clearing filters or broaden your date range.</div></div></td></tr>`;
			}else{
				let html = '';
				for(let i = 0; i < rows.length; i++){
					const r = rows[i];
					const index = (page - 1) * limit + (i + 1);
					const title = esc(r.title || '');
					const msg = esc(r.message || '');
					const user = esc(r.user_name || '');
					const source = esc(r.source || '');
					const sev = badgeSeverity(r.severity || '');
					const st = badgeStatus(r.status || 'OPEN');
					const date = esc(r.date_created || '');
					const viewUrl = `${baseUrl}/view/${encodeURIComponent(r.id)}`;
					let actions = `<a class="btn btn-sm btn-outline-primary" href="${viewUrl}">View</a>`;
					if(canManage){
						const s = String(r.status || 'OPEN').toUpperCase();
						if(s === 'RESOLVED'){
							actions += ` <button type="button" class="btn btn-sm btn-outline-warning" data-er-action="reopen" data-er-id="${esc(r.id)}">Reopen</button>`;
						}else{
							actions += ` <button type="button" class="btn btn-sm btn-outline-success" data-er-action="resolve" data-er-id="${esc(r.id)}">Resolve</button>`;
						}
						actions += ` <button type="button" class="btn btn-sm btn-outline-danger" data-er-action="delete" data-er-id="${esc(r.id)}">Delete</button>`;
					}

					html += `
					<tr>
						<td class="d-lg-none">
							<div class="d-flex align-items-center gap-2 flex-wrap">
								<div class="fw-bolder">${title}</div>
								${sev}
							</div>
							<div class="er-msg" style="font-size:0.85rem;">${msg}</div>
							<div class="text-muted" style="font-size:0.8rem;">${user}${user && date ? ' • ' : ''}${date}</div>
						</td>
						<td class="d-lg-none">
							<div class="d-flex align-items-center gap-2 flex-wrap">${st}<span class="text-muted" style="font-size:0.85rem;">${source}</span></div>
						</td>
						<td class="d-lg-none" style="white-space:nowrap;">${actions}</td>

						<td class="d-none d-lg-table-cell">${index}</td>
						<td class="d-none d-lg-table-cell">${sev}</td>
						<td class="d-none d-lg-table-cell">
							<div class="fw-bolder">${title}</div>
							<div class="er-msg" style="font-size:0.85rem;max-width:540px;">${msg}</div>
						</td>
						<td class="d-none d-lg-table-cell">${user}</td>
						<td class="d-none d-lg-table-cell">${source}</td>
						<td class="d-none d-lg-table-cell">${st}</td>
						<td class="d-none d-lg-table-cell">${date}</td>
						<td class="d-none d-lg-table-cell" style="white-space:nowrap;">${actions}</td>
					</tr>`;
				}
				elTbody.innerHTML = html;
			}

			renderPagination(page, totalPages);
		}catch(err){
			if(err && err.name === 'AbortError') return;
			elTotalText.textContent = 'Failed to load';
			elTbody.innerHTML = `<tr><td colspan="8"><div class="er-empty text-center"><div class="fw-bolder">Failed to load</div><div class="text-muted" style="font-size:0.9rem;">${esc(err?.message || 'Request failed')}</div></div></td></tr>`;
		}
	}

	async function postAction(id, action){
		if(!csrfToken){
			alert('Missing CSRF token. Please refresh this page.');
			return;
		}
		const fd = new FormData();
		fd.append('csrf', csrfToken);
		fd.append('id', String(id));
		fd.append('action', String(action));
		try{
			const res = await fetch(baseUrl + '/ajax_action', {method:'POST', body: fd});
			const json = await res.json();
			if(!json || !json.success){
				throw new Error(json?.error || 'Action failed');
			}
			await fetchList();
			await fetchTrend();
		}catch(err){
			alert(err?.message || 'Action failed');
		}
	}

	function ensureChartJs(){
		return typeof window.Chart !== 'undefined';
	}

	function fetchTrendWithRetry(attempt){
		const a = typeof attempt === 'number' ? attempt : 0;
		if(ensureChartJs()){
			fetchTrend();
			return;
		}
		if(a >= 20){
			return;
		}
		setTimeout(() => fetchTrendWithRetry(a + 1), 250);
	}

	async function fetchTrend(){
		if(!ensureChartJs()){
			return;
		}
		if(trendAbort) trendAbort.abort();
		trendAbort = new AbortController();
		const q = new URLSearchParams();
		q.set('mode', String(currentTrendMode));
		if(currentTrendMode === 'days'){
			q.set('days', String(currentDays));
		}else{
			if(currentTrendDay) q.set('day', String(currentTrendDay));
		}
		if(elFind.value.trim() !== '') q.set('find', elFind.value.trim());
		if(elSeverity.value.trim() !== '') q.set('severity', elSeverity.value.trim());
		if(elStatus.value.trim() !== '') q.set('status', elStatus.value.trim());
		if(elDateFrom.value.trim() !== '') q.set('date_from', elDateFrom.value.trim());
		if(elDateTo.value.trim() !== '') q.set('date_to', elDateTo.value.trim());
		const url = baseUrl + '/ajax_trend?' + q.toString();
		try{
			const res = await fetch(url, {signal: trendAbort.signal});
			const json = await res.json();
			if(!json || !json.success) throw new Error(json?.error || 'Failed');
			const labels = json.data?.labels || [];
			const counts = json.data?.counts || [];
			refreshTrendButtonStates();

			const ctx = document.getElementById('erTrend');
			if(!ctx) return;
			if(trendChart){
				trendChart.data.labels = labels;
				trendChart.data.datasets[0].data = counts;
				trendChart.update();
				return;
			}
			trendChart = new Chart(ctx, {
				type: 'line',
				data: {
					labels,
					datasets: [{
						label: 'Reports',
						data: counts,
						borderColor: '#0d6efd',
						backgroundColor: 'rgba(13,110,253,0.12)',
						fill: true,
						tension: 0.25,
						pointRadius: 2,
					}]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: {legend: {display: false}},
					scales: {y: {beginAtZero: true, ticks: {precision: 0}}}
				}
			});

			try{
				trendChart.options.onClick = function(evt, elements){
					if(!elements || !elements.length) return;
					if(currentTrendMode !== 'days') return;
					const idx = elements[0].index;
					const label = String(trendChart.data.labels[idx] || '').trim();
					if(!/^\d{4}-\d{2}-\d{2}$/.test(label)) return;
					currentTrendMode = 'hourly';
					setTrendDay(label);
					fetchTrend();
				};
			}catch(e){
			}
		}catch(err){
			if(err && err.name === 'AbortError') return;
		}
	}

	function debounced(fn, ms){
		let t = null;
		return function(){
			clearTimeout(t);
			t = setTimeout(() => fn.apply(null, arguments), ms);
		};
	}

	const liveFetch = debounced(() => { currentPage = 1; fetchList(); fetchTrend(); }, 250);

	elApply.addEventListener('click', () => { currentPage = 1; fetchList(); fetchTrend(); });
	elReset.addEventListener('click', () => {
		elFind.value = '';
		elSeverity.value = '';
		elStatus.value = '';
		elDateFrom.value = '';
		elDateTo.value = '';
		currentPage = 1;
		updateTrendRangeUi();
		fetchList();
		fetchTrend();
	});
	elLimit.addEventListener('change', () => { currentPage = 1; fetchList(); });

	elFind.addEventListener('input', liveFetch);
	elSeverity.addEventListener('change', liveFetch);
	elStatus.addEventListener('change', liveFetch);
	elDateFrom.addEventListener('change', liveFetch);
	elDateTo.addEventListener('change', liveFetch);
	elDateFrom.addEventListener('change', updateTrendRangeUi);
	elDateTo.addEventListener('change', updateTrendRangeUi);

	document.addEventListener('click', (e) => {
		const btn = e.target && e.target.closest ? e.target.closest('[data-er-action]') : null;
		if(btn){
			const id = btn.getAttribute('data-er-id');
			const action = btn.getAttribute('data-er-action');
			if(action === 'delete'){
				const fallback = () => { if(confirm('Delete this report?')) postAction(id, action); };
				try{
					if(typeof Swal === 'undefined' || !Swal || !Swal.fire){
						fallback();
						return;
					}
					const theme = (() => {
						try{
							let t = (document.documentElement && document.documentElement.getAttribute('data-bs-theme')) || '';
							if(!t && document.body){ t = document.body.getAttribute('data-bs-theme') || ''; }
							t = String(t || '').toLowerCase();
							let isDark = false;
							if(t === 'dark'){
								isDark = true;
							}else if(t === 'light'){
								isDark = false;
							}else if(t === 'auto' || t === ''){
								if(window.matchMedia){ isDark = window.matchMedia('(prefers-color-scheme: dark)').matches; }
							}
							return {
								popupClass: isDark ? 'bg-dark text-light' : 'bg-body text-body',
								bg: isDark ? '#0b1220' : '#ffffff',
								color: isDark ? '#f8fafc' : '#111827',
							};
						}catch(e){
							return { popupClass: 'bg-body text-body', bg: '#ffffff', color: '#111827' };
						}
					})();
					Swal.fire({
						icon: 'warning',
						title: 'Delete report?',
						html: 'This action cannot be undone.',
						showCancelButton: true,
						confirmButtonText: 'Delete',
						cancelButtonText: 'Cancel',
						buttonsStyling: false,
						reverseButtons: true,
						customClass: {
							confirmButton: 'btn btn-danger',
							cancelButton: 'btn btn-outline-secondary ms-2',
							popup: theme.popupClass,
						},
						background: theme.bg,
						color: theme.color,
					}).then((result) => {
						if(result && result.isConfirmed){
							postAction(id, action);
						}
					});
					return;
				}catch(e){
					fallback();
					return;
				}
			}
			postAction(id, action);
			return;
		}

		const theBtn = e.target && e.target.closest ? e.target.closest('[data-er-trend]') : null;
		if(theBtn){
			const mode = String(theBtn.getAttribute('data-er-trend') || 'hourly');
			if(mode === 'hourly'){
				currentTrendMode = 'hourly';
				if(!currentTrendDay) initTrendDay();
				fetchTrend();
				return;
			}
			if(mode === 'custom'){
				currentTrendMode = 'days';
				fetchTrend();
				return;
			}
			const d = parseInt(theBtn.getAttribute('data-er-days') || '7', 10);
			currentTrendMode = 'days';
			currentDays = (d === 30) ? 30 : 7;
			fetchTrend();
			return;
		}
	});

	if(elTrendDay){
		elTrendDay.addEventListener('change', () => {
			const v = String(elTrendDay.value || '').trim();
			if(v !== ''){
				currentTrendMode = 'hourly';
				setTrendDay(v);
				fetchTrend();
			}
		});
	}
	if(elPrevDay){
		elPrevDay.addEventListener('click', () => {
			const dt = parseYmd(currentTrendDay) || new Date();
			dt.setDate(dt.getDate() - 1);
			currentTrendMode = 'hourly';
			setTrendDay(toYmd(dt));
			fetchTrend();
		});
	}
	if(elNextDay){
		elNextDay.addEventListener('click', () => {
			const dt = parseYmd(currentTrendDay) || new Date();
			dt.setDate(dt.getDate() + 1);
			currentTrendMode = 'hourly';
			setTrendDay(toYmd(dt));
			fetchTrend();
		});
	}

	trendButtons = Array.from(document.querySelectorAll('[data-er-trend]'));
	initTrendDay();
	updateTrendRangeUi();
	refreshTrendButtonStates();
	fetchList();
	fetchTrendWithRetry(0);
})();
</script>
