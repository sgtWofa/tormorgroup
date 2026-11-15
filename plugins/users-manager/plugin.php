<?php

/**
 * Plugin name: Users Manager
 * Description: A way for admin to manage users
 * 
 * 
 **/

set_value([

	'admin_route'	=>'admin',
	'plugin_route'	=>'users',
	'tables'		=>[
		'users_table' 		=> 'users',
	],

	'optional_tables'		=>[
		'roles_table' 		=> 'user_roles',
		'permissions_table' => 'role_permissions',
		'roles_map_table' 	=> 'user_roles_map',
	],
	

]);


/** check if all tables exist **/
$db = new \Core\Database;
$tables = get_value()['tables'];

if(!$db->table_exists($tables)){
	dd("Missing database tables in ".plugin_id() ." plugin: ". implode(",", $db->missing_tables));
	die;
}

require_once __DIR__ . '/helpers/shared-account-assignments.php';

if(!function_exists('users_manager_runtime_marker_path'))
{
	function users_manager_runtime_marker_path(string $name): string
	{
		$dir = __DIR__ . DIRECTORY_SEPARATOR . '.runtime';
		if(!is_dir($dir)){
			@mkdir($dir, 0777, true);
		}
		return $dir . DIRECTORY_SEPARATOR . $name;
	}
}

if(!function_exists('users_manager_db_table_columns'))
{
	function users_manager_db_table_columns(\Core\Database $db, string $table, bool $refresh = false): array
	{
		static $cache = [];
		$key = strtolower(trim($table));
		if(!$refresh && isset($cache[$key])){
			return $cache[$key];
		}

		$cache[$key] = [];
		$tableSql = str_replace('`', '``', $table);
		try {
			$rows = $db->query("SHOW COLUMNS FROM `{$tableSql}`") ?: [];
			foreach($rows as $row){
				$field = strtolower(trim((string)($row->Field ?? '')));
				if($field !== ''){
					$cache[$key][] = $field;
				}
			}
		} catch (\Throwable $e) {
			$cache[$key] = [];
		}

		return $cache[$key];
	}
}

if(!function_exists('users_manager_ensure_runtime_schema'))
{
	function users_manager_ensure_runtime_schema(): void
	{
		$marker = users_manager_runtime_marker_path('users_manager_schema_20260505.done');
		$commissionDayColumns = array_keys(users_manager_sim_commission_day_fields());
		if(file_exists($marker)){
			try {
				$db = new \Core\Database;
				$columns = users_manager_db_table_columns($db, 'user_sim_assignments', true);
				$requiredColumns = $commissionDayColumns;
				$branchColumn = $db->query("SHOW COLUMNS FROM users LIKE 'branch_id'");
				if(count(array_intersect($requiredColumns, $columns)) === count($requiredColumns) && !empty($branchColumn)){
					return;
				}
			} catch (\Throwable $e) {
				return;
			}
		}
		try {
			$db = new \Core\Database;
			$disabledColumn = $db->query("SHOW COLUMNS FROM users LIKE 'disabled'");
			if(empty($disabledColumn)){
				$db->query("ALTER TABLE users ADD COLUMN disabled TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER deleted");
			}

			$branchColumn = $db->query("SHOW COLUMNS FROM users LIKE 'branch_id'");
			if(empty($branchColumn)){
				$db->query("ALTER TABLE users ADD COLUMN branch_id INT UNSIGNED DEFAULT NULL AFTER disabled");
			}

			$db->query("
				CREATE TABLE IF NOT EXISTS user_sim_assignments (
					id INT UNSIGNED NOT NULL AUTO_INCREMENT,
					user_id INT UNSIGNED NOT NULL,
					network VARCHAR(50) NOT NULL,
					sim_type VARCHAR(20) NOT NULL,
					phone_number VARCHAR(30) DEFAULT NULL,
					till_number VARCHAR(50) DEFAULT NULL,
					shortcode VARCHAR(50) DEFAULT NULL,
					account_number VARCHAR(100) DEFAULT NULL,
					evd_commission_day SMALLINT UNSIGNED DEFAULT NULL,
					agent_commission_day SMALLINT UNSIGNED DEFAULT NULL,
					interest_commission_day SMALLINT UNSIGNED DEFAULT NULL,
					cashout_commission_day SMALLINT UNSIGNED DEFAULT NULL,
					dstv_commission_day SMALLINT UNSIGNED DEFAULT NULL,
					others_commission_day SMALLINT UNSIGNED DEFAULT NULL,
					ezwich_commission_day SMALLINT UNSIGNED DEFAULT NULL,
					notes VARCHAR(255) DEFAULT NULL,
					disabled TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
					deleted TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
					created_by VARCHAR(100) DEFAULT NULL,
					updated_by VARCHAR(100) DEFAULT NULL,
					date_created DATETIME DEFAULT NULL,
					date_updated DATETIME DEFAULT NULL,
					date_deleted DATETIME DEFAULT NULL,
					PRIMARY KEY (id),
					KEY user_id (user_id),
					KEY network (network),
					KEY sim_type (sim_type),
					KEY deleted (deleted)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
			");

			$previousColumn = 'account_number';
			foreach($commissionDayColumns as $column){
				$columnSql = str_replace('`', '``', $column);
				$existingColumn = $db->query("SHOW COLUMNS FROM user_sim_assignments LIKE '{$columnSql}'");
				if(empty($existingColumn)){
					$afterSql = str_replace('`', '``', $previousColumn);
					$db->query("ALTER TABLE user_sim_assignments ADD COLUMN `{$columnSql}` SMALLINT UNSIGNED DEFAULT NULL AFTER `{$afterSql}`");
				}
				$previousColumn = $column;
			}
			users_manager_db_table_columns($db, 'user_sim_assignments', true);
			@file_put_contents($marker, '1');
		} catch (\Throwable $e) {
			error_log('[users-manager] Runtime schema check failed: ' . $e->getMessage());
		}
	}
}

if(!function_exists('users_manager_clean_sim_value'))
{
	function users_manager_clean_sim_value($value): string
	{
		return trim((string)($value ?? ''));
	}
}

if(!function_exists('users_manager_branch_options'))
{
	function users_manager_branch_options(): array
	{
		if(function_exists('admin_settings_branch_options')){
			return admin_settings_branch_options();
		}
		return [];
	}
}

if(!function_exists('users_manager_sim_commission_day_fields'))
{
	function users_manager_sim_commission_day_fields(): array
	{
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

if(!function_exists('users_manager_bank_network_catalog'))
{
	function users_manager_bank_network_catalog(): array
	{
		return [
			['value' => 'ABSA BANK', 'label' => 'Absa Bank', 'aliases' => ['ABSA', 'ABSA BANK']],
			['value' => 'APEX NAFANA BANK', 'label' => 'Apex Nafana Bank', 'aliases' => ['APEX NAFANA', 'APEX NAFANA BANK']],
			['value' => 'APEX SUMA BANK', 'label' => 'Apex Suma Bank', 'aliases' => ['APEX SUMA', 'APEX SUMA BANK']],
			['value' => 'ACCESS BANK', 'label' => 'Access Bank', 'aliases' => ['ACCESS', 'ACCESS BANK']],
			['value' => 'CAL BANK', 'label' => 'Cal Bank', 'aliases' => ['CAL', 'CALBANK', 'CAL BANK']],
			['value' => 'ECOBANK', 'label' => 'Ecobank', 'aliases' => ['ECOBANK']],
			['value' => 'FIDELITY BANK', 'label' => 'Fidelity Bank', 'aliases' => ['FIDELITY', 'FIDELITY BANK']],
			['value' => 'FIRST NATIONAL BANK (FNB)', 'label' => 'First National Bank (FNB)', 'aliases' => ['FNB', 'FIRST NATIONAL BANK', 'FIRST NATIONAL BANK FNB']],
			['value' => 'FIRST BANK GHANA (FBG)', 'label' => 'First Bank Ghana (FBG)', 'aliases' => ['FBG', 'FIRST BANK GHANA', 'FIRST BANK GHANA FBG']],
			['value' => 'GCB BANK', 'label' => 'GCB Bank', 'aliases' => ['GCB', 'GCB BANK']],
			['value' => 'GT BANK', 'label' => 'GT Bank', 'aliases' => ['GT', 'GT BANK']],
			['value' => 'STANBIC BANK', 'label' => 'Stanbic Bank', 'aliases' => ['STANBIC', 'STANBIC BANK']],
		];
	}
}

if(!function_exists('users_manager_bank_network_lookup_key'))
{
	function users_manager_bank_network_lookup_key($value): string
	{
		$value = strtoupper(users_manager_clean_sim_value($value));
		$value = preg_replace('/[^A-Z0-9]+/', ' ', $value);
		return trim(preg_replace('/\s+/', ' ', $value));
	}
}

if(!function_exists('users_manager_bank_network_alias_map'))
{
	function users_manager_bank_network_alias_map(): array
	{
		static $map = null;
		if($map !== null){
			return $map;
		}

		$map = [];
		foreach(users_manager_bank_network_catalog() as $item){
			$canonical = strtoupper(trim((string)($item['value'] ?? '')));
			if($canonical === ''){
				continue;
			}

			$aliases = $item['aliases'] ?? [];
			$aliases[] = $canonical;
			foreach($aliases as $alias){
				$key = users_manager_bank_network_lookup_key($alias);
				if($key !== ''){
					$map[$key] = $canonical;
				}
			}
		}

		return $map;
	}
}

if(!function_exists('users_manager_bank_network_values'))
{
	function users_manager_bank_network_values(): array
	{
		return array_values(array_map(function($item){
			return strtoupper(trim((string)($item['value'] ?? '')));
		}, users_manager_bank_network_catalog()));
	}
}

if(!function_exists('users_manager_bank_network_options'))
{
	function users_manager_bank_network_options(): array
	{
		return array_values(array_map(function($item){
			return [
				'value' => strtoupper(trim((string)($item['value'] ?? ''))),
				'label' => trim((string)($item['label'] ?? ($item['value'] ?? ''))),
			];
		}, users_manager_bank_network_catalog()));
	}
}

if(!function_exists('users_manager_bank_network_to_channel_key'))
{
	function users_manager_bank_network_to_channel_key($network): string
	{
		$network = users_manager_normalize_sim_network($network);
		if($network === ''){
			return '';
		}

		$key = 'ECASH_' . strtoupper(preg_replace('/[^A-Z0-9]+/i', '_', $network));
		return trim($key, '_');
	}
}

if(!function_exists('users_manager_normalize_role_names'))
{
	function users_manager_normalize_role_names($roles): array
	{
		if(!is_array($roles)){
			$roles = [$roles];
		}

		$normalized = [];
		foreach($roles as $role){
			$role = strtolower(trim((string)$role));
			if($role !== ''){
				$normalized[] = $role;
			}
		}

		return array_values(array_unique($normalized));
	}
}

if(!function_exists('users_manager_role_is_admin_or_general_manager'))
{
	function users_manager_role_is_admin_or_general_manager($roles): bool
	{
		$roles = users_manager_normalize_role_names($roles);
		$allowed = ['admin', 'general manager', 'general_manager', 'generalmanager', 'gm'];
		return (bool)array_intersect($roles, $allowed);
	}
}

if(!function_exists('users_manager_role_names_from_ids'))
{
	function users_manager_role_names_from_ids(array $roleIds): array
	{
		$roleIds = array_values(array_unique(array_filter(array_map('intval', $roleIds), function($id){
			return $id > 0;
		})));
		if(empty($roleIds)){
			return [];
		}

		try {
			$db = new \Core\Database;
			if(!$db->table_exists(['user_roles'])){
				return [];
			}

			$in = implode(',', $roleIds);
			$rows = $db->query("SELECT role FROM user_roles WHERE id IN ({$in})") ?: [];
			$roles = array_map(function($row){
				return $row->role ?? '';
			}, $rows);

			return users_manager_normalize_role_names($roles);
		} catch (\Throwable $e) {
			return [];
		}
	}
}

if(!function_exists('users_manager_allowed_networks_by_type'))
{
	function users_manager_allowed_networks_by_type(array $targetRoleNames = []): array
	{
		$bankNetworks = users_manager_role_is_admin_or_general_manager($targetRoleNames)
			? users_manager_bank_network_values()
			: ['CAL BANK'];

		return [
			'agent' => ['MTN', 'AT', 'TELECEL', 'G-MONEY', 'GHANAPAY', 'ZEEPAY'],
			'evd' => ['MTN', 'AT', 'TELECEL'],
			'whatsapp' => ['MTN', 'AT', 'TELECEL'],
			'bank' => $bankNetworks,
		];
	}
}

if(!function_exists('users_manager_normalize_sim_network'))
{
	function users_manager_normalize_sim_network($network): string
	{
		$network = strtoupper(str_replace(['_', '-'], ' ', users_manager_clean_sim_value($network)));
		$network = preg_replace('/\s+/', ' ', $network);

		if(in_array($network, ['G MONEY', 'GMONEY', 'G-MONEY'], true)){
			return 'G-MONEY';
		}

		$bankAliasMap = users_manager_bank_network_alias_map();
		$bankKey = users_manager_bank_network_lookup_key($network);
		if($bankKey !== '' && isset($bankAliasMap[$bankKey])){
			return $bankAliasMap[$bankKey];
		}

		return $network;
	}
}

if(!function_exists('users_manager_normalize_sim_type'))
{
	function users_manager_normalize_sim_type($type): string
	{
		return strtolower(users_manager_clean_sim_value($type));
	}
}

if(!function_exists('users_manager_is_fidelity_bank_assignment'))
{
	function users_manager_is_fidelity_bank_assignment($type, $network): bool
	{
		return users_manager_normalize_sim_type($type) === 'bank'
			&& users_manager_normalize_sim_network($network) === 'FIDELITY BANK';
	}
}

if(!function_exists('users_manager_default_ezwich_commission_day'))
{
	function users_manager_default_ezwich_commission_day($baseDate = 'now'): int
	{
		try {
			$date = new \DateTime((string)$baseDate, new \DateTimeZone('Africa/Accra'));
		} catch (\Throwable $e) {
			$date = new \DateTime('now', new \DateTimeZone('Africa/Accra'));
		}

		$dayOfWeek = (int)$date->format('N');
		return $dayOfWeek >= 5 ? (8 - $dayOfWeek) : 1;
	}
}

if(!function_exists('users_manager_normalize_sim_assignments'))
{
	function users_manager_normalize_sim_assignments($raw): array
	{
		if(is_string($raw)){
			$decoded = json_decode($raw, true);
			$raw = is_array($decoded) ? $decoded : [];
		}

		if(!is_array($raw)){
			return [];
		}

		$rows = [];
		foreach($raw as $item){
			if(!is_array($item)){
				$item = (array)$item;
			}

			$row = [
				'network' => users_manager_normalize_sim_network($item['network'] ?? ''),
				'sim_type' => users_manager_normalize_sim_type($item['sim_type'] ?? ''),
				'phone_number' => users_manager_clean_sim_value($item['phone_number'] ?? ''),
				'till_number' => users_manager_clean_sim_value($item['till_number'] ?? ''),
				'shortcode' => users_manager_clean_sim_value($item['shortcode'] ?? ''),
				'account_number' => users_manager_clean_sim_value($item['account_number'] ?? ''),
				'expected_commission_day' => users_manager_clean_sim_value($item['expected_commission_day'] ?? ''),
				'notes' => users_manager_clean_sim_value($item['notes'] ?? ''),
			];
			foreach(users_manager_sim_commission_day_fields() as $field => $label){
				$row[$field] = users_manager_clean_sim_value($item[$field] ?? '');
			}
			if($row['sim_type'] === 'evd' && $row['evd_commission_day'] === '' && $row['expected_commission_day'] !== ''){
				$row['evd_commission_day'] = $row['expected_commission_day'];
			}elseif(in_array($row['sim_type'], ['agent', 'bank'], true) && $row['agent_commission_day'] === '' && $row['expected_commission_day'] !== ''){
				$row['agent_commission_day'] = $row['expected_commission_day'];
			}
			$row['expected_commission_day'] = '';

			if(implode('', $row) === ''){
				continue;
			}

			$rows[] = $row;
		}

		return $rows;
	}
}

if(!function_exists('users_manager_validate_sim_assignments'))
{
	function users_manager_validate_sim_assignments(array $rows, array $context = []): array
	{
		$errors = [];
		$allowedTypes = ['agent', 'evd', 'bank', 'whatsapp'];
		$targetRoleNames = users_manager_normalize_role_names($context['target_role_names'] ?? []);
		$allowedNetworksByType = users_manager_allowed_networks_by_type($targetRoleNames);
		$allowedNetworks = array_values(array_unique(array_merge(...array_values($allowedNetworksByType))));
		$bankOptionLabels = array_map(function($item){
			return trim((string)($item['label'] ?? ''));
		}, array_filter(users_manager_bank_network_options(), function($item) use ($allowedNetworksByType){
			return in_array(strtoupper(trim((string)($item['value'] ?? ''))), $allowedNetworksByType['bank'], true);
		}));
		$bankLabelText = !empty($bankOptionLabels) ? implode(', ', $bankOptionLabels) : 'Cal Bank';
		$allNetworkLabels = array_merge(
			$allowedNetworksByType['agent'],
			$allowedNetworksByType['evd'],
			$bankOptionLabels
		);
		$allNetworkLabels = array_values(array_unique(array_filter(array_map('trim', $allNetworkLabels))));

		foreach($rows as $index => $row){
			$rowNo = $index + 1;
			$network = users_manager_normalize_sim_network($row['network'] ?? '');
			$type = users_manager_normalize_sim_type($row['sim_type'] ?? '');
			$phone = users_manager_clean_sim_value($row['phone_number'] ?? '');
			$till = users_manager_clean_sim_value($row['till_number'] ?? '');
			$shortcode = users_manager_clean_sim_value($row['shortcode'] ?? '');
			$accountNumber = users_manager_clean_sim_value($row['account_number'] ?? '');
			$commissionDayFields = users_manager_sim_commission_day_fields();
			if(in_array($type, ['agent', 'bank'], true)){
				$agentCommissionDay = users_manager_clean_sim_value($row['agent_commission_day'] ?? '');
				$legacyExpectedDay = users_manager_clean_sim_value($row['expected_commission_day'] ?? '');
				if($agentCommissionDay === '' && $legacyExpectedDay !== ''){
					$row['agent_commission_day'] = $legacyExpectedDay;
				}
			}elseif($type === 'evd'){
				$evdCommissionDay = users_manager_clean_sim_value($row['evd_commission_day'] ?? '');
				$legacyExpectedDay = users_manager_clean_sim_value($row['expected_commission_day'] ?? '');
				if($evdCommissionDay === '' && $legacyExpectedDay !== ''){
					$row['evd_commission_day'] = $legacyExpectedDay;
				}
			}

			if($network === ''){
				$errors[] = "SIM row {$rowNo}: network is required.";
			}else if($network !== '' && !in_array($network, $allowedNetworks, true)){
				$errors[] = "SIM row {$rowNo}: network must be " . implode(', ', $allNetworkLabels) . ".";
			}

			if($type === ''){
				$errors[] = "SIM row {$rowNo}: SIM type is required.";
			}else if(!in_array($type, $allowedTypes, true)){
				$errors[] = "SIM row {$rowNo}: SIM type must be Agent, EVD or Bank.";
			}else if($network !== '' && !in_array($network, $allowedNetworksByType[$type], true)){
				$labels = [
					'agent' => 'MTN, AT, TELECEL, G-MONEY, GHANAPAY or ZEEPAY',
					'evd' => 'MTN, AT or TELECEL',
					'whatsapp' => 'MTN, AT or TELECEL',
					'bank' => $bankLabelText,
				];
				$errors[] = "SIM row {$rowNo}: {$type} network must be " . $labels[$type] . ".";
			}

			if($type === 'bank'){
				if($accountNumber === ''){
					$errors[] = "SIM row {$rowNo}: account number is required for bank type.";
				}
			}

			foreach($commissionDayFields as $field => $label){
				if($type === 'evd' && $field !== 'evd_commission_day'){
					continue;
				}
				if(in_array($type, ['agent', 'bank'], true) && $field === 'evd_commission_day'){
					continue;
				}
				if($type === 'bank' && in_array($field, ['interest_commission_day', 'dstv_commission_day'], true)){
					continue;
				}
				$value = users_manager_clean_sim_value($row[$field] ?? '');
				if($value === ''){
					continue;
				}
				if(!ctype_digit($value)){
					$errors[] = "SIM row {$rowNo}: {$label} must be a number between 1 and 365.";
				}else{
					$commissionDay = (int)$value;
					if($commissionDay < 1 || $commissionDay > 365){
						$errors[] = "SIM row {$rowNo}: {$label} must be between 1 and 365.";
					}
				}
			}

			if($type === 'bank'){
				continue;
			}

			if($network === 'TELECEL'){
				if($till === ''){
					$errors[] = "SIM row {$rowNo}: till number is required for TELECEL.";
				}
				if($shortcode === ''){
					$errors[] = "SIM row {$rowNo}: shortcode is required for TELECEL.";
				}
			}else if($network === 'AT' && $type === 'evd'){
				if($phone === ''){
					$errors[] = "SIM row {$rowNo}: phone number is required for AT EVD.";
				}
			}else if(in_array($network, ['AT', 'G-MONEY'], true)){
				if($shortcode === ''){
					$errors[] = "SIM row {$rowNo}: shortcode is required for {$network}.";
				}
			}else if(in_array($network, ['GHANAPAY', 'ZEEPAY', 'MTN'], true)){
				if($phone === ''){
					$errors[] = "SIM row {$rowNo}: phone number is required for {$network}.";
				}
			}
		}

		return $errors;
	}
}

if(!function_exists('users_manager_fetch_sim_assignments'))
{
	function users_manager_fetch_sim_assignments(int $userId): array
	{
		try {
			$db = new \Core\Database;
			$rows = $db->query(
				"SELECT * FROM user_sim_assignments WHERE user_id = :user_id AND deleted = 0 ORDER BY network ASC, sim_type ASC, id ASC",
				['user_id' => (string)$userId]
			);
			if(is_array($rows)){
				foreach($rows as $row){
					if(!is_object($row)){
						continue;
					}
					$type = users_manager_normalize_sim_type($row->sim_type ?? '');
					$legacyExpectedDay = users_manager_clean_sim_value($row->expected_commission_day ?? '');
					if($legacyExpectedDay === ''){
						continue;
					}
					if($type === 'evd' && empty($row->evd_commission_day)){
						$row->evd_commission_day = $legacyExpectedDay;
					}elseif(in_array($type, ['agent', 'bank'], true) && empty($row->agent_commission_day)){
						$row->agent_commission_day = $legacyExpectedDay;
					}
				}
			}

			return is_array($rows) ? $rows : [];
		} catch (\Throwable $e) {
			error_log('[users-manager] Fetch SIM assignments failed: ' . $e->getMessage());
			return [];
		}
	}
}

if(!function_exists('users_manager_validate_sim_assignment_uniqueness'))
{
	function users_manager_validate_sim_assignment_uniqueness(int $userId, array $rows): array
	{
		$errors = [];
		$db = new \Core\Database;
		$uniqueFields = [
			'phone_number' => 'Phone number',
			'till_number' => 'Till number',
			'shortcode' => 'Shortcode',
			'account_number' => 'Account number',
		];
		$seen = [];

		foreach($rows as $index => $row){
			$rowNo = $index + 1;
			$network = users_manager_normalize_sim_network($row['network'] ?? '');
			$type = users_manager_normalize_sim_type($row['sim_type'] ?? '');
			$scopeLabel = $network !== '' ? " for {$network}" : '';

			foreach($uniqueFields as $column => $label){
				$value = users_manager_clean_sim_value($row[$column] ?? '');
				if($value === ''){
					continue;
				}

				// Allow phone_number duplicate checks to be skipped if the current row is WhatsApp
				if($type === 'whatsapp' && $column === 'phone_number'){
					continue;
				}

				$seenKey = strtolower(($network !== '' ? $network : 'any-network') . '|' . $column . '|' . $value);
				if(isset($seen[$seenKey])){
					$errors[] = "SIM row {$rowNo}: {$label} '{$value}' is repeated{$scopeLabel} in this form.";
					continue;
				}
				$seen[$seenKey] = true;

				$params = [
					'user_id' => (string)$userId,
					'value' => strtolower($value),
				];
				$networkSql = '';
				if($network !== ''){
					$networkSql = ' AND s.network = :network';
					$params['network'] = $network;
				}

				// Exclude existing WhatsApp database records when performing uniqueness checks for non-WhatsApp rows
				$match = $db->query(
					"SELECT s.user_id, s.network, u.first_name, u.last_name, u.email
					 FROM user_sim_assignments s
					 LEFT JOIN users u ON u.id = s.user_id
					 WHERE s.deleted = 0
					 AND s.sim_type != 'whatsapp'
					 AND s.user_id != :user_id
					 {$networkSql}
					 AND LOWER(TRIM(s.{$column})) = :value
					 LIMIT 1",
					$params
				);
				if(!empty($db->has_error)){
					$errors[] = "SIM row {$rowNo}: unable to check duplicate {$label}. " . $db->error;
					continue;
				}

				if(!empty($match)){
					$owner = trim((string)($match[0]->first_name ?? '') . ' ' . (string)($match[0]->last_name ?? ''));
					if($owner === ''){
						$owner = 'user ID ' . (string)($match[0]->user_id ?? 'unknown');
					}
					$errors[] = "SIM row {$rowNo}: {$label} '{$value}' already exists{$scopeLabel} on another user ({$owner}).";
				}
			}
		}

		return $errors;
	}
}


if(!function_exists('users_manager_save_sim_assignments'))
{
	function users_manager_save_sim_assignments(int $userId, array $rows, int $actorUserId, array $context = []): void
	{
		$errors = users_manager_validate_sim_assignments($rows, $context);
		if(!empty($errors)){
			throw new \InvalidArgumentException(implode(' ', $errors));
		}

		users_manager_ensure_runtime_schema();
		$errors = users_manager_validate_sim_assignment_uniqueness($userId, $rows);
		if(!empty($errors)){
			throw new \InvalidArgumentException(implode(' ', $errors));
		}

		$db = new \Core\Database;
		$now = date('Y-m-d H:i:s');
		$assignmentColumns = users_manager_db_table_columns($db, 'user_sim_assignments');

		$db->query(
			"UPDATE user_sim_assignments
			 SET deleted = 1, disabled = 1, date_deleted = :date_deleted, date_updated = :date_updated, updated_by = :updated_by
			 WHERE user_id = :user_id AND deleted = 0",
			[
				'date_deleted' => $now,
				'date_updated' => $now,
				'updated_by' => (string)$actorUserId,
				'user_id' => (string)$userId,
			]
		);
		if(!empty($db->has_error)){
			throw new \RuntimeException('Unable to update existing assigned SIM data: ' . $db->error);
		}

		foreach($rows as $row){
			$columns = [];
			$values = [];
			$params = [];
			$add = static function(string $column, string $placeholder, $value) use (&$columns, &$values, &$params): void {
				$columns[] = $column;
				$values[] = $placeholder;
				$params[ltrim($placeholder, ':')] = $value;
			};

			if(in_array('user_id', $assignmentColumns, true)){
				$add('user_id', ':user_id', (string)$userId);
			}
			if(in_array('network', $assignmentColumns, true)){
				$add('network', ':network', users_manager_normalize_sim_network($row['network'] ?? ''));
			}
			if(in_array('sim_type', $assignmentColumns, true)){
				$add('sim_type', ':sim_type', users_manager_normalize_sim_type($row['sim_type'] ?? ''));
			}
			if(in_array('phone_number', $assignmentColumns, true)){
				$add('phone_number', ':phone_number', users_manager_clean_sim_value($row['phone_number'] ?? ''));
			}
			if(in_array('till_number', $assignmentColumns, true)){
				$add('till_number', ':till_number', users_manager_clean_sim_value($row['till_number'] ?? ''));
			}
			if(in_array('shortcode', $assignmentColumns, true)){
				$add('shortcode', ':shortcode', users_manager_clean_sim_value($row['shortcode'] ?? ''));
			}
			if(in_array('account_number', $assignmentColumns, true)){
				$add('account_number', ':account_number', users_manager_clean_sim_value($row['account_number'] ?? ''));
			}
			if(in_array('expected_commission_day', $assignmentColumns, true)){
				$add('expected_commission_day', ':expected_commission_day', null);
			}
			foreach(users_manager_sim_commission_day_fields() as $field => $label){
				if(!in_array($field, $assignmentColumns, true)){
					continue;
				}

				$type = users_manager_normalize_sim_type($row['sim_type'] ?? '');
				$network = users_manager_normalize_sim_network($row['network'] ?? '');
				$dayValue = '';
				if($type === 'evd' && $field === 'evd_commission_day'){
					$dayValue = users_manager_clean_sim_value($row[$field] ?? '');
					if($dayValue === ''){
						$dayValue = users_manager_clean_sim_value($row['expected_commission_day'] ?? '');
					}
				}elseif(in_array($type, ['agent', 'bank'], true) && $field !== 'evd_commission_day'){
					$dayValue = users_manager_clean_sim_value($row[$field] ?? '');
				}
				if($type === 'bank' && in_array($field, ['interest_commission_day', 'dstv_commission_day'], true)){
					$dayValue = '';
				}
				if($field === 'ezwich_commission_day' && !users_manager_is_fidelity_bank_assignment($type, $network)){
					$dayValue = '';
				}
				if($field === 'ezwich_commission_day' && $dayValue === '' && users_manager_is_fidelity_bank_assignment($row['sim_type'] ?? '', $row['network'] ?? '')){
					$dayValue = (string)users_manager_default_ezwich_commission_day();
				}
				$add($field, ':' . $field, $dayValue !== '' ? (int)$dayValue : null);
			}
			if(in_array('notes', $assignmentColumns, true)){
				$add('notes', ':notes', users_manager_clean_sim_value($row['notes'] ?? ''));
			}
			if(in_array('disabled', $assignmentColumns, true)){
				$columns[] = 'disabled';
				$values[] = '0';
			}
			if(in_array('deleted', $assignmentColumns, true)){
				$columns[] = 'deleted';
				$values[] = '0';
			}
			if(in_array('created_by', $assignmentColumns, true)){
				$add('created_by', ':created_by', (string)$actorUserId);
			}
			if(in_array('date_created', $assignmentColumns, true)){
				$add('date_created', ':date_created', $now);
			}

			$sql = "INSERT INTO user_sim_assignments (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ")";
			$db->query($sql, $params);
			if(!empty($db->has_error)){
				throw new \RuntimeException('Unable to save assigned SIM data: ' . $db->error);
			}
		}
	}
}

users_manager_ensure_runtime_schema();


/** set user permissions for this plugin **/
add_filter('permissions',function($permissions){

	$permissions[] = 'all';
	$permissions[] = 'view_users';
	$permissions[] = 'view_user_details';
	$permissions[] = 'reset_password';
	$permissions[] = 'add_user';
	$permissions[] = 'edit_user';
	$permissions[] = 'delete_user';

	return $permissions;
});


/** add to amin links **/
/*add_filter('basic-admin_before_admin_links',function($links){

	if(user_can('view_users'))
	{
		$vars = get_value();

		$obj = (object)[];
		$obj->title = 'Users';
		$obj->link = ROOT . '/'.$vars['admin_route'].'/'.$vars['plugin_route'];
		$obj->icon = 'fa-solid fa-people-group';
		$obj->parent = 0;
		$links[] = $obj;
	}

	return $links;
});*/


/** run this after a form submit **/
add_action('controller',function(){

	$req = new \Core\Request;
	$vars = get_value();
	
	$admin_route = $vars['admin_route'];
	$plugin_route = $vars['plugin_route'];

	if(URL(1) == $vars['plugin_route'] && $req->posted())
	{
		$ses = new \Core\Session;
		$user = new \UsersManager\User;
		$user_roles_map = new \UsersManager\User_roles_map;

		$id = URL(3) ?? null;
		if($id)
			$row = $user->first(['id'=>$id]);

		if(URL(2) == 'add'){
			require plugin_path('controllers/add-controller.php');
		}else
		if(URL(2) == 'edit'){
			
			require plugin_path('controllers/edit-controller.php');
		}else
		if(URL(2) == 'delete'){

			require plugin_path('controllers/delete-controller.php');
		}else
		if(URL(2) == 'restore'){

			require plugin_path('controllers/restore-controller.php');
		}else
		if(URL(2) == 'resetpassword'){

			require plugin_path('controllers/resetpassword-controller.php');
		}

	}
});

add_action('controller',function(){
	$vars = get_value();
	if(URL(1) != $vars['plugin_route'] || URL(2) != 'ajax'){
		return;
	}
	while (ob_get_level() > 0) {
		ob_end_clean();
	}
	ini_set('display_errors', '0');
	error_reporting(E_ERROR | E_PARSE);
	header('Content-Type: application/json; charset=utf-8');

	if(!user_can('view_users')){
		http_response_code(403);
		echo json_encode(['success' => false, 'message' => 'Access denied']);
		exit;
	}

	$admin_route = $vars['admin_route'];
	$plugin_route = $vars['plugin_route'];
	$user = new \UsersManager\User;

	$limit = (int)($_GET['limit'] ?? 10);
	$limit = $limit > 0 ? $limit : 10;
	$limit = max(5, $limit);
	$pageNum = (int)($_GET['page'] ?? 1);
	$pageNum = $pageNum > 0 ? $pageNum : 1;
	$offset = ($pageNum - 1) * $limit;

	$searchTerm = trim($_GET['find'] ?? '');
	$selectedRole = trim($_GET['role'] ?? '');
	$showDeleted = !empty($_GET['deleted']) && user_can('delete_user');
	$conditions = ["u.deleted = " . ($showDeleted ? "1" : "0")];
	$params = [];

	if($searchTerm !== '')
	{
		$conditions[] = "(u.first_name LIKE :find OR u.last_name LIKE :find OR u.email LIKE :find OR u.phone LIKE :find)";
		$params['find'] = '%' . $searchTerm . '%';
	}

	if($selectedRole !== '')
	{
		$conditions[] = "EXISTS (SELECT 1 FROM user_roles_map urm JOIN user_roles ur ON ur.id = urm.role_id WHERE urm.user_id = u.id AND urm.disabled = 0 AND ur.role = :role)";
		$params['role'] = $selectedRole;
	}

	$filterSql = implode(' AND ', $conditions);
	$user::$query_id = 'get-users';
	$rows = $user->query(
		"SELECT u.*, b.name AS branch_name
		 FROM users u
		 LEFT JOIN branches b ON b.id = u.branch_id
		 WHERE $filterSql
		 ORDER BY u.date_created DESC
		 LIMIT $limit OFFSET $offset",
		$params
	) ?? [];
	if(!is_array($rows)) $rows = [];

	$countRow = $user->query("SELECT COUNT(*) AS total FROM users u WHERE $filterSql", $params);
	$totalRows = (int)($countRow[0]->total ?? 0);
	$totalPages = (int)max(1, (int)ceil($totalRows / max(1, $limit)));
	if($pageNum > $totalPages){
		$pageNum = $totalPages;
	}

	$roleBadgeClass = function(string $role): string {
		$key = strtolower(trim($role));
		$map = [
			'admin' => 'bg-danger',
			'operations manager' => 'bg-primary',
			'solo agent' => 'bg-success',
			'momo agent' => 'bg-warning text-dark',
		];
		return $map[$key] ?? 'bg-secondary';
	};

	$cardsHtml = '';
	$tableHtml = '';
	$startIndex = $offset + 1;
	$session = get_session();
	$currentUserId = $session->id ?? null;

	if(!empty($rows)){
		$idCounter = $startIndex;
		foreach($rows as $row){
			$uid = (int)($row->id ?? 0);
			$name = esc(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''));
			$branchName = trim((string)($row->branch_name ?? ''));
			$branchLabel = $branchName !== '' ? esc($branchName) : 'Unassigned';
			$phone = esc((string)($row->phone ?? ''));
			$email = esc((string)($row->email ?? ''));
			$img = get_image($row->image ?? null, $row->gender ?? null);
			$profileUrl = ROOT . '/'.$admin_route.'/'.$plugin_route.'/view/'.$uid;
			$editUrl = ROOT . '/'.$admin_route.'/'.$plugin_route.'/edit/'.$uid;
			$resetUrl = ROOT . '/'.$admin_route.'/'.$plugin_route.'/resetpassword/'.$uid;
			$deleteUrl = ROOT . '/'.$admin_route.'/'.$plugin_route.'/delete/'.$uid;
			$restoreUrl = ROOT . '/'.$admin_route.'/'.$plugin_route.'/restore/'.$uid;
			$isDeleted = (int)($row->deleted ?? 0) === 1;
			$isDisabled = (int)($row->disabled ?? 0) === 1;
			$statusBadge = $isDeleted ? '<span class="badge bg-warning text-dark ms-1">Deleted</span>' : ($isDisabled ? '<span class="badge bg-danger ms-1">Disabled</span>' : '<span class="badge bg-success ms-1">Active</span>');
			$roles = $row->roles ?? [];
			if(!is_array($roles)) $roles = [];

			$rolesBadges = '';
			if(!empty($roles)){
				foreach($roles as $r){
					$rolesBadges .= '<span class="badge '.esc($roleBadgeClass((string)$r)).' border">'.esc((string)$r).'</span>';
				}
			}else{
				$rolesBadges = '<span class="text-muted small">No roles</span>';
			}

			$actions = '';
			if(user_can('view_profile') || $currentUserId === $uid){
				$actions .= '<li><a class="dropdown-item" href="'.esc($profileUrl).'">Profile</a></li>';
			}
			if(!$isDeleted && (user_can('edit_user') || $currentUserId === $uid)){
				$actions .= '<li><a class="dropdown-item" href="'.esc($editUrl).'">Edit user</a></li>';
			}
			if(!$isDeleted && (user_can('reset_password') || $currentUserId === $uid)){
				$actions .= '<li><a class="dropdown-item" href="'.esc($resetUrl).'">Reset password</a></li>';
			}
			if($isDeleted && user_can('delete_user')){
				$actions .= '<li><a class="dropdown-item text-success" href="'.esc($restoreUrl).'">Restore user</a></li>';
			}elseif(user_can('delete_user') || $currentUserId === $uid){
				$actions .= '<li><a class="dropdown-item text-danger" href="'.esc($deleteUrl).'">Delete</a></li>';
			}

			$cardsHtml .= '<div class="card border-0 shadow-sm"><div class="card-body"><div class="row g-3 align-items-center">';
			$cardsHtml .= '<div class="col-12 col-sm-4"><div class="d-flex align-items-center gap-2">';
			$cardsHtml .= '<a href="'.esc($profileUrl).'" class="text-decoration-none"><img src="'.esc($img).'" alt="'.esc($name).'" class="rounded-circle" width="44" height="44" style="object-fit: cover;"></a>';
			$cardsHtml .= '<div><div class="fw-semibold">'.$name.' '.$statusBadge.'</div><div class="text-muted small">Branch: '.$branchLabel.'</div>';
			if($phone !== '') $cardsHtml .= '<div class="text-muted small">'.$phone.'</div>';
			if($email !== '') $cardsHtml .= '<div class="text-muted small">'.$email.'</div>';
			$cardsHtml .= '</div></div></div>';
			$cardsHtml .= '<div class="col-12 col-sm-4 text-sm-end">';
			if(!empty($roles)){
				$cardsHtml .= '<div class="d-flex flex-wrap gap-1 justify-content-sm-end">'.$rolesBadges.'</div>';
			}else{
				$cardsHtml .= $rolesBadges;
			}
			$cardsHtml .= '</div>';
			$cardsHtml .= '<div class="col-12 col-sm-4 text-sm-end"><div class="dropdown d-inline-block">';
			$cardsHtml .= '<button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButtonSm'.$uid.'" data-bs-toggle="dropdown" aria-expanded="false">Actions</button>';
			$cardsHtml .= '<ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButtonSm'.$uid.'">'.$actions.'</ul>';
			$cardsHtml .= '</div></div>';
			$cardsHtml .= '</div></div></div>';
			$idCounter++;
		}

		$idCounter = $startIndex;
		foreach($rows as $row){
			$uid = (int)($row->id ?? 0);
			$img = get_image($row->image ?? null, $row->gender ?? null);
			$name = esc(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''));
			$branchName = trim((string)($row->branch_name ?? ''));
			$branchLabel = $branchName !== '' ? esc($branchName) : 'Unassigned';
			$gender = esc(ucfirst((string)($row->gender ?? '')));
			$phone = esc((string)($row->phone ?? ''));
			$email = esc((string)($row->email ?? ''));
			$created = get_date($row->date_created ?? null);
			$updated = get_date($row->date_updated ?? null);
			$profileUrl = ROOT . '/'.$admin_route.'/'.$plugin_route.'/view/'.$uid;
			$editUrl = ROOT . '/'.$admin_route.'/'.$plugin_route.'/edit/'.$uid;
			$resetUrl = ROOT . '/'.$admin_route.'/'.$plugin_route.'/resetpassword/'.$uid;
			$deleteUrl = ROOT . '/'.$admin_route.'/'.$plugin_route.'/delete/'.$uid;
			$restoreUrl = ROOT . '/'.$admin_route.'/'.$plugin_route.'/restore/'.$uid;
			$isDeleted = (int)($row->deleted ?? 0) === 1;
			$isDisabled = (int)($row->disabled ?? 0) === 1;
			$statusBadge = $isDeleted ? '<span class="badge bg-warning text-dark ms-1">Deleted</span>' : ($isDisabled ? '<span class="badge bg-danger ms-1">Disabled</span>' : '<span class="badge bg-success ms-1">Active</span>');
			$roles = $row->roles ?? [];
			if(!is_array($roles)) $roles = [];

			// Rebuild actions for each row to avoid using stale values from card loop
			$actions = '';
			if(user_can('view_profile') || $currentUserId === $uid){
				$actions .= '<li><a class="dropdown-item" href="'.esc($profileUrl).'">Profile</a></li>';
			}
			if(!$isDeleted && (user_can('edit_user') || $currentUserId === $uid)){
				$actions .= '<li><a class="dropdown-item" href="'.esc($editUrl).'">Edit user</a></li>';
			}
			if(!$isDeleted && (user_can('reset_password') || $currentUserId === $uid)){
				$actions .= '<li><a class="dropdown-item" href="'.esc($resetUrl).'">Reset password</a></li>';
			}
			if($isDeleted && user_can('delete_user')){
				$actions .= '<li><a class="dropdown-item text-success" href="'.esc($restoreUrl).'">Restore user</a></li>';
			}elseif(user_can('delete_user') || $currentUserId === $uid){
				$actions .= '<li><a class="dropdown-item text-danger" href="'.esc($deleteUrl).'">Delete</a></li>';
			}

			$rolesCell = '';
			if(!empty($roles)){
				$rolesCell .= '<div class="d-flex flex-wrap gap-1">';
				foreach($roles as $r){
					$rolesCell .= '<span class="badge '.esc($roleBadgeClass((string)$r)).' border">'.esc((string)$r).'</span>';
				}
				$rolesCell .= '</div>';
			}else{
				$rolesCell = '<span class="text-muted small">No roles</span>';
			}

			$tableHtml .= '<tr>';
			$tableHtml .= '<td>'.$idCounter.'</td>';
			$tableHtml .= '<td><div class="d-flex align-items-center gap-2"><a href="'.esc($profileUrl).'" class="text-decoration-none"><img src="'.esc($img).'" alt="'.esc($name).'" class="rounded-circle" width="48" height="48" style="object-fit: cover;"></a><div><a href="'.esc($profileUrl).'" class="fw-semibold text-decoration-none">'.$name.'</a> '.$statusBadge.'<div class="text-muted small">Branch: '.$branchLabel.'</div><p class="text-muted small mb-0">'.$gender.'</p></div></div></td>';
			$tableHtml .= '<td><div class="text-muted small">'.$phone.'</div><div>'.$email.'</div></td>';
			$tableHtml .= '<td>'.$rolesCell.'</td>';
			$tableHtml .= '<td>'.$created.'</td>';
			$tableHtml .= '<td>'.$updated.'</td>';
			$tableHtml .= '<td class="text-end"><div class="dropdown"><button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButton'.$uid.'" data-bs-toggle="dropdown" aria-expanded="false">Actions</button><ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton'.$uid.'">'.$actions.'</ul></div></td>';
			$tableHtml .= '</tr>';
			$idCounter++;
		}
	}

	$paginationHtml = '';
	$paginationHtml .= '<nav aria-label="Users pagination"><ul class="pagination justify-content-center mb-0">';
	$prev = max(1, $pageNum - 1);
	$next = min($totalPages, $pageNum + 1);
	$paginationHtml .= '<li class="page-item'.($pageNum <= 1 ? ' disabled' : '').'"><a class="page-link" href="#" data-page="1">First</a></li>';
	$paginationHtml .= '<li class="page-item'.($pageNum <= 1 ? ' disabled' : '').'"><a class="page-link" href="#" data-page="'.$prev.'">Prev</a></li>';
	$start = max(1, $pageNum - 2);
	$end = min($totalPages, $pageNum + 2);
	for($x = $start; $x <= $end; $x++){
		$paginationHtml .= '<li class="page-item'.($x === $pageNum ? ' active' : '').'"><a class="page-link" href="#" data-page="'.$x.'">'.$x.'</a></li>';
	}
	$paginationHtml .= '<li class="page-item'.($pageNum >= $totalPages ? ' disabled' : '').'"><a class="page-link" href="#" data-page="'.$next.'">Next</a></li>';
	$paginationHtml .= '<li class="page-item'.($pageNum >= $totalPages ? ' disabled' : '').'"><a class="page-link" href="#" data-page="'.$totalPages.'">Last</a></li>';
	$paginationHtml .= '</ul></nav>';

	echo json_encode([
		'success' => true,
		'page' => $pageNum,
		'limit' => $limit,
		'totalRows' => $totalRows,
		'totalPages' => $totalPages,
		'cardsHtml' => $cardsHtml,
		'tableHtml' => $tableHtml,
		'paginationHtml' => $paginationHtml,
	]);
	exit;
});


/** displays the view file **/
add_action('basic-admin_main_content',function(){

	$ses = new \Core\Session;
	$vars = get_value();

	$admin_route = $vars['admin_route'];
	$plugin_route = $vars['plugin_route'];
	
	$errors = $vars['errors'] ?? [];

	$user = new \UsersManager\User;
	$sim = new \UsersManager\Sim;
	$transaction = new \UsersManager\Transaction;
	$wallet = new \UsersManager\Wallet;


	if(URL(1) == $vars['plugin_route']){

		$id = URL(3) ?? null;
		if($id){
			$user::$query_id = 'get-users';
			$row = $user->first(['id'=>$id]);
			$isDeletedUserRecord = !empty($row) && (int)($row->deleted ?? 0) === 1;
			if($isDeletedUserRecord && in_array(URL(2), ['edit', 'delete', 'resetpassword'], true)){
				$row = false;
			}
		}

		$session = get_session();
		if (!empty($session)) {
			$user_id = $session->id;
		}

		if(URL(2) == 'add'){

			$user_role = new \UsersManager\User_role;
			require plugin_path('views/add.php');
		}else
		if(URL(2) == 'edit'){
			
			$user_role = new \UsersManager\User_role;
			require plugin_path('views/edit.php');
		}else
		if(URL(2) == 'delete'){

			require plugin_path('views/delete.php');
		}else
		if(URL(2) == 'restore'){

			require plugin_path('views/restore.php');
		}else
		if (URL(2) == 'view') {

		    //sort all sims associated with this user	    
			$limit = 10;
			$pager = new \Core\Pager($limit);
			$offset = $pager->offset;

			$sim->limit = $limit;
			$sim->offset = $offset;
			$sims = $sim->where(['assigned'=>$id]);

		    //sort all wallets associated with this user	    
			$wallet->limit = $limit;
			$wallet->offset = $offset;
			$wallets = $wallet->where(['assigned'=>$id]);
		    
		    //sort all transactions associated with this user		    
			$transaction->limit = $limit;
			$transaction->offset = $offset;
			$query = "SELECT * FROM transactions WHERE created_by = $id ORDER BY id DESC LIMIT $limit OFFSET $offset";
			$trans = $transaction->query($query);
			//dd(['trans'=>$trans]);
		    
		    //sort all sims associated with this user
			$sims = $sim->where(['assigned'=>$id]);

			//for superior ranks
			$query = "SELECT SUM(amount) AS total_amount, SUM(commission) AS total_commission FROM sims WHERE deleted = 0 AND assigned = $id AND transaction_status = :transaction_status";
			$total_unassigned = $sim->query($query, ['transaction_status'=>'Unassigned']);
			if (!empty($total_unassigned)) {			
				$totalUnassigned_amount = $total_unassigned[0]->total_amount ?? 0;
				$totalUnassigned_commission = $total_unassigned[0]->total_commission ?? 0;
			}

			$total_assigned = $sim->query($query, ['transaction_status'=>'Assigned']);
			if (!empty($total_assigned)) {
				$totalAssigned_amount = $total_assigned[0]->total_amount ?? 0;
				$totalAssigned_commission = $total_assigned[0]->total_commission ?? 0;
			}

			$total_soldWholesale = $sim->query($query, ['transaction_status'=>'Sold - wholesale']);
			if (!empty($total_soldWholesale)) {				
				$totalSoldWholesale_amount = $total_soldWholesale[0]->total_amount ?? 0;
				$totalSoldWholesale_commission = $total_soldWholesale[0]->total_commission ?? 0;
			}

			$total_soldRetail = $sim->query($query, ['transaction_status'=>'Sold - retail']);
			if (!empty($total_soldRetail)) {
				$totalSoldRetail_amount = $total_soldRetail[0]->total_amount ?? 0;
				$totalSoldRetail_commission = $total_soldRetail[0]->total_commission ?? 0;
			}

			$total_expired = $sim->query($query, ['transaction_status'=>'Expired']);
			if (!empty($total_expired)) {
				$totalExpired_amount = $total_expired[0]->total_amount ?? 0;
				$totalExpired_commission = $total_expired[0]->total_commission ?? 0;
			}
			
			$query = "SELECT SUM(amount) AS total_amount, SUM(commission) AS total_commission FROM sims WHERE deleted = 0 AND assigned = $id";
			$total = $sim->query($query);
			if (!empty($total)) {
				$total_amount = $total[0]->total_amount ?? 0;
				$total_commission = $total[0]->total_commission ?? 0;
			}
			//dd(['sims'=>$sims, 'total'=>$total]);
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
		            a.id AS trans_id, 
		            a.network AS trans_network, 
		            a.transaction_type AS trans_transaction_type, 
		            a.category AS trans_category, 
		            a.phone AS trans_phone, 
		            a.amount AS trans_amount, 
		            a.ussd AS trans_ussd,
		            a.account_number AS trans_account_number,
		            a.destination_bank AS trans_destination_bank,
		            a.balance AS trans_balance, 
		            a.balance_before AS trans_balance_before, 
		            a.cash_source AS trans_cash_source, 
		            a.receiver AS trans_receiver, 
		            a.sender_phone AS trans_sender_phone, 
		            a.full_name AS trans_full_name, 
		            a.dob AS trans_dob, 
		            a.house_number AS trans_house_number, 
		            a.id_type AS trans_id_type, 
		            a.id_number AS trans_id_number, 
		            a.next_of_king AS trans_next_of_king, 
		            a.nok_phone AS trans_nok_phone, 
		            a.nok_relation AS trans_nok_relation, 
		            a.transaction_status AS trans_transaction_status, 
		            a.charge AS trans_charge, 
		            a.slug AS trans_slug, 
		            a.disabled AS trans_disabled, 
		            a.deleted AS trans_deleted, 
		            a.created_by AS trans_created_by, 
		            a.updated_by AS trans_updated_by, 
		            a.date_created AS trans_date_created, 
		            a.date_updated AS trans_date_updated, 
		            a.date_deleted AS trans_date_deleted
		        FROM users AS m 
		        LEFT JOIN users AS u ON m.created_by = u.id 
		        LEFT JOIN users AS u_updated ON m.updated_by = u_updated.id 
		        LEFT JOIN agencybankings AS a ON m.id = a.created_by 
		        WHERE m.id = :id";

		    // Execute the query and get all results
		    $rows = $user->query($query, ['id' => $id]);

		    // Initialize an array to hold all transactions
		    $transactions = [];

		    if (!empty($rows)) {
		        foreach ($rows as $row) {
		            // Group transaction fields into an array for each transaction
		            $transactions[] = [
		                'id' => $row->trans_id,
		                'network' => $row->trans_network,
		                'transaction_type' => $row->trans_transaction_type,
		                'category' => $row->trans_category,
		                'phone' => $row->trans_phone,
		                'amount' => $row->trans_amount,
		                'ussd' => $row->trans_ussd,
		                'account_number' => $row->trans_account_number,
		                'destination_bank' => $row->trans_destination_bank,
		                'balance' => $row->trans_balance,
		                'balance_before' => $row->trans_balance_before,
		                'cash_source' => $row->trans_cash_source,
		                'receiver' => $row->trans_receiver,
		                'sender_phone' => $row->trans_sender_phone,
		                'full_name' => $row->trans_full_name,
		                'dob' => $row->trans_dob,
		                'house_number' => $row->trans_house_number,
		                'id_type' => $row->trans_id_type,
		                'id_number' => $row->trans_id_number,
		                'next_of_king' => $row->trans_next_of_king,
		                'nok_phone' => $row->trans_nok_phone,
		                'nok_relation' => $row->trans_nok_relation,
		                'transaction_status' => $row->trans_transaction_status,
		                'charge' => $row->trans_charge,
		                'slug' => $row->trans_slug,
		                'disabled' => $row->trans_disabled,
		                'deleted' => $row->trans_deleted,
		                'created_by' => $row->trans_created_by,
		                'updated_by' => $row->trans_updated_by,
		                'date_created' => $row->trans_date_created,
		                'date_updated' => $row->trans_date_updated,
		                'date_deleted' => $row->trans_date_deleted
		            ];
		        }
		    }

		    //dd($transactions); // Output the array of transactions

		    // Check if the query was successful and if any results were returned
		    if (empty($transactions)) {
		        // Handle the error or empty result
		        echo "Error: No results found or query failed.";
		    }

			$userSimAssignments = function_exists('users_manager_fetch_sim_assignments')
				? users_manager_fetch_sim_assignments((int)$id)
				: [];
			$userSharedAccountAssignments = [];
			$userSharedAccountAssignmentError = '';
			try {
				$userSharedAccountAssignments = users_manager_user_shared_account_keys((int)$id);
			} catch (\Throwable $e) {
				error_log('[users-manager] Shared-account assignments could not be loaded for profile: ' . $e->getMessage());
				$userSharedAccountAssignmentError = 'Shared-account assignments are temporarily unavailable.';
			}

		    require plugin_path('views/view.php');
		}else
		if(URL(2) == 'resetpassword'){

			require plugin_path('views/resetpassword.php');
		}else
		{
			$limit = 10;
			$pager = new \Core\Pager($limit);
			$offset = $pager->offset;

			$searchTerm = trim($_GET['find'] ?? '');
			$selectedRole = trim($_GET['role'] ?? '');
			$showDeleted = URL(2) === 'deleted' && user_can('delete_user');
			$conditions = ["u.deleted = " . ($showDeleted ? "1" : "0")];
			$params = [];

			if($searchTerm !== '')
			{
				$conditions[] = "(u.first_name LIKE :find OR u.last_name LIKE :find OR u.email LIKE :find OR u.phone LIKE :find)";
				$params['find'] = '%' . $searchTerm . '%';
			}

			if($selectedRole !== '')
			{
				$conditions[] = "EXISTS (SELECT 1 FROM user_roles_map urm JOIN user_roles ur ON ur.id = urm.role_id WHERE urm.user_id = u.id AND urm.disabled = 0 AND ur.role = :role)";
				$params['role'] = $selectedRole;
			}

			$filterSql = implode(' AND ', $conditions);
			$user::$query_id = 'get-users';
			$query = "SELECT u.*, b.name AS branch_name
				FROM users u
				LEFT JOIN branches b ON b.id = u.branch_id
				WHERE $filterSql
				ORDER BY u.date_created DESC
				LIMIT $limit OFFSET $offset";
			$rows = $user->query($query,$params) ?? [];

			$roleModel = new \UsersManager\User_role;
			$roleOptions = $roleModel->where(['disabled'=>0]) ?? [];

			$deletedFilter = $showDeleted ? 1 : 0;
			$totalUsersRow = $user->query("SELECT COUNT(*) AS total FROM users WHERE deleted = {$deletedFilter}");
			$totalUsers = (int)($totalUsersRow[0]->total ?? 0);
			$monthStart = date('Y-m-01 00:00:00');
			$monthEnd = date('Y-m-t 23:59:59');
			$newUsersRow = $user->query("SELECT COUNT(*) AS total FROM users WHERE deleted = {$deletedFilter} AND date_created BETWEEN :start AND :end", ['start'=>$monthStart,'end'=>$monthEnd]);
			$newUsers = (int)($newUsersRow[0]->total ?? 0);
			$withRolesRow = $user->query("SELECT COUNT(DISTINCT urm.user_id) AS total FROM user_roles_map urm JOIN users u ON u.id = urm.user_id WHERE urm.disabled = 0 AND u.deleted = {$deletedFilter}");
			$withRoles = (int)($withRolesRow[0]->total ?? 0);
			$recentlyUpdatedRow = $user->query("SELECT COUNT(*) AS total FROM users WHERE deleted = {$deletedFilter} AND date_updated >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
			$recentlyUpdated = (int)($recentlyUpdatedRow[0]->total ?? 0);
			$unassigned = max($totalUsers - $withRoles, 0);
			$staleProfiles = max($totalUsers - $recentlyUpdated, 0);

			$stats = [
				[
					'label' => $showDeleted ? 'Deleted users' : 'Total users',
					'value' => $totalUsers,
					'highlight' => $showDeleted ? 'Removed records' : 'Active records',
					'subLabel' => $showDeleted ? 'Created this month' : 'New this month',
					'subValue' => $newUsers,
				],
				[
					'label' => 'Profiles with roles',
					'value' => $withRoles,
					'highlight' => 'Role-mapped accounts',
					'subLabel' => 'Unassigned',
					'subValue' => $unassigned,
				],
				[
					'label' => 'Recently updated',
					'value' => $recentlyUpdated,
					'highlight' => 'Touched last 7 days',
					'subLabel' => 'Need review',
					'subValue' => $staleProfiles,
				],
			];

			require plugin_path('views/list.php');
		}

	}
});


/** for manipulating data after a query operation **/
add_filter('after_query',function($data){

	
	if(empty($data['result']))
		return $data;

	if($data['query_id'] == 'get-users')
	{
		$role_map = new \UsersManager\User_roles_map;
		foreach ($data['result'] as $key => $row) {
			//dd(['row'=>$row]);
			
			$query = "select * from user_roles where disabled = 0 && id in (select role_id from user_roles_map where disabled = 0 && user_id = :user_id)";
			$roles = $role_map->query($query,['user_id'=>$row->id]);
			if($roles)
				$data['result'][$key]->roles = array_column($roles, 'role');
			
			/** get user's roles **/
			$user_roles_map = new \UsersManager\User_roles_map;
				
			$role_ids = $user_roles_map->where(['user_id'=>$row->id,'disabled'=>0]);
			if($role_ids)
				$data['result'][$key]->role_ids = array_column($role_ids, 'role_id');

		}
	}


	return $data;
});

