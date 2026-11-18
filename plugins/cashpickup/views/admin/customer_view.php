<?php if(!user_can('view_cashpickup')): ?>
	<div class="alert alert-danger text-center">Access denied.</div>
<?php else: ?>

<?php
	require_once plugin_path('helpers/customer-ledger.php');
	require_once plugin_path('helpers/transfer-allocation.php');
	require_once plugin_path('helpers/customer-assignment.php');
	require_once plugin_path('helpers/permissions.php');
	$db = new \Core\Database;
	\CashpickupCustomerAssignment\ensure_schema($db);
	\CashpickupLedger\ensure_cashpickup_customer_schema($db);
	\CashpickupLedger\ensure_cashpickup_columns($db);
	\CashpickupLedger\ensure_cashpickup_credit_schema($db);
	\CashpickupLedger\repair_customer_transaction_links($db);
	$session = get_session();
	$user_id = (int)($session->id ?? 0);
	$isManagerOrAdmin = (user_can('is_manager') || user_can('is_admin'));
	$isOperationsManager = false;
	$isGeneralManager = false;
	$canAssignPayout = false;
	$canWithdraw = false;
	try {
		$isOperationsManager = \CashpickupPermissions\is_operations_manager($db, $user_id);
		$isGeneralManager = \CashpickupPermissions\is_general_manager($db, $user_id);
		$canAssignPayout = user_can('is_admin') || $isOperationsManager || $isGeneralManager;
		$canWithdraw = \CashpickupPermissions\can_withdraw_customer_payout($db, $user_id);
	} catch (\Throwable $e) {
		error_log('[cashpickup] Customer payout access check failed: ' . $e->getMessage());
	}
	$canApproveDeny = user_can('is_admin') || $isOperationsManager || $isGeneralManager;
	$canCreateCreditPayout = (user_can('is_admin') || user_can('is_manager'));
	$canEditCashpickup = (user_can('is_admin') || user_can('is_manager'));
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

	$isAjax = (string)($_GET['ajax'] ?? '') === '1';
	$customerId = (int)(URL(3) ?? 0);
	if ($customerId <= 0) {
		$customerId = (int)($_GET['customer_id'] ?? 0);
	}
	$customer = null;
	if ($customerId > 0) {
		$rows = $db->query("SELECT * FROM cashpickup_customers WHERE deleted = 0 AND id = :id LIMIT 1", ['id' => $customerId]) ?: [];
		if (!empty($rows)) {
			$customer = $rows[0];
		}
	}
	$customerApproved = \CashpickupLedger\customer_is_approved($customer);
	if ($customerApproved && $customerId > 0) {
		\CashpickupCustomerAssignment\migrate_legacy_customer_assignments($db, $customerId, $user_id);
	}

	if (empty($customer)):
?>
	<div class="alert alert-warning text-center">Customer not found.</div>
<?php else: ?>

<?php
	$phone = trim((string)($customer->phone_number ?? ''));
	$idNumber = trim((string)($customer->id_number ?? ''));
	list($baseMatchSql, $baseParams) = \CashpickupLedger\build_customer_match_sql($customerId, $phone, $idNumber, 'cashpickups', 'customer_page');
	$baseWhere = "deleted = 0 AND {$baseMatchSql}";
	$paidGrossExpr = "(COALESCE(amount_paid_out,0) + COALESCE(pickup_charge,0))";
	$activePaidOutExpr = \CashpickupLedger\active_payout_amount_sql();
	$activePaidOutExprLedger = \CashpickupLedger\active_payout_amount_sql('c');
	$statementReceivedExpr = \CashpickupLedger\statement_received_amount_sql();
	$statementDeltaExprLedger = \CashpickupLedger\statement_delta_sql('c');

	$metrics = [
		'tx_count' => 0,
		'total_receive' => 0.0,
		'total_paid' => 0.0,
		'total_withdrawals' => 0.0,
		'balance' => 0.0,
		'last_tx' => '',
	];
	try {
		$metrics = \CashpickupLedger\fetch_customer_balance_metrics($db, $customerId);
		// Still fetch last_tx separately
		$rowM = $db->query(
			"SELECT MAX(date_created) AS last_tx FROM cashpickups WHERE {$baseWhere}",
			$baseParams
		) ?: [];
		$metrics['last_tx'] = (string)($rowM[0]->last_tx ?? '');
	} catch (\Throwable $e) {
		// ignore
	}
	$customerAssignments = \CashpickupCustomerAssignment\get_customer_assignments($db, $customerId);
	$unassignedBalance = \CashpickupCustomerAssignment\get_unassigned_balance($db, $customerId);
	$viewerAssignedBalance = \CashpickupCustomerAssignment\get_remaining_amount($db, $customerId, $user_id);
	$withdrawAvailable = !$canWithdraw
		? 0.0
		: ($isOperationsManager
		? max(0.0, (float)$metrics['balance'])
		: min(max(0.0, (float)$metrics['balance']), $viewerAssignedBalance));
	$viewerChargeAssignments = [];
	foreach ($customerAssignments as $assignment) {
		if (strtolower(trim((string)($assignment->status ?? ''))) !== 'active'
			|| (int)($assignment->assigned_user_id ?? 0) !== $user_id
			|| (float)($assignment->remaining_amount ?? 0) <= 0.00001) {
			continue;
		}
		$viewerChargeAssignments[] = [
			'amount' => (float)($assignment->amount ?? 0),
			'pickup_charge' => (float)($assignment->pickup_charge ?? 0),
			'remaining_amount' => (float)($assignment->remaining_amount ?? 0),
		];
	}
	$assignmentUsers = [];
	if ($canAssignPayout) {
		$assignmentUsers = $db->query(
			"SELECT u.id, u.first_name, u.last_name
			 FROM users u
			 WHERE u.deleted = 0
			 AND EXISTS (
				SELECT 1 FROM attendance a
				WHERE a.deleted = 0
				AND a.user_id = u.id
				AND a.date = CURDATE()
				AND UPPER(a.remarks) IN ('PRESENT','LATE')
			 )
			 AND NOT EXISTS (
				SELECT 1
				FROM user_roles_map urm
				JOIN user_roles ur ON ur.id = urm.role_id
				WHERE urm.disabled = 0
				AND urm.user_id = u.id
				AND LOWER(TRIM(ur.role)) IN (
					'manager','admin','is_manager','is_admin',
					'general manager','general_manager','generalmanager','gm','is_general_manager',
					'operations manager','operations_manager','operationsmanager','ops manager','ops_manager','opsmanager','is_operations_manager'
				)
			 )
			 ORDER BY u.first_name, u.last_name"
		) ?: [];
	}
	$creditMetrics = [
		'total_credit_given' => 0.0,
		'total_credit_paid' => 0.0,
		'total_amount_in_credit' => 0.0,
	];
	try {
		$creditMetrics = \CashpickupLedger\fetch_customer_credit_metrics($db, $customerId);
	} catch (\Throwable $e) {
		$creditMetrics = [
			'total_credit_given' => 0.0,
			'total_credit_paid' => 0.0,
			'total_amount_in_credit' => 0.0,
		];
	}

	// CFA rates + bank list for the Withdraw form payment methods.
	$cfaRates = ['buying_rate' => 0.0, 'selling_rate' => 0.0];
	try {
		$cfaRates = \CashpickupLedger\cashpickup_settings_cfa_rates($db);
	} catch (\Throwable $e) {}
	$withdrawBanks = [
		'Absa Bank', 'Apex Nafana Bank', 'Apex Suma Bank', 'Access Bank',
		'Cal Bank', 'Ecobank', 'Fidelity Bank', 'First National Bank (FNB)',
		'First Bank Ghana (FBG)', 'GCB Bank', 'GT Bank', 'Stanbic Bank',
	];

	$find = trim((string)($_GET['find'] ?? ''));
	$sideFilter = strtolower(trim((string)($_GET['side'] ?? '')));
	$dateFrom = trim((string)($_GET['date_from'] ?? ''));
	$dateTo = trim((string)($_GET['date_to'] ?? ''));
	$statementFilters = [
		'find' => $find,
		'date_from' => $dateFrom,
		'date_to' => $dateTo,
		'side' => $sideFilter,
		'order' => 'desc',
	];
	$statementRows = [];
	$statementTotalRows = 0;
	try {
		$statementResult = \CashpickupLedger\fetch_customer_workstation_activity($db, $customerId, $statementFilters);
		$statementRows = is_array($statementResult['rows'] ?? null) ? $statementResult['rows'] : [];
		$statementTotalRows = (int)($statementResult['total_rows'] ?? count($statementRows));
	} catch (\Throwable $e) {
		$statementRows = [];
		$statementTotalRows = 0;
	}
	$statementExportParamsPdf = ['resource' => 'customer', 'id' => $customerId, 'format' => 'pdf'];
	$statementExportParamsXlsx = ['resource' => 'customer', 'id' => $customerId, 'format' => 'xlsx'];
	foreach (['find' => $find, 'date_from' => $dateFrom, 'date_to' => $dateTo] as $exportKey => $exportValue) {
		if (trim((string)$exportValue) !== '') {
			$statementExportParamsPdf[$exportKey] = $exportValue;
			$statementExportParamsXlsx[$exportKey] = $exportValue;
		}
	}
	$limit = (int)($_GET['limit'] ?? 10);
	$limit = $limit > 0 ? min(200, $limit) : 10;
	$pager = new \Core\Pager($limit);
	$offset = $pager->offset;
	$activityTotalRows = count($statementRows);
	$activityRows = array_slice($statementRows, $offset, $limit);
	$totalRows = $activityTotalRows;

	list($txMatchSql, $txParams) = \CashpickupLedger\build_customer_match_sql($customerId, $phone, $idNumber, 'm', 'customer_tx');
	$txConditions = ['m.deleted = 0', "LOWER(m.transaction_side) = 'receive'", $txMatchSql];
	if ($find !== '') {
		$txConditions[] = "(m.tracking_id LIKE :find
			OR m.source LIKE :find
			OR m.payment_method LIKE :find
			OR m.transaction_side LIKE :find
			OR EXISTS (
				SELECT 1
				FROM cashpickups p
				WHERE (p.deleted IS NULL OR p.deleted = 0)
				AND LOWER(p.transaction_side) = 'pay'
				AND (
					p.receive_id = m.id
					OR (
						COALESCE(p.tracking_id,'') <> ''
						AND COALESCE(m.tracking_id,'') <> ''
						AND p.tracking_id = m.tracking_id
					)
				)
				AND (
					p.tracking_id LIKE :find
					OR p.source LIKE :find
					OR p.payment_method LIKE :find
					OR p.transaction_side LIKE :find
				)
			))";
		$txParams['find'] = '%' . $find . '%';
	}
	if ($sideFilter === 'pay') {
		$txConditions[] = "EXISTS (
			SELECT 1
			FROM cashpickups pside
			WHERE (pside.deleted IS NULL OR pside.deleted = 0)
			AND LOWER(pside.transaction_side) = 'pay'
			AND (
				pside.receive_id = m.id
				OR (
					COALESCE(pside.tracking_id,'') <> ''
					AND COALESCE(m.tracking_id,'') <> ''
					AND pside.tracking_id = m.tracking_id
				)
			)
		)";
	}
	if ($dateFrom !== '') {
		$txConditions[] = 'DATE(m.date_created) >= :date_from';
		$txParams['date_from'] = $dateFrom;
	}
	if ($dateTo !== '') {
		$txConditions[] = 'DATE(m.date_created) <= :date_to';
		$txParams['date_to'] = $dateTo;
	}
	$where = $txConditions ? ('WHERE ' . implode(' AND ', $txConditions)) : '';
	$trackingGroupExprM = "CASE WHEN COALESCE(m.tracking_id,'') <> '' THEN m.tracking_id ELSE CONCAT('cashpickup-', m.id) END";
	list($matchSqlLedger, $balanceParams) = \CashpickupLedger\build_customer_match_sql($customerId, $phone, $idNumber, 'c', 'customer_balance');
	$balanceSubquery = "(
		SELECT COALESCE(SUM({$statementDeltaExprLedger}),0)
		FROM (
			SELECT DISTINCT id, customer_id, ledger_transfer_from_customer_id, ledger_transfer_to_customer_id,
			                phone_number, customer_id_number, date_created, transaction_side,
			                credit_mode, transaction_status, confirmed_by, amount_involved,
			                amount_paid_out, approval_status, pickup_charge
			FROM cashpickups
			WHERE (deleted IS NULL OR deleted = 0)
		) AS c
		WHERE {$matchSqlLedger}
		AND (
			c.date_created < m.date_created
			OR (c.date_created = m.date_created AND c.id <= m.id)
		)
	)";

	$totalRow = $db->query(
		"SELECT COUNT(DISTINCT {$trackingGroupExprM}) AS c
		 FROM cashpickups AS m
		 {$where}",
		$txParams
	) ?: [];
	$totalRows = (int)($totalRow[0]->c ?? 0);

	$hasTransferLedger = false;
	try {
		$hasTransferLedger = $db->table_exists(['cashpickup_transfers']);
	} catch (\Throwable $e) { $hasTransferLedger = false; }
	$trSelect = $hasTransferLedger ? "COALESCE(tr.transferred,0)" : "0";
	$trJoin = $hasTransferLedger ? "\n\t\tLEFT JOIN (\n\t\t\tSELECT CASE WHEN COALESCE(r.tracking_id,'') <> '' THEN r.tracking_id ELSE CONCAT('cashpickup-', t.receive_id) END AS tracking_group,\n\t\t\t\tCOALESCE(SUM(t.amount),0) AS transferred\n\t\t\tFROM cashpickup_transfers t\n\t\t\tLEFT JOIN cashpickups r ON r.id = t.receive_id\n\t\t\tGROUP BY tracking_group\n\t\t) AS tr ON tr.tracking_group = grp.tracking_group" : "";
	$txRowParams = $txParams;
	$payoutParams = $baseParams;
	$viewerSortJoin = '';
	$viewerSortOrder = 'm.id DESC';
	$viewerRemainingSelect = '0';
	$payoutSortJoin = '';
	$payoutSortOrder = 'm.date_created DESC, m.id DESC';
	if (!$isManagerOrAdmin && $user_id > 0 && $hasTransferLedger) {
		$txRowParams['viewer_sort_user_id'] = $user_id;
		$payoutParams['viewer_sort_user_id'] = $user_id;
		$viewerSortJoin = "\n\t\tLEFT JOIN (\n\t\t\tSELECT receive_id,\n\t\t\t\tMAX(date_created) AS last_assigned_at,\n\t\t\t\tCOALESCE(SUM(amount),0) AS assigned_gross\n\t\t\tFROM cashpickup_transfers\n\t\t\tWHERE receive_id IS NOT NULL\n\t\t\tAND to_user_id = :viewer_sort_user_id\n\t\t\tAND (\n\t\t\t\tapproval_status IS NULL\n\t\t\t\tOR approval_status = ''\n\t\t\t\tOR LOWER(approval_status) IN ('approved','pending')\n\t\t\t)\n\t\t\tGROUP BY receive_id\n\t\t) AS viewer_tr ON viewer_tr.receive_id = m.id\n\t\tLEFT JOIN (\n\t\t\tSELECT receive_id,\n\t\t\t\tCOALESCE(SUM({$paidGrossExpr}),0) AS paid_requested\n\t\t\tFROM cashpickups\n\t\t\tWHERE (deleted IS NULL OR deleted = 0)\n\t\t\tAND LOWER(transaction_side) = 'pay'\n\t\t\tAND (\n\t\t\t\tapproval_status IS NULL\n\t\t\t\tOR approval_status = ''\n\t\t\t\tOR LOWER(approval_status) IN ('approved','pending')\n\t\t\t)\n\t\t\tAND transaction_status = 'Approved'\n\t\t\tAND (confirmed_by IS NOT NULL AND confirmed_by != '')\n\t\t\tAND COALESCE(amount_paid_out,0) > 0\n\t\t\tAND (created_by = :viewer_sort_user_id OR assigned_user_id = :viewer_sort_user_id)\n\t\t\tGROUP BY receive_id\n\t\t) AS viewer_paid ON viewer_paid.receive_id = m.id";
		$viewerSortOrder = "CASE WHEN (COALESCE(viewer_tr.assigned_gross,0) - COALESCE(viewer_paid.paid_requested,0)) > 0.00001 THEN 0 ELSE 1 END, COALESCE(viewer_tr.last_assigned_at, m.date_created) DESC, m.id DESC";
		$viewerRemainingSelect = 'GREATEST(0, COALESCE(viewer_tr.assigned_gross,0) - COALESCE(viewer_paid.paid_requested,0))';
		$payoutSortJoin = $viewerSortJoin;
		$payoutSortOrder = $viewerSortOrder;
	}

	$txRows = $db->query(
		"SELECT m.*, u.first_name, u.last_name, u.image AS user_image,
		        au.first_name AS assigned_first_name, au.last_name AS assigned_last_name,
		        grp.group_received,
		        grp.receive_parts,
		        grp.tracking_group,
		        0 AS paid_requested,
		        0 AS paid_out,
		        0 AS payout_parts,
		        0 AS pending_reserved,
		        0 AS pending_parts,
		        0 AS transferred_to_agents,
		        0 AS ledger_balance,
		        {$viewerRemainingSelect} AS viewer_remaining
		 FROM (
			SELECT MAX(m.id) AS row_id,
			       {$trackingGroupExprM} AS tracking_group,
			       COALESCE(SUM(
			       	CASE
						WHEN " . \CashpickupLedger\normalized_cashpickup_credit_mode_sql('m') . " = 'credit' THEN 0
			       		ELSE COALESCE(m.amount_involved,0)
			       	END
			       ),0) AS group_received,
			       COUNT(*) AS receive_parts
			FROM (SELECT DISTINCT m.* FROM cashpickups AS m) AS m
			{$where}
			GROUP BY tracking_group
		 ) AS grp
			JOIN (SELECT DISTINCT m.* FROM cashpickups AS m) AS m ON m.id = grp.row_id
		 LEFT JOIN users AS u ON m.created_by = u.id
		 LEFT JOIN users AS au ON m.assigned_user_id = au.id
		 {$viewerSortJoin}
		 ORDER BY {$viewerSortOrder}
		 LIMIT {$limit} OFFSET {$offset}",
		$txRowParams
	);
	$txRows = $txRows ?: [];
	if (!empty($txRows)) {
		$pageReceiveIds = [];
		$pageTrackingGroups = [];
		$receiveGroupById = [];
		foreach ($txRows as $tx) {
			$rid = (int)($tx->id ?? 0);
			$group = trim((string)($tx->tracking_group ?? ''));
			if ($group === '' && $rid > 0) {
				$group = 'cashpickup-' . $rid;
				$tx->tracking_group = $group;
			}
			if ($rid > 0) {
				$pageReceiveIds[$rid] = $rid;
				$receiveGroupById[$rid] = $group;
			}
			if ($group !== '') {
				$pageTrackingGroups[$group] = $group;
			}
			$tx->paid_requested = 0.0;
			$tx->paid_out = 0.0;
			$tx->payout_parts = 0;
			$tx->pending_reserved = 0.0;
			$tx->pending_parts = 0;
			$tx->transferred_to_agents = 0.0;
		}

		$makePlaceholders = static function(array $values, string $prefix, array &$params): array {
			$placeholders = [];
			$idx = 0;
			foreach ($values as $value) {
				$key = $prefix . $idx++;
				$placeholders[] = ':' . $key;
				$params[$key] = $value;
			}
			return $placeholders;
		};

		$payConditions = [];
		$payParams = [];
		if (!empty($pageTrackingGroups)) {
			$payConditions[] = 'tracking_id IN (' . implode(',', $makePlaceholders(array_values($pageTrackingGroups), 'ptg', $payParams)) . ')';
		}
		if (!empty($pageReceiveIds)) {
			$payConditions[] = 'receive_id IN (' . implode(',', $makePlaceholders(array_values($pageReceiveIds), 'prid', $payParams)) . ')';
		}
		if (!empty($payConditions)) {
			$payRows = $db->query(
				"SELECT DISTINCT receive_id, tracking_id, approval_status, transaction_status, confirmed_by, amount_paid_out, pickup_charge, credit_mode
				 FROM cashpickups
				 WHERE (deleted IS NULL OR deleted = 0)
				 AND LOWER(transaction_side) = 'pay'
				 AND (" . implode(' OR ', $payConditions) . ")",
				$payParams
			) ?: [];
			$paidByGroup = [];
			foreach ($payRows as $payRow) {
				$rid = (int)($payRow->receive_id ?? 0);
				$tracking = trim((string)($payRow->tracking_id ?? ''));
				$group = ($rid > 0 && isset($receiveGroupById[$rid])) ? $receiveGroupById[$rid] : $tracking;
				if ($group === '' && $rid > 0) {
					$group = 'cashpickup-' . $rid;
				}
				if ($group === '' || !isset($pageTrackingGroups[$group])) {
					continue;
				}
				if (!isset($paidByGroup[$group])) {
					$paidByGroup[$group] = [
						'paid_requested' => 0.0,
						'paid_out' => 0.0,
						'payout_parts' => 0,
						'pending_reserved' => 0.0,
						'pending_parts' => 0,
					];
				}
				$pApproval = strtolower(trim((string)($payRow->approval_status ?? '')));
				$pTxStatus = strtolower(trim((string)($payRow->transaction_status ?? '')));
				$pConfirmedBy = trim((string)($payRow->confirmed_by ?? ''));
				$pPaidOut = (float)($payRow->amount_paid_out ?? 0);
				$pGross = $pPaidOut + (float)($payRow->pickup_charge ?? 0);
				$isActivePay = ($pApproval === '' || in_array($pApproval, ['approved', 'pending'], true))
					&& $pTxStatus === 'approved'
					&& $pConfirmedBy !== ''
					&& $pPaidOut > 0;
				if (!$isActivePay) {
					continue;
				}
				$paidByGroup[$group]['paid_requested'] += $pGross;
				$paidByGroup[$group]['paid_out'] += $pPaidOut;
				$paidByGroup[$group]['payout_parts']++;
				if ($pApproval === 'pending') {
					$paidByGroup[$group]['pending_reserved'] += $pGross;
					$paidByGroup[$group]['pending_parts']++;
				}
			}
			foreach ($txRows as $tx) {
				$group = trim((string)($tx->tracking_group ?? ''));
				if ($group === '' || empty($paidByGroup[$group])) {
					continue;
				}
				$stats = $paidByGroup[$group];
				$tx->paid_requested = (float)$stats['paid_requested'];
				$tx->paid_out = (float)$stats['paid_out'];
				$tx->payout_parts = (int)$stats['payout_parts'];
				$tx->pending_reserved = (float)$stats['pending_reserved'];
				$tx->pending_parts = (int)$stats['pending_parts'];
			}
		}

		if ($hasTransferLedger && (!empty($pageTrackingGroups) || !empty($pageReceiveIds))) {
			$transferConditions = [];
			$transferParams = [];
			if (!empty($pageTrackingGroups)) {
				$transferConditions[] = 'r.tracking_id IN (' . implode(',', $makePlaceholders(array_values($pageTrackingGroups), 'ttg', $transferParams)) . ')';
			}
			if (!empty($pageReceiveIds)) {
				$transferConditions[] = 't.receive_id IN (' . implode(',', $makePlaceholders(array_values($pageReceiveIds), 'trid', $transferParams)) . ')';
			}
			$transferRows = $db->query(
				"SELECT CASE WHEN COALESCE(r.tracking_id,'') <> '' THEN r.tracking_id ELSE CONCAT('cashpickup-', t.receive_id) END AS tracking_group,
				        COALESCE(SUM(t.amount),0) AS transferred
				 FROM cashpickup_transfers t
				 LEFT JOIN cashpickups r ON r.id = t.receive_id
				 WHERE " . implode(' OR ', $transferConditions) . "
				 GROUP BY tracking_group",
				$transferParams
			) ?: [];
			$transferredByGroup = [];
			foreach ($transferRows as $trRow) {
				$group = trim((string)($trRow->tracking_group ?? ''));
				if ($group !== '' && isset($pageTrackingGroups[$group])) {
					$transferredByGroup[$group] = (float)($trRow->transferred ?? 0);
				}
			}
			foreach ($txRows as $tx) {
				$group = trim((string)($tx->tracking_group ?? ''));
				if ($group !== '' && isset($transferredByGroup[$group])) {
					$tx->transferred_to_agents = (float)$transferredByGroup[$group];
				}
			}
		}
	}
	$payoutCandidate = 0;
	$payoutCandidateIsCredit = false;
	if ($canWithdraw && $withdrawAvailable > 0.00001) {
		$payoutCandidate = $customerId;
	}
	if ($canWithdraw && !$isOperationsManager) {
		try {
			list($matchSql, $payoutMatchParams) = \CashpickupLedger\build_customer_match_sql($customerId, $phone, $idNumber, 'm', 'customer_payout');
			$payoutRows = $db->query(
				"SELECT m.id, m.amount_involved, m.approval_status, m.assigned_user_id, m.credit_mode,
				        {$viewerRemainingSelect} AS viewer_remaining
				 FROM cashpickups m
				 {$payoutSortJoin}
				 WHERE m.deleted = 0
				 AND LOWER(m.transaction_side) = 'receive'
				 AND {$matchSql}
				 ORDER BY {$payoutSortOrder}
				 LIMIT 200",
				array_merge($payoutParams, $payoutMatchParams)
			) ?: [];
			foreach ($payoutRows as $tx) {
				$approvalStatus = strtolower(trim((string)($tx->approval_status ?? 'pending')));
				if ($approvalStatus === '') $approvalStatus = 'pending';
				$assignedTo = (int)($tx->assigned_user_id ?? 0);
				$isCreditPayout = strtolower(trim((string)($tx->credit_mode ?? ''))) === 'credit';
				$allocationRemaining = isset($tx->viewer_remaining)
					? (float)$tx->viewer_remaining
					: $getAllocationRemaining((int)($tx->id ?? 0));
				$canPayoutThis = (
					($approvalStatus === 'approved' && $allocationRemaining > 0.00001)
					|| ($isCreditPayout && $assignedTo === (int)$user_id && $approvalStatus === 'pending')
				);
				if ($canPayoutThis) {
					$payoutCandidate = (int)($tx->id ?? 0);
					$payoutCandidateIsCredit = $isCreditPayout;
					break;
				}
			}
		} catch (\Throwable $e) {
			// Keep the customer-level assignment payout action available.
		}
	}

	$txRows = $activityRows;
	$totalRows = $activityTotalRows;
	$renderLegacyTxResults = function() use ($txRows, $admin_route, $plugin_route, $user_id, $isManagerOrAdmin, $canApproveDeny, $canEditCashpickup, $totalRows, $limit, $pager, $getAllocationRemaining) {
		$totalPages = (int)ceil(((float)$totalRows) / max(1, (int)$limit));
		$csrfField = csrf();
		?>
		<div class="card border-0 shadow-sm">
			<div class="table-responsive">
				<table class="table table-sm table-hover mb-0">
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
						<?php if(empty($txRows)): ?>
							<tr><td colspan="9" class="text-center text-muted">No transactions found.</td></tr>
						<?php else: ?>
							<?php foreach($txRows as $tx): ?>
								<?php
									$isCreditPayout = strtolower(trim((string)($tx->credit_mode ?? ''))) === 'credit';
									$paidRequested = (float)($tx->paid_requested ?? 0);
									$pendingReserved = (float)($tx->pending_reserved ?? 0);
									$pendingParts = (int)($tx->pending_parts ?? 0);
									$receivedAmount = (float)($tx->group_received ?? $tx->amount_involved ?? 0);
									$displayReceived = $isCreditPayout ? 0.0 : $receivedAmount;
									$comparisonAmount = $isCreditPayout ? (float)($tx->amount_involved ?? 0) : $displayReceived;
									$receiveParts = (int)($tx->receive_parts ?? 1);
									$rawRemaining = $isCreditPayout ? (0 - $paidRequested) : ($displayReceived - $paidRequested);
				$overage = !$isCreditPayout ? max(0, (float)(-$rawRemaining)) : 0.0;
				$parts = (int)($tx->payout_parts ?? 0);
				$ledgerBalance = round((float)$rawRemaining, 2);
				$fullyPaid = $comparisonAmount <= 0.00001
					? ($paidRequested > 0.00001)
					: ($paidRequested >= ($comparisonAmount - 0.00001));
				$payoutStatusText = $fullyPaid ? 'Paid' : ($paidRequested > 0 ? 'Partial' : 'Not paid');
				$balanceTone = $ledgerBalance < -0.00001 ? 'text-danger fw-semibold' : '';
									$approvalStatusRaw = strtolower(trim((string)($tx->approval_status ?? 'pending')));
									if ($approvalStatusRaw === '') $approvalStatusRaw = 'pending';
				$displayApprovalStatus = ($isCreditPayout && !in_array($approvalStatusRaw, ['denied','reversed'], true) && !$fullyPaid) ? 'pending' : $approvalStatusRaw;
				$displayApprovalText = ($isCreditPayout && !in_array($approvalStatusRaw, ['denied','reversed'], true) && !$fullyPaid) ? 'Pending payout' : ucfirst($displayApprovalStatus);
				$assignedTo = (int)($tx->assigned_user_id ?? 0);
				$canAssignThis = $isManagerOrAdmin
					&& ($approvalStatusRaw === 'approved' || ($isCreditPayout && !in_array($approvalStatusRaw, ['denied','reversed'], true) && !$fullyPaid));
				$allocationRemaining = isset($tx->viewer_remaining)
					? (float)$tx->viewer_remaining
					: $getAllocationRemaining((int)($tx->id ?? 0));
				$canPayoutThis = !$isManagerOrAdmin
					&& (
						($approvalStatusRaw === 'approved' && $allocationRemaining > 0.00001)
						|| ($isCreditPayout && $assignedTo === (int)$user_id && $displayApprovalStatus === 'pending')
					);
				$canApproveThis = $canApproveDeny && $approvalStatusRaw === 'pending' && !$isCreditPayout;
			?>
								<tr>
									<td>
										<div class="fw-semibold">#<?=esc((string)($tx->id ?? ''))?></div>
										<div class="small text-muted"><?=esc($tx->date_created ?? $tx->date ?? '')?></div>
										<div class="mt-1">
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
										<div class="fw-semibold"><?=esc($tx->source ?? '')?></div>
										<div class="small text-muted">Tracking: <?=esc($tx->tracking_id ?? '')?></div>
									</td>
									<td>
										<div class="fw-semibold"><?=esc($tx->sender_details ?? '')?></div>
										<div class="small text-muted">Phone: <?=esc($tx->phone_number ?? '')?></div>
										<?php $assignedName = trim(((string)($tx->assigned_first_name ?? '')).' '.((string)($tx->assigned_last_name ?? ''))); ?>
										<?php if($assignedName !== ''): ?>
											<div class="small text-muted">Assigned to: <?=esc($assignedName)?></div>
										<?php endif; ?>
									</td>
									<td class="text-end">
										GH&#8373; <?=number_format((float)$displayReceived, 2)?>
										<?php if($receiveParts > 1): ?><span class="text-muted">(<?=number_format((float)$receiveParts)?>)</span><?php endif; ?>
									</td>
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
									<td><?=esc($statementType)?></td>
									<td>
										<?php if($displayApprovalStatus === 'approved'): ?>
											<span class="badge bg-success">Approved</span>
										<?php elseif($displayApprovalStatus === 'reversed'): ?>
											<span class="badge bg-danger">Reversed</span>
										<?php elseif($displayApprovalStatus === 'denied'): ?>
											<span class="badge bg-danger">Denied</span>
											<?php if(trim((string)($tx->denial_reason ?? '')) !== ''): ?>
												<div class="small text-danger mt-1" title="<?=esc((string)($tx->denial_reason))?>"><?=esc((string)($tx->denial_reason))?></div>
											<?php endif; ?>
										<?php else: ?>
											<span class="badge bg-warning text-dark"><?=esc($displayApprovalText)?></span>
										<?php endif; ?>
									</td>
									<td class="text-end">
										<div class="<?=$balanceTone?>"><?=number_format((float)$ledgerBalance, 2)?></div>
										<?php if($pendingParts > 0 && $pendingReserved > 0.00001): ?>
											<div class="small text-warning">Pending: <?=number_format((float)$pendingReserved, 2)?> (<?=number_format((float)$pendingParts)?>)</div>
										<?php endif; ?>
										<?php if(!$isCreditPayout && (float)$overage > 0.00001): ?>
											<div class="small text-danger">Overage: <?=number_format((float)$overage, 2)?></div>
										<?php endif; ?>
									</td>
									<td class="text-end">
										<?php if($canApproveThis): ?>
											<form method="post" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/approve/<?=esc((string)($tx->id ?? 0))?>" class="d-inline">
												<?=$csrfField?>
												<button class="btn btn-icon btn-success" type="submit" title="Approve"><i class="fa fa-check"></i></button>
											</form>
											<button class="btn btn-icon btn-danger" type="button" title="Reject" aria-label="Reject" data-cashpickup-reject data-deny-url="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/deny/<?=esc((string)($tx->id ?? 0))?>" data-transaction-id="<?=esc((string)($tx->id ?? 0))?>" data-customer-id="<?=esc((string)$customerId)?>"><i class="fa fa-times"></i></button>
										<?php endif; ?>
										<a class="btn btn-icon btn-outline-primary" title="View" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=esc((string)($tx->id ?? 0))?>"><i class="fa fa-eye"></i></a>
										<?php
											$cancelStatuses = strtolower(trim((string)($tx->approval_status ?? ''))) . ' '
												. strtolower(trim((string)($tx->transaction_status ?? ''))) . ' ' . $displayApprovalStatus;
											$cancelBlocked = preg_match('/denied|deny|rejected|reject|declined|decline|cancelled|canceled/i', $cancelStatuses) === 1;
										?>
										<?php if(user_can('is_admin') && !$cancelBlocked): ?>
											<button class="btn btn-icon btn-outline-danger" type="button" title="Cancel transaction" aria-label="Cancel transaction" data-cashpickup-cancel data-cancel-url="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/cancel/<?=esc((string)($tx->id ?? 0))?>" data-transaction-id="<?=esc((string)($tx->id ?? 0))?>">
												<i class="fa fa-ban"></i>
											</button>
										<?php endif; ?>
										<?php if($canPayoutThis): ?>
											<a class="btn btn-icon btn-warning" title="Payout" href="<?=$isCreditPayout ? ROOT.'/'.$admin_route.'/'.$plugin_route.'/payout/'.esc((string)($tx->id ?? 0)) : ROOT.'/'.$admin_route.'/'.$plugin_route.'/customer/'.esc((string)$customerId).'#withdraw-section'?>"><i class="fa fa-money-bill-wave"></i></a>
										<?php endif; ?>
										<?php $creatorId = (int)($tx->created_by ?? 0); ?>
										<?php if($canAssignThis): ?>
											<a class="btn btn-icon btn-info" title="Assign" href="<?=$isCreditPayout ? ROOT.'/'.$admin_route.'/'.$plugin_route.'/view/'.esc((string)($tx->id ?? 0)).'#transfer' : ROOT.'/'.$admin_route.'/'.$plugin_route.'/customer/'.esc((string)$customerId).'#withdraw-section'?>"><i class="fa fa-user-plus"></i></a>
										<?php endif; ?>
										<?php if($approvalStatusRaw === 'approved' && !$fullyPaid && $canEditCashpickup): ?>
											<a class="btn btn-icon btn-outline-secondary" title="Edit" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit/<?=esc((string)($tx->id ?? 0))?>"><i class="fa fa-pen"></i></a>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<div
			class="mt-3 js-cashpickup-customer-pager"
			data-total-rows="<?=esc((string)$totalRows)?>"
			data-limit="<?=esc((string)$limit)?>"
			data-page="<?=esc((string)($pager->page_number ?? 1))?>"
			data-total-pages="<?=esc((string)$totalPages)?>"
		></div>
		<?php
	};

	$renderTxResults = function() use ($txRows, $admin_route, $plugin_route, $customerId, $totalRows, $limit, $pager, $canApproveDeny) {
		$totalPages = (int)ceil(((float)$totalRows) / max(1, (int)$limit));
		$csrfField = csrf();
		$typeLabels = [
			'deposit' => 'Deposit',
			'payout' => 'Payout',
			'credit_issued' => 'Credit issued',
			'credit_payout' => 'Credit payout',
			'credit_repayment' => 'Credit repayment',
		];
		$typeClasses = [
			'deposit' => 'success',
			'payout' => 'warning',
			'credit_issued' => 'primary',
			'credit_payout' => 'info',
			'credit_repayment' => 'secondary',
		];
		?>
		<div class="card border-0 shadow-sm cashpickup-activity-results">
			<div class="d-md-none p-2 p-sm-3 cashpickup-activity-cards">
				<?php if(empty($txRows)): ?>
					<div class="text-center text-body-secondary py-4">No account activity matches these filters.</div>
				<?php else: ?>
					<?php foreach($txRows as $tx): ?>
						<?php
							$type = (string)($tx->entry_type ?? 'payout');
							$typeLabel = $typeLabels[$type] ?? ucfirst(str_replace('_', ' ', $type));
							$typeClass = $typeClasses[$type] ?? 'secondary';
							$received = (float)($tx->received_amount ?? 0);
							$paid = (float)($tx->payout_amount ?? 0);
							$credit = (float)($tx->credit_amount ?? 0);
							$repayment = (float)($tx->credit_repayment_amount ?? 0);
							$relatedId = (int)($tx->related_receive_id ?? $tx->id ?? 0);
							$recordId = (int)($tx->id ?? 0);
							$approvalStatus = strtolower(trim((string)($tx->approval_status ?? '')));
							$transactionStatus = strtolower(trim((string)($tx->transaction_status ?? '')));
							$statusText = trim((string)($tx->approval_status ?? '')) !== ''
								? (string)$tx->approval_status
								: (string)($tx->transaction_status ?? 'Recorded');
							$statusValue = strtolower(trim($statusText));
							$statusTone = strpos($statusValue, 'approved') !== false ? 'success'
								: (strpos($statusValue, 'pending') === 0 ? 'warning'
									: (preg_match('/denied|reversed|rejected/i', $statusValue) ? 'danger' : 'secondary'));
							$canApproveActivity = $canApproveDeny
								&& $recordId > 0
								&& $type !== 'credit_issued'
								&& ($approvalStatus === 'pending'
									|| ($approvalStatus === '' && strpos($transactionStatus, 'pending') === 0));
						?>
						<article class="card mb-2 cashpickup-activity-card">
							<div class="card-body p-3">
								<div class="d-flex align-items-start justify-content-between gap-2">
									<div class="min-w-0">
										<div class="fw-semibold"><?=esc((string)($tx->sender_details ?? 'Account activity'))?></div>
										<div class="small text-body-secondary">Customer #<?=esc((string)$customerId)?> · <?=esc((string)get_date_time($tx->date_created ?? ''))?></div>
									</div>
									<span class="badge text-bg-<?=$typeClass?> flex-shrink-0"><?=esc($typeLabel)?></span>
								</div>
								<div class="small text-body-secondary mt-2">
									<?php if(trim((string)($tx->source ?? '')) !== ''): ?><?=esc((string)$tx->source)?><?php endif; ?>
									<?php if(trim((string)($tx->payment_method ?? '')) !== ''): ?> · <?=esc((string)$tx->payment_method)?><?php endif; ?>
									<?php if(trim((string)($tx->tracking_id ?? '')) !== ''): ?> · Ref <?=esc((string)$tx->tracking_id)?><?php endif; ?>
								</div>
								<div class="row g-2 mt-1 small">
									<?php if($received > 0): ?><div class="col-6"><span class="text-body-secondary">Cash in</span><div class="fw-semibold text-success">GH&#8373; <?=number_format($received, 2)?></div></div><?php endif; ?>
									<?php if($paid > 0): ?><div class="col-6"><span class="text-body-secondary">Cash out</span><div class="fw-semibold text-danger">GH&#8373; <?=number_format($paid, 2)?></div></div><?php endif; ?>
									<?php if($credit > 0): ?><div class="col-6"><span class="text-body-secondary">Credit</span><div class="fw-semibold">GH&#8373; <?=number_format($credit, 2)?></div></div><?php endif; ?>
									<?php if($repayment > 0): ?><div class="col-6"><span class="text-body-secondary">Repayment</span><div class="fw-semibold">GH&#8373; <?=number_format($repayment, 2)?></div></div><?php endif; ?>
									<div class="col-6"><span class="text-body-secondary">Account balance</span><div class="fw-bold">GH&#8373; <?=number_format((float)($tx->balance_amount ?? 0), 2)?></div></div>
								</div>
								<div class="d-flex justify-content-between align-items-center border-top mt-2 pt-2">
									<span class="badge text-bg-<?=$statusTone?> <?=$statusTone === 'warning' ? 'text-dark' : ''?>"><?=esc(ucfirst($statusText))?></span>
									<div class="d-flex gap-1">
										<?php if($canApproveActivity): ?>
											<form method="post" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/approve/<?=$recordId?>">
												<?=$csrfField?>
												<input type="hidden" name="customer_id" value="<?=esc((string)$customerId)?>">
												<button class="btn btn-sm btn-success" type="submit"><i class="fa fa-check me-1"></i> Approve</button>
											</form>
											<button class="btn btn-sm btn-outline-danger" type="button" data-cashpickup-reject data-deny-url="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/deny/<?=$recordId?>" data-transaction-id="<?=esc((string)$recordId)?>" data-customer-id="<?=esc((string)$customerId)?>"><i class="fa fa-times me-1"></i> Reject</button>
										<?php endif; ?>
										<?php if($relatedId > 0): ?>
											<a class="btn btn-sm btn-outline-primary" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$relatedId?>"><i class="fa fa-arrow-up-right-from-square me-1"></i> Details</a>
										<?php endif; ?>
									</div>
								</div>
							</div>
						</article>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
			<div class="d-none d-md-block table-responsive">
				<table class="table table-hover align-middle mb-0 cashpickup-activity-table">
					<thead>
						<tr><th>Date</th><th>Activity / Reference</th><th class="text-end">Cash flow</th><th class="text-end">Balance</th><th class="text-end">Action</th></tr>
					</thead>
					<tbody>
						<?php if(empty($txRows)): ?>
							<tr><td colspan="5" class="text-center text-body-secondary py-4">No account activity matches these filters.</td></tr>
						<?php else: ?>
							<?php foreach($txRows as $tx): ?>
								<?php
									$type = (string)($tx->entry_type ?? 'payout');
									$typeLabel = $typeLabels[$type] ?? ucfirst(str_replace('_', ' ', $type));
									$typeClass = $typeClasses[$type] ?? 'secondary';
									$received = (float)($tx->received_amount ?? 0);
									$paid = (float)($tx->payout_amount ?? 0);
									$credit = (float)($tx->credit_amount ?? 0);
									$repayment = (float)($tx->credit_repayment_amount ?? 0);
									$relatedId = (int)($tx->related_receive_id ?? $tx->id ?? 0);
									$recordId = (int)($tx->id ?? 0);
									$approvalStatus = strtolower(trim((string)($tx->approval_status ?? '')));
									$transactionStatus = strtolower(trim((string)($tx->transaction_status ?? '')));
									$statusText = trim((string)($tx->approval_status ?? '')) !== ''
										? (string)$tx->approval_status
										: (string)($tx->transaction_status ?? 'Recorded');
									$statusValue = strtolower(trim($statusText));
									$statusTone = strpos($statusValue, 'approved') !== false ? 'success'
										: (strpos($statusValue, 'pending') === 0 ? 'warning'
											: (preg_match('/denied|reversed|rejected/i', $statusValue) ? 'danger' : 'secondary'));
									$canApproveActivity = $canApproveDeny
										&& $recordId > 0
										&& $type !== 'credit_issued'
										&& ($approvalStatus === 'pending'
											|| ($approvalStatus === '' && strpos($transactionStatus, 'pending') === 0));
								?>
								<tr>
									<td class="text-nowrap">
										<div><?=esc((string)get_date_time($tx->date_created ?? ''))?></div>
										<div class="small text-body-secondary">Record #<?=esc((string)($tx->id ?? ''))?></div>
										<div class="small text-body-secondary">Customer #<?=esc((string)$customerId)?></div>
									</td>
									<td>
										<div class="d-flex align-items-center gap-2"><span class="badge text-bg-<?=$typeClass?>"><?=esc($typeLabel)?></span><span class="fw-semibold"><?=esc((string)($tx->sender_details ?? 'Account activity'))?></span></div>
										<div class="small text-body-secondary"><?=esc(implode(' · ', array_filter([(string)($tx->source ?? ''), (string)($tx->payment_method ?? ''), trim((string)($tx->tracking_id ?? '')) !== '' ? 'Ref ' . (string)$tx->tracking_id : ''])))?></div>
									</td>
									<td class="text-end">
										<div class="d-inline-flex flex-column align-items-end gap-1">
											<span class="badge text-bg-<?=$statusTone?> <?=$statusTone === 'warning' ? 'text-dark' : ''?>"><?=esc(ucfirst($statusText))?></span>
											<?php if($received > 0): ?><span class="cashpickup-flow-amount text-success"><i class="fa-solid fa-arrow-down me-1" aria-hidden="true"></i>GH&#8373; <?=number_format($received, 2)?></span><?php endif; ?>
											<?php if($paid > 0): ?><span class="cashpickup-flow-amount text-danger"><i class="fa-solid fa-arrow-up me-1" aria-hidden="true"></i>GH&#8373; <?=number_format($paid, 2)?></span><?php endif; ?>
											<?php if($credit > 0): ?><span class="cashpickup-flow-amount text-primary">Credit: GH&#8373; <?=number_format($credit, 2)?></span><?php endif; ?>
											<?php if($repayment > 0): ?><span class="cashpickup-flow-amount text-success">Repayment: GH&#8373; <?=number_format($repayment, 2)?></span><?php endif; ?>
											<?php if($received <= 0 && $paid <= 0 && $credit <= 0 && $repayment <= 0): ?><span class="text-body-secondary">—</span><?php endif; ?>
										</div>
									</td>
									<td class="text-end fw-semibold"><?=number_format((float)($tx->balance_amount ?? 0), 2)?></td>
									<td class="text-end">
										<?php if($canApproveActivity): ?>
											<form method="post" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/approve/<?=$recordId?>" class="d-inline">
												<?=$csrfField?>
												<input type="hidden" name="customer_id" value="<?=esc((string)$customerId)?>">
												<button class="btn btn-sm btn-success" type="submit" title="Approve" aria-label="Approve"><i class="fa fa-check"></i></button>
											</form>
											<button class="btn btn-sm btn-outline-danger" type="button" title="Reject" aria-label="Reject" data-cashpickup-reject data-deny-url="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/deny/<?=$recordId?>" data-transaction-id="<?=esc((string)$recordId)?>" data-customer-id="<?=esc((string)$customerId)?>"><i class="fa fa-times"></i></button>
										<?php endif; ?>
										<?php if($relatedId > 0): ?><a class="btn btn-sm btn-outline-primary" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$relatedId?>" aria-label="View details"><i class="fa fa-eye"></i></a><?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<div class="mt-3 js-cashpickup-customer-pager"
			data-total-rows="<?=esc((string)$totalRows)?>"
			data-limit="<?=esc((string)$limit)?>"
			data-page="<?=esc((string)($pager->page_number ?? 1))?>"
			data-total-pages="<?=esc((string)$totalPages)?>"></div>
		<?php
	};
?>

<?php if($isAjax){ $renderTxResults(); return; } ?>

<style>
	.cashpickup-customer-tabs{
		gap:.35rem;
		border-bottom:1px solid var(--bs-border-color);
	}
	.cashpickup-customer-tabs .nav-link{
		border:0;
		border-radius:.7rem .7rem 0 0;
		color:var(--bs-secondary-color);
		font-weight:600;
		padding:.7rem 1rem;
		transition:background-color .15s ease,color .15s ease;
	}
	.cashpickup-customer-tabs .nav-link:hover{
		background:var(--bs-tertiary-bg);
		color:var(--bs-emphasis-color);
	}
	.cashpickup-customer-tabs .nav-link.active{
		background:rgba(var(--bs-primary-rgb),.1);
		box-shadow:inset 0 -2px var(--bs-primary);
		color:var(--bs-primary);
	}
	.cashpickup-activity-table{
		min-width:760px;
	}
	.cashpickup-statement-table{
		min-width:760px;
	}
	.cashpickup-activity-table thead th,
	.cashpickup-statement-table thead th{
		background:var(--bs-tertiary-bg);
		border-bottom:1px solid var(--bs-border-color);
		color:var(--bs-secondary-color);
		font-size:.72rem;
		font-weight:700;
		letter-spacing:.045em;
		padding:.85rem .75rem;
		text-transform:uppercase;
		vertical-align:middle;
		white-space:nowrap;
	}
	.cashpickup-activity-table tbody td,
	.cashpickup-statement-table tbody td{
		border-color:var(--bs-border-color);
		padding:.8rem .75rem;
		vertical-align:middle;
	}
	.cashpickup-activity-table tbody tr:hover,
	.cashpickup-statement-table tbody tr:hover{
		background:var(--bs-tertiary-bg);
	}
	.cashpickup-flow-amount{
		font-size:.8rem;
		font-weight:700;
		white-space:nowrap;
	}
	.cashpickup-statement-card{
		border:1px solid var(--bs-border-color);
		border-radius:1rem;
		overflow:hidden;
	}
	.cashpickup-statement-card>.card-header{
		background:linear-gradient(135deg,var(--bs-tertiary-bg),var(--bs-body-bg));
		border-bottom:1px solid var(--bs-border-color);
		padding:1rem 1.25rem;
	}
	.cashpickup-statement-card>.card-body{
		background:var(--bs-body-bg);
	}
	.cashpickup-activity-filter{
		border:1px solid var(--bs-border-color);
		border-radius:1rem;
	}
	.cashpickup-activity-filter .card-body{
		padding:1.1rem;
	}
	.cashpickup-activity-filter .form-label{
		color:var(--bs-secondary-color);
		font-size:.78rem;
		font-weight:700;
		margin-bottom:.4rem;
	}
	.cashpickup-activity-filter .form-control,
	.cashpickup-activity-filter .form-select{
		border-radius:.7rem;
		min-height:2.65rem;
	}
	.cashpickup-activity-filter .form-control:focus,
	.cashpickup-activity-filter .form-select:focus{
		box-shadow:0 0 0 .2rem rgba(var(--bs-primary-rgb),.14);
	}
	.cashpickup-activity-filter .btn{
		border-radius:.7rem;
		font-weight:600;
		min-height:2.65rem;
	}
	.cashpickup-activity-results{
		border:1px solid var(--bs-border-color);
		border-radius:1rem;
		overflow:hidden;
	}
	.cashpickup-statement-type{
		font-size:.7rem;
		font-weight:700;
		letter-spacing:.02em;
		white-space:nowrap;
	}
	.cashpickup-statement-description{
		min-width:12rem;
		max-width:24rem;
		overflow-wrap:anywhere;
	}
	.cashpickup-statement-table .cashpickup-statement-balance{
		background:rgba(var(--bs-primary-rgb),.035);
		font-weight:700;
		white-space:nowrap;
	}
	.cashpickup-statement-flow{
		min-width:9rem;
	}
	.cashpickup-statement-flow-line{
		align-items:center;
		display:flex;
		gap:.45rem;
		justify-content:space-between;
		white-space:nowrap;
	}
	.cashpickup-statement-flow-line+.cashpickup-statement-flow-line{
		border-top:1px solid var(--bs-border-color);
		margin-top:.35rem;
		padding-top:.35rem;
	}
	@media(max-width:767.98px){
		.cashpickup-statement-table{
			min-width:820px;
		}
		.cashpickup-customer-tabs{
			flex-wrap:nowrap;
			overflow-x:auto;
			scrollbar-width:thin;
		}
		.cashpickup-customer-tabs .nav-link{
			white-space:nowrap;
		}
		.cashpickup-statement-card>.card-header{
			align-items:flex-start!important;
			flex-direction:column;
		}
	}
	.cashpickup-metrics-details>summary{list-style:none;cursor:pointer}
	.cashpickup-metrics-details>summary::-webkit-details-marker{display:none}
	.cashpickup-metrics-chevron{transition:transform .15s ease}
	.cashpickup-metrics-details[open] .cashpickup-metrics-chevron{transform:rotate(180deg)}
	@media (prefers-reduced-motion:reduce){.cashpickup-metrics-chevron{transition:none}}
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
	.customer-profile-card{position:relative;overflow:hidden;border:1px solid var(--bs-border-color);border-radius:1rem;background:var(--bs-body-bg)}
	.customer-profile-card::before{content:"";position:absolute;inset:0 0 auto;height:5px;background:linear-gradient(90deg,var(--bs-primary),var(--bs-info))}
	.customer-profile-card .card-body{padding:1.25rem}
	.customer-profile-avatar{width:3.5rem;height:3.5rem;display:grid;place-items:center;flex:0 0 auto;border-radius:1rem;background:rgba(var(--bs-primary-rgb),.1);color:var(--bs-primary);font-size:1.35rem}
	.customer-profile-title{font-size:1.2rem;font-weight:700;letter-spacing:-.02em;overflow-wrap:anywhere}
	.customer-profile-detail{height:100%;padding:.85rem 1rem;border:1px solid var(--bs-border-color);border-radius:.75rem;background:var(--bs-tertiary-bg)}
	.customer-profile-detail-label{display:flex;align-items:center;gap:.45rem;margin-bottom:.3rem;color:var(--bs-secondary-color);font-size:.72rem;font-weight:700;letter-spacing:.045em;text-transform:uppercase}
	.customer-profile-detail-value{font-weight:600;overflow-wrap:anywhere}
	@media(max-width:575.98px){.customer-profile-card .card-body{padding:1rem}.customer-profile-avatar{width:3rem;height:3rem;border-radius:.85rem}}
</style>

<div class="d-flex align-items-center justify-content-between mb-3">
	<div>
		<h4 class="mb-0">Customer Ledger</h4>
		<small class="text-muted">Profile and transactions</small>
	</div>
	<div class="d-flex flex-wrap gap-2">
		<a class="btn btn-outline-secondary" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/customers">Back to Customers</a>
		<?php if($canAssignPayout && $customerApproved): ?>
			<a class="btn btn-primary" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/customer/<?=esc((string)$customerId)?>#withdraw-section"><i class="fa fa-user-plus me-1"></i> Assign payout</a>
		<?php endif; ?>
		<?php if(user_can('add_cashpickup')): ?>
			<?php if($customerApproved): ?>
				<a class="btn btn-outline-success" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/add?customer_id=<?=esc((string)$customerId)?>">New Receive</a>
			<?php else: ?>
				<button type="button" class="btn btn-outline-success" disabled title="Customer registration must be approved by admin or manager first.">New Receive</button>
			<?php endif; ?>
		<?php endif; ?>
		<?php if($canCreateCreditPayout): ?>
			<?php if($customerApproved): ?>
				<a class="btn btn-outline-primary" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/credit-payout/<?=esc((string)$customerId)?>">Credit Payout</a>
			<?php else: ?>
				<button type="button" class="btn btn-outline-primary" disabled title="Customer registration must be approved by admin or manager first.">Credit Payout</button>
			<?php endif; ?>
		<?php endif; ?>
		<?php if($payoutCandidate > 0): ?>
			<a class="btn btn-outline-warning" href="<?=$payoutCandidateIsCredit ? ROOT.'/'.$admin_route.'/'.$plugin_route.'/payout/'.esc((string)$payoutCandidate) : ROOT.'/'.$admin_route.'/'.$plugin_route.'/customer/'.esc((string)$customerId).'#withdraw-section'?>">New Payout</a>
		<?php endif; ?>
		<a class="btn btn-outline-success" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/export?<?=http_build_query($statementExportParamsXlsx)?>">Excel</a>
		<a class="btn btn-outline-danger" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/export?<?=http_build_query($statementExportParamsPdf)?>">PDF</a>
	</div>
</div>

<?php if(!$customerApproved): ?>
	<div class="alert alert-warning py-2">
		This customer is still awaiting admin or manager approval. New Receive and Credit Payout stay disabled until approval is completed.
	</div>
<?php endif; ?>

<details class="card border-0 shadow-sm mb-3 cashpickup-metrics-details">
	<summary class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
		<span>
			<span class="d-block small text-muted">Account balance</span>
			<span class="d-block fw-bold fs-5 <?=((float)$metrics['balance'] < 0) ? 'text-danger' : ''?>">GH&#8373; <?=number_format((float)$metrics['balance'], 2)?></span>
			<span class="d-block small text-muted">Credit payouts are deducted from the general cashpickup balance.</span>
		</span>
		<span class="small fw-semibold text-primary">Metrics <i class="fa fa-chevron-down ms-1 cashpickup-metrics-chevron" aria-hidden="true"></i></span>
	</summary>
	<div class="card-body">
<div class="row g-2 mb-3">
	<div class="col-12 col-md-4 col-lg">
		<div class="border rounded p-3 h-100">
			<div class="d-flex align-items-center justify-content-between">
				<div class="text-muted">Total Transactions</div>
				<i class="fa fa-receipt text-primary"></i>
			</div>
			<div class="fw-bold"><?=number_format((float)$metrics['tx_count'])?></div>
			<div class="small text-muted"><?=esc($metrics['last_tx'] !== '' ? ('Last: ' . $metrics['last_tx']) : '')?></div>
		</div>
	</div>
	<div class="col-12 col-md-4 col-lg">
		<div class="border rounded p-3 h-100">
			<div class="d-flex align-items-center justify-content-between">
				<div class="text-muted">Deposits</div>
				<i class="fa fa-hand-holding-dollar text-success"></i>
			</div>
			<div class="fw-bold">GH&#8373; <?=number_format((float)$metrics['total_received'], 2)?></div>
		</div>
	</div>
	<div class="col-12 col-md-4 col-lg">
		<div class="border rounded p-3 h-100">
			<div class="d-flex align-items-center justify-content-between">
				<div class="text-muted">Payouts</div>
				<i class="fa fa-coins text-warning"></i>
			</div>
			<div class="fw-bold">GH&#8373; <?=number_format((float)$metrics['total_paid_out'], 2)?></div>
		</div>
	</div>
	<div class="col-12 col-md-4 col-lg">
		<div class="border rounded p-3 h-100">
			<div class="d-flex align-items-center justify-content-between">
				<div class="text-muted">Withdrawals</div>
				<i class="fa fa-money-bill-transfer text-danger"></i>
			</div>
			<div class="fw-bold">GH&#8373; <?=number_format((float)$metrics['total_withdrawals'], 2)?></div>
		</div>
	</div>
</div>

<div class="row g-2 mb-0">
	<div class="col-12 col-md-4">
		<div class="border rounded p-3 h-100">
			<div class="d-flex align-items-center justify-content-between">
				<div class="text-muted">Total Credit Given</div>
				<i class="fa fa-arrow-up-right-dots text-primary"></i>
			</div>
			<div class="fw-bold">GH&#8373; <?=number_format((float)($creditMetrics['total_credit_given'] ?? 0), 2)?></div>
		</div>
	</div>
	<div class="col-12 col-md-4">
		<div class="border rounded p-3 h-100">
			<div class="d-flex align-items-center justify-content-between">
				<div class="text-muted">Total Credit Paid</div>
				<i class="fa fa-rotate-left text-success"></i>
			</div>
			<div class="fw-bold">GH&#8373; <?=number_format((float)($creditMetrics['total_credit_paid'] ?? 0), 2)?></div>
		</div>
	</div>
	<div class="col-12 col-md-4">
		<div class="border rounded p-3 h-100">
			<div class="d-flex align-items-center justify-content-between">
				<div class="text-muted">Amount in Credit</div>
				<i class="fa fa-scale-balanced text-warning"></i>
			</div>
			<div class="fw-bold">GH&#8373; <?=number_format((float)($creditMetrics['total_amount_in_credit'] ?? 0), 2)?></div>
		</div>
	</div>
</div>
</div>
</details>

<?php
	$profileName = trim((string)($customer->full_name ?? ''));
	$profilePhone = trim((string)($customer->phone_number ?? ''));
	$profileApprovalStatus = strtolower(trim((string)($customer->approval_status ?? 'approved')));
	if ($profileApprovalStatus === '') {
		$profileApprovalStatus = 'approved';
	}
	$profileStatusClass = match ($profileApprovalStatus) {
		'approved' => 'text-bg-success',
		'denied' => 'text-bg-danger',
		default => 'text-bg-warning',
	};
?>
<section class="card customer-profile-card shadow-sm mb-3" aria-labelledby="customerProfileHeading">
	<div class="card-body">
		<div class="d-flex flex-column flex-sm-row align-items-sm-center gap-3">
			<div class="customer-profile-avatar" aria-hidden="true"><i class="fa-solid fa-user"></i></div>
			<div class="flex-grow-1 min-w-0">
				<div class="small text-body-secondary fw-semibold text-uppercase">Customer profile</div>
				<div class="d-flex flex-wrap align-items-center gap-2">
					<h5 class="customer-profile-title mb-0" id="customerProfileHeading"><?=esc($profileName !== '' ? $profileName : 'Unnamed customer')?></h5>
					<span class="badge rounded-pill <?=$profileStatusClass?>"><?=esc(ucfirst($profileApprovalStatus))?></span>
				</div>
				<div class="text-body-secondary mt-1">
					<i class="fa-solid fa-phone me-1" aria-hidden="true"></i>
					<?=esc($profilePhone !== '' ? $profilePhone : 'Phone number not provided')?>
					<span class="mx-1" aria-hidden="true">·</span>
					<span>Customer #<?=esc((string)$customerId)?></span>
				</div>
			</div>
			<?php if(user_can('edit_cashpickup')): ?>
				<button class="btn btn-outline-primary flex-shrink-0" type="button" data-bs-toggle="modal" data-bs-target="#customerEditModal">
					<i class="fa-solid fa-pen me-1" aria-hidden="true"></i>Edit profile
				</button>
			<?php endif; ?>
		</div>

		<div class="row g-2 mt-2">
			<div class="col-12 col-sm-6 col-xl-3">
				<div class="customer-profile-detail">
					<div class="customer-profile-detail-label"><i class="fa-solid fa-id-card" aria-hidden="true"></i>ID type</div>
					<div class="customer-profile-detail-value"><?=esc(trim((string)($customer->id_type ?? '')) ?: 'Not provided')?></div>
				</div>
			</div>
			<div class="col-12 col-sm-6 col-xl-3">
				<div class="customer-profile-detail">
					<div class="customer-profile-detail-label"><i class="fa-solid fa-hashtag" aria-hidden="true"></i>ID number</div>
					<div class="customer-profile-detail-value"><?=esc(trim((string)($customer->id_number ?? '')) ?: 'Not provided')?></div>
				</div>
			</div>
			<div class="col-12 col-xl-6">
				<div class="customer-profile-detail">
					<div class="customer-profile-detail-label"><i class="fa-solid fa-location-dot" aria-hidden="true"></i>Location / address</div>
					<div class="customer-profile-detail-value"><?=esc(trim((string)($customer->location ?? '')) ?: 'Not provided')?></div>
				</div>
			</div>
		</div>
	</div>
</section>

<?php if(user_can('edit_cashpickup')): ?>
	<div class="modal fade" id="customerEditModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-lg modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Edit Customer</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<form method="post" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/customer/<?=esc((string)$customerId)?>">
					<?=csrf()?>
					<input type="hidden" name="customer_id" value="<?=esc((string)$customerId)?>">
					<div class="modal-body">
						<div class="row g-3">
							<div class="col-md-6">
								<label class="form-label">Full name</label>
								<input name="full_name" type="text" class="form-control" value="<?=esc((string)($customer->full_name ?? ''))?>">
							</div>
							<div class="col-md-6">
								<label class="form-label">Phone number</label>
								<input name="phone_number" type="text" class="form-control" value="<?=esc((string)($customer->phone_number ?? ''))?>">
							</div>
							<div class="col-md-4">
								<label class="form-label">ID type</label>
								<select name="id_type" class="form-select">
									<option value="">---Select---</option>
									<option <?=((string)($customer->id_type ?? '') === 'Ghana Card') ? 'selected' : ''?> value="Ghana Card">Ghana Card</option>
									<option <?=((string)($customer->id_type ?? '') === 'NHIS') ? 'selected' : ''?> value="NHIS">NHIS</option>
									<option <?=((string)($customer->id_type ?? '') === 'Passport') ? 'selected' : ''?> value="Passport">Passport</option>
								</select>
							</div>
							<div class="col-md-4">
								<label class="form-label">ID number</label>
								<input name="id_number" type="text" class="form-control" value="<?=esc((string)($customer->id_number ?? ''))?>">
							</div>
							<div class="col-md-4">
								<label class="form-label">Location / Address</label>
								<input name="location" type="text" class="form-control" value="<?=esc((string)($customer->location ?? ''))?>">
							</div>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
						<button type="submit" class="btn btn-primary">Save</button>
					</div>
				</form>
			</div>
		</div>
	</div>
<?php endif; ?>

<ul class="nav nav-tabs cashpickup-customer-tabs mb-3" id="cashpickupCustomerTabs" role="tablist">
	<li class="nav-item" role="presentation">
		<button class="nav-link active" id="customer-transactions-tab" data-bs-toggle="tab" data-bs-target="#customer-transactions-pane" type="button" role="tab" aria-controls="customer-transactions-pane" aria-selected="true">Transactions</button>
	</li>
	<li class="nav-item" role="presentation">
		<button class="nav-link" id="customer-statement-tab" data-bs-toggle="tab" data-bs-target="#customer-statement-pane" type="button" role="tab" aria-controls="customer-statement-pane" aria-selected="false">Statement</button>
	</li>
	<?php if($customerApproved && ($canWithdraw || $canAssignPayout)): ?>
	<li class="nav-item" role="presentation">
		<button class="nav-link" id="withdraw-tab" data-bs-toggle="tab" data-bs-target="#withdraw-section" type="button" role="tab" aria-controls="withdraw-section" aria-selected="false"><?=$canWithdraw ? 'Withdraw' : 'Payout assignments'?></button>
	</li>
	<?php endif; ?>
	<?php if($customerApproved): ?>
	<li class="nav-item" role="presentation">
		<button class="nav-link" id="transfer-tab" data-bs-toggle="tab" data-bs-target="#transfer-section" type="button" role="tab" aria-controls="transfer-section" aria-selected="false">Transfer</button>
	</li>
	<?php endif; ?>
</ul>

<div class="tab-content" id="cashpickupCustomerTabsContent">
	<div class="tab-pane fade show active" id="customer-transactions-pane" role="tabpanel" aria-labelledby="customer-transactions-tab" tabindex="0">
		<div class="card border-0 shadow-sm mb-3 cashpickup-activity-filter">
			<div class="card-body">
				<form method="get" class="row g-3 align-items-end" id="cashpickupCustomerTxFilterForm">
					<input type="hidden" name="customer_id" value="<?=esc((string)$customerId)?>">
					<div class="col-12 col-md-4">
						<label class="form-label">Search</label>
						<input type="text" name="find" value="<?=esc($find)?>" class="form-control" placeholder="Tracking, source, method" id="cashpickupCustomerFind">
					</div>

					<script>
						(function(){
							function activateTabFromHash(){
								try{
									var hash = (window.location.hash || '').replace('#','');
									if(!hash) return;
									var mapping = {
										'withdraw-section': 'withdraw-tab',
										'transfer-section': 'transfer-tab',
										'customer-statement-pane': 'customer-statement-tab',
										'customer-transactions-pane': 'customer-transactions-tab'
									};
									var tabId = mapping[hash];
									if(!tabId) return;
									var btn = document.getElementById(tabId);
									if(!btn) return;
									if(typeof bootstrap !== 'undefined' && bootstrap.Tab){
										var t = new bootstrap.Tab(btn);
										t.show();
									} else {
										btn.click();
									}
								}catch(e){/* ignore */}
							}
							document.addEventListener('DOMContentLoaded', function(){ activateTabFromHash(); });
							window.addEventListener('hashchange', activateTabFromHash);
						})();
					</script>
					<div class="col-6 col-md-2">
						<label class="form-label">Side</label>
						<select class="form-select" name="side" id="cashpickupCustomerSide">
							<option value="" <?=($sideFilter==='')?'selected':''?>>All</option>
							<option value="receive" <?=($sideFilter==='receive')?'selected':''?>>Receive</option>
							<option value="pay" <?=($sideFilter==='pay')?'selected':''?>>Pay</option>
						</select>
					</div>
					<div class="col-6 col-md-2">
						<label class="form-label">From</label>
						<input type="date" name="date_from" value="<?=esc($dateFrom)?>" class="form-control" id="cashpickupCustomerDateFrom">
					</div>
					<div class="col-6 col-md-2">
						<label class="form-label">To</label>
						<input type="date" name="date_to" value="<?=esc($dateTo)?>" class="form-control" id="cashpickupCustomerDateTo">
					</div>
					<div class="col-6 col-md-2">
						<label class="form-label">Per page</label>
						<select name="limit" class="form-select" id="cashpickupCustomerLimit">
							<?php foreach([10,25,30,50,100,200] as $l): ?>
								<option value="<?=$l?>" <?=$limit===$l?'selected':''?>><?=$l?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-12 col-md-2">
						<button class="btn btn-primary w-100" type="submit"><i class="fa fa-filter"></i> Apply</button>
					</div>
				</form>
			</div>
		</div>

		<div id="cashpickupCustomerTxContainer" data-ajax-endpoint="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/customer_tx_ajax" data-customer-id="<?=esc((string)$customerId)?>">
			<?php $renderTxResults(); ?>
		</div>
		<?php if($canApproveDeny): ?>
			<div class="modal fade" id="cashpickupRejectModal" tabindex="-1" aria-labelledby="cashpickupRejectModalLabel" aria-hidden="true">
				<div class="modal-dialog">
					<div class="modal-content">
						<form method="post" id="cashpickupRejectForm">
							<div class="modal-header">
								<h5 class="modal-title" id="cashpickupRejectModalLabel">Reject pending cashpickup</h5>
								<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
							</div>
							<div class="modal-body">
								<?=$csrfField?>
								<input type="hidden" name="customer_id" value="<?=esc((string)$customerId)?>">
								<label for="cashpickupRejectionReason" class="form-label">Rejection reason</label>
								<textarea class="form-control" id="cashpickupRejectionReason" name="denial_reason" rows="4" required></textarea>
							</div>
							<div class="modal-footer">
								<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
								<button type="submit" class="btn btn-danger"><i class="fa fa-times me-1"></i> Reject transaction</button>
							</div>
						</form>
					</div>
				</div>
			</div>
			<script>
				(function(){
					var modal = document.getElementById('cashpickupRejectModal');
					var form = document.getElementById('cashpickupRejectForm');
					var reason = document.getElementById('cashpickupRejectionReason');
					if (!modal || !form || !reason) return;
					document.addEventListener('click', function(event){
						var trigger = event.target.closest('[data-cashpickup-reject]');
						if (!trigger) return;
						form.action = trigger.getAttribute('data-deny-url') || '';
						reason.value = '';
						if (window.bootstrap && bootstrap.Modal) {
							bootstrap.Modal.getOrCreateInstance(modal).show();
						}
					});
					modal.addEventListener('shown.bs.modal', function(){ reason.focus(); });
				})();
			</script>
		<?php endif; ?>
		<?php if(user_can('is_admin')): ?>
			<?php require plugin_path('views/admin/cancel-modal.php'); ?>
		<?php endif; ?>
	</div>

	<div class="tab-pane fade" id="customer-statement-pane" role="tabpanel" aria-labelledby="customer-statement-tab" tabindex="0">
		<div class="card border-0 shadow-sm cashpickup-statement-card">
			<div class="card-header d-flex align-items-center justify-content-between">
				<div>
					<strong>Ledger Statement</strong>
					<div class="small text-muted">Customer-level activity, including credit issuance and repayment, with the cash balance after each entry.</div>
				</div>
				<div class="small text-muted">Rows: <?=number_format((int)$statementTotalRows)?></div>
			</div>
			<div class="card-body p-0">
				<div class="small text-muted px-3 pt-3">Credit and repayment activity appears in Cash Flow and does not change the customer cash balance.</div>
				<div class="table-responsive">
					<table class="table table-striped align-middle mb-0 cashpickup-statement-table">
						<thead>
							<tr>
								<th>Transaction / Reference</th>
								<th>Activity / Description</th>
								<th class="text-end d-md-none">Received</th>
								<th class="text-end d-md-none">Payout</th>
								<th class="text-end d-md-none">Credit</th>
								<th class="text-end d-md-none">Repayment</th>
								<th class="text-end d-none d-md-table-cell">Cash flow</th>
								<th class="text-end">Accumulated Balance</th>
							</tr>
						</thead>
						<tbody>
							<?php if (empty($statementRows)): ?>
								<tr>
									<td class="text-center text-muted py-5 d-md-none" colspan="7">No customer ledger statement rows found.</td>
									<td class="text-center text-muted py-5 d-none d-md-table-cell" colspan="4">No customer ledger statement rows found.</td>
								</tr>
							<?php else: ?>
								<?php foreach ($statementRows as $statementRow): ?>
									<?php
										$statementDescription = trim((string)($statementRow->sender_details ?? ''));
										$statementPhone = trim((string)($statementRow->phone_number ?? ''));
										$statementSource = trim((string)($statementRow->source ?? ''));
										$statementMethod = trim((string)($statementRow->payment_method ?? ''));
										$statementTracking = trim((string)($statementRow->tracking_id ?? ''));
										$statementTransferFrom = trim((string)($statementRow->transfer_from_customer_name ?? ''));
										$statementTransferTo = trim((string)($statementRow->transfer_to_customer_name ?? ''));
										$statementBalance = (float)($statementRow->balance_amount ?? 0);
										$statementType = ucwords(str_replace('_', ' ', (string)($statementRow->entry_type ?? '')));
										$statementReceived = (float)($statementRow->received_amount ?? 0);
										$statementPayout = (float)($statementRow->payout_amount ?? 0);
										$statementCredit = (float)($statementRow->credit_amount ?? 0);
										$statementRepayment = (float)($statementRow->credit_repayment_amount ?? 0);
									?>
									<tr>
										<td class="text-nowrap">
											<div class="small text-muted"><?=esc((string)get_date_time($statementRow->date_created ?? ''))?></div>
											<div class="fw-semibold">#<?=esc((string)($statementRow->id ?? ''))?></div>
											<?php if ($statementTracking !== ''): ?>
												<div class="small text-muted text-break">Ref: <?=esc($statementTracking)?></div>
											<?php endif; ?>
										</td>
										<td class="cashpickup-statement-description">
											<?php if($statementType !== ''): ?><span class="badge rounded-pill bg-primary-subtle text-primary-emphasis cashpickup-statement-type mb-1"><?=esc($statementType)?></span><?php endif; ?>
											<div class="fw-semibold"><?=esc($statementDescription !== '' ? $statementDescription : '---')?></div>
											<?php if ($statementPhone !== ''): ?>
												<div class="small text-muted">Phone: <?=esc($statementPhone)?></div>
											<?php endif; ?>
											<?php if ($statementTransferFrom !== '' || $statementTransferTo !== ''): ?>
												<div class="small text-muted">Transfer: <?=esc($statementTransferFrom !== '' ? $statementTransferFrom : 'Unknown source')?> &rarr; <?=esc($statementTransferTo !== '' ? $statementTransferTo : 'Unknown destination')?></div>
											<?php endif; ?>
											<?php if ($statementSource !== ''): ?>
												<div class="small text-muted">Source: <?=esc($statementSource)?></div>
											<?php endif; ?>
											<?php if ($statementMethod !== ''): ?>
												<div class="small text-muted">Method: <?=esc($statementMethod)?></div>
											<?php endif; ?>
										</td>
										<td class="text-end text-success text-nowrap d-md-none"><?=number_format($statementReceived, 2)?></td>
										<td class="text-end text-danger text-nowrap d-md-none"><?=number_format($statementPayout, 2)?></td>
										<td class="text-end text-nowrap d-md-none"><?=number_format($statementCredit, 2)?></td>
										<td class="text-end text-nowrap d-md-none"><?=number_format($statementRepayment, 2)?></td>
										<td class="text-end d-none d-md-table-cell">
											<div class="cashpickup-statement-flow">
												<?php if($statementReceived > 0): ?><div class="cashpickup-statement-flow-line text-success"><span><i class="fa-solid fa-arrow-down" aria-hidden="true"></i> Received</span><strong><?=number_format($statementReceived, 2)?></strong></div><?php endif; ?>
												<?php if($statementPayout > 0): ?><div class="cashpickup-statement-flow-line text-danger"><span><i class="fa-solid fa-arrow-up" aria-hidden="true"></i> Payout</span><strong><?=number_format($statementPayout, 2)?></strong></div><?php endif; ?>
												<?php if($statementCredit > 0): ?><div class="cashpickup-statement-flow-line text-primary"><span><i class="fa-solid fa-circle-plus" aria-hidden="true"></i> Credit</span><strong><?=number_format($statementCredit, 2)?></strong></div><?php endif; ?>
												<?php if($statementRepayment > 0): ?><div class="cashpickup-statement-flow-line text-success"><span><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Repayment</span><strong><?=number_format($statementRepayment, 2)?></strong></div><?php endif; ?>
												<?php if($statementReceived <= 0 && $statementPayout <= 0 && $statementCredit <= 0 && $statementRepayment <= 0): ?><span class="text-body-secondary">—</span><?php endif; ?>
											</div>
										</td>
										<td class="text-end cashpickup-statement-balance <?=($statementBalance < 0) ? 'text-danger' : ''?>"><?=number_format($statementBalance, 2)?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
					</div>
				</div>
			</div>
		</div>

		<?php if($customerApproved): ?>
		<?php if($canWithdraw || $canAssignPayout): ?>
		<!-- ===== Cumulative Withdrawal ===== -->
		<div class="tab-pane fade" id="withdraw-section" role="tabpanel" aria-labelledby="withdraw-tab" tabindex="0">
			<div class="card border-0 shadow-sm">
				<div class="card-header d-flex align-items-center justify-content-between">
					<div>
						<strong><?=$canWithdraw ? 'Withdraw from Cumulative Balance' : 'Payout assignments'?></strong>
						<div class="small text-muted"><?=$canWithdraw ? "Withdraw against the customer's total balance. The amount is allocated FIFO across their unpaid receive records." : "Assign customer balance to eligible agents for payout."?></div>
					</div>
					<?php if($canWithdraw): ?>
						<div class="small">Available to withdraw: <span class="fw-bold <?=((float)$withdrawAvailable < 0) ? 'text-danger' : 'text-success'?>">GH&#8373; <?=number_format((float)$withdrawAvailable, 2)?></span></div>
					<?php endif; ?>
				</div>
				<div class="card-body">
					<?php if($canAssignPayout): ?>
						<div class="card border mb-4">
							<div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
								<strong><i class="fa fa-user-check me-1"></i> Payout assignments</strong>
								<span class="small text-muted">Unassigned balance: GH&#8373; <?=number_format((float)$unassignedBalance, 2)?></span>
							</div>
							<div class="card-body">
								<form method="post" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/customer-assignment/<?=esc((string)$customerId)?>" class="row g-2 align-items-end mb-3">
									<?=csrf()?>
									<input type="hidden" name="customer_id" value="<?=esc((string)$customerId)?>">
									<div class="col-12 col-md-4">
										<label class="form-label">Assign to</label>
										<select name="assigned_user_id" class="form-select" required>
											<option value="">Select an eligible user</option>
											<?php foreach($assignmentUsers as $assignmentUser): ?>
												<option value="<?=esc((string)($assignmentUser->id ?? 0))?>"><?=esc(trim((string)($assignmentUser->first_name ?? '') . ' ' . (string)($assignmentUser->last_name ?? '')))?></option>
											<?php endforeach; ?>
										</select>
									</div>
									<div class="col-12 col-md-3">
										<label class="form-label">Amount (GH&#8373;)</label>
										<input type="number" name="amount" class="form-control" min="0.01" step="0.01" max="<?=esc(number_format((float)$unassignedBalance, 2, '.', ''))?>" required>
									</div>
									<div class="col-12 col-md-2">
										<label class="form-label">Payout charge (GH&#8373;)</label>
										<input type="number" name="pickup_charge" class="form-control" min="0" step="0.01" value="0.00" required>
									</div>
									<div class="col-12 col-md-3">
										<button type="submit" class="btn btn-primary w-100" <?=empty($assignmentUsers) || $unassignedBalance <= 0.00001 ? 'disabled' : ''?>>
											<i class="fa fa-user-plus me-1"></i> Assign balance
										</button>
									</div>
								</form>
								<div class="table-responsive">
									<table class="table table-sm align-middle mb-0">
										<thead><tr><th>Assigned user</th><th>Assigned</th><th>Payout charge</th><th>Used</th><th>Remaining</th><th>Status</th><th class="text-end">Action</th></tr></thead>
										<tbody>
											<?php
												$visibleAssignments = array_filter($customerAssignments, static function($assignment) use ($canAssignPayout, $user_id) {
													return $canAssignPayout || (int)($assignment->assigned_user_id ?? 0) === $user_id;
												});
											?>
											<?php if(empty($visibleAssignments)): ?>
												<tr><td colspan="7" class="text-center text-muted">No payout assignments yet.</td></tr>
											<?php else: ?>
												<?php foreach($visibleAssignments as $assignment): ?>
													<?php
														$assignmentStatus = strtolower(trim((string)($assignment->status ?? 'active')));
														$cancelledByName = trim((string)($assignment->cancelled_first_name ?? '') . ' ' . (string)($assignment->cancelled_last_name ?? ''));
														$cancelledById = (int)($assignment->cancelled_by ?? 0);
														if ($cancelledByName === '' && $cancelledById > 0) {
															$cancelledByName = 'User #' . $cancelledById;
														}
														$assignedByName = trim((string)($assignment->assigner_first_name ?? '') . ' ' . (string)($assignment->assigner_last_name ?? ''));
														$assignedById = (int)($assignment->assigned_by ?? 0);
														if ($assignedByName === '' && $assignedById > 0) {
															$assignedByName = 'User #' . $assignedById;
														}
														$assignedAt = get_date_time($assignment->date_created ?? '');
													?>
													<tr>
														<td>
															<div><?=esc(trim((string)($assignment->assigned_first_name ?? '') . ' ' . (string)($assignment->assigned_last_name ?? '')))?></div>
															<div class="small text-body-secondary">Assigned by: <?=esc($assignedByName !== '' ? $assignedByName : 'Unknown user')?></div>
															<div class="small text-body-secondary"><?=esc($assignedAt !== '' ? $assignedAt : 'Assignment time not recorded')?></div>
														</td>
														<td>GH&#8373; <?=number_format((float)($assignment->amount ?? 0), 2)?></td>
														<td>GH&#8373; <?=number_format((float)($assignment->pickup_charge ?? 0), 2)?></td>
														<td>GH&#8373; <?=number_format((float)($assignment->used_amount ?? 0), 2)?></td>
														<td class="fw-semibold">GH&#8373; <?=number_format((float)($assignment->remaining_amount ?? 0), 2)?></td>
														<td>
															<span class="badge <?=$assignmentStatus === 'active' ? 'bg-success' : 'bg-secondary'?>"><?=esc(ucfirst($assignmentStatus))?></span>
															<?php if($assignmentStatus === 'cancelled'): ?>
																<div class="small text-body-secondary mt-1">Cancelled by: <?=esc($cancelledByName !== '' ? $cancelledByName : 'Unknown user')?></div>
																<?php $cancelledAt = get_date_time($assignment->date_cancelled ?? ''); ?>
																<div class="small text-body-secondary"><?=esc($cancelledAt !== '' ? $cancelledAt : 'Date/time not recorded')?></div>
															<?php endif; ?>
														</td>
														<td class="text-end">
															<?php if(user_can('is_admin') && $assignmentStatus === 'active' && (float)($assignment->remaining_amount ?? 0) > 0.00001): ?>
																<form method="post" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/customer-assignment-cancel/<?=esc((string)($assignment->id ?? 0))?>" class="d-inline js-cashpickup-assignment-cancel" data-cancel-confirmation="Cancel the unspent part of this payout assignment?">
																	<?=csrf()?>
																	<input type="hidden" name="customer_id" value="<?=esc((string)$customerId)?>">
																	<button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa fa-ban me-1"></i> Cancel</button>
																</form>
															<?php endif; ?>
														</td>
													</tr>
												<?php endforeach; ?>
											<?php endif; ?>
										</tbody>
									</table>
								</div>
							</div>
						</div>
					<?php elseif($canWithdraw && $viewerAssignedBalance > 0.00001): ?>
						<div class="alert alert-info d-flex justify-content-between align-items-center">
							<span><i class="fa fa-user-check me-1"></i> Your assigned payout balance</span>
							<strong>GH&#8373; <?=number_format((float)$withdrawAvailable, 2)?></strong>
						</div>
					<?php endif; ?>
					<?php if($canWithdraw): ?>
					<?php if((float)$withdrawAvailable <= 0.00001): ?>
						<div class="alert alert-warning mb-0"><?=$isOperationsManager ? 'This customer has no available balance to withdraw.' : 'No payout balance is assigned to you for this customer.'?></div>
					<?php else: ?>
					<form method="post" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/customer_withdraw/<?=esc((string)$customerId)?>" id="cashpickupWithdrawForm">
						<?=csrf()?>
						<input type="hidden" name="customer_id" value="<?=esc((string)$customerId)?>">
						<input type="hidden" name="denos_json" id="withdrawDenosJson" value="[]">
						<input type="hidden" name="cfa_denos_json" id="withdrawCfaDenosJson" value="[]">
						<input type="hidden" name="cfa_amount" id="withdrawCfaAmount" value="0">
						<div class="row g-3">
							<div class="col-12 col-md-4">
								<label class="form-label">Amount (GH&#8373;) <span class="text-danger">*</span></label>
								<input type="number" step="0.01" min="0.01" max="<?=esc(number_format((float)$withdrawAvailable, 2, '.', ''))?>" name="amount" class="form-control" id="withdrawAmount" required>
								<small class="text-muted">Max: GH&#8373; <?=number_format((float)$withdrawAvailable, 2)?></small>
							</div>
							<div class="col-12 col-md-4">
								<label class="form-label">Pickup Charge (GH&#8373;)</label>
								<input type="number" step="0.01" min="0" name="pickup_charge" value="0" class="form-control" id="withdrawCharge" <?=$isOperationsManager ? '' : 'readonly'?>>
								<?php if(!$isOperationsManager): ?><small class="text-muted">Calculated from the payout charge assigned to you.</small><?php endif; ?>
							</div>
							<div class="col-12 col-md-4">
								<label class="form-label">Payment Method <span class="text-danger">*</span></label>
								<select name="payment_method" class="form-select" id="withdrawPaymentMethod" required>
									<option value="">---Select---</option>
									<option value="cash_ghs">Cash GHS</option>
									<option value="cash_cfa">Cash CFA</option>
									<option value="transfer_telcos">Transfer Telcos</option>
									<option value="transfer_bank">Transfer Bank</option>
								</select>
							</div>

							<!-- Transfer Telcos: telco channel -->
							<div class="col-12 col-md-4 withdraw-method-block" data-method="transfer_telcos" style="display:none;">
								<label class="form-label">Telco <span class="text-danger">*</span></label>
								<select name="payout_channel" class="form-select" id="withdrawPayoutChannel">
									<option value="">---Select---</option>
									<option value="mtn">MTN</option>
									<option value="telecel">Telecel</option>
									<option value="at">AT</option>
								</select>
							</div>

							<!-- Transfer Bank: bank select -->
							<div class="col-12 col-md-4 withdraw-method-block" data-method="transfer_bank" style="display:none;">
								<label class="form-label">Bank <span class="text-danger">*</span></label>
								<select name="account_paid_to" class="form-select" id="withdrawBank">
									<option value="">---Select---</option>
									<?php foreach($withdrawBanks as $b): ?>
										<option value="<?=esc($b)?>"><?=esc($b)?></option>
									<?php endforeach; ?>
								</select>
							</div>

							<!-- Cash GHS: calculator button -->
							<div class="col-12 col-md-4 withdraw-method-block" data-method="cash_ghs" style="display:none;">
								<label class="form-label">GHS Cash Denominations</label>
								<button type="button" class="btn btn-outline-warning w-100" data-bs-toggle="modal" data-bs-target="#withdrawDenosGhsModal"><i class="fa fa-calculator"></i> Cash GHS Calculator</button>
								<div class="small text-muted mt-1" id="withdrawGhsDenosSummary">No denominations entered.</div>
							</div>

							<!-- Cash CFA: calculator + currency converter -->
							<div class="col-12 col-md-4 withdraw-method-block" data-method="cash_cfa" style="display:none;">
								<label class="form-label">CFA Amount</label>
								<div class="input-group">
									<input type="number" step="1" min="0" class="form-control" id="withdrawCfaAmountDisplay" readonly placeholder="0">
									<button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#withdrawDenosCfaModal"><i class="fa fa-calculator"></i></button>
								</div>
								<small class="text-muted">Selling rate: CFA 1,000 = GH&#8373; <?=number_format((float)$cfaRates['selling_rate'], 2)?></small>
								<div class="small text-muted mt-1" id="withdrawCfaDenosSummary">No CFA denominations entered.</div>
							</div>

							<div class="col-12 col-md-4">
								<label class="form-label">Customer Confirmation Name/ID <span class="text-danger">*</span></label>
								<input type="text" name="confirmed_by" class="form-control" value="<?=esc((string)($customer->full_name ?? ''))?>" required>
							</div>
							<div class="col-12">
								<label class="form-label">Notes</label>
								<textarea name="notes" class="form-control" rows="2"></textarea>
							</div>
						</div>
						<div class="mt-3">
							<button type="submit" class="btn btn-warning"><i class="fa fa-hand-holding-dollar"></i> Withdraw</button>
							<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/customer/<?=esc((string)$customerId)?>" class="btn btn-secondary">Cancel</a>
						</div>
					</form>

					<!-- GHS denominations modal -->
					<div class="modal fade" id="withdrawDenosGhsModal" tabindex="-1" aria-hidden="true">
						<div class="modal-dialog modal-lg">
							<div class="modal-content">
								<div class="modal-header"><h5 class="modal-title">Cash GHS Notes</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
								<div class="modal-body table-responsive">
									<div class="mb-2 small">Target: GH&#8373; <span id="wdGhsTarget" class="fw-bold">0.00</span></div>
									<div id="wdGhsMismatch" class="alert alert-danger py-2 mb-2 d-none" role="alert" aria-live="polite"></div>
									<table class="table table-sm">
										<thead><tr><th>NOTE</th><th>COUNT</th><th>VOLUME</th></tr></thead>
										<tbody>
											<?php foreach([200,100,50,20,10,5,2,1,0.5,0.2,0.1] as $d): ?>
											<tr data-denomination="<?=$d?>"><td>GH&#8373; <?=esc($d)?></td>
												<td><input type="number" min="0" step="1" class="form-control wd-ghs-count" placeholder="0"></td>
												<td><input type="text" class="form-control wd-ghs-volume" readonly placeholder="0.00"></td>
											</tr>
											<?php endforeach; ?>
										</tbody>
										<tfoot><tr><th>TOTAL</th><th><input type="text" class="form-control" id="wdGhsTotalCount" readonly></th><th><input type="text" class="form-control" id="wdGhsTotalVolume" readonly></th></tr></tfoot>
									</table>
								</div>
								<div class="modal-footer">
									<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
									<button type="button" class="btn btn-danger" id="wdGhsReset">Reset</button>
									<button type="button" class="btn btn-primary" id="wdGhsConfirm" disabled>Confirm</button>
								</div>
							</div>
						</div>
					</div>

					<!-- CFA denominations modal -->
					<div class="modal fade" id="withdrawDenosCfaModal" tabindex="-1" aria-hidden="true">
						<div class="modal-dialog modal-lg">
							<div class="modal-content">
								<div class="modal-header"><h5 class="modal-title">Cash CFA Notes</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
								<div class="modal-body table-responsive">
									<div class="mb-2 small">Target CFA: <span id="wdCfaTarget" class="fw-bold">0</span> (from GH&#8373; <span id="wdCfaGhs">0.00</span> @ selling rate <?=number_format((float)$cfaRates['selling_rate'], 2)?>)</div>
									<div id="wdCfaMismatch" class="alert alert-danger py-2 mb-2 d-none" role="alert" aria-live="polite"></div>
									<table class="table table-sm">
										<thead><tr><th>NOTE</th><th>COUNT</th><th>VOLUME</th></tr></thead>
										<tbody>
											<?php foreach([10000,5000,2000,1000,500,200,100,50,20,10,5,2,1] as $d): ?>
											<tr data-denomination="<?=$d?>"><td><?=esc($d)?> CFA</td>
												<td><input type="number" min="0" step="1" class="form-control wd-cfa-count" placeholder="0"></td>
												<td><input type="text" class="form-control wd-cfa-volume" readonly placeholder="0"></td>
											</tr>
											<?php endforeach; ?>
										</tbody>
										<tfoot><tr><th>TOTAL</th><th><input type="text" class="form-control" id="wdCfaTotalCount" readonly></th><th><input type="text" class="form-control" id="wdCfaTotalVolume" readonly></th></tr></tfoot>
									</table>
								</div>
								<div class="modal-footer">
									<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
									<button type="button" class="btn btn-danger" id="wdCfaReset">Reset</button>
									<button type="button" class="btn btn-primary" id="wdCfaConfirm" disabled>Confirm</button>
								</div>
							</div>
						</div>
					</div>

					<script>
						(function(){
							var pm = document.getElementById('withdrawPaymentMethod');
							var amount = document.getElementById('withdrawAmount');
							var charge = document.getElementById('withdrawCharge');
							var sellingRate = <?=json_encode((float)$cfaRates['selling_rate'])?>;
							var isOperationsManager = <?=$isOperationsManager ? 'true' : 'false'?>;
							var chargeAssignments = <?=json_encode($viewerChargeAssignments, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)?>;
							if(!pm) return;

							var blocks = function(){ return document.querySelectorAll('.withdraw-method-block'); };
							var sync = function(){
								var v = (pm.value || '').toLowerCase();
								blocks().forEach(function(b){ b.style.display = b.getAttribute('data-method') === v ? '' : 'none'; });
								updateCfaTarget();
								updateGhsTarget();
							};
							pm.addEventListener('change', sync);

							var payable = function(){
								var a = parseFloat(amount && amount.value) || 0;
								var c = parseFloat(charge && charge.value) || 0;
								return Math.max(0, a - c);
							};
							var updateAssignedCharge = function(){
								if(isOperationsManager || !charge || !amount) return;
								var remaining = Math.max(0, parseFloat(amount.value) || 0);
								var totalCharge = 0;
								chargeAssignments.forEach(function(assignment){
									if(remaining <= 0.00001) return;
									var assignedAmount = parseFloat(assignment.amount) || 0;
									var available = parseFloat(assignment.remaining_amount) || 0;
									var take = Math.min(available, remaining);
									if(assignedAmount > 0 && take > 0){
										var prorated = (parseFloat(assignment.pickup_charge) || 0) * take / assignedAmount;
										totalCharge += Math.floor((prorated + 0.000000001) * 10) / 10;
										remaining = Math.max(0, remaining - take);
									}
								});
								charge.value = totalCharge.toFixed(1);
							};

							// GHS calculator
							var ghsModal = document.getElementById('withdrawDenosGhsModal');
							function updateGhsTarget(){
								var t = document.getElementById('wdGhsTarget');
								if(t) t.textContent = payable().toFixed(2);
								validateGhs(recomputeGhs());
							}
							function recomputeGhs(){
								var tc = 0, tv = 0, rows = [];
								document.querySelectorAll('.wd-ghs-count').forEach(function(inp){
									var tr = inp.closest('tr'); var denom = parseFloat(tr.getAttribute('data-denomination')) || 0;
									var c = parseInt(inp.value || '0', 10) || 0; var vol = denom * c;
									tr.querySelector('.wd-ghs-volume').value = vol.toFixed(2);
									tc += c; tv += vol;
									if(c > 0) rows.push({denomination: denom, count: c, volume: vol});
								});
								document.getElementById('wdGhsTotalCount').value = tc;
								document.getElementById('wdGhsTotalVolume').value = tv.toFixed(2);
								return {tc: tc, tv: tv, rows: rows};
							}
							function validateGhs(result){
								var targetMinor = Math.round(payable() * 100);
								var totalMinor = Math.round(result.tv * 100);
								var valid = totalMinor === targetMinor && (targetMinor === 0 || result.tc > 0);
								var button = document.getElementById('wdGhsConfirm');
								var error = document.getElementById('wdGhsMismatch');
								if(button) button.disabled = !valid;
								if(error){
									error.textContent = result.tc === 0
										? 'Enter notes totaling GHS ' + (targetMinor / 100).toFixed(2) + ' to enable Confirm.'
										: 'Entered notes total GHS ' + result.tv.toFixed(2) + ', but the target is GHS ' + (targetMinor / 100).toFixed(2) + '. Adjust the note counts to match exactly.';
									error.classList.toggle('d-none', valid);
								}
								return valid;
							}
							document.querySelectorAll('.wd-ghs-count').forEach(function(inp){ inp.addEventListener('input', updateGhsTarget); });
							document.getElementById('wdGhsReset').addEventListener('click', function(){
								document.querySelectorAll('.wd-ghs-count').forEach(function(inp){ inp.value = ''; });
								updateGhsTarget();
							});
							document.getElementById('wdGhsConfirm').addEventListener('click', function(){
								var r = recomputeGhs();
								if(!validateGhs(r)) return;
								document.getElementById('withdrawDenosJson').value = JSON.stringify(r.rows);
								var sum = document.getElementById('withdrawGhsDenosSummary');
								if(sum) sum.textContent = r.rows.length ? ('GHS ' + r.tv.toFixed(2) + ' across ' + r.tc + ' notes') : 'No denominations entered.';
								bootstrap.Modal.getOrCreateInstance(ghsModal).hide();
							});
							if(amount) amount.addEventListener('input', function(){
								updateAssignedCharge();
								updateGhsTarget();
								updateCfaTarget();
							});
							if(charge) charge.addEventListener('input', updateGhsTarget);

							// CFA calculator
							var cfaModal = document.getElementById('withdrawDenosCfaModal');
							function cfaFromGhs(){
								var g = payable();
								if(sellingRate <= 0) return 0;
								return Math.round((g * 1000 / sellingRate) / 5) * 5;
							}
							function updateCfaTarget(){
								var cfa = cfaFromGhs();
								var t = document.getElementById('wdCfaTarget'); if(t) t.textContent = cfa;
								var gh = document.getElementById('wdCfaGhs'); if(gh) gh.textContent = payable().toFixed(2);
								var disp = document.getElementById('withdrawCfaAmountDisplay'); if(disp) disp.value = cfa;
								document.getElementById('withdrawCfaAmount').value = cfa;
								validateCfa(recomputeCfa());
							}
							function recomputeCfa(){
								var tc = 0, tv = 0, rows = [];
								document.querySelectorAll('.wd-cfa-count').forEach(function(inp){
									var tr = inp.closest('tr'); var denom = parseFloat(tr.getAttribute('data-denomination')) || 0;
									var c = parseInt(inp.value || '0', 10) || 0; var vol = denom * c;
									tr.querySelector('.wd-cfa-volume').value = vol;
									tc += c; tv += vol;
									if(c > 0) rows.push({denomination: denom, count: c, volume: vol});
								});
								document.getElementById('wdCfaTotalCount').value = tc;
								document.getElementById('wdCfaTotalVolume').value = tv;
								return {tc: tc, tv: tv, rows: rows};
							}
							function validateCfa(result){
								var target = cfaFromGhs();
								var valid = Math.round(result.tv) === target && (target === 0 || result.tc > 0);
								var button = document.getElementById('wdCfaConfirm');
								var error = document.getElementById('wdCfaMismatch');
								if(button) button.disabled = !valid;
								if(error){
									error.textContent = result.tc === 0
										? 'Enter notes totaling ' + target + ' CFA to enable Confirm.'
										: 'Entered notes total ' + result.tv + ' CFA, but the target is ' + target + ' CFA. Adjust the note counts to match exactly.';
									error.classList.toggle('d-none', valid);
								}
								return valid;
							}
							document.querySelectorAll('.wd-cfa-count').forEach(function(inp){ inp.addEventListener('input', updateCfaTarget); });
							document.getElementById('wdCfaReset').addEventListener('click', function(){
								document.querySelectorAll('.wd-cfa-count').forEach(function(inp){ inp.value = ''; });
								updateCfaTarget();
							});
							document.getElementById('wdCfaConfirm').addEventListener('click', function(){
								var r = recomputeCfa();
								if(!validateCfa(r)) return;
								document.getElementById('withdrawCfaDenosJson').value = JSON.stringify(r.rows);
								var sum = document.getElementById('withdrawCfaDenosSummary');
								if(sum) sum.textContent = r.rows.length ? (r.tv + ' CFA across ' + r.tc + ' notes') : 'No CFA denominations entered.';
								bootstrap.Modal.getOrCreateInstance(cfaModal).hide();
							});
							if(charge) charge.addEventListener('input', updateCfaTarget);

							updateAssignedCharge();
							sync();
						})();
					</script>
					<?php endif; ?>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php endif; ?>

		<!-- ===== Customer-to-Customer Transfer ===== -->
		<div class="tab-pane fade" id="transfer-section" role="tabpanel" aria-labelledby="transfer-tab" tabindex="0">
			<div class="card border-0 shadow-sm">
				<div class="card-header d-flex align-items-center justify-content-between">
					<div>
						<strong>Transfer to Another Customer</strong>
						<div class="small text-muted">Move funds from this customer's balance to another customer's balance.</div>
					</div>
					<div class="small">Available balance: <span class="fw-bold <?=((float)$metrics['balance'] < 0) ? 'text-danger' : 'text-success'?>">GH&#8373; <?=number_format((float)$metrics['balance'], 2)?></span></div>
				</div>
				<div class="card-body">
					<?php if((float)$metrics['balance'] <= 0.00001): ?>
						<div class="alert alert-warning mb-0">This customer has no available balance to transfer.</div>
					<?php else: ?>
					<form method="post" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/customer_transfer/<?=esc((string)$customerId)?>" id="cashpickupTransferForm">
						<?=csrf()?>
						<input type="hidden" name="from_customer_id" value="<?=esc((string)$customerId)?>">
						<div class="row g-3">
							<div class="col-12 col-md-4">
								<label class="form-label">Amount (GH&#8373;) <span class="text-danger">*</span></label>
								<input type="number" step="0.01" min="0.01" max="<?=esc(number_format((float)$metrics['balance'], 2, '.', ''))?>" name="amount" class="form-control" required>
								<small class="text-muted">Max: GH&#8373; <?=number_format((float)$metrics['balance'], 2)?></small>
							</div>
							<div class="col-12 col-md-8">
								<label class="form-label">Destination Customer <span class="text-danger">*</span></label>
								<input type="hidden" name="to_customer_id" id="transferToCustomerId" value="">
								<input type="text" id="transferToCustomerSearch" class="form-control" placeholder="Search customer by name, phone, or ID number..." autocomplete="off">
								<div id="transferToCustomerResults" class="list-group position-relative" style="max-height:240px;overflow:auto;"></div>
								<div id="transferToCustomerSelected" class="small text-muted mt-1"></div>
							</div>
							<div class="col-12">
								<label class="form-label">Notes</label>
								<textarea name="notes" class="form-control" rows="2"></textarea>
							</div>
						</div>
						<div class="mt-3">
							<button type="submit" class="btn btn-info" id="transferSubmitBtn" disabled><i class="fa fa-right-left"></i> Transfer</button>
							<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/customer/<?=esc((string)$customerId)?>" class="btn btn-secondary">Cancel</a>
						</div>
					</form>
					<script>
						(function(){
							var search = document.getElementById('transferToCustomerSearch');
							var results = document.getElementById('transferToCustomerResults');
							var hidden = document.getElementById('transferToCustomerId');
							var selected = document.getElementById('transferToCustomerSelected');
							var submitBtn = document.getElementById('transferSubmitBtn');
							if(!search || !results || !hidden) return;
							var endpoint = '<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/customer_search';
							var timer = null;
							var fromId = '<?=esc((string)$customerId)?>';
							var clearResults = function(){ results.innerHTML = ''; results.style.display = 'none'; };
							var doSearch = function(term){
								if(!term || term.trim().length < 2){ clearResults(); return; }
								fetch(endpoint + '?term=' + encodeURIComponent(term.trim()), { cache: 'no-store' })
									.then(function(r){ return r.json(); })
									.then(function(data){
										if(!data || !data.success || !Array.isArray(data.data) || data.data.length === 0){
											results.innerHTML = '<div class="list-group-item small text-muted">No customers found.</div>';
											results.style.display = 'block';
											return;
										}
										var html = '';
										data.data.forEach(function(c){
											if(String(c.id) === String(fromId)) return;
											var name = (c.full_name || '').trim() || '***';
											var phone = (c.phone_number || '').trim() || '';
											var idnum = (c.id_number || '').trim() || '';
											html += '<a href="#" class="list-group-item list-group-item-action small" data-id="' + escape(String(c.id)) + '" data-name="' + escape(name) + '" data-phone="' + escape(phone) + '">';
											html += '<div class="fw-semibold">' + escape(name) + '</div>';
											if(phone) html += '<div class="text-muted">Phone: ' + escape(phone) + '</div>';
											if(idnum) html += '<div class="text-muted">ID: ' + escape(idnum) + '</div>';
											html += '</a>';
										});
										if(!html){ html = '<div class="list-group-item small text-muted">No other customers found.</div>'; }
										results.innerHTML = html;
										results.style.display = 'block';
									})
									.catch(function(){ clearResults(); });
							};
							function escape(s){ var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
							search.addEventListener('input', function(){
								hidden.value = '';
								if(submitBtn) submitBtn.disabled = true;
								if(selected) selected.textContent = '';
								if(timer) clearTimeout(timer);
								var v = search.value;
								timer = setTimeout(function(){ doSearch(v); }, 350);
							});
							results.addEventListener('click', function(e){
								var a = e.target.closest('a[data-id]');
								if(!a) return;
								e.preventDefault();
								hidden.value = a.getAttribute('data-id');
								var name = a.getAttribute('data-name') || '';
								var phone = a.getAttribute('data-phone') || '';
								search.value = name + (phone ? (' (' + phone + ')') : '');
								if(selected) selected.textContent = 'Selected: ' + name + (phone ? (' • ' + phone) : '');
								if(submitBtn) submitBtn.disabled = false;
								clearResults();
							});
							document.addEventListener('click', function(e){
								if(!results.contains(e.target) && e.target !== search){ clearResults(); }
							});
						})();
					</script>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php endif; ?>

	</div>

<script>
	(function(){
		const tabButtons = document.querySelectorAll('#cashpickupCustomerTabs [data-bs-toggle="tab"]');
		const currentHash = window.location.hash;
		if(currentHash){
			const targetButton = document.querySelector('#cashpickupCustomerTabs [data-bs-target="' + currentHash + '"]');
			if(targetButton && window.bootstrap && bootstrap.Tab){
				bootstrap.Tab.getOrCreateInstance(targetButton).show();
			}
		}
		tabButtons.forEach(btn => {
			btn.addEventListener('shown.bs.tab', function(e){
				const target = e.target.getAttribute('data-bs-target');
				if(target && window.history && typeof window.history.replaceState === 'function'){
					window.history.replaceState({}, '', window.location.pathname + window.location.search + target);
				}
			});
		});

		const container = document.getElementById('cashpickupCustomerTxContainer');
		const form = document.getElementById('cashpickupCustomerTxFilterForm');
		if(!container || !form) return;

		const endpoint = container.getAttribute('data-ajax-endpoint');
		if(!endpoint) return;

		const currentFilters = () => {
			const fd = new FormData(form);
			const out = {};
			for(const [k,v] of fd.entries()){
				if(v === null || v === '') continue;
				out[k] = v;
			}
			if(!out.customer_id){
				const hidden = form.querySelector('input[name="customer_id"]');
				const fromData = container.getAttribute('data-customer-id') || '';
				if(hidden && hidden.value) out.customer_id = hidden.value;
				else if(fromData) out.customer_id = fromData;
			}
			return out;
		};

		const load = async (payload) => {
			container.style.opacity = '0.6';
			try{
				const body = new URLSearchParams();
				Object.keys(payload || {}).forEach(k => {
					if(payload[k] !== undefined && payload[k] !== null) body.set(k, String(payload[k]));
				});
				body.set('_ts', String(Date.now()));
				const res = await fetch(endpoint, {
					method: 'POST',
					cache: 'no-store',
					headers: {
						'X-Requested-With': 'XMLHttpRequest',
						'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
					},
					body: body.toString()
				});
				const html = await res.text();
				container.innerHTML = html;
				renderPager();
				bindPagerLinks();
			}finally{
				container.style.opacity = '1';
			}
		};

		const renderPager = () => {
			const pager = container.querySelector('.js-cashpickup-customer-pager');
			if(!pager) return;
			const totalPages = parseInt(pager.getAttribute('data-total-pages') || '0', 10) || 0;
			const page = parseInt(pager.getAttribute('data-page') || '1', 10) || 1;
			if(totalPages <= 1){
				pager.innerHTML = '';
				return;
			}
			const extras = 2;
			let start = page - extras;
			let end = page + extras;
			if(start < 1) start = 1;
			if(end > totalPages) end = totalPages;

			const firstPage = 1;
			const nextJump = Math.min(totalPages, page + extras + 1);
			let html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';
			html += '<li class="page-item' + (page <= 1 ? ' disabled' : '') + '"><a class="page-link" href="#" data-page="' + firstPage + '">First</a></li>';
			for(let x = start; x <= end; x++){
				html += '<li class="page-item' + (x === page ? ' active' : '') + '"><a class="page-link" href="#" data-page="' + x + '">' + x + '</a></li>';
			}
			html += '<li class="page-item' + (page >= totalPages ? ' disabled' : '') + '"><a class="page-link" href="#" data-page="' + nextJump + '">Next</a></li>';
			html += '</ul></nav>';
			pager.innerHTML = html;
		};

		const bindPagerLinks = () => {
			const pager = container.querySelector('.js-cashpickup-customer-pager');
			if(!pager) return;
			const links = pager.querySelectorAll('a[data-page]');
			links.forEach(a => {
				if(a.dataset && a.dataset.ajaxBound) return;
				a.dataset.ajaxBound = '1';
				a.addEventListener('click', function(e){
					e.preventDefault();
					const page = a.getAttribute('data-page');
					if(!page) return;
					if(a.closest('.page-item') && a.closest('.page-item').classList.contains('disabled')) return;
					const payload = currentFilters();
					payload.page = String(page);
					load(payload);
				});
			});
		};

		const submitFilters = () => {
			const payload = currentFilters();
			payload.page = '1';
			load(payload);
		};

		document.addEventListener('submit', function(e){
			if(e.target !== form) return;
			e.preventDefault();
			submitFilters();
		});

		let searchTimer = null;
		const bindAutoFilters = () => {
			const findInput = document.getElementById('cashpickupCustomerFind');
			const sideSelect = document.getElementById('cashpickupCustomerSide');
			const dateFrom = document.getElementById('cashpickupCustomerDateFrom');
			const dateTo = document.getElementById('cashpickupCustomerDateTo');
			const limitSelect = document.getElementById('cashpickupCustomerLimit');
			if(findInput){
				findInput.addEventListener('input', function(){
					if(searchTimer) clearTimeout(searchTimer);
					searchTimer = setTimeout(submitFilters, 350);
				});
			}
			const immediate = (el) => {
				if(!el) return;
				el.addEventListener('change', submitFilters);
			};
			immediate(sideSelect);
			immediate(dateFrom);
			immediate(dateTo);
			immediate(limitSelect);
		};
		bindAutoFilters();

		renderPager();
		bindPagerLinks();
	})();
</script>

<?php endif; ?>
<?php endif; ?>
