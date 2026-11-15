<?php
	$status = strtolower((string)($row->status ?? 'pending_ack'));
	$statusClass = pettycash_status_badge_class($status);
	$statusLabel = pettycash_status_label($status);
	$currency = strtoupper(trim((string)($row->currency ?? 'GHS')));
	if (!in_array($currency, ['GHS', 'CFA'], true)) {
		$currency = 'GHS';
	}
	$category = pettycash_normalize_category($row->category ?? '') ?: 'GENERAL';
	$budgetAmount = (float)($row->budget_amount ?? 0);
	$spentAmount = (float)($row->spent_amount ?? 0);
	$changeAmount = (float)($row->change_amount ?? 0);
	$itemSummary = pettycash_items_summary($row->items_json ?? '[]', $currency);
	$itemRows = $itemSummary['items'];
	$paidDenoRows = json_decode((string)($row->paid_denos_json ?? '[]'), true);
	$changeDenoRows = json_decode((string)($row->change_denos_json ?? '[]'), true);
	if (!is_array($paidDenoRows)) $paidDenoRows = [];
	if (!is_array($changeDenoRows)) $changeDenoRows = [];
	$voucherImageUrl = !empty($row->voucher_photo) ? get_image($row->voucher_photo) : '';
	$creatorName = trim((string)($row->creator_first_name ?? '') . ' ' . (string)($row->creator_last_name ?? ''));
	$assigneeName = trim((string)($row->assignee_first_name ?? '') . ' ' . (string)($row->assignee_last_name ?? ''));
	$ackName = trim((string)($row->acknowledged_first_name ?? '') . ' ' . (string)($row->acknowledged_last_name ?? ''));
	$doneName = trim((string)($row->completed_first_name ?? '') . ' ' . (string)($row->completed_last_name ?? ''));
	$completionNote = trim((string)($row->completion_note ?? ''));

	$renderDenoTable = static function(array $items, string $currencyCode): string {
		if (empty($items)) {
			return '<div class="text-muted small">No denominations captured.</div>';
		}

		$html = '<div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Denomination</th><th class="text-center">Count</th><th class="text-end">Total</th></tr></thead><tbody>';
		foreach ($items as $item) {
			$denomination = (float)($item['denomination'] ?? 0);
			$count = (int)($item['count'] ?? 0);
			$volume = (float)($item['volume'] ?? ($denomination * $count));
			if ($denomination <= 0 || $count <= 0) {
				continue;
			}
			$html .= '<tr><td>' . esc(pettycash_format_amount($denomination, $currencyCode)) . '</td><td class="text-center">' . $count . '</td><td class="text-end">' . esc(pettycash_format_amount($volume, $currencyCode)) . '</td></tr>';
		}
		$html .= '</tbody></table></div>';
		return $html;
	};
?>

<style>
	.pc-summary-card{
		border: none;
		background: linear-gradient(135deg, #facc15 0%, #fed7aa 40%, #fff7ed 100%);
		box-shadow: 0 4px 14px rgba(0,0,0,.08);
		color: #111827;
	}
	.pc-summary-dark{
		background: linear-gradient(135deg, #0f172a 0%, #1e293b 45%, #020617 100%);
		color: #f9fafb;
	}
	.pc-detail-card{
		border: 1px solid var(--bs-border-color);
		border-radius: 16px;
		background: var(--bs-body-bg);
		box-shadow: 0 10px 30px rgba(15, 23, 42, .06);
	}
	.pc-upload-card{
		border: 1px dashed var(--bs-border-color);
		border-radius: 14px;
		padding: 1rem;
		background: rgba(var(--bs-secondary-rgb, 148, 163, 184), 0.06);
	}
	.pc-upload-actions{
		display: flex;
		align-items: center;
		gap: .75rem;
		flex-wrap: wrap;
	}
	.pc-upload-trigger{
		display: inline-flex;
		align-items: center;
		gap: .5rem;
		padding: .6rem .9rem;
		border-radius: 10px;
		border: 1px solid var(--bs-border-color);
		background: var(--bs-body-bg);
		cursor: pointer;
		font-weight: 600;
	}
	.pc-upload-preview{
		margin-top: 1rem;
		display: grid;
		grid-template-columns: 120px 1fr;
		gap: 1rem;
		align-items: start;
	}
	.pc-upload-thumb{
		width: 120px;
		height: 120px;
		border-radius: 12px;
		border: 1px solid var(--bs-border-color);
		background: rgba(var(--bs-secondary-rgb, 148, 163, 184), 0.08);
		display: flex;
		align-items: center;
		justify-content: center;
		overflow: hidden;
	}
	.pc-upload-thumb img{
		width: 100%;
		height: 100%;
		object-fit: cover;
	}
	.pc-upload-empty{
		text-align: center;
		padding: .75rem;
		font-size: .82rem;
		color: var(--bs-secondary-color);
		line-height: 1.35;
	}
	.pc-upload-meta{
		min-height: 120px;
		display: flex;
		flex-direction: column;
		justify-content: center;
	}
	.pc-upload-meta .title{
		font-weight: 700;
		margin-bottom: .35rem;
	}
	.pc-upload-meta .sub{
		font-size: .9rem;
		color: var(--bs-secondary-color);
	}
	@media (max-width: 575.98px){
		.pc-upload-preview{
			grid-template-columns: 1fr;
		}
	}
</style>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 my-3">
	<div>
		<h4 class="mb-1">Petty Cash Detail</h4>
		<div class="text-muted small"><?=esc($row->reference ?? '')?></div>
	</div>
	<div class="d-flex gap-2">
		<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>" class="btn btn-outline-secondary btn-sm">Back</a>
		<?php if ($canEdit && $status === 'pending_ack'): ?>
			<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit/<?=$row->id?>" class="btn btn-outline-primary btn-sm">Edit</a>
			<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/delete/<?=$row->id?>" class="btn btn-outline-danger btn-sm">Delete</a>
		<?php endif; ?>
	</div>
</div>

<div class="row g-2 mb-3">
	<div class="col-12 col-md-3">
		<div class="rounded-3 p-3 pc-summary-card h-100">
			<div class="text-uppercase small">Budgeted Amount</div>
			<div class="fs-4 fw-semibold"><?=pettycash_format_amount($budgetAmount, $currency)?></div>
		</div>
	</div>
	<div class="col-6 col-md-3">
		<div class="rounded-3 p-3 pc-summary-card h-100">
			<div class="text-uppercase small">Spent Amount</div>
			<div class="fs-4 fw-semibold"><?=pettycash_format_amount($spentAmount, $currency)?></div>
		</div>
	</div>
	<div class="col-6 col-md-3">
		<div class="rounded-3 p-3 pc-summary-card h-100">
			<div class="text-uppercase small">Change Returned</div>
			<div class="fs-4 fw-semibold"><?=pettycash_format_amount($changeAmount, $currency)?></div>
		</div>
	</div>
	<div class="col-12 col-md-3">
		<div class="rounded-3 p-3 pc-summary-dark h-100">
			<div class="text-uppercase small">Status</div>
			<div class="mt-2"><span class="<?=$statusClass?>"><?=$statusLabel?></span></div>
		</div>
	</div>
</div>

<div class="row g-3">
	<div class="col-lg-7">
		<div class="pc-detail-card p-3 h-100">
			<div class="d-flex justify-content-between align-items-start gap-3">
				<div>
					<h5 class="mb-1"><?=esc($row->title ?? '')?></h5>
					<div class="mb-2"><span class="badge bg-secondary"><?=esc($category)?></span></div>
					<div class="text-muted"><?=nl2br(esc($row->description ?? ''))?></div>
				</div>
				<span class="<?=$statusClass?>"><?=$statusLabel?></span>
			</div>
			<hr>
			<div class="row g-3 small">
				<div class="col-md-6">
					<div class="text-muted text-uppercase mb-1">Category</div>
					<div><?=esc($category)?></div>
				</div>
				<div class="col-md-6">
					<div class="text-muted text-uppercase mb-1">Budget Items</div>
					<div><?=number_format((int)$itemSummary['count'])?> item(s)</div>
				</div>
				<div class="col-md-6">
					<div class="text-muted text-uppercase mb-1">Manager</div>
					<div class="d-flex align-items-center gap-2">
						<img src="<?=get_image($row->creator_image ?? '')?>" style="width:34px;height:34px;object-fit:cover;border-radius:50%;">
						<div><?=esc($creatorName !== '' ? $creatorName : 'N/A')?></div>
					</div>
				</div>
				<div class="col-md-6">
					<div class="text-muted text-uppercase mb-1">Assigned User</div>
					<div class="d-flex align-items-center gap-2">
						<img src="<?=get_image($row->assignee_image ?? '')?>" style="width:34px;height:34px;object-fit:cover;border-radius:50%;">
						<div><?=esc($assigneeName !== '' ? $assigneeName : 'N/A')?></div>
					</div>
				</div>
				<div class="col-md-6">
					<div class="text-muted text-uppercase mb-1">Acknowledged By</div>
					<div><?=esc($ackName !== '' ? $ackName : 'Not yet acknowledged')?></div>
					<?php if (!empty($row->acknowledged_at)): ?><div class="text-muted"><?=get_date_time($row->acknowledged_at)?></div><?php endif; ?>
				</div>
				<div class="col-md-6">
					<div class="text-muted text-uppercase mb-1">Completed By</div>
					<div><?=esc($doneName !== '' ? $doneName : 'Not yet completed')?></div>
					<?php if (!empty($row->completed_at)): ?><div class="text-muted"><?=get_date_time($row->completed_at)?></div><?php endif; ?>
				</div>
				<?php if ($completionNote !== ''): ?>
					<div class="col-12">
						<div class="text-muted text-uppercase mb-1">Completion Note</div>
						<div><?=nl2br(esc($completionNote))?></div>
					</div>
				<?php endif; ?>
				<div class="col-12">
					<div class="text-muted text-uppercase mb-1">Budget Breakdown</div>
					<?php if (!empty($itemRows)): ?>
						<div class="table-responsive">
							<table class="table table-sm mb-0">
								<thead>
									<tr>
										<th>#</th>
										<th>Item</th>
										<th class="text-end">Budgeted Amount</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($itemRows as $itemIndex => $item): ?>
										<tr>
											<td><?=($itemIndex + 1)?></td>
											<td><?=esc($item['item_name'] ?? '')?></td>
											<td class="text-end"><?=pettycash_format_amount((float)($item['amount'] ?? 0), $currency)?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php else: ?>
						<div class="text-muted small">No itemized budget rows captured.</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
	<div class="col-lg-5">
		<div class="pc-detail-card p-3 mb-3">
			<div class="fw-semibold mb-2">Paid Denominations</div>
			<?=$renderDenoTable($paidDenoRows, $currency)?>
		</div>
		<div class="pc-detail-card p-3 mb-3">
			<div class="fw-semibold mb-2">Returned Change Denominations</div>
			<?=$renderDenoTable($changeDenoRows, $currency)?>
		</div>
		<div class="pc-detail-card p-3">
			<div class="fw-semibold mb-2">Payment Voucher</div>
			<?php if ($voucherImageUrl !== ''): ?>
				<a href="<?=esc($voucherImageUrl)?>" target="_blank" class="d-inline-block text-decoration-none">
					<img src="<?=esc($voucherImageUrl)?>" alt="Voucher" class="img-fluid rounded border" style="max-height:260px;">
				</a>
			<?php else: ?>
				<div class="text-muted small">No voucher uploaded yet.</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<?php if ($canAcknowledge): ?>
	<div class="pc-detail-card p-3 mt-4">
		<h5 class="mb-1">Acknowledge Assigned Payment</h5>
		<div class="text-muted small mb-3">Enter the cash denominations you are taking from your cashdrawer. The calculator must exactly match the assigned petty cash amount.</div>
		<form method="post" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/acknowledge/<?=$row->id?>" id="pettyAckForm">
			<input type="hidden" name="paid_denos_json" id="pettyAckDenoJson" value="">
			<div class="table-responsive border rounded-3">
				<table class="table table-sm mb-0">
					<thead>
						<tr>
							<th style="width:35%">Denomination</th>
							<th class="text-center" style="width:25%">Count</th>
							<th class="text-end" style="width:30%">Total</th>
							<th style="width:10%"></th>
						</tr>
					</thead>
					<tbody id="pettyAckDenoTable"></tbody>
					<tfoot>
						<tr class="fw-semibold">
							<td colspan="2" class="text-end">Total</td>
							<td class="text-end" id="pettyAckDenoTotal"><?=pettycash_format_amount(0, $currency)?></td>
							<td></td>
						</tr>
						<tr>
							<td colspan="4" class="pt-1 small">
								<span id="pettyAckDenoError" class="text-danger d-none">Denomination total must exactly match the assigned petty cash amount.</span>
								<span class="text-muted d-block mt-1">Expected: <?=pettycash_format_amount($budgetAmount, $currency)?></span>
							</td>
						</tr>
						<tr>
							<td colspan="4" class="pt-2">
								<button type="button" class="btn btn-outline-secondary btn-sm" id="pettyAckAddDeno"><i class="fa fa-plus-circle"></i> Add denomination</button>
							</td>
						</tr>
					</tfoot>
				</table>
			</div>
			<div class="d-flex justify-content-end mt-3">
				<button type="submit" class="btn btn-success" id="pettyAckSubmit" disabled>Acknowledge Payment</button>
			</div>
			<?=csrf()?>
		</form>
	</div>
<?php endif; ?>

<?php if ($canComplete): ?>
	<div class="pc-detail-card p-3 mt-4">
		<h5 class="mb-1">Complete Petty Cash</h5>
		<div class="text-muted small mb-3">Upload the payment voucher and enter the amount spent. If the amount spent is lower than the assigned amount, capture the returned change denominations so they can be added back to your cashdrawer.</div>
		<form method="post" enctype="multipart/form-data" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/complete/<?=$row->id?>" id="pettyCompleteForm">
			<input type="hidden" name="completion_action" id="pettyCompletionAction" value="approve">
			<input type="hidden" name="change_denos_json" id="pettyChangeDenoJson" value="">
			<div class="row g-3">
				<div class="col-md-4">
					<label class="form-label">Amount Spent</label>
					<input type="number" min="0" step="0.01" class="form-control" name="spent_amount" id="pettySpentAmount" value="">
					<small class="text-muted">Budget: <?=pettycash_format_amount($budgetAmount, $currency)?></small>
				</div>
				<div class="col-md-8">
					<label class="form-label">Completion Note</label>
					<textarea name="completion_note" id="pettyCompletionNote" rows="2" class="form-control" placeholder="Add any purchase note or rejection reason if needed..."></textarea>
				</div>
				<div class="col-12">
					<label class="form-label d-block">Payment Voucher</label>
					<div class="pc-upload-card">
						<input type="file" class="d-none" name="voucher_photo" id="pettyVoucherInput" accept="image/*">
						<div class="pc-upload-actions">
							<label for="pettyVoucherInput" class="pc-upload-trigger">
								<i class="fa-solid fa-receipt"></i>
								<span>Choose Voucher</span>
							</label>
							<div class="text-muted small">The selected voucher image will open in the cropper before submission.</div>
						</div>
						<div class="pc-upload-preview">
							<div class="pc-upload-thumb">
								<div class="pc-upload-empty" id="pettyVoucherEmpty">
									<i class="fa-regular fa-image d-block mb-2 fs-4"></i>
									No voucher selected yet
								</div>
								<img id="pettyVoucherPreview" alt="Voucher preview" style="display:none;">
							</div>
							<div class="pc-upload-meta" id="pettyVoucherMeta">
								<div class="title">Payment voucher preview</div>
								<div class="sub">Selected file details and thumbnail will appear here.</div>
							</div>
						</div>
					</div>
					<small class="text-danger d-none" id="pettyVoucherError">Upload the payment voucher before completing this transaction.</small>
				</div>
			</div>

			<div class="alert alert-warning mt-3 d-none" id="pettyOverBudgetAlert">
				The spent amount exceeds the assigned petty cash amount. You can only reject this transaction.
			</div>

			<div class="mt-3 d-none" id="pettyChangeWrap">
				<div class="fw-semibold mb-1">Returned Change Denominations</div>
				<div class="text-muted small mb-2">Capture the change that must go back into your cashdrawer.</div>
				<div class="table-responsive border rounded-3">
					<table class="table table-sm mb-0">
						<thead>
							<tr>
								<th style="width:35%">Denomination</th>
								<th class="text-center" style="width:25%">Count</th>
								<th class="text-end" style="width:30%">Total</th>
								<th style="width:10%"></th>
							</tr>
						</thead>
						<tbody id="pettyChangeDenoTable"></tbody>
						<tfoot>
							<tr class="fw-semibold">
								<td colspan="2" class="text-end">Change Total</td>
								<td class="text-end" id="pettyChangeDenoTotal"><?=pettycash_format_amount(0, $currency)?></td>
								<td></td>
							</tr>
							<tr>
								<td colspan="4" class="pt-1 small">
									<span id="pettyChangeDenoError" class="text-danger d-none">Change denominations must exactly match the remaining amount.</span>
									<span class="text-muted d-block mt-1">Expected change: <span id="pettyExpectedChange"><?=pettycash_format_amount(0, $currency)?></span></span>
								</td>
							</tr>
							<tr>
								<td colspan="4" class="pt-2">
									<button type="button" class="btn btn-outline-secondary btn-sm" id="pettyChangeAddDeno"><i class="fa fa-plus-circle"></i> Add denomination</button>
								</td>
							</tr>
						</tfoot>
					</table>
				</div>
			</div>

			<div class="d-flex flex-wrap justify-content-end gap-2 mt-3">
				<button type="submit" class="btn btn-danger" id="pettyRejectBtn" disabled>Reject Transaction</button>
				<button type="submit" class="btn btn-success" id="pettyApproveBtn" disabled>Complete Transaction</button>
			</div>
			<?=csrf()?>
		</form>
	</div>
<?php endif; ?>

<script>
<?=get_app_image_cropper_inline_source()?>

(function(){
	const currency = <?=json_encode($currency)?>;
	const budgetAmount = <?=json_encode(round($budgetAmount, 2))?>;
	const canAcknowledge = <?=json_encode((bool)$canAcknowledge)?>;
	const canComplete = <?=json_encode((bool)$canComplete)?>;
	const GHS_DENOS = [200, 100, 50, 20, 10, 5, 2, 1, 0.5, 0.2, 0.1];
	const CFA_DENOS = [10000, 5000, 2000, 1000, 500, 200, 100, 50, 20, 10];

	function formatCurrency(amount){
		const value = Number(amount || 0);
		if (currency === 'CFA') {
			return 'CFA ' + value.toLocaleString(undefined, { maximumFractionDigits: 0 });
		}
		return 'GHS ' + value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	}

	function formatFileSize(bytes) {
		const value = Number(bytes || 0);
		if (!(value > 0)) return '0 KB';
		if (value >= 1024 * 1024) return (value / (1024 * 1024)).toFixed(2) + ' MB';
		return Math.max(1, Math.round(value / 1024)) + ' KB';
	}

	function calculatorFactory(options){
		const denos = currency === 'CFA' ? CFA_DENOS : GHS_DENOS;
		const state = {
			rows: Array.isArray(options.initialRows) ? options.initialRows.slice() : [],
			expected: Number(options.expected || 0),
			body: document.getElementById(options.bodyId),
			total: document.getElementById(options.totalId),
			error: document.getElementById(options.errorId),
			hidden: document.getElementById(options.hiddenId),
			expectedLabel: options.expectedLabelId ? document.getElementById(options.expectedLabelId) : null,
		};

		function serialize(){
			return state.rows
				.map(function (row) {
					const denomination = Number(row.denomination || 0);
					const count = parseInt(row.count || 0, 10) || 0;
					return {
						currency: currency,
						denomination: denomination,
						count: count,
						volume: denomination * count
					};
				})
				.filter(function (row) {
					return row.denomination > 0 && row.count > 0;
				});
		}

		function total(){
			return serialize().reduce(function (sum, row) { return sum + Number(row.volume || 0); }, 0);
		}

		function updateRowTotalCell(index) {
			if (!state.body) return;
			const row = state.body.querySelector('tr[data-index="' + index + '"]');
			if (!row) return;
			const totalCell = row.querySelector('.pc-deno-row-total');
			if (!totalCell || !state.rows[index]) return;
			const denomination = Number(state.rows[index].denomination || 0);
			const count = parseInt(state.rows[index].count || 0, 10) || 0;
			totalCell.textContent = formatCurrency(denomination * count);
		}

		function update(){
			const payload = serialize();
			const value = total();
			if (state.hidden) state.hidden.value = JSON.stringify(payload);
			if (state.total) state.total.textContent = formatCurrency(value);
			if (state.expectedLabel) state.expectedLabel.textContent = formatCurrency(state.expected);
			const valid = state.expected <= 0 ? payload.length > 0 : (Math.abs(value - state.expected) <= 0.01 && payload.length > 0);
			if (state.error) state.error.classList.toggle('d-none', valid);
			if (typeof options.onChange === 'function') {
				options.onChange({ total: value, valid: valid, payload: payload });
			}
		}

		function render(){
			if (!state.body) return;
			if (!state.rows.length) {
				state.body.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No denominations captured.</td></tr>';
				update();
				return;
			}

			const used = new Set();
			state.rows.forEach(function (row) {
				const denomination = Number(row.denomination || 0);
				if (denomination > 0) used.add(String(denomination));
			});

			state.body.innerHTML = state.rows.map(function (row, index) {
				const denomination = Number(row.denomination || 0);
				const count = parseInt(row.count || 0, 10) || 0;
				const countValue = count > 0 ? String(count) : '';
				const totalValue = denomination * count;
				const optionsHtml = denos.map(function (item) {
					const itemKey = String(Number(item));
					if (itemKey !== String(denomination) && used.has(itemKey)) {
						return '';
					}
					const optionValue = currency === 'CFA' ? String(Math.round(Number(item))) : Number(item).toFixed(2);
					const selected = Math.abs(Number(item) - denomination) < 0.001 ? ' selected' : '';
					const optionLabel = currency === 'CFA'
						? Number(item).toLocaleString(undefined, { maximumFractionDigits: 0 })
						: Number(item).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
					return '<option value="' + optionValue + '"' + selected + '>' + optionLabel + '</option>';
				}).join('');
				return '<tr data-index="' + index + '">' +
					'<td><div class="input-group input-group-sm"><span class="input-group-text">' + currency + '</span><select class="form-select pc-deno-note" data-index="' + index + '">' + optionsHtml + '</select></div></td>' +
					'<td><input type="number" min="0" step="1" class="form-control form-control-sm text-center pc-deno-count" data-index="' + index + '" value="' + countValue + '"></td>' +
					'<td class="text-end pc-deno-row-total">' + formatCurrency(totalValue) + '</td>' +
					'<td class="text-center"><button type="button" class="btn btn-link text-danger p-0 pc-deno-remove" data-index="' + index + '"><i class="fa fa-trash"></i></button></td>' +
				'</tr>';
			}).join('');

			update();
		}

		function addRow(){
			let next = Number(denos[0] || 0);
			const used = new Set(state.rows.map(function (row) { return String(Number(row.denomination || 0)); }));
			for (let i = 0; i < denos.length; i++) {
				if (!used.has(String(Number(denos[i])))) {
					next = Number(denos[i]);
					break;
				}
			}
			state.rows.push({ denomination: next, count: '' });
			render();
		}

		if (state.body) {
			state.body.addEventListener('input', function (event) {
				const target = event.target;
				const index = parseInt(target.getAttribute('data-index') || '-1', 10);
				if (index < 0 || !state.rows[index]) return;
				if (target.classList.contains('pc-deno-note')) {
					state.rows[index].denomination = Number(target.value || 0);
					render();
					return;
				}
				if (target.classList.contains('pc-deno-count')) {
					const rawValue = String(target.value || '').trim();
					state.rows[index].count = rawValue === '' ? '' : Math.max(0, parseInt(rawValue, 10) || 0);
					update();
					updateRowTotalCell(index);
				}
			});
			state.body.addEventListener('click', function (event) {
				const btn = event.target.closest('.pc-deno-remove');
				if (!btn) return;
				const index = parseInt(btn.getAttribute('data-index') || '-1', 10);
				if (index < 0 || !state.rows[index]) return;
				state.rows.splice(index, 1);
				render();
			});
		}

		return {
			render: render,
			addRow: addRow,
			setExpected: function (value) {
				const nextExpected = Number(value || 0);
				if (Math.abs(nextExpected - state.expected) <= 0.009) {
					return;
				}
				state.expected = nextExpected;
				update();
			},
			reset: function () {
				state.rows = [];
				render();
			}
		};
	}

	if (canAcknowledge) {
		const acknowledgeButton = document.getElementById('pettyAckSubmit');
		const ackCalc = calculatorFactory({
			bodyId: 'pettyAckDenoTable',
			totalId: 'pettyAckDenoTotal',
			errorId: 'pettyAckDenoError',
			hiddenId: 'pettyAckDenoJson',
			expected: budgetAmount,
			onChange: function (state) {
				if (acknowledgeButton) acknowledgeButton.disabled = !state.valid;
			}
		});
		const addAckRowButton = document.getElementById('pettyAckAddDeno');
		if (addAckRowButton) {
			addAckRowButton.addEventListener('click', function () {
				ackCalc.addRow();
			});
		}
		ackCalc.addRow();
	}

	if (canComplete) {
		const spentInput = document.getElementById('pettySpentAmount');
		const approveBtn = document.getElementById('pettyApproveBtn');
		const rejectBtn = document.getElementById('pettyRejectBtn');
		const completionAction = document.getElementById('pettyCompletionAction');
		const changeWrap = document.getElementById('pettyChangeWrap');
		const overBudgetAlert = document.getElementById('pettyOverBudgetAlert');
		const voucherInput = document.getElementById('pettyVoucherInput');
		const voucherPreview = document.getElementById('pettyVoucherPreview');
		const voucherEmpty = document.getElementById('pettyVoucherEmpty');
		const voucherMeta = document.getElementById('pettyVoucherMeta');
		const voucherError = document.getElementById('pettyVoucherError');

		let voucherReady = false;
		let changeState = { total: 0, valid: true };

		const changeCalc = calculatorFactory({
			bodyId: 'pettyChangeDenoTable',
			totalId: 'pettyChangeDenoTotal',
			errorId: 'pettyChangeDenoError',
			hiddenId: 'pettyChangeDenoJson',
			expectedLabelId: 'pettyExpectedChange',
			expected: 0,
			onChange: function (state) {
				changeState = state;
				updateCompletionState();
			}
		});

		function resetVoucherPreview() {
			if (voucherPreview) {
				const objectUrl = String(voucherPreview.dataset.objectUrl || '').trim();
				if (objectUrl) {
					try { URL.revokeObjectURL(objectUrl); } catch (e) {}
					delete voucherPreview.dataset.objectUrl;
				}
				voucherPreview.removeAttribute('src');
				voucherPreview.style.display = 'none';
			}
			if (voucherEmpty) voucherEmpty.style.display = '';
			if (voucherMeta) voucherMeta.innerHTML = '<div class="title">Payment voucher preview</div><div class="sub">Selected file details and thumbnail will appear here.</div>';
			voucherReady = false;
		}

		function updateVoucherMeta(file) {
			if (!voucherMeta || !file) return;
			voucherMeta.innerHTML =
				'<div class="title">' + String(file.name || 'Voucher image') + '</div>' +
				'<div class="sub">Type: ' + String(file.type || 'image') + '</div>' +
				'<div class="sub">Size: ' + formatFileSize(file.size || 0) + '</div>';
		}

		function updateCompletionState() {
			const spentValue = Math.max(0, Number(spentInput && spentInput.value ? spentInput.value : 0));
			const diff = Math.max(0, Math.round((budgetAmount - spentValue) * 100) / 100);
			const overBudget = spentValue > budgetAmount + 0.009;
			const hasVoucher = !!(voucherInput && voucherInput.files && voucherInput.files[0] && voucherReady);
			if (voucherError) voucherError.classList.toggle('d-none', hasVoucher);

			if (changeWrap) changeWrap.classList.toggle('d-none', !(spentValue > 0 && diff > 0 && !overBudget));
			if (overBudgetAlert) overBudgetAlert.classList.toggle('d-none', !overBudget);

			changeCalc.setExpected(diff > 0 && !overBudget ? diff : 0);

			const canApprove = hasVoucher && spentValue > 0 && !overBudget && (diff <= 0.009 || changeState.valid);
			const canReject = hasVoucher && overBudget;
			if (approveBtn) approveBtn.disabled = !canApprove;
			if (rejectBtn) rejectBtn.disabled = !canReject;
		}

		const addChangeRowButton = document.getElementById('pettyChangeAddDeno');
		if (addChangeRowButton) {
			addChangeRowButton.addEventListener('click', function () {
				changeCalc.addRow();
			});
		}

		if (spentInput) {
			spentInput.addEventListener('input', updateCompletionState);
		}

		if (approveBtn) {
			approveBtn.addEventListener('click', function () {
				if (completionAction) completionAction.value = 'approve';
			});
		}

		if (rejectBtn) {
			rejectBtn.addEventListener('click', function () {
				if (completionAction) completionAction.value = 'reject';
			});
		}

		if (voucherInput) {
			voucherInput.addEventListener('change', function () {
				const file = voucherInput.files && voucherInput.files[0] ? voucherInput.files[0] : null;
				if (!file) {
					resetVoucherPreview();
					updateCompletionState();
					return;
				}

				if (window.AppImageCropper && typeof window.AppImageCropper.handleLegacyChange === 'function') {
					window.AppImageCropper.handleLegacyChange({ currentTarget: voucherInput }, {
						previewEl: voucherPreview,
						placeholderEl: voucherEmpty,
						errorEl: voucherError,
						maxBytes: window.AppImageCropper.MAX_BYTES,
						modalTitle: 'Crop petty cash voucher',
						errorTitle: 'Invalid petty cash voucher',
						onSuccess: function (result) {
							if (result && result.file) {
								updateVoucherMeta(result.file);
							}
						}
					}).then(function (result) {
						const finalFile = voucherInput.files && voucherInput.files[0] ? voucherInput.files[0] : null;
						if (!result || !result.ok || !finalFile) {
							resetVoucherPreview();
						} else {
							updateVoucherMeta(finalFile);
							voucherReady = true;
						}
						updateCompletionState();
					});
					return;
				}

				const reader = new FileReader();
				reader.onload = function (event) {
					if (voucherPreview) {
						voucherPreview.src = String(event.target && event.target.result ? event.target.result : '');
						voucherPreview.style.display = 'block';
					}
					if (voucherEmpty) voucherEmpty.style.display = 'none';
					updateVoucherMeta(file);
					voucherReady = true;
					updateCompletionState();
				};
				reader.readAsDataURL(file);
			});
		}

		changeCalc.render();
		updateCompletionState();
	}
})();
</script>
