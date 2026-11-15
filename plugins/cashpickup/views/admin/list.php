<?php if(!user_can('view_cashpickup')): ?>
	<div class="alert alert-danger text-center">Access denied.</div>
<?php else: ?>

<?php
	require_once plugin_path('helpers/transfer-allocation.php');
	require_once plugin_path('helpers/customer-ledger.php');
	require_once plugin_path('helpers/customer-assignment.php');
	$isAjax = (string)($_GET['ajax'] ?? '') === '1';
	$session = get_session();
	$user_id = (int)($session->id ?? 0);
	$isManagerOrAdmin = (user_can('is_manager') || user_can('is_admin'));
	$isGeneralManager = false;
	try {
		$dbRole = new \Core\Database;
		$gm = $dbRole->query(
			"SELECT 1
			 FROM user_roles_map urm
			 JOIN user_roles ur ON ur.id = urm.role_id
			 WHERE urm.disabled = 0
			 AND urm.user_id = :uid
			 AND LOWER(ur.role) IN ('general manager','general_manager','generalmanager','is_general_manager','operations manager','operations_manager')
			 LIMIT 1",
			['uid' => $user_id]
		);
		$isGeneralManager = !empty($gm);
	} catch (\Throwable $e) {}
	$canApproveDeny = (user_can('is_admin') || $isGeneralManager);
	$canViewAll = $isManagerOrAdmin;

	$db = new \Core\Database;
	\CashpickupCustomerAssignment\ensure_schema($db);
	\CashpickupLedger\ensure_cashpickup_customer_schema($db);
	\CashpickupLedger\ensure_cashpickup_customer_approval_columns($db);
	$getAllocationRemaining = function (int $receiveId) use ($db, $user_id): float {
		if ($receiveId <= 0 || $user_id <= 0) {
			return 0.0;
		}
		try {
			return \CashpickupTransferAllocation\get_transfer_remaining_amount($db, $receiveId, (int)$user_id);
		} catch (\Throwable $e) {
			return 0.0;
		}
	};
	$params = [];
	$conditions = ['cashpickups.deleted = 0'];
	$paidGrossExpr = "(COALESCE(amount_paid_out,0) + COALESCE(pickup_charge,0))";

	$find = trim((string)($_GET['find'] ?? ''));
	$sideFilter = strtolower(trim((string)($_GET['side'] ?? '')));
	$dateFrom = trim((string)($_GET['date_from'] ?? ''));
	$dateTo = trim((string)($_GET['date_to'] ?? ''));
	$assignedFilter = trim((string)($_GET['assigned_user_id'] ?? ''));
	$paidFilter = strtolower(trim((string)($_GET['paid'] ?? '')));
	$limit = (int)($_GET['limit'] ?? 30);
	$limit = $limit > 0 ? min(200, $limit) : 30;

	// list is receive-driven: one row per received pickup (pay rows are shown as payout history)
	$conditions[] = "LOWER(cashpickups.transaction_side) = 'receive'";

	if(!$canViewAll && $user_id > 0){
		$viewerVisibleReceiveIds = [];
		try {
			$viewerVisibleReceiveIds = \CashpickupTransferAllocation\get_visible_transfer_receive_ids($db, $user_id);
		} catch (\Throwable $e) {
			$viewerVisibleReceiveIds = [];
		}
		$viewerVisibleReceiveIds = array_values(array_filter(array_map('intval', (array)$viewerVisibleReceiveIds), function ($v) {
			return $v > 0;
		}));
		$viewerScopeParts = [
			'cashpickups.assigned_user_id = :viewer_assigned_user_id',
			'cashpickups.created_by = :viewer_created_by_user_id',
		];
		$params['viewer_assigned_user_id'] = $user_id;
		$params['viewer_created_by_user_id'] = $user_id;
		if (!empty($viewerVisibleReceiveIds)) {
			$viewerScopeParts[] = 'cashpickups.id IN (' . implode(',', $viewerVisibleReceiveIds) . ')';
		}
		$conditions[] = '(' . implode(' OR ', $viewerScopeParts) . ')';
	}

	// managers/admins: show standard receive sources plus credit payout receives
	if($isManagerOrAdmin){
		$conditions[] = "LOWER(COALESCE(cashpickups.source,'')) IN ('cash','mtn','telecel','at','bank','credit')";
	}
	// side filter kept in UI for now, but list always groups by receive
	if($dateFrom !== ''){
		$conditions[] = 'DATE(cashpickups.date_created) >= :date_from';
		$params['date_from'] = $dateFrom;
	}
	if($dateTo !== ''){
		$conditions[] = 'DATE(cashpickups.date_created) <= :date_to';
		$params['date_to'] = $dateTo;
	}
	if($assignedFilter !== ''){
		$assignedUserId = (int)$assignedFilter;
		$assignedScopeParts = [
			'cashpickups.assigned_user_id = :assigned_user_id',
		];
		$params['assigned_user_id'] = $assignedUserId;
		try {
			$assignedVisibleReceiveIds = \CashpickupTransferAllocation\get_visible_transfer_receive_ids($db, $assignedUserId);
			$assignedVisibleReceiveIds = array_values(array_filter(array_map('intval', (array)$assignedVisibleReceiveIds), function ($v) {
				return $v > 0;
			}));
			if (!empty($assignedVisibleReceiveIds)) {
				$assignedScopeParts[] = 'cashpickups.id IN (' . implode(',', $assignedVisibleReceiveIds) . ')';
			}
		} catch (\Throwable $e) {}
		$conditions[] = '(' . implode(' OR ', $assignedScopeParts) . ')';
	}
	if($find !== ''){
		$conditions[] = '(cashpickups.tracking_id LIKE :find OR cashpickups.phone_number LIKE :find OR cashpickups.sender_details LIKE :find)';
		$params['find'] = '%'.$find.'%';
	}

	$where = $conditions ? 'WHERE '.implode(' AND ', $conditions) : '';
	$paidWhere = '';
	if (in_array($paidFilter, ['paid','partial','unpaid'], true)) {
		if ($paidFilter === 'paid') {
			$paidWhere = " AND (COALESCE(paid.paid_requested,0) >= (COALESCE(cashpickups.amount_involved,0) - 0.00001))";
		} elseif ($paidFilter === 'partial') {
			$paidWhere = " AND (COALESCE(paid.paid_requested,0) > 0 AND COALESCE(paid.paid_requested,0) < (COALESCE(cashpickups.amount_involved,0) - 0.00001))";
		} else {
			$paidWhere = " AND (COALESCE(paid.paid_requested,0) <= 0)";
		}
	}

	$assignees = [];
	if (!$isAjax) {
		$assignees = $db->query("SELECT id, first_name, last_name FROM users WHERE deleted = 0 ORDER BY first_name ASC") ?? [];
	}

	$totals = $db->query("SELECT
		COUNT(*) AS total_rows,
		COALESCE(SUM(amount_involved),0) AS total_amount,
		COALESCE(SUM(CASE
			WHEN LOWER(TRIM(COALESCE(cashpickups.credit_mode,''))) <> 'credit'
			THEN COALESCE(amount_involved,0)
			ELSE 0
		END),0) AS balance_received,
		COALESCE(SUM(CASE
			WHEN LOWER(TRIM(COALESCE(cashpickups.credit_mode,''))) = 'credit'
				AND LOWER(TRIM(COALESCE(cashpickups.transaction_status,''))) IN ('approved','pending payout')
				AND (cashpickups.approval_status IS NULL OR cashpickups.approval_status = '' OR LOWER(TRIM(cashpickups.approval_status)) IN ('approved','pending'))
			THEN COALESCE(amount_involved,0)
			ELSE 0
		END),0) AS credit_balance_debit,
		(COALESCE(SUM(pickup_charge),0) + COALESCE(SUM(paid.pay_charge),0)) AS total_charge,
		COALESCE(SUM(paid.paid_requested),0) AS total_paid_out,
		COALESCE(SUM(paid.regular_paid_requested),0) AS regular_paid_requested,
		COALESCE(SUM(amount_transferred),0) AS total_transferred,
		COALESCE(SUM(paid.parts),0) AS pay_count,
		COUNT(*) AS receive_count
		FROM cashpickups
		LEFT JOIN (
			SELECT receive_id,
				COALESCE(SUM({$paidGrossExpr}),0) AS paid_requested,
				COALESCE(SUM(CASE
					WHEN LOWER(TRIM(COALESCE(credit_mode,''))) <> 'credit' THEN {$paidGrossExpr}
					ELSE 0
				END),0) AS regular_paid_requested,
				COALESCE(SUM(amount_paid_out),0) AS paid_out,
				COALESCE(SUM(pickup_charge),0) AS pay_charge,
				COUNT(*) AS parts
			FROM cashpickups
			WHERE (deleted IS NULL OR deleted = 0)
			AND LOWER(transaction_side) = 'pay'
			AND (
				approval_status IS NULL
				OR approval_status = ''
				OR LOWER(approval_status) IN ('approved','pending')
			)
			AND transaction_status = 'Approved'
			AND (confirmed_by IS NOT NULL AND confirmed_by != '')
			AND COALESCE(amount_paid_out,0) > 0
			GROUP BY receive_id
		) AS paid ON paid.receive_id = cashpickups.id
		{$where}{$paidWhere}", $params);
	$totals = $totals[0] ?? null;

	$widgetRows = [];
	try {
		$widgetConditions = [];
		foreach (($conditions ?? []) as $c) {
			// widget shows receive + pay; list itself is receive-driven
			if (stripos((string)$c, 'transaction_side') !== false) continue;
			$widgetConditions[] = $c;
		}
		$widgetWhere = $widgetConditions ? 'WHERE '.implode(' AND ', $widgetConditions) : '';
		$widgetParams = $params;

		$bySideApproval = $db->query("SELECT
			LOWER(COALESCE(transaction_side,'')) AS side,
			LOWER(COALESCE(approval_status,'pending')) AS status,
			COUNT(*) AS total_count
			FROM cashpickups
			{$widgetWhere}
			GROUP BY LOWER(COALESCE(transaction_side,'')), LOWER(COALESCE(approval_status,'pending'))
		", $widgetParams);
		if ($bySideApproval === false || $bySideApproval === null) {
			$bySideApproval = [];
		}

		$agg = [];
		foreach ($bySideApproval as $r) {
			$side = strtolower(trim((string)($r->side ?? '')));
			$st = strtolower(trim((string)($r->status ?? 'pending')));
			$cnt = (int)($r->total_count ?? 0);
			if ($cnt <= 0) continue;
			if ($side === 'receive') $sideLabel = 'Receive';
			elseif ($side === 'pay') $sideLabel = 'Pay';
			else $sideLabel = ($side !== '' ? ucfirst($side) : '');
			if ($st === '' || $st === 'null') $st = 'pending';
			$key = $sideLabel.'||'.$st;
			$agg[$key] = ($agg[$key] ?? 0) + $cnt;
		}

		$statusLabel = function($k) {
			$k = strtolower(trim((string)$k));
			if ($k === 'approved') return 'Approved';
			if ($k === 'pending') return 'Pending';
			if ($k === 'draft') return 'Draft';
			if ($k === 'denied') return 'Denied';
			if ($k === 'reversed') return 'Reversed';
			return ($k !== '' ? ucfirst(str_replace('_',' ', $k)) : 'Other');
		};
		foreach ($agg as $k => $cnt) {
			[$sideLabel, $st] = explode('||', $k, 2);
			$name = ($sideLabel !== '' ? ($sideLabel.' — '.$statusLabel($st)) : $statusLabel($st));
			$widgetRows[] = ['name' => $name, 'status' => $st, 'cnt' => (int)$cnt];
		}
	} catch (\Throwable $e) {
		$widgetRows = [];
	}

	$pager = new \Core\Pager($limit);
	$offset = $pager->offset;
	$hasTransferLedger = false;
	try {
		$hasTransferLedger = $db->table_exists(['cashpickup_transfers']);
	} catch (\Throwable $e) { $hasTransferLedger = false; }
	$rowParams = $params;
	$viewerSortJoin = '';
	$viewerSortOrder = 'm.id DESC';
	$viewerPriorityOrder = '1';
	$viewerRemainingSelect = '0';
	$assignedAmountJoin = '';
	$assignedRemainingSelect = '0';
	$attentionSortOrder = "CASE
		WHEN LOWER(COALESCE(m.transaction_status,'')) LIKE 'pending%'
			OR LOWER(COALESCE(NULLIF(TRIM(m.approval_status),''),'pending')) IN ('pending','draft')
			OR LOWER(COALESCE(cc.approval_status,'')) IN ('pending','draft')
			OR COALESCE(pending.pending_parts,0) > 0
		THEN 0 ELSE 1
	END";
	if (!$canViewAll && $user_id > 0 && $hasTransferLedger) {
		$rowParams['viewer_sort_user_id'] = $user_id;
		$viewerSortJoin = "\n\t\tLEFT JOIN (\n\t\t\tSELECT receive_id,\n\t\t\t\tMAX(date_created) AS last_assigned_at,\n\t\t\t\tCOALESCE(SUM(amount),0) AS assigned_gross\n\t\t\tFROM cashpickup_transfers\n\t\t\tWHERE receive_id IS NOT NULL\n\t\t\tAND to_user_id = :viewer_sort_user_id\n\t\t\tAND (\n\t\t\t\tapproval_status IS NULL\n\t\t\t\tOR approval_status = ''\n\t\t\t\tOR LOWER(approval_status) IN ('approved','pending')\n\t\t\t)\n\t\t\tGROUP BY receive_id\n\t\t) AS viewer_tr ON viewer_tr.receive_id = m.id\n\t\tLEFT JOIN (\n\t\t\tSELECT receive_id,\n\t\t\t\tCOALESCE(SUM({$paidGrossExpr}),0) AS paid_requested\n\t\t\tFROM cashpickups\n\t\t\tWHERE (deleted IS NULL OR deleted = 0)\n\t\t\tAND LOWER(transaction_side) = 'pay'\n\t\t\tAND (\n\t\t\t\tapproval_status IS NULL\n\t\t\t\tOR approval_status = ''\n\t\t\t\tOR LOWER(approval_status) IN ('approved','pending')\n\t\t\t)\n\t\t\tAND transaction_status = 'Approved'\n\t\t\tAND (confirmed_by IS NOT NULL AND confirmed_by != '')\n\t\t\tAND COALESCE(amount_paid_out,0) > 0\n\t\t\tAND (created_by = :viewer_sort_user_id OR assigned_user_id = :viewer_sort_user_id)\n\t\t\tGROUP BY receive_id\n\t\t) AS viewer_paid ON viewer_paid.receive_id = m.id";
		$viewerPriorityOrder = "CASE WHEN (COALESCE(viewer_tr.assigned_gross,0) - COALESCE(viewer_paid.paid_requested,0)) > 0.00001 THEN 0 ELSE 1 END";
		$viewerSortOrder = "COALESCE(viewer_tr.last_assigned_at, m.date_created) DESC, m.id DESC";
		$viewerRemainingSelect = 'GREATEST(0, COALESCE(viewer_tr.assigned_gross,0) - COALESCE(viewer_paid.paid_requested,0))';
	}
	if ($hasTransferLedger) {
		$assignedAmountJoin = "\n\t\tLEFT JOIN (\n\t\t\t		SELECT receive_id, to_user_id, COALESCE(SUM(amount),0) AS assigned_gross\n\t\t\tFROM cashpickup_transfers\n\t\t\tWHERE receive_id IS NOT NULL\n\t\t\tAND (\n\t\t\t\tapproval_status IS NULL\n\t\t\t\tOR approval_status = ''\n\t\t\t\tOR LOWER(approval_status) IN ('approved','pending')\n\t\t\t)\n\t\t\tGROUP BY receive_id, to_user_id\n\t\t) AS current_assignee_tr ON current_assignee_tr.receive_id = m.id AND current_assignee_tr.to_user_id = m.assigned_user_id\n\t\tLEFT JOIN (\n\t\t\tSELECT receive_id, user_id, COALESCE(SUM(paid_requested),0) AS paid_requested\n\t\t\tFROM (\n\t\t\t\tSELECT receive_id, assigned_user_id AS user_id, COALESCE(SUM({$paidGrossExpr}),0) AS paid_requested\n\t\t\t\tFROM cashpickups\n\t\t\t\tWHERE (deleted IS NULL OR deleted = 0)\n\t\t\t\tAND LOWER(transaction_side) = 'pay'\n\t\t\t\tAND (approval_status IS NULL OR approval_status = '' OR LOWER(approval_status) IN ('approved','pending'))\n\t\t\t\tAND transaction_status = 'Approved'\n\t\t\t\tAND confirmed_by IS NOT NULL AND confirmed_by != ''\n\t\t\t\tAND COALESCE(amount_paid_out,0) > 0\n\t\t\t\tAND assigned_user_id IS NOT NULL AND assigned_user_id > 0\n\t\t\t\tGROUP BY receive_id, assigned_user_id\n\t\t\t\tUNION ALL\n\t\t\t\tSELECT receive_id, created_by AS user_id, COALESCE(SUM({$paidGrossExpr}),0) AS paid_requested\n\t\t\t\tFROM cashpickups\n\t\t\t\tWHERE (deleted IS NULL OR deleted = 0)\n\t\t\t\tAND LOWER(transaction_side) = 'pay'\n\t\t\t\tAND (approval_status IS NULL OR approval_status = '' OR LOWER(approval_status) IN ('approved','pending'))\n\t\t\t\tAND transaction_status = 'Approved'\n\t\t\t\tAND confirmed_by IS NOT NULL AND confirmed_by != ''\n\t\t\t\tAND COALESCE(amount_paid_out,0) > 0\n\t\t\t\tAND created_by IS NOT NULL AND created_by > 0\n\t\t\t\tAND (assigned_user_id IS NULL OR assigned_user_id = 0 OR assigned_user_id != created_by)\n\t\t\t\tGROUP BY receive_id, created_by\n\t\t\t) AS payout_by_user\n\t\t\tGROUP BY receive_id, user_id\n\t\t) AS current_assignee_paid ON current_assignee_paid.receive_id = m.id AND current_assignee_paid.user_id = m.assigned_user_id";
		$assignedRemainingSelect = 'GREATEST(0, COALESCE(current_assignee_tr.assigned_gross,0) - COALESCE(current_assignee_paid.paid_requested,0))';
	}
	$listSortOrder = ($viewerSortJoin !== '')
		? $viewerPriorityOrder . ', ' . $attentionSortOrder . ', ' . $viewerSortOrder
		: $attentionSortOrder . ', ' . $viewerSortOrder;

	$whereList = str_replace('cashpickups.', 'm.', $where);
	$paidWhereList = '';
	if ($paidWhere !== '') {
		$paidWhereList = str_replace('cashpickups.', 'm.', $paidWhere);
	}
	$trSelect = $hasTransferLedger ? "COALESCE(tr.transferred,0)" : "0";
	$trJoin = $hasTransferLedger ? "\n\t\tLEFT JOIN (\n\t\t\tSELECT receive_id, COALESCE(SUM(amount),0) AS transferred\n\t\t\tFROM cashpickup_transfers\n\t\t\tWHERE approval_status IS NULL\n\t\t\tOR approval_status = ''\n\t\t\tOR LOWER(approval_status) IN ('approved','pending')\n\t\t\tGROUP BY receive_id\n\t\t) AS tr ON tr.receive_id = m.id" : "";
	$rows = $db->query("SELECT m.*, cc.approval_status AS customer_approval_status,
		u.first_name, u.last_name, u.image AS user_image,
		au.first_name AS assigned_first_name, au.last_name AS assigned_last_name,
		COALESCE(paid.paid_requested,0) AS paid_requested,
		COALESCE(paid.paid_out,0) AS paid_out,
		COALESCE(paid.parts,0) AS payout_parts,
		COALESCE(pending.pending_reserved,0) AS pending_reserved,
		COALESCE(pending.pending_parts,0) AS pending_parts,
		{$trSelect} AS transferred_to_agents,
		{$viewerRemainingSelect} AS viewer_remaining,
		{$assignedRemainingSelect} AS assigned_remaining
		FROM cashpickups AS m
		LEFT JOIN cashpickup_customers AS cc ON cc.id = m.customer_id
		LEFT JOIN users AS u ON m.created_by = u.id
		LEFT JOIN users AS au ON m.assigned_user_id = au.id
		{$trJoin}
		LEFT JOIN (
			SELECT receive_id,
				COALESCE(SUM({$paidGrossExpr}),0) AS paid_requested,
				COALESCE(SUM(amount_paid_out),0) AS paid_out,
				COUNT(*) AS parts
			FROM cashpickups
			WHERE (deleted IS NULL OR deleted = 0)
			AND LOWER(transaction_side) = 'pay'
			AND (
				approval_status IS NULL
				OR approval_status = ''
				OR LOWER(approval_status) IN ('approved','pending')
			)
			AND transaction_status = 'Approved'
			AND (confirmed_by IS NOT NULL AND confirmed_by != '')
			AND COALESCE(amount_paid_out,0) > 0
			GROUP BY receive_id
		) AS paid ON paid.receive_id = m.id
		LEFT JOIN (
			SELECT receive_id,
				COALESCE(SUM({$paidGrossExpr}),0) AS pending_reserved,
				COUNT(*) AS pending_parts
			FROM cashpickups
			WHERE (deleted IS NULL OR deleted = 0)
			AND LOWER(transaction_side) = 'pay'
			AND LOWER(approval_status) = 'pending'
			AND transaction_status = 'Approved'
			AND (confirmed_by IS NOT NULL AND confirmed_by != '')
			AND COALESCE(amount_paid_out,0) > 0
			GROUP BY receive_id
		) AS pending ON pending.receive_id = m.id
		{$viewerSortJoin}
		{$assignedAmountJoin}
		$whereList{$paidWhereList}
		ORDER BY {$listSortOrder}
		LIMIT $limit OFFSET $offset", $rowParams) ?? [];
?>

<style>
	.btn-icon{
		width: 34px;
		height: 34px;
		padding: 0;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		border-radius: 10px;
	}
	.btn-icon i{ font-size: .9rem; }
	.cashpickup-mobile-cols .row{ --bs-gutter-x: .5rem; --bs-gutter-y: .5rem; }
	.cashpickup-mobile-cols .card-body{ padding: .55rem .65rem; }
	.cashpickup-mobile-cols [class*="col-"]{ min-width: 0; }
	.cashpickup-mobile-cols .text-truncate-2{
		display: -webkit-box;
		-webkit-line-clamp: 2;
		-webkit-box-orient: vertical;
		overflow: hidden;
	}
	.cashpickup-mobile-cols .amount-line{ white-space: nowrap; }
	.cashpickup-mobile-list{display:flex;flex-direction:column;gap:.45rem}
	.cashpickup-mobile-cols .cashpickup-mobile-card{border:1px solid var(--bs-border-color);border-radius:.5rem;box-shadow:none!important}
	.cashpickup-mobile-cols .btn-strip{display:flex;justify-content:flex-end;align-items:center;gap:.3rem;flex-wrap:wrap}
	.cashpickup-mobile-cols .btn-strip form{display:flex!important;margin:0}
	.cashpickup-mobile-cols .btn-strip .btn{width:30px;height:30px;min-height:30px;padding:.15rem;white-space:nowrap}
	.cashpickup-mobile-cols .cashpickup-mobile-header{display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem}
	.cashpickup-mobile-cols .cashpickup-mobile-heading{min-width:0;overflow-wrap:anywhere}
	.cashpickup-mobile-cols .cashpickup-mobile-badges{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:.25rem}
	.cashpickup-mobile-cols .cashpickup-mobile-badges .badge{font-size:.65rem;padding:.25rem .35rem}
	.cashpickup-mobile-cols .cashpickup-mobile-details{display:flex;flex-wrap:wrap;column-gap:.6rem;row-gap:.1rem;font-size:.75rem}
	.cashpickup-mobile-cols .cashpickup-mobile-metrics{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.35rem .65rem}
	.cashpickup-mobile-cols .cashpickup-mobile-metric{min-width:0}
	.cashpickup-mobile-cols .cashpickup-mobile-metric-value{font-size:.8rem;font-weight:600;overflow-wrap:anywhere}
	.cashpickup-transaction-table tbody tr{transition:background-color .16s ease}
	.cashpickup-mobile-card{transition:border-color .16s ease,box-shadow .16s ease,transform .16s ease}
	.cashpickup-mobile-card.is-pressed,
	.cashpickup-mobile-card:focus-within{
		border-top-color:rgba(var(--bs-primary-rgb),.55);
		border-right-color:rgba(var(--bs-primary-rgb),.55);
		border-bottom-color:rgba(var(--bs-primary-rgb),.55);
		box-shadow:0 .25rem .8rem rgba(15,23,42,.08)!important;
		transform:translateY(-1px);
	}
	@media (hover:hover) and (pointer:fine){
		.cashpickup-transaction-table.table-hover>tbody>tr:hover>*{--bs-table-bg-state:rgba(var(--bs-primary-rgb),.075)}
		.cashpickup-mobile-card:hover{border-top-color:rgba(var(--bs-primary-rgb),.55);border-right-color:rgba(var(--bs-primary-rgb),.55);border-bottom-color:rgba(var(--bs-primary-rgb),.55);box-shadow:0 .25rem .8rem rgba(15,23,42,.08)!important;transform:translateY(-1px)}
	}
	@media (prefers-reduced-motion:reduce){
		.cashpickup-transaction-table tbody tr,
		.cashpickup-mobile-card{transition:none}
		.cashpickup-mobile-card:hover,
		.cashpickup-mobile-card.is-pressed{transform:none}
	}
	.cashpickup-mobile-cols .cashpickup-priority-card{border-left:3px solid var(--bs-warning)}
	.cashpickup-priority-row>td:first-child{border-left:4px solid var(--bs-warning)}
	@media (min-width:768px) and (max-width:991.98px){
		#cashpickupListResults .border.rounded.p-3{padding:1.1rem !important;}
		#cashpickupListResults .fw-bold{font-size:1.1rem;}
	}
	.cashpickup-metric-icon{ font-size: 1.25rem; opacity: .9; }
	.cashpickup-dist-item{ display:flex; gap:.75rem; align-items:center; padding:.35rem 0; }
	.cashpickup-dist-name{ flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
	.cashpickup-dist-right{ width: 65%; }
	.cashpickup-dist-meta{ display:flex; justify-content:space-between; font-size:.9rem; opacity:.9; }
	.cashpickup-dist-progress{ height:8px; border-radius:999px; background:#e9ecef; overflow:hidden; }
	.cashpickup-dist-bar{ height:100%; border-radius:999px; }
	.cashpickup-toolbar-actions{ display:flex; gap:.5rem; flex-wrap:wrap; }
	.cashpickup-toolbar-actions .btn{ flex: 1 1 90px; }
	#cashpickupStatusWidgetSlot .border.rounded{ height: 100%; }
	.cashpickup-metrics-details>summary{list-style:none;cursor:pointer}
	.cashpickup-metrics-details>summary::-webkit-details-marker{display:none}
	.cashpickup-metrics-chevron{transition:transform .15s ease}
	.cashpickup-metrics-details[open] .cashpickup-metrics-chevron{transform:rotate(180deg)}
	@media (prefers-reduced-motion:reduce){.cashpickup-metrics-chevron{transition:none}}
</style>

<?php
	$renderResults = function() use ($rows, $totals, $widgetRows, $pager, $admin_route, $plugin_route, $limit, $user_id, $isManagerOrAdmin, $canApproveDeny, $getAllocationRemaining, $find, $sideFilter, $dateFrom, $dateTo, $assignedFilter, $paidFilter) {
		$totalPages = (int)ceil(((float)($totals->total_rows ?? 0)) / max(1, (int)$limit));
		$cashpickupBalance = (float)($totals->balance_received ?? 0)
			- (float)($totals->regular_paid_requested ?? 0)
			- (float)($totals->credit_balance_debit ?? 0);
		$pageNumber = max(1, (int)($pager->page_number ?? 1));
		$pageUrl = static function(int $targetPage) use ($admin_route, $plugin_route, $find, $sideFilter, $dateFrom, $dateTo, $assignedFilter, $paidFilter, $limit): string {
			$params = [
				'find' => $find,
				'side' => $sideFilter,
				'date_from' => $dateFrom,
				'date_to' => $dateTo,
				'assigned_user_id' => $assignedFilter,
				'paid' => $paidFilter,
				'limit' => $limit,
				'page' => max(1, $targetPage),
			];
			return ROOT . '/' . $admin_route . '/' . $plugin_route . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
		};
		$renderPageItem = static function(string $label, int $targetPage, bool $disabled = false, bool $active = false) use ($pageUrl): string {
			$itemClass = 'page-item' . ($disabled ? ' disabled' : '') . ($active ? ' active' : '');
			if ($disabled || $active) {
				$link = '<span class="page-link"' . ($active ? ' aria-current="page"' : '') . '>' . esc($label) . '</span>';
			} else {
				$link = '<a class="page-link" href="' . esc($pageUrl($targetPage)) . '">' . esc($label) . '</a>';
			}
			return '<li class="' . $itemClass . '">' . $link . '</li>';
		};
		$csrfField = csrf();
		?>
		<div id="cashpickupListResults">
			<details class="card border-0 shadow-sm mb-3 cashpickup-metrics-details">
				<summary class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
					<span>
						<span class="d-block small text-muted">Cashpickup balance (current filters)</span>
						<span class="d-block fw-bold fs-5 <?=($cashpickupBalance < 0) ? 'text-danger' : ''?>">GH₵ <?=number_format($cashpickupBalance, 2)?></span>
						<span class="d-block small text-muted">Credit payouts are deducted from the general cashpickup balance.</span>
					</span>
					<span class="small fw-semibold text-primary">Metrics <i class="fa fa-chevron-down ms-1 cashpickup-metrics-chevron" aria-hidden="true"></i></span>
				</summary>
				<div class="card-body">
			<div class="row g-2 mb-0">
				<div class="col-12 col-sm-6 col-lg-4">
					<div class="border rounded p-3">
						<div class="d-flex align-items-center justify-content-between">
							<div class="text-muted">Total Pickups</div>
							<i class="fa fa-hand-holding-dollar text-primary cashpickup-metric-icon"></i>
						</div>
						<div class="fw-bold"><?=number_format((float)($totals->total_rows ?? 0))?></div>
						<div class="small text-muted">Payout parts: <?=number_format((float)($totals->pay_count ?? 0))?> | Receives: <?=number_format((float)($totals->receive_count ?? 0))?></div>
					</div>
				</div>
				<div class="col-12 col-sm-6 col-lg-4">
					<div class="border rounded p-3">
						<div class="d-flex align-items-center justify-content-between">
							<div class="text-muted">Amount Involved</div>
							<i class="fa fa-money-bill-wave text-success cashpickup-metric-icon"></i>
						</div>
						<div class="fw-bold">GH₵ <?=number_format((float)($totals->total_amount ?? 0), 2)?></div>
						<div class="small text-muted">Charge: GH₵ <?=number_format((float)($totals->total_charge ?? 0), 2)?></div>
					</div>
				</div>
				<div class="col-12 col-lg-4">
					<div class="border rounded p-3">
						<div class="d-flex align-items-center justify-content-between">
							<div class="text-muted">Total Paid Out</div>
							<i class="fa fa-coins text-warning cashpickup-metric-icon"></i>
						</div>
						<div class="fw-bold">GH₵ <?=number_format((float)($totals->total_paid_out ?? 0), 2)?></div>
						<div class="small text-muted">(Across all parts)</div>
					</div>
				</div>
			</div>
				</div>
			</details>

			<?php
				$distRows = $widgetRows;
				usort($distRows, function($a,$b){ return ((int)($b['cnt'] ?? 0)) <=> ((int)($a['cnt'] ?? 0)); });
				$distTotal = 0;
				foreach ($distRows as $rr) { $distTotal += (int)($rr['cnt'] ?? 0); }
				$statusColor = function($st) {
					$st = strtolower(trim((string)$st));
					if ($st === 'approved') return '#22c55e';
					if ($st === 'pending') return '#f59e0b';
					if ($st === 'draft') return '#a855f7';
					if ($st === 'denied') return '#ef4444';
					return '#0ea5e9';
				};
			?>
			<?php if (!empty($distRows) && $distTotal > 0): ?>
				<div class="row g-2 mb-3 d-lg-none">
					<div class="col-12">
						<div class="border rounded p-3" id="cashpickupStatusWidgetInner">
							<div class="d-flex align-items-center justify-content-between mb-2">
								<div class="fw-bold">Cashpickup Status</div>
								<div class="text-muted small"><?=number_format((float)$distTotal)?> cashpickups</div>
							</div>
							<?php foreach(array_slice($distRows, 0, 12) as $rr): ?>
								<?php
									$cnt = (int)($rr['cnt'] ?? 0);
									$pct = $distTotal > 0 ? (int)round(($cnt / $distTotal) * 100) : 0;
									$color = $statusColor($rr['status'] ?? '');
								?>
								<div class="cashpickup-dist-item">
									<div class="cashpickup-dist-name" title="<?=esc((string)($rr['name'] ?? ''))?>"><?=esc((string)($rr['name'] ?? ''))?></div>
									<div class="cashpickup-dist-right">
										<div class="cashpickup-dist-meta">
											<span><?=number_format((float)$cnt)?></span>
											<span><?=$pct?>%</span>
										</div>
										<div class="cashpickup-dist-progress">
											<div class="cashpickup-dist-bar" style="width:<?=$pct?>%;background:<?=$color?>;"></div>
										</div>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			<?php endif; ?>

			<div class="d-lg-none cashpickup-mobile-cols">
				<?php if(empty($rows)): ?>
					<div class="text-center text-muted py-4">No cash pickups found</div>
				<?php else: ?>
					<div class="cashpickup-mobile-list">
						<?php $approvalStatus = 'pending'; ?>
						<?php foreach($rows as $r): ?>
						<?php
							$isCreditPayout = strtolower(trim((string)($r->credit_mode ?? ''))) === 'credit';
							$paidRequested = (float)($r->paid_requested ?? 0);
							$paidOut = (float)($r->paid_out ?? 0);
							$pendingReserved = (float)($r->pending_reserved ?? 0);
							$pendingParts = (int)($r->pending_parts ?? 0);
							$rawRemaining = (float)($r->amount_involved ?? 0) - $paidRequested;
							$remaining = max(0, (float)$rawRemaining);
							$overage = max(0, (float)(-$rawRemaining));
							$transferredAgents = (float)($r->transferred_to_agents ?? ($r->amount_transferred ?? 0));
							$remainingTransferable = max(0, (float)($r->amount_involved ?? 0) - $paidRequested - $transferredAgents);
							$parts = (int)($r->payout_parts ?? 0);
							$fullyPaid = $remaining <= 0.00001;
							$fullyTransferred = $remainingTransferable <= 0.00001;
							$payoutStatusText = $fullyPaid ? 'Paid' : ($paidRequested > 0 ? 'Partial' : 'Not paid');
							$approvalStatus = strtolower(trim((string)($r->approval_status ?? 'pending')));
							if ($approvalStatus === '') $approvalStatus = 'pending';
							$displayApprovalStatus = ($isCreditPayout && !in_array($approvalStatus, ['denied','reversed'], true) && !$fullyPaid) ? 'pending' : $approvalStatus;
							$displayApprovalText = ($isCreditPayout && !in_array($approvalStatus, ['denied','reversed'], true) && !$fullyPaid) ? 'Pending payout' : ucfirst($displayApprovalStatus);
							$canApproveThis = $canApproveDeny && $approvalStatus === 'pending' && !$isCreditPayout;
							$customerApprovalStatus = strtolower(trim((string)($r->customer_approval_status ?? 'approved')));
							$isNewCustomer = in_array($customerApprovalStatus, ['pending','draft'], true);
							$transactionStatus = strtolower(trim((string)($r->transaction_status ?? '')));
							$transactionNeedsAttention = $isNewCustomer
								|| in_array($approvalStatus, ['pending','draft'], true)
								|| strpos($transactionStatus, 'pending') === 0
								|| $pendingParts > 0;
							$assignmentRemaining = (float)($isManagerOrAdmin
								? ($r->assigned_remaining ?? 0)
								: ($r->viewer_remaining ?? 0));
						?>
							<div class="card cashpickup-mobile-card <?=$transactionNeedsAttention ? 'cashpickup-priority-card' : ''?>">
								<div class="card-body">
									<div class="row g-2">
										<div class="col-12">
											<div class="cashpickup-mobile-header">
												<div class="cashpickup-mobile-heading">
													<div class="fw-semibold">#<?=esc($r->id)?> <span class="small fw-normal text-muted"><?=esc($r->date_created ?? $r->date ?? '')?></span></div>
													<div class="small text-truncate-2"><?=esc($r->sender_details ?? '')?></div>
												</div>
												<div class="cashpickup-mobile-badges">
													<?php if($transactionNeedsAttention): ?>
														<span class="badge bg-danger-subtle text-danger">Priority</span>
													<?php endif; ?>
													<?php if($isNewCustomer): ?>
														<span class="badge text-bg-info">New customer</span>
													<?php endif; ?>
													<?php if($displayApprovalStatus === 'approved'): ?>
														<span class="badge bg-success">Approved</span>
													<?php elseif($displayApprovalStatus === 'reversed'): ?>
														<span class="badge bg-danger">Reversed</span>
													<?php elseif($displayApprovalStatus === 'denied'): ?>
														<span class="badge bg-danger">Denied</span>
													<?php else: ?>
														<span class="badge bg-warning text-dark"><?=esc($displayApprovalText)?></span>
													<?php endif; ?>
												</div>
											</div>
											<div class="cashpickup-mobile-details mt-2">
												<span>Phone: <?=esc($r->phone_number ?? '')?></span>
												<span>Tracking: <?=esc($r->tracking_id ?? '')?></span>
												<span>Source: <?=esc($r->source ?? '')?></span>
												<?php $assignedName = trim(((string)($r->assigned_first_name ?? '')).' '.((string)($r->assigned_last_name ?? ''))); ?>
												<?php if($assignedName !== ''): ?>
													<span>Assigned: <?=esc($assignedName)?></span>
												<?php endif; ?>
												<?php if($assignmentRemaining > 0.00001): ?>
													<span class="text-primary fw-semibold">Assigned payout remaining: GH₵ <?=number_format($assignmentRemaining, 2)?></span>
												<?php endif; ?>
											</div>
										</div>

										<div class="col-12">
											<div class="cashpickup-mobile-metrics">
												<div class="cashpickup-mobile-metric">
													<div class="text-muted small">Received</div>
													<div class="cashpickup-mobile-metric-value">GH₵ <?=number_format((float)($r->amount_involved ?? 0), 2)?></div>
												</div>
												<div class="cashpickup-mobile-metric">
													<div class="text-muted small">Paid</div>
													<div class="cashpickup-mobile-metric-value">GH₵ <?=number_format($paidOut, 2)?> <span class="text-muted fw-normal">(<?=number_format($parts)?>)</span></div>
												</div>
												<div class="cashpickup-mobile-metric">
													<div class="text-muted small">Remaining</div>
													<div class="cashpickup-mobile-metric-value">GH₵ <?=number_format((float)$remaining, 2)?></div>
												</div>
											</div>
											<?php if($pendingParts > 0 && $pendingReserved > 0.00001): ?>
												<div class="small text-warning mt-1">Pending payout: GH₵ <?=number_format((float)$pendingReserved, 2)?> (<?=number_format((float)$pendingParts)?>)</div>
											<?php endif; ?>
											<?php if((float)$overage > 0.00001): ?>
												<div class="small text-danger mt-1">Overage: GH₵ <?=number_format((float)$overage, 2)?></div>
											<?php endif; ?>
											<div class="small text-muted mt-1">Payout: <?=esc($payoutStatusText)?></div>
											<?php if(in_array($approvalStatus, ['denied', 'cancelled', 'canceled'], true) && trim((string)($r->denial_reason ?? '')) !== ''): ?>
												<div class="small text-danger mt-1"><?=in_array($approvalStatus, ['cancelled', 'canceled'], true) ? 'Cancellation reason' : 'Reason'?>: <?=esc((string)$r->denial_reason)?></div>
											<?php endif; ?>
										</div>

										<div class="col-12">
											<div class="btn-strip">
												<?php if($canApproveThis): ?>
													<form method="post" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/approve/<?=$r->id?>" class="d-inline">
														<?=$csrfField?>
														<button class="btn btn-success" type="submit" title="Approve deposit" aria-label="Approve deposit"><i class="fa fa-check"></i></button>
													</form>
													<form method="post" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/deny/<?=$r->id?>" class="d-inline">
														<?=$csrfField?>
														<input type="hidden" name="denial_reason" value="">
														<button class="btn btn-outline-danger" type="submit" title="Deny deposit" aria-label="Deny deposit"><i class="fa fa-times"></i></button>
													</form>
												<?php endif; ?>
												<?php
													$cancelStatuses = strtolower(trim((string)($r->approval_status ?? ''))) . ' '
														. strtolower(trim((string)($r->transaction_status ?? ''))) . ' ' . $displayApprovalStatus;
													$cancelBlocked = preg_match('/denied|deny|rejected|reject|declined|decline|cancelled|canceled/i', $cancelStatuses) === 1;
												?>
												<?php if(user_can('is_admin') && !$cancelBlocked): ?>
													<button class="btn btn-sm btn-outline-danger" type="button" title="Cancel transaction" aria-label="Cancel transaction" data-cashpickup-cancel data-cancel-url="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/cancel/<?=esc((string)($r->id ?? 0))?>" data-transaction-id="<?=esc((string)($r->id ?? 0))?>">
														<i class="fa fa-ban"></i>
													</button>
												<?php endif; ?>

												<a class="btn btn-outline-primary" title="View transaction" aria-label="View transaction" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$r->id?>"><i class="fa fa-eye"></i></a>
											<?php
												$assignedTo = (int)($r->assigned_user_id ?? 0);
												$creatorId = (int)($r->created_by ?? 0);
												$canAssignThis = $isManagerOrAdmin
													&& ($approvalStatus === 'approved' || ($isCreditPayout && !in_array($approvalStatus, ['denied','reversed'], true) && !$fullyPaid));
												// Payout only when the current viewer still has active allocation remaining
												$allocationRemaining = isset($r->viewer_remaining)
													? (float)$r->viewer_remaining
													: $getAllocationRemaining((int)($r->id ?? 0));
												$canPayoutThis = !$isManagerOrAdmin 
													&& (
														($approvalStatus === 'approved' && $allocationRemaining > 0.00001)
														|| ($isCreditPayout && $assignedTo === (int)$user_id && $displayApprovalStatus === 'pending')
													);
											?>
											<?php if($canAssignThis): ?>
												<a class="btn btn-info" title="Assign" aria-label="Assign" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$r->id?>#transfer"><i class="fa fa-user-plus"></i></a>
												<?php elseif($canPayoutThis): ?>
													<a class="btn btn-warning" title="Payout" aria-label="Payout" href="<?=$isCreditPayout || (int)($r->customer_id ?? 0) <= 0 ? ROOT.'/'.$admin_route.'/'.$plugin_route.'/payout/'.esc((string)($r->id ?? 0)) : ROOT.'/'.$admin_route.'/'.$plugin_route.'/customer/'.esc((string)$r->customer_id).'#withdraw-section'?>"><i class="fa fa-money-bill-wave"></i></a>
												<?php endif; ?>
												<?php if($approvalStatus === 'approved' && !$fullyPaid && $isManagerOrAdmin): ?>
													<a class="btn btn-outline-secondary" title="Edit" aria-label="Edit" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit/<?=$r->id?>"><i class="fa fa-pen"></i></a>
												<?php endif; ?>
											</div>
											<div class="small text-muted mt-1">By <?=esc(trim(($r->first_name ?? '').' '.($r->last_name ?? '')))?></div>
										</div>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="d-none d-lg-block">
				<div class="table-responsive">
					<table class="table table-sm table-hover cashpickup-transaction-table">
						<thead>
							<tr>
								<th>Transaction</th>
								<th>Source / Tracking</th>
								<th>Sender / Phone</th>
								<th class="text-end">Received</th>
								<th class="text-end">Paid</th>
								<th>Payout</th>
								<th>Approval</th>
								<th class="text-end">Remaining</th>
								<th class="text-end">Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php if(empty($rows)): ?>
								<tr><td colspan="9" class="text-center text-muted">No cash pickups found</td></tr>
							<?php else: ?>
								<?php foreach($rows as $r): ?>
								<?php
									$isCreditPayout = strtolower(trim((string)($r->credit_mode ?? ''))) === 'credit';
									$paidRequested = (float)($r->paid_requested ?? 0);
									$pendingReserved = (float)($r->pending_reserved ?? 0);
									$pendingParts = (int)($r->pending_parts ?? 0);
									$rawRemaining = (float)($r->amount_involved ?? 0) - $paidRequested;
									$remaining = max(0, (float)$rawRemaining);
									$overage = max(0, (float)(-$rawRemaining));
									$parts = (int)($r->payout_parts ?? 0);
									$fullyPaid = $remaining <= 0.00001;
									$payoutStatusText = $fullyPaid ? 'Paid' : ($paidRequested > 0 ? 'Partial' : 'Not paid');
									$approvalStatus = strtolower(trim((string)($r->approval_status ?? 'pending')));
									if ($approvalStatus === '') $approvalStatus = 'pending';
									$displayApprovalStatus = ($isCreditPayout && !in_array($approvalStatus, ['denied','reversed'], true) && !$fullyPaid) ? 'pending' : $approvalStatus;
									$displayApprovalText = ($isCreditPayout && !in_array($approvalStatus, ['denied','reversed'], true) && !$fullyPaid) ? 'Pending payout' : ucfirst($displayApprovalStatus);
									$canApproveThis = $canApproveDeny && $approvalStatus === 'pending' && !$isCreditPayout;
									$customerApprovalStatus = strtolower(trim((string)($r->customer_approval_status ?? 'approved')));
									$isNewCustomer = in_array($customerApprovalStatus, ['pending','draft'], true);
									$transactionStatus = strtolower(trim((string)($r->transaction_status ?? '')));
									$transactionNeedsAttention = $isNewCustomer
										|| in_array($approvalStatus, ['pending','draft'], true)
										|| strpos($transactionStatus, 'pending') === 0
										|| $pendingParts > 0;
									$assignmentRemaining = (float)($isManagerOrAdmin
										? ($r->assigned_remaining ?? 0)
										: ($r->viewer_remaining ?? 0));
								?>
									<tr class="<?=$transactionNeedsAttention ? 'cashpickup-priority-row' : ''?>">
										<td>
											<div class="fw-semibold">#<?=esc($r->id)?></div>
											<div class="small text-muted"><?=esc($r->date_created ?? $r->date ?? '')?></div>
											<div class="mt-1">
												<?php if($isNewCustomer): ?>
													<span class="badge text-bg-info">New customer</span>
												<?php endif; ?>
												<?php if($transactionNeedsAttention): ?>
													<span class="badge bg-danger-subtle text-danger">Priority</span>
												<?php endif; ?>
												<?php if($displayApprovalStatus === 'approved'): ?>
													<span class="badge bg-success">Approved</span>
												<?php elseif($displayApprovalStatus === 'reversed'): ?>
													<span class="badge bg-danger">Reversed</span>
												<?php elseif($displayApprovalStatus === 'denied'): ?>
													<span class="badge bg-danger">Denied</span>
												<?php else: ?>
													<span class="badge bg-warning text-dark"><?=esc($displayApprovalText)?></span>
												<?php endif; ?>
											</div>
										</td>
										<td>
											<div class="fw-semibold"><?=esc($r->source ?? '')?></div>
											<div class="small text-muted">Tracking: <?=esc($r->tracking_id ?? '')?></div>
										</td>
										<td>
											<div class="fw-semibold"><?=esc($r->sender_details ?? '')?></div>
											<div class="small text-muted">Phone: <?=esc($r->phone_number ?? '')?></div>
											<?php $assignedName = trim(((string)($r->assigned_first_name ?? '')).' '.((string)($r->assigned_last_name ?? ''))); ?>
											<?php if($assignedName !== ''): ?>
												<div class="small text-muted">Assigned to: <?=esc($assignedName)?></div>
											<?php endif; ?>
											<?php if($assignmentRemaining > 0.00001): ?>
												<div class="small text-primary fw-semibold">Payout remaining: GH₵ <?=number_format($assignmentRemaining, 2)?></div>
											<?php endif; ?>
										</td>
										<td class="text-end"><?=number_format((float)($r->amount_involved ?? 0), 2)?></td>
										<td class="text-end"><?=number_format((float)$paidRequested, 2)?> <?php if($parts>0): ?><span class="text-muted">(<?=number_format((float)$parts)?>)</span><?php endif; ?></td>
										<td>
											<?php if($payoutStatusText === 'Paid'): ?>
												<span class="badge bg-success">Paid</span>
											<?php elseif($payoutStatusText === 'Partial'): ?>
												<span class="badge bg-warning text-dark">Partial</span>
											<?php else: ?>
												<span class="badge bg-secondary">Not paid</span>
											<?php endif; ?>
										</td>
										<td>
											<?php if($displayApprovalStatus === 'approved'): ?>
												<span class="badge bg-success">Approved</span>
											<?php elseif($displayApprovalStatus === 'reversed'): ?>
												<span class="badge bg-danger">Reversed</span>
											<?php elseif($displayApprovalStatus === 'denied'): ?>
												<span class="badge bg-danger">Denied</span>
												<?php if(trim((string)($r->denial_reason ?? '')) !== ''): ?>
													<div class="small text-danger mt-1" title="<?=esc((string)$r->denial_reason)?>"><?=esc((string)$r->denial_reason)?></div>
												<?php endif; ?>
											<?php else: ?>
												<span class="badge bg-warning text-dark"><?=esc($displayApprovalText)?></span>
											<?php endif; ?>
										</td>
										<td class="text-end">
										<div><?=number_format((float)$remaining, 2)?></div>
										<?php if($pendingParts > 0 && $pendingReserved > 0.00001): ?>
											<div class="small text-warning">Pending: <?=number_format((float)$pendingReserved, 2)?> (<?=number_format((float)$pendingParts)?>)</div>
										<?php endif; ?>
										<?php if((float)$overage > 0.00001): ?>
											<div class="small text-danger">Overage: <?=number_format((float)$overage, 2)?></div>
										<?php endif; ?>
									</td>
									<td class="text-end">
										<?php if($canApproveThis): ?>
											<form method="post" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/approve/<?=$r->id?>" class="d-inline">
												<?=$csrfField?>
												<button class="btn btn-icon btn-success" type="submit" title="Approve"><i class="fa fa-check"></i></button>
											</form>
											<form method="post" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/deny/<?=$r->id?>" class="d-inline">
												<?=$csrfField?>
												<input type="hidden" name="denial_reason" value="">
												<button class="btn btn-icon btn-danger" type="submit" title="Deny"><i class="fa fa-times"></i></button>
											</form>
										<?php endif; ?>
										<a class="btn btn-icon btn-outline-primary" title="View" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$r->id?>"><i class="fa fa-eye"></i></a>
										<?php
											$cancelStatuses = strtolower(trim((string)($r->approval_status ?? ''))) . ' '
												. strtolower(trim((string)($r->transaction_status ?? ''))) . ' ' . $displayApprovalStatus;
											$cancelBlocked = preg_match('/denied|deny|rejected|reject|declined|decline|cancelled|canceled/i', $cancelStatuses) === 1;
										?>
										<?php if(user_can('is_admin') && !$cancelBlocked): ?>
											<button class="btn btn-icon btn-outline-danger" type="button" title="Cancel transaction" aria-label="Cancel transaction" data-cashpickup-cancel data-cancel-url="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/cancel/<?=esc((string)($r->id ?? 0))?>" data-transaction-id="<?=esc((string)($r->id ?? 0))?>">
												<i class="fa fa-ban"></i>
											</button>
										<?php endif; ?>
											<?php
												$assignedTo = (int)($r->assigned_user_id ?? 0);
												$creatorId = (int)($r->created_by ?? 0);
												$canAssignThis = $isManagerOrAdmin
													&& ($approvalStatus === 'approved' || ($isCreditPayout && !in_array($approvalStatus, ['denied','reversed'], true) && !$fullyPaid));
												// Payout only when the current viewer still has active allocation remaining
											$allocationRemaining = isset($r->viewer_remaining)
												? (float)$r->viewer_remaining
												: $getAllocationRemaining((int)($r->id ?? 0));
											$canPayoutThis = !$isManagerOrAdmin 
												&& (
													($approvalStatus === 'approved' && $allocationRemaining > 0.00001)
													|| ($isCreditPayout && $assignedTo === (int)$user_id && $displayApprovalStatus === 'pending')
												);
										?>
										<?php if($canAssignThis): ?>
											<a class="btn btn-icon btn-info" title="Assign" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$r->id?>#transfer"><i class="fa fa-user-plus"></i></a>
										<?php elseif($canPayoutThis): ?>
											<a class="btn btn-icon btn-warning" title="Payout" href="<?=$isCreditPayout || (int)($r->customer_id ?? 0) <= 0 ? ROOT.'/'.$admin_route.'/'.$plugin_route.'/payout/'.esc((string)($r->id ?? 0)) : ROOT.'/'.$admin_route.'/'.$plugin_route.'/customer/'.esc((string)$r->customer_id).'#withdraw-section'?>"><i class="fa fa-money-bill-wave"></i></a>
										<?php endif; ?>
										<?php if($approvalStatus === 'approved' && !$fullyPaid && $isManagerOrAdmin): ?>
											<a class="btn btn-icon btn-outline-secondary" title="Edit" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit/<?=$r->id?>"><i class="fa fa-pen"></i></a>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>

			<?php if($totalPages > 1): ?>
				<div class="mt-3 js-cashpickup-pager">
					<div class="small text-muted text-center mb-2">Page <?=number_format($pageNumber)?> of <?=number_format($totalPages)?></div>
					<nav aria-label="Cash Pickup pages">
						<ul class="pagination flex-wrap justify-content-center mb-0">
							<?=$renderPageItem('First', 1, $pageNumber <= 1)?>
							<?=$renderPageItem('Prev', max(1, $pageNumber - 1), $pageNumber <= 1)?>
							<?php
								$firstVisiblePage = max(1, $pageNumber - 1);
								$lastVisiblePage = min($totalPages, $pageNumber + 1);
								if($firstVisiblePage > 1):
							?>
								<?=$renderPageItem('1', 1, false, $pageNumber === 1)?>
								<?php if($firstVisiblePage > 2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
							<?php endif; ?>
							<?php for($page = $firstVisiblePage; $page <= $lastVisiblePage; $page++): ?>
								<?=$renderPageItem((string)$page, $page, false, $page === $pageNumber)?>
							<?php endfor; ?>
							<?php if($lastVisiblePage < $totalPages): ?>
								<?php if($lastVisiblePage < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
								<?=$renderPageItem((string)$totalPages, $totalPages, false, $pageNumber === $totalPages)?>
							<?php endif; ?>
							<?=$renderPageItem('Next', min($totalPages, $pageNumber + 1), $pageNumber >= $totalPages)?>
							<?=$renderPageItem('Last', $totalPages, $pageNumber >= $totalPages)?>
						</ul>
					</nav>
				</div>
			<?php endif; ?>
		</div>
		<?php
	};
?>

<?php if($isAjax){ $renderResults(); return; } ?>

<div class="d-flex align-items-center justify-content-between mb-3">
	<div>
		<h4 class="mb-0">Cash Pickup</h4>
		<small class="text-muted">Receive from customers or pay to customers</small>
	</div>
	<div class="d-flex gap-2">
		<a class="btn btn-outline-secondary" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/customers"> <i class="fa-solid fa-user"></i> Customer Ledger</a>
		<?php if(user_can('add_cashpickup')): ?>
			<a class="btn btn-danger" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/add">
				<i class="fa fa-plus"></i> New Cashpickup
			</a>
		<?php endif; ?>
	</div>
</div>

<div class="card border-0 shadow-sm mb-3">
	<div class="card-body">
		<?php
			$exportBaseParams = [
				'resource' => 'list',
				'find' => $find,
				'side' => 'receive',
				'date_from' => $dateFrom,
				'date_to' => $dateTo,
				'assigned_user_id' => $assignedFilter,
				'paid' => $paidFilter,
				'limit' => $limit,
			];
		?>
		<form id="cashpickupListFilterForm" class="row g-3 align-items-end" method="get" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
			<div class="col-7 col-md-8 col-lg-4">
				<label class="form-label">Search</label>
				<input type="text" name="find" value="<?=esc($find)?>" class="form-control" placeholder="Tracking, phone, customer name">
			</div>
			<div class="col-2 col-md-1 d-lg-none">
				<label class="form-label">&nbsp;</label>
				<button id="cashpickupListApplyBtnSm" class="btn btn-primary w-100" type="submit" aria-label="Apply filters"><i class="fa fa-filter"></i></button>
			</div>
			<input type="hidden" name="side" value="receive">
			<input type="hidden" name="page" value="1">
			<div class="col-6 col-lg-2 d-none d-lg-block">
				<label class="form-label">From</label>
				<input type="date" name="date_from" value="<?=esc($dateFrom)?>" class="form-control">
			</div>
			<div class="col-6 col-lg-2 d-none d-lg-block">
				<label class="form-label">To</label>
				<input type="date" name="date_to" value="<?=esc($dateTo)?>" class="form-control">
			</div>
			<div class="col-6 col-lg-2 d-none d-lg-block">
				<label class="form-label">Assigned</label>
				<select name="assigned_user_id" class="form-select">
					<option value="">All</option>
					<?php foreach($assignees as $a): $an = trim(($a->first_name ?? '').' '.($a->last_name ?? '')); ?>
						<option value="<?=esc($a->id)?>" <?=((string)$assignedFilter === (string)$a->id)?'selected':''?>><?=esc($an)?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="col-12 col-lg-2 d-none d-lg-block">
				<label class="form-label">Paid</label>
				<select class="form-select" name="paid">
					<option value="" <?=($paidFilter==='')?'selected':''?>>All</option>
					<option value="paid" <?=($paidFilter==='paid')?'selected':''?>>Paid</option>
					<option value="partial" <?=($paidFilter==='partial')?'selected':''?>>Partial</option>
					<option value="unpaid" <?=($paidFilter==='unpaid')?'selected':''?>>Unpaid</option>
				</select>
			</div>
			<div class="col-3 col-md-3 col-lg-2">
				<label class="form-label">Per page</label>
				<select name="limit" class="form-select w-auto">
					<?php foreach([10,25,30,50,100,200] as $l): ?>
						<option value="<?=$l?>" <?=$limit===$l?'selected':''?>><?=$l?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="col-12 col-lg-6 d-none d-lg-block">
				<label class="form-label">Export / Apply</label>
				<div class="cashpickup-toolbar-actions">
					<a class="btn btn-outline-secondary" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/export?<?=http_build_query(array_merge($exportBaseParams, ['format' => 'pdf']))?>"> <i class="fa-solid fa-file-pdf"></i> PDF</a>
					<a class="btn btn-outline-success" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/export?<?=http_build_query(array_merge($exportBaseParams, ['format' => 'xlsx']))?>"> <i class="fa-solid fa-file-excel"></i> Excel</a>
					<a class="btn btn-outline-primary" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/export?<?=http_build_query(array_merge($exportBaseParams, ['format' => 'csv']))?>"> <i class="fa-solid fa-file-csv"></i> CSV</a>
					<button id="cashpickupListApplyBtn" class="btn btn-primary" type="submit"><i class="fa fa-filter"></i> Apply</button>
				</div>
			</div>
			<div class="col-12 col-lg-6 d-none">
				<label class="form-label">Cashpickup Status</label>
				<div id="cashpickupStatusWidgetSlot"></div>
			</div>
		</form>
	</div>
</div>





<div id="cashpickupListContainer">
	<?php $renderResults(); ?>
</div>
<div id="cashpickupListLoading" class="small text-muted mt-2" role="status" aria-live="polite" hidden>Updating cash pickups...</div>
<div id="cashpickupListLoadError" class="alert alert-danger mt-2" role="alert" hidden>Unable to update the cashpickup list. Please try again.</div>
<?php require plugin_path('views/admin/cancel-modal.php'); ?>

<script>
	(function(){
		const filterForm = document.getElementById('cashpickupListFilterForm');
		const resultsContainer = document.getElementById('cashpickupListContainer');
		const loadingMessage = document.getElementById('cashpickupListLoading');
		const errorMessage = document.getElementById('cashpickupListLoadError');
		if(!filterForm || !resultsContainer || !loadingMessage || !errorMessage) return;

		const listUrl = <?=json_encode(ROOT . '/' . $admin_route . '/' . $plugin_route)?>;
		const findInput = filterForm.elements.namedItem('find');
		const pageInput = filterForm.elements.namedItem('page');
		let activeRequest = null;
		let requestSequence = 0;
		let searchTimer = null;

		const getFilterParams = function(page){
			const query = new URLSearchParams();
			for(const [name, value] of new FormData(filterForm).entries()){
				if(typeof value === 'string' && value.trim() !== ''){
					query.set(name, value);
				}
			}
			query.set('page', String(page));
			return query;
		};

		const updateAddressBar = function(page, mode){
			if(mode === 'none') return;
			const url = new URL(listUrl, window.location.href);
			url.search = getFilterParams(page).toString();
			if(mode === 'push'){
				window.history.pushState(null, '', url);
			}else{
				window.history.replaceState(null, '', url);
			}
		};

		const loadResults = async function(page, historyMode){
			const currentPage = Math.max(1, Number.parseInt(String(page), 10) || 1);
			pageInput.value = String(currentPage);
			if(activeRequest){
				activeRequest.abort();
			}
			const controller = new AbortController();
			activeRequest = controller;
			const sequence = ++requestSequence;
			resultsContainer.setAttribute('aria-busy', 'true');
			loadingMessage.hidden = false;
			errorMessage.hidden = true;

			try{
				const requestUrl = new URL(listUrl, window.location.href);
				const requestParams = getFilterParams(currentPage);
				requestParams.set('ajax', '1');
				requestUrl.search = requestParams.toString();
				const response = await fetch(requestUrl.href, {
					method: 'GET',
					credentials: 'same-origin',
					headers: {'X-Requested-With': 'XMLHttpRequest'},
					signal: controller.signal
				});
				if(!response.ok){
					throw new Error('Cashpickup list request failed with status ' + response.status);
				}
				const html = await response.text();
				const template = document.createElement('template');
				template.innerHTML = html;
				const nextResults = template.content.querySelector('#cashpickupListResults');
				if(!nextResults){
					throw new Error('Cashpickup list response did not contain results');
				}
				if(sequence !== requestSequence) return;
				resultsContainer.replaceChildren(nextResults);
				updateAddressBar(currentPage, historyMode);
			}catch(error){
				if(error.name === 'AbortError') return;
				if(sequence === requestSequence){
					console.error('Unable to update the cashpickup list.', error);
					errorMessage.hidden = false;
				}
			}finally{
				if(sequence === requestSequence){
					activeRequest = null;
					resultsContainer.removeAttribute('aria-busy');
					loadingMessage.hidden = true;
				}
			}
		};

		filterForm.addEventListener('submit', function(event){
			event.preventDefault();
			window.clearTimeout(searchTimer);
			loadResults(1, 'replace');
		});

		if(findInput){
			findInput.addEventListener('input', function(){
				window.clearTimeout(searchTimer);
				pageInput.value = '1';
				searchTimer = window.setTimeout(function(){
					loadResults(1, 'replace');
				}, 250);
			});
		}

		filterForm.addEventListener('change', function(event){
			if(event.target === findInput) return;
			window.clearTimeout(searchTimer);
			pageInput.value = '1';
			loadResults(1, 'replace');
		});

		resultsContainer.addEventListener('click', function(event){
			const target = event.target instanceof Element ? event.target.closest('.js-cashpickup-pager a.page-link[href]') : null;
			if(!target) return;
			const targetUrl = new URL(target.href, window.location.href);
			const page = Number.parseInt(targetUrl.searchParams.get('page') || '', 10);
			if(!Number.isInteger(page) || page < 1) return;
			event.preventDefault();
			window.clearTimeout(searchTimer);
			loadResults(page, 'push');
		});

		window.addEventListener('popstate', function(){
			const page = Number.parseInt(new URL(window.location.href).searchParams.get('page') || '1', 10);
			loadResults(page, 'none');
		});
	})();

	(function(){
		let pressedCard = null;
		document.addEventListener('pointerdown', function(event){
			const card = event.target instanceof Element ? event.target.closest('.cashpickup-mobile-card') : null;
			if(!card) return;
			if(pressedCard) pressedCard.classList.remove('is-pressed');
			pressedCard = card;
			pressedCard.classList.add('is-pressed');
		}, {passive:true});
		const clearPressedCard = function(){
			if(pressedCard) pressedCard.classList.remove('is-pressed');
			pressedCard = null;
		};
		document.addEventListener('pointerup', clearPressedCard, {passive:true});
		document.addEventListener('pointercancel', clearPressedCard, {passive:true});
	})();

		document.addEventListener('submit', function(e){
			const formEl = e.target;
			if(!formEl) return;
			const action = String(formEl.getAttribute('action') || '');
			if(action.indexOf('/deny/') === -1) return;
			const reasonInput = formEl.querySelector('input[name="denial_reason"]');
			if(!reasonInput) return;
			const existing = String(reasonInput.value || '').trim();
			let reason = existing;
			if(reason === ''){
				reason = window.prompt('Reason for denial:');
				if(reason === null){
					e.preventDefault();
					return;
				}
				reason = String(reason || '').trim();
			}
			if(reason === ''){
				e.preventDefault();
				window.alert('Reason is required');
				return;
			}
			reasonInput.value = reason;
		}, true);
</script>

<?php endif; ?>
