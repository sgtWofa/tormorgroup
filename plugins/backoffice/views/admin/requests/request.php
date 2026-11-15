<style type="text/css">
	thead tr{
		white-space: nowrap;
	}
	tbody tr{
		white-space: nowrap;
		transition: background-color .12s ease, transform .06s ease;
	}
	tfoot tr{
		white-space: nowrap;
	}

	/* header band */
	table.table thead th{
		text-transform: uppercase;
		font-size: .75rem;
		letter-spacing: .06em;
		border-top: 0;
		border-bottom: 1px solid var(--bs-border-color);
		background: rgba(var(--bs-secondary-rgb,148,163,184),.1);
	}

	/* rows */
	table.table tbody tr:hover{
		background: rgba(var(--bs-warning-rgb,255,193,7),.06);
		transform: translateY(-1px);
	}

	/* links */
	a{
		text-decoration: none;
	}

	.bo-desc-cell{
		width: 32%;
		min-width: 260px;
		max-width: 420px;
		white-space: normal !important;
	}
	.bo-desc-text{
		display: block;
		white-space: normal !important;
		overflow-wrap: anywhere;
		word-break: break-word;
	}
	.bo-desc-text small{
		font-size: 10px;
	}

	#bo-req-confirm-modal .table td,
	#bo-req-confirm-modal .table th,
	#bo-req-confirm-modal .modal-body,
	#bo-req-confirm-modal .modal-body .text-danger,
	#bo-req-confirm-modal .modal-body .text-muted{
		white-space: normal !important;
		overflow-wrap: anywhere;
		word-break: break-word;
	}

	#bo-req-confirm-modal #bo-req-confirm-error,
	#bo-req-confirm-modal #bo-req-confirm-deno-mismatch-error,
	#bo-req-confirm-modal #bo-req-confirm-amounts{
		display: block;
		max-width: 100%;
	}

	/* empty state row */
	table.table tbody tr td.text-center.justify-content-center{
		color: var(--bs-secondary-color);
		font-size: .9rem;
	}

	/* metrics cards */
	#bo-req-metrics .metric-card{
		border: none;
		background: linear-gradient(135deg, #facc15 0%, #fed7aa 40%, #fff7ed 100%);
		box-shadow: 0 4px 14px rgba(0,0,0,.08);
		color: #111827;
	}

	#bo-req-metrics .metric-card.metric-accent{
		background: linear-gradient(135deg, #0f172a 0%, #1e293b 45%, #020617 100%);
		color: #f9fafb;
	}

	#bo-req-metrics .metric-label{
		font-size: .7rem;
		letter-spacing: .08em;
		text-transform: uppercase;
		color: #111827 !important;
	}

	body[data-bs-theme="dark"] #bo-req-metrics .metric-label,
	body.dark-mode #bo-req-metrics .metric-label{
		color: #000000ff !important;
	}

	#bo-req-metrics .metric-trend{
		font-size: .7rem;
		color: #1f2933 !important;
	}

	@media (min-width:768px) and (max-width:991.98px){
		#bo-req-metrics .metric-card{padding:.75rem !important;}
		#bo-req-metrics .fw-semibold{font-size:1.05rem;}
		#bo-req-metrics .metric-trend{display:none;}
	}
</style>

<div id="bo-req-flash" class="alert d-none small py-2 px-3 mb-2" role="alert"></div>

<div class="row my-3 mx-auto g-2 align-items-center">
	<div class="col-md-6">
		<div class="input-group input-group-sm">
			<span class="input-group-text bg-body-secondary border-0"><i class="fa fa-search text-muted"></i></span>
			<input type="text" id="bo-req-search" class="form-control border-0" placeholder="Search requests (id, wallet, till, type, status, parties)...">
		</div>
	</div>
	<div class="col-md-3 mt-2 mt-md-0">
		<div class="d-flex gap-2">
			<select id="bo-req-status" class="form-select form-select-sm">
				<option value="">All statuses</option>
				<option value="pending">Pending</option>
				<option value="approved">Approved</option>
				<option value="rejected">Rejected</option>
				<option value="draft">Draft</option>
			</select>
			<select id="bo-req-scope" class="form-select form-select-sm">
				<option value="all">Created & Assigned</option>
				<option value="created">Created by me</option>
				<option value="assigned">Assigned to me</option>
			</select>
		</div>
	</div>
	<div class="col-md-3 mt-2 mt-md-0 text-md-end">
		<div class="d-flex justify-content-end gap-2 mb-2">
			<button type="button" id="bo-req-export-pdf" class="btn btn-outline-secondary btn-sm">Export PDF</button>
			<button type="button" id="bo-req-export-xlsx" class="btn btn-outline-success btn-sm">Export Excel</button>
		</div>
		<?php if(user_can('add_request')):?>
		<a style="text-decoration: none;" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/make_request">
			<button class="btn btn-warning btn-sm">
				<i class="fa-solid fa-plus"></i> Make Request
			</button>
		</a>
		<?php endif?>
	</div>
</div>
<div id="bo-req-metrics" class="row g-2 mb-1 small align-items-stretch">
	<div class="col-12 col-sm-6 col-lg-3">
		<div class="p-2 rounded-3 metric-card h-100 d-flex flex-column justify-content-center">
			<div class="d-flex justify-content-between align-items-center mb-1">
				<div class="metric-label text-muted">Total amount</div>
				<i class="fa fa-coins text-warning"></i>
			</div>
			<div class="d-flex justify-content-between align-items-end">
				<div class="fw-semibold" id="bo-req-metric-amount">–</div>
				<div class="metric-trend text-muted" id="bo-req-metric-trend">vs yesterday: –</div>
			</div>
		</div>
	</div>
	<div class="col-12 col-sm-6 col-lg-3">
		<div class="p-2 rounded-3 bg-body border h-100 d-flex flex-column justify-content-center metric-card">
			<div class="d-flex justify-content-between align-items-center mb-1">
				<div class="metric-label text-muted">Total requests</div>
				<i class="fa fa-list-check text-secondary"></i>
			</div>
			<div class="fw-semibold" id="bo-req-metric-count">–</div>
		</div>
	</div>
	<div class="col-4 col-sm-4 col-lg-2">
		<div class="p-2 rounded-3 bg-body border h-100 d-flex flex-column justify-content-center metric-card">
			<div class="metric-label text-muted">Pending</div>
			<div class="fw-semibold text-secondary" id="bo-req-metric-pending">–</div>
		</div>
	</div>
	<div class="col-4 col-sm-4 col-lg-2">
		<div class="p-2 rounded-3 bg-body border h-100 d-flex flex-column justify-content-center metric-card">
			<div class="metric-label text-muted">Approved</div>
			<div class="fw-semibold text-success" id="bo-req-metric-approved">–</div>
		</div>
	</div>
	<div class="col-12 col-sm-4 col-lg-2">
		<div class="p-2 rounded-3 bg-body border h-100 d-flex flex-column justify-content-center metric-card metric-accent">
			<div class="metric-label">Rejected</div>
			<div class="fw-semibold text-danger mb-1" id="bo-req-metric-rejected">–</div>
			<div class="text-end" style="font-size:.7rem;"><span id="bo-req-scope-label" class="text-uppercase text-warning"></span></div>
		</div>
	</div>
</div>

<div class="table-responsive">
	<table class="table table-striped table-hover">
		<thead class="d-none d-lg-table-header-group">
			<tr>
				<th>#</th>
				<th>Date created</th>
				<th>Description</th>
				<th>Status</th>
				<th>Amount</th>
				<th>Parties Involved</th>
				<th>Action</th>
			</tr>
		</thead>
		<tbody id="bo-req-tbody">
		<?php if(!empty($requests)): $id = 0; ?>
			<?php foreach($requests as $row): $id ++;?>
				<?php
					$badgeClass = '';
					switch (strtolower($row->transaction_status ?? 'approved')) {
						case 'pending':
							$badgeClass = 'badge bg-warning text-dark';
							break;
						case 'approved':
							$badgeClass = 'badge bg-success';
							break;
						case 'rejected':
							$badgeClass = 'badge bg-danger';
							break;
						default:
							$badgeClass = 'badge bg-secondary';
							break;
					}
					$rejectReason = trim($row->reject_reason ?? '');
					$dropdownId = 'dropdownMenuButton' . (string)($row->id ?? $id);
				?>
				<tr>
					<td class="d-lg-none align-top" style="width:1%">
						<span class="badge bg-warning text-black rounded-circle pill"><?=$id?></span>
					</td>
					<td class="d-lg-none align-top">
						<div class="bo-stack-cell">
							<div class="line">
								<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view_request/<?=$row->id?>">
									<?=esc($row->network . ' - ' . $row->request_type ?? '')?>
								</a>
							</div>
							<div class="line"><span class="k">Date</span><span><?=get_date_time($row->date_created)?></span></div>
							<div class="line">
								<span class="k">Status</span>
								<span>
									<span class="<?= $badgeClass ?>"><?=esc($row->transaction_status)?></span>
									<?php if (strtolower($row->transaction_status ?? '') === 'rejected' && $rejectReason !== ''): ?>
										<i class="fa fa-info-circle text-muted ms-1"
										   tabindex="0"
										   data-bs-toggle="popover"
										   data-bs-trigger="focus hover"
										   data-bs-placement="top"
										   data-bs-html="true"
										   data-bs-title="Reject reason"
										   data-bs-content="<?=esc($rejectReason)?>"></i>
									<?php endif; ?>
								</span>
							</div>
							<div class="line"><span class="k">Desc</span><span><?=esc($row->description ?? '')?></span></div>
							<div class="line"><span class="k">Amount</span><span class="fw-semibold"><?=number_format($row->amount ?? 0, 2)?></span></div>
							<div class="line"><span class="k">From</span><span><?=$row->user_first_name . ' ' . $row->user_last_name ?></span></div>
							<div class="line"><span class="k">To</span><span><?=$row->assigned_first_name . ' ' . $row->assigned_last_name ?></span></div>
						</div>
					</td>
					<td class="d-lg-none text-end align-top" style="width:1%">
						<?php
							$st = strtolower($row->transaction_status ?? '');
							$canAct = user_can('approve_request') && in_array($st, ['pending','draft'], true);
							$canEdit = in_array($st, ['draft','pending'], true);
							$canDelete = user_can('delete_request') && in_array($st, ['draft','pending'], true);
						?>
						<div class="d-inline-flex flex-wrap gap-1 justify-content-end">
							<?php if ($canAct): ?>
								<button type="button" class="btn btn-outline-success btn-sm bo-req-action" data-id="<?=esc($row->id)?>" data-action="approve" data-bs-toggle="tooltip" title="Approve" aria-label="Approve"><i class="fa fa-check"></i></button>
								<button type="button" class="btn btn-outline-danger btn-sm bo-req-action" data-id="<?=esc($row->id)?>" data-action="reject" data-bs-toggle="tooltip" title="Reject" aria-label="Reject"><i class="fa fa-times"></i></button>
							<?php endif; ?>
							<a class="btn btn-outline-warning btn-sm" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view_request/<?=$row->id?>" data-bs-toggle="tooltip" title="Details" aria-label="Details"><i class="fa fa-eye"></i></a>
							<?php if ($canEdit): ?>
								<a class="btn btn-outline-warning btn-sm" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit_request/<?=$row->id?>" data-bs-toggle="tooltip" title="Update" aria-label="Update"><i class="fa fa-pen"></i></a>
							<?php endif; ?>
							<?php if ($canDelete): ?>
								<a class="btn btn-outline-danger btn-sm" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/delete/<?=$row->id?>" data-bs-toggle="tooltip" title="Delete" aria-label="Delete"><i class="fa fa-trash"></i></a>
							<?php endif; ?>
						</div>
					</td>
					<td class="d-none d-lg-table-cell">
						<span class="badge bg-warning text-black rounded-circle pill"><?=$id?></span>
					</td>
					<td class="d-none d-lg-table-cell"><?=get_date_time($row->date_created)?></td>
					<td class="d-none d-lg-table-cell bo-desc-cell">
						<div>
							<a  href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view_request/<?=$row->id?>">
								<?=esc($row->network . ' - ' . $row->request_type ?? '')?>
							</a>
						</div>
						<div class="text-muted fw-semibold bo-desc-text"><?=esc($row->description ?? '')?></div>
					</td>
					<td class="d-none d-lg-table-cell">
						<span class="<?= $badgeClass ?>"><?=esc($row->transaction_status)?></span>
						<?php if (strtolower($row->transaction_status ?? '') === 'rejected' && $rejectReason !== ''): ?>
							<i class="fa fa-info-circle text-muted ms-1"
							   tabindex="0"
							   data-bs-toggle="popover"
							   data-bs-trigger="focus hover"
							   data-bs-placement="top"
							   data-bs-html="true"
							   data-bs-title="Reject reason"
							   data-bs-content="<?=esc($rejectReason)?>"></i>
						<?php endif; ?>
					</td>
					<td class="d-none d-lg-table-cell"><?=number_format($row->amount ?? 0, 2)?></td>
					<td class="d-none d-lg-table-cell">
						<div class="d-flex align-items-center gap-2 flex-nowrap mb-1">
							<img src="<?=get_image($row->user_image)?>" style="width: 20px; height: 20px; object-fit: cover; border-radius: 50%;">
							<code class="mb-0">From:</code>
							<span class="text-nowrap"><?=$row->user_first_name . ' ' . $row->user_last_name ?></span>
						</div>
						<div class="d-flex align-items-center gap-2 flex-nowrap">
						 	<img src="<?=get_image($row->assigned_image)?>" style="width: 15px; height: 15px; object-fit: cover; border-radius: 50%;">
						 	<code class="mb-0">To:</code>
						 	<span class="text-nowrap"><?=$row->assigned_first_name . ' ' . $row->assigned_last_name ?></span>
						 </div>
					</td>
					<td class="d-none d-lg-table-cell">
						<?php
							$st = strtolower($row->transaction_status ?? '');
							$canAct = user_can('approve_request') && in_array($st, ['pending','draft'], true);
							$canEdit = in_array($st, ['draft','pending'], true);
							$canDelete = user_can('delete_request') && in_array($st, ['draft','pending'], true);
						?>
						<div class="d-inline-flex flex-wrap gap-1 justify-content-end">
							<?php if ($canAct): ?>
								<button type="button" class="btn btn-outline-success btn-sm bo-req-action" data-id="<?=esc($row->id)?>" data-action="approve" data-bs-toggle="tooltip" title="Approve" aria-label="Approve"><i class="fa fa-check"></i></button>
								<button type="button" class="btn btn-outline-danger btn-sm bo-req-action" data-id="<?=esc($row->id)?>" data-action="reject" data-bs-toggle="tooltip" title="Reject" aria-label="Reject"><i class="fa fa-times"></i></button>
							<?php endif; ?>
							<a class="btn btn-outline-warning btn-sm" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view_request/<?=$row->id?>" data-bs-toggle="tooltip" title="Details" aria-label="Details"><i class="fa fa-eye"></i></a>
							<?php if ($canEdit): ?>
								<a class="btn btn-outline-warning btn-sm" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit_request/<?=$row->id?>" data-bs-toggle="tooltip" title="Update" aria-label="Update"><i class="fa fa-pen"></i></a>
							<?php endif; ?>
							<?php if ($canDelete): ?>
								<a class="btn btn-outline-danger btn-sm" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/delete/<?=$row->id?>" data-bs-toggle="tooltip" title="Delete" aria-label="Delete"><i class="fa fa-trash"></i></a>
							<?php endif; ?>
						</div>
					</td>
				</tr>
			<?php endforeach?>
		<?php else:?>
			<tr><td colspan="10" class="text-center justify-content-center">No records to show!</td></tr>
		<?php endif?>
		</tbody>
	</table>

	<div id="bo-req-pager" class="mt-2">
		<?=$pager->display()?>
	</div>
</div>

<!-- Inline approval/rejection confirm modal -->
<div class="modal fade" id="bo-req-confirm-modal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header bg-dark text-white">
				<h6 class="modal-title" id="bo-req-confirm-title">Confirm action</h6>
				<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<div id="bo-req-confirm-loading" class="text-center py-4">
					<div class="spinner-border text-warning" role="status"><span class="visually-hidden">Loading...</span></div>
					<div class="mt-2 small text-muted">Preparing request details...</div>
				</div>
				<div id="bo-req-confirm-content" class="d-none">
					<div class="mb-2 small text-muted" id="bo-req-confirm-context"></div>
					<div class="mb-3 border rounded-3 bg-body-tertiary p-2 small">
						<div class="d-flex flex-wrap justify-content-between align-items-center mb-1">
							<div>
								<div class="text-uppercase text-muted" style="font-size:.7rem;">Request</div>
								<div id="bo-req-confirm-summary-main" class="fw-semibold text-warning"></div>
							</div>
							<div class="text-end">
								<div class="text-uppercase text-muted" style="font-size:.7rem;">Amount</div>
								<div id="bo-req-confirm-summary-amount" class="fw-semibold text-warning"></div>
							</div>
						</div>
						<div id="bo-req-confirm-summary-desc" class="text-muted" style="font-size:.8rem;"></div>
					</div>
					<div id="bo-req-confirm-assigned-details" class="mb-3 border rounded-3 bg-body-tertiary p-2 small d-none"></div>
					<div id="bo-req-cash-verify-section" class="d-none">
						<div class="alert alert-light border small mb-3" id="bo-req-cash-verify-copy">
							Review the denominations and confirm the total matches the request amount before approval.
						</div>
					<div class="table-responsive border rounded-3">
						<table class="table table-sm mb-0">
							<thead class="">
								<tr>
									<th style="width:35%">Denomination</th>
									<th class="text-center" style="width:25%">Count</th>
									<th class="text-end" style="width:30%">Total</th>
									<th style="width:10%"></th>
								</tr>
							</thead>
							<tbody id="bo-req-confirm-denos"></tbody>
							<tfoot>
								<tr class="fw-semibold">
									<td colspan="2" class="text-end">Request amount</td>
									<td class="text-end" id="bo-req-confirm-amount">GHS 0.00</td>
									<td></td>
								</tr>
								<tr>
									<td colspan="4" class="pt-1 small">
										<span id="bo-req-confirm-error" class="text-danger d-none">Calculator total must exactly match the original request amount before you can approve.</span>
										<span id="bo-req-confirm-deno-mismatch-error" class="text-danger d-none">Denomination mismatch: the entered cash denominations do not match the creator submitted denominations.</span>
										<span id="bo-req-confirm-amounts" class="text-muted small d-block mt-1"></span>
									</td>
								</tr>
								<tr>
									<td colspan="4" class="pt-2">
										<button type="button" class="btn btn-outline-secondary btn-xs btn-sm" id="bo-req-add-deno">
											<i class="fa fa-plus-circle"></i> Add denomination
										</button>
									</td>
								</tr>
							</tfoot>
						</table>
					</div>
					<div class="mt-3 small text-muted">Adjust the note counts if needed and confirm the total still matches the request amount.</div>
					</div>
					<div class="mt-3 d-none" id="bo-req-receipt-section">
						<label for="bo-req-approval-receipt" class="form-label small mb-1">Payment receipt</label>
						<input type="file" class="form-control form-control-sm" id="bo-req-approval-receipt" accept="image/*">
						<small class="text-muted d-block mt-1">Upload the payment receipt before approving this non-cash request.</small>
						<small class="text-danger d-none" id="bo-req-approval-receipt-error">Payment receipt is required before approval.</small>
					</div>
					<div class="mt-3 d-none" id="bo-req-reject-reason-wrap">
						<label for="bo-req-reject-reason" class="form-label small mb-1">Reason for rejection</label>
						<textarea id="bo-req-reject-reason" class="form-control form-control-sm" rows="2" placeholder="Provide a short reason so the requester understands why this was rejected..."></textarea>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-danger btn-sm d-none" id="bo-req-confirm-reject" data-id="" data-action="reject">Confirm reject</button>
				<button type="button" class="btn btn-success btn-sm d-none" id="bo-req-confirm-approve" data-id="" data-action="approve">Confirm approve</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="bo-req-upload-progress-modal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
	<div class="modal-dialog modal-sm modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-body">
				<div class="fw-semibold mb-2" id="bo-req-upload-progress-title">Submitting approval...</div>
				<div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100">
					<div class="progress-bar progress-bar-striped progress-bar-animated" id="bo-req-upload-progress-bar" style="width:0%">0%</div>
				</div>
				<div class="small text-muted mt-2" id="bo-req-upload-progress-status">Preparing upload...</div>
			</div>
		</div>
	</div>
</div>

<script>
const boReqActionUrl = '<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/request_action';
let boReqCurrentPage = 1;

function boReqShowWhatsappNotification(notification) {
	const links = notification && Array.isArray(notification.links) ? notification.links.filter(function (item) {
		return item && typeof item.url === 'string' && item.url !== '';
	}) : [];
	if (!links.length) return;
	const buttons = links.map(function (item) {
		return '<a class="btn btn-success btn-sm m-1" target="_blank" rel="noopener" href="' + item.url + '">Open WhatsApp</a>';
	}).join('');
	const message = 'WhatsApp message ready for the creator. Open the link and press Send.';
	if (window.Swal && typeof window.Swal.fire === 'function') {
		const options = { icon: 'info', title: 'WhatsApp message ready', html: '<p>' + message + '</p><div>' + buttons + '</div>' };
		if (typeof window.basicAdminSwalTheme === 'function') Object.assign(options, window.basicAdminSwalTheme());
		window.Swal.fire(options);
	}
}

function getRequestCurrency(ctx) {
	return String((ctx && ctx.currency) || 'GHS').toUpperCase() === 'CFA' ? 'CFA' : 'GHS';
}

function formatRequestAmount(value, currency) {
	const cur = String(currency || 'GHS').toUpperCase() === 'CFA' ? 'CFA' : 'GHS';
	const num = Number(value || 0);
	const options = cur === 'CFA'
		? { minimumFractionDigits: 0, maximumFractionDigits: 0 }
		: { minimumFractionDigits: 2, maximumFractionDigits: 2 };
	return cur + ' ' + num.toLocaleString(undefined, options);
}

function boReqRenderAssignedSnapshot(snapshot) {
	const ctx = window.boReqModal || {};
	const box = ctx.assignedDetails;
	if (!box) return;
	box.innerHTML = '';
	box.className = 'mb-3 border rounded-3 bg-body-tertiary p-2 small d-none';
	const lines = snapshot && Array.isArray(snapshot.lines) ? snapshot.lines : [];
	if (!snapshot || (!snapshot.missing && !lines.length)) return;

	box.classList.remove('d-none');
	if (snapshot.missing) {
		box.classList.add('border-warning');
	}

	const title = document.createElement('div');
	title.className = 'text-uppercase text-muted mb-1';
	title.style.fontSize = '.7rem';
	title.textContent = 'Assigned SIM / Wallet';
	box.appendChild(title);

	if (snapshot.message) {
		const message = document.createElement('div');
		message.className = snapshot.missing ? 'text-warning mb-1' : 'text-muted mb-1';
		message.textContent = String(snapshot.message);
		box.appendChild(message);
	}

	lines.forEach(function (line) {
		const row = document.createElement('div');
		row.className = 'd-flex justify-content-between gap-3';
		const label = document.createElement('span');
		label.className = 'text-muted';
		label.textContent = String(line.label || '');
		const value = document.createElement('span');
		value.className = line.missing ? 'text-warning fw-semibold text-end' : 'fw-semibold text-end';
		value.textContent = line.missing ? 'Not saved' : String(line.value || '');
		row.appendChild(label);
		row.appendChild(value);
		box.appendChild(row);
	});
}

function boReqBuildDenoSignature(list, currency) {
	const cur = String(currency || 'GHS').toUpperCase() === 'CFA' ? 'CFA' : 'GHS';
	const signature = {};
	(Array.isArray(list) ? list : []).forEach(function (item) {
		const itemCurrency = String(item && item.currency ? item.currency : cur).toUpperCase();
		if (itemCurrency !== cur) return;
		const denomination = Number(item && item.denomination ? item.denomination : 0);
		const count = Number(item && item.count ? item.count : 0);
		if (denomination <= 0 || count <= 0) return;
		const key = cur === 'CFA' ? String(Math.round(denomination)) : Number(denomination).toFixed(2);
		signature[key] = (signature[key] || 0) + count;
	});
	return signature;
}

function boReqUpdateApproveState() {
	const ctx = window.boReqModal || {};
	const approveBtn = ctx.btnConfirmApprove;
	const totalError = document.getElementById('bo-req-confirm-error');
	const denoMismatchError = document.getElementById('bo-req-confirm-deno-mismatch-error');
	const receiptError = ctx.receiptError;
	if (!approveBtn) return true;

	let canApprove = true;
	if (ctx.currentAction !== 'approve') {
		approveBtn.disabled = false;
		approveBtn.classList.remove('disabled');
		return true;
	}

	if (ctx.isCashRequest) {
		const original = Number(ctx.originalAmount || 0);
		const enteredTotal = Number(ctx.enteredTotal || 0);
		const totalMatches = original > 0 && Math.abs(enteredTotal - original) <= 0.01;
		const hasEntries = Array.isArray(ctx.denos) && ctx.denos.some(function (item) {
			return Number(item && item.count ? item.count : 0) > 0;
		});
		const expectedSignature = boReqBuildDenoSignature(ctx.expectedDenos || [], ctx.currency);
		const actualSignature = boReqBuildDenoSignature(ctx.denos || [], ctx.currency);
		const denosMatch = hasEntries && JSON.stringify(expectedSignature) === JSON.stringify(actualSignature);

		if (totalError) {
			totalError.classList.toggle('d-none', !hasEntries || totalMatches);
		}
		if (denoMismatchError) {
			denoMismatchError.classList.toggle('d-none', !hasEntries || denosMatch);
		}
		if (receiptError) {
			receiptError.classList.add('d-none');
		}
		canApprove = hasEntries && totalMatches && denosMatch;
	} else {
		if (totalError) totalError.classList.add('d-none');
		if (denoMismatchError) denoMismatchError.classList.add('d-none');
		if (receiptError) {
			receiptError.classList.add('d-none');
		}
		canApprove = true;
	}

	approveBtn.disabled = !canApprove;
	approveBtn.classList.toggle('disabled', !canApprove);
	return canApprove;
}

// Helper: recompute and display the total volume from current denominations
function recalcModalAmount() {
	if (!window.boReqModal || !Array.isArray(window.boReqModal.denos)) return;
	const ctx = window.boReqModal;
	const currency = getRequestCurrency(ctx);
	let total = 0;
	ctx.denos.forEach(function (item) {
		const note = Number(item.denomination || 0);
		const count = Number(item.count || 0);
		if (note > 0 && count > 0) {
			total += note * count;
		}
	});
	ctx.enteredTotal = total;
	// Update displayed calculator total
	if (ctx.modalAmount) {
		ctx.modalAmount.textContent = formatRequestAmount(total, currency);
	}
	// Toggle input error border when mismatched
	if (ctx.modalDenos) {
		const hasErrorClass = Number(ctx.originalAmount || 0) > 0 && Math.abs(total - Number(ctx.originalAmount || 0)) > 0.01;
		ctx.modalDenos.querySelectorAll('.bo-deno-note, .bo-deno-count').forEach(function (inp) {
			if (hasErrorClass) {
				inp.classList.add('is-invalid');
			} else {
				inp.classList.remove('is-invalid');
			}
		});
	}

	// Initialize popovers for any existing reject reason icons
	boReqInitPopovers();
	boReqUpdateApproveState();
}

// Helper: render denominations table from window.boReqModal.denos
function renderDenosTable() {
	if (!window.boReqModal) return;
	const ctx = window.boReqModal;
	const modalDenos = ctx.modalDenos;
	if (!modalDenos) return;
	const list = Array.isArray(ctx.denos) ? ctx.denos : [];
	if (!list.length) {
		modalDenos.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No denominations captured.</td></tr>';
		recalcModalAmount();
		return;
	}
	var cur = String(ctx.currency || 'GHS').toUpperCase();
	var DENOS = (cur === 'CFA')
		? [10000, 5000, 2000, 1000, 500, 200, 100, 50, 20, 10]
		: [200, 100, 50, 20, 10, 5, 2, 1, 0.5, 0.2, 0.1];
	function formatDenoOption(n){
		try{
			return Number(n).toLocaleString(undefined, { minimumFractionDigits: (cur === 'CFA') ? 0 : 2, maximumFractionDigits: (cur === 'CFA') ? 0 : 2 });
		}catch(e){
			return String(n);
		}
	}
	var selected = new Set();
	list.forEach(function(it){
		var v = Number(it.denomination || 0);
		if (v > 0) selected.add(String(v));
	});
	let rowsHtml = '';
	list.forEach(function (item, index) {
		var note = Number(item.denomination || 0);
		var count = Number(item.count || 0);
		var total = note * count;
		var countValue = count > 0 ? String(count) : '';
		var options = '';
		DENOS.forEach(function (d) {
			var s = String(Number(d));
			if (s !== String(note) && selected.has(s)) return;
			options += '<option value="' + ((cur === 'CFA') ? String(Math.round(Number(d))) : Number(d).toFixed(2)) + '"' + (Math.abs(Number(d) - note) < 0.001 ? ' selected' : '') + '>' + formatDenoOption(Number(d)) + '</option>';
		});
		if (!options) {
			options = '<option value="' + ((cur === 'CFA') ? String(Math.round(note || 0)) : (note || 0).toFixed(2)) + '" selected>' + formatDenoOption(note || 0) + '</option>';
		}
		rowsHtml += '<tr data-index="' + index + '">' +
			'<td style="max-width:160px;"><div class="input-group input-group-sm"><span class="input-group-text">' + cur + '</span><select class="form-select text-end bo-deno-note" data-index="' + index + '">' + options + '</select></div></td>' +
			'<td class="text-center" style="max-width:90px;"><input type="number" min="0" step="1" class="form-control form-control-sm text-center bo-deno-count" data-index="' + index + '" value="' + countValue + '"></td>' +
			'<td class="text-end bo-deno-total">' + (cur === 'CFA' ? ('CFA ' + total.toLocaleString(undefined, { maximumFractionDigits: 0 })) : ('GHS ' + total.toFixed(2))) + '</td>' +
			'<td class="text-center"><button type="button" class="btn btn-link text-danger p-0 bo-deno-remove" data-index="' + index + '"><i class="fa fa-trash"></i></button></td>' +
		'</tr>';
	});
	modalDenos.innerHTML = rowsHtml;
	recalcModalAmount();
}

function boReqInitPopovers() {
	if (typeof bootstrap === 'undefined') return;
	if (bootstrap.Popover) {
		var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
		popoverTriggerList.forEach(function (el) {
			try {
				bootstrap.Popover.getInstance(el)?.dispose?.();
			} catch (e) {}
			new bootstrap.Popover(el);
		});
	}
	if (bootstrap.Tooltip) {
		var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
		tooltipTriggerList.forEach(function (el) {
			try {
				bootstrap.Tooltip.getInstance(el)?.dispose?.();
			} catch (e) {}
			new bootstrap.Tooltip(el);
		});
	}
}

function boReqShowMessage(type, message) {
	var box = document.getElementById('bo-req-flash');
	if (!box) return;
	box.className = 'alert small py-2 px-3 mb-2';
	if (type === 'success') {
		box.classList.add('alert-success');
	} else if (type === 'warning') {
		box.classList.add('alert-warning');
	} else {
		box.classList.add('alert-danger');
	}
	box.textContent = message || '';
	box.classList.remove('d-none');
}

function boReqGetUploadProgress() {
	const el = document.getElementById('bo-req-upload-progress-modal');
	if (!el || typeof bootstrap === 'undefined' || !bootstrap.Modal) return null;
	if (!window.boReqUploadProgress) {
		window.boReqUploadProgress = {
			el,
			modal: new bootstrap.Modal(el, { backdrop: 'static', keyboard: false }),
			title: document.getElementById('bo-req-upload-progress-title'),
			bar: document.getElementById('bo-req-upload-progress-bar'),
			status: document.getElementById('bo-req-upload-progress-status'),
		};
	}
	return window.boReqUploadProgress;
}

function boReqSetUploadProgress(percent, status) {
	const ctx = boReqGetUploadProgress();
	if (!ctx || !ctx.bar) return;
	const value = Math.max(0, Math.min(100, Math.round(Number(percent) || 0)));
	ctx.bar.style.width = value + '%';
	ctx.bar.textContent = value + '%';
	ctx.bar.setAttribute('aria-valuenow', String(value));
	if (ctx.status && status) ctx.status.textContent = status;
}

function boReqShowUploadProgress(title) {
	const ctx = boReqGetUploadProgress();
	if (!ctx) return;
	if (ctx.title) ctx.title.textContent = title || 'Submitting approval...';
	boReqSetUploadProgress(0, 'Preparing upload...');
	ctx.modal.show();
}

function boReqHideUploadProgress(delay) {
	const ctx = boReqGetUploadProgress();
	if (!ctx) return;
	setTimeout(function () {
		try { ctx.modal.hide(); } catch (e) {}
	}, Number(delay || 0));
}

function performAction(id, action, extras) {
	if (!id || !action) return;
	function extractFirstJsonObject(rawText) {
		let t = String(rawText || '');
		const splitter = /<!doctype html/i;
		if (splitter.test(t)) {
			t = t.split(splitter)[0];
		}
		const start = t.indexOf('{');
		if (start === -1) return '';
		let depth = 0;
		let inString = false;
		let escaped = false;
		for (let i = start; i < t.length; i++) {
			const ch = t[i];
			if (inString) {
				if (escaped) {
					escaped = false;
					continue;
				}
				if (ch === '\\') {
					escaped = true;
					continue;
				}
				if (ch === '"') {
					inString = false;
				}
				continue;
			}
			if (ch === '"') {
				inString = true;
				continue;
			}
			if (ch === '{') depth++;
			if (ch === '}') {
				depth--;
				if (depth === 0) {
					return t.substring(start, i + 1).trim();
				}
			}
		}
		return '';
	}
	const formData = new FormData();
	formData.append('id', String(id));
	formData.append('action', action);
	if (extras && extras.denos) {
		formData.append('denos', JSON.stringify(extras.denos));
	}
	if (extras && extras.reason) {
		formData.append('reason', extras.reason);
	}
	if (extras && extras.receiptFile) {
		formData.append('approval_receipt_photo', extras.receiptFile);
	}

	const showProgress = action === 'approve';
	if (showProgress) {
		boReqShowUploadProgress('Submitting request approval...');
		boReqSetUploadProgress(2, 'Preparing approval data...');
	}

	const xhr = new XMLHttpRequest();
	xhr.open('POST', boReqActionUrl, true);
	xhr.withCredentials = true;
	xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
	if (showProgress && xhr.upload) {
		xhr.upload.addEventListener('progress', function (event) {
			if (!event.lengthComputable) {
				boReqSetUploadProgress(15, 'Uploading approval details...');
				return;
			}
			const percent = Math.max(3, Math.min(95, Math.round((event.loaded / event.total) * 100)));
			boReqSetUploadProgress(percent, 'Uploading approval details...');
		});
	}
	xhr.addEventListener('load', function () {
		if (showProgress) boReqSetUploadProgress(100, 'Processing server response...');
		const text = xhr.responseText || '';
		if (xhr.status < 200 || xhr.status >= 300) {
			boReqShowMessage('danger', 'Failed to update request. Server returned HTTP ' + xhr.status + '.');
			return;
		}
			const jsonText = extractFirstJsonObject(text);
			let data;
			try {
				data = jsonText ? JSON.parse(jsonText) : null;
			} catch (e) {
				let snippet = (text || '').trim();
				snippet = snippet.length > 180 ? snippet.substring(0, 180) + '...' : snippet;
				boReqShowMessage('danger', 'Failed to update request. Details: ' + (snippet || 'Empty response'));
				return;
			}
			if (!data || !data.success) {
				let msg = data && data.message ? data.message : 'Failed to update request.';
				if (data && data.error) {
					msg += ' Details: ' + data.error;
				}
				boReqShowMessage('danger', msg);
				return;
			}
			boReqShowMessage('success', data.message || 'Request updated.');
			if (typeof window.boReqReload === 'function') {
				window.boReqReload(boReqCurrentPage);
			}
			setTimeout(function () { boReqShowWhatsappNotification(data.whatsapp); }, 150);
	});
	xhr.addEventListener('error', function () {
		boReqShowMessage('danger', 'Failed to update request.');
	});
	xhr.addEventListener('loadend', function () {
		if (showProgress) boReqHideUploadProgress(700);
	});
	xhr.send(formData);
}

function openConfirmModal(id, action) {
	if (!window.boReqModal) return;
	const ctx = window.boReqModal;
	boReqCurrentPage = window.boReqCurrentPage || 1;

	// Reset state
			ctx.modalLoading.classList.remove('d-none');
			ctx.modalContent.classList.add('d-none');
			ctx.modalDenos.innerHTML = '';
			ctx.modalAmount.textContent = formatRequestAmount(0, getRequestCurrency(ctx));
			if (ctx.summaryMain) ctx.summaryMain.textContent = '';
			if (ctx.summaryAmount) ctx.summaryAmount.textContent = '';
			if (ctx.summaryDesc) ctx.summaryDesc.textContent = '';
			boReqRenderAssignedSnapshot(null);
	ctx.btnConfirmApprove.classList.add('d-none');
	ctx.btnConfirmReject.classList.add('d-none');
	ctx.btnConfirmApprove.dataset.id = '';
	ctx.btnConfirmReject.dataset.id = '';
	ctx.currentAction = action;
	ctx.isCashRequest = false;
	ctx.expectedDenos = [];
	ctx.enteredTotal = 0;
	ctx.denos = [];
	ctx.receiptTouched = false;
	if (ctx.cashVerifySection) ctx.cashVerifySection.classList.add('d-none');
	if (ctx.receiptSection) ctx.receiptSection.classList.add('d-none');
	if (ctx.receiptInput) ctx.receiptInput.value = '';
	if (ctx.receiptError) ctx.receiptError.classList.add('d-none');
	const denoMismatchError = document.getElementById('bo-req-confirm-deno-mismatch-error');
	if (denoMismatchError) denoMismatchError.classList.add('d-none');
	const totalError = document.getElementById('bo-req-confirm-error');
	if (totalError) totalError.classList.add('d-none');

	ctx.modalTitle.textContent = (action === 'approve') ? 'Confirm approval' : 'Confirm rejection';
	if (ctx.rejectReasonWrap && ctx.rejectReasonInput) {
		if (action === 'reject') {
			ctx.rejectReasonWrap.classList.remove('d-none');
			ctx.rejectReasonInput.value = '';
		} else {
			ctx.rejectReasonWrap.classList.add('d-none');
			ctx.rejectReasonInput.value = '';
		}
	}

	const url = '<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/request_view_modal?id=' + encodeURIComponent(String(id));
	fetch(url, { credentials: 'same-origin' })
		.then(window.backofficeParseJson)
		.then(data => {
			if (!data || !data.success) {
				ctx.modalContext.textContent = data && data.message ? data.message : 'Unable to load request preview.';
				ctx.modalLoading.classList.add('d-none');
				ctx.modalContent.classList.remove('d-none');
				return;
			}
			const fromName = (data.from || 'N/A');
			const toName = (data.to || 'N/A');
			ctx.modalContext.textContent = 'From ' + fromName + ' to ' + toName;
			if (ctx.summaryMain) {
				const requestLabel = String((data.network || '') + ' - ' + (data.request_type || '')).replace(/^\s*-\s*|\s*-\s*$/g, '').trim();
				ctx.summaryMain.textContent = requestLabel || (data.request_type || '') || (data.network || '');
			}
			ctx.isCashRequest = !!data.is_cash_request;
			ctx.currency = String(data.currency || ((String(data.request_type || '').toUpperCase().includes('CFA')) ? 'CFA' : 'GHS')).toUpperCase();
			if (ctx.summaryAmount) {
				const amt = Number(data.amount || 0);
				ctx.summaryAmount.textContent = formatRequestAmount(amt, ctx.currency);
			}
			if (ctx.summaryDesc) {
				ctx.summaryDesc.textContent = String(data.description || '').trim();
			}
			boReqRenderAssignedSnapshot(data.assigned_snapshot);
			ctx.expectedDenos = Array.isArray(data.expected_denos) ? data.expected_denos.map(function (d) {
				return {
					currency: String(d.currency || ctx.currency || 'GHS'),
					denomination: Number(d.denomination || 0),
					count: Number(d.count || 0),
					volume: Number(d.volume || 0),
				};
			}) : [];
			ctx.denos = Array.isArray(data.denos) ? data.denos.map(function (d) {
					return {
						currency: String(d.currency || ctx.currency || 'GHS'),
						denomination: Number(d.denomination || 0),
						count: Number(d.count || 0),
						volume: Number(d.volume || 0),
					};
				}) : [];
			ctx.originalAmount = Number(data.amount || 0);
			if (ctx.isCashRequest) {
				if (ctx.cashVerifySection) ctx.cashVerifySection.classList.remove('d-none');
				renderDenosTable();
			} else {
				ctx.modalAmount.textContent = formatRequestAmount(ctx.originalAmount, ctx.currency);
			}
			ctx.modalLoading.classList.add('d-none');
			ctx.modalContent.classList.remove('d-none');
			if (action === 'approve') {
				ctx.btnConfirmApprove.classList.remove('d-none');
				ctx.btnConfirmApprove.dataset.id = String(id);
			} else {
				ctx.btnConfirmReject.classList.remove('d-none');
				ctx.btnConfirmReject.dataset.id = String(id);
			}
			boReqUpdateApproveState();
		})
		.catch(error => {
			ctx.modalContext.textContent = error && error.message ? error.message : 'Unable to load request preview.';
			ctx.modalLoading.classList.add('d-none');
			ctx.modalContent.classList.remove('d-none');
		})
		.finally(() => {
			ctx.modal.show();
		});
}

document.addEventListener('DOMContentLoaded', function () {
	const tbody = document.getElementById('bo-req-tbody');
	const pager = document.getElementById('bo-req-pager');
	const searchInput = document.getElementById('bo-req-search');
	const statusSelect = document.getElementById('bo-req-status');
	const scopeSelect = document.getElementById('bo-req-scope');
	const exportPdfBtn = document.getElementById('bo-req-export-pdf');
	const exportXlsxBtn = document.getElementById('bo-req-export-xlsx');
	const metricAmount = document.getElementById('bo-req-metric-amount');
	const metricTrend = document.getElementById('bo-req-metric-trend');
	const metricCount = document.getElementById('bo-req-metric-count');
	const metricPending = document.getElementById('bo-req-metric-pending');
	const metricApproved = document.getElementById('bo-req-metric-approved');
	const metricRejected = document.getElementById('bo-req-metric-rejected');
	const scopeLabel = document.getElementById('bo-req-scope-label');
	const modalEl = document.getElementById('bo-req-confirm-modal');
	const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
	const modalTitle = document.getElementById('bo-req-confirm-title');
	const modalLoading = document.getElementById('bo-req-confirm-loading');
	const modalContent = document.getElementById('bo-req-confirm-content');
	const modalContext = document.getElementById('bo-req-confirm-context');
	const summaryMain = document.getElementById('bo-req-confirm-summary-main');
	const summaryAmount = document.getElementById('bo-req-confirm-summary-amount');
	const summaryDesc = document.getElementById('bo-req-confirm-summary-desc');
	const assignedDetails = document.getElementById('bo-req-confirm-assigned-details');
	const cashVerifySection = document.getElementById('bo-req-cash-verify-section');
	const modalDenos = document.getElementById('bo-req-confirm-denos');
	const modalAmount = document.getElementById('bo-req-confirm-amount');
	const btnConfirmApprove = document.getElementById('bo-req-confirm-approve');
	const btnConfirmReject = document.getElementById('bo-req-confirm-reject');
	const btnAddDeno = document.getElementById('bo-req-add-deno');
	const receiptSection = document.getElementById('bo-req-receipt-section');
	const receiptInput = document.getElementById('bo-req-approval-receipt');
	const receiptError = document.getElementById('bo-req-approval-receipt-error');
	const rejectReasonWrap = document.getElementById('bo-req-reject-reason-wrap');
	const rejectReasonInput = document.getElementById('bo-req-reject-reason');
	if (!tbody || !pager || !searchInput || !statusSelect || !scopeSelect) return;
	if (modalEl && rejectReasonInput) {
		modalEl.addEventListener('shown.bs.modal', function () {
			if (rejectReasonWrap && !rejectReasonWrap.classList.contains('d-none')) {
				setTimeout(function () {
					try { rejectReasonInput.focus(); } catch (e) {}
				}, 50);
			}
		});
	}

	// Store modal context globally so openConfirmModal can use it
	window.boReqModal = {
		modal,
		modalTitle,
		modalLoading,
		modalContent,
		modalContext,
		summaryMain,
		summaryAmount,
		summaryDesc,
		assignedDetails,
		cashVerifySection,
		modalDenos,
		modalAmount,
		btnConfirmApprove,
		btnConfirmReject,
		receiptSection,
		receiptInput,
		receiptError,
		rejectReasonWrap,
		rejectReasonInput,
		btnAddDeno,
		currency: 'GHS',
		currentAction: '',
		isCashRequest: false,
		expectedDenos: [],
		enteredTotal: 0,
		receiptTouched: false,
		denos: [],
	};
	window.boReqCurrentPage = 1;

	const baseUrl = '<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/requests_data';
	const isAdminViewer = <?= user_can('is_admin') ? 'true' : 'false' ?>;
	let currentPage = 1;
	let isLoading = false;

	const exportBaseUrl = '<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/export';

	const buildExportParams = function() {
		const params = new URLSearchParams();
		params.set('entity', 'requests');
		params.set('resource', 'list');
		const term = searchInput.value.trim();
		if (term) params.set('find', term);
		const status = statusSelect.value.trim();
		if (status) params.set('status', status);
		const scope = scopeSelect.value.trim();
		if (scope) params.set('scope', scope);
		return params;
	};

	if (exportPdfBtn) {
		exportPdfBtn.addEventListener('click', function(){
			const params = buildExportParams();
			params.set('format', 'pdf');
			window.location.href = exportBaseUrl + '?' + params.toString();
		});
	}
	if (exportXlsxBtn) {
		exportXlsxBtn.addEventListener('click', function(){
			const params = buildExportParams();
			params.set('format', 'xlsx');
			window.location.href = exportBaseUrl + '?' + params.toString();
		});
	}

	function escapeHtml(str) {
		return String(str ?? '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function formatAmount(value) {
		const n = Number(value || 0);
		return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	}

	function renderStatusBadge(status) {
		const s = String(status || '').toLowerCase();
		let cls = 'badge bg-secondary';
		if (s === 'pending' || s === 'draft') cls = 'badge bg-warning text-dark';
		else if (s === 'approved') cls = 'badge bg-success';
		else if (s === 'rejected') cls = 'badge bg-danger';
		return '<span class="' + cls + '">' + escapeHtml(status || '') + '</span>';
	}

	function updateMetrics(totals, pagination) {
		if (!totals) return;
		if (metricAmount) {
			const total = Number(totals.total_amount ?? 0);
			const cfa = Number(totals.total_amount_cfa ?? 0);
			let ghs;
			if (totals.total_amount_ghs === null || totals.total_amount_ghs === undefined) {
				ghs = total;
			} else {
				ghs = Number(totals.total_amount_ghs ?? 0);
				// If backend didn't provide a useful split (0) but total exists and there is no CFA,
				// fall back to total to avoid showing 0.00 incorrectly.
				if (ghs === 0 && total > 0 && cfa === 0) {
					ghs = total;
				}
			}
			let text = 'GHS ' + formatAmount(ghs);
			if (cfa > 0) {
				text += '  CFA ' + cfa.toLocaleString(undefined, { maximumFractionDigits: 0 });
			}
			metricAmount.textContent = text;
		}
		if (metricCount) metricCount.textContent = String((pagination && pagination.total_rows) || 0);
		if (metricPending) metricPending.textContent = String(totals.pending ?? 0);
		if (metricApproved) metricApproved.textContent = String(totals.approved ?? 0);
		if (metricRejected) metricRejected.textContent = String(totals.rejected ?? 0);

		const tabBadge = document.getElementById('bo-tab-requests-badge');
		if(tabBadge){
			const pending = Number(totals.pending ?? 0);
			tabBadge.textContent = pending ? String(pending) : '';
			tabBadge.style.display = pending ? '' : 'none';
		}

		// Trend vs yesterday (amount)
		if (metricTrend) {
			const today = Number(totals.total_amount ?? 0);
			const prev = Number(totals.prev_total_amount ?? 0);
			if (!prev && !today) {
				metricTrend.textContent = 'vs yesterday: –';
			} else if (!prev) {
				metricTrend.innerHTML = '<span class="text-success">▲</span> vs yesterday';
			} else {
				const delta = today - prev;
				const pct = prev ? (delta / prev) * 100 : 0;
				const arrow = delta >= 0 ? '<span class="text-success">▲</span>' : '<span class="text-danger">▼</span>';
				metricTrend.innerHTML = arrow + ' ' + Math.abs(pct).toFixed(1) + '% vs yesterday';
			}
		}

		// Effective scope label
		if (scopeLabel) {
			let scopeText = 'Created & Assigned';
			const s = (scopeSelect?.value || '').toLowerCase();
			if (s === 'all' && isAdminViewer) scopeText = 'All requests';
			if (s === 'created') scopeText = 'Created by me';
			else if (s === 'assigned') scopeText = 'Assigned to me';

			let statusText = 'All statuses';
			const st = (statusSelect?.value || '').toLowerCase();
			if (st) statusText = st.charAt(0).toUpperCase() + st.slice(1);

			scopeLabel.textContent = scopeText + ' | ' + statusText;
		}
	}

	function buildRow(row, index) {
		const idBadge = '<span class="badge bg-warning text-black rounded-circle pill">' + index + '</span>';
		const viewUrl = '<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view_request/' + row.id;
		const editUrl = '<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit_request/' + row.id;
		const deleteUrl = '<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/delete/' + row.id;
		const createdImg = row.created_by?.image || '';
		const createdName = row.created_by?.name || '';
		const assignedImg = row.assigned_to?.image || '';
		const assignedName = row.assigned_to?.name || '';
		const canEdit = !!row.can_edit;
		const canAct = !!row.can_act;
		const statusLower = String(row.status || '').toLowerCase();
		const canDelete = (statusLower === 'pending' || statusLower === 'draft') && <?= json_encode(user_can('delete_request')) ?>;

		const actionItems = [];
		if (canAct) {
			actionItems.push('<button type="button" class="btn btn-outline-success btn-sm bo-req-action" data-id="' + row.id + '" data-action="approve" data-bs-toggle="tooltip" title="Approve" aria-label="Approve"><i class="fa fa-check"></i></button>');
			actionItems.push('<button type="button" class="btn btn-outline-danger btn-sm bo-req-action" data-id="' + row.id + '" data-action="reject" data-bs-toggle="tooltip" title="Reject" aria-label="Reject"><i class="fa fa-times"></i></button>');
		}
		actionItems.push('<a class="btn btn-outline-warning btn-sm" href="' + viewUrl + '" data-bs-toggle="tooltip" title="Details" aria-label="Details"><i class="fa fa-eye"></i></a>');
		if (canEdit) {
			actionItems.push('<a class="btn btn-outline-warning btn-sm" href="' + editUrl + '" data-bs-toggle="tooltip" title="Update" aria-label="Update"><i class="fa fa-pen"></i></a>');
		}
		if (canDelete) {
			actionItems.push('<a class="btn btn-outline-danger btn-sm" href="' + deleteUrl + '" data-bs-toggle="tooltip" title="Delete" aria-label="Delete"><i class="fa fa-trash"></i></a>');
		}

		const actions = '<div class="d-inline-flex flex-wrap gap-1 justify-content-end">' + actionItems.join('') + '</div>';

		const reason = (row.reject_reason || '').trim();
		const statusHtml = renderStatusBadge(row.status) + (row.status && row.status.toLowerCase() === 'rejected' && reason
			? ' <i class="fa fa-info-circle text-muted ms-1" tabindex="0" data-bs-toggle="popover" data-bs-trigger="focus hover" data-bs-placement="top" data-bs-html="true" data-bs-title="Reject reason" data-bs-content="' + escapeHtml(reason) + '"></i>'
			: '');

		const reqTypeLabel = escapeHtml((row.network || '') + ' - ' + (row.request_type || ''));
		const createdAt = escapeHtml(row.created_at || '');
		const transId = escapeHtml(row.id || '');
		const transDateAndId = '<div><a href="' + viewUrl + '">' + transId + '</a></div>'
			+ '<small class="text-muted bo-desc-text">' + createdAt + '</small>';
		const desc = escapeHtml(row.description || '');
		const amount = formatAmount(row.amount);
		const desktopDescription = '<div><a href="' + viewUrl + '">' + reqTypeLabel + '</a></div>'
			+ '<div class="text-muted fw-semibold bo-desc-text">' + desc + '</div>';
		const desktopParties = '<div class="d-flex align-items-center gap-2 flex-nowrap mb-1"><img src="' + escapeHtml(createdImg) + '" style="width: 20px; height: 20px; object-fit: cover; border-radius: 50%;"><code class="mb-0">From:</code> <span class="text-nowrap">' + escapeHtml(createdName) + '</span></div>'
			+ '<div class="d-flex align-items-center gap-2 flex-nowrap"><code class="mb-0"><img src="' + escapeHtml(assignedImg) + '" style="width: 15px; height: 15px; object-fit: cover; border-radius: 50%;"> To:</code> <span class="text-nowrap">' + escapeHtml(assignedName) + '</span></div>';

		return '<tr>'
			+ '<td class="d-lg-none align-top" style="width:1%">' + idBadge + '</td>'
			+ '<td class="d-lg-none align-top">'
				+ '<div class="bo-stack-cell">'
					+ '<div class="line"><a href="' + viewUrl + '">' + reqTypeLabel + '</a></div>'
					+ '<div class="line"><span class="k">Date</span><span>' + transDateAndId + '</span></div>'
					+ '<div class="line"><span class="k">Status</span><span>' + statusHtml + '</span></div>'
					+ '<div class="line"><span class="k">Desc</span><span>' + desc + '</span></div>'
					+ '<div class="line"><span class="k">Amount</span><span class="fw-semibold">' + amount + '</span></div>'
					+ '<div class="line"><span class="k">From</span><span>' + escapeHtml(createdName) + '</span></div>'
					+ '<div class="line"><span class="k">To</span><span>' + escapeHtml(assignedName) + '</span></div>'
				+ '</div>'
			+ '</td>'
			+ '<td class="d-lg-none text-end align-top" style="width:1%">' + actions + '</td>'
			+ '<td class="d-none d-lg-table-cell">' + idBadge + '</td>'
			+ '<td class="d-none d-lg-table-cell">' + transDateAndId + '</td>'
			+ '<td class="d-none d-lg-table-cell bo-desc-cell">' + desktopDescription + '</td>'
			+ '<td class="d-none d-lg-table-cell">' + statusHtml + '</td>'
			+ '<td class="d-none d-lg-table-cell">' + amount + '</td>'
			+ '<td class="d-none d-lg-table-cell">' + desktopParties + '</td>'
			+ '<td class="d-none d-lg-table-cell">' + actions + '</td>'
			+ '</tr>';
	}

	function buildPager(pagination) {
		const totalPages = Number(pagination && pagination.total_pages ? pagination.total_pages : 0);
		if (totalPages <= 1) return '';

		const currentPage = Math.min(Math.max(Number(pagination.page || 1), 1), totalPages);
		const pages = [];
		const seen = {};
		const pushPage = function (p) {
			if (p >= 1 && p <= totalPages && !seen[p]) {
				seen[p] = true;
				pages.push(p);
			}
		};
		const addRange = function (start, end) {
			for (let p = start; p <= end; p++) {
				pushPage(p);
			}
		};

		if (totalPages <= 6) {
			addRange(1, totalPages);
		} else {
			const firstEnd = Math.min(currentPage + 2, totalPages);
			const lastStart = Math.max(totalPages - 2, 1);
			addRange(currentPage, firstEnd);
			if (firstEnd < lastStart - 1) {
				pages.push('ellipsis');
			}
			addRange(lastStart, totalPages);
		}

		let html = '<nav aria-label="Requests pagination"><ul class="pagination pagination-sm justify-content-end mb-0">';
		const addButton = function (label, page, disabled, active) {
			if (disabled) {
				html += '<li class="page-item disabled"><span class="page-link">' + label + '</span></li>';
				return;
			}
			const activeClass = active ? ' active' : '';
			html += '<li class="page-item' + activeClass + '">'
				+ '<a class="page-link" href="#" data-page="' + page + '">' + label + '</a>'
				+ '</li>';
		};
		const addEllipsis = function () {
			html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
		};

		addButton('First', 1, currentPage <= 1, false);
		addButton('&laquo;', currentPage - 1, currentPage <= 1, false);
		let previousPage = 0;
		pages.forEach(function (page) {
			if (page === 'ellipsis') {
				addEllipsis();
				previousPage = 0;
				return;
			}
			if (previousPage && page - previousPage > 1) {
				addEllipsis();
			}
			addButton(String(page), page, false, page === currentPage);
			previousPage = page;
		});
		addButton('&raquo;', currentPage + 1, currentPage >= totalPages, false);
		addButton('Last', totalPages, currentPage >= totalPages, false);

		html += '</ul></nav>';
		return html;
	}

	function setLoading(state) {
		isLoading = state;
		if (state) {
			tbody.innerHTML = '<tr><td colspan="10" class="text-center justify-content-center">Loading requests...</td></tr>';
		}
	}

	function loadRequests(page = 1) {
		if (isLoading) return;
		currentPage = page;
		boReqCurrentPage = page;
		setLoading(true);
		const params = new URLSearchParams();
		params.set('page', String(page));
		params.set('limit', '10');
		const term = searchInput.value.trim();
		if (term) params.set('find', term);
		const status = statusSelect.value.trim();
		if (status) params.set('status', status);
		const scope = scopeSelect.value.trim();
		if (scope) params.set('scope', scope);

		fetch(baseUrl + '?' + params.toString(), {
			credentials: 'same-origin',
			headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
		})
			.then(window.backofficeParseJson)
			.then(data => {
				if (!data || !data.success) {
					tbody.innerHTML = '<tr><td colspan="10" class="text-center justify-content-center">' + escapeHtml(data.message || data.error || 'Failed to load requests.') + '</td></tr>';
					pager.innerHTML = '';
					return;
				}
				try {
					const rows = Array.isArray(data.rows) ? data.rows : [];
					if (!rows.length) {
						tbody.innerHTML = '<tr><td colspan="10" class="text-center justify-content-center">No records to show!</td></tr>';
					} else {
						let html = '';
						rows.forEach((row, idx) => {
							html += buildRow(row, (page - 1) * 10 + idx + 1);
						});
						tbody.innerHTML = html;
					}
					pager.innerHTML = buildPager(data.pagination || {});
					updateMetrics(data.totals || {}, data.pagination || {});
					boReqInitPopovers();
				} catch (e) {
					console.error('Requests render error', e);
				}
			})
			.catch(() => {
				tbody.innerHTML = '<tr><td colspan="10" class="text-center justify-content-center">Failed to load requests.</td></tr>';
				pager.innerHTML = '';
			})
			.finally(() => {
				isLoading = false;
			});
	}

	let searchTimeout = null;
	searchInput.addEventListener('input', function () {
		if (searchTimeout) clearTimeout(searchTimeout);
		searchTimeout = setTimeout(() => loadRequests(1), 350);
	});
	statusSelect.addEventListener('change', function () {
		loadRequests(1);
	});
	scopeSelect.addEventListener('change', function () {
		loadRequests(1);
	});

	pager.addEventListener('click', function (e) {
		const target = e.target;
		if (target && target.matches('a.page-link[data-page]')) {
			e.preventDefault();
			const p = parseInt(target.getAttribute('data-page') || '1', 10) || 1;
			loadRequests(p);
		}
	});

	tbody.addEventListener('click', function (e) {
		const btn = e.target ? e.target.closest('.bo-req-action') : null;
		if (!btn) return;
		const id = parseInt(btn.getAttribute('data-id') || '0', 10) || 0;
		const action = btn.getAttribute('data-action') || '';
		openConfirmModal(id, action);
	});

	if (btnConfirmApprove) {
		btnConfirmApprove.addEventListener('click', function () {
			const id = parseInt(this.dataset.id || '0', 10) || 0;
			if (!id) return;
			if (!boReqUpdateApproveState()) {
				return;
			}
			const receiptFile = (window.boReqModal && window.boReqModal.receiptInput && window.boReqModal.receiptInput.files)
				? (window.boReqModal.receiptInput.files[0] || null)
				: null;
			modal.hide();
			const denos = (window.boReqModal && Array.isArray(window.boReqModal.denos)) ? window.boReqModal.denos : [];
			performAction(id, 'approve', { denos, receiptFile });
		});
	}
	if (btnConfirmReject) {
		btnConfirmReject.addEventListener('click', function () {
			const id = parseInt(this.dataset.id || '0', 10) || 0;
			if (!id) return;
			modal.hide();
			let reason = '';
			if (rejectReasonInput) {
				reason = String(rejectReasonInput.value || '').trim();
			}
			if (!reason) {
				alert('Please provide a brief reason for rejecting this request.');
				return;
			}
			performAction(id, 'reject', { reason });
		});
	}

	// Denomination table interactions: add, edit note/count, remove
	if (btnAddDeno) {
		btnAddDeno.addEventListener('click', function () {
			if (!window.boReqModal) return;
			if (!Array.isArray(window.boReqModal.denos)) window.boReqModal.denos = [];
			var cur = String(window.boReqModal.currency || 'GHS').toUpperCase();
			var DENOS = (cur === 'CFA')
				? [10000, 5000, 2000, 1000, 500, 200, 100, 50, 20, 10]
				: [200, 100, 50, 20, 10, 5, 2, 1, 0.5, 0.2, 0.1];
			var used = new Set();
			window.boReqModal.denos.forEach(function(it){
				var v = Number(it.denomination || 0);
				if (v > 0) used.add(String(v));
			});
			var next = 0;
			for (var i = 0; i < DENOS.length; i++) {
				var d = Number(DENOS[i]);
				if (!used.has(String(d))) { next = d; break; }
			}
			window.boReqModal.denos.push({
				currency: cur,
				denomination: next,
				count: '',
				volume: 0,
			});
			renderDenosTable();
		});
	}

	if (modalDenos) {
		modalDenos.addEventListener('input', function (e) {
			const target = e.target;
			if (!target) return;
			const idx = parseInt(target.getAttribute('data-index') || '0', 10) || 0;
			if (!window.boReqModal || !Array.isArray(window.boReqModal.denos)) return;
			const item = window.boReqModal.denos[idx];
			if (!item) return;
			if (target.classList.contains('bo-deno-note')) {
				const note = Math.max(0, parseFloat(target.value || '0') || 0);
				item.denomination = note;
				item.volume = note * Number(item.count || 0);
				renderDenosTable();
				return;
			} else if (target.classList.contains('bo-deno-count')) {
				const rawValue = String(target.value || '').trim();
				const count = rawValue === '' ? '' : Math.max(0, parseInt(rawValue, 10) || 0);
				item.count = count;
				item.volume = Number(item.denomination || 0) * Number(count || 0);
			}
			// Update the specific row total
			const row = target.closest('tr');
			if (row) {
				const totalCell = row.querySelector('.bo-deno-total');
				if (totalCell) {
					totalCell.textContent = formatRequestAmount(Number(item.volume || 0), window.boReqModal && window.boReqModal.currency ? window.boReqModal.currency : 'GHS');
				}
			}
			// Also recalculate overall request amount from all rows
			recalcModalAmount();
		});

		modalDenos.addEventListener('click', function (e) {
			const target = e.target.closest('.bo-deno-remove');
			if (!target) return;
			const idx = parseInt(target.getAttribute('data-index') || '0', 10) || 0;
			if (!window.boReqModal || !Array.isArray(window.boReqModal.denos)) return;
			if (idx < 0 || idx >= window.boReqModal.denos.length) return;
			window.boReqModal.denos.splice(idx, 1);
			renderDenosTable();
		});
	}

	if (receiptInput) {
		receiptInput.addEventListener('change', function () {
			if (window.boReqModal) {
				window.boReqModal.receiptTouched = true;
			}
			boReqUpdateApproveState();
		});
	}

	// Expose reload so performAction can refresh from global scope
	window.boReqReload = loadRequests;

	// Initial load to hydrate with fresh data
	loadRequests(1);
});
</script>
