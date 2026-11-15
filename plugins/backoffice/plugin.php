<?php

use function BackOfficeCashdrawer\calculateTotal;
use function BackOfficeCashdrawer\getAssignableUsersToday;
use function BackOfficeCashdrawer\getActiveDrawer;
use function BackOfficeCashdrawer\normalizeDenominations;
use function BackOfficeCashdrawer\syncCashMovement;
use function BackOfficeCashdrawer\checkGhsDenominationSufficiency;
use function BackOfficeCashdrawer\checkCashDenominationSufficiencyCurrency;
use function BackOfficeCashdrawer\checkEcashChannelSufficiency;
use function BackOfficeCashdrawer\checkCashDenominationCountSufficiencyCurrency;

/**
 * Plugin name: MyWallet
 * Description: A way for admin to manage account
 * 
 * 
 **/

require_once __DIR__ . '/helpers/cashdrawer-bridge.php';
require_once __DIR__ . '/helpers/whatsapp-notifications.php';
require_once __DIR__ . '/helpers/shared-account-reconciliation.php';
\BackOfficeCashdrawer\init();

set_value([

	'admin_route'	=>'admin',
	'plugin_route'	=>'back_office',
	'tables'		=>[
		'requests_backoffice_table' 				=> 'requests_backoffice',
		'commissions_table' 				=> 'commissions',
	],

	'optional_tables'		=>[
		'reconciliation_table' 				=> 'reconciliations',
	],
	

]);


/** check if all tables exist **/
$db = new \Core\Database;
$tables = get_value()['tables'];

if(!$db->table_exists($tables)){
	dd("Missing database tables in ".plugin_id() ." plugin: ". implode(",", $db->missing_tables));
	die;
}

if(!function_exists('backoffice_runtime_marker_path')){
	function backoffice_runtime_marker_path(string $name): string
	{
		$dir = __DIR__ . DIRECTORY_SEPARATOR . '.runtime';
		if(!is_dir($dir)){
			@mkdir($dir, 0777, true);
		}
		return $dir . DIRECTORY_SEPARATOR . $name;
	}
}

if(!function_exists('backoffice_ensure_reject_reason_schema')){
	function backoffice_ensure_reject_reason_schema(): void
	{
		static $ensured = false;
		if ($ensured) return;
		$marker = backoffice_runtime_marker_path('backoffice_reject_reason_schema_20260427.done');
		if(file_exists($marker)){
			return;
		}
		try {
			$db = new \Core\Database;
			$targets = [
				['table' => 'requests', 'column' => 'reject_reason'],
				['table' => 'payments_backoffice', 'column' => 'reject_reason'],
			];
			foreach ($targets as $t) {
				$rows = $db->query(
					"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :t AND COLUMN_NAME = :c LIMIT 1",
					['db' => DB_NAME, 't' => $t['table'], 'c' => $t['column']]
				);
				if (empty($rows)) {
					try {
						$db->query("ALTER TABLE {$t['table']} ADD COLUMN {$t['column']} TEXT NULL");
					} catch (\Throwable $e) {
						continue;
					}
				}
			}
			@file_put_contents($marker, '1');
		} catch (\Throwable $e) {
			return;
		}
		$ensured = true;
	}
}

backoffice_ensure_reject_reason_schema();

if(!function_exists('backoffice_ensure_commission_assignment_schema')){
	function backoffice_ensure_commission_assignment_schema(): void
	{
		static $ensured = false;
		if ($ensured) {
			return;
		}
		$ensured = true;

		$marker = backoffice_runtime_marker_path('backoffice_commission_assignment_schema_20260505.done');

		try {
			$db = new \Core\Database;
			if (!$db->table_exists(['commissions'])) {
				return;
			}

			$columns = [
				'assigned' => "ALTER TABLE commissions ADD COLUMN assigned BIGINT UNSIGNED NULL AFTER pay_to",
				'sim_assignment_id' => "ALTER TABLE commissions ADD COLUMN sim_assignment_id BIGINT UNSIGNED NULL AFTER assigned",
				'commission_field' => "ALTER TABLE commissions ADD COLUMN commission_field VARCHAR(80) NULL AFTER sim_assignment_id",
				'proof_image' => "ALTER TABLE commissions ADD COLUMN proof_image VARCHAR(255) NULL AFTER sim_assignment_id",
				'reject_reason' => "ALTER TABLE commissions ADD COLUMN reject_reason TEXT NULL AFTER proof_image",
				'assignment_snapshot_json' => "ALTER TABLE commissions ADD COLUMN assignment_snapshot_json LONGTEXT NULL AFTER reject_reason",
				'bulk_parent_id' => "ALTER TABLE commissions ADD COLUMN bulk_parent_id BIGINT UNSIGNED NULL AFTER id",
			];

			$existingColumns = [];
			$existingRows = $db->query("SHOW COLUMNS FROM commissions") ?: [];
			foreach ($existingRows as $existingRow) {
				$field = strtolower(trim((string)($existingRow->Field ?? $existingRow->field ?? '')));
				if ($field !== '') {
					$existingColumns[$field] = true;
				}
			}
			if (file_exists($marker)) {
				$missing = false;
				foreach (array_keys($columns) as $columnName) {
					if (empty($existingColumns[$columnName])) {
						$missing = true;
						break;
					}
				}
				if (!$missing) {
					return;
				}
			}

			foreach ($columns as $column => $sql) {
				if (!empty($existingColumns[$column])) {
					continue;
				}
				try {
					$db->query($sql);
				} catch (\Throwable $e) {
					continue;
				}
			}

			foreach ([
				"CREATE INDEX idx_commissions_assigned ON commissions (assigned)",
				"CREATE INDEX idx_commissions_sim_assignment ON commissions (sim_assignment_id)",
				"CREATE INDEX idx_commissions_commission_field ON commissions (commission_field)",
			] as $indexSql) {
				try {
					$db->query($indexSql);
				} catch (\Throwable $e) {
					continue;
				}
			}

			@file_put_contents($marker, '1');
		} catch (\Throwable $e) {
			return;
		}
	}
}

backoffice_ensure_commission_assignment_schema();

if(!function_exists('backoffice_commission_role_candidates')){
	function backoffice_commission_role_candidates(): array
	{
		return [
			'admin',
			'operations manager',
			'operation manager',
			'operations_manager',
			'operation_manager',
			'operationsmanager',
			'operationmanager',
			'is_operations_manager',
			'general manager',
			'general_manager',
			'generalmanager',
			'gm',
		];
	}
}

if(!function_exists('backoffice_commission_user_roles')){
	function backoffice_commission_user_roles(object $row): array
	{
		$roles = $row->roles ?? [];
		if (!is_array($roles)) {
			$roles = [$roles];
		}

		$normalized = [];
		foreach ($roles as $role) {
			$role = strtolower(trim((string)$role));
			if ($role !== '') {
				$normalized[] = $role;
			}
		}

		return array_values(array_unique($normalized));
	}
}

if(!function_exists('backoffice_commission_row_is_approver')){
	function backoffice_commission_row_is_approver(object $row): bool
	{
		return (bool)array_intersect(backoffice_commission_user_roles($row), backoffice_commission_role_candidates());
	}
}

if(!function_exists('backoffice_commission_creator_can_self_assign')){
	function backoffice_commission_creator_can_self_assign($session = null): bool
	{
		return backoffice_user_is_admin($session)
			|| backoffice_user_is_general_manager($session)
			|| backoffice_user_is_operations_manager($session);
	}
}

if(!function_exists('backoffice_commission_assignable_users')){
	function backoffice_commission_assignable_users(int $excludeUserId = 0, $session = null, bool $includeEligibleSelf = false): array
	{
		try {
			$session = $session ?: get_session();
			$rows = getAssignableUsersToday($excludeUserId);
			$rows = array_values(array_filter($rows, function($row){
				return backoffice_commission_row_is_approver($row);
			}));

			if ($includeEligibleSelf && backoffice_commission_creator_can_self_assign($session)) {
				$selfId = (int)($session->id ?? 0);
				if ($selfId > 0) {
					$foundSelf = false;
					foreach ($rows as $existingRow) {
						if ((int)($existingRow->id ?? 0) === $selfId) {
							$foundSelf = true;
							break;
						}
					}
					if (!$foundSelf) {
						$selfRow = (object)[
							'id' => $selfId,
							'first_name' => (string)($session->first_name ?? ''),
							'last_name' => (string)($session->last_name ?? ''),
							'roles' => $session->roles ?? [],
						];
						if (backoffice_commission_row_is_approver($selfRow)) {
							array_unshift($rows, $selfRow);
						}
					}
				}
			}

			return $rows;
		} catch (\Throwable $e) {
			return [];
		}
	}
}

if(!function_exists('backoffice_payment_is_telecel_type')){
	function backoffice_payment_is_telecel_type(string $paymentType): bool
	{
		return strpos(strtoupper(trim($paymentType)), 'TELECEL') !== false;
	}
}

if(!function_exists('backoffice_payment_amount_is_whole_number')){
	function backoffice_payment_amount_is_whole_number(mixed $amount): bool
	{
		$amountValue = (float)$amount;
		return abs($amountValue - round($amountValue)) < 0.00001;
	}
}

if(!function_exists('backoffice_payment_resolved_channel_key')){
	function backoffice_payment_resolved_channel_key(string $paymentType): string
	{
		$providerRaw = strtoupper(trim($paymentType));
		if ($providerRaw === '') {
			return '';
		}

		$slug = preg_replace('/[^A-Z0-9]+/', '_', $providerRaw);
		$slug = trim((string)$slug, '_');
		if (in_array($slug, ['AT_EVD', 'MTN_EVD', 'TELECEL_EVD'], true)) {
			return $slug;
		}

		if (strpos($providerRaw, 'TELECEL') !== false) {
			return 'TELECEL';
		}
		if (strpos($providerRaw, 'MTN') !== false) {
			return 'MTN';
		}
		if (strpos($providerRaw, 'AT') !== false || strpos($providerRaw, 'AIRTELTIGO') !== false) {
			return 'AT';
		}

		return $slug;
	}
}

if(!function_exists('backoffice_payment_bulk_channels')){
	function backoffice_payment_bulk_channels(string $network): array
	{
		$network = strtoupper(trim($network));
		if ($network === 'E-CASH') {
			return ['AT', 'G-MONEY', 'GHANAPAY', 'MTN', 'TELECEL', 'ZEEPAY'];
		}
		if ($network === 'EVD') {
			return ['AT_EVD', 'MTN_EVD', 'TELECEL_EVD'];
		}
		return [];
	}
}

if(!function_exists('backoffice_payment_normalize_breakdown')){
	function backoffice_payment_normalize_breakdown(mixed $raw, string $network): array
	{
		$allowed = backoffice_payment_bulk_channels($network);
		if (empty($allowed)) {
			return ['items' => [], 'total' => 0.0, 'errors' => []];
		}

		if (is_string($raw)) {
			$decoded = json_decode(trim($raw) === '' ? '[]' : $raw, true);
		} elseif (is_object($raw)) {
			$decoded = get_object_vars($raw);
		} else {
			$decoded = $raw;
		}
		if (!is_array($decoded)) {
			$decoded = [];
		}

		$allowedMap = array_fill_keys($allowed, true);
		$itemsByChannel = [];
		$errors = [];
		foreach ($decoded as $entry) {
			if (is_object($entry)) {
				$entry = get_object_vars($entry);
			}
			if (!is_array($entry)) {
				continue;
			}
			$channel = strtoupper(trim((string)($entry['channel'] ?? $entry['type'] ?? '')));
			$channel = preg_replace('/[^A-Z0-9]+/', '-', $channel);
			$channel = trim((string)$channel, '-');
			if ($channel === 'GMONEY') {
				$channel = 'G-MONEY';
			}
			if ($channel === 'GHANA-PAY' || $channel === 'GHANA-PAYMENT') {
				$channel = 'GHANAPAY';
			}
			if ($network === 'EVD') {
				if ($channel === 'AT' || $channel === 'AT-EVD') {
					$channel = 'AT_EVD';
				} elseif ($channel === 'MTN' || $channel === 'MTN-EVD') {
					$channel = 'MTN_EVD';
				} elseif ($channel === 'TELECEL' || $channel === 'TELECEL-EVD') {
					$channel = 'TELECEL_EVD';
				}
			}
			if ($channel === '' || empty($allowedMap[$channel])) {
				continue;
			}
			$amount = round((float)($entry['amount'] ?? 0), 2);
			if ($amount <= 0) {
				continue;
			}
			if (backoffice_payment_is_telecel_type($channel) && !backoffice_payment_amount_is_whole_number($amount)) {
				$errors[] = 'Telecel payments must use whole-number amounts only.';
			}
			$current = $itemsByChannel[$channel] ?? [];
			$itemsByChannel[$channel] = [
				'channel' => $channel,
				'amount' => round(($current['amount'] ?? 0) + $amount, 2),
			];
			foreach (['receipt_photo', 'receipt_uploaded_at'] as $extraKey) {
				if (!empty($entry[$extraKey]) && empty($itemsByChannel[$channel][$extraKey])) {
					$itemsByChannel[$channel][$extraKey] = (string)$entry[$extraKey];
				}
			}
		}

		$items = [];
		$total = 0.0;
		foreach ($allowed as $channel) {
			if (empty($itemsByChannel[$channel])) {
				continue;
			}
			$amount = round((float)$itemsByChannel[$channel]['amount'], 2);
			if ($amount <= 0) {
				continue;
			}
			$item = ['channel' => $channel, 'amount' => $amount];
			foreach (['receipt_photo', 'receipt_uploaded_at'] as $extraKey) {
				if (!empty($itemsByChannel[$channel][$extraKey])) {
					$item[$extraKey] = $itemsByChannel[$channel][$extraKey];
				}
			}
			$items[] = $item;
			$total += $amount;
		}

		return ['items' => $items, 'total' => round($total, 2), 'errors' => array_values(array_unique($errors))];
	}
}

if(!function_exists('backoffice_payment_row_breakdown')){
	function backoffice_payment_row_breakdown(array|object $row): array
	{
		$raw = '';
		if (is_array($row)) {
			$raw = (string)($row['payment_breakdown_json'] ?? '');
			$network = (string)($row['network'] ?? '');
		} else {
			$raw = (string)($row->payment_breakdown_json ?? '');
			$network = (string)($row->network ?? '');
		}
		return backoffice_payment_normalize_breakdown($raw, $network);
	}
}

if(!function_exists('backoffice_reconciliation_outstanding_ecash_channels')){
	function backoffice_reconciliation_acknowledged_petty_cash(int $userId): array
	{
		if ($userId <= 0) {
			return [];
		}
		try {
			$db = new \Core\Database;
			if (!$db->table_exists(['petty_cash_transactions'])) {
				return [];
			}
			return $db->query(
				"SELECT id, reference, title, budget_amount, currency
				 FROM petty_cash_transactions
				 WHERE (deleted = 0 OR deleted IS NULL)
				 AND LOWER(COALESCE(status, 'pending_ack')) = 'acknowledged'
				 AND (assigned_user_id = :assigned_user_id OR acknowledged_by = :acknowledged_by)
				 ORDER BY date_created ASC, id ASC",
				['assigned_user_id' => $userId, 'acknowledged_by' => $userId]
			) ?: [];
		} catch (\Throwable $e) {
			error_log('[backoffice] acknowledged petty cash lookup failed: ' . $e->getMessage());
			return [];
		}
	}

	function backoffice_reconciliation_acknowledged_petty_cash_message(array $rows): string
	{
		$items = [];
		foreach ($rows as $row) {
			$items[] = trim((string)($row->reference ?? '')) ?: ('#' . (int)($row->id ?? 0));
		}
		return 'Complete all acknowledged petty cash transactions before submitting today\'s reconciliation' . (!empty($items) ? ' (' . implode(', ', $items) . ').' : '.');
	}

	function backoffice_reconciliation_is_evd_ecash_channel(string $channelKey): bool
	{
		$normalized = strtoupper(trim($channelKey));
		$normalized = preg_replace('/[^A-Z0-9]+/', '-', $normalized) ?: '';
		return strpos($normalized, 'EVD') !== false;
	}

	function backoffice_reconciliation_is_momopay_ecash_channel(string $channelKey): bool
	{
		$normalized = strtoupper(trim($channelKey));
		$normalized = preg_replace('/[^A-Z0-9]+/', '', $normalized) ?: '';
		return strpos($normalized, 'MOMOPAY') !== false;
	}

	function backoffice_reconciliation_outstanding_ecash_channels(array $ecashBalances): array
	{
		$bankNeedles = [
			'BANK',
			'ADB',
			'ABSA',
			'FIDELITY',
			'APEX',
			'ACCESS',
			'CAL',
			'ECOBANK',
			'GCB',
			'GT',
			'STANBIC',
			'FNB',
			'FIRST',
		];
		$outstanding = [];
		foreach ($ecashBalances as $channelKey => $balance) {
			$normalizedKey = strtoupper(trim((string)$channelKey));
			if ($normalizedKey === '') {
				continue;
			}
			if (backoffice_reconciliation_is_evd_ecash_channel($normalizedKey)) {
				continue;
			}
			if (backoffice_reconciliation_is_momopay_ecash_channel($normalizedKey)) {
				continue;
			}
			$isBankChannel = false;
			foreach ($bankNeedles as $needle) {
				if ($needle !== '' && strpos($normalizedKey, $needle) !== false) {
					$isBankChannel = true;
					break;
				}
			}
			if ($isBankChannel) {
				continue;
			}

			$balanceValue = round((float)$balance, 2);
			$isTelecel = (strpos($normalizedKey, 'TELECEL') !== false);
			$shouldBlock = $isTelecel ? ($balanceValue >= 1.0) : ($balanceValue > 0.00001);
			if (!$shouldBlock) {
				continue;
			}

			$outstanding[] = [
				'channel' => $normalizedKey,
				'balance' => $balanceValue,
				'is_telecel' => $isTelecel,
			];
		}

		return $outstanding;
	}
}

if(!function_exists('backoffice_reconciliation_outstanding_ecash_message')){
	function backoffice_reconciliation_outstanding_ecash_message(array $outstanding): string
	{
		if (empty($outstanding)) {
			return '';
		}

		$parts = [];
		foreach ($outstanding as $row) {
			$channel = strtoupper(trim((string)($row['channel'] ?? '')));
			$balance = (float)($row['balance'] ?? 0);
			if ($channel === '') {
				continue;
			}
			$parts[] = $channel . ' (' . number_format($balance, 2) . ')';
		}

		$message = 'You still have outstanding e-cash balances on ' . implode(', ', $parts) . '. Initiate a backoffice payment to an admin or general manager before creating today\'s reconciliation.';
		$hasTelecel = (bool)array_filter($outstanding, function(array $row): bool {
			return !empty($row['is_telecel']);
		});
		if ($hasTelecel) {
			$message .= ' Telecel balances below 1.00 are allowed because Telecel payouts must use whole numbers.';
		}

		return $message;
	}
}

if(!function_exists('backoffice_commission_can_view_all')){
	function backoffice_commission_can_view_all($session = null): bool
	{
		if (backoffice_user_is_admin($session) || user_can('is_manager') || user_can('is_general_manager') || user_can('is_operations_manager')) {
			return true;
		}

		$allowedRoles = [
			'admin',
			'manager',
			'operations manager',
			'operation manager',
			'operations_manager',
			'operation_manager',
			'operationsmanager',
			'operationmanager',
			'general manager',
			'general_manager',
			'generalmanager',
			'gm',
		];

		return (bool)array_intersect(backoffice_reconciliation_role_names($session), $allowedRoles);
	}
}

if(!function_exists('backoffice_commission_can_delete_pending')){
	function backoffice_commission_can_delete_pending(mixed $row, mixed $session = null): bool
	{
		if (!is_object($row) && !is_array($row)) {
			return false;
		}

		$data = is_array($row) ? $row : (array)$row;
		$statusLower = strtolower(trim((string)($data['transaction_status'] ?? $data['status'] ?? '')));
		if (!in_array($statusLower, ['pending', 'draft'], true)) {
			return false;
		}

		if (backoffice_user_is_admin($session) || backoffice_user_is_general_manager($session)) {
			return true;
		}

		$session = $session ?: get_session();
		$currentUserId = (int)($session->id ?? 0);
		$rowOwnerId = (int)($data['created_by'] ?? 0);
		$rowPayToId = (int)($data['pay_to'] ?? 0);

		return $currentUserId > 0 && ($rowOwnerId === $currentUserId || $rowPayToId === $currentUserId);
	}
}

if(!function_exists('backoffice_commission_assignee_id')){
	function backoffice_commission_assignee_id(object $row): int
	{
		return (int)($row->assigned ?? ($row->pay_to ?? 0));
	}
}

if(!function_exists('backoffice_commission_sim_type_to_type')){
	function backoffice_commission_sim_type_to_type(string $simType): string
	{
		$simType = strtolower(trim($simType));
		if ($simType === 'evd') {
			return 'EVD';
		}
		if ($simType === 'bank') {
			return 'AGENT COMMISSION';
		}
		return 'AGENT COMMISSION';
	}
}

if(!function_exists('backoffice_commission_normalize_network')){
	function backoffice_commission_normalize_network(mixed $network): string
	{
		if (function_exists('users_manager_normalize_sim_network')) {
			return users_manager_normalize_sim_network($network);
		}

		$network = strtoupper(trim(str_replace(['_', '-'], ' ', (string)$network)));
		$network = preg_replace('/\s+/', ' ', $network);
		if ($network === 'G MONEY') {
			return 'G-MONEY';
		}
		return $network;
	}
}

if(!function_exists('backoffice_commission_can_access_bank_sims')){
	function backoffice_commission_can_access_bank_sims($session = null): bool
	{
		return backoffice_user_is_admin($session) || backoffice_user_is_general_manager($session);
	}
}

if(!function_exists('backoffice_commission_assignment_snapshots')){
	function backoffice_commission_assignment_snapshots(mixed $row): array
	{
		$data = is_array($row) ? $row : (array)$row;
		$raw = trim((string)($data['assignment_snapshot_json'] ?? ''));
		if ($raw === '') {
			return [];
		}

		$decoded = json_decode($raw, true);
		if (!is_array($decoded)) {
			return [];
		}

		if (array_key_exists('sim_type', $decoded) || array_key_exists('network', $decoded)) {
			return [$decoded];
		}

		$snapshots = [];
		foreach ($decoded as $item) {
			if (!is_array($item)) {
				continue;
			}
			if (!empty($item['assignment']) && is_array($item['assignment'])) {
				$snapshots[] = $item['assignment'];
				continue;
			}
			if (array_key_exists('sim_type', $item) || array_key_exists('network', $item)) {
				$snapshots[] = $item;
			}
		}

		return $snapshots;
	}
}

if(!function_exists('backoffice_commission_snapshot_is_bank')){
	function backoffice_commission_snapshot_is_bank(mixed $snapshot): bool
	{
		$data = is_array($snapshot) ? $snapshot : (array)$snapshot;
		$simType = strtolower(trim((string)($data['sim_type'] ?? '')));
		if ($simType === 'bank') {
			return true;
		}

		$network = backoffice_commission_normalize_network($data['network'] ?? '');
		$bankNetworks = function_exists('users_manager_bank_network_values')
			? users_manager_bank_network_values()
			: ['CAL BANK'];

		return $network !== '' && in_array($network, $bankNetworks, true);
	}
}

if(!function_exists('backoffice_commission_row_has_bank_sim')){
	function backoffice_commission_row_has_bank_sim(mixed $row): bool
	{
		foreach (backoffice_commission_assignment_snapshots($row) as $snapshot) {
			if (backoffice_commission_snapshot_is_bank($snapshot)) {
				return true;
			}
		}

		$data = is_array($row) ? $row : (array)$row;
		return backoffice_commission_snapshot_is_bank([
			'sim_type' => $data['sim_type'] ?? '',
			'network' => $data['network'] ?? '',
		]);
	}
}

if(!function_exists('backoffice_commission_filter_assignments_for_session')){
	function backoffice_commission_filter_assignments_for_session(array $assignments, mixed $session = null): array
	{
		if (backoffice_commission_can_access_bank_sims($session)) {
			return array_values($assignments);
		}

		return array_values(array_filter($assignments, function($assignment){
			return !backoffice_commission_snapshot_is_bank(backoffice_commission_assignment_snapshot($assignment));
		}));
	}
}

if(!function_exists('backoffice_commission_filter_overdue_assignments_for_session')){
	function backoffice_commission_filter_overdue_assignments_for_session(array $entries, mixed $session = null): array
	{
		if (backoffice_commission_can_access_bank_sims($session)) {
			return array_values($entries);
		}

		return array_values(array_filter($entries, function($entry){
			$assignment = $entry['assignment'] ?? null;
			return $assignment && !backoffice_commission_snapshot_is_bank(backoffice_commission_assignment_snapshot($assignment));
		}));
	}
}

if(!function_exists('backoffice_table_columns')){
	function backoffice_table_columns(object $db, string $table): array
	{
		static $cache = [];

		$table = strtolower(trim($table));
		if ($table === '') {
			return [];
		}

		if (isset($cache[$table])) {
			return $cache[$table];
		}

		$columns = [];
		try {
			$rows = $db->query("SHOW COLUMNS FROM {$table}") ?: [];
			foreach ($rows as $row) {
				$field = strtolower(trim((string)($row->Field ?? $row->field ?? '')));
				if ($field !== '') {
					$columns[$field] = true;
				}
			}
		} catch (\Throwable $e) {
			$columns = [];
		}

		$cache[$table] = $columns;
		return $columns;
	}
}

if(!function_exists('backoffice_commission_non_bank_sql_clause')){
	function backoffice_commission_non_bank_sql_clause(string $alias = 'm', ?array $commissionColumns = null): string
	{
		$alias = preg_replace('/[^A-Za-z0-9_]/', '', $alias) ?: 'm';
		$networkCol = "UPPER(TRIM(COALESCE({$alias}.network, '')))";
		$bankNetworks = function_exists('users_manager_bank_network_values')
			? users_manager_bank_network_values()
			: ['CAL BANK'];
		$quotedBanks = array_map(function($network){
			return "'" . str_replace("'", "''", strtoupper(trim((string)$network))) . "'";
		}, array_values(array_unique(array_filter($bankNetworks))));
		$bankListSql = !empty($quotedBanks) ? implode(',', $quotedBanks) : "''";

		$hasSnapshotJson = $commissionColumns === null || !empty($commissionColumns['assignment_snapshot_json']);
		if (!$hasSnapshotJson) {
			return "({$networkCol} NOT IN ({$bankListSql}))";
		}

		$jsonCol = "COALESCE({$alias}.assignment_snapshot_json, '')";
		return "({$jsonCol} NOT LIKE '%\"sim_type\":\"bank\"%' AND {$jsonCol} NOT LIKE '%\"sim_type\": \"bank\"%' AND {$networkCol} NOT IN ({$bankListSql}))";
	}
}

if(!function_exists('backoffice_commission_credit_channel_key')){
	function backoffice_commission_credit_channel_key(mixed $rowOrSnapshot): string
	{
		if (is_array($rowOrSnapshot) && (array_key_exists('sim_type', $rowOrSnapshot) || array_key_exists('network', $rowOrSnapshot))) {
			$snapshot = $rowOrSnapshot;
		} else {
			$snapshots = backoffice_commission_assignment_snapshots($rowOrSnapshot);
			$snapshot = !empty($snapshots[0]) ? $snapshots[0] : (is_array($rowOrSnapshot) ? $rowOrSnapshot : (array)$rowOrSnapshot);
		}

		$simType = strtolower(trim((string)($snapshot['sim_type'] ?? '')));
		$network = backoffice_commission_normalize_network($snapshot['network'] ?? '');
		if ($network === '') {
			return '';
		}

		if ($simType === 'evd') {
			return $network . ' EVD';
		}

		if ($simType === 'bank') {
			if (function_exists('users_manager_bank_network_to_channel_key')) {
				return users_manager_bank_network_to_channel_key($network);
			}
			$key = 'ECASH_' . strtoupper(preg_replace('/[^A-Z0-9]+/i', '_', $network));
			return trim($key, '_');
		}

		return $network;
	}
}

if(!function_exists('backoffice_commission_assignment_snapshot')){
	function backoffice_commission_assignment_snapshot(mixed $assignment): array
	{
		$data = is_array($assignment) ? $assignment : (array)$assignment;
		$simType = strtolower(trim((string)($data['sim_type'] ?? '')));
		$network = backoffice_commission_normalize_network($data['network'] ?? '');

		$evdCommissionDay = trim((string)($data['evd_commission_day'] ?? ''));
		if ($simType === 'evd' && $evdCommissionDay === '') {
			$evdCommissionDay = trim((string)($data['expected_commission_day'] ?? ''));
		}
		$agentCommissionDay = trim((string)($data['agent_commission_day'] ?? ''));
		if (in_array($simType, ['agent', 'bank'], true) && $agentCommissionDay === '') {
			$agentCommissionDay = trim((string)($data['expected_commission_day'] ?? ''));
		}

		return [
			'id' => (int)($data['id'] ?? 0),
			'network' => $network,
			'sim_type' => $simType,
			'commission_type' => backoffice_commission_sim_type_to_type($simType),
			'phone_number' => trim((string)($data['phone_number'] ?? '')),
			'till_number' => trim((string)($data['till_number'] ?? '')),
			'shortcode' => trim((string)($data['shortcode'] ?? '')),
			'account_number' => trim((string)($data['account_number'] ?? '')),
			'expected_commission_day' => '',
			'evd_commission_day' => $evdCommissionDay,
			'agent_commission_day' => $agentCommissionDay,
			'interest_commission_day' => trim((string)($data['interest_commission_day'] ?? '')),
			'cashout_commission_day' => trim((string)($data['cashout_commission_day'] ?? '')),
			'dstv_commission_day' => trim((string)($data['dstv_commission_day'] ?? '')),
			'others_commission_day' => trim((string)($data['others_commission_day'] ?? '')),
			'ezwich_commission_day' => trim((string)($data['ezwich_commission_day'] ?? '')),
			'notes' => trim((string)($data['notes'] ?? '')),
		];
	}
}

if(!function_exists('backoffice_commission_assignment_label')){
	function backoffice_commission_assignment_label(mixed $assignment): string
	{
		$data = is_array($assignment) ? $assignment : (array)$assignment;
		$parts = [];
		$phone = trim((string)($data['phone_number'] ?? ''));
		$till = trim((string)($data['till_number'] ?? ''));
		$shortcode = trim((string)($data['shortcode'] ?? ''));
		$account = trim((string)($data['account_number'] ?? ''));

		if ($phone !== '') {
			$parts[] = $phone;
		}
		if ($till !== '') {
			$parts[] = 'Till ' . $till;
		}
		if ($shortcode !== '') {
			$parts[] = 'Shortcode ' . $shortcode;
		}
		if ($account !== '') {
			$parts[] = 'Acct ' . $account;
		}

		return !empty($parts) ? implode(' | ', $parts) : 'Assigned SIM';
	}
}

if(!function_exists('backoffice_commission_uses_assignment_workflow')){
	function backoffice_commission_uses_assignment_workflow(mixed $row): bool
	{
		if (empty($row)) {
			return false;
		}

		$commissionType = strtoupper(trim((string)($row->commission_type ?? '')));
		if ($commissionType === 'OTHER SIM COMMISSION BULK') {
			return true;
		}

		return (int)($row->assigned ?? 0) > 0
			|| (int)($row->sim_assignment_id ?? 0) > 0
			|| trim((string)($row->proof_image ?? '')) !== ''
			|| trim((string)($row->assignment_snapshot_json ?? '')) !== '';
	}
}

if(!function_exists('backoffice_commission_breakdown_rows')){
	function backoffice_commission_breakdown_rows(object $commissionModel, object $row, array $commissionColumns = []): array
	{
		if (empty($row)) {
			return [];
		}

		$children = [];
		$hasBulkParentId = !empty($commissionColumns['bulk_parent_id']);
		if ($hasBulkParentId && !empty($row->id)) {
			try {
				$children = $commissionModel->query(
					"SELECT * FROM commissions WHERE (deleted = 0 OR deleted IS NULL) AND bulk_parent_id = :pid ORDER BY id ASC",
					['pid' => (int)$row->id]
				) ?: [];
			} catch (\Throwable $e) {
				$children = [];
			}
		}

		if (!empty($children)) {
			return $children;
		}

		$snapshots = backoffice_commission_assignment_snapshots($row);
		if (!empty($snapshots)) {
			$data = is_array($row) ? $row : (array)$row;
			$decoded = json_decode((string)($data['assignment_snapshot_json'] ?? ''), true);
			$decoded = is_array($decoded) ? $decoded : [];
			$rows = [];
			foreach ($decoded as $item) {
				if (!is_array($item)) {
					continue;
				}
				$snapshot = [];
				if (!empty($item['assignment']) && is_array($item['assignment'])) {
					$snapshot = $item['assignment'];
				} elseif (array_key_exists('sim_type', $item) || array_key_exists('network', $item)) {
					$snapshot = $item;
				}
				if (empty($snapshot)) {
					continue;
				}

				$obj = (object)[
					'id' => 0,
					'network' => $snapshot['network'] ?? ($data['network'] ?? ''),
					'commission_type' => $snapshot['commission_type'] ?? ($data['commission_type'] ?? ''),
					'amount' => (float)($item['amount'] ?? 0),
					'proof_image' => (string)($item['proof_image'] ?? ''),
					'assignment_snapshot_json' => json_encode($snapshot, JSON_UNESCAPED_SLASHES),
				];
				$rows[] = $obj;
			}

			if (!empty($rows)) {
				return $rows;
			}
		}

		$data = is_array($row) ? $row : (array)$row;
		$parentId = (int)($data['id'] ?? 0);
		$createdBy = (int)($data['created_by'] ?? 0);
		$payTo = (int)($data['pay_to'] ?? 0);
		$dateCreated = trim((string)($data['date_created'] ?? ''));
		$dateReceived = trim((string)($data['date_received'] ?? ''));
		$status = trim((string)($data['transaction_status'] ?? ''));
		$parentType = strtoupper(trim((string)($data['commission_type'] ?? '')));
		if (
			$parentType === 'OTHER SIM COMMISSION BULK'
			&& $parentId > 0
			&& $createdBy > 0
			&& $dateCreated !== ''
		) {
			try {
				$sql = "SELECT *
					FROM commissions
					WHERE id <> :pid
					AND (deleted = 0 OR deleted IS NULL)
					AND created_by = :created_by
					AND pay_to = :pay_to
					AND date_created = :date_created";
				$params = [
					'pid' => $parentId,
					'created_by' => $createdBy,
					'pay_to' => $payTo,
					'date_created' => $dateCreated,
				];
				if ($dateReceived !== '') {
					$sql .= " AND date_received = :date_received";
					$params['date_received'] = $dateReceived;
				}
				if ($status !== '') {
					$sql .= " AND COALESCE(transaction_status,'') = :status";
					$params['status'] = $status;
				}
				$sql .= " AND UPPER(COALESCE(commission_type,'')) <> 'OTHER SIM COMMISSION BULK'
					ORDER BY id ASC";

				$siblingRows = $commissionModel->query($sql, $params) ?: [];
				if (!empty($siblingRows)) {
					return $siblingRows;
				}
			} catch (\Throwable $e) {
				// ignore fallback lookup errors
			}
		}

		return !empty($row->sim_assignment_id) || trim((string)($row->proof_image ?? '')) !== '' ? [$row] : [];
	}
}

if(!function_exists('backoffice_commission_expected_interval_days')){
	function backoffice_commission_expected_interval_days(int $expectedDay): int
	{
		return max(1, min(365, $expectedDay));
	}
}

if(!function_exists('backoffice_commission_expected_day_ordinal')){
	function backoffice_commission_expected_day_ordinal(int $day): string
	{
		$day = max(1, $day);
		$mod100 = $day % 100;
		if ($mod100 >= 11 && $mod100 <= 13) {
			return $day . 'th';
		}

		switch ($day % 10) {
			case 1:
				return $day . 'st';
			case 2:
				return $day . 'nd';
			case 3:
				return $day . 'rd';
			default:
				return $day . 'th';
		}
	}
}

if(!function_exists('backoffice_commission_expected_interval_label')){
	function backoffice_commission_expected_interval_label(int $expectedDay): string
	{
		$days = backoffice_commission_expected_interval_days($expectedDay);
		return $days . ' day' . ($days === 1 ? '' : 's');
	}
}

if(!function_exists('backoffice_commission_expected_due_label')){
	function backoffice_commission_expected_due_label(int $expectedDay): string
	{
		$days = backoffice_commission_expected_interval_days($expectedDay);
		return 'Expected by: after ' . backoffice_commission_expected_interval_label($days);
	}
}

if(!function_exists('backoffice_commission_day_fields')){
	function backoffice_commission_day_fields(): array
	{
		if (function_exists('users_manager_sim_commission_day_fields')) {
			return users_manager_sim_commission_day_fields();
		}

		return [
			'evd_commission_day' => 'EVD commission days',
			'agent_commission_day' => 'Agent commission days',
			'interest_commission_day' => 'Interest commission days',
			'cashout_commission_day' => 'Cashout commission days',
			'dstv_commission_day' => 'DSTV commission days',
			'others_commission_day' => 'Others days',
			'ezwich_commission_day' => 'eZwich days',
		];
	}
}

if(!function_exists('backoffice_commission_day_payment_label')){
	function backoffice_commission_day_payment_label(string $field, string $label = '', string $simType = ''): string
	{
		$simType = strtolower(trim($simType));
		$labels = [
			'evd_commission_day' => 'EVD commission',
			'agent_commission_day' => 'Agent commission',
			'interest_commission_day' => 'Interest commission',
			'cashout_commission_day' => 'Cashout commission',
			'dstv_commission_day' => 'DSTV commission',
			'others_commission_day' => 'Others commission',
			'ezwich_commission_day' => 'eZwich commission',
		];
		if (isset($labels[$field])) {
			return $labels[$field];
		}

		$label = trim($label);
		$label = preg_replace('/\s+days$/i', '', $label);
		return $label !== '' ? $label : 'Commission';
	}
}

if(!function_exists('backoffice_commission_source_label_from_parts')){
	function backoffice_commission_source_label_from_parts(string $network, string $commissionType = '', string $commissionField = '', array $snapshot = []): string
	{
		$snapshotNetwork = backoffice_commission_normalize_network($snapshot['network'] ?? '');
		$network = $snapshotNetwork !== '' ? $snapshotNetwork : backoffice_commission_normalize_network($network);
		$networkIsUnknown = ($network === '' || strcasecmp($network, 'Unknown') === 0);

		$field = strtolower(trim((string)($snapshot['commission_field'] ?? $commissionField)));
		$type = strtoupper(trim((string)($snapshot['commission_label'] ?? $snapshot['commission_type'] ?? $commissionType)));
		$simType = strtolower(trim((string)($snapshot['sim_type'] ?? '')));

		$fieldMap = [
			'agent_commission_day' => 'AGENT',
			'evd_commission_day' => 'EVD',
			'interest_commission_day' => 'INTEREST',
			'cashout_commission_day' => 'CASHIN',
			'dstv_commission_day' => 'DSTV',
			'others_commission_day' => 'OTHERS',
			'ezwich_commission_day' => 'EZWICH',
			'general' => '',
		];

		$source = $fieldMap[$field] ?? '';
		if ($source === '') {
			if ($simType === 'evd') {
				$source = 'EVD';
			} elseif (strpos($type, 'AIRTIME') !== false || strpos($type, 'BUNDLE') !== false || strpos($type, 'DATA') !== false) {
				$source = 'AIRTIME';
			} elseif (strpos($type, 'EVD') !== false) {
				$source = 'EVD';
			} elseif (strpos($type, 'CASHIN') !== false || strpos($type, 'CASH IN') !== false || strpos($type, 'SENDING') !== false) {
				$source = 'CASHIN';
			} elseif (strpos($type, 'CASHOUT') !== false || strpos($type, 'CASH OUT') !== false) {
				$source = 'CASHOUT';
			} elseif (strpos($type, 'AGENT') !== false) {
				$source = 'AGENT';
			} elseif (strpos($type, 'DSTV') !== false) {
				$source = 'DSTV';
			} elseif (strpos($type, 'INTEREST') !== false) {
				$source = 'INTEREST';
			}
		}

		if ($source === '') {
			$source = trim(preg_replace('/\s+COMMISSION\b/i', '', $type));
			$source = $source !== '' ? $source : 'COMMISSION';
		}

		$label = $networkIsUnknown ? strtoupper($source) : trim($network . ' ' . strtoupper($source));
		$label = preg_replace('/\s+/', ' ', $label);
		return $label !== '' ? $label : 'Unknown Commission Source';
	}
}

if(!function_exists('backoffice_commission_assignment_payment_lines')){
	function backoffice_commission_assignment_payment_lines(mixed $assignment): array
	{
		$snapshot = backoffice_commission_assignment_snapshot($assignment);
		$simType = strtolower(trim((string)($snapshot['sim_type'] ?? '')));
		$network = backoffice_commission_normalize_network($snapshot['network'] ?? '');
		$lines = [];

		foreach (backoffice_commission_day_fields() as $field => $label) {
			if ($simType === 'evd' && $field !== 'evd_commission_day') {
				continue;
			}
			if (in_array($simType, ['agent', 'bank'], true) && $field === 'evd_commission_day') {
				continue;
			}
			if (!in_array($simType, ['agent', 'bank', 'evd'], true)) {
				continue;
			}
			if ($simType === 'bank' && in_array($field, ['interest_commission_day', 'dstv_commission_day'], true)) {
				continue;
			}
			if ($field === 'ezwich_commission_day') {
				$isFidelity = function_exists('users_manager_is_fidelity_bank_assignment')
					? users_manager_is_fidelity_bank_assignment($simType, $network)
					: ($simType === 'bank' && $network === 'FIDELITY BANK');
				if (!$isFidelity) {
					continue;
				}
			}

			$rawDays = trim((string)($snapshot[$field] ?? ''));
			if ($rawDays === '' || !ctype_digit($rawDays) || (int)$rawDays <= 0) {
				continue;
			}

			$days = backoffice_commission_expected_interval_days((int)$rawDays);
			$paymentLabel = backoffice_commission_day_payment_label($field, $label, $simType);
			$lineSnapshot = $snapshot;
			$lineSnapshot['commission_field'] = $field;
			$lineSnapshot['commission_label'] = $paymentLabel;
			$lineSnapshot['expected_day'] = $days;
			$lines[] = [
				'assignment' => $assignment,
				'snapshot' => $lineSnapshot,
				'commission_field' => $field,
				'commission_label' => $paymentLabel,
				'expected_day' => $days,
			];
		}

		if (empty($lines)) {
			$snapshot['commission_field'] = 'general';
			$snapshot['commission_label'] = $snapshot['commission_type'] ?? 'Commission';
			$lines[] = [
				'assignment' => $assignment,
				'snapshot' => $snapshot,
				'commission_field' => 'general',
				'commission_label' => $snapshot['commission_label'],
				'expected_day' => 0,
			];
		}

		return $lines;
	}
}

if(!function_exists('backoffice_overdue_commission_assignments')){
	function backoffice_overdue_commission_assignments(int $userId, ?\DateTimeImmutable $now = null): array
	{
		if ($userId <= 0 || !function_exists('users_manager_fetch_sim_assignments')) {
			return [];
		}

		$now = $now ?: new \DateTimeImmutable('now', new \DateTimeZone('Africa/Accra'));
		$assignments = users_manager_fetch_sim_assignments($userId);
		$session = get_session();
		if ((int)($session->id ?? 0) === $userId) {
			$assignments = backoffice_commission_filter_assignments_for_session($assignments, $session);
		}
		if (empty($assignments)) {
			return [];
		}

		$eligible = [];
		foreach ($assignments as $assignment) {
			$assignmentId = (int)($assignment->id ?? 0);
			if ($assignmentId <= 0) {
				continue;
			}

			$simType = strtolower(trim((string)($assignment->sim_type ?? '')));
			$network = backoffice_commission_normalize_network($assignment->network ?? '');
			foreach (backoffice_commission_day_fields() as $field => $label) {
				if ($simType === 'evd' && $field !== 'evd_commission_day') {
					continue;
				}
				if (in_array($simType, ['agent', 'bank'], true) && $field === 'evd_commission_day') {
					continue;
				}
				if (!in_array($simType, ['agent', 'bank', 'evd'], true)) {
					continue;
				}
				if ($simType === 'bank' && in_array($field, ['interest_commission_day', 'dstv_commission_day'], true)) {
					continue;
				}
				if ($field === 'ezwich_commission_day') {
					$isFidelity = function_exists('users_manager_is_fidelity_bank_assignment')
						? users_manager_is_fidelity_bank_assignment($simType, $network)
						: ($simType === 'bank' && $network === 'FIDELITY BANK');
					if (!$isFidelity) {
						continue;
					}
				}

				$expectedDayRaw = trim((string)($assignment->{$field} ?? ''));
				if ($expectedDayRaw === '' || !ctype_digit($expectedDayRaw)) {
					continue;
				}

				$expectedDay = (int)$expectedDayRaw;
				if ($expectedDay <= 0) {
					continue;
				}

				$key = $assignmentId . '|' . $field;
				$eligible[$key] = [
					'assignment' => $assignment,
					'assignment_id' => $assignmentId,
					'commission_field' => $field,
					'commission_label' => backoffice_commission_day_payment_label($field, $label, $simType),
					'expected_day' => backoffice_commission_expected_interval_days($expectedDay),
				];
			}
		}

		if (empty($eligible)) {
			return [];
		}

		try {
			$db = new \Core\Database;
			if ($db->table_exists(['commissions'])) {
				$assignmentIds = array_keys($eligible);
				$assignmentIds = array_values(array_unique(array_map(function($key) use ($eligible){
					return (int)($eligible[$key]['assignment_id'] ?? 0);
				}, array_keys($eligible))));
				$in = implode(',', array_map('intval', $assignmentIds));
				$commissionColumns = [];
				$columnRows = $db->query("SHOW COLUMNS FROM commissions") ?: [];
				foreach ($columnRows as $columnRow) {
					$columnName = strtolower(trim((string)($columnRow->Field ?? $columnRow->field ?? '')));
					if ($columnName !== '') {
						$commissionColumns[$columnName] = true;
					}
				}
				$fieldSelect = !empty($commissionColumns['commission_field'])
					? "COALESCE(NULLIF(TRIM(commission_field), ''), '__legacy__')"
					: "'__legacy__'";
				$rows = $db->query(
					"SELECT sim_assignment_id,
					        {$fieldSelect} AS submitted_commission_field,
					        MAX(
					        	COALESCE(
					        		NULLIF(NULLIF(NULLIF(TRIM(date_received), ''), '0000-00-00 00:00:00'), '0000-00-00'),
					        		date_created
					        	)
					        ) AS last_submitted_at
					 FROM commissions
					 WHERE created_by = :user_id
					 AND sim_assignment_id IN ($in)
					 AND (deleted IS NULL OR deleted = 0)
					 GROUP BY sim_assignment_id, submitted_commission_field",
					[
						'user_id' => $userId,
					]
				) ?: [];
				$submittedMeta = [];
				foreach ($rows as $row) {
					$submittedId = (int)($row->sim_assignment_id ?? 0);
					if ($submittedId > 0) {
						$submittedField = trim((string)($row->submitted_commission_field ?? '__legacy__'));
						if ($submittedField === '') {
							$submittedField = '__legacy__';
						}
						if (empty($submittedMeta[$submittedId])) {
							$submittedMeta[$submittedId] = [];
						}
						$submittedMeta[$submittedId][$submittedField] = trim((string)($row->last_submitted_at ?? ''));
					}
				}

				foreach ($eligible as $eligibleKey => $meta) {
					$assignmentId = (int)($meta['assignment_id'] ?? 0);
					$assignment = $meta['assignment'] ?? null;
					if (empty($assignment)) {
						unset($eligible[$eligibleKey]);
						continue;
					}

					$expectedDays = (int)($meta['expected_day'] ?? 0);
					if ($expectedDays <= 0) {
						unset($eligible[$eligibleKey]);
						continue;
					}

					$commissionField = trim((string)($meta['commission_field'] ?? ''));
					$anchorRaw = trim((string)($submittedMeta[$assignmentId][$commissionField] ?? ''));
					if ($anchorRaw === '') {
						$anchorRaw = trim((string)($submittedMeta[$assignmentId]['__legacy__'] ?? ''));
					}
					if ($anchorRaw === '') {
						$anchorRaw = trim((string)($assignment->date_created ?? ''));
					}
					if ($anchorRaw === '') {
						unset($eligible[$eligibleKey]);
						continue;
					}

					try {
						$anchorAt = new \DateTimeImmutable($anchorRaw, new \DateTimeZone('Africa/Accra'));
					} catch (\Throwable $e) {
						unset($eligible[$eligibleKey]);
						continue;
					}

					$dueAt = $anchorAt->modify('+' . $expectedDays . ' days');
					if ($now < $dueAt) {
						unset($eligible[$eligibleKey]);
						continue;
					}

					$eligible[$eligibleKey]['due_at'] = $dueAt;
				}
			}
		} catch (\Throwable $e) {
			return [];
		}

		$overdue = [];
		foreach ($eligible as $assignmentId => $meta) {
			$overdue[] = [
				'assignment' => $meta['assignment'],
				'assignment_id' => $meta['assignment_id'] ?? 0,
				'commission_field' => $meta['commission_field'] ?? '',
				'commission_label' => $meta['commission_label'] ?? 'Commission',
				'expected_day' => $meta['expected_day'],
				'due_at' => $meta['due_at'] ?? null,
			];
		}

		return $overdue;
	}
}

if(!function_exists('backoffice_overdue_commission_message')){
	function backoffice_overdue_commission_message(array $overdueAssignments): string
	{
		if (empty($overdueAssignments)) {
			return '';
		}

		$labels = [];
		foreach ($overdueAssignments as $item) {
			$assignment = $item['assignment'] ?? null;
			if (empty($assignment)) {
				continue;
			}
			$snapshot = backoffice_commission_assignment_snapshot($assignment);
			$typeLabel = trim((string)($item['commission_label'] ?? 'Commission'));
			$labels[] = trim((string)($snapshot['network'] ?? '') . ' - ' . backoffice_commission_assignment_label($snapshot) . ' (' . $typeLabel . ')');
		}

		$labels = array_values(array_unique(array_filter($labels)));
		$preview = array_slice($labels, 0, 3);
		$remaining = count($labels) - count($preview);
		$message = 'Commission submission is overdue for: ' . implode(', ', $preview);
		if ($remaining > 0) {
			$message .= ' and ' . $remaining . ' more SIM' . ($remaining > 1 ? 's' : '');
		}
		$message .= '. Please submit the affected commissions.';
		return $message;
	}
}

if(!function_exists('backoffice_commission_deleted_reactivation_entries')){
	function backoffice_commission_deleted_reactivation_entries(array $deletedRows, array $excludeIds = [], ?\DateTimeImmutable $now = null): array
	{
		if (empty($deletedRows)) {
			return [];
		}

		$now = $now ?: new \DateTimeImmutable('now', new \DateTimeZone('Africa/Accra'));
		$excludeIds = array_values(array_unique(array_filter(array_map('intval', $excludeIds))));
		$excludeSql = !empty($excludeIds) ? (' AND id NOT IN (' . implode(',', $excludeIds) . ')') : '';
		$db = new \Core\Database;
		$commissionColumns = backoffice_table_columns($db, 'commissions');
		$hasCommissionField = !empty($commissionColumns['commission_field']);
		$assignmentsByUser = [];
		$entries = [];
		$seen = [];

		foreach ($deletedRows as $deletedRow) {
			$row = is_array($deletedRow) ? (object)$deletedRow : $deletedRow;
			if (empty($row)) {
				continue;
			}

			$assignmentId = (int)($row->sim_assignment_id ?? 0);
			$createdBy = (int)($row->created_by ?? 0);
			if ($assignmentId <= 0 || $createdBy <= 0) {
				continue;
			}

			$snapshot = [];
			$rawSnapshot = trim((string)($row->assignment_snapshot_json ?? ''));
			if ($rawSnapshot !== '') {
				$decodedSnapshot = json_decode($rawSnapshot, true);
				if (is_array($decodedSnapshot)) {
					$snapshot = $decodedSnapshot;
				}
			}

			$commissionField = trim((string)($row->commission_field ?? ($snapshot['commission_field'] ?? '')));
			if ($commissionField === '') {
				$commissionField = trim((string)($snapshot['commission_field'] ?? 'general'));
			}
			if ($commissionField === '') {
				$commissionField = 'general';
			}

			$key = $createdBy . '|' . $assignmentId . '|' . $commissionField;
			if (isset($seen[$key])) {
				continue;
			}
			$seen[$key] = true;

			if (empty($assignmentsByUser[$createdBy])) {
				$assignmentsByUser[$createdBy] = [];
				if (function_exists('users_manager_fetch_sim_assignments')) {
					foreach (users_manager_fetch_sim_assignments($createdBy) as $assignment) {
						$aid = (int)($assignment->id ?? 0);
						if ($aid > 0) {
							$assignmentsByUser[$createdBy][$aid] = $assignment;
						}
					}
				}
			}

			$assignment = $assignmentsByUser[$createdBy][$assignmentId] ?? null;
			if (empty($assignment)) {
				continue;
			}

			$assignmentSnapshot = backoffice_commission_assignment_snapshot($assignment);
			$simType = strtolower(trim((string)($assignmentSnapshot['sim_type'] ?? '')));
			$expectedDays = (int)($snapshot['expected_day'] ?? 0);
			if ($expectedDays <= 0 && isset($assignment->{$commissionField})) {
				$rawDays = trim((string)$assignment->{$commissionField});
				if ($rawDays !== '' && ctype_digit($rawDays)) {
					$expectedDays = (int)$rawDays;
				}
			}
			if ($expectedDays <= 0) {
				continue;
			}
			$expectedDays = backoffice_commission_expected_interval_days($expectedDays);

			$commissionLabel = trim((string)($snapshot['commission_label'] ?? ''));
			if ($commissionLabel === '') {
				$commissionLabel = backoffice_commission_day_payment_label($commissionField, '', $simType);
			}

			$fieldSql = '';
			$params = [
				'user_id' => $createdBy,
				'assignment_id' => $assignmentId,
			];
			if ($hasCommissionField) {
				$fieldSql = " AND COALESCE(NULLIF(TRIM(commission_field), ''), 'general') = :commission_field";
				$params['commission_field'] = $commissionField;
			}

			$lastSubmitted = null;
			try {
				$rows = $db->query(
					"SELECT id,
					        COALESCE(
					        	NULLIF(NULLIF(NULLIF(TRIM(date_received), ''), '0000-00-00 00:00:00'), '0000-00-00'),
					        	date_created
					        ) AS submitted_at
					 FROM commissions
					 WHERE created_by = :user_id
					 AND sim_assignment_id = :assignment_id
					 {$fieldSql}
					 {$excludeSql}
					 AND (deleted IS NULL OR deleted = 0)
					 ORDER BY submitted_at DESC, id DESC
					 LIMIT 1",
					$params
				) ?: [];
				$lastSubmitted = !empty($rows[0]) ? trim((string)($rows[0]->submitted_at ?? '')) : '';
			} catch (\Throwable $e) {
				$lastSubmitted = '';
			}

			$anchorRaw = trim((string)$lastSubmitted);
			if ($anchorRaw === '') {
				$anchorRaw = trim((string)($assignment->date_created ?? ''));
			}
			if ($anchorRaw === '') {
				continue;
			}

			try {
				$anchorAt = new \DateTimeImmutable($anchorRaw, new \DateTimeZone('Africa/Accra'));
			} catch (\Throwable $e) {
				continue;
			}

			$dueAt = $anchorAt->modify('+' . $expectedDays . ' days');
			if ($now < $dueAt) {
				continue;
			}

			$assignmentLabel = trim((string)($assignmentSnapshot['network'] ?? '') . ' - ' . backoffice_commission_assignment_label($assignmentSnapshot));
			$deletedId = (int)($row->id ?? 0);
			$reason = 'Overdue reactivated because submitted commission #' . $deletedId . ' was deleted. '
				. $commissionLabel . ' is expected every ' . backoffice_commission_expected_interval_label($expectedDays)
				. '; last valid submission anchor is ' . $anchorAt->format('jS M, Y H:i')
				. ', so it has been due since ' . $dueAt->format('jS M, Y H:i') . '.';

			$entries[] = [
				'assignment_id' => $assignmentId,
				'commission_field' => $commissionField,
				'commission_label' => $commissionLabel,
				'assignment_label' => $assignmentLabel,
				'due_at' => $dueAt,
				'reason' => $reason,
				'deleted_commission_id' => $deletedId,
				'created_by' => $createdBy,
			];
		}

		return $entries;
	}
}

if(!function_exists('backoffice_request_is_cash')){
	function backoffice_request_is_cash(object $row): bool
	{
		$network = strtoupper(trim((string)($row->network ?? '')));
		$requestType = strtoupper(trim((string)($row->request_type ?? '')));
		return strpos($network, 'CASH') === 0 || $requestType === 'CASH';
	}
}

if(!function_exists('backoffice_request_cash_currency')){
	function backoffice_request_cash_currency(object $row): string
	{
		$requestTypeRaw = strtoupper(trim((string)($row->request_type ?? '')));
		$currency = (strpos($requestTypeRaw, 'CFA') !== false) ? 'CFA' : 'GHS';
		if ($currency !== 'CFA') {
			$currencyProbe = json_decode((string)($row->denos_json ?? ''), true);
			if (is_array($currencyProbe)) {
				foreach ($currencyProbe as $probeRow) {
					if (strtoupper((string)($probeRow['currency'] ?? '')) === 'CFA') {
						$currency = 'CFA';
						break;
					}
				}
			}
		}
		return $currency;
	}
}

if(!function_exists('backoffice_request_resolved_channel_key')){
	function backoffice_request_resolved_channel_key(string $network, string $requestType = ''): string
	{
		$providerForKey = strtoupper(trim($network));
		$requestTypeRaw = strtoupper(trim($requestType));
		if ($requestTypeRaw !== '' && $requestTypeRaw !== 'CASH') {
			$providerForKey = $requestTypeRaw;
			if (strpos(strtoupper(trim($network)), 'EVD') !== false && strpos($providerForKey, 'EVD') === false) {
				$providerForKey .= '_EVD';
			}
		} elseif (strpos($providerForKey, 'E-CASH') !== false || strpos($providerForKey, 'EVD') !== false) {
			$providerForKey = $requestTypeRaw;
		}

		$slug = preg_replace('/[^A-Z0-9]+/', '_', $providerForKey);
		$slug = trim((string)$slug, '_');
		if (in_array($slug, ['AT_EVD', 'MTN_EVD', 'TELECEL_EVD'], true)) {
			return $slug;
		}

		if (strpos($providerForKey, 'TELECEL') !== false) {
			return 'TELECEL';
		}
		if (strpos($providerForKey, 'MTN') !== false) {
			return 'MTN';
		}
		if (strpos($providerForKey, 'AT') !== false || strpos($providerForKey, 'AIRTELTIGO') !== false) {
			return 'AT';
		}

		return $slug;
	}
}

if(!function_exists('backoffice_denominations_signature')){
	function backoffice_denominations_signature(array $denos, string $currency = 'GHS'): array
	{
		$currency = strtoupper(trim($currency ?: 'GHS'));
		$formatKey = static function (float $denom) use ($currency): string {
			if ($currency === 'CFA') {
				return (string)((int)round($denom));
			}
			return number_format((float)$denom, 2, '.', '');
		};

		$signature = [];
		foreach ($denos as $row) {
			$rowCurrency = strtoupper((string)($row['currency'] ?? $currency));
			if ($rowCurrency !== $currency) {
				continue;
			}
			$denomination = (float)($row['denomination'] ?? 0);
			$count = (int)($row['count'] ?? 0);
			if ($denomination <= 0 || $count <= 0) {
				continue;
			}
			$key = $formatKey($denomination);
			$signature[$key] = ($signature[$key] ?? 0) + $count;
		}

		ksort($signature, SORT_NATURAL);
		return $signature;
	}
}

if(!function_exists('backoffice_denominations_match')){
	function backoffice_denominations_match(array $expected, array $actual, string $currency = 'GHS'): bool
	{
		return backoffice_denominations_signature($expected, $currency) === backoffice_denominations_signature($actual, $currency);
	}
}

if(!function_exists('backoffice_ensure_bank_transfer_schema')){
	function backoffice_ensure_bank_transfer_schema(): void
	{
		static $ensured = false;
		if ($ensured) return;
		$marker = backoffice_runtime_marker_path('backoffice_bank_transfer_schema_20260427.done');
		if(file_exists($marker)){
			try {
				$db = new \Core\Database;
				$sourceColumn = $db->query("SHOW COLUMNS FROM bank_transfers_backoffice LIKE 'source_vault_id'");
				$destinationColumn = $db->query("SHOW COLUMNS FROM bank_transfers_backoffice LIKE 'destination_vault_id'");
				if (!empty($sourceColumn) && !empty($destinationColumn)) {
					$ensured = true;
					return;
				}
			} catch (\Throwable $e) {
				// Retry the schema migration below when the schema check fails.
			}
		}

		try {
			$db = new \Core\Database;
			$db->query(
				"CREATE TABLE IF NOT EXISTS bank_transfers_backoffice (
					id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
					transaction_type VARCHAR(30) NULL,
					transfer_category VARCHAR(30) NULL,
					bank_name VARCHAR(120) NULL,
					network_name VARCHAR(60) NULL,
					amount DECIMAL(12,2) NOT NULL DEFAULT 0,
					denos_json LONGTEXT NULL,
					source_vault_id BIGINT UNSIGNED NULL,
					destination_vault_id BIGINT UNSIGNED NULL,
					notes TEXT NULL,
					withdrawing_user_id BIGINT UNSIGNED NULL,
					withdrawal_status VARCHAR(30) NULL DEFAULT 'Pending',
					withdrawn_by BIGINT UNSIGNED NULL,
					withdrawn_at DATETIME NULL,
					cheque_number VARCHAR(120) NULL,
					cheque_date DATE NULL,
					cheque_photo VARCHAR(255) NULL,
					second_party_user_id BIGINT UNSIGNED NULL,
					second_party_status VARCHAR(30) NULL DEFAULT 'Pending',
					second_party_verified_by BIGINT UNSIGNED NULL,
					second_party_verified_at DATETIME NULL,
					treasury_slip_photo VARCHAR(255) NULL,
					creator_bank_receipt_photo VARCHAR(255) NULL,
					creator_bank_receipt_uploaded_at DATETIME NULL,
					creator_receipt_status VARCHAR(30) NULL DEFAULT 'Pending',
					third_party_user_id BIGINT UNSIGNED NULL,
					third_party_status VARCHAR(30) NULL DEFAULT 'Pending',
					third_party_reviewed_by BIGINT UNSIGNED NULL,
					third_party_reviewed_at DATETIME NULL,
					third_party_reject_reason TEXT NULL,
					assignment_snapshot_json LONGTEXT NULL,
					transaction_status VARCHAR(50) NULL DEFAULT 'Pending 2nd Party',
					disabled TINYINT(1) NULL DEFAULT 0,
					deleted TINYINT(1) NULL DEFAULT 0,
					created_by BIGINT UNSIGNED NULL,
					updated_by BIGINT UNSIGNED NULL,
					approved_by BIGINT UNSIGNED NULL,
					approved_at DATETIME NULL,
					date_created DATETIME NULL,
					date_updated DATETIME NULL,
					date_deleted DATETIME NULL
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
			);

			$columns = [
				'transaction_type' => "ALTER TABLE bank_transfers_backoffice ADD COLUMN transaction_type VARCHAR(30) NULL",
				'transfer_category' => "ALTER TABLE bank_transfers_backoffice ADD COLUMN transfer_category VARCHAR(30) NULL",
				'bank_name' => "ALTER TABLE bank_transfers_backoffice ADD COLUMN bank_name VARCHAR(120) NULL",
				'network_name' => "ALTER TABLE bank_transfers_backoffice ADD COLUMN network_name VARCHAR(60) NULL",
				'amount' => "ALTER TABLE bank_transfers_backoffice ADD COLUMN amount DECIMAL(12,2) NOT NULL DEFAULT 0",
				'denos_json' => "ALTER TABLE bank_transfers_backoffice ADD COLUMN denos_json LONGTEXT NULL",
				'source_vault_id' => "ALTER TABLE bank_transfers_backoffice ADD COLUMN source_vault_id BIGINT UNSIGNED NULL",
				'destination_vault_id' => "ALTER TABLE bank_transfers_backoffice ADD COLUMN destination_vault_id BIGINT UNSIGNED NULL",
				'notes' => "ALTER TABLE bank_transfers_backoffice ADD COLUMN notes TEXT NULL",
				'withdrawing_user_id' => "ALTER TABLE bank_transfers_backoffice ADD COLUMN withdrawing_user_id BIGINT UNSIGNED NULL",
				'withdrawal_status' => "ALTER TABLE bank_transfers_backoffice ADD COLUMN withdrawal_status VARCHAR(30) NULL DEFAULT 'Pending'",
				'withdrawn_by' => "ALTER TABLE bank_transfers_backoffice ADD COLUMN withdrawn_by BIGINT UNSIGNED NULL",
				'withdrawn_at' => "ALTER TABLE bank_transfers_backoffice ADD COLUMN withdrawn_at DATETIME NULL",
				'cheque_number' => "ALTER TABLE bank_transfers_backoffice ADD COLUMN cheque_number VARCHAR(120) NULL",
				'cheque_date' => "ALTER TABLE bank_transfers_backoffice ADD COLUMN cheque_date DATE NULL",
				'cheque_photo' => "ALTER TABLE bank_transfers_backoffice ADD COLUMN cheque_photo VARCHAR(255) NULL",
				'second_party_user_id' => "ALTER TABLE bank_transfers_backoffice ADD COLUMN second_party_user_id BIGINT UNSIGNED NULL",
				'second_party_status' => "ALTER TABLE bank_transfers_backoffice ADD COLUMN second_party_status VARCHAR(30) NULL DEFAULT 'Pending'",
				'second_party_verified_by' => "ALTER TABLE bank_transfers_backoffice ADD COLUMN second_party_verified_by BIGINT UNSIGNED NULL",
				'second_party_verified_at' => "ALTER TABLE bank_transfers_backoffice ADD COLUMN second_party_verified_at DATETIME NULL",
				'treasury_slip_photo' => "ALTER TABLE bank_transfers_backoffice ADD COLUMN treasury_slip_photo VARCHAR(255) NULL",
				'creator_bank_receipt_photo' => "ALTER TABLE bank_transfers_backoffice ADD COLUMN creator_bank_receipt_photo VARCHAR(255) NULL",
				'creator_bank_receipt_uploaded_at' => "ALTER TABLE bank_transfers_backoffice ADD COLUMN creator_bank_receipt_uploaded_at DATETIME NULL",
				'creator_receipt_status' => "ALTER TABLE bank_transfers_backoffice ADD COLUMN creator_receipt_status VARCHAR(30) NULL DEFAULT 'Pending'",
				'third_party_user_id' => "ALTER TABLE bank_transfers_backoffice ADD COLUMN third_party_user_id BIGINT UNSIGNED NULL",
				'third_party_status' => "ALTER TABLE bank_transfers_backoffice ADD COLUMN third_party_status VARCHAR(30) NULL DEFAULT 'Pending'",
				'third_party_reviewed_by' => "ALTER TABLE bank_transfers_backoffice ADD COLUMN third_party_reviewed_by BIGINT UNSIGNED NULL",
				'third_party_reviewed_at' => "ALTER TABLE bank_transfers_backoffice ADD COLUMN third_party_reviewed_at DATETIME NULL",
				'third_party_reject_reason' => "ALTER TABLE bank_transfers_backoffice ADD COLUMN third_party_reject_reason TEXT NULL",
				'assignment_snapshot_json' => "ALTER TABLE bank_transfers_backoffice ADD COLUMN assignment_snapshot_json LONGTEXT NULL",
				'transaction_status' => "ALTER TABLE bank_transfers_backoffice ADD COLUMN transaction_status VARCHAR(50) NULL DEFAULT 'Pending 2nd Party'",
				'disabled' => "ALTER TABLE bank_transfers_backoffice ADD COLUMN disabled TINYINT(1) NULL DEFAULT 0",
				'deleted' => "ALTER TABLE bank_transfers_backoffice ADD COLUMN deleted TINYINT(1) NULL DEFAULT 0",
				'created_by' => "ALTER TABLE bank_transfers_backoffice ADD COLUMN created_by BIGINT UNSIGNED NULL",
				'updated_by' => "ALTER TABLE bank_transfers_backoffice ADD COLUMN updated_by BIGINT UNSIGNED NULL",
				'approved_by' => "ALTER TABLE bank_transfers_backoffice ADD COLUMN approved_by BIGINT UNSIGNED NULL",
				'approved_at' => "ALTER TABLE bank_transfers_backoffice ADD COLUMN approved_at DATETIME NULL",
				'date_created' => "ALTER TABLE bank_transfers_backoffice ADD COLUMN date_created DATETIME NULL",
				'date_updated' => "ALTER TABLE bank_transfers_backoffice ADD COLUMN date_updated DATETIME NULL",
				'date_deleted' => "ALTER TABLE bank_transfers_backoffice ADD COLUMN date_deleted DATETIME NULL",
			];

			foreach ($columns as $column => $sql) {
				$rows = $db->query("SHOW COLUMNS FROM bank_transfers_backoffice LIKE '{$column}'") ?: [];
				if ($db->has_error) {
					throw new \RuntimeException('Unable to inspect bank transfer schema column ' . $column . ': ' . $db->error);
				}
				if (!empty($rows)) {
					continue;
				}
				$db->query($sql);
				if ($db->has_error) {
					throw new \RuntimeException('Unable to add bank transfer schema column ' . $column . ': ' . $db->error);
				}
			}

			foreach ([
				"CREATE INDEX idx_bank_transfers_status ON bank_transfers_backoffice (transaction_status)",
				"CREATE INDEX idx_bank_transfers_creator ON bank_transfers_backoffice (created_by)",
				"CREATE INDEX idx_bank_transfers_withdrawer ON bank_transfers_backoffice (withdrawing_user_id)",
				"CREATE INDEX idx_bank_transfers_second_party ON bank_transfers_backoffice (second_party_user_id)",
				"CREATE INDEX idx_bank_transfers_third_party ON bank_transfers_backoffice (third_party_user_id)",
				"CREATE INDEX idx_bank_transfers_deleted ON bank_transfers_backoffice (deleted)",
			] as $indexSql) {
				try {
					$db->query($indexSql);
				} catch (\Throwable $e) {
					continue;
				}
			}

			try {
				$db->query("UPDATE bank_transfers_backoffice SET second_party_status = 'Pending' WHERE second_party_status IS NULL OR TRIM(second_party_status) = ''");
				$db->query("UPDATE bank_transfers_backoffice SET withdrawal_status = 'Pending' WHERE withdrawal_status IS NULL OR TRIM(withdrawal_status) = ''");
				$db->query("UPDATE bank_transfers_backoffice SET creator_receipt_status = 'Pending' WHERE creator_receipt_status IS NULL OR TRIM(creator_receipt_status) = ''");
				$db->query("UPDATE bank_transfers_backoffice SET third_party_status = 'Pending' WHERE third_party_status IS NULL OR TRIM(third_party_status) = ''");
				$db->query("UPDATE bank_transfers_backoffice SET transaction_status = 'Pending 2nd Party' WHERE transaction_status IS NULL OR TRIM(transaction_status) = ''");
				@file_put_contents($marker, '1');
			} catch (\Throwable $e) {
				throw $e;
			}
		} catch (\Throwable $e) {
			error_log('[backoffice bank transfer schema] ' . $e->getMessage());
			throw $e;
		}
		$ensured = true;
	}
}

backoffice_ensure_bank_transfer_schema();

if(!function_exists('backoffice_commission_bank_options')){
	function backoffice_commission_bank_options(): array
	{
		return [
			'Absa Bank',
			'Apex Nafana Bank',
			'Apex Suma Bank',
			'Access Bank',
			'Cal Bank',
			'Ecobank',
			'Fidelity Bank',
			'First National Bank (FNB)',
			'First Bank Ghana (FBG)',
			'GCB Bank',
			'GT Bank',
			'Stanbic Bank',
		];
	}
}

if(!function_exists('backoffice_ensure_commission_bank_schema')){
	function backoffice_ensure_commission_bank_schema(): void
	{
		static $ensured = false;
		if ($ensured) return;

		$db = new \Core\Database;
		$db->query(
			"CREATE TABLE IF NOT EXISTS backoffice_commission_bank_transfers (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
				bank_name VARCHAR(120) NOT NULL,
				amount DECIMAL(12,2) NOT NULL DEFAULT 0,
				proof_image VARCHAR(255) NOT NULL,
				status VARCHAR(30) NOT NULL DEFAULT 'Pending',
				reject_reason TEXT NULL,
				created_by BIGINT UNSIGNED NOT NULL,
				assigned_admin_id BIGINT UNSIGNED NULL,
				approved_by BIGINT UNSIGNED NULL,
				approved_at DATETIME NULL,
				date_created DATETIME NOT NULL,
				date_updated DATETIME NULL,
				deleted TINYINT(1) NOT NULL DEFAULT 0,
				KEY idx_commission_bank_transfer_status (status),
				KEY idx_commission_bank_transfer_bank (bank_name),
				KEY idx_commission_bank_transfer_created (date_created),
				KEY idx_commission_bank_transfer_assigned_admin (assigned_admin_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
		);
		if ($db->has_error) {
			throw new \RuntimeException('Unable to prepare commission bank transfers: ' . $db->error);
		}
		$db->query(
			"CREATE TABLE IF NOT EXISTS backoffice_commission_bank_transfer_items (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
				transfer_id BIGINT UNSIGNED NOT NULL,
				commission_id BIGINT UNSIGNED NOT NULL,
				user_id BIGINT UNSIGNED NOT NULL,
				branch_id BIGINT UNSIGNED NULL,
				amount DECIMAL(12,2) NOT NULL DEFAULT 0,
				UNIQUE KEY uq_commission_bank_transfer_item (transfer_id, commission_id),
				KEY idx_commission_bank_item_commission (commission_id),
				KEY idx_commission_bank_item_transfer (transfer_id),
				KEY idx_commission_bank_item_branch_user (branch_id, user_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
		);
		if ($db->has_error) {
			throw new \RuntimeException('Unable to prepare commission bank transfer details: ' . $db->error);
		}
		$transferColumns = $db->query('SHOW COLUMNS FROM backoffice_commission_bank_transfers');
		if ($db->has_error || !is_array($transferColumns)) {
			throw new \RuntimeException('Unable to inspect commission bank transfer columns: ' . $db->error);
		}
		$existingTransferColumns = [];
		foreach ($transferColumns as $column) {
			$existingTransferColumns[strtolower((string)($column->Field ?? ''))] = true;
		}
		$transferAlters = [];
		if (empty($existingTransferColumns['updated_by'])) {
			$transferAlters[] = 'ADD COLUMN updated_by BIGINT UNSIGNED NULL AFTER created_by';
		}
		if (empty($existingTransferColumns['assigned_admin_id'])) {
			$transferAlters[] = 'ADD COLUMN assigned_admin_id BIGINT UNSIGNED NULL AFTER created_by';
		}
		if (empty($existingTransferColumns['date_updated'])) {
			$transferAlters[] = 'ADD COLUMN date_updated DATETIME NULL AFTER date_created';
		}
		if (empty($existingTransferColumns['cashdrawer_credited'])) {
			$transferAlters[] = 'ADD COLUMN cashdrawer_credited TINYINT(1) NOT NULL DEFAULT 0 AFTER date_updated';
		}
		if ($transferAlters) {
			$db->query('ALTER TABLE backoffice_commission_bank_transfers ' . implode(', ', $transferAlters));
			if ($db->has_error) {
				throw new \RuntimeException('Unable to prepare commission transfer audit columns: ' . $db->error);
			}
		}

		$ensured = true;
	}
}

if(!function_exists('backoffice_commission_bank_admin_approvers')){
	function backoffice_commission_bank_admin_approvers(): array
	{
		$admins = [];
		foreach (\BackOfficeCashdrawer\getAssignableUsersToday() as $candidate) {
			$roles = backoffice_commission_user_roles($candidate);
			if ((int)($candidate->id ?? 0) > 0 && in_array('admin', $roles, true)) {
				$admins[] = $candidate;
			}
		}
		return $admins;
	}
}

if(!function_exists('backoffice_commission_bank_attention_count')){
	function backoffice_commission_bank_attention_count(int $userId, bool $isAdmin = false): int
	{
		if ($userId <= 0) {
			return 0;
		}
		try {
			$db = new \Core\Database;
			if (!$db->table_exists(['backoffice_commission_bank_transfers'])) {
				return 0;
			}
			$transferColumns = $db->query('SHOW COLUMNS FROM backoffice_commission_bank_transfers');
			if ($db->has_error || !is_array($transferColumns)) {
				throw new \RuntimeException('Unable to inspect commission transfer columns: ' . $db->error);
			}
			$hasAssignedAdminColumn = false;
			foreach ($transferColumns as $column) {
				if (strtolower((string)($column->Field ?? '')) === 'assigned_admin_id') {
					$hasAssignedAdminColumn = true;
					break;
				}
			}
			if (!$hasAssignedAdminColumn) {
				backoffice_ensure_commission_bank_schema();
			}
			$adminScope = $isAdmin ? ' OR assigned_admin_id IS NULL OR assigned_admin_id = 0' : '';
			$rows = $db->query(
				"SELECT COUNT(id) AS pending_count
				 FROM backoffice_commission_bank_transfers
				 WHERE deleted = 0 AND LOWER(TRIM(status)) = 'pending'
				   AND (created_by = :user_id OR assigned_admin_id = :assigned_admin_id{$adminScope})",
				['user_id' => $userId, 'assigned_admin_id' => $userId]
			) ?: [];
			if ($db->has_error) {
				throw new \RuntimeException((string)$db->error);
			}
			return (int)($rows[0]->pending_count ?? 0);
		} catch (\Throwable $error) {
			error_log('[commission bank transfer counter] ' . $error->getMessage());
			return 0;
		}
	}
}

if(!function_exists('backoffice_user_is_general_manager')){
	function backoffice_user_is_general_manager($session = null): bool
	{
		if (user_can('is_general_manager')) {
			return true;
		}

		$allowedRoles = ['general manager', 'general_manager', 'generalmanager', 'gm'];
		return (bool)array_intersect(backoffice_reconciliation_role_names($session), $allowedRoles);
	}
}

if(!function_exists('backoffice_user_can_bank_transfer_third_party')){
	function backoffice_user_can_bank_transfer_third_party($session = null): bool
	{
		return backoffice_user_is_admin($session) || backoffice_user_is_general_manager($session);
	}
}

if(!function_exists('backoffice_user_can_create_from_bank_transfer')){
	function backoffice_user_can_create_from_bank_transfer($session = null): bool
	{
		return backoffice_user_is_admin($session) || user_can('is_manager') || backoffice_user_is_general_manager($session);
	}
}

if(!function_exists('backoffice_bank_transfer_is_from_bank_flow')){
	function backoffice_bank_transfer_is_from_bank_flow(string $transactionType): bool
	{
		return in_array(strtolower(trim($transactionType)), ['from bank', 'to vault'], true);
	}
}

if(!function_exists('backoffice_bank_transfer_is_to_bank_flow')){
	function backoffice_bank_transfer_is_to_bank_flow(string $transactionType): bool
	{
		return in_array(strtolower(trim($transactionType)), ['to bank', 'from vault'], true);
	}
}

if(!function_exists('backoffice_bank_transfer_user_row_is_third_party_candidate')){
	function backoffice_bank_transfer_user_row_is_third_party_candidate(object $row): bool
	{
		if (empty($row)) {
			return false;
		}

		$roles = $row->roles ?? [''];
		if (!is_array($roles)) {
			$roles = [$roles];
		}
		$normalized = array_map(function($role){
			return strtolower(trim((string)$role));
		}, $roles);

		return (bool)array_intersect($normalized, ['admin', 'general manager', 'general_manager', 'generalmanager', 'gm']);
	}
}

if(!function_exists('backoffice_bank_transfer_second_party_candidates')){
	function backoffice_bank_transfer_second_party_candidates(int $excludeUserId = 0): array
	{
		return getAssignableUsersToday($excludeUserId);
	}
}

if(!function_exists('backoffice_bank_transfer_candidates_excluding')){
	function backoffice_bank_transfer_candidates_excluding(array $excludeIds = []): array
	{
		$excludeIds = array_values(array_unique(array_filter(array_map('intval', $excludeIds))));
		$rows = getAssignableUsersToday();
		if (empty($excludeIds)) {
			return $rows;
		}

		return array_values(array_filter($rows, function($row) use ($excludeIds){
			$rowId = (int)($row->id ?? 0);
			return $rowId > 0 && !in_array($rowId, $excludeIds, true);
		}));
	}
}

if(!function_exists('backoffice_bank_transfer_filter_users_by_branch')){
	function backoffice_bank_transfer_filter_users_by_branch(array $rows, int $branchId): array
	{
		if ($branchId <= 0) {
			return [];
		}
		return array_values(array_filter($rows, static function($row) use ($branchId): bool {
			return (int)($row->branch_id ?? 0) === $branchId;
		}));
	}
}

if(!function_exists('backoffice_bank_transfer_all_users_excluding')){
	function backoffice_bank_transfer_all_users_excluding(array $excludeIds = []): array
	{
		$excludeIds = array_values(array_unique(array_filter(array_map('intval', $excludeIds))));
		try {
			$user = new \BackOffices\User;
			$user::$query_id = 'get-users';
			$rows = $user->query(
				"SELECT id, first_name, last_name, image, branch_id
				 FROM users
				 WHERE (disabled IS NULL OR disabled = 0)
				 ORDER BY first_name ASC, last_name ASC"
			) ?: [];
		} catch (\Throwable $e) {
			$rows = [];
		}

		if (empty($excludeIds)) {
			return $rows;
		}

		return array_values(array_filter($rows, function($row) use ($excludeIds){
			$rowId = (int)($row->id ?? 0);
			return $rowId > 0 && !in_array($rowId, $excludeIds, true);
		}));
	}
}

if(!function_exists('backoffice_bank_transfer_third_party_all_candidates')){
	function backoffice_bank_transfer_third_party_all_candidates(array $excludeIds = []): array
	{
		return array_values(array_filter(backoffice_bank_transfer_all_users_excluding($excludeIds), function($row){
			return backoffice_bank_transfer_user_row_is_third_party_candidate($row);
		}));
	}
}

if(!function_exists('backoffice_bank_transfer_include_current_user')){
	function backoffice_bank_transfer_include_current_user(array $rows, int $currentUserId): array
	{
		if ($currentUserId <= 0) {
			return $rows;
		}
		foreach ($rows as $row) {
			if ((int)($row->id ?? 0) === $currentUserId) {
				return $rows;
			}
		}
		foreach (backoffice_bank_transfer_all_users_excluding([]) as $user) {
			if ((int)($user->id ?? 0) === $currentUserId) {
				$rows[] = $user;
				break;
			}
		}
		return $rows;
	}
}

if(!function_exists('backoffice_bank_transfer_third_party_candidates')){
	function backoffice_bank_transfer_third_party_candidates(int $excludeUserId = 0): array
	{
		$users = getAssignableUsersToday($excludeUserId);
		return array_values(array_filter($users, function($row){
			return backoffice_bank_transfer_user_row_is_third_party_candidate($row);
		}));
	}
}

if(!function_exists('backoffice_bank_transfer_user_ids')){
	function backoffice_bank_transfer_user_ids(array $rows): array
	{
		$ids = [];
		foreach ($rows as $row) {
			$id = (int)($row->id ?? 0);
			if ($id > 0) {
				$ids[] = $id;
			}
		}
		return array_values(array_unique($ids));
	}
}

if(!function_exists('backoffice_bank_transfer_pick_assignment_row')){
	function backoffice_bank_transfer_pick_assignment_row(array $rows, string $category, string $bankName, string $networkName): ?array
	{
		$categoryKey = backoffice_assignment_detail_key($category);
		$bankKey = backoffice_assignment_detail_key($bankName);
		$networkKey = backoffice_assignment_detail_key($networkName);

		$normalRows = [];
		foreach ($rows as $row) {
			$row = is_array($row) ? $row : (array)$row;
			$row['_network_key'] = backoffice_assignment_detail_key($row['network'] ?? '');
			$row['_sim_type_key'] = strtolower(trim((string)($row['sim_type'] ?? '')));
			$normalRows[] = $row;
		}

		if ($categoryKey === 'BANK-FLOAT' || $categoryKey === 'BANK FLOAT') {
			$matches = array_values(array_filter($normalRows, function($row) use ($bankKey){
				return ($row['_sim_type_key'] ?? '') === 'bank' && ($bankKey === '' || ($row['_network_key'] ?? '') === $bankKey);
			}));
			foreach ($matches as $row) {
				if (trim((string)($row['account_number'] ?? '')) !== '') {
					return $row;
				}
			}
			foreach ($normalRows as $row) {
				if (($row['_sim_type_key'] ?? '') === 'bank' && trim((string)($row['account_number'] ?? '')) !== '') {
					return $row;
				}
			}
			return $matches[0] ?? null;
		}

		$matches = array_values(array_filter($normalRows, function($row) use ($networkKey){
			return ($networkKey === '' || ($row['_network_key'] ?? '') === $networkKey) && !in_array(($row['_sim_type_key'] ?? ''), ['bank', 'evd'], true);
		}));

		if ($networkKey === 'MTN' || $networkKey === 'G-MONEY') {
			foreach ($matches as $row) {
				if (trim((string)($row['phone_number'] ?? '')) !== '') {
					return $row;
				}
			}
		}
		if ($networkKey === 'TELECEL') {
			foreach ($matches as $row) {
				if (trim((string)($row['till_number'] ?? '')) !== '' || trim((string)($row['shortcode'] ?? '')) !== '') {
					return $row;
				}
			}
		}
		if ($networkKey === 'AT') {
			foreach ($matches as $row) {
				if (
					trim((string)($row['phone_number'] ?? '')) !== '' ||
					trim((string)($row['till_number'] ?? '')) !== '' ||
					trim((string)($row['shortcode'] ?? '')) !== ''
				) {
					return $row;
				}
			}
		}

		return $matches[0] ?? null;
	}
}

if(!function_exists('backoffice_bank_transfer_assignment_snapshot')){
	function backoffice_bank_transfer_assignment_snapshot(int $userId, string $category, string $bankName, string $networkName): array
	{
		if ($userId <= 0) {
			return [
				'missing' => true,
				'message' => 'Select the 3rd party to display assigned account or SIM details.',
				'lines' => [],
			];
		}

		$map = backoffice_user_sim_assignments_map([$userId]);
		$rows = $map[(string)$userId] ?? [];
		$row = backoffice_bank_transfer_pick_assignment_row($rows, $category, $bankName, $networkName);
		if (empty($row)) {
			return [
				'missing' => true,
				'message' => 'No assigned account or SIM details were found for the selected 3rd party.',
				'lines' => [],
			];
		}

		$categoryKey = backoffice_assignment_detail_key($category);
		$networkKey = backoffice_assignment_detail_key($networkName);
		$lines = [];

		if ($categoryKey === 'BANK-FLOAT' || $categoryKey === 'BANK FLOAT') {
			$lines[] = ['label' => 'Bank', 'value' => $bankName];
			$lines[] = ['label' => 'Account number', 'value' => trim((string)($row['account_number'] ?? ''))];
		} else {
			if ($networkKey === 'MTN' || $networkKey === 'G-MONEY') {
				$lines[] = ['label' => 'Phone number', 'value' => trim((string)($row['phone_number'] ?? ''))];
			} elseif ($networkKey === 'TELECEL') {
				$lines[] = ['label' => 'Till number', 'value' => trim((string)($row['till_number'] ?? ''))];
				$lines[] = ['label' => 'Shortcode', 'value' => trim((string)($row['shortcode'] ?? ''))];
			} elseif ($networkKey === 'AT') {
				$lines[] = ['label' => 'Phone number', 'value' => trim((string)($row['phone_number'] ?? ''))];
				$lines[] = ['label' => 'Till number', 'value' => trim((string)($row['till_number'] ?? ''))];
				$lines[] = ['label' => 'Shortcode', 'value' => trim((string)($row['shortcode'] ?? ''))];
			}
		}

		return [
			'missing' => false,
			'message' => '',
			'lines' => $lines,
		];
	}
}

if(!function_exists('backoffice_user_sim_assignments_map')){
	function backoffice_user_sim_assignments_map(array $userIds): array
	{
		$userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
		if(empty($userIds)){
			return [];
		}

		try {
			$db = new \Core\Database;
			$tableRows = $db->query("SHOW TABLES LIKE 'user_sim_assignments'");
			if(empty($tableRows)){
				return [];
			}

			$params = [];
			$placeholders = [];
			foreach($userIds as $index => $uid){
				$key = 'uid' . $index;
				$params[$key] = (string)$uid;
				$placeholders[] = ':' . $key;
			}

			$rows = $db->query(
				"SELECT user_id, network, sim_type, phone_number, till_number, shortcode, account_number, notes
				 FROM user_sim_assignments
				 WHERE deleted = 0 AND user_id IN (" . implode(',', $placeholders) . ")
				 ORDER BY user_id ASC, network ASC, sim_type ASC, id ASC",
				$params
			);
			if(!is_array($rows)){
				return [];
			}

			$map = [];
			foreach($rows as $row){
				$uid = (string)(int)($row->user_id ?? 0);
				if($uid === '0'){
					continue;
				}
				if(!isset($map[$uid])){
					$map[$uid] = [];
				}
				$map[$uid][] = [
					'network' => (string)($row->network ?? ''),
					'sim_type' => (string)($row->sim_type ?? ''),
					'phone_number' => (string)($row->phone_number ?? ''),
					'till_number' => (string)($row->till_number ?? ''),
					'shortcode' => (string)($row->shortcode ?? ''),
					'account_number' => (string)($row->account_number ?? ''),
					'notes' => (string)($row->notes ?? ''),
				];
			}

			return $map;
		} catch (\Throwable $e) {
			error_log('[backoffice] Failed to load user SIM assignment map: ' . $e->getMessage());
			return [];
		}
	}
}

if(!function_exists('backoffice_assignment_detail_key')){
	function backoffice_assignment_detail_key(mixed $value): string
	{
		$value = strtoupper(trim((string)$value));
		$value = str_replace(['_', ' '], '-', $value);
		$value = preg_replace('/-+/', '-', $value);
		$value = trim((string)$value, '-');
		if(in_array($value, ['ECASH', 'E-CASH'], true)) return 'E-CASH';
		if(in_array($value, ['GMONEY', 'G-MONEY'], true)) return 'G-MONEY';
		if(in_array($value, ['GHANA-PAY', 'GHANAPAY'], true)) return 'GHANAPAY';
		if(in_array($value, ['CAL-BANK', 'CALBANK'], true)) return 'CALBANK';
		return $value;
	}
}

if(!function_exists('backoffice_assignment_detail_spec')){
	function backoffice_assignment_detail_spec(mixed $option, mixed $type): array
	{
		$optionKey = backoffice_assignment_detail_key($option);
		$typeKey = backoffice_assignment_detail_key($type);

		if($optionKey === 'BANK'){
			return [['key' => 'account_number', 'label' => 'CalBank account number']];
		}
		if($optionKey === 'EVD'){
			return [['key' => 'phone_number', 'label' => 'Phone number']];
		}
		if($optionKey !== 'E-CASH'){
			return [];
		}
		if(in_array($typeKey, ['MTN', 'GHANAPAY', 'ZEEPAY'], true)){
			return [['key' => 'phone_number', 'label' => 'Phone number']];
		}
		if($typeKey === 'TELECEL'){
			return [
				['key' => 'till_number', 'label' => 'Till number'],
				['key' => 'shortcode', 'label' => 'Shortcode'],
			];
		}
		if($typeKey === 'AT'){
			return [
				['key' => 'shortcode', 'label' => 'Shortcode'],
				['key' => 'phone_number', 'label' => 'Phone number'],
			];
		}
		if($typeKey === 'G-MONEY'){
			return [['key' => 'shortcode', 'label' => 'Shortcode']];
		}

		return [];
	}
}

if(!function_exists('backoffice_pick_assignment_detail_row')){
	function backoffice_pick_assignment_detail_row(array $rows, mixed $option, mixed $type): ?array
	{
		$optionKey = backoffice_assignment_detail_key($option);
		$typeKey = backoffice_assignment_detail_key($type);

		$normalRows = [];
		foreach($rows as $row){
			$row = is_array($row) ? $row : (array)$row;
			$row['_network_key'] = backoffice_assignment_detail_key($row['network'] ?? '');
			$row['_sim_type_key'] = strtolower(trim((string)($row['sim_type'] ?? '')));
			$normalRows[] = $row;
		}

		if($optionKey === 'BANK'){
			foreach($normalRows as $row){
				if($row['_sim_type_key'] === 'bank' && trim((string)($row['account_number'] ?? '')) !== '') return $row;
			}
			foreach($normalRows as $row){
				if(trim((string)($row['account_number'] ?? '')) !== '') return $row;
			}
			return $normalRows[0] ?? null;
		}

		$matches = array_values(array_filter($normalRows, function($row) use ($typeKey){
			return ($row['_network_key'] ?? '') === $typeKey;
		}));

		if($optionKey === 'EVD'){
			foreach($matches as $row){
				if($row['_sim_type_key'] === 'evd' && trim((string)($row['phone_number'] ?? '')) !== '') return $row;
			}
			foreach($matches as $row){
				if(trim((string)($row['phone_number'] ?? '')) !== '') return $row;
			}
			return $matches[0] ?? null;
		}

		foreach($matches as $row){
			if(!in_array($row['_sim_type_key'], ['evd', 'bank'], true)) return $row;
		}

		return $matches[0] ?? null;
	}
}

if(!function_exists('backoffice_assignment_snapshot_lines')){
	function backoffice_assignment_snapshot_lines(int $userId, mixed $option, mixed $type): array
	{
		$spec = backoffice_assignment_detail_spec($option, $type);
		if($userId <= 0 || empty($spec)){
			return [];
		}

		$map = backoffice_user_sim_assignments_map([$userId]);
		$rows = $map[(string)$userId] ?? [];
		$assignment = backoffice_pick_assignment_detail_row($rows, $option, $type);
		if(empty($assignment)){
			return [
				'missing' => true,
				'message' => 'No assigned SIM/wallet details found for this transaction type.',
				'lines' => [],
			];
		}

		$lines = [];
		foreach($spec as $item){
			$value = trim((string)($assignment[$item['key']] ?? ''));
			$lines[] = [
				'label' => $item['label'],
				'value' => $value,
				'missing' => ($value === ''),
			];
		}

		return [
			'missing' => false,
			'message' => '',
			'lines' => $lines,
		];
	}
}

if(!function_exists('backoffice_ensure_reconciliation_breakdown_schema')){
	function backoffice_ensure_reconciliation_breakdown_schema(): void
	{
		static $ensured = false;
		if ($ensured) return;
		$ensured = true;
		$marker = backoffice_runtime_marker_path('backoffice_reconciliation_breakdown_schema_20260427.done');
		if(file_exists($marker)){
			return;
		}
		try {
			$db = new \Core\Database;
			if (!$db->table_exists(['reconciliations'])) {
				return;
			}
			$rows = $db->query("SHOW COLUMNS FROM reconciliations LIKE 'system_denos_json'") ?: [];
			if (empty($rows)) {
				try {
					$db->query("ALTER TABLE reconciliations ADD COLUMN system_denos_json LONGTEXT NULL");
				} catch (\Throwable $e) {
					return;
				}
			}
			@file_put_contents($marker, '1');
		} catch (\Throwable $e) {
			return;
		}
	}
}

backoffice_ensure_reconciliation_breakdown_schema();

if(!function_exists('backoffice_ensure_reconciliation_access_schema')){
	function backoffice_ensure_reconciliation_access_schema(): void
	{
		static $ensured = false;
		if ($ensured) return;
		$ensured = true;
		$marker = backoffice_runtime_marker_path('backoffice_reconciliation_access_schema_20260427.done');
		if(file_exists($marker)){
			try {
				$db = new \Core\Database;
				if ($db->table_exists(['reconciliations_summary'])) {
					$rows = $db->query("SHOW COLUMNS FROM reconciliations_summary LIKE 'late_access_id'") ?: [];
					if (empty($rows)) {
						$db->query("ALTER TABLE reconciliations_summary ADD COLUMN late_access_id BIGINT UNSIGNED NULL AFTER session_id");
					}
				}
			} catch (\Throwable $e) {
				// keep existing schema behavior if this optional column cannot be added
			}
			return;
		}

		try {
			$db = new \Core\Database;
			if (!$db->table_exists(['reconciliations_summary'])) {
				return;
			}

			$columns = [
				'transaction_status' => "ALTER TABLE reconciliations_summary ADD COLUMN transaction_status VARCHAR(50) NULL DEFAULT 'Submitted' AFTER remarks",
				'rejection_reason' => "ALTER TABLE reconciliations_summary ADD COLUMN rejection_reason TEXT NULL AFTER transaction_status",
				'rejected_by' => "ALTER TABLE reconciliations_summary ADD COLUMN rejected_by VARCHAR(100) NULL AFTER rejection_reason",
				'date_rejected' => "ALTER TABLE reconciliations_summary ADD COLUMN date_rejected DATETIME NULL AFTER rejected_by",
				'approved_by' => "ALTER TABLE reconciliations_summary ADD COLUMN approved_by VARCHAR(100) NULL AFTER date_rejected",
				'date_approved' => "ALTER TABLE reconciliations_summary ADD COLUMN date_approved DATETIME NULL AFTER approved_by",
				'late_access_id' => "ALTER TABLE reconciliations_summary ADD COLUMN late_access_id BIGINT UNSIGNED NULL AFTER session_id",
			];

			foreach ($columns as $column => $sql) {
				$rows = $db->query("SHOW COLUMNS FROM reconciliations_summary LIKE '{$column}'") ?: [];
				if (!empty($rows)) {
					continue;
				}
				try {
					$db->query($sql);
				} catch (\Throwable $e) {
					continue;
				}
			}

			try {
				$db->query("UPDATE reconciliations_summary SET transaction_status = 'Submitted' WHERE transaction_status IS NULL OR TRIM(transaction_status) = ''");
				@file_put_contents($marker, '1');
			} catch (\Throwable $e) {
				return;
			}
		} catch (\Throwable $e) {
			return;
		}
	}
}

backoffice_ensure_reconciliation_access_schema();

if(!function_exists('backoffice_ensure_late_reconciliation_access_schema')){
	function backoffice_ensure_late_reconciliation_access_schema(): void
	{
		static $ensured = false;
		if ($ensured) return;
		$ensured = true;

		try {
			$db = new \Core\Database;
			$db->query(
				"CREATE TABLE IF NOT EXISTS reconciliation_late_access (
					id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
					user_id BIGINT UNSIGNED NOT NULL,
					cashdrawer_id BIGINT UNSIGNED NOT NULL,
					drawer_date DATE NOT NULL,
					status VARCHAR(30) NOT NULL DEFAULT 'active',
					notes TEXT NULL,
					granted_by BIGINT UNSIGNED NULL,
					granted_at DATETIME NULL,
					notification_seen_at DATETIME NULL,
					used_at DATETIME NULL,
					date_created DATETIME NULL,
					date_updated DATETIME NULL,
					PRIMARY KEY (id),
					KEY user_id (user_id),
					KEY cashdrawer_id (cashdrawer_id),
					KEY drawer_date (drawer_date),
					KEY status (status)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
			);
		} catch (\Throwable $e) {
			error_log('[backoffice] late reconciliation access schema failed: ' . $e->getMessage());
		}
	}
}

backoffice_ensure_late_reconciliation_access_schema();

if(!function_exists('backoffice_late_reconciliation_access_rows')){
	function backoffice_late_reconciliation_access_rows(int $userId = 0, bool $onlyUnseen = false): array
	{
		try {
			backoffice_ensure_late_reconciliation_access_schema();
			$db = new \Core\Database;
			if (!$db->table_exists(['reconciliation_late_access'])) {
				return [];
			}
			$where = "WHERE LOWER(COALESCE(a.status,'')) = 'active'";
			$params = [];
			if ($userId > 0) {
				$where .= " AND a.user_id = :user_id";
				$params['user_id'] = (string)$userId;
			}
			if ($onlyUnseen) {
				$where .= " AND a.notification_seen_at IS NULL";
			}
			return $db->query(
				"SELECT a.*, u.first_name, u.last_name, c.date_created AS cashdrawer_date
				 FROM reconciliation_late_access a
				 LEFT JOIN users u ON u.id = a.user_id
				 LEFT JOIN cashdrawers c ON c.id = a.cashdrawer_id
				 {$where}
				 ORDER BY a.granted_at DESC, a.id DESC",
				$params
			) ?: [];
		} catch (\Throwable $e) {
			return [];
		}
	}
}

if(!function_exists('backoffice_active_late_reconciliation_access')){
	function backoffice_active_late_reconciliation_access(int $userId, int $accessId = 0)
	{
		$rows = backoffice_late_reconciliation_access_rows($userId, false);
		foreach ($rows as $row) {
			if ($accessId <= 0 || (int)($row->id ?? 0) === $accessId) {
				return $row;
			}
		}
		return null;
	}
}

if(!function_exists('backoffice_cashdrawer_snapshot_by_id')){
	function backoffice_cashdrawer_snapshot_by_id(int $cashdrawerId, int $userId): array
	{
		if ($cashdrawerId <= 0 || $userId <= 0) {
			return [];
		}
		$db = new \Core\Database;
		$drawer = $db->query(
			"SELECT id, user_id, date_created FROM cashdrawers WHERE id = :id AND user_id = :user_id LIMIT 1",
			['id' => $cashdrawerId, 'user_id' => $userId]
		) ?: [];
		if (empty($drawer)) {
			return [];
		}

		$ghsTotals = $db->query(
			"SELECT COALESCE(SUM(volume),0) AS total_volume FROM drawer_denos WHERE drawer_id = :drawer_id AND user_id = :user_id",
			['drawer_id' => $cashdrawerId, 'user_id' => $userId]
		) ?: [];
		$ghsHistory = $db->query(
			"SELECT denomination, SUM(count) AS count, SUM(volume) AS volume, MAX(date_created) AS last_date
			 FROM drawer_denos
			 WHERE drawer_id = :drawer_id AND user_id = :user_id
			 GROUP BY denomination
			 ORDER BY last_date DESC
			 LIMIT 10",
			['drawer_id' => $cashdrawerId, 'user_id' => $userId]
		) ?: [];
		$cfaTotals = $db->query(
			"SELECT COALESCE(SUM(volume),0) AS total_volume FROM drawer_fx_denos WHERE drawer_id = :drawer_id AND user_id = :user_id AND currency = 'CFA'",
			['drawer_id' => $cashdrawerId, 'user_id' => $userId]
		) ?: [];
		$cfaHistory = $db->query(
			"SELECT denomination, SUM(count) AS count, SUM(volume) AS volume, MAX(date_created) AS last_date
			 FROM drawer_fx_denos
			 WHERE drawer_id = :drawer_id AND user_id = :user_id AND currency = 'CFA'
			 GROUP BY denomination
			 ORDER BY last_date DESC
			 LIMIT 10",
			['drawer_id' => $cashdrawerId, 'user_id' => $userId]
		) ?: [];
		$normalizeHistory = function($rows): array {
			$out = [];
			foreach ((array)$rows as $row) {
				$out[] = [
					'denomination' => (float)($row->denomination ?? 0),
					'count' => (int)($row->count ?? 0),
					'volume' => round((float)($row->volume ?? 0), 2),
					'last_date' => (string)($row->last_date ?? ''),
				];
			}
			return $out;
		};

		return [
			'drawer_id' => $cashdrawerId,
			'drawer_user_id' => $userId,
			'drawer_date' => (string)($drawer[0]->date_created ?? ''),
			'ghs_balance' => round((float)($ghsTotals[0]->total_volume ?? 0), 2),
			'cfa_balance' => round((float)($cfaTotals[0]->total_volume ?? 0), 2),
			'ghs_history' => $normalizeHistory($ghsHistory),
			'cfa_history' => $normalizeHistory($cfaHistory),
		];
	}
}

if(!function_exists('backoffice_unreconciled_past_cashdrawers')){
	function backoffice_unreconciled_past_cashdrawers(int $limit = 100): array
	{
		try {
			$db = new \Core\Database;
			if (!$db->table_exists(['cashdrawers', 'users', 'reconciliations_summary'])) {
				return [];
			}
			$today = date('Y-m-d');
			$limit = max(1, min(500, $limit));
			$cashdrawerColumns = backoffice_table_columns($db, 'cashdrawers');
			$summaryColumns = backoffice_table_columns($db, 'reconciliations_summary');
			$cashdrawerDeletedSql = !empty($cashdrawerColumns['deleted']) ? " AND (c.deleted IS NULL OR c.deleted = 0)" : "";
			$summaryDeletedSql = !empty($summaryColumns['deleted']) ? " AND (r.deleted IS NULL OR r.deleted = 0)" : "";
			return $db->query(
				"SELECT c.id, c.user_id, c.date_created, u.first_name, u.last_name, a.id AS active_access_id
				 FROM cashdrawers c
				 LEFT JOIN users u ON u.id = c.user_id
				 LEFT JOIN reconciliation_late_access a ON a.cashdrawer_id = c.id AND LOWER(COALESCE(a.status,'')) = 'active'
				 WHERE DATE(c.date_created) < :today
				 AND DATE(c.date_created) < CURDATE()
				 {$cashdrawerDeletedSql}
				 AND NOT EXISTS (
				 	SELECT 1 FROM reconciliations_summary r
				 	WHERE r.created_by = c.user_id
				 	{$summaryDeletedSql}
				 	AND DATE(r.date_created) = DATE(c.date_created)
				 	AND LOWER(COALESCE(NULLIF(TRIM(r.transaction_status),''),'submitted')) NOT IN ('rejected','declined')
				 )
				 ORDER BY c.date_created DESC, c.id DESC
				 LIMIT {$limit}",
				['today' => $today]
			) ?: [];
		} catch (\Throwable $e) {
			return [];
		}
	}
}

if(!function_exists('backoffice_reconciliation_role_names')){
	function backoffice_reconciliation_role_names($session = null): array
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

if(!function_exists('backoffice_user_is_admin')){
	function backoffice_user_is_admin($session = null): bool
	{
		if (user_can('is_admin')) {
			return true;
		}

		return in_array('admin', backoffice_reconciliation_role_names($session), true);
	}
}

if(!function_exists('backoffice_user_is_operations_manager')){
	function backoffice_user_is_operations_manager($session = null): bool
	{
		if (user_can('is_operations_manager')) {
			return true;
		}

		$allowedRoles = [
			'operations manager',
			'operation manager',
			'operations_manager',
			'operation_manager',
			'operationsmanager',
			'operationmanager',
			'is_operations_manager',
		];
		return (bool)array_intersect(backoffice_reconciliation_role_names($session), $allowedRoles);
	}
}

if(!function_exists('backoffice_user_can_view_general_balance')){
	function backoffice_user_can_view_general_balance($session = null): bool
	{
		if (backoffice_user_is_admin($session)
			|| user_can('is_operations_manager')
			|| backoffice_user_is_general_manager($session)) {
			return true;
		}

		$allowedRoles = [
			'operations manager',
			'operation manager',
			'operations_manager',
			'operation_manager',
			'operationsmanager',
			'operationmanager',
			'is_operations_manager',
		];
		return (bool)array_intersect(backoffice_reconciliation_role_names($session), $allowedRoles);
	}
}

if(!function_exists('backoffice_user_can_oversee_reconciliations')){
	function backoffice_user_can_oversee_reconciliations($session = null): bool
	{
		if (backoffice_user_is_admin($session)) {
			return true;
		}
		if (user_can('is_operations_manager') || user_can('is_general_manager') || user_can('is_manager')) {
			return true;
		}

		$allowedRoles = [
			'operations manager',
			'operation manager',
			'operations_manager',
			'operation_manager',
			'operationsmanager',
			'operationmanager',
			'general manager',
			'general_manager',
			'generalmanager',
			'gm',
		];

		return (bool)array_intersect(backoffice_reconciliation_role_names($session), $allowedRoles);
	}
}

if(!function_exists('backoffice_user_can_reject_reconciliations')){
	function backoffice_user_can_reject_reconciliations($session = null): bool
	{
		return backoffice_user_is_admin($session) || backoffice_user_is_operations_manager($session);
	}
}

if(!function_exists('backoffice_user_can_approve_reconciliations')){
	function backoffice_user_can_approve_reconciliations($session = null): bool
	{
		return backoffice_user_is_admin($session) || backoffice_user_is_operations_manager($session);
	}
}

if(!function_exists('backoffice_user_can_see_shared_reconciliation_channels')){
	function backoffice_user_can_see_shared_reconciliation_channels($session = null): bool
	{
		return backoffice_user_can_oversee_reconciliations($session);
	}
}

if(!function_exists('backoffice_reconciliation_is_shared_channel')){
	function backoffice_reconciliation_is_shared_channel(string $key): bool
	{
		$key = strtoupper(trim($key));
		$key = str_replace(['_', ' '], '-', $key);
		$key = preg_replace('/-+/', '-', (string)$key);

		return strpos($key, 'MOMOPAY') !== false || strpos($key, 'MOMO-PAY') !== false;
	}
}

if(!function_exists('backoffice_active_reconciliation_for_day')){
	function backoffice_active_reconciliation_for_day(int $userId, ?string $date = null)
	{
		if ($userId <= 0) {
			return null;
		}

		try {
			backoffice_ensure_reconciliation_access_schema();
			$tz = new \DateTimeZone('Africa/Accra');
			$day = $date ? new \DateTime($date, $tz) : new \DateTime('now', $tz);
			$start = $day->format('Y-m-d 00:00:00');
			$end = (clone $day)->modify('+1 day')->format('Y-m-d 00:00:00');

			$db = new \Core\Database;
			if (!$db->table_exists(['reconciliations_summary'])) {
				return null;
			}

			$rows = $db->query(
				"SELECT *
				 FROM reconciliations_summary
				 WHERE (deleted IS NULL OR deleted = 0)
				 AND created_by = :uid
				 AND date_created >= :start
				 AND date_created < :end
				 AND LOWER(COALESCE(NULLIF(TRIM(transaction_status), ''), 'submitted')) NOT IN ('rejected', 'declined')
				 ORDER BY date_created DESC, id DESC
				 LIMIT 1",
				[
					'uid' => (string)$userId,
					'start' => $start,
					'end' => $end,
				]
			) ?: [];

			return !empty($rows) ? $rows[0] : null;
		} catch (\Throwable $e) {
			return null;
		}
	}
}

if(!function_exists('backoffice_approved_reconciliation_for_day')){
	function backoffice_approved_reconciliation_for_day(int $userId, ?string $date = null)
	{
		if ($userId <= 0) {
			return null;
		}

		try {
			backoffice_ensure_reconciliation_access_schema();
			$tz = new \DateTimeZone('Africa/Accra');
			$day = $date ? new \DateTime($date, $tz) : new \DateTime('now', $tz);
			$start = $day->format('Y-m-d 00:00:00');
			$end = (clone $day)->modify('+1 day')->format('Y-m-d 00:00:00');

			$db = new \Core\Database;
			if (!$db->table_exists(['reconciliations_summary'])) {
				return null;
			}

			$rows = $db->query(
				"SELECT *
				 FROM reconciliations_summary
				 WHERE (deleted IS NULL OR deleted = 0)
				 AND created_by = :uid
				 AND date_created >= :start
				 AND date_created < :end
				 AND LOWER(COALESCE(NULLIF(TRIM(transaction_status), ''), 'submitted')) = 'approved'
				 ORDER BY date_created DESC, id DESC
				 LIMIT 1",
				[
					'uid' => (string)$userId,
					'start' => $start,
					'end' => $end,
				]
			) ?: [];

			return !empty($rows) ? $rows[0] : null;
		} catch (\Throwable $e) {
			return null;
		}
	}
}

if(!function_exists('backoffice_reconciliation_state_message')){
	function backoffice_reconciliation_state_message($reconciliation = null): string
	{
		$statusValue = 'Submitted';
		if (is_object($reconciliation)) {
			$statusValue = $reconciliation->transaction_status ?? 'Submitted';
		} elseif (is_array($reconciliation)) {
			$statusValue = $reconciliation['transaction_status'] ?? 'Submitted';
		}
		$status = strtolower(trim((string)$statusValue));
		if ($status === 'approved') {
			return 'Your reconciliation has been approved for today. All regular pages are locked until Admin rejects it with a reason and allows you to redo reconciliation for that day.';
		}
		if ($status === 'submitted') {
			return 'You have already submitted reconciliation for today. It is awaiting Admin approval or rejection before you can redo it.';
		}

		return 'You have already submitted reconciliation for today. Admin must reject it with a reason before redo is allowed.';
	}
}

if(!function_exists('backoffice_reconciliation_lock_message')){
	function backoffice_reconciliation_lock_message($reconciliation = null): string
	{
		return backoffice_reconciliation_state_message($reconciliation);
	}
}

add_action('before_controller', function () {
	$vars = get_value();
	$admin_route = $vars['admin_route'] ?? 'admin';
	$plugin_route = $vars['plugin_route'] ?? 'back_office';

	if ((URL(0) ?? '') === $admin_route
		&& (URL(1) ?? '') === $plugin_route
		&& (URL(2) ?? '') === 'general_balance'
		&& !backoffice_user_can_view_general_balance(get_session())) {
		message_fail('You do not have permission to view General Balancing.');
		redirect($admin_route . '/' . $plugin_route . '/list');
		exit;
	}
	if ((URL(0) ?? '') === $admin_route && (URL(1) ?? '') === $plugin_route && in_array((URL(2) ?? ''), ['general_balance', 'general_balance_pdf'], true)) {
		return;
	}

	if ((URL(0) ?? '') !== $admin_route) {
		return;
	}

	$session = get_session();
	$userId = (int)($session->id ?? 0);
	if ($userId <= 0 || backoffice_user_is_admin($session)) {
		return;
	}

	if ((URL(1) ?? '') === $plugin_route && (URL(2) ?? '') === 'reconciliation') {
		$lateAccessId = (int)($_GET['late_access'] ?? 0);
		if ($lateAccessId > 0 && backoffice_active_late_reconciliation_access($userId, $lateAccessId)) {
			return;
		}
	}

	$approved = backoffice_approved_reconciliation_for_day($userId);
	if (empty($approved)) {
		return;
	}

	$approvedReconciliationId = (string)($approved->id ?? $approved->session_id ?? '');
	$approvedReconciliationUrl = $admin_route . '/' . $plugin_route . '/view_reconciliation/' . $approvedReconciliationId;
	if ((URL(1) ?? '') === $plugin_route && (URL(2) ?? '') === 'view_reconciliation') {
		return;
	}

	if ((URL(1) ?? '') === $plugin_route && (URL(2) ?? '') === 'reconciliation' && $approvedReconciliationId !== '') {
		redirect($approvedReconciliationUrl);
		exit;
	}

	message_fail(backoffice_reconciliation_lock_message($approved));
	redirect($approvedReconciliationId !== '' ? $approvedReconciliationUrl : ($admin_route . '/' . $plugin_route . '/reconciliation'));
	exit;
});

add_action('before_controller', function () {
	$vars = get_value();
	$admin_route = $vars['admin_route'] ?? 'admin';
	$plugin_route = $vars['plugin_route'] ?? 'back_office';

	if ((URL(0) ?? '') !== $admin_route) {
		return;
	}
	if ((URL(1) ?? '') !== $plugin_route) {
		return;
	}

	$session = get_session();
	$userId = (int)($session->id ?? 0);
	if ($userId <= 0) {
		return;
	}

	$overdueAssignments = backoffice_overdue_commission_assignments($userId);
	if (empty($overdueAssignments)) {
		return;
	}

	$signatureParts = array_map(function($item){
		return (int)(($item['assignment']->id ?? 0)) . ':' . (string)($item['commission_field'] ?? '');
	}, $overdueAssignments);
	sort($signatureParts);
	$signature = date('Y-m-d') . ':' . implode(',', $signatureParts);

	$ses = new \Core\Session;
	$markerKey = 'backoffice_overdue_commission_notice_sig';
	$existingSignature = (string)$ses->get($markerKey);

	if ($existingSignature === $signature) {
		return;
	}

	if (trim((string)message_fail()) === '') {
		message_fail(backoffice_overdue_commission_message($overdueAssignments));
	}

	$ses->set($markerKey, $signature);
});

if(!function_exists('backoffice_force_denomination_currency')){
	function backoffice_force_denomination_currency(array $payload, string $currency, bool &$changed = false): array
	{
		$currency = strtoupper(trim($currency ?: 'GHS'));
		if (!in_array($currency, ['GHS', 'CFA'], true)) {
			$currency = 'GHS';
		}

		$normalized = [];
		foreach ($payload as $key => $value) {
			if (is_array($value)) {
				$isDenominationRow = array_key_exists('denomination', $value) || array_key_exists('volume', $value) || array_key_exists('count', $value);
				if ($isDenominationRow) {
					$currentCurrency = strtoupper(trim((string)($value['currency'] ?? '')));
					if ($currentCurrency !== $currency) {
						$value['currency'] = $currency;
						$changed = true;
					}
					$normalized[$key] = $value;
					continue;
				}

				$subChanged = false;
				$normalized[$key] = backoffice_force_denomination_currency($value, $currency, $subChanged);
				if ($subChanged) {
					$changed = true;
				}
				continue;
			}

			$normalized[$key] = $value;
		}

		return $normalized;
	}
}

if(!function_exists('backoffice_backfill_pending_cfa_request_denominations')){
	function backoffice_backfill_pending_cfa_request_denominations(): void
	{
		static $ran = false;
		if ($ran) {
			return;
		}
		$ran = true;

		try {
			$db = new \Core\Database;
			if (!$db->table_exists(['requests'])) {
				return;
			}

			$rows = $db->query(
				"SELECT id, denos_json
				 FROM requests
				 WHERE LOWER(COALESCE(transaction_status, '')) = 'pending'
				 AND UPPER(COALESCE(request_type, '')) LIKE '%CFA%'
				 AND COALESCE(denos_json, '') <> ''"
			) ?: [];

			foreach ($rows as $row) {
				$decoded = json_decode((string)($row->denos_json ?? ''), true);
				if (!is_array($decoded)) {
					continue;
				}

				$changed = false;
				$fixed = backoffice_force_denomination_currency($decoded, 'CFA', $changed);
				if (!$changed) {
					continue;
				}

				try {
					$db->query(
						"UPDATE requests
						 SET denos_json = :denos_json
						 WHERE id = :id",
						[
							'denos_json' => json_encode($fixed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
							'id' => (int)($row->id ?? 0),
						]
					);
				} catch (\Throwable $e) {
					continue;
				}
			}
		} catch (\Throwable $e) {
			return;
		}
	}
}

backoffice_backfill_pending_cfa_request_denominations();

add_action('controller', function () {
	$vars = get_value();
	$plugin_route = $vars['plugin_route'] ?? 'back_office';
	if (URL(1) === $plugin_route && URL(2) === 'general_balance_pdf') {
		$session = get_session();
		if (!backoffice_user_can_view_general_balance($session)) {
			http_response_code(403);
			exit('Not authorized');
		}
		$admin_route = $vars['admin_route'] ?? 'admin';
		$generalBalanceExport = true;
		require plugin_path('controllers/general-balance-controller.php');
		exit;
	}
	if (URL(1) !== $plugin_route || URL(2) !== 'export') {
		return;
	}
	require plugin_path('controllers/export-controller.php');
	exit;
});

add_action('controller', function () {
	$vars = get_value();
	$plugin_route = $vars['plugin_route'] ?? 'back_office';

	if (URL(1) !== $plugin_route || URL(2) !== 'request_precheck') {
		return;
	}

	header('Content-Type: application/json; charset=utf-8');
	$respond = static function (array $payload, ?int $statusCode = null): void {
		if ($statusCode !== null) {
			http_response_code($statusCode);
		}
		while (ob_get_level() > 0) {
			ob_end_clean();
		}
		echo json_encode($payload);
		exit;
	};

	try {
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			$respond(['success' => false, 'message' => 'Invalid request method.'], 405);
		}

		$session = get_session();
		$userId = (int)($session->id ?? 0);
		if ($userId <= 0) {
			$respond(['success' => false, 'message' => 'Not authenticated'], 401);
		}
		if (!user_can('add_request')) {
			$respond(['success' => false, 'message' => 'You do not have permission to add requests.'], 403);
		}

		$network = strtoupper(trim((string)($_POST['network'] ?? '')));
		$requestType = strtoupper(trim((string)($_POST['request_type'] ?? '')));
		$amountValue = (float)($_POST['amount'] ?? 0);
		$assignedUserId = (int)($_POST['assigned'] ?? 0);
		$rawDenos = $_POST['denos_json'] ?? ($_POST['denos'] ?? '[]');
		$isCashRequest = ($network === 'CASH');

		if ($network === '' || $amountValue <= 0) {
			$respond(['success' => true, 'message' => 'OK']);
		}
		if ($assignedUserId <= 0) {
			$respond(['success' => false, 'message' => 'Select a receiving user before submitting this request.']);
		}

		$assignedDrawer = \BackOfficeCashdrawer\getActiveDrawer($assignedUserId);
		if (empty($assignedDrawer)) {
			$respond(['success' => false, 'message' => 'The assigned user must open a cashdrawer before this request can be submitted.']);
		}
		$drawerUserId = (int)($assignedDrawer['drawer_user_id'] ?? $assignedUserId);

		if (!$isCashRequest) {
			if ($requestType === '') {
				$respond(['success' => false, 'message' => 'Request type is required.']);
			}
			$ecashChannelKey = backoffice_request_resolved_channel_key($network, $requestType);
			$ecashCheck = \BackOfficeCashdrawer\checkEcashChannelSufficiency($drawerUserId, $ecashChannelKey, $amountValue);
			if (empty($ecashCheck['ok'])) {
				$required = number_format((float)($ecashCheck['required'] ?? 0), 2);
				$available = number_format((float)($ecashCheck['available'] ?? 0), 2);
				$respond(['success' => false, 'message' => "Insufficient {$ecashChannelKey} balance in the assigned user's cashdrawer. Required: {$required}, available: {$available}."]);
			}
			$respond(['success' => true, 'message' => 'OK']);
		}

		$cashCurrency = in_array($requestType, ['GHS', 'CFA'], true) ? $requestType : 'GHS';
		$denosPayload = \BackOfficeCashdrawer\normalizeDenominations($rawDenos, $amountValue, $cashCurrency);
		$denosTotal = \BackOfficeCashdrawer\calculateTotal($denosPayload);
		if (empty($denosPayload)) {
			$respond(['success' => false, 'message' => 'Capture the cash denominations before submitting this request.']);
		}
		if ($denosTotal > 0 && abs($denosTotal - $amountValue) > 0.01) {
			$respond(['success' => false, 'message' => 'Calculator total must match request amount.']);
		}

		$balanceCheck = \BackOfficeCashdrawer\checkCashDenominationSufficiencyCurrency($drawerUserId, $denosPayload, $amountValue, $cashCurrency);
		if (empty($balanceCheck['ok'])) {
			$cur = strtoupper((string)($balanceCheck['currency'] ?? $cashCurrency));
			$required = number_format((float)($balanceCheck['required'] ?? 0), ($cur === 'CFA') ? 0 : 2);
			$available = number_format((float)($balanceCheck['available'] ?? 0), ($cur === 'CFA') ? 0 : 2);
			$details = '';
			$countsCheck = \BackOfficeCashdrawer\checkCashDenominationCountSufficiencyCurrency($drawerUserId, $denosPayload, $amountValue, $cashCurrency);
			if (empty($countsCheck['ok'])) {
				$shortages = $countsCheck['shortages'] ?? [];
				if (!empty($shortages) && is_array($shortages)) {
					$parts = [];
					foreach ($shortages as $s) {
						$den = (string)($s['denomination'] ?? '');
						$need = (int)($s['required_count'] ?? 0);
						$have = (int)($s['available_count'] ?? 0);
						if ($den === '' || $need <= 0) continue;
						$parts[] = "{$den} x{$need} (available {$have})";
					}
					if (!empty($parts)) {
						$details = ' Shortages: ' . implode(', ', $parts) . '.';
					}
				}
			}
			$respond(['success' => false, 'message' => "Insufficient {$cur} cash balance in the assigned user's cashdrawer. Required: {$cur} {$required}, available: {$cur} {$available}.{$details}"]);
		}

		$countsCheck = \BackOfficeCashdrawer\checkCashDenominationCountSufficiencyCurrency($drawerUserId, $denosPayload, $amountValue, $cashCurrency);
		if (empty($countsCheck['ok'])) {
			$cur = strtoupper((string)($countsCheck['currency'] ?? $cashCurrency));
			$shortages = $countsCheck['shortages'] ?? [];
			$msg = "Insufficient {$cur} denominations in the assigned user's cashdrawer.";
			if (!empty($shortages) && is_array($shortages)) {
				$parts = [];
				foreach ($shortages as $s) {
					$den = (string)($s['denomination'] ?? '');
					$need = (int)($s['required_count'] ?? 0);
					$have = (int)($s['available_count'] ?? 0);
					if ($den === '' || $need <= 0) continue;
					$parts[] = "{$den} x{$need} (available {$have})";
				}
				if (!empty($parts)) {
					$msg .= ' Shortages: ' . implode(', ', $parts) . '.';
				}
			}
			$respond(['success' => false, 'message' => $msg]);
		}

		$respond(['success' => true, 'message' => 'OK']);
	} catch (\Throwable $e) {
		$respond([
			'success' => false,
			'message' => 'Failed to validate request before submit.',
		], 500);
	}
});


add_action('controller', function () {
	$vars = get_value();
	$plugin_route = $vars['plugin_route'] ?? 'back_office';

	if (URL(1) !== $plugin_route || URL(2) !== 'payment_precheck') {
		return;
	}

	header('Content-Type: application/json; charset=utf-8');
	$respond = static function (array $payload, ?int $statusCode = null): void {
		if ($statusCode !== null) {
			http_response_code($statusCode);
		}
		while (ob_get_level() > 0) {
			ob_end_clean();
		}
		echo json_encode($payload);
		exit;
	};
	try {
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			$respond(['success' => false, 'message' => 'Invalid request method.'], 405);
		}

		$session = get_session();
		$userId = (int)($session->id ?? 0);
		if ($userId <= 0) {
			$respond(['success' => false, 'message' => 'Not authenticated'], 401);
		}
		if (!user_can('add_payment')) {
			$respond(['success' => false, 'message' => 'You do not have permission to add payments.'], 403);
		}

		$network = strtoupper(trim((string)($_POST['network'] ?? '')));
		$paymentType = strtoupper(trim((string)($_POST['payment_type'] ?? '')));
		$amountValue = (float)($_POST['amount'] ?? 0);
		$rawDenos = $_POST['denos_json'] ?? ($_POST['denos'] ?? '[]');
		$isDynamicBulkPayment = in_array($network, ['E-CASH', 'EVD'], true);
		$bulkBreakdown = ['items' => [], 'total' => 0.0, 'errors' => []];
		if ($isDynamicBulkPayment) {
			$bulkBreakdown = backoffice_payment_normalize_breakdown($_POST['bulk_payment_items'] ?? '[]', $network);
			$amountValue = (float)$bulkBreakdown['total'];
		}

		if ($network === '' || $amountValue <= 0) {
			$respond(['success' => true, 'message' => 'OK']);
		}

		$payerDrawer = \BackOfficeCashdrawer\getActiveDrawer($userId);
		if (empty($payerDrawer)) {
			$respond(['success' => false, 'message' => 'You must open a cashdrawer before submitting this payment.']);
		}
		$drawerUserId = (int)($payerDrawer['drawer_user_id'] ?? $userId);

		if ($network !== 'CASH') {
			if ($isDynamicBulkPayment) {
				if (empty($bulkBreakdown['items'])) {
					$respond(['success' => false, 'message' => 'Enter at least one ' . $network . ' payment amount.']);
				}
				if (!empty($bulkBreakdown['errors'])) {
					$respond(['success' => false, 'message' => implode(' ', $bulkBreakdown['errors'])]);
				}
				$insufficientMessages = [];
				foreach ($bulkBreakdown['items'] as $item) {
					$channel = backoffice_payment_resolved_channel_key((string)($item['channel'] ?? ''));
					$itemAmount = (float)($item['amount'] ?? 0);
					if ($channel === '' || $itemAmount <= 0) {
						continue;
					}
					$ecashCheck = \BackOfficeCashdrawer\checkEcashChannelSufficiency($drawerUserId, $channel, $itemAmount);
					if (empty($ecashCheck['ok'])) {
						$required = number_format((float)($ecashCheck['required'] ?? 0), 2);
						$available = number_format((float)($ecashCheck['available'] ?? 0), 2);
						$insufficientMessages[] = "{$channel}: required {$required}, available {$available}";
					}
				}
				if (!empty($insufficientMessages)) {
					$respond([
						'success' => false,
						'message' => "Insufficient E-Cash balance:\n" . implode("\n", $insufficientMessages),
					]);
				}
				$respond(['success' => true, 'message' => 'OK']);
			}
			if ($paymentType === '') {
				$respond(['success' => false, 'message' => 'Payment type is required.']);
			}
			if (backoffice_payment_is_telecel_type($paymentType) && !backoffice_payment_amount_is_whole_number($amountValue)) {
				$respond(['success' => false, 'message' => 'Telecel payments must use whole-number amounts only.']);
			}
			$ecashChannelKey = backoffice_payment_resolved_channel_key($paymentType);
			$ecashCheck = \BackOfficeCashdrawer\checkEcashChannelSufficiency($drawerUserId, $ecashChannelKey, $amountValue);
			if (empty($ecashCheck['ok'])) {
				$required = number_format((float)($ecashCheck['required'] ?? 0), 2);
				$available = number_format((float)($ecashCheck['available'] ?? 0), 2);
				$respond(['success' => false, 'message' => "Insufficient E-Cash balance on {$ecashChannelKey}. Required: {$required}, available: {$available}." ]);
			}
			$respond(['success' => true, 'message' => 'OK']);
		}

		$cashCurrency = in_array($paymentType, ['GHS', 'CFA'], true) ? $paymentType : 'GHS';
		$denosPayload = \BackOfficeCashdrawer\normalizeDenominations($rawDenos, $amountValue, $cashCurrency);
		$denosTotal = \BackOfficeCashdrawer\calculateTotal($denosPayload);
		if (empty($denosPayload)) {
			$respond(['success' => false, 'message' => 'Capture the cash denominations before submitting.']);
		}
		if ($denosTotal > 0 && abs($denosTotal - $amountValue) > 0.01) {
			$respond(['success' => false, 'message' => 'Calculator total must match payment amount.']);
		}

		$balanceCheck = \BackOfficeCashdrawer\checkCashDenominationSufficiencyCurrency($drawerUserId, $denosPayload, $amountValue, $cashCurrency);
		if (empty($balanceCheck['ok'])) {
			$cur = strtoupper((string)($balanceCheck['currency'] ?? $cashCurrency));
			$required = number_format((float)($balanceCheck['required'] ?? 0), ($cur === 'CFA') ? 0 : 2);
			$available = number_format((float)($balanceCheck['available'] ?? 0), ($cur === 'CFA') ? 0 : 2);
			$details = '';
			$countsCheck = \BackOfficeCashdrawer\checkCashDenominationCountSufficiencyCurrency($drawerUserId, $denosPayload, $amountValue, $cashCurrency);
			if (empty($countsCheck['ok'])) {
				$shortages = $countsCheck['shortages'] ?? [];
				if (!empty($shortages) && is_array($shortages)) {
					$parts = [];
					foreach ($shortages as $s) {
						$den = (string)($s['denomination'] ?? '');
						$need = (int)($s['required_count'] ?? 0);
						$have = (int)($s['available_count'] ?? 0);
						if ($den === '' || $need <= 0) continue;
						$parts[] = "{$den} x{$need} (available {$have})";
					}
					if (!empty($parts)) {
						$details = ' Shortages: ' . implode(', ', $parts) . '.';
					}
				}
			}
			$respond(['success' => false, 'message' => "Insufficient {$cur} cashdrawer balance. Required: {$cur} {$required}, available: {$cur} {$available}.{$details}" ]);
		}

		$countsCheck = \BackOfficeCashdrawer\checkCashDenominationCountSufficiencyCurrency($drawerUserId, $denosPayload, $amountValue, $cashCurrency);
		if (empty($countsCheck['ok'])) {
			$cur = strtoupper((string)($countsCheck['currency'] ?? $cashCurrency));
			$shortages = $countsCheck['shortages'] ?? [];
			$msg = "Insufficient {$cur} denominations in cashdrawer.";
			if (!empty($shortages) && is_array($shortages)) {
				$parts = [];
				foreach ($shortages as $s) {
					$den = (string)($s['denomination'] ?? '');
					$need = (int)($s['required_count'] ?? 0);
					$have = (int)($s['available_count'] ?? 0);
					if ($den === '' || $need <= 0) continue;
					$parts[] = "{$den} x{$need} (available {$have})";
				}
				if (!empty($parts)) {
					$msg .= ' Shortages: ' . implode(', ', $parts) . '.';
				}
			}
			$respond(['success' => false, 'message' => $msg]);
		}

		$respond(['success' => true, 'message' => 'OK']);
	} catch (\Throwable $e) {
		$respond([
			'success' => false,
			'message' => 'Failed to validate payment',
			'error' => $e->getMessage(),
		], 500);
	}
});

/** JSON endpoint for AJAX Requests tab **/
add_action('controller', function () {
	$vars = get_value();
	$plugin_route = $vars['plugin_route'] ?? 'back_office';

	if (URL(1) !== $plugin_route || URL(2) !== 'requests_data') {
		return;
	}

	while (ob_get_level() > 0) {
		ob_end_clean();
	}
	header('Content-Type: application/json; charset=utf-8');
	try {
		$session = get_session();
		$userId = (int)($session->id ?? 0);
		if ($userId <= 0) {
			http_response_code(401);
			echo json_encode(['success' => false, 'message' => 'Not authenticated']);
			return;
		}

		$dbAjax = new \Core\Database;

		$isAdminViewer = user_can('is_admin');
		if ($isAdminViewer) {
			try {
				$backfillPath = plugin_path('helpers/commissions-backfill.php');
				if ($backfillPath && file_exists($backfillPath)) {
					require_once $backfillPath;
					\BackofficeCommissions\backoffice_run_charges_backfill($dbAjax, !empty($_GET['force_backfill']));
				}
			} catch (\Throwable $e) {
				// do not block list rendering if backfill fails
			}
		}
		$page = max(1, (int)($_GET['page'] ?? 1));
		$limit = max(5, min(50, (int)($_GET['limit'] ?? 10)));
		$offset = ($page - 1) * $limit;
		$find = trim($_GET['find'] ?? '');
		$status = strtolower(trim($_GET['status'] ?? ''));
		$scope = strtolower(trim($_GET['scope'] ?? 'all'));
		$createdByFilter = (int)($_GET['created_by'] ?? 0);
		$assignedFilter = (int)($_GET['assigned'] ?? ($_GET['pay_to'] ?? 0));

		$conditions = [];
		$params = [];
		if ($scope === 'created') {
			$conditions[] = 'm.created_by = :user_id';
			$params['user_id'] = $userId;
		} elseif ($scope === 'assigned') {
			$conditions[] = 'm.assigned = :user_id';
			$params['user_id'] = $userId;
		} elseif (!$isAdminViewer) {
			$conditions[] = '(m.created_by = :user_id OR m.assigned = :user_id)';
			$params['user_id'] = $userId;
		}
		if ($createdByFilter > 0) {
			$conditions[] = 'm.created_by = :created_by';
			$params['created_by'] = $createdByFilter;
		}
		if ($assignedFilter > 0) {
			$conditions[] = 'm.assigned = :assigned';
			$params['assigned'] = $assignedFilter;
		}

		if ($find !== '') {
			$conditions[] = '(
				m.id LIKE :find OR
				m.wallet_number LIKE :find OR
				m.till_number LIKE :find OR
				m.request_type LIKE :find OR
				m.network LIKE :find OR
				m.transaction_status LIKE :find OR
				m.created_by LIKE :find OR
				m.assigned LIKE :find OR
				u.first_name LIKE :find OR
				u.last_name LIKE :find OR
				CONCAT(COALESCE(u.first_name, \'\'), \' \', COALESCE(u.last_name, \'\')) LIKE :find OR
				u_assigned.first_name LIKE :find OR
				u_assigned.last_name LIKE :find OR
				CONCAT(COALESCE(u_assigned.first_name, \'\'), \' \', COALESCE(u_assigned.last_name, \'\')) LIKE :find
			)';
			$params['find'] = "%{$find}%";
		}
		if ($status !== '' && $status !== 'all') {
			$conditions[] = 'LOWER(m.transaction_status) = :status';
			$params['status'] = $status;
		}

		$where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';
		$limitSql = " LIMIT {$limit} OFFSET {$offset}";
		$requestPartyJoins = "
			LEFT JOIN users AS u ON m.created_by = u.id
			LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id
		";

		$sqlRows = "
			SELECT
				m.id,
				m.date_created,
				m.request_type,
				m.network,
				m.description,
				m.amount,
				m.transaction_status,
				m.reject_reason,
				m.created_by,
				m.assigned,
				u.first_name AS user_first_name,
				u.last_name AS user_last_name,
				u.image AS user_image,
				u_assigned.first_name AS assigned_first_name,
				u_assigned.last_name AS assigned_last_name,
				u_assigned.image AS assigned_image
			FROM requests AS m
			{$requestPartyJoins}
			{$where}
			ORDER BY m.id DESC
			{$limitSql}
		";

		$rows = $dbAjax->query($sqlRows, $params) ?: [];

		$sqlCount = "
			SELECT 
				COUNT(*) AS total_rows,
				COALESCE(SUM(amount),0) AS total_amount,
				COALESCE(SUM(CASE WHEN UPPER(m.request_type) LIKE '%CFA%' THEN amount ELSE 0 END),0) AS total_amount_cfa,
				COALESCE(SUM(CASE WHEN UPPER(m.request_type) LIKE '%CFA%' THEN 0 ELSE amount END),0) AS total_amount_ghs,
				SUM(CASE WHEN LOWER(transaction_status) = 'pending' THEN 1 ELSE 0 END) AS pending_count,
				SUM(CASE WHEN LOWER(transaction_status) = 'approved' THEN 1 ELSE 0 END) AS approved_count,
				SUM(CASE WHEN LOWER(transaction_status) = 'rejected' THEN 1 ELSE 0 END) AS rejected_count
			FROM requests AS m
			{$requestPartyJoins}
			{$where}
		";
		$countRow = ($dbAjax->query($sqlCount, $params) ?: [])[0] ?? null;
		$totalRows = $countRow ? (int)($countRow->total_rows ?? 0) : 0;
		$totalPages = $totalRows > 0 ? (int)ceil($totalRows / $limit) : 1;
		$totalAmount = $countRow ? (float)($countRow->total_amount ?? 0.0) : 0.0;
		$totalAmountCfa = $countRow ? (float)($countRow->total_amount_cfa ?? 0.0) : 0.0;
		$totalAmountGhs = $countRow ? (float)($countRow->total_amount_ghs ?? 0.0) : 0.0;
		$pendingCount = $countRow ? (int)($countRow->pending_count ?? 0) : 0;
		$approvedCount = $countRow ? (int)($countRow->approved_count ?? 0) : 0;
		$rejectedCount = $countRow ? (int)($countRow->rejected_count ?? 0) : 0;

		// Previous day window (yesterday 00:00:00 to today 00:00:00)
		$yStart = date('Y-m-d 00:00:00', strtotime('-1 day'));
		$yEnd   = date('Y-m-d 00:00:00');
		$paramsPrev = $params;
		$paramsPrev['y_start'] = $yStart;
		$paramsPrev['y_end'] = $yEnd;
		$yWhere = $where
			? ($where . ' AND m.date_created >= :y_start AND m.date_created < :y_end')
			: 'WHERE m.date_created >= :y_start AND m.date_created < :y_end';
		$sqlPrev = "SELECT COALESCE(SUM(amount),0) AS prev_total_amount, COUNT(*) AS prev_total_rows FROM requests AS m {$requestPartyJoins} {$yWhere}";
		$prevRow = ($dbAjax->query($sqlPrev, $paramsPrev) ?: [])[0] ?? null;
		$prevTotalAmount = $prevRow ? (float)($prevRow->prev_total_amount ?? 0.0) : 0.0;
		$prevTotalRows = $prevRow ? (int)($prevRow->prev_total_rows ?? 0) : 0;

		$rowsPayload = array_map(function ($row) use ($userId, $session) {
			$createdName = trim(($row->user_first_name ?? '') . ' ' . ($row->user_last_name ?? ''));
			$assignedName = trim(($row->assigned_first_name ?? '') . ' ' . ($row->assigned_last_name ?? ''));
			$statusLower = strtolower($row->transaction_status ?? '');
			$createdById = (int)($row->created_by ?? 0);
			$assignedId = (int)($row->assigned ?? 0);
			return [
				'id' => (int)$row->id,
				'created_at' => $row->date_created ?? '',
				'network' => $row->network ?? '',
				'request_type' => $row->request_type ?? '',
				'description' => $row->description ?? '',
				'amount' => (float)($row->amount ?? 0),
				'status' => $row->transaction_status ?? '',
				'reject_reason' => $row->reject_reason ?? '',
				'created_by' => [
					'id' => $createdById,
					'name' => $createdName,
					'image' => get_image($row->user_image ?? ''),
				],
				'assigned_to' => [
					'id' => $assignedId,
					'name' => $assignedName,
					'image' => get_image($row->assigned_image ?? ''),
				],
				'can_edit' => in_array($statusLower, ['draft', 'pending'], true),
				'is_pending' => $statusLower === 'pending',
				'can_act' => ($statusLower === 'pending' && $assignedId === $userId),
			];
		}, $rows);

		echo json_encode([
			'success' => true,
			'rows' => $rowsPayload,
			'pagination' => [
				'page' => $page,
				'limit' => $limit,
				'total_rows' => $totalRows,
				'total_pages' => $totalPages,
			],
			'totals' => [
				'total_amount' => $totalAmount,
				'total_amount_ghs' => $totalAmountGhs,
				'total_amount_cfa' => $totalAmountCfa,
				'pending' => $pendingCount,
				'approved' => $approvedCount,
				'rejected' => $rejectedCount,
				'prev_total_amount' => $prevTotalAmount,
				'prev_total_rows' => $prevTotalRows,
			],
		]);
	} catch (\Throwable $e) {
		http_response_code(500);
		echo json_encode([
			'success' => false,
			'message' => 'Failed to load requests',
			'error' => $e->getMessage(),
		]);
	}
	exit;
});

add_action('controller', function () {
	$vars = get_value();
	$plugin_route = $vars['plugin_route'] ?? 'back_office';

	if (URL(1) !== $plugin_route || URL(2) !== 'commissions_users_v2') {
		return;
	}

	header('Content-Type: application/json; charset=utf-8');
	try {
		$session = get_session();
		$userId = (int)($session->id ?? 0);
		if ($userId <= 0) {
			http_response_code(401);
			echo json_encode(['success' => false, 'message' => 'Not authenticated']);
			return;
		}
		if (!backoffice_commission_can_view_all($session)) {
			http_response_code(403);
			echo json_encode(['success' => false, 'message' => 'Access denied']);
			return;
		}

		$find = trim($_GET['find'] ?? '');
		if ($find === '') {
			echo json_encode(['success' => true, 'users' => []]);
			return;
		}
		$findLike = "%{$find}%";
		$dbAjax = new \Core\Database;
		$sql = "
			SELECT
				u.id,
				u.first_name,
				u.last_name,
				u.image
			FROM users AS u
			WHERE u.deleted = 0 AND (u.first_name LIKE :find OR u.last_name LIKE :find OR CONCAT(u.first_name,' ',u.last_name) LIKE :find OR u.id LIKE :find)
			ORDER BY u.first_name ASC
			LIMIT 20
		";
		$rows = $dbAjax->query($sql, ['find' => $findLike]) ?: [];
		$users = array_map(function ($r) {
			$name = trim((string)($r->first_name ?? '') . ' ' . (string)($r->last_name ?? ''));
			return [
				'id' => (int)($r->id ?? 0),
				'name' => $name !== '' ? $name : ('User ' . (int)($r->id ?? 0)),
				'image' => get_image($r->image ?? ''),
			];
		}, $rows);

		echo json_encode(['success' => true, 'users' => $users]);
	} catch (\Throwable $e) {
		http_response_code(500);
		echo json_encode([
			'success' => false,
			'message' => 'Failed to load users',
			'error' => $e->getMessage(),
		]);
	}
	exit;
});

/** JSON endpoint for inline request preview (denomination breakdown) **/
add_action('controller', function () {
	$vars = get_value();
	$plugin_route = $vars['plugin_route'] ?? 'back_office';

	if (URL(1) !== $plugin_route || URL(2) !== 'request_view_modal') {
		return;
	}

	header('Content-Type: application/json; charset=utf-8');
	$respond = static function (array $payload, ?int $statusCode = null): void {
		if ($statusCode !== null) {
			http_response_code($statusCode);
		}
		while (ob_get_level() > 0) {
			ob_end_clean();
		}
		echo json_encode($payload);
		exit;
	};
	try {
		$session = get_session();
		$userId = (int)($session->id ?? 0);
		if ($userId <= 0) {
			$respond(['success' => false, 'message' => 'Not authenticated'], 401);
		}

		$id = (int)($_GET['id'] ?? 0);
		if ($id <= 0) {
			$respond(['success' => false, 'message' => 'Invalid request id.'], 400);
		}

		$request = new \BackOffices\RequestClass;
		// Include creator and assignee names for context
		$sql = "
			SELECT m.*, 
				u.first_name AS user_first_name,
				u.last_name AS user_last_name,
				u_assigned.first_name AS assigned_first_name,
				u_assigned.last_name AS assigned_last_name
			FROM requests AS m
			LEFT JOIN users AS u ON m.created_by = u.id
			LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id
			WHERE m.id = :id
			LIMIT 1
		";
		$row = ($request->query($sql, ['id' => $id]) ?: [])[0] ?? null;
		if (!$row) {
			$respond(['success' => false, 'message' => 'Request not found.'], 404);
		}

		$amountValue = (float)($row->amount ?? 0);
		$isCashRequest = backoffice_request_is_cash($row);
		$currency = backoffice_request_cash_currency($row);
		$rawDenos = $row->denos_json ?? '[]';
		$denosPayload = normalizeDenominations($rawDenos, 0, $currency);
		$verifiedDenosPayload = normalizeDenominations((string)($row->verified_denos_json ?? '[]'), 0, $currency);
		$denoRowsHtml = '';
		foreach ($denosPayload as $item) {
			$note = isset($item['denomination']) ? (float)$item['denomination'] : 0;
			$count = isset($item['count']) ? (int)$item['count'] : 0;
			$volume = isset($item['volume']) ? (float)$item['volume'] : ($note * $count);
			if ($count <= 0 && $volume == 0.0) continue;
			$denoRowsHtml .= '<tr>'
				. '<td>' . $currency . ' ' . number_format($note, ($currency === 'CFA') ? 0 : 2) . '</td>'
				. '<td class="text-center">' . $count . '</td>'
				. '<td class="text-end">' . $currency . ' ' . number_format($volume, ($currency === 'CFA') ? 0 : 2) . '</td>'
				. '</tr>';
		}

		$createdName = trim(($row->user_first_name ?? '') . ' ' . ($row->user_last_name ?? ''));
		$assignedName = trim(($row->assigned_first_name ?? '') . ' ' . ($row->assigned_last_name ?? ''));
		$assignedSnapshot = function_exists('backoffice_assignment_snapshot_lines')
			? backoffice_assignment_snapshot_lines((int)($row->created_by ?? 0), $row->network ?? '', $row->request_type ?? '')
			: [];

		$respond([
			'success' => true,
			'id' => (int)$row->id,
			'amount' => $amountValue,
			'from' => $createdName,
			'to' => $assignedName,
			'network' => (string)($row->network ?? ''),
			'request_type' => (string)($row->request_type ?? ''),
			'is_cash_request' => $isCashRequest,
			'currency' => $currency,
			'description' => (string)($row->description ?? ''),
			'denos_html' => $denoRowsHtml,
			'denos' => $denosPayload,
			'expected_denos' => $denosPayload,
			'verified_denos' => $verifiedDenosPayload,
			'assigned_snapshot' => $assignedSnapshot,
		]);
	} catch (\Throwable $e) {
		$respond([
			'success' => false,
			'message' => 'Failed to load request preview',
			'error' => $e->getMessage(),
		], 500);
	}
});

/** JSON endpoint for inline approve / reject actions on Requests tab **/
add_action('controller', function () {
	$vars = get_value();
	$plugin_route = $vars['plugin_route'] ?? 'back_office';

	if (URL(1) !== $plugin_route || URL(2) !== 'request_action') {
		return;
	}

	header('Content-Type: application/json; charset=utf-8');
	$respond = static function (array $payload, ?int $statusCode = null): void {
		if ($statusCode !== null) {
			http_response_code($statusCode);
		}
		while (ob_get_level() > 0) {
			ob_end_clean();
		}
		echo json_encode($payload);
		exit;
	};
	try {
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			$respond(['success' => false, 'message' => 'Invalid request method.'], 405);
		}

		$session = get_session();
		$userId = (int)($session->id ?? 0);
		if ($userId <= 0) {
			$respond(['success' => false, 'message' => 'Not authenticated'], 401);
		}
		if (!user_can('edit_request')) {
			$respond(['success' => false, 'message' => 'You do not have permission to update requests.'], 403);
		}

		$id = (int)($_POST['id'] ?? 0);
		$action = strtolower(trim($_POST['action'] ?? ''));
		if ($id <= 0 || !in_array($action, ['approve', 'reject'], true)) {
			$respond(['success' => false, 'message' => 'Invalid request payload.'], 400);
		}

		$request = new \BackOffices\RequestClass;
		$row = $request->first(['id' => $id]);
		if (!$row) {
			$respond(['success' => false, 'message' => 'Request not found.'], 404);
		}

		$statusLower = strtolower($row->transaction_status ?? '');
		$assignedUserId = (int)($row->assigned ?? 0);
		if ($assignedUserId !== $userId) {
			$respond(['success' => false, 'message' => 'Only the assigned user can approve or reject this request.']);
		}
		if ($statusLower !== 'pending' && $statusLower !== 'draft') {
			$respond(['success' => false, 'message' => 'Only pending or draft requests can be updated inline.']);
		}

		// Base update payload
		$update = [
			'date_updated' => date('Y-m-d H:i:s'),
			'updated_by' => $userId,
		];
		$whatsappForCreator = static function (string $status, array $details = []) use ($row, $assignedUserId, $userId): array {
			return backoffice_whatsapp_notify_creator_on_assignee_update([
				'type' => 'Backoffice Request',
				'id' => (int)$row->id,
				'status' => $status,
				'creator_id' => (int)($row->created_by ?? 0),
				'assigned_id' => $assignedUserId,
				'details' => $details,
				'view_url' => ROOT . '/admin/back_office/view_request/' . (int)$row->id,
				'flash' => false,
			], $userId);
		};

		if ($action === 'reject') {
			$reason = trim($_POST['reason'] ?? '');
			if ($reason !== '') {
				// Lightweight logging without schema changes
				error_log("Backoffice request {$row->id} rejected by user {$userId}: {$reason}");
				$update['reject_reason'] = $reason;
			}
			$update['transaction_status'] = 'Rejected';
			$request->update($row->id, $update);
			if (!empty($request->has_error) || !empty($request->error)) {
				error_log('Backoffice request update failed (reject) for request ' . $row->id . ': ' . ($request->error ?? 'unknown'));
				$respond(['success' => false, 'message' => 'Failed to update request.', 'error' => ($request->error ?? '')]);
			}
			$respond(['success' => true, 'message' => 'Request rejected.', 'new_status' => 'Rejected', 'whatsapp' => $whatsappForCreator('Rejected', ['Reason' => $reason])]);
		}

		// Approve path
		$amountValue = (float)($row->amount ?? 0);
		$isCashRequest = backoffice_request_is_cash($row);

		// For non-cash requests, mark as Approved and sync e-cash channels between assignee and requester
		if (!$isCashRequest) {
			// Ensure assignee has sufficient e-cash channel balance before debiting.
			if ($amountValue > 0) {
				$ecashChannelKey = backoffice_request_resolved_channel_key(
					(string)($row->network ?? ''),
					(string)($row->request_type ?? '')
				);
				$ecashCheck = \BackOfficeCashdrawer\checkEcashChannelSufficiency($assignedUserId, $ecashChannelKey, $amountValue);
				if (empty($ecashCheck['ok'])) {
					$required = number_format((float)($ecashCheck['required'] ?? 0), 2);
					$available = number_format((float)($ecashCheck['available'] ?? 0), 2);
					$respond(['success' => false, 'message' => "Insufficient E-Cash balance on {$ecashChannelKey}. Required: {$required}, available: {$available}." ]);
				}
			}

			$update['transaction_status'] = 'Approved';
			$update['approved_by'] = $userId;
			$update['approved_at'] = date('Y-m-d H:i:s');
			$request->update($row->id, $update);
			if (!empty($request->has_error) || !empty($request->error)) {
				error_log('Backoffice request update failed (approve ecash) for request ' . $row->id . ': ' . ($request->error ?? 'unknown'));
				$respond(['success' => false, 'message' => 'Failed to update request.', 'error' => ($request->error ?? '')]);
			}

			// Attempt to mirror the movement into ecash drawer_balances for both users
			try {
				if ($amountValue > 0) {
					$dbBal = new \Core\Database;
					if ($dbBal->table_exists(['drawer_balances'])) {
						$ecashChannelKey = backoffice_request_resolved_channel_key(
							(string)($row->network ?? ''),
							(string)($row->request_type ?? '')
						);
						$transId = (int)$row->id;
						$transStatus = 'Approved';

						// Helper closure to adjust one user's ecash channel (direction: 'in' => credit, 'out' => debit)
						$adjustEcash = function (int $actorUserId, string $direction) use ($dbBal, $ecashChannelKey, $amountValue, $transId, $transStatus) {
							$drawerContext = \BackOfficeCashdrawer\getActiveDrawer($actorUserId);
							if (empty($drawerContext)) {
								return;
							}
							$drawerId = (int)($drawerContext['drawer_id'] ?? 0);
							$drawerUserId = (int)($drawerContext['drawer_user_id'] ?? $actorUserId);
							if ($drawerId <= 0) {
								return;
							}

							$ecashChannelKey = \BackOfficeCashdrawer\resolveExistingEcashChannelKey($drawerId, $drawerUserId, $ecashChannelKey);
							// For MTN/TELECEL/AT we also accept historical ECASH_ prefixed keys
							$rowsEcash = $dbBal->query(
								"SELECT * FROM drawer_balances WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'ecash' AND (channel_key = :channel_key OR channel_key = :ecash_channel_key) LIMIT 1",
								[
									'drawer_id' => $drawerId,
									'user_id' => $drawerUserId,
									'channel_key' => $ecashChannelKey,
									'ecash_channel_key' => 'ECASH_' . $ecashChannelKey,
								]
							);
							$now = date('Y-m-d H:i:s');
							$creditDelta = (strtolower($direction) === 'in') ? $amountValue : 0.0;
							$debitDelta  = (strtolower($direction) === 'out') ? $amountValue : 0.0;

							if (empty($rowsEcash)) {
								$opening = 0.0;
								$closing = $opening + $creditDelta - $debitDelta;
								$dbBal->query(
									"INSERT INTO drawer_balances (drawer_id, user_id, channel_type, channel_key, opening_balance, credit, debit, closing_balance, date_created, trans_id, trans_status) VALUES (:drawer_id, :user_id, 'ecash', :channel_key, :opening, :credit, :debit, :closing, :date_created, :trans_id, :trans_status)",
									[
										'drawer_id' => $drawerId,
										'user_id' => $drawerUserId,
										'channel_key' => $ecashChannelKey,
										'opening' => $opening,
										'credit' => $creditDelta,
										'debit' => $debitDelta,
										'closing' => $closing,
										'date_created' => $now,
										'trans_id' => $transId,
										'trans_status' => $transStatus,
									]
								);
							} else {
								$rowEcash = $rowsEcash[0];
								$opening = (float)($rowEcash->opening_balance ?? 0);
								$credit  = (float)($rowEcash->credit ?? 0) + $creditDelta;
								$debit   = (float)($rowEcash->debit ?? 0) + $debitDelta;
								$closing = $opening + $credit - $debit;
								$dbBal->query(
									"UPDATE drawer_balances SET channel_key = :channel_key, credit = :credit, debit = :debit, closing_balance = :closing, trans_id = :trans_id, trans_status = :trans_status WHERE id = :id",
									[
										'channel_key' => $ecashChannelKey,
										'credit' => $credit,
										'debit' => $debit,
										'closing' => $closing,
										'trans_id' => $transId,
										'trans_status' => $transStatus,
										'id' => $rowEcash->id,
									]
								);
							}
						};

					// Assignee (receiver of request) pays out e-cash
					$adjustEcash($assignedUserId, 'out');
					// Requester receives e-cash
					$requesterId = (int)($row->created_by ?? 0);
					if ($requesterId > 0) {
						$adjustEcash($requesterId, 'in');
					}
				}
			}
			} catch (\Throwable $e2) {
				// Do not break approval if e-cash sync fails
				error_log('Backoffice ecash sync failed for request ' . $row->id . ': ' . $e2->getMessage());
			}

			$respond(['success' => true, 'message' => 'Request approved.', 'new_status' => 'Approved', 'whatsapp' => $whatsappForCreator('Approved', [
				'Network' => $row->network ?? '',
				'Request type' => $row->request_type ?? '',
				'Amount' => 'GHS ' . number_format($amountValue, 2),
			])]);
		}

		// CASH requests: enforce denomination + cashdrawer rules
		$cashCurrency = backoffice_request_cash_currency($row);
		$rawDenos = $_POST['denos'] ?? ($row->denos_json ?? '[]');
		$denosPayload = normalizeDenominations($rawDenos, $amountValue, $cashCurrency);
		$denosTotal = calculateTotal($denosPayload);
		if (empty($denosPayload)) {
			$respond(['success' => false, 'message' => 'Capture the cash denominations before approving this request.']);
		}
		if ($denosTotal > 0 && abs($denosTotal - $amountValue) > 0.01) {
			$respond(['success' => false, 'message' => 'Calculator total must match request amount.']);
		}
		if ($assignedUserId <= 0) {
			$respond(['success' => false, 'message' => 'Select a receiving user before approving requests.']);
		}

		$check = checkCashDenominationSufficiencyCurrency($assignedUserId, $denosPayload, $amountValue, $cashCurrency);
		if (!$check['ok']) {
			$required = number_format((float)($check['required'] ?? 0), ($cashCurrency === 'CFA') ? 0 : 2);
			$available = number_format((float)($check['available'] ?? 0), ($cashCurrency === 'CFA') ? 0 : 2);
			$message = "The receiving cashdrawer does not have enough {$cashCurrency} notes to fulfill this request.";
			if (($check['required'] ?? 0) > 0) {
				$message .= " Required: {$cashCurrency} {$required}, available: {$cashCurrency} {$available}.";
			}
			$respond(['success' => false, 'message' => $message]);
		}

		$countsCheck = checkCashDenominationCountSufficiencyCurrency($assignedUserId, $denosPayload, $amountValue, $cashCurrency);
		if (empty($countsCheck['ok'])) {
			$shortages = $countsCheck['shortages'] ?? [];
			$msg = "The receiving cashdrawer does not have sufficient {$cashCurrency} denominations to fulfill this request.";
			if (!empty($shortages) && is_array($shortages)) {
				$parts = [];
				foreach ($shortages as $s) {
					$den = (string)($s['denomination'] ?? '');
					$need = (int)($s['required_count'] ?? 0);
					$have = (int)($s['available_count'] ?? 0);
					if ($den === '' || $need <= 0) continue;
					$parts[] = "{$den} x{$need} (available {$have})";
				}
				if (!empty($parts)) {
					$msg .= ' Shortages: ' . implode(', ', $parts) . '.';
				}
			}
			$respond(['success' => false, 'message' => $msg]);
		}

		$recipientDrawer = getActiveDrawer($assignedUserId);
		if (empty($recipientDrawer)) {
			$respond(['success' => false, 'message' => 'The receiving user must open a cashdrawer before approval.']);
		}

		$update['transaction_status'] = 'Approved';
		$update['denos_json'] = json_encode($denosPayload);
		$update['cashdrawer_id'] = $recipientDrawer['drawer_id'];
		$update['drawer_user_id'] = $recipientDrawer['drawer_user_id'];
		$update['approved_by'] = $userId;
		$update['approved_at'] = date('Y-m-d H:i:s');

		$request->update($row->id, $update);
		if (!empty($request->has_error) || !empty($request->error)) {
			error_log('Backoffice request update failed (approve cash) for request ' . $row->id . ': ' . ($request->error ?? 'unknown'));
			$respond(['success' => false, 'message' => 'Failed to update request.', 'error' => ($request->error ?? '')]);
		}

		// Sync cash movements after the approval is saved, but do not roll back the
		// visible result if the drawer mirror hits a downstream issue.
		try {
			$requesterId = (int)($row->created_by ?? 0);
			if ($requesterId > 0) {
				\BackOfficeCashdrawer\syncCashMovementCurrency($requesterId, $denosPayload, $amountValue, 'Approved', $row->id, 'in', $cashCurrency);
			}
			if ($assignedUserId > 0) {
				\BackOfficeCashdrawer\syncCashMovementCurrency($assignedUserId, $denosPayload, $amountValue, 'Approved', $row->id, 'out', $cashCurrency);
			}
		} catch (\Throwable $e2) {
			error_log('Backoffice request cash sync failed for request ' . $row->id . ': ' . $e2->getMessage());
		}

		$respond(['success' => true, 'message' => 'Request approved.', 'new_status' => 'Approved', 'whatsapp' => $whatsappForCreator('Approved', [
			'Network' => $row->network ?? '',
			'Request type' => $row->request_type ?? '',
			'Amount' => $cashCurrency . ' ' . number_format($amountValue, $cashCurrency === 'CFA' ? 0 : 2),
		])]);
	} catch (\Throwable $e) {
		$respond([
			'success' => false,
			'message' => 'Failed to update request',
			'error' => $e->getMessage(),
		], 500);
	}
});

/** JSON endpoint for AJAX Payments tab **/
add_action('controller', function () {
	$vars = get_value();
	$plugin_route = $vars['plugin_route'] ?? 'back_office';

	if (URL(1) !== $plugin_route || URL(2) !== 'payments_data') {
		return;
	}

	while (ob_get_level() > 0) {
		ob_end_clean();
	}
	header('Content-Type: application/json; charset=utf-8');
	try {
		$session = get_session();
		$userId = (int)($session->id ?? 0);
		if ($userId <= 0) {
			http_response_code(401);
			echo json_encode(['success' => false, 'message' => 'Not authenticated']);
			return;
		}

		$dbAjax = new \Core\Database;
		$isAdminViewer = user_can('is_admin');
		$page = max(1, (int)($_GET['page'] ?? 1));
		$limit = max(5, min(50, (int)($_GET['limit'] ?? 10)));
		$offset = ($page - 1) * $limit;
		$find = trim($_GET['find'] ?? '');
		$status = strtolower(trim($_GET['status'] ?? ''));
		$scope = strtolower(trim($_GET['scope'] ?? 'all'));

		$conditions = [];
		$params = [];
		if ($scope === 'created') {
			$conditions[] = 'm.created_by = :user_id';
			$params['user_id'] = $userId;
		} elseif ($scope === 'pay_to') {
			$conditions[] = 'm.pay_to = :user_id';
			$params['user_id'] = $userId;
		} elseif (!$isAdminViewer) {
			$conditions[] = '(m.created_by = :user_id OR m.pay_to = :user_id)';
			$params['user_id'] = $userId;
		}

		if ($find !== '') {
			$conditions[] = '(
				m.id LIKE :find OR
				m.description LIKE :find OR
				m.network LIKE :find OR
				m.payment_type LIKE :find OR
				m.transaction_status LIKE :find OR
				m.created_by LIKE :find OR
				m.pay_to LIKE :find OR
				u.first_name LIKE :find OR
				u.last_name LIKE :find
			)';
			$params['find'] = "%{$find}%";
		}
		if ($status !== '' && $status !== 'all') {
			$conditions[] = 'LOWER(m.transaction_status) = :status';
			$params['status'] = $status;
		}

		$where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';
		$limitSql = " LIMIT {$limit} OFFSET {$offset}";

		$sqlRows = "
			SELECT
				m.id,
				m.date_created,
				m.description,
				m.amount,
				m.transaction_status,
				m.reject_reason,
				m.network,
				m.payment_type,
				m.created_by,
				m.pay_to,
				u.first_name AS user_first_name,
				u.last_name AS user_last_name,
				u.image AS user_image,
				u_payto.first_name AS payto_first_name,
				u_payto.last_name AS payto_last_name,
				u_payto.image AS payto_image,
				out_wallet.name AS out_wallet_name,
				out_wallet.network AS out_wallet_network
			FROM payments_backoffice AS m
			LEFT JOIN users AS u ON m.created_by = u.id
			LEFT JOIN users AS u_payto ON m.pay_to = u_payto.id
			LEFT JOIN mywallets AS out_wallet ON m.wallet_out_id = out_wallet.id
			{$where}
			ORDER BY m.id DESC
			{$limitSql}
		";

		$rows = $dbAjax->query($sqlRows, $params) ?: [];

		$sqlCount = "
			SELECT 
				COUNT(*) AS total_rows,
				COALESCE(SUM(amount),0) AS total_amount,
				SUM(CASE WHEN LOWER(transaction_status) = 'pending' THEN 1 ELSE 0 END) AS pending_count,
				SUM(CASE WHEN LOWER(transaction_status) = 'approved' THEN 1 ELSE 0 END) AS approved_count,
				SUM(CASE WHEN LOWER(transaction_status) = 'rejected' THEN 1 ELSE 0 END) AS rejected_count
			FROM payments_backoffice AS m {$where}
		";
		$countRow = ($dbAjax->query($sqlCount, $params) ?: [])[0] ?? null;
		$totalRows = $countRow ? (int)($countRow->total_rows ?? 0) : 0;
		$totalPages = $totalRows > 0 ? (int)ceil($totalRows / $limit) : 1;
		$totalAmount = $countRow ? (float)($countRow->total_amount ?? 0.0) : 0.0;
		$pendingCount = $countRow ? (int)($countRow->pending_count ?? 0) : 0;
		$approvedCount = $countRow ? (int)($countRow->approved_count ?? 0) : 0;
		$rejectedCount = $countRow ? (int)($countRow->rejected_count ?? 0) : 0;

		// Previous day window (yesterday 00:00:00 to today 00:00:00)
		$yStart = date('Y-m-d 00:00:00', strtotime('-1 day'));
		$yEnd   = date('Y-m-d 00:00:00');
		$paramsPrev = $params;
		$paramsPrev['y_start'] = $yStart;
		$paramsPrev['y_end'] = $yEnd;
		$yWhere = $where
			? ($where . ' AND m.date_created >= :y_start AND m.date_created < :y_end')
			: 'WHERE m.date_created >= :y_start AND m.date_created < :y_end';
		$sqlPrev = "SELECT COALESCE(SUM(amount),0) AS prev_total_amount, COUNT(*) AS prev_total_rows FROM payments_backoffice AS m {$yWhere}";
		$prevRow = ($dbAjax->query($sqlPrev, $paramsPrev) ?: [])[0] ?? null;
		$prevTotalAmount = $prevRow ? (float)($prevRow->prev_total_amount ?? 0.0) : 0.0;
		$prevTotalRows = $prevRow ? (int)($prevRow->prev_total_rows ?? 0) : 0;

		$rowsPayload = array_map(function ($row) use ($userId, $session) {
			$createdName = trim(($row->user_first_name ?? '') . ' ' . ($row->user_last_name ?? ''));
			$paytoName = trim(($row->payto_first_name ?? '') . ' ' . ($row->payto_last_name ?? ''));
			$statusLower = strtolower($row->transaction_status ?? '');
			$createdById = (int)($row->created_by ?? 0);
			$payToId = (int)($row->pay_to ?? 0);
			return [
				'id' => (int)$row->id,
				'created_at' => $row->date_created ?? '',
				'description' => $row->description ?? '',
				'amount' => (float)($row->amount ?? 0),
				'status' => $row->transaction_status ?? '',
				'reject_reason' => $row->reject_reason ?? '',
				'network' => $row->network ?? '',
				'payment_type' => $row->payment_type ?? '',
				'created_by_id' => $createdById,
				'created_by_name' => $createdName,
				'created_by_image' => get_image($row->user_image ?? ''),
				'pay_to_id' => $payToId,
				'created_by' => [
					'name' => $createdName,
					'image' => get_image($row->user_image ?? ''),
				],
				'pay_to' => [
					'name' => $paytoName,
					'image' => get_image($row->payto_image ?? ''),
				],
				'out_wallet' => trim((string)($row->out_wallet_name ?? '') . ' ' . (string)($row->out_wallet_network ?? '')),
				'can_edit' => in_array($statusLower, ['draft', 'pending'], true),
				'can_act' => (user_can('edit_payment') && in_array($statusLower, ['draft', 'pending'], true) && ($payToId === (int)$userId)),
			];
		}, $rows);

		echo json_encode([
			'success' => true,
			'rows' => $rowsPayload,
			'pagination' => [
				'page' => $page,
				'limit' => $limit,
				'total_rows' => $totalRows,
				'total_pages' => $totalPages,
			],
			'totals' => [
				'total_amount' => $totalAmount,
				'pending' => $pendingCount,
				'approved' => $approvedCount,
				'rejected' => $rejectedCount,
				'prev_total_amount' => $prevTotalAmount,
				'prev_total_rows' => $prevTotalRows,
			],
		]);
	} catch (\Throwable $e) {
		http_response_code(500);
		echo json_encode([
			'success' => false,
			'message' => 'Failed to load payments',
			'error' => $e->getMessage(),
		]);
	}
	exit;
});

/** JSON endpoint for AJAX Agent Services tab **/
add_action('controller', function () {
	$vars = get_value();
	$plugin_route = $vars['plugin_route'] ?? 'back_office';

	if (URL(1) !== $plugin_route || URL(2) !== 'agent_services_data') {
		return;
	}

	while (ob_get_level() > 0) {
		ob_end_clean();
	}
	header('Content-Type: application/json; charset=utf-8');
	try {
		$session = get_session();
		$userId = (int)($session->id ?? 0);
		if ($userId <= 0) {
			http_response_code(401);
			echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
			return;
		}

		$dbAjax = new \Core\Database;
		$isAdminViewer = user_can('is_admin');
		$page = max(1, (int)($_GET['page'] ?? 1));
		$perPage = max(5, min(50, (int)($_GET['per_page'] ?? 10)));
		$offset = ($page - 1) * $perPage;
		$search = trim((string)($_GET['search'] ?? ''));
		$status = strtolower(trim((string)($_GET['status'] ?? '')));
		$provider = strtolower(trim((string)($_GET['provider'] ?? '')));
		$user = (int)($_GET['user'] ?? 0);
		$startDate = trim((string)($_GET['start_date'] ?? ''));
		$endDate = trim((string)($_GET['end_date'] ?? ''));

		$conditions = ['(m.deleted IS NULL OR m.deleted = 0)'];
		$params = [];
		if (!$isAdminViewer) {
			$conditions[] = '(m.creator_id = :user_id OR m.assigned_to = :user_id OR m.processor_id = :user_id OR m.approver_id = :user_id)';
			$params['user_id'] = $userId;
		}
		if ($search !== '') {
			$conditions[] = "(
				CAST(m.id AS CHAR) LIKE :search OR
				LOWER(COALESCE(m.customer_type, '')) LIKE :search OR
				LOWER(COALESCE(m.service_type, '')) LIKE :search OR
				LOWER(COALESCE(m.provider, '')) LIKE :search OR
				LOWER(COALESCE(m.paying_network, '')) LIKE :search OR
				LOWER(COALESCE(m.receiving_network, '')) LIKE :search OR
				LOWER(COALESCE(m.agent_name, '')) LIKE :search OR
				LOWER(COALESCE(m.agent_contact, '')) LIKE :search OR
				LOWER(COALESCE(m.business_name, '')) LIKE :search OR
				LOWER(COALESCE(m.agent_number, '')) LIKE :search OR
				LOWER(COALESCE(m.status, '')) LIKE :search OR
				LOWER(COALESCE(c.first_name, '')) LIKE :search OR
				LOWER(COALESCE(c.last_name, '')) LIKE :search OR
				LOWER(COALESCE(a.first_name, '')) LIKE :search OR
				LOWER(COALESCE(a.last_name, '')) LIKE :search OR
				LOWER(COALESCE(u.first_name, '')) LIKE :search OR
				LOWER(COALESCE(u.last_name, '')) LIKE :search
			)";
			$params['search'] = '%' . strtolower($search) . '%';
		}
		if ($status !== '') {
			$conditions[] = 'LOWER(COALESCE(m.status, \'\')) LIKE :status';
			$params['status'] = '%' . $status . '%';
		}
		if ($provider !== '') {
			$conditions[] = "(
				LOWER(COALESCE(m.provider, '')) LIKE :provider OR
				LOWER(COALESCE(m.paying_network, '')) LIKE :provider OR
				LOWER(COALESCE(m.receiving_network, '')) LIKE :provider
			)";
			$params['provider'] = '%' . $provider . '%';
		}
		if ($user > 0) {
			$conditions[] = "(
				m.creator_id = :filter_user OR
				m.assigned_to = :filter_user OR
				m.updated_by = :filter_user
			)";
			$params['filter_user'] = $user;
		}
		if ($startDate !== '') {
			$conditions[] = 'DATE(m.date_created) >= :start_date';
			$params['start_date'] = $startDate;
		}
		if ($endDate !== '') {
			$conditions[] = 'DATE(m.date_created) <= :end_date';
			$params['end_date'] = $endDate;
		}

		$where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';
		$sqlRows = "
			SELECT m.*, 
			       c.first_name AS creator_first_name, c.last_name AS creator_last_name,
				       a.first_name AS assigned_first_name, a.last_name AS assigned_last_name,
				       u.first_name AS updated_by_first_name, u.last_name AS updated_by_last_name
				FROM agent_services AS m
				LEFT JOIN users AS c ON m.creator_id = c.id
				LEFT JOIN users AS a ON m.assigned_to = a.id
				LEFT JOIN users AS u ON m.updated_by = u.id
				{$where}
				ORDER BY m.id DESC
				LIMIT {$perPage} OFFSET {$offset}
		";

		$agent_services_rows = $dbAjax->query($sqlRows, $params) ?: [];

		$sqlCount = "
			SELECT COUNT(*) AS total_rows
			FROM agent_services AS m
			LEFT JOIN users AS c ON m.creator_id = c.id
			LEFT JOIN users AS a ON m.assigned_to = a.id
			LEFT JOIN users AS u ON m.updated_by = u.id
			{$where}
		";
		$countRow = ($dbAjax->query($sqlCount, $params) ?: [])[0] ?? null;
		$totalRecords = $countRow ? (int)($countRow->total_rows ?? 0) : 0;
		$totalPages = $totalRecords > 0 ? (int)ceil($totalRecords / $perPage) : 1;

		$admin_route = $vars['admin_route'] ?? 'admin';
		$plugin_route = $vars['plugin_route'] ?? 'back_office';

		ob_start();
		require plugin_path('views/admin/agent_services/agent_services_rows.php');
		$html = ob_get_clean();

		echo json_encode([
			'status' => 'success',
			'html' => $html,
			'current_page' => $page,
			'total_pages' => $totalPages,
			'total_records' => $totalRecords,
		]);
	} catch (\Throwable $e) {
		http_response_code(500);
		echo json_encode(['status' => 'error', 'message' => 'Failed to load agent services', 'error' => $e->getMessage()]);
	}
	exit;
});

/** JSON endpoint for AJAX Agent Services balance preview **/
add_action('controller', function () {
	$vars = get_value();
	$plugin_route = $vars['plugin_route'] ?? 'back_office';

	if (URL(1) !== $plugin_route || URL(2) !== 'agent_services_balance') {
		return;
	}

	header('Content-Type: application/json; charset=utf-8');
	$respond = static function (array $payload, ?int $statusCode = null): void {
		if ($statusCode !== null) {
			http_response_code($statusCode);
		}
		while (ob_get_level() > 0) {
			ob_end_clean();
		}
		echo json_encode($payload);
		exit;
	};
	try {
		$session = get_session();
		$userId = (int)($session->id ?? 0);
		if ($userId <= 0) {
			$respond(['status' => 'error', 'message' => 'Not authenticated'], 401);
		}

		$assignedId = (int)($_GET['assigned_id'] ?? 0);
		$serviceType = trim((string)($_GET['service_type'] ?? ''));
		$provider = trim((string)($_GET['provider'] ?? ''));
		$payingNetwork = trim((string)($_GET['paying_network'] ?? ''));
		$receivingNetwork = trim((string)($_GET['receiving_network'] ?? ''));
		$amount = max(0.0, (float)($_GET['amount'] ?? 0));

		$noAssignedAllowed = false;
		if ($assignedId <= 0) {
			// Allow admin/operations creating External transactions to check their own balances
			$sessionLocal = get_session();
			$isAdminOrOpsLocal = backoffice_user_is_admin($sessionLocal) || user_can('is_operations_manager');
			$customerTypeCheck = trim((string)($_GET['customer_type'] ?? ''));
			if (!($customerTypeCheck === 'External' && $isAdminOrOpsLocal)) {
				$respond(['status' => 'error', 'message' => 'Assigned user is required'], 400);
			}
			$noAssignedAllowed = true;
		}

		$channelKey = '';
		if ($serviceType === 'Cash4Ecash') {
			$channelKey = $provider ?: $receivingNetwork;
		} elseif ($serviceType === 'Ecash4Cash' || $serviceType === 'Ecash4Ecash') {
			$channelKey = $payingNetwork ?: $provider ?: $receivingNetwork;
		}

		if ($channelKey === '') {
			$respond(['status' => 'error', 'message' => 'Select a service provider or network first'], 400);
		}

		$customerType = trim((string)($_GET['customer_type'] ?? ''));
		$assignedChannelKey = $channelKey;
		$creatorChannelKey = $channelKey;
		if ($customerType === 'Internal' && $serviceType === 'Ecash4Ecash') {
			if ($receivingNetwork !== '') {
				$assignedChannelKey = $receivingNetwork;
			}
			if ($payingNetwork !== '') {
				$creatorChannelKey = $payingNetwork;
			}
		}

		// Assigned ecash/cash availability
		$assignedEcashAvailable = null;
		$assignedEcashOk = true;
		$assignedCashAvailable = null;
		$assignedSufficient = true;
		if (!$noAssignedAllowed) {
			$assignedEcash = \BackOfficeCashdrawer\checkEcashChannelSufficiency($assignedId, $assignedChannelKey, $amount);
			$assignedSnapshot = function_exists('Cfas\\cfa_get_cashdrawer_snapshot') ? \Cfas\cfa_get_cashdrawer_snapshot($assignedId) : null;

			$assignedEcashAvailable = (float)($assignedEcash['available'] ?? 0.0);
			$assignedEcashOk = !empty($assignedEcash['ok']);
			$assignedChannelKey = trim((string)($assignedEcash['channel_key'] ?? $assignedChannelKey));

			$assignedCashAvailable = $assignedSnapshot ? (float)($assignedSnapshot['ghs_balance'] ?? 0.0) : null;

			// Decide which sufficiency to evaluate for assigned user based on customer_type and service_type
			$assignedSufficient = false;
			if ($customerType === 'Internal') {
				if ($serviceType === 'Ecash4Cash') {
					// Assigned is expected to pay CASH out
					$assignedSufficient = ($assignedCashAvailable !== null && $assignedCashAvailable >= $amount);
				} elseif ($serviceType === 'Cash4Ecash') {
					// Assigned is expected to provide ECASH out
					$assignedSufficient = $assignedEcashOk;
				} elseif ($serviceType === 'Ecash4Ecash') {
					// For internal ecash<->ecash, check assigned ecash availability for the needed network
					$assignedSufficient = $assignedEcashOk;
				} else {
					$assignedSufficient = $assignedEcashOk;
				}
			} else {
				// External: determine expected assigned payment type per service
				if ($serviceType === 'Ecash4Cash') {
					$assignedSufficient = ($assignedCashAvailable !== null && $assignedCashAvailable >= $amount);
				} elseif ($serviceType === 'Cash4Ecash') {
					$assignedSufficient = $assignedEcashOk;
				} elseif ($serviceType === 'Ecash4Ecash') {
					$assignedSufficient = $assignedEcashOk;
				} else {
					$assignedSufficient = $assignedEcashOk;
				}
			}
		}

		// Creator (current user) balances
		$creatorId = $userId;
		$creatorSnapshot = function_exists('Cfas\\cfa_get_cashdrawer_snapshot') ? \Cfas\cfa_get_cashdrawer_snapshot($creatorId) : null;
		$creatorCashAvailable = $creatorSnapshot ? (float)($creatorSnapshot['ghs_balance'] ?? 0.0) : null;
		$creatorEcash = \BackOfficeCashdrawer\checkEcashChannelSufficiency($creatorId, $creatorChannelKey, $amount);
		$creatorEcashAvailable = (float)($creatorEcash['available'] ?? 0.0);
		$creatorEcashOk = !empty($creatorEcash['ok']);
		$creatorChannelKey = trim((string)($creatorEcash['channel_key'] ?? $creatorChannelKey));

		$creatorPaymentType = 'ecash';
		if ($customerType === 'Internal') {
			if ($serviceType === 'Cash4Ecash') {
				$creatorPaymentType = 'cash';
			} elseif ($serviceType === 'Ecash4Cash') {
				$creatorPaymentType = 'ecash';
			} elseif ($serviceType === 'Ecash4Ecash') {
				$creatorPaymentType = 'ecash';
			}
		} else {
			if ($serviceType === 'Cash4Ecash') {
				$creatorPaymentType = 'ecash';
			} elseif ($serviceType === 'Ecash4Cash') {
				$creatorPaymentType = 'cash';
			} elseif ($serviceType === 'Ecash4Ecash') {
				$creatorPaymentType = 'ecash';
			}
		}
		if ($creatorPaymentType === 'cash') {
			$creatorSufficient = ($creatorCashAvailable !== null && $creatorCashAvailable >= $amount);
		} else {
			$creatorSufficient = $creatorEcashOk;
		}

		// If this is an External transaction created by a non-admin/ops user,
		// the assigned "sufficient" indicator should reflect the creator's sufficiency
		// because the assignee is only approving; the creator is the one who will pay.
		$isAdminOrOpsLocal = backoffice_user_is_admin($session) || user_can('is_operations_manager');
		if ($customerType === 'External' && !$isAdminOrOpsLocal) {
			$assignedSufficient = (bool)$creatorSufficient;
		}

		$assignedResponse = null;
		if ($customerType !== 'External') {
			$assignedResponse = [
				'cash_available' => $assignedCashAvailable,
				'ecash_available' => $assignedEcashAvailable,
				'ecash_channel_key' => $assignedChannelKey,
				'sufficient' => (bool)$assignedSufficient,
			];
		}

        $respond([
            'status' => 'success',
            'assigned' => $assignedResponse,
            'creator' => [
                'cash_available' => $creatorCashAvailable,
                'ecash_available' => $creatorEcashAvailable,
                'ecash_channel_key' => $creatorChannelKey,
                'sufficient' => (bool)$creatorSufficient,
            ],
            'message' => 'Balance snapshot returned',
        ]);
    } catch (\Throwable $e) {
        $respond(['status' => 'error', 'message' => 'Failed to load balance information', 'error' => $e->getMessage()], 500);
    }
});

/** JSON endpoint for AJAX Commissions tab **/
add_action('controller', function () {
    $vars = get_value();
    $plugin_route = $vars['plugin_route'] ?? 'back_office';

    if (URL(1) !== $plugin_route || URL(2) !== 'commissions_data') {
        return;
    }

	while (ob_get_level() > 0) {
		ob_end_clean();
	}
	header('Content-Type: application/json; charset=utf-8');
	try {
		$session = get_session();
		$userId = (int)($session->id ?? 0);
		if ($userId <= 0) {
			http_response_code(401);
			echo json_encode(['success' => false, 'message' => 'Not authenticated']);
			return;
		}

		$dbAjax = new \Core\Database;
		$commissionColumns = backoffice_table_columns($dbAjax, 'commissions');
		$hasBulkParentId = !empty($commissionColumns['bulk_parent_id']);
		$hasAssigned = !empty($commissionColumns['assigned']);
		$hasRejectReason = !empty($commissionColumns['reject_reason']);
		$assignedExpr = $hasAssigned ? 'COALESCE(m.assigned, m.pay_to)' : 'm.pay_to';
		$page = max(1, (int)($_GET['page'] ?? 1));
		$limit = max(5, min(50, (int)($_GET['limit'] ?? 10)));
		$offset = ($page - 1) * $limit;
		$find = trim($_GET['find'] ?? '');
		$status = strtolower(trim($_GET['status'] ?? ''));
		$scope = strtolower(trim($_GET['scope'] ?? 'all'));

		$conditions = [];
		$params = ['user_id' => $userId];

		// Only show parent rows (bulk parents or normal commissions)
		$conditions[] = 'm.bulk_parent_id IS NULL';

		$canViewAll = backoffice_commission_can_view_all($session);
		if ($scope === 'created') {
			$conditions[] = 'm.created_by = :user_id';
			$params['user_id'] = $userId;
		} elseif ($scope === 'pay_to') {
			$conditions[] = 'COALESCE(m.assigned, m.pay_to) = :user_id';
			$params['user_id'] = $userId;
		} elseif ($canViewAll) {
			// For admins/viewers, default scope (mine/all) should show all.
			$conditions[] = '(:user_id = :user_id)';
		} else {
			// default: mine (created-by or assigned-to)
			$conditions[] = '(m.created_by = :user_id OR COALESCE(m.assigned, m.pay_to) = :user_id)';
			$params['user_id'] = $userId;
		}

		if ($find !== '') {
			$conditions[] = '(
				m.id LIKE :find OR
				m.description LIKE :find OR
				m.network LIKE :find OR
				m.commission_type LIKE :find OR
				m.transaction_status LIKE :find OR
				u.first_name LIKE :find OR
				u.last_name LIKE :find OR
				payto.first_name LIKE :find OR
				payto.last_name LIKE :find OR
				assigned_user.first_name LIKE :find OR
				assigned_user.last_name LIKE :find
			)';
			$params['find'] = "%{$find}%";
		}
		if ($status !== '' && $status !== 'all') {
			if ($status === 'approved') {
				$conditions[] = "LOWER(m.transaction_status) IN ('approved','complete','completed')";
			} else {
				$conditions[] = 'LOWER(m.transaction_status) = :status';
				$params['status'] = $status;
			}
		}

		$where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';
		$limitSql = " LIMIT {$limit} OFFSET {$offset}";

		$sqlRows = "
			SELECT
				m.id,
				m.date_created,
				m.description,
				m.amount,
				m.transaction_status,
				m.network,
				m.commission_type,
				m.created_by,
				m.pay_to,
				m.assigned,
				m.reject_reason,
				u.first_name AS user_first_name,
				u.last_name AS user_last_name,
				u.image AS user_image,
				payto.first_name AS payto_first_name,
				payto.last_name AS payto_last_name,
				payto.image AS payto_image,
				assigned_user.first_name AS assigned_first_name,
				assigned_user.last_name AS assigned_last_name,
				assigned_user.image AS assigned_image
			FROM commissions AS m
			LEFT JOIN users AS u ON m.created_by = u.id
			LEFT JOIN users AS payto ON m.pay_to = payto.id
			LEFT JOIN users AS assigned_user ON COALESCE(m.assigned, m.pay_to) = assigned_user.id
			{$where}
			ORDER BY m.id DESC
			{$limitSql}
		";

		$rows = $dbAjax->query($sqlRows, $params) ?: [];

		$sqlCount = "
			SELECT
				COUNT(*) AS total_rows,
				COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(m.transaction_status,''))) <> 'rejected' THEN amount ELSE 0 END),0) AS total_amount,
				COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(m.transaction_status,''))) <> 'rejected' AND UPPER(m.network) LIKE '%CFA%' THEN amount ELSE 0 END),0) AS total_amount_cfa,
				COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(m.transaction_status,''))) <> 'rejected' AND COALESCE(UPPER(m.network),'') NOT LIKE '%CFA%' THEN amount ELSE 0 END),0) AS total_amount_ghs,
				SUM(CASE WHEN LOWER(transaction_status) = 'pending' THEN 1 ELSE 0 END) AS pending_count,
				SUM(CASE WHEN LOWER(transaction_status) IN ('approved','complete','completed') THEN 1 ELSE 0 END) AS approved_count,
				SUM(CASE WHEN LOWER(transaction_status) = 'rejected' THEN 1 ELSE 0 END) AS rejected_count
			FROM commissions AS m {$where}
		";
		$countRow = ($dbAjax->query($sqlCount, $params) ?: [])[0] ?? null;
		$totalRows = $countRow ? (int)($countRow->total_rows ?? 0) : 0;
		$totalPages = $totalRows > 0 ? (int)ceil($totalRows / $limit) : 1;
		$totalAmount = $countRow ? (float)($countRow->total_amount ?? 0.0) : 0.0;
		$totalAmountCfa = $countRow ? (float)($countRow->total_amount_cfa ?? 0.0) : 0.0;
		$totalAmountGhs = $countRow ? (float)($countRow->total_amount_ghs ?? 0.0) : 0.0;
		$pendingCount = $countRow ? (int)($countRow->pending_count ?? 0) : 0;
		$approvedCount = $countRow ? (int)($countRow->approved_count ?? 0) : 0;
		$rejectedCount = $countRow ? (int)($countRow->rejected_count ?? 0) : 0;

		$yStart = date('Y-m-d 00:00:00', strtotime('-1 day'));
		$yEnd   = date('Y-m-d 00:00:00');
		$paramsPrev = $params;
		$paramsPrev['y_start'] = $yStart;
		$paramsPrev['y_end'] = $yEnd;
		$yWhere = $where
			? ($where . ' AND m.date_created >= :y_start AND m.date_created < :y_end')
			: 'WHERE m.date_created >= :y_start AND m.date_created < :y_end';
		$sqlPrev = "SELECT COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(m.transaction_status,''))) <> 'rejected' THEN amount ELSE 0 END),0) AS prev_total_amount, COUNT(*) AS prev_total_rows FROM commissions AS m {$yWhere}";
		$prevRow = ($dbAjax->query($sqlPrev, $paramsPrev) ?: [])[0] ?? null;
		$prevTotalAmount = $prevRow ? (float)($prevRow->prev_total_amount ?? 0.0) : 0.0;
		$prevTotalRows = $prevRow ? (int)($prevRow->prev_total_rows ?? 0) : 0;

		$rowsPayload = array_map(function ($row) use ($userId, $session) {
			$createdName = trim(($row->user_first_name ?? '') . ' ' . ($row->user_last_name ?? ''));
			$paytoName = trim(($row->payto_first_name ?? '') . ' ' . ($row->payto_last_name ?? ''));
			$assignedName = trim(($row->assigned_first_name ?? '') . ' ' . ($row->assigned_last_name ?? ''));
			$statusLower = strtolower($row->transaction_status ?? '');
			$normalizedStatus = in_array($statusLower, ['complete', 'completed'], true) ? 'approved' : ($row->transaction_status ?? '');
			$normalizedStatusLower = strtolower((string)$normalizedStatus);
			$createdById = (int)($row->created_by ?? 0);
			$payToId = (int)($row->pay_to ?? 0);
			$assignedId = backoffice_commission_assignee_id($row);
			return [
				'id' => (int)($row->id ?? 0),
				'created_at' => $row->date_created ?? '',
				'description' => $row->description ?? '',
				'amount' => (float)($row->amount ?? 0),
				'status' => $normalizedStatus,
				'network' => $row->network ?? '',
				'commission_type' => $row->commission_type ?? '',
				'reject_reason' => $row->reject_reason ?? '',
				'created_by_id' => $createdById,
				'pay_to_id' => $assignedId,
				'created_by' => [
					'id' => $createdById,
					'name' => $createdName,
					'image' => get_image($row->user_image ?? ''),
				],
				'pay_to' => [
					'id' => $payToId,
					'name' => $paytoName,
					'image' => get_image($row->payto_image ?? ''),
				],
				'assigned_to' => [
					'id' => $assignedId,
					'name' => $assignedName !== '' ? $assignedName : $paytoName,
					'image' => get_image($row->assigned_image ?? ($row->payto_image ?? '')),
				],
				'can_edit' => (user_can('edit_payment') && in_array($normalizedStatusLower, ['draft', 'pending'], true)),
				'can_act' => ($assignedId === (int)$userId && in_array($normalizedStatusLower, ['draft', 'pending'], true)),
			];
		}, $rows);

		echo json_encode([
			'success' => true,
			'rows' => $rowsPayload,
			'pagination' => [
				'page' => $page,
				'limit' => $limit,
				'total_rows' => $totalRows,
				'total_pages' => $totalPages,
			],
			'totals' => [
				'total_amount' => $totalAmount,
				'pending' => $pendingCount,
				'approved' => $approvedCount,
				'rejected' => $rejectedCount,
				'prev_total_amount' => $prevTotalAmount,
				'prev_total_rows' => $prevTotalRows,
			],
		]);
	} catch (\Throwable $e) {
		http_response_code(500);
		echo json_encode([
			'success' => false,
			'message' => 'Failed to load commissions',
			'error' => $e->getMessage(),
		]);
	}
	exit;
});

add_action('controller', function () {
	$vars = get_value();
	$plugin_route = $vars['plugin_route'] ?? 'back_office';

	if (URL(1) !== $plugin_route || URL(2) !== 'commissions_dashboard_v2') {
		return;
	}

	while (ob_get_level() > 0) {
		ob_end_clean();
	}
	header('Content-Type: application/json; charset=utf-8');
	try {
		$session = get_session();
		$userId = (int)($session->id ?? 0);
		if ($userId <= 0) {
			http_response_code(401);
			echo json_encode(['success' => false, 'message' => 'Not authenticated']);
			return;
		}

		$dbAjax = new \Core\Database;
		$commissionColumns = backoffice_table_columns($dbAjax, 'commissions');
		$hasBulkParentId = !empty($commissionColumns['bulk_parent_id']);
		$hasAssigned = !empty($commissionColumns['assigned']);
		$assignedExpr = $hasAssigned ? 'COALESCE(m.assigned, m.pay_to)' : 'm.pay_to';
		$period = strtolower(trim($_GET['period'] ?? '30d'));
		$start = trim($_GET['start'] ?? '');
		$end = trim($_GET['end'] ?? '');
		$status = strtolower(trim($_GET['status'] ?? ''));
		$scope = strtolower(trim($_GET['scope'] ?? 'all'));
		$network = trim($_GET['network'] ?? '');
		$ctype = trim($_GET['commission_type'] ?? '');
		$createdBy = (int)($_GET['created_by'] ?? 0);
		$payTo = (int)($_GET['pay_to'] ?? 0);
		$find = trim($_GET['find'] ?? '');

		$rangeStart = null;
		$rangeEndExclusive = null;
		if ($start !== '' && $end !== '') {
			$rangeStart = date('Y-m-d 00:00:00', strtotime($start));
			$rangeEndExclusive = date('Y-m-d 00:00:00', strtotime($end . ' +1 day'));
		} else {
			$today0 = date('Y-m-d 00:00:00');
			$tomorrow0 = date('Y-m-d 00:00:00', strtotime('+1 day'));
			if ($period === 'today') {
				$rangeStart = $today0;
				$rangeEndExclusive = $tomorrow0;
			} elseif ($period === '7d' || $period === 'week') {
				$rangeStart = date('Y-m-d 00:00:00', strtotime('-6 day'));
				$rangeEndExclusive = $tomorrow0;
			} elseif ($period === '30d' || $period === 'month') {
				$rangeStart = date('Y-m-d 00:00:00', strtotime('-29 day'));
				$rangeEndExclusive = $tomorrow0;
			} elseif ($period === '90d' || $period === 'quarter' || $period === 'quarterly') {
				$rangeStart = date('Y-m-d 00:00:00', strtotime('-89 day'));
				$rangeEndExclusive = $tomorrow0;
			} elseif ($period === 'ytd') {
				$rangeStart = date('Y-01-01 00:00:00');
				$rangeEndExclusive = $tomorrow0;
			} elseif ($period === '365d' || $period === 'annual' || $period === 'year') {
				$rangeStart = date('Y-m-d 00:00:00', strtotime('-364 day'));
				$rangeEndExclusive = $tomorrow0;
			} else {
				$rangeStart = date('Y-m-d 00:00:00', strtotime('-29 day'));
				$rangeEndExclusive = $tomorrow0;
			}
		}

		$conditions = [];
		$params = [
			'd_start' => $rangeStart,
			'd_end' => $rangeEndExclusive,
		];
		$params['user_id'] = $userId;
		if ($hasBulkParentId) {
			$conditions[] = 'm.bulk_parent_id IS NULL';
		}
		$conditions[] = '(m.deleted = 0 OR m.deleted IS NULL)';
		$conditions[] = '(m.disabled = 0 OR m.disabled IS NULL)';
		$conditions[] = 'm.date_created >= :d_start AND m.date_created < :d_end';

		$canViewAll = backoffice_commission_can_view_all($session);
		if (!$canViewAll) {
			$createdBy = 0;
			$payTo = 0;
		}
		if ($scope === 'created') {
			$conditions[] = 'm.created_by = :user_id';
			$params['user_id'] = $userId;
		} elseif ($scope === 'pay_to') {
			$conditions[] = $assignedExpr . ' = :user_id';
			$params['user_id'] = $userId;
		} elseif ($canViewAll) {
			// For admins/viewers, default scope (mine/all) should show all.
			$conditions[] = '(:user_id = :user_id)';
		} else {
			// default: mine (created-by or assigned-to)
			$conditions[] = '(m.created_by = :user_id OR ' . $assignedExpr . ' = :user_id)';
			$params['user_id'] = $userId;
		}

		if ($status !== '' && $status !== 'all') {
			if ($status === 'approved') {
				$conditions[] = "LOWER(m.transaction_status) IN ('approved','complete','completed')";
			} else {
				$conditions[] = 'LOWER(m.transaction_status) = :status';
				$params['status'] = $status;
			}
		}
		if ($network !== '' && $network !== 'all') {
			$conditions[] = 'm.network = :network';
			$params['network'] = $network;
		}
		if ($ctype !== '' && $ctype !== 'all') {
			$conditions[] = 'm.commission_type = :commission_type';
			$params['commission_type'] = $ctype;
		}
		if ($createdBy > 0) {
			$conditions[] = 'm.created_by = :created_by';
			$params['created_by'] = $createdBy;
		}
		if ($payTo > 0) {
			$conditions[] = $assignedExpr . ' = :pay_to';
			$params['pay_to'] = $payTo;
		}
		if ($find !== '') {
			$conditions[] = '(
				m.id LIKE :find OR
				m.description LIKE :find OR
				m.network LIKE :find OR
				m.commission_type LIKE :find OR
				m.transaction_status LIKE :find
			)';
			$params['find'] = "%{$find}%";
		}
		if (!backoffice_commission_can_access_bank_sims($session)) {
			$conditions[] = backoffice_commission_non_bank_sql_clause('m', $commissionColumns);
		}

		$where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';
		$analyticsConditions = $conditions;
		$analyticsConditions[] = "LOWER(TRIM(COALESCE(m.transaction_status,''))) <> 'rejected'";
		$analyticsWhere = 'WHERE ' . implode(' AND ', $analyticsConditions);

		$sqlKpis = "
			SELECT
				COUNT(*) AS total_rows,
				COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(m.transaction_status,''))) <> 'rejected' THEN amount ELSE 0 END),0) AS total_amount,
				COALESCE(SUM(CASE WHEN LOWER(transaction_status) IN ('approved','complete','completed') THEN amount ELSE 0 END),0) AS approved_amount,
				COALESCE(SUM(CASE WHEN LOWER(transaction_status) = 'pending' THEN amount ELSE 0 END),0) AS pending_amount,
				COALESCE(SUM(CASE WHEN LOWER(transaction_status) = 'rejected' THEN amount ELSE 0 END),0) AS rejected_amount,
				SUM(CASE WHEN LOWER(transaction_status) IN ('approved','complete','completed') THEN 1 ELSE 0 END) AS approved_count,
				SUM(CASE WHEN LOWER(transaction_status) = 'pending' THEN 1 ELSE 0 END) AS pending_count,
				SUM(CASE WHEN LOWER(transaction_status) = 'rejected' THEN 1 ELSE 0 END) AS rejected_count,
				COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(m.transaction_status,''))) <> 'rejected' AND UPPER(m.network) LIKE '%CFA%' THEN amount ELSE 0 END),0) AS total_amount_cfa,
				COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(m.transaction_status,''))) <> 'rejected' AND COALESCE(UPPER(m.network),'') NOT LIKE '%CFA%' THEN amount ELSE 0 END),0) AS total_amount_ghs
			FROM commissions AS m
			{$where}
		";
		$kpiRow = ($dbAjax->query($sqlKpis, $params) ?: [])[0] ?? null;

		$byNetwork = [];

		$sqlByType = "
			SELECT
				COALESCE(m.commission_type,'Unknown') AS label,
				COUNT(*) AS total_rows,
				COALESCE(SUM(m.amount),0) AS total_amount
			FROM commissions AS m
			{$analyticsWhere}
			GROUP BY m.commission_type
			ORDER BY total_amount DESC
			LIMIT 25
		";
		$byType = $dbAjax->query($sqlByType, $params) ?: [];

		$sqlSourceRows = "
			SELECT
				m.network,
				m.commission_type,
				m.amount,
				" . (!empty($commissionColumns['commission_field']) ? "m.commission_field" : "'' AS commission_field") . ",
				" . (!empty($commissionColumns['assignment_snapshot_json']) ? "m.assignment_snapshot_json" : "'' AS assignment_snapshot_json") . "
			FROM commissions AS m
			{$analyticsWhere}
			LIMIT 5000
		";
		$sourceRows = $dbAjax->query($sqlSourceRows, $params) ?: [];
		$networkBuckets = [];
		$addNetworkBucket = static function(string $label, float $amount) use (&$networkBuckets): void {
			$label = backoffice_commission_normalize_network($label);
			$label = $label !== '' ? $label : 'CASHPICKUP';
			if (!isset($networkBuckets[$label])) {
				$networkBuckets[$label] = [
					'label' => $label,
					'total_rows' => 0,
					'total_amount' => 0.0,
				];
			}
			$networkBuckets[$label]['total_rows']++;
			$networkBuckets[$label]['total_amount'] += $amount;
		};
		$sourceBuckets = [];
		$addSourceBucket = static function(string $label, float $amount) use (&$sourceBuckets): void {
			$label = trim($label) !== '' ? trim($label) : 'Unknown Commission Source';
			if (!isset($sourceBuckets[$label])) {
				$sourceBuckets[$label] = [
					'label' => $label,
					'total_rows' => 0,
					'total_amount' => 0.0,
				];
			}
			$sourceBuckets[$label]['total_rows']++;
			$sourceBuckets[$label]['total_amount'] += $amount;
		};
		foreach ($sourceRows as $sourceRow) {
			$rowNetwork = (string)($sourceRow->network ?? '');
			$rowType = (string)($sourceRow->commission_type ?? '');
			$rowField = (string)($sourceRow->commission_field ?? '');
			$rowAmount = (float)($sourceRow->amount ?? 0);
			$rawSnapshot = trim((string)($sourceRow->assignment_snapshot_json ?? ''));
			$decoded = $rawSnapshot !== '' ? json_decode($rawSnapshot, true) : null;
			$networkSplitAdded = false;
			$rowTypeUpper = strtoupper(trim($rowType));
			if (($rowTypeUpper === 'OTHER SIM COMMISSION BULK' || strtoupper(trim($rowNetwork)) === 'MULTI') && is_array($decoded)) {
				$networkItems = (array_key_exists('network', $decoded) || array_key_exists('sim_type', $decoded)) ? [$decoded] : $decoded;
				foreach ($networkItems as $item) {
					if (!is_array($item)) {
						continue;
					}
					$snapshot = [];
					if (!empty($item['assignment']) && is_array($item['assignment'])) {
						$snapshot = $item['assignment'];
					} elseif (array_key_exists('network', $item) || array_key_exists('sim_type', $item)) {
						$snapshot = $item;
					}
					$networkLabel = (string)($snapshot['network'] ?? '');
					if (trim($networkLabel) === '') {
						continue;
					}
					$itemAmount = isset($item['amount']) ? (float)$item['amount'] : $rowAmount;
					if ($itemAmount <= 0 && $rowAmount > 0 && count($networkItems) === 1) {
						$itemAmount = $rowAmount;
					}
					$addNetworkBucket($networkLabel, $itemAmount);
					$networkSplitAdded = true;
				}
			}
			if (!$networkSplitAdded) {
				$addNetworkBucket($rowNetwork, $rowAmount);
			}
			$splitAdded = false;
			if (is_array($decoded)) {
				$items = (array_key_exists('network', $decoded) || array_key_exists('sim_type', $decoded)) ? [$decoded] : $decoded;
				foreach ($items as $item) {
					if (!is_array($item)) {
						continue;
					}
					$snapshot = [];
					if (!empty($item['assignment']) && is_array($item['assignment'])) {
						$snapshot = $item['assignment'];
					} elseif (array_key_exists('network', $item) || array_key_exists('sim_type', $item)) {
						$snapshot = $item;
					}
					if (empty($snapshot)) {
						continue;
					}
					if (!empty($item['commission_field'])) {
						$snapshot['commission_field'] = $item['commission_field'];
					}
					if (!empty($item['commission_label'])) {
						$snapshot['commission_label'] = $item['commission_label'];
					}
					$itemAmount = isset($item['amount']) ? (float)$item['amount'] : $rowAmount;
					if ($itemAmount <= 0 && $rowAmount > 0 && count($items) === 1) {
						$itemAmount = $rowAmount;
					}
					$label = backoffice_commission_source_label_from_parts($rowNetwork, $rowType, $rowField, $snapshot);
					$addSourceBucket($label, $itemAmount);
					$splitAdded = true;
				}
			}
			if (!$splitAdded) {
				$label = backoffice_commission_source_label_from_parts($rowNetwork, $rowType, $rowField, []);
				$addSourceBucket($label, $rowAmount);
			}
		}
		$byNetwork = array_values($networkBuckets);
		usort($byNetwork, static function(array $a, array $b): int {
			$amountCompare = (float)$b['total_amount'] <=> (float)$a['total_amount'];
			if ($amountCompare !== 0) {
				return $amountCompare;
			}
			return strcasecmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));
		});
		$byNetwork = array_slice($byNetwork, 0, 20);
		$bySourceRevenue = array_values($sourceBuckets);
		usort($bySourceRevenue, static function(array $a, array $b): int {
			return strcasecmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));
		});
		$bySourceRevenue = array_slice($bySourceRevenue, 0, 30);

		$sqlTopPayTo = "
			SELECT
				{$assignedExpr} AS user_id,
				CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) AS name,
				u.image AS image,
				COUNT(*) AS total_rows,
				COALESCE(SUM(m.amount),0) AS total_amount
			FROM commissions AS m
			LEFT JOIN users AS u ON {$assignedExpr} = u.id
			{$analyticsWhere}
			GROUP BY {$assignedExpr}, u.first_name, u.last_name, u.image
			ORDER BY total_amount DESC
		";
		$topPayTo = $dbAjax->query($sqlTopPayTo, $params) ?: [];

		$sqlTopCreated = "
			SELECT
				m.created_by AS user_id,
				CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) AS name,
				u.image AS image,
				COUNT(*) AS total_rows,
				COALESCE(SUM(m.amount),0) AS total_amount
			FROM commissions AS m
			LEFT JOIN users AS u ON m.created_by = u.id
			{$analyticsWhere}
			GROUP BY m.created_by, u.first_name, u.last_name, u.image
			ORDER BY total_amount DESC
		";
		$topCreatedBy = $dbAjax->query($sqlTopCreated, $params) ?: [];

		$sqlSeries = "
			SELECT
				DATE(m.date_created) AS day,
				COUNT(*) AS total_rows,
				COALESCE(SUM(m.amount),0) AS total_amount
			FROM commissions AS m
			{$analyticsWhere}
			GROUP BY DATE(m.date_created)
			ORDER BY day ASC
		";
		$series = $dbAjax->query($sqlSeries, $params) ?: [];

		$sqlFilters = "
			SELECT
				COALESCE(m.network,'') AS network,
				COALESCE(m.commission_type,'') AS commission_type
			FROM commissions AS m
			{$where}
			GROUP BY m.network, m.commission_type
		";
		$filterRows = $dbAjax->query($sqlFilters, $params) ?: [];
		$networks = [];
		$types = [];
		foreach ($filterRows as $fr) {
			$nw = trim((string)($fr->network ?? ''));
			$tp = trim((string)($fr->commission_type ?? ''));
			if ($nw !== '' && !in_array($nw, $networks, true)) $networks[] = $nw;
			if ($tp !== '' && !in_array($tp, $types, true)) $types[] = $tp;
		}
		sort($networks);
		sort($types);

		echo json_encode([
			'success' => true,
			'range' => [
				'start' => substr((string)$rangeStart, 0, 10),
				'end' => date('Y-m-d', strtotime((string)$rangeEndExclusive . ' -1 day')),
			],
			'filters' => [
				'networks' => $networks,
				'commission_types' => $types,
			],
			'kpis' => [
				'total_rows' => (int)($kpiRow->total_rows ?? 0),
				'total_amount' => (float)($kpiRow->total_amount ?? 0),
				'total_amount_ghs' => (float)($kpiRow->total_amount_ghs ?? 0),
				'total_amount_cfa' => (float)($kpiRow->total_amount_cfa ?? 0),
				'approved_count' => (int)($kpiRow->approved_count ?? 0),
				'pending_count' => (int)($kpiRow->pending_count ?? 0),
				'rejected_count' => (int)($kpiRow->rejected_count ?? 0),
				'approved_amount' => (float)($kpiRow->approved_amount ?? 0),
				'pending_amount' => (float)($kpiRow->pending_amount ?? 0),
				'rejected_amount' => (float)($kpiRow->rejected_amount ?? 0),
			],
			'by_network' => array_map(function ($r) {
				return [
					'label' => (string)(is_array($r) ? ($r['label'] ?? '') : ($r->label ?? '')),
					'total_rows' => (int)(is_array($r) ? ($r['total_rows'] ?? 0) : ($r->total_rows ?? 0)),
					'total_amount' => (float)(is_array($r) ? ($r['total_amount'] ?? 0) : ($r->total_amount ?? 0)),
				];
			}, $byNetwork),
			'by_commission_type' => array_map(function ($r) {
				return [
					'label' => (string)($r->label ?? ''),
					'total_rows' => (int)($r->total_rows ?? 0),
					'total_amount' => (float)($r->total_amount ?? 0),
				];
			}, $byType),
			'by_source_revenue' => array_map(function ($r) {
				return [
					'label' => (string)($r['label'] ?? ''),
					'total_rows' => (int)($r['total_rows'] ?? 0),
					'total_amount' => (float)($r['total_amount'] ?? 0),
				];
			}, $bySourceRevenue),
			'top_pay_to' => array_map(function ($r) {
				return [
					'user_id' => (int)($r->user_id ?? 0),
					'name' => trim((string)($r->name ?? '')),
					'image' => get_image($r->image ?? ''),
					'total_rows' => (int)($r->total_rows ?? 0),
					'total_amount' => (float)($r->total_amount ?? 0),
				];
			}, $topPayTo),
			'top_created_by' => array_map(function ($r) {
				return [
					'user_id' => (int)($r->user_id ?? 0),
					'name' => trim((string)($r->name ?? '')),
					'image' => get_image($r->image ?? ''),
					'total_rows' => (int)($r->total_rows ?? 0),
					'total_amount' => (float)($r->total_amount ?? 0),
				];
			}, $topCreatedBy),
			'series' => array_map(function ($r) {
				return [
					'day' => (string)($r->day ?? ''),
					'total_rows' => (int)($r->total_rows ?? 0),
					'total_amount' => (float)($r->total_amount ?? 0),
				];
			}, $series),
		]);
	} catch (\Throwable $e) {
		http_response_code(500);
		echo json_encode([
			'success' => false,
			'message' => 'Failed to load commissions dashboard',
			'error' => $e->getMessage(),
		]);
	}
	exit;
});

add_action('controller', function () {
	$vars = get_value();
	$plugin_route = $vars['plugin_route'] ?? 'back_office';

	if (URL(1) !== $plugin_route || URL(2) !== 'commissions_list_v2') {
		return;
	}

	while (ob_get_level() > 0) {
		ob_end_clean();
	}
	header('Content-Type: application/json; charset=utf-8');
	try {
		$session = get_session();
		$userId = (int)($session->id ?? 0);
		if ($userId <= 0) {
			http_response_code(401);
			echo json_encode(['success' => false, 'message' => 'Not authenticated']);
			return;
		}

		$dbAjax = new \Core\Database;
		$commissionColumns = backoffice_table_columns($dbAjax, 'commissions');
		$hasBulkParentId = !empty($commissionColumns['bulk_parent_id']);
		$hasAssigned = !empty($commissionColumns['assigned']);
		$hasRejectReason = !empty($commissionColumns['reject_reason']);
		$assignedExpr = $hasAssigned ? 'COALESCE(m.assigned, m.pay_to)' : 'm.pay_to';
		$page = max(1, (int)($_GET['page'] ?? 1));
		$limit = max(5, min(50, (int)($_GET['limit'] ?? 10)));
		$offset = ($page - 1) * $limit;
		$period = strtolower(trim($_GET['period'] ?? '30d'));
		$start = trim($_GET['start'] ?? '');
		$end = trim($_GET['end'] ?? '');
		$status = strtolower(trim($_GET['status'] ?? ''));
		$scope = strtolower(trim($_GET['scope'] ?? 'all'));
		$network = trim($_GET['network'] ?? '');
		$ctype = trim($_GET['commission_type'] ?? '');
		$createdBy = (int)($_GET['created_by'] ?? 0);
		$payTo = (int)($_GET['pay_to'] ?? 0);
		$find = trim($_GET['find'] ?? '');

		$rangeStart = null;
		$rangeEndExclusive = null;
		if ($start !== '' && $end !== '') {
			$rangeStart = date('Y-m-d 00:00:00', strtotime($start));
			$rangeEndExclusive = date('Y-m-d 00:00:00', strtotime($end . ' +1 day'));
		} else {
			$today0 = date('Y-m-d 00:00:00');
			$tomorrow0 = date('Y-m-d 00:00:00', strtotime('+1 day'));
			if ($period === 'today') {
				$rangeStart = $today0;
				$rangeEndExclusive = $tomorrow0;
			} elseif ($period === '7d' || $period === 'week') {
				$rangeStart = date('Y-m-d 00:00:00', strtotime('-6 day'));
				$rangeEndExclusive = $tomorrow0;
			} elseif ($period === '30d' || $period === 'month') {
				$rangeStart = date('Y-m-d 00:00:00', strtotime('-29 day'));
				$rangeEndExclusive = $tomorrow0;
			} elseif ($period === '90d' || $period === 'quarter' || $period === 'quarterly') {
				$rangeStart = date('Y-m-d 00:00:00', strtotime('-89 day'));
				$rangeEndExclusive = $tomorrow0;
			} elseif ($period === 'ytd') {
				$rangeStart = date('Y-01-01 00:00:00');
				$rangeEndExclusive = $tomorrow0;
			} elseif ($period === '365d' || $period === 'annual' || $period === 'year') {
				$rangeStart = date('Y-m-d 00:00:00', strtotime('-364 day'));
				$rangeEndExclusive = $tomorrow0;
			} else {
				$rangeStart = date('Y-m-d 00:00:00', strtotime('-29 day'));
				$rangeEndExclusive = $tomorrow0;
			}
		}

		$conditions = [];
		$params = [
			'd_start' => $rangeStart,
			'd_end' => $rangeEndExclusive,
		];
		if ($hasBulkParentId) {
			$conditions[] = 'm.bulk_parent_id IS NULL';
		}
		$conditions[] = '(m.deleted = 0 OR m.deleted IS NULL)';
		$conditions[] = '(m.disabled = 0 OR m.disabled IS NULL)';
		$conditions[] = 'm.date_created >= :d_start AND m.date_created < :d_end';

		$canViewAll = backoffice_commission_can_view_all($session);
		if (!$canViewAll) {
			$createdBy = 0;
			$payTo = 0;
		}
		if ($scope === 'created') {
			$conditions[] = 'm.created_by = :user_id';
			$params['user_id'] = $userId;
		} elseif ($scope === 'pay_to') {
			$conditions[] = $assignedExpr . ' = :user_id';
			$params['user_id'] = $userId;
		} elseif ($canViewAll) {
			// For admins/viewers, default scope (mine/all) should show all.
			$params['user_id'] = $userId;
			$conditions[] = '(:user_id = :user_id)';
		} else {
			// default: mine (created-by or assigned-to)
			$conditions[] = '(m.created_by = :user_id OR ' . $assignedExpr . ' = :user_id)';
			$params['user_id'] = $userId;
		}
		if ($status !== '' && $status !== 'all') {
			if ($status === 'approved') {
				$conditions[] = "LOWER(m.transaction_status) IN ('approved','complete','completed')";
			} else {
				$conditions[] = 'LOWER(m.transaction_status) = :status';
				$params['status'] = $status;
			}
		}
		if ($network !== '' && $network !== 'all') {
			$conditions[] = 'm.network = :network';
			$params['network'] = $network;
		}
		if ($ctype !== '' && $ctype !== 'all') {
			$conditions[] = 'm.commission_type = :commission_type';
			$params['commission_type'] = $ctype;
		}
		if ($createdBy > 0) {
			$conditions[] = 'm.created_by = :created_by';
			$params['created_by'] = $createdBy;
		}
		if ($payTo > 0) {
			$conditions[] = $assignedExpr . ' = :pay_to';
			$params['pay_to'] = $payTo;
		}
		if ($find !== '') {
			$conditions[] = '(
				m.id LIKE :find OR
				m.description LIKE :find OR
				m.network LIKE :find OR
				m.commission_type LIKE :find OR
				m.transaction_status LIKE :find OR
				u.first_name LIKE :find OR
				u.last_name LIKE :find OR
				payto.first_name LIKE :find OR
				payto.last_name LIKE :find
			)';
			$params['find'] = "%{$find}%";
		}
		if (!backoffice_commission_can_access_bank_sims($session)) {
			$conditions[] = backoffice_commission_non_bank_sql_clause('m', $commissionColumns);
		}

		$where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';
		$limitSql = " LIMIT {$limit} OFFSET {$offset}";
		$assignedSelect = $hasAssigned ? 'm.assigned' : 'NULL AS assigned';
		$rejectReasonSelect = $hasRejectReason ? 'm.reject_reason' : "'' AS reject_reason";

		$sqlRows = "
			SELECT
				m.id,
				m.date_created,
				m.description,
				m.amount,
				m.transaction_status,
				m.network,
				m.commission_type,
				m.created_by,
				m.pay_to,
				{$assignedSelect},
				{$rejectReasonSelect},
				u.first_name AS user_first_name,
				u.last_name AS user_last_name,
				u.image AS user_image,
				payto.first_name AS payto_first_name,
				payto.last_name AS payto_last_name,
				payto.image AS payto_image,
				assigned_user.first_name AS assigned_first_name,
				assigned_user.last_name AS assigned_last_name,
				assigned_user.image AS assigned_image
			FROM commissions AS m
			LEFT JOIN users AS u ON m.created_by = u.id
			LEFT JOIN users AS payto ON m.pay_to = payto.id
			LEFT JOIN users AS assigned_user ON {$assignedExpr} = assigned_user.id
			{$where}
			ORDER BY m.id DESC
			{$limitSql}
		";
		$rows = $dbAjax->query($sqlRows, $params) ?: [];

		$sqlCount = "
			SELECT COUNT(*) AS total_rows,
				COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(m.transaction_status,''))) <> 'rejected' THEN m.amount ELSE 0 END),0) AS total_amount
			FROM commissions AS m
			LEFT JOIN users AS u ON m.created_by = u.id
			LEFT JOIN users AS payto ON m.pay_to = payto.id
			LEFT JOIN users AS assigned_user ON {$assignedExpr} = assigned_user.id
			{$where}
		";
		$countRow = ($dbAjax->query($sqlCount, $params) ?: [])[0] ?? null;
		$totalRows = $countRow ? (int)($countRow->total_rows ?? 0) : 0;
		$totalPages = $totalRows > 0 ? (int)ceil($totalRows / $limit) : 1;

		$rowsPayload = array_map(function ($row) use ($userId) {
			$createdName = trim(($row->user_first_name ?? '') . ' ' . ($row->user_last_name ?? ''));
			$paytoName = trim(($row->payto_first_name ?? '') . ' ' . ($row->payto_last_name ?? ''));
			$assignedName = trim(($row->assigned_first_name ?? '') . ' ' . ($row->assigned_last_name ?? ''));
			$statusLower = strtolower($row->transaction_status ?? '');
			$normalizedStatus = in_array($statusLower, ['complete', 'completed'], true) ? 'approved' : ($row->transaction_status ?? '');
			$createdById = (int)($row->created_by ?? 0);
			$assignedId = backoffice_commission_assignee_id($row);
			return [
				'id' => (int)($row->id ?? 0),
				'created_at' => (string)($row->date_created ?? ''),
				'description' => (string)($row->description ?? ''),
				'amount' => (float)($row->amount ?? 0),
				'status' => (string)$normalizedStatus,
				'network' => (string)($row->network ?? ''),
				'commission_type' => (string)($row->commission_type ?? ''),
				'reject_reason' => (string)($row->reject_reason ?? ''),
				'created_by' => [
					'id' => $createdById,
					'name' => $createdName,
					'image' => get_image($row->user_image ?? ''),
				],
				'pay_to' => [
					'id' => (int)($row->pay_to ?? 0),
					'name' => $paytoName,
					'image' => get_image($row->payto_image ?? ''),
				],
				'assigned_to' => [
					'id' => $assignedId,
					'name' => $assignedName !== '' ? $assignedName : $paytoName,
					'image' => get_image($row->assigned_image ?? ($row->payto_image ?? '')),
				],
				'can_act' => in_array($statusLower, ['pending', 'draft'], true) && $assignedId === (int)$userId,
				'can_delete' => backoffice_commission_can_delete_pending($row, get_session()),
			];
		}, $rows);

		echo json_encode([
			'success' => true,
			'rows' => $rowsPayload,
			'pagination' => [
				'page' => $page,
				'limit' => $limit,
				'total_rows' => $totalRows,
				'total_pages' => $totalPages,
			],
		]);
	} catch (\Throwable $e) {
		http_response_code(500);
		echo json_encode([
			'success' => false,
			'message' => 'Failed to load commissions list',
			'error' => $e->getMessage(),
		]);
	}
	exit;
});

add_action('controller', function () {
	$vars = get_value();
	$plugin_route = $vars['plugin_route'] ?? 'back_office';

	if (URL(1) !== $plugin_route || URL(2) !== 'payment_view_modal') {
		return;
	}

	header('Content-Type: application/json; charset=utf-8');
	$respond = static function (array $payload, ?int $statusCode = null): void {
		if ($statusCode !== null) {
			http_response_code($statusCode);
		}
		while (ob_get_level() > 0) {
			ob_end_clean();
		}
		echo json_encode($payload);
		exit;
	};
	try {
		$session = get_session();
		$userId = (int)($session->id ?? 0);
		if ($userId <= 0) {
			$respond(['success' => false, 'message' => 'Not authenticated'], 401);
		}

		$id = (int)($_GET['id'] ?? 0);
		if ($id <= 0) {
			$respond(['success' => false, 'message' => 'Invalid payment id.'], 400);
		}

		$payment = new \BackOffices\Payment;
		$sql = "
			SELECT
				m.id,
				m.amount,
				m.network,
				m.payment_type,
				m.description,
				m.denos_json,
				m.verified_denos_json,
				m.payment_breakdown_json,
				m.created_by,
				m.pay_to,
				u_from.first_name AS from_first_name,
				u_from.last_name AS from_last_name,
				u_to.first_name AS to_first_name,
				u_to.last_name AS to_last_name
			FROM payments_backoffice AS m
			LEFT JOIN users AS u_from ON m.created_by = u_from.id
			LEFT JOIN users AS u_to ON m.pay_to = u_to.id
			WHERE m.id = :id
			LIMIT 1
		";
		$rows = $payment->query($sql, ['id' => $id]);
		$row = !empty($rows) ? $rows[0] : null;
		if (!$row) {
			$respond(['success' => false, 'message' => 'Payment not found.'], 404);
		}

		$createdById = (int)($row->created_by ?? 0);
		$payToId = (int)($row->pay_to ?? 0);
		if ($createdById !== $userId && $payToId !== $userId) {
			$respond(['success' => false, 'message' => 'You do not have access to this payment.'], 403);
		}

		$amountValue = (float)($row->amount ?? 0);
		$network = strtoupper((string)($row->network ?? ''));
		$isCashNetwork = ($network === 'CASH');

		$currency = strtoupper(trim((string)($row->payment_type ?? '')));
		if (!in_array($currency, ['GHS', 'CFA'], true)) {
			$currency = 'GHS';
			$decoded = json_decode((string)($row->denos_json ?? ''), true);
			if (is_array($decoded)) {
				foreach ($decoded as $d) {
					if (strtoupper((string)($d['currency'] ?? '')) === 'CFA') {
						$currency = 'CFA';
						break;
					}
				}
			}
		}

		$denosPayload = [];
		$verifiedDenosPayload = [];
		if ($isCashNetwork) {
			$denosPayload = normalizeDenominations($row->denos_json ?? '[]', $amountValue, $currency);
			$verifiedDenosPayload = normalizeDenominations((string)($row->verified_denos_json ?? '[]'), 0, $currency);
		}

		$fromName = trim((string)($row->from_first_name ?? '') . ' ' . (string)($row->from_last_name ?? ''));
		$toName = trim((string)($row->to_first_name ?? '') . ' ' . (string)($row->to_last_name ?? ''));
		$assignedSnapshot = function_exists('backoffice_assignment_snapshot_lines')
			? backoffice_assignment_snapshot_lines((int)($row->pay_to ?? 0), $row->network ?? '', $row->payment_type ?? '')
			: [];
		$bulkBreakdown = function_exists('backoffice_payment_row_breakdown') ? backoffice_payment_row_breakdown($row) : ['items' => [], 'total' => 0.0];

		$respond([
			'success' => true,
			'id' => (int)($row->id ?? 0),
			'amount' => $amountValue,
			'from' => $fromName,
			'to' => $toName,
			'network' => (string)($row->network ?? ''),
			'payment_type' => (string)($row->payment_type ?? ''),
			'description' => (string)($row->description ?? ''),
			'is_cash' => $isCashNetwork,
			'currency' => $currency,
			'denos' => $denosPayload,
			'expected_denos' => $denosPayload,
			'verified_denos' => $verifiedDenosPayload,
			'assigned_snapshot' => $assignedSnapshot,
			'bulk_items' => $bulkBreakdown['items'] ?? [],
		]);
	} catch (\Throwable $e) {
		$respond([
			'success' => false,
			'message' => 'Failed to load payment preview',
			'error' => $e->getMessage(),
		], 500);
	}
});

add_action('controller', function () {
	$vars = get_value();
	$plugin_route = $vars['plugin_route'] ?? 'back_office';

	if (URL(1) !== $plugin_route || URL(2) !== 'payment_action') {
		return;
	}

	header('Content-Type: application/json; charset=utf-8');
	$respond = static function (array $payload, ?int $statusCode = null): void {
		if ($statusCode !== null) {
			http_response_code($statusCode);
		}
		while (ob_get_level() > 0) {
			ob_end_clean();
		}
		echo json_encode($payload);
		exit;
	};
	try {
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			$respond(['success' => false, 'message' => 'Invalid request method.'], 405);
		}

		$session = get_session();
		$userId = (int)($session->id ?? 0);
		if ($userId <= 0) {
			$respond(['success' => false, 'message' => 'Not authenticated'], 401);
		}
		if (!user_can('edit_payment')) {
			$respond(['success' => false, 'message' => 'You do not have permission to update payments.'], 403);
		}

		$id = (int)($_POST['id'] ?? 0);
		$action = strtolower(trim($_POST['action'] ?? ''));
		if ($id <= 0 || !in_array($action, ['approve', 'reject'], true)) {
			$respond(['success' => false, 'message' => 'Invalid payment payload.'], 400);
		}

		$payment = new \BackOffices\Payment;
		$row = $payment->first(['id' => $id]);
		if (!$row) {
			$respond(['success' => false, 'message' => 'Payment not found.'], 404);
		}

		$createdById = (int)($row->created_by ?? 0);
		$payToId = (int)($row->pay_to ?? 0);
		if ($createdById !== $userId && $payToId !== $userId) {
			$respond(['success' => false, 'message' => 'You do not have access to this payment.'], 403);
		}
		// Separation of duties: only the pay-to (assigned) user can approve/reject.
		if ($payToId !== $userId) {
			$respond(['success' => false, 'message' => 'Only the assigned user can approve or reject this payment.'], 403);
		}

		$statusLower = strtolower($row->transaction_status ?? '');
		if ($statusLower !== 'pending' && $statusLower !== 'draft') {
			$respond(['success' => false, 'message' => 'Only pending or draft payments can be updated inline.']);
		}

		$update = [
			'date_updated' => date('Y-m-d H:i:s'),
			'updated_by' => $userId,
		];
		$whatsappForCreator = static function (string $status, array $details = []) use ($row, $payToId, $userId): array {
			return backoffice_whatsapp_notify_creator_on_assignee_update([
				'type' => 'Backoffice Payment',
				'id' => (int)$row->id,
				'status' => $status,
				'creator_id' => (int)($row->created_by ?? 0),
				'assigned_id' => $payToId,
				'details' => $details,
				'view_url' => ROOT . '/admin/back_office/view_payment/' . (int)$row->id,
				'flash' => false,
			], $userId);
		};

		if ($action === 'reject') {
			$reason = trim($_POST['reason'] ?? '');
			if ($reason === '') {
				$respond(['success' => false, 'message' => 'Rejection reason is required.']);
			}
			error_log("Backoffice payment {$row->id} rejected by user {$userId}: {$reason}");
			$update['transaction_status'] = 'Rejected';
			$update['reject_reason'] = $reason;
			$payment->update($row->id, $update);
			if (!empty($payment->has_error) || !empty($payment->error)) {
				error_log('Backoffice payment update failed (reject) for payment ' . $row->id . ': ' . ($payment->error ?? 'unknown'));
				$respond(['success' => false, 'message' => 'Failed to update payment', 'error' => ($payment->error ?? '')]);
			}
			$respond(['success' => true, 'message' => 'Payment rejected.', 'new_status' => 'Rejected', 'whatsapp' => $whatsappForCreator('Rejected', ['Reason' => $reason])]);
		}

		$amountValue = (float)($row->amount ?? 0);
		$network = strtoupper((string)($row->network ?? ''));
		$isCashNetwork = ($network === 'CASH');
		$receiverId = (int)($row->pay_to ?? 0);

		if (!$isCashNetwork) {
			$uploadReq = new \Core\Request;
			$uploadReq->upload_folder = 'uploads/backoffice/payments';
			$uploadReq->upload_max_size = 1;
			$bulkBreakdown = backoffice_payment_row_breakdown($row);
			$bulkItems = $bulkBreakdown['items'] ?? [];
			$uploadedReceipt = '';
			if (!empty($bulkItems)) {
				$receiptMessages = [];
				foreach ($bulkItems as $idx => $bulkItem) {
					$channel = backoffice_payment_resolved_channel_key((string)($bulkItem['channel'] ?? ''));
					if ($channel === '') {
						continue;
					}
					$field = 'bulk_receipt_' . preg_replace('/[^A-Z0-9]+/', '_', strtoupper($channel));
					if (empty($_FILES[$field]['name'])) {
						$receiptMessages[] = "{$channel}: receipt is required";
						continue;
					}
					if (!empty($_FILES[$field]['size']) && (int)$_FILES[$field]['size'] > (1 * 1024 * 1024)) {
						$receiptMessages[] = "{$channel}: receipt must not exceed 1MB";
						continue;
					}
					$uploaded = $uploadReq->upload_files($field);
					if (empty($uploaded)) {
						$receiptMessages[] = "{$channel}: failed to upload receipt";
						continue;
					}
					$bulkItems[$idx]['receipt_photo'] = $uploaded;
					$bulkItems[$idx]['receipt_uploaded_at'] = date('Y-m-d H:i:s');
					if ($uploadedReceipt === '') {
						$uploadedReceipt = $uploaded;
					}
				}
				if (!empty($receiptMessages)) {
					$respond(['success' => false, 'message' => "Upload receipts for every bulk channel:\n" . implode("\n", $receiptMessages)]);
				}
			} else {
				$receiptField = 'approval_receipt_photo';
				if (empty($_FILES[$receiptField]['name'])) {
					$respond(['success' => false, 'message' => 'Upload the payment receipt before approving this payment.']);
				}
				if (!empty($_FILES[$receiptField]['size']) && (int)$_FILES[$receiptField]['size'] > (1 * 1024 * 1024)) {
					$respond(['success' => false, 'message' => 'Payment receipt must not exceed 1MB.']);
				}
				$uploadedReceipt = $uploadReq->upload_files($receiptField);
				if (empty($uploadedReceipt)) {
					$respond(['success' => false, 'message' => 'Failed to upload the payment receipt.']);
				}
			}
			if (!empty($bulkItems)) {
				foreach ($bulkItems as $bulkItem) {
					$ecashChannelKey = backoffice_payment_resolved_channel_key((string)($bulkItem['channel'] ?? ''));
					$itemAmount = (float)($bulkItem['amount'] ?? 0);
					if ($ecashChannelKey === '' || $itemAmount <= 0) {
						continue;
					}
					if (backoffice_payment_is_telecel_type($ecashChannelKey) && !backoffice_payment_amount_is_whole_number($itemAmount)) {
						$respond(['success' => false, 'message' => 'Telecel payments must use whole-number amounts only.']);
					}
					$ecashCheck = \BackOfficeCashdrawer\checkEcashChannelSufficiency($createdById, $ecashChannelKey, $itemAmount);
					if (empty($ecashCheck['ok'])) {
						$required = number_format((float)($ecashCheck['required'] ?? 0), 2);
						$available = number_format((float)($ecashCheck['available'] ?? 0), 2);
						$respond(['success' => false, 'message' => "Insufficient E-Cash balance on {$ecashChannelKey}. Required: {$required}, available: {$available}." ]);
					}
				}
			} elseif ($amountValue > 0) {
				$providerRaw = strtoupper((string)($row->payment_type ?? ''));
				if ($providerRaw === '') {
					$respond(['success' => false, 'message' => 'Payment type is required to approve this transaction.']);
				}
				if (backoffice_payment_is_telecel_type($providerRaw) && !backoffice_payment_amount_is_whole_number($amountValue)) {
					$respond(['success' => false, 'message' => 'Telecel payments must use whole-number amounts only.']);
				}
				$ecashChannelKey = backoffice_payment_resolved_channel_key($providerRaw);
				$ecashCheck = \BackOfficeCashdrawer\checkEcashChannelSufficiency($createdById, $ecashChannelKey, $amountValue);
				if (empty($ecashCheck['ok'])) {
					$required = number_format((float)($ecashCheck['required'] ?? 0), 2);
					$available = number_format((float)($ecashCheck['available'] ?? 0), 2);
					$respond(['success' => false, 'message' => "Insufficient E-Cash balance on {$ecashChannelKey}. Required: {$required}, available: {$available}." ]);
				}
			}
			$update['transaction_status'] = 'Approved';
			$update['approval_receipt_photo'] = $uploadedReceipt;
			$update['approval_receipt_uploaded_at'] = date('Y-m-d H:i:s');
			if (!empty($bulkItems)) {
				$update['payment_breakdown_json'] = json_encode($bulkItems);
			}
			$update['approved_by'] = $userId;
			$update['approved_at'] = date('Y-m-d H:i:s');
			$payment->update($row->id, $update);
			if (!empty($payment->has_error) || !empty($payment->error)) {
				error_log('Backoffice payment update failed (approve ecash) for payment ' . $row->id . ': ' . ($payment->error ?? 'unknown'));
				$respond(['success' => false, 'message' => 'Failed to update payment', 'error' => ($payment->error ?? '')]);
			}

			try {
				$dbBal = new \Core\Database;
				if ($dbBal->table_exists(['drawer_balances'])) {
					$ecashAdjustments = [];
					if (!empty($bulkItems)) {
						foreach ($bulkItems as $bulkItem) {
							$channelKey = backoffice_payment_resolved_channel_key((string)($bulkItem['channel'] ?? ''));
							$itemAmount = (float)($bulkItem['amount'] ?? 0);
							if ($channelKey !== '' && $itemAmount > 0) {
								$ecashAdjustments[] = ['channel' => $channelKey, 'amount' => $itemAmount];
							}
						}
					} else {
						$providerRaw = strtoupper((string)($row->payment_type ?? ''));
						if ($providerRaw !== '') {
							$ecashAdjustments[] = ['channel' => backoffice_payment_resolved_channel_key($providerRaw), 'amount' => $amountValue];
						}
					}
					if (empty($ecashAdjustments)) {
						$respond(['success' => false, 'message' => 'Payment type is required to approve this transaction.']);
					}

					$transId = (int)$row->id;
					$transStatus = 'Approved';
					$adjustEcash = function (int $actorUserId, string $direction, string $ecashChannelKey, float $lineAmount) use ($dbBal, $transId, $transStatus) {
						$drawerContext = \BackOfficeCashdrawer\getActiveDrawer($actorUserId);
						if (empty($drawerContext)) {
							return;
						}
						$drawerId = (int)($drawerContext['drawer_id'] ?? 0);
						$drawerUserId = (int)($drawerContext['drawer_user_id'] ?? $actorUserId);
						if ($drawerId <= 0) {
							return;
						}
						$ecashChannelKey = \BackOfficeCashdrawer\resolveExistingEcashChannelKey($drawerId, $drawerUserId, $ecashChannelKey);
						$rowsEcash = $dbBal->query(
							"SELECT * FROM drawer_balances WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'ecash' AND (channel_key = :channel_key OR channel_key = :ecash_channel_key) LIMIT 1",
							[
								'drawer_id' => $drawerId,
								'user_id' => $drawerUserId,
								'channel_key' => $ecashChannelKey,
								'ecash_channel_key' => 'ECASH_' . $ecashChannelKey,
							]
						);
						$now = date('Y-m-d H:i:s');
						$creditDelta = (strtolower($direction) === 'in') ? $lineAmount : 0.0;
						$debitDelta  = (strtolower($direction) === 'out') ? $lineAmount : 0.0;
						if (empty($rowsEcash)) {
							$opening = 0.0;
							$closing = $opening + $creditDelta - $debitDelta;
							$dbBal->query(
								"INSERT INTO drawer_balances (drawer_id, user_id, channel_type, channel_key, opening_balance, credit, debit, closing_balance, date_created, trans_id, trans_status) VALUES (:drawer_id, :user_id, 'ecash', :channel_key, :opening, :credit, :debit, :closing, :date_created, :trans_id, :trans_status)",
								[
									'drawer_id' => $drawerId,
									'user_id' => $drawerUserId,
									'channel_key' => $ecashChannelKey,
									'opening' => $opening,
									'credit' => $creditDelta,
									'debit' => $debitDelta,
									'closing' => $closing,
									'date_created' => $now,
									'trans_id' => $transId,
									'trans_status' => $transStatus,
								]
							);
						} else {
							$rowEcash = $rowsEcash[0];
							$opening = (float)($rowEcash->opening_balance ?? 0);
							$credit  = (float)($rowEcash->credit ?? 0) + $creditDelta;
							$debit   = (float)($rowEcash->debit ?? 0) + $debitDelta;
							$closing = $opening + $credit - $debit;
							$dbBal->query(
								"UPDATE drawer_balances SET channel_key = :channel_key, credit = :credit, debit = :debit, closing_balance = :closing, trans_id = :trans_id, trans_status = :trans_status WHERE id = :id",
								[
									'channel_key' => $ecashChannelKey,
									'credit' => $credit,
									'debit' => $debit,
									'closing' => $closing,
									'trans_id' => $transId,
									'trans_status' => $transStatus,
									'id' => $rowEcash->id,
								]
							);
						}
					};

					foreach ($ecashAdjustments as $adj) {
						$adjustEcash($createdById, 'out', (string)$adj['channel'], (float)$adj['amount']);
						if ($receiverId > 0) {
							$receiverDrawer = \BackOfficeCashdrawer\getActiveDrawer($receiverId);
							if (!empty($receiverDrawer)) {
								$adjustEcash($receiverId, 'in', (string)$adj['channel'], (float)$adj['amount']);
							}
						}
					}
				}
			} catch (\Throwable $e2) {
				error_log('Backoffice payment ecash sync failed for payment ' . $row->id . ': ' . $e2->getMessage());
			}

			$respond(['success' => true, 'message' => 'Payment approved.', 'new_status' => 'Approved', 'whatsapp' => $whatsappForCreator('Approved', [
				'Network' => $row->network ?? '',
				'Payment type' => $row->payment_type ?? '',
				'Amount' => 'GHS ' . number_format($amountValue, 2),
			])]);
		}

		$payerDrawer = \BackOfficeCashdrawer\getActiveDrawer($createdById);
		if (empty($payerDrawer)) {
			$respond(['success' => false, 'message' => 'You must open a cashdrawer before approval.']);
		}
		$drawerUserId = (int)($payerDrawer['drawer_user_id'] ?? $createdById);

		$cashCurrency = strtoupper(trim((string)($row->payment_type ?? '')));
		if (!in_array($cashCurrency, ['GHS', 'CFA'], true)) {
			$cashCurrency = 'GHS';
		}

		$expectedDenos = normalizeDenominations((string)($row->denos_json ?? '[]'), 0, $cashCurrency);
		if (empty($expectedDenos)) {
			$respond(['success' => false, 'message' => 'The creator cash denominations are missing. Ask the payment creator to re-submit the payment before approval.']);
		}
		$expectedTotal = calculateTotal($expectedDenos);
		if ($expectedTotal <= 0 || abs($expectedTotal - $amountValue) > 0.01) {
			$respond(['success' => false, 'message' => 'The creator cash denominations do not match the original payment amount. Ask the payment creator to review the payment before approval.']);
		}

		$rawDenos = $_POST['denos'] ?? '[]';
		$denosPayload = normalizeDenominations($rawDenos, 0, $cashCurrency);
		$denosTotal = calculateTotal($denosPayload);
		if (empty($denosPayload)) {
			$respond(['success' => false, 'message' => 'Enter the cash denominations received before approving this payment.']);
		}
		if ($denosTotal > 0 && abs($denosTotal - $amountValue) > 0.01) {
			$respond(['success' => false, 'message' => 'Entered denomination total must match the original payment amount before approval.']);
		}
		if (!backoffice_denominations_match($expectedDenos, $denosPayload, $cashCurrency)) {
			$respond(['success' => false, 'message' => 'Denomination mismatch: the entered cash denominations do not match the creator submitted denominations. Recount and try again.']);
		}
		$balanceCheck = \BackOfficeCashdrawer\checkCashDenominationSufficiencyCurrency($drawerUserId, $denosPayload, $amountValue, $cashCurrency);
		if (empty($balanceCheck['ok'])) {
			$cur = strtoupper((string)($balanceCheck['currency'] ?? $cashCurrency));
			$required = number_format((float)($balanceCheck['required'] ?? 0), ($cur === 'CFA') ? 0 : 2);
			$available = number_format((float)($balanceCheck['available'] ?? 0), ($cur === 'CFA') ? 0 : 2);
			$respond(['success' => false, 'message' => "Insufficient {$cur} cashdrawer balance. Required: {$cur} {$required}, available: {$cur} {$available}." ]);
		}

		$countsCheck = \BackOfficeCashdrawer\checkCashDenominationCountSufficiencyCurrency($drawerUserId, $denosPayload, $amountValue, $cashCurrency);
		if (empty($countsCheck['ok'])) {
			$cur = strtoupper((string)($countsCheck['currency'] ?? $cashCurrency));
			$shortages = $countsCheck['shortages'] ?? [];
			$msg = "Insufficient {$cur} denominations in cashdrawer.";
			if (!empty($shortages) && is_array($shortages)) {
				$parts = [];
				foreach ($shortages as $s) {
					$den = (string)($s['denomination'] ?? '');
					$need = (int)($s['required_count'] ?? 0);
					$have = (int)($s['available_count'] ?? 0);
					if ($den === '' || $need <= 0) continue;
					$parts[] = "{$den} x{$need} (available {$have})";
				}
			if (!empty($parts)) {
				$msg .= ' Shortages: ' . implode(', ', $parts) . '.';
			}
		}
			$respond(['success' => false, 'message' => $msg]);
		}

		$update['transaction_status'] = 'Approved';
		$update['verified_denos_json'] = json_encode($denosPayload, JSON_UNESCAPED_SLASHES);
		$update['cashdrawer_id'] = $payerDrawer['drawer_id'];
		$update['drawer_user_id'] = $payerDrawer['drawer_user_id'];
		$update['approved_by'] = $userId;
		$update['approved_at'] = date('Y-m-d H:i:s');
		$payment->update($row->id, $update);
		if (!empty($payment->has_error) || !empty($payment->error)) {
			error_log('Backoffice payment update failed (approve cash) for payment ' . $row->id . ': ' . ($payment->error ?? 'unknown'));
			$respond(['success' => false, 'message' => 'Failed to update payment', 'error' => ($payment->error ?? '')]);
		}

		try {
			\BackOfficeCashdrawer\syncCashMovementCurrency($createdById, $denosPayload, $amountValue, 'Approved', $row->id, 'out', $cashCurrency);
			if ($receiverId > 0) {
				$receiverDrawer = \BackOfficeCashdrawer\getActiveDrawer($receiverId);
				if (!empty($receiverDrawer)) {
					\BackOfficeCashdrawer\syncCashMovementCurrency($receiverId, $denosPayload, $amountValue, 'Approved', $row->id, 'in', $cashCurrency);
				}
			}
		} catch (\Throwable $e2) {
			error_log('Backoffice payment cash sync failed for payment ' . $row->id . ': ' . $e2->getMessage());
		}

		$respond(['success' => true, 'message' => 'Payment approved.', 'new_status' => 'Approved', 'whatsapp' => $whatsappForCreator('Approved', [
			'Network' => $row->network ?? '',
			'Payment type' => $row->payment_type ?? '',
			'Amount' => $cashCurrency . ' ' . number_format($amountValue, $cashCurrency === 'CFA' ? 0 : 2),
		])]);
	} catch (\Throwable $e) {
		$respond([
			'success' => false,
			'message' => 'Failed to update payment',
			'error' => $e->getMessage(),
		], 500);
	}
});


/** set mywallet permissions for this plugin **/
add_filter('permissions',function($permissions){

	$permissions[] = 'view_backoffice';

	return $permissions;
});


/** Add to admin links **/
add_filter('basic-admin_before_admin_links', function ($links) {
    $vars = get_value();
	$session = get_session();
	$user_id = (int)($session->id ?? 0);
	$db = new \Core\Database;

	$total_pending = 0;
	try{
		$params = [];
		$scopeWherePayments = '';
		$scopeWhereRequests = '';
		$scopeWhereCommissions = '';
		$scopeWhereBankTransfers = '';
		$bankCommissionVisibilitySql = backoffice_commission_can_access_bank_sims($session)
			? ''
			: ' AND ' . backoffice_commission_non_bank_sql_clause('commissions');
		if($user_id > 0 && !user_can('is_admin')){
			$scopeWherePayments = ' AND (created_by = :uid OR pay_to = :uid)';
			$scopeWhereRequests = ' AND (created_by = :uid OR assigned = :uid)';
			$scopeWhereCommissions = ' AND (created_by = :uid OR COALESCE(assigned, pay_to) = :uid)' . $bankCommissionVisibilitySql;
			$agentServicesCanSeeAll = user_can('is_admin') || (function_exists('backoffice_user_can_bank_transfer_third_party') && backoffice_user_can_bank_transfer_third_party($session));
			if($agentServicesCanSeeAll) {
				$scopeWhereAgentServices = '';
			} else {
				$scopeWhereAgentServices = ' AND (creator_id = :uid OR assigned_to = :uid OR processor_id = :uid OR approver_id = :uid)';
			}
			$scopeWhereBankTransfers = backoffice_user_can_bank_transfer_third_party($session) ? '' : ' AND (created_by = :uid OR withdrawing_user_id = :uid OR second_party_user_id = :uid OR third_party_user_id = :uid)';
			$params['uid'] = $user_id;
		}else{
			$scopeWhereCommissions = $bankCommissionVisibilitySql;
			$scopeWhereAgentServices = '';
		}

		$rowsPay = $db->query(
			"SELECT COUNT(id) AS c
			 FROM payments_backoffice
			 WHERE (deleted IS NULL OR deleted = 0)
			 AND (disabled IS NULL OR disabled = 0)
			 AND LOWER(COALESCE(transaction_status,'')) = 'pending'{$scopeWherePayments}",
			$params
		);
		$rowsReq = $db->query(
			"SELECT COUNT(id) AS c
			 FROM requests
			 WHERE (deleted IS NULL OR deleted = 0)
			 AND (disabled IS NULL OR disabled = 0)
			 AND LOWER(COALESCE(transaction_status,'')) = 'pending'{$scopeWhereRequests}",
			$params
		);
		$rowsCom = $db->query(
			"SELECT COUNT(id) AS c
			 FROM commissions
			 WHERE (deleted IS NULL OR deleted = 0)
			 AND (disabled IS NULL OR disabled = 0)
			 AND bulk_parent_id IS NULL
			 AND LOWER(COALESCE(transaction_status,'')) = 'pending'{$scopeWhereCommissions}",
			$params
		);
		$rowsBankTransfers = $db->query(
			"SELECT COUNT(id) AS c
			 FROM bank_transfers_backoffice
			 WHERE (deleted IS NULL OR deleted = 0)
			 AND LOWER(COALESCE(transaction_status,'')) NOT IN ('approved','rejected'){$scopeWhereBankTransfers}",
			$params
		);

		$rowsAgentServices = $db->query(
			"SELECT COUNT(id) AS c
			 FROM agent_services
			 WHERE (deleted IS NULL OR deleted = 0)
			 AND COALESCE(stage, 1) < 4
			 AND LOWER(COALESCE(status,'')) NOT IN ('completed', 'rejected'){$scopeWhereAgentServices}",
			$params
		);

		$total_pending = (int)($rowsPay[0]->c ?? 0) + (int)($rowsReq[0]->c ?? 0) + (int)($rowsCom[0]->c ?? 0) + (int)($rowsBankTransfers[0]->c ?? 0) + (int)($rowsAgentServices[0]->c ?? 0);
	}catch(\Throwable $e){
		$total_pending = 0;
	}
	$total_pending += backoffice_commission_bank_attention_count($user_id, backoffice_user_is_admin($session));
	if ($user_id > 0 && function_exists('Vaults\\attentionCount')) {
		$total_pending += \Vaults\attentionCount($user_id);
	}

    $obj = (object)[];
    $obj->title = 'Back Office';
    $obj->id = 'view_backoffice';
    $obj->link = ROOT . '/' . $vars['admin_route'] . '/' . $vars['plugin_route'] . '/list';
    $obj->icon = 'fas fa-briefcase'; // Briefcase icon for "Back Office"
    $obj->parent = 0;
	$obj->count = $total_pending > 0 ? (string)$total_pending : '';

   // $obj->sublinks = []; // Initialize sublinks array within the main link object

    // Add sublinks in ascending order by title

   /* if (!user_can('is_manager') || user_can('is_admin')) {
        $sub = (object)[];
        $sub->title = 'Make Request';
        $sub->link = ROOT . '/' . $vars['admin_route'] . '/' . $vars['plugin_route'] . '/make_request';
        $sub->icon = 'fas fa-hand-holding-dollar'; // Hand holding money icon
        $sub->parent = $obj->id;
        $sub->id = 'make_request';
        $obj->sublinks[] = $sub;
	    
	   
	    $sub = (object)[];
	    $sub->title = 'Make Payment';
	    $sub->link = ROOT . '/' . $vars['admin_route'] . '/' . $vars['plugin_route'] . '/make_payment';
	    $sub->icon = 'fas fa-money-check-alt'; // Money check icon for payment
	    $sub->parent = $obj->id;
	    $sub->id = 'make_payment';
	    $obj->sublinks[] = $sub;
	    
	   
	    $sub = (object)[];
	    $sub->title = 'Daily Commission';
	    $sub->link = ROOT . '/' . $vars['admin_route'] . '/' . $vars['plugin_route'] . '/pay_dailycommission';
        $sub->icon = 'fas fa-hand-holding-dollar'; // Hand holding money icon
	    $sub->parent = $obj->id;
	    $sub->id = 'pay_commission';
	    $obj->sublinks[] = $sub;
	    
	   
	    $sub = (object)[];
	    $sub->title = 'Other Commission';
	    $sub->link = ROOT . '/' . $vars['admin_route'] . '/' . $vars['plugin_route'] . '/pay_commission';
        $sub->icon = 'fas fa-wallet'; // Hand holding money icon
	    $sub->parent = $obj->id;
	    $sub->id = 'pay_commission';
	    $obj->sublinks[] = $sub;

	    $sub = (object)[];
	    $sub->title = 'EVD Self-Stocking';
	    $sub->link = ROOT . '/' . $vars['admin_route'] . '/' . $vars['plugin_route'] . '/self_stocking';
	    $sub->icon = 'fas fa-warehouse'; // Warehouse icon for self-stocking
	    $sub->parent = $obj->id;
	    $sub->id = 'self_stocking';
	    $obj->sublinks[] = $sub;

	    $sub = (object)[];
	    $sub->title = 'Other Payment';
	    $sub->link = ROOT . '/' . $vars['admin_route'] . '/' . $vars['plugin_route'] . '/payment';
	    $sub->icon = 'fas fa-piggy-bank'; // Piggy bank icon for "Other Payment"
	    $sub->parent = $obj->id;
	    $sub->id = 'other_payment';
	    $obj->sublinks[] = $sub;

	    $sub = (object)[];
	    $sub->title = 'Reconciliation';
	    $sub->link = ROOT . '/' . $vars['admin_route'] . '/' . $vars['plugin_route'] . '/reconciliation';
	    $sub->icon = 'fas fa-balance-scale'; // Balance scale icon for reconciliation
	    $sub->parent = $obj->id;
	    $sub->id = 'reconciliation';
	    $obj->sublinks[] = $sub;
	}

    $sub = (object)[];
    $sub->title = 'View Transactions';
    $sub->link = ROOT . '/' . $vars['admin_route'] . '/' . $vars['plugin_route'] . '/list';
    $sub->icon = 'fas fa-list-alt'; // List icon for transactions
    $sub->parent = $obj->id;
    $sub->id = 'view_backoffice';
    $obj->sublinks[] = $sub;*/

    $links[] = $obj;

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
		$mywallet = new \BackOffices\Mywallet;
		$payment = new \BackOffices\Payment;
		$bankTransfer = new \BackOffices\BankTransfer;
		$commission = new \BackOffices\Commission;
		$reconciliation = new \BackOffices\Reconciliation;
		$request = new \BackOffices\RequestClass;
		$transaction = new \BackOffices\Transaction;
		$category = new \BackOffices\Category;
		$mycategory = new \BackOffices\Mycategory;
		$mywallet_roles_map = new \BackOffices\User_roles_map;

		$id = URL(3) ?? null;
		if($id)
			$categories = $category->where(['disabled'=>0]);

		if(URL(2) == 'add'){
			require plugin_path('controllers/add-controller.php');
		}else
		if(in_array(URL(2), ['make_agent_service', 'edit_agent_service', 'view_agent_service', 'process_agent_service'])){
			require plugin_path('controllers/agent-services-controller.php');
		}else
		if(URL(2) == 'make_bank_transfer'){
			require plugin_path('controllers/bank-transfer-create-controller.php');
		}else
		if(URL(2) == 'commission_bank_transfers'){
			require plugin_path('controllers/commission-bank-transfer-controller.php');
		}else
		if(URL(2) == 'bank_transfer_withdraw'){
			require plugin_path('controllers/bank-transfer-withdraw-controller.php');
		}else
		if(URL(2) == 'bank_transfer_verify'){
			require plugin_path('controllers/bank-transfer-verify-controller.php');
		}else
		if(URL(2) == 'bank_transfer_reassign'){
			require plugin_path('controllers/bank-transfer-reassign-controller.php');
		}else
		if(URL(2) == 'bank_transfer_creator_approve'){
			require plugin_path('controllers/bank-transfer-creator-approve-controller.php');
		}else
		if(URL(2) == 'bank_transfer_creator_receipt'){
			require plugin_path('controllers/bank-transfer-receipt-controller.php');
		}else
		if(URL(2) == 'bank_transfer_review'){
			require plugin_path('controllers/bank-transfer-review-controller.php');
		}else
		if(URL(2) == 'reject_reconciliation'){
			require plugin_path('controllers/reject_reconciliation-controller.php');
		}else
		if(URL(2) == 'approve_reconciliation'){
			require plugin_path('controllers/approve_reconciliation-controller.php');
		}else
		if(URL(2) == 'grant_reconciliation_access'){
			require plugin_path('controllers/grant-reconciliation-access-controller.php');
		}else
		if(URL(2) == 'reconciliation'){
			require plugin_path('controllers/reconciliation-controller.php');
		}else
		if(URL(2) == 'make_payment'){
			
			require plugin_path('controllers/payment-controller.php');
		}else
		if(URL(2) == 'pay_commission'){
			
			require plugin_path('controllers/commission-controller.php');
		}else
		if(URL(2) == 'pay_dailycommission'){
			
			require plugin_path('controllers/dailycommission-controller.php');
		}else
		if(URL(2) == 'edit_commission'){
			$mywallet_role = new \BackOffices\User_role;
			$row = $commission->first(['id'=>$id]);
			
			if (backoffice_commission_uses_assignment_workflow($row)) {
				require plugin_path('controllers/review_commission-controller.php');
			} else {
				require plugin_path('controllers/edit_commission-controller.php');
			}
		}else
		if(URL(2) == 'edit_payment'){
			$mywallet_role = new \BackOffices\User_role;
			$row = $payment->first(['id'=>$id]);
			
			require plugin_path('controllers/edit_payment-controller.php');
		}else
		if(URL(2) == 'make_request'){			
			require plugin_path('controllers/make_request-controller.php');
		}else
		if(URL(2) == 'edit_request'){
			$mywallet_role = new \BackOffices\User_role;
			$request = new \BackOffices\RequestClass;
			
			$query = "SELECT * FROM mywallets WHERE transaction_status =:transaction_status AND deleted = :deleted ORDER BY network ASC";

			$mywallets = $mywallet->query($query,
				['deleted'=>0, 'transaction_status'=>'Approved']
			);
			$row = $request->first(['id'=>$id]);
			
			require plugin_path('controllers/edit_request-controller.php');
		}else
		if(URL(2) == 'edit'){			
			require plugin_path('controllers/edit-controller.php');
		}else
		if(URL(2) == 'delete'){
			$request = new \BackOffices\RequestClass;
			$row = $request->first(['id'=>$id]);
			require plugin_path('controllers/delete-controller.php');
		}else
		if(URL(2) == 'delete_payment'){
			$row = $payment->first(['id'=>$id]);
			require plugin_path('controllers/delete_payment-controller.php');
		}else
		if(URL(2) == 'delete_commission'){
			$row = $commission->first(['id'=>$id]);
			require plugin_path('controllers/delete_commission-controller.php');
		}else
		if(URL(2) == 'transfer'){
			require plugin_path('controllers/transfer-controller.php');
		}

	}
});


/** displays the view file **/
add_action('basic-admin_main_content',function(){

	$ses = new \Core\Session;
	$mywallet = new \BackOffices\Mywallet;
	$payment = new \BackOffices\Payment;
	$bankTransfer = new \BackOffices\BankTransfer;
	$commission = new \BackOffices\Commission;
	$reconciliation = new \BackOffices\Reconciliation;
	$transaction = new \BackOffices\Transaction;
	$category = new \BackOffices\Category;
	$mycategory = new \BackOffices\Mycategory;
	$sim = new \BackOffices\Sim;
	$user = new \BackOffices\User;
	$vars = get_value();

	$admin_route = $vars['admin_route'];
	$plugin_route = $vars['plugin_route'];
	
	$errors = $vars['errors'] ?? [];

	$user::$query_id = 'get-users';
	$users = $user->where(['deleted'=>0]);

	if (URL(1) == $vars['plugin_route'] && URL(2) == 'commission_bank_transfers') {
		require plugin_path('controllers/commission-bank-transfer-controller.php');
		return;
	}

	if (URL(1) == $vars['plugin_route'] && URL(2) == 'general_balance') {
		require plugin_path('controllers/general-balance-controller.php');
		return;
	}


	if(URL(1) == $vars['plugin_route']){

		$id = URL(3) ?? null;

	    $session = get_session();
	    $user_id = $session->id;
	    $commissionBankAttentionCount = backoffice_commission_bank_attention_count(
	    	(int)$user_id,
	    	backoffice_user_is_admin($session)
	    );
	    //dd($session);

		if($id){
			$mywallet::$query_id = 'get-users';			
			$row = $mywallet->first(['id'=>$id]);
		}

		$query = "SELECT * FROM mywallets WHERE assigned = :assigned AND transaction_status =:transaction_status AND deleted = :deleted ORDER BY network ASC";
		$query2 = "SELECT SUM(balance) AS expected_balance FROM mywallets WHERE assigned = :assigned AND transaction_status =:transaction_status AND deleted = :deleted";

		$mywallets = $mywallet->query($query,
			['deleted'=>0, 'transaction_status'=>'Approved', 'assigned'=>$user_id]
		);

		$wallets = $mywallet->where(['deleted'=>0, 'transaction_status'=>'Approved']);

		$otherWallets = $mywallet->where(
			['deleted'=>0, 'transaction_status'=>'Approved'],
			['assigned'=>$user_id]
		);

		$mywalletsBalance = $mywallet->query($query2,
			['deleted'=>0, 'transaction_status'=>'Approved', 'assigned'=>$user_id]
		);

		if (!empty($mywalletsBalance)) {
			$expectedBalance = $mywalletsBalance[0]->expected_balance ?? 0;
		}else{
			$expectedBalance = 0;
		}
		
		$categories = $category->where(['disabled'=>0]);

		if(URL(2) == 'make_request'){
			
			$request = new \BackOffices\RequestClass;
			$mywallet_role = new \BackOffices\User_role;
			
			$user::$query_id = 'get-users';
			$users = getAssignableUsersToday((int)$user_id);
			$backofficeCurrentUserAssignmentsMap = backoffice_user_sim_assignments_map([(int)$user_id]);
			$backofficeCurrentUserAssignments = $backofficeCurrentUserAssignmentsMap[(string)(int)$user_id] ?? [];

			require plugin_path('views/admin/requests/make_request.php');
		}else
		if(URL(2) == 'edit_request'){			
			$mywallet_role = new \BackOffices\User_role;
			$request = new \BackOffices\RequestClass;

			$query = "SELECT * FROM mywallets WHERE transaction_status =:transaction_status AND deleted = :deleted ORDER BY network ASC";

			$mywallets = $mywallet->query($query,
				['deleted'=>0, 'transaction_status'=>'Approved']
			);
			$row = $request->first(['id'=>$id]);
			$users = getAssignableUsersToday();
			require plugin_path('views/admin/requests/edit_request.php');
		}else
		if(URL(2) == 'view_request'){			
			$mywallet_role = new \BackOffices\User_role;
			$request = new \BackOffices\RequestClass;

				$bankCommissionMySql = backoffice_commission_can_access_bank_sims($session)
					? ''
					: ' AND ' . backoffice_commission_non_bank_sql_clause('m');
				$query = "
				    SELECT 
				        m.*, 
				        u.first_name AS user_first_name, 
			        u.last_name AS user_last_name, 
			        u.image AS user_image,
			        u_updated.first_name AS updated_by_first_name, 
			        u_updated.last_name AS updated_by_last_name, 
			        u_updated.image AS updated_by_image,
			        u_assigned.first_name AS assigned_first_name, 
			        u_assigned.last_name AS assigned_last_name, 
			        u_assigned.image AS assigned_image
			    FROM requests AS m 
			    LEFT JOIN users AS u ON m.created_by = u.id 
			    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
			    LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id 
			    WHERE m.id = :id 
			    LIMIT 1";

			$row = $request->query($query, ['id'=>$id])[0];

			require plugin_path('views/admin/requests/view_request.php');
		}else
		if(URL(2) == 'reconciliation'){			
			$mywallet_role = new \BackOffices\User_role;
			$session = get_session();
			$user_id = (int)($session->id ?? 0);
			$reconciliationLocked = false;
			$reconciliationLockMessage = '';
			$reconciliationLockTitle = 'Reconciliation unavailable';
			$activeReconciliation = null;
			$reconciliationSetupError = '';
			$lateAccessId = (int)($_GET['late_access'] ?? 0);
			$lateReconciliationAccess = $lateAccessId > 0 ? backoffice_active_late_reconciliation_access($user_id, $lateAccessId) : null;
			if ($lateAccessId > 0 && empty($lateReconciliationAccess)) {
				$reconciliationLocked = true;
				$reconciliationLockTitle = 'Late reconciliation access unavailable';
				$reconciliationLockMessage = 'This late reconciliation access has expired, was used, or does not belong to your account.';
			}
			if (!empty($lateReconciliationAccess) && empty($lateReconciliationAccess->notification_seen_at)) {
				try {
					$dbLateSeen = new \Core\Database;
					$dbLateSeen->query(
						"UPDATE reconciliation_late_access SET notification_seen_at = :seen_at, date_updated = :seen_at WHERE id = :id AND user_id = :user_id",
						[
							'seen_at' => date('Y-m-d H:i:s'),
							'id' => (int)$lateReconciliationAccess->id,
							'user_id' => (int)$user_id,
						]
					);
				} catch (\Throwable $e) {
					// keep access usable even if notification state cannot be updated
				}
			}
			if ($user_id > 0 && !backoffice_user_is_admin($session) && empty($lateReconciliationAccess)) {
				$acknowledgedPettyCash = backoffice_reconciliation_acknowledged_petty_cash($user_id);
				if (!empty($acknowledgedPettyCash)) {
					$reconciliationLocked = true;
					$reconciliationLockTitle = 'Petty Cash completion required';
					$reconciliationLockMessage = backoffice_reconciliation_acknowledged_petty_cash_message($acknowledgedPettyCash);
				}
				$activeReconciliation = backoffice_active_reconciliation_for_day($user_id);
				if (!empty($activeReconciliation)) {
					$reconciliationLocked = true;
					$reconciliationLockTitle = 'Reconciliation already submitted for today';
					$reconciliationLockMessage = backoffice_reconciliation_lock_message($activeReconciliation);
					$activeReconciliationStatus = strtolower(trim((string)($activeReconciliation->transaction_status ?? 'Submitted')));
					$activeReconciliationId = (string)($activeReconciliation->id ?? $activeReconciliation->session_id ?? '');
					if ($activeReconciliationStatus === 'approved' && $activeReconciliationId !== '') {
						redirect($admin_route . '/' . $plugin_route . '/view_reconciliation/' . $activeReconciliationId);
						exit;
					}
				}
			}
			try {
				$dbReconSetup = new \Core\Database;
				if (!$dbReconSetup->table_exists(['reconciliations'])) {
					$reconciliationLocked = true;
					$reconciliationLockTitle = 'Reconciliation unavailable';
					$reconciliationSetupError = 'Reconciliation detail table is missing. Please repair the reconciliations table before submitting a new reconciliation.';
					$reconciliationLockMessage = $reconciliationSetupError;
				}
			} catch (\Throwable $e) {
				$reconciliationLocked = true;
				$reconciliationLockTitle = 'Reconciliation unavailable';
				$reconciliationSetupError = 'Unable to verify reconciliation table setup. Please retry after MySQL is running normally.';
				$reconciliationLockMessage = $reconciliationSetupError;
			}
			$drawerContext = null;
			$drawerSnapshot = [];
			$ecashBalances = [];
			$assignedSharedAccountKeys = users_manager_user_shared_account_keys((int)$user_id);
			if (!empty($lateReconciliationAccess)) {
				$drawerSnapshot = backoffice_cashdrawer_snapshot_by_id((int)$lateReconciliationAccess->cashdrawer_id, $user_id);
				$drawerContext = !empty($drawerSnapshot)
					? ['drawer_id' => (int)($drawerSnapshot['drawer_id'] ?? 0), 'drawer_user_id' => (int)($drawerSnapshot['drawer_user_id'] ?? 0)]
					: null;
			} elseif ($user_id > 0 && function_exists('Cfas\\cfa_get_cashdrawer_snapshot')) {
				$drawerSnapshot = \Cfas\cfa_get_cashdrawer_snapshot($user_id);
				$drawerContext = !empty($drawerSnapshot)
					? ['drawer_id' => (int)($drawerSnapshot['drawer_id'] ?? 0), 'drawer_user_id' => (int)($drawerSnapshot['drawer_user_id'] ?? 0)]
					: null;
			}
			if (!empty($drawerContext)) {
				$dbRecon = new \Core\Database;
				$rowsEcash = $dbRecon->query(
					"SELECT channel_key, closing_balance FROM drawer_balances WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'ecash'",
					[
						'drawer_id' => (int)$drawerContext['drawer_id'],
						'user_id' => (int)$drawerContext['drawer_user_id'],
					]
				) ?: [];
				foreach ($rowsEcash as $r) {
					$key = strtoupper(trim((string)($r->channel_key ?? '')));
					if ($key === '') {
						continue;
					}
					if (strpos($key, 'ECASH_') === 0) {
						$key = substr($key, 6);
					}
					$key = str_replace('_', '-', $key);
					$key = str_replace(' ', '-', $key);
					$key = trim($key, '-');
					$sharedAccountKey = users_manager_normalize_shared_account_key($key);
					if ($sharedAccountKey !== null) {
						if (!in_array($sharedAccountKey, $assignedSharedAccountKeys, true)) {
							continue;
						}
						$key = str_replace('_', '-', $sharedAccountKey);
					}
					if (in_array($key, ['ECASH', 'E-CASH', 'E-CASH-', 'E-CASH--', 'EZWICH', 'E-ZWICH'], true)) {
						continue;
					}
					$ecashBalances[$key] = (float)($r->closing_balance ?? 0);
				}
				$reconciliationDate = !empty($lateReconciliationAccess->drawer_date)
					? (string)$lateReconciliationAccess->drawer_date
					: date('Y-m-d');
				foreach (backoffice_reconciliation_assigned_shared_account_balances($assignedSharedAccountKeys, $reconciliationDate) as $sharedKey => $balance) {
					$ecashBalances[str_replace('_', '-', $sharedKey)] = $balance;
				}
				if (in_array('MTN_MOMOPAY', $assignedSharedAccountKeys, true)
					&& function_exists('Agencybankings\\agency_find_shared_momopay_context')) {
					$sharedMomoContext = \Agencybankings\agency_find_shared_momopay_context(
						null,
						$reconciliationDate
					);
					if (!empty($sharedMomoContext)) {
						$ecashBalances['MTN-MOMOPAY'] = (float)($sharedMomoContext['closing_balance'] ?? 0);
					}
				}
			}
			$outstandingEcashChannels = backoffice_reconciliation_outstanding_ecash_channels($ecashBalances);
			if (!$reconciliationLocked
				&& empty($lateReconciliationAccess)
				&& !backoffice_user_is_admin($session)
				&& !backoffice_user_is_operations_manager($session)
				&& !empty($outstandingEcashChannels)) {
				$reconciliationLocked = true;
				$reconciliationLockTitle = 'Outstanding e-cash balances';
				$reconciliationLockMessage = backoffice_reconciliation_outstanding_ecash_message($outstandingEcashChannels);
				set_value('reconciliation_outstanding_payment_context', [
					'user_id' => (int)$user_id,
					'late_access_id' => 0,
					'channels' => $outstandingEcashChannels,
					'created_at' => time(),
				]);
			}

			$defaultEcashChannels = ['AT', 'G-MONEY', 'MTN', 'MTN-MOMOPAY', 'TELECEL', 'ZEEPAY', 'GHANAPAY'];
			$allEcashChannels = array_values(array_unique(array_merge($defaultEcashChannels, array_keys($ecashBalances))));
			sort($allEcashChannels);

			$isReconPrivileged = (user_can('is_admin') || user_can('is_manager'));
			$isGeneralManager = false;
			try {
				$rolesRaw = $session->roles ?? [];
				if (!is_array($rolesRaw)) {
					$rolesRaw = [$rolesRaw];
				}
				$rolesLower = array_map(function ($r) {
					return strtolower(trim((string)$r));
				}, $rolesRaw);
				$isGeneralManager = (bool)array_intersect($rolesLower, ['general manager', 'general_manager', 'generalmanager', 'gm', 'is_general_manager']);
			} catch (\Throwable $e) {
				$isGeneralManager = false;
			}
			$canSeeBankChannels = (user_can('is_admin') || $isGeneralManager);
			$bankChannelNeedles = [
				'BANK',
				'ADB',
				'ABSA',
				'FIDELITY',
				'APEX',
				'ACCESS',
				'CAL',
				'ECOBANK',
				'GCB',
				'GT',
				'STANBIC',
				'FNB',
				'FIRST',
			];
			$isBankKey = function (string $key) use ($bankChannelNeedles): bool {
				$key = strtoupper(trim($key));
				if ($key === '') {
					return false;
				}
				foreach ($bankChannelNeedles as $needle) {
					if ($needle !== '' && strpos($key, $needle) !== false) {
						return true;
					}
				}
				return false;
			};

			$commissionTotal = 0.0;
			try {
				$dbComm = new \Core\Database;
				if ($dbComm->table_exists(['commissions'])) {
					$today = !empty($lateReconciliationAccess) && !empty($lateReconciliationAccess->drawer_date)
						? (string)$lateReconciliationAccess->drawer_date
						: date('Y-m-d');
					$commWhereParts = [
						"(deleted IS NULL OR deleted = 0)",
						"(disabled IS NULL OR disabled = 0)",
						"LOWER(TRIM(COALESCE(transaction_status,''))) <> 'rejected'",
						"(pay_to = :uid OR created_by = :uid)",
						"DATE(COALESCE(NULLIF(NULLIF(NULLIF(TRIM(date_received),''),'0000-00-00 00:00:00'),'0000-00-00'), date_created)) = :today",
						"(
							UPPER(COALESCE(commission_type,'')) LIKE '%CHARGE%'
							OR UPPER(COALESCE(commission_type,'')) = 'MOMOREMIT CASHIN'
							OR UPPER(COALESCE(colletion_period,'')) = 'INSTANT'
							OR UPPER(COALESCE(commission_type,'')) LIKE '%BULK%'
							OR UPPER(COALESCE(description,'')) LIKE '%COMMISSION BULK%'
						)"
					];
					try {
						$bulkCol = $dbComm->query("SHOW COLUMNS FROM commissions LIKE 'bulk_parent_id'") ?: [];
						if (!empty($bulkCol)) {
							$commWhereParts[] = "(bulk_parent_id IS NULL OR bulk_parent_id = 0)";
						}
					} catch (\Throwable $e) {
						// ignore bulk parent check if column lookup fails
					}
					$commWhereSql = implode(' AND ', $commWhereParts);
					$commissionRow = $dbComm->query(
						"SELECT COALESCE(SUM(amount),0) AS total_amount
						 FROM commissions
						 WHERE {$commWhereSql}",
						[
							'uid' => $user_id,
							'today' => $today,
						]
					);
					if (!empty($commissionRow)) {
						$commissionTotal = (float)($commissionRow[0]->total_amount ?? 0);
					}
				}
			} catch (\Throwable $e) {
				$commissionTotal = 0.0;
			}

			$reconChannels = [];
			$reconChannels[] = [
				'channel_id' => 'cash:GHS',
				'group' => 'Cash',
				'label' => 'Cash (GHS)',
				'expected' => (float)($drawerSnapshot['ghs_balance'] ?? 0),
			];
			$reconChannels[] = [
				'channel_id' => 'cash:CFA',
				'group' => 'Cash',
				'label' => 'Cash (CFA)',
				'expected' => (float)($drawerSnapshot['cfa_balance'] ?? 0),
			];
			if ($commissionTotal > 0.009) {
				$reconChannels[] = [
					'channel_id' => 'commission:charges',
					'group' => 'Commissions',
					'label' => 'Commissions (Auto)',
					'expected' => (float)$commissionTotal,
					'is_commission' => true,
				];
			}
			foreach ($allEcashChannels as $ch) {
				$key = strtoupper(trim((string)$ch));
				if ($key === '') {
					continue;
				}
				if (in_array($key, ['EZWICH', 'E-ZWICH'], true)) {
					continue;
				}
				$isBank = $isBankKey($key);
				$isSharedChannel = backoffice_reconciliation_is_shared_channel($key);
				$sharedAccountKey = users_manager_normalize_shared_account_key($key);
				if (($isBank || $isSharedChannel || $sharedAccountKey !== null)
					&& ($sharedAccountKey === null || !in_array($sharedAccountKey, $assignedSharedAccountKeys, true))) {
					continue;
				}
				$isBank = $isBank || ($sharedAccountKey !== null && $sharedAccountKey !== 'MTN_MOMOPAY');
				$isSharedChannel = $isSharedChannel || $sharedAccountKey === 'MTN_MOMOPAY';
				$reconChannels[] = [
					'channel_id' => 'ecash:' . $key,
					'group' => ($isBank || $isSharedChannel) ? 'Shared Accounts Channels' : 'E-Cash',
					'label' => $isSharedChannel ? 'MTN MoMoPay' : ($isBank ? ('Bank (' . $key . ')') : ('E-Cash (' . $key . ')')),
					'is_bank' => $isBank,
					'expected' => (float)($ecashBalances[$key] ?? 0),
				];
			}

			$expectedBalance = 0.0;
			foreach ($reconChannels as $rc) {
				$expectedBalance += (float)($rc['expected'] ?? 0);
			}

			require plugin_path('views/admin/reconciliations/reconciliation.php');
		}else
		if(URL(2) == 'view_reconciliation'){			
			$mywallet_role = new \BackOffices\User_role;
			$reconciliation_summary = new \BackOffices\Reconciliation_summary;
			$session = get_session();
			$user_id = (int)($session->id ?? 0);
			$canRejectReconciliations = backoffice_user_can_reject_reconciliations($session);
			$canApproveReconciliations = backoffice_user_can_approve_reconciliations($session);

			$summaryRows = $reconciliation_summary->query(
				"SELECT m.*, u.first_name AS user_first_name, u.last_name AS user_last_name, u.image AS user_image
				 FROM reconciliations_summary AS m
				 LEFT JOIN users AS u ON m.created_by = u.id
				 WHERE m.id = :id
				 LIMIT 1",
				['id' => $id]
			) ?: [];
			if (empty($summaryRows)) {
				$summaryRows = $reconciliation_summary->query(
					"SELECT m.*, u.first_name AS user_first_name, u.last_name AS user_last_name, u.image AS user_image
					 FROM reconciliations_summary AS m
					 LEFT JOIN users AS u ON m.created_by = u.id
					 WHERE m.session_id = :session_id
					 ORDER BY m.id DESC
					 LIMIT 1",
					['session_id' => (string)$id]
				) ?: [];
			}
			$summary = !empty($summaryRows) ? $summaryRows[0] : null;

			if (empty($summary)) {
				message_fail('Reconciliation not found.');
				redirect($admin_route . '/' . $plugin_route);
				exit;
			}

			if (!backoffice_user_can_oversee_reconciliations($session) && (string)($summary->created_by ?? '') !== (string)$user_id) {
				message_fail('You can only view your own reconciliation.');
				redirect($admin_route . '/' . $plugin_route);
				exit;
			}

			$reconciliationSessionId = (string)($summary->session_id ?? $summary->id ?? $id);
			$reconciliations = [];

			try {
				$dbReconDetail = new \Core\Database;
				if ($dbReconDetail->table_exists(['reconciliations'])) {
					$query = "
					SELECT 
						m.*, 
						u.first_name AS user_first_name, 
						u.last_name AS user_last_name, 
						u.image AS user_image
					FROM reconciliations AS m 
					LEFT JOIN users AS u ON m.created_by = u.id 
					WHERE m.session_id = :id
					ORDER BY m.id ASC
				";

					$reconciliations = $reconciliation->query($query, ['id'=>$reconciliationSessionId]) ?: [];
				}
			} catch (\Throwable $e) {
				$reconciliations = [];
			}

			require plugin_path('views/admin/reconciliations/view.php');
		}else
		if(URL(2) == 'pay_commission'){
			
			$mywallet_role = new \BackOffices\User_role;
			$session = get_session();
			$uid = (int)($session->id ?? 0);
			$users = backoffice_commission_assignable_users($uid, $session, true);
			$assignedSims = function_exists('users_manager_fetch_sim_assignments') ? users_manager_fetch_sim_assignments($uid) : [];
			$assignedSims = backoffice_commission_filter_assignments_for_session($assignedSims, $session);
			require plugin_path('views/admin/commissions/pay_commission.php');
		}else
		if(URL(2) == 'pay_dailycommission'){
			
			$mywallet_role = new \BackOffices\User_role;
			try {
				$dbEligible = new \Core\Database;
				$session = get_session();
				$uid = (int)($session->id ?? 0);
				$todayEligible = date('Y-m-d');
				if ($uid > 0 && $dbEligible->table_exists(['cashdrawers', 'attendance', 'users'])) {
					$eligibleRows = $dbEligible->query(
						"SELECT DISTINCT u.*
						 FROM users u
						 LEFT JOIN cashdrawers c ON c.user_id = u.id AND DATE(c.date_created) = :d
						 LEFT JOIN attendance a ON a.user_id = u.id AND a.deleted = 0 AND a.date = :d AND UPPER(COALESCE(a.remarks,'')) IN ('PRESENT','LATE')
						 WHERE u.deleted = 0
						 AND u.id <> :uid
						 AND (c.id IS NOT NULL OR a.id IS NOT NULL)
						 ORDER BY u.first_name ASC, u.last_name ASC, u.id ASC",
						['d' => $todayEligible, 'uid' => $uid]
					) ?: [];
					if (!empty($eligibleRows) && $dbEligible->table_exists(['user_roles', 'user_roles_map'])) {
						$ids = array_values(array_filter(array_map(function ($r) {
							return (int)($r->id ?? 0);
						}, $eligibleRows)));
						$ids = array_values(array_unique(array_filter($ids, function ($x) {
							return $x > 0;
						})));
						if (!empty($ids)) {
							$in = implode(',', array_map('intval', $ids));
							$roleRows = $dbEligible->query(
								"SELECT m.user_id, ur.role
								 FROM user_roles_map m
								 JOIN user_roles ur ON ur.id = m.role_id
								 WHERE m.disabled = 0 AND ur.disabled = 0 AND m.user_id IN ($in)
								 ORDER BY m.id ASC"
							) ?: [];
							$roleMap = [];
							foreach ($roleRows as $rr) {
								$uidR = (int)($rr->user_id ?? 0);
								if ($uidR <= 0 || isset($roleMap[$uidR])) {
									continue;
								}
								$roleMap[$uidR] = trim((string)($rr->role ?? ''));
							}
							foreach ($eligibleRows as $er) {
								$uidR = (int)($er->id ?? 0);
								$role0 = $roleMap[$uidR] ?? '';
								$er->roles = [$role0];
							}
						}
					}
					$users = $eligibleRows;
				}
			} catch (\Throwable $e) {
				// ignore
			}
			require plugin_path('views/admin/commissions/pay_dailycommission.php');
		}else
		if(URL(2) == 'edit_commission'){
			
			$mywallet_role = new \BackOffices\User_role;
			$row = $commission->first(['id'=>$id]);
			$commRouteDb = new \Core\Database;
			$commissionColumns = backoffice_table_columns($commRouteDb, 'commissions');
			$hasAssigned = !empty($commissionColumns['assigned']);
			$assignedExpr = $hasAssigned ? 'COALESCE(m.assigned, m.pay_to)' : 'm.pay_to';
			$session = get_session();
			$viewerId = (int)($session->id ?? 0);
			$commissionWorkflowMode = backoffice_commission_uses_assignment_workflow($row);
			if (!empty($row) && $commissionWorkflowMode) {
				$detailRows = $commission->query(
					"SELECT
						m.*,
						u.first_name AS user_first_name,
						u.last_name AS user_last_name,
						u.image AS user_image,
						payto.first_name AS payto_first_name,
						payto.last_name AS payto_last_name,
						payto.image AS payto_image,
						assigned_user.first_name AS assigned_first_name,
						assigned_user.last_name AS assigned_last_name,
						assigned_user.image AS assigned_image
					FROM commissions m
					LEFT JOIN users u ON m.created_by = u.id
					LEFT JOIN users payto ON m.pay_to = payto.id
					LEFT JOIN users assigned_user ON {$assignedExpr} = assigned_user.id
					WHERE m.id = :id
					LIMIT 1",
					['id' => (int)$id]
				) ?: [];
				if (!empty($detailRows[0])) {
					$row = $detailRows[0];
				}
			}
			if (!empty($row) && $commissionWorkflowMode) {
				$canViewAll = backoffice_commission_can_view_all($session);
				$assignedId = backoffice_commission_assignee_id($row);
				$createdBy = (int)($row->created_by ?? 0);
				if ($viewerId <= 0 || (!$canViewAll && $viewerId !== $createdBy && $viewerId !== $assignedId)) {
					message_fail('Access denied.');
					redirect($admin_route . '/' . $plugin_route . '/list');
					exit;
				}
			}
			$bulkChildren = backoffice_commission_breakdown_rows($commission, $row, $commissionColumns);
			try {
				$dbEligible = new \Core\Database;
				$session = get_session();
				$uid = (int)($session->id ?? 0);
				$todayEligible = date('Y-m-d');
				if ($uid > 0 && $dbEligible->table_exists(['cashdrawers', 'attendance', 'users'])) {
					$eligibleRows = $dbEligible->query(
						"SELECT DISTINCT u.*
						 FROM users u
						 LEFT JOIN cashdrawers c ON c.user_id = u.id AND DATE(c.date_created) = :d
						 LEFT JOIN attendance a ON a.user_id = u.id AND a.deleted = 0 AND a.date = :d AND UPPER(COALESCE(a.remarks,'')) IN ('PRESENT','LATE')
						 WHERE u.deleted = 0
						 AND u.id <> :uid
						 AND (c.id IS NOT NULL OR a.id IS NOT NULL)
						 ORDER BY u.first_name ASC, u.last_name ASC, u.id ASC",
						['d' => $todayEligible, 'uid' => $uid]
					) ?: [];
					$payToIdExisting = !empty($row) ? (int)($row->pay_to ?? 0) : 0;
					$hasExisting = false;
					if ($payToIdExisting > 0) {
						foreach ($eligibleRows as $er) {
							if ((int)($er->id ?? 0) === $payToIdExisting) {
								$hasExisting = true;
								break;
							}
						}
						if (!$hasExisting) {
							$existingRows = $dbEligible->query(
								"SELECT u.* FROM users u WHERE u.deleted = 0 AND u.id = :pid LIMIT 1",
								['pid' => $payToIdExisting]
							) ?: [];
							if (!empty($existingRows)) {
								$eligibleRows[] = $existingRows[0];
							}
						}
					}
					if (!empty($eligibleRows) && $dbEligible->table_exists(['user_roles', 'user_roles_map'])) {
						$ids = array_values(array_filter(array_map(function ($r) {
							return (int)($r->id ?? 0);
						}, $eligibleRows)));
						$ids = array_values(array_unique(array_filter($ids, function ($x) {
							return $x > 0;
						})));
						if (!empty($ids)) {
							$in = implode(',', array_map('intval', $ids));
							$roleRows = $dbEligible->query(
								"SELECT m.user_id, ur.role
								 FROM user_roles_map m
								 JOIN user_roles ur ON ur.id = m.role_id
								 WHERE m.disabled = 0 AND ur.disabled = 0 AND m.user_id IN ($in)
								 ORDER BY m.id ASC"
							) ?: [];
							$roleMap = [];
							foreach ($roleRows as $rr) {
								$uidR = (int)($rr->user_id ?? 0);
								if ($uidR <= 0 || isset($roleMap[$uidR])) {
									continue;
								}
								$roleMap[$uidR] = trim((string)($rr->role ?? ''));
							}
							foreach ($eligibleRows as $er) {
								$uidR = (int)($er->id ?? 0);
								$role0 = $roleMap[$uidR] ?? '';
								$er->roles = [$role0];
							}
						}
					}
					$users = $eligibleRows;
				}
			} catch (\Throwable $e) {
				// ignore
			}
			$isDailyBulk = false;
			if (!empty($row)) {
				$ctype = strtoupper(trim((string)($row->commission_type ?? '')));
				if ($ctype === 'DAILY BULK' || !empty($bulkChildren)) {
					$isDailyBulk = true;
				}
			}

			if ($commissionWorkflowMode) {
				require plugin_path('views/admin/commissions/review_commission.php');
			} elseif ($isDailyBulk) {
				require plugin_path('views/admin/commissions/edit_dailycommission.php');
			} else {
				require plugin_path('views/admin/commissions/edit_commission.php');
			}
		}else
		if(URL(2) == 'view_commission'){
			
			$mywallet_role = new \BackOffices\User_role;
			$viewDb = new \Core\Database;
			$commissionColumns = backoffice_table_columns($viewDb, 'commissions');
			$hasAssigned = !empty($commissionColumns['assigned']);
			$hasRejectReason = !empty($commissionColumns['reject_reason']);
			$hasBulkParentId = !empty($commissionColumns['bulk_parent_id']);
			$assignedExpr = $hasAssigned ? 'COALESCE(m.assigned, m.pay_to)' : 'm.pay_to';
			$rejectReasonSelect = $hasRejectReason ? 'm.reject_reason' : "'' AS reject_reason";
			//mycommissions tab data
			$query = "
			    SELECT 
				        m.*, 
				        u.first_name AS user_first_name, 
				        u.last_name AS user_last_name, 
				        u.image AS user_image,
				        u_updated.first_name AS updated_by_first_name, 
				        u_updated.last_name AS updated_by_last_name, 
				        u_updated.image AS updated_by_image,
				        payto.first_name AS payto_first_name, 
				        payto.last_name AS payto_last_name, 
				        payto.image AS payto_image,
				        assigned_user.first_name AS assigned_first_name,
				        assigned_user.last_name AS assigned_last_name,
				        assigned_user.image AS assigned_image,
				        {$rejectReasonSelect}
				    FROM commissions AS m 
				    LEFT JOIN users AS u ON m.created_by = u.id 
				    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
				    LEFT JOIN users AS payto ON m.pay_to = payto.id 
				    LEFT JOIN users AS assigned_user ON {$assignedExpr} = assigned_user.id
		        WHERE m.id = :id
				";

				$viewRows = $commission->query($query, ['id'=>$id]) ?: [];
				$row = $viewRows[0] ?? null;
				if (empty($row)) {
					message_fail('Commission record could not be loaded.');
					redirect($admin_route . '/' . $plugin_route . '/list');
					exit;
				}
				$viewerId = (int)($session->id ?? 0);
				$canViewAll = backoffice_commission_can_view_all($session);
				$canViewBankSims = backoffice_commission_can_access_bank_sims($session);
				if (!empty($row) && !$canViewAll) {
					$createdBy = (int)($row->created_by ?? 0);
					$assignedId = backoffice_commission_assignee_id($row);
					if ($viewerId <= 0 || ($createdBy !== $viewerId && $assignedId !== $viewerId)) {
						message_fail('Access denied.');
						redirect($admin_route . '/' . $plugin_route . '/list');
						exit;
					}
				}
				$breakdown = backoffice_commission_breakdown_rows($commission, $row, $commissionColumns);
				if (!$canViewBankSims && !empty($row) && backoffice_commission_row_has_bank_sim($row)) {
					message_fail('Access denied.');
					redirect($admin_route . '/' . $plugin_route . '/list');
					exit;
				}

			require plugin_path('views/admin/commissions/view_commission.php');
		}else
		if(URL(2) == 'payment'){
			
			$mywallet_role = new \BackOffices\User_role;
			require plugin_path('views/admin/payments/payment.php');
		}else
		if(URL(2) == 'make_agent_service'){
			$session = get_session();
			$uid = (int)($session->id ?? 0);
			$isAdminOrOps = backoffice_user_is_admin($session) || user_can('is_operations_manager');
			$users = getAssignableUsersToday($uid);
			if (!empty($users) && function_exists('Cfas\\cfa_get_cashdrawer_snapshot')) {
				foreach ($users as $u) {
					$drawerSnapshot = \Cfas\cfa_get_cashdrawer_snapshot((int)($u->id ?? 0));
					$u->current_balance = isset($drawerSnapshot['ghs_balance']) ? (float)$drawerSnapshot['ghs_balance'] : 0.0;
				}
			}
			$backofficePaymentAssignmentsMap = backoffice_user_sim_assignments_map(array_map(function($u){ return (int)$u->id; }, $users));
			require plugin_path('views/admin/agent_services/make_agent_service.php');
		}else
		if(URL(2) == 'view_agent_service'){
			$session = get_session();
			$uid = (int)($session->id ?? 0);
			if (!function_exists('backoffice_agent_services_assignable_users')) {
				function backoffice_agent_services_assignable_users() {
					return \BackOfficeCashdrawer\getAssignableUsersToday();
				}
			}
			$dbAgent = new \Core\Database;
			$rows = $dbAgent->query("SELECT m.*, c.first_name as creator_first_name, c.last_name as creator_last_name, a.first_name as assigned_first_name, a.last_name as assigned_last_name, u.first_name AS updated_by_first_name, u.last_name AS updated_by_last_name FROM agent_services m LEFT JOIN users c ON m.creator_id = c.id LEFT JOIN users a ON m.assigned_to = a.id LEFT JOIN users u ON m.updated_by = u.id WHERE m.id = :id LIMIT 1", ['id' => $id]);
			$row = !empty($rows) ? $rows[0] : null;
			if (empty($row)) {
				message_fail('Transaction not found.');
				redirect($admin_route . '/' . $plugin_route . '#agent-services');
				exit;
			}
			require plugin_path('views/admin/agent_services/view_agent_service.php');
		}else
		if(URL(2) == 'process_agent_service'){
			$session = get_session();
			$uid = (int)($session->id ?? 0);
			$dbAgent = new \Core\Database;
			$rows = $dbAgent->query("SELECT * FROM agent_services WHERE id = :id LIMIT 1", ['id' => $id]);
			$row = !empty($rows) ? $rows[0] : null;
			if (empty($row)) {
				message_fail('Transaction not found.');
				redirect($admin_route . '/' . $plugin_route . '#agent-services');
				exit;
			}
			$statusLower = strtolower(trim((string)($row->status ?? '')));
			$stage = (int)($row->stage ?? 1);
			$isAdminOrOps = backoffice_user_is_admin($session) || user_can('is_operations_manager');
			$isAssignee = (int)($row->assigned_to ?? 0) === $uid;

			if ($stage !== 2 || $statusLower !== 'pending' || !($isAssignee || $isAdminOrOps)) {
				message_fail('Only the assigned user, admin, or operations manager may process this stage.');
				redirect($admin_route . '/' . $plugin_route . '/view_agent_service/' . $id);
				exit;
			}

			$isProcessorMode = true;

			// Load creator's SIM assignments for internal transactions (to show to the assigned user)
			$agentServiceCreatorSimMap = [];
			if (!empty($row->creator_id) && function_exists('backoffice_user_sim_assignments_map')) {
				$agentServiceCreatorSimMap = backoffice_user_sim_assignments_map([(int)$row->creator_id]);
			}
			require plugin_path('views/admin/agent_services/edit_agent_service.php');
		}else
		if(URL(2) == 'edit_agent_service'){
			$session = get_session();
			$uid = (int)($session->id ?? 0);
			$dbAgent = new \Core\Database;
			$rows = $dbAgent->query("SELECT * FROM agent_services WHERE id = :id LIMIT 1", ['id' => $id]);
			$row = !empty($rows) ? $rows[0] : null;
			if (empty($row)) {
				message_fail('Transaction not found.');
				redirect($admin_route . '/' . $plugin_route . '#agent-services');
				exit;
			}
			$statusLower = strtolower(trim((string)($row->status ?? '')));
			$stage = (int)($row->stage ?? 1);
			$isAdminOrOps = backoffice_user_is_admin($session) || user_can('is_operations_manager');
			$isCreator = (int)($row->creator_id ?? 0) === $uid;
			$isAssignee = (int)($row->assigned_to ?? 0) === $uid;
			$isInternal = strtolower(trim((string)($row->customer_type ?? ''))) === 'internal';

			if ($stage === 1 && $statusLower === 'pending' && !($isCreator || $isAdminOrOps)) {
				message_fail('Only the creator, admin, or operations manager may edit pending transactions.');
				redirect($admin_route . '/' . $plugin_route . '/view_agent_service/' . $id);
				exit;
			}

			if ($stage === 2) {
				if ($isInternal) {
					if (!($isAssignee || $isCreator || $isAdminOrOps)) {
						message_fail('Only the creator, assigned user, admin, or operations manager may edit pending internal transactions at this stage.');
						redirect($admin_route . '/' . $plugin_route . '/view_agent_service/' . $id);
						exit;
					}
				} else {
					if (!($isCreator || $isAdminOrOps)) {
						message_fail('Only the creator, admin, or operations manager may edit stage 2.');
						redirect($admin_route . '/' . $plugin_route . '/view_agent_service/' . $id);
						exit;
					}
				}
			}
			// Load creator's SIM assignments for internal transactions (to show to the assigned user)
			$agentServiceCreatorSimMap = [];
			if (!empty($row->creator_id) && function_exists('backoffice_user_sim_assignments_map')) {
				$agentServiceCreatorSimMap = backoffice_user_sim_assignments_map([(int)$row->creator_id]);
			}
			require plugin_path('views/admin/agent_services/edit_agent_service.php');
		}else
		if(URL(2) == 'make_bank_transfer'){
			$session = get_session();
			$uid = (int)($session->id ?? 0);
			$withdrawerUsers = backoffice_bank_transfer_candidates_excluding([$uid]);
			$secondPartyUsers = backoffice_bank_transfer_candidates_excluding([$uid]);
			$thirdPartyUsers = backoffice_bank_transfer_third_party_candidates();
			$vaultOptions = function_exists('Vaults\\visibleVaults') ? \Vaults\visibleVaults($uid) : [];
			$assignmentUserIds = array_merge(
				backoffice_bank_transfer_user_ids($withdrawerUsers),
				backoffice_bank_transfer_user_ids($secondPartyUsers),
				backoffice_bank_transfer_user_ids($thirdPartyUsers)
			);
			$bankTransferAssignmentsMap = backoffice_user_sim_assignments_map(array_values(array_unique($assignmentUserIds)));
			require plugin_path('views/admin/bank_transfers/make_bank_transfer.php');
		}else
		if(URL(2) == 'view_bank_transfer'){
			$session = get_session();
			$uid = (int)($session->id ?? 0);
			$thirdPartyUsers = backoffice_bank_transfer_third_party_candidates();
			$thirdPartyUsers = backoffice_bank_transfer_include_current_user($thirdPartyUsers, (int)($row->third_party_user_id ?? 0));
			$thirdPartyIds = backoffice_bank_transfer_user_ids($thirdPartyUsers);
			$thirdPartyAssignmentsMap = backoffice_user_sim_assignments_map($thirdPartyIds);
			$bankRows = $bankTransfer->query(
				"SELECT m.*,
				        u.first_name AS creator_first_name,
				        u.last_name AS creator_last_name,
				        u.image AS creator_image,
				        uw.first_name AS withdrawer_first_name,
				        uw.last_name AS withdrawer_last_name,
				        uw.image AS withdrawer_image,
				        u2.first_name AS second_party_first_name,
				        u2.last_name AS second_party_last_name,
				        u2.image AS second_party_image,
				        u3.first_name AS third_party_first_name,
				        u3.last_name AS third_party_last_name,
				        u3.image AS third_party_image,
				        uv.first_name AS second_verified_first_name,
				        uv.last_name AS second_verified_last_name,
				        ur.first_name AS third_reviewed_first_name,
				        ur.last_name AS third_reviewed_last_name,
				        vsrc.name AS source_vault_name,
				        vdst.name AS destination_vault_name
				 FROM bank_transfers_backoffice m
				 LEFT JOIN users u ON m.created_by = u.id
				 LEFT JOIN users uw ON m.withdrawing_user_id = uw.id
				 LEFT JOIN users u2 ON m.second_party_user_id = u2.id
				 LEFT JOIN users u3 ON m.third_party_user_id = u3.id
				 LEFT JOIN users uv ON m.second_party_verified_by = uv.id
				 LEFT JOIN users ur ON m.third_party_reviewed_by = ur.id
				 LEFT JOIN vaults vsrc ON m.source_vault_id = vsrc.id
				 LEFT JOIN vaults vdst ON m.destination_vault_id = vdst.id
				 WHERE m.id = :id
				 LIMIT 1",
				['id' => $id]
			);
			$row = !empty($bankRows) ? $bankRows[0] : null;
			if (empty($row)) {
				message_fail('Bank transfer not found.');
				redirect($admin_route . '/' . $plugin_route);
				exit;
			}
			$withdrawerUsers = backoffice_bank_transfer_candidates_excluding([(int)$uid]);
			$creatorApprovalSecondPartyUsers = backoffice_bank_transfer_candidates_excluding([
				(int)($row->created_by ?? 0),
				(int)($row->withdrawing_user_id ?? 0),
			]);
			$secondPartyReassignExclude = [(int)($row->created_by ?? 0)];
			if (backoffice_bank_transfer_is_from_bank_flow((string)($row->transaction_type ?? ''))) {
				$secondPartyReassignExclude[] = (int)($row->withdrawing_user_id ?? 0);
			}
			$secondPartyReassignUsers = backoffice_bank_transfer_candidates_excluding($secondPartyReassignExclude);
			$secondPartyReassignUsers = backoffice_bank_transfer_include_current_user($secondPartyReassignUsers, (int)($row->second_party_user_id ?? 0));
			$withdrawerReassignUsers = backoffice_bank_transfer_candidates_excluding([
				(int)($row->created_by ?? 0),
				(int)($row->second_party_user_id ?? 0),
			]);
			$withdrawerReassignUsers = backoffice_bank_transfer_include_current_user($withdrawerReassignUsers, (int)($row->withdrawing_user_id ?? 0));
			$rowType = strtolower(trim((string)($row->transaction_type ?? '')));
			if (in_array($rowType, ['from vault', 'to vault'], true)) {
				$vaultId = $rowType === 'from vault'
					? (int)($row->source_vault_id ?? 0)
					: (int)($row->destination_vault_id ?? 0);
				$vault = function_exists('Vaults\\getVault') ? \Vaults\getVault($vaultId, (int)$uid) : null;
				$branchId = (int)($vault->branch_id ?? 0);
				$secondPartyReassignUsers = backoffice_bank_transfer_filter_users_by_branch($secondPartyReassignUsers, $branchId);
				if ($rowType === 'from vault') {
					$thirdPartyUsers = backoffice_bank_transfer_filter_users_by_branch($thirdPartyUsers, $branchId);
				} else {
					$withdrawerReassignUsers = backoffice_bank_transfer_filter_users_by_branch($withdrawerReassignUsers, $branchId);
				}
			}
			$canSeeAllBankTransfers = backoffice_user_can_bank_transfer_third_party($session);
			if (
				!$canSeeAllBankTransfers &&
				(int)($row->created_by ?? 0) !== $uid &&
				(int)($row->withdrawing_user_id ?? 0) !== $uid &&
				(int)($row->second_party_user_id ?? 0) !== $uid &&
				(int)($row->third_party_user_id ?? 0) !== $uid
			) {
				message_fail('You do not have access to this bank transfer.');
				redirect($admin_route . '/' . $plugin_route);
				exit;
			}
			require plugin_path('views/admin/bank_transfers/view_bank_transfer.php');
		}else
		if(URL(2) == 'make_payment'){
			
			$mywallet_role = new \BackOffices\User_role;
			$session = get_session();
			$uid = (int)($session->id ?? 0);
			$users = getAssignableUsersToday($uid);
			$lateReconciliationAccessNotifications = backoffice_late_reconciliation_access_rows((int)$uid, false);
			$reconciliationPaymentContext = null;
			if (!empty($_GET['recon_payment'])) {
				$ctx = get_value('reconciliation_outstanding_payment_context');
				if (is_array($ctx) && (int)($ctx['user_id'] ?? 0) === $uid && !empty($ctx['channels']) && is_array($ctx['channels'])) {
					$createdAt = (int)($ctx['created_at'] ?? 0);
					if ($createdAt <= 0 || (time() - $createdAt) <= 3600) {
						$reconciliationPaymentContext = $ctx;
					}
				}
				if (empty($reconciliationPaymentContext) && $uid > 0 && function_exists('Cfas\\cfa_get_cashdrawer_snapshot')) {
					$drawerSnapshot = \Cfas\cfa_get_cashdrawer_snapshot($uid);
					$drawerContext = !empty($drawerSnapshot)
						? ['drawer_id' => (int)($drawerSnapshot['drawer_id'] ?? 0), 'drawer_user_id' => (int)($drawerSnapshot['drawer_user_id'] ?? 0)]
						: null;
					if (!empty($drawerContext)) {
						$dbReconPay = new \Core\Database;
						$rowsEcash = $dbReconPay->query(
							"SELECT channel_key, closing_balance FROM drawer_balances WHERE drawer_id = :drawer_id AND user_id = :user_id AND channel_type = 'ecash'",
							[
								'drawer_id' => (int)$drawerContext['drawer_id'],
								'user_id' => (int)$drawerContext['drawer_user_id'],
							]
						) ?: [];
						$ecashBalances = [];
						foreach ($rowsEcash as $r) {
							$key = strtoupper(trim((string)($r->channel_key ?? '')));
							if ($key === '') {
								continue;
							}
							if (strpos($key, 'ECASH_') === 0) {
								$key = substr($key, 6);
							}
							$key = str_replace('_', '-', $key);
							$key = str_replace(' ', '-', $key);
							$key = trim($key, '-');
							if (in_array($key, ['ECASH', 'E-CASH', 'E-CASH-', 'E-CASH--', 'EZWICH', 'E-ZWICH'], true)) {
								continue;
							}
							$ecashBalances[$key] = (float)($r->closing_balance ?? 0);
						}
						$outstanding = backoffice_reconciliation_outstanding_ecash_channels($ecashBalances);
						if (!empty($outstanding)) {
							$reconciliationPaymentContext = [
								'user_id' => $uid,
								'late_access_id' => 0,
								'channels' => $outstanding,
								'created_at' => time(),
							];
						}
					}
				}
			}
			$paymentUserIds = [];
			if(is_array($users)){
				foreach($users as $assignableUser){
					$assignableId = (int)($assignableUser->id ?? 0);
					if($assignableId > 0){
						$paymentUserIds[] = $assignableId;
					}
				}
			}
			$backofficePaymentAssignmentsMap = backoffice_user_sim_assignments_map($paymentUserIds);
			require plugin_path('views/admin/payments/make_payment.php');
		}else
		if(URL(2) == 'edit_payment'){
			
			$mywallet_role = new \BackOffices\User_role;
			$row = $payment->first(['id'=>$id]);
			$users = getAssignableUsersToday();

			require plugin_path('views/admin/payments/edit_payment.php');
		}else
		if(URL(2) == 'view_payment'){
			
			//mypayments tab data
			$query = "
				    SELECT 
				        m.*, 
				        u.first_name AS user_first_name, 
				        u.last_name AS user_last_name, 
				        u.image AS user_image,
				        u_updated.first_name AS updated_by_first_name, 
				        u_updated.last_name AS updated_by_last_name, 
				        u_updated.image AS updated_by_image,
				        u_payto.first_name AS payto_first_name, 
				        u_payto.last_name AS payto_last_name, 
				        u_payto.image AS payto_image,
			            out_wallet.name AS out_wallet_name, 
			            out_wallet.network AS out_wallet_network,
			            receiving_wallet.name AS receiving_wallet_name, 
			            receiving_wallet.network AS receiving_wallet_network
				    FROM payments_backoffice AS m 
				    LEFT JOIN users AS u ON m.created_by = u.id 
				    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
				    LEFT JOIN users AS u_payto ON m.pay_to = u_payto.id 
			        LEFT JOIN mywallets AS out_wallet ON m.wallet_out_id = out_wallet.id  
			        LEFT JOIN mywallets AS receiving_wallet ON m.receiving_wallet = receiving_wallet.id  
			        WHERE m.id = :id
				";

				$row = $payment->query($query, ['id'=>$id])[0];

			require plugin_path('views/admin/payments/view_payment.php');
		}else
		if(URL(2) == 'delete'){
			$mywallet_role = new \BackOffices\User_role;
			$request = new \BackOffices\RequestClass;
			$query = "
			    SELECT 
			        m.*, 
			        u.first_name AS user_first_name, 
			        u.last_name AS user_last_name, 
			        u.image AS user_image,
			        u_updated.first_name AS updated_by_first_name, 
			        u_updated.last_name AS updated_by_last_name, 
			        u_updated.image AS updated_by_image,
			        u_assigned.first_name AS assigned_first_name, 
			        u_assigned.last_name AS assigned_last_name, 
			        u_assigned.image AS assigned_image
			    FROM requests AS m 
			    LEFT JOIN users AS u ON m.created_by = u.id 
			    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
			    LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id 
			    WHERE m.id = :id 
			    LIMIT 1";
			$tmp = $request->query($query, ['id' => $id]);
			$row = !empty($tmp) ? $tmp[0] : null;
			require plugin_path('views/admin/requests/delete.php');
		}else
		if(URL(2) == 'delete_payment'){
			$mywallet_role = new \BackOffices\User_role;
			$row = $payment->first(['id'=>$id]);
			require plugin_path('views/admin/payments/delete_payment.php');
		}else
		if(URL(2) == 'delete_commission'){
			$mywallet_role = new \BackOffices\User_role;
			$row = $commission->first(['id'=>$id]);
			require plugin_path('views/admin/commissions/delete_commission.php');
		}else
		if(URL(2) == 'transfer'){
			$query = "SELECT * FROM mywallets WHERE transaction_status =:transaction_status AND deleted = :deleted ORDER BY network ASC";

			$mywallets = $mywallet->query($query,
				['deleted'=>0, 'transaction_status'=>'Approved']
			);

			require plugin_path('views/admin/transfer.php');
		}else
		{
			$mywallet = new \BackOffices\Mywallet;
			$request = new \BackOffices\RequestClass;
			$reconciliation = new \BackOffices\Reconciliation;
			$reconciliation_summary = new \BackOffices\Reconciliation_summary;
			$sim = new \BackOffices\Sim;
			$user = new \BackOffices\User;
			$mywallet_roles_map = new \BackOffices\User_roles_map;
			$limit = 10;
			$pager = new \Core\Pager($limit);
			$offset = $pager->offset;

			$mywallet->limit = $limit;
			$mywallet->offset = $offset;
			$mywallet::$query_id = 'get-mywallet';
	
			//Request tab query data
			$query = "
			   SELECT 
				    m.id,
				    m.assigned,
				    SUM(m.balance) AS total_balance,
				    SUM(m.debit) AS total_debit,
				    SUM(m.credit) AS total_credit,
				    MAX(m.id) AS latest_wallet_id,
				    u_assigned.id AS user_id, 
				    u_assigned.first_name AS assigned_first_name, 
				    u_assigned.last_name AS assigned_last_name, 
				    u_assigned.image AS assigned_image
				FROM mywallets AS m 
				LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id
				WHERE m.transaction_status = 'Approved'
				GROUP BY m.assigned, 
				         u_assigned.first_name, u_assigned.last_name, u_assigned.image
				ORDER BY m.assigned, latest_wallet_id DESC
			";

			$statements = $mywallet->query($query);

			$query2 = "SELECT SUM(debit) AS total_debit, SUM(credit) AS total_credit, SUM(balance) AS total_balance FROM mywallets WHERE transaction_status = 'Approved'";
			$total_query = $mywallet->query($query2)[0];

			$totalStatementDebit = $total_query->total_debit ?? 0;
			$totalStatementCredit = $total_query->total_credit ?? 0;
			$totalStatementBalance = $total_query->total_balance ?? 0;

	
			//Request tab query data
			$query = "
			    SELECT 
			        m.*, 
			        u.first_name AS user_first_name, 
			        u.last_name AS user_last_name, 
			        u.image AS user_image,
			        u_updated.first_name AS updated_by_first_name, 
			        u_updated.last_name AS updated_by_last_name, 
			        u_updated.image AS updated_by_image,
		            u_assigned.first_name AS assigned_first_name, 
		            u_assigned.last_name AS assigned_last_name, 
		            u_assigned.image AS assigned_image,
		            c.category AS category_name 
			    FROM mywallets AS m 
			    LEFT JOIN users AS u ON m.created_by = u.id 
			    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
		        LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id
		        LEFT JOIN categories AS c ON m.category = c.id
		        WHERE m.assigned = $user_id  AND m.transaction_status = 'Approved'
			    ORDER BY m.id DESC
			";

			$mywallets = $mywallet->query($query);
			//dd($mywallets);
			$query2 = "SELECT sum(debit) AS total_debit, sum(credit) AS total_credit, sum(balance) AS total_balance from mywallets WHERE created_by = $user_id OR assigned = $user_id ";
			$total_query = $mywallet->query($query2)[0];

			$totalWalletDebit = $total_query->total_debit ?? 0;
			$totalWalletCredit = $total_query->total_credit ?? 0;
			$totalWalletBalance = $total_query->total_balance ?? 0;

			//Request tab data
			$request->limit = $limit;
			$request->offset = $offset;
			$request::$query_id = 'get-users';
			$requestScopeSql = user_can('is_admin') ? '' : " WHERE m.created_by = $user_id OR m.assigned = $user_id";

			$query = "
			    SELECT 
			        m.*, 
			        u.first_name AS user_first_name, 
			        u.last_name AS user_last_name, 
			        u.image AS user_image,
			        u_updated.first_name AS updated_by_first_name, 
			        u_updated.last_name AS updated_by_last_name, 
			        u_updated.image AS updated_by_image,
		            u_assigned.first_name AS assigned_first_name, 
		            u_assigned.last_name AS assigned_last_name, 
		            u_assigned.image AS assigned_image
			    FROM requests AS m 
			    LEFT JOIN users AS u ON m.created_by = u.id 
			    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
		        LEFT JOIN users AS u_assigned ON m.assigned = u_assigned.id
		        {$requestScopeSql}
			    ORDER BY m.id DESC LIMIT $limit OFFSET $offset
			";

			$requests = $request->query($query);
			//dd($requests);
			$query2 = "SELECT sum(balance) AS total_balance from requests ";
			$total_query = $request->query($query2)[0] ?? [];

			$total_Requestbalance = $total_query->total_balance ?? 0;

			 // Define the queries
			    $requestCountSql = user_can('is_admin') ? '' : ' AND (created_by = :user_id OR assigned = :user_id)';
			    $query = "SELECT COUNT(id) AS pending_reversal FROM requests WHERE (deleted IS NULL OR deleted = 0) AND LOWER(COALESCE(transaction_status,'')) = 'pending'{$requestCountSql} LIMIT 1";
			    $countReqParams = user_can('is_admin') ? [] : ['user_id' => $user_id];
			    $countReq = $request->query($query, $countReqParams);

			    // Determine total pending
			    $total_pending = 0;
			    if (!empty($countReq)) {
			        $total_pending = $countReq[0]->pending_reversal ?? 0;
			    }

			    // Format the badge
			    $countBadge = $total_pending > 0 ? (string)$total_pending : '';

			//TO & FROM BANK tab data
			$canSeeAllBankTransfers = backoffice_user_can_bank_transfer_third_party($session);
			$bankTransferWhere = $canSeeAllBankTransfers
				? 'WHERE (m.deleted IS NULL OR m.deleted = 0)'
				: 'WHERE (m.deleted IS NULL OR m.deleted = 0) AND (m.created_by = :bank_user_id OR m.withdrawing_user_id = :bank_user_id OR m.second_party_user_id = :bank_user_id OR m.third_party_user_id = :bank_user_id)';
			$bankTransferParams = $canSeeAllBankTransfers ? [] : ['bank_user_id' => (string)$user_id];
			$bankTransfers = $bankTransfer->query(
				"SELECT m.*,
				        u.first_name AS creator_first_name,
				        u.last_name AS creator_last_name,
				        uw.first_name AS withdrawer_first_name,
				        uw.last_name AS withdrawer_last_name,
				        u2.first_name AS second_party_first_name,
				        u2.last_name AS second_party_last_name,
				        u3.first_name AS third_party_first_name,
				        u3.last_name AS third_party_last_name
				 FROM bank_transfers_backoffice m
				 LEFT JOIN users u ON m.created_by = u.id
				 LEFT JOIN users uw ON m.withdrawing_user_id = uw.id
				 LEFT JOIN users u2 ON m.second_party_user_id = u2.id
				 LEFT JOIN users u3 ON m.third_party_user_id = u3.id
				 {$bankTransferWhere}
				 ORDER BY m.id DESC",
				$bankTransferParams
			) ?: [];
			$bankPendingRows = $bankTransfer->query(
				"SELECT COUNT(id) AS pending_count
				 FROM bank_transfers_backoffice m
				 {$bankTransferWhere}
				 AND LOWER(COALESCE(m.transaction_status,'')) NOT IN ('approved','rejected')",
				$bankTransferParams
			) ?: [];
			$countBankTransfers = (!empty($bankPendingRows) ? (int)($bankPendingRows[0]->pending_count ?? 0) : 0) + $commissionBankAttentionCount;
			$countBankTransfersBadge = $countBankTransfers > 0 ? (string)$countBankTransfers : '';

			//Reconciliations tab data
			$request->limit = $limit;
			$request->offset = $offset;
			$canSeeAllReconciliations = backoffice_user_can_oversee_reconciliations($session);
			$canRejectReconciliations = backoffice_user_can_reject_reconciliations($session);
			$canApproveReconciliations = backoffice_user_can_approve_reconciliations($session);
			$reconciliationLocked = false;
			$reconciliationLockMessage = '';
			if ((int)$user_id > 0 && !backoffice_user_is_admin($session)) {
				$acknowledgedPettyCash = backoffice_reconciliation_acknowledged_petty_cash((int)$user_id);
				if (!empty($acknowledgedPettyCash)) {
					$reconciliationLocked = true;
					$reconciliationLockMessage = backoffice_reconciliation_acknowledged_petty_cash_message($acknowledgedPettyCash);
				}
				$activeReconciliation = backoffice_active_reconciliation_for_day((int)$user_id);
				if (!empty($activeReconciliation)) {
					$reconciliationLocked = true;
					$reconciliationLockMessage = backoffice_reconciliation_lock_message($activeReconciliation);
				}
			}
			$reconciliationScopeSql = $canSeeAllReconciliations ? '' : 'WHERE m.created_by = :recon_user_id';
			$reconciliationScopeParams = $canSeeAllReconciliations ? [] : ['recon_user_id' => (string)$user_id];
			$lateReconciliationAccessNotifications = backoffice_late_reconciliation_access_rows((int)$user_id, false);
			$canGrantLateReconciliationAccess = backoffice_user_is_admin($session);
			$lateReconciliationAccessRows = $canGrantLateReconciliationAccess ? backoffice_unreconciled_past_cashdrawers(500) : [];
			$reconciliationMiscSelect = '0 AS misc_balance';
			$reconciliationMiscJoin = '';
			$reconciliationLateSelect = 'NULL AS late_access_id_resolved, NULL AS late_cashdrawer_date, NULL AS late_submitted_at';
			$reconciliationLateJoin = '';
			try {
				$dbReconDetail = new \Core\Database;
				if ($dbReconDetail->table_exists(['reconciliations'])) {
					$reconciliationMiscSelect = 'COALESCE(misc.balance, 0) AS misc_balance';
					$reconciliationMiscJoin = "LEFT JOIN reconciliations AS misc ON misc.session_id = m.session_id AND misc.wallet_id = 'misc'";
				}
				if ($dbReconDetail->table_exists(['reconciliation_late_access'])) {
					$summaryColumnsForLate = backoffice_table_columns($dbReconDetail, 'reconciliations_summary');
					$exactLateJoin = !empty($summaryColumnsForLate['late_access_id'])
						? "LEFT JOIN reconciliation_late_access AS la_exact ON la_exact.id = m.late_access_id"
						: "";
					$reconciliationLateSelect = "COALESCE(la_exact.id, la_fallback.id) AS late_access_id_resolved, COALESCE(la_exact.drawer_date, la_fallback.drawer_date) AS late_cashdrawer_date, COALESCE(la_exact.used_at, la_fallback.used_at) AS late_submitted_at";
					if (empty($summaryColumnsForLate['late_access_id'])) {
						$reconciliationLateSelect = "la_fallback.id AS late_access_id_resolved, la_fallback.drawer_date AS late_cashdrawer_date, la_fallback.used_at AS late_submitted_at";
					}
					$reconciliationLateJoin = "{$exactLateJoin}
					LEFT JOIN (
						SELECT la1.*
						FROM reconciliation_late_access AS la1
						INNER JOIN (
							SELECT user_id, drawer_date, MAX(id) AS max_id
							FROM reconciliation_late_access
							WHERE LOWER(COALESCE(status,'')) = 'used'
							GROUP BY user_id, drawer_date
						) la2 ON la2.max_id = la1.id
					) AS la_fallback ON la_fallback.user_id = m.created_by AND DATE(la_fallback.drawer_date) = DATE(m.date_created)";
				}
			} catch (\Throwable $e) {
				$reconciliationMiscSelect = '0 AS misc_balance';
				$reconciliationMiscJoin = '';
				$reconciliationLateSelect = 'NULL AS late_access_id_resolved, NULL AS late_cashdrawer_date, NULL AS late_submitted_at';
				$reconciliationLateJoin = '';
			}

			$querySummary = "
			    SELECT 
			        m.*, 
			        u.first_name AS user_first_name, 
			        u.last_name AS user_last_name, 
			        u.image AS user_image,
			        {$reconciliationMiscSelect},
			        {$reconciliationLateSelect}
			    FROM reconciliations_summary AS m 
			    LEFT JOIN users AS u ON m.created_by = u.id 
			    {$reconciliationMiscJoin}
			    {$reconciliationLateJoin}
			    {$reconciliationScopeSql}
			    ORDER BY m.id DESC
			";

			$reconciliations_summary = $reconciliation_summary->query($querySummary, $reconciliationScopeParams);
			//dd($reconciliations_summary);		

			//payments tab data
			$paymentScopeSql = user_can('is_admin') ? '' : " WHERE m.created_by = $user_id OR m.pay_to = $user_id";
			$query = "
				    SELECT 
				        m.*, 
				        u.first_name AS user_first_name, 
				        u.last_name AS user_last_name, 
				        u.image AS user_image,
				        u_updated.first_name AS updated_by_first_name, 
				        u_updated.last_name AS updated_by_last_name, 
				        u_updated.image AS updated_by_image,
				        u_payto.first_name AS payto_first_name, 
				        u_payto.last_name AS payto_last_name, 
				        u_payto.image AS payto_image,
			            out_wallet.name AS out_wallet_name, 
			            out_wallet.network AS out_wallet_network 
				    FROM payments_backoffice AS m 
				    LEFT JOIN users AS u ON m.created_by = u.id 
				    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
				    LEFT JOIN users AS u_payto ON m.pay_to = u_payto.id 
			        LEFT JOIN mywallets AS out_wallet ON m.wallet_out_id = out_wallet.id  
			        {$paymentScopeSql}
				    ORDER BY m.id DESC
				";

				$payments = $payment->query($query);
				$query2 = "SELECT sum(amount) AS total_amount from payments_backoffice WHERE (deleted IS NULL OR deleted = 0)";
				$total_queryP = $payment->query($query2);

				if (!empty($total_queryP)) {
					$total_payments = $total_queryP[0]->total_amount ?? 0;
				}


			 // Define the queries
			    $paymentCountSql = user_can('is_admin') ? '' : ' AND (created_by = :user_id OR pay_to = :user_id)';
			    $query = "SELECT COUNT(id) AS pending_payment FROM payments_backoffice WHERE (deleted IS NULL OR deleted = 0) AND LOWER(COALESCE(transaction_status,'')) = 'pending'{$paymentCountSql} LIMIT 1";
			    $countPayAllParams = user_can('is_admin') ? [] : ['user_id' => $user_id];
			    $countPayAll = $request->query($query, $countPayAllParams);

			    // Determine total pending
			    $total_pendingPay = 0;
			    if (!empty($countPayAll)) {
			        $total_pendingPay = $countPayAll[0]->pending_payment ?? 0;
			    }

			    // Format the badge
			    $countPayAll = $total_pendingPay > 0 ? (string)$total_pendingPay : '';

			//mypayments tab data
			$query = "
				    SELECT 
				        m.*, 
				        u.first_name AS user_first_name, 
				        u.last_name AS user_last_name, 
				        u.image AS user_image,
				        u_updated.first_name AS updated_by_first_name, 
				        u_updated.last_name AS updated_by_last_name, 
				        u_updated.image AS updated_by_image,
				        u_payto.first_name AS payto_first_name, 
				        u_payto.last_name AS payto_last_name, 
				        u_payto.image AS payto_image,
			            out_wallet.name AS out_wallet_name, 
			            out_wallet.network AS out_wallet_network 
				    FROM payments_backoffice AS m 
				    LEFT JOIN users AS u ON m.created_by = u.id 
				    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
				    LEFT JOIN users AS u_payto ON m.pay_to = u_payto.id 
			        LEFT JOIN mywallets AS out_wallet ON m.wallet_out_id = out_wallet.id  
			        WHERE m.created_by = $user_id OR m.pay_to = $user_id
				    ORDER BY m.id DESC
				";

				$mypayments = $payment->query($query);
				$query2 = "SELECT sum(amount) AS total_amount from payments_backoffice WHERE (created_by = $user_id OR pay_to = $user_id) AND (deleted IS NULL OR deleted = 0)";
				$mytotal_queryP = $payment->query($query2);

				if (!empty($mytotal_queryP)) {
					$mytotal_payments = $mytotal_queryP[0]->total_amount ?? 0;
				}

			 // Define the queries
			    $query = "SELECT COUNT(id) AS pending_payment FROM payments_backoffice WHERE (deleted IS NULL OR deleted = 0) AND LOWER(transaction_status) = :transaction_status AND (created_by = :user_id OR pay_to = :user_id) LIMIT 1";
			$countPay = $request->query($query, ['transaction_status' => 'pending', 'user_id' => $user_id]);

			    // Determine total pending
			    $mytotal_pending = 0;
			    if (!empty($countPay)) {
			        $mytotal_pending = $countPay[0]->pending_payment ?? 0;
			    }

			    // Format the badge
			    $mypayBadge = $mytotal_pending > 0 ? (string)$mytotal_pending : '';

			//mypayments tab data
			$bankCommissionMySql = backoffice_commission_can_access_bank_sims($session)
				? ''
				: ' AND ' . backoffice_commission_non_bank_sql_clause('m');
			$query = "
				    SELECT 
				        m.*, 
				        u.first_name AS user_first_name, 
				        u.last_name AS user_last_name, 
				        u.image AS user_image,
				        u_updated.first_name AS updated_by_first_name, 
				        u_updated.last_name AS updated_by_last_name, 
				        u_updated.image AS updated_by_image,
				        payto.first_name AS payto_first_name, 
				        payto.last_name AS payto_last_name, 
				        payto.image AS payto_image
				    FROM commissions AS m 
				    LEFT JOIN users AS u ON m.created_by = u.id 
				    LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
				    LEFT JOIN users AS payto ON m.pay_to = payto.id 
			        WHERE (m.created_by = $user_id OR COALESCE(m.assigned, m.pay_to) = $user_id) AND m.bulk_parent_id IS NULL{$bankCommissionMySql}
				    ORDER BY m.id DESC
				";

				$commissions = $commission->query($query);
				$query2 = "SELECT sum(CASE WHEN LOWER(TRIM(COALESCE(transaction_status,''))) <> 'rejected' THEN amount ELSE 0 END) AS total_amount from commissions WHERE (created_by = $user_id OR COALESCE(assigned, pay_to) = $user_id) AND bulk_parent_id IS NULL AND (deleted IS NULL OR deleted = 0)" . ($bankCommissionMySql !== '' ? str_replace('m.', '', $bankCommissionMySql) : '');
				$query2 = $commission->query($query2);
				//dd($commissions);
				
				if (!empty($query2)) {
					$total_commissions = $query2[0]->total_amount ?? 0;
				}

				// Define the queries
			    $query = "SELECT COUNT(id) AS pending_com FROM commissions WHERE (deleted IS NULL OR deleted = 0) AND LOWER(COALESCE(transaction_status,'')) = 'pending' AND (created_by = :created_by OR COALESCE(assigned, pay_to) = :pay_to) AND bulk_parent_id IS NULL" . ($bankCommissionMySql !== '' ? str_replace('m.', '', $bankCommissionMySql) : '') . " LIMIT 1";
			    $countComissions = $commission->query($query, ['created_by' => $user_id, 'pay_to' => $user_id]);

			    // Determine total pending
			    $total_pendingCom = 0;
			    if (!empty($countComissions)) {
			        $total_pendingCom = $countComissions[0]->pending_com ?? 0;
			    }

			    $overdueCommissionAssignments = backoffice_overdue_commission_assignments((int)$user_id);
			    $overdueCommissionCount = count($overdueCommissionAssignments);
			    $overdueCommissionMessage = backoffice_overdue_commission_message($overdueCommissionAssignments);

			    // Format the badge
			    $countComissionsValue = $total_pendingCom + $overdueCommissionCount + $commissionBankAttentionCount;
			    $countComissions = $countComissionsValue > 0 ? (string)$countComissionsValue : '';

			    // agent_services tab data
			    $dbAgent = new \Core\Database;
			    $canSeeAllAgentServices = user_can('is_admin') || (function_exists('backoffice_user_can_bank_transfer_third_party') && backoffice_user_can_bank_transfer_third_party($session));
			$agentScopeSql = $canSeeAllAgentServices ? '' : " AND (m.creator_id = :user_id OR m.assigned_to = :user_id OR m.processor_id = :user_id OR m.approver_id = :user_id)";
			$agentServicePage = max(1, (int)($_GET['page'] ?? 1));
$agentServicePerPage = max(5, min(100, (int)($_GET['per_page'] ?? 10)));
		$agentServiceOffset = ($agentServicePage - 1) * $agentServicePerPage;
		$agentServiceSearch = trim((string)($_GET['search'] ?? ''));
		$agentServiceStatus = trim((string)($_GET['status'] ?? ''));
		$agentServiceProvider = trim((string)($_GET['provider'] ?? ''));
		$agentServiceUser = (int)($_GET['user'] ?? 0);
		$agentServiceStartDate = trim((string)($_GET['start_date'] ?? ''));
		$agentServiceEndDate = trim((string)($_GET['end_date'] ?? ''));

			$agentWhere = ['(m.deleted IS NULL OR m.deleted = 0)'];
			$agentParams = [];
			if (!$canSeeAllAgentServices) {
				$agentWhere[] = '(m.creator_id = :user_id OR m.assigned_to = :user_id OR m.processor_id = :user_id OR m.approver_id = :user_id)';
				$agentParams['user_id'] = $user_id;
			}
			if ($agentServiceSearch !== '') {
				$agentWhere[] = "(
					m.id LIKE :search OR
					m.customer_type LIKE :search OR
					m.service_type LIKE :search OR
					m.provider LIKE :search OR
					m.paying_network LIKE :search OR
					m.receiving_network LIKE :search OR
					m.agent_name LIKE :search OR
					m.agent_contact LIKE :search OR
					m.business_name LIKE :search OR
					m.agent_number LIKE :search OR
					m.status LIKE :search
				)";
				$agentParams['search'] = '%' . $agentServiceSearch . '%';
			}
			if ($agentServiceStatus !== '') {
				$agentWhere[] = 'LOWER(m.status) = :status';
				$agentParams['status'] = strtolower($agentServiceStatus);
			}
			if ($agentServiceProvider !== '') {
				$agentWhere[] = "(
					m.provider LIKE :provider OR
					m.paying_network LIKE :provider OR
					m.receiving_network LIKE :provider
				)";
				$agentParams['provider'] = '%' . $agentServiceProvider . '%';
			}
			if ($agentServiceUser > 0) {
				$agentWhere[] = "(
					m.creator_id = :filter_user OR
					m.assigned_to = :filter_user OR
					m.updated_by = :filter_user
				)";
				$agentParams['filter_user'] = $agentServiceUser;
			}
			if ($agentServiceStartDate !== '') {
				$agentWhere[] = 'DATE(m.date_created) >= :start_date';
				$agentParams['start_date'] = $agentServiceStartDate;
			}
			if ($agentServiceEndDate !== '') {
				$agentWhere[] = 'DATE(m.date_created) <= :end_date';
				$agentParams['end_date'] = $agentServiceEndDate;
			}

			$agentWhereSql = $agentWhere ? ('WHERE ' . implode(' AND ', $agentWhere)) : '';
			$query = "
			    SELECT m.*, 
			           c.first_name as creator_first_name, c.last_name as creator_last_name, 
			           a.first_name as assigned_first_name, a.last_name as assigned_last_name
			    FROM agent_services m
			    LEFT JOIN users c ON m.creator_id = c.id
			    LEFT JOIN users a ON m.assigned_to = a.id
			    {$agentWhereSql}
			    ORDER BY m.id DESC
			    LIMIT {$agentServicePerPage} OFFSET {$agentServiceOffset}
			";
			$agent_services_rows = $dbAgent->query($query, $agentParams);

			$query = "SELECT COUNT(id) AS total_rows FROM agent_services m {$agentWhereSql}";
			$countAgentRecords = $dbAgent->query($query, $agentParams);
			$totalRecords = !empty($countAgentRecords) ? (int)($countAgentRecords[0]->total_rows ?? 0) : 0;
			$totalPages = $totalRecords > 0 ? (int)ceil($totalRecords / $agentServicePerPage) : 1;
			$page = $agentServicePage;
			$per_page = $agentServicePerPage;
			$total_records = $totalRecords;

			$agentServiceNetworks = $dbAgent->query("SELECT DISTINCT TRIM(network) AS network FROM (
				SELECT provider AS network FROM agent_services
				UNION
				SELECT paying_network FROM agent_services
				UNION
				SELECT receiving_network FROM agent_services
			) AS nets WHERE network IS NOT NULL AND TRIM(network) != '' ORDER BY network ASC") ?: [];

			$agentServiceUsers = $dbAgent->query("SELECT DISTINCT u.id, u.first_name, u.last_name FROM users u JOIN (
				SELECT creator_id AS uid FROM agent_services
				UNION
				SELECT assigned_to FROM agent_services
				UNION
				SELECT updated_by FROM agent_services
			) ids ON u.id = ids.uid WHERE u.id > 0 ORDER BY u.first_name ASC, u.last_name ASC") ?: [];

		$pendingAgentWhereSql = $agentWhereSql
			? ($agentWhereSql . " AND COALESCE(m.stage, 1) < 4 AND LOWER(COALESCE(m.status, '')) NOT IN ('completed', 'rejected')")
			: "WHERE COALESCE(m.stage, 1) < 4 AND LOWER(COALESCE(m.status, '')) NOT IN ('completed', 'rejected')";
		$countAgentAll = $dbAgent->query("SELECT COUNT(id) AS pending FROM agent_services m {$pendingAgentWhereSql}", $agentParams);
		$total_pendingAgent = 0;
		if (!empty($countAgentAll)) {
			$total_pendingAgent = $countAgentAll[0]->pending ?? 0;
		}
		$countAgentServicesBadge = $total_pendingAgent > 0 ? (string)$total_pendingAgent : '';

		require plugin_path('views/admin/list.php');
	}

}
});

add_filter('after_query',function($data){

	
	if(empty($data['result']))
		return $data;

	if($data['query_id'] == 'get-users')
	{
		$role_map = new \BackOffices\User_roles_map;
		foreach ($data['result'] as $key => $row) {
			
			$query = "select * from user_roles where disabled = 0 && id in (select role_id from user_roles_map where disabled = 0 && user_id = :user_id)";
			$roles = $role_map->query($query,['user_id'=>$row->id]);
			if($roles)
				$data['result'][$key]->roles = array_column($roles, 'role');
			
			/** get mywallet's roles **/
			$mywallet_roles_map = new \BackOffices\User_roles_map;
				
			$role_ids = $mywallet_roles_map->where(['user_id'=>$row->id,'disabled'=>0]);
			if($role_ids)
				$data['result'][$key]->role_ids = array_column($role_ids, 'role_id');

		}
	}

	return $data;
});
