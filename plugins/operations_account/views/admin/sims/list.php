<?php if(user_can('view_sim')):?>
	<?php
		//session data
		$session = get_session();
		//dd($session);
		if(!empty($session->id)){
			$username = $session->first_name . ' '. $session->last_name;
			$user_id = $session->id;
		}
		// flag to differentiate full-page render from ajax_sims fragment
		$is_ajax_sims = isset($is_ajax_sims) && $is_ajax_sims ? true : false;
	?>
	<link rel="stylesheet" type="text/css" href="<?=plugin_http_path('assets/css/style.css')?>">

<?php if(user_can('add_sim')):?>

<?php if(user_can('edit_sim') && !$is_ajax_sims):?>
<div id="bulkAssignPanel" class="alert alert-secondary d-flex flex-wrap align-items-center justify-content-between gap-3">
	<div>
		<strong class="text-dark">Bulk assigning</strong>
		<small class="text-muted d-block">Select unassigned sims to enable bulk assignment.</small>
		<span class="badge bg-warning text-dark ms-2" id="bulkSelectedCount">0 selected</span>
	</div>
	<div class="d-flex align-items-center gap-2 flex-wrap">
		<select class="form-select form-select-sm" id="bulkNetworkFilter" style="min-width: 160px;">
			<option value="">All networks</option>
			<?php if(!empty($networks)): foreach($networks as $network => $data):?>
			<option value="<?=esc(strtolower($network))?>"><?=strtoupper($network)?></option>
			<?php endforeach; endif;?>
		</select>
		<button type="button" class="btn btn-outline-secondary btn-sm" id="bulkClearSelection">
			<i class="fa-solid fa-xmark"></i> Clear
		</button>
		<button type="button" class="btn btn-sm btn-warning" id="openAssignModal" disabled>
			<i class="fa-solid fa-people-arrows"></i> Assign to user
		</button>
		
		<span class="mx-2">
			<a style="text-decoration: none;" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/add_sim">
				<button class="btn btn-outline-warning btn-sm">
					<i class="fa-solid fa-plus"></i> New Sim
				</button>
			</a>
		</span>
	</div>
</div>
<?php endif; ?>


<div id="opsSimsRegion" data-ajax-url="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/ajax_sims">
	<?php
		// Normalise SIMS KPI values so this fragment works for both full-page and ajax_sims renders
		$opsTotalSimsKpi      = isset($opsTotalSimsKpi)      ? (int)$opsTotalSimsKpi      : (int)($total_count ?? 0);
		$opsTotalSoldKpi      = isset($opsTotalSoldKpi)      ? (int)$opsTotalSoldKpi      : (int)(($totalSoldWholesale_count ?? 0) + ($totalSoldRetail_count ?? 0));
		$opsSellThroughKpi    = isset($opsSellThroughKpi)    ? (float)$opsSellThroughKpi : (
			$opsTotalSimsKpi > 0 ? round(($opsTotalSoldKpi / $opsTotalSimsKpi) * 100, 1) : 0
		);
		$opsActiveNetworksKpi = isset($opsActiveNetworksKpi) ? (int)$opsActiveNetworksKpi : 0;
	?>
	<div data-role="ops-sims-metrics"
	     data-total-sims="<?=$opsTotalSimsKpi?>"
	     data-total-sold="<?=$opsTotalSoldKpi?>"
	     data-sell-through="<?=$opsSellThroughKpi?>"
	     data-active-networks="<?=$opsActiveNetworksKpi?>"></div>

	<div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2 ops-sims-toolbar">
		<div class="flex-grow-1 ops-sims-search" style="max-width: 260px;">
			<input type="text" class="form-control form-control-sm" data-role="ops-sim-search" placeholder="Search SIM, serial or ID">
		</div>
		<div class="ops-sims-page-size">
			<select class="form-select form-select-sm w-auto" data-role="ops-page-size">
				<?php $pageLimit = isset($pager) ? (int)$pager->limit : 10; ?>
				<option value="5"  <?=$pageLimit == 5  ? 'selected' : ''?>>5</option>
				<option value="10" <?=$pageLimit == 10 ? 'selected' : ''?>>10</option>
				<option value="25" <?=$pageLimit == 25 ? 'selected' : ''?>>25</option>
				<option value="50" <?=$pageLimit == 50 ? 'selected' : ''?>>50</option>
			</select>
		</div>
	</div>

	<div class="table-responsive">
		<table class="table table-striped table-hover">
			<thead>
				<tr>
					<?php if(user_can('edit_sim')):?>
						<th class="bg-secondary text-warning sim-select-cell text-center">
							<input type="checkbox" class="form-check-input" id="selectAllSims"/>
						</th>
					<?php endif; ?>
					<th class="bg-secondary text-warning">#</th>
					<th class="bg-secondary text-warning">
						<span class="d-none d-lg-inline">Date created</span>
						<span class="d-lg-none">Details</span>
					</th>
					<th class="bg-secondary text-warning d-none d-lg-table-cell">Sim</th>
					<th class="bg-secondary text-warning ops-sim-serial-col d-none d-lg-table-cell">Serial #</th>
					<th class="bg-secondary text-warning ops-sim-status-col d-none d-lg-table-cell">Status</th>
					<th class="bg-secondary text-warning ops-sim-assigned-col d-none d-lg-table-cell">Assigned</th>
					<th class="bg-secondary text-warning ops-sim-created-col d-none d-lg-table-cell">Created by</th>
					<th class="bg-secondary text-warning"></th>
				</tr>
			</thead>
				<?php if(!empty($sims)): $id = 0;?>
					<?php
							//session data
							$session = get_session();
							//dd($session);
							if(!empty($session->id)){
								$username = $session->first_name . ' '. $session->last_name;
								$user_id = $session->id;
							}
						?>

					<tbody>
					<?php foreach($sims as $row): $id ++;?>
						<?php if(user_can('approve_transactions') || $session->id == $row->assigned ?? ''):?>
							<?php
								$badgeClass = '';
								switch (strtolower($row->transaction_status ?? 'unassigned')) {
									case 'expired':
										$badgeClass = 'badge bg-danger';
										break;
									case 'sold - retail':
									case 'sold - wholesale':
										$badgeClass = 'badge bg-success';
										break;
									case 'unassigned':
										$badgeClass = 'badge bg-warning text-dark';
										break;
									case 'assigned':
										$badgeClass = 'badge bg-primary';
										break;
									default:
										$badgeClass = 'badge bg-secondary';
										break;
								}
								$assignedName = trim((string)(($row->assigned_first_name ?? '') . ' ' . ($row->assigned_last_name ?? '')));
								$assignedName = $assignedName !== '' ? $assignedName : 'Unassigned';
								$createdName = trim((string)(($row->user_first_name ?? '') . ' ' . ($row->user_last_name ?? '')));
							?>
							<tr data-network="<?=esc(strtolower($row->network ?? ''))?>" data-status="<?=esc(strtolower($row->transaction_status ?? ''))?>" data-serial="<?=esc($row->serial_number ?? '')?>">
								<?php if(user_can('edit_sim')):?>
									<td class="sim-select-cell text-center align-middle">
										<?php if(strtolower($row->transaction_status ?? '') === 'unassigned'):?>
											<input type="checkbox" class="form-check-input sim-select" data-sim-id="<?=$row->id?>"/>
										<?php else:?>
											<span class="badge text-warning">—</span>
										<?php endif;?>
									</td>
								<?php endif;?>
								<td><span class="badge bg-warning text-black rounded-circle pill"><?=$id?></span></td>
								<td>
									<div><?=get_date($row->date_created ?? '')?></div>
									<div class="ops-sim-mobile-meta d-lg-none">
										<div class="text-primary">
											<strong>Network:</strong>
											<a style="text-decoration: none;" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view_sim/<?=$row->id?>">
												<?=strtoupper($row->network )?> <?=$row->number_assigned ?? ''?>
											</a>
										</div>
										<small class="text-muted">Serial #: <?=esc($row->serial_number ?? '')?></small><br>
										<small class="text-muted">Status:<span class="<?= $badgeClass ?>"><?=esc($row->transaction_status)?></span></small><br>
										<small class="text-muted">
											<span>Assigned:</span>
											<img src="<?=get_image($row->assigned_image)?>" style="width: 15px; height: 15px; object-fit: cover; border-radius: 50%;">
											<?=esc($assignedName)?>
										</small><br>
										<small class="mt-1 text-muted">
											<span>Created by:</span>
											<img src="<?=get_image($row->user_image)?>" style="width: 15px; height: 15px; object-fit: cover; border-radius: 50%;">
											<?=esc($createdName)?>
										</small>
									</div>
								</td>
								<td class="d-none d-lg-table-cell">
									<a style="text-decoration: none;" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view_sim/<?=$row->id?>">
										<?=strtoupper($row->network )?> <?=$row->number_assigned ?? ''?>
									</a>
								</td>
								<td class="ops-sim-serial-col d-none d-lg-table-cell"><?=esc($row->serial_number ?? '')?></td>
								<td class="ops-sim-status-col d-none d-lg-table-cell">
									<span class="<?= $badgeClass ?>"><?=esc($row->transaction_status)?></span>
								</td>
								<td class="ops-sim-assigned-col d-none d-lg-table-cell">
									<span><img src="<?=get_image($row->assigned_image)?>" style="width: 30px; height: 30px; object-fit: cover; border-radius: 50%;"></span>
									 <?=$row->assigned_first_name ?? ''?> <?=$row->assigned_last_name ?? 'Unassigned' ?>
								</td>
								<td class="ops-sim-created-col d-none d-lg-table-cell">
									<span><img src="<?=get_image($row->user_image)?>" style="width: 30px; height: 30px; object-fit: cover; border-radius: 50%;"></span>
									 <?=$row->user_first_name . ' ' . $row->user_last_name ?>
								</td>

								<td>
									<?php if(user_can('view_sim')):?>
									<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view_sim/<?=$row->id?>">
										<button class="btn btn-primary btn-sm" title="View">
											<i class="fa-solid fa-eye"></i> 
										</button>
									</a>
									<?php endif?>

									<?php if(user_can('edit_sim') AND ($row->transaction_status === 'Unassigned' || $row->transaction_status === 'Assigned' )):?>
									<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit_sim/<?=$row->id?>">
										<button class="btn btn-warning btn-sm" title="Edit">
											<i class="fa-solid fa-pen-to-square"></i>
										</button>
									</a>
									<?php endif?>
								</td>
							</tr>
						<?php endif;?>
						<?php endforeach?>
						<?php else:?>
							<tr>
								<td colspan="7" class="text-center">No records to show!</td>
							</tr>
					</tbody>				
				<?php endif?>
				</table>

	<?=$pager->display()?>
</div>
</div><!-- /#opsSimsRegion -->
<?php if(user_can('edit_sim') && !$is_ajax_sims):?>
<div id="bulkAssignCsrf" class="d-none"><?=csrf()?></div>

<div class="modal fade" id="bulkAssignModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header bg-warning text-dark">
				<h5 class="modal-title"><i class="fa-solid fa-people-arrows"></i> Assign selected sims</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<div id="bulkAssignAlert" class="alert d-none" role="alert"></div>
				<form id="bulkAssignForm" class="row g-3">
					<div class="col-12">
						<label class="form-label fw-semibold">Assign to user</label>
						<select class="form-select" id="assignUserSelect" name="assign_to" required>
							<option value="">Select staff member</option>
							<?php if(!empty($users)): foreach($users as $user):?>
							<option value="<?=$user->id?>"><?=esc($user->first_name . ' ' . $user->last_name)?></option>
							<?php endforeach; endif;?>
						</select>
					</div>
					<div class="col-md-12">
						<label class="form-label fw-semibold">Optional note</label>
						<textarea class="form-control" id="assignmentNote" rows="2" placeholder="e.g. Assigning for weekend activation run"></textarea>
					</div>
					<div class="col-12">
						<label class="form-label fw-semibold">Selected sims</label>
						<div class="table-responsive border rounded">
							<table class="table table-sm mb-0" id="selectedSimsTable">
								<thead class="">
									<tr>
										<th>#</th>
										<th>Serial</th>
										<th>Network</th>
									</tr>
								</thead>
								<tbody></tbody>
							</table>
						</div>
					</div>
				</form>
			</div>
			<div class="modal-footer d-flex justify-content-between">
				<small class="text-muted" id="bulkAssignHint"></small>
				<div>
					<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="button" class="btn btn-warning" id="confirmBulkAssign">
						<span class="assign-label">Assign sims</span>
						<span class="assign-spinner spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
					</button>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
	(function(){
		const panel = document.getElementById('bulkAssignPanel');
		if(!panel) return;

		const selectedMap = new Map();
		let bulkToggling = false;
		let bound = false;
		let checkboxes = [];
		let selectAll = null;

		const selectedCountEl = document.getElementById('bulkSelectedCount');
		const openModalBtn = document.getElementById('openAssignModal');
		const clearBtn = document.getElementById('bulkClearSelection');
		const networkFilter = document.getElementById('bulkNetworkFilter');

		const modalEl = document.getElementById('bulkAssignModal');
		const modal = (modalEl && window.bootstrap && typeof window.bootstrap.Modal === 'function')
			? new window.bootstrap.Modal(modalEl)
			: null;
		const fallbackModal = (!modal && modalEl) ? {
			show(){
				modalEl.classList.add('show','d-block');
				document.body.classList.add('modal-open');
			},
			hide(){
				modalEl.classList.remove('show','d-block');
				document.body.classList.remove('modal-open');
			}
		}: null;

		const selectedTableBody = document.querySelector('#selectedSimsTable tbody');
		const confirmBtn = document.getElementById('confirmBulkAssign');
		const assignSelect = document.getElementById('assignUserSelect');
		const noteField = document.getElementById('assignmentNote');
		const alertBox = document.getElementById('bulkAssignAlert');
		const hintEl = document.getElementById('bulkAssignHint');
		const csrfInput = document.querySelector('#bulkAssignCsrf input[name="csrf"]');

		function refreshPanel(){
			checkboxes = Array.from(document.querySelectorAll('.sim-select'));
			selectAll = document.getElementById('selectAllSims');

			const count = selectedMap.size;
			if (selectedCountEl) selectedCountEl.textContent = `${count} selected`;
			if (openModalBtn) openModalBtn.disabled = count === 0;

			if(selectAll && !bulkToggling){
				const enabledCheckboxes = checkboxes.filter(cb => !cb.disabled);
				const checkedEnabled = enabledCheckboxes.filter(cb => cb.checked);
				const allChecked = enabledCheckboxes.length > 0 && checkedEnabled.length === enabledCheckboxes.length;
				if (selectAll) {
					selectAll.checked = allChecked;
					selectAll.indeterminate = !allChecked && checkedEnabled.length > 0;
				}
			}
		}

		function toggleSelection(input){
			const id = parseInt(input.dataset.simId);
			if(!id) return;
			const row = input.closest('tr');
			row?.classList.toggle('sim-selected', input.checked);
			if(input.checked){
				selectedMap.set(id, {
					id,
					serial: row?.dataset.serial || '',
					network: row?.dataset.network?.toUpperCase() || ''
				});
			}else{
				selectedMap.delete(id);
			}
			refreshPanel();
		}

		function onToggleEvent(e){
			const target = e.target;
			if (!target) return;

			if (target.classList && target.classList.contains('sim-select')) {
				toggleSelection(target);
				return;
			}

			if (target.id === 'selectAllSims') {
				const targetState = !!target.checked;
				bulkToggling = true;
				const current = Array.from(document.querySelectorAll('.sim-select'));
				current.forEach(cb => {
					if(cb.disabled) return;
					cb.checked = targetState;
					toggleSelection(cb);
				});
				bulkToggling = false;
				refreshPanel();
			}
		}

		document.addEventListener('change', onToggleEvent);

		if(clearBtn){
			clearBtn.addEventListener('click', () => {
				selectedMap.clear();

				checkboxes.forEach(cb => {
					cb.checked = false;
					cb.closest('tr')?.classList.remove('sim-selected');
				});
				refreshPanel();
			});
		}

		if(networkFilter){
			networkFilter.addEventListener('change', () => {
				const value = networkFilter.value;
				// Always work with the current set of rows
				const currentRows = document.querySelectorAll('table.table tbody tr[data-network]');
				currentRows.forEach(row => {
					if(!value){
						row.classList.remove('d-none');
						return;
					}
					row.classList.toggle('d-none', row.dataset.network !== value);
				});
			});
		}

		function populateModal(){
			if (selectedTableBody) selectedTableBody.innerHTML = '';
			let idx = 0;
			selectedMap.forEach(sim => {
				const tr = document.createElement('tr');
				tr.innerHTML = `<td>${++idx}</td><td>${sim.serial || '—'}</td><td>${sim.network}</td>`;
				if (selectedTableBody) selectedTableBody.appendChild(tr);
			});
			if (hintEl) hintEl.textContent = `${selectedMap.size} sim(s) queued for assignment.`;
			if (alertBox) {
				alertBox.classList.add('d-none');
				alertBox.textContent = '';
			}
			if (assignSelect) assignSelect.value = '';
			if (noteField) noteField.value = '';
		}

		function showModal(){
			populateModal();
			if(modal){
				modal.show();
			}else{
				fallbackModal?.show();
			}
		}

		if(openModalBtn){
			openModalBtn.addEventListener('click', showModal);
		}

		if(fallbackModal && modalEl){
			modalEl.querySelectorAll('[data-bs-dismiss="modal"]').forEach(btn => {
				btn.addEventListener('click', () => fallbackModal.hide());
			});
		}

		if(confirmBtn){
			confirmBtn.addEventListener('click', submitBulkAssign);
		}

		function setLoading(state){
			if (!confirmBtn) return;
			confirmBtn.disabled = state;
			const lbl = confirmBtn.querySelector('.assign-label');
			const spn = confirmBtn.querySelector('.assign-spinner');
			if (lbl) lbl.classList.toggle('d-none', state);
			if (spn) spn.classList.toggle('d-none', !state);
		}

		function showAlert(type, message){
			if (!alertBox) return;
			alertBox.className = `alert alert-${type}`;
			alertBox.textContent = message;
			alertBox.classList.remove('d-none');
		}

		async function submitBulkAssign(){

			const assignTo = assignSelect.value;
			if(!assignTo){
				assignSelect.focus();
				showAlert('warning', 'Select the user receiving these sims.');
				return;
			}
			if(!csrfInput){
				showAlert('danger', 'Missing security token. Refresh the page.');
				return;
			}
			setLoading(true);
			try{

				const response = await fetch('<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/bulk_assign_sims', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'Accept': 'application/json'
					},
					body: JSON.stringify({
						__ajax: true,
						assign_to: assignTo,
						assignment_note: noteField.value,
						selected_ids: Array.from(selectedMap.keys()),
						csrf: csrfInput.value
					})
				});
				if (!response.ok) {
					showAlert('danger', 'Assignment failed. Please refresh and try again.');
					return;
				}
				let data = null;
				try {
					data = await response.json();
				} catch (e) {
					showAlert('danger', 'Server did not return JSON. Please refresh and try again.');
					return;
				}
				if(!data.success){

					const message = Object.values(data.errors || {}).join(' ') || 'Assignment failed.';
					showAlert('danger', message);
					return;
				}
				showAlert('success', `${data.assigned} sim(s) assigned to ${data.assigned_to?.name || 'user'}.`);
				updateRowsAfterAssign(data);
				setTimeout(() => {
					if(modal){
						modal.hide();
					}else{
						fallbackModal?.hide();
					}
				}, 800);

			}catch(err){
				console.error(err);
				showAlert('danger', 'Unexpected error. Please try again.');
			}finally{
				setLoading(false);
			}
		}

		function updateRowsAfterAssign(payload){
			const assignedName = payload.assigned_to?.name || '';
			const assignedImg = payload.assigned_to?.image || '';
			selectedMap.forEach((info, id) => {
				const checkbox = document.querySelector(`.sim-select[data-sim-id="${id}"]`);
				const row = checkbox?.closest('tr');
				if(!row) return;
				checkbox.checked = false;
				checkbox.disabled = true;
				checkbox.classList.add('d-none');
				const badgeCell = checkbox.parentElement;
				if(badgeCell){
					badgeCell.innerHTML = '<span class="badge text-muted">—</span>';
				}
				row.classList.remove('sim-selected');
				row.dataset.status = 'assigned';
				const statusEl = row.querySelector('td:nth-child(<?=(user_can('edit_sim') ? 6 : 5)?>) span');
				if(statusEl){
					statusEl.className = 'badge bg-primary';
					statusEl.textContent = 'Assigned';
				}
				const assignedCell = row.querySelector('td:nth-child(<?=(user_can('edit_sim') ? 7 : 6)?>)');
				if(assignedCell){
					assignedCell.innerHTML = `<span><img src="${assignedImg}" style="width: 30px; height: 30px; object-fit: cover; border-radius: 50%;"></span> ${assignedName}`;
				}
			});
			selectedMap.clear();
			refreshPanel();
		}

		function init(){
			if(bound) return;
			bound = true;
			refreshPanel();
		}

		init();
		document.addEventListener('ops:sims:updated', function(){
			selectedMap.clear();
			refreshPanel();
		});
	})();
</script>

<?php endif?>
<?php endif?>
<?php else:?>
	<div class="alert alert-danger text-center">
		Access denied. You dont have permission for this action
	</div>
<?php endif?>
