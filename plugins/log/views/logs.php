<style>
    .logs-shell{max-width:1100px;}
    .logs-title{letter-spacing:.16em;text-transform:uppercase;font-size:.9rem;}
    .logs-muted{font-size:12px;}
    .logs-table td{vertical-align:top;}
    .logs-desc{max-width:520px;white-space:normal;}
    .logs-meta{font-size:12px;color:var(--bs-secondary-color);}
    .logs-filters .form-label{font-size:12px;color:var(--bs-secondary-color);}
    .logs-pagination .btn{min-width:100px;}

    @media (max-width: 992px){
        .logs-table thead{display:none;}
        .logs-table tbody tr{
            display:grid;
            grid-template-columns: 1fr 1fr;
            gap:.35rem .75rem;
            padding:.75rem .75rem;
            border:1px solid rgba(0,0,0,0.08);
            border-radius:.75rem;
            margin-bottom:.65rem;
        }
        .logs-table tbody td{
            display:block;
            padding:0;
            border:0;
        }
        .logs-table tbody td::before{
            content: attr(data-label);
            display:block;
            font-size:11px;
            color: var(--bs-secondary-color);
            margin-bottom:.15rem;
            text-transform: uppercase;
            letter-spacing:.08em;
        }
        .logs-table tbody td.logs-cell-desc{grid-column: 1 / -1; margin-top:.25rem;}
        .logs-table tbody td.logs-cell-desc::before{content:"Description";}
        .logs-desc{max-width:none;}
    }
</style>


<?php
	$vars = get_value();
	$admin_route = $vars['admin_route'] ?? 'admin';
	$plugin_route = $vars['plugin_route'] ?? 'logs';
	$baseAjax = ROOT . '/' . $admin_route . '/' . $plugin_route . '/';
?>

<div class="container-fluid py-3">
	<div class="card border-0 shadow-sm mx-auto logs-shell">
		<div class="card-body">
			<div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
				<div>
					<div class="logs-title">Activities <span class="fw-semibold">Log</span></div>
					<div class="text-muted logs-muted">Search, filter, and review audit events.</div>
				</div>
				<div class="d-flex gap-2">
					<button type="button" class="btn btn-outline-secondary btn-sm" id="logsResetBtn">Reset</button>
					<button type="button" class="btn btn-outline-primary btn-sm" id="logsRefreshBtn">Refresh</button>
				</div>
			</div>

			<div class="row g-3 mt-1 logs-filters">
				<div class="col-12 col-sm-6 col-md-6 col-lg-4">
					<label class="form-label" for="logsQ">Search</label>
					<input type="text" class="form-control" id="logsQ" placeholder="Search description, user, table, action, IP..." autocomplete="off">
				</div>
				<div class="col-12 col-sm-6 col-md-6 col-lg-2">
					<label class="form-label" for="logsAction">Action</label>
					<select class="form-select" id="logsAction">
						<option value="">All</option>
					</select>
				</div>
				<div class="col-12 col-sm-6 col-md-6 col-lg-3">
					<label class="form-label" for="logsTable">Table</label>
					<select class="form-select" id="logsTable">
						<option value="">All</option>
					</select>
				</div>
				<div class="col-12 col-sm-6 col-md-6 col-lg-3">
					<label class="form-label" for="logsUser">User</label>
					<select class="form-select" id="logsUser">
						<option value="">All</option>
					</select>
				</div>
				<div class="col-12 col-sm-6 col-md-6 col-lg-3">
					<label class="form-label">Date range</label>
					<div class="d-flex gap-2">
						<input type="date" class="form-control" id="logsDateFrom">
						<input type="date" class="form-control" id="logsDateTo">
					</div>
				</div>
				<div class="col-12 col-sm-6 col-md-6 col-lg-2">
					<label class="form-label" for="logsLimit">Per page</label>
					<select class="form-select" id="logsLimit">
						<option value="10" selected>10</option>
						<option value="20">20</option>
						<option value="50">50</option>
						<option value="100">100</option>
					</select>
				</div>
				<div class="col-12 col-lg-10 d-flex align-items-end">
					<div class="text-muted logs-muted" id="logsSummary"></div>
				</div>
			</div>

			<hr class="my-3">

			<div class="table-responsive">
				<table class="table table-hover align-middle logs-table mb-0">
					<thead>
						<tr>
							<th style="width:170px;">Date</th>
							<th style="width:120px;">Action</th>
							<th style="width:160px;">Table</th>
							<th>User</th>
							<th>Description</th>
						</tr>
					</thead>
					<tbody id="logsTbody">
						<tr><td colspan="5" class="text-center text-muted py-4">Loading...</td></tr>
					</tbody>
				</table>
			</div>

			<div class="d-flex justify-content-between align-items-center mt-3 logs-pagination flex-wrap gap-2">
				<div class="text-muted logs-muted" id="logsPageLabel">Page 1 of 1</div>
				<div class="d-flex gap-2">
					<button type="button" class="btn btn-outline-secondary btn-sm" id="logsPrevBtn">Previous</button>
					<button type="button" class="btn btn-outline-secondary btn-sm" id="logsNextBtn">Next</button>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
	(function(){
		var baseAjax = "<?=esc($baseAjax)?>";
		var state = {
			page: 1,
			limit: 10,
			q: '',
			action: '',
			table: '',
			user_id: '',
			date_from: '',
			date_to: '',
			totalPages: 1,
			totalRows: 0,
			loading: false,
		};

		function escHtml(str){
			if(str === undefined || str === null) str = '';
			return String(str).replace(/[&<>"']/g, function(m){
				return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#039;'})[m];
			});
		}

		function formatDateHuman(dtStr){
			if(!dtStr) return '—';
			var d = new Date(dtStr);
			if(isNaN(d.getTime())){
				// MySQL often returns "YYYY-MM-DD HH:MM:SS"; try forcing ISO
				var forced = new Date(String(dtStr).replace(' ', 'T'));
				if(!isNaN(forced.getTime())) d = forced;
			}
			if(isNaN(d.getTime())) return String(dtStr);
			try{
				return new Intl.DateTimeFormat(undefined, {
					year: 'numeric',
					month: 'short',
					day: '2-digit',
					hour: '2-digit',
					minute: '2-digit'
				}).format(d);
			}catch(e){
				return d.toLocaleString();
			}
		}

		function parseUserAgent(ua){
			ua = String(ua || '');
			if(!ua) return {browser:null, os:null, device:null};
			var lower = ua.toLowerCase();

			var device = 'Desktop';
			if(/mobile|iphone|android.*mobile|windows phone/.test(lower)) device = 'Mobile';
			if(/ipad|tablet|android(?!.*mobile)/.test(lower)) device = 'Tablet';

			var os = null;
			if(/windows nt 10\.0/.test(lower)) os = 'Windows 10';
			else if(/windows nt 11\.0/.test(lower)) os = 'Windows 11';
			else if(/windows nt 6\.3/.test(lower)) os = 'Windows 8.1';
			else if(/windows nt 6\.2/.test(lower)) os = 'Windows 8';
			else if(/windows nt 6\.1/.test(lower)) os = 'Windows 7';
			else if(/mac os x/.test(lower) && !/iphone|ipad/.test(lower)) os = 'macOS';
			else if(/android/.test(lower)) os = 'Android';
			else if(/iphone/.test(lower)) os = 'iPhone iOS';
			else if(/ipad/.test(lower)) os = 'iPad iOS';
			else if(/linux/.test(lower)) os = 'Linux';

			var browser = null;
			if(/edg\//.test(lower)) browser = 'Edge';
			else if(/opr\//.test(lower) || /opera/.test(lower)) browser = 'Opera';
			else if(/chrome\//.test(lower) && !/edg\//.test(lower) && !/opr\//.test(lower)) browser = 'Chrome';
			else if(/safari\//.test(lower) && /version\//.test(lower) && !/chrome\//.test(lower)) browser = 'Safari';
			else if(/firefox\//.test(lower)) browser = 'Firefox';

			return {browser: browser, os: os, device: device};
		}

		function badgeForAction(a){
			a = String(a || '').toUpperCase();
			if(!a) return '<span class="badge bg-secondary">—</span>';
			if(a === 'CREATE') return '<span class="badge bg-success">CREATE</span>';
			if(a === 'UPDATE') return '<span class="badge bg-primary">UPDATE</span>';
			if(a === 'DELETE') return '<span class="badge bg-danger">DELETE</span>';
			return '<span class="badge bg-secondary">'+escHtml(a)+'</span>';
		}

		async function fetchJson(url){
			var res = await fetch(url, {credentials: 'same-origin'});
			return await res.json();
		}

		function setSummary(){
			var el = document.getElementById('logsSummary');
			if(!el) return;
			if(state.loading){
				el.textContent = 'Loading...';
				return;
			}
			var parts = [];
			parts.push(state.totalRows + ' result' + (state.totalRows === 1 ? '' : 's'));
			if(state.q) parts.push('Search: "' + state.q + '"');
			if(state.action) parts.push('Action: ' + state.action);
			if(state.table) parts.push('Table: ' + state.table);
			el.textContent = parts.join(' • ');
		}

		function setPageLabel(){
			var el = document.getElementById('logsPageLabel');
			if(!el) return;
			el.textContent = 'Page ' + state.page + ' of ' + state.totalPages;
		}

		function setLoadingTbody(){
			var tb = document.getElementById('logsTbody');
			if(!tb) return;
			tb.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Loading...</td></tr>';
		}

		function renderRows(rows){
			var tb = document.getElementById('logsTbody');
			if(!tb) return;
			rows = Array.isArray(rows) ? rows : [];
			if(!rows.length){
				tb.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No logs found.</td></tr>';
				return;
			}
			tb.innerHTML = rows.map(function(r){
				var dtHuman = formatDateHuman(r.date_created || '');
				var dt = escHtml(dtHuman || '—');
				var table = escHtml(r.table_name || '—');
				var user = escHtml(r.user_name || (r.user_id ? ('#' + r.user_id) : '—'));
				var desc = escHtml(r.description || '');
				var ip = (r && r.ip_address) ? String(r.ip_address) : '';
				var plat = (r && r.device_platform) ? String(r.device_platform) : '';
				var lang = (r && r.device_language) ? String(r.device_language) : '';
				var ua = (r && r.user_agent) ? String(r.user_agent) : '';
				var uaInfo = parseUserAgent(ua);
				var metaParts = [];
				if(ip) metaParts.push('IP: ' + ip);
				if(uaInfo && uaInfo.device) metaParts.push(uaInfo.device);
				if(uaInfo && uaInfo.os) metaParts.push(uaInfo.os);
				if(uaInfo && uaInfo.browser) metaParts.push(uaInfo.browser);
				if(plat && (!uaInfo || !uaInfo.os)) metaParts.push(plat);
				if(lang) metaParts.push(lang);
				var meta = metaParts.length ? ('<div class="logs-meta"' + (ua ? (' title="'+escHtml(ua)+'"') : '') + '>' + escHtml(metaParts.join(' • ')) + '</div>') : '';
				return '<tr>'+
					'<td data-label="Date"><div class="fw-semibold">'+dt+'</div><div class="text-muted logs-muted">#'+escHtml(r.id || '')+'</div></td>'+
					'<td data-label="Action">'+badgeForAction(r.action)+'</td>'+
					'<td data-label="Table"><span class="badge text-bg-light">'+table+'</span></td>'+
					'<td data-label="User">'+user+'</td>'+
					'<td class="logs-desc logs-cell-desc" data-label="Description">'+desc + meta +'</td>'+
				'</tr>';
			}).join('');
		}

		async function loadMeta(){
			var actionSel = document.getElementById('logsAction');
			var tableSel = document.getElementById('logsTable');
			var userSel = document.getElementById('logsUser');
			try{
				var data = await fetchJson(baseAjax + 'ajax_meta');
				if(!data || !data.success) return;
				var actions = Array.isArray(data.actions) ? data.actions : [];
				var tables = Array.isArray(data.tables) ? data.tables : [];
				var users = Array.isArray(data.users) ? data.users : [];

				if(actionSel){
					while(actionSel.options.length > 1){
						actionSel.remove(1);
					}
				}
				if(tableSel){
					while(tableSel.options.length > 1){
						tableSel.remove(1);
					}
				}
				if(userSel){
					while(userSel.options.length > 1){
						userSel.remove(1);
					}
				}
				if(actionSel){
					actions.forEach(function(a){
						if(!a) return;
						var opt = document.createElement('option');
						opt.value = a;
						opt.textContent = a;
						actionSel.appendChild(opt);
					});
				}
				if(tableSel){
					tables.forEach(function(t){
						if(!t) return;
						var opt2 = document.createElement('option');
						opt2.value = t;
						opt2.textContent = t;
						tableSel.appendChild(opt2);
					});
				}
				if(userSel){
					var seenUsers = {};
					users.forEach(function(u){
						if(!u) return;
						var uid = (u.user_id !== undefined && u.user_id !== null) ? String(u.user_id) : '';
						if(!uid) return;
						if(seenUsers[uid]) return;
						seenUsers[uid] = true;
						var opt3 = document.createElement('option');
						opt3.value = uid;
						opt3.textContent = u.user_name ? String(u.user_name) : ('#' + uid);
						userSel.appendChild(opt3);
					});
				}
			}catch(e){
				// ignore
			}
		}

		function buildQuery(){
			var params = new URLSearchParams();
			params.set('page', String(state.page));
			params.set('limit', String(state.limit));
			if(state.q) params.set('q', state.q);
			if(state.action) params.set('action', state.action);
			if(state.table) params.set('table', state.table);
			if(state.user_id) params.set('user_id', state.user_id);
			if(state.date_from) params.set('date_from', state.date_from);
			if(state.date_to) params.set('date_to', state.date_to);
			return params.toString();
		}

		async function loadList(){
			if(state.loading) return;
			state.loading = true;
			setLoadingTbody();
			setSummary();
			try{
				var url = baseAjax + 'ajax_list?' + buildQuery();
				var data = await fetchJson(url);
				if(!data || !data.success){
					renderRows([]);
					state.totalPages = 1;
					state.totalRows = 0;
					setPageLabel();
					state.loading = false;
					setSummary();
					return;
				}
				var pag = data.pagination || {};
				state.totalPages = Number(pag.total_pages || 1);
				state.totalRows = Number(pag.total_rows || 0);
				renderRows(data.rows || []);
				setPageLabel();
				state.loading = false;
				setSummary();
			}catch(e){
				renderRows([]);
				state.totalPages = 1;
				state.totalRows = 0;
				setPageLabel();
				state.loading = false;
				setSummary();
			}
		}

		function bindControls(){
			var q = document.getElementById('logsQ');
			var actionSel = document.getElementById('logsAction');
			var tableSel = document.getElementById('logsTable');
			var userSel = document.getElementById('logsUser');
			var df = document.getElementById('logsDateFrom');
			var dt = document.getElementById('logsDateTo');
			var limitSel = document.getElementById('logsLimit');
			var prev = document.getElementById('logsPrevBtn');
			var next = document.getElementById('logsNextBtn');
			var reset = document.getElementById('logsResetBtn');
			var refresh = document.getElementById('logsRefreshBtn');

			var t = null;
			function debounceLoad(){
				if(t) clearTimeout(t);
				t = setTimeout(function(){
					state.page = 1;
					loadList();
				}, 250);
			}

			if(q){
				q.addEventListener('input', function(){
					state.q = String(q.value || '').trim();
					debounceLoad();
				});
			}
			if(actionSel){
				actionSel.addEventListener('change', function(){
					state.action = String(actionSel.value || '').trim();
					state.page = 1;
					loadList();
				});
			}
			if(tableSel){
				tableSel.addEventListener('change', function(){
					state.table = String(tableSel.value || '').trim();
					state.page = 1;
					loadList();
				});
			}
			if(userSel){
				userSel.addEventListener('change', function(){
					state.user_id = String(userSel.value || '').trim();
					state.page = 1;
					loadList();
				});
			}
			if(df){
				df.addEventListener('change', function(){
					state.date_from = String(df.value || '').trim();
					state.page = 1;
					loadList();
				});
			}
			if(dt){
				dt.addEventListener('change', function(){
					state.date_to = String(dt.value || '').trim();
					state.page = 1;
					loadList();
				});
			}
			if(limitSel){
				limitSel.addEventListener('change', function(){
					state.limit = Number(limitSel.value || 20);
					state.page = 1;
					loadList();
				});
			}
			if(prev){
				prev.addEventListener('click', function(){
					if(state.page <= 1) return;
					state.page -= 1;
					loadList();
				});
			}
			if(next){
				next.addEventListener('click', function(){
					if(state.page >= state.totalPages) return;
					state.page += 1;
					loadList();
				});
			}
			if(reset){
				reset.addEventListener('click', function(){
					state.page = 1;
					state.limit = 10;
					state.q = '';
					state.action = '';
					state.table = '';
					state.user_id = '';
					state.date_from = '';
					state.date_to = '';
					if(q) q.value = '';
					if(actionSel) actionSel.value = '';
					if(tableSel) tableSel.value = '';
					if(userSel) userSel.value = '';
					if(df) df.value = '';
					if(dt) dt.value = '';
					if(limitSel) limitSel.value = '10';
					loadList();
				});
			}
			if(refresh){
				refresh.addEventListener('click', function(){
					loadList();
				});
			}
		}

		bindControls();
		loadMeta().then(loadList);
	})();
</script>
