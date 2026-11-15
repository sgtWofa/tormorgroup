<?php
/**
 * @var string $admin_route
 * @var string $plugin_route
 * @var int $user_id
 * @var array $rows
 * @var array $onlineUserRows
 * @var array $onlineUserIds
 */
?>
<?php if(user_can('view_tickets')):?>
	<?php
		$metrics = $metrics ?? [
			'total_count' => 0,
			'unseen_total' => 0,
			'status_counts' => [],
		];
		$isAjax = $isAjax ?? false;
		$perPage = $perPage ?? 10;
		$statusVal = strtolower(trim((string)old_value('status','all','get')));
		$onlineCount = count($onlineUserRows ?? []);
	?>

	<?php if($isAjax): ?>
		<div id="tickets-ajax-root">
	<?php endif; ?>

<style>
    .tickets-page { --ticket-accent: #0d6efd; }
    .tickets-page-heading {
        display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between;
        gap:1rem; margin:0 0 1rem; padding:.25rem .15rem .9rem;
        border-bottom:1px solid var(--bs-border-color);
    }
    .tickets-page-heading h1 {
        margin:0; font-size:clamp(1.25rem,2.5vw,1.7rem); font-weight:750; letter-spacing:-.035em;
    }
    .tickets-page-heading p { margin:.2rem 0 0; color:var(--bs-secondary-color); }
    .tickets-page .tickets-metric {
        height:100%; border:1px solid var(--bs-border-color)!important;
        border-radius:.9rem; background:var(--bs-body-bg); box-shadow:0 .4rem 1.2rem rgba(20,35,60,.055);
        transition:transform .18s ease,box-shadow .18s ease;
    }
    .tickets-page .tickets-metric:hover { transform:translateY(-2px); box-shadow:0 .7rem 1.5rem rgba(20,35,60,.1); }
    .tickets-page .tickets-metric .card-body { padding:1rem 1.1rem!important; }
    .tickets-page .tickets-metric .fs-5 { margin-top:.2rem; font-size:1.45rem!important; letter-spacing:-.03em; }
    .tickets-page .reversal-toolbar {
        padding:1rem; border:1px solid var(--bs-border-color); border-radius:.9rem;
        background:var(--bs-tertiary-bg); box-shadow:0 .3rem 1rem rgba(20,35,60,.04);
    }
    .tickets-page #tickets-search-form { min-width:min(100%,320px); }
    .tickets-page .form-control,.tickets-page .form-select,.tickets-page .input-group-text {
        border-color:var(--bs-border-color); background-color:var(--bs-body-bg); color:var(--bs-body-color);
    }
    .tickets-page .form-control:focus,.tickets-page .form-select:focus {
        border-color:rgba(13,110,253,.65); box-shadow:0 0 0 .2rem rgba(13,110,253,.13);
    }
    .tickets-page .tickets-table-shell {
        overflow:hidden; border:1px solid var(--bs-border-color); border-radius:.9rem;
        background:var(--bs-body-bg); box-shadow:0 .45rem 1.4rem rgba(20,35,60,.055);
    }
    .tickets-page #tickets-table-container { margin:0; }
    .tickets-page table { margin:0; }
    .tickets-page thead th {
        padding:.8rem .9rem; border:0; background:var(--bs-tertiary-bg)!important;
        color:var(--bs-secondary-color)!important; font-size:.72rem; font-weight:750;
        letter-spacing:.07em; text-transform:uppercase; white-space:nowrap;
    }
    .tickets-page tbody td { padding:.9rem; border-color:var(--bs-border-color); vertical-align:middle; }
    .tickets-page tbody tr { transition:background-color .15s ease; }
    .tickets-page tbody tr:hover { background:rgba(13,110,253,.045); }
    .tickets-page .ticket-id-link,.tickets-page .ticket-subject-link { color:var(--bs-body-color); font-weight:650; }
    .tickets-page .ticket-id-link { color:var(--ticket-accent); }
    .tickets-page .ticket-subject-link:hover,.tickets-page .ticket-id-link:hover { color:var(--bs-primary); }
    .tickets-page .description {
        width:min(100%,520px); max-width:520px; margin-top:.55rem; padding:.65rem .8rem;
        border:1px solid rgba(13,110,253,.12); border-radius:.75rem;
        background:rgba(13,110,253,.055); color:var(--bs-secondary-color);
        font-size:.78rem; line-height:1.5; white-space:normal; overflow-wrap:anywhere;
    }
    .tickets-page .ticket-parties { display:grid; gap:.4rem; min-width:190px; }
    .tickets-page .ticket-party { display:flex; align-items:center; gap:.4rem; font-size:.8rem; }
    .tickets-page .ticket-party img { width:1.45rem; height:1.45rem; object-fit:cover; border-radius:50%; }
    .tickets-page .ticket-party-label { color:var(--bs-secondary-color); }
    .tickets-page .tickets-pager-wrap { padding:.9rem; border-top:1px solid var(--bs-border-color); }
    .tickets-page .pagination { gap:.25rem; }
    .tickets-page .page-link { border-radius:.45rem!important; }
    .tickets-page .ticket-mobile-details { white-space:normal; }
    @media (max-width: 900px) {
        .tickets-page .large-screen { display:none; }
        .tickets-page .reversal-toolbar { align-items:stretch!important; }
        .tickets-page .reversal-toolbar > .d-flex:first-child { width:100%; }
        .tickets-page #tickets-search-form { flex:1 1 100%; }
        .tickets-page .tickets-table-shell { border-radius:.7rem; }
        .tickets-page tbody td { padding:.7rem .55rem; }
    }
</style>

<div class="tickets-page">
	<div class="tickets-page-heading">
		<div>
			<h1><i class="fa-solid fa-ticket text-primary me-2" aria-hidden="true"></i>Tickets</h1>
			<p>Track requests, follow up with colleagues, and keep work moving.</p>
		</div>
		<?php if(user_can('add_tickets')):?>
		<a class="btn btn-primary rounded-pill px-3 shadow-sm" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/add">
			<i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Create ticket
		</a>
		<?php endif?>
	</div>
	<div class="row g-2 mb-3 ticket-metrics">
		<div class="col-6 col-lg-4">
			<div class="card shadow-sm border-0 tickets-metric">
				<div class="card-body py-2">
					<div class="d-flex align-items-center justify-content-between">
						<div class="text-muted small">Total tickets</div>
						<i class="fa-solid fa-ticket text-muted"></i>
					</div>
					<div class="fs-5 fw-bold"><?= (int)($metrics['total_count'] ?? 0) ?></div>
				</div>
			</div>
		</div>
		<div class="col-6 col-lg-4">
			<div class="card shadow-sm border-0 tickets-metric">
				<div class="card-body py-2">
					<div class="d-flex align-items-center justify-content-between">
						<div class="text-muted small">Users online</div>
						<i class="fa-solid fa-circle text-success"></i>
					</div>
					<div class="fs-5 fw-bold"><?= (int)$onlineCount ?></div>
				</div>
			</div>
		</div>
		<div class="col-6 col-lg-4">
			<div class="card shadow-sm border-0 tickets-metric">
				<div class="card-body py-2">
					<div class="d-flex align-items-center justify-content-between">
						<div class="text-muted small">Open</div>
						<i class="fa-regular fa-folder-open text-muted"></i>
					</div>
					<div class="fs-5 fw-bold"><?= (int)($metrics['status_counts']['open'] ?? 0) ?></div>
				</div>
			</div>
		</div>
		<div class="col-6 col-lg-4">
			<div class="card shadow-sm border-0 tickets-metric">
				<div class="card-body py-2">
					<div class="d-flex align-items-center justify-content-between">
						<div class="text-muted small">In progress</div>
						<i class="fa-solid fa-spinner text-muted"></i>
					</div>
					<div class="fs-5 fw-bold"><?= (int)($metrics['status_counts']['in progress'] ?? 0) ?></div>
				</div>
			</div>
		</div>
		<div class="col-6 col-lg-4">
			<div class="card shadow-sm border-0 tickets-metric">
				<div class="card-body py-2">
					<div class="d-flex align-items-center justify-content-between">
						<div class="text-muted small">Unseen responses</div>
						<i class="fa-regular fa-envelope text-muted"></i>
					</div>
					<div class="fs-5 fw-bold"><?= (int)($metrics['unseen_total'] ?? 0) ?></div>
				</div>
			</div>
		</div>
	</div>

	<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3 reversal-toolbar">
		<div class="d-flex flex-wrap gap-2 align-items-center">
			<form class="input-group" id="tickets-search-form">
				<input placeholder="Search by: ID, status, subject, description, created-by, assigned, date" type="text" class="form-control" value="<?=old_value('find','','get')?>" name="find">
				<button class="btn btn-outline-secondary" type="submit">Search</button>
			</form>
			<div class="input-group" style="width: 190px;">
				<span class="input-group-text">Status</span>
				<select class="form-select" id="tickets-filter-status" name="status">
					<option value="all" <?=$statusVal==='all'?'selected':''?>>All</option>
					<option value="open" <?=$statusVal==='open'?'selected':''?>>Open</option>
					<option value="in progress" <?=$statusVal==='in progress'?'selected':''?>>In progress</option>
					<option value="closed" <?=$statusVal==='closed'?'selected':''?>>Closed</option>
				</select>
			</div>
			<div class="input-group" style="width: 170px;">
				<span class="input-group-text">From</span>
				<input type="date" class="form-control" id="tickets-filter-date-from" name="date_from" value="<?=old_value('date_from','','get')?>">
			</div>
			<div class="input-group" style="width: 170px;">
				<span class="input-group-text">To</span>
				<input type="date" class="form-control" id="tickets-filter-date-to" name="date_to" value="<?=old_value('date_to','','get')?>">
			</div>
			<div class="input-group" style="width: 170px;">
				<span class="input-group-text">Show</span>
				<select class="form-select" id="tickets-per-page" name="per_page">
					<option value="10" <?=$perPage==10?'selected':''?>>10</option>
					<option value="25" <?=$perPage==25?'selected':''?>>25</option>
					<option value="50" <?=$perPage==50?'selected':''?>>50</option>
					<option value="100" <?=$perPage==100?'selected':''?>>100</option>
				</select>
			</div>
			<button class="btn btn-outline-secondary" type="button" id="tickets-reset-filters">Reset</button>
		</div>
	</div>

<div class="tickets-table-shell">
<div class="table-responsive" id="tickets-table-container">
	<table class="table table-hover align-middle">
		<thead>
			<tr>
				<th class="d-lg-none">Details</th>
				<th class="d-lg-none">Status</th>
				<th class="d-lg-none text-end">Actions</th>

				<th class="d-none d-lg-table-cell">ID / Date</th>
				<th class="d-none d-lg-table-cell">Subject</th>
				<th class="d-none d-lg-table-cell">Parties involved</th>
				<th class="d-none d-lg-table-cell"></th>
			</tr>
		</thead>
			<tbody>
			<?php if(!empty($rows)): $id = 0; ?>
				<?php foreach($rows as $row): $id ++;?>
						<?php
							$ticketId = esc($row->id ?? '');
							$subject = esc($row->subject ?? '');
							$dateCreated = get_date_time($row->date_created ?? '');
							$assignedName = trim(($row->assigned_first_name ?? '') . ' ' . ($row->assigned_last_name ?? ''));
							$assignedName = $assignedName !== '' ? $assignedName : 'Unassigned';
						?>
						<tr>
							<td class="d-lg-none">
								<div class="fw-bold">
									<a class="ticket-id-link" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$row->id?>">
										<?=$ticketId?>
									</a>
								</div>
								<div class="small text-muted" style="max-width: 320px; white-space: normal;">
									<?=$subject?>
								</div>
								<div class="small text-muted">Sent by: <?=esc(trim(($row->user_first_name ?? '') . ' ' . ($row->user_last_name ?? '')))?> <?=tickets_online_indicator_html($row->created_by ?? 0, $onlineUserIds ?? [])?></div>
								<div class="small text-muted">Assigned: <?=esc($assignedName)?> <?=tickets_online_indicator_html($row->assigned ?? 0, $onlineUserIds ?? [])?></div>
								<div class="small text-muted"><?=$dateCreated?></div>
							</td>
							<td class="d-lg-none">
								<?php
									$status = strtolower(trim($row->status ?? ''));
									switch ($status) {
										case 'in progress':
											$badgeClass = 'badge bg-success';
											break;
										case 'open':
											$badgeClass = 'badge bg-primary';
											break;
										case 'closed':
											$badgeClass = 'badge bg-dark text-white';
											break;
										default:
											$badgeClass = 'badge bg-secondary';
											break;
									}
								?>
								<div>
									<span class="<?= $badgeClass ?>"><?= esc($row->status ?? 'Unknown') ?></span>
									<?php if (($row->unseen_responses ?? 0) > 0): ?>
										<span class="badge fw-bolder rounded-pill" data-notification-urgency="serious"><?=$row->unseen_responses ?? 0?></span>
									<?php endif ?>
								</div>
							</td>
							<td class="d-lg-none text-end">
								<div class="d-flex justify-content-end gap-2 flex-wrap">
									<?php if(user_can("view_tickets")):?>
										<a class="btn btn-primary btn-sm" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$row->id?>" title="View">
											<i class="fa-solid fa-eye"></i>
										</a>
									<?php endif;?>
									<?php if(user_can('edit_tickets') AND ($row->status !== 'Closed')):?>
										<a class="btn btn-warning btn-sm" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit/<?=$row->id?>" title="Update">
											<i class="fa-solid fa-pen-to-square"></i>
										</a>
									<?php endif;?>
								</div>
							</td>

							<td class="d-none d-lg-table-cell text-nowrap">
								<a class="ticket-id-link" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$row->id?>">
									<?=esc($row->id ?? '')?>
								</a>
								<div class="small text-muted"><?=get_date_time($row->date_created ?? '')?></div>
							</td>

							<td class="d-none d-lg-table-cell">
							    <?php
							        // Determine badge class based on transaction status
							        $status = strtolower(trim($row->status ?? ''));
							        switch ($status) {
							            case 'in progress':
							                $badgeClass = 'badge bg-success';
							                break;
							            case 'open':
							                $badgeClass = 'badge bg-primary';
							                break;
							            case 'closed':
							                $badgeClass = 'badge bg-dark text-white';
							                break;
							            default:
							                $badgeClass = 'badge bg-secondary';
							                break;
							        }
							    ?>
								<a class="ticket-subject-link" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$row->id?>">
									<?=esc($row->subject ?? '')?>
								</a>

							    <span class="<?= $badgeClass ?>"><?= esc($row->status ?? 'Unknown') ?></span>
								<?php if (($row->unseen_responses ?? 0) > 0): ?>
							 	 	 <span class="badge fw-bolder rounded-pill" data-notification-urgency="serious"><?=$row->unseen_responses ?? 0?></span>	
								<?php endif ?>
							    <div class="description text-muted"><?=esc(strip_tags($row->description ?? ''))?></div>
							</td>
							<td class="large-screen d-none d-lg-table-cell">
								<div class="ticket-parties">
								<div class="ticket-party">
									<span><img src="<?=get_image($row->user_image)?>" alt=""></span>
									<span class="ticket-party-label">Sent by:</span>
								 	<?=$row->user_first_name . ' ' . $row->user_last_name ?> <?=tickets_online_indicator_html($row->created_by ?? 0, $onlineUserIds ?? [])?>

								</div>
								<div class="ticket-party">
									<span><img src="<?=get_image($row->assigned_image)?>" alt=""></span>
									<span class="ticket-party-label">Assigned:</span>
								 	<?=$row->assigned_first_name ?? ''?> <?=$row->assigned_last_name ?? 'Unassigned' ?> <?=tickets_online_indicator_html($row->assigned ?? 0, $onlineUserIds ?? [])?>
								</div>
								</div>

							</td>

							<td class="d-none d-lg-table-cell text-end">
								<div class="d-flex justify-content-end gap-2 flex-wrap">
									<?php if(user_can("view_tickets")):?>
										<a class="btn btn-primary btn-sm" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$row->id?>" title="View">
											<i class="fa-solid fa-eye"></i>
										</a>
									<?php endif;?>
								</div>
							</td>
						</tr>
					<?php endforeach?>
			<?php else:?>
				<tr>
					<td colspan="4" class="text-center text-muted py-5">
						<i class="fa-regular fa-folder-open d-block fs-3 mb-2" aria-hidden="true"></i>
						No tickets match your filters.
					</td>
				</tr>
			<?php endif?>
			</tbody>
			</table>
</div>
<div class="tickets-pager-wrap d-flex justify-content-end">
	<div id="tickets-pager"
		data-page="<?=$pager->page_number?>"
		data-per-page="<?=$perPage?>"
		data-total="<?= (int)($metrics['total_count'] ?? 0) ?>"
		data-extras="2"
	></div>
</div>
</div>
</div>

	<?php if(!$isAjax): ?>
	<script>
		(function(){
			const buildUrl = (overrides) => {
				const u = new URL(window.location.href);
				u.searchParams.set('ajax', '1');
				if (overrides) {
					Object.keys(overrides).forEach((k) => {
						const v = overrides[k];
						if (v === null || v === undefined || v === '') {
							u.searchParams.delete(k);
						} else {
							u.searchParams.set(k, String(v));
						}
					});
				}
				return u;
			};

			const setHistory = (u) => {
				const h = new URL(u.toString());
				h.searchParams.delete('ajax');
				window.history.pushState({}, '', h.toString());
			};

			const renderPager = () => {
				const pager = document.getElementById('tickets-pager');
				if (!pager) return;
				const page = parseInt(pager.getAttribute('data-page') || '1', 10) || 1;
				const perPage = parseInt(pager.getAttribute('data-per-page') || '10', 10) || 10;
				const total = parseInt(pager.getAttribute('data-total') || '0', 10) || 0;
				const extras = parseInt(pager.getAttribute('data-extras') || '2', 10) || 2;
				const totalPages = Math.max(1, Math.ceil(total / Math.max(1, perPage)));
				const current = Math.min(Math.max(1, page), totalPages);

				let start = current - extras;
				let end = current + extras;
				if (start < 1) start = 1;
				if (end > totalPages) end = totalPages;

				const mkBtn = (label, targetPage, disabled, active) => {
					const li = document.createElement('li');
					li.className = 'page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '');
					const a = document.createElement('a');
					a.className = 'page-link';
					a.href = '#';
					a.textContent = label;
					if (!disabled) a.setAttribute('data-page', String(targetPage));
					li.appendChild(a);
					return li;
				};

				const nav = document.createElement('nav');
				nav.setAttribute('aria-label', 'Tickets pages');
				const ul = document.createElement('ul');
				ul.className = 'pagination mb-0';

				ul.appendChild(mkBtn('First', 1, current === 1));
				ul.appendChild(mkBtn('Prev', Math.max(1, current - 1), current === 1));
				for (let p = start; p <= end; p++) {
					ul.appendChild(mkBtn(String(p), p, false, p === current));
				}
				ul.appendChild(mkBtn('Next', Math.min(totalPages, current + 1), current === totalPages));
				ul.appendChild(mkBtn('Last', totalPages, current === totalPages));
				nav.appendChild(ul);
				pager.innerHTML = '';
				pager.appendChild(nav);
			};

			const fetchAndReplace = async (u) => {
				try {
					const res = await fetch(u.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
					const html = await res.text();
					const tmp = document.createElement('div');
					tmp.innerHTML = html;
					const root = tmp.querySelector('#tickets-ajax-root');
					if (root) {
						const currentToolbar = document.querySelector('.reversal-toolbar');
						const currentMetrics = document.querySelector('.ticket-metrics');
						const currentTable = document.getElementById('tickets-table-container');
						const currentPager = document.getElementById('tickets-pager');
						const newToolbar = root.querySelector('.reversal-toolbar');
						const newMetrics = root.querySelector('.ticket-metrics');
						const newTable = root.querySelector('#tickets-table-container');
						const newPager = root.querySelector('#tickets-pager');
						if (currentToolbar && newToolbar) currentToolbar.replaceWith(newToolbar);
						if (currentMetrics && newMetrics) currentMetrics.replaceWith(newMetrics);
						if (currentTable && newTable) currentTable.replaceWith(newTable);
						if (currentPager && newPager) currentPager.replaceWith(newPager);
						setHistory(u);
						renderPager();
					}
				} catch (e) {
					const h = new URL(u.toString());
					h.searchParams.delete('ajax');
					window.location.href = h.toString();
				}
			};

			document.addEventListener('click', function(e){
				const a = e.target.closest('#tickets-pager .page-link');
				if (!a) return;
				const page = a.getAttribute('data-page');
				if (!page) return;
				e.preventDefault();
				const u = buildUrl({ page: page });
				fetchAndReplace(u);
			});

			document.addEventListener('change', function(e){
				const sel = e.target.closest('#tickets-per-page');
				if (!sel) return;
				const u = buildUrl({ per_page: sel.value, page: 1 });
				fetchAndReplace(u);
			});

			document.addEventListener('change', function(e){
				const statusSel = e.target.closest('#tickets-filter-status');
				if (!statusSel) return;
				const u = buildUrl({ status: statusSel.value, page: 1 });
				fetchAndReplace(u);
			});

			document.addEventListener('change', function(e){
				const dateFrom = e.target.closest('#tickets-filter-date-from');
				const dateTo = e.target.closest('#tickets-filter-date-to');
				if (!dateFrom && !dateTo) return;
				const df = document.getElementById('tickets-filter-date-from');
				const dt = document.getElementById('tickets-filter-date-to');
				const u = buildUrl({ date_from: df ? df.value : null, date_to: dt ? dt.value : null, page: 1 });
				fetchAndReplace(u);
			});

			document.addEventListener('click', function(e){
				const btn = e.target.closest('#tickets-reset-filters');
				if (!btn) return;
				e.preventDefault();
				const u = buildUrl({ find: null, status: null, date_from: null, date_to: null, page: 1 });
				fetchAndReplace(u);
			});

			document.addEventListener('submit', function(e){
				const f = e.target.closest('#tickets-search-form');
				if (!f) return;
				e.preventDefault();
				const data = new FormData(f);
				const find = data.get('find');
				const per = document.getElementById('tickets-per-page');
				const statusSel = document.getElementById('tickets-filter-status');
				const df = document.getElementById('tickets-filter-date-from');
				const dt = document.getElementById('tickets-filter-date-to');
				const u = buildUrl({
					find: find,
					page: 1,
					per_page: per ? per.value : null,
					status: statusSel ? statusSel.value : null,
					date_from: df ? df.value : null,
					date_to: dt ? dt.value : null,
				});
				fetchAndReplace(u);
			});

			window.addEventListener('popstate', function(){
				const u = new URL(window.location.href);
				u.searchParams.set('ajax','1');
				fetchAndReplace(u);
			});

			renderPager();
		})();
	</script>
	<?=tickets_notification_script((int)($user_id ?? 0), $admin_route, $plugin_route)?>
	<?php endif; ?>

	<?php if($isAjax): ?>
		</div>
		<?php die; ?>
	<?php endif; ?>
<?php else:?>
	<div class="alert alert-danger text-center">
		Access denied. You dont have permission for this action
	</div>
<?php endif?>
