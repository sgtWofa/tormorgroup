<?php 
// Check permissions
if (!user_can('view_momoremit_details') && !user_can('view_momoremit') && !user_can('is_operations_manager')) {
	echo '<div class="alert alert-danger text-center"><i class="fa fa-exclamation-triangle me-2"></i>Access denied. You do not have permission to view this page.</div>';
	return;
}

$session = get_session();
$userId = (int)($session->id ?? 0);

if ($userId <= 0) {
	echo '<div class="alert alert-danger text-center"><i class="fa fa-exclamation-circle me-2"></i>User session invalid. Please login again.</div>';
	return;
}

// Fetch pending commissions for the user. Admins and operations managers can see all records and metrics.
$canViewAllCommissions = user_can('is_admin') || user_can('is_operations_manager');
$userData = momoremit_get_user_pending_commissions($userId, $canViewAllCommissions);
$commissions = $userData['commissions'] ?? [];
$summary = $userData['summary'] ?? null;
$activeWhatsappAdmins = [];
try {
	$adminDb = new \Core\Database();
	$today = (new \DateTimeImmutable('now', new \DateTimeZone('Africa/Accra')))->format('Y-m-d');
	$activeWhatsappAdmins = $adminDb->query(
		"SELECT DISTINCT u.id, u.first_name, u.last_name
		 FROM users u
		 INNER JOIN user_roles_map urm ON urm.user_id = u.id AND (urm.disabled = 0 OR urm.disabled IS NULL)
		 INNER JOIN user_roles ur ON ur.id = urm.role_id AND (ur.disabled = 0 OR ur.disabled IS NULL)
		 INNER JOIN cashdrawers cd ON cd.user_id = u.id AND DATE(cd.date_created) = :today
		 WHERE u.deleted = 0 AND LOWER(TRIM(ur.role)) = 'admin'
		 ORDER BY u.first_name ASC, u.last_name ASC, u.id ASC",
		['today' => $today]
	) ?: [];
} catch (\Throwable $e) {
	$activeWhatsappAdmins = [];
}
$pendingApprovalGroups = [];
if ($canViewAllCommissions) {
	$approvalDb = new \Core\Database();
	foreach ($commissions as $commission) {
		$status = strtoupper(trim((string)($commission->status ?? '')));
		if (!in_array($status, ['PENDING', 'PARTIALLY_TRANSFERRED'], true) || empty($commission->transferred_to_commission_id)) {
			continue;
		}
		$key = strtoupper(trim((string)($commission->network ?? ''))) . '|' . strtoupper(trim((string)($commission->commission_type ?? '')));
		if (!isset($pendingApprovalGroups[$key])) {
			$pendingApprovalGroups[$key] = [
				'network' => (string)($commission->network ?? ''),
				'type' => (string)($commission->commission_type ?? ''),
				'amount' => 0.0,
				'ids' => [],
				'proof_images' => [],
			];
		}
		$commissionId = (int)$commission->id;
		$linkedRows = $approvalDb->query(
			'SELECT amount, proof_image, transaction_status FROM commissions WHERE id = :id AND deleted = 0 LIMIT 1',
			['id' => (int)($commission->transferred_to_commission_id ?? 0)]
		) ?: [];
		$linked = $linkedRows[0] ?? null;
		$linkedStatus = strtolower(trim((string)($linked->transaction_status ?? '')));
		if (!in_array($linkedStatus, ['pending', 'draft'], true)) {
			continue;
		}
		$pendingApprovalGroups[$key]['amount'] += max(0.0, (float)($linked->amount ?? 0));
		$pendingApprovalGroups[$key]['ids'][] = $commissionId;
		$pendingApprovalGroups[$key]['proof_images'][$commissionId] = (string)($linked->proof_image ?? '');
		$pendingApprovalGroups[$key]['amounts'][$commissionId] = max(0.0, (float)($linked->amount ?? 0));
	}
}
$pendingApprovalGroups = array_values(array_filter($pendingApprovalGroups, static function ($group) {
	return !empty($group['ids']);
}));
$pendingApprovalIds = [];
foreach ($pendingApprovalGroups as $approvalGroup) {
	foreach (($approvalGroup['ids'] ?? []) as $approvalId) {
		$pendingApprovalIds[(int)$approvalId] = true;
	}
}
$typeBreakdown = is_array($summary->by_commission_type ?? null) ? $summary->by_commission_type : [];
$networkOptions = [];
$creatorOptions = [];
foreach ($commissions as $commission) {
	$networkValue = strtoupper(trim((string)($commission->network ?? '')));
	if ($networkValue !== '' && !isset($networkOptions[$networkValue])) {
		$networkOptions[$networkValue] = $networkValue;
	}
	$creatorName = trim((string)($commission->creator_first_name ?? '') . ' ' . (string)($commission->creator_last_name ?? ''));
	if ($creatorName === '') {
		$creatorName = 'User #' . (int)($commission->created_by ?? 0);
	}
	if (!isset($creatorOptions[strtolower($creatorName)])) {
		$creatorOptions[strtolower($creatorName)] = $creatorName;
	}
}
ksort($networkOptions);
natcasesort($creatorOptions);

$pendingCount = (int)($summary->pending_count ?? 0);
$pendingAmount = (float)($summary->pending_amount ?? $summary->pending_expected_amount ?? 0);
$transferredCount = (int)($summary->transferred_count ?? 0);
$transferredAmount = (float)($summary->transferred_amount ?? $summary->transferred_expected_amount ?? 0);
$totalCollectedAmount = (float)($summary->total_collected_amount ?? 0);
$transferablePendingCount = 0;
foreach ($commissions as $commission) {
	$status = strtoupper(trim((string)($commission->status ?? '')));
	if (($status === 'PENDING' || $status === 'PARTIALLY_TRANSFERRED') && empty($pendingApprovalIds[(int)($commission->id ?? 0)]) && (float)($commission->expected_value_amount ?? 0) - (float)($commission->transferred_amount ?? 0) > 0.005 && (!$canViewAllCommissions || (int)($commission->created_by ?? 0) === $userId)) {
		$transferablePendingCount++;
	}
}

?>

<!-- External Libraries: jsPDF, AutoTable, and Chart.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
	.commission-dashboard {
		background: var(--bs-body-bg, #ffffff);
		color: var(--bs-body-color, #212529);
		border: 1px solid var(--bs-border-color, rgba(148, 163, 184, 0.18));
		border-radius: 1rem;
		padding: 1.5rem;
		box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
	}
	.commission-hero {
		background: linear-gradient(135deg, rgba(104, 111, 232, 0.95) 0%, rgba(128, 96, 182, 0.98) 100%);
		border-radius: 0.9rem;
		padding: 1.75rem 1.5rem;
		color: #fff;
		margin-bottom: 1.5rem;
		border: 1px solid rgba(255,255,255,0.08);
	}
	.commission-hero h2 {
		font-size: clamp(1.9rem, 2vw, 2.5rem);
		font-weight: 700;
		letter-spacing: -0.03em;
		margin-bottom: 0.35rem;
	}
	.commission-hero p {
		font-size: 0.98rem;
		opacity: 0.9;
		margin: 0;
	}
	.commission-stat {
		border-radius: 0.85rem;
		padding: 1.1rem 1.25rem 1rem;
		border: 1px solid var(--bs-border-color, rgba(148,163,184,.2));
		background: var(--bs-tertiary-bg, rgba(248, 249, 250, 0.8));
		text-align: center;
		min-height: 126px;
		display: flex;
		flex-direction: column;
		justify-content: center;
	}
	.commission-stat-value {
		font-size: clamp(1.8rem, 2vw, 2.4rem);
		font-weight: 700;
		line-height: 1.2;
		margin-top: 0.45rem;
		letter-spacing: -0.03em;
	}
	.commission-stat-label {
		font-size: 0.72rem;
		text-transform: uppercase;
		letter-spacing: 0.12em;
		color: var(--bs-secondary-color, rgba(108, 117, 125, 0.9));
		font-weight: 600;
	}
	.commission-type-grid {
		margin-bottom: 1.5rem;
	}
	.commission-type-card {
		background: var(--bs-tertiary-bg, rgba(248, 249, 250, 0.8));
		border: 1px solid var(--bs-border-color, rgba(148,163,184,.18));
		border-radius: 0.85rem;
		padding: 0.9rem 1rem;
		min-height: 118px;
		display: flex;
		flex-direction: column;
		justify-content: center;
	}
	.commission-type-card .type-label {
		font-size: 0.68rem;
		letter-spacing: 0.12em;
		text-transform: uppercase;
		color: var(--bs-secondary-color, rgba(108, 117, 125, 0.9));
		margin-bottom: 0.45rem;
		font-weight: 700;
	}
	.commission-type-card .type-value {
		font-size: 1.25rem;
		font-weight: 700;
		letter-spacing: -0.02em;
		color: var(--bs-heading-color, inherit);
	}
	.commission-type-card .type-meta {
		font-size: 0.8rem;
		color: var(--bs-secondary-color, rgba(108, 117, 125, 0.85));
		margin-top: 0.3rem;
	}
	.commission-table {
		border-radius: 0.9rem;
		overflow-x: auto;
		-webkit-overflow-scrolling: touch;
		border: 1px solid var(--bs-border-color, rgba(148,163,184,.18));
		box-shadow: 0 4px 12px rgba(0,0,0,.05);
	}
	.commission-table table {
		width: 100%;
		white-space: nowrap;
	}
	.commission-table thead th {
		background: var(--bs-tertiary-bg, rgba(248, 249, 250, 0.9));
		border-color: var(--bs-border-color, rgba(148,163,184,.14));
		text-transform: uppercase;
		font-size: 0.72rem;
		letter-spacing: 0.1em;
		font-weight: 600;
		padding: 0.9rem 0.8rem;
		color: var(--bs-secondary-color, rgba(108, 117, 125, 0.9));
	}
	.commission-table tbody td {
		padding: 0.8rem 0.75rem;
		border-color: var(--bs-border-color, rgba(148,163,184,.12));
		vertical-align: middle;
	}
	.commission-table tbody tr {
		transition: background-color 0.15s ease;
	}
	.commission-table tbody tr:hover {
		background: var(--bs-tertiary-bg, rgba(0,0,0,.03));
	}
	.commission-badge {
		display: inline-block;
		padding: 0.32rem 0.7rem;
		border-radius: 999px;
		font-size: 0.72rem;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.07em;
	}
	.commission-badge.pending {
		background: rgba(245,158,11,.12);
		color: #d97706;
		border: 1px solid rgba(245,158,11,.28);
	}
	.commission-badge.partially_transferred {
		background: rgba(59,130,246,.12);
		color: #2563eb;
		border: 1px solid rgba(59,130,246,.28);
	}
	.commission-badge.transferred {
		background: rgba(34,197,94,.12);
		color: #16a34a;
		border: 1px solid rgba(34,197,94,.22);
	}
	[data-bs-theme="dark"] .commission-badge.pending {
		color: #fbbf24;
	}
	[data-bs-theme="dark"] .commission-badge.partially_transferred {
		color: #60a5fa;
	}
	[data-bs-theme="dark"] .commission-badge.transferred {
		color: #4ade80;
	}
	.commission-actions {
		display: flex;
		gap: 0.45rem;
		flex-wrap: nowrap;
	}
	.commission-actions .btn {
		padding: 0.35rem 0.7rem;
		font-size: 0.8rem;
	}
	.commission-empty {
		padding: 3rem 1rem;
		text-align: center;
		color: var(--bs-secondary-color, rgba(108,117,125,0.7));
	}
	.commission-modal-header {
		background: linear-gradient(135deg, rgba(104, 111, 232, 0.95) 0%, rgba(128, 96, 182, 0.98) 100%);
		color: white;
		border-radius: 0.9rem 0.9rem 0 0;
	}
	.commission-transfer-form {
		border-radius: 0.8rem;
		padding: 1.1rem 1rem;
		border: 1px dashed var(--bs-border-color, rgba(148,163,184,.25));
		background: var(--bs-tertiary-bg, rgba(248,249,250,.5));
	}
	.transfer-single-btn {
		display: inline-flex;
		align-items: center;
		justify-content: center;
	}
	.bulk-proof-trigger {
		white-space: nowrap;
	}
	.proof-preview {
		display: flex;
		align-items: center;
		justify-content: center;
		min-height: 52px;
		margin-top: 0.55rem;
		border: 1px solid var(--bs-border-color, rgba(148,163,184,.2));
		border-radius: 0.55rem;
		background: var(--bs-body-bg, rgba(0,0,0,.05));
		overflow: hidden;
		padding: 0.28rem;
	}
	.proof-preview img {
		display: block;
		max-width: 52px;
		max-height: 52px;
		width: 52px;
		height: 52px;
		object-fit: cover;
		border-radius: 0.45rem;
		border: 1px solid var(--bs-border-color, rgba(148,163,184,.2));
	}
	.proof-preview .proof-placeholder {
		font-size: 0.7rem;
		color: var(--bs-secondary-color, rgba(108,117,125,0.7));
		letter-spacing: 0.04em;
		text-transform: uppercase;
	}
	.checkbox-group {
		display: flex;
		flex-direction: column;
		gap: 0.75rem;
	}
	.checkbox-group label {
		display: flex;
		align-items: center;
		gap: 0.75rem;
		cursor: pointer;
		margin-bottom: 0;
	}
	.checkbox-group input[type="checkbox"] {
		cursor: pointer;
	}

    .chart-container {
        position: relative;
        height: 220px;
        width: 100%;
    }

    .btn-warning-subtle {
	    background-color: rgba(255, 193, 7, 0.2) !important; 
	    color: #ffc107 !important; 
	    border: 1px solid rgba(255, 193, 7, 0.4) !important;
	    transition: all 0.2s ease-in-out;
	}

	.btn-warning-subtle:hover,
	.btn-warning-subtle:focus {
	    background-color: rgba(255, 193, 7, 0.62) !important; 
	    color: #000000 !important;             /* High-contrast dark text */
	    border-color: #ffc107 !important;
	    transform: translateY(-2px);
	    box-shadow: 0 4px 12px rgba(255, 193, 7, 0.4); /* Pronounced glowing shadow */
	}
</style>

<div class="commission-dashboard">
	<div class="d-none"><?= csrf('csrf') ?></div>
	<!-- Header -->
	<div class="commission-hero">
		<div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
			<div>
				<h2 class="mb-2"><i class="fa fa-percentage me-2"></i>Cashout Commission Management</h2>
				<p class="mb-0 text-muted"><i class="fa fa-chart-line me-1"></i>Track and manage your pending commissions from cashout and withdrawal transactions</p>
			</div>
			<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>" class="ms-auto">
				<button class="btn btn-md btn-warning text-dark border-0 btn-warning-subtle text-warning-emphasis fw-semibold shadow-sm">
					<i class="fa-solid fa-chevron-left me-1"></i> Back to MomoRemits
				</button>
			</a>
		</div>
	</div>

	<!-- Statistics -->
	<div class="row g-3 mb-4">
		<div class="col-md-6">
			<div class="commission-stat h-100">
				<div class="commission-stat-label"><i class="fa fa-clock me-1 text-warning"></i>Pending Expected</div>
				<div class="commission-stat-value text-warning" id="metricPendingAmount">GHS <?= number_format($pendingAmount, 2) ?></div>
				<div class="mt-2 small text-secondary">Count: <strong id="metricPendingCount"><?= $pendingCount ?></strong></div>
			</div>
		</div>
		<div class="col-md-6">
			<div class="commission-stat h-100">
				<div class="commission-stat-label"><i class="fa fa-check-circle me-1 text-success"></i>Transferred</div>
				<div class="commission-stat-value text-success" id="metricTransferredAmount">GHS <?= number_format($transferredAmount, 2) ?></div>
				<div class="mt-2 small text-secondary">Count: <strong id="metricTransferredCount"><?= $transferredCount ?></strong></div>
			</div>
		</div>
	</div>

	<div class="row g-3 commission-type-grid" id="commissionTypeGrid">
		<?php foreach ($typeBreakdown as $typeMeta): ?>
		<?php
			$typeName = trim((string)($typeMeta['commission_type'] ?? 'Unknown'));
			$typePendingAmount = (float)($typeMeta['pending_amount'] ?? 0);
			$typeTransferredAmount = (float)($typeMeta['transferred_amount'] ?? 0);
			$typeExpectedAmount = (float)($typeMeta['expected_amount'] ?? ($typePendingAmount + $typeTransferredAmount));
			$typeTotalCount = (int)($typeMeta['count'] ?? 0);
		?>
		<div class="col-md-4 col-xl-3 type-card-item" data-type-name="<?= esc($typeName) ?>">
			<div class="commission-type-card h-100">
				<div class="type-label"><i class="fa fa-layer-group me-1"></i>Commission Type</div>
				<div class="type-value"><?= esc($typeName) ?></div>
				<div class="type-meta">Pending: <strong class="text-warning card-type-pending">GHS <?= number_format($typePendingAmount, 2) ?></strong> • <span class="card-type-pending-count"><?= (int)($typeMeta['pending_count'] ?? 0) ?></span> items</div>
				<div class="type-meta">Transferred: <strong class="text-success card-type-transferred">GHS <?= number_format($typeTransferredAmount, 2) ?></strong> • <span class="card-type-transferred-count"><?= (int)($typeMeta['transferred_count'] ?? 0) ?></span> items</div>
			</div>
		</div>
		<?php endforeach; ?>
	</div>

	<div class="card border-0 shadow-sm mb-4">
		<div class="card-body">
			<div class="row g-3 align-items-end">
				<div class="col-md-3">
					<label class="form-label"><i class="fa fa-search me-1"></i>Search</label>
					<input type="search" id="commissionSearch" class="form-control" placeholder="Network, type, user, status...">
				</div>
				<div class="col-md-2">
					<label class="form-label"><i class="fa fa-filter me-1"></i>Status</label>
					<select id="commissionStatusFilter" class="form-select">
						<option value="">All</option>
						<option value="PENDING">Pending</option>
						<option value="PARTIALLY_TRANSFERRED">Partially Transferred</option>
						<option value="TRANSFERRED">Transferred</option>
					</select>
				</div>
				<div class="col-md-2">
					<label class="form-label"><i class="fa fa-network-wired me-1"></i>Network</label>
					<select id="commissionNetworkFilter" class="form-select">
						<option value="">All</option>
						<?php foreach ($networkOptions as $networkVal): ?>
							<option value="<?= esc($networkVal) ?>"><?= esc($networkVal) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="col-md-2">
					<label class="form-label"><i class="fa fa-calendar-alt me-1"></i>Date From</label>
					<input type="date" id="commissionDateFrom" class="form-control">
				</div>
				<div class="col-md-3">
					<label class="form-label"><i class="fa fa-calendar-alt me-1"></i>Date To</label>
					<input type="date" id="commissionDateTo" class="form-control">
				</div>
				<div class="col-md-3">
					<label class="form-label"><i class="fa fa-user me-1"></i>Created By</label>
					<select id="commissionCreatorFilter" class="form-select">
						<option value="">All users</option>
						<?php foreach ($creatorOptions as $creatorValue): ?>
							<option value="<?=esc($creatorValue)?>"><?=esc($creatorValue)?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="col-md-2">
					<label class="form-label"><i class="fa fa-list-ol me-1"></i>Per Page</label>
					<select id="commissionPageLimit" class="form-select">
						<option value="10">10</option>
						<option value="25">25</option>
						<option value="50">50</option>
						<option value="100">100</option>
					</select>
				</div>
				<div class="col-md-2 d-flex align-items-end">
					<button type="button" class="btn btn-outline-secondary w-100" id="commissionResetFilters">
						<i class="fa fa-undo me-1"></i>Reset
					</button>
				</div>
				<div class="col-md-2 d-flex align-items-end">
					<button type="button" class="btn btn-purple text-white bg-primary w-100" id="openAnalyticsModalBtn">
						<i class="fa fa-chart-pie me-1"></i>Analytics
					</button>
				</div>
				<div class="col-md-2 d-flex align-items-end">
					<button type="button" class="btn btn-outline-danger w-100" id="commissionExportPdf">
						<i class="fa fa-file-pdf me-1"></i>Export PDF
					</button>
				</div>
			</div>
		</div>
	</div>

	<!-- Pending Commissions Table -->
	<div class="mb-4">
		<div class="d-flex justify-content-between align-items-center mb-3">
			<h5 class="mb-0 text-warning"><i class="fa fa-list me-2"></i>Commissions List</h5>
			<?php if ($canViewAllCommissions && !empty($pendingApprovalGroups)): ?>
				<button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#commissionApprovalModal"><i class="fa fa-check-double me-1"></i>Review Pending</button>
			<?php endif; ?>
			<?php if ($transferablePendingCount > 0): ?>
				<button id="openBulkTransferBtn" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#bulkTransferModal">
					<i class="fa fa-exchange-alt me-2"></i> Transfer to Main
				</button>
			<?php endif; ?>
		</div>

		<?php if (!empty($commissions)): ?>
			<div class="table-responsive commission-table">
				<table class="table table-hover mb-0" id="commissionDataTable">
					<thead>
						<tr>
							<th style="width: 40px;">
								<input type="checkbox" id="selectAllCommissions" class="form-check-input">
							</th>
							<th>
                                <i class="fa fa-wifi me-1"></i><i class="fa fa-tags me-1"></i>Type
                            </th>
							<th><i class="fa fa-calculator me-1"></i>Transaction</th>
							<th><i class="fa fa-info-circle me-1"></i><i class="fa fa-coins me-1"></i>Commission</th>
							<th><i class="fa fa-user-clock me-1"></i>Date</th>
							<th><i class="fa fa-cogs me-1"></i>Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($commissions as $commission): ?>
							<?php 
								$status = strtoupper(trim((string)($commission->status ?? 'PENDING')));
								$statusClass = $status === 'TRANSFERRED' ? 'transferred' : ($status === 'PARTIALLY_TRANSFERRED' ? 'partially_transferred' : 'pending');
								$statusIcon = $status === 'TRANSFERRED' ? 'fa-check-circle' : ($status === 'PARTIALLY_TRANSFERRED' ? 'fa-adjust' : 'fa-hourglass-half');
								$amount = (float)($commission->commission_amount ?? 0);
								$transferredAmountRow = (float)($commission->transferred_amount ?? 0);
								$expectedAmount = (float)($commission->expected_value_amount ?? 0);
								$remainingExpectedAmount = max(0.0, $expectedAmount - $transferredAmountRow);
								$transAmount = (float)($commission->transaction_amount ?? 0);
								$rate = (float)($commission->commission_rate ?? 0);
								$isCapped = (int)($commission->is_capped ?? 0) === 1;
								$capValue = (float)($commission->cap_value ?? 0);
								$createdBy = trim((string)($commission->creator_first_name ?? '') . ' ' . (string)($commission->creator_last_name ?? ''));
								$hasRemainingAmount = $remainingExpectedAmount > 0.005;
								$isPendingApproval = $canViewAllCommissions && !empty($pendingApprovalIds[(int)($commission->id ?? 0)]);
								$isRowTransferable = ($status === 'PENDING' || $status === 'PARTIALLY_TRANSFERRED') && $hasRemainingAmount && !$isPendingApproval && (!$canViewAllCommissions || (int)($commission->created_by ?? 0) === $userId);
								if ($createdBy === '') {
									$createdBy = '—';
								}
							?>
							<tr class="commission-row" 
								data-commission-search="<?= esc(strtolower($commission->network . ' ' . $commission->commission_type . ' ' . $createdBy . ' ' . $status)) ?>" 
								data-status="<?= esc($status) ?>" 
								data-network="<?= esc(strtoupper((string)($commission->network ?? ''))) ?>" 
								data-commission-type="<?= esc((string)($commission->commission_type ?? '')) ?>" 
								data-created-by="<?= esc($createdBy) ?>" 
								data-date-created="<?= esc((string)($commission->date_created ?? '')) ?>"
								data-amount="<?= $amount ?>"
								data-transferred-amount="<?= $transferredAmountRow ?>"
								data-expected-amount="<?= $expectedAmount ?>"
								data-remaining-expected="<?= $remainingExpectedAmount ?>"
								data-rate="<?= $rate ?>"
								data-trans-amount="<?= $transAmount ?>">
								<td>
									<?php if (($status === 'PENDING' || $status === 'PARTIALLY_TRANSFERRED') && $isRowTransferable): ?>
										<input type="checkbox" class="form-check-input commission-checkbox" 
											data-commission-id="<?= $commission->id ?>" 
											data-amount="<?= $amount ?>"
											data-transferred-amount="<?= $transferredAmountRow ?>"
											data-expected-amount="<?= $expectedAmount ?>"
											data-remaining-expected="<?= $remainingExpectedAmount ?>">
									<?php elseif ($status === 'PENDING' || $status === 'PARTIALLY_TRANSFERRED'): ?>
										<span class="text-muted" title="Transfer is restricted to your own commissions">—</span>
									<?php else: ?>
										<span class="text-muted">—</span>
									<?php endif; ?>
								</td>
								<td>
									<strong><i class="fa fa-signal me-1 text-primary"></i><?= esc($commission->network) ?></strong>
								    <div><i class="fa fa-tag me-1 text-secondary"></i><?= esc($commission->commission_type) ?></div>
                                </td>
								<td>
									<div class="fw-semibold">GHS <?= number_format($transAmount, 2) ?></div>
									<div class="small text-muted"><i class="fa fa-percent me-1"></i><?= number_format($rate, 2) ?>%</div>
									<?php if ($isCapped && $capValue > 0): ?>
										<div class="small text-info"><i class="fa fa-shield-alt me-1"></i>Capped: GHS <?= number_format($capValue, 2) ?></div>
									<?php endif; ?>
								</td>
								<td>
                                    <span class="commission-badge <?= $statusClass ?>">
										<i class="fa <?= $statusIcon ?> me-1"></i><?= str_replace('_', ' ', $status) ?>
									</span>
									<div class="fw-semibold text-warning">GHS <?= number_format($amount, 2) ?></div>
									<div class="small text-info"><i class="fa fa-bullseye me-1"></i>Expected: GHS <?= number_format($expectedAmount, 2) ?></div>
									<?php if ($transferredAmountRow > 0): ?>
										<div class="small text-success"><i class="fa fa-arrow-down me-1"></i>Paid: GHS <?= number_format($transferredAmountRow, 2) ?></div>
									<?php endif; ?>
									<?php if (!empty($commission->notes)): ?>
										<div class="small text-danger" title="<?= esc((string)$commission->notes) ?>"><i class="fa fa-comment-slash me-1"></i><?= esc((string)$commission->notes) ?></div>
									<?php endif; ?>
								</td>
								<td>
									<small class="text-muted d-block">
										<i class="fa fa-clock me-1"></i><?= $commission->date_created ? date('M d, Y H:i', strtotime($commission->date_created)) : '—' ?>
									</small>
									<div class="small text-secondary mt-1"><i class="fa fa-user me-1"></i><?= esc($createdBy) ?></div>
								</td>
								<td>
									<div class="commission-actions">
										<button class="btn btn-sm btn-outline-info view-commission-btn" 
											data-commission-id="<?= $commission->id ?>"
											title="View details">
											<i class="fa fa-eye"></i>
										</button>
										<?php if (($status === 'PENDING' || $status === 'PARTIALLY_TRANSFERRED') && $isRowTransferable): ?>
											<button class="btn btn-sm btn-outline-success transfer-single-btn" 
												data-commission-id="<?= $commission->id ?>"
												data-amount="<?= $amount ?>"
												data-expected-amount="<?= $expectedAmount ?>"
												data-remaining-expected="<?= $remainingExpectedAmount ?>"
												title="Transfer to main commissions">
												<i class="fa fa-arrow-right"></i>
											</button>
										<?php elseif ($status === 'PENDING' || $status === 'PARTIALLY_TRANSFERRED'): ?>
											<button class="btn btn-sm btn-outline-secondary transfer-single-btn disabled" 
												disabled
												title="Transfer is restricted to your own commissions"
												type="button"
												aria-label="Transfer unavailable">
												<i class="fa fa-arrow-right"></i>
											</button>
										<?php endif; ?>
										<?php if ($isPendingApproval): ?>
											<button class="btn btn-sm btn-outline-warning approve-commission-btn" data-commission-id="<?= (int)$commission->id ?>" title="Approve and credit cashdrawer">
												<i class="fa fa-check"></i>
											</button>
										<?php endif; ?>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<nav aria-label="Commission pagination" class="mt-3">
				<ul class="pagination justify-content-center mb-0" id="commissionPagination"></ul>
			</nav>
		<?php else: ?>
			<div class="commission-empty">
				<i class="fa fa-inbox fa-3x mb-3 text-muted"></i>
				<p>No commissions found. Commissions will appear here when you complete cashout or withdrawal transactions.</p>
			</div>
		<?php endif; ?>
	</div>
</div>

<!-- Bulk Transfer Modal -->
<div class="modal fade" id="bulkTransferModal" tabindex="-1" aria-labelledby="bulkTransferLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header commission-modal-header border-0">
				<h5 class="modal-title" id="bulkTransferLabel"><i class="fa fa-exchange-alt me-2"></i>Transfer Commissions</h5>
				<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<div class="mb-3">
					<label for="cashoutWhatsappAdmins" class="form-label"><i class="fa fa-whatsapp text-success me-1"></i>Notify admins by WhatsApp</label>
					<select id="cashoutWhatsappAdmins" class="form-select" multiple size="<?= max(2, min(5, count($activeWhatsappAdmins))) ?>">
						<?php foreach ($activeWhatsappAdmins as $admin): ?>
							<option value="<?= (int)$admin->id ?>" selected><?= esc(trim((string)($admin->first_name ?? '') . ' ' . (string)($admin->last_name ?? ''))) ?></option>
						<?php endforeach; ?>
					</select>
					<div class="form-text">Only administrators with an active cashdrawer today are listed.</div>
				</div>
				<div class="commission-transfer-form">
					<h6 class="mb-3"><i class="fa fa-layer-group me-1"></i>Selected commission groups</h6>
					<div id="selectedCommissionsList" class="mb-3">
						<!-- Populated by JS -->
					</div>
					<div class="alert alert-info mb-0" id="selectedSummary">
						<strong><i class="fa fa-bullseye me-1"></i>Total Remaining Expected:</strong> <span id="selectedTotalExpectedAmount">0.00</span> GHS
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fa fa-times me-1"></i>Cancel</button>
				<button type="button" class="btn btn-primary" id="confirmTransferBtn">
					<i class="fa fa-check me-2"></i> Confirm Transfer
				</button>
			</div>
		</div>
	</div>
</div>

<!-- Commission Details Modal -->
<div class="modal fade" id="commissionDetailsModal" tabindex="-1" aria-labelledby="commissionDetailsLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header commission-modal-header border-0">
				<h5 class="modal-title" id="commissionDetailsLabel"><i class="fa fa-info-circle me-2"></i>Commission Details</h5>
				<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<div id="commissionDetailsContent">
					<!-- Populated by JS -->
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fa fa-times me-1"></i>Close</button>
			</div>
		</div>
	</div>
</div>


<?php if ($canViewAllCommissions && !empty($pendingApprovalGroups)): ?>
<div class="modal fade" id="commissionApprovalModal" tabindex="-1" aria-labelledby="commissionApprovalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header commission-modal-header border-0">
				<h5 class="modal-title" id="commissionApprovalLabel"><i class="fa fa-check-double me-2"></i>Review Pending Commissions</h5>
				<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<div class="mb-3">
					<label for="reviewWhatsappAdmins" class="form-label"><i class="fa fa-whatsapp text-success me-1"></i>Notify admins by WhatsApp</label>
					<select id="reviewWhatsappAdmins" class="form-select" multiple size="<?= max(2, min(5, count($activeWhatsappAdmins))) ?>">
						<?php foreach ($activeWhatsappAdmins as $admin): ?>
							<option value="<?= (int)$admin->id ?>" selected><?= esc(trim((string)($admin->first_name ?? '') . ' ' . (string)($admin->last_name ?? ''))) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div id="commissionApprovalGroups"></div>
				<label for="commissionRejectionReason" class="form-label mt-3">Rejection reason</label>
				<textarea id="commissionRejectionReason" class="form-control" rows="3" placeholder="Required when rejecting selected commissions"></textarea>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-outline-danger" id="rejectSelectedCommissions"><i class="fa fa-times me-1"></i>Reject Selected</button>
				<button type="button" class="btn btn-outline-success" id="approveAllPendingCommissions"><i class="fa fa-check-double me-1"></i>Approve All Pending</button>
				<button type="button" class="btn btn-success" id="approveSelectedCommissions"><i class="fa fa-check me-1"></i>Approve Selected</button>
			</div>
		</div>
	</div>
</div>
<?php endif; ?>


<!-- ANALYTICS MODAL -->
<div class="modal fade" id="analyticsModal" tabindex="-1" aria-labelledby="analyticsModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-xl modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header bg-primary text-white">
				<h5 class="modal-title" id="analyticsModalLabel"><i class="fa fa-chart-pie me-2"></i>Commissions Performance & Analytics</h5>
				<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<!-- Chart Row -->
				<div class="row g-3 mb-4">
					<div class="col-md-4">
						<div class="card h-100 shadow-sm">
							<div class="card-body">
								<h6 class="card-title text-muted fw-bold"><i class="fa fa-wifi me-1 text-primary"></i>By Network</h6>
								<div class="chart-container"><canvas id="chartNetwork"></canvas></div>
							</div>
						</div>
					</div>
					<div class="col-md-4">
						<div class="card h-100 shadow-sm">
							<div class="card-body">
								<h6 class="card-title text-muted fw-bold"><i class="fa fa-users me-1 text-success"></i>Top Users Ranking</h6>
								<div class="chart-container"><canvas id="chartUsers"></canvas></div>
							</div>
						</div>
					</div>
					<div class="col-md-4">
						<div class="card h-100 shadow-sm">
							<div class="card-body">
								<h6 class="card-title text-muted fw-bold"><i class="fa fa-tags me-1 text-warning"></i>By Commission Type</h6>
								<div class="chart-container"><canvas id="chartTypes"></canvas></div>
							</div>
						</div>
					</div>
				</div>

				<!-- Data Tables Tabs -->
				<ul class="nav nav-tabs" id="analyticsTabs" role="tablist">
					<li class="nav-item"><button class="nav-link active" id="user-tab" data-bs-toggle="tab" data-bs-target="#user-pane">User Performance</button></li>
					<li class="nav-item"><button class="nav-link" id="network-tab" data-bs-toggle="tab" data-bs-target="#network-pane">Network Breakdown</button></li>
					<li class="nav-item"><button class="nav-link" id="type-tab" data-bs-toggle="tab" data-bs-target="#type-pane">Commission Type Summary</button></li>
				</ul>
				<div class="tab-content pt-3" id="analyticsTabContent">
					<!-- User Tab -->
					<div class="tab-pane fade show active" id="user-pane">
						<div class="table-responsive">
							<table class="table table-striped align-middle" id="analyticsUserTable">
								<thead>
									<tr><th>User</th><th>Type</th><th>Txs</th><th>Volume</th><th>Commission</th></tr>
								</thead>
								<tbody></tbody>
							</table>
						</div>
					</div>
					<!-- Network Tab -->
					<div class="tab-pane fade" id="network-pane">
						<div class="table-responsive">
							<table class="table table-striped align-middle" id="analyticsNetworkTable">
								<thead>
									<tr><th>Network</th><th>Txs</th><th>Volume</th><th>Commission</th><th>Expected</th></tr>
								</thead>
								<tbody></tbody>
							</table>
						</div>
					</div>
					<!-- Type Tab -->
					<div class="tab-pane fade" id="type-pane">
						<div class="table-responsive">
							<table class="table table-striped align-middle" id="analyticsTypeTable">
								<thead>
									<tr><th>Commission Type</th><th>Txs</th><th>Volume</th><th>Commission</th><th>Expected</th></tr>
								</thead>
								<tbody></tbody>
							</table>
						</div>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	const adminRoute = '<?= $admin_route ?? 'admin' ?>';
	const pluginRoute = '<?= $plugin_route ?? 'momoremit' ?>';
	const apiEndpoint = '<?=ROOT?>/' + adminRoute + '/' + pluginRoute + '/commission-api';
	const pendingApprovalGroups = <?= json_encode($pendingApprovalGroups, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
	const defaultPageLimit = 10;
	let currentPage = 1;
	let commissionActionBusy = false;

	const searchInput = document.getElementById('commissionSearch');
	const statusFilter = document.getElementById('commissionStatusFilter');
	const networkFilter = document.getElementById('commissionNetworkFilter');
	const dateFromFilter = document.getElementById('commissionDateFrom');
	const dateToFilter = document.getElementById('commissionDateTo');
	const creatorFilter = document.getElementById('commissionCreatorFilter');
	const pageLimitFilter = document.getElementById('commissionPageLimit');
	const resetFiltersBtn = document.getElementById('commissionResetFilters');
	const exportPdfBtn = document.getElementById('commissionExportPdf');
    const openAnalyticsModalBtn = document.getElementById('openAnalyticsModalBtn');

	function getVisibleRows() {
		const rows = Array.from(document.querySelectorAll('.commission-row'));
		const term = (searchInput?.value || '').trim().toLowerCase();
		const status = (statusFilter?.value || '').trim().toUpperCase();
		const network = (networkFilter?.value || '').trim().toUpperCase();
		const createdBy = (creatorFilter?.value || '').trim().toLowerCase();
		const fromDate = dateFromFilter?.value || '';
		const toDate = dateToFilter?.value || '';

		return rows.filter(row => {
			const text = (row.dataset.commissionSearch || '').toLowerCase();
			const rowStatus = (row.dataset.status || '').toUpperCase();
			const rowNetwork = (row.dataset.network || '').toUpperCase();
			const rowCreatedBy = (row.dataset.createdBy || '').toLowerCase();
			const rowDate = (row.dataset.dateCreated || '').slice(0, 10);

			if (term && !text.includes(term)) return false;
			if (status && rowStatus !== status) return false;
			if (network && rowNetwork !== network) return false;
			if (createdBy && !rowCreatedBy.includes(createdBy)) return false;
			if (fromDate && rowDate < fromDate) return false;
			if (toDate && rowDate > toDate) return false;
			return true;
		});
	}

    function updateMetrics(rows) {
    	let pendingCount = 0;
    	let pendingAmount = 0;
    	let transferredCount = 0;
    	let transferredAmount = 0;
    	const typeStats = {};
    
    	rows.forEach(row => {
    		const status = (row.dataset.status || '').toUpperCase();
    		const typeName = (row.dataset.commissionType || '').trim().toUpperCase();
    		const remainingExpected = parseFloat(row.dataset.remainingExpected) || 0;
    		const expectedAmount = parseFloat(row.dataset.expectedAmount) || 0;
    		const transferredRowAmount = parseFloat(row.dataset.transferredAmount) || 0;
    
    		if (!typeStats[typeName]) {
    			typeStats[typeName] = {
    				pendingAmount: 0,
    				pendingCount: 0,
    				transferredAmount: 0,
    				transferredCount: 0
    			};
    		}
    
    		if (status === 'PENDING' || status === 'PARTIALLY_TRANSFERRED') {
    			pendingCount++;
    			pendingAmount += remainingExpected;
    			typeStats[typeName].pendingCount++;
    			typeStats[typeName].pendingAmount += remainingExpected;
    		}
    		
    		if (status === 'TRANSFERRED' || status === 'PARTIALLY_TRANSFERRED') {
    			transferredCount++;
    			transferredAmount += transferredRowAmount;
    			typeStats[typeName].transferredCount++;
    			typeStats[typeName].transferredAmount += transferredRowAmount;
    		}
    	});
    
    	const metricPendingAmtEl = document.getElementById('metricPendingAmount');
    	const metricPendingCntEl = document.getElementById('metricPendingCount');
    	const metricTransferredAmtEl = document.getElementById('metricTransferredAmount');
    	const metricTransferredCntEl = document.getElementById('metricTransferredCount');
    
    	if (metricPendingAmtEl) metricPendingAmtEl.textContent = 'GHS ' + pendingAmount.toFixed(2);
    	if (metricPendingCntEl) metricPendingCntEl.textContent = pendingCount;
    	if (metricTransferredAmtEl) metricTransferredAmtEl.textContent = 'GHS ' + transferredAmount.toFixed(2);
    	if (metricTransferredCntEl) metricTransferredCntEl.textContent = transferredCount;
    
    	document.querySelectorAll('.type-card-item').forEach(card => {
    		const typeName = (card.dataset.typeName || '').trim().toUpperCase();
    		const stats = typeStats[typeName] || { pendingAmount: 0, pendingCount: 0, transferredAmount: 0, transferredCount: 0 };
    		
    		const pendingAmtEl = card.querySelector('.card-type-pending');
    		const pendingCntEl = card.querySelector('.card-type-pending-count');
    		const transferredAmtEl = card.querySelector('.card-type-transferred');
    		const transferredCntEl = card.querySelector('.card-type-transferred-count');
    
    		if (pendingAmtEl) pendingAmtEl.textContent = 'GHS ' + stats.pendingAmount.toFixed(2);
    		if (pendingCntEl) pendingCntEl.textContent = stats.pendingCount;
    		if (transferredAmtEl) transferredAmtEl.textContent = 'GHS ' + stats.transferredAmount.toFixed(2);
    		if (transferredCntEl) transferredCntEl.textContent = stats.transferredCount;
    	});
    }

	function renderCommissionPagination(rows) {
		const paginator = document.getElementById('commissionPagination');
		if (!paginator) return;
		const limit = Number(pageLimitFilter?.value || defaultPageLimit);
		const totalPages = Math.max(1, Math.ceil(rows.length / limit));
		currentPage = Math.min(currentPage, totalPages);
		paginator.innerHTML = '';

		const makeButton = (label, page, disabled = false, active = false) => {
			const li = document.createElement('li');
			li.className = 'page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '');
			const btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'page-link';
			btn.textContent = label;
			if (!disabled) btn.dataset.page = String(page);
			li.appendChild(btn);
			return li;
		};

		paginator.appendChild(makeButton('«', currentPage - 1, currentPage <= 1));
		for (let page = 1; page <= totalPages; page++) {
			paginator.appendChild(makeButton(String(page), page, false, page === currentPage));
		}
		paginator.appendChild(makeButton('»', currentPage + 1, currentPage >= totalPages));

		paginator.querySelectorAll('button[data-page]').forEach(btn => {
			btn.addEventListener('click', () => {
				currentPage = Number(btn.dataset.page || currentPage);
				displayCommissionPage();
			});
		});
	}

	function displayCommissionPage() {
		const rows = getVisibleRows();
		updateMetrics(rows);

		const limit = Number(pageLimitFilter?.value || defaultPageLimit);
		const totalPages = Math.max(1, Math.ceil(rows.length / limit));
		currentPage = Math.min(currentPage, totalPages);
		const start = (currentPage - 1) * limit;
		const end = start + limit;

		Array.from(document.querySelectorAll('.commission-row')).forEach(row => {
			row.style.display = 'none';
		});
		rows.slice(start, end).forEach(row => {
			row.style.display = '';
		});

		renderCommissionPagination(rows);
	}

	function applyCommissionFilters() {
		currentPage = 1;
		displayCommissionPage();
	}

	function setCommissionActionBusy(button, busy, label) {
		if (!button) return;
		button.disabled = busy;
		button.dataset.originalHtml = button.dataset.originalHtml || button.innerHTML;
		button.innerHTML = busy ? '<i class="fa fa-spinner fa-spin me-1"></i>' + (label || 'Submitting...') : button.dataset.originalHtml;
	}

	function showCashoutWhatsappModal(whatsapp, onClose) {
		const links = Array.isArray(whatsapp?.links) ? whatsapp.links.filter(item => item && item.url) : [];
		if (!window.Swal || typeof window.Swal.fire !== 'function') {
			onClose?.();
			return;
		}
		const buttons = links.map(item => `<a class="btn btn-success btn-sm m-1" target="_blank" rel="noopener" href="${escapeAttribute(item.url)}"><i class="fa fa-whatsapp me-1"></i>Submit to ${escapeHtml(item.label || item.phone || 'Admin')}</a>`).join('');
		const reasons = Array.isArray(whatsapp?.reasons) && whatsapp.reasons.length
			? '<div class="alert alert-warning text-start mt-2 mb-0">' + whatsapp.reasons.map(escapeHtml).join('<br>') + '</div>'
			: '';
		const options = {
			icon: 'info',
			title: links.length ? 'WhatsApp message ready' : 'WhatsApp notification status',
			html: (links.length ? '<p>Select an admin button to open WhatsApp and submit the transfer notification.</p>' : '<p>No WhatsApp message link was prepared.</p>') + '<div>' + buttons + '</div>' + reasons,
			confirmButtonText: 'Continue',
			allowOutsideClick: false,
		};
		if (typeof window.basicAdminSwalTheme === 'function') Object.assign(options, window.basicAdminSwalTheme());
		window.Swal.fire(options).then(() => onClose?.());
	}

	// INITIAL LOAD CALL: Triggers initial page pagination and per-page limits on first render
	displayCommissionPage();

	[searchInput, statusFilter, networkFilter, dateFromFilter, dateToFilter, creatorFilter, pageLimitFilter].forEach(element => {
		element?.addEventListener('input', applyCommissionFilters);
		element?.addEventListener('change', applyCommissionFilters);
	});

	resetFiltersBtn?.addEventListener('click', function() {
		if (searchInput) searchInput.value = '';
		if (statusFilter) statusFilter.value = '';
		if (networkFilter) networkFilter.value = '';
		if (dateFromFilter) dateFromFilter.value = '';
		if (dateToFilter) dateToFilter.value = '';
		if (creatorFilter) creatorFilter.value = '';
		if (pageLimitFilter) pageLimitFilter.value = String(defaultPageLimit);
		currentPage = 1;
		displayCommissionPage();
	});
	
	// 1. Declare chart instances globally at top of script scope
	let chartNetworkInst = null;
	let chartUsersInst = null;
	let chartTypesInst = null;

	// Keep reference to generated analytics data
	let cachedAnalyticsData = null;
	const analyticsModalEl = document.getElementById('analyticsModal');
	analyticsModalEl?.addEventListener('shown.bs.modal', function () {
		if (!cachedAnalyticsData) return;
		renderCharts(
			cachedAnalyticsData.networkMap,
			cachedAnalyticsData.userMap,
			cachedAnalyticsData.typeMap
		);
	});

	// ANALYTICS MODAL & CHARTS LOGIC
	openAnalyticsModalBtn?.addEventListener('click', function() {
		const rows = getVisibleRows();
		const analyticsModal = bootstrap.Modal.getOrCreateInstance(analyticsModalEl);

		// Aggregations
		const userMap = {}, networkMap = {}, typeMap = {};

		rows.forEach(row => {
			const user = row.dataset.createdBy || 'Unknown';
			const net = row.dataset.network || 'UNKNOWN';
			const type = row.dataset.commissionType || 'General';
			const comm = parseFloat(row.dataset.amount || 0);
			const trans = parseFloat(row.dataset.transAmount || 0);
			const exp = parseFloat(row.dataset.expectedAmount || 0);

			const uKey = `${user}__${type}__${net}`;
			if (!userMap[uKey]) userMap[uKey] = { user, type, net, count: 0, trans: 0, comm: 0, exp: 0 };
			userMap[uKey].count++; userMap[uKey].trans += trans; userMap[uKey].comm += comm; userMap[uKey].exp += exp;

			if (!networkMap[net]) networkMap[net] = { count: 0, trans: 0, comm: 0, exp: 0 };
			networkMap[net].count++; networkMap[net].trans += trans; networkMap[net].comm += comm; networkMap[net].exp += exp;

			if (!typeMap[type]) typeMap[type] = { count: 0, trans: 0, comm: 0, exp: 0 };
			typeMap[type].count++; typeMap[type].trans += trans; typeMap[type].comm += comm; typeMap[type].exp += exp;
		});

		cachedAnalyticsData = { userMap, networkMap, typeMap };
		populateAnalyticsTables(userMap, networkMap, typeMap);
		analyticsModal.show();
	});
	

    function populateAnalyticsTables(userMap, networkMap, typeMap) {
		// User Table (Sorted Descending by Commission)
		const userSorted = Object.values(userMap).sort((a,b) => b.exp - a.exp);
		document.querySelector('#analyticsUserTable tbody').innerHTML = userSorted.map(u => 
			`<tr><td><i class="fas fa-user me-1 text-success"></i> ${u.user}</td><td><div>${u.type}</div></td><td>${u.count}</td><td>${u.trans.toFixed(2)}</td><td><div><code>EXP: </code>${u.exp.toFixed(2)}</div></td></tr>`
		).join('');

		// Network Table
		const networkSorted = Object.entries(networkMap).sort(([, a], [, b]) => b.exp - a.exp);
		document.querySelector('#analyticsNetworkTable tbody').innerHTML = networkSorted.map(([label, n]) => {
			return `<tr><td><strong><i class="fa fa-wifi me-1 text-primary"></i> ${label}</strong></td><td>${n.count}</td><td> ${n.trans.toFixed(2)}</td><td class="text-warning fw-bold">${n.comm.toFixed(2)}</td><td>${n.exp.toFixed(2)}</td></tr>`;
		}).join('');

		// Type Table
		const typeSorted = Object.entries(typeMap).sort(([, a], [, b]) => b.exp - a.exp);
		document.querySelector('#analyticsTypeTable tbody').innerHTML = typeSorted.map(([label, t]) => {
			return `<tr><td><strong><i class="fa fa-tags me-1 text-warning"></i> ${label}</strong></td><td>${t.count}</td><td>${t.trans.toFixed(2)}</td><td class="text-warning fw-bold">${t.comm.toFixed(2)}</td><td>${t.exp.toFixed(2)}</td></tr>`;
		}).join('');
	}

	function renderCharts(networkMap, userMap, typeMap) {
		if (chartNetworkInst) chartNetworkInst.destroy();
		if (chartUsersInst) chartUsersInst.destroy();
		if (chartTypesInst) chartTypesInst.destroy();

		const networkCanvas = document.getElementById('chartNetwork');
		const usersCanvas = document.getElementById('chartUsers');
		const typesCanvas = document.getElementById('chartTypes');
		if (!networkCanvas || !usersCanvas || !typesCanvas) return;

		const networkLabels = Object.keys(networkMap || {});
		const typeLabels = Object.keys(typeMap || {});
		const userSummary = {};
		Object.values(userMap || {}).forEach(u => {
			userSummary[u.user] = (userSummary[u.user] || 0) + (Number(u.exp) || 0);
		});
		const topUsers = Object.keys(userSummary).sort((a, b) => userSummary[b] - userSummary[a]).slice(0, 5);

		chartNetworkInst = new Chart(networkCanvas, {
			type: 'doughnut',
			data: {
				labels: networkLabels,
				datasets: [{
					data: networkLabels.map(label => Number((networkMap[label]?.exp) || 0)),
					backgroundColor: ['#686fe8', '#8060b6', '#3b82f6', '#10b981', '#f59e0b', '#ef4444']
				}]
			},
			options: { responsive: true, maintainAspectRatio: false, animation: false }
		});

		chartUsersInst = new Chart(usersCanvas, {
			type: 'bar',
			data: {
				labels: topUsers,
				datasets: [{
					label: 'Expected Total (GHS)',
					data: topUsers.map(user => Number(userSummary[user] || 0)),
					backgroundColor: '#10b981'
				}]
			},
			options: { responsive: true, maintainAspectRatio: false, animation: false }
		});

		chartTypesInst = new Chart(typesCanvas, {
			type: 'pie',
			data: {
				labels: typeLabels,
				datasets: [{
					data: typeLabels.map(label => Number((typeMap[label]?.exp) || 0)),
					backgroundColor: ['#f59e0b', '#ec4899', '#6366f1', '#14b8a6', '#ef4444']
				}]
			},
			options: { responsive: true, maintainAspectRatio: false, animation: false }
		});
	}

	// EXPORT TO PDF FUNCTIONALITY WITH RANKED PERFORMANCE & DETAILED ANALYTICS
	exportPdfBtn?.addEventListener('click', function() {
		if (!window.jspdf || !window.jspdf.jsPDF) {
			showAlert('Error', 'PDF generation library is not loaded', 'error');
			return;
		}

		const rows = getVisibleRows();
		if (rows.length === 0) {
			showAlert('Warning', 'No data available to export', 'warning');
			return;
		}

		const { jsPDF } = window.jspdf;
		const doc = new jsPDF('landscape');

		// Aggregators
		let totalTransAmount = 0;
		let totalCommissionAmount = 0;
		let totalExpectedAmount = 0;

		const userPerformanceMap = {}; 
		const typeNetworkMap = {}; 

		const tableData = rows.map(row => {
			const transAmt = parseFloat(row.dataset.transAmount || 0);
			const commAmt = parseFloat(row.dataset.amount || 0);
			const expAmt = parseFloat(row.dataset.expectedAmount || 0);
			const network = row.dataset.network || 'UNKNOWN';
			const commType = row.dataset.commissionType || 'General';
			const createdBy = row.dataset.createdBy || 'Unknown User';

			totalTransAmount += transAmt;
			totalCommissionAmount += commAmt;
			totalExpectedAmount += expAmt;

			// 1. User Performance Grouping (User + Type + Network)
			const userKey = `${createdBy}__${commType}__${network}`;
			if (!userPerformanceMap[userKey]) {
				userPerformanceMap[userKey] = {
					userName: createdBy,
					commType: commType,
					network: network,
					count: 0,
					trans: 0,
					comm: 0,
					expected: 0
				};
			}
			userPerformanceMap[userKey].count += 1;
			userPerformanceMap[userKey].trans += transAmt;
			userPerformanceMap[userKey].comm += commAmt;
			userPerformanceMap[userKey].expected += expAmt;

			// 2. Type & Network Analytics Grouping
			const typeKey = `${commType}__${network}`;
			if (!typeNetworkMap[typeKey]) {
				typeNetworkMap[typeKey] = {
					commType: commType,
					network: network,
					count: 0,
					trans: 0,
					comm: 0,
					expected: 0
				};
			}
			typeNetworkMap[typeKey].count += 1;
			typeNetworkMap[typeKey].trans += transAmt;
			typeNetworkMap[typeKey].comm += commAmt;
			typeNetworkMap[typeKey].expected += expAmt;

			return [
				network,
				commType,
				`GHS ${transAmt.toFixed(2)} (${parseFloat(row.dataset.rate || 0).toFixed(2)}%)`,
				`GHS ${commAmt.toFixed(2)}`,
				`GHS ${expAmt.toFixed(2)}`,
				row.dataset.status || '',
				createdBy,
				row.dataset.dateCreated || ''
			];
		});

		// --- 1. Header ---
		doc.setFontSize(16);
		doc.setFont(undefined, 'bold');
		doc.text('Cashout Commission Report', 14, 15);
		
		doc.setFontSize(9);
		doc.setFont(undefined, 'normal');
		doc.text(`Generated on: ${new Date().toLocaleString()}`, 14, 21);

		// --- 2. Summary Metrics Box ---
		doc.setDrawColor(200, 200, 200);
		doc.setFillColor(245, 247, 250);
		doc.roundedRect(14, 25, 269, 16, 2, 2, 'FD');

		doc.setFontSize(9);
		doc.setFont(undefined, 'bold');
		doc.text(`Total Records: ${rows.length}`, 18, 31);
		doc.text(`Total Transaction Vol: GHS ${totalTransAmount.toFixed(2)}`, 70, 31);
		doc.text(`Total Commission: GHS ${totalCommissionAmount.toFixed(2)}`, 145, 31);
		doc.text(`Total Expected Value: GHS ${totalExpectedAmount.toFixed(2)}`, 215, 31);

		// --- 3. Ranked User Performance Analysis ---
		// Convert to array and sort descending by commission amount
		const userPerfSorted = Object.values(userPerformanceMap).sort((a, b) => {
			if (a.commType !== b.commType) {
				return a.commType.localeCompare(b.commType); // Group by Commission Type
			}
			return b.comm - a.comm; // Descending per Commission Type
		});

		const userPerfTableData = userPerfSorted.map(item => [
			item.userName,
			item.commType,
			item.network,
			item.count,
			`GHS ${item.trans.toFixed(2)}`,
			`GHS ${item.comm.toFixed(2)}`,
			`GHS ${item.expected.toFixed(2)}`
		]);

		doc.setFontSize(11);
		doc.setFont(undefined, 'bold');
		doc.text('User Performance Ranking (Descending by Commission)', 14, 49);

		doc.autoTable({
			head: [['User', 'Commission Type', 'Network', 'Tx Count', 'Volume', 'Commission', 'Expected Value']],
			body: userPerfTableData,
			startY: 53,
			theme: 'striped',
			styles: { fontSize: 8, cellPadding: 2 },
			headStyles: { fillColor: [128, 96, 182], textColor: [255, 255, 255], fontStyle: 'bold' },
			margin: { left: 14, right: 14 }
		});

		// --- 4. Detailed Commission Log Table ---
		let nextY = doc.lastAutoTable.finalY + 10;
		doc.setFontSize(11);
		doc.setFont(undefined, 'bold');
		doc.text('Detailed Commission Log', 14, nextY);

		doc.autoTable({
			head: [['Network', 'Type', 'Transaction', 'Commission Amount', 'Expected Value', 'Status', 'Created By', 'Date Created']],
			body: tableData,
			startY: nextY + 4,
			theme: 'grid',
			styles: { fontSize: 8, cellPadding: 2.5 },
			headStyles: { fillColor: [104, 111, 232], textColor: [255, 255, 255], fontStyle: 'bold' },
			alternateRowStyles: { fillColor: [250, 250, 250] },
			foot: [[
				'TOTALS', 
				'', 
				`GHS ${totalTransAmount.toFixed(2)}`, 
				`GHS ${totalCommissionAmount.toFixed(2)}`, 
				`GHS ${totalExpectedAmount.toFixed(2)}`, 
				'', '', ''
			]],
			footStyles: { fillColor: [240, 240, 240], textColor: [0, 0, 0], fontStyle: 'bold' },
			margin: { left: 14, right: 14 }
		});

		// --- 5. Additional Bottom Analytics: Type & Network Distribution ---
		nextY = doc.lastAutoTable.finalY + 10;
		
		// If table exceeds page boundary, start clean on next page
		if (nextY + 40 > doc.internal.pageSize.getHeight()) {
			doc.addPage();
			nextY = 15;
		}

		doc.setFontSize(11);
		doc.setFont(undefined, 'bold');
		doc.text('Network & Commission Type Analytics Summary', 14, nextY);

		const typeNetworkSorted = Object.values(typeNetworkMap).sort((a, b) => b.comm - a.comm);
		const typeNetworkTableData = typeNetworkSorted.map(item => {
			const shareOfTotal = totalCommissionAmount > 0 ? ((item.comm / totalCommissionAmount) * 100).toFixed(1) : '0.0';
			return [
				item.commType,
				item.network,
				item.count,
				`GHS ${item.trans.toFixed(2)}`,
				`GHS ${item.comm.toFixed(2)}`,
				`GHS ${item.expected.toFixed(2)}`,
				`${shareOfTotal}%`
			];
		});

		doc.autoTable({
			head: [['Commission Type', 'Network', 'Total Txs', 'Total Volume', 'Total Commission', 'Expected Value', 'Commission Share']],
			body: typeNetworkTableData,
			startY: nextY + 4,
			theme: 'grid',
			styles: { fontSize: 8, cellPadding: 2 },
			headStyles: { fillColor: [71, 85, 105], textColor: [255, 255, 255], fontStyle: 'bold' },
			margin: { left: 14, right: 14 }
		});

		// --- 6. Open PDF in a New Tab ---
		const stringData = doc.output('bloburl');
		window.open(stringData, '_blank');
	});
	
	const openBulkTransferBtn = document.getElementById('openBulkTransferBtn');
	openBulkTransferBtn?.addEventListener('click', function() {
		const pendingCheckboxes = document.querySelectorAll('.commission-checkbox:not(:disabled)');
		if (pendingCheckboxes.length > 0) {
			pendingCheckboxes.forEach(checkbox => {
				checkbox.checked = true;
			});
		}
		const selectAll = document.getElementById('selectAllCommissions');
		if (selectAll) {
			selectAll.checked = pendingCheckboxes.length > 0;
		}
		updateBulkTransferUI();
	});

	// Select all checkboxes
	document.getElementById('selectAllCommissions')?.addEventListener('change', function(e) {
		document.querySelectorAll('.commission-checkbox:not(:disabled)').forEach(checkbox => {
			const row = checkbox.closest('tr');
			if (row && row.style.display !== 'none') {
				checkbox.checked = e.target.checked;
			}
		});
		updateBulkTransferUI();
	});

	// Individual checkbox changes
	document.querySelectorAll('.commission-checkbox').forEach(checkbox => {
		checkbox.addEventListener('change', updateBulkTransferUI);
	});

	// View commission details
	document.querySelectorAll('.view-commission-btn').forEach(btn => {
		btn.addEventListener('click', function() {
			const commissionId = this.dataset.commissionId;
			viewCommissionDetails(commissionId);
		});
	});

		document.querySelectorAll('.approve-commission-btn').forEach(btn => {
			btn.addEventListener('click', function() {
				if (commissionActionBusy) return;
				const commissionId = this.dataset.commissionId;
				showConfirm('Approve this commission and credit the transferring user\'s network cashdrawer?', 'Approve Commission')
					.then(confirmed => {
						if (!confirmed) return;
						commissionActionBusy = true;
						setCommissionActionBusy(this, true, 'Approving...');
						const formData = new FormData();
						formData.append('action', 'approve');
						formData.append('cashout_commission_id', commissionId);
						formData.append('csrf', document.querySelector('input[name="csrf"]')?.value || '');
						document.querySelectorAll('#reviewWhatsappAdmins option:checked').forEach(option => {
							formData.append('whatsapp_admin_ids[]', option.value);
						});
						return fetch(apiEndpoint, {
							method: 'POST',
							headers: {
								'Accept': 'application/json',
								'X-Requested-With': 'XMLHttpRequest'
							},
							credentials: 'include',
							body: formData
						}).then(parseJsonResponse);
					})
					.then(data => {
						if (data) {
							const finishApproval = () => showAlert('Success', data.message || 'Commission approved successfully.', 'success').then(() => location.reload());
							showCashoutWhatsappModal(data.whatsapp, finishApproval);
						}
					})
					.catch(error => {
						commissionActionBusy = false;
						setCommissionActionBusy(this, false);
						return showAlert('Error', error.message || 'Commission approval failed.', 'error');
					});
			});
		});

		const approvalGroupsContainer = document.getElementById('commissionApprovalGroups');
		if (approvalGroupsContainer) {
			approvalGroupsContainer.innerHTML = pendingApprovalGroups.map((group, groupIndex) => `
				<div class="border rounded p-3 mb-3">
					<div class="d-flex justify-content-between align-items-center mb-2">
						<div><strong>${escapeHtml(group.network || 'Unknown network')}</strong><div class="small text-muted">${escapeHtml(group.type || 'Unknown type')}</div></div>
						<div class="text-warning fw-semibold">Transfer amount: GHS ${Number(group.amount || 0).toFixed(2)}</div>
					</div>
					${Object.values(group.proof_images || {}).some(image => image) ? `<div class="mb-2"><img src="<?=ROOT?>/${escapeAttribute(Object.values(group.proof_images).find(image => image) || '')}" alt="Transfer proof" style="max-width:120px;max-height:90px;object-fit:cover;border-radius:6px;border:1px solid rgba(148,163,184,.35);"></div>` : ''}
					<div class="small text-muted mb-2">${(group.ids || []).length} pending commission(s)</div>
					${(group.ids || []).map(id => `
						<label class="d-flex align-items-center gap-2 mb-1">
							<input type="checkbox" class="form-check-input approval-commission-checkbox" value="${Number(id)}" checked>
							<span>Cashout commission #${Number(id)}: <strong>GHS ${Number((group.amounts || {})[id] || 0).toFixed(2)}</strong></span>
						</label>
					`).join('')}
				</div>
			`).join('');
		}

		function reviewSelectedCommissions(decision) {
			if (commissionActionBusy) return;
			const selected = Array.from(document.querySelectorAll('.approval-commission-checkbox:checked')).map(input => input.value);
			if (!selected.length) {
				showAlert('Error', 'Select at least one pending commission.', 'error');
				return;
			}
			const reason = document.getElementById('commissionRejectionReason')?.value.trim() || '';
			if (decision === 'reject' && !reason) {
				showAlert('Error', 'Enter a reason before rejecting commissions.', 'error');
				return;
			}
			const reviewButton = document.getElementById(decision === 'approve' ? 'approveSelectedCommissions' : 'rejectSelectedCommissions');
			commissionActionBusy = true;
			setCommissionActionBusy(reviewButton, true, decision === 'approve' ? 'Approving...' : 'Rejecting...');
			const formData = new FormData();
			formData.append('action', 'review');
			formData.append('decision', decision);
			formData.append('reason', reason);
			formData.append('csrf', document.querySelector('input[name="csrf"]')?.value || '');
			selected.forEach(id => formData.append('cashout_commission_ids[]', id));
			document.querySelectorAll('#reviewWhatsappAdmins option:checked').forEach(option => {
				formData.append('whatsapp_admin_ids[]', option.value);
			});
			fetch(apiEndpoint, {
				method: 'POST',
				headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
				credentials: 'include',
				body: formData
			}).then(parseJsonResponse).then(data => {
				const finishReview = () => showAlert(data.partial ? 'Partially completed' : 'Success', data.message, data.partial ? 'warning' : 'success').then(() => location.reload());
				showCashoutWhatsappModal(data.whatsapp, finishReview);
			})
				.catch(error => {
					commissionActionBusy = false;
					setCommissionActionBusy(reviewButton, false);
					return showAlert('Error', error.message || 'Review failed.', 'error');
				});
		}

		document.getElementById('approveSelectedCommissions')?.addEventListener('click', () => reviewSelectedCommissions('approve'));
		document.getElementById('approveAllPendingCommissions')?.addEventListener('click', () => {
			document.querySelectorAll('.approval-commission-checkbox').forEach(input => {
				input.checked = true;
			});
			reviewSelectedCommissions('approve');
		});
		document.getElementById('rejectSelectedCommissions')?.addEventListener('click', () => reviewSelectedCommissions('reject'));

	// Transfer single commission
	document.querySelectorAll('.transfer-single-btn').forEach(btn => {
		btn.addEventListener('click', function() {
			if (commissionActionBusy) return;
			commissionActionBusy = true;
			setCommissionActionBusy(this, true, 'Submitting...');
			const commissionId = this.dataset.commissionId;
			const remainingExpected = parseFloat(this.dataset.remainingExpected || '0');
			transferSingleCommission(commissionId, remainingExpected, this);
		});
	});

	// Confirm bulk transfer
	document.getElementById('confirmTransferBtn')?.addEventListener('click', function() {
		if (commissionActionBusy) return;
		const selectedCheckboxes = document.querySelectorAll('.commission-checkbox:checked');
		if (selectedCheckboxes.length === 0) {
			showAlert('Error', 'Please select at least one commission to transfer', 'error');
			return;
		}

		const commissionIds = Array.from(selectedCheckboxes).map(cb => cb.dataset.commissionId);
		commissionActionBusy = true;
		setCommissionActionBusy(this, true, 'Submitting...');
		transferCommissions(commissionIds, this);
	});

	function updateBulkTransferUI() {
		const selectedCheckboxes = document.querySelectorAll('.commission-checkbox:checked');
		const selectedList = document.getElementById('selectedCommissionsList');
		selectedList.innerHTML = '';
		let totalRemainingExpectedAmount = 0;
		const groupedMap = new Map();

		selectedCheckboxes.forEach(checkbox => {
			const row = checkbox.closest('tr');
			const network = (row?.dataset.network || '').trim();
			const type = (row?.dataset.commissionType || '').trim();
			const remainingExpected = parseFloat(checkbox.dataset.remainingExpected) || 0;
			const key = `${network}::${type}`;
			if (!groupedMap.has(key)) {
				groupedMap.set(key, {
					network,
					type,
					ids: [],
					remainingExpected: 0
				});
			}
			const group = groupedMap.get(key);
			group.ids.push(Number(checkbox.dataset.commissionId));
			group.remainingExpected += remainingExpected;
			totalRemainingExpectedAmount += remainingExpected;
		});

		if (groupedMap.size === 0) {
			selectedList.innerHTML = '<div class="text-muted"><i class="fa fa-info-circle me-1"></i>No commission rows selected.</div>';
		} else {
			const rowsHtml = Array.from(groupedMap.values()).map(group => {
				const groupIds = group.ids.join(',');
				const defaultTransfer = group.remainingExpected.toFixed(2);
				const groupKey = `${group.network}|${group.type}`;
				return `
					<div class="border rounded p-3 mb-2 bg-dark-subtle">
						<div class="row g-3 align-items-center">
							<div class="col-lg-5">
								<div class="fw-semibold"><i class="fa fa-wifi me-1 text-primary"></i>${escapeHtml(group.network)}</div>
								<div class="small text-muted mt-1"><i class="fa fa-tag me-1"></i>${escapeHtml(group.type)}</div>
								<div class="small text-warning mt-2"><i class="fa fa-bullseye me-1"></i>Remaining expected total: <strong>GHS ${group.remainingExpected.toFixed(2)}</strong></div>
							</div>
							<div class="col-lg-3">
								<label class="form-label small text-muted"><i class="fa fa-money-bill-wave me-1"></i>Amount to transfer</label>
								<input type="number" class="form-control bulk-transfer-amount" data-commission-ids="${groupIds}" data-remaining-expected="${group.remainingExpected}" step="0.01" min="0" value="${defaultTransfer}">
							</div>
							<div class="col-lg-4">
								<label class="form-label small text-muted"><i class="fa fa-file-image me-1"></i>Proof</label>
								<div class="d-flex align-items-center gap-2 flex-wrap">
									<input type="file" class="d-none bulk-proof-input" data-group-key="${escapeAttribute(groupKey)}" data-commission-ids="${escapeAttribute(groupIds)}" accept="image/*">
									<button type="button" class="btn btn-outline-secondary btn-sm bulk-proof-trigger" data-target-group="${escapeAttribute(groupKey)}"><i class="fa fa-upload me-1"></i>Choose proof</button>
									<div class="proof-preview"><span class="proof-placeholder">No proof</span></div>
								</div>
							</div>
						</div>
					</div>
				`;
			}).join('');
			selectedList.innerHTML = rowsHtml;
			selectedList.querySelectorAll('.bulk-proof-trigger').forEach(button => {
				button.addEventListener('click', function() {
					const input = this.parentElement?.querySelector('.bulk-proof-input');
					if (input) input.click();
				});
			});
			selectedList.querySelectorAll('.bulk-proof-input').forEach(input => {
				input.addEventListener('change', function() {
					const file = this.files && this.files.length ? this.files[0] : null;
					const preview = this.parentElement?.querySelector('.proof-preview');
					if (!preview) return;
					if (!file) {
						preview.innerHTML = '<span class="proof-placeholder">No proof</span>';
						return;
					}
					if (file.type.startsWith('image/')) {
						const reader = new FileReader();
						reader.onload = function(e) {
							preview.innerHTML = '<img src="' + e.target.result + '" alt="Proof thumbnail" />';
						};
						reader.readAsDataURL(file);
					} else {
						preview.innerHTML = '<span class="proof-placeholder">' + escapeHtml(file.name) + '</span>';
					}
				});
			});
		}

		document.getElementById('selectedTotalExpectedAmount').textContent = totalRemainingExpectedAmount.toFixed(2);

		const confirmBtn = document.getElementById('confirmTransferBtn');
		if (confirmBtn) {
			confirmBtn.disabled = selectedCheckboxes.length === 0;
		}
	}

	function escapeHtml(value) {
		return String(value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/\"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function escapeAttribute(value) {
		return escapeHtml(value).replace(/`/g, '&#96;');
	}

	function viewCommissionDetails(commissionId) {
		fetch(`${apiEndpoint}?action=details&id=${commissionId}`, {
			headers: {
				'Accept': 'application/json',
				'X-Requested-With': 'XMLHttpRequest'
			},
			credentials: 'include'
		})
			.then(parseJsonResponse)
			.then(data => {
				if (data.success && data.commission) {
					const comm = data.commission;
					const displayedCommissionAmount = comm.transferred_amount && Number(comm.transferred_amount) > 0
						? comm.transferred_amount
						: comm.commission_amount;
					const badgeClass = comm.status === 'TRANSFERRED' ? 'bg-success' : (comm.status === 'PARTIALLY_TRANSFERRED' ? 'bg-primary' : 'bg-warning');
					const html = `
						<table class="table table-sm">
							<tr>
								<th><i class="fa fa-wifi me-1 text-primary"></i>Network:</th>
								<td><strong>${comm.network}</strong></td>
							</tr>
							<tr>
								<th><i class="fa fa-tag me-1 text-secondary"></i>Commission Type:</th>
								<td>${comm.commission_type}</td>
							</tr>
							<tr>
								<th><i class="fa fa-exchange-alt me-1"></i>Transaction Amount:</th>
								<td class="text-end">GHS ${comm.transaction_amount}</td>
							</tr>
							<tr>
								<th><i class="fa fa-percent me-1"></i>Commission Rate:</th>
								<td class="text-center">${comm.commission_rate}%</td>
							</tr>
							<tr>
								<th><i class="fa fa-coins me-1"></i>Commission Amount:</th>
								<td class="text-end"><strong class="text-warning">GHS ${displayedCommissionAmount}</strong></td>
							</tr>
							<tr>
								<th><i class="fa fa-bullseye me-1"></i>Expected Value:</th>
								<td class="text-end">GHS ${comm.expected_value_amount}</td>
							</tr>
							<tr>
								<th><i class="fa fa-arrow-down me-1"></i>Transferred Amount:</th>
								<td class="text-end text-success">GHS ${comm.transferred_amount}</td>
							</tr>
							<tr>
								<th><i class="fa fa-info-circle me-1"></i>Status:</th>
								<td><span class="badge ${badgeClass}">${comm.status}</span></td>
							</tr>
							<tr>
								<th><i class="fa fa-calendar-alt me-1"></i>Date Created:</th>
								<td>${comm.date_created}</td>
							</tr>
							${comm.transferred_at ? `<tr>
								<th><i class="fa fa-clock me-1"></i>Transferred At:</th>
								<td>${comm.transferred_at}</td>
							</tr>` : ''}
						</table>
					`;
					document.getElementById('commissionDetailsContent').innerHTML = html;
					new bootstrap.Modal(document.getElementById('commissionDetailsModal')).show();
				} else {
					showAlert('Error', data.message || 'Failed to load commission details', 'error');
				}
			})
			.catch(err => {
				console.error('Error:', err);
				showAlert('Error', err.message || 'Error loading commission details', 'error');
			});
	}

	function transferSingleCommission(commissionId, amount, actionButton) {
		showConfirm(`Transfer remaining GHS ${amount.toFixed(2)} to main commissions?`, 'Confirm Transfer')
			.then(confirmed => {
				if (confirmed) return transferCommissions([commissionId], actionButton);
				commissionActionBusy = false;
				setCommissionActionBusy(actionButton, false);
			});
	}

	function transferCommissions(commissionIds, actionButton) {
		try {
		const formData = new FormData();
		formData.append('action', 'transfer');
		formData.append('csrf', document.querySelector('input[name="csrf"]')?.value || '');
		commissionIds.forEach(id => formData.append('commission_ids[]', id));
		document.querySelectorAll('#cashoutWhatsappAdmins option:checked').forEach(option => {
			formData.append('whatsapp_admin_ids[]', option.value);
		});
		const groupedAmounts = {};

		document.querySelectorAll('.bulk-transfer-amount').forEach(input => {
			const ids = (input.dataset.commissionIds || '').split(',').map(item => Number(item.trim())).filter(item => Number.isFinite(item) && item > 0);
			const totalInput = parseFloat(input.value || '0');
			const remainingExpected = parseFloat(input.dataset.remainingExpected || '0');

			if (totalInput > remainingExpected + 0.005) {
				throw new Error('One or more transfer values exceed the remaining expected total for the selected group.');
			}
			if (ids.length === 0) return;

			const totalGroupRemaining = ids.reduce((sum, id) => {
				const checkbox = document.querySelector(`.commission-checkbox[data-commission-id="${id}"]`);
				return sum + (parseFloat(checkbox?.dataset.remainingExpected || '0') || 0);
			}, 0);

			ids.forEach((id) => {
				const checkbox = document.querySelector(`.commission-checkbox[data-commission-id="${id}"]`);
				const rowRemaining = parseFloat(checkbox?.dataset.remainingExpected || '0') || 0;
				const share = totalGroupRemaining > 0 ? (rowRemaining / totalGroupRemaining) * totalInput : 0;
				groupedAmounts[id] = Math.min(Math.max(share, 0), rowRemaining);
			});
		});

		Object.keys(groupedAmounts).forEach(id => {
			formData.append(`transfer_amounts[${id}]`, groupedAmounts[id].toFixed(2));
		});

			document.querySelectorAll('.bulk-proof-input').forEach(input => {
			if (input.files && input.files.length > 0) {
					const ids = (input.dataset.commissionIds || '').split(',').map(item => item.trim()).filter(Boolean);
					ids.forEach(id => formData.append(`proof_image_${id}`, input.files[0]));
			}
		});

		return fetch(apiEndpoint, {
			method: 'POST',
			headers: {
				'Accept': 'application/json',
				'X-Requested-With': 'XMLHttpRequest'
			},
			credentials: 'include',
			body: formData
		})
			.then(parseJsonResponse)
			.then(data => {
				if (data.success) {
					const completeTransfer = () => showAlert('Success', data.message || 'Commission transferred successfully', 'success')
						.then(() => location.reload());
					showCashoutWhatsappModal(data.whatsapp, completeTransfer);
				} else {
					throw new Error(data.message || 'Transfer failed');
				}
			})
			.catch(err => {
				commissionActionBusy = false;
				setCommissionActionBusy(actionButton, false);
				console.error('Error:', err);
				showAlert('Error', err.message || 'Error during transfer', 'error');
			});
		} catch (err) {
			commissionActionBusy = false;
			setCommissionActionBusy(actionButton, false);
			showAlert('Error', err.message || 'Error during transfer', 'error');
		}
	}

	function parseJsonResponse(response) {
		return response.text().then(text => {
			let data;
			try {
				data = JSON.parse(text);
			} catch (error) {
				const contentType = response.headers.get('content-type') || '';
				const message = contentType.includes('text/html')
					? 'The server returned an HTML page. Your session may have expired; refresh and sign in again.'
					: 'The server returned an invalid response. Please refresh and try again.';
				throw new Error(message);
			}
			if (!response.ok || !data.success) {
				throw new Error(data.message || `Request failed (${response.status})`);
			}
			return data;
		});
	}

	function showAlert(title, text, icon) {
		if (typeof window.basicAdminSwalAlert === 'function') {
			return window.basicAdminSwalAlert(title, text, icon);
		}
		if (window.Swal && typeof window.Swal.fire === 'function') {
			return window.Swal.fire({ title, text, icon });
		}
		return Promise.resolve();
	}

	function showConfirm(text, title) {
		if (typeof window.basicAdminSwalConfirm === 'function') {
			return window.basicAdminSwalConfirm(text, title);
		}
		if (window.Swal && typeof window.Swal.fire === 'function') {
			return window.Swal.fire({ title, text, icon: 'warning', showCancelButton: true }).then(result => result.isConfirmed);
		}
		return Promise.resolve(false);
	}
});
</script>