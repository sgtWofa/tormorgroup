<?php

if (!function_exists('users_manager_shared_account_catalog')) {
	function users_manager_shared_account_catalog(): array
	{
		return [
			'MTN_MOMOPAY' => [
				'label' => 'MTN MoMoPay',
				'aliases' => ['MTN_MOMOPAY', 'MTN-MOMOPAY', 'MTN MOMOPAY', 'MTN_MOMOPAYOUT', 'MTN-MOMOPAYOUT'],
			],
			'ADB' => ['label' => 'ADB Bank', 'aliases' => ['ADB', 'ADB BANK']],
			'ABSA' => ['label' => 'Absa Bank', 'aliases' => ['ABSA', 'ABSA BANK', 'ABSA FORMERLY BARCLAYS', 'ABSA FORMERLY BARCLAYS BANK', 'BARCLAYS', 'BARCLAYS BANK']],
			'APEX_NAFANA' => ['label' => 'Apex Nafana Bank', 'aliases' => ['APEX NAFANA', 'APEX NAFANA BANK', 'NAFANA BANK', 'NAFANA RURAL BANK']],
			'APEX_SUMA' => ['label' => 'Apex Suma Bank', 'aliases' => ['APEX SUMA', 'APEX SUMA BANK', 'SUMA BANK', 'SUMA RURAL BANK']],
			'ACCESS' => ['label' => 'Access Bank', 'aliases' => ['ACCESS', 'ACCESS BANK']],
			'CAL' => ['label' => 'Cal Bank', 'aliases' => ['CAL', 'CAL BANK', 'CALBANK']],
			'ECOBANK' => ['label' => 'Ecobank', 'aliases' => ['ECOBANK', 'ECOBANK BANK']],
			'FIDELITY' => ['label' => 'Fidelity Bank', 'aliases' => ['FIDELITY', 'FIDELITY BANK']],
			'FNB' => ['label' => 'First National Bank (FNB)', 'aliases' => ['FNB', 'FNB BANK', 'FIRST NATIONAL BANK', 'FIRST NATIONAL BANK FNB']],
			'FBN' => ['label' => 'First Bank Nigeria (FBN)', 'aliases' => ['FBN', 'FBN BANK', 'FIRST BANK NIGERIA']],
			'FBG' => ['label' => 'First Bank Ghana (FBG)', 'aliases' => ['FBG', 'FBG BANK', 'FIRST BANK GHANA', 'FIRST BANK GHANA FBG']],
			'GCB' => ['label' => 'GCB Bank', 'aliases' => ['GCB', 'GCB BANK']],
			'GTBANK' => ['label' => 'GTBank', 'aliases' => ['GT', 'GT BANK', 'GTBANK', 'GTBANK BANK']],
			'STANBIC' => ['label' => 'Stanbic Bank', 'aliases' => ['STANBIC', 'STANBIC BANK']],
		];
	}
}

if (!function_exists('users_manager_normalize_shared_account_key')) {
	function users_manager_normalize_shared_account_key($key): ?string
	{
		$key = strtoupper(trim((string)$key));
		$key = preg_replace('/^ECASH[\s_-]*/', '', $key);
		$compact = preg_replace('/[^A-Z0-9]/', '', (string)$key);
		if ($compact === '') {
			return null;
		}

		static $aliases = null;
		if ($aliases === null) {
			$aliases = [];
			foreach (users_manager_shared_account_catalog() as $canonical => $account) {
				foreach (array_merge([$canonical], $account['aliases']) as $alias) {
					$aliasCompact = preg_replace('/[^A-Z0-9]/', '', strtoupper((string)$alias));
					$aliases[$aliasCompact] = $canonical;
				}
			}
		}

		return $aliases[$compact] ?? null;
	}
}

if (!function_exists('users_manager_ensure_shared_account_assignments_schema')) {
	function users_manager_ensure_shared_account_assignments_schema(): void
	{
		static $ready = false;
		if ($ready) {
			return;
		}

		$db = new \Core\Database;
		$db->query("
			CREATE TABLE IF NOT EXISTS user_shared_account_assignments (
				id INT UNSIGNED NOT NULL AUTO_INCREMENT,
				user_id INT UNSIGNED NOT NULL,
				account_key VARCHAR(64) NOT NULL,
				assigned_by INT UNSIGNED NOT NULL DEFAULT 0,
				date_created DATETIME NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY user_account_key (user_id, account_key),
				KEY account_key (account_key)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
		");
		if (!empty($db->has_error)) {
			throw new \RuntimeException('Unable to prepare shared-account assignments: ' . (string)$db->error);
		}
		$ready = true;
	}
}

if (!function_exists('users_manager_normalize_shared_account_keys')) {
	function users_manager_normalize_shared_account_keys($raw): array
	{
		if (is_string($raw)) {
			$decoded = json_decode($raw, true);
			if (!is_array($decoded)) {
				throw new \InvalidArgumentException('Shared-account selection is invalid. Refresh and try again.');
			}
			$raw = $decoded;
		}
		if (!is_array($raw)) {
			throw new \InvalidArgumentException('Shared-account selection is invalid. Refresh and try again.');
		}

		$keys = [];
		foreach ($raw as $value) {
			if (!is_string($value) && !is_numeric($value)) {
				throw new \InvalidArgumentException('Shared-account selection contains an invalid account.');
			}
			$key = users_manager_normalize_shared_account_key($value);
			if ($key === null) {
				throw new \InvalidArgumentException('Shared-account selection contains an unsupported account.');
			}
			$keys[$key] = true;
		}

		return array_values(array_intersect(array_keys(users_manager_shared_account_catalog()), array_keys($keys)));
	}
}

if (!function_exists('users_manager_shared_account_roles_eligible')) {
	function users_manager_shared_account_roles_eligible(array $roleNames): bool
	{
		$eligibleRoles = [
			'ADMIN',
			'ISADMIN',
			'OPERATIONMANAGER',
			'ISOPERATIONMANAGER',
			'OPERATIONSMANAGER',
			'ISOPERATIONSMANAGER',
			'OPMANAGER',
			'ISOPMANAGER',
			'OPSMANAGER',
			'ISOPSMANAGER',
			'GENERALMANAGER',
			'ISGENERALMANAGER',
			'ADMINISTRATOR',
			'ISADMINISTRATOR',
			'GM',
			'ISGM',
		];
		foreach ($roleNames as $roleName) {
			$role = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim((string)$roleName)));
			if (in_array($role, $eligibleRoles, true)) {
				return true;
			}
		}

		return false;
	}
}

if (!function_exists('users_manager_shared_account_role_names_for_user')) {
	function users_manager_shared_account_role_names_for_user(int $userId): array
	{
		if ($userId <= 0) {
			return [];
		}
		$db = new \Core\Database;
		$rows = $db->query(
			"SELECT r.role
			 FROM user_roles_map urm
			 INNER JOIN user_roles r ON r.id = urm.role_id
			 WHERE urm.user_id = :user_id
			   AND (urm.disabled IS NULL OR urm.disabled = 0)
			   AND (r.disabled IS NULL OR r.disabled = 0)",
			['user_id' => $userId]
		) ?: [];
		if (!empty($db->has_error)) {
			throw new \RuntimeException('Unable to verify shared-account eligibility: ' . (string)$db->error);
		}

		return array_values(array_filter(array_map(
			static fn($row): string => trim((string)($row->role ?? '')),
			$rows
		)));
	}
}

if (!function_exists('users_manager_shared_account_role_names_from_ids')) {
	function users_manager_shared_account_role_names_from_ids(array $roleIds): array
	{
		$roleIds = array_values(array_unique(array_filter(array_map('intval', $roleIds), static fn($id): bool => $id > 0)));
		if (empty($roleIds)) {
			return [];
		}

		$db = new \Core\Database;
		$rows = $db->query(
			"SELECT role
			 FROM user_roles
			 WHERE id IN (" . implode(',', $roleIds) . ")
			   AND (disabled IS NULL OR disabled = 0)"
		) ?: [];
		if (!empty($db->has_error)) {
			throw new \RuntimeException('Unable to verify selected shared-account roles: ' . (string)$db->error);
		}

		return array_values(array_filter(array_map(
			static fn($row): string => trim((string)($row->role ?? '')),
			$rows
		)));
	}
}

if (!function_exists('users_manager_fetch_shared_account_assignments')) {
	function users_manager_fetch_shared_account_assignments(int $userId): array
	{
		if ($userId <= 0) {
			return [];
		}
		users_manager_ensure_shared_account_assignments_schema();
		$db = new \Core\Database;
		$rows = $db->query(
			"SELECT account_key
			 FROM user_shared_account_assignments
			 WHERE user_id = :user_id
			 ORDER BY account_key ASC",
			['user_id' => $userId]
		) ?: [];
		if (!empty($db->has_error)) {
			throw new \RuntimeException('Unable to load shared-account assignments: ' . (string)$db->error);
		}

		$assigned = [];
		foreach ($rows as $row) {
			$key = users_manager_normalize_shared_account_key($row->account_key ?? '');
			if ($key !== null) {
				$assigned[$key] = true;
			}
		}

		return array_values(array_intersect(array_keys(users_manager_shared_account_catalog()), array_keys($assigned)));
	}
}

if (!function_exists('users_manager_fetch_shared_account_assignment_owners')) {
	function users_manager_fetch_shared_account_assignment_owners(): array
	{
		users_manager_ensure_shared_account_assignments_schema();
		$db = new \Core\Database;
		$rows = $db->query(
			"SELECT a.account_key, a.user_id, u.first_name, u.last_name
			 FROM user_shared_account_assignments a
			 LEFT JOIN users u ON u.id = a.user_id
			 ORDER BY a.account_key ASC, u.first_name ASC, u.last_name ASC, a.user_id ASC"
		) ?: [];
		if (!empty($db->has_error)) {
			throw new \RuntimeException('Unable to load shared-account assignment owners: ' . (string)$db->error);
		}

		$owners = [];
		foreach ($rows as $row) {
			$key = users_manager_normalize_shared_account_key($row->account_key ?? '');
			$userId = (int)($row->user_id ?? 0);
			if ($key === null || $userId <= 0 || isset($owners[$key][$userId])) {
				continue;
			}
			$name = trim((string)($row->first_name ?? '') . ' ' . (string)($row->last_name ?? ''));
			$owners[$key][$userId] = [
				'user_id' => $userId,
				'name' => $name !== '' ? $name : 'User #' . $userId,
			];
		}

		foreach ($owners as $key => $accountOwners) {
			$owners[$key] = array_values($accountOwners);
		}

		return $owners;
	}
}

if (!function_exists('users_manager_user_shared_account_keys')) {
	function users_manager_user_shared_account_keys(int $userId): array
	{
		$roleNames = users_manager_shared_account_role_names_for_user($userId);
		if (!users_manager_shared_account_roles_eligible($roleNames)) {
			return [];
		}

		return users_manager_fetch_shared_account_assignments($userId);
	}
}

if (!function_exists('users_manager_save_shared_account_assignments')) {
	function users_manager_save_shared_account_assignments(int $userId, array $accountKeys, int $actorUserId): void
	{
		if ($userId <= 0) {
			throw new \InvalidArgumentException('A valid user is required for shared-account assignment.');
		}
		$keys = users_manager_normalize_shared_account_keys($accountKeys);
		$targetRoleNames = users_manager_shared_account_role_names_for_user($userId);
		if (!users_manager_shared_account_roles_eligible($targetRoleNames) && !empty($keys)) {
			throw new \InvalidArgumentException('Shared accounts may only be assigned to admins, operations managers, or general managers.');
		}
		if (!users_manager_shared_account_roles_eligible($targetRoleNames)) {
			$keys = [];
		}

		users_manager_ensure_shared_account_assignments_schema();
		$db = new \Core\Database;
		$now = date('Y-m-d H:i:s');
		try {
			$db->query('START TRANSACTION');
			if (!empty($db->has_error)) {
				throw new \RuntimeException('Unable to start shared-account assignment update: ' . (string)$db->error);
			}
			$existingRows = $db->query(
				"SELECT account_key
				 FROM user_shared_account_assignments
				 WHERE user_id = :user_id",
				['user_id' => $userId]
			) ?: [];
			if (!empty($db->has_error)) {
				throw new \RuntimeException('Unable to lock previous shared-account assignments: ' . (string)$db->error);
			}
			$accountKeysToLock = $keys;
			foreach ($existingRows as $existingRow) {
				$existingKey = users_manager_normalize_shared_account_key($existingRow->account_key ?? '');
				if ($existingKey !== null) {
					$accountKeysToLock[] = $existingKey;
				}
			}
			$accountKeysToLock = array_values(array_unique($accountKeysToLock));
			sort($accountKeysToLock, SORT_STRING);
			foreach ($accountKeysToLock as $accountKey) {
				$db->query(
					"SELECT id
					 FROM user_shared_account_assignments
					 WHERE account_key = :account_key
					 FOR UPDATE",
					['account_key' => $accountKey]
				);
				if (!empty($db->has_error)) {
					throw new \RuntimeException('Unable to lock shared-account assignment: ' . (string)$db->error);
				}
			}
			$db->query(
				"DELETE FROM user_shared_account_assignments WHERE user_id = :user_id",
				['user_id' => $userId]
			);
			if (!empty($db->has_error)) {
				throw new \RuntimeException('Unable to remove previous shared-account assignments: ' . (string)$db->error);
			}
			foreach ($keys as $key) {
				$db->query(
					"DELETE FROM user_shared_account_assignments
					 WHERE account_key = :account_key AND user_id <> :user_id",
					['account_key' => $key, 'user_id' => $userId]
				);
				if (!empty($db->has_error)) {
					throw new \RuntimeException('Unable to transfer shared-account assignment: ' . (string)$db->error);
				}
				$db->query(
					"INSERT INTO user_shared_account_assignments (user_id, account_key, assigned_by, date_created)
					 VALUES (:user_id, :account_key, :assigned_by, :date_created)",
					[
						'user_id' => $userId,
						'account_key' => $key,
						'assigned_by' => max(0, $actorUserId),
						'date_created' => $now,
					]
				);
				if (!empty($db->has_error)) {
					throw new \RuntimeException('Unable to save shared-account assignments: ' . (string)$db->error);
				}
			}
			$db->query('COMMIT');
			if (!empty($db->has_error)) {
				throw new \RuntimeException('Unable to complete shared-account assignment update: ' . (string)$db->error);
			}
		} catch (\Throwable $e) {
			try {
				$db->query('ROLLBACK');
			} catch (\Throwable $rollbackError) {
				error_log('[users-manager] Shared-account assignment rollback failed: ' . $rollbackError->getMessage());
			}
			throw $e;
		}
	}
}
