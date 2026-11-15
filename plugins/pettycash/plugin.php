<?php

use function BackOfficeCashdrawer\calculateTotal;
use function BackOfficeCashdrawer\checkCashDenominationCountSufficiencyCurrency;
use function BackOfficeCashdrawer\checkCashDenominationSufficiencyCurrency;
use function BackOfficeCashdrawer\getActiveDrawer;
use function BackOfficeCashdrawer\normalizeDenominations;
use function BackOfficeCashdrawer\syncCashMovementCurrency;

/**
 * Plugin name: Petty Cash
 * Description: Manager-assigned petty cash purchase workflow
 */

$pettyCashBridgePath = realpath(__DIR__ . '/../backoffice/helpers/cashdrawer-bridge.php');
if ($pettyCashBridgePath && file_exists($pettyCashBridgePath)) {
	require_once $pettyCashBridgePath;
	\BackOfficeCashdrawer\init();
}

set_value([
	'admin_route'  => 'admin',
	'plugin_route' => 'pettycash',
	'tables'       => [
		'pettycash_table' => 'petty_cash_transactions',
	],
	'optional_tables' => [],
]);

if (!function_exists('pettycash_role_names')) {
	function pettycash_role_names($session = null): array
	{
		if ($session === null) {
			$session = get_session();
		}

		$roles = $session->roles ?? [];
		if (!is_array($roles)) {
			$roles = [$roles];
		}

		return array_values(array_filter(array_map(function ($role) {
			return strtolower(trim((string)$role));
		}, $roles)));
	}
}

if (!function_exists('pettycash_user_is_privileged')) {
	function pettycash_user_is_privileged($session = null): bool
	{
		if ($session === null) {
			$session = get_session();
		}

		try {
			$ses = new \Core\Session;
			if ($ses->is_admin()) {
				return true;
			}
		} catch (\Throwable $e) {
			// Ignore session admin lookup failures.
		}

		$roles = pettycash_role_names($session);
		if (in_array('admin', $roles, true) || in_array('is_admin', $roles, true)) {
			return true;
		}

		$managerRoles = [
			'manager',
			'is_manager',
			'general manager',
			'general_manager',
			'generalmanager',
			'gm',
		];

		return (bool)array_intersect($roles, $managerRoles);
	}
}

if (!function_exists('pettycash_currency_decimals')) {
	function pettycash_currency_decimals(string $currency): int
	{
		return strtoupper(trim($currency)) === 'CFA' ? 0 : 2;
	}
}

if (!function_exists('pettycash_resolve_admin_route')) {
	function pettycash_resolve_admin_route(?string $fallback = null): string
	{
		$route = trim((string)page());
		if ($route !== '') {
			return $route;
		}

		$vars = get_value();
		$fallback = $fallback ?? (string)($vars['admin_route'] ?? 'admin');
		return trim((string)$fallback) !== '' ? (string)$fallback : 'admin';
	}
}

if (!function_exists('pettycash_format_amount')) {
	function pettycash_format_amount($amount, string $currency = 'GHS'): string
	{
		$currency = strtoupper(trim($currency)) === 'CFA' ? 'CFA' : 'GHS';
		$decimals = pettycash_currency_decimals($currency);
		return $currency . ' ' . number_format((float)$amount, $decimals);
	}
}

if (!function_exists('pettycash_make_reference')) {
	function pettycash_make_reference(): string
	{
		try {
			return 'PC-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
		} catch (\Throwable $e) {
			return 'PC-' . date('Ymd') . '-' . strtoupper(substr(md5((string)microtime(true) . (string)rand(1, 999999)), 0, 6));
		}
	}
}

if (!function_exists('pettycash_categories')) {
	function pettycash_categories(): array
	{
		return [
			'GENERAL' => 'GENERAL',
			'UTILITIES' => 'UTILITIES',
			'MAINTENANCE' => 'MAINTENANCE',
			'OPERATIONAL' => 'OPERATIONAL',
			'LOGISTICS' => 'LOGISTICS',
			'RENT' => 'RENT',
			'MARKETING' => 'MARKETING',
		];
	}
}

if (!function_exists('pettycash_normalize_category')) {
	function pettycash_normalize_category($value): string
	{
		$category = strtoupper(trim((string)$value));
		return array_key_exists($category, pettycash_categories()) ? $category : '';
	}
}

if (!function_exists('pettycash_status_label')) {
	function pettycash_status_label($status): string
	{
		return match (strtolower(trim((string)$status))) {
			'pending_ack' => 'Pending Acknowledgement',
			'acknowledged' => 'Acknowledged',
			'completed' => 'Completed',
			'rejected' => 'Rejected',
			default => ucfirst((string)$status),
		};
	}
}

if (!function_exists('pettycash_status_badge_class')) {
	function pettycash_status_badge_class($status): string
	{
		return match (strtolower(trim((string)$status))) {
			'pending_ack' => 'badge bg-warning text-dark',
			'acknowledged' => 'badge bg-info text-dark',
			'completed' => 'badge bg-success',
			'rejected' => 'badge bg-danger',
			default => 'badge bg-secondary',
		};
	}
}

if (!function_exists('pettycash_normalize_items')) {
	function pettycash_normalize_items($rawPayload, string $currency = 'GHS'): array
	{
		$items = [];
		if (is_string($rawPayload)) {
			$decoded = json_decode($rawPayload, true);
			$items = is_array($decoded) ? $decoded : [];
		} elseif (is_array($rawPayload)) {
			$items = $rawPayload;
		}

		$currency = strtoupper(trim($currency)) === 'CFA' ? 'CFA' : 'GHS';
		$decimals = pettycash_currency_decimals($currency);
		$normalized = [];

		foreach ($items as $item) {
			if (!is_array($item)) {
				continue;
			}

			$name = trim((string)($item['item_name'] ?? $item['name'] ?? ''));
			$amount = (float)($item['amount'] ?? 0);
			$note = trim((string)($item['note'] ?? ''));

			if ($name === '' || $amount <= 0) {
				continue;
			}

			$normalized[] = [
				'item_name' => $name,
				'amount' => round($amount, $decimals),
				'note' => $note,
			];
		}

		return $normalized;
	}
}

if (!function_exists('pettycash_calculate_items_total')) {
	function pettycash_calculate_items_total(array $items, string $currency = 'GHS'): float
	{
		$decimals = pettycash_currency_decimals($currency);
		$total = 0.0;
		foreach ($items as $item) {
			$total += (float)($item['amount'] ?? 0);
		}
		return round($total, $decimals);
	}
}

if (!function_exists('pettycash_decode_items')) {
	function pettycash_decode_items($rawPayload, string $currency = 'GHS'): array
	{
		return pettycash_normalize_items($rawPayload, $currency);
	}
}

if (!function_exists('pettycash_create_table_if_missing')) {
	function pettycash_create_table_if_missing(\Core\Database $db): void
	{
		if ($db->table_exists(['petty_cash_transactions'])) {
			return;
		}

		$db->query(
			"CREATE TABLE IF NOT EXISTS petty_cash_transactions (
				id INT UNSIGNED NOT NULL AUTO_INCREMENT,
				reference VARCHAR(80) NOT NULL,
				title VARCHAR(150) NULL,
				description TEXT NULL,
				currency VARCHAR(10) NOT NULL DEFAULT 'GHS',
				category VARCHAR(50) NULL,
				budget_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
				items_json LONGTEXT NULL,
				assigned_user_id INT UNSIGNED NULL,
				status VARCHAR(40) NOT NULL DEFAULT 'pending_ack',
				paid_denos_json LONGTEXT NULL,
				spent_amount DECIMAL(12,2) NULL DEFAULT NULL,
				change_amount DECIMAL(12,2) NULL DEFAULT NULL,
				change_denos_json LONGTEXT NULL,
				voucher_photo VARCHAR(255) NULL,
				completion_note TEXT NULL,
				cashdrawer_id INT UNSIGNED NULL,
				drawer_user_id INT UNSIGNED NULL,
				change_cashdrawer_id INT UNSIGNED NULL,
				change_drawer_user_id INT UNSIGNED NULL,
				acknowledged_by INT UNSIGNED NULL,
				acknowledged_at DATETIME NULL,
				completed_by INT UNSIGNED NULL,
				completed_at DATETIME NULL,
				created_by INT UNSIGNED NULL,
				updated_by INT UNSIGNED NULL,
				deleted TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
				date_created DATETIME NULL,
				date_updated DATETIME NULL,
				date_deleted DATETIME NULL,
				PRIMARY KEY (id),
				KEY pettycash_reference_idx (reference),
				KEY pettycash_status_idx (status),
				KEY pettycash_category_idx (category),
				KEY pettycash_assigned_idx (assigned_user_id),
				KEY pettycash_created_idx (created_by),
				KEY pettycash_date_created_idx (date_created)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
		);
	}
}

if (!function_exists('pettycash_ensure_schema')) {
	function pettycash_ensure_schema(): void
	{
		static $done = false;
		if ($done) {
			return;
		}

		$db = new \Core\Database;
		pettycash_create_table_if_missing($db);
		if (!$db->table_exists(['petty_cash_transactions'])) {
			$done = true;
			return;
		}

		try {
			$schema = $db->query("SHOW COLUMNS FROM petty_cash_transactions") ?: [];
			$existing = [];
			foreach ($schema as $column) {
				$existing[strtolower((string)($column->Field ?? ''))] = true;
			}

			$alters = [];
			$columns = [
				'reference' => "ADD COLUMN reference VARCHAR(80) NOT NULL DEFAULT '' AFTER id",
				'title' => "ADD COLUMN title VARCHAR(150) NULL AFTER reference",
				'description' => "ADD COLUMN description TEXT NULL AFTER title",
				'currency' => "ADD COLUMN currency VARCHAR(10) NOT NULL DEFAULT 'GHS' AFTER description",
				'category' => "ADD COLUMN category VARCHAR(50) NULL AFTER currency",
				'budget_amount' => "ADD COLUMN budget_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER category",
				'items_json' => "ADD COLUMN items_json LONGTEXT NULL AFTER budget_amount",
				'assigned_user_id' => "ADD COLUMN assigned_user_id INT UNSIGNED NULL AFTER items_json",
				'status' => "ADD COLUMN status VARCHAR(40) NOT NULL DEFAULT 'pending_ack' AFTER assigned_user_id",
				'paid_denos_json' => "ADD COLUMN paid_denos_json LONGTEXT NULL AFTER status",
				'spent_amount' => "ADD COLUMN spent_amount DECIMAL(12,2) NULL DEFAULT NULL AFTER paid_denos_json",
				'change_amount' => "ADD COLUMN change_amount DECIMAL(12,2) NULL DEFAULT NULL AFTER spent_amount",
				'change_denos_json' => "ADD COLUMN change_denos_json LONGTEXT NULL AFTER change_amount",
				'voucher_photo' => "ADD COLUMN voucher_photo VARCHAR(255) NULL AFTER change_denos_json",
				'completion_note' => "ADD COLUMN completion_note TEXT NULL AFTER voucher_photo",
				'cashdrawer_id' => "ADD COLUMN cashdrawer_id INT UNSIGNED NULL AFTER completion_note",
				'drawer_user_id' => "ADD COLUMN drawer_user_id INT UNSIGNED NULL AFTER cashdrawer_id",
				'change_cashdrawer_id' => "ADD COLUMN change_cashdrawer_id INT UNSIGNED NULL AFTER drawer_user_id",
				'change_drawer_user_id' => "ADD COLUMN change_drawer_user_id INT UNSIGNED NULL AFTER change_cashdrawer_id",
				'acknowledged_by' => "ADD COLUMN acknowledged_by INT UNSIGNED NULL AFTER change_drawer_user_id",
				'acknowledged_at' => "ADD COLUMN acknowledged_at DATETIME NULL AFTER acknowledged_by",
				'completed_by' => "ADD COLUMN completed_by INT UNSIGNED NULL AFTER acknowledged_at",
				'completed_at' => "ADD COLUMN completed_at DATETIME NULL AFTER completed_by",
				'created_by' => "ADD COLUMN created_by INT UNSIGNED NULL AFTER completed_at",
				'updated_by' => "ADD COLUMN updated_by INT UNSIGNED NULL AFTER created_by",
				'deleted' => "ADD COLUMN deleted TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER updated_by",
				'date_created' => "ADD COLUMN date_created DATETIME NULL AFTER deleted",
				'date_updated' => "ADD COLUMN date_updated DATETIME NULL AFTER date_created",
				'date_deleted' => "ADD COLUMN date_deleted DATETIME NULL AFTER date_updated",
			];

			foreach ($columns as $name => $sql) {
				if (empty($existing[$name])) {
					$alters[] = $sql;
				}
			}

			if (!empty($alters)) {
				$db->query("ALTER TABLE petty_cash_transactions " . implode(', ', $alters));
			}

			$indexes = [
				"ALTER TABLE petty_cash_transactions ADD KEY pettycash_reference_idx (reference)",
				"ALTER TABLE petty_cash_transactions ADD KEY pettycash_status_idx (status)",
				"ALTER TABLE petty_cash_transactions ADD KEY pettycash_category_idx (category)",
				"ALTER TABLE petty_cash_transactions ADD KEY pettycash_assigned_idx (assigned_user_id)",
				"ALTER TABLE petty_cash_transactions ADD KEY pettycash_created_idx (created_by)",
				"ALTER TABLE petty_cash_transactions ADD KEY pettycash_date_created_idx (date_created)",
			];
			foreach ($indexes as $sql) {
				try {
					$db->query($sql);
				} catch (\Throwable $e) {
					// Ignore duplicate index creation attempts.
				}
			}
		} catch (\Throwable $e) {
			// Ignore runtime schema drift failures.
		}

		$done = true;
	}
}

if (!function_exists('pettycash_get_assignable_users')) {
	function pettycash_get_assignable_users(int $excludeUserId = 0): array
	{
		if (function_exists('BackOfficeCashdrawer\\getAssignableUsersToday')) {
			try {
				return \BackOfficeCashdrawer\getAssignableUsersToday($excludeUserId);
			} catch (\Throwable $e) {
				// Fall through to manual query.
			}
		}

		$db = new \Core\Database;
		$params = [];
		$excludeSql = '';
		if ($excludeUserId > 0) {
			$excludeSql = ' AND u.id <> :exclude_user_id';
			$params['exclude_user_id'] = $excludeUserId;
		}

		return $db->query(
			"SELECT DISTINCT u.id, u.first_name, u.last_name, u.email, u.image
			 FROM users u
			 INNER JOIN cashdrawers c ON c.user_id = u.id
			 WHERE (u.deleted = 0 OR u.deleted IS NULL){$excludeSql}
			 ORDER BY u.first_name ASC, u.last_name ASC, u.id ASC",
			$params
		) ?: [];
	}
}

if (!function_exists('pettycash_find_row')) {
	function pettycash_find_row(int $id)
	{
		if ($id <= 0) {
			return null;
		}

		$db = new \Core\Database;
		$rows = $db->query(
			"SELECT p.*,
			        creator.first_name AS creator_first_name,
			        creator.last_name AS creator_last_name,
			        creator.image AS creator_image,
			        assignee.first_name AS assignee_first_name,
			        assignee.last_name AS assignee_last_name,
			        assignee.image AS assignee_image,
			        ack.first_name AS acknowledged_first_name,
			        ack.last_name AS acknowledged_last_name,
			        done.first_name AS completed_first_name,
			        done.last_name AS completed_last_name
			 FROM petty_cash_transactions p
			 LEFT JOIN users creator ON creator.id = p.created_by
			 LEFT JOIN users assignee ON assignee.id = p.assigned_user_id
			 LEFT JOIN users ack ON ack.id = p.acknowledged_by
			 LEFT JOIN users done ON done.id = p.completed_by
			 WHERE p.id = :id
			 AND (p.deleted = 0 OR p.deleted IS NULL)
			 LIMIT 1",
			['id' => $id]
		) ?: [];

		return !empty($rows) ? $rows[0] : null;
	}
}

if (!function_exists('pettycash_build_filters')) {
	function pettycash_build_filters(array $input, bool $isPrivileged, int $userId): array
	{
		$q = trim((string)($input['q'] ?? ''));
		$status = strtolower(trim((string)($input['status'] ?? '')));
		$category = pettycash_normalize_category($input['category'] ?? '');
		$dateFrom = trim((string)($input['date_from'] ?? ''));
		$dateTo = trim((string)($input['date_to'] ?? ''));

		$allowedStatuses = ['pending_ack', 'acknowledged', 'completed', 'rejected'];
		if (!in_array($status, $allowedStatuses, true)) {
			$status = '';
		}

		$where = "WHERE (p.deleted = 0 OR p.deleted IS NULL)";
		$params = [];

		if (!$isPrivileged) {
			$where .= " AND (p.assigned_user_id = :assigned_user_id OR p.acknowledged_by = :acknowledged_user_id)";
			$params['assigned_user_id'] = $userId;
			$params['acknowledged_user_id'] = $userId;
		}

		if ($q !== '') {
			$where .= " AND (
				p.reference LIKE :q
				OR p.title LIKE :q
				OR p.description LIKE :q
				OR p.status LIKE :q
				OR p.category LIKE :q
				OR creator.first_name LIKE :q
				OR creator.last_name LIKE :q
				OR assignee.first_name LIKE :q
				OR assignee.last_name LIKE :q
			)";
			$params['q'] = '%' . $q . '%';
		}

		if ($status !== '') {
			$where .= " AND LOWER(COALESCE(p.status, 'pending_ack')) = :status";
			$params['status'] = $status;
		}

		if ($category !== '') {
			$where .= " AND p.category = :category";
			$params['category'] = $category;
		}

		if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
			$where .= " AND DATE(p.date_created) >= :date_from";
			$params['date_from'] = $dateFrom;
		}

		if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
			$where .= " AND DATE(p.date_created) <= :date_to";
			$params['date_to'] = $dateTo;
		}

		return [
			'q' => $q,
			'status' => $status,
			'category' => $category,
			'date_from' => $dateFrom,
			'date_to' => $dateTo,
			'where' => $where,
			'params' => $params,
		];
	}
}

if (!function_exists('pettycash_items_summary')) {
	function pettycash_items_summary($rawPayload, string $currency = 'GHS'): array
	{
		$items = pettycash_decode_items($rawPayload, $currency);
		return [
			'items' => $items,
			'total' => pettycash_calculate_items_total($items, $currency),
			'count' => count($items),
		];
	}
}

if (!function_exists('pettycash_user_can_access_row')) {
	function pettycash_user_can_access_row($row, int $userId, $session = null): bool
	{
		if (!$row || $userId <= 0) {
			return false;
		}

		if (pettycash_user_is_privileged($session)) {
			return true;
		}

		return (int)($row->assigned_user_id ?? 0) === $userId;
	}
}

if (!function_exists('pettycash_assigned_count')) {
	function pettycash_assigned_count(int $userId): int
	{
		if ($userId <= 0) {
			return 0;
		}

		$db = new \Core\Database;
		$rows = $db->query(
			"SELECT COUNT(id) AS c
			 FROM petty_cash_transactions
			 WHERE (deleted = 0 OR deleted IS NULL)
			 AND (assigned_user_id = :user_id OR acknowledged_by = :acknowledged_user_id)",
			['user_id' => $userId, 'acknowledged_user_id' => $userId]
		) ?: [];

		return (int)($rows[0]->c ?? 0);
	}
}

if (!function_exists('pettycash_nav_count')) {
	function pettycash_nav_count(int $userId, bool $isPrivileged): int
	{
		$db = new \Core\Database;
		if (!$db->table_exists(['petty_cash_transactions'])) {
			return 0;
		}

		if ($isPrivileged) {
			$rows = $db->query(
				"SELECT COUNT(id) AS c
				 FROM petty_cash_transactions
				 WHERE (deleted = 0 OR deleted IS NULL)
				 AND LOWER(COALESCE(status, 'pending_ack')) IN ('pending_ack', 'acknowledged')"
			) ?: [];
			return (int)($rows[0]->c ?? 0);
		}

		$rows = $db->query(
			"SELECT COUNT(id) AS c
			 FROM petty_cash_transactions
			 WHERE (deleted = 0 OR deleted IS NULL)
			 AND (assigned_user_id = :user_id OR acknowledged_by = :user_id_ack)
			 AND LOWER(COALESCE(status, 'pending_ack')) IN ('pending_ack', 'acknowledged')",
			['user_id' => $userId, 'user_id_ack' => $userId]
		) ?: [];

		return (int)($rows[0]->c ?? 0);
	}
}

pettycash_ensure_schema();

add_filter('permissions', function ($permissions) {
	$permissions[] = 'view_pettycash';
	$permissions[] = 'add_pettycash';
	$permissions[] = 'edit_pettycash';
	$permissions[] = 'delete_pettycash';
	$permissions[] = 'view_pettycash_details';
	$permissions[] = 'acknowledge_pettycash';
	$permissions[] = 'complete_pettycash';
	return $permissions;
});

add_filter('user_permissions', function ($permissions) {
	try {
		$session = get_session();
		$userId = (int)($session->id ?? 0);
		if ($userId <= 0) {
			return $permissions;
		}

		if (pettycash_user_is_privileged($session)) {
			return array_values(array_unique(array_merge($permissions, [
				'view_pettycash',
				'add_pettycash',
				'edit_pettycash',
				'delete_pettycash',
				'view_pettycash_details',
				'acknowledge_pettycash',
				'complete_pettycash',
			])));
		}

		if (pettycash_assigned_count($userId) > 0) {
			$permissions[] = 'view_pettycash';
			$permissions[] = 'view_pettycash_details';
			$permissions[] = 'acknowledge_pettycash';
			$permissions[] = 'complete_pettycash';
		}
	} catch (\Throwable $e) {
		// Ignore dynamic petty cash permission failures.
	}

	return array_values(array_unique($permissions));
});

add_filter('basic-admin_before_admin_links', function ($links) {
	$vars = get_value();
	$session = get_session();
	$userId = (int)($session->id ?? 0);
	$isPrivileged = pettycash_user_is_privileged($session);
	$adminRoute = pettycash_resolve_admin_route((string)($vars['admin_route'] ?? 'admin'));

	if (!$isPrivileged && pettycash_assigned_count($userId) <= 0) {
		return $links;
	}

	if (!user_can('view_pettycash') && !$isPrivileged) {
		return $links;
	}

	$obj = (object)[];
	$obj->title = 'Petty Cash';
	$obj->id = 'view_pettycash';
	$obj->link = ROOT . '/' . $adminRoute . '/' . $vars['plugin_route'];
	$obj->icon = 'fa-solid fa-wallet';
	$obj->parent = 0;
	$count = pettycash_nav_count($userId, $isPrivileged);
	$obj->count = $count > 0 ? (string)$count : '';
	$links[] = $obj;

	return $links;
});

add_action('controller', function () {
	$req = new \Core\Request;
	$vars = get_value();
	$admin_route = pettycash_resolve_admin_route((string)($vars['admin_route'] ?? 'admin'));
	$plugin_route = $vars['plugin_route'] ?? 'pettycash';
	set_value('admin_route', $admin_route);

	$seg1 = URL(1) ?? '';
	$seg2 = URL(2) ?? '';
	$seg3 = URL(3) ?? '';
	if ($seg1 !== $plugin_route && $seg2 !== $plugin_route) {
		return;
	}

	$action = null;
	if ($seg2 === 'ajax_list' || $seg3 === 'ajax_list') $action = 'ajax_list';
	if ($seg2 === 'ajax_analytics' || $seg3 === 'ajax_analytics') $action = 'ajax_analytics';
	if ($seg2 === 'export_pdf' || $seg3 === 'export_pdf') $action = 'export_pdf';

	if (in_array($action, ['ajax_list', 'ajax_analytics'], true)) {
		while (ob_get_level() > 0) {
			ob_end_clean();
		}
		ini_set('display_errors', '0');
		error_reporting(E_ERROR | E_PARSE);
		header('Content-Type: application/json; charset=utf-8');
	}

	if ($action !== null) {
		$session = get_session();
		$userId = (int)($session->id ?? 0);
		$isPrivileged = pettycash_user_is_privileged($session);

		if (!$isPrivileged && !user_can('view_pettycash')) {
			if ($action === 'export_pdf') {
				echo '<div class="alert alert-danger text-center">Access denied.</div>';
			} else {
				http_response_code(403);
				echo json_encode(['success' => false, 'message' => 'Unauthorized']);
			}
			exit;
		}

		$filters = pettycash_build_filters($_GET, $isPrivileged, $userId);
		$where = (string)($filters['where'] ?? '');
		$params = (array)($filters['params'] ?? []);
		$db = new \Core\Database;
		$joinSql = "
			FROM petty_cash_transactions p
			LEFT JOIN users creator ON creator.id = p.created_by
			LEFT JOIN users assignee ON assignee.id = p.assigned_user_id
		";

		if ($action === 'ajax_analytics') {
			try {
				$sumRow = $db->query(
					"SELECT COUNT(*) AS cnt, SUM(p.budget_amount) AS total, AVG(p.budget_amount) AS avg_amt {$joinSql} {$where}",
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
						SUM(CASE WHEN LOWER(COALESCE(p.status, 'pending_ack'))='pending_ack' THEN 1 ELSE 0 END) AS pending_ack_count,
						SUM(CASE WHEN LOWER(COALESCE(p.status, 'pending_ack'))='acknowledged' THEN 1 ELSE 0 END) AS acknowledged_count,
						SUM(CASE WHEN LOWER(COALESCE(p.status, 'pending_ack'))='completed' THEN 1 ELSE 0 END) AS completed_count,
						SUM(CASE WHEN LOWER(COALESCE(p.status, 'pending_ack'))='rejected' THEN 1 ELSE 0 END) AS rejected_count,
						SUM(CASE WHEN LOWER(COALESCE(p.status, 'pending_ack'))='pending_ack' THEN p.budget_amount ELSE 0 END) AS pending_ack_amount,
						SUM(CASE WHEN LOWER(COALESCE(p.status, 'pending_ack'))='acknowledged' THEN p.budget_amount ELSE 0 END) AS acknowledged_amount,
						SUM(CASE WHEN LOWER(COALESCE(p.status, 'pending_ack'))='completed' THEN p.budget_amount ELSE 0 END) AS completed_amount,
						SUM(CASE WHEN LOWER(COALESCE(p.status, 'pending_ack'))='rejected' THEN p.budget_amount ELSE 0 END) AS rejected_amount
					{$joinSql} {$where}",
					$params
				) ?: [];
				$srow = !empty($rowsStatus) ? $rowsStatus[0] : (object)[];

				$amountRows = $db->query(
					"SELECT p.budget_amount AS amount {$joinSql} {$where} ORDER BY p.budget_amount ASC",
					$params
				) ?: [];
				$n = count($amountRows);
				if ($n > 0) {
					$mid = (int)floor(($n - 1) / 2);
					if ($n % 2 === 1) {
						$summary->median_amount = (float)($amountRows[$mid]->amount ?? 0);
					} else {
						$a = (float)($amountRows[$mid]->amount ?? 0);
						$b = (float)($amountRows[$mid + 1]->amount ?? 0);
						$summary->median_amount = ($a + $b) / 2;
					}
				}

				$byCategory = $db->query(
					"SELECT COALESCE(NULLIF(p.category, ''), 'UNCATEGORIZED') AS category, COUNT(*) AS cnt, SUM(p.budget_amount) AS total
					{$joinSql} {$where}
					GROUP BY p.category
					ORDER BY total DESC",
					$params
				) ?: [];
				$trend = $db->query(
					"SELECT DATE_FORMAT(p.date_created, '%Y-%m') AS ym, COUNT(*) AS cnt, SUM(p.budget_amount) AS total
					{$joinSql} {$where}
					GROUP BY ym
					ORDER BY ym ASC",
					$params
				) ?: [];

				echo json_encode([
					'success' => true,
					'summary' => $summary,
					'status' => [
						'pending_ack_count' => (int)($srow->pending_ack_count ?? 0),
						'acknowledged_count' => (int)($srow->acknowledged_count ?? 0),
						'completed_count' => (int)($srow->completed_count ?? 0),
						'rejected_count' => (int)($srow->rejected_count ?? 0),
						'pending_ack_amount' => (float)($srow->pending_ack_amount ?? 0),
						'acknowledged_amount' => (float)($srow->acknowledged_amount ?? 0),
						'completed_amount' => (float)($srow->completed_amount ?? 0),
						'rejected_amount' => (float)($srow->rejected_amount ?? 0),
					],
					'by_category' => $byCategory,
					'trend' => $trend,
				]);
			} catch (\Throwable $e) {
				http_response_code(500);
				echo json_encode(['success' => false, 'message' => 'Failed to load petty cash analytics']);
			}
			exit;
		}

		if ($action === 'ajax_list') {
			try {
				$page = (int)($_GET['page'] ?? 1);
				$limit = (int)($_GET['limit'] ?? 30);
				if ($limit < 5) $limit = 5;
				if ($limit > 100) $limit = 100;
				if ($page < 1) $page = 1;
				$offset = ($page - 1) * $limit;

				$sql = "
					SELECT
						p.*,
						creator.first_name AS creator_first_name,
						creator.last_name AS creator_last_name,
						assignee.first_name AS assignee_first_name,
						assignee.last_name AS assignee_last_name
					{$joinSql}
					{$where}
					ORDER BY p.id DESC
					LIMIT {$limit} OFFSET {$offset}
				";

				$countSql = "SELECT COUNT(*) AS total {$joinSql} {$where}";
				$sumSql = "SELECT SUM(p.budget_amount) AS total_amount {$joinSql} {$where}";
				$metricSql = "
					SELECT
						SUM(CASE WHEN LOWER(COALESCE(p.status, 'pending_ack'))='pending_ack' THEN 1 ELSE 0 END) AS pending_ack_count,
						SUM(CASE WHEN LOWER(COALESCE(p.status, 'pending_ack'))='acknowledged' THEN 1 ELSE 0 END) AS acknowledged_count,
						SUM(CASE WHEN LOWER(COALESCE(p.status, 'pending_ack'))='completed' THEN 1 ELSE 0 END) AS completed_count,
						SUM(CASE WHEN LOWER(COALESCE(p.status, 'pending_ack'))='rejected' THEN 1 ELSE 0 END) AS rejected_count
					{$joinSql}
					{$where}
				";

				$rows = $db->query($sql, $params);
				if ($db->has_error) {
					throw new \RuntimeException('List query failed: ' . $db->error);
				}
				$rows = $rows ?: [];
				$countRow = $db->query($countSql, $params);
				if ($db->has_error) {
					throw new \RuntimeException('Count query failed: ' . $db->error);
				}
				$countRow = $countRow ?: [];
				$sumRow = $db->query($sumSql, $params);
				if ($db->has_error) {
					throw new \RuntimeException('Total query failed: ' . $db->error);
				}
				$sumRow = $sumRow ?: [];
				$metricRow = $db->query($metricSql, $params);
				if ($db->has_error) {
					throw new \RuntimeException('Metrics query failed: ' . $db->error);
				}
				$metricRow = $metricRow ?: [];

				$total = !empty($countRow) ? (int)($countRow[0]->total ?? 0) : 0;
				$totalAmount = !empty($sumRow) ? (float)($sumRow[0]->total_amount ?? 0) : 0.0;
				$totalPages = (int)ceil($total / $limit);
				if ($totalPages < 1) $totalPages = 1;
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
						'pending_ack' => (int)($mrow->pending_ack_count ?? 0),
						'acknowledged' => (int)($mrow->acknowledged_count ?? 0),
						'completed' => (int)($mrow->completed_count ?? 0),
						'rejected' => (int)($mrow->rejected_count ?? 0),
					],
				]);
			} catch (\Throwable $e) {
				error_log('PETTY CASH AJAX LIST FAILED: ' . $e->getMessage() . ' | DB: ' . ($db->error ?? ''));
				http_response_code(500);
				echo json_encode(['success' => false, 'message' => 'Failed to load petty cash transactions: ' . $e->getMessage()]);
			}
			exit;
		}

		$exportRows = $db->query(
			"SELECT
				p.*,
				creator.first_name AS creator_first_name,
				creator.last_name AS creator_last_name,
				assignee.first_name AS assignee_first_name,
				assignee.last_name AS assignee_last_name
			{$joinSql}
			{$where}
			ORDER BY p.date_created DESC, p.id DESC",
			$params
		) ?: [];
		$exportSummary = [
			'total_count' => count($exportRows),
			'total_amount' => 0.0,
		];
		foreach ($exportRows as $exportRow) {
			$exportSummary['total_amount'] += (float)($exportRow->budget_amount ?? 0);
		}

		require plugin_path('views/admin/export_pdf.php');
		exit;
	}

	if (!$req->posted() || $seg1 !== $plugin_route) {
		return;
	}

	if ($seg2 === 'add') {
		require plugin_path('controllers/add-controller.php');
		return;
	}

	if ($seg2 === 'edit') {
		require plugin_path('controllers/edit-controller.php');
		return;
	}

	if ($seg2 === 'delete') {
		require plugin_path('controllers/delete-controller.php');
		return;
	}

	if ($seg2 === 'acknowledge') {
		require plugin_path('controllers/acknowledge-controller.php');
		return;
	}

	if ($seg2 === 'complete') {
		require plugin_path('controllers/complete-controller.php');
		return;
	}
});

add_action('basic-admin_main_content', function () {
	$vars = get_value();
	if (URL(1) !== ($vars['plugin_route'] ?? 'pettycash')) {
		return;
	}

	$session = get_session();
	$userId = (int)($session->id ?? 0);
	$isPrivileged = pettycash_user_is_privileged($session);

	if (!$isPrivileged && !user_can('view_pettycash')) {
		echo '<div class="alert alert-danger text-center">Access denied.</div>';
		return;
	}

	$admin_route = pettycash_resolve_admin_route((string)($vars['admin_route'] ?? 'admin'));
	$plugin_route = $vars['plugin_route'];
	set_value('admin_route', $admin_route);
	$errors = $vars['errors'] ?? [];
	$pettycash = new \PettyCashes\PettyCash;
	$row = null;
	$id = (int)(URL(3) ?? 0);
	if ($id > 0) {
		$row = pettycash_find_row($id);
	}

	if ($row && !pettycash_user_can_access_row($row, $userId, $session)) {
		echo '<div class="alert alert-danger text-center">Access denied.</div>';
		return;
	}

	$assignableUsers = [];
	if ($isPrivileged && in_array((string)(URL(2) ?? ''), ['', 'add', 'edit'], true)) {
		$assignableUsers = pettycash_get_assignable_users($userId);
	}

	$canManage = $isPrivileged;
	$canEdit = $isPrivileged || ($row && (int)($row->created_by ?? 0) === $userId);
	$canAcknowledge = $row && !$isPrivileged && (int)($row->assigned_user_id ?? 0) === $userId && strtolower((string)($row->status ?? 'pending_ack')) === 'pending_ack';
	$canComplete = $row && !$isPrivileged && (int)($row->assigned_user_id ?? 0) === $userId && strtolower((string)($row->status ?? 'pending_ack')) === 'acknowledged';

	if (URL(2) === 'add') {
		if (!$canManage || !user_can('add_pettycash')) {
			echo '<div class="alert alert-danger text-center">Access denied.</div>';
			return;
		}
		require plugin_path('views/admin/add.php');
		return;
	}

	if (URL(2) === 'edit') {
		if (!$canManage || !user_can('edit_pettycash') || empty($row)) {
			echo '<div class="alert alert-danger text-center">Access denied.</div>';
			return;
		}
		require plugin_path('views/admin/edit.php');
		return;
	}

	if (URL(2) === 'delete') {
		if (!$canManage || !user_can('delete_pettycash') || empty($row)) {
			echo '<div class="alert alert-danger text-center">Access denied.</div>';
			return;
		}
		require plugin_path('views/admin/delete.php');
		return;
	}

	if (URL(2) === 'view') {
		if (empty($row)) {
			echo '<div class="alert alert-warning text-center">Transaction not found.</div>';
			return;
		}
		require plugin_path('views/admin/view.php');
		return;
	}

	require plugin_path('views/admin/list.php');
});
