<?php
$session = get_session();
$currentUserId = (int)($session->id ?? 0);
$statusOptions = [];
$typeOptions = [];
$bankOptions = [];

foreach (($bankTransfers ?? []) as $transfer) {
	$statusLabel = trim((string)($transfer->transaction_status ?? ''));
	$typeLabel = trim((string)($transfer->transaction_type ?? ''));
	$bankLabel = trim((string)($transfer->bank_name ?? ''));

	if ($statusLabel !== '') $statusOptions[strtolower($statusLabel)] = $statusLabel;
	if ($typeLabel !== '') $typeOptions[strtolower($typeLabel)] = $typeLabel;
	if ($bankLabel !== '') $bankOptions[strtolower($bankLabel)] = $bankLabel;
}

asort($statusOptions);
asort($typeOptions);
asort($bankOptions);
?>

<style>
	#bo-bank-table{
		min-width: 980px;
	}
	#bo-bank-table thead th{
		text-transform: uppercase;
		font-size: .75rem;
		letter-spacing: .06em;
		background: rgba(var(--bs-secondary-rgb,148,163,184),.1);
		border-top: 0;
	}
	#bo-bank-table tbody tr{
		white-space: nowrap;
	}
	#bo-bank-table tbody tr:hover{
		background: rgba(var(--bs-warning-rgb,255,193,7),.06);
	}
	#bo-bank-table .wrap{
		white-space: normal;
	}
	.bo-bank-mobile-meta{
		display:none;
	}
	.bo-bank-mobile-index{
		display:none;
	}
	#bo-bank-toolbar .input-group-text{
		border: 0;
	}
	#bo-bank-toolbar .form-control,
	#bo-bank-toolbar .form-select{
		border-color: var(--bs-border-color);
	}
	#bo-bank-metrics .metric-card{
		border: none;
		background: linear-gradient(135deg, #facc15 0%, #fed7aa 40%, #fff7ed 100%);
		box-shadow: 0 4px 14px rgba(0,0,0,.08);
		color: #111827;
	}
	#bo-bank-metrics .metric-card.metric-accent{
		background: linear-gradient(135deg, #0f172a 0%, #1e293b 45%, #020617 100%);
		color: #f9fafb;
	}
	#bo-bank-metrics .metric-label{
		font-size: .7rem;
		letter-spacing: .08em;
		text-transform: uppercase;
		color: #111827 !important;
	}
	body[data-bs-theme="dark"] #bo-bank-metrics .metric-label,
	body.dark-mode #bo-bank-metrics .metric-label{
		color: #000000 !important;
	}
	#bo-bank-metrics .metric-trend{
		font-size: .7rem;
		color: #1f2933 !important;
	}
	@media (min-width:768px) and (max-width:991.98px){
		#bo-bank-metrics .metric-card{padding:.75rem !important;}
		#bo-bank-metrics .fw-semibold{font-size:1.05rem;}
		#bo-bank-metrics .metric-trend{display:none;}
	}
	@media (max-width: 991.98px){
		#bo-bank-table{
			min-width:0;
			table-layout:fixed;
			width:100%;
		}
		#bo-bank-table .bo-bank-index-col,
		#bo-bank-table .bo-bank-type-col,
		#bo-bank-table .bo-bank-second-party-col,
		#bo-bank-table .bo-bank-third-party-col,
		#bo-bank-table .bo-bank-status-col{
			display:none;
		}
		#bo-bank-table .bo-bank-mobile-index{
			display:inline-flex;
			align-items:center;
			justify-content:center;
			min-width:1.55rem;
			height:1.55rem;
			margin-bottom:.35rem;
		}
		#bo-bank-table .bo-bank-mobile-meta{
			display:block;
			margin-top:.25rem;
			font-size:.78rem;
			color:var(--bs-secondary-color);
			white-space:normal;
			line-height:1.35;
		}
		#bo-bank-table th:nth-child(2),
		#bo-bank-table td:nth-child(2){
			width:28%;
		}
		#bo-bank-table th:nth-child(4),
		#bo-bank-table td:nth-child(4){
			width:34%;
		}
		#bo-bank-table th:nth-child(5),
		#bo-bank-table td:nth-child(5){
			width:19%;
		}
		#bo-bank-table th:nth-child(9),
		#bo-bank-table td:nth-child(9){
			width:19%;
		}
		#bo-bank-table th,
		#bo-bank-table td{
			white-space:normal;
			vertical-align:top;
			overflow-wrap:anywhere;
			word-break:normal;
		}
		#bo-bank-table .btn-sm{
			padding:.25rem .45rem;
		}
	}
	@media (max-width: 420px){
		#bo-bank-table .bo-bank-open-text{
			display:none;
		}
		#bo-bank-table .btn-sm{
			padding:.25rem .4rem;
		}
	}
</style>

<div id="bo-bank-toolbar" class="row my-3 g-2 align-items-center">
	<div class="col-lg-4">
		<div class="input-group input-group-sm">
			<span class="input-group-text bg-body-secondary"><i class="fa fa-search text-muted"></i></span>
			<input type="text" id="bo-bank-search" class="form-control" placeholder="Search bank transfers by id, bank, party or status...">
		</div>
	</div>
	<div class="col-lg-5">
		<div class="d-flex flex-wrap gap-2">
			<select id="bo-bank-status" class="form-select form-select-sm">
				<option value="">All statuses</option>
				<?php foreach($statusOptions as $statusKey => $statusLabel): ?>
					<option value="<?=esc($statusKey)?>"><?=esc($statusLabel)?></option>
				<?php endforeach; ?>
			</select>
			<select id="bo-bank-type" class="form-select form-select-sm">
				<option value="">All types</option>
				<?php foreach($typeOptions as $typeKey => $typeLabel): ?>
					<option value="<?=esc($typeKey)?>"><?=esc($typeLabel)?></option>
				<?php endforeach; ?>
			</select>
			<select id="bo-bank-name" class="form-select form-select-sm">
				<option value="">All banks</option>
				<?php foreach($bankOptions as $bankKey => $bankLabel): ?>
					<option value="<?=esc($bankKey)?>"><?=esc($bankLabel)?></option>
				<?php endforeach; ?>
			</select>
		</div>
	</div>
	<div class="col-lg-3 text-lg-end">
		<div class="d-flex justify-content-lg-end gap-2 flex-wrap">
			<button type="button" id="bo-bank-export-pdf" class="btn btn-outline-secondary btn-sm">Export PDF</button>
			<?php if(user_can('add_payment')): ?>
				<a class="btn btn-warning btn-sm" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/make_bank_transfer">
					<i class="fa-solid fa-plus"></i> Add TO &amp; FROM
				</a>
			<?php endif; ?>
		</div>
	</div>
</div>

<div id="bo-bank-metrics" class="row g-2 mb-1 small align-items-stretch">
	<div class="col-12 col-sm-6 col-lg-3">
		<div class="p-2 rounded-3 metric-card h-100 d-flex flex-column justify-content-center">
			<div class="d-flex justify-content-between align-items-center mb-1">
				<div class="metric-label text-muted">Total amount</div>
				<i class="fa fa-building-columns text-warning"></i>
			</div>
			<div class="d-flex justify-content-between align-items-end">
				<div class="fw-semibold" id="bo-bank-metric-amount">–</div>
				<div class="metric-trend text-muted" id="bo-bank-metric-trend">vs yesterday: –</div>
			</div>
		</div>
	</div>
	<div class="col-12 col-sm-6 col-lg-3">
		<div class="p-2 rounded-3 bg-body border h-100 d-flex flex-column justify-content-center metric-card">
			<div class="d-flex justify-content-between align-items-center mb-1">
				<div class="metric-label text-muted">Total transfers</div>
				<i class="fa fa-list-check text-secondary"></i>
			</div>
			<div class="fw-semibold" id="bo-bank-metric-count">–</div>
		</div>
	</div>
	<div class="col-4 col-sm-4 col-lg-2">
		<div class="p-2 rounded-3 bg-body border h-100 d-flex flex-column justify-content-center metric-card">
			<div class="metric-label text-muted">Pending</div>
			<div class="fw-semibold text-secondary" id="bo-bank-metric-pending">–</div>
		</div>
	</div>
	<div class="col-4 col-sm-4 col-lg-2">
		<div class="p-2 rounded-3 bg-body border h-100 d-flex flex-column justify-content-center metric-card">
			<div class="metric-label text-muted">Approved</div>
			<div class="fw-semibold text-success" id="bo-bank-metric-approved">–</div>
		</div>
	</div>
	<div class="col-12 col-sm-4 col-lg-2">
		<div class="p-2 rounded-3 bg-body border h-100 d-flex flex-column justify-content-center metric-card metric-accent">
			<div class="metric-label">Rejected</div>
			<div class="fw-semibold text-danger mb-1" id="bo-bank-metric-rejected">–</div>
			<div class="text-end" style="font-size:.7rem;"><span id="bo-bank-scope-label" class="text-uppercase text-warning"></span></div>
		</div>
	</div>
</div>

<div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2">
	<div id="bo-bank-results" class="small text-muted"></div>
</div>

<div class="table-responsive">
	<table class="table table-striped table-hover" id="bo-bank-table">
		<thead>
			<tr>
				<th class="bo-bank-index-col">#</th>
				<th>Date</th>
				<th class="bo-bank-type-col">Type</th>
				<th>Category / Detail</th>
				<th>Amount</th>
				<th class="bo-bank-second-party-col">2nd Party</th>
				<th class="bo-bank-third-party-col">3rd Party</th>
				<th class="bo-bank-status-col">Status</th>
				<th>Action</th>
			</tr>
		</thead>
		<tbody id="bo-bank-tbody">
			<?php if(!empty($bankTransfers)): ?>
				<?php foreach($bankTransfers as $index => $row): ?>
					<?php
						$status = trim((string)($row->transaction_status ?? 'Pending 2nd Party'));
						$statusLower = strtolower($status);
						$badgeClass = 'badge bg-secondary';
						if ($statusLower === 'approved') $badgeClass = 'badge bg-success';
						elseif ($statusLower === 'rejected') $badgeClass = 'badge bg-danger';
						elseif (strpos($statusLower, 'pending') !== false) $badgeClass = 'badge bg-warning text-dark';
						$detail = trim((string)($row->transfer_category ?? ''));
						if ($detail === '') $detail = trim((string)($row->bank_name ?? ''));
						if ($detail !== '' && !empty($row->network_name)) {
							$detail .= ' / ' . trim((string)$row->network_name);
						}
						$rejectReason = trim((string)($row->third_party_reject_reason ?? ''));
						$secondPartyName = trim((string)(($row->second_party_first_name ?? '') . ' ' . ($row->second_party_last_name ?? '')));
						$thirdPartyName = trim((string)(($row->third_party_first_name ?? '') . ' ' . ($row->third_party_last_name ?? '')));
						$withdrawerName = trim((string)(($row->withdrawer_first_name ?? '') . ' ' . ($row->withdrawer_last_name ?? '')));
						$creatorName = trim((string)(($row->creator_first_name ?? '') . ' ' . ($row->creator_last_name ?? '')));
						$searchText = implode(' ', array_filter([
							(string)($row->id ?? ''),
							(string)($row->transaction_type ?? ''),
							$detail,
							(string)($row->bank_name ?? ''),
							(string)($row->network_name ?? ''),
							number_format((float)($row->amount ?? 0), 2),
							$status,
							$creatorName,
							$secondPartyName,
							$thirdPartyName,
							$withdrawerName,
							$rejectReason,
							(string)($row->date_created ?? ''),
						], static fn($value) => trim((string)$value) !== ''));
					?>
					<tr
						data-row="1"
						data-search="<?=esc(strtolower($searchText))?>"
						data-status="<?=esc($statusLower)?>"
						data-type="<?=esc(strtolower(trim((string)($row->transaction_type ?? ''))))?>"
						data-bank="<?=esc(strtolower(trim((string)($row->bank_name ?? ''))))?>"
						data-amount="<?=esc((string)((float)($row->amount ?? 0)))?>"
						data-date="<?=esc(substr((string)($row->date_created ?? ''), 0, 10))?>"
					>
						<td class="bo-bank-index-col"><span class="badge bg-warning text-black rounded-circle pill"><?=esc((string)($index + 1))?></span></td>
						<td>
							<span class="badge bg-warning text-black rounded-circle pill bo-bank-mobile-index"><?=esc((string)($index + 1))?></span>
							<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view_bank_transfer/<?=esc((string)$row->id)?>">
								<?=esc(get_date_time($row->date_created ?? ''))?>
							</a>
							<div class="small text-muted mt-1">
								<i class="fa-regular fa-user me-1"></i>
								<?=esc($creatorName !== '' ? $creatorName : 'Unknown creator')?>
							</div>
							<div class="bo-bank-mobile-meta">
								<div><strong>2nd party:</strong> <?=esc($secondPartyName !== '' ? $secondPartyName : 'N/A')?></div>
								<div><strong>3rd party:</strong>
									<?php if(backoffice_bank_transfer_is_from_bank_flow((string)($row->transaction_type ?? ''))): ?>
										<?=esc($withdrawerName !== '' ? $withdrawerName : 'N/A')?>
									<?php else: ?>
										<?=esc($thirdPartyName !== '' ? $thirdPartyName : 'N/A')?>
									<?php endif; ?>
								</div>
							</div>
						</td>
						<td class="wrap bo-bank-type-col"><?=esc((string)($row->transaction_type ?? ''))?></td>
						<td class="wrap">
							<div class="fw-semibold"><?=esc($detail)?></div>
							<div class="small text-muted"><?=esc((string)($row->bank_name ?? ''))?></div>
							<div class="bo-bank-mobile-meta">
								<div><strong>Type:</strong> <?=esc((string)($row->transaction_type ?? ''))?></div>
								<div>
									<strong>Status:</strong> <span class="<?=$badgeClass?>"><?=esc($status)?></span>
								</div>
								<?php if($statusLower === 'rejected' && $rejectReason !== ''): ?>
									<div class="text-danger">Reason: <?=esc($rejectReason)?></div>
								<?php endif; ?>
							</div>
						</td>
						<td><?=number_format((float)($row->amount ?? 0), 2)?></td>
						<td class="wrap bo-bank-second-party-col">
							<div><?=esc($secondPartyName)?></div>
							<div class="small text-muted"><?=esc((string)($row->second_party_status ?? 'Pending'))?></div>
						</td>
						<td class="wrap bo-bank-third-party-col">
							<?php if(backoffice_bank_transfer_is_from_bank_flow((string)($row->transaction_type ?? ''))): ?>
								<div><?=esc($withdrawerName)?></div>
								<div class="small text-muted"><?=esc((string)($row->withdrawal_status ?? 'Pending'))?></div>
							<?php else: ?>
								<div><?=esc($thirdPartyName)?></div>
								<div class="small text-muted"><?=esc((string)($row->third_party_status ?? 'Pending'))?></div>
							<?php endif; ?>
						</td>
						<td class="wrap bo-bank-status-col">
							<span class="<?=$badgeClass?>"><?=esc($status)?></span>
							<?php if($statusLower === 'rejected' && $rejectReason !== ''): ?>
								<div class="small text-danger mt-1">Reason: <?=esc($rejectReason)?></div>
							<?php endif; ?>
						</td>
						<td>
							<a class="btn btn-outline-secondary btn-sm" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view_bank_transfer/<?=esc((string)$row->id)?>">
								<i class="fa fa-eye"></i><span class="bo-bank-open-text"> Open</span>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
				<tr id="bo-bank-empty-row" style="display:none;">
					<td colspan="9" class="text-center">No TO &amp; FROM records match the current filters.</td>
				</tr>
			<?php else: ?>
				<tr id="bo-bank-empty-row">
					<td colspan="9" class="text-center">No TO &amp; FROM records to show.</td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
	const searchInput = document.getElementById('bo-bank-search');
	const statusSelect = document.getElementById('bo-bank-status');
	const typeSelect = document.getElementById('bo-bank-type');
	const bankSelect = document.getElementById('bo-bank-name');
	const exportPdfBtn = document.getElementById('bo-bank-export-pdf');
	const resultsLabel = document.getElementById('bo-bank-results');
	const emptyRow = document.getElementById('bo-bank-empty-row');
	const metricAmount = document.getElementById('bo-bank-metric-amount');
	const metricTrend = document.getElementById('bo-bank-metric-trend');
	const metricCount = document.getElementById('bo-bank-metric-count');
	const metricPending = document.getElementById('bo-bank-metric-pending');
	const metricApproved = document.getElementById('bo-bank-metric-approved');
	const metricRejected = document.getElementById('bo-bank-metric-rejected');
	const scopeLabel = document.getElementById('bo-bank-scope-label');
	const rows = Array.from(document.querySelectorAll('#bo-bank-tbody tr[data-row="1"]'));
	const exportBaseUrl = '<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/export';
	const todayKey = <?=json_encode(date('Y-m-d'))?>;
	const yesterdayKey = <?=json_encode(date('Y-m-d', strtotime('-1 day')) )?>;

	const formatAmount = function(value) {
		return Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	};

	const updateResults = function(visibleCount) {
		if (!resultsLabel) return;
		const total = rows.length;
		resultsLabel.textContent = total ? (visibleCount + ' of ' + total + ' record(s) shown') : '0 records';
	};

	const updateMetrics = function(visibleRows) {
		let totalAmount = 0;
		let pendingCount = 0;
		let approvedCount = 0;
		let rejectedCount = 0;
		let todayAmount = 0;
		let yesterdayAmount = 0;

		visibleRows.forEach(function(row) {
			const amount = Number(row.dataset.amount || 0);
			const statusValue = String(row.dataset.status || '').toLowerCase();
			const rowDate = String(row.dataset.date || '');

			totalAmount += amount;
			if (statusValue === 'approved') approvedCount++;
			else if (statusValue === 'rejected') rejectedCount++;
			else if (statusValue.includes('pending') || statusValue.includes('waiting')) pendingCount++;

			if (rowDate === todayKey) todayAmount += amount;
			if (rowDate === yesterdayKey) yesterdayAmount += amount;
		});

		if (metricAmount) metricAmount.textContent = 'GHS ' + formatAmount(totalAmount);
		if (metricCount) metricCount.textContent = String(visibleRows.length);
		if (metricPending) metricPending.textContent = String(pendingCount);
		if (metricApproved) metricApproved.textContent = String(approvedCount);
		if (metricRejected) metricRejected.textContent = String(rejectedCount);

		if (metricTrend) {
			if (!yesterdayAmount && !todayAmount) {
				metricTrend.textContent = 'vs yesterday: –';
			} else if (!yesterdayAmount) {
				metricTrend.innerHTML = '<span class="text-success">▲</span> vs yesterday';
			} else {
				const delta = todayAmount - yesterdayAmount;
				const pct = yesterdayAmount ? (delta / yesterdayAmount) * 100 : 0;
				const arrow = delta >= 0 ? '<span class="text-success">▲</span>' : '<span class="text-danger">▼</span>';
				metricTrend.innerHTML = arrow + ' ' + Math.abs(pct).toFixed(1) + '% vs yesterday';
			}
		}

		if (scopeLabel) {
			const typeText = (typeSelect?.value || '') ? typeSelect.options[typeSelect.selectedIndex].text : 'All types';
			const statusText = (statusSelect?.value || '') ? statusSelect.options[statusSelect.selectedIndex].text : 'All statuses';
			const bankText = (bankSelect?.value || '') ? bankSelect.options[bankSelect.selectedIndex].text : 'All banks';
			scopeLabel.textContent = typeText + ' | ' + statusText + ' | ' + bankText;
		}
	};

	const buildExportParams = function() {
		const params = new URLSearchParams();
		params.set('entity', 'bank_transfers');
		params.set('resource', 'list');
		const term = (searchInput?.value || '').trim();
		if (term) params.set('find', term);
		const status = (statusSelect?.value || '').trim();
		if (status) params.set('status', status);
		const type = (typeSelect?.value || '').trim();
		if (type) params.set('transaction_type', type);
		const bank = (bankSelect?.value || '').trim();
		if (bank) params.set('bank_name', bank);
		return params;
	};

	const applyFilters = function() {
		const term = (searchInput?.value || '').trim().toLowerCase();
		const status = (statusSelect?.value || '').trim().toLowerCase();
		const type = (typeSelect?.value || '').trim().toLowerCase();
		const bank = (bankSelect?.value || '').trim().toLowerCase();
		let visibleCount = 0;
		const visibleRows = [];

		rows.forEach(function(row) {
			const matches =
				(!term || String(row.dataset.search || '').includes(term)) &&
				(!status || String(row.dataset.status || '') === status) &&
				(!type || String(row.dataset.type || '') === type) &&
				(!bank || String(row.dataset.bank || '') === bank);

			row.style.display = matches ? '' : 'none';
			if (matches) {
				visibleCount++;
				visibleRows.push(row);
			}
		});

		if (emptyRow) {
			emptyRow.style.display = visibleCount ? 'none' : '';
		}
		updateResults(visibleCount);
		updateMetrics(visibleRows);
	};

	if (exportPdfBtn) {
		exportPdfBtn.addEventListener('click', function(){
			const params = buildExportParams();
			params.set('format', 'pdf');
			window.location.href = exportBaseUrl + '?' + params.toString();
		});
	}

	let searchTimeout = null;
	searchInput?.addEventListener('input', function () {
		if (searchTimeout) clearTimeout(searchTimeout);
		searchTimeout = setTimeout(applyFilters, 200);
	});
	statusSelect?.addEventListener('change', applyFilters);
	typeSelect?.addEventListener('change', applyFilters);
	bankSelect?.addEventListener('change', applyFilters);

	applyFilters();
});
</script>
