<?php

/**
 * Plugin name: Payments
 * Description: A way for admin to manage payments
 * 
 * 
 **/

set_value([

	'admin_route'	=>'admin',
	'plugin_route'	=>'payments',
	'tables'		=>[
		'payment_table' 				=> 'payments',
		'transaction_table' 				=> 'transactions',
	],

	'optional_tables'		=>[
	],
	

]);

$paymentsCashdrawerBridge = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'backoffice' . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'cashdrawer-bridge.php';
if(file_exists($paymentsCashdrawerBridge)){
	require_once $paymentsCashdrawerBridge;
	if(function_exists('BackOfficeCashdrawer\\init')){
		\BackOfficeCashdrawer\init();
	}
}


/** check if all tables exist **/
$db = new \Core\Database;
$tables = get_value()['tables'];

if(!function_exists('payments_runtime_marker_path')){
	function payments_runtime_marker_path(string $name): string
	{
		$dir = __DIR__ . DIRECTORY_SEPARATOR . '.runtime';
		if(!is_dir($dir)){
			@mkdir($dir, 0777, true);
		}
		return $dir . DIRECTORY_SEPARATOR . $name;
	}
}

function payments_ensure_schema($db)
{
	$marker = payments_runtime_marker_path('payments_schema_20260429_payment_modes.done');
	try {
		$colRows = $db->query("SHOW COLUMNS FROM payments") ?: [];
		$cols = [];
		foreach ($colRows as $r) {
			$cols[strtolower((string)($r->Field ?? ''))] = true;
		}
		$runSafe = static function(string $sql) use ($db): void {
			try {
				$db->query($sql);
			} catch (\Throwable $e) {
				// continue with the rest of the schema sync
			}
		};
		if (!isset($cols['category'])) {
			$runSafe("ALTER TABLE payments ADD COLUMN category varchar(50) null");
			$runSafe("ALTER TABLE payments ADD KEY (category)");
		}
		if (!isset($cols['salary_month'])) {
			$runSafe("ALTER TABLE payments ADD COLUMN salary_month varchar(20) null");
			$runSafe("ALTER TABLE payments ADD KEY (salary_month)");
		}
		if (!isset($cols['breakdown_total'])) {
			$runSafe("ALTER TABLE payments ADD COLUMN breakdown_total FLOAT(10,2) DEFAULT 0.00");
		}
		if (!isset($cols['assigned_staff_user_id'])) {
			$runSafe("ALTER TABLE payments ADD COLUMN assigned_staff_user_id INT NULL AFTER breakdown_total");
			$runSafe("ALTER TABLE payments ADD KEY (assigned_staff_user_id)");
		}
		if (!isset($cols['cashdrawer_id'])) {
			$runSafe("ALTER TABLE payments ADD COLUMN cashdrawer_id INT NULL AFTER assigned_staff_user_id");
			$runSafe("ALTER TABLE payments ADD KEY (cashdrawer_id)");
		}
		if (!isset($cols['drawer_user_id'])) {
			$runSafe("ALTER TABLE payments ADD COLUMN drawer_user_id INT NULL AFTER cashdrawer_id");
			$runSafe("ALTER TABLE payments ADD KEY (drawer_user_id)");
		}
		if (!isset($cols['payment_mode'])) {
			$runSafe("ALTER TABLE payments ADD COLUMN payment_mode VARCHAR(20) NULL AFTER drawer_user_id");
			$runSafe("ALTER TABLE payments ADD KEY (payment_mode)");
		}
		if (!isset($cols['ecash_network'])) {
			$runSafe("ALTER TABLE payments ADD COLUMN ecash_network VARCHAR(50) NULL AFTER payment_mode");
			$runSafe("ALTER TABLE payments ADD KEY (ecash_network)");
		}
		if (!isset($cols['denos_json'])) {
			$runSafe("ALTER TABLE payments ADD COLUMN denos_json LONGTEXT NULL AFTER ecash_network");
		}

		if (!$db->table_exists(['payment_salary_breakdowns'])) {
			$runSafe(
				"CREATE TABLE IF NOT EXISTS payment_salary_breakdowns (
					id int unsigned auto_increment,
					payment_id varchar(50) null,
					staff_id varchar(50) null,
					staff_name varchar(255) null,
					staff_role varchar(255) null,
					amount_due FLOAT(10,2) DEFAULT 0.00,
					salary_month varchar(20) null,
					created_by varchar(100) null,
					date_created datetime default null,
					primary key (id),
					key (payment_id),
					key (staff_id),
					key (salary_month)
				)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
			);
		}

		if (!$db->table_exists(['payment_item_breakdowns'])) {
			$runSafe(
				"CREATE TABLE IF NOT EXISTS payment_item_breakdowns (
					id int unsigned auto_increment,
					payment_id varchar(50) null,
					category varchar(50) null,
					item_name varchar(255) null,
					amount FLOAT(10,2) DEFAULT 0.00,
					created_by varchar(100) null,
					date_created datetime default null,
					primary key (id),
					key (payment_id),
					key (category)
				)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
			);
		}
		@file_put_contents($marker, '1');
	} catch (\Throwable $e) {
		// fail silently
	}
}

payments_ensure_schema($db);

if(!function_exists('payments_resolve_admin_route')){
	function payments_resolve_admin_route(?string $fallback = null): string
	{
		$route = trim((string)page());
		if($route !== ''){
			return $route;
		}

		$vars = get_value();
		$fallback = $fallback ?? (string)($vars['admin_route'] ?? 'admin');
		return trim((string)$fallback) !== '' ? (string)$fallback : 'admin';
	}
}

if(!function_exists('payments_generate_unique_id')){
	function payments_generate_unique_id(\Core\Database $db, string $table = 'payments', string $column = 'id'): int
	{
		$base = (int)time();
		$attempt = 0;

		do{
			$candidate = $base + $attempt;
			$exists = $db->query(
				"SELECT {$column} FROM {$table} WHERE {$column} = :id LIMIT 1",
				['id' => $candidate]
			);
			if(empty($exists)){
				return $candidate;
			}
			$attempt++;
		}while($attempt < 1000);

		return $base + random_int(1000, 999999);
	}
}

if(!function_exists('payments_money_label')){
	function payments_money_label(float $amount): string
	{
		return 'GH¢' . number_format((float)$amount, 2);
	}
}

if(!function_exists('payments_standard_ghs_denominations')){
	function payments_standard_ghs_denominations(): array
	{
		return [200, 100, 50, 20, 10, 5, 2, 1, 0.50, 0.20, 0.10];
	}
}

if(!function_exists('payments_supported_ecash_networks')){
	function payments_supported_ecash_networks(): array
	{
		return [
			'MTN' => 'MTN',
			'TELECEL' => 'Telecel',
			'AT' => 'AT',
			'G-MONEY' => 'GMoney',
		];
	}
}

if(!function_exists('payments_normalize_payment_mode')){
	function payments_normalize_payment_mode($value): string
	{
		$value = strtolower(trim((string)$value));
		if(in_array($value, ['cash', 'ecash'], true)){
			return $value;
		}
		if($value === 'e-cash'){
			return 'ecash';
		}
		return '';
	}
}

if(!function_exists('payments_normalize_ecash_network')){
	function payments_normalize_ecash_network($value): string
	{
		$value = strtoupper(trim((string)$value));
		if($value === ''){
			return '';
		}

		$normalized = preg_replace('/[^A-Z0-9]+/', '', $value) ?: '';
		if($normalized === 'MTN'){
			return 'MTN';
		}
		if($normalized === 'TELECEL'){
			return 'TELECEL';
		}
		if(in_array($normalized, ['AT', 'AIRTELTIGO', 'AIRTEL'], true)){
			return 'AT';
		}
		if(in_array($normalized, ['GMONEY', 'GMONEYGH'], true)){
			return 'G-MONEY';
		}
		return '';
	}
}

if(!function_exists('payments_normalize_cash_denominations')){
	function payments_normalize_cash_denominations($rawPayload, float $amount = 0.0): array
	{
		if(function_exists('BackOfficeCashdrawer\\normalizeDenominations')){
			return \BackOfficeCashdrawer\normalizeDenominations($rawPayload, $amount, 'GHS');
		}

		if(is_string($rawPayload)){
			$decoded = json_decode($rawPayload, true);
			$rawPayload = is_array($decoded) ? $decoded : [];
		}
		if(!is_array($rawPayload)){
			return [];
		}

		$out = [];
		foreach($rawPayload as $row){
			$denomination = (float)($row['denomination'] ?? 0);
			$count = (int)($row['count'] ?? 0);
			$volume = (float)($row['volume'] ?? ($denomination * $count));
			if($denomination <= 0 || $count <= 0 || $volume <= 0){
				continue;
			}
			$out[] = [
				'currency' => 'GHS',
				'denomination' => $denomination,
				'count' => $count,
				'volume' => $volume,
			];
		}
		return $out;
	}
}

if(!function_exists('payments_calculate_deno_total')){
	function payments_calculate_deno_total(array $denos): float
	{
		if(function_exists('BackOfficeCashdrawer\\calculateTotal')){
			return (float)\BackOfficeCashdrawer\calculateTotal($denos);
		}

		$total = 0.0;
		foreach($denos as $row){
			$total += (float)($row['volume'] ?? 0);
		}
		return round($total, 2);
	}
}

if(!function_exists('payments_get_cashdrawer_assignable_staff')){
	function payments_get_cashdrawer_assignable_staff(int $excludeUserId = 0): array
	{
		if(!function_exists('BackOfficeCashdrawer\\getAssignableUsersToday')){
			return [];
		}

		try{
			$rows = \BackOfficeCashdrawer\getAssignableUsersToday($excludeUserId);
			if(empty($rows) || !is_array($rows)){
				return [];
			}

			$ids = [];
			foreach($rows as $row){
				$rowId = (int)($row->id ?? 0);
				if($rowId > 0){
					$ids[] = $rowId;
				}
			}

			$roleMap = [];
			if(!empty($ids)){
				$dbRoles = new \Core\Database;
				if($dbRoles->table_exists(['user_roles', 'user_roles_map'])){
					$in = implode(',', array_map('intval', array_unique($ids)));
					$roleRows = $dbRoles->query(
						"SELECT m.user_id, ur.role
						 FROM user_roles_map m
						 JOIN user_roles ur ON ur.id = m.role_id
						 WHERE m.disabled = 0 AND ur.disabled = 0 AND m.user_id IN ($in)
						 ORDER BY m.id ASC"
					) ?: [];

					foreach($roleRows as $roleRow){
						$roleUserId = (int)($roleRow->user_id ?? 0);
						if($roleUserId <= 0){
							continue;
						}
						if(empty($roleMap[$roleUserId])){
							$roleMap[$roleUserId] = [];
						}
						$roleName = trim((string)($roleRow->role ?? ''));
						if($roleName !== ''){
							$roleMap[$roleUserId][] = $roleName;
						}
					}
				}
			}

			$filtered = [];
			foreach($rows as $row){
				$rowId = (int)($row->id ?? 0);
				if($rowId <= 0){
					continue;
				}

				$roles = $roleMap[$rowId] ?? [];
				$exclude = false;
				foreach($roles as $roleName){
					$roleLower = strtolower(trim((string)$roleName));
					if($roleLower !== '' && (strpos($roleLower, 'admin') !== false || strpos($roleLower, 'manager') !== false)){
						$exclude = true;
						break;
					}
				}
				if($exclude){
					continue;
				}

				$row->role_names = $roles;
				$row->role_label = !empty($roles) ? implode(', ', $roles) : '';
				$filtered[] = $row;
			}

			return $filtered;
		}catch(\Throwable $e){
			return [];
		}
	}
}

if(!function_exists('payments_find_cashdrawer_assignable_staff')){
	function payments_find_cashdrawer_assignable_staff(int $userId, int $excludeUserId = 0): ?object
	{
		foreach(payments_get_cashdrawer_assignable_staff($excludeUserId) as $row){
			if((int)($row->id ?? 0) === $userId){
				return $row;
			}
		}
		return null;
	}
}

if(!function_exists('payments_user_has_cashdrawer_today')){
	function payments_user_has_cashdrawer_today(int $userId): bool
	{
		if($userId <= 0 || !function_exists('BackOfficeCashdrawer\\getAssignableUsersToday')){
			return false;
		}

		try{
			$rows = \BackOfficeCashdrawer\getAssignableUsersToday(0);
			foreach((array)$rows as $row){
				if((int)($row->id ?? 0) === $userId){
					return true;
				}
			}
		}catch(\Throwable $e){
			return false;
		}

		return false;
	}
}

if(!function_exists('payments_resolve_cashdrawer_owner')){
	function payments_resolve_cashdrawer_owner(array $data, int $creatorUserId, ?object $existingRow = null): array
	{
		$category = strtoupper(trim((string)($data['category'] ?? ($existingRow->category ?? ''))));
		if($category === 'SALARY'){
			return [
				'ok' => true,
				'user_id' => $creatorUserId,
				'owner_label' => 'The payment creator',
				'mode' => 'creator',
			];
		}

		$assignedStaffUserId = (int)($data['assigned_staff_user_id'] ?? ($existingRow->assigned_staff_user_id ?? 0));
		if($assignedStaffUserId <= 0){
			return [
				'ok' => false,
				'message' => 'Assign the staff cashdrawer that should fund this payment.',
			];
		}

		$staff = payments_find_cashdrawer_assignable_staff($assignedStaffUserId, 0);
		if(empty($staff)){
			return [
				'ok' => false,
				'message' => 'Assigned staff must have a cashdrawer created for today and cannot be admin or manager.',
			];
		}

		$staffName = trim((string)($staff->first_name ?? '') . ' ' . (string)($staff->last_name ?? ''));
		if($staffName === ''){
			$staffName = 'Assigned staff';
		}

		return [
			'ok' => true,
			'user_id' => $assignedStaffUserId,
			'owner_label' => $staffName,
			'mode' => 'assigned',
			'staff' => $staff,
		];
	}
}

if(!function_exists('payments_validate_cashdrawer_ghs_debit')){
	function payments_validate_cashdrawer_ghs_debit(int $drawerUserId, float $amount, string $ownerLabel = 'Selected staff', array $denos = []): array
	{
		if($drawerUserId <= 0){
			return [
				'ok' => false,
				'message' => 'Select the staff cashdrawer that should fund this payment.',
			];
		}

		if($amount <= 0){
			return [
				'ok' => false,
				'message' => 'Payment amount must be greater than zero before approval.',
			];
		}

		if(empty($denos)){
			return [
				'ok' => false,
				'message' => 'Use the currency denominations calculator for cash payments.',
			];
		}

		if(!payments_user_has_cashdrawer_today($drawerUserId)){
			return [
				'ok' => false,
				'message' => $ownerLabel . ' must create a cashdrawer for today before this payment can be approved.',
			];
		}

		if(!function_exists('BackOfficeCashdrawer\\checkCashDenominationSufficiencyCurrency')){
			return [
				'ok' => false,
				'message' => 'Cashdrawer GHS validation helper is unavailable.',
			];
		}

		if(function_exists('BackOfficeCashdrawer\\checkCashDenominationCountSufficiencyCurrency')){
			$countCheck = \BackOfficeCashdrawer\checkCashDenominationCountSufficiencyCurrency($drawerUserId, $denos, $amount, 'GHS');
			if(empty($countCheck['ok'])){
				$shortages = [];
				foreach((array)($countCheck['shortages'] ?? []) as $shortage){
					$denomination = (string)($shortage['denomination'] ?? '0');
					$requiredCount = (int)($shortage['required_count'] ?? 0);
					$availableCount = (int)($shortage['available_count'] ?? 0);
					$shortages[] = 'GHS ' . $denomination . ' needs ' . $requiredCount . ', available ' . $availableCount;
				}
				return [
					'ok' => false,
					'message' => $ownerLabel . ' does not have sufficient cash denominations for this payment. ' . (!empty($shortages) ? implode('; ', $shortages) . '.' : 'Please adjust the note breakdown.'),
				];
			}
		}

		$check = \BackOfficeCashdrawer\checkCashDenominationSufficiencyCurrency($drawerUserId, $denos, $amount, 'GHS');
		if(empty($check['ok'])){
			return [
				'ok' => false,
				'message' => $ownerLabel . ' does not have enough cash GHS. Required ' . payments_money_label((float)($check['required'] ?? $amount)) . ', available ' . payments_money_label((float)($check['available'] ?? 0)) . '.',
			];
		}

		return [
			'ok' => true,
			'check' => $check,
		];
	}
}

if(!function_exists('payments_sync_cashdrawer_ghs_debit')){
	function payments_sync_cashdrawer_ghs_debit(int $drawerUserId, float $amount, int $paymentId, string $status = 'Approved', string $ownerLabel = 'Selected staff', array $denos = []): array
	{
		$validation = payments_validate_cashdrawer_ghs_debit($drawerUserId, $amount, $ownerLabel, $denos);
		if(empty($validation['ok'])){
			return $validation;
		}

		if(!function_exists('BackOfficeCashdrawer\\syncCashMovementCurrency')){
			return [
				'ok' => false,
				'message' => 'Cashdrawer GHS sync helper is unavailable.',
			];
		}

		$drawerContext = \BackOfficeCashdrawer\syncCashMovementCurrency($drawerUserId, $denos, $amount, $status, $paymentId, 'out', 'GHS');
		if(empty($drawerContext)){
			return [
				'ok' => false,
				'message' => 'Failed to sync this payment to the cashdrawer cash GHS balance.',
			];
		}

		return [
			'ok' => true,
			'drawer_context' => $drawerContext,
		];
	}
}

if(!function_exists('payments_validate_ecash_debit')){
	function payments_validate_ecash_debit(int $drawerUserId, string $network, float $amount, string $ownerLabel = 'Selected staff'): array
	{
		$network = payments_normalize_ecash_network($network);
		if($drawerUserId <= 0){
			return [
				'ok' => false,
				'message' => 'Select the staff cashdrawer that should fund this payment.',
			];
		}
		if($network === ''){
			return [
				'ok' => false,
				'message' => 'Select the e-cash network for this payment.',
			];
		}
		if($amount <= 0){
			return [
				'ok' => false,
				'message' => 'Payment amount must be greater than zero before approval.',
			];
		}
		if(!payments_user_has_cashdrawer_today($drawerUserId)){
			return [
				'ok' => false,
				'message' => $ownerLabel . ' must create a cashdrawer for today before this payment can be approved.',
			];
		}
		if(!function_exists('BackOfficeCashdrawer\\checkEcashChannelSufficiency')){
			return [
				'ok' => false,
				'message' => 'Cashdrawer e-cash validation helper is unavailable.',
			];
		}

		$check = \BackOfficeCashdrawer\checkEcashChannelSufficiency($drawerUserId, $network, $amount);
		if(empty($check['ok'])){
			return [
				'ok' => false,
				'message' => $ownerLabel . ' does not have enough ' . $network . ' balance. Required ' . payments_money_label((float)($check['required'] ?? $amount)) . ', available ' . payments_money_label((float)($check['available'] ?? 0)) . '.',
				'channel_key' => $network,
			];
		}

		return [
			'ok' => true,
			'channel_key' => $network,
			'check' => $check,
		];
	}
}

if(!function_exists('payments_sync_ecash_debit')){
	function payments_sync_ecash_debit(int $drawerUserId, string $network, float $amount, int $paymentId, string $status = 'Approved', string $ownerLabel = 'Selected staff'): array
	{
		$validation = payments_validate_ecash_debit($drawerUserId, $network, $amount, $ownerLabel);
		if(empty($validation['ok'])){
			return $validation;
		}

		if(!function_exists('BackOfficeCashdrawer\\syncEcashMovement')){
			return [
				'ok' => false,
				'message' => 'Cashdrawer e-cash sync helper is unavailable.',
			];
		}

		$drawerContext = \BackOfficeCashdrawer\syncEcashMovement($drawerUserId, (string)($validation['channel_key'] ?? $network), $amount, $status, $paymentId, 'out');
		if(empty($drawerContext)){
			return [
				'ok' => false,
				'message' => 'Failed to sync this e-cash payment to the cashdrawer channel.',
			];
		}

		return [
			'ok' => true,
			'drawer_context' => $drawerContext,
			'channel_key' => (string)($validation['channel_key'] ?? $network),
		];
	}
}

if(!function_exists('payments_prepare_funding_payload')){
	function payments_prepare_funding_payload(array $data, float $amount, ?object $existingRow = null): array
	{
		$mode = payments_normalize_payment_mode($data['payment_mode'] ?? ($existingRow->payment_mode ?? ''));
		if($mode === ''){
			return [
				'ok' => false,
				'field' => 'payment_mode',
				'message' => 'Select the mode of payment.',
			];
		}

		if($mode === 'cash'){
			$rawDenos = $data['denos_json'] ?? ($existingRow->denos_json ?? '[]');
			$denosPayload = payments_normalize_cash_denominations($rawDenos, $amount);
			$denosTotal = payments_calculate_deno_total($denosPayload);
			if($denosTotal <= 0){
				return [
					'ok' => false,
					'field' => 'denos_json',
					'message' => 'Use the currency denominations calculator for cash payments.',
				];
			}
			if(abs($denosTotal - $amount) > 0.01){
				return [
					'ok' => false,
					'field' => 'denos_json',
					'message' => 'Calculator total must match the payment amount.',
				];
			}

			return [
				'ok' => true,
				'payment_mode' => 'cash',
				'denos_payload' => $denosPayload,
				'denos_json' => json_encode($denosPayload, JSON_UNESCAPED_SLASHES),
				'ecash_network' => null,
			];
		}

		$network = payments_normalize_ecash_network($data['ecash_network'] ?? ($existingRow->ecash_network ?? ''));
		if($network === ''){
			return [
				'ok' => false,
				'field' => 'ecash_network',
				'message' => 'Select the e-cash network for this payment.',
			];
		}

		return [
			'ok' => true,
			'payment_mode' => 'ecash',
			'ecash_network' => $network,
			'denos_payload' => [],
			'denos_json' => '[]',
		];
	}
}

if(!function_exists('payments_validate_funding_availability')){
	function payments_validate_funding_availability(array $fundingPayload, int $drawerUserId, float $amount, string $ownerLabel = 'Selected staff'): array
	{
		$mode = (string)($fundingPayload['payment_mode'] ?? '');
		if($mode === 'cash'){
			return payments_validate_cashdrawer_ghs_debit($drawerUserId, $amount, $ownerLabel, (array)($fundingPayload['denos_payload'] ?? []));
		}
		if($mode === 'ecash'){
			return payments_validate_ecash_debit($drawerUserId, (string)($fundingPayload['ecash_network'] ?? ''), $amount, $ownerLabel);
		}

		return [
			'ok' => false,
			'message' => 'Select a valid mode of payment.',
		];
	}
}

if(!function_exists('payments_sync_funding')){
	function payments_sync_funding(array $fundingPayload, int $drawerUserId, float $amount, int $paymentId, string $status = 'Approved', string $ownerLabel = 'Selected staff'): array
	{
		$mode = (string)($fundingPayload['payment_mode'] ?? '');
		if($mode === 'cash'){
			return payments_sync_cashdrawer_ghs_debit($drawerUserId, $amount, $paymentId, $status, $ownerLabel, (array)($fundingPayload['denos_payload'] ?? []));
		}
		if($mode === 'ecash'){
			return payments_sync_ecash_debit($drawerUserId, (string)($fundingPayload['ecash_network'] ?? ''), $amount, $paymentId, $status, $ownerLabel);
		}

		return [
			'ok' => false,
			'message' => 'Select a valid mode of payment.',
		];
	}
}

if(!$db->table_exists($tables)){
	dd("Missing database tables in ".plugin_id() ." plugin: ". implode(",", $db->missing_tables));
	die;
}


/** set payment permissions for this plugin **/
add_filter('permissions',function($permissions){

	$permissions[] = 'view_payment';
	$permissions[] = 'view_transactions';
	$permissions[] = 'view_account_statements';
	$permissions[] = 'view_payment_details';
	$permissions[] = 'add_payment';
	$permissions[] = 'edit_payment';
	$permissions[] = 'delete_payment';

	return $permissions;
});


/** lightweight ajax endpoints **/
add_action('controller', function(){
	$vars = get_value();
	$req = new \Core\Request;
	$seg1 = URL(1) ?? '';
	$seg2 = URL(2) ?? '';
	$seg3 = URL(3) ?? '';

	// Support both /payments/ajax_list and /admin/payments/ajax_list
	if($seg1 !== ($vars['plugin_route'] ?? 'payments') && $seg2 !== ($vars['plugin_route'] ?? 'payments')){
		return;
	}
	$action = null;
	if($seg2 === 'ajax_list' || $seg3 === 'ajax_list') $action = 'ajax_list';
	if($seg2 === 'ajax_analytics' || $seg3 === 'ajax_analytics') $action = 'ajax_analytics';
	if(!$action) return;

	ini_set('display_errors', '0');
	error_reporting(E_ERROR | E_PARSE);
	header('Content-Type: application/json');

	if(!user_can('view_payment')){
		http_response_code(403);
		echo json_encode(['success' => false, 'message' => 'Unauthorized']);
		exit;
	}

	try{
		$db = new \Core\Database;

		$q = trim((string)($_GET['q'] ?? ''));
		$status = trim((string)($_GET['status'] ?? ''));
		$category = trim((string)($_GET['category'] ?? ''));
		$dateFrom = trim((string)($_GET['date_from'] ?? ''));
		$dateTo = trim((string)($_GET['date_to'] ?? ''));

		$where = "WHERE m.deleted = 0";
		$params = [];
		if($q !== ''){
			$where .= " AND (m.description LIKE :q OR m.id LIKE :q OR m.transaction_status LIKE :q OR u.first_name LIKE :q OR u.last_name LIKE :q)";
			$params['q'] = '%' . $q . '%';
		}
		if($status !== ''){
			$where .= " AND m.transaction_status = :status";
			$params['status'] = $status;
		}
		if($category !== ''){
			$where .= " AND m.category = :category";
			$params['category'] = $category;
		}
		if($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)){
			$where .= " AND DATE(m.date_created) >= :date_from";
			$params['date_from'] = $dateFrom;
		}
		if($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)){
			$where .= " AND DATE(m.date_created) <= :date_to";
			$params['date_to'] = $dateTo;
		}

		if($action === 'ajax_analytics'){
			// KPI summary
			$sumRow = $db->query(
				"SELECT COUNT(*) AS cnt, SUM(m.amount) AS total, AVG(m.amount) AS avg_amt FROM payments AS m LEFT JOIN users AS u ON m.created_by = u.id {$where}",
				$params
			);
			$summary = (object)[
				'total_count' => !empty($sumRow) ? (int)($sumRow[0]->cnt ?? 0) : 0,
				'total_amount' => !empty($sumRow) ? (float)($sumRow[0]->total ?? 0) : 0.0,
				'avg_amount' => !empty($sumRow) ? (float)($sumRow[0]->avg_amt ?? 0) : 0.0,
				'median_amount' => 0.0,
			];

			$rowsStatus = $db->query(
				"SELECT
					SUM(CASE WHEN m.transaction_status='Approved' THEN 1 ELSE 0 END) AS approved_count,
					SUM(CASE WHEN m.transaction_status='Pending' THEN 1 ELSE 0 END) AS pending_count,
					SUM(CASE WHEN m.transaction_status='Draft' THEN 1 ELSE 0 END) AS draft_count,
					SUM(CASE WHEN m.transaction_status='Rejected' THEN 1 ELSE 0 END) AS rejected_count,
					SUM(CASE WHEN m.transaction_status='Approved' THEN m.amount ELSE 0 END) AS approved_amount,
					SUM(CASE WHEN m.transaction_status='Pending' THEN m.amount ELSE 0 END) AS pending_amount,
					SUM(CASE WHEN m.transaction_status='Draft' THEN m.amount ELSE 0 END) AS draft_amount,
					SUM(CASE WHEN m.transaction_status='Rejected' THEN m.amount ELSE 0 END) AS rejected_amount
				 FROM payments AS m LEFT JOIN users AS u ON m.created_by = u.id {$where}",
				$params
			) ?: [];
			$srow = !empty($rowsStatus) ? $rowsStatus[0] : (object)[];

			// median
			$amountRows = $db->query(
				"SELECT m.amount AS amount FROM payments AS m LEFT JOIN users AS u ON m.created_by = u.id {$where} ORDER BY m.amount ASC",
				$params
			) ?: [];
			$n = count($amountRows);
			if($n > 0){
				$mid = (int)floor(($n-1)/2);
				if($n % 2 === 1){
					$summary->median_amount = (float)($amountRows[$mid]->amount ?? 0);
				}else{
					$a = (float)($amountRows[$mid]->amount ?? 0);
					$b = (float)($amountRows[$mid+1]->amount ?? 0);
					$summary->median_amount = ($a + $b) / 2;
				}
			}

			$byCategory = $db->query(
				"SELECT m.category AS category, COUNT(*) AS cnt, SUM(m.amount) AS total
				 FROM payments AS m LEFT JOIN users AS u ON m.created_by = u.id {$where}
				 GROUP BY m.category
				 ORDER BY total DESC",
				$params
			) ?: [];
			$trend = $db->query(
				"SELECT DATE_FORMAT(m.date_created, '%Y-%m') AS ym, COUNT(*) AS cnt, SUM(m.amount) AS total
				 FROM payments AS m LEFT JOIN users AS u ON m.created_by = u.id {$where}
				 GROUP BY ym
				 ORDER BY ym ASC",
				$params
			) ?: [];

			echo json_encode([
				'success' => true,
				'summary' => $summary,
				'status' => [
					'approved_count' => (int)($srow->approved_count ?? 0),
					'pending_count' => (int)($srow->pending_count ?? 0),
					'draft_count' => (int)($srow->draft_count ?? 0),
					'rejected_count' => (int)($srow->rejected_count ?? 0),
					'approved_amount' => (float)($srow->approved_amount ?? 0),
					'pending_amount' => (float)($srow->pending_amount ?? 0),
					'draft_amount' => (float)($srow->draft_amount ?? 0),
					'rejected_amount' => (float)($srow->rejected_amount ?? 0),
				],
				'by_category' => $byCategory,
				'trend' => $trend,
			]);
			exit;
		}

		$page = (int)($_GET['page'] ?? 1);
		$limit = (int)($_GET['limit'] ?? 30);
		if($limit < 5) $limit = 5;
		if($limit > 100) $limit = 100;
		if($page < 1) $page = 1;
		$offset = ($page - 1) * $limit;

		$sql = "
			SELECT
				m.*,
				u.first_name AS user_first_name,
				u.last_name AS user_last_name,
				u.image AS user_image
			FROM payments AS m
			LEFT JOIN users AS u ON m.created_by = u.id
			{$where}
			ORDER BY m.id DESC
			LIMIT {$limit} OFFSET {$offset}
		";

		$countSql = "
			SELECT COUNT(*) AS total
			FROM payments AS m
			LEFT JOIN users AS u ON m.created_by = u.id
			{$where}
		";

		$sumSql = "
			SELECT SUM(m.amount) AS total_amount
			FROM payments AS m
			LEFT JOIN users AS u ON m.created_by = u.id
			{$where}
		";

		$metricSql = "
			SELECT
				SUM(CASE WHEN m.transaction_status = 'Approved' THEN 1 ELSE 0 END) AS approved_count,
				SUM(CASE WHEN m.transaction_status = 'Pending' THEN 1 ELSE 0 END) AS pending_count,
				SUM(CASE WHEN m.transaction_status = 'Draft' THEN 1 ELSE 0 END) AS draft_count,
				SUM(CASE WHEN m.transaction_status = 'Rejected' THEN 1 ELSE 0 END) AS rejected_count
			FROM payments AS m
			LEFT JOIN users AS u ON m.created_by = u.id
			{$where}
		";

		$rows = $db->query($sql, $params) ?: [];
		$countRow = $db->query($countSql, $params);
		$sumRow = $db->query($sumSql, $params);
		$metricRow = $db->query($metricSql, $params);

		$total = !empty($countRow) ? (int)($countRow[0]->total ?? 0) : 0;
		$totalAmount = !empty($sumRow) ? (float)($sumRow[0]->total_amount ?? 0) : 0.0;
		$totalPages = (int)ceil($total / $limit);
		$mrow = !empty($metricRow) ? $metricRow[0] : (object)[];

		echo json_encode([
			'success' => true,
			'data' => $rows,
			'meta' => [
				'page' => $page,
				'limit' => $limit,
				'total' => $total,
				'total_pages' => $totalPages,
				'total_amount' => $totalAmount,
			],
			'metrics' => [
				'approved' => (int)($mrow->approved_count ?? 0),
				'pending' => (int)($mrow->pending_count ?? 0),
				'draft' => (int)($mrow->draft_count ?? 0),
				'rejected' => (int)($mrow->rejected_count ?? 0),
			],
		]);
		exit;
	}catch(\Throwable $e){
		http_response_code(500);
		echo json_encode(['success' => false, 'message' => 'Failed to load payments']);
		exit;
	}
});


/** add to amin links **/
/*add_filter('basic-admin_before_admin_links',function($links){

	if(user_can('view_payment'))
	{
		$vars = get_value();

		$obj = (object)[];
		$obj->title = 'Payments';
		$obj->link = ROOT . '/'.$vars['admin_route'].'/'.$vars['plugin_route'];
		$obj->icon = 'fa-solid fa-piggy-bank';
		$obj->parent = 0;
		$links[] = $obj;
	}

	return $links;
});*/


/** run this after a form submit **/
add_action('controller',function(){

	$req = new \Core\Request;
	$vars = get_value();
	
	$admin_route = payments_resolve_admin_route((string)($vars['admin_route'] ?? 'admin'));
	$plugin_route = $vars['plugin_route'];
	set_value('admin_route', $admin_route);

	if(URL(1) == $vars['plugin_route'] && $req->posted())
	{
		$ses = new \Core\Session;
		$payment = new \Payments\Payment;
		$wallet = new \Payments\Wallet;
		$transaction = new \Payments\Transaction;
		$payment_roles_map = new \Payments\User_roles_map;

		$id = URL(3) ?? null;
		if($id)
			$row = $payment->first(['id'=>$id]);


		if(URL(2) == 'add'){
			require plugin_path('controllers/add-controller.php');
		}else
		if(URL(2) == 'edit'){
			
			require plugin_path('controllers/edit-controller.php');
		}else
		if(URL(2) == 'delete'){

			require plugin_path('controllers/delete-controller.php');
		}

	}
});


/** displays the view file **/
add_action('basic-admin_main_content',function(){

	$ses = new \Core\Session;
	$payment = new \Payments\Payment;
	$wallet = new \Payments\Wallet;
	$transaction = new \Payments\Transaction;
	$user = new \Payments\User;
	$vars = get_value();

	$admin_route = payments_resolve_admin_route((string)($vars['admin_route'] ?? 'admin'));
	$plugin_route = $vars['plugin_route'];
	set_value('admin_route', $admin_route);
	
	$errors = $vars['errors'] ?? [];

	$users = $user->where(['deleted'=>0]);
	$cashdrawer_assignable_users = payments_get_cashdrawer_assignable_staff();

	if(URL(1) == $vars['plugin_route']){

		$id = URL(3) ?? null;
		if($id){
			$payment::$query_id = 'get-payment';
			
			$row = $payment->first(['id'=>$id]);
		}

		$wallets = $wallet->where(
			['deleted'=>0, 'transaction_status'=>'Approved'],
			['id'=>$id]
		);

		if(URL(2) == 'add'){			
			$payment_role = new \Payments\User_role;
			require plugin_path('views/admin/add.php');
		}else
		if(URL(2) == 'analytics'){
			if(!user_can('view_payment')){
				require plugin_path('views/admin/list.php');
				die;
			}
			require plugin_path('views/admin/analytics.php');
		}else
		if(URL(2) == 'edit'){
			$payment_role = new \Payments\User_role;
			$salary_breakdowns = [];
			$item_breakdowns = [];
			try{
				$dbx = new \Core\Database;
				$cat = strtoupper((string)($row->category ?? ''));
				if($cat === 'SALARY' && $dbx->table_exists(['payment_salary_breakdowns'])){
					$salary_breakdowns = $dbx->query(
						"SELECT * FROM payment_salary_breakdowns WHERE payment_id = :pid ORDER BY id ASC",
						['pid' => $row->id]
					) ?: [];
				}
				if(($cat === 'MAINTENANCE' || $cat === 'OPERATIONAL' || $cat === 'LOGISTICS') && $dbx->table_exists(['payment_item_breakdowns'])){
					$item_breakdowns = $dbx->query(
						"SELECT * FROM payment_item_breakdowns WHERE payment_id = :pid ORDER BY id ASC",
						['pid' => $row->id]
					) ?: [];
				}
			}catch(\Throwable $e){
				$salary_breakdowns = [];
				$item_breakdowns = [];
			}
			require plugin_path('views/admin/edit.php');
		}else
		if(URL(2) == 'delete'){

			require plugin_path('views/admin/delete.php');
		}else
		if(URL(2) == 'ajax_list'){
			// handled in controller action above (ensures JSON-only response)
			die;
		}else
		if(URL(2) == 'view'){

			if(!user_can('view_payment_details')){
				require plugin_path('views/admin/list.php');
				die;
			}

			$query = "
				SELECT
					m.*,
					u.first_name AS user_first_name,
					u.last_name AS user_last_name,
					u.image AS user_image,
					assigned_user.first_name AS assigned_staff_first_name,
					assigned_user.last_name AS assigned_staff_last_name,
					assigned_user.image AS assigned_staff_image,
					u_updated.first_name AS updated_by_first_name,
					u_updated.last_name AS updated_by_last_name,
					u_updated.image AS updated_by_image,
					out_wallet.name AS out_wallet_name,
					out_wallet.network AS out_wallet_network
				FROM payments AS m
				LEFT JOIN users AS u ON m.created_by = u.id
				LEFT JOIN users AS assigned_user ON m.assigned_staff_user_id = assigned_user.id
				LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id
				LEFT JOIN wallets AS out_wallet ON m.wallet_out_id = out_wallet.id
				WHERE m.id = :id AND m.deleted = 0
				LIMIT 1";

			$tmp = $payment->query($query, ['id'=>$id]);
			$row = !empty($tmp) ? $tmp[0] : null;
			$salary_breakdowns = [];
			$item_breakdowns = [];
			if(!empty($row) && strtoupper((string)($row->category ?? '')) === 'SALARY'){
				try{
					$db = new \Core\Database;
					if($db->table_exists(['payment_salary_breakdowns'])){
						$salary_breakdowns = $db->query(
							"SELECT * FROM payment_salary_breakdowns WHERE payment_id = :pid ORDER BY id ASC",
							['pid' => $row->id]
						) ?: [];
					}
				}catch(\Throwable $e){ $salary_breakdowns = []; }
			}
			if(!empty($row)){
				$cat = strtoupper((string)($row->category ?? ''));
				if($cat === 'MAINTENANCE' || $cat === 'OPERATIONAL' || $cat === 'LOGISTICS'){
					try{
						$db = new \Core\Database;
						if($db->table_exists(['payment_item_breakdowns'])){
							$item_breakdowns = $db->query(
								"SELECT * FROM payment_item_breakdowns WHERE payment_id = :pid ORDER BY id ASC",
								['pid' => $row->id]
							) ?: [];
						}
					}catch(\Throwable $e){ $item_breakdowns = []; }
				}
			}

			require plugin_path('views/admin/view.php');
		}else
		{
			$payment = new \Payments\Payment;
			$wallet = new \Payments\Wallet;
			$user = new \Payments\User;
			$payment_roles_map = new \Payments\User_roles_map;
			$limit = 30;

			$pager = new \Core\Pager($limit);
			$offset = $pager->offset;

			$payment->limit = $limit;
			$payment->offset = $offset;
			$payment::$query_id = 'get-payment';
			
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
			            out_wallet.network AS out_wallet_network 
				    FROM payments AS m 
				    LEFT JOIN users AS u ON m.created_by = u.id 
				    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
			        LEFT JOIN wallets AS out_wallet ON m.wallet_out_id = out_wallet.id  
			        WHERE (
			        	m.description LIKE :find 
			            OR m.id LIKE :find 
			            OR m.transaction_status LIKE :find
			            OR u.first_name LIKE :find 
			            OR u.last_name LIKE :find 
			            OR out_wallet.network LIKE :find 
			            OR out_wallet.name LIKE :find 
			            OR m.date_created LIKE :find)
			        LIMIT $limit OFFSET $offset";

			    $rows = $payment->query($query, ['find' => $find]);

					$query2 = "SELECT SUM(amount) AS total_amount FROM payments";

					// Run the query
					$total_query = $payment->query($query2);

					// Check if the query was successful and not empty
					if ($total_query && isset($total_query[0])) {
					    $total_payment = $total_query[0]->total_payment ?? 0;
					} else {
					    $total_payment = 0; // Default to 0 if query failed or returned no rows
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
			            out_wallet.network AS out_wallet_network 
				    FROM payments AS m 
				    LEFT JOIN users AS u ON m.created_by = u.id 
				    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
			        LEFT JOIN wallets AS out_wallet ON m.wallet_out_id = out_wallet.id  
				    ORDER BY m.id DESC
				";

				$rows = $payment->query($query);
				//dd($rows);
				$query2 = "SELECT sum(amount) AS total_amount from payments ";
				$total_query = $payment->query($query2);

				if (!empty($total_query)) {
					$total_amount = $total_query[0]->total_amount ?? 0;
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

	if($data['query_id'] == 'get-payment')
	{
		$role_map = new \Payments\User_roles_map;
		foreach ($data['result'] as $key => $row) {
			
			$query = "select * from payment_roles where disabled = 0 && id in (select role_id from payment_roles_map where disabled = 0 && payment_id = :payment_id)";
			$roles = $role_map->query($query,['payment_id'=>$row->id]);
			if($roles)
				$data['result'][$key]->roles = array_column($roles, 'role');
			
			/** get payment's roles **/
			$payment_roles_map = new \Payments\User_roles_map;
				
			$role_ids = $payment_roles_map->where(['payment_id'=>$row->id,'disabled'=>0]);
			if($role_ids)
				$data['result'][$key]->role_ids = array_column($role_ids, 'role_id');

		}
	}

	return $data;
});

