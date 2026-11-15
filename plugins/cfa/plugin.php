<?php

/**
 * Plugin name: Cfas
 * Description: A way for admin to manage cfas
 * 
 * 
 **/

set_value([

	'admin_route'	=>'admin',
	'plugin_route'	=>'cfa',
	'tables'		=>[
		'cfa_table' 				=> 'cfas',
		'transaction_table' 				=> 'transactions',
		'wallet_table' 				=> 'wallets',
	],

	'optional_tables'		=>[
	],
	

]);

if(!function_exists('cfa_json_response')){
	function cfa_json_response(array $payload, int $status = 200): void
	{
		$GLOBALS['cfa_json_response_sent'] = true;
		while(ob_get_level() > 0){
			ob_end_clean();
		}
		http_response_code($status);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
		exit;
	}
}

$cfaJsonRoute = URL(1) === 'cfa' && in_array((string)(URL(2) ?? ''), ['data', 'rate-history'], true);
if($cfaJsonRoute){
	ini_set('display_errors', '0');
	ob_start();
	$GLOBALS['cfa_json_response_sent'] = false;
	register_shutdown_function(static function(): void {
		if(!empty($GLOBALS['cfa_json_response_sent'])){
			return;
		}
		while(ob_get_level() > 0){
			ob_end_clean();
		}
		http_response_code(500);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode(['success' => false, 'message' => 'CFA request failed. Please try again.'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
	});
	set_exception_handler(static function(\Throwable $exception): void {
		cfa_json_response(['success' => false, 'message' => 'CFA request failed. Please try again.'], 500);
	});
}


require_once plugin_path('helpers/cashdrawer-sync.php');
require_once plugin_path('helpers/drawer-availability.php');


/** check if all tables exist **/
$db = new \Core\Database;
$tables = get_value()['tables'];

if(!$db->table_exists($tables)){
	if($cfaJsonRoute){
		cfa_json_response(['success' => false, 'message' => 'CFA records are not available.'], 503);
	}
	dd("Missing database tables in ".plugin_id() ." plugin: ". implode(",", $db->missing_tables));
	die;
}

if(!function_exists('cfa_role_names')){
	function cfa_role_names($session = null): array
	{
		$session = $session ?: get_session();
		$rolesRaw = [];
		try {
			$rolesRaw = $session->roles ?? [];
		} catch (\Throwable $e) {
			$rolesRaw = [];
		}
		if (!is_array($rolesRaw)) {
			$rolesRaw = [$rolesRaw];
		}

		$roles = [];
		foreach ($rolesRaw as $role) {
			$role = strtolower(trim((string)$role));
			if ($role !== '') {
				$roles[] = $role;
			}
		}

		return array_values(array_unique($roles));
	}
}

if(!function_exists('cfa_user_can_see_all_transactions')){
	function cfa_user_can_see_all_transactions($session = null): bool
	{
		if (user_can('is_admin') || user_can('is_manager') || user_can('is_operations_manager') || user_can('is_general_manager')) {
			return true;
		}

		$allowedRoles = [
			'admin',
			'manager',
			'operations manager',
			'operation manager',
			'operations_manager',
			'operation_manager',
			'general manager',
			'general_manager',
			'generalmanager',
			'gm',
		];

		return (bool)array_intersect(cfa_role_names($session), $allowedRoles);
	}
}

if(!function_exists('cfa_visibility_condition')){
	function cfa_visibility_condition(string $cfaAlias = 'm', string $inWalletAlias = 'in_wallet', string $outWalletAlias = 'out_wallet'): string
	{
		return '(' . $cfaAlias . '.created_by = :cfa_scope_user_id OR ' . $inWalletAlias . '.assigned = :cfa_scope_user_id OR ' . $outWalletAlias . '.assigned = :cfa_scope_user_id)';
	}
}

if(!function_exists('cfa_visibility_scope')){
	function cfa_visibility_scope($session = null, string $prefix = 'AND', string $cfaAlias = 'm', string $inWalletAlias = 'in_wallet', string $outWalletAlias = 'out_wallet'): array
	{
		$session = $session ?: get_session();
		if (cfa_user_can_see_all_transactions($session)) {
			return ['', []];
		}

		$userId = (int)($session->id ?? 0);
		if ($userId <= 0) {
			return [$prefix . ' 1 = 0', []];
		}

		$condition = str_replace(':cfa_scope_user_id', (string)$userId, cfa_visibility_condition($cfaAlias, $inWalletAlias, $outWalletAlias));
		return [$prefix . ' ' . $condition, []];
	}
}

if(!function_exists('cfa_row_visible_to_user')){
	function cfa_row_visible_to_user($row, $session = null): bool
	{
		$session = $session ?: get_session();
		if (cfa_user_can_see_all_transactions($session)) {
			return true;
		}

		$userId = (string)((int)($session->id ?? 0));
		if ($userId === '0') {
			return false;
		}

		return (string)($row->created_by ?? '') === $userId
			|| (string)($row->in_wallet_assigned ?? '') === $userId
			|| (string)($row->out_wallet_assigned ?? '') === $userId;
	}
}

if(!function_exists('cfa_classify_transaction_type')){
	function cfa_classify_transaction_type(string $type): string
	{
		$type = strtolower(trim($type));
		if ($type === '') {
			return 'all';
		}
		if (in_array($type, ['buy', 'buys'], true)) {
			return 'buys';
		}
		if (in_array($type, ['sell', 'sells'], true)) {
			return 'sells';
		}
		if (strpos($type, 'cfa to gh') === 0) {
			return 'buys';
		}
		if (strpos($type, 'gh') === 0 && strpos($type, 'to cfa') !== false) {
			return 'sells';
		}
		return 'all';
	}
}

if(!function_exists('cfa_normalize_payable_ghs_amount')){
	function cfa_normalize_payable_ghs_amount($amount): float
	{
		$value = max(0, (float)$amount);
		// Payable GHS should settle only on .00, .10, .20 ... .90.
		// Currency-rate math can produce 3224.999999999 for a flat 3225.00, so
		// use a practical tolerance before falling back to a downward settlement.
		$scaled = $value * 10;
		$nearest = round($scaled);
		$normalized = abs($scaled - $nearest) <= 0.00001
			? $nearest / 10
			: floor($scaled + 0.00001) / 10;
		return (float)number_format($normalized, 2, '.', '');
	}
}

if(!function_exists('cfa_build_listing_where')){
	function cfa_build_listing_where(array $filters, $session = null, array &$params = []): string
	{
		$session = $session ?: get_session();
		$clauses = ['m.deleted = 0'];

		$typeFilter = cfa_classify_transaction_type((string)($filters['type'] ?? 'all'));
		if ($typeFilter === 'buys') {
			$clauses[] = "LOWER(m.transaction_type) LIKE 'cfa to gh%'";
		} elseif ($typeFilter === 'sells') {
			$clauses[] = "LOWER(m.transaction_type) LIKE 'gh% to cfa'";
		}

		$find = trim((string)($filters['find'] ?? ''));
		if ($find !== '') {
			$params['find'] = '%' . $find . '%';
			$clauses[] = "(m.description LIKE :find
				OR m.id LIKE :find
				OR m.transaction_status LIKE :find
				OR m.transaction_type LIKE :find
				OR u.first_name LIKE :find
				OR u.last_name LIKE :find
				OR out_wallet.network LIKE :find
				OR out_wallet.name LIKE :find
				OR in_wallet.network LIKE :find
				OR in_wallet.name LIKE :find
				OR m.date_created LIKE :find)";
		}

		$dateFrom = trim((string)($filters['date_from'] ?? ''));
		if ($dateFrom !== '') {
			$params['date_from'] = $dateFrom;
			$clauses[] = 'DATE(m.date_created) >= :date_from';
		}

		$dateTo = trim((string)($filters['date_to'] ?? ''));
		if ($dateTo !== '') {
			$params['date_to'] = $dateTo;
			$clauses[] = 'DATE(m.date_created) <= :date_to';
		}

		if (!cfa_user_can_see_all_transactions($session)) {
			$userId = (int)($session->id ?? 0);
			$clauses[] = str_replace(':cfa_scope_user_id', (string)$userId, cfa_visibility_condition('m', 'in_wallet', 'out_wallet'));
		}

		return 'WHERE ' . implode(' AND ', $clauses);
	}
}

if(!function_exists('cfa_fetch_listing_payload')){
	function cfa_fetch_listing_payload(array $filters, int $limit, int $page, $session = null): array
	{
		$session = $session ?: get_session();
		$db = new \Core\Database;
		$params = [];
		$where = cfa_build_listing_where($filters, $session, $params);
		$offset = max(0, ($page - 1) * $limit);

		$summaryQuery = "
			SELECT
				COUNT(*) AS total_rows,
				COALESCE(SUM(m.amount_cfa), 0) AS total_amount_cfa,
				COALESCE(SUM(m.amount_ghs), 0) AS total_amount_ghs,
				MAX(m.date_created) AS latest_activity,
				COALESCE(SUM(CASE WHEN LOWER(m.transaction_type) LIKE 'cfa to gh%' THEN m.amount_cfa ELSE 0 END), 0) AS total_in_cfa,
				COALESCE(SUM(CASE WHEN LOWER(m.transaction_type) LIKE 'gh% to cfa' THEN m.amount_ghs ELSE 0 END), 0) AS total_in_ghs,
				COALESCE(SUM(CASE WHEN LOWER(m.transaction_type) LIKE 'gh% to cfa' THEN m.amount_cfa ELSE 0 END), 0) AS total_out_cfa,
				COALESCE(SUM(CASE WHEN LOWER(m.transaction_type) LIKE 'cfa to gh%' THEN m.amount_ghs ELSE 0 END), 0) AS total_out_ghs
			FROM cfas AS m
			LEFT JOIN users AS u ON m.created_by = u.id
			LEFT JOIN wallets AS in_wallet ON m.wallet_in_id = in_wallet.id
			LEFT JOIN wallets AS out_wallet ON m.wallet_out_id = out_wallet.id
			$where";
		$summary = $db->query($summaryQuery, $params)[0] ?? null;

		$rowsQuery = "
			SELECT
				m.*,
				u.first_name AS user_first_name,
				u.last_name AS user_last_name,
				u.image AS user_image,
				out_wallet.assigned AS out_wallet_assigned,
				in_wallet.assigned AS in_wallet_assigned
			FROM cfas AS m
			LEFT JOIN users AS u ON m.created_by = u.id
			LEFT JOIN wallets AS in_wallet ON m.wallet_in_id = in_wallet.id
			LEFT JOIN wallets AS out_wallet ON m.wallet_out_id = out_wallet.id
			$where
			ORDER BY m.id DESC
			LIMIT $limit OFFSET $offset";
		$rowsRaw = $db->query($rowsQuery, $params);
		$rowsRaw = is_array($rowsRaw) ? $rowsRaw : [];

		$rows = [];
		foreach ($rowsRaw as $row) {
			$typeKind = cfa_classify_transaction_type((string)($row->transaction_type ?? ''));
			$amountCfa = (float)($row->amount_cfa ?? 0);
			$amountGhs = (float)($row->amount_ghs ?? 0);
			$amountInLabel = $typeKind === 'sells' ? 'GHS' : 'CFA';
			$amountOutLabel = $typeKind === 'sells' ? 'CFA' : 'GHS';
			$amountInValue = $typeKind === 'sells' ? $amountGhs : $amountCfa;
			$amountOutValue = $typeKind === 'sells' ? $amountCfa : $amountGhs;

			$rows[] = [
				'id' => (int)($row->id ?? 0),
				'customer_phone' => $row->customer_phone ?? '',
				'description' => $row->description ?? '',
				'transaction_type' => $row->transaction_type ?? '',
				'transaction_status' => $row->transaction_status ?? '',
				'rejection_reason' => trim((string)($row->rejection_reason ?? '')),
				'type_kind' => $typeKind,
				'amount_cfa' => $amountCfa,
				'amount_ghs' => $amountGhs,
				'amount_in_label' => $amountInLabel,
				'amount_in_value' => $amountInValue,
				'amount_out_label' => $amountOutLabel,
				'amount_out_value' => $amountOutValue,
				'date_created' => $row->date_created ?? '',
				'created_by' => (int)($row->created_by ?? 0),
				'user' => [
					'name' => trim(($row->user_first_name ?? '') . ' ' . ($row->user_last_name ?? '')),
					'image' => get_image($row->user_image ?? null),
				],
			];
		}

		$totalRows = (int)($summary->total_rows ?? 0);
		$totalAmountGhs = (float)($summary->total_amount_ghs ?? 0);

		return [
			'success' => true,
			'rows' => $rows,
			'totals' => [
				'total_amount_cfa' => (float)($summary->total_amount_cfa ?? 0),
				'total_amount_ghs' => $totalAmountGhs,
				'total_in_cfa' => (float)($summary->total_in_cfa ?? 0),
				'total_in_ghs' => (float)($summary->total_in_ghs ?? 0),
				'total_out_cfa' => (float)($summary->total_out_cfa ?? 0),
				'total_out_ghs' => (float)($summary->total_out_ghs ?? 0),
				'count' => $totalRows,
				'avg_ticket_ghs' => $totalRows > 0 ? ($totalAmountGhs / $totalRows) : 0,
			],
			'latest_activity' => $summary->latest_activity ?? null,
			'pagination' => [
				'page' => $page,
				'limit' => $limit,
				'total_rows' => $totalRows,
				'total_pages' => $limit > 0 ? max(1, (int)ceil($totalRows / $limit)) : 1,
			],
			'filters' => [
				'type' => cfa_classify_transaction_type((string)($filters['type'] ?? 'all')),
				'date_from' => trim((string)($filters['date_from'] ?? '')),
				'date_to' => trim((string)($filters['date_to'] ?? '')),
			],
		];
	}
}

if(!function_exists('cfa_ensure_rate_history_schema')){
	function cfa_ensure_rate_history_schema(): void
	{
		try {
			$db = new \Core\Database;
			$db->query("
				CREATE TABLE IF NOT EXISTS cfa_rate_history (
					id INT(11) NOT NULL AUTO_INCREMENT,
					cfa_id INT(11) NULL DEFAULT NULL,
					settings_id INT(11) NULL DEFAULT NULL,
					buying_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
					selling_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
					changed_by INT(11) NULL DEFAULT NULL,
					note VARCHAR(120) NULL DEFAULT NULL,
					date_created DATETIME NOT NULL,
					PRIMARY KEY (id),
					UNIQUE KEY cfa_id (cfa_id),
					KEY settings_id (settings_id),
					KEY date_created (date_created),
					KEY buying_rate (buying_rate),
					KEY selling_rate (selling_rate)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
			");
			$columns = $db->query("SHOW COLUMNS FROM cfa_rate_history LIKE 'cfa_id'");
			if(empty($columns)){
				$db->query("ALTER TABLE cfa_rate_history ADD COLUMN cfa_id INT(11) NULL DEFAULT NULL AFTER id");
				$db->query("ALTER TABLE cfa_rate_history ADD UNIQUE KEY cfa_id (cfa_id)");
			}
		} catch (\Throwable $e) {
			error_log('CFA rate history schema failed: ' . $e->getMessage());
		}
	}
}

if(!function_exists('cfa_record_transaction_rate_history')){
	function cfa_record_transaction_rate_history($row): void
	{
		cfa_ensure_rate_history_schema();

		$cfaId = (int)($row->id ?? 0);
		$buyingRate = (float)($row->buying_rate ?? 0);
		$sellingRate = (float)($row->selling_rate ?? 0);
		if($cfaId <= 0 || ($buyingRate <= 0 && $sellingRate <= 0)){
			return;
		}

		$dateCreated = $row->date_created ?? date('Y-m-d H:i:s');
		$changedBy = (int)($row->updated_by ?? ($row->created_by ?? 0));

		try {
			$db = new \Core\Database;
			$db->query(
				"INSERT INTO cfa_rate_history (cfa_id, settings_id, buying_rate, selling_rate, changed_by, note, date_created)
				VALUES (:cfa_id, :settings_id, :buying_rate, :selling_rate, :changed_by, :note, :date_created)
				ON DUPLICATE KEY UPDATE
					buying_rate = VALUES(buying_rate),
					selling_rate = VALUES(selling_rate),
					changed_by = VALUES(changed_by),
					note = VALUES(note),
					date_created = VALUES(date_created)",
				[
					'cfa_id' => $cfaId,
					'settings_id' => null,
					'buying_rate' => $buyingRate,
					'selling_rate' => $sellingRate,
					'changed_by' => $changedBy,
					'note' => 'cfa-transaction',
					'date_created' => $dateCreated ?: date('Y-m-d H:i:s'),
				]
			);
		} catch (\Throwable $e) {
			error_log('CFA transaction rate history insert failed: ' . $e->getMessage());
		}
	}
}

if(!function_exists('cfa_sync_transaction_rate_history')){
	function cfa_sync_transaction_rate_history(): void
	{
		cfa_ensure_rate_history_schema();

		try {
			$db = new \Core\Database;
			$rows = $db->query(
				"SELECT c.id, c.buying_rate, c.selling_rate, c.created_by, c.updated_by, c.date_created
				FROM cfas AS c
				LEFT JOIN cfa_rate_history AS h ON h.cfa_id = c.id
				WHERE c.deleted = 0
					AND (COALESCE(c.buying_rate, 0) > 0 OR COALESCE(c.selling_rate, 0) > 0)
					AND h.id IS NULL
				ORDER BY c.date_created ASC, c.id ASC
				LIMIT 1000"
			);
			$rows = is_array($rows) ? $rows : [];
			foreach($rows as $row){
				cfa_record_transaction_rate_history($row);
			}

			$countRows = $db->query("SELECT COUNT(*) AS total_rows FROM cfa_rate_history");
			$countRows = is_array($countRows) ? ($countRows[0] ?? null) : null;
			if((int)($countRows->total_rows ?? 0) > 0){
				return;
			}

			$settingsRows = $db->query("SELECT id, buying_rate, selling_rate, date_updated, date_created FROM settings ORDER BY id DESC LIMIT 1");
			$settings = is_array($settingsRows) ? ($settingsRows[0] ?? null) : null;
			if(!$settings){
				return;
			}

			$buyingRate = (float)($settings->buying_rate ?? 0);
			$sellingRate = (float)($settings->selling_rate ?? 0);
			if($buyingRate <= 0 && $sellingRate <= 0){
				return;
			}

			$db->query(
				"INSERT INTO cfa_rate_history (cfa_id, settings_id, buying_rate, selling_rate, changed_by, note, date_created)
				VALUES (:cfa_id, :settings_id, :buying_rate, :selling_rate, :changed_by, :note, :date_created)",
				[
					'cfa_id' => null,
					'settings_id' => (int)($settings->id ?? 0),
					'buying_rate' => $buyingRate,
					'selling_rate' => $sellingRate,
					'changed_by' => 0,
					'note' => 'current-settings-seed',
					'date_created' => $settings->date_updated ?: ($settings->date_created ?: date('Y-m-d H:i:s')),
				]
			);
		} catch (\Throwable $e) {
			error_log('CFA transaction rate history sync failed: ' . $e->getMessage());
		}
	}
}

if(!function_exists('cfa_rate_history_bounds')){
	function cfa_rate_history_bounds(string $range, string $dateFrom, string $dateTo, string $timeFrom = '', string $timeTo = ''): array
	{
		$today = new \DateTimeImmutable('today');
		$end = new \DateTimeImmutable('tomorrow');
		$start = null;

		if($range === 'custom'){
			if($dateFrom !== ''){
				$parsedStart = \DateTimeImmutable::createFromFormat('Y-m-d', $dateFrom);
				if($parsedStart){
					$start = $parsedStart->setTime(0, 0, 0);
				}
			}
			if($dateTo !== ''){
				$parsedEnd = \DateTimeImmutable::createFromFormat('Y-m-d', $dateTo);
				if($parsedEnd){
					$end = $parsedEnd->modify('+1 day')->setTime(0, 0, 0);
				}
			}
		}

		if($range === 'daily' && $dateFrom !== ''){
			$parsedStart = \DateTimeImmutable::createFromFormat('Y-m-d', $dateFrom);
			if($parsedStart){
				$start = $parsedStart->setTime(0, 0, 0);
				$end = $start->modify('+1 day');
			}
		}

		if(!$start){
			$start = match ($range) {
				'daily' => $today,
				'weekly' => $today->modify('-6 days'),
				'monthly' => $today->modify('-1 month'),
				'quarterly' => $today->modify('-3 months'),
				'yearly' => $today->modify('-1 year'),
				default => $today->modify('-1 month'),
			};
		}

		if($end <= $start){
			$end = $start->modify('+1 day');
		}

		$timePattern = '/^\d{2}:\d{2}$/';
		if(preg_match($timePattern, $timeFrom)){
			[$hour, $minute] = array_map('intval', explode(':', $timeFrom));
			$start = $start->setTime(max(0, min(23, $hour)), max(0, min(59, $minute)), 0);
		}
		if(preg_match($timePattern, $timeTo)){
			[$hour, $minute] = array_map('intval', explode(':', $timeTo));
			$end = $end->setTime(max(0, min(23, $hour)), max(0, min(59, $minute)), 59);
			if($end <= $start){
				$end = $end->modify('+1 day');
			}
		}

		return [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];
	}
}

if(!function_exists('cfa_fetch_rate_history_payload')){
	function cfa_fetch_rate_history_payload(array $filters): array
	{
		cfa_sync_transaction_rate_history();

		$range = strtolower(trim((string)($filters['range'] ?? 'monthly')));
		$allowedRanges = ['daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'custom'];
		if(!in_array($range, $allowedRanges, true)){
			$range = 'monthly';
		}
		$interval = strtolower(trim((string)($filters['interval'] ?? 'auto')));
		$allowedIntervals = ['auto', '5m', '15m', '30m', '1h', '1d', '1w'];
		if(!in_array($interval, $allowedIntervals, true)){
			$interval = 'auto';
		}

		[$from, $to] = cfa_rate_history_bounds(
			$range,
			trim((string)($filters['date_from'] ?? '')),
			trim((string)($filters['date_to'] ?? '')),
			trim((string)($filters['time_from'] ?? '')),
			trim((string)($filters['time_to'] ?? ''))
		);
		$db = new \Core\Database;
		$params = ['date_from' => $from, 'date_to' => $to];
		$rows = $db->query(
			"SELECT h.id, h.cfa_id, h.buying_rate, h.selling_rate, h.note, h.date_created,
				COALESCE(c.amount_cfa, 0) AS amount_cfa,
				COALESCE(c.amount_ghs, 0) AS amount_ghs,
				c.transaction_type,
				TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS changed_by_name
			FROM cfa_rate_history AS h
			LEFT JOIN cfas AS c ON h.cfa_id = c.id
			LEFT JOIN users AS u ON h.changed_by = u.id
			WHERE h.date_created >= :date_from AND h.date_created < :date_to
			ORDER BY h.date_created ASC, h.id ASC",
			$params
		);
		$rows = is_array($rows) ? $rows : [];

		if(empty($rows)){
			$latestRows = $db->query(
				"SELECT h.id, h.cfa_id, h.buying_rate, h.selling_rate, h.note, h.date_created,
					COALESCE(c.amount_cfa, 0) AS amount_cfa,
					COALESCE(c.amount_ghs, 0) AS amount_ghs,
					c.transaction_type,
					TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS changed_by_name
				FROM cfa_rate_history AS h
				LEFT JOIN cfas AS c ON h.cfa_id = c.id
				LEFT JOIN users AS u ON h.changed_by = u.id
				ORDER BY h.date_created DESC, h.id DESC
				LIMIT 1"
			);
			$latest = is_array($latestRows) ? ($latestRows[0] ?? null) : null;
			if($latest){
				$rows = [$latest];
			}
		}

		$points = [];
		$changes = 0;
		$previousBuying = null;
		$previousSelling = null;
		$totalCfaVolume = 0.0;
		$totalGhsVolume = 0.0;
		$buyingValues = [];
		$sellingValues = [];
		$spreadValues = [];
		foreach($rows as $row){
			$buyingRate = (float)($row->buying_rate ?? 0);
			$sellingRate = (float)($row->selling_rate ?? 0);
			$spread = (float)number_format($sellingRate - $buyingRate, 2, '.', '');
			$amountCfa = (float)($row->amount_cfa ?? 0);
			$amountGhs = (float)($row->amount_ghs ?? 0);
			if($previousBuying !== null && ($previousBuying !== $buyingRate || $previousSelling !== $sellingRate)){
				$changes++;
			}
			$previousBuying = $buyingRate;
			$previousSelling = $sellingRate;
			$totalCfaVolume += $amountCfa;
			$totalGhsVolume += $amountGhs;
			$buyingValues[] = $buyingRate;
			$sellingValues[] = $sellingRate;
			$spreadValues[] = $spread;

			$points[] = [
				'id' => (int)($row->id ?? 0),
				'cfa_id' => (int)($row->cfa_id ?? 0),
				'buying_rate' => $buyingRate,
				'selling_rate' => $sellingRate,
				'spread' => $spread,
				'amount_cfa' => $amountCfa,
				'amount_ghs' => $amountGhs,
				'transaction_type' => $row->transaction_type ?? '',
				'note' => $row->note ?? '',
				'changed_by' => trim((string)($row->changed_by_name ?? '')),
				'date_created' => $row->date_created ?? '',
				'label' => !empty($row->date_created) ? date('M j, Y H:i', strtotime($row->date_created)) : '',
			];
		}

		$latestPoint = !empty($points) ? $points[count($points) - 1] : null;
		$firstPoint = !empty($points) ? $points[0] : null;
		$intervalSeconds = match ($interval) {
			'5m' => 300,
			'15m' => 900,
			'30m' => 1800,
			'1h' => 3600,
			'1d' => 86400,
			'1w' => 604800,
			default => match ($range) {
				'daily' => 900,
				'weekly' => 3600,
				'monthly' => 86400,
				'quarterly' => 86400,
				'yearly' => 604800,
				default => 3600,
			},
		};

		$buckets = [];
		foreach($points as $point){
			$timestamp = strtotime($point['date_created'] ?? '');
			if($timestamp === false){
				continue;
			}
			$keyTimestamp = (int)(floor($timestamp / $intervalSeconds) * $intervalSeconds);
			$key = date('Y-m-d H:i:s', $keyTimestamp);
			if(empty($buckets[$key])){
				$buckets[$key] = [
					'label' => date($intervalSeconds >= 86400 ? 'M j, Y' : 'M j, H:i', $keyTimestamp),
					'open_buying' => $point['buying_rate'],
					'high_buying' => $point['buying_rate'],
					'low_buying' => $point['buying_rate'],
					'close_buying' => $point['buying_rate'],
					'open_selling' => $point['selling_rate'],
					'high_selling' => $point['selling_rate'],
					'low_selling' => $point['selling_rate'],
					'close_selling' => $point['selling_rate'],
					'open_spread' => $point['spread'],
					'high_spread' => $point['spread'],
					'low_spread' => $point['spread'],
					'close_spread' => $point['spread'],
					'volume_cfa' => 0,
					'volume_ghs' => 0,
					'transactions' => 0,
				];
			}
			$buckets[$key]['high_buying'] = max($buckets[$key]['high_buying'], $point['buying_rate']);
			$buckets[$key]['low_buying'] = min($buckets[$key]['low_buying'], $point['buying_rate']);
			$buckets[$key]['close_buying'] = $point['buying_rate'];
			$buckets[$key]['high_selling'] = max($buckets[$key]['high_selling'], $point['selling_rate']);
			$buckets[$key]['low_selling'] = min($buckets[$key]['low_selling'], $point['selling_rate']);
			$buckets[$key]['close_selling'] = $point['selling_rate'];
			$buckets[$key]['high_spread'] = max($buckets[$key]['high_spread'], $point['spread']);
			$buckets[$key]['low_spread'] = min($buckets[$key]['low_spread'], $point['spread']);
			$buckets[$key]['close_spread'] = $point['spread'];
			$buckets[$key]['volume_cfa'] += $point['amount_cfa'];
			$buckets[$key]['volume_ghs'] += $point['amount_ghs'];
			$buckets[$key]['transactions']++;
		}
		$ohlc = array_values($buckets);

		$average = function(array $values): float {
			$values = array_values(array_filter($values, fn($value) => is_numeric($value)));
			return empty($values) ? 0.0 : (float)number_format(array_sum($values) / count($values), 2, '.', '');
		};

		return [
			'success' => true,
			'range' => $range,
			'interval' => $interval,
			'date_from' => $from,
			'date_to' => $to,
			'points' => $points,
			'ohlc' => $ohlc,
			'summary' => [
				'count' => count($points),
				'latest_buying_rate' => $latestPoint['buying_rate'] ?? 0,
				'latest_selling_rate' => $latestPoint['selling_rate'] ?? 0,
				'latest_spread' => $latestPoint['spread'] ?? 0,
				'buying_change' => $latestPoint && $firstPoint ? (float)number_format($latestPoint['buying_rate'] - $firstPoint['buying_rate'], 2, '.', '') : 0,
				'selling_change' => $latestPoint && $firstPoint ? (float)number_format($latestPoint['selling_rate'] - $firstPoint['selling_rate'], 2, '.', '') : 0,
				'spread_change' => $latestPoint && $firstPoint ? (float)number_format($latestPoint['spread'] - $firstPoint['spread'], 2, '.', '') : 0,
				'highest_buying_rate' => !empty($buyingValues) ? max($buyingValues) : 0,
				'lowest_buying_rate' => !empty($buyingValues) ? min($buyingValues) : 0,
				'highest_selling_rate' => !empty($sellingValues) ? max($sellingValues) : 0,
				'lowest_selling_rate' => !empty($sellingValues) ? min($sellingValues) : 0,
				'average_buying_rate' => $average($buyingValues),
				'average_selling_rate' => $average($sellingValues),
				'average_spread' => $average($spreadValues),
				'highest_spread' => !empty($spreadValues) ? max($spreadValues) : 0,
				'lowest_spread' => !empty($spreadValues) ? min($spreadValues) : 0,
				'total_cfa_volume' => $totalCfaVolume,
				'total_ghs_volume' => $totalGhsVolume,
				'rate_changes' => $changes,
				'rate_changed' => $changes > 0,
				'last_updated' => $latestPoint['date_created'] ?? null,
			],
		];
	}
}

cfa_ensure_rate_history_schema();


/** set cfa permissions for this plugin **/
add_filter('permissions',function($permissions){

	$permissions[] = 'view_customers';
	$permissions[] = 'view_backoffice';
	$permissions[] = 'view_cfa';
	$permissions[] = 'add_cfa';
	$permissions[] = 'edit_cfa';
	$permissions[] = 'delete_cfa';

	return $permissions;
});


/** Add to admin links **/
add_filter('basic-admin_before_admin_links', function ($links) {
    $vars = get_value(); // Retrieve necessary variables

    // Ensure $vars contains required keys
    if (!isset($vars['admin_route'], $vars['plugin_route'])) {
        return $links;
    }

    $session = get_session();
    $user_id = (int)($session->id ?? 0);
    $db = new \Core\Database;
    $pendingSimCount = 0;
    $pendingAgencyCount = 0;
    try {
        if ($user_id > 0) {
            $simRows = $db->query(
                "SELECT COUNT(id) AS c FROM sims WHERE (deleted IS NULL OR deleted = 0) AND LOWER(TRIM(COALESCE(transaction_status, ''))) NOT LIKE 'sold%' AND assigned = :uid",
                ['uid' => $user_id]
            ) ?: [];
            $pendingSimCount = (int)($simRows[0]->c ?? 0);
        }
    } catch (\Throwable $e) {
        $pendingSimCount = 0;
    }
    try {
        if (user_can('view_agencybanking')) {
            $agencyRole = strtolower(trim((string)($session->roles[0] ?? '')));
            $agencyScope = '';
            $agencyParams = [];
            if (in_array($agencyRole, ['momo agent', 'solo agent'], true)) {
                if ($user_id > 0) {
                    $agencyScope = ' AND created_by = :agency_uid';
                    $agencyParams['agency_uid'] = $user_id;
                } else {
                    $agencyScope = ' AND 1 = 0';
                }
            }
            $agencyRows = $db->query(
                "SELECT COUNT(id) AS c
                 FROM agencybankings
                 WHERE (deleted IS NULL OR deleted = 0)
                 AND LOWER(TRIM(COALESCE(transaction_status, ''))) LIKE 'pending%'{$agencyScope}",
                $agencyParams
            ) ?: [];
            $pendingAgencyCount = (int)($agencyRows[0]->c ?? 0);
        }
    } catch (\Throwable $e) {
        error_log('[agencybanking] sidebar pending count failed: ' . $e->getMessage());
    }

    $simsEnabled = user_can('view_sim') || $pendingSimCount > 0;
    $hasCustomerGroupAccess = user_can('view_customers')
        || user_can('view_agencybanking')
        || user_can('view_cfa')
        || user_can('view_ezwich')
        || user_can('view_momoremit')
        || $simsEnabled;

    // Add "Customers" link if the user has permission for any customer-related section
    if ($hasCustomerGroupAccess) {
        $customers = (object)[
            'title'    => 'Customers',
            'id'       => 'view_customers',
            'link'     => '#',
            'icon'     => 'fas fa-users',
            'parent'   => 0,
            'sublinks' => [],
        ];

        // Add "Agencybanking" sublink if the user has permission
        if (user_can('view_agencybanking')) {
            $agencyBanking = (object)[
                'title'    => 'Agency Banking',
                'id'       => 'view_agencybanking',
                'link'     => ROOT . '/' . $vars['admin_route'] . '/agencybanking',
                'icon'     => 'fa-solid  fa-bank',
                'parent'   => $customers->id,
                'count'    => $pendingAgencyCount,
                'minorlinks' => [], // Initialize minorlinks array
            ];

            // Add CFA Exchange to Customers' sublinks
            $customers->sublinks[] = $agencyBanking;
        }

        // Add "CFA Exchange" sublink if the user has permission
        if (user_can('view_cfa')) {
            $cfaExchange = (object)[
                'title'    => 'CFA Exchange',
                'id'       => 'view_cfa',
                'link'     => ROOT . '/' . $vars['admin_route'] . '/' . $vars['plugin_route'],
                'icon'     => 'fa-solid fa-coins',
                'parent'   => $customers->id,
                'minorlinks' => [], // Initialize minorlinks array
            ];

            // Add CFA Exchange to Customers' sublinks
            $customers->sublinks[] = $cfaExchange;
        }

        // Add "Ezwich" sublink if the user has permission
        if (user_can('view_ezwich')) {
            $cfaExchange = (object)[
                'title'    => 'Ezwich',
                'id'       => 'view_ezwich',
                'link'     => ROOT . '/' . $vars['admin_route'] . '/ezwich',
                'icon'     => 'fa-solid  fa-credit-card',
                'parent'   => $customers->id,
                'minorlinks' => [], // Initialize minorlinks array
            ];

            // Add CFA Exchange to Customers' sublinks
            $customers->sublinks[] = $cfaExchange;
        }

        // Add "Momo & Remitances" sublink if the user has permission
        if (user_can('view_momoremit')) {
            $airtimeBundle = (object)[
                'title'    => 'Airtime & Bundles',
                'id'       => 'view_momoremit',
                'link'     => ROOT . '/' . $vars['admin_route'] . '/airtimebundle',
                'icon'     => 'fa-solid  fa-wifi',
                'parent'   => $customers->id,
                'minorlinks' => [], // Initialize minorlinks array
            ];
            $customers->sublinks[] = $airtimeBundle;

        // Add "Momo & Remitances" sublink if the user has permission
            $cfaExchange = (object)[
                'title'    => 'Momo & Remitances',
                'id'       => 'view_momoremit',
                'link'     => ROOT . '/' . $vars['admin_route'] . '/momoremit',
                'icon'     => 'fa-solid  fa-mobile-alt',
                'parent'   => $customers->id,
                'minorlinks' => [], // Initialize minorlinks array
            ];

            // Add CFA Exchange to Customers' sublinks
            $customers->sublinks[] = $cfaExchange;
        }

	    // Add "Payments" link
	    $payment_services = (object)[
	        'title'  => 'Payment Services',
	        'link'   => ROOT . '/' . $vars['admin_route'] . '/payment_services',
	        'icon'   => 'fas fa-credit-card',
	        'parent' => 0,
	        'id'     => 'view_payment',
	    ];
	    // Add Payments to Customers' sublinks
        $customers->sublinks[] = $payment_services;

        // Add "Sims" link
        $sims = (object)[
            'title'  => 'Sims',
            'link'   => ROOT . '/' . $vars['admin_route'] . '/sims',
            'icon'   => 'fas fa-sim-card',
            'parent' => $customers->id,
            'id'     => 'view_sim',
            'count'  => $pendingSimCount > 0 ? (string)$pendingSimCount : '',
        ];
        if ($simsEnabled) {
            $customers->sublinks[] = $sims;
        }

        // Add "Cash Pickup" link
        /*if (user_can('view_cashpickup')) {
            $cashpickup = (object)[
                'title'  => 'Cash Pickup',
                'link'   => ROOT . '/' . $vars['admin_route'] . '/cashpickup',
                'icon'   => 'fa-solid fa-hand-holding-dollar',
                'parent' => 0,
                'id'     => 'view_cashpickup',
            ];
            $customers->sublinks[] = $cashpickup;
        }*/

        // Add Customers to main links
        $links[] = $customers;
    }

    return $links;
});

/** run this after a form submit **/
add_action('controller',function(){

	$req = new \Core\Request;
	$vars = get_value();
	
	$admin_route = $vars['admin_route'];
	$plugin_route = $vars['plugin_route'];

	if(URL(1) == $vars['plugin_route'] && $req->posted())
	{
		$ses = new \Core\Session;
		$cfa = new \Cfas\Cfa;
		$wallet = new \Cfas\Wallet;
		$transaction = new \Cfas\Transaction;
		$cfa_roles_map = new \Cfas\User_roles_map;

		$id = URL(3) ?? null;
		if($id)
			$row = $cfa->first(['id'=>$id]);


		if(
			URL(2) == 'add'
			|| URL(2) == 'buy'
			|| URL(2) == 'sell'
		){
			require plugin_path('controllers/add-controller.php');
		}else
		if(URL(2) == 'edit'){
			
			require plugin_path('controllers/edit-controller.php');
		}else
		if(URL(2) == 'delete'){

			require plugin_path('controllers/delete-controller.php');
		}

	}elseif(URL(1) == $vars['plugin_route'] && URL(2) == 'data'){
		if(!user_can('view_cfa')){
			cfa_json_response([
				'success' => false,
				'message' => 'Access denied',
			], 403);
		}

		$session = get_session();
		$page = max(1, (int)($_GET['page'] ?? 1));
		$limit = min(100, max(10, (int)($_GET['limit'] ?? 10)));
		$filters = [
			'type' => trim((string)($_GET['type'] ?? 'all')),
			'find' => trim((string)($_GET['find'] ?? '')),
			'date_from' => trim((string)($_GET['date_from'] ?? '')),
			'date_to' => trim((string)($_GET['date_to'] ?? '')),
		];

		try {
			cfa_json_response(cfa_fetch_listing_payload($filters, $limit, $page, $session));
		} catch (\Throwable $e) {
			error_log('CFA /data exception: ' . $e->getMessage());
			cfa_json_response([
				'success' => false,
				'message' => 'Unable to load CFA transactions.',
				'error' => DEBUG ? $e->getMessage() : null,
			], 500);
		}
	}elseif(URL(1) == $vars['plugin_route'] && URL(2) == 'rate-history'){
		if(!user_can('view_cfa')){
			cfa_json_response([
				'success' => false,
				'message' => 'Access denied',
			], 403);
		}

		$filters = [
			'range' => trim((string)($_GET['range'] ?? 'monthly')),
			'date_from' => trim((string)($_GET['date_from'] ?? '')),
			'date_to' => trim((string)($_GET['date_to'] ?? '')),
			'time_from' => trim((string)($_GET['time_from'] ?? '')),
			'time_to' => trim((string)($_GET['time_to'] ?? '')),
			'interval' => trim((string)($_GET['interval'] ?? 'auto')),
		];

		try {
			cfa_json_response(cfa_fetch_rate_history_payload($filters));
		} catch (\Throwable $e) {
			error_log('CFA /rate-history exception: ' . $e->getMessage());
			cfa_json_response([
				'success' => false,
				'message' => 'Unable to load CFA rate history.',
				'error' => DEBUG ? $e->getMessage() : null,
			], 500);
		}
	}
});


/** displays the view file **/
add_action('basic-admin_main_content',function(){

	$ses = new \Core\Session;
	$cfa = new \Cfas\Cfa;
	$wallet = new \Cfas\Wallet;
	$setting = new \Cfas\Setting;
	$transaction = new \Cfas\Transaction;
    $deno = new \Cfas\Deno;
	$user = new \Cfas\User;
	$vars = get_value();

	$admin_route = $vars['admin_route'];
	$plugin_route = $vars['plugin_route'];
	
	$errors = $vars['errors'] ?? [];

	if(URL(1) == $vars['plugin_route']){

		$id = URL(3) ?? null;
		if($id){
			$user::$query_id = 'get-users';			
			$row = $cfa->first(['id'=>$id]);
		}        

        //session data
        $user::$query_id = 'get-users';
        $session = get_session();
        if(!empty($session->id)){
            $username = $session->first_name . ' '. $session->last_name;
            $user_id = $session->id;
            $role = $session->roles[0];
        }
        $canSeeAllCfaTransactions = cfa_user_can_see_all_transactions($session);
        //dd($role);

		$currentView = URL(2) ?? '';
		$drawerSnapshot = !empty($user_id) ? \Cfas\cfa_get_cashdrawer_snapshot($user_id) : [];

		if(
			in_array($currentView, ['', 'buys', 'sells'], true)
			&& !($currentView === '' && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['drawer_action']))
		){
			$cfaInitialTypeFilter = $currentView === 'buys' ? 'buys' : ($currentView === 'sells' ? 'sells' : 'all');
			$rows = [];
			$total_amount_cfa = 0;
			$total_amount_ghs = 0;
			require plugin_path('views/admin/list.php');
			return;
		}

		if($currentView === '' && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['drawer_action'])){
			$postPayload = $_POST;
			$redirectBack = $admin_route.'/'.$plugin_route;
			if(!csrf_verify($postPayload)){
				message_fail('Form expired. Please try again.');
				redirect($redirectBack);
				exit;
			}
			$payloadArray = json_decode($postPayload['drawer_cfa_payload'] ?? '[]', true);
			if(!is_array($payloadArray)){
				$payloadArray = [];
			}
			$fallbackAmount = (float)($postPayload['drawer_cfa_fallback'] ?? 0);
			if(!empty($payloadArray)){
				$fallbackAmount = 0;
				foreach($payloadArray as $rowPayload){
					$fallbackAmount += (float)($rowPayload['volume'] ?? 0);
				}
			}
			if($fallbackAmount <= 0){
				message_fail('Add at least one CFA denomination or amount to sync.');
				redirect($redirectBack);
				exit;
			}
			$updated = false;
			if(!empty($user_id)){
				$updated = \Cfas\cfa_manual_fx_adjustment($user_id, $payloadArray, $fallbackAmount, 'add', 'Manual FX Adjustment');
			}
			if($updated){
				message_success('Cashdrawer updated with your CFA denominations.');
			}else{
				message_fail('Unable to update the CFA cashdrawer. Ensure an active drawer exists.');
			}
			redirect($redirectBack);
			exit;
		}

		$wallets = $wallet->where(
			['deleted'=>0, 'transaction_status'=>'Approved'],
			['id'=>$id]
		);
        
        $mywallets = $wallet->where(['deleted'=>0, 'transaction_status'=>'Approved', 'assigned'=>$user_id]);

		$settings = $setting->first(['id'=>1]);

		if(URL(2) == 'add'){			
			$cfa_role = new \Cfas\User_role;
			require plugin_path('views/admin/add.php');
		}else
		if(URL(2) == 'buy'){			
			$cfa_role = new \Cfas\User_role;
			require plugin_path('views/admin/buy.php');
		}else
		if(URL(2) == 'sell'){			
			$cfa_role = new \Cfas\User_role;
			require plugin_path('views/admin/sell.php');
		}else
		if(URL(2) == 'edit'){
			
			$cfa_role = new \Cfas\User_role;
			require plugin_path('views/admin/edit.php');
		}else
		if(URL(2) == 'delete'){

			require plugin_path('views/admin/delete.php');
		}else
		if(URL(2) == 'export_pdf'){
			$query = "
			        SELECT 
				        m.*, 
				        u.first_name AS user_first_name, 
				        u.last_name AS user_last_name, 
				        u.image AS user_image,
				        u_updated.first_name AS updated_by_first_name, 
				        u_updated.last_name AS updated_by_last_name, 
				        u_updated.image AS updated_by_image,
			            out_wallet.name AS out_wallet_name, 
			            out_wallet.network AS out_wallet_network, 
			            in_wallet.name AS in_wallet_name, 
			            in_wallet.network AS in_wallet_network,
			            out_wallet.assigned AS out_wallet_assigned,
			            in_wallet.assigned AS in_wallet_assigned
				    FROM cfas AS m 
				    LEFT JOIN users AS u ON m.created_by = u.id 
				    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
			        LEFT JOIN wallets AS in_wallet ON m.wallet_in_id = in_wallet.id  
			        LEFT JOIN wallets AS out_wallet ON m.wallet_out_id = out_wallet.id  
			    WHERE m.id = :id 
			    LIMIT 1";

				$row = $cfa->query($query, ['id'=>$id])[0] ?? null;
				if (empty($row) || !cfa_row_visible_to_user($row, $session)) {
					message_fail('You do not have access to this CFA transaction.');
					redirect($admin_route . '/' . $plugin_route);
					exit;
				}
				$user::$query_id = 'get-users';

				  $query = "
			        SELECT 
				        m.*, 
				        u.first_name AS user_first_name, 
				        u.last_name AS user_last_name, 
				        u.image AS user_image,
				        u_updated.first_name AS updated_by_first_name, 
				        u_updated.last_name AS updated_by_last_name, 
				        u_updated.image AS updated_by_image,
			            out_wallet.name AS out_wallet_name, 
			            out_wallet.network AS out_wallet_network, 
			            in_wallet.name AS in_wallet_name, 
			            in_wallet.network AS in_wallet_network 
				    FROM cfas AS m 
				    LEFT JOIN users AS u ON m.created_by = u.id 
				    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
			        LEFT JOIN wallets AS in_wallet ON m.wallet_in_id = in_wallet.id  
			        LEFT JOIN wallets AS out_wallet ON m.wallet_out_id = out_wallet.id 
				    WHERE t.cfa_id = :id
				    ORDER BY t.id DESC
				    ";

				// Execute the query with parameters
				$rows = $cfa->query($query, ['id' => $id]);

				//dd($rows);

				$cfas = $cfa->where(
					['deleted'=>0],
					['id'=>$id]
				);

			require plugin_path('views/admin/export_pdf.php');
		}else
		if(URL(2) == 'view'){

			$query = "
			        SELECT 
				        m.*, 
				        u.first_name AS user_first_name, 
				        u.last_name AS user_last_name, 
				        u.image AS user_image,
				        u_updated.first_name AS updated_by_first_name, 
				        u_updated.last_name AS updated_by_last_name, 
				        u_updated.image AS updated_by_image,
			            out_wallet.name AS out_wallet_name, 
			            out_wallet.network AS out_wallet_network, 
			            in_wallet.name AS in_wallet_name, 
			            in_wallet.network AS in_wallet_network,
			            out_wallet.assigned AS out_wallet_assigned,
			            in_wallet.assigned AS in_wallet_assigned
				    FROM cfas AS m 
				    LEFT JOIN users AS u ON m.created_by = u.id 
				    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
			        LEFT JOIN wallets AS in_wallet ON m.wallet_in_id = in_wallet.id  
			        LEFT JOIN wallets AS out_wallet ON m.wallet_out_id = out_wallet.id  
			    WHERE m.id = :id 
			    LIMIT 1";

				$row = $cfa->query($query, ['id'=>$id])[0] ?? null;
				if (empty($row) || !cfa_row_visible_to_user($row, $session)) {
					message_fail('You do not have access to this CFA transaction.');
					redirect($admin_route . '/' . $plugin_route);
					exit;
				}

				$limit = 10;
				$pager = new \Core\Pager($limit);
				$offset = $pager->offset;
				$user::$query_id = 'get-users';

				$pager = new \Core\Pager($limit);
				$cfa->limit = $limit;
				$cfa->offset = $offset;
				$transaction->limit = $limit;
				$transaction->offset = $offset;
				$user::$query_id = 'get-users-transactions';

				  $query = "
			        SELECT 
				        m.*, 
				        u.first_name AS user_first_name, 
				        u.last_name AS user_last_name, 
				        u.image AS user_image,
				        u_updated.first_name AS updated_by_first_name, 
				        u_updated.last_name AS updated_by_last_name, 
				        u_updated.image AS updated_by_image,
			            out_wallet.name AS out_wallet_name, 
			            out_wallet.network AS out_wallet_network, 
			            in_wallet.name AS in_wallet_name, 
			            in_wallet.network AS in_wallet_network 
				    FROM cfas AS m 
				    LEFT JOIN users AS u ON m.created_by = u.id 
				    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
			        LEFT JOIN wallets AS in_wallet ON m.wallet_in_id = in_wallet.id  
			        LEFT JOIN wallets AS out_wallet ON m.wallet_out_id = out_wallet.id 
				    WHERE t.cfa_id = :id
				    ORDER BY t.id DESC
				    ";

				// Execute the query with parameters
				$rows = $cfa->query($query, ['id' => $id]);

				//dd($rows);

				$cfas = $cfa->where(
					['deleted'=>0],
					['id'=>$id]
				);
				$query = "SELECT * FROM denos WHERE trans_id = :trans_id AND count <> 0 GROUP BY denomination ORDER BY denomination DESC";
                $denos = $deno->query($query, ['trans_id'=>$row->id]);

                $query = "SELECT SUM(count) AS total_count, SUM(volume) AS total_volume FROM denos WHERE trans_id =:trans_id AND currency =:currency AND count <> 0 LIMIT 1";
                $denosTotalCfa = $deno->query($query, ['trans_id'=>$row->id, 'currency'=>"CFA"])[0];
                $denosTotalGhs = $deno->query($query, ['trans_id'=>$row->id, 'currency'=>"GHS"])[0];

                if (!empty($denosTotalCfa)) {
                    $totalCountCfa = $denosTotalCfa->total_count ?? 0;
                    $totalVolumeCfa = $denosTotalCfa->total_volume ?? 0;
                }
                if (!empty($denosTotalGhs)) {
                    $totalCountGhs = $denosTotalGhs->total_count ?? 0;
                    $totalVolumeGhs = $denosTotalGhs->total_volume ?? 0;
                }

			require plugin_path('views/admin/view.php');
		}else
		if(URL(2) == 'buys'){
			$cfa = new \Cfas\Cfa;
			$wallet = new \Cfas\Wallet;
			$cfa_roles_map = new \Cfas\User_roles_map;
			$limit = 10;

			$pager = new \Core\Pager($limit);
			$offset = $pager->offset;

			$cfa->limit = $limit;
			$cfa->offset = $offset;
			$user::$query_id = 'get-users';
			[$cfaScopeAndSql, $cfaScopeAndParams] = cfa_visibility_scope($session, 'AND');
			
			if (!empty($_GET['find'])) {
			    $find = '%' . trim($_GET['find']) . '%';
			    $query = "
			        SELECT 
				        m.*, 
				        u.first_name AS user_first_name, 
				        u.last_name AS user_last_name, 
				        u.image AS user_image,
				        u_updated.first_name AS updated_by_first_name, 
				        u_updated.last_name AS updated_by_last_name, 
				        u_updated.image AS updated_by_image,
			            out_wallet.name AS out_wallet_name, 
			            out_wallet.network AS out_wallet_network, 
			            in_wallet.name AS in_wallet_name, 
			            in_wallet.network AS in_wallet_network 
				    FROM cfas AS m 
				    LEFT JOIN users AS u ON m.created_by = u.id 
				    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
			        LEFT JOIN wallets AS in_wallet ON m.wallet_in_id = in_wallet.id  
			        LEFT JOIN wallets AS out_wallet ON m.wallet_out_id = out_wallet.id  
			        WHERE m.transaction_type = :transaction_type
			        AND (
			        	m.description LIKE :find 
			            OR m.id LIKE :find 
			            OR m.transaction_status LIKE :find
			            OR m.transaction_type LIKE :find
			            OR u.first_name LIKE :find 
			            OR u.last_name LIKE :find 
			            OR out_wallet.network LIKE :find 
			            OR out_wallet.name LIKE :find 
			            OR in_wallet.network LIKE :find 
			            OR in_wallet.name LIKE :find 
			            OR m.date_created LIKE :find)
			        {$cfaScopeAndSql}
			        ORDER BY m.id DESC
			        ";

			    $rows = $cfa->query($query, ['transaction_type'=>'CFA to GH₵','find' => $find]);

					$query2 = "SELECT SUM(m.amount_cfa) AS total_cfa FROM cfas AS m
						LEFT JOIN wallets AS in_wallet ON m.wallet_in_id = in_wallet.id
						LEFT JOIN wallets AS out_wallet ON m.wallet_out_id = out_wallet.id
						WHERE m.transaction_type = :transaction_type {$cfaScopeAndSql}";

					// Run the query
					$total_query = $cfa->query($query2, ['transaction_type'=>'CFA to GH₵']);

					// Check if the query was successful and not empty
					if ($total_query && isset($total_query[0])) {
					    $total_cfa = $total_query[0]->total_cfa ?? 0;
					} else {
					    $total_cfa = 0; // Default to 0 if query failed or returned no rows
					}
			  }
			else{
				$query = "
				    SELECT 
				        m.*, 
				        u.first_name AS user_first_name, 
				        u.last_name AS user_last_name, 
				        u.image AS user_image,
				        u_updated.first_name AS updated_by_first_name, 
				        u_updated.last_name AS updated_by_last_name, 
				        u_updated.image AS updated_by_image,
			            out_wallet.name AS out_wallet_name, 
			            out_wallet.network AS out_wallet_network, 
			            in_wallet.name AS in_wallet_name, 
			            in_wallet.network AS in_wallet_network 
				    FROM cfas AS m 
				    LEFT JOIN users AS u ON m.created_by = u.id 
				    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
			        LEFT JOIN wallets AS in_wallet ON m.wallet_in_id = in_wallet.id  
			        LEFT JOIN wallets AS out_wallet ON m.wallet_out_id = out_wallet.id
                    WHERE m.transaction_type = :transaction_type {$cfaScopeAndSql}
				    ORDER BY m.id DESC 
				";


                $query2 = "
                    SELECT 
                        m.*, 
                        u.first_name AS user_first_name, 
                        u.last_name AS user_last_name, 
                        u.image AS user_image,
                        u_updated.first_name AS updated_by_first_name, 
                        u_updated.last_name AS updated_by_last_name, 
                        u_updated.image AS updated_by_image,
                        out_wallet.name AS out_wallet_name, 
                        out_wallet.network AS out_wallet_network, 
                        in_wallet.name AS in_wallet_name, 
                        in_wallet.network AS in_wallet_network 
                    FROM cfas AS m 
                    LEFT JOIN users AS u ON m.created_by = u.id 
                    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
                    LEFT JOIN wallets AS in_wallet ON m.wallet_in_id = in_wallet.id  
                    LEFT JOIN wallets AS out_wallet ON m.wallet_out_id = out_wallet.id
                    WHERE created_by = $user_id AND transaction_type =:transaction_type    
                    ORDER BY m.id DESC 
                ";

                  if (true) {

                        $rows = $cfa->query($query, ['transaction_type'=> 'CFA to GH₵']);
                        //dd($rows);
                        $queryTotal = "SELECT sum(m.amount_cfa) AS total_amount_cfa, sum(m.amount_ghs) AS total_amount_ghs
                        	from cfas AS m
                        	LEFT JOIN wallets AS in_wallet ON m.wallet_in_id = in_wallet.id
                        	LEFT JOIN wallets AS out_wallet ON m.wallet_out_id = out_wallet.id
                        	WHERE m.transaction_type = :transaction_type {$cfaScopeAndSql}";
                        $total_query = $cfa->query($queryTotal, ['transaction_type'=>'CFA to GH₵']);
                        if (!empty($total_query)) {

                            $total_amount_cfa = $total_query[0]->total_amount_cfa ?? 0;
                            $total_amount_ghs = $total_query[0]->total_amount_ghs ?? 0;
                        }

                    }else{

                        $rows = $cfa->query($query2, ['transaction_type'=> 'CFA to GH₵']);
                        //dd($rows);
                        $queryTotal = "SELECT sum(amount_cfa) AS total_amount_cfa, sum(amount_ghs) AS total_amount_ghs from cfas WHERE created_by = $user_id AND transaction_type =:transaction_type ";
                        $total_query = $cfa->query($queryTotal, ['transaction_type'=>'CFA to GH₵']);
                        if (!empty($total_query)) {

                            $total_amount_cfa = $total_query[0]->total_amount_cfa ?? 0;
                            $total_amount_ghs = $total_query[0]->total_amount_ghs ?? 0;
                        }

                    }
			}
			
			require plugin_path('views/admin/buys.php');
		}else
        if(URL(2) == 'sells'){
            $cfa = new \Cfas\Cfa;
            $wallet = new \Cfas\Wallet;
            $user = new \Cfas\User;
            $cfa_roles_map = new \Cfas\User_roles_map;
            $limit = 10;

            $pager = new \Core\Pager($limit);
            $offset = $pager->offset;

            $cfa->limit = $limit;
            $cfa->offset = $offset;
            $user::$query_id = 'get-users';
            [$cfaScopeAndSql, $cfaScopeAndParams] = cfa_visibility_scope($session, 'AND');
            
            if (!empty($_GET['find'])) {
                $find = '%' . trim($_GET['find']) . '%';
                $query = "
                    SELECT 
                        m.*, 
                        u.first_name AS user_first_name, 
                        u.last_name AS user_last_name, 
                        u.image AS user_image,
                        u_updated.first_name AS updated_by_first_name, 
                        u_updated.last_name AS updated_by_last_name, 
                        u_updated.image AS updated_by_image,
                        out_wallet.name AS out_wallet_name, 
                        out_wallet.network AS out_wallet_network, 
                        in_wallet.name AS in_wallet_name, 
                        in_wallet.network AS in_wallet_network 
                    FROM cfas AS m 
                    LEFT JOIN users AS u ON m.created_by = u.id 
                    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
                    LEFT JOIN wallets AS in_wallet ON m.wallet_in_id = in_wallet.id  
                    LEFT JOIN wallets AS out_wallet ON m.wallet_out_id = out_wallet.id  
                    WHERE m.transaction_type = :transaction_type
                    AND (
                        m.description LIKE :find 
                        OR m.id LIKE :find 
                        OR m.transaction_status LIKE :find
                        OR m.transaction_type LIKE :find
                        OR u.first_name LIKE :find 
                        OR u.last_name LIKE :find 
                        OR out_wallet.network LIKE :find 
                        OR out_wallet.name LIKE :find 
                        OR in_wallet.network LIKE :find 
                        OR in_wallet.name LIKE :find 
                        OR m.date_created LIKE :find)
                    {$cfaScopeAndSql}
                    ORDER BY m.id DESC
                    ";

                $rows = $cfa->query($query, ['transaction_type'=>'GH₵ to CFA','find' => $find]);

                    $query2 = "SELECT SUM(m.amount_cfa) AS total_cfa FROM cfas AS m
                    	LEFT JOIN wallets AS in_wallet ON m.wallet_in_id = in_wallet.id
                    	LEFT JOIN wallets AS out_wallet ON m.wallet_out_id = out_wallet.id
                    	WHERE m.transaction_type = :transaction_type {$cfaScopeAndSql}";

                    // Run the query
                    $total_query = $cfa->query($query2, ['transaction_type'=>'GH₵ to CFA']);

                    // Check if the query was successful and not empty
                    if ($total_query && isset($total_query[0])) {
                        $total_cfa = $total_query[0]->total_cfa ?? 0;
                    } else {
                        $total_cfa = 0; // Default to 0 if query failed or returned no rows
                    }
              }
            else{
                $query = "
                    SELECT 
                        m.*, 
                        u.first_name AS user_first_name, 
                        u.last_name AS user_last_name, 
                        u.image AS user_image,
                        u_updated.first_name AS updated_by_first_name, 
                        u_updated.last_name AS updated_by_last_name, 
                        u_updated.image AS updated_by_image,
                        out_wallet.name AS out_wallet_name, 
                        out_wallet.network AS out_wallet_network, 
                        in_wallet.name AS in_wallet_name, 
                        in_wallet.network AS in_wallet_network 
                    FROM cfas AS m 
                    LEFT JOIN users AS u ON m.created_by = u.id 
                    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
                    LEFT JOIN wallets AS in_wallet ON m.wallet_in_id = in_wallet.id  
                    LEFT JOIN wallets AS out_wallet ON m.wallet_out_id = out_wallet.id
                    WHERE m.transaction_type = :transaction_type {$cfaScopeAndSql}
                    ORDER BY m.id DESC 
                ";

                $query2 = "
                    SELECT 
                        m.*, 
                        u.first_name AS user_first_name, 
                        u.last_name AS user_last_name, 
                        u.image AS user_image,
                        u_updated.first_name AS updated_by_first_name, 
                        u_updated.last_name AS updated_by_last_name, 
                        u_updated.image AS updated_by_image,
                        out_wallet.name AS out_wallet_name, 
                        out_wallet.network AS out_wallet_network, 
                        in_wallet.name AS in_wallet_name, 
                        in_wallet.network AS in_wallet_network 
                    FROM cfas AS m 
                    LEFT JOIN users AS u ON m.created_by = u.id 
                    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
                    LEFT JOIN wallets AS in_wallet ON m.wallet_in_id = in_wallet.id  
                    LEFT JOIN wallets AS out_wallet ON m.wallet_out_id = out_wallet.id
                    WHERE created_by = $user_id AND transaction_type =:transaction_type    
                    ORDER BY m.id DESC 
                ";

              if (true) {

                    $rows = $cfa->query($query, ['transaction_type'=> 'GH₵ to CFA']);
                    //dd($rows);
                    $query2 = "SELECT sum(m.amount_cfa) AS total_amount_cfa, sum(m.amount_ghs) AS total_amount_ghs
                    	from cfas AS m
                    	LEFT JOIN wallets AS in_wallet ON m.wallet_in_id = in_wallet.id
                    	LEFT JOIN wallets AS out_wallet ON m.wallet_out_id = out_wallet.id
                    	WHERE m.transaction_type = :transaction_type {$cfaScopeAndSql}";
                    $total_query = $cfa->query($query2, ['transaction_type'=>'GH₵ to CFA']);
                    if (!empty($total_query)) {

                        $total_amount_cfa = $total_query[0]->total_amount_cfa ?? 0;
                        $total_amount_ghs = $total_query[0]->total_amount_ghs ?? 0;
                    }
              }else{

                    $rows = $cfa->query($query2, ['transaction_type'=> 'GH₵ to CFA']);
                    //dd($rows);
                    $query2 = "SELECT sum(amount_cfa) AS total_amount_cfa, sum(amount_ghs) AS total_amount_ghs from cfas WHERE created_by = $user_id AND transaction_type =:transaction_type ";
                    $total_query = $cfa->query($query2, ['transaction_type'=>'GH₵ to CFA']);
                    if (!empty($total_query)) {

                        $total_amount_cfa = $total_query[0]->total_amount_cfa ?? 0;
                        $total_amount_ghs = $total_query[0]->total_amount_ghs ?? 0;
                    }

              }
            }
            
            require plugin_path('views/admin/sells.php');
        }else
        if(URL(2) == 'exportList_pdf'){
            $cfa = new \Cfas\Cfa;
            $wallet = new \Cfas\Wallet;
            $user = new \Cfas\User;
            $cfa_roles_map = new \Cfas\User_roles_map;
            $user::$query_id = 'get-users';

            //session data
	        $user::$query_id = 'get-users';
	        $session = get_session();
	        if(!empty($session->id)){
	            $username = $session->first_name . ' '. $session->last_name;
	            $user_id = $session->id;
	            $role = $session->roles[0];
	        }
	        [$cfaScopeWhereSql, $cfaScopeParams] = cfa_visibility_scope($session, 'WHERE');
            
                $query = "
                    SELECT 
                        m.*, 
                        u.first_name AS user_first_name, 
                        u.last_name AS user_last_name, 
                        u.image AS user_image,
                        u_updated.first_name AS updated_by_first_name, 
                        u_updated.last_name AS updated_by_last_name, 
                        u_updated.image AS updated_by_image,
                        out_wallet.name AS out_wallet_name, 
                        out_wallet.network AS out_wallet_network, 
                        in_wallet.name AS in_wallet_name, 
                        in_wallet.network AS in_wallet_network,
                        out_wallet.assigned AS out_wallet_assigned,
                        in_wallet.assigned AS in_wallet_assigned
                    FROM cfas AS m 
                    LEFT JOIN users AS u ON m.created_by = u.id 
                    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
                    LEFT JOIN wallets AS in_wallet ON m.wallet_in_id = in_wallet.id  
                    LEFT JOIN wallets AS out_wallet ON m.wallet_out_id = out_wallet.id    
                ";

			    $rows = $cfa->query($query . " {$cfaScopeWhereSql} ORDER BY m.id DESC", $cfaScopeParams);
		        $queryTotal = "SELECT 
		            COALESCE(SUM(m.amount_cfa), 0) AS total_amount_cfa,
		            COALESCE(SUM(m.amount_ghs), 0) AS total_amount_ghs 
		            FROM cfas AS m
		            LEFT JOIN wallets AS in_wallet ON m.wallet_in_id = in_wallet.id
		            LEFT JOIN wallets AS out_wallet ON m.wallet_out_id = out_wallet.id
		            {$cfaScopeWhereSql}";

			    $total_query = $cfa->query($queryTotal, $cfaScopeParams);
			    $total_amount_cfa = $total_query[0]->total_amount_cfa ?? 0;
			    $total_amount_ghs = $total_query[0]->total_amount_ghs ?? 0;
			     //dd($total_query);die;
          
            require plugin_path('views/admin/exportList_pdf.php');
        }else
        {
            $cfa = new \Cfas\Cfa;
            $wallet = new \Cfas\Wallet;
            $user = new \Cfas\User;
            $cfa_roles_map = new \Cfas\User_roles_map;
            $limit = 10;

            $pager = new \Core\Pager($limit);
            $offset = $pager->offset;

            $cfa->limit = $limit;
            $cfa->offset = $offset;
            $user::$query_id = 'get-users';
            [$cfaScopeAndSql, $cfaScopeAndParams] = cfa_visibility_scope($session, 'AND');
            [$cfaScopeWhereSql, $cfaScopeWhereParams] = cfa_visibility_scope($session, 'WHERE');
            
            if (!empty($_GET['find'])) {
                $find = '%' . trim($_GET['find']) . '%';
                $query = "
                    SELECT 
                        m.*, 
                        u.first_name AS user_first_name, 
                        u.last_name AS user_last_name, 
                        u.image AS user_image,
                        u_updated.first_name AS updated_by_first_name, 
                        u_updated.last_name AS updated_by_last_name, 
                        u_updated.image AS updated_by_image,
                        out_wallet.name AS out_wallet_name, 
                        out_wallet.network AS out_wallet_network, 
                        in_wallet.name AS in_wallet_name, 
                        in_wallet.network AS in_wallet_network,
                        out_wallet.assigned AS out_wallet_assigned,
                        in_wallet.assigned AS in_wallet_assigned
                    FROM cfas AS m 
                    LEFT JOIN users AS u ON m.created_by = u.id 
                    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
                    LEFT JOIN wallets AS in_wallet ON m.wallet_in_id = in_wallet.id  
                    LEFT JOIN wallets AS out_wallet ON m.wallet_out_id = out_wallet.id  
                    WHERE (
                        m.description LIKE :find 
                        OR m.id LIKE :find 
                        OR m.transaction_status LIKE :find
                        OR m.transaction_type LIKE :find
                        OR u.first_name LIKE :find 
                        OR u.last_name LIKE :find 
                        OR out_wallet.network LIKE :find 
                        OR out_wallet.name LIKE :find 
                        OR in_wallet.network LIKE :find 
                        OR in_wallet.name LIKE :find 
                        OR m.date_created LIKE :find)
                    	{$cfaScopeAndSql}
                    ORDER BY m.id DESC
                    ";

                $rows = $cfa->query($query, array_merge(['find' => $find], $cfaScopeAndParams));

                    $query2 = "SELECT SUM(m.amount_cfa) AS total_cfa FROM cfas AS m
                    	LEFT JOIN wallets AS in_wallet ON m.wallet_in_id = in_wallet.id
                    	LEFT JOIN wallets AS out_wallet ON m.wallet_out_id = out_wallet.id
                    	{$cfaScopeWhereSql}";

                    // Run the query
                    $total_query = $cfa->query($query2, $cfaScopeWhereParams);

                    // Check if the query was successful and not empty
                    if ($total_query && isset($total_query[0])) {
                        $total_cfa = $total_query[0]->total_cfa ?? 0;
                    } else {
                        $total_cfa = 0; // Default to 0 if query failed or returned no rows
                    }
              }
            else{
                $query = "
                    SELECT 
                        m.*, 
                        u.first_name AS user_first_name, 
                        u.last_name AS user_last_name, 
                        u.image AS user_image,
                        u_updated.first_name AS updated_by_first_name, 
                        u_updated.last_name AS updated_by_last_name, 
                        u_updated.image AS updated_by_image,
                        out_wallet.name AS out_wallet_name, 
                        out_wallet.network AS out_wallet_network, 
                        in_wallet.name AS in_wallet_name, 
                        in_wallet.network AS in_wallet_network,
                        out_wallet.assigned AS out_wallet_assigned,
                        in_wallet.assigned AS in_wallet_assigned
                    FROM cfas AS m 
                    LEFT JOIN users AS u ON m.created_by = u.id 
                    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
                    LEFT JOIN wallets AS in_wallet ON m.wallet_in_id = in_wallet.id  
                    LEFT JOIN wallets AS out_wallet ON m.wallet_out_id = out_wallet.id    
                    {$cfaScopeWhereSql}
                    ORDER BY m.id DESC 
                ";

                    $rows = $cfa->query($query, $cfaScopeWhereParams);
                    $queryTotal = "SELECT sum(m.amount_cfa) AS total_amount_cfa, sum(m.amount_ghs) AS total_amount_ghs
                    	from cfas AS m
                    	LEFT JOIN wallets AS in_wallet ON m.wallet_in_id = in_wallet.id
                    	LEFT JOIN wallets AS out_wallet ON m.wallet_out_id = out_wallet.id
                    	{$cfaScopeWhereSql}";
                    $total_query = $cfa->query($queryTotal, $cfaScopeWhereParams);
                    if (!empty($total_query)) {

                        $total_amount_cfa = $total_query[0]->total_amount_cfa ?? 0;
                        $total_amount_ghs = $total_query[0]->total_amount_ghs ?? 0;
                    }

            }
            
            require plugin_path('views/admin/list.php');
        }

	}
});

/** for manipulating data after a query operation **/
add_filter('after_query',function($data){

	
	if(empty($data['result']))
		return $data;

	if($data['query_id'] == 'get-users')
	{
		$role_map = new \Cfas\User_roles_map;
		foreach ($data['result'] as $key => $row) {
			
			$query = "select * from user_roles where disabled = 0 && id in (select role_id from user_roles_map where disabled = 0 && user_id = :user_id)";
			$roles = $role_map->query($query,['user_id'=>$row->id]);
			if($roles)
				$data['result'][$key]->roles = array_column($roles, 'role');
			
			/** get cfa's roles **/
			$user_roles_map = new \Cfas\User_roles_map;
				
			$role_ids = $user_roles_map->where(['user_id'=>$row->id,'disabled'=>0]);
			if($role_ids)
				$data['result'][$key]->role_ids = array_column($role_ids, 'role_id');

		}
	}

	return $data;
});

