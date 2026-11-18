<?php
if (!user_can('view_cashpickup')) :
?>
	<div class="alert alert-danger text-center">Access denied.</div>
<?php
else:
?>

<?php
	require_once plugin_path('helpers/customer-ledger.php');
	require_once plugin_path('helpers/customer-assignment.php');
	require_once plugin_path('helpers/permissions.php');
	$db = new \Core\Database;
	\CashpickupCustomerAssignment\ensure_schema($db);
	$assignmentUsageSql = \CashpickupCustomerAssignment\payout_usage_summary_sql();
	$assignmentUsedAmountSql = static function(string $assignmentAlias) use ($assignmentUsageSql): string {
		return "COALESCE((
			SELECT assignment_usage.used_amount
			FROM ({$assignmentUsageSql}) AS assignment_usage
			WHERE assignment_usage.assignment_id = {$assignmentAlias}.id
		),0)";
	};
	\CashpickupLedger\ensure_cashpickup_customer_schema($db);
	\CashpickupLedger\ensure_cashpickup_columns($db);
	\CashpickupLedger\ensure_cashpickup_credit_schema($db);
	\CashpickupLedger\ensure_cashpickup_customer_approval_columns($db);
	\CashpickupLedger\repair_customer_transaction_links($db);
	$duplicateCustomerIdentity = "(
		(
			TRIM(COALESCE(duplicate_customer.id_number,'')) <> ''
			AND LOWER(TRIM(duplicate_customer.id_number)) = LOWER(TRIM(c.id_number))
		)
		OR (
			TRIM(COALESCE(duplicate_customer.phone_number,'')) <> ''
			AND TRIM(duplicate_customer.phone_number) = TRIM(c.phone_number)
			AND TRIM(COALESCE(duplicate_customer.full_name,'')) <> ''
			AND LOWER(TRIM(duplicate_customer.full_name)) = LOWER(TRIM(c.full_name))
		)
	)";
	$canonicalCustomerCondition = "NOT EXISTS (
		SELECT 1
		FROM cashpickup_customers duplicate_customer
		WHERE duplicate_customer.deleted = 0
		AND duplicate_customer.id <> c.id
		AND {$duplicateCustomerIdentity}
		AND (
			CASE
				WHEN LOWER(COALESCE(duplicate_customer.approval_status,'approved')) IN ('pending','draft') THEN 0
				ELSE 1
			END
			<
			CASE
				WHEN LOWER(COALESCE(c.approval_status,'approved')) IN ('pending','draft') THEN 0
				ELSE 1
			END
			OR (
				(
					CASE
						WHEN LOWER(COALESCE(duplicate_customer.approval_status,'approved')) IN ('pending','draft') THEN 0
						ELSE 1
					END
				) = (
					CASE
						WHEN LOWER(COALESCE(c.approval_status,'approved')) IN ('pending','draft') THEN 0
						ELSE 1
					END
				)
				AND duplicate_customer.id < c.id
			)
		)
	)";
	$sessionUserId = 0;
	try {
		$sesUser = get_session();
		$sessionUserId = (int)($sesUser->id ?? 0);
	} catch (\Throwable $e) {}
	$canReviewCustomers = \CashpickupPermissions\is_top_authority($db, $sessionUserId);
	$canAssignCustomerPayout = false;
	$canWithdrawCustomerPayout = false;
	try {
		$canAssignCustomerPayout = \CashpickupPermissions\can_assign_customer_payout($db, $sessionUserId);
		$canWithdrawCustomerPayout = \CashpickupPermissions\can_withdraw_customer_payout($db, $sessionUserId);
	} catch (\Throwable $e) {
		error_log('[cashpickup] Customer payout action access check failed: ' . $e->getMessage());
	}
	$isNonPrivileged = !(user_can('is_admin') || user_can('is_manager'));

	require_once plugin_path('helpers/crm-helper.php');
	
	$isAjax = (string)($_GET['ajax'] ?? '') === '1';
	$activeTab = strtolower(trim((string)($_GET['tab'] ?? 'customers')));
	if (!in_array($activeTab, ['customers', 'new', 'gl', 'crm'], true)
		|| ($activeTab === 'new' && !$canReviewCustomers)) {
		$activeTab = 'customers';
	}
	$totalCustomerCountRows = $db->query(
		"SELECT COUNT(DISTINCT c.id) AS c
		 FROM cashpickup_customers c
		 WHERE c.deleted = 0
		 AND {$canonicalCustomerCondition}"
	) ?: [];
	if ($db->has_error) {
		throw new \RuntimeException('Unable to load the total customer count.');
	}
	$totalCustomerCount = (int)($totalCustomerCountRows[0]->c ?? 0);
	$newCustomerCount = 0;
	if ($canReviewCustomers) {
		$newCustomerCountRows = $db->query(
			"SELECT COUNT(DISTINCT c.id) AS c
			 FROM cashpickup_customers c
			 WHERE deleted = 0
			 AND {$canonicalCustomerCondition}
			 AND LOWER(COALESCE(approval_status,'approved')) IN ('pending','draft')"
		) ?: [];
		$newCustomerCount = (int)($newCustomerCountRows[0]->c ?? 0);
	}
	$find = trim((string)($_GET['find'] ?? ''));
	$dateFrom = trim((string)($_GET['date_from'] ?? ''));
	$dateTo = trim((string)($_GET['date_to'] ?? ''));
	$service = trim((string)($_GET['service'] ?? ''));
	$agentId = trim((string)($_GET['agent_id'] ?? ''));
	$statusFilter = strtolower(trim((string)($_GET['status'] ?? '')));
	$payoutStatusFilter = strtolower(trim((string)($_GET['payout_status'] ?? '')));
	$attentionFilter = (string)($_GET['attention'] ?? '');
	if (!in_array($statusFilter, ['', 'approved', 'pending', 'denied'], true)) {
		$statusFilter = '';
	}
	if (!in_array($payoutStatusFilter, ['', 'pending', 'approved', 'denied', 'reversed', 'cancelled', 'none'], true)) {
		$payoutStatusFilter = '';
	}
	if (!in_array($attentionFilter, ['', '1'], true)) {
		$attentionFilter = '';
	}
	$activeAdvancedFilterCount = count(array_filter(
		[$dateFrom, $dateTo, $statusFilter, $payoutStatusFilter, $attentionFilter],
		static fn($value) => trim((string)$value) !== ''
	));
	$sync = trim((string)($_GET['sync'] ?? ''));
	$autoSynced = false;
	$syncCount = 0;

	try {
		$existing = $db->query("SELECT COUNT(*) AS c FROM cashpickup_customers WHERE deleted = 0") ?: [];
		$totalCustomers = (int)($existing[0]->c ?? 0);
		if ($sync === '1' || $totalCustomers === 0) {
			$syncCount = \CashpickupLedger\sync_customers_from_cashpickups($db, 5000);
			$autoSynced = true;
		}
	} catch (\Throwable $e) {
		$autoSynced = false;
	}

	$params = [];
	$conditions = ['c.deleted = 0', $canonicalCustomerCondition];
	$creditSummaryJoin = "LEFT JOIN (
		SELECT cp.customer_id,
		       SUM(GREATEST(0, COALESCE(cp.amount_involved,0) - COALESCE(repaid.total_repaid,0))) AS credit_outstanding,
		       SUM(CASE
		       	WHEN LOWER(TRIM(COALESCE(cp.transaction_status,''))) IN ('approved','pending payout')
		       		AND (cp.approval_status IS NULL OR cp.approval_status = '' OR LOWER(TRIM(cp.approval_status)) IN ('approved','pending'))
		       	THEN COALESCE(cp.amount_involved,0)
		       	ELSE 0
		       END) AS credit_balance_debit
		FROM cashpickups cp
		LEFT JOIN (
			SELECT credit_id, SUM(amount) AS total_repaid
			FROM cashpickup_credit_repayments
			GROUP BY credit_id
		) repaid ON repaid.credit_id = cp.id
		WHERE (cp.deleted IS NULL OR cp.deleted = 0)
		AND LOWER(TRIM(COALESCE(cp.transaction_side,''))) = 'receive'
		AND LOWER(TRIM(COALESCE(cp.credit_mode,''))) = 'credit'
		AND LOWER(TRIM(COALESCE(cp.transaction_status,''))) IN ('approved','pending payout')
		AND (cp.approval_status IS NULL OR cp.approval_status = '' OR LOWER(cp.approval_status) IN ('approved','pending'))
		GROUP BY cp.customer_id
	) credit_summary ON credit_summary.customer_id = c.id";
	$customerBalanceJoin = "LEFT JOIN (
		SELECT cp_balance.customer_id,
		       COALESCE(SUM(" . \CashpickupLedger\statement_delta_sql('cp_balance') . "),0) AS cash_balance
		FROM (
			SELECT DISTINCT id, customer_id, transaction_side, credit_mode, transaction_status,
			                confirmed_by, amount_involved, amount_paid_out, approval_status,
			                pickup_charge
			FROM cashpickups
			WHERE (deleted IS NULL OR deleted = 0)
		) cp_balance
		GROUP BY cp_balance.customer_id
	) customer_balance ON customer_balance.customer_id = c.id";
	if ($activeTab === 'new') {
		$conditions[] = "LOWER(COALESCE(c.approval_status,'approved')) IN ('pending','draft')";
	}
	if ($statusFilter === 'pending') {
		$conditions[] = "LOWER(COALESCE(c.approval_status,'approved')) IN ('pending','draft')";
	} elseif ($statusFilter !== '') {
		$conditions[] = "LOWER(COALESCE(c.approval_status,'approved')) = :customer_status";
		$params['customer_status'] = $statusFilter;
	}
	if ($dateFrom !== '' || $dateTo !== '') {
		$createdDateConditions = [];
		$transactionDateConditions = ['(cp_filter.deleted IS NULL OR cp_filter.deleted = 0)', 'cp_filter.customer_id = c.id'];
		if ($dateFrom !== '') {
			$createdDateConditions[] = 'DATE(c.date_created) >= :customer_created_from';
			$transactionDateConditions[] = 'DATE(cp_filter.date_created) >= :customer_transaction_from';
			$params['customer_created_from'] = $dateFrom;
			$params['customer_transaction_from'] = $dateFrom;
		}
		if ($dateTo !== '') {
			$createdDateConditions[] = 'DATE(c.date_created) <= :customer_created_to';
			$transactionDateConditions[] = 'DATE(cp_filter.date_created) <= :customer_transaction_to';
			$params['customer_created_to'] = $dateTo;
			$params['customer_transaction_to'] = $dateTo;
		}
		$conditions[] = '(((' . implode(' AND ', $createdDateConditions) . ')
			OR EXISTS (SELECT 1 FROM cashpickups cp_filter WHERE ' . implode(' AND ', $transactionDateConditions) . ')))';
	}
	$attentionAssignmentUsedSql = $assignmentUsedAmountSql('ca_attention');
	$workerAssignmentUsedSql = $assignmentUsedAmountSql('ca_assigned_attention');
	$pendingAssignmentUsedSql = $assignmentUsedAmountSql('ca_pending');
	$attentionCondition = "(LOWER(COALESCE(c.approval_status,'approved')) IN ('pending','draft')
			OR EXISTS (
				SELECT 1 FROM cashpickups cp_attention
				WHERE (cp_attention.deleted IS NULL OR cp_attention.deleted = 0)
				AND cp_attention.customer_id = c.id
				AND (
					cp_attention.customer_assignment_id IS NULL
					OR NOT EXISTS (
						SELECT 1 FROM cashpickup_customer_assignments ca_cancelled
						WHERE ca_cancelled.id = cp_attention.customer_assignment_id
						AND LOWER(TRIM(COALESCE(ca_cancelled.status,''))) = 'cancelled'
					)
				)
				AND (LOWER(TRIM(COALESCE(cp_attention.transaction_status,''))) LIKE 'pending%'
					OR LOWER(TRIM(COALESCE(cp_attention.approval_status,''))) LIKE 'pending%')
			)
			OR (
				COALESCE(credit_summary.credit_outstanding,0) > 0.00001
				AND COALESCE(customer_balance.cash_balance,0) < -0.00001
			)
			OR EXISTS (
				SELECT 1
				FROM cashpickup_customer_assignments ca_attention
				WHERE ca_attention.customer_id = c.id
				AND LOWER(TRIM(COALESCE(ca_attention.status,''))) = 'active'
				AND GREATEST(0, ca_attention.amount - {$attentionAssignmentUsedSql}) > 0.00001
			))";
	$workerAttentionCondition = "(EXISTS (
			SELECT 1 FROM cashpickups cp_assigned_attention
			WHERE (cp_assigned_attention.deleted IS NULL OR cp_assigned_attention.deleted = 0)
			AND cp_assigned_attention.customer_id = c.id
			AND cp_assigned_attention.assigned_user_id = {$sessionUserId}
			AND (
				cp_assigned_attention.customer_assignment_id IS NULL
				OR NOT EXISTS (
					SELECT 1 FROM cashpickup_customer_assignments ca_cancelled
					WHERE ca_cancelled.id = cp_assigned_attention.customer_assignment_id
					AND LOWER(TRIM(COALESCE(ca_cancelled.status,''))) = 'cancelled'
				)
			)
			AND (LOWER(TRIM(COALESCE(cp_assigned_attention.transaction_status,''))) LIKE 'pending%'
				OR LOWER(TRIM(COALESCE(cp_assigned_attention.approval_status,''))) LIKE 'pending%')
		)
		OR EXISTS (
			SELECT 1
			FROM cashpickup_customer_assignments ca_assigned_attention
			WHERE ca_assigned_attention.customer_id = c.id
			AND ca_assigned_attention.assigned_user_id = {$sessionUserId}
			AND LOWER(TRIM(COALESCE(ca_assigned_attention.status,''))) = 'active'
			AND GREATEST(0, ca_assigned_attention.amount - {$workerAssignmentUsedSql}) > 0.00001
		))";
	$visibleAttentionCondition = $canReviewCustomers ? $attentionCondition : $workerAttentionCondition;
	if ($attentionFilter === '1') {
		$conditions[] = $visibleAttentionCondition;
	}
	if ($payoutStatusFilter === 'none') {
		$conditions[] = "NOT EXISTS (
			SELECT 1 FROM cashpickups cp_status
			WHERE (cp_status.deleted IS NULL OR cp_status.deleted = 0)
			AND cp_status.customer_id = c.id
			AND LOWER(TRIM(COALESCE(cp_status.transaction_side,''))) = 'pay'
		)";
	} elseif ($payoutStatusFilter !== '') {
		$payoutStatusCondition = match ($payoutStatusFilter) {
			'pending' => "(LOWER(TRIM(COALESCE(cp_status.transaction_status,''))) LIKE 'pending%'
				OR LOWER(TRIM(COALESCE(cp_status.approval_status,''))) = 'pending')",
			'approved' => "LOWER(TRIM(COALESCE(cp_status.transaction_status,''))) = 'approved'
				AND (cp_status.approval_status IS NULL OR cp_status.approval_status = '' OR LOWER(TRIM(cp_status.approval_status)) = 'approved')",
			'denied' => "(LOWER(TRIM(COALESCE(cp_status.transaction_status,''))) LIKE 'denied%'
				OR LOWER(TRIM(COALESCE(cp_status.approval_status,''))) = 'denied')",
			'reversed' => "(LOWER(TRIM(COALESCE(cp_status.transaction_status,''))) LIKE 'reversed%'
				OR LOWER(TRIM(COALESCE(cp_status.approval_status,''))) = 'reversed')",
			'cancelled' => "(LOWER(TRIM(COALESCE(cp_status.transaction_status,''))) LIKE 'cancel%'
				OR LOWER(TRIM(COALESCE(cp_status.approval_status,''))) LIKE 'cancel%')",
		};
		$conditions[] = "EXISTS (
			SELECT 1 FROM cashpickups cp_status
			WHERE (cp_status.deleted IS NULL OR cp_status.deleted = 0)
			AND cp_status.customer_id = c.id
			AND LOWER(TRIM(COALESCE(cp_status.transaction_side,''))) = 'pay'
			AND {$payoutStatusCondition}
		)";
	}
	if ($find !== '') {
		$conditions[] = '(c.full_name LIKE :find OR c.phone_number LIKE :find OR c.id_number LIKE :find)';
		$params['find'] = '%' . $find . '%';
	}
	$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

	$limit = (int)($_GET['limit'] ?? 10);
	$limit = $limit > 0 ? min(200, $limit) : 10;
	$pager = new \Core\Pager($limit);
	$offset = $pager->offset;
	$activePaidOutExpr = "(CASE
		WHEN LOWER(transaction_side) = 'pay'
			AND LOWER(COALESCE(transaction_status,'')) = 'approved'
			AND (confirmed_by IS NOT NULL AND confirmed_by != '')
			AND COALESCE(amount_paid_out,0) > 0
			AND (
				approval_status IS NULL
				OR approval_status = ''
				OR LOWER(approval_status) IN ('approved','pending')
			)
		THEN (COALESCE(amount_paid_out,0) + COALESCE(pickup_charge,0))
		ELSE 0
	END)";
	$activePaidOutExprAliased = "(CASE
		WHEN LOWER(cp.transaction_side) = 'pay'
			AND LOWER(COALESCE(cp.transaction_status,'')) = 'approved'
			AND (cp.confirmed_by IS NOT NULL AND cp.confirmed_by != '')
			AND COALESCE(cp.amount_paid_out,0) > 0
			AND (
				cp.approval_status IS NULL
				OR cp.approval_status = ''
				OR LOWER(cp.approval_status) IN ('approved','pending')
			)
		THEN (COALESCE(cp.amount_paid_out,0) + COALESCE(cp.pickup_charge,0))
		ELSE 0
	END)";

	$totalRows = 0;
	$rows = [];
	$metrics = [
		'total_received' => 0,
		'total_paid_out' => 0,
		'balance' => 0,
		'total_customers' => $totalCustomerCount,
	];
	$creditMetrics = [
		'total_credit_given' => 0,
		'total_credit_paid' => 0,
		'total_amount_in_credit' => 0,
	];
	$assignmentSummaries = [];
	$pendingTaskSummaries = [];
	$pendingPayoutReviewByCustomerUser = [];
	$attentionByCustomer = [];
	if (in_array($activeTab, ['customers', 'new'], true)) {
		$totalRow = $db->query("SELECT COUNT(DISTINCT c.id) AS c FROM cashpickup_customers c {$creditSummaryJoin} {$customerBalanceJoin} {$where}", $params) ?: [];
		$totalRows = (int)($totalRow[0]->c ?? 0);

		$rows = $db->query(
			"SELECT DISTINCT c.*,
			        TRIM(CONCAT_WS(' ', NULLIF(creator.first_name,''), NULLIF(creator.last_name,''))) AS creator_name,
			        0 AS tx_count,
			        0 AS total_received,
			        0 AS total_paid_out,
			        COALESCE((
			            SELECT MAX(cp.date_created)
			            FROM cashpickups cp
			            WHERE (cp.deleted IS NULL OR cp.deleted = 0)
			              AND cp.customer_id = c.id
			        ), c.date_created) AS last_tx,
			        ({$visibleAttentionCondition}) AS requires_attention,
			        COALESCE(credit_summary.credit_outstanding,0) AS credit_outstanding,
			        COALESCE(credit_summary.credit_balance_debit,0) AS credit_balance_debit,
			        GREATEST(
			            COALESCE((
			                SELECT MAX(cp_pending.date_created)
			                FROM cashpickups cp_pending
			                WHERE (cp_pending.deleted IS NULL OR cp_pending.deleted = 0)
			                  AND cp_pending.customer_id = c.id
			                  AND (
			                      cp_pending.customer_assignment_id IS NULL
			                      OR NOT EXISTS (
			                          SELECT 1 FROM cashpickup_customer_assignments ca_cancelled
			                          WHERE ca_cancelled.id = cp_pending.customer_assignment_id
			                            AND LOWER(TRIM(COALESCE(ca_cancelled.status,''))) = 'cancelled'
			                      )
			                  )
			                  AND (LOWER(TRIM(COALESCE(cp_pending.transaction_status,''))) LIKE 'pending%'
			                       OR LOWER(TRIM(COALESCE(cp_pending.approval_status,''))) LIKE 'pending%')
			            ), '1000-01-01 00:00:00'),
			            COALESCE((
			                SELECT MAX(ca_pending.date_created)
			                FROM cashpickup_customer_assignments ca_pending
			                WHERE ca_pending.customer_id = c.id
			                  AND LOWER(TRIM(COALESCE(ca_pending.status,''))) = 'active'
			                  AND GREATEST(0, ca_pending.amount - {$pendingAssignmentUsedSql}) > 0.00001
			            ), '1000-01-01 00:00:00')
			        ) AS last_pending_activity,
			        COALESCE(c.date_updated, c.date_created) AS last_updated
			 FROM cashpickup_customers c
			 LEFT JOIN users creator ON creator.id = CAST(NULLIF(c.created_by,'') AS UNSIGNED)
			 {$creditSummaryJoin}
			 {$customerBalanceJoin}
			 {$where}
			 ORDER BY
			          requires_attention DESC,
			          CASE WHEN last_pending_activity > '1000-01-01 00:00:00' THEN 0 ELSE 1 END,
			          last_pending_activity DESC,
			          CASE WHEN LOWER(COALESCE(c.approval_status,'approved')) IN ('pending','draft') THEN 0 ELSE 1 END,
			          COALESCE(c.date_created,'1000-01-01') DESC,
			          (COALESCE(c.full_name,'') = '') ASC,
			          c.full_name ASC,
			          last_tx DESC,
			          COALESCE(c.date_updated, c.date_created) DESC
			 LIMIT {$limit} OFFSET {$offset}",
			$params
		) ?: [];
		if (!empty($rows)) {
			$customerIds = array_filter(array_map(static fn($row) => (int)($row->id ?? 0), $rows), static fn($id) => $id > 0);
			if (!empty($customerIds)) {
				$customerIdList = implode(',', $customerIds);
				$customerTotals = $db->query(
					"SELECT
						customer_id,
						COUNT(*) AS tx_count,
						COALESCE(SUM(CASE
							WHEN LOWER(TRIM(COALESCE(transaction_side,''))) = 'receive'
								AND LOWER(TRIM(COALESCE(credit_mode,''))) <> 'credit'
							THEN amount_involved
							ELSE 0
						END),0) AS total_received,
						COALESCE(SUM(CASE
							WHEN LOWER(TRIM(COALESCE(transaction_side,''))) = 'pay'
								AND LOWER(TRIM(COALESCE(transaction_status,''))) = 'approved'
								AND (confirmed_by IS NOT NULL AND confirmed_by != '')
								AND COALESCE(amount_paid_out,0) > 0
								AND LOWER(TRIM(COALESCE(credit_mode,''))) <> 'credit'
								AND (
									approval_status IS NULL
									OR approval_status = ''
									OR LOWER(TRIM(COALESCE(approval_status,''))) IN ('approved','pending')
								)
							THEN (COALESCE(amount_paid_out,0) + COALESCE(pickup_charge,0))
							ELSE 0
						END),0) AS total_paid_out,
						MAX(date_created) AS transaction_last_tx
					FROM cashpickups
					WHERE (deleted IS NULL OR deleted = 0)
					AND customer_id IN ({$customerIdList})
					GROUP BY customer_id",
					[]
				) ?: [];
				$customerTotalsMap = [];
				foreach ($customerTotals as $totalRow) {
					$customerTotalsMap[(int)($totalRow->customer_id ?? 0)] = $totalRow;
				}
				foreach ($rows as $row) {
					$customerTotal = $customerTotalsMap[(int)($row->id ?? 0)] ?? null;
					if ($customerTotal !== null) {
						$row->tx_count = (int)($customerTotal->tx_count ?? 0);
						$row->total_received = (float)($customerTotal->total_received ?? 0);
						$row->total_paid_out = (float)($customerTotal->total_paid_out ?? 0);
						$row->last_tx = (string)($customerTotal->transaction_last_tx ?? $row->last_tx);
					}
				}
			}
		}
		if (!empty($rows)) {
			$customerIds = array_filter(
				array_map(static fn($row) => (int)($row->id ?? 0), $rows),
				static fn($id) => $id > 0
			);
			$customerIdList = implode(',', $customerIds);
			if ($customerIdList !== '') {
				$assignmentRows = $db->query(
					"SELECT a.customer_id, a.assigned_user_id,
					        u.first_name AS assigned_first_name,
					        u.last_name AS assigned_last_name,
					        SUM(GREATEST(0, a.amount - COALESCE(used.used_amount,0))) AS remaining_amount
					 FROM cashpickup_customer_assignments a
					 LEFT JOIN users u ON u.id = a.assigned_user_id
					 LEFT JOIN ({$assignmentUsageSql}) used ON used.assignment_id = a.id
					 WHERE a.customer_id IN ({$customerIdList})
					 AND LOWER(TRIM(COALESCE(a.status,''))) = 'active'
					 GROUP BY a.customer_id, a.assigned_user_id, u.first_name, u.last_name",
					[]
				);
				if ($db->has_error) {
					throw new \RuntimeException('Unable to load payout assignment summaries.');
				}
				foreach ($assignmentRows ?: [] as $assignmentRow) {
					$customerId = (int)($assignmentRow->customer_id ?? 0);
					$assignedUserId = (int)($assignmentRow->assigned_user_id ?? 0);
					$remaining = round(max(0.0, (float)($assignmentRow->remaining_amount ?? 0)), 2);
					if ($customerId <= 0 || $assignedUserId <= 0 || $remaining <= 0.00001) {
						continue;
					}
					$firstName = trim((string)($assignmentRow->assigned_first_name ?? ''));
					$lastName = trim((string)($assignmentRow->assigned_last_name ?? ''));
					$assignmentSummaries[$customerId][] = [
						'user_id' => $assignedUserId,
						'name' => trim($firstName . ' ' . $lastName) ?: 'User #' . $assignedUserId,
						'remaining' => $remaining,
					];
				}

				$pendingRows = $db->query(
					"SELECT DISTINCT cp.id, cp.customer_id, cp.transaction_side, cp.assigned_user_id, cp.date_created,
					        cp.amount_paid_out, cp.amount_involved, cp.pickup_charge,
					        u.first_name AS assigned_first_name,
					        u.last_name AS assigned_last_name
					 FROM cashpickups cp
					 LEFT JOIN users u ON u.id = cp.assigned_user_id
					 WHERE (cp.deleted IS NULL OR cp.deleted = 0)
					 AND cp.customer_id IN ({$customerIdList})
					 AND (
						cp.customer_assignment_id IS NULL
						OR NOT EXISTS (
							SELECT 1 FROM cashpickup_customer_assignments ca_cancelled
							WHERE ca_cancelled.id = cp.customer_assignment_id
							AND LOWER(TRIM(COALESCE(ca_cancelled.status,''))) = 'cancelled'
						)
					 )
					 AND (LOWER(TRIM(COALESCE(cp.transaction_status,''))) LIKE 'pending%'
						OR LOWER(TRIM(COALESCE(cp.approval_status,''))) LIKE 'pending%')
					 ORDER BY cp.date_created DESC, cp.id DESC",
					[]
				);
				if ($db->has_error) {
					throw new \RuntimeException('Unable to load pending customer tasks.');
				}
				foreach ($pendingRows ?: [] as $pendingRow) {
					$customerId = (int)($pendingRow->customer_id ?? 0);
					if ($customerId <= 0) {
						continue;
					}
					$assignedUserId = (int)($pendingRow->assigned_user_id ?? 0);
					$side = strtolower(trim((string)($pendingRow->transaction_side ?? '')));
					$firstName = trim((string)($pendingRow->assigned_first_name ?? ''));
					$lastName = trim((string)($pendingRow->assigned_last_name ?? ''));
					$amount = $side === 'pay'
						? (float)($pendingRow->amount_paid_out ?? 0) + (float)($pendingRow->pickup_charge ?? 0)
						: (float)($pendingRow->amount_involved ?? 0);
					$pendingTaskSummaries[$customerId][] = [
						'side' => $side,
						'user_id' => $assignedUserId,
						'id' => (int)($pendingRow->id ?? 0),
						'amount' => $amount,
						'name' => $assignedUserId > 0
							? (trim($firstName . ' ' . $lastName) ?: 'User #' . $assignedUserId)
							: 'Not assigned — management review',
					];
					if ($side === 'pay' && $assignedUserId > 0) {
						$pendingPayoutReviewByCustomerUser[$customerId][$assignedUserId] = true;
					}
				}
			}
		}

		// Populate overall metrics for the Customers tab (general ledger + credit overview)
		if ($activeTab === 'customers') {
			try {
				$ledgerFilters = [
					'find' => $find,
					'date_from' => $dateFrom,
					'date_to' => $dateTo,
				];
				$metrics = \CashpickupLedger\fetch_general_ledger_metrics($db, $ledgerFilters);
				$creditMetrics = \CashpickupLedger\fetch_credit_overview_metrics($db, $ledgerFilters);
			} catch (\Throwable $e) {
				// Keep defaults (zeros) on error
			}
		}
		foreach ($rows as $customer) {
			$customerId = (int)($customer->id ?? 0);
			if ($customerId <= 0) {
				continue;
			}
			$approvalStatus = strtolower(trim((string)($customer->approval_status ?? 'approved')));
			$needsReview = in_array($approvalStatus, ['pending', 'draft'], true);
			$balance = (float)($customer->total_received ?? 0)
				- (float)($customer->total_paid_out ?? 0)
				- (float)($customer->credit_balance_debit ?? 0);
			$availableBalance = max(0.0, $balance);
			$items = [];
			if ($canReviewCustomers && $needsReview) {
				$items[] = [
					'task' => 'Customer registration needs approval.',
					'assignee' => 'Admin / Operations Manager / General Manager',
					'action' => 'approve_customer',
				];
			}

			foreach ($pendingTaskSummaries[$customerId] ?? [] as $pendingTask) {
				if (!$canReviewCustomers && (int)$pendingTask['user_id'] !== $sessionUserId) {
					continue;
				}
				$sideLabel = $pendingTask['side'] === 'pay'
					? 'Payout'
					: ($pendingTask['side'] === 'receive' ? 'Deposit' : 'Transaction');
				$amount = max(0.0, (float)$pendingTask['amount']);
				$items[] = [
					'task' => $sideLabel . ' GH₵ ' . number_format($amount, 2) . ' is awaiting review.',
					'assignee' => $pendingTask['name'],
					'action' => 'review_transaction',
					'transaction_id' => (int)$pendingTask['id'],
				];
			}

			$viewerAssignedBalance = 0.0;
			foreach ($assignmentSummaries[$customerId] ?? [] as $assignment) {
				if (!$canReviewCustomers && (int)$assignment['user_id'] !== $sessionUserId) {
					continue;
				}
				if (!empty($pendingPayoutReviewByCustomerUser[$customerId][(int)$assignment['user_id']])) {
					continue;
				}
				$available = min($availableBalance, (float)$assignment['remaining']);
				$items[] = [
					'task' => $available > 0.00001
						? 'Payout ready: GH₵ ' . number_format($available, 2) . ' available.'
						: 'Assigned payout balance has no matching customer balance; reconcile before payout.',
					'assignee' => $assignment['name'],
					'user_id' => (int)$assignment['user_id'],
					'action' => ((int)$assignment['user_id'] === $sessionUserId && $canWithdrawCustomerPayout && $available > 0.00001 && !$needsReview)
						? 'pay_assigned'
						: ($canAssignCustomerPayout ? 'manage_assignment' : 'review_ledger'),
				];
				if ((int)$assignment['user_id'] === $sessionUserId) {
					$viewerAssignedBalance += (float)$assignment['remaining'];
				}
			}

			$creditOutstanding = max(0.0, (float)($customer->credit_outstanding ?? 0));
			if ($canReviewCustomers && $creditOutstanding > 0.00001 && $balance < -0.00001) {
				$items[] = [
					'task' => 'Credit repayment follow-up: GH₵ ' . number_format($creditOutstanding, 2) . ' outstanding.',
					'assignee' => 'Management follow-up',
					'action' => 'review_ledger',
				];
			}
			if (empty($items)) {
				continue;
			}

			$availableForViewer = min($availableBalance, $viewerAssignedBalance);
			$attentionByCustomer[$customerId] = [
				'items' => $items,
				'viewer_payout_available' => $approvalStatus === 'approved' ? $availableForViewer : 0.0,
			];
		}
	}

	$generalLedger = [
		'rows' => [],
		'total_rows' => 0,
	];
	$generalLedgerMetrics = [
		'total_received' => 0,
		'total_paid_out' => 0,
		'balance' => 0,
		'total_customers' => $totalCustomerCount,
	];
	if ($activeTab === 'gl') {
		try {
			$ledgerFilters = [
				'find' => $find,
				'date_from' => $dateFrom,
				'date_to' => $dateTo,
				'order' => 'desc',
			];
			$ledgerPage = \CashpickupLedger\fetch_general_ledger_page($db, $ledgerFilters, $limit, $offset);
			$generalLedger = [
				'rows' => is_array($ledgerPage['rows'] ?? null) ? $ledgerPage['rows'] : [],
				'total_rows' => (int)($ledgerPage['total_rows'] ?? 0),
			];
			$generalLedgerMetrics = \CashpickupLedger\fetch_general_ledger_metrics($db, $ledgerFilters);
		} catch (\Throwable $e) {
			$generalLedger = [
				'rows' => [],
				'total_rows' => 0,
			];
			$generalLedgerMetrics = [
				'total_received' => 0,
				'total_paid_out' => 0,
				'balance' => 0,
				'total_customers' => $totalCustomerCount,
			];
		}
	}
	$metrics['total_customers'] = $totalCustomerCount;
	$generalLedgerMetrics['total_customers'] = $totalCustomerCount;

	$crmData = ['agent_options' => []];
	$crmFilters = [
		'find' => trim((string)($find ?? '')),
		'date_from' => $dateFrom,
		'date_to' => $dateTo,
		'service' => $service,
		'agent_id' => $agentId,
	];

	if ($activeTab === 'crm') {
		$hasAnyFilter = false;
		foreach (['find','date_from','date_to','service','agent_id'] as $k) {
			if (trim((string)($crmFilters[$k] ?? '')) !== '') { $hasAnyFilter = true; break; }
		}
		if (!$hasAnyFilter) {
			$first = date('Y-m-01');
			$last = date('Y-m-t');
			$crmFilters['date_from'] = $first;
			$crmFilters['date_to'] = $last;
			$selectedDateFrom = $first;
			$selectedDateTo = $last;
		}
		try {
			$crmPhoneSearch = trim((string)($_GET['find'] ?? ''));
			$crmData['agent_options'] = \CashpickupCRM\getAgentOptions($db);
			if ($crmPhoneSearch !== '' || !empty(array_filter($crmFilters, static fn($value) => trim((string)$value) !== ''))) {
				$crmData['customer_info'] = \CashpickupCRM\searchCustomerByPhone($db, $crmPhoneSearch, $sessionUserId, $isNonPrivileged, $crmFilters);
				if ($crmData['customer_info']['found']) {
					$normalizedPhone = $crmData['customer_info']['normalized_phone'];
					if ($normalizedPhone === null) {
						$normalizedPhone = $crmPhoneSearch;
					}
					$returningInfo = \CashpickupCRM\getReturningRate($db, $normalizedPhone, $sessionUserId, $isNonPrivileged, $crmFilters);
					$crmData['customer_info']['returning_info'] = $returningInfo;
					$history = \CashpickupCRM\getTransactionHistory($db, $normalizedPhone, 20, 0, $sessionUserId, $isNonPrivileged, $crmFilters);
					$crmData['transaction_history'] = $history;
				}
			}
			foreach (array_keys(\CashpickupCRM\crmServiceDefinitions()) as $service) {
				$crmData['top_customers'][$service] = \CashpickupCRM\getTopCustomersByService($db, $service, 5, $sessionUserId, $isNonPrivileged, $crmFilters);
				$crmData['top_agents'][$service] = \CashpickupCRM\getTopAgentsByService($db, $service, 5, $sessionUserId, $isNonPrivileged, $crmFilters);
			}
			$crmData['analytics'] = \CashpickupCRM\getReturningAnalytics($db, $crmFilters, $sessionUserId, $isNonPrivileged);

		// Calculate total customer visits for percentage calculation
		if (!empty($crmData['analytics']['customers'])) {
			$totalVisits = 0;
			foreach ($crmData['analytics']['customers'] as $customer) {
				$totalVisits += (int)($customer['visit_count'] ?? 0);
			}
			$crmData['analytics']['total_customer_visits'] = $totalVisits;
		}
		} catch (\Throwable $e) {
			// Silently fail for CRM tab
		}
	}
	$renderAttentionCard = function(int $customerId) use ($attentionByCustomer, $admin_route, $plugin_route, $canReviewCustomers, $canAssignCustomerPayout, $canWithdrawCustomerPayout, $sessionUserId) {
		$attention = $attentionByCustomer[$customerId] ?? null;
		if (empty($attention['items'])) {
			return;
		}
		?>
		<div class="alert alert-warning border-warning-subtle py-2 px-3 mt-2 mb-0 text-start">
			<div class="fw-semibold small mb-1"><i class="fa-solid fa-triangle-exclamation me-1"></i>Attention — next step</div>
			<ul class="small mb-1 ps-3">
				<?php foreach($attention['items'] as $item): ?>
					<li class="d-flex flex-wrap align-items-center gap-2 mb-1">
						<span>
							<?=esc((string)$item['task'])?>
							<span class="text-body-secondary">Assigned to: <?=esc((string)$item['assignee'])?></span>
						</span>
						<?php if(($item['action'] ?? '') === 'approve_customer' && $canReviewCustomers): ?>
							<form method="post" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/customer_approve/<?=$customerId?>" class="d-inline">
								<?=csrf()?>
								<input type="hidden" name="customer_id" value="<?=$customerId?>">
								<button class="btn btn-sm btn-success" type="submit"><i class="fa fa-check me-1" aria-hidden="true"></i>Approve</button>
							</form>
						<?php elseif(($item['action'] ?? '') === 'review_transaction' && (int)($item['transaction_id'] ?? 0) > 0): ?>
							<a class="btn btn-sm btn-outline-primary" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=esc((string)$item['transaction_id'])?>">
								<i class="fa fa-eye me-1" aria-hidden="true"></i>Review transaction
							</a>
						<?php elseif(($item['action'] ?? '') === 'pay_assigned' && $canWithdrawCustomerPayout && (int)($item['user_id'] ?? 0) === $sessionUserId): ?>
							<a class="btn btn-sm btn-warning" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/customer/<?=$customerId?>#withdraw-section">
								<i class="fa-solid fa-hand-holding-dollar me-1" aria-hidden="true"></i>Pay assigned
							</a>
						<?php elseif(($item['action'] ?? '') === 'manage_assignment' && $canAssignCustomerPayout): ?>
							<a class="btn btn-sm btn-outline-primary" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/customer/<?=$customerId?>#withdraw-section">
								<i class="fa fa-sliders me-1" aria-hidden="true"></i>Manage assignment
							</a>
						<?php elseif(($item['action'] ?? '') === 'review_ledger'): ?>
							<a class="btn btn-sm btn-outline-primary" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/customer/<?=$customerId?>">
								<i class="fa fa-book-open me-1" aria-hidden="true"></i>Review ledger
							</a>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	};
	$renderResults = function() use ($rows, $metrics, $creditMetrics, $totalRows, $limit, $pager, $admin_route, $plugin_route, $canReviewCustomers, $isNonPrivileged, $activeTab, $newCustomerCount, $attentionByCustomer, $renderAttentionCard) {
		$totalPages = (int)ceil(((float)$totalRows) / max(1, (int)$limit));
		?>
		<?php if ($activeTab === 'new'): ?>
			<div class="d-flex align-items-center gap-3 p-3 p-md-4 mb-3 rounded-3 bg-warning-subtle border border-warning-subtle">
				<div class="rounded-circle bg-warning text-dark d-flex align-items-center justify-content-center flex-shrink-0" style="width:2.75rem;height:2.75rem">
					<i class="fa-solid fa-user-clock"></i>
				</div>
				<div class="min-w-0">
					<div class="fw-semibold">Review new customer registrations</div>
					<div class="small text-body-secondary"><?=$newCustomerCount?> awaiting approval. Approved or denied registrations leave this queue.</div>
				</div>
			</div>
		<?php else: ?>
		<details class="card border-0 shadow-sm mb-3 cashpickup-metrics-details">
			<summary class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
				<span>
					<span class="d-block small text-muted">Cashpickup balance</span>
					<span class="d-block fw-bold fs-5 <?=((float)$metrics['balance'] < 0) ? 'text-danger' : ''?>">GH&#8373; <?=number_format((float)$metrics['balance'], 2)?></span>
					<span class="d-block small text-muted">Credit payouts are deducted from the general cashpickup balance.</span>
				</span>
				<span class="small fw-semibold text-primary">Metrics <i class="fa fa-chevron-down ms-1 cashpickup-metrics-chevron" aria-hidden="true"></i></span>
			</summary>
			<div class="card-body">
		<div class="row g-2 mb-3">
			<div class="col-12 col-md-4">
				<div class="border rounded p-3 h-100">
					<div class="d-flex align-items-center justify-content-between">
						<div class="text-muted">Total Received</div>
						<i class="fa fa-hand-holding-dollar text-success"></i>
					</div>
					<div class="fw-bold">GH&#8373; <?=number_format((float)$metrics['total_received'], 2)?></div>
				</div>
			</div>
			<div class="col-12 col-md-4">
				<div class="border rounded p-3 h-100">
					<div class="d-flex align-items-center justify-content-between">
						<div class="text-muted">Total Paid Out</div>
						<i class="fa fa-coins text-warning"></i>
					</div>
					<div class="fw-bold">GH&#8373; <?=number_format((float)$metrics['total_paid_out'], 2)?></div>
				</div>
			</div>
			<div class="col-12 col-md-4">
				<div class="border rounded p-3 h-100">
					<div class="d-flex align-items-center justify-content-between">
						<div class="text-muted">Total Customers</div>
						<i class="fa fa-users text-info"></i>
					</div>
					<div class="fw-bold"><?=number_format((int)$metrics['total_customers'])?></div>
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
					<div class="fw-bold">GH&#8373; <?=number_format((float)$creditMetrics['total_credit_given'], 2)?></div>
				</div>
			</div>
			<div class="col-12 col-md-4">
				<div class="border rounded p-3 h-100">
					<div class="d-flex align-items-center justify-content-between">
						<div class="text-muted">Total Credit Paid</div>
						<i class="fa fa-rotate-left text-success"></i>
					</div>
					<div class="fw-bold">GH&#8373; <?=number_format((float)$creditMetrics['total_credit_paid'], 2)?></div>
				</div>
			</div>
			<div class="col-12 col-md-4">
				<div class="border rounded p-3 h-100">
					<div class="d-flex align-items-center justify-content-between">
						<div class="text-muted">Amount in Credit</div>
						<i class="fa fa-scale-balanced text-warning"></i>
					</div>
					<div class="fw-bold">GH&#8373; <?=number_format((float)$creditMetrics['total_amount_in_credit'], 2)?></div>
				</div>
			</div>
		</div>
			</div>
		</details>
		<?php endif; ?>

		<div class="card border-0 shadow-sm">
			<div class="d-lg-none p-2 p-sm-3 cashpickup-customer-mobile-list">
				<?php if(empty($rows)): ?>
					<div class="text-center text-body-secondary py-4">
						<i class="fa-solid fa-circle-check d-block fs-3 mb-2"></i>
						<?=$activeTab === 'new' ? 'No customer registrations need approval.' : 'No customers found.'?>
					</div>
				<?php else: ?>
					<?php foreach($rows as $idx => $c): ?>
						<?php
							$received = (float)($c->total_received ?? 0);
							$paidOut = (float)($c->total_paid_out ?? 0);
							$balance = $received - $paidOut - (float)($c->credit_balance_debit ?? 0);
							$creditOutstanding = (float)($c->credit_outstanding ?? 0);
							$approvalStatus = strtolower(trim((string)($c->approval_status ?? 'approved')));
							if ($approvalStatus === '') $approvalStatus = 'approved';
							$needsReview = in_array($approvalStatus, ['pending', 'draft'], true);
							$requiresAttention = !empty($c->requires_attention) && (int)$c->requires_attention === 1;
							$customerName = trim((string)($c->full_name ?? ''));
							$customerPhone = trim((string)($c->phone_number ?? ''));
							$customerLocation = trim((string)($c->location ?? ''));
							$customerId = (int)($c->id ?? 0);
							$creatorName = trim((string)($c->creator_name ?? ''));
							if ($creatorName === '') {
								$creatorId = (int)($c->created_by ?? 0);
								$creatorName = $creatorId > 0 ? 'User #' . $creatorId : (trim((string)($c->created_by ?? '')) ?: 'Unknown');
							}
							$customerAttention = $attentionByCustomer[$customerId] ?? [];
							$lastTransaction = (string)(get_date_time($c->last_tx ?? '---'));
						?>
						<article class="card cashpickup-customer-mobile-card <?=$needsReview && $canReviewCustomers ? 'is-pending' : ''?>">
							<div class="card-body">
								<div class="d-flex justify-content-between align-items-start gap-2">
									<div class="cashpickup-customer-mobile-heading">
										<div class="fw-semibold text-break"><?=esc($customerName !== '' ? $customerName : 'Unnamed customer')?></div>
										<div class="small text-body-secondary">Customer #<?=$customerId?></div>
										<div class="small text-body-secondary">Registered by: <?=esc($creatorName)?></div>
									</div>
									<div class="d-flex flex-wrap justify-content-end gap-1">
										<?php if($needsReview && $canReviewCustomers): ?>
											<span class="badge text-bg-warning">Needs approval</span>
										<?php elseif($needsReview): ?>
											<span class="badge text-bg-secondary">Pending</span>
										<?php elseif($approvalStatus === 'approved'): ?>
											<span class="badge text-bg-success">Approved</span>
										<?php elseif($approvalStatus === 'denied'): ?>
											<span class="badge text-bg-danger">Denied</span>
										<?php else: ?>
											<span class="badge text-bg-secondary"><?=esc(ucfirst($approvalStatus))?></span>
										<?php endif; ?>
										<?php if($requiresAttention): ?>
											<span class="badge text-bg-danger">Attention</span>
										<?php endif; ?>
									</div>
								</div>
								<?php $renderAttentionCard($customerId); ?>
								<div class="cashpickup-customer-mobile-grid mt-2">
									<div class="cashpickup-customer-mobile-value">
										<div class="small text-body-secondary">Phone</div>
										<div class="fw-medium"><?=esc($customerPhone !== '' ? $customerPhone : 'Not provided')?></div>
									</div>
									<div class="cashpickup-customer-mobile-value">
										<div class="small text-body-secondary">ID number</div>
										<div class="fw-medium text-break"><?=esc((string)($c->id_number ?? 'Not provided'))?></div>
										<div class="small text-body-secondary"><?=esc((string)($c->id_type ?? ''))?></div>
									</div>
									<div class="cashpickup-customer-mobile-value">
										<div class="small text-body-secondary">Location</div>
										<div class="fw-medium text-break"><?=esc($customerLocation !== '' ? $customerLocation : 'Not provided')?></div>
									</div>
									<div class="cashpickup-customer-mobile-value">
										<div class="small text-body-secondary">Activity</div>
										<div class="fw-medium"><?=number_format((float)($c->tx_count ?? 0))?> transactions</div>
										<div class="small text-body-secondary"><?=esc($lastTransaction)?></div>
									</div>
								</div>
								<div class="cashpickup-customer-mobile-footer border-top mt-2 pt-2">
									<div class="cashpickup-customer-summary-grid">
										<div><div class="small text-body-secondary">Deposits · lifetime</div><div class="fw-semibold">GH&#8373; <?=number_format($received, 2)?></div></div>
										<div><div class="small text-body-secondary">Paid out · lifetime</div><div class="fw-semibold">GH&#8373; <?=number_format($paidOut, 2)?></div></div>
										<div><div class="small text-body-secondary">Balance</div><div class="fw-bold <?=($balance < 0) ? 'text-danger' : 'text-body'?>">GH&#8373; <?=number_format((float)$balance, 2)?></div></div>
										<div><div class="small text-body-secondary">Credit due</div><div class="fw-semibold <?=($creditOutstanding > 0) ? 'text-warning-emphasis' : ''?>">GH&#8373; <?=number_format($creditOutstanding, 2)?></div></div>
									</div>
									<div class="cashpickup-customer-mobile-actions justify-content-end">
										<a class="btn btn-icon btn-outline-primary" title="View customer" aria-label="View customer" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/customer/<?=$customerId?>"><i class="fa fa-eye"></i></a>
										<?php if($canReviewCustomers && $needsReview): ?>
											<form method="post" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/customer_approve/<?=$customerId?>">
												<?=csrf()?>
												<input type="hidden" name="customer_id" value="<?=$customerId?>">
												<button class="btn btn-icon btn-success" type="submit" title="Approve customer" aria-label="Approve customer"><i class="fa fa-check"></i></button>
											</form>
											<form method="post" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/customer_deny/<?=$customerId?>">
												<?=csrf()?>
												<input type="hidden" name="customer_id" value="<?=$customerId?>">
												<input type="hidden" name="denial_reason" value="Registration denied">
												<button class="btn btn-icon btn-outline-danger" type="submit" title="Deny customer" aria-label="Deny customer"><i class="fa fa-times"></i></button>
											</form>
										<?php endif; ?>
										<?php if(user_can('add_cashpickup') && $approvalStatus === 'approved'): ?>
											<a class="btn btn-icon btn-outline-success" title="New receive" aria-label="New receive" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/add?customer_id=<?=$customerId?>"><i class="fa fa-plus"></i></a>
										<?php endif; ?>
										<?php if($canWithdrawCustomerPayout && $approvalStatus === 'approved' && $balance > 0.00001): ?>
											<a class="btn btn-icon btn-outline-warning" title="Withdraw" aria-label="Withdraw" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/customer/<?=$customerId?>#withdraw-section"><i class="fa fa-hand-holding-dollar"></i></a>
										<?php endif; ?>
										<?php if($approvalStatus === 'approved'): ?>
											<a class="btn btn-icon btn-outline-info" title="Transfer" aria-label="Transfer" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/customer/<?=$customerId?>#transfer-section"><i class="fa fa-right-left"></i></a>
										<?php endif; ?>
									</div>
								</div>
							</div>
						</article>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
			<div class="d-none d-lg-block">
			<div class="table-responsive">
				<table class="table table-striped table-hover cashpickup-customer-table align-middle mb-0">
					<thead>
						<tr>
							<th>#</th>
							<th>Customer</th>
							<th>ID Number</th>
							<th>Activity</th>
							<th class="text-end">Deposits / paid out</th>
							<th class="text-end">Balance / credit due</th>
							<th>Status</th>
							<th class="text-end">Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php if(empty($rows)): ?>
							<tr><td colspan="8" class="text-center text-muted"><?=$activeTab === 'new' ? 'No customer registrations need approval.' : 'No customers found.'?></td></tr>
						<?php else: ?>
							<?php foreach($rows as $idx => $c): ?>
								<?php
									$received = (float)($c->total_received ?? 0);
									$paidOut = (float)($c->total_paid_out ?? 0);
									$balance = $received - $paidOut - (float)($c->credit_balance_debit ?? 0);
									$creditOutstanding = (float)($c->credit_outstanding ?? 0);
									$approvalStatus = strtolower(trim((string)($c->approval_status ?? 'approved')));
									if ($approvalStatus === '') $approvalStatus = 'approved';
									$requiresAttention = !empty($c->requires_attention) && (int)$c->requires_attention === 1;
									$customerName = trim((string)($c->full_name ?? ''));
									$customerPhone = trim((string)($c->phone_number ?? ''));
									$customerLocation = trim((string)($c->location ?? ''));
									$customerId = (int)($c->id ?? 0);
									$creatorName = trim((string)($c->creator_name ?? ''));
									if ($creatorName === '') {
										$creatorId = (int)($c->created_by ?? 0);
										$creatorName = $creatorId > 0 ? 'User #' . $creatorId : (trim((string)($c->created_by ?? '')) ?: 'Unknown');
									}
									$customerAttention = $attentionByCustomer[$customerId] ?? [];
									$lastTransaction = (string)(get_date_time($c->last_tx ?? '---'));
								?>
								<tr>
									<td><?=number_format((int)$pager->offset + $idx + 1)?></td>
									<td>
										<div class="d-flex align-items-center gap-2">
								<div class="fw-semibold"><?=esc($customerName !== '' ? $customerName : '***')?></div>
								<?php if ($requiresAttention): ?>
									<span class="badge bg-danger text-white">Attention</span>
								<?php endif; ?>
							</div>
							<?php $renderAttentionCard($customerId); ?>
										<div class="small text-muted"><i class="fa fa-phone me-1"></i><?=esc($customerPhone !== '' ? $customerPhone : '***')?></div>
										<div class="small text-muted"><i class="fa fa-user-plus me-1"></i>Registered by: <?=esc($creatorName)?></div>
										<div class="small text-muted"><i class="fa fa-location-dot me-1"></i><?=esc($customerLocation !== '' ? $customerLocation : '***')?></div>
									</td>
									<td>
										<div><?=esc((string)($c->id_number ?? '***'))?></div>
										<small>Type: <?=esc((string)($c->id_type ?? '***'))?></small>
									</td>
									<td>
										<div class="fw-semibold"><?=number_format((float)($c->tx_count ?? 0))?> transaction<?=((float)($c->tx_count ?? 0) == 1) ? '' : 's'?></div>
										<div class="small text-muted"><?=esc($lastTransaction)?></div>
									</td>
									<td class="text-end cashpickup-financial-pair">
										<div><span>Deposits</span><strong class="text-success"><?=number_format($received, 2)?></strong></div>
										<div><span>Paid out</span><strong class="text-danger"><?=number_format($paidOut, 2)?></strong></div>
									</td>
									<td class="text-end cashpickup-financial-pair">
										<div><span>Balance</span><strong class="<?=($balance < 0) ? 'text-danger' : 'text-body'?>"><?=number_format((float)$balance, 2)?></strong></div>
										<div><span>Credit due</span><strong class="<?=($creditOutstanding > 0) ? 'text-warning-emphasis' : 'text-body'?>"><?=number_format($creditOutstanding, 2)?></strong></div>
									</td>
									<td>
										<?php if($approvalStatus === 'approved'): ?>
											<span class="badge bg-success">Approved</span>
										<?php elseif($approvalStatus === 'reversed'): ?>
											<span class="badge bg-danger">Reversed</span>
										<?php elseif($approvalStatus === 'denied'): ?>
											<span class="badge bg-danger">Denied</span>
										<?php else: ?>
											<span class="badge bg-warning text-dark">Pending</span>
										<?php endif; ?>
									</td>
									<td class="text-end">
										<a class="btn btn-icon btn-outline-primary" title="View" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/customer/<?=esc((string)($c->id ?? 0))?>"><i class="fa fa-eye"></i></a>
										<?php if($canReviewCustomers && in_array($approvalStatus, ['pending','draft'], true)): ?>
											<form method="post" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/customer_approve/<?=esc((string)($c->id ?? 0))?>" class="d-inline">
												<?=csrf()?>
												<input type="hidden" name="customer_id" value="<?=esc((string)($c->id ?? 0))?>">
												<button class="btn btn-icon btn-success" type="submit" title="Approve customer"><i class="fa fa-check"></i></button>
											</form>
											<form method="post" action="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/customer_deny/<?=esc((string)($c->id ?? 0))?>" class="d-inline">
												<?=csrf()?>
												<input type="hidden" name="customer_id" value="<?=esc((string)($c->id ?? 0))?>">
												<input type="hidden" name="denial_reason" value="Registration denied">
												<button class="btn btn-icon btn-danger" type="submit" title="Deny customer"><i class="fa fa-times"></i></button>
											</form>
										<?php endif; ?>
										<?php if(user_can('add_cashpickup') && $approvalStatus === 'approved'): ?>
												<a class="btn btn-icon btn-outline-success" title="New Receive" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/add?customer_id=<?=esc((string)($c->id ?? 0))?>"><i class="fa fa-plus"></i></a>
											<?php endif; ?>
											<?php if($canWithdrawCustomerPayout && $approvalStatus === 'approved' && $balance > 0.00001): ?>
												<a class="btn btn-icon btn-outline-warning" title="Withdraw" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/customer/<?=esc((string)($c->id ?? 0))?>#withdraw-section"><i class="fa fa-hand-holding-dollar"></i></a>
											<?php endif; ?>
											<?php if($approvalStatus === 'approved'): ?>
												<a class="btn btn-icon btn-outline-info" title="Transfer" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/customer/<?=esc((string)($c->id ?? 0))?>#transfer-section"><i class="fa fa-right-left"></i></a>
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

		<div
			class="mt-3 js-cashpickup-customers-pager"
			data-total-rows="<?=esc((string)$totalRows)?>"
			data-limit="<?=esc((string)$limit)?>"
			data-page="<?=esc((string)($pager->page_number ?? 1))?>"
			data-total-pages="<?=esc((string)$totalPages)?>"
		></div>
		<?php
	};

	$renderCRM = function() use ($crmData, $admin_route, $plugin_route, $isNonPrivileged, $find) {
		$found = $crmData['customer_info']['found'] ?? false;
		$phones = $crmData['customer_info']['phones'] ?? [];
		$services = $crmData['customer_info']['services'] ?? [];
		$totalTx = $crmData['customer_info']['total_transactions'] ?? 0;
		$totalAmount = $crmData['customer_info']['total_amount'] ?? 0;
		$normalizedPhone = $crmData['customer_info']['normalized_phone'] ?? null;
		$searchValue = $find !== '' ? $find : ($normalizedPhone ?? '');
		$returningInfo = $crmData['customer_info']['returning_info'] ?? [];
		$transactionHistory = $crmData['transaction_history']['transactions'] ?? [];
		$topCustomers = $crmData['top_customers'] ?? [];
		$topAgents = $crmData['top_agents'] ?? [];
		$analytics = $crmData['analytics'] ?? [];
		$agentOptions = $crmData['agent_options'] ?? [];
		$selectedServices = array_values(array_filter(array_map('trim', preg_split('/\s*,\s*/', (string)($_GET['service'] ?? ''))), static fn($v) => $v !== ''));
		$selectedAgents = array_values(array_filter(array_map('trim', preg_split('/\s*,\s*/', (string)($_GET['agent_id'] ?? ''))), static fn($v) => $v !== ''));
		$selectedDateFrom = trim((string)($_GET['date_from'] ?? ''));
		$selectedDateTo = trim((string)($_GET['date_to'] ?? ''));
		?>
		<div class="cashpickup-crm">
		<section class="crm-hero p-3 p-lg-4 mb-4">
			<div class="row g-3 align-items-center">
				<div class="col-12 col-xl-4">
					<span class="badge rounded-pill text-bg-primary mb-2"><i class="fa fa-chart-line me-1"></i>Customer intelligence</span>
					<h4 class="crm-section-title mb-1">Understand customer activity</h4>
					<p class="text-body-secondary mb-0 small">Search across services, review return behavior, and compare customer and agent trends.</p>
				</div>
				<div class="col-12 col-lg-8 col-xl-6">
					<label class="form-label small fw-semibold mb-1" for="crmPhoneSearch">Search customers and transactions</label>
					<input type="search" id="crmPhoneSearch" class="form-control form-control-lg" placeholder="Phone, name, service, reference or note" value="<?=esc((string)$searchValue)?>">
				</div>
				<div class="col-12 col-lg-4 col-xl-2 d-grid">
					<button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#crmAdvancedFilters" aria-expanded="false" aria-controls="crmAdvancedFilters">
						<i class="fa fa-sliders me-1"></i>Filters
					</button>
				</div>
			</div>
			<div class="collapse mt-3" id="crmAdvancedFilters">
				<div class="crm-advanced-panel p-3">
					<div class="row g-3">
							<div class="col-12 col-md-4">
								<label class="form-label small fw-semibold" for="crmDateFrom">Date from</label>
								<input type="date" id="crmDateFrom" class="form-control" value="<?=esc($selectedDateFrom)?>">
							</div>
							<div class="col-12 col-md-4">
								<label class="form-label small fw-semibold" for="crmDateTo">Date to</label>
								<input type="date" id="crmDateTo" class="form-control" value="<?=esc($selectedDateTo)?>">
							</div>
							<div class="col-12 col-md-6">
								<div class="d-flex justify-content-between align-items-center mb-1">
									<label class="form-label small fw-semibold mb-0">Service</label>
									<small class="text-body-secondary">Select any</small>
								</div>
								<div class="crm-checkbox-list border rounded-3 p-2 bg-body">
									<?php foreach(\CashpickupCRM\crmServiceDefinitions() as $key => $def): ?>
										<?php $checked = in_array($key, $selectedServices, true); ?>
										<div class="form-check mb-1">
											<input class="form-check-input crmServiceCheckbox" type="checkbox" id="crmService_<?=esc($key)?>" value="<?=esc($key)?>" <?= $checked ? 'checked' : ''?>>
											<label class="form-check-label" for="crmService_<?=esc($key)?>"><?=esc($def['label'])?></label>
										</div>
									<?php endforeach; ?>
								</div>
							</div>
							<div class="col-12 col-md-6">
								<div class="d-flex justify-content-between align-items-center mb-1">
									<label class="form-label small fw-semibold mb-0">Agent</label>
									<small class="text-body-secondary">Select any</small>
								</div>
								<div class="crm-checkbox-list border rounded-3 p-2 bg-body">
									<?php foreach($agentOptions as $agent): ?>
										<?php $agentValue = (string)($agent['id'] ?? ''); $checked = in_array($agentValue, $selectedAgents, true); ?>
										<div class="form-check mb-1">
											<input class="form-check-input crmAgentCheckbox" type="checkbox" id="crmAgent_<?=esc($agentValue)?>" value="<?=esc($agentValue)?>" <?= $checked ? 'checked' : ''?>>
											<label class="form-check-label" for="crmAgent_<?=esc($agentValue)?>"><?=esc((string)($agent['name'] ?? ''))?></label>
										</div>
									<?php endforeach; ?>
								</div>
							</div>
							<div class="col-12 d-flex justify-content-end">
								<button class="btn btn-primary" type="button" id="crmApplyFiltersBtn"><i class="fa fa-filter me-1"></i>Apply filters</button>
							</div>
					</div>
				</div>
			</div>
		</section>
		<?php if($found): ?>
					<div class="row g-3 mb-4">
						<div class="col-12 col-md-6">
							<div class="crm-metric-card">
								<h6 class="text-muted mb-3">Customer Profile</h6>
								<div class="mb-2">
									<small class="text-muted">Phone Numbers Found:</small>
									<div class="fw-bold"><?=number_format(count($phones))?> unique number<?=count($phones) === 1 ? '' : 's'?></div>
								</div>
								<div class="mb-2">
									<small class="text-muted">Total Transactions:</small>
									<div class="fw-bold text-primary"><?=number_format((int)$totalTx)?></div>
								</div>
								<div class="mb-2">
									<small class="text-muted">Total Amount:</small>
									<div class="fw-bold text-success">GH&#8373; <?=number_format((float)$totalAmount, 2)?></div>
								</div>
							</div>
						</div>
						<div class="col-12 col-md-6">
							<div class="crm-metric-card">
								<h6 class="text-muted mb-3">Returning Customer Analysis</h6>
								<div class="mb-2">
									<small class="text-muted">Total Visits:</small>
									<div class="fw-bold"><?=number_format((int)($returningInfo['total_visits'] ?? 0))?></div>
								</div>
								<div class="mb-2">
									<small class="text-muted">Status:</small>
									<div>
										<?php if($returningInfo['is_returning'] ?? false): ?>
											<span class="badge bg-success">Returning Customer</span>
										<?php else: ?>
											<span class="badge bg-warning text-dark">First Time Customer</span>
										<?php endif; ?>
									</div>
								</div>
								<div class="mb-0">
									<small class="text-muted">Returning Rate:</small>
									<div class="fw-bold text-info"><?=number_format((float)($returningInfo['returning_rate'] ?? 0), 2)?>%</div>
								</div>
							</div>
						</div>
					</div>
					<div class="mb-4">
						<h6 class="text-muted mb-2">Service Metrics</h6>
						<div class="row g-2">
							<?php
							$serviceIcons = [
								'cashpickups' => 'fa-hand-holding-dollar',
								'airtimebundles' => 'fa-mobile-screen',
								'momoremits' => 'fa-paper-plane',
								'agencybankings' => 'fa-building-columns',
								'cfas' => 'fa-coins',
								'ezwichs' => 'fa-credit-card',
								'payments' => 'fa-receipt',
							];
							$serviceColors = [
								'cashpickups' => '#16a34a',
								'airtimebundles' => '#0ea5e9',
								'momoremits' => '#f97316',
								'agencybankings' => '#7c3aed',
								'cfas' => '#eab308',
								'ezwichs' => '#0f172a',
								'payments' => '#06b6d4',
							];
							foreach($services as $svc => $data):
								$serviceLabel = (string)($data['label'] ?? ucwords(str_replace('_', ' ', $svc)));
								$returningRate = (float)($data['returning_rate'] ?? 0);
								$icon = $serviceIcons[$svc] ?? 'fa-chart-simple';
								$color = $serviceColors[$svc] ?? '#6b7280';
							?>
								<div class="col-12 col-md-6 col-lg-4">
									<div class="crm-service-card p-3 h-100" style="border-left:4px solid <?=esc($color)?>;">
										<div class="d-flex justify-content-between align-items-start mb-2">
											<div class="text-start">
												<div class="fw-bold"><i class="fa <?=esc($icon)?> me-2" style="color:<?=esc($color)?>"></i><?=esc($serviceLabel)?></div>
											</div>
											<span class="badge bg-light text-dark"><?=number_format((int)($data['count'] ?? 0))?> tx</span>
										</div>
										<div class="text-muted small">Amount</div>
										<div class="fw-semibold text-success mb-2">GH&#8373; <?=number_format((float)($data['amount'] ?? 0), 2)?></div>
										<div class="d-flex justify-content-between small text-muted">
											<span>Returning rate</span>
											<span class="fw-semibold <?=($returningRate >= 50) ? 'text-success' : (($returningRate >= 20) ? 'text-warning' : 'text-muted')?>"><?=number_format($returningRate, 1)?>%</span>
										</div>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
					<div class="card border-0 shadow-sm mb-4">
						<div class="card-header border-0 bg-transparent d-flex justify-content-between align-items-center gap-2">
							<div>
								<h6 class="crm-section-title mb-0">Recent transactions</h6>
								<small class="text-body-secondary">Latest activity across the selected services</small>
							</div>
							<span class="badge rounded-pill text-bg-secondary"><?=number_format(count($transactionHistory))?> records</span>
						</div>
						<div class="table-responsive">
							<table class="table table-sm table-hover align-middle mb-0">
								<thead>
									<tr>
										<th>Date</th>
										<th>Service</th>
										<th>Agent</th>
										<th>Amount</th>
										<th>Status</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach($transactionHistory as $tx): ?>
										<tr>
											<td><?=esc(get_date_time($tx['date'] ?? ''))?></td>
											<td><span class="badge bg-secondary"><?=esc($tx['service_label'] ?? '')?></span></td>
											<td><?=esc($tx['agent_name'] ?? 'Unassigned')?></td>
											<td class="text-end">GH&#8373; <?=number_format((float)($tx['amount'] ?? 0), 2)?></td>
											<td>
												<span class="badge <?=strtolower($tx['status'] ?? '') === 'approved' ? 'bg-success' : 'bg-warning text-dark'?>">
													<?=esc($tx['status'] ?? 'Unknown')?>
												</span>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>
				<?php elseif(!empty(trim((string)($_GET['find'] ?? ''))) || !empty($selectedDateFrom) || !empty($selectedDateTo) || !empty($selectedServices) || !empty($selectedAgents)): ?>
					<div class="alert alert-info">
						<i class="fa fa-info-circle me-2"></i>
						No customer activities found for the current filters.
					</div>
				<?php else: ?>
					<div class="alert alert-secondary">
						<i class="fa fa-magnifying-glass me-2"></i>
						Search by phone, name, service, reference, or note to view customer profile, transactions, and analytics.
					</div>
				<?php endif; ?>
		<div class="card border-0 shadow-sm mb-4">
			<div class="card-header border-0 bg-transparent d-flex justify-content-between align-items-center">
				<div>
					<h6 class="crm-section-title mb-0">Customer return analysis</h6>
					<small class="text-body-secondary">Repeat engagement and returning customer behavior across services</small>
				</div>
				<div class="badge bg-primary-subtle text-primary">Interactive</div>
			</div>
			<div class="card-body">
				<?php if(!empty($analytics['services'])): ?>
					<?php
						$serviceRows = $analytics['services'] ?? [];
						$customerRows = $analytics['customers'] ?? [];
						$agentRows = $analytics['agents'] ?? [];
						$totalServices = (int)($analytics['total_services'] ?? count($serviceRows));
						$totalReturningCustomers = (int)($analytics['total_returning_customers'] ?? count($customerRows));
						$totalHighPerformingAgents = (int)($analytics['total_high_performing_agents'] ?? count($agentRows));
					?>
					<div class="row g-3 mb-4">
						<div class="col-12 col-md-4">
							<div class="crm-metric-card">
								<div class="text-body-secondary small">Services tracked</div>
								<div class="display-6 fw-bold"><?=number_format($totalServices)?></div>
							</div>
						</div>
						<div class="col-12 col-md-4">
							<div class="crm-metric-card">
								<div class="text-body-secondary small">Returning customers</div>
								<div class="display-6 fw-bold"><?=number_format($totalReturningCustomers)?></div>
							</div>
						</div>
						<div class="col-12 col-md-4">
							<div class="crm-metric-card">
								<div class="text-body-secondary small">High-performing agents</div>
								<div class="display-6 fw-bold"><?=number_format($totalHighPerformingAgents)?></div>
							</div>
						</div>
					</div>
					<div class="accordion" id="crmAnalyticsAccordion">
						<?php foreach($serviceRows as $index => $row): ?>
							<?php $rateValue = (float)($row['rate'] ?? 0); $collapseId = 'crmAnalytic' . $index; ?>
							<div class="accordion-item">
								<h2 class="accordion-header" id="heading-<?=esc($collapseId)?>">
									<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?=esc($collapseId)?>" aria-expanded="false" aria-controls="<?=esc($collapseId)?>">
										<div class="d-flex justify-content-between align-items-center w-100 me-3">
											<div class="text-start">
												<div class="fw-semibold"><?=esc((string)($row['title'] ?? ''))?></div>
												<div class="small text-muted"><?=esc((string)($row['meta'] ?? ''))?></div>
											</div>
											<div class="text-end">
												<div class="fw-bold text-success"><?=number_format($rateValue, 1)?>%</div>
												<div class="progress" style="height: 6px; width: 140px;">
													<div class="progress-bar bg-success" role="progressbar" style="width: <?=min(100, max(0, $rateValue))?>%"></div>
												</div>
											</div>
										</div>
									</button>
								</h2>
								<div id="<?=esc($collapseId)?>" class="accordion-collapse collapse" aria-labelledby="heading-<?=esc($collapseId)?>">
									<div class="accordion-body">
										<ul class="list-group list-group-flush">
											<?php foreach(($row['details'] ?? []) as $detail): ?>
												<li class="list-group-item px-0">
													<div class="d-flex justify-content-between align-items-start">
														<div>
															<div class="fw-semibold"><?=esc((string)($detail['label'] ?? ''))?></div>
															<div class="small text-muted"><?=esc((string)($detail['meta'] ?? ''))?></div>
														</div>
														<div class="text-end">
															<div class=""><?=esc((string)($detail['value'] ?? ''))?></div>
															<div class="small text-muted"><?=number_format((float)($detail['rate'] ?? 0), 1)?>%</div>
															<div class="mt0 d-flex justify-content-end">
																<div class="progress" style="height:6px; width:140px;">
																	<div class="progress-bar" role="progressbar" style="width: <?=min(100, max(0, (float)($detail['rate'] ?? 0)))?>%;" aria-valuenow="<?=number_format((float)($detail['rate'] ?? 0), 1)?>" aria-valuemin="0" aria-valuemax="100"></div>
																</div>
															</div>
														</div>
													</div>
												</li>
											<?php endforeach; ?>
										</ul>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
						<div class="mt-4">
							<div class="row g-2">
								<div class="col-12 col-lg-8">
									<div class="crm-chart-card"><canvas id="crmServicesReturningRateChart" height="140"></canvas></div>
								</div>
								<div class="col-12 col-lg-4">
									<div class="crm-chart-card"><canvas id="crmReturningDoughnutChart" height="140"></canvas></div>
								</div>
							</div>
							<div class="mt-3">
								<div class="crm-chart-card"><canvas id="crmTimeSeriesChart" height="80"></canvas></div>
							</div>
							<div class="row g-2 mt-3">
								<div class="col-12 col-md-6">
									<div class="crm-chart-card">
										<h6 class="mb-2">Top Agents</h6>
										<canvas id="crmTopAgentsChart" height="160"></canvas>
										<div id="crmTopAgentsLegend" class="small text-muted mt-2"></div>
									</div>
								</div>
								<div class="col-12 col-md-6">
									<div class="crm-chart-card">
										<h6 class="mb-2">Top Customers</h6>
										<canvas id="crmTopCustomersChart" height="160"></canvas>
										<div id="crmTopCustomersLegend" class="small text-muted mt-2"></div>
									</div>
								</div>
							</div>
						</div>
						<?php
							// prepare service chart data and overall summary
							$serviceChartData = array_map(static fn($r) => ['label' => $r['title'] ?? '', 'rate' => (float)($r['rate'] ?? 0)], $serviceRows);
							$uniquePhones = [];
							if (!empty($analytics['transactions'])) {
								foreach ($analytics['transactions'] as $t) {
									$ph = \CashpickupCRM\normalizePhone($t['phone']) ?: trim((string)$t['phone']);
									if ($ph !== '') $uniquePhones[$ph] = true;
								}
							}
							$analyticsSummary = [
								'total_transactions' => (int)count($analytics['transactions'] ?? []),
								'total_unique_customers' => (int)count($uniquePhones),
								'total_returning_customers' => (int)($analytics['total_returning_customers'] ?? 0),
							];
							// prepare timeseries (date -> count, total_amount)
							$timeSeries = [];
							foreach (($analytics['transactions'] ?? []) as $t) {
								$dt = substr((string)($t['date'] ?? ''), 0, 10);
								if ($dt === '') continue;
								if (!isset($timeSeries[$dt])) $timeSeries[$dt] = ['count' => 0, 'amount' => 0.0];
								$timeSeries[$dt]['count']++;
								$timeSeries[$dt]['amount'] += (float)($t['amount'] ?? 0);
							}
							ksort($timeSeries);
							$timeSeriesData = [];
							foreach ($timeSeries as $d => $v) { $timeSeriesData[] = ['date' => $d, 'count' => (int)$v['count'], 'amount' => (float)$v['amount']]; }
							$crmPayload = ['services' => $serviceChartData, 'summary' => $analyticsSummary, 'analytics' => $analytics, 'timeSeries' => $timeSeriesData];
							?>
							<script type="application/json" id="crmAnalyticsJson"><?=json_encode($crmPayload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?></script>
				<?php else: ?>
					<div class="alert alert-secondary mb-0">No analytics are available for the current filters yet.</div>
				<?php endif; ?>
			</div>
		</div>
		</div>
				<?php
			};
			$renderGeneralLedger = function() use (&$generalLedger, &$generalLedgerMetrics, $limit, $pager) {
		$rows = is_array($generalLedger['rows'] ?? null) ? $generalLedger['rows'] : [];
		$totalRows = (int)($generalLedger['total_rows'] ?? 0);
		$totalPages = (int)ceil(((float)$totalRows) / max(1, (int)$limit));
		?>
		<details class="card border-0 shadow-sm mb-3 cashpickup-metrics-details">
			<summary class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
				<span>
					<span class="d-block small text-muted">General cashpickup balance</span>
					<span class="d-block fw-bold fs-5 <?=((float)($generalLedgerMetrics['balance'] ?? 0) < 0) ? 'text-danger' : ''?>">GH&#8373; <?=number_format((float)($generalLedgerMetrics['balance'] ?? 0), 2)?></span>
					<span class="d-block small text-muted">Credit payouts are deducted from the general cashpickup balance.</span>
				</span>
				<span class="small fw-semibold text-primary">Metrics <i class="fa fa-chevron-down ms-1 cashpickup-metrics-chevron" aria-hidden="true"></i></span>
			</summary>
			<div class="card-body">
		<div class="row g-2 mb-0">
			<div class="col-12 col-md-4">
				<div class="border rounded p-3 h-100">
					<div class="d-flex align-items-center justify-content-between">
						<div class="text-muted">Total Received</div>
						<i class="fa fa-hand-holding-dollar text-success"></i>
					</div>
					<div class="fw-bold">GH&#8373; <?=number_format((float)($generalLedgerMetrics['total_received'] ?? 0), 2)?></div>
				</div>
			</div>
			<div class="col-12 col-md-4">
				<div class="border rounded p-3 h-100">
					<div class="d-flex align-items-center justify-content-between">
						<div class="text-muted">Total Paid Out</div>
						<i class="fa fa-coins text-warning"></i>
					</div>
					<div class="fw-bold">GH&#8373; <?=number_format((float)($generalLedgerMetrics['total_paid_out'] ?? 0), 2)?></div>
				</div>
			</div>
			<div class="col-12 col-md-4">
				<div class="border rounded p-3 h-100">
					<div class="d-flex align-items-center justify-content-between">
						<div class="text-muted">Total Customers</div>
						<i class="fa fa-users text-info"></i>
					</div>
					<div class="fw-bold"><?=number_format((int)($generalLedgerMetrics['total_customers'] ?? 0))?></div>
				</div>
			</div>
		</div>
			</div>
		</details>
		<div class="small text-muted mb-2">Balance shows the accumulated cashpickup balance across all statement transactions at the time each row was posted.</div>
		<div class="card border-0 shadow-sm">
			<div class="table-responsive">
				<table class="table table-striped align-middle mb-0">
					<thead>
						<tr>
							<th>Transaction Date</th>
							<th>Transaction ID</th>
							<th>Description</th>
							<th class="text-end">Received</th>
							<th class="text-end">Payout</th>
							<th class="text-end">Balance</th>
						</tr>
					</thead>
					<tbody>
						<?php if (empty($rows)): ?>
							<tr><td colspan="6" class="text-center text-muted">No cashpickup statement rows found.</td></tr>
						<?php else: ?>
							<?php foreach ($rows as $row): ?>
								<?php
									$description = trim((string)($row->sender_details ?? ''));
									$phone = trim((string)($row->phone_number ?? ''));
									$source = trim((string)($row->source ?? ''));
									$trackingId = trim((string)($row->tracking_id ?? ''));
								?>
								<tr>
									<td><?=esc((string)get_date_time($row->date_created ?? ''))?></td>
									<td>
										<div class="fw-semibold"><?=esc((string)($row->id ?? ''))?></div>
										<?php if ($trackingId !== ''): ?>
											<div class="small text-muted">Tracking: <?=esc($trackingId)?></div>
										<?php endif; ?>
									</td>
									<td>
										<div class="fw-semibold"><?=esc($description !== '' ? $description : '---')?></div>
										<?php if ($phone !== ''): ?>
											<div class="small text-muted">Phone: <?=esc($phone)?></div>
										<?php endif; ?>
										<?php if ($source !== ''): ?>
											<div class="small text-muted">Source: <?=esc($source)?></div>
										<?php endif; ?>
									</td>
									<td class="text-end"><?=number_format((float)($row->received_amount ?? 0), 2)?></td>
									<td class="text-end"><?=number_format((float)($row->payout_amount ?? 0), 2)?></td>
									<td class="text-end <?=((float)($row->balance_amount ?? 0) < 0) ? 'text-danger fw-semibold' : ''?>"><?=number_format((float)($row->balance_amount ?? 0), 2)?></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>

		<div
			class="mt-3 js-cashpickup-customers-pager"
			data-total-rows="<?=esc((string)$totalRows)?>"
			data-limit="<?=esc((string)$limit)?>"
			data-page="<?=esc((string)($pager->page_number ?? 1))?>"
			data-total-pages="<?=esc((string)$totalPages)?>"
		></div>
		<?php
	};
?>

<?php if($isAjax){ 
	if ($activeTab === 'gl') {
		$renderGeneralLedger();
	} elseif ($activeTab === 'crm') {
		$renderCRM();
	} else {
		$renderResults();
	}
	return; 
} ?>

<style>
	.cashpickup-metrics-details>summary{list-style:none;cursor:pointer}
	.cashpickup-metrics-details>summary::-webkit-details-marker{display:none}
	.cashpickup-metrics-chevron{transition:transform .15s ease}
	.cashpickup-metrics-details[open] .cashpickup-metrics-chevron{transform:rotate(180deg)}
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
	.cashpickup-customers-header{gap:1rem;align-items:flex-start!important}
	.cashpickup-customers-header-actions{display:flex;justify-content:flex-end;gap:.5rem;flex-wrap:wrap}
	.cashpickup-customers-header-actions .btn{white-space:nowrap}
	#cashpickupCustomersTabs{flex-wrap:nowrap;overflow-x:auto;scrollbar-width:thin}
	#cashpickupCustomersTabs .nav-link{white-space:nowrap}
	.cashpickup-customer-mobile-list{display:flex;flex-direction:column;gap:.45rem}
	.cashpickup-customer-mobile-card{border:1px solid var(--bs-border-color);border-radius:.5rem;box-shadow:none!important}
	.cashpickup-customer-mobile-card .card-body{padding:.5rem .6rem}
	.cashpickup-customer-mobile-card.is-pending{border-left:3px solid var(--bs-warning)}
	.cashpickup-customer-mobile-card{transition:border-color .16s ease,box-shadow .16s ease,transform .16s ease}
	.cashpickup-customer-table tbody tr{transition:background-color .16s ease}
	.cashpickup-customer-mobile-card.is-pressed,
	.cashpickup-customer-mobile-card:focus-within{
		border-top-color:rgba(var(--bs-primary-rgb),.55);
		border-right-color:rgba(var(--bs-primary-rgb),.55);
		border-bottom-color:rgba(var(--bs-primary-rgb),.55);
		box-shadow:0 .25rem .8rem rgba(15,23,42,.08)!important;
		transform:translateY(-1px);
	}
	@media (hover:hover) and (pointer:fine){
		.cashpickup-customer-table.table-hover>tbody>tr:hover>*{--bs-table-bg-state:rgba(var(--bs-primary-rgb),.075)}
		.cashpickup-customer-mobile-card:hover{border-top-color:rgba(var(--bs-primary-rgb),.55);border-right-color:rgba(var(--bs-primary-rgb),.55);border-bottom-color:rgba(var(--bs-primary-rgb),.55);box-shadow:0 .25rem .8rem rgba(15,23,42,.08)!important;transform:translateY(-1px)}
	}
	@media (prefers-reduced-motion:reduce){
		.cashpickup-customer-table tbody tr,
		.cashpickup-customer-mobile-card,
		.cashpickup-metrics-chevron{transition:none}
		.cashpickup-customer-mobile-card:hover,
		.cashpickup-customer-mobile-card.is-pressed{transform:none}
	}
	.cashpickup-customer-mobile-heading{min-width:0}
	.cashpickup-customer-mobile-heading .fw-semibold{font-size:.92rem;line-height:1.2}
	.cashpickup-customer-mobile-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.2rem .55rem}
	.cashpickup-customer-mobile-value{min-width:0;overflow-wrap:anywhere;padding:.1rem 0;font-size:.82rem;line-height:1.25}
	.cashpickup-customer-mobile-value .small{font-size:.68rem}
	.cashpickup-customer-mobile-actions{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:.2rem}
	.cashpickup-customer-mobile-actions form{display:flex;margin:0}
	.cashpickup-customer-mobile-actions .btn{width:30px;height:30px;min-width:30px;min-height:30px;padding:.1rem;white-space:nowrap;border-radius:.45rem}
	.cashpickup-customer-mobile-footer{display:flex;flex-direction:column;align-items:stretch;gap:.6rem}
	.cashpickup-customer-mobile-footer .fw-bold{font-size:.9rem}
	.cashpickup-customer-summary-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.35rem .75rem}
	.cashpickup-customer-summary-grid>div{min-width:0}
	.cashpickup-financial-pair>div{display:flex;justify-content:space-between;gap:.75rem;white-space:nowrap}
	.cashpickup-financial-pair>div+div{margin-top:.2rem}
	.cashpickup-financial-pair span{font-size:.72rem;color:var(--bs-secondary-color);font-weight:400}
	.cashpickup-filter-panel{border:1px solid var(--bs-border-color);border-radius:.6rem;padding:.1rem .75rem}
	.cashpickup-filter-panel>summary{cursor:pointer;list-style:none;padding:.55rem 0;font-weight:600}
	.cashpickup-filter-panel>summary::-webkit-details-marker{display:none}
	.cashpickup-filter-panel[open] .cashpickup-filter-chevron{transform:rotate(180deg)}
	.cashpickup-filter-chevron{transition:transform .16s ease}
	.cashpickup-crm .crm-hero{background:linear-gradient(135deg,rgba(var(--bs-primary-rgb),.1),rgba(var(--bs-info-rgb),.06));border:1px solid rgba(var(--bs-primary-rgb),.15);border-radius:1rem}
	.cashpickup-crm .crm-section-title{font-weight:700;letter-spacing:-.02em}
	.cashpickup-crm .crm-metric-card{height:100%;border:1px solid var(--bs-border-color);border-radius:.85rem;padding:1rem;background:var(--bs-body-bg);box-shadow:0 .2rem .7rem rgba(15,23,42,.04)}
	.cashpickup-crm .crm-service-card{border:1px solid var(--bs-border-color);border-radius:.85rem;box-shadow:0 .2rem .7rem rgba(15,23,42,.04);transition:transform .15s ease,box-shadow .15s ease}
	.cashpickup-crm .crm-service-card:hover{transform:translateY(-2px);box-shadow:0 .5rem 1rem rgba(15,23,42,.08)}
	.cashpickup-crm .crm-chart-card{border:1px solid var(--bs-border-color);border-radius:.85rem;padding:1rem;height:100%;background:var(--bs-body-bg)}
	.cashpickup-crm .crm-chart-card canvas{max-width:100%}
	.cashpickup-crm .crm-advanced-panel{border:1px solid var(--bs-border-color);border-radius:.85rem;background:var(--bs-tertiary-bg)}
	.cashpickup-crm .crm-checkbox-list{max-height:180px;overflow:auto}
	.cashpickup-crm .table thead th{font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;color:var(--bs-secondary-color);white-space:nowrap}
	@media(max-width:767.98px){
		.cashpickup-crm .crm-hero{border-radius:.75rem}
		.cashpickup-crm .crm-metric-card{padding:.8rem}
	}
	@media(max-width:767.98px){
		.cashpickup-customers-header{flex-direction:column}
		.cashpickup-customers-header-actions{display:flex;justify-content:flex-start;width:100%}
		.cashpickup-customers-header-actions .btn{width:auto;flex:0 1 auto;padding:.35rem .55rem}
	}
</style>

<div class="d-flex align-items-center justify-content-between mb-3 cashpickup-customers-header">
	<div>
		<h4 class="mb-0" id="cashpickupCustomersHeading" data-default-text="<?=esc($isNonPrivileged ? 'Customers' : 'Customer Ledger')?>"><?=$activeTab === 'new' ? 'New customer approvals' : ($isNonPrivileged ? 'Customers' : 'Customer Ledger')?></h4>
		<small class="text-muted" id="cashpickupCustomersDescription" data-default-text="<?=esc($canWithdrawCustomerPayout ? 'All customer accounts; agents can withdraw from available balances.' : ($isNonPrivileged ? 'All customer accounts.' : 'Cash pickup customer profiles and transaction history'))?>"><?=$activeTab === 'new' ? 'Review new registrations and approve or deny access.' : ($canWithdrawCustomerPayout ? 'All customer accounts; agents can withdraw from available balances.' : ($isNonPrivileged ? 'All customer accounts.' : 'Cash pickup customer profiles and transaction history'))?></small>
		<div id="crmFilterSummary" class="small text-body-secondary mt-1"></div>
	</div>
	<div class="cashpickup-customers-header-actions">
			<a class="btn btn-outline-secondary" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>"> <i class="fa-solid fa-arrow-left"></i> Cash Pickup</a>
			<?php if(user_can('add_cashpickup') || user_can('edit_cashpickup')): ?>
				<a class="btn btn-outline-primary" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/customer_new"> <i class="fa-solid fa-plus"></i> Register Customer</a>
			<?php endif; ?>
			<?php if(user_can('add_cashpickup')): ?>
			<a class="btn btn-danger" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/add">
				<i class="fa fa-plus"></i> New Cashpickup
			</a>
			<?php endif; ?>
			<?php if(!$isNonPrivileged): ?>
			<a class="btn btn-outline-primary" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/customers?sync=1"> <i class="fa-solid fa-sync"></i> Sync Ledger</a>
			<?php $exportResource = $activeTab === 'gl' ? 'general_ledger' : ($activeTab === 'crm' ? 'crm' : 'customers'); ?>
			<a class="btn btn-outline-danger" id="customersExportPdf" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/export?<?=http_build_query(['resource' => $exportResource, 'format' => 'pdf', 'find' => $find, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'service' => $service, 'agent_id' => $agentId])?>"> <i class="fa-solid fa-file-pdf"></i> PDF</a>
			<a class="btn btn-outline-success" id="customersExportXlsx" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/export?<?=http_build_query(['resource' => $exportResource, 'format' => 'xlsx', 'find' => $find, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'service' => $service, 'agent_id' => $agentId])?>"> <i class="fa-solid fa-file-excel"></i> Excel</a>
			<a class="btn btn-outline-primary" id="customersExportCsv" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/export?<?=http_build_query(['resource' => $exportResource, 'format' => 'csv', 'find' => $find, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'service' => $service, 'agent_id' => $agentId])?>"> <i class="fa-solid fa-file-csv"></i> CSV</a>
			<?php endif; ?>
	</div>
</div>

<ul class="nav nav-tabs mb-3" id="cashpickupCustomersTabs" role="tablist">
	<li class="nav-item" role="presentation">
		<button class="nav-link <?=$activeTab === 'customers' ? 'active' : ''?>" type="button" data-cashpickup-tab="customers"><?=$isNonPrivileged ? 'Customers' : 'Customers Ledger'?></button>
	</li>
	<?php if($canReviewCustomers): ?>
	<li class="nav-item" role="presentation">
		<button class="nav-link <?=$activeTab === 'new' ? 'active' : ''?>" type="button" data-cashpickup-tab="new">
			New customers
			<?php if($newCustomerCount > 0): ?>
				<span class="badge rounded-pill text-bg-danger ms-1"><?=$newCustomerCount?></span>
			<?php endif; ?>
		</button>
	</li>
	<?php endif; ?>
	<?php if(!$isNonPrivileged): ?>
	<li class="nav-item" role="presentation">
		<button class="nav-link <?=$activeTab === 'gl' ? 'active' : ''?>" type="button" data-cashpickup-tab="gl">General Ledger (GL)</button>
	</li>
	<?php endif; ?>
	<li class="nav-item" role="presentation">
		<button class="nav-link <?=$activeTab === 'crm' ? 'active' : ''?>" type="button" data-cashpickup-tab="crm">CRM</button>
	</li>
</ul>

<?php if($autoSynced && $syncCount > 0): ?>
	<div class="alert alert-success">Ledger sync completed. Imported/updated <?=number_format((int)$syncCount)?> customer record(s).</div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-3" id="cashpickupGlobalFilters" <?=$activeTab === 'crm' ? 'style="display:none"' : ''?>>
	<div class="card-body">
		<form method="get" id="cashpickupCustomersFilterForm">
			<input type="hidden" name="tab" id="cashpickupCustomersTab" value="<?=esc($activeTab)?>">
			<input type="hidden" name="service" id="cashpickupCustomersHiddenService" value="<?=esc((string)($_GET['service'] ?? ''))?>">
			<input type="hidden" name="agent_id" id="cashpickupCustomersHiddenAgentId" value="<?=esc((string)($_GET['agent_id'] ?? ''))?>">
			<div class="row g-2 align-items-end">
				<div class="col-12 col-md-8">
					<label class="form-label mb-1" for="cashpickupCustomersFind">Search customers</label>
					<input type="text" name="find" value="<?=esc($find)?>" class="form-control" placeholder="<?=$activeTab === 'gl' ? 'Transaction ID, name, phone, source' : 'Name, phone, ID number'?>" id="cashpickupCustomersFind">
				</div>
				<div class="col-12 col-md-4 d-flex gap-2">
					<button class="btn btn-primary flex-fill" type="submit"><i class="fa fa-magnifying-glass me-1"></i>Search</button>
					<a class="btn btn-outline-secondary flex-fill" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/customers" title="Clear filters and show all customers"><i class="fa fa-rotate-left me-1"></i>Clear</a>
				</div>
			</div>
			<details class="cashpickup-filter-panel mt-3" <?=$activeAdvancedFilterCount > 0 ? 'open' : ''?>>
				<summary class="d-flex align-items-center justify-content-between gap-2">
					<span><i class="fa fa-sliders me-2"></i>Filters and display</span>
					<span class="d-flex align-items-center gap-2">
						<span class="badge rounded-pill <?=$activeAdvancedFilterCount > 0 ? 'text-bg-primary' : 'text-bg-secondary'?>">
							<?=$activeAdvancedFilterCount > 0 ? $activeAdvancedFilterCount . ' active' : 'Optional'?>
						</span>
						<i class="fa fa-chevron-down cashpickup-filter-chevron"></i>
					</span>
				</summary>
				<div class="row g-2 pt-3">
					<div class="col-6 col-md-3">
						<label class="form-label small" for="cashpickupCustomersHiddenDateFrom">Created / activity from</label>
						<input type="date" name="date_from" id="cashpickupCustomersHiddenDateFrom" value="<?=esc($dateFrom)?>" class="form-control">
					</div>
					<div class="col-6 col-md-3">
						<label class="form-label small" for="cashpickupCustomersHiddenDateTo">Created / activity to</label>
						<input type="date" name="date_to" id="cashpickupCustomersHiddenDateTo" value="<?=esc($dateTo)?>" class="form-control">
					</div>
					<?php if(in_array($activeTab, ['customers', 'new'], true)): ?>
						<div class="col-6 col-md-3">
							<label class="form-label small">Customer status</label>
							<select name="status" class="form-select">
								<option value="" <?=$statusFilter===''?'selected':''?>>All statuses</option>
								<option value="approved" <?=$statusFilter==='approved'?'selected':''?>>Approved</option>
								<option value="pending" <?=$statusFilter==='pending'?'selected':''?>>Pending</option>
								<option value="denied" <?=$statusFilter==='denied'?'selected':''?>>Denied</option>
							</select>
						</div>
						<div class="col-6 col-md-3">
							<label class="form-label small">Payout status</label>
							<select name="payout_status" class="form-select">
								<option value="" <?=$payoutStatusFilter===''?'selected':''?>>Any payout</option>
								<option value="pending" <?=$payoutStatusFilter==='pending'?'selected':''?>>Pending payout</option>
								<option value="approved" <?=$payoutStatusFilter==='approved'?'selected':''?>>Approved payout</option>
								<option value="denied" <?=$payoutStatusFilter==='denied'?'selected':''?>>Denied payout</option>
								<option value="reversed" <?=$payoutStatusFilter==='reversed'?'selected':''?>>Reversed payout</option>
								<option value="cancelled" <?=$payoutStatusFilter==='cancelled'?'selected':''?>>Cancelled payout</option>
								<option value="none" <?=$payoutStatusFilter==='none'?'selected':''?>>No payouts</option>
							</select>
						</div>
						<div class="col-6 col-md-3">
							<label class="form-label small">Attention</label>
							<select name="attention" class="form-select">
								<option value="" <?=$attentionFilter===''?'selected':''?>>All customers</option>
								<option value="1" <?=$attentionFilter==='1'?'selected':''?>><?=$canReviewCustomers ? 'Needs attention' : 'My assigned tasks'?></option>
							</select>
						</div>
					<?php endif; ?>
					<div class="col-6 col-md-3">
						<label class="form-label small">Customers per page</label>
						<select name="limit" class="form-select" id="cashpickupCustomersLimit">
							<?php foreach([10,25,30,50,100,200] as $l): ?>
								<option value="<?=$l?>" <?=$limit===$l?'selected':''?>><?=$l?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-12 d-flex justify-content-end">
						<button class="btn btn-primary" type="submit"><i class="fa fa-filter me-1"></i>Apply filters</button>
					</div>
				</div>
			</details>
		</form>
		<?php if(in_array($activeTab, ['customers', 'new'], true)): ?>
			<div class="small text-body-secondary mt-2">
				<?php if($canReviewCustomers): ?>
					You can see all attention items. Staff see only attention tasks assigned to them.
				<?php else: ?>
					Customer accounts remain visible; attention and payout actions appear only for tasks assigned to you.
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</div>

<div id="cashpickupCustomersContainer" data-ajax-endpoint="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/customers_ajax">
	<?php
		if ($activeTab === 'gl') {
			$renderGeneralLedger();
		} elseif ($activeTab === 'crm') {
			$renderCRM();
		} else {
			$renderResults();
		}
	?>
</div>

<script>
	(function(){
		let pressedCard = null;
		document.addEventListener('pointerdown', function(event){
			const card = event.target instanceof Element ? event.target.closest('.cashpickup-customer-mobile-card') : null;
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

		const container = document.getElementById('cashpickupCustomersContainer');
		const form = document.getElementById('cashpickupCustomersFilterForm');
		const tabInput = document.getElementById('cashpickupCustomersTab');
		if(!container || !form) return;

		const endpoint = container.getAttribute('data-ajax-endpoint');
		if(!endpoint) return;
		let activeFilterRequest = null;
		let filterRequestSequence = 0;
		let searchTimer = null;

		const getActiveTab = () => {
			return tabInput && tabInput.value ? tabInput.value : 'customers';
		};

		const setActiveTabUi = (tab) => {
			document.querySelectorAll('[data-cashpickup-tab]').forEach(btn => {
				btn.classList.toggle('active', btn.getAttribute('data-cashpickup-tab') === tab);
			});
			const heading = document.getElementById('cashpickupCustomersHeading');
			const description = document.getElementById('cashpickupCustomersDescription');
			if(heading){
				heading.textContent = tab === 'new' ? 'New customer approvals' : (heading.getAttribute('data-default-text') || 'Customers');
			}
			if(description){
				description.textContent = tab === 'new'
					? 'Review new registrations and approve or deny access.'
					: (description.getAttribute('data-default-text') || '');
			}
			const globalFilters = document.getElementById('cashpickupGlobalFilters');
			if(globalFilters) globalFilters.style.display = tab === 'crm' ? 'none' : '';
			const findInput = document.getElementById('cashpickupCustomersFind');
			if(findInput){
				findInput.placeholder = tab === 'gl'
					? 'Transaction id, tracking, name, phone, source'
					: (tab === 'crm' ? 'Phone, name, service, reference or note' : 'Name, phone, ID number');
			}
		};

		const syncUrl = (payload) => {
			if(!window.history || typeof window.history.replaceState !== 'function') return;
			const params = new URLSearchParams();
			Object.keys(payload || {}).forEach(k => {
				if(payload[k] !== undefined && payload[k] !== null && payload[k] !== ''){
					params.set(k, String(payload[k]));
				}
			});
			const nextUrl = `${window.location.pathname}${params.toString() ? ('?' + params.toString()) : ''}`;
			window.history.replaceState({}, '', nextUrl);
		};

		const getCheckedValues = (selector) => {
			return Array.from(document.querySelectorAll(selector))
				.filter(el => el.checked && el.value.trim() !== '')
				.map(el => el.value.trim());
		};

		const syncAdvancedFilters = () => {
			const map = [
				['crmDateFrom', 'cashpickupCustomersHiddenDateFrom'],
				['crmDateTo', 'cashpickupCustomersHiddenDateTo'],
			];
			map.forEach(([sourceId, targetId]) => {
				const source = document.getElementById(sourceId);
				const target = document.getElementById(targetId);
				if(source && target) target.value = source.value || '';
			});
			const serviceTarget = document.getElementById('cashpickupCustomersHiddenService');
			const agentTarget = document.getElementById('cashpickupCustomersHiddenAgentId');
			if(serviceTarget) serviceTarget.value = getCheckedValues('input.crmServiceCheckbox').join(',');
			if(agentTarget) agentTarget.value = getCheckedValues('input.crmAgentCheckbox').join(',');
		};

		const currentFilters = () => {
			syncAdvancedFilters();
			const fd = new FormData(form);
			const out = {};
			for(const [k,v] of fd.entries()){
				if(v === null || v === '') continue;
				out[k] = v;
			}
			return out;
		};

		const load = async (payload) => {
			if(activeFilterRequest){
				activeFilterRequest.abort();
			}
			const controller = new AbortController();
			activeFilterRequest = controller;
			const sequence = ++filterRequestSequence;
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
					body: body.toString(),
					signal: controller.signal
				});
				if(!res.ok){
					throw new Error('Customer list request failed with status ' + res.status);
				}
				const html = await res.text();
				if(sequence !== filterRequestSequence) return;
				container.innerHTML = html;
				renderPager();
				bindPagerLinks();
				bindCrmSearch();
				updateExportLinks();
				try { updateHeaderFilterSummary(); } catch(e){}
				try { renderCrmCharts(); } catch (e) { /* ignore chart errors */ }
				syncUrl(payload);
			}catch(error){
				if(error.name === 'AbortError') return;
				if(sequence === filterRequestSequence){
					console.error('Unable to apply Cashpickup filters', error);
					container.innerHTML = '<div class="alert alert-danger" role="alert">Unable to load the filtered list. Please try again.</div>';
				}
			}finally{
				if(sequence === filterRequestSequence){
					activeFilterRequest = null;
					container.style.opacity = '1';
				}
			}
		};

		const renderPager = () => {
			const pager = container.querySelector('.js-cashpickup-customers-pager');
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
			const pager = container.querySelector('.js-cashpickup-customers-pager');
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

		const renderCrmCharts = () => {
			try{
				var jsonEl = document.getElementById('crmAnalyticsJson');
				var payload = null;
				if (jsonEl && jsonEl.textContent) {
					try { payload = JSON.parse(jsonEl.textContent); window.__cashpickupCrmChartData = payload; } catch(e) { payload = window.__cashpickupCrmChartData || null; }
				} else {
					payload = window.__cashpickupCrmChartData || null;
				}
				if(!payload) return;
				const servicesData = Array.isArray(payload.services) ? payload.services : [];
				const summary = payload.summary || null;
				const timeSeries = Array.isArray(payload.timeSeries) ? payload.timeSeries : [];
				const canvas = document.getElementById('crmServicesReturningRateChart');
				if(!canvas) return;
				const data = servicesData;
				if(!data || !data.length) return;
				const labels = data.map(d => d.label || '');
				const values = data.map(d => Number(d.rate || 0));
				const draw = () => {
					if (!canvas) return;
					const ctx = canvas.getContext('2d');
					if (!ctx) return;
					if (canvas._chartInstance) {
						canvas._chartInstance.destroy();
					}
					canvas._chartInstance = new Chart(ctx, {
						type: 'bar',
						data: {
							labels: labels,
							datasets: [{
								label: 'Returning rate (%)',
								data: values,
								backgroundColor: 'rgba(54,162,235,0.6)',
								borderColor: 'rgba(54,162,235,1)',
								borderWidth: 1
							}]
						},
						options: {
							responsive: true,
							scales: { y: { beginAtZero: true, max: 100 } }
						}
					});
				};
				const ensureChartAndDraw = () => {
					const doAllDraws = () => {
						try { draw(); drawDoughnut(); drawTopAgentsGlobal(); drawTopCustomersGlobal(); drawTimeSeries(); } catch(e){}
					};
					if (typeof Chart !== 'undefined') {
						doAllDraws();
						return;
					}
					let waited = 0;
					const iv = setInterval(() => {
						if (typeof Chart !== 'undefined') {
							clearInterval(iv);
							doAllDraws();
							return;
						}
						waited += 100;
						if (waited >= 2000) {
							clearInterval(iv);
							const s = document.createElement('script');
							s.src = 'https://cdn.jsdelivr.net/npm/chart.js';
							s.onload = function(){ doAllDraws(); };
							document.head.appendChild(s);
						}
					}, 100);
				};
				ensureChartAndDraw();

				function drawDoughnut(){
					try{
						const canvas2 = document.getElementById('crmReturningDoughnutChart');
						if(!canvas2) return;
						const ctx2 = canvas2.getContext('2d');
						if(!ctx2) return;
						if (canvas2._chartInstance) canvas2._chartInstance.destroy();
						const returning = summary && summary.total_returning_customers ? Number(summary.total_returning_customers) : 0;
						const unique = summary && summary.total_unique_customers ? Number(summary.total_unique_customers) : 0;
						const firstTime = Math.max(0, unique - returning);
						canvas2._chartInstance = new Chart(ctx2, {
							type: 'doughnut',
							data: {
								labels: ['Returning','First time'],
								datasets: [{ data: [returning, firstTime], backgroundColor: ['#10b981','#64748b'] }]
							},
							options: { plugins: { tooltip: { callbacks: { label: function(ctx){ const v = ctx.raw||0; const tot = (returning + firstTime) || 1; return ctx.label + ': ' + v + ' (' + (Math.round((v/tot)*10000)/100) + '%)'; } } }, legend: { display: false } } }
						});
					}catch(e){console.error('doughnut error', e)}
				}


				function drawTopAgentsGlobal(){
					try{
						const agents = payload.analytics && Array.isArray(payload.analytics.agents) ? payload.analytics.agents : [];
						const canvasA = document.getElementById('crmTopAgentsChart');
						if(!canvasA || !agents.length) return;
						const ctxA = canvasA.getContext('2d');
						if(!ctxA) return;
						if (canvasA._chartInstance) canvasA._chartInstance.destroy();
						const labels = agents.map(a => a.title || a.agent_name || '');
						const values = agents.map(a => Number(a.rate || 0));
						canvasA._chartInstance = new Chart(ctxA, { type: 'bar', data: { labels: labels, datasets: [{ label: 'Returning rate (%)', data: values, backgroundColor: 'rgba(59,130,246,0.7)' }] }, options: { responsive:true, indexAxis: 'y', scales: { x: { beginAtZero:true, max:100 } } } });
					}catch(e){console.error('top agents global error', e)}
				}

				function drawTopCustomersGlobal(){
					try{
						const customers = payload.analytics && Array.isArray(payload.analytics.customers) ? payload.analytics.customers : [];
						const totalCustomerVisits = payload.analytics && payload.analytics.total_customer_visits ? Number(payload.analytics.total_customer_visits) : 0;
						const canvasC = document.getElementById('crmTopCustomersChart');
						if(!canvasC || !customers.length) return;
						const ctxC = canvasC.getContext('2d');
						if(!ctxC) return;
						if (canvasC._chartInstance) canvasC._chartInstance.destroy();
						const labels = customers.map(c => c.title || c.customer_name || '');
						const values = customers.map(c => Number(c.rate || 0));
						canvasC._chartInstance = new Chart(ctxC, {
							type: 'bar',
							data: {
								labels: labels,
								datasets: [{ label: 'Returning rate (%)', data: values, backgroundColor: 'rgba(16,185,129,0.7)' }]
							},
							options: {
								responsive:true,
								indexAxis: 'y',
								scales: { x: { beginAtZero:true, max:100 } },
								plugins: {
									tooltip: {
										callbacks: {
											afterLabel: function(ctx){
													const c = customers[ctx.dataIndex];
													if (!c) return '';
													const visits = Number(c.visit_count || 0);
													const pct = Number(c.visit_percentage || 0);
													const rate = Number(c.rate || 0);
													const returns = Number(c.return_count || 0);
													return `Visits: ${visits} | Returns: ${returns} | Visit%: ${pct}% | Returning rate: ${rate}%`;
											}
										}
									}
								}
							}
						});
							}catch(e){console.error('top customers global error', e)}
								// render legend for top customers
								try{
									const legendC = document.getElementById('crmTopCustomersLegend');
									if(legendC && Array.isArray(customers) && customers.length){
										legendC.innerHTML = customers.slice(0,8).map(c => {
											const name = c.title || c.customer_name || '';
											const visits = Number(c.visit_count || 0);
											const pct = Number(c.visit_percentage || 0);
											const returns = Number(c.return_count || 0);
											return `<div>${escapeHtml(name)} — ${visits} visits — ${returns} returns — ${pct}% of visits</div>`;
										}).join('');
									}
								}catch(e){/* ignore legend errors */}
							}

					function drawTimeSeries(){
						try{
							const canvas3 = document.getElementById('crmTimeSeriesChart');
							if(!canvas3) return;
							const ctx3 = canvas3.getContext('2d');
							if(!ctx3) return;
							if (canvas3._chartInstance) canvas3._chartInstance.destroy();
							if(!timeSeries || !timeSeries.length) return;
							const labels = timeSeries.map(r => r.date);
							const counts = timeSeries.map(r => Number(r.count || 0));
							const amounts = timeSeries.map(r => Number(r.amount || 0));
							canvas3._chartInstance = new Chart(ctx3, {
								type: 'line',
								data: {
									labels: labels,
									datasets: [
										{ label: 'Transactions', data: counts, borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,0.08)', tension: 0.2, fill: true, yAxisID: 'y' },
										{ label: 'Amount (GH₵)', data: amounts, borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.06)', tension: 0.2, fill: false, yAxisID: 'y1' }
									]
								},
								options: {
									responsive: true,
									interaction: { mode: 'index', intersect: false },
									stacked: false,
									scales: {
										y: { type: 'linear', display: true, position: 'left' },
										y1: { type: 'linear', display: true, position: 'right', grid: { drawOnChartArea: false } }
									}
								}
							});
						}catch(e){console.error('timeseries error', e)}
					}

					// chart drawing is handled by ensureChartAndDraw()
				}catch(e){console.error('CRM chart error', e)}
			};

			const escapeHtml = (s) => String(s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

	const updateHeaderFilterSummary = () => {
		try{
			const out = [];
			if(f.service){
				const services = String(f.service).split(',').filter(Boolean);
				const labels = services.map(value => {
					const checkbox = document.querySelector('input.crmServiceCheckbox[value="' + CSS.escape(value) + '"]');
					return checkbox ? checkbox.closest('.form-check')?.querySelector('.form-check-label')?.textContent.trim() : value;
				}).filter(Boolean);
				if(labels.length) out.push('Service: ' + labels.join(', '));
			}
			if(f.agent_id){
				const agents = String(f.agent_id).split(',').filter(Boolean);
				const labels = agents.map(value => {
					const checkbox = document.querySelector('input.crmAgentCheckbox[value="' + CSS.escape(value) + '"]');
					return checkbox ? checkbox.closest('.form-check')?.querySelector('.form-check-label')?.textContent.trim() : value;
				}).filter(Boolean);
				if(labels.length) out.push('Agent: ' + labels.join(', '));
			}
			const el = document.getElementById('crmFilterSummary');
			if(el) el.textContent = out.length ? ( (el.dataset.prefix ? el.dataset.prefix + ' ' : '') + out.join(' | ') ) : 'No filters applied';
		}catch(e){/* ignore */}
	};

	const submitFilters = () => {
		syncAdvancedFilters();
		const payload = currentFilters();
		payload.page = '1';
		updateExportLinks();
		load(payload);
	};

	const bindAutoFilters = () => {
		const findInput = document.getElementById('cashpickupCustomersFind');
		if(findInput){
			findInput.addEventListener('input', function(){
				if(searchTimer) clearTimeout(searchTimer);
				searchTimer = setTimeout(submitFilters, 350);
			});
		}
		form.querySelectorAll('select, input[type="date"]').forEach(control => {
			control.addEventListener('change', submitFilters);
		});
	};
	bindAutoFilters();

		let crmSearchTimer = null;
		const bindCrmSearch = () => {
			const crmPhoneSearch = document.getElementById('crmPhoneSearch');
			const findInput = document.getElementById('cashpickupCustomersFind');
			const crmDateFrom = document.getElementById('crmDateFrom');
			const crmDateTo = document.getElementById('crmDateTo');
			const crmApplyFiltersBtn = document.getElementById('crmApplyFiltersBtn');
			const crmServiceCheckboxes = Array.from(document.querySelectorAll('input.crmServiceCheckbox'));
			const crmAgentCheckboxes = Array.from(document.querySelectorAll('input.crmAgentCheckbox'));
			if(!crmPhoneSearch || !findInput || (crmPhoneSearch.dataset && crmPhoneSearch.dataset.bound)) return;
			crmPhoneSearch.dataset.bound = '1';
			crmPhoneSearch.addEventListener('input', function(){
				findInput.value = crmPhoneSearch.value;
				updateExportLinks();
				if(crmSearchTimer) clearTimeout(crmSearchTimer);
				crmSearchTimer = setTimeout(submitFilters, 350);
			});
			crmPhoneSearch.addEventListener('keydown', function(e){
				if(e.key !== 'Enter') return;
				e.preventDefault();
				findInput.value = crmPhoneSearch.value;
				submitFilters();
			});
			[crmDateFrom, crmDateTo].forEach(el => {
				if(!el) return;
				el.addEventListener('change', function(){
					syncAdvancedFilters();
					submitFilters();
				});
			});
			[...crmServiceCheckboxes, ...crmAgentCheckboxes].forEach(checkbox => {
				checkbox.addEventListener('change', function(){
					syncAdvancedFilters();
					updateExportLinks();
					submitFilters();
				});
			});
			if(crmApplyFiltersBtn){
				crmApplyFiltersBtn.addEventListener('click', function(){
					syncAdvancedFilters();
					submitFilters();
				});
			}
		};
		bindCrmSearch();

		const updateExportLinks = () => {
			const findInput = document.getElementById('cashpickupCustomersFind');
			const findVal = findInput && findInput.value ? findInput.value.trim() : '';
			const dateFrom = document.getElementById('cashpickupCustomersHiddenDateFrom')?.value || '';
			const dateTo = document.getElementById('cashpickupCustomersHiddenDateTo')?.value || '';
			const service = document.getElementById('cashpickupCustomersHiddenService')?.value || '';
			const agentId = document.getElementById('cashpickupCustomersHiddenAgentId')?.value || '';
			const activeTab = getActiveTab();
			const base = `<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/export`;
			const build = (format) => {
				const params = new URLSearchParams();
				const exportResource = activeTab === 'gl' ? 'general_ledger' : (activeTab === 'crm' ? 'crm' : 'customers');
				params.set('resource', exportResource);
				params.set('format', format);
				if(findVal !== '') params.set('find', findVal);
				if(dateFrom !== '') params.set('date_from', dateFrom);
				if(dateTo !== '') params.set('date_to', dateTo);
				if(service !== '') params.set('service', service);
				if(agentId !== '') params.set('agent_id', agentId);
				return `${base}?${params.toString()}`;
			};
			const pdf = document.getElementById('customersExportPdf');
			const xlsx = document.getElementById('customersExportXlsx');
			const csv = document.getElementById('customersExportCsv');
			[pdf, xlsx, csv].forEach(link => {
				if(!link) return;
				link.classList.remove('disabled');
				link.setAttribute('aria-disabled', 'false');
				link.title = '';
			});
			if(pdf) pdf.href = build('pdf');
			if(xlsx) xlsx.href = build('xlsx');
			if(csv) csv.href = build('csv');
		};
		updateExportLinks();
		// render charts and header summary on initial load
		try { updateHeaderFilterSummary(); } catch (e) {}
		try { renderCrmCharts(); } catch (e) {}
		// charts (including top lists) are rendered via renderCrmCharts()
		document.querySelectorAll('[data-cashpickup-tab]').forEach(btn => {
			btn.addEventListener('click', function(){
				const nextTab = btn.getAttribute('data-cashpickup-tab') || 'customers';
				if (nextTab === 'new') {
					const payload = currentFilters();
					payload.tab = 'new';
					payload.page = '1';
					delete payload.status;
					delete payload.payout_status;
					delete payload.attention;
					const params = new URLSearchParams(payload);
					window.location.assign(`${window.location.pathname}?${params.toString()}`);
					return;
				}
				tabInput.value = nextTab;
				setActiveTabUi(nextTab);
				syncAdvancedFilters();
				const payload = currentFilters();
				payload.tab = nextTab;
				payload.page = '1';
				updateExportLinks();
				load(payload);
			});
		});
		document.addEventListener('submit', function(e){
			if(e.target !== form) return;
			e.preventDefault();
			submitFilters();
		});
		document.getElementById('cashpickupCustomersFind') && document.getElementById('cashpickupCustomersFind').addEventListener('input', function(){
			updateExportLinks();
		});
		document.getElementById('cashpickupCustomersLimit') && document.getElementById('cashpickupCustomersLimit').addEventListener('change', updateExportLinks);

		setActiveTabUi(getActiveTab());
		renderPager();
		bindPagerLinks();
	})();
</script>

<?php
endif;
?>
