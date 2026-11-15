<?php
defined('ROOT') or die('Direct script access denied');

$generalBalance = [
	'left' => [],
	'right' => [],
	'left_total' => 0.0,
	'right_total' => 0.0,
	'variance' => 0.0,
	'cfa_rate' => 0.0,
];
$generalBalanceDetails = ['left' => [], 'right' => []];
$generalBalanceAdjustments = [];
$generalBalanceVarianceExplanation = null;
$generalBalanceError = '';
$generalBalanceExport = !empty($generalBalanceExport);
$generalBalanceStartDate = '';
$generalBalanceEndDate = '';
$generalBalanceBranchId = 0;
$generalBalanceBranches = [];
$generalBalanceSelectedBranchName = '';
$generalBalanceChecks = [];
$generalBalanceAdjustmentHistory = [];
$generalBalancePostAction = '';
$generalBalancePostData = [];
$isCheckDetailsRequest = ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET'
	&& (isset($_GET['check_details'])
		|| (isset($_GET['check_id']) && strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'));
$respondWithCheckDetails = static function (array $payload, int $status = 200): void {
	while (ob_get_level() > 0) {
		ob_end_clean();
	}
	http_response_code($status);
	header('Content-Type: application/json; charset=UTF-8');
	$json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
	if ($json === false) {
		http_response_code(500);
		$json = '{"error":"Unable to encode manual balance check details."}';
	}
	echo $json;
	exit;
};

try {
	backoffice_ensure_commission_bank_schema();
	$db = new \Core\Database;
	$requiredTables = [
		'airtimebundles',
		'backoffice_commission_bank_transfer_items',
		'backoffice_commission_bank_transfers',
		'cashpickups',
		'cashdrawers',
		'commissions',
		'drawer_balances',
		'petty_cash_transactions',
		'sims',
		'vault_stock',
		'vaults',
	];
	if (!$db->table_exists($requiredTables)) {
		throw new \RuntimeException('General Balancing cannot load because required accounting tables are missing: ' . implode(', ', $db->missing_tables));
	}

	$queryRow = static function (string $sql, array $params = []) use ($db): array {
		$rows = $db->query($sql, $params);
		if ($db->has_error) {
			throw new \RuntimeException((string)$db->error);
		}
		$row = $rows[0] ?? null;
		return is_object($row) ? get_object_vars($row) : (is_array($row) ? $row : []);
	};
	$queryRows = static function (string $sql, array $params = []) use ($db): array {
		$rows = $db->query($sql, $params);
		if ($db->has_error) {
			throw new \RuntimeException((string)$db->error);
		}
		return is_array($rows) ? $rows : [];
	};
	if ($isCheckDetailsRequest) {
		try {
			$checkId = max(0, (int)($_GET['check_id'] ?? 0));
			if ($checkId <= 0) {
				$respondWithCheckDetails(['error' => 'Select a valid manual balance check.'], 400);
			}
			$checkRows = $queryRows(
				"SELECT c.id, c.branch_id, c.start_date, c.end_date, c.date_created, c.created_by,
				        c.manual_total, c.system_total,
				        CONCAT_WS(' ', u.first_name, u.last_name) AS created_by_name
				 FROM backoffice_general_balance_checks c
				 LEFT JOIN users u ON u.id = c.created_by
				 WHERE c.id = :check_id
				 LIMIT 1",
				['check_id' => $checkId]
			);
			$check = $checkRows[0] ?? null;
			if (!$check) {
				$respondWithCheckDetails(['error' => 'The selected manual balance check was not found.'], 404);
			}
			$items = $queryRows(
				"SELECT category_label, manual_amount, system_amount
				 FROM backoffice_general_balance_check_items
				 WHERE check_id = :check_id
				 ORDER BY id",
				['check_id' => $checkId]
			);
			$lineItems = $queryRows(
				"SELECT category_label, item_label, amount
				 FROM backoffice_general_balance_check_line_items
				 WHERE check_id = :check_id
				 ORDER BY id",
				['check_id' => $checkId]
			);
			$branchLabel = 'All branches';
			if ((int)($check->branch_id ?? 0) > 0 && $db->table_exists(['branches'])) {
				$branchRows = $queryRows(
					'SELECT name FROM branches WHERE id = :branch_id LIMIT 1',
					['branch_id' => (int)$check->branch_id]
				);
				$branchLabel = (string)($branchRows[0]->name ?? $branchLabel);
			}
			$respondWithCheckDetails([
				'check' => [
					'id' => (int)$check->id,
					'branch' => $branchLabel,
					'date_range' => ($check->start_date ?? null) || ($check->end_date ?? null)
						? (($check->start_date ?? '') ?: 'Beginning') . ' – ' . (($check->end_date ?? '') ?: 'Today')
						: 'Current snapshot',
					'date_created' => (string)$check->date_created,
					'created_by' => trim((string)($check->created_by_name ?? '')) ?: 'User #' . (int)$check->created_by,
					'manual_total' => (float)$check->manual_total,
					'system_total' => (float)$check->system_total,
				],
				'items' => array_map(static function ($item): array {
					return [
						'category' => (string)$item->category_label,
						'entered' => (float)$item->manual_amount,
						'system' => (float)$item->system_amount,
					];
				}, $items),
				'line_items' => array_map(static function ($item): array {
					return [
						'category' => (string)$item->category_label,
						'label' => (string)$item->item_label,
						'amount' => (float)$item->amount,
					];
				}, $lineItems),
			]);
		} catch (\Throwable $detailsError) {
			error_log('[backoffice general balancing details] ' . $detailsError->getMessage());
			$respondWithCheckDetails(['error' => 'Unable to load manual balance check details.'], 500);
		}
	}
	$normalizeDateFilter = static function (string $value, string $label): string {
		$value = trim($value);
		if ($value === '') {
			return '';
		}
		$date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
		if (!$date || $date->format('Y-m-d') !== $value) {
			throw new \InvalidArgumentException('Enter a valid ' . $label . ' date.');
		}
		return $value;
	};
	$requestData = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
	$generalBalanceStartDate = $normalizeDateFilter((string)($requestData['start_date'] ?? ''), 'start');
	$generalBalanceEndDate = $normalizeDateFilter((string)($requestData['end_date'] ?? ''), 'end');
	if ($generalBalanceStartDate !== '' && $generalBalanceEndDate !== '' && $generalBalanceStartDate > $generalBalanceEndDate) {
		throw new \InvalidArgumentException('The start date must be on or before the end date.');
	}
	$generalBalanceBranchId = max(0, (int)($requestData['branch_id'] ?? 0));
	$generalBalanceBranches = $db->table_exists(['branches'])
		? ($db->query('SELECT id, name FROM branches ORDER BY name, id') ?: [])
		: [];
	$generalBalanceSelectedBranchName = '';
	foreach ($generalBalanceBranches as $branch) {
		if ((int)($branch->id ?? 0) === $generalBalanceBranchId) {
			$generalBalanceSelectedBranchName = (string)($branch->name ?? '');
			break;
		}
	}
	if ($generalBalanceBranchId > 0 && $generalBalanceSelectedBranchName === '') {
		throw new \InvalidArgumentException('Select a valid branch.');
	}
	$generalBalanceColumnCache = [];
	$generalBalanceScope = static function (string $table, string $alias, string $userColumn = 'created_by', bool $includeDate = true, string $dateColumn = 'date_created') use ($db, $generalBalanceStartDate, $generalBalanceEndDate, $generalBalanceBranchId, &$generalBalanceColumnCache): array {
		if (!array_key_exists($table, $generalBalanceColumnCache)) {
			$generalBalanceColumnCache[$table] = function_exists('backoffice_table_columns')
				? backoffice_table_columns($db, $table)
				: [];
		}
		$columns = $generalBalanceColumnCache[$table];
		$alias = preg_replace('/[^A-Za-z0-9_]/', '', $alias) ?: 't';
		$token = preg_replace('/[^A-Za-z0-9_]/', '_', $table . '_' . $alias);
		$clauses = [];
		$params = [];
		$hasDateFilter = $includeDate && ($generalBalanceStartDate !== '' || $generalBalanceEndDate !== '');
		if ($hasDateFilter && empty($columns[$dateColumn]) && empty($columns['date_created'])) {
			throw new \RuntimeException('Date filtering is not supported for ' . $table . '.');
		}
		$dateExpression = !empty($columns[$dateColumn])
			? "{$alias}.{$dateColumn}"
			: "{$alias}.date_created";
		if ($dateColumn !== 'date_created' && !empty($columns[$dateColumn]) && !empty($columns['date_created'])) {
			$dateExpression = "COALESCE(NULLIF({$alias}.{$dateColumn}, '0000-00-00 00:00:00'), {$alias}.date_created)";
		}
		if ($hasDateFilter && $generalBalanceStartDate !== '') {
			$key = 'gb_start_' . $token;
			$clauses[] = "{$dateExpression} >= :{$key}";
			$params[$key] = $generalBalanceStartDate . ' 00:00:00';
		}
		if ($hasDateFilter && $generalBalanceEndDate !== '') {
			$key = 'gb_end_' . $token;
			$clauses[] = "{$dateExpression} < :{$key}";
			$params[$key] = (new \DateTimeImmutable($generalBalanceEndDate))->modify('+1 day')->format('Y-m-d 00:00:00');
		}
		if ($generalBalanceBranchId > 0) {
			if (!empty($columns['branch_id'])) {
				$key = 'gb_branch_' . $token;
				$clauses[] = "{$alias}.branch_id = :{$key}";
				$params[$key] = $generalBalanceBranchId;
			} elseif (!empty($columns[strtolower($userColumn)])) {
				$key = 'gb_branch_' . $token;
				$clauses[] = "{$alias}.{$userColumn} IN (SELECT gb_branch_user.id FROM users gb_branch_user WHERE gb_branch_user.branch_id = :{$key})";
				$params[$key] = $generalBalanceBranchId;
			} else {
				throw new \RuntimeException('Branch filtering is not supported for ' . $table . '.');
			}
		}
		return ['sql' => $clauses ? ' AND ' . implode(' AND ', $clauses) : '', 'params' => $params];
	};
	$applyGeneralBalanceScope = static function (string $sql, array $scope): array {
		if ($scope['sql'] === '') {
			return [$sql, $scope['params']];
		}
		if (preg_match('/\r?\n[ \t]+(GROUP\s+BY|HAVING|ORDER\s+BY)\b/i', $sql, $match, PREG_OFFSET_CAPTURE)) {
			$position = $match[0][1];
			$sql = substr($sql, 0, $position) . $scope['sql'] . substr($sql, $position);
		} else {
			$sql .= $scope['sql'];
		}
		return [$sql, $scope['params']];
	};
	$queryScopedRow = static function (string $sql, string $table, string $alias, string $userColumn = 'created_by', array $params = [], string $dateColumn = 'date_created') use ($queryRow, $generalBalanceScope, $applyGeneralBalanceScope): array {
		[$sql, $scopeParams] = $applyGeneralBalanceScope($sql, $generalBalanceScope($table, $alias, $userColumn, true, $dateColumn));
		return $queryRow($sql, array_merge($params, $scopeParams));
	};
	$queryScopedRows = static function (string $sql, string $table, string $alias, string $userColumn = 'created_by', array $params = [], string $dateColumn = 'date_created') use ($queryRows, $generalBalanceScope, $applyGeneralBalanceScope): array {
		[$sql, $scopeParams] = $applyGeneralBalanceScope($sql, $generalBalanceScope($table, $alias, $userColumn, true, $dateColumn));
		return $queryRows($sql, array_merge($params, $scopeParams));
	};
	$number = static function (array $row, string $key): float {
		return (float)($row[$key] ?? 0);
	};
	$detailRows = static function (array $rows, string $labelKey, string $amountKey): array {
		$details = [];
		foreach ($rows as $row) {
			$label = is_array($row) ? ($row[$labelKey] ?? '') : ($row->{$labelKey} ?? '');
			$amount = is_array($row) ? ($row[$amountKey] ?? 0) : ($row->{$amountKey} ?? 0);
			$details[] = ['label' => (string)$label, 'value' => (float)$amount];
		}
		return $details;
	};

	$db->query(
		"CREATE TABLE IF NOT EXISTS backoffice_general_balance_adjustments (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			side VARCHAR(10) NOT NULL,
			amount DECIMAL(14,2) NOT NULL,
			reason VARCHAR(500) NOT NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			date_created DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY idx_general_balance_adjustment_side_date (side, date_created)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
	);
	if ($db->has_error) {
		throw new \RuntimeException('Unable to prepare General Balancing adjustments: ' . $db->error);
	}
	$db->query(
		"CREATE TABLE IF NOT EXISTS backoffice_general_balance_checks (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			branch_id BIGINT UNSIGNED NULL,
			start_date DATE NULL,
			end_date DATE NULL,
			system_total DECIMAL(14,2) NOT NULL,
			manual_total DECIMAL(14,2) NOT NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			date_created DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY idx_general_balance_check_date (date_created),
			KEY idx_general_balance_check_branch_date (branch_id, date_created)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
	);
	if ($db->has_error) {
		throw new \RuntimeException('Unable to prepare General Balancing manual checks: ' . $db->error);
	}
	$db->query(
		"CREATE TABLE IF NOT EXISTS backoffice_general_balance_check_items (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			check_id BIGINT UNSIGNED NOT NULL,
			category_label VARCHAR(190) NOT NULL,
			manual_amount DECIMAL(14,2) NOT NULL,
			system_amount DECIMAL(14,2) NOT NULL,
			PRIMARY KEY (id),
			KEY idx_general_balance_check_item (check_id, id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
	);
	if ($db->has_error) {
		throw new \RuntimeException('Unable to prepare General Balancing check details: ' . $db->error);
	}
	$db->query(
		"CREATE TABLE IF NOT EXISTS backoffice_general_balance_check_line_items (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			check_id BIGINT UNSIGNED NOT NULL,
			category_label VARCHAR(190) NOT NULL,
			item_label VARCHAR(190) NOT NULL,
			amount DECIMAL(14,2) NOT NULL,
			PRIMARY KEY (id),
			KEY idx_general_balance_check_line_item (check_id, id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
	);
	if ($db->has_error) {
		throw new \RuntimeException('Unable to prepare General Balancing check line items: ' . $db->error);
	}

	if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$generalBalanceExport) {
		$generalBalancePostData = (new \Core\Request)->post();
		if (!csrf_verify($generalBalancePostData)) {
			message_fail('Your form session expired. Refresh the page and try again.');
			redirect($admin_route . '/' . $plugin_route . '/general_balance');
			exit;
		}
		$generalBalancePostAction = trim((string)($generalBalancePostData['action'] ?? ''));
		if (!in_array($generalBalancePostAction, ['adjustment', 'manual_check'], true)) {
			throw new \InvalidArgumentException('Choose a valid General Balancing action.');
		}
		$returnFilters = array_filter([
			'start_date' => $generalBalanceStartDate,
			'end_date' => $generalBalanceEndDate,
			'branch_id' => $generalBalanceBranchId > 0 ? $generalBalanceBranchId : '',
		], static fn($value): bool => $value !== '');
		if ($generalBalancePostAction === 'adjustment') {
			$session = get_session();
			if (!backoffice_user_is_admin($session)) {
				http_response_code(403);
				message_fail('Only an Admin can make General Balancing adjustments.');
				redirect($admin_route . '/' . $plugin_route . '/general_balance');
				exit;
			}
			$postdata = $generalBalancePostData;
			$side = strtolower(trim((string)($postdata['side'] ?? '')));
			$rawAmount = trim((string)($postdata['amount'] ?? ''));
			$reason = trim((string)($postdata['reason'] ?? ''));
			if (!in_array($side, ['left', 'right'], true)
				|| $rawAmount === ''
				|| !is_numeric($rawAmount)
				|| !is_finite((float)$rawAmount)
				|| abs((float)$rawAmount) < 0.01
				|| $reason === ''
				|| strlen($reason) > 500) {
				message_fail('Enter a valid non-zero adjustment amount, side, and reason (up to 500 characters).');
				redirect($admin_route . '/' . $plugin_route . '/general_balance');
				exit;
			}
			$db->query(
				"INSERT INTO backoffice_general_balance_adjustments (side, amount, reason, created_by, date_created)
				 VALUES (:side, :amount, :reason, :created_by, :date_created)",
				[
					'side' => $side,
					'amount' => round((float)$rawAmount, 2),
					'reason' => $reason,
					'created_by' => (int)($session->id ?? 0),
					'date_created' => date('Y-m-d H:i:s'),
				]
			);
			if ($db->has_error) {
				throw new \RuntimeException('Unable to save the General Balancing adjustment: ' . $db->error);
			}
			message_success('General Balancing adjustment recorded.');
			redirect($admin_route . '/' . $plugin_route . '/general_balance?' . http_build_query(array_merge($returnFilters, ['tab' => 'adjustments'])));
			exit;
		}
	}
	$excludedStatusSql = "LOWER(TRIM(COALESCE(transaction_status,''))) NOT IN ('rejected','declined','denied','reversed')";

	$cashPickupReceiveScope = $generalBalanceScope('cashpickups', 'r');
	$cashPickupPayoutScope = $generalBalanceScope('cashpickups', 'p');
	$cashPickupBreakdown = $queryRows(
		"SELECT 'Received cash pickups' AS label, COALESCE(SUM(r.amount_involved),0) AS total
		 FROM cashpickups r
		 WHERE (r.deleted IS NULL OR r.deleted = 0)
		   AND (r.disabled IS NULL OR r.disabled = 0)
		   AND LOWER(TRIM(COALESCE(r.transaction_side,''))) = 'receive'
		   AND LOWER(TRIM(COALESCE(r.transaction_status,''))) = 'approved'
		   AND (r.approval_status IS NULL OR TRIM(r.approval_status) = '' OR LOWER(TRIM(r.approval_status)) IN ('approved','pending'))
		   {$cashPickupReceiveScope['sql']}
		 UNION ALL
		 SELECT 'Cash pickup payouts paid to customers' AS label, -COALESCE(SUM(COALESCE(p.amount_paid_out,0)),0) AS total
		 FROM cashpickups p
		 WHERE (p.deleted IS NULL OR p.deleted = 0)
		   AND (p.disabled IS NULL OR p.disabled = 0)
		   AND LOWER(TRIM(COALESCE(p.transaction_side,''))) = 'pay'
		   AND LOWER(TRIM(COALESCE(p.transaction_status,''))) = 'approved'
		   AND COALESCE(p.confirmed_by,'') <> ''
		   AND COALESCE(p.amount_paid_out,0) > 0
		   AND (p.approval_status IS NULL OR TRIM(p.approval_status) = '' OR LOWER(TRIM(p.approval_status)) IN ('approved','pending'))
		   {$cashPickupPayoutScope['sql']}
		   AND EXISTS (
				SELECT 1
				FROM cashpickups r
				WHERE (
					r.id = p.receive_id
					OR (
						TRIM(COALESCE(p.tracking_id,'')) <> ''
						AND LOWER(TRIM(COALESCE(r.tracking_id,''))) = LOWER(TRIM(p.tracking_id))
					)
				  )
				  AND (r.deleted IS NULL OR r.deleted = 0)
				  AND (r.disabled IS NULL OR r.disabled = 0)
				  AND LOWER(TRIM(COALESCE(r.transaction_side,''))) = 'receive'
				  AND LOWER(TRIM(COALESCE(r.transaction_status,''))) = 'approved'
				  AND (r.approval_status IS NULL OR TRIM(r.approval_status) = '' OR LOWER(TRIM(r.approval_status)) IN ('approved','pending'))
		   )
		",
		array_merge($cashPickupReceiveScope['params'], $cashPickupPayoutScope['params'])
	);
	$cashPickupNet = 0.0;
	foreach ($cashPickupBreakdown as $cashPickupLine) {
		$cashPickupNet += (float)($cashPickupLine->total ?? 0);
	}
	$cashPickupRow = ['total' => $cashPickupNet];
	$cashPickupBalanceLabel = ($generalBalanceStartDate !== '' || $generalBalanceEndDate !== '')
		? 'Cash pickup net for selected dates'
		: 'Cash pickup current balance';
	$generalBalanceDetails['left'][$cashPickupBalanceLabel] = $detailRows(
		$cashPickupBreakdown,
		'label',
		'total'
	);
	$soldSimsRow = $queryScopedRow(
		"SELECT COALESCE(SUM(amount),0) AS total, COALESCE(SUM(commission),0) AS commission
		 FROM sims s
		 WHERE (deleted IS NULL OR deleted = 0)
		   AND (disabled IS NULL OR disabled = 0)
		   AND LOWER(TRIM(COALESCE(transaction_status,''))) IN ('sold - retail','sold - wholesale')",
		'sims',
		's',
		'created_by',
		[],
		'date_updated'
	);
	$generalBalanceDetails['left']['SIMs sold (recorded sale amount)'] = $detailRows(
		$queryScopedRows(
			"SELECT COALESCE(NULLIF(TRIM(network),''),'Unspecified network') AS label, COALESCE(SUM(amount),0) AS total
			 FROM sims s
			 WHERE (deleted IS NULL OR deleted = 0) AND (disabled IS NULL OR disabled = 0)
			 AND LOWER(TRIM(COALESCE(transaction_status,''))) IN ('sold - retail','sold - wholesale')
			 GROUP BY COALESCE(NULLIF(TRIM(network),''),'Unspecified network')
			 ORDER BY label",
			'sims',
			's',
			'created_by',
			[],
			'date_updated'
		),
		'label',
		'total'
	);
	$unsoldSimsRow = $queryScopedRow(
		"SELECT COALESCE(SUM(retail_price),0) AS total
		 FROM sims s
		 WHERE (deleted IS NULL OR deleted = 0)
		   AND (disabled IS NULL OR disabled = 0)
		   AND LOWER(TRIM(COALESCE(transaction_status,''))) NOT IN ('sold - retail','sold - wholesale','rejected','declined','denied','reversed')",
		'sims',
		's'
	);
	$generalBalanceDetails['left']['Unsold SIM inventory (retail price)'] = $detailRows(
		$queryScopedRows(
			"SELECT COALESCE(NULLIF(TRIM(network),''),'Unspecified network') AS label, COALESCE(SUM(retail_price),0) AS total
			 FROM sims s
			 WHERE (deleted IS NULL OR deleted = 0) AND (disabled IS NULL OR disabled = 0)
			   AND LOWER(TRIM(COALESCE(transaction_status,''))) NOT IN ('sold - retail','sold - wholesale','rejected','declined','denied','reversed')
			 GROUP BY COALESCE(NULLIF(TRIM(network),''),'Unspecified network')
			 ORDER BY label",
			'sims',
			's'
		),
		'label',
		'total'
	);
	$commissionTransferDateSql = '';
	$commissionTransferParams = [];
	if ($generalBalanceStartDate !== '') {
		$commissionTransferDateSql .= ' AND t.approved_at >= :gb_commission_transfer_start';
		$commissionTransferParams['gb_commission_transfer_start'] = $generalBalanceStartDate . ' 00:00:00';
	}
	if ($generalBalanceEndDate !== '') {
		$commissionTransferDateSql .= ' AND t.approved_at < :gb_commission_transfer_end';
		$commissionTransferParams['gb_commission_transfer_end'] = (new \DateTimeImmutable($generalBalanceEndDate))->modify('+1 day')->format('Y-m-d 00:00:00');
	}
	$commissionRow = $queryScopedRow(
		"SELECT COALESCE(SUM(GREATEST(
				COALESCE(m.amount,0) - COALESCE((
					SELECT SUM(i.amount)
					FROM backoffice_commission_bank_transfer_items i
					JOIN backoffice_commission_bank_transfers t ON t.id = i.transfer_id
					WHERE i.commission_id = m.id
					  AND t.deleted = 0
					  AND LOWER(TRIM(t.status)) = 'approved'{$commissionTransferDateSql}
				),0),
			0)),0) AS total
		 FROM commissions m
		 WHERE (m.deleted IS NULL OR m.deleted = 0)
		   AND (m.disabled IS NULL OR m.disabled = 0)
		   AND {$excludedStatusSql}
		   AND (m.bulk_parent_id IS NULL OR m.bulk_parent_id = 0)",
		'commissions',
		'm',
		'created_by',
		$commissionTransferParams
	);
	$generalBalanceDetails['left']['Recorded commissions'] = $detailRows(
		$queryScopedRows(
			"SELECT CONCAT(COALESCE(NULLIF(TRIM(m.commission_type),''),'Other commission'), ' · ', COALESCE(NULLIF(TRIM(m.network),''),'All networks')) AS label,
			        COALESCE(SUM(GREATEST(
			        	COALESCE(m.amount,0) - COALESCE((
			        		SELECT SUM(i.amount)
			        		FROM backoffice_commission_bank_transfer_items i
			        		JOIN backoffice_commission_bank_transfers t ON t.id = i.transfer_id
			        		WHERE i.commission_id = m.id
			        		  AND t.deleted = 0
			        		  AND LOWER(TRIM(t.status)) = 'approved'{$commissionTransferDateSql}
			        	),0),
			        0)),0) AS total
			 FROM commissions m
			 WHERE (m.deleted IS NULL OR m.deleted = 0) AND (m.disabled IS NULL OR m.disabled = 0)
			   AND {$excludedStatusSql} AND (m.bulk_parent_id IS NULL OR m.bulk_parent_id = 0)
			 GROUP BY m.commission_type, m.network ORDER BY label",
			'commissions',
			'm',
			'created_by',
			$commissionTransferParams
		),
		'label',
		'total'
	);
	$generalBalanceDetails['left']['SIM sale commissions'] = $detailRows(
		$queryScopedRows(
			"SELECT COALESCE(NULLIF(TRIM(network),''),'Unspecified network') AS label, COALESCE(SUM(commission),0) AS total
			 FROM sims s
			 WHERE (deleted IS NULL OR deleted = 0) AND (disabled IS NULL OR disabled = 0)
			   AND LOWER(TRIM(COALESCE(transaction_status,''))) IN ('sold - retail','sold - wholesale')
			 GROUP BY COALESCE(NULLIF(TRIM(network),''),'Unspecified network') ORDER BY label",
			'sims',
			's',
			'created_by',
			[],
			'date_updated'
		),
		'label',
		'total'
	);
	$cashoutCommission = 0.0;
	if ($db->table_exists(['cashout_commissions'])) {
		$cashoutCommission = $number($queryScopedRow(
			"SELECT COALESCE(SUM(GREATEST(COALESCE(commission_amount,0) - COALESCE(transferred_amount,0),0)),0) AS total
			 FROM cashout_commissions c
			 WHERE (deleted IS NULL OR deleted = 0)
			   AND (disabled IS NULL OR disabled = 0)
			   AND UPPER(TRIM(COALESCE(status,''))) = 'PENDING'",
			'cashout_commissions',
			'c',
			'user_id'
		), 'total');
		$generalBalanceDetails['left']['Cashout commissions not yet transferred'] = $detailRows(
			$queryScopedRows(
				"SELECT CONCAT(COALESCE(NULLIF(TRIM(commission_type),''),'Cashout commission'), ' · ', COALESCE(NULLIF(TRIM(network),''),'All networks')) AS label,
				        COALESCE(SUM(GREATEST(COALESCE(commission_amount,0) - COALESCE(transferred_amount,0),0)),0) AS total
				 FROM cashout_commissions c
				 WHERE (deleted IS NULL OR deleted = 0) AND (disabled IS NULL OR disabled = 0)
				   AND UPPER(TRIM(COALESCE(status,''))) = 'PENDING'
				 GROUP BY commission_type, network ORDER BY label",
				'cashout_commissions',
				'c',
				'user_id'
			),
			'label',
			'total'
		);
	}
	$airtimeCommissionRow = $queryScopedRow(
		"SELECT COALESCE(SUM(CAST(commission AS DECIMAL(12,2))),0) AS total
		 FROM airtimebundles a
		 WHERE (deleted IS NULL OR deleted = 0)
		   AND (disabled IS NULL OR disabled = 0)
		   AND {$excludedStatusSql}",
		'airtimebundles',
		'a'
	);
	$generalBalanceDetails['left']['Airtime bundle commissions'] = $detailRows(
		$queryScopedRows(
			"SELECT COALESCE(NULLIF(TRIM(network),''),'Unspecified network') AS label,
			        COALESCE(SUM(CAST(commission AS DECIMAL(12,2))),0) AS total
			 FROM airtimebundles a
			 WHERE (deleted IS NULL OR deleted = 0) AND (disabled IS NULL OR disabled = 0)
			   AND {$excludedStatusSql}
			 GROUP BY COALESCE(NULLIF(TRIM(network),''),'Unspecified network') ORDER BY label",
			'airtimebundles',
			'a'
		),
		'label',
		'total'
	);
	$agentServiceCommission = 0.0;
	if ($db->table_exists(['agent_services'])) {
		$agentServiceCommission = $number($queryScopedRow(
			"SELECT COALESCE(SUM(commission_amount),0) AS total
			 FROM agent_services a
			 WHERE (deleted IS NULL OR deleted = 0)
			   AND LOWER(TRIM(COALESCE(status,''))) IN ('completed','complete','approved','success')",
			'agent_services',
			'a',
			'creator_id'
		), 'total');
		$generalBalanceDetails['left']['Agent service commissions'] = $detailRows(
			$queryScopedRows(
				"SELECT COALESCE(NULLIF(TRIM(service_type),''),'Agent service') AS label, COALESCE(SUM(commission_amount),0) AS total
				 FROM agent_services a
				 WHERE (deleted IS NULL OR deleted = 0)
				   AND LOWER(TRIM(COALESCE(status,''))) IN ('completed','complete','approved','success')
				 GROUP BY COALESCE(NULLIF(TRIM(service_type),''),'Agent service') ORDER BY label",
				'agent_services',
				'a',
				'creator_id'
			),
			'label',
			'total'
		);
	}

	$generalBalance['left'] = [
		['label' => $cashPickupBalanceLabel, 'value' => $number($cashPickupRow, 'total')],
		['label' => 'SIMs sold (recorded sale amount)', 'value' => $number($soldSimsRow, 'total')],
		['label' => 'Unsold SIM inventory (retail price)', 'value' => $number($unsoldSimsRow, 'total')],
		['label' => 'Recorded commissions', 'value' => $number($commissionRow, 'total')],
		['label' => 'Cashout commissions not yet transferred', 'value' => $cashoutCommission],
		['label' => 'SIM sale commissions', 'value' => $number($soldSimsRow, 'commission')],
		['label' => 'Airtime bundle commissions', 'value' => $number($airtimeCommissionRow, 'total')],
		['label' => 'Agent service commissions', 'value' => $agentServiceCommission],
	];

	require_once dirname(__DIR__, 2) . '/vaults/helpers/vaults.php';
	$generalBalance['cfa_rate'] = \Vaults\currentCfaRate();
	$vaultBranchScope = $generalBalanceScope('vaults', 'v', 'created_by', false);
	$vaultCashSql = "SELECT
			COALESCE(SUM(CASE WHEN s.currency = 'GHS' THEN s.denomination * s.note_count ELSE 0 END),0) AS ghs,
			COALESCE(SUM(CASE WHEN s.currency = 'CFA' THEN s.denomination * s.note_count ELSE 0 END),0) AS cfa,
			COALESCE(SUM(CASE WHEN s.currency = 'CFA' THEN s.denomination * s.note_count * s.purchase_rate / 1000 ELSE 0 END),0) AS cfa_ghs
		 FROM vault_stock s
		 JOIN vaults v ON v.id = s.vault_id
		 WHERE v.active = 1{$vaultBranchScope['sql']}";
	$vaultCashRow = $queryRow(
		$vaultCashSql,
		$vaultBranchScope['params']
	);

	$drawerParams = [];
	$drawerWhere = '';
	$latestDrawerDateClause = '';
	$latestBalanceDateClause = '';
	if ($generalBalanceBranchId > 0) {
		$drawerWhere .= ' AND u.branch_id = :gb_drawer_branch';
		$drawerParams['gb_drawer_branch'] = $generalBalanceBranchId;
	}
	if ($generalBalanceEndDate !== '') {
		$drawerEndExclusive = (new \DateTimeImmutable($generalBalanceEndDate))->modify('+1 day')->format('Y-m-d 00:00:00');
		$drawerWhere .= ' AND c.date_created < :gb_drawer_end';
		$latestDrawerDateClause = ' AND c2.date_created < :gb_drawer_end_for_latest';
		$latestBalanceDateClause = ' AND b2.date_created < :gb_balance_end_for_latest';
		$drawerParams['gb_drawer_end'] = $drawerEndExclusive;
		$drawerParams['gb_drawer_end_for_latest'] = $drawerEndExclusive;
		$drawerParams['gb_balance_end_for_latest'] = $drawerEndExclusive;
	}
	$drawerRows = $db->query(
		"SELECT b.channel_type, b.channel_key, b.closing_balance, c.user_id,
		        u.first_name, u.last_name, br.name AS branch_name
		 FROM drawer_balances b
		 JOIN cashdrawers c ON c.id = b.drawer_id
		 LEFT JOIN users u ON u.id = c.user_id
		 LEFT JOIN branches br ON br.id = u.branch_id
		 WHERE c.id = (
			SELECT c2.id FROM cashdrawers c2
			WHERE c2.user_id = c.user_id
			  {$latestDrawerDateClause}
			ORDER BY c2.date_created DESC, c2.id DESC
			LIMIT 1
		 )
		 AND b.id = (
			SELECT b2.id FROM drawer_balances b2
			WHERE b2.drawer_id = b.drawer_id
			  AND b2.user_id = b.user_id
			  AND b2.channel_type = b.channel_type
			  AND COALESCE(b2.channel_key,'') = COALESCE(b.channel_key,'')
			  {$latestBalanceDateClause}
			ORDER BY b2.date_created DESC, b2.id DESC
			LIMIT 1
		 )
		 {$drawerWhere}",
		$drawerParams
	);
	if ($db->has_error) {
		throw new \RuntimeException((string)$db->error);
	}

	$drawerCashGhs = 0.0;
	$drawerCashCfa = 0.0;
	$ecash = 0.0;
	$evd = 0.0;
	$sharedBankAccounts = 0.0;
	$ecashDetails = [];
	$evdDetails = [];
	$bankDetails = [];
	$drawerCashDetails = [];
	$drawerDisplayLabel = static function ($row, string $channel): string {
		$userName = trim((string)($row->first_name ?? '') . ' ' . (string)($row->last_name ?? ''));
		if ($userName === '') {
			$userName = 'User #' . (int)($row->user_id ?? 0);
		}
		$branchName = trim((string)($row->branch_name ?? ''));
		return $channel . ' · ' . $userName . ' · ' . ($branchName !== '' ? $branchName : 'Unassigned branch');
	};
	$bankNeedles = ['BANK', 'ADB', 'ABSA', 'FIDELITY', 'APEX', 'ACCESS', 'CAL', 'ECOBANK', 'GCB', 'GT', 'STANBIC', 'FNB', 'FIRST'];
	foreach ((array)$drawerRows as $drawerRow) {
		$key = strtoupper(trim((string)($drawerRow->channel_key ?? '')));
		$type = strtolower(trim((string)($drawerRow->channel_type ?? '')));
		$balance = (float)($drawerRow->closing_balance ?? 0);
		if ($type === 'cash' && strpos($key, 'CFA') !== false) {
			$drawerCashCfa += $balance;
			$drawerCashDetails['CFA cash in active drawers'][] = [
				'label' => $drawerDisplayLabel($drawerRow, $key !== '' ? $key : 'CFA cash drawer') . ' · converted to GHS',
				'value' => $balance * $generalBalance['cfa_rate'] / 1000,
			];
		} elseif ($type === 'cash') {
			$drawerCashGhs += $balance;
			$drawerCashDetails['GHS cash in active drawers'][] = ['label' => $drawerDisplayLabel($drawerRow, $key !== '' ? $key : 'GHS cash drawer'), 'value' => $balance];
		} elseif ($type === 'ecash') {
			$isBank = false;
			foreach ($bankNeedles as $needle) {
				if (strpos($key, $needle) !== false) {
					$isBank = true;
					break;
				}
			}
			if ($isBank) {
				$sharedBankAccounts += $balance;
				$bankDetails[] = ['label' => $drawerDisplayLabel($drawerRow, $key), 'value' => $balance];
			} elseif (strpos($key, 'EVD') !== false) {
				$evd += $balance;
				$evdDetails[] = ['label' => $drawerDisplayLabel($drawerRow, $key), 'value' => $balance];
			} else {
				$ecash += $balance;
				$ecashDetails[] = ['label' => $drawerDisplayLabel($drawerRow, $key), 'value' => $balance];
			}
		}
	}
	$commissionBankTransferWhere = "WHERE t.deleted = 0 AND LOWER(TRIM(t.status)) = 'approved'";
	$commissionBankTransferParams = [];
	if ($generalBalanceStartDate !== '') {
		$commissionBankTransferWhere .= ' AND t.approved_at >= :gb_commission_bank_start';
		$commissionBankTransferParams['gb_commission_bank_start'] = $generalBalanceStartDate . ' 00:00:00';
	}
	if ($generalBalanceEndDate !== '') {
		$commissionBankTransferWhere .= ' AND t.approved_at < :gb_commission_bank_end';
		$commissionBankTransferParams['gb_commission_bank_end'] = (new \DateTimeImmutable($generalBalanceEndDate))->modify('+1 day')->format('Y-m-d 00:00:00');
	}
	if ($generalBalanceBranchId > 0) {
		$commissionBankTransferWhere .= ' AND i.branch_id = :gb_commission_bank_branch';
		$commissionBankTransferParams['gb_commission_bank_branch'] = $generalBalanceBranchId;
	}
	$commissionBankTransferWhere .= ' AND t.cashdrawer_credited = 0';
	$commissionBankBalanceRows = $queryRows(
		"SELECT t.bank_name AS label, COALESCE(SUM(i.amount),0) AS total
		 FROM backoffice_commission_bank_transfer_items i
		 JOIN backoffice_commission_bank_transfers t ON t.id = i.transfer_id
		 {$commissionBankTransferWhere}
		 GROUP BY t.bank_name
		 ORDER BY t.bank_name",
		$commissionBankTransferParams
	);
	foreach ($commissionBankBalanceRows as $commissionBankBalanceRow) {
		$commissionBankAmount = (float)($commissionBankBalanceRow->total ?? 0);
		$sharedBankAccounts += $commissionBankAmount;
		$bankDetails[] = [
			'label' => trim((string)($commissionBankBalanceRow->label ?? 'Bank')) . ' · approved commission transfers',
			'value' => $commissionBankAmount,
		];
	}
	$vaultBreakdown = $queryRows(
		"SELECT v.name AS label,
		        COALESCE(SUM(CASE WHEN s.currency = 'GHS' THEN s.denomination * s.note_count ELSE 0 END),0) AS ghs,
		        COALESCE(SUM(CASE WHEN s.currency = 'CFA' THEN s.denomination * s.note_count * s.purchase_rate / 1000 ELSE 0 END),0) AS cfa_ghs
		 FROM vault_stock s JOIN vaults v ON v.id = s.vault_id
		 WHERE v.active = 1{$vaultBranchScope['sql']} GROUP BY v.id, v.name ORDER BY v.name",
		$vaultBranchScope['params']
	);
	$vaultGhsDetails = [];
	$vaultCfaDetails = [];
	foreach ($vaultBreakdown as $vaultLine) {
		$vaultGhsDetails[] = ['label' => (string)($vaultLine->label ?? 'Vault'), 'value' => (float)($vaultLine->ghs ?? 0)];
		$vaultCfaDetails[] = ['label' => (string)($vaultLine->label ?? 'Vault') . ' · CFA converted', 'value' => (float)($vaultLine->cfa_ghs ?? 0)];
	}

	$pettyCashRow = $queryScopedRow(
		"SELECT
			COALESCE(SUM(CASE WHEN UPPER(currency) = 'CFA' THEN GREATEST(COALESCE(budget_amount,0) - COALESCE(spent_amount,0),0) * :rate / 1000
				ELSE GREATEST(COALESCE(budget_amount,0) - COALESCE(spent_amount,0),0) END),0) AS total
		 FROM petty_cash_transactions p
		 WHERE (deleted IS NULL OR deleted = 0)
		   AND LOWER(TRIM(COALESCE(status,''))) IN ('acknowledged','in progress','in_progress')",
		'petty_cash_transactions',
		'p',
		'created_by',
		['rate' => $generalBalance['cfa_rate']]
	);
	$generalBalanceDetails['right']['Petty cash currently assigned'] = $detailRows(
		$queryScopedRows(
			"SELECT COALESCE(NULLIF(TRIM(category),''), NULLIF(TRIM(title),''), 'Uncategorized') AS label,
			        COALESCE(SUM(CASE WHEN UPPER(currency) = 'CFA'
			         THEN GREATEST(COALESCE(budget_amount,0) - COALESCE(spent_amount,0),0) * :rate / 1000
			         ELSE GREATEST(COALESCE(budget_amount,0) - COALESCE(spent_amount,0),0) END),0) AS total
			 FROM petty_cash_transactions p
			 WHERE (deleted IS NULL OR deleted = 0)
			   AND LOWER(TRIM(COALESCE(status,''))) IN ('acknowledged','in progress','in_progress')
			 GROUP BY COALESCE(NULLIF(TRIM(category),''), NULLIF(TRIM(title),''), 'Uncategorized')
			 ORDER BY label",
			'petty_cash_transactions',
			'p',
			'created_by',
			['rate' => $generalBalance['cfa_rate']]
		),
		'label',
		'total'
	);

	require_once dirname(__DIR__, 2) . '/cashpickup/helpers/customer-ledger.php';
	$creditRepaymentEndClause = '';
	$creditRepaymentParams = [];
	if ($generalBalanceEndDate !== '') {
		$creditRepaymentEndClause = ' WHERE date_created < :gb_credit_repayment_end';
		$creditRepaymentParams['gb_credit_repayment_end'] = (new \DateTimeImmutable($generalBalanceEndDate))->modify('+1 day')->format('Y-m-d 00:00:00');
	}
	$creditRows = $queryScopedRows(
		"SELECT CONCAT(COALESCE(NULLIF(TRIM(c.full_name),''),'Customer'), ' (#', cr.customer_id, ')') AS label,
		        COALESCE(SUM(GREATEST(COALESCE(cr.amount_involved,0) - COALESCE(repaid.amount_paid,0),0)),0) AS total
		 FROM cashpickups cr
		 LEFT JOIN cashpickup_customers c ON c.id = cr.customer_id
		 LEFT JOIN (
			SELECT credit_id, SUM(amount) AS amount_paid
			FROM cashpickup_credit_repayments{$creditRepaymentEndClause} GROUP BY credit_id
		 ) repaid ON repaid.credit_id = cr.id
		 WHERE (cr.deleted IS NULL OR cr.deleted = 0)
		   AND LOWER(TRIM(COALESCE(cr.transaction_side,''))) = 'receive'
		   AND LOWER(TRIM(COALESCE(cr.credit_mode,''))) = 'credit'
		   AND LOWER(TRIM(COALESCE(cr.transaction_status,''))) IN ('approved','pending payout')
		   AND (cr.approval_status IS NULL OR TRIM(cr.approval_status) = '' OR LOWER(TRIM(cr.approval_status)) IN ('approved','pending'))
		 GROUP BY cr.customer_id, c.full_name
		 HAVING SUM(GREATEST(COALESCE(cr.amount_involved,0) - COALESCE(repaid.amount_paid,0),0)) > 0
		 ORDER BY label",
		'cashpickups',
		'cr',
		'created_by',
		$creditRepaymentParams
	);
	$creditPayout = array_reduce($creditRows, static function (float $total, $row): float {
		return $total + (float)($row->total ?? 0);
	}, 0.0);

	$cashGhs = $number($vaultCashRow, 'ghs') + $drawerCashGhs;
	$cashCfa = $number($vaultCashRow, 'cfa') + $drawerCashCfa;
	$cashCfaGhs = $number($vaultCashRow, 'cfa_ghs') + ($drawerCashCfa * $generalBalance['cfa_rate'] / 1000);
	$generalBalance['right'] = [
		['label' => 'E-cash balances (all networks)', 'value' => $ecash],
		['label' => 'Cash in vaults and active drawers (GHS)', 'value' => $cashGhs],
		['label' => 'Cash in vaults and active drawers (CFA)', 'value' => $cashCfaGhs, 'detail' => number_format($cashCfa, 2) . ' CFA converted using the applicable vault rate'],
		['label' => 'EVD balances', 'value' => $evd],
		['label' => 'Shared bank account balances', 'value' => $sharedBankAccounts],
		['label' => 'Petty cash currently assigned', 'value' => $number($pettyCashRow, 'total')],
		['label' => 'Outstanding credit payout', 'value' => $creditPayout],
	];
	$generalBalanceDetails['right']['E-cash balances (all networks)'] = $ecashDetails;
	$generalBalanceDetails['right']['Cash in vaults and active drawers (GHS)'] = array_merge($vaultGhsDetails, $drawerCashDetails['GHS cash in active drawers'] ?? []);
	$generalBalanceDetails['right']['Cash in vaults and active drawers (CFA)'] = array_merge($vaultCfaDetails, $drawerCashDetails['CFA cash in active drawers'] ?? []);
	$generalBalanceDetails['right']['EVD balances'] = $evdDetails;
	$generalBalanceDetails['right']['Shared bank account balances'] = $bankDetails;
	$generalBalanceDetails['right']['Outstanding credit payout'] = $detailRows($creditRows, 'label', 'total');

	$adjustmentRows = $queryScopedRows(
		"SELECT a.side, a.amount, a.reason, a.date_created,
		        CONCAT_WS(' ', u.first_name, u.last_name) AS created_by_name
		 FROM backoffice_general_balance_adjustments a
		 LEFT JOIN users u ON u.id = a.created_by
		 WHERE 1 = 1
		 ORDER BY a.date_created DESC, a.id DESC",
		'backoffice_general_balance_adjustments',
		'a',
		'created_by'
	);
	foreach ($adjustmentRows as $adjustmentRow) {
		$side = strtolower((string)($adjustmentRow->side ?? ''));
		if (!isset($generalBalanceAdjustments[$side])) {
			$generalBalanceAdjustments[$side] = 0.0;
		}
		$amount = (float)($adjustmentRow->amount ?? 0);
		$generalBalanceAdjustments[$side] += $amount;
		$generalBalanceDetails[$side]['Manual adjustments'][] = [
			'label' => trim((string)($adjustmentRow->reason ?? 'Adjustment')) . ' · ' . trim((string)($adjustmentRow->created_by_name ?? 'Admin')),
			'value' => $amount,
			'detail' => (string)($adjustmentRow->date_created ?? ''),
		];
	}
	foreach (['left', 'right'] as $side) {
		if (array_key_exists($side, $generalBalanceAdjustments) && !empty($generalBalanceDetails[$side]['Manual adjustments'])) {
			$generalBalance[$side][] = ['label' => 'Manual adjustments', 'value' => $generalBalanceAdjustments[$side]];
		}
	}

	foreach (['left', 'right'] as $side) {
		$generalBalance[$side] = array_values(array_filter(
			$generalBalance[$side],
			static fn(array $item): bool => round((float)($item['value'] ?? 0), 2) != 0.0
		));
		foreach ($generalBalanceDetails[$side] as $label => $details) {
			$visibleDetails = array_values(array_filter(
				$details,
				static fn(array $detail): bool => round((float)($detail['value'] ?? 0), 2) != 0.0
			));
			if ($visibleDetails) {
				$generalBalanceDetails[$side][$label] = $visibleDetails;
			} else {
				unset($generalBalanceDetails[$side][$label]);
			}
		}
	}

	foreach ($generalBalance['left'] as $item) {
		$generalBalance['left_total'] += (float)$item['value'];
	}
	foreach ($generalBalance['right'] as $item) {
		$generalBalance['right_total'] += (float)$item['value'];
	}
	$generalBalance['variance'] = $generalBalance['left_total'] - $generalBalance['right_total'];
	if ($generalBalancePostAction === 'manual_check') {
		$manualAmounts = $generalBalancePostData['manual_amounts'] ?? null;
		$manualLabels = $generalBalancePostData['manual_items'] ?? null;
		if (!is_array($manualAmounts) || !is_array($manualLabels) || count($manualAmounts) !== count($generalBalance['right']) || count($manualLabels) !== count($generalBalance['right']) || empty($generalBalance['right'])) {
			throw new \InvalidArgumentException('Enter at least one item for every non-zero balance category before saving the check.');
		}
		$manualTotal = 0.0;
		$manualItems = [];
		foreach (array_values($generalBalance['right']) as $index => $item) {
			$categoryAmounts = $manualAmounts[$index] ?? null;
			$categoryLabels = $manualLabels[$index] ?? null;
			if (!is_array($categoryAmounts) || !is_array($categoryLabels) || !$categoryAmounts || count($categoryAmounts) > 100 || array_keys($categoryAmounts) !== array_keys($categoryLabels)) {
				throw new \InvalidArgumentException('Add at least one valid item for ' . (string)$item['label'] . '.');
			}
			$categoryTotal = 0.0;
			$categoryLineItems = [];
			foreach ($categoryAmounts as $itemIndex => $rawAmount) {
				$rawLabel = $categoryLabels[$itemIndex] ?? null;
				if (!is_scalar($rawAmount) || !is_scalar($rawLabel)) {
					throw new \InvalidArgumentException('Enter a valid item name and amount for ' . (string)$item['label'] . '.');
				}
				$itemLabel = trim((string)$rawLabel);
				$rawAmount = trim((string)$rawAmount);
				if ($itemLabel === '' || strlen($itemLabel) > 190 || $rawAmount === '' || !is_numeric($rawAmount) || !is_finite((float)$rawAmount) || abs((float)$rawAmount) > 999999999999.99) {
					throw new \InvalidArgumentException('Enter a valid item name and amount for ' . (string)$item['label'] . '.');
				}
				$lineAmount = round((float)$rawAmount, 2);
				$categoryTotal += $lineAmount;
				$categoryLineItems[] = [
					'label' => $itemLabel,
					'amount' => $lineAmount,
				];
			}
			$manualTotal += $categoryTotal;
			if (abs($categoryTotal) > 999999999999.99 || abs($manualTotal) > 999999999999.99) {
				throw new \InvalidArgumentException('The entered total is too large to save safely.');
			}
			$manualItems[] = [
				'label' => (string)$item['label'],
				'manual_amount' => round($categoryTotal, 2),
				'system_amount' => round((float)$item['value'], 2),
				'line_items' => $categoryLineItems,
			];
		}
		if (!$db->beginTransaction() || $db->has_error) {
			throw new \RuntimeException('Unable to start the manual balance check: ' . $db->error);
		}
		try {
			$db->query(
				"INSERT INTO backoffice_general_balance_checks
					(branch_id, start_date, end_date, system_total, manual_total, created_by, date_created)
				 VALUES (:branch_id, :start_date, :end_date, :system_total, :manual_total, :created_by, :date_created)",
				[
					'branch_id' => $generalBalanceBranchId > 0 ? $generalBalanceBranchId : null,
					'start_date' => $generalBalanceStartDate !== '' ? $generalBalanceStartDate : null,
					'end_date' => $generalBalanceEndDate !== '' ? $generalBalanceEndDate : null,
					'system_total' => round((float)$generalBalance['right_total'], 2),
					'manual_total' => round($manualTotal, 2),
					'created_by' => (int)(get_session()->id ?? 0),
					'date_created' => date('Y-m-d H:i:s'),
				]
			);
			if ($db->has_error || (int)$db->insert_id <= 0) {
				throw new \RuntimeException('Unable to save the manual balance check: ' . $db->error);
			}
			$checkId = (int)$db->insert_id;
			foreach ($manualItems as $manualItem) {
				$db->query(
					"INSERT INTO backoffice_general_balance_check_items
						(check_id, category_label, manual_amount, system_amount)
					 VALUES (:check_id, :category_label, :manual_amount, :system_amount)",
					[
						'check_id' => $checkId,
						'category_label' => $manualItem['label'],
						'manual_amount' => $manualItem['manual_amount'],
						'system_amount' => $manualItem['system_amount'],
					]
				);
				if ($db->has_error) {
					throw new \RuntimeException('Unable to save a manual balance category: ' . $db->error);
				}
				foreach ($manualItem['line_items'] as $lineItem) {
					$db->query(
						"INSERT INTO backoffice_general_balance_check_line_items
							(check_id, category_label, item_label, amount)
						 VALUES (:check_id, :category_label, :item_label, :amount)",
						[
							'check_id' => $checkId,
							'category_label' => $manualItem['label'],
							'item_label' => $lineItem['label'],
							'amount' => $lineItem['amount'],
						]
					);
					if ($db->has_error) {
						throw new \RuntimeException('Unable to save a manual balance item: ' . $db->error);
					}
				}
			}
			if (!$db->commit() || $db->has_error) {
				throw new \RuntimeException('Unable to complete the manual balance check: ' . $db->error);
			}
		} catch (\Throwable $saveError) {
			$db->rollBack();
			throw $saveError;
		}
		message_success('Manual balance check saved. Open its details to compare the entered amounts with the system snapshot.');
		redirect($admin_route . '/' . $plugin_route . '/general_balance?' . http_build_query(array_merge($returnFilters, ['tab' => 'manual-checks', 'check_id' => $checkId])));
		exit;
	}

	$generalBalanceChecks = $queryRows(
		"SELECT c.id, c.branch_id, c.start_date, c.end_date, c.date_created, c.created_by,
		        c.manual_total, c.system_total, CONCAT_WS(' ', u.first_name, u.last_name) AS created_by_name
		 FROM backoffice_general_balance_checks c
		 LEFT JOIN users u ON u.id = c.created_by
		 ORDER BY c.date_created DESC, c.id DESC"
	);
	$generalBalanceAdjustmentHistory = $queryRows(
		"SELECT a.side, a.amount, a.reason, a.date_created,
		        CONCAT_WS(' ', u.first_name, u.last_name) AS created_by_name
		 FROM backoffice_general_balance_adjustments a
		 LEFT JOIN users u ON u.id = a.created_by
		 ORDER BY a.date_created DESC, a.id DESC"
	);
	if ($generalBalance['variance'] < -0.01) {
		$gap = abs($generalBalance['variance']);
		$generalBalanceVarianceExplanation = [
			'summary' => 'Current recorded balances exceed the accounting basis by GH₵ ' . number_format($gap, 2) . '. This is an unreconciled difference, not a confirmed surplus or a single identified transaction.',
			'checks' => [
				'Compare cash, e-cash, EVD, bank, petty-cash, and credit balances with the supporting receipt, SIM, and commission entries.',
				'Check for opening balances, deposits or transfers, and prior-period funds present in live balances but not represented in this accounting basis.',
				'Check for missing, delayed, duplicated, or incorrectly classified transactions; verify CFA conversion rates and manual adjustments.',
				'Confirm linked cashouts and earned pickup charges are recorded correctly. Paid-out cash is deducted, while pickup charges are counted as income.',
			],
		];
	} elseif ($generalBalance['variance'] > 0.01) {
		$gap = $generalBalance['variance'];
		$generalBalanceVarianceExplanation = [
			'summary' => 'The accounting basis exceeds current recorded balances by GH₵ ' . number_format($gap, 2) . '. This is an unreconciled difference, not a confirmed shortage or a single identified transaction.',
			'checks' => [
				'Check for missing or stale cashdrawer, vault, e-cash, EVD, bank, petty-cash, or credit balances.',
				'Check for recent payouts, expenses, transfers, or withdrawals not yet reflected in live balances.',
				'Review transaction statuses, CFA conversion rates, and manual adjustments against their supporting records.',
			],
		];
	} else {
		$generalBalanceVarianceExplanation = [
			'summary' => 'The accounting basis and current recorded balances are within the GH₵ 0.01 rounding tolerance.',
			'checks' => [],
		];
	}
} catch (\Throwable $e) {
	$generalBalanceError = $e->getMessage();
	$generalBalanceVarianceExplanation = null;
	error_log('[backoffice general balancing] ' . $e->getMessage());
	if ($isCheckDetailsRequest) {
		$respondWithCheckDetails(['error' => 'Unable to load manual balance check details.'], 500);
	}
}

if ($generalBalanceExport) {
	if ($generalBalanceError !== '') {
		http_response_code(500);
		exit('General Balancing PDF could not be generated: ' . htmlspecialchars($generalBalanceError, ENT_QUOTES, 'UTF-8'));
	}
	$autoloadCandidates = [
		dirname(__DIR__, 3) . '/assets/vendor/autoload.php',
		dirname(__DIR__, 3) . '/vendor/autoload.php',
	];
	foreach ($autoloadCandidates as $autoload) {
		if (is_file($autoload)) {
			require_once $autoload;
			if (class_exists('Mpdf\\Mpdf') || class_exists('Dompdf\\Dompdf')) {
				break;
			}
		}
	}
	if (!class_exists('Mpdf\\Mpdf') && !class_exists('Dompdf\\Dompdf')) {
		throw new \RuntimeException('PDF export is unavailable: neither mPDF nor Dompdf is installed. Deploy the application vendor dependencies.');
	}
	$escapePdf = static function ($value): string {
		return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
	};
	$pdfMoney = static function ($amount): string {
		return 'GH₵ ' . number_format((float)$amount, 2);
	};
	$filterParts = [];
	if ($generalBalanceStartDate !== '' || $generalBalanceEndDate !== '') {
		$filterParts[] = 'Recorded activity: ' . ($generalBalanceStartDate !== '' ? $generalBalanceStartDate : 'Beginning') . ' to ' . ($generalBalanceEndDate !== '' ? $generalBalanceEndDate : 'Today');
	}
	if ($generalBalanceSelectedBranchName !== '') {
		$filterParts[] = 'Branch: ' . $generalBalanceSelectedBranchName;
	}
	$filterDescription = !empty($filterParts) ? ' · ' . implode(' · ', $filterParts) : ' · All branches and dates';
	$html = '<h1 style="color:#0f172a;font-size:22pt;margin-bottom:4px">General Balancing</h1><p style="color:#475569;margin-top:0">Live snapshot' . $escapePdf($filterDescription) . ' · Generated ' . $escapePdf(date('d M Y, H:i')) . '</p>';
	$html .= '<h2 style="margin-top:24px;color:#0f172a">Summary</h2>';
	$html .= '<table width="100%" cellpadding="9" cellspacing="0" style="border-collapse:collapse;border:1px solid #cbd5e1"><thead><tr>'
		. '<th width="34%" align="left" style="background-color:#e2e8f0;color:#0f172a;font-weight:bold;border:1px solid #cbd5e1">Accounting basis</th>'
		. '<th width="33%" align="left" style="background-color:#e2e8f0;color:#0f172a;font-weight:bold;border:1px solid #cbd5e1">Current balances</th>'
		. '<th width="33%" align="left" style="background-color:#e2e8f0;color:#0f172a;font-weight:bold;border:1px solid #cbd5e1">Difference</th>'
		. '</tr></thead><tbody><tr>'
		. '<td style="background-color:#eff6ff;color:#0f172a;border:1px solid #cbd5e1;font-size:13pt;font-weight:bold">' . $escapePdf($pdfMoney($generalBalance['left_total'])) . '</td>'
		. '<td style="background-color:#f0fdf4;color:#0f172a;border:1px solid #cbd5e1;font-size:13pt;font-weight:bold">' . $escapePdf($pdfMoney($generalBalance['right_total'])) . '</td>'
		. '<td style="background-color:#f8fafc;color:#0f172a;border:1px solid #cbd5e1;font-size:13pt;font-weight:bold">' . $escapePdf($pdfMoney($generalBalance['variance'])) . '</td>'
		. '</tr></tbody></table>';
	if (!empty($generalBalanceVarianceExplanation)) {
		$html .= '<h2 style="margin-top:20px;color:#0f172a">Variance explanation</h2><p>' . $escapePdf($generalBalanceVarianceExplanation['summary']) . '</p>';
		if (!empty($generalBalanceVarianceExplanation['checks'])) {
			$html .= '<ul>';
			foreach ($generalBalanceVarianceExplanation['checks'] as $check) {
				$html .= '<li>' . $escapePdf($check) . '</li>';
			}
			$html .= '</ul>';
		}
	}
	$html .= '<div style="page-break-before:always;break-before:page;height:1px">&nbsp;</div>';
	foreach (['left' => ['Accounting basis', $generalBalance['left']], 'right' => ['Live balances', $generalBalance['right']]] as $side => [$heading, $items]) {
		$html .= '<h2 style="margin-top:10px;color:#0f172a">' . $escapePdf($heading) . '</h2><table width="100%" cellpadding="7" cellspacing="0" style="border-collapse:collapse;border:1px solid #cbd5e1"><thead style="display:table-header-group"><tr>'
			. '<th width="72%" align="left" style="background-color:#e2e8f0;color:#0f172a;font-weight:bold;border:1px solid #cbd5e1">Entry</th>'
			. '<th width="28%" align="right" style="background-color:#e2e8f0;color:#0f172a;font-weight:bold;border:1px solid #cbd5e1">Total (GHS)</th>'
			. '</tr></thead><tbody>';
		foreach ($items as $item) {
			$html .= '<tr><td style="border-bottom:1px solid #e2e8f0">' . $escapePdf($item['label']) . '</td><td align="right" style="border-bottom:1px solid #e2e8f0">' . $escapePdf($pdfMoney($item['value'])) . '</td></tr>';
			$details = $generalBalanceDetails[$side][$item['label']] ?? [];
			if (empty($details)) {
				$html .= '<tr><td style="padding-left:20px;color:#64748b;border-bottom:1px solid #f1f5f9">No detailed entries</td><td style="border-bottom:1px solid #f1f5f9"></td></tr>';
			}
			foreach ($details as $detail) {
				$html .= '<tr><td style="padding-left:20px;color:#475569;border-bottom:1px solid #f1f5f9">' . $escapePdf($detail['label']) . '</td><td align="right" style="color:#475569;border-bottom:1px solid #f1f5f9">' . $escapePdf($pdfMoney($detail['value'])) . '</td></tr>';
			}
		}
		$html .= '</tbody></table>';
	}
	$fullHtml = '<html><head><meta charset="UTF-8"><style>body{font-family:sans-serif;font-size:10pt;color:#1e293b}table{font-size:9pt;border-collapse:collapse}h1{font-size:20pt}h2{font-size:14pt}td,th{padding:7px;border-bottom:1px solid #e2e8f0}th{color:#0f172a;background-color:#e2e8f0}thead{display:table-header-group;background-color:#e2e8f0;color:#0f172a}</style></head><body>' . $html . '</body></html>';
	$filename = 'general-balancing-' . date('Y-m-d_H-i-s') . '.pdf';
	try {
		if (class_exists('Mpdf\\Mpdf')) {
			try {
				$tempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'tormor-general-balance-mpdf';
				if (!is_dir($tempDir) && !@mkdir($tempDir, 0700, true) && !is_dir($tempDir)) {
					throw new \RuntimeException('Unable to create the PDF temporary directory.');
				}
				if (!is_writable($tempDir)) {
					throw new \RuntimeException('The PDF temporary directory is not writable by the web server.');
				}
				$pdf = new \Mpdf\Mpdf([
					'tempDir' => $tempDir,
					'margin_left' => 14,
					'margin_right' => 14,
					'margin_top' => 16,
					'margin_bottom' => 18,
				]);
				$pdf->SetTitle('General Balancing');
				$pdf->SetHTMLFooter('<div style="font-size:8pt;color:#64748b;border-top:1px solid #e2e8f0;padding-top:5px">General Balancing · Page {PAGENO} of {nbpg}</div>');
				$pdf->WriteHTML($fullHtml);
				$pdfBytes = $pdf->Output('', 'S');
			} catch (\Throwable $mpdfError) {
				if (!class_exists('Dompdf\\Dompdf')) {
					throw $mpdfError;
				}
				error_log('[backoffice general balancing PDF] mPDF failed; trying Dompdf: ' . $mpdfError->getMessage());
				$options = new \Dompdf\Options();
				$options->set('isRemoteEnabled', false);
				$options->set('tempDir', sys_get_temp_dir());
				$dompdf = new \Dompdf\Dompdf($options);
				$dompdf->loadHtml($fullHtml, 'UTF-8');
				$dompdf->setPaper('A4', 'portrait');
				$dompdf->render();
				$pdfBytes = $dompdf->output();
			}
		} else {
			$options = new \Dompdf\Options();
			$options->set('isRemoteEnabled', false);
			$options->set('tempDir', sys_get_temp_dir());
			$dompdf = new \Dompdf\Dompdf($options);
			$dompdf->loadHtml($fullHtml, 'UTF-8');
			$dompdf->setPaper('A4', 'portrait');
			$dompdf->render();
			$pdfBytes = $dompdf->output();
		}
		if (!is_string($pdfBytes) || substr($pdfBytes, 0, 4) !== '%PDF') {
			throw new \RuntimeException('The PDF renderer returned an invalid document.');
		}
	} catch (\Throwable $e) {
		error_log('[backoffice general balancing PDF] ' . $e->getMessage());
		while (ob_get_level()) {
			ob_end_clean();
		}
		http_response_code(500);
		header('Content-Type: text/plain; charset=UTF-8');
		exit('General Balancing PDF could not be generated. Check the server PDF dependencies and writable temporary-directory permissions.');
	}
	while (ob_get_level()) {
		ob_end_clean();
	}
	header('Content-Type: application/pdf');
	header('Content-Disposition: attachment; filename="' . $filename . '"');
	header('Content-Length: ' . strlen($pdfBytes));
	header('Cache-Control: private, no-store, max-age=0');
	echo $pdfBytes;
	exit;
}

require plugin_path('views/admin/general_balance.php');
